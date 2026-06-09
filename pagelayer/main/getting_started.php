<?php

//////////////////////////////////////////////////////////////
//===========================================================
// getting_started.php
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

$app = (!defined('SITEPAD') ? 'Pagelayer' : BRAND_SM);

include_once(PAGELAYER_DIR.'/main/settings.php');
pagelayer_page_header('Getting Started', 1);
?>

<link rel="stylesheet" href="<?php echo PAGELAYER_CSS.'/font-awesome5.min.css';?>">

<div class="pagelayer-getting-started" style="padding-top:10px;">
	<div class="pagelayer-getting-started-container">
		<div class="pagelayer-getting-started-block">
			<div class="pagelayer-getting-started-logo">
				<?php echo (!defined('SITEPAD')) ? '<img src="'.PAGELAYER_URL.'/images/pagelayer-logo-256.png'.'"/>' : '<img src="'.BRAND_SM_LOGO.'" style="width:auto"/>' ?>
			</div>
			<div class="pagelayer-getting-started-desc">
				<h1><?php echo __pl('welcome_to').$app;?></h1>
				<h6><?php echo (!defined('SITEPAD')) ? __pl('choose_pagelayer') : __pl('choose_sitepad');?></h6>
			</div>
			<div class="pagelayer-getting-started-video">
				<?php echo (!defined('SITEPAD')) ? '<iframe width="700" height="400" src="https://www.youtube.com/embed/t8Iz-v-qce8" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>' : '<iframe height="400" width="700" src="https://www.youtube.com/embed/8e3ROkKoFwA" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';?>
			</div>
			<div class="pagelayer-getting-started-desc">
				<h6><?php echo (!defined('SITEPAD')) ? __pl('pagelayer_desc') : __pl('sitepad_desc');?></h6>
				<div class="pagelayer-getting-started-btn">
					<a href="<?php echo admin_url('/post-new.php?post_type=page')?>" class="button button-primary btn-sc"><?php echo __pl('first_page');?></a>
					<a href="<?php echo (!defined('SITEPAD')) ? PAGELAYER_WWW_URL.'getting-started' : "https://sitepad.com/docs/getting-started/"; ?>" class="button button-secondary btn-sc" target="_blank"><?php echo __pl('watch_guide');?></a>
				</div>
			</div>
		</div>
		<div class="pagelayer-features">
			<div class="pagelayer-getting-started-desc">
				<h1><?php echo $app.' '.__pl('feature_style');?></h1>
				<h6><?php echo $app.__pl('brand_feature_text');?></h6>
				<div class="pagelayer-features-list">
				<?php $style = (defined('SITEPAD')) ? 'style="width:30%; height: 265px"' : ''; ?>
					<div class="feature-block-card" <?php echo $style; ?>>
						<div class="feature-block">
							<?php echo (!defined('SITEPAD')) ? '<i class="fas fa-mouse-pointer" aria-hidden="true">' : '<i class="fas fa-paper-plane" aria-hidden="true">' ?></i>
						</div>
						<div class="feature-block-content">
							<h5><?php echo (!defined('SITEPAD')) ? __pl('dragdrop') : __pl('oneclick')?></h5>
							<p><?php echo (!defined('SITEPAD')) ? __pl('dragdrop_desc') : __pl('oneclick_desc');?></p>
						</div>
					</div>
					<div class="feature-block-card" <?php echo $style; ?>>
						<div class="feature-block">
							<?php echo (!defined('SITEPAD')) ? '<i class="fa fa-th-list" aria-hidden="true">' : '<i class="fas fa-random" aria-hidden="true">' ?></i>
						</div>
						<div class="feature-block-content">
							<h5><?php echo (!defined('SITEPAD')) ? __pl('widgets') : __pl('static_pages')?></h5>
							<p><?php echo (!defined('SITEPAD')) ? __pl('widgets_desc') : __pl('static_pages_desc');?></p>
						</div>
					</div>
					<div class="feature-block-card" <?php echo $style; ?>>
						<div class="feature-block">
							<?php echo (!defined('SITEPAD')) ? '<i class="fa fa-pencil" aria-hidden="true">' : '<i class="fas fa-mobile-alt" aria-hidden="true">' ?></i>
						</div>
						<div class="feature-block-content">
							<h5><?php echo (!defined('SITEPAD')) ? __pl('inline_edit') : __pl('responsive_styles')?></h5>
							<p><?php echo (!defined('SITEPAD')) ? __pl('inline_edit_desc') : __pl('responsive_desc');?></p>
						</div>
					</div>
					<div class="feature-block-card" <?php echo $style; ?>>
						<div class="feature-block">
							<?php echo (!defined('SITEPAD')) ? '<i class="fa fa-clone" aria-hidden="true">' : '<i class="fas fa-share-square" aria-hidden="true">' ?></i>
						</div>
						<div class="feature-block-content">
							<h5><?php echo (!defined('SITEPAD')) ? __pl('duplicate') : __pl('social_media')?></h5>
							<p><?php echo (!defined('SITEPAD')) ? __pl('duplicate_desc') : __pl('social_media_desc');?></p>
						</div>
					</div>
					<div class="feature-block-card" <?php echo $style; ?>>
						<div class="feature-block">
							<?php echo (!defined('SITEPAD')) ? '<i class="fa fa-snowflake-o fa-spin" aria-hidden="true">' : '<i class="fas fa-check" aria-hidden="true">' ?></i>
						</div>
						<div class="feature-block-content">
							<h5><?php echo (!defined('SITEPAD')) ? __pl('animation') : __pl('easy_use')?></h5>
							<p><?php echo (!defined('SITEPAD')) ? __pl('animation_desc') : __pl('easy_use_desc');?></p>
						</div>
					</div>
					<div class="feature-block-card" <?php echo $style; ?>>
						<div class="feature-block">
							<?php echo (!defined('SITEPAD')) ? '<i class="fa fa-text-width" aria-hidden="true">' : '<i class="fas fa-cog" aria-hidden="true">' ?></i>
						</div>
						<div class="feature-block-content">
							<h5><?php echo (!defined('SITEPAD')) ? __pl('style_option') : __pl('cpanel_integrate')?></h5>
							<p><?php echo (!defined('SITEPAD')) ? __pl('style_option_desc') : __pl('cpanel_integrate_desc');?></p>
						</div>
					</div>
					<div class="feature-block-card" <?php echo $style; ?>>
						<div class="feature-block">
							<?php echo (!defined('SITEPAD')) ? '<i class="fa fa-paint-brush" aria-hidden="true">' : '<i class="fas fa-th-large" aria-hidden="true">' ?></i>
						</div>
						<div class="feature-block-content">
							<h5><?php echo (!defined('SITEPAD')) ? __pl('real_design') : __pl('multisites')?></h5>
							<p><?php echo (!defined('SITEPAD')) ? __pl('real_design_desc') : __pl('multisites_desc');?></p>
						</div>
					</div>
					<div class="feature-block-card" <?php echo $style; ?>>
						<div class="feature-block">
							<?php echo (!defined('SITEPAD')) ? '<i class="fa fa-font" aria-hidden="true">' : '<i class="fas fa-copy" aria-hidden="true">' ?></i>
						</div>
						<div class="feature-block-content">
							<h5><?php echo (!defined('SITEPAD')) ? __pl('typography') : __pl('replicate_obj')?></h5>
							<p><?php echo (!defined('SITEPAD')) ? __pl('typography_desc') : __pl('replicate_obj_desc');?></p>
						</div>
					</div>
					<div class="feature-block-card" <?php echo $style; ?>>
						<div class="feature-block">
							<?php echo (!defined('SITEPAD')) ? '<i class="fa fa-cubes" aria-hidden="true">' : '<i class="fas fa-shopping-cart" aria-hidden="true">' ?></i>
						</div>
						<div class="feature-block-content">
							<h5><?php echo (!defined('SITEPAD')) ? __pl('easy_customize') : __pl('whmcs')?></h5>
							<p><?php echo (!defined('SITEPAD')) ? __pl('easy_customize_desc') : __pl('whmcs_desc');?></p>
						</div>
					</div>
				</div>
				<div class="pagelayer-getting-started-btn">
					<a href=" <?php echo (!defined('SITEPAD')) ? PAGELAYER_WWW_URL : "http://sitepad.com/"?>" class="button button-secondary btn-sc" target="_blank" style="margin-top:20px;"><?php echo __pl('why').' '.$app.'?';?></a>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
