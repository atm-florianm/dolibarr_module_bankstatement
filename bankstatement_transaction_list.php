<?php
/* Copyright (C) 2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
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

/**
 *   	\file       bankstatement_card.php
 *		\ingroup    bankstatement
 *		\brief      Page to create/edit/view bankstatement
 */

//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU','1');				// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML','1');				// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX','1');       	  	// Do not load ajax.lib.php library
//if (! defined("MAIN_AUTHENTICATION_MODE")) define('MAIN_AUTHENTICATION_MODE','aloginmodule');		// Force authentication handler
//if (! defined("NOREDIRECTBYMAINTOLOGIN"))  define('NOREDIRECTBYMAINTOLOGIN',1);		// The main.inc.php does not make a redirect if not logged, instead show simple error message
//if (! defined("FORCECSP"))                 define('FORCECSP','none');					// Disable all Content Security Policies


// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
dol_include_once('/bankstatement/class/bankstatement.class.php');
dol_include_once('/bankstatement/lib/bankstatement.lib.php');
dol_include_once('/bankstatement/lib/bankstatement_bankstatement.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("bankstatement@bankstatement", "other"));

// Get parameters
//$ref         = GETPOST('ref', 'alpha');
$accountId   = GETPOST('accountId', 'int');
$action      = GETPOST('action', 'aZ09'); if (!$action) $action = 'view';
$confirm     = GETPOST('confirm', 'alpha');
$cancel      = GETPOST('cancel', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ'); if (empty($contextpage)) $contextpage = 'bankstatementtransactions'; // To manage different context of search
$backtopage  = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');
$toselect = GETPOST('toselect', 'array');
$massaction = GETPOST('massaction', 'aZ09');
$confirmmassaction = GETPOST('confirmmassaction', 'alpha');

// Initialize technical objects
$objectline = new BankStatementLine($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->bankstatement->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('bankstatementtransactions', 'globalcard')); // Note that conf->hooks_modules contains array

$search=array();
foreach($objectline->fields as $key => $val)
{
	if (GETPOST('search_'.$key, 'alpha')) $search[$key]=GETPOST('search_'.$key, 'alpha');
}

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$search_all = trim(GETPOST("search_all", 'alpha'));
$search = array();

foreach ($objectline->fields as $key => $val)
{
	if (GETPOST('search_'.$key, 'alpha')) $search[$key] = GETPOST('search_'.$key, 'alpha');
}

//$arrayofselected = array();

// Definition of fields for list
$arrayfields=array();
foreach($objectline->fields as $key => $val)
{
	// If $val['visible']==0, then we never show the field
	if (!$val['visible']) continue;
	$arrayfields['t.'.$key]=array(
		'label'=>$val['label'],
		'checked'=>(($val['visible']<0)?0:1),
		'enabled'=>($val['enabled'] && ($val['visible'] != 3)),
		'position'=>$val['position']
	);
}
// Extra fields
if (is_array($extrafields->attributes[$objectline->table_element]['label']) && count($extrafields->attributes[$objectline->table_element]['label']) > 0)
{
	foreach($extrafields->attributes[$objectline->table_element]['label'] as $key => $val)
	{
		if (!empty($extrafields->attributes[$objectline->table_element]['list'][$key])) {
			$arrayfields["ef.".$key] = array(
				'label'=>$extrafields->attributes[$objectline->table_element]['label'][$key],
				'checked'=>(($extrafields->attributes[$objectline->table_element]['list'][$key]<0)?0:1),
				'position'=>$extrafields->attributes[$objectline->table_element]['pos'][$key],
				'enabled'=>(abs($extrafields->attributes[$objectline->table_element]['list'][$key])!=3 && $extrafields->attributes[$objectline->table_element]['perms'][$key])
			);
		}
	}
}

if (empty($action) && empty($id) && empty($ref)) $action = 'view';

// Security check - Protection if external user
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (($object->statut == $object::STATUS_DRAFT) ? 1 : 0);
//$result = restrictedArea($user, 'bankstatement', $object->id, '', '', 'fk_soc', 'rowid', $isdraft);

$permissiontoread = $user->rights->bankstatement->bankstatement->read;
$permissiontoadd = $user->rights->bankstatement->bankstatement->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
//$permissiontodelete = $user->rights->bankstatement->bankstatement->delete || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_UNRECONCILED);
$permissionnote = $user->rights->bankstatement->bankstatement->write; // Used by the include of actions_setnotes.inc.php
$permissiondellink = $user->rights->bankstatement->bankstatement->write; // Used by the include of actions_dellink.inc.php
//$upload_dir = $conf->bankstatement->multidir_output[isset($objectline->entity) ? $objectline->entity : 1];


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	// TODO
}

/*
 * View
 *
 * Put here all code to build page
 */

$form = new Form($db);
$formfile = new FormFile($db);


$arrayofmassactions = array (
	'reconcile' => $langs->trans("Reconcile"),
);
$massactionbutton = $form->selectMassAction('reconcile', $arrayofmassactions);

llxHeader('', $langs->trans('BankStatementTransactions'), '');

// Example : Adding jquery code
print '<script type="text/javascript" language="javascript">
$(()=>{
	
});
</script>';

// Part to show record
if ($action === 'view')
{
	$head = bankstatementPrepareHead($objectline);
	dol_fiche_head($head, 'card', $langs->trans("BankStatementTransactions"), -1, $objectline->picto);

	// show confirmation pop-in for certain actions
	$actionsRequiringConfirmation = array('delete', 'deleteline');
	if (in_array($action, $actionsRequiringConfirmation, true)) {
		$formconfirm = '';

		// Confirmation to delete
		if ($action === 'delete')
		{
			$formconfirm = $form->formconfirm(
				$_SERVER["PHP_SELF"].'?id='.$objectline->id,
				$langs->trans('DeleteBankStatement'),
				$langs->trans('ConfirmDeleteObject'),
				'confirm_delete',
				'',
				0,
				1
			);
		}

		// Call Hook formConfirm
		$parameters = array('lineid' => $lineid);
		$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if (empty($reshook)) $formconfirm .= $hookmanager->resPrint;
		elseif ($reshook > 0) $formconfirm = $hookmanager->resPrint;

		// Print form confirm
		print $formconfirm;
	}


	// Object card
	// ------------------------------------------------------------

	print '<form action="bankstatement_reconcile.php">';
	print $massactionbutton;
	$linkback = '<a href="'.dol_buildpath('/bankstatement/bankstatement_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	$morehtmlref .= '</div>';


	dol_banner_tab($objectline, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '</div>';

	print '<div class="clearboth"></div>';

	$form->select_comptes($accountId ? $accountId : -1, 'accountId', '', '', 1);
	?>
	<script type="text/javascript">
		$(function () {
			$('#selectaccountId').change(function () {
				// TODO: filtrer la liste par compte
			})
		});
	</script>
	<?php

	dol_fiche_end();


	/*
	 * Lines
	 */

	// Show object lines
//		$objectline->fetchAll('DESC', 'date', 50, 1, array('s.fk_account' => $accountId));
	$TSQLSelect = array();
	foreach ($objectline->fields as $fieldName => $fieldParams) {
		$TSQLSelect[] = 'l.'.$fieldName;
	}
	// TODO : refaire en mode objet ? ça veut dire analyser quelles méthodes doivent être ajoutées / modifiées
	// TODO : rendre la liste filtrable
	// TODO : bug (?) du <select> (select2) des massactions ? Avec Firefox, la première fois que je sélectionne, la valeur
	//        associée au select est correcte mais elle n’est pas affichée dans le select

	$sqlSelect = 'SELECT ' . join(', ', $TSQLSelect) . ' FROM ' . MAIN_DB_PREFIX . 'bankstatement_bankstatementdet AS l';

	$sqlJoin = 'INNER JOIN ' . MAIN_DB_PREFIX . 'bankstatement_bankstatement AS s ON l.fk_bankstatement = s.rowid';

	$TSQLFilter = array();
	if (!empty($accountId)) $TSQLFilter[] = 's.fk_account = ' . $accountId;

	if (!empty($TSQLFilter)) $sqlWhere = 'WHERE ' . join(' AND ', $TSQLFilter);
	$sql = join(' ', array($sqlSelect, $sqlJoin, $sqlWhere));

	print '<div class="div-table-responsive-no-min">';
	print '<table id="tablelines" class="noborder noshadow" width="100%">';

	print '<colgroup id="objectfields">';
	foreach($objectline->fields as $fieldName => $fieldParams) {
		if (!$fieldParams['visible']) continue;
		print '<col class="col_' . $fieldName . '" />';
	}
	print '</colgroup>';
	print '<colgroup id="massactionselect">';
	print '<col class="col_massaction" />';
	print '</colgroup>';

	print '<thead>';
	print '<tr class="liste_titre">';
	/*foreach ($objectline->fields as $fieldName => $fieldParams) {
		if (!$fieldParams['visible']) continue;
		print '<th class="search_col_' . $fieldName . '">';
		// TODO : champs de recherche auto en fonction des paramètres de $objectline->fields
		print '</th>';
	}*/
	print '<th></th>';
	print '</tr>';
	print '<tr class="liste_titre">';
	foreach ($objectline->fields as $fieldName => $fieldParams) {
		if (!$fieldParams['visible']) continue;
		print '<th class="col_' . $fieldName . '">' . $langs->trans($fieldParams['label']) . '</th>';
	}
	// Action column
//		print '<th class="col_massaction liste_titre maxwidthsearch">';
//		$searchpicto = $form->showFilterButtons();
//		print $searchpicto;
	$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields
	$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');
	print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'col_massaction center maxwidthsearch ')."\n";
//		print '</th>';
	print '</tr>';
	print '</thead>';
	print '<tbody>';

	$resql = $db->query($sql);
	$rescount = $resql ? $db->num_rows($resql) : 0;
	if ($rescount) {
		for ($i = 0; $i < $rescount; $i++) {
			$obj = $db->fetch_object($resql);
			if (!$obj) break;
			print '<tr class="" data-rowid="'.$obj->rowid.'">';
			foreach ($objectline->fields as $fieldName => $fieldParams) {
				if (!$fieldParams['visible']) continue;
				print '<td class="col_' . $fieldName . '">' . $objectline->showOutputField($fieldParams, $fieldName, $obj->{$fieldName}) . '</td>';
			}
			// Action column
			print '<td class="colmassaction nowrap center">';
			$selected = 0;
			if (in_array($obj->rowid, $toselect)) $selected = 1;
			print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected ? ' checked="checked"' : '').'>';
			print '</td>';
			print '</tr>';
		}
	}
	print '</tbody>';

	print '</table>';
	print '</div>';

	print "</form>\n";

	// Buttons for actions

	print '<div class="tabsAction">'."\n";
	print '</div>'."\n";
	print '<div class="fichecenter"><div class="fichehalfleft">';
	print '<a name="builddoc"></a>'; // ancre

}

// End of page
llxFooter();
$db->close();
