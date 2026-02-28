<?php

namespace Trafficdesign\Presentation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Trafficdesign\Presentation\Models\Presentation;

class SlideAdded
{
    use Dispatchable;

    public function __construct(
        public Presentation $presentation,
        public array $slide,
        public ?int $position = null,
    ) {}
}
