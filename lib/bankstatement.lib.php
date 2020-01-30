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
 */

/**
 * \file    bankstatement/lib/bankstatement.lib.php
 * \ingroup bankstatement
 * \brief   Library files with common functions for BankStatement
 */

const DIRECTION_DEBIT = -1;
const DIRECTION_CREDIT = 1;
const STATUS_UNRECONCILED = 0;
const STATUS_RECONCILED   = 1;

/**
 * Prepare admin pages header
 *
 * Add one tab for each bank account in the current entity.
 *
 * @return array
 */
function bankstatementAdminPrepareHead()
{
	global $langs, $conf, $db;
	require_once DOL_DOCUMENT_ROOT.'/core/lib/accounting.lib.php';

	$langs->load("bankstatement@bankstatement");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/bankstatement/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'default';
	$h++;

//	$sql = "SELECT DISTINCT account.rowid, account.label FROM ".MAIN_DB_PREFIX."bank_account as account"
//	     . " WHERE account.entity IN (" . getEntity('bank_account') . ")"
//	     . " ORDER BY account.rowid";
//
//	$resql = $db->query($sql);
//
//	if (!$resql) {
//		setEventMessages("Error ".$db->lasterror(), array(), 'errors');
//		return array();
//	}
//	$nbAccounts = $db->num_rows($resql);
//
//	for ($i = 0; $i < $nbAccounts; $i++, $h++) {
//		$obj = $db->fetch_object($resql);
//		if (empty($obj)) break;
//		$head[$h][0] = dol_buildpath("/bankstatement/admin/setup.php?accountId=" . $obj->rowid, 1);
//		$head[$h][1] = $langs->trans("AccountSettings", $obj->label);
//		$head[$h][2] = 'account' . $obj->rowid;
//	}


	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@bankstatement:/bankstatement/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@bankstatement:/bankstatement/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'bankstatement');

	return $head;
}

/**
 * @param array $dataArray  Associative array of key/value pairs you want to be made available to your script
 * @param string $varName   Name for the variable (defaults to 'window.jsonDataArray'). Use a different name
 *                          if you call the function more than once.
 */
function setJavascriptVariables($dataArray, $varName = 'window.jsonDataArray')
{
	echo '<script type="application/javascript">' . "\n"
		. $varName . '=' . json_encode($dataArray) . ";\n"
		. "</script>\n";
}

/**
 * Tells whether the raw (signed) amount of a bank operation is a debit operation or a credit operation.
 * @param $rawAmount
 * @return int|null  NULL if amount == 0 (amounts of bank transactions should not be 0)
 */
function getAmountType($rawAmount) {
	if ($rawAmount > 0) return DIRECTION_CREDIT;
	if ($rawAmount < 0) return DIRECTION_DEBIT;
}

function getDefaultSetupParameters() {
	return array(
		'css'       => 'minwidth500',
		'enabled'   => 1,
		'inputtype'      => 'text'
	);
}

/**
 * @param $confName
 * @return string
 */
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

/**
 * @param $confName
 * @param $parameters
 * @param $form
 * @return string
 */
function get_conf_label($confName, $parameters, $form) {
	global $langs;
	$confHelp = $langs->trans($confName . '_Help');
	$confLabel = '<label for="' . $confName . '">' . $langs->trans($confName) . '</label>';
	if (!empty($langs->tab_translate[$confName . '_Help'])) {
		// help translation found: display help picto
		return $form->textwithpicto($confLabel, $confHelp);
	} else {
		// help translation not found: only display label
		return $confLabel;
	}
}

/**
 * @param string $code       Name of the configuration option.
 * @param array  $parameters Options describing the desired form field
 * @param array  $TAutoParameters Associative array that will be converted to hidden input tags
 * @return string  A <form> tag containing one field to set the desired configuration option
 */
function get_conf_input($code, $parameters, $TAutoParameters = array()) {
	global $conf, $langs;
	$confValue = get_conf_value($code);
	$inputAttrs = sprintf(
		'name="%s" id="%s" class="%s" data-saved-value="%s"',
		htmlspecialchars($code, ENT_COMPAT),
		htmlspecialchars($code, ENT_COMPAT),
		htmlspecialchars($parameters['css'], ENT_COMPAT),
		htmlspecialchars($confValue)
	);
	if (!empty($parameters['required'])) $inputAttrs .= ' required';
	switch ($parameters['inputtype']) {
		case 'bool':
			// TODO : replace with a one-click toggler
			$input = '<select id="' . $code . '" name="' . $code . '">'
					 . '<option value="0">' . $langs->trans('No') . '</option>'
					 . '<option value="1">' . $langs->trans('Yes') . '</option>'
					 .'</select>';
			$input .= '<button class="but" id="btn_save_' . $code . '">' . $langs->trans('Modify') . '</button>';
			//else print '<a href="'.$_SERVER['PHP_SELF'].'?action=del_'.$code.'&entity='.$entity.'">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
			if (!empty($parameters['required_by'])) {
				// enable page reload on value switch
				foreach ($parameters['required_by'] as $subConfName) {
					$input .= '<script>$(()=>setVisibilityDependency("' . $code . '", "' . $subConfName . '"));</script>';
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
						 $code,
						 join("\n", $options),
						 $code,
						 $langs->trans('Modify')
					 ) . '<script type="text/javascript">$(()=>ajaxSaveOnClick("'.htmlspecialchars($code, ENT_COMPAT).'"));</script>';
			break;
		case 'text':
			if (isset($parameters['suggestions'])) {
				$options = array();
				foreach ($parameters['suggestions'] as $label => $value) {
					$options[] = '<option value="' . $value . '">' . $langs->trans($label) . '</option>';
				}
				$datalist = sprintf(
					'<datalist id="%s">%s</datalist>',
					$code . '_suggestions',
					join("\n", $options)
				);
				$inputAttrs .= ' list="' . $code . '_suggestions' . '"';
			}
			if (isset($parameters['pattern'])) {
				$inputAttrs .= ' pattern="' . $parameters['pattern'] . '"';
			}
			$input = sprintf(
						 '<input %s type="text" value="%s" /> <button class="but" id="btn_save_%s">%s</button>',
						 $inputAttrs,
						 htmlspecialchars($confValue, ENT_COMPAT),
						 $code,
						 $langs->trans('Modify')
					 ) . '<script type="text/javascript">$(()=>ajaxSaveOnClick("'.htmlspecialchars($code, ENT_COMPAT).'"));</script>';
			if (isset($datalist)) $input .= $datalist;
			break;
		default:
			$input = $confValue;
	}
	$TAutoParameters['token'] = $TAutoParameters['token'] ? $TAutoParameters['token'] : $_SESSION['newtoken'];
	$TAutoParameters['action'] = $TAutoParameters['action'] ? $TAutoParameters['action'] : 'update';
	$THiddenInput = array();
	foreach ($TAutoParameters as $paramName => $value) {
		$THiddenInput[] = '<input type="hidden" name="'. htmlspecialchars($paramName) .'" value="'. htmlspecialchars($value) .'" />';
	}
	return '<form method="POST" id="form_save_' . $code . '" action="' . $_SERVER['PHP_SELF'] . '">'
		   . '<div class="justify-content-between">'
		   . join("\n", $THiddenInput)
		   . $input
		   . '</div>'
		   . '</form>';
}

