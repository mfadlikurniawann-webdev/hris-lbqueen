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
        $row_bg = $is_weekend ? 'style="background-color: #f8fafc;"' : '';
        $status_bg = $is_weekend ? 'style="background-color: #e0f2fe; color: #0369a1;"' : 'style="background-color: #fee2e2; color: #991b1b;"';

        if (isset($absen_data[$tgl_str])) {
            $d = $absen_data[$tgl_str];
            $status_asli = $d['status_in'] ?: 'Hadir';
            $jam_masuk = $d['in_time'] ? date('H:i', strtotime($d['in_time'])) : '-';
            $jam_keluar = $d['out_time'] ? date('H:i', strtotime($d['out_time'])) : '-';

            if ($status_asli == 'Hadir') {
                $tot_tepat++;
                $status = 'Hadir';
                $status_bg = 'style="background-color: #dcfce7; color: #166534;"';
            } elseif ($status_asli == 'Telat' || $status_asli == 'Terlambat') {
                $tot_telat++;
                $status = 'Terlambat';
                $status_bg = 'style="background-color: #fef9c3; color: #854d0e;"';
            }

            if ($d['in_time'] && $d['out_time']) {
                $diff = strtotime($d['out_time']) - strtotime($d['in_time']);
                if ($diff > 0) {
                    $total_menit_kerja += floor($diff / 60);
                    $total_jam = floor($diff / 3600) . 'j ' . floor(($diff % 3600) / 60) . 'm';
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
                $jam_lembur = floor($diff_lembur / 3600) . 'j ' . floor(($diff_lembur % 3600) / 60) . 'm';
            }
        }
    }

    $tgl_indo = $dt->format('d/m/Y');
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            color: #2d3748;
            line-height: 1.5;
            background: #fdfdfd;
            margin: 0;
            padding: 40px 20px;
        }

        .page-container {
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
            padding: 40px;
            background: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            border-radius: 20px;
            border: 1px solid #edf2f7;
        }

        .header-table {
            width: 100%;
            margin-bottom: 25px;
            border-bottom: 2px dashed #edf2f7;
            padding-bottom: 20px;
        }

        .comp-name {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 4px;
            color: #1a202c;
            letter-spacing: -0.5px;
        }

        .comp-addr {
            font-size: 11px;
            color: #718096;
            line-height: 1.6;
        }

        .header-logo {
            max-height: 70px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid #edf2f7;
            padding: 4px;
        }

        .title-box {
            text-align: center;
            margin: 30px 0;
        }

        .title-box h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #C94F78;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .title-box p {
            margin: 8px 0 0 0;
            font-size: 12px;
            font-weight: 600;
            color: #718096;
            background: #f8fafc;
            display: inline-block;
            padding: 6px 16px;
            border-radius: 50px;
            border: 1px solid #edf2f7;
        }

        .info-table {
            width: 100%;
            max-width: 500px;
            margin-bottom: 25px;
            border: none;
        }

        .info-table td {
            padding: 5px 0;
            border: none;
            color: #4a5568;
            font-size: 13px;
        }

        .info-table td:first-child {
            width: 150px;
            font-weight: 700;
            color: #718096;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 25px;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        th, td {
            padding: 10px 14px;
            border-bottom: 1px solid #edf2f7;
            border-right: 1px solid #edf2f7;
        }

        th:last-child, td:last-child {
            border-right: none;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .text-center {
            text-align: center;
        }

        .fw-bold {
            font-weight: 700;
        }

        .bg-green-soft { background-color: #f0fdf4; }
        .bg-red-soft { background-color: #fef2f2; }
        .bg-blue-soft { background-color: #eff6ff; }
        .bg-orange-soft { background-color: #fffbeb; }
        .bg-yellow-soft { background-color: #fefce8; }

        .tabel-harian th {
            background-color: #C94F78;
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            border-bottom: none;
        }

        .tabel-harian td {
            font-size: 11.5px;
            font-weight: 500;
            color: #4a5568;
        }

        /* HCGA TTD Area */
        .hcga-box {
            position: relative;
            height: 120px;
            width: 220px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .img-cap-hcga {
            position: absolute;
            height: 110px;
            z-index: 2;
            opacity: 0.85;
            transform: rotate(-10deg);
            left: 0;
        }

        .img-ttd-hcga {
            position: absolute;
            height: 80px;
            z-index: 1;
            left: 20px;
        }

        .footer-notes {
            font-size: 10px;
            margin-top: 15px;
            color: #718096;
            background: #f8fafc;
            padding: 12px;
            border-radius: 12px;
            border: 1px dashed #cbd5e1;
            line-height: 1.6;
        }

        .auto-approve {
            border: 1px dashed #93c5fd;
            padding: 15px;
            text-align: center;
            border-radius: 20px;
            width: 280px;
            background: #eff6ff;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.05);
        }

        .print-btn {
            display: block;
            width: 100%;
            max-width: 850px;
            margin: 0 auto 30px;
            padding: 16px;
            background: linear-gradient(135deg, #C94F78, #b03d64);
            color: white;
            text-align: center;
            font-weight: 800;
            border: none;
            cursor: pointer;
            border-radius: 50px;
            font-size: 16px;
            box-shadow: 0 10px 25px rgba(201, 79, 120, 0.25);
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        @media print {
            @page { size: A4; margin: 1cm; }
            body { padding: 0; margin: 0; background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-btn { display: none; }
            .page-container { border: none; padding: 0; box-shadow: none; max-width: 100%; }
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">🖨️ CETAK DOKUMEN PDF</button>

    <div class="page-container">
        <table class="header-table">
            <tr>
                <td style="width: 100px; border:none;">
                    <img src="../public/logo/lbqueen_logo.png" class="header-logo" alt="Logo">
                </td>
                <td style="text-align: right; border:none;">
                    <div class="comp-name">PT LBQUEEN CARE BEAUTY</div>
                    <div class="comp-addr">Jl. Hos Cokroaminoto no.17 Kebon Jeruk Tanjung Karang Timur, Bandar Lampung.<br>
                        Telp: +62 821-7617-1448 | Email: lbqueen.id@gmail.com</div>
                </td>
            </tr>
        </table>

        <div class="title-box">
            <h2>Laporan Kehadiran Pribadi</h2>
            <p>Siklus: <?= $teks_siklus ?></p>
        </div>

        <table class="info-table">
            <tr><td>Nama</td><td>: <b><?= htmlspecialchars($data_karyawan['nama']) ?></b></td></tr>
            <tr><td>NIK</td><td>: <?= htmlspecialchars($data_karyawan['nik']) ?></td></tr>
            <tr><td>Penempatan</td><td>: <?= htmlspecialchars($data_karyawan['penempatan']) ?></td></tr>
            <tr><td>Jabatan</td><td>: <?= htmlspecialchars($data_karyawan['posisi']) ?></td></tr>
        </table>

        <table>
            <tr>
                <th colspan="4" class="bg-green-soft text-center" style="color: #166534;">Ringkasan Hadir: <?= $tot_hadir ?></th>
                <th rowspan="2" class="bg-red-soft text-center" style="color: #991b1b;">Tanpa Ket: <?= $tot_absen ?></th>
                <th colspan="2" class="bg-blue-soft text-center" style="color: #1e3a8a;">Cuti/Libur: <?= $tot_cuti_all ?></th>
            </tr>
            <tr class="text-center" style="font-size: 11px;">
                <td class="bg-green-soft">Tepat: <?= $tot_tepat ?></td>
                <td class="bg-orange-soft">Telat: <?= $tot_telat ?></td>
                <td class="bg-yellow-soft">Invalid: <?= $tot_invalid ?></td>
                <td class="bg-orange-soft" style="opacity:0.7">Revisi: <?= $tot_revisi ?></td>
                <td class="bg-blue-soft">Cuti: <?= $tot_libur ?></td>
                <td style="background-color:#f1f5f9;">Sakit: <?= $tot_sakit ?></td>
            </tr>
        </table>

        <div style="margin-bottom: 15px; font-size: 13px;">
            Total Jam Kerja: <b><?= $total_jam_kerja_all ?></b> | Total Lembur: <b><?= $total_jam_lembur_all ?></b>
        </div>

        <table class="tabel-harian">
            <thead>
                <tr>
                    <th class="text-center">Tanggal</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Masuk</th>
                    <th class="text-center">Keluar</th>
                    <th class="text-center">Lembur</th>
                    <th class="text-center">Total Jam</th>
                </tr>
            </thead>
            <tbody>
                <?= $tabel_harian ?>
            </tbody>
        </table>

        <div class="footer-notes">
            <b>Catatan:</b><br>
            1. Apabila ditemukan ketidaksesuaian pencatatan mohon hubungi departemen HCGA.<br>
            2. Perbaikan/penyesuaian pada riwayat kehadiran akan diimplementasikan pada bulan selanjutnya.
        </div>

        <div style="width: 100%; margin-top: 35px;">
            <div style="float: left; width: 300px;">
                <b style="font-size: 13px;">Diperiksa oleh:</b><br>
                <b style="color: #C94F78;">Dept. Human Capital & General Affairs</b>
                <div class="hcga-box">
                    <img src="../public/logo/Cap_LBQueen.png" class="img-cap-hcga" alt="Cap">
                    <img src="../public/logo/ttd.png" class="img-ttd-hcga" alt="Sign">
                </div>
                <b>( <?= htmlspecialchars($karyawan_login['nama']) ?> )</b><br>
                <span style="color: #718096;"><?= htmlspecialchars($karyawan_login['posisi']) ?></span>
            </div>
            <div style="float: right;">
                <div class="auto-approve">
                    <b style="color: #2563eb; font-size: 14px;">PERSETUJUAN OTOMATIS</b><br>
                    <b style="color: #16a34a; font-size: 12px;">✓ DISETUJUI OLEH SISTEM</b>
                    <p style="font-style: italic; margin-top:8px; font-size: 10px; color: #64748b;">
                        Dokumen ini telah ditinjau dan diperiksa oleh HR melalui sistem HRIS LBQueen.<br>
                        Tanda tangan fisik tambahan tidak diperlukan.
                    </p>
                </div>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>

</html>