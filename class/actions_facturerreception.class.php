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
		global $user,$conf,$langs,$db;
		
		if ($parameters['currentcontext'] == 'ordersuppliercard' && ! empty($conf->fournisseur->enabled) && $object->statut >= 2 && $action=='billedreception')  // 2 means accepted
		{
			if ($user->rights->fournisseur->facture->creer)
			{
				
				$datereception = GETPOST('datereception');
				
				if(!empty($datereception)) {
					$resultset = $db->query("SELECT fk_commandefourndet,fk_product,SUM(qty) as qty
					FROM ".MAIN_DB_PREFIX."commande_fournisseur_dispatch 
					WHERE fk_commande=".$object->id."
					AND datec LIKE '".date('Y-m-d H', strtotime($datereception))."%'
					GROUP BY fk_commandefourndet,fk_product
					"
					);
					
					$Tab = array();
					while($obj = $db->fetch_object($resultset)) {
						$obj->line = $this->getGoodLine($object, $obj->fk_commandefourndet, $obj->fk_product);
						
						$Tab[] = $obj;
					}
				
					$this->createFacture($object,$Tab);
					
				}
				
				
			}
			
		}
	}

	function createFacture(&$object, &$TLine) {
		global $user,$conf,$langs,$db;
		
		dol_include_once('/fourn/class/fournisseur.facture.class.php');
			
		$facture = new FactureFournisseur($db);	
		
		$facture->origin = $object->element;
		$facture->origin_id = $object->id;
		
		$facture->ref           = '';
		$facture->ref_supplier = '';
		//$facture->ref_supplier  = $object->ref_supplier;
        $facture->socid         = $object->socid;
		$facture->libelle         = $object->libelle;
        
        $object->date          = time();
        
        $facture->note_public   = $object->note_public;
        $facture->note_private   = $object->note_private;
        $facture->cond_reglement_id   = $object->cond_reglement_id;
        $facture->fk_account   = $object->fk_account;
        $facture->fk_project   = empty($object->fk_project) ? null : $object->fk_project;
        $facture->fk_incoterms   = $object->fk_incoterms;
        $facture->location_incoterms   = $object->location_incoterms;
		$facture->ref_supplier = time();
		$facture->date_echeance = $facture->calculate_date_lim_reglement();
		
		foreach($TLine as &$row) {
			
			$line = $row->line;
			$line->qty = $row->qty;
			$line->id= 0;
			
			$facture->lines[] = $line;
			
			
		}
		
		$res = $facture->create($user);
		
		if($res>0) {

			header('location:'.dol_buildpath('/fourn/facture/card.php?action=editref_supplier&id='.$res,1));
		
			exit;
			
		}
		else {
			//var_dump($res, $facture);
			setEventMessage("ImpossibleToCreateInvoice","errors");	
		}
		
		
	}

	function getGoodLine(&$object, $fk_commandefourndet, $fk_product) {
		
		if(!empty($object->lines)) {
			
			foreach($object->lines as &$line) {
				
				if($fk_commandefourndet>0 && $line->id == $fk_commandefourndet) return $line;
				
				if($fk_commandefourndet==0 && $line->fk_product == $fk_product) return $line;
				
			}
			
		}

		
	}

	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $user,$conf,$langs,$db;
		
		if ($parameters['currentcontext'] == 'ordersuppliercard' && ! empty($conf->fournisseur->enabled) && $object->statut >= 2)  // 2 means accepted
		{
			if ($user->rights->fournisseur->facture->creer)
			{
				$langs->load('facturerreception@facturerreception');
				
				$resultset = $db->query("SELECT DATE_FORMAT(datec,'%Y-%m-%d %H:%i:%s') as 'date', datec as 'datem', SUM(qty) as 'nb'
				FROM ".MAIN_DB_PREFIX."commande_fournisseur_dispatch 
				WHERE fk_commande=".$object->id
				." GROUP BY rowid ");

				$Tab = array();
				while($obj = $db->fetch_object($resultset)) {
					$Tab[$obj->date] = dol_print_date(strtotime($obj->datem), 'dayhour');
				}
				
				if(empty($Tab)) return 0;
				
				echo '<form name="facturerreception" action="?id=&action=billedreception" style="display:inline;">';
				echo '<input type="hidden" name="id" value="'.$object->id.'" />';
				echo '<input type="hidden" name="action" value="billedreception" />';
				echo '<select name="datereception" >';
					echo '<option value=""> </option>';
				
				foreach ($Tab as $k=>$v) {
					
					echo '<option value="'.$k.'">'.$v.'</option>';
					
				}
				echo '</select>';
				
				echo '<input type="submit" class="butAction" value="'.$langs->trans('BillRecep').'" />';
				
				echo '</form>';
				
				?>
				<script type="text/javascript">
				
					$(document).ready(function() {
					
						$("form[name=facturerreception]").appendTo("div.tabsAction");
							
					});
					
				</script>
				<?php
				
			}
		}
	}
	
	
	
}
