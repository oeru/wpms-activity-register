<?php
/**
 * @package Activity Register
 */
/*
Plugin Name: MultiSite Activity Register
Plugin URI: http://github.com/oeru/wpms-activity-register
Description: Record per-site multisite statistics related to site CRUD and site users to allow assessing participation over time.
Version: 0.0.1
Author: Dave Lane
Author URI: https://davelane.nz
License: GPLv2 or later
Network: true
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/
// plugin computer name
define('ACTREG_NAME', 'wpms-activity-register');
// current version
define('ACTREG_VERSION', '0.0.2');
// the path to this file
define('ACTREG_FILE', __FILE__);
// absolute URL for this plugin, including site name, e.g.
// https://sitename.nz/wp-content/plugins/
define('ACTREG_URL', plugins_url("/", __FILE__));
// absolute server path to this plugin
define('ACTREG_PATH', plugin_dir_path(__FILE__));
// module details
define('ACTREG_SLUG', 'actreg_sync');
define('ACTREG_TITLE', 'Activity Register');
define('ACTREG_MENU', 'Activity Register');
// admin details
define('ACTREG_ADMIN_SLUG', 'actreg_settings');
define('ACTREG_ADMIN_TITLE', 'Activity Register Settings');
define('ACTREG_ADMIN_MENU', 'Act Reg Settings');
// turn on debugging with true, off with false
define('ACTREG_DEBUG', true);
define('ACTREG_TABLE', 'activity_register');

// include Activity Register API and Auth code
//include_once ACTREG_PATH . '/vendor/autoload.php';
// the rest of the app
require ACTREG_PATH . '/includes/actreg.php';
//include_once ACTREG_PATH . '/includes/actreg.php';

/**
 * Start the plugin only if in Admin side and if site is Multisite
 * see http://stackoverflow.com/questions/13960514/how-to-adapt-my-plugin-to-multisite/
 */
if (is_admin() && is_multisite()) {
    add_action('plugins_loaded',
        array(ActivityRegister::get_instance(), 'init')
    );
}
