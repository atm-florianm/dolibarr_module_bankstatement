<?php


class TransactionCompare
{
	/** @var string Negative direction token */
	private $neg_dir;

	/** @var DoliDB $db */
	protected $db;

	/** @var Account */
	public $account;
//	public $file;

	public $dateStart;
	public $dateEnd;
//	public $numReleve;
//	public $hasHeader;
//	public $lineHeader; // Si on historise, on concerve le header d'origine pour avoir le bon intitulé dans nos future tableaux
//	public $TOriginLine=array(); // Contient les lignes d'origin du fichier, pour l'historisation

	/** @var AccountLine[] $TBank */
	public $TBank          = array(); // Will contain all account lines of the period
	/** @var array $TCheckReceipt */
	public $TCheckReceipt  = array(); // Will contain check receipt made for account lines of the period
	/** @var BankStatementLine[] $TImportedLines */
	public $TImportedLines = array(); // Will contain all imported lines

	public $nbCreated = 0;
	public $nbReconciled = 0;

	function __construct($db) {
		$this->db = &$db;
		$this->dateStart = strtotime('first day of last month');
		$this->dateEnd = strtotime('last day of last month');
	}

	/**
	 * @param int $accountId
	 * @return bool
	 */
	function fetchAccount($accountId)
	{
		global $langs;
		// Bank account selected
		if($accountId <= 0) {
			setEventMessage($langs->trans('ErrorAccountIdNotSelected'), 'errors');
			return false;
		} else {
			$this->account = new Account($this->db);
			$this->account->fetch($accountId);
			return True;
		}
	}

	/**
	 * @param $dateStart
	 * @param $dateEnd
	 */
	function setStartAndEndDate($dateStart, $dateEnd) {
		// Start and end date regarding bank statement
		$this->dateStart = $dateStart;
		$this->dateEnd = $dateEnd;
	}

	/**
	 * @param int[] $TLineId  Array of ids of BankStatementLine objects
	 */
	function load_transactions($TLineId) {
		if (empty($TLineId)) {
			// TODO: handle error
			global $langs;
			setEventMessages($langs->trans('ErrorSelectAtLeastOneStatementLine'), array(), 'errors');
		}
		$this->load_imported_transactions($TLineId);
		$this->load_bank_transactions();
		$this->load_check_receipt();
	}

	/**
	 * Load bank transactions (llx_bank) from Dolibarr
	 */
	function load_bank_transactions() {
		$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "bank WHERE fk_account = " . $this->account->id . " ";
		$sql.= "AND dateo BETWEEN '" . date('Y-m-d', $this->dateStart) . "' AND '" . date('Y-m-d', $this->dateEnd) . "' ";
		$sql.= "ORDER BY datev DESC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			global $langs;
//			var_dump($sql);
			setEventMessages($langs->trans('SQLError', $this->db->lasterror()), array(), 'errors');
			return;
		}
		$TBankLineId = array();
		while($obj = $this->db->fetch_object($resql)) {
			$TBankLineId[] = $obj->rowid;
		}

