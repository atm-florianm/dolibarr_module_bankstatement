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
require_once '../class/bankstatementformat.class.php';

// Translations
$langs->loadLangs(array("admin", "bankstatement@bankstatement"));

// Access control
if (! $user->admin) accessforbidden();

// Configuration data
$separatorChoices = array(
	'Comma'      => ',',
	'Semicolon'  => ';',
	'Tabulation' => "\\t",
	'Colon'      => ':',
	'Pipe'       => '|',
);

$lineSeparatorChoices = array(
	'LineSeparatorDefault' => '',
	'LineSeparatorWindows' => "\\r\\n",
	'LineSeparatorUnix'    => "\\n",
	'LineSeparatorMac'     => "\\r"
);

$TAdminFieldParameters=array(
	'BANKSTATEMENT_ALLOW_INVOICE_FROM_SEVERAL_THIRD'    => array('inputtype' => 'bool',),
	'BANKSTATEMENT_ALLOW_DRAFT_INVOICE'                 => array('inputtype' => 'bool',),
	'BANKSTATEMENT_UNCHECK_ALL_LINES'                   => array('inputtype' => 'bool',),
	'BANKSTATEMENT_AUTO_CREATE_DISCOUNT'                => array('inputtype' => 'bool',),
	'BANKSTATEMENT_MATCH_BANKLINES_BY_AMOUNT_AND_LABEL' => array('inputtype' => 'bool',),
	'BANKSTATEMENT_ALLOW_FREELINES'                     => array('inputtype' => 'bool',)
);

$TConfigFieldParameters=array(
	'BANKSTATEMENT_COLUMN_MAPPING'                      => array('required' => 1, 'pattern' => '.*(?=.*\\bdate\\b)(?=.*\\blabel\\b)((?=.*\\bcredit\\b)(?=.*\\bdebit\\b)|(?=.*\\bamount\\b)).*'),
	'BANKSTATEMENT_DELIMITER'                           => array('required' => 1, 'pattern' => '^(.|\\\\t)$', 'suggestions' => $separatorChoices,),
	'BANKSTATEMENT_DATE_FORMAT'                         => array('required' => 1,),
	'BANKSTATEMENT_USE_DIRECTION'                       => array('inputtype' => 'bool', 'required_by' => array('BANKSTATEMENT_DIRECTION_CREDIT', 'BANKSTATEMENT_DIRECTION_DEBIT'),),
	'BANKSTATEMENT_DIRECTION_CREDIT'                    => array('depends' => 'BANKSTATEMENT_USE_DIRECTION',),
	'BANKSTATEMENT_DIRECTION_DEBIT'                     => array('depends' => 'BANKSTATEMENT_USE_DIRECTION',),
	'BANKSTATEMENT_SKIP_FIRST_LINE'                              => array('inputtype' => 'bool',),
	//	'BANKSTATEMENT_LINE_ENDING'                      => array('inputtype' => 'select', 'options' => $lineSeparatorChoices,),
	//	'BANKSTATEMENT_ALLOW_INVOICE_FROM_SEVERAL_THIRD'    => array('inputtype' => 'bool',),
	//	'BANKSTATEMENT_ALLOW_DRAFT_INVOICE'                 => array('inputtype' => 'bool',),
	//	'BANKSTATEMENT_UNCHECK_ALL_LINES'                   => array('inputtype' => 'bool',),
	//	'BANKSTATEMENT_AUTO_CREATE_DISCOUNT'                => array('inputtype' => 'bool',),
	//	'BANKSTATEMENT_MATCH_BANKLINES_BY_AMOUNT_AND_LABEL' => array('inputtype' => 'bool',),
	//	'BANKSTATEMENT_ALLOW_FREELINES'                     => array('inputtype' => 'bool',)
);

$TConstParameter = normalizeConfigFieldParams($TConfigFieldParameters);

// Get fr
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$activeTabName = 'default';

// Load the default CSVFormat object
$CSVFormat = new BankStatementFormat($db);
$resLoad = $CSVFormat->load(0);
//$CSVFormat->db = ''; var_dump($CSVFormat);
if ($resLoad === -1) {
	// TODO: handle error
} else {
	// successfully loaded
}

// Check if there are query parameters to save
$nbValuesToSave = 0;
foreach ($TConstParameter as $confName => $confParam) {
	$check = 'alpha';
	if ($confParam['inputtype'] === 'bool') {
		$check = 'int';
	}
	if (!GETPOSTISSET($confName)) {
		continue;
	}
	$nbValuesToSave++;
	$value = GETPOST($confName, $check);
	if (array_key_exists($confName, $CSVFormat->fieldByConfName)) {
		$fieldName = $CSVFormat->fieldByConfName[$confName];
		$CSVFormat->setFieldValue($fieldName, $value);
	} else {
		// TODO handle error (there might be a mismatch between form field names and BankStatementFormat conf names)
	}
}
if ($nbValuesToSave) {
	$CSVFormat->save(0);
}

setJSONDataArray(array('accountId' => $CSVFormat->fk_account));
/*
 * Main View
 */
$page_name = "BankStatementSetup";
llxHeader('', $langs->trans($page_name), '', '', 0, 0, array('/bankstatement/js/bankstatement_setup.js.php'), array('/bankstatement/css/bankstatement.css.php'));

// Subheader
$linkback = '<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_bankstatement@bankstatement');

// Configuration header
$head = bankstatementAdminPrepareHead();

dol_fiche_head($head, $activeTabName, '', -1, "bankstatement@bankstatement");

print "<p>" . $langs->trans("BankStatementSetupPage") . "</p>";

printCSVFormatEditor($db, $CSVFormat, $TConstParameter, $title=$langs->trans('DefaultCSVFormatConf'));

// Page end
dol_fiche_end();

llxFooter();
$db->close();

