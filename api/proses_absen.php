<?php
// api/proses_absen.php - VERSI FIXED (Batas Absen 12:00)
include __DIR__ . '/koneksi.php';

// =====================================================
// 1. VERIFIKASI JWT
// =====================================================
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
$u         = $res_user ? $res_user->fetch_assoc() : null;
$lokasi   = $u['penempatan'] ?? '-';

// Cek duplikat
$cek = $conn->query("SELECT id FROM absensi WHERE nik='$nik' AND jenis='$jenis' AND DATE(waktu)='$hari_ini'");
if ($cek->num_rows > 0) {
    echo "⚠️ Anda sudah melakukan $jenis hari ini.";
    exit();
}

// =====================================================
// PERBAIKAN LOGIKA BATAS WAKTU (SUDAH DIUBAH KE 12:00)
// =====================================================
$status = '-';
if ($jenis == 'Check In') {
    if ($jam_menit > '12:00') { // <--- SEBELUMNYA 11:00
        echo "❌ Gagal: Batas waktu Check In (12:00 WIB) telah berakhir."; // <--- PESAN DISESUAIKAN
        exit();
    } elseif ($jam_menit > '09:15') {
        $status = 'Telat';
    } else {
        $status = 'Hadir';
    }
}

// =====================================================
// 2. PROSES FOTO - UPLOAD KE CLOUDINARY
// =====================================================
$foto_raw = $_POST['foto'] ?? '';
$foto_url = null; 

if (!empty($foto_raw)) {
    $cloud_name = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? getenv('CLOUDINARY_CLOUD_NAME') ?? '';
    $api_key    = $_ENV['CLOUDINARY_API_KEY']    ?? getenv('CLOUDINARY_API_KEY')    ?? '';
    $api_secret = $_ENV['CLOUDINARY_API_SECRET'] ?? getenv('CLOUDINARY_API_SECRET') ?? '';

    if ($cloud_name && $api_key && $api_secret) {
        $timestamp  = time();
        $jenis_slug = str_replace(' ', '', $jenis); 
        $public_id  = "hris_absen/{$nik}_{$jenis_slug}_{$timestamp}";

        $params_to_sign = "public_id={$public_id}&timestamp={$timestamp}";
        $signature      = sha1($params_to_sign . $api_secret);

        $upload_url = "https://api.cloudinary.com/v1_1/{$cloud_name}/image/upload";

        $post_data = [
            'file'      => $foto_raw,
            'public_id' => $public_id,
            'timestamp' => $timestamp,
            'api_key'   => $api_key,
            'signature' => $signature,
        ];

        $ch = curl_init($upload_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post_data,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);

        if (isset($result['secure_url'])) {
            $foto_url = $result['secure_url'];
        }
    }
}

// =====================================================
// 3. SIMPAN KE DATABASE
// =====================================================
$foto_val = ($foto_url !== null) ? "'" . $conn->real_escape_string($foto_url) . "'" : "NULL";

$sql = "INSERT INTO absensi (nik, waktu, jenis, lokasi, status, foto) 
        VALUES ('$nik', '$waktu_lengkap', '$jenis', '$lokasi', '$status', $foto_val)";

if ($conn->query($sql) === TRUE) {
    $msg = "✅ Berhasil $jenis!";
    if ($status != '-') $msg .= " Status: $status.";
    if ($foto_url) {
        $msg .= " 📸 Foto tersimpan.";
    } else {
        $msg .= " (Tanpa foto)";
    }
    echo $msg;
} else {
    echo "❌ Gagal menyimpan ke database: " . $conn->error;
}
?>