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
dol_include_once('/bankstatement/class/bankstatement.class.php');
dol_include_once('/bankstatement/lib/bankstatement.lib.php');
dol_include_once('/bankstatement/lib/bankstatement_bankstatement.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("bankstatement@bankstatement", "other"));

// Get parameters
//$ref         = GETPOST('ref', 'alpha');
$accountId   = GETPOST('accountId', 'int');
$action      = GETPOST('action', 'aZ09');
$confirm     = GETPOST('confirm', 'alpha');
$cancel      = GETPOST('cancel', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ'); if (empty($contextpage)) $contextpage = 'bankstatementtransactions'; // To manage different context of search
$backtopage  = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

// Initialize technical objects
$object = new BankStatement($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->bankstatement->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('bankstatementtransactions', 'globalcard')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$search_all = trim(GETPOST("search_all", 'alpha'));
$search = array();
foreach ($object->fields as $key => $val)
{
	if (GETPOST('search_'.$key, 'alpha')) $search[$key] = GETPOST('search_'.$key, 'alpha');
}

if (empty($action) && empty($id) && empty($ref)) $action = 'view';

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once.

// Security check - Protection if external user
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (($object->statut == $object::STATUS_DRAFT) ? 1 : 0);
//$result = restrictedArea($user, 'bankstatement', $object->id, '', '', 'fk_soc', 'rowid', $isdraft);

$permissiontoread = $user->rights->bankstatement->bankstatement->read;
$permissiontoadd = $user->rights->bankstatement->bankstatement->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissiontodelete = $user->rights->bankstatement->bankstatement->delete || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_UNRECONCILED);
$permissionnote = $user->rights->bankstatement->bankstatement->write; // Used by the include of actions_setnotes.inc.php
$permissiondellink = $user->rights->bankstatement->bankstatement->write; // Used by the include of actions_dellink.inc.php
$upload_dir = $conf->bankstatement->multidir_output[isset($object->entity) ? $object->entity : 1];


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	$error = 0;

	$backurlforlist = dol_buildpath('/bankstatement/bankstatement_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) $backtopage = $backurlforlist;
			else $backtopage = dol_buildpath('/bankstatement/bankstatement_card.php', 1).'?id='.($id > 0 ? $id : '__ID__');
		}
	}
	$triggermodname = 'BANKSTATEMENT_BANKSTATEMENT_MODIFY'; // Name of trigger action code to execute when we modify record

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Actions when linking object each other
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';

	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

	// Action to move up and down lines of object
	//include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';

	// Action to build doc
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';

	// Actions to send emails
	$triggersendname = 'BANKSTATEMENT_SENTBYMAIL';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_BANKSTATEMENT_TO';
	$trackid = 'bankstatement'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';
}




