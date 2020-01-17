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
 * \file        class/bankstatement.class.php
 * \ingroup     bankstatement
 * \brief       This file is a CRUD class file for BankStatement (Create/Read/Update/Delete)
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once 'bankstatementline.class.php';
dol_include_once('/bankstatement/core/modules/bankstatement/mod_bankstatement_standard.php');
dol_include_once('/bankstatement/lib/bankstatement.lib.php');

/**
 * Class for BankStatement
 */
class BankStatement extends CommonObject
{
	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'bankstatement';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'bankstatement_bankstatement';

	/**
	 * @var int  Does bankstatement support multicompany module ? 0=No test on entity, 1=Test with field entity, 2=Test with link by societe
	 */
	public $ismultientitymanaged = 0;

	/**
	 * @var int  Does bankstatement support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 1;

	/**
	 * @var string String with name of icon for bankstatement. Must be the part after the 'object_' into object_bankstatement.png
	 */
	public $picto = 'bankstatement@bankstatement';

	const STATUS_UNRECONCILED = STATUS_UNRECONCILED;
	const STATUS_RECONCILED   = STATUS_RECONCILED;


	/**
	 *  'type' if the field format ('integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter]]', 'varchar(x)', 'double(24,8)', 'real', 'price', 'text', 'html', 'date', 'datetime', 'timestamp', 'duration', 'mail', 'phone', 'url', 'password')
	 *         Note: Filter can be a string like "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.nature:is:NULL)"
	 *  'label' the translation key.
	 *  'enabled' is a condition when the field must be managed.
	 *  'position' is the sort order of field.
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'default' is a default value for creation (can still be overwrote by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 if you want to have a total on list for this field. Field type must be summable like integer or double(24,8).
	 *  'css' is the CSS style to use on field. For example: 'maxwidth200'
	 *  'help' is a string visible as a tooltip on field
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
	 *  'arraykeyval' to set list of value if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel")
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *
	 *  Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
	 */

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields=array(
		'rowid'             => array('type'=>'integer',      'label'=>'TechnicalID',      'enabled'=>1, 'position'=>1,    'notnull'=>1,  'visible'=> 0, 'noteditable'=>1, 'index'=>1, 'comment'=>"Id"),
		'ref'               => array('type'=>'varchar(128)', 'label'=>'Ref',              'enabled'=>1, 'position'=>10,   'notnull'=>1,  'visible'=> 4, 'noteditable'=>1, 'default'=>'(PROV)', 'index'=>1, 'searchall'=>1, 'showoncombobox'=>'1', 'comment'=>"Reference"),
		'label'             => array('type'=>'varchar(128)', 'label'=>'Label',            'enabled'=>1, 'position'=>11,   'notnull'=>0,  'visible'=> 1, 'searchall'=>1,),
		'status'            => array('type'=>'integer',      'label'=>'Status',           'enabled'=>1, 'position'=>12,   'notnull'=>1,  'visible'=> 4, 'noteditable'=>1, 'arrayofkeyval'=>array(self::STATUS_UNRECONCILED=>'Unreconciled', self::STATUS_RECONCILED=>'Reconciled'),),
		'fk_account'        => array('type'=>'integer',      'label'=>'Account',          'enabled'=>1, 'position'=>13,   'notnull'=>1,  'visible'=> 1, 'foreignkey'=>'bank_account.rowid',),
		'date_start'        => array('type'=>'date',         'label'=>'DateStart',        'enabled'=>1, 'position'=>20,   'notnull'=>0,  'visible'=> -4,),
		'date_end'          => array('type'=>'date',         'label'=>'DateEnd',          'enabled'=>1, 'position'=>21,   'notnull'=>0,  'visible'=> -4,),
		'tms'               => array('type'=>'timestamp',    'label'=>'DateModification', 'enabled'=>1, 'position'=>501,  'notnull'=>0,  'visible'=> 0,),
		'fk_user_import'    => array('type'=>'integer',      'label'=>'UserImport',       'enabled'=>1, 'position'=>502,  'notnull'=>0,  'visible'=> 0, 'foreignkey'=>'user.rowid',),
		'date_import'       => array('type'=>'date',         'label'=>'DateImport',       'enabled'=>1, 'position'=>503,  'notnull'=>0,  'visible'=> 0, 'noteditable'=>1),
		'fk_user_reconcile' => array('type'=>'integer',      'label'=>'UserReconcile',    'enabled'=>1, 'position'=>504,  'notnull'=>0,  'visible'=> 0, 'foreignkey'=>'user.rowid',),
		'date_reconcile'    => array('type'=>'date',         'label'=>'DateReconcile',    'enabled'=>1, 'position'=>505,  'notnull'=>0,  'visible'=> 4,),
		'import_key'        => array('type'=>'varchar(14)',  'label'=>'ImportId',         'enabled'=>1, 'position'=>1000, 'notnull'=>-1, 'visible'=> 0,),
		'entity'            => array('type'=>'integer',      'label'=>'Entity',           'enabled'=>1, 'position'=>1000, 'notnull'=>0,  'visible'=> 0, 'foreignkey'=>'entity.rowid',),
	);
	public $rowid;
	public $ref;
	public $tms;
	public $import_key;
	public $label;
	public $fk_account;
	public $fk_user_import;
	public $date_import;
	public $fk_user_reconcile;
	public $date_reconcile;
	public $date_start;
	public $date_end;
	public $status;
	public $entity;
	// END MODULEBUILDER PROPERTIES


