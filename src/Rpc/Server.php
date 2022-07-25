<?php

namespace vandarpay\OrchestrationSaga\Rpc;

use Exception;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;
use Swoole\Process;
use Throwable;
use vandarpay\OrchestrationSaga\DTO\RpcLogJobDto;
use vandarpay\OrchestrationSaga\Enums\RpcLogStatusEnum;
use vandarpay\OrchestrationSaga\Enums\RpcRequestParameterEnum;
use vandarpay\ServiceRepository\ServiceException;

class Server extends Base
{
    public function getQueueName()
    {
        return $this->queueRequest;
    }

    public function getServerName(): string
    {
        return parent::getServerName();
    }
    /**
     * @return void
     * @throws Exception
     */
    private function waitForResponse()
    {
        $this->channel->basic_qos(null, 0, false);
        $responseFunction = function ($req) {
            $process = new Process(function (Process $process) use ($req) {
                $redisClient = $this->getRedisClient();
                $correlationId = $req->get_properties()['correlation_id'];
                $this->debug('Publish server request correlation_id :' . $correlationId . ' properties : ' . json_encode($req->get_properties()));
                $logDto = new RpcLogJobDto();
                $logDto->setCorrelationId($req->get_properties()['correlation_id'])->setBody($req->body);
                $requestBodyArray = [];
                try {
                    $this->storeLog($logDto->setStatus(RpcLogStatusEnum::PROCESSING));
                    if (time() - $req->get('timestamp') > config('rpc.rabbitmq.request_timeout')) {
                        $req->ack();
                        $this->storeLog($logDto->setStatus(RpcLogStatusEnum::EXPIRED));
                        $process->close();
                        $process->exit(0);
                    }
                    $requestBodyArray = $this->getDataFromAmqpMessage($req);
                    $this->storeLog($logDto->setStatus(RpcLogStatusEnum::BEFORE_TRANSFORM)->setBody(json_encode($requestBodyArray)));
                    $transformerData = [
                        RpcRequestParameterEnum::DATA->value => $requestBodyArray[RpcRequestParameterEnum::DATA->value],
                        RpcRequestParameterEnum::VERSION->value => $requestBodyArray[RpcRequestParameterEnum::VERSION->value],
                        RpcRequestParameterEnum::SERVICE_NAME->value => $requestBodyArray[RpcRequestParameterEnum::SERVICE_NAME->value],
                        RpcRequestParameterEnum::METHOD_NAME->value => $requestBodyArray[RpcRequestParameterEnum::METHOD_NAME->value]
                    ];
                    $serviceResponse = $this->makeServiceTransformer(...$transformerData);
                    $this->storeLog($logDto->setStatus(RpcLogStatusEnum::PROCESSED)->setBody(json_encode($serviceResponse)));
                } catch (Throwable $exception) {
                    $serviceResponse = $this->transformErrorResponse($exception);
                    $logDto->setBody($req->body . ' /**/ ' . json_encode($exception));
                    $this->storeLog($logDto->setStatus(RpcLogStatusEnum::SERVER_EXCEPTION));
                }
                $redisClient->publish($this->queuePendingResponseStack, json_encode([
                    'serviceResponse' => $serviceResponse,
                    'requestBodyArray' => $requestBodyArray,
                    'correlationId' => $correlationId,
                    'message' => $req->body,
                    'reply_to' => $req->get('reply_to'),
                    'delivery_mode' => $req->get('delivery_mode'),
                    'priority' => $req->get('priority'),
                    'time' => time()
                ]));
                $this->storeLog($logDto->setStatus(RpcLogStatusEnum::RESPONDED));
                $process->close();
                $process->exit(0);
            });
            $pid = $process->start();
            $this->debug('Process Start with pid (' . $pid . ') for correlation id : ' . $req->get_properties()['correlation_id']);
            $req->ack();
        };
        $this->channel->basic_consume($this->queueRequest, '', false, false, false, false, $responseFunction);
        while ($this->channel->is_open()) {
            $this->channel->wait();
        }
        $this->channel->close();
        $this->connection->close();
    }

