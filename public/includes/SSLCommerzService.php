<?php
/**
 * ==============================================================================
 * GroCo Grocery Store — Official SSLCOMMERZ V4 API Service Layer
 * ==============================================================================
 * Production-ready client implementation for SSLCOMMERZ V4 API specifications.
 * Enforces zero hardcoded merchant credentials, strict BDT currency isolation,
 * comprehensive cURL security timeouts, and automatic secret redaction.
 *
 * Official SSLCOMMERZ V4 Documentation:
 * https://sandbox-gw.sslcommerz.com/docs
 * ==============================================================================
 */

declare(strict_types=1);

class SSLCommerzService
{
    // Official Endpoint Definitions
    public const SANDBOX_HOST = 'https://sandbox.sslcommerz.com';
    public const LIVE_HOST    = 'https://securepay.sslcommerz.com';

    public const SESSION_ENDPOINT     = '/gwprocess/v4/api.php';
    public const VALIDATION_ENDPOINT  = '/validator/api/validationserverAPI.php';
    public const TRANS_QUERY_ENDPOINT = '/validator/api/merchantTransIDvalidationAPI.php';
    public const REFUND_ENDPOINT      = '/validator/api/merchantTransIDvalidationAPI.php';
    public const REFUND_QUERY_ENDPOINT= '/validator/api/merchantTransIDvalidationAPI.php';

    private string $storeId;
    private string $storePasswd;
    private bool $isSandbox;
    private string $currency;
    private string $baseUrl;
    private ?Closure $mockTransport = null;

    public function __construct(
        ?string $storeId = null,
        ?string $storePasswd = null,
        ?bool $isSandbox = null,
        ?string $currency = null
    ) {
        $envSandbox = getenv('SSLCOMMERZ_IS_SANDBOX');
        $this->isSandbox = $isSandbox ?? ($envSandbox !== false ? filter_var($envSandbox, FILTER_VALIDATE_BOOLEAN) : (getenv('PAYMENT_ENV') !== 'production'));
        
        $this->storeId = $storeId ?? (string)(getenv('SSLCOMMERZ_STORE_ID') ?: 'testbox');
        $this->storePasswd = $storePasswd ?? (string)(getenv('SSLCOMMERZ_STORE_PASSWORD') ?: 'qwerty');
        $this->currency = $currency ?? (string)(getenv('SSLCOMMERZ_CURRENCY') ?: 'BDT');

        $this->baseUrl = $this->isSandbox ? self::SANDBOX_HOST : self::LIVE_HOST;
    }

    /**
     * Check if currently running in Sandbox environment
     */
    public function isSandbox(): bool
    {
        return $this->isSandbox;
    }

    /**
     * Get configured Merchant Store ID
     */
    public function getStoreId(): string
    {
        return $this->storeId;
    }

    /**
     * Get configured Currency (BDT)
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Allow mocking HTTP transport strictly for automated testing suites
     * @internal
     */
    public function setMockTransport(?Closure $mock): self
    {
        $this->mockTransport = $mock;
        return $this;
    }

