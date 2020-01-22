<?php

/* Copyright (C) 2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2020 ATM Consulting       <support@atm-consulting.fr>
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

// Load Dolibarr environment
$mainIncludePath = '../../main.inc.php';
$MAX_BACKTRACK=5; // max depth for finding 'main.inc.php' in parent directories
for ($resInclude = 0, $depth = 0; !$resInclude && $depth < $MAX_BACKTRACK; $depth++) {
	$resInclude = @include $mainIncludePath;
	$mainIncludePath = '../' . $mainIncludePath;
}
if (!$resInclude) die ('Unable to include main.inc.php');

dol_include_once('/compta/paiement/cheque/class/remisecheque.class.php');
dol_include_once('/bankstatement/class/transactioncompare.class.php');

// compte bancaire
require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
// tiers
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
// facture
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
// facture fournisseur
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
// devis
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
// commande
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
// produit
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

global $db;


/**
 * Class SampleGen
 */
class SampleGen
{
	private $db;
	function __construct($db)
	{
		$this->db = $db;
	}
	function createSampleScenario()
	{
		/*
		TODO:
		 * crée une entité "robots" ; utilise uniquement cette entité
		 * crée un compte bancaire
		 *
		 * crée deux produits (P01 = "Robot ménager 3 fonctions" 375€ = 450TTC, P02 = "Robot ménager 4 fonctions" 425€ = 510TTC)
		 *
		 * crée un tiers client ("PassionPropreté")
		 * crée deux factures pour ce client (une pour 10×P01 = 5100, une pour 5×P02 = 2250) et les valide
		 * crée un règlement partiel (550) pour cette facture (llx_paiement + llx_paiement_facture) et une écriture bancaire correspondante (llx_bank)
		 *
		 * Partie fournisseur :
		 * crée un produit (M01 = "moteur mini-robot")
		 * crée un tiers fournisseur ("RoboParts INC")
		 * crée un prix d’achat (fournisseur) pour le produit M01 (50€ HT = 60 TTC)
		 * crée une facture fournisseur avec ce tiers, valide la facture
		 * crée un règlement partiel (300) pour cette facture
		 *
		 * génère deux relevés CSV avec 4 lignes (réparties sur 2 fichiers pour simuler le passage d’un mois à l’autre) :
		 *    - un "crédit" correspondant au règlement partiel déjà dans Dolibarr → pour vérifier que le rapprochement auto fonctionne
		 *    - un "débit" correspondant au règlement partiel déjà dans Dolibarr → idem
		 *    - un "crédit" qui règle tout le reste à payer des 2 factures client → pour vérifier que la création d’écritures fonctionne
		 *    - un "débit" qui règle le reste à payer de la facture fournisseur → idem
		 */
	}
}

$sampleGen = new SampleGen($db);
$sampleGen->createSampleScenario();
