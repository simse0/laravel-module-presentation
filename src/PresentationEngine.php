<?php

namespace Trafficdesign\Presentation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Trafficdesign\Presentation\Contracts\DataCollectorInterface;
use Trafficdesign\Presentation\Contracts\SlideBuilderInterface;
use Trafficdesign\Presentation\Events\SlideAdded;
use Trafficdesign\Presentation\Events\SlideRemoved;
use Trafficdesign\Presentation\Events\SlidesSaved;
use Trafficdesign\Presentation\Models\Presentation;

/**
 * Zentraler Service fuer das Praesentationsmodul.
 *
 * Orchestriert Daten-Sammlung, Slide-Erzeugung, Snapshot-Persistenz und Slide-Verwaltung.
 * Komplett unabhaengig vom Datenmodell der Host-App.
 */
class PresentationEngine
{
    public function __construct(
        private SlideBuilderInterface $slideBuilder,
        private DataCollectorInterface $dataCollector,
    ) {}

    /**
     * Praesentation per eindeutigem Namen finden.
     */
    public function findByName(string $name): ?Presentation
    {
        return Presentation::byName($name)->first();
    }

    /**
     * Neue Praesentation erstellen, Slides generieren und als Snapshot speichern.
     */
    public function createPresentation(string $name, Model $subject, Authenticatable|Model $user): Presentation
    {
        $presentation = Presentation::create([
            'name' => $name,
            'presentable_type' => get_class($subject),
            'presentable_id' => $subject->getKey(),
            'user_id' => $user->getKey(),
            'title' => $this->dataCollector->resolveTitle($subject),
            'slide_order' => null,
            'text_overrides' => [],
            'settings' => [],
        ]);

        $this->generateAndSave($subject, $presentation);

        return $presentation->fresh();
    }

    /**
     * Slides generieren und als Snapshot in der DB speichern.
     */
    public function generateAndSave(Model $subject, Presentation $presentation): array
    {
        $data = $this->dataCollector->collectData($subject);
        $slides = $this->slideBuilder->buildSlides($subject, $data);

        $slides = array_map(function (array $slide) {
            $slide['source'] = $slide['source'] ?? 'generated';
            return $slide;
        }, $slides);

        $serializable = $this->makeSerializable($slides);
        $serializableData = $this->makeSerializable($data);

        $presentation->update([
            'slides_data' => $serializable,
            'report_data' => $serializableData,
        ]);

        return [
            'slides' => $serializable,
            'reportData' => $serializableData,
        ];
    }

    /**
     * Slides + ReportData aus dem gespeicherten Snapshot laden.
     */
    public function loadFromSnapshot(Presentation $presentation): array
    {
        return [
            'slides' => $presentation->getSlides(),
            'reportData' => $presentation->getReportData(),
        ];
    }

    /**
     * Slides komplett neu generieren und Snapshot ueberschreiben.
     *
     * Wenn der SlideBuilder User-Slides bereits liefert (z.B. aus einer SSoT),
     * werden sie nicht dupliziert. Andernfalls werden User-Slides aus dem alten
     * Snapshot als Fallback ans Ende angehaengt.
     */
    public function regenerate(Model $subject, Presentation $presentation): array
    {
        $existingSlides = $presentation->getSlides() ?: [];
        $userSlides = array_filter($existingSlides, fn ($s) => ($s['source'] ?? 'generated') === 'user');

        $result = $this->generateAndSave($subject, $presentation);

        if (! empty($userSlides)) {
            $resultIds = array_column($result['slides'], 'id');
            $missing = array_filter($userSlides, fn ($s) => ! in_array($s['id'] ?? '', $resultIds, true));

            if (! empty($missing)) {
                $merged = array_merge($result['slides'], array_values($missing));
                $presentation->update(['slides_data' => $merged]);
                $result['slides'] = $merged;
            }
        }

        $presentation->update(['title' => $this->dataCollector->resolveTitle($subject)]);

        return $result;
    }

