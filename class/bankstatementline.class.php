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

	const TYPE_CREDIT = 1;
	const TYPE_DEBIT = -1;
	const STATUS_UNRECONCILED = 0;
	const STATUS_RECONCILED = 1;

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields=array(
		'rowid'            => array('type'=>'integer',      'label'=>'TechnicalID',     'enabled'=>1, 'position'=>1, 'notnull'=>1, 'visible'=> 1, 'noteditable'=>'1', 'index'=>1, 'comment'=>"Id"),
		'date'             => array('type'=>'date',         'label'=>'TransactionDate', 'enabled'=>1, 'position'=>2, 'notnull'=>1, 'visible'=> 1,),
		'label'            => array('type'=>'varchar(128)', 'label'=>'Label',           'enabled'=>1, 'position'=>3, 'notnull'=>0, 'visible'=> 1,),
		'amount'           => array('type'=>'double(24,8)', 'label'=>'Amount',          'enabled'=>1, 'position'=>4, 'notnull'=>1, 'visible'=> 1,),
		'type'             => array('type'=>'integer',      'label'=>'Type',            'enabled'=>1, 'position'=>5, 'notnull'=>1, 'visible'=> 1, 'arrayofkeyval'=>array(self::TYPE_CREDIT=>'Credit', self::TYPE_DEBIT=>'Debit'),),
		'status'           => array('type'=>'integer',      'label'=>'Status',          'enabled'=>1, 'position'=>6, 'notnull'=>1, 'visible'=> 1, 'arrayofkeyval'=>array(0=>'Unreconciled', 1=>'Reconciled'),),
		'fk_bankstatement' => array('type'=>'integer',      'label'=>'BankStatement',   'enabled'=>1, 'position'=>7, 'notnull'=>1, 'visible'=> 1, 'foreignkey'=>'bankstatement_bankstatement.rowid',),
	);
	public $rowid;
	public $date;
	public $label;
	public $amount;
	public $type;
	public $status;
	public $fk_bankstatement;
	// END MODULEBUILDER PROPERTIES

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
	 * Copies and converts cell values from the CSV onto the object.
	 *
	 * @param array $dataRow  Associative array with keys: 'date', 'label', 'credit', 'debit'
	 */
	public function setValuesFromCSVRow($dataRow)
	{
		global $conf;
		$this->status = self::STATUS_UNRECONCILED;
		$row = array_combine($this->CSVFormat->mapping, $dataRow);
		if (!empty($row['date'])) {
			$this->date = DateTime::createFromFormat(
				$this->CSVFormat->dateFormat,
				$row['date']
			);
		}
		if (!empty($this->date)) {
			$this->date->setTime(0, 0, 0);
			$this->date = $this->date->getTimestamp();
		} else {
			$this->error = 'ErrorBankStatementLineHasNoDate';
			return;
		}

		if (isset($row['label'])) $this->label = $row['label'];

		if (isset($row['credit']) && is_numeric($row['credit'])) {
			$this->credit = doubleval(price2num($row['credit']));
			$this->type = self::TYPE_CREDIT;
			$this->amount = $this->credit;
		}

		if (isset($row['debit' ]) && is_numeric($row['debit'])) {
			$this->debit  = doubleval(price2num($row['debit' ]));
			$this->type = self::TYPE_DEBIT;
			$this->amount = $this->debit;
		}

		if ($this->credit && $this->debit) {
			$this->error = 'ErrorBankStatementLineHasBothDebitAndCredit';
		} elseif (!$this->credit && !$this->debit) {
			$this->error = 'ErrorBankStatementLineHasNeitherDebitAndCredit';
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
			// do not allow inserting a line that is attached to no bank statement
			return -1;
		}
		if ($this->type === null) {
			// do not allow inserting a line with no type
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
			$ret[$name] = $this->{$name};
		}
		$ret['error'] = $this->error;
		return $ret;
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
}
