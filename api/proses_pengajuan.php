<?php
// api/proses_pengajuan.php
include __DIR__ . '/koneksi.php';

$token = get_token_from_cookie();
$payload = jwt_verify($token);
if (!$payload) { echo "❌ Sesi tidak valid."; exit(); }

$nik = $conn->real_escape_string($payload['nik']);
$jenis = $_POST['jenis'] ?? '';

if ($jenis === 'dinas') {
    $tujuan = $conn->real_escape_string($_POST['tujuan']);
    $berangkat = $conn->real_escape_string($_POST['tgl_berangkat']);
    $kembali = $conn->real_escape_string($_POST['tgl_kembali']);
    $ket = $conn->real_escape_string($_POST['keterangan']);

    $sql = "INSERT INTO perjalanan_dinas (nik, tujuan, tgl_berangkat, tgl_kembali, keterangan) 
            VALUES ('$nik', '$tujuan', '$berangkat', '$kembali', '$ket')";
    if ($conn->query($sql)) echo "✅ Pengajuan Perjalanan Dinas berhasil dikirim!";
    else echo "❌ Gagal: " . $conn->error;

} elseif ($jenis === 'reimburse') {
    $kategori = $conn->real_escape_string($_POST['kategori']);
    $nominal = (int) $_POST['nominal'];
    $ket = $conn->real_escape_string($_POST['keterangan']);
    $foto = $conn->real_escape_string($_POST['foto_nota']);

    $sql = "INSERT INTO reimburse (nik, kategori, nominal, keterangan, foto_nota) 
            VALUES ('$nik', '$kategori', $nominal, '$ket', '$foto')";
    if ($conn->query($sql)) echo "✅ Pengajuan Reimburse berhasil dikirim!";
    else echo "❌ Gagal: " . $conn->error;
}
?>