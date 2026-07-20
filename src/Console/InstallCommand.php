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
        $stubsPath = dirname(__DIR__, 2).'/stubs';
        $packageRoot = dirname(__DIR__, 2);

        $this->components->info('Präsentationsmodul wird installiert...');

        $this->callSilently('vendor:publish', ['--tag' => 'presentation-config']);
        $this->components->task('Config veröffentlicht', fn () => true);

        $adapterDir = app_path('Presentation');
        $fs->ensureDirectoryExists($adapterDir);

        $adapters = [
            'ExampleSlideBuilder.php' => 'ExampleSlideBuilder.php.stub',
            'ExampleDataCollector.php' => 'ExampleDataCollector.php.stub',
            'ExampleAuthorizer.php' => 'ExampleAuthorizer.php.stub',
            'PresentationServiceProvider.php' => 'PresentationServiceProvider.php.stub',
        ];

        foreach ($adapters as $target => $stub) {
            $targetPath = $adapterDir.'/'.$target;
            if (! $force && $fs->exists($targetPath)) {
                $this->components->warn("  {$target} existiert bereits (--force zum Überschreiben)");

                continue;
            }
            $fs->copy($stubsPath.'/Presentation/'.$stub, $targetPath);
            $this->components->task("app/Presentation/{$target}", fn () => true);
        }

        $presentationViewDir = resource_path('views/presentation');
        $fs->ensureDirectoryExists($presentationViewDir);

        $hostViews = [
            'show.blade.php' => $stubsPath.'/views/presentation/show.blade.php.stub',
            'edit.blade.php' => $stubsPath.'/views/presentation/edit.blade.php.stub',
        ];

        foreach ($hostViews as $targetName => $stubPath) {
            $targetPath = $presentationViewDir.'/'.$targetName;
            if (! $force && $fs->exists($targetPath)) {
                $this->components->warn("  resources/views/presentation/{$targetName} existiert bereits (--force zum Überschreiben)");

                continue;
            }
            $fs->copy($stubPath, $targetPath);
            $this->components->task("resources/views/presentation/{$targetName}", fn () => true);
        }

        $bootstrapDir = resource_path('js/presentation');
        $fs->ensureDirectoryExists($bootstrapDir);
        $bootstrapTarget = $bootstrapDir.'/bootstrap.js';
        $bootstrapStub = $packageRoot.'/resources/stubs/host-alpine-bootstrap.js.example';

        if (! $force && $fs->exists($bootstrapTarget)) {
            $this->components->warn('  resources/js/presentation/bootstrap.js existiert bereits (--force zum Überschreiben)');
        } else {
            $fs->copy($bootstrapStub, $bootstrapTarget);
            $this->components->task('resources/js/presentation/bootstrap.js', fn () => true);
        }

        $slidesDir = resource_path('views/components/presentation/slides');
        $fs->ensureDirectoryExists($slidesDir);

        $slideStubs = $fs->files($stubsPath.'/views/components/presentation/slides');
        foreach ($slideStubs as $file) {
            $targetName = str_replace('.stub', '', $file->getFilename());
            $targetPath = $slidesDir.'/'.$targetName;
            if (! $force && $fs->exists($targetPath)) {
                $this->components->warn("  resources/views/components/presentation/slides/{$targetName} existiert bereits (--force zum Überschreiben)");

                continue;
            }
            $fs->copy($file->getPathname(), $targetPath);
            $this->components->task("resources/views/components/presentation/slides/{$targetName}", fn () => true);
        }

        $this->callSilently('vendor:publish', ['--tag' => 'presentation-migrations']);
        $this->components->task('Migration veröffentlicht', fn () => true);

        $this->newLine();
        $this->components->info('Installation abgeschlossen!');
        $this->newLine();

        $this->components->bulletList([
            'Adapter-Klassen anpassen: app/Presentation/',
            'ServiceProvider registrieren: App\\Presentation\\PresentationServiceProvider in bootstrap/providers.php',
            'Config anpassen: config/presentation.php (subject_model setzen!)',
            'Vite-Entry ergänzen: resources/js/presentation/bootstrap.js in vite.config.js',
            'Migration ausführen: php artisan migrate',
            'Slide-Components anpassen: resources/views/components/presentation/slides/',
        ]);

        return self::SUCCESS;
    }
}
