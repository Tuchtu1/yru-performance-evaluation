<?php

/**
 * config/permission.php
 * Permission & Access Control System
 * ระบบจัดการสิทธิ์และการเข้าถึง สำหรับระบบประเมินผลการปฏิบัติงาน มหาวิทยาลัยราชภัฏยะลา
 */

if (!defined('APP_ROOT')) {
    die('Access Denied');
}

// ==================== Role Definitions ====================

define('ROLES', [
    'admin' => [
        'name' => 'ผู้ดูแลระบบ',
        'level' => 3,
        'description' => 'มีสิทธิ์เข้าถึงและจัดการระบบทั้งหมด'
    ],
    'manager' => [
        'name' => 'ผู้บริหาร',
        'level' => 2,
        'description' => 'พิจารณาและอนุมัติแบบประเมิน'
    ],
    'staff' => [
        'name' => 'บุคลากร',
        'level' => 1,
        'description' => 'สร้างและส่งแบบประเมิน'
    ]
]);

// ==================== Permission Definitions ====================

define('PERMISSIONS', [

    // Dashboard
    'dashboard.view' => ['admin', 'manager', 'staff'],

    // Evaluation Permissions
    'evaluation.view_own' => ['admin', 'manager', 'staff'],
    'evaluation.view_all' => ['admin', 'manager'],
    'evaluation.create' => ['admin', 'staff'],
    'evaluation.edit_own' => ['admin', 'staff'],
    'evaluation.edit_all' => ['admin'],
    'evaluation.delete_own' => ['admin', 'staff'],
    'evaluation.delete_all' => ['admin'],
    'evaluation.submit' => ['admin', 'staff'],
    'evaluation.approve' => ['admin', 'manager'],
    'evaluation.reject' => ['admin', 'manager'],
    'evaluation.return' => ['admin', 'manager'],

    // Portfolio Permissions
    'portfolio.view_own' => ['admin', 'manager', 'staff'],
    'portfolio.view_all' => ['admin', 'manager'],
    'portfolio.create' => ['admin', 'staff'],
    'portfolio.edit_own' => ['admin', 'staff'],
    'portfolio.edit_all' => ['admin'],
    'portfolio.delete_own' => ['admin', 'staff'],
    'portfolio.delete_all' => ['admin'],
    'portfolio.claim' => ['admin', 'staff'],
    'portfolio.share' => ['admin', 'staff'],

    // Reports Permissions
    'reports.view_own' => ['admin', 'manager', 'staff'],
    'reports.view_department' => ['admin', 'manager'],
    'reports.view_organization' => ['admin'],
    'reports.export' => ['admin', 'manager', 'staff'],
    'reports.statistics' => ['admin'],

    // User Management Permissions
    'users.view' => ['admin', 'manager'],
    'users.create' => ['admin'],
    'users.edit' => ['admin'],
    'users.delete' => ['admin'],
    'users.manage_roles' => ['admin'],
    'users.manage_permissions' => ['admin'],
    'users.view_activity' => ['admin'],

    // Configuration Permissions
    'config.view' => ['admin'],
    'config.edit' => ['admin'],
    'config.personnel_types' => ['admin'],
    'config.evaluation_aspects' => ['admin'],
    'config.evaluation_topics' => ['admin'],
    'config.evaluation_periods' => ['admin'],
    'config.notifications' => ['admin'],
    'config.system_backup' => ['admin'],

    // Notification Permissions
    'notifications.view_own' => ['admin', 'manager', 'staff'],
    'notifications.create' => ['admin'],
    'notifications.broadcast' => ['admin'],
    'notifications.manage_settings' => ['admin', 'manager', 'staff'],

    // Approval Permissions
    'approval.view_pending' => ['admin', 'manager'],
    'approval.review' => ['admin', 'manager'],
    'approval.approve' => ['admin', 'manager'],
    'approval.reject' => ['admin', 'manager'],
    'approval.history' => ['admin', 'manager'],

    // System Permissions
    'system.logs' => ['admin'],
    'system.backup' => ['admin'],
    'system.restore' => ['admin'],
    'system.maintenance' => ['admin']
]);

