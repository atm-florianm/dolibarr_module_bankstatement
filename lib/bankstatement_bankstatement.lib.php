<?php
/* Copyright (C) ---Put here your own copyright and developer email---
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/bankstatement_bankstatement.lib.php
 * \ingroup bankstatement
 * \brief   Library files with common functions for BankStatement
 */

/**
 * Prepare array of tabs for BankStatement card
 *
 * @param	BankStatement	$object	BankStatement
 * @return 	array Array of arrays (each array represents a tab; array indices matter)
 */
function bankstatementPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("bankstatement@bankstatement");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/bankstatement/bankstatement_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Card");
	$head[$h][2] = 'card';
	$h++;

	if (isset($object->fields['note_public']) || isset($object->fields['note_private']))
	{
		$nbNote = 0;
		if (!empty($object->note_private)) $nbNote++;
		if (!empty($object->note_public)) $nbNote++;
		$head[$h][0] = dol_buildpath('/bankstatement/bankstatement_note.php', 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans('Notes');
		if ($nbNote > 0) $head[$h][1].= '<span class="badge marginleftonlyshort">'.$nbNote.'</span>';
		$head[$h][2] = 'note';
		$h++;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
	$upload_dir = $conf->bankstatement->dir_output . "/bankstatement/" . dol_sanitizeFileName($object->ref);
	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks=Link::count($db, $object->element, $object->id);

//	$head[$h][0] = dol_buildpath("/bankstatement/bankstatement_document.php", 1).'?id='.$object->id;
//	$head[$h][1] = $langs->trans('Documents');
//	if (($nbFiles+$nbLinks) > 0) $head[$h][1].= '<span class="badge marginleftonlyshort">'.($nbFiles+$nbLinks).'</span>';
//	$head[$h][2] = 'document';
//	$h++;

	if ($object->status !== $object::STATUS_RECONCILED) {
		$reconcileUrlQueryParams = array(
			'id' => $object->id,
			'action' => 'reconcileTransactions'
		);
		$reconcileUrl = dol_buildpath("/bankstatement/bankstatement_card.php", 1) . '?' . http_build_query($reconcileUrlQueryParams);
		$head[$h] = array(
			$reconcileUrl,
			$langs->trans("Reconcile"),
			'reconcile' // → <a id="reconcile" […]></a>
		);
		$h++;
	}

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@bankstatement:/bankstatement/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@bankstatement:/bankstatement/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'bankstatement@bankstatement');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'bankstatement@bankstatement', 'remove');

	return $head;
}
