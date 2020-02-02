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
 * \file        class/bankstatementformat.class.php
 * \ingroup     bankstatement
 * \brief       This file is a class file for BankStatementFormat
 */

/**
 * Simple data class for handling details of a CSV format variant (like the column separator etc.)
 *
 * Its role is only to turn various CSV formats into standardized bank transaction arrays with:
 *  - 'date'      date of transaction
 *  - 'label'     transaction label / reference
 *  - 'amount'    transaction amount (absolute value)
 *  - 'direction' direction of the transaction (-1 for debit, +1 for credit)
 *
 * See the samples/ directory for sample CSV formats that can be parsed.
 * It is not meant to perform any business logic beyond interpreting CSV.
 * TODO: save its values somewhere (within a bank account extrafield?) to
 * provide per-account CSV formats
 */
class BankStatementFormat
{
	const table_element = 'bankstatement_importformat';
	/** @var DoliDB $this->db */
	public $db;

	public $rowid;
	public $fk_account;

	/* mimic ModuleBuilder's fields */
	public $fields = array(
		'enclosure'        => array('conf' => 'BANKSTATEMENT_ENCLOSURE',        'type' => 'varchar',),
		'columnMapping'    => array('conf' => 'BANKSTATEMENT_COLUMN_MAPPING',   'type' => 'varchar',),
		'delimiter'        => array('conf' => 'BANKSTATEMENT_DELIMITER',        'type' => 'varchar',),
		'dateFormat'       => array('conf' => 'BANKSTATEMENT_DATE_FORMAT',      'type' => 'varchar',),
		'lineEnding'       => array('conf' => 'BANKSTATEMENT_LINE_ENDING',      'type' => 'varchar',),
		'escapeChar'       => array('conf' => 'BANKSTATEMENT_ESCAPE_CHAR',      'type' => 'varchar',),
		'enclosure'        => array('conf' => 'BANKSTATEMENT_ENCLOSURE',        'type' => 'varchar',),
		'skipFirstLine'    => array('conf' => 'BANKSTATEMENT_SKIP_FIRST_LINE',  'type' => 'integer',),
		'rotateCSV'        => array('conf' => 'BANKSTATEMENT_ROTATE_CSV',       'type' => 'integer',),
		'useDirection'     => array('conf' => 'BANKSTATEMENT_USE_DIRECTION',    'type' => 'integer',),
		'directionCredit'  => array('conf' => 'BANKSTATEMENT_DIRECTION_CREDIT', 'type' => 'varchar',),
		'directionDebit'   => array('conf' => 'BANKSTATEMENT_DIRECTION_DEBIT',  'type' => 'varchar',),
	);
	public $fieldByConfName = array();
	public $columnMapping = 'date;label;credit;debit';
	public $dateFormat = 'Y-m-d';
	public $enclosure  = '"';
	public $delimiter  = ';';
	public $lineEnding = "\\r\\n|\\r|\\n"; // NULL means stream_get_line will split at '\n', '\r' and '\r\n'
	//                                   ()
	public $fileChunkSize     = 4096; // how many characters will be read at a time.
	//                                   In previous implementations it was the maximum length of a CSV line but it no
	//                                   longer is (buffering ensures longer lines can be handled).
	public $maxLineBufferSize = 16384; // sanity check: if the wrong file is sent (a large binary file for instance),
	//                                    don't wait until the server is out of memory to raise an error.
	public $escapeChar       = '\\';
	// TODO: when $rotateCSV is true, a CSV line is a logical column
	//       (e.g. one CSV line for dates, one for credits, etc.)
	//       this will require moving a part of BankStatement::createFromCSVFile() here
	//       (BankStatementFormat will load the lines to an array before BankStatement saves them)
	public $rotateCSV        = false;
	public $directionCredit  = 'credit';
	public $directionDebit   = 'debit';
	public $useDirection     = false;
	public $skipFirstLine    = true;
	public $directionMapping = array('credit' => DIRECTION_CREDIT, 'debit' => DIRECTION_DEBIT);

	/** @var array $TColumnName */
	public $TColumnName;

	// internal CSV string buffering
	private $TFullLines = array(); // stores full CSV lines as string until they are read
	private $lineBuffer = '';      // internal buffer for leftover bytes from the fread() call that might not be a full line

