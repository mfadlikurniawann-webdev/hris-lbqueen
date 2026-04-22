<?php
// api/cetak_rekap.php
include __DIR__ . '/koneksi.php';

$karyawan = auth_required($conn);
$posisi   = trim(strtoupper($karyawan['posisi'] ?? ''));
$level    = trim(strtoupper($karyawan['level_jabatan'] ?? ''));
$is_admin = (strpos($posisi, 'HC') !== false || strpos($posisi, 'HR') !== false || in_array($level, ['OWNER', 'DIREKTUR']));

if (!$is_admin) {
    die("❌ Akses Ditolak!");
}

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$nama_bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

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
    <title>Rekap Absensi - <?= $nama_bulan[(int)$bulan - 1] ?> <?= $tahun ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #C94F78;
            padding-bottom: 10px;
        }

        .header h2 {
            margin: 5px 0;
            color: #C94F78;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #FDF0F5;
            color: #C94F78;
        }

        .text-center {
            text-align: center;
        }

        /* Area TTD dan Cap */
        .ttd-area {
            margin-top: 50px;
            float: right;
            width: 250px;
            text-align: center;
            position: relative;
        }

        .box-cap-ttd {
            position: relative;
            height: 110px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .img-cap {
            position: absolute;
            height: 100px;
            opacity: 0.85;
            transform: rotate(-5deg);
            z-index: 1;
        }

        .img-ttd {
            position: absolute;
            height: 80px;
            z-index: 2;
            top: 15px;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <button class="no-print" onclick="window.print()" style="padding:10px; background:#C94F78; color:white; border:none; cursor:pointer; border-radius:5px; margin-bottom:20px; width:100%; font-weight:bold;">🖨️ Cetak PDF</button>

    <div class="header">
        <h2>PT LBQUEEN CARE BEAUTY</h2>
        <p>Rekapitulasi Kehadiran Karyawan - Periode: <b><?= $nama_bulan[(int)$bulan - 1] ?> <?= $tahun ?></b></p>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center">No</th>
                <th>NIK</th>
                <th>Nama Karyawan</th>
                <th>Posisi / Penempatan</th>
                <th class="text-center">Total Hadir</th>
                <th class="text-center">Total Terlambat</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1;
            while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= $row['nik'] ?></td>
                    <td><b><?= htmlspecialchars($row['nama']) ?></b></td>
                    <td><?= $row['posisi'] ?> (<?= $row['penempatan'] ?>)</td>
                    <td class="text-center"><?= $row['hadir'] ?> Hari</td>
                    <td class="text-center"><?= $row['telat'] ?> Hari</td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="ttd-area">
        <p>Mengetahui,</p>
        <div class="box-cap-ttd">
            <img src="/logo/Cap_LBQueen.png" class="img-cap" alt="Cap">
            <img src="/logo/ttd.png" class="img-ttd" alt="Tanda Tangan">
        </div>
        <p><b>Human Capital / HRD</b></p>
    </div>
    <div style="clear:both;"></div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>

</html>