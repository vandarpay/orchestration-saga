<?php

namespace vandarpay\OrchestrationSaga\Rpc;


use vandarpay\OrchestrationSaga\Events\RpcCallBack;
use vandarpay\OrchestrationSaga\Events\RpcRollBack;
use vandarpay\OrchestrationSaga\DTO\RpcLogJobDto;
use vandarpay\OrchestrationSaga\Enums\RpcCallTypeEnum;
use vandarpay\OrchestrationSaga\Enums\RpcRequestParameterEnum;
use vandarpay\OrchestrationSaga\Jobs\RpcLogJob;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use Redis;
use RedisException;

class Base
{
    protected string $queuePendingAsyncStack = 'pending_async_job';
    protected string $queuePendingSyncStack = 'pending_sync_job';
    protected string $queueResponseSyncStack = 'response_async_job';
    protected string $queuePendingResponseStack = 'pending_response_job';
    protected AMQPStreamConnection $connection;
    protected AbstractChannel|AMQPChannel $channel;
    protected string $queueRequest;
    protected string $transformerNamespace = 'App\Services';
    protected string $queueResponse;
    protected string $microName;
    protected string $serviceName;
    protected ?string $serviceVersion = null;
    protected int $requestTimeout;
    protected ?string $callBackEvent = null;
    protected ?string $rollBackEvent = null;
    protected string $logJob;
    private bool $useExchangeLog;
    private const LOG_QUEUE = 'logs';
    private const LOG_EXCHANGE = 'logExchange';

    public function __construct()
    {
        ini_set('default_socket_timeout', -1);
        $this->connection = new AMQPStreamConnection(
            config('rpc.rabbitmq.host'),
            config('rpc.rabbitmq.port'),
            config('rpc.rabbitmq.user'),
            config('rpc.rabbitmq.password'),
            '/',
            false,
            AMQPConnectionConfig::AUTH_AMQPPLAIN,
            null,
            'en_US',
            config('rpc.rabbitmq.connection_timeout'),
            config('rpc.rabbitmq.read_write_timeout'),
            null,
            config('rpc.rabbitmq.keep_alive')
        );
        $this->channel = $this->connection->channel();
        $this->queueRequest = config('rpc.rabbitmq.request_queue');
        $this->requestTimeout = config('rpc.request_timeout');
        $this->useExchangeLog = (bool)config('rpc.rabbitmq.use_exchange_log');
        $this->logJob = config('rpc.log_job');
        $this->queueResponse = $this->getMicroName() . '_response';
        if ($this->useExchangeLog) {
            $this->channel->queue_declare(self::LOG_QUEUE, false, false, false, false);
        }
    }

    /**
     * @return string
     */
    protected function getMicroName(): string
    {
        return Str::slug(config('app.name'), '_');
    }

    /**
     * @param string $transformerNamespace
     * @return Base
     */
    protected function setTransformerNamespace(string $transformerNamespace): static
    {
        $this->transformerNamespace = $transformerNamespace;
        return $this;
    }

    protected function getRedisClient(): Redis
    {
        $redisClient = new Redis();
        $redisClient->connect(config('database.redis.default.host'), config('database.redis.default.port'));
        $redisClient->setOption(Redis::OPT_READ_TIMEOUT, -1);
        $redisClient->select(config('database.redis.default.database'));
        return $redisClient;
    }

    /**
     * @return string
     */
    protected function getServerName(): string
    {
        return config('rpc.rabbitmq.host') . ":" . config('rpc.rabbitmq.port');
    }

    /**
     * @param $queueName
     * @param $channel
     * @return void
     */
    protected function declareQueue($queueName, AbstractChannel|AMQPChannel &$channel = null)
    {
        if (is_null($channel)) {
            $this->channel->queue_declare($queueName, false, false, false, false);
            $channel = $this->channel;
        } else {
            $channel->queue_declare($queueName, false, false, false, false);
        }
        if ($this->useExchangeLog) {
            $channel->exchange_declare(self::LOG_EXCHANGE, AMQPExchangeType::DIRECT, false, false, false);
            $channel->queue_bind(self::LOG_QUEUE, self::LOG_EXCHANGE, $queueName);
            $channel->queue_bind($queueName, self::LOG_EXCHANGE, $queueName);
        }
    }

    /**
     * @param string $microName
     * @return $this
     */
    protected function setMicroName(string $microName): static
    {
        $this->microName = $microName;
        $this->queueRequest = Str::slug($this->microName, '_') . '_request';
        return $this;
    }

    /**
     * @param string $serviceName
     * @return $this
     */
    protected function setServiceName(string $serviceName): static
    {
        $this->serviceName = $serviceName;
        return $this;
    }

    /**
     * @param string|null $serviceVersion
     * @return $this
     */
    protected function setserviceVersion(?string $serviceVersion): static
    {
        $this->serviceVersion = $serviceVersion;
        return $this;
    }

    /**
     * @param string $callBackEvent
     * @return $this
     * @throws Exception
     */
    protected function setCallBackEvent(string $callBackEvent): static
    {
        if (!(new $callBackEvent()) instanceof RpcCallBack) {
            throw new Exception('Call back event must be instance of ' . RpcCallBack::class);
        }
        $this->callBackEvent = $callBackEvent;
        return $this;
    }

