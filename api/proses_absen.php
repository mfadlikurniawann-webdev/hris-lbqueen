<?php
// api/proses_absen.php - FINAL CLOUDINARY VERSION
include __DIR__ . '/koneksi.php';

// 1. VERIFIKASI JWT (Tetap seperti aslinya)
$token   = get_token_from_cookie();
$payload = jwt_verify($token);
if (!$payload) {
    echo "❌ Sesi tidak valid.";
    exit();
}

if (!isset($_POST['nik']) || !isset($_POST['jenis_absen'])) {
    echo "❌ Data tidak lengkap.";
    exit();
}

$nik   = $conn->real_escape_string($_POST['nik']);
$jenis = $conn->real_escape_string($_POST['jenis_absen']);

date_default_timezone_set('Asia/Jakarta');
$waktu_lengkap = date('Y-m-d H:i:s');
$hari_ini      = date('Y-m-d');
$jam_menit     = date('H:i:s');

// Cek duplikat
$cek = $conn->query("SELECT id FROM absensi WHERE nik='$nik' AND jenis='$jenis' AND DATE(waktu)='$hari_ini'");
if ($cek->num_rows > 0) {
    echo "⚠️ Anda sudah $jenis hari ini.";
    exit();
}

// Logika status Check In (Batas 12:00)
$status = '-';
if ($jenis == 'Check In') {
    if ($jam_menit > '13:00') { // <--- UBAH DARI 12:00 KE 13:00
        echo "❌ Gagal: Batas waktu Check In (13:00 WIB) telah berakhir.";
        exit();
    } elseif ($jam_menit > '09:15') {
        $status = 'Telat';
    } else {
        $status = 'Hadir';
    }
}

// =====================================================
// 2. PROSES FOTO KE CLOUDINARY
// =====================================================
$foto_raw = $_POST['foto'] ?? '';
$foto_url = null;
$debug_msg = "";

if (!empty($foto_raw) && strlen($foto_raw) > 100) {
    
    // Menggunakan getenv() untuk membaca Environment Variables Vercel
    $c_name   = getenv('CLOUDINARY_CLOUD_NAME');
    $c_key    = getenv('CLOUDINARY_API_KEY');
    $c_secret = getenv('CLOUDINARY_API_SECRET');

    if (!$c_name || !$c_key || !$c_secret) {
        $debug_msg = " (Error: Env Vercel Kosong)";
    } else {
        $timestamp = time();
        $public_id = "hris_absen/" . $nik . "_" . $timestamp;
        $params_to_sign = "public_id=$public_id&timestamp=$timestamp";
        $signature = sha1($params_to_sign . $c_secret);

        $ch = curl_init("https://api.cloudinary.com/v1_1/$c_name/image/upload");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file'      => $foto_raw,
            'api_key'   => $c_key,
            'timestamp' => $timestamp,
            'public_id' => $public_id,
            'signature' => $signature
        ]);

        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);

        if (isset($result['secure_url'])) {
            $foto_url = $result['secure_url'];
        } else {
            // Jika gagal, ambil pesan error dari Cloudinary
            $err_desc = $result['error']['message'] ?? 'Upload Gagal';
            $debug_msg = " (Cloudinary: $err_desc)";
        }
    }
} else {
    $debug_msg = " (Data foto kosong/tidak terkirim)";
}

// 3. SIMPAN KE DATABASE
$foto_val = ($foto_url !== null) ? "'" . $conn->real_escape_string($foto_url) . "'" : "NULL";

// Ambil lokasi penempatan
$res_user = $conn->query("SELECT penempatan FROM karyawan WHERE nik='$nik'");
$u = $res_user->fetch_assoc();
$lokasi = $u['penempatan'] ?? '-';

$sql = "INSERT INTO absensi (nik, waktu, jenis, lokasi, status, foto) 
        VALUES ('$nik', '$waktu_lengkap', '$jenis', '$lokasi', '$status', $foto_val)";

if ($conn->query($sql) === TRUE) {
    $out = "✅ Berhasil $jenis!";
    if ($status != '-') $out .= " Status: $status.";
    echo ($foto_url) ? $out . " 📸 Foto OK." : $out . $debug_msg;
} else {
    echo "❌ DB Error: " . $conn->error;
}
?>