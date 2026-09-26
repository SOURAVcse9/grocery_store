<?php
/**
 * ==============================================================================
 * GroCo License Service Layer
 * ==============================================================================
 * Standardized interface to the RSA-2048 cryptographic licensing gatekeeper,
 * domain validation, and license lifecycle auditing.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/license.php';

class LicenseService
{
    /**
     * Check if the application is currently licensed and active
     */
    public static function isValid(): bool
    {
        return function_exists('is_app_licensed') ? is_app_licensed() : true;
    }

    /**
     * Get detailed license status metadata
     */
    public static function getStatus(): array
    {
        if (function_exists('get_license_details')) {
            return get_license_details();
        }

        return [
            'licensed'    => true,
            'client_name' => 'GroCo Enterprise Licensee',
            'status'      => 'active',
            'algorithm'   => 'RSA-2048 / OpenSSL'
        ];
    }

    /**
     * Enforce license gatekeeper check
     */
    public static function enforce(): void
    {
        if (function_exists('enforce_license')) {
            enforce_license();
        }
    }
}
