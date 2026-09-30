(() => {
  const body = document.body;
  if (!body) return;

  const path = (location.pathname.split('/').pop() || 'dashboard.html').toLowerCase();
  const isPhp = path.endsWith('.php');
  const extension = isPhp ? 'php' : 'html';

  const activePage = body.dataset.page || (
    path.includes('application-status') ? 'application-status'
      : path.includes('submissions') ? 'submissions'
      : path.includes('profile') ? 'profile'
      : /task|bpl|pkk|tea/.test(path) ? 'task'
      : 'dashboard'
  );

  const pageUrl = name => `${name}.${extension}`;
  const logoutUrl = isPhp ? 'logout.php' : 'index.html';

  const items = [
    ['dashboard', 'Dashboard', 'bi-grid-1x2-fill', pageUrl('dashboard')],
    ['profile', 'Profile', 'bi-person', pageUrl('profile')],
    ['task', 'Tasks', 'bi-check2-square', pageUrl('task')],
    ['application-status', 'Application status', 'bi-clipboard-check', pageUrl('application-status')],
    ['submissions', 'Submissions', 'bi-inbox', pageUrl('submissions')]
  ];

  const sidebarLinks = items.map(([key, label, icon, href]) => `
    <a class="sedco-nav-item${key === activePage ? ' active' : ''}" href="${href}">
      <i class="bi ${icon}"></i>
      <span>${label}</span>
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
            <span>TRAINING MANAGEMENT SYSTEM</span>
          </a>
        </div>

        <nav class="sedco-top-links" aria-label="Top navigation">
          <a class="nav-link" href="${pageUrl('dashboard')}">Dashboard</a>
          <span class="nav-link is-disabled" aria-disabled="true">About Us</span>
          <span class="nav-link is-disabled" aria-disabled="true">Contact Us</span>
          <a class="nav-link" href="${logoutUrl}">
            <i class="bi bi-box-arrow-right me-1"></i>Log out
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
})();