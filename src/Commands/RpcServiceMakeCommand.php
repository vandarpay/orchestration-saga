<?php

namespace vandarpay\OrchestrationSaga\Commands;


use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:rpc-service')]
class RpcServiceMakeCommand extends GeneratorCommand
{
    protected $name = 'make:rpc-service';

    protected $description = 'Create a new rpc service class';

    protected $type = 'Rpc Service';

    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);
        return $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . 'RpcService.php';
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/rpc-service.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return "{$rootNamespace}\\RpcServices";
    }

}
