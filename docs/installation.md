# Install CCI Withdrawal Form

## Before you start

Make a backup of the shop files and database. Check that outgoing email works, because the module sends a confirmation to the customer and a notice to the shop.

## Install and check the module

1. Open **Modules > Module Manager**.
2. Click **Upload a module** and choose `cci_withdrawal-1.0.0.zip`.
3. Open **Cool Cat Ideas > CCI Withdrawal Form** and save the rules and access points you want to use.
4. Optionally turn merchandise returns on and set their technical period in **Shop Parameters > Order Settings**.
6. Clear the PrestaShop cache.
7. Open the public form and submit a request for a test order.
8. Open **Cool Cat Ideas > CCI Withdrawal Form > Submitted withdrawals** and confirm that the statement appears. If the order supports native returns, also check its RMA link.

## If the menu link is missing

Open the module settings once, clear the back-office cache and sign in again. Also check that the employee profile can read the CCI module tab and merchandise returns.

## Update or remove the module

When replacing a development build with another 1.0.0 package, upload the new ZIP, clear the cache and open the settings once.

Uninstalling removes the module settings, canonical statement records, lookup-rate-limit records and menu shortcut. It does not delete PrestaShop merchandise returns created earlier. Keep a database backup if the statement snapshots must be retained.
