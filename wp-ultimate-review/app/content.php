<?php

namespace WurReview\App;

defined('ABSPATH') || exit;

use WP_Query;
use WurReview\App\Settings as Settings;
use WurReview\Helper\Helper;

/**
 * Class Name : Content Meta Box - Custom Post Type
 * Class Type : Normal class
 * Class Description: show meta box data in front page - Post, Page, Product
 *
 * initiate all necessary classes, hooks, configs
 *
 * @since 1.0.0
 * @access Public
 */
class Content {

	private static $instance;
	private $getPostType;
	private $getPostId;
	private $post_type;
	private $controls;
	
	/**
	 * Construct the content meta box object
	 * @since 1.0.0
	 * @access public
	 */
	public function init(array $controls, $post_type) {

		// Declear public controls
		$this->controls = $controls;

		// Declear public post type
		$this->post_type = $post_type;


		// Load script and Css file for content page
		add_action('wp_enqueue_scripts', [$this, 'wur_content_script_loader']);

		// add review form and list in the content
		$return_data_display_setting = get_option('xs_review_display', '');

		$content_Position = isset($return_data_display_setting['review_location']) ? $return_data_display_setting['review_location'] : 'after_content';

		if($content_Position != 'custom_code') {
			add_filter('the_content', [$this, 'wur_meta_box_content_view']);
		}

		// Save meta review data for public
		add_action('init', [$this, 'wur_meta_box_content_save']);

		// add shortcode options
		add_shortcode('wp-reviews', [$this, 'wur_review_kit_forms_shortcode']);

		// add shortcode for rating
		add_shortcode('wp-reviews-rating', [$this, 'wur_review_kit_shortcode_rating']);
	}


	/**
	 * Return the content for front-end
	 *
	 * Review wur_meta_box_content_view.
	 * Method Description: Review form show in content
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function wur_meta_box_content_view($content) {

		if(is_admin()) {
			return  $content;
		}

		if(is_front_page() || is_home() || is_archive()) {
			return $content;
		}

		global $post;

		if(!is_object($post) || !property_exists($post, 'ID')) {
			return $content;
		}

		if( post_password_required( $post ) ) {
			return $content;
		}


		// Avada Theme conflict check
		$theme = wp_get_theme();
		$is_active_theme = ($theme->name == 'Avada');

		// Apply main query/loop checks only for Avada
		if ( $is_active_theme && !in_the_loop() ) {
			return $content;
		}

		$wur_settings = new Wur_Settings();
		$wur_settings->load();

		$display_setting = $wur_settings->getDisplaySettings();

		if($wur_settings->is_review_enable_for_post_type($post->post_type)) {

			$content_Position = isset($display_setting['review_location']) ? $display_setting['review_location'] : 'after_content';

			// WooCommerce products show the reviews in the product "Reviews" tab instead (see Woocommerce class)
			if(Woocommerce::is_product_tab_display($post, $content_Position)) {
				return $content;
			}

			$review_content = $this->get_review_content($post);


			if($content_Position == 'after_content') {
				return $content . $review_content;
			}

			if($content_Position == 'before_content') {
				return $review_content . $content;
			}

			if($content_Position == 'custom_code') {
				return $review_content;
			}
		}


		return $content;
	}


	public function get_review_content($post) {

		// output for display settings. Get from options
		$this->getPostType = $post->post_type;
		$this->getPostId   = $post->ID;


		/**
		 * Loading the saved settings from database
		 *
		 */
		$wur_settings = new Wur_Settings();
		$wur_settings->load();


		$display_setting  = $wur_settings->getDisplaySettings();
		$global_setting   = $wur_settings->getGlobalSettings();
		$post_review_meta = Wur_Settings::get_xs_post_meta($this->getPostId, 'xs_review_overview_settings');

		$review_content = '';

		if($wur_settings->is_author_review_enabled()) {

			$post_review_meta = Wur_Settings::get_xs_post_meta($this->getPostId);

			if(!empty($post_review_meta->overview->enable)) {

				ob_start();

				require(WUR_REVIEW_PLUGIN_PATH . 'views/public/meta-box-author-review.php');

				$author_content = ob_get_contents();
				ob_end_clean();

				$review_content .= $author_content;
			}

		}


		if($wur_settings->is_user_review_enabled()) {

			ob_start();

			require(WUR_REVIEW_PLUGIN_PATH . 'views/public/meta-box-user-review.php');

			$author_content = ob_get_contents();
			ob_end_clean();

			$review_content .= $author_content;
		}

