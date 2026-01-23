<?php
// student_dashboard.php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') { header("Location: index.php"); exit; }

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/CourseService.php';

$db = (new Database())->getConnection();
$student_id = $_SESSION['user_id'];

// 抓取該學生所有課程包
$stmt = $db->prepare("
    SELECT e.*, c.course_name, c.session_limit 
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.student_id = ? AND e.status != 'expired'
");
$stmt->execute([$student_id]);
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>個人進度 - 卓球教室</title>
    <style>
        body { background: #F9F9F7; font-family: 'Noto Sans TC', sans-serif; padding: 40px; margin: 0; }
        .container { max-width: 700px; margin: 0 auto; }
        .nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 60px; }
        .welcome { font-size: 14px; color: #888; letter-spacing: 1px; }
        .course-card { background: #FFF; padding: 35px; margin-bottom: 30px; border: 1px solid #E8E4E1; border-radius: 4px; }
        .course-title { font-size: 18px; color: #2C2C2C; margin-bottom: 25px; font-weight: 500; }
        .progress-grid { display: flex; gap: 8px; margin-bottom: 20px; }
        .box { flex: 1; height: 35px; background: #F1F1F1; border-radius: 2px; }
        .box.filled { background: #8E9775; } /* 和風綠 */
        .info { font-size: 13px; color: #666; display: flex; justify-content: space-between; margin-top: 20px; border-top: 1px solid #F1F1F1; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <div class="welcome">HELLO, <?= htmlspecialchars($_SESSION['student_name']) ?></div>
            <a href="logout.php" style="font-size: 12px; color: #2C2C2C; text-decoration: none; border-bottom: 1px solid;">LOGOUT</a>
        </div>

        <?php foreach ($courses as $c): ?>
        <div class="course-card">
            <div class="course-title"><?= htmlspecialchars($c['course_name']) ?></div>
            <div class="progress-grid">
                <?php for($i=1; $i<=$c['session_limit']; $i++): ?>
                    <div class="box <?= $i <= $c['total_checkins'] ? 'filled' : '' ?>"></div>
                <?php endfor; ?>
            </div>
            <div class="info">
                <span>進度：<?= $c['total_checkins'] ?> / <?= $c['session_limit'] ?></span>
                <span>有效期至：<?= $c['expiry_date'] ?: '尚未起算' ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>