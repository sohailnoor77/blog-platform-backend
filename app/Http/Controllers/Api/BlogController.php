<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Author\StoreBlogRequest;
use App\Http\Requests\Author\UpdateBlogRequest;
use App\Jobs\SendPostPublishedEmail;
use App\Models\Blog;
use App\Models\BlogComment;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $search = $request->query('search');
        $page = $request->query('page', 1);

        // Unique cache key per user, with optional search
        $cacheKey = $search
            ? "user:$userId:blogs_search:" . Str::slug($search) . ":page:$page"
            : "user:$userId:blogs_page:$page";

        $blogs = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($userId, $search) {
            $query = Blog::select(['id', 'title', 'excerpt', 'image', 'published_at'])
                ->where('user_id', $userId)
                ->withCount('comments')
                ->latest();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('keywords', 'like', "%{$search}%")
                        ->orWhere('meta_title', 'like', "%{$search}%")
                        ->orWhere('meta_description', 'like', "%{$search}%");
                });
            }

            return $query->paginate(10);
        });

        $formattedBlogs = $blogs->getCollection()->map(function (Blog $blog) {
            $blog->published_at = Carbon::parse($blog->published_at)->format('d-M-Y H:i A');
            return $blog;
        });

        $blogs->setCollection($formattedBlogs);

        return response()->json([
            'message' => $search ? 'Search results fetched.' : 'Blogs Data Fetched.',
            'blogs' => $blogs->items(),
            'meta' => [
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'per_page' => $blogs->perPage(),
                'total' => $blogs->total(),
            ]
        ]);
    }


    public function show(Blog $blog)
    {
        // authorization check
        if ($blog->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized - You cannot view this blog post.'], 403);
        }

        // Use Redis to cache the blog details for 10 minutes
        $cacheKey = "blog:{$blog->id}";

        $cachedBlog = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($blog) {
            $blog->load(['comments.user']);

            return [
                ...$blog->toArray(),
                'published_at' => $blog->published_at ? Carbon::parse($blog->published_at)->format('d-M-Y h:i A') : 'Not Published',
                'scheduled_at' => $blog->scheduled_at ? Carbon::parse($blog->scheduled_at)->format('d-M-Y h:i A') : 'Not Scheduled',
                'comments' => $blog->comments->map(function (BlogComment $comment) {
                    return [
                        'id' => $comment->id,
                        'body' => $comment->body,
                        'user' => [
                            'id' => $comment->user->id,
                            'name' => $comment->user->name,
                        ],
                        'created_at' => $comment->created_at->format('d-M-Y h:i A'),
                    ];
                }),
            ];
        });

        return response()->json([
            'message' => 'Blog Data Fetched.',
            'blog' => $cachedBlog
        ]);
    }

    public function store(StoreBlogRequest $request)
    {
        try {

            $data = $request->validated();

            // image upload
            if ($request->hasFile('image')) {
                $file = $request->file('image');

                // Get the original name and sanitize it by replacing spaces with underscores
                $originalFileName = $file->getClientOriginalName();
                $sanitizedFileName = str_replace(' ', '_', $originalFileName);

                // Append a random number to avoid duplicate file names
                $rand = rand();
                $finalFileName = $rand . '_' . $sanitizedFileName;

                // Move the file to the desired directory
                $file->move('images/blogs', $finalFileName);

                // Create the URL for the file
                $data['image'] = url('images/blogs/' . $finalFileName);
            }

            $data['user_id'] = Auth::id();
            $data['keywords'] = collect(explode(',', $request->post('keywords')))
                ->map(fn($tag) => trim($tag))
                ->filter()
                ->values()
                ->toArray();

            // Create the blog post
            $blogPost = Blog::create($data);

            $now = now();

            if (!$request->filled('scheduled_at')) {
                // No schedule, publish immediately
                $blogPost->update(['published_at' => $now]);
                SendPostPublishedEmail::dispatch($blogPost);
            } else {
                $scheduledAt = Carbon::parse($request->post('scheduled_at'));

                // If scheduled_at is within 2 mins range, publish now
                if ($scheduledAt->isBetween($now->copy()->subMinute(), $now->copy()->addMinutes(2))) {
                    $blogPost->update(['published_at' => $now]);
                    SendPostPublishedEmail::dispatch($blogPost);
                }
            }

            // clear cache
            Cache::flush();

            return response()->json([
                'message' => 'Blog post created successfully.',
                'data' => $blogPost
            ]);
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    public function update(UpdateBlogRequest $request, Blog $blog)
    {
        try {
            // authorization check
            if ($blog->user_id !== Auth::id()) {
                return response()->json(['error' => 'Unauthorized - You cannot update this blog post.'], 403);
            }

            $data = $request->validated();

            if ($request->hasFile('image')) {
                // removing previous image
                if ($blog->image && str_contains($blog->image, url('/images/blogs'))) {
                    $existingFilePath = public_path('images/blogs/' . basename($blog->image));
                    if (file_exists($existingFilePath)) {
                        unlink($existingFilePath);
                    }
                }

                $file = $request->file('image');

                $originalFileName = $file->getClientOriginalName();
                $sanitizedFileName = str_replace(' ', '_', $originalFileName);
                $rand = rand();
                $finalFileName = $rand . '_' . $sanitizedFileName;

                $file->move('images/blogs', $finalFileName);

                $data['image'] = url('images/blogs/' . $finalFileName);
            }

            $data['keywords'] = collect(explode(',', $request->post('keywords')))
                ->map(fn($tag) => trim($tag))
                ->filter()
                ->values()
                ->toArray();

            // Determine if scheduled_at is updated and whether to publish now
            $newSchedule = $request->post('scheduled_at');
            $now = now();

            // Update post
            $blog->update($data);

            if (!$newSchedule) {
                // No schedule, publish immediately if not already published
                if (!$blog->published_at) {
                    $blog->update(['published_at' => $now]);
                    SendPostPublishedEmail::dispatch($blog);
                }
            } else {
                $newScheduledAt = Carbon::parse($newSchedule);

                // Dispatch now if it's within the next 2 minutes and not already published
                if ($newScheduledAt->isBetween($now->copy()->subMinute(), $now->copy()->addMinutes(2)) && !$blog->published_at) {
                    $blog->update(['published_at' => $now]);
                    SendPostPublishedEmail::dispatch($blog);
                }
            }

            // clear cache
            Cache::flush();

            return response()->json([
                'message' => 'Blog post updated successfully.',
                'blog' => $blog
            ]);
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    public function destroy(Blog $blog)
    {
        try {
            // authorization check
            if ($blog->user_id !== Auth::id()) {
                return response()->json(['error' => 'Unauthorized - You cannot delete this blog post.'], 403);
            }

            // delete blog image
            if ($blog->image && str_contains($blog->image, url('/images/blogs'))) {
                $existingFilePath = public_path('images/blogs/' . basename($blog->image));
                if (file_exists($existingFilePath)) {
                    unlink($existingFilePath);
                }
            }

            $blog->delete();

            // clear cache
            Cache::flush();

            return response()->json([
                'message' => 'Blog post deleted successfully.'
            ]);
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }
}
