<?php
// api/proses_approval.php
include __DIR__ . '/koneksi.php';

$karyawan = auth_required($conn);
$posisi   = trim(strtoupper($karyawan['posisi'] ?? ''));
$level    = trim(strtoupper($karyawan['level_jabatan'] ?? ''));
$is_admin = (strpos($posisi, 'HC') !== false || strpos($posisi, 'HR') !== false || in_array($level, ['OWNER','DIREKTUR']));

if (!$is_admin) { die("❌ Akses Ditolak!"); }

$id = (int) ($_POST['id'] ?? 0);
$status = $conn->real_escape_string($_POST['status'] ?? '');
$jenis = $_POST['jenis'] ?? '';

$tabel = '';
if ($jenis === 'lembur') $tabel = 'lembur';
elseif ($jenis === 'cuti') $tabel = 'pengajuan_cuti';
elseif ($jenis === 'dinas') $tabel = 'perjalanan_dinas';
elseif ($jenis === 'reimburse') $tabel = 'reimburse';

if ($tabel && $id > 0) {
    $sql = "UPDATE $tabel SET status = '$status' WHERE id = $id";
    if ($conn->query($sql)) echo "✅ Status berhasil diubah menjadi $status!";
    else echo "❌ Gagal: " . $conn->error;
} else {
    echo "❌ Data tidak valid.";
}
?>