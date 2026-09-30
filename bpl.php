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
    <title>Borang Permohonan Latihan (BPL)</title>

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f8f9fa;
        padding-top: 80px;
    }

    .navbar {
        background-color: #800000;
        padding: 15px 30px;
        position: fixed;
        width: 100%;
        top: 0;
        left: 0;
        z-index: 1000;
    }

    .navbar .nav-link {
        color: white !important;
        font-weight: 500;
        margin: 0 10px;
    }

    .sidebar {
        height: 100vh;
        width: 250px;
        position: fixed;
        top: 65px;
        left: 0;
        background-color: #800000;
        padding-top: 20px;
    }

    .sidebar a {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        color: white;
        text-decoration: none;
        transition: background 0.3s;
    }

    .sidebar a:hover {
        background-color: #a31515;
    }

    .sidebar a i {
        margin-right: 10px;
    }

    .content {
        margin-left: 270px;
        padding: 30px;
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-right: 20px;
        margin-bottom: 50px;
    }

    h2 {
        text-align: center;
        margin-bottom: 30px;
        color: #800000;
        font-weight: bold;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 25px;
        background-color: #fdfdfd;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    td, th {
        padding: 10px;
        vertical-align: top;
        border: 1px solid #ddd;
    }

    th {
        background-color: #eaeaea;
        color: #333;
        font-size: 1rem;
    }

    .input-field,
    input[type="text"],
    input[type="date"],
    input[type="time"],
    textarea {
        width: 100%;
        padding: 8px;
        font-size: 0.95rem;
        border: 1px solid #ccc;
        border-radius: 6px;
        background-color: #fff;
    }

    textarea {
        resize: vertical;
    }

    /* PKK-style Button Design */
    .btn-maroon {
        background-color: #800000;
        color: white;
        padding: 10px 24px;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 500;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        transition: background-color 0.3s ease, transform 0.2s;
    }

    .btn-maroon:hover {
        background-color: #a31515;
        transform: translateY(-2px);
    }

    .btn-maroon:active {
        background-color: #6a0000;
        transform: scale(0.98);
    }

    @media print {
        .btn-maroon {
            display: none;
        }
    }
</style>

<script>
        function printPage() {
            window.print();
        }
    </script>
    <link rel="stylesheet" href="sedco-saas.css?v=20260930-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-8">
