<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $presentation->title }} – Bearbeiten</title>
    @if($config['favicon'] ?? false)
    <link rel="icon" type="image/svg+xml" href="{{ asset($config['favicon']) }}">
    @endif
    @if($config['font_url'] ?? false)
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $config['font_url'] }}" rel="stylesheet">
    @endif
    @if($config['vite_assets'] ?? false)
    @vite($config['vite_assets'])
    @endif
    @php $accent = $config['accent_color'] ?? '#00AFCE'; @endphp
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #111; font-family: '{{ $config['font_family'] ?? 'sans-serif' }}', sans-serif; overflow: hidden; color: #E5E7EB; }

        .edit-wrapper { display: flex; height: 100vh; flex-direction: column; }

        .edit-topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 8px 16px; background: #1A1A1A; border-bottom: 1px solid #2A2A2A;
            flex-shrink: 0; z-index: 50; gap: 12px;
        }
        .edit-topbar-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
        .edit-topbar-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

        .edit-title-input {
            background: transparent; border: 1px solid transparent; color: #fff;
            font-size: 14px; font-weight: 600; padding: 4px 8px; border-radius: 4px;
            font-family: inherit; min-width: 200px; max-width: 400px;
        }
        .edit-title-input:hover { border-color: #374151; }
        .edit-title-input:focus { border-color: {{ $accent }}; outline: none; background: #222; }

        .edit-body { display: flex; flex: 1; min-height: 0; }

        .edit-sidebar {
            width: 200px; background: #161616; border-right: 1px solid #2A2A2A;
            display: flex; flex-direction: column; flex-shrink: 0; overflow: hidden;
        }
        .sidebar-slides {
            flex: 1; overflow-y: auto; padding: 12px 10px; display: flex;
            flex-direction: column; gap: 8px;
        }
        .sidebar-slides::-webkit-scrollbar { width: 4px; }
        .sidebar-slides::-webkit-scrollbar-thumb { background: #374151; border-radius: 2px; }

        .sidebar-slide {
            position: relative; border-radius: 6px; cursor: pointer;
            border: 2px solid transparent; transition: all 0.15s;
            background: #1D1D1D; overflow: hidden;
        }
        .sidebar-slide:hover { border-color: #374151; }
        .sidebar-slide.active { border-color: {{ $accent }}; }

        .sidebar-thumb {
            aspect-ratio: 16 / 9; width: 100%; position: relative;
            overflow: hidden; border-radius: 4px 4px 0 0;
            display: flex; align-items: center; justify-content: center;
        }
        .sidebar-thumb.theme-dark { background: #1D1D1D; }
        .sidebar-thumb.theme-light { background: #f3f4f6; }
        .sidebar-thumb-img {
            width: 100%; height: 100%; object-fit: cover;
            position: absolute; inset: 0;
        }
        .sidebar-thumb-placeholder {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 2px; padding: 6px;
            width: 100%; height: 100%; position: relative;
        }
        .sidebar-thumb-icon {
            font-size: 18px; opacity: 0.25; line-height: 1;
        }
        .sidebar-thumb-label {
            font-size: 7px; text-transform: uppercase; letter-spacing: 0.5px;
            opacity: 0.3; font-weight: 600;
        }

        .sidebar-meta {
            padding: 5px 8px; display: flex; align-items: center; gap: 6px;
        }
        .sidebar-slide-number {
            font-size: 9px; color: #6B7280; font-weight: 700;
            flex-shrink: 0; min-width: 14px;
        }
        .sidebar-slide-title {
            font-size: 9px; color: #9CA3AF;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            flex: 1;
        }
        .sidebar-slide-remove {
            position: absolute; top: 2px; right: 4px; background: rgba(0,0,0,0.5); border: none;
            color: #9CA3AF; cursor: pointer; font-size: 12px; padding: 1px 4px;
            opacity: 0; transition: opacity 0.15s; border-radius: 3px; z-index: 5;
        }
        .sidebar-slide:hover .sidebar-slide-remove { opacity: 1; }
        .sidebar-slide-remove:hover { color: #EF4444; background: rgba(0,0,0,0.7); }

        .sidebar-footer {
            padding: 10px; border-top: 1px solid #2A2A2A; flex-shrink: 0;
        }

        .btn-add-slide {
            width: 100%; padding: 8px; border-radius: 6px; border: 1px dashed #374151;
            background: transparent; color: #9CA3AF; font-size: 12px; cursor: pointer;
            font-family: inherit; transition: all 0.15s; display: flex;
            align-items: center; justify-content: center; gap: 6px;
        }
        .btn-add-slide:hover { border-color: {{ $accent }}; color: {{ $accent }}; }

        .edit-main {
            flex: 1; display: flex; align-items: center; justify-content: center;
            overflow: hidden; position: relative; padding: 16px;
        }

        .slide {
            width: {{ $config['slide_width'] ?? 1280 }}px;
            height: {{ $config['slide_height'] ?? 720 }}px;
            aspect-ratio: 16 / 9;
            position: relative; overflow: hidden;
            border-radius: 8px; transition: all 0.3s ease;
            transform: scale(var(--slide-scale, 0.7));
            transform-origin: center center;
            flex-shrink: 0;
        }
        .slide-light { background: #ffffff; color: #1a1a2e; }
        .slide-dark { background: #1D1D1D; color: #E5E7EB; }

        .slide-inner { padding: 48px 56px; height: 100%; display: flex; flex-direction: column; }
        .slide-title { font-size: 28px; font-weight: 800; line-height: 1.2; margin-bottom: 8px; }
        .slide-subtitle { font-size: 15px; font-weight: 500; margin-bottom: 24px; opacity: 0.6; }
        .slide-content { flex: 1; min-height: 0; overflow: hidden; }
        .slide-footer {
            display: flex; align-items: center; justify-content: space-between;
            padding-top: 12px; border-top: 1px solid rgba(128,128,128,0.15);
            font-size: 11px; opacity: 0.4;
        }

        [contenteditable]:hover { outline: 1px dashed {{ $accent }}66; outline-offset: 2px; cursor: text; }
        [contenteditable]:focus { outline: 2px solid {{ $accent }}; outline-offset: 2px; background: {{ $accent }}0d; }

        .score-circle {
            width: 80px; height: 80px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; font-weight: 800; color: #fff; flex-shrink: 0;
        }
        .gap-card { padding: 12px 16px; border-radius: 8px; text-align: center; flex: 1; }

        .apexcharts-toolbar { display: none !important; }

        .btn-action {
            padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600;
            cursor: pointer; font-family: inherit; border: none; transition: all 0.15s;
        }
        .btn-primary { background: {{ $accent }}; color: #fff; }
        .btn-primary:hover { filter: brightness(1.15); }
        .btn-secondary { background: rgba(255,255,255,0.1); color: #D1D5DB; }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); }

        .save-indicator { font-size: 11px; color: #4CAF50; transition: opacity 0.3s; }

        .menu-dropdown {
            position: absolute; top: 100%; right: 0; margin-top: 4px;
            background: #2A2A2A; border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px; padding: 4px 0; min-width: 200px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4); z-index: 200;
        }
        .menu-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 16px; font-size: 13px; color: #D1D5DB;
            cursor: pointer; transition: background 0.15s; border: none;
            background: transparent; width: 100%; text-align: left; font-family: inherit;
            text-decoration: none;
        }
        .menu-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .menu-item svg { width: 16px; height: 16px; flex-shrink: 0; }

        .pdf-progress-overlay {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,0.8); backdrop-filter: blur(4px);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: #fff; font-family: inherit;
        }
        .pdf-progress-bar { width: 300px; height: 6px; background: rgba(255,255,255,0.15); border-radius: 3px; margin-top: 16px; overflow: hidden; }
        .pdf-progress-fill { height: 100%; background: {{ $accent }}; border-radius: 3px; transition: width 0.3s ease; }

        .slide-nav-controls {
            position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%);
            display: flex; align-items: center; gap: 8px; z-index: 10;
        }
        .nav-btn {
            background: rgba(0,0,0,0.6); border: none; color: #fff; width: 32px; height: 32px;
            border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
        }
        .nav-btn:hover { background: rgba(0,0,0,0.8); }
        .nav-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .nav-counter { color: #9CA3AF; font-size: 12px; }
    </style>

    @stack('presentation-styles')

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js" defer></script>
</head>
<body>

<div x-data="editEngine()" @keydown.window="handleKeydown($event)" class="edit-wrapper">

    {{-- Top-Bar --}}
    <div class="edit-topbar">
        <div class="edit-topbar-left">
            <a href="{{ $backUrl }}" style="color: #9CA3AF; text-decoration: none; display: flex;" title="Zurück">
                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <input type="text" class="edit-title-input" :value="presentationTitle"
                   @change="renamePresentation($event.target.value)"
                   @keydown.enter="$event.target.blur()">
            <span class="save-indicator" x-show="saveStatus" x-text="saveStatus" x-transition></span>
        </div>
        <div class="edit-topbar-right">
            <a href="{{ route('presentation.show', $presentation->id) }}" class="btn-action btn-secondary" style="text-decoration: none;">
                <svg style="width:14px;height:14px;display:inline;vertical-align:middle;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16l13-8z"/>
                </svg>
                Präsentieren
            </a>
            <button class="btn-action btn-primary" @click="saveAll()">Speichern</button>
            <div style="position: relative;" @click.outside="menuOpen = false">
                <button class="btn-action btn-secondary" @click="menuOpen = !menuOpen" style="padding: 6px 8px;">
                    <svg style="width:16px;height:16px;" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
                    </svg>
                </button>
                <div x-show="menuOpen" x-transition.scale.origin.top.right class="menu-dropdown">
                    <button class="menu-item" @click="exportPdf(); menuOpen = false" :disabled="$store.pdfState.exporting">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Als PDF exportieren</span>
                    </button>
                    <button class="menu-item" @click="regeneratePresentation(); menuOpen = false">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Neu generieren</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Body: Sidebar + Main --}}
    <div class="edit-body">
        {{-- Sidebar --}}
        <div class="edit-sidebar">
            <div class="sidebar-slides" x-ref="sidebarSlides">
                <template x-for="(slide, idx) in slidesData" :key="slide.id">
                    <div class="sidebar-slide" :class="{ 'active': currentSlide === idx }"
                         @click="goToSlide(idx)" :data-slide-id="slide.id">
                        <div class="sidebar-thumb" :class="'theme-' + (slide.theme || 'dark')">
                            <img x-show="slide.thumbnail" :src="slide.thumbnail" class="sidebar-thumb-img" alt="">
                            <div class="sidebar-thumb-placeholder" x-show="!slide.thumbnail">
                                <span class="sidebar-thumb-icon" :style="'color:' + (slide.theme === 'light' ? '#1a1a2e' : '#E5E7EB')" x-text="slideTypeIcon(slide.type)"></span>
                                <span class="sidebar-thumb-label" :style="'color:' + (slide.theme === 'light' ? '#6B7280' : '#9CA3AF')" x-text="slide.type"></span>
                            </div>
                        </div>
                        <div class="sidebar-meta">
                            <span class="sidebar-slide-number" x-text="idx + 1"></span>
                            <span class="sidebar-slide-title" x-text="slide.title || '(Kein Titel)'"></span>
                        </div>
                        <button class="sidebar-slide-remove" @click.stop="removeSlide(slide.id, idx)"
                                x-show="slide.source === 'user'" title="Slide entfernen">&times;</button>
                    </div>
                </template>
            </div>
            <div class="sidebar-footer">
                <button class="btn-add-slide" @click="addTextSlide()">
                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Text-Slide
                </button>
            </div>
        </div>

        {{-- Main Slide Area --}}
        <div class="edit-main">
            @yield('slides')

            <div class="slide-nav-controls">
                <button class="nav-btn" @click="prevSlide()" :disabled="currentSlide === 0">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <span class="nav-counter" x-text="(currentSlide + 1) + ' / ' + totalSlides"></span>
                <button class="nav-btn" @click="nextSlide()" :disabled="currentSlide === totalSlides - 1">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- PDF Overlay --}}
<div id="pdf-overlay" x-data x-show="$store.pdfState.exporting" x-transition.opacity class="pdf-progress-overlay" style="display: none;">
    <div style="font-size: 18px; font-weight: 600;" x-text="$store.pdfState.statusText"></div>
    <div style="font-size: 13px; color: #9CA3AF; margin-top: 6px;" x-text="'Slide ' + $store.pdfState.currentSlide + ' / ' + $store.pdfState.totalSlides"></div>
    <div class="pdf-progress-bar"><div class="pdf-progress-fill" :style="'width: ' + $store.pdfState.progress + '%'"></div></div>
</div>

@php
    $slidesMeta = collect($slides)->map(function ($s) {
        return [
            'id' => $s['id'],
            'type' => $s['type'],
            'title' => $s['title'] ?? '',
            'theme' => $s['theme'] ?? 'dark',
            'source' => $s['source'] ?? 'generated',
            'thumbnail' => null,
        ];
    })->values()->toArray();
@endphp
<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('pdfState', { exporting: false, progress: 0, currentSlide: 0, totalSlides: 0, statusText: '' });
});

function editEngine() {
    return {
        currentSlide: 0,
        totalSlides: {{ count($slides) }},
        slidesData: @json($slidesMeta),
        presentationTitle: @json($presentation->title),
        presentationId: {{ $presentation->id }},
        isFullscreen: false,
        controlsHidden: false,
        chartInstances: {},
        saveStatus: '',
        menuOpen: false,
        sortableInstance: null,

        init() {
            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') {
                    this.renderChartsForSlide(0);
                }
                this.initSortable();
                this.calcSlideScale();
                window.addEventListener('resize', () => this.calcSlideScale());
                setTimeout(() => this.captureThumbnail(0), 1200);
            });
        },

        calcSlideScale() {
            const main = document.querySelector('.edit-main');
            if (!main) return;
            const pad = 80;
            const sw = {{ $config['slide_width'] ?? 1280 }};
            const sh = {{ $config['slide_height'] ?? 720 }};
            const scaleX = (main.clientWidth - pad) / sw;
            const scaleY = (main.clientHeight - pad) / sh;
            const s = Math.min(scaleX, scaleY, 1);
            document.documentElement.style.setProperty('--slide-scale', s.toFixed(4));
        },

        slideTypeIcon(type) {
            const icons = { title: '◆', summary: '◎', participants: '👥', 'chart-bar': '📊', perspective: '🔍', 'self-gap': '⇄', divergence: '◇', text: '¶' };
            return icons[type] || '□';
        },

        async captureThumbnail(idx) {
            if (typeof html2canvas === 'undefined') return;
            const slideEl = document.querySelector('[data-slide-index="' + idx + '"]');
            if (!slideEl) return;
            try {
                const canvas = await html2canvas(slideEl, {
                    scale: 0.2, useCORS: true, allowTaint: true, backgroundColor: null,
                    logging: false, width: {{ $config['slide_width'] ?? 1280 }}, height: {{ $config['slide_height'] ?? 720 }},
                });
                this.slidesData[idx].thumbnail = canvas.toDataURL('image/jpeg', 0.6);
            } catch (e) {}
        },

        initSortable() {
            const el = this.$refs.sidebarSlides;
            if (!el || typeof Sortable === 'undefined') return;
            this.sortableInstance = new Sortable(el, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: (evt) => {
                    const moved = this.slidesData.splice(evt.oldIndex, 1)[0];
                    this.slidesData.splice(evt.newIndex, 0, moved);
                    if (this.currentSlide === evt.oldIndex) {
                        this.currentSlide = evt.newIndex;
                    }
                },
            });
        },

        handleKeydown(e) {
            if (e.target.isContentEditable || e.target.tagName === 'INPUT') return;
            switch (e.key) {
                case 'ArrowRight': e.preventDefault(); this.nextSlide(); break;
                case 'ArrowLeft': e.preventDefault(); this.prevSlide(); break;
            }
        },

        nextSlide() { if (this.currentSlide < this.totalSlides - 1) this.goToSlide(this.currentSlide + 1); },
        prevSlide() { if (this.currentSlide > 0) this.goToSlide(this.currentSlide - 1); },

        goToSlide(idx) {
            if (idx < 0 || idx >= this.totalSlides || idx === this.currentSlide) return;
            this.destroyChartsForSlide(this.currentSlide);
            this.currentSlide = idx;
            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') {
                    this.renderChartsForSlide(idx);
                }
                setTimeout(() => this.captureThumbnail(idx), 1000);
            });
        },

        async saveAll() {
            this.saveStatus = 'Wird gespeichert…';
            try {
                const res = await this._fetch('{{ route("presentation.save", $presentation->id) }}', 'POST', {
                    slides: this.slidesData,
                });
                if (res.ok) {
                    this.saveStatus = 'Gespeichert';
                    setTimeout(() => { this.saveStatus = ''; }, 2000);
                }
            } catch (e) {
                this.saveStatus = 'Fehler';
                setTimeout(() => { this.saveStatus = ''; }, 3000);
            }
        },

        async renamePresentation(newTitle) {
            this.presentationTitle = newTitle;
            await this._fetch('{{ route("presentation.rename", $presentation->id) }}', 'POST', { title: newTitle });
        },

        async addTextSlide() {
            try {
                const res = await this._fetch('{{ route("presentation.slides.add", $presentation->id) }}', 'POST', {
                    title: 'Neuer Slide',
                    theme: 'light',
                    position: this.currentSlide + 1,
                });
                if (res.ok) {
                    const data = await res.json();
                    const newSlides = data.slides.map(s => ({ id: s.id, type: s.type, title: s.title || '', source: s.source || 'generated' }));
                    this.slidesData = newSlides;
                    this.totalSlides = newSlides.length;
                    this.$nextTick(() => this.goToSlide(this.currentSlide + 1));
                    window.location.reload();
                }
            } catch (e) { console.error(e); }
        },

        async removeSlide(slideId, idx) {
            if (!confirm('Diesen Slide wirklich entfernen?')) return;
            try {
                const res = await this._fetch('{{ url(config("presentation.route_prefix", "presentations")) }}/' + this.presentationId + '/slides/' + slideId, 'DELETE');
                if (res.ok) {
                    this.slidesData.splice(idx, 1);
                    this.totalSlides = this.slidesData.length;
                    if (this.currentSlide >= this.totalSlides) this.currentSlide = Math.max(0, this.totalSlides - 1);
                    window.location.reload();
                }
            } catch (e) { console.error(e); }
        },

        async regeneratePresentation() {
            if (!confirm('Slides neu generieren? Textaenderungen an generierten Slides gehen verloren.')) return;
            try {
                const res = await this._fetch('{{ route("presentation.regenerate", $presentation->id) }}', 'POST');
                const data = await res.json();
                if (data.redirect) window.location.href = data.redirect;
            } catch (e) { console.error(e); }
        },

        saveOverride(event) {
            // In edit mode text changes are saved via saveAll()
        },

        destroyChartsForSlide(idx) {
            const keys = Object.keys(this.chartInstances).filter(k => k.startsWith('slide-' + idx + '-'));
            keys.forEach(k => { try { this.chartInstances[k].destroy(); } catch(e) {} delete this.chartInstances[k]; });
        },

        renderChartsForSlide(idx) {},

        async exportPdf() {
            const pdfState = Alpine.store('pdfState');
            if (pdfState.exporting) return;
            pdfState.exporting = true; pdfState.progress = 0; pdfState.currentSlide = 0;
            pdfState.totalSlides = this.totalSlides; pdfState.statusText = 'PDF wird vorbereitet…';

            const originalSlide = this.currentSlide;
            const { jsPDF } = window.jspdf;
            const slideW = {{ $config['slide_width'] ?? 1280 }};
            const slideH = {{ $config['slide_height'] ?? 720 }};
            const pdf = new jsPDF({ orientation: 'landscape', unit: 'px', format: [slideW, slideH], hotfixes: ['px_scaling'] });
            const title = this.presentationTitle || 'Praesentation';

            for (let i = 0; i < this.totalSlides; i++) {
                pdfState.currentSlide = i + 1;
                pdfState.statusText = 'Slide ' + (i + 1) + ' wird erfasst…';
                pdfState.progress = Math.round(((i) / this.totalSlides) * 90);

                this.destroyChartsForSlide(this.currentSlide);
                this.currentSlide = i;
                await this._wait(150);
                if (typeof this.renderChartsForSlide === 'function') this.renderChartsForSlide(i);
                await this._wait(800);

                const slideEl = document.querySelector('[data-slide-index="' + i + '"]');
                if (!slideEl) continue;

                try {
                    const canvas = await html2canvas(slideEl, {
                        scale: 2, useCORS: true, allowTaint: true, backgroundColor: null, logging: false,
                        onclone: (clonedDoc, clonedEl) => {
                            clonedEl.style.width = slideW + 'px';
                            clonedEl.style.height = slideH + 'px';
                            clonedEl.style.borderRadius = '0';
                            clonedEl.style.transform = 'none';
                            clonedEl.style.position = 'relative';
                        },
                    });
                    if (i > 0) pdf.addPage([slideW, slideH], 'landscape');
                    pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, 0, slideW, slideH);
                } catch (e) { console.error('Slide ' + i + ':', e); }
            }

            pdfState.statusText = 'Fertigstellen…'; pdfState.progress = 95;
            await this._wait(200);
            pdf.save(title.replace(/[^a-zA-Z0-9äöüÄÖÜß\s\-_]/g, '').replace(/\s+/g, '_') + '.pdf');
            pdfState.progress = 100; pdfState.statusText = 'Fertig!';
            await this._wait(500);

            this.destroyChartsForSlide(this.currentSlide);
            this.currentSlide = originalSlide;
            this.$nextTick(() => { if (typeof this.renderChartsForSlide === 'function') this.renderChartsForSlide(originalSlide); });
            pdfState.exporting = false; pdfState.progress = 0;
        },

        async _fetch(url, method, body) {
            return fetch(url, {
                method, credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body ? JSON.stringify(body) : undefined,
            });
        },

        _wait(ms) { return new Promise(r => setTimeout(r, ms)); },
    };
}
</script>

@stack('presentation-scripts')

</body>
</html>
