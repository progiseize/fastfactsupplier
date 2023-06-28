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

// Protection if external user
if ($user->societe_id > 0): accessforbidden(); endif;

dol_include_once('./fastfactsupplier/class/fastfactsupplier.class.php');
dol_include_once('./fastfactsupplier/lib/functions.lib.php');

include_once('../lib/functions.lib.php');

/*******************************************************************
* VARIABLES
********************************************************************/
$fastfactsupplier = new FastFactSupplier($db);

// POUR <= V10, ON INSTANCIE LES EXTRAFIELDS
$version = explode('.', DOL_VERSION);
if($version[0] <= 10): $extrafields = new ExtraFields($db); endif;

$form = new Form($db);

// On recupère la liste des services
if($fastfactsupplier->params['use_categories_product']): $tab_prodserv = $fastfactsupplier->get_products_services_list($fastfactsupplier->params['cats_to_use']);
else : $tab_prodserv = explode(',',getDolGlobalString('SRFF_SERVERLIST')); endif;

// On recupere les taux de TVA
$form->load_cache_vatrates("'".$mysoc->country_code."'");
$vat_rates = array();
foreach($form->cache_vatrates as $vat):
    $vat_rates[$vat['txtva']] = $vat['label'];
endforeach;

// Extrafields line
if($fastfactsupplier->params['show_extrafields_factureline']): $extralabels_factureligne = $extrafields->fetch_name_optionals_label('facture_fourn_det'); endif; // LIGNES FACTURE
$extrafields_view_tab = array('1','3'); 
$invoice_extrafields_line = array();
if($fastfactsupplier->params['show_extrafields_factureline'] && !empty($extralabels_factureligne)):
    foreach($extralabels_factureligne as $key_extrafield => $label_extrafield):

        // On check l'entité
        if(!in_array($extrafields->attributes['facture_fourn_det']['entityid'][$key_extrafield], array(0,$conf->entity))): continue; endif;

        // On check s'il est activé 
        if(!$extrafields->attributes['facture_fourn_det']['enabled'][$key_extrafield]): continue; endif;
        if(!empty($extrafields->attributes['facture_fourn_det']['enabled'][$key_extrafield])):
            if(!dol_eval($extrafields->attributes['facture_fourn_det']['enabled'][$key_extrafield], 1,0)): continue; endif;
        endif;

        // On check si il est visible
        if(!in_array($extrafields->attributes['facture_fourn_det']['list'][$key_extrafield], $extrafields_view_tab)): continue; endif;

        // On check les perms
        if(!$extrafields->attributes['facture_fourn_det']['perms'][$key_extrafield]): continue; endif;

        // Lang File
        if(!empty($extrafields->attributes['facture_fourn_det']['langfile'][$key_extrafield])):
            $langs->load($extrafields->attributes['facture_fourn_det']['langfile'][$key_extrafield]);
            $extralabels_factureligne[$key_extrafield] = $langs->transnoentities($label_extrafield);
            $extrafields->attributes['facture_fourn_det']['label'][$key_extrafield] = $langs->transnoentities($label_extrafield);
        endif;

        // On l'ajoute au tableau
        array_push($invoice_extrafields_line, $key_extrafield);

    endforeach;    
endif;
/***************************************************************************/

?>

<tr id="linefact-<?php echo GETPOST('viewnumber') ?>" class="dolpgs-tbody linefact">
    <td class="">
        <input type="hidden" name="infofact-saisie-<?php echo GETPOST('viewnumber'); ?>" id="infofact-saisie-<?php echo GETPOST('viewnumber'); ?>" value="" >
        <?php echo $fastfactsupplier->select_prodserv($tab_prodserv,GETPOST('viewnumber'),''); ?>
    </td>
    <?php 

        if($fastfactsupplier->params['show_extrafields_factureline'] && !empty($invoice_extrafields_line)):
            foreach($invoice_extrafields_line as $key_extrafield):

                $value_extrafield = $line['extrafields'][$key_extrafield];
                $type_extrafield = $extrafields->attributes['facture_fourn_det']['type'][$key_extrafield];

                $class_extrafield = 'ffs-cfligne minwidth200'; 
                if($key_extrafield == $fastfactsupplier->params['extra_lineproject']): $class_extrafield .= ' ffs-lineproject'; endif;
                if(in_array($type_extrafield, array('select','sellist'))): $class_extrafield .= ' ffs-slct'; endif;
               
                ?>
                <td class="left pgsz-optiontable-field">
                    <?php echo $extrafields->showInputField($key_extrafield,$value_extrafield,'','-'.GETPOST('viewnumber'),'',trim($class_extrafield),'','facture_fourn_det'); ?>
                </td>

            <?php endforeach;
        endif;  ?>
    <td class="<?php if($fastfactsupplier->params['mode_amount'] == 'ttc'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantht-<?php echo GETPOST('viewnumber'); ?>" id="infofact-montantht-<?php echo GETPOST('viewnumber'); ?>" class="calc-amount" value="" data-mode="ht" data-linenum="<?php echo GETPOST('viewnumber'); ?>" /></td>
    <td class="<?php if($fastfactsupplier->params['mode_amount'] == 'ht'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantttc-<?php echo GETPOST('viewnumber'); ?>" id="infofact-montantttc-<?php echo GETPOST('viewnumber'); ?>" class="calc-amount" value="" data-mode="ttc" data-linenum="<?php echo GETPOST('viewnumber'); ?>" /></td> 
    <td class="right">
        <?php if(!empty($vat_rates)): echo $form->selectarray('infofact-tva-'.GETPOST('viewnumber'),$vat_rates,$fastfactsupplier->params['default_tva'],0,0,0,'data-linenum="'.GETPOST('viewnumber').'"',0,0,0,'','minwidth100 calc-tva');
        else: echo $langs->transnoentities('ffs_noVAT');
        endif; ?>
    </td>

    
    
</tr>