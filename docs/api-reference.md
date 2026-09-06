# PHP API

The methods below are the supported module-level integration points. Controller
lifecycle and back-office list callbacks follow PrestaShop contracts and are not
application APIs.

## URLs and signed access

### `getWithdrawalLink(): string`

Returns the HTTPS-aware public form URL for the current shop and language.

```php
$url = Module::getInstanceByName('cci_withdrawal')->getWithdrawalLink();
```

### `getOrderWithdrawalLink(Order $order): string`

Accepts a loaded `Order` and returns an order-specific URL containing a signed
access token. Opening the URL prefills the form; it does not submit a statement.

### `resolveOrderAccessRequest(int $orderId, string $accessToken = ''): ?Order`

Returns the loaded order when it belongs to the current shop and either the
signed token is valid or the current signed-in customer owns it. Returns `null`
otherwise. It does not change the order.

### `getFormToken(): string`

Returns the current session-bound CSRF token checked by the front controller.

### `getReceiptToken(string $submissionToken): string`

Derives the 64-character receipt bearer token from a valid single-use submission
token. Store only its hash; do not log or expose it outside the confirmation URL.

## Stored statements

### `ensureDatabase(): bool`

Idempotently creates or upgrades the canonical statement and lookup-attempt
tables. Returns `true` only when both tables and required columns are ready.

### `getWithdrawalReference(int $submissionId): string`

Formats a numeric ID as the public reference, for example `CCI-WD-000123`.

### `getWithdrawalSubmission(int $submissionId): ?array`

Returns the raw database row, including the JSON `statement_payload`, or `null`
when the ID is invalid or storage cannot be prepared. This method does not check
a public receipt token and is intended for trusted server-side code.

### `getWithdrawalReceipt(int $submissionId, string $receiptToken): ?array`

Validates the token hash and current shop, then returns the row with decoded
`payload` and formatted `reference`. Returns `null` for an invalid token, wrong
shop, legacy row without a token or missing submission.

```php
$receipt = $module->getWithdrawalReceipt(123, (string) Tools::getValue('receipt_token'));
if ($receipt) {
    echo Tools::htmlentitiesUTF8($receipt['reference']);
}
```

## Confirmation delivery

### `sendSubmissionEmails(int $submissionId, bool $sendMerchantNotification = true): array`

Synchronously sends the customer confirmation and, by default, the merchant
notification. Returns `['customer' => bool, 'shop' => bool]`. Every customer
attempt updates `confirmation_status`, `confirmation_attempts`,
`confirmation_last_error` and `confirmation_date`. It never queues background
work and does not delete or invalidate the stored statement on failure.

### `getConfirmationPdfAttachment(int $submissionId): ?array`

Builds the PDF from the immutable snapshot. Returns the PrestaShop attachment
shape `['content' => string, 'name' => string, 'mime' => 'application/pdf']` or
`null` when the record/payload is invalid or PDF generation fails.

## Configuration and repair

### `ensureAdminTab(): bool`

Creates or repairs the CCI parent tab, statement inbox and settings tab. Returns
whether all required tabs and positions were saved.

### `ensureFrontendHooks(): bool`

Registers every configured storefront/access hook for an already installed copy.
Returns `false` when any hook cannot be registered.

### `getExcludedProductIds(): array`

Returns unique positive excluded product IDs sorted numerically.

### `shouldExcludeCustomizedProducts(): bool`

Returns whether lines with native customization data are excluded.

### `getEnabledBuyerStatuses(): array`

Returns a subset of `consumer`, `sole_trader` and `business` in display order.

### Boolean feature accessors

Each method has no parameters and returns `bool`:

- `allowsGuestOrders()` — guest lookup is enabled;
- `showsAdditionalInformation()` — the optional message field is visible;
- `requiresAdditionalInformation()` — always `false`; retained for compatibility;
- `showsFooterLink()` — footer placement is enabled;
- `showsAccountLink()` — customer-account placement is enabled;
- `showsOrderLinks()` — order history/detail enhancement is enabled;
- `showsOrderConfirmationLink()` — checkout confirmation placement is enabled;
- `sendsOrderAccessEmail()` — separate post-order access mail is enabled;
- `attachesConfirmationPdf()` — confirmation PDF is enabled.
