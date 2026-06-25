<?php

declare(strict_types=1);

namespace ZkTeco\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ZkTeco\ADMS\AttendancePhotoSink;
use ZkTeco\ADMS\AttendanceSink;
use ZkTeco\ADMS\BiometricSink;
use ZkTeco\ADMS\Commands\CommandQueue;
use ZkTeco\ADMS\Commands\DeviceCommander;
use ZkTeco\ADMS\Generations\GenerationSelector;
use ZkTeco\ADMS\Generations\LegacyGeneration;
use ZkTeco\ADMS\Generations\PushSdkGeneration;
use ZkTeco\ADMS\OperationLogSink;
use ZkTeco\ADMS\Registry\DeviceRegistry;
use ZkTeco\ADMS\Registry\ProtocolGeneration;
use ZkTeco\ADMS\UserSink;
use ZkTeco\Exceptions\ZkException;
use ZkTeco\Laravel\Commands\ApproveDeviceCommand;
use ZkTeco\Laravel\Commands\ListDevicesCommand;
use ZkTeco\Laravel\Commands\ListenCommand;
use ZkTeco\Laravel\Http\PushController;
use ZkTeco\TCP\Protocol\NameField;

/**
 * Registers the Laravel bridge. Auto-discovered when the package is installed
 * inside a Laravel application; never loaded otherwise, which keeps the core
 * free of any illuminate/* requirement (see docs/adr/0002, 0006).
 */
final class ZkTecoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/zkteco.php', 'zkteco');

        $this->app->singleton('zkteco', function (Application $app): DeviceManager {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('zkteco', []);

            return new DeviceManager($config);
        });

        $this->app->alias('zkteco', DeviceManager::class);

        $this->registerPushBindings();
    }

    /**
     * Bind the ADMS core's seams to their bridge implementations: the registry
     * and command queue persist via the bridge, and the read-path sinks
     * (attendance, operation log, user, attendance photo, biometric) each
     * dispatch their matching event. The framework-neutral handlers and router
     * resolve themselves from these (see docs/adr/0008).
     */
    private function registerPushBindings(): void
    {
        $this->app->singleton(DeviceRegistry::class, function (Application $app): EloquentDeviceRegistry {
            /** @var list<string> $allowed */
            $allowed = $app['config']->get('zkteco.adms.allowed_serials', []);

            return new EloquentDeviceRegistry(
                $allowed,
                (bool) $app['config']->get('zkteco.adms.auto_register', false),
            );
        });

        $this->app->singleton(CommandQueue::class, EloquentCommandQueue::class);
        $this->app->singleton(AttendanceSink::class, EventDispatchingSink::class);
        $this->app->singleton(OperationLogSink::class, EventDispatchingOperationLogSink::class);
        $this->app->singleton(UserSink::class, EventDispatchingUserSink::class);
        $this->app->singleton(AttendancePhotoSink::class, EventDispatchingAttendancePhotoSink::class);
        $this->app->singleton(BiometricSink::class, EventDispatchingBiometricSink::class);

        $this->registerGenerations();

        // The typed write path: resolves a device's generation, renders the
        // command intent, and enqueues it. Its dependencies are all bound above.
        $this->app->singleton(DeviceCommander::class);
    }

    /**
     * Bind the per-generation strategies and the selector that picks one from a
     * device's protocol generation (see docs/adr/0012). The PUSH-SDK strategy
     * serves every push generation value; legacy is the fallback for any value
     * with no bespoke strategy, mirroring the "unrecognised means legacy"
     * negotiation stance.
     */
    private function registerGenerations(): void
    {
        // The codepage the panel stores user names in (Windows-1256 for Arabic
        // firmware). Sourced from the default connection so the ADMS render path
        // re-encodes `Name=` the same way the socket path does — without it the
        // device reads raw UTF-8 bytes and shows mojibake.
        $this->app->when(LegacyGeneration::class)
            ->needs('$nameEncoding')
            ->give(function (Application $app): string {
                $default = $app['config']->get('zkteco.default', 'default');

                return (string) $app['config']->get(
                    "zkteco.connections.{$default}.name_encoding",
                    NameField::DEFAULT_ENCODING,
                );
            });

        $this->app->singleton(LegacyGeneration::class);
        $this->app->singleton(PushSdkGeneration::class);

        $this->app->singleton(GenerationSelector::class, function (Application $app): GenerationSelector {
            $legacy = $app->make(LegacyGeneration::class);
            $pushSdk = $app->make(PushSdkGeneration::class);

            return new GenerationSelector(
                [
                    ProtocolGeneration::Legacy->value => $legacy,
                    ProtocolGeneration::PushV2->value => $pushSdk,
                    ProtocolGeneration::PushV3->value => $pushSdk,
                ],
                $legacy,
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'zkteco');

        $this->registerExceptionRenderer();
        $this->registerPushRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/zkteco.php' => $this->app->configPath('zkteco.php'),
            ], 'zkteco-config');

            $this->publishes([
                __DIR__.'/../../database/migrations' => $this->app->databasePath('migrations'),
            ], 'zkteco-migrations');

            $this->publishes([
                __DIR__.'/../../lang' => $this->app->langPath('vendor/zkteco'),
            ], 'zkteco-lang');

            $this->commands([
                ListenCommand::class,
                ApproveDeviceCommand::class,
                ListDevicesCommand::class,
            ]);
        }
    }

    /**
     * Mount the ADMS endpoints when enabled. They stay dormant by default so an
     * app using only the socket client never exposes a push surface; the
     * allowlist in config is the trust gate once they are on (see
     * docs/adr/0006, 0010). The router decides per request what each path does,
     * so one controller action serves them all.
     */
    private function registerPushRoutes(): void
    {
        $config = $this->app['config'];

        if (! $config->get('zkteco.adms.enabled', false)) {
            return;
        }

        Route::group([
            'prefix' => $config->get('zkteco.adms.prefix', 'iclock'),
            'middleware' => $config->get('zkteco.adms.middleware', []),
        ], function (): void {
            Route::match(['GET', 'POST'], 'cdata', [PushController::class, 'handle']);
            Route::get('getrequest', [PushController::class, 'handle']);
            Route::post('devicecmd', [PushController::class, 'handle']);
            Route::get('registry', [PushController::class, 'handle']);
        });
    }

    /**
     * Render any {@see ZkException} that reaches the handler as a localised JSON
     * error for API requests, leaving other responses to Laravel's defaults.
     */
    private function registerExceptionRenderer(): void
    {
        $this->callAfterResolving(ExceptionHandler::class, function ($handler): void {
            if (method_exists($handler, 'renderable')) {
                $handler->renderable(fn (ZkException $exception, $request) => (new ZkExceptionRenderer)->render($exception, $request));
            }
        });
    }
}
