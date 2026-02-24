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
</body>
</html>