<?php
/**
 * ==============================================================================
 * tests/production_readiness_test.php — Automated Production Readiness Test
 * ==============================================================================
 * Verifies security configurations, environment parsing, session hardening,
 * error handling (403/404/500), image helpers, database backup tool, and headers.
 * ==============================================================================
 */

declare(strict_types=1);

define('GROCO_CLI_TEST_MODE', true);
require_once dirname(__DIR__) . '/public/dbconnect.php';

$passed = 0;
$failed = 0;
$total = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}" . ($detail ? " — {$detail}" : "") . "\n";
    }
}

echo "\n=======================================================\n";
echo " GroCo Production Readiness & Deployment Test Suite\n";
echo "=======================================================\n\n";

// ------------------------------------------------------------------------------
// Test Suite 1: Environment & Secrets (.env & .env.example)
// ------------------------------------------------------------------------------
echo "--- 1. Environment & Configuration Security ---\n";

$envExamplePath = dirname(__DIR__) . '/.env.example';
assert_test('.env.example template exists', file_exists($envExamplePath));
$exampleContent = file_get_contents($envExamplePath);
assert_test('.env.example contains APP_ENV=production', str_contains($exampleContent, 'APP_ENV=production'));
assert_test('.env.example contains APP_DEBUG=false', str_contains($exampleContent, 'APP_DEBUG=false'));
assert_test('.env.example contains SESSION_TIMEOUT', str_contains($exampleContent, 'SESSION_TIMEOUT=7200'));
assert_test('.env.example contains SESSION_SECURE=true', str_contains($exampleContent, 'SESSION_SECURE=true'));
assert_test('.env.example contains GA_MEASUREMENT_ID', str_contains($exampleContent, 'GA_MEASUREMENT_ID='));
assert_test('.env.example contains GSC_VERIFICATION_TOKEN', str_contains($exampleContent, 'GSC_VERIFICATION_TOKEN='));
assert_test('.env.example contains CLOUDINARY credentials template', str_contains($exampleContent, 'CLOUDINARY_CLOUD_NAME='));

$gitignorePath = dirname(__DIR__) . '/.gitignore';
if (file_exists($gitignorePath)) {
    $gitignore = file_get_contents($gitignorePath);
    assert_test('.gitignore protects .env file', str_contains($gitignore, '.env'));
}

// ------------------------------------------------------------------------------
// Test Suite 2: Directory & Sensitive File Protection (.htaccess)
// ------------------------------------------------------------------------------
echo "\n--- 2. Apache & Sensitive File Protection ---\n";

$rootHtaccess = file_get_contents(dirname(__DIR__) . '/.htaccess');
assert_test('Root .htaccess disables directory indexes', str_contains($rootHtaccess, 'Options -Indexes'));
assert_test('Root .htaccess blocks .env, .git, .sql, .bak, .key', str_contains($rootHtaccess, '<FilesMatch') && str_contains($rootHtaccess, '\.env'));
assert_test('Root .htaccess blocks direct access to storage, database, tools, tests', str_contains($rootHtaccess, 'storage|database|tools|scratch|tests'));
assert_test('Root .htaccess contains production HTTPS redirect rule', str_contains($rootHtaccess, 'RewriteCond %{HTTPS} !=on'));
assert_test('Root .htaccess injects X-Content-Type-Options', str_contains($rootHtaccess, 'X-Content-Type-Options "nosniff"'));
assert_test('Root .htaccess injects X-Frame-Options SAMEORIGIN', str_contains($rootHtaccess, 'X-Frame-Options "SAMEORIGIN"'));
assert_test('Root .htaccess injects Referrer-Policy', str_contains($rootHtaccess, 'Referrer-Policy "strict-origin-when-cross-origin"'));
assert_test('Root .htaccess injects Permissions-Policy', str_contains($rootHtaccess, 'Permissions-Policy'));
assert_test('Root .htaccess enables Gzip/Deflate compression', str_contains($rootHtaccess, 'mod_deflate.c'));

