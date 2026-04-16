<?php
// api/index.php - VERSI UTUH (Fix Batas 12:00 tanpa hapus fitur)
include __DIR__ . '/koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// PROTEKSI HALAMAN - Pakai JWT bukan $_SESSION
$karyawan = auth_required($conn);

// HAK AKSES ADMIN/HR
$posisi   = strtoupper($karyawan['posisi']);
$level    = strtoupper($karyawan['level_jabatan']);
$is_admin = in_array($posisi, ['HCG','HRD','HR']) || in_array($level, ['OWNER','DIREKTUR']);

// INISIAL NAMA
$nama_parts = explode(" ", $karyawan['nama']);
$inisial    = strtoupper(substr($nama_parts[0], 0, 1) . (isset($nama_parts[1]) ? substr($nama_parts[1], 0, 1) : ''));

function formatTanggal($tanggal) {
    if ($tanggal == '0000-00-00' || !$tanggal) return "-";
    return date('d-m-Y', strtotime($tanggal));
}

function formatTanggalIndo($tanggal) {
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $p = explode('-', $tanggal);
    return $p[2] . ' ' . $bulan[(int)$p[1]] . ' ' . $p[0];
}

// LOGIKA WAKTU ABSENSI HARI INI
$hari_ini             = date('Y-m-d');
$jam_sekarang         = date('H:i');
$batas_check_in_awal  = '08:30';
$batas_check_in_akhir = '12:00'; // <--- SUDAH DIUBAH KE JAM 12:00
$batas_check_out_awal = '18:00';

$belum_waktunya_in  = ($jam_sekarang < $batas_check_in_awal);
$lewat_batas_in     = ($jam_sekarang > $batas_check_in_akhir);
$belum_waktunya_out = ($jam_sekarang < $batas_check_out_awal);

$cek_in   = $conn->query("SELECT waktu, status FROM absensi WHERE nik='".$karyawan['nik']."' AND jenis='Check In' AND DATE(waktu)='$hari_ini'");
$data_in  = $cek_in->fetch_assoc();
$sudah_in = $cek_in->num_rows > 0;
$waktu_in = $sudah_in ? date('H:i', strtotime($data_in['waktu'])) : '--:--';
$status_in = $sudah_in ? $data_in['status'] : '-';

$cek_out   = $conn->query("SELECT waktu FROM absensi WHERE nik='".$karyawan['nik']."' AND jenis='Check Out' AND DATE(waktu)='$hari_ini'");
$data_out  = $cek_out->fetch_assoc();
$sudah_out = $cek_out->num_rows > 0;
$waktu_out = $sudah_out ? date('H:i', strtotime($data_out['waktu'])) : '--:--';

// PENENTUAN KAMERA AKTIF ATAU TIDAK
$show_camera = false;
if (!$sudah_in && !$belum_waktunya_in && !$lewat_batas_in) {
    $show_camera = true; // Buka kamera untuk Check In
} elseif ($sudah_in && !$sudah_out && !$belum_waktunya_out) {
    $show_camera = true; // Buka kamera untuk Check Out
}

