(() => {
  const APP_KEY = 'sedcoApplications';
  const HISTORY_KEY = 'sedcoApprovalHistory';
  let history = [];
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

  function stageMeta(stage) {
    switch (String(stage || '').toLowerCase()) {
      case 'training': return { label:'Training Department', cls:'training', icon:'bi-briefcase' };
      case 'hod': return { label:'Head of Department', cls:'hod', icon:'bi-person-check' };
      case 'gm': return { label:'General Manager', cls:'gm', icon:'bi-person-badge' };
      case 'chairman': return { label:'Pengerusi', cls:'chairman', icon:'bi-award' };
      case 'finance': return { label:'Kewangan', cls:'finance', icon:'bi-cash-stack' };
      default: return { label:'Not available', cls:'none', icon:'bi-dash' };
    }
  }

  function decisionMeta(decision) {
    switch (String(decision || '').toLowerCase()) {
      case 'approved': return { label:'Approved', cls:'approved', icon:'bi-check2-circle' };
      case 'correction': return { label:'Correction Requested', cls:'correction', icon:'bi-arrow-counterclockwise' };
      case 'rejected': return { label:'Rejected', cls:'rejected', icon:'bi-x-circle' };
      default: return { label:'Not available', cls:'pending', icon:'bi-dash' };
    }
  }

  function formatDate(value) {
    if (!value) return 'Not available';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat('en-MY', {
      day:'2-digit',
      month:'short',
      year:'numeric',
      hour:'2-digit',
      minute:'2-digit'
    }).format(date);
  }

  function load() {
    try {
      const stored = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
      if (Array.isArray(stored) && stored.length) {
        history = stored;
        return;
      }
    } catch {}

    let apps = [];
    try {
      const storedApps = JSON.parse(localStorage.getItem(APP_KEY) || '[]');
      apps = Array.isArray(storedApps) ? storedApps : [];
    } catch {}

    let previewUser = null;
    try {
      previewUser = JSON.parse(localStorage.getItem('sedcoPreviewUser') || 'null');
    } catch {}

    history = apps
      .filter(app => ['approved','correction','rejected'].includes(String(app.status || '').toLowerCase()))
      .map(app => ({
        id:app.id || app.application_no || 'Application',
        title:app.title || app.formName || 'Permohonan Latihan',
        applicant:app.applicant || app.data?.nama || 'Guest',
        department:app.department || app.data?.bahagian || 'Not assigned',
        stage:app.currentStage || app.current_stage || 'training',
        decision:String(app.status || '').toLowerCase(),
        note:app.reviewNote || app.review_note || 'No review note',
        reviewedAt:app.updatedAt || app.submittedAt || new Date().toISOString(),
        reviewer:previewUser?.fullname || previewUser?.name || 'Preview user'
      }));
  }

  function updateStats() {
    $('historyTotal').textContent = history.length;
    $('historyApproved').textContent = history.filter(x => x.decision === 'approved').length;
    $('historyCorrection').textContent = history.filter(x => x.decision === 'correction').length;
    $('historyRejected').textContent = history.filter(x => x.decision === 'rejected').length;
  }

  function filtered() {
    const query = ($('historySearch')?.value || '').trim().toLowerCase();

    return history.filter(item => {
      if (filter !== 'all' && item.decision !== filter) return false;
      if (!query) return true;

      return [
        item.id,
        item.title,
        item.applicant,
        item.department,
        item.stage,
        item.note
      ].some(value => String(value || '').toLowerCase().includes(query));
    });
  }

  function render() {
    updateStats();

    const rows = filtered();
    const tbody = $('historyRows');
    const empty = $('historyEmpty');

    if (!rows.length) {
      tbody.innerHTML = '';
      empty.classList.remove('d-none');
      return;
    }

    empty.classList.add('d-none');

    tbody.innerHTML = rows.map(item => {
      const stage = stageMeta(item.stage);
      const decision = decisionMeta(item.decision);

      return `
        <tr>
          <td>
            <div class="submission-ref">${escapeHtml(item.id)}</div>
            <div class="submission-subtext">${escapeHtml(item.title || '')}</div>
          </td>
          <td><div class="submission-person">${escapeHtml(item.applicant)}</div></td>
          <td><div class="submission-title">${escapeHtml(item.department)}</div></td>
          <td><span class="submission-stage-pill stage-${stage.cls}"><i class="bi ${stage.icon}"></i>${stage.label}</span></td>
          <td><span class="status-pill status-${decision.cls}"><i class="bi ${decision.icon}"></i>${decision.label}</span></td>
          <td><div class="approval-history-note-cell">${escapeHtml(item.note || 'No review note')}</div></td>
          <td><div class="submission-subtext is-history-date">${escapeHtml(formatDate(item.reviewedAt))}</div></td>
          <td class="text-end">
            <button class="submission-action-view" type="button" data-history-id="${escapeHtml(item.id)}">
              <i class="bi bi-eye"></i><span>View</span>
            </button>
          </td>
        </tr>
      `;
    }).join('');

    tbody.querySelectorAll('[data-history-id]').forEach(button => {
      button.addEventListener('click', () => openDetails(button.dataset.historyId));
    });
  }

  function openDetails(id) {
    const item = history.find(entry => String(entry.id) === String(id));
    if (!item) return;

    const stage = stageMeta(item.stage);
    const decision = decisionMeta(item.decision);

    $('historyModalRef').textContent = item.id;
    $('historyModalTitle').textContent = item.title || 'Approval details';
    $('historyModalApplicant').textContent = item.applicant || 'Not available';
    $('historyModalDepartment').textContent = item.department || 'Not available';
    $('historyModalDate').textContent = formatDate(item.reviewedAt);
    $('historyModalReviewer').textContent = item.reviewer || 'Preview user';
    $('historyModalNote').textContent = item.note || 'No review note';

    $('historyModalDecision').className = `status-pill status-${decision.cls}`;
    $('historyModalDecision').innerHTML = `<i class="bi ${decision.icon}"></i>${decision.label}`;

    $('historyModalStage').className = `submission-stage-pill stage-${stage.cls}`;
    $('historyModalStage').innerHTML = `<i class="bi ${stage.icon}"></i>${stage.label}`;

    bootstrap.Modal.getOrCreateInstance($('historyModal')).show();
  }

  document.addEventListener('DOMContentLoaded', () => {
    load();
    render();

    document.querySelectorAll('[data-history-filter]').forEach(button => {
      button.addEventListener('click', () => {
        filter = button.dataset.historyFilter || 'all';
        document.querySelectorAll('[data-history-filter]').forEach(item => {
          item.classList.toggle('active', item === button);
        });
        render();
      });
    });

    $('historySearch')?.addEventListener('input', render);
  });
})();