	/**
	 * BankStatementFormat constructor.
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
		foreach ($this->fields as $fieldName => $fieldParams) {
			$this->fieldByConfName[$fieldParams['conf']] = $fieldName;
		}
	}

	/**
	 * Returns the next line of text from the open file descriptor $csvFile. It basically works a lot like PHP standard
	 * stream_get_line() [https://www.php.net/manual/en/function.stream-get-line.php], except that
	 * stream_get_line($csvFile, $this->max) without the $ending parameter will not work the same: with getNextCSVLine,
	 * $this->lineEnding can be a regular expression.
	 *
	 * @param resource  $csvFile  An open CSV file
	 * @param boolean   $isFirst  Whether this is the first line read from the CSV
	 * @return string|null        Null when feof is reached
	 * @throws ErrorException
	 */
	public function getNextCSVLine($csvFile)
	{
		$ending = $this->lineEnding ? '#' . $this->lineEnding . '#' :  "#\\r\\n|\\r|\\n#";
		if (count($this->TFullLines)) {
			return array_shift($this->TFullLines);
		} elseif (feof($csvFile)) {
			return null;
		} else {
			while (count($lines = preg_split($ending, $this->lineBuffer)) === 1 && !feof($csvFile)) {
				$chunk = fread($csvFile, $this->fileChunkSize);
				if (strlen($this->lineBuffer) + strlen($chunk) > $this->maxLineBufferSize) {
					throw new ErrorException('CSV line length exceeds maxLineBufferSize');
				}
				$this->lineBuffer .= $chunk;
			}
			$this->lineBuffer = array_pop($lines);
			if ($lines) $this->TFullLines += $lines;
			return array_shift($this->TFullLines);
		}
	}

	/**
	 * @param $csvLine
	 * @return array
	 */
	public function parseCSVLine($csvLine)
	{
		$delimiter = $this->delimiter;
		if ($delimiter === '\\t') $delimiter = "\t";
		return str_getcsv($csvLine, $this->delimiter, $this->enclosure, $this->escapeChar);
	}

	/**
	 *
	 * @param $dataRow
	 * @return array
	 */
	public function getStandardDataRow($dataRow)
	{
		global $langs;
		$standardRow = array(
			'date' => null,
			'label' => null,
			'amount' => null,
			'direction' => null,
			'error' => ''
		);

		if (count($this->TColumnName) !== count($dataRow)) {
			$standardRow['error'] = $langs->trans('ErrorCSVColumnCountMismatch', count($this->TColumnName), count($dataRow));
			return $standardRow;
		}
		$combinedRow = array_combine($this->TColumnName, $dataRow);
		if (!empty($combinedRow['date'])) {
			$standardRow['date'] = DateTime::createFromFormat(
				$this->dateFormat,
				$combinedRow['date']
			);
		}
		// must happen once in a billion, but let’s account for the possibility that the CSV line has the date '1970-01-01'
		$CSVDateIsEpoch = isset($combinedRow['date']) && $combinedRow['date'] === date($this->dateFormat, 0);

		if (!empty($standardRow['date']) || $CSVDateIsEpoch) {
			$standardRow['date']->setTime(0, 0, 0);
			$standardRow['date'] = $standardRow['date']->getTimestamp();
		} else {
			$standardRow['error'] = 'ErrorBankStatementLineHasNoDate';
			return $standardRow;
		}

		if (isset($combinedRow['label'])) $standardRow['label'] = $combinedRow['label'];

		// Depending on format configuration, use one of several specialized methods to
		// get 'amount' and 'direction'
		if (in_array('amount', $this->TColumnName)) {
			if ($this->useDirection) {
				$this->standardizeAmountDirectionDataRow($standardRow, $combinedRow);
			} else {
				$this->standardizeSignedAmountDataRow($standardRow, $combinedRow);
			}
		} else {
			$this->standardizeCreditDebitDataRow($standardRow, $combinedRow);
		}

		return $standardRow;
	}

