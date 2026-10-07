(() => {
  const $ = id => document.getElementById(id);

  let cursor = new Date();
  cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);

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

  function normalizedRows() {
    return scopeRows(readApps())
      .filter(row => String(row.type || row.form_type || '').toUpperCase() === 'BPL')
      .filter(row => String(row.status || '').toLowerCase() === 'approved')
      .map(row => ({
        ref:String(row.id || row.application_no || ''),
        title:String(row.title || row.data?.tajuk || row.data?.kursus || 'Training'),
        employee:String(row.applicant || row.ownerEmail || 'Applicant'),
        department:String(row.department || row.data?.bahagian || 'Unassigned'),
        start:dateOnly(row.trainingStart || row.training_start || row.data?.tarikh_mula),
        end:dateOnly(row.trainingEnd || row.training_end || row.data?.tarikh_tamat)
      }))
      .filter(row => row.start && row.end);
  }

  function monthKey(date) {
    return date.getFullYear() + '-' + String(date.getMonth()+1).padStart(2,'0');
  }

  function toDate(value) {
    const [y,m,d] = String(value).split('-').map(Number);
    return new Date(y,m-1,d);
  }

  function visibleRows() {
    const department = $('calendarDepartment')?.value || '';
    const query = String($('calendarSearch')?.value || '').trim().toLowerCase();
    const start = new Date(cursor.getFullYear(),cursor.getMonth(),1);
    const end = new Date(cursor.getFullYear(),cursor.getMonth()+1,1);

    return normalizedRows().filter(row => {
      const rowStart = toDate(row.start);
      const rowEnd = toDate(row.end);
      if (!(rowStart < end && rowEnd >= start)) return false;
      if (department && row.department !== department) return false;
      if (query && ![row.ref,row.title,row.employee,row.department].some(value => value.toLowerCase().includes(query))) return false;
      return true;
    });
  }

  function renderDepartments() {
    const select = $('calendarDepartment');
    if (!select || select.dataset.ready === '1') return;

    const user = readUser();
    const role = String(user?.role || '').toLowerCase();
    if (role === 'staff' || ['head_of_department','head_of_division'].includes(role)) {
      select.closest('.training-calendar-filter').hidden = true;
      select.dataset.ready = '1';
      return;
    }

    [...new Set(normalizedRows().map(row => row.department).filter(Boolean))]
      .sort()
      .forEach(department => {
        const option = document.createElement('option');
        option.value = department;
        option.textContent = department;
        select.appendChild(option);
      });

    select.dataset.ready = '1';
  }

  function render() {
    renderDepartments();
    $('calendarMonth').value = monthKey(cursor);
    $('calendarTitle').textContent = cursor.toLocaleString('en-MY',{month:'long'});
    $('calendarYear').textContent = String(cursor.getFullYear());

    const rows = visibleRows();
    $('calendarTrainingCount').textContent = String(rows.length);
    $('calendarEmployeeCount').textContent = String(new Set(rows.map(row => row.employee)).size);
    $('calendarDepartmentCount').textContent = String(new Set(rows.map(row => row.department)).size);
    $('calendarRecordCount').textContent = rows.length + (rows.length === 1 ? ' record' : ' records');

    const eventsByDay = {};
    const monthStart = new Date(cursor.getFullYear(),cursor.getMonth(),1);
    const monthEnd = new Date(cursor.getFullYear(),cursor.getMonth()+1,1);

    rows.forEach(row => {
      let date = toDate(row.start);
      const end = toDate(row.end);
      while (date <= end) {
        if (date >= monthStart && date < monthEnd) {
          const key = [
            date.getFullYear(),
            String(date.getMonth()+1).padStart(2,'0'),
            String(date.getDate()).padStart(2,'0')
          ].join('-');
          (eventsByDay[key] ||= []).push(row);
        }
        date = new Date(date.getFullYear(),date.getMonth(),date.getDate()+1);
      }
    });

    const firstDow = (monthStart.getDay()+6)%7;
    const gridStart = new Date(monthStart.getFullYear(),monthStart.getMonth(),1-firstDow);
    const lastDay = new Date(cursor.getFullYear(),cursor.getMonth()+1,0);
    const endDow = (lastDay.getDay()+6)%7;
    const gridEnd = new Date(lastDay.getFullYear(),lastDay.getMonth(),lastDay.getDate()+(6-endDow)+1);
    const today = dateOnly(new Date());

    const cells=[];
    for(let date=new Date(gridStart); date<gridEnd; date=new Date(date.getFullYear(),date.getMonth(),date.getDate()+1)){
      const key=[
        date.getFullYear(),
        String(date.getMonth()+1).padStart(2,'0'),
        String(date.getDate()).padStart(2,'0')
      ].join('-');
      const outside=date.getMonth()!==cursor.getMonth();
      const events=eventsByDay[key]||[];

      cells.push(`
        <article class="training-calendar-day${outside?' is-outside':''}${key===today?' is-today':''}">
          <div class="training-calendar-day-head"><span>${date.getDate()}</span>${key===today?'<em>Today</em>':''}</div>
          <div class="training-calendar-day-events">
            ${events.slice(0,3).map(event=>`
              <a class="training-calendar-event is-training" href="application-status.html" title="${escapeHtml(event.title+' · '+event.employee)}">
                <strong>${escapeHtml(event.title)}</strong>
                <small>${escapeHtml(event.employee)}</small>
              </a>
            `).join('')}
            ${events.length>3?'<span class="training-calendar-more">+'+(events.length-3)+' more</span>':''}
          </div>
        </article>
      `);
    }

    $('calendarGrid').innerHTML=cells.join('');

    $('calendarList').innerHTML=rows.map(row=>`
      <a href="application-status.html">
        <span class="training-calendar-list-date"><strong>${escapeHtml(row.start.slice(8,10))}</strong><small>${toDate(row.start).toLocaleString('en-MY',{month:'short'})}</small></span>
        <div><strong>${escapeHtml(row.title)}</strong><p>${escapeHtml(row.employee)} · ${escapeHtml(row.department)}</p></div>
        <em>${escapeHtml(row.start)} → ${escapeHtml(row.end)}</em>
        <i class="bi bi-chevron-right"></i>
      </a>
    `).join('');

    $('calendarList').hidden=rows.length===0;
    $('calendarEmpty').hidden=rows.length!==0;
  }

  $('calendarPrev')?.addEventListener('click',()=>{cursor=new Date(cursor.getFullYear(),cursor.getMonth()-1,1);render();});
  $('calendarNext')?.addEventListener('click',()=>{cursor=new Date(cursor.getFullYear(),cursor.getMonth()+1,1);render();});
  $('calendarMonth')?.addEventListener('change',event=>{
    const [y,m]=String(event.target.value).split('-').map(Number);
    if(y&&m) cursor=new Date(y,m-1,1);
    render();
  });
  $('calendarDepartment')?.addEventListener('change',render);
  $('calendarSearch')?.addEventListener('input',render);
  $('calendarReset')?.addEventListener('click',()=>{
    if($('calendarDepartment')) $('calendarDepartment').value='';
    if($('calendarSearch')) $('calendarSearch').value='';
    cursor=new Date(new Date().getFullYear(),new Date().getMonth(),1);
    render();
  });

  render();
})();