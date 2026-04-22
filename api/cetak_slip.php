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

// Ambil Data Karyawan
$q_kar = $conn->query("SELECT nama, posisi, status_pegawai, tgl_bergabung FROM karyawan WHERE nik='$nik_target'");
$data_kar = $q_kar->fetch_assoc();
if (!$data_kar) die("Data Karyawan tidak ditemukan.");

$status_pegawai = strtolower($data_kar['status_pegawai']);
$is_probation = (strpos($status_pegawai, 'probation') !== false);
$tgl_bergabung = $data_kar['tgl_bergabung'] ?? '0000-00-00';

// Hitung Kehadiran Berdasarkan Cutoff 26 - 25
$bulan_p = (int)$bulan;
$tahun_p = (int)$tahun;
$bulan_s = $bulan_p - 1;
$tahun_s = $tahun_p;
if ($bulan_s == 0) {
    $bulan_s = 12;
    $tahun_s--;
}
$start_date = "$tahun_s-" . str_pad($bulan_s, 2, "0", STR_PAD_LEFT) . "-26";
$end_date   = "$tahun_p-" . str_pad($bulan_p, 2, "0", STR_PAD_LEFT) . "-25";

$q_abs = $conn->query("SELECT COUNT(DISTINCT DATE(waktu)) as total_hadir FROM absensi WHERE nik='$nik_target' AND jenis='Check In' AND DATE(waktu) BETWEEN '$start_date' AND '$end_date'");
$d_abs = $q_abs->fetch_assoc();
$kehadiran_aktual = (int)$d_abs['total_hadir'];

// Input HR dari Modal Payroll
$hari_kerja = (int)($_GET['hari_kerja'] ?? 26);
if ($kehadiran_aktual > 0) {
    $hari_kerja = $kehadiran_aktual;
}

$uang_makan = (int)($_GET['uang_makan'] ?? 0);
$tidak_gaji = isset($_GET['tidak_hitung_gaji']) && $_GET['tidak_hitung_gaji'] == '1';
$capai_target = isset($_GET['capai_target']) && $_GET['capai_target'] == '1';
$alpa_banyak = isset($_GET['alpa_banyak']) && $_GET['alpa_banyak'] == '1';

// Cek apakah belum genap 1 bulan
$belum_genap_1_bulan = false;
if ($tgl_bergabung != '0000-00-00') {
    $date_bergabung = new DateTime($tgl_bergabung);
    $date_cutoff = new DateTime($end_date);
    $diff = $date_bergabung->diff($date_cutoff);
    if ($diff->y == 0 && $diff->m == 0) {
        $belum_genap_1_bulan = true;
    }
}

// LOGIKA KOMPENSASI KONTRAK
$gaji_pokok = 1000000;
$uang_kerajinan = 200000; 
$uang_bonus = 0;

if ($tidak_gaji) {
    $gaji_pokok = 0;
} else if ($is_probation && $belum_genap_1_bulan) {
    // Gaji pokok harian 25000 untuk probation < 1 bulan
    $gaji_pokok = $hari_kerja * 25000;
}

