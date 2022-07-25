<?php

namespace vandarpay\OrchestrationSaga\Rpc;

use vandarpay\OrchestrationSaga\Enums\RpcCallTypeEnum;
use vandarpay\OrchestrationSaga\Enums\RpcRequestParameterEnum;
use Exception;

class Service extends Base
{
    protected int $startTime;
    protected string $correlationId;
    protected string $microName;
    protected string $serviceName;

    /**
     * @return void
     */
    private function createNewRequest()
    {
        $this->correlationId = uniqid();
        $this->startTime = time();
    }

    /**
     * @param string $methodName
     * @param array $data
     * @param RpcCallTypeEnum $callType
     * @return bool
     * @throws Exception
     */
    private function call(string $methodName, array $data, RpcCallTypeEnum $callType = RpcCallTypeEnum::Async): bool
    {
        if (empty($this->microName)) {
            throw new Exception('Please set micro name');
        }
        if (empty($this->serviceName)) {
            throw new Exception('Please set service name');
        }
        $requestData = [
            RpcRequestParameterEnum::DATA->value => $data,
            RpcRequestParameterEnum::VERSION->value => $this->serviceVersion,
            RpcRequestParameterEnum::SERVICE_NAME->value => $this->serviceName,
            RpcRequestParameterEnum::METHOD_NAME->value => $methodName,
            RpcRequestParameterEnum::CALLBACK_EVENT->value => $this->callBackEvent,
            RpcRequestParameterEnum::ROLLBACK_EVENT->value => $this->rollBackEvent,
        ];
        return $this->publishJobToRedis($this->correlationId, json_encode($requestData), $callType);
    }

    /**
     * @param string $methodName
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    protected function callSync(string $methodName, array $data): mixed
    {
        $this->createNewRequest();
        $this->call($methodName, $data, RpcCallTypeEnum::Sync);
        $this->debug('Wait to response [Request :' . $this->correlationId . ' Queue : ' . $this->queueResponse . ']');
        $response = $this->waitResponse($this->correlationId);
        $this->debug('Response received [Request :' . $this->correlationId . ' Queue : ' . $this->queueResponse . ']');
        return $this->parsResponse($response);
    }

    /**
     * @param string $methodName
     * @param array $data
     * @return bool
     * @throws Exception
     */
    protected function callAsync(string $methodName, array $data): bool
    {
        $this->createNewRequest();
        return $this->call($methodName, $data);
    }

    /**
     * @param $responseJson
     * @return mixed
     * @throws Exception
     */
    protected function parsResponse($responseJson): mixed
    {
        $response = json_decode($responseJson, true)['response'];
        if (isset($response['exception'])) {
            throw new Exception($response['exception']['message'], $response['exception']['status_code']);
        }
        return $response;
    }

}
