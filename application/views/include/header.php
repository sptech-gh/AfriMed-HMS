 <!-- Load jQuery locally first (no CDN dependency) -->
   <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
   <script>
    setTimeout(function timeru(){$('.alert').fadeOut(1000)}, 3000);
   </script>
	<script type="text/javascript">
	(function(){
		try {
			var tokenName = <?php echo json_encode($this->security->get_csrf_token_name()); ?>;
			var cookieName = <?php echo json_encode($this->config->item('csrf_cookie_name')); ?>;
			function getCookie(name){
				var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()\[\]\\\/\+^]/g, '\\$&') + '=([^;]*)'));
				return m ? decodeURIComponent(m[1]) : '';
			}
			function csrfHash(){
				return getCookie(cookieName);
			}
			if (window.jQuery && window.jQuery.ajaxPrefilter) {
				window.jQuery.ajaxPrefilter(function(options, originalOptions){
					var type = (options.type || 'GET').toUpperCase();
					if (type !== 'POST') return;
					var h = csrfHash();
					if (!h) return;
					if (typeof originalOptions.data === 'string') {
						if (originalOptions.data.indexOf(tokenName + '=') === -1) {
							options.data = originalOptions.data + (originalOptions.data ? '&' : '') + encodeURIComponent(tokenName) + '=' + encodeURIComponent(h);
						}
					} else if (typeof originalOptions.data === 'object') {
						if (!originalOptions.data) originalOptions.data = {};
						if (!Object.prototype.hasOwnProperty.call(originalOptions.data, tokenName)) {
							originalOptions.data[tokenName] = h;
							options.data = originalOptions.data;
						}
					} else if (typeof options.data === 'string') {
						if (options.data.indexOf(tokenName + '=') === -1) {
							options.data = options.data + (options.data ? '&' : '') + encodeURIComponent(tokenName) + '=' + encodeURIComponent(h);
						}
					} else if (typeof options.data === 'object') {
						if (!options.data) options.data = {};
						if (!Object.prototype.hasOwnProperty.call(options.data, tokenName)) {
							options.data[tokenName] = h;
						}
					}
				});
			}
		} catch (e) {}
	})();
	</script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
   <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
   <!-- Global sidebar toggle - pure JS, no jQuery dependency, works on all pages -->
   <script type="text/javascript">
   (function(){
       function sidebarToggle(e) {
           e.preventDefault();
           var w = window.innerWidth || document.documentElement.clientWidth;
           if (w <= 992) {
               var row = document.querySelector('.row-offcanvas');
               if (row) {
                   row.classList.toggle('active');
                   row.classList.toggle('relative');
               }
               var left = document.querySelector('.left-side');
               if (left) left.classList.remove('collapse-left');
               var right = document.querySelector('.right-side');
               if (right) right.classList.remove('strech');
           } else {
               var left = document.querySelector('.left-side');
               if (left) left.classList.toggle('collapse-left');
               var right = document.querySelector('.right-side');
               if (right) right.classList.toggle('strech');
           }
       }
       document.addEventListener('click', function(e) {
           var t = e.target;
           // Walk up to handle clicks on child <span> elements inside the toggle button
           while (t && t !== document) {
               if (t.getAttribute && (t.getAttribute('data-toggle') === 'offcanvas' || t.classList.contains('sidebar-toggle'))) {
                   sidebarToggle(e);
                   return;
               }
               t = t.parentNode;
           }
       }, false);
   })();
   </script>
	<link href="<?php echo base_url();?>public/css/hms-enhanced.css?v=<?php echo time(); ?>" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url();?>assets/css/hms-responsive.css?v=<?php echo time(); ?>" rel="stylesheet" type="text/css" />
	<?php $ui_theme = $this->session->userdata('ui_theme'); if ($ui_theme !== 'dark' && $ui_theme !== 'light') { $ui_theme = 'light'; } ?>
	<script type="text/javascript">
	(function(){
		try {
			var serverTheme = '<?php echo $ui_theme; ?>';
			var stored = localStorage.getItem('hms_ui_theme');
			var theme = (stored === 'dark' || stored === 'light') ? stored : serverTheme;
			if (theme === 'dark') {
				document.documentElement.classList.add('theme-dark');
				document.documentElement.classList.remove('theme-light');
				if (document.body) {
					document.body.classList.add('theme-dark');
					document.body.classList.remove('theme-light');
				}
			} else {
				document.documentElement.classList.add('theme-light');
				document.documentElement.classList.remove('theme-dark');
				if (document.body) {
					document.body.classList.add('theme-light');
					document.body.classList.remove('theme-dark');
				}
			}
		} catch (e) {}
	})();
	</script>
	<?php
		$companyName = getFacilityName();
		$headerLogo = getFacilityLogo();
		$siteTitle = getFacilityName() . ' | ' . getPlatformName();

		if (!isset($userInfo) || !is_object($userInfo)) {
			if (isset($this) && isset($this->data) && isset($this->data['userInfo']) && is_object($this->data['userInfo'])) {
				$userInfo = $this->data['userInfo'];
			} else if (isset($this->general_model)) {
				$userInfo = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
			}
		}
	?>
	<script type="text/javascript">
	(function(){
		try {
			document.title = <?php echo json_encode($siteTitle); ?>;
			var link = document.querySelector("link[rel*='icon']");
			if (!link) {
				link = document.createElement('link');
				link.type = 'image/png';
				link.rel = 'shortcut icon';
				document.getElementsByTagName('head')[0].appendChild(link);
			}
			link.href = <?php echo json_encode(BrandingService::platformLogo()); ?>;
		} catch (e) {}
	})();
	</script>

	<script type="text/javascript">
	(function(){
		function ensureOverlay(){
			if (document.getElementById('hms-loading-overlay')) return;
			var el = document.createElement('div');
			el.id = 'hms-loading-overlay';
			el.className = 'loading-overlay';
			el.innerHTML = '<div class="loading-spinner"></div>';
			document.body.appendChild(el);
		}
		function showOverlay(){
			ensureOverlay();
			var el = document.getElementById('hms-loading-overlay');
			if (el) el.classList.add('active');
		}
		document.addEventListener('click', function(e){
			var t = e.target;
			if (!t) return;
			if (t.tagName && t.tagName.toLowerCase() === 'i' && t.parentNode) {
				t = t.parentNode;
			}
			if (!t.tagName) return;
			var tag = t.tagName.toLowerCase();
			if (tag !== 'button' && tag !== 'input') return;
			var type = (t.getAttribute('type') || '').toLowerCase();
			if (type !== 'submit') return;
			var form = t.form;
			if (!form) return;
			form.__hmsSubmitBtn = t;
		}, true);

		document.addEventListener('submit', function(e){
			if (e.defaultPrevented) return;
			var form = e.target;
			if (!form || !form.querySelectorAll) return;
			var btns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
			for (var i = 0; i < btns.length; i++) {
				btns[i].setAttribute('disabled', 'disabled');
			}
			if (form.__hmsSubmitBtn && form.__hmsSubmitBtn.classList) {
				form.__hmsSubmitBtn.classList.add('btn-loading');
			}
			showOverlay();
		}, false);
	})();
	</script>

