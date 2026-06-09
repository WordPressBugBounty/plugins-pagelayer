<?php

//////////////////////////////////////////////////////////////
//===========================================================
// license.php
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

include_once(PAGELAYER_DIR.'/main/settings.php');

function pagelayer_clear_empty_r(&$r){
	
	foreach($r as $a => $b){
		if(empty($b)){
			unset($r[$a]);
			continue;
		}
		
		if(is_array($b)){
			pagelayer_clear_empty_r($r[$a]);
		}
	}
	
	return $r;
	
}

// The License Page
function pagelayer_website_settings(){
	
	global $pagelayer, $pl_error;
	
	pagelayer_load_font_options();
	
	if(!empty($_POST)){
		check_admin_referer('pagelayer-options');
	}
	
	if(isset($_POST['submit'])){
		
		foreach($pagelayer->css_settings as $set => $params){
			
			foreach($pagelayer->screens as $sk => $sv){
				
				$suffix = (!empty($sv) ? '_'.$sv : '');
				$key = $set.$suffix;
				$setting = empty($params['key']) ? 'pagelayer_'.$set.'_css' : $params['key'];
					
				if(isset($_POST[$key])){
					
					foreach($_POST[$key] as $k => $v){
						if($v == 'Default' || empty($v)){
							unset($_POST[$key][$k]);
						}
					
						// For sidebar, width default should not be saved
						if($set == 'sidebar' && $k == 'width' && $v == 20){
							unset($_POST[$key][$k]);
						}
					}
					
					// Padding and Margins or any array based setting
					if(!empty($_POST[$key]) && is_array($_POST[$key])){
						pagelayer_clear_empty_r($_POST[$key]);
						//pagelayer_print($_POST[$key]);
					}
					
					// Are we to save ?
					if(!empty($_POST[$key])){
						update_option($setting.$suffix, (!empty($_POST[$key]) ? $_POST[$key] : []));
					}else{
						delete_option($setting.$suffix);
					}
					
				}else{
					delete_option($setting.$suffix);
				}
				
			}
			
		}
		
		// Blank the old color values
		delete_option('pagelayer_color');
			
		// Blank the old Body font
		if(!empty($_POST['body']['font-family'])){
			update_option('pagelayer_body_font', '');
		}
		
		//pagelayer_print($_POST);		
	
		// Content Width
		if(isset($_REQUEST['pagelayer_content_width'])){
			update_option( 'pagelayer_content_width', absint($_REQUEST['pagelayer_content_width']));
		}

		// Tablet breakpoint 
		if(isset($_REQUEST['pagelayer_tablet_breakpoint'])){	
			update_option( 'pagelayer_tablet_breakpoint', absint($_REQUEST['pagelayer_tablet_breakpoint']));			
		}

		// Mobile breakpoint 
		if(isset($_REQUEST['pagelayer_mobile_breakpoint'])){
			update_option( 'pagelayer_mobile_breakpoint', absint($_REQUEST['pagelayer_mobile_breakpoint']));
		}
		
		// Widget Space
		if(isset($_REQUEST['pagelayer_between_widgets'])){
			update_option( 'pagelayer_between_widgets', absint($_REQUEST['pagelayer_between_widgets']));
		}
		
		if(defined('PAGELAYER_PREMIUM')){
		
			// Save Header code
			if(isset($_REQUEST['pagelayer_header_code'])){	
				$header_code = wp_unslash($_REQUEST['pagelayer_header_code']);
				if(!pagelayer_user_can_add_js_content()){
					$header_code = sanitize_textarea_field($header_code);
				}
				
				update_option( 'pagelayer_header_code', $header_code);
			}else{
				delete_option('pagelayer_header_code');
			}

			// Save Body open code
			if(isset($_REQUEST['pagelayer_body_open_code'])){	
				$body_code = wp_unslash($_REQUEST['pagelayer_body_open_code']);
				if(!pagelayer_user_can_add_js_content()){
					$body_code = sanitize_textarea_field($body_code);
				}
				
				update_option( 'pagelayer_body_open_code', $body_code);
			}else{
				delete_option('pagelayer_body_open_code');
			}

			// Save Footer code
			if(isset($_REQUEST['pagelayer_footer_code'])){
				$footer_code = wp_unslash($_REQUEST['pagelayer_footer_code']);
				if(!pagelayer_user_can_add_js_content()){
					$footer_code = sanitize_textarea_field($footer_code);
				}
				
				update_option( 'pagelayer_footer_code', $footer_code);
			}else{
				delete_option('pagelayer_footer_code');
			}
		
		}
		
		$GLOBALS['pl_saved'] = true;
		
	}
	
	pagelayer_website_settings_T();
	
}