	/**
	 * Set keys 'amount' and 'direction' of a standard row using 'credit' and 'debit' from
	 * the unprocessed $combinedRow
	 * @param $standardRow
	 * @param $combinedRow
	 */
	public function standardizeCreditDebitDataRow(&$standardRow, $combinedRow)
	{
		$hasCredit = !empty($combinedRow['credit']);
		$hasDebit  = !empty($combinedRow['debit']);

		if ($hasCredit XOR $hasDebit) {
			// either debit or credit is set, but not both = OK
			if ($hasCredit) {
				$standardRow['direction'] = DIRECTION_CREDIT;
				$standardRow['amount'] = doubleval(price2num($combinedRow['credit']));
			} else {
				$standardRow['direction'] = DIRECTION_DEBIT;
				$standardRow['amount'] = doubleval(price2num($combinedRow['debit' ]));
			}
		} elseif ($hasCredit && $hasDebit) {
			// both set = error
			$standardRow['error'] = 'ErrorBankStatementLineHasBothDebitAndCredit';
		} else {
			// both unset = error
			$standardRow['error'] = 'ErrorBankStatementLineHasNeitherDebitAndCredit';
		}
	}

	/**
	 * Set keys 'amount' and 'direction' of a standard row using 'amount' and 'direction' from
	 * the unprocessed $combinedRow
	 * @param $standardRow
	 * @param $combinedRow
	 */
	public function standardizeAmountDirectionDataRow(&$standardRow, $combinedRow)
	{
		$hasAmount = !empty($combinedRow['amount']);
		$hasType   = !empty($combinedRow['direction']);
		if (!$hasAmount || !$hasType) {
			$standardRow['error'] = 'ErrorBankStatementLineHasNoAmountOrType';
		}
		$standardRow['amount'] = doubleval(price2num($combinedRow['amount']));
		if (array_key_exists($combinedRow['direction'], $this->directionMapping)) {
			$standardRow['direction'] = $this->directionMapping[$combinedRow['direction']];
		} else {
			$standardRow['error'] = 'ErrorInvalidBankStatementDirectionValue';
		}
	}

	/**
	 * Set keys 'amount' and 'direction' of a standard row using a raw (signed) amount.
	 * @param $standardRow
	 * @param $combinedRow
	 */
	public function standardizeSignedAmountDataRow(&$standardRow, $combinedRow)
	{
		$hasAmount = !empty($combinedRow['amount']);
		if (!$hasAmount) {
			$standardRow['error'] = 'ErrorBankStatementLineHasNoAmount';
		}
		$rawAmount = doubleval(price2num($combinedRow['amount']));
		if (!$rawAmount) {
			$standardRow['error'] = 'ErrorBankStatementAmountIsZero';
		}
		$standardRow['amount'] = abs($rawAmount);
		$standardRow['direction'] = getAmountType($rawAmount);
	}

	/**
	 * Set a field's value enforcing type
	 *
	 * @param $fieldName
	 * @param $fieldValue
	 * @return bool
	 */
	public function setFieldValue($fieldName, $fieldValue)
	{
		if (!array_key_exists($fieldName, $this->fields)) return false;
		$fieldParams = $this->fields[$fieldName];
		$type = $fieldParams['type'];
		if ($type === 'integer') $fieldValue = intval($fieldValue);
		$this->{$fieldName} = $fieldValue;
	}

