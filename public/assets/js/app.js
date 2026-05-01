document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('[data-sidebar-toggle]');
  const sidebar = document.querySelector('.sidebar');

  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('is-open');
    });
  }

  document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.getElementById(button.dataset.modalOpen);

      if (modal) {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
      }
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = button.closest('.modal');

      if (modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
      }
    });
  });

  document.querySelectorAll('[data-row-href]').forEach((row) => {
    row.addEventListener('click', (event) => {
      if (event.target.closest('a, button, input, select, textarea')) {
        return;
      }

      window.location.href = row.dataset.rowHref;
    });
  });

  document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll('[data-step-add]').forEach((button) => {
    button.addEventListener('click', () => {
      const form = button.closest('form');
      const rows = form ? form.querySelector('[data-step-rows]') : null;
      const firstRow = rows ? rows.querySelector('.treatment-step-row') : null;

      if (!rows || !firstRow) {
        return;
      }

      const clone = firstRow.cloneNode(true);
      clone.querySelectorAll('input').forEach((input) => {
        input.value = '';
      });
      clone.querySelectorAll('select').forEach((select) => {
        select.value = 'pending';
      });
      rows.appendChild(clone);
    });
  });

  document.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-step-remove]');

    if (!remove) {
      return;
    }

    const rows = remove.closest('[data-step-rows]');
    const row = remove.closest('.treatment-step-row');

    if (rows && row && rows.querySelectorAll('.treatment-step-row').length > 1) {
      row.remove();
    }
  });

  document.querySelectorAll('[data-invoice-item-add]').forEach((button) => {
    button.addEventListener('click', () => {
      const form = button.closest('form');
      const rows = form ? form.querySelector('[data-invoice-item-rows]') : null;
      const firstRow = rows ? rows.querySelector('.invoice-item-row') : null;

      if (!rows || !firstRow) {
        return;
      }

      const clone = firstRow.cloneNode(true);
      clone.querySelectorAll('input').forEach((input) => {
        if (input.name.includes('[quantity]')) {
          input.value = '1';
          return;
        }
        input.value = '';
      });
      rows.appendChild(clone);
    });
  });

  document.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-invoice-item-remove]');

    if (!remove) {
      return;
    }

    const rows = remove.closest('[data-invoice-item-rows]');
    const row = remove.closest('.invoice-item-row');

    if (rows && row && rows.querySelectorAll('.invoice-item-row').length > 1) {
      row.remove();
    }
  });

  document.querySelectorAll('[data-payment-invoice-select]').forEach((select) => {
    const syncPaymentFields = () => {
      const option = select.selectedOptions ? select.selectedOptions[0] : null;
      const form = select.closest('form');
      const patient = form ? form.querySelector('[data-payment-patient-select]') : null;
      const amount = form ? form.querySelector('[data-payment-amount]') : null;

      if (!option || !form) {
        return;
      }

      if (patient && option.dataset.patient) {
        patient.value = option.dataset.patient;
      }

      if (amount && option.dataset.remaining && (!amount.value || Number(amount.value) === 0)) {
        amount.value = Math.max(0, Number(option.dataset.remaining || 0)).toFixed(0);
      }
    };

    select.addEventListener('change', syncPaymentFields);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    document.querySelectorAll('.modal.is-open').forEach((modal) => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    });
  });
});
