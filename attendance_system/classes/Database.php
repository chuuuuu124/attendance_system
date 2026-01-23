<?php
// classes/Database.php
require_once __DIR__ . '/../config.php'; // 確保讀取到根目錄的設定檔

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // 使用 config.php 定義的常量進行連線
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            // 這裡是重點：我們把真正的錯誤原因印出來看
            echo "<p style='color: red;'>資料庫詳細錯誤: " . $exception->getMessage() . "</p>";
        }
        return $this->conn;
    }
}