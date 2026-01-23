<?php
// api/get_version.php
header('Content-Type: application/json');
$version_file = 'last_update.txt';
// 讀取檔案內容，若不存在則回傳 0
$v = file_exists($version_file) ? file_get_contents($version_file) : '0';
echo json_encode(["version" => $v]);