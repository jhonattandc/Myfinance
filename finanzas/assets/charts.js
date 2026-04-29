(function () {
  const formatCOP = (value) => {
    const number = Number(value || 0);
    return '$ ' + new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(number);
  };

  const flashRoot = document.getElementById('flash-root');
  if (flashRoot && window.APP_FLASH && window.APP_FLASH.message) {
    const toast = document.createElement('div');
    toast.className = 'inline-toast ' + (window.APP_FLASH.type === 'error' ? 'error' : 'success');
    toast.textContent = window.APP_FLASH.message;
    flashRoot.appendChild(toast);
    setTimeout(() => toast.classList.add('fade-out'), 3600);
    setTimeout(() => toast.remove(), 4200);
  }

  const sidebar = document.getElementById('sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  document.querySelectorAll('[data-sidebar-open]').forEach((button) => {
    button.addEventListener('click', () => {
      sidebar?.classList.add('is-open');
      overlay?.classList.add('is-open');
    });
  });

  document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
    button.addEventListener('click', () => {
      sidebar?.classList.remove('is-open');
      overlay?.classList.remove('is-open');
    });
  });

  document.querySelectorAll('[data-expand-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      button.closest('[data-expandable]')?.classList.toggle('is-open');
    });
  });

  document.querySelectorAll('[data-dashboard-section]').forEach((section) => {
    const button = section.querySelector('[data-dashboard-toggle]');
    const label = section.querySelector('[data-toggle-label]');
    const storageKey = section.dataset.storageKey;
    const getStoredState = () => {
      try {
        return storageKey ? localStorage.getItem(storageKey) : null;
      } catch (error) {
        return null;
      }
    };
    const storeState = (isOpen) => {
      try {
        if (storageKey) {
          localStorage.setItem(storageKey, isOpen ? '1' : '0');
        }
      } catch (error) {
        // The toggle still works even when browser storage is unavailable.
      }
    };

    const setOpenState = (isOpen) => {
      section.classList.toggle('is-open', isOpen);
      button?.setAttribute('aria-expanded', String(isOpen));
      if (label) {
        label.textContent = isOpen ? 'Ocultar' : 'Mostrar';
      }
      storeState(isOpen);
    };

    if (getStoredState() === '0') {
      setOpenState(false);
    }

    button?.addEventListener('click', () => {
      setOpenState(!section.classList.contains('is-open'));
    });
  });

  document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => {
      document.getElementById(button.dataset.modalOpen)?.classList.add('is-open');
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', () => {
      button.closest('.modal')?.classList.remove('is-open');
    });
  });

  document.querySelectorAll('.modal').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        modal.classList.remove('is-open');
      }
    });
  });

  const panel = document.getElementById('obligationPanel');
  const select = document.getElementById('obligationSelect');
  const amount = document.getElementById('expenseAmount');
  const preview = document.getElementById('obligationPreview');
  const radioButtons = document.querySelectorAll('input[name="es_pago_obligacion"]');
  const incomePanel = document.getElementById('incomeCarteraPanel');
  const incomeSelect = document.getElementById('incomeCarteraSelect');
  const incomeAmount = document.getElementById('incomeAmount');
  const incomePreview = document.getElementById('incomeCarteraPreview');
  const incomeRadioButtons = document.querySelectorAll('input[name="es_cobro_cartera"]');

  const renderPreview = () => {
    if (!preview || !select) {
      return;
    }

    const option = select.selectedOptions[0];
    if (!option || !option.value) {
      preview.innerHTML = '';
      return;
    }

    const pending = Number(option.dataset.pending || 0);
    const enteredAmount = Number(amount?.value || 0);
    const remaining = pending - enteredAmount;
    const exceeds = enteredAmount > pending && enteredAmount > 0;

    preview.innerHTML = `
      <strong>${option.dataset.icon} ${option.dataset.name}</strong>
      <div>Saldo actual: ${formatCOP(pending)} pendiente</div>
      <div>Con este pago quedará: ${formatCOP(Math.max(remaining, 0))} pendiente</div>
      ${exceeds ? `<div class="warning-text">⚠️ El monto supera el saldo pendiente (${formatCOP(pending)}). ¿Deseas continuar?</div>` : ''}
    `;
  };

  radioButtons.forEach((radio) => {
    radio.addEventListener('change', () => {
      if (radio.value === '1' && radio.checked) {
        panel?.classList.add('is-visible');
      }
      if (radio.value === '0' && radio.checked) {
        panel?.classList.remove('is-visible');
      }
      renderPreview();
    });
  });

  select?.addEventListener('change', renderPreview);
  amount?.addEventListener('input', renderPreview);
  renderPreview();

  const renderIncomePreview = () => {
    if (!incomePreview || !incomeSelect) {
      return;
    }

    const option = incomeSelect.selectedOptions[0];
    if (!option || !option.value) {
      incomePreview.innerHTML = '';
      return;
    }

    const pending = Number(option.dataset.pending || 0);
    const enteredAmount = Number(incomeAmount?.value || 0);
    const remaining = pending - enteredAmount;
    const exceeds = enteredAmount > pending && enteredAmount > 0;

    incomePreview.innerHTML = `
      <strong>${option.dataset.person}</strong>
      <div>Cartera: ${option.dataset.concept}</div>
      <div>Saldo actual: ${formatCOP(pending)} pendiente</div>
      <div>Con este ingreso quedará: ${formatCOP(Math.max(remaining, 0))} pendiente</div>
      ${exceeds ? `<div class="warning-text">El monto supera el saldo pendiente (${formatCOP(pending)}).</div>` : ''}
    `;
  };

  incomeRadioButtons.forEach((radio) => {
    radio.addEventListener('change', () => {
      if (radio.value === '1' && radio.checked) {
        incomePanel?.classList.add('is-visible');
      }
      if (radio.value === '0' && radio.checked) {
        incomePanel?.classList.remove('is-visible');
      }
      renderIncomePreview();
    });
  });

  incomeSelect?.addEventListener('change', renderIncomePreview);
  incomeAmount?.addEventListener('input', renderIncomePreview);
  renderIncomePreview();

  if (window.Chart) {
    Chart.defaults.color = '#8892a4';
    Chart.defaults.borderColor = '#2a2d3e';
    Chart.defaults.font.family = 'Inter';
    Chart.defaults.responsive = true;
    Chart.defaults.maintainAspectRatio = false;
  }

  const monthlyCanvas = document.getElementById('expensesMonthlyChart');
  if (monthlyCanvas && window.Chart) {
    const data = JSON.parse(monthlyCanvas.dataset.chart || '{}');
    new Chart(monthlyCanvas, {
      type: 'bar',
      data: {
        labels: data.labels || [],
        datasets: [{
          label: 'Gastos',
          data: data.values || [],
          backgroundColor: '#6c63ff',
          borderRadius: 6,
          borderSkipped: false,
        }],
      },
      options: {
        layout: {
          padding: { top: 8, right: 12, bottom: 0, left: 0 },
        },
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: (value) => formatCOP(value),
              maxTicksLimit: 6,
              padding: 10,
            },
            grid: { color: '#2a2d3e' },
          },
          x: {
            grid: { display: false },
            ticks: {
              maxRotation: 0,
              minRotation: 0,
            },
          },
        },
      },
    });
  }

  const categoryCanvas = document.getElementById('expensesCategoryChart');
  if (categoryCanvas && window.Chart) {
    const data = JSON.parse(categoryCanvas.dataset.chart || '{}');
    new Chart(categoryCanvas, {
      type: 'doughnut',
      data: {
        labels: data.labels || [],
        datasets: [{
          data: data.values || [],
          backgroundColor: data.colors || ['#6c63ff', '#63b3ed', '#48bb78'],
          borderWidth: 0,
        }],
      },
      options: {
        layout: {
          padding: 12,
        },
        cutout: '62%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 10,
              padding: 16,
              usePointStyle: true,
              pointStyle: 'circle',
            },
          },
        },
      },
    });
  }
})();
