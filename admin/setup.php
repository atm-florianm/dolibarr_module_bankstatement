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

// Configuration data
$separatorChoices = array(
	'Comma'      => ',',
	'Semicolon'  => ';',
	'Tabulation' => "\t",
	'Colon'      => ':',
	'Pipe'       => '|',
);
$lineSeparatorChoices = array(
	'LineSeparatorDefault' => '',
	'LineSeparatorWindows' => "\\r\\n",
	'LineSeparatorUnix'    => "\\n",
	'LineSeparatorMac'     => "\\r"
);

$defaultParameters = array(
	'css'       => 'minwidth500',
	'enabled'   => 1,
	'inputtype'      => 'text'
);

$specificParameters=array(
	'BANKSTATEMENT_SEPARATOR'                           => array('required' => 1, 'pattern' => '^.$', 'suggestions' => $separatorChoices,),
	'BANKSTATEMENT_MAPPING'                             => array('required' => 1, 'pattern' => '.*(?=.*\\bdate\\b)(?=.*\\blabel\\b)((?=.*\\bcredit\\b)(?=.*\\bdebit\\b)|(?=.*\\bamount\\b)).*'),
	'BANKSTATEMENT_DATE_FORMAT'                         => array('required' => 1,),
	'BANKSTATEMENT_USE_DIRECTION'                       => array('inputtype' => 'bool', 'required_by' => array('BANKSTATEMENT_DIRECTION_CREDIT', 'BANKSTATEMENT_DIRECTION_DEBIT'),),
	'BANKSTATEMENT_DIRECTION_CREDIT'                    => array('depends' => 'BANKSTATEMENT_USE_DIRECTION',),
	'BANKSTATEMENT_DIRECTION_DEBIT'                     => array('depends' => 'BANKSTATEMENT_USE_DIRECTION',),
	'BANKSTATEMENT_HEADER'                              => array('inputtype' => 'bool',),
	//	'BANKSTATEMENT_LINE_SEPARATOR'                      => array('inputtype' => 'select', 'options' => $lineSeparatorChoices,),
	//	'BANKSTATEMENT_HISTORY_IMPORT'                      => array('inputtype' => 'bool',),
	'BANKSTATEMENT_ALLOW_INVOICE_FROM_SEVERAL_THIRD'    => array('inputtype' => 'bool',),
	'BANKSTATEMENT_ALLOW_DRAFT_INVOICE'                 => array('inputtype' => 'bool',),
	'BANKSTATEMENT_UNCHECK_ALL_LINES'                   => array('inputtype' => 'bool',),
	'BANKSTATEMENT_AUTO_CREATE_DISCOUNT'                => array('inputtype' => 'bool',),
	'BANKSTATEMENT_MATCH_BANKLINES_BY_AMOUNT_AND_LABEL' => array('inputtype' => 'bool',),
	'BANKSTATEMENT_ALLOW_FREELINES'                     => array('inputtype' => 'bool',)
);
$TConstParameter = array_map(
	function($specificParameters) use ($defaultParameters) {
		return $specificParameters + $defaultParameters; // specific parameters override default
	},
	$specificParameters
);

// Translations
$langs->loadLangs(array("admin", "bankstatement@bankstatement"));

// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// a different setup can be saved for each bank account.
$accountId = GETPOST('accountId', 'int');

if (!empty($accountId)) {
	$activeTabName = 'account' . intval($accountId);
	require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
	$account = new Account($db);
	if ($account->fetch($accountId) <= 0) {
		setEventMessages($langs->trans('AccountNotFound', $accountId), array(), 'errors');
		exit; // TODO: redirect? in any case, we should not use setEventMessages because this could be an ajax call.
	}
	// load account-specific conf (currently saved as a JSON bank_account extrafield)
	$account->fetch_optionals();
	$rawAccountConf = $account->array_options['options_bank_statement_import_format'];
	if (empty($rawAccountConf)) {
		$accountConf = array();
	} else {
		$accountConf = json_decode($rawAccountConf, true);
	}
	foreach ($TConstParameter as $key => $constParameter) {
		if (isset($conf->global->{$key}) && !isset($accountConf[$key])) {
			$accountConf[$key] = $conf->global->{$key};
		} elseif ($constParameter['inputtype'] === 'bool') {
			$accountConf[$key] = '';
		}
	}
} else {
	$activeTabName = 'default';
}

