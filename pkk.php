<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Management System - Borang Penilaian</title>

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

    <link rel="stylesheet" href="sedco-saas.css?v=20260930-9">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-9">
</head>
<body class="app-page form-page pkk-page" data-page="task">

    </head>
<body>

<!-- Navbar -->
<!-- Sidebar -->
<!-- Main Content Area -->
<div class="main-content">
    <div class="container shadow-lg p-5 bg-white rounded-4">
        <h2 class="text-center mb-3 text-uppercase fw-bold">Borang Penilaian Keberkesanan Kursus / Seminar</h2>
        <p class="text-center text-muted mb-4"><em>Borang ini hendaklah diisi dalam masa tujuh (7) hari bekerja selepas menghadiri kursus/seminar.</em></p>

        <form method="post" action="submit_application.php?type=PKK">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Nama Pegawai/Staf</label>
                    <input type="text" class="form-control" name="nama">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Bahagian</label>
                    <input type="text" class="form-control" name="bahagian">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Jawatan</label>
                    <input type="text" class="form-control" name="jawatan">
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tajuk Kursus/Seminar</label>
                    <input type="text" class="form-control" name="tajuk">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tarikh / Hari</label>
                    <input type="date" class="form-control" name="tarikh">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tempat</label>
                    <input type="text" class="form-control" name="tempat">
                </div>
            </div>

            <hr class="my-4">

            <div class="mb-3">
            <div class="mb-3 page-break">
                <label class="form-label fw-semibold">1. Objektif menghadiri kursus:</label>
                <textarea class="form-control" name="objektif" rows="3"></textarea>
            </div>

            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">2. Lima (5) perkara yang anda pelajari:</label>
                <ol class="ps-3">
                    <li><textarea class="form-control my-2" name="perkara1" rows="2"></textarea></li>
                    <li><textarea class="form-control my-2" name="perkara2" rows="2"></textarea></li>
                    <li><textarea class="form-control my-2" name="perkara3" rows="2"></textarea></li>
                    <li><textarea class="form-control my-2" name="perkara4" rows="2"></textarea></li>
                    <li><textarea class="form-control my-2" name="perkara5" rows="2"></textarea></li>
                </ol>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">3. Cadangan/Penambahbaikan:</label>
                <ol class="ps-3">
                    <li><textarea class="form-control my-2" name="cadangan1" rows="2"></textarea></li>
                    <li><textarea class="form-control my-2" name="cadangan2" rows="2"></textarea></li>
                    <li><textarea class="form-control my-2" name="cadangan3" rows="2"></textarea></li>
                </ol>
            </div>

            <hr class="my-4">

            <h5 class="fw-bold page-break">Penceramah</h5>
            <div class="row g-3 mb-4">

            <div class="row g-3 mb-4">
                <div class="col"><input type="text" class="form-control" name="p1" placeholder="Penceramah 1"></div>
                <div class="col"><input type="text" class="form-control" name="p2" placeholder="Penceramah 2"></div>
                <div class="col"><input type="text" class="form-control" name="p3" placeholder="Penceramah 3"></div>
                <div class="col"><input type="text" class="form-control" name="p4" placeholder="Penceramah 4"></div>
                <div class="col"><input type="text" class="form-control" name="p5" placeholder="Penceramah 5"></div>
            </div>

            <p class="text-muted">Skala penilaian: <strong>10–9: Cemerlang, 8–6: Baik, 5–4: Sederhana, 3–1: Lemah</strong></p>

            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>Aspek</th>
                            <th>P1</th><th>P2</th><th>P3</th><th>P4</th><th>P5</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $aspects = [
                            "i. Kefahaman/Penguasaan terhadap subjek",
                            "ii. Penyampaian",
                            "iii. Penyediaan bahan/slaid",
                            "iv. Perhubungan dan penglibatan peserta",
                            "v. Penggunaan contoh-contoh yang diselitkan dalam ceramah"
                        ];
                        foreach ($aspects as $index => $text) {
                            echo "<tr>";
                            echo "<td class='text-start'>$text</td>";
                            for ($i = 1; $i <= 5; $i++) {
                                echo "<td><input type='text' class='form-control text-center' name='aspect{$index}_p{$i}' style='width: 60px; margin: auto;'></td>";
                            }
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="row g-3 mt-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tandatangan</label>
                    <input type="text" class="form-control" name="tandatangan">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tarikh</label>
                    <input type="date" class="form-control" name="tarikh_penilaian">
                </div>
            </div>
            <div class="text-end mt-4">
            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold rounded-pill shadow-sm no-print">Submit</button>
            <button type="button" class="btn btn-secondary px-4 py-2 fw-semibold rounded-pill shadow-sm no-print" onclick="window.print()">Print</button>
            </div>

        </form>
    </div>
</div>

<script src="sedco-submission.js"></script>
<script src="sedco-shell.js?v=20260930-9"></script>
</body>
</html>
