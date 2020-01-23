<?php
/* Copyright (C) 2017  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2020  ATM Consulting <support@atm-consulting.fr>
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
 * \file        class/bankstatementline.class.php
 * \ingroup     bankstatement
 * \brief       This file is a CRUD class file for BankStatementLine (Create/Read/Update/Delete)
 */

dol_include_once('/bankstatement/lib/bankstatement.lib.php');

/**
 * Class for BankStatementLine.
 */
class BankStatementLine extends CommonObjectLine
{
	/** @var DoliDB $db */
	public $db;

	/** @var string $element ID to identify managed object */
	public $element = 'bankstatementdet';

	/** @var string $table_element Name of table without prefix where object is stored */
	public $table_element = 'bankstatement_bankstatementdet';

	const DIRECTION_CREDIT = DIRECTION_CREDIT;
	const DIRECTION_DEBIT  = DIRECTION_DEBIT;
	const STATUS_UNRECONCILED = STATUS_UNRECONCILED;
	const STATUS_RECONCILED   = STATUS_RECONCILED;

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields=array(
		'rowid'            => array('type'=>'integer',      'label'=>'TechnicalID',     'enabled'=>1, 'position'=>1, 'notnull'=>1, 'visible'=> 0, 'noteditable'=>'1', 'index'=>1, 'comment'=>"Id"),
		'date'             => array('type'=>'date',         'label'=>'TransactionDate', 'enabled'=>1, 'position'=>2, 'notnull'=>1, 'visible'=> 1,),
		'label'            => array('type'=>'varchar(128)', 'label'=>'Label',           'enabled'=>1, 'position'=>3, 'notnull'=>0, 'visible'=> 1,),
		'amount'           => array('type'=>'double(24,8)', 'label'=>'Amount',          'enabled'=>1, 'position'=>4, 'notnull'=>1, 'visible'=> 1,),
		'status'           => array('type'=>'integer',      'label'=>'Status',          'enabled'=>1, 'position'=>6, 'notnull'=>1, 'visible'=> 1, 'arrayofkeyval'=>array(0=>'Unreconciled', 1=>'Reconciled'),),
		'fk_bankstatement' => array('type'=>'integer',      'label'=>'BankStatement',   'enabled'=>1, 'position'=>7, 'notnull'=>1, 'visible'=> 1, 'foreignkey'=>'bankstatement_bankstatement.rowid',),
//		'fk_payment'       => array('type'=>'integer',      'label'=>'BankPayment',     'enabled'=>1, 'position'=>8, 'notnull'=>1, 'visible'=> 1, 'foreignkey'=>'paiement.rowid',),
	);
	public $rowid;
	public $date;
	public $label;
	public $amount;
	public $direction;
	public $status;
	public $fk_bankstatement;
	// END MODULEBUILDER PROPERTIES

	/** @var BankStatement $statement */
	public $statement;

	// NOT module builder: these definitions will mimic modulebuilder fields to help display the 'credit' and 'debit' dynamic fields
	// we call these 'dynamic' because their values are not stored directly (as is) in database
	public $dynamicFields = array(
		'credit' => array('type' => 'double(24,8)', 'label'=>'Credit', 'enabled'=>1, 'visible'=>1),
		'debit'  => array('type' => 'double(24,8)', 'label'=>'Debit',  'enabled'=>1, 'visible'=>1),
	);

	/** @var float $credit */
	public $credit;
	/** @var float $debit */
	public $debit;

	/** @var string $error */
	public $error;

	/** @var BankStatementFormat $CSVFormat */
	public $CSVFormat;

	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Tell whether debit or credit; current implementation is "naive" and very simple, it may evolve
	 * to handle edge cases (e.g. zero-amount transactions).
	 * @return int|null  BankStatementLine::DIRECTION_CREDIT or BankStatementLine::DIRECTION_DEBIT or null
	 */
	public function getDirection()
	{
		if ($this->amount === 0) return null;
		return ($this->amount > 0) ? self::DIRECTION_CREDIT : self::DIRECTION_DEBIT;
	}

