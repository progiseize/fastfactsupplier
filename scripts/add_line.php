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


/*******************************************************************
* FONCTIONS
********************************************************************/
dol_include_once('./fastfactsupplier/lib/functions.lib.php');

// Protection if external user
if ($user->societe_id > 0): accessforbidden(); endif;

// Configuration module
$use_server_list = $conf->global->SRFF_USESERVERLIST;
$ncats = json_decode($conf->global->SRFF_CATS);
$prodservs = explode(',',$conf->global->SRFF_SERVERLIST);
$gotoreg = $conf->global->SRFF_GOTOREG;
$show_extrafields_facture = $conf->global->SRFF_SHOWEXTRAFACT;
$show_extrafields_factureline = $conf->global->SRFF_SHOWEXTRAFACTLINE;

$form = new Form($db);

// On recupère la liste des services
if($use_server_list):

    $tab_prodserv = array();

    $sql = "SELECT rowid, label, tva_tx FROM ".MAIN_DB_PREFIX."product as a";
    $sql .=" INNER JOIN ".MAIN_DB_PREFIX."categorie_product as b";
    $sql .=" ON a.rowid = b.fk_product WHERE";

    $nbcats = 0;
    foreach($ncats as $cc): $nbcats++;
        if($nbcats > 1): $sql .= " OR"; endif;
        $sql .=" b.fk_categorie = '".$cc."'";
    endforeach;
    
    $sql .=" ORDER BY label";
    $results_prodserv = $db->query($sql);

    if($results_prodserv): $count_prods = $db->num_rows($result_prods); $i = 0;
        while ($i < $count_prods): $prodserv = $db->fetch_object($result_prods);
            if($prodserv): $tab_prodserv[$prodserv->rowid] = $prodserv->label; endif;
            $i++;
        endwhile;
    endif;
else : $tab_prodserv = $prodservs;
endif;

// On recupere les taux de TVA
$form->load_cache_vatrates("'".$mysoc->country_code."'");
$vat_rates = array();
foreach($form->cache_vatrates as $vat):
    $vat_rates[$vat['txtva']] = $vat['label'];
endforeach;

// Variables
$input_errors = array();

// POUR <= V10, ON INSTANCIE LES EXTRAFIELDS
$version = explode('.', DOL_VERSION);
if($version[0] <= 10): $extrafields = new ExtraFields($db); endif;

if($show_extrafields_factureline): $extralabels_factureligne = $extrafields->fetch_name_optionals_label('facture_fourn_det'); endif; // LIGNES FACTURE
$extraf_visibletab = array('1','3'); 
/***************************************************************************/

include_once('../lib/functions.lib.php'); ?>

<tr id="linefact-<?php echo GETPOST('viewnumber') ?>" class="oddeven pgsz-optiontable-tr linefact">
    <td class="pgsz-optiontable-field">
        <input type="hidden" name="infofact-saisie-<?php echo GETPOST('viewnumber'); ?>" id="infofact-saisie-<?php echo GETPOST('viewnumber'); ?>" value="" >
        <?php echo ffs_select_prodserv($tab_prodserv,GETPOST('viewnumber'),'',$input_errors); ?>
    </td>
    <?php if($show_extrafields_factureline && !empty($extralabels_factureligne)):  ?>
        <?php foreach($extralabels_factureligne as $key => $label): 
            if(in_array($extrafields->attributes['facture_fourn_det']['list'][$key], $extraf_visibletab) && $extrafields->attributes['facture_fourn_det']['enabled'][$key]): ?>
            <td class="left">
                <?php 
                    $class_field = 'ffs-cfligne minwidth200';
                    if($key == $conf->global->SRFF_EXTRAFACTLINE_PROJECT): $class_field .= ' ffs-lineproject'; endif;
                    if(in_array($extrafields->attribute_type[$key], array('select','sellist'))): $class_field .= ' ffs-slct'; endif;
                ?>
                <?php echo $extrafields->showInputField($key,$value_extrafield,'','-'.GETPOST('viewnumber'),'',$class_field,$facture->id,'facture_fourn_det'); ?>
            </td>
        <?php endif; endforeach; ?>
    <?php endif; ?>
    <td class="pgsz-optiontable-field <?php if($conf->global->SRFF_AMOUNT_MODE == 'ttc'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantht-<?php echo GETPOST('viewnumber'); ?>" id="infofact-montantht-<?php echo GETPOST('viewnumber'); ?>" class="calc-amount" value="" data-mode="ht" data-linenum="<?php echo GETPOST('viewnumber'); ?>" /></td>
    <td class="pgsz-optiontable-field <?php if($conf->global->SRFF_AMOUNT_MODE == 'ht'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantttc-<?php echo GETPOST('viewnumber'); ?>" id="infofact-montantttc-<?php echo GETPOST('viewnumber'); ?>" class="calc-amount" value="" data-mode="ttc" data-linenum="<?php echo GETPOST('viewnumber'); ?>" /></td> 
    <td class="pgsz-optiontable-field right">
        <?php if(!empty($vat_rates)): echo $form->selectarray('infofact-tva-'.GETPOST('viewnumber'),$vat_rates,$conf->global->SRFF_DEFAULT_TVA,0,0,0,'data-linenum="'.GETPOST('viewnumber').'"',0,0,0,'','minwidth100 calc-tva');
        else: echo $langs->transnoentities('ffs_noVAT');
        endif; ?>
    </td>

    
    
</tr>