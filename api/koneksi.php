<?php
// api/koneksi.php

// Teknik Fallback: Mengutamakan $_ENV / getenv, jika kosong akan pakai data cadangan (hardcode)
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'sql12.freesqldatabase.com';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'sql12823338';
$db   = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'sql12823338';
$port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306;

// PERHATIAN: Ganti tulisan di bawah ini dengan password asli yang ada di email kamu!
$pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'PASSWORD_DARI_EMAIL_KAMU'; 

$conn = new mysqli($host, $user, $pass, $db, (int)$port);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Koneksi database gagal: ' . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");

// =====================================================
// JWT HELPER - Pengganti $_SESSION untuk Vercel
// =====================================================

$jwt_secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: 'hris_lbqueen_rahasia_2026';
define('JWT_SECRET', $jwt_secret);

// --- PERUBAHAN DI SINI ---
// Sebelumnya: 60 * 60 * 8 (8 Jam)
// Sekarang: 60 Detik * 60 Menit * 24 Jam * 30 Hari = 30 Hari
define('JWT_EXPIRE', 60 * 60 * 24 * 30); 

function jwt_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function jwt_base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function jwt_create($payload) {
    $header   = jwt_base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['exp'] = time() + JWT_EXPIRE;
    $payloadEnc = jwt_base64url_encode(json_encode($payload));
    $signature  = jwt_base64url_encode(hash_hmac('sha256', "$header.$payloadEnc", JWT_SECRET, true));
    return "$header.$payloadEnc.$signature";
}

function jwt_verify($token) {
    if (!$token) return null;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $sig] = $parts;
    $valid = jwt_base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($valid, $sig)) return null;
    $data = json_decode(jwt_base64url_decode($payload), true);
    if (!$data || $data['exp'] < time()) return null;
    return $data;
}

function get_token_from_cookie() {
    return $_COOKIE['hris_token'] ?? null;
}

function set_token_cookie($token) {
    setcookie('hris_token', $token, [
        'expires'  => time() + JWT_EXPIRE,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => true, // Vercel sudah pakai HTTPS otomatis
    ]);
}

function clear_token_cookie() {
    setcookie('hris_token', '', ['expires' => time() - 3600, 'path' => '/']);
}

function auth_required($conn) {
    $token = get_token_from_cookie();
    $payload = jwt_verify($token);
    if (!$payload) {
        header("Location: /login");
        exit();
    }
    $email = $conn->real_escape_string($payload['email']);
    $res = $conn->query("SELECT * FROM karyawan WHERE email = '$email'");
    $user = $res ? $res->fetch_assoc() : null;
    if (!$user) {
        clear_token_cookie();
        header("Location: /login");
        exit();
    }
    return $user;
}
?>