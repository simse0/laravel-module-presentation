<?php

namespace Trafficdesign\Presentation;

use Illuminate\Database\Eloquent\Model;
use Trafficdesign\Presentation\Contracts\DataCollectorInterface;
use Trafficdesign\Presentation\Contracts\SlideBuilderInterface;
use Trafficdesign\Presentation\Models\Presentation;

/**
 * Zentraler Service für das Präsentationsmodul.
 *
 * Orchestriert Daten-Sammlung, Slide-Erzeugung, Override-Anwendung und Persistenz.
 * Komplett unabhängig vom Datenmodell der Host-App.
 */
class PresentationEngine
{
    public function __construct(
        private SlideBuilderInterface $slideBuilder,
        private DataCollectorInterface $dataCollector,
    ) {}

    /**
     * Präsentation für ein Subject laden oder neu erstellen.
     */
    public function getOrCreate(Model $subject, $user): Presentation
    {
        return Presentation::firstOrCreate(
            [
                'presentable_type' => get_class($subject),
                'presentable_id' => $subject->getKey(),
                'user_id' => $user->getKey(),
            ],
            [
                'title' => $this->dataCollector->resolveTitle($subject),
                'slide_order' => null,
                'text_overrides' => [],
                'settings' => [],
            ]
        );
    }

    /**
     * Daten sammeln und Slides bauen.
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

    /**
     * Overrides auf Slides anwenden.
     */
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

    /**
     * Slide-Reihenfolge anwenden.
     */
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

    /**
     * Text-Overrides speichern.
     */
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

    /**
     * Slide-Reihenfolge speichern.
     */
    public function saveSlideOrder(Presentation $presentation, array $slideIds): Presentation
    {
        $presentation->update(['slide_order' => $slideIds]);
        return $presentation;
    }
}