if ($action === 'ajax_set_const') {
	$name = GETPOST('name', 'alpha');


	if (!$user->admin) {
		echo '{"response": "failure", "reason": "NotAdmin"}';
		exit;
	} elseif (!preg_match('/^BANKSTATEMENT_/', $name)) {
		echo '{"response": "failure", "reason": "ConstKeyMustBeBankstatement"}';
		exit;
	} else {
		$value = GETPOST('value');
		if (!empty($accountId)) {
			$accountConf[$name] = $value;
			$rawAccountConf = json_encode($accountConf);
			if (strlen($rawAccountConf) > 1024) {
				// TODO: handle error (extrafield size is 1024)
			}
			$account->array_options['options_bank_statement_import_format'] = json_encode($accountConf);
			$account->insertExtraFields();
		} else {
			dolibarr_set_const($db, $name, $value, 'chaine', 0, '', $conf->entity);
		}
		echo '{"response": "success"}';
		exit;
	}
}

/*
 * Main View
 */
$page_name = "BankStatementSetup";
llxHeader('', $langs->trans($page_name));
jsValuesAsJSON(array('accountId' => $accountId), 'window.jsonDataArray');

// Subheader
$linkback = '<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_bankstatement@bankstatement');

// Configuration header
$head = bankstatementAdminPrepareHead();

dol_fiche_head($head, $activeTabName, '', -1, "bankstatement@bankstatement");

function get_conf_value($confName) {
	global $conf, $langs, $accountId, $account, $accountConf;
	$globalConfValue = isset($conf->global->{$confName}) ? $conf->global->{$confName} : '';
	if (!empty($accountId)) {
		// account-specific conf
		$confValue = isset($accountConf[$confName]) ? $accountConf[$confName] : $globalConfValue;
	} else {
		// global / default conf
		$confValue = $globalConfValue;
	}
	return $confValue;
}

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
	$confValue = get_conf_value($confName);
	$inputAttrs = sprintf(
		'name="%s" id="%s" class="%s" data-saved-value="%s"',
		htmlspecialchars($confName, ENT_COMPAT),
		htmlspecialchars($confName, ENT_COMPAT),
		htmlspecialchars($parameters['css'], ENT_COMPAT),
		htmlspecialchars($confValue)
	);
	if (!empty($parameters['required'])) $inputAttrs .= ' required';
	switch ($parameters['inputtype']) {
		case 'bool':
			$input = ajax_constantonoff($confName);
			if (!empty($parameters['required_by'])) {
				// enable page reload on value switch
				foreach ($parameters['required_by'] as $subConfName) {
					$input .= '<script>$(()=>reloadOnClick("' . $confName . '", "' . $subConfName . '"));</script>';
				}
			}
			break;
		case 'select':
			$options = array();
			foreach ($parameters['options'] as $label => $value) {
				$options[] = '<option value="' . $value . '">' . $langs->trans($label) . '</option>';
			}
			$input = sprintf(
				'<select %s id="%s">%s</select> <button class="but" id="btn_save_%s">%s</button>',
				$inputAttrs,
				$confName,
				join("\n", $options),
				$confName,
				$langs->trans('Modify')
			) . '<script type="text/javascript">$(()=>ajaxSaveOnClick("'.htmlspecialchars($confName, ENT_COMPAT).'"));</script>';
			break;
		case 'text':
			if (isset($parameters['suggestions'])) {
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
			}
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
	foreach ($TConstParameter as $confName => $confParams) {
		$tableRowClass = 'oddeven';
		if (!empty($confParams['depends']) && empty($conf->global->{$confParams['depends']})) {
			// do not show configuration input if it depends on a disabled option
			$tableRowClass .= ' hide_conf';
		}
		printf(
			'<tr class="%s">'
			. '<td>%s</td>'
			. '<td>%s</td>'
			. '</tr>',
			$tableRowClass,
			get_conf_label($confName, $TConstParameter[$confName], $form),
			get_conf_input($confName, $TConstParameter[$confName])
		);
	}
	?>
	<tr><td></td><td><button onclick="saveAll('BANKSTATEMENT_')" class="button"><?php echo $langs->trans('SaveAll');?></button></td></tr>
	</tbody>
</table>
<?php

// Page end
dol_fiche_end();

llxFooter();
$db->close();

