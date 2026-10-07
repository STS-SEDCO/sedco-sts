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

  function normalizeAnalytics(data) {
    return {
      statusCounts:{
        pending:Number(data?.statusCounts?.pending || 0),
        approved:Number(data?.statusCounts?.approved || 0),
        correction:Number(data?.statusCounts?.correction || 0),
        rejected:Number(data?.statusCounts?.rejected || 0),
        cancelled:Number(data?.statusCounts?.cancelled || 0)
      },
      typeCounts:{
        BPL:Number(data?.typeCounts?.BPL || 0),
        PKK:Number(data?.typeCounts?.PKK || 0),
        TEA:Number(data?.typeCounts?.TEA || 0)
      },
      deptCounts:data?.deptCounts && typeof data.deptCounts === 'object' ? data.deptCounts : {},
      monthCounts:data?.monthCounts && typeof data.monthCounts === 'object' ? data.monthCounts : {}
    };
  }

  function renderStatusDonut(counts) {
    const target = $('reportStatusDonut');
    if (!target) return;

    const items = [
      ['Pending',Number(counts.pending || 0),'#c58a2c'],
      ['Approved',Number(counts.approved || 0),'#8f1010'],
      ['Correction',Number(counts.correction || 0),'#d56868'],
      ['Rejected',Number(counts.rejected || 0),'#52545a'],
      ['Cancelled',Number(counts.cancelled || 0),'#b7b8bd']
    ];
    const total = items.reduce((sum,item)=>sum+item[1],0);
    const radius = 42;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;

    const segments = items.map(([label,value,color]) => {
      const length = total ? (value / total) * circumference : 0;
      const circle = value > 0
        ? `<circle cx="60" cy="60" r="${radius}" fill="none" stroke="${color}" stroke-width="14" stroke-linecap="butt" stroke-dasharray="${length.toFixed(2)} ${(circumference-length).toFixed(2)}" stroke-dashoffset="${(-offset).toFixed(2)}" transform="rotate(-90 60 60)"></circle>`
        : '';
      offset += length;
      return circle;
    }).join('');

    const legend = items.map(([label,value,color]) => `
      <div class="report-donut-legend-row">
        <span><i style="background:${color}"></i>${escapeHtml(label)}</span>
        <strong>${value}</strong>
      </div>
    `).join('');

    target.innerHTML = `
      <div class="report-donut-layout">
        <div class="report-donut-visual">
          <svg viewBox="0 0 120 120" role="img" aria-label="Status distribution">
            <circle cx="60" cy="60" r="${radius}" fill="none" stroke="#f0eaea" stroke-width="14"></circle>
            ${segments}
          </svg>
          <div class="report-donut-center"><strong>${total}</strong><span>Total</span></div>
        </div>
        <div class="report-donut-legend">${legend}</div>
      </div>
    `;
  }

  function renderFormsChart(counts) {
    const target = $('reportFormsChart');
    if (!target) return;

    const items = [
      ['BPL',Number(counts.BPL || 0)],
      ['PKK',Number(counts.PKK || 0)],
      ['TEA',Number(counts.TEA || 0)]
    ];
    const max = Math.max(1,...items.map(item=>item[1]));

    target.innerHTML = `
      <div class="report-form-chart">
        ${items.map(([label,value]) => {
          const height = Math.max(value ? 14 : 3, Math.round((value/max)*100));
          return `
            <div class="report-form-column">
              <strong>${value}</strong>
              <div class="report-form-bar-track"><i style="height:${height}%"></i></div>
              <span>${label}</span>
            </div>
          `;
        }).join('')}
      </div>
      <div class="report-chart-caption">
        <i class="bi bi-info-circle"></i>
        <span>Higher bars indicate stronger submission demand.</span>
      </div>
    `;
  }

  function latestSixMonthSeries(monthCounts) {
    const clean = Object.fromEntries(
      Object.entries(monthCounts || {})
        .filter(([key]) => /^\d{4}-\d{2}$/.test(key))
        .map(([key,value]) => [key,Number(value || 0)])
    );
    const keys = Object.keys(clean).sort();
    const anchor = keys.length
      ? new Date(Number(keys[keys.length-1].slice(0,4)),Number(keys[keys.length-1].slice(5,7))-1,1)
      : new Date(new Date().getFullYear(),new Date().getMonth(),1);

    const series=[];
    for(let index=5; index>=0; index--){
      const date=new Date(anchor.getFullYear(),anchor.getMonth()-index,1);
      const key=date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0');
      series.push({
        key,
        label:date.toLocaleString('en-MY',{month:'short'}),
        value:Number(clean[key] || 0)
      });
    }
    return series;
  }

  function renderMonthlyTrend(monthCounts) {
    const target = $('reportMonthlyTrend');
    if (!target) return;

    const series = latestSixMonthSeries(monthCounts);
    const width=720;
    const height=230;
    const left=38;
    const right=18;
    const top=20;
    const bottom=42;
    const plotWidth=width-left-right;
    const plotHeight=height-top-bottom;
    const max=Math.max(1,...series.map(item=>item.value));
    const baseline=top+plotHeight;

    const points=series.map((item,index)=>{
      const x=left+(plotWidth*(index/(series.length-1)));
      const y=top+plotHeight-(item.value/max)*plotHeight;
      return {...item,x,y};
    });

    const polyline=points.map(point=>point.x.toFixed(1)+','+point.y.toFixed(1)).join(' ');
    const area=left+','+baseline+' '+polyline+' '+(left+plotWidth)+','+baseline;
    const grid=[0,.25,.5,.75,1].map(ratio=>{
      const y=top+plotHeight-(plotHeight*ratio);
      return `<line x1="${left}" y1="${y}" x2="${width-right}" y2="${y}" class="report-trend-gridline"></line>`;
    }).join('');

    const labels=points.map(point=>`
      <text x="${point.x}" y="${height-13}" text-anchor="middle" class="report-trend-label">${escapeHtml(point.label)}</text>
    `).join('');

    const dots=points.map(point=>`
      <circle cx="${point.x}" cy="${point.y}" r="4.5" class="report-trend-dot"></circle>
      <text x="${point.x}" y="${Math.max(12,point.y-10)}" text-anchor="middle" class="report-trend-value">${point.value}</text>
    `).join('');

    target.innerHTML = `
      <svg class="report-trend-svg" viewBox="0 0 ${width} ${height}" role="img" aria-label="Monthly submission trend">
        <defs>
          <linearGradient id="reportTrendArea" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#8f1010" stop-opacity=".22"></stop>
            <stop offset="100%" stop-color="#8f1010" stop-opacity=".02"></stop>
          </linearGradient>
        </defs>
        ${grid}
        <polygon points="${area}" fill="url(#reportTrendArea)"></polygon>
        <polyline points="${polyline}" class="report-trend-line"></polyline>
        ${dots}
        ${labels}
      </svg>
    `;
  }

  function renderDepartmentChart(counts) {
    const target = $('reportDepartmentChart');
    if (!target) return;

    const items = Object.entries(counts || {})
      .map(([label,value]) => [label,Number(value || 0)])
      .sort((a,b)=>b[1]-a[1])
      .slice(0,6);
    const max=Math.max(1,...items.map(item=>item[1]));

    target.innerHTML = items.length
      ? `<div class="report-department-bars">${items.map(([label,value],index)=>{
          const width=Math.max(4,Math.round((value/max)*100));
          return `
            <div class="report-department-row">
              <div><span>${escapeHtml(label)}</span><strong>${value}</strong></div>
              <i><b style="width:${width}%"></b></i>
            </div>
          `;
        }).join('')}</div>`
      : '<div class="report-chart-empty"><i class="bi bi-bar-chart"></i><strong>No department data yet</strong><span>Department demand will appear here once applications are submitted.</span></div>';
  }

  function renderGraphicAnalytics(data) {
    const analytics=normalizeAnalytics(data);
    renderStatusDonut(analytics.statusCounts);
    renderFormsChart(analytics.typeCounts);
    renderMonthlyTrend(analytics.monthCounts);
    renderDepartmentChart(analytics.deptCounts);
  }

  function renderSnapshot() {
    const now = new Date();
    const monthKey = now.toISOString().slice(0,7);
    const scoped = allRows;

    const bpl = scoped.filter(row => typeOf(row) === 'BPL');
    const bplThisMonth = bpl.filter(row => dateOnly(submittedOf(row)).slice(0,7) === monthKey).length;
    const followupsThisMonth = scoped.filter(row =>
      ['PKK','TEA'].includes(typeOf(row))
      && dateOnly(submittedOf(row)).slice(0,7) === monthKey
    ).length;

    const overdue = scoped.filter(row => {
      const status = statusOf(row);
      const due = row.slaDueAt || row.sla_due_at || '';
      if (status !== 'pending' || !due) return false;
      const ts = new Date(due).getTime();
      return Number.isFinite(ts) && ts < Date.now();
    }).length;

    const eligible = bpl.filter(row => {
      const end = row.trainingEnd || row.training_end || row.data?.tarikh_tamat || '';
      const endTs = new Date(String(end) + 'T23:59:59').getTime();
      return statusOf(row) === 'approved' && Number.isFinite(endTs) && endTs <= Date.now();
    });

    let onTime = 0;
    eligible.forEach(parent => {
      const parentId = String(parent.id || parent.application_no || '');
      const end = parent.trainingEnd || parent.training_end || parent.data?.tarikh_tamat || '';
      const dueTs = new Date(String(end) + 'T23:59:59').getTime() + (7 * 86400000);

      const match = scoped.find(row => {
        if (typeOf(row) !== 'PKK') return false;
        const linked = String(
          row.parentApplicationId
          || row.parent_application_id
          || row.data?.parent_application_id
          || ''
        );
        if (!linked || linked !== parentId) return false;
        const submittedTs = new Date(submittedOf(row)).getTime();
        return statusOf(row) === 'approved'
          && Number.isFinite(submittedTs)
          && submittedTs <= dueTs;
      });

      if (match) onTime++;
    });

    const compliance = eligible.length ? Math.round((onTime / eligible.length) * 1000) / 10 : 0;
    const monthLabel = now.toLocaleString('en-MY',{month:'long',year:'numeric'});

    const setText = (id,value) => {
      const node = $(id);
      if (node) node.textContent = String(value);
    };

    setText('reportSnapshotMonth', monthLabel);
    setText('reportMonthBpl', bplThisMonth);
    setText('reportPkkCompliance', compliance + '%');
    setText('reportPkkComplianceNote', 'PKK within 7 days · ' + onTime + '/' + eligible.length);
    setText('reportOverdue', overdue);
    setText('reportMonthFollowups', followupsThisMonth);

    $('reportPkkComplianceCard')?.classList.toggle('is-warning', eligible.length > 0 && compliance < 80);
    $('reportOverdueCard')?.classList.toggle('is-danger', overdue > 0);
  }

  function render() {
    renderSnapshot();
    const total = filteredRows.length;
    const statusCounts = { pending:0, approved:0, correction:0, rejected:0, cancelled:0 };
    const typeCounts = { BPL:0, PKK:0, TEA:0 };
    const deptCounts = {};
    const monthCounts = {};
    const processing = [];
    let fees = 0;

    filteredRows.forEach(row => {
      const status = statusOf(row);
      const type = typeOf(row);
      statusCounts[status] = (statusCounts[status] || 0) + 1;
      typeCounts[type] = (typeCounts[type] || 0) + 1;

      const dept = departmentOf(row);
      deptCounts[dept] = (deptCounts[dept] || 0) + 1;
      const monthKey = dateOnly(submittedOf(row)).slice(0,7);
      if (/^\\d{4}-\\d{2}$/.test(monthKey)) monthCounts[monthKey] = (monthCounts[monthKey] || 0) + 1;
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
    $('reportAvgProcessing').textContent = avg === null ? 'No data yet' : avg + ' d';
    $('reportFees').textContent = 'RM ' + fees.toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});
    $('reportRecordCount').textContent = total + (total === 1 ? ' result' : ' results');

    renderGraphicAnalytics({
      statusCounts,
      typeCounts,
      deptCounts,
      monthCounts
    });

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
    if (window.STS_REPORT_SERVER_ANALYTICS) {
      renderGraphicAnalytics(window.STS_REPORT_SERVER_ANALYTICS);
      return;
    }

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
