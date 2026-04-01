@php
    $title = $title ?? 'AI Slides';
    $latest = $latest ?? [
        'status' => 'empty',
        'generated_at' => null,
        'quarter' => null,
        'html' => null,
        'dataset' => null,
    ];
    $generateApiPath = $generateApiPath ?? '/api/agency-highlights/generate';
    $latestApiPath = $latestApiPath ?? '/api/agency-highlights/latest';
    $emptyStateText = $emptyStateText ?? 'No presentation yet';
    $emptyStateHint = $emptyStateHint ?? 'Click Generate to create your latest presentation';
@endphp

<div x-data="aiSlides({
        title: @js($title),
        initialLatest: @js($latest),
        generateApiPath: @js($generateApiPath),
        latestApiPath: @js($latestApiPath),
        csrfToken: @js(csrf_token()),
    })"
    x-init="init()"
    class="-m-6 flex flex-col overflow-hidden"
    style="height: calc(100dvh - 56px)"
>
    <div class="flex-none flex items-center justify-between gap-3 px-5 h-13 bg-brand-dark border-b border-white/10"
         style="min-height:52px">
        <div class="flex items-center gap-3 min-w-0">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-brand-primary flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5"/>
                </svg>
                <span class="text-sm font-semibold text-white" x-text="title"></span>
            </div>

            <span x-show="latest.quarter?.label && !generating"
                  class="hidden sm:inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-white/10 text-white/70"
                  x-text="latest.quarter?.label"></span>

            <span x-show="latest.quarter?.date_from && !generating"
                  class="hidden md:inline text-xs text-white/40"
                  x-text="`${latest.quarter?.date_from} → ${latest.quarter?.date_to}`"></span>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
            <span x-show="latest.generated_at && !generating"
                  class="hidden lg:inline text-xs text-white/40"
                  x-text="`Generated ${formatDate(latest.generated_at)}`"></span>

            <button type="button"
                    x-show="hasPresentation() && !generating"
                    @click="enterFullscreen()"
                    title="Fullscreen"
                    class="inline-flex items-center justify-center h-8 w-8 rounded bg-white/10 hover:bg-white/20 text-white/70 hover:text-white transition-colors">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>
                </svg>
            </button>

            <button type="button"
                    :disabled="generating"
                    @click="generate()"
                    class="inline-flex items-center gap-1.5 h-8 px-3 rounded text-xs font-semibold transition-colors"
                    :class="generating
                        ? 'bg-white/10 text-white/40 cursor-not-allowed'
                        : 'bg-brand-primary hover:bg-brand-primary/90 text-white'">
                <svg x-show="generating" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="generating ? 'Generating…' : (hasPresentation() ? 'Regenerate' : 'Generate')"></span>
            </button>
        </div>
    </div>

    <div x-show="error" x-cloak
         class="flex-none flex items-center gap-3 px-5 py-2 bg-red-900/60 border-b border-red-700/40 text-red-200 text-xs">
        <svg class="h-4 w-4 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
        </svg>
        <span x-text="error" class="flex-1"></span>
        <button @click="error=''" class="text-red-400 hover:text-red-200">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="flex-1 min-h-0 relative bg-neutral-950">
        <div x-show="generating"
             class="absolute inset-0 flex flex-col items-center justify-center gap-8 z-10">
            <div class="relative flex items-center justify-center">
                <span class="absolute inline-flex h-24 w-24 rounded-full bg-brand-primary/20 animate-ping"></span>
                <span class="relative inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-primary/30 ring-1 ring-brand-primary/50">
                    <svg class="h-7 w-7 text-brand-primary animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </div>

            <div class="text-center space-y-1">
                <p class="text-base font-semibold text-white" x-text="loadingStage()"></p>
                <p class="text-xs text-white/40" x-text="elapsedLabel()"></p>
            </div>

            <div class="flex flex-wrap justify-center gap-2 max-w-xs">
                <template x-for="(step, i) in loadingSteps" :key="i">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition-all duration-500"
                          :class="stepClass(i)">
                        <svg x-show="stepDone(i)" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <svg x-show="stepActive(i)" class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="step.label"></span>
                    </span>
                </template>
            </div>
        </div>

        <div x-show="!generating && !hasPresentation()"
             class="absolute inset-0 flex flex-col items-center justify-center gap-4">
            <svg class="h-14 w-14 text-white/10" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6"/>
            </svg>
            <div class="text-center">
                <p class="text-white/50 text-sm font-medium">{{ $emptyStateText }}</p>
                <p class="text-white/25 text-xs mt-1">{{ $emptyStateHint }}</p>
            </div>
        </div>

        <iframe x-show="!generating && hasPresentation()"
                x-ref="stage"
                class="absolute inset-0 w-full h-full border-0"
                sandbox="allow-scripts"
                :srcdoc="latest.html || ''"
                :title="title"
                loading="lazy"></iframe>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function aiSlides(options) {
                return {
                    title: options.title || 'AI Slides',
                    generating: false,
                    error: '',
                    latest: options.initialLatest || {},
                    csrfToken: options.csrfToken || '',
                    generateApiPath: options.generateApiPath,
                    latestApiPath: options.latestApiPath,
                    _elapsedSecs: 0,
                    _elapsedTimer: null,
                    _startedAt: 0,

                    loadingSteps: [
                        { label: 'Daten laden',   thresholdSec: 0  },
                        { label: 'KI-Analyse',    thresholdSec: 4  },
                        { label: 'Folien rendern', thresholdSec: 9  },
                        { label: 'Fertigstellen', thresholdSec: 14 },
                    ],

                    init() {
                        // initial data is already in this.latest (from PHP @js($latest))
                        // no fetch needed on load
                    },

                    hasPresentation() {
                        return !!(this.latest && this.latest.html);
                    },

                    async generate() {
                        this.generating = true;
                        this.error = '';
                        this._elapsedSecs = 0;
                        this._startedAt = Date.now();
                        clearInterval(this._elapsedTimer);
                        this._elapsedTimer = setInterval(() => { this._elapsedSecs++; }, 1000);

                        // Capture the current generated_at before we fire — only reload
                        // when we see a DIFFERENT (newer) timestamp to avoid false positives.
                        const previousGeneratedAt = this.latest?.generated_at || null;

                        try {
                            const r = await fetch(this.generateApiPath, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                                body: JSON.stringify({}),
                            });

                            if (!r.ok && r.status !== 202) {
                                const payload = await r.json().catch(() => ({}));
                                this.error = payload.error || `Server error ${r.status}`;
                                return;
                            }

                            await this.pollForCompletion(previousGeneratedAt);
                        } catch (e) {
                            this.error = e?.message || 'Unexpected error';
                        } finally {
                            this.generating = false;
                            clearInterval(this._elapsedTimer);
                        }
                    },

                    async pollForCompletion(previousGeneratedAt) {
                        for (let i = 0; i < 45; i++) {
                            await new Promise((res) => setTimeout(res, 4000));
                            try {
                                const r = await fetch(this.latestApiPath);
                                if (r.ok) {
                                    const d = await r.json();
                                    if (d?.generated_at && d.generated_at !== previousGeneratedAt) {
                                        window.location.reload();
                                        return;
                                    }
                                }
                            } catch (_) {}
                        }
                        this.error = 'Generation timed out - check server logs';
                    },

                    enterFullscreen() {
                        const el = this.$refs.stage;
                        if (el?.requestFullscreen) el.requestFullscreen();
                        else if (el?.webkitRequestFullscreen) el.webkitRequestFullscreen();
                    },

                    currentStepIndex() {
                        let idx = 0;
                        for (let i = 0; i < this.loadingSteps.length; i++) {
                            if (this._elapsedSecs >= this.loadingSteps[i].thresholdSec) idx = i;
                        }
                        return idx;
                    },

                    stepDone(i) { return i < this.currentStepIndex(); },
                    stepActive(i) { return i === this.currentStepIndex(); },

                    stepClass(i) {
                        if (this.stepDone(i)) return 'bg-green-900/60 text-green-400 ring-1 ring-green-700/40';
                        if (this.stepActive(i)) return 'bg-brand-primary/20 text-brand-primary ring-1 ring-brand-primary/40';
                        return 'bg-white/5 text-white/30';
                    },

                    loadingStage() {
                        return [
                            'Daten werden geladen…',
                            'KI analysiert die Kampagnendaten…',
                            'Folien werden gerendert…',
                            'Letzter Schliff…',
                        ][this.currentStepIndex()];
                    },

                    elapsedLabel() {
                        if (this._elapsedSecs < 5) return 'Dauert in der Regel 10–20 Sekunden';
                        return `${this._elapsedSecs}s vergangen`;
                    },

                    formatDate(value) {
                        if (!value) return '-';
                        return new Date(value).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' });
                    },
                };
            }
        </script>
    @endpush
@endonce
