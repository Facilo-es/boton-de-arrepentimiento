<?php
/**
 * Emails E1 to E5 in one simple HTML frame, with a plain-text part for plain clients.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Mailer.
 */
class Mailer {

	/**
	 * Plain-text part of the message being sent.
	 *
	 * @var string
	 */
	private static $alt = '';

	/**
	 * Send one HTML email in Free's frame. $html is the body only.
	 *
	 * @param string $to      Address.
	 * @param string $subject Subject.
	 * @param string $html    Body HTML (already escaped).
	 * @param array  $args    reply_to, template, req, preheader.
	 * @return bool
	 */
	public static function send( $to, $subject, $html, $args = array() ) {
		$args    = wp_parse_args(
			$args,
			array(
				'reply_to'  => '',
				'template'  => '',
				'req'       => null,
				'preheader' => '',
			)
		);
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $args['reply_to'] ) ) {
			$headers[] = 'Reply-To: ' . $args['reply_to'];
		}
		$mail = array(
			'to'      => $to,
			'subject' => $subject,
			'html'    => self::frame( $html, $args['preheader'] ),
			'headers' => $headers,
		);
		/**
		 * Change an email before it goes.
		 *
		 * @param array       $mail     to, subject, html, headers.
		 * @param string      $template Template name.
		 * @param object|null $req      Request.
		 */
		$mail      = (array) apply_filters( 'bclaro_mail_args', $mail, $args['template'], $args['req'] );
		self::$alt = self::plain( $mail['html'] );
		add_action( 'phpmailer_init', array( __CLASS__, 'alt_body' ) );
		$ok = wp_mail( $mail['to'], $mail['subject'], $mail['html'], $mail['headers'] );
		remove_action( 'phpmailer_init', array( __CLASS__, 'alt_body' ) );
		self::$alt = '';
		return (bool) $ok;
	}

	/**
	 * Add the plain-text part.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $mailer Mailer.
	 */
	public static function alt_body( $mailer ) {
		if ( '' !== self::$alt ) {
			$mailer->AltBody = self::$alt; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
		}
	}

	/**
	 * Plain text from the HTML: line breaks kept, links written out.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function plain( $html ) {
		$t = preg_replace( '#<(head|style)[^>]*>.*?</\1>#is', '', $html );
		$t = preg_replace( '#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $t );
		$t = preg_replace( '#<br\s*/?>|</(p|div|tr|h1|h2|h3|li)>#i', "\n", $t );
		$t = html_entity_decode( wp_strip_all_tags( $t ), ENT_QUOTES, 'UTF-8' );
		$t = preg_replace( "/[ \t]+/", ' ', $t );
		$t = preg_replace( "/\n\s*\n\s*(\n\s*)+/", "\n\n", $t );
		return trim( implode( "\n", array_map( 'trim', explode( "\n", $t ) ) ) );
	}

	/**
	 * Path of a template. Filter bclaro_mail_template.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function path( $name ) {
		/**
		 * Path of an email template.
		 *
		 * @param string $path Path.
		 * @param string $name Name: frame, e1-arrepentimiento, e2-baja, e3-dueno, e4-fallo, e5-constancia.
		 */
		return (string) apply_filters( 'bclaro_mail_template', BCLARO_DIR . 'templates/emails/' . $name . '.php', $name );
	}

	/**
	 * Render a template with variables.
	 *
	 * @param string $name Name.
	 * @param array  $v    Variables, raw: the template escapes.
	 * @return string
	 */
	public static function render( $name, $v ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $v is read by the included template.
		$path = self::path( $name );
		if ( ! file_exists( $path ) ) {
			return '';
		}
		ob_start();
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * The frame around a body: shop name and logo, the body, customer service.
	 *
	 * @param string $body      Body HTML.
	 * @param string $preheader Hidden preview line.
	 * @return string
	 */
	public static function frame( $body, $preheader = '' ) {
		$s    = Settings::get();
		$logo = '';
		$id   = (int) get_theme_mod( 'custom_logo' );
		if ( $id ) {
			$logo = (string) wp_get_attachment_image_url( $id, 'medium' );
		}
		$v = array(
			'body'      => $body,
			'preheader' => $preheader,
			'shop'      => self::shop(),
			'logo'      => $logo,
			'home'      => home_url( '/' ),
			'accent'    => Styles::clean_color( $s['color'] ) ? Styles::clean_color( $s['color'] ) : '#a8421f',
			'att'       => Service::lines(),
		);
		return self::render( 'frame', $v );
	}

	/**
	 * Shop name.
	 *
	 * @return string
	 */
	public static function shop() {
		return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Common variables of a request.
	 *
	 * @param object $req Request.
	 * @return array
	 */
	private static function vars( $req ) {
		$ts = Util::ts( $req->created_at );
		return array(
			'req'   => $req,
			'shop'  => self::shop(),
			'name'  => $req->name,
			'code'  => $req->code,
			'date'  => Util::date( $ts ),
			'time'  => Util::time( $ts ),
			'order' => '' !== (string) $req->order_ref ? $req->order_ref : __( 'sin indicar', 'boton-de-arrepentimiento' ),
			'url'   => Util::admin_url( 'bclaro', array( 'id' => (int) $req->id ) ),
		);
	}

	/**
	 * E1 or E2 to the customer. Records the result on the request.
	 *
	 * @param object $req    Request.
	 * @param bool   $manual Pressed "Reintentar".
	 * @return bool
	 */
	public static function send_code( $req, $manual = false ) {
		$name = 'baja' === $req->type ? 'e2-baja' : 'e1-arrepentimiento';
		/* translators: %s: code. */
		$subject = 'baja' === $req->type ? __( 'Recibimos tu pedido de baja: código %s', 'boton-de-arrepentimiento' ) : __( 'Recibimos tu pedido de arrepentimiento: código %s', 'boton-de-arrepentimiento' );
		$v       = self::vars( $req );
		$ok      = self::send(
			$req->email,
			sprintf( $subject, $req->code ),
			self::render( $name, $v ),
			array(
				'reply_to'  => Util::contact_email(),
				'template'  => $name,
				'req'       => $req,
				'preheader' => $req->code,
			)
		);
		$tries   = (int) $req->code_mail_tries + 1;
		Requests::update(
			$req->id,
			array(
				'code_mail_status' => $ok ? 'enviado' : 'fallido',
				'code_mail_tries'  => $tries,
			)
		);
		/* translators: %d: try number. */
		$note = sprintf( __( 'Intento %d', 'boton-de-arrepentimiento' ), $tries );
		if ( $manual ) {
			Requests::event( $req->id, 'reintento', $note, get_current_user_id() );
		}
		Requests::event( $req->id, $ok ? 'codigo_enviado' : 'codigo_fallido', $note );
		if ( $ok ) {
			Retry::clear_alert( (int) $req->id );
		}
		return $ok;
	}

	/**
	 * E3 to the owner.
	 *
	 * @param object $req Request.
	 * @return bool
	 */
	public static function send_owner( $req ) {
		$v          = self::vars( $req );
		$due        = $req->reply_due_at ? Util::ts( $req->reply_due_at ) : Util::ts( $req->created_at ) + DAY_IN_SECONDS;
		$v['due_d'] = Util::date( $due );
		$v['due_t'] = Util::time( $due );
		$v['type']  = 'baja' === $req->type ? 'baja' : 'arrepentimiento';
		/* translators: 1: arrepentimiento or baja, 2: date, 3: time. */
		$subject = sprintf( __( 'Nuevo pedido de %1$s: respondé antes del %2$s a las %3$s', 'boton-de-arrepentimiento' ), $v['type'], $v['due_d'], $v['due_t'] );
		if ( $req->is_test ) {
			$subject = '[' . __( 'Prueba', 'boton-de-arrepentimiento' ) . '] ' . $subject;
		}
		$ok = self::send(
			Util::notify_email(),
			$subject,
			self::render( 'e3-dueno', $v ),
			array(
				'reply_to' => $req->is_test ? '' : $req->email,
				'template' => 'e3-dueno',
				'req'      => $req,
			)
		);
		Requests::event( $req->id, $ok ? 'aviso_enviado' : 'aviso_fallido' );
		return $ok;
	}

	/**
	 * E4 to the owner: the code never reached the customer.
	 *
	 * @param object $req Request.
	 * @return bool
	 */
	public static function send_fail( $req ) {
		$v = self::vars( $req );
		/* translators: %s: code. */
		$subject = sprintf( __( 'No pudimos mandarle el código a un cliente: %s', 'boton-de-arrepentimiento' ), $req->code );
		return self::send(
			Util::notify_email(),
			$subject,
			self::render( 'e4-fallo', $v + array( 'email' => $req->email ) ),
			array(
				'template' => 'e4-fallo',
				'req'      => $req,
			)
		);
	}

	/**
	 * E5 to the customer: the proof of cancellation.
	 *
	 * @param object $req     Request.
	 * @param int    $done_ts When the service was cancelled.
	 * @return bool
	 */
	public static function send_proof( $req, $done_ts ) {
		$v            = self::vars( $req );
		$v['service'] = $req->service;
		$v['got']     = Util::full( Util::ts( $req->created_at ) );
		$v['done']    = Util::full( $done_ts );
		/* translators: %s: code. */
		$subject = sprintf( __( 'Constancia de baja de servicio: %s', 'boton-de-arrepentimiento' ), $req->code );
		return self::send(
			$req->email,
			$subject,
			self::render( 'e5-constancia', $v ),
			array(
				'reply_to' => Util::contact_email(),
				'template' => 'e5-constancia',
				'req'      => $req,
			)
		);
	}

	/**
	 * A plain test email for Revisión.
	 *
	 * @return bool
	 */
	public static function send_test() {
		$body = '<h1 style="margin:0 0 12px;font-size:22px">' . esc_html__( 'Correo de prueba', 'boton-de-arrepentimiento' ) . '</h1><p style="margin:0">' . esc_html__( 'Si lees esto, tu sitio puede mandar correos. Volvé a Revisión y tocá "Me llegó".', 'boton-de-arrepentimiento' ) . '</p>';
		return self::send(
			Util::notify_email(),
			__( 'Correo de prueba de Botón Claro', 'boton-de-arrepentimiento' ),
			$body,
			array( 'template' => 'prueba' )
		);
	}
}