.pagelayer-getting-started{
	padding: 20px 0;
}

.pagelayer-getting-started-container{
	margin: 0 auto;
	max-width: 1000px;
	padding: 0;
	text-align: center;
}

.pagelayer-getting-started-block{
	background-color: #fff;
	border: 1px solid #e2e8f0;
	border-radius: 16px;
	margin-bottom: 40px;
	padding: 48px 32px;
	box-shadow: 0 4px 20px -2px rgba(0,0,0,0.03), 0 2px 8px -1px rgba(0,0,0,0.02);
}

.pagelayer-getting-started-logo {
	margin-bottom: 24px;
}

.pagelayer-getting-started-logo img{
	max-width: 120px;
	height: auto;
	display: inline-block;
}

.pagelayer-getting-started-desc{
	padding: 0;
	margin-bottom: 28px;
}

.pagelayer-getting-started-desc h1{
	color: #0f172a;
	font-size: 28px;
	font-weight: 700;
	margin: 0 0 12px 0;
	letter-spacing: -0.5px;
}

.pagelayer-getting-started-desc h6{
	font-size: 15px;
	font-weight: 400;
	line-height: 1.6;
	color: #475569;
	margin: 0 auto;
	max-width: 720px;
}

/* Responsive Video Wrapper */
.pagelayer-getting-started-video {
	position: relative;
	padding-bottom: 56.25%; /* 16:9 ratio */
	height: 0;
	overflow: hidden;
	max-width: 760px;
	margin: 32px auto;
	border-radius: 12px;
	box-shadow: 0 12px 30px rgba(0,0,0,0.08);
	border: 1px solid #e2e8f0;
}

