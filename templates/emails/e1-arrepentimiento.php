<?php
/**
 * E1: to the customer, arrepentimiento.
 *
 * @package FaciloBotonArrepentimiento
 *
 * @var array $v Variables.
 */

defined( 'ABSPATH' ) || exit;
?>
<p style="margin:0 0 14px">
<?php
/* translators: %s: customer name. */
echo esc_html( sprintf( __( 'Hola %s:', 'facilo-boton-arrepentimiento' ), $v['name'] ) );
?>
</p>
<p style="margin:0 0 18px">
<?php
/* translators: 1: shop, 2: date, 3: time. */
echo esc_html( sprintf( __( 'Recibimos tu pedido de arrepentimiento en %1$s el %2$s a las %3$s.', 'facilo-boton-arrepentimiento' ), $v['shop'], $v['date'], $v['time'] ) );
?>
</p>
<?php require __DIR__ . '/parts-code.php'; ?>
<p style="margin:0 0 18px">
<?php
/* translators: %s: order number or "sin indicar". */
echo esc_html( sprintf( __( 'Pedido: %s', 'facilo-boton-arrepentimiento' ), $v['order'] ) );
?>
</p>
<p style="margin:0"><strong><?php esc_html_e( 'Qué sigue:', 'facilo-boton-arrepentimiento' ); ?></strong> <?php esc_html_e( 'revisamos tu pedido y te escribimos a este correo con los pasos para devolver tu compra.', 'facilo-boton-arrepentimiento' ); ?></p>
