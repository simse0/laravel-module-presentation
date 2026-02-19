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
        }

        .slide {
            width: {{ $config['slide_width'] ?? 1280 }}px;
            height: {{ $config['slide_height'] ?? 720 }}px;
            position: relative; overflow: hidden;
            border-radius: 8px; transition: all 0.3s ease;
        }

        .slide-light { background: #ffffff; color: #1a1a2e; }
        .slide-dark { background: #1D1D1D; color: #E5E7EB; }
        .fullscreen .slide { width: 100vw; height: 100vh; border-radius: 0; }

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
    </style>
    {{-- Host-App kann hier eigene Styles einschleusen --}}
    @stack('presentation-styles')
</head>
<body>

<div x-data="presentationEngine()" @keydown.window="handleKeydown($event)"
     :class="{ 'fullscreen': isFullscreen }" class="slide-container" @mousemove="showControls()">

    {{-- Slides werden von der Host-App gerendert (publishable view) --}}
    @yield('slides')

    {{-- Controls --}}
    @include('presentation::partials.controls', ['backUrl' => $backUrl])
</div>

<script>
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
                const res = await fetch('{{ route("presentation.overrides", $subject->getKey()) }}', {
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

        // Host-App kann renderChartsForSlide überschreiben
        renderChartsForSlide(idx) {}
    };
}
</script>

{{-- Host-App kann hier Chart-Rendering und eigene Scripts einschleusen --}}
@stack('presentation-scripts')

</body>
</html>
