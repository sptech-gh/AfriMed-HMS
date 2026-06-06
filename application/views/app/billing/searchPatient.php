<!DOCTYPE html>
<html>
    <head>
<head>

        <meta charset="UTF-8">
        <title>Hebrew Medical Center</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    

        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
        
        <!----------BOOTSTRAP DATEPICKER----------------------------->
    	<link rel="stylesheet" href="<?php echo base_url();?>public/datepicker/css/datepicker.css">
		<!---------------------------------------------------------->
        
        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->
    </head>  
    <body class="skin-blue">
        <!-- header logo: style can be found in header.less -->
        <?php require_once(APPPATH.'views/include/header.php');?>
        
        <div class="wrapper row-offcanvas row-offcanvas-left">
            
            <?php require_once(APPPATH.'views/include/sidebar.php');?>

            <!-- Right side column. Contains the navbar and content of the page -->
            <aside class="right-side">                
                <!-- Content Header (Page header) -->
                <section class="content-header">
                    <h1>POS</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Billing</a></li>
                        <li class="active">POS</li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">

                 <div class="row">
                 	<div class="col-md-12">
                    
                    	 <div class="box">
                         		
                         		<div class="box-header">
                                    <h3 class="box-title"><i class="fa fa-search"></i> Search Patient</h3>
                                </div>
                        	<div class="box-body">
                            	<form role="form" method="post" action="<?php echo base_url()?>app/billing/pointOfSale" id="patientSearchForm">
                            		<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                		
                                		<div class="form-group">
                                            <label>Search by Name, Patient No, or Phone</label>
                                            <input type="text" id="patientSearch" class="form-control" placeholder="Type to search patient..." autocomplete="off" style="max-width: 400px;">
                                            <input type="hidden" name="IO_ID" id="IO_ID" required>
                                        </div>
                                        
                                        <!-- Selected Patient Display -->
                                        <div id="selectedPatient" style="display: none; margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; border-left: 4px solid #3c8dbc;">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <strong><i class="fa fa-user"></i> Patient:</strong> <span id="selName">-</span><br>
                                                    <strong><i class="fa fa-id-card"></i> Patient No:</strong> <span id="selPatientNo">-</span>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong><i class="fa fa-phone"></i> Phone:</strong> <span id="selPhone">-</span><br>
                                                    <strong><i class="fa fa-calendar"></i> Visit Date:</strong> <span id="selDate">-</span>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-xs btn-default" onclick="clearSelection()" style="margin-top: 10px;"><i class="fa fa-times"></i> Clear</button>
                                        </div>
                                        
                                        <!-- Search Results Dropdown -->
                                        <div id="searchResults" style="display: none; position: absolute; z-index: 1000; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 300px; overflow-y: auto; width: 400px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);"></div>
                                        
                                        <hr>
                                        
                                        <button class="btn btn-primary btn-lg" name="btnSubmit" id="btnSubmit" type="submit" disabled>
                                            <i class="fa fa-arrow-circle-right"></i> Continue to Billing
                                        </button>
                                        
                                </form>

                                <div class="callout callout-info" style="margin-top: 20px;">
                                    <i class="fa fa-info-circle"></i> <strong>Tip:</strong> Type at least 2 characters to search. You can search by patient name, patient number, or phone number.
                                </div>
                                
                                <div class="callout callout-warning" style="margin-top: 10px;">
                                    <i class="fa fa-exclamation-triangle"></i> <strong>Final System Clearance</strong> should only be done after billing is accurate/settled and pharmacy medication clearance is completed.
                                </div>
                                
                            </div>
                        </div>
                    </div>
                 </div>
                 
                 
                </section><!-- /.content -->
            </aside><!-- /.right-side -->
        </div><!-- ./wrapper -->
  
        
         <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
         <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>     
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
        
        <!-- Patient Search Script -->
        <script type="text/javascript">
            var searchTimeout = null;
            var baseUrl = '<?php echo base_url(); ?>';
            
            $(document).ready(function() {
                var $search = $('#patientSearch');
                var $results = $('#searchResults');
                var $ioId = $('#IO_ID');
                var $btn = $('#btnSubmit');
                var $selected = $('#selectedPatient');
                
                // Search on input
                $search.on('input', function() {
                    var query = $(this).val().trim();
                    
                    // Clear previous timeout
                    if (searchTimeout) clearTimeout(searchTimeout);
                    
                    if (query.length < 2) {
                        $results.hide().empty();
                        return;
                    }
                    
                    // Debounce - wait 300ms after typing stops
                    searchTimeout = setTimeout(function() {
                        searchPatients(query);
                    }, 300);
                });
                
                // Hide results when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('#patientSearch, #searchResults').length) {
                        $results.hide();
                    }
                });
                
                // Show results on focus if there's text
                $search.on('focus', function() {
                    if ($(this).val().trim().length >= 2 && $results.children().length > 0) {
                        $results.show();
                    }
                });
            });
            
            function searchPatients(query) {
                var $results = $('#searchResults');
                
                $results.html('<div style="padding: 10px; text-align: center;"><i class="fa fa-spinner fa-spin"></i> Searching...</div>').show();
                
                $.ajax({
                    url: baseUrl + 'app/billing/search_patient_ajax',
                    type: 'GET',
                    data: { q: query, limit: 20 },
                    dataType: 'json',
                    success: function(data) {
                        if (data && data.error) {
                            $results.html('<div style="padding: 15px; text-align: center; color: #d9534f;"><i class="fa fa-exclamation-triangle"></i> ' + data.error + '</div>');
                            return;
                        }
                        if (!data || data.length === 0) {
                            $results.html('<div style="padding: 15px; text-align: center; color: #999;"><i class="fa fa-exclamation-circle"></i> No patients found</div>');
                            return;
                        }
                        
                        var html = '';
                        for (var i = 0; i < data.length; i++) {
                            var p = data[i];
                            html += '<div class="search-result-item" onclick="selectPatient(\'' + p.id + '\', \'' + escapeHtml(p.name) + '\', \'' + p.patient_no + '\', \'' + (p.phone || '-') + '\', \'' + (p.date || '-') + '\')" style="padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #eee;">';
                            html += '<strong>' + escapeHtml(p.name) + '</strong>';
                            html += '<br><small style="color: #666;"><i class="fa fa-id-card"></i> ' + p.patient_no + ' &nbsp; <i class="fa fa-stethoscope"></i> ' + p.type + '</small>';
                            if (p.phone) {
                                html += '<br><small style="color: #888;"><i class="fa fa-phone"></i> ' + p.phone + '</small>';
                            }
                            html += '</div>';
                        }
                        $results.html(html);
                    },
                    error: function(xhr, status, error) {
                        console.log('Search error:', status, error, xhr.responseText);
                        $results.html('<div style="padding: 15px; text-align: center; color: #d9534f;"><i class="fa fa-exclamation-triangle"></i> Search failed. Please try again.</div>');
                    }
                });
            }
            
            function selectPatient(ioId, name, patientNo, phone, date) {
                $('#IO_ID').val(ioId);
                $('#patientSearch').val(name);
                $('#searchResults').hide();
                
                // Show selected patient info
                $('#selName').text(name);
                $('#selPatientNo').text(patientNo);
                $('#selPhone').text(phone || '-');
                $('#selDate').text(date || '-');
                $('#selectedPatient').slideDown();
                
                // Enable submit button
                $('#btnSubmit').prop('disabled', false);
            }
            
            function clearSelection() {
                $('#IO_ID').val('');
                $('#patientSearch').val('').focus();
                $('#selectedPatient').slideUp();
                $('#btnSubmit').prop('disabled', true);
            }
            
            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            }
        </script>
        
        <style>
            .search-result-item:hover {
                background-color: #f5f5f5 !important;
            }
            #patientSearch {
                font-size: 16px;
                padding: 10px 15px;
                height: auto;
            }
        </style>
        
    </body>
</html>