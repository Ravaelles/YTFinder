<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index']);
Route::get('/dashboard', [DashboardController::class, 'index']);

Route::prefix('api')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'apiIndex']);
    Route::get('/health', function () {
        return [
            'ok' => true,
            'scanStrategy' => 'channels.forHandle -> playlistItems.uploads -> videos.details',
        ];
    });

    Route::get('/channels', [ChannelController::class, 'index']);
    Route::post('/channels/scan', [ChannelController::class, 'scan']);
    Route::get('/channels/{channel}', [ChannelController::class, 'show']);
    Route::post('/channels/{channel}/refresh', [ChannelController::class, 'refresh']);
    Route::get('/channels/{channel}/videos', [ChannelController::class, 'videos']);

    Route::patch('/videos/{video}/favorite', [VideoController::class, 'favorite']);
    Route::post('/videos/{video}/click', [VideoController::class, 'click']);
});
