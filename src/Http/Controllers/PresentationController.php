<?php

namespace Trafficdesign\Presentation\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Trafficdesign\Presentation\Contracts\AuthorizerInterface;
use Trafficdesign\Presentation\Models\Presentation;
use Trafficdesign\Presentation\PresentationEngine;
use Trafficdesign\Presentation\Services\PdfExportService;

// WIP: exportPdf() + render() – Headless-Chrome-Export, siehe docs/WIP-PDF-EXPORT-HEADLESS-CHROME.md
class PresentationController extends Controller
{
    public function __construct(
        private PresentationEngine $engine,
        private AuthorizerInterface $authorizer,
    ) {}

    /**
     * Praesentation per Name suchen.
     */
    public function lookup(string $name): JsonResponse
    {
        $presentation = $this->engine->findByName($name);

        if (! $presentation) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'id' => $presentation->id,
            'name' => $presentation->name,
            'title' => $presentation->title,
            'has_snapshot' => $presentation->hasSnapshot(),
            'version_name' => $presentation->version_name,
            'updated_at' => $presentation->updated_at,
        ]);
    }

    /**
     * Neue Praesentation erstellen.
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:presentations,name'],
            'subject_type' => ['required', 'string'],
            'subject_id' => ['required', 'integer'],
        ]);

        $allowedModels = config('presentation.allowed_subject_types', [
            config('presentation.subject_model'),
        ]);

        if (! in_array($validated['subject_type'], $allowedModels, true)) {
            abort(422, 'Invalid subject_type.');
        }

        $subjectModel = $validated['subject_type'];
        $subject = $subjectModel::findOrFail($validated['subject_id']);

        $this->authorizer->authorize($request, $subject);

        $presentation = $this->engine->createPresentation(
            $validated['name'],
            $subject,
            $request->user(),
        );

        return response()->json([
            'id' => $presentation->id,
            'name' => $presentation->name,
            'title' => $presentation->title,
        ], 201);
    }

    /**
     * Present-Modus (read-only).
     */
    public function present(Request $request, int $presentation): View
    {
        $pres = Presentation::findOrFail($presentation);
        $subject = $pres->presentable;
        abort_unless($subject, 404, 'Presentable subject not found.');

        $this->authorizer->authorize($request, $subject);

        if ($pres->hasSnapshot()) {
            $result = $this->engine->loadFromSnapshot($pres);
        } else {
            $result = $this->engine->generateAndSave($subject, $pres);
        }

        $slides = $result['slides'];
        $slides = $this->engine->applyOverrides($slides, $pres);
        $slides = $this->engine->applySlideOrder($slides, $pres);

        $backUrl = $this->authorizer->backUrl($subject);
        $viewName = config('presentation.view', 'presentation::show');

        return view($viewName, [
            'subject' => $subject,
            'presentation' => $pres,
            'slides' => $slides,
            'reportData' => $result['reportData'],
            'backUrl' => $backUrl,
            'config' => config('presentation'),
            'mode' => 'present',
        ]);
    }

    /**
     * Edit-Modus mit Sidebar.
     */
    public function edit(Request $request, int $presentation): View
    {
        if (! config('presentation.enable_edit_mode', true)) {
            abort(403, 'Edit mode is disabled.');
        }

        $pres = Presentation::findOrFail($presentation);
        $subject = $pres->presentable;
        abort_unless($subject, 404, 'Presentable subject not found.');

        $this->authorizer->authorize($request, $subject);

        if ($pres->hasSnapshot()) {
            $result = $this->engine->loadFromSnapshot($pres);
        } else {
            $result = $this->engine->generateAndSave($subject, $pres);
        }

        $slides = $result['slides'];
        $slides = $this->engine->applyOverrides($slides, $pres);
        $slides = $this->engine->applySlideOrder($slides, $pres);

        $backUrl = $this->authorizer->backUrl($subject);
        $viewName = config('presentation.edit_view', 'presentation.edit');

        return view($viewName, [
            'subject' => $subject,
            'presentation' => $pres,
            'slides' => $slides,
            'reportData' => $result['reportData'],
            'backUrl' => $backUrl,
            'config' => config('presentation'),
            'mode' => 'edit',
        ]);
    }

    /**
     * Kompletten Slide-State speichern (AJAX).
     */
    public function save(Request $request, int $presentation): JsonResponse
    {
        $pres = Presentation::findOrFail($presentation);
        $this->authorizer->authorize($request, $pres->presentable);

        $request->validate([
            'slides' => ['required', 'array'],
            'slides.*.id' => ['required', 'string'],
            'slides.*.type' => ['required', 'string'],
            'slides.*.textboxes' => ['nullable', 'array'],
            'slides.*.fontOverrides' => ['nullable', 'array'],
            'slides.*.title' => ['nullable', 'string'],
            'slides.*.theme' => ['nullable', 'string'],
        ]);

        $this->engine->saveSlides($pres, $request->input('slides'));

        return response()->json(['success' => true, 'updated_at' => $pres->fresh()->updated_at]);
    }

    /**
     * Slides neu generieren (ueberschreibt Snapshot).
     */
    public function regenerate(Request $request, int $presentation): JsonResponse
    {
        $pres = Presentation::findOrFail($presentation);
        $subject = $pres->presentable;
        abort_unless($subject, 404, 'Presentable subject not found.');

        $this->authorizer->authorize($request, $subject);

        $this->engine->regenerate($subject, $pres);

        return response()->json([
            'success' => true,
            'redirect' => route('presentation.show', $pres->id),
        ]);
    }

    /**
     * Praesentation umbenennen.
     */
    public function rename(Request $request, int $presentation): JsonResponse
    {
        $pres = Presentation::findOrFail($presentation);
        $this->authorizer->authorize($request, $pres->presentable);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'version_name' => ['nullable', 'string', 'max:255'],
        ]);

        $pres->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['success' => true]);
    }

    /**
     * Neuen Text-Slide hinzufuegen.
     */
    public function addSlide(Request $request, int $presentation): JsonResponse
    {
        $pres = Presentation::findOrFail($presentation);
        $this->authorizer->authorize($request, $pres->presentable);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:500'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:5000'],
            'theme' => ['nullable', 'string', 'in:light,dark'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $slides = $this->engine->addTextSlide($pres, $validated, $validated['position'] ?? null);

        return response()->json([
            'success' => true,
            'slides' => $slides,
            'slide_count' => count($slides),
        ]);
    }

    /**
     * Slide entfernen.
     */
    public function removeSlide(Request $request, int $presentation, string $slideId): JsonResponse
    {
        $pres = Presentation::findOrFail($presentation);
        $this->authorizer->authorize($request, $pres->presentable);

        $slides = $this->engine->removeSlide($pres, $slideId);

        return response()->json([
            'success' => true,
            'slides' => $slides,
            'slide_count' => count($slides),
        ]);
    }

    /**
     * Server-seitiger PDF-Export via Headless Chrome Screenshots.
     */
    public function exportPdf(Request $request, int $presentation): BinaryFileResponse
    {
        $pres = Presentation::findOrFail($presentation);
        $subject = $pres->presentable;
        abort_unless($subject, 404, 'Presentable subject not found.');

        $this->authorizer->authorize($request, $subject);

        $service = app(PdfExportService::class);
        $pdfPath = $service->export($pres);

        $filename = str_replace(
            [' ', '/'],
            ['_', '-'],
            preg_replace('/[^a-zA-Z0-9äöüÄÖÜß\s\-_]/', '', $pres->title ?: 'Praesentation')
        ) . '.pdf';

        return response()
            ->download($pdfPath, $filename, ['Content-Type' => 'application/pdf'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Render-Route fuer Headless Chrome (Token-basiert, kein Auth).
     * Identisch mit present(), aber ohne Autorisierung – der Cache-Token IST die Autorisierung.
     */
    public function render(Request $request, int $presentation): View
    {
        $token = $request->query('token');
        $cachedId = $token ? \Illuminate\Support\Facades\Cache::get('presentation-render-' . $token) : null;
        abort_unless($cachedId && (int) $cachedId === $presentation, 403, 'Invalid or expired token.');

        $pres = Presentation::findOrFail($presentation);
        $subject = $pres->presentable;
        abort_unless($subject, 404, 'Presentable subject not found.');

        if ($pres->hasSnapshot()) {
            $result = $this->engine->loadFromSnapshot($pres);
        } else {
            $result = $this->engine->generateAndSave($subject, $pres);
        }

        $slides = $result['slides'];
        $slides = $this->engine->applyOverrides($slides, $pres);
        $slides = $this->engine->applySlideOrder($slides, $pres);

        $viewName = config('presentation.view', 'presentation::show');

        return view($viewName, [
            'subject' => $subject,
            'presentation' => $pres,
            'slides' => $slides,
            'reportData' => $result['reportData'],
            'backUrl' => '#',
            'config' => config('presentation'),
            'mode' => 'present',
            'export' => true,
        ]);
    }

    // --- Legacy (Abwaertskompatibilitaet) ---

    /**
     * @deprecated Verwende present() stattdessen.
     */
    public function show(Request $request, int|string $subjectId): \Illuminate\Http\RedirectResponse
    {
        $subjectModel = config('presentation.subject_model');
        $subject = $subjectModel::findOrFail($subjectId);

        $this->authorizer->authorize($request, $subject);

        $presentation = $this->engine->getOrCreate($subject, $request->user());

        return redirect()->route('presentation.show', $presentation->id);
    }

    public function saveOverrides(Request $request, int $presentation): JsonResponse
    {
        $pres = Presentation::findOrFail($presentation);
        $this->authorizer->authorize($request, $pres->presentable);

        $validated = $request->validate([
            'overrides' => ['required', 'array'],
            'overrides.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->engine->saveTextOverrides($pres, $validated['overrides']);

        return response()->json(['success' => true]);
    }

    public function saveOrder(Request $request, int $presentation): JsonResponse
    {
        $pres = Presentation::findOrFail($presentation);
        $this->authorizer->authorize($request, $pres->presentable);

        $validated = $request->validate([
            'slide_order' => ['required', 'array'],
            'slide_order.*' => ['required', 'string'],
        ]);

        $this->engine->saveSlideOrder($pres, $validated['slide_order']);

        return response()->json(['success' => true]);
    }
}
