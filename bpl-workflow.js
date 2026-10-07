(() => {
  const data = window.SEDCO_BPL_DATA || {};
  const context = window.SEDCO_FORM_CONTEXT || {};

  function valuesFor(name) {
    const value = data[name];
    if (Array.isArray(value)) return value.map(String);
    if (value === undefined || value === null) return [];
    return [String(value)];
  }

  function hydrate() {
    const form = document.querySelector('.bpl-page form');
    if (!form) return;

    form.querySelectorAll('input[name], textarea[name], select[name]').forEach(field => {
      if (field.type === 'hidden' || field.name === '_csrf' || field.name === 'application_no') return;

      const name = field.name.replace(/\[\]$/, '');
      const values = valuesFor(name);

      if (field.type === 'checkbox' || field.type === 'radio') {
        field.checked = values.includes(String(field.value));
        return;
      }

      if (!values.length) return;
      field.value = values[0];

      if (field.tagName === 'SELECT') {
        field.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    setupAutomaticReviewDates(form);
    setupCorrectionGuidance(form);
    setupBplQualityGuard(form);
  }

  function setupCorrectionGuidance(form) {
    const targeted = Array.isArray(data._correction_fields)
      ? data._correction_fields.map(String)
      : [];

    if (context.status === 'correction' && targeted.length) {
      let first = null;

      targeted.forEach(name => {
        const fields = [...form.querySelectorAll('[name="' + CSS.escape(name) + '"],[name="' + CSS.escape(name + '[]') + '"]')];

        fields.forEach(field => {
          const container = field.closest('tr, .bpl-applicant-field, .sts-other-field') || field.parentElement;
          if (!container) return;

          container.classList.add('sts-correction-field-target');
          container.dataset.correctionTarget = '1';

          if (!container.querySelector('.sts-correction-field-badge')) {
            const badge = document.createElement('span');
            badge.className = 'sts-correction-field-badge';
            badge.innerHTML = '<i class="bi bi-pencil-square"></i> Please correct this field';

            const badgeHost = container.tagName === 'TR'
              ? (container.querySelector('td:last-child') || container.querySelector('td'))
              : container;

            if (badgeHost) {
              badgeHost.classList.add('sts-correction-badge-host');
              badgeHost.appendChild(badge);
            }
          }

          if (!first && !field.disabled && field.type !== 'hidden') first = field;
        });
      });

      window.setTimeout(() => {
        if (!first) return;
        first.scrollIntoView({ behavior:'smooth', block:'center' });
        window.setTimeout(() => first.focus({ preventScroll:true }), 350);
      }, 420);
    }

    form.addEventListener('submit', event => {
      if (String(event.submitter?.value || '').toLowerCase() !== 'correction') return;

      const panel = form.querySelector('[data-correction-target-panel]');
      if (panel) panel.hidden = false;

      const checked = [...form.querySelectorAll('input[name="correction_fields[]"]:checked')];
      const error = form.querySelector('[data-correction-target-error]');

      if (checked.length) {
        if (error) error.hidden = true;
        return;
      }

      event.preventDefault();
      if (error) error.hidden = false;
      panel?.scrollIntoView({ behavior:'smooth', block:'center' });
    }, true);
  }

  function setupBplQualityGuard(form) {
    if (context.mode === 'review' && context.isOwner !== true) return;

    const panel = form.querySelector('[data-bpl-quality-panel]');
    const titleNode = form.querySelector('[data-bpl-quality-title]');
    const copyNode = form.querySelector('[data-bpl-quality-copy]');
    const messagesNode = form.querySelector('[data-bpl-quality-messages]');
    const conflictsNode = form.querySelector('[data-bpl-conflict-list]');
    const titleField = form.querySelector('[name="tajuk"]');
    const startField = form.querySelector('[name="tarikh_mula"]');
    const endField = form.querySelector('[name="tarikh_tamat"]');
    const feeField = form.querySelector('[name="yuran"]');

    if (!panel || !messagesNode || !conflictsNode || !titleField || !startField || !endField || !feeField) return;

    let timer = null;
    let controller = null;

    const escapeHtml = value => String(value ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');

    const validDate = value => /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''));

    const localCheck = () => {
      const errors = [];
      const warnings = [];
      const start = String(startField.value || '');
      const end = String(endField.value || '');
      const feeRaw = String(feeField.value || '').trim();
      const cleanedFee = feeRaw.replace(/[^0-9.]/g,'');

      if (start && !validDate(start)) errors.push('Training start date is invalid.');
      if (end && !validDate(end)) errors.push('Training end date is invalid.');
      if (validDate(start) && validDate(end) && end < start) {
        errors.push('Training end date cannot be earlier than the start date.');
      }

      if (feeRaw) {
        const fee = Number(cleanedFee);
        if (!cleanedFee || !Number.isFinite(fee)) {
          errors.push('Course fee must be a valid number.');
        } else if (fee < 0 || fee > 1000000) {
          errors.push('Course fee is outside the allowed range.');
        }
      }

      return {errors,warnings};
    };

    const render = (result = {}) => {
      const errors = Array.isArray(result.errors) ? result.errors : [];
      const warnings = Array.isArray(result.warnings) ? result.warnings : [];
      const conflicts = Array.isArray(result.conflicts) ? result.conflicts : [];
      const hasAnything = errors.length || warnings.length || conflicts.length;
      panel.hidden = !hasAnything;

      if (!hasAnything) {
        panel.className = 'bpl-quality-panel no-print';
        messagesNode.innerHTML = '';
        conflictsNode.innerHTML = '';
        return;
      }

      const state = errors.length ? 'is-error' : 'is-warning';
      panel.className = 'bpl-quality-panel no-print ' + state;
      if (titleNode) titleNode.textContent = errors.length ? 'Please fix before submitting' : 'Schedule check';
      if (copyNode) {
        copyNode.textContent = errors.length
          ? 'The form contains data that cannot be submitted yet.'
          : 'Review the possible conflict below before continuing.';
      }

      messagesNode.innerHTML = [...errors.map(message => ({type:'error',message})), ...warnings.map(message => ({type:'warning',message}))]
        .map(item => `
          <div class="bpl-quality-message is-${item.type}">
            <i class="bi ${item.type === 'error' ? 'bi-x-octagon' : 'bi-exclamation-triangle'}"></i>
            <span>${escapeHtml(item.message)}</span>
          </div>
        `).join('');

      conflictsNode.innerHTML = conflicts.map(item => `
        <article class="bpl-conflict-item">
          <span class="bpl-conflict-icon"><i class="bi bi-calendar2-x"></i></span>
          <div>
            <strong>${escapeHtml(item.title || 'Existing training')}</strong>
            <p>${escapeHtml(item.application_no || '')} · ${escapeHtml(item.start || '')} → ${escapeHtml(item.end || '')}</p>
          </div>
          ${item.similar_title ? '<em>Similar course</em>' : '<em>Date overlap</em>'}
        </article>
      `).join('');
    };

    const run = async () => {
      const local = localCheck();

      if (local.errors.length) {
        render(local);
        return;
      }

      const title = String(titleField.value || '').trim();
      const start = String(startField.value || '');
      const end = String(endField.value || '');

      if (!title || !validDate(start) || !validDate(end)) {
        render(local);
        return;
      }

      if (!location.pathname.toLowerCase().endsWith('.php')) {
        const previewRows = (() => {
          try {
            const rows = JSON.parse(localStorage.getItem('sedcoApplications') || '[]');
            return Array.isArray(rows) ? rows : [];
          } catch { return []; }
        })();

        const previewUser = (() => {
          try { return JSON.parse(localStorage.getItem('sedcoPreviewUser') || 'null'); }
          catch { return null; }
        })();

        const email = String(previewUser?.email || '').toLowerCase();
        const conflicts = previewRows
          .filter(row => String(row.type || row.form_type || '').toUpperCase() === 'BPL')
          .filter(row => !['rejected','cancelled'].includes(String(row.status || '').toLowerCase()))
          .filter(row => !email || String(row.ownerEmail || '').toLowerCase() === email)
          .map(row => ({
            application_no:row.id || row.application_no || '',
            title:row.title || row.data?.tajuk || row.data?.kursus || 'Existing training',
            start:row.trainingStart || row.training_start || row.data?.tarikh_mula || '',
            end:row.trainingEnd || row.training_end || row.data?.tarikh_tamat || ''
          }))
          .filter(row => row.start && row.end && row.start <= end && row.end >= start);

        render({
          errors:local.errors,
          warnings:conflicts.length ? ['Another training record overlaps with these dates. Confirm the schedule before submitting.'] : [],
          conflicts
        });
        return;
      }

      controller?.abort();
      controller = new AbortController();
      const applicationNo = form.querySelector('[name="application_no"]')?.value || '';
      const params = new URLSearchParams({title,start,end});
      if (applicationNo) params.set('exclude', applicationNo);

      try {
        const response = await fetch('bpl-quality.php?' + params.toString(), {
          credentials:'same-origin',
          cache:'no-store',
          signal:controller.signal
        });

        if (!response.ok) {
          render(local);
          return;
        }

        const result = await response.json();
        render({
          errors:[...local.errors, ...(Array.isArray(result.errors) ? result.errors : [])],
          warnings:[...local.warnings, ...(Array.isArray(result.warnings) ? result.warnings : [])],
          conflicts:Array.isArray(result.conflicts) ? result.conflicts : []
        });
      } catch (error) {
        if (error?.name !== 'AbortError') render(local);
      }
    };

    const schedule = () => {
      clearTimeout(timer);
      timer = window.setTimeout(run, 320);
    };

    [titleField,startField,endField,feeField].forEach(field => {
      field.addEventListener('input', schedule);
      field.addEventListener('change', schedule);
    });

    form.addEventListener('submit', event => {
      const local = localCheck();
      if (!local.errors.length) return;

      event.preventDefault();
      render({errors:local.errors,warnings:[],conflicts:[]});

      const firstInvalid = local.errors.some(message => message.toLowerCase().includes('end date'))
        ? endField
        : (local.errors.some(message => message.toLowerCase().includes('fee')) ? feeField : startField);

      firstInvalid?.scrollIntoView({behavior:'smooth',block:'center'});
      firstInvalid?.focus({preventScroll:true});
    }, true);

    schedule();
  }

  function malaysiaDate() {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Kuala_Lumpur',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    }).formatToParts(new Date());
    const map = Object.fromEntries(parts.map(part => [part.type, part.value]));
    return map.year + '-' + map.month + '-' + map.day;
  }

  function setupAutomaticReviewDates(form) {
    const dateByStage = {
      training: 'tarikh_latihan',
      hod: 'tarikh_bahagian',
      gm: 'tarikh_pgs',
      chairman: 'tarikh_sedco'
    };

    const activeDateName = dateByStage[String(context.currentStage || '').toLowerCase()];
    const isActiveReview = context.mode === 'review' && context.status === 'pending';

    if (isActiveReview && activeDateName) {
      const field = form.querySelector('[name="' + activeDateName + '"]');
      if (field) {
        field.readOnly = true;
        field.value = malaysiaDate();
      }
    }

    const registrationDate = form.querySelector('[name="tarikh_didaftar"]');
    const registrationChoices = [...form.querySelectorAll('[name="telah_didaftar"]')];

    const syncRegistrationDate = () => {
      if (!registrationDate) return;
      const selected = registrationChoices.find(field => field.checked)?.value || '';

      if (
        isActiveReview
        && String(context.currentStage || '').toLowerCase() === 'finance'
        && selected === 'Ya'
      ) {
        registrationDate.value = malaysiaDate();
      } else if (
        isActiveReview
        && String(context.currentStage || '').toLowerCase() === 'finance'
        && selected !== 'Ya'
      ) {
        registrationDate.value = '';
      }
    };

    registrationChoices.forEach(field => field.addEventListener('change', syncRegistrationDate));
    syncRegistrationDate();

    form.addEventListener('submit', event => {
      if (event.submitter?.classList.contains('form-print-button')) return;

      if (isActiveReview && activeDateName) {
        const field = form.querySelector('[name="' + activeDateName + '"]');
        if (field) field.value = malaysiaDate();
      }

      syncRegistrationDate();
    }, true);
  }

  document.addEventListener('DOMContentLoaded', hydrate);
})();