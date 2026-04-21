<?php
// api/proses_approval.php
include __DIR__ . '/koneksi.php';

$karyawan = auth_required($conn);
$posisi   = trim(strtoupper($karyawan['posisi'] ?? ''));
$level    = trim(strtoupper($karyawan['level_jabatan'] ?? ''));
$is_admin = (strpos($posisi, 'HC') !== false || strpos($posisi, 'HR') !== false || in_array($level, ['OWNER','DIREKTUR']));

if (!$is_admin) { die("Akses Ditolak!"); }

$id = $conn->real_escape_string($_POST['id'] ?? '');
$status = $conn->real_escape_string($_POST['status'] ?? '');
$jenis = $_POST['jenis'] ?? '';

if ($jenis == 'lembur') {
    $sql = "UPDATE lembur SET status = '$status' WHERE id = '$id'";
    if ($conn->query($sql)) echo "✅ Lembur berhasil $status!";
    else echo "❌ Gagal: " . $conn->error;
} elseif ($jenis == 'cuti') {
    $sql = "UPDATE pengajuan_cuti SET status = '$status' WHERE id = '$id'";
    if ($conn->query($sql)) echo "✅ Pengajuan berhasil $status!";
    else echo "❌ Gagal: " . $conn->error;
}
?>