</div> <!-- Close main-content-body -->
</div> <!-- Close #content -->
</div> <!-- Close .wrapper -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/notifications.js"></script>
<script>
    $(document).ready(function () {
        // --- BURGER MENU ENGINE ---
        $('#sidebarCollapse').on('click', function (e) {
            e.preventDefault();
            // Toggle active class
            $('#sidebar').toggleClass('active');
            // Toggle overlay for mobile
            $('#sidebarOverlay').toggleClass('active');
            console.log("Burger menu toggled successfully");
        });

        // Close sidebar if user clicks the dark background on mobile
        $('#sidebarOverlay').on('click', function () {
            $('#sidebar').removeClass('active');
            $(this).removeClass('active');
        });
    });
</script>
</body>
</html>