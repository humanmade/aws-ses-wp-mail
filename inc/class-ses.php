<?php

namespace AWS_SES_WP_Mail;

use Aws\Ses\SesClient;
use Exception;
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
	public function send_wp_mail( $to, $subject, $message, $headers = array(), $attachments = array() ) {

		// Compact the input, apply the filters, and extract them back out
		extract( apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) ) );

		// Get headers as array
		if ( empty( $headers ) ) {
			$headers = array();
		}

		if ( ! is_array( $headers ) ) {
			// Explode the headers out, so this function can take both
			// string headers and an array of headers.
			$headers = array_filter( explode( "\n", str_replace( "\r\n", "\n", $headers ) ) );
		}

		// transform headers array into a key => value map
		foreach ( $headers as $header => $value ) {
			if ( strpos( $value, ':' ) ) {
				$value = array_map( 'trim', explode( ':', $value ) );
				$headers[ $value[0] ] = $value[1];

				// Gravity Forms uses an array like
				// ['Content-Type' => 'Content-Type: text/html']
				// so we need to ensure we don't accidentally unset the
				// new header.
				if ( $header !== $value[0] ) {
					unset( $headers[ $header ] );
				}
			}
		}

		// normalize header names to Camel-Case
		foreach ( $headers as $name => $value ) {
			$uc_name = ucwords( strtolower( $name ), '-' );
			if ( $uc_name !== $name ) {
				$headers[ $uc_name ] = $value;
				unset( $headers[ $name ] );
			}
		}

		// Get the site domain and get rid of www.
		$sitename = strtolower( parse_url( site_url(), PHP_URL_HOST ) );
		if ( 'www.' === substr( $sitename, 0, 4 ) ) {
			$sitename = substr( $sitename, 4 );
		}

		$from_email = 'no-reply@' . $sitename;

		$message_args = array(
			// Email
			'subject'                    => $subject,
			'to'                         => $to,
			'headers'                    => array(
				'Content-Type'           => apply_filters( 'wp_mail_content_type', 'text/plain' ),
				'From'                   => sprintf( '"%s" <%s>', apply_filters( 'wp_mail_from_name', get_bloginfo( 'name' ) ), apply_filters( 'wp_mail_from', $from_email ) ),
			),
		);
		$message_args['headers'] = array_merge( $message_args['headers'], $headers );
		$message_args = apply_filters( 'aws_ses_wp_mail_pre_message_args', $message_args );

		// Make sure our to value is an array so we can manipulate it for the API.
		if ( ! is_array( $message_args['to'] ) ) {
			$message_args['to'] = explode( ',', $message_args['to'] );
		}

		if ( strpos( $message_args['headers']['Content-Type'], 'text/plain' ) !== false ) {
			$message_args['text'] = $message;
		} else {
			$message_args['html'] = $message;
		}

		// Allow user to override message args before they're sent to Mandrill.
		$message_args = apply_filters( 'aws_ses_wp_mail_message_args', $message_args );

		$ses = $this->get_client();

		if ( is_wp_error( $ses ) ) {
			return $ses;
		}

		try {
			$args = array(
				'Source'      => $message_args['headers']['From'],
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

			if ( ! empty( $message_args['headers']['Reply-To'] ) ) {
				$replyto = explode( ',', $message_args['headers']['Reply-To'] );
				$args['ReplyToAddresses'] = array_map( 'trim', $replyto );
			}

			foreach ( [ 'Cc', 'Bcc'] as $type ) {
				if ( empty( $message_args['headers'][ $type ] ) ) {
					continue;
				}

				$addrs = explode( ',', $message_args['headers'][ $type ] );
				$args['Destination'][ $type . 'Addresses' ] = array_map( 'trim', $addrs );
			}

			$args = apply_filters( 'aws_ses_wp_mail_ses_send_message_args', $args, $message_args );
			$result = $ses->sendEmail( $args );
		} catch ( Exception $e ) {
			do_action( 'aws_ses_wp_mail_ses_error_sending_message', $e, $args, $message_args );
			return new WP_Error( get_class( $e ), $e->getMessage() );
		}

		do_action( 'aws_ses_wp_mail_ses_sent_message', $result, $args, $message_args );
		return true;
	}

	/**
	 * Get the client for AWS SES.
	 *
	 * @return SesClient|WP_Error
	 */
	public function get_client() {
		if ( ! empty( $this->client ) ) {
			return $this->client;
		}

		$params = array(
			'version' => 'latest',
		);

		if ( $this->key && $this->secret ) {
			$params['credentials'] = [
				'key' => $this->key,
				'secret' => $this->secret,
			];
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
			$this->client = SesClient::factory( $params );
		} catch( Exception $e ) {
			return new WP_Error( get_class( $e ), $e->getMessage() );
		}

		return $this->client;
	}
}
