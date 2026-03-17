<?php

namespace Trafficdesign\Presentation\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Definiert, welche Slides für ein bestimmtes Subject erzeugt werden.
 *
 * Jede Host-App implementiert dieses Interface, um ihre eigenen
 * Slide-Typen und Daten-Zuordnungen zu definieren.
 */
interface SlideBuilderInterface
{
    /**
     * Slide-Definitionen aus den gesammelten Daten erzeugen.
     *
     * Pflichtfelder pro Slide:
     * - id:       string – eindeutiger Identifier (z.B. 'title', 'summary')
     * - type:     string – bestimmt die Blade-Component (z.B. 'title', 'chart-bar')
     * - theme:    string – 'dark' oder 'light'
     * - title:    string – Haupttitel (SSoT-Wahrheit; System-Textboxen lesen immer diesen Wert)
     * - subtitle: string – Untertitel (optional)
     * - footer:   string – Footer-Text (optional)
     * - source:   string – 'generated' | 'user'
     *                      'generated': wird bei regenerate() durch neue Daten ersetzt
     *                      'user':      bleibt bei regenerate() erhalten
     * - data:     array  – Slide-typ-spezifische Daten fuer Charts/Statistiken
     *
     * @return array<int, array{id: string, type: string, theme: string, title: string, subtitle: string, source: string, data: array}>
     */
    public function buildSlides(Model $subject, array $data): array;
}
