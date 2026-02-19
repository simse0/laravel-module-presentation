<?php

use Illuminate\Support\Facades\Route;
use Trafficdesign\Presentation\Http\Controllers\PresentationController;

$prefix = config('presentation.route_prefix', 'presentations');
$middleware = config('presentation.middleware', ['web', 'auth']);

Route::middleware($middleware)->prefix($prefix)->group(function () {
    Route::get('/{subject}', [PresentationController::class, 'show'])
        ->name('presentation.show')
        ->where('subject', '[0-9]+');

    Route::post('/{subject}/overrides', [PresentationController::class, 'saveOverrides'])
        ->name('presentation.overrides')
        ->where('subject', '[0-9]+');

    Route::post('/{subject}/order', [PresentationController::class, 'saveOrder'])
        ->name('presentation.order')
        ->where('subject', '[0-9]+');
});
