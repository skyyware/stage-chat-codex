<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

enum DiagnosticClass: string
{
    case Unknown = 'unknown';
    case Authentication = 'authentication';
    case PermissionDenied = 'permission_denied';
    case RateLimit = 'rate_limit';
    case ProviderRequest = 'provider_request';
    case ProviderService = 'provider_service';
    case ModelCatalog = 'model_catalog';
    case CliVersion = 'cli_version';
    case Timeout = 'timeout';
    case Cancelled = 'cancelled';
    case InvalidResponse = 'invalid_response';
}
