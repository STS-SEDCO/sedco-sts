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
      const stored = Array.isArray(value) ? value : [];

      if (window.SEDCO_STATIC_MODE === true) {
        let previewUser = null;
        try {
          previewUser = JSON.parse(localStorage.getItem('sedcoPreviewUser') || 'null');
        } catch {
          previewUser = null;
        }

        const email = String(previewUser?.email || '').trim().toLowerCase();
        applications = email
          ? stored.filter(item => String(item.ownerEmail || '').trim().toLowerCase() === email)
          : [];
      } else {
        applications = stored;
      }
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
      case 'cancelled':
        return { label: 'Cancelled', cls: 'cancelled', icon: 'bi-slash-circle' };
      default:
        return { label: 'Pending Review', cls: 'pending', icon: 'bi-clock-history' };
    }
  }

  function stageLabel(stage) {
    switch (String(stage || '').toLowerCase()) {
      case 'hod': return 'Head of Department';
      case 'training': return 'Training Department';
      case 'gm': return 'General Manager';
      case 'chairman': return 'Pengerusi';
      case 'finance': return 'Kewangan';
      case 'completed': return 'Completed';
      default: return 'Pending';
    }
  }

  function formatDate(value, includeTime = false) {
    if (!value) return 'Not available';

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
        status,
        application.stageLabel,
        application.currentStage
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
      const stage = application.stageLabel || stageLabel(application.currentStage);
      const actionUrl = application.editUrl || application.viewUrl;
      const primaryAction = actionUrl
        ? `<a class="view-btn${application.editUrl ? ' is-correction' : ''}" href="${escapeHtml(actionUrl)}">
             ${application.editUrl ? (application.editLabel || 'Edit submission') : 'View details'} <i class="bi bi-arrow-up-right"></i>
           </a>`
        : `<button class="view-btn" type="button" data-view-id="${escapeHtml(application.id)}">
             View details <i class="bi bi-arrow-up-right"></i>
           </button>`;

      const canCancel = application.canCancel === true
        || (
          window.SEDCO_STATIC_MODE === true
          && ['pending','correction'].includes(String(application.status || 'pending').toLowerCase())
        );

      const cancelAction = canCancel
        ? `<button class="cancel-app-btn" type="button" data-cancel-id="${escapeHtml(application.id)}">
             <i class="bi bi-x-circle"></i> Cancel
           </button>`
        : '';

      const action = `<div class="status-row-actions">${primaryAction}${cancelAction}</div>`;

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
            ${application.type === 'BPL' ? `<div class="application-stage-text">Stage: ${escapeHtml(stage)}</div>` : ''}
            ${application.status === 'correction' && application.reviewNote
              ? `<div class="application-correction-note"><i class="bi bi-info-circle"></i><span>${escapeHtml(application.reviewNote)}</span></div>`
              : ''}
          </td>
          <td class="text-end">
            ${action}
          </td>
        </tr>
      `;
    }).join('');

    tbody.querySelectorAll('[data-view-id]').forEach(button => {
      button.addEventListener('click', () => showDetails(button.dataset.viewId));
    });

    tbody.querySelectorAll('[data-cancel-id]').forEach(button => {
      button.addEventListener('click', () => {
        const target = $('cancelApplicationNo');
        const reason = $('cancelReason');

        if (target) target.value = button.dataset.cancelId || '';
        if (reason) reason.value = '';

        bootstrap.Modal.getOrCreateInstance($('cancelApplicationModal')).show();

        window.setTimeout(() => reason?.focus(), 250);
      });
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

    const stageTarget = $('modalWorkflowStage');
    if (stageTarget) {
      stageTarget.textContent = application.type === 'BPL'
        ? (application.stageLabel || stageLabel(application.currentStage))
        : 'Not applicable';
    }

    const noteTarget = $('modalReviewNote');
    if (noteTarget) {
      noteTarget.textContent = application.reviewNote || 'No review note';
    }

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

  function showToastMessage(message, isError = false) {
    const toast = isError ? $('errorToast') : $('successToast');
    const text = isError ? $('errorToastText') : $('successToastText');

    if (text) text.textContent = message;
    if (!toast) return;

    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 4000);
  }

  function setupStaticCancellation() {
    if (window.SEDCO_STATIC_MODE !== true) return;

    const form = $('cancelApplicationForm');
    if (!form) return;

    form.addEventListener('submit', event => {
      event.preventDefault();

      const applicationNo = String($('cancelApplicationNo')?.value || '').trim();
      const reason = String($('cancelReason')?.value || '').trim();

      if (reason.length < 5) {
        showToastMessage('Cancellation reason must be at least 5 characters.', true);
        $('cancelReason')?.focus();
        return;
      }

      const index = applications.findIndex(item => item.id === applicationNo);
      if (index < 0) return;

      const current = applications[index];
      if (!['pending','correction'].includes(String(current.status || '').toLowerCase())) {
        showToastMessage('This application can no longer be cancelled.', true);
        return;
      }

      const updatedApplication = {
        ...current,
        status: 'cancelled',
        currentStage: 'completed',
        stageLabel: 'Completed',
        canCancel: false,
        editUrl: null,
        cancellationReason: reason,
        cancelledAt: new Date().toISOString(),
        updatedAt: new Date().toISOString()
      };

      applications[index] = updatedApplication;

      const allStored = (() => {
        try {
          const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
          return Array.isArray(raw) ? raw : [];
        } catch {
          return [];
        }
      })();

      const globalIndex = allStored.findIndex(item => item.id === applicationNo);
      if (globalIndex >= 0) allStored[globalIndex] = updatedApplication;

      localStorage.setItem(STORAGE_KEY, JSON.stringify(allStored));
      bootstrap.Modal.getInstance($('cancelApplicationModal'))?.hide();
      showToastMessage('Application cancelled successfully. It remains in your history.');
      render();
    });
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
    setupStaticCancellation();

    const params = new URLSearchParams(location.search);

    if (
      params.get('submitted') === '1'
      || params.get('resubmitted') === '1'
      || params.get('cancelled') === '1'
    ) {
      const toast = $('successToast');
      const text = $('successToastText');

      if (params.get('resubmitted') === '1' && text) {
        text.textContent = 'Correction submitted successfully. The application has returned to the same reviewer stage.';
      } else if (params.get('cancelled') === '1' && text) {
        text.textContent = 'Application cancelled successfully. It remains in your history for reference.';
      }

      if (toast) {
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3800);
      }

      history.replaceState({}, '', location.pathname);
    }

    if (params.get('cancel_error') === '1' || params.get('cancel_setup') === '1') {
      const toast = $('errorToast');
      const text = $('errorToastText');

      if (params.get('cancel_setup') === '1' && text) {
        text.textContent = 'Import CANCEL_APPLICATION_UPGRADE.sql once before using cancellation.';
      }

      if (toast) {
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 4200);
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