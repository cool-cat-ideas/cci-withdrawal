# Hooks reference

## Hooks implemented by the module

### `moduleRoutes`

Input is the standard PrestaShop route-build context. Returns two descriptors for
the `form` controller: `withdrawal-form` and `odstapienie-od-umowy`. Neither route
contains customer data or a mutable token.

### `registerGDPRConsent`

Receives the discovery payload and returns an empty string. It is only a marker
for the official GDPR module. The withdrawal form displays a processing notice
and does not request consent as a condition of receiving the statement.

### `displayHeader`

Receives the normal front-controller context and returns no HTML. It is retained
as a compatibility path and delegates to the same idempotent asset registration.

### `actionFrontControllerSetMedia`

Runs before theme assets are rendered. It registers the scoped stylesheet and,
when order placements are enabled, the history/detail enhancement script plus
the translated public URL definition. The shared asset IDs prevent duplicate
CSS or JavaScript when a theme also executes `displayHeader`.

### `displayFooter`

Returns the translated public-form link when footer placement is enabled;
otherwise returns an empty string.

### `displayCustomerAccount`

Returns one customer-account shortcut when account placement is enabled;
otherwise returns an empty string.

### `displayOrderDetail`

Accepts `order`, `objOrder` or `id_order` when supplied by the theme. Returns a
signed order-specific shortcut when an order is available, or a generic shortcut
when the hook is called without parameters, including guest-tracking templates.
Returns an empty string only when order placements are disabled.

### `displayOrderConfirmation2`

Accepts the same order keys as `displayOrderDetail`. Returns a signed
order-specific shortcut after checkout when both the placement and order are
available; otherwise returns an empty string.

### `actionValidateOrder`

Reads `order`/`objOrder` and optional `customer` from the standard validation
payload. When enabled, sends the separate `withdrawal_access` transactional email
to the order customer. It only exposes a signed form link and does not create a
statement or RMA. A failed send is written to the PrestaShop log.

### `actionAdminBreadcrumbModifier`

Receives the back-office `breadcrumb` array by reference. On the module inbox and
settings controllers, it labels the parent path as Cool Cat Ideas and CCI
Withdrawal Form. It has no storefront effect.

## Hook emitted by the module

### `actionOrderReturn`

After a new native `OrderReturn`, all detail rows and the submission link are
committed, the controller executes:

```php
Hook::exec('actionOrderReturn', ['orderReturn' => $orderReturn]);
```

The value is a loaded `OrderReturn`. The hook is not emitted when the statement
requires manual review or an idempotent repeat resolves to an existing return.
