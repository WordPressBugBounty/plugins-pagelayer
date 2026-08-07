<?php

if(!defined('PAGELAYER_VERSION')) {
	exit('Hacking Attempt !');
}

class Pagelayer_Abilities {

	static $APP_PASSWORD_NAME = 'Pagelayer AI Agents';
	static $APP_PASSWORD_APP_ID = 'pagelayer-mcp';
	static $ABILITIES_ENDPOINT = '/wp-json/wp-abilities/v1/abilities';
	static $MCP_SERVER_ENDPOINT = '/wp-json/mcp/mcp-adapter-default-server';
	static $TEST_STATUS_META = 'pagelayer_mcp_test_status';

	public static function render_page() {
		// Include settings header
		include_once(PAGELAYER_DIR.'/main/settings.php');
		
		$capabilities_ok = current_user_can('manage_options');
		if (!$capabilities_ok) {
			echo '<div class="wrap"><h1>' . __('Unauthorized', 'pagelayer') . '</h1></div>';
			return;
		}

		pagelayer_page_header(__('AI Agents Connection', 'pagelayer'));

		$abilities_api_available = function_exists('wp_register_ability');
		$adapter_active = class_exists('\\WP\\MCP\\Core\\McpAdapter');
		$adapter_installed = '' !== self::get_installed_mcp_adapter_file();
		$has_app_password = self::current_user_has_mcp_app_password();
		$test_status = self::get_test_connection_status();
		$test_ok = !empty($test_status['ok']);
		$test_message = !empty($test_status['message']) ? $test_status['message'] : '';
		$site_url = trailingslashit(rtrim(home_url(), '/'));
		$endpoint_url = $site_url . ltrim(self::$ABILITIES_ENDPOINT, '/');
		$mcp_server_url = $site_url . ltrim(self::$MCP_SERVER_ENDPOINT, '/');
		$username = function_exists('wp_get_current_user') ? wp_get_current_user()->user_login : '';
		$app_pass_placeholder = __('your-application-password', 'pagelayer');
		$user_placeholder = $username ?: __('<your-username>', 'pagelayer');

		$total_steps = 4;
		$done_count  = 0;
		$next_step = '';
		if(!$abilities_api_available){
			$next_step = __('Abilities API registration', 'pagelayer');
		}elseif(!$adapter_active){
			$done_count++;
			$next_step = __('MCP Adapter installation', 'pagelayer');
		}elseif(!$has_app_password){
			$done_count += 2;
			$next_step = __('Application Password generation', 'pagelayer');
		}elseif(!$test_ok){
			$done_count += 3;
			$next_step = __('Connection test', 'pagelayer');
		}else{
			$done_count += 4;
		}

		$stepper_width = 0;
		if ($done_count > 1) {
			$stepper_width = ($done_count - 1) / 3 * 100;
		}

		$adapter_label = $adapter_active ? __('Active', 'pagelayer') : ($adapter_installed ? __('Inactive', 'pagelayer') : __('Not installed', 'pagelayer'));
		?>

		<div class="pagelayer-ai">

			<!-- Main 2-column layout -->
			<div class="pagelayer-ai-layout">

				<!-- LEFT COLUMN -->
				<div class="pagelayer-ai-main">

					<!-- Setup checklist card -->
					<div class="pagelayer-ai-card">
						<div class="pagelayer-ai-card-head">
							<h2 class="pagelayer-ai-h2"><?php esc_html_e('Setup', 'pagelayer'); ?></h2>
							<div class="pagelayer-ai-meta"><?php echo esc_html(sprintf(__('%1$d of %2$d steps complete', 'pagelayer'), $done_count, $total_steps)); ?></div>
						</div>

						<!-- Connector progress bar -->
						<div class="pagelayer-ai-stepper" role="list">
							<div class="pagelayer-ai-stepper-track" aria-hidden="true">
								<span class="pagelayer-ai-stepper-fill" style="width: <?php echo esc_attr($stepper_width); ?>%;"></span>
							</div>
							<?php
								$chips = [
									['label' => __('Abilities', 'pagelayer'),  'state' => $abilities_api_available ? 'done' : 'todo'],
									['label' => __('MCP Adapter', 'pagelayer'),'state' => $adapter_active ? 'done' : 'todo'],
									['label' => __('Password', 'pagelayer'),   'state' => $has_app_password ? 'done' : 'todo'],
									['label' => __('Test', 'pagelayer'),       'state' => $test_ok ? 'done' : 'todo'],
								];
								foreach($chips as $idx => $chip){
									echo '<div class="pagelayer-ai-stepper-node pagelayer-ai-stepper-node-' . esc_attr($chip['state']) . '" role="listitem">';
									echo '<span class="pagelayer-ai-stepper-circle">' . ($chip['state'] === 'done' ? '&#10003;' : esc_html((string)($idx + 1))) . '</span>';
									echo '<span class="pagelayer-ai-stepper-label">' . esc_html($chip['label']) . '</span>';
									echo '</div>';
								}
							?>
						</div>

						<?php
							if($next_step){
								echo '<p class="pagelayer-ai-nextup">' . sprintf(__('Next up: %s', 'pagelayer'), '<strong>' . esc_html($next_step) . '</strong>') . '</p>';
							}else{
								echo '<p class="pagelayer-ai-nextup pagelayer-ai-nextup-done">' . __('All steps complete. Your AI client can now connect.', 'pagelayer') . '</p>';
							}
						?>

						<!-- Step rows -->
						<div class="pagelayer-ai-rows">

							<!-- Step 1: Abilities registered -->
							<div class="pagelayer-ai-row <?php echo ($abilities_api_available ? 'pagelayer-ai-row-done' : ''); ?>" data-step="1">
								<button type="button" class="pagelayer-ai-row-toggle" aria-expanded="true">
									<span class="pagelayer-ai-row-indicator"><?php echo ($abilities_api_available ? '&#10003;' : '1'); ?></span>
									<span class="pagelayer-ai-row-text">
										<span class="pagelayer-ai-row-title"><?php esc_html_e('Pagelayer abilities registered', 'pagelayer'); ?></span>
										<span class="pagelayer-ai-row-sub"><?php esc_html_e('Exposed via the WordPress 6.9+ Abilities API', 'pagelayer'); ?></span>
									</span>
									<span class="pagelayer-ai-pill pagelayer-ai-pill-<?php echo ($abilities_api_available ? 'success' : 'warning'); ?>"><?php echo ($abilities_api_available ? esc_html__('Ready', 'pagelayer') : esc_html__('Requires WP 6.9+', 'pagelayer')); ?></span>
								</button>
								<div class="pagelayer-ai-row-body">
									<?php
										if($abilities_api_available){
											echo '<p>' . esc_html__('Pagelayer registers building and styling abilities. They are picked up automatically by any installed MCP adapter and surfaced to AI clients as tools.', 'pagelayer') . '</p>';
											echo wp_kses_post( self::render_abilities_list() );
										}else{
											echo '<p>' . esc_html__('The WordPress Abilities API ships in WordPress 6.9+. Pagelayer can still work today — installing the MCP Adapter backfills the API so your AI client can connect immediately. Consider updating WordPress for native abilities support.', 'pagelayer') . '</p>';
										}
									?>
								</div>
							</div>

							<!-- Step 2: MCP Adapter -->
							<?php
								$adapter_state = $adapter_active ? 'done' : ($adapter_installed ? 'progress' : 'todo');
							?>
							<div class="pagelayer-ai-row <?php echo ($adapter_active ? 'pagelayer-ai-row-done' : ''); ?>" data-step="2">
								<button type="button" class="pagelayer-ai-row-toggle" aria-expanded="<?php echo ($adapter_active ? 'false' : 'true'); ?>">
									<span class="pagelayer-ai-row-indicator"><?php echo ($adapter_active ? '&#10003;' : '2'); ?></span>
									<span class="pagelayer-ai-row-text">
										<span class="pagelayer-ai-row-title"><?php esc_html_e('MCP Adapter installed', 'pagelayer'); ?></span>
										<span class="pagelayer-ai-row-sub"><?php esc_html_e('Bridges your site to MCP-compatible AI clients', 'pagelayer'); ?></span>
									</span>
									<span class="pagelayer-ai-pill pagelayer-ai-pill-<?php echo ($adapter_active ? 'success' : 'warning'); ?>"><?php echo esc_html($adapter_label); ?></span>
								</button>
								<div class="pagelayer-ai-row-body">
									<?php
										if($adapter_active){
											echo '<p>' . esc_html__('The MCP Adapter plugin is installed and active. Your site now exposes an MCP server that AI clients can connect to.', 'pagelayer') . '</p>';
										}elseif($adapter_installed){
											echo '<p>' . esc_html__('The MCP Adapter is installed but not active. Activate it to enable the MCP server.', 'pagelayer') . '</p>';
											echo '<button type="button" class="button button-primary pagelayer-ai-btn" id="pagelayer-ai-adapter-btn" data-action="activate">' . esc_html__('Activate MCP Adapter', 'pagelayer') . '</button>';
										}else{
											echo '<p>' . esc_html__('Install the official WordPress MCP Adapter plugin with a single click. It bridges your site to any MCP-compatible AI client.', 'pagelayer') . '</p>';
											echo '<button type="button" class="button button-primary pagelayer-ai-btn" id="pagelayer-ai-adapter-btn" data-action="install">' . esc_html__('Install MCP Adapter', 'pagelayer') . '</button>';
										}
									?>
									<div id="pagelayer-ai-adapter-msg" style="margin-top:10px; font-size:13px;"></div>
								</div>
							</div>

							<!-- Step 3: Application Password -->
							<div class="pagelayer-ai-row <?php echo ($has_app_password ? 'pagelayer-ai-row-done' : ''); ?>" data-step="3">
								<button type="button" class="pagelayer-ai-row-toggle" aria-expanded="<?php echo ($has_app_password ? 'false' : 'true'); ?>">
									<span class="pagelayer-ai-row-indicator"><?php echo ($has_app_password ? '&#10003;' : '3'); ?></span>
									<span class="pagelayer-ai-row-text">
										<span class="pagelayer-ai-row-title"><?php esc_html_e('Application Password generated', 'pagelayer'); ?></span>
										<span class="pagelayer-ai-row-sub"><?php esc_html_e('Authenticates your AI client against your site', 'pagelayer'); ?></span>
									</span>
									<span class="pagelayer-ai-pill pagelayer-ai-pill-<?php echo ($has_app_password ? 'success' : 'warning'); ?>"><?php echo ($has_app_password ? esc_html__('Generated', 'pagelayer') : esc_html__('Action needed', 'pagelayer')); ?></span>
								</button>
								<div class="pagelayer-ai-row-body">
									<p><?php esc_html_e('Generate a WordPress Application Password for your AI client. The password is shown only once — copy it somewhere safe.', 'pagelayer'); ?></p>
									<div class="pagelayer-ai-app-pass-fields" id="pagelayer-ai-pass-container" style="display:none;">
										<label class="pagelayer-ai-field"><span><?php esc_html_e('Username', 'pagelayer'); ?></span><input type="text" readonly class="pagelayer-ai-input" value="<?php echo esc_attr($username); ?>" /></label>
										<label class="pagelayer-ai-field"><span><?php esc_html_e('Application Password', 'pagelayer'); ?></span><input type="text" readonly class="pagelayer-ai-input" id="pagelayer-ai-pass-input" value="" /></label>
										<a class="pagelayer-ai-profile-link" href="<?php echo esc_url(admin_url('profile.php#application-passwords-section')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Manage in profile', 'pagelayer'); ?> &nbsp;&rarr;</a>
									</div>
									<button type="button" class="button button-secondary pagelayer-ai-btn" id="pagelayer-ai-pass-btn"><?php echo ($has_app_password ? esc_html__('Generate a new Application Password', 'pagelayer') : esc_html__('Generate Application Password', 'pagelayer')); ?></button>
								</div>
							</div>

							<!-- Step 4: Test Connection -->
							<div class="pagelayer-ai-row <?php echo ($test_ok ? 'pagelayer-ai-row-done' : ($test_message ? 'pagelayer-ai-row-progress' : '')); ?>" data-step="4">
								<button type="button" class="pagelayer-ai-row-toggle" aria-expanded="<?php echo ($test_ok ? 'false' : 'true'); ?>">
									<span class="pagelayer-ai-row-indicator"><?php echo ($test_ok ? '&#10003;' : '4'); ?></span>
									<span class="pagelayer-ai-row-text">
										<span class="pagelayer-ai-row-title"><?php esc_html_e('Test the connection', 'pagelayer'); ?></span>
										<span class="pagelayer-ai-row-sub"><?php esc_html_e('Verify authentication and ability discovery', 'pagelayer'); ?></span>
									</span>
									<span class="pagelayer-ai-pill pagelayer-ai-pill-<?php echo ($test_ok ? 'success' : 'neutral'); ?>" id="pagelayer-ai-test-status"><?php echo ($test_ok ? esc_html__('Verified', 'pagelayer') : esc_html__('Pending', 'pagelayer')); ?></span>
								</button>
								<div class="pagelayer-ai-row-body">
									<p><?php esc_html_e('Verify that your AI client can authenticate against your site and discover Pagelayer abilities.', 'pagelayer'); ?></p>
									<button type="button" class="button button-secondary pagelayer-ai-btn" id="pagelayer-ai-test-btn"><?php esc_html_e('Test connection', 'pagelayer'); ?></button>
									<div id="pagelayer-ai-test-result" class="pagelayer-ai-test-result <?php echo $test_ok ? 'pagelayer-ai-test-result-ok' : 'pagelayer-ai-test-result-error'; ?>" style="<?php echo $test_message ? '' : 'display:none;'; ?>">
										<?php echo $test_message ? esc_html(($test_ok ? __('Success: ', 'pagelayer') : __('Failed: ', 'pagelayer')) . $test_message) : ''; ?>
									</div>
								</div>
							</div>

						</div> <!-- .pagelayer-ai-rows -->
					</div> <!-- .pagelayer-ai-card -->

					<!-- AI Client Configuration -->
					<div class="pagelayer-ai-card">
						<div class="pagelayer-ai-card-head">
							<h2 class="pagelayer-ai-h2"><?php esc_html_e('Connect to AI Client', 'pagelayer'); ?></h2>
							<div class="pagelayer-ai-meta"><?php esc_html_e('Pick a client, copy the snippet, paste it in the configuration file.', 'pagelayer'); ?></div>
						</div>

						<script type="application/json" id="pagelayer-abilities-json-data"><?php
							echo wp_json_encode([
								'siteUrl'        => $site_url,
								'mcpServerUrl'   => $mcp_server_url,
								'username'       => $username,
								'nonce'          => wp_create_nonce('pagelayer_mcp_nonce'),
							]);
						?></script>

						<div class="pagelayer-ai-segmented" role="tablist">
							<button type="button" class="pagelayer-ai-segmented-btn active" data-client="claude">Claude Desktop</button>
							<button type="button" class="pagelayer-ai-segmented-btn" data-client="claude_code">Claude Code CLI</button>
							<button type="button" class="pagelayer-ai-segmented-btn" data-client="cursor">Cursor</button>
							<button type="button" class="pagelayer-ai-segmented-btn" data-client="grok_cli">Grok CLI</button>
							<button type="button" class="pagelayer-ai-segmented-btn" data-client="vscode">VS Code</button>
							<button type="button" class="pagelayer-ai-segmented-btn" data-client="antigravity">Antigravity</button>
							<button type="button" class="pagelayer-ai-segmented-btn" data-client="opencode">OpenCode</button>
							<button type="button" class="pagelayer-ai-segmented-btn" data-client="gemini_cli">Gemini CLI <span class="pagelayer-deprecated-badge"><?php esc_html_e('Deprecated', 'pagelayer'); ?></span></button>
						</div>

						<!-- Client panel -->
						<div class="pagelayer-ai-client-panel">
							<div class="pagelayer-ai-client-meta">
								<span class="pagelayer-ai-client-path-label"><?php esc_html_e('Config file path:', 'pagelayer'); ?></span>
								<code class="pagelayer-ai-client-path-value" id="pagelayer-ai-filepath"></code>
							</div>

							<div class="pagelayer-ai-snippet-wrap">
								<div class="pagelayer-ai-snippet-header">
									<span class="pagelayer-ai-snippet-title" id="pagelayer-ai-snippet-title"><?php esc_html_e('JSON Configuration', 'pagelayer'); ?></span>
									<button type="button" class="pagelayer-ai-copy-btn" id="pagelayer-ai-copy-snippet-btn">
										<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
										<span id="pagelayer-ai-copy-snippet-btn-label"><?php esc_html_e('Copy snippet', 'pagelayer'); ?></span>
									</button>
								</div>
								<pre class="pagelayer-ai-snippet" id="pagelayer-ai-snippet" tabindex="0"></pre>
							</div>

							<ol class="pagelayer-ai-instructions" id="pagelayer-ai-instructions-list"></ol>
						</div>
					</div>

				</div> <!-- .pagelayer-ai-main -->

				<!-- RIGHT COLUMN (sidebar) -->
				<div class="pagelayer-ai-side">

					<!-- Status card -->
					<div class="pagelayer-ai-card pagelayer-ai-side-card">
						<h3 class="pagelayer-ai-h3"><?php esc_html_e('Site status', 'pagelayer'); ?></h3>
						<dl class="pagelayer-ai-status-list">
							<div>
								<dt><?php esc_html_e('Abilities API', 'pagelayer'); ?></dt>
								<dd><span class="pagelayer-ai-pill pagelayer-ai-pill-<?php echo ($abilities_api_available ? 'success' : 'neutral'); ?>"><?php echo ($abilities_api_available ? esc_html__('Available', 'pagelayer') : esc_html__('Unavailable', 'pagelayer')); ?></span></dd>
							</div>
							<div>
								<dt><?php esc_html_e('MCP Adapter', 'pagelayer'); ?></dt>
								<dd><span class="pagelayer-ai-pill pagelayer-ai-pill-<?php echo ($adapter_active ? 'success' : ($adapter_installed ? 'warning' : 'neutral')); ?>"><?php echo esc_html($adapter_label); ?></span></dd>
							</div>
							<div>
								<dt><?php esc_html_e('Application Password', 'pagelayer'); ?></dt>
								<dd><span class="pagelayer-ai-pill pagelayer-ai-pill-<?php echo ($has_app_password ? 'success' : 'neutral'); ?>"><?php echo ($has_app_password ? esc_html__('Generated', 'pagelayer') : esc_html__('Not generated', 'pagelayer')); ?></span></dd>
							</div>
							<div>
								<dt><?php esc_html_e('Last test', 'pagelayer'); ?></dt>
								<dd><span class="pagelayer-ai-pill pagelayer-ai-pill-<?php echo ($test_ok ? 'success' : ($test_message ? 'error' : 'neutral')); ?>"><?php echo ($test_ok ? esc_html__('Tested', 'pagelayer') : ($test_message ? esc_html__('Failed', 'pagelayer') : esc_html__('Never', 'pagelayer'))); ?></span></dd>
							</div>
						</dl>
					</div>

					<!-- Quick tips card -->
					<div class="pagelayer-ai-card pagelayer-ai-side-card">
						<h3 class="pagelayer-ai-h3"><?php esc_html_e('Quick tips', 'pagelayer'); ?></h3>
						<ul class="pagelayer-ai-tip-list">
							<li><?php esc_html_e('Generate one Application Password per AI client so you can revoke them individually.', 'pagelayer'); ?></li>
							<li><?php esc_html_e('The password is shown only once. Copy it before navigating away.', 'pagelayer'); ?></li>
							<li><?php esc_html_e('Application Passwords are stored in your user profile and can be revoked at any time.', 'pagelayer'); ?></li>
							<li><?php esc_html_e('Each ability is permission-gated - your AI client can only act within your user capabilities.', 'pagelayer'); ?></li>
						</ul>
					</div>

					<!-- Image search (Pexels) card -->
					<?php
						$pexels_key = get_option('pagelayer_pexels_api_key', '');
						$pexels_configured = !empty($pexels_key);
					?>
					<div class="pagelayer-ai-card pagelayer-ai-side-card">
						<h3 class="pagelayer-ai-h3"><?php esc_html_e('Image Search (Pexels)', 'pagelayer'); ?></h3>
						<p class="pagelayer-ai-side-desc"><?php esc_html_e('Lets the AI pull real, topically relevant photos instead of generic placeholders when it builds pages. Get a free key from pexels.com/api.', 'pagelayer'); ?></p>
						<label class="pagelayer-ai-field">
							<span><?php esc_html_e('Pexels API Key', 'pagelayer'); ?></span>
							<input type="text" class="pagelayer-ai-input" id="pagelayer-ai-pexels-key" placeholder="<?php echo $pexels_configured ? esc_attr(str_repeat('•', 8) . substr($pexels_key, -4)) : esc_attr__('your-pexels-api-key', 'pagelayer'); ?>" value="" />
						</label>
						<button type="button" class="button button-secondary pagelayer-ai-btn" id="pagelayer-ai-pexels-save-btn"><?php echo $pexels_configured ? esc_html__('Update Key', 'pagelayer') : esc_html__('Save Key', 'pagelayer'); ?></button>
						<span class="pagelayer-ai-pill pagelayer-ai-pill-<?php echo $pexels_configured ? 'success' : 'neutral'; ?>" id="pagelayer-ai-pexels-status" style="margin-left:8px;"><?php echo $pexels_configured ? esc_html__('Configured', 'pagelayer') : esc_html__('Not configured', 'pagelayer'); ?></span>
						<div id="pagelayer-ai-pexels-msg" style="margin-top:8px; font-size:13px;"></div>
					</div>

					<!-- Resources card -->
					<div class="pagelayer-ai-card pagelayer-ai-side-card">
						<h3 class="pagelayer-ai-h3"><?php esc_html_e('Resources', 'pagelayer'); ?></h3>
						<ul class="pagelayer-ai-resource-list">
							<li><a href="https://pagelayer.com/docs" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Pagelayer documentation', 'pagelayer'); ?></a><span class="pagelayer-ai-resource-sub"><?php esc_html_e('Guides', 'pagelayer'); ?></span></li>
							<li><a href="https://modelcontextprotocol.io/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Model Context Protocol', 'pagelayer'); ?></a><span class="pagelayer-ai-resource-sub"><?php esc_html_e('What MCP is and how it works', 'pagelayer'); ?></span></li>
							<li><a href="https://github.com/WordPress/mcp-adapter" target="_blank" rel="noopener noreferrer"><?php esc_html_e('WordPress MCP Adapter', 'pagelayer'); ?></a><span class="pagelayer-ai-resource-sub"><?php esc_html_e('Official plugin repository', 'pagelayer'); ?></span></li>
						</ul>
					</div>

				</div> <!-- .pagelayer-ai-side -->
			</div> <!-- .pagelayer-ai-layout -->
		</div> <!-- .pagelayer-ai -->

		<?php
		pagelayer_page_footer();
	}

