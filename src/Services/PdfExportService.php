<?php

namespace Trafficdesign\Presentation\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Trafficdesign\Presentation\Models\Presentation;

class PdfExportService
{
    /**
     * Generate a PDF from a presentation using headless Chrome screenshots.
     *
     * @return string Path to the generated PDF file
     *
     * @throws \RuntimeException
     */
    public function export(Presentation $presentation): string
    {
        $slides = $this->resolveSlides($presentation);
        $slideCount = count($slides);

        if ($slideCount === 0) {
            throw new \RuntimeException('Presentation has no slides.');
        }

        $token = Str::random(64);
        Cache::put('presentation-render-' . $token, $presentation->id, now()->addMinutes(5));

        $renderUrl = route('presentation.render', [
            'presentation' => $presentation->id,
            'token' => $token,
        ]);

        $outputPath = storage_path('app/temp/presentation-' . $presentation->id . '-' . uniqid() . '.pdf');
        $outputDir = dirname($outputPath);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        } elseif (! is_writable($outputDir)) {
            throw new \RuntimeException(
                'Output directory is not writable by current process user: ' . $outputDir
            );
        }

        $scriptPath = realpath(__DIR__ . '/../../scripts/export-pdf.js');

        if (! $scriptPath || ! file_exists($scriptPath)) {
            throw new \RuntimeException('Export script not found. Expected at: ' . __DIR__ . '/../../scripts/export-pdf.js');
        }

        $nodeBinary = config('presentation.pdf_export.node_binary', 'node');
        $chromePath = config('presentation.pdf_export.chrome_path');
        $slideWidth = (string) config('presentation.slide_width', 1280);
        $slideHeight = (string) config('presentation.slide_height', 720);

        $command = [
            $nodeBinary,
            $scriptPath,
            $renderUrl,
            $outputPath,
            (string) $slideCount,
            $slideWidth,
            $slideHeight,
        ];

        if ($chromePath) {
            $command[] = $chromePath;
        }

        $timeout = max(30, $slideCount * 5 + 30);

        $puppeteerCache = config('presentation.pdf_export.puppeteer_cache_dir')
            ?: $this->detectPuppeteerCacheDir();

        $env = array_merge($_ENV, [
            'NODE_PATH' => base_path('node_modules'),
        ]);

        if ($puppeteerCache) {
            $env['PUPPETEER_CACHE_DIR'] = $puppeteerCache;
        }

        $result = Process::timeout($timeout)
            ->path(base_path())
            ->env($env)
            ->run($command);

        if (! $result->successful()) {
            $error = $result->errorOutput() ?: $result->output();
            throw new \RuntimeException('PDF export failed: ' . $error);
        }

        if (! file_exists($outputPath)) {
            throw new \RuntimeException('PDF file was not created.');
        }

        return $outputPath;
    }

    /**
     * Auto-detect the Puppeteer cache directory by checking common locations.
     */
    private function detectPuppeteerCacheDir(): ?string
    {
        $candidates = [
            storage_path('.puppeteer-cache'),
            base_path('.puppeteer-cache'),
            getenv('HOME') . '/.cache/puppeteer',
            '/root/.cache/puppeteer',
        ];

        foreach ($candidates as $dir) {
            if ($dir && is_dir($dir)) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * Load slides from snapshot with overrides and ordering applied.
     */
    private function resolveSlides(Presentation $presentation): array
    {
        $engine = app(\Trafficdesign\Presentation\PresentationEngine::class);

        if ($presentation->hasSnapshot()) {
            $result = $engine->loadFromSnapshot($presentation);
        } else {
            $subject = $presentation->presentable;
            if (! $subject) {
                return [];
            }
            $result = $engine->generateAndSave($subject, $presentation);
        }

        $slides = $result['slides'];
        $slides = $engine->applyOverrides($slides, $presentation);
        $slides = $engine->applySlideOrder($slides, $presentation);

        return $slides;
    }
}
