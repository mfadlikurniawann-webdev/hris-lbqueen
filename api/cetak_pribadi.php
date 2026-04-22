<?php
// api/cetak_pribadi.php
include __DIR__ . '/koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$karyawan_login = auth_required($conn);
$posisi   = trim(strtoupper($karyawan_login['posisi'] ?? ''));
$level    = trim(strtoupper($karyawan_login['level_jabatan'] ?? ''));
$is_admin = (strpos($posisi, 'HC') !== false || strpos($posisi, 'HR') !== false || in_array($level, ['OWNER', 'DIREKTUR']));

if (!$is_admin) {
    die("❌ Akses Ditolak!");
}

$nik_target = $_GET['nik'] ?? '';
if (empty($nik_target)) {
    die("❌ NIK Karyawan tidak ditemukan.");
}

$q_target = $conn->query("SELECT * FROM karyawan WHERE nik='$nik_target'");
$data_karyawan = $q_target->fetch_assoc();
if (!$data_karyawan) {
    die("❌ Data karyawan tidak ditemukan di sistem.");
}

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$nama_bulan_arr = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$end_date_str = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-25";
$end_date = new DateTime($end_date_str);
$start_date = clone $end_date;
$start_date->modify('-1 month')->modify('+1 day');

$start_str = $start_date->format('Y-m-d');
$end_str = $end_date->format('Y-m-d');
$hari_ini_str = date('Y-m-d');

$bulan_lalu = ($bulan == 1) ? 12 : $bulan - 1;
$tahun_lalu = ($bulan == 1) ? $tahun - 1 : $tahun;
$teks_siklus = "26 " . $nama_bulan_arr[$bulan_lalu - 1] . " $tahun_lalu s/d 25 " . $nama_bulan_arr[$bulan - 1] . " $tahun";

$sql_absen = "SELECT DATE(waktu) as tgl, MAX(CASE WHEN jenis='Check In' THEN waktu END) as in_time, MAX(CASE WHEN jenis='Check Out' THEN waktu END) as out_time, MAX(CASE WHEN jenis='Check In' THEN status END) as status_in FROM absensi WHERE nik='$nik_target' AND DATE(waktu) BETWEEN '$start_str' AND '$end_str' GROUP BY DATE(waktu)";
$res_absen = $conn->query($sql_absen);
$absen_data = [];
while ($row = $res_absen->fetch_assoc()) {
    $absen_data[$row['tgl']] = $row;
}

$sql_lembur = "SELECT tanggal, jam_mulai, jam_selesai FROM lembur WHERE nik='$nik_target' AND tanggal BETWEEN '$start_str' AND '$end_str' AND status='Disetujui'";
$res_lembur = $conn->query($sql_lembur);
$lembur_data = [];
while ($row = $res_lembur->fetch_assoc()) {
    $lembur_data[$row['tanggal']] = $row;
}

$tot_tepat = 0;
$tot_telat = 0;
$tot_invalid = 0;
$tot_revisi = 0;
$tot_absen = 0;
$tot_libur = 0;
$tot_sakit = 0;
$total_menit_kerja = 0;
$total_menit_lembur = 0;
$tabel_harian = "";

$period = new DatePeriod($start_date, new DateInterval('P1D'), $end_date->modify('+1 day'));

foreach ($period as $dt) {
    $tgl_str = $dt->format('Y-m-d');
    $hari_ini = $dt->format('D');
    $is_weekend = ($hari_ini == 'Sat' || $hari_ini == 'Sun');

    $row_bg = '';
    $status_bg = '';
    $status = '-';
    $jam_masuk = '-';
    $jam_keluar = '-';
    $jam_lembur = '-';
    $total_jam = '-';

    if ($tgl_str > $hari_ini_str) {
        $status = '-';
        $row_bg = 'style="background-color: #fcfcfc;"';
    } else {
        $status = $is_weekend ? 'Libur' : 'Tidak Hadir';
        $row_bg = $is_weekend ? 'style="background-color: #f8f9fa;"' : '';
        $status_bg = $is_weekend ? 'style="background-color: #cfe2ff;"' : 'style="background-color: #f5c2c7;"';

        if (isset($absen_data[$tgl_str])) {
            $d = $absen_data[$tgl_str];
            $status_asli = $d['status_in'] ?: 'Hadir';
            $jam_masuk = $d['in_time'] ? date('H:i', strtotime($d['in_time'])) : '-';
            $jam_keluar = $d['out_time'] ? date('H:i', strtotime($d['out_time'])) : '-';

            if ($status_asli == 'Hadir') {
                $tot_tepat++;
                $status = 'Hadir';
                $status_bg = 'style="background-color: #d1e7dd;"';
            } elseif ($status_asli == 'Telat' || $status_asli == 'Terlambat') {
                $tot_telat++;
                $status = 'Terlambat';
                $status_bg = 'style="background-color: #ffe69c;"';
            }

            if ($d['in_time'] && $d['out_time']) {
                $diff = strtotime($d['out_time']) - strtotime($d['in_time']);
                if ($diff > 0) {
                    $total_menit_kerja += floor($diff / 60);
                    $total_jam = floor($diff / 3600) . ' jam ' . floor(($diff % 3600) / 60) . ' menit';
                }
            }
            $row_bg = '';
        } else {
            if ($is_weekend) {
                $tot_libur++;
            } else {
                $tot_absen++;
            }
        }

        if (isset($lembur_data[$tgl_str])) {
            $jm = strtotime($lembur_data[$tgl_str]['jam_mulai']);
            $js = strtotime($lembur_data[$tgl_str]['jam_selesai']);
            if ($js < $jm) {
                $js += 86400;
            }

            $diff_lembur = $js - $jm;
            if ($diff_lembur > 0) {
                $total_menit_lembur += floor($diff_lembur / 60);
                $jam_lembur = floor($diff_lembur / 3600) . ' jam ' . floor(($diff_lembur % 3600) / 60) . ' mnt';
            }
        }
    }

    $tgl_indo = $dt->format('d-m-Y');
    $tabel_harian .= "<tr $row_bg><td class='text-center'>$tgl_indo</td><td class='text-center fw-bold' $status_bg>$status</td><td class='text-center'>$jam_masuk</td><td class='text-center'>$jam_keluar</td><td class='text-center'>$jam_lembur</td><td class='text-center'>$total_jam</td></tr>";
}

