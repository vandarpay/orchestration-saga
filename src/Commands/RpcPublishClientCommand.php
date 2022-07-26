<?php

namespace vandarpay\OrchestrationSaga\Commands;

use Exception;
use Illuminate\Console\Command;
use vandarpay\OrchestrationSaga\Enums\RpcCallTypeEnum;
use vandarpay\OrchestrationSaga\Rpc\Client;
use vandarpay\OrchestrationSaga\Rpc\Publisher;

class RpcPublishClientCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rpc:publish-client';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run rpc client and publish request to targeted microservice';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        \Co::set(['hook_flags' => SWOOLE_HOOK_TCP]);
        \Co\run(function () {
            $publisher = resolve(Publisher::class);
            $connectedMicroservices = $publisher->getConnectedMicroservices();
            if (empty($connectedMicroservices)) {
                $this->output->info('No publisher connection is defined to send requests to the destination server queue');
                return;
            }
            foreach ($connectedMicroservices as $microName => $callTypes) {
                foreach ($callTypes as $callType) {
                    go(function () use ($microName, $callType) {
                        $cid = \Swoole\Coroutine::getuid();
                        $rpcClient = new Client();
                        $message = 'Coroutine(' . $cid . ') Start ' . $callType->value . ' publisher for ' . $microName . ' (' . $rpcClient->getServerName() . ')';
                        $this->output->info($message);
                        $rpcClient->setMicroName($microName)->publish($callType);
                    });
                }
            }
        });
    }
}
