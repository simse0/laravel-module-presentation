<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $presentation->title }} – Präsentation</title>
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
        body { margin: 0; padding: 0; background: #111; font-family: '{{ $config['font_family'] ?? 'sans-serif' }}', sans-serif; overflow: hidden; }

        .slide-container {
            width: 100vw; height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: #111;
            padding-top: 36px;
            box-sizing: border-box;
        }
        .slide-container.fullscreen { padding-top: 0; }

        .slide {
            width: {{ $config['slide_width'] ?? 1280 }}px;
            height: {{ $config['slide_height'] ?? 720 }}px;
            aspect-ratio: 16 / 9;
            position: relative; overflow: hidden;
            border-radius: 8px; transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .slide-light { background: #ffffff; color: #1a1a2e; }
        .slide-dark { background: #1D1D1D; color: #E5E7EB; }
        .fullscreen .slide { width: 100vw; height: 100vh; aspect-ratio: auto; border-radius: 0; }

        .slide-inner { padding: 48px 56px; height: 100%; display: flex; flex-direction: column; }
        .slide-title { font-size: 28px; font-weight: 800; line-height: 1.2; margin-bottom: 8px; }
        .slide-subtitle { font-size: 15px; font-weight: 500; margin-bottom: 24px; opacity: 0.6; }
        .slide-content { flex: 1; min-height: 0; overflow: hidden; }
        .slide-footer {
            display: flex; align-items: center; justify-content: space-between;
            padding-top: 12px; border-top: 1px solid rgba(128,128,128,0.15);
            font-size: 11px; opacity: 0.4;
        }

        @if(($mode ?? 'present') === 'edit')
        [contenteditable]:hover { outline: 1px dashed {{ $accent }}66; outline-offset: 2px; cursor: text; }
        [contenteditable]:focus { outline: 2px solid {{ $accent }}; outline-offset: 2px; background: {{ $accent }}0d; }
        @endif

        .controls-bar {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: rgba(0,0,0,0.85); backdrop-filter: blur(8px);
            padding: 8px 24px; display: flex; align-items: center; justify-content: space-between;
            z-index: 100; transition: opacity 0.3s, transform 0.3s;
        }
        .controls-bar.hidden-bar { opacity: 0; transform: translateY(100%); pointer-events: none; }

        .ctrl-btn {
            background: rgba(255,255,255,0.1); border: none; color: #fff; padding: 6px 12px;
            border-radius: 6px; font-size: 13px; cursor: pointer; font-family: inherit;
            transition: background 0.15s;
        }
        .ctrl-btn:hover { background: rgba(255,255,255,0.2); }
        .ctrl-btn:disabled { opacity: 0.3; cursor: not-allowed; }

        .slide-nav-dot {
            width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.25);
            cursor: pointer; transition: all 0.2s;
        }
        .slide-nav-dot.active { background: {{ $accent }}; transform: scale(1.3); }
        .slide-nav-dot:hover { background: rgba(255,255,255,0.5); }

        .score-circle {
            width: 80px; height: 80px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; font-weight: 800; color: #fff; flex-shrink: 0;
        }
        .gap-card { padding: 12px 16px; border-radius: 8px; text-align: center; flex: 1; }

        @keyframes slideIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }
        .slide-animate { animation: slideIn 0.4s ease-out; }
        .apexcharts-toolbar { display: none !important; }

        @if($export ?? false)
        .top-bar, .controls-bar, .pdf-progress-overlay, #pdf-overlay { display: none !important; }
        .slide-container { padding-top: 0 !important; }
        .slide-animate { animation: none !important; }
        .slide { border-radius: 0 !important; }
        @endif

        .present-tb-overlay { position: absolute; inset: 0; pointer-events: none; z-index: 5; }
        .present-tb {
            position: absolute; pointer-events: none;
            padding: 6px 10px; line-height: 1.4; word-wrap: break-word;
        }

        .top-bar {
        position: fixed; top: 0; left: 0; right: 0;
        background: rgba(0,0,0,0.85); backdrop-filter: blur(8px);
        padding: 6px 24px; display: flex; align-items: center; justify-content: flex-end;
        z-index: 101; transition: opacity 0.3s, transform 0.3s;
    }
    .fullscreen .top-bar { opacity: 0; transform: translateY(-100%); pointer-events: none; }

    .menu-dropdown {
        position: absolute; top: 100%; right: 0; margin-top: 4px;
        background: #2A2A2A; border: 1px solid rgba(255,255,255,0.1);
        border-radius: 8px; padding: 4px 0; min-width: 200px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.4);
    }
    .menu-item {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 16px; font-size: 13px; color: #D1D5DB;
        cursor: pointer; transition: background 0.15s; border: none;
        background: transparent; width: 100%; text-align: left; font-family: inherit;
    }
    .menu-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
    .menu-item:disabled { opacity: 0.4; cursor: not-allowed; }
    .menu-item svg { width: 16px; height: 16px; flex-shrink: 0; }

    .pdf-progress-overlay {
        position: fixed; inset: 0; z-index: 9999;
        background: rgba(0,0,0,0.8); backdrop-filter: blur(4px);
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        color: #fff; font-family: inherit;
    }
    .pdf-progress-bar {
        width: 300px; height: 6px; background: rgba(255,255,255,0.15);
        border-radius: 3px; margin-top: 16px; overflow: hidden;
    }
    .pdf-progress-fill {
        height: 100%; background: {{ $accent }}; border-radius: 3px;
        transition: width 0.3s ease;
    }
    </style>

    {{-- Host-App kann hier eigene Styles einschleusen --}}
    @stack('presentation-styles')

    @if(!($export ?? false))
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js" defer></script>
    @endif
</head>
<body>

<div x-data="presentationEngine()" @keydown.window="handleKeydown($event)"
     :class="{ 'fullscreen': isFullscreen }" class="slide-container" @mousemove="showControls()">

    {{-- Top-Bar mit Menü (nur sichtbar wenn nicht Vollbild) --}}
    @include('presentation::partials.topbar')

    {{-- Slides werden von der Host-App gerendert (publishable view) --}}
    @yield('slides')

    {{-- Controls --}}
    @include('presentation::partials.controls', ['backUrl' => $backUrl])


</div>

{{-- PDF Export Progress Overlay (außerhalb slide-container, wird nicht von html2canvas erfasst) --}}
<div id="pdf-overlay" x-data x-show="$store.pdfState.exporting" x-transition.opacity class="pdf-progress-overlay" style="display: none;">
    <div style="font-size: 18px; font-weight: 600;" x-text="$store.pdfState.statusText">PDF wird erstellt…</div>
    <div style="font-size: 13px; color: #9CA3AF; margin-top: 6px;" x-text="'Slide ' + $store.pdfState.currentSlide + ' / ' + $store.pdfState.totalSlides"></div>
    <div class="pdf-progress-bar">
        <div class="pdf-progress-fill" :style="'width: ' + $store.pdfState.progress + '%'"></div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('pdfState', {
        exporting: false,
        progress: 0,
        currentSlide: 0,
        totalSlides: 0,
        statusText: 'PDF wird erstellt…',
    });
});

