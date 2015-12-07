<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_facturerreception.class.php
 * \ingroup facturerreception
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class ActionsfacturerReception
 */
class ActionsfacturerReception
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		
	}

	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $user,$conf;
		
		if (! empty($conf->fournisseur->enabled) && $object->statut >= 2)  // 2 means accepted
		{
			if ($user->rights->fournisseur->facture->creer)
			{
				echo '<div class="tabsAction"><a class="butAction" href="'.dol_buildpath('/fourn/facture/card.php?action=create&onreception=1&origin='.$object->element.'&originid='.$object->id.'&socid='.$object->socid, 1).'" >Facturer la réception de stock</a></div>';
			}
		}
	}

	function fetchOriginFourn($parameters, &$object, &$action, $hookmanager)
	{
		global $db,$conf;
		
		$debug = isset($_REQUEST['DEBUG']) ? true : false;
		
		if ($object->element !== 'order_supplier') return 0;
		
		$products_dispatched = array();
		
		// List of already dispatching
		$sql = "SELECT p.ref, p.label,";
		$sql.= " e.rowid as warehouse_id, e.label as entrepot,";
		$sql.= " cfd.fk_product, cfd.qty, cfd.rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."product as p,";
		$sql.= " ".MAIN_DB_PREFIX."commande_fournisseur_dispatch as cfd";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON cfd.fk_entrepot = e.rowid";
		$sql.= " WHERE cfd.fk_commande = ".$object->id;
		$sql.= " AND cfd.fk_product = p.rowid";
		$sql.= " ORDER BY cfd.rowid ASC";

		if ($debug) print 'Requête SQL pour récup les qty ventilées => '.$sql.'<br />';

		$resql = $db->query($sql);
		if ($resql)
		{
			while ($obj = $db->fetch_object($resql))
			{
				$products_dispatched[$obj->fk_product] = $obj;
			}
		}
		
		if ($debug) { print 'count + var_dump de $product_dispatched = '.count($products_dispatched).'<br />'; var_dump($products_dispatched); }
		
		$total_ht = $total_tva = $total_ttc = $total_localtax1 = $total_localtax2 = 0;
		if (count($products_dispatched) > 0)
		{
			dol_include_once('/core/lib/price.lib.php');
			
			if ($debug) print "total_ht = $total_ht, total_tva = $total_tva, total_ttc = $total_ttc, total_localtax1 = $total_localtax1, total_localtax2 = $total_localtax2<br />";
			
			foreach ($object->lines as $key => &$TValue)
			{
				if (isset($products_dispatched[$TValue->fk_product]))
				{
					$this->_calcTotaux($object, $TValue, $products_dispatched[$TValue->fk_product], $total_ht, $total_tva, $total_ttc, $total_localtax1, $total_localtax2, $debug);
				}
				else
				{
					//Accepte les lignes libres ou non
					if (empty($TValue->fk_product) && $conf->global->FACTURERRECEPTION_ALLOW_FREE_LINE_SERVICE && $TValue->product_type == 1) $this->_calcTotaux($object, $TValue, $total_ht, $total_tva, $total_ttc, $total_localtax1, $total_localtax2, $debug);
					elseif (empty($TValue->fk_product) && $conf->global->FACTURERRECEPTION_ALLOW_FREE_LINE_PRODUCT && $TValue->product_type == 0) $this->_calcTotaux($object, $TValue, $total_ht, $total_tva, $total_ttc, $total_localtax1, $total_localtax2, $debug);
					else unset($object->lines[$key]);
				}
			}
			
			if ($debug) print "total_ht = $total_ht, total_tva = $total_tva, total_ttc = $total_ttc, total_localtax1 = $total_localtax1, total_localtax2 = $total_localtax2<br />";
			
			$object->total_ht = $total_ht;
			$object->total_tva = $total_tva;
			$object->total_ttc = $total_ttc;
			$object->total_localtax1 = $total_localtax1;
			$object->total_localtax2 = $total_localtax2;
		}

	}

	function _calcTotaux(&$object, &$TValue, &$line_dispatched, &$total_ht, &$total_tva, &$total_ttc, &$total_localtax1, &$total_localtax2, $debug)
	{
		if ($debug) print 'fk_product = '.$TValue->fk_product.' :: qty cmd = '.$TValue->qty.' :: qty ventilés = '.$line_dispatched->qty.'<br />';
		
		$TValue->qty = $line_dispatched->qty; // Ceci est important de le faire, j'update la qty de la ligne courante qui sera repris sur l'affichage de Dolibarr
		$tabprice = calcul_price_total($TValue->qty, $TValue->subprice, $TValue->remise_percent, $TValue->tva_tx, $TValue->localtax1_tx, $TValue->localtax2_tx, 0, 'HT', $TValue->info_bits, $TValue->product_type, $object->thirdparty);
		
        $total_ht  += $tabprice[0];
        $total_tva += $tabprice[1];
        $total_ttc += $tabprice[2];
        $total_localtax1 += $tabprice[9];
        $total_localtax2 += $tabprice[10];
	}
	
}