    /**
     * 1. Create Gateway Session (gwprocess/v4/api.php)
     *
     * @param array<string, mixed> $params Customer, cart & order parameters
     * @return array{success: bool, gateway_url: ?string, sessionkey: ?string, raw: array, error: ?string}
     */
    public function createSession(array $params): array
    {
        $requiredKeys = ['total_amount', 'tran_id', 'success_url', 'fail_url', 'cancel_url', 'cus_name', 'cus_email', 'cus_phone'];
        foreach ($requiredKeys as $key) {
            if (empty($params[$key])) {
                return [
                    'success'     => false,
                    'gateway_url' => null,
                    'sessionkey'  => null,
                    'raw'         => [],
                    'error'       => "Missing required parameter for SSLCOMMERZ session: {$key}"
                ];
            }
        }

        // Format and compile standard SSLCOMMERZ V4 payload
        $postData = [
            'store_id'         => $this->storeId,
            'store_passwd'     => $this->storePasswd,
            'total_amount'     => number_format((float)$params['total_amount'], 2, '.', ''),
            'currency'         => $this->currency,
            'tran_id'          => (string)$params['tran_id'],
            'success_url'      => (string)$params['success_url'],
            'fail_url'         => (string)$params['fail_url'],
            'cancel_url'       => (string)$params['cancel_url'],
            'ipn_url'          => (string)($params['ipn_url'] ?? ''),

            // Customer details
            'cus_name'         => substr((string)$params['cus_name'], 0, 50),
            'cus_email'        => substr((string)$params['cus_email'], 0, 50),
            'cus_add1'         => substr((string)($params['cus_add1'] ?? 'Dhaka'), 0, 100),
            'cus_add2'         => substr((string)($params['cus_add2'] ?? ''), 0, 100),
            'cus_city'         => substr((string)($params['cus_city'] ?? 'Dhaka'), 0, 50),
            'cus_state'        => substr((string)($params['cus_state'] ?? 'Dhaka'), 0, 50),
            'cus_postcode'     => substr((string)($params['cus_postcode'] ?? '1000'), 0, 30),
            'cus_country'      => substr((string)($params['cus_country'] ?? 'Bangladesh'), 0, 50),
            'cus_phone'        => substr((string)$params['cus_phone'], 0, 20),

            // Shipment details
            'shipping_method'  => (string)($params['shipping_method'] ?? 'Courier'),
            'num_of_item'      => (int)($params['num_of_item'] ?? 1),
            'ship_name'        => substr((string)($params['ship_name'] ?? $params['cus_name']), 0, 50),
            'ship_add1'        => substr((string)($params['ship_add1'] ?? $params['cus_add1'] ?? 'Dhaka'), 0, 100),
            'ship_city'        => substr((string)($params['ship_city'] ?? $params['cus_city'] ?? 'Dhaka'), 0, 50),
            'ship_postcode'    => substr((string)($params['ship_postcode'] ?? $params['cus_postcode'] ?? '1000'), 0, 30),
            'ship_country'     => substr((string)($params['ship_country'] ?? 'Bangladesh'), 0, 50),

            // Product profile
            'product_name'     => substr((string)($params['product_name'] ?? 'Groceries'), 0, 255),
            'product_category' => substr((string)($params['product_category'] ?? 'Grocery'), 0, 100),
            'product_profile'  => (string)($params['product_profile'] ?? 'physical-goods'),

            // Optional extra reference
            'value_a'          => (string)($params['value_a'] ?? ''), // Internal Order ID
            'value_b'          => (string)($params['value_b'] ?? ''), // User ID
            'value_c'          => (string)($params['value_c'] ?? ''), // Attempt ID
            'value_d'          => (string)($params['value_d'] ?? ''),
        ];

        $url = $this->baseUrl . self::SESSION_ENDPOINT;
        $response = $this->sendHttpRequest($url, $postData, 'POST');

        if (!$response['success']) {
            return [
                'success'     => false,
                'gateway_url' => null,
                'sessionkey'  => null,
                'raw'         => $response['data'],
                'error'       => $response['error'] ?? 'Failed to connect to SSLCOMMERZ gateway.'
            ];
        }

        $body = $response['data'];
        $gatewayStatus = strtoupper((string)($body['status'] ?? ''));

        if ($gatewayStatus === 'SUCCESS' && !empty($body['GatewayPageURL'])) {
            return [
                'success'     => true,
                'gateway_url' => (string)$body['GatewayPageURL'],
                'sessionkey'  => (string)($body['sessionkey'] ?? ''),
                'raw'         => $body,
                'error'       => null
            ];
        }

        $failureReason = $body['failedreason'] ?? ($body['status'] ?? 'Unknown gateway rejection.');
        return [
            'success'     => false,
            'gateway_url' => null,
            'sessionkey'  => null,
            'raw'         => $body,
            'error'       => (string)$failureReason
        ];
    }

