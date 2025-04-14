<?php

use App\Http\Controllers\Api\BlogCommentController;
use App\Http\Controllers\Api\BlogController;
use Illuminate\Support\Facades\Route;

Route::prefix('/author')->group(function () {
    // blogs 
    Route::get('/blogs', [BlogController::class, 'index']);
    Route::get('/blog/{blog}', [BlogController::class, 'show']);
    Route::post('/blog/store', [BlogController::class, 'store']);
    Route::post('/blog/update/{blog}', [BlogController::class, 'update']);
    Route::delete('/blog/delete/{blog}', [BlogController::class, 'destroy']);

    // comments
    Route::post('/blogs/{blog}/comments', [BlogCommentController::class, 'store']);
});
