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
	 * @since  0.0.1
	 * @access public
	 * @param  string $to
	 * @param  string $subject
	 * @param  string $message
	 * @param  mixed  $headers
	 * @param  array  $attachments
	 * @return bool true if mail has been sent, false if it failed
	 */
	public function send_wp_mail( $to, $subject, $message, $headers = [], $attachments = [] ) {

		// Compact the input, apply the filters, and extract them back out
		extract( apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) ) ); // @codingStandardsIgnoreLine

		// Get headers as array
		if ( empty( $headers ) ) {
			$headers = [];
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
		$sitename = strtolower( wp_parse_url( site_url(), PHP_URL_HOST ) );
		if ( 'www.' === substr( $sitename, 0, 4 ) ) {
			$sitename = substr( $sitename, 4 );
		}

		$from_email = 'no-reply@' . $sitename;

		$message_args = [
			// Email
			'subject'                    => $subject,
			'to'                         => $to,
			'headers'                    => [
				'Content-Type'           => apply_filters( 'wp_mail_content_type', 'text/plain' ),
				'From'                   => sprintf( '"%s" <%s>', apply_filters( 'wp_mail_from_name', get_bloginfo( 'name' ) ), apply_filters( 'wp_mail_from', $from_email ) ),
			],
		];
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
			$args = [];
			if ( ! empty( $attachments ) ) {
				$args = [
					'RawMessage' => [
						'Data' => $this->get_raw_message( $to, $subject, $message, $headers, $attachments ),
					],
				];

				$args = apply_filters( 'aws_ses_wp_mail_ses_send_raw_message_args', $args, $to, $subject, $message, $headers, $attachments );

				$result = $ses->sendRawEmail( $args );
			} else {
				$args = [
					'Source'      => $message_args['headers']['From'],
					'Destination' => [
						'ToAddresses' => $message_args['to'],
					],
					'Message'     => [
						'Subject' => [
							'Data'    => $message_args['subject'],
							'Charset' => get_bloginfo( 'charset' ),
						],
						'Body'   => [],
					],
				];

				if ( isset( $message_args['text'] ) ) {
					$args['Message']['Body']['Text'] = [
						'Data'    => $message_args['text'],
						'Charset' => get_bloginfo( 'charset' ),
					];
				}

				if ( isset( $message_args['html'] ) ) {
					$args['Message']['Body']['Html'] = [
						'Data'    => $message_args['html'],
						'Charset' => get_bloginfo( 'charset' ),
					];
				}

				if ( ! empty( $message_args['headers']['Reply-To'] ) ) {
					$replyto = explode( ',', $message_args['headers']['Reply-To'] );
					$args['ReplyToAddresses'] = array_map( 'trim', $replyto );
				}

				foreach ( [ 'Cc', 'Bcc' ] as $type ) {
					if ( empty( $message_args['headers'][ $type ] ) ) {
						continue;
					}

					$addrs = explode( ',', $message_args['headers'][ $type ] );
					$args['Destination'][ $type . 'Addresses' ] = array_map( 'trim', $addrs );
				}

				$args = apply_filters( 'aws_ses_wp_mail_ses_send_message_args', $args, $message_args );
				$result = $ses->sendEmail( $args );
			}
		} catch ( Exception $e ) {
			do_action( 'aws_ses_wp_mail_ses_error_sending_message', $e, $args, $message_args );
			return new WP_Error( get_class( $e ), $e->getMessage() );
		}

		do_action( 'aws_ses_wp_mail_ses_sent_message', $result, $args, $message_args );

		return true;
	}

	/**
	 * Generate raw multipart email string.
	 *
	 * @param array|string $to
	 * @param string       $subject
	 * @param string       $message
	 * @param array        $headers
	 * @param array        $attachments
	 *
	 * @return string
	 */
	protected function get_raw_message( $to, $subject, $message, $headers = [], $attachments = [] ) {
		// Initial headers
		$custom_from        = false;
		$raw_message_header = '';

		$cc = [];
		$bcc = [];
		$reply_to = [];

		// Filter initial content type, if custom header present then it will overwrite it.
		$content_type = apply_filters( 'wp_mail_content_type', 'text/plain' );

		if ( ! empty( $headers ) ) {
			// Iterate through the raw headers
			foreach ( $headers as $name => $content ) {
				$name    = trim( $name );
				$content = trim( $content );
				switch ( strtolower( $name ) ) {
					case 'from':
						// Gravity forms allow custom from header, so it will overwrite the from value.
						$custom_from = $content;
						break;
					case 'content-type':
						// if content-type header present and contains charset details, extract it for multipart.
						if ( strpos( $content, ';' ) !== false ) {
							list( $type, $charset_content ) = explode( ';', $content );
							$content_type                   = trim( $type );
							if ( false !== stripos( $charset_content, 'charset=' ) ) {
								$charset = trim( str_replace( [ 'charset=', '"' ], '', $charset_content ) );
							} elseif ( false !== stripos( $charset_content, 'boundary=' ) ) {
								$boundary = trim(
									str_replace(
										[
											'BOUNDARY=',
											'boundary=',
											'"',
										],
										'',
										$charset_content
									)
								);
								$charset  = '';
							}
						} elseif ( '' !== trim( $content ) ) {
							$content_type = trim( $content );
						}
						break;
					case 'cc':
						$cc = array_merge( (array) $cc, explode( ',', $content ) );
						break;
					case 'bcc':
						$bcc = array_merge( (array) $bcc, explode( ',', $content ) );
						break;
					case 'reply-to':
						$reply_to = array_merge( (array) $reply_to, explode( ',', $content ) );
						break;
					default:
						$raw_message_header .= $name . ': ' . str_replace(
							[
								"\r\n",
								"\r",
								"\n",
							],
							'',
							$content
						) . "\n";
						break;
				}
			}
		}

		// Get the site domain and get rid of www.
		$sitename = strtolower( wp_parse_url( site_url(), PHP_URL_HOST ) );
		if ( 'www.' === substr( $sitename, 0, 4 ) ) {
			$sitename = substr( $sitename, 4 );
		}

		$from_email = 'wordpress@' . $sitename;

		// If custom from address is not present in header, generate it.
		if ( ! $custom_from ) {
			$custom_from = sprintf( '"%s" <%s>', apply_filters( 'wp_mail_from_name', get_bloginfo( 'name' ) ), apply_filters( 'wp_mail_from', $from_email ) );
		}
		$boundary     = 'aws-ses-wp-mail-' . wp_rand();
		$raw_message  = $raw_message_header;
		$raw_message .= 'To: ' . $this->trim_recipients( $to ) . "\n";
		$raw_message .= 'From: ' . $custom_from . "\n";
		$raw_message .= 'Reply-To: ' . $this->trim_recipients( $reply_to ) . "\n";

		if ( ! empty( $cc ) ) {
			$raw_message .= 'CC: ' . $this->trim_recipients( $cc ) . "\n";
		}
		if ( ! empty( $bcc ) ) {
			$raw_message .= 'BCC: ' . $this->trim_recipients( $bcc ) . "\n";
		}

		if ( $subject != null && strlen( $subject ) > 0 ) {
			$raw_message .= 'Subject: ' . $subject . "\n";
		}

		$raw_message .= 'MIME-Version: 1.0' . "\n";
		$raw_message .= sprintf( 'Content-Type: Multipart/Mixed; boundary="%s"', esc_attr( $boundary ) ) . "\n";
		$raw_message .= sprintf( "\n--%s\n", $boundary );
		$raw_message .= sprintf( 'Content-Type: Multipart/Alternative; boundary="alt-%s"', $boundary ) . "\n";

		$charset = empty( $charset ) ? '' : sprintf( '; charset="%s";', esc_attr( $charset ) );
		if ( $content_type && strpos( $content_type, 'text/plain' ) === false && strlen( $message ) > 0 ) {
			$raw_message .= sprintf( "\n--alt-%s\n", $boundary );
			$raw_message .= sprintf( 'Content-Type: text/html%s', $charset ) . "\n\n";
			$raw_message .= $message . "\n";
		} elseif ( strlen( $message ) > 0 ) {
			$raw_message .= sprintf( "\n--alt-%s\n", $boundary );
			$raw_message .= sprintf( 'Content-Type: text/plain%s', $charset ) . "\n\n";
			$raw_message .= $message . "\n";
		}
		$raw_message .= sprintf( "\n--alt-%s--\n", $boundary );

		foreach ( $attachments as $attachment ) {
			if ( ! file_exists( $attachment ) ) {
				continue;
			}

			$filename = basename( $attachment );

			$data = file_get_contents( $attachment ); // phpcs:ignore:WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			$file_type = wp_check_filetype( $filename );

			// If mime type is not present, set it as octet-stream
			if ( ! $file_type['type'] ) {
				$file_type['type'] = 'application/octet-stream';
			}

			$raw_message .= sprintf( "\n--%s\n", $boundary );
			$raw_message .= sprintf( 'Content-Type: %1$s; name="%2$s"', $file_type['type'], esc_attr( $filename ) ) . "\n";
			$raw_message .= 'Content-Disposition: attachment' . "\n";
			$raw_message .= 'Content-Transfer-Encoding: base64' . "\n";
			$raw_message .= "\n" . chunk_split( base64_encode( $data ), 76, "\n" ) . "\n";
		}

		$raw_message .= sprintf( "\n--%s--\n", $boundary );

		return $raw_message;
	}

	/**
	 * Trim recipients addresses.
	 *
	 * @param  string|array $recipient Single recipient or array of recipients
	 *
	 * @return string            Trimmed recipients joined with comma
	 */
	public function trim_recipients( $recipient ) {
		if ( is_array( $recipient ) ) {
			return join( ', ', array_map( [ $this, 'trim_recipients' ], $recipient ) );
		}

		return trim( $recipient );
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

		$params = [
			'version' => 'latest',
		];

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
		} catch ( Exception $e ) {
			return new WP_Error( get_class( $e ), $e->getMessage() );
		}

		return $this->client;
	}
}
