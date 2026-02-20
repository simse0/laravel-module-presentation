# Laravel Presentation Module

Eigenstaendiges Composer-Package fuer browser-basierte Slide-Praesentationen in Laravel-Anwendungen. Bietet einen Present-Modus (read-only mit Vollbild) und einen Edit-Modus (PowerPoint-aehnlicher Editor mit Sidebar, Textboxen, Drag-and-Drop).

## Features

- **Present-Modus**: Read-Only Vollbild-Praesentation mit Tastatur-Navigation
- **Edit-Modus**: Sidebar-Editor mit Drag-and-Drop, Textbox-System, Font- und Farb-Steuerung
- **Block-basiertes Text-System**: Alle Text-Elemente (Title, Subtitle, Footer, Custom) als frei verschiebbare Textboxen
- **PDF-Export**: Client-seitig via html2canvas + jsPDF
- **Snapshot-Persistenz**: Slide-Daten als JSON in der DB
- **Polymorphe Zuordnung**: Praesentationen koennen beliebigen Models zugeordnet werden
- **Name-basierter Lookup**: Eindeutiger `name` fuer programmatischen Zugriff

---

## Installation

```bash
composer require trafficdesign/laravel-presentation
php artisan presentation:install
php artisan migrate
```

Config publizieren (optional):

```bash
php artisan vendor:publish --tag=presentation-config
```

---

## Architektur

```
Host-App (z.B. 360-Feedback)
    |-- SlideBuilder            --> Implementiert SlideBuilderInterface
    |-- DataCollector           --> Implementiert DataCollectorInterface
    |-- Authorizer              --> Implementiert AuthorizerInterface
    +-- Views
        |-- presentation/show.blade.php  --> @extends('presentation::layout')
        +-- presentation/edit.blade.php  --> @extends('presentation::edit-layout')

Package (trafficdesign/laravel-presentation)
    |-- PresentationEngine      --> Zentraler Service (DI-faehig)
    |-- PresentationController  --> Routes-Handler
    |-- Presentation Model      --> DB-Entity mit JSON-Snapshot
    +-- Blade Layouts           --> Present + Edit Mode
```

---

## Slide-Datenstruktur

Jede Praesentation speichert ihre Slides als JSON-Array in `slides_data`. Jeder Slide ist ein assoziatives Array:

### Basis-Felder (alle Slide-Typen)

```php
[
    'id'       => 'title',              // Eindeutige Slide-ID (string)
    'type'     => 'title',              // Slide-Typ -> bestimmt Blade-Komponente
    'theme'    => 'dark',               // 'dark' oder 'light' (Hintergrundfarbe)
    'title'    => 'Mein Titel',         // Haupttitel (editierbar)
    'subtitle' => 'Untertitel',         // Untertitel (editierbar, optional)
    'footer'   => 'Firma - Produkt',    // Footer-Text (editierbar, optional)
    'source'   => 'generated',          // 'generated' oder 'user'
    'data'     => [ ... ],              // Slide-spezifische Daten (Charts, Statistiken)
    'textboxes'    => [ ... ],          // Textbox-Array (siehe unten)
    'fontOverrides' => [],              // Font-Size-Overrides fuer contenteditable
]
```

### Slide-Typen

| `type` | Blade-Komponente | Beschreibung |
|--------|-----------------|--------------|
| `title` | `slides.title` | Titelfolie mit Score-Circle |
| `summary` | `slides.summary` | Executive Summary mit Chart |
| `participants` | `slides.participants` | Teilnehmer-Uebersicht |
| `chart-bar` | `slides.chart-bar` | Balkendiagramm (Top/Bottom) |
| `perspective` | `slides.perspective` | Perspektiven-Detail |
| `self-gap` | `slides.self-gap` | Selbst-/Fremdbild-Vergleich |
| `divergence` | `slides.divergence` | Divergenz-Analyse |
| `text` | `slides.text` | Freitext-Slide (user-erstellt) |

### `source`-Feld

- `"generated"` — Automatisch vom SlideBuilder erstellt. Wird bei `regenerate()` ueberschrieben.
- `"user"` — Manuell im Edit-Modus erstellt. Bleibt bei `regenerate()` erhalten.

---

## Textbox-System

Im Edit-Modus werden alle sichtbaren Text-Elemente als frei positionierbare Textboxen dargestellt. Es gibt zwei Arten:

### System-Textboxen (`source: "system"`)

