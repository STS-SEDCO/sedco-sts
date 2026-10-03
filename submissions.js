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
      case 'cancelled':
        return { label:'Cancelled', cls:'cancelled', icon:'bi-slash-circle' };
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
      case 'chairman':
        return { label:'Pengerusi', cls:'chairman', icon:'bi-award' };
      case 'finance':
        return { label:'Kewangan', cls:'finance', icon:'bi-cash-stack' };
      case 'completed':
        return { label:'Completed', cls:'completed', icon:'bi-check2-all' };
      default:
        return { label:'Not available', cls:'none', icon:'bi-dash' };
    }
  }

  function formatDate(value, includeTime = false) {
    if (!value) return 'Not available';
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
    $('submissionReviewed').textContent = submissions.filter(x => x.overdue === true).length;
    $('submissionWeek').textContent = submissions.filter(x => isThisWeek(x.submittedAt)).length;
  }

  function filteredRows() {
    const query = ($('submissionSearch')?.value || '').trim().toLowerCase();
    const stage = $('submissionStage')?.value || 'all';
    const department = $('submissionDepartment')?.value || 'all';
    const date = $('submissionDate')?.value || '';

    return submissions.filter(item => {
      const status = item.status || 'pending';
      if (filter !== 'all' && status !== filter) return false;
      if (stage !== 'all' && String(item.currentStage || '') !== stage) return false;
      if (department !== 'all' && String(item.department || '') !== department) return false;

      if (date) {
        const submitted = new Date(item.submittedAt);
        if (Number.isNaN(submitted.getTime())) return false;
        const localDate = [
          submitted.getFullYear(),
          String(submitted.getMonth()+1).padStart(2,'0'),
          String(submitted.getDate()).padStart(2,'0')
        ].join('-');
        if (localDate !== date) return false;
      }

      if (!query) return true;

      return [
        item.id,
        item.type,
        item.title,
        item.formName,
        item.applicant,
        item.department,
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
      const canRequestCorrection = ['training','hod'].includes(String(item.currentStage || '').toLowerCase());
      const action = item.canReview
        ? `<div class="submission-action-group">
             <a class="submission-action-view" href="${escapeHtml(item.reviewUrl || '#')}" title="View full form">
               <i class="bi bi-eye"></i><span>View</span>
             </a>
             <button class="submission-action-btn is-approve" type="button" data-review-action="approved" data-review-id="${escapeHtml(item.id)}">
               <i class="bi bi-check2"></i><span>Approve</span>
             </button>
             ${canRequestCorrection ? `
             <button class="submission-action-btn is-correction" type="button" data-review-action="correction" data-review-id="${escapeHtml(item.id)}">
               <i class="bi bi-arrow-counterclockwise"></i><span>Correction</span>
             </button>` : ''}
             <button class="submission-action-btn is-reject" type="button" data-review-action="rejected" data-review-id="${escapeHtml(item.id)}">
               <i class="bi bi-x-lg"></i><span>Reject</span>
             </button>
           </div>`
        : `<a class="submission-action-view" href="${escapeHtml(item.reviewUrl || '#')}">
             <i class="bi bi-eye"></i><span>View</span>
           </a>`;

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

    tbody.querySelectorAll('[data-review-action][data-review-id]').forEach(button => {
      button.addEventListener('click', () => {
        openQuickReview(button.dataset.reviewId, button.dataset.reviewAction);
      });
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

  function fieldValue(item, name) {
    const value = item?.data?.[name];
    if (Array.isArray(value)) return value[0] ?? '';
    return value ?? '';
  }

  function textField(name, label, value = '', options = {}) {
    const required = options.required !== false;
    const type = options.type || 'text';
    const placeholder = options.placeholder || '';
    const input = type === 'textarea'
      ? `<textarea name="${escapeHtml(name)}" rows="4" ${required ? 'required' : ''} placeholder="${escapeHtml(placeholder)}">${escapeHtml(value)}</textarea>`
      : `<input type="${escapeHtml(type)}" name="${escapeHtml(name)}" value="${escapeHtml(value)}" ${required ? 'required' : ''} placeholder="${escapeHtml(placeholder)}">`;

    return `<label class="quick-review-field">
      <span>${escapeHtml(label)}${required ? ' <b>*</b>' : ''}</span>
      ${input}
    </label>`;
  }

  function choiceField(name, label, value = '') {
    return `<div class="quick-review-field">
      <span>${escapeHtml(label)} <b>*</b></span>
      <div class="quick-review-choice">
        <label><input type="radio" name="${escapeHtml(name)}" value="Ya" ${String(value) === 'Ya' ? 'checked' : ''} required> Ya</label>
        <label><input type="radio" name="${escapeHtml(name)}" value="Tidak" ${String(value) === 'Tidak' ? 'checked' : ''}> Tidak</label>
      </div>
    </div>`;
  }

  function stageReviewFields(item, decision) {
    const stage = String(item.currentStage || '').toLowerCase();
    const correction = decision === 'correction';

    if (stage === 'training') {
      if (correction) {
        return textField(
          'ulasan_latihan',
          'Arahan pembetulan Seksyen Training',
          fieldValue(item, 'ulasan_latihan'),
          { type:'textarea', placeholder:'Nyatakan dengan jelas perkara yang perlu dibetulkan oleh pemohon' }
        );
      }

      return [
        textField('ulasan_latihan', 'Ulasan Seksyen Training', fieldValue(item,'ulasan_latihan'), {
          type:'textarea',
          placeholder:'Masukkan ulasan semakan'
        }),
        textField('tarikh_latihan', 'Tarikh', fieldValue(item,'tarikh_latihan'), { type:'date' }),
        textField('tt_latihan', 'Tandatangan / Nama Pegawai', fieldValue(item,'tt_latihan'))
      ].join('');
    }

    if (stage === 'hod') {
      if (correction) {
        return textField(
          'ulasan_bahagian',
          'Arahan pembetulan HOD',
          fieldValue(item, 'ulasan_bahagian'),
          { type:'textarea', placeholder:'Nyatakan dengan jelas perkara yang perlu dibetulkan oleh pemohon' }
        );
      }

      return [
        textField('ulasan_bahagian', 'Ulasan HOD', fieldValue(item,'ulasan_bahagian'), {
          type:'textarea',
          placeholder:'Masukkan ulasan semakan'
        }),
        textField('tarikh_bahagian', 'Tarikh', fieldValue(item,'tarikh_bahagian'), { type:'date' }),
        textField('tt_bahagian', 'Tandatangan / Nama HOD', fieldValue(item,'tt_bahagian'))
      ].join('');
    }

    if (stage === 'gm') {
      return [
        textField('tarikh_pgs', 'Tarikh keputusan GM', fieldValue(item,'tarikh_pgs'), { type:'date' }),
        textField('tt_pgs', 'Tandatangan / Nama GM', fieldValue(item,'tt_pgs'))
      ].join('');
    }

    if (stage === 'chairman') {
      return [
        textField('tarikh_sedco', 'Tarikh keputusan Pengerusi', fieldValue(item,'tarikh_sedco'), { type:'date' }),
        textField('tt_sedco', 'Tandatangan / Nama Pengerusi', fieldValue(item,'tt_sedco'))
      ].join('');
    }

    if (stage === 'finance') {
      return [
        textField('bayaran_kursus', 'Bayaran Kursus (RM)', fieldValue(item,'bayaran_kursus'), {
          placeholder:'Contoh: 350.00'
        }),
        choiceField('pendahuluan_diterima', 'Permohonan Pendahuluan Diterima', fieldValue(item,'pendahuluan_diterima')),
        choiceField('telah_didaftar', 'Telah Didaftarkan', fieldValue(item,'telah_didaftar'))
      ].join('');
    }

    return '<div class="quick-review-empty">No quick review fields are available for this stage.</div>';
  }

  function openQuickReview(id, decision) {
    const item = submissions.find(entry => String(entry.id) === String(id));
    if (!item || !item.canReview) return;

    const stage = stageMeta(item.currentStage);
    const decisionMeta = {
      approved: {
        label:'Approve application',
        icon:'bi-check2-circle',
        cls:'is-approve',
        hint:'After approval, this application will move to the next approval stage.'
      },
      correction: {
        label:'Request correction',
        icon:'bi-arrow-counterclockwise',
        cls:'is-correction',
        hint:'The applicant will receive your correction instructions and the application will return to this same stage after resubmission.'
      },
      rejected: {
        label:'Reject application',
        icon:'bi-x-circle',
        cls:'is-reject',
        hint:'This will end the approval workflow for this application.'
      }
    }[decision];

    if (!decisionMeta) return;

    $('quickReviewApplication').value = item.id || '';
    $('quickReviewDecision').value = decision;
    $('quickReviewRef').textContent = item.id || 'BPL Review';
    $('quickReviewTitle').textContent = item.title || 'Review application';
    $('quickReviewApplicant').textContent = item.applicant || 'Not available';
    $('quickReviewStage').textContent = stage.label;
    $('quickReviewFields').innerHTML = stageReviewFields(item, decision);
    $('quickReviewComment').value = '';
    $('quickReviewHint').textContent = decisionMeta.hint;
    $('quickReviewDecisionBadge').className = 'quick-review-decision ' + decisionMeta.cls;
    $('quickReviewDecisionBadge').innerHTML = `<i class="bi ${decisionMeta.icon}"></i><span>${decisionMeta.label}</span>`;
    $('quickReviewSubmit').className = 'quick-review-submit ' + decisionMeta.cls;
    $('quickReviewSubmit').innerHTML = `<i class="bi ${decisionMeta.icon}"></i> ${decisionMeta.label}`;
    $('quickReviewOpenForm').href = item.reviewUrl || '#';

    bootstrap.Modal.getOrCreateInstance($('quickReviewModal')).show();
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

    $('quickReviewForm')?.addEventListener('submit', () => {
      const submit = $('quickReviewSubmit');
      if (!submit) return;
      submit.disabled = true;
      submit.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Saving...';
    });

    const departmentSelect = $('submissionDepartment');
    if (departmentSelect) {
      [...new Set(submissions.map(item => item.department).filter(Boolean))]
        .sort((a,b) => String(a).localeCompare(String(b)))
        .forEach(department => {
          const option = document.createElement('option');
          option.value = department;
          option.textContent = department;
          departmentSelect.appendChild(option);
        });
    }

    $('submissionSearch')?.addEventListener('input', render);
    $('submissionStage')?.addEventListener('change', render);
    $('submissionDepartment')?.addEventListener('change', render);
    $('submissionDate')?.addEventListener('change', render);

    document.querySelectorAll('[data-submission-filter]').forEach(button => {
      button.addEventListener('click', () => setFilter(button.dataset.submissionFilter));
    });

    render();
  });
})();