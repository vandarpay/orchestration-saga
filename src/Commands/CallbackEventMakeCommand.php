<?php

namespace vandarpay\OrchestrationSaga\Commands;


use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:callback-event')]
class CallbackEventMakeCommand extends GeneratorCommand
{
    protected $name = 'make:callback-event';

    protected $description = 'Create a new call back event for rpc call';

    protected $type = 'Callback Event';

    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);
        return $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . 'CallBackEvent.php';
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/callback-event.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return "{$rootNamespace}\\Events";
    }

}
