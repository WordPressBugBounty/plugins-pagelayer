<?php

if(!defined('PAGELAYER_VERSION')) {
	exit('Hacking Attempt !');
}

/**
 * Pagelayer Abilities Register
 * PageLayer AI Website Builder v1.0 MCP & WordPress Abilities Engine
 */
class Pagelayer_Abilities_Register {

	public static function init() {
		if (function_exists('wp_register_ability')) {
			self::register_categories();
			self::register_abilities();
		}
	}

	public static function register_categories() {
		if (!function_exists('wp_register_ability_category')) {
			return;
		}

		wp_register_ability_category('pagelayer-pages', array(
			'label'       => __('Pagelayer Pages', 'pagelayer'),
			'description' => __('Create, update, validate, duplicate, and publish pages built with Pagelayer.', 'pagelayer'),
		));
		wp_register_ability_category('pagelayer-posts', array(
			'label'       => __('Pagelayer Posts', 'pagelayer'),
			'description' => __('Create, update, list, duplicate, and publish individual blog posts built with Pagelayer.', 'pagelayer'),
		));
		wp_register_ability_category('pagelayer-widgets', array(
			'label'       => __('Pagelayer Widgets', 'pagelayer'),
			'description' => __('Discover widgets, schemas, controls, nesting rules, and example nodes.', 'pagelayer'),
		));
		wp_register_ability_category('pagelayer-global', array(
			'label'       => __('Pagelayer Global Styles & Presets', 'pagelayer'),
			'description' => __('Manage design systems, global colors/fonts, theme settings, icons, fonts, and presets.', 'pagelayer'),
		));
		wp_register_ability_category('pagelayer-templates', array(
			'label'       => __('Pagelayer Templates', 'pagelayer'),
			'description' => __('Manage theme builder templates (header, footer, archive, single, search, 404, popup, woocommerce).', 'pagelayer'),
		));
		wp_register_ability_category('pagelayer-menus', array(
			'label'       => __('Pagelayer Navigation Menus', 'pagelayer'),
			'description' => __('Build the WordPress nav menus that the Primary Menu / Mega Menu widgets render in headers and footers.', 'pagelayer'),
		));
		wp_register_ability_category('pagelayer-media', array(
			'label'       => __('Pagelayer Media', 'pagelayer'),
			'description' => __('Upload and manage media library assets for Pagelayer layouts.', 'pagelayer'),
		));
	}

	public static function register_abilities() {
		self::register_widget_abilities();
		self::register_global_abilities();
		self::register_template_abilities();
		self::register_menu_abilities();
		self::register_pages_abilities();
		self::register_posts_abilities();
		self::register_media_abilities();
	}

	// ------------------------------------------------------------------
	// Permission callbacks
	// ------------------------------------------------------------------

	public static function can_edit_posts() {
		return current_user_can('edit_posts');
	}

	public static function can_manage_options() {
		return current_user_can('manage_options');
	}

	// ------------------------------------------------------------------
	// Shared helpers
	// ------------------------------------------------------------------

	protected static function ensure_shortcodes_loaded() {
		global $pagelayer;
		if (empty($pagelayer->shortcodes) && function_exists('pagelayer_load_shortcodes')) {
			pagelayer_load_shortcodes();
		}
	}

	/**
	 * Pagelayer stores a global colour/font as array('title' => ..., 'value' =>
	 * ...) — customizer.php emits the CSS custom properties by reading
	 * $entry['value']. An AI client naturally sends the flat map it was asked
	 * for ({"primary": "#E50914"}), and writing that through verbatim made
	 * every --pagelayer-color-* variable render EMPTY, so every "$token" in the
	 * generated site resolved to nothing and the whole palette silently
	 * vanished. Accept both shapes and store the one the renderer reads.
	 */
	protected static function normalize_global_map($map) {
		if (!is_array($map)) {
			return array();
		}

		$out = array();
		foreach ($map as $key => $entry) {
			if (is_array($entry)) {
				// Already in storage shape (or close enough) — keep it, but make
				// sure the keys the renderer needs are present.
				if (!isset($entry['value'])) {
					continue;
				}
				$out[$key] = array(
					'title' => isset($entry['title']) ? $entry['title'] : ucwords(str_replace('_', ' ', $key)),
					'value' => $entry['value'],
				);
				continue;
			}

			$out[$key] = array(
				'title' => ucwords(str_replace('_', ' ', $key)),
				'value' => $entry,
			);
		}

		return $out;
	}

	public static function maybe_update_global_styles($input) {
		if (isset($input['global_colors'])) {
			update_option('pagelayer_global_colors', json_encode(self::normalize_global_map($input['global_colors'])));
		}
		if (isset($input['global_fonts'])) {
			update_option('pagelayer_global_fonts', json_encode(self::normalize_global_map($input['global_fonts'])));
		}
		if (isset($input['content_width'])) {
			update_option('pagelayer_content_width', sanitize_text_field($input['content_width']));
		}
	}

	// ------------------------------------------------------------------
	// Widget Schema Extractor & Examples
	// ------------------------------------------------------------------

	public static function extract_widget_schema($tag, $data) {
		global $pagelayer;

		$schema = array(
			'id'             => $tag,
			'name'           => isset($data['name']) ? $data['name'] : $tag,
			'group'          => isset($data['group']) ? $data['group'] : 'misc',
			'html'           => isset($data['html']) ? $data['html'] : '',
			'holder'         => isset($data['holder']) ? $data['holder'] : '',
			'innerHTML'      => isset($data['innerHTML']) ? $data['innerHTML'] : '',
			'parent'         => isset($data['parent']) ? $data['parent'] : array(),
			'has_group'      => isset($data['has_group']) ? $data['has_group'] : array(),
			'skip_props_cat' => isset($data['skip_props_cat']) ? $data['skip_props_cat'] : array(),
			'skip_props'     => isset($data['skip_props']) ? $data['skip_props'] : array(),
			'sections'       => array(),
		);

		$settings_tabs = isset($data['settings']) ? $data['settings'] : array();
		$options       = isset($data['options']) ? $data['options'] : array();
		$section_keys  = array();

		if (!empty($pagelayer->tabs) && is_array($pagelayer->tabs)) {
			foreach ($pagelayer->tabs as $tab) {
				if (empty($data[$tab]) || !is_array($data[$tab])) {
					continue;
				}
				foreach ($data[$tab] as $section_key => $section_label) {
					$section_keys[] = $section_key;
				}
			}
		}

		foreach ($section_keys as $section_key) {
			$props = array();
			if (isset($data[$section_key]) && is_array($data[$section_key])) {
				$props = $data[$section_key];
			} elseif (isset($pagelayer->styles[$section_key]) && is_array($pagelayer->styles[$section_key])) {
				$props = $pagelayer->styles[$section_key];
			}

			if (empty($props)) {
				continue;
			}

			$clean_props = array();
			foreach ($props as $prop_key => $prop_def) {
				if (!is_array($prop_def)) {
					$clean_props[$prop_key] = array('label' => $prop_def);
					continue;
				}

				$clean_prop = array(
					'type'    => isset($prop_def['type']) ? $prop_def['type'] : '',
					'label'   => isset($prop_def['label']) ? $prop_def['label'] : '',
					'default' => isset($prop_def['default']) ? $prop_def['default'] : null,
				);

				if (isset($prop_def['list']) && is_array($prop_def['list'])) {
					$clean_prop['allowed_values'] = $prop_def['list'];
				}
				if (isset($prop_def['min']))   $clean_prop['min']   = $prop_def['min'];
				if (isset($prop_def['max']))   $clean_prop['max']   = $prop_def['max'];
				if (isset($prop_def['step']))  $clean_prop['step']  = $prop_def['step'];
				if (isset($prop_def['units'])) $clean_prop['units'] = $prop_def['units'];

				if (isset($prop_def['screen'])) $clean_prop['responsive']    = (bool)$prop_def['screen'];
				if (isset($prop_def['req']))    $clean_prop['requires']      = $prop_def['req'];
				if (isset($prop_def['show']))   $clean_prop['show_when']     = $prop_def['show'];
				if (isset($prop_def['edit']))   $clean_prop['edit_selector'] = $prop_def['edit'];
				if (isset($prop_def['desc']))   $clean_prop['desc']          = $prop_def['desc'];

				$clean_props[$prop_key] = $clean_prop;
			}

			$schema['sections'][$section_key] = array(
				'label'      => isset($settings_tabs[$section_key]) ? $settings_tabs[$section_key] : (isset($options[$section_key]) ? $options[$section_key] : ucfirst($section_key)),
				'properties' => $clean_props,
			);
		}

		if (isset($pagelayer->default_params[$tag])) {
			$schema['default_attrs'] = $pagelayer->default_params[$tag];
		}

		return $schema;
	}

	// ------------------------------------------------------------------
	// Token-compaction layer
	//
	// extract_widget_schema() stays full-fidelity because the server-side
	// quality gate (widget_attr_rules) validates against every section. What
	// follows only trims the OUTPUT that crosses the wire to the AI client.
	//
	// The measured problem: a single get_widget_schema call returned ~27KB, of
	// which ~25KB was the ten style sections that pagelayer_add_shortcode()
	// bolts onto all 125 widgets identically (motion_effects alone is 11KB /
	// 53 props). Sending that per widget re-teaches the model the same
	// boilerplate every call. Now the widget's OWN sections go out by default
	// and the shared ones are fetched once via get_common_styles.
	// ------------------------------------------------------------------

	/**
	 * One property rendered as a single compact string instead of an object:
	 *   "select|def:left|opts:left,center,right|resp"
	 *   "color|req:ele_bg_type=color"
	 * The legend travels once per response (see compact_legend), not per prop,
	 * so the per-property cost drops from ~120 bytes of JSON scaffolding to ~30.
	 */
	protected static function compact_prop($prop) {
		$parts = array();
		$parts[] = !empty($prop['type']) ? $prop['type'] : 'text';

		if (isset($prop['default']) && $prop['default'] !== '' && $prop['default'] !== null) {
			$def = is_array($prop['default']) ? json_encode($prop['default']) : (string)$prop['default'];
			if (strlen($def) > 40) {
				$def = substr($def, 0, 40) . '…';
			}
			$parts[] = 'def:' . $def;
		}

		if (!empty($prop['allowed_values']) && is_array($prop['allowed_values'])) {
			// Lists are either value=>label maps or plain value lists; the model
			// only needs the values it is allowed to send.
			$vals = array_values(array_filter(array_keys($prop['allowed_values']), 'strlen'));
			if (empty($vals) || $vals === range(0, count($prop['allowed_values']) - 1)) {
				$vals = array_values($prop['allowed_values']);
			}
			$vals = array_map(function($v) { return is_scalar($v) ? (string)$v : ''; }, $vals);
			$parts[] = 'opts:' . implode(',', array_filter($vals, 'strlen'));
		}

		if (isset($prop['min']) || isset($prop['max'])) {
			$range = (isset($prop['min']) ? $prop['min'] : '') . '-' . (isset($prop['max']) ? $prop['max'] : '');
			if (!empty($prop['units'])) {
				$units = is_array($prop['units']) ? implode('/', $prop['units']) : $prop['units'];
				$range .= $units;
			}
			$parts[] = $range;
		}

		// The render-time gate. This one is never dropped, however compact the
		// output gets: an attribute sent without its companion is silently
		// discarded and the page renders unstyled with no error.
		if (!empty($prop['requires']) && is_array($prop['requires'])) {
			$req = array();
			foreach ($prop['requires'] as $k => $v) {
				$req[] = $k . '=' . (is_array($v) ? implode('/', $v) : $v);
			}
			$parts[] = 'req:' . implode('&', $req);
		}

		if (!empty($prop['responsive'])) {
			$parts[] = 'resp';
		}

		return implode('|', $parts);
	}

	protected static function compact_legend() {
		return 'prop format "type|def:X|opts:a,b|min-maxunit|req:attr=val|resp". '
			. 'req = companion attr that must ALSO be set explicitly on the same node, else Pagelayer discards this property at render and the page looks unstyled with no error. '
			. '"a/b" = any one of those values, "&" = all conditions must hold, and a leading "!" negates ("req:!view=default" means view must not be default). '
			. 'resp = also accepts _tablet and _mobile suffixed siblings.';
	}

	/**
	 * Section keys the widget itself declares (its `settings` tab) versus the
	 * ten global style sections shared by every widget.
	 */
	protected static function own_section_keys($tag) {
		global $pagelayer;
		self::ensure_shortcodes_loaded();
		$data = isset($pagelayer->shortcodes[$tag]) ? $pagelayer->shortcodes[$tag] : array();
		return isset($data['settings']) && is_array($data['settings']) ? array_keys($data['settings']) : array();
	}

	/**
	 * Compact a full schema for transport.
	 *
	 * $mode 'own'    - only the widget's own sections (default, ~95% smaller)
	 *       'all'    - own + shared style sections
	 *       'shared' - only the shared style sections
	 * $only - optional explicit list of section keys, overrides $mode.
	 */
	public static function compact_widget_schema($schema, $mode = 'own', $only = array()) {
		$tag  = $schema['id'];
		$own  = self::own_section_keys($tag);
		$out  = array(
			'id'    => $tag,
			'name'  => $schema['name'],
			'group' => $schema['group'],
		);

		if (!empty($schema['parent'])) {
			$out['must_be_inside'] = $schema['parent'];
		}
		if (!empty($schema['holder']) || !empty($schema['has_group'])) {
			$out['accepts_children'] = true;
		}
		if (!empty($schema['innerHTML'])) {
			// The one genuinely load-bearing quirk of the node format: this
			// widget's main text goes in the node's "content" field, not attrs.
			$out['content_attr'] = $schema['innerHTML'];
			$out['content_note'] = 'Main text goes in the node "content" field, not in attrs.' . $schema['innerHTML'] . '.';
		}
		if (!empty($schema['skip_props'])) {
			$out['unsupported_props'] = $schema['skip_props'];
		}

		$sections = array();
		foreach ($schema['sections'] as $key => $section) {
			$is_own = in_array($key, $own, true);

			if (!empty($only)) {
				if (!in_array($key, $only, true)) {
					continue;
				}
			} elseif ($mode === 'own' && !$is_own) {
				continue;
			} elseif ($mode === 'shared' && $is_own) {
				continue;
			}

			$props = array();
			foreach ($section['properties'] as $prop_key => $prop) {
				// _hover variants double the payload and are almost never what a
				// text/content edit needs; get_widget_schema(sections:[...]) still
				// surfaces them when explicitly asked for.
				if ($mode === 'own' && strpos($prop_key, '_hover') !== false) {
					continue;
				}
				$props[$prop_key] = self::compact_prop($prop);
			}
			$sections[$key] = $props;
		}

		$out['props'] = $sections;

		if (empty($only) && $mode === 'own') {
			$shared = array_values(array_diff(array_keys($schema['sections']), $own));
			if (!empty($shared)) {
				$out['shared_style_sections'] = $shared;
				$out['shared_note'] = 'These ' . count($shared) . ' sections are identical on every widget and are omitted here. Call get_common_styles ONCE per session for them, or get_widget_schema with sections:["ele_bg_styles"] for one of them.';
			}
		}

		$out['legend'] = self::compact_legend();

		return $out;
	}

	public static function get_all_widget_schemas() {
		global $pagelayer;
		self::ensure_shortcodes_loaded();

		$schemas = array();
		if (!empty($pagelayer->shortcodes) && is_array($pagelayer->shortcodes)) {
			foreach ($pagelayer->shortcodes as $tag => $data) {
				$schemas[$tag] = self::extract_widget_schema($tag, $data);
			}
		}
		return $schemas;
	}

	/**
	 * Every attribute name a widget really accepts, plus the render-time
	 * dependency each one is gated behind.
	 *
	 * Pagelayer walks the same sections at render time and does two things that
	 * make bad attrs invisible rather than loud (shortcode_functions.php ~157-245):
	 *   1. an attribute whose name is not in this map is never looked at;
	 *   2. an attribute whose `req` is not satisfied by another EXPLICITLY SET
	 *      attribute is unset before any CSS is generated — widget defaults are
	 *      NOT merged in first, so e.g. ele_bg_color does nothing unless
	 *      ele_bg_type=color travels with it, and btn_bg_color does nothing
	 *      unless type=pagelayer-btn-custom travels with it.
	 * Both cases render a perfectly valid-looking page with none of the styling
	 * that was asked for, which is why they are reported as hard errors.
	 *
	 * Returns null for tags that have no registered schema.
	 */
	public static function widget_attr_rules($tag) {
		global $pagelayer;
		static $cache = array();

		// pl_inner_row/pl_inner_col are rendered through the pl_row/pl_col schema.
		$lookup = str_replace(array('pl_inner_row', 'pl_inner_col'), array('pl_row', 'pl_col'), $tag);

		if (array_key_exists($lookup, $cache)) {
			return $cache[$lookup];
		}

		self::ensure_shortcodes_loaded();
		if (empty($pagelayer->shortcodes[$lookup])) {
			return $cache[$lookup] = null;
		}

		$schema = self::extract_widget_schema($lookup, $pagelayer->shortcodes[$lookup]);
		$rules  = array('allowed' => array(), 'req' => array());

		foreach ($schema['sections'] as $section) {
			foreach ($section['properties'] as $key => $prop) {
				$rules['allowed'][$key] = isset($prop['type']) ? $prop['type'] : '';

				if (!empty($prop['requires']) && is_array($prop['requires'])) {
					$rules['req'][$key] = $prop['requires'];
				}

				// Responsive props accept _tablet / _mobile siblings.
				if (!empty($prop['responsive'])) {
					$rules['allowed'][$key . '_tablet'] = $rules['allowed'][$key];
					$rules['allowed'][$key . '_mobile'] = $rules['allowed'][$key];
				}
			}
		}

		return $cache[$lookup] = $rules;
	}

	public static function get_widget_schema($widget_id) {
		global $pagelayer;
		self::ensure_shortcodes_loaded();

		if (!isset($pagelayer->shortcodes[$widget_id])) {
			return null;
		}
		return self::extract_widget_schema($widget_id, $pagelayer->shortcodes[$widget_id]);
	}

	/**
	 * Canonical JSON node examples, derived LIVE from each widget's own
	 * registered schema (same source as extract_widget_schema/get_widget_schema)
	 * instead of a hand-maintained list. A hand-written example silently
	 * drifts from the real widget params (e.g. pl_iconbox's real fields are
	 * service_heading/service_text/service_icon_color, not title/desc/icon_color)
	 * and any AI that trusts the wrong field name ends up setting nothing —
	 * the widget then renders its own built-in default text/icon instead.
	 * Deriving examples from the live schema makes that class of bug
	 * impossible and automatically covers every widget, not just a curated few.
	 */
	public static function get_widget_examples($widget_id = null) {
		self::ensure_shortcodes_loaded();
		global $pagelayer;

		$examples = array();

		if ($widget_id) {
			if (isset($pagelayer->shortcodes[$widget_id])) {
				$examples[$widget_id] = self::build_widget_example($widget_id, $pagelayer->shortcodes[$widget_id]);
			}
			return $examples;
		}

		if (!empty($pagelayer->shortcodes) && is_array($pagelayer->shortcodes)) {
			foreach ($pagelayer->shortcodes as $tag => $data) {
				$examples[$tag] = self::build_widget_example($tag, $data);
			}
		}

		// One verified, hand-checked nesting example — schema extraction only
		// yields flat single-widget examples, so this is kept separately to
		// still demonstrate the Row > Column > Widget hierarchy in practice.
		$examples['_structure_example'] = array(
			'tag' => 'pl_row',
			// ele_bg_type=color is mandatory alongside ele_bg_color, and "$bg" only
			// resolves if a global color with the key "bg" actually exists —
			// unknown keys silently fall back to $primary.
			'attrs' => array('stretch' => 'full', 'ele_bg_type' => 'color', 'ele_bg_color' => '$primary', 'ele_padding' => '80px,0px,80px,0px'),
			'content' => array(
				array(
					'tag' => 'pl_col',
					'attrs' => array('col' => 12),
					'content' => array(
						array(
							'tag' => 'pl_heading',
							'attrs' => array('align' => 'center', 'color' => '$primary'),
							'content' => '<h1>Real, on-topic headline for this section</h1>',
						),
					),
				),
			),
		);

		return $examples;
	}

	/**
	 * Build one widget's example node from its live schema. Content-bearing
	 * fields (text/textarea/editor) get an instructional placeholder rather
	 * than the widget's own built-in default — copying the widget's real
	 * default verbatim would just recreate the "This is Icon Box" problem.
	 */
	protected static function build_widget_example($tag, $data) {
		$schema    = self::extract_widget_schema($tag, $data);
		$inner_key = isset($data['innerHTML']) ? $data['innerHTML'] : '';

		// Only the widget's OWN settings sections. The `options` tab holds the ten
		// global style sections that pagelayer_add_shortcode() bolts onto every
		// single widget (background, border, font, position, animation, motion,
		// responsive, attributes, custom CSS) — including them made a single
		// widget's "example" hundreds of attrs long with every colour prop set to
		// $primary, which is both unreadable and terrible design advice.
		$own_sections = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : array();

		$attrs = array();
		foreach ($schema['sections'] as $section_key => $section) {
			if (!isset($own_sections[$section_key])) {
				continue;
			}
			foreach ($section['properties'] as $key => $prop) {
				$type = isset($prop['type']) ? $prop['type'] : '';
				if (strpos($key, '_hover') !== false) {
					continue;
				}

				// Props gated behind a `req` need their companion attr set too, or
				// Pagelayer discards them at render. Leave them out of the example
				// rather than modelling a combination that silently does nothing.
				if (!empty($prop['requires'])) {
					continue;
				}

				if (in_array($type, array('text', 'textarea', 'editor'), true)) {
					$label       = !empty($prop['label']) ? $prop['label'] : $key;
					$attrs[$key] = '<real, unique, on-topic ' . $label . ' — never leave this as the widget default>';
				} elseif ($type === 'color') {
					$attrs[$key] = '$primary';
				} elseif ($type === 'icon') {
					$attrs[$key] = !empty($prop['default']) ? $prop['default'] : 'fas fa-star';
				} elseif ($type === 'image') {
					$attrs[$key] = '<image URL from search_images, or a WP attachment ID — omit the attr entirely to leave the builder placeholder>';
				} elseif ($type === 'link') {
					$attrs[$key] = '#';
				}
			}
		}

		$example = array('tag' => $tag, 'attrs' => $attrs);

		if ($inner_key && isset($attrs[$inner_key])) {
			$example['content'] = $attrs[$inner_key];
			unset($example['attrs'][$inner_key]);
			$example['_note'] = 'This widget\'s main text is bound to the "' . $inner_key . '" attr but should be supplied via the node\'s "content" field (it is copied into that attr at render time) — do not also duplicate it as an attrs key.';
		}

		return $example;
	}

	// ------------------------------------------------------------------
	// Layout normalization & serialization
	// ------------------------------------------------------------------

	// ------------------------------------------------------------------
	// Section shorthand
	// ------------------------------------------------------------------
	//
	// Writing a page out as raw nodes costs the model ~300 tokens per section,
	// and generating those tokens is where nearly all the wall-clock time of a
	// site build goes (the PHP side of create_page is ~1.5 ms). A section spec
	// carries only the content — {"section":"features","heading":"...","items":
	// [...]} — and PHP expands it here into the same node tree it would have
	// written by hand, using attribute names verified against the live widget
	// schemas. Roughly 6-10x fewer output tokens per section, and no chance of
	// inventing an attribute that fails the quality gate.
	//
	// Raw nodes still work exactly as before; the two can be mixed in one page.

	public static function section_presets() {
		return array(
			'hero'         => 'Full-width opening section. {heading*, sub, cta:{text,link}, cta2:{text,link}, image (url, sits in a second column), align:"left"|"center"}',
			'features'     => 'Icon cards grid. {heading, sub, items*:[{icon:"fas fa-bolt", title, text}], columns:2-4 (default 3)}',
			'about'        => 'Image + copy split. {heading*, text, image, cta, flip:true to put the image first}',
			'stats'        => 'Animated counters. {heading, items*:[{number, label, prefix, suffix}]}',
			'testimonials' => 'Quote cards. {heading, items*:[{quote, name, role, avatar}]}',
			'faq'          => 'Accordion. {heading, items*:[{q, a}]}',
			'cta'          => 'Closing call to action. {heading*, text, cta:{text,link}}',
			'team'         => 'Photo cards. {heading, items*:[{name, role, text, image}]}',
		);
	}

	/**
	 * Replaces every {"section": ...} spec in a node list with real nodes,
	 * recursing into container content so a spec nested in a column also works.
	 */
	public static function expand_sections($nodes) {
		if (!is_array($nodes)) {
			return $nodes;
		}

		$out = array();
		foreach ($nodes as $node) {
			if (is_array($node) && !empty($node['section']) && is_string($node['section'])) {
				foreach (self::expand_section($node) as $expanded) {
					$out[] = $expanded;
				}
				continue;
			}
			if (is_array($node) && isset($node['content']) && is_array($node['content'])) {
				$node['content'] = self::expand_sections($node['content']);
			}
			$out[] = $node;
		}

		return $out;
	}

	protected static function sec_str($spec, $key, $default = '') {
		return isset($spec[$key]) && is_string($spec[$key]) && $spec[$key] !== '' ? $spec[$key] : $default;
	}

	/**
	 * Whether the section sits on a dark background, so text has to invert.
	 * Callers can be explicit with "dark": true|false.
	 */
	protected static function sec_is_dark($spec) {
		if (isset($spec['dark'])) {
			return !empty($spec['dark']);
		}

		$bg = self::sec_str($spec, 'bg');
		if ($bg === '') {
			return !empty($spec['bg_image']);
		}

		$colors = json_decode((string) get_option('pagelayer_global_colors', ''), true);

		return self::bg_is_dark($bg, is_array($colors) ? $colors : array());
	}

