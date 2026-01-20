</div> <!-- Close main-content-body -->
</div> <!-- Close #content -->
</div> <!-- Close .wrapper -->

<!-- Load Necessary Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function () {
        // --- 1. BURGER MENU FUNCTIONALITY ---
        $('#sidebarCollapse').on('click', function (e) {
            e.preventDefault();
            $('#sidebar').toggleClass('active');
            $('#sidebarOverlay').toggleClass('active');
        });

        $('#sidebarOverlay').on('click', function () {
            $('#sidebar').removeClass('active');
            $(this).removeClass('active');
        });

        // --- 2. LOGOUT CONFIRMATION (SWEETALERT) ---
        $('#logoutTrigger').on('click', function (e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Logout Account?',
                text: "You will need to sign in again to access the ELMS portal.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#800000', // Maroon
                cancelButtonColor: '#003366',  // Blue
                confirmButtonText: 'Yes, Logout',
                cancelButtonText: 'Stay Logged In',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../logout.php';
                }
            });
        });
    });
</script>
</body>
</html>