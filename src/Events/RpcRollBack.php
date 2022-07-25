<?php

namespace vandarpay\OrchestrationSaga\Events;

class RpcRollBack
{
    public $response;
    public $correlationId;
    public $request;

    /**
     * @param mixed $response
     * @return RpcRollBack
     */
    public function setResponse($response): static
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @param mixed $correlationId
     * @return RpcRollBack
     */
    public function setCorrelationId($correlationId): static
    {
        $this->correlationId = $correlationId;
        return $this;
    }

    /**
     * @param mixed $request
     * @return RpcRollBack
     */
    public function setRequest($request): static
    {
        $this->request = $request;
        return $this;
    }
}
