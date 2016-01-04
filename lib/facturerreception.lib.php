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
 *	\file		lib/facturerreception.lib.php
 *	\ingroup	facturerreception
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function facturerreceptionAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("facturerreception@facturerreception");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/facturerreception/admin/facturerreception_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/facturerreception/admin/facturerreception_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@facturerreception:/facturerreception/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@facturerreception:/facturerreception/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'facturerreception');

    return $head;
}

function _getProductDispatched(&$db, &$object, $debug)
{
	// List of already dispatching
	$sql = "SELECT cfd.fk_product, SUM(cfd.qty) as qty, cfd.fk_commandefourndet";
	$sql.= " FROM ".MAIN_DB_PREFIX."product as p,";
	$sql.= " ".MAIN_DB_PREFIX."commande_fournisseur_dispatch as cfd";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON cfd.fk_entrepot = e.rowid";
	$sql.= " WHERE cfd.fk_commande = ".$object->id;
	$sql.= " AND cfd.fk_product = p.rowid GROUP BY cfd.fk_commandefourndet";
	$sql.= " ORDER BY cfd.rowid ASC";

	if ($debug) print 'Requête SQL pour récup les qty ventilées => '.$sql.'<br />';

	$resql = $db->query($sql);
	if ($resql)
	{
		while ($obj = $db->fetch_object($resql))
		{
			$products_dispatched[$obj->fk_product][$obj->fk_commandefourndet] += $obj->qty;
		}
	}
	
	return $products_dispatched;
}

function _calcTotaux(&$object, &$line, &$qty_dispatched, &$total_ht, &$total_tva, &$total_ttc, &$total_localtax1, &$total_localtax2, $debug)
{
	if ($debug) print 'fk_product = '.$line->fk_product.' :: qty cmd = '.$line->qty.' :: qty ventilés = '.$qty_dispatched.'<br />';
	
	$line->qty = $qty_dispatched; // Ceci est important de le faire, j'update la qty de la ligne courante qui sera repris sur l'affichage de Dolibarr
	$tabprice = calcul_price_total($line->qty, $line->subprice, $line->remise_percent, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 0, 'HT', $line->info_bits, $line->product_type, $object->thirdparty, array());
	
    $total_ht  += $tabprice[0];
    $total_tva += $tabprice[1];
    $total_ttc += $tabprice[2];
    $total_localtax1 += $tabprice[9];
    $total_localtax2 += $tabprice[10];
}

