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

// Ambil data karyawan
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
// SIMPAN FOTO LANGSUNG KE DATABASE (BASE64)
// =====================================================
$foto_raw = $_POST['foto'] ?? '';

if (empty($foto_raw)) {
    echo "❌ Kamera gagal menangkap gambar. Silakan coba lagi.";
    exit();
}

// Bersihkan data foto untuk keamanan database
$foto_final = $conn->real_escape_string($foto_raw);

// Simpan ke database MySQL
$sql = "INSERT INTO absensi (nik, waktu, jenis, lokasi, status, foto) 
        VALUES ('$nik', '$waktu_lengkap', '$jenis', '$lokasi', '$status', '$foto_final')";

if ($conn->query($sql) === TRUE) {
    $msg = "✅ Berhasil $jenis!";
    if ($status != '-') $msg .= " Status: $status.";
    echo $msg;
} else {
    echo "❌ Gagal menyimpan ke database: " . $conn->error;
}
?>