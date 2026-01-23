<?php
// api/checkin_handler.php - 整合版本檢查觸發版
header('Content-Type: application/json');
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/CourseService.php';

// 引入 PHPMailer 核心
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/PHPMailer/Exception.php';
require '../vendor/PHPMailer/PHPMailer.php';
require '../vendor/PHPMailer/SMTP.php';

try {
    $uid = $_GET['uid'] ?? null;
    if (!$uid) throw new Exception("缺少卡片編號");

    $db = (new Database())->getConnection();
    $service = new CourseService($db);

    // 1. 驗證學員與課程
    $enrollment = $service->getActiveEnrollment($uid);
    if (!$enrollment) throw new Exception("無效卡片或無活躍課程");

    // 2. 執行簽到 (更新次數)
    $new_count = $service->processCheckIn($enrollment['id']);

    // 3. 執行郵件通知邏輯
    $mailStatus = "未設定 Email";
    if (!empty($enrollment['parent_email'])) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
            $mail->addAddress($enrollment['parent_email']);

            $time = date('H:i');
            $mail->isHTML(true);
            $mail->Subject = "【到班通知】{$enrollment['student_name']} 已到達教室";
            $mail->Body = "
                <div style='font-family: sans-serif; padding: 20px; border: 1px solid #EEE;'>
                    <h2 style='color: #2C2C2C; font-weight: 400;'>到班確認通知</h2>
                    <p>您好，您的孩子 <b>{$enrollment['student_name']}</b> 已於今日 <b>{$time}</b> 順利抵達桌球教室。</p>
                    <hr style='border: 0; border-top: 1px solid #EEE;'>
                    <p style='font-size: 14px;'>目前課程進度：<b>第 {$new_count} 堂課</b> (上限 {$enrollment['session_limit']} 堂)</p>
                    <p style='color: #888; font-size: 12px;'>本郵件由系統自動發出，請勿直接回覆。</p>
                </div>";

            $mail->send();
            $mailStatus = "通知郵件已寄出";
        } catch (Exception $e) { $mailStatus = "郵件發送失敗: " . $mail->ErrorInfo; }
    }

    // 4. 【新增/修改】更新版本標籤檔案並回傳訊息
    if ($new_count) {
        // 在 api 目錄下更新 last_update.txt 檔案
        file_put_contents('last_update.txt', time());

        echo json_encode([
            "success" => true,
            "message" => "簽到完成！ ($mailStatus)",
            "data" => [
                "name" => $enrollment['student_name'],
                "count" => $new_count,
                "course" => $enrollment['course_name']
            ]
        ]);
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}