	/**
	 * Copies and converts cell values from the CSV onto the object.
	 *
	 * @param array $dataRow  Associative array with keys: 'date', 'label', 'credit', 'debit'
	 */
	public function setValuesFromStandardizedCSVRow($dataRow)
	{
		$this->status    = self::STATUS_UNRECONCILED;
		$this->date      = $dataRow['date'];
		$this->label     = $dataRow['label'];
		$this->amount    = $dataRow['amount'];
		if ($dataRow['direction'] === self::DIRECTION_DEBIT) $this->amount = -$this->amount;
		$this->error     = $dataRow['error'];
		$this->calculateDebitCredit();
	}

	/**
	 * Extends CommonObject::setVarsFromFetchObj for BankStatementLine:
	 * set credit and debit values from amount and type.
	 * @param stdClass $obj
	 */
	public function setVarsFromFetchObj(&$obj)
	{
		parent::setVarsFromFetchObj($obj);
		$this->calculateDebitCredit();
	}

	/**
	 * Set the 'debit' and 'credit' fields using amount and direction
	 */
	public function calculateDebitCredit()
	{
		switch ($this->getDirection()) {
			case self::DIRECTION_CREDIT:
				$this->credit = $this->amount;
				break;
			case self::DIRECTION_DEBIT:
				$this->debit = -$this->amount;
				break;
		}
	}

	/**
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = false)
	{
		if (!$this->fk_bankstatement) {
			// do not allow inserting a line that is not attached a bank statement
			return -1;
		}
		if ($this->getDirection() === null) {
			// do not allow inserting a line with no direction
			return -1;
		}
		if (!$this->status) $this->status = 0;
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Primarily for debugging: returns an array with the properties extracted from the CSV file.
	 */
	public function getFieldValues()
	{
		$ret = array();
		foreach ($this->fields as $name => $fieldDescriptor) {
			$ret[$name] = $this->{$name === 'rowid' ? 'id' : $name};
		}
		$ret['error'] = $this->error;
		return $ret;
	}

	/**
	 * Returns the related bank statement (loads it in $this->statement if necessary)
	 * Note: not a pure function as it may set $this->statement
	 * @return BankStatement|null  The related bank statement (null if loading failed)
	 */
	public function getStatement()
	{
		if (empty($this->statement)) {
			$statement = new BankStatement($this->db);
			if ($statement->fetch($this->fk_bankstatement) <= 0) {
				// failure
				return null;
			} else {
				$this->statement = $statement;
			}
		}
		return $this->statement;
	}

	/**
	 * @param string $fieldKey
	 * @return array
	 */
	public function getFieldDefinition($fieldKey)
	{
		if (isset($this->fields[$fieldKey]))
			return $this->fields[$fieldKey];
		if (isset($this->dynamicFields[$fieldKey]))
			return $this->dynamicFields[$fieldKey];
		global $langs;
		setEventMessages($langs->trans('ErrorFieldNotFound', $fieldKey), array(), 'errors');
	}

	/**
	 * @param array $fieldKey
	 * @return string
	 */
	public function showInputField($fieldKey)
	{
		$filterName = 'search_' . $fieldKey;
		$filterInput = '<input type="text" name="%s" value="%s" />';
		switch ($fieldKey) {
			case 'date':
				$monthFilterName = $filterName . '_month';
				$yearFilterName = $filterName . '_year';
				$yearFilterValue = intval(GETPOST($yearFilterName, 'int'));
				if (!$yearFilterValue) $yearFilterValue = date('Y');
				$monthFilterValue = intval(GETPOST($monthFilterName, 'int'));
				if (!$monthFilterValue) $monthFilterValue = date('m');
				$monthSelect = sprintf(
					'<input class="monthinput" type="number" name="%s" value="%d" min="1" max="12" />',
					$monthFilterName,
					$monthFilterValue);
				$yearSelect = sprintf(
					'<input class="yearinput" type="number" name="%s" value="%d" min="%d" max="%d"/>',
					$yearFilterName,
					$yearFilterValue, date('Y') - 50, date('Y') + 50);
				$filterInput = $monthSelect . $yearSelect;
			default:
				return parent::showInputField();
		}
		return $filterInput;
	}

	/**
	 * Set statement line properties from the database record identified by $id
	 * @param $id
	 * @return int
	 */
	public function fetch($id)
	{
		return self::fetchCommon($id);
	}

