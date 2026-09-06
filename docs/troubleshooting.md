# Fix common withdrawal form problems

## The CCI Withdrawals menu link is missing

1. Confirm the module is enabled.
2. Open its settings once.
3. Clear the back-office cache and sign in again.
4. Check that the employee profile can read the CCI module tab and merchandise returns.

## The form cannot find an order

Use the order reference shown to the customer, not the numeric database ID. Enter the same email address used at checkout. Also check that the order belongs to the current shop.

## The statement has no native RMA

The statement is still recorded. The RMA adapter uses these technical checks:

1. Merchandise returns are enabled.
2. The order is paid and delivered.
3. The return period has not expired.
4. Every selected item is physical and its quantity can still be returned.
5. The product is not excluded by the module settings.

Digital content and unusual order states are routed to manual review instead of being rejected.

## The email was not sent

The statement remains saved. Open **Cool Cat Ideas > CCI Withdrawal Form > Submitted withdrawals**, inspect the error and use **Send again** after fixing SMTP or templates. The module sends synchronously and does not require a cron or worker. Do not delete the statement or RMA only to repeat the message.

## The confirmation URL does not show success

Use the complete signed receipt URL returned after submission or included in a confirmation. A numeric submission ID or `submitted=1` is deliberately insufficient.

## The page has no styling

Open the browser network panel and confirm `views/css/front.css` loads successfully. Clear the PrestaShop and theme cache. If the file loads, check whether the theme overrides `.cci-withdrawal-page`.
