<?php

namespace vandarpay\OrchestrationSaga;

use Illuminate\Support\ServiceProvider;
use vandarpay\OrchestrationSaga\Commands\CallbackEventMakeCommand;
use vandarpay\OrchestrationSaga\Commands\RollbackEventMakeCommand;
use vandarpay\OrchestrationSaga\Commands\RpcListenClientCommand;
use vandarpay\OrchestrationSaga\Commands\RpcListenServerCommand;
use vandarpay\OrchestrationSaga\Commands\RpcPublishClientCommand;
use vandarpay\OrchestrationSaga\Commands\RpcPublishServerCommand;
use vandarpay\OrchestrationSaga\Commands\RpcServiceMakeCommand;
use vandarpay\OrchestrationSaga\Rpc\Publisher;
use vandarpay\OrchestrationSaga\Rpc\Service;

class OrchestrationSagaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
        $this->app->singleton(Publisher::class,Publisher::class);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->publishConfigs();
        }
    }

    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/rpc.php', 'rpc');
    }

    protected function registerCommands(): void
    {
        $this->commands([
            RpcListenClientCommand::class,
            RpcListenServerCommand::class,
            RpcPublishClientCommand::class,
            RpcPublishServerCommand::class,
            RpcServiceMakeCommand::class,
            RollbackEventMakeCommand::class,
            CallbackEventMakeCommand::class,
        ]);
    }

    protected function publishConfigs(): void
    {
        $this->publishes([
            __DIR__ . '/../config/rpc.php' => config_path('rpc.php'),
        ], 'rpc-config');
    }
}
