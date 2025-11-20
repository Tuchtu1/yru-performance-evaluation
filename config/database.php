<?php

/**
 * config/database.php
 * การตั้งค่าการเชื่อมต่อฐานข้อมูล
 * YRU Performance Evaluation System
 */

// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('APP_ROOT')) {
    die('Access Denied');
}

// ตั้งค่าการเชื่อมต่อฐานข้อมูล // TODO: DB_... -> .env
define('DB_HOST', 'localhost:8889');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'yru_evaluation');
define('DB_CHARSET', 'utf8mb4');

class Database
{
    private static $instance = null;
    private $conn;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล");
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    // ป้องกันการ clone
    private function __clone() {}

    // ป้องกันการ unserialize
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

// ฟังก์ชันช่วยในการเชื่อมต่อฐานข้อมูล
function getDB()
{
    return Database::getInstance()->getConnection();
}