.pagelayer-getting-started-video iframe {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	border: 0;
}

.pagelayer-getting-started-btn{
	margin: 32px auto 0;
	display: flex;
	gap: 16px;
	justify-content: center;
	flex-wrap: wrap;
}

/* Buttons styling */
.pagelayer-getting-started-btn .btn-sc {
	font-size: 14px !important;
	font-weight: 600 !important;
	padding: 12px 32px !important;
	border-radius: 8px !important;
	height: auto !important;
	line-height: 1.4 !important;
	cursor: pointer;
	text-decoration: none;
	transition: all 0.2s ease-in-out;
	display: inline-block;
}

.pagelayer-getting-started-btn .button-primary {
	background: #4f46e5 !important;
	color: #fff !important;
	border: none !important;
	box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.15), 0 2px 4px -1px rgba(79, 70, 229, 0.1) !important;
}

.pagelayer-getting-started-btn .button-primary:hover {
	background: #4338ca !important;
	box-shadow: 0 6px 12px -2px rgba(79, 70, 229, 0.25) !important;
	transform: translateY(-1px);
}

.pagelayer-getting-started-btn .button-secondary {
	background: #fff !important;
	color: #344054 !important;
	border: 1.5px solid #d0d5dd !important;
}

.pagelayer-getting-started-btn .button-secondary:hover {
	background: #f9fafb !important;
	border-color: #cbd5e1 !important;
	color: #1e293b !important;
	transform: translateY(-1px);
}

/* Features section */
.pagelayer-features{
	background-color: #fff;
	border: 1px solid #e2e8f0;
	border-radius: 16px;
	padding: 48px 32px;
	box-shadow: 0 4px 20px -2px rgba(0,0,0,0.03);
}

.pagelayer-features-list {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 24px;
	margin-top: 40px;
}

.feature-block-card{
	background: #fff;
	border: 1px solid #f1f5f9;
	border-radius: 12px;
	padding: 28px 20px;
	box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.01);
	transition: all 0.25s ease-in-out;
	display: flex;
	flex-direction: column;
	align-items: center;
	text-align: center;
	box-sizing: border-box;
}

.feature-block-card:hover {
	border-color: #e2e8f0;
	box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
	transform: translateY(-2px);
}

.feature-block{
	background: #eef2ff !important;
	border-radius: 50%;
	width: 56px;
	height: 56px;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: 20px;
	transition: background-color 0.2s;
}

.feature-block-card:hover .feature-block {
	background: #e0e7ff !important;
}

.feature-block i{
	font-size: 22px;
	color: #4f46e5;
}

.feature-block-content h5{
	color: #0f172a;
	font-size: 17px;
	font-weight: 600;
	margin: 0 0 10px 0;
	letter-spacing: -0.2px;
}

.feature-block-content p{
	color: #475569;
	font-size: 13.5px;
	line-height: 1.5;
	margin: 0;
}

.fa-spin{
	-webkit-animation: fa-spin 2s infinite linear;
	animation: fa-spin 2s infinite linear;
}
</style>
