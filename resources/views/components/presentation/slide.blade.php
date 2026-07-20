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
        @if($mode === 'present')
        <div class="present-tb-overlay" x-show="(currentPresentShapes || []).length > 0">
            <template x-for="shape in currentPresentShapes" :key="shape.id || `${shape.type}-${shape.x}-${shape.y}`">
                <div :style="`${shape.type === 'ellipse' ? 'border-radius:50%;' : ''} position:absolute; left:${shape.x}px; top:${shape.y}px; width:${shape.width}px; height:${shape.height}px; background:${shape.fill}; pointer-events:none;`"></div>
            </template>
        </div>
        <div class="present-tb-overlay" x-show="(currentPresentImages || []).length > 0">
            <template x-for="img in currentPresentImages" :key="img.id">
                <img :src="img.url" :alt="img.filename || ''"
                     :style="`position:absolute; left:${img.x}px; top:${img.y}px; width:${img.width}px; height:${img.height}px; object-fit:contain; pointer-events:none;`">
            </template>
        </div>
        <div class="present-tb-overlay" x-show="(currentPresentTextboxes || []).length > 0">
            <template x-for="tb in currentPresentTextboxes" :key="tb.id">
                <div class="present-tb"
                     :class="{ 'present-tb-link': tb.link }"
                     :style="`${tb.link ? 'pointer-events:auto; display:block; cursor:pointer;' : ''} left:${tb.x}px; top:${tb.y}px; width:${tb.width}px; ${tb.height ? 'height:'+tb.height+'px;' : ''} font-size:${tb.fontSize}px; color:${tb.color}; font-weight:${tb.fontWeight || 400}; text-align:${tb.align || 'left'}; text-decoration:${tb.textDecoration || 'none'};`"
                     @click="tb.link && window.open(tb.link, '_blank', 'noopener,noreferrer')"
                     x-html="tb.text"></div>
            </template>
        </div>
        @endif
    </div>
</template>
