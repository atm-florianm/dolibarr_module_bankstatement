
<?php
	/**
	 * Variables initialized outside the scope of static (initialized in the files that include this template):
	 * @var TransactionCompare $transactionCompare
	 */
?>
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