// The License Page - THEME
function pagelayer_website_settings_T(){
	
	global $pagelayer, $pl_error;

	pagelayer_page_header('Website Settings');

	// Saved ?
	if(!empty($GLOBALS['pl_saved'])){
		echo '<div class="notice notice-success"><p>'. __('The settings were saved successfully', 'pagelayer'). '</p></div><br />';
	}

	// Any errors ?
	if(!empty($pl_error)){
		pagelayer_report_error($pl_error);echo '<br />';
	}
	
	// Reduce load
	echo '<select id="skeleton_of_fonts" style="display:none">';
	foreach($pagelayer->fonts as $subType => $fontType){
		if($subType != 'default'){
			echo '<optgroup style="text-transform: capitalize" label="'.$subType.'">';
		}
		foreach($fontType as $k => $font){
			echo '<option value="'.esc_html(is_numeric($k) ? $font : $k).'">'. esc_html(empty($font) ? 'Default': $font) .'</option>';
		}		
	}
	echo '</select>';
	
	?>
	
<form class="pagelayer-setting-form" method="post" action="" id="pagelayer-website-settings-form" onsubmit="return pagelayer_handle_website_submit(this)">
	<?php wp_nonce_field('pagelayer-options'); 
	// vanilla-picker is already bundled in combined.js / editor - no remote script needed
	
	// Inject Save Changes button into header via inline script to keep UI consistent
	echo '<script>
	jQuery(function($){
		var btn = \'<input type="submit" name="submit" form="pagelayer-website-settings-form" class="pl-save-btn" value="' . esc_attr__('Save Changes') . '">\';
		$(".pagelayer-header-actions").append(btn);
	});
	</script>';
	?>
	<div class="tabs-wrapper">
		<h2 class="nav-tab-wrapper pagelayer-wrapper">
			<a href="#headings" class="nav-tab "><?php echo esc_html(__pl('elem_styles'));?></a>
			<a href="#website_container" class="nav-tab"><?php echo esc_html(__pl('container'));?></a>
			<!--<a href="#pagelayer-sidebar" class="nav-tab">Sidebar</a>-->
			<a href="#hf" class="nav-tab "><?php echo esc_html(__pl('hf'));?></a>
		</h2>
		
		<div id="headings" class="pagelayer-tab-panel pagelayer-tab-active">
			
			<?php
			
			echo '<div style="display:inline-block;vertical-align: top;">
			<ul class="nav-tab-wrapper pagelayer-wrapper pagelayer-heading-wrapper">';
				
			foreach($pagelayer->css_settings as $k => $v){
				echo '<li><a href="#tab_'.esc_attr($k).'" class="nav-tab pagelayer-heading-tab" tab-class="pagelayer-heading-tab-panel">'.esc_html($v['name']).' Style</a></li>';				
			}
			
			echo '</ul>
			</div>
			
			<div style="display:inline-block;vertical-align: top;">';
			
			foreach($pagelayer->css_settings as $k => $v){
				
				echo '<div class="pagelayer-heading-tab-panel" id="tab_'.esc_attr($k).'">
				<div class="pagelayer-settings-card">
					<div class="pagelayer-settings-card-header">
						<h3>'.esc_html($v['name']).' Style</h3>
						<span class="pl-badge pl-badge-pro">'.esc_html__('Element Styles').'</span>
					</div>';
				
				echo '<div style="padding: 16px 24px; border-bottom: 1px solid #f1f5f9;">
				<ul class="nav-tab-wrapper pagelayer-wrapper pagelayer-styles-screens" style="margin: 0 !important; border-bottom: none !important;">';
				
				foreach($pagelayer->screens as $sk => $sv){
					echo '<li><a href="#tab_'.esc_attr($k).'_'.esc_attr($sk).'" class="nav-tab pagelayer-styles-screen-tab" tab-class="pagelayer-styles-screen-panel">'.esc_html(ucfirst($sk)).'</a></li>';					
				}
				
				echo '</ul>
				</div>';
				
				foreach($pagelayer->screens as $sk => $sv){
					echo '<div class="pagelayer-styles-screen-panel" id="tab_'.esc_attr($k).'_'.esc_attr($sk).'">';
					pagelayer_website_font_settings($k.(!empty($sv) ? '_'.$sv : ''));
					echo '</div>';
				}
				
				echo '</div></div>';
			}
			
			echo '</div>';
			
			?>
		
		</div>
	
		<div class="pagelayer-tab-panel" id="website_container">
			<div class="pagelayer-settings-card">
				<div class="pagelayer-settings-card-header">
					<h3><?php echo esc_html(__('Container Settings')); ?></h3>
					<span class="pl-badge"><?php echo esc_html(__('Layout')); ?></span>
				</div>
				
				<!-- Content Width -->
				<div class="pagelayer-setting-row">
					<div class="pagelayer-setting-row-left">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('Content Width')); ?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('Set the custom width of the content area. The default width set is 1170px.')); ?></p>
					</div>
					<div class="pagelayer-setting-row-control" style="width: 200px;">
						<input name="pagelayer_content_width" type="number" step="1" min="320" max="5000" placeholder="1170" <?php if(get_option('pagelayer_content_width')){
							echo 'value="'.esc_attr(absint(get_option('pagelayer_content_width'))).'"';
						}?>>
					</div>
				</div>

				<!-- Tablet Breakpoint -->
				<div class="pagelayer-setting-row">
					<div class="pagelayer-setting-row-left">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('Tablet Breakpoint')); ?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('Set the breakpoint for tablet devices. The default breakpoint for tablet layout is 768px.')); ?></p>
					</div>
					<div class="pagelayer-setting-row-control" style="width: 200px;">
						<input name="pagelayer_tablet_breakpoint" type="number" step="1" min="320" max="5000" placeholder="780" <?php if(get_option('pagelayer_tablet_breakpoint')){
							echo 'value="'.esc_attr(absint(get_option('pagelayer_tablet_breakpoint'))).'"';
						}?>>
					</div>
				</div>

				<!-- Mobile Breakpoint -->
				<div class="pagelayer-setting-row">
					<div class="pagelayer-setting-row-left">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('Mobile Breakpoint')); ?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('Set the breakpoint for mobile devices. The default breakpoint for mobile layout is 480px.')); ?></p>
					</div>
					<div class="pagelayer-setting-row-control" style="width: 200px;">
						<input name="pagelayer_mobile_breakpoint" type="number" step="1" min="320" max="5000" placeholder="480" <?php if(get_option('pagelayer_mobile_breakpoint')){
							echo 'value="'.esc_attr(absint(get_option('pagelayer_mobile_breakpoint'))).'"';
						}?>>
					</div>
				</div>

				<!-- Space Between Widgets -->
				<div class="pagelayer-setting-row">
					<div class="pagelayer-setting-row-left">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('Space Between Widgets')); ?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('Set the Space Between Widgets. The default Space set is 15px.')); ?></p>
					</div>
					<div class="pagelayer-setting-row-control" style="width: 200px;">
						<input name="pagelayer_between_widgets" type="number" step="1" min="0" max="500" placeholder="15" <?php if(get_option('pagelayer_between_widgets')){
							echo 'value="'.esc_attr(absint(get_option('pagelayer_between_widgets'))).'"';
						}?>>
					</div>
				</div>
			</div>
		</div>
	
		<div class="pagelayer-tab-panel" id="sidebar">
			<div class="pagelayer-settings-card">
				<div class="pagelayer-settings-card-header">
					<h3><?php echo esc_html(__('Sidebar Preferences')); ?></h3>
					<span class="pl-badge"><?php echo esc_html(__('Sidebar')); ?></span>
				</div>
				<div style="padding: 20px 24px; border-bottom: 1px solid #f1f5f9; background: #fafafa;">
					<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('By default, the themes sidebar will be shown. But you can customize the settings here as per your preference. Note : This will work only if your theme uses the get_sidebar() function. Also the main content element and sidebar element should be siblings. If they are not siblings, then only the <b>No Sidebar</b> option will be usable.'));?></p>
				</div>
				
				<!-- Default -->
				<div class="pagelayer-setting-row">
					<div class="pagelayer-setting-row-left">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('Default Sidebar Layout'));?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('Default layout for the Sidebar throughout the site.'));?></p>
					</div>
					<div class="pagelayer-setting-row-control">
						<?php pagelayer_sidebar_select('default');?>
					</div>
				</div>

				<!-- For Pages -->
				<div class="pagelayer-setting-row">
					<div class="pagelayer-setting-row-left">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('For Pages'));?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('Sidebar layout specifically for static pages.'));?></p>
					</div>
					<div class="pagelayer-setting-row-control">
						<?php pagelayer_sidebar_select('page');?>
					</div>
				</div>

				<!-- For Posts -->
				<div class="pagelayer-setting-row">
					<div class="pagelayer-setting-row-left">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('For Posts'));?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('Sidebar layout specifically for blog posts.'));?></p>
					</div>
					<div class="pagelayer-setting-row-control">
						<?php pagelayer_sidebar_select('post');?>
					</div>
				</div>

				<!-- For Archives -->
				<div class="pagelayer-setting-row">
					<div class="pagelayer-setting-row-left">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('For Archives'));?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('Sidebar layout specifically for archives.'));?></p>
					</div>
					<div class="pagelayer-setting-row-control">
						<?php pagelayer_sidebar_select('archives');?>
					</div>
				</div>

				<!-- Width -->
				<div class="pagelayer-setting-row">
					<div class="pagelayer-setting-row-left">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('Width'));?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('Set the width of the sidebar (in percentage).'));?></p>
					</div>
					<div class="pagelayer-setting-row-control" style="display:flex; align-items:center; gap:8px;">
						<?php
						$sidebar_width = '20';
						if (!empty($_POST['sidebar']) && is_array($_POST['sidebar']) && isset($_POST['sidebar']['width'])) {
							$sidebar_width = $_POST['sidebar']['width'];
						} elseif (!empty($pagelayer->css) && is_array($pagelayer->css) && isset($pagelayer->css['sidebar']['width'])) {
							$sidebar_width = $pagelayer->css['sidebar']['width'];
						}
						?>
						<input type="number" name="sidebar[width]" min="1" step="1" value="<?php echo esc_attr($sidebar_width); ?>" style="width: 100px;" /><span>%</span>
					</div>
				</div>
			</div>
		</div>
		
		<div class="pagelayer-tab-panel" id="hf">
			<?php pagelayer_show_pro_notice();?>
			<div class="pagelayer-settings-card">
				<div class="pagelayer-settings-card-header">
					<h3><?php echo esc_html(__('Header, Body and Footer Custom Code')); ?></h3>
					<span class="pl-badge pl-badge-pro"><?php echo esc_html(__('Pro Feature')); ?></span>
				</div>
				<div style="padding: 20px 24px; border-bottom: 1px solid #f1f5f9; background: #fafafa;">
					<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('You can add custom code like HTML, JavaScript, CSS etc. which will be inserted throughout your site.'));?></p>
				</div>
				
				<!-- Header Code -->
				<div class="pagelayer-setting-row" style="align-items: flex-start;">
					<div class="pagelayer-setting-row-left" style="max-width: 320px;">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('Header Code'));?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('This code will be inserted inside the <code>&lt;head&gt;</code> section.'));?></p>
					</div>
					<div class="pagelayer-setting-row-control" style="flex: 1; max-width: 600px;">
						<textarea name="pagelayer_header_code" rows="6" style="width: 100%; max-width: 100%; font-family: monospace; font-size: 12px;"><?php echo esc_textarea(get_option( 'pagelayer_header_code' )); ?></textarea>
					</div>
				</div>

				<!-- Body Open Code -->
				<div class="pagelayer-setting-row" style="align-items: flex-start;">
					<div class="pagelayer-setting-row-left" style="max-width: 320px;">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('Body Open Code'));?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('This code will be printed at the beginning of the <code>&lt;body&gt;</code> section.'));?></p>
					</div>
					<div class="pagelayer-setting-row-control" style="flex: 1; max-width: 600px;">
						<textarea name="pagelayer_body_open_code" rows="8" style="width: 100%; max-width: 100%; font-family: monospace; font-size: 12px;"><?php echo esc_textarea(get_option( 'pagelayer_body_open_code' )); ?></textarea>
					</div>
				</div>

				<!-- Footer Code -->
				<div class="pagelayer-setting-row" style="align-items: flex-start;">
					<div class="pagelayer-setting-row-left" style="max-width: 320px;">
						<span class="pagelayer-setting-row-label"><?php echo esc_html(__('Footer Code'));?></span>
						<p class="pagelayer-setting-row-desc"><?php echo esc_html(__('This code will be printed before the closing <code>&lt;/body&gt;</code> section.'));?></p>
					</div>
					<div class="pagelayer-setting-row-control" style="flex: 1; max-width: 600px;">
						<textarea name="pagelayer_footer_code" rows="6" style="width: 100%; max-width: 100%; font-family: monospace; font-size: 12px;"><?php echo esc_textarea(get_option( 'pagelayer_footer_code' )); ?></textarea>
					</div>
				</div>
			</div>
		</div>
		
	</div>
			
	<div class="pl-security-note" style="margin-top:20px; margin-bottom: 20px;">
		<b><?php echo esc_html__('Note:'); ?></b> <?php echo esc_html(__pl('color_notice')); ?>
	</div>
