<?php
/**
 * Pagelayer Canvas — blank full-width page template.
 * No theme header/footer, post title, breadcrumbs, author, or post nav.
 */
if (!defined('ABSPATH')) {
	exit;
}

$pagelayer_canvas_kind = '';
$pagelayer_canvas_type = '';
if (is_singular()) {
	$pagelayer_canvas_id = get_queried_object_id();
	if ($pagelayer_canvas_id) {
		$pagelayer_canvas_kind = (string) get_post_meta($pagelayer_canvas_id, 'pagelayer_ai_layout_kind', true);
		$pagelayer_canvas_type = (string) get_post_meta($pagelayer_canvas_id, 'pagelayer_template_type', true);
		if ($pagelayer_canvas_kind === '' && $pagelayer_canvas_type !== '') {
			$pagelayer_canvas_kind = $pagelayer_canvas_type;
		}
		if ($pagelayer_canvas_kind === '' && $pagelayer_canvas_type === 'single') {
			$conds = get_post_meta($pagelayer_canvas_id, 'pagelayer_template_conditions', true);
			if (is_array($conds)) {
				foreach ($conds as $c) {
					if (!empty($c['sub_template']) && $c['sub_template'] === '404') {
						$pagelayer_canvas_kind = '404';
						break;
					}
				}
			}
		}
	}
}

