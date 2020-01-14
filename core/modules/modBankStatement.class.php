<?php
/* Copyright (C) 2004-2018 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2018      Nicolas ZABOURI      <info@inovea-conseil.com>
 * Copyright (C) 2020      ATM Consulting       <support@atm-consulting.fr>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \defgroup   bankstatement     Module BankStatement
 *  \brief      BankStatement module descriptor.
 *
 *  \file       htdocs/bankstatement/core/modules/modBankStatement.class.php
 *  \ingroup    bankstatement
 *  \brief      Description and activation file for module BankStatement
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module BankStatement
 */
class modBankStatement extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs,$conf;

		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 104078;    // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve id number for your module
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'bankstatement';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = "other";
		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';
		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));

		// Module label (no space allowed), used if translation string 'ModuleBankStatementName' not found (BankStatement is name of module).
		$this->name = preg_replace('/^mod/i','',get_class($this));
		// Module description, used if translation string 'ModuleBankStatementDesc' not found (BankStatement is name of module).
		$this->description = "Bank Statement Import";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "BankStatement: a module for importing bank statements and reconciling them within Dolibarr";

		$this->editor_name = 'ATM Consulting';
		$this->editor_url = 'https://www.atm-consulting.fr';

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = 'development';

		//Url to the file with your last numberversion of this module
		//$this->url_last_version = 'http://www.example.com/versionmodule.txt';
		// Key used in llx_const table to save module status enabled/disabled (where BANKSTATEMENT is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto='bankstatement@bankstatement';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			'triggers'      => 0,                               // Set this to 1 if module has its own trigger directory (core/triggers)
			'login'         => 0,                               // Set this to 1 if module has its own login method file (core/login)
			'substitutions' => 1,                               // Set this to 1 if module has its own substitution function file (core/substitutions)
			'menus'         => 0,                               // Set this to 1 if module has its own menus handler directory (core/menus)
			'theme'         => 0,                               // Set this to 1 if module has its own theme directory (theme)
			'tpl'           => 0,                               // Set this to 1 if module overwrite template dir (core/tpl)
			'barcode'       => 0,                               // Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'models'        => 0,                               // Set this to 1 if module has its own models directory (core/modules/xxx)
			'css' => array('/bankstatement/css/bankstatement.css.php'),	// Set this to relative path of css file if module has its own css file
			'js' => array('/bankstatement/js/bankstatement.js.php'),    // Set this to relative path of js file if module must load a js on all pages
			'hooks' => array(),                                 // Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context 'all'
			'moduleforexternal' => 0                            // Set this to 1 if feature of module are opened to external users
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/bankstatement/temp","/bankstatement/subdir");
		$this->dirs = array("/bankstatement/temp");

		// Config pages. Put here list of php page, stored into bankstatement/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@bankstatement");

		// Dependencies
		$this->hidden = false;                     // A condition to hide module
		$this->depends = array('modBanque');       // List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR1'=>'modModuleToEnableFR'...)
		$this->requiredby = array();               // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array();             // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)
		$this->langfiles = array("bankstatement@bankstatement");
		$this->need_dolibarr_version = array(4,0); // Minimum version of Dolibarr required by module
		$this->warnings_activation = array();      // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		$this->warnings_activation_ext = array();  // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...)

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		$this->const = array();

		if (! isset($conf->bankstatement) || ! isset($conf->bankstatement->enabled))
		{
			$conf->bankstatement=new stdClass();
			$conf->bankstatement->enabled=0;
		}

		// Array to add new pages in new tabs

		// Dictionaries
		$this->dictionaries=array();


		// Boxes/Widgets
		$this->boxes = array();


		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		$this->cronjobs = array();

		// Permissions
		$this->rights = array();    // Permission array used by this module

		$r=0;
		$this->rights[$r][0] = ($this->numero << 8) | $r;          // Permission id (must not be already used);
		$this->rights[$r][1] = 'ImportAndReconcileBankStatements'; // Permission label
		$this->rights[$r][3] = 0;                                  // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'read';                             // In php code, permission will be checked by test if ($user->rights->bankstatement->level1->level2)

		// Main menu entries
		$this->menu = array();    // List of menus to add
		$r=0;
		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=bank',
			'type'=>'left',
			'titre'=>'LeftMenuBankStatement',
			'mainmenu'=>'bank',
			'leftmenu'=>'bankstatement',
			'url'=>'/bankstatement/bankstatement_card.php?action=create',
			'langs'=>'bankstatement@bankstatement',
			'position'=>100,
			'enabled'=>'$conf->bankstatement->enabled',
			'perms'=>'$user->rights->bankstatement->read',
			'target'=>'',
			'user'=>0
		);

		// Exports
	}

	/**
	 * Function called when module is enabled.
	 * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 * It also creates data directories
	 *
	 * @param      string  $options    Options when enabling module ('', 'noboxes')
	 * @return     int                 1 if OK, 0 if KO
	 */
	public function init($options='')
	{
		$result=$this->_load_tables('/bankstatement/sql/');
		if ($result < 0) return -1; // Do not activate module if not allowed errors found on module SQL queries (the _load_table run sql with run_sql with error allowed parameter to 'default')

		// Create extrafields
		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 * Remove from database constants, boxes and permissions from Dolibarr database.
	 * Data directories are not deleted
	 *
	 * @param      string  $options    Options when enabling module ('', 'noboxes')
	 * @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}
}