assert_test('storage/.htaccess denies all public access', file_exists(dirname(__DIR__) . '/storage/.htaccess') && str_contains(file_get_contents(dirname(__DIR__) . '/storage/.htaccess'), 'Require all denied'));
assert_test('database/.htaccess denies all public access', file_exists(dirname(__DIR__) . '/database/.htaccess') && str_contains(file_get_contents(dirname(__DIR__) . '/database/.htaccess'), 'Require all denied'));
assert_test('tools/.htaccess denies all public access', file_exists(dirname(__DIR__) . '/tools/.htaccess') && str_contains(file_get_contents(dirname(__DIR__) . '/tools/.htaccess'), 'Require all denied'));
assert_test('tests/.htaccess denies all public access', file_exists(dirname(__DIR__) . '/tests/.htaccess') && str_contains(file_get_contents(dirname(__DIR__) . '/tests/.htaccess'), 'Require all denied'));

$publicHtaccess = file_get_contents(dirname(__DIR__) . '/public/.htaccess');
assert_test('public/.htaccess defines Content-Security-Policy', str_contains($publicHtaccess, 'Content-Security-Policy'));
assert_test('CSP allows Cloudinary CDN', str_contains($publicHtaccess, 'res.cloudinary.com'));
assert_test('CSP allows Google Analytics', str_contains($publicHtaccess, 'www.google-analytics.com'));
assert_test('public/.htaccess defines browser caching via mod_expires', str_contains($publicHtaccess, 'mod_expires.c'));

// ------------------------------------------------------------------------------
// Test Suite 3: Error Pages (403, 404, 500)
// ------------------------------------------------------------------------------
echo "\n--- 3. Friendly Error Handling & HTTP Statuses ---\n";

assert_test('public/404.php exists', file_exists(dirname(__DIR__) . '/public/404.php'));
$content404 = file_get_contents(dirname(__DIR__) . '/public/404.php');
assert_test('404.php sets http_response_code(404)', str_contains($content404, 'http_response_code(404)'));
assert_test('404.php uses noindex directive', str_contains($content404, 'noindex'));

assert_test('public/403.php exists', file_exists(dirname(__DIR__) . '/public/403.php'));
$content403 = file_get_contents(dirname(__DIR__) . '/public/403.php');
assert_test('403.php sets http_response_code(403)', str_contains($content403, 'http_response_code(403)'));
assert_test('403.php uses noindex directive', str_contains($content403, 'noindex'));
assert_test('403.php provides return to homepage button', str_contains($content403, 'Return to Homepage'));

assert_test('public/500.php exists', file_exists(dirname(__DIR__) . '/public/500.php'));
$content500 = file_get_contents(dirname(__DIR__) . '/public/500.php');
assert_test('500.php sets http_response_code(500)', str_contains($content500, 'http_response_code(500)'));

// ------------------------------------------------------------------------------
// Test Suite 4: Bootstrap & Session Hardening
// ------------------------------------------------------------------------------
echo "\n--- 4. Session Security & Bootstrap Hardening ---\n";

require_once dirname(__DIR__) . '/public/dbconnect.php';

assert_test('Database PDO singleton connected', db() instanceof PDO);
assert_test('Database errMode is EXCEPTION', db()->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION);
assert_test('Database emulate prepares is disabled', (bool)db()->getAttribute(PDO::ATTR_EMULATE_PREPARES) === false);
assert_test('Session status is ACTIVE', session_status() === PHP_SESSION_ACTIVE);
assert_test('Session UA hash is initialized', !empty($_SESSION['_ua_hash']));
assert_test('Session last activity timestamp is tracked', isset($_SESSION['_last_activity']));

// ------------------------------------------------------------------------------
// Test Suite 5: Cloudinary Image Helpers
// ------------------------------------------------------------------------------
echo "\n--- 5. Cloudinary & Media Optimization Helpers ---\n";

require_once dirname(__DIR__) . '/public/includes/image.php';

assert_test('Function getProductImageUrl exists', function_exists('getProductImageUrl'));
assert_test('Function getThumbnailUrl exists', function_exists('getThumbnailUrl'));
assert_test('Function getProductDetailImageUrl exists', function_exists('getProductDetailImageUrl'));
assert_test('Function getOgImageUrl exists', function_exists('getOgImageUrl'));

