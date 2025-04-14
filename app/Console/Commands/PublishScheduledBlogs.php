<?php

namespace App\Console\Commands;

use App\Jobs\SendPostPublishedEmail;
use App\Models\Blog;
use Illuminate\Console\Command;

class PublishScheduledBlogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:publish-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish scheduled blog posts and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();

        $posts = Blog::whereNull('published_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($posts as $post) {
            $post->update(['published_at' => $now]);
            SendPostPublishedEmail::dispatch($post);
            $this->info("Published: {$post->title}");
        }

        return 0;
    }
}