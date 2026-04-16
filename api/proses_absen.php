<?php
// api/proses_absen.php
include __DIR__ . '/koneksi.php';

// Verifikasi JWT
$token   = get_token_from_cookie();
$payload = jwt_verify($token);
if (!$payload) {
    echo "❌ Sesi tidak valid. Silakan login ulang.";
    exit();
}

if (!isset($_POST['nik']) || !isset($_POST['jenis_absen'])) {
    echo "❌ Data tidak lengkap.";
    exit();
}

$nik   = $conn->real_escape_string($_POST['nik']);
$jenis = $conn->real_escape_string($_POST['jenis_absen']);

// Validasi NIK cocok dengan token
if ($nik !== $payload['nik']) {
    echo "❌ Akses tidak diizinkan.";
    exit();
}

date_default_timezone_set('Asia/Jakarta');
$waktu_lengkap = date('Y-m-d H:i:s');
$hari_ini      = date('Y-m-d');
$jam_menit     = date('H:i');
$jam_file      = date('H-i-s'); // Format jam untuk nama file (tanpa titik dua)
$tgl_file      = date('d-m-Y');

// Ambil Nama Karyawan untuk penamaan file yang rapi
$res_user = $conn->query("SELECT nama, penempatan FROM karyawan WHERE nik='$nik'");
$u = $res_user->fetch_assoc();
$nama_karyawan = $u['nama'] ?? 'Karyawan';
$lokasi   = $u['penempatan'] ?? '-';

// Cek duplikat absen hari ini
$cek = $conn->query("SELECT id FROM absensi WHERE nik='$nik' AND jenis='$jenis' AND DATE(waktu)='$hari_ini'");
if ($cek->num_rows > 0) {
    echo "⚠️ Anda sudah melakukan $jenis hari ini.";
    exit();
}

// Logika status Check In
$status = '-';
if ($jenis == 'Check In') {
    if ($jam_menit > '11:00') {
        echo "❌ Gagal: Batas waktu Check In (11:00 WIB) telah berakhir.";
        exit();
    } elseif ($jam_menit > '09:15') {
        $status = 'Telat';
    } else {
        $status = 'Hadir';
    }
}

// =====================================================
// UPLOAD FOTO KE GOOGLE DRIVE VIA APPS SCRIPT
// =====================================================
$nama_file_foto = NULL;

// URL Aplikasi Web (Apps Script) yang sudah disesuaikan dari screenshot
$apps_script_url = "https://script.google.com/macros/s/AKfycbwAI0PZal-xWEZuec5GMUvfJIVWazMSjYg1G2j0W7mp8EEXjZQnVyy84zfUKRrr3NA5ag/exec";

if (isset($_POST['foto']) && !empty($_POST['foto'])) {
    
    // FORMAT NAMA FILE YANG RAPI: [Check In] - M Fadli Kurniawan - 16-04-2026 08-30-00.jpg
    $nama_file_rapi = "[$jenis] - $nama_karyawan - {$tgl_file} {$jam_file}.jpg";
    
    $postData = json_encode([
        "foto" => $_POST['foto'],
        "namaFile" => $nama_file_rapi
    ]);

    $ch = curl_init($apps_script_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Wajib untuk mengikuti redirect Google
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    
    // Cek apakah balasan dari Google Drive sukses
    if (isset($result['status']) && $result['status'] === 'success') {
        $nama_file_foto = $result['url'];
    } else {
        $err = $result['message'] ?? 'Koneksi ke Google Drive terputus / diblokir.';
        echo "❌ Gagal mengunggah foto ke Google Drive: $err";
        exit(); // Hentikan proses, JANGAN simpan ke database
    }
} else {
    echo "❌ Akses Kamera Ditolak / Foto Kosong.";
    exit();
}

// Simpan ke database MySQL
$foto_val = $nama_file_foto ? "'" . $conn->real_escape_string($nama_file_foto) . "'" : "NULL";

$sql = "INSERT INTO absensi (nik, waktu, jenis, lokasi, status, foto) 
        VALUES ('$nik', '$waktu_lengkap', '$jenis', '$lokasi', '$status', $foto_val)";

if ($conn->query($sql) === TRUE) {
    // Tanda berhasil
    $msg = "✅ Berhasil $jenis ke Drive!";
    if ($status != '-') $msg .= " ($status)";
    echo $msg;
} else {
    echo "❌ Gagal menyimpan ke database MySQL: " . $conn->error;
}
?>