Werden automatisch aus den Slide-Feldern (`title`, `subtitle`, `footer`) erzeugt. Ihre Position, Groesse und Farbe basieren auf dem Slide-Typ und Theme.

```php
[
    'id'         => 'summary__title',   // Format: {slideId}__{role}
    'role'       => 'title',            // Welches Slide-Feld ('title','subtitle','footer','content')
    'source'     => 'system',           // Kennzeichnung als System-Element
    'text'       => 'Executive Summary',
    'x'          => 56,                 // Position X in Slide-Koordinaten (0-1280)
    'y'          => 48,                 // Position Y in Slide-Koordinaten (0-720)
    'width'      => 1168,               // Breite in Pixeln
    'height'     => null,               // Hoehe (null = auto)
    'fontSize'   => 28,                 // Schriftgroesse in px
    'fontWeight' => 800,                // CSS font-weight
    'color'      => '#1a1a2e',          // Textfarbe (hex)
    'align'      => 'left',             // Ausrichtung: 'left', 'center', 'right'
]
```

**Wichtig**: Beim Speichern wird der `text`-Wert von System-Textboxen automatisch zurueck in das entsprechende Slide-Feld geschrieben (`role` -> Feld). So bleiben Blade-Templates und Textbox-System synchron.

### User-Textboxen (`source: "user"`)

Vom Nutzer manuell platzierte Textfelder:

```php
[
    'id'         => 'tb-1771593917545-8gen',  // Generierte ID
    'source'     => 'user',
    'text'       => 'Freitext-Inhalt',
    'x'          => 200,
    'y'          => 300,
    'width'      => 300,
    'height'     => null,
    'fontSize'   => 18,
    'fontWeight' => 400,
    'color'      => '#E5E7EB',
    'align'      => 'left',
]
```

### Textbox-Koordinatensystem

- **Ursprung**: Oben-links des Slides (0, 0)
- **Slide-Groesse**: 1280 x 720 px (konfigurierbar)
- **Alle Positionen**: In absoluten Pixeln relativ zum Slide

### Standard-Positionen nach Slide-Typ

| Typ | Element | x | y | fontSize | align |
|-----|---------|---|---|----------|-------|
| `title` (center) | title | 56 | 330 | 42 | center |
| `title` (center) | subtitle | 56 | 390 | 18 | center |
| Alle anderen | title | 56 | 48 | 28 | left |
| Alle anderen | subtitle | 56 | 80 | 15 | left |
| Alle | footer | 56 | 681 | 11 | left |

### Farben nach Theme

| Theme | Title-Farbe | Subtitle-Farbe | Footer-Farbe |
|-------|------------|----------------|-------------|
| `dark` | `#ffffff` | `#9CA3AF` | `#6B7280` |
| `light` | `#1a1a2e` | `#6B7280` | `#9CA3AF` |

---

## Slides programmatisch befuellen

### 1. SlideBuilder implementieren

```php
use Trafficdesign\Presentation\Contracts\SlideBuilderInterface;

class MySlideBuilder implements SlideBuilderInterface
{
    public function buildSlides(Model $subject, array $data): array
    {
        return [
            [
                'id'       => 'title',
                'type'     => 'title',
                'theme'    => 'dark',
                'title'    => $data['name'],
                'subtitle' => 'Auswertung ' . $data['period'],
                'footer'   => 'Firma - Produkt',
                'source'   => 'generated',
                'data'     => [
                    'score'       => $data['overallScore'],
                    'score_label' => 'Solid State',
                    'stats'       => [
                        ['label' => 'Bewertungen', 'value' => $data['totalRatings']],
                        ['label' => 'Teilnehmer',  'value' => $data['totalReviewers']],
                    ],
                ],
            ],
            [
                'id'       => 'summary',
                'type'     => 'summary',
                'theme'    => 'light',
                'title'    => 'Executive Summary',
                'subtitle' => 'Gesamtuebersicht der Ergebnisse',
                'footer'   => $data['name'] . ' - ' . $data['period'],
                'source'   => 'generated',
                'data'     => [
                    'fremd_avg'   => $data['fremdAvg'],
                    'self_avg'    => $data['selfAvg'],
                    'diff'        => $data['diff'],
                    'total_count' => $data['totalRatings'],
                    'self_count'  => $data['selfCount'],
                ],
            ],
            // ... weitere Slides
        ];
    }
}
```

### 2. DataCollector implementieren

