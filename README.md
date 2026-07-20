# Laravel Presentation Module

Eigenstaendiges Composer-Package fuer browser-basierte Slide-Praesentationen in Laravel-Anwendungen. Bietet einen Present-Modus (read-only mit Vollbild) und einen Edit-Modus (PowerPoint-aehnlicher Editor mit Sidebar, Textboxen, Drag-and-Drop).

## Features

- **Present-Modus**: Read-Only Vollbild-Praesentation mit Tastatur-Navigation
- **Edit-Modus**: Sidebar-Editor mit Drag-and-Drop, Textbox-System, Font- und Farb-Steuerung, Undo/Redo (Ctrl+Z/Y)
  - **Einfach-Klick** = Textbox auswaehlen (Move-Cursor, Resize-Handles)
  - **Doppelklick** = Textediting aktivieren (Text-Cursor, Inhalt bearbeiten)
  - **Escape** = Edit-Modus → Select-Modus → Deselect
- **Block-basiertes Text-System**: Alle Text-Elemente (Title, Subtitle, Footer, Custom) als frei verschiebbare Textboxen
- **PDF-Export**: Server-seitig via Headless Chrome (Puppeteer) – pixel-perfekte Screenshots aller Slides
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

## Minimal Host in 10 Minuten

Kurzcheckliste fuer eine neue Laravel-App ohne Domain-Slides (nur Titelfolie + Content-Slide):

1. `composer require trafficdesign/laravel-presentation` und `php artisan presentation:install`
2. `App\Presentation\PresentationServiceProvider` in `bootstrap/providers.php` registrieren
3. `config/presentation.php`: `subject_model` auf dein Eloquent-Model setzen
4. `npm install alpinejs`, dann `resources/js/presentation/bootstrap.js` in `vite.config.js` als Vite-Input eintragen und `npm run build`
5. `php artisan migrate`
6. Erste Praesentation erzeugen:

```php
$engine = app(\Trafficdesign\Presentation\PresentationEngine::class);
$presentation = $engine->createPresentation('demo-1', $subject, auth()->user());
$engine->generateAndSave($subject, $presentation);
// route('presentation.show', $presentation->id)
```

**Ownership Package vs Host**

