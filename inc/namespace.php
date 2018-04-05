<?php

namespace AWS_SES_WP_Mail;

/**
 * Hook all required action & filters.
 */
function bootstrap() {
	add_filter( 'wp_mail', __NAMESPACE__ . '\\init_aws_ses' );
	add_action( 'phpmailer_init', __NAMESPACE__ . '\\set_mailer', 999 );
}

/**
 * Initialize custom ses phpmailer class.
 *
 * @param $args array wp_mail args.
 *
 * @return array  return args as it is.
 */
function init_aws_ses( $args ) {
	global $phpmailer;

	if ( ! class_exists( 'PHPMailer' ) ) {
		require_once ABSPATH . WPINC . '/class-phpmailer.php';
	}

	if ( ! class_exists( '\\AWS_SES_WP_Mail\\SES_PHPMailer' ) ) {
		require_once dirname( __FILE__ ) . '/class-ses-phpmailer.php';
	}

	// (Re)create it, if it's not ses phpmailer missing
	if ( ! ( $phpmailer instanceof SES_PHPMailer ) ) {
		$phpmailer = new SES_PHPMailer( true );
	}

	return $args;
}

/**
 * Set ses as Mailer method so that it will call sesSend method for sending a mail.
 *
 * @param $phpmailer \PHPMailer Current PHPMailer instance.
 */
function set_mailer( $phpmailer ) {
	$phpmailer->Mailer = 'ses';
}