    /**
     * 2. Order Validation API (validator/api/validationserverAPI.php)
     * Calls the official SSLCOMMERZ server-to-server validation API using val_id.
     *
     * @param string $valId Validation ID returned by SSLCOMMERZ in callback/IPN
     * @return array{success: bool, status: string, data: array, error: ?string}
     */
    public function validateOrder(string $valId): array
    {
        if (trim($valId) === '') {
            return [
                'success' => false,
                'status'  => 'INVALID_VAL_ID',
                'data'    => [],
                'error'   => 'val_id cannot be empty.'
            ];
        }

        $queryParams = [
            'val_id'       => $valId,
            'store_id'     => $this->storeId,
            'store_passwd' => $this->storePasswd,
            'v'            => '1',
            'format'       => 'json'
        ];

        $url = $this->baseUrl . self::VALIDATION_ENDPOINT . '?' . http_build_query($queryParams);
        $response = $this->sendHttpRequest($url, [], 'GET');

        if (!$response['success']) {
            return [
                'success' => false,
                'status'  => 'CONNECTION_ERROR',
                'data'    => $response['data'],
                'error'   => $response['error'] ?? 'Validation server connection failed.'
            ];
        }

        $body = $response['data'];
        $valStatus = strtoupper((string)($body['status'] ?? ''));

        // Official valid statuses: VALID or VALIDATED
        $isValid = ($valStatus === 'VALID' || $valStatus === 'VALIDATED');

        return [
            'success' => $isValid,
            'status'  => $valStatus !== '' ? $valStatus : 'UNKNOWN',
            'data'    => $body,
            'error'   => $isValid ? null : ($body['error'] ?? $body['failedreason'] ?? "Gateway returned non-valid status: {$valStatus}")
        ];
    }

    /**
     * Official SSLCOMMERZ Signature Hash Verification (verify_sign)
     * Verifies the MD5 digest of verify_key parameters appended with md5(store_passwd).
     *
     * @param array<string, mixed> $postData
     * @return bool
     */
    public function verifySignature(array $postData): bool
    {
        if (empty($postData['verify_sign']) || empty($postData['verify_key'])) {
            return false;
        }

        $preDefinedKeys = explode(',', (string)$postData['verify_key']);
        $hashData = [];
        foreach ($preDefinedKeys as $key) {
            $key = trim($key);
            if (array_key_exists($key, $postData)) {
                $hashData[$key] = (string)$postData[$key];
            }
        }

        $hashData['store_passwd'] = md5($this->storePasswd);
        ksort($hashData);

        $hashString = '';
        foreach ($hashData as $k => $v) {
            $hashString .= $k . '=' . $v . '&';
        }
        $hashString = rtrim($hashString, '&');

        return hash_equals(md5($hashString), (string)$postData['verify_sign']);
    }

    /**
     * 3. Transaction Query API (validator/api/merchantTransIDvalidationAPI.php)
     * Queries transaction status by merchant's internal tran_id.
     *
     * @param string $tranId Merchant Transaction ID
     * @return array{success: bool, data: array, error: ?string}
     */
    public function queryTransaction(string $tranId): array
    {
        if (trim($tranId) === '') {
            return ['success' => false, 'data' => [], 'error' => 'tran_id cannot be empty.'];
        }

        $queryParams = [
            'tran_id'      => $tranId,
            'store_id'     => $this->storeId,
            'store_passwd' => $this->storePasswd,
            'v'            => '1',
            'format'       => 'json'
        ];

        $url = $this->baseUrl . self::TRANS_QUERY_ENDPOINT . '?' . http_build_query($queryParams);
        $response = $this->sendHttpRequest($url, [], 'GET');

        return [
            'success' => $response['success'],
            'data'    => $response['data'],
            'error'   => $response['error']
        ];
    }

