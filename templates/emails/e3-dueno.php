<?php
/**
 * E3: to the owner, new request.
 *
 * @package BotonClaro
 *
 * @var array $v Variables.
 */

defined( 'ABSPATH' ) || exit;

$bclaro_r     = $v['req'];
$bclaro_who   = array_filter( array( $bclaro_r->name, $bclaro_r->email, $bclaro_r->phone ), 'strlen' );
$bclaro_data  = 'baja' === $bclaro_r->type ? trim( $bclaro_r->service . ( '' !== (string) $bclaro_r->order_ref ? ' (' . $bclaro_r->order_ref . ')' : '' ) ) : $v['order'];
$bclaro_data .= ( 'parte' === $bclaro_r->scope && '' !== (string) $bclaro_r->detail ) ? ' · ' . __( 'Una parte', 'boton-de-arrepentimiento' ) . ': ' . $bclaro_r->detail : '';
$bclaro_rows  = array(
	__( 'Código', 'boton-de-arrepentimiento' )            => $v['code'],
	__( 'Cliente', 'boton-de-arrepentimiento' )           => implode( ', ', $bclaro_who ),
	__( 'Pedido o servicio', 'boton-de-arrepentimiento' ) => $bclaro_data,
	__( 'Mensaje', 'boton-de-arrepentimiento' )           => '' !== (string) $bclaro_r->message ? $bclaro_r->message : '—',
);
?>
<?php if ( $bclaro_r->is_test ) : ?>
<p style="margin:0 0 14px;padding:8px 12px;background:#f8f3ee;border-radius:10px;font-size:14px"><?php esc_html_e( 'Este es un pedido de prueba: solo te llegó a vos, y no corre ningún plazo.', 'boton-de-arrepentimiento' ); ?></p>
<?php endif; ?>
<p style="margin:0 0 16px;font-size:18px;font-weight:700">
<?php
/* translators: 1: arrepentimiento or baja, 2: shop. */
echo esc_html( sprintf( __( 'Llegó un pedido de %1$s en %2$s.', 'boton-de-arrepentimiento' ), $v['type'], $v['shop'] ) );
?>
</p>
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 16px;font-size:15px">
<?php foreach ( $bclaro_rows as $bclaro_k => $bclaro_val ) : ?>
<tr><td style="padding:6px 12px 6px 0;color:#6b5d52;vertical-align:top;white-space:nowrap"><?php echo esc_html( $bclaro_k ); ?>:</td><td style="padding:6px 0;vertical-align:top"><?php echo nl2br( esc_html( $bclaro_val ) ); ?></td></tr>
<?php endforeach; ?>
</table>
<p style="margin:0">
<?php
/* translators: 1: date, 2: time. */
echo esc_html( sprintf( __( 'Tu cliente ya tiene su código. Tenés hasta el %1$s a las %2$s para responderle.', 'boton-de-arrepentimiento' ), $v['due_d'], $v['due_t'] ) );
?>
</p>
<?php require __DIR__ . '/parts-button.php'; ?>
