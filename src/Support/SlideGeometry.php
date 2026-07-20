<?php

namespace Trafficdesign\Presentation\Support;

class SlideGeometry
{
    /**
     * Compute object-fit: contain draw rectangle inside a bounding box.
     *
     * @return array{x: float, y: float, width: float, height: float}
     */
    public static function fitContain(float $boxWidth, float $boxHeight, float $naturalWidth, float $naturalHeight): array
    {
        if ($naturalWidth <= 0 || $naturalHeight <= 0) {
            return [
                'x' => 0.0,
                'y' => 0.0,
                'width' => $boxWidth,
                'height' => $boxHeight,
            ];
        }

        $scale = min($boxWidth / $naturalWidth, $boxHeight / $naturalHeight);
        $drawWidth = $naturalWidth * $scale;
        $drawHeight = $naturalHeight * $scale;

        return [
            'x' => ($boxWidth - $drawWidth) / 2,
            'y' => ($boxHeight - $drawHeight) / 2,
            'width' => $drawWidth,
            'height' => $drawHeight,
        ];
    }
}
