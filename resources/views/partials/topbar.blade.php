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
            <button class="menu-item" @click="exportPdf(); menuOpen = false" :disabled="$store.pdfState.exporting">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span x-text="$store.pdfState.exporting ? 'Wird exportiert…' : 'Als PDF exportieren'"></span>
            </button>
        </div>
    </div>
</div>
