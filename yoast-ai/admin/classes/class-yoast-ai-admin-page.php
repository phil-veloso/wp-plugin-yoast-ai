<?php

if (!class_exists('Yoast_AI_Admin_page')) {

	class Yoast_AI_Admin_page
	{
		public function __construct()
		{
			add_action('admin_menu', array($this, 'add_menus'));
			add_action('admin_post_openaikey_external_api', array($this, 'submit_api_key'));
		}

		function add_menus()
		{
			add_submenu_page(
				'tools.php', 					// Add our page under the "Tools" menu
				'Yoast AI Settings', 		// Title in menu
				'Yoast AI Settings', 		// Page title
				'administrator', 				// permissions
				'yoast-ai-settings', 			// slug for our page
				array($this, 'admin_page'), 	// Callback to render the page
				null
			);
		}

		function admin_page()
		{

			$data_encryption = new Yoast_AI_Encryption();
			$api_key = get_option('openai_key');
			if ($api_key) {
				$api_key = $data_encryption->decrypt( $api_key );
			} 
		
			echo '<div class="wrap">';
			echo '<h2>API key settings</h2>';

			// Check if status is 1 which means a successful options save just happened
			if (isset($_GET['status']) && $_GET['status'] == 1) {
				echo '<div class="notice notice-success inline">';
				echo '<p>Options Saved!</p>';
				echo '</div>';
			}

			echo sprintf('<form action="%s" method="POST">', esc_url(admin_url('admin-post.php')));
			echo '<h3>Your API Key</h3>';
			// The nonce field is a security feature to avoid submissions from outside WP admin
			echo wp_nonce_field('openaikey_api_options_verify');
			echo sprintf('<input type="password" name="openai_key" placeholder="Enter API Key" value="%s" style="width:400px;">', $api_key ? esc_attr($api_key) : '');
			echo '<input type="hidden" name="action" value="openaikey_external_api">';
			echo '<input type="submit" name="submit" id="submit" class="update-button button button-primary" value="Update API Key" />';
			echo '</form>';

			echo '</div>';
		}

		// Submit functionality
		function submit_api_key()
		{

			// Make sure user actually has the capability to edit the options
			if (!current_user_can('edit_theme_options')) {
				wp_die("You do not have permission to view this page.");
			}

			// pass in the nonce ID from our form's nonce field - if the nonce fails this will kill script
			check_admin_referer('openaikey_api_options_verify');

			if (isset($_POST['openai_key'])) {

				$data_encryption = new Yoast_AI_Encryption();
				$submitted_api_key = sanitize_text_field($_POST['openai_key']);
				$api_key = $data_encryption->encrypt($submitted_api_key);

				$api_exists = get_option('openai_key');

				if (!empty($api_key) && !empty($api_exists)) {
					update_option('openai_key', $api_key);
				} else {
					add_option('openai_key', $api_key);
				}
			}
			// Redirect to same page with status=1 to show our options updated banner
			wp_redirect($_SERVER['HTTP_REFERER'] . '&status=1');
		}

	}
}
