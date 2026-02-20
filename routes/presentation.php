<?php

use Illuminate\Support\Facades\Route;
use Trafficdesign\Presentation\Http\Controllers\PresentationController;

$prefix = config('presentation.route_prefix', 'presentations');
$middleware = config('presentation.middleware', ['web', 'auth']);

Route::middleware($middleware)->prefix($prefix)->group(function () {
    // Lookup + Create
    Route::get('/by-name/{name}', [PresentationController::class, 'lookup'])
        ->name('presentation.lookup');

    Route::post('/', [PresentationController::class, 'create'])
        ->name('presentation.create');

    // Present + Edit
    Route::get('/{presentation}', [PresentationController::class, 'present'])
        ->name('presentation.show')
        ->where('presentation', '[0-9]+');

    Route::get('/{presentation}/edit', [PresentationController::class, 'edit'])
        ->name('presentation.edit')
        ->where('presentation', '[0-9]+');

    // Aktionen
    Route::post('/{presentation}/save', [PresentationController::class, 'save'])
        ->name('presentation.save')
        ->where('presentation', '[0-9]+');

    Route::post('/{presentation}/regenerate', [PresentationController::class, 'regenerate'])
        ->name('presentation.regenerate')
        ->where('presentation', '[0-9]+');

    Route::post('/{presentation}/rename', [PresentationController::class, 'rename'])
        ->name('presentation.rename')
        ->where('presentation', '[0-9]+');

    Route::post('/{presentation}/slides', [PresentationController::class, 'addSlide'])
        ->name('presentation.slides.add')
        ->where('presentation', '[0-9]+');

    Route::delete('/{presentation}/slides/{slideId}', [PresentationController::class, 'removeSlide'])
        ->name('presentation.slides.remove')
        ->where('presentation', '[0-9]+');

    // Legacy
    Route::post('/{presentation}/overrides', [PresentationController::class, 'saveOverrides'])
        ->name('presentation.overrides')
        ->where('presentation', '[0-9]+');

    Route::post('/{presentation}/order', [PresentationController::class, 'saveOrder'])
        ->name('presentation.order')
        ->where('presentation', '[0-9]+');
});