	// If this object has a subtable with lines

	/**
	 * @var int    Name of subtable line
	 */
	public $table_element_line = 'bankstatement_line';

	/**
	 * @var int    Field with ID of parent key if this field has a parent
	 */
	//public $fk_element = 'fk_bankstatement';

	/**
	 * @var int    Name of subtable class that manage subtable lines
	 */
	//public $class_element_line = 'BankStatementline';

	/**
	 * @var array	List of child tables. To test if we can delete object.
	 */
	//protected $childtables=array();

	/**
	 * @var array	List of child tables. To know object to delete on cascade.
	 */
	//protected $childtablesoncascade=array('bankstatement_bankstatementdet');

	/**
	 * @var BankStatementLine[]     Array of subtable lines
	 */
	public $lines = array();

	/** @var BankStatementFormat $CSVFormat */
	public $CSVFormat;


	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf, $langs;

		$this->db = $db;

		$this->CSVFormat = new BankStatementFormat(
			$conf->global->BANKSTATEMENT_MAPPING,
			$conf->global->BANKSTATEMENT_DATE_FORMAT,
			'"',
			$conf->global->BANKSTATEMENT_SEPARATOR,
			null,
			false,
			array($conf->global->BANKSTATEMENT_DIRECTION_CREDIT => DIRECTION_CREDIT, $conf->global->BANKSTATEMENT_DIRECTION_DEBIT => DIRECTION_DEBIT),
			$conf->global->BANKSTATEMENT_USE_DIRECTION,
			$conf->global->BANKSTATEMENT_HEADER
		);

		if (empty($conf->global->MAIN_SHOW_TECHNICAL_ID) && isset($this->fields['rowid'])) $this->fields['rowid']['visible'] = 0;
		if (empty($conf->multicompany->enabled) && isset($this->fields['entity'])) $this->fields['entity']['enabled'] = 0;

		// Example to show how to set values of fields definition dynamically
		/*if ($user->rights->bankstatement->bankstatement->read) {
			$this->fields['myfield']['visible'] = 1;
			$this->fields['myfield']['noteditable'] = 0;
		}*/

		// Unset fields that are disabled
		foreach ($this->fields as $key => $val)
		{
			if (isset($val['enabled']) && empty($val['enabled']))
			{
				unset($this->fields[$key]);
			}
		}

