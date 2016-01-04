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
	
	function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		$onreception = GETPOST('onreception', 'int');
		if (!empty($onreception)) echo '<input type="hidden" name="onreception" value="1" />';
	}

	function fetchOriginFourn($parameters, &$object, &$action, $hookmanager)
	{
		global $db,$conf;
		
		$debug = isset($_REQUEST['DEBUG']) ? true : false;
		$onreception = GETPOST('onreception', 'int');
		
		if ($object->element !== 'order_supplier' || empty($onreception) || $onreception <= 0) return 0;
		
		dol_include_once('/facturerreception/lib/facturerreception.lib.php');
		
		$products_dispatched = _getProductDispatched($db, $object, $debug);
		
		if ($debug) { print 'count + var_dump de $product_dispatched = '.count($products_dispatched).'<br />'; var_dump($products_dispatched); }
		
		if (count($products_dispatched) > 0)
		{
			dol_include_once('/core/lib/price.lib.php');
			$total_ht = $total_tva = $total_ttc = $total_localtax1 = $total_localtax2 = 0;
			if ($debug) print "total_ht = $total_ht, total_tva = $total_tva, total_ttc = $total_ttc, total_localtax1 = $total_localtax1, total_localtax2 = $total_localtax2<br />";
			
			foreach ($object->lines as $key => &$line)
			{
				if (isset($products_dispatched[$line->fk_product]))
				{
					foreach ($products_dispatched[$line->fk_product] as $fk_commandefourndet => $qty_dispatched)
					{
						if ($line->id == $fk_commandefourndet) _calcTotaux($object, $line, $qty_dispatched, $total_ht, $total_tva, $total_ttc, $total_localtax1, $total_localtax2, $debug);
					}
					
				}
				else
				{
					//Accepte les lignes libres ou non
					if (	
						(empty($line->fk_product) && $conf->global->FACTURERRECEPTION_ALLOW_FREE_LINE_SERVICE && $line->product_type == 1) ||
						(empty($line->fk_product) && $conf->global->FACTURERRECEPTION_ALLOW_FREE_LINE_PRODUCT && $line->product_type == 0)
					) _calcTotaux($object, $line, $total_ht, $total_tva, $total_ttc, $total_localtax1, $total_localtax2, $debug);
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
		else
		{
			foreach ($object->lines as $key => &$line) unset($object->lines[$key]);
			
			$object->total_ht = 0;
			$object->total_tva = 0;
			$object->total_ttc = 0;
			$object->total_localtax1 = 0;
			$object->total_localtax2 = 0;
		}

	}

	
	
}