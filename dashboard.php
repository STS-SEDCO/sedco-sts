<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Training Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="sedco-saas.css?v=20260930-9">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-9">
</head>
<body class="app-page dashboard-page" data-page="dashboard">

    <main class="content">
        <div class="dashboard-shell">
            <div class="page-heading">
                <div>
                    <div class="eyebrow">Dashboard</div>
                    <h1 class="welcome-message">Welcome, <?= e($user['fullname']) ?>!</h1>
                    <p class="page-subtitle">Here is an overview of your training tasks and schedule.</p>
                </div>
            </div>

            <div class="dashboard-grid">
                <section class="tasks-panel">
                    <div class="panel-heading">
                        <h2 class="panel-title">My tasks</h2>
                        <span class="panel-meta">2 tasks</span>
                    </div>

                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchBar" class="form-control" placeholder="Search tasks..." onkeyup="filterTasks()">
                    </div>

                    <div id="taskList">
                        <div class="task-card overdue">
                            <span class="task-date">Monday, 17 March 2025</span>
                            <div class="task-title danger">
                                Submission of Log Book
                                <span class="status-badge">Overdue</span>
                            </div>
                            <p>BORANG PENILAIAN KEBERKESANAN KURSUS IS DUE</p>
                        </div>

                        <div class="task-card upcoming">
                            <span class="task-date">Thursday, 20 March 2025</span>
                            <div class="task-title info">Submission of LI Report</div>
                            <p>KD44212 LATIHAN INDUSTRI [1-2024/2025]: Assignment is due</p>
                        </div>
                    </div>
                </section>

                <aside class="calendar-container">
                    <div class="calendar-topline">
                        <div>
                            <h5>Calendar</h5>
                            <span>Plan your schedule</span>
                        </div>
                        <i class="bi bi-calendar3" style="color:#8f1010;font-size:17px;"></i>
                    </div>
                    <div class="calendar-header" id="calendar-header"></div>
                    <table class="calendar-table" id="calendar"></table>
                    <div id="selected-date"></div>
                    <button class="reset-btn mt-2" onclick="resetCalendar()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset calendar
                    </button>
                </aside>
            </div>
        </div>
    </main>

    <script>
        let currentDate = new Date();

        function changeMonth(direction) {
            currentDate.setMonth(currentDate.getMonth() + direction);
            generateCalendar();
        }

        function generateCalendar() {
            let month = currentDate.toLocaleString('default', { month: 'long' });
            let year = currentDate.getFullYear();
            let today = new Date();
            let firstDay = new Date(year, currentDate.getMonth(), 1).getDay();
            let lastDate = new Date(year, currentDate.getMonth() + 1, 0).getDate();

            document.getElementById("calendar-header").innerHTML = `
                <span onclick="changeMonth(-1)"><i class="bi bi-chevron-left"></i></span>
                <span>${month} ${year}</span>
                <span onclick="changeMonth(1)"><i class="bi bi-chevron-right"></i></span>
            `;

            let calendarHTML = "<tr>";
            let weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
            calendarHTML += weekdays.map(day => `<th>${day}</th>`).join("") + "</tr><tr>";

            for (let i = 0; i < firstDay; i++) {
                calendarHTML += "<td></td>";
            }

            for (let date = 1; date <= lastDate; date++) {
                if ((firstDay + date - 1) % 7 === 0) calendarHTML += "</tr><tr>";
                let isToday = (date === today.getDate() && currentDate.getMonth() === today.getMonth() && currentDate.getFullYear() === today.getFullYear()) ? "today" : "";
                calendarHTML += `<td class="${isToday}" onclick="selectDate(this, ${date})">${date}</td>`;
            }

            calendarHTML += "</tr>";
            document.getElementById("calendar").innerHTML = calendarHTML;
        }

        function selectDate(element, date) {
            document.querySelectorAll(".calendar-table td").forEach(td => td.classList.remove("selected-date"));
            element.classList.add("selected-date");
            document.getElementById("selected-date").innerText = `Selected Date: ${date} ${document.getElementById("calendar-header").querySelector("span:nth-child(2)").innerText}`;
        }

        function resetCalendar() {
            currentDate = new Date();
            generateCalendar();
            document.getElementById("selected-date").innerText = "";
        }

        generateCalendar();

        function filterTasks() {
            const input = document.getElementById("searchBar").value.toLowerCase();
            const taskCards = document.querySelectorAll("#taskList .task-card");

            taskCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(input) ? "" : "none";
            });
        }
    </script>
<script src="sedco-shell.js?v=20260930-9"></script>
</body>
</html>