		foreach($TBankLineId as $bankLineId) {
			$bankLine = new AccountLine($this->db);
			$bankLine->fetch($bankLineId);
			$this->TBank[$bankLineId] = $bankLine;
		}
	}

	/**
	 * Load check receipts related to Dolibarr bank transactions
	 */
	function load_check_receipt() {
		foreach($this->TBank as $bankLine) {
			if($bankLine->fk_bordereau > 0 && empty($this->TCheckReceipt[$bankLine->fk_bordereau])) {
				$bord = new RemiseCheque($this->db);
				$bord->fetch($bankLine->fk_bordereau);

				$this->TCheckReceipt[$bankLine->fk_bordereau] = $bord;
			}
		}
	}

	/**
	 * Load bank transactions as imported from CSV-formatted bank statements (issued from the bank, not Dolibarr)
	 * @param array $TBankstatementLineId
	 */
	function load_imported_transactions($TBankstatementLineId = array())
	{
		$earliestDate = null;
		$latestDate = null;
		$TError = array();
		foreach ($TBankstatementLineId as $id) {
			$bankStatementLine = new BankStatementLine($this->db);
			if ($bankStatementLine->fetch($id) <= 0) {
				$TError[] = $id;
				continue;
			}
			$this->TImportedLines[] = $bankStatementLine;
			// find start and end date
			if ($earliestDate === null || $bankStatementLine->date < $earliestDate) $earliestDate = $bankStatementLine->date;
			if ($latestDate   === null || $bankStatementLine->date > $latestDate)   $latestDate   = $bankStatementLine->date;
		}
		$this->dateStart = $earliestDate;
		$this->dateEnd   = $latestDate;
	}

	/**
	 * Legacy CSV parsing method (was used by the CSV parser to parse the "rotated" CSV format).
	 * This functionality has not yet been reimplemented, so I kept this method as a reminder.
	 *
	 * @param $mapping
	 * @param $data
	 * @return array
	 */
	function construct_data_tab_column_file(&$mapping, $data) {

		$TDataFinal = array();
		$pos = 0;
		foreach($mapping as $m) {

			$TTemp = explode(':', $m);

			$label_colonne = $TTemp[0];
			$nb_car = $TTemp[1];
			$res = substr($data, $pos, $nb_car);
			$res = trim($res);
			$TDataFinal[$label_colonne] = $res;
			$pos += $nb_car;
		}

		return $TDataFinal;

	}

	/**
	 *
	 */
	function compare_transactions() {

		// For each file transaction, we search in Dolibarr bank transaction if there is a match by amount
		foreach($this->TImportedLines as &$importedLine) {
			$transac = $this->search_dolibarr_transaction_by_amount($importedLine);
			if($transac === false) $transac = $this->search_dolibarr_transaction_by_receipt($importedLine);
			$importedLine->bankline = $transac;
		}
	}

	/**
	 * @param BankStatementLine $importedLine
	 * @return array|bool  Array of llx_bank lines matching the searched amount (and, optionally, label).
	 */
	private function search_dolibarr_transaction_by_amount($importedLine) {
		global $conf, $langs;
		$langs->load("banks");
		// [FM] : est-ce que ça a un sens de faire floatval(price2num(<float>)) ?
		$amount = floatval(price2num($importedLine->amount)); // Transform to numeric string
		foreach($this->TBank as $i => $bankLine) {
			$test = ($amount == $bankLine->amount);
			if($conf->global->BANKSTATEMENT_MATCH_BANKLINES_BY_AMOUNT_AND_LABEL) {
				$test = ($test && $importedLine->label == $bankLine->label);
			}
			if(!empty($test)) {
				// unset because this dolibarr bank line is now assigned to the bankstatement line
				unset($this->TBank[$i]);

				return array($this->get_bankline_data($bankLine));
			}
		}

		return false;
	}

	/**
	 * @param BankStatementLine $importedLine
	 * @return array|bool
	 */
	private function search_dolibarr_transaction_by_receipt($importedLine) {
		global $langs;
		$langs->load("banks");
		// [FM] : est-ce que ça a un sens de faire floatval(price2num(<float>)) ?
		$amount = floatval(price2num($importedLine->amount)); // Transform to numeric string
		foreach($this->TCheckReceipt as $bordereau) {
			if($amount == $bordereau->amount) {
				$TBankLine = array();
				foreach($this->TBank as $i => $bankLine) {
					if($bankLine->fk_bordereau == $bordereau->id) {
						unset($this->TBank[$i]);

						$TBankLine[] = $this->get_bankline_data($bankLine);
					}
				}

				return $TBankLine;
			}
		}

		return false;
	}

	/**
	 * @param AccountLine $bankLine
	 * @return array
	 */
	private function get_bankline_data($bankLine) {
		global $langs, $db;

		if(!empty($bankLine->num_releve)) {
			$link = '<a href="' . dol_buildpath(
					'/compta/bank/releve.php'
					. '?num=' . $bankLine->num_releve
					. '&account=' . $bankLine->fk_account, 2)
					. '">'
					. $bankLine->num_releve
					. '</a>';
			$result = $langs->trans('AlreadyReconciledWithStatement', $link);
			$autoaction = false;
		} else {
			$result = $langs->trans('WillBeReconciledWithStatement', ''); /* TODO: fix this or fix translation */
			$autoaction = true;
		}

		$societestatic = new Societe($db);
		$userstatic = new User($db);
		$chargestatic = new ChargeSociales($db);
		$memberstatic = new Adherent($db);

		$links = $this->account->get_url($bankLine->id);
		$relatedItem = '';
		foreach($links as $key=>$val) {
			if ($links[$key]['type'] == 'company') {
				$societestatic->id = $links[$key]['url_id'];
				$societestatic->name = $links[$key]['label'];
				$relatedItem = $societestatic->getNomUrl(1,'',16);
			} else if ($links[$key]['type'] == 'user') {
				$userstatic->id = $links[$key]['url_id'];
				$userstatic->lastname = $links[$key]['label'];
				$relatedItem = $userstatic->getNomUrl(1,'');
			} else if ($links[$key]['type'] == 'sc') {
				// sc=old value
				$chargestatic->id = $links[$key]['url_id'];
				if (preg_match('/^\((.*)\)$/i',$links[$key]['label'],$reg)) {
					if ($reg[1] == 'socialcontribution') $reg[1] = 'SocialContribution';
					$chargestatic->lib = $langs->trans($reg[1]);
				} else {
					$chargestatic->lib = $links[$key]['label'];
				}
				$chargestatic->ref = $chargestatic->lib;
				$relatedItem = $chargestatic->getNomUrl(1,16);
			} else if ($links[$key]['type'] == 'member') {
				$memberstatic->id = $links[$key]['url_id'];
				$memberstatic->ref = $links[$key]['label'];
				$relatedItem = $memberstatic->getNomUrl(1,16,'card');
			}
		}

		return array(
			'id' => $bankLine->id
			,'url' => $bankLine->getNomUrl(1)
			,'date' => dol_print_date($bankLine->datev,"day")
			,'label' => (preg_match('/^\((.*)\)$/i',$bankLine->label,$reg) ? $langs->trans($reg[1]) : dol_trunc($bankLine->label,60))
			,'amount' => price($bankLine->amount)
			,'result' => $result
			,'autoaction' => $autoaction
			,'relateditem' => $relatedItem
			,'time' => $bankLine->datev
		);
	}

	/** Actions made after file check by user
	 * @param array $TLine  Nested assoc array describing the conciliation actions the user wants to apply
	 */
	public function applyConciliation($TLine)
	{
		if (!empty($TLine['piece']))
		{
			dol_include_once('/compta/paiement/class/paiement.class.php');
			dol_include_once('/fourn/class/paiementfourn.class.php');
			dol_include_once('/fourn/class/fournisseur.facture.class.php');
			dol_include_once('/compta/sociales/class/paymentsocialcontribution.class.php');

			/*
			 * Reglemenent créé manuellement
			 */

			$db = &$this->db;
			foreach($TLine['piece'] as $iImportedLine=>$TObject)
			{
				if(!empty($TLine['fk_soc'][$iImportedLine])) {
					$l_societe = new Societe($db);
					$l_societe->fetch($TLine['fk_soc'][$iImportedLine]);
				}

				$fk_payment = $TLine['fk_payment'][$iImportedLine];
				$date_paye = $this->TImportedLines[$iImportedLine]->date;

				foreach($TObject as $typeObject=>$TAmounts)
				{
					if(!empty($TAmounts))
					{
						$this->normalizeTAmounts($TAmounts);
						switch ($typeObject)
						{
							case 'facture':
								$fk_bank = $this->doPaymentForFacture(
									$TLine,
									$TAmounts,
									$l_societe,
									$iImportedLine,
									$fk_payment,
									$date_paye
								);
								break;
							case 'fournfacture':
								$fk_bank = $this->doPaymentForFactureFourn(
									$TLine,
									$TAmounts,
									$l_societe,
									$iImportedLine,
									$fk_payment,
									$date_paye
								);
								break;
							case 'charge':
								$fk_bank = $this->doPaymentForCharge();
								break;
							default:
								continue;
								break;
						}
					}
				}
			}
			unset($TLine['piece']);
		}

		unset($TLine['fk_payment'], $TLine['fk_soc'], $TLine['type']);

		//	exit;

		if (isset($TLine['new']))
		{
			if(!empty($TLine['new'])) {
				foreach($TLine['new'] as $iImportedLine) {
					$bankLineId = $this->create_bank_transaction($this->TImportedLines[$iImportedLine]);
					if($bankLineId > 0) {
						$bankLine = new AccountLine($this->db);
						$bankLine->fetch($bankLineId);
						$this->reconcile_bank_transaction($bankLine, $this->TImportedLines[$iImportedLine]);
					}
				}
			}
			unset($TLine['new']);
		}

		foreach($TLine as $bankLineId => $iImportedLine)
		{
			$this->reconcile_bank_transaction($this->TBank[$bankLineId], $this->TImportedLines[$iImportedLine]);
//			if (!empty($conf->global->BANKSTATEMENT_HISTORY_IMPORT) && $bankLineId > 0)
//			{
//				$this->insertHistoryLine($PDOdb, $iImportedLine, $bankLineId);
//			}
		}
	}

	private function validateInvoices(&$TAmounts, $type) {

		global $db, $user;

		dol_include_once('/compta/facture/class/facture.class.php');
		dol_include_once('/fourn/class/fournisseur.facture.class.php');
		
		$TTypeElement = array('payment'=>'Facture', 'payment_supplier'=>'FactureFournisseur');
		
		if(!empty($TAmounts) && in_array($type, array_keys($TTypeElement))) {
			foreach($TAmounts as $facid=>$amount) {
				/** @var CommonObject $f */
				$f = new $TTypeElement[$type]($db);
				if($f->fetch($facid) > 0 && $f->statut == 0 && $amount > 0) $f->validate($user);
			}
		}

	}

	private function doPaymentForFacture(&$TLine, &$TAmounts, &$l_societe, $iImportedLine, $fk_payment, $date_paye)
	{
		return $this->doPayment($TLine, $TAmounts, $l_societe, $iImportedLine, $fk_payment, $date_paye, 'payment');
	}

	private function doPaymentForFactureFourn(&$TLine, &$TAmounts, &$l_societe, $iImportedLine, $fk_payment, $date_paye)
	{
		return $this->doPayment($TLine, $TAmounts, $l_societe, $iImportedLine, $fk_payment, $date_paye, 'payment_supplier');
	}

	private function doPaymentForCharge(&$TLine, &$TAmounts, &$l_societe, $iImportedLine, $fk_payment, $date_paye)
	{
		return $this->doPayment($TLine, $TAmounts, $l_societe, $iImportedLine, $fk_payment, $date_paye, 'payment_sc');
	}

	/**
	 * @param array $TLine  Nested array describing actions to be performed.
	 * @param array $TAmounts  Decomposition of the total amount.
	 *                         Keys: invoice IDs; values: amounts. A client may settle several invoices (or even settle
	 *                         them partially) with one payment; then we need to break down the total amount in order
	 *                         assign an amount to each invoice
	 * @param $l_societe
	 * @param int $iImportedLine  Array index of BankStatementLine in $this->TImportedLines
	 * @param $fk_payment
	 * @param $date_paye
	 * @param string $type
	 * @return int
	 */
	private function doPayment(&$TLine, &$TAmounts, &$l_societe, $iImportedLine, $fk_payment, $date_paye, $type='payment')
	{
		global $conf, $langs,$user;

		$note = $langs->trans('BankStatementTitle');

		if ($type == 'payment') $paiement = new Paiement($this->db);
		elseif ($type == 'payment_supplier') $paiement = new PaiementFourn($this->db);
		elseif ($type == 'payment_supplier') $paiement = new PaymentSocialContribution($this->db);
		else exit($langs->trans('BankStatement_FatalError_PaymentType_NotPossible', $type));

		if(!empty($conf->global->BANKSTATEMENT_ALLOW_DRAFT_INVOICE)) $this->validateInvoices($TAmounts, $type);

		$paiement->datepaye     = $date_paye;
		$paiement->amounts      = $TAmounts;   // Array with all payments dispatching
		$paiement->paiementid   = $fk_payment;
		$paiement->num_paiement = '';
		$paiement->note         = $note;

		$paiement_id = $paiement->create($user, 1);

		if ($paiement_id > 0)
		{
			$bankLineId = $paiement->addPaymentToBank(
				$user,
				$type,
				!empty($this->TImportedLines[$iImportedLine]->label) ? $this->TImportedLines[$iImportedLine]->label : $note,
				$this->account->id,
				$l_societe->name,
				''
			);
			$TLine[$bankLineId] = $iImportedLine;

			$bankLine = new AccountLine($this->db);
			$bankLine->fetch($bankLineId);
			$this->TBank[$bankLineId] = $bankLine;

			// On supprime le new saisi
			foreach($TLine['new'] as $k=>$iFileLineNew)
			{
				if($iFileLineNew == $iImportedLine) unset($TLine['new'][$k]);
			}

			// Uniquement pour les factures client (les acomptes fournisseur n'existent pas)
			if($conf->global->BANKSTATEMENT_AUTO_CREATE_DISCOUNT && $type === 'payment') $this->createDiscount($TAmounts);

			return $bankLineId;
		}

		return 0; // Payment fail, can't return bankLineId
	}

	/**
	 * @param $TAmounts
	 */
	private function createDiscount(&$TAmounts)
	{

		global $db, $user, $langs;

		dol_include_once('/core/class/discount.class.php');

		foreach($TAmounts as $id_fac => $amount) {

			$object = new Facture($db);
			$object->fetch($id_fac);
			if($object->type != 3) continue; // Uniquement les acomptes

			$object->fetch_thirdparty();

			// Check if there is already a discount (protection to avoid duplicate creation when resubmit post)
			$discountcheck=new DiscountAbsolute($db);
			$result=$discountcheck->fetch(0,$object->id);

			$canconvert=0;
			if ($object->type == Facture::TYPE_DEPOSIT && $object->paye == 1 && empty($discountcheck->id)) $canconvert=1;	// we can convert deposit into discount if deposit is payed completely and not already converted (see real condition into condition used to show button converttoreduc)
			if ($object->type == Facture::TYPE_CREDIT_NOTE && $object->paye == 0 && empty($discountcheck->id)) $canconvert=1;	// we can convert credit note into discount if credit note is not payed back and not already converted and amount of payment is 0 (see real condition into condition used to show button converttoreduc)
			if ($canconvert)
			{
				$db->begin();

				// Boucle sur chaque taux de tva
				$i = 0;
				$amount_ht = array();
				$amount_tva = array();
				$amount_ttc = array();
				foreach ($object->lines as $line) {
					$amount_ht [$line->tva_tx] += $line->total_ht;
					$amount_tva [$line->tva_tx] += $line->total_tva;
					$amount_ttc [$line->tva_tx] += $line->total_ttc;
					$i ++;
				}

				// Insert one discount by VAT rate category
				$discount = new DiscountAbsolute($db);
				if ($object->type == Facture::TYPE_CREDIT_NOTE)
					$discount->description = '(CREDIT_NOTE)';
				elseif ($object->type == Facture::TYPE_DEPOSIT)
					$discount->description = '(DEPOSIT)';
				else {
					setEventMessage($langs->trans('CantConvertToReducAnInvoiceOfThisType'),'errors');
				}
				$discount->tva_tx = abs($object->total_ttc);
				$discount->fk_soc = $object->socid;
				$discount->fk_facture_source = $object->id;

				$error = 0;
				foreach ($amount_ht as $tva_tx => $xxx) {
					$discount->amount_ht = abs($amount_ht [$tva_tx]);
					$discount->amount_tva = abs($amount_tva [$tva_tx]);
					$discount->amount_ttc = abs($amount_ttc [$tva_tx]);
					$discount->tva_tx = abs($tva_tx);

					$result = $discount->create($user);
					if ($result < 0)
					{
						$error++;
						break;
					}
				}

				if (empty($error))
				{
					// Classe facture
					$result = $object->set_paid($user);
					if ($result >= 0)
					{
						//$mesgs[]='OK'.$discount->id;
						$db->commit();
					}
					else
					{
						setEventMessage($object->error,'errors');
						$db->rollback();
					}
				}
				else
				{
					setEventMessage($discount->error,'errors');
					$db->rollback();
				}
			}

		}

	}

	/**
	 * @param $importedLine
	 * @return int
	 */
	private function create_bank_transaction($importedLine) {
		global $user;

		$bankLineId = $this->account->addline(
			$importedLine->date,
			'PRE',
			$importedLine->label,
			$importedLine->amount,
			'',
			'',
			$user
		);
		$this->nbCreated++;

		return $bankLineId;
	}

	/**
	 * @param AccountLine       $bankLine     A Dolibarr bank account transaction line
	 * @param BankStatementLine $importedLine An bank statement transaction imported with BankStatement
	 */
	private function reconcile_bank_transaction($bankLine, $importedLine) {
		global $user;

		// Set conciliation
		$bankStatement = $importedLine->getStatement();
		if ($bankStatement === null) {
			// todo : handle statement not loaded error
		}
		$bankLine->num_releve = $bankStatement->label;
		$bankLine->update_conciliation($user, 0);
		$importedLine->setStatus($user, $importedLine::STATUS_RECONCILED);

		// Update value date
		$dateDiff = ($importedLine->date - strtotime($bankLine->datev)) / 24 / 3600;
		$bankLine->datev_change($bankLine->id, $dateDiff);

		$this->nbReconciled++;
	}

	/**
	 * Extract negative direction token from direction key
	 *
	 * @param array $matches Regex matches
	 * @return string Last separator (Effectively removing the extracted negative direction)
	 */
	private function extractNegDir(array $matches) {
		$this->neg_dir = $matches[1];
		return substr($matches[0], -1);
	}

	/**
	 * @param $TAmounts  Array associating invoice IDs with amounts. It represents the breakdown of a payment (because
	 *                   one payment may pay several invoices).
	 */
	private function normalizeTAmounts(&$TAmounts)
	{
		foreach ($TAmounts as $key => &$value)
		{
			if ($value === '') $value = 0;
		}
	}
}
