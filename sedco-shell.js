(() => {
  const body = document.body;
  if (!body) return;

  const path = (location.pathname.split('/').pop() || 'dashboard.html').toLowerCase();
  const isPhp = path.endsWith('.php');
  const extension = isPhp ? 'php' : 'html';

  const activePage = body.dataset.page || (
    path.includes('application-status') ? 'application-status'
      : path.includes('approval-history') ? 'approval-history'
      : path.includes('submissions') ? 'submissions'
      : path.includes('profile') ? 'profile'
      : path.includes('notifications') ? 'notifications'
      : path.includes('reports') ? 'reports'
      : path.includes('admin-users') ? 'admin-users'
      : path.includes('admin-settings') ? 'admin-settings'
      : path.includes('audit-log') ? 'audit-log'
      : /task|bpl|pkk|tea/.test(path) ? 'task'
      : 'dashboard'
  );

  const pageUrl = name => `${name}.${extension}`;
  const logoutUrl = isPhp ? 'logout.php' : 'index.html';

  let previewUser = null;
  if (!isPhp) {
    try {
      previewUser = JSON.parse(localStorage.getItem('sedcoPreviewUser') || 'null');
    } catch {
      previewUser = null;
    }

    if (!previewUser?.email) {
      location.replace('index.html');
      return;
    }
  }

  const userRole = String(
    body.dataset.role || (!isPhp ? previewUser?.role : '') || ''
  ).toLowerCase();

  const items = [
    ['dashboard', 'Dashboard', 'bi-grid-1x2-fill', pageUrl('dashboard')],
    ['task', 'Training Forms', 'bi-file-earmark-text', pageUrl('task')],
    ['application-status', 'Application status', 'bi-clipboard-check', pageUrl('application-status')],
    ['submissions', 'Approval', 'bi-check2-square', pageUrl('submissions')],
    ['approval-history', 'Approval History', 'bi-clock-history', pageUrl('approval-history')],
    ['notifications', 'Notifications', 'bi-bell', pageUrl('notifications')],
    ['reports', 'Reports & Analytics', 'bi-bar-chart-line', pageUrl('reports')],
    ['admin-users', 'User Management', 'bi-people', pageUrl('admin-users')],
    ['admin-settings', 'System Settings', 'bi-sliders', pageUrl('admin-settings')],
    ['audit-log', 'Audit Log', 'bi-shield-check', pageUrl('audit-log')]
  ];

  const visibleItems = items.filter(([key]) => {
    if (['submissions','approval-history'].includes(key)) {
      return ['admin','training_section','general_manager','head_of_department'].includes(userRole);
    }
    if (key === 'reports') return ['admin','training_section','general_manager','head_of_department'].includes(userRole);
    if (['admin-users','admin-settings','audit-log'].includes(key)) return userRole === 'admin';
    return true;
  });

  const sidebarLinks = visibleItems.map(([key, label, icon, href]) => `
    <a class="sedco-nav-item${key === activePage ? ' active' : ''}" href="${href}" data-nav-key="${key}">
      <i class="bi ${icon}"></i>
      <span>${label}</span>
      ${key === 'notifications' ? '<b class="sedco-nav-badge" data-notification-badge hidden>0</b>' : ''}
      ${key === 'submissions' ? '<b class="sedco-nav-badge" data-approval-badge hidden>0</b>' : ''}
    </a>
  `).join('');

  const shell = `
    <header class="navbar sedco-navbar">
      <div class="sedco-navbar-inner">
        <div class="sedco-brand-wrap">
          <button class="sedco-mobile-toggle" id="sedcoMobileToggle" type="button" aria-label="Toggle navigation" aria-expanded="true">
            <span class="sedco-burger-lines" aria-hidden="true">
              <span></span>
              <span></span>
              <span></span>
            </span>
          </button>

          <a class="navbar-brand sedco-brand" href="${pageUrl('dashboard')}">
            <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
            <span>SMART TRAINING SYSTEM</span>
          </a>
        </div>

        <nav class="sedco-top-links" aria-label="Top navigation">
          <a class="nav-link" href="${pageUrl('dashboard')}">Dashboard</a>
          <span class="nav-link is-disabled" aria-disabled="true">About Us</span>
          <span class="nav-link is-disabled" aria-disabled="true">Contact Us</span>
          <a class="nav-link" href="${logoutUrl}">
            <i class="bi bi-box-arrow-right me-1"></i>Log out
          </a>
          <a
            class="sedco-top-profile${activePage === 'profile' ? ' active' : ''}"
            href="${pageUrl('profile')}"
            aria-label="Open profile"
            title="Profile"
          >
            <i class="bi bi-person-fill"></i>
          </a>
        </nav>
      </div>
    </header>

    <aside class="sidebar sedco-sidebar" id="sedcoSidebar">
      <div class="sedco-sidebar-label">Workspace</div>

      <nav class="sedco-side-nav" aria-label="Sidebar navigation">
        ${sidebarLinks}
      </nav>

      <div class="sedco-sidebar-footer">
        <a class="sedco-nav-item" href="${logoutUrl}">
          <i class="bi bi-box-arrow-left"></i>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <button
      class="sedco-sidebar-backdrop"
      id="sedcoSidebarBackdrop"
      type="button"
      aria-label="Close navigation"
    ></button>
  `;

  body.insertAdjacentHTML('afterbegin', shell);

  const toggle = document.getElementById('sedcoMobileToggle');
  const backdrop = document.getElementById('sedcoSidebarBackdrop');

  if (!isPhp) {
    body.dataset.role = userRole || 'staff';

    document.querySelectorAll(`a[href="${logoutUrl}"]`).forEach(link => {
      link.addEventListener('click', () => {
        localStorage.removeItem('sedcoPreviewUser');
      });
    });
  }

  const closeMenu = () => body.classList.remove('sedco-menu-open');

  const syncToggleState = () => {
    const mobile = window.innerWidth <= 760;
    const expanded = mobile
      ? body.classList.contains('sedco-menu-open')
      : !body.classList.contains('sedco-sidebar-collapsed');

    toggle?.setAttribute('aria-expanded', String(expanded));
  };

  try {
    if (window.innerWidth > 900 && localStorage.getItem('sedcoSidebarCollapsed') === '1') {
      body.classList.add('sedco-sidebar-collapsed');
    }
  } catch {}

  toggle?.addEventListener('click', () => {
    if (window.innerWidth <= 760) {
      body.classList.toggle('sedco-menu-open');
    } else {
      body.classList.toggle('sedco-sidebar-collapsed');

      try {
        localStorage.setItem(
          'sedcoSidebarCollapsed',
          body.classList.contains('sedco-sidebar-collapsed') ? '1' : '0'
        );
      } catch {}
    }

    syncToggleState();
  });

  backdrop?.addEventListener('click', () => {
    closeMenu();
    syncToggleState();
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 760) closeMenu();

    if (window.innerWidth <= 900) {
      body.classList.remove('sedco-sidebar-collapsed');
    } else {
      try {
        if (localStorage.getItem('sedcoSidebarCollapsed') === '1') {
          body.classList.add('sedco-sidebar-collapsed');
        }
      } catch {}
    }

    syncToggleState();
  });

  syncToggleState();

  if (isPhp) {
    fetch('notification-count.php', { credentials: 'same-origin' })
      .then(response => response.ok ? response.json() : null)
      .then(data => {
        const badge = document.querySelector('[data-notification-badge]');
        const count = Number(data?.count || 0);
        if (!badge || count <= 0) return;
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.hidden = false;
      })
      .catch(() => {});

    if (['admin','training_section','general_manager','head_of_department'].includes(userRole)) {
      fetch('approval-count.php', { credentials: 'same-origin' })
        .then(response => response.ok ? response.json() : null)
        .then(data => {
          const badge = document.querySelector('[data-approval-badge]');
          const count = Number(data?.count || 0);
          if (!badge || count <= 0) return;
          badge.textContent = count > 99 ? '99+' : String(count);
          badge.hidden = false;
        })
        .catch(() => {});
    }
  }
})();