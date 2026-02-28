<?php

namespace Trafficdesign\Presentation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
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
            'title' => $slideData['title'] ?? '',
            'subtitle' => $slideData['subtitle'] ?? '',
            'content' => $slideData['content'] ?? '',
            'footer' => $slideData['footer'] ?? '',
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

            // Extract text from system textboxes back into slide fields
            $persistTextboxes = [];
            foreach ($textboxes as $tb) {
                if (($tb['source'] ?? '') === 'system' && ! empty($tb['role'])) {
                    $field = $tb['role'];
                    if (in_array($field, ['title', 'subtitle', 'footer', 'content'], true)) {
                        $base[$field] = $tb['text'] ?? '';
                    }
                    // Save position/style overrides for system textboxes
                    $persistTextboxes[] = $tb;
                } else {
                    $persistTextboxes[] = $tb;
                }
            }

            $base['textboxes'] = $persistTextboxes;
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
