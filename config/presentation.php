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
    'vite_assets' => ['resources/css/app.css', 'resources/js/app.js'],

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
    'allowed_html_tags' => ['a'],

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
    | AI Slides
    |--------------------------------------------------------------------------
    | Generic AI slide generation settings.
    */
    'ai_slides' => [
        'cache_ttl' => (int) env('AI_SLIDES_CACHE_TTL', 604800),
        'generators' => [
            // Example:
            // 'agency-highlights' => [
            //     'service' => \App\Services\AgencyHighlightsService::class,
            //     'method' => 'buildAiPayload',
            // ],
        ],
    ],
];