</form>

<script>

function pagelayer_handle_website_submit(ele){
	
	var jEle = jQuery(ele);
	var $form = jEle.is('form') ? jEle : jEle.closest('form');
	if (!$form.length && jEle.attr('form')) {
		$form = jQuery('#' + jEle.attr('form'));
	}
	$form.find('input, select, textarea').each(function(){
		var j = jQuery(this);
		if(j.attr('type') === 'submit'){
			return;
		}
		
		if(j.val().length == 0){
			j.prop("disabled", true);
		}
	});
	
	return true;
}
	
// Show the vanilla selector
function pagelayer_show_vanilla(){
	if (typeof Picker === 'undefined') {
		return;
	}
	jQuery('.pagelayer-show-vanilla').each(function(){
		var jEle = jQuery(this);
		var par = jEle.parent();
		var input = par.find('input');
		var sColor = '';
		
		if(input.val().length > 0){
			sColor = input.val();
			jEle.find('.pagelayer-color-div').css('background', sColor);
			jEle.find('.pagelayer-color-div').removeClass('pagelayer-color-none');
		}
		
		var picker = new Picker({
			parent : jEle[0],
			color : sColor,
		});
		
		// You can do what you want with the chosen color using two callbacks: onChange and onDone.
		picker.onChange = function(color) {
			jEle.find('.pagelayer-color-div').css('background', color.rgbaString);
			jEle.find('.pagelayer-color-div').removeClass('pagelayer-color-none');
			input.val(color.hex);
		};
		
		jEle.find('.dashicons').on('click', function(event){
			event.preventDefault();
			event.stopPropagation();
			jEle.find('.pagelayer-color-div').addClass('pagelayer-color-none');
			input.val('');
		});
	});
}

