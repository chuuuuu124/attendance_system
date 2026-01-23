<?php
// api/get_today_status.php - 修正星期過濾版
header('Content-Type: application/json');
require_once '../config.php';
require_once '../classes/Database.php';

$db = (new Database())->getConnection();

try {
    // 1. 取得今天的星期簡寫 (例如: Mon, Tue, Wed...)
    $today_day = date('D'); 

    // 2. 取得今日已簽到的學生 (僅限今日有課的定時排課學員)
    $sql_checked = "
        SELECT s.name, cr.course_name, c.sequence_no, cr.session_limit, DATE_FORMAT(c.checkin_time, '%H:%i') as time
        FROM checkins c
        JOIN enrollments e ON c.enrollment_id = e.id
        JOIN students s ON e.student_id = s.id
        JOIN courses cr ON e.course_id = cr.id
        WHERE DATE(c.checkin_time) = CURDATE()
        AND cr.course_type = 'scheduled'
        AND FIND_IN_SET(:today1, cr.days_of_week) > 0
        ORDER BY c.checkin_time DESC";
    
    $stmt1 = $db->prepare($sql_checked);
    $stmt1->execute([':today1' => $today_day]);
    $checked = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // 3. 取得今日尚未簽到的學生 (必須符合：活躍中、定時排課、星期符合今天)
    $sql_uncheck = "
        SELECT s.name, cr.course_name, e.total_checkins, cr.session_limit
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN courses cr ON e.course_id = cr.id
        WHERE e.status = 'active'
        AND cr.course_type = 'scheduled'
        AND cr.status = 'active'
        AND FIND_IN_SET(:today2, cr.days_of_week) > 0
        AND e.id NOT IN (
            SELECT enrollment_id FROM checkins WHERE DATE(checkin_time) = CURDATE()
        )
        ORDER BY s.name ASC";
    
    $stmt2 = $db->prepare($sql_uncheck);
    $stmt2->execute([':today2' => $today_day]);
    $uncheck = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "checked" => $checked,
        "uncheck" => $uncheck
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}