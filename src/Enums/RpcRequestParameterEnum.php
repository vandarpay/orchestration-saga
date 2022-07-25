<?php

namespace vandarpay\OrchestrationSaga\Enums;

enum RpcRequestParameterEnum: string
{
    case DATA = 'data';
    case SERVICE_NAME = 'serviceName';
    case VERSION = 'version';
    case METHOD_NAME = 'methodName';
    case CALLBACK_EVENT = 'callbackEvent';
    case ROLLBACK_EVENT = 'rollbackEvent';
}
