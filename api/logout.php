<?php
// api/logout.php
include __DIR__ . '/koneksi.php';
clear_token_cookie();
header("Location: /login");
exit();
?>
