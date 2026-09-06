{if $cci_withdrawal_surface == 'footer'}
  <div class="cci-withdrawal-footer-entry">
    <a class="cci-withdrawal-entry-link" href="{$cci_withdrawal_url|escape:'html':'UTF-8'}">
      {l s='Withdraw from contract here' d='Modules.Cciwithdrawal.Shop'}
    </a>
  </div>
{else}
  <div class="cci-withdrawal-order-entry cci-withdrawal-order-entry-{$cci_withdrawal_surface|escape:'html':'UTF-8'}">
    <p class="cci-withdrawal-order-entry-title">{l s='Need to withdraw from this contract?' d='Modules.Cciwithdrawal.Shop'}</p>
    <a class="btn btn-outline-primary cci-withdrawal-entry-link" href="{$cci_withdrawal_url|escape:'html':'UTF-8'}">
      {l s='Withdraw from contract here' d='Modules.Cciwithdrawal.Shop'}
    </a>
  </div>
{/if}