	/**
	 * Is this background dark enough that text must invert?
	 *
	 * Kept free of WordPress so it can be exercised directly — see
	 * test-sec-is-dark.php.
	 *
	 * A "$token" MUST be resolved against the live palette before it is judged.
	 * This used to guess from the token NAME, treating only $primary/$secondary
	 * as dark and everything else as light. A site whose palette defined
	 * light_bg as #18181C therefore got white cards, red headings and
	 * theme-default body copy on a near-black band — invisible text, and
	 * silently so, because every attribute involved is schema-valid and the
	 * quality gate has nothing to complain about.
	 *
	 * @param string               $bg     Hex colour or "$token".
	 * @param array<string,string> $colors The global_colors palette.
	 * @return bool
	 */
	public static function bg_is_dark($bg, $colors = array()) {
		$bg = trim((string) $bg);

		if (strpos($bg, '$') === 0) {
			// Stored palettes are array('title','value'); callers and tests may
			// pass the flat map instead. Accept both.
			$resolve = function ($key) use ($colors) {
				if (!isset($colors[$key])) {
					return '';
				}
				$entry = $colors[$key];
				if (is_array($entry)) {
					return isset($entry['value']) && is_string($entry['value']) ? $entry['value'] : '';
				}
				return is_string($entry) ? $entry : '';
			};

			$key   = substr($bg, 1);
			$value = $resolve($key);

			// An undefined key does not error — Pagelayer resolves it to
			// primary at render, so judge the colour that will actually paint.
			if ($value === '') {
				$value = $resolve('primary');
			}

			if ($value === '') {
				// No palette to consult: a brand-coloured band, assume dark as
				// this function always has.
				return true;
			}

			$bg = $value;
		}

		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $bg)) {
			$hex = ltrim($bg, '#');
			if (strlen($hex) === 3) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$lum = (0.299 * hexdec(substr($hex, 0, 2)) + 0.587 * hexdec(substr($hex, 2, 2)) + 0.114 * hexdec(substr($hex, 4, 2))) / 255;
			return $lum < 0.55;
		}

		return false;
	}

	/**
	 * A heading node. Also used for body copy on dark sections: pl_text has no
	 * colour control of its own (its only param is the editor field), so white
	 * paragraph copy has to come from pl_heading, which does have "color",
	 * carrying <p> markup.
	 */
	/**
	 * Heading node.
	 *
	 * Sizing MUST go through heading_typo, not font_size. font_size renders as
	 * `{{element}}{font-size:..}` — the wrapper div only — so the <h1> inside
	 * kept the theme's own h1 rule and rendered at the theme's size: a 54px
	 * headline came out around 90px, overflowed its column and collided with
	 * the paragraph beneath it. heading_typo targets
	 * `.pagelayer-heading-holder` and its children with !important, which is
	 * the element that actually carries the text.
	 *
	 * heading_typo is not a responsive prop (no _tablet/_mobile sibling), and
	 * its !important would beat any wrapper-level override anyway, so the
	 * smaller breakpoints go through ele_css — the sanctioned escape hatch,
	 * used here precisely because no control covers responsive heading type.
	 */
	protected static function sec_heading($html, $color, $align = '', $size = '', $weight = '700') {
		$attrs = array('color' => $color);
		if ($align !== '') {
			$attrs['align'] = $align;
		}

		if ($size === '') {
			return array('tag' => 'pl_heading', 'attrs' => $attrs, 'content' => $html);
		}

		$px = (int) preg_replace('/[^0-9]/', '', (string) $size);
		$lh = $px >= 30 ? '1.15' : '1.6';

		// Comma-joined, 11 fixed positions:
		// family,size,style,weight,variant,decoration-line,decoration-style,
		// line-height(em),text-transform,letter-spacing,word-spacing.
		// Positions left blank inherit from the theme, which is what we want for
		// family and transform — only size, weight and leading are ours to set.
		$attrs['heading_typo'] = implode(',', array('', $px, '', $weight, '', '', '', $lh, '', '', ''));

		$tablet = self::scale_type($size, 0.78);
		$mobile = self::scale_type($size, 0.60);
		$sel    = '{{element}} .pagelayer-heading-holder, {{element}} .pagelayer-heading-holder *';

		$attrs['ele_css'] = '@media (max-width:780px){' . $sel . '{font-size:' . $tablet . 'px !important}}'
			. '@media (max-width:480px){' . $sel . '{font-size:' . $mobile . 'px !important}}';

		return array('tag' => 'pl_heading', 'attrs' => $attrs, 'content' => $html);
	}

	/**
	 * Smaller-screen type size. Scales down but never below a readable floor,
	 * so body copy (17px) stays legible while a 54px display headline drops far
	 * enough to fit a phone.
	 */
	protected static function scale_type($size, $factor, $min = 15) {
		$px = (int) preg_replace('/[^0-9]/', '', (string) $size);
		if ($px <= 0) {
			return $size;
		}
		return max($min, (int) round($px * $factor));
	}

	protected static function sec_body($text, $dark, $align = '') {
		$html = (strpos($text, '<') === 0) ? $text : '<p>' . $text . '</p>';

		// pl_text is the natural widget for body copy but it has no colour and
		// no alignment control of its own — in the builder those come from the
		// editor toolbar, i.e. inline CSS, which is exactly what we may not
		// emit. So centred or on-dark copy is rendered through pl_heading
		// (which does have color/align) carrying <p> markup.
		if ($dark || ($align !== '' && $align !== 'left')) {
			return self::sec_heading($html, $dark ? '#ffffff' : '$text', $align, '17', '400');
		}

		return array('tag' => 'pl_text', 'attrs' => array('font_size' => '17', 'line_height' => '1.7'), 'content' => $html);
	}

	protected static function sec_btn($cta, $dark, $align = '', $secondary = false) {
		if (!is_array($cta) || empty($cta['text'])) {
			return null;
		}

		$attrs = array(
			'text'        => (string) $cta['text'],
			'link'        => isset($cta['link']) ? (string) $cta['link'] : '#',
			'type'        => 'pagelayer-btn-custom',
			'font_weight' => '600',
			// "size" defaults to pagelayer-btn-large in the widget, but a node
			// built here carries only the attrs set explicitly — the default is
			// never merged in, so the button rendered with no size class at all
			// and came out as a tiny bordered scrap of text.
			'size'        => 'pagelayer-btn-large',
			'font_size'   => '16',
		);

		if ($secondary) {
			$attrs['btn_bg_color']     = 'rgba(0,0,0,0)';
			$attrs['btn_color']        = $dark ? '#ffffff' : '$primary';
			$attrs['btn_border_type']  = 'solid';
			$attrs['btn_border_width'] = '2px,2px,2px,2px';
			$attrs['btn_border_color'] = $dark ? '#ffffff' : '$primary';
		} else {
			$attrs['btn_bg_color'] = $dark ? '#ffffff' : '$primary';
			$attrs['btn_color']    = $dark ? '$primary' : '#ffffff';
		}

		if ($align !== '') {
			$attrs['align'] = $align;
		}

		return array('tag' => 'pl_btn', 'attrs' => $attrs);
	}

	protected static function sec_col($col, $content, $extra = array()) {
		return array('tag' => 'pl_col', 'attrs' => array_merge(array('col' => $col), $extra), 'content' => $content);
	}

	/**
	 * The card treatment for a grid item (feature, testimonial, team member).
	 *
	 * The card visual lives on the COLUMN, and columns are `width: 33.333%` with
	 * `box-sizing: border-box` — so adjacent cards are flush and the row reads as
	 * one continuous slab rather than a set of cards. Margin cannot fix that: it
	 * sits outside the width and tips the row past 100%, wrapping the grid.
	 *
	 * A border does sit inside border-box, so a transparent border of the gap
	 * width plus `background-clip: padding-box` (which stops the background at
	 * the padding edge instead of running under the border) produces a real gap
	 * and cannot disturb the column math. col_gap is not usable for this — it
	 * pads `.pagelayer-col-holder` INSIDE the card, insetting the contents while
	 * leaving the cards themselves touching.
	 */
	protected static function sec_card_style($dark, $gap = '12px') {
		return array(
			'ele_bg_type'   => 'color',
			'ele_bg_color'  => $dark ? 'rgba(255,255,255,0.08)' : '#ffffff',
			'ele_padding'   => '32px,28px,32px,28px',
			// box_shadow is "x,y,blur,color,spread,inset" split on commas, so an
			// rgba() colour tears itself apart mid-value and emits
			// "box-shadow: 0px 8px 24px 23px rgba(15 42" — invalid, dropped, and
			// the cards had no shadow at all. 8-digit hex carries the alpha
			// without commas; the renderer converts it via hex8_to_rgba().
			'ele_shadow'    => '0,8,24,#0f172a14,0,',
			'border_radius' => '10px,10px,10px,10px',
			'border_type'   => 'solid',
			'border_width'  => $gap . ',' . $gap . ',' . $gap . ',' . $gap,
			'border_color'  => 'transparent',
			'ele_css'       => '{{element}}{background-clip:padding-box}',
		);
	}

	/**
	 * The section row wrapper: background, generous desktop padding and a
	 * tighter mobile override so a generated page is not a wall of whitespace
	 * on a phone.
	 */
	protected static function sec_row($spec, $cols) {
		$attrs = array(
			'stretch'            => 'full',
			'ele_padding'        => self::sec_str($spec, 'padding', '80px,20px,80px,20px'),
			'ele_padding_mobile' => self::sec_str($spec, 'padding_mobile', '48px,16px,48px,16px'),
		);

		$bg_image = self::sec_str($spec, 'bg_image');
		$bg       = self::sec_str($spec, 'bg');

		if ($bg_image !== '') {
			$attrs['ele_bg_type'] = 'image';
			$attrs['ele_bg_img']  = $bg_image;
			if ($bg !== '') {
				$attrs['ele_bg_overlay_type']  = 'color';
				$attrs['ele_bg_overlay_color'] = $bg;
			}
		} elseif ($bg !== '') {
			$attrs['ele_bg_type']  = 'color';
			$attrs['ele_bg_color'] = $bg;
		}

		if (!empty($spec['anchor'])) {
			$attrs['ele_id'] = sanitize_title($spec['anchor']);
		}

		return array('tag' => 'pl_row', 'attrs' => $attrs, 'content' => $cols);
	}

	/**
	 * Section heading + optional sub-heading as a full-width column, so the
	 * item columns below it wrap onto the next flex line.
	 */
	protected static function sec_header_col($spec, $dark, $align = 'center') {
		$heading = self::sec_str($spec, 'heading');
		$sub     = self::sec_str($spec, 'sub');

		if ($heading === '' && $sub === '') {
			return null;
		}

		$content = array();
		if ($heading !== '') {
			$content[] = self::sec_heading('<h2>' . $heading . '</h2>', $dark ? '#ffffff' : '$primary', $align, '38');
		}
		if ($sub !== '') {
			$content[] = self::sec_body($sub, $dark, $align);
		}

		return self::sec_col(12, $content, array('ele_padding' => '0px,0px,32px,0px'));
	}

	protected static function sec_items($spec) {
		return isset($spec['items']) && is_array($spec['items']) ? $spec['items'] : array();
	}

	/**
	 * One section spec -> real nodes. Every attribute used here is checked
	 * against the widget's live schema by the test sweep, so an expanded
	 * section always passes the quality gate.
	 */
	public static function expand_section($spec) {
		$type = strtolower(trim($spec['section']));
		$dark = self::sec_is_dark($spec);
		$cols = array();

		switch ($type) {
			case 'hero':
				$align   = self::sec_str($spec, 'align', self::sec_str($spec, 'image') !== '' ? 'left' : 'center');
				$image   = self::sec_str($spec, 'image');
				$content = array();

				$heading = self::sec_str($spec, 'heading');
				if ($heading !== '') {
					$content[] = self::sec_heading('<h1>' . $heading . '</h1>', $dark ? '#ffffff' : '$primary', $align, '54');
				}
				if (self::sec_str($spec, 'sub') !== '') {
					$content[] = self::sec_body(self::sec_str($spec, 'sub'), $dark, $align);
				}

				$btns = array();
				$b1 = self::sec_btn(isset($spec['cta']) ? $spec['cta'] : null, $dark, $align);
				$b2 = self::sec_btn(isset($spec['cta2']) ? $spec['cta2'] : null, $dark, $align, true);
				if ($b1) { $btns[] = $b1; }
				if ($b2) { $btns[] = $b2; }

				if (count($btns) === 2) {
					// Side by side, each in its own inner column.
					$content[] = array('tag' => 'pl_inner_row', 'content' => array(
						array('tag' => 'pl_inner_col', 'attrs' => array('col' => 6), 'content' => array($btns[0])),
						array('tag' => 'pl_inner_col', 'attrs' => array('col' => 6), 'content' => array($btns[1])),
					));
				} elseif (!empty($btns)) {
					$content[] = $btns[0];
				}

				if ($image !== '') {
					$cols[] = self::sec_col(6, $content);
					$cols[] = self::sec_col(6, array(
						array('tag' => 'pl_image', 'attrs' => array('id' => $image, 'id-alt' => $heading !== '' ? $heading : 'Hero image', 'align' => 'center')),
					));
				} else {
					$cols[] = self::sec_col(12, $content);
				}
				break;

			case 'features':
				$items   = self::sec_items($spec);
				$columns = isset($spec['columns']) ? max(1, min(4, (int) $spec['columns'])) : 3;
				$width   = (int) floor(12 / $columns);
				$header  = self::sec_header_col($spec, $dark);
				if ($header) { $cols[] = $header; }

				$item_align = self::sec_str($spec, 'item_align', 'left');
				// With a left/right aligned icon the glyph sits inline against
				// the title, and icon spacing has no default — so the two ran
				// together with no gap at all.
				$icon_gap = ($item_align === 'top') ? ',,14px,' : ',14px,,';

				foreach ($items as $item) {
					if (!is_array($item)) { continue; }
					$cols[] = self::sec_col($width, array(array(
						'tag' => 'pl_iconbox',
						'attrs' => array(
							'service_icon_spacing' => $icon_gap,
							'service_icon'       => self::sec_str($item, 'icon', 'fas fa-check'),
							'service_icon_color' => $dark ? '#ffffff' : '$primary',
							'service_heading'    => self::sec_str($item, 'title'),
							// Only the icon was coloured, so on a dark card the
							// title kept the theme's dark default and was
							// effectively invisible against it.
							'service_heading_color' => $dark ? '#ffffff' : '$primary',
							'service_text'       => self::sec_str($item, 'text'),
							'service_alignment'  => $item_align,
							// The card's body copy has no colour prop of its own
							// — only the heading and icon do — so on a dark card
							// it kept the theme's dark default and read as a
							// barely-visible grey. ele_css is the only route.
							'ele_css' => $dark ? '{{element}} .pagelayer-service-text{color:rgba(255,255,255,0.72)}' : '',
						),
					)), self::sec_card_style($dark));
				}
				break;

			case 'about':
				$image   = self::sec_str($spec, 'image');
				$content = array();
				if (self::sec_str($spec, 'heading') !== '') {
					$content[] = self::sec_heading('<h2>' . self::sec_str($spec, 'heading') . '</h2>', $dark ? '#ffffff' : '$primary', 'left', '38');
				}
				if (self::sec_str($spec, 'text') !== '') {
					$content[] = self::sec_body(self::sec_str($spec, 'text'), $dark, 'left');
				}
				$btn = self::sec_btn(isset($spec['cta']) ? $spec['cta'] : null, $dark, 'left');
				if ($btn) { $content[] = $btn; }

				$text_col = self::sec_col($image !== '' ? 6 : 12, $content);
				$img_col  = $image !== '' ? self::sec_col(6, array(
					array('tag' => 'pl_image', 'attrs' => array('id' => $image, 'id-alt' => self::sec_str($spec, 'heading', 'About us'), 'align' => 'center')),
				)) : null;

				if ($img_col && !empty($spec['flip'])) {
					$cols[] = $img_col;
					$cols[] = $text_col;
				} else {
					$cols[] = $text_col;
					if ($img_col) { $cols[] = $img_col; }
				}
				break;

			case 'stats':
				$items  = self::sec_items($spec);
				$width  = (int) floor(12 / max(1, min(4, count($items) ?: 1)));
				$header = self::sec_header_col($spec, $dark);
				if ($header) { $cols[] = $header; }

				foreach ($items as $item) {
					if (!is_array($item)) { continue; }
					$attrs = array(
						// counter_start_number is deliberately NOT set here. The
						// number block is gated by if="{{counter_start_number}}",
						// and "0" is falsy — setting it to zero hid the figures
						// just as completely as omitting it did. The widget's own
						// default ("1") is truthy and is supplied by
						// apply_markup_defaults(), which is where markup-critical
						// params belong.
						'counter_end_number' => (string) (isset($item['number']) ? preg_replace('/[^0-9.]/', '', (string) $item['number']) : '0'),
						'counter_text'       => self::sec_str($item, 'label'),
						'counter_align'      => 'center',
						'counter_text_color'   => $dark ? '#ffffff' : '$text',
						'counter_number_color' => $dark ? '#ffffff' : '$primary',
					);
					if (self::sec_str($item, 'prefix') !== '') { $attrs['number_prefix'] = self::sec_str($item, 'prefix'); }
					if (self::sec_str($item, 'suffix') !== '') { $attrs['number_suffix'] = self::sec_str($item, 'suffix'); }
					$cols[] = self::sec_col($width, array(array('tag' => 'pl_counter', 'attrs' => $attrs)));
				}
				break;

			case 'testimonials':
				$items  = self::sec_items($spec);
				$width  = (int) floor(12 / max(1, min(3, count($items) ?: 1)));
				$header = self::sec_header_col($spec, $dark);
				if ($header) { $cols[] = $header; }

				foreach ($items as $item) {
					if (!is_array($item)) { continue; }
					$attrs = array(
						'quote_content'  => self::sec_str($item, 'quote'),
						'cite'           => self::sec_str($item, 'name'),
						'designation'    => self::sec_str($item, 'role'),
						// Nothing was coloured here at all, so on a dark card
						// the name and role rendered in the theme's dark default
						// and disappeared into the background.
						'cite_color'        => $dark ? '#ffffff' : '$primary',
						'designation_color' => $dark ? 'rgba(255,255,255,0.7)' : '$text',
						// The quote body, like the icon-box text, has no colour
						// prop — only the cite and designation do.
						'ele_css'        => $dark ? '{{element}} .pagelayer-testimonial-content{color:rgba(255,255,255,0.72)}' : '',
						'image_position' => 'top-position',
						'alignment'      => 'center',
					);
					if (self::sec_str($item, 'avatar') !== '') {
						$attrs['avatar']                 = self::sec_str($item, 'avatar');
						$attrs['img_shape']              = 'circle';
						// Without a fixed size the avatar stretches into an oval.
						$attrs['testimonial_image_size'] = '80';
					}
					$cols[] = self::sec_col($width, array(array('tag' => 'pl_testimonial', 'attrs' => $attrs)), self::sec_card_style($dark));
				}
				break;

			case 'faq':
				$items  = self::sec_items($spec);
				$header = self::sec_header_col($spec, $dark);
				if ($header) { $cols[] = $header; }

				$acc_items = array();
				foreach ($items as $i => $item) {
					if (!is_array($item)) { continue; }
					$answer = self::sec_str($item, 'a');
					$acc_items[] = array(
						'tag'   => 'pl_accordion_item',
						'attrs' => array(
							'title'          => self::sec_str($item, 'q'),
							'default_active' => $i === 0 ? 'true' : '',
						),
						'content' => array(
							array('tag' => 'pl_inner_row', 'content' => array(
								array('tag' => 'pl_inner_col', 'attrs' => array('col' => 12), 'content' => array(
									self::sec_body($answer, $dark, 'left'),
								)),
							)),
						),
					);
				}

				// An uncoloured accordion on a dark section renders dark question
				// text on a dark panel — the FAQ was there but unreadable.
				$acc_attrs = array('acc_space' => '12');
				if ($dark) {
					$acc_attrs['tabs_color']           = '#ffffff';
					$acc_attrs['tabs_bg_color']        = 'rgba(255,255,255,0.08)';
					$acc_attrs['tabs_active_color']    = '#ffffff';
					$acc_attrs['tabs_active_bg_color'] = 'rgba(255,255,255,0.14)';
					$acc_attrs['tabs_content_bg_color'] = 'rgba(255,255,255,0.05)';
				}

				$cols[] = self::sec_col(12, array(array(
					'tag'     => 'pl_accordion',
					'attrs'   => $acc_attrs,
					'content' => $acc_items,
				)));
				break;

			case 'cta':
				$content = array();
				if (self::sec_str($spec, 'heading') !== '') {
					$content[] = self::sec_heading('<h2>' . self::sec_str($spec, 'heading') . '</h2>', $dark ? '#ffffff' : '$primary', 'center', '38');
				}
				if (self::sec_str($spec, 'text') !== '') {
					$content[] = self::sec_body(self::sec_str($spec, 'text'), $dark, 'center');
				}
				$btn = self::sec_btn(isset($spec['cta']) ? $spec['cta'] : null, $dark, 'center');
				if ($btn) { $content[] = $btn; }
				$cols[] = self::sec_col(12, $content);
				break;

			case 'team':
				$items  = self::sec_items($spec);
				$width  = (int) floor(12 / max(1, min(4, count($items) ?: 1)));
				$header = self::sec_header_col($spec, $dark);
				if ($header) { $cols[] = $header; }

				foreach ($items as $item) {
					if (!is_array($item)) { continue; }
					$attrs = array(
						'service_heading'   => self::sec_str($item, 'name'),
						'service_text'      => self::sec_str($item, 'role') . (self::sec_str($item, 'text') !== '' ? ' — ' . self::sec_str($item, 'text') : ''),
						'service_alignment' => 'center',
						// Same absent-colour problem as the feature cards.
						'service_heading_color' => $dark ? '#ffffff' : '$primary',
						'ele_css' => $dark ? '{{element}} .pagelayer-service-text{color:rgba(255,255,255,0.72)}' : '',
					);
					if (self::sec_str($item, 'image') !== '') {
						$attrs['service_image'] = self::sec_str($item, 'image');
						// Portraits and landscapes sitting in one row render at
						// their natural aspect ratios, so one card came out twice
						// the height of its neighbours and the row looked broken.
						// A fixed height plus object-fit:cover crops them to a
						// common shape instead of distorting them.
						$attrs['service_image_height']     = '260';
						$attrs['service_image_object_fit'] = 'cover';
					}
					$cols[] = self::sec_col($width, array(array('tag' => 'pl_service', 'attrs' => $attrs)), self::sec_card_style($dark));
				}
				break;

			default:
				// Unknown preset: keep it visible as an error the gate will
				// report, rather than silently dropping the caller's content.
				return array(array(
					'tag'     => 'pl_' . preg_replace('/[^a-z0-9_]/', '', $type),
					'attrs'   => array(),
					'content' => '',
				));
		}

		if (empty($cols)) {
			return array();
		}

		return array(self::sec_row($spec, $cols));
	}

	public static function normalize_layout_data($data) {
		if (!is_array($data)) {
			return $data;
		}
		$data       = self::expand_sections($data);
		$normalized = array();
		foreach ($data as $node) {
			if (!is_array($node)) {
				$normalized[] = $node;
				continue;
			}
			$normalized[] = self::normalize_node($node);
		}

		return $normalized;
	}

	/**
	 * Move a widget's innerHTML-backed text from attrs into node content.
	 *
	 * Only acts when the node has no usable content of its own, and never on a
	 * container (whose content is an array of child nodes).
	 */
	protected static function bridge_inner_html(&$node) {
		global $pagelayer;

		$tag = isset($node['tag']) ? $node['tag'] : '';
		if ($tag === '') {
			return;
		}

		self::ensure_shortcodes_loaded();
		$inner_key = isset($pagelayer->shortcodes[$tag]['innerHTML']) ? $pagelayer->shortcodes[$tag]['innerHTML'] : '';

		// The mirror case: a widget with NO innerHTML mapping reads its label
		// from an attribute and ignores node content completely. pl_btn is the
		// one that bites — its label span is gated `if="{{text}}"`, so a button
		// whose caption was written as node content renders as an empty
		// coloured rectangle. Nothing objects: content is legal on any node and
		// the missing attr is simply absent.
		if ($inner_key === '') {
			if (
				isset($node['content']) && is_string($node['content']) && trim($node['content']) !== ''
				&& empty($node['attrs']['text'])
			) {
				$rules = self::widget_attr_rules($tag);
				if (isset($rules['allowed']['text'])) {
					$node['attrs']['text'] = trim(wp_strip_all_tags($node['content']));
					$node['content']       = '';
				}
			}
			return;
		}

		if (empty($node['attrs'][$inner_key]) || !is_string($node['attrs'][$inner_key])) {
			return;
		}

		// A container's content holds child nodes — never overwrite it.
		if (isset($node['content']) && is_array($node['content'])) {
			return;
		}

		if (isset($node['content']) && is_string($node['content']) && trim($node['content']) !== '') {
			// Author supplied content explicitly; drop the duplicate attr so the
			// two cannot disagree.
			unset($node['attrs'][$inner_key]);
			return;
		}

		$node['content'] = $node['attrs'][$inner_key];
		unset($node['attrs'][$inner_key]);
	}

	/**
	 * Add missing CSS units to padding-style attribute values.
	 *
	 * Props of type "padding" (ele_padding, ele_margin, *_border_width,
	 * *_border_radius, icon padding, ...) render through templates that emit the
	 * stored value VERBATIM — "padding-top: {{val[0]}}". A bare number therefore
	 * produces `padding-top: 15`, which is not valid CSS, so the browser drops
	 * every one of those declarations and the element ends up with no padding at
	 * all.
	 *
	 * "80px,20px,80px,20px" and "15,20,15,20" look equally reasonable when
	 * writing JSON, and the second silently does nothing — the attribute name is
	 * real and the numbers are sane, so neither the schema nor the quality gate
	 * has anything to object to. That is how a header ended up with its
	 * navigation jammed against the edge of the viewport.
	 *
	 * Only bare numbers are touched; anything already carrying a unit (px, %,
	 * em, rem, vh, auto, calc(...)) is left exactly as written.
	 */
	protected static function add_missing_css_units(&$node) {
		if (empty($node['tag']) || empty($node['attrs']) || !is_array($node['attrs'])) {
			return;
		}

		$rules = self::widget_attr_rules($node['tag']);
		if (empty($rules['allowed'])) {
			return;
		}

		foreach ($node['attrs'] as $key => $value) {
			if (!is_string($value) || $value === '') {
				continue;
			}
			if (!isset($rules['allowed'][$key]) || $rules['allowed'][$key] !== 'padding') {
				continue;
			}

			$parts   = explode(',', $value);
			$changed = false;
			foreach ($parts as $i => $part) {
				$part = trim($part);
				if ($part === '' || !preg_match('/^-?\d+(\.\d+)?$/', $part)) {
					continue;
				}
				// A bare 0 is valid CSS on its own; everything else needs a unit.
				if ((float) $part === 0.0) {
					continue;
				}
				$parts[$i] = $part . 'px';
				$changed   = true;
			}

			if ($changed) {
				$node['attrs'][$key] = implode(',', $parts);
			}
		}
	}

	/**
	 * Supply widget defaults for the params the widget's MARKUP depends on.
	 *
	 * Pagelayer writes a widget's defaults into the node when the editor inserts
	 * it; nothing does that for a node built through the abilities layer, so it
	 * carries only what was set explicitly. Any param the html template
	 * interpolates then renders as the literal token, and any block gated by
	 * if="{{param}}" is dropped outright. Observed consequences:
	 *
	 *   pl_wp_menu  layout ("horizontal")  -> class="pagelayer-menu-type-{{layout}}"
	 *                                         so the nav fell back to a vertical
	 *                                         bulleted <ul>
	 *   pl_counter  counter_start_number ("1") -> if="" dropped the whole number
	 *                                         block, leaving labels with no figures
	 *   pl_iconbox  service_icon_view, service_icon_shape_type -> literal
	 *                                         {{...}} leaked into class names
	 *
	 * Scope is deliberately narrow: only params the markup names, and never a
	 * text-bearing one. Text defaults are placeholder copy ("Counter", "This is
	 * Icon Box") — writing those would ship filler and trip the quality gate.
	 * Styling and structural defaults are exactly what we want.
	 */
	protected static function apply_markup_defaults(&$node) {
		global $pagelayer;
		static $cache = array();

		$tag = isset($node['tag']) ? $node['tag'] : '';
		if ($tag === '') {
			return;
		}

		self::ensure_shortcodes_loaded();
		if (empty($pagelayer->shortcodes[$tag]['html'])) {
			return;
		}

		if (!isset($cache[$tag])) {
			$def    = $pagelayer->shortcodes[$tag];
			$schema = self::extract_widget_schema($tag, $def);

			$props = array();
			foreach ($schema['sections'] as $section) {
				foreach ($section['properties'] as $key => $prop) {
					$props[$key] = $prop;
				}
			}

			// Params named anywhere in the markup, including {{{escaped}}} form.
			preg_match_all('/\{\{\{?([a-zA-Z0-9_\-]+)\}?\}\}/', $def['html'], $matches);

			$inner = isset($def['innerHTML']) ? $def['innerHTML'] : '';
			$fill  = array();

			foreach (array_unique($matches[1]) as $name) {
				if (!isset($props[$name]) || $name === $inner) {
					continue;
				}
				$type = isset($props[$name]['type']) ? $props[$name]['type'] : '';
				if (in_array($type, array('text', 'textarea', 'editor'), true)) {
					continue; // placeholder copy — never inject it
				}

				// A param gated behind a companion (`requires`) is discarded at
				// render unless that companion is explicitly set, and the
				// quality gate treats the orphan as a hard error. Filling its
				// default can only ever create that orphan — e.g. pl_iconbox's
				// iconbox_button_type, which needs service_button="true" that
				// nobody asked for. Leave gated params to the caller.
				if (!empty($props[$name]['requires'])) {
					continue;
				}
				$default = isset($props[$name]['default']) ? $props[$name]['default'] : null;
				if ($default === null || $default === '' || is_array($default)) {
					continue;
				}
				$fill[$name] = $default;
			}

			$cache[$tag] = $fill;
		}

		foreach ($cache[$tag] as $key => $value) {
			if (!isset($node['attrs'][$key]) || $node['attrs'][$key] === '') {
				$node['attrs'][$key] = $value;
			}
		}
	}

	public static function normalize_node($node) {
		if (!is_array($node)) {
			return $node;
		}

		// 1. Shorthand Tag Mapping
		$tag = isset($node['tag']) ? (string)$node['tag'] : (isset($node['type']) ? (string)$node['type'] : '');
		$tag = strtolower(trim($tag));

		$tag_map = array(
			'container'   => 'pl_row',
			'section'     => 'pl_row',
			'row'         => 'pl_row',
			'pl_section'  => 'pl_row',
			'pagelayer_section' => 'pl_row',
			'column'      => 'pl_col',
			'col'         => 'pl_col',
			'pagelayer_col'     => 'pl_col',
			'heading'     => 'pl_heading',
			'title'       => 'pl_heading',
			'text'        => 'pl_text',
			'paragraph'   => 'pl_text',
			'button'      => 'pl_btn',
			'btn'         => 'pl_btn',
			'image'       => 'pl_image',
			'img'         => 'pl_image',
			'iconbox'     => 'pl_iconbox',
			'icon_box'    => 'pl_iconbox',
			'accordion'   => 'pl_accordion',
			'testimonial' => 'pl_testimonial',
		);

		if (isset($tag_map[$tag])) {
			$node['tag'] = $tag_map[$tag];
		} elseif (strpos($tag, 'pagelayer_') === 0) {
			$node['tag'] = str_replace('pagelayer_', 'pl_', $tag);
		} elseif (strpos($tag, 'pl_') !== 0 && !empty($tag)) {
			$node['tag'] = 'pl_' . $tag;
		}

		if (empty($node['tag'])) {
			$node['tag'] = isset($node['content']) && is_array($node['content']) ? 'pl_row' : 'pl_text';
		}

		// Ensure attrs array exists
		if (!isset($node['attrs']) || !is_array($node['attrs'])) {
			$node['attrs'] = array();
		}

		// Ensure pagelayer-id exists
		if (empty($node['attrs']['pagelayer-id']) && function_exists('pagelayer_create_id')) {
			$node['attrs']['pagelayer-id'] = pagelayer_create_id();
		}

		// Some widgets take their main text from the node's inner content rather
		// than from an attribute — the widget declares which param that is via
		// 'innerHTML' (pl_iconbox/pl_service => service_text,
		// pl_testimonial/pl_quote => quote_content, pl_accordion_item => title).
		// Putting that text in attrs is the natural mistake and it fails
		// silently: the attribute is a real property so the quality gate accepts
		// it, then the renderer reads content, finds nothing, and emits an empty
		// element. That is how feature cards shipped with titles but no
		// description and testimonials with names but no quote.
		//
		// Bridge it here, where every caller passes through, so presets and
		// hand-written nodes are both fixed. An explicit content value always
		// wins — this only fills an empty one.
		self::bridge_inner_html($node);

		self::apply_markup_defaults($node);

		self::add_missing_css_units($node);

		// 2. Specific Normalization for Row Nodes
		if ($node['tag'] === 'pl_row') {
			if (!isset($node['attrs']['stretch'])) {
				$node['attrs']['stretch'] = 'full';
			}
			if (empty($node['attrs']['ele_padding'])) {
				$node['attrs']['ele_padding'] = '80px,0px,80px,0px';
			}

			// Process children inside Row
			$raw_children = isset($node['content']) && is_array($node['content']) ? $node['content'] : array();
			$normalized_children = array();

			// Auto-wrap non-column leaf widgets into Columns
			foreach ($raw_children as $child) {
				if (!is_array($child)) continue;
				$child_tag = isset($child['tag']) ? $child['tag'] : (isset($child['type']) ? $child['type'] : '');
				$child_tag = strtolower(trim($child_tag));
				if ($child_tag !== 'col' && $child_tag !== 'column' && $child_tag !== 'pl_col' && $child_tag !== 'pagelayer_col') {
					// Wrap in column
					$child = array(
						'tag' => 'pl_col',
						'attrs' => array('pagelayer-id' => function_exists('pagelayer_create_id') ? pagelayer_create_id() : ''),
						'content' => array($child)
					);
				}
				$normalized_children[] = self::normalize_node($child);
			}

			// Auto-calculate column grid width (col: 12 / N)
			$child_count = count($normalized_children);
			if ($child_count > 0) {
				$auto_col = max(1, (int) floor(12 / $child_count));
				foreach ($normalized_children as &$c_node) {
					if (is_array($c_node) && $c_node['tag'] === 'pl_col') {
						if (!isset($c_node['attrs']['col']) || empty($c_node['attrs']['col'])) {
							$c_node['attrs']['col'] = $auto_col;
						}
					}
				}
				unset($c_node);
			}

			$node['content'] = $normalized_children;

		// 3. Specific Normalization for Column Nodes
		} elseif ($node['tag'] === 'pl_col') {
			if (empty($node['attrs']['col'])) {
				$node['attrs']['col'] = 12;
			}
			if (isset($node['content']) && is_array($node['content'])) {
				$norm_content = array();
				foreach ($node['content'] as $c) {
					$norm_content[] = self::normalize_node($c);
				}
				$node['content'] = $norm_content;
			}

		// 4. Default Visual Enhancement Injection for Leaf Widgets.
		//    Everything injected here must be a REAL attribute of the widget AND
		//    must satisfy that attribute's `req` dependency, otherwise
		//    pagelayer_render_shortcode() unsets it before render
		//    (shortcode_functions.php:191) and the styling silently disappears.
		} elseif ($node['tag'] === 'pl_btn') {
			// btn_bg_color/btn_color are gated behind req: type must be
			// pagelayer-btn-custom or pagelayer-btn-anim. The widget default is
			// pagelayer-btn-default, so without this the colors are discarded.
			if (empty($node['attrs']['btn_bg_color'])) {
				$node['attrs']['btn_bg_color'] = '$primary';
			}
			if (empty($node['attrs']['btn_color'])) {
				$node['attrs']['btn_color'] = '#ffffff';
			}
			if (empty($node['attrs']['type'])) {
				$node['attrs']['type'] = 'pagelayer-btn-custom';
			}
			// btn_border_radius is itself gated behind btn_border_type != "".
			// Injecting the radius alone did nothing at render (Pagelayer drops
			// it) AND tripped the quality gate on every page with a button, so
			// every such create_page was rejected and regenerated for nothing.
			// "solid" with zero width gives the rounded corners with no visible
			// border, which is what the radius was here for.
			if (empty($node['attrs']['btn_border_radius'])) {
				$node['attrs']['btn_border_radius'] = '6px,6px,6px,6px';
				if (!isset($node['attrs']['btn_border_type']) || $node['attrs']['btn_border_type'] === '') {
					$node['attrs']['btn_border_type'] = 'solid';
				}
				if (empty($node['attrs']['btn_border_width'])) {
					$node['attrs']['btn_border_width'] = '0px,0px,0px,0px';
				}
			}
			if (!isset($node['attrs']['font_weight'])) {
				$node['attrs']['font_weight'] = '600';
			}
		} elseif ($node['tag'] === 'pl_heading') {
			if (empty($node['attrs']['color'])) {
				$node['attrs']['color'] = '$primary';
			}
			if (empty($node['attrs']['font_weight'])) {
				$node['attrs']['font_weight'] = '700';
			}
		} elseif ($node['tag'] === 'pl_text') {
			// pl_text has no "color" attr of its own — its only param is "text".
			// font_size/line_height come from the global font_style section and
			// do apply.
			if (empty($node['attrs']['font_size'])) {
				$node['attrs']['font_size'] = '16';
			}
			if (empty($node['attrs']['line_height'])) {
				$node['attrs']['line_height'] = '1.6';
			}
		} elseif ($node['tag'] === 'pl_image') {
			// pl_image's real image-source attribute is literally named "id"
			// (see shortcodes.php pl_image 'params'.'id') — NOT "img". It
			// accepts either a full https:// URL or a numeric WP attachment
			// ID (resolved via pagelayer_image()). "img" is not a real attr
			// on this widget and silently does nothing.
			//
			// ponytail: deliberately NOT substituting a stock photo when the
			// image is unset — the widget's own default-image.png placeholder is
			// the honest result, and layout is what we are tuning right now.
			// validate_page reports missing images as a warning, not an error.
			if (empty($node['attrs']['align'])) {
				$node['attrs']['align'] = 'center';
			}
		}

		return $node;
	}

	public static function serialize_layout_to_blocks($data) {
		if (!is_array($data)) {
			return '';
		}
		$prefix = defined('PAGELAYER_BLOCK_PREFIX') ? PAGELAYER_BLOCK_PREFIX : 'wp';
		$out = '';
		foreach ($data as $node) {
			if (!is_array($node) || empty($node['tag'])) {
				continue;
			}
			$tag = $node['tag'];

			// Block names MUST keep the widget tag verbatim (underscores and all).
			// pagelayer_render_blocks() does substr($block_name, 10) and looks the
			// result up directly in $pagelayer->shortcodes with NO dash/underscore
			// translation (shortcode_functions.php:47), so "pagelayer/pl-grid_gallery"
			// or "pagelayer/pl-grid-gallery" both miss and the widget renders as
			// nothing. Core writes "pagelayer/pl_grid_gallery" everywhere (live.php,
			// import.php) — match it.
			$clean_tag = str_replace('pagelayer_', 'pl_', $tag);
			if (strpos($clean_tag, 'pl_') !== 0) {
				$clean_tag = 'pl_' . $clean_tag;
			}

			$attrs = isset($node['attrs']) && is_array($node['attrs']) ? $node['attrs'] : array();
			if (empty($attrs['pagelayer-id']) && function_exists('pagelayer_create_id')) {
				$attrs['pagelayer-id'] = pagelayer_create_id();
			}

			$content    = isset($node['content']) ? $node['content'] : '';
			$block_name = $prefix . ':pagelayer/' . $clean_tag;
			$attrs_str  = '';
			if (!empty($attrs)) {
				$attrs_str = ' ' . json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			}

			$out .= '<!-- ' . $block_name . $attrs_str . " -->\n";
			if (is_array($content)) {
				$out .= self::serialize_layout_to_blocks($content);
			} else {
				$out .= $content . "\n";
			}
			$out .= '<!-- /' . $block_name . " -->\n";
		}
		return $out;
	}

	public static function get_data_structure_doc() {
		return array(
			'description' => 'pagelayer_data is a JSON array of element nodes. Each node represents a Row, Column, or Widget. The hierarchy is: Rows contain Columns, Columns contain Widgets (or nested Rows). Every node has "tag", "attrs", and optionally "content".',
			'design_consistency' => 'Pagelayer has a built-in design-token system: global_colors and global_fonts. Define the site palette and fonts ONCE, then reference those tokens from every widget using "$<key>" (e.g. "$primary", "$accent") instead of repeating literal hex codes or font stacks.',
			'global_reference_syntax' => array(
				'color_or_gradient_props' => 'Pass "$<key>" (e.g. "$primary", "$accent") for color/gradient properties to bind to live CSS variables (var(--pagelayer-color-<key>)).',
				'typography_props' => 'Pass "$<key>" (e.g. "$primary") for typography properties to inherit global font presets.',
				'defined_keys_only' => 'A "$key" that does not exist in global_colors silently resolves to $primary — it does NOT error. Only "primary", "secondary", "text" and "accent" exist by default, so if you want to use "$bg" or "$light_bg" you MUST first define those keys via update_global_styles (or the global_colors argument of create_page/create_website). Otherwise every section background collapses to the same brand color.',
				'example' => 'attrs: {"ele_bg_type": "color", "ele_bg_color": "$primary", "color": "$text", "heading_typo": "$secondary"}'
			),
			'dependent_attributes' => array(
				'rule' => 'CRITICAL — many style attributes are gated behind another attribute on the SAME node. At render time Pagelayer deletes a gated attribute if its companion is not explicitly set, and widget defaults do not count. The page still renders, just without your styling and without any error. validate_page and the create/update abilities now report this as a hard error.',
				'how_to_check' => 'get_widget_schema returns a "requires" key on every gated property — send every attribute named there, with one of the listed values, on the same node.',
				'common_pairs' => array(
					'ele_bg_color / ele_bg_gradient / ele_bg_img' => 'require ele_bg_type set to "color" / "gradient" / "image" respectively. A row or column background WILL NOT render without it.',
					'btn_bg_color / btn_color' => 'require type = "pagelayer-btn-custom" (or "pagelayer-btn-anim"). The button default is "pagelayer-btn-default", which discards both colors.',
					'pl_col col_width' => 'requires col = "" (custom width mode). If you set the 1-12 "col" attr, do not also send col_width.',
				),
			),
			'fast_path_sections' => array(
				'rule' => 'DEFAULT TO THIS. A pagelayer_data entry may be a section spec — {"section":"<preset>", ...content...} — instead of a hand-written pl_row/pl_col/widget tree. Pagelayer expands it server-side into the same nodes, with correct attribute names, gated companions, spacing, responsive padding and colours already right. It is ~5x fewer tokens to write, it is faster, and it cannot fail the quality gate on invented attributes.',
				'presets' => self::section_presets(),
				'common_keys' => 'Every preset also accepts: bg (row background — a "$token" or hex), bg_image (url, with bg used as the overlay colour), dark (true|false — forces light text; auto-detected from bg otherwise), padding / padding_mobile ("top,right,bottom,left"), anchor (adds an element id for in-page links).',
				'example' => '[{"section":"hero","heading":"Fresh bread, baked at 4am","sub":"Family bakery since 1998.","cta":{"text":"Order online","link":"/order"},"image":"https://...jpg"},{"section":"features","heading":"Why people come back","items":[{"icon":"fas fa-clock","title":"36-hour ferment","text":"Slow rise, easier to digest."}]},{"section":"cta","heading":"Order tonight","cta":{"text":"Start an order","link":"/order"},"bg":"$primary"}]',
				'when_to_hand_write' => 'Mix freely: use raw nodes for anything the presets do not cover (galleries, pricing tables, maps, forms, custom layouts) and section specs for the ordinary page furniture. Read the widget schemas only for the parts you hand-write.',
			),
			'node_format' => array(
				'tag' => 'string - The widget shortcode tag, e.g. "pl_row", "pl_col", "pl_heading", "pl_btn", "pl_image", "pl_iconbox", etc.',
				'attrs' => 'object - Key-value map of widget attributes matching widget schema. ALL styling lives here.',
				'content' => 'array of child nodes for container elements (pl_row, pl_col), or an HTML string for leaf content widgets (pl_heading, pl_text). That HTML is CONTENT MARKUP ONLY — see styling_never_inline.'
			),
			'styling_never_inline' => array(
				'rule' => 'ENFORCED, not bypassable: never write a style="" attribute or a <style> block into rich text — not in node.content, not in a text/textarea/editor attribute (service_heading, quote_content, text, ...), and not via a "style=" entry in ele_attributes. The write is rejected and nothing is saved.',
				'why' => 'Pagelayer renders that HTML verbatim, so the inline rule outranks every builder control: the widget\'s own color/typography options stop having any effect, the _tablet/_mobile variants never apply to it, a later global-color change leaves the page half-rebranded, and the site owner cannot undo any of it from the Pagelayer UI.',
				'allowed_in_rich_text' => 'Content markup only: <strong>, <em>, <u>, <a href>, <br>, <span> without style, <ul>/<ol>/<li>.',
				'do_this_instead' => array(
					'1_widget_attribute' => 'Set the widget\'s own attribute — get_widget_schema for widget-specific props, get_common_styles for the ones every widget/row/column accepts (color, font_size, font_weight, font_style, ele_padding, ele_margin, ele_bg_*, ele_border_*, and their _tablet/_mobile variants).',
					'2_custom_css_attribute' => 'ONLY when no control exists for what you need: put a real CSS rule in the "ele_css" attribute of that same node, using {{element}} as the element selector. Example: attrs: {"ele_css": "{{element}} .pagelayer-heading-holder h2 { letter-spacing: 2px; text-transform: uppercase; }"}. This is the sanctioned place for hand-written CSS and is the ONLY attribute exempt from the rule.',
					'3_never' => 'Do not route around the rule with pl_embed or pl_shortcodes — those exist for third-party embed code, not for hand-rolled styled markup.',
				),
				'example' => 'WRONG pl_heading content: "<h2 style=\"color:#fff;font-size:42px;text-align:center\">Welcome</h2>" | RIGHT: content "<h2>Welcome</h2>" plus attrs {"color": "$primary", "heading_typo": "$primary", "align": "center"} — pl_heading sizes its text through heading_typo, not a font-size in the markup. Confirm the exact attr names per widget with get_widget_schema.',
			),
			'site_navigation' => array(
				'rule' => 'ENFORCED on header templates: unless the user explicitly asked for a ONE-PAGE / single-page site, the header must contain the Primary Menu widget (tag "pl_wp_menu") with attrs.nav_list set to a real WordPress menu id. create_template/update_template reject a header without it and nothing is saved.',
				'why' => 'pl_wp_menu renders an actual WordPress menu: the owner can edit it from Appearance > Menus, it collapses into a mobile toggle, it supports submenus, mega dropdowns and current-page highlighting. A row of pl_btn/pl_text links looks the same in the builder and gives none of that — and every new page has to be added by hand in the builder.',
				'order_of_operations' => array(
					'1' => 'Create the pages first (create_page / create_website) so there is something to link to.',
					'2' => 'Call create_menu {name:"Primary Menu", location:"<slug from get_menus.locations>", items:[{title, page_id}, ...]} — it returns menu_id. get_menus lists menus that already exist.',
					'3' => 'Build the header with a pl_wp_menu node carrying that menu_id, then configure the widget: nav_list, layout (horizontal|vertical|dropdown), align, drop_breakpoint (tablet/mobile — this is what produces the hamburger toggle), pointer, m_animation, submenu_ind, plus the menu/submenu colour and typography props. Call get_widget_schema {"widget":"pl_wp_menu"} for the full list; never guess.',
				),
				'mega_menu' => 'A Mega Menu is a per-ITEM setting, not a separate widget: pass menu_type:"mega" plus mega_content (an array of pl_inner_row nodes, same node format as any layout) on that item in create_menu. Pagelayer stores it on the menu item and the Primary Menu widget renders it as the dropdown. menu_type:"column" gives a multi-column plain dropdown (with columns / col_gap); the default "" is a normal flyout submenu. Use mega for a big services/products dropdown with icons, images or promo blocks — a plain submenu is fine for two or three links.',
				'one_page_sites' => 'Only when the user actually asked for a one-pager: pass single_page_site:true to create_template/create_website and use pl_btn/pl_list links with "#section-id" hrefs, adding a pl_anchor node at each target section.',
				'footer' => 'A footer may use a second menu the same way (a compact "Quick Links" menu via pl_wp_menu with layout "vertical"), but it is not enforced there.',
			),
			'theme_template_conditions' => array(
				'rule' => 'Header and footer templates are always saved with Display Conditions = Action Type "include", Display On "Full Site" — i.e. {"type":"include","template":"","sub_template":"","id":""}. If you send conditions without that rule, it is added back at the front of your list.',
				'why' => 'A header scoped to "singular" or "front_page" simply does not render on archives, search or 404 views, and Pagelayer gives no warning about it — the site just looks broken on those pages.',
				'other_types' => 'For every other template type (blog_archive, single_blog, search, 404, popup, woocommerce_*) you choose the conditions: template "archives" or "singular" narrows it, sub_template narrows further (e.g. singular + front_page, archives + search), and "id" pins it to one object. "exclude" rules can be combined with the site-wide include to carve out exceptions.',
			),
			'hierarchy_rules' => array(
				'pl_row' => 'Top-level section container. Content must be array of pl_col nodes.',
				'pl_col' => 'Column inside a Row. Content is array of widget nodes or inner rows. Attr "col" sets grid width (1-12).',
				'widgets' => 'Placed inside Columns. Never place leaf widgets directly inside a Row without a Column.',
				'parent_widgets' => 'Widgets with parent constraints (e.g. pl_tab inside pl_tabs, pl_accordion_item inside pl_accordion).'
			),
			'responsive_properties' => 'Properties supporting per-device overrides append screen suffixes in attrs: base for desktop, _tablet for tablet, _mobile for mobile (e.g., ele_padding, ele_padding_mobile, font_size, font_size_mobile).',
			'design_workflow' => array(
				'step_1' => 'Call list_widgets or get_widgets_summary to see every widget available on this install (never assume a fixed catalog).',
				'step_2' => 'Call get_widget_schema (or get_all_schemas) for the widgets you plan to use, to learn their real attributes, allowed values, and defaults on this site.',
				'step_3' => 'Call get_widget_examples for canonical node shapes, get_color_presets / get_spacing_presets / get_fonts for design-token starting points, and get_icons for icon classes. Check the "requires" key on every property you intend to set (see dependent_attributes) — a gated attribute sent without its companion is discarded at render. Images are optional: call search_images for a topically relevant photo and put the URL in pl_image\'s "id" attr (NOT "img", which does not exist), or omit it and accept the placeholder.',
				'step_4' => 'Define global_colors/global_fonts (via update_global_styles or when creating the page) based on the requested brand/niche, then compose pagelayer_data nodes using "$<key>" references and the schemas discovered above.',
				'step_5' => 'Use validate_page before publishing to catch hierarchy, accessibility, and SEO issues.'
			),
						'content_quality_rules' => array(
							'no_placeholders' => 'Every widget must ship with real, unique, on-topic copy for the requested business/niche. Never leave pl_iconbox/pl_testimonial/pl_heading/pl_text relying on the widget\'s built-in default text (e.g. generic "Icon Box" titles) — this is an ENFORCED gate on create_page/create_website/update_page/add_element/update_element/create_design_ui/edit_layout, not just a validate_page warning: the call fails and nothing is saved.',
							'no_inline_css' => 'ENFORCED and NOT bypassable by skip_validation: rich text must not contain style="" attributes or <style> blocks (see styling_never_inline in get_data_structure). Put styling in the node\'s attrs — a real widget attribute, or the "ele_css" custom-CSS attribute when no control exists for it.',
							'valid_attributes_only' => 'ENFORCED: an attribute name that is not in the widget\'s schema, or a gated attribute sent without its companion (see dependent_attributes), fails the same gate. Both would otherwise render a page with none of the requested styling and no error, so they are treated as hard errors. Call get_widget_schema for any widget you have not used before.',
							'images_are_optional_right_now' => 'Images are NOT gated. If you have a relevant photo, call search_images with a specific keyword and put the result URL in attrs.id — that is pl_image\'s real image field, NOT "img", which does not exist on this widget and silently renders nothing. If you do not, simply omit attrs.id and the builder placeholder renders; validate_page reports it as a warning. Do NOT invent image URLs or reuse one photo across widgets to fill the gap — a placeholder is better than a wrong or broken image.',
							'section_variety' => 'A "full website" page is expected to include, at minimum: hero, feature/benefit grid, an about/story split section, social proof (testimonials or stats), a call-to-action section, and a footer — not a single thin column of default widgets. Build each section with the purpose-built widget listed in widget_recommendations rather than hand-rolling it out of pl_row/pl_col/pl_text — the dedicated widget already has the right markup, animation, and structure that a generic composition will not match.',
							'visual_polish' => 'Apply shadows and generous spacing (see get_spacing_presets) to cards and buttons so the result looks like a designed template, not raw defaults. Plain pl_image has NO border-radius attribute (only img_shadow exists) — do not claim or attempt to round its corners. If a widget pairs an image with a shape control (pl_testimonial\'s img_shape, pl_iconbox\'s stacked icon view), that shape only renders as a true circle/square when the matching width/height-style size control (e.g. testimonial_image_size) is explicitly set to one fixed value — omit it and the image stretches into an uneven oval.',
							'consistent_image_sizing' => 'When several images sit in the same row (a gallery, a row of avatars, a row of cards), they need a consistent size/aspect ratio. Prefer a dedicated gallery widget (pl_grid_gallery, pl_image_slider) over hand-placed pl_image nodes for galleries. For anything hand-placed, explicitly set matching width/height-style attrs on every image in that row — never let images with different natural dimensions sit side by side unconstrained, the row will look visibly broken.',
							'safe_layout_composition' => 'Default to normal stacked flow inside a column: heading, then subtext, then buttons, each a sibling block in reading order, spaced apart via padding/margin-style attrs (e.g. ele_padding). Do not stack multiple text/button elements on top of each other with overlapping/absolute positioning to fake a "layered hero" — that reliably renders as illegible overlapping text. The one safe layering pattern is a background image on the ROW itself (ele_bg_type=image, optionally with an overlay color) with the heading/text/buttons flowing normally inside its column on top of it.',
							'widget_recommendations' => array(
								'stats_or_counters_row' => 'pl_counter — animated number counters (e.g. "1240+ Active Members"), not plain pl_heading numbers in a row.',
								'image_gallery_or_portfolio' => 'pl_grid_gallery — real masonry/grid gallery widget, not a manual grid of pl_image nodes.',
								'before_after_or_carousel_images' => 'pl_image_slider for a slideshow of images.',
								'feature_or_service_cards' => 'pl_iconbox — icon + heading + text card (real fields: service_icon/service_heading/service_text/service_icon_color, NOT icon/title/desc). For a card with a real photo instead of an icon, use pl_service ("Image Box": service_image/service_heading/service_text) instead. Always confirm exact field names via get_widget_examples before using either.',
								'testimonials_or_reviews' => 'pl_testimonial — real fields are quote_content/cite/designation/avatar, NOT content/name/image.',
								'star_ratings' => 'pl_stars for a review/rating display, not text like "★★★★★".',
								'progress_or_skill_bars' => 'pl_progress for animated progress/skill bars.',
								'faq_or_expandable_content' => 'pl_accordion (with pl_accordion_item children) for FAQs, not a stack of pl_heading+pl_text pairs.',
								'tabbed_content' => 'pl_tabs (with pl_tab children).',
								'pull_quote_or_highlighted_statement' => 'pl_quote for a single stylized blockquote-like statement.',
								'call_to_action_button' => 'pl_btn with a real "link" and, where relevant, an icon — never a plain text link.',
								'numbered_or_bulleted_list' => 'pl_list (with pl_list_item children) for checklists/feature lists — do not fake bullets inside pl_text HTML.',
								'map_or_location' => 'pl_google_maps for an embedded map, pl_address/pl_phone/pl_email for contact details.',
								'site_navigation_in_header' => 'pl_wp_menu (Primary Menu) bound to a real menu built with create_menu — see site_navigation in get_data_structure. Never a row of pl_btn/pl_text links. Mega dropdowns are the menu item\'s menu_type:"mega" + mega_content, not a separate widget.',
								'social_links' => 'pl_social_grp (with pl_social children) for a row of social icons, not raw pl_btn/pl_icon guesses.',
								'video_embed' => 'pl_video for embedded/self-hosted video.',
								'note' => 'This list is not exhaustive and is not a substitute for calling list_widgets/get_widgets_summary — new or renamed widgets may exist on this install. When in doubt, look for a widget whose name matches the content type before composing it manually from generic row/col/text/image nodes.',
							),
						),
		);
	}

	// ==================================================================
	// REGISTRATION REGISTRY
	// ==================================================================

	protected static function register_widget_abilities() {
		$abilities = array(
			'list_widgets' => array(
				'label' => __('List Pagelayer Widgets', 'pagelayer'),
				'description' => __('Compact list of every registered widget (tag, name, group, nesting rules). Filter with group or search to keep it small.', 'pagelayer'),
				'category' => 'pagelayer-widgets',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'group'  => array('type' => 'string'),
						'search' => array('type' => 'string', 'description' => 'Substring match on widget name or tag.'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_list_widgets'),
			),
			'get_widget' => array(
				'label' => __('Get Widget Details', 'pagelayer'),
				'description' => __('Metadata and setting-section names for one widget. For the actual attributes use get_widget_schema.', 'pagelayer'),
				'category' => 'pagelayer-widgets',
				// Strict subset of get_widget_schema.
				'mcp_public' => false,
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('widget' => array('type' => 'string')),
					'required' => array('widget'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_widget'),
			),
			'get_widget_schema' => array(
				'label' => __('Get Widget Schema', 'pagelayer'),
				'description' => __('Attributes for one widget, compact. Returns only that widget\'s OWN props by default; the style props shared by all widgets come from get_common_styles (call it once per session).', 'pagelayer'),
				'category' => 'pagelayer-widgets',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'widget'   => array('type' => 'string'),
						'mode'     => array('type' => 'string', 'description' => 'own (default) | all | shared'),
						'sections' => array('type' => 'array', 'description' => 'Specific section keys only, e.g. ["ele_bg_styles"].'),
						'verbose'  => array('type' => 'boolean', 'description' => 'Raw uncompacted schema. Very large — avoid.'),
					),
					'required' => array('widget'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_widget_schema'),
			),
			'get_common_styles' => array(
				'label' => __('Get Common Style Props', 'pagelayer'),
				'description' => __('The style attributes every widget/row/column accepts (background, border, font, position, animation, motion, responsive, custom CSS). Fetch ONCE per session — get_widget_schema omits them.', 'pagelayer'),
				'category' => 'pagelayer-widgets',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'sections' => array('type' => 'array', 'description' => 'Limit to specific section keys.'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_common_styles'),
			),
			'get_widget_examples' => array(
				'label' => __('Get Widget Examples', 'pagelayer'),
				'description' => __('Canonical JSON node example for a widget, or for several at once. Not needed for anything you build with section specs.', 'pagelayer'),
				'category' => 'pagelayer-widgets',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'widget'  => array('type' => 'string'),
						'widgets' => array('type' => 'array', 'description' => 'Up to 8 tags in one call.'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_widget_examples'),
			),
			'get_widgets_summary' => array(
				'label' => __('Get Widgets Summary', 'pagelayer'),
				'description' => __('Same compact widget list as list_widgets.', 'pagelayer'),
				'category' => 'pagelayer-widgets',
				// Identical to list_widgets since both were made compact.
				'mcp_public' => false,
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'group'  => array('type' => 'string'),
						'search' => array('type' => 'string'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_widgets_summary'),
			),
			'get_library_sections' => array(
				'label' => __('Get PopularFX Library Sections', 'pagelayer'),
				'description' => __('Browse prebuilt official PopularFX and PageLayer section templates (Hero, Features, Menu, Pricing, Testimonials, Headers, Footers).', 'pagelayer'),
				'category' => 'pagelayer-templates',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'type' => array('type' => 'string', 'default' => 'sections', 'description' => 'sections, pages, header, footer')
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_library_sections'),
			),
			'import_library_section' => array(
				'label' => __('Import Library Section', 'pagelayer'),
				'description' => __('Import and insert an official PopularFX / PageLayer library section directly into a post/page by section_id.', 'pagelayer'),
				'category' => 'pagelayer-templates',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'section_id' => array('type' => 'string'),
						'post_id'    => array('type' => 'integer'),
					),
					'required' => array('section_id', 'post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_import_library_section'),
			),
			'scrape_website_content' => array(
				'label' => __('Scrape Website Content', 'pagelayer'),
				'description' => __('Fetch a URL and extract its raw content (title, headings, paragraphs, image URLs) for reference. Extraction only — it imposes no layout.', 'pagelayer'),
				'category' => 'pagelayer-templates',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'url' => array('type' => 'string', 'description' => 'Target website URL to read (e.g. https://example.com/)')
					),
					'required' => array('url'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_scrape_website_content'),
			),
			'get_all_schemas' => array(
				'label' => __('Get Schemas For Several Widgets', 'pagelayer'),
				'description' => __('Compact schemas for up to 12 named widgets in one call. The widgets list is required.', 'pagelayer'),
				'category' => 'pagelayer-widgets',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'widgets' => array('type' => 'array', 'description' => 'Widget tags, e.g. ["pl_heading","pl_btn"]. Max 12.'),
					),
					'required' => array('widgets'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_all_widget_schemas'),
			),
		);

		foreach ($abilities as $id => $def) {
			self::do_register_ability('pagelayer-widgets/' . str_replace('_', '-', $id), $def);
		}
	}

	protected static function register_global_abilities() {
		$abilities = array(
			'get_theme_settings' => array(
				'label' => __('Get Theme Settings', 'pagelayer'),
				'description' => __('Retrieve PageLayer theme options, active layout settings, header/footer assignments, WooCommerce status, and global styles.', 'pagelayer'),
				'category' => 'pagelayer-global',
				'input_schema' => array('type' => 'object', 'additionalProperties' => false),
				'execute' => array(__CLASS__, 'execute_get_theme_settings'),
			),
			'get_global_styles' => array(
				'label' => __('Get Global Styles', 'pagelayer'),
				'description' => __('Retrieve site design system tokens: global colors, global fonts, content width, and style presets.', 'pagelayer'),
				'category' => 'pagelayer-global',
				'input_schema' => array('type' => 'object', 'additionalProperties' => false),
				'execute' => array(__CLASS__, 'execute_get_styles'),
			),
			'update_global_styles' => array(
				'label' => __('Update Global Styles', 'pagelayer'),
				'description' => __('Update site design system tokens: global colors, global fonts, content width.', 'pagelayer'),
				'category' => 'pagelayer-global',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'global_colors' => array('type' => 'object'),
						'global_fonts'  => array('type' => 'object'),
						'content_width' => array('type' => 'string'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_update_styles'),
				'perm' => array(__CLASS__, 'can_manage_options')
			),
			'get_icons' => array(
				'label' => __('Get Icon Catalog', 'pagelayer'),
				'description' => __('List available FontAwesome and Pagelayer icons with category filtering and search.', 'pagelayer'),
				'category' => 'pagelayer-global',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'search'   => array('type' => 'string'),
						'category' => array('type' => 'string'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_icons'),
			),
			'get_fonts' => array(
				'label' => __('Get Font Catalog', 'pagelayer'),
				'description' => __('List Google Fonts, system fonts, and Pagelayer custom fonts available for layouts.', 'pagelayer'),
				'category' => 'pagelayer-global',
				'input_schema' => array('type' => 'object', 'additionalProperties' => false),
				'execute' => array(__CLASS__, 'execute_get_fonts'),
			),
			'get_color_presets' => array(
				'label' => __('Get Color Presets', 'pagelayer'),
				'description' => __('Retrieve curated color palette presets (Modern Agency, Sleek Dark, Vibrant Tech, Elegant Serif, Warm Minimal) as a starting point — these are optional suggestions, not mandatory themes.', 'pagelayer'),
				'category' => 'pagelayer-global',
				'input_schema' => array('type' => 'object', 'additionalProperties' => false),
				'execute' => array(__CLASS__, 'execute_get_color_presets'),
			),
			'get_spacing_presets' => array(
				'label' => __('Get Spacing Presets', 'pagelayer'),
				'description' => __('Retrieve standardized spacing scale presets (container width, section padding, column gaps, border radiuses, shadows).', 'pagelayer'),
				'category' => 'pagelayer-global',
				'input_schema' => array('type' => 'object', 'additionalProperties' => false),
				'execute' => array(__CLASS__, 'execute_get_spacing_presets'),
			),
			'search_images' => array(
				'label' => __('Search Real Images', 'pagelayer'),
				'description' => __('Search Pexels for real licensed photos and return direct URLs. Put the URL in pl_image\'s "id" attr (not "img", which does not exist). Pass "queries" with ALL the photos a page needs in ONE call — one keyword per distinct image, never reuse a result. Needs a Pexels API key on the Pagelayer AI Agents settings page.', 'pagelayer'),
				'category' => 'pagelayer-global',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'queries'     => array('type' => 'array', 'description' => 'PREFERRED: up to 10 specific keywords in one call, e.g. ["wood fired pizza oven","barista pouring latte"]. Returns {batch: {keyword: [results]}}.'),
						'query'       => array('type' => 'string', 'description' => 'Single search keyword, when you only need one photo.'),
						'per_page'    => array('type' => 'integer', 'default' => 5, 'description' => 'Results per keyword (max 20; defaults to 3 in batch mode).'),
						'orientation' => array('type' => 'string', 'description' => 'landscape, portrait, or square. Optional.'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_search_images'),
			),
		);

		foreach ($abilities as $id => $def) {
			self::do_register_ability('pagelayer-global/' . str_replace('_', '-', $id), $def);
		}
	}

	protected static function register_template_abilities() {
		$abilities = array(
			'get_templates' => array(
				'label' => __('Get Templates', 'pagelayer'),
				'description' => __('List Pagelayer theme templates (header, footer, blog_archive, single_blog, sidebar, search, 404, popup, woocommerce) and library items.', 'pagelayer'),
				'category' => 'pagelayer-templates',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('type' => array('type' => 'string')),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_templates'),
			),
			'create_template' => array(
				'label' => __('Create Template', 'pagelayer'),
				'description' => __('Create a reusable theme template or library section with pagelayer_data and display conditions. header/footer templates are always saved with the Include / Full Site display condition. A header must contain the Primary Menu widget (pl_wp_menu) bound to a real menu — build it with create_menu first — unless single_page_site:true.', 'pagelayer'),
				'category' => 'pagelayer-templates',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'title'          => array('type' => 'string'),
						'type'           => array('type' => 'string', 'description' => 'header, footer, blog_archive, single_blog, sidebar, search, 404, popup, woocommerce_shop, woocommerce_product, general'),
						'pagelayer_data' => array('type' => 'object'),
						'conditions'     => array('type' => 'array', 'description' => 'Display conditions: [{type:"include"|"exclude", template:""|"archives"|"singular", sub_template:"", id:""}]. An empty "template" means Full Site. header/footer templates always get the Include / Full Site rule, added back if you omit it.'),
						'single_page_site' => array('type' => 'boolean', 'description' => 'Only for a header on a genuine ONE-PAGE site whose nav links are in-page anchors. Opts out of the requirement that a header contains the Primary Menu widget.'),
					),
					'required' => array('title', 'type', 'pagelayer_data'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_create_template'),
			),
			'update_template' => array(
				'label' => __('Update Template', 'pagelayer'),
				'description' => __('Update an existing Pagelayer theme template title, layout data, or display conditions. Same header/footer rules as create_template: Include / Full Site is enforced, and a header needs a Primary Menu widget with a real nav_list.', 'pagelayer'),
				'category' => 'pagelayer-templates',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'template_id'    => array('type' => 'integer'),
						'title'          => array('type' => 'string'),
						'type'           => array('type' => 'string'),
						'pagelayer_data' => array('type' => 'object'),
						'conditions'     => array('type' => 'array'),
						'single_page_site' => array('type' => 'boolean'),
					),
					'required' => array('template_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_update_template'),
			),
			'delete_template' => array(
				'label' => __('Delete Template', 'pagelayer'),
				'description' => __('Delete a Pagelayer theme template by ID.', 'pagelayer'),
				'category' => 'pagelayer-templates',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('template_id' => array('type' => 'integer')),
					'required' => array('template_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_delete_template'),
			),
			'save_template' => array(
				'label' => __('Save Section Template', 'pagelayer'),
				'description' => __('Save a specific page section or layout to the local template library.', 'pagelayer'),
				'category' => 'pagelayer-templates',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'name'       => array('type' => 'string'),
						'post_id'    => array('type' => 'integer'),
						'element_id' => array('type' => 'string'),
					),
					'required' => array('name', 'post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_save_template'),
			),
			'insert_template' => array(
				'label' => __('Insert Template', 'pagelayer'),
				'description' => __('Insert a saved template layout structure into a target container on a page.', 'pagelayer'),
				'category' => 'pagelayer-templates',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'name'      => array('type' => 'string'),
						'post_id'   => array('type' => 'integer'),
						'parent_id' => array('type' => 'string'),
						'index'     => array('type' => 'integer'),
					),
					'required' => array('name', 'post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_insert_template'),
			),
		);

		foreach ($abilities as $id => $def) {
			self::do_register_ability('pagelayer-templates/' . str_replace('_', '-', $id), $def);
		}
	}

	/**
	 * The Primary Menu widget (pl_wp_menu) renders a real WordPress nav menu by
	 * term id — it has no item list of its own. Without these abilities an AI
	 * client can drop the widget into a header and the header renders an empty
	 * <ul>, so building the menu has to be part of the same toolset.
	 */
	protected static function register_menu_abilities() {
		$abilities = array(
			'get_menus' => array(
				'label' => __('Get Navigation Menus', 'pagelayer'),
				'description' => __('List every WordPress nav menu with its term id and item tree, plus the theme\'s registered menu locations and what is assigned to them. The term id is what goes in the Primary Menu widget\'s "nav_list" attribute.', 'pagelayer'),
				'category' => 'pagelayer-menus',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'menu' => array('type' => 'string', 'description' => 'Optional name, slug or term id to return just one menu.'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_menus'),
			),
			'create_menu' => array(
				'label' => __('Create / Update Navigation Menu', 'pagelayer'),
				'description' => __('Create a WordPress nav menu (or rebuild an existing one by the same name) from a list of items, optionally assigning it to a theme menu location. Items can nest via "children" and can carry Pagelayer per-item settings — menu_type "mega" with mega_content builds a real Mega Menu dropdown. Returns the menu term id to put in the Primary Menu widget\'s "nav_list" attribute. Call this BEFORE building the header template.', 'pagelayer'),
				'category' => 'pagelayer-menus',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'name'          => array('type' => 'string', 'description' => 'Menu name, e.g. "Primary Menu". An existing menu with this name is reused.'),
						'location'      => array('type' => 'string', 'description' => 'Optional theme menu location slug to assign this menu to (see get_menus.locations).'),
						'replace_items' => array('type' => 'boolean', 'description' => 'Default true — the menu ends up containing exactly the items sent. false appends instead.'),
						'items'         => array('type' => 'array', 'description' => 'Item objects: {title, page_id|post_id|url, target, children:[...], menu_type:""|"mega"|"column", mega_content:[pl_inner_row nodes], mega_width, mega_custom_width, columns, col_gap, menu_icon, highlight_label, disable_link}.'),
					),
					'required' => array('name', 'items'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_create_menu'),
				'perm' => array(__CLASS__, 'can_manage_options'),
			),
			'delete_menu' => array(
				'label' => __('Delete Navigation Menu', 'pagelayer'),
				'description' => __('Delete a WordPress nav menu by name, slug or term id.', 'pagelayer'),
				'category' => 'pagelayer-menus',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('menu' => array('type' => 'string')),
					'required' => array('menu'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_delete_menu'),
				'perm' => array(__CLASS__, 'can_manage_options'),
			),
		);

		foreach ($abilities as $id => $def) {
			self::do_register_ability('pagelayer-menus/' . str_replace('_', '-', $id), $def);
		}
	}

	protected static function register_pages_abilities() {
		$abilities = array(
			'create_website' => array(
				'label' => __('Create Entire Website', 'pagelayer'),
				'description' => __('Generate a full multi-page website for any niche in ONE call — always prefer this over repeated create_page. Build each page out of section specs ({"section":"hero",...}, see fast_path_sections in get_data_structure) and fetch every photo in one batched search_images call first. Before calling, read get_data_structure with topic:"all" — it carries the node format, the global colour tokens, the gated-attribute rule and the enforced content-quality gate that decides whether a page is accepted. A failing page is rejected unsaved and the error lists what to fix. Pages are created first, then a Primary Menu from those pages (unless single_page_site), then the templates — so a header can bind its Primary Menu widget to a real menu (leave nav_list empty or "auto" in a header you pass and it is filled in for you). Auto-creates minimal Header/Footer templates only if none exist.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'site_name'       => array('type' => 'string'),
						'primary_color'   => array('type' => 'string', 'description' => 'Fallback color used only for the auto-generated bare header/footer scaffolding if none exist.'),
						'global_colors'   => array('type' => 'object'),
						'global_fonts'    => array('type' => 'object'),
						'content_width'   => array('type' => 'string'),
						'pages'           => array('type' => 'array', 'description' => 'List of page objects to create (title, pagelayer_data, status, skip_validation, ...)'),
						'theme_templates' => array('type' => 'array'),
						'menu'            => array('type' => 'object', 'description' => 'Optional nav menu spec {name, location, items:[...]} in create_menu format. Omit and a "Primary Menu" is built from the pages created here (homepage first) and assigned to a free theme location.'),
						'single_page_site' => array('type' => 'boolean', 'description' => 'Set ONLY when the user asked for a one-page site. Skips menu creation; header links are expected to be in-page anchors.'),
					),
					'required' => array('site_name', 'pages'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_create_website'),
			),
			'create_page' => array(
				'label' => __('Create Page', 'pagelayer'),
				'description' => __('Create one page with Pagelayer builder data, status and global styles. FASTEST PATH: send section specs ({"section":"hero",...}) instead of hand-written node trees — see fast_path_sections in get_data_structure. Enforced quality gate: rejected unsaved if any widget keeps its placeholder text, uses an attr not in its schema, sets a gated attr without its companion, or uses an unregistered tag. Styling must live in attrs — an inline style attribute or <style> block in rich text is rejected and cannot be bypassed. Missing images are only a warning. Read get_data_structure and get_widget_schema first.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'title'           => array('type' => 'string'),
						'pagelayer_data'  => array('type' => 'object'),
						'status'          => array('type' => 'string', 'default' => 'publish'),
						'is_homepage'     => array('type' => 'boolean'),
						'is_posts_page'   => array('type' => 'boolean'),
						'global_colors'   => array('type' => 'object'),
						'global_fonts'    => array('type' => 'object'),
						'content_width'   => array('type' => 'string'),
						'skip_validation' => array('type' => 'boolean', 'description' => 'Bypass the content-quality gate for an intentional unfinished draft. Not recommended.'),
					),
					'required' => array('title', 'pagelayer_data'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_create_page'),
			),
			'update_page' => array(
				'label' => __('Update Page', 'pagelayer'),
				'description' => __('Update page title, status, or the WHOLE pagelayer_data tree. Sending pagelayer_data REPLACES the entire layout and discards any human edits made in the editor since — to change part of a page use update_element/add_element/change_styles instead, which are far cheaper and non-destructive. Same enforced quality gate as create_page, including the no-inline-CSS-in-rich-text rule.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'         => array('type' => 'integer'),
						'title'           => array('type' => 'string'),
						'pagelayer_data'  => array('type' => 'object'),
						'status'          => array('type' => 'string'),
						'skip_validation' => array('type' => 'boolean', 'description' => 'Bypass the content-quality gate for an intentional unfinished draft. Not recommended.'),
					),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_update_page'),
			),
			'get_page' => array(
				'label' => __('Get Page', 'pagelayer'),
				'description' => __('Page details plus a compact outline of its elements (id, tag, text preview) — enough to locate anything you want to edit. Pass element_id for one node in full, or mode:"full" for the whole raw tree (large; only needed to rewrite the entire layout).', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'    => array('type' => 'integer'),
						'mode'       => array('type' => 'string', 'description' => 'outline (default) | full'),
						'element_id' => array('type' => 'string', 'description' => 'Return just this node, in full.'),
					),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_page'),
			),
			'list_pages' => array(
				'label' => __('List Pages', 'pagelayer'),
				'description' => __('List WordPress pages built with Pagelayer.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'limit'  => array('type' => 'integer', 'default' => 20),
						'status' => array('type' => 'string', 'default' => 'any'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_list_pages'),
			),
			'publish_page' => array(
				'label' => __('Publish Page', 'pagelayer'),
				'description' => __('Change page status to publish.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('post_id' => array('type' => 'integer')),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_publish_page'),
			),
			'duplicate_page' => array(
				'label' => __('Duplicate Page', 'pagelayer'),
				'description' => __('Clone an existing page and regenerate all Pagelayer element IDs.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id' => array('type' => 'integer'),
						'title'   => array('type' => 'string'),
					),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_duplicate_page'),
			),
			'delete_page' => array(
				'label' => __('Delete Page', 'pagelayer'),
				'description' => __('Trash or delete a page by ID.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id' => array('type' => 'integer'),
						'force'   => array('type' => 'boolean', 'default' => false),
					),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_delete_page'),
			),
			'preview_page' => array(
				'label' => __('Preview Page', 'pagelayer'),
				'description' => __('Retrieve the live view/preview URL for a post or page.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('post_id' => array('type' => 'integer')),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_preview'),
			),
			'validate_page' => array(
				'label' => __('Validate Page Layout', 'pagelayer'),
				'description' => __('Perform comprehensive AI validation checks (widget compatibility, structure, responsiveness, accessibility, SEO, global styles token usage).', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'        => array('type' => 'integer'),
						'pagelayer_data' => array('type' => 'object'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_validate_page'),
			),
			'create_design_ui' => array(
				'label' => __('Create Design UI Sections', 'pagelayer'),
				'description' => __('Append custom UI section nodes to an existing page. ENFORCED content-quality gate on the appended nodes — no placeholder text, no missing images, no unregistered widgets. Pass skip_validation:true to bypass for an intentional draft.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'         => array('type' => 'integer'),
						'pagelayer_data'  => array('type' => 'object'),
						'skip_validation' => array('type' => 'boolean'),
					),
					'required' => array('post_id', 'pagelayer_data'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_create_design_ui'),
			),
			'edit_layout' => array(
				'label' => __('Edit Page Layout', 'pagelayer'),
				'description' => __('Replace the full layout structure of a Pagelayer page. ENFORCED content-quality gate on the new layout — no placeholder text, no missing images, no unregistered widgets. Pass skip_validation:true to bypass for an intentional draft.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				// Same full-layout replace as update_page with pagelayer_data.
				'mcp_public' => false,
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'         => array('type' => 'integer'),
						'pagelayer_data'  => array('type' => 'object'),
						'skip_validation' => array('type' => 'boolean'),
					),
					'required' => array('post_id', 'pagelayer_data'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_edit_layout'),
			),
			'change_styles' => array(
				'label' => __('Change Element Styles', 'pagelayer'),
				'description' => __('Batch update style properties on page elements by ID or widget tag.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id' => array('type' => 'integer'),
						'styles'  => array('type' => 'array'),
					),
					'required' => array('post_id', 'styles'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_change_styles'),
			),
			'get_data_structure' => array(
				'label' => __('Get Data Structure Guide', 'pagelayer'),
				'description' => __('How pagelayer_data nodes, global $color tokens and gated attributes work. Default topic covers editing; pass topic:"quality"/"widgets"/"workflow"/"all" when building a page from scratch.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'topic' => array('type' => 'string', 'description' => 'core (default) | quality | widgets | navigation | workflow | all'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_data_structure'),
			),
			'find_elements' => array(
				'label' => __('Find Elements', 'pagelayer'),
				'description' => __('Find elements on a page by tag and/or text. Returns compact "id tag text" lines; matches text held in attrs as well as node content.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'       => array('type' => 'integer'),
						'tag'           => array('type' => 'string'),
						'query'         => array('type' => 'string'),
						'include_attrs' => array('type' => 'boolean', 'description' => 'Include every attr of each match. Large — usually unnecessary.'),
					),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_find_elements'),
			),
			'navigator' => array(
				'label' => __('Page Navigator Outline', 'pagelayer'),
				'description' => __('Indented outline of every row, column and widget on a page with ids and text previews. Same data as get_page in outline mode.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				// get_page returns this same outline by default.
				'mcp_public' => false,
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('post_id' => array('type' => 'integer')),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_navigator'),
			),
			'update_element' => array(
				'label' => __('Update Element', 'pagelayer'),
				'description' => __('Change attrs and/or content of one node by pagelayer-id — the cheapest way to edit an existing page. Given attrs are merged, not replaced. Enforced quality gate on the resulting node (no placeholder text, valid attr names, gated attrs sent with their companion); skip_validation:true bypasses it. Inline CSS in the content/attrs you send is always rejected.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'         => array('type' => 'integer'),
						'element_id'      => array('type' => 'string', 'description' => 'pagelayer-id or "@0.1.2" position path, as shown in the get_page outline.'),
						'attrs'           => array('type' => 'object'),
						'content'         => array('type' => 'string'),
						'skip_validation' => array('type' => 'boolean'),
					),
					'required' => array('post_id', 'element_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_update_element'),
			),
			'add_element' => array(
				'label' => __('Add Element', 'pagelayer'),
				'description' => __('Insert a new element node into a parent container at an index. Enforced quality gate on the new element (no placeholder text, valid attrs, registered tag); skip_validation:true bypasses it. Inline CSS in rich text is always rejected.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'         => array('type' => 'integer'),
						'parent_id'       => array('type' => 'string'),
						'element'         => array('type' => 'object'),
						'index'           => array('type' => 'integer'),
						'skip_validation' => array('type' => 'boolean'),
					),
					'required' => array('post_id', 'element'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_add_element'),
			),
			'delete_element' => array(
				'label' => __('Delete Element', 'pagelayer'),
				'description' => __('Remove an element from a page by its pagelayer-id.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'    => array('type' => 'integer'),
						'element_id' => array('type' => 'string'),
					),
					'required' => array('post_id', 'element_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_delete_element'),
			),
			'move_element' => array(
				'label' => __('Move Element', 'pagelayer'),
				'description' => __('Relocate an element to a target parent container or index.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'    => array('type' => 'integer'),
						'element_id' => array('type' => 'string'),
						'parent_id'  => array('type' => 'string'),
						'index'      => array('type' => 'integer'),
					),
					'required' => array('post_id', 'element_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_move_element'),
			),
			'duplicate_element' => array(
				'label' => __('Duplicate Element', 'pagelayer'),
				'description' => __('Clone an element by ID, generating new IDs for all child nodes.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'    => array('type' => 'integer'),
						'element_id' => array('type' => 'string'),
					),
					'required' => array('post_id', 'element_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_duplicate_element'),
			),
			'begin_transaction' => array(
				'label' => __('Begin Transaction', 'pagelayer'),
				'description' => __('Backup page layout state before multi-step modifications.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('post_id' => array('type' => 'integer')),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_begin_transaction'),
			),
			'commit_transaction' => array(
				'label' => __('Commit Transaction', 'pagelayer'),
				'description' => __('Commit layout changes and delete backup state.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('post_id' => array('type' => 'integer')),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_commit_transaction'),
			),
			'rollback_transaction' => array(
				'label' => __('Rollback Transaction', 'pagelayer'),
				'description' => __('Restore original page layout state from transaction backup.', 'pagelayer'),
				'category' => 'pagelayer-pages',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('post_id' => array('type' => 'integer')),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_rollback_transaction'),
			),
		);

		foreach ($abilities as $id => $def) {
			self::do_register_ability('pagelayer-pages/' . str_replace('_', '-', $id), $def);
		}
	}

	protected static function register_posts_abilities() {
		$abilities = array(
			'create_post' => array(
				'label' => __('Create Individual Post', 'pagelayer'),
				'description' => __('Create a blog post built with Pagelayer, specifying categories, tags, excerpt, featured image, and layout.', 'pagelayer'),
				'category' => 'pagelayer-posts',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'title'          => array('type' => 'string'),
						'pagelayer_data' => array('type' => 'object'),
						'status'         => array('type' => 'string', 'default' => 'publish'),
						'categories'     => array('type' => 'array', 'items' => array('type' => 'string')),
						'tags'           => array('type' => 'array', 'items' => array('type' => 'string')),
						'excerpt'        => array('type' => 'string'),
						'featured_image' => array('type' => 'string'),
						'global_colors'  => array('type' => 'object'),
						'global_fonts'   => array('type' => 'object'),
						'content_width'  => array('type' => 'string'),
					),
					'required' => array('title', 'pagelayer_data'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_create_post'),
			),
			'update_post' => array(
				'label' => __('Update Individual Post', 'pagelayer'),
				'description' => __('Update an existing blog post title, layout data, categories, tags, excerpt, or status.', 'pagelayer'),
				'category' => 'pagelayer-posts',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id'        => array('type' => 'integer'),
						'title'          => array('type' => 'string'),
						'pagelayer_data' => array('type' => 'object'),
						'status'         => array('type' => 'string'),
						'categories'     => array('type' => 'array', 'items' => array('type' => 'string')),
						'tags'           => array('type' => 'array', 'items' => array('type' => 'string')),
						'excerpt'        => array('type' => 'string'),
						'featured_image' => array('type' => 'string'),
					),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_update_post'),
			),
			'get_post' => array(
				'label' => __('Get Individual Post', 'pagelayer'),
				'description' => __('Retrieve blog post details, categories, tags, excerpt, featured image, and pagelayer_data.', 'pagelayer'),
				'category' => 'pagelayer-posts',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('post_id' => array('type' => 'integer')),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_get_post'),
			),
			'list_posts' => array(
				'label' => __('List Blog Posts', 'pagelayer'),
				'description' => __('List WordPress blog posts built with Pagelayer.', 'pagelayer'),
				'category' => 'pagelayer-posts',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'limit'    => array('type' => 'integer', 'default' => 20),
						'status'   => array('type' => 'string', 'default' => 'any'),
						'category' => array('type' => 'string'),
					),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_list_posts'),
			),
			'publish_post' => array(
				'label' => __('Publish Individual Post', 'pagelayer'),
				'description' => __('Publish a draft blog post.', 'pagelayer'),
				'category' => 'pagelayer-posts',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array('post_id' => array('type' => 'integer')),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_publish_post'),
			),
			'duplicate_post' => array(
				'label' => __('Duplicate Individual Post', 'pagelayer'),
				'description' => __('Clone an existing blog post with new Pagelayer element IDs.', 'pagelayer'),
				'category' => 'pagelayer-posts',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id' => array('type' => 'integer'),
						'title'   => array('type' => 'string'),
					),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_duplicate_post'),
			),
			'delete_post' => array(
				'label' => __('Delete Individual Post', 'pagelayer'),
				'description' => __('Trash or delete a blog post by ID.', 'pagelayer'),
				'category' => 'pagelayer-posts',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'post_id' => array('type' => 'integer'),
						'force'   => array('type' => 'boolean', 'default' => false),
					),
					'required' => array('post_id'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_delete_post'),
			),
		);

		foreach ($abilities as $id => $def) {
			self::do_register_ability('pagelayer-posts/' . str_replace('_', '-', $id), $def);
		}
	}

	protected static function register_media_abilities() {
		$abilities = array(
			'upload_media' => array(
				'label' => __('Upload Media', 'pagelayer'),
				'description' => __('Sideload an image from a URL into the WordPress Media Library.', 'pagelayer'),
				'category' => 'pagelayer-media',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(
						'url'      => array('type' => 'string'),
						'alt_text' => array('type' => 'string'),
					),
					'required' => array('url'),
					'additionalProperties' => false
				),
				'execute' => array(__CLASS__, 'execute_upload_media'),
			),
		);

		foreach ($abilities as $id => $def) {
			self::do_register_ability('pagelayer-media/' . str_replace('_', '-', $id), $def);
		}
	}

	protected static function do_register_ability($id, $def) {
		$perm = isset($def['perm']) ? $def['perm'] : array(__CLASS__, 'can_edit_posts');

		// Every exposed tool's description and input schema is re-sent to the
		// model on EVERY request, so a duplicate tool costs tokens forever, not
		// once. Abilities marked mcp_public=false stay fully registered and
		// callable over the REST abilities API, but the MCP adapter leaves them
		// out of the advertised tool list (DefaultServerFactory filters on this
		// flag). Flip one back to true to re-expose it.
		$mcp_public = array_key_exists('mcp_public', $def) ? (bool)$def['mcp_public'] : true;

		wp_register_ability($id, array(
			'label'               => $def['label'],
			'description'         => $def['description'],
			'category'            => $def['category'],
			'input_schema'        => $def['input_schema'],
			'execute_callback'    => $def['execute'],
			'permission_callback' => $perm,
			'meta'                => array('show_in_rest' => true, 'mcp' => array('public' => $mcp_public)),
		));
	}

	// ==================================================================
	// EXECUTE CALLBACK IMPLEMENTATIONS
	// ==================================================================

	// ------------------------------------------------------------------
	// Widget Callbacks
	// ------------------------------------------------------------------

	/**
	 * Was a near-duplicate of get_widgets_summary that cost slightly MORE
	 * tokens (the extra field it carried, `icon`, is a dashicon class the model
	 * has no use for). Both now share one compact representation.
	 */
	public static function execute_list_widgets($input) {
		return self::execute_get_widgets_summary($input);
	}

	public static function execute_get_widget($input) {
		$widget_id = isset($input['widget']) ? sanitize_text_field($input['widget']) : '';
		self::ensure_shortcodes_loaded();
		global $pagelayer;

		if (!isset($pagelayer->shortcodes[$widget_id])) {
			return new \WP_Error('invalid_widget', __('Widget not found.', 'pagelayer'));
		}

		$data = $pagelayer->shortcodes[$widget_id];
		return array(
			'widget' => array(
				'id'         => $widget_id,
				'name'       => isset($data['name']) ? $data['name'] : $widget_id,
				'group'      => isset($data['group']) ? $data['group'] : 'misc',
				'holder'     => isset($data['holder']) ? $data['holder'] : '',
				'parent'     => isset($data['parent']) ? $data['parent'] : array(),
				'has_group'  => isset($data['has_group']) ? $data['has_group'] : array(),
				'settings'   => isset($data['settings']) ? $data['settings'] : array(),
				'options'    => isset($data['options']) ? $data['options'] : array(),
			)
		);
	}

	public static function execute_get_widget_schema($input) {
		$widget_id = isset($input['widget']) ? sanitize_text_field($input['widget']) : (isset($input['widget_id']) ? sanitize_text_field($input['widget_id']) : '');
		$schema    = self::get_widget_schema($widget_id);

		if (!$schema) {
			return new \WP_Error('invalid_widget', __('Widget not found.', 'pagelayer'));
		}

		if (!empty($input['verbose'])) {
			return array('widget' => $schema);
		}

		$only = isset($input['sections']) && is_array($input['sections']) ? array_map('sanitize_text_field', $input['sections']) : array();
		$mode = isset($input['mode']) ? sanitize_text_field($input['mode']) : 'own';

		return array('widget' => self::compact_widget_schema($schema, $mode, $only));
	}

	/**
	 * The ten style sections every widget inherits, emitted once instead of
	 * once per widget. Derived from a live widget rather than hardcoded so it
	 * cannot drift from what pagelayer_add_shortcode() actually attaches.
	 */
	public static function execute_get_common_styles($input) {
		global $pagelayer;
		self::ensure_shortcodes_loaded();

		$probe = isset($pagelayer->shortcodes['pl_heading']) ? 'pl_heading' : key($pagelayer->shortcodes);
		if (!$probe) {
			return new \WP_Error('no_widgets', __('No widgets are registered.', 'pagelayer'));
		}

		$schema = self::extract_widget_schema($probe, $pagelayer->shortcodes[$probe]);
		$only   = isset($input['sections']) && is_array($input['sections']) ? array_map('sanitize_text_field', $input['sections']) : array();
		$compact = self::compact_widget_schema($schema, 'shared', $only);

		return array(
			'common_styles' => $compact['props'],
			'legend'        => self::compact_legend(),
			'note'          => 'These style props are accepted by EVERY Pagelayer widget, row and column. Fetch this once per session — get_widget_schema omits them and returns only widget-specific props. A widget may drop a few via its own "unsupported_props".',
			'styling_rule'  => 'Styling goes in attrs, never into rich text. A style="" attribute or <style> block inside node.content or any text/editor attribute is rejected outright by the write abilities. When nothing in this list or in the widget\'s own schema covers what you need, write a real CSS rule in the "ele_css" attribute of that node using {{element}} as the selector, e.g. "{{element}} .pagelayer-heading-holder h2 { letter-spacing: 2px; }" — that is the one sanctioned place for hand-written CSS.',
		);
	}

	/**
	 * Dumping all 125 examples is ~9k tokens for a payload the model asked for
	 * by accident — one forgotten argument used to cost more context than the
	 * whole page it was about to write. Bare calls now return the widget list
	 * instead, which is what the caller actually needed to pick one.
	 */
	public static function execute_get_widget_examples($input) {
		$widget_id = isset($input['widget']) ? sanitize_text_field($input['widget']) : '';

		if ($widget_id === '') {
			$widgets = isset($input['widgets']) && is_array($input['widgets']) ? array_map('sanitize_text_field', $input['widgets']) : array();
			if (empty($widgets)) {
				return array(
					'examples' => array(),
					'error'    => 'Pass "widget" (one tag) or "widgets" (a few tags). Fetching all 125 examples costs more context than the page you are building — call list_widgets to choose first.',
				);
			}

			$out = array();
			foreach (array_slice($widgets, 0, 8) as $tag) {
				$one = self::get_widget_examples($tag);
				if (!empty($one)) {
					$out = array_merge($out, is_array($one) ? $one : array($one));
				}
			}
			return array('examples' => $out);
		}

		return array('examples' => self::get_widget_examples($widget_id));
	}

	/**
	 * Batch schema fetch. The unbounded form used to serialize every section of
	 * all 125 widgets — 3.6MB, roughly 900k tokens, which no client can hold.
	 * It now requires an explicit widget list and returns the compact form.
	 */
	public static function execute_get_all_widget_schemas($input) {
		self::ensure_shortcodes_loaded();
		global $pagelayer;

		$wanted = isset($input['widgets']) && is_array($input['widgets']) ? array_map('sanitize_text_field', $input['widgets']) : array();

		if (empty($wanted)) {
			return new \WP_Error(
				'widgets_required',
				__('Pass widgets:["pl_heading","pl_btn"] — the schemas for all registered widgets are several hundred thousand tokens and will not fit in context. Use get_widgets_summary to pick the widgets you need first.', 'pagelayer')
			);
		}

		if (count($wanted) > 12) {
			$wanted = array_slice($wanted, 0, 12);
		}

		$out     = array();
		$unknown = array();
		foreach ($wanted as $tag) {
			if (!isset($pagelayer->shortcodes[$tag])) {
				$unknown[] = $tag;
				continue;
			}
			$schema = self::extract_widget_schema($tag, $pagelayer->shortcodes[$tag]);
			$compact = self::compact_widget_schema($schema, 'own');
			// The legend and shared-section note are identical for every widget;
			// hoist them out of the per-widget payloads.
			unset($compact['legend'], $compact['shared_note'], $compact['shared_style_sections']);
			$out[$tag] = $compact;
		}

		$result = array('widgets' => $out, 'legend' => self::compact_legend());
		if (!empty($unknown)) {
			$result['unknown_widgets'] = $unknown;
		}
		$result['note'] = 'Widget-specific props only. Call get_common_styles once for the style props shared by all widgets.';

		return $result;
	}

	public static function execute_get_widgets_summary($input) {
		self::ensure_shortcodes_loaded();
		global $pagelayer;

		$group_filter = isset($input['group']) ? sanitize_text_field($input['group']) : '';
		$search       = isset($input['search']) ? strtolower(sanitize_text_field($input['search'])) : '';

		// One line per widget: "Display Name|group|children|parent1,parent2".
		// The repeated JSON keys in the old object-per-widget shape were most of
		// the payload, and the model needs none of them to pick a widget.
		$summary = array();
		if (!empty($pagelayer->shortcodes) && is_array($pagelayer->shortcodes)) {
			foreach ($pagelayer->shortcodes as $tag => $data) {
				$group = isset($data['group']) ? $data['group'] : 'misc';
				if ($group_filter && $group !== $group_filter) {
					continue;
				}
				$name = isset($data['name']) ? $data['name'] : $tag;
				if ($search && strpos(strtolower($name . ' ' . $tag), $search) === false) {
					continue;
				}

				$line = $name . '|' . $group;
				if (!empty($data['holder']) || !empty($data['has_group'])) {
					$line .= '|children';
				}
				if (!empty($data['parent'])) {
					$line .= '|in:' . implode(',', (array)$data['parent']);
				}
				$summary[$tag] = $line;
			}
		}

		return array(
			'widgets' => $summary,
			'legend'  => 'tag => "Display Name|group[|children][|in:required parent tags]"',
		);
	}

	public static function execute_get_library_sections($input) {
		$type = isset($input['type']) ? sanitize_text_field($input['type']) : 'sections';
		$url  = 'https://api.pagelayer.com/library.php?give=' . rawurlencode($type);

		$res = wp_remote_get($url, array('timeout' => 30));
		if (is_wp_error($res)) {
			return array('error' => $res->get_error_message(), 'sections' => array());
		}

		$body = wp_remote_retrieve_body($res);
		$data = json_decode($body, true);

		return array(
			'type'    => $type,
			'library' => is_array($data) ? $data : array()
		);
	}

	public static function execute_import_library_section($input) {
		$section_id = isset($input['section_id']) ? sanitize_text_field($input['section_id']) : '';
		$post_id    = isset($input['post_id']) ? (int) $input['post_id'] : 0;

		if (empty($section_id) || !$post_id || !get_post($post_id)) {
			return new \WP_Error('invalid_input', __('Valid section_id and post_id are required.', 'pagelayer'));
		}

		global $pagelayer;
		$license_key = !empty($pagelayer->license['license']) ? $pagelayer->license['license'] : '';
		$url = 'https://api.pagelayer.com/library.php?give_id=' . rawurlencode($section_id) . '&license=' . rawurlencode($license_key) . '&url=' . rawurlencode(site_url());

		$res = wp_remote_get($url, array('timeout' => 60));
		if (is_wp_error($res)) {
			return $res;
		}

		$body = wp_remote_retrieve_body($res);
		$data = json_decode($body, true);

		if (empty($data['code'])) {
			return new \WP_Error('import_failed', __('Could not retrieve section data from library.', 'pagelayer'));
		}

		if (preg_match_all('/"'.preg_quote('{{pl_lib_images}}', '/').'([^"]*)"/is', $data['code'], $matches)) {
			$urls = array();
			foreach ($matches[0] as $v) {
				$img_url = trim($v, '"\'');
				$urls[$img_url] = $img_url;
			}
			foreach ($urls as $img_url) {
				$filename = basename($img_url);
				if (!empty($data[$filename])) {
					$attachment_id = pagelayer_upload_media($filename, base64_decode($data[$filename]));
					if (!empty($attachment_id)) {
						$data['code'] = str_replace('"'.$img_url.'"', '"'.$attachment_id.'"', $data['code']);
					}
				}
			}
		}

		$blocks_content = get_post_field('post_content', $post_id);
		$blocks_content .= "\n" . $data['code'];

		wp_update_post(array(
			'ID'           => $post_id,
			'post_content' => $blocks_content
		));

		return array(
			'success'    => true,
			'section_id' => $section_id,
			'post_id'    => $post_id,
			'url'        => get_permalink($post_id)
		);
	}

	/**
	 * Extract real, raw content from a live URL: title, headings, paragraph
	 * snippets and image URLs. This performs NO design decisions and creates
	 * NO page — it only gives the AI real source material to work from. The
	 * caller is expected to design the actual pagelayer_data using
	 * list_widgets / get_widget_schema / get_widget_examples / get_data_structure
	 * and its own judgement about layout, matching the requested niche/brand.
	 */
	public static function execute_scrape_website_content($input) {
		$url = isset($input['url']) ? esc_url_raw($input['url']) : '';
		if (empty($url)) {
			return new \WP_Error('missing_url', __('URL is required.', 'pagelayer'));
		}

		$response = wp_remote_get($url, array(
			'timeout'     => 25,
			'redirection' => 5,
			'sslverify'   => false,
			'headers'     => array('User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
		));

		if (is_wp_error($response)) {
			return $response;
		}

		$html = wp_remote_retrieve_body($response);
		if (empty($html)) {
			return new \WP_Error('empty_response', __('Could not retrieve any content from the target URL.', 'pagelayer'));
		}

		$title = '';
		if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
			$title = trim(html_entity_decode(strip_tags($m[1])));
		}

		$meta_description = '';
		if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/is', $html, $m)) {
			$meta_description = trim(html_entity_decode(strip_tags($m[1])));
		}

		$extract_tag_text = function($tag) use ($html) {
			$out = array();
			if (preg_match_all('/<' . $tag . '[^>]*>(.*?)<\/' . $tag . '>/is', $html, $m)) {
				foreach ($m[1] as $t) {
					$clean = trim(html_entity_decode(strip_tags($t)));
					if ($clean !== '') {
						$out[] = $clean;
					}
				}
			}
			return $out;
		};

		$h1_list = $extract_tag_text('h1');
		$h2_list = $extract_tag_text('h2');
		$h3_list = $extract_tag_text('h3');
		$p_list  = array_slice($extract_tag_text('p'), 0, 12);

		$images = array();
		if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/is', $html, $m)) {
			$parsed = parse_url($url);
			$base   = (!empty($parsed['scheme']) ? $parsed['scheme'] : 'https') . '://' . (!empty($parsed['host']) ? $parsed['host'] : '');
			foreach ($m[1] as $img_src) {
				if (strpos($img_src, 'data:image') === 0) continue;
				if (strpos($img_src, '//') === 0) {
					$img_src = 'https:' . $img_src;
				} elseif (strpos($img_src, 'http') !== 0) {
					$img_src = $base . '/' . ltrim($img_src, '/');
				}
				$images[] = $img_src;
				if (count($images) >= 20) break;
			}
		}

		return array(
			'source_url'        => $url,
			'title'              => $title,
			'meta_description'   => $meta_description,
			'headings'           => array(
				'h1' => $h1_list,
				'h2' => $h2_list,
				'h3' => $h3_list,
			),
			'paragraphs'         => $p_list,
			'images'             => $images,
			'note'               => __('This is raw extracted content only. Use list_widgets, get_widget_schema, get_widget_examples, get_color_presets/get_fonts, and get_data_structure to design an original page layout around this content — do not fabricate facts (pricing, testimonials, features) that were not actually found here.', 'pagelayer'),
		);
	}

	// ------------------------------------------------------------------
	// Global Style & Preset Callbacks
	// ------------------------------------------------------------------

	public static function execute_get_theme_settings($input) {
		$options       = get_option('pagelayer_options', array());
		$global_colors = json_decode(get_option('pagelayer_global_colors', '[]'), true);
		$global_fonts  = json_decode(get_option('pagelayer_global_fonts', '[]'), true);
		$content_width = get_option('pagelayer_content_width', '1170');

		return array(
			'site_name'         => get_option('blogname'),
			'site_description'  => get_option('blogdescription'),
			'active_theme'      => wp_get_theme()->get('Name'),
			'content_width'     => $content_width,
			'woocommerce_active'=> class_exists('WooCommerce'),
			'global_colors'     => is_array($global_colors) ? $global_colors : (object) array(),
			'global_fonts'      => is_array($global_fonts) ? $global_fonts : (object) array(),
			'options'           => $options,
		);
	}

	public static function execute_get_styles($input) {
		$global_colors = json_decode(get_option('pagelayer_global_colors', '[]'), true);
		$global_fonts  = json_decode(get_option('pagelayer_global_fonts', '[]'), true);
		$content_width = get_option('pagelayer_content_width', '1170');

		return array(
			'global_colors' => is_array($global_colors) ? $global_colors : (object) array(),
			'global_fonts'  => is_array($global_fonts) ? $global_fonts : (object) array(),
			'content_width' => $content_width,
		);
	}

	public static function execute_update_styles($input) {
		self::maybe_update_global_styles($input);
		return array('success' => true);
	}

	public static function execute_get_icons($input) {
		$search   = isset($input['search']) ? strtolower(sanitize_text_field($input['search'])) : '';
		$category = isset($input['category']) ? strtolower(sanitize_text_field($input['category'])) : '';

		$icons = array(
			array('class' => 'fas fa-rocket', 'name' => 'Rocket', 'category' => 'ui'),
			array('class' => 'fas fa-star', 'name' => 'Star', 'category' => 'ui'),
			array('class' => 'fas fa-check-circle', 'name' => 'Check Circle', 'category' => 'ui'),
			array('class' => 'fas fa-heart', 'name' => 'Heart', 'category' => 'ui'),
			array('class' => 'fas fa-envelope', 'name' => 'Envelope', 'category' => 'communication'),
			array('class' => 'fas fa-phone-alt', 'name' => 'Phone', 'category' => 'communication'),
			array('class' => 'fas fa-map-marker-alt', 'name' => 'Map Marker', 'category' => 'communication'),
			array('class' => 'fas fa-globe', 'name' => 'Globe', 'category' => 'communication'),
			array('class' => 'fas fa-briefcase', 'name' => 'Briefcase', 'category' => 'business'),
			array('class' => 'fas fa-chart-line', 'name' => 'Chart Line', 'category' => 'business'),
			array('class' => 'fas fa-laptop-code', 'name' => 'Laptop Code', 'category' => 'business'),
			array('class' => 'fas fa-shield-alt', 'name' => 'Shield', 'category' => 'business'),
			array('class' => 'fab fa-twitter', 'name' => 'Twitter / X', 'category' => 'social'),
			array('class' => 'fab fa-facebook-f', 'name' => 'Facebook', 'category' => 'social'),
			array('class' => 'fab fa-instagram', 'name' => 'Instagram', 'category' => 'social'),
			array('class' => 'fab fa-linkedin-in', 'name' => 'LinkedIn', 'category' => 'social'),
			array('class' => 'fab fa-github', 'name' => 'GitHub', 'category' => 'social'),
			array('class' => 'fab fa-youtube', 'name' => 'YouTube', 'category' => 'social'),
			array('class' => 'fas fa-play', 'name' => 'Play', 'category' => 'media'),
			array('class' => 'fas fa-image', 'name' => 'Image', 'category' => 'media'),
			array('class' => 'fas fa-shopping-cart', 'name' => 'Shopping Cart', 'category' => 'ecommerce'),
			array('class' => 'fas fa-tag', 'name' => 'Tag', 'category' => 'ecommerce'),
		);

		$filtered = array();
		foreach ($icons as $ico) {
			if ($category && strtolower($ico['category']) !== $category) {
				continue;
			}
			if ($search && strpos(strtolower($ico['name']), $search) === false && strpos(strtolower($ico['class']), $search) === false) {
				continue;
			}
			$filtered[] = $ico;
		}

		return array('icons' => $filtered);
	}

	public static function execute_get_fonts($input) {
		$system_fonts = array('Arial', 'Helvetica', 'Georgia', 'Times New Roman', 'Trebuchet MS', 'Verdana', 'Courier New', 'Impact');
		$google_fonts = array(
			array('family' => 'Inter', 'category' => 'sans-serif', 'weights' => array('300','400','500','600','700','800')),
			array('family' => 'Roboto', 'category' => 'sans-serif', 'weights' => array('300','400','500','700')),
			array('family' => 'Open Sans', 'category' => 'sans-serif', 'weights' => array('300','400','600','700')),
			array('family' => 'Montserrat', 'category' => 'sans-serif', 'weights' => array('400','500','600','700','800')),
			array('family' => 'Poppins', 'category' => 'sans-serif', 'weights' => array('300','400','500','600','700')),
			array('family' => 'Playfair Display', 'category' => 'serif', 'weights' => array('400','600','700','900')),
			array('family' => 'Merriweather', 'category' => 'serif', 'weights' => array('300','400','700')),
			array('family' => 'Outfit', 'category' => 'sans-serif', 'weights' => array('300','400','500','600','700')),
			array('family' => 'Plus Jakarta Sans', 'category' => 'sans-serif', 'weights' => array('400','500','600','700','800')),
		);

		return array(
			'system_fonts' => $system_fonts,
			'google_fonts' => $google_fonts,
		);
	}

	public static function execute_get_color_presets($input) {
		return array(
			'presets' => array(
				'modern_agency' => array(
					'title' => 'Modern Agency',
					'colors' => array(
						'primary'   => array('title' => 'Primary', 'value' => '#0F172A'),
						'secondary' => array('title' => 'Secondary', 'value' => '#3B82F6'),
						'accent'    => array('title' => 'Accent', 'value' => '#06B6D4'),
						'text'      => array('title' => 'Text', 'value' => '#334155'),
						'bg'        => array('title' => 'Background', 'value' => '#FFFFFF'),
						'light_bg'  => array('title' => 'Light Background', 'value' => '#F8FAFC'),
					)
				),
				'sleek_dark' => array(
					'title' => 'Sleek Dark',
					'colors' => array(
						'primary'   => array('title' => 'Primary', 'value' => '#10B981'),
						'secondary' => array('title' => 'Secondary', 'value' => '#34D399'),
						'accent'    => array('title' => 'Accent', 'value' => '#6EE7B7'),
						'text'      => array('title' => 'Text', 'value' => '#F9FAFB'),
						'bg'        => array('title' => 'Background', 'value' => '#090D16'),
						'light_bg'  => array('title' => 'Light Background', 'value' => '#1E293B'),
					)
				),
				'vibrant_tech' => array(
					'title' => 'Vibrant Tech',
					'colors' => array(
						'primary'   => array('title' => 'Primary', 'value' => '#6366F1'),
						'secondary' => array('title' => 'Secondary', 'value' => '#818CF8'),
						'accent'    => array('title' => 'Accent', 'value' => '#F43F5E'),
						'text'      => array('title' => 'Text', 'value' => '#1E293B'),
						'bg'        => array('title' => 'Background', 'value' => '#FFFFFF'),
						'light_bg'  => array('title' => 'Light Background', 'value' => '#F0FDF4'),
					)
				),
				'elegant_serif' => array(
					'title' => 'Elegant Serif',
					'colors' => array(
						'primary'   => array('title' => 'Primary', 'value' => '#78350F'),
						'secondary' => array('title' => 'Secondary', 'value' => '#D97706'),
						'accent'    => array('title' => 'Accent', 'value' => '#B45309'),
						'text'      => array('title' => 'Text', 'value' => '#27272A'),
						'bg'        => array('title' => 'Background', 'value' => '#FFFBEB'),
						'light_bg'  => array('title' => 'Light Background', 'value' => '#FEF3C7'),
					)
				),
				'warm_minimal' => array(
					'title' => 'Warm Minimal',
					'colors' => array(
						'primary'   => array('title' => 'Primary', 'value' => '#9A3412'),
						'secondary' => array('title' => 'Secondary', 'value' => '#FB923C'),
						'accent'    => array('title' => 'Accent', 'value' => '#EA580C'),
						'text'      => array('title' => 'Text', 'value' => '#1F2937'),
						'bg'        => array('title' => 'Background', 'value' => '#FFFFFF'),
						'light_bg'  => array('title' => 'Light Background', 'value' => '#FAF5FF'),
					)
				)
			)
		);
	}

	public static function execute_get_spacing_presets($input) {
		return array(
			'container_widths' => array('boxed' => '1170px', 'wide' => '1320px', 'full' => '100%'),
			'section_padding'  => array('compact' => '40px,0px,40px,0px', 'medium' => '80px,0px,80px,0px', 'spacious' => '120px,0px,120px,0px'),
			'column_gaps'      => array('narrow' => '15px', 'normal' => '30px', 'wide' => '45px'),
			'border_radiuses'  => array('none' => '0px', 'sm' => '4px', 'md' => '8px', 'lg' => '16px', 'pill' => '9999px'),
			// Order is x,y,blur,COLOUR,spread,inset — the renderer reads the
			// colour from position 3 and the spread from position 4, appends its
			// own "px" to the numbers, and splits the whole value on commas.
			// These were previously written as "0px,4px,12px,0px,rgba(0,0,0,.05)",
			// which got all three of those wrong at once: doubled units, colour
			// and spread transposed, and an rgba() that tore in half on its own
			// commas — so every shadow the model was told to use rendered as
			// invalid CSS and silently did nothing. Alpha travels as 8-digit hex.
			'box_shadows'      => array(
				'none'     => 'none',
				'soft'     => '0,4,12,#0000000d,0,',
				'medium'   => '0,8,24,#00000014,0,',
				'floating' => '0,16,40,#0000001f,0,',
				'glow'     => '0,0,20,#3b82f64d,0,',
			),
			'box_shadow_format' => 'x,y,blur,color,spread,inset — bare numbers (the renderer appends px) and an #rrggbbaa colour, never rgba() (its commas split the value).'
		);
	}

	/**
	 * One or many searches. A page needs 4-8 photos and each Pexels call is a
	 * ~400 ms network round trip plus a full model turn; batching the queries
	 * turns "eight tool calls, eight waits" into one, which is most of the
	 * image time in a site build.
	 */
	public static function execute_search_images($input) {
		$queries = array();
		if (!empty($input['queries']) && is_array($input['queries'])) {
			foreach ($input['queries'] as $q) {
				$q = sanitize_text_field((string) $q);
				if ($q !== '') {
					$queries[] = $q;
				}
			}
			$queries = array_slice(array_unique($queries), 0, 10);
		}

		if (!empty($queries)) {
			$batch   = array();
			$per_one = isset($input['per_page']) ? $input['per_page'] : 3;
			foreach ($queries as $q) {
				$one = self::search_one_image_query(array_merge($input, array('query' => $q, 'per_page' => $per_one)));
				if (is_wp_error($one)) {
					// A key/config failure is fatal for every query — report it
					// once instead of ten times.
					if (in_array($one->get_error_code(), array('no_image_api_key', 'invalid_image_api_key'), true)) {
						return $one;
					}
					$batch[$q] = array('error' => $one->get_error_message());
					continue;
				}
				$batch[$q] = $one['results'];
			}
			return array('batch' => $batch);
		}

		return self::search_one_image_query($input);
	}

	protected static function search_one_image_query($input) {
		$query = isset($input['query']) ? sanitize_text_field($input['query']) : '';
		if (empty($query)) {
			return new \WP_Error('missing_query', __('A search query is required — pass "query" for one search or "queries" for several in one call.', 'pagelayer'));
		}

		$api_key = get_option('pagelayer_pexels_api_key', '');
		if (empty($api_key)) {
			return new \WP_Error(
				'no_image_api_key',
				__('No Pexels API key is configured. Ask the site owner to add one on the Pagelayer AI Agents settings page (get a free key at https://www.pexels.com/api/), then retry search_images. Do not fall back to placeholder or made-up image URLs.', 'pagelayer')
			);
		}

		$per_page = isset($input['per_page']) ? max(1, min(20, (int) $input['per_page'])) : 5;

		// add_query_arg() already URL-encodes — rawurlencode()ing first sent
		// "wood%2520fired%2520oven" to Pexels and matched nothing.
		$url = add_query_arg(array(
			'query'    => $query,
			'per_page' => $per_page,
		), 'https://api.pexels.com/v1/search');

		if (!empty($input['orientation']) && in_array($input['orientation'], array('landscape', 'portrait', 'square'), true)) {
			$url = add_query_arg('orientation', $input['orientation'], $url);
		}

		$response = wp_remote_get($url, array(
			'timeout' => 20,
			'headers' => array('Authorization' => $api_key),
		));

		if (is_wp_error($response)) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		if ($code === 401) {
			return new \WP_Error('invalid_image_api_key', __('The configured Pexels API key was rejected. Ask the site owner to check it on the Pagelayer AI Agents settings page.', 'pagelayer'));
		}
		if ($code !== 200 || !is_array($body)) {
			return new \WP_Error('image_search_failed', sprintf(__('Pexels search failed with status %d.', 'pagelayer'), $code));
		}

		$results = array();
		foreach (($body['photos'] ?? array()) as $photo) {
			$results[] = array(
				'url'          => isset($photo['src']['large']) ? $photo['src']['large'] : (isset($photo['src']['original']) ? $photo['src']['original'] : ''),
				'thumb'        => isset($photo['src']['medium']) ? $photo['src']['medium'] : '',
				'alt'          => isset($photo['alt']) && $photo['alt'] !== '' ? $photo['alt'] : $query,
				'width'        => isset($photo['width']) ? (int) $photo['width'] : 0,
				'height'       => isset($photo['height']) ? (int) $photo['height'] : 0,
				'photographer' => isset($photo['photographer']) ? $photo['photographer'] : '',
			);
		}

		return array('query' => $query, 'results' => $results);
	}

	// ------------------------------------------------------------------
	// Template Callbacks
	// ------------------------------------------------------------------

	public static function execute_get_templates($input) {
		$type_filter = isset($input['type']) ? sanitize_text_field($input['type']) : '';

		$args = array(
			'post_type'      => 'pagelayer-template',
			'posts_per_page' => -1,
			'post_status'    => array('publish', 'draft'),
		);

		if (!empty($type_filter)) {
			$args['meta_key']   = 'pagelayer_template_type';
			$args['meta_value'] = $type_filter;
		}

		$query = new \WP_Query($args);
		$templates = array();

		foreach ($query->posts as $post) {
			$tt_type       = get_post_meta($post->ID, 'pagelayer_template_type', true);
			$tt_conditions = get_post_meta($post->ID, 'pagelayer_template_conditions', true);
			$templates[]   = array(
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'type'       => $tt_type ?: 'general',
				'status'     => $post->post_status,
				'conditions' => is_array($tt_conditions) ? $tt_conditions : array(),
			);
		}

		// Also check local template library
		$library = get_option('pagelayer_template_library', array());
		foreach ($library as $lib_name => $lib_data) {
			if (empty($type_filter) || $type_filter === 'library') {
				$templates[] = array(
					'id'         => 'lib_' . sanitize_title($lib_name),
					'title'      => $lib_name,
					'type'       => 'library',
					'status'     => 'publish',
					'conditions' => array(),
				);
			}
		}

		return array('templates' => $templates);
	}

	/**
	 * Header and footer templates are site furniture: they must carry an
	 * Include / Full Site display condition (type "include" with an empty
	 * "template", which is what pagelayer_template_check_conditons() treats as
	 * the general, whole-site rule — see main/template.php). An AI client that
	 * scopes a header to "singular" or "front_page" leaves every other view
	 * without one, with nothing to warn the site owner, so the rule is added
	 * back if it is missing. Any exclude rules the caller sent are kept — they
	 * still work as exceptions on top of the site-wide include.
	 */
	protected static function force_full_site_condition($conditions, $type) {
		if (!in_array($type, array('header', 'footer'), true)) {
			return $conditions;
		}

		if (!is_array($conditions)) {
			$conditions = array();
		}

		foreach ($conditions as $c) {
			if (!is_array($c)) {
				continue;
			}
			$c_type     = isset($c['type']) ? $c['type'] : 'include';
			$c_template = isset($c['template']) ? $c['template'] : '';
			if ($c_type === 'include' && $c_template === '') {
				return $conditions;
			}
		}

		array_unshift($conditions, array('type' => 'include', 'template' => '', 'sub_template' => '', 'id' => ''));
		return $conditions;
	}

	/**
	 * A multi-page site needs real navigation in its header. The Primary Menu
	 * widget (pl_wp_menu) renders a WordPress menu — hand-placed pl_btn/pl_text
	 * links look similar in the builder but give the owner no menu to edit, no
	 * mobile toggle, no submenus and no current-page state. So a header must
	 * carry a pl_wp_menu bound to a real menu, unless the caller states the
	 * site is a one-pager (single_page_site:true), where in-page anchor links
	 * are the correct pattern.
	 *
	 * Skipped entirely when pl_wp_menu is not registered on this install (the
	 * widget ships with the Pagelayer Pro add-on) — there is no point demanding
	 * a widget that does not exist.
	 */
	protected static function header_nav_gate($type, $p_data, $single_page_site = false) {
		if ($type !== 'header' || $single_page_site) {
			return null;
		}

		self::ensure_shortcodes_loaded();
		global $pagelayer;
		if (empty($pagelayer->shortcodes['pl_wp_menu'])) {
			return null;
		}

		$menu_nodes = array();
		$walk = function($nodes) use (&$walk, &$menu_nodes) {
			if (!is_array($nodes)) {
				return;
			}
			foreach ($nodes as $node) {
				if (!is_array($node) || empty($node['tag'])) {
					continue;
				}
				if (str_replace('pagelayer_', 'pl_', $node['tag']) === 'pl_wp_menu') {
					$menu_nodes[] = $node;
				}
				if (isset($node['content']) && is_array($node['content'])) {
					$walk($node['content']);
				}
			}
		};
		$walk($p_data);

		if (empty($menu_nodes)) {
			return new \WP_Error('header_without_menu', __('A header template must contain the Primary Menu widget (tag "pl_wp_menu") — nothing was saved. Build the menu first with create_menu (it returns a menu_id), then place {"tag":"pl_wp_menu","attrs":{"nav_list":"<menu_id>","layout":"horizontal","align":"right","drop_breakpoint":"tablet","pointer":"underline"}} in the header, and style it with the widget\'s own attributes (get_widget_schema for pl_wp_menu). Hand-placed pl_btn/pl_text links are not navigation: the site owner gets no editable menu, no mobile toggle, no submenus and no current-page highlight. If the user asked for a ONE-PAGE site whose header links are in-page anchors, pass single_page_site:true to opt out.', 'pagelayer'));
		}

		foreach ($menu_nodes as $node) {
			$nav_list = isset($node['attrs']['nav_list']) ? trim((string) $node['attrs']['nav_list']) : '';
			if ($nav_list === '' || $nav_list === '0') {
				return new \WP_Error('menu_widget_without_menu', __('The Primary Menu widget in this header has no menu selected ("nav_list" is empty), so it renders an empty menu — nothing was saved. Call create_menu with the pages this site has, then set attrs.nav_list on the pl_wp_menu node to the menu_id it returns (get_menus lists existing menus).', 'pagelayer'));
			}

			if (is_numeric($nav_list) && !wp_get_nav_menu_object((int) $nav_list)) {
				return new \WP_Error('menu_not_found', sprintf(__('The Primary Menu widget points at nav_list "%s", which is not an existing WordPress menu — nothing was saved. Call get_menus for the real ids, or create_menu to build one.', 'pagelayer'), $nav_list));
			}
		}

		return null;
	}

	/**
	 * Give a header/footer an explicit background drawn from the site palette.
	 *
	 * A theme template whose row sets no background is transparent, so it shows
	 * the THEME's body colour — light on a stock theme — while every page below
	 * it is dark. That is what produced a white header bar with white menu links
	 * on it: the caller's colour choices (white nav, $text headings) were right
	 * for the dark chrome it thought it was building, and only the background
	 * was missing. Supplying it makes the rest correct in one move.
	 *
	 * Only ever fills a gap — a row that sets its own background is untouched.
	 */
	protected static function theme_chrome_background($p_data, $type) {
		if (!in_array($type, array('header', 'footer'), true) || !is_array($p_data)) {
			return $p_data;
		}

		$colors = json_decode((string) get_option('pagelayer_global_colors', ''), true);
		$colors = is_array($colors) ? $colors : array();

		// Prefer a key the palette actually defines, darkest-intent first.
		$token = '';
		foreach (array('bg', 'secondary', 'primary') as $key) {
			if (!empty($colors[$key])) {
				$token = '$' . $key;
				break;
			}
		}
		if ($token === '') {
			return $p_data;
		}

		$dark = self::bg_is_dark($token, $colors);

		foreach ($p_data as $i => $node) {
			if (!is_array($node) || ($node['tag'] ?? '') !== 'pl_row') {
				continue;
			}
			$attrs = isset($node['attrs']) && is_array($node['attrs']) ? $node['attrs'] : array();

			if (!empty($attrs['ele_bg_type'])) {
				continue;
			}

			$attrs['ele_bg_type']  = 'color';
			$attrs['ele_bg_color'] = $token;

			// pl_text has no colour control at all, so paragraph and link copy
			// in the chrome keeps the theme's dark default and vanishes on a
			// dark bar. ele_css is the sanctioned place for a rule no widget
			// attribute can express.
			if ($dark && empty($attrs['ele_css'])) {
				$attrs['ele_css'] = '{{element}} p, {{element}} li, {{element}} .pagelayer-text-holder{color:rgba(255,255,255,0.75)}'
					. '{{element}} a{color:rgba(255,255,255,0.75)}'
					. '{{element}} a:hover{color:var(--pagelayer-color-primary)}';
			}

			$p_data[$i]['attrs'] = $attrs;
		}

		self::ensure_menu_spacing($p_data);

		return $p_data;
	}

	/**
	 * Give the navigation its item padding.
	 *
	 * pl_wp_menu declares horizontal_padding / vertical_padding with a default
	 * of 10, but a default is only written into a node by the editor when the
	 * widget is inserted — a node built through the abilities layer carries only
	 * what was set explicitly. The menu therefore renders with no padding at all
	 * and the links sit jammed against each other and against the edge of the
	 * viewport.
	 *
	 * These are slider props whose CSS template appends its own unit, so a bare
	 * number is correct here (unlike the padding-typed props handled by
	 * add_missing_css_units).
	 */
	protected static function ensure_menu_spacing(&$nodes) {
		foreach ($nodes as &$node) {
			if (!is_array($node)) {
				continue;
			}

			if (($node['tag'] ?? '') === 'pl_wp_menu') {
				if (!isset($node['attrs']) || !is_array($node['attrs'])) {
					$node['attrs'] = array();
				}
				foreach (array('horizontal_padding' => '18', 'vertical_padding' => '10') as $key => $val) {
					if (!isset($node['attrs'][$key]) || $node['attrs'][$key] === '') {
						$node['attrs'][$key] = $val;
					}
				}
			}

			if (isset($node['content']) && is_array($node['content'])) {
				self::ensure_menu_spacing($node['content']);
			}
		}
		unset($node);
	}

	public static function execute_create_template($input) {
		$title  = isset($input['title']) ? sanitize_text_field($input['title']) : '';
		$type   = isset($input['type']) ? sanitize_text_field($input['type']) : 'general';
		$p_data = isset($input['pagelayer_data']) && is_array($input['pagelayer_data']) ? $input['pagelayer_data'] : array();

		$raw_conditions = isset($input['conditions']) && is_array($input['conditions']) && !empty($input['conditions']) ? $input['conditions'] : array(
			array('type' => 'include', 'template' => '', 'sub_template' => '', 'id' => '')
		);
		$conditions = array();
		foreach ($raw_conditions as $c) {
			if (!is_array($c)) continue;
			$conditions[] = array(
				'type'         => isset($c['type']) ? sanitize_text_field($c['type']) : 'include',
				'template'     => isset($c['template']) ? sanitize_text_field($c['template']) : '',
				'sub_template' => isset($c['sub_template']) ? sanitize_text_field($c['sub_template']) : '',
				'id'           => isset($c['id']) ? sanitize_text_field($c['id']) : '',
			);
		}
		if (empty($conditions)) {
			$conditions = array(
				array('type' => 'include', 'template' => '', 'sub_template' => '', 'id' => '')
			);
		}

		// A header/footer that is not displayed site-wide is a header/footer
		// nobody sees on most of the site.
		$conditions = self::force_full_site_condition($conditions, $type);

		if (empty($title) || empty($p_data)) {
			return new \WP_Error('missing_params', __('Title and pagelayer_data are required.', 'pagelayer'));
		}

		// Before the post is created, so a rejected template leaves nothing behind.
		$inline_css = self::inline_css_gate($p_data);
		if (is_wp_error($inline_css)) {
			return $inline_css;
		}

		$nav_gate = self::header_nav_gate($type, $p_data, !empty($input['single_page_site']));
		if (is_wp_error($nav_gate)) {
			return $nav_gate;
		}

		// The header and footer appear on every page of the site, yet they were
		// the only builder output the content-quality gate never saw — which is
		// how four pl_text "color" attrs (a prop pl_text does not have) reached
		// a live footer and were dropped at render, leaving default-blue links.
		if (empty($input['skip_validation'])) {
			$gate = self::quality_gate($p_data);
			if (is_wp_error($gate)) {
				return $gate;
			}
		}

		$p_data = self::theme_chrome_background($p_data, $type);

		$singleton_types = array('header', 'footer');
		$template_id = null;

		if (in_array($type, $singleton_types, true)) {
			$existing = get_posts(array(
				'post_type'      => 'pagelayer-template',
				'post_status'    => array('publish', 'draft'),
				'posts_per_page' => 1,
				'meta_key'       => 'pagelayer_template_type',
				'meta_value'     => $type,
				'fields'         => 'ids',
			));
			if (!empty($existing)) {
				$template_id = (int) $existing[0];
				wp_update_post(wp_slash(array('ID' => $template_id, 'post_title' => $title)));
			}
		}

		if (empty($template_id)) {
			$postarr = array(
				'post_title'  => $title,
				'post_type'   => 'pagelayer-template',
				'post_status' => 'publish',
			);
			$template_id = wp_insert_post(wp_slash($postarr), true);
			if (is_wp_error($template_id)) {
				return $template_id;
			}
		}

		$normalized = self::normalize_layout_data($p_data);
		update_post_meta($template_id, 'pagelayer-data', $normalized);
		update_post_meta($template_id, 'pagelayer_template_type', $type);
		update_post_meta($template_id, 'pagelayer_template_conditions', $conditions);

		$blocks_content = self::serialize_layout_to_blocks($normalized);
		wp_update_post(array('ID' => $template_id, 'post_content' => $blocks_content));

		return array(
			'template_id' => $template_id,
			'title'       => $title,
			'type'        => $type,
			'success'     => true,
		);
	}

	public static function execute_update_template($input) {
		$template_id = isset($input['template_id']) ? (int) $input['template_id'] : 0;
		if (!$template_id || get_post_type($template_id) !== 'pagelayer-template') {
			return new \WP_Error('invalid_template', __('Template not found.', 'pagelayer'));
		}

		$type = isset($input['type'])
			? sanitize_text_field($input['type'])
			: (string) get_post_meta($template_id, 'pagelayer_template_type', true);

		if (isset($input['title'])) {
			wp_update_post(array('ID' => $template_id, 'post_title' => sanitize_text_field($input['title'])));
		}
		if (isset($input['type'])) {
			update_post_meta($template_id, 'pagelayer_template_type', $type);
		}
		if (isset($input['conditions']) && is_array($input['conditions'])) {
			update_post_meta($template_id, 'pagelayer_template_conditions', self::force_full_site_condition($input['conditions'], $type));
		}
		if (isset($input['pagelayer_data']) && is_array($input['pagelayer_data'])) {
			$inline_css = self::inline_css_gate($input['pagelayer_data']);
			if (is_wp_error($inline_css)) {
				return $inline_css;
			}

			$nav_gate = self::header_nav_gate($type, $input['pagelayer_data'], !empty($input['single_page_site']));
			if (is_wp_error($nav_gate)) {
				return $nav_gate;
			}

			if (empty($input['skip_validation'])) {
				$gate = self::quality_gate($input['pagelayer_data']);
				if (is_wp_error($gate)) {
					return $gate;
				}
			}

			$normalized = self::normalize_layout_data(self::theme_chrome_background($input['pagelayer_data'], $type));
			update_post_meta($template_id, 'pagelayer-data', $normalized);
			$blocks_content = self::serialize_layout_to_blocks($normalized);
			wp_update_post(array('ID' => $template_id, 'post_content' => $blocks_content));
		}

		return array('success' => true);
	}

	public static function execute_delete_template($input) {
		$template_id = isset($input['template_id']) ? (int) $input['template_id'] : 0;
		if (!$template_id || get_post_type($template_id) !== 'pagelayer-template') {
			return new \WP_Error('invalid_template', __('Template not found.', 'pagelayer'));
		}

		$res = wp_delete_post($template_id, true);
		return array('success' => (bool)$res);
	}

	// ------------------------------------------------------------------
	// Navigation Menu Callbacks
	// ------------------------------------------------------------------
	//
	// pl_wp_menu ("Primary Menu") renders a real WordPress nav menu picked by
	// term id in its "nav_list" attr, and the per-item Mega Menu settings live
	// on the menu ITEM as a serialized pagelayer/pl_nav_menu_item block in its
	// "_pagelayer_content" meta (see main/nav_walker.php). Both are built here.

	/**
	 * Accepts a term id, slug or name.
	 */
	protected static function resolve_menu($ref) {
		if ($ref === '' || $ref === null) {
			return false;
		}
		$menu = wp_get_nav_menu_object(is_numeric($ref) ? (int) $ref : $ref);
		return $menu ? $menu : false;
	}

	protected static function menu_items_tree($menu) {
		$items = wp_get_nav_menu_items($menu->term_id);
		if (!is_array($items)) {
			return array();
		}

		$by_parent = array();
		foreach ($items as $item) {
			$by_parent[(int) $item->menu_item_parent][] = $item;
		}

		$build = function($parent_id) use (&$build, $by_parent) {
			$out = array();
			if (empty($by_parent[$parent_id])) {
				return $out;
			}
			foreach ($by_parent[$parent_id] as $item) {
				$row = array(
					'item_id' => (int) $item->ID,
					'title'   => $item->title,
					'url'     => $item->url,
					'object'  => $item->object,
					'object_id' => (int) $item->object_id,
				);

				$settings = self::read_menu_item_settings($item->ID);
				if (!empty($settings)) {
					$row['pagelayer_settings'] = $settings;
				}

				$children = $build((int) $item->ID);
				if (!empty($children)) {
					$row['children'] = $children;
				}
				$out[] = $row;
			}
			return $out;
		};

		return $build(0);
	}

	/**
	 * The pl_nav_menu_item attrs stored on a menu item, without the mega-menu
	 * body (which can be a whole layout tree and is not worth echoing back).
	 */
	protected static function read_menu_item_settings($item_id) {
		$content = get_post_meta($item_id, '_pagelayer_content', true);
		if (empty($content) || !function_exists('parse_blocks')) {
			return array();
		}

		foreach (parse_blocks($content) as $block) {
			if (empty($block['blockName']) || $block['blockName'] !== 'pagelayer/pl_nav_menu_item') {
				continue;
			}
			$attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : array();
			unset($attrs['pagelayer-id']);
			if (!empty($block['innerBlocks'])) {
				$attrs['has_mega_content'] = true;
			}
			return $attrs;
		}

		return array();
	}

	public static function execute_get_menus($input) {
		$locations   = function_exists('get_registered_nav_menus') ? get_registered_nav_menus() : array();
		$assignments = function_exists('get_nav_menu_locations') ? (array) get_nav_menu_locations() : array();

		$wanted = isset($input['menu']) ? sanitize_text_field($input['menu']) : '';
		$menus  = array();

		foreach (wp_get_nav_menus() as $menu) {
			if ($wanted !== '' && (string) $menu->term_id !== $wanted && $menu->slug !== $wanted && $menu->name !== $wanted) {
				continue;
			}

			$assigned = array();
			foreach ($assignments as $slug => $term_id) {
				if ((int) $term_id === (int) $menu->term_id) {
					$assigned[] = $slug;
				}
			}

			$menus[] = array(
				'menu_id'    => (int) $menu->term_id,
				'name'       => $menu->name,
				'slug'       => $menu->slug,
				'item_count' => (int) $menu->count,
				'locations'  => $assigned,
				'items'      => self::menu_items_tree($menu),
			);
		}

		$location_rows = array();
		foreach ($locations as $slug => $label) {
			$location_rows[] = array(
				'slug'         => $slug,
				'label'        => $label,
				'assigned_menu_id' => isset($assignments[$slug]) ? (int) $assignments[$slug] : 0,
			);
		}

		return array(
			'menus'     => $menus,
			'locations' => $location_rows,
			'note'      => 'Put a "menu_id" from this list in the pl_wp_menu (Primary Menu) widget\'s "nav_list" attribute — that widget has no item list of its own, it renders the WordPress menu you point it at. Build or rebuild a menu with create_menu.',
		);
	}

	public static function execute_create_menu($input) {
		$name = isset($input['name']) ? sanitize_text_field($input['name']) : '';
		if ($name === '') {
			return new \WP_Error('missing_name', __('A menu name is required.', 'pagelayer'));
		}

		$items = isset($input['items']) && is_array($input['items']) ? $input['items'] : array();
		if (empty($items)) {
			return new \WP_Error('missing_items', __('At least one menu item is required.', 'pagelayer'));
		}

		// Mega-menu bodies are layout data like any other, so they answer to the
		// same inline-CSS rule.
		$inline_css_found = array();
		$scrub_mega = function($rows) use (&$scrub_mega, &$inline_css_found) {
			foreach ($rows as $i => $row) {
				if (!is_array($row)) {
					continue;
				}
				if (isset($row['mega_content']) && is_array($row['mega_content'])) {
					self::scrub_layout_inline_css($row['mega_content'], $inline_css_found);
				}
				if (isset($row['children']) && is_array($row['children'])) {
					$row['children'] = $scrub_mega($row['children']);
				}
				$rows[$i] = $row;
			}
			return $rows;
		};
		$items = $scrub_mega($items);
		if (!empty($inline_css_found)) {
			return self::inline_css_error($inline_css_found);
		}

		$menu = self::resolve_menu($name);
		if (!$menu) {
			$menu_id = wp_create_nav_menu($name);
			if (is_wp_error($menu_id)) {
				return $menu_id;
			}
			$menu = wp_get_nav_menu_object($menu_id);
		}

		if (!$menu) {
			return new \WP_Error('menu_failed', __('The navigation menu could not be created.', 'pagelayer'));
		}

		$replace = !isset($input['replace_items']) || !empty($input['replace_items']);
		if ($replace) {
			foreach ((array) wp_get_nav_menu_items($menu->term_id) as $existing) {
				wp_delete_post($existing->ID, true);
			}
		}

		$created  = array();
		$failures = array();

		$insert = function($rows, $parent_id) use (&$insert, $menu, &$created, &$failures) {
			$out = array();

			foreach ($rows as $row) {
				if (!is_array($row)) {
					continue;
				}

				$item_id = self::insert_menu_item($menu->term_id, $parent_id, $row);
				if (is_wp_error($item_id)) {
					$failures[] = array(
						'title' => isset($row['title']) ? $row['title'] : '',
						'error' => $item_id->get_error_message(),
					);
					continue;
				}

				$entry = array(
					'item_id' => $item_id,
					'title'   => isset($row['title']) ? $row['title'] : '',
				);

				if (!empty($row['children']) && is_array($row['children'])) {
					$entry['children'] = $insert($row['children'], $item_id);
				}

				$created[] = $item_id;
				$out[]     = $entry;
			}

			return $out;
		};

		$tree = $insert($items, 0);

		$assigned_location = '';
		if (!empty($input['location'])) {
			$location   = sanitize_text_field($input['location']);
			$registered = function_exists('get_registered_nav_menus') ? get_registered_nav_menus() : array();

			if (!isset($registered[$location])) {
				$failures[] = array(
					'title' => '',
					'error' => sprintf(__('Theme location "%1$s" is not registered by this theme. Registered: %2$s', 'pagelayer'), $location, implode(', ', array_keys($registered)) ?: '-'),
				);
			} else {
				$locations             = (array) get_nav_menu_locations();
				$locations[$location]  = (int) $menu->term_id;
				set_theme_mod('nav_menu_locations', $locations);
				$assigned_location     = $location;
			}
		}

		$result = array(
			'success'  => empty($failures),
			'menu_id'  => (int) $menu->term_id,
			'name'     => $menu->name,
			'items'    => $tree,
			'assigned_location' => $assigned_location,
			'next_step' => sprintf('Set the Primary Menu widget\'s nav_list attribute to %d: {"tag":"pl_wp_menu","attrs":{"nav_list":"%d", ...}}', $menu->term_id, $menu->term_id),
		);

		if (!empty($failures)) {
			$result['failed'] = $failures;
		}

		return $result;
	}

	/**
	 * One nav menu item plus, when the item carries Pagelayer settings, the
	 * pl_nav_menu_item block that the nav walker reads them from.
	 */
	protected static function insert_menu_item($menu_id, $parent_id, $row) {
		$title = isset($row['title']) ? sanitize_text_field($row['title']) : '';
		$args  = array(
			'menu-item-title'     => $title,
			'menu-item-parent-id' => (int) $parent_id,
			'menu-item-status'    => 'publish',
		);

		$object_id = 0;
		foreach (array('page_id', 'post_id', 'object_id') as $key) {
			if (!empty($row[$key])) {
				$object_id = (int) $row[$key];
				break;
			}
		}

		if ($object_id > 0) {
			$post = get_post($object_id);
			if (!$post) {
				return new \WP_Error('invalid_menu_target', sprintf(__('Menu item "%1$s" points at post %2$d, which does not exist.', 'pagelayer'), $title, $object_id));
			}
			$args['menu-item-type']      = 'post_type';
			$args['menu-item-object']    = $post->post_type;
			$args['menu-item-object-id'] = $object_id;
			if ($title === '') {
				$args['menu-item-title'] = get_the_title($object_id);
			}
		} else {
			$url = isset($row['url']) ? esc_url_raw($row['url']) : '';
			if ($url === '' && $title === '') {
				return new \WP_Error('invalid_menu_item', __('A menu item needs a title plus either page_id or url.', 'pagelayer'));
			}
			$args['menu-item-type'] = 'custom';
			$args['menu-item-url']  = $url !== '' ? $url : '#';
		}

		if (!empty($row['target'])) {
			$args['menu-item-target'] = sanitize_text_field($row['target']);
		}
		if (!empty($row['description'])) {
			$args['menu-item-description'] = sanitize_text_field($row['description']);
		}

		$item_id = wp_update_nav_menu_item($menu_id, 0, $args);
		if (is_wp_error($item_id)) {
			return $item_id;
		}

		$content = self::menu_item_settings_block($row);
		if ($content !== '') {
			update_post_meta($item_id, '_pagelayer_content', $content);
		}

		return (int) $item_id;
	}

	/**
	 * Serialized pagelayer/pl_nav_menu_item block for one item's settings. Its
	 * inner blocks are the Mega Menu body, which is what makes the walker treat
	 * the item as a mega item at all (menu_type alone is not enough — it also
	 * checks that the block has content).
	 */
	protected static function menu_item_settings_block($row) {
		$allowed = array('menu_type', 'mega_width', 'mega_custom_width', 'columns', 'col_gap', 'menu_icon', 'icon_position', 'highlight_label', 'disable_link');
		$attrs   = array();

		foreach ($allowed as $key) {
			if (isset($row[$key]) && $row[$key] !== '' && !is_array($row[$key])) {
				$attrs[$key] = is_bool($row[$key]) ? ($row[$key] ? 'true' : '') : sanitize_text_field($row[$key]);
			}
		}

		$mega = isset($row['mega_content']) && is_array($row['mega_content']) ? $row['mega_content'] : array();

		if (empty($attrs) && empty($mega)) {
			return '';
		}

		if (!empty($mega) && empty($attrs['menu_type'])) {
			$attrs['menu_type'] = 'mega';
		}

		$node = array(
			'tag'   => 'pl_nav_menu_item',
			'attrs' => $attrs,
		);

		if (!empty($mega)) {
			$node['content'] = self::normalize_layout_data($mega);
		}

		return self::serialize_layout_to_blocks(array($node));
	}

	public static function execute_delete_menu($input) {
		$menu = self::resolve_menu(isset($input['menu']) ? sanitize_text_field($input['menu']) : '');
		if (!$menu) {
			return new \WP_Error('invalid_menu', __('Navigation menu not found.', 'pagelayer'));
		}

		$res = wp_delete_nav_menu($menu->term_id);
		if (is_wp_error($res)) {
			return $res;
		}

		return array('success' => (bool) $res, 'menu_id' => (int) $menu->term_id);
	}

	// ------------------------------------------------------------------
	// Page Callbacks
	// ------------------------------------------------------------------

	public static function execute_create_page($input) {
		$input['post_type'] = 'page';
		return self::create_post_or_page($input);
	}

	public static function execute_update_page($input) {
		$post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;
		if (!$post_id || get_post_type($post_id) !== 'page') {
			return new \WP_Error('invalid_page', __('Page not found.', 'pagelayer'));
		}

		if (isset($input['title'])) {
			wp_update_post(array('ID' => $post_id, 'post_title' => sanitize_text_field($input['title'])));
		}
		if (isset($input['status'])) {
			wp_update_post(array('ID' => $post_id, 'post_status' => sanitize_text_field($input['status'])));
		}
		if (isset($input['pagelayer_data']) && is_array($input['pagelayer_data'])) {
			$inline_css = self::inline_css_gate($input['pagelayer_data']);
			if (is_wp_error($inline_css)) {
				return $inline_css;
			}

			$normalized = self::normalize_layout_data($input['pagelayer_data']);

			if (empty($input['skip_validation'])) {
				$gate = self::quality_gate($normalized);
				if (is_wp_error($gate)) {
					return $gate;
				}
			}

			update_post_meta($post_id, 'pagelayer-data', $normalized);
			$blocks_content = self::serialize_layout_to_blocks($normalized);
			wp_update_post(array('ID' => $post_id, 'post_content' => $blocks_content));
		}

		return array('success' => true, 'post_id' => $post_id, 'url' => get_permalink($post_id));
	}

	public static function execute_get_page($input) {
		$post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;
		$post    = get_post($post_id);
		if (!$post || $post->post_type !== 'page') {
			return new \WP_Error('invalid_page', __('Page not found.', 'pagelayer'));
		}

		$data = get_post_meta($post_id, 'pagelayer-data', true);
		$data = is_array($data) ? $data : array();

		$result = array(
			'post_id'  => $post->ID,
			'title'    => $post->post_title,
			'status'   => $post->post_status,
			'url'      => get_permalink($post->ID),
			'edit_url' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
		);

		// One element's full node — the normal read before an update_element.
		if (!empty($input['element_id'])) {
			$node = self::find_node_by_id($data, sanitize_text_field($input['element_id']));
			if ($node === null) {
				return new \WP_Error('not_found', sprintf(__('Element %s not found on this page.', 'pagelayer'), $input['element_id']));
			}
			$result['element'] = $node;
			return $result;
		}

		// Default is the outline, not the tree. A real page's pagelayer_data is
		// tens of KB of style attrs that a targeted edit never reads; the model
		// asks for mode:"full" on the rare occasion it needs all of it.
		$mode = isset($input['mode']) ? sanitize_text_field($input['mode']) : 'outline';

		if ($mode === 'full') {
			$result['pagelayer_data'] = $data;
			return $result;
		}

		$lines = array();
		$result['outline'] = self::outline_nodes($data, 0, $lines);
		$result['legend']  = 'One line per node: [indent = nesting depth] <ref> <tag> [col=N] "text preview". <ref> is the node\'s pagelayer-id, or an "@0.1.2" position path when it has none yet — either form works as element_id/parent_id in every element ability. To read one node in full pass element_id; mode:"full" returns the entire raw tree (large).';

		return $result;
	}

	public static function execute_list_pages($input) {
		$limit  = isset($input['limit']) ? (int) $input['limit'] : 20;
		$status = isset($input['status']) ? sanitize_text_field($input['status']) : 'any';

		$query = new \WP_Query(array(
			'post_type'      => 'page',
			'posts_per_page' => $limit,
			'post_status'    => $status,
			'meta_query'     => array(
				array('key' => 'pagelayer-data', 'compare' => 'EXISTS'),
			),
		));

		$pages = array();
		foreach ($query->posts as $post) {
			$pages[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'status' => $post->post_status,
				'url'    => get_permalink($post->ID),
			);
		}

		return array('pages' => $pages);
	}

	public static function execute_publish_page($input) {
		$post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;
		if (!$post_id || !get_post($post_id)) {
			return new \WP_Error('invalid_post', __('Post or page not found.', 'pagelayer'));
		}

		wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));
		return array('success' => true, 'url' => get_permalink($post_id));
	}

	public static function execute_duplicate_page($input) {
		$post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;
		$post    = get_post($post_id);
		if (!$post) {
			return new \WP_Error('invalid_post', __('Post or page not found.', 'pagelayer'));
		}

		$title = isset($input['title']) ? sanitize_text_field($input['title']) : $post->post_title . ' (Copy)';

		$new_id = wp_insert_post(array(
			'post_title'   => $title,
			'post_type'    => $post->post_type,
			'post_status'  => 'draft',
			'post_content' => $post->post_content,
		));

		if (is_wp_error($new_id)) {
			return $new_id;
		}

		$data = get_post_meta($post_id, 'pagelayer-data', true);
		if (is_array($data)) {
			$refresh_ids = function(&$node) use (&$refresh_ids) {
				if (!is_array($node)) return;
				if (isset($node['attrs']['pagelayer-id'])) {
					$node['attrs']['pagelayer-id'] = pagelayer_create_id();
				}
				if (isset($node['content']) && is_array($node['content'])) {
					foreach ($node['content'] as &$child) {
						$refresh_ids($child);
					}
					unset($child);
				}
			};
			foreach ($data as &$node) {
				$refresh_ids($node);
			}
			unset($node);

			$normalized = self::normalize_layout_data($data);
			update_post_meta($new_id, 'pagelayer-data', $normalized);
			wp_update_post(array('ID' => $new_id, 'post_content' => self::serialize_layout_to_blocks($normalized)));
		}

		return array(
			'success' => true,
			'post_id' => $new_id,
			'url'     => get_permalink($new_id),
		);
	}

	public static function execute_delete_page($input) {
		$post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;
		$force   = !empty($input['force']);
		if (!$post_id || get_post_type($post_id) !== 'page') {
			return new \WP_Error('invalid_page', __('Page not found.', 'pagelayer'));
		}

		$res = wp_delete_post($post_id, $force);
		return array('success' => (bool)$res);
	}

	public static function execute_validate_page($input) {
		$p_data = null;
		if (isset($input['pagelayer_data']) && is_array($input['pagelayer_data'])) {
			$p_data = $input['pagelayer_data'];
		} elseif (!empty($input['post_id'])) {
			$p_data = get_post_meta((int)$input['post_id'], 'pagelayer-data', true);
		}

		if (!is_array($p_data)) {
			return new \WP_Error('invalid_input', __('No pagelayer_data or valid post_id provided for validation.', 'pagelayer'));
		}

		$result = self::run_layout_validation($p_data);

		return array(
			'valid'       => $result['valid'],
			'score'       => $result['score'],
			'summary'     => $result['valid'] ? sprintf(__('Validation passed cleanly with quality score %d/100.', 'pagelayer'), $result['score']) : sprintf(__('Validation failed with %d errors and %d warnings.', 'pagelayer'), count($result['errors']), count($result['warnings'])),
			'checks'      => array(
				'widget_compatibility' => count($result['errors']) === 0,
				'hierarchy'            => count($result['errors']) === 0,
				'accessibility'        => count($result['warnings']) === 0,
				'seo'                  => $result['h1_count'] === 1,
				'global_styles'        => $result['global_refs'] > 0,
			),
			'errors'      => $result['errors'],
			'warnings'    => $result['warnings'],
			'suggestions' => $result['suggestions'],
		);
	}

	/**
	 * Shared node-walking validation used by both the public validate_page
	 * ability and the internal quality_gate() enforcement in the write paths.
	 */
	/**
	 * Whether one property's `req` map is satisfied by a node's attrs — the
	 * same test pagelayer_render_shortcode() applies before it decides to keep
	 * or discard a gated attribute. A leading "!" on the key negates it.
	 */
	public static function deps_satisfied($requires, $attrs) {
		if (!is_array($requires)) {
			return true;
		}

		foreach ($requires as $dep_key => $dep_val) {
			$negated = (isset($dep_key[0]) && $dep_key[0] === '!');
			$dep_key = $negated ? substr($dep_key, 1) : $dep_key;
			$current = isset($attrs[$dep_key]) ? $attrs[$dep_key] : '';

			$matched = is_array($dep_val)
				? in_array($current, $dep_val, false)
				: ((string) $dep_val === (string) $current);

			if ($negated ? $matched : !$matched) {
				return false;
			}
		}

		return true;
	}

	public static function run_layout_validation($p_data) {
		self::ensure_shortcodes_loaded();
		global $pagelayer;

		$registered_tags = array_keys($pagelayer->shortcodes ?: array());
		$structural_tags = array('pl_row', 'pl_col', 'pl_inner_row', 'pl_inner_col', 'pagelayer_section', 'pagelayer_row', 'pagelayer_col');

		$errors      = array();
		$warnings    = array();
		$suggestions = array();
		$h1_count    = 0;
		$global_refs = 0;
		$total_nodes = 0;

		$schema_cache = array();

		$walk = function($nodes, $parent_tag = '') use (&$walk, &$errors, &$warnings, &$suggestions, &$h1_count, &$global_refs, &$total_nodes, &$schema_cache, $registered_tags, $structural_tags, $pagelayer) {
			if (!is_array($nodes)) return;

			foreach ($nodes as $node) {
				if (!is_array($node) || empty($node['tag'])) continue;
				$total_nodes++;
				$tag = $node['tag'];
				$clean_tag = str_replace('pagelayer_', 'pl_', $tag);
				$attrs = isset($node['attrs']) && is_array($node['attrs']) ? $node['attrs'] : array();
				$node_id = isset($attrs['pagelayer-id']) ? $attrs['pagelayer-id'] : '';

				// 1. Widget compatibility check
				if (!in_array($clean_tag, $registered_tags, true) && !in_array($clean_tag, $structural_tags, true) && !in_array($tag, $structural_tags, true)) {
					$errors[] = array(
						'element_id' => $node_id,
						'widget' => $tag,
						'issue' => 'Unregistered or unsupported widget tag',
						'recommendation' => 'Replace with a widget returned by list_widgets()'
					);
				}

				// 2. Hierarchy check
				if ($clean_tag === 'pl_row') {
					$has_col = false;
					if (isset($node['content']) && is_array($node['content'])) {
						foreach ($node['content'] as $child) {
							if (is_array($child) && isset($child['tag']) && str_replace('pagelayer_', 'pl_', $child['tag']) === 'pl_col') {
								$has_col = true;
								break;
							}
						}
					}
					if (!$has_col) {
						$errors[] = array(
							'element_id' => $node_id,
							'widget' => $tag,
							'issue' => 'Row contains no Column children',
							'recommendation' => 'Place widgets inside pl_col nodes'
						);
					}
				}

				if (!in_array($clean_tag, array('pl_row', 'pl_col', 'pl_inner_row', 'pl_inner_col')) && $parent_tag === 'pl_row') {
					$errors[] = array(
						'element_id' => $node_id,
						'widget' => $tag,
						'issue' => 'Widget placed directly inside Row without Column wrapper',
						'recommendation' => 'Wrap widget inside pl_col node'
					);
				}

				// 3. SEO Heading check
				if ($clean_tag === 'pl_heading') {
					$content = isset($node['content']) && is_string($node['content']) ? $node['content'] : '';
					if (strpos($content, '<h1') !== false || (isset($attrs['heading_type']) && $attrs['heading_type'] === 'h1')) {
						$h1_count++;
					}
				}

				// 4. Accessibility check — pl_image's alt attr is "id-alt"
				// (bound to the "id" image-source field), not "alt".
				if ($clean_tag === 'pl_image') {
					if (empty($attrs['id-alt'])) {
						$warnings[] = array(
							'element_id' => $node_id,
							'widget' => $tag,
							'issue' => 'Image missing alt text for accessibility',
							'recommendation' => 'Add an "id-alt" attribute with descriptive alt text'
						);
					}
				}

				// 5. Global style token check
				foreach ($attrs as $k => $v) {
					if (is_string($v) && strpos($v, '$') === 0) {
						$global_refs++;
					}
				}

				// 4b. Menu widget sanity. An unconfigured Primary Menu renders,
				// but as a plain unstyled list that overflows on phones.
				if ($clean_tag === 'pl_wp_menu') {
					$nav_list = isset($attrs['nav_list']) ? trim((string) $attrs['nav_list']) : '';
					if ($nav_list === '' || $nav_list === '0') {
						$errors[] = array(
							'element_id' => $node_id,
							'widget' => $tag,
							'issue' => 'Primary Menu widget has no menu selected — it renders an empty menu',
							'recommendation' => 'Set attrs.nav_list to a WordPress menu id (get_menus lists them, create_menu builds one)'
						);
					}
					$layout = isset($attrs['layout']) ? $attrs['layout'] : '';
					if (empty($attrs['drop_breakpoint']) && $layout !== 'dropdown') {
						$warnings[] = array(
							'element_id' => $node_id,
							'widget' => $tag,
							'issue' => 'Primary Menu has no drop_breakpoint, so it never collapses into a mobile toggle',
							'recommendation' => 'Set attrs.drop_breakpoint to "tablet" (or "mobile") unless the menu is deliberately always expanded'
						);
					}
				}

				// 5a. Inline CSS in rich text. Layouts written through the
				// abilities are scrubbed of this at the input boundary, so
				// anything reaching here was authored elsewhere — report it,
				// never rewrite it (see the inline-CSS guard).
				foreach (self::inline_css_hits($node) as $hit) {
					$warnings[] = array(
						'element_id' => $node_id,
						'widget' => $tag,
						'issue' => sprintf('Inline CSS in rich text (%s): "%s" — it overrides the widget\'s own controls and cannot be changed from the builder UI', $hit['where'], $hit['css']),
						'recommendation' => 'Drop the style="" attribute and set the equivalent widget attribute instead (get_widget_schema / get_common_styles); if no control covers it, move the rule into "ele_css" using {{element}} as the selector.'
					);
				}

				// 5b. Attribute names + render-time dependencies. Both failure
				// modes below produce a page that renders without the requested
				// styling and without any error, so catch them here.
				$rules = self::widget_attr_rules($clean_tag);
				if (is_array($rules)) {
					$reserved = array('pagelayer-id' => 1, 'pagelayer-srcset' => 1, 'global_id' => 1, 'is_not_sc' => 1);

					foreach ($attrs as $attr_key => $attr_val) {
						if (isset($reserved[$attr_key]) || isset($rules['allowed'][$attr_key])) {
							continue;
						}

						// Image/link props expose derived keys (id-alt, id-title,
						// ele_bg_img-url, ...) that are not declared separately.
						$dash = strrpos($attr_key, '-');
						if ($dash !== false && isset($rules['allowed'][substr($attr_key, 0, $dash)])) {
							continue;
						}

						$errors[] = array(
							'element_id' => $node_id,
							'widget' => $tag,
							'issue' => sprintf('"%s" is not an attribute of %s — it will be dropped at render and the styling will not appear', $attr_key, $tag),
							'recommendation' => sprintf('Call get_widget_schema({"widget":"%s"}) and use one of its real property names.', $tag)
						);
					}

					foreach ($rules['req'] as $attr_key => $requires) {
						if (!isset($attrs[$attr_key]) || $attrs[$attr_key] === '') {
							continue;
						}

						foreach ($requires as $dep_key => $dep_val) {
							$negated = (isset($dep_key[0]) && $dep_key[0] === '!');
							$dep_key = $negated ? substr($dep_key, 1) : $dep_key;
							$current = isset($attrs[$dep_key]) ? $attrs[$dep_key] : '';

							$matched = is_array($dep_val)
								? in_array($current, $dep_val, false)
								: ((string) $dep_val === (string) $current);

							if ($negated ? !$matched : $matched) {
								continue;
							}

							$expected = is_array($dep_val) ? implode('" or "', $dep_val) : $dep_val;
							$errors[] = array(
								'element_id' => $node_id,
								'widget' => $tag,
								'issue' => sprintf('"%s" is set but its dependency is not, so Pagelayer discards it at render', $attr_key),
								'recommendation' => $negated
									? sprintf('Remove attrs.%s (it must NOT be "%s") or drop attrs.%s.', $dep_key, $expected, $attr_key)
									: sprintf('Also set attrs.%s to "%s" on this same node.', $dep_key, $expected)
							);
						}
					}
				}

				// 6. Placeholder / default-content check (generic — works for any niche)
				$placeholder_patterns = array(
					'/^this is icon box$/i',
					'/^lorem ipsum/i',
					'/^your (title|heading|text) here$/i',
					'/^click here$/i',
					'/^enter (your )?(title|description|text)/i',
					'/choose your image/i',
				);
				$text_fields_to_check = array('title', 'desc');
				foreach ($text_fields_to_check as $field) {
					if (!empty($attrs[$field]) && is_string($attrs[$field])) {
						foreach ($placeholder_patterns as $pattern) {
							if (preg_match($pattern, trim($attrs[$field]))) {
								$errors[] = array(
									'element_id' => $node_id,
									'widget' => $tag,
									'issue' => 'Widget still contains builder placeholder/default text instead of real content',
									'recommendation' => 'Write unique, on-topic copy for this widget before publishing'
								);
							}
						}
					}
				}
				// The node's own "content" field (not attrs.content) is how
				// most leaf/composite widgets carry their main text — check
				// it too, e.g. an untouched pl_heading/pl_text default.
				if (isset($node['content']) && is_string($node['content']) && $node['content'] !== '') {
					foreach ($placeholder_patterns as $pattern) {
						if (preg_match($pattern, trim(wp_strip_all_tags($node['content'])))) {
							$errors[] = array(
								'element_id' => $node_id,
								'widget' => $tag,
								'issue' => 'Widget still contains builder placeholder/default text instead of real content',
								'recommendation' => 'Write unique, on-topic copy for this widget before publishing'
							);
							break;
						}
					}
				}
				// pl_image's real image attr is "id" (not "img") — accepts a
				// full https:// URL or a numeric WP attachment ID. Flag it
				// empty or still pointing at the widget's own default image.
				// A missing image renders the builder's placeholder, which is an
				// acceptable intermediate state — reported, but it does not block
				// a save the way a broken layout does.
				$image_id_val    = isset($attrs['id']) ? $attrs['id'] : '';
				$is_default_image = is_string($image_id_val) && (stripos($image_id_val, '/images/default-image.png') !== false || stripos($image_id_val, 'choose your image') !== false);
				if ($clean_tag === 'pl_image' && (empty($image_id_val) || $is_default_image)) {
					$warnings[] = array(
						'element_id' => $node_id,
						'widget' => $tag,
						'issue' => 'Image widget has no image set — the builder placeholder will render',
						'recommendation' => 'Set attrs.id to a real image URL (e.g. from search_images) — note the field is named "id", not "img"'
					);
				}

				// 7. Missing primary content field, derived from this widget's
				// OWN registered schema (not a hardcoded field-name list, so it
				// generalizes to every widget). If a text/textarea/editor field
				// is absent and that field's builder default is itself generic
				// filler (e.g. pl_iconbox's service_heading defaults to the
				// literal string "This is Icon Box"), the widget will silently
				// render that filler — flag it before it ever gets that far.
				if (!empty($pagelayer->shortcodes[$clean_tag])) {
					if (!isset($schema_cache[$clean_tag])) {
						$schema_cache[$clean_tag] = self::extract_widget_schema($clean_tag, $pagelayer->shortcodes[$clean_tag]);
					}
					$w_schema  = $schema_cache[$clean_tag];
					$inner_key = isset($pagelayer->shortcodes[$clean_tag]['innerHTML']) ? $pagelayer->shortcodes[$clean_tag]['innerHTML'] : '';
					$has_content_bridge = $inner_key && isset($node['content']) && is_string($node['content']) && $node['content'] !== '';

					foreach ($w_schema['sections'] as $section) {
						foreach ($section['properties'] as $prop_key => $prop) {
							$type = isset($prop['type']) ? $prop['type'] : '';
							if (!in_array($type, array('text', 'textarea', 'editor'), true)) {
								continue;
							}
							if (isset($attrs[$prop_key]) && $attrs[$prop_key] !== '') {
								continue; // explicitly set — fine
							}
							if ($prop_key === $inner_key && $has_content_bridge) {
								continue; // supplied via node.content instead, which is valid
							}
							// A field gated behind a companion attr only renders
							// when that companion is set, so its filler default
							// can never reach the page. pl_image's "text" (the
							// overlay caption, gated on overlay=true) defaults to
							// lorem ipsum — demanding it turned every plain image
							// into a rejected page, with advice that makes no
							// sense for an image widget.
							if (is_array($rules) && !empty($rules['req'][$prop_key]) && !self::deps_satisfied($rules['req'][$prop_key], $attrs)) {
								continue;
							}
							$default = isset($prop['default']) ? $prop['default'] : '';
							if (!is_string($default) || $default === '') {
								continue;
							}
							foreach ($placeholder_patterns as $pattern) {
								if (preg_match($pattern, trim(wp_strip_all_tags($default)))) {
									$errors[] = array(
										'element_id' => $node_id,
										'widget' => $tag,
										'issue' => sprintf('Widget did not set "%s" (%s) — it will silently render the builder\'s own default filler text/image for this field', $prop_key, !empty($prop['label']) ? $prop['label'] : $prop_key),
										'recommendation' => sprintf('Set attrs.%s to real, on-topic content. Call get_widget_examples({"widget":"%s"}) for the exact field name.', $prop_key, $tag)
									);
									break;
								}
							}
						}
					}
				}

				// Recurse children
				if (isset($node['content']) && is_array($node['content'])) {
					$walk($node['content'], $clean_tag);
				}
			}
		};

		$walk($p_data);

		if ($h1_count === 0) {
			$warnings[] = array(
				'element_id' => '',
				'widget' => 'pl_heading',
				'issue' => 'No <h1> heading found on page',
				'recommendation' => 'Include one primary <h1> heading for SEO'
			);
		} elseif ($h1_count > 1) {
			$warnings[] = array(
				'element_id' => '',
				'widget' => 'pl_heading',
				'issue' => 'Multiple <h1> headings found',
				'recommendation' => 'Use only one <h1> per page for proper SEO heading structure'
			);
		}

		if ($global_refs === 0 && $total_nodes > 3) {
			$suggestions[] = 'Consider using global design tokens ($primary, $secondary, etc.) in widget attributes for consistent site styling.';
		}

		$valid = (count($errors) === 0);
		$score = max(0, 100 - (count($errors) * 20) - (count($warnings) * 5));

		return array(
			'valid'       => $valid,
			'score'       => $score,
			'errors'      => $errors,
			'warnings'    => $warnings,
			'suggestions' => $suggestions,
			'h1_count'    => $h1_count,
			'global_refs' => $global_refs,
			'total_nodes' => $total_nodes,
		);
	}

	// ------------------------------------------------------------------
	// Inline-CSS guard
	// ------------------------------------------------------------------
	//
	// Rich text is content, never styling. When an AI client writes
	// <h2 style="color:#fff;font-size:42px"> into a widget's editor field, that
	// CSS is rendered verbatim and outranks the whole builder: the widget's own
	// color/typography controls no longer show or change anything, the
	// _tablet/_mobile variants never apply to it, a global-color change leaves
	// the page half-rebranded, and nobody can undo it from the Pagelayer UI.
	// Every such declaration belongs on the node instead — as a real widget
	// attribute, or, when the widget has no control for it, as a rule in the
	// "ele_css" custom-CSS attribute.
	//
	// The scrub below runs on AI-supplied input ONLY. Data already stored on the
	// post is left alone on purpose: Pagelayer's own WYSIWYG writes inline
	// styles (the justify buttons run execCommand with styleWithCSS), so
	// rewriting stored content would silently destroy human edits.

	/**
	 * Widgets whose whole purpose is to carry third-party markup — an embed
	 * snippet legitimately ships with its own style attributes.
	 */
	protected static $inline_css_exempt_tags = array('pl_embed' => 1, 'pl_shortcodes' => 1);

	/**
	 * One opening HTML tag. Quoted attribute values are consumed as a unit so a
	 * ">" inside one (title="a > b") neither ends the match early nor lets a
	 * style attribute after it slip through.
	 */
	const INLINE_CSS_TAG_RE = '#<[a-z][a-z0-9:_-]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>#i';

	/**
	 * Every inline CSS declaration in one HTML string: style="" attributes on
	 * tags, plus whole <style> blocks. Read-only.
	 */
	public static function find_inline_css($html) {
		$found = array();

		if (!is_string($html) || $html === '' || stripos($html, 'style') === false) {
			return $found;
		}

		if (preg_match_all('#<\s*style\b[^>]*>(.*?)<\s*/\s*style\s*>#is', $html, $blocks)) {
			foreach ($blocks[1] as $css) {
				$css = trim(preg_replace('/\s+/', ' ', $css));
				if ($css !== '') {
					$found[] = '<style> ' . $css;
				}
			}
		}

		if (preg_match_all(self::INLINE_CSS_TAG_RE, $html, $tags)) {
			foreach ($tags[0] as $tag) {
				if (!preg_match('#\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))#i', $tag, $m)) {
					continue;
				}
				// Only one of the three alternatives participates in a match.
				$css = '';
				foreach (array(4, 3, 2) as $group) {
					if (isset($m[$group]) && $m[$group] !== '') {
						$css = $m[$group];
						break;
					}
				}
				$css = trim(preg_replace('/\s+/', ' ', $css));
				if ($css !== '') {
					$found[] = $css;
				}
			}
		}

		return $found;
	}

	/**
	 * The same string with every style="" attribute and <style> block removed.
	 * Content markup (<strong>, <a>, <br>, lists, ...) is untouched.
	 */
	public static function strip_inline_css($html) {
		if (!is_string($html) || $html === '' || stripos($html, 'style') === false) {
			return $html;
		}

		$html = preg_replace('#<\s*style\b[^>]*>.*?<\s*/\s*style\s*>#is', '', $html);
		$html = preg_replace('#<\s*link\b[^>]*\srel\s*=\s*["\']?stylesheet["\']?[^>]*>#is', '', $html);

		return preg_replace_callback(self::INLINE_CSS_TAG_RE, function($m) {
			$tag = preg_replace('#\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $m[0], -1, $count);
			if (!$count) {
				return $m[0];
			}
			// Do not leave "<span >" or break a self-closing tag behind.
			return preg_replace('#\s+(/?)>$#', '$1>', $tag);
		}, $html);
	}

	/**
	 * "ele_attributes" is a name=value;name2=value2 list of extra HTML
	 * attributes — a style entry there is inline CSS by another route.
	 */
	protected static function attributes_field_style($value) {
		if (!is_string($value) || stripos($value, 'style') === false) {
			return '';
		}

		foreach (explode(';', $value) as $pair) {
			$parts = explode('=', $pair, 2);
			if (strtolower(trim($parts[0])) === 'style' && isset($parts[1]) && trim($parts[1]) !== '') {
				return trim($parts[1]);
			}
		}

		return '';
	}

	protected static function strip_attributes_field_style($value) {
		$kept = array();

		foreach (explode(';', $value) as $pair) {
			if (trim($pair) === '') {
				continue;
			}
			$parts = explode('=', $pair, 2);
			if (strtolower(trim($parts[0])) === 'style') {
				continue;
			}
			$kept[] = trim($pair);
		}

		return implode(';', $kept);
	}

	/**
	 * Inline CSS carried by ONE node — its rich-text content and its attribute
	 * values. Children are not visited. Read-only.
	 */
	public static function inline_css_hits($node) {
		$hits = array();

		if (!is_array($node)) {
			return $hits;
		}

		$tag       = isset($node['tag']) ? $node['tag'] : '';
		$clean_tag = str_replace('pagelayer_', 'pl_', $tag);
		if (isset(self::$inline_css_exempt_tags[$clean_tag])) {
			return $hits;
		}

		$attrs   = isset($node['attrs']) && is_array($node['attrs']) ? $node['attrs'] : array();
		$node_id = isset($attrs['pagelayer-id']) ? $attrs['pagelayer-id'] : '';

		if (isset($node['content']) && is_string($node['content'])) {
			foreach (self::find_inline_css($node['content']) as $css) {
				$hits[] = array('element_id' => $node_id, 'widget' => $tag, 'where' => 'content', 'css' => $css);
			}
		}

		foreach ($attrs as $key => $value) {
			// ele_css is the sanctioned place for hand-written CSS.
			if ($key === 'ele_css') {
				continue;
			}

			// Nested values (a section spec's items[], a link prop's sub-keys).
			if (is_array($value)) {
				foreach (self::inline_css_hits(array('tag' => $tag, 'attrs' => $value)) as $nested) {
					$nested['element_id'] = $node_id;
					$nested['where']      = 'attrs.' . $key . '.' . preg_replace('/^attrs\./', '', $nested['where']);
					$hits[] = $nested;
				}
				continue;
			}

			if (!is_string($value)) {
				continue;
			}

			if ($key === 'ele_attributes') {
				$css = self::attributes_field_style($value);
				if ($css !== '') {
					$hits[] = array('element_id' => $node_id, 'widget' => $tag, 'where' => 'attrs.ele_attributes', 'css' => $css);
				}
				continue;
			}

			foreach (self::find_inline_css($value) as $css) {
				$hits[] = array('element_id' => $node_id, 'widget' => $tag, 'where' => 'attrs.' . $key, 'css' => $css);
			}
		}

		return $hits;
	}

	/**
	 * Strips inline CSS from one node and everything below it, collecting what
	 * was removed into $found.
	 */
	public static function scrub_node_inline_css(&$node, &$found) {
		if (!is_array($node)) {
			return;
		}

		// A section spec carries its copy in top-level keys (heading, sub,
		// items[].text ...) rather than in attrs, so scrub the whole spec.
		if (!empty($node['section']) && is_string($node['section'])) {
			$section = $node['section'];
			$before  = $node;
			unset($before['section']);

			foreach (self::inline_css_hits(array('tag' => 'section:' . $section, 'attrs' => $before)) as $hit) {
				$found[] = $hit;
			}

			$after = self::strip_attrs_inline_css($before);
			$after['section'] = $section;
			$node = $after;
			return;
		}

		$clean_tag = isset($node['tag']) ? str_replace('pagelayer_', 'pl_', $node['tag']) : '';
		if (!isset(self::$inline_css_exempt_tags[$clean_tag])) {
			foreach (self::inline_css_hits($node) as $hit) {
				$found[] = $hit;
			}

			if (isset($node['content']) && is_string($node['content'])) {
				$node['content'] = self::strip_inline_css($node['content']);
			}

			if (isset($node['attrs']) && is_array($node['attrs'])) {
				$node['attrs'] = self::strip_attrs_inline_css($node['attrs']);
			}
		}

		if (isset($node['content']) && is_array($node['content'])) {
			self::scrub_layout_inline_css($node['content'], $found);
		}
	}

	/**
	 * Same, for a list of nodes (a whole pagelayer_data tree).
	 */
	public static function scrub_layout_inline_css(&$nodes, &$found) {
		if (!is_array($nodes)) {
			return;
		}

		foreach ($nodes as &$node) {
			self::scrub_node_inline_css($node, $found);
		}
		unset($node);
	}

	/**
	 * Same, for a bare attrs map (update_element / change_styles send one
	 * without a node around it).
	 */
	public static function strip_attrs_inline_css($attrs) {
		if (!is_array($attrs)) {
			return $attrs;
		}

		foreach ($attrs as $key => $value) {
			if ($key === 'ele_css') {
				continue;
			}
			if (is_array($value)) {
				$attrs[$key] = self::strip_attrs_inline_css($value);
			} elseif (is_string($value)) {
				$attrs[$key] = ($key === 'ele_attributes')
					? self::strip_attributes_field_style($value)
					: self::strip_inline_css($value);
			}
		}

		return $attrs;
	}

	/**
	 * Failed tool call describing the inline CSS that was rejected, so the
	 * client rewrites it as attributes instead of retrying the same markup.
	 */
	protected static function inline_css_error($found) {
		$lines = array();
		foreach (array_slice($found, 0, 6) as $hit) {
			$lines[] = sprintf('[%s%s] %s: "%s"',
				!empty($hit['widget']) ? $hit['widget'] : '?',
				!empty($hit['element_id']) ? ' ' . $hit['element_id'] : '',
				$hit['where'],
				$hit['css']
			);
		}
		$more = count($found) > 6 ? sprintf(__(' (+%d more)', 'pagelayer'), count($found) - 6) : '';

		$message = sprintf(
			__('Inline CSS in rich text is not allowed — nothing was saved. %1$d occurrence(s): %2$s%3$s. Rich text carries content only (<strong>, <em>, <a>, <br>, lists). Styling belongs on the node: use the widget\'s own attributes (get_widget_schema) or the shared style props every widget accepts (get_common_styles — color, font_size, font_weight, ele_padding, ele_margin, ... plus their _tablet/_mobile variants). ONLY when no control exists for what you need, put a real CSS rule in the "ele_css" attribute of that same node, using {{element}} as the selector, e.g. ele_css: "{{element}} .pagelayer-heading-holder h2 { letter-spacing: 2px; }". Resubmit with every style="" attribute removed.', 'pagelayer'),
			count($found),
			implode(' | ', $lines),
			$more
		);

		return new \WP_Error('inline_css_not_allowed', $message, array('occurrences' => $found));
	}

	/**
	 * Enforcement entry point for AI-supplied layout data. The tree is scrubbed
	 * in place, and a WP_Error is returned so the caller abandons the write and
	 * the client resubmits the styling as attributes. Returns null when there
	 * was nothing to strip. Unlike quality_gate this is not bypassable with
	 * skip_validation — inline CSS in rich text is never a valid layout.
	 */
	protected static function inline_css_gate(&$data) {
		$found = array();
		self::scrub_layout_inline_css($data, $found);

		if (empty($found)) {
			return null;
		}

		return self::inline_css_error($found);
	}

	/**
	 * Hard content-quality gate used by every layout-writing ability. Returns
	 * true when the layout is clean, or a WP_Error describing what to fix
	 * (placeholder text, missing images, unregistered widgets, broken
	 * hierarchy) when it is not. Callers should return the WP_Error as-is so
	 * the AI client sees it as a failed tool call and can retry with fixes.
	 */
	protected static function quality_gate($data) {
		if (!is_array($data) || empty($data)) {
			return true;
		}

		$result = self::run_layout_validation($data);
		if (empty($result['errors'])) {
			return true;
		}

		$lines = array();
		foreach (array_slice($result['errors'], 0, 8) as $err) {
			$widget = isset($err['widget']) ? $err['widget'] : '?';
			$lines[] = sprintf('[%s] %s — %s', $widget, $err['issue'], $err['recommendation']);
		}
		$more = count($result['errors']) > 8 ? sprintf(__(' (+%d more)', 'pagelayer'), count($result['errors']) - 8) : '';

		$message = sprintf(
			__('Content quality gate failed with %1$d issue(s) — nothing was saved. Fix these and resubmit (or pass skip_validation:true to bypass for an intentional draft): %2$s%3$s', 'pagelayer'),
			count($result['errors']),
			implode(' | ', $lines),
			$more
		);

		return new \WP_Error('quality_gate_failed', $message, array('errors' => $result['errors']));
	}

	// ------------------------------------------------------------------
	// Post Callbacks (Individual Posts)
	// ------------------------------------------------------------------

	public static function execute_create_post($input) {
		$input['post_type'] = 'post';
		return self::create_post_or_page($input);
	}

	public static function execute_update_post($input) {
		$post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;
		$post    = get_post($post_id);
		if (!$post || $post->post_type !== 'post') {
			return new \WP_Error('invalid_post', __('Blog post not found.', 'pagelayer'));
		}

		if (isset($input['title'])) {
			wp_update_post(array('ID' => $post_id, 'post_title' => sanitize_text_field($input['title'])));
		}
		if (isset($input['status'])) {
			wp_update_post(array('ID' => $post_id, 'post_status' => sanitize_text_field($input['status'])));
		}
		if (isset($input['excerpt'])) {
			wp_update_post(array('ID' => $post_id, 'post_excerpt' => sanitize_textarea_field($input['excerpt'])));
		}
		if (isset($input['pagelayer_data']) && is_array($input['pagelayer_data'])) {
			$inline_css = self::inline_css_gate($input['pagelayer_data']);
			if (is_wp_error($inline_css)) {
				return $inline_css;
			}

			$normalized = self::normalize_layout_data($input['pagelayer_data']);
			update_post_meta($post_id, 'pagelayer-data', $normalized);
			$blocks_content = self::serialize_layout_to_blocks($normalized);
			wp_update_post(array('ID' => $post_id, 'post_content' => $blocks_content));
		}
		if (!empty($input['categories']) && is_array($input['categories'])) {
			$cat_ids = array();
			foreach ($input['categories'] as $cat) {
				if (is_numeric($cat)) {
					$cat_ids[] = (int) $cat;
				} else {
					$term = get_term_by('name', $cat, 'category');
					if (!$term) {
						$term = wp_insert_term($cat, 'category');
					}
					if (!is_wp_error($term) && isset($term['term_id'])) {
						$cat_ids[] = (int) $term['term_id'];
					}
				}
			}
			if (!empty($cat_ids)) {
				wp_set_post_categories($post_id, $cat_ids);
			}
		}
		if (!empty($input['tags'])) {
			wp_set_post_tags($post_id, $input['tags']);
		}
		if (!empty($input['featured_image'])) {
			$img_id = 0;
			if (is_numeric($input['featured_image'])) {
				$img_id = (int) $input['featured_image'];
			} elseif (filter_var($input['featured_image'], FILTER_VALIDATE_URL)) {
				$upload = self::execute_upload_media(array('url' => $input['featured_image']));
				if (!is_wp_error($upload) && !empty($upload['attachment_id'])) {
					$img_id = $upload['attachment_id'];
				}
			}
			if ($img_id > 0) {
				set_post_thumbnail($post_id, $img_id);
			}
		}

		return array('success' => true, 'post_id' => $post_id, 'url' => get_permalink($post_id));
	}

	public static function execute_get_post($input) {
		$post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;
		$post    = get_post($post_id);
		if (!$post || $post->post_type !== 'post') {
			return new \WP_Error('invalid_post', __('Blog post not found.', 'pagelayer'));
		}

		$data       = get_post_meta($post_id, 'pagelayer-data', true);
		$cats       = wp_get_post_categories($post_id, array('fields' => 'names'));
		$tags       = wp_get_post_tags($post_id, array('fields' => 'names'));
		$feat_image = get_the_post_thumbnail_url($post_id, 'full');

		return array(
			'post_id'        => $post->ID,
			'title'          => $post->post_title,
			'status'         => $post->post_status,
			'excerpt'        => $post->post_excerpt,
			'url'            => get_permalink($post->ID),
			'edit_url'       => admin_url('post.php?post=' . $post->ID . '&action=edit'),
			'categories'     => $cats,
			'tags'           => $tags,
			'featured_image' => $feat_image ?: '',
			'pagelayer_data' => is_array($data) ? $data : array(),
		);
	}

	public static function execute_list_posts($input) {
		$limit  = isset($input['limit']) ? (int) $input['limit'] : 20;
		$status = isset($input['status']) ? sanitize_text_field($input['status']) : 'any';

		$args = array(
			'post_type'      => 'post',
			'posts_per_page' => $limit,
			'post_status'    => $status,
			'meta_query'     => array(
				array('key' => 'pagelayer-data', 'compare' => 'EXISTS'),
			),
		);

		if (!empty($input['category'])) {
			$args['category_name'] = sanitize_text_field($input['category']);
		}

		$query = new \WP_Query($args);
		$posts = array();

		foreach ($query->posts as $post) {
			$posts[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'status' => $post->post_status,
				'url'    => get_permalink($post->ID),
			);
		}

		return array('posts' => $posts);
	}

	public static function execute_publish_post($input) {
		return self::execute_publish_page($input);
	}

	public static function execute_duplicate_post($input) {
		return self::execute_duplicate_page($input);
	}

	public static function execute_delete_post($input) {
		$post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;
		$force   = !empty($input['force']);
		if (!$post_id || get_post_type($post_id) !== 'post') {
			return new \WP_Error('invalid_post', __('Blog post not found.', 'pagelayer'));
		}

		$res = wp_delete_post($post_id, $force);
		return array('success' => (bool)$res);
	}

	// ------------------------------------------------------------------
	// Core Post/Page Creator Helper
	// ------------------------------------------------------------------

	protected static function create_post_or_page($input) {
		self::maybe_update_global_styles($input);
		$title     = isset($input['title']) ? sanitize_text_field($input['title']) : '';
		$post_type = isset($input['post_type']) ? sanitize_text_field($input['post_type']) : 'page';
		$status    = isset($input['status']) ? sanitize_text_field($input['status']) : 'publish';

		if (empty($title)) {
			return new \WP_Error('missing_title', __('A title is required.', 'pagelayer'));
		}

		if (!isset($input['pagelayer_data']) || !is_array($input['pagelayer_data'])) {
			return new \WP_Error('missing_pagelayer_data', __('pagelayer_data is required.', 'pagelayer'));
		}

		$inline_css = self::inline_css_gate($input['pagelayer_data']);
		if (is_wp_error($inline_css)) {
			return $inline_css;
		}

		$normalized_data = self::normalize_layout_data($input['pagelayer_data']);

		if (empty($input['skip_validation'])) {
			$gate = self::quality_gate($normalized_data);
			if (is_wp_error($gate)) {
				return $gate;
			}
		}

		$postarr = array(
			'post_title'  => $title,
			'post_type'   => $post_type,
			'post_status' => $status,
		);

		if (isset($input['excerpt'])) {
			$postarr['post_excerpt'] = sanitize_textarea_field($input['excerpt']);
		}

		$post_id = wp_insert_post(wp_slash($postarr), true);
		if (is_wp_error($post_id)) {
			return $post_id;
		}

		// 'pagelayer-data' is the single source of truth for the layout tree —
		// every other ability (add_element, update_element, validate_page,
		// find_elements, navigator, transactions, duplicate) reads/writes it
		// directly. It also doubles as the native PageLayer "is this a
		// PageLayer post" flag (checked via empty()), which a populated array
		// satisfies just as well as the old time() placeholder did.
		update_post_meta($post_id, 'pagelayer-data', $normalized_data);

		$blocks_content = self::serialize_layout_to_blocks($normalized_data);
		wp_update_post(array(
			'ID'           => $post_id,
			'post_content' => $blocks_content,
		));

		if (!empty($input['categories']) && is_array($input['categories'])) {
			$cat_ids = array();
			foreach ($input['categories'] as $cat) {
				if (is_numeric($cat)) {
					$cat_ids[] = (int) $cat;
				} else {
					$term = get_term_by('name', $cat, 'category');
					if (!$term) {
						$term = wp_insert_term($cat, 'category');
					}
					if (!is_wp_error($term) && isset($term['term_id'])) {
						$cat_ids[] = (int) $term['term_id'];
					}
				}
			}
			if (!empty($cat_ids)) {
				wp_set_post_categories($post_id, $cat_ids);
			}
		}

		if (!empty($input['tags'])) {
			wp_set_post_tags($post_id, $input['tags']);
		}

		if (!empty($input['featured_image'])) {
			$img_id = 0;
			if (is_numeric($input['featured_image'])) {
				$img_id = (int) $input['featured_image'];
			} elseif (filter_var($input['featured_image'], FILTER_VALIDATE_URL)) {
				$upload = self::execute_upload_media(array('url' => $input['featured_image']));
				if (!is_wp_error($upload) && !empty($upload['attachment_id'])) {
					$img_id = $upload['attachment_id'];
				}
			}
			if ($img_id > 0) {
				set_post_thumbnail($post_id, $img_id);
			}
		}

		if (!empty($input['is_homepage'])) {
			update_option('show_on_front', 'page');
			update_option('page_on_front', $post_id);
		}
		if (!empty($input['is_posts_page'])) {
			update_option('page_for_posts', $post_id);
		}

		return array(
			'post_id'  => $post_id,
			'url'      => get_permalink($post_id),
			'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
		);
	}

	// ------------------------------------------------------------------
	// Builder & Website Callbacks
	// ------------------------------------------------------------------

	protected static function widget_exists($tag) {
		self::ensure_shortcodes_loaded();
		global $pagelayer;
		return !empty($pagelayer->shortcodes[$tag]);
	}

	/**
	 * The nav menu a generated site's header points at. Uses the caller's "menu"
	 * spec when given, otherwise builds one from the pages just created (home
	 * first), and reuses an existing populated menu rather than duplicating it.
	 */
	protected static function ensure_site_menu($input, $pages_created, $homepage_id) {
		if (!empty($input['menu']) && is_array($input['menu'])) {
			$spec = $input['menu'];
			if (empty($spec['name'])) {
				$spec['name'] = __('Primary Menu', 'pagelayer');
			}
			if (empty($spec['items'])) {
				$spec['items'] = self::menu_items_from_pages($pages_created, $homepage_id);
			}
			if (empty($spec['items'])) {
				return array();
			}
			$res = self::execute_create_menu($spec);
			return is_wp_error($res) ? array('error' => $res->get_error_message()) : $res;
		}

		// Nothing asked for, but the site already has a menu with items in it —
		// use that rather than creating a second one nobody asked for.
		foreach (wp_get_nav_menus() as $existing) {
			if ((int) $existing->count > 0) {
				return array('menu_id' => (int) $existing->term_id, 'name' => $existing->name, 'reused' => true);
			}
		}

		$items = self::menu_items_from_pages($pages_created, $homepage_id);
		if (empty($items)) {
			return array();
		}

		$spec = array('name' => __('Primary Menu', 'pagelayer'), 'items' => $items);

		// Assign to the theme's own location too, so the menu still works in
		// theme areas that are not rendered by the Pagelayer header.
		$registered = function_exists('get_registered_nav_menus') ? get_registered_nav_menus() : array();
		if (!empty($registered)) {
			$assigned = (array) get_nav_menu_locations();
			foreach (array_keys($registered) as $slug) {
				if (empty($assigned[$slug])) {
					$spec['location'] = $slug;
					break;
				}
			}
		}

		$res = self::execute_create_menu($spec);
		return is_wp_error($res) ? array('error' => $res->get_error_message()) : $res;
	}

	protected static function menu_items_from_pages($pages_created, $homepage_id) {
		$items = array();
		$home  = array();

		foreach ($pages_created as $page) {
			if (empty($page['post_id'])) {
				continue;
			}
			$row = array('title' => get_the_title($page['post_id']), 'page_id' => (int) $page['post_id']);
			if ($homepage_id && (int) $page['post_id'] === (int) $homepage_id) {
				$home = $row;
				continue;
			}
			$items[] = $row;
		}

		if (!empty($home)) {
			array_unshift($items, $home);
		}

		return $items;
	}

	/**
	 * Fills in the menu id on any pl_wp_menu node that was written before the
	 * menu existed (empty nav_list, or the literal "auto").
	 */
	protected static function fill_menu_placeholder(&$nodes, $menu_id) {
		if (!is_array($nodes)) {
			return;
		}

		foreach ($nodes as &$node) {
			if (!is_array($node)) {
				continue;
			}
			if (!empty($node['tag']) && str_replace('pagelayer_', 'pl_', $node['tag']) === 'pl_wp_menu') {
				$current = isset($node['attrs']['nav_list']) ? trim((string) $node['attrs']['nav_list']) : '';
				if ($current === '' || $current === '0' || strtolower($current) === 'auto') {
					if (!isset($node['attrs']) || !is_array($node['attrs'])) {
						$node['attrs'] = array();
					}
					$node['attrs']['nav_list'] = (string) $menu_id;
				}
			}
			if (isset($node['content']) && is_array($node['content'])) {
				self::fill_menu_placeholder($node['content'], $menu_id);
			}
		}
		unset($node);
	}

	public static function execute_create_website($input) {
		self::maybe_update_global_styles($input);
		$site_name = isset($input['site_name']) ? sanitize_text_field($input['site_name']) : get_option('blogname');
		if (empty($site_name)) {
			return new \WP_Error('missing_site_name', __('A site name is required.', 'pagelayer'));
		}

		update_option('blogname', $site_name);
		// Fallback color used ONLY for bare scaffolding below if no header/footer exists yet.
		// This is NOT a themed design — the caller is expected to supply real pagelayer_data
		// for every page, and its own global_colors/global_fonts for the actual brand.
		$primary = !empty($input['primary_color']) ? sanitize_text_field($input['primary_color']) : '#0F172A';

		$has_template_type = function($type) {
			$posts = get_posts(array(
				'post_type'      => 'pagelayer-template',
				'post_status'    => array('publish', 'draft'),
				'posts_per_page' => 1,
				'meta_key'       => 'pagelayer_template_type',
				'meta_value'     => $type,
				'fields'         => 'ids',
			));
			return !empty($posts);
		};

		$created_templates = array();
		$single_page_site  = !empty($input['single_page_site']);

		// 1. Auto-create a bare, minimal Footer scaffold ONLY if none exists yet.
		if (!$has_template_type('footer')) {
			$footer_data = array(
				array(
					'tag' => 'pl_row',
					'attrs' => array('stretch' => 'full', 'ele_bg_type' => 'color', 'ele_bg_color' => $primary, 'ele_padding' => '40px,0px,40px,0px'),
					'content' => array(
						array('tag' => 'pl_col', 'attrs' => array('col' => 12), 'content' => array(
							// pl_text has neither "color" nor "align" (its only
							// param is the editor field) — both were silently
							// dropped here, leaving dark text on a dark footer.
							// pl_heading carries the same <p> markup and does
							// have colour and alignment.
							array('tag' => 'pl_heading', 'attrs' => array('color' => '#ffffff', 'align' => 'center', 'font_size' => '14', 'font_weight' => '400'), 'content' => '<p>&copy; ' . date('Y') . ' ' . esc_html($site_name) . '</p>')
						))
					)
				)
			);
			$res = self::execute_create_template(array(
				'title'          => $site_name . ' Footer',
				'type'           => 'footer',
				'pagelayer_data' => $footer_data,
				'conditions'     => array(array('type' => 'include', 'template' => '', 'sub_template' => '', 'id' => ''))
			));
			if (!is_wp_error($res)) {
				$created_templates['footer'] = $res;
			}
		}

		// Per-item failures are collected and reported, never swallowed. Returning
		// success:true while three of six pages were rejected by the quality gate
		// left the caller believing it had built a site that did not exist.
		$failures = array();

		// Pages come BEFORE the templates now: the header's Primary Menu widget
		// has to point at a menu, and a menu of pages cannot be built until the
		// pages exist. Templates never reference pages, so nothing is lost by
		// the reorder.
		$pages_created = array();
		$posts_created = array();
		$homepage_id   = null;

		if (isset($input['pages']) && is_array($input['pages'])) {
			foreach ($input['pages'] as $i => $page_input) {
				$res = self::create_post_or_page($page_input);

				if (is_wp_error($res)) {
					$failures[] = array(
						'item'  => 'pages[' . $i . ']',
						'title' => isset($page_input['title']) ? $page_input['title'] : '',
						'error' => $res->get_error_message(),
					);
					continue;
				}

				$p_type = isset($page_input['post_type']) ? $page_input['post_type'] : 'page';
				if ($p_type === 'post') {
					$posts_created[] = $res;
				} else {
					$pages_created[] = $res;
				}
				if (!empty($page_input['is_homepage'])) {
					$homepage_id = $res['post_id'];
				}
			}
		}

		// Front page. create_page honours is_homepage, but create_website — the
		// tool callers are told to prefer — only ever used $homepage_id to sort
		// the menu, so a generated site kept WordPress's default "latest posts"
		// root and the designed home page sat at /home/ where nobody saw it.
		//
		// Most callers never send is_homepage at all (it is not mentioned in the
		// pages description), so fall back to a page that is obviously the home
		// page, then to the first one created. Only ever applied when the site
		// is still on the WordPress default, so a deliberate existing front page
		// is never clobbered.
		if (!$homepage_id && !empty($pages_created)) {
			foreach ($pages_created as $page) {
				// The create result carries no title, only ids and urls.
				$title = strtolower(trim(get_the_title($page['post_id'])));
				if (in_array($title, array('home', 'homepage', 'home page'), true)) {
					$homepage_id = $page['post_id'];
					break;
				}
			}
			if (!$homepage_id) {
				$homepage_id = $pages_created[0]['post_id'];
			}
		}

		if ($homepage_id && 'page' !== get_option('show_on_front')) {
			update_option('show_on_front', 'page');
			update_option('page_on_front', $homepage_id);
		}

		// The site's navigation menu, so the header has something real to render.
		$menu = $single_page_site ? array() : self::ensure_site_menu($input, $pages_created, $homepage_id);
		$menu_id = isset($menu['menu_id']) ? (int) $menu['menu_id'] : 0;

		if (isset($input['theme_templates']) && is_array($input['theme_templates'])) {
			foreach ($input['theme_templates'] as $i => $tt) {
				// A header the caller wrote before the menu existed can leave
				// nav_list empty (or "auto") — fill it in rather than reject it.
				if ($menu_id && isset($tt['type']) && $tt['type'] === 'header' && isset($tt['pagelayer_data']) && is_array($tt['pagelayer_data'])) {
					self::fill_menu_placeholder($tt['pagelayer_data'], $menu_id);
				}
				if ($single_page_site && !isset($tt['single_page_site'])) {
					$tt['single_page_site'] = true;
				}

				$res = self::execute_create_template($tt);
				if (is_wp_error($res)) {
					$failures[] = array(
						'item'  => 'theme_templates[' . $i . ']',
						'title' => isset($tt['title']) ? $tt['title'] : '',
						'error' => $res->get_error_message(),
					);
				} elseif (isset($tt['type'])) {
					$created_templates[$tt['type']] = $res;
				}
			}
		}

		// Bare Header scaffold, ONLY if the caller supplied none and none exists.
		if (!$has_template_type('header')) {
			$brand_col = array('tag' => 'pl_col', 'attrs' => array('col' => 4), 'content' => array(
				array('tag' => 'pl_heading', 'attrs' => array('color' => $primary, 'font_size' => '24px', 'font_weight' => '800'), 'content' => '<span>' . esc_html($site_name) . '</span>')
			));

			$nav_col = array('tag' => 'pl_col', 'attrs' => array('col' => 8), 'content' => array());
			if ($menu_id && self::widget_exists('pl_wp_menu')) {
				$nav_col['content'][] = array(
					'tag'   => 'pl_wp_menu',
					'attrs' => array(
						'nav_list'        => (string) $menu_id,
						'layout'          => 'horizontal',
						'align'           => 'right',
						'drop_breakpoint' => 'tablet',
						'pointer'         => 'underline',
						'm_animation'     => 'slide',
						'submenu_ind'     => 'caret-down',
					),
				);
			}

			$header_data = array(
				array(
					'tag' => 'pl_row',
					// ele_bg_color needs ele_bg_type=color to survive render, and the
					// element shadow prop is ele_shadow (there is no ele_box_shadow).
					'attrs' => array('stretch' => 'full', 'ele_bg_type' => 'color', 'ele_bg_color' => '#ffffff', 'ele_padding' => '20px,0px,20px,0px', 'ele_shadow' => '0,4,20,rgba(0,0,0,0.05),0,'),
					'content' => array($brand_col, $nav_col),
				)
			);

			$res = self::execute_create_template(array(
				'title'            => $site_name . ' Header',
				'type'             => 'header',
				'pagelayer_data'   => $header_data,
				'conditions'       => array(array('type' => 'include', 'template' => '', 'sub_template' => '', 'id' => '')),
				// The scaffold can only carry a menu widget when one is available;
				// without it this bare header would fail its own nav gate.
				'single_page_site' => ($single_page_site || !$menu_id || !self::widget_exists('pl_wp_menu')),
			));
			if (!is_wp_error($res)) {
				$created_templates['header'] = $res;
			} else {
				$failures[] = array('item' => 'header_scaffold', 'title' => $site_name . ' Header', 'error' => $res->get_error_message());
			}
		}

		$result = array(
			'success'           => empty($failures),
			'site_name'         => $site_name,
			'homepage_id'       => $homepage_id,
			'created_templates' => $created_templates,
			'menu'              => $menu,
			'pages'             => $pages_created,
			'posts'             => $posts_created,
		);

		if (!empty($failures)) {
			$result['failed']  = $failures;
			$result['message'] = sprintf(
				__('%1$d of %2$d item(s) were rejected and NOT created. Fix the issues listed in "failed" and re-submit just those items with create_page.', 'pagelayer'),
				count($failures),
				count($failures) + count($pages_created) + count($posts_created)
			);
		}

		return $result;
	}

	public static function execute_create_design_ui($input) {
		self::maybe_update_global_styles($input);
		$post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;
		if (!$post_id || !get_post($post_id)) {
			return new \WP_Error('invalid_post', __('Post not found.', 'pagelayer'));
		}

		if (!isset($input['pagelayer_data']) || !is_array($input['pagelayer_data'])) {
			return new \WP_Error('missing_pagelayer_data', __('pagelayer_data is required.', 'pagelayer'));
		}

		$existing_data = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($existing_data)) {
			$existing_data = array();
		}

		$inline_css = self::inline_css_gate($input['pagelayer_data']);
		if (is_wp_error($inline_css)) {
			return $inline_css;
		}

		$normalized_new = self::normalize_layout_data($input['pagelayer_data']);

		if (empty($input['skip_validation'])) {
			$gate = self::quality_gate($normalized_new);
			if (is_wp_error($gate)) {
				return $gate;
			}
		}

		$merged_data     = array_merge($existing_data, $normalized_new);
		$normalized_data = self::normalize_layout_data($merged_data);
		update_post_meta($post_id, 'pagelayer-data', $normalized_data);

		$blocks_content = self::serialize_layout_to_blocks($normalized_data);
		wp_update_post(array(
			'ID'           => $post_id,
			'post_content' => $blocks_content,
		));

		return array(
			'success' => true,
			'post_id' => $post_id,
			'url'     => get_permalink($post_id)
		);
	}

	public static function execute_edit_layout($input) {
		return self::execute_update_data($input);
	}

	public static function execute_update_data($input) {
		$post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;
		if (!$post_id || !get_post($post_id)) {
			return new \WP_Error('invalid_post', __('Post not found.', 'pagelayer'));
		}

		if (isset($input['pagelayer_data']) && is_array($input['pagelayer_data'])) {
			$inline_css = self::inline_css_gate($input['pagelayer_data']);
			if (is_wp_error($inline_css)) {
				return $inline_css;
			}

			$normalized_data = self::normalize_layout_data($input['pagelayer_data']);

			if (empty($input['skip_validation'])) {
				$gate = self::quality_gate($normalized_data);
				if (is_wp_error($gate)) {
					return $gate;
				}
			}

			update_post_meta($post_id, 'pagelayer-data', $normalized_data);

			$blocks_content = self::serialize_layout_to_blocks($normalized_data);
			wp_update_post(array(
				'ID'           => $post_id,
				'post_content' => $blocks_content,
			));
		}

		return array('success' => true);
	}

	public static function execute_change_styles($input) {
		$post_id = isset($input['post_id']) ? (int) $input['post_id'] : 0;
		if (!$post_id || !get_post($post_id)) {
			return new \WP_Error('invalid_post', __('Post not found.', 'pagelayer'));
		}

		$styles = isset($input['styles']) && is_array($input['styles']) ? $input['styles'] : array();
		if (empty($styles)) {
			return new \WP_Error('no_styles', __('No style changes provided.', 'pagelayer'));
		}

		// Props land straight in attrs, so they get the same inline-CSS guard.
		$inline_css_found = array();
		foreach ($styles as &$style_check) {
			if (!isset($style_check['props']) || !is_array($style_check['props'])) {
				continue;
			}
			$props_node = array('tag' => isset($style_check['selector']) ? $style_check['selector'] : '', 'attrs' => $style_check['props']);
			self::scrub_node_inline_css($props_node, $inline_css_found);
			$style_check['props'] = $props_node['attrs'];
		}
		unset($style_check);

		if (!empty($inline_css_found)) {
			return self::inline_css_error($inline_css_found);
		}

		// Selectors may be an "@0.1.2" outline path as well as a pagelayer-id
		// or a widget tag.
		foreach ($styles as &$style) {
			if (isset($style['selector']) && strpos((string)$style['selector'], '@') === 0) {
				$style['selector'] = self::resolve_element_ref($post_id, $style['selector']);
			}
		}
		unset($style);

		$data = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($data)) {
			return new \WP_Error('no_data', __('No Pagelayer data found for this post.', 'pagelayer'));
		}

		$applied = 0;
		$walk = function(&$node) use (&$walk, $styles, &$applied) {
			if (!is_array($node)) return;

			$node_id  = isset($node['attrs']['pagelayer-id']) ? $node['attrs']['pagelayer-id'] : (isset($node['id']) ? $node['id'] : '');
			$node_tag = isset($node['tag']) ? $node['tag'] : '';

			foreach ($styles as $style) {
				$selector = isset($style['selector']) ? $style['selector'] : '';
				$props    = isset($style['props']) && is_array($style['props']) ? $style['props'] : array();

				$match          = false;
				$clean_selector = str_replace(array('pagelayer_', 'pl_'), '', ltrim($selector, '.'));
				$clean_tag      = str_replace(array('pagelayer_', 'pl_'), '', $node_tag);
				$clean_id       = str_replace(array('pagelayer_', 'pl_'), '', $node_id);

				if (!empty($node_id) && ($selector === $node_id || $clean_selector === $clean_id)) {
					$match = true;
				} elseif (!empty($node_tag) && ($selector === $node_tag || $selector === ('.' . $node_tag) || $clean_selector === $clean_tag)) {
					$match = true;
				}

				if ($match && !empty($props)) {
					if (!isset($node['attrs']) || !is_array($node['attrs'])) {
						$node['attrs'] = array();
					}
					$node['attrs'] = array_merge($node['attrs'], $props);
					$applied++;
				}
			}

			if (isset($node['content']) && is_array($node['content'])) {
				foreach ($node['content'] as &$child) {
					$walk($child);
				}
				unset($child);
			}
		};

		foreach ($data as &$section) {
			$walk($section);
		}
		unset($section);

		$normalized_data = self::normalize_layout_data($data);
		update_post_meta($post_id, 'pagelayer-data', $normalized_data);

		$blocks_content = self::serialize_layout_to_blocks($normalized_data);
		wp_update_post(array(
			'ID'           => $post_id,
			'post_content' => $blocks_content,
		));

		return array('success' => true, 'changes_applied' => $applied);
	}

	/**
	 * The full guide is ~2.6k tokens, and most of it (the section-variety and
	 * widget-recommendation guidance) only matters when BUILDING a page. An
	 * edit to existing content needs the node format and the dependent-attribute
	 * rule and nothing else, so those are what the default topic returns.
	 */
	public static function execute_get_data_structure($input) {
		$doc   = self::get_data_structure_doc();
		$topic = isset($input['topic']) ? sanitize_text_field($input['topic']) : 'core';

		if ($topic === 'all') {
			return $doc;
		}

		if ($topic === 'quality') {
			$quality = $doc['content_quality_rules'];
			unset($quality['widget_recommendations']);
			return array(
				'content_quality_rules'      => $quality,
				'styling_never_inline'       => $doc['styling_never_inline'],
				'site_navigation'            => $doc['site_navigation'],
				'theme_template_conditions'  => $doc['theme_template_conditions'],
			);
		}

		if ($topic === 'navigation' || $topic === 'menus') {
			return array(
				'site_navigation'           => $doc['site_navigation'],
				'theme_template_conditions' => $doc['theme_template_conditions'],
			);
		}

		if ($topic === 'widgets') {
			return array('widget_recommendations' => $doc['content_quality_rules']['widget_recommendations']);
		}

		if ($topic === 'workflow') {
			return array('design_workflow' => $doc['design_workflow']);
		}

		return array(
			'description'              => $doc['description'],
			'fast_path_sections'       => $doc['fast_path_sections'],
			'node_format'              => $doc['node_format'],
			'styling_never_inline'     => $doc['styling_never_inline'],
			'hierarchy_rules'          => $doc['hierarchy_rules'],
			'global_reference_syntax'  => $doc['global_reference_syntax'],
			'dependent_attributes'     => $doc['dependent_attributes'],
			'responsive_properties'    => $doc['responsive_properties'],
			'more_topics'              => 'Editing existing content needs nothing beyond this. When BUILDING a page also read topic:"quality" (the enforced content gate), topic:"widgets" (which widget to use for which section), topic:"navigation" (header menus and header/footer display conditions — both enforced), topic:"workflow", or topic:"all".',
		);
	}

	public static function execute_find_elements($input) {
		$post_id = (int) $input['post_id'];
		$data    = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($data)) {
			return array('elements' => array());
		}

		$results      = array();
		$target_tag   = isset($input['tag']) ? sanitize_text_field($input['tag']) : '';
		$search_query = isset($input['query']) ? strtolower($input['query']) : '';
		$with_attrs   = !empty($input['include_attrs']);

		// A text query used to be tested against node["content"] only, so it
		// silently missed every widget that keeps its copy in attrs instead
		// (pl_iconbox, pl_testimonial, pl_btn...). It now matches the same
		// preview text the outline shows.
		$search_node = function($node) use (&$results, &$search_node, $target_tag, $search_query, $with_attrs) {
			if (!is_array($node) || empty($node['tag'])) {
				return;
			}

			$match   = true;
			$preview = self::node_preview($node, 120);

			if ($target_tag && $node['tag'] !== $target_tag) {
				$match = false;
			}
			if ($search_query && strpos(strtolower($preview), $search_query) === false) {
				$match = false;
			}

			if ($match) {
				$id  = isset($node['attrs']['pagelayer-id']) ? $node['attrs']['pagelayer-id'] : '';
				if ($with_attrs) {
					// Opt-in: every style attr on the node, which is most of what
					// made this call expensive when it was the default.
					$results[] = array(
						'id'      => $id,
						'tag'     => $node['tag'],
						'attrs'   => isset($node['attrs']) ? $node['attrs'] : array(),
						'content' => isset($node['content']) && is_string($node['content']) ? $node['content'] : '',
					);
				} else {
					$results[] = trim($id . ' ' . $node['tag'] . ($preview !== '' ? ' "' . $preview . '"' : ''));
				}
			}

			if (isset($node['content']) && is_array($node['content'])) {
				foreach ($node['content'] as $child) {
					$search_node($child);
				}
			}
		};

		foreach ($data as $section) {
			$search_node($section);
		}

		$out = array('elements' => $results, 'count' => count($results));
		if (!$with_attrs) {
			$out['legend'] = '"<ref> <tag> \"text\"", where <ref> is a pagelayer-id or an "@0.1.2" position path — both usable as element_id. Pass include_attrs:true for full attrs, or read one node via get_page with element_id.';
		}
		return $out;
	}

	// ------------------------------------------------------------------
	// Page outline
	//
	// The old navigator returned ids and tags but no text, so it could not
	// answer "which node is the hero heading?" — the model had to pull the
	// whole pagelayer_data tree (33KB on a real page) just to find one
	// element's id. The outline carries a text preview per node, which is what
	// makes a targeted edit possible without ever reading the full tree.
	// ------------------------------------------------------------------

	/**
	 * Text-bearing attribute names for a widget, from its own schema.
	 */
	protected static function text_attrs_for($tag) {
		global $pagelayer;
		static $cache = array();

		if (isset($cache[$tag])) {
			return $cache[$tag];
		}

		self::ensure_shortcodes_loaded();
		if (empty($pagelayer->shortcodes[$tag])) {
			return $cache[$tag] = array();
		}

		$data   = $pagelayer->shortcodes[$tag];
		$schema = self::extract_widget_schema($tag, $data);
		$own    = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : array();
		$keys   = array();

		foreach ($schema['sections'] as $key => $section) {
			if (!isset($own[$key])) {
				continue;
			}
			foreach ($section['properties'] as $prop_key => $prop) {
				if (in_array(isset($prop['type']) ? $prop['type'] : '', array('text', 'textarea', 'editor'), true)) {
					$keys[] = $prop_key;
				}
			}
		}

		return $cache[$tag] = $keys;
	}

	/**
	 * Short human-readable preview of what a node actually says on the page.
	 */
	protected static function node_preview($node, $limit = 70) {
		$tag   = isset($node['tag']) ? $node['tag'] : '';
		$attrs = isset($node['attrs']) && is_array($node['attrs']) ? $node['attrs'] : array();
		$text  = '';

		if (isset($node['content']) && is_string($node['content'])) {
			$text = $node['content'];
		}

		if ($text === '') {
			foreach (self::text_attrs_for($tag) as $key) {
				if (!empty($attrs[$key]) && is_string($attrs[$key])) {
					$text = $attrs[$key];
					break;
				}
			}
		}

		$text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string)$text)));
		if ($text === '') {
			return '';
		}
		if (function_exists('mb_strlen') && mb_strlen($text) > $limit) {
			return mb_substr($text, 0, $limit) . '…';
		}
		if (strlen($text) > $limit) {
			return substr($text, 0, $limit) . '…';
		}
		return $text;
	}

	/**
	 * Flat, indentation-encoded outline. Indentation carries the hierarchy, so
	 * there is no nested-object scaffolding to pay for:
	 *   "  a8fn2 pl_heading \"Luxury Car Detailing\""
	 */
	public static function outline_nodes($nodes, $depth = 0, &$lines = array(), $path = '') {
		$index = -1;
		foreach ($nodes as $node) {
			$index++;
			if (!is_array($node) || empty($node['tag'])) {
				continue;
			}

			$here = ($path === '' ? '' : $path . '.') . $index;

			// Pages built in the editor, imported from a template, or written by
			// older versions carry no pagelayer-id at all, which used to make
			// every element tool unusable on them. Fall back to the positional
			// path, which every element ability also accepts.
			$id   = !empty($node['attrs']['pagelayer-id']) ? $node['attrs']['pagelayer-id'] : '@' . $here;
			$line = str_repeat('  ', $depth) . $id . ' ' . $node['tag'];

			// Column widths are structural — the model needs them to reason
			// about layout without reading attrs.
			if ($node['tag'] === 'pl_col' && isset($node['attrs']['col'])) {
				$line .= ' col=' . $node['attrs']['col'];
			}

			$preview = self::node_preview($node);
			if ($preview !== '') {
				$line .= ' "' . $preview . '"';
			}

			$lines[] = $line;

			if (isset($node['content']) && is_array($node['content'])) {
				self::outline_nodes($node['content'], $depth + 1, $lines, $here);
			}
		}
		return $lines;
	}

	/**
	 * Locate one node by pagelayer-id, or by "@0.1.2" positional path.
	 */
	public static function find_node_by_id($nodes, $element_id) {
		if (strpos($element_id, '@') === 0) {
			$level = $nodes;
			$node  = null;
			foreach (explode('.', substr($element_id, 1)) as $step) {
				$step = (int)$step;
				if (!isset($level[$step]) || !is_array($level[$step])) {
					return null;
				}
				$node  = $level[$step];
				$level = (isset($node['content']) && is_array($node['content'])) ? $node['content'] : array();
			}
			return $node;
		}

		foreach ($nodes as $node) {
			if (!is_array($node)) {
				continue;
			}
			if (isset($node['attrs']['pagelayer-id']) && $node['attrs']['pagelayer-id'] === $element_id) {
				return $node;
			}
			if (isset($node['content']) && is_array($node['content'])) {
				$found = self::find_node_by_id($node['content'], $element_id);
				if ($found !== null) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Turn an element reference into a real pagelayer-id.
	 *
	 * A "@0.1.2" path refers to a node that has no id yet; give it a permanent
	 * one and persist it, so this and every later call can address it by id.
	 * Anything else is already an id and passes through untouched.
	 */
	public static function resolve_element_ref($post_id, $ref) {
		$ref = trim((string)$ref);
		if ($ref === '' || strpos($ref, '@') !== 0) {
			return $ref;
		}

		$data = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($data)) {
			return $ref;
		}

		$steps = array_map('intval', explode('.', substr($ref, 1)));
		$id    = null;

		// Walk by reference so the assigned id is written back into $data.
		$cursor = &$data;
		$last   = count($steps) - 1;
		foreach ($steps as $i => $step) {
			if (!isset($cursor[$step]) || !is_array($cursor[$step])) {
				return $ref;
			}
			if ($i === $last) {
				if (empty($cursor[$step]['attrs']['pagelayer-id'])) {
					if (!function_exists('pagelayer_create_id')) {
						return $ref;
					}
					$cursor[$step]['attrs']['pagelayer-id'] = pagelayer_create_id();
					update_post_meta($post_id, 'pagelayer-data', $data);
				}
				$id = $cursor[$step]['attrs']['pagelayer-id'];
				break;
			}
			if (!isset($cursor[$step]['content']) || !is_array($cursor[$step]['content'])) {
				return $ref;
			}
			$cursor = &$cursor[$step]['content'];
		}
		unset($cursor);

		return $id === null ? $ref : $id;
	}

	public static function execute_navigator($input) {
		$post_id = (int) $input['post_id'];
		$data    = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($data)) {
			return array('outline' => array());
		}

		$lines = array();
		return array(
			'outline' => self::outline_nodes($data, 0, $lines),
			'legend'  => 'One line per node: [indent = nesting depth] <ref> <tag> [col=N] "text preview". <ref> is the node\'s pagelayer-id, or an "@0.1.2" position path when it has none yet. Pass either to update_element/delete_element/move_element, or to get_page as element_id to read that node in full.',
		);
	}

	public static function execute_update_element($input) {
		$post_id    = (int) $input['post_id'];
		$element_id = self::resolve_element_ref($post_id, sanitize_text_field($input['element_id']));
		$data       = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($data)) {
			return new \WP_Error('no_data', __('Page has no Pagelayer data.', 'pagelayer'));
		}

		// Guard what the client sent, not what is already on the node — the
		// builder's own editor writes inline styles that are not ours to strip.
		$in_attrs   = isset($input['attrs']) && is_array($input['attrs']) ? $input['attrs'] : null;
		$in_content = isset($input['content']) && is_string($input['content']) ? $input['content'] : null;
		if ($in_attrs !== null || $in_content !== null) {
			$check_node = array('tag' => '', 'attrs' => $in_attrs !== null ? $in_attrs : array());
			if ($in_content !== null) {
				$check_node['content'] = $in_content;
			}

			$inline_css_found = array();
			self::scrub_node_inline_css($check_node, $inline_css_found);

			if ($in_attrs !== null) {
				$input['attrs'] = $check_node['attrs'];
			}
			if ($in_content !== null) {
				$input['content'] = $check_node['content'];
			}

			if (!empty($inline_css_found)) {
				return self::inline_css_error($inline_css_found);
			}
		}

		$updated      = false;
		$matched_node = null;
		$update_node  = function(&$node) use ($element_id, $input, &$updated, &$update_node, &$matched_node) {
			if (!is_array($node)) return;
			if (isset($node['attrs']['pagelayer-id']) && $node['attrs']['pagelayer-id'] === $element_id) {
				if (isset($input['attrs']) && is_array($input['attrs'])) {
					$node['attrs'] = array_merge($node['attrs'], $input['attrs']);
				}
				if (isset($input['content'])) {
					$node['content'] = $input['content'];
				}
				$updated      = true;
				$matched_node = $node;
				return;
			}
			if (isset($node['content']) && is_array($node['content'])) {
				foreach ($node['content'] as &$child) {
					$update_node($child);
				}
				unset($child);
			}
		};

		foreach ($data as &$section) {
			$update_node($section);
		}
		unset($section);

		if (!$updated) {
			return new \WP_Error('not_found', sprintf(__('Element with ID %s not found.', 'pagelayer'), $element_id));
		}

		if (empty($input['skip_validation']) && is_array($matched_node)) {
			$gate = self::quality_gate(array($matched_node));
			if (is_wp_error($gate)) {
				return $gate;
			}
		}

		$normalized = self::normalize_layout_data($data);
		update_post_meta($post_id, 'pagelayer-data', $normalized);
		$blocks_content = self::serialize_layout_to_blocks($normalized);
		wp_update_post(array('ID' => $post_id, 'post_content' => $blocks_content));
		return array('success' => true);
	}

	public static function execute_add_element($input) {
		$post_id   = (int) $input['post_id'];
		$parent_id = isset($input['parent_id']) ? self::resolve_element_ref($post_id, sanitize_text_field($input['parent_id'])) : '';

		$inline_css_found = array();
		self::scrub_node_inline_css($input['element'], $inline_css_found);
		if (!empty($inline_css_found)) {
			return self::inline_css_error($inline_css_found);
		}

		$element = self::normalize_node($input['element']);

		if (empty($input['skip_validation'])) {
			$gate = self::quality_gate(array($element));
			if (is_wp_error($gate)) {
				return $gate;
			}
		}

		$data = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($data)) {
			$data = array();
		}

		$inserted = false;
		$index    = isset($input['index']) ? (int) $input['index'] : -1;

		if (empty($parent_id)) {
			if ($index >= 0 && $index < count($data)) {
				array_splice($data, $index, 0, array($element));
			} else {
				$data[] = $element;
			}
			$inserted = true;
		} else {
			$insert_node = function(&$node) use ($parent_id, $element, $index, &$inserted, &$insert_node) {
				if (!is_array($node)) return;
				if (isset($node['attrs']['pagelayer-id']) && $node['attrs']['pagelayer-id'] === $parent_id) {
					if (!isset($node['content']) || !is_array($node['content'])) {
						$node['content'] = array();
					}
					if ($index >= 0 && $index < count($node['content'])) {
						array_splice($node['content'], $index, 0, array($element));
					} else {
						$node['content'][] = $element;
					}
					$inserted = true;
					return;
				}
				if (isset($node['content']) && is_array($node['content'])) {
					foreach ($node['content'] as &$child) {
						$insert_node($child);
					}
					unset($child);
				}
			};

			foreach ($data as &$section) {
				$insert_node($section);
			}
			unset($section);
		}

		if ($inserted) {
			$normalized = self::normalize_layout_data($data);
			update_post_meta($post_id, 'pagelayer-data', $normalized);
			$blocks_content = self::serialize_layout_to_blocks($normalized);
			wp_update_post(array('ID' => $post_id, 'post_content' => $blocks_content));
			return array('success' => true, 'element_id' => isset($element['attrs']['pagelayer-id']) ? $element['attrs']['pagelayer-id'] : '');
		}
		return new \WP_Error('parent_not_found', __('Parent element not found.', 'pagelayer'));
	}

	public static function execute_delete_element($input) {
		$post_id    = (int) $input['post_id'];
		$element_id = self::resolve_element_ref($post_id, sanitize_text_field($input['element_id']));
		$data       = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($data)) {
			return new \WP_Error('no_data', __('Page has no Pagelayer data.', 'pagelayer'));
		}

		$deleted = false;
		foreach ($data as $idx => $node) {
			if (isset($node['attrs']['pagelayer-id']) && $node['attrs']['pagelayer-id'] === $element_id) {
				array_splice($data, $idx, 1);
				$deleted = true;
				break;
			}
		}

		if (!$deleted) {
			$delete_node = function(&$node) use ($element_id, &$deleted, &$delete_node) {
				if (!is_array($node)) return;
				if (isset($node['content']) && is_array($node['content'])) {
					foreach ($node['content'] as $idx => $child) {
						if (isset($child['attrs']['pagelayer-id']) && $child['attrs']['pagelayer-id'] === $element_id) {
							array_splice($node['content'], $idx, 1);
							$deleted = true;
							return;
						}
					}
					foreach ($node['content'] as &$child) {
						$delete_node($child);
					}
					unset($child);
				}
			};

			foreach ($data as &$section) {
				if ($deleted) break;
				$delete_node($section);
			}
			unset($section);
		}

		if ($deleted) {
			$normalized = self::normalize_layout_data($data);
			update_post_meta($post_id, 'pagelayer-data', $normalized);
			$blocks_content = self::serialize_layout_to_blocks($normalized);
			wp_update_post(array('ID' => $post_id, 'post_content' => $blocks_content));
			return array('success' => true);
		}
		return new \WP_Error('not_found', __('Element not found.', 'pagelayer'));
	}

	public static function execute_move_element($input) {
		$post_id    = (int) $input['post_id'];
		$element_id = self::resolve_element_ref($post_id, sanitize_text_field($input['element_id']));
		$parent_id  = isset($input['parent_id']) ? self::resolve_element_ref($post_id, sanitize_text_field($input['parent_id'])) : '';
		$index      = isset($input['index']) ? (int) $input['index'] : -1;

		$data = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($data)) {
			return new \WP_Error('no_data', __('Page has no Pagelayer data.', 'pagelayer'));
		}

		$extracted = null;
		foreach ($data as $idx => $node) {
			if (isset($node['attrs']['pagelayer-id']) && $node['attrs']['pagelayer-id'] === $element_id) {
				$extracted = $node;
				array_splice($data, $idx, 1);
				break;
			}
		}

		if (!$extracted) {
			$extract_node = function(&$node) use ($element_id, &$extracted, &$extract_node) {
				if (!is_array($node)) return;
				if (isset($node['content']) && is_array($node['content'])) {
					foreach ($node['content'] as $idx => $child) {
						if (isset($child['attrs']['pagelayer-id']) && $child['attrs']['pagelayer-id'] === $element_id) {
							$extracted = $child;
							array_splice($node['content'], $idx, 1);
							return;
						}
					}
					foreach ($node['content'] as &$child) {
						if ($extracted) return;
						$extract_node($child);
					}
					unset($child);
				}
			};
			foreach ($data as &$section) {
				if ($extracted) break;
				$extract_node($section);
			}
			unset($section);
		}

		if (!$extracted) {
			return new \WP_Error('not_found', __('Element to move not found.', 'pagelayer'));
		}

		$inserted = false;
		if (empty($parent_id)) {
			if ($index >= 0 && $index < count($data)) {
				array_splice($data, $index, 0, array($extracted));
			} else {
				$data[] = $extracted;
			}
			$inserted = true;
		} else {
			$insert_node = function(&$node) use ($parent_id, $extracted, $index, &$inserted, &$insert_node) {
				if (!is_array($node)) return;
				if (isset($node['attrs']['pagelayer-id']) && $node['attrs']['pagelayer-id'] === $parent_id) {
					if (!isset($node['content']) || !is_array($node['content'])) {
						$node['content'] = array();
					}
					if ($index >= 0 && $index < count($node['content'])) {
						array_splice($node['content'], $index, 0, array($extracted));
					} else {
						$node['content'][] = $extracted;
					}
					$inserted = true;
					return;
				}
				if (isset($node['content']) && is_array($node['content'])) {
					foreach ($node['content'] as &$child) {
						if ($inserted) return;
						$insert_node($child);
					}
					unset($child);
				}
			};
			foreach ($data as &$section) {
				if ($inserted) break;
				$insert_node($section);
			}
			unset($section);
		}

		if ($inserted) {
			$normalized = self::normalize_layout_data($data);
			update_post_meta($post_id, 'pagelayer-data', $normalized);
			$blocks_content = self::serialize_layout_to_blocks($normalized);
			wp_update_post(array('ID' => $post_id, 'post_content' => $blocks_content));
			return array('success' => true);
		}
		return new \WP_Error('parent_not_found', __('Target parent not found.', 'pagelayer'));
	}

	public static function execute_duplicate_element($input) {
		$post_id    = (int) $input['post_id'];
		$element_id = self::resolve_element_ref($post_id, sanitize_text_field($input['element_id']));
		$data       = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($data)) {
			return new \WP_Error('no_data', __('Page has no Pagelayer data.', 'pagelayer'));
		}

		$refresh_ids = function(&$node) use (&$refresh_ids) {
			if (!is_array($node)) return;
			if (isset($node['attrs']['pagelayer-id']) && function_exists('pagelayer_create_id')) {
				$node['attrs']['pagelayer-id'] = pagelayer_create_id();
			}
			if (isset($node['content']) && is_array($node['content'])) {
				foreach ($node['content'] as &$child) {
					$refresh_ids($child);
				}
				unset($child);
			}
		};

		$duplicated = false;
		$new_id     = '';

		foreach ($data as $idx => $node) {
			if (isset($node['attrs']['pagelayer-id']) && $node['attrs']['pagelayer-id'] === $element_id) {
				$copy = $node;
				$refresh_ids($copy);
				$new_id = isset($copy['attrs']['pagelayer-id']) ? $copy['attrs']['pagelayer-id'] : '';
				array_splice($data, $idx + 1, 0, array($copy));
				$duplicated = true;
				break;
			}
		}

		if (!$duplicated) {
			$duplicate_node = function(&$node) use ($element_id, $refresh_ids, &$duplicated, &$new_id, &$duplicate_node) {
				if (!is_array($node)) return;
				if (isset($node['content']) && is_array($node['content'])) {
					foreach ($node['content'] as $idx => $child) {
						if (isset($child['attrs']['pagelayer-id']) && $child['attrs']['pagelayer-id'] === $element_id) {
							$copy = $child;
							$refresh_ids($copy);
							$new_id = isset($copy['attrs']['pagelayer-id']) ? $copy['attrs']['pagelayer-id'] : '';
							array_splice($node['content'], $idx + 1, 0, array($copy));
							$duplicated = true;
							return;
						}
					}
					foreach ($node['content'] as &$child) {
						if ($duplicated) return;
						$duplicate_node($child);
					}
					unset($child);
				}
			};
			foreach ($data as &$section) {
				if ($duplicated) break;
				$duplicate_node($section);
			}
			unset($section);
		}

		if ($duplicated) {
			$normalized = self::normalize_layout_data($data);
			update_post_meta($post_id, 'pagelayer-data', $normalized);
			$blocks_content = self::serialize_layout_to_blocks($normalized);
			wp_update_post(array('ID' => $post_id, 'post_content' => $blocks_content));
			return array('success' => true, 'new_element_id' => $new_id);
		}
		return new \WP_Error('not_found', __('Element to duplicate not found.', 'pagelayer'));
	}

	public static function execute_begin_transaction($input) {
		$post_id = (int) $input['post_id'];
		$data    = get_post_meta($post_id, 'pagelayer-data', true);
		$post    = get_post($post_id);
		if (!$post) {
			return new \WP_Error('invalid_post', __('Post not found.', 'pagelayer'));
		}

		$backup = array(
			'data'    => $data,
			'content' => $post->post_content
		);
		update_option('pagelayer_tx_backup_' . $post_id, $backup);
		return array('success' => true);
	}

	public static function execute_commit_transaction($input) {
		$post_id = (int) $input['post_id'];
		delete_option('pagelayer_tx_backup_' . $post_id);
		return array('success' => true);
	}

	public static function execute_rollback_transaction($input) {
		$post_id = (int) $input['post_id'];
		$backup  = get_option('pagelayer_tx_backup_' . $post_id);
		if (!$backup) {
			return new \WP_Error('no_backup', __('No active transaction to rollback.', 'pagelayer'));
		}

		update_post_meta($post_id, 'pagelayer-data', $backup['data']);
		wp_update_post(array(
			'ID'           => $post_id,
			'post_content' => $backup['content']
		));
		delete_option('pagelayer_tx_backup_' . $post_id);
		return array('success' => true);
	}

	public static function execute_save_template($input) {
		$template_name = sanitize_text_field($input['name']);
		$post_id       = (int) $input['post_id'];
		$element_id    = isset($input['element_id']) ? self::resolve_element_ref($post_id, sanitize_text_field($input['element_id'])) : '';

		$data = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($data)) {
			return new \WP_Error('no_data', __('No layout data to save.', 'pagelayer'));
		}

		$template_data = $data;
		if (!empty($element_id)) {
			$found = null;
			$find_node = function($node) use ($element_id, &$found, &$find_node) {
				if (!is_array($node)) return;
				if (isset($node['attrs']['pagelayer-id']) && $node['attrs']['pagelayer-id'] === $element_id) {
					$found = $node;
					return;
				}
				if (isset($node['content']) && is_array($node['content'])) {
					foreach ($node['content'] as $child) {
						$find_node($child);
					}
				}
			};
			foreach ($data as $section) {
				$find_node($section);
			}
			if (!$found) {
				return new \WP_Error('not_found', __('Element to save not found.', 'pagelayer'));
			}
			$template_data = array($found);
		}

		$library = get_option('pagelayer_template_library', array());
		$library[$template_name] = $template_data;
		update_option('pagelayer_template_library', $library);
		return array('success' => true);
	}

	public static function execute_list_templates($input) {
		$library = get_option('pagelayer_template_library', array());
		return array('templates' => array_keys($library));
	}

	public static function execute_insert_template($input) {
		$template_name = sanitize_text_field($input['name']);
		$post_id       = (int) $input['post_id'];
		$parent_id     = isset($input['parent_id']) ? sanitize_text_field($input['parent_id']) : '';
		$index         = isset($input['index']) ? (int) $input['index'] : -1;

		$library = get_option('pagelayer_template_library', array());
		if (!isset($library[$template_name])) {
			return new \WP_Error('not_found', __('Template not found.', 'pagelayer'));
		}

		$template_data = $library[$template_name];
		$refresh_ids   = function(&$node) use (&$refresh_ids) {
			if (!is_array($node)) return;
			if (isset($node['attrs']['pagelayer-id']) && function_exists('pagelayer_create_id')) {
				$node['attrs']['pagelayer-id'] = pagelayer_create_id();
			}
			if (isset($node['content']) && is_array($node['content'])) {
				foreach ($node['content'] as &$child) {
					$refresh_ids($child);
				}
				unset($child);
			}
		};
		foreach ($template_data as &$node) {
			$refresh_ids($node);
		}
		unset($node);

		$data = get_post_meta($post_id, 'pagelayer-data', true);
		if (!is_array($data)) {
			$data = array();
		}

		$inserted = false;
		if (empty($parent_id)) {
			if ($index >= 0 && $index < count($data)) {
				array_splice($data, $index, 0, $template_data);
			} else {
				$data = array_merge($data, $template_data);
			}
			$inserted = true;
		} else {
			$insert_node = function(&$node) use ($parent_id, $template_data, $index, &$inserted, &$insert_node) {
				if (!is_array($node)) return;
				if (isset($node['attrs']['pagelayer-id']) && $node['attrs']['pagelayer-id'] === $parent_id) {
					if (!isset($node['content']) || !is_array($node['content'])) {
						$node['content'] = array();
					}
					if ($index >= 0 && $index < count($node['content'])) {
						array_splice($node['content'], $index, 0, $template_data);
					} else {
						$node['content'] = array_merge($node['content'], $template_data);
					}
					$inserted = true;
					return;
				}
				if (isset($node['content']) && is_array($node['content'])) {
					foreach ($node['content'] as &$child) {
						$insert_node($child);
					}
					unset($child);
				}
			};
			foreach ($data as &$section) {
				$insert_node($section);
			}
			unset($section);
		}

		if ($inserted) {
			$normalized = self::normalize_layout_data($data);
			update_post_meta($post_id, 'pagelayer-data', $normalized);
			$blocks_content = self::serialize_layout_to_blocks($normalized);
			wp_update_post(array('ID' => $post_id, 'post_content' => $blocks_content));
			return array('success' => true);
		}
		return new \WP_Error('parent_not_found', __('Parent not found.', 'pagelayer'));
	}

	public static function execute_upload_media($input) {
		$url  = esc_url_raw($input['url']);
		$desc = isset($input['alt_text']) ? sanitize_text_field($input['alt_text']) : '';

		require_once(ABSPATH . 'wp-admin/includes/image.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/media.php');

		$tmp = download_url($url);
		if (is_wp_error($tmp)) {
			return $tmp;
		}

		$file_array = array(
			'name'     => basename($url),
			'tmp_name' => $tmp
		);

		if (strpos($file_array['name'], '.') === false) {
			$file_array['name'] .= '.jpg';
		}

		$id = media_handle_sideload($file_array, 0, $desc);
		if (is_wp_error($id)) {
			@unlink($tmp);
			return $id;
		}

		return array(
			'attachment_id' => $id,
			'url'           => wp_get_attachment_url($id)
		);
	}

	public static function execute_get_preview($input) {
		$post_id = (int) $input['post_id'];
		$post    = get_post($post_id);
		if (!$post) {
			return new \WP_Error('invalid_post', __('Post not found.', 'pagelayer'));
		}
		$url = ('publish' === $post->post_status)
			? get_permalink($post_id)
			: get_preview_post_link($post_id);
		return array('url' => $url);
	}

}