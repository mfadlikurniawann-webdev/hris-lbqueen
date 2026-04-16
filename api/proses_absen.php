<?php
// api/proses_absen.php - VERSI FIXED (Cloudinary + Base64 Fallback)
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
$u        = $res_user ? $res_user->fetch_assoc() : null;
$lokasi   = $u['penempatan'] ?? '-';

// Cek duplikat
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
// 2. PROSES FOTO - UPLOAD KE CLOUDINARY
// =====================================================
$foto_raw = $_POST['foto'] ?? '';
$foto_url = null; // Yang disimpan ke DB adalah URL Cloudinary

if (empty($foto_raw)) {
    // Jika tidak ada foto, tetap lanjut absen tanpa foto
    $foto_url = null;
} else {
    // Ambil kredensial Cloudinary dari env variable
    $cloud_name = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? getenv('CLOUDINARY_CLOUD_NAME') ?? '';
    $api_key    = $_ENV['CLOUDINARY_API_KEY']    ?? getenv('CLOUDINARY_API_KEY')    ?? '';
    $api_secret = $_ENV['CLOUDINARY_API_SECRET'] ?? getenv('CLOUDINARY_API_SECRET') ?? '';

    if (!$cloud_name || !$api_key || !$api_secret) {
        // Cloudinary belum dikonfigurasi — lanjut absen tanpa foto
        $foto_url = null;
    } else {
        // Siapkan parameter upload ke Cloudinary
        $timestamp  = time();
        $jenis_slug = str_replace(' ', '', $jenis); // "CheckIn" atau "CheckOut"
        $public_id  = "hris_absen/{$nik}_{$jenis_slug}_{$timestamp}";

        // Buat signature (WAJIB untuk authenticated upload)
        $params_to_sign = "public_id={$public_id}&timestamp={$timestamp}";
        $signature      = sha1($params_to_sign . $api_secret);

        // Kirim ke Cloudinary menggunakan cURL
        $upload_url = "https://api.cloudinary.com/v1_1/{$cloud_name}/image/upload";

        $post_data = [
            'file'      => $foto_raw,      // Base64 langsung diterima Cloudinary
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
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            // cURL error, lanjut tanpa foto
            $foto_url = null;
        } else {
            $result = json_decode($response, true);
            if (isset($result['secure_url'])) {
                // Berhasil! Simpan URL Cloudinary
                $foto_url = $result['secure_url'];
            } else {
                // Upload gagal, lanjut tanpa foto
                $foto_url = null;
            }
        }
    }
}

// =====================================================
// 3. SIMPAN KE DATABASE
// Kolom foto menyimpan URL Cloudinary (atau NULL)
// =====================================================
if ($foto_url !== null) {
    $foto_escaped = $conn->real_escape_string($foto_url);
    $foto_val     = "'$foto_escaped'";
} else {
    $foto_val = "NULL";
}

$sql = "INSERT INTO absensi (nik, waktu, jenis, lokasi, status, foto) 
        VALUES ('$nik', '$waktu_lengkap', '$jenis', '$lokasi', '$status', $foto_val)";

if ($conn->query($sql) === TRUE) {
    $msg = "✅ Berhasil $jenis!";
    if ($status != '-') $msg .= " Status: $status.";
    if ($foto_url) {
        $msg .= " 📸 Foto tersimpan.";
    } else {
        $msg .= " (Foto tidak tersimpan - cek pengaturan Cloudinary)";
    }
    echo $msg;
} else {
    echo "❌ Gagal menyimpan ke database: " . $conn->error;
}
?>
