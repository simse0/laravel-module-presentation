<?php

namespace Trafficdesign\Presentation\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Autorisierungsprüfung für Präsentationszugriff.
 *
 * Die Host-App bestimmt, wer eine Präsentation ansehen/bearbeiten darf.
 */
interface AuthorizerInterface
{
    /**
     * Prüfen ob der aktuelle User Zugriff auf das Subject hat.
     * Sollte abort(403) werfen wenn nicht autorisiert.
     */
    public function authorize(Request $request, Model $subject): void;

    /**
     * URL für den "Zurück"-Button in der Präsentation.
     */
    public function backUrl(Model $subject): string;
}
