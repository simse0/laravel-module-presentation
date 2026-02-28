@props([
    'theme' => 'dark',
    'slideIndex' => 0,
    'totalSlides' => 1,
    'footer' => '',
    'mode' => 'present',
    'slideId' => '',
])

<template x-if="currentSlideId === '{{ $slideId }}'">
    <div class="slide slide-{{ $theme }} slide-animate" data-slide-id="{{ $slideId }}" data-slide-index="{{ $slideIndex }}">
        <div class="slide-inner">
            {{ $slot }}

            <div class="slide-footer{{ $mode === 'edit' ? ' edit-hidden' : '' }}" @if($theme === 'dark') style="border-color: rgba(255,255,255,0.1);" @endif>
                <span @if($theme === 'dark') style="color: #6B7280;" @endif>{{ $footer }}</span>
                <span @if($theme === 'dark') style="color: #6B7280;" @endif
                      x-text="'Slide ' + (currentSlide + 1) + ' / ' + totalSlides"></span>
            </div>
        </div>
    </div>
</template>
