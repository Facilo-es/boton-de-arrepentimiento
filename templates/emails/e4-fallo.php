<?php
/**
 * E4: to the owner, the code email failed.
 *
 * @package FaciloBotonArrepentimiento
 *
 * @var array $v Variables.
 */

defined( 'ABSPATH' ) || exit;
?>
<p style="margin:0 0 14px">
<?php
/* translators: %s: customer email. */
echo esc_html( sprintf( __( 'Probamos varias veces y el correo a %s no salió. Tu cliente vio el código en pantalla, pero no lo tiene por correo.', 'facilo-boton-arrepentimiento' ), $v['email'] ) );
?>
</p>
<?php require __DIR__ . '/parts-code.php'; ?>
<p style="margin:0"><?php esc_html_e( 'Escribile vos, y revisá el envío de correos de tu sitio.', 'facilo-boton-arrepentimiento' ); ?></p>
<?php require __DIR__ . '/parts-button.php'; ?>
