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
            width: 260px; background: #111; border-right: 1px solid #2A2A2A;
            display: flex; flex-direction: column; flex-shrink: 0; overflow: hidden;
        }
        .sidebar-slides {
            flex: 1; overflow-y: auto; padding: 8px; display: flex;
            flex-direction: column; gap: 8px;
        }
        .sidebar-slides::-webkit-scrollbar { width: 4px; }
        .sidebar-slides::-webkit-scrollbar-thumb { background: #374151; border-radius: 2px; }

        .sidebar-slide {
            position: relative; border-radius: 4px; cursor: pointer;
            border: 2px solid transparent; transition: all 0.15s;
            overflow: hidden; flex-shrink: 0;
            aspect-ratio: 16 / 9;
        }
        .sidebar-slide:hover { border-color: #555; }
        .sidebar-slide.active { border-color: {{ $accent }}; box-shadow: 0 0 0 1px {{ $accent }}40; }

        .sidebar-thumb {
            width: 100%; height: 100%; position: relative;
            overflow: hidden;
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
            justify-content: center; gap: 4px; padding: 8px;
            width: 100%; height: 100%; position: relative;
        }
        .sidebar-thumb-icon {
            font-size: 28px; opacity: 0.2; line-height: 1;
        }
        .sidebar-thumb-label {
            font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px;
            opacity: 0.25; font-weight: 600;
        }

        .sidebar-overlay {
            position: absolute; bottom: 0; left: 0; right: 0;
            padding: 4px 8px; display: flex; align-items: center; gap: 5px;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            z-index: 2;
        }
        .sidebar-slide-number {
            font-size: 10px; color: rgba(255,255,255,0.6); font-weight: 700;
            flex-shrink: 0;
        }
        .sidebar-slide-title {
            font-size: 9px; color: rgba(255,255,255,0.7);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            flex: 1;
        }
        .sidebar-slide-remove {
            position: absolute; top: 3px; right: 4px; background: rgba(0,0,0,0.6); border: none;
            color: #ccc; cursor: pointer; font-size: 12px; padding: 1px 5px;
            opacity: 0; transition: opacity 0.15s; border-radius: 3px; z-index: 5;
        }
        .sidebar-slide:hover .sidebar-slide-remove { opacity: 1; }
        .sidebar-slide-remove:hover { color: #EF4444; background: rgba(0,0,0,0.8); }

        .sidebar-footer {
            padding: 8px; border-top: 1px solid #2A2A2A; flex-shrink: 0;
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
        .slide-textbox {
            position: absolute; pointer-events: all;
            padding: 6px 10px; border: 2px dashed transparent;
            border-radius: 4px; cursor: move; min-height: 28px; min-width: 60px;
            outline: none; word-wrap: break-word; line-height: 1.4;
            background: transparent; overflow: visible;
        }
        .slide-textbox:hover { border-color: {{ $accent }}66; }
        .slide-textbox.tb-selected {
            border-color: {{ $accent }}; box-shadow: 0 0 0 1px {{ $accent }}44;
        }
        .slide-textbox:focus { cursor: text; border-style: solid; border-color: {{ $accent }}; }

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
            <button class="btn-action btn-primary" :class="{ 'btn-save-dirty': isDirty }" @click="saveAll()" x-text="isDirty ? '● Speichern' : 'Speichern'"></button>
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
                        <div class="sidebar-overlay">
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

        {{-- Main Column (Toolbar + Slide) --}}
        <div class="edit-main-col">
            {{-- Editing Toolbar --}}
            <div class="edit-toolbar">
                <div class="toolbar-left">
                    <button class="toolbar-btn" :class="{ 'active': placingTextbox }" @click="togglePlaceTextbox()" title="Textfeld auf Slide platzieren">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/></svg>
                        Textfeld
                    </button>
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
                </div>
            </div>

            {{-- Main Slide Area --}}
            <div class="edit-main" @click="deselectAll($event)">
                @yield('slides')

                {{-- Textbox Overlay Layer --}}
                <div class="textbox-layer"
                     :class="{ 'placing': placingTextbox }"
                     @click.stop="handleLayerClick($event)">
                    <template x-for="tb in currentTextboxes" :key="tb.id">
                        <div class="slide-textbox"
                             :class="{ 'tb-selected': selectedElement?.id === tb.id }"
                             :style="`left:${tb.x}px; top:${tb.y}px; width:${tb.width}px; ${tb.height ? 'height:'+tb.height+'px;' : ''} font-size:${tb.fontSize}px; color:${tb.color};`"
                             @mousedown.stop="startDragTextbox($event, tb)"
                             @dblclick.stop="editTextbox($event, tb)"
                             @click.stop="selectTextbox(tb)">
                            <div class="slide-textbox-content"
                                 @blur="onTextboxBlur($event, tb)"
                                 @input="onTextboxInput($event, tb)"
                                 :contenteditable="selectedElement?.id === tb.id && textboxEditing ? 'true' : 'false'"
                                 x-html="tb.text"
                                 style="min-height: 1em; outline: none; width: 100%; height: 100%;"></div>
                            <div class="tb-resize-handle tb-resize-r" @mousedown.stop.prevent="startResize($event, tb, 'r')"></div>
                            <div class="tb-resize-handle tb-resize-b" @mousedown.stop.prevent="startResize($event, tb, 'b')"></div>
                            <div class="tb-resize-handle tb-resize-br" @mousedown.stop.prevent="startResize($event, tb, 'br')"></div>
                            <div class="slide-textbox-del" @click.stop="deleteTextboxById(tb.id)" title="Entfernen">&times;</div>
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
            'textboxes' => $s['textboxes'] ?? [],
            'fontOverrides' => $s['fontOverrides'] ?? [],
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

        isDirty: false,
        placingTextbox: false,
        selectedElement: null,
        currentFontSize: 16,
        textboxEditing: false,
        _dragging: null,
        _focusedEditable: null,

        get currentTextboxes() {
            return this.slidesData[this.currentSlide]?.textboxes || [];
        },

        init() {
            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') {
                    this.renderChartsForSlide(0);
                }
                this.initSortable();
                this.calcSlideScale();
                this.applyFontOverrides(0);
                window.addEventListener('resize', () => this.calcSlideScale());
                setTimeout(() => this.captureThumbnail(0), 1200);
            });

            window.addEventListener('beforeunload', (e) => {
                if (this.isDirty) { e.preventDefault(); e.returnValue = ''; }
            });

            window.addEventListener('mousemove', (e) => { this.onDragTextbox(e); this.onResizeTextbox(e); });
            window.addEventListener('mouseup', () => { this.endDragTextbox(); this.endResizeTextbox(); });

            document.addEventListener('focusin', (e) => {
                if (e.target.classList?.contains('slide-textbox')) return;
                if (e.target.isContentEditable) {
                    this._focusedEditable = e.target;
                    const size = Math.round(parseFloat(window.getComputedStyle(e.target).fontSize));
                    this.currentFontSize = size;
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

        markDirty() { this.isDirty = true; },

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

        // ── Thumbnails ──
        async captureThumbnail(idx) {
            if (typeof html2canvas === 'undefined') return;
            const slideEl = document.querySelector('[data-slide-index="' + idx + '"]');
            if (!slideEl) return;
            try {
                const origTransform = slideEl.style.transform;
                slideEl.style.transform = 'none';
                const isDark = slideEl.classList.contains('slide-dark');
                const canvas = await html2canvas(slideEl, {
                    scale: 0.25, useCORS: true, allowTaint: true,
                    backgroundColor: isDark ? '#1D1D1D' : '#ffffff', logging: false,
                });
                slideEl.style.transform = origTransform;
                this.slidesData[idx].thumbnail = canvas.toDataURL('image/jpeg', 0.7);
            } catch (e) {
                slideEl.style.transform = slideEl.style.transform || '';
            }
        },

        // ── SortableJS (with Alpine fix) ──
        initSortable() {
            const el = this.$refs.sidebarSlides;
            if (!el || typeof Sortable === 'undefined') return;
            const self = this;
            this.sortableInstance = new Sortable(el, {
                animation: 200,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd(evt) {
                    const { oldIndex, newIndex } = evt;
                    if (oldIndex === newIndex) return;
                    evt.item.remove();
                    const ref = el.children[oldIndex];
                    if (ref) ref.before(evt.item); else el.appendChild(evt.item);

                    const moved = self.slidesData.splice(oldIndex, 1)[0];
                    self.slidesData.splice(newIndex, 0, moved);
                    if (self.currentSlide === oldIndex) {
                        self.currentSlide = newIndex;
                    } else if (oldIndex < self.currentSlide && newIndex >= self.currentSlide) {
                        self.currentSlide--;
                    } else if (oldIndex > self.currentSlide && newIndex <= self.currentSlide) {
                        self.currentSlide++;
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
                this.placingTextbox = false;
                this.deselectAll();
                return;
            }
            if (e.target.isContentEditable || e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            if ((e.key === 'Delete' || e.key === 'Backspace') && this.selectedElement?.type === 'textbox') {
                e.preventDefault();
                this.deleteSelectedTextbox();
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
            this.$nextTick(() => {
                if (typeof this.renderChartsForSlide === 'function') this.renderChartsForSlide(idx);
                this.applyFontOverrides(idx);
                setTimeout(() => this.captureThumbnail(idx), 1000);
            });
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
            this.$nextTick(() => this.selectTextbox(tb));
        },

        // ── Textbox: Select / Edit / Delete ──
        selectTextbox(tb) {
            this.selectedElement = { type: 'textbox', id: tb.id };
            this.currentFontSize = tb.fontSize;
            this.textboxEditing = false;
        },

        editTextbox(e, tb) {
            this.selectedElement = { type: 'textbox', id: tb.id };
            this.currentFontSize = tb.fontSize;
            this.textboxEditing = true;
            this.$nextTick(() => {
                const contentEl = e.target.closest('.slide-textbox')?.querySelector('.slide-textbox-content')
                    || e.target.querySelector('.slide-textbox-content')
                    || e.target;
                contentEl.focus();
            });
        },

        onTextboxBlur(e, tb) {
            tb.text = e.target.innerHTML;
            this.textboxEditing = false;
            this.markDirty();
        },

        onTextboxInput(e, tb) {
            tb.text = e.target.innerHTML;
            this.markDirty();
        },

        deleteSelectedTextbox() {
            if (!this.selectedElement || this.selectedElement.type !== 'textbox') return;
            const tbs = this.slidesData[this.currentSlide].textboxes;
            if (!tbs) return;
            const idx = tbs.findIndex(t => t.id === this.selectedElement.id);
            if (idx !== -1) {
                tbs.splice(idx, 1);
                this.selectedElement = null;
                this.markDirty();
            }
        },

        deselectAll(e) {
            if (e && (e.target.closest('.slide-textbox') || e.target.closest('.edit-toolbar') || e.target.closest('.font-size-control'))) return;
            this.selectedElement = null;
            this.textboxEditing = false;
            this._focusedEditable = null;
        },

        // ── Textbox: Drag ──
        startDragTextbox(e, tb) {
            if (this.textboxEditing && this.selectedElement?.id === tb.id) return;
            if (e.target.closest('.tb-resize-handle') || e.target.closest('.slide-textbox-del')) return;
            e.preventDefault();
            this.selectTextbox(tb);
            this._dragging = {
                tb, startX: e.clientX, startY: e.clientY, origX: tb.x, origY: tb.y, moved: false
            };
        },

        onDragTextbox(e) {
            if (!this._dragging) return;
            const scale = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--slide-scale')) || 0.7;
            const dx = (e.clientX - this._dragging.startX) / scale;
            const dy = (e.clientY - this._dragging.startY) / scale;
            if (Math.abs(dx) > 3 || Math.abs(dy) > 3) this._dragging.moved = true;
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
            const idx = tbs.findIndex(t => t.id === id);
            if (idx !== -1) {
                tbs.splice(idx, 1);
                if (this.selectedElement?.id === id) this.selectedElement = null;
                this.markDirty();
            }
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
                const tbs = this.slidesData[this.currentSlide]?.textboxes;
                const tb = tbs?.find(t => t.id === this.selectedElement.id);
                if (tb) { tb.fontSize = size; this.markDirty(); }
            } else if (this.selectedElement?.type === 'contenteditable' && this._focusedEditable) {
                this._focusedEditable.style.fontSize = size + 'px';
                this.saveFontOverride(this._focusedEditable, size);
                this.markDirty();
            }
        },

        saveFontOverride(el, size) {
            const slideEl = el.closest('[data-slide-index]');
            if (!slideEl) return;
            const idx = parseInt(slideEl.dataset.slideIndex);
            const editables = slideEl.querySelectorAll('[contenteditable]');
            let editIdx = -1;
            editables.forEach((ed, i) => { if (ed === el) editIdx = i; });
            if (editIdx === -1) return;
            if (!this.slidesData[idx].fontOverrides) this.slidesData[idx].fontOverrides = {};
            this.slidesData[idx].fontOverrides['ce-' + editIdx] = size;
        },

        applyFontOverrides(idx) {
            const overrides = this.slidesData[idx]?.fontOverrides;
            if (!overrides || !Object.keys(overrides).length) return;
            this.$nextTick(() => {
                const slideEl = document.querySelector('[data-slide-index="' + idx + '"]');
                if (!slideEl) return;
                const editables = slideEl.querySelectorAll('[contenteditable]');
                Object.entries(overrides).forEach(([key, size]) => {
                    const i = parseInt(key.replace('ce-', ''));
                    if (editables[i]) editables[i].style.fontSize = size + 'px';
                });
            });
        },

        // ── Save ──
        async saveAll() {
            this.saveStatus = 'Wird gespeichert…';
            try {
                const res = await this._fetch('{{ route("presentation.save", $presentation->id) }}', 'POST', {
                    slides: this.slidesData,
                });
                if (res.ok) {
                    this.isDirty = false;
                    this.saveStatus = 'Gespeichert';
                    setTimeout(() => { this.saveStatus = ''; }, 2000);
                }
            } catch (e) {
                this.saveStatus = 'Fehler beim Speichern';
                setTimeout(() => { this.saveStatus = ''; }, 3000);
            }
        },

        async renamePresentation(newTitle) {
            this.presentationTitle = newTitle;
            this.markDirty();
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
                    const newSlides = data.slides.map(s => ({
                        id: s.id, type: s.type, title: s.title || '', source: s.source || 'generated',
                        theme: s.theme || 'light', thumbnail: null, textboxes: s.textboxes || [], fontOverrides: s.fontOverrides || {},
                    }));
                    this.slidesData = newSlides;
                    this.totalSlides = newSlides.length;
                    this.isDirty = false;
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
            if (!confirm('Slides neu generieren? Textänderungen an generierten Slides gehen verloren.')) return;
            try {
                const res = await this._fetch('{{ route("presentation.regenerate", $presentation->id) }}', 'POST');
                const data = await res.json();
                if (data.redirect) window.location.href = data.redirect;
            } catch (e) { console.error(e); }
        },

        saveOverride(event) {},

        destroyChartsForSlide(idx) {
            const keys = Object.keys(this.chartInstances).filter(k => k.startsWith('slide-' + idx + '-'));
            keys.forEach(k => { try { this.chartInstances[k].destroy(); } catch(e) {} delete this.chartInstances[k]; });
        },

        renderChartsForSlide(idx) {},

        // ── PDF Export ──
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
