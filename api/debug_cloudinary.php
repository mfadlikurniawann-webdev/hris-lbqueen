<?php
// api/debug_cloudinary.php
// HAPUS FILE INI SETELAH SELESAI TESTING!
// Akses via: https://hris-lbqueen.vercel.app/debug_cloudinary

include __DIR__ . '/koneksi.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>🔍 Debug Cloudinary</h2>";

$cloud_name = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? getenv('CLOUDINARY_CLOUD_NAME') ?? '';
$api_key    = $_ENV['CLOUDINARY_API_KEY']    ?? getenv('CLOUDINARY_API_KEY')    ?? '';
$api_secret = $_ENV['CLOUDINARY_API_SECRET'] ?? getenv('CLOUDINARY_API_SECRET') ?? '';

echo "<h3>1. Cek Environment Variables:</h3>";
echo "CLOUDINARY_CLOUD_NAME : " . ($cloud_name ? "✅ <b>$cloud_name</b>" : "❌ <b>KOSONG</b>") . "<br>";
echo "CLOUDINARY_API_KEY    : " . ($api_key    ? "✅ Ada (" . substr($api_key,0,6) . "...)" : "❌ <b>KOSONG</b>") . "<br>";
echo "CLOUDINARY_API_SECRET : " . ($api_secret ? "✅ Ada (tersembunyi)" : "❌ <b>KOSONG</b>") . "<br>";

echo "<h3>2. Cek cURL tersedia:</h3>";
echo function_exists('curl_init') ? "✅ cURL tersedia<br>" : "❌ cURL tidak tersedia<br>";

echo "<h3>3. Test Upload Gambar Kecil ke Cloudinary:</h3>";
if (!$cloud_name || !$api_key || !$api_secret) {
    echo "❌ Lewati test - env variables tidak lengkap<br>";
} else {
    // Buat gambar 1x1 pixel PNG sebagai Base64 untuk test
    $test_image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    
    $timestamp  = time();
    $public_id  = "hris_test/test_{$timestamp}";
    $signature  = sha1("public_id={$public_id}&timestamp={$timestamp}{$api_secret}");
    $upload_url = "https://api.cloudinary.com/v1_1/{$cloud_name}/image/upload";

    $ch = curl_init($upload_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'file'      => $test_image,
            'public_id' => $public_id,
            'timestamp' => $timestamp,
            'api_key'   => $api_key,
            'signature' => $signature,
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $curl_err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curl_err) {
        echo "❌ cURL Error: $curl_err<br>";
    } else {
        $result = json_decode($response, true);
        if (isset($result['secure_url'])) {
            echo "✅ Upload berhasil!<br>";
            echo "URL: <a href='{$result['secure_url']}' target='_blank'>{$result['secure_url']}</a><br>";
        } else {
            echo "❌ Upload gagal. Response dari Cloudinary:<br>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
    }
}

echo "<h3>4. Cek Kolom foto di tabel absensi:</h3>";
$desc = $conn->query("DESCRIBE absensi");
while ($row = $desc->fetch_assoc()) {
    if ($row['Field'] === 'foto') {
        $type = $row['Type'];
        $ok = (stripos($type, 'text') !== false || stripos($type, 'blob') !== false);
        echo ($ok ? "✅" : "⚠️") . " Kolom <b>foto</b> bertipe: <b>$type</b>";
        if (!$ok) echo " → <span style='color:red'>Perlu diubah ke TEXT! Jalankan file alter_foto.sql</span>";
        echo "<br>";
    }
}

echo "<h3>5. Data foto terbaru di tabel absensi:</h3>";
$rows = $conn->query("SELECT nik, jenis, waktu, foto FROM absensi ORDER BY waktu DESC LIMIT 5");
echo "<table border='1' cellpadding='5'><tr><th>NIK</th><th>Jenis</th><th>Waktu</th><th>Foto</th></tr>";
while ($r = $rows->fetch_assoc()) {
    $foto_preview = '-';
    if ($r['foto']) {
        if (str_starts_with($r['foto'], 'http')) {
            $foto_preview = "✅ URL: <a href='{$r['foto']}' target='_blank'>Lihat</a>";
        } else {
            $foto_preview = "⚠️ Base64 (" . strlen($r['foto']) . " char)";
        }
    }
    echo "<tr><td>{$r['nik']}</td><td>{$r['jenis']}</td><td>{$r['waktu']}</td><td>$foto_preview</td></tr>";
}
echo "</table>";

echo "<br><hr><small>⚠️ <b>Hapus file ini setelah selesai testing!</b></small>";
?>