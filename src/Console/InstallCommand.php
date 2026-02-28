<?php

namespace Trafficdesign\Presentation\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    protected $signature = 'presentation:install {--force : Bestehende Dateien überschreiben}';
    protected $description = 'Präsentationsmodul installieren: Config, Views, Adapter-Klassen und Migration';

    public function handle(): int
    {
        $fs = new Filesystem;
        $force = $this->option('force');
        $stubsPath = dirname(__DIR__, 2) . '/stubs';

        $this->components->info('Präsentationsmodul wird installiert...');

        // 1. Config publishen
        $this->callSilently('vendor:publish', ['--tag' => 'presentation-config']);
        $this->components->task('Config veröffentlicht', fn () => true);

        // 2. Adapter-Klassen scaffolden
        $adapterDir = app_path('Presentation');
        $fs->ensureDirectoryExists($adapterDir);

        $adapters = [
            'ExampleSlideBuilder.php' => 'ExampleSlideBuilder.php.stub',
            'ExampleDataCollector.php' => 'ExampleDataCollector.php.stub',
            'ExampleAuthorizer.php' => 'ExampleAuthorizer.php.stub',
            'PresentationServiceProvider.php' => 'PresentationServiceProvider.php.stub',
        ];

        foreach ($adapters as $target => $stub) {
            $targetPath = $adapterDir . '/' . $target;
            if (! $force && $fs->exists($targetPath)) {
                $this->components->warn("  {$target} existiert bereits (--force zum Überschreiben)");
                continue;
            }
            $fs->copy($stubsPath . '/Presentation/' . $stub, $targetPath);
            $this->components->task("app/Presentation/{$target}", fn () => true);
        }

        // 3. Views scaffolden
        $viewDir = resource_path('views/vendor/presentation');
        $fs->ensureDirectoryExists($viewDir);

        $engineStub = $stubsPath . '/views/vendor/presentation/engine.blade.php.stub';
        $engineTarget = $viewDir . '/engine.blade.php';
        if ($force || ! $fs->exists($engineTarget)) {
            $fs->copy($engineStub, $engineTarget);
            $this->components->task('resources/views/vendor/presentation/engine.blade.php', fn () => true);
        }

        // 4. Slide-Components scaffolden
        $slidesDir = resource_path('views/components/presentation/slides');
        $fs->ensureDirectoryExists($slidesDir);

        $slideStubs = $fs->files($stubsPath . '/views/components/presentation/slides');
        foreach ($slideStubs as $file) {
            $targetName = str_replace('.stub', '', $file->getFilename());
            $targetPath = $slidesDir . '/' . $targetName;
            if ($force || ! $fs->exists($targetPath)) {
                $fs->copy($file->getPathname(), $targetPath);
                $this->components->task("resources/views/components/presentation/slides/{$targetName}", fn () => true);
            }
        }

        // 5. Migration
        $this->callSilently('vendor:publish', ['--tag' => 'presentation-migrations']);
        $this->components->task('Migration veröffentlicht', fn () => true);

        $this->newLine();
        $this->components->info('Installation abgeschlossen!');
        $this->newLine();

        $this->components->bulletList([
            'Adapter-Klassen anpassen: app/Presentation/',
            'ServiceProvider registrieren: App\\Presentation\\PresentationServiceProvider in bootstrap/providers.php',
            'Oder Bindings in AppServiceProvider::register() eintragen',
            'Config anpassen: config/presentation.php (subject_model setzen!)',
            'Migration ausführen: php artisan migrate',
            'Slide-Components anpassen: resources/views/components/presentation/slides/',
        ]);

        return self::SUCCESS;
    }
}
