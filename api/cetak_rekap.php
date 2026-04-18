<?php
// api/cetak_rekap.php
include __DIR__ . '/koneksi.php';

// Autentikasi HR/Admin (Logika Kebal Spasi)
$karyawan = auth_required($conn);
$posisi   = trim(strtoupper($karyawan['posisi'] ?? ''));
$level    = trim(strtoupper($karyawan['level_jabatan'] ?? ''));
$is_admin = (strpos($posisi, 'HC') !== false || strpos($posisi, 'HR') !== false || in_array($level, ['OWNER','DIREKTUR']));

if (!$is_admin) {
    die("<div style='font-family:sans-serif; padding:20px; color:red; text-align:center;'>
        <h2>❌ Akses Ditolak!</h2>
        <p>Halaman ini khusus untuk HR & Manajemen.</p>
        <p>Posisi Anda saat ini terbaca sebagai: <b>" . ($posisi ?: 'Kosong') . "</b></p>
        </div>");
}

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$nama_bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// Query Rekap Kehadiran
$sql = "SELECT k.nik, k.nama, k.posisi, k.penempatan,
        COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as hadir,
        COUNT(CASE WHEN a.status = 'Telat' OR a.status = 'Terlambat' THEN 1 END) as telat
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; font-size: 13px; color: #2d3748; line-height: 1.5; background: #fdfdfd; margin: 0; padding: 40px 20px; }
        .page-container { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #edf2f7; border-radius: 20px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px dashed #edf2f7; padding-bottom: 20px; }
        .header h2 { margin: 5px 0 15px; color: #C94F78; font-weight: 800; font-size: 22px; letter-spacing: -0.5px; text-transform: uppercase; }
        .header p { color: #718096; font-size: 14px; font-weight: 500; background: #f8fafc; display: inline-block; padding: 8px 24px; border-radius: 50px; border: 1px solid #edf2f7; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px; border: 1px solid #edf2f7; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #edf2f7; border-right: 1px solid #edf2f7; }
        th:last-child, td:last-child { border-right: none; }
        tr:last-child th, tr:last-child td { border-bottom: none; }
        th { background-color: #FDF0F5; color: #C94F78; font-weight: 700; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
        td { font-weight: 500; color: #4a5568; }
        td b { color: #1a202c; font-weight: 700; }
        .text-center { text-align: center; }
        
        .print-btn { display: block; width: 100%; max-width: 900px; margin: 0 auto 25px auto; padding: 16px; background: linear-gradient(135deg, #C94F78, #b03d64); color: white; text-align: center; text-decoration: none; font-weight: 800; font-size: 16px; border: none; cursor: pointer; border-radius: 50px; box-shadow: 0 10px 20px rgba(201, 79, 120, 0.25); text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s ease; }
        .print-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 25px rgba(201, 79, 120, 0.35); }
        
        .signatures { margin-top: 50px; display: flex; justify-content: flex-end; }
        .signature-box { width: 250px; text-align: center; border: 1px dashed #cbd5e1; padding: 20px; border-radius: 16px; background: #f8fafc; }
        .signature-box p { margin: 0 0 10px 0; color: #718096; font-size: 13px; }
        .signature-box p.sign-title { color: #1a202c; font-weight: 800; font-size: 14px; margin-top: 60px; border-top: 1px solid #cbd5e1; padding-top: 10px; }
        
        @media print { 
            body { padding: 0; background: #fff; margin: 0; }
            .page-container { border: none; padding: 0; box-shadow: none; border-radius: 0; max-width: 100%; }
            .no-print { display: none; } 
            th { background-color: #f0f0f0 !important; color: #000 !important; -webkit-print-color-adjust: exact; }
            table, th, td { border-color: #ddd; }
            td, td b, .header h2, .header p { color: #000; }
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Cetak Rekap Tim PDF</button>
    
    <div class="page-container">
        <div class="header">
            <h2>PT LBQueen Care Beauty</h2>
            <p>Rekapitulasi Kehadiran Karyawan - Periode: <b><?= $nama_bulan[$bulan-1] ?> <?= $tahun ?></b></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 50px;">No</th>
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
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= $row['nik'] ?></td>
                    <td><b><?= htmlspecialchars($row['nama']) ?></b></td>
                    <td><?= htmlspecialchars($row['posisi']) ?> <br> <span style="font-size:11px; color:#718096;"><?= htmlspecialchars($row['penempatan']) ?></span></td>
                    <td class="text-center"><b><?= $row['hadir'] ?></b> Hari</td>
                    <td class="text-center"><b><?= $row['telat'] ?></b> Hari</td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div class="signatures">
            <div class="signature-box">
                <p>Bandar Lampung, <?= date('d') ?> <?= $nama_bulan[(int)date('m')-1] ?> <?= date('Y') ?></p>
                <p>Mengetahui,</p>
                <p class="sign-title">Human Capital / HRD</p>
            </div>
        </div>
    </div>
    
    <script>window.onload = function() { window.print(); }</script>
</body>
</html>