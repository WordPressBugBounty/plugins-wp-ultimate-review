<?php

namespace WurReview\App;


class Wur_Settings {

	private static $instance;

	public static $ok_review_display_settings = 'xs_review_display';
	public static $ok_review_global_settings = 'xs_review_global';
	public static $xs_review_setting_criteria_key = 'xs_review_criteria';
	public static $xs_review_captcha_setting_key = 'xs_review_captcha';

	private $post_type = 'xs_review';

	private $global_settings;
	private $display_settings;
	private $criteria_settings;
	private $captcha_settings;

	private $post_meta;


	public static function instance() {

		if(!self::$instance) {
			self::$instance = new static();
		}

		return self::$instance;
	}


	public function load() {

		$sett_display = get_option(self::$ok_review_display_settings, []);
		$sett_global  = get_option(self::$ok_review_global_settings, []);
		$set_captcha_data = get_option(self::$xs_review_captcha_setting_key, []);

		$this->global_settings = $sett_global;
		$this->display_settings = $sett_display;
		$this->captcha_settings = $set_captcha_data;		
	}


	public function _load_settings_global() {

		$sett_global = get_option(self::$ok_review_global_settings, []);

		$this->global_settings = $sett_global;
	}


	public function _load_settings_display() {

		$sett_display = get_option(self::$ok_review_display_settings, []);

		$this->display_settings = $sett_display;
	}

	public function set_criteria_settings() {
		$this->criteria_settings = get_option(self::$xs_review_setting_criteria_key, []);
	}


	public function get_criteria_settings() {
		return $this->criteria_settings;
	}

	/**
	 * get_captcha_settings
	 * 
	 * @since 2.2.0
	 * @access public
	 * @return array
	 */
	public function get_captcha_settings() {
		return $this->captcha_settings;
	}

	/**
	 * is_captcha_enabled
	 * 
	 * @since 2.2.0
	 * @access public
	 * @return bool
	 */
	public function is_captcha_enabled() {
		return !empty($this->captcha_settings['wur_captcha']['enable']);
	}

	/**
	 * get_captcha_version
	 * 
	 * @since 2.2.0
	 * @access public
	 * @return string
	 */
	public function get_captcha_version(){
		return isset($this->captcha_settings['captcha_version']) ? $this->captcha_settings['captcha_version'] : '';
	}

	/**
	 * get_captcha_site_key
	 * 
	 * @since 2.2.0
	 * @access public
	 * @return string
	 */
	public function get_captcha_site_key(){
		return isset($this->captcha_settings['captcha_site_key']) ? $this->captcha_settings['captcha_site_key'] : '';
	}

	/**
	 * get_captcha_secret_key
	 * 
	 * @since 2.2.0
	 * @access public
	 * @return string
	 */
	public function get_captcha_secret_key(){
		return isset($this->captcha_settings['captcha_secret_key']) ? $this->captcha_settings['captcha_secret_key'] : '';
	}

	public function is_author_review_enabled() {

		return !empty($this->global_settings['author_review']);
	}


	public function is_user_review_enabled() {

		return !empty($this->global_settings['user_review']);
	}


	/**
	 * Whether visitors rate every criterion separately instead of giving one overall rating.
	 * Pro only, so the feature switches itself off when the pro plugin is not active.
	 *
	 * @since 2.4.4
	 * @access public
	 * @return bool
	 */
	public function is_criteria_review_enabled() {

		return Application::pro_version_exist()
		       && isset($this->global_settings['criteria_review'])
		       && $this->global_settings['criteria_review'] === 'Yes';
	}


	/**
	 * Criteria names of a post type, without the empty and duplicated ones.
	 * Products use the product criteria, every other post type uses the post/page criteria.
	 *
	 * @since 2.4.4
	 * @access public
	 *
	 * @param string $post_type
	 * @return array list of criteria names
	 */
	public function get_criteria_names_for_post_type($post_type) {

		if(!is_array($this->criteria_settings)) {
			$this->set_criteria_settings();
		}

		$criteria_type = ($post_type === 'product') ? 'product' : 'post';
		$criteria      = isset($this->criteria_settings[$criteria_type]['criteria_names']) ? (array)$this->criteria_settings[$criteria_type]['criteria_names'] : [];

		$names = [];
		$seen  = [];

		foreach($criteria as $name) {

			if(!is_scalar($name)) {
				continue;
			}

			$name = trim((string)$name);

			if($name === '' || in_array(strtolower($name), $seen, true)) {
				continue;
			}

			$seen[]  = strtolower($name);
			$names[] = $name;
		}

		return $names;
	}


