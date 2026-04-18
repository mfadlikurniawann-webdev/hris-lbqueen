<?php
// api/cetak_slip.php
include __DIR__ . '/koneksi.php';

$karyawan_login = auth_required($conn);
$posisi   = trim(strtoupper($karyawan_login['posisi'] ?? ''));
$level    = trim(strtoupper($karyawan_login['level_jabatan'] ?? ''));
$is_admin = (strpos($posisi, 'HC') !== false || strpos($posisi, 'HR') !== false || in_array($level, ['OWNER','DIREKTUR']));

if (!$is_admin) { die("❌ Akses Ditolak!"); }

$nik_target = $_GET['nik'] ?? '';
$bulan = str_pad($_GET['bulan'] ?? date('m'), 2, '0', STR_PAD_LEFT);
$tahun = $_GET['tahun'] ?? date('Y');
$nama_bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$periode = $nama_bulan[(int)$bulan-1] . " " . $tahun;

// Ambil Data Karyawan
$q_kar = $conn->query("SELECT nama, posisi, status_pegawai FROM karyawan WHERE nik='$nik_target'");
$data_kar = $q_kar->fetch_assoc();
if(!$data_kar) die("Data Karyawan tidak ditemukan.");

$status_pegawai = strtolower($data_kar['status_pegawai']);
$is_probation = (strpos($status_pegawai, 'probation') !== false);

// Variabel Input HR
$hari_kerja = (int)($_GET['hari_kerja'] ?? 26);
$uang_makan = (int)($_GET['uang_makan'] ?? 0);
$capai_target = isset($_GET['capai_target']) && $_GET['capai_target'] == '1';
$alpa_banyak = isset($_GET['alpa_banyak']) && $_GET['alpa_banyak'] == '1';

// Logika Gaji Sesuai Kontrak
$gaji_pokok = 1000000;
$uang_kerajinan = 200000; // Default Uang Kerajinan 200k
$uang_bonus = 0;

// Jika checkbox Alpa >= 2x dicentang, maka uang kerajinan hangus (0)
if ($alpa_banyak) {
    $uang_kerajinan = 0;
}

if ($capai_target) {
    $uang_bonus = 600000;
}

