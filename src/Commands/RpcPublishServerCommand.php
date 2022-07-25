<?php

namespace vandarpay\OrchestrationSaga\Commands;

use Exception;
use Illuminate\Console\Command;
use vandarpay\OrchestrationSaga\Rpc\Server;

class RpcPublishServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rpc:publish-server';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run rpc server and process for incoming rpc calls';

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
     * @param Server $rpcServer
     * @return void
     * @throws Exception
     */
    public function handle(Server $rpcServer)
    {
        $message = 'Start listen to ' . $rpcServer->getQueueName() . ' on ' . $rpcServer->getServerName();
        $this->output->info($message);
        $rpcServer->publish();
    }
}
