<?php

use Illuminate\Support\Facades\Route;
use Trafficdesign\Presentation\Http\Controllers\PresentationController;

$prefix = config('presentation.route_prefix', 'presentations');
$middleware = config('presentation.middleware', ['web', 'auth']);

// Signed-URL Route fuer Headless Chrome Rendering (kein Auth noetig)
Route::middleware(['web'])->prefix($prefix)->group(function () {
    Route::get('/{presentation}/render', [PresentationController::class, 'render'])
        ->name('presentation.render')
        ->where('presentation', '[0-9]+');
});

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

    // PDF-Export (async via Queue)
    Route::post('/{presentation}/export-pdf', [PresentationController::class, 'exportPdf'])
        ->name('presentation.export-pdf')
        ->where('presentation', '[0-9]+');

    Route::get('/{presentation}/export-pdf/status', [PresentationController::class, 'exportPdfStatus'])
        ->name('presentation.export-pdf.status')
        ->where('presentation', '[0-9]+');

    Route::get('/{presentation}/export-pdf/download', [PresentationController::class, 'exportPdfDownload'])
        ->name('presentation.export-pdf.download')
        ->where('presentation', '[0-9]+');

    // PPTX-Export (async via Queue)
    Route::post('/{presentation}/export-pptx', [PresentationController::class, 'exportPptx'])
        ->name('presentation.export-pptx')
        ->where('presentation', '[0-9]+');

    Route::get('/{presentation}/export-pptx/status', [PresentationController::class, 'exportPptxStatus'])
        ->name('presentation.export-pptx.status')
        ->where('presentation', '[0-9]+');

    Route::get('/{presentation}/export-pptx/download', [PresentationController::class, 'exportPptxDownload'])
        ->name('presentation.export-pptx.download')
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

    // Images
    Route::post('/{presentation}/images', [PresentationController::class, 'uploadImage'])
        ->name('presentation.images.upload')
        ->where('presentation', '[0-9]+');

    Route::delete('/{presentation}/images/{imageId}', [PresentationController::class, 'deleteImage'])
        ->name('presentation.images.delete')
        ->where('presentation', '[0-9]+');

    // Legacy
    Route::post('/{presentation}/overrides', [PresentationController::class, 'saveOverrides'])
        ->name('presentation.overrides')
        ->where('presentation', '[0-9]+');

    Route::post('/{presentation}/order', [PresentationController::class, 'saveOrder'])
        ->name('presentation.order')
        ->where('presentation', '[0-9]+');
});
