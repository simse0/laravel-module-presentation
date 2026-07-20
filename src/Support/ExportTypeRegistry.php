<?php

namespace Trafficdesign\Presentation\Support;

class ExportTypeRegistry
{
    /**
     * Package fallback when host config is missing or cached without export.types.
     *
     * @return array<string, array{mode: string, screenshot: string}>
     */
    public static function fallbackTypes(): array
    {
        return [
            'title' => ['mode' => 'native', 'screenshot' => 'none'],
            'text' => ['mode' => 'native', 'screenshot' => 'none'],
            'chart-bar' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'perspective' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'perspective-focus' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'summary' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'self-gap' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'divergence' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'year-over-year' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'perspective-quotes' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'participants' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'rating-scale' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'perspective-cover' => ['mode' => 'hybrid', 'screenshot' => 'full'],
            'agenda' => ['mode' => 'hybrid', 'screenshot' => 'full'],
            'action-plans' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'reflection' => ['mode' => 'hybrid', 'screenshot' => 'content'],
            'heatmap' => ['mode' => 'hybrid', 'screenshot' => 'content'],
        ];
    }

    /**
     * @return array{mode: string, screenshot: string}
     */
    public static function resolve(string $type, ?array $slideExport = null): array
    {
        if ($slideExport !== null && isset($slideExport['mode'], $slideExport['screenshot'])) {
            return [
                'mode' => (string) $slideExport['mode'],
                'screenshot' => (string) $slideExport['screenshot'],
            ];
        }

        $configured = config('presentation.export.types', []);
        if (isset($configured[$type]['mode'], $configured[$type]['screenshot'])) {
            return [
                'mode' => (string) $configured[$type]['mode'],
                'screenshot' => (string) $configured[$type]['screenshot'],
            ];
        }

        $fallback = self::fallbackTypes();

        return $fallback[$type] ?? ['mode' => 'native', 'screenshot' => 'none'];
    }
}
