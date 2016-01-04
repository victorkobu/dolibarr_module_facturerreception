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
 * 	\file		core/triggers/interface_99_modMyodule_facturerReceptiontrigger.class.php
 * 	\ingroup	facturerreception
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMymodule_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class InterfacefacturerReceptiontrigger
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct($db)
    {
        $this->db = &$db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'facturerreception@facturerreception';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') {
            return $langs->trans("Development");
        } elseif ($this->version == 'experimental')

                return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else {
            return $langs->trans("Unknown");
        }
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		Object		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, &$object, &$user, &$langs, &$conf)
    {
        
        if ($action == 'LINEBILL_SUPPLIER_CREATE') {
            dol_syslog(
                "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
            );
			
			if (get_class($object) == 'FactureFournisseur')
			{
				$debug = isset($_REQUEST['DEBUG']) ? true : false;
				$products_dispatched = array();
				if ($object->origin == 'order_supplier' && $object->origin_id > 0)
				{
					$objsrc = new CommandeFournisseur($this->db);
					if ($objsrc->fetch($object->origin_id) > 0)
					{
						dol_include_once('/facturerreception/lib/facturerreception.lib.php');
						$products_dispatched = _getProductDispatched($this->db, $objsrc, $debug);
					}
				}
				
				if (count($products_dispatched) > 0)
				{
					$object->fetch($object->id); // Ré-actualisation des lignes
					foreach ($object->lines as $line) $object->deleteline((!empty($line->id) ? $line->id : $line->rowid), 1); // suppression des lignes existantes
					
					$total_ht = $total_tva = $total_ttc = $total_localtax1 = $total_localtax2 = 0;
					foreach ($objsrc->lines as $key => &$line)
					{
						if (isset($products_dispatched[$line->fk_product]))
						{
							foreach ($products_dispatched[$line->fk_product] as $fk_commandefourndet => $qty)
							{
								if ($line->id == $fk_commandefourndet) $this->addline($object, $line, $qty);
							}
							
							//_calcTotaux($objsrc, $line, $products_dispatched[$line->fk_product], $total_ht, $total_tva, $total_ttc, $total_localtax1, $total_localtax2, $debug);
						}
						else 
						{
							//Accepte les lignes libres ou non
						//	if (empty($line->fk_product) && $conf->global->FACTURERRECEPTION_ALLOW_FREE_LINE_SERVICE && $line->product_type == 1) _calcTotaux($objsrc, $line, $total_ht, $total_tva, $total_ttc, $total_localtax1, $total_localtax2, $debug);
						//	elseif (empty($line->fk_product) && $conf->global->FACTURERRECEPTION_ALLOW_FREE_LINE_PRODUCT && $line->product_type == 0) _calcTotaux($objsrc, $line, $total_ht, $total_tva, $total_ttc, $total_localtax1, $total_localtax2, $debug);
						}
					}	
				}
			} 
			
			
		}

        return 0;
    }

	function addline(&$object, &$line, $qty)
	{
		$desc=($line->desc?$line->desc:$line->libelle);
        $product_type=($line->product_type?$line->product_type:0);

        // Dates
        // TODO mutualiser
        $date_start=$line->date_debut_prevue;
        if ($line->date_debut_reel) $date_start=$line->date_debut_reel;
        if ($line->date_start) $date_start=$line->date_start;
        $date_end=$line->date_fin_prevue;
        if ($line->date_fin_reel) $date_end=$line->date_fin_reel;
        if ($line->date_end) $date_end=$line->date_end;
						
		$object->addline(
            $desc,
            $line->subprice,
            $line->tva_tx,
            $line->localtax1_tx,
            $line->localtax2_tx,
            $qty,
            $line->fk_product,
            $line->remise_percent,
            $date_start,
            $date_end,
            0,
            $line->info_bits,
            'HT',
            $product_type,
            -1,
            1 //$notrigger
        );
	} 
}