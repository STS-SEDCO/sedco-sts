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
          return ['ulasan_latihan','tarikh_latihan','tt_latihan'];
        }
        if (currentStage === 'gm') {
          return ['tarikh_pgs','tt_pgs'];
        }
        if (currentStage === 'chairman') {
          return ['tarikh_sedco','tt_sedco'];
        }
        if (currentStage === 'finance') {
          return ['bayaran_kursus'];
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
      if (currentStage === 'gm') {
        return ['kelulusan_pgs'];
      }
      if (currentStage === 'chairman') {
        return ['kelulusan_sedco'];
      }
      if (currentStage === 'finance') {
        return ['pendahuluan_diterima','telah_didaftar'];
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

    const report = document.createElement('section');
    report.className = 'sts-official-print-report';
    report.setAttribute('aria-hidden', 'true');
    document.body.appendChild(report);

    const escapeHtml = value => String(value ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");

    const rawValue = name => {
      const controls = fieldsByName(name);
      if (!controls.length) return '';

      const first = controls[0];

      if (first.type === 'radio' || first.type === 'checkbox') {
        const values = controls
          .filter(control => control.checked)
          .map(control => String(control.value || '').trim())
          .filter(Boolean);

        if (name === 'kenderaan[]' && values.includes('Lain-lain')) {
          const other = String(fieldByName('kenderaan_other')?.value || '').trim();
          if (other) {
            const index = values.indexOf('Lain-lain');
            values[index] = 'Lain-lain: ' + other;
          }
        }

        return values.join(', ');
      }

      if (first.tagName === 'SELECT') {
        return first.options[first.selectedIndex]?.textContent?.trim() || first.value || '';
      }

      return String(first.value || '').trim();
    };

    const displayValue = name => {
      const value = rawValue(name);
      if (!value) return 'Not provided';

      const control = fieldByName(name);
      if (control?.type === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [year,month,day] = value.split('-');
        return `${day}/${month}/${year}`;
      }

      return value;
    };

    const row = (label, value, colspan = 1) =>
      `<tr>
        <th class="report-label">${escapeHtml(label)}</th>
        <td colspan="${colspan}">${escapeHtml(value || 'Not provided')}</td>
      </tr>`;

    const pairRow = (l1,v1,l2,v2) =>
      `<tr>
        <th class="report-label">${escapeHtml(l1)}</th>
        <td>${escapeHtml(v1 || 'Not provided')}</td>
        <th class="report-label">${escapeHtml(l2)}</th>
        <td>${escapeHtml(v2 || 'Not provided')}</td>
      </tr>`;

    const sectionWrap = (title, innerHtml, extraClass = '') => {
      const match = String(title).match(/^([A-Z])\.\s*(.+)$/);
      const index = match ? match[1] : '';
      const heading = match ? match[2] : title;

      return `
        <section class="report-section ${extraClass}">
          <div class="report-section-heading">
            ${index ? `<span class="report-section-index">${escapeHtml(index)}</span>` : ''}
            <div class="report-section-copy">
              <strong>${escapeHtml(heading)}</strong>
            </div>
          </div>
          <div class="report-section-body">
            ${innerHtml}
          </div>
        </section>
      `;
    };

    const signatureBlock = (dateLabel, dateValue, signLabel, signValue) => `
      <div class="report-signoff-grid">
        <div class="report-signoff-item">
          <span class="report-signoff-label">${escapeHtml(dateLabel)}</span>
          <strong class="report-signoff-date">${escapeHtml(dateValue || 'Not provided')}</strong>
        </div>

        <div class="report-signoff-item report-signature-item">
          <span class="report-signoff-label">${escapeHtml(signLabel)}</span>
          <div class="report-signature-space">${signValue && signValue !== 'Not provided' ? escapeHtml(signValue) : '&nbsp;'}</div>
          <div class="report-signature-line"></div>
        </div>
      </div>
    `;

    const buildBpl = () => {
      return `
        ${sectionWrap('A. MAKLUMAT PEMOHON', `
          <table class="report-table report-table-four">
            <tbody>
              ${pairRow('01. Nama',displayValue('nama'),'02. Bahagian',displayValue('bahagian'))}
              ${pairRow('03. Jawatan',displayValue('jawatan'),'Tarikh Penghantaran',displayValue('tarikh'))}
              ${row('04. Kursus / Seminar',displayValue('kursus'),3)}
            </tbody>
          </table>
        `)}

        ${sectionWrap('B. MAKLUMAT KURSUS / SEMINAR', `
          <table class="report-table report-table-four">
            <tbody>
              ${row('01. Tajuk Kursus',displayValue('tajuk'),3)}
              ${row('02. Penganjur',displayValue('penganjur'),3)}
              ${pairRow('03. Tarikh Mula',displayValue('tarikh_mula'),'04. Tarikh Tamat',displayValue('tarikh_tamat'))}
              ${row('05. Tempat Kursus',displayValue('tempat'),3)}
              ${row('06. Yuran (RM)',displayValue('yuran'),3)}
              ${row('07. Kandungan',displayValue('kandungan'),3)}
            </tbody>
          </table>
        `)}

        ${sectionWrap('C. MAKLUMAT TUGAS LUAR DAERAH', `
          <table class="report-table report-table-four">
            <tbody>
              ${row('08. Tempat Bertugas',displayValue('tempat_tugas'),3)}
              ${row('i. Kenderaan',displayValue('kenderaan[]'),3)}
              ${pairRow('ii. Masa Bertolak',displayValue('masa_bertolak'),'iii. Masa Kembali',displayValue('masa_kembali'))}
              ${row('09. Pendahuluan',displayValue('pendahuluan'),3)}
            </tbody>
          </table>
        `)}

        ${sectionWrap('D. ULASAN PENGURUS SEKSYEN LATIHAN', `
          <table class="report-table report-table-two">
            <tbody>
              ${row('Ulasan',displayValue('ulasan_latihan'),1)}
            </tbody>
          </table>
          ${signatureBlock('Tarikh',displayValue('tarikh_latihan'),'Tandatangan',displayValue('tt_latihan'))}
        `, 'report-review-section')}

        ${sectionWrap('E. ULASAN KETUA / PENGURUS BAHAGIAN', `
          <table class="report-table report-table-two">
            <tbody>
              ${row('Ulasan',displayValue('ulasan_bahagian'),1)}
            </tbody>
          </table>
          ${signatureBlock('Tarikh',displayValue('tarikh_bahagian'),'Tandatangan',displayValue('tt_bahagian'))}
        `, 'report-review-section')}

        ${sectionWrap('F. ULASAN PENGURUS BESAR KUMPULAN SEDCO', `
          <table class="report-table report-table-two">
            <tbody>
              ${row('Kelulusan',displayValue('kelulusan_pgs'),1)}
            </tbody>
          </table>
          ${signatureBlock('Tarikh',displayValue('tarikh_pgs'),'Tandatangan',displayValue('tt_pgs'))}
        `, 'report-review-section')}

        ${sectionWrap('G. ULASAN PENGURUS SEDCO', `
          <table class="report-table report-table-two">
            <tbody>
              ${row('Kelulusan',displayValue('kelulusan_sedco'),1)}
            </tbody>
          </table>
          ${signatureBlock('Tarikh',displayValue('tarikh_sedco'),'Tandatangan',displayValue('tt_sedco'))}
        `, 'report-review-section')}

        ${sectionWrap('H. ULASAN KEWANGAN', `
          <table class="report-table report-table-four">
            <tbody>
              ${row('a) Bayaran Kursus / Yuran (RM)',displayValue('bayaran_kursus'),3)}
              ${row('b) Permohonan Pendahuluan Diterima',displayValue('pendahuluan_diterima'),3)}
              ${row('c) Telah Didaftarkan',displayValue('telah_didaftar'),3)}
            </tbody>
          </table>
        `)}
      `;
    };

    const buildPkk = () => {
      const learned = [1,2,3,4,5].map(i =>
        `<tr>
          <th class="report-label report-number">${i}.</th>
          <td>${escapeHtml(displayValue('perkara'+i))}</td>
        </tr>`
      ).join('');

      const suggestions = [1,2,3].map(i =>
        `<tr>
          <th class="report-label report-number">${i}.</th>
          <td>${escapeHtml(displayValue('cadangan'+i))}</td>
        </tr>`
      ).join('');

      const speakers = [1,2,3,4,5].map(i => escapeHtml(displayValue('p'+i)));

      const scoreRows = [
        ['i. Kefahaman / Penguasaan terhadap subjek','aspect0'],
        ['ii. Penyampaian','aspect1'],
        ['iii. Penyediaan bahan / slaid','aspect2'],
        ['iv. Perhubungan dan penglibatan peserta','aspect3'],
        ['v. Penggunaan contoh dalam ceramah','aspect4']
      ].map(([label,prefix]) => `
        <tr>
          <th class="report-label">${escapeHtml(label)}</th>
          ${[1,2,3,4,5].map(i => `<td class="report-center">${escapeHtml(displayValue(prefix+'_p'+i))}</td>`).join('')}
        </tr>
      `).join('');

      return `
        ${sectionWrap('A. MAKLUMAT PEGAWAI / KURSUS', `
          <table class="report-table report-table-four">
            <tbody>
              ${pairRow('Nama Pegawai / Staf',displayValue('nama'),'Bahagian',displayValue('bahagian'))}
              ${pairRow('Jawatan',displayValue('jawatan'),'Tarikh / Hari',displayValue('tarikh'))}
              ${row('Tajuk Kursus / Seminar',displayValue('tajuk'),3)}
              ${row('Tempat',displayValue('tempat'),3)}
            </tbody>
          </table>
        `)}

        ${sectionWrap('B. PENILAIAN KURSUS / SEMINAR', `
          <table class="report-table report-table-two">
            <tbody>
              ${row('1. Objektif menghadiri kursus',displayValue('objektif'),1)}
              <tr class="report-subtitle"><th colspan="2">2. Lima (5) perkara yang dipelajari</th></tr>
              ${learned}
              <tr class="report-subtitle"><th colspan="2">3. Cadangan / Penambahbaikan</th></tr>
              ${suggestions}
            </tbody>
          </table>
        `)}

        ${sectionWrap('C. PENILAIAN PENCERAMAH', `
          <table class="report-table report-score-table">
            <thead>
              <tr>
                <th>Aspek</th>
                <th>P1<br><small>${speakers[0]}</small></th>
                <th>P2<br><small>${speakers[1]}</small></th>
                <th>P3<br><small>${speakers[2]}</small></th>
                <th>P4<br><small>${speakers[3]}</small></th>
                <th>P5<br><small>${speakers[4]}</small></th>
              </tr>
            </thead>
            <tbody>${scoreRows}</tbody>
          </table>
        `)}

        ${sectionWrap('D. PENGESAHAN', `
          ${signatureBlock('Tarikh Penilaian',displayValue('tarikh_penilaian'),'Tandatangan',displayValue('tandatangan'))}
        `, 'report-review-section')}
      `;
    };

    const buildTea = () => {
      const rows = [0,1].map(rowIndex => {
        const scores = fieldsByName(`score_${rowIndex}[]`).map(
          control => String(control.value || '').trim() || 'Not provided'
        );

        const title = rowIndex === 0
          ? (
              form.querySelector('table:nth-of-type(2) tbody tr:nth-child(1) td:first-child')
                ?.textContent?.trim()
              || 'Training'
            )
          : 'Public Speaking & Presentation Skill';

        return `
          <tr>
            <td>${escapeHtml(title)}</td>
            ${[0,1,2,3,4].map(i => `<td class="report-center">${escapeHtml(scores[i] || 'Not provided')}</td>`).join('')}
            <td class="report-center">${escapeHtml(displayValue('total_score_'+rowIndex))}</td>
            <td>${escapeHtml(displayValue('competency_level_'+rowIndex))}</td>
            <td>${escapeHtml(displayValue('comments_'+rowIndex))}</td>
          </tr>
        `;
      }).join('');

      return `
        ${sectionWrap('A. EMPLOYEE / EVALUATION INFORMATION', `
          <table class="report-table report-table-four">
            <tbody>
              ${pairRow('Employee Name',displayValue('employee_name'),'Division / Section',displayValue('division'))}
              ${row('Evaluation Period',displayValue('month'),3)}
            </tbody>
          </table>
        `)}

        ${sectionWrap('B. TRAINING EFFECTIVENESS ASSESSMENT', `
          <div class="report-rating-note">
            Rating Scale: Poor (1) · Average (2) · Good (3) · Excellent (4)
          </div>
          <table class="report-table report-tea-table">
            <thead>
              <tr>
                <th>Training Title</th>
                <th>Productivity</th>
                <th>Quality of Work</th>
                <th>Skill Enhancement</th>
                <th>Application of Knowledge</th>
                <th>Attitude</th>
                <th>Total Score</th>
                <th>Competency Level</th>
                <th>Additional Comments</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        `)}

        ${sectionWrap('C. COMPETENCY RANKING REFERENCE', `
          <table class="report-table report-table-two">
            <thead>
              <tr><th>Ranking</th><th>Description</th></tr>
            </thead>
            <tbody>
              <tr><td>Fail</td><td>0 to 7 points. No significant improvement observed. Additional training is recommended.</td></tr>
              <tr><td>Probation</td><td>8 to 12 points. Requires supervision for 6 months. A reassessment is required.</td></tr>
              <tr><td>Pass</td><td>13 to 17 points. Can perform tasks with minimal supervision.</td></tr>
              <tr><td>Merit</td><td>18 points. Shows excellent competency. Can guide others.</td></tr>
            </tbody>
          </table>
        `)}

        ${sectionWrap('D. EVALUATION CONFIRMATION', `
          <table class="report-table report-table-two">
            <tbody>
              ${row('Head of Division / Section',displayValue('head_division'),1)}
            </tbody>
          </table>
          ${signatureBlock('Date of Evaluation',displayValue('date'),'Signature',displayValue('signature'))}
        `, 'report-review-section')}
      `;
    };

    const updateReport = () => {
      const now = new Intl.DateTimeFormat('en-GB', {
        timeZone:'Asia/Kuala_Lumpur',
        day:'2-digit',
        month:'2-digit',
        year:'numeric',
        hour:'2-digit',
        minute:'2-digit',
        second:'2-digit',
        hour12:true
      }).format(new Date());

      const body = formType === 'BPL'
        ? buildBpl()
        : formType === 'PKK'
          ? buildPkk()
          : buildTea();

      const applicationRef =
        String(fieldByName('application_no')?.value || '').trim()
        || String(document.querySelector('[data-application-no]')?.textContent || '').trim();

      report.innerHTML = `
        <header class="report-official-header">
          <div class="report-letterhead">
            <div class="report-org-copy">
              <span class="report-org-kicker">OFFICIAL TRAINING DOCUMENT</span>
              <strong>SABAH ECONOMIC DEVELOPMENT CORPORATION</strong>
              <small>Smart Training System · Human Resource & Administration</small>
            </div>

            <div class="report-doc-code">
              <span>FORM CODE</span>
              <strong>${escapeHtml(formType)}</strong>
            </div>
          </div>

          <div class="report-title-block">
            <h1>${escapeHtml(titleByType[formType] || 'TRAINING REPORT')}</h1>
            <div class="report-title-meta">
              <span>Document type: Training administration record</span>
              ${applicationRef ? `<span>Reference: ${escapeHtml(applicationRef)}</span>` : ''}
            </div>
          </div>
        </header>

        <main class="report-official-body">
          ${body}
        </main>

        <footer class="report-official-footer">
          <div>
            <strong>Smart Training System (STS)</strong>
            <span>Sabah Economic Development Corporation</span>
          </div>
          <div class="report-print-meta">
            <span>Official copy generated by the system</span>
            <strong>Dicetak pada: ${escapeHtml(now)}</strong>
          </div>
        </footer>
      `;
    };

    window.addEventListener('beforeprint', () => {
      updateReport();
      document.body.classList.add('sts-printing-report');
      report.removeAttribute('aria-hidden');
    });

    window.addEventListener('afterprint', () => {
      document.body.classList.remove('sts-printing-report');
      report.setAttribute('aria-hidden','true');
    });

    const activatePrintReport = () => {
      updateReport();
      document.body.classList.add('sts-printing-report');
      report.removeAttribute('aria-hidden');
    };

    document.querySelectorAll('.form-print-button').forEach(button => {
      button.addEventListener('click', activatePrintReport, {capture:true});
    });

    updateReport();
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