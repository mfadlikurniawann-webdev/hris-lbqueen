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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            color: #2d3748;
            line-height: 1.5;
            background: #fdfdfd;
            margin: 0;
            padding: 40px 20px;
        }

        .page-container {
            max-width: 950px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #edf2f7;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }

        .header {
            text-align: center;
            margin-bottom: 35px;
            border-bottom: 2px dashed #edf2f7;
            padding-bottom: 25px;
        }

        .header h2 {
            margin: 0 0 10px;
            color: #C94F78;
            font-weight: 800;
            font-size: 24px;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        .header p {
            color: #718096;
            font-size: 14px;
            font-weight: 500;
            background: #f8fafc;
            display: inline-block;
            padding: 10px 24px;
            border-radius: 50px;
            border: 1px solid #edf2f7;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border: 1px solid #edf2f7;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }

        th, td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #edf2f7;
        }

        th {
            background-color: #FDF0F5;
            color: #C94F78;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
        }

        td {
            font-weight: 500;
            color: #4a5568;
        }

        td b {
            color: #1a202c;
            font-weight: 700;
        }

        .text-center {
            text-align: center;
        }

        /* TTD Area */
        .signatures {
            margin-top: 50px;
            display: flex;
            justify-content: flex-end;
        }

        .signature-box {
            width: 260px;
            text-align: center;
            border: 1px solid #edf2f7;
            border-radius: 20px;
            background: #f8fafc;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .signature-box .sig-date {
            padding: 12px;
            font-size: 12px;
            color: #718096;
            border-bottom: 1px dashed #edf2f7;
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
            transform: rotate(-8deg);
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

        .signature-box .sig-title {
            color: #C94F78;
            font-weight: 800;
            font-size: 14px;
            background: #FDF0F5;
            padding: 12px;
            border-top: 1px dashed #edf2f7;
        }

        .print-btn {
            display: block;
            width: 100%;
            max-width: 950px;
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

        .print-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(201, 79, 120, 0.35);
        }

        @media print {
            @page { size: A4; margin: 1cm; }
            body { padding: 0; margin: 0; background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-btn { display: none; }
            .page-container { border: none; padding: 0; box-shadow: none; border-radius: 0; max-width: 100%; }
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">🖨️ CETAK REKAP TIM PDF</button>

    <div class="page-container">
        <div class="header">
            <h2>PT LBQUEEN CARE BEAUTY</h2>
            <div style="font-size: 13px; margin-bottom: 15px; color: #718096;">
                Jl. Hos Cokroaminoto no.17 Kebon Jeruk Tanjung Karang Timur, Bandar Lampung.<br>
                Telp: +62 821-7617-1448 | Email: lbqueen.id@gmail.com
            </div>
            <p>Rekapitulasi Kehadiran - Periode: <b><?= $nama_bulan[(int)$bulan - 1] ?> <?= $tahun ?></b></p>
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
                <?php $no = 1;
                while ($row = $result->fetch_assoc()): ?>
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
                <div class="sig-date">Bandar Lampung, <?= date('d') ?> <?= $nama_bulan[(int)date('m') - 1] ?> <?= date('Y') ?></div>
                <div class="sig-area">
                    <img src="../public/logo/Cap_LBQueen.png" class="img-cap" alt="Cap">
                    <img src="../public/logo/ttd.png" class="img-ttd" alt="Tanda Tangan">
                </div>
                <div class="sig-title">Human Capital / HRD</div>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>

</html>