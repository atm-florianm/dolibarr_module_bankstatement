<?php
	/**
	 * Variables initialized outside the scope of static (initialized in the files that include this template):
	 * @var TransactionCompare $transactionCompare
	 */
?>
<div class="border" width="100%">
	<dl class="reconcile-common">
		<div class="nocolbreak">
			<dt><?php echo $langs->trans("BankAccount") ?></dt>
			<dd><?php echo $transactionCompare->account->getNomUrl(1) ?></dd>
		</div>

		<div class="nocolbreak">
			<div>
				<dt><?php echo $langs->trans("DateStart") ?></dt>
				<dd><?php echo dol_print_date($transactionCompare->dateStart, 'day') ?></dd>
			</div>
			<div>
				<dt><?php echo $langs->trans("DateEnd") ?></dt>
				<dd><?php echo dol_print_date($transactionCompare->dateEnd, 'day') ?></dd>
			</div>
		</div>
	</dl>
</div>