    /**
     * @return void
     * @throws Exception
     */
    public function listen()
    {
        $this->declareQueue($this->queueRequest);
        $this->waitForResponse();
    }

    public function publish()
    {
        $redisClient = $this->getRedisClient();
        $responseFunction = function ($redis, $channel, $message) {
            $job = json_decode($message, true);
            $this->debug('Get new request (' . $job['correlationId'] . ') in server');
            $this->returnResponse($job['serviceResponse'], $job, $job['requestBodyArray']);
            $this->debug('Return response request (' . $job['correlationId'] . ') to client');
        };
        $redisClient->subscribe([$this->queuePendingResponseStack], $responseFunction);
    }

    /**
     * @param array $data
     * @param string|null $version
     * @param string $serviceName
     * @param string $methodName
     * @return mixed
     * @throws Exception
     */
    private function makeServiceTransformer(array $data, ?string $version, string $serviceName, string $methodName): mixed
    {
        $serviceNamePascalCase = Str::studly($serviceName);
        $serviceTransformerNamespace = $this->transformerNamespace . '\\' . $serviceNamePascalCase . '\\' ;
        if(!is_null($version)){
            $serviceTransformerNamespace .= $version . '\\';
        }
        $serviceTransformerNamespace .= $serviceNamePascalCase . 'Transformer';
        if (!class_exists($serviceTransformerNamespace)) {
            throw new Exception('Service ' . $serviceTransformerNamespace . ' not exist in micro ' . $this->getMicroName());
        }
        $serviceTransformer = resolve($serviceTransformerNamespace);
        if (!method_exists($serviceTransformer, $methodName)) {
            throw new Exception('Method ' . $serviceNamePascalCase . 'Transformer->' . $methodName . ' not exist in micro ' . $this->getMicroName());
        }
        $serviceResponse = $serviceTransformer->$methodName(...$data);
        $this->debug('Calling ' . $serviceNamePascalCase . 'Transformer->' . $methodName . '[ Parameter : ' . json_encode($data) . ' Response : ' . $serviceResponse . ']');
        unset($serviceTransformer);
        return $serviceResponse;
    }

    /**
     * @param $serviceResponse
     * @param $req
     * @param $requestBodyArray
     * @return void
     */
    private function returnResponse($serviceResponse, $req, $requestBodyArray)
    {
        $responseQueue = $req['reply_to'];
        $properties = [
            'correlation_id' => $req['correlationId'],
            'delivery_mode' => $req['delivery_mode'] ?? AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'priority' => $req['priority'] ?? 2,
            'timestamp' => time(),
            'app_id' => $this->getMicroName()
        ];
        $message['response'] = $serviceResponse;
        $message['request'] = $requestBodyArray;
        $jsonMessage = json_encode($message);
        $msg = new AMQPMessage($jsonMessage, $properties);
        $this->declareQueue($responseQueue);
        $logDto = (new RpcLogJobDto())->setCorrelationId($properties['correlation_id'])
            ->setBody($jsonMessage)
            ->setStatus(RpcLogStatusEnum::REPLY_REQUEST);
        $this->storeLog($logDto);
        $this->publishJobToQueue($msg, $responseQueue);
        unset($msg);
    }

    /**
     * @param AMQPMessage $request
     * @return array
     * @throws Exception
     */
    private function getDataFromAmqpMessage(AMQPMessage $request): array
    {
        $dataArray = json_decode($request->getBody(), true);
        $responseArray = [];
        foreach (RpcRequestParameterEnum::cases() as $parameter) {
            if (array_key_exists($parameter->value, $dataArray)) {
                $responseArray[$parameter->value] = $dataArray[$parameter->value];
            } else {
                $responseArray[$parameter->value] = '';
            }
        }
        return $responseArray;
    }


    /**
     * @param Exception $exception
     * @return array[]
     */
    private function transformErrorResponse(Throwable $exception): array
    {
        return [
            'exception' => [
                'status_code' => $exception->getCode(),
                'app_code' => $exception instanceof ServiceException ? $exception->getAppCode() : 'Exception',
                'message' => $exception->getMessage(),
            ]
        ];
    }
}