    /**
     * Text-Slide an einer bestimmten Position einfuegen.
     */
    public function addTextSlide(Presentation $presentation, array $slideData, ?int $position = null): array
    {
        $slides = $presentation->getSlides();

        $newSlide = [
            'id' => 'custom-' . Str::random(8),
            'type' => 'text',
            'theme' => $slideData['theme'] ?? 'light',
            'title' => $this->sanitizeText($slideData['title'] ?? ''),
            'subtitle' => $this->sanitizeText($slideData['subtitle'] ?? ''),
            'content' => $this->sanitizeText($slideData['content'] ?? ''),
            'footer' => $this->sanitizeText($slideData['footer'] ?? ''),
            'data' => [],
            'source' => 'user',
        ];

        if ($position !== null && $position >= 0 && $position <= count($slides)) {
            array_splice($slides, $position, 0, [$newSlide]);
        } else {
            $slides[] = $newSlide;
        }

        $presentation->update(['slides_data' => $slides]);

        event(new SlideAdded($presentation, $newSlide, $position));

        return $slides;
    }

    /**
     * Bild auf den konfigurierten Disk hochladen und Metadaten zurueckgeben.
     *
     * @return array{id: string, url: string, filename: string, disk_path: string}
     */
    public function storeImage(Presentation $presentation, UploadedFile $file): array
    {
        $disk = config('presentation.images.disk', 'public');
        $basePath = config('presentation.images.path', 'presentation-images');
        $directory = $basePath . '/' . $presentation->id;

        PresentationServiceProvider::ensureImageDirectoryExists();

        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $storedName = Str::random(16) . '.' . $extension;
        $storedPath = $file->storeAs($directory, $storedName, $disk);

        return [
            'id' => 'img-' . Str::random(8),
            'url' => Storage::disk($disk)->url($storedPath),
            'filename' => $file->getClientOriginalName(),
            'disk_path' => $storedPath,
        ];
    }

    /**
     * Bild-Datei vom Disk loeschen.
     */
    public function deleteImageFile(string $diskPath): void
    {
        $disk = config('presentation.images.disk', 'public');
        Storage::disk($disk)->delete($diskPath);
    }

    /**
     * Alle Bild-Dateien einer Praesentation vom Disk loeschen.
     */
    public function deleteAllImages(Presentation $presentation): void
    {
        $disk = config('presentation.images.disk', 'public');
        $basePath = config('presentation.images.path', 'presentation-images');
        $directory = $basePath . '/' . $presentation->id;

        Storage::disk($disk)->deleteDirectory($directory);
    }

    /**
     * Slide entfernen.
     */
    public function removeSlide(Presentation $presentation, string $slideId): array
    {
        $slides = array_values(array_filter(
            $presentation->getSlides(),
            fn ($s) => $s['id'] !== $slideId
        ));

        $presentation->update(['slides_data' => $slides]);

        event(new SlideRemoved($presentation, $slideId));

        return $slides;
    }

    /**
     * Editierbare Slide-Daten mergen (Textboxen, Font-Overrides, Reihenfolge).
     * Bewahrt die originalen Slide-Inhalte (data, charts etc.) und aktualisiert nur die editierbaren Felder.
     */
    public function saveSlides(Presentation $presentation, array $incomingSlides): void
    {
        $existing = $presentation->getSlides();
        $existingById = [];
        foreach ($existing as $slide) {
            if (isset($slide['id'])) {
                $existingById[$slide['id']] = $slide;
            }
        }

        $merged = [];
        foreach ($incomingSlides as $incoming) {
            $id = $incoming['id'] ?? null;

            if ($id && isset($existingById[$id])) {
                $base = $existingById[$id];
            } else {
                $base = $incoming;
            }

            $textboxes = $incoming['textboxes'] ?? ($base['textboxes'] ?? []);

            $persistTextboxes = [];
            foreach ($textboxes as $tb) {
                if (isset($tb['text'])) {
                    $tb['text'] = $this->sanitizeText($tb['text']);
                }

                if (($tb['source'] ?? '') === 'system' && ! empty($tb['role'])) {
                    $field = $tb['role'];
                    if (in_array($field, ['title', 'subtitle', 'footer', 'content'], true)) {
                        $base[$field] = $tb['text'] ?? '';
                    }
                    $persistTextboxes[] = $tb;
                } else {
                    $persistTextboxes[] = $tb;
                }
            }

            $base['textboxes'] = $persistTextboxes;
            $base['images'] = $incoming['images'] ?? ($base['images'] ?? []);
            $base['fontOverrides'] = $incoming['fontOverrides'] ?? ($base['fontOverrides'] ?? []);

            if (isset($incoming['theme'])) {
                $base['theme'] = $incoming['theme'];
            }

            $merged[] = $base;
        }

        $merged = $this->makeSerializable($merged);
        $presentation->update(['slides_data' => $merged]);

        event(new SlidesSaved($presentation, $merged));

        // Clear text_overrides that are now managed by the textbox system
        $overrides = $presentation->text_overrides ?? [];
        if (! empty($overrides)) {
            $changed = false;
            foreach ($merged as $slide) {
                $prefix = $slide['id'] ?? '';
                foreach (['title', 'subtitle', 'footer', 'content'] as $field) {
                    $key = $prefix . '.' . $field;
                    if (isset($overrides[$key])) {
                        unset($overrides[$key]);
                        $changed = true;
                    }
                }
            }
            if ($changed) {
                $presentation->update(['text_overrides' => empty($overrides) ? null : $overrides]);
            }
        }
    }

