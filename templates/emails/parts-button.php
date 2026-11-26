<?php
/**
 * "Ver el pedido" button for the owner's emails.
 *
 * @package FaciloBotonArrepentimiento
 *
 * @var array $v Variables.
 */

defined( 'ABSPATH' ) || exit;
?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 0"><tr><td style="border-radius:999px;background:#241c16">
<a href="<?php echo esc_url( $v['url'] ); ?>" style="display:inline-block;padding:12px 24px;color:#ffffff;font-weight:700;text-decoration:none;border-radius:999px"><?php esc_html_e( 'Ver el pedido', 'facilo-boton-arrepentimiento' ); ?></a>
</td></tr></table>
