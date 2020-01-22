<?php
/* Copyright (C) 2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2020 ATM Consulting       <support@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// Load Dolibarr environment
$mainIncludePath = '../../main.inc.php';
$MAX_BACKTRACK=5; // max depth for finding 'main.inc.php' in parent directories
for ($resInclude = 0, $depth = 0; !$resInclude && $depth < $MAX_BACKTRACK; $depth++) {
	$resInclude = @include $mainIncludePath;
	$mainIncludePath = '../' . $mainIncludePath;
}
if (!$resInclude) die ('Unable to include main.inc.php');

dol_include_once('/bankstatement/class/bankstatement.class.php');
dol_include_once('/bankstatement/lib/bankstatement.lib.php');

dol_include_once('/compta/bank/class/account.class.php');
dol_include_once('/compta/paiement/cheque/class/remisecheque.class.php');
dol_include_once('/bankstatement/class/transactioncompare.class.php');
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/adherents/class/adherent.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/sociales/class/chargesociales.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

global $langs, $user, $conf, $db;

$TLineId = array_map('intval', GETPOST('toselect', 'array'));
$accountId = GETPOST('accountId', 'int');
$actionApplyConciliation = !empty(GETPOST('applyConciliation'));

$sqlCheckIdsHaveSameAccount='SELECT COUNT(DISTINCT fk_account) FROM ' . MAIN_DB_PREFIX . 'bankstatement_bankstatementdet line'
	. ' INNER JOIN ' . MAIN_DB_PREFIX . 'bankstatement_bankstatement statement ON line.fk_bankstatement = statement.rowid'
	. ' WHERE line.rowid IN (' . join(',', $TLineId) . ')';
// TODO: run query & check that result === 1, not more.
//$resql = $db->query($sqlCheckIdsHaveSameAccount);
//var_dump($db->fetch_object($resql));

$transactionCompare = new TransactionCompare($db);
$form = new Form($db);
$transactionCompare->fetchAccount($accountId);

if ($actionApplyConciliation) {
	$tpl = 'tpl/bankstatement.end.tpl.php';
	$transactionCompare->setStartAndEndDate(GETPOST('datestart'), GETPOST('dateend'));
	$transactionCompare->load_imported_transactions($TLineId);
	$transactionCompare->applyConciliation(GETPOST('TLine'));
} else {
	$tpl = 'tpl/bankstatement.check.tpl.php';
	$transactionCompare->load_transactions($TLineId);
	$transactionCompare->compare_transactions();
	$TTransactions = $transactionCompare->TImportedLines;
}

llxHeader('', $langs->trans('TitleBankCompare'));
print_fiche_titre($langs->trans("TitleBankCompare"));

include 'tpl/bankstatement.common.tpl.php';
include $tpl;

