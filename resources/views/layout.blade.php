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

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js" defer></script>
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
    return {
        currentSlide: 0,
        totalSlides: {{ count($slides) }},
        isFullscreen: false,
        controlsHidden: false,
        controlsTimer: null,
        chartInstances: {},
        saveStatus: '',
        saveTimer: null,
        pendingOverrides: {},
        menuOpen: false,

        init() {
            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') {
                    this.renderChartsForSlide(0);
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
            this.destroyChartsForSlide(this.currentSlide);
            this.currentSlide = idx;
            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') {
                    this.renderChartsForSlide(idx);
                }
            });
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
            const keys = Object.keys(this.chartInstances).filter(k => k.startsWith('slide-' + idx + '-'));
            keys.forEach(k => {
                try { this.chartInstances[k].destroy(); } catch(e) {}
                delete this.chartInstances[k];
            });
        },

        // Host-App kann renderChartsForSlide ueberschreiben
        renderChartsForSlide(idx) {},

        async regeneratePresentation() {
            if (!confirm('Slides neu generieren? Manuelle Textaenderungen an generierten Slides gehen verloren. Eigene Text-Slides bleiben erhalten.')) return;
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
            pdfState.progress = 0;
            pdfState.currentSlide = 0;
            pdfState.totalSlides = this.totalSlides;
            pdfState.statusText = 'PDF wird vorbereitet…';

            const originalSlide = this.currentSlide;
            const wasFullscreen = this.isFullscreen;
            if (wasFullscreen) {
                document.exitFullscreen().catch(() => {});
                await this._wait(400);
            }

            const { jsPDF } = window.jspdf;
            const slideW = {{ $config['slide_width'] ?? 1280 }};
            const slideH = {{ $config['slide_height'] ?? 720 }};
            const pdf = new jsPDF({ orientation: 'landscape', unit: 'px', format: [slideW, slideH], hotfixes: ['px_scaling'] });
            const title = @json($presentation->title ?? 'Präsentation');

            const overlay = document.getElementById('pdf-overlay');
            const topBar = document.querySelector('.top-bar');
            const controlsBar = document.querySelector('.controls-bar');

            for (let i = 0; i < this.totalSlides; i++) {
                pdfState.currentSlide = i + 1;
                pdfState.statusText = 'Slide ' + (i + 1) + ' wird erfasst…';
                pdfState.progress = Math.round(((i) / this.totalSlides) * 90);

                this.destroyChartsForSlide(this.currentSlide);
                this.currentSlide = i;
                await this._wait(150);

                if (typeof this.renderChartsForSlide === 'function') {
                    this.renderChartsForSlide(i);
                }
                await this._wait(800);

                const slideEl = document.querySelector('[data-slide-index="' + i + '"]');
                if (!slideEl) continue;

                if (overlay) overlay.style.display = 'none';
                if (topBar) topBar.style.display = 'none';
                if (controlsBar) controlsBar.style.display = 'none';
                await this._wait(50);

                try {
                    const canvas = await html2canvas(slideEl, {
                        scale: 2,
                        useCORS: true,
                        allowTaint: true,
                        backgroundColor: null,
                        logging: false,
                        onclone: (clonedDoc, clonedEl) => {
                            clonedEl.style.width = slideW + 'px';
                            clonedEl.style.height = slideH + 'px';
                            clonedEl.style.borderRadius = '0';
                            clonedEl.style.animation = 'none';
                            clonedEl.style.transform = 'none';
                            clonedEl.style.position = 'relative';
                            clonedEl.style.overflow = 'hidden';
                            const removes = clonedDoc.querySelectorAll('.top-bar, .controls-bar, .pdf-progress-overlay, #pdf-overlay');
                            removes.forEach(el => el.remove());
                        },
                    });

                    if (i > 0) pdf.addPage([slideW, slideH], 'landscape');
                    pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, 0, slideW, slideH);
                } catch (e) {
                    console.error('Slide ' + i + ' konnte nicht erfasst werden:', e);
                }

                if (overlay) overlay.style.display = '';
                if (topBar) topBar.style.display = '';
                if (controlsBar) controlsBar.style.display = '';
            }

            pdfState.statusText = 'PDF wird fertiggestellt…';
            pdfState.progress = 95;
            await this._wait(200);

            const filename = title.replace(/[^a-zA-Z0-9äöüÄÖÜß\s\-_]/g, '').replace(/\s+/g, '_') + '.pdf';
            pdf.save(filename);

            pdfState.progress = 100;
            pdfState.statusText = 'Fertig!';
            await this._wait(500);

            this.destroyChartsForSlide(this.currentSlide);
            this.currentSlide = originalSlide;
            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') {
                    this.renderChartsForSlide(originalSlide);
                }
            });

            pdfState.exporting = false;
            pdfState.progress = 0;
        },

        _wait(ms) { return new Promise(r => setTimeout(r, ms)); },
    };
}
</script>

{{-- Host-App kann hier Chart-Rendering und eigene Scripts einschleusen --}}
@stack('presentation-scripts')

</body>
</html>
