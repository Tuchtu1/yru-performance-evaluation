<?php

/**
 * Helper Functions
 * ฟังก์ชันช่วยเหลือทั่วไป สำหรับระบบประเมินผลการปฏิบัติงาน มหาวิทยาลัยราชภัฏยะลา
 */

if (!defined('APP_ROOT')) {
    die('Access Denied');
}

// ==================== String Helpers ====================

/**
 * ตัด string และเพิ่ม ...
 */
function str_limit($string, $limit = 100, $end = '...')
{
    if ($string === null) {
        return '';
    }
    if (mb_strlen($string, 'UTF-8') <= $limit) {
        return $string;
    }
    return mb_substr($string, 0, $limit, 'UTF-8') . $end;
}

/**
 * Slugify string (สำหรับ URL)
 */
function slugify($text)
{
    if ($text === null) {
        return 'n-a';
    }
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);

    return empty($text) ? 'n-a' : $text;
}

/**
 * Random string
 */
function str_random($length = 16)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomString;
}

/**
 * แปลง string เป็น boolean
 */
function str_to_bool($string)
{
    return filter_var($string, FILTER_VALIDATE_BOOLEAN);
}

/**
 * Sanitize string
 */
function clean_string($string)
{
    if ($string === null) {
        return '';
    }
    return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize HTML
 */
function clean_html($html)
{
    if ($html === null) {
        return '';
    }
    // Allow only safe HTML tags
    $allowed_tags = '<p><br><strong><em><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6>';
    return strip_tags($html, $allowed_tags);
}

// ==================== Array Helpers ====================

/**
 * ดึงค่าจาก array ด้วย dot notation
 */
function array_get($array, $key, $default = null)
{
    if (!is_array($array)) {
        return $default;
    }

    if (is_null($key)) {
        return $array;
    }

    if (array_key_exists($key, $array)) {
        return $array[$key];
    }

    foreach (explode('.', $key) as $segment) {
        if (is_array($array) && array_key_exists($segment, $array)) {
            $array = $array[$segment];
        } else {
            return $default;
        }
    }

    return $array;
}

/**
 * ดึงเฉพาะ keys ที่ต้องการ
 */
function array_only($array, $keys)
{
    return array_intersect_key($array, array_flip((array) $keys));
}

/**
 * ยกเว้น keys ที่ไม่ต้องการ
 */
function array_except($array, $keys)
{
    return array_diff_key($array, array_flip((array) $keys));
}

/**
 * เช็คว่า array มีค่าหรือไม่
 */
function array_has_value($array, $value)
{
    return in_array($value, $array, true);
}

// ==================== Date & Time Helpers ====================

/**
 * แปลงวันที่เป็นภาษาไทย
 */
function thai_date($date, $format = 'full')
{
    if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = is_numeric($date) ? $date : strtotime($date);

    $thai_months = [
        1 => 'มกราคม',
        'กุมภาพันธ์',
        'มีนาคม',
        'เมษายน',
        'พฤษภาคม',
        'มิถุนายน',
        'กรกฎาคม',
        'สิงหาคม',
        'กันยายน',
        'ตุลาคม',
        'พฤศจิกายน',
        'ธันวาคม'
    ];

    $thai_months_short = [
        1 => 'ม.ค.',
        'ก.พ.',
        'มี.ค.',
        'เม.ย.',
        'พ.ค.',
        'มิ.ย.',
        'ก.ค.',
        'ส.ค.',
        'ก.ย.',
        'ต.ค.',
        'พ.ย.',
        'ธ.ค.'
    ];

    $day = date('j', $timestamp);
    $month = (int)date('n', $timestamp);
    $year = date('Y', $timestamp) + 543;
    $time = date('H:i', $timestamp);

    switch ($format) {
        case 'full':
            return "$day {$thai_months[$month]} $year";
        case 'short':
            return "$day {$thai_months_short[$month]} $year";
        case 'datetime':
            return "$day {$thai_months[$month]} $year เวลา $time น.";
        case 'time':
            return $time . ' น.';
        case 'year':
            return $year;
        default:
            return "$day {$thai_months[$month]} $year";
    }
}

/**
 * แปลงวันที่เป็น Time Ago (ภาษาไทย)
 */
function time_ago($datetime)
{
    if (!$datetime) {
        return '-';
    }

    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $difference = time() - $timestamp;

    $periods = [
        31536000 => 'ปี',
        2592000 => 'เดือน',
        604800 => 'สัปดาห์',
        86400 => 'วัน',
        3600 => 'ชั่วโมง',
        60 => 'นาที',
        1 => 'วินาที'
    ];

    foreach ($periods as $seconds => $label) {
        if ($difference >= $seconds) {
            $time = floor($difference / $seconds);
            return $time . ' ' . $label . 'ที่แล้ว';
        }
    }

    return 'เมื่อสักครู่';
}

/**
 * นับจำนวนวันระหว่างวันที่
 */
function days_between($date1, $date2 = null)
{
    $date2 = $date2 ?: date('Y-m-d');
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $interval = $datetime1->diff($datetime2);
    return $interval->days;
}

/**
 * เช็คว่าวันที่หมดอายุหรือยัง
 */
function is_expired($date)
{
    return strtotime($date) < time();
}

/**
 * วันที่ในรูปแบบ MySQL
 */
function mysql_date($date = null)
{
    return date('Y-m-d', $date ? strtotime($date) : time());
}

/**
 * วันที่และเวลาในรูปแบบ MySQL
 */
function mysql_datetime($datetime = null)
{
    return date('Y-m-d H:i:s', $datetime ? strtotime($datetime) : time());
}

// ==================== Number & Format Helpers ====================

/**
 * จัดรูปแบบตัวเลข
 */
function number_format_thai($number, $decimals = 2)
{
    return number_format($number, $decimals, '.', ',');
}

/**
 * แปลงขนาดไฟล์เป็นรูปแบบที่อ่านง่าย
 */
function format_bytes($bytes, $precision = 2)
{
    if ($bytes == 0) return '0 Bytes';

    $units = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $base = log($bytes, 1024);
    $pow = floor($base);

    return round(pow(1024, $base - $pow), $precision) . ' ' . $units[$pow];
}

/**
 * แปลงเลขไทยเป็นเลขอารบิก
 */
function thai_to_arabic_numerals($string)
{
    $thai = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
    $arabic = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($thai, $arabic, $string);
}

/**
 * แปลงเลขอารบิกเป็นเลขไทย
 */
function arabic_to_thai_numerals($string)
{
    $thai = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
    $arabic = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($arabic, $thai, $string);
}

/**
 * แปลงตัวเลขเป็นคำไทย (สำหรับจำนวนเงิน)
 */
function number_to_thai_text($number)
{
    $txtnum1 = ["", "หนึ่ง", "สอง", "สาม", "สี่", "ห้า", "หก", "เจ็ด", "แปด", "เก้า"];
    $txtnum2 = ["", "สิบ", "ร้อย", "พัน", "หมื่น", "แสน", "ล้าน"];

    $number = number_format($number, 2, '.', '');
    $parts = explode('.', $number);
    $baht = $parts[0];
    $satang = $parts[1];

    $bahtText = '';
    $len = strlen($baht);

    for ($i = 0; $i < $len; $i++) {
        $tmp = substr($baht, $i, 1);
        if ($tmp != 0) {
            if (($i == ($len - 1)) && ($tmp == 1)) {
                $bahtText .= "เอ็ด";
            } elseif (($i == ($len - 2)) && ($tmp == 2)) {
                $bahtText .= "ยี่";
            } elseif (($i == ($len - 2)) && ($tmp == 1)) {
                $bahtText .= "";
            } else {
                $bahtText .= $txtnum1[$tmp];
            }
            $bahtText .= $txtnum2[$len - $i - 1];
        }
    }

    $bahtText .= "บาท";

    if ($satang == "00") {
        $bahtText .= "ถ้วน";
    } else {
        $satangLen = strlen($satang);
        for ($i = 0; $i < $satangLen; $i++) {
            $tmp = substr($satang, $i, 1);
            if ($tmp != 0) {
                if (($i == ($satangLen - 1)) && ($tmp == 1)) {
                    $bahtText .= "เอ็ด";
                } elseif (($i == ($satangLen - 2)) && ($tmp == 2)) {
                    $bahtText .= "ยี่";
                } elseif (($i == ($satangLen - 2)) && ($tmp == 1)) {
                    $bahtText .= "";
                } else {
                    $bahtText .= $txtnum1[$tmp];
                }
                $bahtText .= $txtnum2[$satangLen - $i - 1];
            }
        }
        $bahtText .= "สตางค์";
    }

    return $bahtText;
}

// ==================== File Helpers ====================

/**
 * อัปโหลดไฟล์
 */
function upload_file($file, $destination = 'uploads/', $allowed_types = null, $max_size = null)
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid parameters'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error: ' . $file['error']];
    }

    // Check file size
    $max_size = $max_size ?: MAX_FILE_SIZE;
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large. Max: ' . format_bytes($max_size)];
    }

    // Check file type
    $allowed_types = $allowed_types ?: ALLOWED_FILE_TYPES;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_types)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }

    // Create destination folder if not exists
    $upload_path = UPLOAD_PATH . $destination;
    if (!file_exists($upload_path)) {
        mkdir($upload_path, 0755, true);
    }

    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $upload_path . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'filepath' => $destination . $filename,
        'size' => $file['size'],
        'ext' => $ext,
        'original_name' => $file['name']
    ];
}

