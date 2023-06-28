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
if (! $res && file_exists("../main.inc.php")): $res=@include '../main.inc.php'; endif;
if (! $res && file_exists("../../main.inc.php")): $res=@include '../../main.inc.php'; endif;
if (! $res && file_exists("../../../main.inc.php")): $res=@include '../../../main.inc.php'; endif;

// Protection if external user
if ($user->socid > 0): accessforbidden(); endif;
if(!$user->rights->fastfactsupplier->configurer): accessforbidden(); endif;

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

dol_include_once('./fastfactsupplier/class/fastfactsupplier.class.php');
dol_include_once('./fastfactsupplier/lib/functions.lib.php');

// ON INSTANCIE LES EXTRAFIELDS SI BESOIN
if(!isset($extrafields)): $extrafields = new ExtraFields($db); endif;



/*******************************************************************
* VARIABLES
********************************************************************/
$action = GETPOST('action');

$form = new Form($db);
$fastfactsupplier = new FastFactSupplier($db);

// EXTRAFIELDS
$extralabels_facture = $extrafields->fetch_name_optionals_label('facture_fourn');
$extralabels_factureligne = $extrafields->fetch_name_optionals_label('facture_fourn_det');

// ON RECUPERE LES CATEGORIES PRODUITS
$cate_arbo = $form->select_all_categories(Categorie::TYPE_PRODUCT, '', 'parent', 64, 0, 1);
$cats = $fastfactsupplier->params['cats_to_use'];
$arrayselected=array();
if(!empty($cats)): foreach($cats as $cat): $arrayselected[] = $cat; endforeach; endif;

// ON RECUPERE LES TAUX DE TVA
$form->load_cache_vatrates("'".$mysoc->country_code."'");
$vat_rates = array();
foreach($form->cache_vatrates as $vat):
    $vat_rates[$vat['txtva']] = $vat['label'];
endforeach;

