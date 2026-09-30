<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - Training Management System</title>
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

    <link rel="stylesheet" href="sedco-saas.css?v=20260930-9">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-9">
</head>
<body class="app-page task-page" data-page="task">

    <!-- Navbar -->
    <!-- Sidebar -->
    <!-- Cards -->
    <div class="card-container">
        <div class="card">
            <h5>PERMOHONAN LATIHAN (BPL)</h5>
            <a href="bpl.php" class="btn btn-warning">APPLY</a>
        </div>
        <div class="card">
            <h5>PENILAIAN KEBERKESANAN KURSUS</h5>
            <a href="pkk.php" class="btn btn-warning">APPLY</a>
        </div>
        <div class="card">
            <h5>TRAINING EFFECTIVENESS ASSESSMENT FORM</h5>
            <a href="tea.php" class="btn btn-warning">APPLY</a>
        </div>
    </div>

<script src="sedco-shell.js?v=20260930-9"></script>
</body>
</html>
   