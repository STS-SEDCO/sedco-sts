(() => {
  const STORAGE_KEY = 'sedcoApplications';
  let allRows = [];
  let filteredRows = [];
  let previewUser = null;

  const $ = id => document.getElementById(id);

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');
  }

  function readPreviewUser() {
    try {
      return JSON.parse(localStorage.getItem('sedcoPreviewUser') || 'null');
    } catch {
      return null;
    }
  }

  function readApplications() {
    try {
      const value = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      return Array.isArray(value) ? value : [];
    } catch {
      return [];
    }
  }

  function normalizeRole(role) {
    return String(role || '') === 'head_of_division' ? 'head_of_department' : String(role || '');
  }

  function departmentOf(row) {
    return String(row.department || row.data?.bahagian || row.data?.division || '').trim() || 'Unassigned';
  }

  function applicantOf(row) {
    return String(row.applicant || row.data?.nama || row.data?.employee_name || row.ownerEmail || 'Not available');
  }

  function typeOf(row) {
    return String(row.type || row.form_type || '').toUpperCase() || 'FORM';
  }

  function stageOf(row) {
    return String(row.currentStage || row.current_stage || '').toLowerCase() || (row.status === 'approved' ? 'completed' : 'pending');
  }

  function statusOf(row) {
    return String(row.status || 'pending').toLowerCase();
  }

  function submittedOf(row) {
    return row.submittedAt || row.submitted_at || row.applicationDate || row.data?.tarikh || '';
  }

  function completedOf(row) {
    return row.completedAt || row.completed_at || '';
  }

  function titleOf(row) {
    return String(row.title || row.data?.tajuk || row.formName || typeOf(row));
  }

  function referenceOf(row, index) {
    return String(row.id || row.application_no || ('PREVIEW-' + (index + 1)));
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

  function statusLabel(status) {
    return {
      pending:'Pending',
      approved:'Approved',
      correction:'Needs Correction',
      rejected:'Rejected',
      cancelled:'Cancelled'
    }[String(status || '').toLowerCase()] || 'Pending';
  }

  function formatDate(value) {
    if (!value) return 'Not available';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('en-MY', {
      day:'2-digit', month:'short', year:'numeric'
    }).format(date);
  }

  function dateOnly(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value).slice(0,10);
    return date.toISOString().slice(0,10);
  }

  function feeOf(row) {
    if (typeOf(row) !== 'BPL') return 0;
    const raw = String(row.data?.yuran ?? '').replace(/[^0-9.]/g,'');
    const value = Number(raw);
    return Number.isFinite(value) ? value : 0;
  }

  function scopeRows(rows) {
    const role = normalizeRole(previewUser?.role);
    const department = String(previewUser?.department || '').trim().toLowerCase();

    if (role === 'head_of_department' && department) {
      return rows.filter(row => departmentOf(row).toLowerCase() === department);
    }

    return rows;
  }

  function populateDepartments() {
    const select = $('reportDepartment');
    if (!select) return;

    const role = normalizeRole(previewUser?.role);
    if (role === 'head_of_department') {
      select.closest?.('.report-filter-bar');
      select.hidden = true;
      return;
    }

    const departments = [...new Set(allRows.map(departmentOf).filter(Boolean))].sort();
    departments.forEach(value => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      select.appendChild(option);
    });
  }

  function applyFilters() {
    const status = $('reportStatus')?.value || '';
    const type = $('reportType')?.value || '';
    const department = $('reportDepartment')?.value || '';
    const from = $('reportFrom')?.value || '';
    const to = $('reportTo')?.value || '';

    filteredRows = allRows.filter(row => {
      const submitted = dateOnly(submittedOf(row));
      return (!status || statusOf(row) === status)
        && (!type || typeOf(row) === type)
        && (!department || departmentOf(row) === department)
        && (!from || submitted >= from)
        && (!to || submitted <= to);
    });

    render();
  }

  function renderBars(targetId, entries) {
    const target = $(targetId);
    if (!target) return;

    const values = Object.values(entries);
    const max = Math.max(1, ...values);

    target.innerHTML = Object.entries(entries).map(([label,value]) => `
      <div>
        <div><strong>${escapeHtml(label)}</strong><span>${value}</span></div>
        <i><b style="width:${Math.round((value/max)*1000)/10}%"></b></i>
      </div>
    `).join('');
  }

  function render() {
    const total = filteredRows.length;
    const statusCounts = { pending:0, approved:0, correction:0, rejected:0, cancelled:0 };
    const typeCounts = { BPL:0, PKK:0, TEA:0 };
    const deptCounts = {};
    const processing = [];
    let fees = 0;

    filteredRows.forEach(row => {
      const status = statusOf(row);
      const type = typeOf(row);
      statusCounts[status] = (statusCounts[status] || 0) + 1;
      typeCounts[type] = (typeCounts[type] || 0) + 1;

      const dept = departmentOf(row);
      deptCounts[dept] = (deptCounts[dept] || 0) + 1;
      fees += feeOf(row);

      const start = new Date(submittedOf(row));
      const end = new Date(completedOf(row));
      if (!Number.isNaN(start.getTime()) && !Number.isNaN(end.getTime()) && end >= start) {
        processing.push((end - start) / 86400000);
      }
    });

    const approvalRate = total ? Math.round((statusCounts.approved / total) * 1000) / 10 : 0;
    const avg = processing.length
      ? Math.round((processing.reduce((a,b)=>a+b,0) / processing.length) * 10) / 10
      : null;

    $('reportTotal').textContent = String(total);
    $('reportApprovalRate').textContent = approvalRate + '%';
    $('reportAvgProcessing').textContent = avg === null ? 'Not available' : avg + ' d';
    $('reportFees').textContent = 'RM ' + fees.toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});
    $('reportRecordCount').textContent = total + (total === 1 ? ' result' : ' results');

    renderBars('reportStatusBars', {
      Pending:statusCounts.pending,
      Approved:statusCounts.approved,
      Correction:statusCounts.correction,
      Rejected:statusCounts.rejected,
      Cancelled:statusCounts.cancelled
    });
    renderBars('reportTypeBars', typeCounts);

    const ranking = $('reportDepartmentRanking');
    if (ranking) {
      const sorted = Object.entries(deptCounts).sort((a,b)=>b[1]-a[1]).slice(0,8);
      ranking.innerHTML = sorted.length
        ? sorted.map(([dept,value]) => `<div><span>${escapeHtml(dept)}</span><strong>${value}</strong></div>`).join('')
        : '<p class="sts-muted-copy">No department data available.</p>';
    }

    const tbody = $('reportRows');
    if (!tbody) return;

    tbody.innerHTML = filteredRows.length
      ? filteredRows.map((row,index) => {
          const status = statusOf(row);
          return `
            <tr>
              <td><strong>${escapeHtml(referenceOf(row,index))}</strong></td>
              <td>${escapeHtml(applicantOf(row))}</td>
              <td>${escapeHtml(departmentOf(row))}</td>
              <td>${escapeHtml(typeOf(row))}</td>
              <td><span class="status-pill status-${escapeHtml(status)}">${escapeHtml(statusLabel(status))}</span></td>
              <td>${escapeHtml(stageLabel(stageOf(row)))}</td>
              <td>${escapeHtml(formatDate(submittedOf(row)))}</td>
              <td><a href="application-status.html">View <i class="bi bi-arrow-up-right"></i></a></td>
            </tr>
          `;
        }).join('')
      : '<tr><td colspan="8" class="text-center py-5 text-muted">No records match the selected filters.</td></tr>';
  }

  function exportCsv() {
    const headers = ['Reference','Applicant','Department','Form','Status','Stage','Submitted','Title'];
    const escapeCsv = value => '"' + String(value ?? '').replaceAll('"','""') + '"';
    const lines = [headers.map(escapeCsv).join(',')];

    filteredRows.forEach((row,index) => {
      lines.push([
        referenceOf(row,index),
        applicantOf(row),
        departmentOf(row),
        typeOf(row),
        statusLabel(statusOf(row)),
        stageLabel(stageOf(row)),
        dateOnly(submittedOf(row)),
        titleOf(row)
      ].map(escapeCsv).join(','));
    });

    const blob = new Blob([lines.join('\n')], {type:'text/csv;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'sts-reports-preview.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  document.addEventListener('DOMContentLoaded', () => {
    previewUser = readPreviewUser();

    const allowed = ['admin','training_section','general_manager','head_of_department','head_of_division','pengerusi_besar','finance'];
    if (!previewUser?.email || !allowed.includes(String(previewUser.role || ''))) {
      location.replace('dashboard.html');
      return;
    }

    allRows = scopeRows(readApplications());
    filteredRows = [...allRows];
    populateDepartments();

    $('reportApply')?.addEventListener('click', applyFilters);
    ['reportStatus','reportType','reportDepartment','reportFrom','reportTo'].forEach(id => {
      $(id)?.addEventListener('change', applyFilters);
    });

    $('reportReset')?.addEventListener('click', () => {
      ['reportStatus','reportType','reportDepartment','reportFrom','reportTo'].forEach(id => {
        const field = $(id);
        if (field) field.value = '';
      });
      filteredRows = [...allRows];
      render();
    });

    $('reportExport')?.addEventListener('click', exportCsv);
    render();
  });
})();
