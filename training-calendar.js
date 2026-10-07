(() => {
  const $ = id => document.getElementById(id);
  const staticMode = document.body.dataset.calendarStatic === '1';
  const serverItems = Array.isArray(window.STS_TRAINING_CALENDAR_ITEMS)
    ? window.STS_TRAINING_CALENDAR_ITEMS
    : null;

  const configuredMonth = document.body.dataset.calendarMonth || '';
  let cursor = configuredMonth && /^\d{4}-\d{2}$/.test(configuredMonth)
    ? new Date(Number(configuredMonth.slice(0,4)), Number(configuredMonth.slice(5,7)) - 1, 1)
    : new Date();
  cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
  let selectedKey = null;

  const escapeHtml = value => String(value ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'",'&#039;');

  function readUser() {
    try { return JSON.parse(localStorage.getItem('sedcoPreviewUser') || 'null'); }
    catch { return null; }
  }

  function readApps() {
    try {
      const rows = JSON.parse(localStorage.getItem('sedcoApplications') || '[]');
      return Array.isArray(rows) ? rows : [];
    } catch { return []; }
  }

  function dateOnly(value) {
    if (!value) return '';
    const raw = String(value);
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return '';
    return [
      date.getFullYear(),
      String(date.getMonth()+1).padStart(2,'0'),
      String(date.getDate()).padStart(2,'0')
    ].join('-');
  }

  function toDate(value) {
    const [y,m,d] = String(value).split('-').map(Number);
    return new Date(y,m-1,d);
  }

  function keyOf(date) {
    return [
      date.getFullYear(),
      String(date.getMonth()+1).padStart(2,'0'),
      String(date.getDate()).padStart(2,'0')
    ].join('-');
  }

  function scopeRows(rows) {
    const user = readUser();
    if (!user) return [];

    const role = String(user.role || '').toLowerCase();
    const email = String(user.email || '').toLowerCase();
    const department = String(user.department || '').toLowerCase();

    if (role === 'staff') {
      return rows.filter(row => String(row.ownerEmail || '').toLowerCase() === email);
    }

    if (['head_of_department','head_of_division'].includes(role) && department) {
      return rows.filter(row => String(row.department || row.data?.bahagian || '').toLowerCase() === department);
    }

    return rows;
  }

  function staticItems() {
    return scopeRows(readApps())
      .filter(row => String(row.type || row.form_type || '').toUpperCase() === 'BPL')
      .filter(row => String(row.status || '').toLowerCase() === 'approved')
      .map(row => ({
        kind:'training',
        ref:String(row.id || row.application_no || ''),
        title:String(row.title || row.data?.tajuk || row.data?.kursus || 'Training'),
        applicant:String(row.applicant || row.ownerEmail || 'Applicant'),
        department:String(row.department || row.data?.bahagian || 'Unassigned'),
        start:dateOnly(row.trainingStart || row.training_start || row.data?.tarikh_mula),
        end:dateOnly(row.trainingEnd || row.training_end || row.data?.tarikh_tamat),
        url:'application-status.html'
      }))
      .filter(row => row.start && row.end);
  }

  function allItems() {
    return serverItems || staticItems();
  }

  function visibleItems() {
    let rows = allItems();
    const start = new Date(cursor.getFullYear(),cursor.getMonth(),1);
    const end = new Date(cursor.getFullYear(),cursor.getMonth()+1,1);

    rows = rows.filter(row => {
      const rowStart = toDate(row.start);
      const rowEnd = toDate(row.end || row.start);
      return rowStart < end && rowEnd >= start;
    });

    if (staticMode) {
      const department = $('calendarDepartment')?.value || '';
      const query = String($('calendarSearch')?.value || '').trim().toLowerCase();

      rows = rows.filter(row => {
        if (department && row.department !== department) return false;
        if (query && ![row.ref,row.title,row.applicant,row.department].some(value => String(value || '').toLowerCase().includes(query))) return false;
        return true;
      });
    }

    return rows;
  }

  function renderDepartments() {
    if (!staticMode) return;
    const select = $('calendarDepartment');
    if (!select || select.dataset.ready === '1') return;

    const user = readUser();
    const role = String(user?.role || '').toLowerCase();
    if (role === 'staff' || ['head_of_department','head_of_division'].includes(role)) {
      select.closest('.training-calendar-filter')?.setAttribute('hidden','hidden');
      select.dataset.ready = '1';
      return;
    }

    [...new Set(staticItems().map(row => row.department).filter(Boolean))]
      .sort()
      .forEach(department => {
        const option = document.createElement('option');
        option.value = department;
        option.textContent = department;
        select.appendChild(option);
      });

    select.dataset.ready = '1';
  }

  function eventsByDay(items) {
    const map = {};
    const monthStart = new Date(cursor.getFullYear(),cursor.getMonth(),1);
    const monthEnd = new Date(cursor.getFullYear(),cursor.getMonth()+1,1);

    items.forEach(item => {
      let date = toDate(item.start);
      const end = toDate(item.end || item.start);

      while (date <= end) {
        if (date >= monthStart && date < monthEnd) {
          const key = keyOf(date);
          (map[key] ||= []).push(item);
        }
        date = new Date(date.getFullYear(),date.getMonth(),date.getDate()+1);
      }
    });

    return map;
  }

  function renderSelectedDay(key, map) {
    const panel = $('calendarSelectedDay');
    if (!panel) return;

    const date = toDate(key);
    const rows = map[key] || [];
    const dayName = date.toLocaleDateString('en-MY',{weekday:'long'});
    const fullDate = date.toLocaleDateString('en-MY',{day:'numeric',month:'long',year:'numeric'});

    const body = rows.length
      ? `<div class="training-selected-events">${rows.map(item => {
          const kind = item.kind === 'company' ? 'company' : 'training';
          const icon = kind === 'company' ? 'bi-building' : 'bi-mortarboard';
          const inner = `
            <span class="training-selected-event-icon tone-${kind}"><i class="bi ${icon}"></i></span>
            <div>
              <strong>${escapeHtml(item.title)}</strong>
              <p>${item.ref ? escapeHtml(item.ref) + ' · ' : ''}${escapeHtml(item.applicant || item.department || '')}</p>
            </div>
            ${item.url && item.url !== '#' ? '<i class="bi bi-arrow-up-right"></i>' : ''}
          `;
          return item.url && item.url !== '#'
            ? `<a class="training-selected-event" href="${escapeHtml(item.url)}">${inner}</a>`
            : `<div class="training-selected-event">${inner}</div>`;
        }).join('')}</div>`
      : `<div class="training-selected-empty">
          <span><i class="bi bi-calendar2"></i></span>
          <strong>Nothing scheduled</strong>
          <p>This day is clear. Approved training and company events will appear here automatically.</p>
        </div>`;

    panel.innerHTML = `
      <div class="training-selected-day-head">
        <span class="training-selected-day-icon"><i class="bi bi-calendar3"></i></span>
        <div>
          <small>Selected day</small>
          <strong>${escapeHtml(dayName)}</strong>
          <p>${escapeHtml(fullDate)}</p>
        </div>
        <em>${rows.length}</em>
      </div>
      <div class="training-selected-day-body">${body}</div>
    `;
  }

  function renderList(items) {
    const list = $('calendarList');
    const empty = $('calendarEmpty');
    const count = $('calendarRecordCount');

    if (!list || !empty || !count) return;

    const trainingRows = items.filter(item => item.kind !== 'company');
    count.textContent = trainingRows.length + (trainingRows.length === 1 ? ' record' : ' records');

    list.innerHTML = trainingRows.map(row => `
      <a href="${escapeHtml(row.url || 'application-status.html')}">
        <span class="training-calendar-list-date"><strong>${escapeHtml(row.start.slice(8,10))}</strong><small>${toDate(row.start).toLocaleString('en-MY',{month:'short'})}</small></span>
        <div><strong>${escapeHtml(row.title)}</strong><p>${escapeHtml(row.applicant)} · ${escapeHtml(row.department)}</p></div>
        <em>${escapeHtml(row.start)} → ${escapeHtml(row.end || row.start)}</em>
        <i class="bi bi-chevron-right"></i>
      </a>
    `).join('');

    list.hidden = trainingRows.length === 0;
    empty.hidden = trainingRows.length !== 0;
  }

  function render() {
    renderDepartments();

    const items = visibleItems();
    const map = eventsByDay(items);
    const title = $('calendarTitle');
    const count = $('calendarMonthCount');

    if (title) {
      title.innerHTML = `${escapeHtml(cursor.toLocaleString('en-MY',{month:'long'}))} <em>${cursor.getFullYear()}</em>`;
    }
    if (count) {
      count.textContent = items.length + (items.length === 1 ? ' scheduled item this month' : ' scheduled items this month');
    }

    const today = new Date();
    const todayKey = keyOf(today);
    const monthStart = new Date(cursor.getFullYear(),cursor.getMonth(),1);
    const firstDow = (monthStart.getDay()+6)%7;
    const gridStart = new Date(monthStart.getFullYear(),monthStart.getMonth(),1-firstDow);

    if (!selectedKey || toDate(selectedKey).getMonth() !== cursor.getMonth() || toDate(selectedKey).getFullYear() !== cursor.getFullYear()) {
      selectedKey = cursor.getMonth() === today.getMonth() && cursor.getFullYear() === today.getFullYear()
        ? todayKey
        : keyOf(monthStart);
    }

    const cells=[];
    for (let index=0; index<42; index++) {
      const date = new Date(gridStart);
      date.setDate(gridStart.getDate()+index);
      const key = keyOf(date);
      const outside = date.getMonth() !== cursor.getMonth();
      const rows = outside ? [] : (map[key] || []);
      const selected = key === selectedKey;
      const isToday = key === todayKey;

      const eventMarkup = rows.slice(0,2).map(item => {
        const kind = item.kind === 'company' ? 'company' : 'training';
        return `<span class="training-calendar-reference-event tone-${kind}" title="${escapeHtml(item.title)}">
          <i></i><b>${escapeHtml(item.title)}</b>
        </span>`;
      }).join('');

      cells.push(`
        <button type="button" class="training-calendar-reference-day${outside?' is-outside':''}${selected?' is-selected':''}${isToday?' is-today':''}" data-calendar-date="${key}">
          <span class="training-calendar-reference-day-number">${date.getDate()}</span>
          <span class="training-calendar-reference-day-events">
            ${eventMarkup}
            ${rows.length>2?'<small>+'+(rows.length-2)+' more</small>':''}
          </span>
        </button>
      `);
    }

    const grid = $('calendarGrid');
    if (grid) {
      grid.innerHTML = cells.join('');
      grid.querySelectorAll('[data-calendar-date]').forEach(cell => {
        cell.addEventListener('click',() => {
          const key = cell.dataset.calendarDate;
          const date = toDate(key);
          if (date.getMonth() !== cursor.getMonth() || date.getFullYear() !== cursor.getFullYear()) {
            if (!staticMode) return;
            cursor = new Date(date.getFullYear(),date.getMonth(),1);
          }
          selectedKey = key;
          render();
        });
      });
    }

    renderSelectedDay(selectedKey,map);
    if (staticMode) renderList(items);
  }

  $('calendarPrev')?.addEventListener('click',()=>{
    cursor=new Date(cursor.getFullYear(),cursor.getMonth()-1,1);
    selectedKey=null;
    render();
  });

  $('calendarNext')?.addEventListener('click',()=>{
    cursor=new Date(cursor.getFullYear(),cursor.getMonth()+1,1);
    selectedKey=null;
    render();
  });

  $('calendarToday')?.addEventListener('click',()=>{
    const now=new Date();
    cursor=new Date(now.getFullYear(),now.getMonth(),1);
    selectedKey=keyOf(now);
    render();
  });

  $('calendarDepartment')?.addEventListener('change',render);
  $('calendarSearch')?.addEventListener('input',render);
  $('calendarReset')?.addEventListener('click',()=>{
    if($('calendarDepartment')) $('calendarDepartment').value='';
    if($('calendarSearch')) $('calendarSearch').value='';
    const now=new Date();
    cursor=new Date(now.getFullYear(),now.getMonth(),1);
    selectedKey=keyOf(now);
    render();
  });

  render();
})();
