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

  if (window.Chart) {
    Chart.defaults.color = '#8892a4';
    Chart.defaults.borderColor = '#2a2d3e';
    Chart.defaults.font.family = 'Inter';
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
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: (value) => formatCOP(value) },
            grid: { color: '#2a2d3e' },
          },
          x: { grid: { color: '#2a2d3e' } },
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
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { boxWidth: 10, padding: 16 },
          },
        },
      },
    });
  }
})();
