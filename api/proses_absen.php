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

// Cek duplikat absen hari ini
$cek = $conn->query("SELECT id FROM absensi WHERE nik='$nik' AND jenis='$jenis' AND DATE(waktu)='$hari_ini'");
if ($cek->num_rows > 0) {
    echo "⚠️ Anda sudah melakukan $jenis hari ini.";
    exit();
}

// Logika status Check In
$status = '-';
if ($jenis == 'Check In') {
    if ($jam_menit > '10:30') {
        echo "❌ Gagal: Batas waktu Check In (10:30 WIB) telah berakhir.";
        exit();
    } elseif ($jam_menit > '09:15') {
        $status = 'Telat';
    } else {
        $status = 'Hadir';
    }
}

// =====================================================
// UPLOAD FOTO KE CLOUDINARY DENGAN FALLBACK
// =====================================================
$nama_file_foto = NULL;

if (isset($_POST['foto']) && !empty($_POST['foto'])) {
    // Menggunakan teknik fallback seperti di koneksi.php
    $cloud_name = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? getenv('CLOUDINARY_CLOUD_NAME') ?: 'dr54qn228';
    $api_key    = $_ENV['CLOUDINARY_API_KEY'] ?? getenv('CLOUDINARY_API_KEY') ?: '512863719148927';
    $api_secret = $_ENV['CLOUDINARY_API_SECRET'] ?? getenv('CLOUDINARY_API_SECRET') ?: 'Ypn54AwSMFH0Tn_9SR5IPZ76dz8';

    if ($cloud_name && $api_key && $api_secret) {
        $img_data   = $_POST['foto'];
        $timestamp  = time();
        $jenis_slug = str_replace(' ', '', $jenis);
        $public_id  = "hris_absen/{$nik}_{$jenis_slug}_{$timestamp}";
        
        // Pembuatan Signature untuk keamanan Cloudinary
        $signature  = sha1("public_id={$public_id}&timestamp={$timestamp}{$api_secret}");

        $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloud_name}/image/upload");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file'      => $img_data,
                'public_id' => $public_id,
                'timestamp' => $timestamp,
                'api_key'   => $api_key,
                'signature' => $signature,
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        
        // Jika berhasil, ambil link gambarnya
        if (isset($result['secure_url'])) {
            $nama_file_foto = $result['secure_url'];
        }
    }
}

// Ambil lokasi dari data karyawan
$res_kar  = $conn->query("SELECT penempatan FROM karyawan WHERE nik='$nik'");
$kar_data = $res_kar->fetch_assoc();
$lokasi   = $kar_data['penempatan'] ?? '-';

// Simpan ke database
$foto_sql = $nama_file_foto ? $conn->real_escape_string($nama_file_foto) : NULL;
$foto_val = $foto_sql ? "'$foto_sql'" : "NULL";

$sql = "INSERT INTO absensi (nik, waktu, jenis, lokasi, status, foto) 
        VALUES ('$nik', '$waktu_lengkap', '$jenis', '$lokasi', '$status', $foto_val)";

if ($conn->query($sql) === TRUE) {
    $msg = "✅ Berhasil $jenis";
    if ($status != '-') $msg .= " ($status)";
    echo $msg . " pada " . date('H:i:s') . " WIB";
} else {
    echo "❌ Gagal menyimpan: " . $conn->error;
}
?>