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

require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

dol_include_once('./fastfactsupplier/lib/functions.lib.php');

// Protection if external user
if ($user->societe_id > 0): accessforbidden(); endif;
if(!$user->rights->fastfactsupplier->configurer): accessforbidden(); endif;


/*******************************************************************/
// VARIABLES
/*******************************************************************/
$version = explode('.', DOL_VERSION);
$ccs = array(
    '606110' => "Eau",
    '606120' => "Gaz",
    '606130' => "Electricité",
    '606300' => "Fournitures d\’entretien",
    '606310' => "Petit équipement",
    '606400' => "Fournitures administratives",
    '611000' => "Sous-traitance",
    '612200' => "Crédit Bail mobilier",
    '612500' => "Crédit Bail immobilier",
    '613000' => "Loyers",
    '613200' => "Locations mobilière",
    '613500' => "Location immobilière",
    '614000' => "Charges locatives",
    '615200' => "Entretien et réparation sur bien mobilier",
    '615500' => "Entretien et réparation sur bien immobilier",
    '615600' => "Maintenance",
    '616000' => "Assurances",
    '617000' => "Etudes et recherches",
    '618000' => "Divers",
    '618100' => "Documentation générale",
    '618300' => "Documentation technique",
    '681500' => "Frais de séminaires",
    '621100' => "Personnel extérieur, interim",
    '622600' => "Honoraires",
    '622100' => "Commissions sur achats",
    '622200' => "Commissions sur ventes",
    '622700' => "Frais d\’actes",
    '623100' => "Publicités",
    '623400' => "Cadeaux à la clientèle",
    '624000' => "Transport",
    '625100' => "Frais de déplacements",
    '625700' => "Frais de réception",
    '626100' => "Frais postaux",
    '626200' => "Frais télécommunication",
    '626300' => "Frais internet",
    '627000' => "Frais bancaires",
    '647500' => "Médecine du travail",
    //'CODECOMPTABLE' => "LIBELLE",
);
$nbCcs = count($ccs);
$split_ccs = array_chunk($ccs, 20, true);

$cats = new Categorie($db);

$form = new Form($db);
$cate_arbo = $form->select_all_categories(Categorie::TYPE_PRODUCT, '', 'parent', 64, 0, 1);

/*******************************************************************/
// ACTIONS
/*******************************************************************/
$action = GETPOST('action');

