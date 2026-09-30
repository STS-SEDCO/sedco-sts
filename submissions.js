(() => {
  const STORAGE_KEY = 'sedcoApplications';
  let submissions = [];
  let filter = 'all';

  const $ = id => document.getElementById(id);

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');
  }

  function load() {
    if (Array.isArray(window.SEDCO_SUBMISSIONS)) {
      submissions = window.SEDCO_SUBMISSIONS;
      return;
    }

    try {
      const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      submissions = Array.isArray(stored) ? stored : [];
    } catch {
      submissions = [];
    }
  }

  function statusMeta(status) {
    switch (String(status || 'pending').toLowerCase()) {
      case 'approved':
        return { label:'Approved', cls:'approved', icon:'bi-check2-circle' };
      case 'rejected':
        return { label:'Rejected', cls:'rejected', icon:'bi-x-circle' };
      case 'correction':
        return { label:'Needs correction', cls:'correction', icon:'bi-exclamation-circle' };
      default:
        return { label:'Pending review', cls:'pending', icon:'bi-clock-history' };
    }
  }

  function stageMeta(stage) {
    switch (String(stage || '').toLowerCase()) {
      case 'hod':
        return { label:'Head of Department', cls:'hod', icon:'bi-person-check' };
      case 'training':
        return { label:'Training Department', cls:'training', icon:'bi-briefcase' };
      case 'gm':
        return { label:'General Manager', cls:'gm', icon:'bi-person-badge' };
      case 'completed':
        return { label:'Completed', cls:'completed', icon:'bi-check2-all' };
      default:
        return { label:'—', cls:'none', icon:'bi-dash' };
    }
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

  function isThisWeek(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return false;

    const now = new Date();
    const day = (now.getDay() + 6) % 7;
    const start = new Date(now);
    start.setHours(0,0,0,0);
    start.setDate(now.getDate() - day);

    return date >= start;
  }

  function updateStats() {
    $('submissionTotal').textContent = submissions.length;
    $('submissionPending').textContent = submissions.filter(x => (x.status || 'pending') === 'pending').length;
    $('submissionReviewed').textContent = submissions.filter(x => (x.status || 'pending') !== 'pending').length;
    $('submissionWeek').textContent = submissions.filter(x => isThisWeek(x.submittedAt)).length;
  }

  function filteredRows() {
    const query = ($('submissionSearch')?.value || '').trim().toLowerCase();

    return submissions.filter(item => {
      const status = item.status || 'pending';
      if (filter !== 'all' && status !== filter) return false;
      if (!query) return true;

      return [
        item.id,
        item.type,
        item.title,
        item.formName,
        item.applicant,
        status,
        item.stageLabel,
        item.currentStage
      ].some(value => String(value || '').toLowerCase().includes(query));
    });
  }

  function render() {
    updateStats();

    const tbody = $('submissionRows');
    const empty = $('submissionEmpty');
    if (!tbody || !empty) return;

    const rows = filteredRows();

    if (!rows.length) {
      tbody.innerHTML = '';
      empty.classList.remove('d-none');
      return;
    }

    empty.classList.add('d-none');

    tbody.innerHTML = rows.map(item => {
      const meta = statusMeta(item.status);
      const stage = stageMeta(item.currentStage);
      const action = item.reviewUrl
        ? `<a class="submission-review-btn${item.canReview ? ' is-ready' : ''}" href="${escapeHtml(item.reviewUrl)}">
             ${item.canReview ? 'Review' : 'View form'} <i class="bi bi-arrow-up-right"></i>
           </a>`
        : `<button class="submission-view-btn" type="button" data-submission-id="${escapeHtml(item.id)}">
             View <i class="bi bi-arrow-up-right"></i>
           </button>`;

      return `
        <tr>
          <td>
            <div class="submission-ref">${escapeHtml(item.id)}</div>
            <div class="submission-subtext">${formatDate(item.submittedAt, true)}</div>
          </td>
          <td>
            <div class="submission-person">${escapeHtml(item.applicant || 'Guest')}</div>
            <div class="submission-subtext">Applicant</div>
          </td>
          <td>
            <span class="type-pill type-${escapeHtml((item.type || 'form').toLowerCase())}">${escapeHtml(item.type || 'FORM')}</span>
          </td>
          <td>
            <div class="submission-title">${escapeHtml(item.title || item.formName || 'Submission')}</div>
            <div class="submission-subtext">${escapeHtml(item.formName || '')}</div>
          </td>
          <td>
            <span class="status-pill status-${meta.cls}"><i class="bi ${meta.icon}"></i>${meta.label}</span>
          </td>
          <td>
            <span class="submission-stage-pill stage-${stage.cls}"><i class="bi ${stage.icon}"></i>${stage.label}</span>
          </td>
          <td class="text-end">
            ${action}
          </td>
        </tr>
      `;
    }).join('');

    tbody.querySelectorAll('[data-submission-id]').forEach(button => {
      button.addEventListener('click', () => openDetails(button.dataset.submissionId));
    });
  }

  function humanize(key) {
    return String(key || '').replaceAll('_',' ').replace(/\b\w/g, c => c.toUpperCase());
  }

  function openDetails(id) {
    const item = submissions.find(x => x.id === id);
    if (!item) return;

    const meta = statusMeta(item.status);

    $('submissionModalRef').textContent = item.id || 'Submission';
    $('submissionModalTitle').textContent = item.title || item.formName || 'Submission details';
    $('submissionModalStatus').className = `status-pill status-${meta.cls}`;
    $('submissionModalStatus').innerHTML = `<i class="bi ${meta.icon}"></i>${meta.label}`;
    $('submissionModalApplicant').textContent = item.applicant || 'Guest';
    $('submissionModalType').textContent = item.type || 'FORM';
    $('submissionModalDate').textContent = formatDate(item.submittedAt, true);
    if ($('submissionModalStage')) {
      $('submissionModalStage').textContent = item.stageLabel || stageMeta(item.currentStage).label;
    }

    const entries = Object.entries(item.data || {}).filter(([,value]) => {
      if (Array.isArray(value)) return value.some(v => String(v || '').trim());
      return String(value || '').trim() !== '';
    });

    $('submissionModalFields').innerHTML = entries.length
      ? entries.map(([key,value]) => `
          <div class="submission-detail-field">
            <span>${escapeHtml(humanize(key))}</span>
            <strong>${escapeHtml(Array.isArray(value) ? value.join(', ') : value)}</strong>
          </div>
        `).join('')
      : '<div class="submission-detail-empty">No additional form details available.</div>';

    bootstrap.Modal.getOrCreateInstance($('submissionModal')).show();
  }

  function setFilter(next) {
    filter = next;
    document.querySelectorAll('[data-submission-filter]').forEach(button => {
      button.classList.toggle('active', button.dataset.submissionFilter === next);
    });
    render();
  }

  document.addEventListener('DOMContentLoaded', () => {
    load();

    $('submissionSearch')?.addEventListener('input', render);

    document.querySelectorAll('[data-submission-filter]').forEach(button => {
      button.addEventListener('click', () => setFilter(button.dataset.submissionFilter));
    });

    render();
  });
})();