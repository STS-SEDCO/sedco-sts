(() => {
  const STORAGE_KEY = 'sedcoApplications';
  let applications = [];
  let filter = 'all';

  const $ = (id) => document.getElementById(id);

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function load() {
    try {
      const value = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      applications = Array.isArray(value) ? value : [];
    } catch {
      applications = [];
    }
  }

  function statusMeta(status) {
    const key = String(status || 'pending').toLowerCase();
    if (key === 'approved') return { label: 'Approved', cls: 'approved', icon: 'bi-check2-circle' };
    if (key === 'rejected') return { label: 'Rejected', cls: 'rejected', icon: 'bi-x-circle' };
    if (key === 'correction') return { label: 'Needs Correction', cls: 'correction', icon: 'bi-exclamation-circle' };
    return { label: 'Pending Review', cls: 'pending', icon: 'bi-clock-history' };
  }

  function formatDate(value, includeTime = false) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return escapeHtml(value);
    return new Intl.DateTimeFormat('en-MY', includeTime
      ? { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }
      : { day:'2-digit', month:'short', year:'numeric' }
    ).format(date);
  }

  function updateStats() {
    $('statTotal').textContent = applications.length;
    $('statPending').textContent = applications.filter(a => (a.status || 'pending') === 'pending').length;
    $('statApproved').textContent = applications.filter(a => a.status === 'approved').length;
    $('statAction').textContent = applications.filter(a => a.status === 'correction' || a.status === 'rejected').length;
  }

  function filtered() {
    const q = ($('applicationSearch').value || '').trim().toLowerCase();
    return applications.filter(a => {
      const status = a.status || 'pending';
      if (filter !== 'all' && status !== filter) return false;
      if (!q) return true;
      return [a.id,a.type,a.formName,a.title,a.applicant,status]
        .some(v => String(v || '').toLowerCase().includes(q));
    });
  }

  function render() {
    updateStats();
    const rows = filtered();
    const tbody = $('applicationRows');
    const empty = $('emptyState');

    if (!rows.length) {
      tbody.innerHTML = '';
      empty.classList.remove('d-none');
      return;
    }

    empty.classList.add('d-none');
    tbody.innerHTML = rows.map(a => {
      const s = statusMeta(a.status);
      return `
        <tr>
          <td>
            <div class="app-id">${escapeHtml(a.id)}</div>
            <div class="app-submitted">Submitted ${formatDate(a.submittedAt, true)}</div>
          </td>
          <td>
            <span class="type-pill type-${escapeHtml((a.type || 'form').toLowerCase())}">${escapeHtml(a.type || 'FORM')}</span>
          </td>
          <td>
            <div class="application-title">${escapeHtml(a.title || a.formName || 'Application')}</div>
            <div class="application-meta">${escapeHtml(a.formName || '')}</div>
          </td>
          <td>
            <div class="application-title">${escapeHtml(a.applicant || 'Guest')}</div>
            <div class="application-meta">${formatDate(a.applicationDate)}</div>
          </td>
          <td><span class="status-pill status-${s.cls}"><i class="bi ${s.icon}"></i>${s.label}</span></td>
          <td class="text-end"><button class="view-btn" type="button" data-view-id="${escapeHtml(a.id)}">View details <i class="bi bi-arrow-up-right"></i></button></td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('[data-view-id]').forEach(btn => {
      btn.addEventListener('click', () => showDetails(btn.dataset.viewId));
    });
  }

  function humanizeKey(key) {
    return String(key || '').replaceAll('_', ' ').replace(/\b\w/g, s => s.toUpperCase());
  }

  function showDetails(id) {
    const a = applications.find(item => item.id === id);
    if (!a) return;
    const s = statusMeta(a.status);

    $('modalApplicationId').textContent = a.id || 'Application';
    $('modalApplicationTitle').textContent = a.title || a.formName || 'Application';
    $('modalStatus').className = 'status-pill status-' + s.cls;
    $('modalStatus').innerHTML = '<i class="bi ' + s.icon + '"></i>' + s.label;
    $('modalType').textContent = a.type || 'FORM';
    $('modalApplicant').textContent = a.applicant || 'Guest';
    $('modalSubmitted').textContent = formatDate(a.submittedAt, true);
    $('modalApplicationDate').textContent = formatDate(a.applicationDate);

    const detailEntries = Object.entries(a.data || {}).filter(([,v]) => {
      if (Array.isArray(v)) return v.some(x => String(x || '').trim());
      return String(v || '').trim() !== '';
    });

    $('modalFields').innerHTML = detailEntries.length
      ? detailEntries.map(([k,v]) => `
          <div class="detail-field">
            <span>${escapeHtml(humanizeKey(k))}</span>
            <strong>${escapeHtml(Array.isArray(v) ? v.join(', ') : v)}</strong>
          </div>`).join('')
      : '<div class="detail-empty">No additional form details available.</div>';

    bootstrap.Modal.getOrCreateInstance($('applicationModal')).show();
  }

  function setFilter(next) {
    filter = next;
    document.querySelectorAll('[data-filter]').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.filter === next);
    });
    render();
  }

  document.addEventListener('DOMContentLoaded', () => {
    load();

    const params = new URLSearchParams(location.search);
    if (params.get('submitted') === '1') {
      $('successToast').classList.add('show');
      setTimeout(() => $('successToast').classList.remove('show'), 3800);
      history.replaceState({}, '', location.pathname);
    }

    $('applicationSearch').addEventListener('input', render);
    document.querySelectorAll('[data-filter]').forEach(btn => {
      btn.addEventListener('click', () => setFilter(btn.dataset.filter));
    });

    render();
  });
})();