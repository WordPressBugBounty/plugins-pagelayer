/**
 * Pagelayer AI Layout Generator Frontend Controller
 * Real-time Live Building & Direct Canvas Widget Assembly
 * (c) Pagelayer Team
 */

(function ($) {
	'use strict';

	var PagelayerAI = {
		panel: null,
		floatingBtn: null,
		topbarBtn: null,
		isGenerating: false,
		settings: {
			rest_url: (typeof pagelayer_ai_settings !== 'undefined' && pagelayer_ai_settings.rest_url) ? pagelayer_ai_settings.rest_url : '/wp-json/pagelayer/v1/ai/',
			nonce: (typeof pagelayer_ai_settings !== 'undefined' && pagelayer_ai_settings.nonce) ? pagelayer_ai_settings.nonce : '',
			ajax_url: (typeof pagelayer_ai_settings !== 'undefined' && pagelayer_ai_settings.ajax_url) ? pagelayer_ai_settings.ajax_url : (typeof pagelayer_ajax_url !== 'undefined' ? pagelayer_ajax_url : '/wp-admin/admin-ajax.php'),
			ajax_nonce: (typeof pagelayer_ai_settings !== 'undefined' && pagelayer_ai_settings.ajax_nonce) ? pagelayer_ai_settings.ajax_nonce : (typeof pagelayer_ajax_nonce !== 'undefined' ? pagelayer_ajax_nonce : ''),
			default_provider: 'gemini',
			default_model: 'gemini-3.7-flash',
			models: (typeof pagelayer_ai_settings !== 'undefined' && pagelayer_ai_settings.providers) ? pagelayer_ai_settings.providers : {}
		},
		models: {
			gemini: 'gemini-3.7-flash',
			deepseek: 'deepseek-chat',
			openrouter: 'deepseek/deepseek-chat',
			openai: 'gpt-4o-mini',
			anthropic: 'claude-3-7-sonnet-20250219',
			custom: 'llama3.3'
		},
		activeKeys: {
			gemini: false,
			deepseek: false,
			openai: false,
			anthropic: false,
			openrouter: false,
			custom: false
		},
		chatHistory: [],
		_lastAiHighlight: null,

		/**
		 * Initialize AI Assistant
		 */
		init: function () {
			var self = this;
			var targetDoc = (window.parent && window.parent.document) ? window.parent.document : document;

			// Build Panel UI
			self.buildUI(targetDoc);

			if (typeof pagelayer_ai_settings !== 'undefined' && pagelayer_ai_settings.user_settings) {
				var boot = pagelayer_ai_settings.user_settings;
				self.applyLoadedSettings({
					has_deepseek_key: !!boot.deepseek_key,
					has_gemini_key: !!boot.gemini_key,
					has_openrouter_key: !!boot.openrouter_key,
					has_openai_key: !!boot.openai_key,
					has_anthropic_key: !!boot.anthropic_key,
					has_custom_key: !!boot.custom_key,
					deepseek_model: boot.deepseek_model || '',
					gemini_model: boot.gemini_model || '',
					openrouter_model: boot.openrouter_model || '',
					openai_model: boot.openai_model || '',
					anthropic_model: boot.anthropic_model || '',
					custom_endpoint: boot.custom_endpoint || '',
					custom_model: boot.custom_model || '',
					default_provider: boot.default_provider || '',
					default_model: boot.default_model || ''
				});
			}

			// Load user AI settings
			self.loadSettings();

			// Bind event listeners
			self.bindEvents(targetDoc);

			// Inject Topbar Button
			self.injectTopbarButton(targetDoc);

			// Welcome message
			self.addBotMessage(
				"👋 Hello! I am your <strong>Pagelayer AI Architect</strong>. Describe any section, page hero, feature grid, or layout in natural language and watch it <strong>build live on your canvas step-by-step</strong> with fully editable widgets.<br><br>🎨 <em>Theme Mode: Set to keep your existing site colors & fonts by default. You can switch to generating new colors anytime in the Theme bar above.</em>",
				null,
				true
			);
		},

		/**
		 * Build Panel and Floating Trigger DOM
		 */
		buildUI: function (targetDoc) {
			var self = this;
			var $targetBody = $(targetDoc.body);
			var $targetHead = $(targetDoc.head || targetDoc.getElementsByTagName('head')[0])
			// Ensure AI CSS and critical topbar styles are loaded in the target document
			if ($targetHead.length) {
				if (!$targetHead.find('link[href*="pagelayer-ai.css"], link[href*="pagelayer-ai"]').length) {
					var cssUrl = (typeof pagelayer_ai_settings !== 'undefined' && pagelayer_ai_settings.css_url) ? pagelayer_ai_settings.css_url : ((typeof pagelayer_url !== 'undefined' ? pagelayer_url : '') + '/css/pagelayer-ai.css');
					if (cssUrl && cssUrl !== '/css/pagelayer-ai.css') {
						$targetHead.append('<link rel="stylesheet" id="pagelayer-ai-css" href="' + cssUrl + '" type="text/css" media="all" />');
					}
				}
				if (!$targetHead.find('#pagelayer-ai-critical-css').length) {
					var styleEl = targetDoc.createElement('style');
					styleEl.id = 'pagelayer-ai-critical-css';
					styleEl.textContent = '.pagelayer-header-right .pagelayer-ai-topbar-btn { cursor:pointer!important; display:inline-flex!important; align-items:center!important; justify-content:center!important; width:34px!important; height:34px!important; border-radius:6px!important; color:#c084fc!important; background:rgba(168,85,247,0.15)!important; border:1px solid rgba(168,85,247,0.3)!important; transition:all .2s cubic-bezier(0.4,0,0.2,1)!important; position:relative!important; margin-right:4px!important; box-sizing:border-box!important; font-size:14px!important; line-height:1!important; } .pagelayer-header-right .pagelayer-ai-topbar-btn:hover, .pagelayer-header-right .pagelayer-ai-topbar-btn.active { background:linear-gradient(135deg,#7c3aed,#4f46e5)!important; color:#fff!important; border-color:transparent!important; box-shadow:0 0 12px rgba(124,58,237,0.5)!important; transform:translateY(-1px)!important; } .pagelayer-header-right .pagelayer-ai-topbar-btn i { font-size:14px!important; line-height:1!important; pointer-events:none!important; color:inherit!important; }';
					$targetHead.append(styleEl);
				}
			}

			// Remove any existing instance
			$targetBody.find('.pagelayer-ai-chat-panel, .pagelayer-ai-floating-pill').remove();

			// Main Floating Panel
			var panelHtml = '<div class="pagelayer-ai-chat-panel pagelayer-ai-hidden" id="pagelayer-ai-panel">' +
				'<!-- Header -->' +
				'<div class="pagelayer-ai-header" id="pagelayer-ai-drag-handle">' +
				'<div class="pagelayer-ai-header-top">' +
				'<div class="pagelayer-ai-title-wrap">' +
				'<div class="pagelayer-ai-logo-icon"><i class="fas fa-magic"></i></div>' +
				'<div class="pagelayer-ai-title">Build with AI <span class="pagelayer-ai-badge">LIVE</span></div>' +
				'</div>' +
				'<div class="pagelayer-ai-header-actions">' +
				'<button type="button" class="pagelayer-ai-api-btn" id="pagelayer-ai-settings-toggle" title="Configure AI API Keys"><i class="fas fa-key"></i> <span>API Keys</span></button>' +
				'<button type="button" class="pagelayer-ai-icon-btn" id="pagelayer-ai-minimize-btn" title="Minimize"><i class="fas fa-minus"></i></button>' +
				'<button type="button" class="pagelayer-ai-icon-btn" id="pagelayer-ai-close-btn" title="Close"><i class="fas fa-times"></i></button>' +
				'</div>' +
				'</div>' +
				'<div class="pagelayer-ai-header-subbar">' +
				'<div class="pagelayer-ai-provider-pill-wrap">' +
				'<i class="fas fa-robot pagelayer-ai-subbar-icon"></i>' +
				'<select class="pagelayer-ai-provider-select" id="pagelayer-ai-provider-picker" title="Active AI Provider">' +
				'<option value="gemini">Google Gemini</option>' +
				'<option value="deepseek">DeepSeek</option>' +
				'<option value="openrouter">OpenRouter</option>' +
				'<option value="openai">OpenAI</option>' +
				'<option value="anthropic">Claude</option>' +
				'<option value="custom">Custom / Local</option>' +
				'</select>' +
				'<span class="pagelayer-ai-active-model-tag" id="pagelayer-ai-active-model-tag" title="Click to edit model">gemini-3.7-flash</span>' +
				'</div>' +
				'</div>' +
				'</div>' +

				'<!-- Settings Drawer -->' +
				'<div class="pagelayer-ai-settings-view" id="pagelayer-ai-settings-view">' +
				'<div class="pagelayer-ai-settings-header">' +
				'<div class="pagelayer-ai-settings-header-top">' +
				'<div class="pagelayer-ai-settings-title">' +
				'<i class="fas fa-key"></i>' +
				'<span>AI Providers & Models</span>' +
				'</div>' +
				'<button type="button" class="pagelayer-ai-back-btn" id="pagelayer-ai-settings-close" title="Back to Chat">' +
				'<i class="fas fa-arrow-left"></i> <span>Back</span>' +
				'</button>' +
				'</div>' +
				'<p class="pagelayer-ai-settings-desc">Add your API keys and enter any custom model name you prefer.</p>' +
				'</div>' +

				'<div class="pagelayer-ai-settings-scroll-body">' +
				'<div class="pagelayer-ai-settings-cards">' +

				'<!-- Google Gemini Card -->' +
				'<div class="pagelayer-ai-card" data-provider="gemini">' +
				'<div class="pagelayer-ai-card-header">' +
				'<div class="pagelayer-ai-card-title">' +
				'<span class="pagelayer-ai-provider-dot" id="dot-gemini"></span>' +
				'<strong>Google Gemini</strong>' +
				'</div>' +
				'<a href="https://aistudio.google.com/app/apikey" target="_blank" class="pagelayer-ai-key-link">Get Free Key <i class="fas fa-external-link-alt"></i></a>' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>API Key</label>' +
				'<div class="pagelayer-ai-input-with-eye">' +
				'<input type="password" class="pagelayer-ai-input" id="pagelayer-ai-gemini-key" placeholder="Enter Gemini API key (AIzaSy...)" />' +
				'<button type="button" class="pagelayer-ai-eye-btn" title="Show/Hide Key"><i class="fas fa-eye"></i></button>' +
				'</div>' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>Model Name <span class="pagelayer-ai-field-hint">e.g. gemini-3.7-flash, gemini-1.5-flash, gemini-1.5-pro</span></label>' +
				'<input type="text" class="pagelayer-ai-input" id="pagelayer-ai-gemini-model" placeholder="gemini-3.7-flash" />' +
				'</div>' +
				'</div>' +

				'<!-- DeepSeek Official Card -->' +
				'<div class="pagelayer-ai-card" data-provider="deepseek">' +
				'<div class="pagelayer-ai-card-header">' +
				'<div class="pagelayer-ai-card-title">' +
				'<span class="pagelayer-ai-provider-dot" id="dot-deepseek"></span>' +
				'<strong>DeepSeek (Official)</strong>' +
				'</div>' +
				'<a href="https://platform.deepseek.com/api_keys" target="_blank" class="pagelayer-ai-key-link">Get Key <i class="fas fa-external-link-alt"></i></a>' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>API Key</label>' +
				'<div class="pagelayer-ai-input-with-eye">' +
				'<input type="password" class="pagelayer-ai-input" id="pagelayer-ai-deepseek-key" placeholder="Enter DeepSeek API key (sk-...)" />' +
				'<button type="button" class="pagelayer-ai-eye-btn" title="Show/Hide Key"><i class="fas fa-eye"></i></button>' +
				'</div>' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>Model Name <span class="pagelayer-ai-field-hint">e.g. deepseek-chat, deepseek-reasoner</span></label>' +
				'<input type="text" class="pagelayer-ai-input" id="pagelayer-ai-deepseek-model" placeholder="deepseek-chat" />' +
				'</div>' +
				'</div>' +

				'<!-- OpenRouter Card -->' +
				'<div class="pagelayer-ai-card" data-provider="openrouter">' +
				'<div class="pagelayer-ai-card-header">' +
				'<div class="pagelayer-ai-card-title">' +
				'<span class="pagelayer-ai-provider-dot" id="dot-openrouter"></span>' +
				'<strong>OpenRouter (All Open Models)</strong>' +
				'</div>' +
				'<a href="https://openrouter.ai/keys" target="_blank" class="pagelayer-ai-key-link">Get Key <i class="fas fa-external-link-alt"></i></a>' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>API Key</label>' +
				'<div class="pagelayer-ai-input-with-eye">' +
				'<input type="password" class="pagelayer-ai-input" id="pagelayer-ai-openrouter-key" placeholder="Enter OpenRouter API key (sk-or-...)" />' +
				'<button type="button" class="pagelayer-ai-eye-btn" title="Show/Hide Key"><i class="fas fa-eye"></i></button>' +
				'</div>' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>Model Name <span class="pagelayer-ai-field-hint">e.g. deepseek/deepseek-chat, google/gemini-3.7-flash-001, meta-llama/llama-3.3-70b-instruct</span></label>' +
				'<input type="text" class="pagelayer-ai-input" id="pagelayer-ai-openrouter-model" placeholder="deepseek/deepseek-chat" />' +
				'</div>' +
				'</div>' +

				'<!-- OpenAI Card -->' +
				'<div class="pagelayer-ai-card" data-provider="openai">' +
				'<div class="pagelayer-ai-card-header">' +
				'<div class="pagelayer-ai-card-title">' +
				'<span class="pagelayer-ai-provider-dot" id="dot-openai"></span>' +
				'<strong>OpenAI</strong>' +
				'</div>' +
				'<a href="https://platform.openai.com/api-keys" target="_blank" class="pagelayer-ai-key-link">Get Key <i class="fas fa-external-link-alt"></i></a>' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>API Key</label>' +
				'<div class="pagelayer-ai-input-with-eye">' +
				'<input type="password" class="pagelayer-ai-input" id="pagelayer-ai-openai-key" placeholder="Enter OpenAI API key (sk-...)" />' +
				'<button type="button" class="pagelayer-ai-eye-btn" title="Show/Hide Key"><i class="fas fa-eye"></i></button>' +
				'</div>' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>Model Name <span class="pagelayer-ai-field-hint">e.g. gpt-4o-mini, gpt-4o, o3-mini</span></label>' +
				'<input type="text" class="pagelayer-ai-input" id="pagelayer-ai-openai-model" placeholder="gpt-4o-mini" />' +
				'</div>' +
				'</div>' +

				'<!-- Anthropic Card -->' +
				'<div class="pagelayer-ai-card" data-provider="anthropic">' +
				'<div class="pagelayer-ai-card-header">' +
				'<div class="pagelayer-ai-card-title">' +
				'<span class="pagelayer-ai-provider-dot" id="dot-anthropic"></span>' +
				'<strong>Anthropic Claude</strong>' +
				'</div>' +
				'<a href="https://console.anthropic.com/settings/keys" target="_blank" class="pagelayer-ai-key-link">Get Key <i class="fas fa-external-link-alt"></i></a>' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>API Key</label>' +
				'<div class="pagelayer-ai-input-with-eye">' +
				'<input type="password" class="pagelayer-ai-input" id="pagelayer-ai-anthropic-key" placeholder="Enter Claude API key (sk-ant-...)" />' +
				'<button type="button" class="pagelayer-ai-eye-btn" title="Show/Hide Key"><i class="fas fa-eye"></i></button>' +
				'</div>' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>Model Name <span class="pagelayer-ai-field-hint">e.g. claude-3-7-sonnet-20250219, claude-3-5-sonnet-20241022, claude-3-5-haiku-20241022</span></label>' +
				'<input type="text" class="pagelayer-ai-input" id="pagelayer-ai-anthropic-model" placeholder="claude-3-7-sonnet-20250219" />' +
				'</div>' +
				'</div>' +

				'<!-- Custom / Local Endpoint Card -->' +
				'<div class="pagelayer-ai-card" data-provider="custom">' +
				'<div class="pagelayer-ai-card-header">' +
				'<div class="pagelayer-ai-card-title">' +
				'<span class="pagelayer-ai-provider-dot" id="dot-custom"></span>' +
				'<strong>Custom / Local (OpenAI-Compatible)</strong>' +
				'</div>' +
				'<span class="pagelayer-ai-badge-sm">Ollama / LM Studio / vLLM</span>' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>Endpoint URL</label>' +
				'<input type="text" class="pagelayer-ai-input" id="pagelayer-ai-custom-endpoint" placeholder="e.g. http://localhost:11434/v1 or https://api.deepseek.com/v1" />' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>Custom Model Name <span class="pagelayer-ai-field-hint">e.g. llama3.3, qwen2.5-coder, deepseek-r1, mistral</span></label>' +
				'<input type="text" class="pagelayer-ai-input" id="pagelayer-ai-custom-model" placeholder="llama3.3" />' +
				'</div>' +
				'<div class="pagelayer-ai-field">' +
				'<label>API Key (Optional for local servers)</label>' +
				'<div class="pagelayer-ai-input-with-eye">' +
				'<input type="password" class="pagelayer-ai-input" id="pagelayer-ai-custom-key" placeholder="Enter API key if required" />' +
				'<button type="button" class="pagelayer-ai-eye-btn" title="Show/Hide Key"><i class="fas fa-eye"></i></button>' +
				'</div>' +
				'</div>' +
				'</div>' +

				'</div>' +
				'</div>' +

				'<div class="pagelayer-ai-settings-footer-sticky">' +
				'<button type="button" class="pagelayer-ai-save-settings-btn" id="pagelayer-ai-save-keys-btn">' +
				'<i class="fas fa-save"></i> Save AI Settings' +
				'</button>' +
				'</div>' +
				'</div>' +

				'<!-- Chat Messages Container -->' +
				'<div class="pagelayer-ai-messages" id="pagelayer-ai-messages-box"></div>' +

				'<!-- Footer & Input -->' +
				'<div class="pagelayer-ai-footer">' +
				'<div class="pagelayer-ai-input-row">' +
				'<textarea class="pagelayer-ai-textarea" id="pagelayer-ai-prompt-input" rows="2" placeholder="Describe section or layout (e.g. 3-column features with icons)..."></textarea>' +
				'<button type="button" class="pagelayer-ai-submit-btn" id="pagelayer-ai-send-btn" title="Generate Layout (Enter)">' +
				'<i class="fas fa-arrow-up"></i>' +
				'</button>' +
				'</div>' +
				'<div class="pagelayer-ai-footer-options">' +
				'<span>Insert at: ' +
				'<select class="pagelayer-ai-insert-select" id="pagelayer-ai-target-position">' +
				'<option value="end">Bottom of Canvas</option>' +
				'<option value="start">Top of Canvas</option>' +
				'<option value="selection">At Selected Element</option>' +
				'</select>' +
				'</span>' +
				'<span>Shift+Enter for newline</span>' +
				'</div>' +
				'</div>' +
				'</div>';

			self.panel = $(panelHtml).appendTo($targetBody);
		},

		/**
		 * Inject "Build with AI" button in editor top bar
		 */
		injectTopbarButton: function (targetDoc) {
			var self = this;
			var checkInterval = setInterval(function () {
				var $headerRight = $(targetDoc).find('.pagelayer-top-header-bar .pagelayer-header-right');
				if ($headerRight.length) {
					// Always remove any previous button to ensure clean icon only (no text inside)
					$headerRight.find('.pagelayer-ai-topbar-btn').remove();

					// Strict icon-only button without ANY text node
					var $btn = $('<span class="pagelayer-header-icon-wrap pagelayer-ai-topbar-btn" title="Build with AI" data-tlite="Build with AI"><i class="fas fa-magic"></i></span>');
					$btn.on('click', function (e) {
						e.preventDefault();
						e.stopPropagation();
						self.togglePanel();
					});

					var $divider = $headerRight.find('.pagelayer-header-divider');
					if ($divider.length) {
						$divider.before($btn);
					} else {
						$headerRight.prepend($btn);
					}
					self.topbarBtn = $btn;
					clearInterval(checkInterval);
				}
			}, 300);
		},

		/**
		 * Bind all user interactions and drag behaviors
		 */
		bindEvents: function (targetDoc) {
			var self = this;
			var $panel = self.panel;

			// Close & Minimize buttons
			$panel.find('#pagelayer-ai-close-btn').on('click', function () {
				self.hidePanel();
			});

			$panel.find('#pagelayer-ai-minimize-btn').on('click', function () {
				$panel.toggleClass('pagelayer-ai-minimized');
				var isMin = $panel.hasClass('pagelayer-ai-minimized');
				$(this).find('i').attr('class', isMin ? 'fas fa-chevron-up' : 'fas fa-minus');
			});

			// Settings Toggle (delegated for triggers like error bubble button)
			$panel.on('click', '#pagelayer-ai-settings-toggle, .pagelayer-ai-open-settings-trigger, #pagelayer-ai-active-model-tag', function (e) {
				e.preventDefault();
				var $view = $panel.find('#pagelayer-ai-settings-view');
				$view.addClass('active');
				var curProvider = $panel.find('#pagelayer-ai-provider-picker').val() || 'deepseek';
				var $card = $panel.find('.pagelayer-ai-card[data-provider="' + curProvider + '"]');
				if ($card.length) {
					var $body = $panel.find('#pagelayer-ai-settings-body');
					if ($body.length) {
						var cardPos = $card.position();
						if (cardPos) {
							$body.animate({ scrollTop: cardPos.top + $body.scrollTop() - 12 }, 200);
						}
					}
					setTimeout(function () {
						$card.find('#pagelayer-ai-' + curProvider + '-key').focus();
					}, 220);
				}
			});

			$panel.on('click', '#pagelayer-ai-settings-close', function (e) {
				e.preventDefault();
				$panel.find('#pagelayer-ai-settings-view').removeClass('active');
			});

			// Provider switch in header
			$panel.find('#pagelayer-ai-provider-picker').on('change', function () {
				self.updateActiveModelBadge();
			});

			// Eye password toggle
			$panel.on('click', '.pagelayer-ai-eye-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var $inp = $btn.siblings('input');
				if ($inp.attr('type') === 'password') {
					$inp.attr('type', 'text');
					$btn.find('i').attr('class', 'fas fa-eye-slash');
				} else {
					$inp.attr('type', 'password');
					$btn.find('i').attr('class', 'fas fa-eye');
				}
			});

			// Live input updates for model tags
			$panel.find('#pagelayer-ai-gemini-model, #pagelayer-ai-deepseek-model, #pagelayer-ai-openrouter-model, #pagelayer-ai-openai-model, #pagelayer-ai-anthropic-model, #pagelayer-ai-custom-model').on('input', function () {
				self.updateActiveModelBadge();
			});

			// Save Settings
			$panel.find('#pagelayer-ai-save-keys-btn').on('click', function () {
				self.saveSettings();
			});

			// Autosize textarea & Submit on Enter (delegated for reliability)
			$panel.on('input', '#pagelayer-ai-prompt-input', function () {
				this.style.height = 'auto';
				var h = Math.max(50, Math.min(this.scrollHeight, 120));
				this.style.height = h + 'px';
				this.style.overflowY = (this.scrollHeight > 120) ? 'auto' : 'hidden';
			});

			$panel.on('keydown', '#pagelayer-ai-prompt-input', function (e) {
				if (e.which === 13 || e.keyCode === 13 || e.key === 'Enter') {
					if (!e.shiftKey) {
						e.preventDefault();
						e.stopPropagation();
						self.handlePromptSubmit();
					}
				}
			});

			$panel.on('click', '#pagelayer-ai-send-btn', function (e) {
				e.preventDefault();
				e.stopPropagation();
				self.handlePromptSubmit();
			});

			// Keyboard shortcut: Ctrl+Shift+A / Cmd+Shift+A
			$(window.parent.document).add(document).on('keydown', function (e) {
				if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'A' || e.key === 'a')) {
					e.preventDefault();
					self.togglePanel();
				}
			});

			// Canvas Add Section AI button trigger
			$(targetDoc).add(document).on('click', '.pagelayer-add-circle-ai, .pagelayer-ai-canvas-btn, .pagelayer-ai-trigger-btn', function (e) {
				e.preventDefault();
				e.stopPropagation();
				self.showPanel();
			});

			// Drag functionality
			self.initDraggable(targetDoc);
		},

		/**
		 * Floating Draggable behavior
		 */
		initDraggable: function (targetDoc) {
			var self = this;
			var $panel = self.panel;
			var $handle = $panel.find('#pagelayer-ai-drag-handle');
			var isDragging = false;
			var startX, startY, initialLeft, initialTop;

			$handle.on('mousedown', function (e) {
				if ($(e.target).closest('button, select, input, .pagelayer-ai-provider-pill-wrap').length) return;
				isDragging = true;
				var offset = $panel.offset();
				startX = e.clientX;
				startY = e.clientY;
				initialLeft = offset.left;
				initialTop = offset.top;

				$panel.css({
					bottom: 'auto',
					right: 'auto',
					left: initialLeft + 'px',
					top: initialTop + 'px'
				});

				$(targetDoc).on('mousemove.plAiDrag', function (ev) {
					if (!isDragging) return;
					var dx = ev.clientX - startX;
					var dy = ev.clientY - startY;
					var newLeft = Math.max(10, Math.min(targetDoc.documentElement.clientWidth - $panel.outerWidth() - 10, initialLeft + dx));
					var newTop = Math.max(10, Math.min(targetDoc.documentElement.clientHeight - $panel.outerHeight() - 10, initialTop + dy));
					$panel.css({ left: newLeft + 'px', top: newTop + 'px' });
				});

				$(targetDoc).on('mouseup.plAiDrag', function () {
					isDragging = false;
					$(targetDoc).off('.plAiDrag');
				});
			});
		},

		togglePanel: function () {
			var self = this;
			if (self.panel.hasClass('pagelayer-ai-hidden')) {
				self.showPanel();
			} else {
				self.hidePanel();
			}
		},

		showPanel: function () {
			this.panel.removeClass('pagelayer-ai-hidden');
			if (this.floatingBtn) this.floatingBtn.hide();
			this.panel.find('#pagelayer-ai-prompt-input').focus();
		},

		hidePanel: function () {
			this.panel.addClass('pagelayer-ai-hidden');
			if (this.floatingBtn) this.floatingBtn.show();
		},

		/**
		 * Update status indicator dots on each provider card
		 */
		updateStatusBadges: function () {
			var self = this;
			var setDot = function (id, isConnected) {
				var $dot = $('#dot-' + id, self.panel);
				if (isConnected) {
					$dot.addClass('connected').attr('title', 'Connected');
				} else {
					$dot.removeClass('connected').attr('title', 'Not Configured');
				}
			};
			setDot('deepseek', self.activeKeys.deepseek);
			setDot('gemini', self.activeKeys.gemini);
			setDot('openrouter', self.activeKeys.openrouter);
			setDot('openai', self.activeKeys.openai);
			setDot('anthropic', self.activeKeys.anthropic);
			setDot('custom', self.activeKeys.custom);
		},

		/**
		 * Update the active model pill displayed in the header
		 */
		updateActiveModelBadge: function () {
			var self = this;
			var provider = $('#pagelayer-ai-provider-picker', self.panel).val() || self.settings.default_provider || 'gemini';
			var model = '';
			if (provider === 'deepseek') {
				model = $('#pagelayer-ai-deepseek-model', self.panel).val() || self.models.deepseek || 'deepseek-chat';
			} else if (provider === 'gemini') {
				model = $('#pagelayer-ai-gemini-model', self.panel).val() || self.models.gemini || 'gemini-3.7-flash';
			} else if (provider === 'openrouter') {
				model = $('#pagelayer-ai-openrouter-model', self.panel).val() || self.models.openrouter || 'deepseek/deepseek-chat';
			} else if (provider === 'openai') {
				model = $('#pagelayer-ai-openai-model', self.panel).val() || self.models.openai || 'gpt-4o-mini';
			} else if (provider === 'anthropic') {
				model = $('#pagelayer-ai-anthropic-model', self.panel).val() || self.models.anthropic || 'claude-3-7-sonnet-20250219';
			} else if (provider === 'custom') {
				model = $('#pagelayer-ai-custom-model', self.panel).val() || self.models.custom || 'llama3.3';
			}
			var shortModel = model;
			if (shortModel.indexOf('/') !== -1) {
				shortModel = shortModel.split('/').pop();
			}
			$('#pagelayer-ai-active-model-tag', self.panel).text(shortModel).attr('title', 'Active Model: ' + model + ' (Click to configure)');
		},

		/**
		 * Write Pagelayer global colors/fonts into live CSS variables so
		 * $primary / $text tokens in the new widgets resolve immediately.
		 */
		applySiteGlobals: function (globals) {
			if (!globals) return;
			var colors = globals.colors || globals.global_colors;
			var fonts = globals.fonts || globals.global_fonts;
			var docs = [document];
			try {
				if (window.parent && window.parent.document && window.parent.document !== document) {
					docs.push(window.parent.document);
				}
			} catch (e) { }
			if (typeof pagelayer !== 'undefined') {
				if (pagelayer.gDocument) docs.push(pagelayer.gDocument);
				if (pagelayer.$ && pagelayer.$[0] && pagelayer.$[0].ownerDocument) {
					docs.push(pagelayer.$[0].ownerDocument);
				}
			}

			var seen = [];
			docs.forEach(function (doc) {
				if (!doc || !doc.documentElement || seen.indexOf(doc) !== -1) return;
				seen.push(doc);
				var root = doc.documentElement;
				if (colors && typeof colors === 'object') {
					Object.keys(colors).forEach(function (k) {
						var entry = colors[k];
						var v = (entry && typeof entry === 'object' && entry.value) ? entry.value : entry;
						if (typeof v === 'string' && v) {
							root.style.setProperty('--pagelayer-color-' + k, v);
						}
					});
				}
				if (fonts && typeof fonts === 'object') {
					Object.keys(fonts).forEach(function (k) {
						var val = fonts[k] && fonts[k].value ? fonts[k].value : null;
						if (!val || typeof val !== 'object') return;
						Object.keys(val).forEach(function (fk) {
							var fv = val[fk];
							if (!fv || typeof fv !== 'string') return;
							var unit = (fk === 'font-size' || fk === 'letter-spacing' || fk === 'word-spacing') ? 'px' : '';
							root.style.setProperty('--pagelayer-font-' + k + '-' + fk, fv + unit);
						});
					});
				}
			});

			if (colors) {
				if (typeof pagelayer_global_colors !== 'undefined') pagelayer_global_colors = colors;
				try { if (window.parent && typeof window.parent.pagelayer_global_colors !== 'undefined') window.parent.pagelayer_global_colors = colors; } catch (e2) { }
			}
			if (fonts) {
				if (typeof pagelayer_global_fonts !== 'undefined') pagelayer_global_fonts = fonts;
				try { if (window.parent && typeof window.parent.pagelayer_global_fonts !== 'undefined') window.parent.pagelayer_global_fonts = fonts; } catch (e3) { }
			}
		},

		/**
		 * Drop the live-build outline and Pagelayer "selected" state so the
		 * finished layout does not look like every widget is active.
		 */
		ensureCanvasHighlightStyle: function ($canvas) {
			var doc = document;
			try {
				if ($canvas && $canvas[0] && $canvas[0].ownerDocument) {
					doc = $canvas[0].ownerDocument;
				}
			} catch (e) { }
			if (!doc) {
				return;
			}
			var uniformCss = '.pagelayer-ai-just-inserted{outline:3px solid #6366f1;outline-offset:2px;box-shadow:0 0 24px rgba(99,102,241,.3);}' +
				'.pagelayer-service .pagelayer-service-image,.pagelayer-service-image{width:100%!important;overflow:hidden!important;margin:0 auto 16px auto!important;border-radius:8px!important;}' +
				'.pagelayer-service .pagelayer-service-image img,.pagelayer-service img.pagelayer-img,.pagelayer-service-image img{width:100%!important;height:220px!important;max-height:220px!important;min-height:220px!important;object-fit:cover!important;object-position:center!important;border-radius:8px!important;display:block!important;margin:0 auto!important;}' +
				'.pagelayer-service-container{display:flex!important;flex-direction:column!important;height:100%!important;}' +
				'.pagelayer-service-details{display:flex!important;flex-direction:column!important;flex-grow:1!important;}' +
				'.pagelayer-service-text{flex-grow:1!important;margin-bottom:16px!important;}' +
				'.pagelayer-service-btn{margin-top:auto!important;align-self:center!important;}' +
				'.pagelayer-iconbox .pagelayer-service-icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:64px!important;height:64px!important;min-width:64px!important;min-height:64px!important;margin:0 auto 16px auto!important;line-height:1!important;}' +
				'.pagelayer-image_slider,.pagelayer-image-slider-div{max-width:1000px!important;margin:0 auto!important;overflow:hidden!important;border-radius:12px!important;}' +
				'.pagelayer-image-slider-ul{margin:0 auto!important;}' +
				'.pagelayer-slider-item{overflow:hidden!important;border-radius:12px!important;}' +
				'.pagelayer-image-slider-div img,.pagelayer-image_slider img,.pagelayer-slider-item img,.pagelayer-image-slider-ul img{width:100%!important;height:420px!important;max-height:440px!important;min-height:280px!important;object-fit:cover!important;object-position:center!important;border-radius:12px!important;display:block!important;margin:0 auto!important;}' +
				'.pagelayer-row:not(.pagelayer-row-holder)>.pagelayer-row-holder{display:flex!important;flex-wrap:wrap!important;}' +
				'.pagelayer-col{display:flex!important;flex-direction:column!important;}' +
				'.pagelayer-col>.pagelayer-col-holder{display:flex!important;flex-direction:column!important;flex-grow:1!important;height:100%!important;}' +
				'.pagelayer-col-holder>.pagelayer-ele-wrap{flex-grow:1!important;display:flex!important;flex-direction:column!important;}' +
				'.pagelayer-col-holder>.pagelayer-ele-wrap>.pagelayer-ele{flex-grow:1!important;height:100%!important;}';
			var existing = doc.getElementById('pagelayer-ai-canvas-style');
			if (existing) {
				if (existing.textContent.indexOf('.pagelayer-image_slider') === -1) {
					existing.appendChild(doc.createTextNode(uniformCss));
				}
				return;
			}
			var style = doc.createElement('style');
			style.id = 'pagelayer-ai-canvas-style';
			style.appendChild(doc.createTextNode(uniformCss));
			(doc.head || doc.documentElement).appendChild(style);
		},

		clearLiveBuildHighlight: function ($canvas, $fullNodes) {
			var $scope = $fullNodes && $fullNodes.length ? $fullNodes : $canvas('body');
			$scope.find('.pagelayer-ai-just-inserted').addBack('.pagelayer-ai-just-inserted').removeClass('pagelayer-ai-just-inserted');
			$canvas('.pagelayer-ai-just-inserted').removeClass('pagelayer-ai-just-inserted');
			this._lastAiHighlight = null;

			$scope.find('[pagelayer-active]').addBack('[pagelayer-active]').removeAttr('pagelayer-active');
			$scope.find('.pagelayer-active').addBack('.pagelayer-active').removeClass('pagelayer-active pagelayer-ele-hover pagelayer-drag-ele-hover');
			$scope.find('.pagelayer-ele-overlay').removeClass('pagelayer-active pagelayer-ele-hover pagelayer-drag-ele-hover');
		},

		/**
		 * Load user API settings from REST / AJAX
		 */
		loadSettings: function () {
			var self = this;
			var url = self.settings.rest_url + 'settings';

			$.ajax({
				url: url,
				type: 'GET',
				headers: { 'X-WP-Nonce': self.settings.nonce },
				success: function (resp) {
					if (resp && resp.success) {
						self.applyLoadedSettings(resp);
					}
				},
				error: function () {
					// Fallback to AJAX
					$.ajax({
						url: self.settings.ajax_url + (self.settings.ajax_url.indexOf('?') === -1 ? '?' : '&') + 'action=pagelayer_ai_settings',
						type: 'POST',
						data: { pagelayer_nonce: self.settings.ajax_nonce },
						success: function (r) {
							if (r && !r.error && (r.success || r.default_provider || r.has_gemini_key !== undefined)) {
								self.applyLoadedSettings(r.data || r);
							}
						}
					});
				}
			});
		},

		applyLoadedSettings: function (resp) {
			var self = this;
			self.activeKeys.deepseek = !!resp.has_deepseek_key;
			self.activeKeys.gemini = !!resp.has_gemini_key;
			self.activeKeys.openrouter = !!resp.has_openrouter_key;
			self.activeKeys.openai = !!resp.has_openai_key;
			self.activeKeys.anthropic = !!resp.has_anthropic_key;
			self.activeKeys.custom = !!(resp.custom_endpoint || resp.has_custom_key);

			if (resp.deepseek_model) { self.models.deepseek = resp.deepseek_model; $('#pagelayer-ai-deepseek-model', self.panel).val(resp.deepseek_model); }
			if (resp.gemini_model) { self.models.gemini = resp.gemini_model; $('#pagelayer-ai-gemini-model', self.panel).val(resp.gemini_model); }
			if (resp.openrouter_model) { self.models.openrouter = resp.openrouter_model; $('#pagelayer-ai-openrouter-model', self.panel).val(resp.openrouter_model); }
			if (resp.openai_model) { self.models.openai = resp.openai_model; $('#pagelayer-ai-openai-model', self.panel).val(resp.openai_model); }
			if (resp.anthropic_model) { self.models.anthropic = resp.anthropic_model; $('#pagelayer-ai-anthropic-model', self.panel).val(resp.anthropic_model); }
			if (resp.custom_model) { self.models.custom = resp.custom_model; $('#pagelayer-ai-custom-model', self.panel).val(resp.custom_model); }

			if (resp.deepseek_masked) $('#pagelayer-ai-deepseek-key', self.panel).attr('placeholder', 'Saved: ' + resp.deepseek_masked);
			if (resp.openrouter_masked) $('#pagelayer-ai-openrouter-key', self.panel).attr('placeholder', 'Saved: ' + resp.openrouter_masked);
			if (resp.gemini_masked) $('#pagelayer-ai-gemini-key', self.panel).attr('placeholder', 'Saved: ' + resp.gemini_masked);
			if (resp.openai_masked) $('#pagelayer-ai-openai-key', self.panel).attr('placeholder', 'Saved: ' + resp.openai_masked);
			if (resp.anthropic_masked) $('#pagelayer-ai-anthropic-key', self.panel).attr('placeholder', 'Saved: ' + resp.anthropic_masked);
			if (resp.custom_endpoint) $('#pagelayer-ai-custom-endpoint', self.panel).val(resp.custom_endpoint);
			if (resp.custom_masked) $('#pagelayer-ai-custom-key', self.panel).attr('placeholder', 'Saved: ' + resp.custom_masked);

			self.updateStatusBadges();

			var provider = resp.default_provider || '';
			var connected = {
				deepseek: self.activeKeys.deepseek,
				custom: self.activeKeys.custom,
				openrouter: self.activeKeys.openrouter,
				openai: self.activeKeys.openai,
				anthropic: self.activeKeys.anthropic,
				gemini: self.activeKeys.gemini
			};
			if (!provider || !connected[provider]) {
				var order = ['deepseek', 'custom', 'openrouter', 'openai', 'anthropic', 'gemini'];
				provider = '';
				for (var i = 0; i < order.length; i++) {
					if (connected[order[i]]) {
						provider = order[i];
						break;
					}
				}
			}
			if (!provider) provider = 'gemini';

			var $picker = $('#pagelayer-ai-provider-picker', self.panel);
			$picker.val(provider);
			self.settings.default_provider = provider;

			self.updateActiveModelBadge();
		},

		/**
		 * Save user API keys and models
		 */
		saveSettings: function () {
			var self = this;
			var $panel = self.panel;
			var deepseek = $('#pagelayer-ai-deepseek-key', $panel).val();
			var deepseekModel = $('#pagelayer-ai-deepseek-model', $panel).val();
			var openrouter = $('#pagelayer-ai-openrouter-key', $panel).val();
			var openrouterModel = $('#pagelayer-ai-openrouter-model', $panel).val();
			var gemini = $('#pagelayer-ai-gemini-key', $panel).val();
			var geminiModel = $('#pagelayer-ai-gemini-model', $panel).val();
			var openai = $('#pagelayer-ai-openai-key', $panel).val();
			var openaiModel = $('#pagelayer-ai-openai-model', $panel).val();
			var anthropic = $('#pagelayer-ai-anthropic-key', $panel).val();
			var anthropicModel = $('#pagelayer-ai-anthropic-model', $panel).val();
			var customEndpoint = $('#pagelayer-ai-custom-endpoint', $panel).val();
			var customModel = $('#pagelayer-ai-custom-model', $panel).val();
			var customKey = $('#pagelayer-ai-custom-key', $panel).val();
			var activeProvider = $('#pagelayer-ai-provider-picker', $panel).val() || 'gemini';

			var data = {
				default_provider: activeProvider
			};

			if (deepseek) data.deepseek_key = deepseek;
			if (deepseekModel !== undefined) data.deepseek_model = deepseekModel;
			if (openrouter) data.openrouter_key = openrouter;
			if (openrouterModel !== undefined) data.openrouter_model = openrouterModel;
			if (gemini) data.gemini_key = gemini;
			if (geminiModel !== undefined) data.gemini_model = geminiModel;
			if (openai) data.openai_key = openai;
			if (openaiModel !== undefined) data.openai_model = openaiModel;
			if (anthropic) data.anthropic_key = anthropic;
			if (anthropicModel !== undefined) data.anthropic_model = anthropicModel;
			if (customEndpoint !== undefined) data.custom_endpoint = customEndpoint;
			if (customModel !== undefined) data.custom_model = customModel;
			if (customKey) data.custom_key = customKey;

			// Determine default model based on active provider
			if (activeProvider === 'deepseek') data.default_model = deepseekModel || self.models.deepseek;
			else if (activeProvider === 'gemini') data.default_model = geminiModel || self.models.gemini;
			else if (activeProvider === 'openrouter') data.default_model = openrouterModel || self.models.openrouter;
			else if (activeProvider === 'openai') data.default_model = openaiModel || self.models.openai;
			else if (activeProvider === 'anthropic') data.default_model = anthropicModel || self.models.anthropic;
			else if (activeProvider === 'custom') data.default_model = customModel || self.models.custom;

			// Custom/OpenCode is connected by endpoint; don't keep Gemini selected
			// just because the dropdown still had it as the HTML default.
			if (customEndpoint && !gemini && !self.activeKeys.gemini && !deepseek && !self.activeKeys.deepseek && data.default_provider === 'gemini') {
				data.default_provider = 'custom';
				data.default_model = customModel || 'llama3.3';
				$('#pagelayer-ai-provider-picker', $panel).val('custom');
			}

			var $btn = $('#pagelayer-ai-save-keys-btn', $panel).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

			$.ajax({
				url: self.settings.rest_url + 'settings',
				type: 'POST',
				headers: { 'X-WP-Nonce': self.settings.nonce },
				contentType: 'application/json',
				data: JSON.stringify(data),
				success: function (resp) {
					$btn.html('<i class="fas fa-check"></i> Settings Saved!');
					setTimeout(function () {
						$btn.html('<i class="fas fa-save"></i> Save AI Settings');
						$panel.find('#pagelayer-ai-settings-view').removeClass('active');
					}, 1200);
					self.loadSettings();
				},
				error: function (xhr) {
					var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error saving settings';
					alert(msg);
					$btn.html('<i class="fas fa-save"></i> Save AI Settings');
				}
			});
		},

		/**
		 * Handle Prompt Submission - Prompt user inside chat for style mode
		 */
		handlePromptSubmit: function () {
			var self = this;
			if (self.isGenerating) return;

			var $input = self.panel.find('#pagelayer-ai-prompt-input');
			var prompt = $.trim($input.val());
			if (!prompt) return;

			var provider = self.panel.find('#pagelayer-ai-provider-picker').val() || self.settings.default_provider || 'deepseek';
			var model = '';
			if (provider === 'deepseek') {
				model = $('#pagelayer-ai-deepseek-model', self.panel).val() || self.models.deepseek || 'deepseek-chat';
			} else if (provider === 'gemini') {
				model = $('#pagelayer-ai-gemini-model', self.panel).val() || self.models.gemini || 'gemini-3.7-flash';
			} else if (provider === 'openrouter') {
				model = $('#pagelayer-ai-openrouter-model', self.panel).val() || self.models.openrouter || 'deepseek/deepseek-chat';
			} else if (provider === 'openai') {
				model = $('#pagelayer-ai-openai-model', self.panel).val() || self.models.openai || 'gpt-4o-mini';
			} else if (provider === 'anthropic') {
				model = $('#pagelayer-ai-anthropic-model', self.panel).val() || self.models.anthropic || 'claude-3-7-sonnet-20250219';
			} else if (provider === 'custom') {
				model = $('#pagelayer-ai-custom-model', self.panel).val() || self.models.custom || 'llama3.3';
			}

			if (provider === 'gemini' && !self.activeKeys.gemini) {
				if (self.activeKeys.deepseek) {
					provider = 'deepseek';
					model = $('#pagelayer-ai-deepseek-model', self.panel).val() || self.models.deepseek || 'deepseek-chat';
					self.panel.find('#pagelayer-ai-provider-picker').val('deepseek');
				} else if (self.activeKeys.openrouter) {
					provider = 'openrouter';
					model = $('#pagelayer-ai-openrouter-model', self.panel).val() || self.models.openrouter || 'deepseek/deepseek-chat';
					self.panel.find('#pagelayer-ai-provider-picker').val('openrouter');
				} else if (self.activeKeys.custom) {
					provider = 'custom';
					model = $('#pagelayer-ai-custom-model', self.panel).val() || self.models.custom || 'llama3.3';
					self.panel.find('#pagelayer-ai-provider-picker').val('custom');
				} else if (self.activeKeys.openai) {
					provider = 'openai';
					model = $('#pagelayer-ai-openai-model', self.panel).val() || self.models.openai || 'gpt-4o-mini';
					self.panel.find('#pagelayer-ai-provider-picker').val('openai');
				} else if (self.activeKeys.anthropic) {
					provider = 'anthropic';
					model = $('#pagelayer-ai-anthropic-model', self.panel).val() || self.models.anthropic || 'claude-3-7-sonnet-20250219';
					self.panel.find('#pagelayer-ai-provider-picker').val('anthropic');
				}
				self.updateActiveModelBadge();
			}

			var targetPos = self.panel.find('#pagelayer-ai-target-position').val();
			var postId = (typeof pagelayer_postID !== 'undefined') ? pagelayer_postID : ((typeof pagelayer_post !== 'undefined' && pagelayer_post.ID) ? pagelayer_post.ID : 0);

			// Add User bubble
			self.addUserMessage(prompt);
			$input.val('').css('height', 'auto');

			var postData = {
				prompt: prompt,
				provider: provider,
				model: model,
				post_id: postId,
				target_position: targetPos,
				context: {
					page_title: (typeof pagelayer_post !== 'undefined' && pagelayer_post.post_title) ? pagelayer_post.post_title : '',
					target_position: targetPos
				}
			};
			if (provider === 'deepseek') {
				var enteredKey = $('#pagelayer-ai-deepseek-key', self.panel).val();
				if (enteredKey) postData.api_key = enteredKey;
			}
			if (provider === 'custom') {
				postData.custom_endpoint = self.panel.find('#pagelayer-ai-custom-endpoint').val();
				postData.custom_model = self.panel.find('#pagelayer-ai-custom-model').val() || model;
			}

			// Determine if this prompt is purely a color, background, or theme color tweak (skip choice modal)
			// OR a build request (ask user Style & Theme Preference modal)
			var isColorOrStyleOnly = self.isColorOrStyleChangeInstruction(prompt);

			if (isColorOrStyleOnly) {
				// Style/color tweaks, background changes, or global color updates execute directly without asking!
				if (self.isGlobalColorInstruction(prompt)) {
					postData.globals_mode = 'new';
					postData.use_existing_globals = false;
				} else if (self.isBackgroundColorInstruction(prompt)) {
					postData.globals_mode = 'custom';
					postData.custom_styling = true;
				} else {
					postData.globals_mode = 'existing';
					postData.use_existing_globals = true;
				}
				self.executeGeneration(postData);
			} else {
				// Building/generating any layout, section, page, or "build other": ALWAYS ask Style & Theme Preference!
				self.addStyleChoiceMessage(prompt, postData);
			}
		},

		/**
		 * Detect if prompt is an explicit command to build/make a new section or page
		 */
		isExplicitBuildRequest: function (prompt) {
			return !this.isColorOrStyleChangeInstruction(prompt);
		},

		/**
		 * Detect if prompt is purely a direct color, theme color, or background change instruction (and NOT a build request)
		 */
		isColorOrStyleChangeInstruction: function (prompt) {
			var p = (prompt || '').trim().toLowerCase();
			if (!p) {
				return false;
			}

			// If it contains multiple lines, dashes, or bullets, it is a multi-section build request
			if (p.indexOf('–') !== -1 || p.indexOf('—') !== -1 || /[-•]\s*\w+/i.test(p)) {
				var lines = p.split(/[\r\n]+/);
				if (lines.length >= 2) {
					return false;
				}
			}

			// Build commands are always build requests (e.g., "build other", "build this", "build pizza shop", "make a section")
			if (/^\s*(build|create|design|generate|add)\b/i.test(p)) {
				return false;
			}
			if (/^\s*make\s+(this|other|another|a\s+|an\s+|new\s+|page|section|site|layout|shop|store|landing|hero|card|table|widget)\b/i.test(p)) {
				return false;
			}
			if (/\b(build|create|design|generate)\s+(other|another|a\s+|an\s+|new\s+|this\s+|page|section|layout|site|shop|store|landing|hero)\b/i.test(p)) {
				return false;
			}

			// 1. Explicit global color / theme color update
			// e.g., "change theme color global", "set this global color", "change global color"
			if (this.isGlobalColorInstruction(p)) {
				return true;
			}

			// 2. Background color update
			// e.g., "change background color", "set background to black", "make all background dark"
			if (this.isBackgroundColorInstruction(p)) {
				return true;
			}

			// 3. Direct color set/change instructions
			// e.g., "set this color", "give set this color", "change color to blue"
			if (/^\s*(give\s+)?(set|change|update|apply)\s+(this|the)?\s*colou?r\b/i.test(p) ||
				/^\s*(change|set|update)\s+(theme\s+colou?r|brand\s+colou?r|background|bg)\b/i.test(p)) {
				return true;
			}

			return false;
		},

		/**
		 * Detect if prompt is asking to update/change global colors
		 */
		isGlobalColorInstruction: function (prompt) {
			var p = (prompt || '').trim().toLowerCase();
			return /\b(global\s+colou?rs?|global\s+palette|brand\s+colou?rs?|site\s+colou?rs?|theme\s+colou?r\s+global)\b/i.test(p) ||
				/\b(change|set|update|modify|turn|make)\b.{0,30}\b(global\s+colou?r|global\s+palette|brand\s+colou?r|theme\s+colou?r\s+global)\b/i.test(p) ||
				/\b(theme\s+colou?r\s+global|change\s+theme\s+colou?r\s+global)\b/i.test(p) ||
				/\b(give\s+)?set\s+this\s+global\s+colou?r\b/i.test(p);
		},

		/**
		 * Detect if prompt is asking to change/set background color or canvas dark/light mode
		 */
		isBackgroundColorInstruction: function (prompt) {
			var p = (prompt || '').trim().toLowerCase();
			return /\b(make|set|change|turn|switch)\b.{0,25}\b(all\s+)?(background|bg|theme)\b/i.test(p) ||
				/\b(change|set|update)\b.{0,20}\b(background\s+colou?r|bg\s+colou?r|canvas\s+colou?r)\b/i.test(p) ||
				/\b(make|set)\b.{0,20}\b(all\s+)?(background|bg)\b.{0,20}\b(dark|light|white|black|#[0-9a-f]{3,6}|[a-z]+)\b/i.test(p) ||
				(/\b(background|bg)\b.{0,20}\b(dark|light|white|black|#[0-9a-f]{3,6}|[a-z]+)\b/i.test(p) && !/\b(build|create)\s+(a|an|new)\s+(section|page|hero|pricing)\b/i.test(p)) ||
				(/\b(dark\s*mode|light\s*mode)\b/i.test(p) && !/\b(build|create)\s+(a|an|new)\s+(section|page|hero|pricing)\b/i.test(p)) ||
				/\b(give\s+)?set\s+this\s+colou?r\b/i.test(p);
		},

		/**
		 * Detect general style, color, font, or tweak instructions
		 */
		isStyleOrColorInstruction: function (prompt) {
			return this.isColorOrStyleChangeInstruction(prompt);
		},

		/**
		 * Render interactive Style Choice Card in Chat
		 */
		addStyleChoiceMessage: function (prompt, postData) {
			var self = this;
			var $box = self.panel.find('#pagelayer-ai-messages-box');
			var msgId = 'pl_choice_msg_' + Date.now();

			// Detect if prompt contains explicit color, font, or theme styling requests
			var promptLower = prompt.toLowerCase();
			var hasCustomHints = /(#([0-9a-f]{3}|[0-9a-f]{6})\b|dark mode|light mode|theme|color|palette|font|roboto|poppins|inter|montserrat|open sans|lato|red|blue|green|purple|black|white|gold|orange|yellow|gradient|glassmorphism|cyberpunk|minimalist)/i.test(promptLower);

			var html = '<div class="pagelayer-ai-msg assistant" id="' + msgId + '">' +
				'<div class="pagelayer-ai-avatar"><i class="fas fa-palette"></i></div>' +
				'<div class="pagelayer-ai-bubble" style="width:100%">' +
				'<div class="pagelayer-ai-choice-card">' +
				'<div class="pagelayer-ai-choice-title"><i class="fas fa-swatchbook"></i> Style & Theme Preference</div>' +
				'<p class="pagelayer-ai-choice-desc">How would you like to style this layout on your canvas?</p>' +
				'<div class="pagelayer-ai-choice-options">' +

				'<button type="button" class="pagelayer-ai-choice-btn" data-mode="existing">' +
				'<div class="pagelayer-ai-choice-icon"><i class="fas fa-palette"></i></div>' +
				'<div class="pagelayer-ai-choice-text">' +
				'<strong>Use Existing Site Colors & Fonts</strong>' +
				'<span>Keep active site theme without overriding globals</span>' +
				'</div>' +
				'</button>' +

				'<button type="button" class="pagelayer-ai-choice-btn" data-mode="new">' +
				'<div class="pagelayer-ai-choice-icon"><i class="fas fa-magic"></i></div>' +
				'<div class="pagelayer-ai-choice-text">' +
				'<strong>Generate New Brand Globals</strong>' +
				'<span>Create fresh AI colors & fonts and override site globals</span>' +
				'</div>' +
				'</button>' +

				(hasCustomHints ?
				'<button type="button" class="pagelayer-ai-choice-btn" data-mode="custom">' +
				'<div class="pagelayer-ai-choice-icon"><i class="fas fa-sliders-h"></i></div>' +
				'<div class="pagelayer-ai-choice-text">' +
				'<strong>Use Custom Styling from Prompt</strong>' +
				'<span>Follow your prompt\'s specific colors & typography</span>' +
				'</div>' +
				'</button>' : '') +

				'</div>' +
				'</div>' +
				'</div></div>';

			var $msg = $(html).appendTo($box);
			self.scrollToBottom();

			// Bind click handlers to choices
			$msg.find('.pagelayer-ai-choice-btn').on('click', function () {
				if (self.isGenerating) return;
				var $btn = $(this);
				var mode = $btn.attr('data-mode') || 'existing';

				// Disable options and highlight chosen
				$msg.find('.pagelayer-ai-choice-btn').prop('disabled', true);
				$btn.addClass('active-selected');

				postData.globals_mode = mode;
				postData.use_existing_globals = (mode === 'existing');
				if (mode === 'custom') {
					postData.custom_styling = true;
				}

				// Execute generation
				self.executeGeneration(postData);
			});
		},

		/**
		 * Execute Generation with Chosen Style Mode
		 */
		executeGeneration: function (postData) {
			var self = this;
			if (self.isGenerating) return;

			var targetPos = postData.target_position || 'end';

			// Add Loading / Real-time Planning bubble
			var loadingMsgId = self.addLoadingMessage(postData.prompt, postData);
			self.isGenerating = true;
			self.panel.find('#pagelayer-ai-send-btn').prop('disabled', true);

			$.ajax({
				url: self.settings.rest_url + 'generate',
				type: 'POST',
				headers: { 'X-WP-Nonce': self.settings.nonce },
				contentType: 'application/json',
				data: JSON.stringify(postData),
				timeout: 180000,
				success: function (resp) {
					self.removeMessage(loadingMsgId);

					if (resp && resp.success) {
						// 1. Direct Background Color Update
						if (resp.action === 'update_background') {
							self.applyCanvasBackgroundUpdate(resp);
							self.addBackgroundUpdatedMessage(resp);
							self.isGenerating = false;
							self.panel.find('#pagelayer-ai-send-btn').prop('disabled', false);
							return;
						}

						// 2. Direct Site Globals Update
						if (resp.action === 'update_globals') {
							self.applyCanvasGlobalsUpdate(resp);
							self.addGlobalsUpdatedMessage(resp);
							self.isGenerating = false;
							self.panel.find('#pagelayer-ai-send-btn').prop('disabled', false);
							return;
						}

						// 3. Layout generation
						if (resp.tree || resp.html || resp.theme_template) {
							if (resp.globals && resp.globals_updated) {
								self.applySiteGlobals(resp.globals);
							}

							if (resp.kind) {
								try {
									var $canvasBody = (typeof pagelayer !== 'undefined' && pagelayer.$) ? pagelayer.$('body') : $('body');
									$canvasBody.removeClass(function (i, c) {
										return (c.match(/(^|\s)pagelayer-ai-kind-\S+/g) || []).join(' ');
									});
									$canvasBody.addClass('pagelayer-ai-kind-' + resp.kind);
								} catch (e) { }
							}

							// Display Background AI Strategy & Plan Card in Chat
							if (resp.plan) {
								self.addPlanMessage(resp.plan, resp);
							}

							var shouldInsert = (resp.insert_on_canvas !== false) && (resp.tree || resp.html);
							if (shouldInsert) {
								self.buildLiveStepByStep(resp, targetPos, function () {
									self.isGenerating = false;
									self.panel.find('#pagelayer-ai-send-btn').prop('disabled', false);
									self.addTaskDoneMessage(resp);
								});
							} else {
								self.isGenerating = false;
								self.panel.find('#pagelayer-ai-send-btn').prop('disabled', false);
								self.addTaskDoneMessage(resp);
							}
							return;
						}
					}

					self.isGenerating = false;
					self.panel.find('#pagelayer-ai-send-btn').prop('disabled', false);
					self.addBotErrorMessage("Generated output was malformed. Please try again with a revised prompt.");
				},
				error: function (xhr) {
					self.removeMessage(loadingMsgId);
					self.isGenerating = false;
					self.panel.find('#pagelayer-ai-send-btn').prop('disabled', false);

					var err = '';
					if (xhr.responseJSON) {
						if (xhr.responseJSON.message) {
							err = xhr.responseJSON.message;
						} else if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
							err = xhr.responseJSON.data.message;
						} else if (typeof xhr.responseJSON === 'string') {
							err = xhr.responseJSON;
						}
					}
					if (!err && xhr.responseText) {
						try {
							var parsed = JSON.parse(xhr.responseText);
							err = parsed.message || (parsed.data && parsed.data.message) || parsed.error || '';
						} catch (e) { }
					}
					if (!err) {
						err = (xhr.statusText && xhr.statusText !== 'error') ? ('Request failed: ' + xhr.statusText) : 'Request failed. Please verify your API key and model settings.';
					}

					var isApiKeyMissing = (xhr.responseJSON && (xhr.responseJSON.code === 'missing_api_key' || (xhr.responseJSON.data && xhr.responseJSON.data.provider))) ||
						(typeof err === 'string' && (/API key/i.test(err) || /No API key found/i.test(err)));

					if (isApiKeyMissing) {
						self.addBotErrorMessage(err + '<br><button type="button" class="pagelayer-ai-btn-sm primary pagelayer-ai-open-settings-trigger" style="margin-top:8px;cursor:pointer"><i class="fas fa-key"></i> Open API Key Settings</button>');
					} else {
						self.addBotErrorMessage("<strong>Error:</strong> " + err);
					}
				}
			});
		},

		/**
		 * Apply Canvas Background & Dark Mode Update directly to editor DOM and rows
		 */
		applyCanvasBackgroundUpdate: function (resp) {
			var self = this;
			var $canvas = (typeof pagelayer !== 'undefined' && pagelayer.$) ? pagelayer.$ : $;
			var $editable = $canvas(pagelayer_editable || '.pagelayer-editable-area');
			if (!$editable.length) $editable = $canvas('[pagelayer-data]').first();
			if (!$editable.length) $editable = $canvas('.entry-content, .post-content, main, body').first();

			var bgColor = resp.bg_color || '#0f172a';
			var isDark = !!resp.is_dark;

			// Update Canvas body background and dark classes
			var $body = $canvas('body');
			if (isDark) {
				$body.addClass('pagelayer-canvas-dark pagelayer-ai-dark-canvas');
			} else {
				$body.removeClass('pagelayer-canvas-dark pagelayer-ai-dark-canvas');
			}
			$body.css('background-color', bgColor);

			// Update all section rows on canvas
			var $rows = $editable.find('.pagelayer-row:not(.pagelayer-row-holder)');
			if (!$rows.length) {
				$rows = $canvas('.pagelayer-row:not(.pagelayer-row-holder)');
			}

			$rows.each(function () {
				var $row = $canvas(this);
				if ($row.closest('.pagelayer-add-widget-area').length) return;

				var eleId = $row.attr('pagelayer-id');
				if (eleId && typeof pagelayer_set_atts === 'function') {
					var curClasses = (typeof pagelayer_get_att === 'function' ? pagelayer_get_att($row, 'ele_classes') : '') || '';
					curClasses = curClasses.replace(/\b(pagelayer-ai-on-dark|pagelayer-ai-on-light)\b/g, '').trim();
					var newClasses = curClasses + (isDark ? ' pagelayer-ai-on-dark' : '');

					pagelayer_set_atts($row, 'ele_bg_type', 'color');
					pagelayer_set_atts($row, 'ele_bg_color', bgColor);
					pagelayer_set_atts($row, 'ele_classes', newClasses.trim());
				}

				if (isDark) {
					$row.addClass('pagelayer-ai-on-dark').removeClass('pagelayer-ai-on-light');
				} else {
					$row.removeClass('pagelayer-ai-on-dark');
				}

				if (typeof pagelayer_sc_render === 'function') {
					pagelayer_sc_render($row);
				}
			});

			// Apply globals to CSS variables and pagelayer_global_colors
			if (resp.globals) {
				self.applySiteGlobals(resp.globals);
			}

			// Mark canvas dirty for save
			if (typeof pagelayer_do_dirty === 'function') {
				pagelayer_do_dirty($editable);
			}

			if (typeof pagelayer_history_action_push === 'function') {
				pagelayer_history_action_push({
					'title': 'AI Background Update',
					'action': 'Edited',
					'pl_id': 'canvas_bg',
					'color': bgColor
				});
				if (typeof pagelayer !== 'undefined') {
					pagelayer.history_action = true;
					pagelayer.global_render = true;
				}
			}
		},

		/**
		 * Apply Global Colors Update directly to editor CSS variables and re-render elements
		 */
		applyCanvasGlobalsUpdate: function (resp) {
			var self = this;
			var $canvas = (typeof pagelayer !== 'undefined' && pagelayer.$) ? pagelayer.$ : $;
			var $editable = $canvas(pagelayer_editable || '.pagelayer-editable-area');
			if (!$editable.length) $editable = $canvas('[pagelayer-data]').first();
			if (!$editable.length) $editable = $canvas('.entry-content, .post-content, main, body').first();

			// Apply site globals to CSS variables and pagelayer_global_colors
			if (resp.globals) {
				self.applySiteGlobals(resp.globals);
			}

			// Re-render shortcodes for all elements
			if (typeof pagelayer_sc_render === 'function') {
				$canvas('.pagelayer-ele').each(function () {
					pagelayer_sc_render($canvas(this));
				});
			}

			if (typeof pagelayer_do_dirty === 'function') {
				pagelayer_do_dirty($editable);
			}

			if (typeof pagelayer !== 'undefined') {
				pagelayer.history_action = true;
				pagelayer.global_render = true;
			}
		},

		/**
		 * Add Background Updated Message Card in Chat
		 */
		addBackgroundUpdatedMessage: function (resp) {
			var self = this;
			var $box = self.panel.find('#pagelayer-ai-messages-box');
			var msgId = 'pl_bg_msg_' + Date.now();
			var color = resp.bg_color || '#0f172a';
			var isDark = !!resp.is_dark;
			var title = isDark ? 'Dark Background Applied' : 'Background Color Updated';
			var desc = resp.message || (isDark ? 'Canvas & section backgrounds set to dark mode with high-contrast text.' : 'Background color set to ' + color + '.');

			var html = '<div class="pagelayer-ai-msg assistant" id="' + msgId + '">' +
				'<div class="pagelayer-ai-avatar" style="background:#8b5cf6"><i class="fas fa-fill-drip"></i></div>' +
				'<div class="pagelayer-ai-bubble" style="width:100%">' +
				'<div class="pagelayer-ai-done-card">' +
				'<div class="pagelayer-ai-done-head">' +
				'<div class="pagelayer-ai-done-badge" style="background:rgba(139,92,246,0.2);color:#c4b5fd"><i class="fas fa-check-circle"></i> ' + escapeHtml(title) + '</div>' +
				'</div>' +
				'<div class="pagelayer-ai-done-body">' +
				'<p style="margin:0 0 8px 0">' + escapeHtml(desc) + '</p>' +
				'<div style="display:flex;align-items:center;gap:8px;font-size:12px;color:#94a3b8">' +
				'<span style="display:inline-block;width:20px;height:20px;border-radius:4px;border:1px solid rgba(255,255,255,0.2);background:' + escapeHtml(color) + '"></span>' +
				'<span>Color: <strong>' + escapeHtml(color) + '</strong> (' + (isDark ? 'Dark Mode' : 'Light Mode') + ')</span>' +
				'</div>' +
				'</div>' +
				'</div>' +
				'</div></div>';

			$box.append(html);
			self.scrollToBottom();
		},

		/**
		 * Add Globals Updated Message Card in Chat
		 */
		addGlobalsUpdatedMessage: function (resp) {
			var self = this;
			var $box = self.panel.find('#pagelayer-ai-messages-box');
			var msgId = 'pl_globals_msg_' + Date.now();
			var palette = resp.palette || (resp.globals && resp.globals.colors) || {};
			var swatches = [];

			Object.keys(palette).forEach(function (k) {
				var val = palette[k];
				var hex = (val && typeof val === 'object' && val.value) ? val.value : val;
				if (typeof hex === 'string' && hex) {
					swatches.push('<span class="pagelayer-ai-plan-swatch" style="background:' + escapeHtml(hex) + '" title="' + escapeHtml(k + ': ' + hex) + '"></span>');
				}
			});

			var html = '<div class="pagelayer-ai-msg assistant" id="' + msgId + '">' +
				'<div class="pagelayer-ai-avatar" style="background:#3b82f6"><i class="fas fa-palette"></i></div>' +
				'<div class="pagelayer-ai-bubble" style="width:100%">' +
				'<div class="pagelayer-ai-done-card">' +
				'<div class="pagelayer-ai-done-head">' +
				'<div class="pagelayer-ai-done-badge" style="background:rgba(59,130,246,0.2);color:#93c5fd"><i class="fas fa-check-circle"></i> Site Globals Updated</div>' +
				'</div>' +
				'<div class="pagelayer-ai-done-body">' +
				'<p style="margin:0 0 8px 0">' + escapeHtml(resp.message || 'Global site colors updated. All matching elements reflect changes.') + '</p>' +
				(swatches.length ? '<div class="pagelayer-ai-plan-swatches" style="margin-top:6px">' + swatches.join('') + '</div>' : '') +
				'</div>' +
				'</div>' +
				'</div></div>';

			$box.append(html);
			self.scrollToBottom();
		},

		/**
		 * Live Step-by-Step Canvas Building Engine
		 * Constructs rows, columns, and widgets sequentially in real-time with Pagelayer element setup
		 */
		buildLiveStepByStep: function (response, position, onComplete) {
			var self = this;
			position = position || 'end';
			self.applySiteGlobals(response && response.globals);
			var $canvas = (typeof pagelayer !== 'undefined' && pagelayer.$) ? pagelayer.$ : $;
			self.ensureCanvasHighlightStyle($canvas);

			// Locate active canvas editable container
			var $editable = $canvas(pagelayer_editable || '.pagelayer-editable-area');
			if (!$editable.length) $editable = $canvas('[pagelayer-data]').first();
			if (!$editable.length) $editable = $canvas('.entry-content, .post-content, main, body').first();

			// Locate add-widget area ('Click here to add new row OR drag widgets')
			var $addSectionArea = $canvas('.pagelayer-add-widget-area').first();
			if (!$addSectionArea.length) {
				$addSectionArea = $canvas('.pagelayer-add-section-holder, .pagelayer-add-section').first();
			}

			// Parse full HTML into DOM nodes
			var $fullNodes = $canvas(response.html);
			if (!$fullNodes.length) {
				if (typeof onComplete === 'function') onComplete();
				return;
			}

			// Add a progress notification in chatbox
			var liveProgressMsgId = self.addLiveBuildingProgress();

			// Check if canvas currently only has an empty placeholder row (e.g., blank page with 0 widgets)
			var $hasAnyWidgets = $editable.find('.pagelayer-ele:not(.pagelayer-row):not(.pagelayer-col)').length > 0;
			if (!$hasAnyWidgets) {
				$editable.find('.pagelayer-wrap-row, .pagelayer-row:not(.pagelayer-row-holder)').filter(function () {
					return !$canvas(this).closest('.pagelayer-add-widget-area').length;
				}).remove();
			}

			// Insert outer container at target position (cleanly before add-widget area by default)
			if (position === 'start') {
				$editable.prepend($fullNodes);
			} else if (position === 'selection' && typeof pagelayer_active !== 'undefined' && pagelayer_active && pagelayer_active.id) {
				var $activeEle = $canvas('[pagelayer-id="' + pagelayer_active.id + '"]').first();
				if ($activeEle.length) {
					var $activeRow = $activeEle.closest('.pagelayer-wrap-row, .pagelayer-row:not(.pagelayer-row-holder)');
					var $activeRowWidgets = $activeRow.length ? $activeRow.find('.pagelayer-ele:not(.pagelayer-row):not(.pagelayer-col)') : [];
					if ($activeRow.length && $activeRowWidgets.length === 0) {
						$activeRow.replaceWith($fullNodes);
					} else {
						var $activeWrap = $activeEle.closest('.pagelayer-ele-wrap');
						if ($activeWrap.length) {
							$activeWrap.after($fullNodes);
						} else {
							$activeEle.after($fullNodes);
						}
					}
				} else if ($addSectionArea.length) {
					$addSectionArea.before($fullNodes);
				} else {
					$editable.append($fullNodes);
				}
			} else {
				if ($addSectionArea.length) {
					$addSectionArea.before($fullNodes);
				} else {
					$editable.append($fullNodes);
				}
			}

			// Run Pagelayer element setup only on the newly inserted tree
			if (typeof pagelayer_element_setup === 'function') {
				$fullNodes.each(function () {
					var $root = $canvas(this);
					var $eles = $root.find('.pagelayer-ele').addBack('.pagelayer-ele');
					$eles.each(function () {
						var id = $canvas(this).attr('pagelayer-id');
						if (id) {
							pagelayer_element_setup('[pagelayer-id="' + id + '"]', true);
						}
					});
				});
			}

			// Ensure no stray empty column dropzones remain inside populated columns
			$fullNodes.find('.pagelayer-col-holder').each(function () {
				var $holder = $canvas(this);
				if ($holder.find('.pagelayer-ele:not(.pagelayer-col)').length > 0) {
					$holder.children('.pagelayer-add-ele').remove();
				}
			});

			// Prepare build sequence queue from the properly wrapped elements
			var queue = [];
			$fullNodes.find('.pagelayer-wrap-row, .pagelayer-wrap-col, .pagelayer-wrap-ele').addBack('.pagelayer-wrap-row, .pagelayer-wrap-col, .pagelayer-wrap-ele').each(function () {
				var $wrap = $canvas(this);
				var $ele = $wrap.children('.pagelayer-ele');
				if (!$ele.length) return;

				var tag = $ele.attr('pagelayer-tag') || 'widget';
				var name = ucfirst(tag.replace('pl_', ''));
				if (tag === 'pl_row') name = 'Section Row';
				else if (tag === 'pl_col') name = 'Column';
				else if (tag === 'pl_heading') name = 'Heading';
				else if (tag === 'pl_text') name = 'Rich Text';
				else if (tag === 'pl_btn') name = 'Button';
				else if (tag === 'pl_image') name = 'Image';
				else if (tag === 'pl_service') name = 'Service Card';
				else if (tag === 'pl_icon') name = 'Icon';
				else if (tag === 'pl_testimonial') name = 'Testimonial';

				queue.push({
					wrap: $wrap,
					ele: $ele,
					type: (tag === 'pl_row' ? 'row' : (tag === 'pl_col' ? 'col' : 'widget')),
					name: name
				});
			});

			// Hide elements initially for sequential visual assembly
			queue.forEach(function (item) {
				if (item.type !== 'row') {
					item.wrap.css({ opacity: 0, transform: 'translateY(12px)', transition: 'all 0.25s cubic-bezier(0.16, 1, 0.3, 1)' });
				}
			});

			// Execute sequential live building (snappy dynamic interval completing in ~1.5s)
			var stepIndex = 0;
			var stepInterval = Math.max(25, Math.min(45, Math.floor(1800 / Math.max(1, queue.length))));

			function playNextStep() {
				if (stepIndex >= queue.length) {
					// Finalize complete setup for all elements
					$fullNodes.each(function () {
						var $node = $canvas(this);
						if (typeof pagelayer_sc_render === 'function') {
							pagelayer_sc_render($node);
							$node.find('.pagelayer-ele').each(function () {
								pagelayer_sc_render($canvas(this));
							});
						}
					});

					// Push to Undo/Redo stack
					if (typeof pagelayer_history_action_push === 'function') {
						var firstId = $fullNodes.first().attr('pagelayer-id') || 'ai_' + Math.floor(Math.random() * 10000);
						pagelayer_history_action_push({
							'title': 'Build with AI Section',
							'action': 'Added',
							'pl_id': firstId,
							'html': $fullNodes[0] ? $fullNodes[0].outerHTML : '',
							'cEle': $fullNodes
						});
						if (typeof pagelayer !== 'undefined') {
							pagelayer.history_action = true;
							pagelayer.global_render = true;
						}
					}

					// Mark Canvas Dirty for Save
					if (typeof pagelayer_do_dirty === 'function') {
						pagelayer_do_dirty($fullNodes);
					}

					self.clearLiveBuildHighlight($canvas, $fullNodes);

					// Remove progress indicator
					self.removeMessage(liveProgressMsgId);

					if (typeof onComplete === 'function') onComplete();
					return;
				}

				var cur = queue[stepIndex];
				stepIndex++;

				// Reveal element — highlight only the one currently being placed
				if (self._lastAiHighlight && self._lastAiHighlight.length) {
					self._lastAiHighlight.removeClass('pagelayer-ai-just-inserted');
				}
				cur.wrap.css({ opacity: 1, transform: 'translateY(0)' });
				cur.wrap.addClass('pagelayer-ai-just-inserted');
				self._lastAiHighlight = cur.wrap;

				// Render shortcode styles
				if (typeof pagelayer_sc_render === 'function') {
					pagelayer_sc_render(cur.ele);
				}

				// Update live building chat status
				self.updateLiveBuildingStatus(liveProgressMsgId, cur.type, cur.name);

				// Smooth scroll to active element being built
				if (cur.wrap[0] && cur.wrap[0].scrollIntoView) {
					cur.wrap[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				}

				setTimeout(playNextStep, stepInterval);
			}

			playNextStep();
		},

		/**
		 * Add Live Building Progress Indicator in Chat
		 */
		addLiveBuildingProgress: function () {
			var self = this;
			var $box = self.panel.find('#pagelayer-ai-messages-box');
			var id = 'pl_live_build_' + Date.now();
			var html = '<div class="pagelayer-ai-msg assistant" id="' + id + '">' +
				'<div class="pagelayer-ai-avatar"><div class="pagelayer-ai-spinner"></div></div>' +
				'<div class="pagelayer-ai-bubble" style="min-width:200px">' +
				'<div style="font-size:12px;font-weight:600;color:#818cf8;margin-bottom:4px"><i class="fas fa-magic"></i> Live Building Canvas...</div>' +
				'<div class="pl-ai-step-status" style="font-size:11px;color:#d1d5db;">🏗️ Assembling section container...</div>' +
				'</div>' +
				'</div>';
			$box.append(html);
			self.scrollToBottom();
			return id;
		},

		/**
		 * Update Live Building Step Text
		 */
		updateLiveBuildingStatus: function (msgId, type, name) {
			var self = this;
			var icon = '✨';
			if (type === 'row') icon = '🏗️';
			else if (type === 'col') icon = '📐';
			else if (name.toLowerCase().indexOf('heading') !== -1) icon = '✍️';
			else if (name.toLowerCase().indexOf('image') !== -1) icon = '🖼️';
			else if (name.toLowerCase().indexOf('btn') !== -1 || name.toLowerCase().indexOf('button') !== -1) icon = '🔘';

			$('#' + msgId + ' .pl-ai-step-status', self.panel).html(icon + ' Placing & styling <strong>' + name + '</strong>...');
		},

		/**
		 * Append User Message
		 */
		addUserMessage: function (text) {
			var self = this;
			var $box = self.panel.find('#pagelayer-ai-messages-box');
			var html = '<div class="pagelayer-ai-msg user">' +
				'<div class="pagelayer-ai-avatar"><i class="fas fa-user"></i></div>' +
				'<div class="pagelayer-ai-bubble">' + $('<div>').text(text).html() + '</div>' +
				'</div>';
			$box.append(html);
			self.scrollToBottom();
		},

		/**
		 * Add AI Architecture & Design Plan Card in Chat
		 */
		addPlanMessage: function (plan, responseData) {
			var self = this;
			if (!plan) return null;

			var $box = self.panel.find('#pagelayer-ai-messages-box');
			var msgId = 'pl_plan_msg_' + Date.now();

			var moodHtml = plan.mood ? '<span class="pagelayer-ai-plan-mood"><i class="fas fa-bolt"></i> ' + escapeHtml(plan.mood) + '</span>' : '';
			var summaryHtml = plan.summary ? '<div class="pagelayer-ai-plan-summary">' + escapeHtml(plan.summary) + '</div>' : '';

			// Sections badges
			var sectionsHtml = '';
			if (plan.sections && plan.sections.length) {
				sectionsHtml = '<div class="pagelayer-ai-plan-row">' +
					'<div class="pagelayer-ai-plan-label">Planned Structure:</div>' +
					'<div class="pagelayer-ai-plan-badges">' +
					plan.sections.map(function (s) {
						return '<span class="pagelayer-ai-plan-badge section"><i class="fas fa-layer-group"></i> ' + escapeHtml(s) + '</span>';
					}).join('') +
					'</div></div>';
			}

			// Palette swatches
			var paletteHtml = '';
			if (plan.palette && typeof plan.palette === 'object') {
				var swatches = [];
				var colors = plan.palette;
				var isPreserved = responseData && (responseData.globals_mode === 'existing' || !responseData.globals_updated);
				var tagHtml = isPreserved ?
					'<span class="pagelayer-ai-globals-tag preserved"><i class="fas fa-check"></i> Preserving Site Theme</span>' :
					'<span class="pagelayer-ai-globals-tag override"><i class="fas fa-magic"></i> New Site Globals</span>';

				Object.keys(colors).forEach(function (k) {
					var hex = (colors[k] && typeof colors[k] === 'object' && colors[k].value) ? colors[k].value : colors[k];
					if (typeof hex === 'string' && hex) {
						swatches.push('<span class="pagelayer-ai-plan-swatch" style="background:' + escapeHtml(hex) + '" title="' + escapeHtml(k + ': ' + hex) + '"></span>');
					}
				});

				if (swatches.length) {
					paletteHtml = '<div class="pagelayer-ai-plan-row">' +
						'<div style="display:flex;align-items:center;justify-content:space-between">' +
						'<span class="pagelayer-ai-plan-label">Design Palette:</span>' +
						tagHtml +
						'</div>' +
						'<div class="pagelayer-ai-plan-swatches">' + swatches.join('') + '</div>' +
						'</div>';
				}
			}

			// Fonts
			var fontsHtml = '';
			if (plan.fonts && typeof plan.fonts === 'object') {
				var fontBadges = [];
				Object.keys(plan.fonts).forEach(function (k) {
					var f = plan.fonts[k];
					var fam = (f && typeof f === 'object' && f.value && f.value['font-family']) ? f.value['font-family'] : ((typeof f === 'string') ? f : '');
					if (fam) {
						fontBadges.push('<span class="pagelayer-ai-plan-badge"><i class="fas fa-font"></i> ' + escapeHtml(ucfirst(k) + ': ' + fam) + '</span>');
					}
				});
				if (fontBadges.length) {
					fontsHtml = '<div class="pagelayer-ai-plan-row">' +
						'<div class="pagelayer-ai-plan-label">Typography:</div>' +
						'<div class="pagelayer-ai-plan-badges">' + fontBadges.join('') + '</div>' +
						'</div>';
				}
			}

			// Widgets
			var widgetsHtml = '';
			if (plan.widgets && plan.widgets.length) {
				var wBadges = plan.widgets.slice(0, 8).map(function (w) {
					var label = ucfirst(w.replace('pl_', ''));
					return '<span class="pagelayer-ai-plan-badge widget"><i class="fas fa-cube"></i> ' + escapeHtml(label) + '</span>';
				});
				if (plan.widgets.length > 8) {
					wBadges.push('<span class="pagelayer-ai-plan-badge widget">+' + (plan.widgets.length - 8) + ' more</span>');
				}
				widgetsHtml = '<div class="pagelayer-ai-plan-row">' +
					'<div class="pagelayer-ai-plan-label">Widgets:</div>' +
					'<div class="pagelayer-ai-plan-badges">' + wBadges.join('') + '</div>' +
					'</div>';
			}

			var html = '<div class="pagelayer-ai-msg assistant" id="' + msgId + '">' +
				'<div class="pagelayer-ai-avatar"><i class="fas fa-clipboard-list"></i></div>' +
				'<div class="pagelayer-ai-bubble" style="min-width:0">' +
				'<div class="pagelayer-ai-plan-card">' +
				'<div class="pagelayer-ai-plan-head">' +
				'<span class="pagelayer-ai-plan-title"><i class="fas fa-drafting-compass"></i> AI Design Plan</span>' +
				moodHtml +
				'</div>' +
				summaryHtml +
				sectionsHtml +
				paletteHtml +
				fontsHtml +
				widgetsHtml +
				'</div>' +
				'</div></div>';

			var $msg = $(html).appendTo($box);
			self.scrollToBottom();
			return msgId;
		},

		/**
		 * Add Task Done Completion Summary Card in Chat
		 */
		addTaskDoneMessage: function (responseData) {
			var self = this;
			var $box = self.panel.find('#pagelayer-ai-messages-box');
			var msgId = 'pl_done_msg_' + Date.now();

			var isPreserved = responseData && (responseData.globals_mode === 'existing' || !responseData.globals_updated);
			var globalsText = isPreserved ? 'Site global colors & typography preserved' : 'New site global colors & typography applied';
			var globalsIcon = isPreserved ? 'fas fa-palette' : 'fas fa-magic';

			var widgetCount = (responseData && responseData.widgets && responseData.widgets.length) ? responseData.widgets.length : 0;
			var shortcode = responseData && responseData.shortcode ? responseData.shortcode : '';
			var themeTpl = responseData && responseData.theme_template ? responseData.theme_template : null;
			var inserted = !(responseData && responseData.insert_on_canvas === false);
			var doneBody = inserted
				? 'Layout successfully assembled live on your canvas. All widgets follow Pagelayer standards and are 100% editable.'
				: (responseData && responseData.message ? responseData.message : 'Theme template created. Open it in the live editor to review and publish.');

			var html = '<div class="pagelayer-ai-msg assistant" id="' + msgId + '">' +
				'<div class="pagelayer-ai-avatar" style="background:#10b981"><i class="fas fa-check"></i></div>' +
				'<div class="pagelayer-ai-bubble" style="min-width:0">' +
				'<div class="pagelayer-ai-done-card">' +
				'<div class="pagelayer-ai-done-head">' +
				'<div class="pagelayer-ai-done-badge"><i class="fas fa-check-circle"></i> Task Completed!</div>' +
				'</div>' +
				'<div class="pagelayer-ai-done-body">' +
				'<p style="margin:0 0 6px 0">' + escapeHtml(doneBody) + '</p>' +
				(themeTpl && themeTpl.edit_url
					? '<p style="margin:0 0 8px 0"><a href="' + escapeHtml(themeTpl.edit_url) + '" target="_blank" rel="noopener" class="pagelayer-ai-meta-item"><i class="fas fa-external-link-alt"></i> ' + escapeHtml(themeTpl.title || 'Open theme template') + '</a></p>'
					: '') +
				'<div class="pagelayer-ai-done-meta">' +
				(widgetCount ? '<span class="pagelayer-ai-meta-item"><i class="fas fa-cubes"></i> ' + widgetCount + ' Widget Types</span>' : '') +
				'<span class="pagelayer-ai-meta-item"><i class="' + globalsIcon + '"></i> ' + globalsText + '</span>' +
				'</div>' +
				'</div>';

			if (inserted && responseData) {
				html += '<div class="pagelayer-ai-card-actions">' +
					'<button type="button" class="pagelayer-ai-btn-sm primary pl-ai-reinsert-btn"><i class="fas fa-redo"></i> Rebuild Live</button>' +
					'</div>';
			}

			html += '</div></div></div>';

			var $msg = $(html).appendTo($box);

			// Rebuild button
			if (responseData) {
				$msg.find('.pl-ai-reinsert-btn').on('click', function () {
					self.buildLiveStepByStep(responseData, self.panel.find('#pagelayer-ai-target-position').val());
				});
			}

			self.scrollToBottom();
		},

		/**
		 * Append Assistant Message
		 */
		addBotMessage: function (text, shortcode, showChips, responseData) {
			var self = this;
			var $box = self.panel.find('#pagelayer-ai-messages-box');
			var msgId = 'pl_ai_msg_' + Date.now();

			var html = '<div class="pagelayer-ai-msg assistant" id="' + msgId + '">' +
				'<div class="pagelayer-ai-avatar"><i class="fas fa-sparkles fa-magic"></i></div>' +
				'<div class="pagelayer-ai-bubble">' +
				'<div>' + text + '</div>';

			if (showChips) {
				html += '<div class="pagelayer-ai-chips-wrap">' +
					'<span class="pagelayer-ai-chip" data-prompt="Modern SaaS hero section with headline, subtitle, primary CTA button, and responsive mockup image">🚀 Hero Section</span>' +
					'<span class="pagelayer-ai-chip" data-prompt="3-column responsive feature grid highlighting fast performance, bank-grade security, and 24/7 support with icons and descriptions">✨ 3-Column Features</span>' +
					'<span class="pagelayer-ai-chip" data-prompt="Customer testimonial section with client photo avatars, 5-star rating icons, quotes, and company names">💬 Testimonials Grid</span>' +
					'<span class="pagelayer-ai-chip" data-prompt="3-tier pricing table for Starter, Professional (recommended badge), and Enterprise plans with feature checklist and CTA buttons">📊 Pricing Table</span>' +
					'<span class="pagelayer-ai-chip" data-prompt="Modern 2-column Contact Us section with contact details, address, working hours, and email info box">📞 Contact Section</span>' +
					'</div>';
			}

			if (responseData) {
				html += '<div class="pagelayer-ai-card-actions">' +
					'<button type="button" class="pagelayer-ai-btn-sm primary pl-ai-reinsert-btn"><i class="fas fa-redo"></i> Rebuild Live</button>' +
					'</div>';
			}

			html += '</div></div>';

			var $msg = $(html).appendTo($box);

			// Chip click handling
			$msg.find('.pagelayer-ai-chip').on('click', function () {
				var prompt = $(this).attr('data-prompt');
				self.panel.find('#pagelayer-ai-prompt-input').val(prompt).trigger('input');
				self.handlePromptSubmit();
			});

			// Rebuild button
			if (responseData) {
				$msg.find('.pl-ai-reinsert-btn').on('click', function () {
					self.buildLiveStepByStep(responseData, self.panel.find('#pagelayer-ai-target-position').val());
				});
			}

			self.scrollToBottom();
		},

		/**
		 * Append Error Message
		 */
		addBotErrorMessage: function (errMsg) {
			var self = this;
			var $box = self.panel.find('#pagelayer-ai-messages-box');
			var html = '<div class="pagelayer-ai-msg assistant">' +
				'<div class="pagelayer-ai-avatar" style="background:#ef4444"><i class="fas fa-exclamation-triangle"></i></div>' +
				'<div class="pagelayer-ai-bubble" style="border-color:rgba(239,68,68,0.3);background:rgba(239,68,68,0.1)">' + errMsg + '</div>' +
				'</div>';
			$box.append(html);
			self.scrollToBottom();
		},

		/**
		 * Infer Planning Metadata & Steps from prompt for real-time progress feedback
		 */
		inferPlanningMeta: function (prompt, postData) {
			var p = (prompt || '').toLowerCase();
			var target = 'Custom Layout';
			var components = [];
			var thoughts = [];

			// Detect target type
			if (/\b(header|navbar|nav bar)\b/.test(p) && /\b(template|theme|header)\b/.test(p) && p.indexOf('footer') === -1) {
				target = 'Header Theme Template';
				components = [
					{ icon: 'fa-heading', label: 'Brand / Logo' },
					{ icon: 'fa-bars', label: 'Primary Menu' },
					{ icon: 'fa-mouse-pointer', label: 'Header CTA' }
				];
				thoughts = [
					'Building a slim Theme Builder header bar (logo + menu)...',
					'Skipping homepage sections — header chrome only...',
					'Applying bar padding and contrast for site-wide use...'
				];
			} else if (/\bfooter\b/.test(p)) {
				target = 'Footer Theme Template';
				components = [
					{ icon: 'fa-columns', label: 'Footer Columns' },
					{ icon: 'fa-link', label: 'Useful Links' },
					{ icon: 'fa-share-alt', label: 'Social + Contact' },
					{ icon: 'fa-copyright', label: 'Copyright Bar' }
				];
				thoughts = [
					'Designing a real multi-column footer theme template...',
					'Excluding features, services, and other page sections...',
					'Styling brand, links, contact, and copyright rows...'
				];
			} else if (p.indexOf('404') !== -1 || p.indexOf('not found') !== -1) {
				target = '404 Error Page';
				components = [
					{ icon: 'fa-exclamation-circle', label: 'Error Code 404' },
					{ icon: 'fa-heading', label: 'Lost Headline' },
					{ icon: 'fa-align-left', label: 'Helpful Subtext' },
					{ icon: 'fa-home', label: 'Back Home CTA' }
				];
				thoughts = [
					'Designing clean, single-screen 404 layout without unrequested sections...',
					'Styling prominent error typography and navigation recovery button...',
					'Ensuring responsive viewport centering and theme contrast...'
				];
			} else if (p.indexOf('hero') !== -1 || p.indexOf('banner') !== -1) {
				target = 'Hero Section';
				components = [
					{ icon: 'fa-columns', label: 'Split Hero Row' },
					{ icon: 'fa-heading', label: 'Primary Headline' },
					{ icon: 'fa-paragraph', label: 'Subtitle Copy' },
					{ icon: 'fa-mouse-pointer', label: 'Primary CTA Button' },
					{ icon: 'fa-image', label: 'Mockup / Visual' }
				];
				thoughts = [
					'Architecting high-converting visual hierarchy and spacing...',
					'Selecting responsive grid proportions for headline and media...',
					'Aligning typography scale with site styling tokens...'
				];
			} else if (p.indexOf('price') !== -1 || p.indexOf('pricing') !== -1 || p.indexOf('plan') !== -1) {
				target = 'Pricing Table';
				components = [
					{ icon: 'fa-table', label: 'Pricing Cards' },
					{ icon: 'fa-dollar-sign', label: 'Tier Pricing & Badges' },
					{ icon: 'fa-check', label: 'Feature Checklist' },
					{ icon: 'fa-shopping-cart', label: 'Tier CTA Buttons' }
				];
				thoughts = [
					'Structuring comparative multi-column pricing grid...',
					'Setting up highlight styling for recommended tier card...',
					'Standardizing feature list padding and checkmark icons...'
				];
			} else if (p.indexOf('feature') !== -1 || p.indexOf('why choose') !== -1 || p.indexOf('service') !== -1) {
				target = 'Features & Services';
				components = [
					{ icon: 'fa-heading', label: 'Section Header' },
					{ icon: 'fa-th-large', label: 'Multi-Column Grid' },
					{ icon: 'fa-cubes', label: 'Icon Boxes' },
					{ icon: 'fa-arrow-right', label: 'Learn More Links' }
				];
				thoughts = [
					'Calculating balanced column widths and card gutters...',
					'Selecting contextual icons and uniform content heights...',
					'Configuring hover elevations and subtle transitions...'
				];
			} else if (p.indexOf('testimonial') !== -1 || p.indexOf('review') !== -1) {
				target = 'Testimonials & Social Proof';
				components = [
					{ icon: 'fa-heading', label: 'Social Proof Title' },
					{ icon: 'fa-quote-left', label: 'Review Cards' },
					{ icon: 'fa-star', label: 'Star Rating Rows' },
					{ icon: 'fa-user', label: 'Author Avatars' }
				];
				thoughts = [
					'Laying out customer feedback cards and quote typography...',
					'Adding star rating elements and reviewer verification tags...',
					'Optimizing avatar photo dimensions and card borders...'
				];
			} else if (p.indexOf('contact') !== -1 || p.indexOf('location') !== -1 || p.indexOf('hours') !== -1) {
				target = 'Contact & Location';
				components = [
					{ icon: 'fa-info-circle', label: 'Contact Details' },
					{ icon: 'fa-map-marker-alt', label: 'Location & Hours' },
					{ icon: 'fa-envelope', label: 'Inquiry CTA' }
				];
				thoughts = [
					'Organizing address, business hours, and contact channels...',
					'Configuring clean icon alignments for contact info items...',
					'Setting up call-to-action button styling...'
				];
			} else if (p.indexOf('slider') !== -1 || p.indexOf('carousel') !== -1) {
				target = 'Interactive Slider';
				components = [
					{ icon: 'fa-images', label: 'Slider Container' },
					{ icon: 'fa-layer-group', label: 'Content Slides' },
					{ icon: 'fa-chevron-left', label: 'Nav Arrows & Dots' }
				];
				thoughts = [
					'Configuring slider viewport and slide animation timing...',
					'Restricting slide image dimensions to prevent oversized displays...',
					'Formatting slide headlines, subtitles, and button triggers...'
				];
			} else {
				target = 'Section & Layout';
				components = [
					{ icon: 'fa-layer-group', label: 'Pagelayer Row' },
					{ icon: 'fa-heading', label: 'Headline & Content' },
					{ icon: 'fa-shapes', label: 'Styled Widgets' }
				];
				thoughts = [
					'Deconstructing layout requirements from your prompt...',
					'Architecting responsive rows, columns, and spacing...',
					'Selecting and styling suitable Pagelayer widgets...'
				];
			}

			// Add custom prompt components if detected
			if (p.indexOf('button') !== -1 && !components.some(function(c) { return c.label.indexOf('Button') !== -1; })) {
				components.push({ icon: 'fa-mouse-pointer', label: 'Action Button' });
			}
			if ((p.indexOf('image') !== -1 || p.indexOf('photo') !== -1) && !components.some(function(c) { return c.label.indexOf('Image') !== -1 || c.label.indexOf('Mockup') !== -1; })) {
				components.push({ icon: 'fa-image', label: 'Visual Media' });
			}

			var steps = [
				{ icon: 'fa-lightbulb', text: 'Deconstructing prompt requirements' },
				{ icon: 'fa-sitemap', text: 'Architecting section layout & grid' },
				{ icon: 'fa-cubes', text: 'Selecting Pagelayer widgets' },
				{ icon: 'fa-palette', text: 'Applying typography & color hierarchy' },
				{ icon: 'fa-code', text: 'Synthesizing layout JSON' }
			];

			return {
				target: target,
				components: components.slice(0, 5),
				thoughts: thoughts,
				steps: steps
			};
		},

		/**
		 * Add Loading & Real-Time Planning Architecture Bubble
		 */
		addLoadingMessage: function (prompt, postData) {
			var self = this;
			var $box = self.panel.find('#pagelayer-ai-messages-box');
			var id = 'pl_loading_' + Date.now();

			// Clean any existing interval
			if (self.loadingInterval) {
				clearInterval(self.loadingInterval);
				self.loadingInterval = null;
			}

			var meta = self.inferPlanningMeta(prompt, postData);

			var stepsHtml = '';
			meta.steps.forEach(function (st, idx) {
				var statusClass = (idx === 0) ? 'done' : (idx === 1 ? 'active' : 'pending');
				var iconClass = (idx === 0) ? 'fa-check-circle' : (idx === 1 ? 'fa-spinner' : 'fa-circle');
				stepsHtml += '<div class="pagelayer-ai-plan-step ' + statusClass + '" data-step="' + idx + '">' +
					'<span class="pl-step-icon"><i class="fas ' + iconClass + '"></i></span>' +
					'<span class="pl-step-text">' + escapeHtml(st.text) + '</span>' +
					'</div>';
			});

			var badgesHtml = '';
			meta.components.forEach(function (cp) {
				badgesHtml += '<span class="pagelayer-ai-plan-chip"><i class="fas ' + cp.icon + '"></i> ' + escapeHtml(cp.label) + '</span>';
			});

			var initialThought = meta.thoughts[0] || 'Analyzing requirements and planning visual architecture...';

			var html = '<div class="pagelayer-ai-msg assistant" id="' + id + '">' +
				'<div class="pagelayer-ai-avatar"><div class="pagelayer-ai-spinner"></div></div>' +
				'<div class="pagelayer-ai-bubble pagelayer-ai-loading-bubble" style="min-width:260px">' +
				'<div class="pagelayer-ai-loading-plan-card">' +
				'<div class="pagelayer-ai-loading-plan-head">' +
				'<div class="pagelayer-ai-loading-plan-title">' +
				'<span class="pagelayer-ai-loading-pulse-dot"></span>' +
				'<span>AI Planning & Architecture</span>' +
				'</div>' +
				'<span class="pagelayer-ai-loading-plan-target" title="' + escapeHtml(meta.target) + '">' + escapeHtml(meta.target) + '</span>' +
				'</div>' +

				'<div class="pagelayer-ai-planning-steps">' +
				stepsHtml +
				'</div>' +

				(badgesHtml ?
				'<div class="pagelayer-ai-planning-components">' +
				'<div class="pagelayer-ai-planning-comp-label">Planned Elements</div>' +
				'<div class="pagelayer-ai-planning-badges-wrap">' + badgesHtml + '</div>' +
				'</div>' : '') +

				'<div class="pagelayer-ai-live-thought">' +
				'<i class="fas fa-brain"></i>' +
				'<span class="pl-thought-text">' + escapeHtml(initialThought) + '</span>' +
				'</div>' +

				'<div class="pagelayer-ai-loading-progress-wrap">' +
				'<div class="pagelayer-ai-progress-track">' +
				'<div class="pagelayer-ai-progress-bar" style="width: 25%"></div>' +
				'</div>' +
				'</div>' +

				'</div>' + // .pagelayer-ai-loading-plan-card
				'</div>' + // .pagelayer-ai-bubble
				'</div>';

			$box.append(html);
			self.scrollToBottom();

			// Real-time animation ticker
			var currentStep = 1;
			var thoughtIdx = 0;
			var progressPct = 25;
			var $card = $('#' + id, self.panel);

			self.loadingInterval = setInterval(function () {
				if (!$card.length || !$('#' + id, self.panel).length) {
					clearInterval(self.loadingInterval);
					self.loadingInterval = null;
					return;
				}

				// Advance step progression
				if (currentStep < meta.steps.length - 1) {
					var $prev = $card.find('.pagelayer-ai-plan-step[data-step="' + currentStep + '"]');
					$prev.removeClass('active pending').addClass('done');
					$prev.find('.pl-step-icon').html('<i class="fas fa-check-circle"></i>');

					currentStep++;
					var $next = $card.find('.pagelayer-ai-plan-step[data-step="' + currentStep + '"]');
					$next.removeClass('pending done').addClass('active');
					$next.find('.pl-step-icon').html('<i class="fas fa-spinner"></i>');
				}

				// Update thought ticker
				thoughtIdx = (thoughtIdx + 1) % (meta.thoughts.length || 1);
				if (meta.thoughts[thoughtIdx]) {
					var $thought = $card.find('.pl-thought-text');
					$thought.fadeOut(200, function () {
						$thought.text(meta.thoughts[thoughtIdx]).fadeIn(200);
					});
				}

				// Advance progress bar smoothly up to 92%
				if (progressPct < 92) {
					progressPct += Math.floor(Math.random() * 14) + 10;
					if (progressPct > 92) progressPct = 92;
					$card.find('.pagelayer-ai-progress-bar').css('width', progressPct + '%');
				}
			}, 2200);

			return id;
		},

		removeMessage: function (id) {
			if (this.loadingInterval) {
				clearInterval(this.loadingInterval);
				this.loadingInterval = null;
			}
			$('#' + id, this.panel).remove();
		},

		scrollToBottom: function () {
			var $box = this.panel.find('#pagelayer-ai-messages-box');
			$box.stop().animate({ scrollTop: $box[0].scrollHeight }, 300);
		}
	};

	function escapeHtml(str) {
		if (typeof str !== 'string') return '';
		return $('<div>').text(str).html();
	}

	function ucfirst(str) {
		if (!str) return '';
		return str.charAt(0).toUpperCase() + str.slice(1);
	}

	// Initialize when Document and Pagelayer Editor are ready
	$(document).ready(function () {
		if (typeof pagelayer !== 'undefined') {
			PagelayerAI.init();
		} else {
			$(window).on('load', function () {
				PagelayerAI.init();
			});
		}
	});

	window.PagelayerAI = PagelayerAI;
	if (typeof window.parent !== 'undefined' && window.parent && window.parent !== window) {
		window.parent.PagelayerAI = PagelayerAI;
	}

})(jQuery);
