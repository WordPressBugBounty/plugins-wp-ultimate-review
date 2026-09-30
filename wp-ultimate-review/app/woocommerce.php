<?php

namespace WurReview\App;

defined('ABSPATH') || exit;

/**
 * Class Name : Woocommerce
 *
 * Feeds the ratings collected by WP Ultimate Review into WooCommerce, so the
 * product rating (single product summary, shop loop, widgets, blocks) reflects
 * the reviews submitted through this plugin.
 *
 * @since 2.4.4
 */
class Woocommerce {

	/**
	 * WooCommerce always works with a 5 star scale
	 */
	const WC_RATING_SCALE = 5;

	/**
	 * Calculated rating stats per product for the current request
	 *
	 * @var array
	 */
	private $stats = [];

	/**
	 * @var bool|null
	 */
	private $enabled = null;


	public function __construct() {

		if(!class_exists('WooCommerce')) {
			return;
		}

		add_filter('woocommerce_product_get_average_rating', [$this, 'average_rating'], 20, 2);
		add_filter('woocommerce_product_get_rating_counts', [$this, 'rating_counts'], 20, 2);
		add_filter('woocommerce_product_get_review_count', [$this, 'review_count'], 20, 2);

		// show the review form and list in the product "Reviews" tab
		add_filter('woocommerce_product_tabs', [$this, 'product_tabs'], 98);
	}


	/**
	 * Whether the reviews of this post are shown in the WooCommerce product tab instead of the post content.
	 * When "custom code" location is selected the shortcode decides where the reviews go, so the tab is not used.
	 *
	 * @param \WP_Post $post
	 * @param string   $location review location from the display settings
	 *
	 * @return bool
	 */
	public static function is_product_tab_display($post, $location) {

		return class_exists('WooCommerce') && is_object($post) && $post->post_type === 'product' && $location !== 'custom_code';
	}


	/**
	 * Replace WooCommerce's own reviews tab with the reviews of this plugin
	 *
	 * @param array $tabs
	 *
	 * @return array
	 */
	public function product_tabs($tabs) {

		global $post, $product;

		if(!$this->is_enabled() || !is_object($post)) {
			return $tabs;
		}

		$display_settings = get_option(Wur_Settings::$ok_review_display_settings, []);
		$location         = isset($display_settings['review_location']) ? $display_settings['review_location'] : 'after_content';

		if(!self::is_product_tab_display($post, $location) || post_password_required($post)) {
			return $tabs;
		}

		$stats = is_object($product) ? $this->get_stats($product) : false;

		$tabs['reviews'] = [
			/* translators: %s: number of reviews */
			'title'    => sprintf(__('Reviews (%d)', 'wp-ultimate-review'), $stats === false ? 0 : $stats['total']),
			'priority' => isset($tabs['reviews']['priority']) ? $tabs['reviews']['priority'] : 30,
			'callback' => [$this, 'reviews_tab_content'],
		];

		return $tabs;
	}


	public function reviews_tab_content() {

		global $post;

		echo Content::instance()->get_review_content($post); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the view templates
	}


	public function average_rating($value, $product) {

		$stats = $this->get_stats($product);

		return $stats === false ? $value : $stats['average'];
	}


	public function rating_counts($value, $product) {

		$stats = $this->get_stats($product);

		return $stats === false ? $value : $stats['counts'];
	}


	public function review_count($value, $product) {

		$stats = $this->get_stats($product);

		return $stats === false ? $value : $stats['total'];
	}


	/**
	 * User reviews must be enabled and the product post type must be selected in the display settings
	 *
	 * @return bool
	 */
	private function is_enabled() {

		if($this->enabled === null) {
			$wur_settings = new Wur_Settings();
			$wur_settings->load();

			$this->enabled = $wur_settings->is_user_review_enabled() && $wur_settings->is_review_enable_for_post_type('product');
		}

		return $this->enabled;
	}


	/**
	 * Rating stats of a product, calculated from the published reviews of this plugin.
	 *
	 * @param \WC_Product $product
	 *
	 * @return array|false false when the plugin has no reviews for the product, so WooCommerce keeps its own data
	 */
	private function get_stats($product) {

		if(!is_object($product) || !is_callable([$product, 'get_id']) || !$this->is_enabled()) {
			return false;
		}

		$product_id = (int)$product->get_id();

		if(isset($this->stats[$product_id])) {
			return $this->stats[$product_id];
		}

		// same lookup the rating shortcode uses
		$reviews = get_posts([
			'nopaging'         => true,
			'post_type'        => 'xs_review',
			'post_status'      => 'publish',
			'meta_query'       => [
				[
					'key'     => 'xs_public_review_data',
					'value'   => '"xs_post_id":"' . $product_id . '"',
					'compare' => 'LIKE',
				],
			],
			'suppress_filters' => true,
		]);

		$counts = [];
		$sum    = 0;
		$total  = 0;

		foreach($reviews as $review) {

			$meta = Wur_Settings::get_xs_post_meta($review->ID, 'xs_public_review_data');

			if(empty($meta->xs_reviwer_ratting) || !is_numeric($meta->xs_reviwer_ratting) || (float)$meta->xs_reviwer_ratting <= 0) {
				continue;
			}

			$limit  = (empty($meta->review_score_limit) || !is_numeric($meta->review_score_limit) || (float)$meta->review_score_limit <= 0) ? self::WC_RATING_SCALE : (float)$meta->review_score_limit;
			$rating = min((float)$meta->xs_reviwer_ratting, $limit);

			// convert the rating to WooCommerce's 5 star scale
			$rating = ($rating * self::WC_RATING_SCALE) / $limit;

			$bucket          = max(1, min(self::WC_RATING_SCALE, (int)round($rating)));
			$counts[$bucket] = isset($counts[$bucket]) ? $counts[$bucket] + 1 : 1;

			$sum += $rating;
			$total++;
		}

		if($total === 0) {
			$this->stats[$product_id] = false;

			return false;
		}

		ksort($counts);

		$this->stats[$product_id] = [
			'average' => (string)round($sum / $total, 2),
			'counts'  => $counts,
			'total'   => $total,
		];

		return $this->stats[$product_id];
	}
}
