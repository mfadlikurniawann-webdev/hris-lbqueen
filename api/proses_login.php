<?php
// api/proses_login.php
include __DIR__ . '/koneksi.php';

$email    = $conn->real_escape_string($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    echo "<script>alert('Email dan password wajib diisi!'); window.location='/login';</script>";
    exit();
}

$result = $conn->query("SELECT * FROM karyawan WHERE email = '$email'");

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // Cek password (plain text sesuai database)
    if ($password === $user['password']) {
        $token = jwt_create(['email' => $user['email'], 'nik' => $user['nik']]);
        set_token_cookie($token);
        header("Location: /");
        exit();
    } else {
        echo "<script>alert('Password salah!'); window.location='/login';</script>";
    }
} else {
    echo "<script>alert('Email tidak terdaftar!'); window.location='/login';</script>";
}
?>