```php
use Trafficdesign\Presentation\Contracts\DataCollectorInterface;

class MyDataCollector implements DataCollectorInterface
{
    public function collectData(Model $subject): array
    {
        return [
            'name'           => $subject->name,
            'period'         => '01.01.2025 - 31.12.2025',
            'overallScore'   => 3.4,
            'totalRatings'   => 281,
            'totalReviewers' => 17,
            // ... alle Daten die der SlideBuilder braucht
        ];
    }

    public function resolveTitle(Model $subject): string
    {
        return $subject->name . ' - Praesentation';
    }
}
```

### 3. Bindings registrieren (ServiceProvider)

```php
$this->app->bind(SlideBuilderInterface::class, MySlideBuilder::class);
$this->app->bind(DataCollectorInterface::class, MyDataCollector::class);
$this->app->bind(AuthorizerInterface::class, MyAuthorizer::class);
```

### 4. Praesentation erstellen und generieren

```php
use Trafficdesign\Presentation\PresentationEngine;

$engine = app(PresentationEngine::class);

// Erstellen
$presentation = $engine->createPresentation(
    name: 'report-' . $subject->id,
    subject: $subject,
    user: $user
);

// Slides generieren und als Snapshot speichern
$result = $engine->generateAndSave($subject, $presentation);
// $result = ['slides' => [...], 'reportData' => [...]]

// Spaeter: Snapshot laden (ohne Neugenerierung)
$result = $engine->loadFromSnapshot($presentation);
```

### 5. Slides mit vordefinierten Textboxen erstellen

Wenn du Slides mit spezifischen Textbox-Positionen erstellen willst:

```php
$slides = [
    [
        'id'    => 'custom-intro',
        'type'  => 'text',
        'theme' => 'light',
        'title' => 'Einleitung',
        'source' => 'generated',
        'textboxes' => [
            [
                'id'         => 'custom-intro__title',
                'role'       => 'title',
                'source'     => 'system',
                'text'       => 'Einleitung',
                'x'          => 56,
                'y'          => 48,
                'width'      => 1168,
                'height'     => null,
                'fontSize'   => 28,
                'fontWeight' => 800,
                'color'      => '#1a1a2e',
                'align'      => 'left',
            ],
            [
                'id'         => 'tb-body-text',
                'source'     => 'user',
                'text'       => 'Hier steht der Inhalt...',
                'x'          => 56,
                'y'          => 120,
                'width'      => 600,
                'fontSize'   => 16,
                'color'      => '#374151',
            ],
        ],
    ],
];
```

---

## API-Referenz

### PresentationEngine

| Methode | Beschreibung |
|---------|-------------|
| `findByName(string $name): ?Presentation` | Suche per eindeutigem Namen |
| `createPresentation(string $name, Model $subject, User $user): Presentation` | Neue Praesentation anlegen |
| `generateAndSave(Model $subject, Presentation $pres): array` | Slides generieren + Snapshot speichern |
| `loadFromSnapshot(Presentation $pres): array` | Gespeicherten Snapshot laden |
| `regenerate(Model $subject, Presentation $pres): array` | Neu generieren (user-Slides bleiben) |
| `addTextSlide(Presentation $pres, array $data, ?int $pos): array` | Text-Slide einfuegen |
| `removeSlide(Presentation $pres, string $slideId): array` | Slide entfernen |
| `saveSlides(Presentation $pres, array $slides): void` | Slide-State speichern (Textboxen, Overrides) |

### Presentation Model

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `name` | string | Eindeutiger Bezeichner (z.B. `"feedback-42"`) |
| `presentable_type` / `presentable_id` | morph | Zugeordnetes Model |
| `user_id` | foreignId | Ersteller |
| `title` | string | Anzeige-Titel |
| `slides_data` | JSON/array | Kompletter Slide-Snapshot |
| `report_data` | JSON/array | Daten-Snapshot fuer Charts |
| `slide_order` | JSON/array | Slide-Reihenfolge (IDs) |
| `text_overrides` | JSON/array | Legacy: Text-Ueberschreibungen |
| `settings` | JSON/array | Zusaetzliche Einstellungen |

| Methode | Beschreibung |
|---------|-------------|
| `hasSnapshot(): bool` | Pruefen ob Snapshot existiert |
| `getSlides(): array` | Slides aus Snapshot holen |
| `getReportData(): array` | Report-Daten aus Snapshot holen |

---

## Routes

Alle Routes unter Prefix `config('presentation.route_prefix')` (default: `presentations`).

