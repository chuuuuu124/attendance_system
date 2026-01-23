<?php
// classes/CourseService.php - 修正簽到紀錄寫入版

class CourseService {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function getActiveEnrollment($card_uid) {
        $sql = "SELECT e.*, s.name as student_name, c.course_name, c.session_limit, c.valid_months 
                FROM enrollments e
                JOIN students s ON e.student_id = s.id
                JOIN courses c ON e.course_id = c.id
                WHERE s.card_uid = :card_uid AND e.status = 'active' AND e.is_completed = 0
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':card_uid', $card_uid);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function processCheckIn($enrollment_id) {
        // 1. 抓取最新狀態與課程設定
        $stmt = $this->db->prepare("SELECT e.*, c.session_limit, c.valid_months FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.id = ?");
        $stmt->execute([$enrollment_id]);
        $e = $stmt->fetch();

        if ($e['is_completed']) throw new Exception("此課程已結業");
        if ($e['total_checkins'] >= $e['session_limit']) throw new Exception("堂數已用盡");

        $new_count = $e['total_checkins'] + 1;
        
        // A. 更新報名表 (增加次數)
        if ($e['total_checkins'] == 0) {
            $expiry = date('Y-m-d', strtotime("+{$e['valid_months']} months"));
            $update_sql = "UPDATE enrollments SET total_checkins = ?, first_checkin_date = ?, expiry_date = ? WHERE id = ?";
            $params = [$new_count, date('Y-m-d'), $expiry, $enrollment_id];
        } else {
            $update_sql = "UPDATE enrollments SET total_checkins = ? WHERE id = ?";
            $params = [$new_count, $enrollment_id];
        }
        $this->db->prepare($update_sql)->execute($params);

        // B. 【重要！新增此段】在 checkins 資料表留下紀錄，儀表板才看得到清單
        $log_sql = "INSERT INTO checkins (enrollment_id, sequence_no, checkin_time) VALUES (?, ?, CURRENT_TIMESTAMP)";
        $this->db->prepare($log_sql)->execute([$enrollment_id, $new_count]);

        // C. 結業判定
        if ($new_count >= $e['session_limit']) {
            $this->db->prepare("UPDATE enrollments SET is_completed = 1, status = 'completed' WHERE id = ?")->execute([$enrollment_id]);
        }
        return $new_count;
    }
}