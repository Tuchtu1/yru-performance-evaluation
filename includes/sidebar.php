<?php
// sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page, $module = '')
{
    global $current_page, $current_module;
    if ($module && $module === $current_module) {
        return 'bg-blue-50 text-blue-600 border-r-4 border-blue-600';
    }
    if ($page === $current_page) {
        return 'bg-blue-50 text-blue-600 border-r-4 border-blue-600';
    }
    return 'text-gray-700 hover:bg-gray-50';
}
?>

<aside class="w-64 bg-white border-r border-gray-200 min-h-screen">
    <nav class="p-4 space-y-1">

        <!-- Dashboard -->
        <a href="<?php echo url('modules/dashboard/index.php'); ?>"
            class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors <?php echo isActive('index.php', 'dashboard'); ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="font-medium">หน้าหลัก</span>
        </a>

        <?php if ($_SESSION['user']['role'] === 'staff'): ?>
            <!-- สำหรับบุคลากร -->
            <div class="pt-4 pb-2">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase">การประเมิน</p>
            </div>

            <a href="<?php echo url('modules/evaluation/list.php'); ?>"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors <?php echo isActive('list.php', 'evaluation'); ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>แบบประเมินของฉัน</span>
            </a>

            <a href="<?php echo url('modules/evaluation/create.php'); ?>"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors <?php echo isActive('create.php', 'evaluation'); ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>สร้างแบบประเมินใหม่</span>
            </a>

            <a href="<?php echo url('modules/portfolio/index.php'); ?>"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors <?php echo isActive('index.php', 'portfolio'); ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>คลังผลงาน</span>
            </a>

        <?php elseif ($_SESSION['user']['role'] === 'manager'): ?>
            <!-- สำหรับผู้บริหาร -->
            <div class="pt-4 pb-2">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase">การพิจารณา</p>
            </div>

            <a href="<?php echo url('modules/approval/pending-list.php'); ?>"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors <?php echo isActive('pending-list.php', 'approval'); ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span>รออนุมัติ</span>
                <span class="ml-auto bg-red-500 text-white text-xs font-semibold px-2.5 py-0.5 rounded-full">5</span>
            </a>

            <a href="<?php echo url('modules/approval/history.php'); ?>"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors <?php echo isActive('history.php', 'approval'); ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>ประวัติการพิจารณา</span>
            </a>

        <?php elseif ($_SESSION['user']['role'] === 'admin'): ?>
            <!-- สำหรับผู้ดูแลระบบ -->
            <div class="pt-4 pb-2">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase">การจัดการระบบ</p>
            </div>

            <a href="<?php echo url('modules/configuration/personnel-type.php'); ?>"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors <?php echo isActive('', 'configuration'); ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>ตั้งค่าระบบ</span>
            </a>

            <a href="<?php echo url('modules/users/list.php'); ?>"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors <?php echo isActive('list.php', 'users'); ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>จัดการผู้ใช้งาน</span>
            </a>
        <?php endif; ?>

        <!-- รายงาน (ทุกบทบาท) -->
        <div class="pt-4 pb-2">
            <p class="px-4 text-xs font-semibold text-gray-400 uppercase">รายงาน</p>
        </div>

        <a href="<?php echo url('modules/reports/individual.php'); ?>"
            class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors <?php echo isActive('individual.php', 'reports'); ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>รายงานรายบุคคล</span>
        </a>

        <?php if (in_array($_SESSION['user']['role'], ['admin', 'manager'])): ?>
            <a href="<?php echo url('modules/reports/department.php'); ?>"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors <?php echo isActive('department.php', 'reports'); ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>รายงานรายหน่วยงาน</span>
            </a>

            <a href="<?php echo url('modules/reports/organization.php'); ?>"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors <?php echo isActive('organization.php', 'reports'); ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>รายงานภาพรวมองค์กร</span>
            </a>
        <?php endif; ?>

    </nav>
</aside>