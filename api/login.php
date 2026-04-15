<?php
// api/login.php
// Jika sudah login, redirect ke beranda
include __DIR__ . '/koneksi.php';
$token   = get_token_from_cookie();
$payload = jwt_verify($token);
if ($payload) {
    header("Location: /");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login HRIS LBQueen Care Beauty</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; background-color: #FDF0F5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-card { max-width: 400px; width: 90%; border-radius: 20px; border: none; }
        .btn-pink { background-color: #C94F78; color: white; border-radius: 10px; }
        .btn-pink:hover { background-color: #A83E60; color: white; }
        .form-control:focus { border-color: #C94F78; box-shadow: 0 0 0 0.2rem rgba(201,79,120,0.15); }
    </style>
</head>
<body>
    <div class="card login-card shadow-lg p-4">
        <div class="text-center mb-4 mt-2">
            <img src="/logo/lbqueen_logo.PNG" alt="Logo LBQueen" class="mb-3"
                 style="max-height: 80px; object-fit: contain;"
                 onerror="this.style.display='none'">
            <h5 class="fw-bold" style="color: #C94F78;">HRIS LBQueen Care Beauty</h5>
            <p class="text-muted" style="font-size: 14px;">Silahkan masuk dengan email Anda</p>
        </div>
        <form action="/proses_login" method="POST">
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold">Email</label>
                <input type="email" name="email" class="form-control form-control-lg bg-light fs-6"
                       placeholder="contoh@gmail.com" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label text-muted small fw-bold">Password</label>
                <input type="password" name="password" class="form-control form-control-lg bg-light fs-6"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-pink w-100 py-3 fw-bold mb-2">Masuk</button>
        </form>
    </div>
</body>
</html>
