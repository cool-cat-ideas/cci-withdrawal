<p align="center"><img src="views/img/cool-cat-ideas-logo.svg" alt="Cool Cat Ideas — CCI Withdrawal Form" width="320"></p>

# CCI Withdrawal Form

Let a customer or guest submit a full or partial withdrawal statement online. The module first stores an immutable statement snapshot and then, when PrestaShop can represent the request, creates a native merchandise return. It never issues a refund automatically.

Download the module ZIP from the [Cool Cat Ideas product page](https://coolcatideas.com/products/cci-withdrawal-form/).

## Set it up

1. Install `cci_withdrawal-1.0.0.zip` in **Modules > Module Manager**.
2. Open **Cool Cat Ideas > CCI Withdrawal Form** and review buyer, product, access-link, email and PDF settings.
3. Optionally enable merchandise returns in **Shop Parameters > Order Settings** to create RMA records automatically.
4. Confirm the configured link appears in the footer, customer account, order details and checkout confirmation.
5. Submit a test request and open it in **Cool Cat Ideas > CCI Withdrawal Form > Submitted withdrawals**.

The customer can choose all products or only selected items and quantities. A guest finds the order with its reference and the same email address used at checkout.

If the form cannot find an order, check the reference, email address and shop assignment. Payment state, delivery state and the native return period do not block recording the statement; they only decide whether the RMA adapter can run.

Customer confirmation email is sent synchronously. The result and attempt count are stored. If delivery fails, the statement remains validly recorded and an employee can use **Send again** from the statement inbox. There is no background email queue.

## Tests

Run the source-level workflow checks with `php tests/withdrawal_workflow_smoke.php`. In a local PrestaShop runtime, run the transactional persistence test with `CCI_WITHDRAWAL_RUN_RUNTIME_TESTS=1 php tests/runtime_workflow_test.php`. The runtime test always rolls its fixtures back and never sends email.

The module works without a license key. It does not decide whether a customer has a legal right to withdraw and it does not issue a refund automatically.

## Compatibility

- PrestaShop 9.0.0-9.2.0;
- PHP version supported by the installed PrestaShop release;
- native merchandise returns are optional and used as an adapter when available.

## License

Academic Free License 3.0 (AFL-3.0). See `LICENSE.md`.

## Support

Use the product repository under https://github.com/orgs/cool-cat-ideas/repositories for public questions and bug reports. This channel has no guaranteed response time. When you need a private request with an agreed scope, buy a support service on the product page; the request and further messages are then handled in the Cool Cat Ideas customer panel.

Release history is available in the [changelog](CHANGELOG.md).
