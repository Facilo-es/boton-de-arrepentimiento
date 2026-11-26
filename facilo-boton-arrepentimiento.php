<?php
/**
 * Plugin Name:       Facilo Botón de Arrepentimiento y Baja
 * Description:       Mostrá los botones de arrepentimiento y baja, dale un código a cada cliente en el momento y que ningún pedido se te pierda.
 * Plugin URI:        https://facilo.es/proyectos/boton-claro/
 * Version:           1.0.2
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Facilo
 * Author URI:        https://facilo.es/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       facilo-boton-arrepentimiento
 * Domain Path:       /languages
 * WC requires at least: 9.0
 * WC tested up to:   11.2
 *
 * @package FaciloBotonArrepentimiento
 */

defined( 'ABSPATH' ) || exit;

define( 'BCLARO_VERSION', '1.0.2' );
define( 'BCLARO_DB_VERSION', '2' );
define( 'BCLARO_FILE', __FILE__ );
define( 'BCLARO_DIR', plugin_dir_path( __FILE__ ) );
define( 'BCLARO_URL', plugin_dir_url( __FILE__ ) );
define( 'BCLARO_PRO_URL', 'https://checkout.freemius.com/plugin/44794/plan/78947/' );

require_once BCLARO_DIR . 'includes/class-util.php';
require_once BCLARO_DIR . 'includes/class-kses.php';
require_once BCLARO_DIR . 'includes/class-install.php';
require_once BCLARO_DIR . 'includes/class-settings.php';
require_once BCLARO_DIR . 'includes/class-styles.php';
require_once BCLARO_DIR . 'includes/class-codes.php';
require_once BCLARO_DIR . 'includes/class-calendar.php';
require_once BCLARO_DIR . 'includes/class-requests.php';
require_once BCLARO_DIR . 'includes/class-buttons.php';
require_once BCLARO_DIR . 'includes/class-pages.php';
require_once BCLARO_DIR . 'includes/class-formblock.php';
require_once BCLARO_DIR . 'includes/class-service.php';
require_once BCLARO_DIR . 'includes/class-spamguard.php';
require_once BCLARO_DIR . 'includes/class-orderlink.php';
require_once BCLARO_DIR . 'includes/class-mailer.php';
require_once BCLARO_DIR . 'includes/class-retry.php';
require_once BCLARO_DIR . 'includes/class-rest.php';
require_once BCLARO_DIR . 'includes/class-notice.php';
require_once BCLARO_DIR . 'includes/class-privacy.php';
require_once BCLARO_DIR . 'includes/class-retention.php';
require_once BCLARO_DIR . 'includes/class-admin.php';
require_once BCLARO_DIR . 'includes/class-adminlist.php';
require_once BCLARO_DIR . 'includes/class-adminrequest.php';
require_once BCLARO_DIR . 'includes/class-adminsettings.php';
require_once BCLARO_DIR . 'includes/class-setup.php';
require_once BCLARO_DIR . 'includes/class-sitecheck.php';

register_activation_hook( __FILE__, array( 'BotonClaro\\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BotonClaro\\Install', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

BotonClaro\Install::boot();
BotonClaro\Buttons::boot();
BotonClaro\Pages::boot();
BotonClaro\FormBlock::boot();
BotonClaro\Service::boot();
BotonClaro\Rest::boot();
BotonClaro\Retry::boot();
BotonClaro\Notice::boot();
BotonClaro\Privacy::boot();
BotonClaro\Retention::boot();
if ( is_admin() ) {
	BotonClaro\Admin::boot();
}
