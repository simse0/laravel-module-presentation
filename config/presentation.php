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
];
