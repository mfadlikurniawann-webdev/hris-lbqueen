<?php
// api/proses_ganti_password.php
// Endpoint AJAX untuk mengganti kata sandi karyawan
header('Content-Type: application/json');

include __DIR__ . '/koneksi.php';

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit();
}

// Verifikasi token / sesi login
$token   = get_token_from_cookie();
$payload = jwt_verify($token);

if (!$payload) {
    echo json_encode(['success' => false, 'message' => 'Sesi Anda telah berakhir. Silakan login ulang.']);
    exit();
}

// Ambil input
$password_lama = $_POST['password_lama'] ?? '';
$password_baru = $_POST['password_baru'] ?? '';

// Validasi server-side
if (empty($password_lama) || empty($password_baru)) {
    echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
    exit();
}

if (strlen($password_baru) < 6) {
    echo json_encode(['success' => false, 'message' => 'Kata sandi baru minimal 6 karakter.']);
    exit();
}

if ($password_lama === $password_baru) {
    echo json_encode(['success' => false, 'message' => 'Kata sandi baru tidak boleh sama dengan kata sandi lama.']);
    exit();
}

// Ambil data user dari database
$email  = $conn->real_escape_string($payload['email']);
$result = $conn->query("SELECT * FROM karyawan WHERE email = '$email' LIMIT 1");

if (!$result || $result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Data pengguna tidak ditemukan.']);
    exit();
}

$user = $result->fetch_assoc();

// --------------------------------------------------------
// Verifikasi kata sandi lama
// Sistem menggunakan plain text password (sesuai database)
// Jika di masa depan ingin pakai password_hash, ubah bagian ini
// --------------------------------------------------------
if ($password_lama !== $user['password']) {
    echo json_encode(['success' => false, 'message' => 'Kata sandi saat ini tidak sesuai.']);
    exit();
}

// Update kata sandi baru ke database
// Password disimpan plain text sesuai sistem yang sudah ada
$password_baru_escaped = $conn->real_escape_string($password_baru);
$update = $conn->query(
    "UPDATE karyawan SET password = '$password_baru_escaped' WHERE email = '$email' LIMIT 1"
);

if ($update && $conn->affected_rows > 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Kata sandi berhasil diperbarui! Gunakan kata sandi baru saat login berikutnya.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memperbarui kata sandi. Silakan coba lagi.'
    ]);
}

$conn->close();
?>