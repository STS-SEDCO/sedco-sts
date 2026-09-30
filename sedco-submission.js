(() => {
  // PHP pages submit to MySQL through submit_application.php.
  // This script is only the GitHub Pages preview fallback.
  if (location.pathname.toLowerCase().endsWith('.php')) return;

  const STORAGE_KEY = 'sedcoApplications';

  function readApplications() {
    try {
      const value = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      return Array.isArray(value) ? value : [];
    } catch {
      return [];
    }
  }

  function formToObject(form) {
    const data = {};
    const formData = new FormData(form);

    for (const [key, rawValue] of formData.entries()) {
      const value = typeof rawValue === 'string' ? rawValue.trim() : rawValue;

      if (data[key] === undefined) {
        data[key] = value;
      } else {
        data[key] = Array.isArray(data[key])
          ? [...data[key], value]
          : [data[key], value];
      }
    }

    return data;
  }

  function formMeta() {
    const page = (location.pathname.split('/').pop() || '').toLowerCase();

    if (page.includes('bpl')) {
      return { type: 'BPL', formName: 'Permohonan Latihan' };
    }

    if (page.includes('pkk')) {
      return { type: 'PKK', formName: 'Penilaian Keberkesanan Kursus' };
    }

    return { type: 'TEA', formName: 'Training Effectiveness Assessment' };
  }

  function firstValue(data, keys, fallback) {
    for (const key of keys) {
      const value = data[key];

      if (Array.isArray(value) && value.length) return String(value[0]).trim();
      if (typeof value === 'string' && value.trim()) return value.trim();
    }

    return fallback;
  }

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form');
    if (!form) return;

    form.addEventListener('submit', event => {
      event.preventDefault();

      if (!form.reportValidity()) return;

      const data = formToObject(form);
      const meta = formMeta();
      const now = new Date();
      const applications = readApplications();

      const application = {
        id: `APP-${now.getFullYear()}-${String(now.getTime()).slice(-7)}`,
        type: meta.type,
        formName: meta.formName,
        title: firstValue(
          data,
          ['tajuk', 'kursus', 'training_title'],
          meta.formName
        ),
        applicant: firstValue(
          data,
          ['nama', 'employee_name', 'fullname'],
          'Guest'
        ),
        applicationDate: firstValue(
          data,
          ['tarikh', 'tarikh_mula', 'date', 'tarikh_penilaian'],
          now.toISOString().slice(0, 10)
        ),
        status: 'pending',
        submittedAt: now.toISOString(),
        updatedAt: now.toISOString(),
        data
      };

      applications.unshift(application);
      localStorage.setItem(STORAGE_KEY, JSON.stringify(applications));

      location.href = 'application-status.html?submitted=1';
    });
  });
})();