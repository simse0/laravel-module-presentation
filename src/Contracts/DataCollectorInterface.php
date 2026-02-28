<?php

namespace Trafficdesign\Presentation\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Sammelt die Daten, die für die Slide-Erzeugung benötigt werden.
 *
 * Jede Host-App implementiert dieses Interface, um ihre eigene
 * Datenbank-Struktur abzufragen und aufzubereiten.
 */
interface DataCollectorInterface
{
    /**
     * Alle Daten sammeln, die der SlideBuilder braucht.
     *
     * @param  Model  $subject  Das Hauptobjekt (z.B. Feedback, Report, etc.)
     * @return array Assoziatives Array mit allen benötigten Daten
     */
    public function collectData(Model $subject): array;

    /**
     * Einen lesbaren Titel für die Präsentation generieren.
     */
    public function resolveTitle(Model $subject): string;
}
