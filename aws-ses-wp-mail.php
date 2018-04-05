<?php
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

require_once dirname( __FILE__ ) . '/inc/namespace.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/inc/class-wp-cli-command.php';
	WP_CLI::add_command( 'aws-ses', 'AWS_SES_WP_Mail\\WP_CLI_Command' );
}

add_action( 'plugins_loaded', '\\AWS_SES_WP_Mail\\bootstrap' );