$total_penerima = $gaji_pokok + $uang_kerajinan + $uang_bonus + $uang_makan;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - <?= htmlspecialchars($data_kar['nama']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; background: #fdfdfd; padding: 20px; color: #2d3748; line-height: 1.5; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; border: 1px solid #edf2f7; border-radius: 20px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .header { display: flex; align-items: center; border-bottom: 2px dashed #edf2f7; padding-bottom: 25px; margin-bottom: 30px; }
        .header img { width: 85px; height: 85px; object-fit: contain; border-radius: 16px; padding: 5px; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #edf2f7; }
        .header-text { flex: 1; text-align: center; }
        .header-text h2 { margin: 0; font-size: 14px; font-weight: 800; color: #C94F78; text-transform: uppercase; letter-spacing: 2px; }
        .header-text h1 { margin: 8px 0; font-size: 24px; font-weight: 800; color: #1a202c; letter-spacing: -0.5px; }
        .header-text p { margin: 0; font-size: 13px; color: #718096; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 30px; font-size: 13.5px; border: 1px solid #edf2f7; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        th, td { padding: 14px 20px; text-align: left; border-bottom: 1px solid #edf2f7; }
        tr:last-child th, tr:last-child td { border-bottom: none; }
        .bg-pink { background-color: #C94F78; color: #ffffff; text-transform: uppercase; letter-spacing: 1px; font-size: 12px; font-weight: 700; border-bottom: none; }
        td { color: #4a5568; font-weight: 600; }
        td.text-right, th.text-right { text-align: right; }
        .signature-box { float: right; width: 260px; text-align: center; border: 1px solid #edf2f7; border-radius: 16px; margin-top: 30px; font-size: 13.5px; background: #fff; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .signature-box .title { border-bottom: 1px dashed #edf2f7; padding: 12px; font-weight: 700; color: #718096; background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .signature-box .sign-area { height: 90px; position: relative; background: #fff; }
        .signature-box .sign-area img { height: 70px; position: absolute; top: 10px; left: 50%; transform: translateX(-50%); opacity: 0.9; }
        .signature-box .name { border-top: 1px dashed #edf2f7; padding: 12px; font-weight: 800; color: #C94F78; background: #FDF0F5; }
        .notes { margin-top: 180px; font-size: 13px; }
        .notes table { border: none; box-shadow: none; border-radius: 12px; overflow: hidden; }
        .notes th { background-color: #f8fafc; color: #C94F78; border-bottom: 1px solid #edf2f7; }
        .notes td { color: #718096; border-bottom: 1px dashed #edf2f7; font-weight: 500; font-size: 12.5px; }
        .print-btn { display: block; width: 100%; padding: 16px; background: linear-gradient(135deg, #C94F78, #b03d64); color: white; text-align: center; font-weight: 800; margin-bottom: 25px; border: none; cursor: pointer; border-radius: 50px; font-size: 16px; box-shadow: 0 10px 20px rgba(201, 79, 120, 0.25); text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s ease; }
        .print-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 25px rgba(201, 79, 120, 0.35); }
        @media print { 
            body { padding: 0; margin: 0; -webkit-print-color-adjust: exact; color-adjust: exact; print-color-adjust: exact; }
            .print-btn { display: none; } 
            .container { border: none; padding: 0; box-shadow: none; max-width: 100%; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ CETAK SLIP GAJI</button>
    <div class="container">
        <div class="header">
            <img src="/logo/lbqueen_logo.PNG" alt="Logo">
            <div class="header-text">
                <h2>SLIP GAJI KARYAWAN</h2>
                <h1>PT LBQUEEN CARE BEAUTY</h1>
                <p>Jl. Hos Cokroaminoto no.17 Kebon Jeruk Tanjung Karang Timur, Bandar Lampung.<br>
                Telp: +62 821-7617-1448 | Email: lbqueen.id@gmail.com</p>
            </div>
        </div>

        <table>
            <tr><th colspan="2" class="bg-pink">IDENTITAS KARYAWAN</th></tr>
            <tr><td style="width: 30%;">Nama</td><td><?= htmlspecialchars($data_kar['nama']) ?></td></tr>
            <tr><td>Jabatan</td><td><?= htmlspecialchars($data_kar['posisi']) ?> (<?= htmlspecialchars($data_kar['status_pegawai']) ?>)</td></tr>
            <tr><td>Periode</td><td><?= $periode ?></td></tr>
        </table>

        <table>
            <tr><th colspan="2" class="bg-pink">PENERIMA</th></tr>
            <tr><td>Gaji Pokok</td><td class="text-right">Rp <?= number_format($gaji_pokok, 0, ',', '.') ?></td></tr>
            <tr><td>Total Hari Kerja</td><td class="text-right"><?= $hari_kerja ?></td></tr>
            <tr><td colspan="2" style="border-left:none; border-right:none;"></td></tr>
            <tr><td>Tunjangan Uang Makan</td><td class="text-right">Rp <?= number_format($uang_makan, 0, ',', '.') ?></td></tr>
            <tr><td>Uang Kerajinan</td><td class="text-right">Rp <?= number_format($uang_kerajinan, 0, ',', '.') ?></td></tr>
            <tr><td>Uang Bonus Target</td><td class="text-right">Rp <?= number_format($uang_bonus, 0, ',', '.') ?></td></tr>
            <tr><th class="bg-pink">Total Penerima</th><th class="bg-pink text-right">Rp <?= number_format($total_penerima, 0, ',', '.') ?></th></tr>
        </table>

        <div class="signature-box">
            <div class="title">Best Regards</div>
            <div class="sign-area">
                <img src="/logo/ttd.png" alt="Sign">
            </div>
            <div class="name">HR & Digital Ops</div>
        </div>

        <div class="notes">
            <table style="margin-bottom: 0;">
                <tr><th class="bg-pink">Catatan:</th></tr>
                <tr><td>1. Cut off dilakukan setiap tanggal 25, jika lebih dari itu maka masuk ke bulan depan.</td></tr>
                <tr><td>2. Uang Kerajinan hangus apabila Alpa 2x atau lebih (khusus karyawan Kontrak).</td></tr>
                <tr><td>3. Jika ada kesalahan silakan hubungi tim HR.</td></tr>
            </table>
        </div>
    </div>
    <script> if(window.innerWidth > 800) { window.onload = function() { window.print(); } } </script>
</body>
</html>