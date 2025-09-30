<?php
/**
 * MonsterDevs Tools
 *
 * @copyright    Copyright (C) 2025, Jaed Mosharraf
 * @link         https://jaedpro.com
 * @since        1.0.0
 *
 * Plugin Name:       MonsterDevs Tools
 * Version:           1.0.0
 * Plugin URI:        https://jaedpro.com
 * Description:       Helpers Package for MonsterDevs Tools from Jaed Mosharraf.
 * Author:            Jaed Mosharraf
 * Author URI:        https://jaedpro.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 6.0
 * Tested up to:      6.8
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloading.
 */
$path = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $path ) ) {
	include $path;
} else {
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error">
			<p><?php _e( 'Please run <code>composer install</code> to use MonsterDevs Tools Helpers Package as a plugin.', 'monsterdevs-tools' ); ?></p>
		</div>
			<?php
		}
	);
}
