<?php

namespace AWS_SES_WP_Mail;

use Aws\Ses\SesClient;
use Exception;
use PHPMailer;
use phpmailerException;

/**
 * Class SES_PHPMailer, Extend PHPMailer, add custom sesSend method.
 * @package AWS_SES_WP_Mail
 */
class SES_PHPMailer extends PHPMailer {
	/**
	 * Get the client for AWS SES.
	 *
	 * @return SesClient
	 */
	public function get_client() {
		if ( ! empty( $this->client ) ) {
			return $this->client;
		}

		// Ensure the AWS SDK can be loaded.
		if ( ! class_exists( '\\Aws\\Ses\\SesClient' ) ) {
			// Require AWS Autoloader file.
			require_once dirname( dirname( __FILE__ ) ) . '/lib/aws-sdk/aws-autoloader.php';
		}

		$params = array(
			'version' => 'latest',
		);

		$params['credentials'] = [
			'key'    => defined( 'AWS_SES_WP_MAIL_KEY' ) ? AWS_SES_WP_MAIL_KEY : null,
			'secret' => defined( 'AWS_SES_WP_MAIL_SECRET' ) ? AWS_SES_WP_MAIL_SECRET : null,
		];

		$region = defined( 'AWS_SES_WP_MAIL_REGION' ) ? AWS_SES_WP_MAIL_REGION : null;
		if ( $region ) {
			$params['signature'] = 'v4';
			$params['region']    = $region;
		}

		if ( defined( 'WP_PROXY_HOST' ) && defined( 'WP_PROXY_PORT' ) ) {
			$proxy_auth    = '';
			$proxy_address = WP_PROXY_HOST . ':' . WP_PROXY_PORT;

			if ( defined( 'WP_PROXY_USERNAME' ) && defined( 'WP_PROXY_PASSWORD' ) ) {
				$proxy_auth = WP_PROXY_USERNAME . ':' . WP_PROXY_PASSWORD . '@';
			}

			$params['request.options']['proxy'] = $proxy_auth . $proxy_address;
		}

		$params = apply_filters( 'aws_ses_wp_mail_ses_client_params', $params );

		$this->client = SesClient::factory( $params );

		return $this->client;
	}

	/**
	 * Send raw email generated by PHPMailer using ses sendRawEmail method.
	 *
	 * @param $mimeHeader string
	 * @param $mimeBody string
	 *
	 * @return bool true if mail sent successfully.
	 * @throws phpmailerException postSend & Send method are catching this exceptions.
	 */
	public function sesSend( $mimeHeader = '', $mimeBody = '' ) {
		try {
			$ses  = $this->get_client();
			$args = array(
				'RawMessage' => array(
					// We can also use following snippet to create body using function arguments
					// rtrim($mimeHeader . $this->mailHeader, "\n\r") . self::CRLF . self::CRLF . $mimeBody;
					'Data' => $this->getSentMIMEMessage(),
				),
			);

			$ses->sendRawEmail( $args );

		} catch ( Exception $e ) {
			throw new phpmailerException( $e->getMessage(), $e->getCode() );
		}

		return true;
	}
}
