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


$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';
if (! $res && file_exists("../../../main.inc.php")) $res=@include '../../../main.inc.php';

dol_include_once('/module/class/skeleton_class.class.php');

// ON RECUPERE LA RECHERCHE
$term = $_GET['term'];

// ON CREE ET EXECUTE LA REQUETE
$sql = "SELECT rowid,nom,code_fournisseur FROM ".MAIN_DB_PREFIX."societe WHERE fournisseur = '1' AND nom LIKE '%$term%'  ";
$fourns = $db->query($sql);

// ON RENVOIE LES RESULTATS
$nb = $db->num_rows($fourns); $i = 0;
$res = array();
while ($i < $nb): $obj = $db->fetch_object($fourns);array_push($res, $obj);$i++;
endwhile;
echo json_encode($res);

?>