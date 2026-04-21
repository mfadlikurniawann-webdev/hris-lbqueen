<?php
// api/proses_pengajuan.php
include __DIR__ . '/koneksi.php';

$karyawan = auth_required($conn);
$nik = $conn->real_escape_string($karyawan['nik']);
$jenis = $_POST['jenis'] ?? '';

if ($jenis === 'lembur') {
    $tgl = $conn->real_escape_string($_POST['tanggal']);
    $jm = $conn->real_escape_string($_POST['jam_mulai']);
    $js = $conn->real_escape_string($_POST['jam_selesai']);
    $ket = $conn->real_escape_string($_POST['keterangan']);

    $sql = "INSERT INTO lembur (nik, tanggal, jam_mulai, jam_selesai, keterangan, status) VALUES ('$nik', '$tgl', '$jm', '$js', '$ket', 'Pending')";
    if ($conn->query($sql)) echo "✅ Pengajuan Lembur berhasil dikirim!";
    else echo "❌ Gagal: " . $conn->error;
} elseif ($jenis === 'cuti') {
    $kat = $conn->real_escape_string($_POST['kategori_cuti']);
    $tm = $conn->real_escape_string($_POST['tgl_mulai']);
    $ts = $conn->real_escape_string($_POST['tgl_selesai']);
    $ket = $conn->real_escape_string($_POST['keterangan']);

    $sql = "INSERT INTO pengajuan_cuti (nik, jenis, tanggal_mulai, tanggal_selesai, keterangan, status) VALUES ('$nik', '$kat', '$tm', '$ts', '$ket', 'Pending')";
    if ($conn->query($sql)) echo "✅ Pengajuan $kat berhasil dikirim!";
    else echo "❌ Gagal: " . $conn->error;
} elseif ($jenis === 'dinas') {
    $tujuan = $conn->real_escape_string($_POST['tujuan']);
    $tb = $conn->real_escape_string($_POST['tgl_berangkat']);
    $tk = $conn->real_escape_string($_POST['tgl_kembali']);
    $ket = $conn->real_escape_string($_POST['keterangan']);

    $sql = "INSERT INTO perjalanan_dinas (nik, tujuan, tgl_berangkat, tgl_kembali, keterangan, status) VALUES ('$nik', '$tujuan', '$tb', '$tk', '$ket', 'Pending')";
    if ($conn->query($sql)) echo "✅ Pengajuan Dinas berhasil dikirim!";
    else echo "❌ Gagal: " . $conn->error;
} elseif ($jenis === 'reimburse') {
    $kat = $conn->real_escape_string($_POST['kategori']);
    $nom = (int) $_POST['nominal'];
    $foto = $conn->real_escape_string($_POST['foto_nota']);
    $ket = $conn->real_escape_string($_POST['keterangan']);

    $sql = "INSERT INTO reimburse (nik, kategori, nominal, foto_nota, keterangan, status) VALUES ('$nik', '$kat', $nom, '$foto', '$ket', 'Pending')";
    if ($conn->query($sql)) echo "✅ Pengajuan Reimburse berhasil dikirim!";
    else echo "❌ Gagal: " . $conn->error;
} else {
    echo "❌ Jenis pengajuan tidak valid.";
}