$tot_hadir = $tot_tepat + $tot_telat;
$tot_cuti_all = $tot_libur + $tot_sakit;
$total_jam_kerja_all = floor($total_menit_kerja / 60) . ' jam ' . ($total_menit_kerja % 60) . ' menit';
$total_jam_lembur_all = ($total_menit_lembur > 0) ? floor($total_menit_lembur / 60) . ' jam ' . ($total_menit_lembur % 60) . ' menit' : '-';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Kehadiran - <?= htmlspecialchars($data_karyawan['nama']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Arial:wght@400;700&display=swap');

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.4;
        }

        .page-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }

        .header-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .comp-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .comp-addr {
            font-size: 10px;
            color: #333;
        }

        .title-box {
            text-align: center;
            margin: 30px 0;
        }

        .title-box h2 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }

        .title-box p {
            margin: 5px 0 0 0;
            font-size: 12px;
            font-weight: bold;
        }

        .info-table {
            width: 60%;
            margin-bottom: 20px;
        }

        .info-table td {
            padding: 3px 0;
        }

        .info-table td:first-child {
            width: 130px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px;
        }

        .text-center {
            text-align: center;
        }

        .fw-bold {
            font-weight: bold;
        }

        .bg-green-soft {
            background-color: #d1e7dd;
        }

        .bg-red-soft {
            background-color: #f8d7da;
        }

        .bg-blue-soft {
            background-color: #cfe2ff;
        }

        .bg-orange-soft {
            background-color: #ffe69c;
        }

        .bg-yellow-soft {
            background-color: #fff3cd;
        }

        .tabel-harian th {
            background-color: #C94F78;
            color: white;
            border-color: #C94F78;
        }

        .footer-notes {
            font-size: 10px;
            margin-top: 20px;
        }

        .auto-approve {
            border: 1px solid #0d6efd;
            padding: 10px;
            text-align: center;
            color: #0d6efd;
            width: 300px;
        }

        .print-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: #C94F78;
            color: white;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 20px;
            border: none;
            cursor: pointer;
        }

        /* Area TTD dan Cap Khusus Form HCGA Kiri */
        .box-cap-ttd-left {
            position: relative;
            height: 100px;
            width: 200px;
            margin: 5px 0;
        }

        .img-cap-left {
            position: absolute;
            height: 95px;
            opacity: 0.85;
            transform: rotate(-5deg);
            z-index: 2;
            left: 10px;
        }

        .img-ttd-left {
            position: absolute;
            height: 75px;
            z-index: 1;
            top: 15px;
            left: 30px;
        }

        @media print {
            body {
                padding: 0;
                margin: 0;
            }

            .page-container {
                padding: 0;
                width: 100%;
                max-width: 100%;
                box-shadow: none;
            }

            .print-btn {
                display: none;
            }
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">🖨️ CETAK DOKUMEN PDF</button>

    <div class="page-container">
        <table class="header-table">
            <tr>
                <td style="width: 150px; border:none;"><img src="/logo/lbqueen_logo.PNG" style="max-height: 60px;"></td>
                <td style="text-align: right; border:none;">
                    <div class="comp-name">PT LBQUEEN CARE BEAUTY</div>
                    <div class="comp-addr">Jalan Alam Kurnia, Kalibalau Kencana, Bandar Lampung, Lampung 35133<br>
                        Telp: (+62) 812-3456-7890 | Email: hrd@lbqueen.com</div>
                </td>
            </tr>
        </table>

        <div class="title-box">
            <h2>Laporan Kehadiran</h2>
            <p>Siklus: <?= $teks_siklus ?></p>
        </div>

        <table class="info-table" style="border:none;">
            <tr>
                <td style="border:none;">Nama</td>
                <td style="border:none;">: <?= htmlspecialchars($data_karyawan['nama']) ?></td>
            </tr>
            <tr>
                <td style="border:none;">Nomor Karyawan</td>
                <td style="border:none;">: <?= htmlspecialchars($data_karyawan['nik']) ?></td>
            </tr>
            <tr>
                <td style="border:none;">Penempatan</td>
                <td style="border:none;">: <?= htmlspecialchars($data_karyawan['penempatan']) ?></td>
            </tr>
            <tr>
                <td style="border:none;">Jabatan</td>
                <td style="border:none;">: <?= htmlspecialchars($data_karyawan['posisi']) ?></td>
            </tr>
        </table>

        <table>
            <tr>
                <th colspan="4" class="bg-green-soft text-center">Hadir</th>
                <th rowspan="2" class="bg-red-soft text-center" style="width: 100px;">Tidak<br>Hadir</th>
                <th colspan="2" class="bg-blue-soft text-center">Cuti/Libur/Sakit</th>
            </tr>
            <tr>
                <th colspan="4" class="bg-green-soft text-center fs-5"><?= $tot_hadir ?></th>
                <th colspan="2" class="bg-blue-soft text-center fs-5"><?= $tot_cuti_all ?></th>
            </tr>
            <tr class="text-center">
                <td class="bg-green-soft">Tepat</td>
                <td class="bg-orange-soft">Terlambat</td>
                <td class="bg-yellow-soft">Invalid</td>
                <td class="bg-orange-soft" style="opacity:0.7">Revisi</td>
                <td rowspan="2" class="bg-red-soft fw-bold fs-5"><?= $tot_absen ?></td>
                <td class="bg-blue-soft">Cuti/Libur</td>
                <td style="background-color:#e2e3e5;">Sakit</td>
            </tr>
            <tr class="text-center fw-bold">
                <td class="bg-green-soft"><?= $tot_tepat ?></td>
                <td class="bg-orange-soft"><?= $tot_telat ?></td>
                <td class="bg-yellow-soft"><?= $tot_invalid ?></td>
                <td class="bg-orange-soft" style="opacity:0.7"><?= $tot_revisi ?></td>
                <td class="bg-blue-soft"><?= $tot_libur ?></td>
                <td style="background-color:#e2e3e5;"><?= $tot_sakit ?></td>
            </tr>
        </table>

        <table class="info-table" style="border:none; width:100%; margin-bottom:15px;">
            <tr>
                <td style="border:none; width: 130px; font-weight:bold;">Total Jam Kerja</td>
                <td style="border:none;">: <?= $total_jam_kerja_all ?></td>
            </tr>
            <tr>
                <td style="border:none; font-weight:bold;">Total Jam Lembur</td>
                <td style="border:none;">: <?= $total_jam_lembur_all ?></td>
            </tr>
        </table>

        <table class="tabel-harian">
            <tr>
                <th class="text-center">Tanggal</th>
                <th class="text-center">Status</th>
                <th class="text-center">Jam Masuk</th>
                <th class="text-center">Jam Keluar</th>
                <th class="text-center">Jam Lembur</th>
                <th class="text-center">Total Jam Kerja</th>
            </tr>
            <?= $tabel_harian ?>
        </table>

        <div class="footer-notes">
            <b>Catatan:</b><br>1. Apabila ditemukan ketidaksesuaian pencatatan mohon hubungi departemen HCGA.<br>2. Perbaikan/penyesuaian pada riwayat kehadiran akan diimplementasikan pada bulan selanjutnya.<br>
        </div>

        <div style="width: 100%; margin-top: 30px;">
            <div style="float: left;">
                <b>Diperiksa oleh:</b><br>
                <b>Dept. Human Capital & General Affairs</b><br>
                <div class="box-cap-ttd-left">
                    <img src="../public/logo/Cap_LBQueen.png" class="img-cap-left" alt="Cap">
                    <img src="../public/logo/ttd.png" class="img-ttd-left" alt="Tanda Tangan">
                </div>
                ( <?= htmlspecialchars($karyawan_login['nama']) ?> )<br>
                <b><?= htmlspecialchars($karyawan_login['posisi']) ?></b>
            </div>
            <div style="float: right;">
                <div class="auto-approve">
                    <b style="color: #0d6efd;">PERSETUJUAN OTOMATIS</b><br><br>
                    <b style="color: #198754;">✓ DISETUJUI OLEH SISTEM</b>
                    <p style="font-style: italic; margin-top:5px;">Dokumen ini telah ditinjau dan diperiksa<br>oleh HR melalui sistem HRIS LBQueen.<br>Tanda tangan fisik tambahan tidak diperlukan.</p>
                </div>
            </div>
            <div style="clear: both;"></div>
        </div>
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