<?
if (!class_exists('Yoast_AI_Meta_Box')) {

	class Yoast_AI_Meta_Box
	{
		public function __construct()
		{
			add_action('add_meta_boxes', array($this, 'add_custom_box'));
			add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		}

		function add_custom_box()
		{
			$screens = ['post'];
			foreach ($screens as $screen) {
				add_meta_box(
					'yoast_ai_meta',
					'Yoast AI ',
					array($this, 'meta_box_html'),
					$screen,
					'advanced',
					'default',
				);
			}
		}

		function meta_box_html($post)
		{

			$api_key = get_option('openai_key');
			if ( empty( $api_key ) ) {
				echo "Please add valid api key in Tools -> Yoast AI Settings";
				return;
			}

			// https://yoast.com/developer-blog/yoast-seo-14-0-using-yoast-seo-surfaces/#seo-data
			$title_limit = 65;
			$meta_title = YoastSEO()->meta->for_post($post->ID)->title;

			$desc_limit = 155;
			$meta_desc = YoastSEO()->meta->for_post($post->ID)->description;

?>
			<div class="yoast-ai-metabox">
				<label class="yoast-ai-label">Use AI to generate Yoast SEO meta data.</label>
				<hr>
				<div class="yoast-ai-suggestions">
					<div class="yoast-ai-meta-title-wrapper">
						<label for="meta-title">Meta Title:</label>
						<input id="yoast-ai-meta-title" type="text" name="meta-title" minlength="4" maxlength="<?php echo $title_limit; ?>" value="<?php echo !empty($meta_title) ? htmlspecialchars($meta_title) : ''; ?>" placeholder="Automatically Generated">
						<span class="character-count" data-limit="<?php echo $title_limit; ?>" data-target="yoast-ai-meta-title"></span>
						<div class=" yoast-ai-status">
						</div>
					</div>
					<div class="yoast-ai-meta-description-wrapper">
						<label for="meta-description">Meta Description:</label>
						<textarea id="yoast-ai-meta-description" name="meta-description" rows="2" minlength="4" maxlength="<?php echo $desc_limit; ?>" placeholder="Automatically Generated"><?php echo !empty($meta_desc) ? $meta_desc : ''; ?></textarea>
						<span class="character-count" data-limit="<?php echo $desc_limit; ?>" data-target="yoast-ai-meta-description"></span>
						<div class="yoast-ai-status">
						</div>
					</div>
				</div>
				<hr>
				<div id="yoast-ai-actions">
					<div id="yoast-ai-message"></div>
					<span class="spinner"></span>
					<input id="yoast-ai-post" type="hidden" value="<?php echo $post->ID; ?>" />
					<input id="yoast-ai-generate" type="button" class="button-primary" value="Generate">
					<input id="yoast-ai-save" type="button" class="button-primary" value="Save Data" disabled="true">
				</div>
			</div>
<?php
		}

		function enqueue_assets($hook)
		{
			if ('post.php' != $hook) {
				return;
			}

			$script      = 'admin/assets/main.js';
			$script_file  = JOAST_AI_DIR . '/' . $script;
			if (file_exists($script_file)) {
				wp_enqueue_script('yoast-ai', JOAST_AI_ADMIN_URL . $script, array(), filemtime($script_file), true);

				wp_localize_script(
					'yoast-ai',
					'yoastAiRequests',
					array(
						'urlGenerate' => sprintf('%s/wp-json/yoastai/v2/generate', get_site_url()),
						'urlSave' => sprintf('%s/wp-json/yoastai/v2/save', get_site_url()),
						'nonce' => wp_create_nonce('wp_rest'),
					)
				);
			}

			$style      = 'admin/assets/main.css';
			$style_file = JOAST_AI_DIR . '/' . $style;
			if (file_exists($style_file)) {
				wp_enqueue_style('yoast-ai', JOAST_AI_ADMIN_URL . $style, array(), filemtime($style_file));
			}
		}
	}
}