/*******************************************************************
* ACTIONS
********************************************************************/
if ($action == 'setOptions' && GETPOST('token') == $_SESSION['token']):

    $cats = json_encode(GETPOST('srff-cats','alphanohtml'));
    $useServer = GETPOST('srff-useserver','alphanohtml');
    $serverList = GETPOST('srff-serverlist','alphanohtml');
    $gotoreg = GETPOST('srff-gotoreg','alphanohtml');
    $use_customfield_uploadfile = GETPOST('srff-usecustomfield-uploadfile','alphanohtml');
    $defTva = GETPOST('srff-default-tva','int');
    $bkAccount = GETPOST('srff-bank-account','int');
    $modeAMount = GETPOST('srff-amount-mode','alphanohtml');
    $showExtraFields_fact = GETPOST('srff-showextrafact','alphanohtml');
    $showExtraFields_factline = GETPOST('srff-showextrafactline','alphanohtml');            

    $errs = 0;

    // Si l'option en cochée
    if($gotoreg && $gotoreg == 'oui'): dolibarr_set_const($db, "SRFF_GOTOREG",true,'chaine',0,'',$conf->entity);
    else: dolibarr_set_const($db, "SRFF_GOTOREG",false,'chaine',0,'',$conf->entity);
    endif;

    // Si l'option en cochée
    if($use_customfield_uploadfile && $use_customfield_uploadfile == 'oui'): 
        dolibarr_set_const($db, "SRFF_USECUSTOMFIELD_UPLOADFILE",true,'chaine',0,'',$conf->entity);
        if(!array_key_exists('ffs_uploadfile', $extralabels_facture)): $extrafields->addExtraField('ffs_uploadfile',$langs->trans('ffs_options_params_use_extrauploadfilename'),'boolean','50','','facture_fourn',0,0,'0','',1,'','2'); endif;
        
    else: 
        dolibarr_set_const($db, "SRFF_USECUSTOMFIELD_UPLOADFILE",false,'chaine',0,'',$conf->entity);
        if(array_key_exists('ffs_uploadfile', $extralabels_facture)): $extrafields->delete('ffs_uploadfile','facture_fourn'); endif;
    endif;

    // Si l'option en cochée
    if($showExtraFields_fact && $showExtraFields_fact == 'oui'): dolibarr_set_const($db, "SRFF_SHOWEXTRAFACT",true,'chaine',0,'',$conf->entity);
    else: dolibarr_set_const($db, "SRFF_SHOWEXTRAFACT",false,'chaine',0,'',$conf->entity);
    endif;

    // Si l'option en cochée
    if($showExtraFields_factline && $showExtraFields_factline == 'oui'): 
        dolibarr_set_const($db, "SRFF_SHOWEXTRAFACTLINE",true,'chaine',0,'',$conf->entity);
        dolibarr_set_const($db, "SRFF_EXTRAFACTLINE_PROJECT",GETPOST('srff-lineprojectfield'),'chaine',0,'',$conf->entity);
    else: dolibarr_set_const($db, "SRFF_SHOWEXTRAFACTLINE",false,'chaine',0,'',$conf->entity);
    endif;

    // Si l'option en cochée
    if($useServer && $useServer == 'oui'):

        // On verifie que l'identifiant de la categorie est egalement renseignée
        // Si oui, on enregistre les infos en bdd
        if($cats != '""'):

            dolibarr_set_const($db, "SRFF_USESERVERLIST",true,'chaine',0,'',$conf->entity);
            dolibarr_set_const($db, "SRFF_CATS",$cats,'chaine',0,'',$conf->entity);


        // Sinon, on affiche un message warning
        else:
            setEventMessages($langs->trans('ffs_options_error_cats_needed'), null, 'warnings');
            $errs = 1;
        endif;

    else:
        dolibarr_set_const($db, "SRFF_USESERVERLIST",false,'chaine',0,'',$conf->entity);
        dolibarr_set_const($db, "SRFF_CATS",$cats,'chaine',0,'',$conf->entity);
    endif;

    // Todo : Faire une vérification regex sur la chaine de caractères

    dolibarr_set_const($db, "SRFF_SERVERLIST",$serverList,'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "SRFF_DEFAULT_TVA",$defTva,'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "SRFF_BANKACCOUNT",$bkAccount,'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "SRFF_AMOUNT_MODE",$modeAMount,'chaine',0,'',$conf->entity);

    if ($errs == 0):
        setEventMessages($langs->trans('ffs_options_process_ok'), null, 'mesgs');
    endif;

endif;

// On recharge les valeurs
$fastfactsupplier->refresh_params();

/***************************************************
* VIEW
****************************************************/

$array_js = array();
$array_css = array(
    '/fastfactsupplier/css/fastfactsupplier.css',
    '/fastfactsupplier/css/dolpgs.css',
);

llxHeader('',$langs->trans('ffs_options_page_title'),'','','','',$array_js,$array_css,'','fastfactsupplier'); ?>

<div class="dolpgs-main-wrapper fastfact">

    <?php if(in_array('progiseize', $conf->modules)): ?>
        <h1 class="has-before"><?php echo $langs->transnoentities('ffs_page_title'); ?></h1>
    <?php else : ?>
        <table class="centpercent notopnoleftnoright table-fiche-title"><tbody><tr class="titre"><td class="nobordernopadding widthpictotitle valignmiddle col-picto"><span class="fas fa-file-invoice-dollar valignmiddle widthpictotitle pictotitle" style=""></span></td><td class="nobordernopadding valignmiddle col-title"><div class="titre inline-block"><?php echo $langs->transnoentities('ffs_page_title'); ?></div></td></tr></tbody></table>
    <?php endif; ?>
    <?php $head = ffsAdminPrepareHead(); echo dol_get_fiche_head($head, 'setup','FastFactSupplier', 0,'fa-file-invoice-dollar_fas_#fb2a52'); ?>

    
    
    <form enctype="multipart/form-data" action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="POST" id="">

        <input type="hidden" name="action" value="setOptions">
        <input type="hidden" name="token" value="<?php echo newtoken(); ?>">

        <h3 class="dolpgs-table-title"><?php echo $langs->trans('ffs_infosgen'); ?></h3>
        <table class="dolpgs-table fastfact-table" style="border-top:none;">
            <tbody>
                <tr class="dolpgs-thead" >
                    <th colspan="3"><?php echo $langs->trans('ffs_options_cats'); ?></th>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php echo $langs->trans('ffs_options_cats_usedolicats'); ?></td>               
                    <td class="hideonsmartphone"><?php echo $langs->trans('ffs_options_cats_usedolicats_desc'); ?></td>
                    <td class="right"><input type="checkbox" name="srff-useserver" id="srff-useserver" value="oui" <?php if($fastfactsupplier->params['use_categories_product']): ?>checked="checked"<?php endif; ?> /></td>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php echo $langs->trans('ffs_options_cats_usedolicats_ids'); ?></td>               
                    <td class="hideonsmartphone"><?php echo $langs->trans('ffs_options_cats_usedolicats_ids_desc'); ?></td>
                    <td class="right"><?php print $form->multiselectarray('srff-cats', $cate_arbo, $arrayselected, '', 0, '', 0, '100%'); ?></td>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->trans('ffs_options_cats_predefined'); ?></td>               
                    <td class="hideonsmartphone"><?php print $langs->trans('ffs_options_cats_predefined_desc'); ?></td>
                    <td class="right"><input type="text" name="srff-serverlist" id="srff-serverlist" style="width: 100%;" value="<?php echo $fastfactsupplier->params['custom_list']; ?>"></td>
                </tr>
            </tbody>
            <tbody>                
                <tr class="dolpgs-thead" >
                    <th colspan="3"><?php echo $langs->trans('ffs_options_params'); ?></th>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->trans('ffs_options_params_amountmode'); ?></td>               
                    <td class="hideonsmartphone"><?php print $langs->trans('ffs_options_params_amountmode_desc'); ?></td>
                    <td class="right">
                        <?php echo $form->selectarray('srff-amount-mode',array('ht' => 'HT','ttc' => 'TTC','both'=>'HT & TTC'),$fastfactsupplier->params['mode_amount'],0,0,0,'',0,0,0,'','opt-slct minwidth200'); ?>
                    </td>
                </tr>
                <tr class="oddeven dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->trans('ffs_options_params_defaulttax'); ?></td>               
                    <td class="hideonsmartphone"><?php print $langs->trans('ffs_options_params_defaulttax_desc'); ?></td>
                    <td class="right">
                        <?php // echo $form->load_tva('srff-default-tva',$conf->global->SRFF_DEFAULT_TVA); ?>
                        <?php echo $form->selectarray('srff-default-tva',$vat_rates,$fastfactsupplier->params['default_tva'],0,0,0,'',0,0,0,'','minwidth200'); ?>
                    </td>
                </tr>
                <tr class="oddeven dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php echo $langs->trans('ffs_options_params_bankaccount'); ?></td>               
                    <td class="hideonsmartphone"><?php echo $langs->trans('ffs_options_params_bankaccount_desc'); ?></td>
                    <td class="right"><?php $form->select_comptes($fastfactsupplier->params['default_bankaccount'],'srff-bank-account',0,'',1,'',0,'minwidth200'); ?></td>
                </tr>
                <tr class="oddeven dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php echo $langs->trans('ffs_options_params_gotoreg'); ?></td>               
                    <td class="hideonsmartphone"><?php echo $langs->trans('ffs_options_params_gotoreg_desc'); ?></td>
                    <td class="right"><input type="checkbox" name="srff-gotoreg" id="srff-gotoreg" value="oui" <?php if($fastfactsupplier->params['gotoreg']): ?>checked="checked"<?php endif; ?> /></td>
                </tr>
                <tr class="oddeven dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php echo $langs->trans('ffs_options_params_use_extrauploadfile'); ?></td>               
                    <td class="hideonsmartphone"><?php echo $langs->trans('ffs_options_params_use_extrauploadfile_desc'); ?></td>
                    <td class="right"><input type="checkbox" name="srff-usecustomfield-uploadfile" id="srff-usecustomfield-uploadfile" value="oui" <?php if($fastfactsupplier->params['usecustomfield_uploadfile']): ?>checked="checked"<?php endif; ?> /></td>
                </tr>
            </tbody>
            <tbody>
                <tr class="liste_titre dolpgs-thead" >
                    <th colspan="3"><?php echo $langs->trans('ffs_options_customfields'); ?></th>
                </tr>
                
                <tr class="oddeven dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->trans('ffs_options_customfields_invoice'); ?></td>               
                    <td class="hideonsmartphone"><?php print $langs->trans('ffs_options_customfields_desc'); ?></td>
                    <td class="right"><input type="checkbox" name="srff-showextrafact" id="srff-showextrafact" value="oui" <?php if($fastfactsupplier->params['show_extrafields_facture']): ?>checked="checked"<?php endif; ?> /></td>
                </tr>
                <tr class="oddeven dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->trans('ffs_options_customfields_invoiceline'); ?></td>               
                    <td class="hideonsmartphone"><?php print $langs->trans('ffs_options_customfields_desc'); ?></td>
                    <td class="right"><input type="checkbox" name="srff-showextrafactline" id="srff-showextrafactline" value="oui" <?php if($fastfactsupplier->params['show_extrafields_factureline']): ?>checked="checked"<?php endif; ?> /></td>
                </tr>

                <?php if($fastfactsupplier->params['show_extrafields_factureline'] && !empty($extralabels_factureligne)): ?>
                <tr class="oddeven dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php echo $langs->trans('ffs_options_params_linkprojectslines'); ?></td>               
                    <td class="hideonsmartphone"><?php echo $langs->trans('ffs_options_params_linkprojectslines_desc'); ?></td>
                    <td class="right">
                        <?php echo $form->selectarray('srff-lineprojectfield',$extralabels_factureligne,$fastfactsupplier->params['extra_lineproject'],1,0,0,'',0,0,0,'','minwidth200',); ?>
                    </td>
                </tr>                    
                <?php endif; ?>

            </tbody>
        </table>

        <?php if(!$fastfactsupplier->params['show_extrafields_factureline'] || $fastfactsupplier->params['show_extrafields_factureline'] && empty($extralabels_factureligne)): ?>
        <?php $valextra = ($fastfactsupplier->params['extra_lineproject'])?$fastfactsupplier->params['extra_lineproject']:'0'; ?>
            <input type="hidden" name="srff-lineprojectfield" value="<?php echo $valextra; ?>">
        <?php endif; ?>
        <div class="right pgsz-buttons" style="padding:16px 0;">
            <input type="button" class="dolpgs-btn btn-danger btn-sm" value="<?php print $langs->trans('ffs_cancel'); ?>" onclick="javascript:history.go(-1)">
            <input type="submit" class="dolpgs-btn btn-primary btn-sm" name="" value="<?php print $langs->trans('ffs_save'); ?>">
        </div>
    </form>

</div>

<?php llxFooter(); $db->close(); ?>