function pagelayer_handle_custom(ele){
	var jEle = jQuery(ele);
	var val = jEle.val();
	if(val && val.length > 1){
		jEle.siblings().show();
	}else{
		jEle.siblings().hide();
		jEle.siblings('input').val('');
		jEle.siblings().children().val('');
	}
}

// Handle the font family
function pagelayer_handle_font_family(ele){
	jEle = jQuery(ele);
	if(jEle.children().length <= 1){
		var val = jEle.val();
		jEle.html(jQuery('#skeleton_of_fonts').html());
		jEle.val(val);
	}
}

function pagelayer_handle_textdecor(ele){
	var jEle = jQuery(ele);
	var val = jEle.val();
	if(val && val.length > 1 && val !== 'none'){
		jEle.siblings().show();
	}else{
		jEle.siblings().hide();
		jEle.siblings().val('');
	}
}

jQuery(document).ready(function(){
	pagelayer_show_vanilla();
	jQuery('.pagelayer-show-custom').each(function(){
		pagelayer_handle_custom(jQuery(this));
	});
	
});
</script>

<?php
	
	pagelayer_page_footer();

}

function pagelayer_website_padding_field($name, $val){
?>
	<input type="number" name="<?php echo esc_attr($name);?>[0]" step="1" class="pagelayer-website-padding" <?php echo (!empty($val[0]) ? 'value="'.esc_attr($val[0]).'"' : '');?> />
	<input type="number" name="<?php echo esc_attr($name);?>[1]" step="1" class="pagelayer-website-padding" <?php echo (!empty($val[1]) ? 'value="'.esc_attr($val[1]).'"' : '');?> />
	<input type="number" name="<?php echo esc_attr($name);?>[2]" step="1" class="pagelayer-website-padding" <?php echo (!empty($val[2]) ? 'value="'.esc_attr($val[2]).'"' : '');?> />
	<input type="number" name="<?php echo esc_attr($name);?>[3]" step="1" class="pagelayer-website-padding" <?php echo (!empty($val[3]) ? 'value="'.esc_attr($val[3]).'"' : '');?> /><span>px</span>
<?php	
}

