(() => {
  const context = window.SEDCO_FORM_CONTEXT || {};
  const formType = String(context.formType || '').toUpperCase();
  const mode = String(context.mode || 'new').toLowerCase();
  const currentStage = String(context.currentStage || '').toLowerCase();
  const status = String(context.status || 'pending').toLowerCase();

  const form = document.querySelector('form');
  if (!form || !['BPL','PKK','TEA'].includes(formType)) return;

  form.noValidate = false;
  form.classList.add('sts-smart-form');

  let invalidScrollScheduled = false;

  const fieldByName = name =>
    form.querySelector(`[name="${CSS.escape(name)}"]`);

  const fieldsByName = name =>
    [...form.querySelectorAll(`[name="${CSS.escape(name)}"]`)];

  function visibleEnabled(control) {
    return !!control && !control.disabled && control.type !== 'hidden' && control.offsetParent !== null;
  }

  function fieldLabel(control) {
    if (!control) return 'This field';

    const explicit = control.id
      ? document.querySelector(`label[for="${CSS.escape(control.id)}"]`)
      : null;

    if (explicit) return explicit.textContent.trim();

    const wrappingLabel = control.closest('label');
    if (wrappingLabel) {
      const clone = wrappingLabel.cloneNode(true);
      clone.querySelectorAll('input,textarea,select,small').forEach(node => node.remove());
      const text = clone.textContent.trim();
      if (text) return text;
    }

    const cell = control.closest('td');
    if (cell) {
      const row = cell.closest('tr');
      if (row) {
        const cells = [...row.children];
        const index = cells.indexOf(cell);
        const previous = index > 0 ? cells[index - 1] : null;
        if (previous) {
          const text = previous.textContent.trim();
          if (text) return text.replace(/^\d+[.)]?\s*/, '');
        }
      }
    }

    return control.name
      ? control.name.replace(/\[\]$/, '').replaceAll('_',' ').replace(/\b\w/g, x => x.toUpperCase())
      : 'This field';
  }

  function errorHost(control) {
    if (!control) return null;
    if (control.closest('.sts-other-field')) return control.closest('.sts-other-field');
    if (control.closest('.form-check')) return control.closest('.form-check');
    return control.parentElement || control;
  }

  function removeErrorMessage(host) {
    host?.querySelector(':scope > .sts-field-error')?.remove();
  }

  function clearFieldError(control) {
    if (!control) return;
    control.classList.remove('sts-invalid');
    control.removeAttribute('aria-invalid');
    const host = errorHost(control);
    host?.classList.remove('sts-invalid-host');
    removeErrorMessage(host);
  }

  function setFieldError(control, message) {
    if (!control) return;

    clearFieldError(control);
    control.classList.add('sts-invalid');
    control.setAttribute('aria-invalid','true');

    const host = errorHost(control);
    if (!host) return;

    host.classList.add('sts-invalid-host');
    const note = document.createElement('div');
    note.className = 'sts-field-error';
    note.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i><span></span>';
    note.querySelector('span').textContent = message;
    host.appendChild(note);
  }

  function clearGroupError(controls) {
    const first = controls.find(visibleEnabled) || controls[0];
    const host = first?.closest('td, .mb-3, .row, .sts-choice-host') || first?.parentElement;
    host?.classList.remove('sts-choice-error');
    host?.querySelector(':scope > .sts-field-error')?.remove();
    controls.forEach(control => {
      control.classList.remove('sts-invalid');
      control.removeAttribute('aria-invalid');
    });
  }

  function setGroupError(controls, message) {
    clearGroupError(controls);
    const first = controls.find(visibleEnabled) || controls[0];
    if (!first) return;

    const host = first.closest('td, .mb-3, .row, .sts-choice-host') || first.parentElement;
    host?.classList.add('sts-choice-error');

    controls.forEach(control => {
      control.classList.add('sts-invalid');
      control.setAttribute('aria-invalid','true');
    });

    if (host && !host.querySelector(':scope > .sts-field-error')) {
      const note = document.createElement('div');
      note.className = 'sts-field-error';
      note.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i><span></span>';
      note.querySelector('span').textContent = message;
      host.appendChild(note);
    }
  }

  function requiredNames() {
    if (formType === 'BPL') {
      if (mode === 'review' && status === 'pending') {
        if (currentStage === 'hod') {
          return ['ulasan_bahagian','tarikh_bahagian','tt_bahagian'];
        }
        if (currentStage === 'training') {
          return ['ulasan_latihan','tarikh_latihan','tt_latihan','bayaran_kursus'];
        }
        if (currentStage === 'gm') {
          return ['tarikh_pgs','tt_pgs'];
        }
      }

      return [
        'nama','bahagian','jawatan','kursus','tarikh',
        'tajuk','penganjur','tarikh_mula','tarikh_tamat',
        'tempat','yuran','kandungan'
      ];
    }

    if (formType === 'PKK') {
      return [
        'nama','bahagian','jawatan','tajuk','tarikh','tempat',
        'objektif',
        'perkara1','perkara2','perkara3','perkara4','perkara5',
        'cadangan1','cadangan2','cadangan3',
        'p1',
        'aspect0_p1','aspect1_p1','aspect2_p1','aspect3_p1','aspect4_p1',
        'tandatangan','tarikh_penilaian'
      ];
    }

    return ['employee_name','division','head_division','date','signature'];
  }

  function requiredGroups() {
    if (formType === 'BPL' && mode === 'review' && status === 'pending') {
      if (currentStage === 'training') {
        return ['pendahuluan_diterima','telah_didaftar'];
      }
      if (currentStage === 'gm') {
        return ['kelulusan_pgs'];
      }
    }

    if (formType === 'TEA') return ['month'];

    return [];
  }


  function applyNativeGroupRequirements() {
    requiredGroups().forEach(name => {
      const controls = fieldsByName(name).filter(control => !control.disabled);

      controls.forEach(control => {
        control.required = false;
      });

      if (controls[0]) {
        controls[0].required = true;
        controls[0].setAttribute('aria-required','true');
      }
    });
  }

  function makeRequiredMarkers() {
    requiredNames().forEach(name => {
      const control = fieldByName(name);
      if (!control || control.disabled) return;

      control.dataset.stsRequired = '1';
      control.required = true;
      control.setAttribute('aria-required','true');

      const wrapperLabel = control.closest('label.form-label, label:not(.profile-photo-upload)');
      if (wrapperLabel && !wrapperLabel.querySelector('.sts-required-mark')) {
        const mark = document.createElement('span');
        mark.className = 'sts-required-mark';
        mark.textContent = ' *';
        wrapperLabel.appendChild(mark);
      }
    });
  }

  function enhanceWritingFields() {
    const placeholders = {
      BPL: {
        nama: 'Nama penuh pemohon',
        bahagian: 'Contoh: Bahagian Sumber Manusia',
        jawatan: 'Jawatan pemohon',
        kursus: 'Nama kursus / seminar',
        tajuk: 'Tajuk latihan',
        penganjur: 'Nama penganjur',
        tempat: 'Lokasi kursus / seminar',
        yuran: 'Contoh: 350.00',
        kandungan: 'Ringkaskan kandungan utama kursus / seminar...',
        tempat_tugas: 'Lokasi tugas luar daerah',
        kenderaan_other: 'Contoh: Grab, teksi, bas...',
        ulasan_latihan: 'Tulis ulasan Training Department...',
        ulasan_bahagian: 'Tulis ulasan Head of Department...',
        ulasan_pengurus: 'Tulis ulasan pengurus...',
        ulasan_kewangan: 'Tulis ulasan kewangan...'
      },
      PKK: {
        nama: 'Nama penuh pegawai / staf',
        bahagian: 'Bahagian / divisyen',
        jawatan: 'Jawatan',
        tajuk: 'Tajuk kursus / seminar',
        tempat: 'Tempat kursus / seminar',
        objektif: 'Terangkan objektif anda menghadiri kursus...',
        perkara1: 'Perkara pertama yang dipelajari...',
        perkara2: 'Perkara kedua yang dipelajari...',
        perkara3: 'Perkara ketiga yang dipelajari...',
        perkara4: 'Perkara keempat yang dipelajari...',
        perkara5: 'Perkara kelima yang dipelajari...',
        cadangan1: 'Cadangan / penambahbaikan 1...',
        cadangan2: 'Cadangan / penambahbaikan 2...',
        cadangan3: 'Cadangan / penambahbaikan 3...',
        p1: 'Nama Penceramah 1',
        p2: 'Nama Penceramah 2',
        p3: 'Nama Penceramah 3',
        p4: 'Nama Penceramah 4',
        p5: 'Nama Penceramah 5',
        tandatangan: 'Nama / tandatangan digital ringkas'
      },
      TEA: {
        employee_name: 'Nama pekerja',
        division: 'Bahagian / seksyen',
        comments_0: 'Tambah komen jika perlu...',
        comments_1: 'Tambah komen jika perlu...',
        head_division: 'Nama Ketua Bahagian / Seksyen',
        signature: 'Nama / tandatangan digital ringkas'
      }
    };

    const map = placeholders[formType] || {};

    form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="number"], textarea, select').forEach(control => {
      if (control.disabled) return;

      control.classList.add('sts-writing-field');

      if (!control.placeholder && map[control.name]) {
        control.placeholder = map[control.name];
      }

      if (control.tagName === 'TEXTAREA') {
        control.classList.add('sts-auto-grow');

        const resize = () => {
          control.style.height = 'auto';
          const next = Math.min(Math.max(control.scrollHeight, 82), 220);
          control.style.height = next + 'px';
        };

        control.addEventListener('input', resize);
        window.setTimeout(resize, 0);
      }
    });
  }

  function setupOtherFields() {
    const controls = [
      ...form.querySelectorAll('input[type="checkbox"][value], input[type="radio"][value], select')
    ];

    controls.forEach(control => {
      const controlValue = String(control.value || '').trim();
      const explicitTarget = control.dataset.otherTrigger || '';
      const isOtherChoice = /^(other|others|lain-lain|lain lain)$/i.test(controlValue);

      if (!explicitTarget && control.tagName !== 'SELECT' && !isOtherChoice) return;

      const base = String(control.name || 'other')
        .replace(/\[\]$/, '')
        .replace(/[^A-Za-z0-9_]/g,'_');
      const otherName = explicitTarget || (base + '_other');

      let otherInput = fieldByName(otherName);
      let box = otherInput?.closest('.sts-other-field') || null;

      if (!box) {
        const host = control.closest('td, .mb-3, .col, .col-md-4, .form-group') || control.parentElement;
        if (!host) return;

        box = document.createElement('div');
        box.className = 'sts-other-field';
        box.dataset.otherField = otherName;
        box.hidden = true;
        box.innerHTML = `
          <label>
            <span>Nyatakan lain-lain <b>*</b></span>
            <input type="text" name="${otherName}" maxlength="120" placeholder="Taip jawapan di sini..." disabled>
          </label>
        `;
        host.appendChild(box);
        otherInput = box.querySelector('input');
      }

      if (!otherInput) return;

      const sync = () => {
        let active = false;

        if (control.tagName === 'SELECT') {
          const option = control.options[control.selectedIndex];
          active = !!option && /^(other|others|lain-lain|lain lain)$/i.test(String(option.value).trim());
        } else {
          active = control.checked;
        }

        box.hidden = !active;
        otherInput.disabled = !active;
        otherInput.required = active;
        otherInput.dataset.stsRequired = active ? '1' : '0';
        otherInput.setAttribute('aria-required', active ? 'true' : 'false');

        if (active) {
          box.classList.add('is-visible');
          window.setTimeout(() => {
            otherInput.focus({ preventScroll: true });
          }, 80);
        } else {
          box.classList.remove('is-visible');
          clearFieldError(otherInput);
          otherInput.value = '';
        }
      };

      if (control.tagName === 'SELECT') {
        control.addEventListener('change', sync);
      } else {
        fieldsByName(control.name).forEach(item => item.addEventListener('change', sync));
      }

      sync();
    });
  }

  function setupTeaTotals() {
    if (formType !== 'TEA') return;

    [0,1].forEach(row => {
      const scores = fieldsByName(`score_${row}[]`);
      const total = fieldByName(`total_score_${row}`);
      const level = fieldByName(`competency_level_${row}`);

      if (!scores.length || !total || !level) return;

      scores.forEach(score => {
        score.min = '1';
        score.max = '4';
        score.step = '1';
        score.inputMode = 'numeric';
        score.required = true;
        score.dataset.stsRequired = '1';
      });

      total.readOnly = true;
      total.classList.add('sts-calculated-field');

      const calculate = () => {
        const values = scores.map(score => Number(score.value));
        const complete = values.every(value => Number.isFinite(value) && value >= 1 && value <= 4);

        if (!complete) {
          total.value = '';
          return;
        }

        const sum = values.reduce((a,b) => a+b,0);
        total.value = String(sum);

        if (sum <= 7) level.value = 'Fail';
        else if (sum <= 12) level.value = 'Probation';
        else if (sum <= 17) level.value = 'Pass';
        else level.value = 'Merit';
      };

      scores.forEach(score => score.addEventListener('input', calculate));
      calculate();
    });
  }

  function setupPkkSpeakerRules() {
    if (formType !== 'PKK') return;

    for (let speaker = 1; speaker <= 5; speaker += 1) {
      const name = fieldByName(`p${speaker}`);
      const scores = [0,1,2,3,4]
        .map(aspect => fieldByName(`aspect${aspect}_p${speaker}`))
        .filter(Boolean);

      scores.forEach(score => {
        score.type = 'number';
        score.min = '1';
        score.max = '10';
        score.step = '1';
        score.inputMode = 'numeric';
      });

      const sync = () => {
        const active = speaker === 1
          || String(name?.value || '').trim() !== ''
          || scores.some(score => String(score.value || '').trim() !== '');

        name?.classList.toggle('sts-dependent-active', active);

        if (name) {
          name.required = active;
          name.setAttribute('aria-required', active ? 'true' : 'false');
        }

        scores.forEach(score => {
          score.required = active;
          score.dataset.stsSpeakerRequired = active ? '1' : '0';
          score.setAttribute('aria-required', active ? 'true' : 'false');
        });
      };

      name?.addEventListener('input', sync);
      scores.forEach(score => score.addEventListener('input', sync));
      sync();
    }
  }

  function validate() {
    const invalid = [];

    requiredNames().forEach(name => {
      const control = fieldByName(name);
      if (!visibleEnabled(control)) return;

      clearFieldError(control);
      const value = String(control.value || '').trim();

      if (!value) {
        setFieldError(control, `${fieldLabel(control)} perlu diisi.`);
        invalid.push(control);
        return;
      }

      if (control.type === 'number') {
        const numeric = Number(value);
        const min = control.min === '' ? null : Number(control.min);
        const max = control.max === '' ? null : Number(control.max);

        if (
          !Number.isFinite(numeric)
          || (min !== null && numeric < min)
          || (max !== null && numeric > max)
        ) {
          setFieldError(control, `${fieldLabel(control)} mesti antara ${control.min} hingga ${control.max}.`);
          invalid.push(control);
      }
      }
    });

    requiredGroups().forEach(name => {
      const controls = fieldsByName(name).filter(visibleEnabled);
      if (!controls.length) return;

      clearGroupError(controls);

      if (!controls.some(control => control.checked)) {
        setGroupError(controls, 'Sila pilih satu jawapan.');
        invalid.push(controls[0]);
      }
    });

    form.querySelectorAll('[data-sts-required="1"]').forEach(control => {
      if (!visibleEnabled(control) || invalid.includes(control)) return;
      clearFieldError(control);

      if (String(control.value || '').trim() === '') {
        setFieldError(control, `${fieldLabel(control)} perlu diisi.`);
        invalid.push(control);
      }
    });

    if (formType === 'PKK') {
      for (let speaker = 1; speaker <= 5; speaker += 1) {
        const name = fieldByName(`p${speaker}`);
        const scores = [0,1,2,3,4]
          .map(aspect => fieldByName(`aspect${aspect}_p${speaker}`))
          .filter(Boolean);
        const active = speaker === 1
          || String(name?.value || '').trim() !== ''
          || scores.some(score => String(score.value || '').trim() !== '');

        if (!active) continue;

        if (visibleEnabled(name) && String(name.value || '').trim() === '') {
          setFieldError(name, `Nama Penceramah ${speaker} perlu diisi.`);
          invalid.push(name);
        }

        scores.forEach(score => {
          if (!visibleEnabled(score)) return;
          const value = Number(score.value);

          if (!score.value || !Number.isFinite(value) || value < 1 || value > 10) {
            setFieldError(score, 'Skor mesti 1 hingga 10.');
            invalid.push(score);
          }
        });
      }
    }

    if (formType === 'TEA') {
      [0,1].forEach(row => {
        fieldsByName(`score_${row}[]`).filter(visibleEnabled).forEach(score => {
          const value = Number(score.value);
          clearFieldError(score);

          if (!score.value || !Number.isFinite(value) || value < 1 || value > 4) {
            setFieldError(score, 'Skor mesti 1 hingga 4.');
            invalid.push(score);
          }
        });
      });
    }

    return [...new Set(invalid)];
  }

  function ensureValidationBanner() {
    let banner = form.querySelector('.sts-validation-banner');
    if (banner) return banner;

    banner = document.createElement('div');
    banner.className = 'sts-validation-banner';
    banner.hidden = true;
    banner.innerHTML = `
      <span class="sts-validation-banner-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
      <div>
        <strong>Lengkapkan maklumat yang bertanda merah.</strong>
        <p data-validation-copy></p>
      </div>
    `;

    const firstSection = form.querySelector('[data-form-owner], .row, table, .form-linked-training');
    if (firstSection) form.insertBefore(banner, firstSection);
    else form.prepend(banner);

    return banner;
  }

  function showValidationSummary(count) {
    const banner = ensureValidationBanner();
    banner.hidden = false;
    const copy = banner.querySelector('[data-validation-copy]');
    if (copy) {
      copy.textContent = `${count} bahagian belum lengkap. Sistem sudah bawa anda ke jawapan pertama yang perlu diisi.`;
    }
  }

  function hideValidationSummary() {
    const banner = form.querySelector('.sts-validation-banner');
    if (banner) banner.hidden = true;
  }

  function firstTarget(control) {
    if (!control) return null;
    return control.closest('.sts-invalid-host, .sts-choice-error, td, .col-md-4, .mb-3') || control;
  }

  form.addEventListener('submit', event => {
    const submitter = event.submitter;
    if (submitter?.classList.contains('form-print-button')) return;

    const invalid = validate();
    const nativeValid = form.checkValidity();

    if (!invalid.length && nativeValid) {
      hideValidationSummary();
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    const nativeInvalid = [...form.elements].filter(control =>
      typeof control.checkValidity === 'function'
      && !control.disabled
      && !control.checkValidity()
    );

    const allInvalid = [...new Set([...invalid, ...nativeInvalid])];
    showValidationSummary(allInvalid.length || 1);

    const first = allInvalid[0];
    const target = firstTarget(first);
    target?.scrollIntoView({behavior:'smooth', block:'center'});

    window.setTimeout(() => {
      if (visibleEnabled(first) && typeof first.focus === 'function') {
        first.focus({preventScroll:true});
      }
    }, 450);
  }, true);

  form.addEventListener('invalid', event => {
    const control = event.target;
    if (!(control instanceof HTMLElement) || !control.matches('input,textarea,select')) return;

    event.preventDefault();

    if (control.type === 'radio' || control.type === 'checkbox') {
      const group = control.name ? fieldsByName(control.name) : [control];
      setGroupError(group, 'Sila lengkapkan pilihan ini.');
    } else {
      setFieldError(control, `${fieldLabel(control)} perlu diisi.`);
    }

    if (!invalidScrollScheduled) {
      invalidScrollScheduled = true;

      window.setTimeout(() => {
        const invalidControls = [...form.elements].filter(item =>
          item instanceof HTMLElement
          && item.matches?.('input,textarea,select')
          && !item.disabled
          && item.type !== 'hidden'
          && item.offsetParent !== null
          && item.validity
          && !item.validity.valid
        );

        showValidationSummary(invalidControls.length || 1);

        const first = invalidControls[0] || control;
        const target = firstTarget(first);

        target?.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });

        window.setTimeout(() => {
          if (visibleEnabled(first) && typeof first.focus === 'function') {
            first.focus({ preventScroll: true });
          }
          invalidScrollScheduled = false;
        }, 450);
      }, 0);
    }
  }, true);

  form.addEventListener('input', event => {
    const control = event.target;
    if (!(control instanceof HTMLElement)) return;

    if (control.matches('input,textarea,select')) {
      clearFieldError(control);

      if (control.name) {
        const group = fieldsByName(control.name);
        if (group.length > 1) clearGroupError(group);
      }
    }
  });

  form.addEventListener('change', event => {
    const control = event.target;
    if (!(control instanceof HTMLElement)) return;

    if (control.matches('input,textarea,select')) {
      clearFieldError(control);

      if (control.name) clearGroupError(fieldsByName(control.name));
    }
  });


  function setupPrintReport() {
    const titleByType = {
      BPL: 'BORANG PERMOHONAN LATIHAN (BPL)',
      PKK: 'BORANG PENILAIAN KEBERKESANAN KURSUS / SEMINAR',
      TEA: 'TRAINING EFFECTIVENESS ASSESSMENT FORM'
    };

    const header = document.createElement('div');
    header.className = 'sts-print-report-header';
    header.innerHTML = `
      <div class="sts-print-brand">
        <span class="sts-print-mark">STS</span>
        <div>
          <strong>SMART TRAINING SYSTEM</strong>
          <small>Sabah Economic Development Corporation (SEDCO)</small>
        </div>
      </div>
      <div class="sts-print-title">
        <span>OFFICIAL REPORT</span>
        <h1>${titleByType[formType] || 'TRAINING FORM REPORT'}</h1>
      </div>
    `;

    const footer = document.createElement('div');
    footer.className = 'sts-print-report-footer';
    footer.innerHTML = `
      <span>Generated by Smart Training System (STS)</span>
      <strong data-print-timestamp></strong>
    `;

    form.prepend(header);
    form.appendChild(footer);

    const updatePrintedAt = () => {
      const target = footer.querySelector('[data-print-timestamp]');
      if (!target) return;

      const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Kuala_Lumpur',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
      }).format(new Date());

      target.textContent = 'Dicetak pada: ' + parts;
    };

    window.addEventListener('beforeprint', updatePrintedAt);
    document.querySelectorAll('.form-print-button').forEach(button => {
      button.addEventListener('click', updatePrintedAt, {capture:true});
    });

    updatePrintedAt();
  }

  function setupDepartmentDropdowns() {
    const selects = [...form.querySelectorAll('select.sts-department-select')];

    selects.forEach(select => {
      if (select.dataset.stsCustomSelect === '1') return;
      select.dataset.stsCustomSelect = '1';

      const wrapper = document.createElement('div');
      wrapper.className = 'sts-department-combobox';

      const trigger = document.createElement('button');
      trigger.type = 'button';
      trigger.className = 'sts-department-trigger';
      trigger.setAttribute('aria-haspopup', 'listbox');
      trigger.setAttribute('aria-expanded', 'false');

      const triggerText = document.createElement('span');
      triggerText.className = 'sts-department-trigger-text';

      const triggerIcon = document.createElement('span');
      triggerIcon.className = 'sts-department-trigger-icon';
      triggerIcon.setAttribute('aria-hidden', 'true');
      triggerIcon.textContent = '⌄';

      trigger.append(triggerText, triggerIcon);

      const menu = document.createElement('div');
      menu.className = 'sts-department-menu';
      menu.setAttribute('role', 'listbox');
      menu.hidden = true;

      const sync = () => {
        const selected = select.options[select.selectedIndex] || select.options[0];
        triggerText.textContent = selected?.textContent?.trim() || 'Pilih bahagian SEDCO';
        trigger.classList.toggle('is-placeholder', !select.value);
        trigger.disabled = select.disabled || select.dataset.profileLocked === '1';
        trigger.classList.toggle('is-profile-locked', select.dataset.profileLocked === '1');

        menu.querySelectorAll('.sts-department-option').forEach(item => {
          const active = item.dataset.value === select.value;
          item.classList.toggle('is-selected', active);
          item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
      };

      [...select.options].forEach(option => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'sts-department-option';
        item.dataset.value = option.value;
        item.setAttribute('role', 'option');
        item.textContent = option.textContent.trim();
        item.disabled = option.disabled;

        item.addEventListener('click', () => {
          select.value = option.value;
          select.dispatchEvent(new Event('input', { bubbles: true }));
          select.dispatchEvent(new Event('change', { bubbles: true }));
          menu.hidden = true;
          trigger.setAttribute('aria-expanded', 'false');
          sync();
          trigger.focus();
        });

        menu.appendChild(item);
      });

      trigger.addEventListener('click', () => {
        if (trigger.disabled) return;
        const shouldOpen = menu.hidden;

        document.querySelectorAll('.sts-department-menu:not([hidden])').forEach(openMenu => {
          if (openMenu !== menu) openMenu.hidden = true;
        });
        document.querySelectorAll('.sts-department-trigger[aria-expanded="true"]').forEach(openTrigger => {
          if (openTrigger !== trigger) openTrigger.setAttribute('aria-expanded', 'false');
        });

        menu.hidden = !shouldOpen;
        trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
      });

      trigger.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
          menu.hidden = true;
          trigger.setAttribute('aria-expanded', 'false');
        }
      });

      select.addEventListener('change', sync);
      select.addEventListener('invalid', event => {
        event.preventDefault();
        trigger.classList.add('is-invalid');
        trigger.focus();
      });
      select.addEventListener('input', () => trigger.classList.remove('is-invalid'));

      select.parentNode.insertBefore(wrapper, select);
      wrapper.append(trigger, menu, select);
      select.classList.add('sts-department-native');
      sync();
    });

    document.addEventListener('click', event => {
      if (event.target.closest('.sts-department-combobox')) return;
      document.querySelectorAll('.sts-department-menu:not([hidden])').forEach(menu => {
        menu.hidden = true;
      });
      document.querySelectorAll('.sts-department-trigger[aria-expanded="true"]').forEach(trigger => {
        trigger.setAttribute('aria-expanded', 'false');
      });
    });
  }

  enhanceWritingFields();
  setupOtherFields();
  setupTeaTotals();
  setupPkkSpeakerRules();
  makeRequiredMarkers();
  applyNativeGroupRequirements();
  setupDepartmentDropdowns();
  setupPrintReport();
})();