<?php
// api/ganti_password.php
include __DIR__ . '/koneksi.php';

$token   = get_token_from_cookie();
$payload = jwt_verify($token);
if (!$payload) {
    header("Location: /login");
    exit();
}

// Ambil data user yang sedang login
$email  = $conn->real_escape_string($payload['email']);
$result = $conn->query("SELECT * FROM karyawan WHERE email = '$email'");
$user   = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - HRIS LBQueen Care Beauty</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="/logo/lbqueen_logo.PNG">
    <style>
        :root {
            --lbq-pink: #b6416a;
            --lbq-pink-hover: #9c365a;
            --lbq-pink-light: rgba(182, 65, 106, 0.08);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: #f5f6fa;
            min-height: 100vh;
        }

        /* ===== HEADER / NAVBAR ===== */
        .top-navbar {
            background: white;
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .navbar-brand-area {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }

        .logo-box-sm {
            background: var(--lbq-pink);
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
        }

        .logo-box-sm img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .brand-text {
            font-weight: 700;
            font-size: 0.95rem;
            color: #222;
        }

        .brand-sub {
            font-size: 0.7rem;
            color: #999;
            display: block;
            line-height: 1;
        }

        .nav-back-btn {
            background: var(--lbq-pink-light);
            color: var(--lbq-pink);
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: 0.2s;
        }

        .nav-back-btn:hover {
            background: var(--lbq-pink);
            color: white;
        }

        /* ===== MAIN CONTENT ===== */
        .main-wrapper {
            max-width: 640px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            background: linear-gradient(135deg, var(--lbq-pink) 0%, #9c365a 100%);
            border-radius: 16px;
            padding: 28px 32px;
            color: white;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            top: -60px;
            right: -40px;
        }

        .page-header::after {
            content: '';
            position: absolute;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            bottom: -30px;
            right: 80px;
        }

        .page-header .icon-wrap {
            background: rgba(255,255,255,0.15);
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 12px;
        }

        .page-header h4 {
            font-weight: 700;
            margin: 0 0 4px;
            font-size: 1.3rem;
        }

        .page-header p {
            margin: 0;
            opacity: 0.85;
            font-size: 0.88rem;
        }

        /* ===== USER INFO CARD ===== */
        .user-info-card {
            background: white;
            border-radius: 12px;
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid #f0f0f0;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: var(--lbq-pink-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--lbq-pink);
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .user-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: #222;
            margin: 0 0 2px;
        }

        .user-meta {
            font-size: 0.8rem;
            color: #999;
            margin: 0;
        }

        /* ===== FORM CARD ===== */
        .form-card {
            background: white;
            border-radius: 16px;
            padding: 28px 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border: 1px solid #f0f0f0;
        }

        .form-card .section-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: #bbb;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f5f5f5;
        }

        .form-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #444;
            margin-bottom: 8px;
        }

        /* Custom Input Group */
        .custom-input-group {
            display: flex;
            align-items: center;
            border: 1.5px solid #e8e8e8;
            border-radius: 10px;
            padding: 0 14px;
            background-color: #fafafa;
            transition: 0.25s;
        }

        .custom-input-group:focus-within {
            border-color: var(--lbq-pink);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(182, 65, 106, 0.08);
        }

        .custom-input-group i.icon-left {
            color: #c0c0c0;
            font-size: 0.95rem;
            margin-right: 2px;
        }

        .custom-input-group input {
            border: none;
            box-shadow: none;
            padding: 12px 10px;
            font-size: 0.9rem;
            width: 100%;
            outline: none;
            background: transparent;
            font-family: 'DM Sans', sans-serif;
            color: #333;
        }

        .custom-input-group input::placeholder {
            color: #c5c5c5;
        }

        .toggle-pw {
            background: none;
            border: none;
            color: #bbb;
            padding: 0;
            cursor: pointer;
            font-size: 0.95rem;
            line-height: 1;
            transition: color 0.2s;
        }

        .toggle-pw:hover {
            color: var(--lbq-pink);
        }

        /* Password Strength */
        .strength-bar-wrap {
            display: flex;
            gap: 4px;
            margin-top: 8px;
        }

        .strength-bar {
            flex: 1;
            height: 4px;
            border-radius: 4px;
            background: #eeeeee;
            transition: background 0.3s;
        }

        .strength-label {
            font-size: 0.75rem;
            margin-top: 5px;
            font-weight: 600;
        }

        .strength-label.weak   { color: #e53935; }
        .strength-label.medium { color: #fb8c00; }
        .strength-label.strong { color: #43a047; }

        /* Requirements checklist */
        .req-list {
            list-style: none;
            padding: 0;
            margin: 10px 0 0;
        }

        .req-list li {
            font-size: 0.78rem;
            color: #bbb;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
            transition: color 0.2s;
        }

        .req-list li.ok {
            color: #43a047;
        }

        .req-list li i {
            font-size: 0.75rem;
        }

        /* Divider */
        .form-divider {
            border: none;
            border-top: 1px solid #f0f0f0;
            margin: 24px 0;
        }

        /* Buttons */
        .btn-save {
            background: var(--lbq-pink);
            color: white;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            padding: 13px 24px;
            width: 100%;
            font-size: 0.92rem;
            font-family: 'DM Sans', sans-serif;
            transition: 0.25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-save:hover {
            background: var(--lbq-pink-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(182, 65, 106, 0.3);
        }

        .btn-save:active {
            transform: translateY(0);
        }

        .btn-save:disabled {
            background: #ddd;
            color: #aaa;
            transform: none;
            box-shadow: none;
            cursor: not-allowed;
        }

        /* Alert styles */
        .alert-custom {
            border: none;
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 0.88rem;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
        }

        .alert-success-custom {
            background: #f0fdf4;
            color: #166534;
        }

        .alert-danger-custom {
            background: #fef2f2;
            color: #991b1b;
        }

        /* Loading spinner */
        .spinner-sm {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Security note */
        .security-note {
            background: #fff8f0;
            border: 1px solid #ffe0b2;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 0.8rem;
            color: #9c6a00;
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        @media (max-width: 600px) {
            .form-card { padding: 20px; }
            .main-wrapper { margin: 20px auto; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <div class="top-navbar">
        <a href="/" class="navbar-brand-area">
            <div class="logo-box-sm">
                <img src="/logo/lbqueen_logo.PNG" alt="Logo" onerror="this.style.background='white'; this.style.display='block'">
            </div>
            <div>
                <span class="brand-text">LBQueen Care Beauty</span>
                <span class="brand-sub">HRIS System</span>
            </div>
        </a>
        <a href="/" class="nav-back-btn">
            <i class="bi bi-arrow-left"></i> Kembali ke Beranda
        </a>
    </div>

    <div class="main-wrapper">

        <!-- Page Header -->
        <div class="page-header">
            <div class="icon-wrap"><i class="bi bi-shield-lock-fill"></i></div>
            <h4>Ubah Kata Sandi</h4>
            <p>Perbarui kata sandi akun Anda untuk menjaga keamanan data HRIS</p>
        </div>

        <!-- User Info -->
        <div class="user-info-card">
            <div class="user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div>
                <p class="user-name"><?= htmlspecialchars($user['nama'] ?? 'Pengguna') ?></p>
                <p class="user-meta">
                    <?= htmlspecialchars($user['nik'] ?? '') ?> &nbsp;·&nbsp;
                    <?= htmlspecialchars($user['posisi'] ?? '') ?> &nbsp;·&nbsp;
                    <?= htmlspecialchars($user['email'] ?? '') ?>
                </p>
            </div>
        </div>

        <!-- Alert area (injected by JS) -->
        <div id="alert-area"></div>

        <!-- Form Card -->
        <div class="form-card">
            <div class="section-title">Formulir Perubahan Kata Sandi</div>

            <form id="formGantiPassword" novalidate>

                <!-- Password Lama -->
                <div class="mb-4">
                    <label class="form-label">Kata Sandi Saat Ini</label>
                    <div class="custom-input-group" id="group-old">
                        <i class="bi bi-lock-fill icon-left"></i>
                        <input type="password" id="password_lama" name="password_lama"
                               placeholder="Masukkan kata sandi saat ini" autocomplete="current-password">
                        <button type="button" class="toggle-pw" onclick="togglePw('password_lama', this)">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    </div>
                </div>

                <hr class="form-divider">

                <!-- Password Baru -->
                <div class="mb-3">
                    <label class="form-label">Kata Sandi Baru</label>
                    <div class="custom-input-group" id="group-new">
                        <i class="bi bi-key-fill icon-left"></i>
                        <input type="password" id="password_baru" name="password_baru"
                               placeholder="Buat kata sandi baru" autocomplete="new-password"
                               oninput="checkStrength(this.value); checkMatch()">
                        <button type="button" class="toggle-pw" onclick="togglePw('password_baru', this)">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    </div>

                    <!-- Strength bars -->
                    <div class="strength-bar-wrap" id="strength-bars">
                        <div class="strength-bar" id="bar1"></div>
                        <div class="strength-bar" id="bar2"></div>
                        <div class="strength-bar" id="bar3"></div>
                        <div class="strength-bar" id="bar4"></div>
                    </div>
                    <div class="strength-label" id="strength-label"></div>

                    <!-- Requirements -->
                    <ul class="req-list" id="req-list">
                        <li id="req-len"><i class="bi bi-circle"></i> Minimal 6 karakter</li>
                        <li id="req-upper"><i class="bi bi-circle"></i> Mengandung huruf kapital</li>
                        <li id="req-num"><i class="bi bi-circle"></i> Mengandung angka</li>
                    </ul>
                </div>

                <!-- Konfirmasi Password Baru -->
                <div class="mb-4">
                    <label class="form-label">Konfirmasi Kata Sandi Baru</label>
                    <div class="custom-input-group" id="group-confirm">
                        <i class="bi bi-check-circle-fill icon-left"></i>
                        <input type="password" id="password_konfirmasi" name="password_konfirmasi"
                               placeholder="Ulangi kata sandi baru" autocomplete="new-password"
                               oninput="checkMatch()">
                        <button type="button" class="toggle-pw" onclick="togglePw('password_konfirmasi', this)">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    </div>
                    <div id="match-msg" style="font-size:0.78rem; margin-top:5px; font-weight:600;"></div>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-save" id="btn-submit">
                    <div class="spinner-sm" id="spinner"></div>
                    <i class="bi bi-shield-check" id="btn-icon"></i>
                    <span id="btn-text">Simpan Kata Sandi Baru</span>
                </button>

            </form>

            <!-- Security note -->
            <div class="security-note">
                <i class="bi bi-info-circle-fill" style="flex-shrink:0; margin-top:1px;"></i>
                <span>Setelah berhasil mengubah kata sandi, Anda akan tetap masuk. Gunakan kata sandi baru saat login berikutnya.</span>
            </div>
        </div>

    </div>

    <script>
        // Toggle show/hide password
        function togglePw(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon  = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash-fill';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye-fill';
            }
        }

        // Password strength checker
        function checkStrength(val) {
            const bars  = [document.getElementById('bar1'), document.getElementById('bar2'),
                           document.getElementById('bar3'), document.getElementById('bar4')];
            const label = document.getElementById('strength-label');

            // Reset
            bars.forEach(b => { b.style.background = '#eee'; });
            label.textContent = '';
            label.className = 'strength-label';

            if (!val) return;

            let score = 0;
            if (val.length >= 6)                         score++;
            if (val.length >= 10)                        score++;
            if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
            if (/[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val)) score++;

            const colors = ['#e53935', '#fb8c00', '#fdd835', '#43a047'];
            const labels = ['Sangat Lemah', 'Lemah', 'Cukup Kuat', 'Kuat'];
            const classes = ['weak', 'weak', 'medium', 'strong'];

            for (let i = 0; i < score; i++) {
                bars[i].style.background = colors[Math.min(score - 1, 3)];
            }

            label.textContent = labels[Math.min(score - 1, 3)];
            label.classList.add(classes[Math.min(score - 1, 3)]);

            // Requirements
            toggleReq('req-len',   val.length >= 6);
            toggleReq('req-upper', /[A-Z]/.test(val));
            toggleReq('req-num',   /[0-9]/.test(val));
        }

        function toggleReq(id, ok) {
            const el = document.getElementById(id);
            el.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
            el.className = ok ? 'ok' : '';
        }

        // Match checker
        function checkMatch() {
            const pw1 = document.getElementById('password_baru').value;
            const pw2 = document.getElementById('password_konfirmasi').value;
            const msg = document.getElementById('match-msg');
            const grp = document.getElementById('group-confirm');

            if (!pw2) { msg.textContent = ''; return; }

            if (pw1 === pw2) {
                msg.textContent = '✓ Kata sandi cocok';
                msg.style.color = '#43a047';
                grp.style.borderColor = '#43a047';
            } else {
                msg.textContent = '✗ Kata sandi tidak cocok';
                msg.style.color = '#e53935';
                grp.style.borderColor = '#e53935';
            }
        }

        // Show alert
        function showAlert(type, msg) {
            const area = document.getElementById('alert-area');
            const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
            const cls  = type === 'success' ? 'alert-success-custom' : 'alert-danger-custom';
            area.innerHTML = `
                <div class="alert-custom ${cls}">
                    <i class="bi ${icon}" style="flex-shrink:0;margin-top:2px;"></i>
                    <span>${msg}</span>
                </div>`;
            area.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Form submit
        document.getElementById('formGantiPassword').addEventListener('submit', async function(e) {
            e.preventDefault();

            const pwLama  = document.getElementById('password_lama').value.trim();
            const pwBaru  = document.getElementById('password_baru').value;
            const pwKonf  = document.getElementById('password_konfirmasi').value;
            const btn     = document.getElementById('btn-submit');
            const spinner = document.getElementById('spinner');
            const btnIcon = document.getElementById('btn-icon');
            const btnText = document.getElementById('btn-text');

            // Validasi client-side
            if (!pwLama) { showAlert('error', 'Kata sandi saat ini wajib diisi.'); return; }
            if (!pwBaru)  { showAlert('error', 'Kata sandi baru wajib diisi.'); return; }
            if (pwBaru.length < 6) { showAlert('error', 'Kata sandi baru minimal 6 karakter.'); return; }
            if (pwBaru !== pwKonf) { showAlert('error', 'Konfirmasi kata sandi tidak cocok.'); return; }
            if (pwLama === pwBaru) { showAlert('error', 'Kata sandi baru tidak boleh sama dengan kata sandi lama.'); return; }

            // Loading state
            btn.disabled = true;
            spinner.style.display = 'block';
            btnIcon.style.display = 'none';
            btnText.textContent = 'Menyimpan...';

            try {
                const formData = new FormData();
                formData.append('password_lama', pwLama);
                formData.append('password_baru', pwBaru);

                const response = await fetch('/proses_ganti_password', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', result.message || 'Kata sandi berhasil diperbarui!');
                    document.getElementById('formGantiPassword').reset();
                    // Reset UI states
                    document.getElementById('strength-label').textContent = '';
                    document.getElementById('match-msg').textContent = '';
                    ['bar1','bar2','bar3','bar4'].forEach(id => {
                        document.getElementById(id).style.background = '#eee';
                    });
                    ['req-len','req-upper','req-num'].forEach(id => {
                        document.getElementById(id).querySelector('i').className = 'bi bi-circle';
                        document.getElementById(id).className = '';
                    });
                    document.getElementById('group-confirm').style.borderColor = '';
                } else {
                    showAlert('error', result.message || 'Gagal memperbarui kata sandi.');
                }
            } catch (err) {
                showAlert('error', 'Terjadi kesalahan koneksi. Silakan coba lagi.');
            } finally {
                btn.disabled = false;
                spinner.style.display = 'none';
                btnIcon.style.display = 'inline';
                btnText.textContent = 'Simpan Kata Sandi Baru';
            }
        });
    </script>

</body>
</html>