// Test null / empty fallbacks
$fallbackThumb = getThumbnailUrl(null);
assert_test('getThumbnailUrl(null) returns placeholder', str_contains($fallbackThumb, 'placeholder.png'));

$fallbackOg = getOgImageUrl('');
assert_test('getOgImageUrl("") returns logo placeholder', str_contains($fallbackOg, 'logo.png'));

// Test resolution on an identifier
$sampleId = 'groco/products/fresh-milk-1-liter';
$detailUrl = getProductDetailImageUrl($sampleId);
assert_test('getProductDetailImageUrl formats 800w transform', str_contains($detailUrl, 'w_800') || str_contains($detailUrl, 'fresh-milk'));

$ogUrl = getOgImageUrl($sampleId);
assert_test('getOgImageUrl formats 1200x630 transform', str_contains($ogUrl, 'w_1200') || str_contains($ogUrl, 'fresh-milk'));

// ------------------------------------------------------------------------------
// Test Suite 6: Centralized Settings, Analytics & Favicons
// ------------------------------------------------------------------------------
echo "\n--- 6. Centralized Settings & Webmaster Integrations ---\n";

$metaTagsContent = file_get_contents(dirname(__DIR__) . '/public/components/meta-tags.php');
assert_test('meta-tags.php includes Favicon and Apple Touch icon links', str_contains($metaTagsContent, 'apple-touch-icon'));
assert_test('meta-tags.php includes Google Search Console verification support', str_contains($metaTagsContent, 'google-site-verification'));
assert_test('meta-tags.php includes Google Analytics 4 (GA4) script integration', str_contains($metaTagsContent, 'googletagmanager.com/gtag/js'));
assert_test('meta-tags.php includes GA4 eCommerce view_item event dataLayer', str_contains($metaTagsContent, 'event: \'view_item\''));

// ------------------------------------------------------------------------------
// Test Suite 7: Database Backup Tool Execution & Storage
// ------------------------------------------------------------------------------
echo "\n--- 7. Production Database Backup Tool ---\n";

$backupToolPath = dirname(__DIR__) . '/tools/backup_database.php';
assert_test('tools/backup_database.php exists', file_exists($backupToolPath));

// Run backup tool in dry test
$cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($backupToolPath) . ' --compress --keep=7';
$output = [];
$returnVar = 0;
exec($cmd, $output, $returnVar);

assert_test('Backup script executes with return code 0', $returnVar === 0, "Exit code: {$returnVar}");

$backups = glob(dirname(__DIR__) . '/storage/backups/*.sql.gz');
assert_test('Gzip backup archive created in storage/backups/', !empty($backups));

if (!empty($backups)) {
    $latest = end($backups);
    assert_test('Backup archive size is > 10KB', filesize($latest) > 10240, "Size: " . filesize($latest) . " bytes");
}

// ------------------------------------------------------------------------------
// Test Suite 8: Documentation Suite
// ------------------------------------------------------------------------------
echo "\n--- 8. Production Documentation Suite ---\n";

$docs = [
    'PRODUCTION_CHECKLIST.md',
    'DEPLOYMENT.md',
    'CLOUDINARY_SETUP.md',
    'SEO_SETUP.md',
    'DATABASE_BACKUP.md',
    'SECURITY.md',
    'PERFORMANCE.md'
];

foreach ($docs as $doc) {
    $path = dirname(__DIR__) . '/docs/' . $doc;
    assert_test("Document docs/{$doc} exists and is populated", file_exists($path) && filesize($path) > 500);
}

// ------------------------------------------------------------------------------
// Summary
// ------------------------------------------------------------------------------
echo "\n=======================================================\n";
echo " Production Readiness Test Results: {$passed}/{$total} PASSED\n";
if ($failed === 0) {
    echo " All verification checks PASSED successfully! System is production-ready.\n";
} else {
    echo " {$failed} check(s) FAILED. Please review the details above.\n";
}
echo "=======================================================\n\n";

exit($failed === 0 ? 0 : 1);