// ==================== Permission Class ====================

class Permission
{

    private static $currentUser = null;

    /**
     * ตั้งค่า current user
     */
    public static function setUser($user)
    {
        self::$currentUser = $user;
    }

    /**
     * ดึงข้อมูล current user
     */
    public static function getUser()
    {
        if (self::$currentUser === null && isset($_SESSION['user'])) {
            self::$currentUser = $_SESSION['user'];
        }
        return self::$currentUser;
    }

    /**
     * ตรวจสอบว่า user มี permission หรือไม่
     */
    public static function can($permission)
    {
        $user = self::getUser();

        if (!$user) {
            return false;
        }

        // Admin มีสิทธิ์ทุกอย่าง
        if ($user['role'] === 'admin') {
            return true;
        }

        // ตรวจสอบ permission
        if (isset(PERMISSIONS[$permission])) {
            return in_array($user['role'], PERMISSIONS[$permission]);
        }

        return false;
    }

    /**
     * ตรวจสอบว่า user ไม่มี permission
     */
    public static function cannot($permission)
    {
        return !self::can($permission);
    }

    /**
     * ตรวจสอบว่า user มีหลาย permissions
     */
    public static function canAll(array $permissions)
    {
        foreach ($permissions as $permission) {
            if (!self::can($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * ตรวจสอบว่า user มีอย่างน้อยหนึ่ง permission
     */
    public static function canAny(array $permissions)
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * ตรวจสอบว่า user มี role ตามที่กำหนด
     */
    public static function hasRole($role)
    {
        $user = self::getUser();
        return $user && $user['role'] === $role;
    }

    /**
     * ตรวจสอบว่า user มีหลาย roles
     */
    public static function hasAnyRole(array $roles)
    {
        $user = self::getUser();
        return $user && in_array($user['role'], $roles);
    }

    /**
     * ตรวจสอบว่าเป็น admin หรือไม่
     */
    public static function isAdmin()
    {
        return self::hasRole('admin');
    }

    /**
     * ตรวจสอบว่าเป็น manager หรือไม่
     */
    public static function isManager()
    {
        return self::hasRole('manager');
    }

    /**
     * ตรวจสอบว่าเป็น staff หรือไม่
     */
    public static function isStaff()
    {
        return self::hasRole('staff');
    }

    /**
     * ตรวจสอบว่า user เป็นเจ้าของ resource หรือไม่
     */
    public static function owns($resourceUserId)
    {
        $user = self::getUser();
        return $user && $user['user_id'] == $resourceUserId;
    }

    /**
     * ตรวจสอบว่า user อยู่ใน department เดียวกันหรือไม่
     */
    public static function sameDepartment($departmentId)
    {
        $user = self::getUser();
        return $user && $user['department_id'] == $departmentId;
    }

    /**
     * ตรวจสอบ role level
     */
    public static function getRoleLevel()
    {
        $user = self::getUser();
        if (!$user) return 0;

        return ROLES[$user['role']]['level'] ?? 0;
    }

    /**
     * ตรวจสอบว่า role มี level สูงกว่าหรือไม่
     */
    public static function hasHigherRoleThan($role)
    {
        $currentLevel = self::getRoleLevel();
        $targetLevel = ROLES[$role]['level'] ?? 0;
        return $currentLevel > $targetLevel;
    }

    /**
     * ดึงรายการ permissions ของ user
     */
    public static function getUserPermissions()
    {
        $user = self::getUser();
        if (!$user) return [];

        $userPermissions = [];

        foreach (PERMISSIONS as $permission => $roles) {
            if (in_array($user['role'], $roles)) {
                $userPermissions[] = $permission;
            }
        }

        return $userPermissions;
    }

    /**
     * Abort with error ถ้าไม่มีสิทธิ์
     */
    public static function authorize($permission, $message = null)
    {
        if (!self::can($permission)) {
            self::abort(403, $message ?? 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        }
    }

    /**
     * Abort with HTTP error
     */
    public static function abort($code = 403, $message = 'Access Denied')
    {
        http_response_code($code);

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            // AJAX request
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $message,
                'code' => $code
            ]);
        } else {
            // Regular request
            include APP_ROOT . '/includes/errors/' . $code . '.php';
        }
        exit;
    }
}

// ==================== Helper Functions ====================

/**
 * ตรวจสอบ permission (shorthand)
 */
function can($permission)
{
    return Permission::can($permission);
}

/**
 * ตรวจสอบว่าไม่มี permission (shorthand)
 */
function cannot($permission)
{
    return Permission::cannot($permission);
}

/**
 * ตรวจสอบ role (shorthand)
 */
function hasRole($role)
{
    return Permission::hasRole($role);
}

/**
 * ตรวจสอบว่าเป็น admin (shorthand)
 */
function isAdmin()
{
    return Permission::isAdmin();
}

/**
 * ตรวจสอบว่าเป็น manager (shorthand)
 */
function isManager()
{
    return Permission::isManager();
}

/**
 * ตรวจสอบว่าเป็น staff (shorthand)
 */
function isStaff()
{
    return Permission::isStaff();
}

/**
 * Authorize หรือ abort
 */
function authorize($permission, $message = null)
{
    Permission::authorize($permission, $message);
}

/**
 * ตรวจสอบว่าเป็นเจ้าของหรือเป็น admin
 */
function canModify($resourceUserId)
{
    return Permission::owns($resourceUserId) || Permission::isAdmin();
}

/**
 * Middleware: ต้อง login
 */
function requireAuth()
{
    if (!isset($_SESSION['user'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect('modules/auth/login.php');
    }
}

/**
 * Middleware: ต้องมี permission
 */
function requirePermission($permission, $message = null)
{
    requireAuth();
    Permission::authorize($permission, $message);
}

/**
 * Middleware: ต้องมี role
 */
function requireRole($role, $message = null)
{
    requireAuth();

    if (!Permission::hasRole($role)) {
        Permission::abort(403, $message ?? 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    }
}

/**
 * Middleware: ต้องมีหนึ่งใน roles
 */
function requireAnyRole(array $roles, $message = null)
{
    requireAuth();

    if (!Permission::hasAnyRole($roles)) {
        Permission::abort(403, $message ?? 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    }
}

/**
 * Middleware: ต้องเป็น Admin
 */
function requireAdmin($message = null)
{
    requireRole('admin', $message ?? 'เฉพาะผู้ดูแลระบบเท่านั้น');
}

/**
 * Middleware: ต้องเป็น Manager
 */
function requireManager($message = null)
{
    requireAnyRole(['admin', 'manager'], $message ?? 'เฉพาะผู้บริหารเท่านั้น');
}

// ==================== Page Access Control ====================

/**
 * ตรวจสอบสิทธิ์การเข้าถึงหน้าต่างๆ
 */
$pagePermissions = [
    // Dashboard
    'dashboard/index.php' => 'dashboard.view',

    // Evaluation
    'evaluation/list.php' => 'evaluation.view_own',
    'evaluation/create.php' => 'evaluation.create',
    'evaluation/edit.php' => 'evaluation.edit_own',
    'evaluation/view.php' => 'evaluation.view_own',

    // Portfolio
    'portfolio/index.php' => 'portfolio.view_own',
    'portfolio/add.php' => 'portfolio.create',
    'portfolio/edit.php' => 'portfolio.edit_own',

    // Reports
    'reports/individual.php' => 'reports.view_own',
    'reports/department.php' => 'reports.view_department',
    'reports/organization.php' => 'reports.view_organization',

    // Approval
    'approval/pending-list.php' => 'approval.view_pending',
    'approval/review.php' => 'approval.review',

    // Configuration
    'configuration/personnel-type.php' => 'config.view',
    'configuration/evaluation-aspects.php' => 'config.view',
    'configuration/evaluation-topics.php' => 'config.view',
    'configuration/evaluation-period.php' => 'config.view',

    // Users
    'users/list.php' => 'users.view',
    'users/add.php' => 'users.create',
    'users/edit.php' => 'users.edit',
];

/**
 * ตรวจสอบสิทธิ์หน้าปัจจุบัน
 */
function checkCurrentPagePermission()
{
    global $pagePermissions;

    $currentPage = str_replace(APP_ROOT . '/modules/', '', $_SERVER['SCRIPT_FILENAME']);

    if (isset($pagePermissions[$currentPage])) {
        $permission = $pagePermissions[$currentPage];
        requirePermission($permission);
    }
}

// ==================== View Helpers ====================

/**
 * แสดงเฉพาะเมื่อมีสิทธิ์
 */
function ifCan($permission, $callback)
{
    if (Permission::can($permission)) {
        $callback();
    }
}

/**
 * แสดงเฉพาะเมื่อไม่มีสิทธิ์
 */
function ifCannot($permission, $callback)
{
    if (Permission::cannot($permission)) {
        $callback();
    }
}

/**
 * แสดงเฉพาะเมื่อเป็น role
 */
function ifRole($role, $callback)
{
    if (Permission::hasRole($role)) {
        $callback();
    }
}

/**
 * แสดงเฉพาะเมื่อเป็น admin
 */
function ifAdmin($callback)
{
    if (Permission::isAdmin()) {
        $callback();
    }
}

// ==================== Dynamic Permission Checks ====================

/**
 * ตรวจสอบสิทธิ์แบบ dynamic
 */
class Gate
{

    private static $gates = [];

    /**
     * กำหนด gate
     */
    public static function define($name, $callback)
    {
        self::$gates[$name] = $callback;
    }

    /**
     * ตรวจสอบ gate
     */
    public static function allows($name, ...$args)
    {
        if (!isset(self::$gates[$name])) {
            return false;
        }

        $user = Permission::getUser();
        return call_user_func(self::$gates[$name], $user, ...$args);
    }

    /**
     * ตรวจสอบว่าไม่ผ่าน gate
     */
    public static function denies($name, ...$args)
    {
        return !self::allows($name, ...$args);
    }

    /**
     * Authorize gate หรือ abort
     */
    public static function authorize($name, ...$args)
    {
        if (!self::allows($name, ...$args)) {
            Permission::abort(403, 'คุณไม่มีสิทธิ์ทำการนี้');
        }
    }
}

// ==================== Define Common Gates ====================

Gate::define('update-evaluation', function ($user, $evaluation) {
    // เป็น admin หรือเป็นเจ้าของและอยู่ในสถานะแก้ไขได้
    return $user['role'] === 'admin' ||
        ($evaluation['user_id'] == $user['user_id'] &&
            in_array($evaluation['status'], ['draft', 'returned']));
});

Gate::define('delete-evaluation', function ($user, $evaluation) {
    // เป็น admin หรือเป็นเจ้าของและเป็น draft
    return $user['role'] === 'admin' ||
        ($evaluation['user_id'] == $user['user_id'] &&
            $evaluation['status'] === 'draft');
});

Gate::define('approve-evaluation', function ($user, $evaluation) {
    // เป็น admin หรือ manager และไม่ใช่เจ้าของ
    return in_array($user['role'], ['admin', 'manager']) &&
        $evaluation['user_id'] != $user['user_id'];
});

Gate::define('view-report', function ($user, $reportUserId) {
    // ดูได้ถ้าเป็นของตัวเอง, เป็น admin, หรือเป็น manager ใน department เดียวกัน
    if ($user['user_id'] == $reportUserId) return true;
    if ($user['role'] === 'admin') return true;

    if ($user['role'] === 'manager') {
        // ต้องตรวจสอบ department (ต้องเชื่อมกับฐานข้อมูล)
        return Permission::sameDepartment($reportUserId);
    }

    return false;
});

Gate::define('manage-user', function ($user, $targetUser) {
    // Admin จัดการได้ทุกคน
    if ($user['role'] === 'admin') return true;

    // Manager จัดการได้เฉพาะ staff ใน department เดียวกัน
    if ($user['role'] === 'manager' && $targetUser['role'] === 'staff') {
        return Permission::sameDepartment($targetUser['department_id']);
    }

    return false;
});

// ==================== Initialize ====================

// Set current user
if (isset($_SESSION['user'])) {
    Permission::setUser($_SESSION['user']);
}

// Auto-check page permission (optional - comment out if not needed)
// checkCurrentPagePermission();