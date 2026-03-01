</div> <!-- Close main-content-body -->
        </div> <!-- Close #content -->
    </div> <!-- Close .wrapper -->

    <!-- Load Necessary Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Global notifications (role-aware; safe for all modules) -->
    <script src="/elms_system/assets/js/notifications.js"></script>

    <script>
        // ============================================
        // CSRF Auto-Injection for all fetch() calls
        // ============================================
        (function() {
            const token = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';
            if (!token) return;

            const originalFetch = window.fetch;
            window.fetch = function(url, options) {
                options = options || {};
                const method = (options.method || 'GET').toUpperCase();

                // Only inject for state-changing methods
                if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
                    // If body is FormData, append token
                    if (options.body instanceof FormData) {
                        if (!options.body.has('csrf_token')) {
                            options.body.append('csrf_token', token);
                        }
                    }
                    // If body is URLSearchParams, append token
                    else if (options.body instanceof URLSearchParams) {
                        if (!options.body.has('csrf_token')) {
                            options.body.append('csrf_token', token);
                        }
                    }
                    // For JSON or other bodies, add as header
                    else {
                        options.headers = options.headers || {};
                        if (options.headers instanceof Headers) {
                            if (!options.headers.has('X-CSRF-Token')) {
                                options.headers.set('X-CSRF-Token', token);
                            }
                        } else {
                            if (!options.headers['X-CSRF-Token'] && !options.headers['x-csrf-token']) {
                                options.headers['X-CSRF-Token'] = token;
                            }
                        }
                    }
                }

                return originalFetch.call(this, url, options);
            };

            // Also inject for jQuery $.ajax if jQuery is loaded
            if (window.jQuery) {
                jQuery(document).ajaxSend(function(e, xhr, settings) {
                    if (settings.type && ['POST','PUT','DELETE','PATCH'].includes(settings.type.toUpperCase())) {
                        xhr.setRequestHeader('X-CSRF-Token', token);
                    }
                });
            }
        })();
    </script>

    <script>
        $(document).ready(function () {

            // BURGER MENU FUNCTIONALITY
            $('#sidebarCollapse').on('click', function (e) {
                e.preventDefault();
                $('#sidebar').toggleClass('active');
                
                // Only toggle overlay if on Mobile (screen width < 992px)
                if (window.innerWidth <= 992) {
                    $('#sidebarOverlay').toggleClass('active');
                }
            });

            // Close sidebar when clicking overlay (Mobile only)
            $('#sidebarOverlay').on('click', function () {
                $('#sidebar').removeClass('active');
                $(this).removeClass('active');
            });

            // LOGOUT CONFIRMATION
            $(document).on('click', '.logout-trigger', function (e) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'Sign Out?',
                    text: "Are you sure you want to end your session?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#800000', 
                    cancelButtonColor: '#6c757d',  
                    confirmButtonText: 'Yes, Sign Out',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-4'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../../logout.php';
                    }
                });
            });

            // Ensure Bootstrap modals are always clickable by removing backdrops
            document.addEventListener('shown.bs.modal', function () {
                document.querySelectorAll('.modal-backdrop').forEach(function (el) {
                    el.parentNode && el.parentNode.removeChild(el);
                });
            });
        });
    </script>

    <!-- Cookie Consent Banner -->
    <div id="cookieConsent" style="display:none; position:fixed; bottom:0; left:0; right:0; z-index:9999; background:#1a1a2e; color:#fff; padding:16px 24px; box-shadow:0 -2px 12px rgba(0,0,0,0.15); font-size:0.9rem;">
        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2" style="max-width:1200px; margin:0 auto;">
            <div>
                <i class="bi bi-shield-lock me-1"></i>
                This site uses essential cookies to maintain your session and ensure proper functionality. No tracking or advertising cookies are used.
                <a href="/elms_system/privacy_policy.php" style="color:#ffd54f; text-decoration:underline;">Privacy Policy</a>
            </div>
            <button id="acceptCookies" class="btn btn-sm btn-warning fw-bold px-4" style="white-space:nowrap;">I Understand</button>
        </div>
    </div>
    <script>
    (function() {
        if (!localStorage.getItem('elms_cookie_consent')) {
            document.getElementById('cookieConsent').style.display = 'block';
        }
        document.getElementById('acceptCookies')?.addEventListener('click', function() {
            localStorage.setItem('elms_cookie_consent', Date.now());
            document.getElementById('cookieConsent').style.display = 'none';
        });
    })();
    </script>
</body>
</html>