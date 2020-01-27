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

	$sql = "SELECT DISTINCT account.rowid, account.label FROM ".MAIN_DB_PREFIX."bank_account as account"
	     . " WHERE account.entity IN (" . getEntity('bank_account') . ")"
	     . " ORDER BY account.rowid";

	$resql = $db->query($sql);

	if (!$resql) {
		setEventMessages("Error ".$db->lasterror(), array(), 'errors');
		return array();
	}
	$nbAccounts = $db->num_rows($resql);

	for ($i = 0; $i < $nbAccounts; $i++, $h++) {
		$obj = $db->fetch_object($resql);
		if (empty($obj)) break;
		$head[$h][0] = dol_buildpath("/bankstatement/admin/setup.php?accountId=" . $obj->rowid, 1);
		$head[$h][1] = $langs->trans("AccountSettings", $obj->label);
		$head[$h][2] = 'account' . $obj->rowid;
	}


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
function jsValuesAsJSON($dataArray, $varName = 'window.jsonDataArray')
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