| Verantwortung | Package | Host |
|---|---|---|
| Layout, Engine, Routes, DB-Model | ja | nein |
| Slide-Inhalt, Daten, Berechtigungen | nein | ja (`DataCollector`, `SlideBuilder`, `Authorizer`) |
| Slide-Blade-Komponenten | Basis-Stubs | Domain-spezifische Slides |
| Alpine-Bootstrap / Charts | Stub bereitstellen | Vite-Entry + optional Chart-JS |
| SSoT fuer Overrides | Events (`SlidesSaved`) | Listener + Persistenz (siehe [SSoT-Vertrag](#ssot-vertrag)) |

**Upgrade-Hinweis:** Hosts mit bereits publizierter Config (z. B. weiter `app.js` in `vite_assets`) bleiben unveraendert. Neue Installs und frisch publizierte Config nutzen `resources/js/presentation/bootstrap.js`. Ab Package-Upgrade mit Layout-Aenderung: Inline-Config muss vor dem Vite-Modul stehen (`@stack('presentation-styles')` vor `@vite`).

---

### PDF-Export (Headless Chrome)

Der PDF-Export laeuft **asynchron via Queue-Worker**. Benoetigt werden:

**1. npm-Pakete** (im Root-Verzeichnis der Host-App):

```bash
npm install puppeteer pdf-lib pptxgenjs
```

| Paket | Benoetigt fuer |
|-------|---------------|
| `puppeteer` | PDF-Export (Headless Chrome) + PowerPoint-Export (Chart-Screenshots) |
| `pdf-lib` | PDF-Export (Slides zusammenfuegen) |
| `pptxgenjs` | PowerPoint-Export (.pptx erstellen) |

**2. System-Pakete** (Debian/Ubuntu) fuer Headless Chrome:

```bash
apt-get install -y libnss3 libatk-bridge2.0-0 libdrm2 libxkbcommon0 libgbm1 \
  libcups2 libxrandr2 libasound2t64 libpango-1.0-0 libpangocairo-1.0-0 \
  libatk1.0-0 libcairo2 libx11-xcb1 libxcomposite1 libxdamage1 libxfixes3 \
  libxrender1 libxtst6
```

**3. Queue-Worker** muss laufen (verarbeitet den Export-Job):

```bash
php artisan queue:work --timeout=300
```

Empfohlen: Via Supervisor mit `stopwaitsecs=300` damit der Worker nicht vor Abschluss gekillt wird.

**4. Schreibrechte** fuer das temporaere Ausgabeverzeichnis:

```bash
mkdir -p storage/app/temp
chown -R www-data:www-data storage/app/temp
```

**5. Puppeteer-Cache** muss fuer den Webserver-User zugaenglich sein. Falls Chrome unter `/root/.cache/puppeteer/` installiert wurde (Puppeteer-Standard), in ein zugaengliches Verzeichnis kopieren:

```bash
cp -r /root/.cache/puppeteer/ /var/www/html/storage/.puppeteer-cache/
chown -R www-data:www-data /var/www/html/storage/.puppeteer-cache/
```

Optional: Chrome-Pfad, Node-Binary und Puppeteer-Cache in `.env` konfigurieren:

```env
PRESENTATION_NODE_BINARY=node
PRESENTATION_CHROME_PATH=
PRESENTATION_PUPPETEER_CACHE_DIR=/var/www/html/storage/.puppeteer-cache
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

## JavaScript / Asset-Ladereihenfolge

Das Package startet **Alpine.js nie selbst**. Es stellt Layouts mit inline `presentationEngine()` / `editEngine()` und zwei Erweiterungs-Hooks bereit:

| Hook | Position | Zweck |
|------|----------|-------|
| `@stack('presentation-styles')` | `<head>`, vor `@vite` | Inline-Config (`window.__*`) die Vite-Module brauchen |
| `@stack('presentation-scripts')` | Body-Ende, nach Engine-Script | Host-Hooks via `alpine:init` |

**Load-Order-Regel fuer Host-Apps:**

1. Inline-Config als sync-`<script>` (z.B. `window.__presentationChartColors`)
2. Genau **ein** Vite-Entry, der Dependencies laedt und **als letztes** `Alpine.start()` aufruft
3. Package-Layout definiert `presentationEngine()` / `editEngine()` als sync-`<script>`
4. Host registriert Wrappers in `@push('presentation-scripts')` via `document.addEventListener('alpine:init', ...)`

**Standalone ohne Charts:** Minimal-Stub `resources/stubs/host-alpine-bootstrap.js.example` (wird von `presentation:install` nach `resources/js/presentation/bootstrap.js` kopiert). Fuer Chart-Hosts optional `host-presentation-bootstrap.js.example`. Alternativ `vite_assets => null` und Alpine selbst laden. `renderChartsForSlide` ist im Package ein leerer Hook.

**Anti-Pattern:** Dasselbe Vite-Modul zweimal laden (einmal im Bootstrap-Entry importiert, zusaetzlich per `@vite` in einem Partial). Das erzeugt Race-Conditions bei der Initialisierungsreihenfolge.

Beispiel Host-Integration (360-Feedback):

```php
// config/presentation.php
'vite_assets' => ['resources/css/app.css', 'resources/js/presentation/bootstrap.js'],
```

```blade
{{-- presentation/show.blade.php --}}
@include('presentation._chart-colors-presentation-styles', ['ratingScale' => $reportData['ratingScale'] ?? null])
@include('presentation._presentation-chart-setup', [
    'engineFunction' => 'presentationEngine',
    'reportData' => $reportData,
    'slides' => $slides,
    'includeYoy' => true,
])
```

Referenz-Bootstrap Minimal: `resources/stubs/host-alpine-bootstrap.js.example`  
Referenz-Bootstrap mit Charts (optional): `resources/stubs/host-presentation-bootstrap.js.example`

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
    'images'       => [ ... ],          // Bild-Array (siehe Bild-Upload-Abschnitt)
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

- `"generated"` — Automatisch vom SlideBuilder erstellt. Wird bei `regenerate()` mit aktuellen Daten neu erzeugt.
- `"user"` — Manuell im Edit-Modus erstellt. Bleibt bei `regenerate()` erhalten (SSoT-Position und Overrides werden uebernommen, Fallback: aus altem Snapshot angehaengt).

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

**SSoT-Titel-Sync (wichtig):** Der `text`-Wert von System-Textboxen wird beim Laden **immer aus dem SSoT-Slide-Feld** gelesen (z.B. `slide['title']`), gespeicherte `text`-Werte werden ignoriert. Nur Positions- und Style-Felder (`x`, `y`, `fontSize`, etc.) werden aus dem gespeicherten Snapshot uebernommen. So zeigen Slide-Ueberschriften immer den aktuellen SSoT-Titel – auch wenn der Titel nachtraeglich geaendert wurde.

Beim Speichern wird `text` einer System-Textbox automatisch zurueck ins Slide-Feld geschrieben (`role: "title"` → `slide['title']`).

**`hidden`-Flag:** System-Textboxen koennen nicht direkt geloescht werden. Stattdessen wird `hidden: true` gesetzt. `prepareSlidesForView()` filtert versteckte Textboxen heraus. Das ermoeglicht es dem User, eine System-Textbox zu "loeschen", ohne sie aus der Datenstruktur zu entfernen (sie kann so jederzeit wiederhergestellt werden).

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
- **Slide-Groesse**: 1280 x 720 px (konfigurierbar via `slide_width`/`slide_height`)
- **Alle Positionen**: In absoluten Pixeln relativ zum Slide
- **Konfigurierbar**: Alle Standard-Koordinaten kommen aus `config('presentation.textbox_positions')` – kein Hardcoding im Package noetig

### Standard-Positionen nach Slide-Typ

| Typ | Element | x | y | fontSize | align |
|-----|---------|---|---|----------|-------|
| `title` (Titelseite, zentriert) | title | 56 | 330 | 42 | center |
| `title` (Titelseite, zentriert) | subtitle | 56 | 385 | 18 | center |
| Standard Content-Slides | title | 56 | 48 | 28 | left |
| Standard Content-Slides | subtitle | 56 | 86 | 15 | left |
| `perspective*` (mit Farbpunkt) | title | **84** | 48 | 28 | left |
| `perspective*` | subtitle | 56 | 86 | 15 | left |
| `reflection` (mit Icon) | title | **100** | 48 | 28 | left |
| `reflection` | subtitle | 56 | 86 | 15 | left |
| Alle | footer | 56 | 681 | 11 | left |

Die X-Offsets fuer `perspective` und `reflection` entstehen durch Blade-Elemente (Farbpunkt 16px + Gap 12px, Icon 34px + Gap 10px) vor dem Titel. Wenn sich das Design der Blade-Templates aendert, muessen die entsprechenden `textbox_positions`-Werte in der Host-App-Config angepasst werden.

### `$skipTextboxes` – wann und warum

`PresentationEngine` hat intern eine `$skipTextboxes`-Liste. Slide-Typen darin bekommen **keine** System-Textboxen generiert – weder Titel noch Untertitel als Overlay. Stattdessen rendert das Blade-Template den Titel direkt.

**Aktuell in der Liste:** `perspective-cover`, `agenda`

**Regel:** Nur dann eintragen, wenn ein Overlay-Textbox technisch keinen Sinn ergibt (z.B. weil der Slide-Typ keinen klassischen Titel hat). In allen anderen Faellen Koordinaten berechnen und als System-Textbox rendern – das ermoeglicht SSoT-Sync, Verschiebbarkeit und Editierbarkeit.

> ⚠️ `$skipTextboxes` ist ein Notfall-Mechanismus. Erweiterungen muessen gut begruendet sein.

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
| `prepareSlidesForView(array $slides, array $config = []): array` | Roh-Slides in einheitliches View-Format transformieren (System-Textbox-Merge, Images, Farben) |
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
| POST | `/{id}/export-pdf` | `presentation.export-pdf` | PDF-Export starten → `{ export_key }` |
| GET | `/{id}/export-pdf/status` | `presentation.export-pdf.status` | Job-Status abfragen (Polling) |
| GET | `/{id}/export-pdf/download` | `presentation.export-pdf.download` | Fertiges PDF herunterladen |
| GET | `/{id}/render` | `presentation.render` | Headless-Chrome Render (Token-basiert, kein Auth) |
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
    'enable_lookup_route' => true,
    'font_family'      => 'Plus Jakarta Sans',
    'font_url'         => 'https://fonts.googleapis.com/css2?family=...',
    'slide_width'      => 1280,
    'slide_height'     => 720,
    'accent_color'     => '#00AFCE',
    'brand_name'       => 'trafficdesign',
    'favicon'          => null,
    'vite_assets'      => ['resources/css/app.css', 'resources/js/presentation/bootstrap.js'],
    'allowed_html_tags' => ['a', 'b', 'strong', 'i', 'em', 'u'],
    'images' => [
        'disk' => 'public',
        'path' => 'presentation-images',
        'max_size' => 2048,
        'allowed_types' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
    ],

    // Standard-Koordinaten fuer System-Textboxen (Titel, Untertitel, Footer).
    // Alle Werte in Pixeln. Koennen in der Host-App-Config ueberschrieben werden.
    'textbox_positions' => [
        'slide_padding_x'        => 56,   // Entspricht .slide-inner padding
        'slide_padding_y'        => 48,

        // Allgemeine Defaults (Fallback fuer alle Typen)
        'default_title_x'        => 56,
        'default_title_y'        => 48,
        'default_subtitle_x'     => 56,
        'default_subtitle_y'     => 86,   // padding_y + Titelzeile (~34px) + Gap (4px)
        'default_footer_x'       => 56,
        'default_footer_y'       => 681,

        // Standard Content-Slides (divergence, summary, participants, etc.)
        'title_x'    => 56,  'title_y'    => 48,
        'subtitle_x' => 56,  'subtitle_y' => 86,

        // Perspektiv-Slides: Farbpunkt (16px) + Gap (12px) vor dem Titel
        'perspective_title_x'    => 84,   // 56 + 16 + 12
        'perspective_title_y'    => 48,
        'perspective_subtitle_x' => 56,
        'perspective_subtitle_y' => 86,

        // Reflexion-Slide: Icon (34px) + Gap (10px) vor dem Titel
        'reflection_title_x'    => 100,   // 56 + 34 + 10
        'reflection_title_y'    => 48,
        'reflection_subtitle_x' => 56,
        'reflection_subtitle_y' => 86,

        // Title-Slide (zentrierte Titelseite)
        'title_slide_title_x'    => 56,
        'title_slide_title_y'    => 330,
        'title_slide_subtitle_x' => 56,
        'title_slide_subtitle_y' => 385,

        // Footer + Freitext-Slide
        'footer_x'       => 56,
        'footer_y'       => 681,
        'footer_width'   => 500,
        'content_x'      => 56,
        'content_y'      => 128,
        'content_height' => 400,
    ],
];
```

**Wichtig:** Die `textbox_positions`-Werte muessen mit dem `.slide-inner`-Padding aus dem Package-CSS uebereinstimmen. Wenn du das Padding aenderst, passe `slide_padding_x/y` entsprechend an – sonst sind die Overlay-Textboxen verschoben.

---

## PDF-Export (Headless Chrome)

### Funktionsweise

Der PDF-Export laeuft **asynchron** – der Browser bekommt sofort eine Job-ID und pollt den Status. Ablauf:

1. `POST /presentations/{id}/export-pdf` → gibt `{ export_key: "..." }` zurueck (sofort)
2. `ExportPresentationPdf`-Job landet in der Queue
3. Queue-Worker startet den Job: `PdfExportService` erstellt Cache-Token + Render-URL
4. Node.js-Script (`scripts/export-pdf.js`) startet Headless Chrome
5. Chrome oeffnet die Token-URL (ohne Auth-Middleware, Token = Autorisierung)
6. Fuer jede Slide: Navigation via Alpine.js → Warten auf Chart-Rendering → Screenshot
7. Slides **mit Charts** bekommen einen kurzen Wait, Slides ohne Charts werden sofort gecaptured
8. Alle Screenshots werden via `pdf-lib` zu einem PDF zusammengefuegt
9. Status wird im Cache auf `ready` gesetzt
10. Browser pollt `GET /presentations/{id}/export-pdf/status?key=...` bis `ready`
11. Browser laedt PDF via `GET /presentations/{id}/export-pdf/download?key=...` herunter

### Vorteile gegenueber html2canvas

- **Pixel-perfekt**: Identisch mit der Browser-Darstellung
- **Fonts**: Chrome laedt alle Webfonts korrekt
- **Charts**: ApexCharts werden nativ gerendert (kein Canvas-Nachbau)
- **Kein Verrutschen**: Was man sieht, bekommt man
- **SVG-Support**: Vollstaendige SVG-Unterstuetzung

### Abhaengigkeiten

| Paket | Typ | Zweck |
|-------|-----|-------|
| `puppeteer` | npm | Headless Chrome Steuerung (PDF + PPTX) |
| `pdf-lib` | npm | PDF-Erstellung aus PNG-Screenshots |
| `pptxgenjs` | npm | Native .pptx-Erstellung |
| `libnss3` etc. | System (Debian) | Chrome-Laufzeitabhaengigkeiten |

### Konfiguration

In `config/presentation.php` unter `pdf_export`:

```php
'pdf_export' => [
    'node_binary'      => env('PRESENTATION_NODE_BINARY', 'node'),
    'chrome_path'      => env('PRESENTATION_CHROME_PATH'),        // null = Puppeteer-bundled Chrome
    'puppeteer_cache_dir' => env('PRESENTATION_PUPPETEER_CACHE_DIR'), // null = auto-detect
],
```

Der Service erkennt den Puppeteer-Cache automatisch an folgenden Orten (in dieser Reihenfolge):
1. `PRESENTATION_PUPPETEER_CACHE_DIR` aus `.env`
2. `storage/.puppeteer-cache/`
3. `.puppeteer-cache/` (App-Root)
4. `$HOME/.cache/puppeteer`
5. `/root/.cache/puppeteer`

### Render-Route (Token-basiert)

Die Route `GET /presentations/{id}/render` wird **nur** von Headless Chrome aufgerufen:
- Verwendet **keine** Auth-Middleware
- Validiert stattdessen einen einmaligen Cache-Token (`?token=xxx`)
- Der Token wird vom `PdfExportService` generiert und ist 5 Minuten gueltig
- Rendert die Praesentation im Export-Modus (keine Controls, keine Animationen)

### Troubleshooting

| Problem | Ursache | Loesung |
|---------|---------|---------|
| `Cannot find module 'puppeteer'` | npm-Pakete fehlen | `npm install puppeteer pdf-lib pptxgenjs` im App-Root ausfuehren |
| `Failed to launch the browser process` | System-Abhaengigkeiten fehlen oder Chrome-Cache nicht zugaenglich | System-Pakete installieren, Puppeteer-Cache fuer Webserver-User zugaenglich machen (siehe Installation) |
| `EACCES: permission denied` on `storage/app/temp/` | Verzeichnis gehoert root statt www-data | `chown -R www-data:www-data storage/app/temp` |
| Export bleibt bei "wird generiert..." haengen | Queue-Worker laeuft nicht oder Job schlaegt lautlos fehl | `php artisan queue:work` starten; `storage/logs/laravel.log` pruefen |
| Leere/weisse Slides im PDF | JS/CSS-Assets koennen nicht geladen werden | `APP_URL` korrekt setzen (muss von Headless Chrome erreichbar sein) |
| Token abgelaufen / 403 | Cache-Driver persistiert nicht | Cache-Driver auf `database`, `redis` oder `file` setzen (nicht `array`); Token ist 5 Min gueltig |
| Export dauert sehr lange | Viele Slides mit Charts | Normal bei 30-40 Slides (~30s); Queue-Worker-Timeout (`--timeout=300`) muss ausreichen |

---

## PowerPoint-Export (.pptx)

### Funktionsweise

Der PPTX-Export erzeugt native PowerPoint-Dateien mit einem **Hybrid-Ansatz**: Texte und Hintergruende werden als echte PowerPoint-Objekte erstellt (editierbar), waehrend Charts und komplexe Blade-Inhalte als hochaufloesende Bilder eingebettet werden.

| Element | PPTX-Umsetzung | Editierbar in PPT? |
|---------|---------------|-------------------|
| Hintergrund (dark/light) | Nativ (`slide.background`) | Ja |
| Title, Subtitle, Footer | Nativ (`addText()`) | Ja |
| User-Textboxen | Nativ (`addText()`) | Ja |
| User-Bilder | Nativ (`addImage()`) | Verschiebbar |
| ApexCharts | Puppeteer-Screenshot als Bild | Nein |
| Blade-Inhalte (Zitate, Statistik-Boxen) | Puppeteer-Screenshot als Bild | Nein |

### Ablauf

1. `PptxExportService` laedt Slides und bereitet ein JSON-Manifest vor (Textboxen, Positionen, Flags)
2. Node.js-Script (`export-pptx.js`) liest das Manifest
3. Fuer Chart-/Blade-Slides: Puppeteer oeffnet die Render-URL und screenshottet den Content-Bereich
4. PptxGenJS erstellt native Slides: Hintergrund, Textboxen als `addText()`, Screenshots/Bilder als `addImage()`
5. Slide-Format: **Widescreen 16:9** (13.33" × 7.5"), passend zum 1280×720px Layout

### Installation

Alle npm-Abhaengigkeiten werden einmalig beim Setup installiert (siehe oben). `pptxgenjs` ist bereits im gemeinsamen `npm install`-Befehl enthalten. Puppeteer und System-Abhaengigkeiten (Headless Chrome) werden mit dem PDF-Export geteilt – kein separater Setup-Schritt noetig.

### Routes

| Method | Route | Name | Beschreibung |
|--------|-------|------|-------------|
| POST | `/{id}/export-pptx` | `presentation.export-pptx` | Export starten → `{ export_key }` |
| GET | `/{id}/export-pptx/status` | `presentation.export-pptx.status` | Status abfragen (Polling) |
| GET | `/{id}/export-pptx/download` | `presentation.export-pptx.download` | Fertige .pptx herunterladen |

### Hinweise

- **Font:** `config('presentation.font_family')` wird als `fontFace` in PptxGenJS gesetzt. Der Font muss auf dem Zielrechner installiert sein oder PowerPoint nutzt einen Fallback.
- **Farben:** Hex-Werte werden automatisch konvertiert (PptxGenJS nutzt Hex ohne `#`).
- **HTML in Textboxen:** `<br>`-Tags werden zu Zeilenumbruechen konvertiert, alle anderen Tags werden gestrippt.

---

## Events

Das Package dispatcht Events bei Slide-Aenderungen, auf die die Host-App optional reagieren kann. Damit laesst sich z.B. eine externe Slide-Konfiguration als Single Source of Truth (SSoT) implementieren, die User-Anpassungen (Theme, Fonts, Textboxen, Inhalt) bei Regenerierung bewahrt.

### Verfuegbare Events

| Event | Ausgeloest bei | Payload |
|-------|---------------|---------|
| `SlideAdded` | Text-Slide hinzugefuegt (`addTextSlide`) | `$presentation`, `$slide` (Array mit id, type, title, theme, source), `$position` |
| `SlideRemoved` | Slide entfernt (`removeSlide`) | `$presentation`, `$slideId` |
| `SlidesSaved` | Slides gespeichert (`saveSlides`) | `$presentation`, `$slides` (vollstaendiges Array aller Slides) |

Alle Events liegen im Namespace `Trafficdesign\Presentation\Events`.

### Listener registrieren (Host-App)

In Laravel 11+ im `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Event;
use Trafficdesign\Presentation\Events\SlideAdded;
use Trafficdesign\Presentation\Events\SlideRemoved;
use Trafficdesign\Presentation\Events\SlidesSaved;

public function boot(): void
{
    Event::listen(SlideAdded::class, [MySlideListener::class, 'handleSlideAdded']);
    Event::listen(SlideRemoved::class, [MySlideListener::class, 'handleSlideRemoved']);
    Event::listen(SlidesSaved::class, [MySlideListener::class, 'handleSlidesSaved']);
}
```

### Beispiel-Listener

```php
use Trafficdesign\Presentation\Events\SlideAdded;
use Trafficdesign\Presentation\Events\SlideRemoved;
use Trafficdesign\Presentation\Events\SlidesSaved;

class MySlideListener
{
    public function handleSlideAdded(SlideAdded $event): void
    {
        // $event->presentation  – Presentation Model
        // $event->slide          – Array: ['id' => 'custom-xxx', 'type' => 'text', 'title' => '...', ...]
        // $event->position       – int|null: Position an der eingefuegt wurde
    }

    public function handleSlideRemoved(SlideRemoved $event): void
    {
        // $event->presentation
        // $event->slideId       – string: ID der entfernten Slide
    }

    public function handleSlidesSaved(SlidesSaved $event): void
    {
        // $event->presentation
        // $event->slides        – array: Vollstaendige Slide-Liste nach dem Speichern
        //                         Jede Slide hat: id, type, title, theme, source, textboxes, ...
    }
}
```

**Hinweis:** Ohne registrierte Listener passiert nichts – die Events sind rein optional.

### SSoT-Vertrag

Das Package ist SSoT-agnostisch: **jede Host-App kann als SSoT fungieren**, solange sie die folgenden Verantwortlichkeiten uebernimmt.

---

#### Seite 1: Was die SSoT dem Package liefern muss

Die Host-App implementiert `DataCollectorInterface` und `SlideBuilderInterface`. Zusammen bilden sie die **vollstaendige Schnittstelle der SSoT gegenueber dem Package**.

```
SSoT (Host-App)
    │
    ├── DataCollectorInterface::collectData(Model $subject): array
    │       → Liefert alle Rohdaten (Statistiken, Texte, Metadaten)
    │       → Wird bei generateAndSave() und regenerate() aufgerufen
    │
    ├── SlideBuilderInterface::buildSlides(Model $subject, array $data): array
    │       → Liefert das komplette Slide-Array
    │       → Jeder Slide MUSS folgende Felder enthalten:
    │           - id:       string  – eindeutige Slide-ID
    │           - type:     string  – bestimmt die Blade-Komponente
    │           - theme:    string  – 'dark' | 'light'
    │           - title:    string  – Haupttitel (SSoT-Wahrheit fuer System-Textboxen)
    │           - subtitle: string  – Untertitel (optional)
    │           - footer:   string  – Footer (optional)
    │           - source:   string  – 'generated' | 'user'
    │           - data:     array   – Slide-typ-spezifische Daten
    │
    └── DataCollectorInterface::resolveTitle(Model $subject): string
            → Anzeige-Titel der Praesentation
```

**Das `source`-Feld ist entscheidend:**
- `"generated"` – Slide wird bei `regenerate()` durch frisch generierte Daten ersetzt
- `"user"` – Slide bleibt bei `regenerate()` erhalten (z.B. manuell hinzugefuegte Text-Slides)

Wenn der SlideBuilder User-Slides bereits aus der SSoT liefert (z.B. gespeicherte Freitext-Folien), werden sie nicht doppelt angehaengt. Wenn er sie nicht liefert, haengt `regenerate()` die User-Slides aus dem alten Snapshot als Fallback an.

---

#### Seite 2: Was das Package an die SSoT zurueckmeldet

Das Package dispatcht nach dem Speichern das `SlidesSaved`-Event. Die Host-App **soll** dort einen Listener registrieren, der die User-Anpassungen in die eigene SSoT zurueckschreibt:

```
Package                              SSoT (Host-App)
    │                                      │
    │  SlidesSaved($presentation, $slides) │
    │ ────────────────────────────────────>│
    │                                      │
    │                                      ├── Fuer jeden Slide extrahieren:
    │                                      │     textboxes   (Positionen, Texte)
    │                                      │     images      (hochgeladene Bilder)
    │                                      │     fontOverrides
    │                                      │     theme       (falls geaendert)
    │                                      │     title/subtitle (bei user-Slides)
    │                                      │
    │                                      └── Als "overrides" in eigener Config speichern
```

Beim naechsten `buildSlides()`-Aufruf liest der SlideBuilder diese Overrides und wendet sie auf die frisch generierten Slides an. So bleiben User-Anpassungen auch nach `regenerate()` erhalten.

**Override-Merge im SlideBuilder (Beispiel):**

```php
public function buildSlides(Model $subject, array $data): array
{
    $slides = $this->buildGeneratedSlides($subject, $data);
    $overrides = $subject->presentationConfig?->overrides ?? [];

    return collect($slides)->map(function (array $slide) use ($overrides) {
        $saved = $overrides[$slide['id']] ?? null;
        if (! $saved) {
            return $slide;
        }

        return array_merge($slide, array_filter([
            'theme' => $saved['theme'] ?? null,
            'textboxes' => $saved['textboxes'] ?? null,
            'images' => $saved['images'] ?? null,
            'fontOverrides' => $saved['fontOverrides'] ?? null,
        ], fn ($v) => $v !== null));
    })->values()->all();
}
```

**`report_data` vs `slide['data']`:** `DataCollector::collectData()` liefert das gesamte Report-Array. Das Package speichert es als `report_data` am Presentation-Model (ein Snapshot fuer die ganze Praesentation). `slide['data']` enthaelt nur die typ-spezifischen Werte einer einzelnen Folie (z. B. Chart-Serien, KPIs). Der SlideBuilder mappt aus `$data` in die passenden `slide['data']`-Felder.

**Chart-Rendering:** Chart-Serien gehoeren in `slide['data']`. Das Rendern (ApexCharts, Chart.js) ist **Host-JavaScript**, kein Package-Pflichtfeature. Das Package stellt nur den Hook `renderChartsForSlide` bereit.

---

#### Seite 3: Staleness-Erkennung (optional, empfohlen)

Das Package selbst prueft nicht ob der Snapshot veraltet ist – das ist Aufgabe der Host-App (im Bridge-Controller). Empfohlenes Muster:

```php
private function isStale(Model $subject, Presentation $presentation): bool
{
    $presUpdated  = $presentation->updated_at?->timestamp ?? 0;
    $ssotUpdated  = $subject->config?->updated_at?->timestamp ?? 0;

    return $ssotUpdated > $presUpdated;
}

// Verwendung:
if (!$presentation) {
    $presentation = $engine->createPresentation($name, $subject, $user);
} elseif ($this->isStale($subject, $presentation)) {
    $engine->regenerate($subject, $presentation);  // nicht-destruktiv
}
```

`regenerate()` ist **nicht-destruktiv**: User-Slides und Overrides bleiben erhalten, weil der SlideBuilder sie aus der SSoT einliest (oder als Fallback aus dem Snapshot).

---

#### Zusammenfassung: Pflichten der Host-App als SSoT

| Pflicht | Wo | Typ |
|---------|----|-----|
| `DataCollectorInterface` implementieren | ServiceProvider | **Pflicht** |
| `SlideBuilderInterface` implementieren | ServiceProvider | **Pflicht** |
| `AuthorizerInterface` implementieren | ServiceProvider | **Pflicht** |
| `SlidesSaved` Listener registrieren | ServiceProvider | Empfohlen |
| Staleness-Check im Bridge-Controller | Host-Controller | Empfohlen |
| User-Anpassungen in SSoT persistieren | SlidesSaved-Listener | Empfohlen |

---

## Lizenz

proprietary (siehe `composer.json`)