// RIWAYAT ABSENSI PRIBADI
$full_history = $conn->query("SELECT 
    DATE(waktu) as tgl,
    MAX(CASE WHEN jenis='Check In' THEN waktu END) as in_time,
    MAX(CASE WHEN jenis='Check Out' THEN waktu END) as out_time,
    MAX(CASE WHEN jenis='Check In' THEN status END) as status_in,
    MAX(CASE WHEN jenis='Check In' THEN lokasi END) as lok_in,
    MAX(CASE WHEN jenis='Check Out' THEN lokasi END) as lok_out,
    MAX(CASE WHEN jenis='Check In' THEN foto END) as foto_in,
    MAX(CASE WHEN jenis='Check Out' THEN foto END) as foto_out
FROM absensi WHERE nik='".$karyawan['nik']."'
GROUP BY DATE(waktu) ORDER BY tgl DESC");

// DATA ADMIN (SEMUA KARYAWAN)
if ($is_admin) {
    $admin_history = $conn->query("SELECT 
        DATE(a.waktu) as tgl,
        a.nik, k.nama, k.posisi,
        MAX(CASE WHEN a.jenis='Check In' THEN a.waktu END) as in_time,
        MAX(CASE WHEN a.jenis='Check Out' THEN a.waktu END) as out_time,
        MAX(CASE WHEN a.jenis='Check In' THEN a.status END) as status_in,
        MAX(CASE WHEN a.jenis='Check In' THEN a.lokasi END) as lok_in,
        MAX(CASE WHEN a.jenis='Check Out' THEN a.lokasi END) as lok_out,
        MAX(CASE WHEN a.jenis='Check In' THEN a.foto END) as foto_in,
        MAX(CASE WHEN a.jenis='Check Out' THEN a.foto END) as foto_out
    FROM absensi a JOIN karyawan k ON a.nik=k.nik
    GROUP BY DATE(a.waktu), a.nik ORDER BY tgl DESC, in_time DESC LIMIT 100");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>HRIS Mobile - <?= $karyawan['nama'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/style/style.css">
    <style>
        .modal-content { border-radius: 20px; border: none; }
        .nav-pills .nav-link { color: #6c757d; border-radius: 10px; font-weight: bold; }
        .nav-pills .nav-link.active { background-color: var(--lb-pink); color: white; }
        .detail-box { border: 1px solid #eee; border-radius: 12px; padding: 15px; margin-bottom: 15px; }
        .status-box { border-radius: 15px; padding: 15px; display: flex; align-items: center; justify-content: space-between; }
        .status-box.terlambat { background-color: #fff4e5; border: 1px solid #ffe0b2; color: #e65100; }
        .status-box.hadir { background-color: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; }
        .btn-pink { background-color: var(--lb-pink); color: white; transition: 0.3s; }
        .btn-pink:hover { background-color: var(--lb-pink-hover); color: white; }
        .activity-box { background: #fff; border: 1px solid #eaeaea; border-radius: 15px; padding: 15px; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
        .table-admin td { vertical-align: middle; }
    </style>
</head>
<body>
<div class="mobile-container">

    <div id="screen-beranda" class="app-screen active">
        <div class="bg-pink p-4 rounded-bottom-4 shadow-sm">
            <div class="d-flex align-items-center justify-content-center mb-3 pb-3 border-bottom border-light border-opacity-25">
                <img src="/logo/lbqueen_logo.PNG" alt="Logo LBQueen" onerror="this.style.display='none'" style="height:40px; margin-right:12px; background:white; border-radius:8px; padding:4px;">
                <h5 class="mb-0 fw-bold text-white" style="font-size:16px;">HRIS LBQueen Care Beauty</h5>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="mb-0 text-white-50" id="date-display" style="font-size:13px;">Memuat tanggal...</p>
                    <h4 class="mb-0 fw-bold mt-1 text-white">Hai, <?= explode(" ", $karyawan['nama'])[0] ?>!</h4>
                </div>
                <i class="bi bi-bell fs-4 text-white"></i>
            </div>
        </div>

        <div class="p-3">
            <div class="card border-0 shadow-sm rounded-4 mb-4" style="margin-top:-25px;">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar-initials"><?= $inisial ?></div>
                    <div>
                        <h6 class="fw-bold mb-1"><?= $karyawan['posisi'] ?></h6>
                        <span class="badge bg-warning text-dark"><?= $karyawan['status_pegawai'] ?></span>
                        <span class="badge bg-secondary"><?= $karyawan['penempatan'] ?></span>
                    </div>
                </div>
            </div>

            <div class="text-center mt-2">
                <h1 class="display-3 fw-bold text-pink" id="clock-display">00:00:00</h1>
                <div class="alert alert-success d-inline-flex align-items-center gap-2 rounded-pill px-4 py-2 mt-2 mb-3">
                    <i class="bi bi-geo-alt-fill"></i> Lokasi Kerja: <?= $karyawan['penempatan'] ?>
                </div>

                <?php if ($show_camera): ?>
                    <div id="camera-wrapper" class="mb-3 mx-auto shadow-sm" style="width:200px; height:200px; border-radius:50%; overflow:hidden; border:4px solid var(--lb-pink-light); background:#eee;">
                        <video id="kamera" autoplay playsinline style="width:100%; height:100%; object-fit:cover; transform:scaleX(-1);"></video>
                        <canvas id="canvas_kamera" style="display:none;"></canvas>
                    </div>
                    <small class="text-muted d-block mb-3">Pastikan wajah terlihat jelas sebelum absen.</small>
                <?php else: ?>
                    <div class="alert alert-secondary text-center p-4 rounded-4 mb-4 mx-auto shadow-sm" style="max-width: 300px;">
                        <i class="bi bi-camera-video-off fs-1 text-muted mb-2 d-block"></i>
                        <?php if (!$sudah_in && $belum_waktunya_in): ?>
                            <h6 class="fw-bold mb-1">Kamera Belum Aktif</h6>
                            <p class="mb-0 small text-muted">Check In baru bisa dilakukan mulai pukul 08:30 WIB.</p>
                        <?php elseif (!$sudah_in && $lewat_batas_in): ?>
                            <h6 class="fw-bold mb-1 text-danger">Batas Waktu Habis</h6>
                            <p class="mb-0 small text-danger">Batas Check In (12:00 WIB) telah terlewat.</p>
                        <?php elseif ($sudah_in && !$sudah_out && $belum_waktunya_out): ?>
                            <h6 class="fw-bold mb-1">Belum Waktu Pulang</h6>
                            <p class="mb-0 small text-muted">Check Out baru bisa dilakukan mulai pukul 18:00 WIB.</p>
                        <?php elseif ($sudah_out): ?>
                            <h6 class="fw-bold mb-1 text-success">Absensi Selesai</h6>
                            <p class="mb-0 small text-muted">Terima kasih, selamat beristirahat!</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div id="absen-response" class="mb-3 fw-bold text-primary"></div>

                <div class="d-flex gap-3 mb-4">
                    <button class="btn <?= ($show_camera && !$sudah_in) ? 'btn-success' : 'btn-secondary' ?> flex-fill rounded-4 py-3 fw-bold shadow-sm"
                            <?= ($show_camera && !$sudah_in) ? "onclick=\"kirimAbsen('Check In')\"" : 'disabled' ?>>
                        <i class="bi bi-box-arrow-in-right me-1"></i> Check In
                    </button>
                    <button class="btn <?= ($show_camera && $sudah_in && !$sudah_out) ? 'btn-outline-danger' : 'btn-secondary' ?> flex-fill rounded-4 py-3 fw-bold shadow-sm"
                            <?= ($show_camera && $sudah_in && !$sudah_out) ? "onclick=\"kirimAbsen('Check Out')\"" : 'disabled' ?>>
                        <i class="bi bi-box-arrow-right me-1"></i> Check Out
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
                <h6 class="section-title mb-0 mt-0">Aktivitas Hari Ini</h6>
                <button class="btn btn-sm btn-link text-pink text-decoration-none fw-bold p-0" onclick="switchScreen('riwayat')">Lihat Riwayat</button>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-3 border-bottom pb-3">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px; height:40px;">
                            <i class="bi bi-clock-history text-pink fs-5"></i>
                        </div>
                        <div>
                            <small class="text-muted fw-bold d-block" style="font-size:11px; letter-spacing:0.5px;">JADWAL KERJA KAMU</small>
                            <span class="fw-bold fs-6">09:00 - 19:00 <small class="text-muted">WIB</small></span>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="activity-box">
                                <div class="d-flex align-items-start mb-3">
                                    <i class="bi bi-check-circle-fill fs-5 me-2 <?= $sudah_in ? 'text-success' : 'text-secondary opacity-50' ?>"></i>
                                    <div>
                                        <small class="fw-bold d-block text-dark lh-1 mb-1">Status</small>
                                        <small class="text-muted" style="font-size:11px;"><?= $sudah_in ? 'Sudah Check In' : 'Belum Check In' ?></small>
                                    </div>
                                </div>
                                <h4 class="fw-bold mb-1 <?= $sudah_in ? 'text-dark' : 'text-muted' ?>"><?= $waktu_in ?> <small class="text-muted" style="font-size:12px;">WIB</small></h4>
                                <div class="d-flex justify-content-between align-items-end mt-2">
                                    <small class="text-success fw-bold">Masuk</small>
                                    <?php if ($sudah_in):
                                        $b = 'bg-success';
                                        if ($status_in=='Telat') $b='bg-warning text-dark';
                                        if ($status_in=='Tidak Hadir') $b='bg-danger';
                                    ?>
                                        <span class="badge <?= $b ?> rounded-pill" style="font-size:10px; padding:4px 8px;"><?= $status_in ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="activity-box">
                                <div class="d-flex align-items-start mb-3">
                                    <i class="bi bi-check-circle-fill fs-5 me-2 <?= $sudah_out ? 'text-success' : 'text-secondary opacity-50' ?>"></i>
                                    <div>
                                        <small class="fw-bold d-block text-dark lh-1 mb-1">Status</small>
                                        <small class="text-muted" style="font-size:11px;"><?= $sudah_out ? 'Sudah Check Out' : 'Belum Check Out' ?></small>
                                    </div>
                                </div>
                                <h4 class="fw-bold mb-1 <?= $sudah_out ? 'text-dark' : 'text-muted' ?>"><?= $waktu_out ?> <small class="text-muted" style="font-size:12px;">WIB</small></h4>
                                <div class="d-flex justify-content-between align-items-end mt-2">
                                    <small class="text-secondary fw-bold">Pulang</small>
                                    <?php if ($sudah_out): ?>
                                        <span class="badge bg-info text-white rounded-pill" style="font-size:10px; padding:4px 8px;">Selesai</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="screen-riwayat" class="app-screen">
        <div class="bg-pink p-3 position-relative rounded-bottom-4 shadow-sm text-center mb-3">
            <h5 class="mb-0 text-white fw-bold mt-2">Riwayat Kehadiran</h5>
        </div>
        <div class="p-3 pt-0">
            <?php if ($full_history->num_rows > 0): ?>
                <?php while ($data = $full_history->fetch_assoc()):
                    $durasi_teks = '-';
                    if ($data['in_time'] && $data['out_time']) {
                        $diff = strtotime($data['out_time']) - strtotime($data['in_time']);
                        $durasi_teks = floor($diff/3600).' jam '.floor(($diff%3600)/60).' menit';
                    }
                    $waktu_in_view  = $data['in_time']  ? date('H.i', strtotime($data['in_time']))  : '-';
                    $waktu_out_view = $data['out_time'] ? date('H.i', strtotime($data['out_time'])) : '-';
                    $status_in_view = $data['status_in'] ?: 'Tidak Hadir';
                    $badge_bg  = 'bg-success';
                    if ($status_in_view=='Telat') $badge_bg='bg-warning text-dark';
                    if ($status_in_view=='Tidak Hadir') $badge_bg='bg-danger';
                    $modalData = htmlspecialchars(json_encode([
                        'tanggal'    => formatTanggalIndo($data['tgl']),
                        'nama'       => $karyawan['nama'],
                        'status'     => $status_in_view,
                        'durasi'     => $durasi_teks,
                        'in_time'    => $data['in_time']  ? date('H:i', strtotime($data['in_time']))  : '-',
                        'out_time'   => $data['out_time'] ? date('H:i', strtotime($data['out_time'])) : '-',
                        'in_lokasi'  => $data['lok_in']  ?: 'Tidak ada data lokasi',
                        'out_lokasi' => $data['lok_out'] ?: 'Tidak ada data lokasi',
                        'in_foto'    => $data['foto_in']  ?: '',
                        'out_foto'   => $data['foto_out'] ?: ''
                    ]));
                ?>
                <div class="card border-0 shadow-sm rounded-4 mb-3" onclick="bukaDetail(<?= $modalData ?>)" style="cursor:pointer;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                            <span class="fw-bold"><i class="bi bi-calendar-event me-2 text-pink"></i><?= formatTanggalIndo($data['tgl']) ?></span>
                            <span class="badge <?= $badge_bg ?> rounded-pill px-3"><?= $status_in_view ?></span>
                        </div>
                        <div class="row text-center mt-2">
                            <div class="col-5"><small class="text-muted d-block">Check In</small><span class="fw-bold fs-5 text-success"><?= $waktu_in_view ?></span></div>
                            <div class="col-2 d-flex align-items-center justify-content-center"><i class="bi bi-arrow-right text-muted"></i></div>
                            <div class="col-5"><small class="text-muted d-block">Check Out</small><span class="fw-bold fs-5 text-danger"><?= $waktu_out_view ?></span></div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-5 text-muted">Belum ada riwayat kehadiran tersimpan.</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="screen-layanan" class="app-screen">
        <div class="bg-pink p-3 text-center rounded-bottom-4 shadow-sm mb-3">
            <h5 class="mb-0 text-white fw-bold mt-2">Layanan HRIS</h5>
        </div>
        <div class="p-3">
            <?php if ($is_admin): ?>
            <h6 class="section-title mt-0 text-pink"><i class="bi bi-shield-lock-fill me-2"></i>Menu HR & Owner</h6>
            <div class="row g-3 mb-4">
                <div class="col-12" onclick="switchScreen('admin-absen')">
                    <div class="action-card shadow-sm" style="border-left:5px solid var(--lb-pink);">
                        <i class="bi bi-people-fill action-icon"></i>
                        <div><h6 class="fw-bold mb-0">Kehadiran Karyawan</h6><small class="text-muted">Pantau absensi seluruh tim & foto</small></div>
                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <h6 class="section-title mt-0">Pengajuan & Dokumen</h6>
            <div class="row g-3">
                <div class="col-12" onclick="showModernAlert('Informasi Cuti','Status Anda <b><?= $karyawan['status_pegawai'] ?></b>.<br>Cuti tahunan baru dapat digunakan setelah <b><?= formatTanggal($karyawan['akhir_probation']) ?></b>.<br><br><span class=\'text-pink fw-bold\'>Fitur Izin Sakit akan segera hadir.</span>','bi bi-calendar-x-fill','var(--lb-pink)')">
                    <div class="action-card shadow-sm">
                        <i class="bi bi-calendar-event action-icon"></i>
                        <div><h6 class="fw-bold mb-0">Pengajuan Cuti / Izin</h6><small class="text-muted">Cek kuota dan ajukan libur</small></div>
                    </div>
                </div>
                <div class="col-12" onclick="aksesGajiDitolak()">
                    <div class="action-card shadow-sm bg-light">
                        <i class="bi bi-lock-fill action-icon text-secondary"></i>
                        <div><h6 class="fw-bold mb-0 text-secondary">Slip Gaji <span class="badge bg-secondary ms-2" style="font-size:10px;">Terkunci</span></h6><small class="text-muted">Akses melalui portal HRD/Finance</small></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <div id="screen-admin-absen" class="app-screen">
        <div class="bg-pink p-3 position-relative rounded-bottom-4 shadow-sm text-center mb-3">
            <button class="btn-back" onclick="switchScreen('layanan')"><i class="bi bi-arrow-left fs-3 text-white"></i></button>
            <h5 class="mb-0 text-white fw-bold mt-2">Log Kehadiran Tim</h5>
        </div>
        <div class="p-3 pt-0">
            <?php if ($admin_history && $admin_history->num_rows > 0): ?>
                <div class="table-responsive bg-white rounded-4 shadow-sm border" style="overflow: hidden;">
                    <table class="table table-hover table-admin align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th class="px-3 py-3">Nama & Tgl</th>
                                <th class="text-center py-3">Masuk</th>
                                <th class="text-center py-3">Pulang</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($data = $admin_history->fetch_assoc()):
                                $status_adm = $data['status_in'] ?: 'Tidak Hadir';
                                $badge_adm  = 'bg-success';
                                if ($status_adm=='Telat') $badge_adm='bg-warning text-dark';
                                if ($status_adm=='Tidak Hadir') $badge_adm='bg-danger';
                                $modalDataAdm = htmlspecialchars(json_encode([
                                    'tanggal'    => formatTanggalIndo($data['tgl']),
                                    'nama'       => $data['nama'],
                                    'status'     => $status_adm,
                                    'in_time'    => $data['in_time'] ? date('H:i', strtotime($data['in_time'])) : '-',
                                    'out_time'   => $data['out_time'] ? date('H:i', strtotime($data['out_time'])) : '-',
                                    'in_lokasi'  => $data['lok_in'] ?: 'Tidak ada lokasi',
                                    'out_lokasi' => $data['lok_out'] ?: 'Tidak ada lokasi',
                                    'in_foto'    => $data['foto_in'] ?: '',
                                    'out_foto'   => $data['foto_out'] ?: ''
                                ]));
                            ?>
                            <tr onclick="bukaDetail(<?= $modalDataAdm ?>)" style="cursor:pointer;">
                                <td class="px-3 py-2">
                                    <span class="fw-bold d-block text-dark text-truncate" style="max-width: 130px;"><?= $data['nama'] ?></span>
                                    <small class="text-muted d-block"><?= date('d/m/Y', strtotime($data['tgl'])) ?></small>
                                    <span class="badge <?= $badge_adm ?> rounded-pill mt-1" style="font-size:9px;"><?= $status_adm ?></span>
                                </td>
                                <td class="text-center text-success fw-bold"><?= $data['in_time'] ? date('H:i', strtotime($data['in_time'])) : '-' ?></td>
                                <td class="text-center text-danger fw-bold"><?= $data['out_time'] ? date('H:i', strtotime($data['out_time'])) : '-' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">Belum ada data kehadiran dari tim.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div id="screen-profil" class="app-screen">
        <div class="bg-pink p-4 pb-5 text-center rounded-bottom-4 shadow-sm">
            <div class="avatar-initials mx-auto mt-2 mb-2 bg-white text-pink" style="width:80px; height:80px; font-size:32px;"><?= $inisial ?></div>
            <h4 class="text-white fw-bold mb-0"><?= $karyawan['nama'] ?></h4>
            <p class="text-white-50 mb-0"><?= $karyawan['posisi'] ?></p>
        </div>
        <div class="p-3" style="margin-top:-30px;">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-body p-0">
                    <div class="p-3 pb-0"><h6 class="section-title mt-0">Informasi Pribadi</h6></div>
                    <ul class="list-group list-group-flush rounded-4">
                        <li class="list-group-item d-flex justify-content-between p-3"><span class="text-muted">NIK</span><span class="fw-bold"><?= $karyawan['nik'] ?></span></li>
                        <li class="list-group-item d-flex justify-content-between p-3"><span class="text-muted">Email</span><span class="fw-bold small text-end"><?= $karyawan['email'] ?></span></li>
                        <li class="list-group-item d-flex justify-content-between p-3"><span class="text-muted">No. HP</span><span class="fw-bold"><?= $karyawan['no_hp'] ?></span></li>
                        <li class="list-group-item d-flex justify-content-between p-3"><span class="text-muted">Tgl Bergabung</span><span class="fw-bold"><?= formatTanggal($karyawan['tgl_bergabung']) ?></span></li>
                    </ul>
                </div>
            </div>
            <a href="/logout" class="btn btn-outline-danger w-100 rounded-4 py-3 fw-bold mt-3 shadow-sm">
                <i class="bi bi-box-arrow-right me-2"></i> Keluar Aplikasi
            </a>
        </div>
    </div>

    <div class="bottom-nav shadow-lg">
        <button class="nav-item-btn active" id="nav-beranda" onclick="switchScreen('beranda')"><i class="bi bi-house-door-fill"></i> Beranda</button>
        <button class="nav-item-btn" id="nav-riwayat" onclick="switchScreen('riwayat')"><i class="bi bi-clock-history"></i> Riwayat</button>
        <button class="nav-item-btn" id="nav-layanan" onclick="switchScreen('layanan')"><i class="bi bi-ui-checks-grid"></i> Layanan</button>
        <button class="nav-item-btn" id="nav-profil" onclick="switchScreen('profil')"><i class="bi bi-person-fill"></i> Profil</button>
    </div>
</div>

<div class="modal fade" id="modalDetailAbsen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold">Detail Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-pink small fw-bold mb-3" id="mdl-header"></p>
                <div id="mdl-status-box" class="status-box mb-3 shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <i id="mdl-status-icon" class="bi fs-4"></i>
                        <span class="fw-bold fs-5" id="mdl-status-text"></span>
                    </div>
                </div>
                <ul class="nav nav-pills nav-fill bg-light p-1 rounded-3 mb-3 shadow-sm" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pills-in" type="button">In</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-out" type="button">Out</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pills-in">
                        <div class="detail-box mb-2">Pukul: <span id="mdl-in-time" class="fw-bold"></span></div>
                        <div class="detail-box mb-2">Lokasi: <span id="mdl-in-lokasi" class="small"></span></div>
                        <img id="mdl-in-foto" src="" class="img-fluid rounded-3 shadow-sm" style="max-height: 250px; width: 100%; object-fit: cover;">
                    </div>
                    <div class="tab-pane fade" id="pills-out">
                        <div class="detail-box mb-2">Pukul: <span id="mdl-out-time" class="fw-bold"></span></div>
                        <div class="detail-box mb-2">Lokasi: <span id="mdl-out-lokasi" class="small"></span></div>
                        <img id="mdl-out-foto" src="" class="img-fluid rounded-3 shadow-sm" style="max-height: 250px; width: 100%; object-fit: cover;">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modernAlertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-body text-center p-4 pt-5">
                <div id="modernAlertIcon" class="mb-3"></div>
                <h5 class="fw-bold mb-3" id="modernAlertTitle"></h5>
                <p class="text-muted mb-4" id="modernAlertMessage" style="font-size:14px;"></p>
                <button type="button" class="btn btn-pink w-100 rounded-pill py-2 fw-bold" data-bs-dismiss="modal">Mengerti</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const userNIK = '<?= $karyawan['nik'] ?>';
    const screens = ['beranda','riwayat','layanan','profil','admin-absen'];

    function switchScreen(target) {
        screens.forEach(s => {
            const el = document.getElementById('screen-'+s);
            const nav = document.getElementById('nav-'+s);
            if (el) el.classList.remove('active');
            if (nav) nav.classList.remove('active');
        });
        document.getElementById('screen-'+target).classList.add('active');
        const n = document.getElementById('nav-'+target);
        if (n) n.classList.add('active');
        window.scrollTo(0,0);
    }

    function bukaDetail(data) {
        document.getElementById('mdl-header').innerText = data.nama + ' • ' + data.tanggal;
        document.getElementById('mdl-status-text').innerText = data.status;
        const box = document.getElementById('mdl-status-box');
        const icon = document.getElementById('mdl-status-icon');
        
        if (data.status === 'Telat') {
            box.className = 'status-box terlambat mb-3';
            icon.className = 'bi bi-exclamation-circle-fill fs-4';
        } else if (data.status === 'Hadir') {
            box.className = 'status-box hadir mb-3';
            icon.className = 'bi bi-check-circle-fill fs-4';
        } else {
            box.className = 'status-box bg-light mb-3';
            icon.className = 'bi bi-x-circle-fill fs-4';
        }

        document.getElementById('mdl-in-time').innerText = data.in_time;
        document.getElementById('mdl-out-time').innerText = data.out_time;
        document.getElementById('mdl-in-lokasi').innerText = data.in_lokasi;
        document.getElementById('mdl-out-lokasi').innerText = data.out_lokasi;
        document.getElementById('mdl-in-foto').src = data.in_foto || 'https://placehold.co/300x400?text=Tidak+Ada+Foto';
        document.getElementById('mdl-out-foto').src = data.out_foto || 'https://placehold.co/300x400?text=Tidak+Ada+Foto';
        new bootstrap.Modal(document.getElementById('modalDetailAbsen')).show();
    }

    function showModernAlert(title, message, iconClass, iconColor) {
        document.getElementById('modernAlertTitle').innerText = title;
        document.getElementById('modernAlertMessage').innerHTML = message;
        document.getElementById('modernAlertIcon').innerHTML = `<i class="${iconClass}" style="font-size:3.5rem; color:${iconColor};"></i>`;
        new bootstrap.Modal(document.getElementById('modernAlertModal')).show();
    }

    function aksesGajiDitolak() {
        showModernAlert('Akses Terkunci','Gaji hanya dapat diakses melalui portal resmi.','bi bi-lock-fill','#6c757d');
    }

    const video = document.getElementById('kamera');
    const canvas = document.getElementById('canvas_kamera');
    let kameraAktif = false;
    if (video) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
            .then(stream => { video.srcObject = stream; kameraAktif = true; })
            .catch(() => {
                const w = document.getElementById('camera-wrapper');
                if (w) w.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 bg-light text-danger small">Kamera Error</div>';
            });
    }

    function kirimAbsen(jenis) {
        if (!confirm("Kirim " + jenis + "?")) return;
        const resp = document.getElementById('absen-response');
        resp.innerText = "Mengirim...";
        
        const fd = new FormData();
        fd.append('jenis_absen', jenis);
        fd.append('nik', userNIK);
        
        if (kameraAktif && video && canvas) {
            canvas.width = video.videoWidth || 300;
            canvas.height = video.videoHeight || 400;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            fd.append('foto', canvas.toDataURL('image/jpeg', 0.8));
        }

        fetch('/proses_absen', { method: 'POST', body: fd })
            .then(res => res.text())
            .then(data => { resp.innerText = data; setTimeout(() => location.reload(), 1500); });
    }

    setInterval(() => {
        const now = new Date();
        if (document.getElementById('clock-display'))
            document.getElementById('clock-display').innerText = now.toLocaleTimeString('id-ID') + ' WIB';
        if (document.getElementById('date-display'))
            document.getElementById('date-display').innerText = now.toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
    }, 1000);
</script>
</body>
</html>