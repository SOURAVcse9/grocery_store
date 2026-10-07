# GroCo Grocery Store — SSLCOMMERZ V4 Official Integration Guide

## 1. Official SSLCOMMERZ V4 API Endpoints

GroCo communicates with official SSLCOMMERZ endpoints verified against standard V4 gateway documentation:

### Sandbox Environment
- **Session Initialization (POST)**: `https://sandbox.sslcommerz.com/gwprocess/v4/api.php`
- **Order Validation API (GET)**: `https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php`
- **Merchant Trans ID Query API (GET)**: `https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php`
- **Refund API (GET)**: `https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php`
- **Refund Query API (GET)**: `https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php`

### Production Environment
- **Session Initialization (POST)**: `https://securepay.sslcommerz.com/gwprocess/v4/api.php`
- **Order Validation API (GET)**: `https://securepay.sslcommerz.com/validator/api/validationserverAPI.php`
- **Merchant Trans ID Query API (GET)**: `https://securepay.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php`
- **Refund API (GET)**: `https://securepay.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php`
- **Refund Query API (GET)**: `https://securepay.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php`

---

## 2. Environment Configuration (.env)

Merchant store credentials and endpoint routing are loaded dynamically from environment variables:

```ini
# ==============================================================================
# SSLCOMMERZ V4 Payment Gateway Integration
# ==============================================================================
PAYMENT_ENV=sandbox
SSLCOMMERZ_IS_SANDBOX=true
SSLCOMMERZ_STORE_ID=testbox
SSLCOMMERZ_STORE_PASSWORD=qwerty
SSLCOMMERZ_CURRENCY=BDT
SSLCOMMERZ_SUCCESS_URL=http://localhost:8080/grocery-store/public/payment/sslcommerz/success.php
SSLCOMMERZ_FAIL_URL=http://localhost:8080/grocery-store/public/payment/sslcommerz/fail.php
SSLCOMMERZ_CANCEL_URL=http://localhost:8080/grocery-store/public/payment/sslcommerz/cancel.php
SSLCOMMERZ_IPN_URL=http://localhost:8080/grocery-store/public/payment/sslcommerz/ipn.php
```

> [!WARNING]
> Real merchant credentials (`store_id` and `store_passwd`) must **NEVER** be committed to Git. The `.gitignore` file enforces exclusion of the authoritative `.env` file.

---

## 3. Session Initiation Parameters (Request Schema)

When `SSLCommerzService::createSession()` compiles the gateway payload, the following parameters are validated:

| Parameter | Type | Required | Description |
|---|---|---|---|
| `store_id` | String | Yes | Merchant Store ID assigned by SSLCOMMERZ |
| `store_passwd` | String | Yes | Merchant Store Password |
| `total_amount` | Decimal String | Yes | Exact order total formatted as `1250.00` |
| `currency` | String | Yes | Strictly `BDT` |
| `tran_id` | String | Yes | Server-generated unique ID (Max 30 characters) |
| `success_url` | URL String | Yes | Browser return URL on transaction completion |
| `fail_url` | URL String | Yes | Browser return URL on transaction decline |
| `cancel_url` | URL String | Yes | Browser return URL on customer cancellation |
| `ipn_url` | URL String | Yes | Server-to-server webhook callback |
| `cus_name` | String | Yes | Customer full name (Max 50 chars) |
| `cus_email` | String | Yes | Customer valid email (Max 50 chars) |
| `cus_phone` | String | Yes | Customer Bangladesh phone number (Max 20 chars) |
| `cus_add1` | String | Yes | Delivery street address |
| `cus_city` | String | Yes | City / district (e.g. Dhaka) |
| `cus_country` | String | Yes | `Bangladesh` |
| `shipping_method` | String | Yes | `Courier` or `NO` |
| `num_of_item` | Integer | Yes | Count of items in shipment |
| `product_name` | String | Yes | Summary of basket items |
| `product_category`| String | Yes | `Grocery` |
| `product_profile` | String | Yes | `physical-goods` |
| `value_a` | String | Optional | Internal GroCo Order ID |
| `value_b` | String | Optional | Customer User ID |
| `value_c` | String | Optional | Payment Attempt ID |

---

## 4. Order Validation API Verification Flow

Upon return or webhook reception, `public/includes/PaymentService.php` queries the validation endpoint:

```php
$queryParams = [
    'val_id'       => $valId,
    'store_id'     => $this->storeId,
    'store_passwd' => $this->storePasswd,
    'v'            => '1',
    'format'       => 'json'
];
```

The server parses the JSON response and enforces:
1. Gateway status must be `VALID` or `VALIDATED`.
2. Response `tran_id` must match `payments.tran_id`.
3. Response `currency` must match `payments.currency` (`BDT`).
4. Response `amount` must match `payments.amount` within 0.01 tolerance.
5. If risk score indicates elevated fraud (`risk_level == '1'`), record audit log for administrative review.