function presentationEngine() {
    const _slideIds = @json(collect($slides)->pluck('id')->values()->toArray());
    const _slidesTextboxes = @json(collect($slides)->mapWithKeys(fn($s) => [$s['id'] => collect($s['textboxes'] ?? [])->filter(fn($tb) => ($tb['source'] ?? '') !== 'system')->values()->toArray()])->toArray());

    return {
        currentSlide: 0,
        totalSlides: {{ count($slides) }},
        slideIds: _slideIds,
        isFullscreen: false,
        controlsHidden: false,
        controlsTimer: null,
        chartInstances: {},
        saveStatus: '',
        saveTimer: null,
        pendingOverrides: {},
        menuOpen: false,

        currentSlideId: '',

        get currentPresentTextboxes() {
            return _slidesTextboxes[this.currentSlideId] || [];
        },

        init() {
            this.currentSlideId = this.slideIds[0] || '';
            this.$watch('currentSlide', () => {
                this.currentSlideId = this.slideIds[this.currentSlide] || '';
            });

            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') {
                    this.renderChartsForSlide(0);
                }
                if (typeof window.onPresentationSlideChange === 'function') {
                    window.onPresentationSlideChange(this.slideIds[0] || '', 0, this.totalSlides);
                }
                
            });

            window.addEventListener('beforeunload', () => {
                if (typeof window.onPresentationClose === 'function') {
                    window.onPresentationClose();
                }
            });

            document.addEventListener('fullscreenchange', () => {
                this.isFullscreen = !!document.fullscreenElement;
                this.$nextTick(() => {
                    if (typeof this.renderChartsForSlide === 'function') {
                        this.renderChartsForSlide(this.currentSlide);
                    }
                });
            });
        },

        handleKeydown(e) {
            if (e.target.isContentEditable) return;
            switch (e.key) {
                case 'ArrowRight': case 'ArrowDown': case ' ':
                    e.preventDefault(); this.nextSlide(); break;
                case 'ArrowLeft': case 'ArrowUp':
                    e.preventDefault(); this.prevSlide(); break;
                case 'Escape':
                    if (this.isFullscreen) document.exitFullscreen(); break;
                case 'f': case 'F':
                    this.toggleFullscreen(); break;
                case 'Home':
                    e.preventDefault(); this.goToSlide(0); break;
                case 'End':
                    e.preventDefault(); this.goToSlide(this.totalSlides - 1); break;
            }
        },

        nextSlide() { if (this.currentSlide < this.totalSlides - 1) this.goToSlide(this.currentSlide + 1); },
        prevSlide() { if (this.currentSlide > 0) this.goToSlide(this.currentSlide - 1); },

        goToSlide(idx) {
            if (idx < 0 || idx >= this.totalSlides || idx === this.currentSlide) return;
            if (this._bgCapturing) this._bgCancelled = true;
            this.destroyChartsForSlide(this.currentSlide);
            this.currentSlide = idx;
            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') {
                    this.renderChartsForSlide(idx);
                }
            });
            if (typeof window.onPresentationSlideChange === 'function') {
                window.onPresentationSlideChange(this.slideIds[idx], idx, this.totalSlides);
            }
        },

        toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen();
            }
        },

        showControls() {
            this.controlsHidden = false;
            clearTimeout(this.controlsTimer);
            if (this.isFullscreen) {
                this.controlsTimer = setTimeout(() => { this.controlsHidden = true; }, 3000);
            }
        },

        saveOverride(event) {
            const key = event.target.dataset.overrideKey;
            if (!key) return;
            this.pendingOverrides[key] = event.target.innerText.trim();
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.flushOverrides(), 500);
        },

        async flushOverrides() {
            if (Object.keys(this.pendingOverrides).length === 0) return;
            const overrides = { ...this.pendingOverrides };
            this.pendingOverrides = {};
            try {
                const res = await fetch('{{ route("presentation.overrides", $presentation->id) }}', {
                    method: 'POST', credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ overrides }),
                });
                if (res.ok) {
                    this.saveStatus = 'Gespeichert';
                    setTimeout(() => { this.saveStatus = ''; }, 2000);
                }
            } catch (e) {
                this.saveStatus = 'Fehler beim Speichern';
                setTimeout(() => { this.saveStatus = ''; }, 3000);
            }
        },

        destroyChartsForSlide(idx) {
            const sid = this.slideIds[idx] || idx;
            const keys = Object.keys(this.chartInstances).filter(k => k.startsWith('slide-' + sid + '-'));
            keys.forEach(k => {
                try { this.chartInstances[k].destroy(); } catch(e) {}
                delete this.chartInstances[k];
            });
        },

        // Host-App kann renderChartsForSlide ueberschreiben
        renderChartsForSlide(idx) {},

        async regeneratePresentation() {
            if (!confirm('Slides mit aktuellen Daten neu generieren? Ihre Anpassungen (Schriftarten, Hintergrundfarben, hinzugefuegte Text-Slides) bleiben erhalten.')) return;
            try {
                const res = await fetch('{{ route("presentation.regenerate", $presentation->id) }}', {
                    method: 'POST', credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json();
                if (data.redirect) window.location.href = data.redirect;
            } catch (e) {
                console.error('Regeneration fehlgeschlagen:', e);
            }
        },

        async exportPdf() {
            const pdfState = Alpine.store('pdfState');
            if (pdfState.exporting) return;

            pdfState.exporting = true;
            pdfState.progress = 30;
            pdfState.currentSlide = 0;
            pdfState.totalSlides = this.totalSlides;
            pdfState.statusText = 'PDF wird generiert…';

            try {
                const response = await fetch('{{ route("presentation.export-pdf", $presentation->id) }}', {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/pdf',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(text || 'Export fehlgeschlagen');
                }

                pdfState.progress = 90;
                pdfState.statusText = 'PDF wird heruntergeladen…';

                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                const title = @json($presentation->title ?? 'Präsentation');
                a.download = title.replace(/[^a-zA-Z0-9äöüÄÖÜß\s\-_]/g, '').replace(/\s+/g, '_') + '.pdf';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                pdfState.progress = 100;
                pdfState.statusText = 'Fertig!';
                await this._wait(800);
            } catch (e) {
                console.error('PDF-Export fehlgeschlagen:', e);
                pdfState.statusText = 'Fehler beim Erstellen des PDFs';
                pdfState.progress = 0;
                await this._wait(2500);
            } finally {
                pdfState.exporting = false;
                pdfState.progress = 0;
            }
        },

        _wait(ms) { return new Promise(r => setTimeout(r, ms)); },
    };
}
</script>

{{-- Host-App kann hier Chart-Rendering und eigene Scripts einschleusen --}}
@stack('presentation-scripts')

</body>
</html>
