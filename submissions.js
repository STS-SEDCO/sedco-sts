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

    if (location.pathname.toLowerCase().endsWith('.html')) {
      let previewUser = null;
      try {
        previewUser = JSON.parse(localStorage.getItem('sedcoPreviewUser') || 'null');
      } catch {}

      const role = String(previewUser?.role || '').toLowerCase();
      const department = String(previewUser?.department || '').trim();

      const roleStage = {
        training_section:'training',
        head_of_department:'hod',
        general_manager:'gm',
        pengerusi_besar:'chairman',
        finance:'finance'
      };

      submissions = submissions
        .filter(item => String(item.type || item.form_type || '').toUpperCase() === 'BPL')
        .map(item => {
          const currentStage = String(item.currentStage || item.current_stage || 'training').toLowerCase();
          const itemDepartment = String(item.department || item.data?.bahagian || '').trim();
          const stageForRole = roleStage[role] || currentStage;
          const roleCanReview = role === 'admin'
            || (
              currentStage === stageForRole
              && (role !== 'head_of_department' || !department || !itemDepartment || department === itemDepartment)
            );

          return {
            ...item,
            type:'BPL',
            formName:item.formName || 'Permohonan Latihan',
            currentStage,
            department:itemDepartment || 'Unassigned',
            stageLabel:stageMeta(currentStage).label,
            canReview:String(item.status || 'pending').toLowerCase() === 'pending' && roleCanReview,
            reviewUrl:item.reviewUrl || 'bpl.html'
          };
        })
        .filter(item => role === 'admin' || item.canReview);
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
             <button class="submission-action-view" type="button" data-submission-id="${escapeHtml(item.id)}" title="View application details">
               <i class="bi bi-eye"></i><span>View</span>
             </button>
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
        : `<button class="submission-action-view" type="button" data-submission-id="${escapeHtml(item.id)}">
             <i class="bi bi-eye"></i><span>View</span>
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

    tbody.querySelectorAll('[data-review-action][data-review-id]').forEach(button => {
      button.addEventListener('click', () => {
        openQuickReview(button.dataset.reviewId, button.dataset.reviewAction);
      });
    });
  }

  function humanize(key) {
    return String(key || '').replaceAll('_',' ').replace(/\b\w/g, c => c.toUpperCase());
  }

  const DETAIL_LABELS = {
    nama:'Nama',
    bahagian:'Bahagian',
    jawatan:'Jawatan',
    kursus:'Kursus / Seminar',
    tarikh:'Tarikh Permohonan',
    tajuk:'Tajuk Kursus / Seminar',
    penganjur:'Penganjur',
    tarikh_mula:'Tarikh Mula',
    tarikh_tamat:'Tarikh Tamat',
    tempat:'Tempat',
    yuran:'Yuran Kursus',
    kandungan:'Kandungan Kursus',
    tempat_tugas:'Tempat Bertugas',
    kenderaan:'Kenderaan',
    kenderaan_other:'Kenderaan Lain',
    masa_bertolak:'Masa Bertolak',
    masa_kembali:'Masa Kembali',
    pendahuluan:'Pendahuluan',
    ulasan_latihan:'Ulasan Seksyen Training',
    tarikh_latihan:'Tarikh Semakan Training',
    tt_latihan:'Pengesahan Training',
    ulasan_bahagian:'Ulasan HOD',
    tarikh_bahagian:'Tarikh Semakan HOD',
    tt_bahagian:'Pengesahan HOD',
    kelulusan_pgs:'Keputusan GM',
    tarikh_pgs:'Tarikh Keputusan GM',
    tt_pgs:'Pengesahan GM',
    kelulusan_sedco:'Keputusan Pengerusi',
    tarikh_sedco:'Tarikh Keputusan Pengerusi',
    tt_sedco:'Pengesahan Pengerusi',
    bayaran_kursus:'Bayaran Kursus',
    pendahuluan_diterima:'Pendahuluan Diterima',
    telah_didaftar:'Status Pendaftaran'
  };

  function detailLabel(key) {
    return DETAIL_LABELS[key] || humanize(key);
  }

  function detailValue(key, value) {
    if (Array.isArray(value)) return value.join(', ');
    if (value === null || value === undefined || String(value).trim() === '') return 'Not provided';

    if (/^tarikh(_|$)/i.test(key) || ['tarikh_mula','tarikh_tamat'].includes(key)) {
      const parsed = new Date(String(value) + (String(value).length === 10 ? 'T00:00:00' : ''));
      if (!Number.isNaN(parsed.getTime())) {
        return new Intl.DateTimeFormat('en-MY', {
          day:'2-digit',
          month:'short',
          year:'numeric'
        }).format(parsed);
      }
    }

    if (key === 'yuran' || key === 'bayaran_kursus') {
      const number = Number(value);
      if (!Number.isNaN(number)) {
        return new Intl.NumberFormat('en-MY', {
          style:'currency',
          currency:'MYR',
          minimumFractionDigits:2
        }).format(number);
      }
    }

    return String(value);
  }

  function isReviewField(key) {
    return [
      'ulasan_latihan','tarikh_latihan','tt_latihan',
      'ulasan_bahagian','tarikh_bahagian','tt_bahagian',
      'kelulusan_pgs','tarikh_pgs','tt_pgs',
      'kelulusan_sedco','tarikh_sedco','tt_sedco',
      'bayaran_kursus','pendahuluan_diterima','telah_didaftar'
    ].includes(key);
  }

  function openDetails(id) {
    const item = submissions.find(x => String(x.id) === String(id));
    if (!item) return;

    const meta = statusMeta(item.status);
    const stage = stageMeta(item.currentStage);

    $('submissionModal').dataset.currentSubmissionId = item.id || '';
    $('submissionModalRef').textContent = item.id || 'Submission';
    $('submissionModalTitle').textContent = item.title || item.formName || 'Submission details';
    $('submissionModalStatus').className = `status-pill status-${meta.cls}`;
    $('submissionModalStatus').innerHTML = `<i class="bi ${meta.icon}"></i>${meta.label}`;

    $('submissionModalStageBadge').className = `submission-stage-pill stage-${stage.cls}`;
    $('submissionModalStageBadge').innerHTML = `<i class="bi ${stage.icon}"></i><span id="submissionModalStage">${escapeHtml(stage.label)}</span>`;

    $('submissionModalApplicant').textContent = item.applicant || 'Not available';
    $('submissionModalType').textContent = item.formName || item.type || 'Not available';
    $('submissionModalDate').textContent = formatDate(item.submittedAt, true);
    $('submissionModalDepartment').textContent = item.department || 'Not assigned';
    const isStaticPreview = location.pathname.toLowerCase().endsWith('.html');
    const fallbackForm = String(item.type || '').toUpperCase() === 'BPL'
      ? (isStaticPreview ? 'bpl.html' : 'bpl.php')
      : '#';
    $('submissionModalOpenForm').href = item.reviewUrl || fallbackForm;

    const entries = Object.entries(item.data || {}).filter(([key,value]) => {
      if (isReviewField(key)) return false;
      if (Array.isArray(value)) return value.some(v => String(v || '').trim());
      return String(value || '').trim() !== '';
    });

    $('submissionModalFields').innerHTML = entries.length
      ? entries.map(([key,value]) => `
          <div class="submission-detail-field">
            <span>${escapeHtml(detailLabel(key))}</span>
            <strong>${escapeHtml(detailValue(key, value))}</strong>
          </div>
        `).join('')
      : '<div class="submission-detail-empty">No application information is available.</div>';

    const actions = $('submissionModalActions');
    if (actions) {
      actions.hidden = !item.canReview;
      actions.querySelectorAll('[data-detail-review-action]').forEach(button => {
        const action = button.dataset.detailReviewAction;
        button.hidden = action === 'correction'
          && !['training','hod'].includes(String(item.currentStage || '').toLowerCase());
      });
    }

    bootstrap.Modal.getOrCreateInstance($('submissionModal')).show();
  }

  function openReviewFromDetails(decision) {
    const modal = $('submissionModal');
    const id = modal?.dataset.currentSubmissionId || '';
    if (!id) return;

    const detailsInstance = bootstrap.Modal.getOrCreateInstance(modal);
    detailsInstance.hide();

    const launch = () => {
      modal.removeEventListener('hidden.bs.modal', launch);
      openQuickReview(id, decision);
    };

    modal.addEventListener('hidden.bs.modal', launch);
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

    $('quickReviewForm')?.addEventListener('submit', event => {
      const isStaticPreview = location.pathname.toLowerCase().endsWith('.html');
      const submit = $('quickReviewSubmit');
      if (!submit) return;

      if (isStaticPreview) {
        event.preventDefault();
        const decision = $('quickReviewDecision')?.value || 'approved';
        const id = $('quickReviewApplication')?.value || '';
        const item = submissions.find(entry => String(entry.id) === String(id));

        if (item) {
          item.status = decision === 'approved'
            ? 'approved'
            : (decision === 'correction' ? 'correction' : 'rejected');
          try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(submissions));
          } catch {}
          bootstrap.Modal.getOrCreateInstance($('quickReviewModal')).hide();
          render();
        }
        return;
      }

      submit.disabled = true;
      submit.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Saving...';
    });

    document.querySelectorAll('[data-detail-review-action]').forEach(button => {
      button.addEventListener('click', () => {
        openReviewFromDetails(button.dataset.detailReviewAction);
      });
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