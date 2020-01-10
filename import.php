<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
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

// Load Dolibarr environment
$mainIncludePath = '../../main.inc.php';
$MAX_BACKTRACK=5; // max depth for finding 'main.inc.php' in parent directories
for ($resInclude = 0, $depth = 0; !$resInclude && $depth < $MAX_BACKTRACK; $depth++) {
	$resInclude = @include $mainIncludePath;
	$mainIncludePath = '../' . $mainIncludePath;
}
if (!$resInclude) die ('Unable to include main.inc.php');

require_once 'class/bankstatement.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

global $db, $langs;

$langs->load('bills');

ini_set("auto_detect_line_endings", true);

$mesg = "";
$form = new Form($db);
$tpl = 'tpl/bankimport.new.tpl.php';

$import = new BankStatement($db);

if(GETPOST('compare')) {
	
	$datestart = dol_mktime(0, 0, 0, GETPOST('dsmonth'), GETPOST('dsday'), GETPOST('dsyear'));
	$dateend = dol_mktime(0, 0, 0, GETPOST('demonth'), GETPOST('deday'), GETPOST('deyear'));
	$numreleve = GETPOST('numreleve');
	$hasHeader = GETPOST('hasheader');
	
	if($import->analyse(GETPOST('accountid','int'), 'bankimportfile', $datestart, $dateend, $numreleve, $hasHeader)) {
			
		$import->load_transactions(GETPOST('bankimportseparator'), GETPOST('bankimportdateformat'), GETPOST('bankimportmapping'));
		$import->compare_transactions();
		
		$TTransactions = $import->TFile;
		
		global $bc;
		
		$langs->load('bankimport@bankimport');
		$var = true;
		$tpl = 'tpl/bankimport.check.tpl.php';
	}
} else if(GETPOST('import')) {
	
	if(
		$import->analyse(
			GETPOST('accountid','int'),
			GETPOST('filename','alpha'),
			GETPOST('datestart','int'),
			GETPOST('dateend','int'),
			GETPOST('numreleve'),
			GETPOST('hasheader')
		)
	) {
		$import->load_transactions(GETPOST('bankimportseparator'), GETPOST('bankimportdateformat'), GETPOST('bankimportmapping'));
		
		$import->import_data(GETPOST('TLine'));
		$tpl = 'tpl/bankimport.end.tpl.php';
	}
}

llxHeader('', $langs->trans('TitleBankImport'));

print_fiche_titre($langs->trans("TitleBankImport"));

include($tpl);

llxFooter();
$db->close();