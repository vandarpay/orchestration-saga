<?php

namespace vandarpay\OrchestrationSaga\Enums;

enum RpcLogStatusEnum: string
{
    case INIT = 'init';
    case SENT = 'sent';
    case RECEIVED = 'received';
    case EXPIRED = 'expired';
    case BEFORE_TRANSFORM = 'before_transform';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case REPLY_REQUEST = 'reply_request';
    case RESPONDED = 'responded';
    case RESPONSE_RECEIVED = 'response_received';
    case SERVER_EXCEPTION = 'server_exception';
    case CLIENT_EXCEPTION = 'client_exception';
    case COMPLETED = 'completed';
}
