# Test the withdrawal flow safely

1. Use a staging shop with outgoing mail redirected to a test inbox.
2. Enable merchandise returns and choose a short but non-zero return period for the first scenario.
3. Create a customer order containing two ordinary physical products.
4. Mark the order paid and delivered.
5. Open the public form in a private browser window and submit a partial request.
6. Confirm that one canonical statement and one linked native return were created.
7. Repeat the final browser request and confirm that no duplicate return appears.
8. Open **Cool Cat Ideas > CCI Withdrawal Form > Submitted withdrawals** and verify the immutable snapshot and native return link.
9. Disable native returns or use an unsupported status/digital item. Confirm the statement is accepted and marked for manual review without an RMA.
10. Repeat at mobile width and with a guest order if guest checkout is enabled.

Do not use a real customer's identity or email in demonstration data.