if ($action == 'insert' && GETPOST('token') == $_SESSION['token']):

    $cat = GETPOST('cats-ids');
    $tab_cc = GETPOST('tab_cc');

    // SI LE TABLEAU RECU N'EST PAS VIDE
    if(!empty($tab_cc)):

        // ON PREPARE UN TABLEAU PROPRE DES DONNEES A INSERER
        $k = array();

        foreach ($tab_cc as $post_cc):
            $n = array('account_number' => $post_cc, 'label' => $ccs[$post_cc]); array_push($k, $n);
        endforeach;

        //var_dump($k);

        $def_cat = '';

         // SI ON RECOIT UNE CATEGORIE
        if(!empty($cat)):

            // SI ELLE EXISTE
            if($cats->fetch($cat)): $def_cat = $cats->id;

            // SI ELLE N'EXISTE PAS
            else:

                // ON CREE LA CATEGORIE
                $cats->label = $cat;
                $cats->type = 0;
                $id_cat = $cats->create($user);

                if($id_cat > 0):

                    setEventMessages('La catégorie à été créée', null, 'mesgs');
                    $def_cat = $id_cat;
                else:

                    switch($idcat):
                        case -1: setEventMessages('Erreur SQL', null, 'errors'); break;
                        case -2: setEventMessages('Nouvel identifiant de catégorgie inconnu.', null, 'errors'); break;
                        case -3: setEventMessages('Catégorie invalide', null, 'errors'); break;
                        case -4: setEventMessages('Cette catégorie existe déjà', null, 'errors'); break;
                    endswitch;

                endif;

            endif;
        endif;

        foreach($k as $cc):

            $accountingaccount = new AccountingAccount($db);
            $accountingaccount->fk_pcg_version = 'PCG99-BASE';
            $accountingaccount->account_number = $cc['account_number'];
            $accountingaccount->label = stripslashes($cc['label']);

            $id_aa = $accountingaccount->create($user);
            if($id_aa > 0): 

                if(intval($version[0]) <= 14): $accountingaccount->account_activate($id_aa,0);
                else: $accountingaccount->accountActivate($id_aa,0);
                endif;

                setEventMessages('<span style="font-weight:bold;">'.$accountingaccount->label.'</span> : Code comptable inséré.', null, 'mesgs');

                // SI ON DOIT CREER LE PRODUIT
                if(isset($_POST['products_cc']) && $_POST['products_cc'] == "on"):

                    $prodx = new Product($db);

                    //ON DEFINIT SES VARIABLES
                    $prodx->ref = $accountingaccount->account_number;
                    $prodx->label = $accountingaccount->label;
                    $prodx->libelle = $accountingaccount->label;
                    $prodx->description = "";
                    $prodx->type = 1;
                    $prodx->price_base_type = 'HT';
                    $prodx->status_buy = 1; // EN ACHAT
                    $prodx->accountancy_code_buy = $accountingaccount->account_number; // Code comptable

                    $id_prodx = $prodx->create($user);
                    if($id_prodx > 0):

                        setEventMessages('<span style="font-size:0.75em;"><span style="font-weight:bold;">'.$accountingaccount->label.'</span> : Le service a été créé.</span><br/>', null, 'mesgs');

                        // ON ASSIGNE LA CATEGORIE SI DEFINIE
                        if(!empty($def_cat)):
                            $prodx->fetch($id_prodx);                            
                            $prodx->setCategories(array($def_cat)); 
                        endif;

                    else:
                        setEventMessages('<span style="font-weight:bold;">'.$accountingaccount->label.'</span> : Une erreur est survenue lors de la création du service.', null, 'errors');
                    endif;

                endif;

            else:
                switch($accountingaccount->db->lasterrno):
                    case 'DB_ERROR_RECORD_ALREADY_EXISTS': setEventMessages('<span style="font-weight:bold;">'.$accountingaccount->label.'</span> : ce code comptable existe déjà.', null, 'errors'); break;
                    default:setEventMessages('<span style="font-weight:bold;">'.$accountingaccount->label.'</span> : une erreur est survenue lors de la création du code comptable.', null, 'errors'); break;
                endswitch;
            endif;

        endforeach;

    else: setEventMessages('Aucune donnée séléctionnée', null, 'errors');
    endif;

   

endif;

/*******************************************************************/
// VUE
/*******************************************************************/
$array_js = array(
    '/fastfactsupplier/js/fastfactsupplier.js',
);
$array_css = array(
    '/fastfactsupplier/css/fastfactsupplier.css',
    '/fastfactsupplier/css/dolpgs.css',
);

llxHeader('',$langs->trans('ffs_cc_insert'),'','','','',$array_js,$array_css,'','fastfactsupplier insert'); ?>

