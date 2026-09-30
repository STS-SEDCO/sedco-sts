(() => {
  const context = window.SEDCO_FORM_CONTEXT || {};
  const role = String(context.role || 'staff').toLowerCase();
  const mode = String(context.mode || 'new').toLowerCase();
  const formType = String(context.formType || '').toUpperCase();

  const aliases = {
    head_of_division: 'head_of_department',
    pengerusi_besar: 'general_manager'
  };

  const normalizedRole = aliases[role] || role;

  const roleLabels = {
    staff: 'Staff / Applicant',
    head_of_department: 'Head of Department',
    training_section: 'Training Department',
    general_manager: 'General Manager',
    admin: 'System Administrator'
  };

  function canEdit(owner) {
    const normalizedOwner = aliases[owner] || owner;

    if (mode === 'new' && formType === 'BPL') {
      if (normalizedRole === 'admin') return normalizedOwner === 'staff';
      return normalizedRole === 'staff' && normalizedOwner === 'staff';
    }

    if (normalizedRole === 'admin') return true;
    return normalizedOwner === normalizedRole;
  }

  function lockSection(section, owner) {
    section.classList.add('form-section-locked');

    section.querySelectorAll('input, textarea, select, button').forEach(control => {
      if (control.type === 'hidden') return;
      if (control.classList.contains('form-print-button')) return;
      control.disabled = true;
      control.setAttribute('aria-disabled', 'true');
    });

    const header = section.querySelector('th');
    if (header && !header.querySelector('.form-lock-badge')) {
      const badge = document.createElement('span');
      badge.className = 'form-lock-badge';
      badge.innerHTML = '<i class="bi bi-lock-fill"></i> ' + (roleLabels[owner] || 'Restricted');
      header.appendChild(badge);
    }
  }

  function unlockSection(section) {
    section.classList.add('form-section-editable');

    const header = section.querySelector('th');
    if (header && !header.querySelector('.form-edit-badge')) {
      const badge = document.createElement('span');
      badge.className = 'form-edit-badge';
      badge.innerHTML = '<i class="bi bi-pencil-square"></i> Your section';
      header.appendChild(badge);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const sections = [...document.querySelectorAll('[data-form-owner]')];

    sections.forEach(section => {
      const owner = String(section.dataset.formOwner || '').toLowerCase();

      if (canEdit(owner)) {
        unlockSection(section);
      } else {
        lockSection(section, owner);
      }
    });

    const notice = document.querySelector('[data-form-permission-notice]');
    if (notice) {
      const label = roleLabels[normalizedRole] || normalizedRole;
      notice.innerHTML = '<i class="bi bi-shield-lock"></i><span><strong>' + label + '</strong> — only your section is editable. Other workflow sections are locked.</span>';
    }
  });
})();