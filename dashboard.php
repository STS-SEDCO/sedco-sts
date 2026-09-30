<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
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
    <style>
        :root {
            --brand: #8f1010;
            --brand-dark: #681010;
            --brand-soft: #fff2f2;
            --bg: #f6f7f9;
            --surface: #ffffff;
            --surface-2: #fafafa;
            --text: #171717;
            --muted: #737373;
            --border: #e8e8e8;
            --shadow-sm: 0 1px 2px rgba(0,0,0,.04), 0 1px 3px rgba(0,0,0,.06);
            --shadow-md: 0 12px 32px rgba(20,20,20,.07);
        }

        * { box-sizing: border-box; }

        html, body { min-height: 100%; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .navbar {
            height: 72px;
            background: rgba(255,255,255,.96);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0 28px;
            position: fixed;
            inset: 0 0 auto 0;
            z-index: 1000;
            box-shadow: none;
        }

        .navbar .container-fluid { height: 100%; }

        .navbar-brand {
            color: var(--brand-dark) !important;
            font-size: 17px;
            font-weight: 700 !important;
            letter-spacing: -.01em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-mark {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--brand);
            color: #fff;
            box-shadow: 0 6px 16px rgba(143,16,16,.18);
        }

        .navbar .nav-link {
            color: #525252 !important;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 12px !important;
            margin: 0 2px;
            border-radius: 9px;
            transition: .18s ease;
        }

        .navbar .nav-link:hover {
            color: var(--brand) !important;
            background: var(--brand-soft);
            text-decoration: none;
        }

        .sidebar {
            position: fixed;
            top: 72px;
            left: 0;
            width: 244px;
            height: calc(100vh - 72px);
            padding: 22px 14px;
            background: #741010;
            border-right: 1px solid rgba(255,255,255,.08);
            z-index: 900;
            overflow-y: auto;
        }

        .sidebar-title {
            color: rgba(255,255,255,.55);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .12em;
            font-weight: 700;
            padding: 0 12px 10px;
            margin: 0;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 44px;
            padding: 10px 12px;
            margin-bottom: 5px;
            border-radius: 10px;
            color: rgba(255,255,255,.82);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: .18s ease;
        }

        .sidebar a i {
            width: 18px;
            font-size: 17px;
            text-align: center;
            opacity: .9;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,.09);
            color: #fff;
        }

        .sidebar a.active {
            color: #fff;
            background: rgba(255,255,255,.14);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
        }

        .sidebar-footer {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,.1);
        }

        .content {
            margin-left: 244px;
            padding: 104px 28px 36px;
            width: calc(100% - 244px);
        }

        .dashboard-shell {
            max-width: 1440px;
            margin: 0 auto;
        }

        .page-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
        }

        .eyebrow {
            color: var(--brand);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        .welcome-message {
            margin: 0;
            color: var(--text);
            font-size: clamp(25px, 2vw, 34px);
            line-height: 1.15;
            font-weight: 700;
            letter-spacing: -.035em;
        }

        .page-subtitle {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 330px;
            gap: 22px;
            align-items: start;
        }

        .tasks-panel,
        .calendar-container {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
        }

        .tasks-panel {
            padding: 18px;
        }

        .panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .panel-title {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -.01em;
        }

        .panel-meta {
            color: var(--muted);
            font-size: 12px;
        }

        .search-wrap {
            position: relative;
            margin-bottom: 14px;
        }

        .search-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #a3a3a3;
            font-size: 14px;
            pointer-events: none;
        }

        #searchBar {
            height: 44px;
            padding: 0 14px 0 39px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--surface-2);
            color: var(--text);
            font-size: 13px;
            box-shadow: none;
        }

        #searchBar:focus {
            border-color: rgba(143,16,16,.35);
            box-shadow: 0 0 0 3px rgba(143,16,16,.08);
            background: #fff;
        }

        .task-card {
            position: relative;
            border: 1px solid var(--border) !important;
            border-radius: 13px !important;
            padding: 16px 16px 16px 18px !important;
            margin-bottom: 10px !important;
            background: #fff;
            box-shadow: none;
            overflow: hidden;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .task-card:last-child { margin-bottom: 0 !important; }

        .task-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 12px;
            bottom: 12px;
            width: 3px;
            border-radius: 0 999px 999px 0;
            background: #d4d4d4;
        }

        .task-card.overdue::before { background: #dc2626; }
        .task-card.upcoming::before { background: #2563eb; }

        .task-card:hover {
            transform: translateY(-1px);
            border-color: #dddddd !important;
            box-shadow: 0 8px 22px rgba(0,0,0,.05);
        }

        .task-date {
            display: block;
            margin-bottom: 8px;
            color: #404040;
            font-size: 12px;
            font-weight: 600;
        }

        .task-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: 700;
        }

        .task-title.danger { color: #b91c1c; }
        .task-title.info { color: #1d4ed8; }

        .task-card p {
            margin: 0;
            color: var(--muted);
            font-size: 12.5px;
            line-height: 1.55;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 7px;
            border-radius: 999px;
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fee2e2;
            font-size: 10px;
            font-weight: 700;
        }

        .calendar-container {
            width: 100%;
            max-width: none;
            padding: 16px;
            text-align: center;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .calendar-topline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            text-align: left;
        }

        .calendar-topline h5 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
        }

        .calendar-topline span {
            color: var(--muted);
            font-size: 11px;
        }

        .calendar-header {
            display: grid;
            grid-template-columns: 36px 1fr 36px;
            align-items: center;
            gap: 4px;
            margin-bottom: 12px;
            padding: 0;
            background: transparent;
            color: var(--text);
            border: 0;
            font-size: 14px;
            font-weight: 700;
        }

        .calendar-header span:first-child,
        .calendar-header span:last-child {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 34px;
            border-radius: 9px;
            color: #525252;
            background: var(--surface-2);
            border: 1px solid var(--border);
            font-size: 14px;
            transition: .18s ease;
        }

        .calendar-header span:first-child:hover,
        .calendar-header span:last-child:hover {
            background: var(--brand-soft);
            color: var(--brand);
            border-color: #f4cccc;
        }

        .calendar-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 3px;
            font-size: 11px;
            border: 0;
        }

        .calendar-table th,
        .calendar-table td {
            width: 14.28%;
            height: 34px;
            padding: 0;
            border: 0;
            border-radius: 8px;
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
        }

        .calendar-table th {
            background: transparent;
            color: #a3a3a3;
            font-size: 10px;
            font-weight: 700;
        }

        .calendar-table td {
            color: #525252;
            cursor: pointer;
            transition: .15s ease;
        }

        .calendar-table td:hover:not(:empty) {
            background: #f5f5f5;
            color: var(--text);
        }

        .today {
            background: var(--brand) !important;
            color: #fff !important;
            font-weight: 700;
            border: 0 !important;
            box-shadow: 0 4px 10px rgba(143,16,16,.18);
        }

        .selected-date {
            background: var(--brand-soft) !important;
            color: var(--brand) !important;
            font-weight: 700;
            border: 1px solid #f3caca !important;
        }

        .today.selected-date {
            background: var(--brand) !important;
            color: #fff !important;
            border: 0 !important;
        }

        #selected-date {
            min-height: 18px;
            margin-top: 10px !important;
            color: var(--muted);
            font-size: 11px;
            font-weight: 500 !important;
        }

        .reset-btn {
            height: 38px;
            padding: 0 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #fff;
            color: #525252;
            font-size: 12px;
            font-weight: 600;
            transition: .18s ease;
        }

        .reset-btn:hover {
            background: var(--brand-soft);
            color: var(--brand);
            border-color: #f3caca;
        }

        @media (max-width: 1100px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .calendar-container { max-width: 430px; }
        }

        @media (max-width: 820px) {
            .navbar { padding: 0 16px; }
            .navbar-nav { display: none; }
            .sidebar {
                width: 72px;
                padding: 18px 10px;
            }
            .sidebar-title { display: none; }
            .sidebar a {
                justify-content: center;
                padding: 10px;
            }
            .sidebar a span { display: none; }
            .sidebar a i { width: auto; font-size: 18px; }
            .content {
                margin-left: 72px;
                width: calc(100% - 72px);
                padding: 96px 16px 28px;
            }
        }

        @media (max-width: 560px) {
            .navbar-brand { font-size: 14px; }
            .brand-mark { width: 30px; height: 30px; }
            .sidebar { display: none; }
            .content {
                margin-left: 0;
                width: 100%;
                padding: 92px 14px 24px;
            }
            .tasks-panel, .calendar-container { border-radius: 14px; }
        }
    </style>
    <link rel="stylesheet" href="sedco-saas.css?v=20260930-3">
</head>
<body class="app-page dashboard-page" data-page="dashboard">

    <main class="content">
        <div class="dashboard-shell">
            <div class="page-heading">
                <div>
                    <div class="eyebrow">Dashboard</div>
                    <h1 class="welcome-message">Welcome, <?php echo isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Guest'; ?>!</h1>
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
<script src="sedco-shell.js?v=20260930-3"></script>
</body>
</html>