<?php

namespace vandarpay\OrchestrationSaga\Rpc;

use vandarpay\OrchestrationSaga\Enums\RpcCallTypeEnum;

class Publisher
{
    protected static array $connectedServices = [];

    public function setConnectMicroservice(string $microName, RpcCallTypeEnum $callTypeEnum): static
    {
        if (!isset(self::$connectedServices[$microName]) || !in_array($callTypeEnum, self::$connectedServices[$microName])) {
            self::$connectedServices[$microName][] = $callTypeEnum;
        }
        return $this;
    }

    public function getConnectedMicroservices(): array
    {
        return self::$connectedServices;
    }


}
