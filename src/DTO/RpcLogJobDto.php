<?php

namespace vandarpay\OrchestrationSaga\DTO;


use vandarpay\OrchestrationSaga\Enums\RpcLogStatusEnum;
use vandarpay\ServiceRepository\BaseDto;

/**
 * @method $this setCorrelationId(string $correlationId)
 * @method string getCorrelationId()
 * @method $this setBody(string $body)
 * @method string getBody()
 * @method string getStatus()
 * @method $this setTime(int $time)
 * @method int getTime(int $time)
 */
class RpcLogJobDto extends BaseDto
{
    protected string $correlationId;
    protected string $body;
    protected string $status;
    protected string $time;

    public function setStatus(RpcLogStatusEnum $rpcLogStatusEnum): static
    {
        $this->status = $rpcLogStatusEnum->value;
        return $this;
    }
}
