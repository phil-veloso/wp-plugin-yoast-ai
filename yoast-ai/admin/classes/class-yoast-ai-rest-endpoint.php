<?php

use \Yoast_AI_Encryption as Secret;

if (!class_exists('Yoast_AI_REST_Endpoint')) {

	class Yoast_AI_REST_Endpoint extends WP_REST_Controller
	{

		public function __construct()
		{
			// add_action('wp_ajax_yoast_ai_generate_data', array($this, 'generate_data'));
			add_action('rest_api_init', array($this, 'register_routes'));
		}

		/**
		 * Register endpoints with WP routes
		 *
		 * @return void
		 */
		function register_routes()
		{
			// wp-json/yoastai/v2/generate
			register_rest_route('yoastai/v2', '/generate/', array(
				'methods'  				=> WP_REST_Server::EDITABLE,
				'callback' 				=> array($this, 'generate_data'),
				'permission_callback' 	=> array($this, 'check_valid_user'),
			));

			// wp-json/yoastai/v2/save
			register_rest_route('yoastai/v2', '/save/', array(
				'methods'  				=> WP_REST_Server::EDITABLE,
				'callback' 				=> array($this, 'save_data'),
				'permission_callback' 	=> array($this, 'check_valid_user'),
			));
		}

		/**
		 * Only allow users who are logged in to access api.
		 *
		 * @return bool
		 */
		public function check_valid_user()
		{
			return is_user_logged_in() && current_user_can('edit_posts');
		}

		/**
		 * API method for generating meta suggestions
		 *
		 * @param WP_REST_Request $request
		 * @return WP_REST_Response
		 */
		function generate_data(WP_REST_Request $request)
		{
			if (!isset($request['post_id'])) {
				return new WP_REST_Response('Post ID missing', 403);
			}

			$post_id = filter_var(json_decode($request['post_id']), FILTER_SANITIZE_NUMBER_INT);
			$fields = array(
				'title',
				'description'
			);
			$result = array();
			foreach ($fields as $field) {
				$response = self::generate_response($post_id, $field);
				$result[$field] = array(
					'status' 	=> is_wp_error($response) ? 'error' : 'success',
					'value'		=> is_wp_error($response) ? $response->get_error_message() :  trim($response, '"'),
				);
			}
			return new WP_REST_Response($result, 200);
		}

		/**
		 * Fetch suggestions from external API
		 *
		 * @param int $post_id
		 * @param string $field
		 * @return object
		 */
		function generate_response($post_id, $field)
		{
			$suggestion	= '';

			$data_encryption = new Secret();
			$openai_api_key = $data_encryption->decrypt(get_option('openai_key'));

			if (empty($openai_api_key)) {
				return new WP_Error('error', 'Please enter an API key in settings to use this feature', array('status' => 403));
			}

			$field_values = array(
				'title' => array(
					'key' => 'post_title',
					'count' => 60,
				),
				'description' => array(
					'key' => 'post_content',
					'count' => 120,
				),
			);

			$context 	= get_post_field($field_values[$field]['key'], $post_id);
			$prompt 	= sprintf("Write an SEO optimised website meta %s using less than %s characters, for the following text: %s", $field_values[$field]['key'], $field_values[$field]['count'], $context);

			$data = array(
				'model' => 'gpt-3.5-turbo',
				"messages" => array(
					array(
						"role" => "user",
						"content" => $prompt,
					),
				),
				'max_tokens' => 100,
				'temperature' => 0.3,
			);

			$url = 'https://api.openai.com/v1/chat/completions';
			$headers = array(
				'Content-Type: application/json',
				'Authorization: Bearer ' . $openai_api_key
			);

			// error_log(print_r($data, true));
			$data_string = json_encode($data);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_TIMEOUT, 20);

			$response = json_decode(curl_exec($ch));
			curl_close($ch);
			// error_log(print_r($response, true));

			if (isset($response->error)) {
				error_log(print_r($response->error, true));
				return new WP_Error('error', $response->error->message, array('status' => 403));
			}

			if (empty($response)) {
				return new WP_Error('error', 'Currently unable to generate suggestion, please try again later.', array('status' => 403));
			}

			if (property_exists($response, 'choices')) {
				$suggestion = $response->choices[0]->message->content;
				// $tokens	= $response->usage->total_tokens;
				// $cost 	= $tokens * 0.000002;
			}

			return $suggestion;
		}


		/**
		 * API function to save post data from request.
		 *
		 * @param WP_REST_Request $request
		 * @return WP_REST_Response
		 */
		function save_data(WP_REST_Request $request)
		{
			$post_id = filter_var($request['post_id'], FILTER_SANITIZE_NUMBER_INT);
			$title = filter_var($request['title'], FILTER_UNSAFE_RAW);
			$description = filter_var($request['description'], FILTER_UNSAFE_RAW);

			$data = array(
				'title' => $title,
				'description' => $description,
			);

			$result = array();
			foreach ($data as $field => $value) {
				$updated = self::update_yoast_seo_fileds($post_id, $field, $value);
				$result[$field] = is_wp_error($updated) ? $updated->get_error_message() : $updated;
			}

			return new WP_REST_Response($result, 200);
		}

		/**
		 * Update post meta fields
		 *
		 * @param int $post_id
		 * @param string $field
		 * @param string $value
		 * @return string
		 */
		function update_yoast_seo_fileds($post_id, $field, $value)
		{
			$field_values = array(
				'title' => array(
					'key' => 'title',
				),
				'description' => array(
					'key' => 'metadesc',
				),
			);
			if (!array_key_exists($field, $field_values)) {
				return new WP_Error('error', 'field key does not exist');
			}

			$field_name = sprintf('_yoast_wpseo_%s', $field_values[$field]['key']);
			$updated_field = update_post_meta($post_id, $field_name, trim($value, '"'));

			if (false === $updated_field) {
				return new WP_Error('error', 'failure or value already in database');
			}

			return $value;
		}
	}
}
