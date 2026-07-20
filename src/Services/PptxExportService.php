<?php

namespace Trafficdesign\Presentation\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Trafficdesign\Presentation\Models\Presentation;
use Trafficdesign\Presentation\PresentationEngine;
use Trafficdesign\Presentation\Support\ExportTypeRegistry;
use Trafficdesign\Presentation\Support\SlideGeometry;

class PptxExportService
{
    /**
     * Generate a PPTX from a presentation using native text + chart screenshots.
     *
     * @return string Path to the generated .pptx file
     *
     * @throws \RuntimeException
     */
    public function export(Presentation $presentation): string
    {
        $engine = app(PresentationEngine::class);
        $slides = $this->resolveSlides($presentation, $engine);
        $slideCount = count($slides);

        if ($slideCount === 0) {
            throw new \RuntimeException('Presentation has no slides.');
        }

        $config = config('presentation');
        $preparedSlides = $engine->prepareSlidesForView($slides, $config);

        $manifest = $this->buildManifest($slides, $preparedSlides, $config);

        $manifestPath = storage_path('app/temp/pptx-manifest-'.$presentation->id.'-'.uniqid().'.json');
        $outputPath = storage_path('app/temp/presentation-'.$presentation->id.'-'.uniqid().'.pptx');
        $outputDir = dirname($outputPath);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $token = Str::random(64);
        Cache::put('presentation-render-'.$token, $presentation->id, now()->addMinutes(5));

        $renderUrl = route('presentation.render', [
            'presentation' => $presentation->id,
            'token' => $token,
        ]);

        $scriptPath = realpath(__DIR__.'/../../scripts/export-pptx.js');

        if (! $scriptPath || ! file_exists($scriptPath)) {
            throw new \RuntimeException('PPTX export script not found.');
        }

        $nodeBinary = config('presentation.pdf_export.node_binary', 'node');
        $chromePath = config('presentation.pdf_export.chrome_path');

        $command = [
            $nodeBinary,
            $scriptPath,
            $renderUrl,
            $manifestPath,
            $outputPath,
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

        @unlink($manifestPath);

        if (! $result->successful()) {
            $error = $result->errorOutput() ?: $result->output();

            throw new \RuntimeException('PPTX export failed: '.$error);
        }

        if (! file_exists($outputPath)) {
            throw new \RuntimeException('PPTX file was not created.');
        }

        return $outputPath;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawSlides
     * @param  array<int, array<string, mixed>>  $preparedSlides
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function buildManifest(array $rawSlides, array $preparedSlides, array $config): array
    {
        $slideWidth = $config['slide_width'] ?? 1280;
        $slideHeight = $config['slide_height'] ?? 720;
        $fontFamily = $config['font_family'] ?? 'Arial';

        $manifestSlides = [];

        foreach ($preparedSlides as $idx => $prepared) {
            $raw = $rawSlides[$idx] ?? [];
            $type = $raw['type'] ?? '';
            $theme = $raw['theme'] ?? 'dark';
            $export = ExportTypeRegistry::resolve($type, $raw['export'] ?? null);
            $needsScreenshot = ($export['screenshot'] ?? 'none') !== 'none';

            $background = $theme === 'dark' ? '#1D1D1D' : '#FFFFFF';

            $headerBadge = $prepared['header_badge'] ?? null;

            $textboxes = [];
            foreach ($prepared['textboxes'] ?? [] as $tb) {
                if (! empty($tb['hidden'])) {
                    continue;
                }
                $manifestTb = [
                    'text' => $tb['text'] ?? '',
                    'x' => $tb['x'] ?? 0,
                    'y' => $tb['y'] ?? 0,
                    'width' => $tb['width'] ?? 400,
                    'height' => $tb['height'] ?? null,
                    'fontSize' => $tb['fontSize'] ?? 16,
                    'fontWeight' => $tb['fontWeight'] ?? 400,
                    'color' => $tb['color'] ?? '#ffffff',
                    'align' => $tb['align'] ?? 'left',
                ];

                if (($tb['role'] ?? '') === 'title' && is_array($headerBadge)) {
                    $manifestTb['runs'] = [
                        [
                            'text' => $manifestTb['text'],
                            'fontSize' => $manifestTb['fontSize'],
                            'color' => $manifestTb['color'],
                            'bold' => ((int) $manifestTb['fontWeight']) >= 700,
                        ],
                        [
                            'text' => '  '.$headerBadge['text'],
                            'fontSize' => 20,
                            'color' => $headerBadge['color'],
                            'bold' => true,
                        ],
                    ];
                }

                $textboxes[] = $manifestTb;
            }

            $images = [];
            foreach ($prepared['images'] ?? [] as $img) {
                $manifestImage = $this->resolveImageForManifest($img);
                if ($manifestImage !== null) {
                    $images[] = $manifestImage;
                }
            }

            $shapes = [];
            foreach ($prepared['shapes'] ?? [] as $shape) {
                $shapes[] = [
                    'type' => $shape['type'] ?? 'rect',
                    'x' => $shape['x'] ?? 0,
                    'y' => $shape['y'] ?? 0,
                    'width' => $shape['width'] ?? 16,
                    'height' => $shape['height'] ?? 16,
                    'fill' => $shape['fill'] ?? '#000000',
                ];
            }

            $manifestSlides[] = [
                'id' => $raw['id'] ?? 'slide-'.$idx,
                'type' => $type,
                'theme' => $theme,
                'background' => $background,
                'export' => $export,
                'needsScreenshot' => $needsScreenshot,
                'screenshotScope' => $export['screenshot'] ?? 'none',
                'textboxes' => $textboxes,
                'images' => $images,
                'shapes' => $shapes,
            ];
        }

        return [
            'slideWidth' => $slideWidth,
            'slideHeight' => $slideHeight,
            'fontFamily' => $fontFamily,
            'slides' => $manifestSlides,
        ];
    }

    /**
     * @param  array<string, mixed>  $img
     * @return array<string, mixed>|null
     */
    private function resolveImageForManifest(array $img): ?array
    {
        $boxX = (float) ($img['x'] ?? 0);
        $boxY = (float) ($img['y'] ?? 0);
        $boxWidth = (float) ($img['width'] ?? 400);
        $boxHeight = (float) ($img['height'] ?? 300);

        $localPath = $this->resolveLocalImagePath($img);
        if ($localPath === null) {
            Log::warning('PPTX export: image could not be resolved locally, skipping.', [
                'image_id' => $img['id'] ?? null,
                'url' => $img['url'] ?? null,
            ]);

            return null;
        }

        $naturalWidth = 0.0;
        $naturalHeight = 0.0;

        if (isset($img['aspectRatio']) && (float) $img['aspectRatio'] > 0) {
            $naturalWidth = (float) $img['aspectRatio'];
            $naturalHeight = 1.0;
        } else {
            $size = @getimagesize($localPath);
            if (is_array($size) && ($size[0] ?? 0) > 0 && ($size[1] ?? 0) > 0) {
                $naturalWidth = (float) $size[0];
                $naturalHeight = (float) $size[1];
            }
        }

        $fit = SlideGeometry::fitContain($boxWidth, $boxHeight, $naturalWidth, $naturalHeight);

        return [
            'path' => $localPath,
            'x' => $boxX + $fit['x'],
            'y' => $boxY + $fit['y'],
            'width' => $fit['width'],
            'height' => $fit['height'],
            'boxWidth' => $boxWidth,
            'boxHeight' => $boxHeight,
            'useContainSizing' => $naturalWidth <= 0 || $naturalHeight <= 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $img
     */
    private function resolveLocalImagePath(array $img): ?string
    {
        $disk = config('presentation.images.disk', 'public');
        $diskPath = $img['disk_path'] ?? null;

        if (is_string($diskPath) && $diskPath !== '') {
            $fullPath = Storage::disk($disk)->path($diskPath);
            if (is_file($fullPath)) {
                return $fullPath;
            }
        }

        $url = $img['url'] ?? '';
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/storage/')) {
            $relative = ltrim(substr($url, strlen('/storage/')), '/');
            $publicPath = storage_path('app/public/'.$relative);
            if (is_file($publicPath)) {
                return $publicPath;
            }
        }

        if (str_starts_with($url, storage_path())) {
            return is_file($url) ? $url : null;
        }

        return null;
    }

    private function detectPuppeteerCacheDir(): ?string
    {
        $candidates = [
            storage_path('.puppeteer-cache'),
            base_path('.puppeteer-cache'),
            getenv('HOME').'/.cache/puppeteer',
            '/root/.cache/puppeteer',
        ];

        foreach ($candidates as $dir) {
            if ($dir && is_dir($dir)) {
                return $dir;
            }
        }

        return null;
    }

    private function resolveSlides(Presentation $presentation, PresentationEngine $engine): array
    {
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
