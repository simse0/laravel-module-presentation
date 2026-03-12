<?php

namespace Trafficdesign\Presentation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Trafficdesign\Presentation\Models\Presentation;
use Trafficdesign\Presentation\Services\PdfExportService;

class ExportPresentationPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        private int $presentationId,
        private string $exportKey,
    ) {}

    public function handle(): void
    {
        $presentation = Presentation::findOrFail($this->presentationId);

        Cache::put($this->exportKey, [
            'status' => 'processing',
            'started_at' => now()->toIso8601String(),
        ], now()->addMinutes(10));

        try {
            $service = app(PdfExportService::class);
            $pdfPath = $service->export($presentation);

            $filename = str_replace(
                [' ', '/'],
                ['_', '-'],
                preg_replace('/[^a-zA-Z0-9äöüÄÖÜß\s\-_]/', '', $presentation->title ?: 'Praesentation')
            ) . '.pdf';

            Cache::put($this->exportKey, [
                'status' => 'ready',
                'path' => $pdfPath,
                'filename' => $filename,
                'completed_at' => now()->toIso8601String(),
            ], now()->addMinutes(10));
        } catch (\Throwable $e) {
            Log::error('PDF export job failed', [
                'presentation_id' => $this->presentationId,
                'error' => $e->getMessage(),
            ]);

            Cache::put($this->exportKey, [
                'status' => 'failed',
                'error' => 'PDF-Export fehlgeschlagen.',
                'failed_at' => now()->toIso8601String(),
            ], now()->addMinutes(5));
        }
    }
}
