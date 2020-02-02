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
$MAX_BACKTRACK=5; // max depth for finding 'main.inc.php' in parent directories
for ($resInclude = 0, $depth = 0; !$resInclude && $depth < $MAX_BACKTRACK; $depth++) {
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
$langs->load('bankstatement@bankstatement');


// including all translations is maybe a bit too heavy.

echo 'let _trans = ' . json_encode(
	array_intersect_key(
		$langs->tab_translate,
		array_flip(
			array(
				'valueSaved',
				'valueUnchanged',
				'noValueToSave',
			)
		)
	)
) . ';';
?>
if (typeof _trans === 'undefined') _trans = [];
function translate(key) {
	if (typeof _trans[key] === 'undefined') return key;
	return _trans[key];
}


$(function () {
	$('.ajaxSaveOnClick').each(
		function () {
			ajaxSaveOnClick($(this).attr('name'));
		}
	);
});

/**
 * Asynchronously save a setup config value when the conf-specific form is submitted.
 *
 * Example:
 *   DOM:
 *   <form id="form_save_MODULE_OPTION">
 *       <input id="MODULE_OPTION" name="MODULE_OPTION" />
 *       <button id="save_MODULE_OPTION">Save</button>
 *   </form>
 *   Js:  ajaxSaveOnClick("MODULE_OPTION")
 *
 * @param code  The constant name; the DOM must have a HTMLElement with the id "save_" + code
 */
window.ajaxSaveOnClick = function (code) {
	let url = <?php echo '"' . dol_buildpath('/bankstatement/scripts/interface.php', 1) . '"'; ?>;
	let $formField = $('#' + code);
	$formField.closest('form').submit(
		function(ev) {
			if (isUnsaved(code)) {
				let value = $('#' + code).val();
				//if (value == '') action = 'del';
				$.get(
					url,
					{
						accountId: window.jsonDataArray.accountId,
						set: 'bankstatementformatvalue',
						name: code,
						value: value
					},
					function (response) {
						try {
							response = JSON.parse(response);
							if (response.response === 'success') {
								$.jnotify(translate('ValueSaved')+ ' ' + translate(code), 'mesgs');
								$formField.attr('data-saved-value', value)
							}
							if (response.response === 'failure') {
								$.jnotify(translate('Error') + ' ' + translate(response.reason), 'error');
							}
						} catch(error) {
						}
					}
				);
			} else {
				$.jnotify(translate('valueUnchanged'), 'warning');
				ev.preventDefault();
				return;
			}
			ev.preventDefault();
		}
	);
}

/**
 * Toggle some visibility switches after a particular element is clicked.
 * Enables you to not display some conf inputs if a boolean config they depend
 * on is disabled.
 * @param confName
 * @param confName2
 */
window.setVisibilityDependency = function (confName, confName2) {
	console.log(confName, confName2);
	let toggleShowInput = function() {
		$('#' + confName2).closest('tr').toggleClass('hide_conf');
	};
	//$('#set_' + confName).click(toggleShowInput);
	//$('#del_' + confName).click(toggleShowInput);
	$('#' + confName).change(toggleShowInput); // todo: change this if the widget is changed
}

window.isUnsaved = function (confName) {
	let formField = $('#' + confName);
	return formField.val() != formField.attr('data-saved-value');
}

/**
 * Saves all unsaved confs (except those not displayed).
 *
 * @param confPrefix
 */
window.saveAll = function (confPrefix) {
	let toBeSaved = $('form[id^=form_save_' + confPrefix + ']:visible').filter(function(n, form) {
		let confName = form.id.replace(/^form_save_/g, '');
		return isUnsaved(confName);
	});
	if (toBeSaved.length === 0) {
		$.jnotify(translate('NoValueToSave'), 'warning');
	}
	toBeSaved.each(function(n, form) {
		/*
		Note : we trigger a button click instead of a form submit
		because form submit bypasses HTML5 validation: this would happily
		disregard the "required", "pattern", etc. attributes.
		*/
		//$(form).trigger('submit');
		$(form).find('button[id^=btn_save_]').trigger('click');
	});
}