    // --- Legacy-Methoden (Abwaertskompatibilitaet) ---

    /**
     * @deprecated Verwende createPresentation() stattdessen.
     */
    public function getOrCreate(Model $subject, Authenticatable|Model $user): Presentation
    {
        $name = strtolower(class_basename($subject)) . '-' . $subject->getKey();

        $existing = $this->findByName($name);
        if ($existing) {
            return $existing;
        }

        return $this->createPresentation($name, $subject, $user);
    }

    /**
     * @deprecated Verwende generateAndSave() oder loadFromSnapshot() stattdessen.
     */
    public function buildPresentation(Model $subject): array
    {
        $data = $this->dataCollector->collectData($subject);
        $slides = $this->slideBuilder->buildSlides($subject, $data);

        return [
            'slides' => $slides,
            'data' => $data,
        ];
    }

    public function applyOverrides(array $slides, ?Presentation $presentation): array
    {
        if (! $presentation || empty($presentation->text_overrides)) {
            return $slides;
        }

        $overrides = $presentation->text_overrides;

        return array_map(function (array $slide) use ($overrides) {
            $prefix = $slide['id'];
            foreach (['title', 'subtitle', 'footer'] as $field) {
                $key = $prefix . '.' . $field;
                if (isset($overrides[$key])) {
                    $slide[$field] = $overrides[$key];
                }
            }
            return $slide;
        }, $slides);
    }

    public function applySlideOrder(array $slides, ?Presentation $presentation): array
    {
        if (! $presentation || $presentation->slide_order === null) {
            return $slides;
        }

        $indexed = collect($slides)->keyBy('id');
        $ordered = [];
        foreach ($presentation->slide_order as $slideId) {
            if ($indexed->has($slideId)) {
                $ordered[] = $indexed->get($slideId);
            }
        }
        return $ordered;
    }

    public function saveTextOverrides(Presentation $presentation, array $overrides): Presentation
    {
        $existing = $presentation->text_overrides ?? [];
        $merged = array_filter(
            array_merge($existing, $overrides),
            fn ($val) => $val !== null && $val !== ''
        );

        $presentation->update(['text_overrides' => $merged]);
        return $presentation;
    }

    public function saveSlideOrder(Presentation $presentation, array $slideIds): Presentation
    {
        $presentation->update(['slide_order' => $slideIds]);
        return $presentation;
    }

    /**
     * Bereitet alle Slides fuer die View-Ausgabe auf (Editor + Praesentation).
     *
     * Erzeugt System-Textboxen aus Slide-Feldern, merged sie mit gespeicherten
     * Textboxen und reicht alle Felder (inkl. images) einheitlich durch.
     * Damit gibt es nur eine einzige Transformation fuer beide Modi.
     *
     * @param  array<int, array<string, mixed>>  $slides  Roh-Slides aus dem Snapshot
     * @param  array<string, mixed>  $config  Presentation-Config (slide_width, slide_height, etc.)
     * @return array<int, array<string, mixed>>
     */
    public function prepareSlidesForView(array $slides, array $config = []): array
    {
        return array_values(array_map(
            fn (array $slide) => $this->prepareSlideForView($slide, $config),
            $slides
        ));
    }

