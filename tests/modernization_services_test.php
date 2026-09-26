<?php
/**
 * Modernization Services Automated Verification Test Suite
 * GroCo Grocery Store Platform
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!defined('GROCO_CLI_TEST_MODE')) {
    define('GROCO_CLI_TEST_MODE', true);
}

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../public/includes/MediaService.php';
require_once __DIR__ . '/../public/includes/CacheService.php';
require_once __DIR__ . '/../public/includes/SearchService.php';
require_once __DIR__ . '/../public/includes/QueueService.php';
require_once __DIR__ . '/../public/includes/EmailService.php';
require_once __DIR__ . '/../public/includes/LoggerService.php';
require_once __DIR__ . '/../public/api/v1/ApiResponse.php';

echo "====================================================================\n";
echo " GROCO GROCERY STORE — MODERNIZATION SERVICES VERIFICATION SUITE   \n";
echo "====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $title, bool $condition, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] {$title}\n";
        if ($details) echo "       -> {$details}\n";
        $passCount++;
    } else {
        echo "[FAIL] {$title}\n";
        if ($details) echo "       -> Error: {$details}\n";
        $failCount++;
    }
}

// 1. MediaService Tests
echo "\n--- 1. MEDIASERVICE & IMAGE PIPELINE ---\n";
$testMediaUrl = MediaService::getUrl('dada rice.webp', ['w' => 600, 'h' => 600, 'c' => 'fill'], 'products');
assertTest(
    "MediaService generates valid asset URL",
    !empty($testMediaUrl) && (strpos($testMediaUrl, 'uploads/products/') !== false || strpos($testMediaUrl, 'res.cloudinary.com') !== false),
    "URL generated: {$testMediaUrl}"
);

$responsiveUrls = MediaService::getResponsiveUrls('dada rice.webp', [300, 600, 900], 'products');
assertTest(
    "MediaService builds responsive multi-width array",
    isset($responsiveUrls[300], $responsiveUrls[600], $responsiveUrls[900]),
    "Widths 300w, 600w, 900w compiled"
);

$renderedImg = MediaService::renderResponsiveImage('dada rice.webp', 'Organic Rice', ['class' => 'product-img'], 'products');
assertTest(
    "MediaService renders accessible responsive HTML <img> tag with srcset & lazyloading",
    strpos($renderedImg, 'srcset=') !== false && strpos($renderedImg, 'loading="lazy"') !== false && strpos($renderedImg, 'alt="Organic Rice"') !== false,
    "Rendered: " . substr($renderedImg, 0, 80) . "..."
);

// 2. CacheService Tests
echo "\n--- 2. CACHESERVICE & TAGGED INVALIDATION ---\n";
CacheService::init();
CacheService::set('test_key_1', ['foo' => 'bar', 'time' => time()], 60, ['test_tag']);

$cachedVal = CacheService::get('test_key_1');
assertTest(
    "CacheService stores and retrieves structured data",
    is_array($cachedVal) && ($cachedVal['foo'] ?? '') === 'bar',
    "Data retrieved accurately from cache backend (" . CacheService::getActiveDriver() . ")"
);

$remembered = CacheService::remember('test_remember', 60, function() {
    return 'remembered_value_123';
}, ['test_tag']);
assertTest(
    "CacheService remember() executes callback and caches result",
    $remembered === 'remembered_value_123',
    "Remember returned expected value"
);

CacheService::invalidateTag('test_tag');
$afterInvalidation = CacheService::get('test_key_1');
assertTest(
    "CacheService tag invalidation successfully purges tagged keys",
    $afterInvalidation === null,
    "Key properly purged after invalidateTag('test_tag')"
);

// 3. QueueService Tests
echo "\n--- 3. QUEUESERVICE & ASYNC JOBS ---\n";
QueueService::init();
QueueService::clear();
$testJobExecuted = false;
$GLOBALS['test_queue_ran'] = false;

function sample_test_job_handler(array $payload) {
    $GLOBALS['test_queue_ran'] = true;
}

$jobId = QueueService::push('sample_test_job_handler', ['user_id' => 99, 'email' => 'test@groco.site']);
assertTest(
    "QueueService pushes job with unique ID",
    strpos($jobId, 'job_') === 0,
    "Job ID: {$jobId}"
);

$processResult = QueueService::processNext();
assertTest(
    "QueueService executes queued job handler",
    $processResult !== null && $processResult['success'] === true && $GLOBALS['test_queue_ran'] === true,
    "Job executed successfully"
);

// 4. EmailService Tests
echo "\n--- 4. EMAILSERVICE & TEMPLATE RENDERING ---\n";
$emailHtml = EmailService::renderTemplate(
    'Order Confirmation',
    'Hello Alice,',
    '<p>Your order #ORD-9999 has been received.</p>',
    'https://groco.site.je/orders.php?id=9999',
    'View Order Details'
);
assertTest(
    "EmailService renders responsive HTML template with branding and CTA",
    strpos($emailHtml, 'GroCo Grocery Store') !== false &&
    strpos($emailHtml, 'ORD-9999') !== false &&
    strpos($emailHtml, 'View Order Details') !== false,
    "Email template compiled with zero syntax issues"
);

$sendResult = EmailService::send('test@groco.site', 'Test Subject', $emailHtml);
assertTest(
    "EmailService dispatches email via provider or safe local log fallback",
    $sendResult === true,
    "Email accepted for delivery / logged safely"
);

// 5. LoggerService Tests
echo "\n--- 5. LOGGERSERVICE & SECRET REDACTION ---\n";
LoggerService::init();
LoggerService::info("Test customer login event", [
    'customer_email' => 'alice@test.groco',
    'password'       => 'SuperSecretPassword123!',
    'csrf_token'     => 'a1b2c3d4e5f6',
    'cart_items'     => 3
]);

$logFile = dirname(__DIR__) . '/storage/logs/app_' . date('Y-m-d') . '.log';
$logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
assertTest(
    "LoggerService writes structured JSON log with automated secret redaction",
    strpos($logContent, 'Test customer login event') !== false &&
    strpos($logContent, '******** [REDACTED]') !== false &&
    strpos($logContent, 'SuperSecretPassword123!') === false,
    "Secret properly redacted from JSON log file"
);

// 6. SearchService Tests
echo "\n--- 6. SEARCHSERVICE AUTOCOMPLETE & FACETED LOGIC ---\n";
$pdo = Database::getConnection();
$searchService = new SearchService($pdo);

$autocompleteResults = $searchService->autocomplete('ri', 5);
assertTest(
    "SearchService autocomplete returns structured array",
    is_array($autocompleteResults),
    "Results count: " . count($autocompleteResults)
);

$searchResult = $searchService->search('rice', [], 1, 10);
assertTest(
    "SearchService executes faceted query returning items & metadata",
    isset($searchResult['items'], $searchResult['total']),
    "Total matching items: " . $searchResult['total']
);

// 7. POS Idempotency Header Verification
echo "\n--- 7. POS IDEMPOTENCY & REST API RESPONSES ---\n";
$reqId = ApiResponse::getRequestId();
assertTest(
    "ApiResponse generates formatted X-Request-Id",
    strpos($reqId, 'req_') === 0,
    "Request ID: {$reqId}"
);

// Summary
echo "\n====================================================================\n";
echo " MODERNIZATION RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
