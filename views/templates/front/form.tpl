{extends file=$layout}

{block name='content'}
  <section id="main" class="cci-withdrawal-page">
    <header class="page-header cci-withdrawal-page-header">
      <h1 class="h1">{l s='Withdraw from contract' d='Modules.Cciwithdrawal.Shop'}</h1>
    </header>

    <section id="content" class="page-content cci-withdrawal-content">
      {if $withdrawal_success}
        <div class="cci-withdrawal-success" role="status">
          <p class="cci-withdrawal-success-title">{l s='Your withdrawal statement has been recorded' d='Modules.Cciwithdrawal.Shop'}</p>
          <p>
            {l s='Withdrawal reference' d='Modules.Cciwithdrawal.Shop'}:
            <strong>{$withdrawal_receipt.reference|escape:'html':'UTF-8'}</strong><br>
            {l s='Order reference' d='Modules.Cciwithdrawal.Shop'}:
            <strong>{$withdrawal_receipt.order_reference|escape:'html':'UTF-8'}</strong><br>
            {l s='Submission date and time' d='Modules.Cciwithdrawal.Shop'}:
            {$withdrawal_receipt.payload.submitted_at_display|escape:'html':'UTF-8'}
          </p>
          <p><strong>{l s='Products covered by the statement' d='Modules.Cciwithdrawal.Shop'}:</strong></p>
          <ul class="cci-withdrawal-selected-products">
            {foreach from=$withdrawal_receipt.payload.products item=receipt_product}
              <li>
                {$receipt_product.quantity|intval} × {$receipt_product.name|escape:'html':'UTF-8'}
                {if $receipt_product.reference}
                  <span class="cci-withdrawal-muted">({$receipt_product.reference|escape:'html':'UTF-8'})</span>
                {/if}
              </li>
            {/foreach}
          </ul>
          {if $withdrawal_receipt.processing_status == 'rma_created'}
            <p>{l s='The statement was also linked to the standard PrestaShop merchandise return workflow.' d='Modules.Cciwithdrawal.Shop'}</p>
          {else}
            <p>{l s='The statement is validly recorded and will be reviewed by the store team. A merchandise return does not need to be available for the statement to be received.' d='Modules.Cciwithdrawal.Shop'}</p>
          {/if}
          {if $confirmation_email_failed}
            <div class="alert alert-warning" role="alert">
              {l s='The statement was recorded, but the email confirmation could not be sent. Contact us and keep this page as confirmation of submission.' d='Modules.Cciwithdrawal.Shop'}
            </div>
          {/if}
          <div class="cci-withdrawal-actions">
            {if $confirmation_pdf_url}
              <a class="btn btn-primary" href="{$confirmation_pdf_url|escape:'html':'UTF-8'}">
                {l s='Download PDF confirmation' d='Modules.Cciwithdrawal.Shop'}
              </a>
            {/if}
            {if $customer_logged_in}
              <a class="btn btn-default" href="{$order_returns_url|escape:'html':'UTF-8'}">
                {l s='View merchandise returns' d='Modules.Cciwithdrawal.Shop'}
              </a>
            {/if}
            <a class="btn btn-default" href="{$contact_url|escape:'html':'UTF-8'}">{l s='Contact us' d='Shop.Theme.Global'}</a>
          </div>
        </div>
      {else}
        <div class="alert alert-info" role="note">
          {if $guest_orders_enabled}
            <p>{l s='Use this function to submit an online withdrawal statement for a customer or guest order. The store uses a standard merchandise return period of %days% days, while exceptional or extended rights are reviewed separately.' sprintf=['%days%' => $return_period_days] d='Modules.Cciwithdrawal.Shop'}</p>
          {else}
            <p>{l s='Use this function to submit an online withdrawal statement for a customer order. The store uses a standard merchandise return period of %days% days, while exceptional or extended rights are reviewed separately.' sprintf=['%days%' => $return_period_days] d='Modules.Cciwithdrawal.Shop'}</p>
          {/if}
          <p>{l s='The statutory right applies to consumers and, in specific cases, to sole traders when the purchase is not of a professional nature. It does not apply to companies, professional business purchases or goods made to an individual specification.' d='Modules.Cciwithdrawal.Shop'}</p>
        </div>

        {if $withdrawal_preview}
          <div class="cci-withdrawal-review">
            <p class="cci-withdrawal-review-title">{l s='Review your withdrawal statement' d='Modules.Cciwithdrawal.Shop'}</p>
            <p>{l s='Check the content below. Selecting “Confirm withdrawal” submits an unequivocal withdrawal statement.' d='Modules.Cciwithdrawal.Shop'}</p>
            <dl>
              <dt>{l s='Full name' d='Modules.Cciwithdrawal.Shop'}</dt>
              <dd>{$form_values.full_name|escape:'html':'UTF-8'}</dd>
              <dt>{l s='Email' d='Modules.Cciwithdrawal.Shop'}</dt>
              <dd>{$form_values.email|escape:'html':'UTF-8'}</dd>
              <dt>{l s='Order reference' d='Modules.Cciwithdrawal.Shop'}</dt>
              <dd>{$form_values.order_reference|escape:'html':'UTF-8'}</dd>
              <dt>{l s='Purchase capacity' d='Modules.Cciwithdrawal.Shop'}</dt>
              <dd>
                {if $form_values.buyer_status == 'sole_trader'}
                  {l s='Sole trader — purchase without a professional nature' d='Modules.Cciwithdrawal.Shop'}
                {elseif $form_values.buyer_status == 'business'}
                  {l s='Company or professional business purchase' d='Modules.Cciwithdrawal.Shop'}
                {else}
                  {l s='Consumer purchase outside business activity' d='Modules.Cciwithdrawal.Shop'}
                {/if}
              </dd>
              <dt>{l s='Withdrawal scope' d='Modules.Cciwithdrawal.Shop'}</dt>
              <dd>
                {if $form_values.withdrawal_scope == 'partial'}
                  {l s='Part of the order' d='Modules.Cciwithdrawal.Shop'}
                {else}
                  {l s='All eligible products from the order' d='Modules.Cciwithdrawal.Shop'}
                {/if}
              </dd>
              <dt>{l s='Products selected for withdrawal' d='Modules.Cciwithdrawal.Shop'}</dt>
              <dd>
                <ul class="cci-withdrawal-selected-products">
                  {foreach from=$selected_products item=selected_product}
                    <li>
                      {$selected_product.selected_quantity|intval} × {$selected_product.name|escape:'html':'UTF-8'}
                      {if $selected_product.reference}
                        <span class="cci-withdrawal-muted">({$selected_product.reference|escape:'html':'UTF-8'})</span>
                      {/if}
                    </li>
                  {/foreach}
                </ul>
              </dd>
              {if $form_values.buyer_status == 'sole_trader'}
                <dt>{l s='Sole trader declaration' d='Modules.Cciwithdrawal.Shop'}</dt>
                <dd>{l s='The purchase was not of a professional nature for my sole proprietorship.' d='Modules.Cciwithdrawal.Shop'}</dd>
              {/if}
              {if $form_values.message|trim !== ''}
                <dt>{l s='Additional information' d='Modules.Cciwithdrawal.Shop'}</dt>
                <dd>{$form_values.message|escape:'html':'UTF-8'|nl2br nofilter}</dd>
              {/if}
            </dl>
          </div>

          {if $excluded_products|count}
            <div class="alert alert-warning" role="note">
              {l s='Products excluded by law or store configuration are not included in this statement.' d='Modules.Cciwithdrawal.Shop'}
            </div>
          {/if}

          <form class="cci-withdrawal-actions-form" action="{$form_action}" method="post">
            <input type="hidden" name="cci_withdrawal_token" value="{$form_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="cci_withdrawal_submission_token" value="{$submission_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="full_name" value="{$form_values.full_name|escape:'html':'UTF-8'}">
            <input type="hidden" name="email" value="{$form_values.email|escape:'html':'UTF-8'}">
            <input type="hidden" name="order_reference" value="{$form_values.order_reference|escape:'html':'UTF-8'}">
            <input type="hidden" name="withdrawal_scope" value="{$form_values.withdrawal_scope|escape:'html':'UTF-8'}">
            <input type="hidden" name="buyer_status" value="{$form_values.buyer_status|escape:'html':'UTF-8'}">
            {if $form_values.non_professional_purchase}
              <input type="hidden" name="non_professional_purchase" value="1">
            {/if}
            {if $additional_information_enabled}
              <input type="hidden" name="message" value="{$form_values.message|escape:'html':'UTF-8'}">
            {/if}
            {foreach from=$selected_products item=selected_product}
              <input type="hidden" name="withdrawal_products[]" value="{$selected_product.id_order_detail|intval}">
              <input type="hidden" name="withdrawal_product_qty[{$selected_product.id_order_detail|intval}]" value="{$selected_product.selected_quantity|intval}">
            {/foreach}

            <div class="form-footer cci-withdrawal-actions">
              <button class="btn btn-primary" type="submit" name="confirmCciWithdrawal">
                {l s='Confirm withdrawal' d='Modules.Cciwithdrawal.Shop'}
              </button>
              <button class="btn btn-default" type="submit" name="editCciWithdrawal" formnovalidate>
                {l s='Edit details' d='Modules.Cciwithdrawal.Shop'}
              </button>
            </div>
          </form>
        {elseif $withdrawal_product_selection}
          <form class="cci-withdrawal-form" action="{$form_action}" method="post">
            <input type="hidden" name="cci_withdrawal_token" value="{$form_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="cci_withdrawal_submission_token" value="{$submission_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="full_name" value="{$form_values.full_name|escape:'html':'UTF-8'}">
            <input type="hidden" name="email" value="{$form_values.email|escape:'html':'UTF-8'}">
            <input type="hidden" name="order_reference" value="{$form_values.order_reference|escape:'html':'UTF-8'}">
            <input type="hidden" name="withdrawal_scope" value="{$form_values.withdrawal_scope|escape:'html':'UTF-8'}">
            <input type="hidden" name="buyer_status" value="{$form_values.buyer_status|escape:'html':'UTF-8'}">
            {if $form_values.non_professional_purchase}
              <input type="hidden" name="non_professional_purchase" value="1">
            {/if}
            {if $additional_information_enabled}
              <input type="hidden" name="message" value="{$form_values.message|escape:'html':'UTF-8'}">
            {/if}

            <div class="cci-withdrawal-products">
              <p class="cci-withdrawal-products-title">{l s='Select products' d='Modules.Cciwithdrawal.Shop'}</p>
              <p class="cci-withdrawal-muted">{l s='Choose the products and quantities covered by the withdrawal statement.' d='Modules.Cciwithdrawal.Shop'}</p>

              <div class="cci-withdrawal-product-list">
                {foreach from=$order_products item=order_product}
                  <label class="cci-withdrawal-product{if !$order_product.is_returnable} is-disabled{/if}">
                    <span class="cci-withdrawal-product-check">
                      <input
                        type="checkbox"
                        name="withdrawal_products[]"
                        value="{$order_product.id_order_detail|intval}"
                        {if !$order_product.is_returnable}disabled{/if}
                      >
                    </span>
                    <span class="cci-withdrawal-product-main">
                      <span class="cci-withdrawal-product-name">{$order_product.name|escape:'html':'UTF-8'}</span>
                      {if $order_product.reference}
                        <span class="cci-withdrawal-muted">{$order_product.reference|escape:'html':'UTF-8'}</span>
                      {/if}
                      <span class="cci-withdrawal-muted">
                        {l s='Ordered quantity' d='Modules.Cciwithdrawal.Shop'}: {$order_product.quantity|intval}
                        {if $order_product.returned_quantity > 0}
                          · {l s='Already returned' d='Modules.Cciwithdrawal.Shop'}: {$order_product.returned_quantity|intval}
                        {/if}
                      </span>
                      {if !$order_product.is_returnable}
                        <span class="cci-withdrawal-product-reason">
                          {if $order_product.non_returnable_reason == 'customized'}
                            {l s='Made to an individual specification — statutory withdrawal is not available.' d='Modules.Cciwithdrawal.Shop'}
                          {elseif $order_product.non_returnable_reason == 'configured'}
                            {l s='This product is excluded from statutory withdrawal.' d='Modules.Cciwithdrawal.Shop'}
                          {else}
                            {l s='The full available quantity has already been returned or refunded.' d='Modules.Cciwithdrawal.Shop'}
                          {/if}
                        </span>
                      {/if}
                      {if $order_product.is_returnable && !$order_product.native_return_supported}
                        <span class="cci-withdrawal-product-note">
                          {l s='This digital item will be recorded in the statement and reviewed separately from the merchandise return workflow.' d='Modules.Cciwithdrawal.Shop'}
                        </span>
                      {/if}
                    </span>
                    <span class="cci-withdrawal-product-side">
                      <span class="cci-withdrawal-product-price">{$order_product.unit_price|escape:'html':'UTF-8'}</span>
                      {if $order_product.is_returnable}
                        <span class="cci-withdrawal-qty-field">
                          <span class="cci-withdrawal-muted">{l s='Quantity' d='Modules.Cciwithdrawal.Shop'}</span>
                          <input
                            class="form-control"
                            type="number"
                            name="withdrawal_product_qty[{$order_product.id_order_detail|intval}]"
                            value="1"
                            min="1"
                            max="{$order_product.available_quantity|intval}"
                          >
                        </span>
                      {/if}
                    </span>
                  </label>
                {/foreach}
              </div>
            </div>

            <div class="form-footer cci-withdrawal-actions">
              <button class="btn btn-primary" type="submit" name="previewCciWithdrawal">
                {l s='Review statement' d='Modules.Cciwithdrawal.Shop'}
              </button>
              <button class="btn btn-default" type="submit" name="editCciWithdrawal" formnovalidate>
                {l s='Back' d='Shop.Theme.Actions'}
              </button>
            </div>
          </form>
        {else}
          <form class="cci-withdrawal-form" action="{$form_action}" method="post">
            <input type="hidden" name="cci_withdrawal_token" value="{$form_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="cci_withdrawal_submission_token" value="{$submission_token|escape:'html':'UTF-8'}">

            <div class="form-group">
              <label class="cci-withdrawal-label required" for="cci-withdrawal-full-name">
                {l s='Full name' d='Modules.Cciwithdrawal.Shop'}
              </label>
              <input id="cci-withdrawal-full-name" class="form-control" type="text" name="full_name" value="{$form_values.full_name|escape:'html':'UTF-8'}" autocomplete="name" maxlength="160" required>
            </div>

            <div class="form-group">
              <label class="cci-withdrawal-label required" for="cci-withdrawal-email">
                {l s='Email' d='Modules.Cciwithdrawal.Shop'}
              </label>
              <input id="cci-withdrawal-email" class="form-control" type="email" name="email" value="{$form_values.email|escape:'html':'UTF-8'}" autocomplete="email" maxlength="160" required>
              <small class="cci-withdrawal-muted">{l s='Enter the email address used for the order. It is also where we will send the confirmation.' d='Modules.Cciwithdrawal.Shop'}</small>
            </div>

            <div class="form-group">
              <label class="cci-withdrawal-label required" for="cci-withdrawal-order-reference">
                {l s='Order reference' d='Modules.Cciwithdrawal.Shop'}
              </label>
              <input id="cci-withdrawal-order-reference" class="form-control" type="text" name="order_reference" value="{$form_values.order_reference|escape:'html':'UTF-8'}" autocomplete="off" maxlength="64" required>
            </div>

            <fieldset class="form-group cci-withdrawal-scope">
              <legend class="cci-withdrawal-label required">{l s='Purchase capacity' d='Modules.Cciwithdrawal.Shop'}</legend>
              {if $buyer_status_consumer_enabled}
                <label class="cci-withdrawal-option">
                  <input type="radio" name="buyer_status" value="consumer" {if $form_values.buyer_status == 'consumer'}checked{/if} required>
                  <span>
                    <span class="cci-withdrawal-option-label">{l s='Consumer purchase outside business activity' d='Modules.Cciwithdrawal.Shop'}</span>
                  </span>
                </label>
              {/if}
              {if $buyer_status_sole_trader_enabled}
                <label class="cci-withdrawal-option cci-withdrawal-option-stacked">
                  <input type="radio" name="buyer_status" value="sole_trader" {if $form_values.buyer_status == 'sole_trader'}checked{/if} required>
                  <span>
                    <span class="cci-withdrawal-option-label">{l s='Sole trader — purchase without a professional nature' d='Modules.Cciwithdrawal.Shop'}</span>
                    <span class="cci-withdrawal-muted">{l s='This applies only to a natural person running a sole proprietorship when the agreement was not professional in nature.' d='Modules.Cciwithdrawal.Shop'}</span>
                  </span>
                </label>
                <label class="cci-withdrawal-declaration">
                  <input type="checkbox" name="non_professional_purchase" value="1" {if $form_values.non_professional_purchase}checked{/if}>
                  <span>{l s='I declare that this purchase was not of a professional nature for my sole proprietorship.' d='Modules.Cciwithdrawal.Shop'}</span>
                </label>
              {/if}
              {if $buyer_status_business_enabled}
                <label class="cci-withdrawal-option cci-withdrawal-option-stacked">
                  <input type="radio" name="buyer_status" value="business" {if $form_values.buyer_status == 'business'}checked{/if} required>
                  <span>
                    <span class="cci-withdrawal-option-label">{l s='Company or professional business purchase' d='Modules.Cciwithdrawal.Shop'}</span>
                    <span class="cci-withdrawal-muted">{l s='The statutory consumer withdrawal procedure does not apply. You can still contact us about a voluntary return.' d='Modules.Cciwithdrawal.Shop'}</span>
                  </span>
                </label>
              {/if}
            </fieldset>

            <fieldset class="form-group cci-withdrawal-scope">
              <legend class="cci-withdrawal-label required">{l s='Withdrawal scope' d='Modules.Cciwithdrawal.Shop'}</legend>
              <label class="cci-withdrawal-option">
                <input type="radio" name="withdrawal_scope" value="full" {if $form_values.withdrawal_scope != 'partial'}checked{/if}>
                <span class="cci-withdrawal-option-label">{l s='All eligible products from the order' d='Modules.Cciwithdrawal.Shop'}</span>
              </label>
              <label class="cci-withdrawal-option">
                <input type="radio" name="withdrawal_scope" value="partial" {if $form_values.withdrawal_scope == 'partial'}checked{/if}>
                <span class="cci-withdrawal-option-label">{l s='Only selected products and quantities' d='Modules.Cciwithdrawal.Shop'}</span>
              </label>
            </fieldset>

            {if $additional_information_enabled}
              <div class="form-group">
                <label class="cci-withdrawal-label" for="cci-withdrawal-message">{l s='Additional information (optional)' d='Modules.Cciwithdrawal.Shop'}</label>
                <textarea id="cci-withdrawal-message" class="form-control" name="message" rows="5" maxlength="2000">{$form_values.message|escape:'html':'UTF-8'}</textarea>
              </div>
            {/if}

            <p class="cci-withdrawal-privacy-note">
              {l s='We use these details only to identify the order, record the withdrawal statement and send its confirmation. Submitting the statement does not require marketing consent.' d='Modules.Cciwithdrawal.Shop'}
            </p>

            <div class="form-footer cci-withdrawal-actions">
              <button class="btn btn-primary" type="submit" name="previewCciWithdrawal">
                {l s='Continue to verification' d='Modules.Cciwithdrawal.Shop'}
              </button>
              {if $guest_orders_enabled}
                <a class="btn btn-default" href="{$guest_tracking_url|escape:'html':'UTF-8'}">{l s='Track guest order' d='Shop.Theme.Customeraccount'}</a>
              {/if}
            </div>
          </form>
        {/if}
      {/if}
    </section>
  </section>
{/block}
