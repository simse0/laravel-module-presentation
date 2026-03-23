@php $mode = $mode ?? 'present'; @endphp
<div class="top-bar">
    <div style="position: relative;" @click.outside="menuOpen = false">
        <button class="ctrl-btn" @click="menuOpen = !menuOpen" title="Menü">
            <svg style="width:16px;height:16px;" fill="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="5" r="2"/>
                <circle cx="12" cy="12" r="2"/>
                <circle cx="12" cy="19" r="2"/>
            </svg>
        </button>
        <div x-show="menuOpen" x-transition.scale.origin.top.right class="menu-dropdown">
            @if($mode === 'present' && config('presentation.enable_edit_mode', true))
            <a :href="`{{ route('presentation.edit', $presentation->id) }}#slide=${currentSlide}`" class="menu-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Bearbeiten</span>
            </a>
            @endif

            @if($mode === 'edit')
            <a :href="`{{ route('presentation.show', $presentation->id) }}#slide=${currentSlide}`" class="menu-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16l13-8z"/>
                </svg>
                <span>Präsentieren</span>
            </a>
            @endif

            <button class="menu-item" @click="exportPdf(); menuOpen = false" :disabled="$store.exportState.exporting">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span x-text="$store.exportState.exporting ? 'Wird exportiert…' : 'Als PDF exportieren'"></span>
            </button>

            <button class="menu-item" @click="exportPptx(); menuOpen = false" :disabled="$store.exportState.exporting">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span x-text="$store.exportState.exporting ? 'Wird exportiert…' : 'Als PowerPoint exportieren'"></span>
            </button>

            {{-- "Neu generieren" deaktiviert – Funktion aktuell nicht zuverlässig
            <button class="menu-item" @click="regeneratePresentation(); menuOpen = false">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span>Neu generieren</span>
            </button>
            --}}
        </div>
    </div>
</div>
