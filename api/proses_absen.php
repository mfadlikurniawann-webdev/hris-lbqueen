<?php
// api/proses_absen.php - VERSI FOLDER LOKAL (Fix Tanpa Foto)
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

// Logika status Check In (Batas 12:00)
$status = '-';
if ($jenis == 'Check In') {
    if ($jam_menit > '12:00') {
        echo "❌ Gagal: Batas waktu Check In (12:00 WIB) telah berakhir.";
        exit();
    } elseif ($jam_menit > '09:15') {
        $status = 'Telat';
    } else {
        $status = 'Hadir';
    }
}

// =====================================================
// 2. PROSES FOTO - SIMPAN KE FOLDER LOKAL /uploads
// =====================================================
$foto_raw = $_POST['foto'] ?? '';
$foto_url = null; 

if (!empty($foto_raw) && strpos($foto_raw, 'data:image') !== false) {
    try {
        // Tentukan path folder uploads (naik satu tingkat dari folder api)
        $target_dir = __DIR__ . '/../uploads/';
        
        // Buat folder jika belum ada
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Olah data base64
        $image_parts = explode(";base64,", $foto_raw);
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[1];
        $image_base64 = base64_decode($image_parts[1]);

        // Nama file unik: nik_jenis_timestamp.jpg
        $file_name = $nik . "_" . str_replace(' ', '', $jenis) . "_" . time() . "." . $image_type;
        $file_path = $target_dir . $file_name;

        // Simpan file ke sistem
        if (file_put_contents($file_path, $image_base64)) {
            // Simpan path relatif untuk database agar bisa dipanggil di HTML
            $foto_url = "/uploads/" . $file_name;
        }
    } catch (Exception $e) {
        $foto_url = null;
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
    
    // Beri info apakah foto masuk atau tidak
    if ($foto_url) {
        $msg .= " 📸 Foto tersimpan di server.";
    } else {
        $msg .= " (Tanpa foto)";
    }
    echo $msg;
} else {
    echo "❌ Gagal menyimpan ke database: " . $conn->error;
}
?>