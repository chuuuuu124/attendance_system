<?php
// settings.php
session_start();
if ($_SESSION['role'] !== 'admin') exit;

require_once 'config.php';
require_once 'classes/Database.php';

$db = (new Database())->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val = isset($_POST['email_notify']) ? '1' : '0';
    $stmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'email_notification'");
    $stmt->execute([$val]);
    $message = "⚙️ 設定已更新";
}

$notify = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'email_notification'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>系統設定 - 卓球教室</title>
    <style>
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --border: #E8E4E1; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #FFF; border: 1px solid var(--border); padding: 40px; width: 300px; text-align: center; }
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="font-weight: 400; font-size: 18px;">系統全域設定</h2>
        <form method="POST">
            <div style="margin: 30px 0;">
                <label style="font-size: 14px; margin-right: 10px;">Gmail 到班通知功能</label>
                <input type="checkbox" name="email_notify" <?= $notify === '1' ? 'checked' : '' ?> onchange="this.form.submit()">
            </div>
            <a href="admin_dashboard.php" style="font-size: 12px; color: #888; text-decoration: none;">← 返回儀表板</a>
        </form>
    </div>
</body>
</html>