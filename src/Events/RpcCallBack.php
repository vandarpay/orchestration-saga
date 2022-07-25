<?php

namespace vandarpay\OrchestrationSaga\Events;

class RpcCallBack
{
    public $response;
    public $correlationId;
    public $request;

    /**
     * @param mixed $response
     * @return RpcCallBack
     */
    public function setResponse($response): static
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @param mixed $correlationId
     * @return RpcCallBack
     */
    public function setCorrelationId($correlationId): static
    {
        $this->correlationId = $correlationId;
        return $this;
    }

    /**
     * @param mixed $request
     * @return RpcCallBack
     */
    public function setRequest($request): static
    {
        $this->request = $request;
        return $this;
    }
}