    /**
     * @param string $rollBackEvent
     * @return $this
     * @throws Exception
     */
    protected function setRollBackEvent(string $rollBackEvent): static
    {
        if (!(new $rollBackEvent()) instanceof RpcRollBack) {
            throw new Exception('Call back event must be instance of ' . RpcRollBack::class);
        }
        $this->rollBackEvent = $rollBackEvent;
        return $this;
    }

    /**
     * @param string $correlationId
     * @param string $message
     * @param RpcCallTypeEnum $callType
     * @return bool
     */
    protected function publishJobToRedis(string $correlationId, string $message, RpcCallTypeEnum $callType = RpcCallTypeEnum::Async): bool
    {
        $channelName = $callType == RpcCallTypeEnum::Sync ? $this->queuePendingSyncStack : $this->queuePendingAsyncStack;
        $redisClient = $this->getRedisClient();
        $this->debug('Publish job to Redis correlation_id :' . $correlationId . ' channel : ' . $channelName);
        return (bool)$redisClient->publish($channelName, json_encode([
            'correlationId' => $correlationId,
            'message' => $message,
            'time' => time(),
        ]));
    }

    /**
     * @param AMQPMessage $message
     * @param string $queueName
     * @return void
     */
    protected function publishJobToQueue(AMQPMessage $message, string $queueName)
    {
        $exchange = '';
        if ($this->useExchangeLog) {
            $exchange = self::LOG_EXCHANGE;
        }
        $this->channel->basic_publish($message, $exchange, $queueName);
    }


    /**
     * @param $callback
     * @param RpcCallTypeEnum $callType
     * @return void
     */
    protected function subscribeJob($callback, RpcCallTypeEnum $callType = RpcCallTypeEnum::Async)
    {
        $channelName = $callType == RpcCallTypeEnum::Sync ? $this->queuePendingSyncStack : $this->queuePendingAsyncStack;
        $redisClient = $this->getRedisClient();
        $subscribeFunction = function ($redis, $channel, $message) use ($callback) {
            $callback(json_decode($message, true));
        };
        $redisClient->subscribe([$channelName], $subscribeFunction);
    }

    /**
     * @param string $correlationId
     * @return mixed
     * @throws Exception
     */
    protected function waitResponse(string $correlationId): mixed
    {
        $key = $this->queueResponseSyncStack . $correlationId;
        $response = null;
        $redisClient = $this->getRedisClient();
        $redisClient->setOption(Redis::OPT_READ_TIMEOUT, $this->requestTimeout);
        try {
            $subscribeFunction = function ($redis, $channel, $message) use ($key, $redisClient, &$response) {
                $response = $message;
                $redisClient->unsubscribe([$key]);
                $redisClient->close();
            };
            $redisClient->subscribe([$key], $subscribeFunction);
        } catch (RedisException $exception) {
            if (is_null($response)) {
                $response['exception'] = 'Request Timeout ' . $this->requestTimeout . ' Second';
            }
        }
        unset($redisClient);
        return $response;
    }


    /**
     * @param $response
     * @return bool
     */
    protected function receiveResponse($response): bool
    {
        $correlationId = $response->get('correlation_id');
        $priority = $response->get('priority');
        $responseArray = json_decode($response->body, true);
        $this->debug('Receive Response correlation_id :' . $correlationId . ' body : ' . $response->body);

        $requestArray = $responseArray['request'] ?? [];
        $callBackEventNameSpace = $requestArray[RpcRequestParameterEnum::CALLBACK_EVENT->value] ?? '';
        $rollbackEventNameSpace = $requestArray[RpcRequestParameterEnum::ROLLBACK_EVENT->value] ?? '';
        if (isset($responseArray['response']['exception'])) {
            if (!empty($rollbackEventNameSpace) && class_exists($rollbackEventNameSpace)) {
                /** @var RpcRollBack $rollbackEvent */
                $rollbackEvent = (new $rollbackEventNameSpace())->setCorrelationId($correlationId)
                    ->setRequest($requestArray)
                    ->setResponse($responseArray['response']['exception']);
                event($rollbackEvent);
            }
        } elseif (!empty($callBackEventNameSpace) && class_exists($callBackEventNameSpace)) {
            /** @var RpcCallBack $callBackEvent */
            $callBackEvent = (new $callBackEventNameSpace())->setCorrelationId($correlationId)
                ->setRequest($requestArray)
                ->setResponse($responseArray['response']);
            event($callBackEvent);
        }
        if ($priority == 1) {
            $redisClient = $this->getRedisClient();
            $redisClient->publish($this->queueResponseSyncStack . $correlationId, $response->body);
        }
        $response->ack();
        return true;
    }

    /**
     * @param string $message
     * @return void
     */
    protected function debug(string $message)
    {
        if (config('rpc.debug')) {
            Log::channel('rpc')->debug($message);
        }
    }

    /**
     * @param RpcLogJobDto $rpcLogJobDto
     * @return void
     */
    protected function storeLog(RpcLogJobDto $rpcLogJobDto)
    {
        $this->debug('Store log Request :' . $rpcLogJobDto->getCorrelationId() . ' Status : ' . $rpcLogJobDto->getStatus());
        if (!empty($this->logJob) && class_exists($this->logJob)) {
            $rpcLogJob = $this->logJob;
            dispatch(new $rpcLogJob($rpcLogJobDto));
        }
    }
}