</head>
<body class="app-page form-page bpl-page" data-page="task">

    <!-- Navbar -->
    <!-- Sidebar -->
    <!-- Form Content -->
    <div class="content">
        <h2>BORANG PERMOHONAN LATIHAN (BPL)</h2>

        <form method="post" action="submit_application.php?type=BPL">

            <!-- A. MAKLUMAT PEMOHON -->
            <table>
                <tr><th colspan="4">A. MAKLUMAT PEMOHON</th></tr>
                <tr>
                    <td>01. Nama</td>
                    <td><input type="text" name="nama" class="input-field"></td>
                    <td>02. Bahagian</td>
                    <td><input type="text" name="bahagian" class="input-field"></td>
                </tr>
                <tr>
                    <td>03. Jawatan</td>
                    <td colspan="3"><input type="text" name="jawatan" class="input-field"></td>
                </tr>
                <tr>
                    <td>04. Kursus/Seminar</td>
                    <td colspan="3"><input type="text" name="kursus" class="input-field"></td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td colspan="3"><input type="date" name="tarikh"></td>
                </tr>
            </table>

            <!-- B. MAKLUMAT KURSUS/SEMINAR -->
            <table>
                <tr><th colspan="4">B. MAKLUMAT KURSUS/SEMINAR</th></tr>
                <tr>
                    <td>01. Tajuk Kursus</td>
                    <td colspan="3"><input type="text" name="tajuk" class="input-field"></td>
                </tr>
                <tr>
                    <td>02. Penganjur</td>
                    <td colspan="3"><input type="text" name="penganjur" class="input-field"></td>
                </tr>
                <tr>
                    <td>03. Tarikh Mula</td>
                    <td><input type="date" name="tarikh_mula"></td>
                    <td>04. Tarikh Tamat</td>
                    <td><input type="date" name="tarikh_tamat"></td>
                </tr>
                <tr>
                    <td>05. Tempat Kursus</td>
                    <td colspan="3"><input type="text" name="tempat" class="input-field"></td>
                </tr>
                <tr>
                    <td>06. Yuran (RM)</td>
                    <td colspan="3"><input type="text" name="yuran" class="input-field"></td>
                </tr>
                <tr>
                    <td>07. Kandungan</td>
                    <td colspan="3"><textarea name="kandungan" class="input-field" rows="4"></textarea></td>
                </tr>
            </table>

            <!-- C. MAKLUMAT TUGAS LUAR DAERAH -->
            <table>
                <tr><th colspan="4">C. MAKLUMAT TUGAS LUAR DAERAH</th></tr>
                <tr>
                    <td>08. Tempat Bertugas</td>
                    <td colspan="3"><input type="text" name="tempat_tugas" class="input-field"></td>
                </tr>
                <tr>
                    <td>i. Kenderaan</td>
                    <td colspan="3">
                        <label><input type="checkbox" name="kenderaan[]" value="Kapal Terbang"> Kapal Terbang</label>
                        <label><input type="checkbox" name="kenderaan[]" value="Kapal Terbang"> Kenderaan Pejabat</label>
                        <label><input type="checkbox" name="kenderaan[]" value="Kapal Terbang"> Kenderaan sendiri</label>
                        <label><input type="checkbox" name="kenderaan[]" value="Lain-lain"> Lain-lain</label>
                    </td>
                </tr>
                <tr>
                    <td>ii. Masa Bertolak</td>
                    <td><input type="time" name="masa_bertolak"></td>
                    <td>iii. Masa Kembali</td>
                    <td><input type="time" name="masa_kembali"></td>
                </tr>
                <tr>
                    <td>09. Pendahuluan</td>
                    <td colspan="3">
                        <label><input type="radio" name="pendahuluan" value="Ya"> Ya</label>
                        <label><input type="radio" name="pendahuluan" value="Tidak"> Tidak</label>
                    </td>
                </tr>
            </table>

            <!-- ULASAN C - F -->
            <table>
                <tr><th colspan="2">C. ULASAN PENGURUS SEKSYEN LATIHAN</th></tr>
                <tr>
                    <td>Ulasan</td>
                    <td><textarea name="ulasan_latihan" class="input-field" rows="4"></textarea></td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td><input type="date" name="tarikh_latihan"></td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td><input type="text" name="tt_latihan" class="input-field"></td>
                </tr>
            </table>

            <table>
                <tr><th colspan="2">D. ULASAN KETUA/PENGURUS BAHAGIAN</th></tr>
                <tr>
                    <td>Ulasan</td>
                    <td><textarea name="ulasan_bahagian" class="input-field" rows="4"></textarea></td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td><input type="date" name="tarikh_bahagian"></td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td><input type="text" name="tt_bahagian" class="input-field"></td>
                </tr>
            </table>

            <table>
                <tr><th colspan="2">E. ULASAN PENGURUS BESAR KUMPULAN SEDCO</th></tr>
                <tr>
                    <td>Kelulusan</td>
                    <td>
                        <label><input type="radio" name="kelulusan_pgs" value="Diluluskan"> Diluluskan</label>
                        <label><input type="radio" name="kelulusan_pgs" value="Tidak Diluluskan"> Tidak Diluluskan</label>
                    </td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td><input type="date" name="tarikh_pgs"></td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td><input type="text" name="tt_pgs" class="input-field"></td>
                </tr>
            </table>

            <table>
                <tr><th colspan="2">F. ULASAN PENGURUS SEDCO</th></tr>
                <tr>
                    <td>Kelulusan</td>
                    <td>
                        <label><input type="radio" name="kelulusan_sedco" value="Diluluskan"> Diluluskan</label>
                        <label><input type="radio" name="kelulusan_sedco" value="Tidak Diluluskan"> Tidak Diluluskan</label>
                    </td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td><input type="date" name="tarikh_sedco"></td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td><input type="text" name="tt_sedco" class="input-field"></td>
                </tr>
            </table>

            <!-- FINAL CHECKLIST -->
            <table>
                <tr><th colspan="2">ULASAN KEWANGAN</th></tr>
                <tr>
                    <td>a) Bayaran Kursus</td>
                    <td><input type="text" name="bayaran_kursus"> Yuran (RM)</td>
                </tr>
                <tr>
                    <td>b) Permohonan Pendahuluan Diterima</td>
                    <td>
                        <label><input type="radio" name="pendahuluan_diterima" value="Ya"> Ya</label>
                        <label><input type="radio" name="pendahuluan_diterima" value="Tidak"> Tidak</label>
                    </td>
                </tr>
                <tr>
                    <td>c) Telah Didaftarkan</td>
                    <td>
                        <label><input type="radio" name="telah_didaftar" value="Ya"> Ya</label>
                        <label><input type="radio" name="telah_didaftar" value="Tidak"> Tidak</label>
                    </td>
                </tr>
            </table>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <input type="submit" name="submit" value="Submit" class="btn-maroon">
                <button onclick="printPage()" type="button" class="btn-maroon">Print</button>
            </div>

        </form>
</div>
<script src="sedco-submission.js"></script>
<script src="sedco-shell.js?v=20260930-8"></script>
</body>
</html>
