<?php
$asset_path = 'https://tanitbet216.com/ui/www.moneyking365.com/';
?>
<!-- Footer Scripts -->
<script src="<?php echo $asset_path; ?>assets/js/jquery-3.2.1.min.js"></script>
<script src="<?php echo $asset_path; ?>assets/js/bootstrap.min.js"></script>
<script src="<?php echo $asset_path; ?>assets/owlcarousel/owl.carousel.js"></script>

<script>
    $(document).ready(function(){
        // Initialize Main Carousel
        $("#pc-carousel").owlCarousel({
            items: 1,
            loop: true,
            autoplay: true,
            dots: false,
            nav: false
        });

        // Toggle Password Visibility
        $(".toggelPass").click(function() {
            $(this).toggleClass("fa-eye fa-eye-slash");
            var input = $($(this).attr("toggle"));
            if (input.attr("type") == "password") {
                input.attr("type", "text");
            } else {
                input.attr("type", "password");
            }
        });
        
        // Mobile Menu Toggle (if needed for the navbar-toggle in header-ui)
        // Bootstrap 3 handles this via data-target="#bs-example-navbar-collapse-1"
        // But we need to ensure the target ID exists. 
        // In header-ui.php, the button targets #bs-example-navbar-collapse-1
        // But I didn't see the collapse div in header-ui.php!
        // I need to check header-ui.php again.
    });
</script>

<!-- Close Landing Page Wrappers -->
</div> <!-- .landingpage -->
</app-d1-landing-dashboard>

</body>
</html>
