<?php

namespace Trafficdesign\Presentation\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Trafficdesign\Presentation\Models\Presentation;
use Trafficdesign\Presentation\PresentationEngine;

class PptxExportService
{
    private const CHART_TYPES = [
        'chart-bar',
        'perspective',
        'perspective-focus',
        'summary',
        'self-gap',
        'divergence',
        'year-over-year',
    ];

    private const BLADE_CONTENT_TYPES = [
        'perspective-quotes',
        'participants',
        'rating-scale',
        'perspective-cover',
        'agenda',
        'action-plans',
        'reflection',
    ];

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

        $manifestPath = storage_path('app/temp/pptx-manifest-' . $presentation->id . '-' . uniqid() . '.json');
        $outputPath = storage_path('app/temp/presentation-' . $presentation->id . '-' . uniqid() . '.pptx');
        $outputDir = dirname($outputPath);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $token = Str::random(64);
        Cache::put('presentation-render-' . $token, $presentation->id, now()->addMinutes(5));

        $renderUrl = route('presentation.render', [
            'presentation' => $presentation->id,
            'token' => $token,
        ]);

        $scriptPath = realpath(__DIR__ . '/../../scripts/export-pptx.js');

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

            throw new \RuntimeException('PPTX export failed: ' . $error);
        }

        if (! file_exists($outputPath)) {
            throw new \RuntimeException('PPTX file was not created.');
        }

        return $outputPath;
    }

    private function buildManifest(array $rawSlides, array $preparedSlides, array $config): array
    {
        $slideWidth = $config['slide_width'] ?? 1280;
        $slideHeight = $config['slide_height'] ?? 720;
        $fontFamily = $config['font_family'] ?? 'Arial';

        $manifestSlides = [];

        foreach ($preparedSlides as $idx => $prepared) {
            $raw = $rawSlides[$idx] ?? [];
            $type = $raw['type'] ?? '';
            $theme = $raw['theme'] ?? 'dark';

            $needsScreenshot = in_array($type, self::CHART_TYPES) || in_array($type, self::BLADE_CONTENT_TYPES);

            $background = $theme === 'dark' ? '#1D1D1D' : '#FFFFFF';

            $textboxes = [];
            foreach ($prepared['textboxes'] ?? [] as $tb) {
                if (! empty($tb['hidden'])) {
                    continue;
                }
                $textboxes[] = [
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
            }

            $images = [];
            foreach ($prepared['images'] ?? [] as $img) {
                if (empty($img['url'])) {
                    continue;
                }
                $images[] = [
                    'url' => $img['url'],
                    'x' => $img['x'] ?? 0,
                    'y' => $img['y'] ?? 0,
                    'width' => $img['width'] ?? 400,
                    'height' => $img['height'] ?? 300,
                ];
            }

            $manifestSlides[] = [
                'id' => $raw['id'] ?? 'slide-' . $idx,
                'type' => $type,
                'theme' => $theme,
                'background' => $background,
                'needsScreenshot' => $needsScreenshot,
                'textboxes' => $textboxes,
                'images' => $images,
            ];
        }

        return [
            'slideWidth' => $slideWidth,
            'slideHeight' => $slideHeight,
            'fontFamily' => $fontFamily,
            'slides' => $manifestSlides,
        ];
    }

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
