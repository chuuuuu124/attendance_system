<?php
// student_edit.php - 整合課程綁定與時間顯示版
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }

require_once 'config.php';
require_once 'classes/Database.php';

$db = (new Database())->getConnection();
$id = $_GET['id'] ?? null;
$message = "";

// 1. 抓取學員基本資料
$stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) { die("找不到該學員"); }

// 2. 抓取該學員目前的活躍課程包
$stmt_enrolled = $db->prepare("
    SELECT e.*, c.course_name 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    WHERE e.student_id = ? AND e.status = 'active'
");
$stmt_enrolled->execute([$id]);
$current_enrollments = $stmt_enrolled->fetchAll();

// 3. 抓取系統所有可選購的課程 (包含時間資訊)
$all_courses = $db->query("SELECT * FROM courses WHERE status = 'active'")->fetchAll();

// 4. 處理表單更新邏輯
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = $_POST['name'];
    $sid    = $_POST['student_id'];
    $uid    = $_POST['card_uid'] ?: null;
    $bday   = $_POST['birthday'] ?: null;
    $school = $_POST['school'];
    $gender = $_POST['gender'];
    $phone  = $_POST['phone'];
    $p_email = $_POST['parent_email'] ?: null;
    
    $bind_course_id = $_POST['bind_course_id'] ?? null;

    try {
        $db->beginTransaction();

        $password = $student['password'];
        if ($bday && $bday !== $student['birthday']) {
            $password = date('Ymd', strtotime($bday));
        }

        $sql = "UPDATE students SET name=?, student_id=?, card_uid=?, birthday=?, school=?, gender=?, phone=?, parent_email=?, password=? WHERE id=?";
        $db->prepare($sql)->execute([$name, $sid, $uid, $bday, $school, $gender, $phone, $p_email, $password, $id]);

        if ($bind_course_id) {
            $c_stmt = $db->prepare("SELECT valid_months FROM courses WHERE id = ?");
            $c_stmt->execute([$bind_course_id]);
            $course_info = $c_stmt->fetch();
            
            if ($course_info) {
                $expiry_date = date('Y-m-d', strtotime("+" . $course_info['valid_months'] . " months"));
                $sql_enroll = "INSERT INTO enrollments (student_id, course_id, status, expiry_date, total_checkins) VALUES (?, ?, 'active', ?, 0)";
                $db->prepare($sql_enroll)->execute([$id, $bind_course_id, $expiry_date]);
            }
        }

        $db->commit();
        header("Location: student_management.php?msg=updated");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $message = "❌ 錯誤：" . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>編輯學員 - 卓球教室</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --accent: #8E9775; --border: #E8E4E1; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; display: flex; justify-content: center; padding: 60px 0; margin: 0; }
        .edit-container { width: 100%; max-width: 500px; }
        .card { background: #FFF; border: 1px solid var(--border); padding: 40px; border-radius: 4px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); margin-bottom: 30px; }
        h2 { font-size: 18px; font-weight: 400; margin-bottom: 30px; letter-spacing: 2px; text-align: center; color: var(--ink); }
        h3 { font-size: 14px; font-weight: 400; margin-bottom: 20px; color: #AAA; letter-spacing: 1px; border-bottom: 1px solid #F1F1F1; padding-bottom: 10px; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 11px; color: #AAA; margin-bottom: 8px; }
        input, select { width: 100%; border: none; border-bottom: 1px solid var(--border); padding: 10px 0; outline: none; font-size: 15px; background: transparent; }
        input:focus { border-bottom-color: var(--accent); }
        
        .current-enrollment { font-size: 13px; color: var(--ink); background: #FDFDFB; padding: 10px; border-radius: 4px; margin-bottom: 5px; border-left: 3px solid var(--accent); }
        .btn-save { background: var(--ink); color: #FFF; border: none; padding: 15px; width: 100%; cursor: pointer; margin-top: 10px; letter-spacing: 2px; }
        .back-link { display: block; text-align: center; margin-top: 20px; font-size: 12px; color: #888; text-decoration: none; }
    </style>
</head>
<body>

<div class="edit-container">
    <form method="POST">
        <div class="card">
            <h2>編輯學員資料</h2>
            <?php if($message): ?><div style="color: #D9534F; font-size: 13px; margin-bottom: 20px;"><?= $message ?></div><?php endif; ?>
            
            <div class="form-group">
                <label>卡片感應 (掃描以更換卡片)</label>
                <input type="text" name="card_uid" id="card_uid" style="color:var(--accent); font-weight:bold;" value="<?= htmlspecialchars($student['card_uid'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group"><label>姓名</label><input type="text" name="name" value="<?= htmlspecialchars($student['name']) ?>" required></div>
                <div class="form-group"><label>學號</label><input type="text" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>" required></div>
            </div>
            <div class="form-group"><label>家長 Email</label><input type="email" name="parent_email" value="<?= htmlspecialchars($student['parent_email'] ?? '') ?>"></div>
            <div class="form-group"><label>聯絡電話</label><input type="text" name="phone" value="<?= htmlspecialchars($student['phone'] ?? '') ?>"></div>
            <div class="form-group"><label>就讀學校</label><input type="text" name="school" value="<?= htmlspecialchars($student['school'] ?? '') ?>"></div>
            <div class="form-group"><label>出生年月日</label><input type="date" name="birthday" value="<?= $student['birthday'] ?>"></div>
            <div class="form-group">
                <label>性別</label>
                <select name="gender">
                    <option value="M" <?= $student['gender'] === 'M' ? 'selected' : '' ?>>男</option>
                    <option value="F" <?= $student['gender'] === 'F' ? 'selected' : '' ?>>女</option>
                </select>
            </div>
        </div>

        <div class="card">
            <h3>課程管理</h3>
            
            <label>目前活躍課程包</label>
            <?php if(empty($current_enrollments)): ?>
                <p style="font-size: 12px; color: #CCC; margin-bottom: 20px;">尚無綁定任何課程</p>
            <?php else: ?>
                <div style="margin-bottom: 25px;">
                    <?php foreach ($current_enrollments as $en): ?>
                        <div class="current-enrollment">
                            <?= htmlspecialchars($en['course_name']) ?> 
                            <span style="float:right; font-size:11px; color:#888;">(進度: <?= $en['total_checkins'] ?> 次)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-group" style="background: #F9F9F9; padding: 15px; border-radius: 4px;">
                <label style="color: var(--ink); font-weight: 500;">綁定新課程 (立即選購)</label>
                <select name="bind_course_id">
                    <option value="">-- 請選擇欲加入的課程 --</option>
                    <?php foreach ($all_courses as $c): ?>
                        <?php 
                            // 格式化時間，若無設定則顯示為空
                            $display_time = $c['start_time'] ? ' ' . substr($c['start_time'], 0, 5) : '';
                        ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['course_name']) ?><?= $display_time ?> (<?= $c['session_limit'] ?>堂 / <?= $c['valid_months'] ?>個月)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="font-size: 11px; color: #AAA; margin-top: 10px;">* 選擇後點擊下方儲存，將自動計算有效期並新增紀錄。</p>
            </div>
        </div>

        <button type="submit" class="btn-save">儲存變更 (SAVE)</button>
        <a href="student_management.php" class="back-link">取消並返回名單</a>
    </form>
</div>

<script>
    const cardInput = document.getElementById('card_uid');
    cardInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementsByName('name')[0].focus();
        }
    });
</script>

</body>
</html>