		return $review_content;
	}


	/**
	 * Neutralise any shortcode syntax in user-submitted review content.
	 *
	 * Escapes the shortcode delimiters "[" and "]" to their HTML entities so
	 * the value can never be parsed as a shortcode by do_shortcode(), no matter
	 * which shortcodes are registered or when. This is deliberately used instead
	 * of strip_shortcodes(), which only removes a fixed set of known tags in a
	 * single pass and is bypassable (split-tag re-forming, late-registered tags).
	 *
	 * @since 2.4.4
	 * @access protected
	 * @param string $value Raw review text.
	 * @return string Text with shortcode brackets escaped.
	 */
	protected function wur_neutralise_shortcodes( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), $value );
	}


	/**
	 * Review wur_meta_box_content_save.
	 * Method Description: Save review information in DB
	 * @since 1.0.0
	 * @access public
	 */
	public function wur_meta_box_content_save() {

		if(!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])),"meta-box-review-nonce")) {
			return false;
		}


		$content_meta_key = 'xs_submit_review_data';
		
		if(isset($_POST['xs_review_form_public_data']) ) {

			$wur_settings = new Wur_Settings();
			$wur_settings->load();

			if( Application::pro_version_exist() && $wur_settings->is_captcha_enabled() ){
				$secret_key = $wur_settings->get_captcha_secret_key();
				$response_token = isset($_POST['g-recaptcha-response']) ? Settings::sanitize($_POST['g-recaptcha-response']) : '';
	
				if( $wur_settings->get_captcha_version() === 'recaptcha-v3'){
					$response_token = isset($_POST['g-recaptcha-response-v3']) ? Settings::sanitize($_POST['g-recaptcha-response-v3']) : '';
				}
				
				if( !$this->is_captcha_valid($secret_key, $response_token) ){
					$_SESSION['xs_review_message'] = __('reCaptcha is not valid!', 'wp-ultimate-review');

					return false;
				}
			}
			
			// GDPR: reject the review when consent is required but not given
			if($wur_settings->is_gdpr_consent_enabled() && (!isset($_POST['xs_review_gdpr_consent']) || sanitize_text_field(wp_unslash($_POST['xs_review_gdpr_consent'])) !== 'yes')) {
				$_SESSION['xs_review_message'] = __('Please accept the privacy consent to submit your review', 'wp-ultimate-review');

				return false;
			}

			// get meta content data for review
			$metaReviewData = isset($_POST[$content_meta_key]) ? $_POST[$content_meta_key] : []; //phpcs:ignore sanitization done in array with self custom function
			$metaReviewData = Settings::sanitize($metaReviewData);

			// Security: derive post ID from the actual requested URL rather than trusting
			// the client-submitted hidden field, which is trivially forgeable.
			$request_uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$server_post_id = absint( url_to_postid( home_url( $request_uri ) ) );
			if ( $server_post_id > 0 ) {
				// Cast to string so json_encode produces "xs_post_id":"5" (quoted),
				// matching the LIKE query format used by the frontend review list.
				$metaReviewData['xs_post_id']   = (string) $server_post_id;
				$metaReviewData['xs_post_type'] = get_post_type( $server_post_id );
			}

			// Security: always use the actual current user ID — never the client-supplied
			// value, which could be set to an arbitrary user to spoof identity / leak PII.
			$metaReviewData['xs_post_author'] = get_current_user_id();

			$main_post_id = isset($metaReviewData['xs_post_id']) ? absint($metaReviewData['xs_post_id']) : false;

			//If someone try bypassing review (without login on private post) or (without password on protected post) then return false
			if( $main_post_id == false || post_password_required( $main_post_id ) || ( $this->is_review_from_private_post( $main_post_id ) && !current_user_can('read_post', $main_post_id)) ){
				
				return false;
			}		
				
			if(is_array($metaReviewData) and sizeof($metaReviewData) > 0) :
				
				// require data
				// get display settings data
				$return_data_display_setting = get_option('xs_review_display', '');

				/**
				 * Criteria based review (pro).
				 *
				 * The criteria names always come from the settings, never from the submitted form,
				 * and every rating is clamped to the score limit. The average of the criteria is
				 * stored as the overall rating, so the review list, the rating shortcode and the
				 * WooCommerce product rating keep working without any change.
				 */
				$criteria_context = $wur_settings->get_criteria_review_context(get_post_type($main_post_id));

				if(!empty($criteria_context['criteria'])) {

					$submitted_criteria = (isset($metaReviewData['xs_criteria_ratings']) && is_array($metaReviewData['xs_criteria_ratings'])) ? $metaReviewData['xs_criteria_ratings'] : [];
					$criteria_ratings   = [];
					$criteria_sum       = 0;
					$criteria_limit     = (int)$criteria_context['score_limit'];

					foreach($criteria_context['criteria'] as $criteria_index => $criteria_name) {

						$submitted_rating = isset($submitted_criteria[$criteria_index]['rating']) ? $submitted_criteria[$criteria_index]['rating'] : null;

						if(!is_scalar($submitted_rating) || !is_numeric($submitted_rating)) {
							continue;
						}

						$criteria_rating = (int)$submitted_rating;

						if($criteria_rating < 1) {
							continue;
						}

						$criteria_rating = min($criteria_rating, $criteria_limit);

						$criteria_ratings[] = ['name' => $criteria_name, 'rating' => (string)$criteria_rating];
						$criteria_sum       += $criteria_rating;
					}

					// at least one criterion has to be rated, the rest may be skipped
					if(empty($criteria_ratings)) {
						$_SESSION['xs_review_message'] = __('Please rate at least one criterion before submitting your review', 'wp-ultimate-review');

						return false;
					}

					$metaReviewData['xs_criteria_ratings'] = $criteria_ratings;
					$metaReviewData['xs_reviwer_ratting']  = (string)round($criteria_sum / count($criteria_ratings), 2);

				} else {
					// nothing submitted through the form can fake criteria data while the feature is off
					unset($metaReviewData['xs_criteria_ratings']);
				}


				foreach($this->controls as $requireKey => $requireValue) :
					$checkEnable = (isset($return_data_display_setting['form'][$requireKey]) && $return_data_display_setting['form'][$requireKey] == 'Yes') ? 'Yes' : 'No';
					if($checkEnable == 'Yes') :
						if((isset($requireValue['require']) ? $requireValue['require'] : 'No') == 'Yes') :
							$checkDataRequire = trim(isset($metaReviewData[$requireKey]) ? $metaReviewData[$requireKey] : '');
							if(strlen($checkDataRequire) == 0) {
								$_SESSION['xs_review_message'] = __('Please fill up all required fields', 'wp-ultimate-review');

								return false;
							}
						endif;
					endif;
				endforeach;

				// checking review limit start
				
				$global_setting = $wur_settings->getGlobalSettings();

				$review_user_limit    = isset($global_setting['review_user_limit']) ? (int)$global_setting['review_user_limit'] : 1;
				$review_user_limit_by = isset($global_setting['review_user_limit_by']) ? $global_setting['review_user_limit_by'] : 'email';

				$re_review = false;

				$ip = Helper::ip_address();

				if($review_user_limit_by == 'browser') {

					$post_limit_cookie_name = 'xs_review_user_post_limit_' . absint($metaReviewData['xs_post_id']);

					if(isset($_COOKIE[$post_limit_cookie_name])) {

						$post_limit_cookie = sanitize_text_field(wp_unslash($_COOKIE[$post_limit_cookie_name])) ;

						$post_limit_cookie = (array)json_decode(str_replace('\\', '', $post_limit_cookie));

						if(!empty($post_limit_cookie) && $review_user_limit > $post_limit_cookie['limit']) {

							$post_limit_cookie['limit'] = $post_limit_cookie['limit'] + 1;

							$re_review = true;
						}
					} else {
						$re_review = true;

						$post_limit_cookie = ['post_id' => absint($metaReviewData['xs_post_id']), 'limit' => 1];
					}
				} else {
					if($review_user_limit_by == 'email') {
						$args_type = [
							'key'   => 'xs_reviwer_email',
							'value' => isset($metaReviewData['xs_reviwer_email']) ? sanitize_email($metaReviewData['xs_reviwer_email']) : filter_var($ip, FILTER_VALIDATE_IP),
						];
					} else {
						$args_type = [
							'key'   => 'xs_reviews_ip',
							'value' => filter_var($ip, FILTER_VALIDATE_IP),
						];
					}
					$args    = [
						'post_type'  => 'xs_review',
						'meta_query' => [
							[
								'key'   => 'xs_main_post_id',
								'value' => absint($metaReviewData['xs_post_id']),
							],
							$args_type,
						],
					];
					$reviews = new WP_Query($args);

					if($review_user_limit > count($reviews->posts)) {
						$re_review = true;
					}
				}

				// checking review limit end

				if($re_review === true) {
					// output for global settings
					$return_data_global_setting           = get_option('xs_review_global');
					$metaReviewData['review_score_style'] = isset($return_data_global_setting['review_score_style']) ? $return_data_global_setting['review_score_style'] : 'star';
					$metaReviewData['review_score_limit'] = isset($return_data_global_setting['review_score_limit']) ? $return_data_global_setting['review_score_limit'] : 5;
					$metaReviewData['review_score_input'] = isset($return_data_global_setting['review_score_input']) ? $return_data_global_setting['review_score_input'] : 'star';
					$metaReviewData['xs_reviews_ip']      =  filter_var($ip, FILTER_VALIDATE_IP);
					
					if ( isset( $metaReviewData['xs_reviwer_ratting'] ) ) {
						if ( ! is_numeric( $metaReviewData['xs_reviwer_ratting'] ) ) {
							$metaReviewData['xs_reviwer_ratting'] = 0;
						}
						$metaReviewData['xs_reviwer_ratting'] = (float) $metaReviewData['xs_reviwer_ratting'];
						$score_limit_f = (float) $metaReviewData['review_score_limit'];
						if ( $metaReviewData['xs_reviwer_ratting'] < 0 ) {
							$metaReviewData['xs_reviwer_ratting'] = 0;
						}
						if ( $metaReviewData['xs_reviwer_ratting'] > $score_limit_f ) {
							$metaReviewData['xs_reviwer_ratting'] = $score_limit_f;
						}
					}
					
					// create array for save post data in post table
					$postarr                 = [];
					// Security: neutralise shortcodes in attacker-controlled review content before
					// storing it as post_content. The xs_review CPT is publicly_queryable, so
					// WordPress core's the_content filter runs do_shortcode() on this value for
					// anyone viewing the review — without neutralisation, a submitter (including an
					// anonymous/unauthenticated visitor when the public review form is enabled)
					// could execute arbitrary shortcodes.
					//
					// strip_shortcodes() alone is insufficient: it is a single, non-idempotent
					// regex pass over only the shortcodes registered at the moment it runs
					// (this handler is on init:10). It can be defeated by splitting a tag around a
					// copy of itself ([aud[audio]io ...] re-forms after the inner tag is removed)
					// and it does not touch shortcodes registered later than init:10. Instead we
					// escape the shortcode brackets outright, so no shortcode can ever form
					// regardless of registration timing. Brackets render as literal characters.
					$postarr['post_content'] = isset($metaReviewData['xs_reviw_summery']) ? $this->wur_neutralise_shortcodes( wp_kses( wp_specialchars_decode( sanitize_textarea_field($metaReviewData['xs_reviw_summery']) ), array() ) ) : '';
					$postarr['post_title']   = isset($metaReviewData['xs_reviw_title']) ? $this->wur_neutralise_shortcodes( sanitize_text_field($metaReviewData['xs_reviw_title']) ) : '';

					$text_meta_fields = array( 'xs_reviwer_name', 'xs_reviw_title', 'xs_reviw_summery', 'xs_reviwer_website' );
					foreach ( $text_meta_fields as $field_key ) {
						if ( isset( $metaReviewData[ $field_key ] ) && is_string( $metaReviewData[ $field_key ] ) ) {
							$metaReviewData[ $field_key ] = $this->wur_neutralise_shortcodes( $metaReviewData[ $field_key ] );
						}
					}

					if(!isset($return_data_global_setting['require_approval'])) :
						$postarr['post_status'] = 'publish';
					endif;

					if( $main_post_id && $this->is_review_from_private_post( $main_post_id ) ){

						$postarr['post_status'] = 'private';

					}else if( $main_post_id && $this->is_pass_protected_post( $main_post_id ) ){

						$postarr['post_password'] = $this->get_main_post_password( $main_post_id );
						
					}

					$postarr['post_type'] = $this->post_type;
					// insert post and return post id
					$getPostId = wp_insert_post($postarr, true);
					// check post id
					if(!empty($getPostId)) {
						// insert meta data for review
						$metaKey = 'xs_public_review_data';
						if(update_post_meta($getPostId, $metaKey, Settings::_encode_json_unicode($metaReviewData))) {
							update_post_meta($getPostId, 'xs_main_post_id', $metaReviewData['xs_post_id']);
							update_post_meta($getPostId, 'xs_reviwer_email', isset($metaReviewData['xs_reviwer_email']) ? $metaReviewData['xs_reviwer_email'] : $ip);
							update_post_meta($getPostId, 'xs_reviews_ip', $ip);

							// keep a record of when the reviewer gave GDPR consent
							if($wur_settings->is_gdpr_consent_enabled()) {
								update_post_meta($getPostId, 'xs_review_gdpr_consent', current_time('mysql', true));
							}

							$_SESSION['xs_review_message'] = __('Successfully submitted review', 'wp-ultimate-review');
							if($review_user_limit_by == 'browser') {
								setcookie($post_limit_cookie_name, json_encode($post_limit_cookie), (time() + 3600), site_url());
							}
							// email subject
							$post_title = isset($postarr['post_title']) ? sanitize_text_field($postarr['post_title']) : 'Review';
							$subject = sprintf(
								esc_html__('Review of %s', 'wp-ultimate-review'),
								$post_title
							);
							// email details
							$mailDetails = '';
							foreach($this->controls as $metaKeyFiled => $metaValueFiled) :
								if(isset($metaReviewData[$metaKeyFiled])) {
									$inputTitle  = (isset($metaValueFiled) and is_array($metaValueFiled) and array_key_exists('title_name', $metaValueFiled)) ? sanitize_text_field($metaValueFiled['title_name']): '';
										$mailDetails .= ' ' . sanitize_text_field($inputTitle) . ' : ' . sanitize_text_field($metaReviewData[$metaKeyFiled]) . "\n";
								}
							endforeach;

							// check adminstrator email enable and send email
							if((isset($return_data_global_setting['send_administrator']) ? $return_data_global_setting['send_administrator'] : 'No') == 'Yes') {
								$getAdminEmail = get_option('admin_email');
								wp_mail($getAdminEmail, $subject, $mailDetails);
							}
							// check user email enable and send email
							if((isset($return_data_global_setting['send_author']) ? $return_data_global_setting['send_author'] : 'No') == 'Yes') {
								$getUserEmail = isset($metaReviewData['xs_reviwer_email']) ? sanitize_email( $metaReviewData['xs_reviwer_email'] ) : '';
								if(strlen($getUserEmail) > 0) :
									wp_mail($getUserEmail, $subject, $mailDetails);
								endif;
							}

							return '';
						}
					}
				}

				$_SESSION['xs_review_message'] = __('Already submitted review', 'wp-ultimate-review');
			endif;
		}
	}

	/**
	 * Determines if the review is from a private post.
	 *
	 * @since 2.2.6
	 * @access public
	 * 
	 * @param int $post_id The ID of the post.
	 * @return bool Returns true if the review is from a private post, false otherwise.
	 */
	public function is_review_from_private_post( $post_id ){

		$post = get_post( $post_id );
		
		return $post->post_status == 'private' ? true : false;
	}

	/**
	 * Checks if a post is password protected.
	 *
	 * @since 2.2.6
	 * @access public
	 * 
	 * @param int $post_id The ID of the post to check.
	 * @return bool Returns true if the post is password protected, false otherwise.
	 */
	public function is_pass_protected_post( $post_id ){

		$post = get_post( $post_id );
		
		return !empty( $post->post_password ) ? true : false;
	}

	/**
	 * Retrieves the password for a given post.
	 *
	 * @since 2.2.6
	 * @access public
	 * 
	 * @param int $post_id The ID of the post.
	 * @return string The password for the post.
	 */
	public function get_main_post_password( $post_id ){

		$post = get_post( $post_id );
		
		return $post->post_password;
	}

	/**
	 * Check captcha
	 * 
	 * @since 2.2.0
	 * @access public
	 * @return boolean
	 */
	public function is_captcha_valid($secret_key, $response_token) {
		
		if ( empty($secret_key) || empty($response_token) ) {
			return false;
		}

		$data = array(
			'secret' => $secret_key,
			'response' => $response_token
		);

		$args = array(
			'body' => $data,
			'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
			'method' => 'POST',
		);
		
		$response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', $args);

		if (is_wp_error($response)) {
			return false;
		}
		
		$api_response = json_decode(wp_remote_retrieve_body($response));
		
		if (isset($api_response->success) && $api_response->success && !empty($response_token)) {
			return true;
		}
		
		return false;
	}

	/**
	 * Review wur_content_script_loader .
	 * Method Description: Content Script Loader
	 * @since 1.0.0
	 * @access public
	 */
	public function wur_content_script_loader() {

		/**
		 * Loading the saved settings from database
		 *
		 */
		$wur_settings = new Wur_Settings();
		$wur_settings->load();

		wp_enqueue_style('wur_content_css', WUR_REVIEW_PLUGIN_URL . 'assets/public/css/content-page.css', [], WUR_REVIEW_VERSION);

		wp_enqueue_script('wur_review_content_script', WUR_REVIEW_PLUGIN_URL . 'assets/public/script/content-page.js', ['jquery'], WUR_REVIEW_VERSION);

		wp_enqueue_style('dashicons');

		wp_register_script('wur_captcha_script',  WUR_REVIEW_PLUGIN_URL . 'assets/public/script/captcha-script.js', [], WUR_REVIEW_VERSION, true);
		wp_register_script('wur_captchav2_script', 'https://www.google.com/recaptcha/api.js', [], WUR_REVIEW_VERSION, true);

		if( $wur_settings->get_captcha_version() === 'recaptcha-v3' && $wur_settings->get_captcha_site_key() !== '') {
            wp_register_script('wur_captchav3_script', 'https://www.google.com/recaptcha/api.js?render=' . $wur_settings->get_captcha_site_key(), [], WUR_REVIEW_VERSION, false);
		}
	}


	public function wur_review_kit_forms_shortcode($atts, $content = null) {
		// create shortcode
		$atts = shortcode_atts(
			[
				'post-id' => 0,
				'class'   => '',
			], $atts, 'wp-reviews'
		);

		return $this->wur_review_kit_shortcode($atts);
	}


	/**
	 * Review wur_review_kit_shortcode .
	 * Method Description: shortcode for review kit
	 * @since 1.0.0
	 * @access public
	 */
	public function wur_review_kit_shortcode($atts = []) {

		$wur_settings = new Wur_Settings();
		$wur_settings->load();

		$global_setting   = $wur_settings->getGlobalSettings();

		$postId = (int)isset($atts['post-id']) ? $atts['post-id'] : 0;

		$className = isset( $atts['class'] ) ? sanitize_html_class( $atts['class'] ) : '';
		$reviewBox = isset($atts['overview']) ? $atts['overview'] : 'no';

		if($postId != 0) {
			$post              = get_post($postId);
			$this->getPostType = $post->post_type;
			$this->getPostId   = $post->ID;
		} else {
			global $post;
			$this->getPostType = $post->post_type;
			$this->getPostId   = $post->ID;
		}

		// get display settings data
		$return_data_display_setting = get_option('xs_review_display', '');
		// get global settings data
		$return_data_global_setting = get_option('xs_review_global');

		$review_location = isset( $return_data_display_setting['review_location'] ) ? $return_data_display_setting['review_location'] : 'after_content';
		if($review_location != 'custom_code') {
			if($postId == 0) {
				return '';
			}
		}

		$postId    = (int)isset($atts['post-id']) ? $atts['post-id'] : 0;
		$className = isset( $atts['class'] ) ? sanitize_html_class( $atts['class'] ) : '';
		$reviewBox = isset($atts['overview']) ? $atts['overview'] : 'no';

		if($postId != 0) {
			$post              = get_post($postId);
			$this->getPostType = $post->post_type;
			$this->getPostId   = $post->ID;
		} else {
			global $post;
			$this->getPostType = $post->post_type;
			$this->getPostId   = $post->ID;
		}

		// get display settings data
		$return_data_display_setting = get_option('xs_review_display', '');
		// get global settings data
		$return_data_global_setting = get_option('xs_review_global');

		$review_location = isset( $return_data_display_setting['review_location'] ) ? $return_data_display_setting['review_location'] : 'after_content';
		if($review_location != 'custom_code') {
			if($postId == 0) {
				return '';
			}
		}


		/**
		 * Settings from each page/post
		 */

		$metaDataOverviewJson = get_post_meta($this->getPostId, 'xs_review_overview_settings', true);

		if(empty($metaDataOverviewJson)) {
			$return_data_overview = [];

		} else {
			$return_data_overview = json_decode($metaDataOverviewJson);
		}

		/**
		 * AR:20201109
		 * After 1.2.2 enable review is removed and only cpt's are listed,
		 * so now we have to check only if tht list is empty or not -
		 * todo - one catch need to be checked if user can uncheck all cpt's, if not we need to move back again
		 */
		//if((isset($return_data_display_setting['page']['enable']) ? $return_data_display_setting['page']['enable'] : 'No') == 'Yes'):

			
		if(isset($return_data_display_setting['page']['data']) && in_array($this->getPostType, $return_data_display_setting['page']['data'])):

			// show per page
			$showPostNo = isset($return_data_display_setting['review_show_per']) ? $return_data_display_setting['review_show_per'] : 10;
			// Like query data
			$likeData = '"xs_post_id":"' . $this->getPostId . '"';
			// code for view review list
			$paged = isset($_GET['review_page']) ? sanitize_text_field(wp_unslash($_GET['review_page'])): 1;
			$args  = [
				'post_type'      => $this->post_type,
				'meta_query'     => [
					[
						'key'     => 'xs_public_review_data',
						'value'   => '' . $likeData . '',
						'compare' => 'LIKE',
					],
				],
				'orderby'        => [
					'post_date' => 'DESC',
				],
				'posts_per_page' => $showPostNo,
				'paged'          => $paged,
			];
			// query review list data
			$the_query = new \WP_Query($args);

			// content key for submit array index
			$content_meta_key = 'xs_submit_review_data';

			// total review count

			$argsTotal      = [
				'post_type'  => $this->post_type,
				'meta_query' => [
					[
						'key'     => 'xs_public_review_data',
						'value'   => '' . $likeData . '',
						'compare' => 'LIKE',
					],
				],
				'orderby'    => [
					'post_date' => 'DESC',
				],

			];
			$the_queryTotal = new \WP_Query($argsTotal);

			// content key for submit array index
			$content_meta_key = 'xs_submit_review_data';

			// start object
			ob_start();
			//require page for submit review form and review list
			require(WUR_REVIEW_PLUGIN_PATH . 'views/public/meta-box-view.php');
			$getContent = ob_get_contents();
			ob_end_clean();

			// end object content

			return $getContent;

			//endif;
		endif;
	}


	/**
	 * Review review_kit_shortcode_ratting .
	 * Method Description: shortcode for review kit for rattings
	 * @since 1.0.0
	 * @access public
	 */
	public function wur_review_kit_shortcode_rating($atts, $content = null) {

		// create shortcode
		$atts = shortcode_atts(
			[
				'post-id'       => 0,
				'ratting-show'  => 'yes',
				'ratting-style' => 'star',
				'count-show'    => 'yes',
				'vote-show'     => 'yes',
				'vote-text'     => 'Votes',
				'class'         => 'xs-ratting-content',
				'id'            => '',
			], $atts, 'wp-reviews-rating'
		);

		return $this->wur_review_kit_rating($atts);
	}


	public function wur_review_kit_rating($atts) {

		$postId       = (int)isset($atts['post-id']) ? $atts['post-id'] : 0;
		$rattingShow  = isset($atts['ratting-show']) ? $atts['ratting-show'] : 'yes';
		$rattingStyle = isset($atts['ratting-style']) ? $atts['ratting-style'] : 'star';
		$countShow    = isset($atts['count-show']) ? $atts['count-show'] : 'yes';
		$voteShow     = isset($atts['vote-show']) ? $atts['vote-show'] : 'yes';
    	$voteText     = isset($atts['vote-text']) ? sanitize_text_field($atts['vote-text']) : 'Votes';
		$return       = isset($atts['return-type']) ? $atts['return-type'] : '';

		$className = isset( $atts['class'] ) ? sanitize_html_class( $atts['class'] ) : '';
		$idName    = isset($atts['id']) ? $atts['id'] : '';
		if($postId > 0) {

			$likeData = '"xs_post_id":"' . $postId . '"';
			// content key for submit array index
			$args = [
				'nopaging'         => true,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'post_type'        => 'xs_review',
				'post_status'      => 'publish',
				'meta_query'       => [
					[
						'key'     => 'xs_public_review_data',
						'value'   => $likeData,
						'compare' => 'LIKE',
					],
				],
				'suppress_filters' => true,

			];

			$the_queryTotal = get_posts($args);

			$overViewTotal      = 0;
			$totalRattingsSum   = 0;
			$totalRattingsCount = 0;
			$rattingRatting     = 5;
			$overViewArray      = [];

			if(count($the_queryTotal) > 0) {
				foreach($the_queryTotal as $post) {

					$metaReviewID = $post->ID;
					$metaDataJson = get_post_meta($metaReviewID, 'xs_public_review_data', false);

					if(is_array($metaDataJson) && sizeof($metaDataJson) > 0) {
						$getMetaData = json_decode(end($metaDataJson));
					} else {
						$getMetaData = [];
					}

					$xs_reviwer_rattingOver = (double)isset($getMetaData->xs_reviwer_ratting) ? $getMetaData->xs_reviwer_ratting : '0';
					$reviwerStyleLimitOver  = (double)isset($getMetaData->review_score_limit) ? $getMetaData->review_score_limit : '5';

					$overViewArray['xs_reviwer_ratting'][] = $xs_reviwer_rattingOver;
					$overViewArray['review_score_limit'][] = $reviwerStyleLimitOver;

				}
				$defaultRatting = (isset($return_data_global_setting['review_score_limit']) && $return_data_global_setting['review_score_limit'] != '0') ? $return_data_global_setting['review_score_limit'] : '5';
				$rattingRatting = (double)max(isset($overViewArray['xs_reviwer_ratting']) ? $overViewArray['review_score_limit'] : $defaultRatting);

				// count same values in array. Return array by unique.
				$arrayCountValues = array_count_values($overViewArray['xs_reviwer_ratting']);

				$totalRattingsSum   = array_sum($overViewArray['xs_reviwer_ratting']);
				$totalRattingsCount = (int)count($overViewArray['xs_reviwer_ratting']);

				$overViewTotal = (double)round(($totalRattingsSum / $totalRattingsCount), 2);
			}

			if($return == 'only_avg') {
				return $overViewTotal;
			} elseif($return == 'total_ratting') {
				return (double)$totalRattingsSum;
			} elseif($return == 'total_review') {
				return $totalRattingsCount;
			} elseif($return == 'get_score_limit') {
				return $rattingRatting;
			} elseif($return == 'percentage') {
				$persentange = ($overViewTotal * 100) / $rattingRatting;

				return $persentange;
			} elseif($return == 'get_all') {
				$returnData                    = [];
				$returnData['only_avg']        = $overViewTotal;
				$returnData['total_ratting']   = (double)$totalRattingsSum;
				$returnData['total_review']    = $totalRattingsCount;
				$returnData['get_score_limit'] = $rattingRatting;
				$persentange                   = ($overViewTotal * 100) / $rattingRatting;
				$returnData['percentage']      = $persentange;

				return $returnData;
			}

			$contentRatting = '';
			if($overViewTotal > 0):
				$contentRatting .= '<div class="' . esc_attr('xs-ratting-content ' . $className) . '">';
				if($rattingShow == 'yes'):
					if($rattingStyle == 'point') {
						$contentRatting .= self::wur_ratting_view_point_per($overViewTotal, $rattingRatting);
					} elseif($rattingStyle == 'percentange') {
						$contentRatting .= self::wur_ratting_view_percentange_per($overViewTotal, $rattingRatting);
					} elseif($rattingStyle == 'pie') {
						$contentRatting .= self::wur_ratting_view_pie_per($overViewTotal, $rattingRatting);
					} else {
						$contentRatting .= self::wur_ratting_view_star_point($overViewTotal, $rattingRatting);
					}
				endif;
				if($countShow == 'yes'):
					$contentRatting .= '<span class="wp-ratting-number"> ' . esc_html( $totalRattingsCount ) . '  </span>';
				endif;
				if($voteShow == 'yes'):
					$contentRatting .= '<span class="wp-ratting-vote"> ' . esc_html( $totalRattingsCount ) . '  ' . esc_html($voteText) . '</span>';
				endif;
				$contentRatting .= '</div>';
			endif;

			return $contentRatting;
		}
	}


	/**
	 * Review review_kit_shortcode .
	 * Method Description: shortcode for review kit
	 * @since 1.0.0
	 * @access public
	 */
	public static function getStyles($key = '', $type = 'display') {
		// get display settings data
		$return_data_display_setting = get_option('xs_review_display', '');

		$styleData  = '';
		$styleData  .= 'color: ';
		$styleColor = (isset($return_data_display_setting['form'][$key][$type]['color']) && $return_data_display_setting['form'][$key][$type]['color'] != '') ? $return_data_display_setting['form'][$key][$type]['color'] : '#333333';
		$styleData  .= '' . $styleColor . '; ';
		$styleData  .= 'font-size: ';
		$styleSize  = (isset($return_data_display_setting['form'][$key][$type]['size']) && $return_data_display_setting['form'][$key][$type]['size'] != '') ? $return_data_display_setting['form'][$key][$type]['size'] : '';
		$styleData  .= '' . $styleSize . 'px; ';

		//return $styleData;
		return '';
	}


	/**
	 * Review wur_ratting_view_star_point . for star style of content view
	 * Method Description: this method use for ratting view in admin page
	 *
	 * @params $rat, get ratting value
	 * @params $max, limit score data
	 * @return ratting html data
	 * @since 1.0.0
	 * @access public
	 */
	/**
	 * Rating graph markup in the given style. Used by the free templates and by the pro plugin,
	 * which renders the criteria ratings of a review.
	 *
	 * @since 2.4.4
	 * @access public
	 *
	 * @param float|int $rat   the rating
	 * @param float|int $max   the score limit
	 * @param string    $style star, point, percentage or pie
	 * @return string html, escape it with wp_kses(..., Settings::kses(null, true)) before printing
	 */
	public static function wur_rating_graph_html($rat = 0, $max = 5, $style = 'star') {

		$rat = is_numeric($rat) ? (float)$rat : 0;
		$max = (is_numeric($max) && (float)$max > 0) ? (float)$max : 5;

		if($style === 'point') {
			return self::wur_ratting_view_point_per($rat, $max);
		}

		if($style === 'percentage') {
			return self::wur_ratting_view_percentange_per($rat, $max);
		}

		if($style === 'pie') {
			return self::wur_ratting_view_pie_per($rat, $max);
		}

		return self::wur_ratting_view_star_point($rat, $max);
	}


	private static function wur_ratting_view_star_point($rat = 0, $max = 5) {
		$rat = (float) $rat;
		$max = max( 1, (float) $max );
		$tarring = '';
		$tarring .= '<div class="xs-review-rattting">';
		$tarring .= '<span class="screen-rattting-text"> ' . esc_html(round($rat, 1)) . ' </span>';
		$halF    = 0;
		for($ratting = 1; $ratting <= $max; $ratting++):
			$rattingClass = 'dashicons-star-empty';
			if($halF == 1) {
				$rattingClass = 'dashicons-star-half';
				$halF         = 0;
			}
			if($ratting <= $rat) {
				$rattingClass = 'dashicons-star-filled';
				if($ratting == floor($rat)):
					$expLode = explode('.', $rat);
					if(is_array($expLode) && sizeof($expLode) > 1) {
						$halF = 1;
					}

				endif;
			}

			$tarring .= '<div style="' . self::getStyles('xs_reviwer_ratting_data') . '" class="xs-star dashicons-before ' . esc_html($rattingClass) . '" aria-hidden="true"></div>';
		endfor;
		$tarring .= '</div>';

		return $tarring;
	}


	/**
	 * Review wur_ratting_view_point_per . for point styles
	 * Method Description: this method use for ratting view in admin page
	 * @params $rat, get ratting value
	 * @params $max, limit score data
	 * @return ratting html data
	 * @since 1.0.0
	 * @access private
	 */
	private static function wur_ratting_view_point_per($rat = 0, $max = 5) {
		$rat = (float) $rat;
		$max = max( 1, (float) $max );
		$tarring   = '';
		$tarring   .= '<div class="xs-review-rattting xs-percentange">';
		$widthData = ($rat * 100) / $max;
		$tarring   .= '<div style="width:' . $widthData . '%;" class="percentange_check"><span class="show-per-data">' . round($rat, 1) . '/' . $max . '</span></div>';
		$tarring   .= '</div>';

		return $tarring;
	}


	/**
	 * Review wur_ratting_view_percentange_per . for percentage styles
	 * Method Description: this method use for ratting view in admin page
	 * @params $rat, get ratting value
	 * @params $max, limit score data
	 * @return ratting html data
	 * @since 1.0.0
	 * @access private
	 */
	private static function wur_ratting_view_percentange_per($rat = 0, $max = 5) {
		$rat = (float) $rat;
		$max = max( 1, (float) $max );
		$tarring   = '';
		$tarring   .= '<div class="xs-review-rattting xs-percentange xs-point">';
		$widthData = ($rat * 100) / $max;
		$tarring   .= '<div style="width:' . esc_attr( $widthData ) . '%;" class="percentange_check"><span class="show-per-data">' . esc_html( round( $widthData ) ) . '%</span></div>';
		$tarring   .= '</div>';

		return $tarring;
	}


	/**
	 * Review wur_ratting_view_pie_per . for pie chart styles
	 * Method Description: this method use for ratting view in admin page
	 * @params $rat, get ratting value
	 * @params $max, limit score data
	 * @return ratting html data
	 * @since 1.0.0
	 * @access private
	 */
	private static function wur_ratting_view_pie_per($rat = 0, $max = 5) {
		$rat = (float) $rat;
		$max = max( 1, (float) $max );
		$tarring   = '';
		$widthData = ($rat * 100) / $max;
		$tarring   .= '<div class="xs-review-rattting xs-pie " style="--value: ' . $widthData . '%;">';
		$tarring   .= '<p> ' . round($rat, 1) . ' </p>';
		$tarring   .= '</div>';

		return $tarring;
	}


	/**
	 * Review wur_review_pagination . for pagination
	 * Method Description: this method use for ratting view in admin page
	 * @params $paged, show page number
	 * @params $max_page, max page number
	 * @return ratting html data
	 * @since 1.0.0
	 * @access private
	 */
	public function wur_review_pagination($paged = '', $max_page = '') {
		if(!$paged) {
			$paged = get_query_var('paged');
		}

		if($max_page > 1){
			echo wp_kses(paginate_links(array(
				'format'    => '?review_page=%#%',
				'current'   => max(1, $paged),
				'total'     => $max_page,
				'mid_size'  => 1,
				'prev_text' => '«',
				'next_text' => '»',
				'type'      => 'list',
			)), Settings::kses(null,true)); // Settings::kses(null,true) will return the allowed tags array
		}
	}


	public static function instance() {
		if(!self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}

