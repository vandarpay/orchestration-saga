<?php

namespace vandarpay\OrchestrationSaga\Commands;


use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:rollback-event')]
class RollbackEventMakeCommand extends GeneratorCommand
{
    protected $name = 'make:rollback-event';

    protected $description = 'Create a new roll back event for rpc call';

    protected $type = 'Callback Event';

    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);
        return $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . 'RollBackEvent.php';
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/rollback-event.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return "{$rootNamespace}\\Events";
    }

}
