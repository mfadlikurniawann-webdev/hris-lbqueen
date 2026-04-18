<?php
// api/proses_absen.php
include __DIR__ . '/koneksi.php';

// =====================================================
// FIX: Terima input JSON (dari script.js baru) ATAU FormData (fallback)
// =====================================================
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $jsonData        = json_decode(file_get_contents('php://input'), true);
    $_POST['nik']        = $jsonData['nik']        ?? '';
    $_POST['jenis_absen'] = $jsonData['jenis_absen'] ?? '';
    $_POST['foto']       = $jsonData['foto']       ?? '';
}

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

// Ambil data karyawan untuk lokasi
$res_user = $conn->query("SELECT penempatan FROM karyawan WHERE nik='$nik'");
$u = $res_user->fetch_assoc();
$lokasi = $u['penempatan'] ?? '-';

// Cek duplikat absen hari ini
$cek = $conn->query("SELECT id FROM absensi WHERE nik='$nik' AND jenis='$jenis' AND DATE(waktu)='$hari_ini'");
if ($cek->num_rows > 0) {
    echo "⚠️ Anda sudah melakukan $jenis hari ini.";
    exit();
}

// Logika status Check In
$status = '-';
if ($jenis == 'Check In') {
    if ($jam_menit > '13:00') {
        echo "❌ Gagal: Batas waktu Check In (13:00 WIB) telah berakhir.";
        exit();
    } elseif ($jam_menit > '09:15') {
        $status = 'Telat';
    } else {
        $status = 'Hadir';
    }
}

// =====================================================
// SIMPAN FOTO LANGSUNG KE DATABASE (BASE64)
// =====================================================
$foto_raw = $_POST['foto'] ?? '';

if (empty($foto_raw)) {
    echo "❌ Kamera gagal menangkap gambar. Silakan coba lagi.";
    exit();
}

// Gunakan prepared statement agar base64 panjang tidak terpotong
// dan aman dari SQL injection
$stmt = $conn->prepare("INSERT INTO absensi (nik, waktu, jenis, lokasi, status, foto) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo "❌ Gagal menyiapkan query: " . $conn->error;
    exit();
}

$stmt->bind_param("ssssss", $nik, $waktu_lengkap, $jenis, $lokasi, $status, $foto_raw);

if ($stmt->execute()) {
    $msg = "✅ Berhasil $jenis!";
    if ($status != '-') $msg .= " Status: $status.";
    echo $msg;
} else {
    echo "❌ Gagal menyimpan ke database: " . $stmt->error;
}

$stmt->close();
?>