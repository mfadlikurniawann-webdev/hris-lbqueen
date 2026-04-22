<?php
// api/cetak_slip.php
include __DIR__ . '/koneksi.php';

$karyawan_login = auth_required($conn);
$posisi   = trim(strtoupper($karyawan_login['posisi'] ?? ''));
$level    = trim(strtoupper($karyawan_login['level_jabatan'] ?? ''));
$is_admin = (strpos($posisi, 'HC') !== false || strpos($posisi, 'HR') !== false || in_array($level, ['OWNER', 'DIREKTUR']));

if (!$is_admin) {
    die("❌ Akses Ditolak!");
}

$nik_target = $_GET['nik'] ?? '';
$bulan = str_pad($_GET['bulan'] ?? date('m'), 2, '0', STR_PAD_LEFT);
$tahun = $_GET['tahun'] ?? date('Y');
$nama_bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$periode = $nama_bulan[(int)$bulan - 1] . " " . $tahun;

$q_kar = $conn->query("SELECT nama, posisi, status_pegawai FROM karyawan WHERE nik='$nik_target'");
$data_kar = $q_kar->fetch_assoc();
if (!$data_kar) die("Data Karyawan tidak ditemukan.");

$status_pegawai = strtolower($data_kar['status_pegawai']);
$is_probation = (strpos($status_pegawai, 'probation') !== false);

// Input HR dari Modal Payroll
$hari_kerja = (int)($_GET['hari_kerja'] ?? 26);
$uang_makan = (int)($_GET['uang_makan'] ?? 0);
$tidak_gaji = isset($_GET['tidak_hitung_gaji']) && $_GET['tidak_hitung_gaji'] == '1';
$capai_target = isset($_GET['capai_target']) && $_GET['capai_target'] == '1';
$alpa_banyak = isset($_GET['alpa_banyak']) && $_GET['alpa_banyak'] == '1';

// LOGIKA KOMPENSASI KONTRAK
$gaji_pokok = $tidak_gaji ? 0 : 1000000;
$uang_kerajinan = 0;
$uang_bonus = 0;

if (!$is_probation && !$alpa_banyak) {
    $uang_kerajinan = 200000;
}
if ($capai_target) {
    $uang_bonus = 600000;
}

