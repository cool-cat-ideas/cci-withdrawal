(function () {
  'use strict';

  var config = window.cciWithdrawalAccess;
  if (!config || !config.formUrl || !config.label) {
    return;
  }

  function buildUrl(orderId) {
    var url = new URL(config.formUrl, window.location.origin);
    if (orderId) {
      url.searchParams.set(config.orderParameter, orderId);
    }
    return url.toString();
  }

  function getOrderId(href) {
    if (!href) {
      return '';
    }

    try {
      var url = new URL(href, window.location.origin);
      return url.searchParams.get('id_order') || '';
    } catch (error) {
      return '';
    }
  }

  function createLink(orderId, compact) {
    var link = document.createElement('a');
    link.className = compact
      ? 'cci-withdrawal-history-link'
      : 'btn btn-outline-primary cci-withdrawal-entry-link';
    link.href = buildUrl(orderId);
    link.textContent = config.label;
    return link;
  }

  function enhanceOrderHistory() {
    document.querySelectorAll('.order-actions, .order-history__cell--actions').forEach(function (actions) {
      if (actions.querySelector('.cci-withdrawal-history-link')) {
        return;
      }

      var details = actions.querySelector('[data-link-action="view-order-details"], a[href*="id_order="]');
      var orderId = getOrderId(details && details.href);
      if (orderId) {
        actions.appendChild(createLink(orderId, true));
      }
    });

    document.querySelectorAll('.orders .order').forEach(function (orderCard) {
      if (orderCard.querySelector('.cci-withdrawal-history-link')) {
        return;
      }

      var details = orderCard.querySelector('[data-link-action="view-order-details"], a[href*="id_order="]');
      var orderId = getOrderId(details && details.href);
      if (orderId) {
        orderCard.appendChild(createLink(orderId, true));
      }
    });
  }

  function enhanceOrderDetail() {
    if (document.querySelector('.cci-withdrawal-order-entry, .cci-withdrawal-detail-fallback')) {
      return;
    }

    var orderId = new URL(window.location.href).searchParams.get('id_order');
    var target = document.querySelector('#content .page-footer, #content-wrapper .page-footer, #main');
    if (!orderId || !target) {
      return;
    }

    var wrapper = document.createElement('div');
    wrapper.className = 'cci-withdrawal-detail-fallback';
    wrapper.appendChild(createLink(orderId, false));
    target.appendChild(wrapper);
  }

  function preventRepeatedSubmit() {
    document.querySelectorAll('.cci-withdrawal-form, .cci-withdrawal-actions-form').forEach(function (form) {
      form.addEventListener('submit', function () {
        window.setTimeout(function () {
          form.querySelectorAll('button[type="submit"]').forEach(function (button) {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
          });
        }, 0);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    enhanceOrderHistory();
    enhanceOrderDetail();
    preventRepeatedSubmit();
  });
}());
