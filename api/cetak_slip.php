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
        body { font-family: 'Arial', sans-serif; background: #fff; padding: 20px; color: #000; }
        .container { max-width: 800px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; }
        .header { display: flex; align-items: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header img { width: 80px; height: 80px; object-fit: contain; }
        .header-text { flex: 1; text-align: center; }
        .header-text h2 { margin: 0; font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .header-text h1 { margin: 5px 0; font-size: 20px; font-weight: bold; }
        .header-text p { margin: 0; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px; }
        th, td { border: 1px solid #000; padding: 8px 12px; text-align: left; }
        .bg-pink { background-color: #d8a2b5; font-weight: bold; }
        .text-right { text-align: right; }
        .signature-box { float: right; width: 250px; text-align: center; border: 1px solid #000; margin-top: 20px; font-size: 13px; }
        .signature-box .title { border-bottom: 1px solid #000; padding: 8px; font-weight: bold; }
        .signature-box .sign-area { height: 80px; position: relative; }
        .signature-box .sign-area img { height: 60px; position: absolute; top: 10px; left: 50%; transform: translateX(-50%); opacity: 0.8; }
        .signature-box .name { border-top: 1px solid #000; padding: 8px; font-weight: bold; }
        .notes { margin-top: 150px; font-size: 12px; }
        .print-btn { display: block; width: 100%; padding: 15px; background: #C94F78; color: white; text-align: center; font-weight: bold; margin-bottom: 20px; border: none; cursor: pointer; }
        @media print { .print-btn { display: none; } .container { border: none; padding: 0; } }
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
                <p>Jl. Hos Cokroaminoto no.17 Kebon Jeruk Tanjung Karang Timur, Bandar Lampung</p>
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
                <img src="https://upload.wikimedia.org/wikipedia/commons/f/f8/Fictional_signature.svg" alt="Sign">
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