	/**
	 * @param int    $fk_account  If empty, values will be loaded from the $conf object; if set, values will be
	 *                            loaded from llx_bankstatement_importformat where fk_account matches $fk_account
	 * @return int  -1 on failure, 0 if no record found, 1 on success
	 */
	public function load($fk_account = 0)
	{
		global $conf;
		$this->fk_account = $fk_account;
		if (!empty($fk_account)) {
			$TSelectColumn = array_map('strtolower', array_keys($this->fields));
			$sql = 'SELECT ' . join(', ', $TSelectColumn) . ' FROM ' . MAIN_DB_PREFIX . self::table_element
				   . ' WHERE fk_account = ' . intval($fk_account);
//			var_dump($sql);
			$resql = $this->db->query($sql);
			if (!$resql) {
				// TODO error message? 'ErrorNoCSVFormatFoundForThisAccount'
				return -1;
			}
			$obj = $this->db->fetch_array($resql);
			if (empty($obj)) {
				return 0;
			}
			foreach ($this->fields as $fieldName => $fieldParams) {
				$dbFieldName = strtolower($fieldName);
				$value = $obj[$dbFieldName];
				$confName = isset($fieldParams['conf']) ? $fieldParams['conf'] : null;
				if (empty($value) && $value != 0 && !empty($confName) && !empty($conf->global->{$confName})) {
					// load only empty values from $conf
					$value = $conf->global->{$confName};
				}
				$this->{$fieldName} = $value;
			}
		} else {
			// load from $conf
			foreach ($this->fields as $fieldName => $fieldParams) {
				$confName = isset($fieldParams['conf']) ? $fieldParams['conf'] : null;
				if ($confName) {
					$this->{$fieldName} = $conf->global->{$confName};
				}
			}
		}

		/*
		 * All empty values from the account configuration will be replaced
		 * with the $conf->global equivalent.
		 */
		// TODO: simplify this!! instead of saving every value in a conf, save a row in llx_bankstatement_importformat
		//       with no fk_account (change the foreign key constraint if need be) and save the resulting ID in a const.
		$this->_initMappingArrays();
		$this->directionMapping = array(
		);
		return 1;
	}

	/**
	 * @param int  $fk_account
	 * @return bool
	 */
	public function save($fk_account = null)
	{
		$errors = 0;
		if ($fk_account === null) $fk_account = $this->fk_account;
		if ($fk_account === 0) {
			// save to $conf
			foreach ($this->fields as $fieldKey => $fieldParams) {
				$dbFieldName = strtolower($fieldKey);
				$value = $this->{$fieldKey};
				$confName = isset($fieldParams['conf']) ? $fieldParams['conf'] : null;
				if (!empty($confName)) {
					if (dolibarr_set_const($this->db, $confName, $value) < 0) {
						$errors++;
					}
				}
			}
			return ($errors === 0);
		} else {
			// save to self::table_element
			// first check if the record already exists

			$sqlCount = 'SELECT COUNT(rowid) AS n_rows FROM ' . MAIN_DB_PREFIX . self::table_element
				      . ' WHERE fk_account = ' . intval($fk_account);
			$resql = $this->db->query($sqlCount);
			if (!$resql) { return false; }
			$obj = $this->db->fetch_object($resql);
			if (!$obj) { return false; }

			// build a table with keys => values to be saved for both INSERT and UPDATE
			$TSaveValue = array('fk_account' => $fk_account);
			foreach ($this->fields as $fieldKey => $fieldParams) {
				$type = $fieldParams['type'];
				$value = $this->{$fieldKey};
				$dbKey = strtolower($fieldKey);
				// no need to manage more types for now
				if ($type === 'varchar') {
					$TSaveValue[$dbKey] = '"' . $this->db->escape($value) . '"';
				} elseif ($type === 'integer') {
					$TSaveValue[$dbKey] = '' . intval($value);
				}
			}

			if ($obj->n_rows == 1) {
				$TUpdateValue = array();
				foreach ($TSaveValue as $k => $v) { $TUpdateValue[] = '' . $k . ' = ' . $v; }
				$sql = 'UPDATE ' . MAIN_DB_PREFIX . self::table_element . ' SET'
					   . ' ' . join(', ', $TUpdateValue)
					   . ' WHERE fk_account = ' . intval($fk_account);
			} else {
				$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . self::table_element
					   . ' (' . join(', ', array_keys($TSaveValue)) . ')'
					   . ' VALUES (' . join(', ', array_values($TSaveValue)) . ')';
			}
			$resql = $this->db->query($sql);
			if (!$resql) return false;
			return true;
		}
	}

	/**
	 * Computes $this->TColumnName (an array) from $this->columnMapping (a string)
	 */
	private function _initMappingArrays()
	{
		// TColumnName tells the parser which columns to consider and what field they are mapped to
		$this->TColumnName = preg_split('/[;,: ]/', $this->columnMapping);

		// directionMapping enables loading CSV files with a 'direction' column whose value
		// is a string (like 'DEB' for debit, or 'CR' for credit) that must be mapped to
		// its "standard" meaning
		$this->directionMapping = array(
			$this->directionCredit => DIRECTION_CREDIT,
			$this->directionDebit  => DIRECTION_DEBIT
		);
	}

}
