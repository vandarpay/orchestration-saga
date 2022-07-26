<?php

namespace vandarpay\OrchestrationSaga\Commands;

use Exception;
use Illuminate\Console\Command;
use vandarpay\OrchestrationSaga\Rpc\Client;
use vandarpay\OrchestrationSaga\Rpc\Publisher;

class RpcListenClientCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rpc:listen-client';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run rpc client and listen for response rpc calls';

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
     * @param Client $rpcClient
     * @return void
     * @throws Exception
     */
    public function handle(Client $rpcClient)
    {
        $publisher = resolve(Publisher::class);
        if (empty($publisher->getConnectedMicroservices())) {
            $this->output->info('No publisher connection is defined to send requests to the destination server queue');
            return;
        }
        $message = 'Start listen to response queue from server ' . $rpcClient->getServerName();
        $this->output->info($message);
        $rpcClient->listen();
    }
}
