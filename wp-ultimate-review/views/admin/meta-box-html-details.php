<?php

defined('ABSPATH') || exit;

$overview_setting_optionKey = 'xs_review_overview_settings';

?>

<div class="xs-review-box details-xs-review wur-meta-box-container wur-login-main-wrapper" id="xs-review-box">

    <div class="content-xs-review-box">
        <div class="xs-wp-review-field">
            <div class="xs-wp-review-field-label">
                <label class="wur-sec-title" for="overview_enable">
                    <?php echo esc_html__('Overview Enable', 'wp-ultimate-review'); ?>
                </label>
            </div>
            <div class="xs-wp-review-field-option">
                <input class="review_switch_button"
                       type="checkbox"
                       id="overview_enable"
                       name="xs_review_overview_settings[overview][enable]"
                       value="Yes" <?php echo (isset($saved_meta->overview->enable) && $saved_meta->overview->enable == 'Yes') ? 'checked' : ''; ?> >

                <label for="overview_enable"
                       onclick="xs_review_show_hide(2);"
                       class="review_switch_button_label">
                </label>
            </div>
        </div>
    </div>

    <div id="xs_review_tr__2" class="wur-meta-main-content content-xs-review-box deactive_tr  <?php echo isset($saved_meta->overview->enable) ? 'active_tr' : ''; ?>">

        <div class="xs-wp-review-field wur-single-item">
            <div class="xs-wp-review-field-label">
                <label class="wur-sec-title" for="review_score_style_id">
                    <?php echo esc_html__('Review Graph Style', 'wp-ultimate-review'); ?>
                </label>
            </div>


            <div class="xs-review-graph-style-field-container">

	            <?php
	            // global score styles
	            $review_score_style = isset($global_settings['review_score_style']) ? $global_settings['review_score_style'] : 'star';

	            // overview score style
	            $selectReviewScoreStyle = isset($saved_meta->overview->style) ? $saved_meta->overview->style : $review_score_style;

	            foreach($this->review_style AS $reviewStyleKey => $style) {

	            $checked = ($selectReviewScoreStyle == $reviewStyleKey) ? 'checked' : ''; ?>
                <div class="xs-review-graph-style-field-box-container">
                    <input type="radio" id="graphStyle-<?php echo esc_attr($reviewStyleKey) ?>" name="xs_review_overview_settings[overview][style]" value="<?php echo esc_attr($reviewStyleKey) ?>" <?php echo esc_attr($checked); ?>>
                    <div class="xs-review-graph-style-field-box">
                        <label for="graphStyle-<?php echo esc_attr($reviewStyleKey) ?>"> <img src="<?php echo esc_url($style['thumbnail']) ?>" alt=""></label>
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                </div>
                <?php
                }
                ?>

            </div>

        </div>

        <!-- Overview Headding-->
        <div class="xs-wp-review-field wur-single-item">
            <div class="xs-wp-review-field-label">
                <label class="wur-sec-title"
                       for="wp_review_heading">
                    <?php echo esc_html__('Heading', 'wp-ultimate-review'); ?>
                </label>
            </div>

            <div class="xs-wp-review-field-option">
				<?php
				$selectOverviewHeading = isset($saved_meta->overview->heading) ? $saved_meta->overview->heading : 'Overview';
				?>
                <input id="wp_review_heading"
                       class="wur-global-input"
                       type="text"
                       placeholder="<?php esc_attr_e('Overview heading', 'wp-ultimate-review'); ?>"
                       value="<?php echo esc_attr($selectOverviewHeading); ?>"
                       name="xs_review_overview_settings[overview][heading]">

            </div>
        </div>

        <div class="xs-wp-review-field wur-single-item wur-single-item-top">
            <div class="xs-wp-review-field-label">
                <label for="overview_summary_enable"
                       class="wur-sec-title"><?php echo esc_html__('Summary Enable', 'wp-ultimate-review'); ?></label>
            </div>
            <div class="xs-wp-review-field-option">
                <input class="review_switch_button" type="checkbox" id="overview_summary_enable"
                       name="xs_review_overview_settings[overview][summary][enable]"
                       value="Yes" <?php echo (isset($saved_meta->overview->summary->enable) && $saved_meta->overview->summary->enable == 'Yes') ? 'checked' : ''; ?> >
                <label for="overview_summary_enable" class="review_switch_button_label"></label>

                <div class="wur-input-list">
					<?php
					$selectOverviewSummary = isset($saved_meta->overview->summary->data) ? $saved_meta->overview->summary->data : '';
					?>
                    <textarea class="wur-global-text-area" type="text" placeholder="<?php esc_attr_e('Overview summary', 'wp-ultimate-review'); ?>"
                              name="xs_review_overview_settings[overview][summary][data]"><?php echo esc_html($selectOverviewSummary); ?></textarea>
                </div>
            </div>
        </div>

        <div class="xs-wp-review-field wur-single-item wur-single-item-top">
            <div class="xs-wp-review-field-label">
                <label for="overview_ratting_enable"
                       class="wur-sec-title"><?php echo esc_html__('Rating Short-code', 'wp-ultimate-review'); ?></label>
            </div>
            <div class="xs-wp-review-field-option">
                <input class="review_switch_button" type="checkbox" id="overview_ratting_enable"
                       name="xs_review_overview_settings[overview][ratting][enable]"
                       value="Yes" <?php echo (isset($saved_meta->overview->ratting->enable) && $saved_meta->overview->ratting->enable == 'Yes') ? 'checked' : ''; ?> >
                <label for="overview_ratting_enable" class="review_switch_button_label"></label>

                <div class="wur-input-list">
                    <textarea
                            class="wur-global-text-area">[wp-reviews-rating post-id="<?php echo esc_attr($post->ID); ?>" ratting-show="yes" count-show="yes" vote-show="yes" vote-text="Votes" class=""]</textarea>
                    <div class="wur-review-type-help-label"><?php echo esc_html__('Shortcode Rating ', 'wp-ultimate-review'); ?></div>
                </div>

            </div>
        </div>

        <div class="xs-wp-review-field overview-item">
            <div class="xs-wp-review-field-label">
                <label for="xs-wp_review_type" class="wur-sec-title">
                    <strong><?php echo esc_html__('Review Item', 'wp-ultimate-review'); ?></strong></label>
            </div>
			<?php
			$review_score_style_input = isset($global_settings['review_score_input']) ? $global_settings['review_score_input'] : 'star';

			$review_score_limit = isset($global_settings['review_score_limit']) ? $global_settings['review_score_limit'] : 5;

			// The rating input always follows the global "Review Score Input Style" (same as the public review form),
			// so every post type shows the same input regardless of the selected graph style.

			?>

        </div>

        <div class="repater-overview-item" id="repater_review_item">
            <button type="button" class="xs-review-btnAdd xs-review-add-button xs-review-btn xs-btn btn-special small">
                <span class="dashicons dashicons-plus"></span><?php echo esc_html__('Add', 'wp-ultimate-review'); ?>
            </button>
			<?php
			// products use the product criteria, every other post type uses the post/page criteria
			$post_criteria = $wur->get_criteria_names_for_post_type($post->post_type);

			$default_ratting = min(3, (int)$review_score_limit);

			// Saved items (without the empty ones) followed by every criteria that is not saved yet,
			// so criteria added in the settings also show up on posts that were already reviewed.
			$overview_items = [];
			$saved_names    = [];

			if(!empty($saved_meta->overview->item)) {
				foreach($saved_meta->overview->item as $saved_item) {
					$saved_name = (isset($saved_item->name) && is_scalar($saved_item->name)) ? trim((string)$saved_item->name) : '';

					if($saved_name === '' || in_array(strtolower($saved_name), $saved_names, true)) {
						continue;
					}

					$saved_names[]    = strtolower($saved_name);
					$overview_items[] = [
						'name'      => $saved_name,
						'ratting'   => isset($saved_item->ratting) ? $saved_item->ratting : $default_ratting,
						'rat_range' => isset($saved_item->rat_range) ? $saved_item->rat_range : $review_score_limit,
					];
				}
			}

			foreach($post_criteria as $criteria_name) {
				if(!in_array(strtolower($criteria_name), $saved_names, true)) {
					$overview_items[] = ['name' => $criteria_name, 'ratting' => $default_ratting, 'rat_range' => $review_score_limit];
				}
			}

			// no saved items and no criteria: show three empty rows as before
			if(empty($overview_items)) {
				$overview_items = array_fill(0, 3, ['name' => '', 'ratting' => $default_ratting, 'rat_range' => $review_score_limit]);
			}

			$selectOverviewReapter = count($overview_items);

			foreach($overview_items as $rep => $overview_item):
				$dataName         = $overview_item['name'];
				$dataRatting      = $overview_item['ratting'];
				$dataRattingRange = $overview_item['rat_range'];
				?>

                <div class="reapter-div-xs">
                    <div class="xs-wp-review-field overview-item-repeater">
                        <div class="xs-wp-review-field-label">
                            <label for="xs_review_<?php echo esc_attr($rep); ?>_name" data-pattern-text="Item Name +=1"
                                   class="wur-sec-title"> <?php echo esc_html__('Item Name', 'wp-ultimate-review'); ?> </label>
                        </div>
                        <div class="xs-wp-review-field-option">
                            <input type="text" value="<?php echo esc_attr($dataName); ?>"
                                   name="xs_review_overview_settings[overview][item][<?php echo esc_attr($rep); ?>][name]"
                                   data-pattern-name="xs_review_overview_settings[overview][item][++][name]"
                                   id="xs_review_<?php echo esc_attr($rep); ?>_name" data-pattern-id="xs_review_++_name"
                                   class="wur-global-input">
                        </div>
                    </div>
                    <div class="xs-wp-review-field overview-item-repeater">
                        <div class="xs-wp-review-field-label">
                            <label for="xs_review_<?php echo esc_attr($rep); ?>_ratting" data-pattern-text="Ratting +=1"
                                   class="wur-sec-title"> <?php echo esc_html__('Rating', 'wp-ultimate-review'); ?></label>
                        </div>
                        <div class="xs-wp-review-field-option">

                            <div class="xs-review xs-select">
								<?php if(in_array($review_score_style_input, array(
									'star',
									'square',
									'movie',
									'bar',
									'pill',
								))): ?>
                                    <div class="xs-review-rating-stars text-center">
                                        <ul class="xs_review_stars">
											<?php for($ratting = 1; $ratting <= $dataRattingRange; $ratting++): ?>
                                                <li class="star-li <?php echo esc_attr($review_score_style_input); ?>  <?php if($ratting <= $dataRatting) {
													echo 'selected';
												} ?>" data-value="<?php echo esc_attr($ratting); ?>">
													<?php if($review_score_style_input == 'star') { ?>
                                                        <i class="xs-star dashicons-before dashicons-star-filled"></i>
													<?php } else {
														echo '<span>' . esc_html($ratting) . '<span>';
													} ?>
                                                </li>
											<?php endfor; ?>
                                        </ul>
                                        <input type="number"
                                               class="right-review-ratting wur-global-input wur-number-input"
                                               min="1" max="<?php echo esc_attr($dataRattingRange); ?>" step="1"
                                               data-default="<?php echo esc_attr($default_ratting); ?>"
                                               value="<?php echo esc_attr($dataRatting); ?>"
                                               name="xs_review_overview_settings[overview][item][<?php echo esc_attr($rep); ?>][ratting]"
                                               data-pattern-name="xs_review_overview_settings[overview][item][++][ratting]"
                                               id="xs_review_<?php echo esc_attr($rep); ?>_ratting"
                                               data-pattern-id="xs_review_++_ratting">

                                    </div>
								<?php endif;
								if($review_score_style_input == 'slider'):?>
                                    <div class="xs-review-rating-slider text-center">
                                        <div class="xs-slidecontainer">
                                            <input type="range" min="1" max="<?php echo esc_html($dataRattingRange); ?>"
                                                   value="<?php echo esc_attr($dataRatting); ?>"
                                                   name="xs_review_overview_settings[overview][item][<?php echo esc_attr($rep); ?>][ratting]"
                                                   class="xs-slider-range" id="xs_review_range"
                                                   data-default="<?php echo esc_attr($default_ratting); ?>"
                                                   data-pattern-name="xs_review_overview_settings[overview][item][++][ratting]"
                                                   id="xs_review_<?php echo esc_attr($rep); ?>_ratting"
                                                   data-pattern-id="xs_review_++_ratting"
                                                   onchange="click_xs_review_data_slider(this)">

                                        </div>
                                        <div id="review_data_show"> <?php echo esc_html($dataRatting); ?></div>
                                    </div>
									<?php
								endif;
								?>
                            </div>

                        </div>
                    </div>
                    <button type="button"
                            class="xs-review-btnRemove xs-review-remove-button xs-review-btn xs-btn btn-danger small">
                        <span class="dashicons dashicons-no-alt"></span></button>
                </div>
			<?php endforeach; ?>
        </div>

    </div>
</div>

<script type="text/javascript">
    /*Reapter data*/
    jQuery(document).ready(function ($) {

        var numberOfRowXs = '<?php echo esc_html(($selectOverviewReapter - 1));?>';
        var numberOfRowXsKey = '<?php echo esc_html($overview_setting_optionKey);?>';

        $('#repater_review_item').repeater({
                btnAddClass: 'xs-review-btnAdd',
                btnRemoveClass: 'xs-review-btnRemove',
                groupClass: 'reapter-div-xs',
                minItems: 1,
                maxItems: 0,
                startingIndex: parseInt(numberOfRowXs),
                showMinItemsOnLoad: false,
                reindexOnDelete: true,
                repeatMode: 'append',
                animation: 'fade',
                animationSpeed: 400,
                animationEasing: 'swing',
                clearValues: true,
                afterAdd: function ($row) {
                    xs_review_after_add_item($row);
                }
            }, []
        );
    });
</script>