		// Translate some data of arrayofkeyval
		if (is_object($langs))
		{
			foreach($this->fields as $key => $val)
			{
				if (is_array($val['arrayofkeyval']))
				{
					foreach($val['arrayofkeyval'] as $key2 => $val2)
					{
						$this->fields[$key]['arrayofkeyval'][$key2]=$langs->trans($val2);
					}
				}
			}
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
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * @param string $filePath
	 * @param int    $fk_account
	 * @return bool  True on success, False on failure
	 */
	public function createFromCSVFile($filePath, $fk_account)
	{
		/*
		 * Formats gérés :
		 * ☑ format mac (les "\n" sont remplacés par des "\r") => il suffit de faire le remplacement inverse
		 *      tester avec samples/mac-sample.csv
		 * ☑ format "montant + direction" : au lieu d’avoir une colonne débit et une colonne crédit, on a une colonne
		 *   "montant" et une colonne "direction" : la colonne direction indique si c’est un débit ou un
		 *   crédit
		 *      tester avec samples/direction-sample.csv
		 * ☑ format "montant signé" : une colonne "montant" avec un montant positif ou négatif
		 *      tester avec ../samples/signed-amount-sample.csv
		 * ☐ TODO format "colonnes" : ce n’est même pas un format CSV (ou plutôt c’est comme un CSV renversé)
		 *      tester avec samples/colonnes-sample.csv
		 *
		 * Dans l’ancien module, le format Mac est spécifié par une conf.
		 * Le format "direction" et le format "colonnes" sont gérés en ajoutant des indications ésotériques
		 * non documentées (?) au mapping.
		 *
		 * Je pense qu’à terme, il faudrait gérer chacun de ces 3 formats spéciaux avec une conf séparée
		 * et pouvoir enregistrer des profils de configuration (associables à des comptes)
		 *
		*/
		global $conf, $user, $langs;
		if (!is_file($filePath)) {
			return false;
		}

		$this->fk_account = $fk_account;

		$this->ref = $this->getNextNumRef();

		$this->db->begin();
		if ($this->create($user) < 0) {
			$this->db->rollback();
			return false;
		}

		/** @var BankStatementLine[] $TBankStatementLine */
		$TBankStatementLine = array();
		$TBankStatementInvalidLine = array();

		// Actual loading of the CSV file
		$csvFile = fopen($filePath, 'r');
		for ($i = 0; ($csvLine = $this->CSVFormat->getNextCSVLine($csvFile)) !== null ; $i++) {
			if ($i === 0 && $this->CSVFormat->skipFirstLine)
				continue;
			// do not parse empty lines or lines with whitespace only
			if (empty($csvLine) || preg_match('/^\s+$/', $csvLine)) {
				continue;
			}

			$line = new BankStatementLine($this->db);
			$line->CSVFormat = $this->CSVFormat;
			$line->fk_bankstatement = $this->id;
			$dataRow = $this->CSVFormat->parseCSVLine($csvLine);
			$dataRow = $this->CSVFormat->getStandardDataRow($dataRow);

			if ($dataRow['error']) {
				setEventMessages(
					$langs->transnoentities(
						'ErrorCSVLineFormatMismatch',
						($i + $this->CSVFormat->skipFirstLine),
						dol_htmlentities($csvLine) . '<br>' . $langs->trans($dataRow['error'])
					),
					array(),
					'errors');
			}

			$line->setValuesFromStandardizedCSVRow($dataRow);

			if ($line->create($user) < 0) {
				setEventMessages($langs->trans('ErrorUnableToCreateBankStatementLine'), array(), 'errors');
				continue;
			}

			if ($line->error) {
				$TBankStatementInvalidLine[] = $line;
			} else {
				$TBankStatementLine[] = $line;
			}
		}
		if (empty($TBankStatementInvalidLine) && !empty($TBankStatementLine)) {
			$this->db->commit();
		} else {
			$this->db->rollback();
		}

		return true;
	}

	/**
	 * Clone an object into another one
	 *
	 * @param  	User 	$user      	User that creates
	 * @param  	int 	$fromid     Id of object to clone
	 * @return 	mixed 				New object created, <0 if KO
	 */
	public function createFromClone(User $user, $fromid)
	{
		global $langs, $extrafields;
		$error = 0;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$object = new self($this->db);

		$this->db->begin();

		// Load source object
		$result = $object->fetchCommon($fromid);
		if ($result > 0 && !empty($object->table_element_line)) $object->fetchLines();

		// get lines so they will be clone
		//foreach($this->lines as $line)
		//	$line->fetch_optionals();

		// Reset some properties
		unset($object->id);
		unset($object->fk_user_creat);
		unset($object->import_key);


		// Clear fields
		$object->ref = empty($this->fields['ref']['default']) ? "copy_of_".$object->ref : $this->fields['ref']['default'];
		$object->label = empty($this->fields['label']['default']) ? $langs->trans("CopyOf")." ".$object->label : $this->fields['label']['default'];
		$object->status = self::STATUS_DRAFT;
		// ...
		// Clear extrafields that are unique
		if (is_array($object->array_options) && count($object->array_options) > 0)
		{
			$extrafields->fetch_name_optionals_label($this->table_element);
			foreach ($object->array_options as $key => $option)
			{
				$shortkey = preg_replace('/options_/', '', $key);
				if (!empty($extrafields->attributes[$this->element]['unique'][$shortkey]))
				{
					unset($object->array_options[$key]);
				}
			}
		}

		// Create clone
		$object->context['createfromclone'] = 'createfromclone';
		$result = $object->createCommon($user);
		if ($result < 0) {
			$error++;
			$this->error = $object->error;
			$this->errors = $object->errors;
		}

		if (!$error)
		{
			// copy internal contacts
			if ($this->copy_linked_contact($object, 'internal') < 0)
			{
				$error++;
			}
		}

		if (!$error)
		{
			// copy external contacts if same company
			if (property_exists($this, 'socid') && $this->socid == $object->socid)
			{
				if ($this->copy_linked_contact($object, 'external') < 0)
					$error++;
			}
		}

		unset($object->context['createfromclone']);

		// End
		if (!$error) {
			$this->db->commit();
			return $object;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param int    $id   Id object
	 * @param string $ref  Ref
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		$result = $this->fetchCommon($id, $ref);
		if ($result > 0 && !empty($this->table_element_line)) $this->fetchLines();
		return $result;
	}

	/**
	 * Load object lines in memory from the database
	 *
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetchLines()
	{
		$this->lines = array();

		$result = $this->fetchLinesCommon();
		return $result;
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
			$i = 0;
			while ($i < min($limit, $num))
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
	 * Update object into database
	 *
	 * @param  User $user      User that modifies
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = false)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param User $user       User that deletes
	 * @param bool $notrigger  false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = false)
	{
		return $this->deleteCommon($user, $notrigger);
		//return $this->deleteCommon($user, $notrigger, 1);
	}

	/**
	 *  Delete a line of object in database
	 *
	 *	@param  User	$user       User that delete
	 *  @param	int		$idline		Id of line to delete
	 *  @param 	bool 	$notrigger  false=launch triggers after, true=disable triggers
	 *  @return int         		>0 if OK, <0 if KO
	 */
	public function deleteLine(User $user, $idline, $notrigger = false)
	{
		if ($this->status < 0)
		{
			$this->error = 'ErrorDeleteLineNotAllowedByObjectStatus';
			return -2;
		}

		return $this->deleteLineCommon($user, $idline, $notrigger);
	}


	/**
	 *	Validate object
	 *
	 *	@param		User	$user     		User making status change
	 *  @param		int		$notrigger		1=Does not execute triggers, 0= execute triggers
	 *	@return  	int						<=0 if OK, 0=Nothing done, >0 if KO
	 */
	public function validate($user, $notrigger = 0)
	{
		global $conf, $langs;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$error = 0;

		// Protection
		if ($this->status == self::STATUS_VALIDATED)
		{
			dol_syslog(get_class($this)."::validate action abandonned: already validated", LOG_WARNING);
			return 0;
		}

		/*if (! ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->bankstatement->create))
		 || (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->bankstatement->bankstatement_advance->validate))))
		 {
		 $this->error='NotEnoughPermissions';
		 dol_syslog(get_class($this)."::valid ".$this->error, LOG_ERR);
		 return -1;
		 }*/

		$now = dol_now();

		$this->db->begin();

		// Define new ref
		if (!$error && (preg_match('/^[\(]?PROV/i', $this->ref) || empty($this->ref))) // empty should not happened, but when it occurs, the test save life
		{
			$num = $this->getNextNumRef();
		}
		else
		{
			$num = $this->ref;
		}
		$this->newref = $num;

		// Validate
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " SET ref = '".$this->db->escape($num)."',";
		$sql .= " status = ".self::STATUS_VALIDATED.",";
		$sql .= " date_validation = '".$this->db->idate($now)."',";
		$sql .= " fk_user_valid = ".$user->id;
		$sql .= " WHERE rowid = ".$this->id;

		dol_syslog(get_class($this)."::validate()", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql)
		{
			dol_print_error($this->db);
			$this->error = $this->db->lasterror();
			$error++;
		}

		if (!$error && !$notrigger)
		{
			// Call trigger
			$result = $this->call_trigger('BANKSTATEMENT_VALIDATE', $user);
			if ($result < 0) $error++;
			// End call triggers
		}

		if (!$error)
		{
			$this->oldref = $this->ref;

			// Rename directory if dir was a temporary ref
			if (preg_match('/^[\(]?PROV/i', $this->ref))
			{
				// Now we rename also files into index
				$sql = 'UPDATE '.MAIN_DB_PREFIX."ecm_files set filename = CONCAT('".$this->db->escape($this->newref)."', SUBSTR(filename, ".(strlen($this->ref) + 1).")), filepath = 'bankstatement/".$this->db->escape($this->newref)."'";
				$sql .= " WHERE filename LIKE '".$this->db->escape($this->ref)."%' AND filepath = 'bankstatement/".$this->db->escape($this->ref)."' and entity = ".$conf->entity;
				$resql = $this->db->query($sql);
				if (!$resql) { $error++; $this->error = $this->db->lasterror(); }

				// We rename directory ($this->ref = old ref, $num = new ref) in order not to lose the attachments
				$oldref = dol_sanitizeFileName($this->ref);
				$newref = dol_sanitizeFileName($num);
				$dirsource = $conf->bankstatement->dir_output.'/bankstatement/'.$oldref;
				$dirdest = $conf->bankstatement->dir_output.'/bankstatement/'.$newref;
				if (!$error && file_exists($dirsource))
				{
					dol_syslog(get_class($this)."::validate() rename dir ".$dirsource." into ".$dirdest);

					if (@rename($dirsource, $dirdest))
					{
						dol_syslog("Rename ok");
						// Rename docs starting with $oldref with $newref
						$listoffiles = dol_dir_list($conf->bankstatement->dir_output.'/bankstatement/'.$newref, 'files', 1, '^'.preg_quote($oldref, '/'));
						foreach ($listoffiles as $fileentry)
						{
							$dirsource = $fileentry['name'];
							$dirdest = preg_replace('/^'.preg_quote($oldref, '/').'/', $newref, $dirsource);
							$dirsource = $fileentry['path'].'/'.$dirsource;
							$dirdest = $fileentry['path'].'/'.$dirdest;
							@rename($dirsource, $dirdest);
						}
					}
				}
			}
		}

		// Set new ref and current status
		if (!$error)
		{
			$this->ref = $num;
			$this->status = self::STATUS_VALIDATED;
		}

		if (!$error)
		{
			$this->db->commit();
			return 1;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *	Set draft status
	 *
	 *	@param	User	$user			Object user that modify
	 *  @param	int		$notrigger		1=Does not execute triggers, 0=Execute triggers
	 *	@return	int						<0 if KO, >0 if OK
	 */
	public function setDraft($user, $notrigger = 0)
	{
		// Protection
		if ($this->status <= self::STATUS_DRAFT)
		{
			return 0;
		}

		/*if (! ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->bankstatement->write))
		 || (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->bankstatement->bankstatement_advance->validate))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		return $this->setStatusCommon($user, self::STATUS_DRAFT, $notrigger, 'BANKSTATEMENT_UNVALIDATE');
	}

	/**
	 *	Set cancel status
	 *
	 *	@param	User	$user			Object user that modify
	 *  @param	int		$notrigger		1=Does not execute triggers, 0=Execute triggers
	 *	@return	int						<0 if KO, 0=Nothing done, >0 if OK
	 */
	public function cancel($user, $notrigger = 0)
	{
		// Protection
		if ($this->status != self::STATUS_VALIDATED)
		{
			return 0;
		}

		/*if (! ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->bankstatement->write))
		 || (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->bankstatement->bankstatement_advance->validate))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		return $this->setStatusCommon($user, self::STATUS_CANCELED, $notrigger, 'BANKSTATEMENT_CLOSE');
	}

	/**
	 *	Set back to validated status
	 *
	 *	@param	User	$user			Object user that modify
	 *  @param	int		$notrigger		1=Does not execute triggers, 0=Execute triggers
	 *	@return	int						<0 if KO, 0=Nothing done, >0 if OK
	 */
	public function reopen($user, $notrigger = 0)
	{
		// Protection
		if ($this->status != self::STATUS_CANCELED)
		{
			return 0;
		}

		/*if (! ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->bankstatement->write))
		 || (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->bankstatement->bankstatement_advance->validate))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		return $this->setStatusCommon($user, self::STATUS_VALIDATED, $notrigger, 'BANKSTATEMENT_REOPEN');
	}

	/**
	 *  Return a link to the object card (with optionaly the picto)
	 *
	 *  @param  int     $withpicto                  Include picto in link (0=No picto, 1=Include picto into link, 2=Only picto)
	 *  @param  string  $option                     On what the link point to ('nolink', ...)
	 *  @param  int     $notooltip                  1=Disable tooltip
	 *  @param  string  $morecss                    Add more css on link
	 *  @param  int     $save_lastsearch_value      -1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
	 *  @return	string                              String with URL
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $langs, $hookmanager;

		if (!empty($conf->dol_no_mouse_hover)) $notooltip = 1; // Force disable tooltips

		$result = '';

		$label = '<u>'.$langs->trans("BankStatement").'</u>';
		$label .= '<br>';
		$label .= '<b>'.$langs->trans('Ref').':</b> '.$this->ref;

		$url = dol_buildpath('/bankstatement/bankstatement_card.php', 1).'?id='.$this->id;

		if ($option != 'nolink')
		{
			// Add param to save lastsearch_values or not
			$add_save_lastsearch_values = ($save_lastsearch_value == 1 ? 1 : 0);
			if ($save_lastsearch_value == -1 && preg_match('/list\.php/', $_SERVER["PHP_SELF"])) $add_save_lastsearch_values = 1;
			if ($add_save_lastsearch_values) $url .= '&save_lastsearch_values=1';
		}

		$linkclose = '';
		if (empty($notooltip))
		{
			if (!empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER))
			{
				$label = $langs->trans("ShowBankStatement");
				$linkclose .= ' alt="'.dol_escape_htmltag($label, 1).'"';
			}
			$linkclose .= ' title="'.dol_escape_htmltag($label, 1).'"';
			$linkclose .= ' class="classfortooltip'.($morecss ? ' '.$morecss : '').'"';
		}
		else $linkclose = ($morecss ? ' class="'.$morecss.'"' : '');

		$linkstart = '<a href="'.$url.'"';
		$linkstart .= $linkclose.'>';
		$linkend = '</a>';

		$result .= $linkstart;
		if ($withpicto) $result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), ($notooltip ? (($withpicto != 2) ? 'class="paddingright"' : '') : 'class="'.(($withpicto != 2) ? 'paddingright ' : '').'classfortooltip"'), 0, 0, $notooltip ? 0 : 1);
		if ($withpicto != 2) $result .= $this->ref;
		$result .= $linkend;
		//if ($withpicto != 2) $result.=(($addlabel && $this->label) ? $sep . dol_trunc($this->label, ($addlabel > 1 ? $addlabel : 0)) : '');

		global $action, $hookmanager;
		$hookmanager->initHooks(array('bankstatementdao'));
		$parameters = array('id'=>$this->id, 'getnomurl'=>$result);
		$reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook > 0) $result = $hookmanager->resPrint;
		else $result .= $hookmanager->resPrint;

		return $result;
	}

	/**
	 *  Return label of the status
	 *
	 *  @param  int		$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string 			       Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return the status
	 *
	 *  @param	int		$status        Id status
	 *  @param  int		$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return string 			       Label of status
	 */
	public function LibStatut($status, $mode = 0)
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
	 * @param string $action   Keyword for the action to be performed (e.g. 'delete', 'modify', etc.)
	 * @param bool   $disabled Whether to show a non-clickable, greyed-out button or a link
	 * @return string  A HTML <a> or <span> element (depending on $disabled)
	 */
	public function getActionButton($action, $disabled=false)
	{
		global $langs;
		$btnLabel = $langs->trans(ucfirst($action));
		$urlParameters = array();

		switch($action) {
			case 'delete':
				$btnClass = 'butActionDelete';
				break;
			default:
				$btnClass = 'butAction';
				$urlParameters['action'] = $action;
				$urlParameters['id']     = $this->id;
		}
		$actionUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($urlParameters);

		// if the user is not allowed to perform the action (or the button is disabled for any reason),
		// do not display a link
		if ($disabled) {
			return sprintf('<span class="butActionRefused">%s</span>', $btnLabel);
		} else {
			return sprintf('<a href="%s" class="%s">%s</a>', $actionUrl, $btnClass, $btnLabel);
		}
	}

	/**
	 *	Load the info information in the object
	 *
	 *	@param  int		$id       Id of object
	 *	@return	void
	 */
	public function info($id)
	{
		$sql = 'SELECT rowid, date_creation as datec, tms as datem,';
		$sql .= ' fk_user_creat, fk_user_modif';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' as t';
		$sql .= ' WHERE t.rowid = '.$id;
		$result = $this->db->query($sql);
		if ($result)
		{
			if ($this->db->num_rows($result))
			{
				$obj = $this->db->fetch_object($result);
				$this->id = $obj->rowid;
				if ($obj->fk_user_author)
				{
					$cuser = new User($this->db);
					$cuser->fetch($obj->fk_user_author);
					$this->user_creation = $cuser;
				}

				if ($obj->fk_user_valid)
				{
					$vuser = new User($this->db);
					$vuser->fetch($obj->fk_user_valid);
					$this->user_validation = $vuser;
				}

				if ($obj->fk_user_cloture)
				{
					$cluser = new User($this->db);
					$cluser->fetch($obj->fk_user_cloture);
					$this->user_cloture = $cluser;
				}

				$this->date_creation     = $this->db->jdate($obj->datec);
				$this->date_modification = $this->db->jdate($obj->datem);
				$this->date_validation   = $this->db->jdate($obj->datev);
			}

			$this->db->free($result);
		}
		else
		{
			dol_print_error($this->db);
		}
	}

	/**
	 * Initialise object with example values
	 * Id must be 0 if object instance is a specimen
	 *
	 * @return void
	 */
	public function initAsSpecimen()
	{
		$this->initAsSpecimenCommon();
	}

	/**
	 * 	Create an array of lines
	 *
	 * 	@return array|int		array of lines if OK, <0 if KO
	 */
	public function getLinesArray()
	{
		$this->lines = array();

		$objectline = new BankStatementLine($this->db, $this->CSVFormat);
		$result = $objectline->fetchAll('ASC', 'date', 0, 0, array('customsql'=>'fk_bankstatement = '.$this->id));

		if (is_numeric($result)) {
//			$this->error = $this->error;
//			$this->errors = $this->errors;
			return $result;
		} else {
			$this->lines = $result;
			return $this->lines;
		}
	}

	/**
	 *  Returns the reference to the following non used object depending on the active numbering module.
	 *
	 *  @return string      		Object free reference
	 */
	public function getNextNumRef()
	{
		global $langs, $conf;
		$langs->load("bankstatement@bankstatement");

		if (empty($conf->global->BANKSTATEMENT_BANKSTATEMENT_ADDON)) {
			$conf->global->BANKSTATEMENT_BANKSTATEMENT_ADDON = 'mod_bankstatement_standard';
		}

		if (!empty($conf->global->BANKSTATEMENT_BANKSTATEMENT_ADDON))
		{
			$mybool = false;

			$file = $conf->global->BANKSTATEMENT_BANKSTATEMENT_ADDON.".php";
			$classname = $conf->global->BANKSTATEMENT_BANKSTATEMENT_ADDON;

			// Include file with class
			$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
			foreach ($dirmodels as $reldir)
			{
				$dir = dol_buildpath($reldir."core/modules/bankstatement/");

				// Load file with numbering class (if found)
				$mybool |= @include_once $dir.$file;
			}

			if ($mybool === false)
			{
				dol_print_error('', "Failed to include file ".$file);
				return '';
			}

			$obj = new $classname();
			$numref = $obj->getNextValue($this);

			if ($numref != "")
			{
				return $numref;
			}
			else
			{
				$this->error = $obj->error;
				//dol_print_error($this->db,get_class($this)."::getNextNumRef ".$obj->error);
				return "";
			}
		}
		else
		{
			print $langs->trans("Error")." ".$langs->trans("Error_BANKSTATEMENT_BANKSTATEMENT_ADDON_NotDefined");
			return "";
		}
	}

	/**
	 *  Create a document onto disk according to template module.
	 *
	 *  @param	    string		$modele			Force template to use ('' to not force)
	 *  @param		Translate	$outputlangs	objet lang a utiliser pour traduction
	 *  @param      int			$hidedetails    Hide details of lines
	 *  @param      int			$hidedesc       Hide description
	 *  @param      int			$hideref        Hide ref
	 *  @param      null|array  $moreparams     Array to provide more information
	 *  @return     int         				0 if KO, 1 if OK
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		global $conf, $langs;

		$langs->load("bankstatement@bankstatement");

		if (!dol_strlen($modele)) {
			$modele = 'standard';

			if ($this->modelpdf) {
				$modele = $this->modelpdf;
			} elseif (!empty($conf->global->BANKSTATEMENT_ADDON_PDF)) {
				$modele = $conf->global->BANKSTATEMENT_ADDON_PDF;
			}
		}

		$modelpath = "core/modules/bankstatement/doc/";

		return $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
	}

	/**
	 * Action executed by scheduler
	 * CAN BE A CRON TASK. In such a case, parameters come from the schedule job setup field 'Parameters'
	 *
	 * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
	 */
	//public function doScheduledJob($param1, $param2, ...)
	public function doScheduledJob()
	{
		global $conf, $langs;

		//$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_mydedicatedlofile.log';

		$error = 0;
		$this->output = '';
		$this->error = '';

		dol_syslog(__METHOD__, LOG_DEBUG);

		$now = dol_now();

		$this->db->begin();

		// ...

		$this->db->commit();

		return $error;
	}

	/**
	 * Overrides CommonObject::showInputField;
	 * Return HTML string to put an input field into a page
	 * Code very similar with showInputField of extra fields
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
	public function showInputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = 0, $nonewbutton = 0)
	{
		switch($key)
		{
			case 'fk_account':
				$form = new Form($this->db);
				$form->select_comptes(-1, 'fk_account', 0, 'courant <> 2', 1, 'required');
				// the empty select should have empty value or the 'required' attribute will be useless
				echo '<script>$("#selectfk_account option[value=-1]").attr("value", "")</script>';
				return '';
			case 'status':
				return '';
			default:
				return parent::showInputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton);
		}
	}

	public function showOutputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '')
	{
		switch($key)
		{
			case 'fk_account':
				$account = new Account($this->db);
				$account->fetch($this->fk_account);
				return $account->getNomUrl(1, '', 'reflabel');
			default:
				return parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
		}
	}

	/**
	 *	Return HTML table for object lines
	 *	TODO Move this into an output class file (htmlline.class.php)
	 *	If lines are into a template, title must also be into a template
	 *	But for the moment we don't know if it's possible as we keep a method available on overloaded objects.
	 *
	 *	@param	string		$action				Action code
	 *	@param  string		$seller            	Object of seller third party
	 *	@param  string  	$buyer             	Object of buyer third party
	 *	@param	int			$selected		   	Object line selected
	 *	@param  int	    	$dateSelector      	1=Show also date range input fields
	 *  @param	string		$defaulttpldir		Directory where to find the template
	 *	@return	void
	 */
	public function printObjectLines($action, $seller, $buyer, $selected = 0, $dateSelector = 0, $defaulttpldir = '/core/tpl')
	{
		global $langs;
//		return parent::printObjectLines($action, $seller, $buyer, $selected, $dateSelector, $defaulttpldir);
		$fieldsToShow = array('date', 'label', 'credit', 'debit');
		$TTh = array_map(function($key) use ($langs) {
			return '<th class="linecol"' . $key . '>' . $langs->trans(ucfirst($key)) . '</th>';
		}, $fieldsToShow);
		?>
		<table id="tablelines" class="noborder noshadow" width="100%">
			<thead>
			<tr class="liste_titre nodrag nodrop">
				<?php echo join("\n", $TTh) ?>
			</tr>
			</thead>
			<tbody>
			<?php
			$i=0;
			foreach ($this->lines as $line) {
				$this->printObjectLine('show', $line, '', '', $i++, '', '', '', '', null, '');
			}
			?>
			</tbody>
		</table>

		<?php
	}
	/**
	 * Overrides CommonObject::printObjectLine
	 *	Return HTML content of a detail line
	 *	TODO Move this into an output class file (htmlline.class.php)
	 *
	 *	@param	string      		$action				GET/POST action
	 *	@param  BankStatementLine 	$line			    Selected object line to output
	 *	@param  string	    		$var               	Is it a an odd line (true)
	 *	@param  int		    		$num               	Number of line (0)
	 *	@param  int		    		$i					I
	 *	@param  int		    		$dateSelector      	1=Show also date range input fields
	 *	@param  string	    		$seller            	Object of seller third party
	 *	@param  string	    		$buyer             	Object of buyer third party
	 *	@param	int					$selected		   	Object line selected
	 *  @param  Extrafields			$extrafields		Object of extrafields
	 *  @param	string				$defaulttpldir		Directory where to find the template
	 *	@return	void
	 */
	public function printObjectLine($action, $line, $var, $num, $i, $dateSelector, $seller, $buyer, $selected = 0, $extrafields = null, $defaulttpldir = '/core/tpl')
	{
//		return parent::printObjectLine($action, $line, $var, $num, $i, $dateSelector, $seller, $buyer, $selected, $extrafields, $defaulttpldir);
//		global $conf, $langs, $user, $object, $hookmanager;
//		global $form;
//		global $object_rights, $disableedit, $disablemove, $disableremove;

		$fieldDisplayMethod = 'showOutputField';
		if ($action === 'editline') {
			$fieldDisplayMethod = 'showInputField';
		}
		$fieldsToShow = array('date', 'label', 'credit', 'debit');
		$THtmlRow = array_map(
			function ($fieldKey) use ($line, $fieldDisplayMethod) {
				return '<td class="linecol' . $fieldKey . '">'
					   . $line->{$fieldDisplayMethod}($line->getFieldDefinition($fieldKey), $fieldKey, $line->{$fieldKey})
					   . '</td>';
			},
			$fieldsToShow
		);
		$lineTypeClass = ($line->direction === $line::DIRECTION_CREDIT) ? 'credit' : 'debit';

		echo '<tr id="row-'.intval($i).'" class="' . $lineTypeClass . '_line" data-element="bankstatementdet" data-id="'.intval($line->id).'">' . "\n"
			 . join("\n", $THtmlRow)
			 . '</tr>' . "\n";
	}
}

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
	public $mapping           = array('date', 'label', 'credit', 'debit');
	public $dateFormat        = 'Y-m-d';
	public $enclosure         = '"';
	public $separator         = ';';
	public $lineEnding        = "\\r\\n|\\r|\\n"; // NULL means stream_get_line will split at '\n', '\r' and '\r\n'
	//                                   ()
	public $fileChunkSize     = 4096; // how many characters will be read at a time.
	//                                   In previous implementations it was the maximum length of a CSV line but it no
	//                                   longer is (buffering ensures longer lines can be handled).
	public $maxLineBufferSize = 16384; // sanity check: if the wrong file is sent (a large binary file for instance),
	//                                    don't wait until the server is out of memory to raise an error.
	public $escape            = '\\';
	public $columnMode        = false;
	public $directionMapping  = array('credit' => DIRECTION_CREDIT, 'debit' => DIRECTION_DEBIT);
	public $useDirection      = false;
	public $skipFirstLine     = true;

	// internal CSV string buffering
	private $TFullLines = array(); // stores full CSV lines as string until they are read
	private $lineBuffer = '';      // internal buffer for leftover bytes from the fread() call that might not be a full line
	public function __construct($mapping, $dateFormat, $enclosure, $separator, $lineEnding, $columnMode, $directionMapping, $useDirection, $skipFirstLine)
	{
		$this->mapping    = explode(';', $mapping);
		$this->dateFormat = $dateFormat;
		$this->enclosure  = $enclosure;
		$this->separator  = $separator;
		$this->lineEnding = $lineEnding;
		$this->columnMode = $columnMode;
		$this->directionMapping = $directionMapping;
		$this->useDirection     = $useDirection;
		$this->skipFirstLine    = $skipFirstLine;
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
		return str_getcsv($csvLine, $this->separator, $this->enclosure, $this->escape);
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

		if (count($this->mapping) !== count($dataRow)) {
			$standardRow['error'] = $langs->trans('ErrorCSVColumnCountMismatch', count($this->mapping), count($dataRow));
			return $standardRow;
		}
		$combinedRow = array_combine($this->mapping, $dataRow);
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
		if (in_array('amount', $this->mapping)) {
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
}
