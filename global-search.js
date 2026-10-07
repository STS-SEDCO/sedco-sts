(() => {
  const input = document.getElementById('globalSearchInput');
  const type = document.getElementById('globalSearchType');
  const status = document.getElementById('globalSearchStatus');
  const results = document.getElementById('globalSearchResults');
  const empty = document.getElementById('globalSearchEmpty');
  const meta = document.getElementById('globalSearchMeta');
  const count = document.getElementById('globalSearchCount');

  const escapeHtml = value => String(value ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'",'&#039;');

  function readUser() {
    try { return JSON.parse(localStorage.getItem('sedcoPreviewUser') || 'null'); }
    catch { return null; }
  }

  function readApps() {
    try {
      const value = JSON.parse(localStorage.getItem('sedcoApplications') || '[]');
      return Array.isArray(value) ? value : [];
    } catch { return []; }
  }

  function stageLabel(stage) {
    return {
      training:'Training Department',
      hod:'Head of Department',
      gm:'General Manager',
      chairman:'Pengerusi',
      finance:'Kewangan',
      completed:'Completed'
    }[String(stage || '').toLowerCase()] || 'Pending';
  }

  function scopeRows(rows) {
    const user = readUser();
    if (!user) return [];

    const role = String(user.role || '').toLowerCase();
    const email = String(user.email || '').toLowerCase();
    const dept = String(user.department || '').toLowerCase();

    if (role === 'staff') {
      return rows.filter(row => String(row.ownerEmail || '').toLowerCase() === email);
    }

    if (['head_of_department','head_of_division'].includes(role) && dept) {
      return rows.filter(row => String(row.department || row.data?.bahagian || '').toLowerCase() === dept);
    }

    return rows;
  }

  function render() {
    const q = String(input?.value || '').trim().toLowerCase();
    const selectedType = String(type?.value || '').toUpperCase();
    const selectedStatus = String(status?.value || '').toLowerCase();

    if (!q) {
      empty.hidden = false;
      meta.hidden = true;
      results.innerHTML = '';
      return;
    }

    const rows = scopeRows(readApps()).filter(row => {
      const rowType = String(row.type || row.form_type || '').toUpperCase();
      const rowStatus = String(row.status || 'pending').toLowerCase();
      if (selectedType && rowType !== selectedType) return false;
      if (selectedStatus && rowStatus !== selectedStatus) return false;

      return [
        row.id,
        row.application_no,
        row.title,
        row.applicant,
        row.ownerEmail,
        row.department,
        row.data?.bahagian,
        rowType,
        rowStatus
      ].some(value => String(value || '').toLowerCase().includes(q));
    }).slice(0,50);

    empty.hidden = true;
    meta.hidden = false;
    count.textContent = String(rows.length);

    results.innerHTML = rows.length ? rows.map(row => {
      const ref = row.id || row.application_no || 'Application';
      const title = row.title || row.formName || 'Application';
      const applicant = row.applicant || row.ownerEmail || 'Applicant';
      const department = row.department || row.data?.bahagian || 'No department';
      const rowType = String(row.type || row.form_type || 'FORM').toUpperCase();
      const rowStatus = String(row.status || 'pending').toLowerCase();

      return `
        <a class="global-search-result" href="application-status.html">
          <span class="global-search-result-icon"><i class="bi bi-file-earmark-text"></i></span>
          <div class="global-search-result-copy">
            <div><strong>${escapeHtml(title)}</strong><span>${escapeHtml(ref)}</span></div>
            <p>${escapeHtml(applicant)} · ${escapeHtml(department)} · ${escapeHtml(rowType)}</p>
          </div>
          <div class="global-search-result-meta">
            <span class="status-pill status-${escapeHtml(rowStatus)}">${escapeHtml(rowStatus.charAt(0).toUpperCase()+rowStatus.slice(1))}</span>
            <small>${escapeHtml(stageLabel(row.currentStage || row.current_stage))}</small>
          </div>
          <i class="bi bi-chevron-right"></i>
        </a>
      `;
    }).join('') : `
      <div class="global-search-no-results">
        <span><i class="bi bi-search"></i></span>
        <strong>No matching records</strong>
        <p>Try a shorter keyword or remove one of the filters.</p>
      </div>
    `;
  }

  input?.addEventListener('input', render);
  type?.addEventListener('change', render);
  status?.addEventListener('change', render);
  render();
})();