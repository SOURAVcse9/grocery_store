<?php
/**
 * licensing_server/cli_license_tool.php
 * 
 * Command-Line License Administration Tool for GroCo Platform Owners.
 * 
 * Usage:
 *   php cli_license_tool.php create --customer="Acme Corp" --email="admin@acme.com" --domains="example.com" --limit=1
 *   php cli_license_tool.php list
 *   php cli_license_tool.php inspect GRCO-XXXX-XXXX-XXXX-XXXX
 *   php cli_license_tool.php revoke GRCO-XXXX-XXXX-XXXX-XXXX --reason="Chargeback"
 *   php cli_license_tool.php suspend GRCO-XXXX-XXXX-XXXX-XXXX --reason="Overdue payment"
 *   php cli_license_tool.php reactivate GRCO-XXXX-XXXX-XXXX-XXXX
 *   php cli_license_tool.php public-key
 */

declare(strict_types=1);

require_once __DIR__ . '/license_server.php';

use GroCo\Licensing\LicenseServer;

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

$server = new LicenseServer();
$command = $argv[1] ?? 'help';

function parse_cli_options(array $argv): array {
    $opts = [];
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--')) {
            $parts = explode('=', substr($arg, 2), 2);
            $opts[$parts[0]] = $parts[1] ?? true;
        }
    }
    return $opts;
}

$opts = parse_cli_options($argv);

switch ($command) {
    case 'create':
        $customer = $opts['customer'] ?? 'Default Client';
        $email = $opts['email'] ?? 'client@example.com';
        $type = $opts['type'] ?? 'production';
        $domains = isset($opts['domains']) ? explode(',', (string)$opts['domains']) : ($type === 'development' ? ['localhost', '127.0.0.1', '::1', '*.test', '*.local'] : ['*']);
        $limit = isset($opts['limit']) ? (int)$opts['limit'] : 1;
        $expires = $opts['expires'] ?? null;
        $notes = $opts['notes'] ?? '';

        $lic = $server->createLicense((string)$customer, (string)$email, $domains, $limit, $expires, (string)$notes, (string)$type);

        echo "\n=======================================================\n";
        echo " 🎉 NEW LICENSE GENERATED SUCCESSFULLY\n";
        echo "=======================================================\n";
        echo " License Key:        " . $lic['license_key'] . "\n";
        echo " License Type:       " . strtoupper($lic['license_type'] ?? 'production') . "\n";
        echo " Customer Name:      " . $lic['customer_name'] . "\n";
        echo " Customer Email:     " . $lic['customer_email'] . "\n";
        echo " Allowed Domains:    " . implode(', ', $lic['allowed_domains']) . "\n";
        echo " Activation Limit:   " . $lic['activation_limit'] . "\n";
        echo " Expiration Date:    " . ($lic['expires_at'] ?: 'Perpetual (No expiry)') . "\n";
        echo " Status:             " . strtoupper($lic['status']) . "\n";
        echo "=======================================================\n\n";
        break;

    case 'list':
        $licenses = $server->listLicenses();
        echo "\n=======================================================\n";
        echo " 📋 REGISTERED LICENSES (" . count($licenses) . " total)\n";
        echo "=======================================================\n";
        foreach ($licenses as $l) {
            echo sprintf(
                " [%-9s] %-24s | %-11s | %-14s | Act: %d/%d | Exp: %s\n",
                strtoupper($l['status']),
                $l['license_key'],
                strtoupper($l['license_type'] ?? 'PROD'),
                substr($l['customer_name'], 0, 14),
                $l['activation_count'],
                $l['activation_limit'],
                $l['expires_at'] ?: 'Perpetual'
            );
        }
        echo "=======================================================\n\n";
        break;

    case 'inspect':
        $key = $argv[2] ?? '';
        if (!$key) die("Error: License key required.\n");
        $lic = $server->getLicense($key);
        if (!$lic) die("Error: License not found.\n");
        print_r($lic);
        break;

    case 'revoke':
        $key = $argv[2] ?? '';
        if (!$key) die("Error: License key required.\n");
        $reason = $opts['reason'] ?? 'Manually revoked by administrator';
        $server->updateLicenseStatus($key, 'revoked', (string)$reason);
        echo "License {$key} has been REVOKED.\n";
        break;

    case 'suspend':
        $key = $argv[2] ?? '';
        if (!$key) die("Error: License key required.\n");
        $reason = $opts['reason'] ?? 'Suspended by administrator';
        $server->updateLicenseStatus($key, 'suspended', (string)$reason);
        echo "License {$key} has been SUSPENDED.\n";
        break;

    case 'reactivate':
        $key = $argv[2] ?? '';
        if (!$key) die("Error: License key required.\n");
        $server->updateLicenseStatus($key, 'active', 'Reactivated by administrator');
        echo "License {$key} has been REACTIVATED.\n";
        break;

    case 'public-key':
        echo "\n----- GROCO LICENSE RSA PUBLIC VERIFICATION KEY -----\n";
        echo $server->getPublicKey() . "\n";
        break;

    default:
        echo "Commands:\n";
        echo "  create --customer=NAME --email=EMAIL [--domains=d1,d2] [--limit=1] [--expires=YYYY-MM-DD]\n";
        echo "  list\n";
        echo "  inspect <KEY>\n";
        echo "  revoke <KEY> [--reason=REASON]\n";
        echo "  suspend <KEY> [--reason=REASON]\n";
        echo "  reactivate <KEY>\n";
        echo "  public-key\n";
        break;
}
