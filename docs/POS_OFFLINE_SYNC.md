# GroCo Supermarket POS — Offline Mode & Synchronization Engine (2026)

## 1. Offline Operation Principles

Supermarket cash counters cannot halt operations when Internet connectivity experiences latency, packet loss, or temporary outages.

The GroCo POS system provides full offline tolerance with zero transaction loss:

1. **Continuous Connection Monitoring**:
   - Listens to browser `online` and `offline` events.
   - Updates UI badge from `Online ●` to `Offline Mode ● (N pending)`.

2. **Client-Side Outbox Storage**:
   - Completed checkout transactions are encoded as JSON payloads.
   - Assigned a unique client UUID: `term-TIMESTAMP-RANDOM`.
   - Appended to `localStorage.getItem('groco_pos_offline_queue')`.

3. **Background Auto-Sync Worker**:
   - Triggers immediately when connection is restored.
   - Periodically evaluates the outbox queue every 15 seconds.
   - Batches offline payloads and submits them to `POST /api/v1/pos/sync`.

4. **Server Idempotency Guarantee**:
   - `pos_transactions.client_uuid` has a unique database index.
   - If a transaction with the same UUID is submitted multiple times, the server detects the idempotency hit and returns the existing transaction without duplicate charges or stock double-deductions.