/*
 * View
 *
 * Put here all code to build page
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader('', $langs->trans('BankStatement'), '');

// Example : Adding jquery code
print '<script type="text/javascript" language="javascript">
$(()=>{
	
});
</script>';


// action 'create' = display the creation form
if ($action === 'create') {
	print load_fiche_titre($langs->trans("NewObject", $langs->transnoentitiesnoconv("BankStatement")));
	print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	if ($backtopageforcancel) print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';

	dol_fiche_head(array(), '');

	print '<table class="border centpercent tableforfieldcreate">'."\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

	?>
	<tbody>
	<tr>
		<td><label for="CSVFile"><?php echo $langs->trans('FileToImport'); ?></label></td>
		<td><input id="CSVFile" name="CSVFile" type="file" required /></td>
	</tr>
	</tbody>
	<?php
	print '</table>'."\n";

	dol_fiche_end();

	print '</div>';
	print '<div class="center">'
		  . '<input type="submit" class="button" name="save" value="'.$langs->trans("Save").'" />'
		  . '&nbsp;'
		  // Cancel for create does not post form if we don't know the backtopage
		  . '<input type="'.($backtopage ? "submit" : "button").'" formnovalidate class="button" name="cancel" value="'.dol_escape_htmltag($langs->trans("Cancel")).'"'.($backtopage ? '' : ' onclick="javascript:history.go(-1)"').'>'
		  . '</div>';

	print '</form>';

	dol_set_focus('input[name="label"]');
}
elseif ($action === 'add') {
	$filename = GETPOST('CSVFile', 'alpha');
	if (isset($_FILES['CSVFile'])) {
		$filePath = $_FILES['CSVFile']['tmp_name'];
		$object->label = GETPOST('label', 'alpha');
		$object->createFromCSVFile($filePath, GETPOST('fk_account', 'int'));
	}
}
elseif ($action === 'reconcile') {
	// TODO : écran de rapprochement
	print load_fiche_titre($langs->trans("BankStatement"));
	$sqlSelect = array('b.rowid', 'b.label', 'b.rappro', 'b.num_releve', 'b.fk_account', 'b.amount', 'b.datev', 'b.dateo');
	$sql = 'SELECT ' . join(', ', $sqlSelect) .' FROM ' . MAIN_DB_PREFIX . 'bank AS b'
		   . ' WHERE b.fk_account = ' . $object->fk_account . ';';
	$resql = $db->query($sql);
	echo '<table class="border centpercent">';
	echo '<thead>';
	printf(
		'<tr class="liste_titre"><th>%s</th> <th>%s</th>',
		$langs->trans('Label'),
		$langs->trans('Amount')
	);
	echo '</thead>';
	echo '<tbody>';

	if ($resql) {
		$n = $db->num_rows($resql);
		for ($i = 0; $i < $n; $i++) {
			$obj = $db->fetch_object($resql);
			printf(
				'<tr><td>%s</td> <td>%s</td></tr>',
				$obj->label,
				$obj->amount
			);
		}
	}
	echo '</tbody>';
	echo '</table>';
}

// Part to edit record
if (($id || $ref) && $action == 'edit')
{
	print load_fiche_titre($langs->trans("BankStatement"));

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($backtopage) print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	if ($backtopageforcancel) print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';

	dol_fiche_head();

	print '<table class="border centpercent tableforfieldedit">'."\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';

	print '</table>';

	dol_fiche_end();

	print '<div class="center">'
		  . '<input type="submit" class="button" name="save" value="'.$langs->trans("Save").'" />'
		  . '&nbsp;'
		  . '<input type="submit" class="button" name="cancel" formnovalidate value="'.$langs->trans("Cancel").'" />'
		  . '</div>';

	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create')))
{
	$res = $object->fetch_optionals();

	$head = bankstatementPrepareHead($object);
	dol_fiche_head($head, 'card', $langs->trans("BankStatement"), -1, $object->picto);

	// show confirmation pop-in for certain actions
	$actionsRequiringConfirmation = array('delete', 'deleteline');
	if (in_array($action, $actionsRequiringConfirmation, true)) {
		$formconfirm = '';

		// Confirmation to delete
		if ($action === 'delete')
		{
			$formconfirm = $form->formconfirm(
				$_SERVER["PHP_SELF"].'?id='.$object->id,
				$langs->trans('DeleteBankStatement'),
				$langs->trans('ConfirmDeleteObject'),
				'confirm_delete',
				'',
				0,
				1
			);
		}
		// Confirmation to delete line
		if ($action === 'deleteline')
		{
			$formconfirm = $form->formconfirm(
				$_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid,
				$langs->trans('DeleteLine'),
				$langs->trans('ConfirmDeleteLine'),
				'confirm_deleteline',
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
	$linkback = '<a href="'.dol_buildpath('/bankstatement/bankstatement_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	/*
	// Ref bis
	$morehtmlref.=$form->editfieldkey("RefBis", 'ref_client', $object->ref_client, $object, $user->rights->bankstatement->bankstatement->creer, 'string', '', 0, 1);
	$morehtmlref.=$form->editfieldval("RefBis", 'ref_client', $object->ref_client, $object, $user->rights->bankstatement->bankstatement->creer, 'string', '', null, null, '', 1);
	// Thirdparty
	$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . (is_object($object->thirdparty) ? $object->thirdparty->getNomUrl(1) : '');
	// Project
	if (! empty($conf->projet->enabled))
	{
		$langs->load("projects");
		$morehtmlref.='<br>'.$langs->trans('Project') . ' ';
		if ($permissiontoadd)
		{
			if ($action != 'classify')
				$morehtmlref.='<a class="editfielda" href="' . $_SERVER['PHP_SELF'] . '?action=classify&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a> : ';
			if ($action == 'classify') {
				//$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'projectid', 0, 0, 1, 1);
				$morehtmlref.='<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
				$morehtmlref.='<input type="hidden" name="action" value="classin">';
				$morehtmlref.='<input type="hidden" name="token" value="'.newToken().'">';
				$morehtmlref.=$formproject->select_projects($object->socid, $object->fk_project, 'projectid', 0, 0, 1, 0, 1, 0, 0, '', 1);
				$morehtmlref.='<input type="submit" class="button valignmiddle" value="'.$langs->trans("Modify").'">';
				$morehtmlref.='</form>';
			} else {
				$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'none', 0, 0, 0, 1);
			}
		} else {
			if (! empty($object->fk_project)) {
				$proj = new Project($db);
				$proj->fetch($object->fk_project);
				$morehtmlref.=$proj->getNomUrl();
			} else {
				$morehtmlref.='';
			}
		}
	}
	*/
	$morehtmlref .= '</div>';


	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);


	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent">'."\n";

	// Common attributes
	//$keyforbreak='fieldkeytoswitchonsecondcolumn';	// We change column just after this field
	//unset($object->fields['fk_project']);				// Hide field already shown in banner
	//unset($object->fields['fk_soc']);					// Hide field already shown in banner
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';

	// Other attributes. Fields from hook formObjectOptions and Extrafields.
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

	print '</table>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';

	dol_fiche_end();


	/*
	 * Lines
	 */

	if (!empty($object->table_element_line))
	{
		// Show object lines
		$result = $object->getLinesArray();

		print '	<form name="addproduct" id="addproduct" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.(($action != 'editline') ? '#addline' : '#line_'.GETPOST('lineid', 'int')).'" method="POST">
		<input type="hidden" name="token" value="' . $_SESSION ['newtoken'].'">
		<input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline').'">
		<input type="hidden" name="mode" value="">
		<input type="hidden" name="id" value="' . $object->id.'">
		';

		if (!empty($conf->use_javascript_ajax) && $object->status == 0) {
			include DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php';
		}

		print '<div class="div-table-responsive-no-min">';
		if (!empty($object->lines) || ($object->status == $object::STATUS_UNRECONCILED && $permissiontoadd && $action != 'selectlines' && $action != 'editline'))
		{
			print '<table id="tablelines" class="noborder noshadow" width="100%">';
		}

		if (!empty($object->lines))
		{
			$object->printObjectLines($action, $mysoc, null, GETPOST('lineid', 'int'), 1);
		}

		// Form to add new line
		if ($object->status == 0 && $permissiontoadd && $action != 'selectlines')
		{
			if ($action != 'editline')
			{
				// Add products/services form
				$object->formAddObjectLine(1, $mysoc, $soc);

				$parameters = array();
				$reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
			}
		}

		if (!empty($object->lines) || ($object->status == $object::STATUS_UNRECONCILED && $permissiontoadd && $action != 'selectlines' && $action != 'editline'))
		{
			print '</table>';
		}
		print '</div>';

		print "</form>\n";
	}


	// Buttons for actions

	if ($action != 'presend' && $action != 'editline') {
		print '<div class="tabsAction">'."\n";
		$parameters = array();
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

		if (empty($reshook))
		{
			if ($object->status === $object::STATUS_UNRECONCILED) {
				echo $object->getActionButton('reconcile', false);
				echo $object->getActionButton('delete', empty($permissiontowrite));
			}
		}
		print '</div>'."\n";
	}


	// Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	if ($action != 'presend')
	{
		print '<div class="fichecenter"><div class="fichehalfleft">';
		print '<a name="builddoc"></a>'; // ancre

		// Documents
		/*$objref = dol_sanitizeFileName($object->ref);
		$relativepath = $objref . '/' . $objref . '.pdf';
		$filedir = $conf->bankstatement->dir_output . '/' . $objref;
		$urlsource = $_SERVER["PHP_SELF"] . "?id=" . $object->id;
		$genallowed = $user->rights->bankstatement->bankstatement->read;	// If you can read, you can build the PDF to read content
		$delallowed = $user->rights->bankstatement->bankstatement->create;	// If you can create/edit, you can remove a file on card
		print $formfile->showdocuments('bankstatement', $objref, $filedir, $urlsource, $genallowed, $delallowed, $object->modelpdf, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang);
		*/

		// Show links to link elements
		$linktoelem = $form->showLinkToObjectBlock($object, null, array('bankstatement'));
		$somethingshown = $form->showLinkedObjectBlock($object, $linktoelem);


		print '</div><div class="fichehalfright"><div class="ficheaddleft">';

		$MAXEVENT = 10;

		$morehtmlright = '<a href="'.dol_buildpath('/bankstatement/bankstatement_agenda.php', 1).'?id='.$object->id.'">';
		$morehtmlright .= $langs->trans("SeeAll");
		$morehtmlright .= '</a>';

		// List of actions on element
		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
		$formactions = new FormActions($db);
		$somethingshown = $formactions->showactions($object, $object->element, (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlright);

		print '</div></div></div>';
	}

	//Select mail models is same action as presend
	/*
	if (GETPOST('modelselected')) $action = 'presend';

	// Presend form
	$modelmail='bankstatement';
	$defaulttopic='InformationMessage';
	$diroutput = $conf->bankstatement->dir_output;
	$trackid = 'bankstatement'.$object->id;

	include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
	*/
}


// share account select form with javascript
//jsValuesAsJSON(array());
//echo '<script type="application/javascript" src="js/bankstatement_card.js.php"></script>'."\n";

// End of page
llxFooter();
$db->close();
