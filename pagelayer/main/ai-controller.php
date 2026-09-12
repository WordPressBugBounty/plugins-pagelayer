<?php

//////////////////////////////////////////////////////////////
//===========================================================
// ai-controller.php
//===========================================================
// PAGELAYER
// AI Layout Engine & Generator Controller
//===========================================================
//////////////////////////////////////////////////////////////

if (!defined('ABSPATH')) exit;

if (!class_exists('Pagelayer_AI_Layout_Engine')) {
	include_once(dirname(__FILE__) . '/ai-layout-engine.php');
}

class Pagelayer_AI_Controller {

	private static $instance = null;
	const REST_NAMESPACE = 'pagelayer/v1';
	const META_KEY = 'pagelayer_ai_keys';

	public static function get_instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	public function __construct() {
		add_action('rest_api_init', array($this, 'register_rest_routes'));
		add_filter('theme_page_templates', array($this, 'register_canvas_page_template'));
		add_filter('template_include', array($this, 'include_canvas_page_template'), 1100);
	}

	/**
	 * Blank full-width template so AI landing pages render edge-to-edge.
	 */
	public function register_canvas_page_template($templates) {
		if (!is_array($templates)) {
			$templates = array();
		}
		$templates['pagelayer-canvas'] = __('Pagelayer Canvas', 'pagelayer');
		return $templates;
	}

	public function include_canvas_page_template($template) {
		if (!is_singular()) {
			return $template;
		}
		$post_id = get_queried_object_id();
		if (!$post_id) {
			return $template;
		}
		$slug = get_page_template_slug($post_id);
		if ($slug !== 'pagelayer-canvas') {
			return $template;
		}
		$file = (defined('PAGELAYER_DIR') ? PAGELAYER_DIR : dirname(__FILE__) . '/..') . '/templates/pagelayer-canvas.php';
		return file_exists($file) ? $file : $template;
	}

