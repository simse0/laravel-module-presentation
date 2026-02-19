@props([
    'theme' => 'dark',
    'slideIndex' => 0,
    'totalSlides' => 1,
    'footer' => '',
])

<template x-if="currentSlide === {{ $slideIndex }}">
    <div class="slide slide-{{ $theme }} slide-animate">
        <div class="slide-inner">
            {{ $slot }}

            <div class="slide-footer" @if($theme === 'dark') style="border-color: rgba(255,255,255,0.1);" @endif>
                <span contenteditable="true" @if($theme === 'dark') style="color: #6B7280;" @endif
                      data-override-key="{{ $attributes->get('slide-id', '') }}.footer"
                      @blur="saveOverride($event)">{{ $footer }}</span>
                <span @if($theme === 'dark') style="color: #6B7280;" @endif
                      x-text="'Slide ' + (currentSlide + 1) + ' / ' + totalSlides"></span>
            </div>
        </div>
    </div>
</template>
