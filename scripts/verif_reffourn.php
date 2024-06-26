<?php 
/* 
 * Copyright (C) 2022 ProgiSeize <contact@progiseize.fr>
 *
 * This program and files/directory inner it is free software: you can 
 * redistribute it and/or modify it under the terms of the 
 * GNU Affero General Public License (AGPL) as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AGPL for more details.
 *
 * You should have received a copy of the GNU AGPL
 * along with this program.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
 */
if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1'); // Disables token renewal

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';
if (! $res && file_exists("../../../main.inc.php")) $res=@include '../../../main.inc.php';

/*$facture_reffourn = $_GET['facture_reffourn'];
$facture_fournid = $_GET['facture_fournid'];

$sql_checkref = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture_fourn WHERE ref_supplier = '$facture_reffourn' AND fk_soc = '$facture_fournid'";
$result_checkref = $db->query($sql_checkref);
$count_checkref = $db->num_rows($result_checkref);

//if($count_checkref > 0): echo 'exist'; endif;
if($count_checkref > 0): echo '1'; else: echo '0'; endif;*/

dol_include_once('./fastfactsupplier/class/fastfactsupplier.class.php');

$fastfactsupplier = new FastFactSupplier($db);
$check = $fastfactsupplier->check_exist_ref_supplier($_GET['facture_reffourn'],$_GET['facture_fournid']);
if($check): echo '1'; else: echo '0'; endif;