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
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            color: #2d3748;
            line-height: 1.5;
            background: #fdfdfd;
            margin: 0;
            padding: 40px 20px;
        }

        .page-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #edf2f7;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px dashed #edf2f7;
            padding-bottom: 20px;
            position: relative;
        }

        .header .logo {
            width: 100px;
            margin-bottom: 15px;
        }

        .header h2 {
            margin: 5px 0 10px;
            color: #C94F78;
            font-weight: 800;
            font-size: 24px;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        .header .company-name {
            font-size: 18px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 5px;
        }

        .header .address {
            color: #718096;
            font-size: 12px;
            max-width: 500px;
            margin: 0 auto 20px;
        }

        .header .period-pill {
            color: #718096;
            font-size: 13px;
            font-weight: 600;
            background: #f8fafc;
            display: inline-block;
            padding: 8px 24px;
            border-radius: 50px;
            border: 1px solid #edf2f7;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #fdfafb;
            border-radius: 16px;
            border: 1px solid #f9eef2;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #a0aec0;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 700;
            color: #2d3748;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 25px;
            border: 1px solid #edf2f7;
            border-radius: 16px;
            overflow: hidden;
        }

        th {
            background-color: #FDF0F5;
            color: #C94F78;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
            padding: 14px 20px;
            text-align: left;
        }

        td {
            padding: 12px 20px;
            border-bottom: 1px solid #edf2f7;
            color: #4a5568;
            font-weight: 500;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: 700;
            color: #1a202c;
        }

        .bg-pink-light {
            background-color: #fdf6f9;
        }

        .total-row td {
            background-color: #C94F78;
            color: #ffffff;
            font-weight: 800;
            font-size: 15px;
        }

        .notes-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid #edf2f7;
        }

        .notes-title {
            font-weight: 800;
            font-size: 13px;
            color: #C94F78;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notes-list {
            margin: 0;
            padding-left: 20px;
            color: #718096;
            font-size: 12px;
            line-height: 1.8;
        }

        .signature-container {
            margin-top: 40px;
            display: flex;
            justify-content: flex-end;
        }

        .signature-box {
            width: 260px;
            text-align: center;
            position: relative;
            padding: 20px;
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            background: #f8fafc;
        }

        .signature-box .date {
            font-size: 12px;
            color: #718096;
            margin-bottom: 10px;
        }

        .signature-box .role {
            font-size: 13px;
            color: #4a5568;
            margin-bottom: 15px;
        }

        .signature-area {
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .signature-img {
            height: 80px;
            z-index: 1;
            position: relative;
        }

        .stamp-img {
            position: absolute;
            height: 110px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            opacity: 0.7;
            z-index: 2;
            pointer-events: none;
        }

        .signature-name {
            margin-top: 15px;
            font-weight: 800;
            font-size: 14px;
            color: #1a202c;
            border-top: 1px solid #cbd5e1;
            padding-top: 10px;
        }

        .print-btn {
            display: block;
            width: 100%;
            max-width: 800px;
            margin: 0 auto 25px auto;
            padding: 16px;
            background: linear-gradient(135deg, #C94F78, #b03d64);
            color: white;
            text-align: center;
            text-decoration: none;
            font-weight: 800;
            font-size: 16px;
            border: none;
            cursor: pointer;
            border-radius: 50px;
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
            @page {
                size: A4;
                margin: 1cm;
            }

            body {
                background: #fff;
                padding: 0;
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page-container {
                box-shadow: none;
                border: none;
                width: 100%;
                max-width: 100%;
                padding: 0;
            }

            .print-btn {
                display: none;
            }

            .info-grid {
                background: #fdfafb !important;
                border: 1px solid #f9eef2 !important;
            }

            th {
                background-color: #FDF0F5 !important;
            }

            .total-row td {
                background-color: #C94F78 !important;
                color: #fff !important;
            }
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">🖨️ CETAK SLIP GAJI PDF</button>

    <div class="page-container">
        <div class="header">
            <img src="/logo/lbqueen_logo.png" class="logo" alt="LBQueen Logo">
            <h2>Slip Gaji Karyawan</h2>
            <div class="company-name">PT LBQUEEN CARE BEAUTY</div>
            <div class="address">Jl. Hos Cokroaminoto no.17 Kebon Jeruk Tanjung Karang Timur, Bandar Lampung</div>
            <div class="period-pill">
                Siklus: <b><?= date('d M Y', strtotime($start_date)) ?></b> s/d <b><?= date('d M Y', strtotime($end_date)) ?></b>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Nama Karyawan</span>
                <span class="info-value"><?= htmlspecialchars($data_kar['nama']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Jabatan / Posisi</span>
                <span class="info-value"><?= htmlspecialchars($data_kar['posisi']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">NIK</span>
                <span class="info-value"><?= htmlspecialchars($nik_target) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Periode Pembayaran</span>
                <span class="info-value"><?= $periode ?></span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Komponen Penerimaan</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Gaji Pokok</td>
                    <td class="text-right font-bold">Rp<?= number_format($gaji_pokok, 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td>Tunjangan Uang Makan <span style="font-size: 11px; color: #718096; margin-left: 5px;">(<?= $hari_kerja ?> Hari Kerja)</span></td>
                    <td class="text-right font-bold">Rp<?= number_format($uang_makan, 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td>Uang Kerajinan</td>
                    <td class="text-right font-bold">Rp<?= number_format($uang_kerajinan, 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td>Uang Bonus Target</td>
                    <td class="text-right font-bold">Rp<?= number_format($uang_bonus, 0, ',', '.') ?></td>
                </tr>
                <tr class="total-row">
                    <td>TOTAL PENERIMAAN BERSIH (TAKE HOME PAY)</td>
                    <td class="text-right">Rp<?= number_format($total_penerima, 0, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>

        <div class="notes-section">
            <div class="notes-title">📌 Catatan Penting:</div>
            <ul class="notes-list">
                <li>Cut off dilakukan setiap tanggal 25. Input setelah tanggal tersebut akan diproses pada periode bulan berikutnya.</li>
                <li>Jika terdapat ketidaksesuaian data, harap segera menghubungi tim HR maksimal 2 hari setelah slip diterima.</li>
                <li>Slip ini merupakan dokumen resmi internal PT LBQueen Care Beauty.</li>
            </ul>
        </div>

        <div class="signature-container">
            <div class="signature-box">
                <div class="date">Bandar Lampung, <?= date('d') ?> <?= $nama_bulan[(int)date('m') - 1] ?> <?= date('Y') ?></div>
                <div class="role">Mengetahui,</div>
                <div class="signature-area">
                    <img src="/logo/ttd.png" class="signature-img" alt="Tanda Tangan">
                    <img src="/logo/Cap_LBQueen.png" class="stamp-img" alt="Stempel Perusahaan">
                </div>
                <div class="signature-name">HR & Digital Ops</div>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            if (window.innerWidth > 800) {
                window.print();
            }
        }
    </script>
</body>

</html>