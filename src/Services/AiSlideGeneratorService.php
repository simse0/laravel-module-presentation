<?php

namespace Trafficdesign\Presentation\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Trafficdesign\AiProvider\Facades\AI;

class AiSlideGeneratorService
{
    public function generate(string $name, string $systemPrompt, array $data, array $options = []): string
    {
        $payloadJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new RuntimeException("Failed to encode payload for '{$name}'.");
        }

        $response = AI::ask(
            $payloadJson,
            $systemPrompt,
            array_merge([
                'model' => $this->defaultModel(),
                'temperature' => 0.4,
            ], $options)
        );

        if (! ($response['success'] ?? false)) {
            $error = (string) ($response['error'] ?? 'Unknown AI provider error');
            throw new RuntimeException("AI generation failed for '{$name}': {$error}");
        }

        $content = trim((string) ($response['content'] ?? ''));
        if ($content === '') {
            throw new RuntimeException("AI generation returned empty content for '{$name}'.");
        }

        return $this->stripMarkdownFences($content);
    }

    public function store(string $name, string $html, array $meta = []): array
    {
        $payload = array_merge([
            'status' => 'ready',
            'generated_at' => now()->toIso8601String(),
            'quarter' => null,
            'html' => $html,
            'dataset' => null,
        ], $meta, ['html' => $html]);

        Cache::put($this->cacheKey($name), $payload, $this->cacheTtl());

        $basePath = $this->storageBasePath($name);
        Storage::disk('local')->put("{$basePath}/latest.html", $html);

        $jsonPayload = json_encode($payload, JSON_PRETTY_PRINT);
        Storage::disk('local')->put("{$basePath}/latest.json", $jsonPayload === false ? '{}' : $jsonPayload);

        return $payload;
    }

    public function latest(string $name): array
    {
        return Cache::get($this->cacheKey($name), [
            'status' => 'empty',
            'generated_at' => null,
            'quarter' => null,
            'html' => null,
            'dataset' => null,
        ]);
    }

    private function cacheKey(string $name): string
    {
        return "ai_slides:{$name}:latest";
    }

    private function storageBasePath(string $name): string
    {
        return 'ai-slides/' . $name;
    }

    private function cacheTtl(): int
    {
        return (int) config('presentation.ai_slides.cache_ttl', 604800);
    }

    private function defaultModel(): string
    {
        $provider = (string) config('ai.default_provider', 'openrouter');

        return (string) config("ai.default_models.{$provider}", 'openai/gpt-4o-mini');
    }

    private function stripMarkdownFences(string $content): string
    {
        $content = preg_replace('/^```html\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/i', '', $content) ?? $content;

        return trim($content);
    }
}
