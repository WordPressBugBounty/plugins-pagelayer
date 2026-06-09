<?php

//////////////////////////////////////////////////////////////
//===========================================================
// settings.php
//===========================================================
// PAGELAYER
// Inspired by the DESIRE to be the BEST OF ALL
// ----------------------------------------------------------
// Started by: Pulkit Gupta
// Date:	   23rd Jan 2017
// Time:	   23:00 hrs
// Site:	   http://pagelayer.com/wordpress (PAGELAYER)
// ----------------------------------------------------------
// Please Read the Terms of use at http://pagelayer.com/tos
// ----------------------------------------------------------
//===========================================================
// (c)Pagelayer Team
//===========================================================
//////////////////////////////////////////////////////////////

// Are we being accessed directly ?
if(!defined('PAGELAYER_VERSION')) {
	exit('Hacking Attempt !');
}

// The Pagelayer Settings Header
function pagelayer_page_header($title = 'Pagelayer Editor', $no_sidebar = 0){

	global $pagelayer, $pagelayer_header_printed;

	if (!empty($pagelayer_header_printed)) {
		return;
	}
	$pagelayer_header_printed = true;
	
	// Determine active nav based on current page
	$current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

	if (defined('SITEPAD')) {
		if ($current_page === 'pagelayer_template_wizard' || $current_page === 'pagelayer_template_export') {
			$no_sidebar = 1;
		}
	}

	if ($current_page === 'pagelayer_website_settings'){
		$nav_items = array(
			'headings' => array( 'label' => __pl('elem_styles'), 'icon' => 'dashicons-editor-paragraph', 'hash' => admin_url('admin.php?page=pagelayer_website_settings#headings') ),
			'website_container' => array( 'label' => __pl('container'), 'icon' => 'dashicons-editor-table', 'hash' => admin_url('admin.php?page=pagelayer_website_settings#website_container') ),
			'hf' => array( 'label' => __pl('hf'), 'icon' => 'dashicons-editor-insertmore', 'hash' => admin_url('admin.php?page=pagelayer_website_settings#hf') ),
		);
	} elseif ($current_page === 'pagelayer_template_export' || $current_page === 'pagelayer_import' || $current_page === 'pagelayer_template_wizard' || $current_page === 'pagelayer_tools') {
		$nav_items = array(
			'add_new_template' => array( 'label' => __('Add New Template'), 'icon' => 'dashicons-plus-alt', 'hash' => admin_url('admin.php?page=pagelayer_tools#add_new_template_tab') ),
			'export_theme' => array( 'label' => __('Export Theme'), 'icon' => 'dashicons-upload', 'hash' => admin_url('admin.php?page=pagelayer_tools#export_theme_tab') ),
			'import_theme' => array( 'label' => __('Import Theme'), 'icon' => 'dashicons-download', 'hash' => admin_url('admin.php?page=pagelayer_tools#import_theme_tab') ),
			'custom_fonts' => array( 'label' => __('Custom Fonts'), 'icon' => 'dashicons-editor-bold', 'hash' => (defined('PAGELAYER_PREMIUM') ? admin_url('edit.php?post_type=pagelayer-fonts') : admin_url('admin.php?page=pagelayer_tools#custom_fonts_tab')) ),
		);
	} elseif ($current_page === 'pagelayer_license' || $current_page === 'pagelayer_getting_started') {
		$nav_items = array(
			'settings' => array( 'label' => __('Settings'), 'icon' => 'dashicons-admin-settings', 'hash' => admin_url('admin.php?page=pagelayer') ),
			'website_settings' => array( 'label' => __('Website Settings'), 'icon' => 'dashicons-editor-table', 'hash' => admin_url('admin.php?page=pagelayer_website_settings') ),
			'theme_templates' => array( 'label' => __('Theme Templates'), 'icon' => 'dashicons-welcome-widgets-menus', 'hash' => admin_url('edit.php?post_type=pagelayer-template') ),
			'getting_started' => array( 'label' => __('Getting Started'), 'icon' => 'dashicons-controls-play', 'hash' => admin_url('admin.php?page=pagelayer_getting_started') ),
		);

		if(defined('PAGELAYER_PREMIUM')){
			$nav_items['license'] = array( 'label' => __('License'), 'icon' => 'dashicons-admin-network', 'hash' => admin_url('admin.php?page=pagelayer_license') );
		}
	} else {
		$nav_items = array(
			'general' => array( 'label' => __('General'), 'icon' => 'dashicons-admin-settings', 'hash' => admin_url('admin.php?page=pagelayer#general') ),
			'icons' => array( 'label' => __('Enable Icons'), 'icon' => 'dashicons-star-filled', 'hash' => admin_url('admin.php?page=pagelayer#icons') ),
			'social' => array( 'label' => __('Information'), 'icon' => 'dashicons-info', 'hash' => admin_url('admin.php?page=pagelayer#social') ),
			'integration' => array( 'label' => __('Integrations'), 'icon' => 'dashicons-networking', 'hash' => admin_url('admin.php?page=pagelayer#integration') ),
		);

		if(defined('PAGELAYER_PREMIUM')){
			$nav_items['contactform'] = array( 'label' => __('Contact Form'), 'icon' => 'dashicons-email-alt', 'hash' => admin_url('admin.php?page=pagelayer#contactform') );
			$nav_items['captcha'] = array( 'label' => __('Google Captcha'), 'icon' => 'dashicons-shield', 'hash' => admin_url('admin.php?page=pagelayer#captcha') );
		}

		if(!defined('SITEPAD')){
			$nav_items['support'] = array( 'label' => __('Support'), 'icon' => 'dashicons-sos', 'hash' => admin_url('admin.php?page=pagelayer#support') );
			$nav_items['faq'] = array( 'label' => __('FAQ'), 'icon' => 'dashicons-editor-help', 'hash' => admin_url('admin.php?page=pagelayer#faq') );
		}
	}

	// Version
	$version = defined('PAGELAYER_VERSION') ? PAGELAYER_VERSION : '';

	echo '<div id="pagelayer-admin-wrap"' . (!empty($no_sidebar) ? ' class="pl-no-sidebar"' : '') . '>';

	// ---- Left Sidebar ----
	if (empty($no_sidebar)) {
		echo '<div id="pagelayer-sidebar">';
		if(!defined('SITEPAD')){
			echo '<div id="pagelayer-sidebar-logo">
				<img src="'.esc_url(PAGELAYER_URL . '/images/pagelayer-logo-40.png').'" alt="Pagelayer Logo">
				<div class="pl-logo-details">
					<span class="pl-logo-text">pagelayer</span>
					<span class="pl-version">v' . esc_html( $version ) . '</span>
				</div>
			</div>';
		}

		echo '<nav id="pagelayer-sidebar-nav">';
		$first_key = key($nav_items); foreach( $nav_items as $key => $item ){
			$is_active = false;
			if ($current_page === 'pagelayer_website_settings' || $current_page === 'pagelayer') {
				$is_active = ($key === $first_key);
			} else {
				if ($current_page === 'pagelayer_template_export' && $key === 'export_theme') {
					$is_active = true;
				} elseif ($current_page === 'pagelayer_import' && $key === 'import_theme') {
					$is_active = true;
				} elseif ($current_page === 'pagelayer_license' && $key === 'license') {
					$is_active = true;
				} elseif (($current_page === 'pagelayer_template_wizard' || $current_page === 'pagelayer_tools') && $key === 'add_new_template') {
					$is_active = true;
				} elseif ($current_page === 'pagelayer_getting_started' && $key === 'getting_started') {
					$is_active = true;
				}
			}
			echo '<a href="' . esc_attr( $item['hash'] ) . '" class="pagelayer-nav-item ' . ($is_active ? 'pagelayer-nav-active' : '') . '" data-tab="' . esc_attr( $key ) . '">
				<span class="pl-nav-icon dashicons ' . esc_attr( $item['icon'] ) . '"></span>'.esc_html( $item['label'] ).'
			</a>';
		}
		echo '</nav>';

		echo '</div>'; // end #pagelayer-sidebar
	}

	// ---- Main Content ----
	echo '<div id="pagelayer-main-content">';

	// Top Header
	echo '<div id="pagelayer-top-header">
		<h1>'.esc_html($title).'</h1>
	<div class="pagelayer-header-actions">';
	if(!defined('SITEPAD')){
		echo '<a href="' . esc_url( PAGELAYER_DOCS ) . '" target="_blank" class="pl-help-btn">
			<span class="dashicons dashicons-editor-help"></span> ' . __('Help').'
		</a>';
	}
	// Save button is rendered by each page form, but we add a placeholder trigger
	echo '</div>
	</div>'; // end #pagelayer-top-header

	// Body row wrapper
	echo '<div id="pagelayer-body-row">
	<div id="pagelayer-settings-content">';
}

