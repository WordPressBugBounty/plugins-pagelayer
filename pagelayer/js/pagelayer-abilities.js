jQuery(document).ready(function($){

	var dataEl = document.getElementById('pagelayer-abilities-json-data');
	var data = dataEl ? JSON.parse(dataEl.textContent) : {};

	var configData = {
		siteUrl: data.siteUrl || '',
		mcpServerUrl: data.mcpServerUrl || '',
		username: data.username || '',
		password: ''
	};

	var filePaths = {
		claude: 'macOS: ~/Library/Application Support/Claude/claude_desktop_config.json\nWindows: %APPDATA%\\Claude\\claude_desktop_config.json',
		claude_code: 'Terminal (Claude Code CLI)',
		cursor: '.cursor/mcp.json in your project root',
		grok_cli: 'Terminal (Grok CLI)',
		vscode: '.vscode/mcp.json in your project root',
		antigravity: '.antigravity/mcp.json in your project root',
		opencode: 'opencode.json in your project root (or ~/.config/opencode/opencode.json for global)',
		gemini_cli: '.gemini/settings.json in your project root'
	};

	var snippetTitles = {
		claude: 'JSON Configuration',
		claude_code: 'Terminal Command',
		cursor: 'JSON Configuration',
		grok_cli: 'Terminal Command',
		vscode: 'JSON Configuration',
		antigravity: 'JSON Configuration',
		opencode: 'JSON Configuration',
		gemini_cli: 'JSON Configuration'
	};

	var instructions = {
		claude: [
			'Open the file for your OS shown above (create it if it doesn\'t exist). On Linux, use the Claude Code CLI tab instead.',
			'Paste the snippet above into the file.',
			'Restart Claude Desktop to load the Pagelayer abilities.'
		],
		claude_code: [
			'Open a terminal in your project directory.',
			'Run the command displayed above.',
			'Verify the connection in your Claude Code CLI.'
		],
		cursor: [
			'Open your project in Cursor.',
			'Create a file at `.cursor/mcp.json` in your project root if it doesn\'t exist.',
			'Paste the configuration snippet into the file and save.'
		],
		grok_cli: [
			'Open a terminal in your project directory.',
			'Run the command displayed above.',
			'Verify the connection in your Grok CLI.'
		],
		vscode: [
			'Open your project in VS Code.',
			'Create a file at `.vscode/mcp.json` in your project root if it doesn\'t exist.',
			'Paste the configuration snippet into the file and save.'
		],
		antigravity: [
			'Open your project in your workspace.',
			'Create a file at `.antigravity/mcp.json` in your project root if it doesn\'t exist.',
			'Paste the configuration snippet into the file and save.'
		],
		opencode: [
			'Open your project.',
			'Create a file at `opencode.json` in your project root (or `~/.config/opencode/opencode.json` for a global config) if it doesn\'t exist.',
			'Paste the configuration snippet into the file and save.',
			'Restart OpenCode to load the Pagelayer abilities.'
		],
		gemini_cli: [
			'Open your project.',
			'Create a file at `.gemini/settings.json` in your project root if it doesn\'t exist.',
			'Paste the configuration snippet into the file and save.'
		]
	};

	var activeClient = 'claude';

	function updateSnippet() {
		var snippet = '';
		var serverName = 'pagelayer-mcp';
		var password = configData.password || '<YOUR_APP_PASSWORD>';
		var username = configData.username || '<your-username>';

		var jsonConfig = {
			mcpServers: {}
		};
		jsonConfig.mcpServers[serverName] = {
			command: 'npx',
			args: [
				'-y',
				'@automattic/mcp-wordpress-remote'
			],
			env: {
				WP_API_URL: configData.mcpServerUrl,
				WP_API_USERNAME: username,
				WP_API_PASSWORD: password
			}
		};

		// For Antigravity and VS Code, let's output basic auth header and direct SSE connection.
		if (activeClient === 'antigravity') {
			var basic = '<base64-of-username:application-password>';
			if (configData.password) {
				try {
					basic = window.btoa(username + ':' + configData.password);
				} catch (e) {}
			}
			var agy = {
				mcpServers: {}
			};
			agy.mcpServers[serverName] = {
				serverUrl: configData.mcpServerUrl,
				headers: { 'Authorization': 'Basic ' + basic }
			};
			snippet = JSON.stringify(agy, null, 2);
		} else if (activeClient === 'claude_code') {
			snippet = 'claude mcp add ' + serverName + ' -e WP_API_URL="' + configData.mcpServerUrl + '" -e WP_API_USERNAME="' + username + '" -e WP_API_PASSWORD="' + password + '" -- npx -y @automattic/mcp-wordpress-remote';
		} else if (activeClient === 'grok_cli') {
			snippet = 'grok mcp add ' + serverName + ' -e WP_API_URL="' + configData.mcpServerUrl + '" -e WP_API_USERNAME="' + username + '" -e WP_API_PASSWORD="' + password + '" -- npx -y @automattic/mcp-wordpress-remote';
		} else if (activeClient === 'opencode') {
			var basicOc = '<base64-of-username:application-password>';
			if (configData.password) {
				try {
					basicOc = window.btoa(username + ':' + configData.password);
				} catch (e) {}
			}
			var oc = {
				'$schema': 'https://opencode.ai/config.json',
				mcp: {}
			};
			oc.mcp[serverName] = {
				type: 'remote',
				url: configData.mcpServerUrl,
				enabled: true,
				headers: { 'Authorization': 'Basic ' + basicOc }
			};
			snippet = JSON.stringify(oc, null, 2);
		} else {
			snippet = JSON.stringify(jsonConfig, null, 2);
		}

		$('#pagelayer-ai-filepath').text(filePaths[activeClient] || '');
		$('#pagelayer-ai-snippet-title').text(snippetTitles[activeClient] || 'Configuration Snippet');
		$('#pagelayer-ai-snippet').text(snippet);

		// Render instructions
		var $inst = $('#pagelayer-ai-instructions-list').empty();
		var steps = instructions[activeClient] || [];
		$.each(steps, function(i, step){
			$inst.append('<li>' + step + '</li>');
		});
	}

	// Tab switching
	$(document).on('click', '.pagelayer-ai-segmented-btn', function(){
		$('.pagelayer-ai-segmented-btn').removeClass('active').attr('aria-selected', 'false');
		$(this).addClass('active').attr('aria-selected', 'true');
		activeClient = $(this).data('client');
		updateSnippet();
	});

	// Accordion toggle
	$(document).on('click', '.pagelayer-ai-row-toggle', function(){
		var $row = $(this).closest('.pagelayer-ai-row');
		var $body = $row.find('.pagelayer-ai-row-body');
		var expanded = $(this).attr('aria-expanded') === 'true';
		
		$(this).attr('aria-expanded', expanded ? 'false' : 'true');
		if(expanded){
			$body.slideUp(150);
		} else {
			$body.slideDown(150);
		}
	});

	// Copy Snippet
	$('#pagelayer-ai-copy-snippet-btn').on('click', function(){
		var text = $('#pagelayer-ai-snippet').text();
		navigator.clipboard.writeText(text).then(function() {
			var $label = $('#pagelayer-ai-copy-snippet-btn-label');
			var original = $label.text();
			$('#pagelayer-ai-copy-snippet-btn').addClass('is-copied');
			$label.text('Copied!');
			setTimeout(function(){
				$('#pagelayer-ai-copy-snippet-btn').removeClass('is-copied');
				$label.text(original);
			}, 2000);
		});
	});

	// Generate App Password
	$('#pagelayer-ai-pass-btn').on('click', function(){
		var $btn = $(this);
		if($btn.prop('disabled')) return;

		$btn.text('Generating...').prop('disabled', true);
		$.post(ajaxurl, {
			action: 'pagelayer_mcp_generate_app_password',
			nonce: data.nonce
		}, function(res) {
			if (res.success && res.data.password) {
				configData.password = res.data.password;
				configData.username = res.data.username || configData.username;

				$('#pagelayer-ai-pass-container').slideDown(150);
				$('.pagelayer-ai-username').val(configData.username);
				$('#pagelayer-ai-pass-input').val(configData.password);

				updateSnippet();
				$btn.text('Generate a new Application Password').prop('disabled', false);
				
				// Update row done style
				$btn.closest('.pagelayer-ai-row').addClass('pagelayer-ai-row-done');
			} else {
				alert('Error generating password.');
				$btn.text('Generate Application Password').prop('disabled', false);
			}
		});
	});

	// Activate/Install MCP Adapter
	$('#pagelayer-ai-adapter-btn').on('click', function(){
		var $btn = $(this);
		var actionType = $btn.data('action');
		if($btn.prop('disabled')) return;

		$btn.text(actionType === 'install' ? 'Installing...' : 'Activating...').prop('disabled', true);
		$('#pagelayer-ai-adapter-msg').text('').css('color', 'inherit');

		$.post(ajaxurl, {
			action: 'pagelayer_mcp_adapter_action',
			type: actionType,
			nonce: data.nonce
		}, function(res) {
			if (res.success) {
				$('#pagelayer-ai-adapter-msg').text('Success! Reloading...').css('color', 'green');
				setTimeout(function(){
					location.reload();
				}, 1200);
			} else {
				$('#pagelayer-ai-adapter-msg').text('Error: ' + (res.data || 'Failed')).css('color', 'red');
				$btn.text(actionType === 'install' ? 'Install Adapter' : 'Activate Adapter').prop('disabled', false);
			}
		});
	});

	// Test connection
	$('#pagelayer-ai-test-btn').on('click', function(){
		var $btn = $(this);
		var $result = $('#pagelayer-ai-test-result');
		var $status = $('#pagelayer-ai-test-status');

		if($btn.prop('disabled')) return;

		var password = configData.password;
		if(!password){
			$result.show().css('color', '#ef4444').text('Please generate an Application Password first.');
			return;
		}

		$btn.prop('disabled', true).text('Testing...');
		$status.text('Testing...');
		$result.hide().empty();

		serverTestConnection();

		function showTestResult(ok, message){
			$result.show()
				.removeClass('pagelayer-ai-test-result-ok pagelayer-ai-test-result-error')
				.addClass(ok ? 'pagelayer-ai-test-result-ok' : 'pagelayer-ai-test-result-error')
				.html((ok ? 'Success: ' : 'Failed: ') + message);
			
			$status.text(ok ? 'Verified' : 'Failed');
			$btn.closest('.pagelayer-ai-row').toggleClass('pagelayer-ai-row-done', !!ok);
			$btn.closest('.pagelayer-ai-row').toggleClass('pagelayer-ai-row-progress', !ok);

			persistTestStatus(ok, message);
		}

		function persistTestStatus(ok, message){
			$.post(ajaxurl, {
				action: 'pagelayer_mcp_save_test_status',
				ok: ok ? 1 : 0,
				message: message,
				nonce: data.nonce
			});
		}

		function serverTestConnection(){
			$.post(ajaxurl, {
				action: 'pagelayer_mcp_test_connection',
				username: configData.username,
				password: password,
				nonce: data.nonce
			}, function(res){
				var msg = (res && res.data && res.data.message) ? res.data.message : res.data;
				if(res.success){
					showTestResult(true, msg || 'Connected successfully.');
				} else {
					showTestResult(false, msg || 'Connection test failed.');
				}
			}).fail(function(){
				showTestResult(false, 'Could not reach the abilities endpoint. Check the site is reachable and try again.');
			}).always(function(){
				$btn.prop('disabled', false).text('Test connection');
			});
		}
	});

	// Save/update Pexels image search API key
	$('#pagelayer-ai-pexels-save-btn').on('click', function(){
		var $btn = $(this);
		var $msg = $('#pagelayer-ai-pexels-msg');
		var $status = $('#pagelayer-ai-pexels-status');
		var key = $('#pagelayer-ai-pexels-key').val().trim();

		if(!key){
			$msg.css('color', '#ef4444').text('Enter an API key first.');
			return;
		}

		$btn.prop('disabled', true).text('Saving...');
		$msg.css('color', 'inherit').text('');

		$.post(ajaxurl, {
			action: 'pagelayer_mcp_save_image_api_key',
			provider: 'pexels',
			api_key: key,
			nonce: data.nonce
		}, function(res){
			if(res.success){
				$msg.css('color', 'green').text('Saved.');
				$status.removeClass('pagelayer-ai-pill-neutral').addClass('pagelayer-ai-pill-success').text('Configured');
				$btn.text('Update Key');
				$('#pagelayer-ai-pexels-key').val('').attr('placeholder', '••••••••' + key.slice(-4));
			} else {
				$msg.css('color', '#ef4444').text((res.data && res.data.message) || 'Could not save key.');
				$btn.text('Save Key');
			}
		}).fail(function(){
			$msg.css('color', '#ef4444').text('Request failed. Please try again.');
			$btn.text('Save Key');
		}).always(function(){
			$btn.prop('disabled', false);
		});
	});

	// Initialize snippet
	updateSnippet();
});
