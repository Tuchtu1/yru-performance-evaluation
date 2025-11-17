<!-- include/footer.php-->
</main>
</div>
</div>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex justify-center md:order-2 space-x-6">
                <a href="#" class="text-gray-400 hover:text-gray-500 transition-colors">
                    <span class="sr-only">Facebook</span>
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd"
                            d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"
                            clip-rule="evenodd" />
                    </svg>
                </a>
                <a href="#" class="text-gray-400 hover:text-gray-500 transition-colors">
                    <span class="sr-only">YouTube</span>
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd"
                            d="M19.812 5.418c.861.23 1.538.907 1.768 1.768C21.998 8.746 22 12 22 12s0 3.255-.418 4.814a2.504 2.504 0 0 1-1.768 1.768c-1.56.419-7.814.419-7.814.419s-6.255 0-7.814-.419a2.505 2.505 0 0 1-1.768-1.768C2 15.255 2 12 2 12s0-3.255.417-4.814a2.507 2.507 0 0 1 1.768-1.768C5.744 5 11.998 5 11.998 5s6.255 0 7.814.418ZM15.194 12 10 15V9l5.194 3Z"
                            clip-rule="evenodd" />
                    </svg>
                </a>
                <a href="mailto:info@yru.ac.th" class="text-gray-400 hover:text-gray-500 transition-colors">
                    <span class="sr-only">Email</span>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </a>
            </div>
            <div class="mt-4 md:mt-0 md:order-1">
                <div class="text-center md:text-left">
                    <p class="text-sm text-gray-500">
                        © <?php echo date('Y'); ?> มหาวิทยาลัยราชภัฏยะลา |
                        <a href="https://www.yru.ac.th" target="_blank"
                            class="text-blue-600 hover:text-blue-700">www.yru.ac.th</a>
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        ระบบประเมินผลการปฏิบัติงาน เวอร์ชัน <?php echo APP_VERSION; ?> |
                        พัฒนาโดยสำนักบริการวิชาการ
                    </p>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Custom JavaScript -->
<script src="<?php echo asset('js/main.js'); ?>"></script>
<script src="<?php echo asset('js/notification.js'); ?>"></script>

</body>

</html>