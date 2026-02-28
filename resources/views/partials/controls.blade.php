<div class="controls-bar" :class="{ 'hidden-bar': controlsHidden && isFullscreen }">
    <div style="display: flex; align-items: center; gap: 12px;">
        <a href="{{ $backUrl }}" class="ctrl-btn" title="Zurück">
            <svg style="width:16px;height:16px;display:inline;vertical-align:middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <button class="ctrl-btn" @click="prevSlide()" :disabled="currentSlide === 0">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </button>
        <div style="display: flex; align-items: center; gap: 6px;">
            <template x-for="i in totalSlides" :key="i">
                <div class="slide-nav-dot" :class="{ 'active': currentSlide === i - 1 }" @click="goToSlide(i - 1)"></div>
            </template>
        </div>
        <button class="ctrl-btn" @click="nextSlide()" :disabled="currentSlide === totalSlides - 1">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>
        <span style="color: #9CA3AF; font-size: 13px; min-width: 60px;" x-text="(currentSlide + 1) + ' / ' + totalSlides"></span>
    </div>
    <div style="display: flex; align-items: center; gap: 8px;">
        <span x-show="saveStatus" x-transition style="color: #4CAF50; font-size: 11px;" x-text="saveStatus"></span>
        <button class="ctrl-btn" @click="toggleFullscreen()" :title="isFullscreen ? 'Vollbild verlassen' : 'Vollbild'">
            <svg x-show="!isFullscreen" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
            </svg>
            <svg x-show="isFullscreen" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4H4m0 0l5 5M9 15v5H4m0 0l5-5m6-6V4h5m0 0l-5 5m5 6v5h-5m0 0l5-5"></path>
            </svg>
        </button>
    </div>
</div>
