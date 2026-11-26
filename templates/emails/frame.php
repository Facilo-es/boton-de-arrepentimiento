<?php
/**
 * Email frame: shop name and logo, the body, customer service. Tables and inline styles, for every client.
 *
 * @package FaciloBotonArrepentimiento
 *
 * @var array $v body, preheader, shop, logo, home, accent, att.
 */

defined( 'ABSPATH' ) || exit;

$bclaro_font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $v['shop'] ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4efe9">
<?php if ( '' !== $v['preheader'] ) : ?>
<div style="display:none;max-height:0;overflow:hidden;opacity:0"><?php echo esc_html( $v['preheader'] ); ?></div>
<?php endif; ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4efe9">
<tr><td align="center" style="padding:28px 12px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;font-family:<?php echo esc_attr( $bclaro_font ); ?>;color:#241c16">
<tr><td style="height:6px;line-height:6px;font-size:0;background:<?php echo esc_attr( $v['accent'] ); ?>">&nbsp;</td></tr>
<tr><td style="padding:24px 28px 8px">
<?php if ( '' !== $v['logo'] ) : ?>
<a href="<?php echo esc_url( $v['home'] ); ?>"><img src="<?php echo esc_url( $v['logo'] ); ?>" alt="<?php echo esc_attr( $v['shop'] ); ?>" style="max-height:44px;max-width:200px;border:0;display:block"></a>
<?php else : ?>
<a href="<?php echo esc_url( $v['home'] ); ?>" style="font-size:18px;font-weight:700;color:#241c16;text-decoration:none"><?php echo esc_html( $v['shop'] ); ?></a>
<?php endif; ?>
</td></tr>
<tr><td style="padding:12px 28px 24px;font-size:16px;line-height:1.55">
<?php echo wp_kses( $v['body'], \BotonClaro\Kses::allowed() ); ?>
</td></tr>
<?php if ( $v['att'] ) : ?>
<tr><td style="padding:18px 28px 24px;border-top:1px solid #eee5da;font-size:14px;line-height:1.6;color:#5c4f45">
<p style="margin:0 0 6px;font-weight:700;color:#241c16"><?php esc_html_e( 'Atención al cliente', 'facilo-boton-arrepentimiento' ); ?></p>
	<?php foreach ( $v['att'] as $bclaro_line ) : ?>
		<?php echo esc_html( $bclaro_line[0] ); ?>: <?php echo esc_html( $bclaro_line[1] ); ?><br>
<?php endforeach; ?>
</td></tr>
<?php endif; ?>
</table>
</td></tr>
</table>
</body>
</html>
