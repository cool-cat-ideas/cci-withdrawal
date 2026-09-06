# Request and back-office workflow

## Storefront

1. The buyer supplies a name, order reference, order email and purchase type.
2. The module verifies order ownership and applies rate limiting to unsuccessful lookups.
3. For a partial statement, the buyer selects lines and quantities.
4. A review page presents the complete statement.
5. The explicit confirmation first stores an immutable snapshot containing statement text, products, quantities, buyer data, date, time, language and shop.
6. The adapter creates a native merchandise return when returns are enabled and the status, period and product type can be represented by PrestaShop. Otherwise the statement is marked for manual review without being rejected.
7. Customer confirmation and merchant notification are sent synchronously. Their result is logged; failed customer confirmation can be sent again from the back office.

The unique submission hash makes repeated confirmation idempotent. The signed
receipt URL is required to display the success page and download its optional
PDF; a bare query flag cannot display a successful submission.

## Back office

Use **Cool Cat Ideas > CCI Withdrawal Form > Submitted withdrawals**. This is a
read-only legal-intake inbox. Employees can inspect the exact snapshot, see mail
attempts, resend a failed customer confirmation and follow the RMA link when it
exists. Native return status remains editable only in PrestaShop's standard
returns area.

The module does not duplicate the editable RMA workflow. Its own status only
states whether the snapshot was linked to an RMA or requires manual review.
