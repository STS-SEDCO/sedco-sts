(() => {
  let currentDate = new Date();
  let selectedKey = null;
  const events = Array.isArray(window.STS_CALENDAR_EVENTS) ? window.STS_CALENDAR_EVENTS : [];

  function dateKey(year, month, day) {
    return [
      year,
      String(month + 1).padStart(2,'0'),
      String(day).padStart(2,'0')
    ].join('-');
  }

  function keyForDate(date) {
    return dateKey(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function dateFromKey(key) {
    const [year, month, day] = String(key || '').split('-').map(Number);
    return new Date(year, (month || 1) - 1, day || 1);
  }

  function eventTone(event) {
    const type = String(event?.type || '').toLowerCase();
    const kind = String(event?.kind || '').toLowerCase();

    if (type === 'training' || kind === 'training') return 'training';
    if (type.includes('holiday')) return 'other';
    if (type.includes('birthday')) return 'other';
    if (kind === 'company' || type === 'company' || type === 'event') return 'company';
    return 'other';
  }

  function eventIcon(event) {
    const tone = eventTone(event);
    if (tone === 'training') return 'bi-mortarboard';
    if (tone === 'company') return 'bi-building';
    return 'bi-calendar-event';
  }

  function eventsForDate(key) {
    const target = new Date(key + 'T00:00:00');

    return events.filter(event => {
      const start = new Date(String(event.date) + 'T00:00:00');
      const end = new Date(String(event.endDate || event.date) + 'T23:59:59');
      return target >= start && target <= end;
    });
  }

  function monthEvents(year, month) {
    const monthStart = new Date(year, month, 1);
    const monthEnd = new Date(year, month + 1, 0, 23, 59, 59);

    return events.filter(event => {
      const start = new Date(String(event.date) + 'T00:00:00');
      const end = new Date(String(event.endDate || event.date) + 'T23:59:59');
      return start <= monthEnd && end >= monthStart;
    });
  }

  function renderSelectedDay(key) {
    const target = document.getElementById('selected-date');
    if (!target) return;

    const date = dateFromKey(key);
    const dayEvents = eventsForDate(key);
    const dayName = date.toLocaleDateString('en-MY',{weekday:'long'});
    const fullDate = date.toLocaleDateString('en-MY',{day:'numeric',month:'long',year:'numeric'});
    const countLabel = dayEvents.length;

    const list = dayEvents.length
      ? `<div class="dashboard-selected-events">${dayEvents.map(event => {
          const tone = eventTone(event);
          const url = event.url && event.url !== '#' ? String(event.url) : '';
          const inner = `
            <span class="dashboard-selected-event-icon tone-${tone}"><i class="bi ${eventIcon(event)}"></i></span>
            <div>
              <strong>${escapeHtml(event.title)}</strong>
              <p>${event.ref ? escapeHtml(event.ref) + ' · ' : ''}${escapeHtml(event.department || (tone === 'training' ? 'Training' : 'SEDCO'))}</p>
            </div>
            ${url ? '<i class="bi bi-arrow-up-right"></i>' : ''}
          `;
          return url
            ? `<a class="dashboard-selected-event tone-${tone}" href="${escapeHtml(url)}">${inner}</a>`
            : `<div class="dashboard-selected-event tone-${tone}">${inner}</div>`;
        }).join('')}</div>`
      : `<div class="dashboard-selected-empty">
          <span><i class="bi bi-calendar2"></i></span>
          <strong>Nothing scheduled</strong>
          <p>This day is clear. Training and company events will appear here automatically.</p>
        </div>`;

    target.innerHTML = `
      <div class="dashboard-selected-day-head">
        <span class="dashboard-selected-day-icon"><i class="bi bi-calendar3"></i></span>
        <div>
          <small>Selected day</small>
          <strong>${escapeHtml(dayName)}</strong>
          <p>${escapeHtml(fullDate)}</p>
        </div>
        <em>${countLabel}</em>
      </div>
      <a class="dashboard-selected-day-action" href="${document.body.classList.contains('dashboard-v4') && location.pathname.endsWith('.html') ? 'training-calendar.html' : 'training-calendar.php'}">
        <i class="bi bi-calendar3"></i> Open full calendar
      </a>
      <div class="dashboard-selected-day-body">${list}</div>
    `;
  }

  function generateCalendar() {
    const header = document.getElementById('calendar-header');
    const grid = document.getElementById('calendar');
    const monthLabel = document.getElementById('calendar-month-label');
    const monthCount = document.getElementById('calendar-month-count');
    if (!header || !grid || !monthLabel || !monthCount) return;

    const year = currentDate.getFullYear();
    const monthIndex = currentDate.getMonth();
    const today = new Date();
    const todayKey = keyForDate(today);
    const monthName = currentDate.toLocaleString('en-MY',{month:'long'});
    const items = monthEvents(year, monthIndex);

    monthLabel.innerHTML = `${escapeHtml(monthName)} <span>${year}</span>`;
    monthCount.textContent = `${items.length} scheduled item${items.length === 1 ? '' : 's'} this month`;

    header.innerHTML = `
      <button type="button" data-cal-prev aria-label="Previous month"><i class="bi bi-chevron-left"></i></button>
      <button type="button" class="dashboard-calendar-today" data-cal-today>Today</button>
      <button type="button" data-cal-next aria-label="Next month"><i class="bi bi-chevron-right"></i></button>
    `;

    const firstOfMonth = new Date(year, monthIndex, 1);
    const mondayOffset = (firstOfMonth.getDay() + 6) % 7;
    const gridStart = new Date(year, monthIndex, 1 - mondayOffset);
    const selectedDate = selectedKey ? dateFromKey(selectedKey) : today;

    if (
      !selectedKey ||
      selectedDate.getFullYear() !== year ||
      selectedDate.getMonth() !== monthIndex
    ) {
      selectedKey = year === today.getFullYear() && monthIndex === today.getMonth()
        ? todayKey
        : dateKey(year, monthIndex, 1);
    }

    const cells = [];

    for (let index = 0; index < 42; index++) {
      const cellDate = new Date(gridStart);
      cellDate.setDate(gridStart.getDate() + index);

      const key = keyForDate(cellDate);
      const inMonth = cellDate.getMonth() === monthIndex;
      const isToday = key === todayKey;
      const isSelected = key === selectedKey;
      const dayEvents = inMonth ? eventsForDate(key) : [];
      const visibleEvents = dayEvents.slice(0,2);
      const extraCount = Math.max(0, dayEvents.length - visibleEvents.length);

      const eventMarkup = visibleEvents.map(event => {
        const tone = eventTone(event);
        return `<span class="dashboard-calendar-event tone-${tone}" title="${escapeHtml(event.title)}">
          <i></i><b>${escapeHtml(event.title)}</b>
        </span>`;
      }).join('');

      cells.push(`
        <button type="button"
          class="dashboard-calendar-day${inMonth ? '' : ' is-outside'}${isToday ? ' is-today' : ''}${isSelected ? ' is-selected' : ''}${dayEvents.length ? ' has-event' : ''}"
          data-calendar-date="${key}"
          aria-label="${escapeHtml(cellDate.toLocaleDateString('en-MY',{day:'numeric',month:'long',year:'numeric'}))}">
          <span class="dashboard-calendar-day-number">${cellDate.getDate()}</span>
          <span class="dashboard-calendar-day-events">${eventMarkup}${extraCount ? `<small>+${extraCount} more</small>` : ''}</span>
        </button>
      `);
    }

    grid.innerHTML = cells.join('');

    header.querySelector('[data-cal-prev]')?.addEventListener('click',() => {
      currentDate = new Date(year, monthIndex - 1, 1);
      selectedKey = dateKey(currentDate.getFullYear(), currentDate.getMonth(), 1);
      generateCalendar();
    });

    header.querySelector('[data-cal-next]')?.addEventListener('click',() => {
      currentDate = new Date(year, monthIndex + 1, 1);
      selectedKey = dateKey(currentDate.getFullYear(), currentDate.getMonth(), 1);
      generateCalendar();
    });

    header.querySelector('[data-cal-today]')?.addEventListener('click',() => {
      currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
      selectedKey = todayKey;
      generateCalendar();
    });

    grid.querySelectorAll('[data-calendar-date]').forEach(cell => {
      cell.addEventListener('click',() => {
        const key = cell.dataset.calendarDate;
        const clickedDate = dateFromKey(key);

        if (clickedDate.getMonth() !== currentDate.getMonth() || clickedDate.getFullYear() !== currentDate.getFullYear()) {
          currentDate = new Date(clickedDate.getFullYear(), clickedDate.getMonth(), 1);
        }

        selectedKey = key;
        generateCalendar();
      });
    });

    renderSelectedDay(selectedKey);
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