<?php
/**
 * The code box, shared by the customer emails.
 *
 * @package BotonClaro
 *
 * @var array $v Variables.
 */

defined( 'ABSPATH' ) || exit;
?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background:#f8f3ee;border:1px solid #eadfd3;border-radius:14px">
<tr><td style="padding:14px 20px">
<span style="display:block;font-size:13px;color:#6b5d52"><?php esc_html_e( 'Tu código es', 'boton-de-arrepentimiento' ); ?></span>
<strong style="display:block;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:24px;letter-spacing:.04em;color:#241c16"><?php echo esc_html( $v['code'] ); ?></strong>
</td></tr>
</table>
