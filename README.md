# 🚀 Panduan Deploy HRIS LBQueen ke Vercel

## 📁 Struktur Project
```
HRIS_LBQueen_Vercel/
├── api/
│   ├── index.php          ← Halaman utama (beranda)
│   ├── login.php          ← Halaman login
│   ├── logout.php         ← Proses logout
│   ├── proses_login.php   ← Proses autentikasi (JWT)
│   ├── proses_absen.php   ← Proses absen + upload Cloudinary
│   └── koneksi.php        ← Koneksi DB + JWT helper
├── public/
│   ├── logo/
│   │   └── lbqueen_logo.PNG
│   └── style/
│       └── style.css
├── database.sql           ← Import ke database kamu
└── vercel.json            ← Konfigurasi routing Vercel
```

---

## 🗄️ STEP 1 — Siapkan Database MySQL Gratis

### Pilihan: FreeSQLDatabase.com (Paling Mudah)
1. Buka https://freesqldatabase.com
2. Klik **Sign Up** → daftar gratis
3. Setelah login, catat informasi berikut:
   - **Host** (contoh: sql104.freesqldatabase.com)
   - **Username**
   - **Password**
   - **Database Name**
   - **Port** (biasanya 3306)
4. Buka phpMyAdmin yang disediakan
5. Pilih database kamu → klik tab **Import**
6. Upload file `database.sql` dari project ini
7. Klik **Go** → data karyawan akan masuk otomatis ✅

---

## ☁️ STEP 2 — Siapkan Cloudinary (Untuk Upload Foto)

1. Buka https://cloudinary.com → klik **Sign Up Free**
2. Setelah login, buka **Dashboard**
3. Catat informasi berikut:
   - **Cloud Name**
   - **API Key**
   - **API Secret**

> ⚠️ Jika tidak ingin pakai Cloudinary, foto absen tidak akan tersimpan
> tapi sistem tetap berjalan normal

---

## 📤 STEP 3 — Upload ke GitHub

1. Buka https://github.com → login atau daftar
2. Klik tombol **+** → **New repository**
3. Nama repository: `hris-lbqueen` → klik **Create repository**
4. Di terminal / command prompt, jalankan:

```bash
cd HRIS_LBQueen_Vercel
git init
git add .
git commit -m "Initial commit HRIS LBQueen"
git branch -M main
git remote add origin https://github.com/USERNAME_KAMU/hris-lbqueen.git
git push -u origin main
```

> Ganti `USERNAME_KAMU` dengan username GitHub kamu

---

## 🌐 STEP 4 — Deploy ke Vercel

1. Buka https://vercel.com → login dengan akun GitHub
2. Klik **Add New Project**
3. Pilih repository `hris-lbqueen` → klik **Import**
4. Biarkan semua pengaturan default → klik **Deploy**
5. Tunggu beberapa menit... ✅

---

## 🔐 STEP 5 — Set Environment Variables

Ini **WAJIB** agar koneksi database dan JWT bisa berjalan.

1. Di dashboard Vercel → pilih project kamu
2. Klik **Settings** → **Environment Variables**
3. Tambahkan variabel berikut satu per satu:

| Key | Value | Keterangan |
|-----|-------|------------|
| `DB_HOST` | `sql104.freesqldatabase.com` | Host database kamu |
| `DB_USER` | `username_kamu` | Username database |
| `DB_PASS` | `password_kamu` | Password database |
| `DB_NAME` | `nama_database_kamu` | Nama database |
| `DB_PORT` | `3306` | Port database |
| `JWT_SECRET` | `hris_lbqueen_rahasia_2026` | Boleh diganti bebas |
| `CLOUDINARY_CLOUD_NAME` | `cloud_name_kamu` | Dari dashboard Cloudinary |
| `CLOUDINARY_API_KEY` | `api_key_kamu` | Dari dashboard Cloudinary |
| `CLOUDINARY_API_SECRET` | `api_secret_kamu` | Dari dashboard Cloudinary |

4. Setelah semua diisi → klik **Save**
5. Klik **Deployments** → klik **Redeploy** (pilih yang terbaru)

---

## ✅ STEP 6 — Akses Aplikasi

Setelah deploy berhasil, situsmu bisa diakses di:
```
https://hris-lbqueen.vercel.app
```

### Login dengan akun yang sudah ada di database:
| NIK | Email | Password |
|-----|-------|----------|
| HR001 | mfadlikurniawann@gmail.com | FadliHR001! |
| BT001 | adelwidya199@gmail.com | AdelBT001! |
| BT002 | vikapujaa@gmail.com | VikaBT002! |

---

## 🔄 Cara Update Kode

Setiap kali kamu mengubah kode, cukup:
```bash
git add .
git commit -m "Update: deskripsi perubahan"
git push
```
Vercel akan **otomatis redeploy** dalam 1-2 menit ✅

---

## ⚠️ Perbedaan dari Versi Lokal

| Fitur | Lokal (XAMPP) | Vercel |
|-------|--------------|--------|
| Login | `$_SESSION` | JWT Cookie |
| Upload Foto | Folder `uploads/` | Cloudinary |
| Link logout | `logout.php` | `/logout` |
| Link absen | `proses_absen.php` | `/proses_absen` |

---

## ❓ Troubleshooting

**Error "Koneksi database gagal"**
→ Cek kembali environment variables DB_HOST, DB_USER, DB_PASS, DB_NAME

**Foto tidak tersimpan**
→ Pastikan CLOUDINARY_CLOUD_NAME, API_KEY, API_SECRET sudah benar

**Halaman blank / 404**
→ Pastikan file `vercel.json` ada di root project dan sudah di-push ke GitHub

**Tidak bisa login**
→ Pastikan sudah import `database.sql` ke database dan JWT_SECRET sudah diset
