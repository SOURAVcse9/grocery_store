# GroCo Supermarket POS — REST API v1 Specification (2026)

## 1. POS API Routes Reference

Base URL: `/api/v1/pos/`

| Endpoint | HTTP Method | Auth Required | Description |
| :--- | :--- | :--- | :--- |
| `GET /api/v1/pos/products` | GET | Token / Admin | Fast catalog search by keyword, SKU, or category |
| `GET /api/v1/pos/products/barcode/{code}` | GET | Token / Admin | Sub-50ms barcode/SKU scanner direct lookup |
| `POST /api/v1/pos/transactions` | POST | Token / Admin | Execute and commit atomic retail sales transaction |
| `GET /api/v1/pos/transactions/{id}` | GET | Token / Admin | Retrieve transaction details, items, and tender breakdown |
| `POST /api/v1/pos/sync` | POST | Token / Admin | Batch offline synchronization with conflict resolution |
| `POST /api/v1/pos/shifts/open` | POST | Token / Admin | Initialize new cashier shift drawer |
| `POST /api/v1/pos/shifts/close` | POST | Token / Admin | Close cashier shift with Z-Reading reconciliation |
| `GET /api/v1/pos/shifts/current` | GET | Token / Admin | Fetch active shift performance and drawer status |
| `POST /api/v1/pos/cash-movements` | POST | Token / Admin | Record petty cash in / out drawer movement |
| `POST /api/v1/pos/returns` | POST | Token / Admin | Execute itemized return and refund payout |
| `GET /api/v1/pos/receipt` | GET | Token / Admin | Generate dynamic thermal receipt data object |

## 2. Sample Request & Response Payloads

### `POST /api/v1/pos/transactions`
```json
{
  "store_id": 1,
  "register_id": 1,
  "terminal_id": 1,
  "shift_id": 12,
  "cashier_id": 1,
  "customer_id": 234,
  "items": [
    {
      "id": 15,
      "quantity": 2.500,
      "price": 85.00,
      "discount": 0.00,
      "price_override": false
    }
  ],
  "cart_discount": 10.00,
  "payments": [
    {
      "method": "cash",
      "amount": 100.00
    },
    {
      "method": "card",
      "amount": 102.50,
      "card_type": "Visa",
      "card_last_four": "1234",
      "card_auth_code": "AUTH-8921",
      "card_bank": "City Bank"
    }
  ],
  "client_uuid": "term-1791055895-a1b2c3",
  "notes": "Express checkout lane 1"
}
```

#### Response (`200 OK`):
```json
{
  "status": "success",
  "message": "POS Transaction completed successfully.",
  "data": {
    "transaction_id": 84,
    "transaction_number": "GR-20261004-9B2F10",
    "order_id": 230,
    "order_number": "POS-20261004-8912",
    "total_amount": 202.50,
    "paid_amount": 202.50,
    "change_amount": 0.00
  }
}
```
