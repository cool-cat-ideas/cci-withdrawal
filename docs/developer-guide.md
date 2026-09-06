# Developer guide

## Stable integration points

- Public route name: `module-cci_withdrawal-form`.
- Default route rule: `odstapienie-od-umowy`.
- Front controller: `controllers/front/form.php`.
- Front template: `views/templates/front/form.tpl`.
- Scoped styles: `views/css/front.css` and `views/css/admin.css`.
- Native event after creation: `actionOrderReturn`.

Use a theme override at
`themes/<theme>/modules/cci_withdrawal/views/templates/front/form.tpl` when
markup must change. Keep module selectors under `.cci-withdrawal-page` so theme
changes do not leak into unrelated forms.

## Canonical data

`cci_withdrawal_submission` is canonical for receipt of the legal statement. Its
`statement_payload` is an immutable JSON snapshot of the submitted content,
products, quantities, date, time, language and shop. `OrderReturn` and
`order_return_detail` remain canonical only for the operational merchandise
return workflow. The nullable `id_order_return` links both records.

`processing_status` is intentionally small: `rma_created` or `manual_review`.
It does not duplicate native RMA states. The submission token has a unique hash,
so concurrent repeats resolve to one statement. Receipt tokens are stored only
as SHA-256 hashes.

## Email overrides

PrestaShop mail overrides can replace `withdrawal_received`,
`withdrawal_merchant` and `withdrawal_access`. Preserve every placeholder used
by `sendSubmissionEmails()` and `hookActionValidateOrder()`. Sending is
synchronous; `confirmation_status`, `confirmation_attempts`,
`confirmation_last_error` and `confirmation_date` record the customer result.
