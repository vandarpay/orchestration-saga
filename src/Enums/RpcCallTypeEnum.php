<?php

namespace vandarpay\OrchestrationSaga\Enums;

enum RpcCallTypeEnum: string
{
    case Sync = 'sync';
    case Async = 'async';
}
