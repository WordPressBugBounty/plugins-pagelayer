<?php

//////////////////////////////////////////////////////////////
//===========================================================
// ai-layout-engine.php
//===========================================================
// PAGELAYER
// Build-with-AI: live widget catalog + schemas for the model.
// The AI invents structure, copy, and visuals — this file does not
// ship canned layouts. Independent of the MCP abilities register.
//===========================================================
//////////////////////////////////////////////////////////////

if (!defined('ABSPATH')) {
	exit;
}

class Pagelayer_AI_Layout_Engine {

	private static $instance = null;

	const SCHEMA_CACHE_TTL = 21600;
	const MAX_SCHEMA_WIDGETS = 64;

	private static $skip_tags = array(
		'pl_missing',
		'pl_customizer',
		'pl_wp_widgets',
		'pl_block',
		'pl_post_props',
	);

	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function ensure_shortcodes() {
		if (function_exists('pagelayer_load_shortcodes')) {
			pagelayer_load_shortcodes();
		}
	}

	private function include_pro_settings() {
		$default = defined('PAGELAYER_PRO_VERSION');
		return (bool) apply_filters('pagelayer_ai_include_pro_settings', $default);
	}

	/**
	 * Full widget schema from the live shortcode registry.
	 * Used only by Build-with-AI — not the MCP abilities layer.
	 */
	private function extract_widget_schema($tag, $data) {
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
				if (isset($prop_def['min'])) {
					$clean_prop['min'] = $prop_def['min'];
				}
				if (isset($prop_def['max'])) {
					$clean_prop['max'] = $prop_def['max'];
				}
				if (isset($prop_def['step'])) {
					$clean_prop['step'] = $prop_def['step'];
				}
				if (isset($prop_def['units'])) {
					$clean_prop['units'] = $prop_def['units'];
				}
				if (isset($prop_def['screen'])) {
					$clean_prop['responsive'] = (bool) $prop_def['screen'];
				}
				if (isset($prop_def['req'])) {
					$clean_prop['requires'] = $prop_def['req'];
				}
				if (isset($prop_def['show'])) {
					$clean_prop['show_when'] = $prop_def['show'];
				}
				if (isset($prop_def['edit'])) {
					$clean_prop['edit_selector'] = $prop_def['edit'];
				}
				if (isset($prop_def['desc'])) {
					$clean_prop['desc'] = $prop_def['desc'];
				}
				if (!empty($prop_def['pro'])) {
					$clean_prop['pro'] = 1;
				}

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

	private function compact_prop($prop) {
		$type  = !empty($prop['type']) ? $prop['type'] : 'text';
		$parts = array($type);

		if (!empty($prop['label']) && is_string($prop['label'])) {
			$lbl = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($prop['label'])));
			if ($lbl !== '') {
				if (function_exists('mb_substr')) {
					$lbl = mb_substr($lbl, 0, 40);
				} else {
					$lbl = substr($lbl, 0, 40);
				}
				$parts[] = 'lbl:' . str_replace(array('|', ':'), array('/', '-'), $lbl);
			}
		}

		if (isset($prop['default']) && $prop['default'] !== '' && $prop['default'] !== null) {
			$def = is_array($prop['default']) ? wp_json_encode($prop['default']) : (string) $prop['default'];
			if (strlen($def) > 40) {
				$def = substr($def, 0, 40) . '…';
			}
			$parts[] = 'def:' . $def;
		}

		if (!empty($prop['allowed_values']) && is_array($prop['allowed_values'])) {
			$vals = array_values(array_filter(array_keys($prop['allowed_values']), 'strlen'));
			if (empty($vals) || $vals === range(0, count($prop['allowed_values']) - 1)) {
				$vals = array_values($prop['allowed_values']);
			}
			$vals = array_map(function ($v) {
				return is_scalar($v) ? (string) $v : '';
			}, $vals);
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

		if ($type === 'typography') {
			$parts[] = 'fields:family,size,style,weight,variant,deco-line,deco-style,line-height,transform,letter,word';
		} elseif ($type === 'image') {
			$parts[] = 'val:url-or-id';
		} elseif ($type === 'padding') {
			$parts[] = 'val:t,r,b,l+unit';
		} elseif ($type === 'icon') {
			$parts[] = 'val:fa5-class';
		}

		return implode('|', $parts);
	}

	private function own_section_keys($tag) {
		global $pagelayer;
		$this->ensure_shortcodes();
		$data = isset($pagelayer->shortcodes[$tag]) ? $pagelayer->shortcodes[$tag] : array();
		return isset($data['settings']) && is_array($data['settings']) ? array_keys($data['settings']) : array();
	}

