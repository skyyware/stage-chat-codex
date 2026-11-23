<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

use SensitiveParameter;

/** @internal */
final class ProviderDiagnostic
{
    public static function fromMessage(#[SensitiveParameter] mixed $message): Diagnostic
    {
        if (!is_string($message)) {
            return new Diagnostic(DiagnosticClass::Unknown);
        }
        if (in_array($message, [
            'Your access token could not be refreshed. Please log out and sign in again.',
            'Your access token could not be refreshed because you have since logged out or signed in to another account. Please sign in again.',
            'Your access token could not be refreshed because your refresh token has expired. Please log out and sign in again.',
            'Your access token could not be refreshed because your refresh token was already used. Please log out and sign in again.',
            'Your access token could not be refreshed because your refresh token was revoked. Please log out and sign in again.',
        ], true)) {
            return new Diagnostic(DiagnosticClass::Authentication);
        }
        if (preg_match('/\Aunexpected status ([45][0-9]{2})(?: [A-Za-z -]+)?:/', $message, $match) !== 1
            && preg_match('/\Aexceeded retry limit, last status: ([45][0-9]{2})(?: [A-Za-z -]+)?(?:,|\z)/', $message, $match) !== 1) {
            return new Diagnostic(DiagnosticClass::Unknown);
        }
        $status = (int) $match[1];
        return new Diagnostic(match ($status) {
            401 => DiagnosticClass::Authentication,
            403 => DiagnosticClass::PermissionDenied,
            429 => DiagnosticClass::RateLimit,
            default => $status >= 500 ? DiagnosticClass::ProviderService : DiagnosticClass::ProviderRequest,
        }, $status);
    }
}
