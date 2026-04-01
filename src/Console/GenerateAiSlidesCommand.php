<?php

namespace Trafficdesign\Presentation\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Trafficdesign\Presentation\Services\AiSlideGeneratorService;

class GenerateAiSlidesCommand extends Command
{
    protected $signature = 'presentation:generate-ai
                            {name : Generator name from presentation.ai_slides.generators}
                            {--only-store : Skip generation and only store returned html}';

    protected $description = 'Generate and persist AI slide HTML using a configured generator.';

    public function handle(AiSlideGeneratorService $slides): int
    {
        $name = (string) $this->argument('name');
        $config = config("presentation.ai_slides.generators.{$name}");

        if ($config === null) {
            $this->error("No generator configured for '{$name}'.");
            return self::FAILURE;
        }

        try {
            $payload = $this->resolvePayload($config);

            $html = (string) ($payload['html'] ?? '');
            if (! $this->option('only-store')) {
                if ($html === '') {
                    $html = $slides->generate(
                        $name,
                        (string) ($payload['system_prompt'] ?? ''),
                        (array) ($payload['dataset'] ?? []),
                        (array) ($payload['options'] ?? [])
                    );
                }
            }

            if ($html === '') {
                throw new RuntimeException("Generator '{$name}' returned empty html.");
            }

            $stored = $slides->store($name, $html, (array) ($payload['meta'] ?? []));
            $generatedAt = (string) ($stored['generated_at'] ?? now()->toIso8601String());

            $this->info("AI slides generated for '{$name}' at {$generatedAt}.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to generate '{$name}': " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function resolvePayload(mixed $config): array
    {
        if (is_callable($config)) {
            $result = $config();
            return is_array($result) ? $result : [];
        }

        if (is_array($config)) {
            if (isset($config['callback']) && is_callable($config['callback'])) {
                $result = $config['callback']();
                return is_array($result) ? $result : [];
            }

            if (isset($config['service'], $config['method'])) {
                $service = app($config['service']);
                $method = (string) $config['method'];

                if (! method_exists($service, $method)) {
                    throw new RuntimeException("Method '{$method}' does not exist on service '{$config['service']}'.");
                }

                $result = $service->{$method}();
                return is_array($result) ? $result : [];
            }

            return $config;
        }

        throw new RuntimeException('Invalid AI slide generator configuration.');
    }
}
