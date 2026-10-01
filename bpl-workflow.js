(() => {
  const data = window.SEDCO_BPL_DATA || {};

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
  }

  document.addEventListener('DOMContentLoaded', hydrate);
})();