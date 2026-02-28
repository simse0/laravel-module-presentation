<?php

namespace Trafficdesign\Presentation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Trafficdesign\Presentation\Models\Presentation;

class SlideRemoved
{
    use Dispatchable;

    public function __construct(
        public Presentation $presentation,
        public string $slideId,
    ) {}
}
