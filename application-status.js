(() => {
  const STORAGE_KEY = 'sedcoApplications';
  let applications = [];
  let filter = 'all';

  const $ = id => document.getElementById(id);

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function loadApplications() {
    if (Array.isArray(window.SEDCO_APPLICATIONS)) {
      applications = window.SEDCO_APPLICATIONS;
      return;
    }

    try {
      const value = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      applications = Array.isArray(value) ? value : [];
    } catch {
      applications = [];
    }
  }

  function statusMeta(status) {
    switch (String(status || 'pending').toLowerCase()) {
      case 'approved':
        return { label: 'Approved', cls: 'approved', icon: 'bi-check2-circle' };
      case 'rejected':
        return { label: 'Rejected', cls: 'rejected', icon: 'bi-x-circle' };
      case 'correction':
        return { label: 'Needs Correction', cls: 'correction', icon: 'bi-exclamation-circle' };
      default:
        return { label: 'Pending Review', cls: 'pending', icon: 'bi-clock-history' };
    }
  }

  function formatDate(value, includeTime = false) {
    if (!value) return '—';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return escapeHtml(value);

    return new Intl.DateTimeFormat(
      'en-MY',
      includeTime
        ? { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }
        : { day: '2-digit', month: 'short', year: 'numeric' }
    ).format(date);
  }

  function updateStats() {
    $('statTotal').textContent = applications.length;
    $('statPending').textContent = applications.filter(a => (a.status || 'pending') === 'pending').length;
    $('statApproved').textContent = applications.filter(a => a.status === 'approved').length;
    $('statAction').textContent = applications.filter(a => ['correction', 'rejected'].includes(a.status)).length;
  }

  function filteredApplications() {
    const query = ($('applicationSearch')?.value || '').trim().toLowerCase();

    return applications.filter(application => {
      const status = application.status || 'pending';

      if (filter !== 'all' && status !== filter) return false;
      if (!query) return true;

      return [
        application.id,
        application.type,
        application.formName,
        application.title,
        application.applicant,
        status
      ].some(value => String(value || '').toLowerCase().includes(query));
    });
  }

  function render() {
    updateStats();

    const tbody = $('applicationRows');
    const empty = $('emptyState');
    if (!tbody || !empty) return;

    const rows = filteredApplications();

    if (!rows.length) {
      tbody.innerHTML = '';
      empty.classList.remove('d-none');
      return;
    }

    empty.classList.add('d-none');

    tbody.innerHTML = rows.map(application => {
      const status = statusMeta(application.status);

      return `
        <tr>
          <td>
            <div class="app-id">${escapeHtml(application.id)}</div>
            <div class="app-submitted">Submitted ${formatDate(application.submittedAt, true)}</div>
          </td>
          <td>
            <span class="type-pill type-${escapeHtml((application.type || 'form').toLowerCase())}">
              ${escapeHtml(application.type || 'FORM')}
            </span>
          </td>
          <td>
            <div class="application-title">${escapeHtml(application.title || application.formName || 'Application')}</div>
            <div class="application-meta">${escapeHtml(application.formName || '')}</div>
          </td>
          <td>
            <div class="application-title">${escapeHtml(application.applicant || 'Guest')}</div>
            <div class="application-meta">${formatDate(application.applicationDate)}</div>
          </td>
          <td>
            <span class="status-pill status-${status.cls}">
              <i class="bi ${status.icon}"></i>${status.label}
            </span>
          </td>
          <td class="text-end">
            <button class="view-btn" type="button" data-view-id="${escapeHtml(application.id)}">
              View details <i class="bi bi-arrow-up-right"></i>
            </button>
          </td>
        </tr>
      `;
    }).join('');

    tbody.querySelectorAll('[data-view-id]').forEach(button => {
      button.addEventListener('click', () => showDetails(button.dataset.viewId));
    });
  }

  function humanizeKey(key) {
    return String(key || '')
      .replaceAll('_', ' ')
      .replace(/\b\w/g, character => character.toUpperCase());
  }

  function showDetails(id) {
    const application = applications.find(item => item.id === id);
    if (!application) return;

    const status = statusMeta(application.status);

    $('modalApplicationId').textContent = application.id || 'Application';
    $('modalApplicationTitle').textContent = application.title || application.formName || 'Application';
    $('modalStatus').className = `status-pill status-${status.cls}`;
    $('modalStatus').innerHTML = `<i class="bi ${status.icon}"></i>${status.label}`;
    $('modalType').textContent = application.type || 'FORM';
    $('modalApplicant').textContent = application.applicant || 'Guest';
    $('modalSubmitted').textContent = formatDate(application.submittedAt, true);
    $('modalApplicationDate').textContent = formatDate(application.applicationDate);

    const entries = Object.entries(application.data || {}).filter(([, value]) => {
      if (Array.isArray(value)) return value.some(item => String(item || '').trim());
      return String(value || '').trim() !== '';
    });

    $('modalFields').innerHTML = entries.length
      ? entries.map(([key, value]) => `
          <div class="detail-field">
            <span>${escapeHtml(humanizeKey(key))}</span>
            <strong>${escapeHtml(Array.isArray(value) ? value.join(', ') : value)}</strong>
          </div>
        `).join('')
      : '<div class="detail-empty">No additional form details available.</div>';

    bootstrap.Modal.getOrCreateInstance($('applicationModal')).show();
  }

  function setFilter(nextFilter) {
    filter = nextFilter;

    document.querySelectorAll('[data-filter]').forEach(button => {
      button.classList.toggle('active', button.dataset.filter === nextFilter);
    });

    render();
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadApplications();

    const params = new URLSearchParams(location.search);

    if (params.get('submitted') === '1') {
      const toast = $('successToast');

      if (toast) {
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3800);
      }

      history.replaceState({}, '', location.pathname);
    }

    $('applicationSearch')?.addEventListener('input', render);

    document.querySelectorAll('[data-filter]').forEach(button => {
      button.addEventListener('click', () => setFilter(button.dataset.filter));
    });

    render();
  });
})();