/**
 * ลบไฟล์
 */
function delete_file($filepath)
{
    $fullpath = UPLOAD_PATH . $filepath;
    if (file_exists($fullpath)) {
        return unlink($fullpath);
    }
    return false;
}

/**
 * ดาวน์โหลดไฟล์
 */
function download_file($filepath, $filename = null)
{
    $fullpath = UPLOAD_PATH . $filepath;

    if (!file_exists($fullpath)) {
        return false;
    }

    $filename = $filename ?: basename($fullpath);

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($fullpath));

    readfile($fullpath);
    exit;
}

/**
 * Get MIME type
 */
function get_mime_type($filepath)
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $filepath);
    finfo_close($finfo);
    return $mime;
}

// ==================== Session & Flash Messages ====================

/**
 * Set flash message
 */
function set_flash($key, $message)
{
    $_SESSION['flash'][$key] = $message;
}

/**
 * Get flash message
 */
function get_flash($key)
{
    if (isset($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }
    return null;
}

/**
 * Check if flash exists
 */
function has_flash($key)
{
    return isset($_SESSION['flash'][$key]);
}

/**
 * Set success message
 */
function flash_success($message)
{
    set_flash('success', $message);
}

/**
 * Set error message
 */
function flash_error($message)
{
    set_flash('error', $message);
}

/**
 * Set warning message
 */
function flash_warning($message)
{
    set_flash('warning', $message);
}

/**
 * Set info message
 */
function flash_info($message)
{
    set_flash('info', $message);
}

// ==================== Security Helpers ====================

/**
 * Generate CSRF token
 */
function csrf_token()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate CSRF field
 */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verify CSRF token
 */
function verify_csrf($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Hash password
 */
function hash_password($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verify_password($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Sanitize input
 */
function sanitize_input($data)
{
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output
 */
function e($string)
{
    // รองรับค่า null สำหรับ PHP 8.1+
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
// ==================== Database Helpers ====================

/**
 * Paginate results
 */
function paginate($total, $page = 1, $perPage = null)
{
    $perPage = $perPage ?: ITEMS_PER_PAGE;
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'last_page' => $totalPages,
        'from' => $offset + 1,
        'to' => min($offset + $perPage, $total),
        'offset' => $offset
    ];
}

/**
 * Build WHERE clause from array
 */
function build_where_clause($conditions, &$params = [])
{
    if (empty($conditions)) {
        return '';
    }

    $clauses = [];
    foreach ($conditions as $field => $value) {
        if (is_array($value)) {
            $placeholders = array_fill(0, count($value), '?');
            $clauses[] = "$field IN (" . implode(',', $placeholders) . ")";
            $params = array_merge($params, $value);
        } else {
            $clauses[] = "$field = ?";
            $params[] = $value;
        }
    }

    return 'WHERE ' . implode(' AND ', $clauses);
}

// ==================== Logging Helpers ====================

/**
 * Log activity
 */
function log_activity($action, $table_name = null, $record_id = null, $details = null)
{
    try {
        $db = getDB();
        $user_id = $_SESSION['user']['user_id'] ?? null;

        $stmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, action, table_name, record_id, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $action,
            $table_name,
            $record_id,
            is_array($details) ? json_encode($details) : $details,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Log Activity Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log error
 */
function log_error($message, $context = [])
{
    $log = date('Y-m-d H:i:s') . " - ERROR: $message";
    if (!empty($context)) {
        $log .= " - Context: " . json_encode($context);
    }
    error_log($log);
}

// ==================== Email Helpers ====================

/**
 * Send email (basic implementation)
 */
function send_email($to, $subject, $message, $from = null)
{
    $from = $from ?: MAIL_FROM;
    $headers = [
        'From' => $from,
        'Reply-To' => $from,
        'X-Mailer' => 'PHP/' . phpversion(),
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8'
    ];

    $headerString = '';
    foreach ($headers as $key => $value) {
        $headerString .= "$key: $value\r\n";
    }

    return mail($to, $subject, $message, $headerString);
}

/**
 * Send email template
 */
function send_email_template($to, $subject, $template, $data = [])
{
    $templatePath = APP_ROOT . '/includes/email-templates/' . $template . '.php';

    if (!file_exists($templatePath)) {
        return false;
    }

    extract($data);
    ob_start();
    include $templatePath;
    $message = ob_get_clean();

    return send_email($to, $subject, $message);
}

// ==================== Validation Helpers ====================

/**
 * Validate Thai ID card number
 */
function is_valid_thai_id($id)
{
    $id = preg_replace('/[^0-9]/', '', $id);

    if (strlen($id) != 13) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$id[$i] * (13 - $i);
    }

    $mod = $sum % 11;
    $check = (11 - $mod) % 10;

    return $check == (int)$id[12];
}

/**
 * Validate Thai phone number
 */
function is_valid_thai_phone($phone)
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^(0[0-9]{8,9}|66[0-9]{8,9})$/', $phone);
}

/**
 * Validate email
 */
function is_valid_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ==================== URL & Request Helpers ====================

/**
 * Get current URL
 */
function current_url()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Get request method
 */
function request_method()
{
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Check if request is POST
 */
function is_post()
{
    return request_method() === 'POST';
}

/**
 * Check if request is GET
 */
function is_get()
{
    return request_method() === 'GET';
}

/**
 * Check if request is AJAX
 */
function is_ajax()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get input value
 */
function input($key, $default = null)
{
    return $_REQUEST[$key] ?? $default;
}

/**
 * Get all inputs
 */
function all_inputs()
{
    return $_REQUEST;
}

// ==================== Debug Helpers ====================

/**
 * Dump and die
 */
function dd(...$vars)
{
    echo '<pre style="background: #f4f4f4; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    die();
}

/**
 * Dump
 */
function dump(...$vars)
{
    echo '<pre style="background: #f4f4f4; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
}

/**
 * Print readable
 */
function pr($var)
{
    echo '<pre>';
    print_r($var);
    echo '</pre>';
}

// ==================== Miscellaneous ====================

/**
 * Get client IP
 */
function get_client_ip()
{
    $ip = '';

    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        $ip = $_SERVER['HTTP_FORWARDED'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    return $ip;
}

/**
 * Get browser info
 */
function get_browser_info()
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Generate UUID
 */
function generate_uuid()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

/**
 * Check if development mode
 */
function is_development()
{
    return defined('ENVIRONMENT') && ENVIRONMENT === 'development';
}

/**
 * Check if production mode
 */
function is_production()
{
    return defined('ENVIRONMENT') && ENVIRONMENT === 'production';
}

/**
 * Convert array to CSV string
 */
function array_to_csv($array, $delimiter = ',', $enclosure = '"')
{
    $output = fopen('php://temp', 'r+');

    foreach ($array as $row) {
        fputcsv($output, $row, $delimiter, $enclosure);
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return $csv;
}

/**
 * Convert array to XML
 */
function array_to_xml($array, $root_element = 'root', $xml = null)
{
    if ($xml === null) {
        $xml = new SimpleXMLElement("<$root_element/>");
    }

    foreach ($array as $key => $value) {
        if (is_array($value)) {
            array_to_xml($value, $key, $xml->addChild($key));
        } else {
            $xml->addChild($key, htmlspecialchars($value));
        }
    }

    return $xml->asXML();
}

/**
 * Truncate text at word boundary
 */
function truncate_words($text, $limit = 100)
{
    if ($text === null) {
        return '';
    }
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    $text = mb_substr($text, 0, $limit);
    $last_space = mb_strrpos($text, ' ');

    if ($last_space !== false) {
        $text = mb_substr($text, 0, $last_space);
    }

    return $text . '...';
}
