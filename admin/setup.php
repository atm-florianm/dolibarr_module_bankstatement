<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2020 SuperAdmin
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
 */

/**
 * \file    bankstatementimport/admin/setup.php
 * \ingroup bankstatementimport
 * \brief   BankStatementImport setup page.
 */

// Load Dolibarr environment
$mainIncludePath = '../../main.inc.php';
for ($resInclude = 0, $depth = 0; !$resInclude && $depth < 5; $depth++) {
	$resInclude = @include $mainIncludePath;
	$mainIncludePath = '../' . $mainIncludePath;
}
if (!$resInclude) die ('Unable to include main.inc.php');

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/bankstatementimport.lib.php';

// Translations
$langs->loadLangs(array("admin", "bankstatementimport@bankstatementimport"));

// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$defaultParameters = array(
	'css'       => 'minwidth500',
	'enabled'   => 1,
	'type'      => 'text'
);
$specificParameters=array(
	'BANKSTATEMENTIMPORT_SEPARATOR'                           => array(),
	'BANKSTATEMENTIMPORT_MAPPING'                             => array(),
	'BANKSTATEMENTIMPORT_DATE_FORMAT'                         => array(),
	'BANKSTATEMENTIMPORT_HEADER'                              => array('type' => 'bool'),
	'BANKSTATEMENTIMPORT_MAC_COMPATIBILITY'                   => array('type' => 'bool'),
	'BANKSTATEMENTIMPORT_HISTORY_IMPORT'                      => array('type' => 'bool'),
	'BANKSTATEMENTIMPORT_ALLOW_INVOICE_FROM_SEVERAL_THIRD'    => array('type' => 'bool'),
	'BANKSTATEMENTIMPORT_ALLOW_DRAFT_INVOICE'                 => array('type' => 'bool'),
	'BANKSTATEMENTIMPORT_UNCHECK_ALL_LINES'                   => array('type' => 'bool'),
	'BANKSTATEMENTIMPORT_AUTO_CREATE_DISCOUNT'                => array('type' => 'bool'),
	'BANKSTATEMENTIMPORT_MATCH_BANKLINES_BY_AMOUNT_AND_LABEL' => array('type' => 'bool'),
	'BANKSTATEMENTIMPORT_ALLOW_FREELINES'                     => array('type' => 'bool')
);
$arrayofparameters = array_map(
	function($specificParameters) use ($defaultParameters) {
		return $specificParameters + $defaultParameters; // specific parameters override default
	},
	$specificParameters
);

/*
 * Actions: in update mode, automatically set config values for parameters that exist in the keys of $arrayofparameters
 */
//if ((float) DOL_VERSION >= 6) include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

/*
 * View
 */

$page_name = "BankStatementImportSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_bankstatementimport@bankstatementimport');

// Configuration header
$head = bankstatementimportAdminPrepareHead();
dol_fiche_head($head, 'settings', '', -1, "bankstatementimport@bankstatementimport");

function get_form_for_input($confName, $parameters) {
	global $conf, $langs;
	$script = '<script type="application/javascript"></script>';
	return sprintf(
		'<form method="POST" action="%s">'
		. '<input type="hidden" name="token" value="%S" />'
		. '</form>');
}

function get_conf_input($confName, $parameters, $mode='edit') {
	global $conf, $langs;
	$value = isset($conf->global->{$confName}) ? $conf->global->{$confName} : '';
	$inputAttrs = sprintf(
		'name="%s" id="%s" class="%s"',
		htmlspecialchars($confName, ENT_COMPAT),
		htmlspecialchars($confName, ENT_COMPAT),
		htmlspecialchars($parameters['css'], ENT_COMPAT)
	);
	switch ($parameters['type']) {
		case 'bool': return ajax_constantonoff($confName);
		case 'text': return sprintf(
				'<input %s type="text" value="%s" /> <button class="but" id="save_%s">%s</button>',
				$inputAttrs,
				htmlspecialchars($value, ENT_COMPAT),
				$confName,
				$langs->trans('Modify')
			) . '<script type="text/javascript">$(()=>ajaxSaveOnClick("'.htmlspecialchars($confName, ENT_COMPAT).'"));</script>';
	}
	return $value;
}

// Setup page goes here
?>
<p><?php echo $langs->trans("BankStatementImportSetupPage"); ?></p>
<form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
	<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>" />
	<input type="hidden" name="action" value="update" />
	<table class="noborder" width="100%">
		<thead>
		<tr class="liste_titre">
			<td class="titlefield">
				<?php echo $langs->trans("Parameter"); ?>
			</td>
			<td>
				<?php echo $langs->trans("Value"); ?>
			</td>
		</tr>
		</thead>
		<tbody>
		<?php
		foreach ($arrayofparameters as $confName => $confParams) {
			printf(
				'<tr class="oddeven">'
				. '<td>%s</td>'
				. '<td>%s</td>'
				. '</tr>',
				$langs->trans($confName),
				get_conf_input($confName, $arrayofparameters[$confName])
			);
		}
		?>
		</tbody>
	</table>
</form>

<?php

goto stop;
if ($action == 'edit')
{
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="update">';

	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

	foreach($arrayofparameters as $key => $val)
	{
		print '<tr class="oddeven"><td>';
		print $form->textwithpicto($langs->trans($key),$langs->trans($key.'Tooltip'));
		print '</td><td><input name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth200':$val['css']).'" value="' . $conf->global->$key . '"></td></tr>';
	}
	print '</table>';

	print '<br><div class="center">';
	print '<input class="button" type="submit" value="'.$langs->trans("Save").'">';
	print '</div>';

	print '</form>';
	print '<br>';
}
else
{
	if (! empty($arrayofparameters))
	{
		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

		foreach($arrayofparameters as $key => $val)
		{
			print '<tr class="oddeven"><td>';
			print $form->textwithpicto($langs->trans($key),$langs->trans($key.'Tooltip'));
			print '</td><td>' . $conf->global->$key . '</td></tr>';
		}

		print '</table>';

		print '<div class="tabsAction">';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit">'.$langs->trans("Modify").'</a>';
		print '</div>';
	}
	else
	{
		print '<br>'.$langs->trans("NothingToSetup");
	}
}
stop:

// Page end
dol_fiche_end();

llxFooter();
$db->close();