<header class="header">
    <a href="<?php echo base_url(); ?>app/dashboard" class="logo" style="display: flex; align-items: center; gap: 8px; justify-content: center; text-decoration: none;">
        <img src="<?php echo getFacilityLogo(); ?>" alt="Logo" style="max-height: 32px; width: auto; border-radius: 4px;">
        <span style="font-weight: bold; font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #fff;"><?php echo getFacilityName(); ?></span>
    </a>
    <nav class="navbar navbar-static-top" role="navigation">
        <!-- Sidebar toggle button-->
        <a href="#" class="navbar-btn sidebar-toggle" data-toggle="offcanvas" role="button">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
        </a>
        <div class="logo2" style="display: flex; align-items: center; gap: 10px; height: 50px; float: left; padding: 0 15px; line-height: 50px; color: #fff; font-size: 20px; font-weight: 500;">
            <span class="facility-header-name" style="font-weight: 700; font-size: 18px; color: #ffffff;"><?php echo getFacilityName(); ?></span>
            <span class="platform-power-badge" style="font-size: 10px; background: rgba(255,255,255,0.15); padding: 2px 8px; border-radius: 20px; line-height: 1.4; color: rgba(255,255,255,0.85); font-weight: 600; display: inline-flex; align-items: center; gap: 4px; vertical-align: middle;">
                Powered by Reddy HMS
            </span>
        </div>
        <div class="navbar-right">
            <ul class="nav navbar-nav">
				<?php
				// NHIS Alert Badge
				$nhisAlertTotal = 0;
				if (isset($this) && isset($this->billing_model) && method_exists($this->billing_model, 'get_nhis_alert_counts')) {
					$nhisAlertData = $this->billing_model->get_nhis_alert_counts();
					$nhisAlertTotal = isset($nhisAlertData['total_alerts']) ? (int)$nhisAlertData['total_alerts'] : 0;
				}
				?>
				<?php if($nhisAlertTotal > 0): ?>
				<li>
					<a href="<?php echo base_url(); ?>app/nhis_claims" title="NHIS Claims Alerts">
						<i class="fa fa-medkit"></i>
						<span class="label label-danger" style="position:relative;top:-8px;left:-5px;font-size:10px;"><?php echo $nhisAlertTotal; ?></span>
					</a>
				</li>
				<?php endif; ?>
				<?php if (isset($hasAccesstoDoctor) && $hasAccesstoDoctor): ?>
				<li class="dropdown notifications-menu">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" title="Lab Notifications">
						<i class="fa fa-bell-o"></i>
						<span class="label label-warning lab-notif-badge" style="position:relative;top:-8px;left:-5px;font-size:10px;display:none;">0</span>
					</a>
					<ul class="dropdown-menu" style="width:320px;">
						<li class="header" style="padding:8px 12px;background:#f5f5f5;border-bottom:1px solid #ddd;">
							<i class="fa fa-flask"></i> Lab Result Notifications
						</li>
						<li>
							<ul class="menu lab-notif-list" style="max-height:280px;overflow-y:auto;list-style:none;padding:0;margin:0;">
								<li class="text-center" style="padding:15px;"><i class="fa fa-spinner fa-spin"></i> Loading...</li>
							</ul>
						</li>
						<li class="footer" style="padding:6px 12px;background:#f5f5f5;border-top:1px solid #ddd;text-align:center;">
							<a href="#" class="mark-all-lab-notif-read"><i class="fa fa-check"></i> Mark all as read</a>
						</li>
					</ul>
				</li>
				<?php endif; ?>
				<li>
					<a href="#" id="hms-theme-toggle" title="Toggle dark/light mode">
						<i class="fa fa-adjust"></i>
					</a>
				</li>
				<?php
				// Prepare user display info
				$_hdr_fn = isset($userInfo) && isset($userInfo->firstname) ? trim((string)$userInfo->firstname) : '';
				$_hdr_ln = isset($userInfo) && isset($userInfo->lastname) ? trim((string)$userInfo->lastname) : '';
				$_hdr_fullname = trim($_hdr_fn . ' ' . $_hdr_ln);
				if ($_hdr_fullname === '') { $_hdr_fullname = isset($this) ? (string)$this->session->userdata('username') : 'User'; }
				$_hdr_role = '';
				if (isset($userInfo) && is_object($userInfo)) {
					if (isset($userInfo->role_name) && trim((string)$userInfo->role_name) !== '') {
						$_hdr_role = trim((string)$userInfo->role_name);
					} elseif (isset($userInfo->module) && trim((string)$userInfo->module) !== '') {
						$_hdr_role = ucwords(str_replace('_', ' ', trim((string)$userInfo->module)));
					}
				}
				$_hdr_designation = (isset($userInfo) && is_object($userInfo) && isset($userInfo->designation)) ? trim((string)$userInfo->designation) : '';
				$_hdr_pic = (isset($userInfo) && is_object($userInfo) && isset($userInfo->picture) && trim((string)$userInfo->picture) !== '') ? trim((string)$userInfo->picture) : '';
				$_hdr_isDoctor = (isset($hasAccesstoDoctor) && $hasAccesstoDoctor);
				$_hdr_displayName = $_hdr_isDoctor ? 'Dr. ' . htmlspecialchars($_hdr_fullname) : htmlspecialchars($_hdr_fullname);
				?>
                <li class="dropdown user user-menu">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" style="display:flex;align-items:center;gap:8px;padding:6px 12px;height:50px;box-sizing:border-box;">
						<?php if ($_hdr_pic !== ''): ?>
						<img src="<?php echo base_url(); ?>public/user_picture/<?php echo $_hdr_pic; ?>" class="img-circle" alt="User" style="width:28px;height:28px;object-fit:cover;">
						<?php else: ?>
						<i class="fa fa-user-circle" style="font-size:22px;"></i>
						<?php endif; ?>
						<span style="line-height:1.15;display:flex;flex-direction:column;justify-content:center;min-width:0;max-width:180px;">
							<span style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;max-width:180px;">
								<?php echo $_hdr_displayName; ?>
							</span>
							<?php if ($_hdr_role !== ''): ?>
							<small style="opacity:0.85;font-size:10px;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;max-width:180px;">
								<?php echo htmlspecialchars($_hdr_role); ?>
							</small>
							<?php endif; ?>
						</span>
						<i class="caret" style="margin-left:4px;flex:0 0 auto;"></i>
                    </a>
                    <ul class="dropdown-menu" style="min-width:260px;">
                        <li class="user-header bg-light-blue" style="text-align:center;padding:20px 10px;">
							<?php if ($_hdr_pic !== ''): ?>
							<img src="<?php echo base_url(); ?>public/user_picture/<?php echo $_hdr_pic; ?>" class="img-circle" alt="User" style="width:80px;height:80px;object-fit:cover;border:3px solid rgba(255,255,255,0.3);">
							<?php else: ?>
							<img src="<?php echo base_url(); ?>public/user_picture/no_avatar.gif" class="img-circle" alt="User" style="width:80px;height:80px;object-fit:cover;border:3px solid rgba(255,255,255,0.3);">
							<?php endif; ?>
                            <p style="margin-top:10px;margin-bottom:0;font-size:15px;font-weight:600;">
								<?php echo $_hdr_displayName; ?>
                            </p>
							<?php if ($_hdr_role !== ''): ?>
							<p style="margin:2px 0 0;font-size:12px;opacity:0.9;">
								<i class="fa fa-shield"></i> <?php echo htmlspecialchars($_hdr_role); ?>
							</p>
							<?php endif; ?>
							<?php if ($_hdr_designation !== ''): ?>
							<p style="margin:2px 0 0;font-size:11px;opacity:0.8;">
								<?php echo htmlspecialchars($_hdr_designation); ?>
							</p>
							<?php endif; ?>
                        </li>
                        <li>
							<a href="<?php echo base_url(); ?>myprofile" style="padding:10px 15px;">
								<i class="fa fa-user" style="width:20px;text-align:center;margin-right:6px;color:#3c8dbc;"></i> My Profile
							</a>
                        </li>
                        <li>
							<a href="<?php echo base_url(); ?>myprofile/editprofile" style="padding:10px 15px;">
								<i class="fa fa-pencil" style="width:20px;text-align:center;margin-right:6px;color:#3c8dbc;"></i> Edit Profile
							</a>
                        </li>
                        <li class="divider" style="margin:4px 0;"></li>
                        <li>
							<a href="<?php echo base_url(); ?>login/logout" style="padding:10px 15px;color:#dd4b39;">
								<i class="fa fa-sign-out" style="width:20px;text-align:center;margin-right:6px;"></i> Sign Out
							</a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