	static function render_abilities_list() {
		$groups = self::get_registered_abilities();

		if(empty($groups)){
			return '<p class="pagelayer-ai-empty">' . esc_html__('No Pagelayer abilities are currently exposed. Activate the MCP Adapter to surface them.', 'pagelayer') . '</p>';
		}

		$html = '<div class="pagelayer-ai-list">';
		foreach($groups as $group => $items){
			$html .= '<div class="pagelayer-ai-group">';
			$html .= '<h4>' . esc_html($group) . '</h4>';
			$html .= '<ul>';
			foreach($items as $ability){
				$html .= '<li>';
				$html .= '<span class="pagelayer-ai-ability-name">' . esc_html($ability['label']) . '</span>';
				if(!empty($ability['description'])){
					$html .= '<span class="pagelayer-ai-ability-desc">' . esc_html($ability['description']) . '</span>';
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	static function get_registered_abilities(){
		return [
			__('Pages Management', 'pagelayer') => [
				[
					'label'       => __('Create Page', 'pagelayer'),
					'description' => __('Creates a new WordPress page built with Pagelayer.', 'pagelayer'),
				],
				[
					'label'       => __('Update Page', 'pagelayer'),
					'description' => __('Updates page title, builder data, and publish status.', 'pagelayer'),
				],
				[
					'label'       => __('Get / List / Delete Pages', 'pagelayer'),
					'description' => __('Inspect, list, and delete Pagelayer pages.', 'pagelayer'),
				],
				[
					'label'       => __('Validate Page Layout', 'pagelayer'),
					'description' => __('AI validation engine for widget compatibility, hierarchy, responsiveness, accessibility, and SEO.', 'pagelayer'),
				],
				[
					'label'       => __('Duplicate / Publish Page', 'pagelayer'),
					'description' => __('Clone pages with fresh element IDs and publish drafts.', 'pagelayer'),
				],
			],
			__('Individual Posts Management', 'pagelayer') => [
				[
					'label'       => __('Create Post', 'pagelayer'),
					'description' => __('Creates a new blog post built with Pagelayer, specifying categories, tags, excerpt, and featured image.', 'pagelayer'),
				],
				[
					'label'       => __('Update Post', 'pagelayer'),
					'description' => __('Updates post layout, categories, tags, excerpt, and status.', 'pagelayer'),
				],
				[
					'label'       => __('Get / List / Delete Posts', 'pagelayer'),
					'description' => __('Inspect, list, publish, duplicate, and delete blog posts.', 'pagelayer'),
				],
			],
			__('Widgets & Schemas', 'pagelayer') => [
				[
					'label'       => __('List Widgets', 'pagelayer'),
					'description' => __('List all registered Pagelayer widgets, display names, categories, icons, and nesting rules.', 'pagelayer'),
				],
				[
					'label'       => __('Get Widget Schema & Examples', 'pagelayer'),
					'description' => __('Retrieve control property schema and canonical pagelayer_data node examples.', 'pagelayer'),
				],
			],
			__('Theme Templates', 'pagelayer') => [
				[
					'label'       => __('Get / Create Theme Templates', 'pagelayer'),
					'description' => __('Build header, footer, blog archive, single blog, search, 404, sidebar, popup, and WooCommerce templates.', 'pagelayer'),
				],
				[
					'label'       => __('Update / Delete Templates', 'pagelayer'),
					'description' => __('Update or delete theme templates and display condition rules.', 'pagelayer'),
				],
			],
			__('Navigation Menus', 'pagelayer') => [
				[
					'label'       => __('Get / Create Menus', 'pagelayer'),
					'description' => __('Build the WordPress nav menus that the Primary Menu widget renders in headers, including Mega Menu dropdowns and theme location assignment.', 'pagelayer'),
				],
			],
			__('Global Design System', 'pagelayer') => [
				[
					'label'       => __('Get Theme Settings & Styles', 'pagelayer'),
					'description' => __('Retrieve theme options, global colors/fonts, content width, and WooCommerce status.', 'pagelayer'),
				],
				[
					'label'       => __('Presets & Catalogs', 'pagelayer'),
					'description' => __('Get color palette presets, spacing scale presets, FontAwesome icon catalog, and font catalog.', 'pagelayer'),
				],
				[
					'label'       => __('Search Real Images', 'pagelayer'),
					'description' => __('Search Pexels by keyword for real, topically relevant photos to use in place of placeholders (requires a Pexels API key, configured below).', 'pagelayer'),
				],
			],
		];
	}

	static function get_installed_mcp_adapter_file(){
		if(!function_exists('get_plugins')){
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach(array_keys(get_plugins()) as $plugin_file){
			if(0 === strpos($plugin_file, 'mcp-adapter/')){
				return $plugin_file;
			}
		}
		return '';
	}

	static function current_user_has_mcp_app_password(){
		$user_id = get_current_user_id();

		if(!$user_id || !class_exists('WP_Application_Passwords')){
			return false;
		}

		foreach(\WP_Application_Passwords::get_user_application_passwords($user_id) as $app){
			if(!empty($app['app_id']) && $app['app_id'] === self::$APP_PASSWORD_APP_ID){
				return true;
			}
		}
		return false;
	}

	static function get_test_connection_status(){
		$user_id = get_current_user_id();
		if(!$user_id){
			return ['ok' => false, 'message' => ''];
		}
		$stored = get_user_meta($user_id, self::$TEST_STATUS_META, true);
		if(!is_array($stored)){
			return ['ok' => false, 'message' => ''];
		}
		return [
			'ok' => !empty($stored['ok']),
			'message' => !empty($stored['message']) ? $stored['message'] : '',
		];
	}

	public static function save_test_connection_status($ok, $message){
		$user_id = get_current_user_id();
		if(!$user_id){
			return;
		}
		update_user_meta($user_id, self::$TEST_STATUS_META, [
			'ok' => !empty($ok),
			'message' => (string)$message,
		]);
	}

	static $MCP_ADAPTER_DOWNLOAD_URL = 'https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip';
	static $MCP_ADAPTER_GITHUB_API = 'https://api.github.com/repos/WordPress/mcp-adapter/releases/latest';

	static function get_mcp_adapter_download_url(){
		$response = wp_remote_get(self::$MCP_ADAPTER_GITHUB_API, array(
			'headers' => array(
				'Accept' => 'application/vnd.github+json',
				'User-Agent' => 'Pagelayer/' . PAGELAYER_VERSION,
			),
			'timeout' => 30,
		));

		if(is_wp_error($response)){
			return self::$MCP_ADAPTER_DOWNLOAD_URL;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if(is_array($data) && !empty($data['assets'])){
			foreach($data['assets'] as $asset){
				if(!empty($asset['browser_download_url']) && false !== stripos($asset['name'], '.zip')){
					return $asset['browser_download_url'];
				}
			}
		}

		return self::$MCP_ADAPTER_DOWNLOAD_URL;
	}
}
