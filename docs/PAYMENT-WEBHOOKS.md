# Payment webhooks

The system takes no card details. To accept online payments, a payment provider collects the money on its own secure pages and then notifies the hotel through a signed webhook. Each verified notification is recorded as a payment on the guest's folio. That payment is handled exactly like one taken at the front desk: it updates the balance, appears on receipts and invoices, and confirms a pending booking once the deposit is covered.

## How a notification is handled

1. **Receive.** The provider sends `POST /webhooks/payments/{gateway}`. Unknown or unconfigured gateways get a 404.
2. **Verify.** The signature and timestamp are checked. An invalid signature gets a 401 and is logged, and the request body is not stored.
3. **Store once.** The event is saved under its unique `(gateway, event id)`. A redelivered event gets a 200 and is not applied again.
4. **Apply.** A queued job applies the event. For `payment.succeeded`, it finds the reservation by reference and records the payment. The payment's idempotency key is `gateway:<name>:<event id>`, so retries cannot post the money twice. Other event types are stored and marked *ignored*.
5. **Handle problems.** If an event cannot be applied (for example an unknown booking reference, a missing transaction ID or an unsupported currency), it is marked **Needs attention** under **Finance → Online payments**. Staff with `payments.create` can retry it after fixing the cause. Transient errors are retried automatically with back-off.

Fields whose names suggest secrets or card data (`card`, `cvv`, `token`, `secret` and so on) are redacted before an event is stored.

## Enabling the built-in signed format

1. Generate a secret:

   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```

2. Configure `.env`:

   ```dotenv
   HMS_PAYMENT_WEBHOOK_ENABLED=true
   HMS_PAYMENT_WEBHOOK_SECRET=<the secret>
   HMS_PAYMENT_WEBHOOK_TOLERANCE=300   # seconds a signature stays valid
   ```

3. Give the provider the endpoint `https://<your-domain>/webhooks/payments/online` and the same secret.

### Request format

```http
POST /webhooks/payments/online
Content-Type: application/json
X-HMS-Signature: t=1762780800,v1=<hex HMAC-SHA256 of "1762780800.<raw body>" using the secret>

{
  "id": "evt_01J9X…",
  "type": "payment.succeeded",
  "data": {
    "reservation_reference": "MLX-7K2Q9D",
    "transaction_id": "txn_88231",
    "amount": "150.00",
    "currency": "USD"
  }
}
```

- `id` must be unique per event. It is the deduplication key.
- `amount` is a decimal string in `currency`, which must be `USD` or `LRD`. If it differs from the folio's currency, the payment is converted at the day's published rate, just like a desk payment.
- Payments are recorded with the `card` method by default. Change this with `HMS_PAYMENT_WEBHOOK_METHOD`, for example to `mobile_money`.

### Signing example (PHP)

```php
$body = json_encode($event);
$timestamp = time();
$signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
$header = "t={$timestamp},v1={$signature}";
```

## Adding a provider-specific adapter

Providers such as Stripe or Flutterwave each have their own signature scheme and payload. To support one:

1. Implement `App\Services\Payments\PaymentGateway`:
   - `verify()` checks the provider's signature.
   - `parse()` maps the provider's payload to a `GatewayEvent`, using `GatewayEvent::PAYMENT_SUCCEEDED` for successful charges and setting the reservation reference you passed to the provider as metadata.
2. Add a `driver` case for it in `App\Services\Payments\PaymentGatewayRegistry`.
3. Add an entry under `hms.payments.gateways` in `config/hms.php`, with its secrets read from `.env`.

Storage, deduplication, the queued processing and the admin screen work unchanged for every adapter.
