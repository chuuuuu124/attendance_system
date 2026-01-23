<?php
// course_management.php - 修正預設值引用版
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }

require_once 'config.php'; // 確保引入配置檔以讀取常數
require_once 'classes/Database.php';

$db = (new Database())->getConnection();
$message = "";

// 1. 處理課程新增邏輯 (維持不變)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = $_POST['course_name'];
    $type = $_POST['course_type'];
    $limit = $_POST['session_limit'];
    $months = $_POST['valid_months'];
    $time = ($type === 'scheduled') ? $_POST['start_time'] : null;
    $days = ($type === 'scheduled' && isset($_POST['days'])) ? implode(',', $_POST['days']) : null;

    try {
        $sql = "INSERT INTO courses (course_name, course_type, start_time, days_of_week, session_limit, valid_months, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'active')";
        $db->prepare($sql)->execute([$name, $type, $time, $days, $limit, $months]);
        $message = "✅ 課程已成功建立";
    } catch (Exception $e) { $message = "❌ 錯誤：" . $e->getMessage(); }
}

// 2. 處理課程刪除邏輯 (維持不變)
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    try {
        $check = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = 'active'");
        $check->execute([$delete_id]);
        $active_count = $check->fetchColumn();

        if ($active_count > 0) {
            throw new Exception("無法刪除：尚有 {$active_count} 名學員正在進行此課程");
        }

        $stmt = $db->prepare("UPDATE courses SET status = 'deleted' WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: course_management.php?msg=deleted");
        exit;
    } catch (Exception $e) { $message = "❌ " . $e->getMessage(); }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') $message = "✅ 課程已成功刪除";

$courses = $db->query("SELECT * FROM courses WHERE status = 'active' ORDER BY id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>課程配置 - 卓球教室</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --border: #E8E4E1; --danger: #D9534F; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; display: flex; margin: 0; }
        .sidebar { width: 240px; background: #2C2C2C; height: 100vh; color: #FFF; padding: 40px 30px; position: fixed; box-sizing: border-box; }
        .sidebar h2 { font-size: 16px; font-weight: 500; letter-spacing: 3px; margin: 0 0 40px 0; }
        .sidebar .back-link { display: block; color: #888; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .sidebar .back-link:hover { color: #FFF; }
        .main { margin-left: 240px; flex: 1; padding: 60px; box-sizing: border-box; }
        .flex-container { display: flex; gap: 50px; align-items: flex-start; }
        .card { background: #FFF; padding: 40px; border: 1px solid var(--border); border-radius: 4px; }
        h3 { font-size: 18px; font-weight: 400; margin: 0 0 30px 0; color: #2C2C2C; }
        .form-group { margin-bottom: 25px; }
        label { display: block; font-size: 12px; color: #AAA; margin-bottom: 8px; letter-spacing: 1px; }
        input, select { width: 100%; border: none; border-bottom: 1px solid #EEE; padding: 12px 0; outline: none; font-size: 15px; color: #2C2C2C; background: transparent; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 10px; font-size: 13px; color: #AAA; font-weight: 400; border-bottom: 1px solid #F1F1F1; }
        td { padding: 20px 10px; font-size: 15px; color: #2C2C2C; border-bottom: 1px solid #F9F9F9; }
        .btn-zen { background: #2C2C2C; color: #FFF; border: none; padding: 15px; width: 100%; cursor: pointer; letter-spacing: 2px; font-size: 13px; margin-top: 20px; }
        .btn-delete { color: #CCC; margin-left: 15px; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .btn-delete:hover { color: var(--danger); }
        .rule-cell { display: flex; align-items: center; justify-content: space-between; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>COURSE MANAGEMENT</h2>
        <a href="admin_dashboard.php" class="back-link">← 儀表板</a>
    </div>

    <div class="main">
        <?php if($message): ?>
            <div style="margin-bottom: 25px; font-size: 14px; color: #8E9775;"><?= $message ?></div>
        <?php endif; ?>

        <div class="flex-container">
            <div class="card" style="flex: 1.2;">
                <h3>建立新課程</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group"><label>課程名稱</label><input type="text" name="course_name" required></div>
                    <div class="form-group">
                        <label>課程類型</label>
                        <select name="course_type" onchange="toggleSchedule(this.value)">
                            <option value="scheduled">定時排課</option>
                            <option value="general">通用課程</option>
                        </select>
                    </div>
                    <div id="schedule_fields">
                        <div class="form-group"><label>上課時段</label><input type="time" name="start_time" step="1800"></div>
                        <div class="form-group">
                            <label>重複星期</label>
                            <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                <?php foreach(['Mon'=>'一','Tue'=>'二','Wed'=>'三','Thu'=>'四','Fri'=>'五','Sat'=>'六','Sun'=>'日'] as $en=>$zh): ?>
                                    <label style="font-size:13px; margin-right:10px;"><input type="checkbox" name="days[]" value="<?= $en ?>"> <?= $zh ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>堂數上限 (系統預設：<?= MAX_SESSIONS ?>)</label>
                        <input type="number" name="session_limit" value="<?= MAX_SESSIONS ?>">
                    </div>
                    <div class="form-group">
                        <label>有效月數 (系統預設：<?= VALID_MONTHS ?>)</label>
                        <input type="number" name="valid_months" value="<?= VALID_MONTHS ?>">
                    </div>
                    <button type="submit" class="btn-zen">建立課程配置</button>
                </form>
            </div>

            <div style="flex: 2;">
                <h3>現有課程配置</h3>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30%;">課程名稱</th>
                            <th style="width: 20%;">時段</th>
                            <th style="width: 25%;">重複日</th>
                            <th style="width: 25%;">規則</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                        <tr>
                            <td style="font-weight: 500;"><?= htmlspecialchars($c['course_name']) ?></td>
                            <td><?= $c['start_time'] ? substr($c['start_time'], 0, 5) : '不限' ?></td>
                            <td style="color: #AAA; font-size: 13px;">
                                <?= $c['days_of_week'] ? str_replace(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],['一','二','三','四','五','六','日'], $c['days_of_week']) : '通用' ?>
                            </td>
                            <td>
                                <div class="rule-cell">
                                    <span><?= $c['session_limit'] ?> 堂 / <?= $c['valid_months'] ?>月</span>
                                    <a href="?delete_id=<?= $c['id'] ?>" class="btn-delete" 
                                       onclick="return confirm('確定要刪除「<?= htmlspecialchars($c['course_name']) ?>」嗎？')">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleSchedule(type) {
            document.getElementById('schedule_fields').style.display = (type === 'general') ? 'none' : 'block';
        }
    </script>
</body>
</html>