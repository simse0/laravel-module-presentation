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
            width: 220px; background: #111; border-right: 1px solid #2A2A2A;
            display: flex; flex-direction: column; flex-shrink: 0; overflow: hidden;
        }
        .sidebar-slides {
            flex: 1; overflow-y: auto; padding: 4px 0; display: flex;
            flex-direction: column; gap: 1px;
        }
        .sidebar-slides::-webkit-scrollbar { width: 4px; }
        .sidebar-slides::-webkit-scrollbar-thumb { background: #374151; border-radius: 2px; }

        .sidebar-slide {
            position: relative; cursor: pointer; transition: background 0.12s;
            display: flex; align-items: center; gap: 8px;
            padding: 7px 10px; user-select: none; flex-shrink: 0;
        }
        .sidebar-slide:hover { background: rgba(255,255,255,0.06); }
        .sidebar-slide.active { background: {{ $accent }}18; }
        .sidebar-slide.active .sidebar-slide-number { color: {{ $accent }}; }
        .sidebar-slide.active .sidebar-slide-title { color: #fff; }

        .sidebar-slide-drag {
            flex-shrink: 0; color: rgba(255,255,255,0.15); font-size: 11px;
            cursor: grab; line-height: 1; padding: 2px 0;
        }
        .sidebar-slide:hover .sidebar-slide-drag { color: rgba(255,255,255,0.4); }

        .sidebar-slide-number {
            font-size: 11px; color: rgba(255,255,255,0.35); font-weight: 700;
            flex-shrink: 0; width: 18px; text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .sidebar-slide-title {
            font-size: 12px; color: rgba(255,255,255,0.65); font-weight: 500;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            flex: 1; line-height: 1.3;
        }
        .sidebar-slide-remove {
            flex-shrink: 0; background: transparent; border: none;
            color: rgba(255,255,255,0.2); cursor: pointer; font-size: 14px;
            padding: 0 2px; opacity: 0; transition: opacity 0.12s; line-height: 1;
        }
        .sidebar-slide:hover .sidebar-slide-remove { opacity: 1; }
        .sidebar-slide-remove:hover { color: #EF4444; }

        .sortable-ghost { opacity: 0.3; }
        .sortable-chosen { background: rgba(255,255,255,0.08); }

        .sidebar-footer {
            padding: 6px 10px; border-top: 1px solid #2A2A2A; flex-shrink: 0;
        }

        .btn-add-slide {
            width: 100%; padding: 8px; border-radius: 6px; border: 1px dashed #374151;
            background: transparent; color: #9CA3AF; font-size: 12px; cursor: pointer;
            font-family: inherit; transition: all 0.15s; display: flex;
            align-items: center; justify-content: center; gap: 6px;
        }
        .btn-add-slide:hover { border-color: {{ $accent }}; color: {{ $accent }}; }

        .edit-main-col {
            flex: 1; display: flex; flex-direction: column; min-width: 0; min-height: 0;
        }
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

        /* Editing Toolbar */
        .edit-toolbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 4px 16px; background: #1E1E1E; border-bottom: 1px solid #2A2A2A;
            flex-shrink: 0; z-index: 49; gap: 12px; min-height: 36px;
        }
        .toolbar-left { display: flex; align-items: center; gap: 4px; }
        .toolbar-right { display: flex; align-items: center; gap: 4px; }
        .toolbar-btn {
            display: flex; align-items: center; gap: 5px;
            padding: 5px 10px; border-radius: 4px; border: 1px solid transparent;
            background: transparent; color: #9CA3AF; font-size: 12px; font-weight: 500;
            cursor: pointer; font-family: inherit; transition: all 0.15s; white-space: nowrap;
        }
        .toolbar-btn:hover { background: rgba(255,255,255,0.08); color: #D1D5DB; }
        .toolbar-btn.active { background: {{ $accent }}22; color: {{ $accent }}; border-color: {{ $accent }}44; }
        .toolbar-btn svg { width: 14px; height: 14px; flex-shrink: 0; }
        .toolbar-separator { width: 1px; height: 20px; background: #333; margin: 0 6px; }
        .font-size-control { display: flex; align-items: center; gap: 2px; }
        .font-size-control label { font-size: 11px; color: #6B7280; margin-right: 4px; white-space: nowrap; }
        .font-size-btn {
            width: 24px; height: 24px; border-radius: 4px; border: 1px solid #333;
            background: #1A1A1A; color: #D1D5DB; cursor: pointer; font-size: 14px; font-weight: 600;
            display: flex; align-items: center; justify-content: center; transition: all 0.15s;
            font-family: inherit; line-height: 1;
        }
        .font-size-btn:hover { background: #2A2A2A; border-color: #555; }
        .font-size-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .font-size-input {
            width: 44px; height: 24px; border-radius: 4px; border: 1px solid #333;
            background: #1A1A1A; color: #fff; font-size: 12px; text-align: center;
            font-family: inherit; outline: none;
        }
        .font-size-input:focus { border-color: {{ $accent }}; }

        .color-palette { display: flex; align-items: center; gap: 3px; }
        .color-palette label { font-size: 11px; color: #6B7280; margin-right: 4px; white-space: nowrap; }
        .color-swatch { width: 20px; height: 20px; border-radius: 4px; border: 2px solid transparent; cursor: pointer; transition: all 0.15s; flex-shrink: 0; box-shadow: inset 0 0 0 1px rgba(128,128,128,0.25); }
        .color-swatch:hover { transform: scale(1.15); }
        .color-swatch.active { border-color: #00AFCE; box-shadow: 0 0 0 1px #00AFCE; }

        .bold-toggle, .link-toggle {
            display: flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 4px; border: 1px solid #333;
            background: #1A1A1A; color: #D1D5DB; cursor: pointer; font-size: 14px;
            font-weight: 700; font-family: inherit; transition: all 0.15s;
        }
        .bold-toggle:hover, .link-toggle:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .bold-toggle.active, .link-toggle.active { background: {{ $accent }}22; color: {{ $accent }}; border-color: {{ $accent }}44; }
        .link-toggle svg { width: 14px; height: 14px; }

        .align-group { display: flex; align-items: center; gap: 2px; }
        .align-toggle {
            display: flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 4px; border: 1px solid #333;
            background: #1A1A1A; color: #D1D5DB; cursor: pointer; transition: all 0.15s;
        }
        .align-toggle:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .align-toggle.active { background: {{ $accent }}22; color: {{ $accent }}; border-color: {{ $accent }}44; }
        .align-toggle svg { width: 15px; height: 15px; }

        .link-popup {
            position: absolute; top: 100%; right: 0; margin-top: 4px;
            background: #2A2A2A; border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px; padding: 10px 12px; min-width: 300px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5); z-index: 200;
            display: flex; flex-direction: column; gap: 8px;
        }
        .link-popup-row { display: flex; align-items: center; gap: 6px; }
        .link-popup input[type="url"] {
            flex: 1; height: 30px; border-radius: 4px; border: 1px solid #444;
            background: #1A1A1A; color: #fff; font-size: 12px; padding: 0 8px;
            font-family: inherit; outline: none;
        }
        .link-popup input[type="url"]:focus { border-color: {{ $accent }}; }
        .link-popup-btn {
            height: 30px; padding: 0 12px; border-radius: 4px; border: none;
            font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit;
            transition: all 0.15s; white-space: nowrap;
        }
        .link-popup-btn.primary { background: {{ $accent }}; color: #fff; }
        .link-popup-btn.primary:hover { opacity: 0.85; }
        .link-popup-btn.danger { background: transparent; color: #EF4444; border: 1px solid #EF444444; }
        .link-popup-btn.danger:hover { background: #EF444422; }

        .tb-link-indicator {
            position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%);
            background: {{ $accent }}; color: #fff; font-size: 9px; padding: 1px 5px;
            border-radius: 3px; white-space: nowrap; max-width: 160px;
            overflow: hidden; text-overflow: ellipsis; pointer-events: none;
            opacity: 0; transition: opacity 0.15s;
        }
        .slide-textbox:hover .tb-link-indicator,
        .slide-textbox.tb-selected .tb-link-indicator { opacity: 1; }

        /* Textbox Layer & Items */
        .textbox-layer {
            position: absolute;
            width: {{ $config['slide_width'] ?? 1280 }}px;
            height: {{ $config['slide_height'] ?? 720 }}px;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) scale(var(--slide-scale, 0.7));
            transform-origin: center center;
            pointer-events: none;
            z-index: 5;
        }
        .textbox-layer.placing { pointer-events: all; cursor: crosshair; }
        .slide-bg-layer {
            position: absolute;
            width: {{ $config['slide_width'] ?? 1280 }}px;
            height: {{ $config['slide_height'] ?? 720 }}px;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) scale(var(--slide-scale, 0.7));
            transform-origin: center center;
            pointer-events: none;
            z-index: 0;
            border-radius: 8px;
            transition: background 0.2s ease;
        }
        .slide-textbox {
            position: absolute; pointer-events: all;
            padding: 0; border: none;
            border-radius: 4px; cursor: move; min-height: 28px; min-width: 60px;
            outline: 2px dashed transparent; outline-offset: -2px;
            word-wrap: break-word; line-height: 1.4;
            background: transparent; overflow: visible;
        }
        .slide-textbox * { cursor: move; }
        .slide-textbox:hover { outline-color: {{ $accent }}66; }
        .slide-textbox.tb-selected {
            outline-color: {{ $accent }}; outline-style: solid; box-shadow: 0 0 0 1px {{ $accent }}44;
        }
        .slide-textbox.tb-editing, .slide-textbox.tb-editing * { cursor: text; }

        /* Resize Handles */
        .tb-resize-handle {
            position: absolute; width: 10px; height: 10px;
            background: {{ $accent }}; border: 1px solid #fff;
            border-radius: 2px; z-index: 3; display: none;
        }
        .slide-textbox.tb-selected .tb-resize-handle { display: block; }
        .tb-resize-br { bottom: -5px; right: -5px; cursor: nwse-resize; }
        .tb-resize-r { top: 50%; right: -5px; transform: translateY(-50%); cursor: ew-resize; }
        .tb-resize-b { bottom: -5px; left: 50%; transform: translateX(-50%); cursor: ns-resize; }

        .slide-textbox-del {
            position: absolute; top: -10px; right: -10px;
            width: 20px; height: 20px; border-radius: 50%;
            background: #EF4444; color: #fff; border: 2px solid #1D1D1D;
            font-size: 11px; cursor: pointer; display: none;
            align-items: center; justify-content: center; line-height: 1;
            z-index: 4;
        }
        .slide-textbox.tb-selected .slide-textbox-del { display: flex; }

        /* Image Elements */
        .slide-image {
            position: absolute; pointer-events: all;
            border: 2px dashed transparent;
            border-radius: 4px; cursor: move;
            min-width: 40px; min-height: 40px;
            overflow: hidden;
        }
        .slide-image:hover { border-color: {{ $accent }}66; }
        .slide-image.img-selected {
            border-color: {{ $accent }}; box-shadow: 0 0 0 1px {{ $accent }}44;
        }
        .slide-image.img-selected .tb-resize-handle { display: block; }
        .slide-image .slide-textbox-del { display: none; }
        .slide-image.img-selected .slide-textbox-del { display: flex; }

        /* Hide elements in edit mode while preserving layout space */
        .edit-hidden { visibility: hidden !important; pointer-events: none !important; }

        /* SortableJS Feedback */
        .sortable-ghost { opacity: 0.3; }
        .sortable-chosen { box-shadow: 0 4px 12px rgba(0,0,0,0.4); }

        /* Save Button Dirty State */
        .btn-save-dirty { position: relative; }
        .btn-save-dirty::before {
            content: ''; position: absolute; top: 4px; left: 6px;
            width: 6px; height: 6px; border-radius: 50%;
            background: #fff;
        }
    </style>

    @stack('presentation-styles')

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>
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
            <button class="btn-action btn-secondary" @click="undo()" :disabled="_undoStack.length <= 1" title="Rückgängig (Strg+Z)" style="padding: 6px 8px;">
                <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a4 4 0 010 8H9m-6-8l4-4m-4 4l4 4"/>
                </svg>
            </button>
            <button class="btn-action btn-secondary" @click="redo()" :disabled="_redoStack.length === 0" title="Wiederherstellen (Strg+Shift+Z)" style="padding: 6px 8px;">
                <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a4 4 0 000 8h4m6-8l-4-4m4 4l-4 4"/>
                </svg>
            </button>
            <button class="btn-action btn-secondary" @click="exitToPresentation()" title="Zur Präsentationsansicht">
                <svg style="width:14px;height:14px;display:inline;vertical-align:middle;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Zur Präsentation
            </button>
            <button class="btn-action btn-primary" :class="{ 'btn-save-dirty': isDirty }" @click="saveAll()" x-text="isDirty ? '● Speichern' : 'Speichern'"></button>
            <div style="position: relative;" @click.outside="menuOpen = false">
                <button class="btn-action btn-secondary" @click="menuOpen = !menuOpen" style="padding: 6px 8px;">
                    <svg style="width:16px;height:16px;" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
                    </svg>
                </button>
                <div x-show="menuOpen" x-transition.scale.origin.top.right class="menu-dropdown">
                    <button class="menu-item" @click="exportPdf(); menuOpen = false" :disabled="$store.exportState.exporting">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Als PDF exportieren</span>
                    </button>
                    <button class="menu-item" @click="exportPptx(); menuOpen = false" :disabled="$store.exportState.exporting">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Als PowerPoint exportieren</span>
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
    </div>

    {{-- Body: Sidebar + Main --}}
    <div class="edit-body">
        {{-- Sidebar --}}
        <div class="edit-sidebar">
            <div class="sidebar-slides" x-ref="sidebarSlides">
                <template x-for="(slide, idx) in slidesData" :key="slide.id">
                    <div class="sidebar-slide" :class="{ 'active': currentSlide === idx }"
                         @click="goToSlide(idx)" :data-slide-id="slide.id">
                        <span class="sidebar-slide-drag" title="Ziehen zum Sortieren">⠿</span>
                        <span class="sidebar-slide-number" x-text="idx + 1"></span>
                        <span class="sidebar-slide-title" x-text="slideLabel(slide)"></span>
                        <button class="sidebar-slide-remove" @click.stop="removeSlide(slide.id, idx)"
                                x-show="slide.source === 'user'" title="Slide entfernen">&times;</button>
                    </div>
                </template>
            </div>
            <div class="sidebar-footer">
                <template x-if="!addingSlide">
                    <button class="btn-add-slide" @click="addingSlide = true; $nextTick(() => $refs.newSlideInput?.focus())">
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Text-Slide
                    </button>
                </template>
                <template x-if="addingSlide">
                    <div style="display: flex; gap: 4px;">
                        <input type="text" x-ref="newSlideInput" x-model="newSlideTitle"
                               @keydown.enter="submitNewSlide()" @keydown.escape="addingSlide = false; newSlideTitle = ''"
                               placeholder="Titel…"
                               style="flex: 1; background: #1A1A1A; border: 1px solid #374151; color: #fff; font-size: 12px; padding: 6px 8px; border-radius: 4px; font-family: inherit; outline: none; min-width: 0;"
                               @focus="$el.style.borderColor = '{{ $accent }}'" @blur="$el.style.borderColor = '#374151'">
                        <button @click="submitNewSlide()" title="Hinzufügen"
                                style="background: {{ $accent }}; border: none; color: #fff; width: 30px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                        <button @click="addingSlide = false; newSlideTitle = ''" title="Abbrechen"
                                style="background: transparent; border: 1px solid #374151; color: #9CA3AF; width: 30px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>

        {{-- Main Column (Toolbar + Slide) --}}
        <div class="edit-main-col">
            {{-- Editing Toolbar --}}
            <div class="edit-toolbar">
                <div class="toolbar-left">
                    <button class="toolbar-btn" :class="{ 'active': placingTextbox }" @click="togglePlaceTextbox()" title="Textfeld auf Slide platzieren">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/></svg>
                        Textfeld
                    </button>
                    <button class="toolbar-btn" :class="{ 'active': _uploadingImage }" @click="openImageUpload()" title="Bild auf Slide platzieren">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Bild
                    </button>
                    <input type="file" x-ref="imageInput" accept="{{ implode(',', array_map(fn($t) => '.' . $t, config('presentation.images.allowed_types', ['jpg','jpeg','png','webp','svg']))) }}"
                           style="display:none" @change="handleImageUpload($event)">
                    <div class="toolbar-separator"></div>
                    <div class="color-palette">
                        <label>Hintergrund</label>
                        <button class="color-swatch"
                                :class="{ 'active': currentSlideTheme === 'light' }"
                                style="background: #ffffff;"
                                @click="setSlideTheme('light')"
                                title="Weißer Hintergrund"></button>
                        <button class="color-swatch"
                                :class="{ 'active': currentSlideTheme === 'dark' }"
                                style="background: #1D1D1D;"
                                @click="setSlideTheme('dark')"
                                title="Schwarzer Hintergrund"></button>
                    </div>
                </div>
                <div class="toolbar-right" x-show="selectedElement" x-transition>
                    <div class="font-size-control">
                        <label>Schriftgröße</label>
                        <button class="font-size-btn" @click="changeFontSize(-2)" :disabled="currentFontSize <= 8">−</button>
                        <input type="number" class="font-size-input" :value="currentFontSize"
                               @change="setFontSize(parseInt($event.target.value))"
                               @keydown.enter="$event.target.blur()" min="8" max="120">
                        <button class="font-size-btn" @click="changeFontSize(2)">+</button>
                    </div>
                    <div class="toolbar-separator"></div>
                    <div class="color-palette">
                        <label>Schriftfarbe</label>
                        <template x-for="c in colorPresets" :key="c">
                            <button class="color-swatch" :class="{ 'active': currentColor === c }"
                                    :style="'background:' + c" @click="setTextColor(c)"
                                    :title="c"></button>
                        </template>
                    </div>
                    <div class="toolbar-separator"></div>
                    <button class="bold-toggle" :class="{ 'active': currentBold }"
                            @click="toggleBold()" title="Fett / Normal">B</button>
                    <div class="align-group" x-show="selectedElement?.type === 'textbox'">
                        <button class="align-toggle" :class="{ 'active': currentAlign === 'left' }"
                                @click="setAlign('left')" title="Linksbündig">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h10M4 14h16M4 18h10"/></svg>
                        </button>
                        <button class="align-toggle" :class="{ 'active': currentAlign === 'center' }"
                                @click="setAlign('center')" title="Zentriert">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 10h10M4 14h16M7 18h10"/></svg>
                        </button>
                        <button class="align-toggle" :class="{ 'active': currentAlign === 'right' }"
                                @click="setAlign('right')" title="Rechtsbündig">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 10h10M4 14h16M10 18h10"/></svg>
                        </button>
                    </div>
                    <div style="position: relative;">
                        <button class="link-toggle" :class="{ 'active': currentLink }"
                                @click.stop="openLinkPopup()" title="Link setzen"
                                x-show="selectedElement?.type === 'textbox'">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        </button>
                        <div class="link-popup" x-show="linkPopupOpen" x-transition @click.stop
                             @keydown.escape.stop="linkPopupOpen = false">
                            <div class="link-popup-row">
                                <input type="url" x-ref="linkInput" x-model="linkInputValue"
                                       placeholder="https://example.com"
                                       @keydown.enter.prevent="applyLink()">
                                <button class="link-popup-btn primary" @click="applyLink()">Setzen</button>
                            </div>
                            <div class="link-popup-row" x-show="currentLink">
                                <button class="link-popup-btn danger" @click="removeLink()">Link entfernen</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Hinweis für generierte Slides --}}
            <div x-show="isGeneratedSlide" x-transition
                 style="display:flex; align-items:center; gap:8px; padding:7px 16px; background: rgba(0,175,206,0.08); border-bottom: 1px solid rgba(0,175,206,0.2); font-size:12px; color:#9CA3AF; flex-shrink:0;">
                <svg style="width:14px;height:14px;flex-shrink:0;color:#00AFCE;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Diese Slide wird automatisch generiert. Inhalte können nicht verschoben werden — du kannst aber Textfelder und Bilder hinzufügen.</span>
            </div>

            {{-- Main Slide Area --}}
            <div class="edit-main" @click="deselectAll($event)">
                {{-- Dynamic slide background (reactive to theme changes) --}}
                <div class="slide-bg-layer"
                     :class="currentSlideTheme === 'light' ? 'slide-light' : 'slide-dark'"></div>

                @yield('slides')

                {{-- Image Overlay Layer --}}
                <div class="textbox-layer">
                    <template x-for="img in currentImages" :key="img.id">
                        <div class="slide-image"
                             :class="{ 'img-selected': selectedElement?.id === img.id }"
                             :style="`left:${img.x}px; top:${img.y}px; width:${img.width}px; height:${img.height}px;`"
                             @mousedown.stop="startDragElement($event, img)"
                             @click.stop="selectImage(img)">
                            <img :src="img.url" :alt="img.filename || ''"
                                 style="width:100%; height:100%; object-fit:contain; pointer-events:none; user-select:none;" draggable="false">
                            <div class="tb-resize-handle tb-resize-r" @mousedown.stop.prevent="startResizeElement($event, img, 'r')"></div>
                            <div class="tb-resize-handle tb-resize-b" @mousedown.stop.prevent="startResizeElement($event, img, 'b')"></div>
                            <div class="tb-resize-handle tb-resize-br" @mousedown.stop.prevent="startResizeElement($event, img, 'br')"></div>
                            <div class="slide-textbox-del" @click.stop="deleteImageById(img.id)" title="Entfernen">&times;</div>
                        </div>
                    </template>
                </div>

                {{-- Textbox Overlay Layer --}}
                <div class="textbox-layer"
                     :class="{ 'placing': placingTextbox }"
                     @click.stop="handleLayerClick($event)">
                    <template x-for="tb in currentTextboxes" :key="tb.id">
                        <div class="slide-textbox"
                             :class="{ 'tb-selected': selectedElement?.id === tb.id, 'tb-editing': editingTextbox === tb.id }"
                             :style="`left:${tb.x}px; top:${tb.y}px; width:${tb.width}px; ${tb.height ? 'height:'+tb.height+'px;' : ''} font-size:${tb.fontSize}px; color:${tb.color}; font-weight:${tb.fontWeight || 400}; text-align:${tb.align || 'left'}; text-decoration:${tb.textDecoration || 'none'};`"
                             @mousedown.stop="startDragTextbox($event, tb)"
                             @click.stop="selectTextbox(tb)"
                             @dblclick.stop="startEditTextbox(tb)">
                            <div class="slide-textbox-content"
                                 @blur="onTextboxBlur($event, tb)"
                                 @input="onTextboxInput($event, tb)"
                                 :contenteditable="editingTextbox === tb.id ? 'true' : 'false'"
                                 x-effect="if (editingTextbox !== tb.id) $el.innerHTML = tb.text"
                                 :style="editingTextbox === tb.id
                                     ? 'min-height:1em;outline:none;width:100%;height:100%;cursor:text;user-select:text;'
                                     : 'min-height:1em;outline:none;width:100%;height:100%;cursor:move;user-select:none;'"></div>
                            <div class="tb-resize-handle tb-resize-r" @mousedown.stop.prevent="startResize($event, tb, 'r')"></div>
                            <div class="tb-resize-handle tb-resize-b" @mousedown.stop.prevent="startResize($event, tb, 'b')"></div>
                            <div class="tb-resize-handle tb-resize-br" @mousedown.stop.prevent="startResize($event, tb, 'br')"></div>
                            <div class="slide-textbox-del" @click.stop="hideOrDeleteTextbox(tb)" title="Entfernen">&times;</div>
                            <div class="tb-link-indicator" x-show="tb.link" x-text="tb.link ? '🔗 ' + tb.link : ''"></div>
                        </div>
                    </template>
                </div>

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
</div>

{{-- PDF Overlay --}}
<div id="pdf-overlay" x-data x-show="$store.exportState.exporting" x-transition.opacity class="pdf-progress-overlay" style="display: none;">
    <div style="font-size: 18px; font-weight: 600;" x-text="$store.exportState.statusText"></div>
    <div style="font-size: 13px; color: #9CA3AF; margin-top: 6px;" x-text="$store.exportState.subText"></div>
    <div class="pdf-progress-bar"><div class="pdf-progress-fill" :style="'width: ' + $store.exportState.progress + '%'"></div></div>
</div>

@php
    $slidesMeta = app(\Trafficdesign\Presentation\PresentationEngine::class)
        ->prepareSlidesForView($slides, $config ?? []);
@endphp
<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('exportState', {
        exporting: false, progress: 0, statusText: '', subText: '',
        _startTime: null, _timer: null,
        start() {
            this._startTime = Date.now();
            this._timer = setInterval(() => {
                const elapsed = Math.floor((Date.now() - this._startTime) / 1000);
                if (this.exporting && this.progress < 90) {
                    this.subText = 'Slides werden gerendert… (' + elapsed + 's)';
                }
            }, 1000);
        },
        stop() {
            if (this._timer) { clearInterval(this._timer); this._timer = null; }
            this._startTime = null;
        },
    });
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

        isDirty: false,
        _saving: false,
        addingSlide: false,
        newSlideTitle: '',
        placingTextbox: false,
        selectedElement: null,
        editingTextbox: null,
        currentFontSize: 16,
        currentColor: '#ffffff',
        currentBold: false,
        currentAlign: 'left',
        currentLink: '',
        linkPopupOpen: false,
        linkInputValue: '',
        colorPresets: ['#FFFFFF','#000000','#6B7280','#00AFCE','#4488FF','#4CAF50','#FFA726','#FF7043','#E53935','#BB86FC'],
        _dragging: null,
        _focusedEditable: null,
        _uploadingImage: false,
        currentSlideId: '',

        _undoStack: [],
        _redoStack: [],
        _maxHistory: 30,
        _snapshotTimer: null,

        get currentTextboxes() {
            return (this.slidesData[this.currentSlide]?.textboxes || []).filter(tb => !tb.hidden);
        },

        get isGeneratedSlide() {
            const generatedTypes = ['perspective','perspective-focus','perspective-quotes','perspective-cover',
                'chart-bar','divergence','summary','participants','reflection','action-plans',
                'self-gap','year-over-year','title','agenda','rating-scale'];
            return generatedTypes.includes(this.slidesData[this.currentSlide]?.type);
        },

        get currentImages() {
            return this.slidesData[this.currentSlide]?.images || [];
        },

        get currentSlideTheme() {
            return this.slidesData[this.currentSlide]?.theme || 'dark';
        },

        getSlideEl(idx) {
            const id = this.slidesData[idx]?.id;
            return id ? document.querySelector('.edit-main .slide[data-slide-id="' + id + '"]') : null;
        },

        init() {
            const hashSlide = window.location.hash.match(/^#slide=(\d+)$/);
            const querySlide = new URLSearchParams(window.location.search).get('slide');
            const raw = hashSlide ? hashSlide[1] : querySlide;
            const startIdx = raw ? Math.max(0, Math.min(parseInt(raw), this.totalSlides - 1)) : 0;
            this.currentSlide = startIdx;
            this.currentSlideId = this.slidesData[startIdx]?.id || '';
            this.$watch('currentSlide', () => {
                this.currentSlideId = this.slidesData[this.currentSlide]?.id || '';
            });
            history.replaceState(null, '', window.location.pathname + '#slide=' + startIdx);

            this._undoStack.push(JSON.stringify(this.slidesData));

            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') {
                    this.renderChartsForSlide(this.currentSlide);
                }
                this.initSortable();
                this.calcSlideScale();
                this.applyFontOverrides(this.currentSlide);
                window.addEventListener('resize', () => this.calcSlideScale());
            });

            window.addEventListener('beforeunload', (e) => {
                if (this.isDirty) { e.preventDefault(); e.returnValue = ''; }
            });

            window.addEventListener('mousemove', (e) => { this.onDragTextbox(e); this.onResizeTextbox(e); this.onDragElement(e); this.onResizeElement(e); });
            window.addEventListener('mouseup', () => { this.endDragTextbox(); this.endResizeTextbox(); this.endDragElement(); this.endResizeElement(); });

            document.addEventListener('paste', (e) => {
                if (e.target.closest('.slide-textbox-content') || e.target.isContentEditable) {
                    e.preventDefault();
                    const text = (e.clipboardData || window.clipboardData).getData('text/plain');
                    document.execCommand('insertText', false, text);
                }
            });

            document.addEventListener('focusin', (e) => {
                if (e.target.closest('.slide-textbox')) return;
                if (e.target.isContentEditable) {
                    this._focusedEditable = e.target;
                    const cs = window.getComputedStyle(e.target);
                    const size = Math.round(parseFloat(cs.fontSize));
                    this.currentFontSize = size;
                    this.currentColor = cs.color;
                    this.currentBold = parseInt(cs.fontWeight) >= 700;
                    this.selectedElement = { type: 'contenteditable', id: 'ce-' + Date.now(), el: e.target };
                }
            });
            document.addEventListener('focusout', (e) => {
                if (e.target === this._focusedEditable) {
                    setTimeout(() => {
                        if (!this._focusedEditable || document.activeElement !== this._focusedEditable) {
                            if (!this.selectedElement || this.selectedElement.type === 'contenteditable') {
                                this.selectedElement = null;
                                this._focusedEditable = null;
                            }
                        }
                    }, 150);
                }
            });
        },

        markDirty() {
            this.isDirty = true;
            this._pushSnapshot();
        },

        // ── Undo / Redo ──
        _pushSnapshot() {
            clearTimeout(this._snapshotTimer);
            this._snapshotTimer = setTimeout(() => {
                const snap = JSON.stringify(this.slidesData);
                if (this._undoStack.at(-1) === snap) return;
                this._undoStack.push(snap);
                if (this._undoStack.length > this._maxHistory) this._undoStack.shift();
                this._redoStack = [];
            }, 400);
        },

        undo() {
            clearTimeout(this._snapshotTimer);
            this._snapshotTimer = null;
            if (this._undoStack.length === 0) return;

            const currentSnap = JSON.stringify(this.slidesData);
            this._redoStack.push(currentSnap);

            let prevSnap = this._undoStack.pop();
            if (prevSnap === currentSnap && this._undoStack.length > 0) {
                prevSnap = this._undoStack.pop();
            }

            this.slidesData = JSON.parse(prevSnap);
            this.isDirty = true;
            this._refreshAfterUndoRedo();
        },

        redo() {
            clearTimeout(this._snapshotTimer);
            this._snapshotTimer = null;
            if (this._redoStack.length === 0) return;

            const currentSnap = JSON.stringify(this.slidesData);
            this._undoStack.push(currentSnap);

            this.slidesData = JSON.parse(this._redoStack.pop());
            this.isDirty = true;
            this._refreshAfterUndoRedo();
        },

        _refreshAfterUndoRedo() {
            this.selectedElement = null;
            this._focusedEditable = null;
            this.totalSlides = this.slidesData.length;
            if (this.currentSlide >= this.totalSlides) this.currentSlide = Math.max(0, this.totalSlides - 1);
            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') this.renderChartsForSlide(this.currentSlide);
                this.applyFontOverrides(this.currentSlide);
                this.applySlideTheme(this.currentSlide);
            });
        },

        // ── Slide Scale ──
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

        slideTypeLabel(type) {
            const labels = {
                title: 'Titelseite', summary: 'Executive Summary', participants: 'Teilnehmer',
                'chart-bar': 'Auswertung', perspective: 'Perspektive', 'perspective-cover': 'Perspektive',
                'perspective-focus': 'Perspektive Detail', 'perspective-quotes': 'Kernaussagen',
                'self-gap': 'Selbst-/Fremdbild', divergence: 'Divergenz', text: 'Text',
                agenda: 'Agenda', 'rating-scale': 'Bewertungsskala', 'year-over-year': 'Jahresvergleich',
                'action-plans': 'Maßnahmenplanung', reflection: 'Reflexion',
            };
            return labels[type] || type;
        },

        slideLabel(slide) {
            const name = slide.title || '';
            const id = slide.id || '';
            if (slide.type === 'perspective-cover' && name) return name + ': Kapitelstart';
            if (slide.type === 'perspective-focus' && name) {
                if (id.endsWith('-strengths')) return name + ': Top 5 Stärken';
                if (id.endsWith('-development')) return name + ': Entwicklungsfelder';
            }
            if (slide.type === 'perspective-quotes' && name) return name + ': Zitate';
            if (slide.type === 'perspective' && name) return name;
            return name || this.slideTypeLabel(slide.type);
        },

        // ── SortableJS (with Alpine fix) ──
        initSortable() {
            const el = this.$refs.sidebarSlides;
            if (!el || typeof Sortable === 'undefined') return;
            const self = this;
            this.sortableInstance = new Sortable(el, {
                animation: 200,
                draggable: '.sidebar-slide',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd(evt) {
                    const { oldIndex, newIndex } = evt;
                    if (oldIndex === newIndex) return;

                    // Revert DOM: use querySelectorAll to skip the <template>
                    // element that el.children[0] includes (off-by-one bug).
                    evt.item.remove();
                    const slideNodes = el.querySelectorAll('.sidebar-slide');
                    if (slideNodes[oldIndex]) {
                        slideNodes[oldIndex].before(evt.item);
                    } else {
                        el.appendChild(evt.item);
                    }

                    // Track active slide by ID so it survives reorder
                    const activeId = self.slidesData[self.currentSlide]?.id;

                    const moved = self.slidesData.splice(oldIndex, 1)[0];
                    self.slidesData.splice(newIndex, 0, moved);

                    if (activeId) {
                        const idx = self.slidesData.findIndex(s => s.id === activeId);
                        if (idx !== -1) self.currentSlide = idx;
                    }

                    self.markDirty();
                },
            });
        },

        // ── Keyboard ──
        handleKeydown(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                this.saveAll();
                return;
            }
            if (e.key === 'Escape') {
                if (this.linkPopupOpen) { this.linkPopupOpen = false; return; }
                if (this.editingTextbox) {
                    // Edit-Modus verlassen, Textbox bleibt selektiert
                    const focused = document.activeElement;
                    if (focused) focused.blur();
                    this.editingTextbox = null;
                    return;
                }
                this.placingTextbox = false;
                this.deselectAll();
                return;
            }
            if (e.target.isContentEditable || e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
                e.preventDefault();
                if (e.shiftKey) { this.redo(); } else { this.undo(); }
                return;
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'y') {
                e.preventDefault();
                this.redo();
                return;
            }
            if ((e.key === 'Delete' || e.key === 'Backspace') && this.selectedElement?.type === 'textbox' && !this.editingTextbox) {
                e.preventDefault();
                this.deleteSelectedTextbox();
                return;
            }
            if ((e.key === 'Delete' || e.key === 'Backspace') && this.selectedElement?.type === 'image') {
                e.preventDefault();
                this.deleteImageById(this.selectedElement.id);
                return;
            }
            switch (e.key) {
                case 'ArrowRight': e.preventDefault(); this.nextSlide(); break;
                case 'ArrowLeft': e.preventDefault(); this.prevSlide(); break;
            }
        },

        // ── Navigation ──
        nextSlide() { if (this.currentSlide < this.totalSlides - 1) this.goToSlide(this.currentSlide + 1); },
        prevSlide() { if (this.currentSlide > 0) this.goToSlide(this.currentSlide - 1); },

        goToSlide(idx) {
            if (idx < 0 || idx >= this.totalSlides || idx === this.currentSlide) return;
            this.deselectAll();
            this.destroyChartsForSlide(this.currentSlide);
            this.currentSlide = idx;
            history.replaceState(null, '', window.location.pathname + '#slide=' + idx);
            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') this.renderChartsForSlide(idx);
                this.applyFontOverrides(idx);
                this.applySlideTheme(idx);
            });
        },

        /**
         * Bereitet einen Roh-Slide (z.B. vom Server nach addTextSlide) fuer slidesData auf.
         * Die eigentliche Merge-Logik liegt in PresentationEngine::prepareSlidesForView().
         * Fuer bereits aufbereitete Slides (aus dem Init) ist diese Funktion nicht noetig.
         */
        buildSlideMeta(s) {
            return {
                id: s.id, type: s.type, title: s.title || '',
                theme: s.theme || 'dark', source: s.source || 'generated',
                textboxes: s.textboxes || [],
                images: s.images || [],
                fontOverrides: s.fontOverrides || {},
            };
        },

        // ── Textbox: Place Mode ──
        togglePlaceTextbox() {
            this.placingTextbox = !this.placingTextbox;
            if (this.placingTextbox) this.deselectAll();
        },

        handleLayerClick(e) {
            if (!this.placingTextbox) return;
            const layer = e.currentTarget;
            const rect = layer.getBoundingClientRect();
            const slideW = {{ $config['slide_width'] ?? 1280 }};
            const slideH = {{ $config['slide_height'] ?? 720 }};
            const x = Math.round(((e.clientX - rect.left) / rect.width) * slideW);
            const y = Math.round(((e.clientY - rect.top) / rect.height) * slideH);

            if (!this.slidesData[this.currentSlide].textboxes) {
                this.slidesData[this.currentSlide].textboxes = [];
            }
            const isDark = (this.slidesData[this.currentSlide].theme || 'dark') === 'dark';
            const tb = {
                id: 'tb-' + Date.now() + '-' + Math.random().toString(36).substr(2, 4),
                source: 'user',
                text: 'Text eingeben…',
                x: Math.max(0, Math.min(x - 150, slideW - 300)),
                y: Math.max(0, Math.min(y - 20, slideH - 60)),
                width: 300,
                height: null,
                fontSize: 18,
                color: isDark ? '#E5E7EB' : '#1a1a2e',
            };
            this.slidesData[this.currentSlide].textboxes.push(tb);
            this.placingTextbox = false;
            this.markDirty();
            this.$nextTick(() => this.startEditTextbox(tb));
        },

        getSelectedTextbox() {
            if (this.selectedElement?.type !== 'textbox') {
                return null;
            }

            return this.slidesData[this.currentSlide]?.textboxes?.find(
                t => t.id === this.selectedElement.id
            ) ?? null;
        },

        // ── Textbox: Select / Edit / Delete ──
        selectTextbox(tb) {
            if (this.selectedElement?.id === tb.id) return;
            // Vorherigen Edit-Modus beenden
            if (this.editingTextbox) {
                this.editingTextbox = null;
            }
            this.linkPopupOpen = false;
            this.selectedElement = { type: 'textbox', id: tb.id };
            this.currentFontSize = tb.fontSize;
            this.currentColor = tb.color || '#ffffff';
            this.currentBold = (tb.fontWeight || 400) >= 700;
            this.currentAlign = tb.align || 'left';
            this.currentLink = tb.link || '';
        },

        startEditTextbox(tb) {
            if (this.selectedElement?.id !== tb.id) {
                this.selectTextbox(tb);
            }
            this.editingTextbox = tb.id;
            this.$nextTick(() => {
                const el = document.querySelector('.slide-textbox.tb-editing .slide-textbox-content');
                if (el) {
                    el.focus();
                    // Cursor ans Ende setzen
                    const range = document.createRange();
                    const sel = window.getSelection();
                    range.selectNodeContents(el);
                    range.collapse(false);
                    sel?.removeAllRanges();
                    sel?.addRange(range);
                }
            });
        },

        onTextboxBlur(e, tb) {
            const newText = e.target.innerHTML;
            if (newText !== tb.text) {
                tb.text = newText;
                this.markDirty();
            }
            // Edit-Modus beenden, aber nur wenn Fokus wirklich weg von dieser Textbox
            this.$nextTick(() => {
                if (this.editingTextbox === tb.id) {
                    const active = document.activeElement;
                    const stillInBox = active?.closest('.slide-textbox');
                    if (!stillInBox) {
                        this.editingTextbox = null;
                    }
                }
            });
        },

        onTextboxInput(e, tb) {
            const newText = e.target.innerHTML;
            if (newText !== tb.text) {
                tb.text = newText;
                this.markDirty();
            }
        },

        hideOrDeleteTextbox(tb) {
            if (tb.source === 'system') {
                tb.hidden = true;
                this.selectedElement = null;
                this.markDirty();
            } else {
                this.deleteTextboxById(tb.id);
            }
        },

        deleteSelectedTextbox() {
            if (!this.selectedElement || this.selectedElement.type !== 'textbox') return;
            const tbs = this.slidesData[this.currentSlide]?.textboxes;
            if (!tbs) return;
            const tb = tbs.find(t => t.id === this.selectedElement.id);
            if (!tb) return;
            this.hideOrDeleteTextbox(tb);
        },

        deselectAll(e) {
            if (e && (e.target.closest('.slide-textbox') || e.target.closest('.slide-image') || e.target.closest('.edit-toolbar') || e.target.closest('.font-size-control') || e.target.closest('.link-popup'))) return;
            this.selectedElement = null;
            this.editingTextbox = null;
            this.linkPopupOpen = false;
            this._focusedEditable = null;
            const focused = document.activeElement;
            if (focused && focused !== document.body) focused.blur();
        },

        // ── Textbox: Drag ──
        startDragTextbox(e, tb) {
            if (e.target.closest('.tb-resize-handle') || e.target.closest('.slide-textbox-del')) return;
            // Im Edit-Modus kein Drag – Text-Selektion im contenteditable erlauben
            if (this.editingTextbox === tb.id) return;
            if (this.selectedElement?.id !== tb.id) {
                this.selectTextbox(tb);
            }
            this._dragging = {
                tb, startX: e.clientX, startY: e.clientY, origX: tb.x, origY: tb.y, moved: false
            };
        },

        onDragTextbox(e) {
            if (!this._dragging) return;
            const scale = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--slide-scale')) || 0.7;
            const dx = (e.clientX - this._dragging.startX) / scale;
            const dy = (e.clientY - this._dragging.startY) / scale;
            if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                if (!this._dragging.moved) {
                    window.getSelection()?.removeAllRanges();
                    const focused = document.activeElement;
                    if (focused?.closest('.slide-textbox')) focused.blur();
                }
                this._dragging.moved = true;
            }
            if (!this._dragging.moved) return;
            const slideW = {{ $config['slide_width'] ?? 1280 }};
            const slideH = {{ $config['slide_height'] ?? 720 }};
            this._dragging.tb.x = Math.max(0, Math.min(this._dragging.origX + dx, slideW - 40));
            this._dragging.tb.y = Math.max(0, Math.min(this._dragging.origY + dy, slideH - 20));
        },

        endDragTextbox() {
            if (this._dragging?.moved) this.markDirty();
            this._dragging = null;
        },

        // ── Textbox: Resize ──
        _resizing: null,

        startResize(e, tb, direction) {
            this.selectTextbox(tb);
            const scale = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--slide-scale')) || 0.7;
            this._resizing = {
                tb, direction, scale,
                startX: e.clientX, startY: e.clientY,
                origW: tb.width, origH: tb.height || 0,
            };
            if (!tb.height) {
                const el = document.querySelector('.slide-textbox.tb-selected');
                if (el) this._resizing.origH = el.offsetHeight;
            }
        },

        onResizeTextbox(e) {
            if (!this._resizing) return;
            const { tb, direction, scale, startX, startY, origW, origH } = this._resizing;
            const dx = (e.clientX - startX) / scale;
            const dy = (e.clientY - startY) / scale;
            if (direction === 'r' || direction === 'br') {
                tb.width = Math.max(60, Math.round(origW + dx));
            }
            if (direction === 'b' || direction === 'br') {
                tb.height = Math.max(28, Math.round(origH + dy));
            }
        },

        endResizeTextbox() {
            if (this._resizing) this.markDirty();
            this._resizing = null;
        },

        deleteTextboxById(id) {
            const tbs = this.slidesData[this.currentSlide]?.textboxes;
            if (!tbs) return;
            const tb = tbs.find(t => t.id === id);
            if (tb?.source === 'system') return;
            const idx = tbs.indexOf(tb);
            if (idx !== -1) {
                tbs.splice(idx, 1);
                if (this.selectedElement?.id === id) this.selectedElement = null;
                this.markDirty();
            }
        },

        // ── Image: Upload ──
        openImageUpload() {
            this.$refs.imageInput.value = '';
            this.$refs.imageInput.click();
        },

        async handleImageUpload(e) {
            const file = e.target.files?.[0];
            if (!file) return;

            const maxKb = {{ config('presentation.images.max_size', 2048) }};
            if (file.size > maxKb * 1024) {
                alert('Bild ist zu gross (max. ' + (maxKb / 1024).toFixed(0) + ' MB)');
                return;
            }

            this._uploadingImage = true;
            try {
                const formData = new FormData();
                formData.append('image', file);

                const res = await fetch('{{ route("presentation.images.upload", $presentation->id) }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });

                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || 'Upload fehlgeschlagen');
                    return;
                }

                const data = await res.json();
                const slideW = {{ $config['slide_width'] ?? 1280 }};
                const slideH = {{ $config['slide_height'] ?? 720 }};

                const img = new Image();
                img.onload = () => {
                    const ar = img.naturalWidth / img.naturalHeight;
                    let w = Math.min(400, slideW * 0.4);
                    let h = w / ar;
                    if (h > slideH * 0.6) { h = slideH * 0.6; w = h * ar; }

                    const imgEl = {
                        id: data.id,
                        source: 'user',
                        url: data.url,
                        filename: data.filename,
                        disk_path: data.disk_path,
                        x: Math.round((slideW - w) / 2),
                        y: Math.round((slideH - h) / 2),
                        width: Math.round(w),
                        height: Math.round(h),
                        aspectRatio: ar,
                    };

                    if (!this.slidesData[this.currentSlide].images) {
                        this.slidesData[this.currentSlide].images = [];
                    }
                    this.slidesData[this.currentSlide].images.push(imgEl);
                    this.markDirty();
                    this.$nextTick(() => this.selectImage(imgEl));
                };
                img.onerror = () => {
                    const imgEl = {
                        id: data.id, source: 'user', url: data.url,
                        filename: data.filename, disk_path: data.disk_path,
                        x: Math.round(slideW * 0.3), y: Math.round(slideH * 0.2),
                        width: 400, height: 300, aspectRatio: 4/3,
                    };
                    if (!this.slidesData[this.currentSlide].images) {
                        this.slidesData[this.currentSlide].images = [];
                    }
                    this.slidesData[this.currentSlide].images.push(imgEl);
                    this.markDirty();
                };
                img.src = data.url;
            } catch (err) {
                console.error('Image upload failed:', err);
                alert('Upload fehlgeschlagen');
            } finally {
                this._uploadingImage = false;
            }
        },

        // ── Image: Select / Delete ──
        selectImage(img) {
            this.selectedElement = { type: 'image', id: img.id };
        },

        deleteImageById(id) {
            const imgs = this.slidesData[this.currentSlide]?.images;
            if (!imgs) return;
            const idx = imgs.findIndex(i => i.id === id);
            if (idx !== -1) {
                const img = imgs[idx];
                if (img.disk_path) {
                    fetch('{{ url(config("presentation.route_prefix", "presentations")) }}/' + this.presentationId + '/images/' + id, {
                        method: 'DELETE', credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }).catch(() => {});
                }
                imgs.splice(idx, 1);
                if (this.selectedElement?.id === id) this.selectedElement = null;
                this.markDirty();
            }
        },

        // ── Image / Generic Element: Drag ──
        _elementDragging: null,

        startDragElement(e, el) {
            if (e.target.closest('.tb-resize-handle') || e.target.closest('.slide-textbox-del')) return;
            if (this.selectedElement?.id !== el.id) this.selectImage(el);
            this._elementDragging = {
                el, startX: e.clientX, startY: e.clientY, origX: el.x, origY: el.y, moved: false
            };
        },

        onDragElement(e) {
            if (!this._elementDragging) return;
            const scale = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--slide-scale')) || 0.7;
            const dx = (e.clientX - this._elementDragging.startX) / scale;
            const dy = (e.clientY - this._elementDragging.startY) / scale;
            if (Math.abs(dx) > 3 || Math.abs(dy) > 3) this._elementDragging.moved = true;
            if (!this._elementDragging.moved) return;
            const slideW = {{ $config['slide_width'] ?? 1280 }};
            const slideH = {{ $config['slide_height'] ?? 720 }};
            this._elementDragging.el.x = Math.max(0, Math.min(this._elementDragging.origX + dx, slideW - 40));
            this._elementDragging.el.y = Math.max(0, Math.min(this._elementDragging.origY + dy, slideH - 20));
        },

        endDragElement() {
            if (this._elementDragging?.moved) this.markDirty();
            this._elementDragging = null;
        },

        // ── Image / Generic Element: Resize ──
        _elementResizing: null,

        startResizeElement(e, el, direction) {
            this.selectImage(el);
            const scale = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--slide-scale')) || 0.7;
            this._elementResizing = {
                el, direction, scale,
                startX: e.clientX, startY: e.clientY,
                origW: el.width, origH: el.height,
                aspectRatio: el.aspectRatio || (el.width / el.height),
            };
        },

        onResizeElement(e) {
            if (!this._elementResizing) return;
            const { el, direction, scale, startX, startY, origW, origH, aspectRatio } = this._elementResizing;
            const dx = (e.clientX - startX) / scale;
            const dy = (e.clientY - startY) / scale;
            if (direction === 'br') {
                const newW = Math.max(40, Math.round(origW + dx));
                el.width = newW;
                el.height = Math.max(30, Math.round(newW / aspectRatio));
            } else if (direction === 'r') {
                el.width = Math.max(40, Math.round(origW + dx));
            } else if (direction === 'b') {
                el.height = Math.max(30, Math.round(origH + dy));
            }
        },

        endResizeElement() {
            if (this._elementResizing) this.markDirty();
            this._elementResizing = null;
        },

        // ── Font Size ──
        changeFontSize(delta) {
            const newSize = Math.max(8, Math.min(120, this.currentFontSize + delta));
            this.setFontSize(newSize);
        },

        setFontSize(size) {
            if (isNaN(size) || size < 8 || size > 120) return;
            this.currentFontSize = size;

            if (this.selectedElement?.type === 'textbox') {
                const tb = this.getSelectedTextbox();
                if (tb) { tb.fontSize = size; this.markDirty(); }
            } else if (this.selectedElement?.type === 'contenteditable' && this._focusedEditable) {
                this._focusedEditable.style.fontSize = size + 'px';
                this.saveFontOverride(this._focusedEditable, size);
                this.markDirty();
            }
        },

        setTextColor(color) {
            this.currentColor = color;
            if (this.selectedElement?.type === 'textbox') {
                const tb = this.getSelectedTextbox();
                if (tb) { tb.color = color; this.markDirty(); }
            } else if (this.selectedElement?.type === 'contenteditable' && this._focusedEditable) {
                this._focusedEditable.style.color = color;
                this.markDirty();
            }
        },

        setAlign(align) {
            const tb = this.getSelectedTextbox();
            if (! tb) {
                return;
            }
            this.currentAlign = align;
            tb.align = align;
            this.markDirty();
        },

        toggleBold() {
            if (this.editingTextbox && this.selectedElement?.type === 'textbox') {
                document.execCommand('bold', false, null);
                const tb = this.getSelectedTextbox();
                const el = document.querySelector('.slide-textbox.tb-editing .slide-textbox-content');
                if (tb && el) {
                    tb.text = el.innerHTML;
                    this.markDirty();
                }
                return;
            }

            this.currentBold = !this.currentBold;
            const weight = this.currentBold ? 700 : 400;

            if (this.selectedElement?.type === 'textbox') {
                const tb = this.getSelectedTextbox();
                if (tb) { tb.fontWeight = weight; this.markDirty(); }
            } else if (this.selectedElement?.type === 'contenteditable' && this._focusedEditable) {
                this._focusedEditable.style.fontWeight = weight;
                this.markDirty();
            }
        },

        // ── Link ──
        openLinkPopup() {
            if (this.selectedElement?.type !== 'textbox') return;
            this.linkInputValue = this.currentLink || '';
            this.linkPopupOpen = !this.linkPopupOpen;
            if (this.linkPopupOpen) {
                this.$nextTick(() => this.$refs.linkInput?.focus());
            }
        },

        applyLink() {
            const url = (this.linkInputValue || '').trim();
            if (!url) { this.removeLink(); return; }

            if (this.selectedElement?.type === 'textbox') {
                const tb = this.getSelectedTextbox();
                if (tb) {
                    const isNewLink = !tb.link;
                    tb.link = url;
                    this.currentLink = url;
                    if (isNewLink) {
                        tb.color = '{{ $accent }}';
                        tb.textDecoration = 'underline';
                        this.currentColor = '{{ $accent }}';
                    }
                    this.markDirty();
                }
            }
            this.linkPopupOpen = false;
        },

        removeLink() {
            if (this.selectedElement?.type === 'textbox') {
                const tb = this.getSelectedTextbox();
                if (tb) {
                    delete tb.link;
                    this.currentLink = '';
                    this.markDirty();
                }
            }
            this.linkPopupOpen = false;
            this.linkInputValue = '';
        },

        setSlideTheme(theme) {
            const slide = this.slidesData[this.currentSlide];
            if (!slide || slide.theme === theme) return;
            slide.theme = theme;
            this.applySlideTheme(this.currentSlide);
            this.markDirty();
        },

        applySlideTheme(idx) {
            const theme = this.slidesData[idx]?.theme || 'dark';
            const el = this.getSlideEl(idx);
            if (el) {
                el.classList.remove('slide-light', 'slide-dark');
                el.classList.add('slide-' + theme);
            }
        },

        saveFontOverride(el, size) {
            const slideEl = el.closest('[data-slide-id]');
            if (!slideEl) return;
            const slideId = slideEl.dataset.slideId;
            const dataIdx = this.slidesData.findIndex(s => s.id === slideId);
            if (dataIdx === -1) return;
            const editables = slideEl.querySelectorAll('[contenteditable]');
            let editIdx = -1;
            editables.forEach((ed, i) => { if (ed === el) editIdx = i; });
            if (editIdx === -1) return;
            if (!this.slidesData[dataIdx].fontOverrides) this.slidesData[dataIdx].fontOverrides = {};
            this.slidesData[dataIdx].fontOverrides['ce-' + editIdx] = size;
        },

        applyFontOverrides(idx) {
            const overrides = this.slidesData[idx]?.fontOverrides;
            if (!overrides || !Object.keys(overrides).length) return;
            this.$nextTick(() => {
                const slideEl = this.getSlideEl(idx);
                if (!slideEl) return;
                const editables = slideEl.querySelectorAll('[contenteditable]');
                Object.entries(overrides).forEach(([key, size]) => {
                    const i = parseInt(key.replace('ce-', ''));
                    if (editables[i]) editables[i].style.fontSize = size + 'px';
                });
            });
        },

        // ── Navigation ──
        async exitToPresentation() {
            const target = `{{ route('presentation.show', $presentation->id) }}#slide=${this.currentSlide}`;
            if (!this.isDirty) {
                window.location.href = target;
                return;
            }
            if (confirm('Es gibt ungespeicherte Änderungen. Jetzt speichern und zur Präsentation wechseln?')) {
                await this.saveAll();
                window.location.href = target;
            }
        },

        // ── Save ──
        async saveAll() {
            if (this._saving) return;
            this._saving = true;
            this.saveStatus = 'Wird gespeichert…';
            try {
                const res = await this._fetch('{{ route("presentation.save", $presentation->id) }}', 'POST', {
                    slides: this.slidesData,
                });
                if (res.ok) {
                    this.isDirty = false;
                    this.saveStatus = 'Gespeichert';
                    setTimeout(() => { this.saveStatus = ''; }, 2000);
                } else {
                    this.saveStatus = 'Fehler beim Speichern';
                    setTimeout(() => { this.saveStatus = ''; }, 3000);
                }
            } catch (e) {
                this.saveStatus = 'Fehler beim Speichern';
                setTimeout(() => { this.saveStatus = ''; }, 3000);
            } finally {
                this._saving = false;
            }
        },

        async renamePresentation(newTitle) {
            if (newTitle === this.presentationTitle) return;
            this.presentationTitle = newTitle;
            try {
                const res = await this._fetch('{{ route("presentation.rename", $presentation->id) }}', 'POST', { title: newTitle });
                if (res.ok) {
                    this.saveStatus = 'Gespeichert';
                    setTimeout(() => { this.saveStatus = ''; }, 2000);
                }
            } catch (e) {
                this.saveStatus = 'Fehler';
                setTimeout(() => { this.saveStatus = ''; }, 3000);
            }
        },

        async submitNewSlide() {
            const title = (this.newSlideTitle || '').trim() || 'Text-Slide';
            this.addingSlide = false;
            this.newSlideTitle = '';
            await this.addTextSlide(title);
        },

        async addTextSlide(title) {
            try {
                const targetIdx = this.currentSlide + 1;
                const res = await this._fetch('{{ route("presentation.slides.add", $presentation->id) }}', 'POST', {
                    title: title || 'Text-Slide',
                    theme: 'light',
                    position: targetIdx,
                });
                if (res.ok) {
                    const data = await res.json();
                    const newSlideRaw = data.slides[targetIdx];
                    if (newSlideRaw) {
                        const newSlideMeta = this.buildSlideMeta(newSlideRaw);
                        this.deselectAll();
                        this.destroyChartsForSlide(this.currentSlide);
                        this.slidesData.splice(targetIdx, 0, newSlideMeta);
                        this.totalSlides = this.slidesData.length;
                        this.currentSlide = targetIdx;
                        this.$nextTick(() => {
                            this.applyFontOverrides(targetIdx);
                        });
                    }
                }
            } catch (e) { console.error(e); }
        },

        async removeSlide(slideId, idx) {
            if (!confirm('Diesen Slide wirklich entfernen?')) return;
            try {
                const res = await this._fetch('{{ url(config("presentation.route_prefix", "presentations")) }}/' + this.presentationId + '/slides/' + slideId, 'DELETE');
                if (res.ok) {
                    this.deselectAll();
                    this.slidesData.splice(idx, 1);
                    this.totalSlides = this.slidesData.length;
                    if (this.currentSlide >= this.totalSlides) this.currentSlide = Math.max(0, this.totalSlides - 1);
                    this.$nextTick(() => {
                        if (typeof this.renderChartsForSlide === 'function') this.renderChartsForSlide(this.currentSlide);
                        this.applyFontOverrides(this.currentSlide);
                    });
                }
            } catch (e) { console.error(e); }
        },

        async regeneratePresentation() {
            if (!confirm('Slides mit aktuellen Daten neu generieren? Ihre Anpassungen bleiben erhalten.')) return;
            try {
                const res = await this._fetch('{{ route("presentation.regenerate", $presentation->id) }}', 'POST');
                const data = await res.json();
                if (data.redirect) window.location.href = data.redirect;
            } catch (e) { console.error(e); }
        },

        saveOverride(event) {},

        destroyChartsForSlide(idx) {
            const sid = this.slidesData[idx]?.id || idx;
            const keys = Object.keys(this.chartInstances).filter(k => k.startsWith('slide-' + sid + '-'));
            keys.forEach(k => { try { this.chartInstances[k].destroy(); } catch(e) {} delete this.chartInstances[k]; });
        },

        renderChartsForSlide(idx) {},

        async exportPdf() {
            const exportState = Alpine.store('exportState');
            if (exportState.exporting) return;

            exportState.exporting = true;
            exportState.progress = 10;
            exportState.statusText = 'Export wird gestartet…';
            exportState.subText = '';
            exportState.start();

            try {
                const startResp = await this._fetch('{{ route("presentation.export-pdf", $presentation->id) }}', 'POST');

                if (!startResp.ok) throw new Error('Export konnte nicht gestartet werden');

                const { export_key } = await startResp.json();
                exportState.progress = 15;
                exportState.statusText = 'PDF wird generiert…';

                const statusUrl = '{{ route("presentation.export-pdf.status", $presentation->id) }}?key=' + encodeURIComponent(export_key);
                let status = 'queued';

                while (status === 'queued' || status === 'processing') {
                    await this._wait(2000);
                    exportState.progress = Math.min(88, exportState.progress + 3);

                    const pollResp = await fetch(statusUrl, {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    });

                    if (!pollResp.ok) throw new Error('Status-Abfrage fehlgeschlagen');

                    const data = await pollResp.json();
                    status = data.status;
                }

                if (status === 'failed') throw new Error('PDF-Generierung fehlgeschlagen');

                exportState.stop();
                exportState.progress = 92;
                exportState.statusText = 'PDF wird heruntergeladen…';
                exportState.subText = '';

                const downloadUrl = '{{ route("presentation.export-pdf.download", $presentation->id) }}?key=' + encodeURIComponent(export_key);
                const dlResp = await fetch(downloadUrl, { credentials: 'same-origin' });

                if (!dlResp.ok) throw new Error('Download fehlgeschlagen');

                const blob = await dlResp.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = (this.presentationTitle || 'Praesentation').replace(/[^a-zA-Z0-9äöüÄÖÜß\s\-_]/g, '').replace(/\s+/g, '_') + '.pdf';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                exportState.progress = 100;
                exportState.statusText = 'Fertig!';
                exportState.subText = 'Download gestartet';
                await this._wait(1200);
            } catch (e) {
                console.error('PDF-Export fehlgeschlagen:', e);
                exportState.stop();
                exportState.statusText = 'Fehler beim Erstellen des PDFs';
                exportState.subText = '';
                exportState.progress = 0;
                await this._wait(2500);
            } finally {
                exportState.stop();
                exportState.exporting = false;
                exportState.progress = 0;
            }
        },

        async exportPptx() {
            const exportState = Alpine.store('exportState');
            if (exportState.exporting) return;

            exportState.exporting = true;
            exportState.progress = 10;
            exportState.statusText = 'PowerPoint wird erstellt…';
            exportState.subText = '';
            exportState.start();

            try {
                const startResp = await this._fetch('{{ route("presentation.export-pptx", $presentation->id) }}', 'POST');

                if (!startResp.ok) throw new Error('Export konnte nicht gestartet werden');

                const { export_key } = await startResp.json();
                exportState.progress = 15;
                exportState.statusText = 'Slides werden konvertiert…';

                const statusUrl = '{{ route("presentation.export-pptx.status", $presentation->id) }}?key=' + encodeURIComponent(export_key);
                let status = 'queued';

                while (status === 'queued' || status === 'processing') {
                    await this._wait(2000);
                    exportState.progress = Math.min(88, exportState.progress + 3);

                    const pollResp = await fetch(statusUrl, {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    });

                    if (!pollResp.ok) throw new Error('Status-Abfrage fehlgeschlagen');

                    const data = await pollResp.json();
                    status = data.status;
                }

                if (status === 'failed') throw new Error('PowerPoint-Generierung fehlgeschlagen');

                exportState.stop();
                exportState.progress = 92;
                exportState.statusText = 'PowerPoint wird heruntergeladen…';
                exportState.subText = '';

                const downloadUrl = '{{ route("presentation.export-pptx.download", $presentation->id) }}?key=' + encodeURIComponent(export_key);
                const dlResp = await fetch(downloadUrl, { credentials: 'same-origin' });

                if (!dlResp.ok) throw new Error('Download fehlgeschlagen');

                const blob = await dlResp.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = (this.presentationTitle || 'Praesentation').replace(/[^a-zA-Z0-9äöüÄÖÜß\s\-_]/g, '').replace(/\s+/g, '_') + '.pptx';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                exportState.progress = 100;
                exportState.statusText = 'Fertig!';
                exportState.subText = 'Download gestartet';
                await this._wait(1200);
            } catch (e) {
                console.error('PPTX-Export fehlgeschlagen:', e);
                exportState.stop();
                exportState.statusText = 'Fehler beim Erstellen der PowerPoint';
                exportState.subText = '';
                exportState.progress = 0;
                await this._wait(2500);
            } finally {
                exportState.stop();
                exportState.exporting = false;
                exportState.progress = 0;
            }
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
