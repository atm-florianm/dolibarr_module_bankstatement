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
function bankstatementAdminPrepareHead() {
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
function setJSONDataArray($dataArray, $varName = 'window.jsonDataArray') {
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

/**
 * Return the URL of the CSV format configuration page for the account identified by $accountId
 * @param $accountId
 * @return string
 */
function getAccountCSVConfigURL($accountId) {
	return dol_buildpath('/bankstatement/admin/account-CSV-setup.php?accountid=', 1) . intval($accountId);
}

/**
 * @return array  Const array with the default parameters for the input fields of config forms.
 *                By default, the input fields are of type 'text', have the CSS class 'minwidth500'
 *                and they are enabled.
 */
function getDefaultConfigFieldParams() {
	return array(
		'inputtype'      => 'text',
		'css'            => 'minwidth500',
		'enabled'        => 1,
	);
}

/**
 * Returns a normalized array of parameters for config form fields.
 *
 * @param array $TConfigFieldParams  complex associative array:
 *                                      keys = strings (name of form field)
 *                                      values = assoc array of parameters for that field
 * @return array
 */
function normalizeConfigFieldParams($TConfigFieldParams) {
	return array_map(
		function($configFieldParams) {
			// $configFieldParams override default
			return array_merge(getDefaultConfigFieldParams(), $configFieldParams);
			/*return $configFieldParams + getDefaultConfigFieldParams();*/
		},
		$TConfigFieldParams
	);
}

/**
 * @param string $confName
 * @param array $parameters
 * @param Form  $form
 * @return string
 */
function getConfLabel($confName, $parameters, $form) {
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
 * @param BankStatementFormat $CSVFormat
 * @param string $code       Name of the configuration option.
 * @param array  $parameters Options describing the desired form field
 * @param array  $TAutoParameters Associative array that will be converted to hidden input tags
 * @return string  A <form> tag containing one field to set the desired configuration option
 */
function getConfInput($CSVFormat, $code, $parameters, $TAutoParameters = array()) {
	global $conf, $langs;
	$confValue = $CSVFormat->{$CSVFormat->fieldByConfName[$code]};
	$inputAttrs = sprintf(
		'name="%s" id="%s" class="%s" data-saved-value="%s"',
		htmlspecialchars($code, ENT_COMPAT),
		htmlspecialchars($code, ENT_COMPAT),
		htmlspecialchars($parameters['css'] . ' ajaxSaveOnClick', ENT_COMPAT),
		htmlspecialchars($confValue)
	);
	if (!empty($parameters['required'])) $inputAttrs .= ' required';
	switch ($parameters['inputtype']) {
		case 'bool':
			// TODO : replace with a one-click toggler
			$input = '<select ' . $inputAttrs . ' id="' . $code . '" name="' . $code . '">'
					 . '<option value="0" ' . ((!$confValue) ? 'selected' : '') . '>' . $langs->trans('No') . '</option>'
					 . '<option value="1" ' . (( $confValue) ? 'selected' : '') . '>' . $langs->trans('Yes') . '</option>'
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
					 );
			break;
		case 'text':
			if (isset($parameters['suggestions'])) {
				$options = array();
				foreach ($parameters['suggestions'] as $label => $value) {
					$options[] = '<option value="' . dol_escape_htmltag($value) . '">' . $langs->trans($label) . '</option>';
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
						 htmlentities($confValue, ENT_COMPAT),
						 $code,
						 $langs->trans('Modify')
					 );
			if (isset($datalist)) $input .= $datalist;
			break;
		default:
			$input = $confValue;
	}
	$TAutoParameters['token'] = $TAutoParameters['token'] ? $TAutoParameters['token'] : $_SESSION['newtoken'];
	$TAutoParameters['action'] = $TAutoParameters['action'] ? $TAutoParameters['action'] : 'update';
	$THiddenInput = array();
	foreach ($TAutoParameters as $paramName => $value) {
		$THiddenInput[] = '<input type="hidden" name="'. htmlspecialchars($paramName) .'" value="'. htmlentities($value) .'" />';
	}
	return '<form method="POST" id="form_save_' . $code . '" action="' . $_SERVER['PHP_SELF'] . '">'
		   . '<div class="justify-content-between">'
		   . join("\n", $THiddenInput)
		   . $input
		   . '</div>'
		   . '</form>';
}

/**
 * @param DoliDB $db
 * @param BankStatementFormat $CSVFormat
 * @param array $TConstParameter
 * @param string $title
 */
function printCSVFormatEditor($db, $CSVFormat, $TConstParameter, $title) {
	global $langs, $conf;
	$form = new Form($db);
	?>
	<table class="noborder setup" width="100%">
		<colgroup><col id="setupConfLabelColumn"/><col id="setupConfValueColumn" /></colgroup>
		<thead>
		<tr>
			<td colspan="2" class="nobordernopadding valignmiddle col-title">
				<div class="titre inline-block"><?php echo $title; ?></div></td>
		</tr>
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
//			$CSVFormat->db = '';var_dump($CSVFormat);
			if (!empty($confParams['depends']) && empty($CSVFormat->{$CSVFormat->fieldByConfName[$confParams['depends']]})) {
				// do not show configuration input if it depends on a disabled option
				$tableRowClass .= ' hide_conf';
			}
			$confLabel = getConfLabel(
				$confName,
				$TConstParameter[$confName],
				$form
			);
			$confInput = getConfInput(
				$CSVFormat,
				$confName,
				$TConstParameter[$confName],
				array('accountid' => $CSVFormat->fk_account)
			);
			echo '<tr class="' . $tableRowClass . '">'
				 . '<td class="configLabel">' . $confLabel . '</td>'
				 . '<td class="configInput">' . $confInput . '</td>'
				 . '</tr>';
		}
		?>
		<tr class="jsRequired">
			<td>
							<noscript><style> .jsRequired { display: none } </style></noscript>
<!--				<style> .jsRequired { display: none } </style>-->
			</td>
			<td>
				<button onclick="saveAll('BANKSTATEMENT_')" class="button jsRequired"><?php echo $langs->trans('SaveAll');?></button>
			</td>
		</tr>
		</tbody>
	</table>
	<?php
}
