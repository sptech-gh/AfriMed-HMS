    <!-- Core JavaScript -->
    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    
    <!-- Select2 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
    
    <!-- Datepicker -->
    <script src="<?php echo base_url(); ?>public/datepicker/js/bootstrap-datepicker.js"></script>
    
    <!-- Enhanced UI/UX JavaScript -->
    <script src="<?php echo base_url(); ?>public/js/hms-enhanced.js"></script>
    
    <!-- Initialize Select2 -->
    <script type="text/javascript">
        $(document).ready(function() {
            // Initialize Select2 on all select elements
            if ($.fn && $.fn.select2) {
                $('select').select2({
                    theme: 'bootstrap',
                    width: '100%'
                });
            }
            
            // Initialize datepickers
            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true
            });
        });
    </script>
</body>
</html>