$pagelayer_canvas_classes = array('pagelayer-canvas-body');
if ($pagelayer_canvas_kind !== '') {
	$pagelayer_canvas_classes[] = 'pagelayer-ai-kind-' . sanitize_html_class($pagelayer_canvas_kind);
}
if ($pagelayer_canvas_type !== '') {
	$pagelayer_canvas_classes[] = 'pagelayer-template-' . sanitize_html_class($pagelayer_canvas_type);
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
	<style id="pagelayer-canvas-hide-theme">
		.pagelayer-canvas-body .entry-title,
		.pagelayer-canvas-body .page-title,
		.pagelayer-canvas-body .entry-header,
		.pagelayer-canvas-body .entry-meta,
		.pagelayer-canvas-body .post-author,
		.pagelayer-canvas-body .author-box,
		.pagelayer-canvas-body .post-navigation,
		.pagelayer-canvas-body .nav-links,
		.pagelayer-canvas-body .breadcrumb,
		.pagelayer-canvas-body .breadcrumbs,
		.pagelayer-canvas-body .yoast-breadcrumb,
		.pagelayer-canvas-body .rank-math-breadcrumb,
		.pagelayer-canvas-body .posted-on,
		.pagelayer-canvas-body .cat-links,
		.pagelayer-canvas-body .tags-links { display: none !important; }
		.pagelayer-canvas-body, .pagelayer-canvas-body .site, .pagelayer-canvas-body #page,
		.pagelayer-canvas-body .site-content, .pagelayer-canvas-body .entry-content,
		.pagelayer-canvas-body .pagelayer-content { margin: 0; padding: 0; max-width: none; width: 100%; }
		/* Canvas Body Background & Dark Canvas Support */
		body.pagelayer-canvas-body {
			background-color: var(--pagelayer-color-bg, #ffffff);
			color: var(--pagelayer-color-text, #111827);
			transition: background-color 0.25s ease, color 0.25s ease;
		}
		body.pagelayer-canvas-body.pagelayer-canvas-dark,
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas,
		body.pagelayer-canvas-body.pagelayer-canvas-dark .site,
		body.pagelayer-canvas-body.pagelayer-canvas-dark #page,
		body.pagelayer-canvas-body.pagelayer-canvas-dark .site-content,
		body.pagelayer-canvas-body.pagelayer-canvas-dark .entry-content,
		body.pagelayer-canvas-body.pagelayer-canvas-dark .pagelayer-content,
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas .site,
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas #page,
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas .site-content,
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas .entry-content,
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas .pagelayer-content {
			background-color: var(--pagelayer-color-bg, #0f172a) !important;
			color: #ffffff !important;
		}
		body.pagelayer-canvas-body.pagelayer-canvas-dark h1:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark h2:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark h3:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark h4:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark h5:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark h6:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark p:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark li:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark span:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark .pagelayer-heading-holder *:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark .pagelayer-text-holder *:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark .pagelayer-service-heading:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-canvas-dark .pagelayer-service-text:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas h1:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas h2:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas h3:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas h4:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas h5:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas h6:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas p:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas li:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas span:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas .pagelayer-heading-holder *:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas .pagelayer-text-holder *:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas .pagelayer-service-heading:not(.pagelayer-ai-on-light *),
		body.pagelayer-canvas-body.pagelayer-ai-dark-canvas .pagelayer-service-text:not(.pagelayer-ai-on-light *) {
			color: #ffffff !important;
		}
		/* Dark AI bands: theme body/heading ink is usually black, which disappears
		   on navy/charcoal section backgrounds. Light cards nested inside keep dark text. */
		.pagelayer-canvas-body .pagelayer-ai-on-dark,
		.pagelayer-canvas-body .pagelayer-ai-on-dark h1,
		.pagelayer-canvas-body .pagelayer-ai-on-dark h2,
		.pagelayer-canvas-body .pagelayer-ai-on-dark h3,
		.pagelayer-canvas-body .pagelayer-ai-on-dark h4,
		.pagelayer-canvas-body .pagelayer-ai-on-dark h5,
		.pagelayer-canvas-body .pagelayer-ai-on-dark h6,
		.pagelayer-canvas-body .pagelayer-ai-on-dark p,
		.pagelayer-canvas-body .pagelayer-ai-on-dark li,
		.pagelayer-canvas-body .pagelayer-ai-on-dark span,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-heading-holder,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-heading-holder *,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-text-holder,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-text-holder *,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-service-heading,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-service-text,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-service-details,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-testimonial-content,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-testimonial-author,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-quote-content,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-cite-holder,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-list-item,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-counter-text,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-counter-display,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-accordion-tab,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-accordion-tabs,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-accordion-title,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-accordion-panel {
			color: #ffffff !important;
		}
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light h1,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light h2,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light h3,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light h4,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light h5,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light h6,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light p,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light li,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light span,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light .pagelayer-heading-holder,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light .pagelayer-heading-holder *,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light .pagelayer-text-holder,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light .pagelayer-text-holder *,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light .pagelayer-service-heading,
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-ai-on-light .pagelayer-service-text {
			color: #111827 !important;
		}
		/* Light bands: never leave white/near-white copy on a white/surface background. */
		.pagelayer-canvas-body .pagelayer-ai-on-light h1,
		.pagelayer-canvas-body .pagelayer-ai-on-light h2,
		.pagelayer-canvas-body .pagelayer-ai-on-light h3,
		.pagelayer-canvas-body .pagelayer-ai-on-light h4,
		.pagelayer-canvas-body .pagelayer-ai-on-light h5,
		.pagelayer-canvas-body .pagelayer-ai-on-light h6,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-heading-holder,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-heading-holder *,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-service-heading,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-counter-text,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-counter-display {
			color: var(--pagelayer-color-primary, #111827) !important;
		}
		.pagelayer-canvas-body .pagelayer-ai-on-light p,
		.pagelayer-canvas-body .pagelayer-ai-on-light li,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-text-holder,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-text-holder *,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-service-text,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-service-details,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-testimonial-content,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-testimonial-author,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-quote-content,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-cite-holder,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-list-item,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-accordion-tab,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-accordion-tabs,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-accordion-title,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-accordion-panel,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-address,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-phone,
		.pagelayer-canvas-body .pagelayer-ai-on-light .pagelayer-email {
			color: var(--pagelayer-color-text, #111827) !important;
		}
		/* Accordion / Tabs: tab chrome and panel always contrast. */
		.pagelayer-canvas-body .pagelayer-accordion-tabs,
		.pagelayer-accordion-tabs {
			background-color: #f1f5f9 !important;
			color: #111827 !important;
		}
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-accordion-tabs,
		.pagelayer-ai-on-dark .pagelayer-accordion-tabs {
			background-color: rgba(255,255,255,0.14) !important;
			color: #ffffff !important;
		}
		.pagelayer-canvas-body .pagelayer-accordion_item.active .pagelayer-accordion-tabs,
		.pagelayer-accordion_item.active .pagelayer-accordion-tabs,
		.pagelayer-canvas-body .active .pagelayer-accordion-tabs,
		.active .pagelayer-accordion-tabs {
			background-color: var(--pagelayer-color-primary, #4f46e5) !important;
			color: #ffffff !important;
		}
		.pagelayer-canvas-body .pagelayer-accordion-panel,
		.pagelayer-accordion-panel {
			background-color: #ffffff !important;
			color: #111827 !important;
		}
		.pagelayer-canvas-body .pagelayer-ai-on-dark .pagelayer-accordion-panel,
		.pagelayer-ai-on-dark .pagelayer-accordion-panel {
			background-color: rgba(255,255,255,0.08) !important;
			color: #ffffff !important;
		}
		/* Social Profile: white glyph on brand circle so icon never matches its fill. */
		.pagelayer-canvas-body .pagelayer-social_grp .pagelayer-icon-holder,
		.pagelayer-social_grp .pagelayer-icon-holder {
			background-color: var(--pagelayer-color-primary, #4f46e5) !important;
		}
		.pagelayer-canvas-body .pagelayer-social_grp .pagelayer-social-fa,
		.pagelayer-social_grp .pagelayer-social-fa {
			color: #ffffff !important;
		}
		/* AI icon / image boxes: centered icon, heading, and copy. */
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-icon,
		.pagelayer-iconbox .pagelayer-service-icon,
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-heading,
		.pagelayer-iconbox .pagelayer-service-heading,
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-details,
		.pagelayer-iconbox .pagelayer-service-details,
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-text,
		.pagelayer-iconbox .pagelayer-service-text,
		.pagelayer-canvas-body .pagelayer-service .pagelayer-service-image,
		.pagelayer-service .pagelayer-service-image,
		.pagelayer-service-image,
		.pagelayer-canvas-body .pagelayer-service .pagelayer-service-heading,
		.pagelayer-service .pagelayer-service-heading,
		.pagelayer-canvas-body .pagelayer-service .pagelayer-service-details,
		.pagelayer-service .pagelayer-service-details,
		.pagelayer-canvas-body .pagelayer-service .pagelayer-service-text,
		.pagelayer-service .pagelayer-service-text {
			text-align: center;
		}
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-heading,
		.pagelayer-iconbox .pagelayer-service-heading,
		.pagelayer-canvas-body .pagelayer-service .pagelayer-service-heading,
		.pagelayer-service .pagelayer-service-heading {
			font-weight: 700;
			line-height: 1.3;
			margin-bottom: 8px;
		}
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-text,
		.pagelayer-iconbox .pagelayer-service-text,
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-text p,
		.pagelayer-iconbox .pagelayer-service-text p,
		.pagelayer-canvas-body .pagelayer-service .pagelayer-service-text,
		.pagelayer-service .pagelayer-service-text,
		.pagelayer-canvas-body .pagelayer-service .pagelayer-service-text p,
		.pagelayer-service .pagelayer-service-text p {
			font-size: 15px;
			line-height: 1.7;
			font-weight: 400;
		}
		/* AI Image Box / Service Card: uniform image sizing and equal card height */
		.pagelayer-canvas-body .pagelayer-service .pagelayer-service-image,
		.pagelayer-service .pagelayer-service-image,
		.pagelayer-service-image {
			width: 100% !important;
			overflow: hidden !important;
			margin: 0 auto 16px auto !important;
			border-radius: 8px !important;
		}
		.pagelayer-canvas-body .pagelayer-service .pagelayer-service-image img,
		.pagelayer-service .pagelayer-service-image img,
		.pagelayer-canvas-body .pagelayer-service img.pagelayer-img,
		.pagelayer-service img.pagelayer-img,
		.pagelayer-canvas-body .pagelayer-service-image img,
		.pagelayer-service-image img {
			width: 100% !important;
			height: 220px !important;
			max-height: 220px !important;
			min-height: 220px !important;
			object-fit: cover !important;
			object-position: center !important;
			display: block !important;
			margin: 0 auto !important;
			border-radius: 8px !important;
		}
		.pagelayer-canvas-body .pagelayer-service-container,
		.pagelayer-service-container {
			display: flex !important;
			flex-direction: column !important;
			height: 100% !important;
		}
		.pagelayer-canvas-body .pagelayer-service-details,
		.pagelayer-service-details {
			display: flex !important;
			flex-direction: column !important;
			flex-grow: 1 !important;
		}
		.pagelayer-canvas-body .pagelayer-service-text,
		.pagelayer-service-text {
			flex-grow: 1 !important;
			margin-bottom: 16px !important;
		}
		.pagelayer-canvas-body .pagelayer-service-btn,
		.pagelayer-service-btn {
			margin-top: auto !important;
			align-self: center !important;
		}

		/* AI Icon Box: uniform icon sizing and equal card height */
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-icon,
		.pagelayer-iconbox .pagelayer-service-icon {
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			width: 64px !important;
			height: 64px !important;
			min-width: 64px !important;
			min-height: 64px !important;
			margin: 0 auto 16px auto !important;
			line-height: 1 !important;
		}
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-icon i,
		.pagelayer-iconbox .pagelayer-service-icon i,
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-icon svg,
		.pagelayer-iconbox .pagelayer-service-icon svg {
			font-size: 28px !important;
			width: 28px !important;
			height: 28px !important;
			line-height: 28px !important;
			display: inline-block !important;
		}
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-container,
		.pagelayer-iconbox .pagelayer-service-container {
			display: flex !important;
			flex-direction: column !important;
			height: 100% !important;
		}
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-details,
		.pagelayer-iconbox .pagelayer-service-details {
			display: flex !important;
			flex-direction: column !important;
			flex-grow: 1 !important;
		}
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-text,
		.pagelayer-iconbox .pagelayer-service-text {
			flex-grow: 1 !important;
			margin-bottom: 16px !important;
		}
		.pagelayer-canvas-body .pagelayer-iconbox .pagelayer-service-btn,
		.pagelayer-iconbox .pagelayer-service-btn {
			margin-top: auto !important;
			align-self: center !important;
		}

		/* AI Image Slider: professional constrained sizing and aspect ratio */
		.pagelayer-canvas-body .pagelayer-image_slider,
		.pagelayer-canvas-body .pagelayer-image-slider-div,
		.pagelayer-image_slider,
		.pagelayer-image-slider-div {
			max-width: 1000px !important;
			margin-left: auto !important;
			margin-right: auto !important;
			overflow: visible !important;
			border-radius: 12px !important;
		}
		.pagelayer-canvas-body .pagelayer-owl-prev,
		.pagelayer-canvas-body .pagelayer-owl-next,
		.pagelayer-owl-prev,
		.pagelayer-owl-next {
			width: 48px !important;
			height: 48px !important;
			min-width: 48px !important;
			min-height: 48px !important;
			border-radius: 50% !important;
			background: rgba(15, 23, 42, 0.72) !important;
			color: #ffffff !important;
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
			opacity: 1 !important;
			z-index: 5 !important;
		}
		.pagelayer-canvas-body .pagelayer-owl-prev span,
		.pagelayer-canvas-body .pagelayer-owl-next span,
		.pagelayer-canvas-body .pagelayer-owl-prev i,
		.pagelayer-canvas-body .pagelayer-owl-next i,
		.pagelayer-owl-prev span,
		.pagelayer-owl-next span,
		.pagelayer-owl-prev i,
		.pagelayer-owl-next i {
			font-size: 28px !important;
			line-height: 1 !important;
			color: #ffffff !important;
		}
		.pagelayer-canvas-body .pagelayer-image-slider-ul,
		.pagelayer-image-slider-ul {
			margin: 0 auto !important;
		}
		.pagelayer-canvas-body .pagelayer-slider-item,
		.pagelayer-slider-item {
			overflow: hidden !important;
			border-radius: 12px !important;
		}
		.pagelayer-canvas-body .pagelayer-image-slider-div img,
		.pagelayer-canvas-body .pagelayer-image_slider img,
		.pagelayer-canvas-body .pagelayer-slider-item img,
		.pagelayer-canvas-body .pagelayer-image-slider-ul img,
		.pagelayer-image-slider-div img,
		.pagelayer-image_slider img,
		.pagelayer-slider-item img,
		.pagelayer-image-slider-ul img {
			width: 100% !important;
			height: 420px !important;
			max-height: 440px !important;
			min-height: 280px !important;
			object-fit: cover !important;
			object-position: center !important;
			display: block !important;
			margin: 0 auto !important;
			border-radius: 12px !important;
		}
		.pagelayer-canvas-body .pagelayer-image-slider-ul[data-slides-items="2"] img,
		.pagelayer-canvas-body .pagelayer-image-slider-ul[data-slides-items="3"] img,
		.pagelayer-canvas-body .pagelayer-image-slider-ul[data-slides-items="4"] img,
		.pagelayer-image-slider-ul[data-slides-items="2"] img,
		.pagelayer-image-slider-ul[data-slides-items="3"] img,
		.pagelayer-image-slider-ul[data-slides-items="4"] img {
			height: 280px !important;
			max-height: 300px !important;
		}
		@media (max-width: 768px) {
			.pagelayer-canvas-body .pagelayer-image-slider-div img,
			.pagelayer-canvas-body .pagelayer-image_slider img,
			.pagelayer-canvas-body .pagelayer-slider-item img,
			.pagelayer-image-slider-div img,
			.pagelayer-image_slider img,
			.pagelayer-slider-item img {
				height: 260px !important;
				max-height: 280px !important;
			}
		}

		/* Multi-column Card Grids: columns stretch to equal height */
		.pagelayer-canvas-body .pagelayer-row:not(.pagelayer-row-holder) > .pagelayer-row-holder,
		.pagelayer-row:not(.pagelayer-row-holder) > .pagelayer-row-holder {
			display: flex !important;
			flex-wrap: wrap !important;
		}
		.pagelayer-canvas-body .pagelayer-col,
		.pagelayer-col {
			display: flex !important;
			flex-direction: column !important;
		}
		.pagelayer-canvas-body .pagelayer-col > .pagelayer-col-holder,
		.pagelayer-col > .pagelayer-col-holder {
			display: flex !important;
			flex-direction: column !important;
			flex-grow: 1 !important;
			height: 100% !important;
		}
		.pagelayer-canvas-body .pagelayer-col-holder > .pagelayer-ele-wrap,
		.pagelayer-col-holder > .pagelayer-ele-wrap {
			flex-grow: 1 !important;
			display: flex !important;
			flex-direction: column !important;
		}
		.pagelayer-canvas-body .pagelayer-col-holder > .pagelayer-ele-wrap > .pagelayer-ele,
		.pagelayer-col-holder > .pagelayer-ele-wrap > .pagelayer-ele {
			flex-grow: 1 !important;
			height: 100% !important;
		}

		/* AI Call To Action (pl_call): ensure robust internal padding and breathing room */
		.pagelayer-canvas-body .pagelayer-cta-content,
		.pagelayer-cta-content {
			box-sizing: border-box !important;
			padding-left: 36px !important;
			padding-right: 36px !important;
			padding-top: 32px !important;
			padding-bottom: 32px !important;
		}
		.pagelayer-canvas-body .pagelayer-cta-content-holder,
		.pagelayer-cta-content-holder {
			box-sizing: border-box !important;
			display: flex !important;
			align-items: center !important;
		}
		@media (max-width: 768px) {
			.pagelayer-canvas-body .pagelayer-cta-content,
			.pagelayer-cta-content {
				padding-left: 20px !important;
				padding-right: 20px !important;
				padding-top: 24px !important;
				padding-bottom: 24px !important;
			}
		}

		/* AI Flipbox (pl_flipbox): prevent text spilling out of the card */
		.pagelayer-canvas-body .pagelayer-flipbox-flipper,
		.pagelayer-flipbox-flipper {
			min-height: 380px !important;
			box-sizing: border-box !important;
		}
		.pagelayer-canvas-body .pagelayer-flipbox-box,
		.pagelayer-flipbox-box {
			box-sizing: border-box !important;
			overflow: hidden !important;
		}
		.pagelayer-canvas-body .pagelayer-flipbox-box-overlay,
		.pagelayer-flipbox-box-overlay {
			box-sizing: border-box !important;
			padding: 24px 28px !important;
			overflow: hidden !important;
		}
		.pagelayer-canvas-body .pagelayer-flipbox-content,
		.pagelayer-flipbox-content {
			box-sizing: border-box !important;
			width: 100% !important;
			max-width: 100% !important;
			padding: 0 !important;
			margin: 0 auto !important;
			overflow: hidden !important;
			display: flex !important;
			flex-direction: column !important;
			align-items: center !important;
			justify-content: center !important;
			word-break: break-word !important;
			overflow-wrap: break-word !important;
		}
		.pagelayer-canvas-body .pagelayer-flipbox-front-heading,
		.pagelayer-canvas-body .pagelayer-flipbox-back-heading,
		.pagelayer-flipbox-front-heading,
		.pagelayer-flipbox-back-heading {
			max-width: 100% !important;
			word-break: break-word !important;
			overflow-wrap: break-word !important;
			margin-bottom: 12px !important;
		}
		.pagelayer-canvas-body .pagelayer-flipbox-front-content,
		.pagelayer-canvas-body .pagelayer-flipbox-back-content,
		.pagelayer-flipbox-front-content,
		.pagelayer-flipbox-back-content {
			max-width: 100% !important;
			word-break: break-word !important;
			overflow-wrap: break-word !important;
			line-height: 1.5 !important;
			margin-bottom: 16px !important;
		}
		.pagelayer-canvas-body .pagelayer-flipbox-content .pagelayer-service-btn,
		.pagelayer-flipbox-content .pagelayer-service-btn {
			max-width: 100% !important;
			white-space: normal !important;
			word-break: break-word !important;
			margin-top: 8px !important;
		}

		/* Theme-template chrome: header/footer/404 must not render as a full-viewport photo hero. */
		body.pagelayer-ai-kind-header .pagelayer-row:not(.pagelayer-row-holder),
		body.pagelayer-template-header .pagelayer-row:not(.pagelayer-row-holder) {
			min-height: 0 !important;
		}
		body.pagelayer-ai-kind-footer .pagelayer-row:not(.pagelayer-row-holder),
		body.pagelayer-template-footer .pagelayer-row:not(.pagelayer-row-holder) {
			min-height: 0 !important;
		}
		body.pagelayer-ai-kind-404 .pagelayer-content,
		body.pagelayer-ai-kind-404 .entry-content {
			min-height: 70vh;
			display: flex;
			flex-direction: column;
			justify-content: center;
		}
		body.pagelayer-ai-kind-404 .pagelayer-row:not(.pagelayer-row-holder) {
			width: 100%;
		}
		body.pagelayer-ai-kind-404 .pagelayer-heading-holder h1,
		body.pagelayer-ai-kind-404 h1 {
			letter-spacing: -0.04em;
		}
	</style>
</head>
<body <?php body_class(implode(' ', $pagelayer_canvas_classes)); ?>>
<?php
if (function_exists('wp_body_open')) {
	wp_body_open();
}
if (have_posts()) {
	while (have_posts()) {
		the_post();
		the_content();
	}
}
wp_footer();
?>
</body>
</html>
