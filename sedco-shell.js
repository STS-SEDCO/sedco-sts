(() => {
  const body = document.body;
  if (!body) return;

  const path = (location.pathname.split('/').pop() || 'dashboard.html').toLowerCase();
  const isPhp = path.endsWith('.php');
  const ext = isPhp ? 'php' : 'html';

  const inferPage = () => {
    const explicit = body.dataset.page;
    if (explicit) return explicit;
    if (path.includes('application-status')) return 'application-status';
    if (path.includes('profile')) return 'profile';
    if (path.includes('task') || path.includes('bpl') || path.includes('pkk') || path.includes('tea')) return 'task';
    return 'dashboard';
  };

  const activePage = inferPage();
  const file = (name) => name === 'logout'
    ? (isPhp ? 'logout.php' : 'index.html')
    : `${name}.${ext}`;

  const sidebarItems = [
    { key: 'dashboard', label: 'Dashboard', icon: 'bi-grid-1x2-fill', href: file('dashboard') },
    { key: 'profile', label: 'Profile', icon: 'bi-person', href: file('profile') },
    { key: 'task', label: 'Tasks', icon: 'bi-check2-square', href: file('task') },
    { key: 'application-status', label: 'Application status', icon: 'bi-clipboard-check', href: file('application-status') },
    { key: 'submissions', label: 'Submissions', icon: 'bi-inbox', href: '#', disabled: true }
  ];

  const sidebarHtml = sidebarItems.map(item => {
    const active = item.key === activePage ? ' active' : '';
    const disabled = item.disabled ? ' is-disabled' : '';
    const attrs = item.disabled ? ' aria-disabled="true" tabindex="-1"' : '';
    return `<a class="sedco-nav-item${active}${disabled}" href="${item.href}"${attrs}>
      <i class="bi ${item.icon}"></i><span>${item.label}</span>
    </a>`;
  }).join('');

  const shell = `
    <header class="navbar sedco-navbar">
      <div class="sedco-navbar-inner">
        <div class="sedco-brand-wrap">
          <button class="sedco-mobile-toggle" id="sedcoMobileToggle" type="button" aria-label="Open navigation">
            <i class="bi bi-list"></i>
          </button>
          <a class="navbar-brand sedco-brand" href="${file('dashboard')}">
            <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
            <span>TRAINING MANAGEMENT SYSTEM</span>
          </a>
        </div>
        <nav class="sedco-top-links" aria-label="Top navigation">
          <a class="nav-link" href="${file('dashboard')}">Dashboard</a>
          <a class="nav-link" href="#">About Us</a>
          <a class="nav-link" href="#">Contact Us</a>
          <a class="nav-link" href="${file('logout')}"><i class="bi bi-box-arrow-right me-1"></i>Log out</a>
        </nav>
      </div>
    </header>

    <aside class="sidebar sedco-sidebar" id="sedcoSidebar">
      <div class="sedco-sidebar-label">Workspace</div>
      <nav class="sedco-side-nav" aria-label="Sidebar navigation">
        ${sidebarHtml}
      </nav>
      <div class="sedco-sidebar-footer">
        <a class="sedco-nav-item" href="${file('logout')}">
          <i class="bi bi-box-arrow-left"></i><span>Logout</span>
        </a>
      </div>
    </aside>
    <button class="sedco-sidebar-backdrop" id="sedcoSidebarBackdrop" type="button" aria-label="Close navigation"></button>
  `;

  document.body.insertAdjacentHTML('afterbegin', shell);

  document.querySelectorAll('.sedco-nav-item.is-disabled').forEach(link => {
    link.addEventListener('click', event => event.preventDefault());
  });

  const toggle = document.getElementById('sedcoMobileToggle');
  const backdrop = document.getElementById('sedcoSidebarBackdrop');
  const closeMenu = () => body.classList.remove('sedco-menu-open');

  toggle?.addEventListener('click', () => body.classList.toggle('sedco-menu-open'));
  backdrop?.addEventListener('click', closeMenu);

  window.addEventListener('resize', () => {
    if (window.innerWidth > 760) closeMenu();
  });
})();