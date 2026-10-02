(() => {
  const context = window.SEDCO_FORM_CONTEXT || {};
  const role = String(context.role || 'staff').toLowerCase();
  const mode = String(context.mode || 'new').toLowerCase();
  const formType = String(context.formType || '').toUpperCase();
  const currentStage = String(context.currentStage || '').toLowerCase();
  const applicationStatus = String(context.status || '').toLowerCase();

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

  function stageOwner(stage) {
    return {
      hod: 'head_of_department',
      training: 'training_section',
      gm: 'general_manager'
    }[stage] || null;
  }

  function canEdit(owner) {
    const normalizedOwner = aliases[owner] || owner;

    if (mode === 'new' && formType === 'BPL') {
      if (normalizedRole === 'admin') return normalizedOwner === 'staff';
      return normalizedRole === 'staff' && normalizedOwner === 'staff';
    }

    if (mode === 'review' && formType === 'BPL') {
      if (['approved', 'rejected'].includes(applicationStatus) || currentStage === 'completed') {
        return false;
      }

      if (applicationStatus === 'correction') {
        return normalizedRole === 'staff' && normalizedOwner === 'staff';
      }

      const ownerForStage = stageOwner(currentStage);

      if (normalizedRole === 'admin') {
        return normalizedOwner === ownerForStage;
      }

      return normalizedRole === ownerForStage && normalizedOwner === ownerForStage;
    }

    if (normalizedRole === 'admin') return true;
    return normalizedOwner === normalizedRole;
  }

  function lockSection(section, owner) {
    section.classList.remove('form-section-editable');
    section.classList.add('form-section-locked');
    section.setAttribute('inert', '');
    section.setAttribute('aria-disabled', 'true');

    section.querySelectorAll('input, textarea, select, button').forEach(control => {
      if (control.type === 'hidden') return;
      if (control.classList.contains('form-print-button')) return;
      control.disabled = true;
      control.setAttribute('aria-disabled', 'true');
    });

    const header = section.querySelector('th, .form-section-title');
    const editBadge = header?.querySelector('.form-edit-badge');
    if (editBadge) editBadge.remove();

    if (header && !header.querySelector('.form-lock-badge')) {
      const badge = document.createElement('span');
      badge.className = 'form-lock-badge';
      badge.innerHTML = '<i class="bi bi-lock-fill"></i> ' + (roleLabels[owner] || 'Restricted');
      header.appendChild(badge);
    }
  }

  function unlockSection(section) {
    section.classList.remove('form-section-locked');
    section.classList.add('form-section-editable');
    section.removeAttribute('inert');
    section.removeAttribute('aria-disabled');

    section.querySelectorAll('input, textarea, select, button').forEach(control => {
      if (control.type === 'hidden') return;
      if (control.classList.contains('form-print-button')) return;
      control.disabled = false;
      control.removeAttribute('aria-disabled');
    });

    const header = section.querySelector('th, .form-section-title');
    const lockBadge = header?.querySelector('.form-lock-badge');
    if (lockBadge) lockBadge.remove();

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
      const ownerForStage = stageOwner(currentStage);
      const stageLabel = roleLabels[ownerForStage] || 'Reviewer';

      if (mode === 'new' && formType === 'BPL') {
        notice.innerHTML = '<i class="bi bi-shield-lock"></i><span><strong>Applicant section open.</strong> Approval sections are greyed out and will unlock only for the assigned reviewer after submission.</span>';
      } else if (mode === 'review' && formType === 'BPL') {
        const editableNow = canEdit(ownerForStage || '');

        if (['approved', 'rejected'].includes(applicationStatus) || currentStage === 'completed') {
          notice.innerHTML = '<i class="bi bi-check2-circle"></i><span><strong>Workflow completed.</strong> This form is read-only.</span>';
        } else if (applicationStatus === 'correction' && normalizedRole === 'staff') {
          notice.innerHTML = '<i class="bi bi-exclamation-circle"></i><span><strong>Correction requested.</strong> Update the applicant sections highlighted for you, then resubmit. Approval sections remain locked.</span>';
        } else if (editableNow) {
          notice.innerHTML = '<i class="bi bi-pencil-square"></i><span><strong>' + label + '</strong> — current stage: ' + stageLabel + '. Only your review section is editable; all other sections are read-only.</span>';
        } else {
          notice.innerHTML = '<i class="bi bi-lock-fill"></i><span><strong>Current stage: ' + stageLabel + '.</strong> You can view this form, but only the assigned reviewer can edit this stage.</span>';
        }
      } else {
        notice.innerHTML = '<i class="bi bi-shield-lock"></i><span><strong>' + label + '</strong> — only your section is editable. Other workflow sections are locked.</span>';
      }
    }
  });
})();