# GroCo Supermarket POS — Multi-Tender Split Payment Engine (2026)

## 1. Supported Payment Tenders

The GroCo POS system supports multi-tender split payments across multiple concurrent methods:

1. **Cash (BDT)**: Includes real-time change calculation and quick tender presets.
2. **Debit / Credit Card**: Visa, MasterCard, Amex, Nexus. Captures last 4 digits, bank, and terminal auth reference.
3. **Mobile Financial Services (MFS)**: bKash, Nagad, Rocket. Captures transaction ID.
4. **Customer Wallet Credit**: Deducts directly from user's verified wallet balance.
5. **Bank Transfer**: Direct bank wire with bank name and remittance reference.

## 2. Split Payment Calculation & Validation Rules

```
Total Entered = Cash + Card + MFS + Wallet + Bank Transfer
Remaining Due = max(0, Grand Total - Total Entered)
Change Due    = max(0, Cash - max(0, Grand Total - NonCashTenders))
```

- If `Total Entered < Grand Total - 0.01`, the `Confirm Sale` CTA button remains disabled.
- Wallet tender is validated against `users.wallet_balance` via `SELECT ... FOR UPDATE` before confirming checkout.
