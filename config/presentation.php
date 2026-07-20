<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subject Model
    |--------------------------------------------------------------------------
    | Das Eloquent-Model, für das Präsentationen erstellt werden.
    | Z.B. App\Models\Feedback, App\Models\Report, etc.
    */
    'subject_model' => env('PRESENTATION_SUBJECT_MODEL', 'App\\Models\\Feedback'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Subject Types
    |--------------------------------------------------------------------------
    | Whitelist der Model-Klassen, die als subject_type in der create()-Route
    | akzeptiert werden. Verhindert beliebige Klassen-Instanziierung.
    | Faellt auf [subject_model] zurueck, wenn nicht gesetzt.
    */
    'allowed_subject_types' => null,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */
    'user_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    | URL-Prefix für die Präsentations-Routes.
    */
    'route_prefix' => 'presentations',

    /*
    |--------------------------------------------------------------------------
    | Lookup Route
    |--------------------------------------------------------------------------
    | GET /by-name/{name} ohne Authorizer – nur aktivieren wenn der Host die
    | Route nutzt; sonst false (Bridge nutzt PresentationEngine::findByName).
    */
    'enable_lookup_route' => true,

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    | Middleware-Stack für die Präsentations-Routes.
    */
    'middleware' => ['web', 'auth', 'verified'],

    /*
    |--------------------------------------------------------------------------
    | View
    |--------------------------------------------------------------------------
    | Die View, die vom Controller gerendert wird.
    | Muss presentation::layout extenden und @section('slides') definieren.
    */
    'view' => 'presentation.show',

    /*
    |--------------------------------------------------------------------------
    | Edit View
    |--------------------------------------------------------------------------
    | Die View fuer den Edit-Modus.
    */
    'edit_view' => 'presentation.edit',

    /*
    |--------------------------------------------------------------------------
    | Edit Mode
    |--------------------------------------------------------------------------
    | Ob der Bearbeitungsmodus verfuegbar ist.
    */
    'enable_edit_mode' => true,

    /*
    |--------------------------------------------------------------------------
    | Font
    |--------------------------------------------------------------------------
    */
    'font_family' => 'Plus Jakarta Sans',
    'font_url' => 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',

    /*
    |--------------------------------------------------------------------------
    | Slide Defaults
    |--------------------------------------------------------------------------
    */
    'slide_width' => 1280,
    'slide_height' => 720,
    'accent_color' => '#00AFCE',

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    */
    'brand_name' => 'trafficdesign',
    'favicon' => null,

    /*
    |--------------------------------------------------------------------------
    | Vite Assets (optional)
    |--------------------------------------------------------------------------
    | Wenn gesetzt, werden diese Vite-Einträge geladen.
    | Setze auf null um keine Vite-Assets zu laden.
    */
    'vite_assets' => ['resources/css/app.css', 'resources/js/presentation/bootstrap.js'],

    /*
    |--------------------------------------------------------------------------
    | PDF Export (Headless Chrome)
    |--------------------------------------------------------------------------
    | Konfiguration fuer den server-seitigen PDF-Export via Puppeteer.
    | Benoetigt: npm install puppeteer pdf-lib
    | System-Abhaengigkeiten (Debian/Ubuntu): libnss3 libatk-bridge2.0-0
    |   libdrm2 libxkbcommon0 libgbm1
    */
    'pdf_export' => [
        'node_binary' => env('PRESENTATION_NODE_BINARY', 'node'),
        'chrome_path' => env('PRESENTATION_CHROME_PATH'),
        'puppeteer_cache_dir' => env('PRESENTATION_PUPPETEER_CACHE_DIR'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed HTML Tags
    |--------------------------------------------------------------------------
    | HTML-Tags die in Textbox-Inhalten erlaubt sind. Alle anderen Tags
    | werden beim Speichern entfernt. Leeres Array = alles strippen.
    */
    'allowed_html_tags' => ['a', 'b', 'strong', 'i', 'em', 'u'],

    /*
    |--------------------------------------------------------------------------
    | Image Upload
    |--------------------------------------------------------------------------
    | Konfiguration fuer Bild-Uploads auf Slides.
    | disk: Laravel Filesystem-Disk (default: public)
    | path: Unterverzeichnis auf dem Disk
    | max_size: Maximale Dateigroesse in KB
    | allowed_types: Erlaubte Dateiendungen
    */
    'images' => [
        'disk' => env('PRESENTATION_IMAGE_DISK', 'public'),
        'path' => 'presentation-images',
        'max_size' => 2048,
        'allowed_types' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Textbox Positions
    |--------------------------------------------------------------------------
    | Standard-Koordinaten fuer System-Textboxen (Titel, Untertitel, Footer).
    | Alle Werte in Pixeln. Koennen in der Host-App-Config ueberschrieben werden.
    */
    /*
    |--------------------------------------------------------------------------
    | PPTX Export Types
    |--------------------------------------------------------------------------
    | Per slide type: mode (native|hybrid) and screenshot scope (none|content|full).
    | Host apps can override or extend (e.g. heatmap). Package fallback in
    | ExportTypeRegistry::fallbackTypes() when config is missing.
    */
    'export' => [
        'types' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Perspective Accent Colors (optional)
    |--------------------------------------------------------------------------
    | Used to derive header_accent shapes for existing snapshots without
    | header_accent stored. Host apps should map their domain colors here.
    */
    'perspective_colors' => [],
    'perspective_color_fallback' => '#6B7280',

    'textbox_positions' => [
        'slide_padding_x' => 56,
        'slide_padding_y' => 48,

        'default_title_x' => 56,
        'default_title_y' => 48,
        'default_subtitle_x' => 56,
        'default_subtitle_y' => 86,
        'default_footer_x' => 56,
        'default_footer_y' => 681,

        'title_x' => 56,
        'title_y' => 48,
        'subtitle_x' => 56,
        'subtitle_y' => 86,

        'perspective_title_x' => 84,
        'perspective_title_y' => 48,
        'perspective_subtitle_x' => 56,
        'perspective_subtitle_y' => 86,

        'reflection_title_x' => 100,
        'reflection_title_y' => 48,
        'reflection_subtitle_x' => 56,
        'reflection_subtitle_y' => 86,

        'title_slide_title_x' => 56,
        'title_slide_title_y' => 330,
        'title_slide_subtitle_x' => 56,
        'title_slide_subtitle_y' => 385,

        'footer_x' => 56,
        'footer_y' => 681,
        'footer_width' => 500,
        'content_x' => 56,
        'content_y' => 128,
        'content_height' => 400,
    ],
];
