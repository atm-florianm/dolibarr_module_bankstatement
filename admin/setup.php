<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2020 ATM Consulting
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
 * \file    bankstatement/admin/setup.php
 * \ingroup bankstatement
 * \brief   BankStatement setup page.
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
require_once '../lib/bankstatement.lib.php';

// Translations
$langs->loadLangs(array("admin", "bankstatement@bankstatement"));

// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

if ($action === 'ajax_set_const') {
	$name = GETPOST('name', 'alpha');

	if (!preg_match('/^BANKSTATEMENT_/', $name)) {
		echo 'Error: modifying consts other than BANKSTATEMENT_ not allowed.';
		exit;
	}

	$entity = GETPOST('entity', 'int');
	$value = GETPOST('value');

	if ($user->admin)
	{
		dolibarr_set_const($db, $name, $value, 'chaine', 0, '', $entity);
	}
	exit;
}

// Configuration data

$separatorChoices = array(
	'Comma'      => ',',
	'Semicolon'  => ';',
	'Tabulation' => "\t",
	'Colon'      => ':',
	'Pipe'       => '|',
	);

$defaultParameters = array(
	'css'       => 'minwidth500',
	'enabled'   => 1,
	'type'      => 'text'
);
$specificParameters=array(
	'BANKSTATEMENT_SEPARATOR'                           => array('type' => 'datalist', 'pattern' => '^.$', 'suggestions' => $separatorChoices, 'required' => 1,),
	'BANKSTATEMENT_MAPPING'                             => array('required' => 1,),
	'BANKSTATEMENT_DATE_FORMAT'                         => array('required' => 1,),
	'BANKSTATEMENT_HEADER'                              => array('type' => 'bool',),
	'BANKSTATEMENT_MAC_COMPATIBILITY'                   => array('type' => 'bool',),
	'BANKSTATEMENT_HISTORY_IMPORT'                      => array('type' => 'bool',),
	'BANKSTATEMENT_ALLOW_INVOICE_FROM_SEVERAL_THIRD'    => array('type' => 'bool',),
	'BANKSTATEMENT_ALLOW_DRAFT_INVOICE'                 => array('type' => 'bool',),
	'BANKSTATEMENT_UNCHECK_ALL_LINES'                   => array('type' => 'bool',),
	'BANKSTATEMENT_AUTO_CREATE_DISCOUNT'                => array('type' => 'bool',),
	'BANKSTATEMENT_MATCH_BANKLINES_BY_AMOUNT_AND_LABEL' => array('type' => 'bool',),
	'BANKSTATEMENT_ALLOW_FREELINES'                     => array('type' => 'bool',)
);
$arrayofparameters = array_map(
	function($specificParameters) use ($defaultParameters) {
		return $specificParameters + $defaultParameters; // specific parameters override default
	},
	$specificParameters
);

/*
 * Main View
 */
$page_name = "BankStatementSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_bankstatement@bankstatement');

// Configuration header
$head = bankstatementAdminPrepareHead();
dol_fiche_head($head, 'settings', '', -1, "bankstatement@bankstatement");

function get_conf_label($confName, $parameters, $form) {
	global $langs;
	$confHelp = $langs->trans($confName . '_Help');
	$confLabel = sprintf(
		'<label for="%s">%s</label>',
		$confName,
		$langs->trans($confName)
	);

	if (!empty($langs->tab_translate[$confName . '_Help'])) {
		// help translation found: display help picto
		return $form->textwithpicto($confLabel, $confHelp);
	} else {
		// help translation not found: only display label
		return $confLabel;
	}
}

function get_conf_input($confName, $parameters) {
	global $conf, $langs;
	$confValue = isset($conf->global->{$confName}) ? $conf->global->{$confName} : '';
	$inputAttrs = sprintf(
		'name="%s" id="%s" class="%s"',
		htmlspecialchars($confName, ENT_COMPAT),
		htmlspecialchars($confName, ENT_COMPAT),
		htmlspecialchars($parameters['css'], ENT_COMPAT)
	);
	if (!empty($parameters['required'])) $inputAttrs .= ' required';
	switch ($parameters['type']) {
		case 'bool':
			$input = ajax_constantonoff($confName);
			break;
		case 'datalist':
			// no break => will also run case 'text'
			$options = array();
			foreach ($parameters['suggestions'] as $label => $value) {
				$options[] = '<option value="' . $value . '">' . $langs->trans($label) . '</option>';
			}
			$datalist = sprintf(
				'<datalist id="%s">%s</datalist>',
				$confName . '_suggestions',
				join("\n", $options)
			);
			$inputAttrs .= ' list="' . $confName . '_suggestions' . '"';
		case 'text':
			if (isset($parameters['pattern'])) {
				$inputAttrs .= ' pattern="' . $parameters['pattern'] . '"';
			}
			$input = sprintf(
				'<input %s type="text" value="%s" /> <button class="but" id="btn_save_%s">%s</button>',
				$inputAttrs,
				htmlspecialchars($confValue, ENT_COMPAT),
				$confName,
				$langs->trans('Modify')
			) . '<script type="text/javascript">$(()=>ajaxSaveOnClick("'.htmlspecialchars($confName, ENT_COMPAT).'"));</script>';
			if (isset($datalist)) $input .= $datalist;
			break;
		default:
			$input = $confValue;
	}
	return '<form method="POST" id="form_save_' . $confName . '" action="' . $_SERVER['PHP_SELF'] . '">'
		   . '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '" />'
		   . '<input type="hidden" name="action" value="update" />'
		   . $input
		   . '</form>';
}
$form = new Form($db);
// Setup page goes here
?>
<p><?php echo $langs->trans("BankStatementSetupPage"); ?></p>
<table class="noborder" width="100%">
	<colgroup><col id="setupConfLabelColumn"/><col id="setupConfValueColumn" /></colgroup>
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
			get_conf_label($confName, $arrayofparameters[$confName], $form),
			get_conf_input($confName, $arrayofparameters[$confName])
		);
	}
	?>
	</tbody>
</table>
<?php

// Page end
dol_fiche_end();

llxFooter();
$db->close();

