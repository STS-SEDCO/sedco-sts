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