<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Author\StoreBlogCommentRequest;
use App\Models\Blog;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class BlogCommentController extends Controller
{
    public function store(StoreBlogCommentRequest $request, Blog $blog)
    {
        try {

            $comment = $blog->comments()->create([
                'body' => $request->post('body'),
                'user_id' => Auth::id(),
            ])->load('user:id,name');

            // Convert to array so we can customize the response structure
            $commentData = $comment->toArray();
            $commentData['created_at'] = Carbon::parse($comment->created_at)->format('d-M-Y h:i A');

            // clear cache
            Cache::flush();

            return response()->json([
                'message' => 'Comment posted successfully.',
                'comment' => $commentData,
            ]);
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }
}
