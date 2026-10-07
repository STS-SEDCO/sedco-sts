(() => {
  let currentDate = new Date();
  const events = Array.isArray(window.STS_CALENDAR_EVENTS) ? window.STS_CALENDAR_EVENTS : [];

  function dateKey(year, month, day) {
    return [
      year,
      String(month + 1).padStart(2,'0'),
      String(day).padStart(2,'0')
    ].join('-');
  }

  function eventsForDate(key) {
    const target = new Date(key + 'T00:00:00');

    return events.filter(event => {
      const start = new Date(String(event.date) + 'T00:00:00');
      const end = new Date(String(event.endDate || event.date) + 'T23:59:59');
      return target >= start && target <= end;
    });
  }

  function generateCalendar() {
    const header = document.getElementById('calendar-header');
    const table = document.getElementById('calendar');
    if (!header || !table) return;

    const month = currentDate.toLocaleString('en-MY',{month:'long'});
    const year = currentDate.getFullYear();
    const today = new Date();
    const firstDay = new Date(year,currentDate.getMonth(),1).getDay();
    const lastDate = new Date(year,currentDate.getMonth()+1,0).getDate();

    header.innerHTML = `
      <button type="button" data-cal-prev aria-label="Previous month"><i class="bi bi-chevron-left"></i></button>
      <span>${month} ${year}</span>
      <button type="button" data-cal-next aria-label="Next month"><i class="bi bi-chevron-right"></i></button>
    `;

    let html = '<tr>' + ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(day => `<th>${day}</th>`).join('') + '</tr><tr>';

    for (let i=0;i<firstDay;i++) html += '<td></td>';

    for (let day=1;day<=lastDate;day++) {
      if ((firstDay + day - 1) % 7 === 0 && day !== 1) html += '</tr><tr>';

      const key = dateKey(year,currentDate.getMonth(),day);
      const isToday =
        day === today.getDate() &&
        currentDate.getMonth() === today.getMonth() &&
        year === today.getFullYear();
      const dayEvents = eventsForDate(key);
      const classes = [
        isToday ? 'today' : '',
        dayEvents.length ? 'has-event' : ''
      ].filter(Boolean).join(' ');

      html += `<td class="${classes}" data-calendar-date="${key}">
        <span>${day}</span>
        ${dayEvents.length ? '<i></i>' : ''}
      </td>`;
    }

    html += '</tr>';
    table.innerHTML = html;

    header.querySelector('[data-cal-prev]')?.addEventListener('click',() => {
      currentDate.setMonth(currentDate.getMonth()-1);
      generateCalendar();
    });
    header.querySelector('[data-cal-next]')?.addEventListener('click',() => {
      currentDate.setMonth(currentDate.getMonth()+1);
      generateCalendar();
    });

    table.querySelectorAll('[data-calendar-date]').forEach(cell => {
      cell.addEventListener('click',() => selectDate(cell));
    });
  }

  function selectDate(cell) {
    document.querySelectorAll('[data-calendar-date]').forEach(item => item.classList.remove('selected-date'));
    cell.classList.add('selected-date');

    const target = document.getElementById('selected-date');
    const key = cell.dataset.calendarDate;
    const dayEvents = eventsForDate(key);

    if (!dayEvents.length) {
      target.innerHTML = '<span>No training or event scheduled for this date.</span>';
      return;
    }

    target.innerHTML = dayEvents.map(event => `
      <div class="calendar-event-row">
        <i class="bi ${event.type === 'training' ? 'bi-mortarboard' : 'bi-calendar-event'}"></i>
        <span><strong>${escapeHtml(event.title)}</strong>${event.ref ? '<small>'+escapeHtml(event.ref)+'</small>' : ''}</span>
      </div>
    `).join('');
  }

  function loadPreviewApplications() {
    if (window.STS_DASHBOARD_STATIC !== true) return [];

    let previewUser = null;
    let stored = [];

    try {
      previewUser = JSON.parse(localStorage.getItem('sedcoPreviewUser') || 'null');
    } catch {
      previewUser = null;
    }

    try {
      const raw = JSON.parse(localStorage.getItem('sedcoApplications') || '[]');
      stored = Array.isArray(raw) ? raw : [];
    } catch {
      stored = [];
    }

    const email = String(previewUser?.email || '').trim().toLowerCase();
    if (!email) return [];

    return stored
      .filter(item => String(item.ownerEmail || '').trim().toLowerCase() === email)
      .sort((a,b) => new Date(b.submittedAt || 0) - new Date(a.submittedAt || 0));
  }

  function previewStageLabel(stage) {
    switch (String(stage || '').toLowerCase()) {
      case 'hod': return 'Head of Department';
      case 'training': return 'Training Department';
      case 'gm': return 'General Manager';
      case 'chairman': return 'Pengerusi';
      case 'finance': return 'Kewangan';
      case 'completed': return 'Completed';
      default: return 'Pending';
    }
  }

  function previewStatusLabel(status) {
    switch (String(status || '').toLowerCase()) {
      case 'approved': return 'Approved';
      case 'rejected': return 'Rejected';
      case 'correction': return 'Needs correction';
      case 'cancelled': return 'Cancelled';
      default: return 'Pending review';
    }
  }

  function renderPreviewActions(applications) {
    const list = document.getElementById('dashboardPreviewActions');
    const empty = document.getElementById('dashboardPreviewActionEmpty');
    const count = document.getElementById('dashboardPreviewActionCount');
    if (!list || !empty || !count) return;

    const actions = [];

    applications
      .filter(item => String(item.status || '').toLowerCase() === 'correction')
      .slice(0,3)
      .forEach(item => {
        actions.push({
          tone:'danger',
          icon:'bi-arrow-counterclockwise',
          title:'Correction required',
          copy:(item.id || '') + ' · ' + (item.title || item.formName || 'Application'),
          url:'application-status.html',
          action:'Fix & Resubmit'
        });
      });

    applications
      .filter(item => String(item.status || '').toLowerCase() === 'pending')
      .slice(0,2)
      .forEach(item => {
        actions.push({
          tone:'warning',
          icon:'bi-clock-history',
          title:'Approval in progress',
          copy:(item.id || '') + ' · Waiting at ' + previewStageLabel(item.currentStage),
          url:'application-status.html',
          action:'Track'
        });
      });

    count.textContent = actions.length + (actions.length === 1 ? ' action' : ' actions');
    empty.hidden = actions.length > 0;
    list.hidden = actions.length === 0;

    list.innerHTML = actions.map(item => `
      <a class="dashboard-action-item tone-${item.tone}" href="${item.url}">
        <span class="dashboard-action-icon"><i class="bi ${item.icon}"></i></span>
        <div><strong>${escapeHtml(item.title)}</strong><p>${escapeHtml(item.copy)}</p></div>
        <em>${escapeHtml(item.action)} <i class="bi bi-arrow-up-right"></i></em>
      </a>
    `).join('');
  }

  function renderPreviewDashboard() {
    if (window.STS_DASHBOARD_STATIC !== true) return;

    const applications = loadPreviewApplications();
    const total = applications.length;
    const pending = applications.filter(item => (item.status || 'pending') === 'pending').length;
    const approved = applications.filter(item => item.status === 'approved').length;
    const attention = applications.filter(item => ['correction','rejected'].includes(item.status)).length;

    const setText = (id, value) => {
      const node = document.getElementById(id);
      if (node) node.textContent = String(value);
    };

    setText('dashboardStatApplications', total);
    setText('dashboardStatPending', pending);
    setText('dashboardStatApproved', approved);
    setText('dashboardStatAttention', attention);
    renderPreviewActions(applications);

    const queue = document.getElementById('dashboardPreviewQueue');
    const empty = document.getElementById('dashboardPreviewEmpty');
    if (!queue || !empty) return;

    const rows = applications.slice(0, 6);

    if (!rows.length) {
      queue.innerHTML = '';
      empty.classList.remove('d-none');
      return;
    }

    empty.classList.add('d-none');

    queue.innerHTML = rows.map(item => {
      const status = String(item.status || 'pending');
      const stage = item.stageLabel || previewStageLabel(item.currentStage);
      const date = item.submittedAt ? new Date(item.submittedAt) : null;
      const when = date && !Number.isNaN(date.getTime())
        ? new Intl.DateTimeFormat('en-MY',{day:'2-digit',month:'short',year:'numeric'}).format(date)
        : '';

      return `
        <a class="dashboard-queue-item" href="application-status.html">
          <span class="dashboard-queue-icon"><i class="bi bi-file-earmark-text"></i></span>
          <div class="dashboard-queue-copy">
            <div>
              <strong>${escapeHtml(item.title || item.formName || 'Application')}</strong>
              <span>${escapeHtml(item.id || '')}</span>
            </div>
            <p>
              ${escapeHtml(item.type || 'FORM')} ·
              ${escapeHtml(previewStatusLabel(status))} ·
              ${escapeHtml(stage)}
              ${when ? ' · ' + escapeHtml(when) : ''}
            </p>
          </div>
          <span class="dashboard-queue-meta"><i class="bi bi-chevron-right"></i></span>
        </a>
      `;
    }).join('');
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');
  }

  document.addEventListener('DOMContentLoaded', () => {
    generateCalendar();
    renderPreviewDashboard();
  });
})();