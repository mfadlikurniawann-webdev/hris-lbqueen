<?php
// api/cetak_rekap.php
include __DIR__ . '/koneksi.php';

$karyawan = auth_required($conn);
$posisi   = strtoupper($karyawan['posisi']);
$level    = strtoupper($karyawan['level_jabatan']);
$is_admin = in_array($posisi, ['HCG','HRD','HR']) || in_array($level, ['OWNER','DIREKTUR']);

if (!$is_admin) { die("❌ Akses Ditolak! Khusus HR & Owner."); }

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$nama_bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// Query Rekap Kehadiran
$sql = "SELECT k.nik, k.nama, k.posisi, k.penempatan,
        COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as hadir,
        COUNT(CASE WHEN a.status = 'Telat' THEN 1 END) as telat
        FROM karyawan k 
        LEFT JOIN absensi a ON k.nik = a.nik AND MONTH(a.waktu) = '$bulan' AND YEAR(a.waktu) = '$tahun'
        GROUP BY k.nik ORDER BY k.nama ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Absensi - <?= $nama_bulan[$bulan-1] ?> <?= $tahun ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #C94F78; padding-bottom: 10px; }
        .header h2 { margin: 5px 0; color: #C94F78; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #FDF0F5; color: #C94F78; }
        .text-center { text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()" style="padding:10px; background:#C94F78; color:white; border:none; cursor:pointer; border-radius:5px; margin-bottom:20px;">🖨️ Cetak PDF</button>
    
    <div class="header">
        <h2>PT Arga Bumi Indonesia / LBQueen</h2>
        <p>Rekapitulasi Kehadiran Karyawan - Periode: <b><?= $nama_bulan[$bulan-1] ?> <?= $tahun ?></b></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIK</th>
                <th>Nama Karyawan</th>
                <th>Posisi / Penempatan</th>
                <th class="text-center">Total Hadir</th>
                <th class="text-center">Total Terlambat</th>
            </tr>
        </thead>
        <tbody>
            <?php $no=1; while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= $row['nik'] ?></td>
                <td><b><?= $row['nama'] ?></b></td>
                <td><?= $row['posisi'] ?> (<?= $row['penempatan'] ?>)</td>
                <td class="text-center"><?= $row['hadir'] ?> Hari</td>
                <td class="text-center"><?= $row['telat'] ?> Hari</td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 50px; text-align: right; float: right; width: 200px;">
        <p>Mengetahui,</p><br><br><br>
        <p><b>Human Capital / HRD</b></p>
    </div>
    <script>window.onload = function() { window.print(); }</script>
</body>
</html>