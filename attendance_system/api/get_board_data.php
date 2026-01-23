<?php
// api/get_board_data.php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../classes/Database.php';

try {
    $db = (new Database())->getConnection();
    
    // 抓取所有活躍報名資訊，依更新時間排序
    $sql = "SELECT e.id, e.total_checkins, e.expiry_date, s.name as student_name, c.course_name, c.session_limit 
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            JOIN courses c ON e.course_id = c.id
            WHERE e.status = 'active'
            ORDER BY e.updated_at DESC";

    $stmt = $db->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $data]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}