    /**
     * Einzelnen Slide fuer die View aufbereiten.
     *
     * @param  array<string, mixed>  $slide
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function prepareSlideForView(array $slide, array $config): array
    {
        $isDark = ($slide['theme'] ?? 'dark') === 'dark';
        $titleColor = $isDark ? '#ffffff' : '#1a1a2e';
        $subtitleColor = $isDark ? '#9CA3AF' : '#6B7280';
        $footerColor = $isDark ? '#6B7280' : '#9CA3AF';
        $type = $slide['type'] ?? '';

        $textElements = [];

        // ⚠️ $skipTextboxes: NUR im absoluten Ausnahmefall erweitern!
        // Verhindert SSoT-Sync, Overlay-Editierbarkeit und Verschiebbarkeit komplett.
        // Nur fuer Slide-Typen, bei denen ein Standard-Titel-Overlay technisch keinen Sinn ergibt.
        // Stattdessen: Koordinaten berechnen und System-Textbox generieren (s. $isPerspective/$isReflection unten).
        $skipTextboxes = in_array($type, ['perspective-cover', 'agenda']);

        $isCenter = $type === 'title';
        $isPerspective = in_array($type, ['perspective', 'perspective-focus', 'perspective-quotes']);
        $isReflection = $type === 'reflection';

        $pos = config('presentation.textbox_positions', []);
        $slideWidth = config('presentation.slide_width', 1280);
        $padX = $pos['slide_padding_x'] ?? 56;

        $defTitleX = $pos['default_title_x'] ?? $padX;
        $defTitleY = $pos['default_title_y'] ?? 48;
        $defSubX = $pos['default_subtitle_x'] ?? $padX;
        $defSubY = $pos['default_subtitle_y'] ?? 86;

        if ($isCenter) {
            $titleX = $pos['title_slide_title_x'] ?? $defTitleX;
            $titleY = $pos['title_slide_title_y'] ?? 330;
            $subX = $pos['title_slide_subtitle_x'] ?? $defSubX;
            $subY = $pos['title_slide_subtitle_y'] ?? 385;
        } elseif ($isPerspective) {
            $titleX = $pos['perspective_title_x'] ?? 84;
            $titleY = $pos['perspective_title_y'] ?? $defTitleY;
            $subX = $pos['perspective_subtitle_x'] ?? $defSubX;
            $subY = $pos['perspective_subtitle_y'] ?? $defSubY;
        } elseif ($isReflection) {
            $titleX = $pos['reflection_title_x'] ?? 100;
            $titleY = $pos['reflection_title_y'] ?? $defTitleY;
            $subX = $pos['reflection_subtitle_x'] ?? $defSubX;
            $subY = $pos['reflection_subtitle_y'] ?? $defSubY;
        } else {
            $titleX = $pos['title_x'] ?? $defTitleX;
            $titleY = $pos['title_y'] ?? $defTitleY;
            $subX = $pos['subtitle_x'] ?? $defSubX;
            $subY = $pos['subtitle_y'] ?? $defSubY;
        }

        $footerX = $pos['footer_x'] ?? ($pos['default_footer_x'] ?? $padX);
        $footerY = $pos['footer_y'] ?? 681;
        $footerWidth = $pos['footer_width'] ?? 500;
        $contentX = $pos['content_x'] ?? $padX;
        $contentY = $pos['content_y'] ?? 128;
        $contentHeight = $pos['content_height'] ?? 400;
        $fullWidth = $slideWidth - ($padX * 2);

        $titleWidth = $isPerspective ? ($slideWidth - $titleX - $padX)
            : ($isReflection ? ($slideWidth - $titleX - $padX)
            : $fullWidth);

        if (! $skipTextboxes && ! empty($slide['title'] ?? '')) {
            $textElements[] = [
                'id' => $slide['id'] . '__title',
                'role' => 'title',
                'source' => 'system',
                'text' => $slide['title'],
                'x' => $titleX, 'y' => $titleY,
                'width' => $titleWidth, 'height' => null,
                'fontSize' => $isCenter ? 42 : 28,
                'fontWeight' => 800,
                'color' => $titleColor,
                'align' => $isCenter ? 'center' : 'left',
            ];
        }

        if (! $skipTextboxes && ! empty($slide['subtitle'] ?? '')) {
            $textElements[] = [
                'id' => $slide['id'] . '__subtitle',
                'role' => 'subtitle',
                'source' => 'system',
                'text' => $slide['subtitle'],
                'x' => $subX, 'y' => $subY,
                'width' => $fullWidth, 'height' => null,
                'fontSize' => $isCenter ? 18 : 15,
                'fontWeight' => 500,
                'color' => $subtitleColor,
                'align' => $isCenter ? 'center' : 'left',
            ];
        }

        if (! empty($slide['footer'] ?? '')) {
            $textElements[] = [
                'id' => $slide['id'] . '__footer',
                'role' => 'footer',
                'source' => 'system',
                'text' => $slide['footer'],
                'x' => $footerX, 'y' => $footerY,
                'width' => $footerWidth, 'height' => null,
                'fontSize' => 11, 'fontWeight' => 400,
                'color' => $footerColor, 'align' => 'left',
            ];
        }

        if (($slide['type'] ?? '') === 'text' && array_key_exists('content', $slide)) {
            $textElements[] = [
                'id' => $slide['id'] . '__content',
                'role' => 'content',
                'source' => 'system',
                'text' => $slide['content'] ?? '',
                'x' => $contentX, 'y' => $contentY,
                'width' => $fullWidth, 'height' => $contentHeight,
                'fontSize' => 16, 'fontWeight' => 400,
                'color' => $isDark ? '#D1D5DB' : '#374151',
                'align' => 'left',
            ];
        }

        $savedTextboxes = $slide['textboxes'] ?? [];
        $savedById = [];
        foreach ($savedTextboxes as $tb) {
            if (isset($tb['id'])) {
                $savedById[$tb['id']] = $tb;
            }
        }

        $merged = [];
        foreach ($textElements as $te) {
            if (isset($savedById[$te['id']])) {
                $saved = $savedById[$te['id']];

                // System textbox text always comes from SSoT, positions from saved
                if (($te['source'] ?? '') === 'system') {
                    $saved['text'] = $te['text'];
                }

                $merged[] = array_merge($te, $saved);
                unset($savedById[$te['id']]);
            } else {
                $merged[] = $te;
            }
        }
        foreach ($savedById as $tb) {
            if (! isset($tb['source'])) {
                $tb['source'] = 'user';
            }
            $merged[] = $tb;
        }

        return [
            'id' => $slide['id'],
            'type' => $slide['type'],
            'title' => $slide['title'] ?? '',
            'theme' => $slide['theme'] ?? 'dark',
            'source' => $slide['source'] ?? 'generated',
            'textboxes' => $merged,
            'images' => $slide['images'] ?? [],
            'fontOverrides' => $slide['fontOverrides'] ?? [],
        ];
    }

    /**
     * Entfernt nicht-erlaubte HTML-Tags, bereinigt Attribute bei erlaubten Tags,
     * dekodiert HTML-Entities ausserhalb von Tags zu UTF-8 und bewahrt
     * Zeilenumbrueche als <br>-Tags.
     */
    private function sanitizeText(string $text): string
    {
        $allowedTags = config('presentation.allowed_html_tags', []);

        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/(?:div|p)>/i', "\n", $text);

        $tagString = implode('', array_map(fn (string $tag) => '<' . $tag . '>', $allowedTags));
        $text = strip_tags($text, $tagString);

        if (in_array('a', $allowedTags, true)) {
            $text = preg_replace_callback(
                '/<a\s[^>]*>/i',
                function (array $match): string {
                    if (preg_match('/href\s*=\s*"([^"]*)"/i', $match[0], $hrefMatch)) {
                        $href = $hrefMatch[1];

                        if (preg_match('/^\s*javascript\s*:/i', $href)) {
                            return '<a href="#">';
                        }

                        return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">';
                    }

                    return '<a href="#">';
                },
                $text
            ) ?? $text;
        }

        $text = $this->decodeEntitiesOutsideTags($text);

        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
        $text = str_replace("\n", '<br>', $text);
        $text = (string) preg_replace('/^(<br>)+|(<br>)+$/i', '', $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Dekodiert HTML-Entities nur in Text-Segmenten (ausserhalb von HTML-Tags),
     * damit href-Werte und Tag-Attribute intakt bleiben.
     */
    private function decodeEntitiesOutsideTags(string $text): string
    {
        if (! str_contains($text, '<')) {
            return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return (string) preg_replace_callback(
            '/(?<=>)[^<]+|^[^<]+/',
            fn (array $m) => html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $text
        );
    }

    /**
     * Konvertiert Collections/Models rekursiv in Arrays fuer JSON-Serialisierung.
     */
    private function makeSerializable(mixed $data): mixed
    {
        if ($data instanceof \Illuminate\Support\Collection) {
            return $data->map(fn ($item) => $this->makeSerializable($item))->toArray();
        }

        if ($data instanceof Model) {
            return $data->toArray();
        }

        if (is_array($data)) {
            return array_map(fn ($item) => $this->makeSerializable($item), $data);
        }

        return $data;
    }
}
