<?php
/**
 * The public form. Variables: $type, $s (settings), $contact, $privacy.
 *
 * No personal data is printed from the server, so the page can be cached.
 *
 * @package BotonClaro
 */

defined( 'ABSPATH' ) || exit;

$bclaro_is_baja = 'baja' === $type;
$bclaro_id      = 'bc-' . $type;
?>
<div class="bc bc-<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $bclaro_id ); ?>" data-bclaro-form="<?php echo esc_attr( $type ); ?>" data-rest="<?php echo esc_url( \BotonClaro\Rest::url() ); ?>" data-yo="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bclaro_yo' ) ); ?>" data-contact="<?php echo esc_attr( $contact ); ?>">
	<div class="bc-head">
		<?php if ( $bclaro_is_baja ) : ?>
			<p><?php esc_html_e( 'Si querés dar de baja un servicio o una suscripción, podés hacerlo acá. No hace falta crear una cuenta ni llamar a nadie.', 'boton-de-arrepentimiento' ); ?></p>
			<p><?php esc_html_e( 'Completá estos datos y te damos un código en el momento. Después te mandamos una constancia de la baja, sin costo, dentro de las 72 horas.', 'boton-de-arrepentimiento' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Si compraste online y te arrepentiste, podés pedir la devolución acá. No hace falta crear una cuenta ni llamar a nadie.', 'boton-de-arrepentimiento' ); ?></p>
			<p><?php esc_html_e( 'Completá estos datos y te damos un código en el momento.', 'boton-de-arrepentimiento' ); ?></p>
		<?php endif; ?>
	</div>

	<noscript><p class="bc-form bc-nojs">
		<?php
		/* translators: %s: contact email. */
		echo esc_html( sprintf( __( 'Para usar este formulario necesitás JavaScript. También podés escribirnos a %s.', 'boton-de-arrepentimiento' ), $contact ) );
		?>
	</p></noscript>

	<form class="bc-form" novalidate hidden>
		<input type="hidden" name="tipo" value="<?php echo esc_attr( $type ); ?>">
		<div class="bc-hp" aria-hidden="true">
			<label for="<?php echo esc_attr( $bclaro_id ); ?>-web">Web</label>
			<input type="text" id="<?php echo esc_attr( $bclaro_id ); ?>-web" name="web" tabindex="-1" autocomplete="off">
		</div>

		<div class="bc-f">
			<label for="<?php echo esc_attr( $bclaro_id ); ?>-nombre"><?php esc_html_e( 'Nombre y apellido', 'boton-de-arrepentimiento' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $bclaro_id ); ?>-nombre" name="nombre" maxlength="120" autocomplete="name" required>
		</div>
		<div class="bc-f">
			<label for="<?php echo esc_attr( $bclaro_id ); ?>-correo"><?php esc_html_e( 'Correo electrónico', 'boton-de-arrepentimiento' ); ?></label>
			<input type="email" id="<?php echo esc_attr( $bclaro_id ); ?>-correo" name="correo" maxlength="190" autocomplete="email" required aria-describedby="<?php echo esc_attr( $bclaro_id ); ?>-correo-h">
			<small id="<?php echo esc_attr( $bclaro_id ); ?>-correo-h"><?php esc_html_e( 'Ahí te mandamos el código.', 'boton-de-arrepentimiento' ); ?></small>
		</div>
		<div class="bc-f">
			<label for="<?php echo esc_attr( $bclaro_id ); ?>-telefono"><?php esc_html_e( 'Teléfono (opcional)', 'boton-de-arrepentimiento' ); ?></label>
			<input type="tel" id="<?php echo esc_attr( $bclaro_id ); ?>-telefono" name="telefono" maxlength="40" autocomplete="tel">
		</div>

		<?php if ( $bclaro_is_baja ) : ?>
			<div class="bc-f">
				<label for="<?php echo esc_attr( $bclaro_id ); ?>-servicio"><?php esc_html_e( '¿Qué servicio querés dar de baja?', 'boton-de-arrepentimiento' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $bclaro_id ); ?>-servicio" name="servicio" maxlength="200" required>
			</div>
			<div class="bc-f">
				<label for="<?php echo esc_attr( $bclaro_id ); ?>-pedido"><?php esc_html_e( 'Número de cliente, pedido o contrato (opcional)', 'boton-de-arrepentimiento' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $bclaro_id ); ?>-pedido" name="pedido" maxlength="60" aria-describedby="<?php echo esc_attr( $bclaro_id ); ?>-pedido-h">
				<small id="<?php echo esc_attr( $bclaro_id ); ?>-pedido-h"><?php esc_html_e( 'Si no lo tenés, dejalo vacío.', 'boton-de-arrepentimiento' ); ?></small>
			</div>
		<?php else : ?>
			<div class="bc-f">
				<label for="<?php echo esc_attr( $bclaro_id ); ?>-pedido"><?php esc_html_e( 'Número de pedido (opcional)', 'boton-de-arrepentimiento' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $bclaro_id ); ?>-pedido" name="pedido" maxlength="60" aria-describedby="<?php echo esc_attr( $bclaro_id ); ?>-pedido-h">
				<small id="<?php echo esc_attr( $bclaro_id ); ?>-pedido-h"><?php esc_html_e( 'Lo encontrás en el correo de tu compra. Si no lo tenés, dejalo vacío.', 'boton-de-arrepentimiento' ); ?></small>
			</div>
			<fieldset class="bc-scope">
				<legend><?php esc_html_e( '¿Qué querés devolver?', 'boton-de-arrepentimiento' ); ?></legend>
				<label><input type="radio" name="alcance" value="todo" checked><span><?php esc_html_e( 'Toda la compra', 'boton-de-arrepentimiento' ); ?></span></label>
				<label><input type="radio" name="alcance" value="parte"><span><?php esc_html_e( 'Una parte', 'boton-de-arrepentimiento' ); ?></span></label>
			</fieldset>
			<div class="bc-f bc-detail" hidden>
				<label for="<?php echo esc_attr( $bclaro_id ); ?>-detalle"><?php esc_html_e( 'Contanos qué querés devolver', 'boton-de-arrepentimiento' ); ?></label>
				<textarea id="<?php echo esc_attr( $bclaro_id ); ?>-detalle" name="detalle" rows="3" maxlength="1000"></textarea>
			</div>
		<?php endif; ?>

		<div class="bc-f">
			<label for="<?php echo esc_attr( $bclaro_id ); ?>-mensaje"><?php esc_html_e( 'Mensaje (opcional)', 'boton-de-arrepentimiento' ); ?></label>
			<textarea id="<?php echo esc_attr( $bclaro_id ); ?>-mensaje" name="mensaje" rows="3" maxlength="3000"></textarea>
		</div>

		<p class="bc-error" role="alert" hidden></p>

		<button type="submit" class="bc-send">
			<?php
			if ( $bclaro_is_baja ) {
				esc_html_e( 'Pedir la baja', 'boton-de-arrepentimiento' );
			} else {
				esc_html_e( 'Enviar pedido de arrepentimiento', 'boton-de-arrepentimiento' );
			}
			?>
		</button>
		<p class="bc-note">
			<?php esc_html_e( 'Usamos estos datos solo para responder tu pedido.', 'boton-de-arrepentimiento' ); ?>
			<?php if ( $privacy ) : ?>
				<a href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'Política de privacidad', 'boton-de-arrepentimiento' ); ?></a>
			<?php endif; ?>
		</p>
	</form>

	<div class="bc-ok" tabindex="-1" hidden>
		<h2><?php echo $bclaro_is_baja ? esc_html__( 'Recibimos tu pedido de baja', 'boton-de-arrepentimiento' ) : esc_html__( 'Recibimos tu pedido', 'boton-de-arrepentimiento' ); ?></h2>
		<p class="bc-dup" hidden><?php esc_html_e( 'Ya habíamos recibido este pedido. Tu código es el mismo:', 'boton-de-arrepentimiento' ); ?></p>
		<p class="bc-cl"><?php esc_html_e( 'Tu código es', 'boton-de-arrepentimiento' ); ?></p>
		<p class="bc-code"><span data-bc="code1"></span><span data-bc="code2"></span></p>
		<button type="button" class="bc-copy" data-done="<?php esc_attr_e( 'Copiado', 'boton-de-arrepentimiento' ); ?>"><?php esc_html_e( 'Copiar código', 'boton-de-arrepentimiento' ); ?></button>
		<p class="bc-keep">
			<?php
			/* translators: %s: customer's email, filled in by the browser. */
			echo wp_kses( sprintf( __( 'Guardalo: lo vas a necesitar si nos consultás. También te lo mandamos a %s.', 'boton-de-arrepentimiento' ), '<b data-bc="email"></b>' ), array( 'b' => array( 'data-bc' => true ) ) );
			?>
		</p>
		<p class="bc-next"><b><?php esc_html_e( 'Qué sigue:', 'boton-de-arrepentimiento' ); ?></b>
			<?php
			if ( $bclaro_is_baja ) {
				esc_html_e( 'revisamos tu pedido y te mandamos la constancia de la baja, sin costo, dentro de las 72 horas.', 'boton-de-arrepentimiento' );
			} else {
				esc_html_e( 'revisamos tu pedido y te escribimos con los pasos para devolver tu compra.', 'boton-de-arrepentimiento' );
			}
			?>
		</p>
		<p class="bc-help">
			<?php
			/* translators: %s: contact email. */
			echo esc_html( sprintf( __( '¿No te llegó el correo? Mirá en spam o escribinos a %s.', 'boton-de-arrepentimiento' ), $contact ) );
			?>
		</p>
	</div>

	<?php if ( ! $bclaro_is_baja && '' !== trim( (string) $s['rights_text'] ) ) : ?>
		<div class="bc-rights"><?php echo \BotonClaro\FormBlock::rich( $s['rights_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in rich(). ?></div>
	<?php endif; ?>
	<?php echo \BotonClaro\Service::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render(). ?>
</div>