$total_penerima = $gaji_pokok + $uang_makan + $uang_kerajinan + $uang_bonus;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - <?= htmlspecialchars($data_kar['nama']) ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 13px;
            color: #000;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        /* Tabel Header Atas */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-bottom: 20px;
        }

        .header-table td {
            padding: 10px;
            text-align: center;
            border: 1px solid #000;
        }

        .header-logo {
            width: 80px;
            height: auto;
        }

        .title-main {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .title-sub {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .title-addr {
            font-size: 12px;
        }

        /* Tabel Konten (Sama Persis Desain Excel) */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table.data-table th,
        table.data-table td {
            border: 1px solid #000;
            padding: 6px 10px;
            text-align: left;
        }

        .bg-pink {
            background-color: #DDA0B8 !important;
            font-weight: bold;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .text-right {
            text-align: right;
        }

        .col-label {
            width: 180px;
        }

        /* Kotak TTD */
        .signature-wrapper {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .signature-box {
            width: 250px;
            border: 1px solid #000;
            text-align: center;
        }

        .signature-box .sig-title {
            border-bottom: 1px solid #000;
            padding: 5px;
            font-weight: bold;
        }

        .signature-box .sig-area {
            height: 100px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .signature-box .sig-area img.sig-ttd {
            height: 75px;
            position: relative;
            z-index: 2;
        }

        .signature-box .sig-area img.sig-cap {
            position: absolute;
            height: 100px;
            width: auto;
            left: 50%;
            top: 50%;
            transform: translate(-60%, -50%); /* Geser sedikit ke kiri agar natural */
            z-index: 1;
            opacity: 0.8;
        }

        .signature-box .sig-name {
            border-top: 1px solid #000;
            padding: 5px;
            font-weight: bold;
        }

        /* Catatan Bawah */
        .notes-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }

        .notes-table th,
        .notes-table td {
            border: 1px solid #000;
            padding: 5px 10px;
            text-align: left;
        }

        .print-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: #C94F78;
            color: white;
            text-align: center;
            font-weight: bold;
            border: none;
            cursor: pointer;
            margin-bottom: 20px;
        }

        @media print {
            .print-btn {
                display: none;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">🖨️ CETAK SLIP GAJI PDF</button>
    <div class="container">

        <table class="header-table">
            <tr>
                <td style="width: 120px;"><img src="/logo/lbqueen_logo.PNG" class="header-logo"></td>
                <td>
                    <div class="title-main">SLIP GAJI KARYAWAN</div>
                    <div class="title-sub">PT LBQUEEN CARE BEAUTY</div>
                    <div class="title-addr">Jl. Hos Cokroaminoto no.17 Kebon Jeruk Tanjung Karang Timur, Bandar Lampung</div>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <tr class="bg-pink">
                <td colspan="2">IDENTITAS KARYAWAN</td>
                <td style="border-left:none; width:40%;"></td>
            </tr>
            <tr>
                <td class="col-label">Nama</td>
                <td colspan="2">: <?= htmlspecialchars($data_kar['nama']) ?></td>
            </tr>
            <tr>
                <td class="col-label">Jabatan</td>
                <td colspan="2">: <?= htmlspecialchars($data_kar['posisi']) ?></td>
            </tr>
            <tr>
                <td class="col-label">Periode</td>
                <td colspan="2">: <?= $periode ?></td>
            </tr>
        </table>

        <table class="data-table">
            <tr class="bg-pink">
                <td colspan="2">PENERIMA</td>
                <td style="width:40%;"></td>
            </tr>
            <tr>
                <td class="col-label">Gaji Pokok</td>
                <td class="text-right" style="width:150px;">Rp<?= number_format($gaji_pokok, 0, ',', '.') ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="col-label">Total Hari Kerja</td>
                <td class="text-right"><?= $hari_kerja ?></td>
                <td></td>
            </tr>
            <tr>
                <td colspan="3" style="height: 15px; border-left: 1px solid #000; border-right: 1px solid #000; border-bottom: none; border-top: none;"></td>
            </tr>
            <tr>
                <td class="col-label">Tunjangan Uang Makan</td>
                <td class="text-right">Rp<?= number_format($uang_makan, 0, ',', '.') ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="col-label">Uang Kerajinan</td>
                <td class="text-right">Rp<?= number_format($uang_kerajinan, 0, ',', '.') ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="col-label">Uang Bonus Target</td>
                <td class="text-right">Rp<?= number_format($uang_bonus, 0, ',', '.') ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="col-label bg-pink">Total Penerima</td>
                <td class="text-right bg-pink">Rp<?= number_format($total_penerima, 0, ',', '.') ?></td>
                <td class="bg-pink"></td>
            </tr>
        </table>

        <div class="signature-wrapper">
            <div class="signature-box">
                <div class="sig-title">Best Regards</div>
                <div class="sig-area">
                    <img src="/public/logo/Cap_LBQueen.png" class="sig-cap" alt="Cap Perusahaan">
                    <img src="/public/logo/ttd.png" class="sig-ttd" alt="Tanda Tangan">
                </div>
                <div class="sig-name">HR & Digital Ops</div>
            </div>
        </div>

        <table class="notes-table">
            <tr>
                <th class="bg-pink">Catatan:</th>
            </tr>
            <tr>
                <td>1. Cut off dilakukan setiap tanggal 25, jika lebih dari itu maka masuk ke bulan depan</td>
            </tr>
            <tr>
                <td>2. Jika ada kesalahan silahkan hubungi tim HR</td>
            </tr>
            <tr>
                <td>3. Silahkan cek lampiran untuk mengetahui uang yang dibayarkan</td>
            </tr>
        </table>
    </div>
    <script>
        if (window.innerWidth > 800) {
            window.onload = function() {
                window.print();
            }
        }
    </script>
</body>

</html>