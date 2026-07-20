<?php

namespace Trafficdesign\Presentation\Support;

class PerspectiveAccentColor
{
    public static function resolve(string $perspective): ?string
    {
        if ($perspective === '') {
            return null;
        }

        $colors = config('presentation.perspective_colors', []);
        if ($colors === [] && config()->has('threesixty.perspective_colors')) {
            $colors = config('threesixty.perspective_colors', []);
        }

        $fallback = config('presentation.perspective_color_fallback', '#6B7280');
        if ($fallback === '#6B7280' && config()->has('threesixty.perspective_color_fallback')) {
            $fallback = config('threesixty.perspective_color_fallback', '#6B7280');
        }

        if (isset($colors[$perspective])) {
            return (string) $colors[$perspective];
        }

        foreach ($colors as $key => $color) {
            if (str_contains($perspective, (string) $key) || str_contains((string) $key, $perspective)) {
                return (string) $color;
            }
        }

        return (string) $fallback;
    }
}