</header>





<script type="text/javascript">
// function closeAd(id)
// {
//     $('#' + id).remove();
// }

// $(document).ready(function() {
//     $('select').select2({
//     closeOnSelect: false
// });
// });
</script>

	<script type="text/javascript">
	(function(){
		function applyTheme(theme){
			if (theme === 'dark') {
				document.documentElement.classList.add('theme-dark');
				document.documentElement.classList.remove('theme-light');
				document.body.classList.add('theme-dark');
				document.body.classList.remove('theme-light');
			} else {
				document.documentElement.classList.add('theme-light');
				document.documentElement.classList.remove('theme-dark');
				document.body.classList.add('theme-light');
				document.body.classList.remove('theme-dark');
			}
		}
		function persistTheme(theme){
			try { localStorage.setItem('hms_ui_theme', theme); } catch (e) {}
			try {
				var xhr = new XMLHttpRequest();
				xhr.open('GET', '<?php echo base_url(); ?>general/setTheme/' + theme, true);
				xhr.send(null);
			} catch (e) {}
		}
		function getCurrentTheme(){
			try {
				var stored = localStorage.getItem('hms_ui_theme');
				if (stored === 'dark' || stored === 'light') return stored;
			} catch (e) {}
			return document.body.classList.contains('theme-dark') ? 'dark' : 'light';
		}
		var btn = document.getElementById('hms-theme-toggle');
		if (!btn) return;
		btn.addEventListener('click', function(e){
			e.preventDefault();
			var cur = getCurrentTheme();
			var next = (cur === 'dark') ? 'light' : 'dark';
			applyTheme(next);
			persistTheme(next);
		});
	})();
	</script>

	<script src="<?php echo base_url();?>public/js/theme-toggle.js"></script>

	<?php if (isset($hasAccesstoDoctor) && $hasAccesstoDoctor): ?>
	<script type="text/javascript">
	(function(){
		var BASE = '<?php echo base_url(); ?>';
		function refreshLabNotifCount(){
			$.ajax({
				url: BASE + 'app/medical_data/count_notifications',
				type: 'get', dataType: 'json',
				success: function(d){
					var c = d && d.count ? parseInt(d.count) : 0;
					if (c > 0) { $('.lab-notif-badge').text(c).show(); }
					else { $('.lab-notif-badge').hide(); }
				}
			});
		}
		function loadLabNotifList(){
			$.ajax({
				url: BASE + 'app/medical_data/get_notifications',
				type: 'get', dataType: 'json',
				success: function(data){
					var html = '';
					if (!data || data.length === 0) {
						html = '<li style="padding:15px;text-align:center;color:#999;">No new notifications</li>';
					} else {
						for (var i = 0; i < Math.min(data.length, 10); i++) {
							var n = data[i];
							html += '<li style="border-bottom:1px solid #f0f0f0;">';
							html += '<a href="#" class="lab-notif-item" data-id="' + n.notif_id + '" style="padding:8px 12px;display:block;">';
							html += '<i class="fa fa-flask text-info"></i> ' + escNotifH(n.title);
							html += '<br><small class="text-muted">' + escNotifH(n.created_at||'') + '</small>';
							html += '</a></li>';
						}
					}
					$('.lab-notif-list').html(html);
				}
			});
		}
		function escNotifH(s){ if(!s) return ''; var d=document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
		$(document).on('click', '.notifications-menu .dropdown-toggle', function(){ loadLabNotifList(); });
		$(document).on('click', '.lab-notif-item', function(e){
			e.preventDefault();
			var id = $(this).data('id');
			var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
			var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
			var postData = {notif_id: id};
			postData[csrfName] = csrfHash;
			$.post(BASE + 'app/medical_data/mark_read', postData);
			$(this).closest('li').fadeOut();
			refreshLabNotifCount();
		});
		$(document).on('click', '.mark-all-lab-notif-read', function(e){
			e.preventDefault();
			var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
			var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
			var postData = {};
			postData[csrfName] = csrfHash;
			$.post(BASE + 'app/medical_data/mark_all_read', postData, function(){
				refreshLabNotifCount();
				loadLabNotifList();
			});
		});
		$(document).ready(function(){
			refreshLabNotifCount();
			setInterval(refreshLabNotifCount, 30000);
		});
	})();
	</script>
	<?php endif; ?>