	/**
	 * Load list of objects in memory from the database.
	 *
	 * @param  string      $sortorder    Sort Order
	 * @param  string      $sortfield    Sort field
	 * @param  int         $limit        limit
	 * @param  int         $offset       Offset
	 * @param  array       $filter       Filter array. Example array('field'=>'valueforlike', 'customurl'=>...)
	 * @param  string      $filtermode   Filter mode (AND or OR)
	 * @return array|int                 int <0 if KO, array of pages if OK
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, array $filter = array(), $filtermode = 'AND')
	{
		global $conf;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$records = array();

		$sql = 'SELECT ';
		$sql .= $this->getFieldList();
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' as t';
		if (isset($this->ismultientitymanaged) && $this->ismultientitymanaged == 1) $sql .= ' WHERE t.entity IN ('.getEntity($this->table_element).')';
		else $sql .= ' WHERE 1 = 1';
		// Manage filter
		$sqlwhere = array();
		if (count($filter) > 0) {
			foreach ($filter as $key => $value) {
				if ($key == 't.rowid') {
					$sqlwhere[] = $key.'='.$value;
				}
				elseif (strpos($key, 'date') !== false) {
					$sqlwhere[] = $key.' = \''.$this->db->idate($value).'\'';
				}
				elseif ($key == 'customsql') {
					$sqlwhere[] = $value;
				}
				else {
					$sqlwhere[] = $key.' LIKE \'%'.$this->db->escape($value).'%\'';
				}
			}
		}
		if (count($sqlwhere) > 0) {
			$sql .= ' AND ('.implode(' '.$filtermode.' ', $sqlwhere).')';
		}

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if (!empty($limit)) {
			$sql .= ' '.$this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			if ($limit) $num = min($limit, $num);
			$i = 0;
			while ($i < $num)
			{
				$obj = $this->db->fetch_object($resql);

				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);

				$records[$record->id] = $record;

				$i++;
			}
			$this->db->free($resql);

			return $records;
		} else {
			$this->errors[] = 'Error '.$this->db->lasterror();
			dol_syslog(__METHOD__.' '.join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	/**
	 * @param int $mode
	 * @return string  A text meant to indicate the "status" of the object. Here the status is either Unreconciled or Reconciled
	 */
	public function getLibStatut($mode=0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * @param $status
	 * @param $mode
	 * @return string  A text meant to indicate a "status". Here the status is either Unreconciled or Reconciled
	 */
	public function LibStatut($status, $mode)
	{
		// phpcs:enable

		if (empty($this->labelStatus) || empty($this->labelStatusShort))
		{
			global $langs;
			foreach($this->fields['status']['arrayofkeyval'] as $key => $val) {
				$this->labelStatus[$key] = $langs->trans($val);
			}
		}

		$statusType = 'status'.$status;

		return dolGetStatus($this->labelStatus[$status], $this->labelStatus[$status], '', $statusType, $mode);
	}

	/**
	 * Overrides CommonObject::showOutputField;
	 * Return HTML string to put an output field into a page
	 * Code very similar with showOutputField of extra fields
	 *
	 * @param  array   		$val	       Array of properties for field to show
	 * @param  string  		$key           Field key (name)
	 * @param  string  		$value         Preselected value to show (for date type it must be in timestamp format, for amount or price it must be a php numeric value)
	 * @param  string  		$moreparam     To add more parameters on html input tag
	 * @param  string  		$keysuffix     Prefix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param  string  		$keyprefix     Suffix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param  string|int	$morecss       Value for css to define style/length of field. May also be a numeric.
	 * @return string
	 */
	public function showOutputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '')
	{
		global $langs;
		switch($key)
		{
			case 'amount':
				return price($value, 1, $langs, 1, 2, 2);
			case 'status':
				return '<span class="badge badge-status' . $value . ' badge-status">' . $langs->trans($val['arrayofkeyval'][$value]) . '</span>';
			case 'fk_bankstatement':
				return '';
			case 'account':
				$account = new Account($this->db);
				$account->fetch($this->fk_account);
				return $account->getNomUrl(1, '', 'reflabel');
			default:
				return parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
		}
	}
}
