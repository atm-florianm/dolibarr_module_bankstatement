<table class="border" width="100%">
	<tr>
		<td width="200"><?php echo $langs->trans("BankAccount") ?></td>
		<td><?php echo $bankImport->account->getNomUrl(1) ?></td>
		<td width="200"><?php echo $langs->trans("DateStart") ?></td>
		<td><?php echo dol_print_date($bankImport->dateStart, 'day') ?></td>
		<td><?php echo $langs->trans("AccountStatement") ?></td>
		<td><?php echo $bankImport->numReleve ?></td>
	</tr>
	<tr>
		<td width="200"><?php echo $langs->trans("BankImportFile") ?></td>
		<td><?php echo basename($bankImport->file) ?></td>
		<td><?php echo $langs->trans("DateEnd") ?></td>
		<td><?php echo dol_print_date($bankImport->dateEnd, 'day') ?></td>
		<td><?php echo $langs->trans("FileHasHeader") ?></td>
		<td><?php echo $bankImport->hasHeader == 1 ? $langs->trans('Yes') : $langs->trans('No') ?></td>
	</tr>
</table>
<br />
<div class="center">
<a href="<?php echo dol_buildpath('/bankimport/releve.php', 2); ?>?account=<?php echo $bankImport->account->id ?>&amp;num=<?php echo $bankImport->numReleve ?>">
	<?php echo $langs->trans('StatementCreatedAndDataImported', $bankImport->numReleve, $bankImport->nbReconciled, $bankImport->nbCreated) ?>
</a>
</div>
