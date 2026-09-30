<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borang Permohonan Latihan (BPL)</title>

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script>
        function printPage() {
            window.print();
        }
    </script>
    <link rel="stylesheet" href="sedco-saas.css?v=20260930-24">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-18">
</head>
<body class="app-page form-page bpl-page" data-page="task">

    <!-- Navbar -->
    <!-- Sidebar -->
    <!-- Form Content -->
    <div class="content">
        <h2>BORANG PERMOHONAN LATIHAN (BPL)</h2>

        <form method="post" action="submit_application.php?type=BPL">
      <?= csrf_field() ?>

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
                        <label><input type="checkbox" name="kenderaan[]" value="Kenderaan Pejabat"> Kenderaan Pejabat</label>
                        <label><input type="checkbox" name="kenderaan[]" value="Kenderaan Sendiri"> Kenderaan sendiri</label>
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

            <div class="form-actions">
                <input type="submit" name="submit" value="Submit" class="btn-maroon">
                <button onclick="printPage()" type="button" class="btn-maroon form-print-button">Print</button>
            </div>

        </form>
</div>
<script>window.SEDCO_FORM_CONTEXT = { role: <?= json_encode($user['role'] ?? 'staff') ?>, mode: 'new' };</script>
<script src="form-permissions.js?v=20260930-24"></script>
<script src="sedco-shell.js?v=20260930-23"></script>
</body>
</html>
