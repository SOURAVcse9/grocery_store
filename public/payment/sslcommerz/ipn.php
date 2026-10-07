<?php
/**
 * ==============================================================================
 * public/payment/sslcommerz/ipn.php — Server-to-Server IPN Listener
 * ==============================================================================
 * Instant Payment Notification (IPN) webhook handler.
 *
 * CRITICAL REQUIREMENTS:
 * - Server-to-server webhook: Never requires customer browser sessions or cookies.
 * - Idempotent processing: Safe against retries, duplicate deliveries, or race conditions
 *   with customer return callbacks.
 * - Authoritative validation: Verifies val_id directly against SSLCOMMERZ validation server.
 * ==============================================================================
 */

declare(strict_types=1);

// Disable output buffering and display errors to guarantee clean webhook responses
ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/dbconnect.php';
require_once dirname(__DIR__, 2) . '/includes/PaymentService.php';

header('Content-Type: application/json; charset=UTF-8');

// IPN is sent via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'FAILED', 'message' => 'Method Not Allowed. IPN expects POST.']);
    exit;
}

// Read POST payload
$rawInput = file_get_contents('php://input');
$payload = $_POST;

if (empty($payload) && !empty($rawInput)) {
    // Attempt parse JSON or form-encoded body
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    } else {
        parse_str($rawInput, $payload);
    }
}

$valId  = trim((string)($payload['val_id'] ?? ''));
$tranId = trim((string)($payload['tran_id'] ?? ''));

if ($valId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'FAILED', 'message' => 'val_id parameter is missing from IPN notification.']);
    exit;
}

try {
    $pdo = db();
    $paymentService = new PaymentService($pdo);

    // Execute server-to-server authoritative validation & finalization
    $result = $paymentService->validateAndFinalizePayment($valId, $tranId !== '' ? $tranId : null, $payload, 'ipn');

    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'status'  => 'SUCCESS',
            'message' => $result['message'],
            'tran_id' => $tranId,
            'val_id'  => $valId
        ]);
        exit;
    }

    // Validation rejected or mismatch
    http_response_code(422);
    echo json_encode([
        'status'  => 'REJECTED',
        'message' => $result['message'],
        'tran_id' => $tranId,
        'val_id'  => $valId
    ]);
    exit;

} catch (Throwable $e) {
    error_log('[SSLCOMMERZ IPN Error] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status'  => 'ERROR',
        'message' => 'Server error processing IPN notification.'
    ]);
    exit;
}