if ($alpa_banyak) {
    $uang_kerajinan = 0;
} else if ($is_probation) {
    $uang_kerajinan = 0; 
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', 'Arial', sans-serif;
            font-size: 13px;
            color: #1a202c;
            background: #fdfdfd;
            padding: 20px;
            line-height: 1.5;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border: 1px solid #edf2f7;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        /* Tabel Header */
        .header-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 30px;
            border-bottom: 2px dashed #edf2f7;
            padding-bottom: 20px;
        }

        .header-table td {
            padding: 0;
            vertical-align: middle;
        }

        .header-logo {
            width: 85px;
            height: 85px;
            object-fit: contain;
            border-radius: 16px;
            padding: 5px;
            background: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #edf2f7;
        }

        .header-text {
            text-align: center;
            padding-right: 85px; /* Offset for logo balance */
        }

        .header-text h2 {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            color: #C94F78;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .header-text h1 {
            margin: 8px 0;
            font-size: 24px;
            font-weight: 800;
            color: #1a202c;
            letter-spacing: -0.5px;
        }

        .header-text p {
            margin: 0;
            font-size: 12px;
            color: #718096;
        }

        /* Data Tables */
        table.data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 25px;
            border: 1px solid #edf2f7;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        table.data-table th,
        table.data-table td {
            border-bottom: 1px solid #edf2f7;
            padding: 12px 20px;
            text-align: left;
        }

        table.data-table tr:last-child td {
            border-bottom: none;
        }

        .bg-pink {
            background-color: #C94F78 !important;
            color: #ffffff !important;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 12px;
        }

        .text-right {
            text-align: right;
            font-weight: 700;
        }

        .col-label {
            width: 200px;
            color: #718096;
            font-weight: 600;
        }

        /* Signature Area */
        .signature-wrapper {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .signature-box {
            width: 260px;
            border: 1px solid #edf2f7;
            border-radius: 16px;
            text-align: center;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .signature-box .sig-title {
            border-bottom: 1px dashed #edf2f7;
            padding: 12px;
            font-weight: 700;
            color: #718096;
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .signature-box .sig-area {
            height: 120px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }

        .img-cap {
            position: absolute;
            height: 110px;
            z-index: 2;
            opacity: 0.85;
            transform: rotate(-10deg);
            left: 50%;
            margin-left: -55px;
        }

        .img-ttd {
            position: absolute;
            height: 80px;
            z-index: 1;
            left: 50%;
            margin-left: -40px;
        }

        .signature-box .sig-name {
            border-top: 1px dashed #edf2f7;
            padding: 12px;
            font-weight: 800;
            color: #C94F78;
            background: #FDF0F5;
        }

        /* Notes Section */
        .notes-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 30px;
            border: none;
        }

        .notes-table th {
            background-color: #f8fafc;
            color: #C94F78;
            padding: 10px 20px;
            border-radius: 12px 12px 0 0;
            border: 1px solid #edf2f7;
            border-bottom: none;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .notes-table td {
            padding: 8px 20px;
            border: 1px solid #edf2f7;
            color: #718096;
            font-size: 12px;
            font-weight: 500;
            border-top: none;
        }
        
        .notes-table tr:last-child td {
            border-radius: 0 0 12px 12px;
        }

        .print-btn {
            display: block;
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #C94F78, #b03d64);
            color: white;
            text-align: center;
            font-weight: 800;
            margin-bottom: 25px;
            border: none;
            cursor: pointer;
            border-radius: 50px;
            font-size: 16px;
            box-shadow: 0 10px 20px rgba(201, 79, 120, 0.25);
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .print-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(201, 79, 120, 0.35);
        }

        @media print {
            @page { size: A4; margin: 1cm; }
            body { padding: 0; margin: 0; background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-btn { display: none; }
            .container { border: none; padding: 0; box-shadow: none; max-width: 100%; }
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">🖨️ CETAK SLIP GAJI</button>
    <div class="container">

        <table class="header-table">
            <tr>
                <td style="width: 85px;">
                    <img src="../public/logo/lbqueen_logo.png" class="header-logo" alt="Logo">
                </td>
                <td class="header-text">
                    <h2>SLIP GAJI KARYAWAN</h2>
                    <h1>PT LBQUEEN CARE BEAUTY</h1>
                    <p>Jl. Hos Cokroaminoto no.17 Kebon Jeruk Tanjung Karang Timur, Bandar Lampung.<br>
                    Telp: +62 821-7617-1448 | Email: lbqueen.id@gmail.com</p>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <tr class="bg-pink">
                <td colspan="2">IDENTITAS KARYAWAN</td>
                <td style="width:40%;"></td>
            </tr>
            <tr>
                <td class="col-label">Nama</td>
                <td colspan="2">: <b><?= htmlspecialchars($data_kar['nama']) ?></b></td>
            </tr>
            <tr>
                <td class="col-label">Jabatan</td>
                <td colspan="2">: <?= htmlspecialchars($data_kar['posisi']) ?> (<?= htmlspecialchars($data_kar['status_pegawai']) ?>)</td>
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
                <td class="text-right"><?= $hari_kerja ?> Hari</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="3" style="height: 10px;"></td>
            </tr>
            <?php if ($uang_makan > 0): ?>
            <tr>
                <td class="col-label">Tunjangan Uang Makan</td>
                <td class="text-right">Rp<?= number_format($uang_makan, 0, ',', '.') ?></td>
                <td></td>
            </tr>
            <?php endif; ?>
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
            <tr class="bg-pink">
                <td class="col-label" style="color:white;">Total Penerima</td>
                <td class="text-right">Rp<?= number_format($total_penerima, 0, ',', '.') ?></td>
                <td style="background:#C94F78;"></td>
            </tr>
        </table>

        <div class="signature-wrapper">
            <div class="signature-box">
                <div class="sig-title">Best Regards</div>
                <div class="sig-area">
                    <img src="../public/logo/Cap_LBQueen.png" class="img-cap" alt="Cap">
                    <img src="../public/logo/ttd.png" class="img-ttd" alt="Tanda Tangan">
                </div>
                <div class="sig-name">HR & Digital Ops</div>
            </div>
        </div>

        <table class="notes-table">
            <thead>
                <tr>
                    <th>Catatan:</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1. Cut off dilakukan setiap tanggal 25, jika lebih dari itu maka masuk ke bulan depan</td>
                </tr>
                <tr>
                    <td>2. Jika ada kesalahan silahkan hubungi tim HR</td>
                </tr>
                <tr>
                    <td>3. Silahkan cek lampiran untuk mengetahui uang yang dibayarkan</td>
                </tr>
            </tbody>
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