// Helper to determine the activation status of recommended plugins
function pagelayer_get_recommended_plugin_status($slug){
	if(!function_exists('get_plugins')){
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	
	$all_plugins = get_plugins();
	
	foreach($all_plugins as $plugin_path => $plugin_data){
		if(strpos($plugin_path, $slug . '/') === 0){
			if(is_plugin_active($plugin_path)){
				return 'active';
			}
			return 'installed';
		}
	}
	
	return 'not_installed';
}

// Render the Recommended Plugins sidebar module (similar to cookieadmin)
function pagelayer_render_recommended_plugins(){
	if(defined('SITEPAD')){
		return;
	}
	$plugins = array(
		'loginizer' => array(
			'name' => 'Loginizer',
			'desc' => __('Brute Force Protection & Login Security', 'pagelayer'),
			'icon' => 'dashicons-shield',
			'wporg_url' => 'https://wordpress.org/plugins/loginizer/',
		),
		'gosmtp' => array(
			'name' => 'GoSMTP',
			'desc' => __('SMTP Mailer for WordPress', 'pagelayer'),
			'icon' => 'dashicons-email-alt',
			'wporg_url' => 'https://wordpress.org/plugins/gosmtp/',
		),
		'backuply' => array(
			'name' => 'Backuply',
			'desc' => __('Backup & Restore made easy', 'pagelayer'),
			'icon' => 'dashicons-backup',
			'wporg_url' => 'https://wordpress.org/plugins/backuply/',
		),
		'siteseo' => array(
			'name' => 'SiteSEO',
			'desc' => __('SEO Optimization for WordPress', 'pagelayer'),
			'icon' => 'dashicons-search',
			'wporg_url' => 'https://wordpress.org/plugins/siteseo/',
		),
	);
	
	echo '<script>
	var pagelayer_recommended_nonce = "' . esc_js(wp_create_nonce('pagelayer_recommended_plugins')) . '";
	</script>';
	
	echo '<div class="pagelayer-settings-card pagelayer-recommended-plugins">
		<div class="pagelayer-settings-card-header">
			<h3><span class="dashicons dashicons-admin-plugins" style="margin-right: 6px; vertical-align: text-bottom; color: #4f46e5;"></span>'.esc_html__('Recommended Plugins', 'pagelayer').'</h3>
		</div>
		<div class="pagelayer-recommended-plugins-body">';
	
	foreach($plugins as $slug => $plugin){
		$status = pagelayer_get_recommended_plugin_status($slug);
		
		echo '<div class="pagelayer-recommended-plugin" data-slug="'.esc_attr($slug).'">
			<div class="pagelayer-recommended-plugin-info">
				<div class="pagelayer-recommended-plugin-icon"><span class="dashicons '.esc_attr($plugin['icon']).'"></span></div>
				<div class="pagelayer-recommended-plugin-details">
					<div class="pagelayer-recommended-plugin-name">'.esc_html($plugin['name']).'</div>
					<div class="pagelayer-recommended-plugin-desc">'.esc_html($plugin['desc']).'</div>
				</div>
			</div>
			<div class="pagelayer-recommended-plugin-action">
				<a href="' . esc_url($plugin['wporg_url']) . '" target="_blank" class="pagelayer-recommended-plugin-link">' . esc_html__('Learn more', 'pagelayer') . '</a>';

		if($status === 'active'){
			echo '<span class="pl-plugin-badge pl-plugin-success">'.esc_html__('Active', 'pagelayer').'</span>';
		}elseif($status === 'installed'){
			echo '<button type="button" class="pl-plugin-btn pl-plugin-btn-secondary pagelayer-plugin-activate-btn" data-slug="'.esc_attr($slug).'">'.esc_html__('Activate', 'pagelayer').'</button>';
		}else{
			echo '<button type="button" class="pl-plugin-btn pl-plugin-btn-primary pagelayer-plugin-install-btn" data-slug="'.esc_attr($slug).'">'.esc_html__('Install', 'pagelayer').'</button>';
		}
		
		echo '</div>
		</div>';
	}
	
	echo '</div>
	</div>';
}

// The Pagelayer Settings footer
function pagelayer_page_footer($no_twitter = 0){
	
	global $pagelayer_footer_printed;

	if (!empty($pagelayer_footer_printed)) {
		return;
	}
	$pagelayer_footer_printed = true;

	// Close #pagelayer-settings-content
	echo '</div>';
	
	$promos = apply_filters('pagelayer_right_bar_promos', false);
	
	// Force promos for Pagelayer settings page only to show recommended plugins
	$current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
	if($current_page === 'pagelayer'){
		$promos = true;
	}
	
	if(defined('SITEPAD')){
		$promos = false;
	}
	
	if($promos){
	
		echo '<div id="pagelayer-right-bar">';
		
		if($current_page === 'pagelayer'){
			pagelayer_render_recommended_plugins();
		}else{
		
			if(!defined('PAGELAYER_PREMIUM')){
				
				echo '
				<div class="postbox" style="min-width:0px !important;">
					<h2 class="hndle ui-sortable-handle">
						<span><a target="_blank" href="'.PAGELAYER_PRO_PRICE_URL.'"><img src="'.PAGELAYER_URL.'/images/pagelayer_product.png" width="100%" /></a></span>
					</h2>
					<div class="inside">
						<i>Upgrade to the premium version and get the following features </i>:<br>
						<ul class="pagelayer-right-ul">
							<li>60+ Premium Widgets</li>
							<li>16+ WooCommerce Widgets</li>
							<li>400+ Premium Sections</li>
							<li>Theme Builder</li>
							<li>WooCommerce Builder</li>
							<li>Theme Creator and Exporter</li>
							<li>Form Builder</li>
							<li>Popup Builder</li>
							<li>And many more ...</li>
						</ul>
						<center><a class="button button-primary" target="_blank" href="'.PAGELAYER_PRO_PRICE_URL.'">Upgrade</a></center>
					</div>
				</div>';
				
			}
			
			echo '
				<div class="postbox" style="min-width:0px !important;">
					<h2 class="hndle ui-sortable-handle">
						<span><a target="_blank" href="https://wpcentral.co/?from=pagelayer-plugin"><img src="'.PAGELAYER_URL.'/images/wpcentral_product.png" width="100%" /></a></span>
					</h2>
					<div class="inside">
						<i>Manage all your WordPress sites from <b>1 dashboard</b> </i>:<br>
						<ul class="pagelayer-right-ul">
							<li>1-click Admin Access</li>
							<li>Update WordPress</li>
							<li>Update Themes</li>
							<li>Update Plugins</li>
							<li>Backup your WordPress Site</li>
							<li>Plugins &amp; Theme Management</li>
							<li>Post Management</li>
							<li>And many more ...</li>
						</ul>
						<center><a class="button button-primary" target="_blank" href="https://wpcentral.co/?from=pagelayer-plugin">Visit wpCentral</a></center>
					</div>
				</div>
			';
		
		}
		
		echo '</div>'; // end #pagelayer-right-bar
	}
	
	// Close #pagelayer-body-row
	echo '</div>';
	
	// Close #pagelayer-main-content and #pagelayer-admin-wrap
	echo '</div></div>';

}

	

function pagelayer_settings_page(){

	$_REQUEST = wp_unslash($_REQUEST);
	$post_type = array();
	$exclude = [ 'attachment', 'pagelayer-template' ];
	$pt_objects = get_post_types(['public' => true,], 'objects');

	foreach ( $pt_objects as $pt_slug => $type ) {
		
		if ( in_array( $pt_slug, $exclude ) ) {
			continue;
		}
		
		$post_type[$pt_slug] = $type->labels->name;
	}

	$support_ept = get_option( 'pl_support_ept', ['post', 'page']);

	$option_name = 'pl_gen_setting';
	$new_value = '';
	
	// DO an admin referrer check
	if(!empty($_POST)){
		check_admin_referer('pagelayer-options');
	}
	
	// We do a $_POST check and hence we are checking the POST var here as well
	// Everywhere down as well, $_POST should be used to save data
	
	if(isset($_POST['pl_support_ept'])){

		$pl_support_ept = $_REQUEST['pl_support_ept'];
		
		foreach($pl_support_ept as $k => $v){
			if(empty($post_type[$v])){
				unset($pl_support_ept[$k]);
			}
		}
		
		// Update it
		update_option('pl_support_ept', $pl_support_ept );
		
		$support_ept = get_option( 'pl_support_ept');
		
		$done = 1;
	}
	
	if(isset($_POST['pagelayer_icons_set'])){
		$pagelayer_icons_set = $_REQUEST['pagelayer_icons_set'];
		
		// Update it
		update_option('pagelayer_icons_set', $pagelayer_icons_set);
		
		$done = 1;
	}
	
	$socials = ['pagelayer-facebook-url','pagelayer-twitter-url','pagelayer-instagram-url','pagelayer-linkedin-url','pagelayer-youtube-url','pagelayer-gplus-url','pagelayer-copyright','pagelayer-phone','pagelayer-address'];
	
	foreach( $socials as $social ){
		if(isset($_POST[$social])){
			$url = $_REQUEST[$social];
			update_option($social, $url);
			$done = 1;
		}
	}
	
	if(isset($_POST['pagelayer_cf_to_email'])){

		$to_email = $_REQUEST['pagelayer_cf_to_email'];
		
		update_option( 'pagelayer_cf_to_email', $to_email );
		
		$done = 1;
	}
	
	if(isset($_POST['pagelayer-gmaps-api-key'])){

		$maps_id = sanitize_text_field($_REQUEST['pagelayer-gmaps-api-key']);
			
		update_option( 'pagelayer-gmaps-api-key', $maps_id );
		
		$done = 1;
	}
		
	if(defined('PAGELAYER_PREMIUM')){
	
		if(isset($_POST['pagelayer_cf_subject'])){

			$subject = $_REQUEST['pagelayer_cf_subject'];
			
			update_option('pagelayer_cf_subject', $subject, 'no');
		
			$done = 1;
			
		}
		
		if(isset($_POST['pagelayer_cf_headers'])){

			$subject = $_REQUEST['pagelayer_cf_headers'];
			
			update_option('pagelayer_cf_headers', $subject, 'no');
		
			$done = 1;
			
		}
		
		if(isset($_POST['pagelayer_cf_from_email'])){

			$subject = $_REQUEST['pagelayer_cf_from_email'];
			
			update_option('pagelayer_cf_from_email', $subject, 'no');
		
			$done = 1;
			
		}
		
		if(isset($_POST['pagelayer_cf_success'])){

			$success = $_REQUEST['pagelayer_cf_success'];
			
			update_option( 'pagelayer_cf_success', $success, 'no');
		
			$done = 1;
			
		}
		
		if(isset($_POST['pagelayer_cf_failed'])){

			$failed = $_REQUEST['pagelayer_cf_failed'];
			
			update_option( 'pagelayer_cf_failed', $failed, 'no');
		
			$done = 1;
			
		}
		
		if(isset($_POST['pagelayer_recaptcha_failed'])){

			$failed = $_REQUEST['pagelayer_recaptcha_failed'];
			
			update_option( 'pagelayer_recaptcha_failed', $failed, 'no');
		
			$done = 1;
			
		}

		if(isset($_POST['pagelayer_recaptcha_version'])){

			$version = sanitize_text_field($_REQUEST['pagelayer_recaptcha_version']);
			
			update_option( 'pagelayer_recaptcha_version', $version );
		
			$done = 1;
			
		}
		
		if(isset($_POST['pagelayer_google_captcha'])){

			$captcha = sanitize_text_field($_REQUEST['pagelayer_google_captcha']);
			
			update_option( 'pagelayer_google_captcha', $captcha );
		
			$done = 1;
			
		}
		
		if(isset($_POST['pagelayer_google_captcha_secret'])){

			$captcha_secret = sanitize_text_field($_REQUEST['pagelayer_google_captcha_secret']);
			
			update_option( 'pagelayer_google_captcha_secret', $captcha_secret );
		
			$done = 1;
			
		}
		
		if(isset($_POST['pagelayer_google_captcha_lang'])){

			$captcha_secret = $_REQUEST['pagelayer_google_captcha_lang'];
			
			update_option( 'pagelayer_google_captcha_lang', $captcha_secret );
		
			$done = 1;
			
		}
		
		// Facebook APP ID
		if(isset($_POST['pagelayer-fbapp-id'])){
			$fb_app_id = sanitize_text_field($_REQUEST['pagelayer-fbapp-id']);

			
			if(preg_match('/\W/is', $fb_app_id)){
				$pl_error[] = 'The Facebook App ID is not correct';
			}else{
			
				// Save it
				update_option( 'pagelayer-fbapp-id', $fb_app_id );
			
				$done = 1;
			
			}
		}
	}

	// reCAPTCHA Langs
	$recap_lang[''] = 'Auto Detect';
	$recap_lang['ar'] = 'Arabic';
	$recap_lang['af'] = 'Afrikaans';
	$recap_lang['am'] = 'Amharic';
	$recap_lang['hy'] = 'Armenian';
	$recap_lang['az'] = 'Azerbaijani';
	$recap_lang['eu'] = 'Basque';
	$recap_lang['bn'] = 'Bengali';
	$recap_lang['bg'] = 'Bulgarian';
	$recap_lang['ca'] = 'Catalan';
	$recap_lang['zh-HK'] = 'Chinese (Hong Kong)';
	$recap_lang['zh-CN'] = 'Chinese (Simplified)';
	$recap_lang['zh-TW'] = 'Chinese (Traditional)';
	$recap_lang['hr'] = 'Croatian';
	$recap_lang['cs'] = 'Czech';
	$recap_lang['da'] = 'Danish';
	$recap_lang['nl'] = 'Dutch';
	$recap_lang['en-GB'] = 'English (UK)';
	$recap_lang['en'] = 'English (US)';
	$recap_lang['et'] = 'Estonian';
	$recap_lang['fil'] = 'Filipino';
	$recap_lang['fi'] = 'Finnish';
	$recap_lang['fr'] = 'French';
	$recap_lang['fr-CA'] = 'French (Canadian)';
	$recap_lang['gl'] = 'Galician';
	$recap_lang['ka'] = 'Georgian';
	$recap_lang['de'] = 'German';
	$recap_lang['de-AT'] = 'German (Austria)';
	$recap_lang['de-CH'] = 'German (Switzerland)';
	$recap_lang['el'] = 'Greek';
	$recap_lang['gu'] = 'Gujarati';
	$recap_lang['iw'] = 'Hebrew';
	$recap_lang['hi'] = 'Hindi';
	$recap_lang['hu'] = 'Hungarain';
	$recap_lang['is'] = 'Icelandic';
	$recap_lang['id'] = 'Indonesian';
	$recap_lang['it'] = 'Italian';
	$recap_lang['ja'] = 'Japanese';
	$recap_lang['kn'] = 'Kannada';
	$recap_lang['ko'] = 'Korean';
	$recap_lang['lo'] = 'Laothian';
	$recap_lang['lv'] = 'Latvian';
	$recap_lang['lt'] = 'Lithuanian';
	$recap_lang['ms'] = 'Malay';
	$recap_lang['ml'] = 'Malayalam';
	$recap_lang['mr'] = 'Marathi';
	$recap_lang['mn'] = 'Mongolian';
	$recap_lang['no'] = 'Norwegian';
	$recap_lang['fa'] = 'Persian';
	$recap_lang['pl'] = 'Polish';
	$recap_lang['pt'] = 'Portuguese';
	$recap_lang['pt-BR'] = 'Portuguese (Brazil)';
	$recap_lang['pt-PT'] = 'Portuguese (Portugal)';
	$recap_lang['ro'] = 'Romanian';
	$recap_lang['ru'] = 'Russian';
	$recap_lang['sr'] = 'Serbian';
	$recap_lang['si'] = 'Sinhalese';
	$recap_lang['sk'] = 'Slovak';
	$recap_lang['sl'] = 'Slovenian';
	$recap_lang['es'] = 'Spanish';
	$recap_lang['es-419'] = 'Spanish (Latin America)';
	$recap_lang['sw'] = 'Swahili';
	$recap_lang['sv'] = 'Swedish';
	$recap_lang['ta'] = 'Tamil';
	$recap_lang['te'] = 'Telugu';
	$recap_lang['th'] = 'Thai';
	$recap_lang['tr'] = 'Turkish';
	$recap_lang['uk'] = 'Ukrainian';
	$recap_lang['ur'] = 'Urdu';
	$recap_lang['vi'] = 'Vietnamese';
	$recap_lang['zu'] = 'Zulu';
	
	pagelayer_page_header('Settings');

	// Media Replace.
	if(isset($_POST['submit']) || isset($_POST['pagelayer_disable_media_replace'])){

		$disable_media = empty($_REQUEST['pagelayer_disable_media_replace']) ? 0 : 1;
		
		update_option( 'pagelayer_disable_media_replace', $disable_media );
	
		$done = 1;
		
	}
	
	// Media Replace
	$media_replace = get_option( 'pagelayer_disable_media_replace');
	
	// Clone Templates.
	if(isset($_POST['submit']) || isset($_POST['pagelayer_disable_clone'])){

		$disable_clone = empty($_REQUEST['pagelayer_disable_clone']) ? 0 : 1;
		
		update_option( 'pagelayer_disable_clone', $disable_clone );
	
		$done = 1;
		
	}
	
	// Disable Clone
	$disable_clone = get_option('pagelayer_disable_clone');
		
	// Dark Mode
	if(isset($_POST['submit']) || isset($_POST['pagelayer_enable_dark_mode'])){

		$enable_dark_mode = empty($_REQUEST['pagelayer_enable_dark_mode']) ? 0 : 1;
		
		update_option( 'pagelayer_enable_dark_mode', $enable_dark_mode );
	
		$done = 1;
		
	}
	
	// Dark Mode
	$enable_dark_mode = get_option('pagelayer_enable_dark_mode');
	
	// Enable JS/CSS Giver 
	if(isset($_POST['submit']) || isset($_POST['pagelayer_enable_jscss_giver'])){
		$done = 1;
		$enable_jscss_giver = empty($_REQUEST['pagelayer_enable_jscss_giver']) ? -1 : 1;
		
		update_option( 'pagelayer_enable_giver', $enable_jscss_giver );
	}
	
	// Enable JS/CSS Giver 
	$enable_jscss_giver = get_option('pagelayer_enable_giver');
	
	if(defined('PAGELAYER_PREMIUM')){
		// Enable Google Font local giver 
		if(isset($_POST['submit']) || isset($_POST['pagelayer_local_gfont'])){
			$done = 1;
			$enable_gfont_downloader = empty($_REQUEST['pagelayer_local_gfont']) ? -1 : 1;
			
			update_option( 'pagelayer_local_gfont', $enable_gfont_downloader );
		}
		
		// Enable Google Font Downloader  
		$enable_gfont_downloader = get_option('pagelayer_local_gfont');
	}
	
	// User roles to allow saving js content
	if(isset($_POST['pagelayer_js_permission'])){	
		update_option( 'pagelayer_js_permission', array_filter($_POST['pagelayer_js_permission']) );
	}
		
	$pagelayer_js_permission = get_option('pagelayer_js_permission');
	$pagelayer_js_permission = empty($pagelayer_js_permission) ? array() : $pagelayer_js_permission;

	// Saved ?
	if(!empty($done)){
		echo '<div class="notice notice-success"><p>'. __('The settings were saved successfully', 'pagelayer'). '</p></div><br />';
	}

	// Any errors ?
	if(!empty($pl_error)){
		pagelayer_report_error($pl_error);echo '<br />';
	}
	
?>
	<form class="pagelayer-setting-form" method="post" action="" id="pagelayer-settings-main-form">
		<?php wp_nonce_field('pagelayer-options'); ?>

		<?php
		// Inject Save Settings button into header via inline script
		echo '<script>
		jQuery(function($){
			var btn = \'<input type="submit" name="submit" form="pagelayer-settings-main-form" class="pl-save-btn" value="' . esc_attr__('Save Settings') . '">\';
			$(".pagelayer-header-actions").append(btn);
		});
		</script>';
		?>

		<div class="tabs-wrapper">

			<!-- ===== GENERAL TAB ===== -->
			<div id="general" class="pagelayer-tab-panel pagelayer-tab-active">

				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('General Settings'); ?></h3>
						<span class="pl-badge"><?php _e('Core'); ?></span>
					</div>

					<?php if(!defined('SITEPAD')){ ?>
					<!-- Editor Enables On -->
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Editor Enables On'); ?></span>
							<p class="pagelayer-setting-row-desc"><?php _e('Choose post types where the editor should be active.'); ?></p>
						</div>
						<div class="pagelayer-setting-row-control">
							<div class="pl-posttype-toggles">
								<?php foreach($post_type as $type => $name){ ?>
								<label class="pl-posttype-toggle">
									<label class="pl-toggle">
										<input type="checkbox" name="pl_support_ept[]" value="<?php echo esc_attr($type); ?>" <?php echo in_array($type, $support_ept) ? 'checked' : ''; ?>>
										<span class="pl-toggle-slider"></span>
									</label>
									<span><?php echo esc_html($name); ?></span>
								</label>
								<?php } ?>
							</div>
						</div>
					</div>

					<!-- Media Replace -->
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Media Replace'); ?></span>
							<p class="pagelayer-setting-row-desc"><?php _e('Disable the automatic media replacement engine.'); ?></p>
						</div>
						<div class="pagelayer-setting-row-control">
							<label class="pl-toggle">
								<input type="checkbox" name="pagelayer_disable_media_replace" <?php echo (!empty($media_replace) ? 'checked' : ''); ?>>
								<span class="pl-toggle-slider"></span>
							</label>
						</div>
					</div>

					<!-- Disable Clone -->
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Disable Clone'); ?></span>
							<p class="pagelayer-setting-row-desc"><?php _e('Turn off the ability to duplicate pages or posts.'); ?></p>
						</div>
						<div class="pagelayer-setting-row-control">
							<label class="pl-toggle">
								<input type="checkbox" name="pagelayer_disable_clone" <?php echo (!empty($disable_clone) ? 'checked' : ''); ?>>
								<span class="pl-toggle-slider"></span>
							</label>
						</div>
					</div>
					<?php } ?>

					<!-- Dark Mode -->
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Dark Mode'); ?></span>
							<p class="pagelayer-setting-row-desc"><?php _e('Enable experimental dark mode for the editor interface.'); ?></p>
						</div>
						<div class="pagelayer-setting-row-control">
							<label class="pl-toggle">
								<input type="checkbox" name="pagelayer_enable_dark_mode" <?php echo (!empty($enable_dark_mode) ? 'checked' : ''); ?>>
								<span class="pl-toggle-slider"></span>
							</label>
						</div>
					</div>

					<!-- JS/CSS Giver -->
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Enable JS/CSS Giver'); ?></span>
							<p class="pagelayer-setting-row-desc"><?php _e('Allow direct injection of custom scripts and styles into templates.'); ?></p>
						</div>
						<div class="pagelayer-setting-row-control">
							<label class="pl-toggle">
								<input type="checkbox" name="pagelayer_enable_jscss_giver" <?php echo ((!empty($enable_jscss_giver) && $enable_jscss_giver == 1) ? 'checked' : ''); ?>>
								<span class="pl-toggle-slider"></span>
							</label>
						</div>
					</div>

					<?php if(defined('PAGELAYER_PREMIUM')){ ?>
					<!-- Local Google Fonts -->
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Local Google Fonts'); ?></span>
							<p class="pagelayer-setting-row-desc">
								<?php _e('Download Google Fonts to your server for GDPR compliance and faster loads.'); ?>
								<strong class="pl-recommend-note"><?php _e('Highly recommended for European servers.'); ?></strong>
							</p>
						</div>
						<div class="pagelayer-setting-row-control">
							<label class="pl-toggle">
								<input type="checkbox" name="pagelayer_local_gfont" <?php echo ((!empty($enable_gfont_downloader) && $enable_gfont_downloader == 1) ? 'checked' : ''); ?>>
								<span class="pl-toggle-slider"></span>
							</label>
						</div>
					</div>
					<?php } ?>

				</div><!-- end general-settings-card -->

				<!-- JS Content Permissions -->
				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('JS Content Permissions'); ?></h3>
					</div>
					<div class="pagelayer-setting-row" style="flex-direction:column; align-items:flex-start;">
						<p class="pagelayer-setting-row-desc" style="margin-bottom:14px;"><?php _e('Select user roles allowed to execute custom JS.'); ?> <strong style="color:#ef4444;"><?php _e('Security Note:'); ?></strong> <?php _e('Be cautious when granting this permission.'); ?></p>
						<div class="pl-checkbox-group">
							<?php
							$user_roles = wp_roles()->get_names();
							foreach ($user_roles as $role => $role_name) {
								$val = ($role == 'administrator') ? '' : $role;
								$label = ($role == 'administrator') ? __('Default') : $role_name;
								echo '<label class="pl-checkbox-item">';
								echo '<input type="checkbox" name="pagelayer_js_permission[]" value="' . esc_attr($val) . '" ' . (($role == 'administrator' || in_array($role, $pagelayer_js_permission)) ? 'checked' : '') . '>';
								echo '<label>' . esc_html($label) . '</label>';
								echo '</label>';
							}
							?>
						</div>
						<div class="pl-security-note" style="margin-top:14px;">
							<b><?php _e('Security Note:'); ?></b> <?php _e('For security reasons, we strongly advise against granting this permission to user roles other than administrators. Adding JavaScript content can lead to Cross Site Scripting and can introduce severe security vulnerabilities to your website, putting it at risk of attacks.'); ?>
						</div>
					</div>
				</div>

			</div><!-- end #general -->

			<!-- ===== ICONS TAB ===== -->
			<div class="pagelayer-tab-panel" id="icons">
				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('Icon Libraries'); ?></h3>
					</div>
					<?php
					$pagelayer_icons = get_option('pagelayer_icons_set');
					if(empty($pagelayer_icons)){ $pagelayer_icons = array(); }
					?>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Font Awesome 5'); ?></span>
							<p class="pagelayer-setting-row-desc"><?php _e('Enable Font Awesome 5 icon library for use in the editor.'); ?></p>
						</div>
						<div class="pagelayer-setting-row-control">
							<label class="pl-toggle">
								<input type="checkbox" name="pagelayer_icons_set[]" value="font-awesome5" <?php if(in_array('font-awesome5', $pagelayer_icons) || !get_option('pagelayer_icons_set')){ echo 'checked'; } ?>>
								<span class="pl-toggle-slider"></span>
							</label>
						</div>
					</div>
				</div>
			</div><!-- end #icons -->

			<!-- ===== INFORMATION TAB ===== -->
			<div class="pagelayer-tab-panel" id="social">
				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('Address and Phone Number'); ?></h3>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Address'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<textarea name="pagelayer-address" rows="3"><?php echo esc_html(pagelayer_get_option('pagelayer-address')); ?></textarea>
						</div>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Phone Number'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input type="tel" name="pagelayer-phone" value="<?php echo esc_html(pagelayer_get_option('pagelayer-phone')); ?>" />
						</div>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Contact Email'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<?php if(defined('PAGELAYER_PREMIUM')){
								echo '<p class="pagelayer-setting-row-desc">' . __('You can change your contact email<br> from the Contact Form Settings.') . '</p>';
							}else{ ?>
							<input name="pagelayer_cf_to_email" type="email" placeholder="email@domain.com" <?php if(get_option('pagelayer_cf_to_email')){ echo 'value="'.esc_html(get_option('pagelayer_cf_to_email')).'"'; } ?> />
							<?php } ?>
						</div>
					</div>
				</div>

				<?php if(defined('PAGELAYER_PREMIUM')){ ?>
				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('Copyright'); ?></h3>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Copyright Text'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<textarea name="pagelayer-copyright" rows="3"><?php echo esc_html(pagelayer_get_option('pagelayer-copyright')); ?></textarea>
						</div>
					</div>
				</div>

				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('Social Profile URLs'); ?></h3>
					</div>
					<?php $social_links = [
						'pagelayer-facebook-url'  => 'Facebook',
						'pagelayer-twitter-url'   => 'Twitter',
						'pagelayer-instagram-url' => 'Instagram',
						'pagelayer-linkedin-url'  => 'LinkedIn',
						'pagelayer-youtube-url'   => 'YouTube',
						'pagelayer-gplus-url'     => 'Google+',
					];
					foreach($social_links as $key => $label){ ?>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php echo esc_html($label); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input type="text" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_url(get_option($key)); ?>" />
						</div>
					</div>
					<?php } ?>
				</div>
				<?php } ?>

			</div><!-- end #social -->

			<!-- ===== INTEGRATIONS TAB ===== -->
			<div class="pagelayer-tab-panel" id="integration">

				<?php if(defined('PAGELAYER_PREMIUM')){ ?>
				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('Facebook SDK Details'); ?></h3>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('App ID'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input type="text" name="pagelayer-fbapp-id" class="pagelayer-app-id" <?php if(get_option('pagelayer-fbapp-id')){ echo 'value="'.esc_html(get_option('pagelayer-fbapp-id')).'"'; } ?> />
						</div>
					</div>
				</div>
				<?php } ?>

				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('Google Maps API Key'); ?></h3>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('API Key'); ?></span>
							<p class="pagelayer-setting-row-desc"><?php _e('Insert your Google Maps API key.'); ?> <a href="https://pagelayer.com/docs/pagelayer-widgets/google-maps/" target="_blank"><strong><?php _e('Get help here'); ?></strong></a>.</p>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input type="text" name="pagelayer-gmaps-api-key" class="pagelayer-gmaps-api-key" <?php if(get_option('pagelayer-gmaps-api-key')){ echo 'value="'. esc_html(get_option('pagelayer-gmaps-api-key')).'"'; } ?> />
						</div>
					</div>
				</div>

			</div><!-- end #integration -->

			<?php if(defined('PAGELAYER_PREMIUM')){ ?>

			<!-- ===== CONTACT FORM TAB ===== -->
			<div class="pagelayer-tab-panel pagelayer-cf" id="contactform">
				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('Contact Form Settings'); ?></h3>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('To Email'); ?></span>
							<p class="pagelayer-setting-row-desc"><?php _e('You can use comma separated values for multiple emails.'); ?></p>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input name="pagelayer_cf_to_email" type="text" placeholder="email@domain.com" <?php if(get_option('pagelayer_cf_to_email')){ echo 'value="'.esc_html(get_option('pagelayer_cf_to_email')).'"'; } ?> />
						</div>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('From Email'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input name="pagelayer_cf_from_email" type="text" placeholder="My Site &lt;email@domain.com&gt;" <?php if(get_option('pagelayer_cf_from_email')){ echo 'value="'.esc_html(get_option('pagelayer_cf_from_email')).'"'; } ?> />
						</div>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Subject'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input name="pagelayer_cf_subject" type="text" placeholder="Subject" <?php if(get_option('pagelayer_cf_subject')){ echo 'value="'.esc_html(get_option('pagelayer_cf_subject')).'"'; } ?> />
						</div>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Additional Headers'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<textarea rows="3" name="pagelayer_cf_headers"><?php if(get_option('pagelayer_cf_headers')){ echo esc_html(get_option('pagelayer_cf_headers')); } ?></textarea>
						</div>
					</div>
				</div>
				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('Form Messages'); ?></h3>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Success Message'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input name="pagelayer_cf_success" type="text" placeholder="Success" <?php if(get_option('pagelayer_cf_success')){ echo 'value="'.esc_html(get_option('pagelayer_cf_success')).'"'; } ?> />
						</div>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('Failed Message'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input name="pagelayer_cf_failed" type="text" placeholder="Failed" <?php if(get_option('pagelayer_cf_failed')){ echo 'value="'.esc_html(get_option('pagelayer_cf_failed')).'"'; } ?> />
						</div>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('reCaptcha Failed Message'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input name="pagelayer_recaptcha_failed" type="text" placeholder="The CAPTCHA verification failed. Please try again." value="<?php echo esc_html(get_option('pagelayer_recaptcha_failed', __pl('cap_ver_fail'))); ?>" />
						</div>
					</div>
				</div>
			</div><!-- end #contactform -->

			<!-- ===== CAPTCHA TAB ===== -->
			<div class="pagelayer-tab-panel" id="captcha">
				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('Google reCAPTCHA'); ?></h3>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('reCAPTCHA Version'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<select name="pagelayer_recaptcha_version" id="pagelayer_recaptcha_version">
								<option value="" <?php echo esc_html(get_option('pagelayer_recaptcha_version', '')) === '' ? 'selected' : ''; ?>><?php _e('Google reCAPTCHA v2', 'pagelayer'); ?></option>
								<option value="v3" <?php echo esc_html(get_option('pagelayer_recaptcha_version', '')) === 'v3' ? 'selected' : ''; ?>><?php _e('Google reCAPTCHA v3', 'pagelayer'); ?></option>
							</select>
						</div>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('reCaptcha Site Key'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input name="pagelayer_google_captcha" type="text" placeholder="Site key" <?php if(get_option('pagelayer_google_captcha')){ echo 'value="'.esc_html(get_option('pagelayer_google_captcha')).'"'; } ?> />
						</div>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('reCaptcha Secret Key'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<input name="pagelayer_google_captcha_secret" type="text" placeholder="Secret key" <?php if(get_option('pagelayer_google_captcha_secret')){ echo 'value="'.esc_html(get_option('pagelayer_google_captcha_secret')).'"'; } ?> />
						</div>
					</div>
					<div class="pagelayer-setting-row">
						<div class="pagelayer-setting-row-left">
							<span class="pagelayer-setting-row-label"><?php _e('reCaptcha Language'); ?></span>
						</div>
						<div class="pagelayer-setting-row-control" style="flex:1;max-width:420px;">
							<select name="pagelayer_google_captcha_lang">
								<?php foreach($recap_lang as $k => $v){ echo '<option '.( get_option('pagelayer_google_captcha_lang', '') == $k ? 'selected="selected"' : '').' value="'.esc_attr($k).'">'.esc_html($v).'</option>'; } ?>
							</select>
						</div>
					</div>
				</div>
			</div><!-- end #captcha -->

			<?php } ?>

			<!-- ===== SUPPORT TAB ===== -->
			<?php if(!defined('SITEPAD')){ ?>
			<div class="pagelayer-tab-panel" id="support">
				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('Support'); ?></h3>
					</div>
					<div style="padding:22px;">
						<h4><?php _e('You can contact the Pagelayer Team via email. Our email address is <a href="mailto:support@pagelayer.com">support@pagelayer.com</a>. We will get back to you as soon as possible!'); ?></h4>
					</div>
				</div>
			</div><!-- end #support -->

			<!-- ===== FAQ TAB ===== -->
			<div class="pagelayer-tab-panel" id="faq">
				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3><?php _e('FAQ'); ?></h3>
					</div>
					<div style="padding:22px;">
						<div class="pagelayer-acc-wrapper">
							<span class="nav-tab pagelayer-acc-tab"><?php _e('1: Why choose us'); ?></span>
							<div class="pagelayer-acc-panel">
								<p><?php _e('Pagelayer is best live editor and easy to use and we will keep improving it !'); ?></p>
							</div>
							<span class="nav-tab pagelayer-acc-tab"><?php _e('2: Support'); ?></span>
							<div class="pagelayer-acc-panel">
								<p><?php _e('You can contact the Pagelayer Group via email. Our email address is <a href="mailto:support@pagelayer.com">support@pagelayer.com</a>. We will get back to you as soon as possible!'); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div><!-- end #faq -->
			<?php } ?>

		</div><!-- end .tabs-wrapper -->

	</form>
	
<?php
	
	pagelayer_page_footer();

}
