<?php
// Load Dolibarr environment
$mainIncludePath = '../../main.inc.php';
$MAX_BACKTRACK=5; // max depth for finding 'main.inc.php' in parent directories
for ($resInclude = 0, $depth = 0; !$resInclude && $depth < $MAX_BACKTRACK; $depth++) {
	$resInclude = @include $mainIncludePath;
	$mainIncludePath = '../' . $mainIncludePath;
}
if (!$resInclude) die ('Unable to include main.inc.php');

$get = GETPOST('get', 'aZ09');
$set = GETPOST('set', 'aZ09');
if ($get !== '' && $set !== '') {
	failureResponse("AjaxQueryCannotBeGetAndSet");
} elseif ($get) {
	switch ($get) {
		case 'pieceList':
			print _get_pieceList(
				GETPOST('i'),
				GETPOST('fk_soc'),
				GETPOST('type')
			);
			exit;
	}
} elseif ($set) {
	switch ($set) {
		case 'const':
			_set_const(
				GETPOST('name', 'aZ09'),
				GETPOST('value', 'alpha')
			);
			exit;
		case '':
	}
}

/**
 * Prints a JSON failure response with a reason and exits.
 * @param $reason
 */
function failureResponse($reason = '') {
	echo json_encode(array('response' => 'failure', 'reason' => $reason));
	exit;
}

/**
 *
 */
function successResponse() {
	echo json_encode(array('response' => 'success'));
	exit;
}

/**
 * @param $i
 * @param $fk_soc
 * @param $type
 * @return string
 */
function _get_pieceList($i, $fk_soc, $type) {
	global $db, $langs, $conf;
	dol_include_once('/compta/facture/class/facture.class.php');
	dol_include_once('/societe/class/societe.class.php');
	dol_include_once('/fourn/class/fournisseur.facture.class.php');
	dol_include_once('/compta/sociales/class/chargesociales.class.php');
	
	$langs->load('compta');
	
	$r='';
	
	if($type == 'facture') {
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture 
				WHERE fk_statut IN (";
		if(!empty($conf->global->BANKSTATEMENT_ALLOW_DRAFT_INVOICE)) $sql.= "0,";
		$sql.= "1,3) AND fk_soc=".$fk_soc." 
		AND paye = 0
		ORDER BY datef";
				
		$res = $db->query($sql);
		
		while($obj = $db->fetch_object($res)) {
			
			$f=new Facture($db);
			$f->fetch($obj->rowid);
			
			$s = new Societe($db);
			$s->fetch($f->socid);
			
			$r.='<div style="margin:2px 0;"><span style="width:400px;display:inline-block;">'
				.$f->getNomUrl(1).' ('.date('d/m/Y', $f->date).') '.$s->getNomUrl(1, '', 12).' <strong>'.price($f->total_ttc).'</strong></span>'
				.'<input type="hidden" name="price_TLine[piece]['.$i.'][facture]['.$f->id.']" value="'.price2num($f->total_ttc).'" />'
				.img_picto($langs->trans('AddRemind'),'rightarrow.png', 'id="TLine[piece]['.$i.'][facture]['.$f->id.']" class="auto_price"')
				.'<input type="text" rel="priceToPaiment" value="" name="TLine[piece]['.$i.'][facture]['.$f->id.']" size="6" class="flat" /></div>';
			
			
		}			
		
	}
	else if($type == 'fournfacture') {
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture_fourn 
				WHERE fk_statut IN (";
		if(!empty($conf->global->BANKSTATEMENT_ALLOW_DRAFT_INVOICE)) $sql.= "0,";
		$sql.= "1) AND fk_soc=".$fk_soc." 
		AND paye = 0
		ORDER BY datef";
				
		$res = $db->query($sql);
		
		while($obj = $db->fetch_object($res)) {
			
			$f=new FactureFournisseur($db);
			$f->fetch($obj->rowid);
			
			$s = new Societe($db);
			$s->fetch($f->socid);
			
			$r.='<div style="margin:2px 0;"><span style="width:400px;display:inline-block;">'
				.$f->getNomUrl(1).' ('.date('d/m/Y', $f->date).') '.$s->getNomUrl(1, '', 17).' <strong>'.price($f->total_ttc).'</strong></span>'
				.'<input type="hidden" name="price_TLine[piece]['.$i.'][fournfacture]['.$f->id.']" value="'.price2num($f->total_ttc).'" />'
				.img_picto($langs->trans('AddRemind'),'rightarrow.png', 'id="TLine[piece]['.$i.'][fournfacture]['.$f->id.']" class="auto_price"')
				.'<input type="text" rel="priceToPaiment" value="" name="TLine[piece]['.$i.'][fournfacture]['.$f->id.']" size="6" class="flat" /></div>';
			
			
		}		
	}
	
	else if($type == 'charge') {
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."chargesociales 
				WHERE paye=0 AND date_ech<=NOW() ORDER BY date_ech";
				
		$res = $db->query($sql);
		
		while($obj = $db->fetch_object($res)) {
			
			$f=new ChargeSociales($db);
			$f->fetch($obj->rowid);
			
			$r.='<div><span style="width:200px;display:inline-block;">'. $f->getNomUrl(1).' '.$f->lib.' '.price($f->amount) .'</span> <input type="text" value="" name="TLine[piece]['.$i.'][charge]['.$f->id.']" size="5" class="flat" /></div>';
			
			
		}		
	}
	
	return $r;
}

/**
 * Set or delete (if $value is empty) a configuration row in llx_const
 * @param string $name
 * @param string $value
 */
function _set_const($name, $value) {
	global $conf, $user, $db;
	// check that the user is admin
	if (!$user->admin) {
		// security: only admin can set const
		failureResponse('Denied:AdminOnly');
	}
	if (!preg_match('/^BANKSTATEMENT_/', $name)) {
		// security: limit what a successful XSS injection could do
		failureResponse('Denied:TryingToSetOtherModuleConst');
	}

	if ($value !== '') {
		dolibarr_set_const($db, $name, $value, 'chaine', 0, '', $conf->entity);
	} else {
		dolibarr_del_const($db, $name, $conf->entity);
	}
	successResponse();
}
