<?php

/**
 * Plugin Name:       Yoast AI
 * Plugin URI:        https://github.com/phil-veloso/wp-plugin-yoast-ai
 * Description:       Use AI to generate Yoast SEO meta data
 * Version:           0.1.0
 * Author:            Phil Veloso
 * Author URI:        https://philveloso.com
 * Text Domain:       yoastai
 */

if (!class_exists('Yoast_AI')) {

	if ( !is_plugin_active('wordpress-seo/wp-seo.php') || !is_plugin_active('wordpress-seo-premium/wp-seo-premium.php') ) {
		exit();
	}

	// Setup
	define('YOAST_AI_DIR', plugin_dir_path(__FILE__));

	require_once(YOAST_AI_DIR . '/admin/classes/class-yoast-ai-encryption.php');

	class Yoast_AI
	{

		public function __construct()
		{
			$this->register_autoloads();
			$this->register_admin_page();
			$this->register_meta_box();
			$this->register_api();
		}

		private function register_autoloads()
		{
			spl_autoload_register(function ($name) {
				$name = strtolower($name);
				$name = str_replace('_', '-', $name);
				$name = 'class-' . $name;
				$file = __DIR__ . '/admin/classes/' . $name . '.php';
				if (file_exists($file)) {
					require_once $file;
				}
			});
		}

		public function register_admin_page()
		{
			new Yoast_AI_Admin_page();
		}

		public function register_meta_box()
		{
			new Yoast_AI_Meta_Box();
		}

		public function register_api()
		{
			new Yoast_AI_REST_Endpoint();
		}
	}
	new Yoast_AI();
}
