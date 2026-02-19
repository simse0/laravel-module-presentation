<?php

namespace Trafficdesign\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Trafficdesign\Presentation\Contracts\AuthorizerInterface;
use Trafficdesign\Presentation\PresentationEngine;

class PresentationController extends Controller
{
    public function __construct(
        private PresentationEngine $engine,
        private AuthorizerInterface $authorizer,
    ) {}

    /**
     * Präsentation anzeigen.
     * Route-Parameter: {subject} wird vom Route-Model-Binding aufgelöst.
     */
    public function show(Request $request, $subjectId)
    {
        $subjectModel = config('presentation.subject_model');
        $subject = $subjectModel::findOrFail($subjectId);

        $this->authorizer->authorize($request, $subject);

        $presentation = $this->engine->getOrCreate($subject, $request->user());
        $result = $this->engine->buildPresentation($subject);

        $slides = $this->engine->applyOverrides($result['slides'], $presentation);
        $slides = $this->engine->applySlideOrder($slides, $presentation);

        $backUrl = $this->authorizer->backUrl($subject);

        $viewName = config('presentation.view', 'presentation::show');

        return view($viewName, [
            'subject' => $subject,
            'presentation' => $presentation,
            'slides' => $slides,
            'reportData' => $result['data'],
            'backUrl' => $backUrl,
            'config' => config('presentation'),
        ]);
    }

    /**
     * Text-Overrides speichern (AJAX).
     */
    public function saveOverrides(Request $request, $subjectId): JsonResponse
    {
        $subjectModel = config('presentation.subject_model');
        $subject = $subjectModel::findOrFail($subjectId);

        $this->authorizer->authorize($request, $subject);

        $validated = $request->validate([
            'overrides' => ['required', 'array'],
            'overrides.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $presentation = $this->engine->getOrCreate($subject, $request->user());
        $this->engine->saveTextOverrides($presentation, $validated['overrides']);

        return response()->json(['success' => true]);
    }

    /**
     * Slide-Reihenfolge speichern (AJAX).
     */
    public function saveOrder(Request $request, $subjectId): JsonResponse
    {
        $subjectModel = config('presentation.subject_model');
        $subject = $subjectModel::findOrFail($subjectId);

        $this->authorizer->authorize($request, $subject);

        $validated = $request->validate([
            'slide_order' => ['required', 'array'],
            'slide_order.*' => ['required', 'string'],
        ]);

        $presentation = $this->engine->getOrCreate($subject, $request->user());
        $this->engine->saveSlideOrder($presentation, $validated['slide_order']);

        return response()->json(['success' => true]);
    }
}