	private function compact_widget_schema($schema, $mode = 'own', $only = array()) {
		$tag = $schema['id'];
		$own = $this->own_section_keys($tag);
		$out = array(
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
			$out['content_attr'] = $schema['innerHTML'];
			$out['content_note'] = 'Main text goes in the node "content" field, not as attrs[' . $schema['innerHTML'] . '].';
		}
		if (!empty($schema['html']) && is_string($schema['html'])) {
			preg_match_all('/\{\{\{?([a-zA-Z0-9_\-]+)\}?\}\}/', $schema['html'], $markup_m);
			if (!empty($markup_m[1])) {
				$renders = array();
				foreach (array_unique($markup_m[1]) as $name) {
					if (strpos($name, 'pagelayer') === 0 || strpos($name, 'func_') === 0) {
						continue;
					}
					$renders[] = $name;
				}
				if (!empty($renders)) {
					$out['renders'] = array_values($renders);
				}
			}
		}
		if (!empty($schema['skip_props'])) {
			$out['unsupported_props'] = $schema['skip_props'];
		}

		$sections    = array();
		$include_pro = $this->include_pro_settings();
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
				if ($mode === 'own' && substr($prop_key, -6) === '_hover') {
					$base = substr($prop_key, 0, -6);
					if ($base !== '' && isset($section['properties'][$base])) {
						continue;
					}
				}
				if (!$include_pro && !empty($prop['pro'])) {
					continue;
				}
				$props[$prop_key] = $this->compact_prop($prop);
			}
			if (empty($props)) {
				continue;
			}
			$sections[$key] = $props;
		}

		$out['props'] = $sections;
		return $out;
	}

	private function widget_attr_rules($tag) {
		global $pagelayer;
		static $cache = array();

		$lookup = str_replace(array('pl_inner_row', 'pl_inner_col'), array('pl_row', 'pl_col'), $tag);
		if (array_key_exists($lookup, $cache)) {
			return $cache[$lookup];
		}

		$this->ensure_shortcodes();
		if (empty($pagelayer->shortcodes[$lookup])) {
			return $cache[$lookup] = null;
		}

		$schema = $this->extract_widget_schema($lookup, $pagelayer->shortcodes[$lookup]);
		$rules  = array('allowed' => array(), 'req' => array());

		foreach ($schema['sections'] as $section) {
			foreach ($section['properties'] as $key => $prop) {
				$rules['allowed'][$key] = isset($prop['type']) ? $prop['type'] : '';
				if (!empty($prop['requires']) && is_array($prop['requires'])) {
					$rules['req'][$key] = $prop['requires'];
				}
				if (!empty($prop['responsive'])) {
					$rules['allowed'][$key . '_tablet'] = $rules['allowed'][$key];
					$rules['allowed'][$key . '_mobile'] = $rules['allowed'][$key];
				}
			}
		}

		return $cache[$lookup] = $rules;
	}

	private function build_widget_example($tag, $data) {
		$schema    = $this->extract_widget_schema($tag, $data);
		$inner_key = isset($data['innerHTML']) ? $data['innerHTML'] : '';
		$own_sections = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : array();

		$attrs = array();
		foreach ($schema['sections'] as $section_key => $section) {
			if (!isset($own_sections[$section_key])) {
				continue;
			}
			foreach ($section['properties'] as $key => $prop) {
				$type = isset($prop['type']) ? $prop['type'] : '';
				if (strpos($key, '_hover') !== false || !empty($prop['requires'])) {
					continue;
				}
				if (in_array($type, array('text', 'textarea', 'editor'), true)) {
					$label       = !empty($prop['label']) ? $prop['label'] : $key;
					$attrs[$key] = '<on-topic ' . $label . ' — never the widget default>';
				} elseif ($type === 'color') {
					$attrs[$key] = '$primary';
				} elseif ($type === 'icon') {
					$attrs[$key] = !empty($prop['default']) ? $prop['default'] : 'fas fa-star';
				} elseif ($type === 'image') {
					$attrs[$key] = 'https://images.unsplash.com/photo-1557804506-669a67965ba0?w=1200';
				} elseif ($type === 'link') {
					$attrs[$key] = '#';
				}
			}
		}

		$example = array('tag' => $tag, 'attrs' => $attrs);
		if ($inner_key && isset($attrs[$inner_key])) {
			$example['content'] = $attrs[$inner_key];
			unset($example['attrs'][$inner_key]);
		}
		return $example;
	}

	/**
	 * Compact catalog of every registered widget the builder may emit.
	 * Format: tag => "Name|group[|children][|in:parent][|content:innerHTML]"
	 */
	public function get_widget_catalog() {
		global $pagelayer;
		$this->ensure_shortcodes();

		$catalog = array();
		if (empty($pagelayer->shortcodes) || !is_array($pagelayer->shortcodes)) {
			return $catalog;
		}

		foreach ($pagelayer->shortcodes as $tag => $data) {
			if (in_array($tag, self::$skip_tags, true)) {
				continue;
			}
			if (strpos($tag, 'pl_wp_') === 0 && $tag !== 'pl_wp_menu') {
				continue;
			}

			$name  = isset($data['name']) ? $data['name'] : $tag;
			$group = isset($data['group']) ? $data['group'] : 'misc';
			$line  = $name . '|' . $group;

			if (!empty($data['holder']) || !empty($data['has_group'])) {
				$line .= '|children';
			}
			if (!empty($data['parent'])) {
				$line .= '|in:' . implode(',', (array) $data['parent']);
			}
			if (!empty($data['innerHTML'])) {
				$line .= '|content:' . $data['innerHTML'];
			}

			$catalog[$tag] = $line;
		}

		return apply_filters('pagelayer_ai_widget_catalog', $catalog);
	}

	public function get_compact_schemas($tags = array()) {
		global $pagelayer;
		$this->ensure_shortcodes();

		$catalog = $this->get_widget_catalog();
		if (empty($tags)) {
			$tags = array_keys($catalog);
		}

		$pro_flag  = $this->include_pro_settings() ? '1' : '0';
		$pro_ver   = defined('PAGELAYER_PRO_VERSION') ? PAGELAYER_PRO_VERSION : '0';
		$cache_key = 'pagelayer_ai_own_schemas_v5_' . PAGELAYER_VERSION . '_' . $pro_ver . '_' . $pro_flag . '_' . md5(implode(',', $tags) . count($pagelayer->shortcodes));
		$cached    = get_transient($cache_key);
		if (is_array($cached)) {
			return $cached;
		}

		$out = array();
		foreach ($tags as $tag) {
			if (!isset($pagelayer->shortcodes[$tag])) {
				continue;
			}
			$schema  = $this->extract_widget_schema($tag, $pagelayer->shortcodes[$tag]);
			$compact = $this->compact_widget_schema($schema, 'own');
			unset($compact['legend'], $compact['shared_note'], $compact['shared_style_sections']);
			$out[$tag] = $compact;
		}

		set_transient($cache_key, $out, self::SCHEMA_CACHE_TTL);
		return $out;
	}

	public function get_common_styles() {
		global $pagelayer;
		$this->ensure_shortcodes();

		$probe = isset($pagelayer->shortcodes['pl_heading']) ? 'pl_heading' : (is_array($pagelayer->shortcodes) ? key($pagelayer->shortcodes) : '');
		if (!$probe || empty($pagelayer->shortcodes[$probe])) {
			return array();
		}

		$sections = apply_filters('pagelayer_ai_common_style_sections', array(
			'ele_bg_styles', 'ele_styles', 'border_styles', 'font_style', 'custom_styles',
		));

		$schema  = $this->extract_widget_schema($probe, $pagelayer->shortcodes[$probe]);
		$compact = $this->compact_widget_schema($schema, 'shared', $sections);
		$styles  = !empty($compact['props']) && is_array($compact['props']) ? $compact['props'] : array();

		foreach ($styles as $section => $props) {
			if (!is_array($props)) {
				continue;
			}
			foreach (array_keys($props) as $key) {
				if (strpos($key, '_hover') !== false || strpos($key, '_state') !== false) {
					unset($styles[$section][$key]);
				}
			}
		}

		return $styles;
	}

	public function get_global_color_keys() {
		$snap = $this->get_globals_snapshot();
		$keys = array_keys($snap['colors']);
		return !empty($keys) ? $keys : array('primary', 'secondary', 'text', 'accent');
	}

	/**
	 * Current Pagelayer global colors + fonts as stored (title/value maps).
	 */
	public function get_globals_snapshot() {
		global $pagelayer;
		if (function_exists('pagelayer_load_global_palette')) {
			pagelayer_load_global_palette();
		}

		$colors = (isset($pagelayer->global_colors) && is_array($pagelayer->global_colors))
			? $pagelayer->global_colors
			: array();
		$fonts = (isset($pagelayer->global_fonts) && is_array($pagelayer->global_fonts))
			? $pagelayer->global_fonts
			: array();

		if (empty($colors)) {
			$colors = array(
				'primary'   => array('title' => 'Primary', 'value' => '#2563eb'),
				'secondary' => array('title' => 'Secondary', 'value' => '#0f172a'),
				'text'      => array('title' => 'Text', 'value' => '#0f172a'),
				'accent'    => array('title' => 'Accent', 'value' => '#f59e0b'),
				'bg'        => array('title' => 'Background', 'value' => '#ffffff'),
				'surface'   => array('title' => 'Surface', 'value' => '#f8fafc'),
			);
		}

		return array(
			'colors' => $colors,
			'fonts'  => $fonts,
		);
	}

	private function sanitize_hex($hex) {
		$hex = $this->coerce_hex($hex);
		return $hex;
	}

	private function coerce_hex($val) {
		$val = trim((string) $val);
		if ($val === '') {
			return '';
		}
		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $val)) {
			return strtolower($val);
		}
		if (preg_match('/^([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $val)) {
			return '#' . strtolower($val);
		}
		if (preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $val, $m)) {
			return sprintf('#%02x%02x%02x', min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]));
		}
		$named = array(
			'dark'      => '#0f172a',
			'light'     => '#f8fafc',
			'black'     => '#111827',
			'white'     => '#ffffff',
			'red'       => '#ef4444',
			'blue'      => '#2563eb',
			'green'     => '#10b981',
			'yellow'    => '#f59e0b',
			'orange'    => '#f97316',
			'purple'    => '#8b5cf6',
			'pink'      => '#ec4899',
			'navy'      => '#0f172a',
			'gold'      => '#d97706',
			'teal'      => '#14b8a6',
			'gray'      => '#64748b',
			'grey'      => '#64748b',
			'charcoal'  => '#1e293b',
			'cream'     => '#fdf8f4',
			'indigo'    => '#6366f1',
			'cyan'      => '#06b6d4',
		);
		$low = strtolower($val);
		if (isset($named[$low])) {
			return $named[$low];
		}
		return '';
	}

	private function sanitize_font_family($name) {
		$name = $this->first_font_name($name);
		$name = preg_replace('/[^a-zA-Z0-9 \-]/', '', $name);
		$name = trim(preg_replace('/\s+/', ' ', $name));
		if ($name === '' || strlen($name) > 60) {
			return '';
		}
		return $name;
	}

	private function first_font_name($name) {
		$name = trim((string) $name);
		$name = trim($name, "\"'`");
		if (strpos($name, ',') !== false) {
			$name = trim(explode(',', $name)[0], "\"'` ");
		}
		return $name;
	}

	private function mix_hex($a, $b, $t) {
		$a = ltrim($this->coerce_hex($a), '#');
		$b = ltrim($this->coerce_hex($b), '#');
		if (strlen($a) === 3) {
			$a = $a[0] . $a[0] . $a[1] . $a[1] . $a[2] . $a[2];
		}
		if (strlen($b) === 3) {
			$b = $b[0] . $b[0] . $b[1] . $b[1] . $b[2] . $b[2];
		}
		if (strlen($a) < 6 || strlen($b) < 6) {
			return '#' . $a;
		}
		$t = max(0, min(1, (float) $t));
		$out = '#';
		for ($i = 0; $i < 3; $i++) {
			$av = hexdec(substr($a, $i * 2, 2));
			$bv = hexdec(substr($b, $i * 2, 2));
			$out .= sprintf('%02x', (int) round($av + ($bv - $av) * $t));
		}
		return $out;
	}

	private function complete_palette($palette) {
		$snap = $this->get_globals_snapshot();
		$keys = array(
			'primary'   => '#2563eb',
			'secondary' => '',
			'text'      => '#0f172a',
			'accent'    => '',
			'bg'        => '#ffffff',
			'surface'   => '',
		);
		$out = array();
		foreach ($keys as $key => $fallback) {
			$hex = '';
			if (is_array($palette) && !empty($palette[$key])) {
				$hex = $this->coerce_hex($palette[$key]);
			}
			if ($hex === '' && !empty($snap['colors'][$key]['value'])) {
				$hex = $this->coerce_hex($snap['colors'][$key]['value']);
			}
			$out[$key] = $hex !== '' ? $hex : $fallback;
		}
		if ($out['primary'] === '') {
			$out['primary'] = '#2563eb';
		}
		if ($out['secondary'] === '') {
			$out['secondary'] = $this->mix_hex($out['primary'], '#0f172a', 0.45);
		}
		if ($out['text'] === '') {
			$out['text'] = '#0f172a';
		}
		if ($out['accent'] === '') {
			$out['accent'] = $this->mix_hex($out['primary'], '#f59e0b', 0.4);
		}
		if ($out['bg'] === '') {
			$out['bg'] = '#ffffff';
		}
		if ($out['surface'] === '') {
			$out['surface'] = $this->mix_hex($out['primary'], '#ffffff', 0.92);
		}
		return $out;
	}

	private function complete_fonts($fonts) {
		$snap = $this->get_globals_snapshot();
		$read = function ($key, $fallback) use ($fonts, $snap) {
			$raw = '';
			if (is_array($fonts) && !empty($fonts[$key])) {
				if (is_string($fonts[$key])) {
					$raw = $fonts[$key];
				} elseif (is_array($fonts[$key])) {
					if (!empty($fonts[$key]['font-family']) && is_string($fonts[$key]['font-family'])) {
						$raw = $fonts[$key]['font-family'];
					} elseif (!empty($fonts[$key]['value']['font-family'])) {
						$raw = $fonts[$key]['value']['font-family'];
					}
				}
			}
			if ($raw === '' && !empty($snap['fonts'][$key]['value']['font-family'])) {
				$raw = $snap['fonts'][$key]['value']['font-family'];
			}
			$family = $this->sanitize_font_family($raw);
			return $family !== '' ? $family : $fallback;
		};

		return array(
			'primary'   => $read('primary', 'Poppins'),
			'secondary' => $read('secondary', 'Poppins'),
			'text'      => $read('text', 'Inter'),
			'accent'    => $read('accent', 'Poppins'),
		);
	}

	/**
	 * Write AI-chosen palette/fonts into Pagelayer global settings and memory
	 * so $primary / $text tokens resolve during render. Always completes bg + fonts.
	 */
	public function apply_ai_globals($palette = array(), $fonts = array(), $persist = true) {
		global $pagelayer;

		$palette = $this->complete_palette(is_array($palette) ? $palette : array());
		$fonts   = $this->complete_fonts(is_array($fonts) ? $fonts : array());

		$snap   = $this->get_globals_snapshot();
		$colors = $snap['colors'];
		$gfonts = $snap['fonts'];

		$titles = array(
			'primary'   => 'Primary',
			'secondary' => 'Secondary',
			'text'      => 'Text',
			'accent'    => 'Accent',
			'bg'        => 'Background',
			'surface'   => 'Surface',
		);
		foreach ($titles as $key => $title) {
			if (empty($palette[$key])) {
				continue;
			}
			$hex = $this->coerce_hex($palette[$key]);
			if ($hex === '') {
				continue;
			}
			if (!isset($colors[$key]) || !is_array($colors[$key])) {
				$colors[$key] = array('title' => $title, 'value' => $hex);
			} else {
				$colors[$key]['value'] = $hex;
				if (empty($colors[$key]['title'])) {
					$colors[$key]['title'] = $title;
				}
			}
		}

		foreach (array('primary' => 'Primary', 'secondary' => 'Secondary', 'text' => 'Text', 'accent' => 'Accent') as $key => $title) {
			if (empty($fonts[$key])) {
				continue;
			}
			$family = $this->sanitize_font_family($fonts[$key]);
			if ($family === '') {
				continue;
			}
			$base = (isset($pagelayer) && is_object($pagelayer) && method_exists($pagelayer, 'default_font_styles'))
				? $pagelayer->default_font_styles(array('font-family' => $family))
				: array('font-family' => $family);
			if (!isset($gfonts[$key]) || !is_array($gfonts[$key])) {
				$gfonts[$key] = array('title' => $title, 'value' => $base);
			} else {
				if (empty($gfonts[$key]['value']) || !is_array($gfonts[$key]['value'])) {
					$gfonts[$key]['value'] = $base;
				} else {
					$gfonts[$key]['value']['font-family'] = $family;
				}
				if (empty($gfonts[$key]['title'])) {
					$gfonts[$key]['title'] = $title;
				}
			}
		}

		if (isset($pagelayer) && is_object($pagelayer)) {
			$pagelayer->global_colors = $colors;
			$pagelayer->global_fonts  = $gfonts;
		}

		if ($persist) {
			update_option('pagelayer_global_colors', wp_json_encode($colors));
			update_option('pagelayer_global_fonts', wp_json_encode($gfonts));
		}

		return array(
			'colors' => $colors,
			'fonts'  => $gfonts,
		);
	}

	/**
	 * Detect if user prompt is asking to update/change global colors
	 */
	public function is_global_color_request($prompt) {
		$hay = strtolower(trim((string) $prompt));
		if ($hay === '') {
			return false;
		}
		// If build/create/design command, it is a build request, not a pure color request
		if (preg_match('/^\s*(build|create|design|generate)\b/i', $hay) || preg_match('/\b(build|create|design)\s+(other|another|a|an|new|this|page|section)\b/i', $hay)) {
			return false;
		}
		if (preg_match('/\b(global\s+colou?rs?|global\s+palette|brand\s+colou?rs?|site\s+colou?rs?|theme\s+colou?r\s+global)\b/i', $hay)) {
			return true;
		}
		if (preg_match('/\b(change|set|update|modify|turn|make)\b.{0,30}\b(global\s+colou?r|global\s+palette|brand\s+colou?r|theme\s+colou?r\s+global)\b/i', $hay)) {
			return true;
		}
		if (preg_match('/\b(give\s+)?set\s+this\s+global\s+colou?r\b/i', $hay)) {
			return true;
		}
		return false;
	}

	/**
	 * Detect if user prompt is asking to change/set background color or canvas dark/light mode
	 */
	public function is_background_color_request($prompt) {
		$hay = strtolower(trim((string) $prompt));
		if ($hay === '') {
			return false;
		}
		// If build/create/design command, it is a build request, not a pure background request
		if (preg_match('/^\s*(build|create|design|generate)\b/i', $hay) || preg_match('/\b(build|create|design)\s+(other|another|a|an|new|this|page|section)\b/i', $hay)) {
			return false;
		}
		if (preg_match('/\b(make|set|change|turn|switch)\b.{0,25}\b(all\s+)?(background|bg|theme)\b/i', $hay)) {
			return true;
		}
		if (preg_match('/\b(change|set|update)\b.{0,20}\b(background\s+colou?r|bg\s+colou?r|canvas\s+colou?r)\b/i', $hay)) {
			return true;
		}
		if (preg_match('/\b(make|set)\b.{0,20}\b(all\s+)?(background|bg)\b.{0,20}\b(dark|light|white|black|#[0-9a-f]{3,6}|[a-z]+)\b/i', $hay)) {
			return true;
		}
		if (preg_match('/\b(background|bg)\b.{0,20}\b(dark|light|white|black|#[0-9a-f]{3,6}|[a-z]+)\b/i', $hay) && !preg_match('/\b(make|build|create)\s+(a|an|new)\s+(section|page|hero|pricing)\b/i', $hay)) {
			return true;
		}
		if (preg_match('/\b(dark\s*mode|light\s*mode)\b/i', $hay) && !preg_match('/\b(make|build|create)\s+(a|an|new)\s+(section|page|hero|pricing)\b/i', $hay)) {
			return true;
		}
		if (preg_match('/\b(give\s+)?set\s+this\s+colou?r\b/i', $hay)) {
			return true;
		}
		return false;
	}

	/**
	 * Process a global color instruction, persist to DB, and return snapshot
	 */
	public function handle_global_color_instruction($prompt) {
		$hay = strtolower(trim((string) $prompt));
		$snap = $this->get_globals_snapshot();
		$palette = array();
		foreach ($snap['colors'] as $k => $c) {
			$palette[$k] = !empty($c['value']) ? $c['value'] : '#ffffff';
		}

		$extracted_color = $this->extract_color_from_string($hay);
		if ($extracted_color === '') {
			$extracted_color = '#2563eb';
		}

		$target_key = 'primary';
		if (preg_match('/\b(secondary)\b/i', $hay)) {
			$target_key = 'secondary';
		} elseif (preg_match('/\b(text|copy|font\s*colou?r)\b/i', $hay)) {
			$target_key = 'text';
		} elseif (preg_match('/\b(accent)\b/i', $hay)) {
			$target_key = 'accent';
		} elseif (preg_match('/\b(background|bg)\b/i', $hay)) {
			$target_key = 'bg';
		} elseif (preg_match('/\b(surface)\b/i', $hay)) {
			$target_key = 'surface';
		}

		$palette[$target_key] = $extracted_color;
		if ($target_key === 'bg') {
			$is_dark = $this->is_dark_color($extracted_color);
			$palette['surface'] = $is_dark ? '#1e293b' : '#f8fafc';
			$palette['text'] = $is_dark ? '#f8fafc' : '#0f172a';
		}

		$updated = $this->apply_ai_globals($palette, array(), true);

		return array(
			'success'         => true,
			'action'          => 'update_globals',
			'target_key'      => $target_key,
			'color'           => $extracted_color,
			'palette'         => $palette,
			'globals'         => $this->get_globals_snapshot(),
			'globals_updated' => true,
			'message'         => sprintf(__('Global %s color updated to %s.', 'pagelayer'), ucfirst($target_key), $extracted_color),
		);
	}

	/**
	 * Process a background color or dark mode instruction, persist to DB, and return status
	 */
	public function handle_background_color_instruction($prompt) {
		$hay = strtolower(trim((string) $prompt));

		$is_dark = false;
		$bg_color = '';

		if (preg_match('/\b(dark|black|navy|charcoal|night|dark\s*mode)\b/i', $hay)) {
			$is_dark = true;
			$bg_color = '#0f172a';
		} elseif (preg_match('/\b(light|white|bright|light\s*mode)\b/i', $hay)) {
			$is_dark = false;
			$bg_color = '#ffffff';
		} else {
			$extracted = $this->extract_color_from_string($hay);
			if ($extracted !== '') {
				$bg_color = $extracted;
				$is_dark = $this->is_dark_color($bg_color);
			} else {
				$is_dark = true;
				$bg_color = '#0f172a';
			}
		}

		$snap = $this->get_globals_snapshot();
		$palette = array();
		foreach ($snap['colors'] as $k => $c) {
			$palette[$k] = !empty($c['value']) ? $c['value'] : '#ffffff';
		}

		$palette['bg'] = $bg_color;
		$palette['surface'] = $is_dark ? '#1e293b' : '#f8fafc';
		$palette['text'] = $is_dark ? '#f8fafc' : '#0f172a';

		$updated = $this->apply_ai_globals($palette, array(), true);

		return array(
			'success'         => true,
			'action'          => 'update_background',
			'bg_color'        => $bg_color,
			'is_dark'         => $is_dark,
			'palette'         => $palette,
			'globals'         => $this->get_globals_snapshot(),
			'globals_updated' => true,
			'message'         => $is_dark
				? __('Canvas background and section rows set to dark mode.', 'pagelayer')
				: sprintf(__('Canvas background color set to %s.', 'pagelayer'), $bg_color),
		);
	}

	/**
	 * Extract a hex or named color from any text string
	 */
	public function extract_color_from_string($str) {
		if (preg_match('/#([0-9a-f]{6}|[0-9a-f]{3})\b/i', $str, $m)) {
			return strtolower($m[0]);
		}
		if (preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $str, $m)) {
			return sprintf('#%02x%02x%02x', min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]));
		}
		$named = array(
			'dark'      => '#0f172a',
			'light'     => '#f8fafc',
			'black'     => '#111827',
			'white'     => '#ffffff',
			'red'       => '#ef4444',
			'blue'      => '#2563eb',
			'green'     => '#10b981',
			'yellow'    => '#f59e0b',
			'orange'    => '#f97316',
			'purple'    => '#8b5cf6',
			'pink'      => '#ec4899',
			'navy'      => '#0f172a',
			'gold'      => '#d97706',
			'teal'      => '#14b8a6',
			'gray'      => '#64748b',
			'grey'      => '#64748b',
			'charcoal'  => '#1e293b',
			'cream'     => '#fdf8f4',
			'indigo'    => '#6366f1',
			'cyan'      => '#06b6d4',
		);
		foreach ($named as $name => $hex) {
			if (preg_match('/\b' . $name . '\b/i', $str)) {
				return $hex;
			}
		}
		return '';
	}

	/**
	 * Check if custom site globals already exist
	 */
	public function has_custom_globals() {
		$colors_opt = get_option('pagelayer_global_colors', '');
		$fonts_opt  = get_option('pagelayer_global_fonts', '');
		return (!empty($colors_opt) && $colors_opt !== '[]' && $colors_opt !== '""') || (!empty($fonts_opt) && $fonts_opt !== '[]' && $fonts_opt !== '""');
	}

	/**
	 * Extract flat color map and font map from current site globals snapshot
	 */
	public function get_existing_tokens() {
		$snap = $this->get_globals_snapshot();
		$palette = array();
		if (!empty($snap['colors']) && is_array($snap['colors'])) {
			foreach ($snap['colors'] as $k => $c) {
				$palette[$k] = !empty($c['value']) ? $c['value'] : (is_string($c) ? $c : '');
			}
		}
		$fonts = array();
		if (!empty($snap['fonts']) && is_array($snap['fonts'])) {
			foreach ($snap['fonts'] as $k => $f) {
				if (!empty($f['value']['font-family'])) {
					$fonts[$k] = $f['value']['font-family'];
				} elseif (is_string($f)) {
					$fonts[$k] = $f;
				}
			}
		}
		return array(
			'palette' => $palette,
			'fonts'   => $fonts,
		);
	}

	public function global_font_family($key = 'primary') {
		$snap = $this->get_globals_snapshot();
		if (!empty($snap['fonts'][$key]['value']['font-family'])) {
			$family = $this->sanitize_font_family($snap['fonts'][$key]['value']['font-family']);
			if ($family !== '') {
				return $family;
			}
		}
		if ($key !== 'primary' && !empty($snap['fonts']['primary']['value']['font-family'])) {
			$family = $this->sanitize_font_family($snap['fonts']['primary']['value']['font-family']);
			if ($family !== '') {
				return $family;
			}
		}
		return ($key === 'text') ? 'Inter' : 'Poppins';
	}

	/**
	 * Live widget groups from the shortcode registry (tag => "Name").
	 */
	public function get_widget_groups() {
		global $pagelayer;
		$this->ensure_shortcodes();

		$groups  = array();
		$catalog = $this->get_widget_catalog();
		if (empty($pagelayer->groups) || !is_array($pagelayer->groups)) {
			foreach ($catalog as $tag => $line) {
				$parts = explode('|', $line);
				$name  = isset($parts[0]) ? $parts[0] : $tag;
				$group = isset($parts[1]) ? $parts[1] : 'misc';
				$groups[$group][] = $tag . ':' . $name;
			}
			return $groups;
		}

		foreach ($pagelayer->groups as $group => $tags) {
			if (!is_array($tags)) {
				continue;
			}
			foreach ($tags as $tag) {
				if (!isset($catalog[$tag])) {
					continue;
				}
				$parts = explode('|', $catalog[$tag]);
				$groups[$group][] = $tag . ':' . $parts[0];
			}
		}

		return $groups;
	}

	/**
	 * Pick widgets whose live name/tag/group matches the user's request.
	 * The catalog is the source of truth — no hardcoded widget field names.
	 */
	public function widgets_for_request($prompt) {
		$catalog = $this->get_widget_catalog();
		$matched = array();

		foreach (array('pl_row', 'pl_col') as $required) {
			if (isset($catalog[$required])) {
				$matched[] = $required;
			}
		}

		$hay   = strtolower((string) $prompt);
		$words = preg_split('/[^a-z0-9]+/i', $hay, -1, PREG_SPLIT_NO_EMPTY);
		$stop  = array(
			'a' => 1, 'an' => 1, 'the' => 1, 'and' => 1, 'or' => 1, 'for' => 1, 'with' => 1,
			'from' => 1, 'this' => 1, 'that' => 1, 'page' => 1, 'section' => 1, 'layout' => 1,
			'make' => 1, 'create' => 1, 'build' => 1, 'want' => 1, 'please' => 1, 'add' => 1,
			'using' => 1, 'into' => 1, 'onto' => 1, 'your' => 1, 'our' => 1, 'my' => 1,
			'modern' => 1, 'nice' => 1, 'good' => 1, 'best' => 1, 'new' => 1, 'some' => 1,
			'need' => 1, 'have' => 1, 'just' => 1, 'like' => 1, 'also' => 1,
			'theme' => 1, 'template' => 1, 'templates' => 1, 'header' => 1, 'footer' => 1,
			'error' => 1, 'found' => 1, 'design' => 1, 'generate' => 1,
		);

		$meaningful = array();
		foreach ($words as $w) {
			$w = strtolower($w);
			if (strlen($w) < 3 || isset($stop[$w])) {
				continue;
			}
			$meaningful[] = $w;
			if (strlen($w) > 4 && substr($w, -1) === 's') {
				$meaningful[] = substr($w, 0, -1);
			}
		}

		foreach ($catalog as $tag => $line) {
			$parts = explode('|', $line);
			$name  = strtolower(isset($parts[0]) ? $parts[0] : '');
			$group = strtolower(isset($parts[1]) ? $parts[1] : '');
			$slug  = strtolower(str_replace(array('pl_', '_'), array('', ' '), $tag));
			$blob  = $name . ' ' . $group . ' ' . $slug . ' ' . $tag;

			if ($name !== '' && strpos($hay, $name) !== false) {
				$matched[] = $tag;
				continue;
			}

			foreach ($meaningful as $w) {
				if (strpos($blob, $w) !== false) {
					$matched[] = $tag;
					break;
				}
			}
		}

		$matched = array_values(array_unique($matched));

		foreach (array('pl_heading', 'pl_text', 'pl_btn', 'pl_image') as $primitive) {
			if (isset($catalog[$primitive]) && !in_array($primitive, $matched, true)) {
				$matched[] = $primitive;
			}
		}

		// Expand holders/children of prompt matches first so purpose-built
		// widgets (including Pro) are not sliced off by the fallback pack.
		$matched = $this->expand_related_widgets($matched);

		$kind = $this->request_kind($prompt);

		$kind_pack = $this->widgets_for_kind($kind);
		if (!empty($kind_pack)) {
			$restricted = array();
			foreach ($kind_pack as $t) {
				if (isset($catalog[$t])) {
					$restricted[] = $t;
				}
			}
			return $this->expand_related_widgets($restricted);
		}

		$extra = array();
		if ($kind === 'blog' || $kind === 'single') {
			$extra = array('pl_archive_posts', 'pl_posts', 'pl_featured_image', 'pl_post_title', 'pl_post_excerpt', 'pl_post_info', 'pl_post_content');
		} elseif ($kind === 'contact') {
			$extra = array('pl_heading', 'pl_text', 'pl_btn', 'pl_address', 'pl_phone', 'pl_email');
		} elseif ($kind === 'faq') {
			$extra = array('pl_accordion', 'pl_accordion_item', 'pl_heading', 'pl_text');
		}

		foreach ($extra as $tag) {
			if (isset($catalog[$tag]) && !in_array($tag, $matched, true)) {
				$matched[] = $tag;
			}
		}

		$requested_sections = $this->extract_requested_sections($prompt);
		$is_full_page       = ($kind === 'home' || (!$this->is_scoped_kind($kind) && count($requested_sections) >= 2));

		if ($is_full_page) {
			foreach ($this->default_widget_set() as $tag) {
				if (!in_array($tag, $matched, true)) {
					$matched[] = $tag;
				}
			}
			foreach (array_keys($catalog) as $tag) {
				if (!in_array($tag, $matched, true)) {
					$matched[] = $tag;
				}
			}
		} else {
			foreach (array('pl_row', 'pl_col', 'pl_heading', 'pl_text', 'pl_btn', 'pl_image') as $tag) {
				if (isset($catalog[$tag]) && !in_array($tag, $matched, true)) {
					$matched[] = $tag;
				}
			}
		}

		return $this->expand_related_widgets($matched);
	}

	/**
	 * Widget allow-list for theme-builder chrome and 404 so the model cannot
	 * reach for icon boxes / services / testimonials.
	 */
	public function widgets_for_kind($kind) {
		$packs = array(
			'header' => array(
				'pl_row', 'pl_col', 'pl_heading', 'pl_image', 'pl_btn', 'pl_icon',
				'pl_wp_menu', 'pl_search', 'pl_social_grp', 'pl_social',
			),
			'footer' => array(
				'pl_row', 'pl_col', 'pl_heading', 'pl_text', 'pl_btn', 'pl_image', 'pl_icon',
				'pl_list', 'pl_list_item', 'pl_social_grp', 'pl_social',
				'pl_address', 'pl_phone', 'pl_email', 'pl_divider', 'pl_space',
			),
			'404' => array(
				'pl_row', 'pl_col', 'pl_heading', 'pl_text', 'pl_btn', 'pl_search',
				'pl_icon', 'pl_image', 'pl_list', 'pl_list_item',
			),
		);
		return isset($packs[$kind]) ? $packs[$kind] : array();
	}

	/**
	 * If a holder is selected, include its children; if a child is selected, include its parent.
	 */
	public function expand_related_widgets($tags) {
		$catalog = $this->get_widget_catalog();
		$set     = array();
		foreach ((array) $tags as $tag) {
			$tag = $this->normalize_tag((string) $tag);
			if (empty($catalog) || isset($catalog[$tag])) {
				$set[$tag] = true;
			}
		}

		foreach ($catalog as $tag => $line) {
			$parts = explode('|', $line);
			foreach ($parts as $part) {
				if (strpos($part, 'in:') !== 0) {
					continue;
				}
				$parents = explode(',', substr($part, 3));
				if (isset($set[$tag])) {
					foreach ($parents as $parent) {
						$parent = trim($parent);
						if (isset($catalog[$parent])) {
							$set[$parent] = true;
						}
					}
				}
				foreach ($parents as $parent) {
					$parent = trim($parent);
					if (isset($set[$parent]) && isset($catalog[$tag])) {
						$set[$tag] = true;
					}
				}
			}
		}

		$out = array_keys($set);
		$max = $this->max_schema_widgets();
		if (count($out) > $max) {
			$priority = array();
			foreach ((array) $tags as $tag) {
				$tag = $this->normalize_tag((string) $tag);
				if (isset($set[$tag])) {
					$priority[$tag] = true;
				}
			}
			$rest = array();
			foreach ($out as $tag) {
				if (!isset($priority[$tag])) {
					$rest[] = $tag;
				}
			}
			$out = array_merge(array_keys($priority), $rest);
			$out = array_slice($out, 0, $max);
		}
		return $out;
	}

	public function max_schema_widgets() {
		$max = (int) apply_filters('pagelayer_ai_max_schema_widgets', self::MAX_SCHEMA_WIDGETS);
		return $max > 0 ? $max : self::MAX_SCHEMA_WIDGETS;
	}

	public function get_compact_examples($tags) {
		global $pagelayer;
		$this->ensure_shortcodes();

		$out = array();
		foreach ((array) $tags as $tag) {
			$tag = $this->normalize_tag((string) $tag);
			if (empty($pagelayer->shortcodes[$tag])) {
				continue;
			}
			$example = $this->build_widget_example($tag, $pagelayer->shortcodes[$tag]);
			if (empty($example) || !is_array($example)) {
				continue;
			}
			if (!empty($example['attrs']) && is_array($example['attrs'])) {
				foreach ($example['attrs'] as $k => $v) {
					if (is_string($v) && strlen($v) > 80) {
						$example['attrs'][$k] = substr($v, 0, 77) . '...';
					}
				}
			}
			if (!empty($example['content']) && is_string($example['content']) && strlen($example['content']) > 80) {
				$example['content'] = substr($example['content'], 0, 77) . '...';
			}
			$out[$tag] = $example;
		}
		return $out;
	}

	private function schema_legend() {
		return 'prop format "type|lbl:Label|def:X|opts:a,b|min-maxunit|req:attr=val|resp". '
			. 'Use the property KEY as the attrs key and lbl as the meaning — never invent a shorter alias. '
			. 'req = companion attr that must ALSO be set on the same node or Pagelayer discards the property at render with no error. '
			. '"a/b" = any one of those values; "&" = all conditions; leading "!" negates. '
			. 'resp = also accepts _tablet and _mobile suffixed siblings. '
			. 'content_attr = that field is the node "content" string, not an attrs key. '
			. 'renders = attrs the widget markup actually interpolates.';
	}

	public function get_plan_prompt() {
		$catalog = $this->get_widget_catalog();
		$groups  = $this->get_widget_groups();

		$prompt  = "You are a senior product/brand designer planning a Pagelayer page or Theme Builder template.\n";
		$prompt .= "Infer what the named brand, domain, or product actually is (industry, audience, offer) from the prompt. ";
		$prompt .= "SiteSEO is an SEO product, not a restaurant; a pizzeria is not a gym. Design for THAT business.\n";
		$prompt .= "SCOPE: Plan ONLY what was asked. A header, footer, 404, blog, or inner page is NOT a homepage — do not add extra marketing bands.\n";
		$prompt .= "NO HEADER / NO FOOTER RULE: Do NOT include a website header (navbar, site menu, logo bar) or website footer (copyright bar, site-wide footer links) in the plan UNLESS the user explicitly asks to build a header or footer. In Pagelayer, headers and footers are managed separately via Pagelayer Theme Builder templates.\n";
		$prompt .= "Choose REAL widgets from the live catalog. Do not invent tags. Include holder children.\n";
		$prompt .= "Invent a complete, original information architecture and column map for THIS request. ";
		$prompt .= "Do not reuse a canned skeleton and do not shrink a homepage to 2–3 generic bands.\n";
		$prompt .= "A homepage/site must feel like a finished marketing theme: distinctive opening, the product explained, proof or specifics, conversion, plus only the extra bands THIS product needs.\n";
		$prompt .= "A header/footer/404 plan must be a finished Theme Builder template of that type only — never a photo-hero with one line of text, and never extra features/services sections.\n\n";
		$prompt .= "OUTPUT a JSON object only:\n";
		$prompt .= '{"widgets":["pl_row","pl_col",...],"layout":[{"name":"block-name","cols":[7,5],"widgets":["pl_heading","pl_btn","pl_image"]}],"palette":{"primary":"#hex","secondary":"#hex","text":"#hex","accent":"#hex","bg":"#hex","surface":"#hex"},"fonts":{"primary":"Heading Font","text":"Body Font"},"mood":"short visual direction","summary":"one sentence"}\n';
		$prompt .= "layout[] is the architecture you invented (column splits + widgets per band) for THIS brand. Always include pl_row and pl_col.\n";
		$prompt .= "palette MUST include bg and surface. fonts.primary is the heading family, fonts.text is the body family (real Google fonts).\n";
		$prompt .= "widgets[] should list every catalog tag this page will use — prefer purpose-built widgets, not only heading/text/button/image.\n";
		$prompt .= "\nWIDGET GROUPS (live on this install):\n";
		$prompt .= wp_json_encode($groups, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
		$prompt .= "CATALOG (tag => Name|group[|children][|in:parent][|content:innerHTML]):\n";
		$prompt .= wp_json_encode($catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

		return apply_filters('pagelayer_ai_plan_prompt', $prompt, $this);
	}

	public function get_build_prompt($widget_tags = array(), $opts = array()) {
		$catalog = $this->get_widget_catalog();
		$groups  = $this->get_widget_groups();
		$kind      = !empty($opts['kind']) ? $opts['kind'] : 'section';

		if (empty($widget_tags)) {
			$widget_tags = array_keys($catalog);
		}

		$widget_tags = $this->expand_related_widgets(array_merge(
			array('pl_row', 'pl_col'),
			$widget_tags
		));

		$schemas = $this->get_compact_schemas($widget_tags);
		$styles  = $this->get_common_styles();
		$example_tags = array_values(array_diff($widget_tags, array('pl_row', 'pl_col', 'pl_inner_row', 'pl_inner_col')));
		$examples     = $this->get_compact_examples(array_slice($example_tags, 0, 20));

		$scoped = $this->is_scoped_kind($kind);
		$chrome = in_array($kind, array('header', 'footer', '404'), true);

		if ($chrome) {
			$allowed = $this->widgets_for_kind($kind);
			if (!empty($allowed)) {
				$widget_tags = $this->expand_related_widgets(array_merge(array('pl_row', 'pl_col'), $allowed));
			}
			$filtered = array();
			foreach ($widget_tags as $t) {
				if (isset($catalog[$t])) {
					$filtered[$t] = $catalog[$t];
				}
			}
			if (!empty($filtered)) {
				$catalog = $filtered;
			}
			$schemas = $this->get_compact_schemas($widget_tags);
			$example_tags = array_values(array_diff($widget_tags, array('pl_row', 'pl_col', 'pl_inner_row', 'pl_inner_col')));
			$examples     = $this->get_compact_examples(array_slice($example_tags, 0, 20));
		}

		$prompt  = "You are a senior product designer and Pagelayer layout engine. Convert the user's request into a production-ready, visually finished Pagelayer JSON theme — not a wireframe sketch.\n";
		$prompt .= "SCOPE: Build ONLY what they asked for. A header, footer, 404, blog template, service page, about page, or contact page is NOT a homepage. Do not add extra marketing bands they did not request.\n";
		if ($kind !== 'header' && $kind !== 'footer') {
			$prompt .= "NO HEADER / NO FOOTER RULE: Do NOT generate a website navigation header (logo + nav menu bar) or website footer (copyright bar / footer links) UNLESS the user explicitly asked to create a header or footer. Pagelayer handles site headers and footers globally via Theme Builder templates.\n";
		}
		$prompt .= "BRAND: Infer the real product/industry from the brand, domain, or topic in the prompt and design for that. Do not invent a generic layout and swap the name in.\n";
		$prompt .= "STRUCTURE: YOU invent the information architecture, column map, widget mix, and visual composition for THIS request. Do not reuse a canned skeleton. Do not emit section-preset shorthand ({\"section\":\"hero\"}). Always emit real pl_row / pl_col trees with widget settings.\n";
		$prompt .= "BADGES: A section badge is its own widget/row above the heading, wrapped in <span class=\"pagelayer-badge\"> with margin-bottom. Never overlay raw '(ribbon text)' or badge strings on a CTA heading. If ribbon_text is empty or a placeholder, omit the ribbon (show_ribbon unset).\n";
		$prompt .= "SOCIAL: Use pl_social_grp > pl_social children in a horizontal inline-flex row with gap. Never stack social icons as full-width vertical buttons. icon_color and icon_bg_color MUST contrast (white glyph on \$primary circle). Never set them to the same color.\n";
		if (!empty($opts['structure_brief'])) {
			$prompt .= $opts['structure_brief'] . "\n\n";
		} elseif ($kind !== 'section') {
			$prompt .= $this->structure_brief($kind, !empty($opts['prompt']) ? $opts['prompt'] : '') . "\n\n";
		}
		if ($kind === 'home') {
			$prompt .= "This request is a HOMEPAGE / FULL SITE landing. Invent a complete branded marketing theme for this product — opening hero, offer, proof/specifics, conversion, plus only bands that belong to THIS product. (Do NOT include site header or site footer).\n\n";
		} elseif ($kind === 'header') {
			$prompt .= "STRICT SCOPE FOR HEADER THEME TEMPLATE:\n";
			$prompt .= "- This is a Pagelayer Theme Builder HEADER, not a page. Generate ONE slim [pl_row]: brand/logo + pl_wp_menu (horizontal) + optional CTA.\n";
			$prompt .= "- Color bar background (ele_bg_type=color). Do NOT use a full-bleed photo as the design.\n";
			$prompt .= "- ABSOLUTELY DO NOT add features, services, icon boxes, testimonials, heroes, or any extra page sections.\n\n";
		} elseif ($kind === 'footer') {
			$prompt .= "STRICT SCOPE FOR FOOTER THEME TEMPLATE:\n";
			$prompt .= "- This is a Pagelayer Theme Builder FOOTER, not a page. Generate TWO [pl_row]s: (1) 3–4 columns — Brand/about, Useful links (pl_list), Contact, Social; (2) copyright bar.\n";
			$prompt .= "- Color background (ele_bg_type=color). A footer that is only a background image plus one line of text is a FAILED response.\n";
			$prompt .= "- ABSOLUTELY DO NOT add features, services, icon boxes, testimonials, pricing, hero, or any extra marketing sections.\n\n";
		} elseif ($kind === '404') {
			$prompt .= "STRICT SCOPE FOR 404 ERROR THEME TEMPLATE:\n";
			$prompt .= "- This is a Pagelayer Theme Builder 404 template, not a homepage. Build a finished error screen: large 404 h1, headline, explanation, pl_search, Back to Home pl_btn. Optional 3–4 text links.\n";
			$prompt .= "- Centered on a color background. A 404 that is only a background image plus one heading is a FAILED response.\n";
			$prompt .= "- ABSOLUTELY DO NOT add feature cards, icon boxes, services, counters, testimonials, pricing, or marketing bands.\n\n";
		} elseif ($kind === 'blog') {
			$prompt .= "This is a BLOG LISTING / ARCHIVE theme template only. Invent the composition. Use catalog post widgets if available. No marketing homepage.\n\n";
		} elseif ($kind === 'single') {
			$prompt .= "This is a SINGLE POST theme template only. Invent the composition. No homepage.\n\n";
		} elseif ($kind === 'service') {
			$prompt .= "This is a SERVICES page only. Invent a layout that fits this domain. Do not wrap it in a homepage.\n\n";
		} elseif (in_array($kind, array('about', 'contact', 'pricing', 'faq', 'search'), true)) {
			$prompt .= "This is a dedicated {$kind} page only. Invent the composition. Do not expand it into a homepage. Do not add extra unrequested sections.\n\n";
		} else {
			$prompt .= "STRICT SCOPE: Follow the user's request. If they asked for one section, build ONLY that section. Do NOT add extra unrequested sections (such as feature icon boxes, services, or testimonials) unless explicitly requested in the prompt — but still finish it as designed UI, not unstyled defaults.\n\n";
		}
		$prompt .= "Use ONLY tags from the catalog and ONLY attribute keys from the widget schemas / common styles below. Inventing a field name makes the style silently vanish.\n\n";

		$prompt .= "OUTPUT a JSON object only:\n";
		$prompt .= '{"palette":{"primary":"#hex","secondary":"#hex","text":"#hex","accent":"#hex","bg":"#hex"},"fonts":{"primary":"Font Name","text":"Font Name"},"nodes":[ ... ]}' . "\n";
		$prompt .= "palette/fonts are written to Pagelayer global settings. Each node: {\"tag\":\"pl_...\",\"attrs\":{...},\"content\": ...}\n";
		$prompt .= "- content is a STRING when the schema has content_attr (that text is innerHTML).\n";
		$prompt .= "- content is an ARRAY of child nodes for containers (pl_row, pl_col, and any widget marked |children or accepts_children).\n";
		$prompt .= "- No markdown. No greeting. JSON only.\n\n";

		$prompt .= "HIERARCHY:\n";
		$prompt .= "1. Root nodes are pl_row.\n";
		$prompt .= "2. pl_row children are ONLY pl_col. Column widths (attrs.col 1-12) in a row MUST sum to 12.\n";
		$prompt .= "3. Widgets live inside pl_col. Nested rows inside a column are still pl_row/pl_col.\n";
		$prompt .= "4. Widgets marked |in:parent MUST be nested under that parent.\n";
		$prompt .= "5. Never emit empty columns. Never put a leaf widget directly in a row.\n\n";

		if (!$chrome) {
			$prompt .= "CARD GRID LAYOUT (only when you actually use repeating cards):\n";
			$prompt .= "- You are NOT required to use icon boxes. Use them only if this page needs that content.\n";
			$prompt .= "- When you DO use Icon Box, Feature, Service, Testimonial, Counter, Pricing, or similar cards: one widget per column, never stacked in a single column.\n";
			$prompt .= "- 1 item = one col=12. 2 items = one row of two col=6. 3 items = one row of three col=4: [Item1][Item2][Item3].\n";
			$prompt .= "- 4+ items: fill a row of 3 columns, then start a NEW sibling pl_row underneath for the rest. Example 6 items:\n";
			$prompt .= "  Row A: [Item1 col=4] [Item2 col=4] [Item3 col=4]\n";
			$prompt .= "  Row B: [Item4 col=4] [Item5 col=4] [Item6 col=4]\n";
			$prompt .= "- Never put 4+ card columns in the same pl_row. Never wrap a card grid in a nested row inside a col=12. Never dump all cards into one column.\n";
			$prompt .= "- Section headings/subcopy go in their own full-width col=12 row above the card grid, not inside the first card column.\n";
			$prompt .= "- UNIFORM IMAGE & ICON SIZING: When using Image Box (pl_service) or card images, all images across columns MUST have the exact same height (220px, object-fit: cover) so card headings and buttons align across columns. For Icon Box (pl_iconbox), all icons must use identical 64px by 64px sizing. For Image Slider (pl_image_slider), do NOT leave images unconstrained or excessively tall; keep slider image height well-proportioned (400px–440px height, object-fit: cover, max-width: 1000px, border-radius: 12px) so it fits elegantly on the screen.\n\n";
		}

		if ($kind === 'home' || (!empty($opts['full_page']) && !$scoped)) {
			$prompt .= "WIDGET MIX (required for homepage / full page):\n";
			$prompt .= "- Use the catalog. A homepage built only from heading+text+button+image is a failed response.\n";
			$prompt .= "- Pick purpose-built widgets that match the content (icon box, counter, testimonial, accordion, list, image, video, pricing, stars, etc.) and fill THAT widget's own schema fields.\n";
			$prompt .= "- A homepage should typically use 8+ different widget tags besides pl_row/pl_col.\n\n";
		} elseif ($kind === 'header') {
			$prompt .= "WIDGET MIX (header theme template):\n";
			$prompt .= "- Use pl_heading or pl_image for the brand, pl_wp_menu for navigation, optional pl_btn CTA.\n";
			$prompt .= "- Do NOT use icon boxes, services, testimonials, or page sections.\n\n";
		} elseif ($kind === 'footer') {
			$prompt .= "WIDGET MIX (footer theme template):\n";
			$prompt .= "- Use pl_heading, pl_text, pl_list + pl_list_item, pl_address/pl_phone/pl_email, pl_social_grp + pl_social, optional pl_btn.\n";
			$prompt .= "- Do NOT use icon boxes, services, testimonials, counters, or pricing.\n\n";
		} elseif ($kind === '404') {
			$prompt .= "WIDGET MIX (for 404 error theme template):\n";
			$prompt .= "- Use pl_heading (404 number + title), pl_text (explanation), pl_btn (Back to Home), pl_search, optional pl_icon and link buttons.\n";
			$prompt .= "- Absolutely DO NOT use icon boxes, testimonials, counters, or feature/service cards.\n\n";
		} else {
			$prompt .= "WIDGET MIX:\n";
			$prompt .= "- Use purpose-built widgets that strictly match what the user requested.\n";
			$prompt .= "- Do NOT add feature cards, icon boxes, services, or testimonials unless the user specifically asked for them.\n\n";
		}

		$prompt .= "FONTS:\n";
		$prompt .= "- palette/fonts in the JSON are saved as Pagelayer globals. Always send fonts.primary (headings) and fonts.text (body) as real Google font names (e.g. Poppins, Inter, Outfit).\n";
		$prompt .= "- Typography props (heading_typo, service_heading_typo, btn_typo, ...) are 11 comma fields: family,size,style,weight,variant,deco-line,deco-style,line-height,transform,letter,word.\n";
		$prompt .= "- ALWAYS put the heading font name in field 0, e.g. \"Poppins,48,,800,,,,1.15,,,\". An empty family means the theme font and looks unfinished.\n";
		$prompt .= "- Body copy (pl_text): set font_family to the body font name AND font_size (16) and line_height (1.7). Do not use font_size on widgets that have their own typography prop.\n\n";

		$prompt .= "COLORS AND BACKGROUNDS:\n";
		$prompt .= "- Invent a NEW palette for THIS brand. JSON palette MUST include primary, secondary, text, accent, bg, surface as #hex.\n";
		$prompt .= "- After you output palette, PHP writes it to the site. Reference colors as \$primary, \$secondary, \$text, \$accent, \$bg, \$surface.\n";
		$prompt .= "- EVERY pl_row needs ele_bg_type=color AND ele_bg_color (\$primary / \$bg / \$surface / a hex). Alternate dark and light bands.\n";
		$prompt .= "- Dark backgrounds MUST use light text (#ffffff). Light / white backgrounds MUST use dark text (\$text / \$primary / #111827). Never put black/#000/\$text on a dark ele_bg_color, and never put white/#fff on a white/\$bg/\$surface band — both are unreadable.\n";
		$prompt .= "- Social icons: bg_shape=pagelayer-social-shape-circle, icon_color=#ffffff, icon_bg_color=\$primary (never the same value).\n";
		$prompt .= "- Image sliders: controls=arrows, nav_size ~28, arraow_bg_size ~48, arraow_color=#ffffff, arrows_bg=rgba(15,23,42,0.72). Arrow glyph and arrow button MUST be different colors.\n";
		$prompt .= "- Accordion / Tabs: tabs_color and tabs_bg_color MUST contrast (e.g. #111827 on #f1f5f9). Active tab is #ffffff on \$primary. Panel text is dark on a light panel. Never set tab text, tab background, and section background to the same color.\n";
		$prompt .= "- Counter: counter_start_number must be 1 or greater (never 0 — 0 hides the widget). counter_end_number greater than start.\n";
		$prompt .= "- EVERY widget: never use the same color for a foreground (text/icon/arrow) and its background.\n";
		$prompt .= "- Set each widget's OWN color fields from its schema (icon color, heading color, text color, button colors). Empty own colors render as widget defaults.\n";
		$prompt .= "- ALWAYS set companion attrs: ele_bg_color needs ele_bg_type=color; btn_bg_color needs type=pagelayer-btn-custom; border_width/border_color need border_type=solid.\n\n";

		$prompt .= "SETTINGS (read the schema — do not guess names; unstyled widgets are a failed response):\n";
		$prompt .= "- The property KEY is the attrs key. lbl: is the meaning. renders: lists attrs the widget markup actually uses.\n";
		$prompt .= "- A prop with req:X=Y is discarded at render unless X is also set on the SAME node. Widget defaults do NOT count.\n";
		$prompt .= "- NEVER write style=\"\" or <style> in content. Styling is attrs only. Use the exact keys from WIDGET SCHEMAS and COMMON STYLES.\n";
		$prompt .= "- Heading/title content is HTML such as <h1>, <h2>, <h3> inside content. One h1 per layout.\n";
		$prompt .= "- Set each widget's OWN visual fields from its schema (colors, icons, images, alignment, typography, spacing) AND common styles (ele_padding, ele_bg_type+ele_bg_color, border_radius, ele_shadow).\n";
		$prompt .= "- Images: real topical Unsplash https URLs in the schema's image key (photo of THIS subject). Do not invent fake domains.\n";
		if ($chrome) {
			$prompt .= "- For header/footer/404: prefer ele_bg_type=color. Do NOT make the whole template a single background-image hero with one line of text.\n";
		}
		$prompt .= "\n";

		$prompt .= "DUMMY DATA (required):\n";
		if ($kind === 'footer') {
			$prompt .= "- Invent realistic footer copy: brand blurb, 4–6 useful links, address/phone/email, social labels, © year copyright. No feature/service/testimonial copy.\n";
		} elseif ($kind === 'header') {
			$prompt .= "- Invent a short brand name and a real CTA label. Navigation comes from pl_wp_menu, not fake Home/About/Services text links unless no menu widget exists.\n";
		} elseif ($kind === '404') {
			$prompt .= "- Invent a short on-brand 404 headline and explanation, a search placeholder, and a Back to Home label. No feature/service marketing copy.\n";
		} else {
			$prompt .= "- Invent realistic dummy content that matches the user's prompt topic (names, prices, quotes, features, FAQs, stats, addresses, emails, phones, button labels).\n";
		}
		$prompt .= "- Fill every heading, text, button, and list item. Never leave schema defaults like \"Icon Box\", \"Title\", \"Lorem ipsum\", empty strings, or placeholders.\n";
		$prompt .= "- Copy is on-topic for THIS brand/product.\n\n";

		if ($chrome) {
			$prompt .= "DESIGN THIS TEMPLATE:\n";
			$prompt .= "- Follow the STRICT SCOPE above. Column widths in a row MUST sum to 12.\n";
			$prompt .= "- Header padding ~16–28px vertical. Footer main row ~48–64px vertical, copyright row ~16–24px. 404 content row min-height via ele_padding (80px+ vertical) and centered align.\n";
			$prompt .= "- Buttons: type=pagelayer-btn-custom (required for colors), plus btn_bg_color and btn_color.\n";
			$prompt .= "- High contrast. No '{ribbon_text}' tokens. No Lorem ipsum.\n\n";
		} else {
			$prompt .= "DESIGN THE THEME (you invent the structure — nothing is prescribed):\n";
			$prompt .= "- Decide rows, column splits (they must sum to 12), which catalog widgets belong in each band, and whether a band is image-led, product-led, list-led, or quote-led — based on THIS brand.\n";
			$prompt .= "- Copy, photos, palette, widgets, AND the wireframe are all invented for this prompt.\n";
			$prompt .= "- Alternate light/dark or tinted rows via ele_bg_type+ele_bg_color. Strong type. EVERY section pl_row must keep its OWN padding-top AND padding-bottom (50px-60px, 20px left/right). Never 0 top on a following row — that makes it touch the columns above.\n";
			$prompt .= "- Padding, radius, shadow via attrs. High contrast (WCAG-ish): light text on dark bands, dark text on light bands.\n";
			$prompt .= "- Buttons: type=pagelayer-btn-custom (required for colors), size=pagelayer-btn-large, plus btn_bg_color and btn_color.\n";
			$prompt .= "- One h1 per page. High contrast. No '{ribbon_text}' tokens. No Lorem ipsum.\n\n";
		}

		$prompt .= "SPACING (schema defaults do NOT render unless the attr is set):\n";
		$prompt .= "- Column gaps MUST be the row setting col_gap (use 20). PHP emits padding on .pagelayer-col-holder — that is what the editor AND the frontend both show.\n";
		$prompt .= "- NEVER fake a column gap with ele_css (gap / grid-gap / column-gap), CSS grid, widget margins, or a transparent border on the column. Those render on the frontend only and disappear in the Pagelayer editor.\n";
		$prompt .= "- Every pl_col MUST set widget_space (use 20). That is the gap between stacked widgets in a column.\n";
		if (!$chrome) {
			$prompt .= "- Card widgets MUST set ele_padding e.g. 32px,28px,32px,28px so the card chrome sits inside the col_gap, visible in both editor and frontend.\n";
			$prompt .= "- Icon Box (pl_iconbox): service_alignment=top, service_icon_alignment=center (Horizontal Position), heading_alignment=center, service_text_alignment=center. service_heading_typo like \"Poppins,20,,700,,,,1.3,,,\". Body copy ~15px / 1.7.\n";
			$prompt .= "- Do not put ele_bg_color / ele_shadow / border on pl_col for a card grid. Style the widget; leave the column transparent so col_gap is the visible gutter.\n";
		}
		$prompt .= "\n";

		$prompt .= "SCHEMA LEGEND: " . $this->schema_legend() . "\n\n";
		if (!$chrome) {
			$prompt .= "WIDGET GROUPS:\n";
			$prompt .= wp_json_encode($groups, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
		}
		$prompt .= "CATALOG (every widget you may use — do not invent tags outside this list):\n";
		$prompt .= wp_json_encode($catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
		$prompt .= "WIDGET SCHEMAS (own settings for the widgets this layout needs):\n";
		$prompt .= wp_json_encode($schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
		if (!empty($examples)) {
			$prompt .= "EXAMPLE NODES (live schema — copy these attr names, not this layout):\n";
			$prompt .= wp_json_encode($examples, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
		}
		$prompt .= "COMMON STYLES (accepted by every widget/row/column — set on attrs):\n";
		$prompt .= wp_json_encode($styles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

		return apply_filters('pagelayer_ai_build_prompt', $prompt, $widget_tags, $this, $opts);
	}

	public function should_two_pass() {
		return apply_filters('pagelayer_ai_should_two_pass', false);
	}

	/**
	 * Page-level requests get a planner pass so the builder designs a theme
	 * instead of a single-shot sketch. Tiny "add a button" prompts stay one pass.
	 */
	public function should_plan($kind) {
		$page = in_array($kind, array('home', 'header', 'footer', '404', 'blog', 'single', 'search', 'about', 'contact', 'service', 'pricing', 'faq'), true);
		return (bool) apply_filters('pagelayer_ai_should_plan', $page || $this->should_two_pass(), $kind);
	}

	public function get_plan_user_message($prompt, $context = array()) {
		$kind  = $this->request_kind($prompt);
		$topic = $this->infer_page_topic($prompt);
		$msg   = "Plan a designed theme for this request. Brand/topic: {$topic}.\n";
		$msg  .= $this->inner_page_brief($kind, $prompt) . "\n";
		$msg  .= $this->structure_brief($kind, $prompt) . "\n";
		if ($kind === 'header' || $kind === 'footer' || $kind === '404') {
			$msg .= "This is a Theme Builder {$kind} template. Plan ONLY that template. Do not plan a homepage or extra marketing sections.\n";
		} elseif ($kind !== 'home') {
			$msg .= "Do not plan a homepage. Do not add extra unrelated sections (features, services, testimonials) unless the user asked for them.\n";
		} else {
			$msg .= "Plan a complete branded homepage/site for this industry — not a 2–3 band sketch and not a generic SaaS clone.\n";
		}
		if (!empty($context['use_existing_globals']) && !empty($context['existing_palette'])) {
			$msg .= "\nCRITICAL: The user has chosen to use the existing site palette (" . wp_json_encode($context['existing_palette']) . ") and typography (" . (!empty($context['existing_fonts']) ? wp_json_encode($context['existing_fonts']) : '') . "). You MUST preserve and reuse these exact colors and fonts in your plan and layout.\n";
		}

		$requested_sections = !empty($context['requested_sections']) ? $context['requested_sections'] : $this->extract_requested_sections($prompt);
		if ($this->is_scoped_kind($kind)) {
			$requested_sections = array();
		}
		if (!empty($requested_sections)) {
			$msg .= "\nCRITICAL REQUIREMENT — USER EXPLICITLY SPECIFIED THESE " . count($requested_sections) . " SECTIONS:\n";
			$msg .= "Your plan MUST include an entry in layout[] for every single one of these sections in order:\n";
			foreach ($requested_sections as $i => $sec) {
				$num = $i + 1;
				$desc = !empty($sec['desc']) ? " — " . $sec['desc'] : '';
				$msg .= "  {$num}. \"{$sec['name']}\"{$desc}\n";
			}
			$msg .= "Do NOT omit, skip, or merge any requested section.\n";
		}

		$msg .= $prompt;
		if (!empty($context['page_title'])) {
			$msg .= "\nTarget page title: " . $context['page_title'];
		}
		if (!empty($context['section_type'])) {
			$msg .= "\nSection type: " . $context['section_type'];
		}
		return $msg;
	}

	public function get_build_user_message($prompt, $context = array(), $plan = array()) {
		$kind  = $this->request_kind($prompt);
		$topic = $this->infer_page_topic($prompt);
		$msg   = "Brand/topic to design for: {$topic}. Infer what this product/business is and design a finished theme for it.\n";
		$msg  .= $this->inner_page_brief($kind, $prompt) . "\n";
		$msg  .= $this->structure_brief($kind, $prompt) . "\n";
		$msg  .= $prompt;
		if (!empty($context['page_title'])) {
			$msg .= "\nTarget page title: " . $context['page_title'];
		}
		if (!empty($context['section_type'])) {
			$msg .= "\nSection type: " . $context['section_type'];
		}
		if (!empty($plan['mood'])) {
			$msg .= "\nBrand mood from the plan: " . $plan['mood'];
		}
		if (!empty($plan['summary'])) {
			$msg .= "\nPlan summary: " . $plan['summary'];
		}
		if (!empty($plan['use_existing_globals'])) {
			$msg .= "\nIMPORTANT: The user wants to preserve existing site global colors & fonts. Reuse existing global tokens (\$primary, \$secondary, \$text, \$accent, \$bg, \$surface) and typography.";
		}
		if (!empty($plan['palette']) && is_array($plan['palette'])) {
			$msg .= "\nPalette to apply: " . wp_json_encode($plan['palette']);
		}
		if (!empty($plan['fonts']) && is_array($plan['fonts'])) {
			$msg .= "\nFonts to apply: " . wp_json_encode($plan['fonts']);
		}
		if (!empty($plan['layout']) && is_array($plan['layout'])) {
			$msg .= "\nUse this invented architecture as a starting point and enrich it (do not collapse it into a thin generic template): " . wp_json_encode($plan['layout']);
		}

		$requested_sections = !empty($plan['requested_sections']) ? $plan['requested_sections'] : (!empty($context['requested_sections']) ? $context['requested_sections'] : $this->extract_requested_sections($prompt));
		if ($this->is_scoped_kind($kind)) {
			$requested_sections = array();
		}
		if (!empty($requested_sections)) {
			$msg .= "\n\n" . str_repeat('=', 70) . "\n";
			$msg .= "CRITICAL REQUIREMENT — ALL USER-SPECIFIED SECTIONS MUST BE INCLUDED:\n";
			$msg .= "The user explicitly listed the following " . count($requested_sections) . " sections. You MUST generate ALL of them in order in your \"nodes\" array. Every requested section must be a complete [pl_row] with realistic dummy copy and widget settings. DO NOT skip, omit, or truncate ANY section:\n";
			foreach ($requested_sections as $i => $sec) {
				$num = $i + 1;
				$desc = !empty($sec['desc']) ? " — " . $sec['desc'] : '';
				$msg .= "  {$num}. [pl_row] \"{$sec['name']}\"{$desc}\n";
			}
			$msg .= "\nTOKEN BUDGETING: Ensure you balance widget detail across all " . count($requested_sections) . " sections so you never run out of tokens before completing the final section. Every single section from 1 to " . count($requested_sections) . " MUST be in your JSON response.\n";
			$msg .= str_repeat('=', 70) . "\n\n";
		}

		$msg .= "\nSet real widget settings from the schemas (own colors/typography/icons/images/alignment AND common ele_padding, ele_bg_type+ele_bg_color, border_radius, ele_shadow). Companion req attrs must be set on the same node.";
		$msg .= "\nPalette must include primary, secondary, text, accent, bg, surface. Fonts must include primary (headings) and text (body) as Google font names.";
		$msg .= "\nEvery heading_typo must start with the heading font name (Family,size,,weight,...). Every pl_text needs font_family. Every pl_row needs ele_bg_type+ele_bg_color.";
		if ($kind === 'home' || !empty($requested_sections)) {
			$msg .= "\nUse many catalog widgets (icon box, counter, testimonial, accordion, list, image, ...), not only heading/text/button.";
		} elseif ($kind === 'header') {
			$msg .= "\nSTRICT HEADER SCOPE: ONE slim header row (logo + pl_wp_menu + optional CTA). Color bar, not a photo hero. NO features, services, or page sections.";
		} elseif ($kind === 'footer') {
			$msg .= "\nSTRICT FOOTER SCOPE: TWO rows — a 3–4 column footer (brand, links, contact, social) plus a copyright bar. Color background. A background-image + one line of text is a failed footer. NO features, services, icon boxes, or testimonials.";
		} elseif ($kind === '404') {
			$msg .= "\nSTRICT 404 SCOPE: Finished error template — large 404 heading, explanation, search, Back to Home button. Color background, not a photo hero with only text. DO NOT add features, icon boxes, testimonials, services, or any other extra sections.";
		} else {
			$msg .= "\nSTRICT SCOPE: Build ONLY the requested {$kind} layout. Do NOT add extra sections like features, icon boxes, services, or testimonials unless specifically requested in the prompt.";
		}
		$msg .= "\nUse Pagelayer global color tokens (\$primary, \$secondary, \$text, \$accent, \$bg, \$surface).";
		$msg .= "\nCard grids: one widget per column. 3 items = 3 columns in one row. 4+ items = a new sibling row of up to 3 columns. Never stack cards in one column and never nest a grid row inside a column.";
		$msg .= "\nColumn spacing must be the row col_gap attribute (20). Do not use ele_css gap or transparent column borders.";
		$msg .= "\nFill every text field with realistic dummy data related to this prompt — never leave widget defaults or empty copy.";
		$msg .= "\nReturn JSON {\"palette\":{...},\"fonts\":{...},\"nodes\":[...]} only.";
		return $msg;
	}

	public function parse_json($raw) {
		if (!is_string($raw) || $raw === '') {
			return null;
		}

		$text = trim($raw);
		$text = preg_replace('/^```(?:json|javascript|js)?\s*/i', '', $text);
		$text = preg_replace('/\s*```$/', '', $text);
		$text = trim($text);

		$decoded = json_decode($text, true);
		if (is_array($decoded)) {
			return $decoded;
		}

		if (preg_match('/(\{.*\}|\[.*\])/s', $text, $m)) {
			$decoded = json_decode($m[1], true);
			if (is_array($decoded)) {
				return $decoded;
			}
			$repaired = $this->repair_truncated_json($m[1]);
			if (is_array($repaired)) {
				return $repaired;
			}
		}

		return $this->repair_truncated_json($text);
	}

	/**
	 * Salvage complete node/section objects from a truncated LLM JSON payload.
	 */
	private function repair_truncated_json($text) {
		if (!is_string($text) || $text === '') {
			return null;
		}

		$nodes = array();
		if (preg_match('/"nodes"\s*:\s*\[/s', $text, $m, PREG_OFFSET_CAPTURE)) {
			$chunk  = substr($text, $m[0][1] + strlen($m[0][0]));
			$offset = 0;
			$len    = strlen($chunk);
			while ($offset < $len) {
				while ($offset < $len && strpos(", \n\r\t", $chunk[$offset]) !== false) {
					$offset++;
				}
				if ($offset >= $len || $chunk[$offset] === ']') {
					break;
				}
				if ($chunk[$offset] !== '{') {
					break;
				}
				$depth  = 0;
				$in_str = false;
				$esc    = false;
				$end    = null;
				for ($j = $offset; $j < $len; $j++) {
					$ch = $chunk[$j];
					if ($in_str) {
						if ($esc) {
							$esc = false;
						} elseif ($ch === '\\') {
							$esc = true;
						} elseif ($ch === '"') {
							$in_str = false;
						}
						continue;
					}
					if ($ch === '"') {
						$in_str = true;
						continue;
					}
					if ($ch === '{') {
						$depth++;
					} elseif ($ch === '}') {
						$depth--;
						if ($depth === 0) {
							$end = $j;
							break;
						}
					}
				}
				if ($end === null) {
					break;
				}
				$obj = json_decode(substr($chunk, $offset, $end - $offset + 1), true);
				if (is_array($obj) && !empty($obj['tag'])) {
					$nodes[] = $obj;
				}
				$offset = $end + 1;
			}
		}

		if (empty($nodes)) {
			return null;
		}

		$out = array('nodes' => $nodes);
		if (preg_match('/"palette"\s*:\s*(\{(?:[^{}]|\{[^{}]*\})*\})/s', $text, $pm)) {
			$palette = json_decode($pm[1], true);
			if (is_array($palette)) {
				$out['palette'] = $palette;
			}
		}
		if (preg_match('/"fonts"\s*:\s*(\{(?:[^{}]|\{[^{}]*\})*\})/s', $text, $fm)) {
			$fonts = json_decode($fm[1], true);
			if (is_array($fonts)) {
				$out['fonts'] = $fonts;
			}
		}
		return $out;
	}

	/**
	/**
	 * Extract user-requested sections from prompt when specified as a bulleted/numbered/dashed list.
	 *
	 * @param string $prompt
	 * @return array Array of array('name' => string, 'desc' => string, 'key' => string)
	 */
	public function extract_requested_sections($prompt) {
		if (!is_string($prompt) || trim($prompt) === '') {
			return array();
		}

		$hay = strtolower(trim($prompt));
		// A header/footer/404 request is never a multi-section homepage list.
		if ($this->wants_header($prompt) || $this->wants_footer($prompt)
			|| strpos($hay, '404') !== false
			|| preg_match('/\b(not\s*found|error\s*page|error\s*template)\b/i', $hay)) {
			return array();
		}

		$sections = array();
		$lines = preg_split('/[\r\n]+/', (string) $prompt);

		// 1. Line-by-line check for section items
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			// Strip leading bullets, numbers, dashes, asterisks
			$clean = preg_replace('/^[\s*\-•#\d\.\)\(\]]+/', '', $line);
			$clean = trim($clean);

			// Match: Name [– or — or - or : or |] Description
			if (preg_match('/^([^–—:\-|]+?)\s*[–—:\-|]\s*(.+)$/u', $clean, $m)) {
				$name = trim($m[1]);
				$desc = trim($m[2]);
				$words = preg_split('/\s+/', $name);
				if (count($words) >= 1 && count($words) <= 6 && strlen($name) <= 50) {
					if (!preg_match('/^(check\s*this|note|example|p\.s|please|make\s*sure|remember|important)\b/i', $name)) {
						$sections[] = array(
							'name' => $name,
							'desc' => $desc,
							'key'  => sanitize_key($name),
						);
					}
				}
			} elseif (preg_match('/^(hero|popular\s*[\w]+|why\s*choose\s*us|features|services|special\s*offer|promotions?|about\s*us|our\s*story|customer\s*reviews|testimonials|reviews|location\s*&\s*hours|contact\s*us|contact|hours\s*&\s*location|final\s*cta|cta|call\s*to\s*action|faq|pricing|stats|team|gallery)\b(.*)$/ui', $clean, $m)) {
				$name = trim($m[1]);
				$desc = trim($m[2]);
				$sections[] = array(
					'name' => ucwords($name),
					'desc' => trim($desc, " \t\n\r\0\x0B–—:-|"),
					'key'  => sanitize_key($name),
				);
			}
		}

		// 2. If line-by-line didn't find multiple sections, check inline numbered or bulleted list in prompt
		if (count($sections) < 2) {
			if (preg_match_all('/(?:\d+[\.\)]|\([0-9a-z]\)|[-•])\s*([^–—:\-\n,;]+?)\s*[–—:\-]\s*([^–—\n;]+?)(?=(?:\d+[\.\)]|\([0-9a-z]\)|[-•]|$))/u', $prompt, $matches, PREG_SET_ORDER)) {
				$inline_sections = array();
				foreach ($matches as $m) {
					$name = trim($m[1]);
					$desc = trim($m[2]);
					$words = preg_split('/\s+/', $name);
					if (count($words) >= 1 && count($words) <= 6 && strlen($name) <= 50) {
						$inline_sections[] = array(
							'name' => $name,
							'desc' => $desc,
							'key'  => sanitize_key($name),
						);
					}
				}
				if (count($inline_sections) >= 2) {
					$sections = $inline_sections;
				}
			}
		}

		return $sections;
	}

	/**
	 * Extract summary text and widget tags from a row node tree
	 */
	public function get_row_summary_text($row_node) {
		$text = '';
		$tags = array();
		$walk = function ($node) use (&$walk, &$text, &$tags) {
			if (!is_array($node)) return;
			if (!empty($node['tag'])) {
				$tags[] = strtolower($node['tag']);
			}
			if (!empty($node['section']) && is_string($node['section'])) {
				$text .= ' ' . $node['section'];
			}
			if (!empty($node['attrs']) && is_array($node['attrs'])) {
				foreach ($node['attrs'] as $k => $v) {
					if (is_string($v) && preg_match('/(heading|title|text|caption|desc|label|name|quote)/i', $k)) {
						$text .= ' ' . $v;
					}
				}
			}
			if (isset($node['content'])) {
				if (is_string($node['content'])) {
					$text .= ' ' . wp_strip_all_tags($node['content']);
				} elseif (is_array($node['content'])) {
					foreach ($node['content'] as $c) {
						$walk($c);
					}
				}
			}
		};
		$walk($row_node);
		return array('text' => strtolower($text), 'tags' => array_unique($tags));
	}

	/**
	 * Find which of the user's requested sections are missing from generated nodes
	 */
	public function find_missing_sections($nodes, $requested_sections) {
		if (empty($requested_sections) || !is_array($requested_sections)) {
			return array();
		}
		if (empty($nodes) || !is_array($nodes)) {
			return $requested_sections;
		}

		$row_summaries = array();
		foreach ($nodes as $row) {
			if (is_array($row) && !empty($row['tag']) && $this->is_row_tag($row['tag'])) {
				$row_summaries[] = $this->get_row_summary_text($row);
			}
		}

		$missing = array();
		foreach ($requested_sections as $sec) {
			$sec_name = strtolower(trim($sec['name']));
			$found    = false;

			$raw_words = preg_split('/[^a-z0-9]+/i', $sec_name);
			$keywords  = array();
			foreach ($raw_words as $w) {
				if (strlen($w) >= 3 && !in_array($w, array('the', 'and', 'for', 'our', 'with', 'your'), true)) {
					$keywords[] = $w;
				}
			}

			foreach ($row_summaries as $summary) {
				$text = $summary['text'];
				$tags = $summary['tags'];

				if (strpos($text, $sec_name) !== false) {
					if (preg_match('/(review|testimonial)/i', $sec_name)) {
						$has_rev_widget = in_array('pl_testimonial', $tags, true) || in_array('pl_review', $tags, true) || in_array('pl_stars', $tags, true);
						if ($has_rev_widget || strlen($text) > 120) {
							$found = true;
							break;
						}
					} else {
						$found = true;
						break;
					}
				}

				$matches_count = 0;
				foreach ($keywords as $kw) {
					if (strpos($text, $kw) !== false) {
						$matches_count++;
					}
				}
				if (!empty($keywords) && $matches_count >= max(1, ceil(count($keywords) * 0.6))) {
					if (preg_match('/(review|testimonial)/i', $sec_name)) {
						$has_rev_widget = in_array('pl_testimonial', $tags, true) || in_array('pl_review', $tags, true) || in_array('pl_stars', $tags, true);
						if ($has_rev_widget || strlen($text) > 120) {
							$found = true;
							break;
						}
					} else {
						$found = true;
						break;
					}
				}

				if (preg_match('/(popular|menu|pizza|items?|product|catalog)/i', $sec_name)) {
					if (in_array('pl_service', $tags, true) && (strpos($text, 'pizza') !== false || strpos($text, 'cart') !== false || strpos($text, '$') !== false)) {
						$found = true;
						break;
					}
				}
				if (preg_match('/(why\s*choose|feature|benefit)/i', $sec_name)) {
					if (in_array('pl_iconbox', $tags, true) && (strpos($text, 'ingredient') !== false || strpos($text, 'fresh') !== false || strpos($text, 'fast') !== false || strpos($text, 'delivery') !== false || strpos($text, 'oven') !== false)) {
						$found = true;
						break;
					}
				}
				if (preg_match('/(offer|promo|deal|discount|banner)/i', $sec_name)) {
					if (strpos($text, 'offer') !== false || strpos($text, 'discount') !== false || strpos($text, 'free') !== false || strpos($text, 'price of one') !== false || strpos($text, '% off') !== false) {
						$found = true;
						break;
					}
				}
				if (preg_match('/(location|hour|address|map|find\s*us)/i', $sec_name)) {
					if (in_array('pl_address', $tags, true) || in_array('pl_phone', $tags, true) || in_array('pl_google_maps', $tags, true) || strpos($text, 'opening hours') !== false || strpos($text, 'mon-') !== false || strpos($text, 'pm') !== false || strpos($text, 'am') !== false) {
						$found = true;
						break;
					}
				}
				if (preg_match('/(cta|final|order\s*now|hungry)/i', $sec_name)) {
					if (in_array('pl_btn', $tags, true) && (strpos($text, 'order') !== false || strpos($text, 'hungry') !== false || strpos($text, 'now') !== false)) {
						$found = true;
						break;
					}
				}
			}

			if (!$found) {
				$missing[] = $sec;
			}
		}

		return $missing;
	}

	/**
	 * Theme-builder kinds that must never expand into a marketing homepage.
	 */
	public function is_scoped_kind($kind) {
		return in_array($kind, array('header', 'footer', '404', 'blog', 'single', 'search', 'about', 'contact', 'service', 'pricing', 'faq', 'section'), true);
	}

	/**
	 * Header / footer / 404 / archive / single — saved as Theme Builder templates.
	 */
	public function is_theme_template_kind($kind) {
		return in_array($kind, array('header', 'footer', '404', 'blog', 'single', 'search'), true);
	}

	/**
	 * Map a request kind onto a Pagelayer Theme Builder template type + display conditions.
	 *
	 * @return array{type:string,title:string,conditions:array}|null
	 */
	public function theme_template_spec($kind, $prompt = '') {
		$site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
		if ($site === '') {
			$site = 'Site';
		}

		$specs = array(
			'header' => array(
				'type'       => 'header',
				'title'      => $site . ' Header',
				'conditions' => array(array('type' => 'include', 'template' => '', 'sub_template' => '', 'id' => '')),
			),
			'footer' => array(
				'type'       => 'footer',
				'title'      => $site . ' Footer',
				'conditions' => array(array('type' => 'include', 'template' => '', 'sub_template' => '', 'id' => '')),
			),
			'404' => array(
				'type'       => 'single',
				'title'      => $site . ' 404',
				'conditions' => array(array('type' => 'include', 'template' => 'singular', 'sub_template' => '404', 'id' => '')),
			),
			'blog' => array(
				'type'       => 'archive',
				'title'      => $site . ' Blog Archive',
				'conditions' => array(array('type' => 'include', 'template' => 'archives', 'sub_template' => '', 'id' => '')),
			),
			'single' => array(
				'type'       => 'single',
				'title'      => $site . ' Single Post',
				'conditions' => array(array('type' => 'include', 'template' => 'singular', 'sub_template' => '', 'id' => '')),
			),
			'search' => array(
				'type'       => 'archive',
				'title'      => $site . ' Search Results',
				'conditions' => array(array('type' => 'include', 'template' => 'archives', 'sub_template' => 'search', 'id' => '')),
			),
		);

		if (!isset($specs[$kind])) {
			return null;
		}

		$spec = $specs[$kind];
		$topic = $this->infer_page_topic($prompt);
		if ($topic !== '' && $topic !== 'this business' && strlen($topic) < 60) {
			$spec['title'] = $site . ' ' . ucwords($kind === '404' ? '404' : str_replace('_', ' ', $kind));
		}

		return $spec;
	}

	/**
	 * What the user actually asked to build. Theme templates (header, footer, 404)
	 * and inner pages must not be treated as marketing homepages.
	 *
	 * @return string home|header|footer|404|blog|single|search|about|contact|service|pricing|faq|section
	 */
	public function request_kind($prompt) {
		$hay = strtolower(trim((string) $prompt));
		if ($hay === '') {
			return 'section';
		}

		// Theme-builder chrome first — never let a footer/header request become a homepage.
		if ($this->wants_header($prompt)) {
			return 'header';
		}
		if ($this->wants_footer($prompt)) {
			return 'footer';
		}

		if (strpos($hay, '404') !== false || preg_match('/\b(not\s*found|error\s*page|error\s*template)\b/i', $hay)) {
			return '404';
		}
		if (preg_match('/\b(single\s+post|single\s+blog|blog\s+post\s+template|post\s+template|single\s+template)\b/i', $hay)) {
			return 'single';
		}
		if (preg_match('/\b(blog\s*page|blog\s*template|blog\s*archive|posts?\s+listing|archive\s*(page|template)|news\s*page)\b/i', $hay)
			|| preg_match('/\b(make|create|build|design)\b.{0,50}\bblog\b/i', $hay)) {
			return 'blog';
		}
		if (preg_match('/\bsearch\s*(page|template|results)\b/i', $hay)) {
			return 'search';
		}
		if (preg_match('/\b(contact\s*(page|template|us)|get\s+in\s+touch\s+page)\b/i', $hay)) {
			return 'contact';
		}
		if (preg_match('/\b(about\s*(page|template|us)|our\s+story\s+page)\b/i', $hay)) {
			return 'about';
		}
		if (preg_match('/\b(pricing\s*(page|template)|plans?\s+page)\b/i', $hay)) {
			return 'pricing';
		}
		if (preg_match('/\b(faq\s*(page|template)|frequently\s+asked)\b/i', $hay)) {
			return 'faq';
		}
		if (preg_match('/\b(service\s*page|services\s*page|service\s*template|services\s*template)\b/i', $hay)
			|| preg_match('/\b(make|create|build|design)\b.{0,40}\bservices?\s+page\b/i', $hay)) {
			return 'service';
		}

		// Numbered multi-section lists are homepage requests — but never after a scoped kind above.
		$requested_sections = $this->extract_requested_sections($prompt);
		if (count($requested_sections) >= 2) {
			return 'home';
		}

		if (preg_match('/\b(home\s*page|homepage|landing\s*page|front\s*page)\b/i', $hay)) {
			return 'home';
		}
		if (preg_match('/\b(full\s*site|entire\s*site|whole\s*site|web\s*site|website)\b/i', $hay)
			&& !preg_match('/\b(404|blog|service|about|contact|pricing|faq|header|footer|template)\b/i', $hay)) {
			return 'home';
		}
		if (preg_match('/\b(make|create|build|design|generate)\b.{0,40}\b(site|homepage|landing)\b/i', $hay)
			&& !preg_match('/\b(404|blog|service|about|contact|pricing|faq|header|footer|template)\b/i', $hay)) {
			return 'home';
		}
		// "make siteseo.com" / "build https://loginizer.com" / a bare domain.
		if (preg_match('/\b(?:https?:\/\/)?(?:www\.)?[a-z0-9][a-z0-9-]{1,61}\.(com|io|net|org|app|dev|co|ai|seo|in|us)\b/i', $hay)
			&& !preg_match('/\b(404|blog|service|about|contact|pricing|faq|header|footer|template)\b/i', $hay)) {
			return 'home';
		}

		return 'section';
	}

	public function is_full_page_request($prompt) {
		return $this->request_kind($prompt) === 'home' || count($this->extract_requested_sections($prompt)) >= 2;
	}

	public function inner_page_brief($kind, $prompt) {
		$topic = $this->infer_page_topic($prompt);
		$briefs = array(
			'header'  => "Build ONLY a site HEADER theme template for: {$topic}. One slim full-width bar: brand/logo + primary navigation (pl_wp_menu) + optional CTA. This is Theme Builder chrome, not a page. No hero, no features, no services, no footer.",
			'footer'  => "Build ONLY a site FOOTER theme template for: {$topic}. A real multi-column footer (brand/about, useful links, contact, social) plus a copyright bar. This is Theme Builder chrome, not a page. No hero photo, no features, no services, no testimonials, no pricing.",
			'404'     => "Build ONLY a 404 Not Found theme template for: {$topic}. A finished error screen: large 404 code, headline, short explanation, search, and Back to Home button. Optional 3–4 text links (Home, Blog, Contact). Absolutely NO feature boxes, services, testimonials, or marketing sections. Do NOT make a photo-hero with only a background image and one line of text.",
			'blog'    => "Build ONLY a blog listing / archive theme template for: {$topic}. Invent the composition. Use catalog post widgets if available. Do NOT build a marketing homepage.",
			'single'  => "Build ONLY a single blog-post theme template for: {$topic}. Invent the composition. Do NOT build a homepage.",
			'search'  => "Build ONLY a search-results theme template. Invent the composition. No marketing homepage sections.",
			'about'   => "Build ONLY an About page/section for: {$topic}. Invent the composition. Do NOT add a full homepage or extra unrequested sections.",
			'contact' => "Build ONLY a Contact page/section for: {$topic}. Invent the composition. No extra marketing sections unless asked.",
			'service' => "Build ONLY a Services page/section for: {$topic}. Invent a layout that fits this domain. No extra marketing bands unless the user asked.",
			'pricing' => "Build ONLY a Pricing page/section for: {$topic}. Invent the composition. Not a full homepage.",
			'faq'     => "Build ONLY an FAQ page/section for: {$topic}. Invent the composition. Not a full homepage.",
			'section' => "Build exactly the section or layout the user described. Do not add extra unrequested sections (features, services, testimonials, pricing).",
			'home'    => "Build a complete branded homepage/theme for: {$topic}. Invent original architecture for THIS business — do not reuse a canned skeleton.",
		);
		return isset($briefs[$kind]) ? $briefs[$kind] : $briefs['section'];
	}

	public function infer_page_topic($prompt) {
		$t = strtolower((string) $prompt);
		$t = preg_replace('/\b(please|make|create|build|design|generate|want|need|a|an|the|my|our|for|with|using|home\s*page|homepage|landing\s*page|full\s*page|web\s*site|website|web\s*page|webpage|site|page)\b/i', ' ', $t);
		$t = trim(preg_replace('/\s+/', ' ', $t));
		if ($t === '' || strlen($t) < 3) {
			$t = trim((string) $prompt);
		}
		return $t !== '' ? $t : 'this business';
	}

	public function niche_from_prompt($prompt) {
		return $this->industry_from_prompt($prompt);
	}

	/**
	 * Lightweight industry guess kept for compatibility. Build-with-AI no longer
	 * uses this to pick a canned layout — the model invents architecture.
	 *
	 * @return string restaurant|saas|service|generic
	 */
	public function industry_from_prompt($prompt) {
		$hay = strtolower((string) $prompt);
		if (preg_match('/pizza|pizzeria|restaurant|trattoria|osteria|cafe|caf[eé]|bakery|burger|food|kitchen|diner|bistro|bar\b|coffee|hospitality|hotel|menu|chef|cuisine|steakhouse|sushi|taco|noodle|brewery/i', $hay)) {
			return 'restaurant';
		}
		if (preg_match('/loginizer|plugin|saas|software|app\b|security|hosting|vpn|backup|wordpress|cyber|brute.?force|2fa|captcha|siteseo|seo\b|analytics|api\b|devtools|cloud|tech\b|startup/i', $hay)) {
			return 'saas';
		}
		if (preg_match('/gym|fitness|yoga|trainer|crossfit|wellness|plumber|dentist|lawyer|attorney|salon|clinic|doctor|consultant|agency|studio|marketing|branding|freelance|cleaning|repair|hvac|roofer|electrician|local\s+business|contractor/i', $hay)) {
			return 'service';
		}
		return 'generic';
	}

	/**
	 * @deprecated Layout variation is no longer prescribed. The model invents composition.
	 */
	public function layout_variation($kind = 'home', $industry = 'generic') {
		return array(
			'hero' => 'invent an original opening for this brand',
			'grid' => 'invent an original content composition for this brand',
		);
	}

	/**
	 * @deprecated Industry wireframes are no longer prescribed. The model invents architecture.
	 */
	public function industry_wireframe($industry) {
		return "Invent the information architecture for THIS brand. Do not reuse a canned skeleton.\n";
	}

	public function parse_plan($raw) {
		$data = $this->parse_json($raw);
		if (!is_array($data)) {
			return array('widgets' => array(), 'sections' => array(), 'layout' => array(), 'palette' => array(), 'fonts' => array(), 'summary' => '', 'mood' => '');
		}

		$widgets = array();
		if (!empty($data['widgets']) && is_array($data['widgets'])) {
			$catalog = $this->get_widget_catalog();
			foreach ($data['widgets'] as $tag) {
				$tag = $this->normalize_tag((string) $tag);
				if (isset($catalog[$tag])) {
					$widgets[] = $tag;
				}
			}
		}

		return array(
			'widgets'  => array_values(array_unique($widgets)),
			'sections' => (!empty($data['sections']) && is_array($data['sections'])) ? array_values($data['sections']) : array(),
			'layout'   => (!empty($data['layout']) && is_array($data['layout'])) ? array_values($data['layout']) : array(),
			'palette'  => (!empty($data['palette']) && is_array($data['palette'])) ? $data['palette'] : array(),
			'fonts'    => (!empty($data['fonts']) && is_array($data['fonts'])) ? $data['fonts'] : array(),
			'summary'  => !empty($data['summary']) ? (string) $data['summary'] : '',
			'mood'     => !empty($data['mood']) ? (string) $data['mood'] : '',
		);
	}

	public function extract_globals_from_json($raw) {
		$data = is_array($raw) ? $raw : $this->parse_json($raw);
		if (!is_array($data)) {
			return array('palette' => array(), 'fonts' => array());
		}
		return array(
			'palette' => (!empty($data['palette']) && is_array($data['palette'])) ? $data['palette'] : array(),
			'fonts'   => (!empty($data['fonts']) && is_array($data['fonts'])) ? $data['fonts'] : array(),
		);
	}

	public function parse_layout($raw) {
		$data = $this->parse_json($raw);
		if (!is_array($data)) {
			return null;
		}

		if (isset($data['nodes']) && is_array($data['nodes'])) {
			$data = $data['nodes'];
		} elseif (isset($data['layout']) && is_array($data['layout'])) {
			$data = $data['layout'];
		} elseif (isset($data['pagelayer_data']) && is_array($data['pagelayer_data'])) {
			$data = $data['pagelayer_data'];
		}

		if (isset($data['tag'])) {
			$data = array($data);
		}

		if (!is_array($data) || empty($data)) {
			return null;
		}

		$clean = array();
		foreach ($data as $node) {
			if (!is_array($node) || empty($node['tag'])) {
				continue;
			}
			$clean[] = $node;
		}

		return empty($clean) ? null : array_values($clean);
	}

	public function normalize_tag($tag) {
		$tag = strtolower(trim((string) $tag));
		$map = array(
			'container' => 'pl_row',
			'section'   => 'pl_row',
			'row'       => 'pl_row',
			'column'    => 'pl_col',
			'col'       => 'pl_col',
			'heading'   => 'pl_heading',
			'title'     => 'pl_heading',
			'text'      => 'pl_text',
			'button'    => 'pl_btn',
			'btn'       => 'pl_btn',
			'image'     => 'pl_image',
			'iconbox'   => 'pl_iconbox',
		);
		$map = apply_filters('pagelayer_ai_tag_aliases', $map);
		if (isset($map[$tag])) {
			return $map[$tag];
		}
		if (strpos($tag, 'pagelayer_') === 0) {
			return str_replace('pagelayer_', 'pl_', $tag);
		}
		if ($tag !== '' && strpos($tag, 'pl_') !== 0) {
			return 'pl_' . $tag;
		}
		return $tag;
	}

	/**
	 * True when a page-level request came back as a fragment or unstyled sketch.
	 * The controller retries the model — it does not inject a hardcoded theme.
	 */
	public function is_thin_layout($nodes, $kind = 'home', $requested_sections = array()) {
		if (!is_array($nodes) || empty($nodes)) {
			return true;
		}

		$stats = $this->layout_stats($nodes);

		if (!empty($requested_sections)) {
			if ($stats['rows'] < count($requested_sections)) {
				return true;
			}
			$missing = $this->find_missing_sections($nodes, $requested_sections);
			if (!empty($missing)) {
				return true;
			}
		}

		$min_rows   = 2;
		$min_leaves = 4;
		$min_styled = 3;
		$min_tags   = 4;

		if ($kind === 'home') {
			$min_rows   = 5;
			$min_leaves = 10;
			$min_styled = 8;
			$min_tags   = 8;
		} elseif ($kind === 'footer') {
			$min_rows   = 2;
			$min_leaves = 6;
			$min_styled = 4;
			$min_tags   = 5;
		} elseif ($kind === 'header') {
			$min_rows   = 1;
			$min_leaves = 2;
			$min_styled = 2;
			$min_tags   = 3;
		} elseif (in_array($kind, array('about', 'service', 'contact', 'pricing', 'faq', 'blog'), true)) {
			$min_rows   = 2;
			$min_leaves = 5;
			$min_styled = 3;
			$min_tags   = 4;
		} elseif (in_array($kind, array('404', 'search', 'single'), true)) {
			$min_rows   = 1;
			$min_leaves = 3;
			$min_styled = 3;
			$min_tags   = 4;
		} else {
			return count($nodes) < 1;
		}

		return ($stats['rows'] < $min_rows)
			|| ($stats['leaves'] < $min_leaves)
			|| ($stats['styled'] < $min_styled)
			|| ($stats['tags'] < $min_tags);
	}

	public function layout_stats($nodes) {
		$rows   = 0;
		$leaves = 0;
		$styled = 0;
		$tags   = array();

		$walk = function ($list) use (&$walk, &$rows, &$leaves, &$styled, &$tags) {
			foreach ((array) $list as $node) {
				if (!is_array($node) || empty($node['tag'])) {
					continue;
				}
				$tag        = $this->normalize_tag($node['tag']);
				$tags[$tag] = true;
				if ($this->is_row_tag($tag)) {
					$rows++;
				}
				$has_children = isset($node['content']) && is_array($node['content']) && !empty($node['content']);
				if (!$has_children && !$this->is_col_tag($tag) && !$this->is_row_tag($tag)) {
					$leaves++;
				}
				if (!empty($node['attrs']) && is_array($node['attrs'])) {
					foreach ($node['attrs'] as $k => $v) {
						if ($v === '' || $v === null || $k === 'pagelayer-id' || $k === 'col' || $k === 'col_gap' || $k === 'stretch' || $k === 'widget_space') {
							continue;
						}
						if (preg_match('/(color|typo|padding|shadow|bg_|border|align|icon|image|heading|font_)/i', (string) $k)) {
							$styled++;
							break;
						}
					}
				}
				if ($has_children) {
					$walk($node['content']);
				}
			}
		};
		$walk($nodes);

		return array(
			'rows'   => $rows,
			'leaves' => $leaves,
			'styled' => $styled,
			'tags'   => count($tags),
		);
	}

	private function detect_section_types($nodes) {
		$have = array();
		$walk = function ($list) use (&$walk, &$have) {
			foreach ((array) $list as $node) {
				if (!is_array($node)) {
					continue;
				}
				if (!empty($node['section']) && is_string($node['section'])) {
					$have[strtolower($node['section'])] = true;
				}
				$tag = !empty($node['tag']) ? $this->normalize_tag($node['tag']) : '';
				if ($tag === 'pl_iconbox' || $tag === 'pl_service') {
					$have['features'] = true;
				} elseif ($tag === 'pl_testimonial') {
					$have['testimonials'] = true;
				} elseif ($tag === 'pl_counter') {
					$have['stats'] = true;
				} elseif ($tag === 'pl_accordion') {
					$have['faq'] = true;
				} elseif ($tag === 'pl_pricing') {
					$have['pricing'] = true;
				} elseif (in_array($tag, array('pl_address', 'pl_phone', 'pl_email', 'pl_contact'), true)) {
					$have['contact'] = true;
				} elseif ($tag === 'pl_heading' && isset($node['content']) && is_string($node['content']) && stripos($node['content'], '<h1') !== false) {
					$have['hero'] = true;
				}
				if (isset($node['content']) && is_array($node['content'])) {
					$walk($node['content']);
				}
			}
		};
		$walk($nodes);
		return $have;
	}

	public function wants_header($prompt) {
		$hay = strtolower(trim((string) $prompt));
		if ($hay === '') {
			return false;
		}
		return (bool) (preg_match('/\b(create|make|build|design|add|generate)\b.{0,50}\b(header|nav\s*bar|navbar|navigation\s*bar|site\s*menu|top\s*menu|main\s*menu)\b/i', $hay)
			|| preg_match('/^(header|nav\s*bar|navbar|navigation\s*bar|top\s*menu|main\s*menu)(\s+for|\s+of|\s+with|\s*$)/i', $hay)
			|| preg_match('/\bheader\s+(theme\s+)?(template|layout|section|bar)\b/i', $hay)
			|| preg_match('/\b(theme\s+)?template\b.{0,30}\bheader\b/i', $hay));
	}

	public function wants_footer($prompt) {
		$hay = strtolower(trim((string) $prompt));
		if ($hay === '') {
			return false;
		}
		return (bool) (preg_match('/\b(create|make|build|design|add|generate)\b.{0,50}\b(footer|footer\s*bar|copyright\s*bar|site\s*footer|bottom\s*bar)\b/i', $hay)
			|| preg_match('/^(footer|site\s*footer|bottom\s*bar)(\s+for|\s+of|\s+with|\s*$)/i', $hay)
			|| preg_match('/\bfooter\s+(theme\s+)?(template|layout|section|page|bar)\b/i', $hay)
			|| preg_match('/\b(theme\s+)?template\b.{0,30}\bfooter\b/i', $hay));
	}

	/**
	 * True only when the user explicitly asked for a features/services SECTION —
	 * not when those words appear as the brand/industry ("plumbing service").
	 */
	public function explicitly_wants_marketing_band($prompt, $band = 'features') {
		$hay = strtolower(trim((string) $prompt));
		if ($hay === '') {
			return false;
		}
		if ($band === 'features') {
			if (preg_match('/\b(header|footer|404)\b/i', $hay)) {
				return false;
			}
			return (bool) (preg_match('/\b(features?\s+(section|grid|cards?|row|boxes))\b/i', $hay)
				|| preg_match('/\b(icon\s*box(es)?|benefit\s+cards?)\b/i', $hay)
				|| preg_match('/\b(add|include|with|plus)\s+(features?|benefits?)\b/i', $hay));
		}
		if ($band === 'services') {
			if (preg_match('/\b(header|footer|404)\b/i', $hay)) {
				return false;
			}
			return (bool) (preg_match('/\b(services?\s+(section|grid|cards?|row|page|template))\b/i', $hay)
				|| preg_match('/\b(add|include|with|plus)\s+services?\b/i', $hay));
		}
		if ($band === 'testimonials') {
			return (bool) preg_match('/\b(testimonials?|reviews?|ratings?|what\s*(our\s*)?clients\s*say)\b/i', $hay);
		}
		if ($band === 'pricing') {
			return (bool) preg_match('/\b(pricing|plans?\s+page|packages?)\b/i', $hay);
		}
		return false;
	}

	/**
	 * Page-kind scope only. The model invents architecture, widgets, and visuals.
	 */
	public function structure_brief($kind = 'section', $prompt = '') {
		$briefs = array(
			'header'  => "Design a finished site HEADER theme template (exactly ONE slim [pl_row], typical padding 16–28px top/bottom). Columns: brand/logo (pl_heading or pl_image) + Primary Menu (pl_wp_menu, layout=horizontal) + optional CTA button. Full-width color bar (ele_bg_type=color), NOT a photo hero. No features, services, testimonials, or page sections.",
			'footer'  => "Design a finished site FOOTER theme template — not a page and not a photo hero. TWO rows: (1) a 3- or 4-column row (col 3+3+3+3 or 4+4+4) with Brand/about, Useful links (pl_list + pl_list_item), Contact (address/phone/email), and Social (pl_social_grp > pl_social); (2) a full-width copyright bar (© year + site name, Privacy, Terms). Use ele_bg_type=color (dark brand bar). Do NOT use a background image as the only design. Do NOT add features, services, icon boxes, testimonials, pricing, or any extra marketing section.",
			'404'     => "Design a finished 404 Not Found theme template. One centered content row (min-height ~70vh, color background — not a random stock photo hero): large 404 number as h1, short headline, 1–2 sentence explanation, pl_search, and a Back to Home pl_btn. Optional second row of 3–4 text/button links (Home, Blog, Contact) — NOT icon boxes or service cards. Absolutely NO features, services, testimonials, pricing, or extra marketing bands. A row that is only a background image plus one heading is a failed 404.",
			'blog'    => "Design a blog/archive theme template for this brand. Invent the composition. Use catalog post widgets if available. No homepage marketing stack.",
			'single'  => "Design a single-post theme template. Invent the composition. No homepage.",
			'search'  => "Design a search-results theme template. Invent the composition. No homepage.",
			'service' => "Design ONLY the requested services layout. Do not wrap it in a full homepage or add unrelated sections unless asked.",
			'contact' => "Design ONLY the requested contact layout (e.g. form, contact info). No extra marketing sections unless asked.",
			'about'   => "Design ONLY the requested about section/page. Do not append a full homepage.",
			'pricing' => "Design ONLY the requested pricing layout. Not a full homepage.",
			'faq'     => "Design ONLY the requested FAQ layout. Not a full homepage.",
			'home'    => "Design a complete branded homepage/theme for this product. Invent original architecture — do not reuse a canned skeleton.",
			'section' => "Design ONLY the requested section as finished UI. Do not add extra sections (such as feature icon boxes, services, or testimonials) unless explicitly requested.",
		);

		$requested_sections = $this->is_scoped_kind($kind) ? array() : $this->extract_requested_sections($prompt);
		if (!empty($requested_sections) && !$this->is_scoped_kind($kind)) {
			$text = "The user has specified an explicit list of " . count($requested_sections) . " sections that MUST all be created in this layout:\n";
			foreach ($requested_sections as $i => $sec) {
				$num = $i + 1;
				$desc = !empty($sec['desc']) ? ": " . $sec['desc'] : '';
				$text .= "{$num}. \"{$sec['name']}\"{$desc}\n";
			}
			$text .= "CRITICAL: You MUST build every single one of these " . count($requested_sections) . " sections as a dedicated [pl_row] in your output. Do NOT omit or truncate any section.";
		} else {
			$text = isset($briefs[$kind]) ? $briefs[$kind] : $briefs['section'];
		}
		$text .= "\nYOU invent rows, column splits (they must sum to 12), widget mix, copy, photos, and palette for THIS brand.";
		if (in_array($kind, array('header', 'footer', '404'), true)) {
			$text .= " Use color row backgrounds (ele_bg_type=color), not a photo hero. High contrast.";
		} else {
			$text .= " Every section row keeps 50px to 60px padding-top AND padding-bottom (never 0 top on a following row). 20px left and right. High contrast.";
		}
		$text .= " Social icons: pl_social_grp with children in a horizontal row.";
		if (!$this->wants_header($prompt)) {
			$text .= "\nCRITICAL: Do NOT create a website header/navigation bar/logo row. In Pagelayer, headers are handled by Theme Builder templates.";
		}
		if (!$this->wants_footer($prompt)) {
			$text .= "\nCRITICAL: Do NOT create a website footer/copyright bar/bottom links row. In Pagelayer, footers are handled by Theme Builder templates.";
		}

		return apply_filters('pagelayer_ai_structure_brief', $text, $kind, $this, $prompt);
	}

	public function uniqueness_seed() {
		return $this->structure_brief('home');
	}

	public function finalize_nodes($nodes, $prompt = '') {
		if (!is_array($nodes) || empty($nodes)) {
			return array();
		}

		$this->apply_ai_globals(array(), array());

		// Remap guessed attr names onto the live schema BEFORE markup defaults
		// are filled, otherwise a default (service_icon, id, ...) occupies the
		// real key and the user's icon/img/heading value is dropped.
		$nodes = $this->remap_tree($nodes);
		$nodes = $this->normalize_ai_nodes($nodes);
		$nodes = $this->scrub_unwanted_headers_and_footers($nodes, $prompt);
		$nodes = $this->scrub_unwanted_extra_sections($nodes, $prompt);
		$nodes = $this->normalize_theme_chrome_rows($nodes, $prompt);
		$nodes = $this->reflow_card_grids($nodes);
		$nodes = $this->normalize_column_gaps($nodes);
		$nodes = $this->sanitize_nodes($nodes);
		$nodes = $this->scrub_placeholders($nodes);
		$nodes = $this->normalize_badges_and_ribbons($nodes);
		$nodes = $this->normalize_social_groups($nodes);
		$nodes = $this->apply_theme_visuals($nodes);
		$nodes = $this->normalize_badges_and_ribbons($nodes);
		$kind  = $this->request_kind($prompt);
		if (in_array($kind, array('header', 'footer', '404'), true)) {
			$nodes = $this->normalize_theme_chrome_rows($nodes, $prompt);
		} else {
			$nodes = $this->enforce_section_padding($nodes);
			$nodes = $this->normalize_row_stack_spacing($nodes);
		}
		return $nodes;
	}

	/**
	 * Remove top navbar/logo rows and bottom copyright/footer rows if not explicitly asked
	 */
	public function scrub_unwanted_headers_and_footers($nodes, $prompt = '') {
		if (!is_array($nodes) || count($nodes) <= 1) {
			return $nodes;
		}

		$wants_hdr = $this->wants_header($prompt);
		$wants_ftr = $this->wants_footer($prompt);

		if ($wants_hdr && $wants_ftr) {
			return $nodes;
		}

		$rows = array_values($nodes);

		// Check first row for header navbar
		if (!$wants_hdr && count($rows) > 1) {
			if ($this->is_header_like_row($rows[0])) {
				array_shift($rows);
			}
		}

		// Check last row for footer/copyright
		if (!$wants_ftr && count($rows) > 1) {
			$last_idx = count($rows) - 1;
			if ($this->is_footer_like_row($rows[$last_idx])) {
				array_pop($rows);
			}
		}

		return array_values($rows);
	}

	/**
	 * Remove unrequested feature, testimonial, or extra marketing rows when the user
	 * asked for a 404 page or a specific single section/template.
	 */
	public function scrub_unwanted_extra_sections($nodes, $prompt = '') {
		if (!is_array($nodes) || empty($nodes)) {
			return $nodes;
		}

		$kind = $this->request_kind($prompt);
		$requested_sections = $this->is_scoped_kind($kind) ? array() : $this->extract_requested_sections($prompt);

		// If user asked for a full homepage or explicitly listed 2+ sections, keep them
		if ($kind === 'home' || count($requested_sections) >= 2) {
			return $nodes;
		}

		$wants_features     = $this->explicitly_wants_marketing_band($prompt, 'features')
			|| $this->explicitly_wants_marketing_band($prompt, 'services');
		$wants_testimonials = $this->explicitly_wants_marketing_band($prompt, 'testimonials');
		$wants_pricing      = $this->explicitly_wants_marketing_band($prompt, 'pricing');

		$clean = array();

		$is_marketing_row = function ($row_json) {
			return (strpos($row_json, 'pl_iconbox') !== false
				|| strpos($row_json, 'pl_service') !== false
				|| strpos($row_json, 'pl_testimonial') !== false
				|| strpos($row_json, 'pl_pricing') !== false
				|| strpos($row_json, 'pl_counter') !== false
				|| strpos($row_json, 'pl_flipbox') !== false);
		};

		if ($kind === 'footer') {
			foreach ($nodes as $row) {
				if (!is_array($row) || empty($row['tag']) || !$this->is_row_tag($row['tag'])) {
					$clean[] = $row;
					continue;
				}
				$row_json = strtolower(wp_json_encode($row));
				if ($is_marketing_row($row_json) && !$wants_features && !$wants_testimonials && !$wants_pricing) {
					continue;
				}
				if ($this->is_header_like_row($row)) {
					continue;
				}
				$clean[] = $row;
			}
			return !empty($clean) ? array_values($clean) : $nodes;
		}

		if ($kind === 'header') {
			foreach ($nodes as $row) {
				if (!is_array($row) || empty($row['tag']) || !$this->is_row_tag($row['tag'])) {
					$clean[] = $row;
					continue;
				}
				$row_json = strtolower(wp_json_encode($row));
				if ($is_marketing_row($row_json) || $this->is_footer_like_row($row)) {
					continue;
				}
				$clean[] = $row;
			}
			// A header is one slim bar — keep the first remaining row.
			if (count($clean) > 1) {
				$first = array();
				foreach ($clean as $row) {
					if (is_array($row) && !empty($row['tag']) && $this->is_row_tag($row['tag'])) {
						$first[] = $row;
						break;
					}
					$first[] = $row;
				}
				$clean = $first;
			}
			return !empty($clean) ? array_values($clean) : $nodes;
		}

		if ($kind === '404') {
			$found_404_row = false;
			foreach ($nodes as $row) {
				if (!is_array($row) || empty($row['tag']) || !$this->is_row_tag($row['tag'])) {
					$clean[] = $row;
					continue;
				}
				$row_json = strtolower(wp_json_encode($row));
				$is_feature_row     = (strpos($row_json, 'pl_iconbox') !== false || strpos($row_json, 'pl_service') !== false);
				$is_testimonial_row = (strpos($row_json, 'pl_testimonial') !== false || strpos($row_json, 'pl_stars') !== false);
				$is_pricing_row     = (strpos($row_json, 'pl_pricing') !== false);
				$has_404_content    = (strpos($row_json, '404') !== false || strpos($row_json, 'not found') !== false || strpos($row_json, 'not-found') !== false || strpos($row_json, 'error') !== false || strpos($row_json, 'oops') !== false || strpos($row_json, 'pl_search') !== false);

				if ($is_feature_row && !$wants_features) {
					continue;
				}
				if ($is_testimonial_row && !$wants_testimonials) {
					continue;
				}
				if ($is_pricing_row && !$wants_pricing) {
					continue;
				}
				if ($found_404_row && !$has_404_content && !$wants_features && !$wants_testimonials) {
					continue;
				}

				if ($has_404_content) {
					$found_404_row = true;
				}

				$clean[] = $row;
			}

			return !empty($clean) ? array_values($clean) : $nodes;
		}

		// For other single-section / specific template requests:
		foreach ($nodes as $row) {
			if (!is_array($row) || empty($row['tag']) || !$this->is_row_tag($row['tag'])) {
				$clean[] = $row;
				continue;
			}
			$row_json = strtolower(wp_json_encode($row));
			$is_feature_row     = (strpos($row_json, 'pl_iconbox') !== false || strpos($row_json, 'pl_service') !== false);
			$is_testimonial_row = (strpos($row_json, 'pl_testimonial') !== false);
			$is_pricing_row     = (strpos($row_json, 'pl_pricing') !== false);

			if ($is_feature_row && !$wants_features && $kind !== 'service') {
				continue;
			}
			if ($is_testimonial_row && !$wants_testimonials) {
				continue;
			}
			if ($is_pricing_row && !$wants_pricing && $kind !== 'pricing') {
				continue;
			}

			$clean[] = $row;
		}

		return !empty($clean) ? array_values($clean) : $nodes;
	}

	/**
	 * Header/footer/404 must be designed chrome, not a photo-hero with one line of text.
	 */
	public function normalize_theme_chrome_rows($nodes, $prompt = '') {
		if (!is_array($nodes) || empty($nodes)) {
			return $nodes;
		}

		$kind = $this->request_kind($prompt);
		if (!in_array($kind, array('header', 'footer', '404'), true)) {
			return $nodes;
		}

		$asked_for_image = (bool) preg_match('/\b(background\s+image|bg\s+image|hero\s+image|photo\s+background)\b/i', (string) $prompt);

		foreach ($nodes as &$node) {
			if (!is_array($node) || empty($node['tag']) || !$this->is_row_tag($node['tag'])) {
				continue;
			}
			if (!isset($node['attrs']) || !is_array($node['attrs'])) {
				$node['attrs'] = array();
			}

			$bg_type = isset($node['attrs']['ele_bg_type']) ? (string) $node['attrs']['ele_bg_type'] : '';
			if (!$asked_for_image && ($bg_type === 'image' || !empty($node['attrs']['ele_bg_img']))) {
				$node['attrs']['ele_bg_type']  = 'color';
				if (empty($node['attrs']['ele_bg_color'])) {
					$node['attrs']['ele_bg_color'] = ($kind === '404') ? '$bg' : '$primary';
				}
				unset($node['attrs']['ele_bg_img'], $node['attrs']['ele_bg_img_size'], $node['attrs']['ele_bg_attachment']);
			} elseif ($bg_type === '' || $bg_type === 'color') {
				$node['attrs']['ele_bg_type'] = 'color';
				if (empty($node['attrs']['ele_bg_color'])) {
					$node['attrs']['ele_bg_color'] = ($kind === 'header') ? '$surface' : '$primary';
				}
			}

			if ($kind === 'header') {
				if (empty($node['attrs']['ele_padding'])) {
					$node['attrs']['ele_padding'] = '18px,24px,18px,24px';
				}
				$node['attrs']['stretch'] = 'stretch';
			} elseif ($kind === 'footer') {
				if (empty($node['attrs']['ele_padding'])) {
					$node['attrs']['ele_padding'] = $this->is_footer_like_row($node)
						? '18px,24px,18px,24px'
						: '56px,24px,40px,24px';
				}
				$node['attrs']['stretch'] = 'stretch';
			} elseif ($kind === '404') {
				if (empty($node['attrs']['ele_padding'])) {
					$node['attrs']['ele_padding'] = '80px,24px,80px,24px';
				}
				if (empty($node['attrs']['content_pos'])) {
					$node['attrs']['content_pos'] = 'center';
				}
				$node['attrs']['stretch'] = 'stretch';
			}

			if (empty($node['attrs']['col_gap'])) {
				$node['attrs']['col_gap'] = '20';
			}
		}
		unset($node);

		if ($kind === 'header') {
			$nodes = $this->ensure_header_nav($nodes);
		}

		return $nodes;
	}

	/**
	 * Bind an existing WP menu to a header that is missing pl_wp_menu.
	 */
	public function ensure_header_nav($nodes) {
		if (!is_array($nodes)) {
			return $nodes;
		}

		$has_menu = false;
		$walk = function ($list) use (&$walk, &$has_menu) {
			foreach ((array) $list as $node) {
				if (!is_array($node) || empty($node['tag'])) {
					continue;
				}
				if ($this->normalize_tag($node['tag']) === 'pl_wp_menu') {
					$has_menu = true;
					return;
				}
				if (isset($node['content']) && is_array($node['content'])) {
					$walk($node['content']);
				}
			}
		};
		$walk($nodes);
		if ($has_menu) {
			return $nodes;
		}

		$catalog = $this->get_widget_catalog();
		if (!isset($catalog['pl_wp_menu'])) {
			return $nodes;
		}

		$menu_id = 0;
		$menus = function_exists('wp_get_nav_menus') ? wp_get_nav_menus(array('number' => 1)) : array();
		if (!empty($menus) && !is_wp_error($menus)) {
			$menu_id = (int) $menus[0]->term_id;
		}
		if ($menu_id <= 0) {
			$locations = get_nav_menu_locations();
			if (!empty($locations) && is_array($locations)) {
				$menu_id = (int) reset($locations);
			}
		}

		$menu_node = array(
			'tag'   => 'pl_wp_menu',
			'attrs' => array(
				'nav_list'        => $menu_id > 0 ? (string) $menu_id : '',
				'layout'          => 'horizontal',
				'align'           => 'right',
				'drop_breakpoint' => 'tablet',
				'pointer'         => 'underline',
			),
		);

		foreach ($nodes as &$row) {
			if (!is_array($row) || empty($row['tag']) || !$this->is_row_tag($row['tag'])) {
				continue;
			}
			if (!isset($row['content']) || !is_array($row['content'])) {
				continue;
			}
			$cols = $row['content'];
			$last = count($cols) - 1;
			if ($last >= 0 && !empty($cols[$last]['tag']) && $this->is_col_tag($cols[$last]['tag'])) {
				if (!isset($cols[$last]['content']) || !is_array($cols[$last]['content'])) {
					$cols[$last]['content'] = array();
				}
				$cols[$last]['content'][] = $menu_node;
				$row['content'] = $cols;
				break;
			}
		}
		unset($row);

		return $nodes;
	}

	/**
	 * Detect if a row is a website header navbar (logo + navigation links / menu)
	 */
	private function is_header_like_row($node) {
		if (!is_array($node) || empty($node['tag']) || !$this->is_row_tag($node['tag'])) {
			return false;
		}

		$json = strtolower(wp_json_encode($node));
		if (strpos($json, 'pl_nav') !== false || strpos($json, 'pl_menu') !== false || strpos($json, 'pl_nav_menu') !== false) {
			return true;
		}

		// Check if it's a slim row containing only Logo/Brand + Menu links (Home, About, Services, Contact, Sign In)
		if (preg_match('/\b(home\b.*\babout\b.*\bcontact|home\b.*\bservices\b|nav[-_]item|navbar|main-menu|site-logo)\b/i', $json)) {
			// Ensure it doesn't contain a hero headline like "welcome to" or "h1"
			if (strpos($json, '<h1>') === false && strpos($json, 'pagelayer-badge') === false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect if a row is a website footer / copyright bar
	 */
	private function is_footer_like_row($node) {
		if (!is_array($node) || empty($node['tag']) || !$this->is_row_tag($node['tag'])) {
			return false;
		}

		$json = strtolower(wp_json_encode($node));
		// Check for copyright text, all rights reserved, footer links
		if (preg_match('/(all\s*rights\s*reserved|©|&copy;|copyright\s*\d{4}|privacy\s*policy.*terms\s*of\s*service)/i', $json)) {
			return true;
		}

		return false;
	}

	private function strip_placeholder_copy($text) {
		$text = (string) $text;
		$text = preg_replace('/\{\{(?!element\})[a-zA-Z0-9_\-]+\}\}/', '', $text);
		$text = preg_replace('/\{[a-zA-Z0-9_\-]+\}/', '', $text);
		$text = preg_replace('/\(\s*ribbon[_\s-]*text\s*\)/i', '', $text);
		$text = preg_replace('/\bribbon[_\s-]*text\b/i', '', $text);
		$text = preg_replace('/\(\s*badge[_\s-]*text\s*\)/i', '', $text);
		$text = preg_replace('/\bLorem ipsum\b[^<]*/i', '', $text);
		return $text;
	}

	private function is_placeholder_label($text) {
		$plain = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $text))));
		if ($plain === '') {
			return true;
		}
		return (bool) preg_match('/^(ribbon([\s_-]*text)?|badge([\s_-]*text)?|your (title|text|heading)|lorem ipsum.*)$/i', $plain);
	}

	private function scrub_placeholders($nodes) {
		if (!is_array($nodes)) {
			return $nodes;
		}
		$out = array();
		foreach ($nodes as $node) {
			if (!is_array($node)) {
				$out[] = $node;
				continue;
			}
			if (isset($node['content']) && is_string($node['content'])) {
				$node['content'] = $this->strip_placeholder_copy($node['content']);
			}
			if (!empty($node['attrs']) && is_array($node['attrs'])) {
				foreach ($node['attrs'] as $k => $v) {
					if (is_string($v) && $k !== 'ele_css' && $k !== 'ele_attributes') {
						$node['attrs'][$k] = $this->strip_placeholder_copy($v);
					}
				}
			}
			if (isset($node['content']) && is_array($node['content'])) {
				$node['content'] = $this->scrub_placeholders($node['content']);
			}
			$out[] = $node;
		}
		return $out;
	}

	private function normalize_badges_and_ribbons($nodes) {
		if (!is_array($nodes)) {
			return $nodes;
		}
		$out = array();
		$n   = count($nodes);
		for ($i = 0; $i < $n; $i++) {
			$node = $nodes[$i];
			if (!is_array($node) || empty($node['tag'])) {
				$out[] = $node;
				continue;
			}

			$tag = $this->normalize_tag($node['tag']);
			if (!isset($node['attrs']) || !is_array($node['attrs'])) {
				$node['attrs'] = array();
			}

			if ($tag === 'pl_call') {
				$ribbon = isset($node['attrs']['ribbon_text']) ? $node['attrs']['ribbon_text'] : '';
				$show   = !empty($node['attrs']['show_ribbon']) && $node['attrs']['show_ribbon'] !== 'false' && $node['attrs']['show_ribbon'] !== '0';
				if (!$show || $this->is_placeholder_label($ribbon)) {
					unset(
						$node['attrs']['show_ribbon'],
						$node['attrs']['ribbon_text'],
						$node['attrs']['ribbon_pos'],
						$node['attrs']['ribbon_style'],
						$node['attrs']['ribbon_typo'],
						$node['attrs']['ribbon_color'],
						$node['attrs']['ribbon_bg']
					);
					$node['attrs']['show_ribbon'] = '';
				}
				// Ensure clean, generous content padding for Call To Action
				if (empty($node['attrs']['content_spacing'])) {
					$node['attrs']['content_spacing'] = '32,36,32,36';
				} else {
					$parts = explode(',', (string) $node['attrs']['content_spacing']);
					$left  = isset($parts[3]) ? intval($parts[3]) : 0;
					$right = isset($parts[1]) ? intval($parts[1]) : 0;
					if ($left < 28 || $right < 28) {
						$top    = isset($parts[0]) && intval($parts[0]) >= 20 ? intval($parts[0]) : 32;
						$r      = max($right, 36);
						$bottom = isset($parts[2]) && intval($parts[2]) >= 20 ? intval($parts[2]) : 32;
						$l      = max($left, 36);
						$node['attrs']['content_spacing'] = "{$top},{$r},{$bottom},{$l}";
					}
				}
				if (empty($node['attrs']['content_valign'])) {
					$node['attrs']['content_valign'] = 'center';
				}
			}

			if ($tag === 'pl_flipbox') {
				// Prevent text overflowing or spilling out of flipbox
				if (empty($node['attrs']['height']) || intval($node['attrs']['height']) < 380) {
					$node['attrs']['height'] = '380';
				}
				if (empty($node['attrs']['front_section_padding']) || $node['attrs']['front_section_padding'] === '100,100,100,100') {
					$node['attrs']['front_section_padding'] = '24,28,24,28';
				}
				if (empty($node['attrs']['back_section_padding']) || $node['attrs']['back_section_padding'] === '100,100,100,100') {
					$node['attrs']['back_section_padding'] = '24,28,24,28';
				}
				if (empty($node['attrs']['content_width']) || intval($node['attrs']['content_width']) < 90) {
					$node['attrs']['content_width'] = '100';
				}
			}

			if ($tag === 'pl_heading' && isset($node['content']) && is_string($node['content'])) {
				$plain = trim(wp_strip_all_tags($node['content']));
				$next  = ($i + 1 < $n && is_array($nodes[$i + 1])) ? $nodes[$i + 1] : null;
				$next_is_title = false;
				if ($next && !empty($next['tag']) && $this->normalize_tag($next['tag']) === 'pl_heading' && isset($next['content']) && is_string($next['content'])) {
					$next_is_title = (bool) preg_match('/<h[1-3]\b/i', $next['content']);
				}
				if ($this->is_placeholder_label($plain)) {
					continue;
				}
				if ($next_is_title && $plain !== '' && strlen($plain) <= 28 && !preg_match('/<h[1-3]\b/i', $node['content'])) {
					$node = $this->badge_heading_node($plain, $node);
				} elseif (preg_match('/^\s*(?:<[^>]+>)?\s*(?:\(|\[)?\s*(ribbon|badge)[^<]{0,20}$/i', $plain)) {
					continue;
				}
			}

			if (isset($node['content']) && is_array($node['content'])) {
				$node['content'] = $this->normalize_badges_and_ribbons($node['content']);
			}
			$out[] = $node;
		}
		return $out;
	}

	private function badge_heading_node($label, $base) {
		$on_dark = false;
		if (!empty($base['attrs']['color'])) {
			$on_dark = !$this->color_is_dark($base['attrs']['color']);
		}
		$bg    = $on_dark ? 'rgba(255,255,255,0.14)' : 'rgba(37,99,235,0.12)';
		$color = !empty($base['attrs']['color']) ? $base['attrs']['color'] : '$primary';
		return array(
			'tag'     => 'pl_heading',
			'attrs'   => array(
				'align'        => !empty($base['attrs']['align']) ? $base['attrs']['align'] : 'left',
				'color'        => $color,
				'heading_typo' => $this->global_font_family('primary') . ',12,,700,,,,1.2,uppercase,1.4,',
				'ele_margin'   => '0px,0px,16px,0px',
				'ele_css'      => '{{element}} .pagelayer-heading-holder{display:block;margin:0 0 16px;}'
					. '{{element}} .pagelayer-badge{display:inline-block;padding:6px 14px;border-radius:999px;background:' . $bg . ';letter-spacing:0.08em;}',
			),
			'content' => '<span class="pagelayer-badge">' . esc_html($label) . '</span>',
		);
	}

	private function normalize_social_groups($nodes) {
		if (!is_array($nodes)) {
			return $nodes;
		}

		$flat = array();
		$buf  = array();
		$flush = function () use (&$buf, &$flat) {
			if (count($buf) >= 2) {
				$flat[] = $this->make_social_grp($buf);
			} else {
				foreach ($buf as $item) {
					$flat[] = $item;
				}
			}
			$buf = array();
		};

		foreach ($nodes as $node) {
			if (!is_array($node) || empty($node['tag'])) {
				$flush();
				$flat[] = $node;
				continue;
			}
			$tag = $this->normalize_tag($node['tag']);
			if ($tag === 'pl_social') {
				$buf[] = $node;
				continue;
			}
			$flush();
			if ($tag === 'pl_social_grp') {
				$node = $this->style_social_grp($node);
			}
			if (isset($node['content']) && is_array($node['content'])) {
				$node['content'] = $this->normalize_social_groups($node['content']);
			}
			$flat[] = $node;
		}
		$flush();
		return $flat;
	}

	private function make_social_grp($children) {
		return $this->style_social_grp(array(
			'tag'     => 'pl_social_grp',
			'attrs'   => array(),
			'content' => array_values($children),
		));
	}

	private function style_social_grp($node, $on_dark = false) {
		if (!isset($node['attrs']) || !is_array($node['attrs'])) {
			$node['attrs'] = array();
		}
		$attrs = $node['attrs'];
		$attrs['group_layout'] = 'pagelayer-btn-grp-horizontal';
		if (empty($attrs['align'])) {
			$attrs['align'] = 'center';
		}
		if (!isset($attrs['icon_spacing']) || $attrs['icon_spacing'] === '' || (string) $attrs['icon_spacing'] === '0') {
			$attrs['icon_spacing'] = '8';
		}
		$size = isset($attrs['icon_size']) ? (int) preg_replace('/[^0-9]/', '', (string) $attrs['icon_size']) : 0;
		$attrs['icon_size'] = (string) ($size >= 18 ? $size : 20);
		if (empty($attrs['bg_shape'])) {
			$attrs['bg_shape'] = 'pagelayer-social-shape-circle';
		}
		if (empty($attrs['bg_size']) || (int) $attrs['bg_size'] < 6) {
			$attrs['bg_size'] = '10';
		}

		// Custom theme colors so icon vs circle never match. Official scheme
		// is fine visually, but AI often sets icon_color == icon_bg_color.
		$attrs['color_scheme'] = '';
		$icon = isset($attrs['icon_color']) ? trim((string) $attrs['icon_color']) : '';
		$bg   = isset($attrs['icon_bg_color']) ? trim((string) $attrs['icon_bg_color']) : '';
		$same = ($icon !== '' && $bg !== '' && strtolower($icon) === strtolower($bg));
		$icon_dark = $icon === '' ? null : $this->color_is_dark($icon, false);
		$bg_dark   = $bg === '' ? null : $this->color_is_dark($bg, true);
		$icon_light = $icon !== '' && $this->color_is_light($icon);
		$bg_light   = $bg !== '' && $this->color_is_light($bg);

		$clash = $same || $icon === '' || $bg === '' || ($icon_dark && $bg_dark) || ($icon_light && $bg_light);
		if ($clash) {
			$attrs['icon_color']    = '#ffffff';
			$attrs['icon_bg_color'] = '$primary';
		}
		if (empty($attrs['icon_color_hover'])) {
			$attrs['icon_color_hover'] = '$primary';
		}
		if (empty($attrs['icon_bg_color_hover'])) {
			$attrs['icon_bg_color_hover'] = '#ffffff';
		}

		$row = '{{element}}{display:inline-flex;flex-direction:row;flex-wrap:wrap;align-items:center;justify-content:center;gap:14px;}'
			. '{{element}}>.pagelayer-ele-wrap,{{element}} .pagelayer-social{display:inline-flex;width:auto;max-width:none;float:none;}'
			. '{{element}} .pagelayer-icon-holder{background-color:var(--pagelayer-color-primary,#4f46e5)!important;}'
			. '{{element}} .pagelayer-social-fa{color:#ffffff!important;}';
		$css = isset($attrs['ele_css']) ? (string) $attrs['ele_css'] : '';
		if (strpos($css, 'pagelayer-social-fa') === false) {
			$attrs['ele_css'] = $css . $row;
		} elseif (strpos($css, 'flex-direction:row') === false) {
			$attrs['ele_css'] = $css . '{{element}}{display:inline-flex;flex-direction:row;flex-wrap:wrap;align-items:center;justify-content:center;gap:14px;}';
		}
		$node['attrs'] = $attrs;
		return $node;
	}

	/**
	 * Icon / image boxes: stacked layout, centered icon + heading + copy,
	 * with a readable heading/body type scale.
	 */
	private function style_icon_box($tag, $attrs) {
		if (!is_array($attrs)) {
			$attrs = array();
		}

		$heading_family = $this->global_font_family('primary');
		$body_family    = $this->global_font_family('text');

		$attrs['service_alignment'] = 'top';
		if ($tag === 'pl_iconbox') {
			$attrs['service_icon_alignment'] = 'center';
		} else {
			$attrs['service_img_alignment'] = 'center';
		}
		$attrs['heading_alignment']      = 'center';
		$attrs['service_text_alignment'] = 'center';

		if (empty($attrs['service_icon_spacing']) && $tag === 'pl_iconbox') {
			$attrs['service_icon_spacing'] = '0,0,16,0';
		}
		if (empty($attrs['service_title_spacing'])) {
			$attrs['service_title_spacing'] = '0,0,8,0';
		}

		$attrs['service_heading_typo'] = $this->iconbox_heading_typo(
			isset($attrs['service_heading_typo']) ? $attrs['service_heading_typo'] : '',
			$heading_family
		);

		if (empty($attrs['font_family'])) {
			$attrs['font_family'] = $body_family;
		}
		if (empty($attrs['font_size']) || (int) $attrs['font_size'] < 14 || (int) $attrs['font_size'] > 18) {
			$attrs['font_size'] = '15';
		}
		if (empty($attrs['line_height'])) {
			$attrs['line_height'] = '1.65';
		}

		if ($tag === 'pl_service') {
			$attrs['service_alignment'] = 'top';
			$attrs['service_img_alignment'] = 'center';
			$attrs['heading_alignment'] = 'center';
			$attrs['service_text_alignment'] = 'center';
			if (empty($attrs['service_image_height'])) {
				$attrs['service_image_height'] = '220';
			}
			if (empty($attrs['service_image_object_fit'])) {
				$attrs['service_image_object_fit'] = 'cover';
			}
			if (empty($attrs['service_image_object_pos'])) {
				$attrs['service_image_object_pos'] = 'center';
			}
			if (empty($attrs['service_image_border_radius'])) {
				$attrs['service_image_border_radius'] = '8,8,8,8';
			}
			$rule = '{{element}} .pagelayer-service-image{width:100%!important;overflow:hidden;margin:0 auto 16px auto;border-radius:8px;}'
				. '{{element}} .pagelayer-service-image img{width:100%!important;height:220px!important;max-height:220px!important;min-height:220px!important;object-fit:cover!important;object-position:center!important;border-radius:8px;display:block;margin:0 auto;}'
				. '{{element}} .pagelayer-service-container{display:flex;flex-direction:column;height:100%;}'
				. '{{element}} .pagelayer-service-details{display:flex;flex-direction:column;flex-grow:1;}'
				. '{{element}} .pagelayer-service-heading{text-align:center;font-weight:700;line-height:1.3;margin-bottom:8px;}'
				. '{{element}} .pagelayer-service-text{text-align:center;font-size:15px;line-height:1.7;font-weight:400;flex-grow:1;margin-bottom:16px;}'
				. '{{element}} .pagelayer-service-btn{margin-top:auto;align-self:center;}';
		} else {
			$attrs['service_alignment'] = 'top';
			$attrs['service_icon_alignment'] = 'center';
			$attrs['heading_alignment'] = 'center';
			$attrs['service_text_alignment'] = 'center';
			if (empty($attrs['service_icon_font_size'])) {
				$attrs['service_icon_font_size'] = '28';
			}
			$rule = '{{element}} .pagelayer-service-icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:64px!important;height:64px!important;min-width:64px!important;min-height:64px!important;margin:0 auto 16px auto!important;line-height:1!important;}'
				. '{{element}} .pagelayer-service-icon i,{{element}} .pagelayer-service-icon svg{font-size:28px!important;width:28px!important;height:28px!important;line-height:28px!important;display:inline-block!important;}'
				. '{{element}} .pagelayer-service-container{display:flex;flex-direction:column;height:100%;}'
				. '{{element}} .pagelayer-service-details{display:flex;flex-direction:column;flex-grow:1;}'
				. '{{element}} .pagelayer-service-heading{text-align:center;font-weight:700;line-height:1.3;margin-bottom:8px;}'
				. '{{element}} .pagelayer-service-text{text-align:center;font-size:15px;line-height:1.7;font-weight:400;flex-grow:1;margin-bottom:16px;}'
				. '{{element}} .pagelayer-service-btn{margin-top:auto;align-self:center;}';
		}
		$attrs = $this->append_ele_css($attrs, $rule);

		return $attrs;
	}

	/**
	 * Image slider: constrained width and height with object-fit: cover,
	 * preventing massive, viewport-overflowing images.
	 */
	private function style_image_slider($attrs) {
		if (!is_array($attrs)) {
			$attrs = array();
		}

		if (empty($attrs['slide_items'])) {
			$attrs['slide_items'] = '1';
		}
		if (empty($attrs['auto'])) {
			$attrs['auto'] = 'true';
		}
		if (empty($attrs['loop'])) {
			$attrs['loop'] = 'true';
		}
		if (empty($attrs['adaptive_height'])) {
			$attrs['adaptive_height'] = 'false';
		}
		if (empty($attrs['controls'])) {
			$attrs['controls'] = 'arrows';
		}
		if (empty($attrs['border_radius'])) {
			$attrs['border_radius'] = '12px,12px,12px,12px';
		}
		$nav = isset($attrs['nav_size']) ? (int) preg_replace('/[^0-9]/', '', (string) $attrs['nav_size']) : 0;
		$attrs['nav_size'] = (string) ($nav >= 22 ? $nav : 28);
		$btn = isset($attrs['arraow_bg_size']) ? (int) preg_replace('/[^0-9]/', '', (string) $attrs['arraow_bg_size']) : 0;
		$attrs['arraow_bg_size'] = (string) ($btn >= 36 ? $btn : 48);
		if (empty($attrs['arraow_bg_shape'])) {
			$attrs['arraow_bg_shape'] = '50';
		}
		$arrow_fg = isset($attrs['arraow_color']) ? (string) $attrs['arraow_color'] : '';
		$arrow_bg = isset($attrs['arrows_bg']) ? (string) $attrs['arrows_bg'] : '';
		if ($arrow_fg === '' || $arrow_bg === '' || $this->colors_clash($arrow_fg, $arrow_bg)) {
			$attrs['arrows_bg']    = 'rgba(15,23,42,0.72)';
			$attrs['arraow_color'] = '#ffffff';
		}

		$rule = '{{element}}{max-width:1000px!important;margin:0 auto!important;overflow:visible!important;}'
			. '{{element}} .pagelayer-image-slider-div{max-width:1000px!important;margin:0 auto!important;overflow:visible!important;border-radius:12px!important;}'
			. '{{element}} .pagelayer-image-slider-ul{margin:0 auto!important;}'
			. '{{element}} .pagelayer-slider-item{overflow:hidden!important;border-radius:12px!important;}'
			. '{{element}} .pagelayer-slider-item img,{{element}} img.pagelayer-img,{{element}} .pagelayer-image-slider-div img{width:100%!important;height:420px!important;max-height:440px!important;min-height:280px!important;object-fit:cover!important;object-position:center!important;border-radius:12px!important;display:block!important;margin:0 auto!important;}'
			. '{{element}} .pagelayer-owl-nav{z-index:5!important;}'
			. '{{element}} .pagelayer-owl-prev,{{element}} .pagelayer-owl-next{width:48px!important;height:48px!important;min-width:48px!important;min-height:48px!important;border-radius:50%!important;background:rgba(15,23,42,0.72)!important;color:#ffffff!important;display:flex!important;align-items:center!important;justify-content:center!important;opacity:1!important;}'
			. '{{element}} .pagelayer-owl-prev span,{{element}} .pagelayer-owl-next span,{{element}} .pagelayer-owl-prev i,{{element}} .pagelayer-owl-next i{font-size:28px!important;line-height:1!important;color:#ffffff!important;}';

		$attrs = $this->append_ele_css($attrs, $rule);

		return $attrs;
	}

	private function iconbox_heading_typo($typo, $family) {
		$parts = array_fill(0, 11, '');
		if (is_string($typo) && $typo !== '') {
			if (isset($typo[0]) && $typo[0] === '$') {
				$token_family = $this->global_font_family(substr($typo, 1));
				if ($token_family !== '') {
					$family = $token_family;
				}
			} else {
				foreach (explode(',', $typo) as $i => $part) {
					if ($i < 11) {
						$parts[$i] = $part;
					}
				}
			}
		}
		if ($family !== '' && $parts[0] === '') {
			$parts[0] = $family;
		}
		$size = (int) preg_replace('/[^0-9]/', '', (string) $parts[1]);
		if ($size < 18 || $size > 24) {
			$parts[1] = '20';
		}
		$parts[3] = ($parts[3] === '' || (int) $parts[3] < 600) ? '700' : $parts[3];
		if ($parts[7] === '' || (float) $parts[7] < 1.2) {
			$parts[7] = '1.3';
		}
		return implode(',', $parts);
	}

	private function enforce_section_padding($nodes) {
		if (!is_array($nodes)) {
			return $nodes;
		}
		foreach ($nodes as &$node) {
			if (!is_array($node) || empty($node['tag']) || !$this->is_row_tag($node['tag'])) {
				continue;
			}
			$tag = $this->normalize_tag($node['tag']);
			if ($tag === 'pl_inner_row') {
				continue;
			}
			if (!isset($node['attrs']) || !is_array($node['attrs'])) {
				$node['attrs'] = array();
			}
			$node['attrs']['ele_padding'] = $this->section_row_padding(
				isset($node['attrs']['ele_padding']) ? $node['attrs']['ele_padding'] : ''
			);
		}
		unset($node);
		return $nodes;
	}

	/**
	 * Public helper so the controller shortcode fallback can reuse section padding.
	 */
	public function format_section_row_padding($pad_raw) {
		return $this->section_row_padding($pad_raw);
	}

	/**
	 * Section rows need their own top AND bottom padding. A 0 top on the next
	 * band makes its columns sit flush against the row above.
	 */
	private function section_row_padding($pad_raw) {
		$pad_raw = trim((string) $pad_raw);
		$min_y   = 50;
		$min_x   = 20;
		$cap_y   = 60;

		if ($pad_raw === '' || $pad_raw === '0px,0px,0px,0px' || $pad_raw === '0,0,0,0') {
			return $min_y . 'px,' . $min_x . 'px,' . $min_y . 'px,' . $min_x . 'px';
		}

		$parts = $this->split_padding($pad_raw);
		$top   = $this->padding_to_px($parts[0]);
		$right = $this->padding_to_px($parts[1]);
		$bot   = $this->padding_to_px($parts[2]);
		$left  = $this->padding_to_px($parts[3]);

		if ($top < $min_y) {
			$parts[0] = $min_y . 'px';
		} elseif ($top > 100) {
			$parts[0] = $cap_y . 'px';
		}
		if ($bot < $min_y) {
			$parts[2] = $min_y . 'px';
		} elseif ($bot > 100) {
			$parts[2] = $cap_y . 'px';
		}
		if ($right <= 0) {
			$parts[1] = $min_x . 'px';
		}
		if ($left <= 0) {
			$parts[3] = $parts[1];
		}

		return implode(',', $parts);
	}

	/**
	 * Apply palette, fonts, row backgrounds, contrast, and schema-owned
	 * visual settings the model left empty. Does not invent new sections.
	 */
	private function apply_theme_visuals($nodes, $on_dark = false, $row_i = 0) {
		if (!is_array($nodes) || empty($nodes)) {
			return $nodes;
		}

		$out = array();
		foreach ($nodes as $node) {
			if (!is_array($node) || empty($node['tag'])) {
				$out[] = $node;
				continue;
			}

			$tag = $this->normalize_tag($node['tag']);
			if (!isset($node['attrs']) || !is_array($node['attrs'])) {
				$node['attrs'] = array();
			}

			$child_dark = $on_dark;
			if ($this->is_row_tag($tag)) {
				$node['attrs'] = $this->ensure_row_background($node['attrs'], $row_i);
				$child_dark = $this->effective_is_dark($node['attrs'], $on_dark);
				$row_i++;
			} elseif ($this->has_own_surface($node['attrs'])) {
				$child_dark = $this->effective_is_dark($node['attrs'], $on_dark);
			}

			$index = $this->get_attr_index($tag);
			$node['attrs'] = $this->apply_schema_visuals($tag, $node['attrs'], $node, $index, $child_dark);
			if ($tag === 'pl_iconbox' || $tag === 'pl_service') {
				$node['attrs'] = $this->style_icon_box($tag, $node['attrs']);
			} elseif ($tag === 'pl_image_slider') {
				$node['attrs'] = $this->style_image_slider($node['attrs']);
			} elseif ($tag === 'pl_accordion' || $tag === 'pl_tabs') {
				$node['attrs'] = $this->style_accordion_or_tabs($tag, $node['attrs'], $child_dark);
			} elseif ($tag === 'pl_counter') {
				$node['attrs'] = $this->style_counter($node['attrs'], $child_dark);
			}

			// Card chrome may have just received a background — recompute contrast
			// so children (and this widget's own text) match the surface they sit on.
			if ($this->has_own_surface($node['attrs'])) {
				$child_dark = $this->effective_is_dark($node['attrs'], $on_dark);
			}

			$node['attrs'] = $this->ensure_contrast_text($tag, $node['attrs'], $index, $child_dark);
			$node['attrs'] = $this->ensure_widget_contrast($tag, $node['attrs'], $child_dark);
			if ($tag === 'pl_social_grp') {
				$node = $this->style_social_grp($node, $child_dark);
			}
			if ($this->is_row_tag($tag) || $this->has_own_surface($node['attrs'])) {
				$node['attrs'] = $this->mark_contrast_class($node['attrs'], $child_dark);
			}
			$node['attrs'] = $this->fill_style_companions($node['attrs']);
			$node['attrs'] = $this->sanitize_attrs($tag, $node['attrs']);
			$node['attrs'] = $this->fill_style_companions($node['attrs']);

			if (isset($node['content']) && is_array($node['content'])) {
				$node['content'] = $this->apply_theme_visuals($node['content'], $child_dark, 0);
			}

			$out[] = $node;
		}

		return $out;
	}

	private function ensure_row_background($attrs, $row_i) {
		if (!is_array($attrs)) {
			$attrs = array();
		}
		if (!empty($attrs['ele_bg_color']) || (!empty($attrs['ele_bg_type']) && $attrs['ele_bg_type'] !== '' && $attrs['ele_bg_type'] !== 'color')) {
			if (!empty($attrs['ele_bg_color']) && (empty($attrs['ele_bg_type']) || $attrs['ele_bg_type'] === '')) {
				$attrs['ele_bg_type'] = 'color';
			}
			return $attrs;
		}

		$cycle = array('$primary', '$bg', '$surface', '$bg');
		$token = $cycle[$row_i % count($cycle)];
		$attrs['ele_bg_type']  = 'color';
		$attrs['ele_bg_color'] = $token;
		return $attrs;
	}

	/**
	 * Public contrast helper so the controller/canvas fallback can reuse it.
	 */
	public function is_dark_color($val, $fallback = false) {
		return $this->color_is_dark($val, $fallback);
	}

	private function has_own_surface($attrs) {
		if (!is_array($attrs)) {
			return false;
		}
		if (!empty($attrs['ele_bg_color'])) {
			return true;
		}
		$type = isset($attrs['ele_bg_type']) ? (string) $attrs['ele_bg_type'] : '';
		return ($type !== '' && $type !== 'color');
	}

	private function effective_is_dark($attrs, $parent_dark = false) {
		if (!is_array($attrs)) {
			return $parent_dark;
		}
		if (!empty($attrs['ele_bg_color'])) {
			return $this->color_is_dark($attrs['ele_bg_color'], $parent_dark);
		}
		$type = isset($attrs['ele_bg_type']) ? (string) $attrs['ele_bg_type'] : '';
		if ($type === 'gradient' && !empty($attrs['ele_bg_gradient']) && preg_match('/#([0-9a-f]{3,8})/i', (string) $attrs['ele_bg_gradient'], $m)) {
			return $this->color_is_dark('#' . $m[1], $parent_dark);
		}
		if ($type === 'image') {
			if (!empty($attrs['ele_img_color'])) {
				return $this->color_is_dark($attrs['ele_img_color'], true);
			}
			return true;
		}
		return $parent_dark;
	}

	private function mark_contrast_class($attrs, $on_dark) {
		if (!is_array($attrs)) {
			$attrs = array();
		}
		$cls = isset($attrs['ele_classes']) ? trim((string) $attrs['ele_classes']) : '';
		$cls = trim(preg_replace('/\s*pagelayer-ai-on-(dark|light)\s*/i', ' ', $cls));
		$attrs['ele_classes'] = trim($cls . ' ' . ($on_dark ? 'pagelayer-ai-on-dark' : 'pagelayer-ai-on-light'));
		return $attrs;
	}

	private function color_is_dark($val, $fallback = false) {
		$val = trim((string) $val);
		if ($val === '') {
			return $fallback;
		}
		$low = strtolower($val);
		if (in_array($low, array('transparent', 'inherit', 'currentcolor', 'currentColor'), true)) {
			return $fallback;
		}

		$dark_names = array(
			'black' => 1, 'navy' => 1, 'maroon' => 1, 'purple' => 1, 'indigo' => 1,
			'midnightblue' => 1, 'darkblue' => 1, 'darkslategray' => 1, 'darkslategrey' => 1,
			'darkgreen' => 1, 'darkred' => 1, 'teal' => 1, 'olive' => 1, 'brown' => 1,
			'#000' => 1, '#000000' => 1, '#111' => 1, '#111111' => 1, '#222' => 1, '#222222' => 1,
		);
		if (isset($dark_names[$low])) {
			return true;
		}
		$light_names = array(
			'white' => 1, 'ivory' => 1, 'snow' => 1, 'whitesmoke' => 1, 'ghostwhite' => 1,
			'azure' => 1, 'beige' => 1, 'linen' => 1, 'seashell' => 1, '#fff' => 1, '#ffffff' => 1,
		);
		if (isset($light_names[$low])) {
			return false;
		}

		if (preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([0-9.]+))?\s*\)/i', $val, $m)) {
			$alpha = isset($m[4]) && $m[4] !== '' ? (float) $m[4] : 1.0;
			if ($alpha < 0.45) {
				return $fallback;
			}
			return $this->rgb_is_dark((int) $m[1], (int) $m[2], (int) $m[3]);
		}

		if (isset($val[0]) && $val[0] === '$') {
			$key = strtolower(substr($val, 1));
			$hex = $this->resolve_color_hex($val);
			if ($hex !== '') {
				return $this->hex_is_dark($hex, $fallback);
			}
			// Unresolved body-text token is almost always a dark ink color.
			return ($key === 'text') ? true : $fallback;
		}

		$hex = $this->resolve_color_hex($val);
		if ($hex === '') {
			return $fallback;
		}
		return $this->hex_is_dark($hex, $fallback);
	}

	private function hex_is_dark($hex, $fallback = false) {
		$hex = ltrim((string) $hex, '#');
		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		} elseif (strlen($hex) === 4) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
		}
		if (strlen($hex) >= 8) {
			$alpha = hexdec(substr($hex, 6, 2)) / 255;
			if ($alpha < 0.45) {
				return $fallback;
			}
			$hex = substr($hex, 0, 6);
		}
		if (strlen($hex) < 6) {
			return $fallback;
		}
		return $this->rgb_is_dark(
			hexdec(substr($hex, 0, 2)),
			hexdec(substr($hex, 2, 2)),
			hexdec(substr($hex, 4, 2))
		);
	}

	private function rgb_is_dark($r, $g, $b) {
		$lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
		return $lum < 0.55;
	}

	private function resolve_color_hex($val) {
		$val = trim((string) $val);
		if ($val === '') {
			return '';
		}
		if ($val[0] === '$') {
			$snap = $this->get_globals_snapshot();
			$key  = substr($val, 1);
			if (!empty($snap['colors'][$key]['value'])) {
				return $this->coerce_hex($snap['colors'][$key]['value']);
			}
			return '';
		}
		return $this->coerce_hex($val);
	}

	private function apply_schema_visuals($tag, $attrs, $node, $index, $on_dark) {
		if (!is_array($attrs)) {
			$attrs = array();
		}

		$heading_family = $this->global_font_family('primary');
		$body_family    = $this->global_font_family('text');
		$light          = '#ffffff';
		$text_on_light  = '$text';
		$brand          = '$primary';
		$accent         = '$accent';

		if (!empty($index['typography']) && is_array($index['typography'])) {
			foreach ($index['typography'] as $typo_key) {
				if (stripos($typo_key, 'ribbon') !== false) {
					continue;
				}
				$is_heading = (bool) preg_match('/heading|title|btn|button|display/i', $typo_key);
				$family     = $is_heading ? $heading_family : $body_family;
				$size       = '';
				$weight     = '';
				if ($tag === 'pl_heading') {
					$content = (isset($node['content']) && is_string($node['content'])) ? $node['content'] : '';
					if (stripos($content, '<h1') !== false) {
						$size   = '48';
						$weight = '800';
					} elseif (stripos($content, '<h2') !== false) {
						$size   = '36';
						$weight = '700';
					} elseif (stripos($content, '<h3') !== false) {
						$size   = '22';
						$weight = '700';
					} else {
						$size   = '18';
						$weight = '600';
					}
				} elseif ($tag === 'pl_iconbox' || $tag === 'pl_service') {
					$size   = '20';
					$weight = '700';
				} elseif (empty($attrs[$typo_key])) {
					$size   = '18';
					$weight = '600';
				}
				$attrs[$typo_key] = $this->typo_with_family(isset($attrs[$typo_key]) ? $attrs[$typo_key] : '', $family, $size, $weight);
			}
		}

		if ($tag === 'pl_text' || $tag === 'pl_heading') {
			if (empty($attrs['font_family'])) {
				$attrs['font_family'] = ($tag === 'pl_heading') ? $heading_family : $body_family;
			}
		}

		if ($tag === 'pl_text') {
			if (empty($attrs['font_size'])) {
				$attrs['font_size'] = '16';
			}
			if (empty($attrs['line_height'])) {
				$attrs['line_height'] = '1.7';
			}
		}

		$cards = $this->card_widget_tags();
		if (isset($cards[$tag])) {
			if (empty($attrs['ele_bg_color'])) {
				$attrs['ele_bg_type']  = 'color';
				$attrs['ele_bg_color'] = $on_dark ? 'rgba(255,255,255,0.08)' : '#ffffff';
			}
			if (empty($attrs['border_radius'])) {
				$attrs['border_radius'] = '16px,16px,16px,16px';
			}
			if (empty($attrs['ele_shadow']) && !$this->color_is_dark(!empty($attrs['ele_bg_color']) ? $attrs['ele_bg_color'] : '', $on_dark)) {
				$attrs['ele_shadow'] = '0,8,24,rgba(15,23,42,0.08),0,';
			}
			if (empty($attrs['ele_padding'])) {
				$attrs['ele_padding'] = '32px,28px,32px,28px';
			}
		}

		if ($this->has_own_surface($attrs)) {
			$on_dark = $this->effective_is_dark($attrs, $on_dark);
		}

		if (!empty($index['own']) && is_array($index['own']) && !empty($index['allowed'])) {
			foreach ($index['own'] as $key => $_) {
				if (strpos($key, '_hover') !== false || strpos($key, '_state') !== false) {
					continue;
				}
				$type = isset($index['allowed'][$key]) ? $index['allowed'][$key] : '';
				if ($type !== 'color') {
					continue;
				}
				if ($this->is_surface_color_key($key)) {
					continue;
				}
				$token = $this->color_token_for_key($key, $on_dark, $light, $text_on_light, $brand, $accent);
				if (empty($attrs[$key])) {
					$attrs[$key] = $token;
				} elseif ($on_dark && $this->color_is_dark($attrs[$key])) {
					$attrs[$key] = $token;
				} elseif (!$on_dark && $this->color_is_light($attrs[$key])) {
					$attrs[$key] = $token;
				}
			}
		}

		return $attrs;
	}

	/**
	 * Force readable text on the surface this widget actually sits on.
	 * Rich Text / service body copy have no color prop, so they inherit the
	 * theme's black ink unless we set heading/text colors and ele_css.
	 */
	private function ensure_contrast_text($tag, $attrs, $index, $on_dark) {
		if (!is_array($attrs)) {
			$attrs = array();
		}

		$light = '#ffffff';
		$brand = '$primary';
		$ink   = '$text';
		$dark_ink = '#111827';
		$allowed = (!empty($index) && !empty($index['allowed']) && is_array($index['allowed'])) ? $index['allowed'] : array();

		$fix_fg = function ($val, $heading = false) use ($on_dark, $light, $brand, $ink, $dark_ink) {
			if ($val === '' || $val === null) {
				if ($on_dark) {
					return $light;
				}
				return $heading ? $brand : $ink;
			}
			if ($on_dark && $this->color_is_dark($val)) {
				return $light;
			}
			if (!$on_dark && $this->color_is_light($val)) {
				return $heading ? $brand : $dark_ink;
			}
			return $val;
		};

		if (isset($allowed['color'])) {
			$cur = isset($attrs['color']) ? (string) $attrs['color'] : '';
			$attrs['color'] = $fix_fg($cur, $tag === 'pl_heading');
		}

		$text_keys = array(
			'service_heading_color' => true, 'heading_color' => true, 'title_color' => true,
			'list_color' => false, 'quote_content_color' => false, 'cite_color' => false,
			'counter_number_color' => true, 'counter_text_color' => false,
			'service_icon_color' => true, 'icon_color' => true,
			'service_text_color' => false, 'desc_color' => false, 'content_color' => false,
		);
		foreach ($text_keys as $key => $is_heading) {
			if (!isset($allowed[$key]) || $allowed[$key] !== 'color') {
				continue;
			}
			$cur = isset($attrs[$key]) ? (string) $attrs[$key] : '';
			$attrs[$key] = $fix_fg($cur, $is_heading);
		}

		$rule = $this->contrast_text_css_for_tag($tag, $on_dark);
		if ($rule !== '') {
			$attrs = $this->append_ele_css($attrs, $rule);
		}

		return $attrs;
	}

	private function is_surface_color_key($key) {
		$key_l = strtolower((string) $key);
		if (preg_match('/(^|_)bg(_color|_hover|$)|background/i', $key_l)) {
			return true;
		}
		if (preg_match('/heading|title|icon|text|desc|cite|quote|label|name|number|count/i', $key_l)) {
			return false;
		}
		if (preg_match('/btn|button/i', $key_l) && preg_match('/color/i', $key_l) && !preg_match('/bg|background/i', $key_l)) {
			return false;
		}
		return (bool) preg_match('/overlay|shadow|ribbon|border|gradient/i', $key_l);
	}

	private function colors_clash($a, $b) {
		$a = trim((string) $a);
		$b = trim((string) $b);
		if ($a === '' || $b === '') {
			return true;
		}
		if (strtolower($a) === strtolower($b)) {
			return true;
		}
		$a_dark  = $this->color_is_dark($a, false);
		$b_dark  = $this->color_is_dark($b, false);
		$a_light = $this->color_is_light($a);
		$b_light = $this->color_is_light($b);
		if ($a_dark && $b_dark) {
			return true;
		}
		if ($a_light && $b_light) {
			return true;
		}
		return false;
	}

	/**
	 * Foreground/background pairs on a widget must contrast — AI often sets
	 * both to $primary or both to white.
	 */
	private function ensure_widget_contrast($tag, $attrs, $on_dark) {
		if (!is_array($attrs)) {
			$attrs = array();
		}

		$white   = '#ffffff';
		$ink     = '#111827';
		$primary = '$primary';
		$muted   = $on_dark ? 'rgba(255,255,255,0.14)' : '#f1f5f9';
		$darkbtn = 'rgba(15,23,42,0.72)';

		$pairs = array(
			array('arraow_color', 'arrows_bg', $white, $darkbtn),
			array('btn_color', 'btn_bg_color', $white, $primary),
			array('icon_color', 'icon_bg_color', $white, $primary),
			array('service_icon_color', 'service_icon_bg_color', $on_dark ? $white : $primary, $on_dark ? 'rgba(255,255,255,0.12)' : '#eef2ff'),
			array('tabs_color', 'tabs_bg_color', $on_dark ? $white : $ink, $muted),
			array('tabs_active_color', 'tabs_active_bg_color', $white, $primary),
			array('tabs_content_color', 'tabs_content_bg_color', $on_dark ? $white : $ink, $on_dark ? 'rgba(255,255,255,0.08)' : $white),
		);

		foreach ($pairs as $pair) {
			list($fg_key, $bg_key, $fg, $bg) = $pair;
			$has_fg = array_key_exists($fg_key, $attrs);
			$has_bg = array_key_exists($bg_key, $attrs);
			if (!$has_fg && !$has_bg) {
				continue;
			}
			$cur_fg = $has_fg ? trim((string) $attrs[$fg_key]) : '';
			$cur_bg = $has_bg ? trim((string) $attrs[$bg_key]) : '';
			if ($this->colors_clash($cur_fg, $cur_bg)) {
				$attrs[$fg_key] = $fg;
				$attrs[$bg_key] = $bg;
			}
		}

		if ($tag === 'pl_btn') {
			if ($this->colors_clash(isset($attrs['btn_color']) ? $attrs['btn_color'] : '', isset($attrs['btn_bg_color']) ? $attrs['btn_bg_color'] : '')) {
				$attrs['btn_color']    = $white;
				$attrs['btn_bg_color'] = $primary;
			}
		}

		return $attrs;
	}

	private function style_accordion_or_tabs($tag, $attrs, $on_dark) {
		if (!is_array($attrs)) {
			$attrs = array();
		}

		$white   = '#ffffff';
		$ink     = '#111827';
		$primary = '$primary';
		$tab_bg  = $on_dark ? 'rgba(255,255,255,0.14)' : '#f1f5f9';
		$panel   = $on_dark ? 'rgba(255,255,255,0.08)' : '#ffffff';

		$attrs['tabs_color']            = $on_dark ? $white : $ink;
		$attrs['tabs_bg_color']         = $tab_bg;
		$attrs['tabs_active_color']     = $white;
		$attrs['tabs_active_bg_color']  = $primary;
		if ($tag === 'pl_tabs') {
			$attrs['tabs_content_color']    = $on_dark ? $white : $ink;
			$attrs['tabs_content_bg_color'] = $panel;
		} else {
			$attrs['tabs_content_bg_color'] = $panel;
		}

		$sel_tab = ($tag === 'pl_tabs')
			? '{{element}} .pagelayer-tablinks'
			: '{{element}} .pagelayer-accordion-tabs';
		$sel_panel = ($tag === 'pl_tabs')
			? '{{element}} .pagelayer-tab,{{element}} .pagelayer-tabcontainer'
			: '{{element}} .pagelayer-accordion-panel';

		$tab_fg    = $on_dark ? $white : $ink;
		$panel_fg  = $on_dark ? $white : $ink;
		$rule = $sel_tab . '{color:' . $tab_fg . '!important;background-color:' . $tab_bg . '!important;}'
			. $sel_tab . '.active,{{element}} .active ' . (($tag === 'pl_tabs') ? '.pagelayer-tablinks' : '.pagelayer-accordion-tabs')
			. '{color:' . $white . '!important;background-color:var(--pagelayer-color-primary,#4f46e5)!important;}'
			. $sel_panel . '{color:' . $panel_fg . '!important;background-color:' . $panel . '!important;}';
		$attrs = $this->append_ele_css($attrs, $rule);

		return $attrs;
	}

	private function style_counter($attrs, $on_dark) {
		if (!is_array($attrs)) {
			$attrs = array();
		}

		$start = isset($attrs['counter_start_number']) ? (int) $attrs['counter_start_number'] : 0;
		if ($start < 1) {
			$attrs['counter_start_number'] = '1';
			$start = 1;
		}
		$end = isset($attrs['counter_end_number']) ? (int) $attrs['counter_end_number'] : 0;
		if ($end <= $start) {
			$attrs['counter_end_number'] = (string) max(100, $start + 99);
		}

		$num = isset($attrs['counter_number_color']) ? (string) $attrs['counter_number_color'] : '';
		$lbl = isset($attrs['counter_text_color']) ? (string) $attrs['counter_text_color'] : '';
		$bg  = isset($attrs['ele_bg_color']) ? (string) $attrs['ele_bg_color'] : '';
		$surface_dark = $bg !== '' ? $this->color_is_dark($bg, $on_dark) : $on_dark;

		if ($num === '' || ($bg !== '' && $this->colors_clash($num, $bg)) || ($surface_dark && $this->color_is_dark($num, true)) || (!$surface_dark && $this->color_is_light($num))) {
			$attrs['counter_number_color'] = $surface_dark ? '#ffffff' : '$primary';
		}
		if ($lbl === '' || ($bg !== '' && $this->colors_clash($lbl, $bg)) || ($surface_dark && $this->color_is_dark($lbl, true)) || (!$surface_dark && $this->color_is_light($lbl))) {
			$attrs['counter_text_color'] = $surface_dark ? 'rgba(255,255,255,0.85)' : '#111827';
		}

		return $attrs;
	}

	private function contrast_text_css_for_tag($tag, $on_dark = true) {
		$selectors = array(
			'pl_text'        => '{{element}},{{element}} .pagelayer-text-holder,{{element}} .pagelayer-text-holder *',
			'pl_heading'     => '{{element}} .pagelayer-heading-holder,{{element}} .pagelayer-heading-holder *',
			'pl_iconbox'     => '{{element}} .pagelayer-service-text,{{element}} .pagelayer-service-heading,{{element}} .pagelayer-service-details',
			'pl_service'     => '{{element}} .pagelayer-service-text,{{element}} .pagelayer-service-heading,{{element}} .pagelayer-service-details',
			'pl_testimonial' => '{{element}} .pagelayer-testimonial-content,{{element}} .pagelayer-testimonial-author,{{element}} .pagelayer-testimonial-designation',
			'pl_quote'       => '{{element}} .pagelayer-quote-content,{{element}} .pagelayer-cite-holder',
			'pl_list'        => '{{element}} .pagelayer-list-item,{{element}} li,{{element}} .pagelayer-list-li',
			'pl_list_item'   => '{{element}},{{element}} .pagelayer-list-li,{{element}} .pagelayer-list-item',
			'pl_counter'     => '{{element}} .pagelayer-counter-text,{{element}} .pagelayer-counter-title,{{element}} .pagelayer-counter-prefix,{{element}} .pagelayer-counter-suffix',
			'pl_accordion'   => '{{element}} .pagelayer-accordion-tab,{{element}} .pagelayer-accordion-item',
			'pl_accordion_item' => '{{element}},{{element}} .pagelayer-accordion-tab',
			'pl_tabs'        => '{{element}} .pagelayer-tab-title,{{element}} .pagelayer-tab-content',
			'pl_tab'         => '{{element}},{{element}} .pagelayer-tab-content',
			'pl_alert'       => '{{element}} .pagelayer-alert-title,{{element}} .pagelayer-alert-text',
			'pl_pricing'     => '{{element}} .pagelayer-pricing-title,{{element}} .pagelayer-pricing-price,{{element}} .pagelayer-pricing-text,{{element}} li',
			'pl_address'     => '{{element}},{{element}} .pagelayer-address,{{element}} .pagelayer-address-holder',
			'pl_phone'       => '{{element}},{{element}} .pagelayer-phone,{{element}} .pagelayer-phone-holder',
			'pl_email'       => '{{element}},{{element}} .pagelayer-email,{{element}} .pagelayer-email-holder',
		);
		if (!isset($selectors[$tag])) {
			return '';
		}
		$color = $on_dark ? '#ffffff' : '#111827';
		return $selectors[$tag] . '{color:' . $color . ';}';
	}

	private function color_is_light($val) {
		$val = trim((string) $val);
		if ($val === '') {
			return false;
		}
		$low = strtolower($val);
		$light_names = array(
			'white' => 1, 'ivory' => 1, 'snow' => 1, 'whitesmoke' => 1, 'ghostwhite' => 1,
			'azure' => 1, 'beige' => 1, 'linen' => 1, 'seashell' => 1, '#fff' => 1, '#ffffff' => 1,
			'#f8fafc' => 1, '#f9fafb' => 1, '#fafafa' => 1, '#f5f5f5' => 1, '#eee' => 1, '#eeeeee' => 1,
		);
		if (isset($light_names[$low])) {
			return true;
		}
		if (isset($val[0]) && $val[0] === '$') {
			$key = strtolower(substr($val, 1));
			if (in_array($key, array('bg', 'surface', 'light', 'light_bg'), true)) {
				$hex = $this->resolve_color_hex($val);
				if ($hex === '') {
					return true;
				}
				return !$this->hex_is_dark($hex, false);
			}
		}
		$hex = $this->resolve_color_hex($val);
		if ($hex === '') {
			return false;
		}
		$hex = ltrim($hex, '#');
		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if (strlen($hex) < 6) {
			return false;
		}
		$r = hexdec(substr($hex, 0, 2));
		$g = hexdec(substr($hex, 2, 2));
		$b = hexdec(substr($hex, 4, 2));
		$lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
		return $lum >= 0.78;
	}

	private function append_ele_css($attrs, $rule) {
		if (!is_array($attrs)) {
			$attrs = array();
		}
		$rule = trim((string) $rule);
		if ($rule === '') {
			return $attrs;
		}
		$css = isset($attrs['ele_css']) ? (string) $attrs['ele_css'] : '';
		if ($css !== '' && strpos($css, $rule) !== false) {
			return $attrs;
		}
		$attrs['ele_css'] = $css . $rule;
		return $attrs;
	}

	private function color_token_for_key($key, $on_dark, $light, $text, $brand, $accent) {
		$key_l = strtolower((string) $key);
		if ($on_dark && preg_match('/text|desc|content|quote|cite|label|color/i', $key_l) && !preg_match('/icon|number|btn|button|bg/i', $key_l)) {
			return $light;
		}
		if (preg_match('/icon|number|count|accent|star/i', $key_l)) {
			return $on_dark ? $light : $accent;
		}
		if (preg_match('/heading|title|name/i', $key_l)) {
			return $on_dark ? $light : $brand;
		}
		if (preg_match('/text|desc|content|quote|cite|label/i', $key_l)) {
			return $on_dark ? $light : $text;
		}
		if (preg_match('/btn|button/i', $key_l) && preg_match('/color/i', $key_l) && !preg_match('/bg/i', $key_l)) {
			return $light;
		}
		return $on_dark ? $light : $brand;
	}

	private function typo_with_family($typo, $family, $size = '', $weight = '') {
		$parts = array_fill(0, 11, '');
		if (is_string($typo) && $typo !== '') {
			if (isset($typo[0]) && $typo[0] === '$') {
				$token_family = $this->global_font_family(substr($typo, 1));
				if ($token_family !== '') {
					$family = $token_family;
				}
			} else {
				$existing = explode(',', $typo);
				foreach ($existing as $i => $part) {
					if ($i < 11) {
						$parts[$i] = $part;
					}
				}
			}
		}
		if ($family !== '' && $parts[0] === '') {
			$parts[0] = $family;
		}
		if ($size !== '' && $parts[1] === '') {
			$parts[1] = preg_replace('/[^0-9.]/', '', (string) $size);
		}
		if ($weight !== '' && $parts[3] === '') {
			$parts[3] = (string) $weight;
		}
		if ($parts[7] === '' && $size !== '' && (int) $size >= 30) {
			$parts[7] = '1.15';
		}
		return implode(',', $parts);
	}

	private function remap_tree($nodes) {
		if (!is_array($nodes)) {
			return $nodes;
		}

		$out = array();
		foreach ($nodes as $node) {
			if (!is_array($node)) {
				$out[] = $node;
				continue;
			}
			if (!empty($node['tag'])) {
				$tag         = $this->normalize_tag($node['tag']);
				$node['tag'] = $tag;
				if (!isset($node['attrs']) || !is_array($node['attrs'])) {
					$node['attrs'] = array();
				}
				$this->remap_node_fields($node, $this->get_attr_index($tag));
			}
			if (isset($node['content']) && is_array($node['content'])) {
				$node['content'] = $this->remap_tree($node['content']);
			}
			$out[] = $node;
		}
		return $out;
	}

	private function sanitize_nodes($nodes) {
		if (!is_array($nodes)) {
			return $nodes;
		}
		$out = array();
		foreach ($nodes as $node) {
			if (is_array($node)) {
				$out[] = $this->sanitize_node($node);
			}
		}
		return $out;
	}

	private function sanitize_node($node) {
		global $pagelayer;

		if (!is_array($node) || empty($node['tag'])) {
			return $node;
		}

		$tag = $this->normalize_tag($node['tag']);
		$node['tag'] = $tag;

		if (!isset($node['attrs']) || !is_array($node['attrs'])) {
			$node['attrs'] = array();
		}

		if (isset($node['content']) && is_string($node['content'])) {
			$node['content'] = $this->strip_placeholder_copy($node['content']);
			$node['content'] = preg_replace('/\s*style\s*=\s*([\'"]).*?\1/i', '', $node['content']);
			$node['content'] = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $node['content']);
		}

		// Strip leaked template tokens like {ribbon_text}, but keep {{element}}
		// in ele_css / ele_attributes — that selector is how Pagelayer CSS works.
		foreach ($node['attrs'] as $k => $v) {
			if (!is_string($v)) {
				continue;
			}
			if ($k === 'ele_css' || $k === 'ele_attributes') {
				continue;
			}
			$v = $this->strip_placeholder_copy($v);
			$node['attrs'][$k] = $v;
		}

		$this->ensure_shortcodes();
		$def = isset($pagelayer->shortcodes[$tag]) ? $pagelayer->shortcodes[$tag] : array();
		if (!empty($def['holder']) && empty($def['innerHTML']) && isset($node['content']) && is_string($node['content']) && trim($node['content']) !== '') {
			$node['content'] = array(
				array(
					'tag'     => 'pl_row',
					'attrs'   => array(),
					'content' => array(
						array(
							'tag'     => 'pl_col',
							'attrs'   => array('col' => 12),
							'content' => array(
								array(
									'tag'     => 'pl_text',
									'attrs'   => array(),
									'content' => $node['content'],
								),
							),
						),
					),
				),
			);
		}

		$index = $this->get_attr_index($tag);
		$this->remap_node_fields($node, $index);
		$node['attrs'] = $this->fill_style_companions($node['attrs']);
		$node['attrs'] = $this->sanitize_attrs($tag, $node['attrs']);
		$node['attrs'] = $this->fill_style_companions($node['attrs']);
		$node['attrs'] = $this->apply_own_typography($node['attrs'], $index);
		$node['attrs'] = $this->apply_spacing_defaults($tag, $node['attrs']);
		$node['attrs'] = $this->apply_modern_defaults($tag, $node['attrs'], $node);
		$node['attrs'] = $this->fill_style_companions($node['attrs']);

		if (isset($node['content']) && is_array($node['content'])) {
			$node['content'] = $this->sanitize_nodes($node['content']);
		}

		return $node;
	}

	/**
	 * Widget schema defaults are editor-only — CSS is emitted only when the
	 * attr is actually set. AI layouts otherwise stack icon boxes / testimonials
	 * with zero column gap and zero widget spacing.
	 */
	private function apply_spacing_defaults($tag, $attrs) {
		if (!is_array($attrs)) {
			$attrs = array();
		}

		$is_inner_row = ($tag === 'pl_inner_row');
		$tag = str_replace(array('pl_inner_row', 'pl_inner_col'), array('pl_row', 'pl_col'), $tag);

		if ($tag === 'pl_row') {
			if (!isset($attrs['col_gap']) || $attrs['col_gap'] === '' || $attrs['col_gap'] === '0') {
				$attrs['col_gap'] = '20';
			}
			if ($is_inner_row) {
				// Inner rows must not stretch to the viewport or inherit section padding —
				// that creates blank bands on the frontend that the editor does not show.
				if (isset($attrs['stretch']) && $attrs['stretch'] === 'full') {
					$attrs['stretch'] = 'auto';
				}
			} else {
				if (!isset($attrs['ele_padding']) || $attrs['ele_padding'] === '') {
					$attrs['ele_padding'] = '50px,20px,50px,20px';
				}
				if (!isset($attrs['stretch']) || $attrs['stretch'] === '') {
					$attrs['stretch'] = 'full';
				}
				$attrs = $this->pin_col_gap_horizontal($attrs);
			}
		}

		if ($tag === 'pl_col') {
			if (!isset($attrs['widget_space']) || $attrs['widget_space'] === '' || (string) $attrs['widget_space'] === '0') {
				$attrs['widget_space'] = '20';
			}
		}

		$cards = $this->card_widget_tags();
		if (isset($cards[$tag])) {
			if (!isset($attrs['ele_padding']) || $attrs['ele_padding'] === '') {
				$attrs['ele_padding'] = '32px,28px,32px,28px';
			}
		}

		return $attrs;
	}

	/**
	 * Live index of a widget's real attribute names, labels, and types.
	 */
	private function get_attr_index($tag) {
		static $cache = array();

		$tag = str_replace(array('pl_inner_row', 'pl_inner_col'), array('pl_row', 'pl_col'), $tag);
		if (array_key_exists($tag, $cache)) {
			return $cache[$tag];
		}

		global $pagelayer;
		$this->ensure_shortcodes();
		if (empty($pagelayer->shortcodes[$tag])) {
			return $cache[$tag] = null;
		}

		$schema   = $this->extract_widget_schema($tag, $pagelayer->shortcodes[$tag]);
		$own_keys = isset($pagelayer->shortcodes[$tag]['settings']) && is_array($pagelayer->shortcodes[$tag]['settings'])
			? array_keys($pagelayer->shortcodes[$tag]['settings'])
			: array();

		$index = array(
			'allowed'      => array(),
			'labels'       => array(),
			'by_type'      => array(),
			'own'          => array(),
			'typography'   => array(),
			'content_attr' => !empty($schema['innerHTML']) ? $schema['innerHTML'] : '',
		);

		if (empty($schema['sections']) || !is_array($schema['sections'])) {
			return $cache[$tag] = $index;
		}

		foreach ($schema['sections'] as $section_key => $section) {
			$is_own = in_array($section_key, $own_keys, true);
			if (empty($section['properties']) || !is_array($section['properties'])) {
				continue;
			}
			foreach ($section['properties'] as $key => $prop) {
				$type  = isset($prop['type']) ? $prop['type'] : '';
				$label = isset($prop['label']) ? strtolower(trim(wp_strip_all_tags((string) $prop['label']))) : '';
				$index['allowed'][$key] = $type;
				$index['labels'][$key]  = $label;
				if ($type !== '') {
					$index['by_type'][$type][] = $key;
				}
				if ($is_own) {
					$index['own'][$key] = true;
					if ($type === 'typography' && strpos($key, '_hover') === false) {
						$index['typography'][] = $key;
					}
				}
			}
		}

		return $cache[$tag] = $index;
	}

	/**
	 * Map guessed attr names onto the widget's live schema (labels, suffixes, value types).
	 */
	private function remap_node_fields(&$node, $index) {
		if (!is_array($index) || empty($node['attrs']) || !is_array($node['attrs'])) {
			return;
		}

		$out          = array();
		$content_attr = $index['content_attr'];
		$aliases      = $this->common_style_aliases();

		foreach ($node['attrs'] as $key => $val) {
			if (isset($index['allowed'][$key])) {
				$out[$key] = $val;
				continue;
			}

			if ($key === 'content' && $content_attr !== '' && is_string($val)) {
				if (!isset($node['content']) || $node['content'] === '' || $node['content'] === array()) {
					$node['content'] = $val;
				}
				continue;
			}

			$key_l = strtolower((string) $key);
			if (isset($aliases[$key_l]) && isset($index['allowed'][$aliases[$key_l]])) {
				$mapped = $aliases[$key_l];
				if (!isset($out[$mapped]) || $out[$mapped] === '') {
					$out[$mapped] = $val;
				}
				continue;
			}

			$mapped = $this->guess_attr_key($key, $val, $index);
			if ($mapped === null) {
				$out[$key] = $val;
				continue;
			}

			if ($mapped === $content_attr && is_string($val)) {
				if (!isset($node['content']) || $node['content'] === '' || $node['content'] === array()) {
					$node['content'] = $val;
				}
				continue;
			}

			if (!isset($out[$mapped]) || $out[$mapped] === '') {
				$out[$mapped] = $val;
			}
		}

		$node['attrs'] = $this->fill_style_companions($out);
	}

	/**
	 * Guessed names models often emit instead of Pagelayer common-style keys.
	 */
	private function common_style_aliases() {
		return array(
			'padding'          => 'ele_padding',
			'margin'           => 'ele_margin',
			'background'       => 'ele_bg_color',
			'background_color' => 'ele_bg_color',
			'bg'               => 'ele_bg_color',
			'bg_color'         => 'ele_bg_color',
			'bg_type'          => 'ele_bg_type',
			'shadow'           => 'ele_shadow',
			'box_shadow'       => 'ele_shadow',
			'radius'           => 'border_radius',
			'border'           => 'border_type',
			'alignment'        => 'align',
			'text_align'       => 'align',
			'text_color'       => 'color',
			'font'             => 'font_family',
			'fontsize'         => 'font_size',
			'fontweight'       => 'font_weight',
		);
	}

	/**
	 * Colors/borders/buttons only render when their companion attr is set
	 * on the same node. Fill those so AI styling is not silently dropped.
	 */
	private function fill_style_companions($attrs) {
		if (!is_array($attrs)) {
			return array();
		}

		if (!empty($attrs['ele_bg_color']) && (empty($attrs['ele_bg_type']) || $attrs['ele_bg_type'] === '')) {
			$attrs['ele_bg_type'] = 'color';
		}
		if (!empty($attrs['ele_bg_gradient']) && (empty($attrs['ele_bg_type']) || $attrs['ele_bg_type'] === '')) {
			$attrs['ele_bg_type'] = 'gradient';
		}
		if (!empty($attrs['ele_bg_img']) && (empty($attrs['ele_bg_type']) || $attrs['ele_bg_type'] === '')) {
			$attrs['ele_bg_type'] = 'image';
		}
		if (!empty($attrs['btn_bg_color']) || !empty($attrs['btn_color'])) {
			if (empty($attrs['type']) || $attrs['type'] === 'pagelayer-btn-default') {
				$attrs['type'] = 'pagelayer-btn-custom';
			}
		}
		if ((!empty($attrs['border_width']) || !empty($attrs['border_color'])) && (empty($attrs['border_type']) || $attrs['border_type'] === '')) {
			$attrs['border_type'] = 'solid';
		}
		if (!empty($attrs['btn_border_width']) || !empty($attrs['btn_border_color']) || !empty($attrs['btn_border_radius'])) {
			if (empty($attrs['btn_border_type']) || $attrs['btn_border_type'] === '') {
				$attrs['btn_border_type'] = 'solid';
			}
		}

		return $attrs;
	}

	private function companion_fill_value($key) {
		$key = (string) $key;
		if ($key === 'border_type' || substr($key, -12) === 'border_type') {
			return 'solid';
		}
		if ($key === 'ele_bg_type' || substr($key, -7) === 'bg_type') {
			return 'color';
		}
		if ($key === 'type') {
			return 'pagelayer-btn-custom';
		}
		return '';
	}

	private function guess_attr_key($key, $val, $index) {
		$key_l = strtolower((string) $key);

		$pick_own = function ($hits) use ($index) {
			$own = array();
			foreach (array_unique($hits) as $ak) {
				if (!empty($index['own'][$ak]) && strpos($ak, '_hover') === false) {
					$own[] = $ak;
				}
			}
			$own = array_values(array_unique($own));
			return count($own) === 1 ? $own[0] : null;
		};

		$suffix_hits = array();
		foreach ($index['allowed'] as $ak => $_type) {
			if (strpos($ak, '_hover') !== false) {
				continue;
			}
			$ak_l = strtolower($ak);
			if ($ak_l === $key_l || substr($ak_l, -(strlen($key_l) + 1)) === '_' . $key_l) {
				$suffix_hits[] = $ak;
			}
		}
		$picked = $pick_own($suffix_hits);
		if ($picked) {
			return $picked;
		}
		if (count($suffix_hits) === 1) {
			return $suffix_hits[0];
		}

		$kn         = preg_replace('/[^a-z0-9]+/', '', $key_l);
		$label_hits = array();
		foreach ($index['labels'] as $ak => $label) {
			if ($label === '' || strpos($ak, '_hover') !== false) {
				continue;
			}
			$ln = preg_replace('/[^a-z0-9]+/', '', $label);
			if ($ln === $kn || $label === $key_l) {
				$label_hits[] = $ak;
			}
		}
		$picked = $pick_own($label_hits);
		if ($picked) {
			return $picked;
		}
		if (count($label_hits) === 1) {
			return $label_hits[0];
		}

		$content_attr = !empty($index['content_attr']) ? $index['content_attr'] : '';
		if ($content_attr !== '') {
			$ca_l = strtolower($content_attr);
			if ($ca_l === $key_l || strpos($ca_l, $key_l . '_') === 0 || substr($ca_l, -(strlen($key_l) + 1)) === '_' . $key_l) {
				return $content_attr;
			}
		}

		$own_of_type = function ($type) use ($index) {
			$hits = array();
			if (empty($index['by_type'][$type])) {
				return $hits;
			}
			foreach ($index['by_type'][$type] as $ak) {
				if (strpos($ak, '_hover') !== false) {
					continue;
				}
				if (!empty($index['own'][$ak])) {
					$hits[] = $ak;
				}
			}
			return array_values(array_unique($hits));
		};

		$type = $this->guess_value_type($val);
		if ($type !== '') {
			$picked = $pick_own($own_of_type($type));
			if ($picked) {
				return $picked;
			}
		}

		$own_images = $own_of_type('image');
		if (count($own_images) === 1) {
			if (in_array($key_l, array('img', 'image', 'src', 'photo', 'picture', 'avatar'), true)) {
				return $own_images[0];
			}
			if ($key_l === 'alt') {
				return $own_images[0] . '-alt';
			}
		}

		$own_icons = $own_of_type('icon');
		if (count($own_icons) === 1 && in_array($key_l, array('icon', 'fa', 'font_icon'), true)) {
			return $own_icons[0];
		}

		return null;
	}

	private function guess_value_type($val) {
		if (!is_string($val) || $val === '') {
			return '';
		}
		if (preg_match('/^(fas|far|fal|fad|fab)\s+fa-/', $val)) {
			return 'icon';
		}
		if (preg_match('#^(https?:)?//#i', $val) && preg_match('/\.(png|jpe?g|gif|webp|svg)(\?|$)/i', $val)) {
			return 'image';
		}
		if (preg_match('/^#([0-9a-f]{3,8})$/i', $val) || (isset($val[0]) && $val[0] === '$')) {
			return 'color';
		}
		return '';
	}

	/**
	 * If the widget has its own typography control, fold common font_* attrs into it.
	 * font_size on the wrapper does not size the inner heading/button text.
	 */
	private function apply_own_typography($attrs, $index) {
		if (!is_array($attrs) || empty($index['typography']) || count($index['typography']) !== 1) {
			return $attrs;
		}

		global $pagelayer;
		$typo_key = $index['typography'][0];
		$props    = !empty($pagelayer->typo_props) && is_array($pagelayer->typo_props)
			? array_values($pagelayer->typo_props)
			: array(
				'font-family', 'font-size', 'font-style', 'font-weight', 'font-variant',
				'text-decoration-line', 'text-decoration-style', 'line-height',
				'text-transform', 'letter-spacing', 'word-spacing',
			);

		if (!empty($attrs[$typo_key]) && is_string($attrs[$typo_key]) && strpos($attrs[$typo_key], '$') === 0) {
			$family = $this->global_font_family(substr($attrs[$typo_key], 1));
			$attrs[$typo_key] = $this->typo_with_family($attrs[$typo_key], $family);
			foreach ($props as $css) {
				unset($attrs[str_replace('-', '_', $css)]);
			}
			return $attrs;
		}

		$parts = array_fill(0, count($props), '');
		if (!empty($attrs[$typo_key]) && is_string($attrs[$typo_key])) {
			$existing = explode(',', $attrs[$typo_key]);
			foreach ($existing as $i => $part) {
				if ($i < count($parts)) {
					$parts[$i] = $part;
				}
			}
		}

		$changed = false;
		foreach ($props as $i => $css) {
			$common = str_replace('-', '_', $css);
			if (!isset($attrs[$common]) || $attrs[$common] === '' || $attrs[$common] === '0') {
				continue;
			}
			$val = (string) $attrs[$common];
			if ($css === 'font-size' || $css === 'line-height' || $css === 'letter-spacing' || $css === 'word-spacing') {
				$val = preg_replace('/[^0-9.]/', '', $val);
			}
			$parts[$i] = $val;
			unset($attrs[$common]);
			$changed = true;
		}

		if ($changed && implode('', $parts) !== '') {
			$attrs[$typo_key] = implode(',', $parts);
		}

		return $attrs;
	}

	private function sanitize_attrs($tag, $attrs) {
		if (!is_array($attrs)) {
			return $attrs;
		}

		$rules = $this->widget_attr_rules($tag);
		if (!is_array($rules) || empty($rules['allowed'])) {
			return $attrs;
		}

		$reserved = array(
			'pagelayer-id' => 1, 'pagelayer-srcset' => 1, 'global_id' => 1, 'is_not_sc' => 1,
			'ele_classes'  => 1, 'ele_css' => 1,
		);
		$clean    = array();

		foreach ($attrs as $key => $val) {
			if (isset($reserved[$key]) || isset($rules['allowed'][$key])) {
				$clean[$key] = $val;
				continue;
			}
			$dash = strrpos($key, '-');
			if ($dash !== false && isset($rules['allowed'][substr($key, 0, $dash)])) {
				$clean[$key] = $val;
			}
		}

		if (!empty($rules['req']) && is_array($rules['req'])) {
			foreach ($rules['req'] as $attr_key => $requires) {
				if (!isset($clean[$attr_key]) || $clean[$attr_key] === '' || !is_array($requires)) {
					continue;
				}
				foreach ($requires as $dep_key => $dep_val) {
					if (!is_string($dep_key) || $dep_key === '') {
						continue;
					}
					$negated  = ($dep_key[0] === '!');
					$real_key = $negated ? substr($dep_key, 1) : $dep_key;
					if ($real_key === '') {
						continue;
					}
					$current = isset($clean[$real_key]) ? $clean[$real_key] : '';

					if ($negated) {
						// req:!border_type='' means border_type must be non-empty
						// or the styled attr is discarded at render.
						$forbidden = is_array($dep_val) ? $dep_val : array($dep_val);
						$is_forbidden = in_array((string) $current, array_map('strval', $forbidden), true);
						if ($current !== '' && !$is_forbidden) {
							continue;
						}
						$fill = $this->companion_fill_value($real_key);
						if ($fill !== '') {
							$clean[$real_key] = $fill;
						}
						continue;
					}

					$ok = is_array($dep_val)
						? in_array($current, $dep_val, false)
						: ((string) $dep_val === (string) $current);
					if ($ok) {
						continue;
					}
					$clean[$real_key] = is_array($dep_val) ? reset($dep_val) : $dep_val;
				}
			}
		}

		return $clean;
	}

	public function validate_nodes($nodes) {
		return array('errors' => array(), 'warnings' => array());
	}

	/**
	 * Normalize AI JSON nodes for Pagelayer render (ids, innerHTML, CSS units,
	 * markup defaults). Does not expand canned section presets.
	 */
	private function normalize_ai_nodes($nodes) {
		if (!is_array($nodes)) {
			return $nodes;
		}
		$out = array();
		foreach ($nodes as $node) {
			if (is_array($node)) {
				$out[] = $this->normalize_ai_node($node);
			}
		}
		return $out;
	}

	private function normalize_ai_node($node) {
		if (!is_array($node)) {
			return $node;
		}

		$tag = isset($node['tag']) ? (string) $node['tag'] : (isset($node['type']) ? (string) $node['type'] : '');
		$node['tag'] = $this->normalize_tag($tag);

		if (empty($node['tag'])) {
			$node['tag'] = (isset($node['content']) && is_array($node['content'])) ? 'pl_row' : 'pl_text';
		}

		if (!isset($node['attrs']) || !is_array($node['attrs'])) {
			$node['attrs'] = array();
		}

		if (empty($node['attrs']['pagelayer-id']) && function_exists('pagelayer_create_id')) {
			$node['attrs']['pagelayer-id'] = pagelayer_create_id();
		}

		$this->bridge_inner_html($node);
		$this->apply_markup_defaults($node);
		$this->add_missing_css_units($node);

		if ($node['tag'] === 'pl_row' && !isset($node['attrs']['stretch'])) {
			$node['attrs']['stretch'] = 'full';
		}

		if (isset($node['content']) && is_array($node['content'])) {
			$node['content'] = $this->normalize_ai_nodes($node['content']);
		}

		return $node;
	}

	private function bridge_inner_html(&$node) {
		global $pagelayer;

		$tag = isset($node['tag']) ? $node['tag'] : '';
		if ($tag === '') {
			return;
		}

		$this->ensure_shortcodes();
		$inner_key = isset($pagelayer->shortcodes[$tag]['innerHTML']) ? $pagelayer->shortcodes[$tag]['innerHTML'] : '';

		if ($inner_key === '') {
			if (
				isset($node['content']) && is_string($node['content']) && trim($node['content']) !== ''
				&& empty($node['attrs']['text'])
			) {
				$rules = $this->widget_attr_rules($tag);
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

		if (isset($node['content']) && is_array($node['content'])) {
			return;
		}

		if (isset($node['content']) && is_string($node['content']) && trim($node['content']) !== '') {
			unset($node['attrs'][$inner_key]);
			return;
		}

		$node['content'] = $node['attrs'][$inner_key];
		unset($node['attrs'][$inner_key]);
	}

	private function add_missing_css_units(&$node) {
		if (empty($node['tag']) || empty($node['attrs']) || !is_array($node['attrs'])) {
			return;
		}

		$rules = $this->widget_attr_rules($node['tag']);
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

	private function apply_markup_defaults(&$node) {
		global $pagelayer;
		static $cache = array();

		$tag = isset($node['tag']) ? $node['tag'] : '';
		if ($tag === '') {
			return;
		}

		$this->ensure_shortcodes();
		if (empty($pagelayer->shortcodes[$tag]['html'])) {
			return;
		}

		if (!isset($cache[$tag])) {
			$def    = $pagelayer->shortcodes[$tag];
			$schema = $this->extract_widget_schema($tag, $def);

			$props = array();
			foreach ($schema['sections'] as $section) {
				foreach ($section['properties'] as $key => $prop) {
					$props[$key] = $prop;
				}
			}

			preg_match_all('/\{\{\{?([a-zA-Z0-9_\-]+)\}?\}\}/', $def['html'], $matches);

			$inner = isset($def['innerHTML']) ? $def['innerHTML'] : '';
			$fill  = array();

			foreach (array_unique($matches[1]) as $name) {
				if (!isset($props[$name]) || $name === $inner) {
					continue;
				}
				$type = isset($props[$name]['type']) ? $props[$name]['type'] : '';
				if (in_array($type, array('text', 'textarea', 'editor'), true)) {
					continue;
				}
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

	public function nodes_to_shortcode($nodes, $depth = 0) {
		if (!is_array($nodes)) {
			return is_string($nodes) ? $nodes : '';
		}

		$out = '';
		foreach ($nodes as $node) {
			if (!is_array($node) || empty($node['tag'])) {
				continue;
			}

			$tag = $this->normalize_tag($node['tag']);
			if ($depth >= 2) {
				if ($tag === 'pl_row') {
					$tag = 'pl_inner_row';
				} elseif ($tag === 'pl_col') {
					$tag = 'pl_inner_col';
				}
			}

			$attrs    = isset($node['attrs']) && is_array($node['attrs']) ? $node['attrs'] : array();
			$attr_str = $this->encode_shortcode_atts($attrs);
			$content  = isset($node['content']) ? $node['content'] : '';

			if (is_array($content)) {
				$inner = $this->nodes_to_shortcode($content, $depth + 1);
			} else {
				$inner = (string) $content;
			}

			$out .= '[' . $tag . $attr_str . ']' . $inner . '[/' . $tag . ']';
		}

		return $out;
	}

	private function encode_shortcode_atts($attrs) {
		if (empty($attrs) || !is_array($attrs)) {
			return '';
		}

		$skip = array('pagelayer-id' => 1, 'pagelayer-srcset' => 1);
		$str  = '';
		foreach ($attrs as $key => $val) {
			if (isset($skip[$key]) || $val === null) {
				continue;
			}
			if (is_bool($val)) {
				$val = $val ? 'true' : '';
			} elseif (is_array($val)) {
				$flat = true;
				foreach ($val as $item) {
					if (!is_scalar($item)) {
						$flat = false;
						break;
					}
				}
				$val = $flat ? implode(',', $val) : wp_json_encode($val);
			}
			$val = (string) $val;
			$val = str_replace(array('[', ']'), array('&#91;', '&#93;'), $val);
			$val = str_replace('"', '&quot;', $val);
			$str .= ' ' . $key . '="' . $val . '"';
		}
		return $str;
	}

	/**
	 * Widgets that form a repeating card grid (icon boxes, testimonials, etc.).
	 */
	private function card_widget_tags() {
		return array(
			'pl_iconbox'     => 1,
			'pl_service'     => 1,
			'pl_testimonial' => 1,
			'pl_counter'     => 1,
			'pl_pricing'     => 1,
			'pl_flipbox'     => 1,
			'pl_review'      => 1,
			'pl_author_box'  => 1,
			'pl_countdown'   => 1,
			'pl_call'        => 1,
			'pl_quote'       => 1,
		);
	}

	private function is_row_tag($tag) {
		$tag = $this->normalize_tag($tag);
		return ($tag === 'pl_row' || $tag === 'pl_inner_row');
	}

	private function is_col_tag($tag) {
		$tag = $this->normalize_tag($tag);
		return ($tag === 'pl_col' || $tag === 'pl_inner_col');
	}

	private function is_card_widget($node) {
		if (!is_array($node) || empty($node['tag'])) {
			return false;
		}
		$tags = $this->card_widget_tags();
		return isset($tags[$this->normalize_tag($node['tag'])]);
	}

	private function col_width_value($node) {
		if (!is_array($node) || empty($node['attrs']['col'])) {
			return 12;
		}
		$c = intval($node['attrs']['col']);
		return $c > 0 ? $c : 12;
	}

	private function col_children($col) {
		if (!is_array($col) || !isset($col['content']) || !is_array($col['content'])) {
			return array();
		}
		$out = array();
		foreach ($col['content'] as $child) {
			if (is_array($child)) {
				$out[] = $child;
			}
		}
		return $out;
	}

	private function count_card_widgets($nodes) {
		$n = 0;
		foreach ((array) $nodes as $node) {
			if ($this->is_card_widget($node)) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * A column is a "card cell" when it holds one card widget (optionally with
	 * a small decorative sibling like stars / a badge heading).
	 */
	private function is_card_column($col) {
		if (!is_array($col) || empty($col['tag']) || !$this->is_col_tag($col['tag'])) {
			return false;
		}
		$children = $this->col_children($col);
		if (empty($children)) {
			return false;
		}
		$cards  = 0;
		$others = 0;
		foreach ($children as $child) {
			if ($this->is_card_widget($child)) {
				$cards++;
				continue;
			}
			$tag = !empty($child['tag']) ? $this->normalize_tag($child['tag']) : '';
			if ($this->is_row_tag($tag) || $this->is_col_tag($tag)) {
				return false;
			}
			if (in_array($tag, array('pl_heading', 'pl_stars', 'pl_icon', 'pl_badge'), true)) {
				continue;
			}
			$others++;
		}
		return ($cards === 1 && $others === 0);
	}

	private function make_col_node($width, $content, $extra = array()) {
		$attrs = array_merge(array('col' => (string) intval($width)), $extra);
		return array(
			'tag'     => 'pl_col',
			'attrs'   => $attrs,
			'content' => is_array($content) ? array_values($content) : array($content),
		);
	}

	private function is_card_companion($node) {
		if (!is_array($node) || empty($node['tag'])) {
			return false;
		}
		$tag = $this->normalize_tag($node['tag']);
		if (in_array($tag, array('pl_stars', 'pl_icon', 'pl_badge'), true)) {
			return true;
		}
		if ($tag !== 'pl_heading') {
			return false;
		}
		$content = (isset($node['content']) && is_string($node['content'])) ? $node['content'] : '';
		if (preg_match('/<h[1-3]\b/i', $content)) {
			return false;
		}
		if (preg_match('/[⭐★]|stars?/i', $content)) {
			return true;
		}
		$text = trim(wp_strip_all_tags($content));
		return (strlen($text) > 0 && strlen($text) <= 24);
	}

	/**
	 * Split a widget list into card units vs other content.
	 * A star rating / badge heading immediately before a card stays in that card's column.
	 */
	private function split_card_units($nodes) {
		$runs  = array();
		$other = array();
		$cards = array();
		$list  = array_values((array) $nodes);
		$n     = count($list);

		$flush_cards = function () use (&$cards, &$runs) {
			if (!empty($cards)) {
				$runs[] = array('type' => 'cards', 'units' => $cards);
				$cards  = array();
			}
		};
		$flush_other = function () use (&$other, &$runs) {
			if (!empty($other)) {
				$runs[] = array('type' => 'other', 'nodes' => $other);
				$other  = array();
			}
		};

		$i = 0;
		while ($i < $n) {
			$node = $list[$i];
			if (!is_array($node)) {
				$i++;
				continue;
			}
			$next = ($i + 1 < $n) ? $list[$i + 1] : null;

			if ($this->is_card_widget($node)) {
				$flush_other();
				$cards[] = array($node);
				$i++;
				continue;
			}
			if ($this->is_card_companion($node) && is_array($next) && $this->is_card_widget($next)) {
				$flush_other();
				$cards[] = array($node, $next);
				$i += 2;
				continue;
			}

			$flush_cards();
			$other[] = $node;
			$i++;
		}
		$flush_cards();
		$flush_other();
		return $runs;
	}

	/**
	 * Walk the tree and turn stacked / over-wide card groups into sibling rows
	 * of up to 3 columns.
	 */
	private function reflow_card_grids($nodes) {
		if (!is_array($nodes) || empty($nodes)) {
			return $nodes;
		}
		$out = array();
		foreach ($nodes as $node) {
			if (!is_array($node) || empty($node['tag'])) {
				$out[] = $node;
				continue;
			}
			if ($this->is_row_tag($node['tag'])) {
				foreach ($this->reflow_row($node) as $row) {
					$out[] = $row;
				}
				continue;
			}
			$out[] = $node;
		}
		return $out;
	}

	/**
	 * One row -> 1+ rows with card grids of at most 3 columns.
	 */
	private function reflow_row($row) {
		if (!is_array($row) || empty($row['tag']) || !$this->is_row_tag($row['tag'])) {
			return array($row);
		}

		$cols = isset($row['content']) && is_array($row['content']) ? array_values($row['content']) : array();
		$segments = array();

		foreach ($cols as $col) {
			if (!is_array($col) || empty($col['tag'])) {
				continue;
			}
			if (!$this->is_col_tag($col['tag'])) {
				$segments[] = array('kind' => 'block', 'cols' => array($this->make_col_node(12, array($col))));
				continue;
			}

			$width    = $this->col_width_value($col);
			$children = $this->col_children($col);
			$pending  = array();

			$flush_pending = function () use (&$pending, &$segments, $col) {
				if (empty($pending)) {
					return;
				}
				$col_copy            = $col;
				$col_copy['content'] = $pending;
				$segments[]          = array('kind' => 'block', 'cols' => array($col_copy));
				$pending             = array();
			};

			foreach ($children as $child) {
				if (!is_array($child) || empty($child['tag'])) {
					continue;
				}

				if ($this->is_row_tag($child['tag'])) {
					$nested_rows = $this->reflow_row($child);
					if ($width >= 8) {
						$flush_pending();
						foreach ($nested_rows as $nr) {
							$nr_cols = isset($nr['content']) && is_array($nr['content']) ? array_values($nr['content']) : array();
							$all_cards = !empty($nr_cols);
							foreach ($nr_cols as $nc) {
								if (!$this->is_card_column($nc)) {
									$all_cards = false;
									break;
								}
							}
							$segments[] = array(
								'kind' => $all_cards ? 'cards' : 'block',
								'cols' => $nr_cols,
							);
						}
						continue;
					}
					foreach ($nested_rows as $nr) {
						$pending[] = $nr;
					}
					continue;
				}

				$pending[] = $child;
			}

			if ($width >= 8 && $this->count_card_widgets($pending) >= 2) {
				$runs = $this->split_card_units($pending);
				foreach ($runs as $run) {
					if ($run['type'] === 'cards' && !empty($run['units']) && count($run['units']) >= 2) {
						$card_cols = array();
						foreach ($run['units'] as $unit) {
							$card_cols[] = $this->make_col_node(4, $unit);
						}
						$segments[] = array('kind' => 'cards', 'cols' => $card_cols);
					} else {
						$content = array();
						if (!empty($run['units']) && is_array($run['units'])) {
							foreach ($run['units'] as $unit) {
								foreach ((array) $unit as $unit_node) {
									$content[] = $unit_node;
								}
							}
						} elseif (!empty($run['nodes'])) {
							$content = $run['nodes'];
						}
						$col_copy                 = $col;
						$col_copy['attrs']['col'] = 12;
						$col_copy['content']      = $content;
						$this->strip_column_card_chrome($col_copy);
						$segments[] = array('kind' => 'block', 'cols' => array($col_copy));
					}
				}
				continue;
			}

			if (empty($pending)) {
				continue;
			}

			$col['content'] = $pending;
			if ($this->is_card_column($col)) {
				$segments[] = array('kind' => 'cards', 'cols' => array($col));
			} else {
				$segments[] = array('kind' => 'block', 'cols' => array($col));
			}
		}

		$merged = array();
		foreach ($segments as $seg) {
			$last = end($merged);
			if ($last && $last['kind'] === $seg['kind']) {
				$merged[count($merged) - 1]['cols'] = array_merge($last['cols'], $seg['cols']);
			} else {
				$merged[] = $seg;
			}
		}

		foreach ($merged as $m_i => $seg) {
			if ($seg['kind'] === 'block' && $this->looks_like_equal_grid($seg['cols'])) {
				$merged[$m_i]['kind'] = 'cards';
			}
		}

		$rows      = array();
		$first     = true;
		$seg_count = count($merged);
		foreach ($merged as $s_i => $seg) {
			$is_last_seg = ($s_i === $seg_count - 1);
			if ($seg['kind'] === 'cards') {
				$chunks = $this->chunk_card_columns($seg['cols']);
				$n_ch   = count($chunks);
				foreach ($chunks as $c_i => $chunk) {
					$rows[] = $this->row_from_cols($chunk, $row, $first, $is_last_seg && ($c_i === $n_ch - 1));
					$first  = false;
				}
			} else {
				$rows[] = $this->row_from_cols($seg['cols'], $row, $first, $is_last_seg);
				$first  = false;
			}
		}

		return !empty($rows) ? $rows : array($row);
	}

	/**
	 * 3+ equal-width narrow columns (3 or 4) are a card grid even when the
	 * cell is a stack of heading/price/button rather than a single card widget.
	 */
	private function looks_like_equal_grid($cols) {
		if (!is_array($cols) || count($cols) < 3) {
			return false;
		}
		$width = null;
		foreach ($cols as $col) {
			$w = $this->col_width_value($col);
			if ($w >= 8) {
				return false;
			}
			if ($width === null) {
				$width = $w;
			} elseif ($w !== $width) {
				return false;
			}
		}
		return in_array($width, array(3, 4), true);
	}

	/**
	 * Split a list of card columns into groups of at most 3 and assign col widths.
	 */
	private function chunk_card_columns($cols) {
		$cols = array_values($cols);
		$n    = count($cols);
		if ($n <= 0) {
			return array();
		}
		if ($n <= 3) {
			$this->assign_grid_widths($cols, $n);
			return array($cols);
		}

		// When exactly 4 items, split into 2 balanced rows of 2 (6 cols each) instead of 3 + 1 orphan!
		if ($n === 4) {
			$chunks = array_chunk($cols, 2);
			foreach ($chunks as $i => $chunk) {
				$this->assign_grid_widths($chunk, 2);
				$chunks[$i] = $chunk;
			}
			return $chunks;
		}

		// If n > 4 leaves a remainder of 1 (e.g. 7 items = 3 + 3 + 1), remove the orphan single card
		// so the grid stays balanced and professional without an isolated trailing card below
		if ($n % 3 === 1) {
			array_pop($cols);
			$n = count($cols);
		}

		$chunks = array_chunk($cols, 3);
		foreach ($chunks as $i => $chunk) {
			$cn = count($chunk);
			if ($cn === 3) {
				$this->assign_grid_widths($chunk, 3);
			} elseif ($cn === 2) {
				$this->assign_grid_widths($chunk, 2);
			} else {
				$this->assign_grid_widths($chunk, 3);
				$chunk[0]['attrs']['col'] = '4';
			}
			$chunks[$i] = $chunk;
		}
		return $chunks;
	}

	private function assign_grid_widths(&$cols, $per_row) {
		$width = 4;
		if ($per_row <= 1) {
			$width = 12;
		} elseif ($per_row === 2) {
			$width = 6;
		}
		foreach ($cols as &$col) {
			if (!isset($col['attrs']) || !is_array($col['attrs'])) {
				$col['attrs'] = array();
			}
			$col['attrs']['col'] = (string) $width;
		}
		unset($col);
	}

	private function strip_column_card_chrome(&$col) {
		if (!isset($col['attrs']) || !is_array($col['attrs'])) {
			return;
		}
		$drop = array(
			'ele_bg_type', 'ele_bg_color', 'ele_shadow',
			'border_type', 'border_width', 'border_color', 'border_radius',
			'ele_padding',
		);
		foreach ($drop as $key) {
			unset($col['attrs'][$key]);
		}
		if (!empty($col['attrs']['ele_css']) && is_string($col['attrs']['ele_css'])) {
			$css = $col['attrs']['ele_css'];
			$css = preg_replace('/\{\{element\}\}\{background-clip:padding-box;[^}]*\}/i', '', $css);
			$css = preg_replace('/\{\{element\}\}\s*\.pagelayer-col-holder\{[^}]*\}/i', '', $css);
			$css = trim($css);
			if ($css === '') {
				unset($col['attrs']['ele_css']);
			} else {
				$col['attrs']['ele_css'] = $css;
			}
		}
	}

	private function row_from_cols($cols, $base_row, $is_first, $is_last) {
		$attrs = array();
		if (is_array($base_row) && isset($base_row['attrs']) && is_array($base_row['attrs'])) {
			$attrs = $base_row['attrs'];
		}
		if (!$is_first) {
			unset($attrs['pagelayer-id'], $attrs['ele_id']);
		}
		if (!isset($attrs['col_gap']) || $attrs['col_gap'] === '' || (string) $attrs['col_gap'] === '0') {
			$attrs['col_gap'] = '20';
		}
		$attrs = $this->pin_col_gap_horizontal($attrs);
		return array(
			'tag'     => 'pl_row',
			'attrs'   => $attrs,
			'content' => array_values($cols),
		);
	}

	/**
	 * PageLayer col_gap is `padding: Npx` on .pagelayer-col-holder (all sides).
	 * The left/right padding is the column gutter. The top/bottom padding
	 * stacks between sibling rows on the frontend as a blank band, while the
	 * editor wrap/overlays hide it. Keep the col_gap value, but only as
	 * horizontal padding so both views match.
	 */
	private function pin_col_gap_horizontal($attrs) {
		if (!is_array($attrs)) {
			$attrs = array();
		}
		$gap = isset($attrs['col_gap']) ? intval($attrs['col_gap']) : 0;
		if ($gap <= 0) {
			$gap = 20;
			$attrs['col_gap'] = '20';
		}
		$rule = '{{element}}{margin-top:0;margin-bottom:0;margin-block-start:0;margin-block-end:0;}'
			. '{{element}}>.pagelayer-row-holder .pagelayer-col-holder{padding-top:0;padding-bottom:0;}';
		$css  = isset($attrs['ele_css']) ? (string) $attrs['ele_css'] : '';
		if (strpos($css, 'padding-top:0') === false) {
			$attrs['ele_css'] = $css . $rule;
		}
		return $attrs;
	}

	/**
	 * Every outer section row keeps its own top and bottom padding so the next
	 * band never sits flush against the columns above it.
	 */
	private function normalize_row_stack_spacing($nodes) {
		if (!is_array($nodes) || empty($nodes)) {
			return $nodes;
		}

		foreach ($nodes as $i => $node) {
			if (!is_array($node) || empty($node['tag'])) {
				continue;
			}
			if (isset($node['content']) && is_array($node['content'])) {
				$nodes[$i]['content'] = $this->normalize_row_stack_spacing($node['content']);
			}
			if (!$this->is_row_tag($node['tag'])) {
				continue;
			}
			if ($this->normalize_tag($node['tag']) === 'pl_inner_row') {
				continue;
			}
			if (!isset($nodes[$i]['attrs']) || !is_array($nodes[$i]['attrs'])) {
				$nodes[$i]['attrs'] = array();
			}
			$nodes[$i]['attrs']['ele_padding'] = $this->section_row_padding(
				isset($nodes[$i]['attrs']['ele_padding']) ? $nodes[$i]['attrs']['ele_padding'] : ''
			);
			if (!empty($nodes[$i]['attrs']['ele_padding_mobile'])) {
				$nodes[$i]['attrs']['ele_padding_mobile'] = $this->section_row_padding($nodes[$i]['attrs']['ele_padding_mobile']);
			}
		}

		return $nodes;
	}

	private function split_padding($val) {
		$parts = array_map('trim', explode(',', (string) $val));
		if (count($parts) === 1 && $parts[0] === '') {
			$parts = array('0px', '0px', '0px', '0px');
		}
		while (count($parts) < 4) {
			$parts[] = isset($parts[0]) ? $parts[0] : '0px';
		}
		return array_slice($parts, 0, 4);
	}

	private function padding_to_px($part) {
		return (int) round((float) preg_replace('/[^0-9.\-]/', '', (string) $part));
	}

	/**
	 * Use the real row col_gap setting and stop faking gutters with CSS that
	 * only shows on the frontend.
	 */
	private function normalize_column_gaps($nodes) {
		if (!is_array($nodes)) {
			return $nodes;
		}
		foreach ($nodes as &$node) {
			if (!is_array($node) || empty($node['tag'])) {
				continue;
			}
			$tag = $this->normalize_tag($node['tag']);
			if (!isset($node['attrs']) || !is_array($node['attrs'])) {
				$node['attrs'] = array();
			}

			if ($tag === 'pl_row' || $tag === 'pl_inner_row') {
				if (!isset($node['attrs']['col_gap']) || $node['attrs']['col_gap'] === '' || (string) $node['attrs']['col_gap'] === '0') {
					$node['attrs']['col_gap'] = '20';
				}
				if ($tag === 'pl_inner_row' && isset($node['attrs']['stretch']) && $node['attrs']['stretch'] === 'full') {
					$node['attrs']['stretch'] = 'auto';
				}
				$this->strip_fake_gap_css($node);
				if ($tag === 'pl_row') {
					$node['attrs'] = $this->pin_col_gap_horizontal($node['attrs']);
				}
			}

			if ($tag === 'pl_col' || $tag === 'pl_inner_col') {
				$this->move_column_card_style_to_widget($node);
				$this->strip_transparent_gap_border($node);
				$this->strip_fake_gap_css($node);
			}

			$this->strip_fake_gap_css($node);

			if (isset($node['content']) && is_array($node['content'])) {
				$node['content'] = $this->normalize_column_gaps($node['content']);
			}
		}
		unset($node);
		return $nodes;
	}

	private function strip_fake_gap_css(&$node) {
		if (empty($node['attrs']['ele_css']) || !is_string($node['attrs']['ele_css'])) {
			return;
		}
		$css = $node['attrs']['ele_css'];
		$css = preg_replace('/(?:column-gap|row-gap|grid-gap|grid-column-gap|grid-row-gap|(?<![a-z-])gap)\s*:\s*[^;}{]+;?/i', '', $css);
		$css = preg_replace('/display\s*:\s*grid\s*;?/i', '', $css);
		$css = preg_replace('/grid-template-columns\s*:\s*[^;}{]+;?/i', '', $css);
		$css = preg_replace('/\{\{element\}\}\{background-clip:padding-box;[^}]*\}/i', '', $css);
		$css = preg_replace('/[^{}]+\{[;\s]*\}/', '', $css);
		$css = preg_replace('/;\s*;+/', ';', $css);
		$css = trim($css, " \t\n\r\0\x0B;");
		if ($css === '' || strpos($css, ':') === false) {
			unset($node['attrs']['ele_css']);
		} else {
			$node['attrs']['ele_css'] = $css;
		}
	}

	private function is_transparent_gap_border($attrs) {
		if (!is_array($attrs) || empty($attrs['border_color'])) {
			return false;
		}
		$c = strtolower(preg_replace('/\s+/', '', (string) $attrs['border_color']));
		return ($c === 'transparent' || $c === 'rgba(0,0,0,0)' || $c === 'rgba(0,0,0,0.0)' || $c === '#00000000' || $c === '#0000');
	}

	private function strip_transparent_gap_border(&$node) {
		if (empty($node['attrs']) || !is_array($node['attrs'])) {
			return;
		}
		if (!$this->is_transparent_gap_border($node['attrs'])) {
			return;
		}
		unset($node['attrs']['border_type'], $node['attrs']['border_width'], $node['attrs']['border_color']);
	}

	/**
	 * Card chrome on a column is flush in the editor (width lives on the wrap)
	 * and only looks gapped on the frontend when a transparent border hack is
	 * used. Move that chrome onto the single card widget so row col_gap is the
	 * visible gutter in both views.
	 */
	private function move_column_card_style_to_widget(&$col) {
		$children = $this->col_children($col);
		$card_idx = -1;
		$cards    = 0;
		foreach ($children as $i => $child) {
			if ($this->is_card_widget($child)) {
				$cards++;
				$card_idx = $i;
			}
		}
		if ($cards !== 1 || $card_idx < 0 || empty($col['attrs'])) {
			return;
		}

		$style_keys = array(
			'ele_bg_type', 'ele_bg_color', 'ele_padding', 'ele_shadow',
			'border_radius',
		);
		$has_style = false;
		foreach ($style_keys as $k) {
			if (!empty($col['attrs'][$k])) {
				$has_style = true;
				break;
			}
		}
		if (!$this->is_transparent_gap_border($col['attrs']) && !$has_style) {
			return;
		}

		$card = $children[$card_idx];
		if (!isset($card['attrs']) || !is_array($card['attrs'])) {
			$card['attrs'] = array();
		}

		foreach ($style_keys as $k) {
			if (empty($col['attrs'][$k])) {
				continue;
			}
			if (empty($card['attrs'][$k])) {
				$card['attrs'][$k] = $col['attrs'][$k];
			}
			unset($col['attrs'][$k]);
		}

		if (!$this->is_transparent_gap_border($col['attrs'])) {
			foreach (array('border_type', 'border_width', 'border_color') as $k) {
				if (empty($col['attrs'][$k])) {
					continue;
				}
				if (empty($card['attrs'][$k])) {
					$card['attrs'][$k] = $col['attrs'][$k];
				}
				unset($col['attrs'][$k]);
			}
		} else {
			unset($col['attrs']['border_type'], $col['attrs']['border_width'], $col['attrs']['border_color']);
		}

		$children[$card_idx] = $card;
		$col['content']      = $children;
	}

	/**
	 * Fill missing modern look-and-feel using real Pagelayer attributes.
	 */
	private function apply_modern_defaults($tag, $attrs, $node) {
		if (!is_array($attrs)) {
			$attrs = array();
		}
		$tag = str_replace(array('pl_inner_row', 'pl_inner_col'), array('pl_row', 'pl_col'), $this->normalize_tag($tag));

		if ($tag === 'pl_row') {
			return $attrs;
		}

		$cards = $this->card_widget_tags();
		if (isset($cards[$tag])) {
			if (empty($attrs['ele_padding'])) {
				$attrs['ele_padding'] = '32px,28px,32px,28px';
			}
			if ($tag === 'pl_iconbox' || $tag === 'pl_service') {
				$attrs = $this->style_icon_box($tag, $attrs);
				if (empty($attrs['service_icon_color'])) {
					$attrs['service_icon_color'] = '$primary';
				}
				if (empty($attrs['service_heading_color'])) {
					$attrs['service_heading_color'] = '$primary';
				}
			}
			if ($tag === 'pl_image_slider') {
				$attrs = $this->style_image_slider($attrs);
			}
			if ($tag === 'pl_testimonial') {
				if (empty($attrs['alignment'])) {
					$attrs['alignment'] = 'center';
				}
				if (empty($attrs['image_position'])) {
					$attrs['image_position'] = 'top-position';
				}
				if (empty($attrs['cite_color'])) {
					$attrs['cite_color'] = '$primary';
				}
				if (empty($attrs['img_shape'])) {
					$attrs['img_shape'] = 'circle';
				}
			}
			if ($tag === 'pl_counter') {
				if (empty($attrs['counter_align'])) {
					$attrs['counter_align'] = 'center';
				}
				if (empty($attrs['counter_number_color'])) {
					$attrs['counter_number_color'] = '$primary';
				}
				$attrs = $this->style_counter($attrs, false);
			}
		}

		if ($tag === 'pl_heading') {
			if (empty($attrs['color'])) {
				$attrs['color'] = '$primary';
			}
			$content = (isset($node['content']) && is_string($node['content'])) ? $node['content'] : '';
			$family  = $this->global_font_family('primary');
			if (empty($attrs['heading_typo']) && empty($attrs['font_size'])) {
				if (stripos($content, '<h1') !== false) {
					$attrs['heading_typo'] = $family . ',48,,800,,,,1.15,,,';
				} elseif (stripos($content, '<h2') !== false) {
					$attrs['heading_typo'] = $family . ',36,,700,,,,1.2,,,';
				} elseif (stripos($content, '<h3') !== false) {
					$attrs['heading_typo'] = $family . ',22,,700,,,,1.3,,,';
				} else {
					$attrs['heading_typo'] = $family . ',18,,600,,,,1.4,,,';
				}
			} elseif (!empty($attrs['heading_typo'])) {
				$attrs['heading_typo'] = $this->typo_with_family($attrs['heading_typo'], $family);
			}
		}

		if ($tag === 'pl_btn') {
			if (empty($attrs['type'])) {
				$attrs['type'] = 'pagelayer-btn-custom';
			}
			if (empty($attrs['size'])) {
				$attrs['size'] = 'pagelayer-btn-large';
			}
			if (empty($attrs['btn_bg_color'])) {
				$attrs['btn_bg_color'] = '$primary';
			}
			if (empty($attrs['btn_color'])) {
				$attrs['btn_color'] = '#ffffff';
			}
			if (!isset($attrs['font_weight']) || $attrs['font_weight'] === '') {
				$attrs['font_weight'] = '600';
			}
			if (empty($attrs['btn_border_radius'])) {
				if (!isset($attrs['btn_border_type']) || $attrs['btn_border_type'] === '') {
					$attrs['btn_border_type'] = 'solid';
				}
				if (empty($attrs['btn_border_width'])) {
					$attrs['btn_border_width'] = '0px,0px,0px,0px';
				}
				$attrs['btn_border_radius'] = '8px,8px,8px,8px';
			}
		}

		if ($tag === 'pl_text') {
			if (empty($attrs['font_family'])) {
				$attrs['font_family'] = $this->global_font_family('text');
			}
			if (empty($attrs['font_size'])) {
				$attrs['font_size'] = '16';
			}
			if (empty($attrs['line_height'])) {
				$attrs['line_height'] = '1.7';
			}
		}

		if ($tag === 'pl_image') {
			if (empty($attrs['align'])) {
				$attrs['align'] = 'center';
			}
			if (empty($attrs['border_radius'])) {
				$attrs['border_radius'] = '16px,16px,16px,16px';
			}
		}

		return $attrs;
	}

	public function default_widget_set() {
		$catalog = $this->get_widget_catalog();
		$prefer  = array(
			'pl_row', 'pl_col', 'pl_heading', 'pl_text', 'pl_btn', 'pl_image',
			'pl_iconbox', 'pl_service', 'pl_icon', 'pl_list', 'pl_list_item',
			'pl_counter', 'pl_testimonial', 'pl_stars', 'pl_divider', 'pl_space',
			'pl_accordion', 'pl_accordion_item', 'pl_tabs', 'pl_tab', 'pl_quote',
			'pl_progress', 'pl_phone', 'pl_email', 'pl_address', 'pl_social_grp',
			'pl_social', 'pl_video', 'pl_google_maps', 'pl_badge', 'pl_alert',
			'pl_image_slider', 'pl_grid_gallery',
		);
		$out = array();
		foreach ($prefer as $tag) {
			if (isset($catalog[$tag])) {
				$out[] = $tag;
			}
		}

		$out = apply_filters('pagelayer_ai_default_widgets', $out, $catalog);
		if (!is_array($out)) {
			return array();
		}

		$clean = array();
		foreach ($out as $tag) {
			$tag = $this->normalize_tag((string) $tag);
			if (isset($catalog[$tag]) && !in_array($tag, $clean, true)) {
				$clean[] = $tag;
			}
		}
		return $clean;
	}
}
