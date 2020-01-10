<?php
/* Copyright (C) 2020 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Library javascript to enable Browser notifications
 */

if (!defined('NOREQUIREUSER'))  define('NOREQUIREUSER', '1');
if (!defined('NOREQUIREDB'))    define('NOREQUIREDB','1');
if (!defined('NOREQUIRESOC'))   define('NOREQUIRESOC', '1');
if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOLOGIN'))        define('NOLOGIN', 1);
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');

// Load Dolibarr environment
$mainIncludePath = '../../main.inc.php';
for ($resInclude = 0, $depth = 0; !$resInclude && $depth < 5; $depth++) {
	$resInclude = @include $mainIncludePath;
	$mainIncludePath = '../' . $mainIncludePath;
}
if (!$resInclude) die ('Unable to include main.inc.php');

global $langs, $user, $conf;

// Define js type
header('Content-Type: application/javascript');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) header('Cache-Control: max-age=60, public, must-revalidate');
else header('Cache-Control: no-cache');
$langs->load('bankstatementimport@bankstatementimport');

echo 'let _trans = ' . json_encode($langs->tab_translate) . ';';
?>
if (!_trans) _trans = [];

/**
 * Adds an onclick event to a "modify" button to save a configuration value.
 * Example:
 *   DOM: <input id="MY_CONF" name="MY_CONF" /> <button id="save_MY_CONF">Save</button>
 *   Js:  ajaxSaveOnClick("MY_CONF")
 *
 * @param code  The constant name; the DOM must have a HTMLElement with the id "save_" + code
 */
function ajaxSaveOnClick(code) {
	//console.log(code);
	let url = '<?php echo DOL_URL_ROOT; ?>/core/ajax/constantonoff.php';
	$("#save_" + code).click(
		function(ev) {
			let entity = '<?php echo $conf->entity; ?>';
			let value = $('#' + code).val()
			let action = 'set';
			if (value == '') action = 'del';
			ev.preventDefault();
			$.get(
				url,
				{
					action: action,
					name: code,
					entity: entity,
					value: value
				},
				function () {
					$.jnotify(_trans['ValueSaved']);
				}
			);
		}
	);
}


/* Javascript library of module BankStatementImport */
window.addEventListener('load', function() {
});