	public function assign_canvas_template($post_id) {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || get_post_type($post_id) !== 'page') {
			return false;
		}
		update_post_meta($post_id, '_wp_page_template', 'pagelayer-canvas');
		return true;
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		// POST /pagelayer/v1/ai/settings
		register_rest_route(self::REST_NAMESPACE, '/ai/settings', array(
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'rest_save_settings'),
				'permission_callback' => array($this, 'check_permissions'),
			),
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'rest_get_settings'),
				'permission_callback' => array($this, 'check_permissions'),
			),
		));

		// POST /pagelayer/v1/ai/generate
		register_rest_route(self::REST_NAMESPACE, '/ai/generate', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array($this, 'rest_generate'),
			'permission_callback' => array($this, 'check_permissions'),
		));
	}

	/**
	 * Verify user permissions
	 */
	public function check_permissions() {
		return current_user_can('edit_posts');
	}

	/**
	 * Available models and providers definition
	 */
	public function get_available_models() {
		return array(
			'gemini' => array(
				'name' => 'Google Gemini',
				'default_model' => 'gemini-3.7-flash',
				'placeholder'   => 'e.g. gemini-3.7-flash, gemini-1.5-flash, gemini-1.5-pro',
				'docs_url'      => 'https://aistudio.google.com/app/apikey',
			),
			'deepseek' => array(
				'name'          => 'DeepSeek (Official API)',
				'default_model' => 'deepseek-chat',
				'placeholder'   => 'e.g. deepseek-chat, deepseek-reasoner',
				'docs_url'      => 'https://platform.deepseek.com/api_keys',
			),
			'openrouter' => array(
				'name'          => 'OpenRouter (All Open Models)',
				'default_model' => 'deepseek/deepseek-chat',
				'placeholder'   => 'e.g. deepseek/deepseek-chat, google/gemini-3.7-flash-001, meta-llama/llama-3.3-70b-instruct',
				'docs_url'      => 'https://openrouter.ai/keys',
			),
			'openai' => array(
				'name'          => 'OpenAI',
				'default_model' => 'gpt-4o-mini',
				'placeholder'   => 'e.g. gpt-4o-mini, gpt-4o, o3-mini',
				'docs_url'      => 'https://platform.openai.com/api-keys',
			),
			'anthropic' => array(
				'name'          => 'Anthropic Claude',
				'default_model' => 'claude-3-7-sonnet-20250219',
				'placeholder'   => 'e.g. claude-3-7-sonnet-20250219, claude-3-5-sonnet-20241022, claude-3-5-haiku-20241022',
				'docs_url'      => 'https://console.anthropic.com/settings/keys',
			),
			'custom' => array(
				'name'          => 'Custom / Local (OpenAI-Compatible)',
				'default_model' => 'llama3.3',
				'placeholder'   => 'e.g. llama3.3, qwen2.5-coder, deepseek-r1, mistral',
				'docs_url'      => '',
			),
		);
	}

	/**
	 * Encrypt sensitive string
	 */
	private function encrypt($plain_text) {
		if (empty($plain_text)) return '';
		$key = hash('sha256', wp_salt('auth'), true);
		$iv = openssl_random_pseudo_bytes(16);
		$cipher = openssl_encrypt($plain_text, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
		if ($cipher === false) return base64_encode($plain_text);
		return base64_encode($iv . $cipher);
	}

	/**
	 * Decrypt sensitive string
	 */
	private function decrypt($encrypted_text) {
		if (empty($encrypted_text)) return '';
		$data = base64_decode($encrypted_text);
		if ($data === false || strlen($data) < 17) {
			return $data !== false ? $data : '';
		}
		$key = hash('sha256', wp_salt('auth'), true);
		$iv = substr($data, 0, 16);
		$cipher = substr($data, 16);
		$plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
		if ($plain === false) {
			return $data;
		}
		return $plain;
	}
	
	/**
	 * Mask an API key for safe client display
	 */
	private function mask_key($key) {
		if (empty($key)) return '';
		$len = strlen($key);
		if ($len <= 8) return '••••••••';
		return substr($key, 0, 4) . '••••••••' . substr($key, -4);
	}

	/**
	 * Get saved API keys and settings for current user
	 */
	public function get_user_ai_settings($user_id = 0) {
		if (!$user_id) {
			$user_id = get_current_user_id();
		}
		$data = get_user_meta($user_id, self::META_KEY, true);
		if (!is_array($data)) {
			$data = array();
		}

		$deepseek_key     = !empty($data['deepseek_key']) ? $this->decrypt($data['deepseek_key']) : '';
		$deepseek_model   = !empty($data['deepseek_model']) ? sanitize_text_field($data['deepseek_model']) : (!empty($data['default_model']) && (!empty($data['default_provider']) && $data['default_provider'] === 'deepseek') ? sanitize_text_field($data['default_model']) : 'deepseek-chat');
		$gemini_key       = !empty($data['gemini_key']) ? $this->decrypt($data['gemini_key']) : '';
		$gemini_model     = !empty($data['gemini_model']) ? sanitize_text_field($data['gemini_model']) : (!empty($data['default_model']) && (!empty($data['default_provider']) && $data['default_provider'] === 'gemini') ? sanitize_text_field($data['default_model']) : 'gemini-3.7-flash');
		if ($gemini_model === 'gemini-2.5-flash' || $gemini_model === 'gemini-2.5-pro' || strpos($gemini_model, 'gemini-2.5') !== false) {
			$gemini_model = 'gemini-3.7-flash';
		}
		$openrouter_key   = !empty($data['openrouter_key']) ? $this->decrypt($data['openrouter_key']) : '';
		$openrouter_model = !empty($data['openrouter_model']) ? sanitize_text_field($data['openrouter_model']) : (!empty($data['default_model']) && (!empty($data['default_provider']) && $data['default_provider'] === 'openrouter') ? sanitize_text_field($data['default_model']) : 'deepseek/deepseek-chat');
		$openai_key       = !empty($data['openai_key']) ? $this->decrypt($data['openai_key']) : '';
		$openai_model     = !empty($data['openai_model']) ? sanitize_text_field($data['openai_model']) : (!empty($data['default_model']) && (!empty($data['default_provider']) && $data['default_provider'] === 'openai') ? sanitize_text_field($data['default_model']) : 'gpt-4o-mini');
		$anthropic_key    = !empty($data['anthropic_key']) ? $this->decrypt($data['anthropic_key']) : '';
		$anthropic_model  = !empty($data['anthropic_model']) ? sanitize_text_field($data['anthropic_model']) : (!empty($data['default_model']) && (!empty($data['default_provider']) && $data['default_provider'] === 'anthropic') ? sanitize_text_field($data['default_model']) : 'claude-3-7-sonnet-20250219');
		$custom_key       = !empty($data['custom_key']) ? $this->decrypt($data['custom_key']) : '';
		$custom_endpoint  = !empty($data['custom_endpoint']) ? sanitize_text_field($data['custom_endpoint']) : '';
		$custom_model     = !empty($data['custom_model']) ? sanitize_text_field($data['custom_model']) : '';

		$resolved = $this->resolve_default_provider($data, array(
			'deepseek_key'      => $deepseek_key,
			'deepseek_model'    => $deepseek_model,
			'gemini_key'        => $gemini_key,
			'gemini_model'      => $gemini_model,
			'openrouter_key'    => $openrouter_key,
			'openrouter_model'  => $openrouter_model,
			'openai_key'        => $openai_key,
			'openai_model'      => $openai_model,
			'anthropic_key'     => $anthropic_key,
			'anthropic_model'   => $anthropic_model,
			'custom_key'        => $custom_key,
			'custom_endpoint'   => $custom_endpoint,
			'custom_model'      => $custom_model,
		));

		return array(
			'default_provider'  => $resolved['provider'],
			'default_model'     => $resolved['model'],
			'deepseek_key'      => $deepseek_key,
			'deepseek_model'    => $deepseek_model,
			'gemini_key'        => $gemini_key,
			'gemini_model'      => $gemini_model,
			'openrouter_key'    => $openrouter_key,
			'openrouter_model'  => $openrouter_model,
			'openai_key'        => $openai_key,
			'openai_model'      => $openai_model,
			'anthropic_key'     => $anthropic_key,
			'anthropic_model'   => $anthropic_model,
			'custom_key'        => $custom_key,
			'custom_endpoint'   => $custom_endpoint,
			'custom_model'      => $custom_model,
		);
	}

	/**
	 * Prefer a provider the user actually connected. Custom/OpenCode is
	 * connected when an endpoint is set (API key is optional for local servers).
	 * Never keep Gemini as default when it has no key.
	 */
	private function resolve_default_provider($data, $keys) {
		$saved_provider = !empty($data['default_provider']) ? $data['default_provider'] : '';
		$saved_model    = !empty($data['default_model']) ? $data['default_model'] : '';

		$connected = array();
		if (!empty($keys['custom_endpoint'])) {
			$connected['custom'] = !empty($keys['custom_model']) ? $keys['custom_model'] : 'llama3.3';
		}
		if (!empty($keys['deepseek_key'])) {
			$connected['deepseek'] = !empty($keys['deepseek_model']) ? $keys['deepseek_model'] : 'deepseek-chat';
		}
		if (!empty($keys['openrouter_key'])) {
			$connected['openrouter'] = !empty($keys['openrouter_model']) ? $keys['openrouter_model'] : 'deepseek/deepseek-chat';
		}
		if (!empty($keys['openai_key'])) {
			$connected['openai'] = !empty($keys['openai_model']) ? $keys['openai_model'] : 'gpt-4o-mini';
		}
		if (!empty($keys['anthropic_key'])) {
			$connected['anthropic'] = !empty($keys['anthropic_model']) ? $keys['anthropic_model'] : 'claude-3-7-sonnet-20250219';
		}
		if (!empty($keys['gemini_key'])) {
			$connected['gemini'] = !empty($keys['gemini_model']) ? $keys['gemini_model'] : 'gemini-3.7-flash';
		}

		if ($saved_provider !== '' && isset($connected[$saved_provider])) {
			$model = $saved_model;
			if ($model === '') {
				$model = $connected[$saved_provider];
			}
			return array('provider' => $saved_provider, 'model' => $model);
		}

		foreach ($connected as $provider => $model) {
			return array('provider' => $provider, 'model' => $model);
		}

		return array('provider' => 'gemini', 'model' => !empty($keys['gemini_model']) ? $keys['gemini_model'] : 'gemini-3.7-flash');
	}

	/**
	 * REST: GET /pagelayer/v1/ai/settings
	 */
	public function rest_get_settings($request) {
		$settings = $this->get_user_ai_settings();
		
		return rest_ensure_response(array(
			'success'            => true,
			'default_provider'   => $settings['default_provider'],
			'default_model'      => $settings['default_model'],
			'has_deepseek_key'   => !empty($settings['deepseek_key']),
			'has_gemini_key'     => !empty($settings['gemini_key']),
			'has_openrouter_key' => !empty($settings['openrouter_key']),
			'has_openai_key'     => !empty($settings['openai_key']),
			'has_anthropic_key'  => !empty($settings['anthropic_key']),
			'has_custom_key'     => !empty($settings['custom_key']),
			'deepseek_model'     => $settings['deepseek_model'],
			'gemini_model'       => $settings['gemini_model'],
			'openrouter_model'   => $settings['openrouter_model'],
			'openai_model'       => $settings['openai_model'],
			'anthropic_model'    => $settings['anthropic_model'],
			'custom_endpoint'    => $settings['custom_endpoint'],
			'custom_model'       => $settings['custom_model'],
			'deepseek_masked'    => $this->mask_key($settings['deepseek_key']),
			'gemini_masked'      => $this->mask_key($settings['gemini_key']),
			'openrouter_masked'  => $this->mask_key($settings['openrouter_key']),
			'openai_masked'      => $this->mask_key($settings['openai_key']),
			'anthropic_masked'   => $this->mask_key($settings['anthropic_key']),
			'custom_masked'      => $this->mask_key($settings['custom_key']),
			'available_models'   => $this->get_available_models(),
		));
	}

	/**
	 * REST: POST /pagelayer/v1/ai/settings
	 */
	public function rest_save_settings($request) {
		$user_id = get_current_user_id();
		$params  = $request->get_json_params();
		if (empty($params)) {
			$params = $request->get_params();
		}

		$existing = get_user_meta($user_id, self::META_KEY, true);
		if (!is_array($existing)) {
			$existing = array();
		}

		// Handle DeepSeek key & model
		if (isset($params['deepseek_key'])) {
			$raw_key = trim($params['deepseek_key']);
			if ($raw_key === '') {
				unset($existing['deepseek_key']);
			} elseif (strpos($raw_key, '••••') === false) {
				$existing['deepseek_key'] = $this->encrypt(sanitize_text_field($raw_key));
			}
		}
		if (isset($params['deepseek_model'])) {
			$existing['deepseek_model'] = sanitize_text_field(trim($params['deepseek_model']));
		}

		// Handle Gemini key & model
		if (isset($params['gemini_key'])) {
			$raw_key = trim($params['gemini_key']);
			if ($raw_key === '') {
				unset($existing['gemini_key']);
			} elseif (strpos($raw_key, '••••') === false) {
				$existing['gemini_key'] = $this->encrypt(sanitize_text_field($raw_key));
			}
		}
		if (isset($params['gemini_model'])) {
			$existing['gemini_model'] = sanitize_text_field(trim($params['gemini_model']));
		}

		// Handle OpenRouter key & model
		if (isset($params['openrouter_key'])) {
			$raw_key = trim($params['openrouter_key']);
			if ($raw_key === '') {
				unset($existing['openrouter_key']);
			} elseif (strpos($raw_key, '••••') === false) {
				$existing['openrouter_key'] = $this->encrypt(sanitize_text_field($raw_key));
			}
		}
		if (isset($params['openrouter_model'])) {
			$existing['openrouter_model'] = sanitize_text_field(trim($params['openrouter_model']));
		}

		// Handle OpenAI key & model
		if (isset($params['openai_key'])) {
			$raw_key = trim($params['openai_key']);
			if ($raw_key === '') {
				unset($existing['openai_key']);
			} elseif (strpos($raw_key, '••••') === false) {
				$existing['openai_key'] = $this->encrypt(sanitize_text_field($raw_key));
			}
		}
		if (isset($params['openai_model'])) {
			$existing['openai_model'] = sanitize_text_field(trim($params['openai_model']));
		}

		// Handle Anthropic key & model
		if (isset($params['anthropic_key'])) {
			$raw_key = trim($params['anthropic_key']);
			if ($raw_key === '') {
				unset($existing['anthropic_key']);
			} elseif (strpos($raw_key, '••••') === false) {
				$existing['anthropic_key'] = $this->encrypt(sanitize_text_field($raw_key));
			}
		}
		if (isset($params['anthropic_model'])) {
			$existing['anthropic_model'] = sanitize_text_field(trim($params['anthropic_model']));
		}

		// Handle Custom endpoint, key, and model
		if (isset($params['custom_endpoint'])) {
			$existing['custom_endpoint'] = sanitize_text_field(trim($params['custom_endpoint']));
		}
		if (isset($params['custom_model'])) {
			$existing['custom_model'] = sanitize_text_field(trim($params['custom_model']));
		}
		if (isset($params['custom_key'])) {
			$raw_key = trim($params['custom_key']);
			if ($raw_key === '') {
				unset($existing['custom_key']);
			} elseif (strpos($raw_key, '••••') === false) {
				$existing['custom_key'] = $this->encrypt(sanitize_text_field($raw_key));
			}
		}

		// Default provider and model
		if (!empty($params['default_provider'])) {
			$existing['default_provider'] = sanitize_text_field($params['default_provider']);
		}
		if (!empty($params['default_model'])) {
			$existing['default_model'] = sanitize_text_field($params['default_model']);
		}

		// Saving a custom/OpenCode endpoint with no Gemini key should not leave
		// Gemini as the default just because the picker still had it selected.
		$has_custom = !empty($existing['custom_endpoint']);
		$has_gemini = !empty($existing['gemini_key']);
		if ($has_custom && !$has_gemini && (empty($existing['default_provider']) || $existing['default_provider'] === 'gemini')) {
			$existing['default_provider'] = 'custom';
			$existing['default_model']    = !empty($existing['custom_model']) ? $existing['custom_model'] : 'custom-model';
		}

		update_user_meta($user_id, self::META_KEY, $existing);

		$settings = $this->get_user_ai_settings($user_id);

		return rest_ensure_response(array(
			'success'            => true,
			'message'            => __('AI settings saved successfully.', 'pagelayer'),
			'default_provider'   => $settings['default_provider'],
			'default_model'      => $settings['default_model'],
			'has_deepseek_key'   => !empty($settings['deepseek_key']),
			'has_gemini_key'     => !empty($settings['gemini_key']),
			'has_openrouter_key' => !empty($settings['openrouter_key']),
			'has_openai_key'     => !empty($settings['openai_key']),
			'has_anthropic_key'  => !empty($settings['anthropic_key']),
			'has_custom_key'     => !empty($settings['custom_key']),
			'deepseek_model'     => $settings['deepseek_model'],
			'gemini_model'       => $settings['gemini_model'],
			'openrouter_model'   => $settings['openrouter_model'],
			'openai_model'       => $settings['openai_model'],
			'anthropic_model'    => $settings['anthropic_model'],
			'custom_endpoint'    => $settings['custom_endpoint'],
			'custom_model'       => $settings['custom_model'],
			'deepseek_masked'    => $this->mask_key($settings['deepseek_key']),
			'gemini_masked'      => $this->mask_key($settings['gemini_key']),
			'openrouter_masked'  => $this->mask_key($settings['openrouter_key']),
			'openai_masked'      => $this->mask_key($settings['openai_key']),
			'anthropic_masked'   => $this->mask_key($settings['anthropic_key']),
			'custom_masked'      => $this->mask_key($settings['custom_key']),
		));
	} 
	 
	/**
	 * Build system instructions from the live widget registry.
	 */
	public function get_system_prompt($widget_tags = array()) {
		$engine = Pagelayer_AI_Layout_Engine::instance();
		return $engine->get_build_prompt($widget_tags);
	}

	public function rest_generate($request) {
		$params   = $request->get_json_params();
		if (empty($params)) {
			$params = $request->get_params();
		}

		$prompt = !empty($params['prompt']) ? trim(sanitize_textarea_field($params['prompt'])) : '';
		if (empty($prompt)) {
			return new WP_Error('missing_prompt', __('Please provide a prompt describing the layout you want to build.', 'pagelayer'), array('status' => 400));
		}

		$user_id  = get_current_user_id();
		$settings = $this->get_user_ai_settings($user_id);

		$provider = !empty($params['provider']) ? sanitize_text_field($params['provider']) : $settings['default_provider'];
		$model    = !empty($params['model']) ? sanitize_text_field($params['model']) : $settings['default_model'];

		// Don't try Gemini when the user never connected it.
		if ($provider === 'gemini' && empty($settings['gemini_key'])) {
			$provider = $settings['default_provider'];
			$model    = $settings['default_model'];
		}
		$context  = !empty($params['context']) ? (is_array($params['context']) ? $params['context'] : array('page_context' => sanitize_text_field($params['context']))) : array();
		$post_id  = !empty($params['post_id']) ? intval($params['post_id']) : (!empty($_REQUEST['postID']) ? intval($_REQUEST['postID']) : 0);

		$api_key = '';
		if (!empty($params['api_key'])) {
			$api_key = trim(sanitize_text_field($params['api_key']));
		} else {
			if ($provider === 'deepseek') {
				$api_key = $settings['deepseek_key'];
			} elseif ($provider === 'gemini') {
				$api_key = $settings['gemini_key'];
			} elseif ($provider === 'openrouter') {
				$api_key = $settings['openrouter_key'];
			} elseif ($provider === 'openai') {
				$api_key = $settings['openai_key'];
			} elseif ($provider === 'anthropic') {
				$api_key = $settings['anthropic_key'];
			} elseif ($provider === 'custom') {
				$api_key = $settings['custom_key'];
			}
		}

		$engine = Pagelayer_AI_Layout_Engine::instance();

		// 1. Direct Global Color update requests ("change global color to red", "set this global color #3b82f6")
		if ($engine->is_global_color_request($prompt)) {
			$res = $engine->handle_global_color_instruction($prompt);
			return rest_ensure_response($res);
		}

		// 2. Direct Background Color / Dark mode requests ("make all background dark", "change background color to ...", "set this color")
		if ($engine->is_background_color_request($prompt)) {
			$res = $engine->handle_background_color_instruction($prompt);
			return rest_ensure_response($res);
		}

		if (empty($api_key) && $provider !== 'custom') {
			return new WP_Error(
				'missing_api_key',
				sprintf(__('No API key found for %s. Please enter your API key in the AI Settings.', 'pagelayer'), strtoupper($provider)),
				array('status' => 400, 'provider' => $provider)
			);
		}

		$globals_mode         = !empty($params['globals_mode']) ? sanitize_text_field($params['globals_mode']) : (!empty($params['use_existing_globals']) ? 'existing' : 'new');
		$use_existing_globals = ($globals_mode === 'existing');
		$custom_styling       = ($globals_mode === 'custom') || !empty($params['custom_styling']);

		$engine = Pagelayer_AI_Layout_Engine::instance();
		$call   = array(
			'provider' => $provider,
			'api_key'  => $api_key,
			'model'    => $model,
			'settings' => $settings,
			'params'   => $params,
		);

		$plan = array(
			'widgets'              => $engine->widgets_for_request($prompt),
			'sections'             => array(),
			'palette'              => array(),
			'fonts'                => array(),
			'summary'              => '',
			'mood'                 => '',
			'layout'               => array(),
			'use_existing_globals' => $use_existing_globals,
			'custom_styling'       => $custom_styling,
		);

		if ($use_existing_globals) {
			$existing_tokens = $engine->get_existing_tokens();
			$plan['palette'] = $existing_tokens['palette'];
			$plan['fonts']   = $existing_tokens['fonts'];
			$context['use_existing_globals'] = true;
			$context['existing_palette']     = $existing_tokens['palette'];
			$context['existing_fonts']       = $existing_tokens['fonts'];
		} elseif ($custom_styling) {
			$context['custom_styling'] = true;
		}

		$kind = $engine->request_kind($prompt);

		$requested_sections = $engine->is_scoped_kind($kind) ? array() : $engine->extract_requested_sections($prompt);
		if (!empty($requested_sections)) {
			$context['requested_sections'] = $requested_sections;
			$plan['requested_sections']    = $requested_sections;
		}

		$full_page = ($kind === 'home' || (!$engine->is_scoped_kind($kind) && count($requested_sections) >= 2));

		// Page-level requests plan identity first so the builder designs a theme.
		if ($full_page || $engine->should_plan($kind)) {
			$plan_raw = $this->call_provider(
				$call,
				$engine->get_plan_prompt(),
				$engine->get_plan_user_message($prompt, $context),
				array('temperature' => 0.7, 'max_tokens' => 2048, 'json' => true)
			);
			if (!is_wp_error($plan_raw)) {
				$parsed = $engine->parse_plan($plan_raw);
				if (!empty($parsed['widgets'])) {
					$merged = array_values(array_unique(array_merge($parsed['widgets'], $plan['widgets'])));
					$plan['widgets']  = $engine->expand_related_widgets($merged);
					$plan['sections'] = $parsed['sections'];
					if (!$use_existing_globals && !empty($parsed['palette'])) {
						$plan['palette']  = $parsed['palette'];
					}
					if (!$use_existing_globals && !empty($parsed['fonts'])) {
						$plan['fonts']    = $parsed['fonts'];
					}
					$plan['summary']  = $parsed['summary'];
					$plan['mood']     = !empty($parsed['mood']) ? $parsed['mood'] : '';
					$plan['layout']   = !empty($parsed['layout']) ? $parsed['layout'] : array();
				} else {
					if (!$use_existing_globals && !empty($parsed['palette'])) {
						$plan['palette'] = $parsed['palette'];
					}
					if (!$use_existing_globals && !empty($parsed['fonts'])) {
						$plan['fonts'] = $parsed['fonts'];
					}
					if (!empty($parsed['mood'])) {
						$plan['mood'] = $parsed['mood'];
					}
					if (!empty($parsed['sections'])) {
						$plan['sections'] = $parsed['sections'];
					}
					if (!empty($parsed['summary'])) {
						$plan['summary'] = $parsed['summary'];
					}
					if (!empty($parsed['layout'])) {
						$plan['layout'] = $parsed['layout'];
					}
				}
			}
		}

		$plan['widgets'] = $engine->expand_related_widgets($plan['widgets']);
		$kind_pack = $engine->widgets_for_kind($kind);
		if (!empty($kind_pack)) {
			$plan['widgets'] = $engine->expand_related_widgets($kind_pack);
			if (!empty($plan['layout']) && is_array($plan['layout'])) {
				$plan['layout'] = $this->filter_plan_layout_widgets($plan['layout'], $plan['widgets']);
				$plan['layout'] = $this->filter_plan_layout_for_kind($plan['layout'], $kind);
			}
		}

		if (!$use_existing_globals) {
			$engine->apply_ai_globals(
				!empty($plan['palette']) ? $plan['palette'] : array(),
				!empty($plan['fonts']) ? $plan['fonts'] : array(),
				true
			);
		}

		$system_prompt = $engine->get_build_prompt($plan['widgets'], array(
			'full_page'       => $full_page,
			'kind'            => $kind,
			'prompt'          => $prompt,
			'structure_brief' => $engine->structure_brief($kind, $prompt),
		));
		$user_message  = $engine->get_build_user_message($prompt, $context, $plan);

		$result = $this->call_provider(
			$call,
			$system_prompt,
			$user_message,
			array(
				'temperature' => $full_page ? 0.7 : ($engine->should_plan($kind) ? 0.55 : 0.45),
				'max_tokens'  => $full_page ? 16384 : 8192,
				'json'        => true,
			)
		);

		if (is_wp_error($result)) {
			return $result;
		}

		$shortcode = '';
		$layout_globals = $engine->extract_globals_from_json($result);
		if (!$use_existing_globals) {
			$engine->apply_ai_globals(
				!empty($layout_globals['palette']) ? $layout_globals['palette'] : (!empty($plan['palette']) ? $plan['palette'] : array()),
				!empty($layout_globals['fonts']) ? $layout_globals['fonts'] : (!empty($plan['fonts']) ? $plan['fonts'] : array()),
				true
			);
		}

		$nodes = $engine->parse_layout($result);

		// If user requested specific sections, verify that all sections were created
		if (!empty($requested_sections) && is_array($nodes)) {
			// If the last row is an incomplete stub (e.g. heading only for reviews, but no cards)
			if (count($nodes) > 1) {
				$last_row   = end($nodes);
				$last_stats = $engine->layout_stats(array($last_row));
				if ($last_stats['leaves'] <= 1) {
					$last_summary = $engine->get_row_summary_text($last_row);
					if (preg_match('/(reviews?|testimonials?|why\s*choose|hours?|location|features?|pricing|faq)/i', $last_summary['text'])) {
						array_pop($nodes);
					}
				}
			}

			$missing = $engine->find_missing_sections($nodes, $requested_sections);
			if (!empty($missing)) {
				$missing_list = array();
				foreach ($missing as $m_i => $m_sec) {
					$num = $m_i + 1;
					$desc = !empty($m_sec['desc']) ? ' — ' . $m_sec['desc'] : '';
					$missing_list[] = "{$num}. [pl_row] \"{$m_sec['name']}\"{$desc}";
				}

				$continuation_prompt = "The initial layout generated earlier sections successfully, but the following user-requested sections are still MISSING:\n\n"
					. implode("\n", $missing_list) . "\n\n"
					. "Generate ONLY the missing sections above as an array of top-level [pl_row] nodes in JSON: {\"nodes\":[...]}.\n"
					. "- Every missing section MUST be its own complete [pl_row] with rich, realistic dummy content and full styling.\n"
					. "- Match the same brand palette and typography.\n"
					. "- For Customer Reviews: use pl_testimonial or pl_review cards with names, quotes, and star ratings.\n"
					. "- For Location & Hours: include address, opening hours, and location/map CTA.\n"
					. "- For Final CTA: include energetic heading, description, and Order Now button.\n"
					. "Return JSON {\"nodes\":[...]} only. Do not repeat sections already created.";

				$add_result = $this->call_provider(
					$call,
					$system_prompt,
					$continuation_prompt,
					array(
						'temperature' => 0.5,
						'max_tokens'  => 8192,
						'json'        => true,
					)
				);

				if (!is_wp_error($add_result)) {
					$additional_nodes = $engine->parse_layout($add_result);
					if (!empty($additional_nodes)) {
						$nodes = array_merge((array) $nodes, (array) $additional_nodes);
					}
				}
			}
		}

		// If a page came back as a fragment or unstyled sketch, ask the model
		// once more — never inject a canned theme.
		if (($engine->should_plan($kind) || !empty($requested_sections)) && $engine->is_thin_layout($nodes, $kind, $requested_sections)) {
			$retry_msg  = $user_message . "\nYour previous JSON was too thin or unstyled for this request. ";
			if (!empty($requested_sections)) {
				$retry_msg .= "You MUST generate ALL " . count($requested_sections) . " requested sections. ";
			}
			$retry_msg .= "Design a complete, visually finished Pagelayer theme for this brand. ";
			$retry_msg .= "Invent the structure from the product. Set real widget settings from the schemas (colors, typography, backgrounds with ele_bg_type, padding, radius, shadow, icons, images). JSON only.";
			$retry = $this->call_provider(
				$call,
				$system_prompt,
				$retry_msg,
				array(
					'temperature' => 0.75,
					'max_tokens'  => $full_page ? 16384 : 8192,
					'json'        => true,
				)
			);
			if (!is_wp_error($retry)) {
				$retry_globals = $engine->extract_globals_from_json($retry);
				if (!$use_existing_globals) {
					$engine->apply_ai_globals(
						!empty($retry_globals['palette']) ? $retry_globals['palette'] : array(),
						!empty($retry_globals['fonts']) ? $retry_globals['fonts'] : array(),
						true
					);
					if (!empty($retry_globals['palette']) || !empty($retry_globals['fonts'])) {
						$layout_globals = $retry_globals;
					}
				}
				$retry_nodes = $engine->parse_layout($retry);
				if (!$engine->is_thin_layout($retry_nodes, $kind, $requested_sections)) {
					$nodes  = $retry_nodes;
					$result = $retry;
				} elseif (!empty($retry_nodes)) {
					$nodes = $retry_nodes;
				}
			}
		}

		if (!empty($nodes)) {
			$nodes     = $engine->finalize_nodes($nodes, $prompt);
			$shortcode = $engine->nodes_to_shortcode($nodes);
		}

		if (empty($shortcode)) {
			$shortcode = $this->sanitize_ai_shortcode($result);
		}

		$shortcode = $this->ensure_dark_band_contrast($shortcode);
		if (!in_array($kind, array('header', 'footer', '404'), true)) {
			$shortcode = $this->ensure_row_section_padding($shortcode);
		}
		$shortcode = $this->ensure_iconbox_center($shortcode);
		$shortcode = $this->ensure_slider_image_size($shortcode);
		$shortcode = $this->ensure_social_contrast($shortcode);
		$shortcode = $this->ensure_accordion_contrast($shortcode);
		$shortcode = $this->ensure_counter_start($shortcode);

		if (empty($shortcode)) {
			return new WP_Error('empty_generation', __('AI generated an empty response. Please try with a more detailed prompt.', 'pagelayer'), array('status' => 500));
		}

		$theme_template = null;
		if ($engine->is_theme_template_kind($kind) && !empty($nodes)) {
			$theme_template = $this->save_theme_template($kind, $prompt, $nodes, $post_id);
		}

		$editing_matching_template = false;
		if ($post_id && get_post_type($post_id) === 'pagelayer-template') {
			$current_type = (string) get_post_meta($post_id, 'pagelayer_template_type', true);
			$spec         = $engine->theme_template_spec($kind, $prompt);
			$editing_matching_template = ($spec && $current_type === $spec['type']);
		}

		// Header/footer are Theme Builder chrome — do not dump them as extra
		// sections onto a regular page. 404 and inner templates still preview
		// on the current canvas so the user can see the design.
		$insert_on_canvas = true;
		if (in_array($kind, array('header', 'footer'), true) && !$editing_matching_template) {
			$insert_on_canvas = false;
		}

		if ($post_id && $insert_on_canvas && ($full_page || $kind === 'home')) {
			$this->assign_canvas_template($post_id);
		}

		if ($post_id && $kind && $insert_on_canvas) {
			update_post_meta($post_id, 'pagelayer_ai_layout_kind', $kind);
		}
		if (is_array($theme_template) && !empty($theme_template['template_id']) && $kind) {
			update_post_meta((int) $theme_template['template_id'], 'pagelayer_ai_layout_kind', $kind);
		}

		$render_id     = ($theme_template && !empty($theme_template['template_id'])) ? (int) $theme_template['template_id'] : $post_id;
		$rendered_html = $this->render_shortcodes_for_canvas($shortcode, $render_id ? $render_id : $post_id);
		$tree          = $this->parse_shortcodes_to_tree($shortcode);
		$globals       = $engine->get_globals_snapshot();

		$message = __('Layout generated successfully.', 'pagelayer');
		if (is_array($theme_template) && !empty($theme_template['template_id'])) {
			$labels = array(
				'header' => __('Header theme template created.', 'pagelayer'),
				'footer' => __('Footer theme template created.', 'pagelayer'),
				'404'    => __('404 theme template created.', 'pagelayer'),
				'blog'   => __('Blog archive theme template created.', 'pagelayer'),
				'single' => __('Single post theme template created.', 'pagelayer'),
				'search' => __('Search results theme template created.', 'pagelayer'),
			);
			$message = isset($labels[$kind]) ? $labels[$kind] : $message;
		}

		return rest_ensure_response(array(
			'success'          => true,
			'shortcode'        => $shortcode,
			'html'             => $rendered_html,
			'tree'             => $tree,
			'provider'         => $provider,
			'model'            => $model,
			'widgets'          => $plan['widgets'],
			'plan'             => $plan,
			'globals'          => $globals,
			'globals_updated'  => ($globals_mode === 'new'),
			'globals_mode'     => $globals_mode,
			'page_template'    => 'pagelayer-canvas',
			'kind'             => $kind,
			'theme_template'   => $theme_template,
			'insert_on_canvas' => $insert_on_canvas,
			'message'          => $message,
		));
	}

	/**
	 * Persist a generated layout as a Pagelayer Theme Builder template.
	 *
	 * @return array|null
	 */
	private function save_theme_template($kind, $prompt, $nodes, $post_id = 0) {
		$engine = Pagelayer_AI_Layout_Engine::instance();
		$spec   = $engine->theme_template_spec($kind, $prompt);
		if (!$spec || empty($nodes) || !is_array($nodes)) {
			return null;
		}

		$existing_id = $this->find_theme_template_id($spec, $post_id);

		if (class_exists('Pagelayer_Abilities_Register')) {
			$input = array(
				'title'            => $spec['title'],
				'type'             => $spec['type'],
				'pagelayer_data'   => $nodes,
				'conditions'       => $spec['conditions'],
				'skip_validation'  => true,
			);
			if ($kind === 'header') {
				$input['single_page_site'] = $this->header_nodes_need_menu_opt_out($nodes);
			}

			if ($existing_id) {
				$input['template_id'] = $existing_id;
				$res = Pagelayer_Abilities_Register::execute_update_template($input);
			} else {
				$res = Pagelayer_Abilities_Register::execute_create_template($input);
			}

			if (!is_wp_error($res) && !empty($res['template_id'])) {
				$tid = (int) $res['template_id'];
				return array(
					'template_id' => $tid,
					'type'        => $spec['type'],
					'kind'        => $kind,
					'title'       => $spec['title'],
					'edit_url'    => $this->theme_template_edit_url($tid),
					'updated'     => (bool) $existing_id,
				);
			}
		}

		$tid = $this->save_theme_template_direct($spec, $nodes, $existing_id);
		if (!$tid) {
			return null;
		}

		return array(
			'template_id' => $tid,
			'type'        => $spec['type'],
			'kind'        => $kind,
			'title'       => $spec['title'],
			'edit_url'    => $this->theme_template_edit_url($tid),
			'updated'     => (bool) $existing_id,
		);
	}

	private function header_nodes_need_menu_opt_out($nodes) {
		$has_bound_menu = false;
		$walk = function ($list) use (&$walk, &$has_bound_menu) {
			foreach ((array) $list as $node) {
				if (!is_array($node) || empty($node['tag'])) {
					continue;
				}
				$tag = str_replace('pagelayer_', 'pl_', (string) $node['tag']);
				if ($tag === 'pl_wp_menu') {
					$nav = isset($node['attrs']['nav_list']) ? trim((string) $node['attrs']['nav_list']) : '';
					if ($nav !== '' && $nav !== '0') {
						$has_bound_menu = true;
						return;
					}
				}
				if (isset($node['content']) && is_array($node['content'])) {
					$walk($node['content']);
				}
			}
		};
		$walk($nodes);
		return !$has_bound_menu;
	}

	private function find_theme_template_id($spec, $post_id = 0) {
		if ($post_id && get_post_type($post_id) === 'pagelayer-template') {
			$current = (string) get_post_meta($post_id, 'pagelayer_template_type', true);
			if ($current === $spec['type']) {
				return (int) $post_id;
			}
		}

		$posts = get_posts(array(
			'post_type'      => 'pagelayer-template',
			'post_status'    => array('publish', 'draft'),
			'posts_per_page' => 20,
			'meta_key'       => 'pagelayer_template_type',
			'meta_value'     => $spec['type'],
			'fields'         => 'ids',
		));
		if (empty($posts)) {
			return 0;
		}

		$want_sub = '';
		if (!empty($spec['conditions'][0]['sub_template'])) {
			$want_sub = (string) $spec['conditions'][0]['sub_template'];
		}

		if ($want_sub === '') {
			return (int) $posts[0];
		}

		foreach ($posts as $id) {
			$conds = get_post_meta($id, 'pagelayer_template_conditions', true);
			if (!is_array($conds)) {
				continue;
			}
			foreach ($conds as $c) {
				if (!empty($c['sub_template']) && (string) $c['sub_template'] === $want_sub) {
					return (int) $id;
				}
			}
		}

		return 0;
	}

	private function save_theme_template_direct($spec, $nodes, $existing_id = 0) {
		$template_id = (int) $existing_id;
		if ($template_id <= 0) {
			$template_id = wp_insert_post(array(
				'post_title'  => $spec['title'],
				'post_type'   => 'pagelayer-template',
				'post_status' => 'publish',
			), true);
			if (is_wp_error($template_id) || !$template_id) {
				return 0;
			}
		} else {
			wp_update_post(array(
				'ID'         => $template_id,
				'post_title' => $spec['title'],
			));
		}

		$normalized = $nodes;
		if (class_exists('Pagelayer_Abilities_Register') && method_exists('Pagelayer_Abilities_Register', 'normalize_layout_data')) {
			$normalized = Pagelayer_Abilities_Register::normalize_layout_data($nodes);
		}

		update_post_meta($template_id, 'pagelayer-data', $normalized);
		update_post_meta($template_id, 'pagelayer_template_type', $spec['type']);
		update_post_meta($template_id, 'pagelayer_template_conditions', $spec['conditions']);

		if (class_exists('Pagelayer_Abilities_Register') && method_exists('Pagelayer_Abilities_Register', 'serialize_layout_to_blocks')) {
			$blocks = Pagelayer_Abilities_Register::serialize_layout_to_blocks($normalized);
			wp_update_post(array('ID' => $template_id, 'post_content' => $blocks));
		}

		return (int) $template_id;
	}

	private function theme_template_edit_url($template_id) {
		$template_id = (int) $template_id;
		if ($template_id <= 0) {
			return '';
		}
		if (function_exists('pagelayer_livelink')) {
			return pagelayer_livelink($template_id);
		}
		return admin_url('post.php?post=' . $template_id . '&action=edit');
	}

	private function filter_plan_layout_for_kind($layout, $kind) {
		if (!is_array($layout) || $layout === array()) {
			return $layout;
		}
		$drop = '/(feature|service|testimonial|review|pricing|hero|cta|faq|counter|stats|team|gallery)/i';
		if ($kind === 'footer') {
			$keep = array();
			foreach ($layout as $block) {
				$name = is_array($block) && !empty($block['name']) ? (string) $block['name'] : '';
				if ($name !== '' && preg_match($drop, $name) && !preg_match('/(footer|copyright|link|contact|social|brand|about)/i', $name)) {
					continue;
				}
				$keep[] = $block;
			}
			return $keep;
		}
		if ($kind === 'header') {
			return array_slice($layout, 0, 1);
		}
		if ($kind === '404') {
			$keep = array();
			foreach ($layout as $block) {
				$name = is_array($block) && !empty($block['name']) ? (string) $block['name'] : '';
				if ($name !== '' && preg_match($drop, $name) && !preg_match('/(404|error|not\s*found|search)/i', $name)) {
					continue;
				}
				$keep[] = $block;
			}
			return $keep;
		}
		return $layout;
	}

	private function filter_plan_layout_widgets($layout, $allowed) {
		if (!is_array($layout) || empty($allowed) || !is_array($allowed)) {
			return $layout;
		}
		$allow = array_flip($allowed);
		foreach ($layout as &$block) {
			if (!is_array($block) || empty($block['widgets']) || !is_array($block['widgets'])) {
				continue;
			}
			$keep = array();
			foreach ($block['widgets'] as $tag) {
				$tag = is_string($tag) ? $tag : '';
				if ($tag !== '' && isset($allow[$tag])) {
					$keep[] = $tag;
				}
			}
			$block['widgets'] = $keep;
		}
		unset($block);
		return $layout;
	}

	private function call_provider($call, $system_prompt, $user_message, $opts = array()) {
		$provider = $call['provider'];
		$api_key  = $call['api_key'];
		$model    = $call['model'];
		$settings = $call['settings'];
		$params   = $call['params'];

		if ($provider === 'deepseek') {
			return $this->call_deepseek($api_key, $model, $system_prompt, $user_message, $opts);
		}
		if ($provider === 'gemini') {
			return $this->call_gemini($api_key, $model, $system_prompt, $user_message, $opts);
		}
		if ($provider === 'openrouter') {
			return $this->call_openrouter($api_key, $model, $system_prompt, $user_message, $opts);
		}
		if ($provider === 'openai') {
			return $this->call_openai($api_key, $model, $system_prompt, $user_message, $opts);
		}
		if ($provider === 'anthropic') {
			return $this->call_anthropic($api_key, $model, $system_prompt, $user_message, $opts);
		}
		if ($provider === 'custom') {
			$endpoint = !empty($params['custom_endpoint']) ? $params['custom_endpoint'] : $settings['custom_endpoint'];
			$custom_m = !empty($params['custom_model']) ? $params['custom_model'] : (!empty($settings['custom_model']) ? $settings['custom_model'] : $model);
			return $this->call_custom_endpoint($endpoint, $api_key, $custom_m, $system_prompt, $user_message, $opts);
		}

		return new WP_Error('invalid_provider', __('Unsupported AI provider selected.', 'pagelayer'), array('status' => 400));
	}

/**
	 * Parse shortcode structure into component tree for fast live step-by-step building
	 */
	public function parse_shortcodes_to_tree($shortcode) {
		$tree = array();
		$shortcode = $this->sanitize_ai_shortcode($shortcode);
		$catalog = Pagelayer_AI_Layout_Engine::instance()->get_widget_catalog();

		// Match rows
		if (preg_match_all('/\[pl_row([^\]]*)\](.*?)\[\/pl_row\]/is', $shortcode, $row_matches, PREG_SET_ORDER)) {
			foreach ($row_matches as $r_idx => $r_match) {
				$row_atts_raw = trim($r_match[1]);
				$row_atts = shortcode_parse_atts($row_atts_raw);
				if (!is_array($row_atts)) $row_atts = array();

				$row_content = $r_match[2];
				$row_node = array(
					'id'    => 'row_' . $r_idx,
					'type'  => 'pl_row',
					'tag'   => 'pl_row',
					'name'  => 'Section Row',
					'atts'  => $row_atts,
					'cols'  => array(),
				);

				// Match columns inside row
				if (preg_match_all('/\[pl_col([^\]]*)\](.*?)\[\/pl_col\]/is', $row_content, $col_matches, PREG_SET_ORDER)) {
					foreach ($col_matches as $c_idx => $c_match) {
						$col_atts_raw = trim($c_match[1]);
						$col_atts = shortcode_parse_atts($col_atts_raw);
						if (!is_array($col_atts)) $col_atts = array();

						$col_content = $c_match[2];
						$col_width_label = !empty($col_atts['col']) ? $col_atts['col'] . '/12' : 'Full';
						$col_node = array(
							'id'      => 'col_' . $r_idx . '_' . $c_idx,
							'type'    => 'pl_col',
							'tag'     => 'pl_col',
							'name'    => 'Column (' . $col_width_label . ')',
							'atts'    => $col_atts,
							'widgets' => array(),
						);

						// Match widgets inside column
						$widget_pattern = '/\[(pl_[a-zA-Z0-9_-]+)([^\]]*)(?:\](.*?)\[\/\1\]|\s*\/\]|\])/is';
						if (preg_match_all($widget_pattern, $col_content, $widget_matches, PREG_SET_ORDER)) {
							foreach ($widget_matches as $w_idx => $w_match) {
								$w_tag      = $w_match[1];
								$w_atts_raw = trim($w_match[2]);
								$w_atts     = shortcode_parse_atts($w_atts_raw);
								if (!is_array($w_atts)) $w_atts = array();
								$w_inner    = isset($w_match[3]) ? trim($w_match[3]) : '';

								// Individual widget shortcode and HTML
								$single_sc   = '[' . $w_tag . ($w_atts_raw ? ' ' . $w_atts_raw : '') . ']' . ($w_inner ? $w_inner . '[/' . $w_tag . ']' : '');
								$single_html = $this->render_shortcodes_for_canvas($single_sc);

								$w_name = ucfirst(str_replace('_', ' ', str_replace('pl_', '', $w_tag)));
								if (isset($catalog[$w_tag])) {
									$w_name = explode('|', $catalog[$w_tag])[0];
								}

								$col_node['widgets'][] = array(
									'id'        => 'w_' . $r_idx . '_' . $c_idx . '_' . $w_idx,
									'type'      => $w_tag,
									'tag'       => $w_tag,
									'name'      => $w_name,
									'atts'      => $w_atts,
									'inner'     => $w_inner,
									'shortcode' => $single_sc,
									'html'      => $single_html,
								);
							}
						}

						$row_node['cols'][] = $col_node;
					}
				}

				$tree[] = $row_node;
			}
		}

		return $tree;
	}

	/**
	 * Call Google Gemini API
	 */
	private function call_gemini($api_key, $model, $system_prompt, $user_prompt, $opts = array()) {
		if (empty($model) || $model === 'gemini-2.5-flash' || $model === 'gemini-2.5-pro' || strpos($model, 'gemini-2.5') !== false) {
			$model = 'gemini-3.7-flash';
		}
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($api_key);

		$payload = array(
			'systemInstruction' => array(
				'parts' => array(
					array('text' => $system_prompt)
				)
			),
			'contents' => array(
				array(
					'role' => 'user',
					'parts' => array(
						array('text' => $user_prompt)
					)
				)
			),
			'generationConfig' => array(
				'temperature'     => isset($opts['temperature']) ? floatval($opts['temperature']) : 0.4,
				'maxOutputTokens' => !empty($opts['max_tokens']) ? intval($opts['max_tokens']) : 8192,
			)
		);
		if (!empty($opts['json'])) {
			$payload['generationConfig']['responseMimeType'] = 'application/json';
		}

		$response = wp_remote_post($url, array(
			'timeout' => 120,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode($payload),
		));

		if (is_wp_error($response)) {
			return new WP_Error('gemini_request_failed', $response->get_error_message(), array('status' => 500));
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($code !== 200) {
			$err_msg = !empty($data['error']['message']) ? $data['error']['message'] : sprintf(__('Gemini API returned error code %d', 'pagelayer'), $code);
			return new WP_Error('gemini_api_error', $err_msg, array('status' => $code));
		}

		if (!empty($data['candidates'][0]['content']['parts']) && is_array($data['candidates'][0]['content']['parts'])) {
			$text = '';
			foreach ($data['candidates'][0]['content']['parts'] as $part) {
				if (!empty($part['text'])) {
					$text .= $part['text'];
				}
			}
			if ($text !== '') {
				return $text;
			}
		}

		return new WP_Error('gemini_empty_response', __('Gemini returned an empty candidate list.', 'pagelayer'), array('status' => 500));
	}

	/**
	 * Call OpenAI API
	 */
	private function call_openai($api_key, $model, $system_prompt, $user_prompt, $opts = array()) {
		if (empty($model)) $model = 'gpt-4o-mini';
		$url = 'https://api.openai.com/v1/chat/completions';

		$payload = array(
			'model'    => $model,
			'messages' => array(
				array('role' => 'system', 'content' => $system_prompt),
				array('role' => 'user', 'content' => $user_prompt),
			),
			'temperature' => isset($opts['temperature']) ? floatval($opts['temperature']) : 0.4,
			'max_tokens'  => !empty($opts['max_tokens']) ? intval($opts['max_tokens']) : 16384,
		);
		if (preg_match('/^(o1|o3|gpt-4o|chatgpt)/i', $model)) {
			$payload['max_completion_tokens'] = !empty($opts['max_tokens']) ? intval($opts['max_tokens']) : 16384;
			if (strpos($model, 'o1') === 0 || strpos($model, 'o3') === 0) {
				unset($payload['max_tokens']);
				unset($payload['temperature']);
			}
		}
		if (!empty($opts['json'])) {
			$payload['response_format'] = array('type' => 'json_object');
		}

		$response = wp_remote_post($url, array(
			'timeout' => 120,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode($payload),
		));

		if (is_wp_error($response)) {
			return new WP_Error('openai_request_failed', $response->get_error_message(), array('status' => 500));
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($code !== 200) {
			$err_msg = !empty($data['error']['message']) ? $data['error']['message'] : sprintf(__('OpenAI API returned error code %d', 'pagelayer'), $code);
			return new WP_Error('openai_api_error', $err_msg, array('status' => $code));
		}

		if (!empty($data['choices'][0]['message']['content'])) {
			return $data['choices'][0]['message']['content'];
		}

		return new WP_Error('openai_empty_response', __('OpenAI returned an empty response.', 'pagelayer'), array('status' => 500));
	}

	/**
	 * Call Anthropic Claude API
	 */
	private function call_anthropic($api_key, $model, $system_prompt, $user_prompt, $opts = array()) {
		if (empty($model)) $model = 'claude-3-7-sonnet-20250219';
		$url = 'https://api.anthropic.com/v1/messages';

		$payload = array(
			'model'      => $model,
			'system'     => $system_prompt,
			'messages'   => array(
				array('role' => 'user', 'content' => $user_prompt)
			),
			'max_tokens' => !empty($opts['max_tokens']) ? intval($opts['max_tokens']) : 8192,
			'temperature'=> isset($opts['temperature']) ? floatval($opts['temperature']) : 0.4,
		);

		$response = wp_remote_post($url, array(
			'timeout'         => 120,
			'connect_timeout' => 30,
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode($payload),
		));

		if (is_wp_error($response)) {
			return new WP_Error('anthropic_request_failed', $response->get_error_message(), array('status' => 500));
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($code !== 200) {
			$err_msg = !empty($data['error']['message']) ? $data['error']['message'] : sprintf(__('Anthropic API returned error code %d', 'pagelayer'), $code);
			return new WP_Error('anthropic_api_error', $err_msg, array('status' => $code));
		}

		if (!empty($data['content'][0]['text'])) {
			return $data['content'][0]['text'];
		}

		return new WP_Error('anthropic_empty_response', __('Anthropic returned an empty response.', 'pagelayer'), array('status' => 500));
	}

	/**
	 * Call DeepSeek Official API (deepseek-chat / deepseek-reasoner)
	 */
	private function call_deepseek($api_key, $model, $system_prompt, $user_prompt, $opts = array()) {
		$model = trim($model);
		if (strpos($model, '/') !== false) {
			$model = basename($model);
		}
		if (empty($model) || ($model !== 'deepseek-reasoner' && $model !== 'deepseek-chat')) {
			if (stripos($model, 'reason') !== false || stripos($model, 'r1') !== false) {
				$model = 'deepseek-reasoner';
			} else {
				$model = 'deepseek-chat';
			}
		}

		$url = 'https://api.deepseek.com/chat/completions';
		$api_key = trim($api_key);

		$payload = array(
			'model'       => $model,
			'messages'    => array(
				array('role' => 'system', 'content' => $system_prompt),
				array('role' => 'user',   'content' => $user_prompt),
			),
			'temperature' => isset($opts['temperature']) ? floatval($opts['temperature']) : 0.4,
			'max_tokens'  => !empty($opts['max_tokens']) ? intval($opts['max_tokens']) : 8192,
		);

		$response = wp_remote_post($url, array(
			'timeout'   => 120,
			'sslverify' => false,
			'headers'   => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'body'            => wp_json_encode($payload),
		));

		if (is_wp_error($response)) {
			return new WP_Error('deepseek_request_failed', $response->get_error_message(), array('status' => 500));
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($code !== 200) {
			$err_msg = !empty($data['error']['message']) ? $data['error']['message'] : sprintf(__('DeepSeek API returned error code %d', 'pagelayer'), $code);
			if ($code === 402 || stripos($err_msg, 'balance') !== false) {
				$err_msg = __('DeepSeek API: Insufficient account balance. Please check/top-up your balance at platform.deepseek.com', 'pagelayer');
			} elseif ($code === 401 || stripos($err_msg, 'Authentication') !== false || stripos($err_msg, 'invalid key') !== false) {
				$err_msg = __('DeepSeek API: Authentication failed. Please verify your DeepSeek API key in AI Settings.', 'pagelayer');
			}
			return new WP_Error('deepseek_api_error', $err_msg, array('status' => $code));
		}

		if (!empty($data['choices'][0]['message']['content'])) {
			return $data['choices'][0]['message']['content'];
		}

		return new WP_Error('deepseek_empty_response', __('DeepSeek returned an empty response.', 'pagelayer'), array('status' => 500));
	}

	/**
	 * Call OpenRouter API (Access to ALL models: DeepSeek, Llama, Qwen, Mistral, etc.)
	 */
	private function call_openrouter($api_key, $model, $system_prompt, $user_prompt, $opts = array()) {
		if (empty($model)) $model = 'deepseek/deepseek-chat';
		$url = 'https://openrouter.ai/api/v1/chat/completions';
		$api_key = trim($api_key);

		$payload = array(
			'model'       => $model,
			'messages'    => array(
				array('role' => 'system', 'content' => $system_prompt),
				array('role' => 'user',   'content' => $user_prompt),
			),
			'temperature' => isset($opts['temperature']) ? floatval($opts['temperature']) : 0.4,
			'max_tokens'  => !empty($opts['max_tokens']) ? intval($opts['max_tokens']) : 8192,
		);

		$response = wp_remote_post($url, array(
			'timeout'         => 120,
			'connect_timeout' => 30,
			'sslverify'       => false,
			'headers'         => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'HTTP-Referer'  => site_url(),
				'X-Title'       => 'Pagelayer AI',
			),
			'body'            => wp_json_encode($payload),
		));

		if (is_wp_error($response)) {
			return new WP_Error('openrouter_request_failed', $response->get_error_message(), array('status' => 500));
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($code !== 200) {
			$err_msg = !empty($data['error']['message']) ? $data['error']['message'] : sprintf(__('OpenRouter API returned error code %d', 'pagelayer'), $code);
			return new WP_Error('openrouter_api_error', $err_msg, array('status' => $code));
		}

		if (!empty($data['choices'][0]['message']['content'])) {
			return $data['choices'][0]['message']['content'];
		}

		return new WP_Error('openrouter_empty_response', __('OpenRouter returned an empty response.', 'pagelayer'), array('status' => 500));
	}

	/**
	 * Call Custom / OpenAI-Compatible Endpoint (OpenCode, Ollama, LM Studio, Groq, DeepSeek, Together, etc.)
	 */
	private function call_custom_endpoint($endpoint, $api_key, $model, $system_prompt, $user_prompt, $opts = array()) {
		$endpoint = trim($endpoint);
		if (empty($endpoint)) {
			$endpoint = 'https://openrouter.ai/api/v1/chat/completions';
		}
		if (strpos($endpoint, '/chat/completions') === false) {
			$endpoint = rtrim($endpoint, '/') . '/chat/completions';
		}

		if (empty($model)) $model = 'custom-model';

		$payload = array(
			'model' => $model,
			'messages' => array(
				array('role' => 'system', 'content' => $system_prompt),
				array('role' => 'user',   'content' => $user_prompt),
			),
			'temperature' => isset($opts['temperature']) ? floatval($opts['temperature']) : 0.4,
			'max_tokens'  => !empty($opts['max_tokens']) ? intval($opts['max_tokens']) : 8192,
		);

		$headers = array(
			'Content-Type' => 'application/json',
		);
		if (!empty($api_key)) {
			$headers['Authorization'] = 'Bearer ' . trim($api_key);
		}

		$response = wp_remote_post($endpoint, array(
			'timeout'   => 120,
			'sslverify' => false,
			'headers'   => $headers,
			'body'      => wp_json_encode($payload),
		));

		if (is_wp_error($response)) {
			return new WP_Error('custom_endpoint_failed', $response->get_error_message(), array('status' => 500));
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($code !== 200) {
			$err_msg = !empty($data['error']['message']) ? $data['error']['message'] : sprintf(__('Custom API returned error code %d', 'pagelayer'), $code);
			return new WP_Error('custom_api_error', $err_msg, array('status' => $code));
		}

		if (!empty($data['choices'][0]['message']['content'])) {
			return $data['choices'][0]['message']['content'];
		}

		return new WP_Error('custom_empty_response', __('Custom API returned an empty response.', 'pagelayer'), array('status' => 500));
	}

	/**
	 * Clean, sanitize and normalize shortcodes from LLM response
	 */
	public function sanitize_ai_shortcode($raw_text) {
		$text = trim($raw_text);

		// Remove Markdown code block fences
		$text = preg_replace('/^```[a-zA-Z0-9_-]*\s*/i', '', $text);
		$text = preg_replace('/\s*```$/i', '', $text);
		$text = trim($text);

		// Normalize [pagelayer_*] into [pl_*] to match Pagelayer shortcode registry
		$text = preg_replace('/\[(\/)?pagelayer_([a-zA-Z0-9_-]+)/', '[$1pl_$2', $text);

		// If shortcode lacks an outer row, wrap it in a default row & column
		if (strpos($text, '[pl_row') === false && strpos($text, '[pagelayer_row') === false) {
			$text = '[pl_row col_gap="20" ele_padding="50px,20px,50px,20px"][pl_col col="12"]' . $text . '[/pl_col][/pl_row]';
		}

		// Remove empty spacer columns. Card grids of 4+ items become sibling
		// rows of 3 columns (col=4) instead of being squashed into one row.
		$text = preg_replace_callback('/\[pl_row([^\]]*)\](.*?)\[\/pl_row\]/is', function($row_match) {
			$row_atts = $row_match[1];
			$row_content = $row_match[2];

			if (strpos($row_atts, 'col_gap=') === false) {
				$row_atts .= ' col_gap="20"';
			}

			$card_tags = 'pl_iconbox|pl_service|pl_testimonial|pl_counter|pl_pricing|pl_flipbox|pl_review|pl_author_box';

			$emit_row = function ($atts, $cols) {
				$content = '';
				foreach ($cols as $c) {
					$content .= '[pl_col' . $c['atts'] . ']' . $c['inner'] . '[/pl_col]';
				}
				return '[pl_row' . $atts . ']' . $content . '[/pl_row]';
			};

			$set_col_width = function ($col, $width) {
				$atts = preg_replace('/col="[^"]*"/', 'col="' . $width . '"', $col['atts']);
				if (strpos($atts, 'col="') === false) {
					$atts = ' col="' . $width . '"' . $atts;
				}
				$col['atts'] = $atts;
				return $col;
			};

			if (preg_match_all('/\[pl_col([^\]]*)\](.*?)\[\/pl_col\]/is', $row_content, $col_matches, PREG_SET_ORDER)) {
				$non_empty_cols = array();
				foreach ($col_matches as $col) {
					$col_atts = $col[1];
					$col_inner = trim($col[2]);
					if (!empty($col_inner) && preg_match('/\[pl_[a-zA-Z0-9_-]+|<[a-z]+|[a-zA-Z0-9]/', $col_inner)) {
						$non_empty_cols[] = array(
							'raw'   => $col[0],
							'atts'  => $col_atts,
							'inner' => $col_inner,
							'is_card' => (bool) preg_match('/\[(' . $card_tags . ')\b/i', $col_inner)
								&& !preg_match('/\[pl_(heading|text|btn|image|row|inner_row)\b/i', $col_inner),
						);
					}
				}

				if (!empty($non_empty_cols)) {
					if (count($non_empty_cols) === 1) {
						$only = $set_col_width($non_empty_cols[0], 12);
						return $emit_row($row_atts, array($only));
					}

					// Split leading full-width header columns from a trailing card grid.
					$header = array();
					$grid   = array();
					$in_grid = false;
					foreach ($non_empty_cols as $c) {
						$w = 0;
						if (preg_match('/col="(\d+)"/', $c['atts'], $cm)) {
							$w = intval($cm[1]);
						}
						if (!$in_grid && ($c['is_card'] || ($w > 0 && $w <= 4))) {
							$in_grid = true;
						}
						if ($in_grid) {
							$grid[] = $c;
						} else {
							$header[] = $c;
						}
					}

					// 4+ card/grid columns: new sibling rows of 3, never squash to one line.
					if (count($grid) >= 2) {
						$out = '';
						if (!empty($header)) {
							foreach ($header as $i => $h) {
								$header[$i] = $set_col_width($h, 12);
							}
							$head_atts = $row_atts;
							$head_atts = preg_replace('/ele_padding="[^"]*"/', 'ele_padding="50px,20px,50px,20px"', $head_atts);
							if (strpos($head_atts, 'ele_padding=') === false) {
								$head_atts .= ' ele_padding="50px,20px,50px,20px"';
							}
							$out .= $emit_row($head_atts, $header);
						}
						$chunks = array_chunk($grid, 3);
						foreach ($chunks as $ci => $chunk) {
							$n = count($chunk);
							$width = ($n >= 3) ? 4 : (($n === 2) ? 6 : 4);
							foreach ($chunk as $i => $c) {
								$chunk[$i] = $set_col_width($c, $width);
							}
							$chunk_atts = $row_atts;
							$pad = '50px,20px,50px,20px';
							if (preg_match('/ele_padding="/', $chunk_atts)) {
								$chunk_atts = preg_replace('/ele_padding="[^"]*"/', 'ele_padding="' . $pad . '"', $chunk_atts);
							} else {
								$chunk_atts .= ' ele_padding="' . $pad . '"';
							}
							$out .= $emit_row($chunk_atts, $chunk);
						}
						return $out;
					}

					$total_cols = 0;
					foreach ($non_empty_cols as $c) {
						if (preg_match('/col="(\d+)"/', $c['atts'], $cm)) {
							$total_cols += intval($cm[1]);
						}
					}

					// Only redistribute when a small row is missing widths (spacer
					// columns removed). Never force 4+ columns to share a single 12.
					$count = count($non_empty_cols);
					if ($total_cols !== 12 && $count <= 3) {
						$each_col = (int) floor(12 / $count);
						foreach ($non_empty_cols as $idx => $c) {
							$this_col = ($idx === 0) ? (12 - ($each_col * ($count - 1))) : $each_col;
							$non_empty_cols[$idx] = $set_col_width($c, $this_col);
						}
					}

					return $emit_row($row_atts, $non_empty_cols);
				}
			}

			// If row has no columns or all columns were empty, wrap content in full 12-column
			if (strpos($row_content, '[pl_col') === false && strpos($row_content, '[pagelayer_col') === false) {
				return '[pl_row' . $row_atts . '][pl_col col="12"]' . $row_content . '[/pl_col][/pl_row]';
			}

			return $row_match[0];
		}, $text);

		// Extract inline styles (color, align) from leaf widgets into shortcode attributes and strip style="..."
		$catalog   = Pagelayer_AI_Layout_Engine::instance()->get_widget_catalog();
		$leaf_list = array();
		foreach ($catalog as $tag => $line) {
			if (strpos($line, '|children') !== false || $tag === 'pl_row' || $tag === 'pl_col' || $tag === 'pl_inner_row' || $tag === 'pl_inner_col') {
				continue;
			}
			$leaf_list[] = preg_quote($tag, '/');
		}
		$leaf_tags = !empty($leaf_list) ? implode('|', $leaf_list) : 'pl_heading|pl_text|pl_btn|pl_image';
		$text = preg_replace_callback('/\[(' . $leaf_tags . ')([^\]]*)\](.*?)\[\/\1\]/is', function($m) {
			$tag   = $m[1];
			$atts  = $m[2];
			$inner = $m[3];

			if (preg_match('/style=[\"\']([^\"\']+)[\"\']/i', $inner, $sm)) {
				$style_str = $sm[1];

				// Extract color if not already present in attributes.
				// pl_text has no `color` prop — put the value in ele_css so it actually renders.
				if (preg_match('/(?:^|;)\s*color\s*:\s*([^;]+)/i', $style_str, $cm)) {
					$extracted = trim($cm[1]);
					$no_color_prop = ($tag === 'pl_text' || $tag === 'pl_iconbox' || $tag === 'pl_service');
					if ($no_color_prop) {
						if (strpos($atts, 'ele_css=') === false) {
							$atts .= ' ele_css="{{element}},{{element}} .pagelayer-text-holder,{{element}} .pagelayer-service-text{color:' . $extracted . '}"';
						}
					} elseif (strpos($atts, 'color=') === false) {
						$atts .= ' color="' . $extracted . '"';
					}
				}

				// Extract text-align if not already present in attributes
				if (strpos($atts, 'align=') === false && preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $style_str, $am)) {
					$atts .= ' align="' . trim($am[1]) . '"';
				}
			}

			// Clean all style attributes from inner HTML
			$inner = preg_replace('/\s*style=[\"\'][^\"\']*[\"\']/i', '', $inner);

			return '[' . $tag . $atts . ']' . $inner . '[/' . $tag . ']';
		}, $text);

		// Final pass to clean any remaining style attributes from any HTML tags
		$text = preg_replace('/(\sstyle=[\"\'][^\"\']*[\"\'])/i', '', $text);

		return $this->ensure_counter_start($this->ensure_accordion_contrast($this->ensure_social_contrast($this->ensure_slider_image_size($this->ensure_iconbox_center($this->ensure_row_section_padding($this->ensure_dark_band_contrast($text)))))));
	}

	/**
	 * Social Profile: icon glyph and circle background must contrast.
	 */
	private function ensure_social_contrast($text) {
		if (!is_string($text) || $text === '' || (stripos($text, 'social_grp') === false && stripos($text, 'social-grp') === false)) {
			return $text;
		}

		$set = function ($atts, $key, $val) {
			if (preg_match('/\s' . preg_quote($key, '/') . '="/i', $atts)) {
				return preg_replace('/\s' . preg_quote($key, '/') . '="[^"]*"/i', ' ' . $key . '="' . $val . '"', $atts, 1);
			}
			return $atts . ' ' . $key . '="' . $val . '"';
		};

		$rule = '{{element}}{display:inline-flex;flex-direction:row;flex-wrap:wrap;align-items:center;justify-content:center;gap:14px;}'
			. '{{element}} .pagelayer-icon-holder{background-color:var(--pagelayer-color-primary,#4f46e5)!important;}'
			. '{{element}} .pagelayer-social-fa{color:#ffffff!important;}';

		return preg_replace_callback('/\[(pl_social_grp|pagelayer_social_grp)([^\]]*)\]/i', function ($m) use ($set, $rule) {
			$atts = $m[2];
			$icon = '';
			$bg   = '';
			if (preg_match('/icon_color="([^"]*)"/i', $atts, $im)) {
				$icon = strtolower(trim($im[1]));
			}
			if (preg_match('/icon_bg_color="([^"]*)"/i', $atts, $bm)) {
				$bg = strtolower(trim($bm[1]));
			}
			$clash = ($icon === '' || $bg === '' || $icon === $bg);

			$atts = $set($atts, 'color_scheme', '');
			$atts = $set($atts, 'bg_shape', 'pagelayer-social-shape-circle');
			if (!preg_match('/icon_size="/i', $atts) || preg_match('/icon_size="(?:0|1|2|3|4|5|6|7|8|9|10|12|14)"/i', $atts)) {
				$atts = $set($atts, 'icon_size', '20');
			}
			if ($clash) {
				$atts = $set($atts, 'icon_color', '#ffffff');
				$atts = $set($atts, 'icon_bg_color', '$primary');
			}
			if (strpos($atts, 'pagelayer-social-fa') === false) {
				if (preg_match('/ele_css="([^"]*)"/i', $atts, $cm)) {
					$atts = preg_replace('/ele_css="([^"]*)"/i', 'ele_css="' . esc_attr($cm[1] . $rule) . '"', $atts, 1);
				} else {
					$atts .= ' ele_css="' . esc_attr($rule) . '"';
				}
			}
			return '[' . $m[1] . $atts . ']';
		}, $text);
	}

	/**
	 * Accordion / Tabs: tab text, tab background, and panel must contrast.
	 */
	private function ensure_accordion_contrast($text) {
		if (!is_string($text) || $text === '' || (stripos($text, 'accordion') === false && stripos($text, 'pl_tabs') === false)) {
			return $text;
		}

		$set = function ($atts, $key, $val) {
			if (preg_match('/\s' . preg_quote($key, '/') . '="/i', $atts)) {
				return preg_replace('/\s' . preg_quote($key, '/') . '="[^"]*"/i', ' ' . $key . '="' . $val . '"', $atts, 1);
			}
			return $atts . ' ' . $key . '="' . $val . '"';
		};

		$same = function ($a, $b) {
			$a = strtolower(trim((string) $a));
			$b = strtolower(trim((string) $b));
			return ($a === '' || $b === '' || $a === $b);
		};

		return preg_replace_callback('/\[(pl_accordion|pl_tabs|pagelayer_accordion|pagelayer_tabs)([^\]]*)\]/i', function ($m) use ($set, $same) {
			$tag  = strtolower($m[1]);
			$atts = $m[2];
			$is_tabs = (strpos($tag, 'tabs') !== false);

			$tab_fg = $this->shortcode_att($atts, 'tabs_color');
			$tab_bg = $this->shortcode_att($atts, 'tabs_bg_color');
			$act_fg = $this->shortcode_att($atts, 'tabs_active_color');
			$act_bg = $this->shortcode_att($atts, 'tabs_active_bg_color');
			$row_bg = $this->shortcode_att($atts, 'ele_bg_color');
			$dark   = $this->hex_looks_dark($row_bg);

			if ($same($tab_fg, $tab_bg) || $same($tab_fg, $row_bg) || $same($tab_bg, $row_bg)) {
				$atts = $set($atts, 'tabs_color', $dark ? '#ffffff' : '#111827');
				$atts = $set($atts, 'tabs_bg_color', $dark ? 'rgba(255,255,255,0.14)' : '#f1f5f9');
			}
			if ($same($act_fg, $act_bg)) {
				$atts = $set($atts, 'tabs_active_color', '#ffffff');
				$atts = $set($atts, 'tabs_active_bg_color', '$primary');
			}
			$atts = $set($atts, 'tabs_content_bg_color', $dark ? 'rgba(255,255,255,0.08)' : '#ffffff');
			if ($is_tabs) {
				$atts = $set($atts, 'tabs_content_color', $dark ? '#ffffff' : '#111827');
			}

			$sel_tab   = $is_tabs ? '{{element}} .pagelayer-tablinks' : '{{element}} .pagelayer-accordion-tabs';
			$sel_panel = $is_tabs ? '{{element}} .pagelayer-tab' : '{{element}} .pagelayer-accordion-panel';
			$rule = $sel_tab . '{color:' . ($dark ? '#ffffff' : '#111827') . '!important;background-color:' . ($dark ? 'rgba(255,255,255,0.14)' : '#f1f5f9') . '!important;}'
				. '{{element}} .active ' . ($is_tabs ? '.pagelayer-tablinks' : '.pagelayer-accordion-tabs') . '{color:#ffffff!important;background-color:var(--pagelayer-color-primary,#4f46e5)!important;}'
				. $sel_panel . '{color:' . ($dark ? '#ffffff' : '#111827') . '!important;background-color:' . ($dark ? 'rgba(255,255,255,0.08)' : '#ffffff') . '!important;}';
			if (strpos($atts, 'pagelayer-accordion-tabs') === false && strpos($atts, 'pagelayer-tablinks') === false) {
				if (preg_match('/ele_css="([^"]*)"/i', $atts, $cm)) {
					$atts = preg_replace('/ele_css="([^"]*)"/i', 'ele_css="' . esc_attr($cm[1] . $rule) . '"', $atts, 1);
				} else {
					$atts .= ' ele_css="' . esc_attr($rule) . '"';
				}
			}
			return '[' . $m[1] . $atts . ']';
		}, $text);
	}

	private function shortcode_att($atts, $key) {
		if (preg_match('/\s' . preg_quote($key, '/') . '="([^"]*)"/i', (string) $atts, $m)) {
			return trim($m[1]);
		}
		return '';
	}

	/**
	 * Counter start must be >= 1 — 0 hides the widget (if="{{counter_start_number}}").
	 */
	private function ensure_counter_start($text) {
		if (!is_string($text) || $text === '' || stripos($text, 'counter') === false) {
			return $text;
		}

		return preg_replace_callback('/\[(pl_counter|pagelayer_counter)([^\]]*)\]/i', function ($m) {
			$atts = $m[2];
			$start = 0;
			if (preg_match('/counter_start_number="([^"]*)"/i', $atts, $sm)) {
				$start = (int) $sm[1];
			}
			if ($start < 1) {
				if (preg_match('/counter_start_number="/i', $atts)) {
					$atts = preg_replace('/counter_start_number="[^"]*"/i', 'counter_start_number="1"', $atts, 1);
				} else {
					$atts .= ' counter_start_number="1"';
				}
				$start = 1;
			}
			$end = 0;
			if (preg_match('/counter_end_number="([^"]*)"/i', $atts, $em)) {
				$end = (int) $em[1];
			}
			if ($end <= $start) {
				$new_end = (string) max(100, $start + 99);
				if (preg_match('/counter_end_number="/i', $atts)) {
					$atts = preg_replace('/counter_end_number="[^"]*"/i', 'counter_end_number="' . $new_end . '"', $atts, 1);
				} else {
					$atts .= ' counter_end_number="' . $new_end . '"';
				}
			}
			return '[' . $m[1] . $atts . ']';
		}, $text);
	}

	/**
	 * Tag dark (and nested light) surfaces so canvas CSS can keep text readable
	 * when a widget has no color prop of its own.
	 */
	private function ensure_dark_band_contrast($text) {
		if (!is_string($text) || $text === '') {
			return $text;
		}

		$engine = class_exists('Pagelayer_AI_Layout_Engine') ? Pagelayer_AI_Layout_Engine::instance() : null;

		return preg_replace_callback(
			'/\[(pl_(?:inner_)?(?:row|col|iconbox|service|testimonial|quote|counter|pricing|flipbox|review|author_box|call|countdown|heading|text|list|accordion|tabs|alert|address|phone|email))([^\]]*)\]/i',
			function ($m) use ($engine) {
				$tag  = $m[1];
				$atts = $m[2];
				if (strpos($atts, 'pagelayer-ai-on-dark') !== false || strpos($atts, 'pagelayer-ai-on-light') !== false) {
					return $m[0];
				}
				if (!preg_match('/ele_bg_color="([^"]*)"/i', $atts, $bm) || trim($bm[1]) === '') {
					return $m[0];
				}
				$dark = $engine
					? $engine->is_dark_color($bm[1], false)
					: $this->hex_looks_dark($bm[1]);
				$cls  = $dark ? 'pagelayer-ai-on-dark' : 'pagelayer-ai-on-light';
				if (preg_match('/ele_classes="/i', $atts)) {
					$atts = preg_replace('/ele_classes="/i', 'ele_classes="' . $cls . ' ', $atts, 1);
				} else {
					$atts .= ' ele_classes="' . $cls . '"';
				}
				return '[' . $tag . $atts . ']';
			},
			$text
		);
	}

	private function hex_looks_dark($val) {
		$val = strtolower(trim((string) $val));
		if ($val === '' || $val === 'transparent') {
			return false;
		}
		if (in_array($val, array('black', 'navy', '#000', '#000000', '#111', '#111111'), true) || $val === '$text') {
			return true;
		}
		if ($val === 'white' || $val === '#fff' || $val === '#ffffff') {
			return false;
		}
		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $val, $m)) {
			$hex = $m[1];
			if (strlen($hex) === 3) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$r = hexdec(substr($hex, 0, 2));
			$g = hexdec(substr($hex, 2, 2));
			$b = hexdec(substr($hex, 4, 2));
			return ((0.299 * $r + 0.587 * $g + 0.114 * $b) / 255) < 0.55;
		}
		return false;
	}

	/**
	 * Every outer section row keeps 50px top and bottom padding so the next
	 * band does not sit flush against the columns above it.
	 */
	private function ensure_row_section_padding($text) {
		if (!is_string($text) || $text === '') {
			return $text;
		}

		$engine = class_exists('Pagelayer_AI_Layout_Engine') ? Pagelayer_AI_Layout_Engine::instance() : null;

		return preg_replace_callback('/\[pl_row([^\]]*)\]/i', function ($m) use ($engine) {
			$atts = $m[1];
			$pad  = '';
			if (preg_match('/ele_padding="([^"]*)"/i', $atts, $pm)) {
				$pad = $pm[1];
			}
			$fixed = $engine
				? $engine->format_section_row_padding($pad)
				: '50px,20px,50px,20px';
			if (preg_match('/ele_padding="/i', $atts)) {
				$atts = preg_replace('/ele_padding="[^"]*"/i', 'ele_padding="' . $fixed . '"', $atts, 1);
			} else {
				$atts .= ' ele_padding="' . $fixed . '"';
			}
			return '[pl_row' . $atts . ']';
		}, $text);
	}

	/**
	 * Icon / image boxes: centered icon (Horizontal Position), heading, and copy.
	 */
	private function ensure_iconbox_center($text) {
		if (!is_string($text) || $text === '') {
			return $text;
		}

		$set = function ($atts, $key, $val) {
			if (preg_match('/\s' . preg_quote($key, '/') . '="/i', $atts)) {
				return preg_replace('/\s' . preg_quote($key, '/') . '="[^"]*"/i', ' ' . $key . '="' . $val . '"', $atts, 1);
			}
			return $atts . ' ' . $key . '="' . $val . '"';
		};

		$append_css = function ($atts, $rule) {
			if (strpos($atts, $rule) !== false) {
				return $atts;
			}
			if (preg_match('/ele_css="([^"]*)"/i', $atts, $cm)) {
				return preg_replace('/ele_css="([^"]*)"/i', 'ele_css="$1' . $rule . '"', $atts, 1);
			}
			return $atts . ' ele_css="' . $rule . '"';
		};

		return preg_replace_callback('/\[(pl_iconbox|pl_service)([^\]]*)\]/i', function ($m) use ($set, $append_css) {
			$tag  = strtolower($m[1]);
			$atts = $m[2];
			$atts = $set($atts, 'service_alignment', 'top');
			$atts = $set($atts, ($tag === 'pl_iconbox') ? 'service_icon_alignment' : 'service_img_alignment', 'center');
			$atts = $set($atts, 'heading_alignment', 'center');
			$atts = $set($atts, 'service_text_alignment', 'center');
			if (strpos($atts, 'font_size=') === false) {
				$atts = $set($atts, 'font_size', '15');
			}
			if (strpos($atts, 'line_height=') === false) {
				$atts = $set($atts, 'line_height', '1.65');
			}
			if ($tag === 'pl_service') {
				if (strpos($atts, 'service_image_height=') === false) {
					$atts = $set($atts, 'service_image_height', '220');
				}
				if (strpos($atts, 'service_image_object_fit=') === false) {
					$atts = $set($atts, 'service_image_object_fit', 'cover');
				}
				if (strpos($atts, 'service_image_object_pos=') === false) {
					$atts = $set($atts, 'service_image_object_pos', 'center');
				}
				if (strpos($atts, 'service_image_border_radius=') === false) {
					$atts = $set($atts, 'service_image_border_radius', '8,8,8,8');
				}
				$rule = '{{element}} .pagelayer-service-image{width:100%!important;overflow:hidden;margin:0 auto 16px auto;border-radius:8px;}'
					. '{{element}} .pagelayer-service-image img{width:100%!important;height:220px!important;max-height:220px!important;min-height:220px!important;object-fit:cover!important;object-position:center!important;border-radius:8px;display:block;margin:0 auto;}'
					. '{{element}} .pagelayer-service-container{display:flex;flex-direction:column;height:100%;}'
					. '{{element}} .pagelayer-service-details{display:flex;flex-direction:column;flex-grow:1;}'
					. '{{element}} .pagelayer-service-heading{text-align:center;font-weight:700;line-height:1.3;margin-bottom:8px;}'
					. '{{element}} .pagelayer-service-text{text-align:center;font-size:15px;line-height:1.7;font-weight:400;flex-grow:1;margin-bottom:16px;}'
					. '{{element}} .pagelayer-service-btn{margin-top:auto;align-self:center;}';
				$atts = $append_css($atts, $rule);
			} else {
				if (strpos($atts, 'service_icon_font_size=') === false) {
					$atts = $set($atts, 'service_icon_font_size', '28');
				}
				$rule = '{{element}} .pagelayer-service-icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:64px!important;height:64px!important;min-width:64px!important;min-height:64px!important;margin:0 auto 16px auto!important;line-height:1!important;}'
					. '{{element}} .pagelayer-service-icon i,{{element}} .pagelayer-service-icon svg{font-size:28px!important;width:28px!important;height:28px!important;line-height:28px!important;display:inline-block!important;}'
					. '{{element}} .pagelayer-service-container{display:flex;flex-direction:column;height:100%;}'
					. '{{element}} .pagelayer-service-details{display:flex;flex-direction:column;flex-grow:1;}'
					. '{{element}} .pagelayer-service-heading{text-align:center;font-weight:700;line-height:1.3;margin-bottom:8px;}'
					. '{{element}} .pagelayer-service-text{text-align:center;font-size:15px;line-height:1.7;font-weight:400;flex-grow:1;margin-bottom:16px;}'
					. '{{element}} .pagelayer-service-btn{margin-top:auto;align-self:center;}';
				$atts = $append_css($atts, $rule);
			}
			return '[' . $m[1] . $atts . ']';
		}, $text);
	}

	/**
	 * Ensure image slider images are constrained to a balanced height (420px, object-fit: cover)
	 * instead of blowing up to full natural image height.
	 */
	private function ensure_slider_image_size($text) {
		if (empty($text) || (stripos($text, 'image_slider') === false && stripos($text, 'image-slider') === false)) {
			return $text;
		}

		$rule = '{{element}}{max-width:1000px!important;margin:0 auto!important;overflow:visible!important;}'
			. '{{element}} .pagelayer-image-slider-div{max-width:1000px!important;margin:0 auto!important;overflow:visible!important;border-radius:12px!important;}'
			. '{{element}} .pagelayer-image-slider-ul{margin:0 auto!important;}'
			. '{{element}} .pagelayer-slider-item{overflow:hidden!important;border-radius:12px!important;}'
			. '{{element}} .pagelayer-slider-item img,{{element}} img.pagelayer-img,{{element}} .pagelayer-image-slider-div img{width:100%!important;height:420px!important;max-height:440px!important;min-height:280px!important;object-fit:cover!important;object-position:center!important;border-radius:12px!important;display:block!important;margin:0 auto!important;}'
			. '{{element}} .pagelayer-owl-nav{z-index:5!important;}'
			. '{{element}} .pagelayer-owl-prev,{{element}} .pagelayer-owl-next{width:48px!important;height:48px!important;min-width:48px!important;min-height:48px!important;border-radius:50%!important;background:rgba(15,23,42,0.72)!important;color:#ffffff!important;display:flex!important;align-items:center!important;justify-content:center!important;opacity:1!important;}'
			. '{{element}} .pagelayer-owl-prev span,{{element}} .pagelayer-owl-next span,{{element}} .pagelayer-owl-prev i,{{element}} .pagelayer-owl-next i{font-size:28px!important;line-height:1!important;color:#ffffff!important;}';

		$set = function ($atts, $key, $val) {
			if (preg_match('/\s' . preg_quote($key, '/') . '="/i', $atts)) {
				return preg_replace('/\s' . preg_quote($key, '/') . '="[^"]*"/i', ' ' . $key . '="' . $val . '"', $atts, 1);
			}
			return $atts . ' ' . $key . '="' . $val . '"';
		};

		return preg_replace_callback('/\[(pl_image_slider|pagelayer-image[_-]slider)(\s+[^\]]*)?\]/i', function ($m) use ($rule, $set) {
			$atts = isset($m[2]) ? $m[2] : '';
			$atts = $set($atts, 'controls', 'arrows');
			if (!preg_match('/nav_size="/i', $atts) || preg_match('/nav_size="(?:0|1|2|3|4|5|6|7|8|9|10|12|14|16)"/i', $atts)) {
				$atts = $set($atts, 'nav_size', '28');
			}
			if (!preg_match('/arraow_bg_size="/i', $atts) || preg_match('/arraow_bg_size="(?:0|1[0-9]|2[0-9]|3[0-5])"/i', $atts)) {
				$atts = $set($atts, 'arraow_bg_size', '48');
			}
			if (!preg_match('/arraow_bg_shape="/i', $atts)) {
				$atts = $set($atts, 'arraow_bg_shape', '50');
			}
			$arrow_fg = '';
			$arrow_bg = '';
			if (preg_match('/arraow_color="([^"]*)"/i', $atts, $fm)) {
				$arrow_fg = strtolower(trim($fm[1]));
			}
			if (preg_match('/arrows_bg="([^"]*)"/i', $atts, $bm)) {
				$arrow_bg = strtolower(trim($bm[1]));
			}
			if ($arrow_fg === '' || $arrow_bg === '' || $arrow_fg === $arrow_bg) {
				$atts = $set($atts, 'arrows_bg', 'rgba(15,23,42,0.72)');
				$atts = $set($atts, 'arraow_color', '#ffffff');
			}

			if (strpos($atts, 'font-size:28px') === false) {
				if (preg_match('/ele_css="([^"]*)"/', $atts, $m_css)) {
					$existing_css = $m_css[1];
					$new_css      = $existing_css . $rule;
					$atts         = str_replace($m_css[0], 'ele_css="' . esc_attr($new_css) . '"', $atts);
				} else {
					$atts .= ' ele_css="' . esc_attr($rule) . '"';
				}
			}

			return '[' . $m[1] . $atts . ']';
		}, $text);
	}

	/**
	 * Render shortcode to live canvas HTML with data-attributes and Pagelayer editor structure
	 */
	public function render_shortcodes_for_canvas($shortcode, $post_id = 0) {
		global $post, $wp_query;

		// Set live mode flag so pagelayer_is_live() returns true and creates pagelayer-ele, pagelayer-tag, and data comments
		$_REQUEST['pagelayer-live'] = 1;
		if (!empty($post_id)) {
			$_REQUEST['postID'] = $post_id;
		}

		if (!empty($post_id)) {
			$post_obj = get_post($post_id);
			if (!empty($post_obj)) {
				$post = $post_obj;
				$GLOBALS['post'] = $post_obj;
				$GLOBALS['wp_query'] = new WP_Query(array(
					'post_type' => $post_obj->post_type,
					'post__in'  => array($post_id),
				));
			}
		}

		if (empty($GLOBALS['post'])) {
			$posts = get_posts(array('numberposts' => 1, 'post_status' => 'any'));
			if (!empty($posts)) {
				$post = $posts[0];
				$GLOBALS['post'] = $posts[0];
			}
		}

		if (function_exists('pagelayer_load_shortcodes')) {
			pagelayer_load_shortcodes();
		}

		// Ensure pagelayer_* shortcode aliases exist as well
		$this->register_pagelayer_aliases();

		if (function_exists('pagelayer_the_content')) {
			$rendered = pagelayer_the_content($shortcode, true);
			return $rendered;
		}

		return do_shortcode($shortcode);
	}

	/**
	 * Register pagelayer_* alias tags pointing to pl_* shortcodes
	 */
	private function register_pagelayer_aliases() {
		global $shortcode_tags;
		if (!is_array($shortcode_tags)) return;

		foreach ($shortcode_tags as $tag => $callback) {
			if (strpos($tag, 'pl_') !== 0) {
				continue;
			}
			$alias = 'pagelayer_' . substr($tag, 3);
			if (!isset($shortcode_tags[$alias])) {
				add_shortcode($alias, $callback);
			}
		}
	}

	/**
	 * Params for admin-ajax fallbacks (JSON body or $_POST).
	 */
	public function ajax_request_params() {
		$raw = file_get_contents('php://input');
		if (is_string($raw) && $raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded) && !empty($decoded)) {
				return $decoded;
			}
		}
		return is_array($_POST) ? $_POST : array();
	}

	/**
	 * Unwrap a REST callback result to an array or WP_Error.
	 */
	private function unwrap_rest_result($response) {
		if (is_wp_error($response)) {
			return $response;
		}
		if ($response instanceof WP_REST_Response) {
			$data = $response->get_data();
			$status = $response->get_status();
			if ($status >= 400) {
				$msg = is_array($data) && !empty($data['message']) ? $data['message'] : __('Request failed.', 'pagelayer');
				return new WP_Error('pagelayer_ai_error', $msg, array('status' => $status));
			}
			return $data;
		}
		return $response;
	}

	public function process_generate($params = null) {
		$request = new WP_REST_Request('POST', '/' . self::REST_NAMESPACE . '/ai/generate');
		$request->set_body_params(is_array($params) ? $params : $this->ajax_request_params());
		return $this->unwrap_rest_result($this->rest_generate($request));
	}

	public function process_get_settings() {
		$request = new WP_REST_Request('GET', '/' . self::REST_NAMESPACE . '/ai/settings');
		return $this->unwrap_rest_result($this->rest_get_settings($request));
	}

	public function process_save_settings($params = null) {
		$request = new WP_REST_Request('POST', '/' . self::REST_NAMESPACE . '/ai/settings');
		$request->set_body_params(is_array($params) ? $params : $this->ajax_request_params());
		return $this->unwrap_rest_result($this->rest_save_settings($request));
	}
}

// Instantiate
Pagelayer_AI_Controller::get_instance();
