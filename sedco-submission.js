(() => {
  const STORAGE_KEY = 'sedcoApplications';

  function getApplications() {
    try {
      const value = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      return Array.isArray(value) ? value : [];
    } catch {
      return [];
    }
  }

  function normalizeFormData(form) {
    const data = {};
    const fd = new FormData(form);
    for (const [key, value] of fd.entries()) {
      const clean = typeof value === 'string' ? value.trim() : value;
      if (data[key] !== undefined) {
        data[key] = Array.isArray(data[key]) ? [...data[key], clean] : [data[key], clean];
      } else {
        data[key] = clean;
      }
    }
    return data;
  }

  function inferMeta() {
    const file = (location.pathname.split('/').pop() || '').toLowerCase();
    if (file.includes('bpl')) return {
      type: 'BPL',
      name: 'Permohonan Latihan',
      statusLabel: 'Pending Review'
    };
    if (file.includes('pkk')) return {
      type: 'PKK',
      name: 'Penilaian Keberkesanan Kursus',
      statusLabel: 'Pending Review'
    };
    if (file.includes('tea')) return {
      type: 'TEA',
      name: 'Training Effectiveness Assessment',
      statusLabel: 'Pending Review'
    };
    return { type: 'FORM', name: 'Application', statusLabel: 'Pending Review' };
  }

  function firstValue(data, keys, fallback = '') {
    for (const key of keys) {
      const value = data[key];
      if (Array.isArray(value) && value.length) return value[0];
      if (typeof value === 'string' && value.trim()) return value.trim();
    }
    return fallback;
  }

  function makeApplication(form) {
    const data = normalizeFormData(form);
    const meta = inferMeta();
    const now = new Date();

    const applicant = firstValue(data, ['nama', 'employee_name', 'fullname'], 'Guest');
    const title = firstValue(
      data,
      ['tajuk', 'kursus', 'training_title', 'tajuk_kursus'],
      meta.name
    );
    const applicationDate = firstValue(
      data,
      ['tarikh', 'tarikh_mula', 'date', 'tarikh_penilaian'],
      now.toISOString().slice(0, 10)
    );

    return {
      id: 'APP-' + now.getFullYear() + '-' + String(now.getTime()).slice(-7),
      type: meta.type,
      formName: meta.name,
      title,
      applicant,
      applicationDate,
      status: 'pending',
      statusLabel: meta.statusLabel,
      submittedAt: now.toISOString(),
      updatedAt: now.toISOString(),
      data
    };
  }

  function saveApplication(application) {
    const applications = getApplications();
    applications.unshift(application);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(applications));
  }

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form');
    if (!form) return;

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      event.stopImmediatePropagation();

      if (typeof form.reportValidity === 'function' && !form.reportValidity()) return;

      const application = makeApplication(form);
      saveApplication(application);

      const isPhp = location.pathname.toLowerCase().endsWith('.php');
      const target = isPhp ? 'application-status.php?submitted=1' : 'application-status.html?submitted=1';
      window.location.href = target;
    }, true);
  });
})();