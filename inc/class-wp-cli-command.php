<?php

namespace AWS_SES_WP_Mail;
use WP_CLI;
use WP_Error;

/**
 * Manage and send email via AWS SES.
 */
class WP_CLI_Command extends \WP_CLI_Command {

	/**
	 * Send an email via AWS SES
	 *
	 * @synopsis <to-email> <subject> <message> [--from-email=<from-email>]
	 */
	public function send( $args, $args_assoc ) {
		$result = SES::get_instance()->send_wp_mail( $args[0], $args[1], $args[2] );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_code() . ': ' . $result->get_error_message() );
		}

		WP_CLI::success( 'Sent.' );
	}

	/**
	 * Verify a domain in SES to send mail from.
	 *
	 * @subcommand verify-sending-domain
	 * @synopsis [--domain=<domain>]
	 */
	public function verify_sending_domain( $args, $args_assoc ) {
		$ses = SES::get_instance()->get_client();
		if ( is_wp_error( $ses ) ) {
			WP_CLI::error( $ses->get_error_code() . ': ' . $ses->get_error_message() );
		}

		// Get the site domain and get rid of www.
		$domain = strtolower( parse_url( site_url(), PHP_URL_HOST ) );
		if ( 'www.' === substr( $domain, 0, 4 ) ) {
			$domain = substr( $domain, 4 );
		}

		if ( $args_assoc['domain'] ) {
			$domain = $args_assoc['domain'];
		}

		try {
			$verify = $ses->verifyDomainIdentity( array(
				'Domain' => $domain
			));
		} catch ( \Exception $e ) {
			WP_CLI::error( get_class( $e ) . ': ' . $e->getMessage() );
		}
	}
}