| Method | Route | Name | Beschreibung |
|--------|-------|------|-------------|
| GET | `/by-name/{name}` | `presentation.lookup` | Lookup per Name (JSON) |
| POST | `/` | `presentation.create` | Neue Praesentation (JSON) |
| GET | `/{id}` | `presentation.show` | Present-Modus |
| GET | `/{id}/edit` | `presentation.edit` | Edit-Modus |
| POST | `/{id}/save` | `presentation.save` | Slides speichern (JSON) |
| POST | `/{id}/regenerate` | `presentation.regenerate` | Neu generieren |
| POST | `/{id}/rename` | `presentation.rename` | Umbenennen |
| POST | `/{id}/slides` | `presentation.slides.add` | Text-Slide hinzufuegen |
| DELETE | `/{id}/slides/{slideId}` | `presentation.slides.remove` | Slide entfernen |

---

## Host-App Views

Die Host-App muss zwei Views bereitstellen, die das Package-Layout erweitern:

### Present-Modus (`presentation/show.blade.php`)

```blade
@extends('presentation::layout')

@section('slides')
    @foreach($slides as $idx => $slide)
        @switch($slide['type'])
            @case('title')
                <x-presentation.slides.title
                    :slide="$slide" :slide-index="$idx"
                    :total-slides="count($slides)" :mode="$mode ?? 'present'" />
                @break
            {{-- weitere Typen --}}
        @endswitch
    @endforeach
@endsection
```

### Edit-Modus (`presentation/edit.blade.php`)

```blade
@extends('presentation::edit-layout')

@section('slides')
    @foreach($slides as $idx => $slide)
        @switch($slide['type'])
            @case('title')
                <x-presentation.slides.title
                    :slide="$slide" :slide-index="$idx"
                    :total-slides="count($slides)" :mode="$mode ?? 'edit'" />
                @break
            {{-- weitere Typen --}}
        @endswitch
    @endforeach
@endsection
```

### Slide-Komponente (Basis)

Jede Slide-Komponente muss die Basis-Komponente `<x-presentation.slide>` verwenden:

```blade
<x-presentation.slide
    :theme="$slide['theme']"
    :slide-index="$slideIndex"
    :total-slides="$totalSlides"
    :footer="$slide['footer']"
    :slide-id="$slide['id']"
    :mode="$mode">
    {{-- Slide-Inhalt --}}
</x-presentation.slide>
```

**Wichtig**: `:slide-id="$slide['id']"` ist **erforderlich** — das Slide-Switching basiert auf IDs (nicht auf numerischen Indices), damit Drag-and-Drop-Umsortierung korrekt funktioniert.

---

## Save-Workflow (Textbox-Synchronisation)

Beim Speichern ueber den Edit-Modus passiert folgendes:

1. **Client sendet** `slidesData` als JSON (inkl. Textboxen mit Position/Text/Style)
2. **`saveSlides()`** iteriert ueber alle Slides:
   - System-Textboxen (`source: "system"`): Der `text`-Wert wird zurueck ins Slide-Feld geschrieben (z.B. `role: "title"` -> `$slide['title']`)
   - Alle Textboxen werden mit Position/Style im `textboxes`-Array persistiert
3. **Legacy-Cleanup**: Alte `text_overrides` werden geloescht, da das Textbox-System jetzt die Datenquelle ist

```
  Edit Mode          saveSlides()           Database
  (Alpine.js)   -->  PresentEngine    -->   slides_data
                                            
  tb.text =          system tb ->           title =
  "Neuer Titel"      slide.title            "Neuer Titel"
                     + persist tb           textboxes = [{...}]
                     with position
```

---

## Konfiguration

```php
// config/presentation.php
return [
    'subject_model'    => App\Models\Feedback::class,
    'user_model'       => App\Models\User::class,
    'route_prefix'     => 'presentations',
    'middleware'        => ['web', 'auth', 'verified'],
    'view'             => 'presentation.show',
    'edit_view'        => 'presentation.edit',
    'enable_edit_mode' => true,
    'font_family'      => 'Plus Jakarta Sans',
    'font_url'         => 'https://fonts.googleapis.com/css2?family=...',
    'slide_width'      => 1280,
    'slide_height'     => 720,
    'accent_color'     => '#00AFCE',
    'brand_name'       => 'trafficdesign',
    'favicon'          => null,
    'vite_assets'      => ['resources/css/app.css', 'resources/js/app.js'],
];
```

---

## Lizenz

MIT