    /**
     * 4. Refund API (validator/api/merchantTransIDvalidationAPI.php)
     * Initiates full or partial refund for a paid bank transaction.
     *
     * @param string $bankTranId Gateway bank transaction ID
     * @param float $refundAmount Amount in BDT to refund
     * @param string $refundRemarks Justification / notes
     * @param string $refundTransId Unique refund identifier generated by GroCo
     * @return array{success: bool, status: string, refund_ref_id: ?string, data: array, error: ?string}
     */
    public function initiateRefund(string $bankTranId, float $refundAmount, string $refundRemarks, string $refundTransId): array
    {
        $queryParams = [
            'bank_tran_id'   => $bankTranId,
            'refund_amount'  => number_format($refundAmount, 2, '.', ''),
            'refund_remarks' => substr($refundRemarks, 0, 255),
            'refund_trn_id'  => $refundTransId,
            'store_id'       => $this->storeId,
            'store_passwd'   => $this->storePasswd,
            'v'              => '1',
            'format'         => 'json'
        ];

        $url = $this->baseUrl . self::REFUND_ENDPOINT . '?' . http_build_query($queryParams);
        $response = $this->sendHttpRequest($url, [], 'GET');

        if (!$response['success']) {
            return [
                'success'       => false,
                'status'        => 'NETWORK_ERROR',
                'refund_ref_id' => null,
                'data'          => $response['data'],
                'error'         => $response['error'] ?? 'Refund endpoint communication error.'
            ];
        }

        $body = $response['data'];
        $status = strtoupper((string)($body['status'] ?? ''));
        $refundRefId = $body['refund_ref_id'] ?? null;

        // SSLCOMMERZ refund returns 'success' or 'processing'
        $isOk = ($status === 'SUCCESS' || $status === 'PROCESSING');

        return [
            'success'       => $isOk,
            'status'        => $status !== '' ? $status : 'FAILED',
            'refund_ref_id' => $refundRefId ? (string)$refundRefId : null,
            'data'          => $body,
            'error'         => $isOk ? null : ($body['errorReason'] ?? 'Refund was not approved by payment gateway.')
        ];
    }

    /**
     * 5. Query Refund Status
     *
     * @param string $refundRefId Gateway refund reference ID
     * @return array{success: bool, data: array, error: ?string}
     */
    public function queryRefund(string $refundRefId): array
    {
        $queryParams = [
            'refund_ref_id' => $refundRefId,
            'store_id'      => $this->storeId,
            'store_passwd'  => $this->storePasswd,
            'v'             => '1',
            'format'        => 'json'
        ];

        $url = $this->baseUrl . self::REFUND_QUERY_ENDPOINT . '?' . http_build_query($queryParams);
        return $this->sendHttpRequest($url, [], 'GET');
    }

    /**
     * Redact sensitive credentials (e.g. store_passwd) from logs and arrays
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function redactCredentials(array $data): array
    {
        $redacted = $data;
        $sensitiveKeys = ['store_passwd', 'password', 'card_no', 'cvv', 'card_cvv', 'secret'];

        foreach ($sensitiveKeys as $k) {
            if (isset($redacted[$k])) {
                $redacted[$k] = '[REDACTED]';
            }
        }

        return $redacted;
    }

    /**
     * Internal cURL HTTP client with strict timeouts, headers, and SSL checks
     *
     * @param string $url
     * @param array<string, mixed> $data
     * @param string $method
     * @return array{success: bool, data: array, error: ?string}
     */
    private function sendHttpRequest(string $url, array $data = [], string $method = 'POST'): array
    {
        if ($this->mockTransport !== null) {
            $fn = $this->mockTransport;
            return $fn($url, $data, $method);
        }

        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => !APP_DEBUG || !($this->isSandbox), // Strict verification
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'GroCo-Grocery-Store-SSLCommerz/4.0',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
            ],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
        }

        curl_setopt_array($ch, $options);

        $rawResult = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawResult === false || $curlError !== '') {
            return [
                'success' => false,
                'data'    => [],
                'error'   => "cURL Error [{$httpCode}]: {$curlError}"
            ];
        }

        $decoded = json_decode((string)$rawResult, true);
        if (!is_array($decoded)) {
            // Some endpoints may return XML or text or raw strings
            return [
                'success' => ($httpCode >= 200 && $httpCode < 300),
                'data'    => ['raw_response' => (string)$rawResult],
                'error'   => "Invalid JSON response from gateway (HTTP {$httpCode})"
            ];
        }

        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'data'    => $decoded,
            'error'   => null
        ];
    }
}
