(() => {
  const context = window.SEDCO_FORM_CONTEXT || {};
  const formType = String(context.formType || '').toUpperCase();
  const mode = String(context.mode || 'new').toLowerCase();

  if (!['BPL','PKK','TEA'].includes(formType) || mode !== 'new') return;

  const form = document.querySelector('form[action*="submit_application.php"]');
  if (!form) return;

  form.enctype = 'multipart/form-data';

  const csrf = form.querySelector('input[name="_csrf"]');
  const parent = form.querySelector('input[name="parent_application_id"]');
  let dirty = false;
  let saving = false;
  let restored = false;

  const support = document.createElement('section');
  support.className = 'sts-form-support';
  support.innerHTML = `
    <div class="sts-form-support-head">
      <div>
        <span>Submission support</span>
        <strong>Draft & attachments</strong>
      </div>
      <span class="sts-draft-state" data-draft-state><i class="bi bi-cloud-check"></i> Ready</span>
    </div>
    <div class="sts-form-support-grid">
      <label class="sts-upload-box">
        <span class="sts-upload-icon"><i class="bi bi-paperclip"></i></span>
        <span>
          <strong>Add supporting files</strong>
          <small>PDF, JPG, PNG, DOCX or XLSX · max 5 MB each</small>
        </span>
        <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx">
      </label>
      <div class="sts-autosave-box">
        <span class="sts-upload-icon"><i class="bi bi-cloud-arrow-up"></i></span>
        <span>
          <strong>Autosave enabled</strong>
          <small>Your latest form values are saved automatically while you work.</small>
        </span>
      </div>
    </div>
  `;

  const actions = form.querySelector('.form-actions, .text-end.mt-4.no-print, .center.mt-4');
  if (actions) {
    actions.before(support);
  } else {
    form.appendChild(support);
  }

  const state = support.querySelector('[data-draft-state]');

  function serialize() {
    const data = {};

    form.querySelectorAll('input[name],textarea[name],select[name]').forEach(field => {
      if (
        field.type === 'file' ||
        field.type === 'submit' ||
        field.type === 'button' ||
        field.name === '_csrf' ||
        field.name === 'parent_application_id'
      ) return;

      const name = field.name.replace(/\[\]$/, '');

      if (field.type === 'checkbox') {
        if (!Array.isArray(data[name])) data[name] = [];
        if (field.checked) data[name].push(field.value);
        return;
      }

      if (field.type === 'radio') {
        if (field.checked) data[name] = field.value;
        return;
      }

      if (field.name.endsWith('[]')) {
        if (!Array.isArray(data[name])) data[name] = [];
        if (field.value !== '') data[name].push(field.value);
        return;
      }

      data[name] = field.value;
    });

    return data;
  }

  function hydrate(data) {
    if (!data || typeof data !== 'object') return;

    form.querySelectorAll('input[name],textarea[name],select[name]').forEach(field => {
      if (
        field.type === 'file' ||
        field.type === 'hidden' ||
        field.type === 'submit' ||
        field.type === 'button'
      ) return;

      const name = field.name.replace(/\[\]$/, '');
      const value = data[name];

      if (value === undefined || value === null) return;

      if (field.type === 'checkbox') {
        const values = Array.isArray(value) ? value.map(String) : [String(value)];
        field.checked = values.includes(String(field.value));
        return;
      }

      if (field.type === 'radio') {
        field.checked = String(field.value) === String(value);
        return;
      }

      if (field.name.endsWith('[]')) {
        const group = [...form.querySelectorAll(`[name="${CSS.escape(field.name)}"]`)];
        const index = group.indexOf(field);
        if (Array.isArray(value) && value[index] !== undefined) {
          field.value = value[index];
        }
        return;
      }

      if (!field.value) field.value = value;
    });
  }

  async function loadDraft() {
    try {
      const response = await fetch(`load_draft.php?type=${encodeURIComponent(formType)}`, {
        credentials: 'same-origin'
      });

      if (!response.ok) return;
      const result = await response.json();

      if (result.data) {
        hydrate(result.data);
        restored = true;
        state.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Draft restored';
      }
    } catch {}
  }

  async function saveDraft() {
    if (!dirty || saving || !csrf) return;

    saving = true;
    state.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving';

    try {
      const body = new FormData();
      body.append('_csrf', csrf.value);
      body.append('form_type', formType);
      body.append('payload', JSON.stringify(serialize()));
      if (parent?.value) body.append('parent_application_id', parent.value);

      const response = await fetch('save_draft.php', {
        method: 'POST',
        body,
        credentials: 'same-origin'
      });

      if (!response.ok) throw new Error('save failed');

      dirty = false;
      const now = new Date();
      state.innerHTML = '<i class="bi bi-cloud-check-fill"></i> Saved ' +
        now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    } catch {
      state.innerHTML = '<i class="bi bi-cloud-slash"></i> Save paused';
    } finally {
      saving = false;
    }
  }

  form.addEventListener('input', event => {
    if (event.target.matches('input[type="file"]')) return;
    dirty = true;
    state.innerHTML = '<i class="bi bi-cloud-arrow-up"></i> Unsaved changes';
  });

  form.addEventListener('change', event => {
    if (event.target.matches('input[type="file"]')) return;
    dirty = true;
  });

  form.addEventListener('submit', event => {
    const invalid = [...form.querySelectorAll('[required]')].find(field => {
      if (field.disabled) return false;
      return !field.checkValidity();
    });

    if (invalid) {
      event.preventDefault();
      invalid.focus();
      invalid.reportValidity();
    }
  });

  loadDraft().finally(() => {
    if (!restored) state.innerHTML = '<i class="bi bi-cloud-check"></i> Ready';
  });

  setInterval(saveDraft, 8000);
  window.addEventListener('beforeunload', () => {
    if (dirty) saveDraft();
  });
})();