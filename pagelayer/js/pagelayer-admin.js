// Pagelayer Admin UI - Modern Sidebar Navigation
jQuery(document).ready(function($){

	/* ======================================================
	   1. NEW SIDEBAR NAV → Tab Panel switching
	   ====================================================== */
	// Helper to update hash with full element style state
	window.pl_block_hash_update = false;
	function pl_update_hash() {
		if (window.pl_block_hash_update) return;
		if ($('#headings').hasClass('pagelayer-tab-active')) {
			var activeHeadingTab = $('.pagelayer-heading-wrapper a.nav-tab-active');
			if (activeHeadingTab.length) {
				var heading_href = activeHeadingTab.attr('href'); // e.g. "#tab_body"
				var active_panel = $(heading_href);
				var active_screen_tab = active_panel.find('.pagelayer-styles-screens a.nav-tab-active');
				if (active_screen_tab.length) {
					var screen_href = active_screen_tab.attr('href'); // e.g. "#tab_body_desktop"
					var new_hash = screen_href + '_tab';
					if (location.hash !== new_hash) {
						if (history.replaceState) {
							history.replaceState(null, null, new_hash);
						} else {
							location.hash = new_hash;
						}
					}
					return;
				}
				var new_hash = heading_href + '_desktop_tab';
				if (location.hash !== new_hash) {
					if (history.replaceState) {
						history.replaceState(null, null, new_hash);
					} else {
						location.hash = new_hash;
					}
				}
				return;
			}
		}
		
		var active_sidebar_tab = $('#pagelayer-sidebar-nav a.pagelayer-nav-item.pagelayer-nav-active');
		if (active_sidebar_tab.length) {
			var new_hash = '#' + active_sidebar_tab.data('tab') + '_tab';
			if (location.hash !== new_hash) {
				if (history.replaceState) {
					history.replaceState(null, null, new_hash);
				} else {
					location.hash = new_hash;
				}
			}
		}
	}

	function pl_activate_sidebar_tab(tab_id){

		// Hide all panels
		$('.pagelayer-tab-panel').removeClass('pagelayer-tab-active').hide();

		// Show target
		var $panel = $('#'+tab_id);
		if($panel.length){
			$panel.addClass('pagelayer-tab-active').show();
		}

		// Update sidebar active state
		$('#pagelayer-sidebar-nav a.pagelayer-nav-item').removeClass('pagelayer-nav-active');
		$('#pagelayer-sidebar-nav a[data-tab="'+tab_id+'"]').addClass('pagelayer-nav-active');

		if (typeof pagelayer_admin_data !== 'undefined' && parseInt(pagelayer_admin_data.is_sitepad) === 1) {
			if (tab_id === 'export_theme') {
				$('#pagelayer-admin-wrap').addClass('pl-no-sidebar');
			} else {
				$('#pagelayer-admin-wrap').removeClass('pl-no-sidebar');
			}
		}

		// Update URL hash without scrolling
		if (!window.pl_block_hash_update) {
			if (tab_id === 'headings') {
				pl_update_hash();
			} else {
				if(history.replaceState){
					history.replaceState(null, null, '#'+tab_id+'_tab');
				}else{
					location.hash = '#'+tab_id+'_tab';
				}
			}
		}
	}

	// Sidebar nav click
	$('#pagelayer-sidebar-nav').on('click', 'a.pagelayer-nav-item', function(e){
		var tab_id = $(this).data('tab');
		if(tab_id && $('#'+tab_id).length > 0){
			e.preventDefault();
			pl_activate_sidebar_tab(tab_id);
		}
	});

	// Init: check hash first and handle sub-tabs + screen tabs
	var raw_hash = location.hash;
	if (raw_hash) {
		var match = raw_hash.match(/^#(tab_[a-zA-Z0-9\-]+)_(desktop|tablet|mobile)_tab$/);
		var match_old = raw_hash.match(/^#(tab_[a-zA-Z0-9\-]+)_tab$/);
		
		if (match || match_old) {
			var heading_id = match ? match[1] : match_old[1];
			var screen = match ? match[2] : 'desktop';
			
			pl_activate_sidebar_tab('headings');
			
			var $headingTab = $('.pagelayer-heading-wrapper a[href="#' + heading_id + '"]');
			if ($headingTab.length) {
				window.pl_block_hash_update = true;
				$headingTab.click();
				
				var $screenTab = $('#' + heading_id).find('.pagelayer-styles-screen-tab[href="#' + heading_id + '_' + screen + '"]');
				if ($screenTab.length) {
					$screenTab.click();
				}
				window.pl_block_hash_update = false;
				pl_update_hash();
			}
		} else {
			var outer_tab = raw_hash.replace('#','').replace('_tab','');
			if(outer_tab && $('#'+outer_tab).length && $('#pagelayer-sidebar-nav a[data-tab="'+outer_tab+'"]').length){
				pl_activate_sidebar_tab(outer_tab);
			} else {
				var $firstNav = $('#pagelayer-sidebar-nav a.pagelayer-nav-item').first();
				if($firstNav.length){
					var first_tab_id = $firstNav.data('tab');
					if(first_tab_id && $('#'+first_tab_id).length > 0){
						pl_activate_sidebar_tab(first_tab_id);
					}
				}
			}
		}
	} else {
		var $firstNav = $('#pagelayer-sidebar-nav a.pagelayer-nav-item').first();
		if($firstNav.length){
			var first_tab_id = $firstNav.data('tab');
			if(first_tab_id && $('#'+first_tab_id).length > 0){
				pl_activate_sidebar_tab(first_tab_id);
			}
		}
	}

	/* ======================================================
	   2. LEGACY: nav-tab-wrapper tabs (website settings, etc.)
	   ====================================================== */
	function pl_admin_tabs(){
		$('.nav-tab-wrapper a').not('.pagelayer-nav-item').click(function(){

			var tEle = $(this);
			var sel = tEle.attr('tab-class') || 'pagelayer-tab-panel';

			// Limit effect to container
			var context = tEle.closest('.nav-tab-wrapper').parent().parent();
			context.find('.nav-tab-wrapper a').removeClass('nav-tab-active');
			tEle.addClass('nav-tab-active');
			context.find('.'+sel).hide().removeClass('pagelayer-tab-active');
			context.find(tEle.attr('href')).show().addClass('pagelayer-tab-active');
			location.hash = tEle.attr('href')+'_tab';

			return false;
		});

		// Activate first or hash-based tab in legacy wrappers
		$('.nav-tab-wrapper.pagelayer-wrapper').each(function(){
			var jEle = $(this);
			// Skip sidebar nav (already handled above)
			if(jEle.closest('#pagelayer-sidebar-nav').length) return;

			// If modern sidebar nav is present, do not click legacy tabs automatically
			if($('#pagelayer-sidebar-nav').length > 0) return;

			var hash = location.hash.slice(1, -4);
			if(hash){
				var active_tab_ele = jEle.find('[href="#'+hash+'"]');
				if(active_tab_ele.length > 0){
					active_tab_ele.click();
					return;
				}
			}
			jEle.find('a').not('.pagelayer-nav-item').first().click();
		});
	}

	/* ======================================================
	   3. Accordion for FAQ panel
	   ====================================================== */
	function pl_admin_accordion(){
		$('.pagelayer-acc-wrapper .pagelayer-acc-tab').click(function(){
			var tEle = $(this);
			if(tEle.hasClass('nav-tab-active')){
				tEle.toggleClass('nav-tab-active').next('.pagelayer-acc-panel').toggle();
			}else{
				var context = tEle.closest('.pagelayer-acc-wrapper');
				context.find('.pagelayer-acc-tab').removeClass('nav-tab-active');
				context.find('.pagelayer-acc-panel').hide();
				tEle.addClass('nav-tab-active');
				tEle.next('.pagelayer-acc-panel').show();
			}
		});

		$('.pagelayer-acc-wrapper').each(function(){
			var jEle = $(this);
			var active_acc_ele = jEle.find('.nav-tab-active');
			if(active_acc_ele.length > 0){
				active_acc_ele.click();
			}else{
				jEle.find('.pagelayer-acc-tab').first().click();
			}
		});
	}

	/* ======================================================
	   4. Heading tabs (website settings sub-tabs)
	   ====================================================== */
	$('.pagelayer-heading-tab').click(function(e){
		e.stopImmediatePropagation();
		var tEle = $(this);
		var sel = tEle.attr('tab-class') || 'pagelayer-heading-tab-panel';
		var context = tEle.closest('#headings').length ? tEle.closest('#headings') : tEle.closest('.nav-tab-wrapper').parent();
		tEle.closest('.nav-tab-wrapper').find('a').removeClass('nav-tab-active');
		tEle.addClass('nav-tab-active');
		context.find('.'+sel).hide();
		var $targetPanel = context.find(tEle.attr('href'));
		$targetPanel.show();
		
		// Keep the currently active screen tab (Desktop/Tablet/Mobile) when switching headings, or default to desktop
		var current_active_screen_name = $('.pagelayer-styles-screens a.nav-tab-active').first().text().trim().toLowerCase() || 'desktop';
		var $screenTab = $targetPanel.find('.pagelayer-styles-screen-tab').filter(function() {
			return $(this).text().trim().toLowerCase() === current_active_screen_name;
		});
		if ($screenTab.length) {
			$screenTab.click();
		} else {
			$targetPanel.find('.pagelayer-styles-screen-tab').first().click();
		}
		
		pl_update_hash();
		return false;
	});

	var isTabHash = location.hash && location.hash.indexOf('#tab_') === 0;

	if (!isTabHash) {
		$('.pagelayer-heading-wrapper').each(function(){
			$(this).find('a').first().click();
		});
	}

	/* ======================================================
	   5. Screen sub-tabs (desktop/tablet/mobile) in website settings
	   ====================================================== */
	$('.pagelayer-styles-screen-tab').click(function(e){
		e.stopImmediatePropagation();
		var tEle = $(this);
		var sel = tEle.attr('tab-class') || 'pagelayer-styles-screen-panel';
		var context = tEle.closest('.pagelayer-heading-tab-panel').length ? tEle.closest('.pagelayer-heading-tab-panel') : tEle.closest('.nav-tab-wrapper').parent();
		tEle.closest('.nav-tab-wrapper').find('a').removeClass('nav-tab-active');
		tEle.addClass('nav-tab-active');
		context.find('.'+sel).hide();
		context.find(tEle.attr('href')).show();
		pl_update_hash();
		return false;
	});

	if (!isTabHash) {
		$('.pagelayer-styles-screens').each(function(){
			$(this).find('a').first().click();
		});
	}

	// Run legacy and accordion
	pl_admin_tabs();
	pl_admin_accordion();

	/* ======================================================
	   6. REDESIGN: Theme Templates & Custom Fonts CPT page edit.php
	   ====================================================== */
	var is_templates_page = $('body').hasClass('post-type-pagelayer-template') && $('body').hasClass('edit-php');
	var is_fonts_page = $('body').hasClass('post-type-pagelayer-fonts') && $('body').hasClass('edit-php');

	if((is_templates_page || is_fonts_page) && typeof pagelayer_admin_data !== 'undefined'){
		
		var logo_url = pagelayer_admin_data.logo;
		var version = pagelayer_admin_data.version;
		
		// Build the templates sub-navigation list from the existing tabs wrapper
		var templates_nav_html = '';
		var $tabs = $('#pagelayer-template-tabs-wrapper');
		
		if($tabs.length){
			$tabs.find('a.nav-tab').each(function(){
				var $tab = $(this);
				var label = $tab.text().trim();
				var href = $tab.attr('href');
				var isActive = $tab.hasClass('nav-tab-active');
				
				// Determine icon based on label text
				var icon = 'dashicons-welcome-widgets-menus';
				var lower_label = label.toLowerCase();
				if(lower_label.indexOf('all') !== -1) {
					icon = 'dashicons-admin-home';
				} else if(lower_label.indexOf('header') !== -1) {
					icon = 'dashicons-editor-insertmore';
				} else if(lower_label.indexOf('footer') !== -1) {
					icon = 'dashicons-editor-break';
				} else if(lower_label.indexOf('single') !== -1) {
					icon = 'dashicons-admin-page';
				} else if(lower_label.indexOf('archive') !== -1) {
					icon = 'dashicons-database';
				} else if(lower_label.indexOf('popup') !== -1) {
					icon = 'dashicons-external';
				} else if(lower_label.indexOf('global section') !== -1) {
					icon = 'dashicons-admin-site-alt3';
				} else if(lower_label.indexOf('global widget') !== -1) {
					icon = 'dashicons-screenoptions';
				} else if(lower_label.indexOf('section') !== -1) {
					icon = 'dashicons-layout';
				}
				
				var active_class = isActive ? ' pagelayer-nav-active' : '';
				templates_nav_html += '<a href="' + href + '" class="pagelayer-nav-item' + active_class + '">';
				templates_nav_html += '<span class="pl-nav-icon dashicons ' + icon + '"></span>' + label;
				templates_nav_html += '</a>';
			});
		} else {
			if (is_fonts_page) {
				// Build the entire unified navigation sidebar
				var isPremium = pagelayer_admin_data.is_premium;
				var admin_url = pagelayer_admin_data.admin_url;
				
				var items = [
					{ label: 'Add New Template', icon: 'dashicons-plus-alt', url: admin_url + 'admin.php?page=pagelayer_tools#add_new_template_tab', active: false },
					{ label: 'Export Theme', icon: 'dashicons-upload', url: admin_url + 'admin.php?page=pagelayer_tools#export_theme_tab', active: false },
					{ label: 'Import Theme', icon: 'dashicons-download', url: admin_url + 'admin.php?page=pagelayer_tools#import_theme_tab', active: false },
					{ label: 'Custom Fonts', icon: 'dashicons-editor-bold', url: admin_url + 'edit.php?post_type=pagelayer-fonts', active: true }
				];

				$.each(items, function(i, item) {
					var active_class = item.active ? ' pagelayer-nav-active' : '';
					templates_nav_html += '<a href="' + item.url + '" class="pagelayer-nav-item' + active_class + '">';
					templates_nav_html += '<span class="pl-nav-icon dashicons ' + item.icon + '"></span>' + item.label;
					templates_nav_html += '</a>';
				});
			} else {
				// Fallback: If no CPT sub-tabs exist, show active post type link
				var label = 'Theme Templates';
				var icon = 'dashicons-welcome-widgets-menus';
				var href = window.location.href;
				templates_nav_html += '<a href="' + href + '" class="pagelayer-nav-item pagelayer-nav-active">';
				templates_nav_html += '<span class="pl-nav-icon dashicons ' + icon + '"></span>' + label;
				templates_nav_html += '</a>';
			}
		}

		// Build Sidebar
		var sidebar_html = '<div id="pagelayer-sidebar">';
		if (typeof pagelayer_admin_data !== 'undefined' && parseInt(pagelayer_admin_data.is_sitepad) !== 1) {
			sidebar_html += '<div id="pagelayer-sidebar-logo">' +
				'<img src="' + logo_url + '" alt="Pagelayer Logo">' +
				'<div class="pl-logo-details">' +
					'<span class="pl-logo-text">pagelayer</span>' +
					'<span class="pl-version">v' + version + '</span>' +
				'</div>' +
			'</div>';
		}
		sidebar_html += '<nav id="pagelayer-sidebar-nav">' +
				templates_nav_html +
			'</nav>' +
		'</div>';

		// Extract original elements
		var title_text = $('.wp-heading-inline').text() || (is_fonts_page ? 'Custom Fonts' : 'Theme Templates');
		
		// Build actions
		var actions_html = '';
		var $addNewBtn = $('.page-title-action');
		var $exportBtn = $('.pagelayer-temp-export-but');
		
		if($addNewBtn.length){
			actions_html += $addNewBtn[0].outerHTML;
		}
		if($exportBtn.length){
			actions_html += $exportBtn[0].outerHTML;
		}
		
		// Build top header
		var top_header_html = '<div id="pagelayer-top-header">' +
			'<h1>' + title_text + '</h1>' +
			'<div class="pagelayer-header-actions">' +
				actions_html +
			'</div>' +
		'</div>';

		// Get the original wrap element
		var $wrap = $('#wpbody-content .wrap').first();
		
		if($wrap.length){
			// Create the modern layout structures
			var $adminWrap = $('<div id="pagelayer-admin-wrap"></div>');
			var $main_content = $('<div id="pagelayer-main-content"></div>');
			var $body_row = $('<div id="pagelayer-body-row"></div>');
			var $settings_content = $('<div id="pagelayer-settings-content"></div>');
			
			// Assemble the new structures
			$main_content.append(top_header_html);
			$body_row.append($settings_content);
			$main_content.append($body_row);
			if (!(is_fonts_page && typeof pagelayer_admin_data !== 'undefined' && parseInt(pagelayer_admin_data.is_sitepad) === 1)) {
				$adminWrap.append(sidebar_html);
			} else {
				$adminWrap.addClass('pl-no-sidebar');
			}
			$adminWrap.append($main_content);
			
			// Prepend our admin wrapper directly into #wpbody-content
			$('#wpbody-content').prepend($adminWrap);
			
			// Move the entire .wrap inside the settings content!
			$settings_content.append($wrap);
			
			// Hide the duplicate/original title and actions inside .wrap
			$wrap.find('.wp-heading-inline, .page-title-action, .pagelayer-temp-export-but, hr-wp-header-end').hide();
			$wrap.find('> h1, > hr').hide();
			
			// Hide the legacy tabs wrapper since we have it in the sidebar
			if($tabs.length){
				$tabs.hide();
			}
		}
	}

	// Recommended plugins install/activate handler (AJAX)
	$(document).on('click', '.pagelayer-plugin-install-btn, .pagelayer-plugin-activate-btn', function(e){
		e.preventDefault();
		var $btn = $(this);
		var slug = $btn.data('slug');
		var is_install = $btn.hasClass('pagelayer-plugin-install-btn');
		var originalText = $btn.text();
		
		$btn.text('Processing...').prop('disabled', true);
		
		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: is_install ? 'pagelayer_install_plugin' : 'pagelayer_activate_plugin',
				security: typeof pagelayer_recommended_nonce !== 'undefined' ? pagelayer_recommended_nonce : '',
				plugin: slug,
			},
			dataType: 'json',
			success: function(data){
				if(data.success){
					$btn.replaceWith('<span class="pl-plugin-badge pl-plugin-success">Active</span>');
				}else{
					$btn.text(originalText).prop('disabled', false);
					alert(data.data && data.data.message ? data.data.message : 'Action failed. Please try again.');
				}
			},
			error: function(){
				$btn.text(originalText).prop('disabled', false);
				alert('An error occurred. Please try again.');
			},
		});
	});

	// Add Back to List button on Add New / Edit pages for templates & fonts
	var is_post_new = $('body').hasClass('post-new-php');
	var is_post_edit = $('body').hasClass('post-php');
	
	if((is_post_new || is_post_edit) && (typeof typenow !== 'undefined')){
		if(typenow === 'pagelayer-fonts' || typenow === 'pagelayer-template'){
			var list_url = 'edit.php?post_type=' + typenow;
			var label = 'Back to Settings';
			
			var back_btn_html = '<a href="' + list_url + '" class="pl-back-to-list-btn">' +
				'<span class="dashicons dashicons-arrow-left-alt2"></span> ' + label +
			'</a>';
			
			// Find the heading or wrap and prepend it
			var $heading = $('.wp-heading-inline').first();
			if($heading.length){
				$heading.before(back_btn_html);
			} else {
				var $wrapHeading = $('.wrap h1').first();
				if($wrapHeading.length){
					$wrapHeading.before(back_btn_html);
				}
			}
		}
	}

	// SitePad check on document ready to hide sidebar for templates wizard and tools pages
	if (typeof pagelayer_admin_data !== 'undefined' && parseInt(pagelayer_admin_data.is_sitepad) === 1) {
		if ($('body').hasClass('pagelayer_page_pagelayer_template_wizard') || $('body').hasClass('pagelayer_page_pagelayer_template_export')) {
			$('#pagelayer-admin-wrap').addClass('pl-no-sidebar');
		}
	}

});