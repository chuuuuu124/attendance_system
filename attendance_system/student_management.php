<?php
// student_management.php - 增加搜尋與過濾功能版
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }

require_once 'config.php';
require_once 'classes/Database.php';

$db = (new Database())->getConnection();
$message = "";

// 1. 處理註冊邏輯 (維持不變)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $sid    = $_POST['student_id'];
    $name   = $_POST['name'];
    $uid    = $_POST['card_uid'] ?: null; 
    $bday   = $_POST['birthday'] ?: null;
    $school = $_POST['school'];
    $gender = $_POST['gender']; 
    $phone  = $_POST['phone'];
    $p_email = $_POST['parent_email'] ?: null;
    $password = $bday ? date('Ymd', strtotime($bday)) : $sid;

    try {
        if ($uid) {
            $check = $db->prepare("SELECT name FROM students WHERE card_uid = ?");
            $check->execute([$uid]);
            if ($check->fetch()) throw new Exception("此卡片已被其他學生使用");
        }
        $sql = "INSERT INTO students (student_id, name, birthday, school, gender, phone, parent_email, card_uid, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $db->prepare($sql)->execute([$sid, $name, $bday, $school, $gender, $phone, $p_email, $uid, $password]);
        $message = "✅ 註冊成功";
    } catch (Exception $e) { $message = "❌ 錯誤：" . $e->getMessage(); }
}

// 2. 獲取學員名單
$sql_list = "
    SELECT s.*, 
    (SELECT CONCAT(e.total_checkins, ' / ', c.session_limit) 
     FROM enrollments e 
     JOIN courses c ON e.course_id = c.id 
     WHERE e.student_id = s.id AND e.status = 'active' 
     LIMIT 1) as progress
    FROM students s 
    WHERE s.status = 'active' 
    ORDER BY s.id DESC
";
$students = $db->query($sql_list)->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>學員管理 - 卓球教室</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --border: #E8E4E1; --accent: #8E9775; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; display: flex; margin: 0; }
        .sidebar { width: 240px; background: #2C2C2C; height: 100vh; color: #FFF; padding: 40px 30px; position: fixed; box-sizing: border-box; }
        .sidebar h2 { font-size: 16px; font-weight: 500; letter-spacing: 3px; margin: 0 0 40px 0; }
        .sidebar .back-link { display: block; color: #888; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .sidebar .back-link:hover { color: #FFF; }

        .main { margin-left: 240px; flex: 1; padding: 60px; box-sizing: border-box; }
        .flex-container { display: flex; gap: 50px; align-items: flex-start; }
        
        .card { background: #FFF; padding: 40px; border: 1px solid var(--border); border-radius: 4px; }
        h3 { font-size: 18px; font-weight: 400; color: #2C2C2C; }
        
        /* 搜尋框樣式 */
        .list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .search-container { position: relative; }
        .search-input { border: 1px solid var(--border); padding: 8px 12px 8px 35px; border-radius: 4px; font-size: 13px; outline: none; width: 200px; transition: 0.3s; background: #FDFDFB; }
        .search-input:focus { border-color: var(--ink); background: #FFF; width: 250px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #AAA; font-size: 12px; }

        .form-group { margin-bottom: 25px; }
        label { display: block; font-size: 12px; color: #AAA; margin-bottom: 8px; letter-spacing: 1px; }
        input, select { width: 100%; border: none; border-bottom: 1px solid #EEE; padding: 12px 0; outline: none; font-size: 15px; background: transparent; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 10px; font-size: 13px; color: #AAA; font-weight: 400; border-bottom: 1px solid #F1F1F1; }
        td { padding: 20px 10px; font-size: 15px; color: #2C2C2C; border-bottom: 1px solid #F9F9F9; }
        
        .btn-submit { background: var(--ink); color: #FFF; border: none; padding: 15px; width: 100%; cursor: pointer; letter-spacing: 2px; font-size: 13px; margin-top: 15px; }
        .btn-edit { color: #2C2C2C; text-decoration: none; font-size: 12px; border: 1px solid var(--border); padding: 6px 12px; border-radius: 4px; }
        
        .progress-text { font-size: 13px; font-weight: 500; color: #8E9775; }
        .no-progress { font-size: 13px; color: #CCC; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>STUDENT MANAGEMENT</h2>
        <a href="admin_dashboard.php" class="back-link">← 儀表板</a>
    </div>

    <div class="main">
        <?php if($message): ?><div style="margin-bottom: 25px; font-size: 14px; color: #8E9775;"><?= $message ?></div><?php endif; ?>

        <div class="flex-container">
            <div class="card" style="flex: 1.2;">
                <h3>快速註冊</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group"><label>卡片感應</label><input type="text" name="card_uid" id="card_uid" autocomplete="off" autofocus></div>
                    <div class="form-group"><label>姓名</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>學號</label><input type="text" name="student_id" required></div>
                    <div class="form-group">
                        <label>性別</label>
                        <select name="gender"><option value="M">男</option><option value="F">女</option></select>
                    </div>
                    <div class="form-group"><label>就讀學校</label><input type="text" name="school"></div>
                    <div class="form-group"><label>聯絡電話</label><input type="text" name="phone"></div>
                    <div class="form-group"><label>家長 Email (到班通知用)</label><input type="email" name="parent_email"></div>
                    <div class="form-group"><label>出生年月日</label><input type="date" name="birthday"></div>
                    <button type="submit" class="btn-submit">完成註冊學員</button>
                </form>
            </div>

            <div style="flex: 2;">
                <div class="list-header">
                    <h3 style="margin: 0;">學員名單</h3>
                    <div class="search-container">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="studentSearch" class="search-input" placeholder="搜尋姓名或學號...">
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 20%;">姓名</th>
                            <th style="width: 25%;">課程進度</th> 
                            <th style="width: 35%;">電話 / Email</th>
                            <th style="width: 20%;">操作</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody">
                        <?php foreach ($students as $s): ?>
                        <tr class="student-row">
                            <td class="student-name"><?= htmlspecialchars($s['name']) ?></td>
                            <td>
                                <?php if ($s['progress']): ?>
                                    <span class="progress-text"><?= htmlspecialchars($s['progress']) ?> 堂</span>
                                <?php else: ?>
                                    <span class="no-progress">沒有</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 13px;"><?= htmlspecialchars($s['phone'] ?: '-') ?></div>
                                <div class="student-id" style="font-size: 11px; color: #AAA;"><?= htmlspecialchars($s['student_id']) ?> / <?= htmlspecialchars($s['parent_email'] ?: '無 Email') ?></div>
                            </td>
                            <td>
                                <a href="student_edit.php?id=<?= $s['id'] ?>" class="btn-edit">
                                    <i class="fa-regular fa-pen-to-square"></i> 編輯
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // 1. 讀卡機自動焦點邏輯
        const cardInput = document.getElementById('card_uid');
        cardInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementsByName('name')[0].focus();
            }
        });

        // 2. 即時搜尋邏輯
        const searchInput = document.getElementById('studentSearch');
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.student-row');

            rows.forEach(row => {
                const name = row.querySelector('.student-name').textContent.toLowerCase();
                const sid = row.querySelector('.student-id').textContent.toLowerCase();
                
                // 同時搜尋姓名與學號
                if (name.includes(filter) || sid.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>