<div class="dolpgs-main-wrapper fastfact">

    <?php if(in_array('progiseize', $conf->modules)): ?>
        <h1 class="has-before"><?php echo $langs->transnoentities('ffs_page_title'); ?></h1>
    <?php else : ?>
        <table class="centpercent notopnoleftnoright table-fiche-title"><tbody><tr class="titre"><td class="nobordernopadding widthpictotitle valignmiddle col-picto"><span class="fas fa-file-invoice-dollar valignmiddle widthpictotitle pictotitle" style=""></span></td><td class="nobordernopadding valignmiddle col-title"><div class="titre inline-block"><?php echo $langs->transnoentities('ffs_page_title'); ?></div></td></tr></tbody></table>
    <?php endif; ?>
    <?php $head = ffsAdminPrepareHead(); echo dol_get_fiche_head($head, 'insert','FastFactSupplier', 0,'fa-file-invoice-dollar_fas_#fb2a52'); ?>

    <?php if(!in_array('progiseize', $conf->modules)): ?>
        <div class="alert-message-need-base">
            <i class="fas fa-info-circle" style="margin-right:5px;"></i> 
            Cette version nécéssite le module PROGISEIZE pour fonctionner correctement. Vous pouvez la télécharger depuis Github en cliquant sur ce lien : <a href="https://github.com/progiseize/progiseize" target="_blank">Module Progiseize Github</a>
        </div>
    <?php endif; ?>
    
    <?php if($langs->shortlang == 'fr'): ?>

        <form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>" method="post" id="">
            <input type="hidden" name="action" value="insert">
            <input type="hidden" name="token" value="<?php echo newtoken(); ?>">

            <?php //var_dump($tab_img); ?>
            <h3 class="dolpgs-table-title"><?php echo $langs->trans('ffs_cc_title_list'); ?></h3>
            <table class="dolpgs-table fastfact-table">
                <tbody>
                    <tr class="dolpgs-thead noborderside">
                        <th><?php echo $langs->trans('Label'); ?></th>
                        <th><?php echo $langs->trans('ffs_codecomptable'); ?></th>
                        <th class="right"><a href="#" id="toggle_untoggle" style="font-size: 0.9em;color: #424242;font-style: italic;margin-left: 6px;"><?php print $langs->trans('ffs_cc_toggle_untoggle'); ?></a></th>
                    </tr>
                    <?php  $i = 0; foreach ($ccs as $cc_key => $cc_label): $i++; ?>
                    <tr class="dolpgs-tbody">
                        <td class="dolpgs-font-medium"><?php echo stripcslashes($cc_label); ?></td>               
                        <td class="dolpgs-color-gray-i"><?php echo $cc_key; ?></td>
                        <td class="right"><input type="checkbox" name="tab_cc[]" value="<?php echo $cc_key; ?>" class="toguntog"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 class="dolpgs-table-title"><?php echo $langs->trans('ffs_cc_title_options'); ?></h3>
            <table class="dolpgs-table fastfact-table">
                <tbody>
                     <tr class="dolpgs-thead noborderside">
                        <th><?php echo $langs->trans('Parameter'); ?></th>
                        <th><?php echo $langs->trans('Description'); ?></th>
                        <th class="right"><?php echo $langs->trans('Value'); ?></th>
                    </tr>
                    <tr class="dolpgs-tbody">
                        <td class="dolpgs-font-medium"><?php print $langs->trans('ffs_cc_create_services'); ?></td>               
                        <td class="dolpgs-color-gray-i"><?php print $langs->trans('ffs_cc_create_services_desc'); ?></td>
                        <td class="right"><input type="checkbox" name="products_cc" checked="checked"></td>
                    </tr>
                    <tr class="dolpgs-tbody">
                        <td class="dolpgs-font-medium"><?php print $langs->trans('ffs_cc_create_cat'); ?></td>               
                        <td class="dolpgs-color-gray-i"><?php print $langs->trans('ffs_cc_create_cat_desc'); ?></td>
                        <td class="right">
                            <?php echo $form->selectarray('cats-ids',$cate_arbo,GETPOST('cats-ids'),0,0,0,'',0,0,0,'','minwidth200'); ?>      
                        </td>
                    </tr>
                             
                </tbody>
            </table>
            <div class="right pgsz-buttons" style="padding:16px 0;">
                <input type="button" class="dolpgs-btn btn-danger btn-sm" value="<?php echo $langs->trans('ffs_cancel'); ?>" onclick="javascript:history.go(-1)">
                <input type="submit" class="dolpgs-btn btn-primary btn-sm" name="" value="<?php echo $langs->trans('ffs_cc_button_insert'); ?>">
            </div>
        </form>

    <?php else: ?>
        <div class="error" ><?php echo $langs->transnoentities('ffs_insertContactProgiseize');?></div>
    <?php endif; ?>

</div>




<?php llxFooter(); $db->close(); ?>