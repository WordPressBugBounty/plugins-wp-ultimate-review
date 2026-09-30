<?php

defined('ABSPATH') || exit;

/**
 * GDPR consent checkbox for the public review forms.
 * Rendered only when enabled in Settings > Global Settings.
 */

$wur_gdpr_settings = new \WurReview\App\Wur_Settings();
$wur_gdpr_settings->_load_settings_global();

if($wur_gdpr_settings->is_gdpr_consent_enabled()) :

	// links, bold and italic are allowed in the consent text
	$wur_gdpr_text = wp_kses($wur_gdpr_settings->get_gdpr_consent_text(), \WurReview\App\Settings::gdpr_consent_allowed_html());
	$wur_gdpr_text = str_replace('[privacy_policy]', get_the_privacy_policy_link(), $wur_gdpr_text);
	?>
	<div class="xs-review xs-checkbox xs-review-gdpr-consent">
		<label for="xs_review_gdpr_consent">
			<input type="checkbox"
			       id="xs_review_gdpr_consent"
			       name="xs_review_gdpr_consent"
			       value="yes"
			       required />
			<span class="xs-review-gdpr-consent-text"><?php echo wp_kses_post($wur_gdpr_text); ?></span>
		</label>
	</div>
<?php
endif;
