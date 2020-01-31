<div class="center">
<a href="<?php echo dol_buildpath('/compta/bank/bankentries_list.php', 2); ?>?id=<?php echo $transactionCompare->account->id ?>">
	<?php
	echo $langs->trans(
		'ReconciliationDone',
		$transactionCompare->nbReconciled,
		$transactionCompare->nbCreated);
	?>
</a>
</div>
