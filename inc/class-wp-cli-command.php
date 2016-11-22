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

		if ( ! empty( $args_assoc['from-email'] ) ) {
			add_filter( 'wp_mail_from', function() use ( $args_assoc ) {
				return $args_assoc['from-email'];
			});
		}
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

		// Get the site domain and get rid of www.
		$domain = strtolower( parse_url( site_url(), PHP_URL_HOST ) );
		if ( 'www.' === substr( $domain, 0, 4 ) ) {
			$domain = substr( $domain, 4 );
		}

		if ( isset( $args_assoc['domain'] ) ) {
			$domain = $args_assoc['domain'];
		}

		$dns_records = $this->get_sending_domain_dns_records( $domain );

		WP_CLI::line( 'Submitted for verification. Make sure you have the following DNS records added to the domain:' );

		\WP_CLI\Utils\format_items( 'table', $dns_records, array( 'Domain', 'Type', 'Value' ) );
	}

	protected function get_sending_domain_dns_records( $domain ) {

		$ses = SES::get_instance()->get_client();
		if ( is_wp_error( $ses ) ) {
			WP_CLI::error( $ses->get_error_code() . ': ' . $ses->get_error_message() );
		}

		try {
			$verify = $ses->verifyDomainIdentity( array(
				'Domain' => $domain,
			));
		} catch ( \Exception $e ) {
			WP_CLI::error( get_class( $e ) . ': ' . $e->getMessage() );
		}

		$dkim = $ses->verifyDomainDkim( array(
			'Domain' => $domain,
		));

		$dns_records[] = array(
			'Domain' => '_amazonses.' . $domain,
			'Type'   => 'TXT',
			'Value'  => $verify['VerificationToken'],
		);

		foreach ( $dkim['DkimTokens'] as $token ) {
			$dns_records[] = array(
				'Domain' => $token . '._domainkey.' . $domain,
				'Type'   => 'CNAME',
				'Value'  => $token . '.dkim.amazonses.com',
			);
		}

		return $dns_records;
	}
}
