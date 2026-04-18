<?php
// api/cetak_pribadi.php
include __DIR__ . '/koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// Autentikasi HR/Admin (Logika Kebal Spasi)
$karyawan_login = auth_required($conn);
$posisi   = trim(strtoupper($karyawan_login['posisi'] ?? ''));
$level    = trim(strtoupper($karyawan_login['level_jabatan'] ?? ''));
$is_admin = (strpos($posisi, 'HC') !== false || strpos($posisi, 'HR') !== false || in_array($level, ['OWNER','DIREKTUR']));

if (!$is_admin) {
    die("<div style='font-family:sans-serif; padding:20px; color:red; text-align:center;'>
        <h2>❌ Akses Ditolak!</h2>
        <p>Halaman ini khusus untuk HR & Manajemen.</p>
        <p>Posisi Anda saat ini terbaca sebagai: <b>" . ($posisi ?: 'Kosong') . "</b></p>
        </div>");
}

// Dapatkan NIK karyawan yang ingin dicetak dari URL (?nik=...)
$nik_target = $_GET['nik'] ?? '';
if (empty($nik_target)) { die("❌ NIK Karyawan tidak ditemukan."); }

// Ambil Data Karyawan Target
$q_target = $conn->query("SELECT * FROM karyawan WHERE nik='$nik_target'");
$data_karyawan = $q_target->fetch_assoc();
if (!$data_karyawan) { die("❌ Data karyawan tidak ditemukan di sistem."); }

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$nama_bulan_arr = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// LOGIKA SIKLUS CUT-OFF (TGL 26 Bulan Lalu - TGL 25 Bulan Ini)
$end_date_str = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-25";
$end_date = new DateTime($end_date_str);

$start_date = clone $end_date;
$start_date->modify('-1 month')->modify('+1 day'); // Mundur 1 bulan, maju 1 hari (jadi tgl 26)

$start_str = $start_date->format('Y-m-d');
$end_str = $end_date->format('Y-m-d');
$hari_ini_str = date('Y-m-d');

// Penamaan Periode Siklus Teks
$bulan_lalu = ($bulan == 1) ? 12 : $bulan - 1;
$tahun_lalu = ($bulan == 1) ? $tahun - 1 : $tahun;
$teks_siklus = "26 " . $nama_bulan_arr[$bulan_lalu - 1] . " $tahun_lalu s/d 25 " . $nama_bulan_arr[$bulan - 1] . " $tahun";

// 1. Ambil riwayat absen di periode Cut-Off
$sql_absen = "SELECT DATE(waktu) as tgl, 
        MAX(CASE WHEN jenis='Check In' THEN waktu END) as in_time,
        MAX(CASE WHEN jenis='Check Out' THEN waktu END) as out_time,
        MAX(CASE WHEN jenis='Check In' THEN status END) as status_in
        FROM absensi 
        WHERE nik='$nik_target' AND DATE(waktu) BETWEEN '$start_str' AND '$end_str'
        GROUP BY DATE(waktu)";
$res_absen = $conn->query($sql_absen);

$absen_data = [];
while ($row = $res_absen->fetch_assoc()) {
    $absen_data[$row['tgl']] = $row;
}

// 2. Ambil riwayat Lembur di periode Cut-Off
$sql_lembur = "SELECT tanggal, jam_mulai, jam_selesai 
               FROM lembur 
               WHERE nik='$nik_target' AND tanggal BETWEEN '$start_str' AND '$end_str' AND status='Disetujui'";
$res_lembur = $conn->query($sql_lembur);

$lembur_data = [];
while ($row = $res_lembur->fetch_assoc()) {
    $lembur_data[$row['tanggal']] = $row;
}

// Variabel Rekap
$tot_tepat = 0; $tot_telat = 0; $tot_invalid = 0; $tot_revisi = 0;
$tot_absen = 0; $tot_libur = 0; $tot_sakit = 0;
$total_menit_kerja = 0;
$total_menit_lembur = 0;

$tabel_harian = "";

// LOOP DARI TANGGAL 26 S/D 25
$period = new DatePeriod($start_date, new DateInterval('P1D'), $end_date->modify('+1 day'));

foreach ($period as $dt) {
    $tgl_str = $dt->format('Y-m-d');
    $hari_ini = $dt->format('D');
    $is_weekend = ($hari_ini == 'Sat' || $hari_ini == 'Sun');
    
    // Default Styling
    $row_bg = '';
    $status_bg = '';
    $status = '-';
    $jam_masuk = '-'; $jam_keluar = '-'; $jam_lembur = '-'; $total_jam = '-';
    
    // Cek apakah tanggal tersebut di masa depan (belum dilewati)
    if ($tgl_str > $hari_ini_str) {
        $status = '-';
        $row_bg = 'style="background-color: #fcfcfc;"';
    } 
    else {
        // Logika Normal jika tanggal sudah dilewati / adalah hari ini
        $status = $is_weekend ? 'Libur' : 'Tidak Hadir';
        $row_bg = $is_weekend ? 'style="background-color: #f8f9fa;"' : '';
        $status_bg = $is_weekend ? 'style="background-color: #cfe2ff;"' : 'style="background-color: #f5c2c7;"';

        // Pengecekan Absensi
        if (isset($absen_data[$tgl_str])) {
            $d = $absen_data[$tgl_str];
            $status_asli = $d['status_in'] ?: 'Hadir';
            
            $jam_masuk = $d['in_time'] ? date('H:i', strtotime($d['in_time'])) : '-';
            $jam_keluar = $d['out_time'] ? date('H:i', strtotime($d['out_time'])) : '-';
            
            if ($status_asli == 'Hadir') {
                $tot_tepat++; $status = 'Hadir'; $status_bg = 'style="background-color: #d1e7dd;"';
            } elseif ($status_asli == 'Telat' || $status_asli == 'Terlambat') {
                $tot_telat++; $status = 'Terlambat'; $status_bg = 'style="background-color: #ffe69c;"';
            }
            
            // Hitung durasi jam kerja harian
            if ($d['in_time'] && $d['out_time']) {
                $diff = strtotime($d['out_time']) - strtotime($d['in_time']);
                if ($diff > 0) {
                    $total_menit_kerja += floor($diff / 60);
                    $total_jam = floor($diff / 3600) . ' jam ' . floor(($diff % 3600) / 60) . ' menit';
                }
            }
            $row_bg = ''; 
        } else {
            if ($is_weekend) { $tot_libur++; } else { $tot_absen++; }
        }

        // Pengecekan Lembur
        if (isset($lembur_data[$tgl_str])) {
            $jm = strtotime($lembur_data[$tgl_str]['jam_mulai']);
            $js = strtotime($lembur_data[$tgl_str]['jam_selesai']);
            
            if ($js < $jm) { $js += 86400; } // Jika lembur lewat tengah malam (beda hari)
            
            $diff_lembur = $js - $jm;
            if ($diff_lembur > 0) {
                $total_menit_lembur += floor($diff_lembur / 60);
                $jam_lembur = floor($diff_lembur / 3600) . ' jam ' . floor(($diff_lembur % 3600) / 60) . ' mnt';
            }
        }
    }

    $tgl_indo = $dt->format('d-m-Y');
    
    $tabel_harian .= "<tr $row_bg>
        <td class='text-center'>$tgl_indo</td>
        <td class='text-center fw-bold' $status_bg>$status</td>
        <td class='text-center'>$jam_masuk</td>
        <td class='text-center'>$jam_keluar</td>
        <td class='text-center'>$jam_lembur</td>
        <td class='text-center'>$total_jam</td>
    </tr>";
}

$tot_hadir = $tot_tepat + $tot_telat;
$tot_cuti_all = $tot_libur + $tot_sakit;
$total_jam_kerja_all = floor($total_menit_kerja / 60) . ' jam ' . ($total_menit_kerja % 60) . ' menit';

$total_jam_lembur_all = '-';
if ($total_menit_lembur > 0) {
    $total_jam_lembur_all = floor($total_menit_lembur / 60) . ' jam ' . ($total_menit_lembur % 60) . ' menit';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kehadiran - <?= htmlspecialchars($data_karyawan['nama']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; font-size: 12px; color: #2d3748; line-height: 1.5; background: #fdfdfd; }
        .page-container { width: 100%; max-width: 850px; margin: 0 auto; padding: 40px; background: white; box-shadow: 0 10px 40px rgba(0,0,0,0.05); border-radius: 20px; border: 1px solid #edf2f7; }
        
        .header-table { width: 100%; margin-bottom: 20px; border-bottom: 2px dashed #edf2f7; padding-bottom: 15px; }
        .header-table td { border: none; padding: 0; }
        .comp-name { font-size: 18px; font-weight: 800; margin-bottom: 4px; color: #1a202c; letter-spacing: -0.5px; }
        .comp-addr { font-size: 11px; color: #718096; line-height: 1.6; }
        .header-logo { max-height: 70px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #edf2f7; padding: 4px; }
        
        .title-box { text-align: center; margin: 30px 0; }
        .title-box h2 { margin: 0; font-size: 20px; font-weight: 800; color: #C94F78; text-transform: uppercase; letter-spacing: 1.5px; }
        .title-box p { margin: 8px 0 0 0; font-size: 12px; font-weight: 600; color: #718096; background: #f8fafc; display: inline-block; padding: 6px 16px; border-radius: 50px; border: 1px solid #edf2f7; }
        
        .info-table { width: 100%; max-width: 500px; margin-bottom: 25px; border: none; }
        .info-table td { padding: 5px 0; border: none; color: #4a5568; font-size: 13px; }
        .info-table td:first-child { width: 150px; font-weight: 700; color: #718096; }
        .info-table td strong { color: #1a202c; }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 25px; border: 1px solid #edf2f7; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        th, td { padding: 10px 14px; border-bottom: 1px solid #edf2f7; border-right: 1px solid #edf2f7; }
        th:last-child, td:last-child { border-right: none; }
        tr:last-child td { border-bottom: none; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: 700; color: #1a202c; }
        
        .bg-green-soft { background-color: #f0fdf4; color: #166534; }
        .bg-red-soft { background-color: #fef2f2; color: #991b1b; }
        .bg-blue-soft { background-color: #eff6ff; color: #1e3a8a; }
        .bg-orange-soft { background-color: #fffbeb; color: #92400e; }
        .bg-yellow-soft { background-color: #fefce8; color: #854d0e; }
        
        .tabel-harian th { background-color: #C94F78; color: white; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; border-bottom: none; }
        .tabel-harian td { font-size: 12px; font-weight: 500; color: #4a5568; }
        
        .footer-notes { font-size: 11px; margin-top: 30px; color: #718096; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px dashed #cbd5e1; }
        .footer-notes b { color: #4a5568; }
        .auto-approve { border: 1px dashed #93c5fd; padding: 15px; text-align: center; border-radius: 16px; width: 300px; background: #eff6ff; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.05); }
        .auto-approve p { margin: 5px 0 0 0; font-size: 10px; color: #64748b; line-height: 1.5; }
        
        .print-btn { display: block; width: 100%; padding: 16px; background: linear-gradient(135deg, #C94F78, #b03d64); color: white; text-align: center; text-decoration: none; font-weight: 800; font-size: 16px; margin-bottom: 25px; border: none; cursor: pointer; border-radius: 50px; box-shadow: 0 10px 20px rgba(201, 79, 120, 0.25); text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s ease; }
        .print-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 25px rgba(201, 79, 120, 0.35); }
        
        @media print {
            body { padding: 0; margin: 0; -webkit-print-color-adjust: exact; color-adjust: exact; print-color-adjust: exact; }
            .page-container { padding: 0; width: 100%; max-width: 100%; box-shadow: none; border: none; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ CETAK DOKUMEN PDF</button>

    <div class="page-container">
        <table class="header-table">
            <tr>
                <td style="width: 150px;"><img src="/logo/lbqueen_logo.PNG" class="header-logo"></td>
                <td style="text-align: right;">
                    <div class="comp-name">PT LBQueen Care Beauty</div>
                    <div class="comp-addr">Jl. Hos Cokroaminoto no.17 Kebon Jeruk Tanjung Karang Timur, Bandar Lampung.<br>
                    Telp: +62 821-7617-1448 | Email: lbqueen.id@gmail.com</div>
                </td>
            </tr>
        </table>

        <div class="title-box">
            <h2>Laporan Kehadiran</h2>
            <p>Siklus: <?= $teks_siklus ?></p>
        </div>

        <table class="info-table" style="border:none;">
            <tr><td style="border:none;">Nama</td><td style="border:none;">: <?= htmlspecialchars($data_karyawan['nama']) ?></td></tr>
            <tr><td style="border:none;">Nomor Karyawan</td><td style="border:none;">: <?= htmlspecialchars($data_karyawan['nik']) ?></td></tr>
            <tr><td style="border:none;">Penempatan</td><td style="border:none;">: <?= htmlspecialchars($data_karyawan['penempatan']) ?></td></tr>
            <tr><td style="border:none;">Jabatan</td><td style="border:none;">: <?= htmlspecialchars($data_karyawan['posisi']) ?></td></tr>
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
            <tr><td style="border:none; width: 130px; font-weight:bold;">Total Jam Kerja</td><td style="border:none;">: <?= $total_jam_kerja_all ?></td></tr>
            <tr><td style="border:none; font-weight:bold;">Total Jam Lembur</td><td style="border:none;">: <?= $total_jam_lembur_all ?></td></tr>
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
            <b>Catatan:</b><br>
            1. Apabila ditemukan ketidaksesuaian pencatatan mohon hubungi departemen HCGA.<br>
            2. Perbaikan/penyesuaian pada riwayat kehadiran akan diimplementasikan pada bulan selanjutnya.<br>
        </div>

        <div style="width: 100%; margin-top: 30px;">
            <div style="float: left;">
                <b>Diperiksa oleh:</b><br>
                <b>Dept. Human Capital & General Affairs</b><br><br><br><br><br>
                ( <?= htmlspecialchars($karyawan_login['nama']) ?> )<br>
                <b><?= htmlspecialchars($karyawan_login['posisi']) ?></b>
            </div>
            <div style="float: right;">
                <div class="auto-approve">
                    <b style="color: #0d6efd;">PERSETUJUAN OTOMATIS</b><br><br>
                    <b style="color: #198754;">✓ DISETUJUI OLEH SISTEM</b>
                    <p style="font-style: italic;">Dokumen ini telah ditinjau dan diperiksa<br>oleh HR melalui sistem HRIS LBQueen.<br>Tanda tangan fisik tidak diperlukan.</p>
                </div>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>
    <script> if(window.innerWidth > 800) { window.onload = function() { window.print(); } } </script>
</body>
</html>