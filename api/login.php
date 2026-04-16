<?php
// api/login.php
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
    <title>Login HRIS | LBQueen Care Beauty</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-pink: #C94F78;
            --dark-pink: #A83E60;
            --soft-pink: #FDF0F5;
        }

        body, html {
            height: 100%;
            margin: 0;
            font-family: 'DM Sans', sans-serif;
            overflow: hidden;
        }

        .login-container {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        /* Sisi Kiri (Branding) */
        .left-side {
            flex: 1.2;
            background-color: var(--primary-pink);
            background-image: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.05) 0%, transparent 40%),
                              radial-gradient(circle at 80% 70%, rgba(255,255,255,0.05) 0%, transparent 40%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 10%;
            position: relative;
        }

        .left-side .logo-section img {
            max-height: 50px;
            margin-bottom: 2rem;
            filter: brightness(0) invert(1); /* Membuat logo jadi putih jika perlu */
        }

        .left-side h1 {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .left-side p.description {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2.5rem;
            max-width: 450px;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 1.05rem;
        }

        .feature-item i {
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 0.8rem;
        }

        .footer-text {
            position: absolute;
            bottom: 30px;
            font-size: 0.85rem;
            opacity: 0.7;
        }

        /* Sisi Kanan (Form) */
        .right-side {
            flex: 1;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }

        .login-card h2 {
            color: var(--primary-pink);
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
        }

        .login-card p.subtitle {
            text-align: center;
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 600;
            color: #444;
            font-size: 0.9rem;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
            color: #adb5bd;
        }

        .form-control {
            border-left: none;
            background-color: #f8f9fa;
            padding: 12px;
            font-size: 0.95rem;
        }

        .form-control:focus {
            background-color: #fff;
            box-shadow: none;
            border-color: #dee2e6;
        }

        .btn-masuk {
            background-color: var(--primary-pink);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            width: 100%;
            margin-top: 10px;
            transition: 0.3s;
        }

        .btn-masuk:hover {
            background-color: var(--dark-pink);
            transform: translateY(-2px);
        }

        @media (max-width: 992px) {
            .left-side { display: none; }
            .right-side { background-color: var(--soft-pink); }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="left-side">
            <div class="logo-section">
                <img src="/logo/lbqueen_logo.PNG" alt="Logo LBQueen">
                <span class="ms-2 fw-bold fs-4">LBQueen Care Beauty</span>
            </div>
            
            <h1>Kelola Karyawan & <br>SDM Lebih Efisien</h1>
            <p class="description">
                Sistem <b>Human Resource Information System (HRIS)</b> terpadu untuk mengatur absensi, penggajian, dan performa tim dalam satu dasbor.
            </p>

            <ul class="feature-list">
                <li class="feature-item"><i class="bi bi-check2"></i> Manajemen Absensi & Shift Real-time</li>
                <li class="feature-item"><i class="bi bi-check2"></i> Penggajian (Payroll) Otomatis</li>
                <li class="feature-item"><i class="bi bi-check2"></i> Database Karyawan Terpusat</li>
                <li class="feature-item"><i class="bi bi-check2"></i> Pengajuan Cuti & Lembur Digital</li>
            </ul>

            <div class="footer-text">
                © 2026 LBQueen Care Beauty. All rights reserved.
            </div>
        </div>

        <div class="right-side">
            <div class="login-card">
                <h2>Selamat Datang</h2>
                <p class="subtitle">Masuk ke akun HRIS Anda untuk melanjutkan</p>

                <form action="/proses_login" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Email / ID Pegawai</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="Masukkan email" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-masuk">Masuk</button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>