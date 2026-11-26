<?php
/**
 * E5: to the customer, plain proof of cancellation.
 *
 * @package FaciloBotonArrepentimiento
 *
 * @var array $v Variables.
 */

defined( 'ABSPATH' ) || exit;

$bclaro_rows = array(
	__( 'Código', 'facilo-boton-arrepentimiento' )     => $v['code'],
	__( 'Pedido recibido', 'facilo-boton-arrepentimiento' ) => $v['got'],
	__( 'Baja hecha', 'facilo-boton-arrepentimiento' ) => $v['done'],
);
?>
<p style="margin:0 0 14px">
<?php
/* translators: %s: customer name. */
echo esc_html( sprintf( __( 'Hola %s:', 'facilo-boton-arrepentimiento' ), $v['name'] ) );
?>
</p>
<p style="margin:0 0 18px">
<?php
/* translators: 1: service, 2: shop. */
echo esc_html( sprintf( __( 'Dejamos constancia de que diste de baja %1$s en %2$s.', 'facilo-boton-arrepentimiento' ), $v['service'], $v['shop'] ) );
?>
</p>
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 18px;background:#f8f3ee;border:1px solid #eadfd3;border-radius:14px;font-size:15px">
<?php foreach ( $bclaro_rows as $bclaro_k => $bclaro_val ) : ?>
<tr><td style="padding:10px 12px 10px 18px;color:#6b5d52;white-space:nowrap"><?php echo esc_html( $bclaro_k ); ?>:</td><td style="padding:10px 18px 10px 0;font-weight:600"><?php echo esc_html( $bclaro_val ); ?></td></tr>
<?php endforeach; ?>
</table>
<p style="margin:0"><?php esc_html_e( 'Esta constancia es sin costo. Guardala por si la necesitás.', 'facilo-boton-arrepentimiento' ); ?></p>
