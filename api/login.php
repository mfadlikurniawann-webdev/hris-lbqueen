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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --lbq-pink: #b6416a; /* Warna pink persis seperti di gambar */
            --lbq-pink-hover: #9c365a;
        }

        body, html {
            margin: 0;
            padding: 0;
            height: 100vh;
            font-family: 'DM Sans', sans-serif;
            overflow: hidden; /* Mencegah scroll */
        }

        .split-container {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        /* --- SISI KIRI (Branding) --- */
        .left-panel {
            flex: 1;
            background-color: var(--lbq-pink);
            color: white;
            position: relative;
            padding: 4rem 5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
        }

        /* Lingkaran Background */
        .bg-circle {
            position: absolute;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.04);
            z-index: 1;
        }
        .circle-1 {
            width: 600px;
            height: 600px;
            top: -100px;
            right: -150px;
        }
        .circle-2 {
            width: 450px;
            height: 450px;
            bottom: -100px;
            left: 25%;
        }

        .left-content {
            z-index: 2; /* Agar teks di atas lingkaran */
        }

        .logo-box {
            background: white;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
        }
        
        .logo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .left-panel h1 {
            font-weight: 700;
            font-size: 2.2rem;
            margin-top: 2rem;
            margin-bottom: 1rem;
            line-height: 1.3;
        }

        .left-panel p.desc {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 2.5rem;
            max-width: 90%;
            line-height: 1.6;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin-bottom: 4rem;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            opacity: 0.95;
        }

        .check-icon {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 12px;
        }

        .copyright-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            width: 300px;
            margin-bottom: 1.5rem;
        }

        .copyright-text {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        /* --- SISI KANAN (Form Login) --- */
        .right-panel {
            flex: 1;
            background-color: #fafbfc; /* Putih sedikit abu persis gambar */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: white;
            padding: 3rem 2.5rem;
            border-radius: 16px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.03);
        }

        .login-card h3 {
            color: var(--lbq-pink);
            font-weight: 700;
            font-size: 1.4rem;
            text-align: center;
            margin-bottom: 5px;
        }

        .login-card p.subtitle {
            text-align: center;
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 2rem;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #444;
            margin-bottom: 8px;
        }

        /* Styling Input Group agar persis dengan referensi */
        .custom-input-group {
            display: flex;
            align-items: center;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 0 12px;
            background-color: white;
            transition: 0.3s;
        }

        .custom-input-group:focus-within {
            border-color: var(--lbq-pink);
            box-shadow: 0 0 0 0.2rem rgba(182, 65, 106, 0.1);
        }

        .custom-input-group i {
            color: #a0a0a0;
            font-size: 1rem;
        }

        .custom-input-group input {
            border: none;
            box-shadow: none;
            padding: 12px 10px;
            font-size: 0.9rem;
            width: 100%;
            outline: none;
            background: transparent;
        }

        .btn-masuk {
            background-color: var(--lbq-pink);
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 12px;
            width: 100%;
            margin-top: 10px;
            transition: 0.3s;
        }

        .btn-masuk:hover {
            background-color: var(--lbq-pink-hover);
            color: white;
        }

        /* Responsive Mobile */
        @media (max-width: 900px) {
            .left-panel { display: none; }
            .right-panel { background-color: var(--lbq-pink); }
            .login-card { box-shadow: 0 10px 40px rgba(0,0,0,0.15); margin: 20px; }
        }
    </style>
</head>
<body>

    <div class="split-container">
        
        <div class="left-panel">
            <div class="bg-circle circle-1"></div>
            <div class="bg-circle circle-2"></div>

            <div class="left-content">
                <div class="d-flex align-items-center">
                    <div class="logo-box">
                        <img src="/logo/lbqueen_logo.PNG" alt="Logo" onerror="this.style.display='none'">
                    </div>
                    <span class="ms-3 fw-bold fs-5">LBQueen Care Beauty</span>
                </div>

                <h1>Kelola Karyawan & <br>SDM Lebih Mudah</h1>
                
                <p class="desc">
                    Sistem <strong>Human Resource Information System (HRIS)</strong> khusus untuk mengelola seluruh absensi, penggajian, dan performa karyawan dalam satu platform terpusat.
                </p>

                <ul class="feature-list">
                    <li>
                        <span class="check-icon"><i class="bi bi-check"></i></span>
                        Manajemen Absensi & Jadwal Shift Real-time
                    </li>
                    <li>
                        <span class="check-icon"><i class="bi bi-check"></i></span>
                        Penggajian (Payroll) & BPJS Otomatis
                    </li>
                    <li>
                        <span class="check-icon"><i class="bi bi-check"></i></span>
                        Database Karyawan & Riwayat Terintegrasi
                    </li>
                    <li>
                        <span class="check-icon"><i class="bi bi-check"></i></span>
                        Sistem Pengajuan Cuti & Lembur Digital
                    </li>
                </ul>

                <div class="copyright-divider"></div>
                <div class="copyright-text">
                    © 2026 LBQueen Care Beauty. All rights reserved.
                </div>
            </div>
        </div>

        <div class="right-panel">
            <div class="login-card">
                <h3>Selamat Datang</h3>
                <p class="subtitle">Masuk ke akun Anda untuk melanjutkan</p>

                <form action="/proses_login" method="POST">
                    
                    <div class="mb-3">
                        <label class="form-label">Username / ID Pegawai</label>
                        <div class="custom-input-group">
                            <i class="bi bi-person-fill"></i>
                            <input type="text" name="email" placeholder="Masukkan username" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="custom-input-group">
                            <i class="bi bi-lock-fill"></i>
                            <input type="password" name="password" placeholder="Masukkan password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-masuk">Masuk</button>
                </form>
            </div>
        </div>

    </div>

</body>
</html>