// Shows the font settings
function pagelayer_website_font_settings($prefix){
	
	global $pagelayer, $pl_error;
	
	// load css from settings
	pagelayer_load_global_css();
	
	if(!empty($_POST)){
		$vals = $_POST;
	}else{
		$vals = $pagelayer->css;
	}
	
	?>
	
	<div class="pagelayer-font-settings-rows">
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label"><?php echo esc_html(__pl('padding'));?></span>
			</div>
			<div class="pagelayer-setting-row-control">
				<label style="display:flex; align-items:center; gap:8px;">
					<select class="pagelayer-show-custom" onchange="pagelayer_handle_custom(this)">
						<option value="" <?php echo (empty($vals[$prefix]['padding']) ? 'selected="selected"' : '');?>>Default</option>
						<option value="custom" <?php echo (!empty($vals[$prefix]['padding']) ? 'selected="selected"' : '');?>>Custom</option>
					</select>
					<span style="display: inline-flex; align-items: center; gap: 4px; vertical-align: middle;">
					<?php pagelayer_website_padding_field($prefix.'[padding]', @$vals[$prefix]['padding']);?>
					</span>
				</label>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label"><?php echo esc_html(__pl('margin'));?></span>
			</div>
			<div class="pagelayer-setting-row-control">
				<label style="display:flex; align-items:center; gap:8px;">
					<select class="pagelayer-show-custom" onchange="pagelayer_handle_custom(this)">
						<option value="" <?php echo (empty($vals[$prefix]['margin']) ? 'selected="selected"' : '');?>>Default</option>
						<option value="custom" <?php echo (!empty($vals[$prefix]['margin']) ? 'selected="selected"' : '');?>>Custom</option>
					</select>
					<span style="display: inline-flex; align-items: center; gap: 4px; vertical-align: middle;">
					<?php pagelayer_website_padding_field($prefix.'[margin]', @$vals[$prefix]['margin']);?>
					</span>
				</label>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label"><?php echo esc_html(__pl('font_family')); ?></span>
			</div>
			<div class="pagelayer-setting-row-control">
				<label>
					<select name="<?php echo esc_attr($prefix);?>[font-family]" onclick="pagelayer_handle_font_family(this)">
					<?php
						echo '<option value="'.esc_attr(empty($vals[$prefix]['font-family']) ? 'Default': $vals[$prefix]['font-family']).'">'.esc_html(empty($vals[$prefix]['font-family']) ? 'Default': $vals[$prefix]['font-family']).'</option>';
					?>
					</select>
				</label>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label"><?php echo esc_html(__pl('font_size')); ?></span>
			</div>
			<div class="pagelayer-setting-row-control">
				<label style="display:flex; align-items:center; gap:8px;">
					<select class="pagelayer-show-custom" onchange="pagelayer_handle_custom(this)">
						<option value="" <?php echo (empty($vals[$prefix]['font-size']) ? 'selected="selected"' : '');?>>Default</option>
						<option value="custom" <?php echo (!empty($vals[$prefix]['font-size']) ? 'selected="selected"' : '');?>>Custom</option>
					</select>
					<input type="number" name="<?php echo esc_attr($prefix);?>[font-size]" <?php echo (!empty($vals[$prefix]['font-size']) ? 'value="'.esc_attr($vals[$prefix]['font-size']).'"' : '');?> style="width:100px;" /><span>px</span>
				</label>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label"><?php echo esc_html(__pl('font_style')); ?></span>
			</div>
			<div class="pagelayer-setting-row-control">
				<label>
					<select name="<?php echo esc_attr($prefix);?>[font-style]">
					<?php
						foreach($pagelayer->font_style as $k => $var){							
							echo '<option value="'.esc_attr($k).'" '.(@$vals[$prefix]['font-style'] == $k ? 'selected' : '').'>'.esc_html($var).'</option>';
						}
					?>
					</select>
				</label>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label"><?php echo esc_html(__pl('font_weight'));?></span>
			</div>
			<div class="pagelayer-setting-row-control">
				<label>
					<select name="<?php echo esc_attr($prefix);?>[font-weight]">
					<?php
						foreach($pagelayer->font_weight as $k => $var){							
							echo '<option value="'.esc_attr($k).'" '.(@$vals[$prefix]['font-weight'] == $k ? 'selected' : '').'>'.esc_html($var).'</option>';
						}
					?>
					</select>
				</label>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label"><?php echo esc_html(__pl('text_transform'));?></span>
			</div>
			<div class="pagelayer-setting-row-control">
				<label>
					<select name="<?php echo esc_attr($prefix);?>[text-transform]">
					<?php
						foreach($pagelayer->text_transform as $k => $var){							
							echo '<option value="'.esc_attr($k).'" '.(@$vals[$prefix]['text-transform'] == $k ? 'selected' : '').'>'.esc_html($var).'</option>';
						}
					?>
					</select>
				</label>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label"><?php echo esc_html(__pl('line_height'));?></span>
			</div>
			<div class="pagelayer-setting-row-control">
				<label style="display:flex; align-items:center; gap:8px;">
					<select class="pagelayer-show-custom" onchange="pagelayer_handle_custom(this)">
						<option value="" <?php echo (empty($vals[$prefix]['line-height']) ? 'selected="selected"' : '');?>>Default</option>
						<option value="custom" <?php echo (!empty($vals[$prefix]['line-height']) ? 'selected="selected"' : '');?>>Custom</option>
					</select>
					<input type="number" name="<?php echo esc_attr($prefix);?>[line-height]" min="0.1" step="0.1" <?php echo (!empty($vals[$prefix]['line-height']) ? 'value="'.esc_attr($vals[$prefix]['line-height']).'"' : '');?> style="width:100px;" />
				</label>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label"><?php echo esc_html(__pl('text_spacing'));?></span>
			</div>
			<div class="pagelayer-setting-row-control">
				<label style="display:flex; align-items:center; gap:8px;">
					<select class="pagelayer-show-custom" onchange="pagelayer_handle_custom(this)">
						<option value="" <?php echo (empty($vals[$prefix]['letter-spacing']) ? 'selected="selected"' : '');?>>Default</option>
						<option value="custom" <?php echo (!empty($vals[$prefix]['letter-spacing']) ? 'selected="selected"' : '');?>>Custom</option>
					</select>
					<input type="number" name="<?php echo esc_attr($prefix);?>[letter-spacing]" min="1" step="1" <?php echo (!empty($vals[$prefix]['letter-spacing']) ? 'value="'.esc_attr($vals[$prefix]['letter-spacing']).'"' : '');?> style="width:100px;" /><span>px</span>
				</label>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label"><?php echo esc_html(__pl('word_spacing'));?></span>
			</div>
			<div class="pagelayer-setting-row-control">
				<label style="display:flex; align-items:center; gap:8px;">
					<select class="pagelayer-show-custom" onchange="pagelayer_handle_custom(this)">
						<option value="" <?php echo (empty($vals[$prefix]['word-spacing']) ? 'selected="selected"' : '');?>>Default</option>
						<option value="custom" <?php echo (!empty($vals[$prefix]['word-spacing']) ? 'selected="selected"' : '');?>>Custom</option>
					</select>
					<input type="number" name="<?php echo esc_attr($prefix);?>[word-spacing]" min="1" step="1" <?php echo (!empty($vals[$prefix]['word-spacing']) ? 'value="'.esc_attr($vals[$prefix]['word-spacing']).'"' : '');?> style="width:100px;" /><span>px</span>
				</label>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label"><?php echo esc_html(__pl('text_decoration'));?></span>
			</div>
			<div class="pagelayer-setting-row-control">
				<label>
					<table class="pagelayer-internal-table">
						<tr>
						<td>
							<select name="<?php echo esc_attr($prefix);?>[text-decoration-line]" onchange="pagelayer_handle_textdecor(this)">
							<?php
								foreach($pagelayer->text_decoration_line as $k => $var){							
									echo '<option value="'.esc_attr($k).'" '.(@$vals[$prefix]['text-decoration-line'] == $k ? 'selected' : '').'>'.esc_html($var).'</option>';
								}
							?>
							</select>
						</td>
						<td>
							<select name="<?php echo esc_attr($prefix);?>[text-decoration-style]">
							<?php
								foreach($pagelayer->text_decoration_style as $k => $var){							
									echo '<option value="'.esc_attr($k).'" '.(@$vals[$prefix]['text-decoration-style'] == $k ? 'selected' : '').'>'.esc_html($var).'</option>';
								}
							?>
							</select>
						</td>
						</tr>
						<tr>
							<td>Line</td>
							<td>Style</td>
						</tr>
					</table>
				</label>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label">Background Color</span>
			</div>
			<div class="pagelayer-setting-row-control">
				<a href="#" class="pagelayer-show-vanilla"><div class="pagelayer-color-div pagelayer-color-none"></div><span class="dashicons dashicons-no"></span></a><input type="hidden" name="<?php echo esc_attr($prefix);?>[background-color]" <?php echo (!empty($vals[$prefix]['background-color']) ? 'value="'.esc_attr($vals[$prefix]['background-color']).'"' : '');?>>
			</div>
		</div>
		
		<div class="pagelayer-setting-row">
			<div class="pagelayer-setting-row-left">
				<span class="pagelayer-setting-row-label">Text Color</span>
			</div>
			<div class="pagelayer-setting-row-control">
				<a href="#" class="pagelayer-show-vanilla"><div class="pagelayer-color-div pagelayer-color-none"></div><span class="dashicons dashicons-no"></span></a><input type="hidden" name="<?php echo esc_attr($prefix);?>[color]" <?php echo (!empty($vals[$prefix]['color']) ? 'value="'.esc_attr($vals[$prefix]['color']).'"' : '');?>>
			</div>
		</div>
	</div>
	
<?php
	
}

function pagelayer_sidebar_select($name){
	
	global $pagelayer;
	
	$val = isset($pagelayer->settings['sidebar'][$name]) ? $pagelayer->settings['sidebar'][$name] : 'default';
	$val = !empty($_POST) ? @$_POST['sidebar'][$name] : $val;
	
	// We dont save the value "Default" (note case sensitivity), but the theme customizer saves "default"
	// We need to keep all values blank if user is submitting values as Default
	
	echo '
	<select class="pagelayer-show-custom" name="sidebar['.$name.']">
		<option value="Default" '.($val == 'default' ? 'selected="seleted"' : '').'>Default</option>
		<option value="no" '.($val == 'no' ? 'selected="seleted"' : '').'>No Sidebar</option>
		<option value="left" '.($val == 'left' ? 'selected="seleted"' : '').'>Left Sidebar</option>
		<option value="right" '.($val == 'right' ? 'selected="seleted"' : '').'>Right Sidebar</option>
	</select>
	';
}