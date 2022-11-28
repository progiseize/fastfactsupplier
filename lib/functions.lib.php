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

/********************************************/
/*                                          */
/********************************************/
function ffsAdminPrepareHead()
{
    global $langs, $conf, $user;

    $langs->load("fastfactsupplier@fastfactsupplier");

    $h = 0;
    $head = array();

    
    if($user->rights->fastfactsupplier->saisir):
        $head[$h][0] = dol_buildpath("/fastfactsupplier/index.php", 1);
        $head[$h][1] = $langs->trans("ffs_tabtitle");
        $head[$h][2] = 'saisir';
        $h++;
    endif;

    if($user->rights->fastfactsupplier->configurer):
    
        $head[$h][0] = dol_buildpath("/fastfactsupplier/admin/setup.php", 1);
        $head[$h][1] = $langs->trans("Configuration");
        $head[$h][2] = 'setup';
        $h++;
        
        if($langs->shortlang == 'fr'):
            $head[$h][0] = dol_buildpath("/fastfactsupplier/admin/insert.php", 1);
            $head[$h][1] = $langs->trans("ffs_cc_insert");
            $head[$h][2] = 'insert';
            $h++;
        endif;

        $head[$h][0] = dol_buildpath("/fastfactsupplier/admin/doc.php", 1);
        $head[$h][1] = $langs->trans("Documentation");
        $head[$h][2] = 'doc';
        $h++;
    endif;

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'fastfactsupplier');

    return $head;
}

function file_upload_max_size() {
  static $max_size = -1;
  if ($max_size < 0): $post_max_size = parse_size(ini_get('post_max_size')); if ($post_max_size > 0): $max_size = $post_max_size; endif; 
  $upload_max = parse_size(ini_get('upload_max_filesize')); if ($upload_max > 0 && $upload_max < $max_size): $max_size = $upload_max; endif; endif;return $max_size;
}

function parse_size($size) {
  $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);$size = preg_replace('/[^0-9\.]/', '', $size);
  if ($unit): return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
  else: return round($size); endif;
}

function ffs_select_prodserv($tab_prodserv,$numero_ligne,$value = '',$input_errors){

    global $db, $conf;

    $field = 'infofact-prodserv-'.$numero_ligne;
    $class_error = is_fielderror($field,$input_errors);

    $select = '<select class="flat minwidth300 pdx fact-line" data-addclass="'.$class_error.'" name="'.$field.'" id="infofact-prodserv-'.$numero_ligne.'">';
    $select .= '<option></option>';
    
    if($conf->global->SRFF_USESERVERLIST):

        foreach ($tab_prodserv as $key => $prodserv):
             $select .= '<option value="'.$key.'"';
             if(!empty($value) && $value == $key): $select .= " selected"; endif;
             $select .= '>'.$prodserv.'</option>';
        endforeach;

    else:
        foreach ($tab_prodserv as $prod):
            $select .= '<option value="'.$prod.'"';
            if(!empty($value) && $value == $prod): $select .= " selected"; endif;
            $select .= '>'.$prod.'</option>';
        endforeach;
    endif;

    $select .= '</select>';

    return $select;
}

function ffs_select_tva($tab_tva,$numero_ligne,$value = 'default'){

    global $db, $conf;

    $select = '<select name="infofact-tva-'.$numero_ligne.'" id="infofact-tva-'.$numero_ligne.'" class="calc-tva minwidth100" data-linenum="'.$numero_ligne.'">';

    foreach ($tab_tva as $taux_tva):
        $select .= '<option value="'.$taux_tva.'" ';

        // VERIFIER LE TAUX DE TVA PAR DEFAUT ET SI IL Y A DEJA UNE VALEUR RENSEIGNEE
        if($value == 'default' && $taux_tva == $conf->global->SRFF_DEFAULT_TVA): $select .= 'selected';
        else: if($taux_tva == $value): $select .= 'selected'; endif;
        endif;   

        $select .= '>';
        $select .= $taux_tva.'%';
        $select .= '</option>';
    endforeach;

    $select .= '</select>';

    return $select;                               
}

function is_fielderror($field,$tab_errors){
    $class_error = '';
    if(in_array($field, $tab_errors)): $class_error = 'ffs-fielderror';endif;
    return $class_error;
}

function ffs_getTxTva($country = '1'){

    global $db;

    $tab_tva = array();

    $sql = "SELECT taux FROM ".MAIN_DB_PREFIX."c_tva WHERE fk_pays = '".$country."' AND active = '1' ";
    $results_taux_tva = $db->query($sql);

    if ($results_taux_tva): $num = $db->num_rows($results_taux_tva); $i = 0;
        while ($i < $num): $obj = $db->fetch_object($results_taux_tva);
            if ($obj): array_push($tab_tva, $obj->taux); endif; 
            $i++; 
        endwhile;
    endif;

    return $tab_tva;
}

function ffs_getListProdServ($tab_cats){

    global $db;

    $tab_prodserv = array();

    $sql = "SELECT rowid, label, tva_tx FROM ".MAIN_DB_PREFIX."product as a";
    $sql .=" INNER JOIN ".MAIN_DB_PREFIX."categorie_product as b";
    $sql .=" ON a.rowid = b.fk_product WHERE";

    $nbcats = 0;
    foreach($tab_cats as $cat_id): $nbcats++;
        if($nbcats > 1): $sql .= " OR"; endif;
        $sql .=" b.fk_categorie = '".$cat_id."'";
    endforeach;
    
    $sql .=" ORDER BY label";
    $results_prodserv = $db->query($sql);

    if($results_prodserv): $count_prods = $db->num_rows($result_prods); $i = 0;
        while ($i < $count_prods): $prodserv = $db->fetch_object($result_prods);
            if($prodserv): $tab_prodserv[$prodserv->rowid] = $prodserv->label; endif;
            $i++;
        endwhile;
    endif;

    return $tab_prodserv;
}

function ffs_getListFourn($selected = '',$next_code_fournisseur){

    global $db;

    $options = '';

    $sql = "SELECT rowid,nom,code_fournisseur FROM ".MAIN_DB_PREFIX."societe WHERE fournisseur = '1'";
    $fourns = $db->query($sql);
    $list_fourns = array();
    while($f = $db->fetch_object($fourns)):
        $options .= '<option ';
        if($selected == $f->nom): $options .= 'selected="selected" '; endif;
        $options .= 'value="'.$f->rowid.'" data-fournid="'.$f->rowid.'" data-codefourn="'.$f->code_fournisseur.'" >'.$f->nom.'</option>';
        array_push($list_fourns, $f->nom);
    endwhile;
    if(!empty($selected) && !in_array($selected, $list_fourns)):
        $options .= '<option selected="selected" data-codefourn="'.$next_code_fournisseur.'" value="'.$selected.'">'.$selected.'</option>';
    endif;

    return $options;
}

?>