<div class="center">
<a href="<?php echo dol_buildpath('/bankimport/releve.php', 2); ?>?account=<?php echo $transactionCompare->account->id ?>&amp;num=<?php echo $bankImport->numReleve ?>">
	<?php
	echo $langs->trans(
		'StatementCreatedAndDataImported',
		$transactionCompare->nbReconciled,
		$transactionCompare->nbCreated);
	?>
</a>
</div>