	/**
	 * Everything a criteria rating callback needs: the criteria names, the score limit and the
	 * input style. Returns an empty criteria list when the feature is off, when the pro plugin is
	 * missing or when no criteria are configured for this post type, and the callers then render
	 * the normal single rating input.
	 *
	 * @since 2.4.4
	 * @access public
	 *
	 * @param string $post_type
	 * @return array
	 */
	public function get_criteria_review_context($post_type) {

		$criteria = $this->is_criteria_review_enabled() ? $this->get_criteria_names_for_post_type($post_type) : [];

		$score_limit = isset($this->global_settings['review_score_limit']) ? (int)$this->global_settings['review_score_limit'] : 5;

		return [
			'post_type'   => $post_type,
			'criteria'    => $criteria,
			'score_limit' => $score_limit > 0 ? $score_limit : 5,
			'input_style' => isset($this->global_settings['review_score_input']) ? $this->global_settings['review_score_input'] : 'star',
			'score_style' => isset($this->global_settings['review_score_style']) ? $this->global_settings['review_score_style'] : 'star',
			'field_key'   => 'xs_submit_review_data',
		];
	}


	/**
	 * Whether the GDPR consent checkbox must be accepted before submitting a review
	 *
	 * @since 2.4.4
	 * @return bool
	 */
	public function is_gdpr_consent_enabled() {

		return isset($this->global_settings['gdpr_consent']) && $this->global_settings['gdpr_consent'] === 'Yes';
	}


	/**
	 * GDPR consent text shown next to the checkbox, [privacy_policy] is replaced with the privacy policy link
	 *
	 * @since 2.4.4
	 * @return string
	 */
	public function get_gdpr_consent_text() {

		$text = (isset($this->global_settings['gdpr_consent_text']) && is_scalar($this->global_settings['gdpr_consent_text'])) ? trim((string)$this->global_settings['gdpr_consent_text']) : '';

		return $text === '' ? self::default_gdpr_consent_text() : $text;
	}


	public static function default_gdpr_consent_text() {

		return __('I consent to this website storing my submitted information so they can respond to my review. [privacy_policy]', 'wp-ultimate-review');
	}


	public function is_reviewer_profile_enabled() {

		return !empty($this->display_settings['form']['xs_reviwer_profile_image_data']['display']['enable']);
	}


	public function is_reviewer_name_enabled() {

		return !empty($this->display_settings['form']['xs_reviwer_name_data']['display']['enable']);
	}


	public function is_reviewer_email_enabled() {

		return !empty($this->display_settings['form']['xs_reviwer_email_data']['display']['enable']);
	}


	public function is_reviewer_website_enabled() {

		return !empty($this->display_settings['form']['xs_reviwer_website_data']['display']['enable']);
	}


	public function is_reviewer_rating_enabled() {

		return !empty($this->display_settings['form']['xs_reviwer_ratting_data']['display']['enable']);
	}


	public function is_reviewer_rating_date_enabled() {

		return !empty($this->display_settings['form']['post_date_data']['display']['enable']);
	}

	public function is_review_title_showing_enabled() {

		return !empty($this->display_settings['form']['xs_reviw_title_data']['display']['enable']);
	}


	public function is_review_text_showing_enabled() {

		return !empty($this->display_settings['form']['xs_reviw_summery_data']['display']['enable']);
	}


	public function get_enabled_post_types($include_self = true) {

		$types = empty($this->display_settings['page']['data']) ? ['post'] : $this->display_settings['page']['data'];

		if($include_self === true) {
			$types[] = $this->post_type;
		}

		return $types;
	}


	public function is_review_enable_for_post_type($type) {

		$stack = $this->get_enabled_post_types();

		return in_array($type, $stack);
	}


	public static function get_xs_post_meta($post_id, $key = 'xs_review_overview_settings') {

		$metaDataOverviewJson = get_post_meta($post_id, $key, true);

		return empty($metaDataOverviewJson) ? [] : json_decode($metaDataOverviewJson);
	}


	/*******************************************************************
	 *
	 * Getters & Setters
	 *
	 *******************************************************************/

	/**
	 * @return mixed
	 */
	public function getGlobalSettings() {
		return $this->global_settings;
	}


	/**
	 * @return mixed
	 */
	public function getDisplaySettings() {
		return $this->display_settings;
	}


	/**
	 * @param mixed $post_meta
	 */
	public function setPostMeta($post_meta) {
		$this->post_meta = $post_meta;
	}

}