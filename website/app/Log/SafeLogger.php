<?php

namespace App\Log;

/** Payload-free logging, including framework SQL/exception interpolation. */
final class SafeLogger extends \CodeIgniter\Log\Logger
{
    private const LABELS = [
        'Credential replacement transaction failed' => 'credential.replace_failed',
        'Abuse limit storage unavailable' => 'abuse.storage_failed',
        'Inventory loan transaction failed' => 'inventory.transition_failed',
        'FCM delivery failed' => 'notification.delivery_failed',
        'FCM token registration removed' => 'notification.unregistered',
        'FCM transport failed' => 'notification.transport_failed',
        'Firebase Service Account file not found.' => 'notification.configuration_missing',
        'Failed to fetch FCM access token.' => 'notification.authentication_failed',
        'Image processing failed' => 'image.processing_failed',
        'Profile photo processing failed' => 'profile.image_failed',
        'Chat cleanup failed' => 'chat.cleanup_failed',
        'Wheel operation failed' => 'wheel.operation_failed',
        'Wheel socket delivery failed' => 'wheel.delivery_failed',
    ];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Unknown messages may contain SQL, provider bodies or framework's
        // {post_vars}/{session_vars}/{env:*}. Never forward them to interpolation.
        $label = is_string($message) ? (self::LABELS[$message] ?? 'application.diagnostic')
            : 'application.diagnostic';
        $id = $context['correlation_id'] ?? null;
        if (!is_string($id) || !preg_match('/\A[a-f0-9]{16}\z/D', $id)) {
            $id = bin2hex(random_bytes(8));
        }
        $safe = $label . ' correlation_id=' . $id;
        // Preserve the code location without exception text, arguments or SQL.
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8) as $frame) {
            $file = $frame['file'] ?? '';
            if ($file !== __FILE__ && str_starts_with($file, ROOTPATH)
                && !str_contains($file, 'system/Log/Logger.php')
                && !str_contains($file, 'system\\Log\\Logger.php')
                && !str_ends_with($file, 'Common.php')) {
                $safe .= ' source=' . str_replace('\\', '/', substr($file, strlen(ROOTPATH)))
                    . ':' . (int)($frame['line'] ?? 0);
                break;
            }
        }
        foreach (['status', 'entity_id', 'tenant_id', 'error_code'] as $field) {
            if (isset($context[$field]) && is_int($context[$field])) {
                $safe .= ' ' . $field . '=' . $context[$field];
            }
        }
        parent::log($level, $safe, []);
    }
}
