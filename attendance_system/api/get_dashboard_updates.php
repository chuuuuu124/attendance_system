<?php
// api/get_dashboard_updates.php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../classes/Database.php';

try {
    $db = (new Database())->getConnection();

    // 1. 抓取統計數據
    $stats = [
        'students' => $db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'today'    => $db->query("SELECT COUNT(*) FROM checkins WHERE DATE(checkin_time) = CURDATE()")->fetchColumn(),
        'active'   => $db->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")->fetchColumn()
    ];

    // 2. 抓取最近 10 筆簽到紀錄
    $recent = $db->query("
        SELECT s.name, c.checkin_time, cr.course_name, c.sequence_no 
        FROM checkins c
        JOIN enrollments e ON c.enrollment_id = e.id
        JOIN students s ON e.student_id = s.id
        JOIN courses cr ON e.course_id = cr.id
        ORDER BY c.checkin_time DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recent as &$r) {
        $r['checkin_time'] = date('H:i', strtotime($r['checkin_time']));
    }

    echo json_encode(["success" => true, "stats" => $stats, "recent" => $recent]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}