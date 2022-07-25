<?php

namespace vandarpay\OrchestrationSaga\Rpc;

use Illuminate\Support\Str;
use vandarpay\OrchestrationSaga\Enums\RpcCallTypeEnum;
use Exception;
use PhpAmqpLib\Message\AMQPMessage;

class Client extends Base
{
    protected array|null $asyncResponse;
    protected array|null $syncResponse;
    protected int $startTime;
    protected string $correlationId;

    public function setMicroName(string $microName): static
    {
        return parent::setMicroName($microName);
    }

    public function getServerName(): string
    {
        return parent::getServerName();
    }

    /**
     * @param RpcCallTypeEnum $rpcCallTypeEnum
     * @return void
     */
    public function publish(RpcCallTypeEnum $rpcCallTypeEnum)
    {
        $this->declareQueue($this->queueRequest);
        $this->subscribeJob(function ($job) use ($rpcCallTypeEnum) {
            $this->debug('Get new job (' . $job['correlationId'] . ') in ' . $rpcCallTypeEnum->value . ' publisher for queue : ' . $this->queueRequest);
            $properties = [
                'correlation_id' => $job['correlationId'],
                'reply_to' => $this->queueResponse,
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'priority' => $rpcCallTypeEnum == RpcCallTypeEnum::Sync ? 1 : 2,
                'timestamp' => time(),
                'app_id' => $this->getMicroName()
            ];
            $rabbitMessage = new AMQPMessage($job['message'], $properties);
            $this->publishJobToQueue($rabbitMessage, $this->queueRequest);
            $this->debug('Publish ' . $rpcCallTypeEnum->value . ' job to rabbitmq [Request :' . $job['correlationId'] . ' Queue : ' . $this->queueRequest . ']');
            unset($rabbitMessage);
        }, $rpcCallTypeEnum);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function listen()
    {
        $this->declareQueue($this->queueResponse);
        $this->channel->basic_qos(null, 1, null);
        $onResponse = function ($response) {
            $this->receiveResponse($response);
        };
        $this->channel->basic_consume(
            $this->queueResponse,
            '',
            false,
            false,
            false,
            false,
            $onResponse
        );
        while ($this->channel->is_open()) {
            $this->channel->wait();
        }
        $this->channel->close();
        $this->connection->close();
    }

}
