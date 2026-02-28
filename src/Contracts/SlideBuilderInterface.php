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
     * Jeder Slide ist ein Array mit folgender Struktur:
     * - id: string (eindeutiger Identifier, z.B. 'title', 'summary')
     * - type: string (bestimmt die Blade-Component, z.B. 'title', 'chart-bar')
     * - theme: string ('dark' oder 'light')
     * - title: string (Überschrift, editierbar)
     * - subtitle: string (Untertitel, editierbar)
     * - footer: string (Footer-Text, optional)
     * - data: array (Slide-typ-spezifische Daten)
     *
     * @return array<int, array{id: string, type: string, theme: string, title: string, subtitle: string, data: array}>
     */
    public function buildSlides(Model $subject, array $data): array;
}
