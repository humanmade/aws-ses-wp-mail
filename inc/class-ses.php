<?php

namespace AWS_SES_WP_Mail;

use WP_Error;

class SES {

	private static $instance;
	private $key;
	private $secret;

	/**
	 *
	 * @return SES
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {

			$key    = defined( 'AWS_SES_WP_MAIL_KEY' ) ? AWS_SES_WP_MAIL_KEY : null;
			$secret = defined( 'AWS_SES_WP_MAIL_SECRET' ) ? AWS_SES_WP_MAIL_SECRET : null;
			$region = defined( 'AWS_SES_WP_MAIL_REGION' ) ? AWS_SES_WP_MAIL_REGION : null;

			self::$instance = new static( $key, $secret, $region );
		}

		return self::$instance;
	}

	public function __construct( $key, $secret, $region = null ) {
		$this->key = $key;
		$this->secret = $secret;
		$this->region = $region;
	}

	/**
	 * Override WordPress' default wp_mail function with one that sends email
	 * using the AWS SDK.
	 *
	 * @todo support cc, bcc
	 * @todo support attachments
	 * @since  0.0.1
	 * @access public
	 * @todo   Add support for attachments
	 * @param  string $to
	 * @param  string $subject
	 * @param  string $message
	 * @param  mixed $headers
	 * @param  array $attachments
	 * @return bool true if mail has been sent, false if it failed
	 */
	public function send_wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {

		// Compact the input, apply the filters, and extract them back out
		extract( apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) ) );

		// Get the site domain and get rid of www.
		$sitename = strtolower( parse_url( site_url(), PHP_URL_HOST ) );
		if ( 'www.' === substr( $sitename, 0, 4 ) ) {
			$sitename = substr( $sitename, 4 );
		}

		$from_email = 'wordpress@' . $sitename;

		$message_args = array(
			// Email
			'subject'                    => $subject,
			'to'                         => $to,
			'headers'                    => array(
				'Content-type'           => apply_filters( 'wp_mail_content_type', 'text/plain' ),
			),
			'from_name'                  => get_bloginfo( 'name' ),
			'from_email'                 => $from_email,
		);
		$message_args['headers'] = array_merge( $message_args['headers'], $headers );
		$message_args = apply_filters( 'aws_ses_wp_mail_pre_message_args', $message_args );

		// Make sure our to value is an array so we can manipulate it for the API.
		if ( ! is_array( $message_args['to'] ) ) {
			$message_args['to'] = explode( ',', $message_args['to'] );
		}

		if ( $message_args['headers']['Content-type'] === 'text/plain' ) {
			$message_args['text'] = $message;
		} else {
			$message_args['html'] = $message;
		}

		// Default filters we should still apply.
		$message_args['from_email'] = apply_filters( 'wp_mail_from', $message_args['from_email'] );
		$message_args['from_name']  = apply_filters( 'wp_mail_from_name', $message_args['from_name'] );

		// Allow user to override message args before they're sent to Mandrill.
		$message_args = apply_filters( 'aws_ses_wp_mail_message_args', $message_args );

		$ses = $this->get_client();

		if ( is_wp_error( $ses ) ) {
			return $ses;
		}

		try {
			$args = array(
				'Source'      => sprintf( '%s <%s>', $message_args['from_name'], $message_args['from_email'] ),
				'Destination' => array(
					'ToAddresses' => $message_args['to']
				),
				'Message'     => array(
					'Subject' => array(
						'Data'    => $message_args['subject'],
						'Charset' => get_bloginfo( 'charset' ),
					),
					'Body'   => array(),
				),
			);

			if ( isset( $message_args['text'] ) ) {
				$args['Message']['Body']['Text'] = array(
					'Data'    => $message_args['text'],
					'Charset' => get_bloginfo( 'charset' ),
				);
			}

			if ( isset( $message_args['html'] ) ) {
				$args['Message']['Body']['Html'] = array(
					'Data'    => $message_args['html'],
					'Charset' => get_bloginfo( 'charset' ),
				);
			}

			$args = apply_filters( 'aws_ses_wp_mail_ses_send_message_args', $args );

			$ses->sendEmail( $args );
		} catch ( \Exception $e ) {
			return new WP_Error( get_class( $e ), $e->getMessage() );
		}

		return true;
	}

	/**
	 * Get the client for AWS SES.
	 *
	 * @return Aws\Client\Ses|WP_Error
	 */
	public function get_client() {
		require_once dirname( dirname( __FILE__ ) ) . '/lib/aws-sdk/aws-autoloader.php';

		$params = array();

		if ( $this->key && $this->secret ) {
			$params['key'] = $this->key;
			$params['secret'] = $this->secret;
		}

		if ( $this->region ) {
			$params['signature'] = 'v4';
			$params['region'] = $this->region;
		}

		if ( defined( 'WP_PROXY_HOST' ) && defined( 'WP_PROXY_PORT' ) ) {
			$proxy_auth = '';
			$proxy_address = WP_PROXY_HOST . ':' . WP_PROXY_PORT;

			if ( defined( 'WP_PROXY_USERNAME' ) && defined( 'WP_PROXY_PASSWORD' ) ) {
				$proxy_auth = WP_PROXY_USERNAME . ':' . WP_PROXY_PASSWORD . '@';
			}

			$params['request.options']['proxy'] = $proxy_auth . $proxy_address;
		}

		$params = apply_filters( 'aws_ses_wp_mail_ses_client_params', $params );

		try {
			return \Aws\Common\Aws::factory( $params )->get( 'ses' );
		} catch( \Exception $e ) {
			return new WP_Error( get_class( $e ), $e->getMessage() );
		}
	}
}