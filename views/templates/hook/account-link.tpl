<a class="cci-withdrawal-account-link account-menu__link col-lg-4 col-md-6 col-sm-6 col-xs-12{if $cci_withdrawal_link_active} account-menu__link--active{/if}" href="{$cci_withdrawal_url|escape:'html':'UTF-8'}"{if $cci_withdrawal_link_active} aria-current="page"{/if}>
  <span class="link-item">
    <i class="material-icons account-menu__icon" aria-hidden="true">assignment_return</i>
    <span>{l s='Withdraw from contract' d='Modules.Cciwithdrawal.Shop'}</span>
  </span>
</a>
