<?php // @codingStandardsIgnoreLine
/**
 * Plugin Name:  AWS SES wp_mail drop-in
 * Plugin URI:   https://github.com/humanmade/aws-ses-wp-mail
 * Description:  Drop-in replacement for wp_mail using the AWS SES.
 * Version:      0.1.1
 * Author:       Joe Hoyle | Human Made
 * Author URI:   https://github.com/humanmade
 * License:      GPL-2.0+
 * License URI:  http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ( ! defined( 'AWS_SES_WP_MAIL_KEY' ) || ! defined( 'AWS_SES_WP_MAIL_SECRET' ) || ! defined( 'AWS_SES_WP_MAIL_REGION' ) ) && ! defined( 'AWS_SES_WP_MAIL_USE_INSTANCE_PROFILE' ) ) {
	return;
}

// Access to the Amazon SDK is required for this plugin to send mail.
// It can be provided directly via composer install within this plugin, or as a dependency of your project.
if ( is_readable( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

require_once dirname( __FILE__ ) . '/inc/class-ses.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/inc/class-wp-cli-command.php';
	WP_CLI::add_command( 'aws-ses', 'AWS_SES_WP_Mail\\WP_CLI_Command' );
}

if ( ! function_exists( 'wp_mail' ) ) :
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ) { // @codingStandardsIgnoreLine
		$result = AWS_SES_WP_Mail\SES::get_instance()->send_wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( is_wp_error( $result ) ) {
			trigger_error(
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				sprintf( 'Sendmail SES Email failed: %d %s', $result->get_error_code(), $result->get_error_message() ),
				E_USER_WARNING
			);
			return false;
		}

		return $result;
	}
endif;
