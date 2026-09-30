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

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');
  }

  document.addEventListener('DOMContentLoaded',generateCalendar);
})();