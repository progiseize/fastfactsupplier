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

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$res=0;
if (! $res && file_exists("../main.inc.php")): $res=@include '../main.inc.php'; endif;
if (! $res && file_exists("../../main.inc.php")): $res=@include '../../main.inc.php'; endif;

// Protection if external user
if ($user->socid > 0): accessforbidden(); endif;
if(!$user->rights->fastfactsupplier->saisir): accessforbidden(); endif;

/*******************************************************************
* FICHIERS & CLASSES
********************************************************************/
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/cactioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

// On ajoute la class Produit si version < 10
$version = explode('.', DOL_VERSION);
if($version[0] < 10 ): require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php'; endif;

// On ajoute la class Projet si activé
if (!empty($conf->projet->enabled)) {
    require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
    require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
}

dol_include_once('./fastfactsupplier/class/fastfactsupplier.class.php');

/*******************************************************************
* FONCTIONS
********************************************************************/
dol_include_once('./fastfactsupplier/lib/functions.lib.php');

/*******************************************************************
* CONFIGURATION
********************************************************************/
$fastfactsupplier = new FastFactSupplier($db);

// On détermine la taille maximum des fichiers en upload
$maxfilesize = file_upload_max_size();
$maxfilesize_ko = $maxfilesize / 1024;
$maxfilesize_mo = $maxfilesize_ko / 1024;


/*******************************************************************
* VARIABLES
********************************************************************/
$facture = new FactureFournisseur($db);
$facture_ligne = new SupplierInvoiceLine($db);
$societe = new Societe($db);
$form = new Form($db);
if(!empty($conf->projet->enabled)): $formproject = new FormProjets($db); endif;

// ON INSTANCIE LES EXTRAFIELDS SI BESOIN
if(!isset($extrafields)): $extrafields = new ExtraFields($db); endif;

// On calcule le prochain code fournisseur
$societe->get_codefournisseur($societe,1);

// ON RECUPERE LES LABELS DES EXTRAFIELDS $FACTURE (FOURNISSEUR)
if($fastfactsupplier->params['show_extrafields_facture']): $extralabels_facture = $extrafields->fetch_name_optionals_label($facture->table_element); endif;
if($fastfactsupplier->params['show_extrafields_factureline']): $extralabels_factureligne = $extrafields->fetch_name_optionals_label($facture_ligne->table_element); endif;
$extrafields_view_tab = array('1','3'); 

$hookmanager->initHooks(array('fastfactsupplier'));

// 
$action = GETPOST('action');

// Infos Thirdparty
$thirdparty_name = GETPOST('creatiers-nom', 'alphanohtml');
$thirdparty_id = GETPOST('fournid', 'int');
$thirdparty_suppliercode = GETPOST('creatiers-codefournisseur', 'alphanohtml');
if(empty($thirdparty_suppliercode)): $thirdparty_suppliercode = $societe->code_fournisseur; endif;

// General infos for Invoice
$invoice_libelle = GETPOST('creafact-libelle', 'alphanohtml');
$invoice_supplierref = GETPOST('creafact-reffourn', 'alphanohtml');
$invoice_date = GETPOST('creafact-datefact', 'alphanohtml');
if(empty($invoice_date)): $invoice_date = date('d/m/Y'); endif;
$invoice_datelimit = GETPOST('creafact-datelim', 'alphanohtml');
if(empty($invoice_datelimit)): $invoice_datelimit = date('d/m/Y'); endif;
$invoice_bank_account = GETPOST('srff-bank-account', 'int');
$invoice_project = GETPOST('creafact-projet', 'int');

//
unset($conf->modules_parts['models']['hrm']);
$specimen = new FactureFournisseur($db);
$specimen->initAsSpecimen();
$specimen_next_number = $specimen->getNextNumRef($mysoc);

// Extrafields invoice
$invoice_extrafields = array();

if($fastfactsupplier->params['show_extrafields_facture'] && !empty($extralabels_facture)):
    foreach($extralabels_facture as $key_extrafield => $label_extrafield):

        // On check l'entité
        if(!in_array($extrafields->attributes[$facture->table_element]['entityid'][$key_extrafield], array(0,$conf->entity))): continue; endif;

        // On check s'il est activé 
        if(!$extrafields->attributes[$facture->table_element]['enabled'][$key_extrafield]): continue; endif;
        if(!empty($extrafields->attributes[$facture->table_element]['enabled'][$key_extrafield])):
            if(!dol_eval($extrafields->attributes[$facture->table_element]['enabled'][$key_extrafield], 1)): continue; endif;
        endif;

        // On check si il est visible
        if(!in_array($extrafields->attributes[$facture->table_element]['list'][$key_extrafield], $extrafields_view_tab)): continue; endif;

        // On check les perms
        if(!$extrafields->attributes[$facture->table_element]['perms'][$key_extrafield]): continue; endif;

        // Lang File
        if(!empty($extrafields->attributes[$facture->table_element]['langfile'][$key_extrafield])):
            $langs->load($extrafields->attributes[$facture->table_element]['langfile'][$key_extrafield]);
            $extralabels_facture[$key_extrafield] = $langs->transnoentities($label_extrafield);
            $extrafields->attributes[$facture->table_element]['label'][$key_extrafield] = $langs->transnoentities($label_extrafield);
        endif;

        // On l'ajoute au tableau avec sa valeur
        $invoice_extrafields[$key_extrafield] = GETPOST('options_'.$key_extrafield,'alphanohtml');

    endforeach;    
endif;

// Extrafields invoice -> list for populate line extrafields
$invoice_extrafields_line = array();
if($fastfactsupplier->params['show_extrafields_factureline'] && !empty($extralabels_factureligne)):
    foreach($extralabels_factureligne as $key_extrafield => $label_extrafield):

        // On check l'entité
        if(!in_array($extrafields->attributes[$facture_ligne->table_element]['entityid'][$key_extrafield], array(0,$conf->entity))): continue; endif;

        // On check s'il est activé 
        if(!$extrafields->attributes[$facture_ligne->table_element]['enabled'][$key_extrafield]): continue; endif;
        if(!empty($extrafields->attributes[$facture_ligne->table_element]['enabled'][$key_extrafield])):
            if(!dol_eval($extrafields->attributes[$facture_ligne->table_element]['enabled'][$key_extrafield], 1,0)): continue; endif;
        endif;

        // On check si il est visible
        if(!in_array($extrafields->attributes[$facture_ligne->table_element]['list'][$key_extrafield], $extrafields_view_tab)): continue; endif;

        // On check les perms
        if(!$extrafields->attributes[$facture_ligne->table_element]['perms'][$key_extrafield]): continue; endif;

        // Lang File
        if(!empty($extrafields->attributes[$facture_ligne->table_element]['langfile'][$key_extrafield])):
            $langs->load($extrafields->attributes[$facture_ligne->table_element]['langfile'][$key_extrafield]);
            $extralabels_factureligne[$key_extrafield] = $langs->transnoentities($label_extrafield);
            $extrafields->attributes[$facture_ligne->table_element]['label'][$key_extrafield] = $langs->transnoentities($label_extrafield);
        endif;

        // On l'ajoute au tableau
        array_push($invoice_extrafields_line, $key_extrafield);

    endforeach;    
endif;

// Lines of invoice
$invoice_nblines = intval(GETPOST('infofact-linenumber','int'));
if(empty($invoice_nblines)): $invoice_nblines = 0; endif;

$invoice_lines = array();
for ($i=1; $i <= $invoice_nblines; $i++): 

    $invoice_lines['line-'.$i] = array(
        'line_num' => $i,
        'type_saisie' => GETPOST('infofact-saisie-'.$i), // Last entry by user
        'prodserv' => GETPOST('infofact-prodserv-'.$i), // ID if fastfactsupplier->params['use_categories_product'], label if not
        'montant_ligne_ht' => GETPOST('infofact-montantht-'.$i),
        'montant_ligne_ttc' => GETPOST('infofact-montantttc-'.$i),
        'qty' => GETPOST('infofact-qty-'.$i),
        'taux_tva' => GETPOST('infofact-tva-'.$i,'int'),
        'extrafields' => array(),
    );

    if(!empty($invoice_extrafields_line)):
        foreach($invoice_extrafields_line as $key_extrafield):
            $invoice_lines['line-'.$i]['extrafields'][$key_extrafield] = GETPOST('options_'.$key_extrafield.'-'.$i,'alphanohtml');
        endforeach;
    endif;
endfor;

// Upload file
$upload_file = (!empty($_FILES['creafact-file']['name']))?$_FILES['creafact-file']:'';

// Link file
$file_link_url = GETPOST('creafact-linkurl','alphanohtml');
$file_link_label = GETPOST('creafact-linklib','alphanohtml');

// Redirect
$gotoreg = $fastfactsupplier->params['gotoreg'];
$redirect = GETPOST('creafact-redirect');
if($redirect): $gotoreg = $redirect; endif;

// Taux de tva
$form->load_cache_vatrates("'".$mysoc->country_code."'");
$vat_rates = array();
foreach($form->cache_vatrates as $vat):
    $vat_rates[$vat['txtva']] = $vat['label'];
endforeach;

// On recupère la liste des services
if($fastfactsupplier->params['use_categories_product']): $tab_prodserv = $fastfactsupplier->get_products_services_list($fastfactsupplier->params['cats_to_use']);
else: $tab_prodserv = explode(',',$fastfactsupplier->params['custom_list']); endif;

// On crée la liste des fournisseurs
$list_options_fournisseurs = $fastfactsupplier->get_supplier_list($thirdparty_name,$thirdparty_suppliercode);

// Gestion des erreurs & securite
$errmsg = array();

// Déclaration de variables utiles
$calcul_ht = 0;
$calcul_tva = 0;
$calcul_ttc = 0;

/*******************************************************************
* ACTIONS
********************************************************************/

// PARAMS
$parameters = array(
    'thirdparty_name' => $thirdparty_name,
    'thirdparty_suppliercode' => $thirdparty_suppliercode,
    'thirdparty_id' => $thirdparty_id,
    'invoice_libelle' => $invoice_libelle,
    'invoice_supplierref' => $invoice_supplierref,
    'invoice_date' => implode('-',array_reverse(explode('/',$invoice_date))),
    'invoice_datelimit' => implode('-',array_reverse(explode('/',$invoice_datelimit))),
    'invoice_bank_account' => $invoice_bank_account,
    'invoice_extrafields' => $invoice_extrafields,
    'invoice_lines' => $invoice_lines,
    'redirect_to_payment' => $gotoreg,
    'upload_file' => $upload_file,
    'file_link_url' => $file_link_url,
    'file_link_label' => $file_link_label,
);

if (!empty($conf->projet->enabled)):
    $parameters['invoice_project'] =  GETPOST('creafact-projet', 'int');
endif;

$reshook = $hookmanager->executeHooks('doActions', $parameters, $fastfactsupplier, $action);
if ($reshook < 0): setEventMessages($hookmanager->error, $hookmanager->errors, 'errors'); endif;

if (empty($reshook)):
    if ($action == 'create' && GETPOST('token') == $_SESSION['token']):

        $db->begin();

        // ON VERIFIE LES VARIABLES
        if (!$thirdparty_name): $error++; array_push($fastfactsupplier->errors, 'creatiers-nom'); array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_tiersname'))); endif;
        if (!$thirdparty_suppliercode): $error++; array_push($fastfactsupplier->errors, 'creatiers-codefournisseur'); array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_codefourn'))); endif;
        if (!$invoice_supplierref): $error++; array_push($fastfactsupplier->errors, 'creafact-reffourn'); array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_reffourn'))); endif;
        if (!empty($invoice_supplierref) && !empty($thirdparty_id)): 
            if($fastfactsupplier->check_exist_ref_supplier($invoice_supplierref,$thirdparty_id)):
                $error++; array_push($fastfactsupplier->errors, 'creafact-reffourn');
                array_push($errmsg,$langs->transnoentities('ffs_fielderror_reffourn_exist'));
            endif;
        endif;    
        if (!$invoice_date): $error++; array_push($fastfactsupplier->errors, 'creafact-datefact'); array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_date'))); endif;
        if (!$invoice_datelimit): $error++; array_push($fastfactsupplier->errors, 'creafact-datelim'); array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_datelim'))); endif;
        if ($invoice_date && $invoice_datelimit):
            $diff_date = strtotime(implode('-',array_reverse(explode('/',$invoice_date)))) - strtotime(implode('-',array_reverse(explode('/',$invoice_datelimit)))); 
            if ($diff_date > 0): array_push($fastfactsupplier->errors, 'creafact-datelim'); array_push($errmsg, $langs->transnoentities('ffs_fielderror_date_limsupdate'));endif;
        endif;

        // ON VERIFIE LES EXTRAFIELDS DE FACTURES SI ACTIVE
        if($fastfactsupplier->params['show_extrafields_facture']):

            foreach($invoice_extrafields as $key_extrafield => $value_extrafield):

                // ON VERIFIE SI LES CHAMPS OBLIG. SONT VIDES
                if($extrafields->attributes[$facture->table_element]['required'][$key_extrafield] && !$value_extrafield):
                    $error++; 
                    array_push($fastfactsupplier->errors,'options_'.$key_extrafield); 
                    array_push($errmsg , $langs->transnoentities("ErrorFieldRequired",$extrafields->attributes[$facture->table_element]['label'][$key_extrafield]));
                endif;

                // ON VERIFIE SI LES CHAMPS DOIVENT ETRE UNIQUE
                if($extrafields->attributes[$facture->table_element]['unique'][$key_extrafield] && $value_extrafield):
                    $check_unique = $fastfactsupplier->is_extrafield_unique($key_extrafield,$facture->table_element,$value_extrafield);
                    if($check_unique > 0):
                        $error++; 
                        array_push($fastfactsupplier->errors,'options_'.$key_extrafield); 
                        array_push($errmsg , $langs->transnoentities("ffs_fielderror_mustbeunique",$extrafields->attributes[$facture->table_element]['label'][$key_extrafield]));
                    endif;
                endif;

                // ON VERIFIE LA TAILLE MAX
                if(!empty($extrafields->attributes[$facture->table_element]['size'][$key_extrafield]) &&  strlen($value_extrafield) > intval($extrafields->attributes[$facture->table_element]['size'][$key_extrafield])):
                    $error++; 
                    array_push($fastfactsupplier->errors,'options_'.$key_extrafield); 
                    array_push($errmsg , $langs->transnoentities("ErrorFieldTooLong",$extrafields->attributes[$facture->table_element]['label'][$key_extrafield]));
                endif;

                // ON VERIFIE LES VALEURS EN FONCTIONS DES TYPES
                if(!empty($value_extrafield)):

                    switch ($extrafields->attributes[$facture->table_element]['type'][$key_extrafield]):

                        //
                        case 'mail': 
                            if(!filter_var($value_extrafield, FILTER_VALIDATE_EMAIL)):
                                $error++;
                                array_push($fastfactsupplier->errors,'options_'.$key_extrafield);
                                array_push($errmsg,$langs->transnoentities('ffs_fielderror_emailnotvalid',$value_extrafield));
                            endif;
                        break;

                        case 'phone': 
                            if(!is_numeric($value_extrafield)): 
                                $error++;
                                array_push($fastfactsupplier->errors,'options_'.$key_extrafield);
                                array_push($errmsg,$langs->transnoentities('ffs_fielderror_noletter',$extrafields->attributes[$facture->table_element]['label'][$key_extrafield]));
                            endif;
                        break;

                        // VALEUR ENTIERE, ON RETIRE SI CHIFFRES APRES LA VIRGULE
                        case 'int':
                            if(!filter_var($value_extrafield, FILTER_VALIDATE_INT)):
                                $error++;
                                array_push($fastfactsupplier->errors,'options_'.$key_extrafield);
                                array_push($errmsg,$langs->transnoentities('ffs_fielderror_needint',$extrafields->attributes[$facture->table_element]['label'][$key_extrafield]));
                            endif;
                        break;

                        // VALEURS DECIMALES
                        case 'double': case 'price': 
                            $value_extrafield = str_replace(',','.',$value_extrafield);
                            if(!is_numeric($value_extrafield)):
                                $error++;
                                array_push($fastfactsupplier->errors,'options_'.$key_extrafield);
                                array_push($errmsg,$langs->transnoentities('ffs_fielderror_needfloat',$extrafields->attributes[$facture->table_element]['label'][$key_extrafield]));
                            endif;
                        break;

                        default: break;

                    endswitch;
                endif;

            endforeach;
        endif;

        // POUR CHAQUE LIGNE DE FACTURE
        foreach($invoice_lines as $keyline => $line):

            $calcul_line = true;
            
            // PRODUIT / SERVICE NON VIDE
            if (empty($line['prodserv'])): 
                $error++; 
                array_push($fastfactsupplier->errors, 'infofact-prodserv-'.$line['line_num']);
                array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_prodserv').' '.$line['line_num']));
            endif;

            // EN FCTION DU MODE DE CALCUL
            switch ($line['type_saisie']):
                case '':
                        $error++; $calcul_line = false;
                        array_push($fastfactsupplier->errors, 'infofact-montantht-'.$line['line_num']); 
                        array_push($fastfactsupplier->errors, 'infofact-montantttc-'.$line['line_num']); 
                        array_push($errmsg,$langs->transnoentities('ffs_fielderror_needhtorttc', $line['line_num']));
                break;
                case 'ht':
                    if(empty($line['montant_ligne_ht'])): 
                        $error++; $calcul_line = false;
                        array_push($fastfactsupplier->errors, 'infofact-montantht-'.$line['line_num']); 
                        array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_amount').' '.$line['line_num']));
                    else:
                        $post_montant = str_replace(',', '.', $line['montant_ligne_ht']);
                        if (!is_numeric($post_montant)): 
                            $error++; $calcul_line = false;
                            array_push($fastfactsupplier->errors, 'infofact-montantht-'.$line['line_num']);
                            array_push($errmsg,$langs->transnoentities("ErrorFieldFormat", $langs->transnoentities('ffs_field_amount').' '.$line['line_num']));
                        endif;
                    endif;                
                break;
                case 'ttc':
                    if (empty($line['montant_ligne_ttc'])): 
                        $error++; $calcul_line = false;
                        array_push($fastfactsupplier->errors, 'infofact-montantttc-'.$line['line_num']); 
                        array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_amount').' '.$line['line_num']));
                    else:
                        $post_montant = str_replace(',', '.', $line['montant_ligne_ttc']);
                        if (!is_numeric($post_montant)): 
                            $error++; $calcul_line = false;
                            array_push($fastfactsupplier->errors, 'infofact-montantttc-'.$line['line_num']);
                            array_push($errmsg,$langs->transnoentities("ErrorFieldFormat", $langs->transnoentities('ffs_field_amount').' '.$line['line_num']));
                        endif;
                    endif;
                break;
            endswitch;

            // QTY
            if (empty($line['qty'])): 
                $error++; $calcul_line = false;
                array_push($fastfactsupplier->errors, 'infofact-qty-'.$line['line_num']);
                array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('Quantity').' '.$line['line_num']));
            endif;

            // ON RECALCULE LES MONTANTS 
            if($calcul_line):

                $lineqty = floatval(str_replace(',', '.', $line['qty']));
                switch ($line['type_saisie']):
                    case 'ht':
                        $ligne_ht_montant = floatval($post_montant);
                        $ligne_tva_montant = (floatval($post_montant)/100) * floatval($line['taux_tva']);
                        $calcul_ht += $ligne_ht_montant * $lineqty; 
                        $calcul_tva += $ligne_tva_montant * $lineqty; 
                        $calcul_ttc += $ligne_ht_montant + $ligne_tva_montant;
                    break;
                    case 'ttc':
                        $ligne_ttc_montant = floatval($post_montant);
                        $ligne_ht_montant = $ligne_ttc_montant / (1 + floatval($line['taux_tva']) / 100);
                        $calcul_ht += $ligne_ht_montant * $lineqty;
                        $calcul_tva += ($ligne_ttc_montant * $lineqty) - ($ligne_ht_montant * $lineqty);
                        $calcul_ttc += $ligne_ttc_montant * $lineqty;
                    break;
                endswitch;                        
            endif;

            // ON VERIFIE LES EXTRAFIELDS DES LIGNES
            if($fastfactsupplier->params['show_extrafields_factureline']):

                /***************/
                foreach($line['extrafields'] as $key_extrafield => $value_extrafield):

                    // ON VERIFIE SI LES CHAMPS OBLIG. SONT VIDES
                    if($extrafields->attributes[$facture_ligne->table_element]['required'][$key_extrafield] && !$value_extrafield):
                        $error++; 
                        array_push($fastfactsupplier->errors,'options_'.$key_extrafield.'-'.$line['line_num']); 
                        array_push($errmsg , $langs->transnoentities("ErrorFieldRequired",$extrafields->attributes[$facture_ligne->table_element]['label'][$key_extrafield].' - '.$line['line_num']));
                    endif;

                    // ON VERIFIE SI LES CHAMPS DOIVENT ETRE UNIQUE
                    if($extrafields->attributes[$facture_ligne->table_element]['unique'][$key_extrafield] && $value_extrafield):
                        $check_unique = $fastfactsupplier->is_extrafield_unique($key_extrafield,$facture_ligne->table_element,$value_extrafield);
                        if($check_unique > 0):
                            $error++; 
                            array_push($fastfactsupplier->errors,'options_'.$key_extrafield.'-'.$line['line_num']); 
                            array_push($errmsg , $langs->transnoentities("ffs_fielderror_mustbeunique",$extrafields->attributes[$facture_ligne->table_element]['label'][$key_extrafield].' - '.$line['line_num']));
                        endif;
                    endif;

                    // ON VERIFIE LA TAILLE MAX
                    if(!empty($extrafields->attributes[$facture_ligne->table_element]['size'][$key_extrafield]) &&  strlen($value_extrafield) > intval($extrafields->attributes[$facture_ligne->table_element]['size'][$key_extrafield])):
                        $error++; 
                        array_push($fastfactsupplier->errors,'options_'.$key_extrafield.'-'.$line['line_num']);
                        array_push($errmsg , $langs->transnoentities("ErrorFieldTooLong",$extrafields->attributes[$facture->table_element]['label'][$key_extrafield].' - '.$line['line_num']));
                    endif;

                    // ON VERIFIE LES VALEURS EN FONCTIONS DES TYPES
                    if(!empty($value_extrafield)):

                        switch ($extrafields->attributes[$facture_ligne->table_element]['type'][$key_extrafield]):

                            //
                            case 'mail': 
                                if(!filter_var($value_extrafield, FILTER_VALIDATE_EMAIL)):
                                    $error++;
                                    array_push($fastfactsupplier->errors,'options_'.$key_extrafield.'-'.$line['line_num']);
                                    array_push($errmsg,$langs->transnoentities('ffs_fielderror_emailnotvalid',$value_extrafield));
                                endif;
                            break;

                            case 'phone': 
                                if(!is_numeric($value_extrafield)): 
                                    $error++;
                                    array_push($fastfactsupplier->errors,'options_'.$key_extrafield.'-'.$line['line_num']);
                                    array_push($errmsg,$langs->transnoentities('ffs_fielderror_noletter',$extrafields->attributes[$facture->table_element]['label'][$key_extrafield].' - '.$line['line_num']));
                                endif;
                            break;

                            // VALEUR ENTIERE, ON RETIRE SI CHIFFRES APRES LA VIRGULE
                            case 'int':
                                if(!filter_var($value_extrafield, FILTER_VALIDATE_INT)):
                                    $error++;
                                    array_push($fastfactsupplier->errors,'options_'.$key_extrafield.'-'.$line['line_num']);
                                    array_push($errmsg,$langs->transnoentities('ffs_fielderror_needint',$extrafields->attributes[$facture->table_element]['label'][$key_extrafield].' - '.$line['line_num']));
                                endif;
                            break;

                            // VALEURS DECIMALES
                            case 'double': case 'price': 
                                $value_extrafield = str_replace(',','.',$value_extrafield);
                                if(!is_numeric($value_extrafield)):
                                    $error++;
                                    array_push($fastfactsupplier->errors,'options_'.$key_extrafield.'-'.$line['line_num']);
                                    array_push($errmsg,$langs->transnoentities('ffs_fielderror_needfloat',$extrafields->attributes[$facture->table_element]['label'][$key_extrafield].' - '.$line['line_num']));
                                endif;
                            break;

                            default: break;

                        endswitch;
                    endif;

                endforeach;
            endif;

        endforeach;

        // S'IL N'Y A PAS D'ERREUR ON CONTINUE
        if(!$error):

            // ON VERIFIE SI LE TIERS A BESOIN D'ETRE CRÉÉ
            if($thirdparty_id > 0): $check_tiers = $societe->fetch($thirdparty_id);
            else: $check_tiers = $societe->fetch('',$thirdparty_name);
            endif;

            /**********************************************************/
            /* TIERS*/
            /**********************************************************/
            // SI LE TIERS EXISTE
            if($check_tiers > 0):
                $tiers_rowid = $thirdparty_id;
                $societe->fetch($societe->id);

            // SI LE TIERS N'EXISTE PAS
            elseif($check_tiers == 0):
                $societe->nom = $thirdparty_name;
                $societe->fournisseur = 1;
                $societe->get_codefournisseur($societe,1);
                $societe->pays = 1;
                $tiers_rowid = $societe->create($user); if($tiers_rowid < 0): $error++; endif;
                $societe->country_id = 1;
                $update_societe = $societe->update($tiers_rowid,$user); if($update_societe < 0): $error++; endif;
                $societe->fetch($tiers_rowid);
            else:
                $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_tierstoomuch'));
            endif;

            /**********************************************************/
            /* FACTURE*/
            /**********************************************************/

            if(!$error):

                $date_facturation_tmp = explode('/', $invoice_date);
                $date_facturation = mktime('0','0','1',$date_facturation_tmp[1],$date_facturation_tmp[0],$date_facturation_tmp[2]);
                $date_limit_tmp = explode('/', $invoice_datelimit);
                $date_limit = mktime('0','0','1',$date_limit_tmp[1],$date_limit_tmp[0],$date_limit_tmp[2]);

                // 
                $facture->socid = $societe->id;
                $facture->ref = $facture->getNextNumRef($societe);
                $facture->ref_supplier = $invoice_supplierref;
                $facture->libelle = $invoice_libelle;
                $facture->date = $date_facturation;
                $facture->date_echeance = $date_limit;
                $facture->entity = $conf->entity;

                $facture->cond_reglement_id = $societe->cond_reglement_supplier_id;
                $facture->mode_reglement_id = $societe->mode_reglement_supplier_id;

                $facture_id = $facture->create($user); if($facture_id < 0): $error++; endif;

                // BANK ACCOUNT
                if(!empty($invoice_bank_account) && intval($invoice_bank_account) > 0): $facture->setBankAccount($invoice_bank_account,false,$user); endif;
                

                // PROJETS
                if (!empty($conf->projet->enabled) && intval($invoice_project) > 0):
                    $facture->setProject($invoice_project);
                endif;

                // ON AJOUTE LES EXTRAFIELDS A LA FACTURE
                if($fastfactsupplier->params['show_extrafields_facture']):
                    $facture->array_options = array(); 
                    foreach($invoice_extrafields as $key_extrafield => $value_extrafield):
                        $facture->array_options['options_'.$key_extrafield] = $value_extrafield;
                    endforeach;
                    if(!empty($facture->array_options)):
                        $facture->insertExtraFields();
                    endif;
                endif; 
                
                $lines_ok = 0;

                // POUR CHAQUE LIGNE DE FACTURE
                foreach($invoice_lines as $keyline => $line):

                    // ON FAIT LES CALCULS DE BASE
                    switch($fastfactsupplier->params['mode_amount']):
                        case 'ht':
                            $typesaisie_ligne = $fastfactsupplier->params['mode_amount'];
                            $montant_ligne = floatval(str_replace(',','.',$line['montant_ligne_ht']));
                        break;
                        case 'ttc':
                            $typesaisie_ligne = $fastfactsupplier->params['mode_amount'];
                            $montant_ligne = floatval(str_replace(',','.',$line['montant_ligne_ttc']));                        
                        break;
                        case 'both':
                            $typesaisie_ligne = $line['type_saisie'];
                            switch($typesaisie_ligne):
                                case 'ht': $montant_ligne = floatval(str_replace(',','.',$line['montant_ligne_ht'])); break;
                                case 'ttc': $montant_ligne = floatval(str_replace(',','.',$line['montant_ligne_ttc'])); break;
                            endswitch;
                        break;
                    endswitch;

                    $ligne_tva = number_format(floatval($line['taux_tva']),3,'.','');
                    $ligne_qty = floatval($line['qty']);

                    if($fastfactsupplier->params['use_categories_product']): $ligne_fk_product = $line['prodserv']; $ligne_description = '';
                    else: $ligne_fk_product = 0; $ligne_description = $line['prodserv'];
                    endif;

                    // ON AJOUTE LA LIGNE
                    if($id_ligne = $facture->addline($ligne_description,$montant_ligne,$ligne_tva,0,0,$ligne_qty,$ligne_fk_product,0,'','',0,'',strtoupper($typesaisie_ligne),1,-1,false,'',null,0,0)):
                    
                        $lines_ok++;
                        $facture_ligne->fetch($id_ligne);

                        // EXTRAFIELDS LINE
                        if($fastfactsupplier->params['show_extrafields_factureline']):
                            foreach($line['extrafields'] as $key_extrafield => $value_extrafield):
                                $facture_ligne->array_options['options_'.$key_extrafield] = $value_extrafield;
                            endforeach;
                            if(!empty($facture_ligne->array_options)):
                                if(!$facture_ligne->insertExtraFields()): $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_saveextraslines')); endif;
                            endif;
                        endif;

                    // SI IL Y A UNE ERREUR
                    else: $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_saveline',$line['line_num']));
                    endif;

                endforeach;

                //
                if($invoice_nblines == $lines_ok):                
                    $valid_facture = $facture->validate($user);
                    if($valid_facture < 0): $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_validateinvoice')); endif;
                endif;
            
                // ON AJOUTE LE DOCUMENT LIE SI IL Y EN A UN
                if(!empty($file_link_url)):

                    require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';

                    $linkstatic = new Link($db);
                    $linkstatic->url = $file_link_url;
                    if(!empty($file_link_label)): $linkstatic->label = $file_link_label; endif;
                    $linkstatic->objecttype = 'invoice_supplier';
                    $linkstatic->objectid = $facture_id;

                    if(!$linkstatic->create($user)): $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_linkfile')); 
                    elseif($fastfactsupplier->params['usecustomfield_uploadfile']): 
                        $facture->array_options['options_ffs_uploadfile'] = 1;
                        $facture->updateExtraField('ffs_uploadfile');
                    endif;

                endif;

                if(!empty($upload_file)):

                    // ON DEFINIT LES REPERTOIRES DES FICHIERS
                    $upload_dir = $conf->fournisseur->facture->dir_output.'/'.get_exdir($facture_id,2,0,0,$facture,'invoice_supplier').$facture->ref .'/';

                    // ON CREE LES REPERTOIRES (S'ILS N'EXISTENT PAS )
                    if(!is_dir($upload_dir)): mkdir($upload_dir, 0777, true); endif;

                    $upload_file_url = $upload_dir .$facture->ref.'-'.basename($upload_file['name']);

                    if(!move_uploaded_file($upload_file['tmp_name'], $upload_file_url)): $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_notuploadfile'));
                    elseif($fastfactsupplier->params['usecustomfield_uploadfile']): 
                        $facture->array_options['options_ffs_uploadfile'] = 1; 
                        $facture->updateExtraField('ffs_uploadfile');
                    endif;

                endif;
            endif;

            //
            $parameters['invoice_id'] = $facture_id;
            $reshook = $hookmanager->executeHooks('doMoreActions', $parameters, $fastfactsupplier, $action);
            if ($reshook < 0): $error++;
                setEventMessages($hookmanager->error, $hookmanager->errors, 'errors'); 
            endif;

        endif;

        //
        if (!$error): 
            $db->commit();
            if($redirect): 
                setEventMessages($langs->transnoentities('ffs_confirm_save_invoice',$facture->ref), null, 'mesgs');
                $goto = dol_buildpath('/fourn/facture/paiement.php?facid='.$facture->id.'&action=create',2);
                header('Location:'.$goto);
            else:
                setEventMessages($langs->transnoentities('ffs_confirm_save_invoice',$facture->ref), null, 'mesgs');
                header('Location:'.$_SERVER['PHP_SELF']);
            endif;
        else: 
            $db->rollback();
            setEventMessages('',$errmsg, 'errors');
        endif;
    endif;
endif;

/*********************************/

/***************************************************
* VIEW
****************************************************/

$array_js = array(
    '/fastfactsupplier/js/jquery-ui.min.js',
    '/fastfactsupplier/js/fastfactsupplier.js'
);
$array_css = array(
    '/fastfactsupplier/css/fastfactsupplier.css',
    '/fastfactsupplier/css/dolpgs.css',
);

llxHeader('',$langs->transnoentities('ffs_page_title'),'','','','',$array_js,$array_css,'','fastfactsupplier saisie'); ?>

<!-- CONTENEUR GENERAL -->
<div class="dolpgs-main-wrapper fastfact">

    <input type="hidden" id="fastfact-lang" value="<?php echo $langs->defaultlang; ?>">

    <?php if(in_array('progiseize', $conf->modules)): ?>
        <h1 class="has-before"><?php echo $langs->transnoentities('ffs_page_title'); ?></h1>
    <?php else : ?>
        <table class="centpercent notopnoleftnoright table-fiche-title"><tbody><tr class="titre"><td class="nobordernopadding widthpictotitle valignmiddle col-picto"><span class="fas fa-file-invoice-dollar valignmiddle widthpictotitle pictotitle" style=""></span></td><td class="nobordernopadding valignmiddle col-title"><div class="titre inline-block"><?php echo $langs->transnoentities('ffs_page_title'); ?></div></td></tr></tbody></table>
    <?php endif; ?>
    <?php $head = ffsAdminPrepareHead(); echo dol_get_fiche_head($head, 'saisir','FastFactSupplier', 0,'fa-file-invoice-dollar_fas_#fb2a52'); ?>

    <?php if($user->rights->fastfactsupplier->saisir): ?>
    <form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>" method="POST">

        <input type="hidden" name="action" value="create">
        <input type="hidden" name="new_reffourn" id="new_reffourn" value="<?php echo $societe->code_fournisseur; ?>">
        <input type="hidden" name="is-already" id="is-already" value="0">
        <input type="hidden" name="fournid" id="fournid" value="<?php echo $thirdparty_id; ?>">
        <input type="hidden" name="token" id="token" value="<?php echo newtoken(); ?>">
        <input type="hidden" name="infofact-linenumber" id="infofact-linenumber" value="<?php if($invoice_nblines == 0): echo '1'; else: echo $invoice_nblines; endif; ?>">
        <input type="hidden" name="ffs_amout_mode" value="<?php echo $fastfactsupplier->params['mode_amount']; ?>">

        <!-- INFOS FACTURE -->
        <h3 class="dolpgs-table-title"><?php echo $langs->trans('ffs_infosgen'); ?></h3>
        <table class="dolpgs-table fastfact-table">
            <tbody>
                <tr class="fastfact-thead dolpgs-thead left">
                    <th colspan="2"><?php echo $langs->trans('ffs_infosgen_tiers'); ?></th>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php echo $langs->transnoentities('ffs_infosgen_tiers_name'); ?> <span class="required">*</span></td>
                    <td class="fournisseur-zone right ">
                        <select id="creatiers-nom" class="quatrevingtpercent" name="creatiers-nom" data-addclass="<?php echo $fastfactsupplier->is_fielderror('creatiers-nom',$fastfactsupplier->errors); ?>" >
                            <option></option>
                            <?php echo $list_options_fournisseurs; ?>
                        </select>                                
                        <input type="text" name="creatiers-codefournisseur" id="creatiers-codefournisseur" value="<?php echo $thirdparty_suppliercode; ?>" style="opacity:0.6;" readonly />
                    </td>
                </tr> 
            </tbody>
            <tbody>

                <tr class="fastfact-thead dolpgs-thead left">
                    <th colspan="2"><?php echo $langs->transnoentities('ffs_infosgen_facture'); ?> <span class="txt-numfact">(<?php echo $specimen_next_number; ?>)</span></th>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php echo $langs->transnoentities('ffs_infosgen_facture_libelle'); ?></td>
                    <td class="right">
                        <input type="text" name="creafact-libelle" class="minwidth300" id="creafact-libelle" value="<?php echo $invoice_libelle; ?>" />
                    </td>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_infosgen_facture_ref'); ?> <span class="required">*</span></td>
                    <td class="right">
                        <input type="text" data-checkrefurl="<?php echo dol_buildpath('/fastfactsupplier/scripts/verif_reffourn.php',1); ?>" name="creafact-reffourn" id="creafact-reffourn" class="minwidth300 <?php echo $fastfactsupplier->is_fielderror('creafact-reffourn',$fastfactsupplier->errors); ?>" value="<?php echo $invoice_supplierref; ?>"  />
                    </td>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_infosgen_facture_date'); ?> <span class="required">*</span></td>
                    <td class="right">
                        <input type="text" name="creafact-datefact" id="creafact-datefact" value="<?php echo $invoice_date; ?>" class="datepick minwidth200 <?php echo $fastfactsupplier->is_fielderror('creafact-datefact',$fastfactsupplier->errors); ?>" />
                    </td>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_infosgen_facture_datelim'); ?> <span class="required">*</span></td>
                    <td class="right">
                        <input type="text" name="creafact-datelim" id="creafact-datelim" value="<?php echo $invoice_datelimit; ?>" class="datepick minwidth200 <?php echo $fastfactsupplier->is_fielderror('creafact-datelim',$fastfactsupplier->errors); ?>" />
                    </td>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_infosgen_facture_bankaccount'); ?></td>
                    <td class="right">
                        <?php $form->select_comptes(!empty($invoice_bank_account)?$invoice_bank_account:$fastfactsupplier->params['default_bankaccount'],'srff-bank-account',0,'',1,'',0,'minwidth300'); ?>
                    </td>
                </tr>
                <?php //endif; ?>
            </tbody>
            <tbody>

                <tr class="fastfact-thead dolpgs-thead left">
                    <th colspan="2"><?php echo $langs->trans('ffs_infosgen_actions'); ?></th>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_infosgen_actions_redirect'); ?></td>
                    <td class="right"><span class="redirect-active <?php if($gotoreg): echo 'show'; endif; ?>"><?php print $langs->transnoentities('ffs_infosgen_actions_redirect_active'); ?></span>
                        <input type="checkbox" id="creafact-redirect" name="creafact-redirect" <?php if($gotoreg): echo 'checked="checked"'; endif; ?>>
                    </td>
                </tr>
                <?php if (!empty($conf->projet->enabled)): ?>
                    <tr class="dolpgs-tbody">
                        <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_infosgen_actions_setproject'); ?></td>
                        <td class="right">
                            <?php $options_creafact_projet = $formproject->select_projects_list(-1,$selected = '','creafact-projet',$maxlength = '',$option_only = 1,$show_empty = 1,$discard_closed = 1,$forcefocus = 0,$disabled = 0,$mode = 1,$filterkey = '',$nooutput = 1,$forceaddid = 0,$morecss = '',$htmlid = '' );?>
                            <select id="creafact-projet" name="creafact-projet" class="minwidth300">
                                <option></option>
                                <?php foreach ($options_creafact_projet as $opt): if(!$opt['disabled']): ?>
                                    <option value="<?php echo $opt['key']; ?>" <?php if($opt['key'] == $invoice_project): echo 'selected'; endif; ?>><?php echo $opt['label']; ?></option>
                                <?php endif; endforeach; ?>
                            </select>                      
                      </td>
                </tr>
                <?php endif; ?>
            </tbody>

            <?php // EXTRAFIELDS FACTURE 
            if($fastfactsupplier->params['show_extrafields_facture'] && !empty($invoice_extrafields)): ?>
            <tbody>
                <?php // ?>
                <tr class="fastfact-thead dolpgs-thead left">
                    <th colspan="2"><?php echo $langs->transnoentities('ffs_infosgen_customfields'); ?></th>
                </tr>
                <?php foreach($invoice_extrafields as $key_extrafield => $value_extrafield): 

                    $type_extrafield = $extrafields->attributes[$facture->table_element]['type'][$key_extrafield];
                    $label_extrafield = $extrafields->attributes[$facture->table_element]['label'][$key_extrafield];
                    $required_extrafield = $extrafields->attributes[$facture->table_element]['required'][$key_extrafield];
                    $class_extrafield = $extrafields->attributes[$facture->table_element]['css'][$key_extrafield];
                    if(in_array('options_'.$key_extrafield, $fastfactsupplier->errors)): $class_extrafield .= ' ffs-fielderror'; endif;
                    ?>

                    <tr class="dolpgs-tbody type-<?php echo $type_extrafield; ?>">
                        <?php if($type_extrafield == 'text'): ?>
                            <td colspan="2">
                                <div class="dolpgs-font-medium"><?php echo $label_extrafield; echo $required_extrafield?' <span class="required">*</span>':''; ?></div>
                                <textarea id="options_<?php echo $key_extrafield; ?>" name="options_<?php echo $key_extrafield; ?>" class="flat fastfact-textarea <?php echo $class_extrafield; ?> type-<?php echo $type_extrafield; ?>"></textarea>
                            </td>
                        <?php else: 
                            if(in_array($type_extrafield, array('int','double','price'))): $value_extrafield = str_replace(',','.',$value_extrafield); endif; ?> 
                            <td class="dolpgs-font-medium"><?php echo $label_extrafield; echo $required_extrafield?' <span class="required">*</span>':''; ?></td>
                            <td class="right"><?php echo $extrafields->showInputField($key_extrafield,$value_extrafield,'','','',$class_extrafield,$facture->id,$facture->table_element); ?></td>
                        <?php endif; ?> 
                        
                    </tr>
                <?php endforeach; ?>

            </tbody>
            <?php endif; ?>            
        </table>

        <!-- LIGNES DE FACTURE -->
        <h3 class="dolpgs-table-title"><?php echo $langs->trans('ffs_details'); ?></h3>
        <table class="dolpgs-table fastfact-table" id="fastfact-tablelines">
            <tbody>
                <tr class="fastfact-thead dolpgs-thead noborderside ">
                    <th><?php echo $langs->trans('ffs_details_prodserv'); ?> <span class="required">*</span></th>
                    <?php if($fastfactsupplier->params['show_extrafields_factureline'] && !empty($invoice_extrafields_line)): // ON RECUPERE LES CHAMPS VISIBLES
                         foreach($invoice_extrafields_line as $key_extrafield): ?>
                            <th class="left">
                                <?php echo $extrafields->attributes[$facture_ligne->table_element]['label'][$key_extrafield]; 
                                if($extrafields->attributes[$facture_ligne->table_element]['required'][$key_extrafield] == 1): ?> <span class="required">*</span><?php endif; ?>
                            </th>
                        <?php endforeach;
                    endif; ?>
                    <th><?php echo $langs->trans('Quantity'); ?></th>
                    <th class="<?php if($fastfactsupplier->params['mode_amount'] == 'ttc'): echo 'fastfact-hidden'; endif; ?>"><?php echo $langs->transnoentities('ffs_details_amountht'); ?> <span class="required">*</span></th>
                    <th class="<?php if($fastfactsupplier->params['mode_amount'] == 'ht'): echo 'fastfact-hidden'; endif; ?>"><?php echo $langs->transnoentities('ffs_details_amountttc'); ?> <span class="required">*</span></th>
                    <th class="right"><?php echo $langs->transnoentities('ffs_details_amounttax'); ?> <span class="required">*</span></th>
                </tr>

                <?php // SI IL N'Y A PAS ENCORE DE LIGNES ?>
                <?php if($invoice_nblines == 0 && empty($invoice_lines)): ?>

                    <tr id="linefact-1" class="oddeven dolpgs-tbody linefact">
                        <td class="pgsz-optiontable-field pdxline">
                            <input type="hidden" name="infofact-saisie-1" id="infofact-saisie-1" value="">
                            <?php echo $fastfactsupplier->select_prodserv($tab_prodserv,1,''); ?>
                        </td>
                        <?php if($fastfactsupplier->params['show_extrafields_factureline'] && !empty($invoice_extrafields_line)):
                            foreach($invoice_extrafields_line as $key_extrafield):

                                $type_extrafield = $extrafields->attributes[$facture_ligne->table_element]['type'][$key_extrafield];

                                $class_extrafield = 'minwidth200'; 
                                if(in_array('options_'.$key_extrafield.'-1', $fastfactsupplier->errors)):  $class_extrafield .= ' ffs-fielderror'; endif;
                                if($key_extrafield == $fastfactsupplier->params['extra_lineproject']): $class_extrafield .= ' ffs-lineproject'; endif;
                                if(in_array($type_extrafield, array('select','sellist'))): $class_extrafield .= ' ffs-slct'; endif;
                               
                                ?>
                                <td class="left pgsz-optiontable-field">
                                    <?php echo $extrafields->showInputField($key_extrafield,'','','-1','',trim($class_extrafield),$facture->id,$facture_ligne->table_element); ?>
                                </td>

                            <?php endforeach; ?>
                        <?php endif; ?>
                        <td class="pgsz-optiontable-field"><input type="number" step="any" name="infofact-qty-1" id="infofact-qty-1" class="calc-qty" data-linenum="1" value="1"></td>
                        <td class="pgsz-optiontable-field <?php if($fastfactsupplier->params['mode_amount'] == 'ttc'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantht-1" id="infofact-montantht-1" class="calc-amount" value="" data-mode="ht" data-linenum="1"/></td>
                        <td class="pgsz-optiontable-field <?php if($fastfactsupplier->params['mode_amount'] == 'ht'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantttc-1" id="infofact-montantttc-1" class="calc-amount" value="" data-mode="ttc" data-linenum="1"/></td>
                        <td class="pgsz-optiontable-field right">
                            <?php if(!empty($vat_rates)): echo $form->selectarray('infofact-tva-1',$vat_rates,$fastfactsupplier->params['default_tva'],0,0,0,'data-linenum="1"',0,0,0,'','minwidth100 calc-tva');
                            else: echo $langs->transnoentities('ffs_noVAT');
                            endif; ?>                                
                        </td>                        
                    </tr>

                <?php else: foreach($invoice_lines as $line): ?>

                    <tr id="linefact-<?php echo $line['line_num']; ?>" class="oddeven dolpgs-tbody linefact">
                        <td class="pgsz-optiontable-field pdxline">
                            <input type="hidden" name="infofact-saisie-<?php echo $line['line_num']; ?>" id="infofact-saisie-<?php echo $line['line_num']; ?>" value="<?php echo $line['type_saisie']; ?>" >
                            <?php echo $fastfactsupplier->select_prodserv($tab_prodserv,$line['line_num'],$line['prodserv']); ?>
                        </td>
                        <?php if($fastfactsupplier->params['show_extrafields_factureline'] && !empty($invoice_extrafields_line)): 
                            foreach($invoice_extrafields_line as $key_extrafield): 

                                $value_extrafield = $line['extrafields'][$key_extrafield];
                                $type_extrafield = $extrafields->attributes[$facture_ligne->table_element]['type'][$key_extrafield];
                                $class_extrafield = 'minwidth200'; 
                                if(in_array('options_'.$key_extrafield.'-'.$line['line_num'], $fastfactsupplier->errors)):  $class_extrafield .= ' ffs-fielderror'; endif;
                                if($key_extrafield == $fastfactsupplier->params['extra_lineproject']): $class_extrafield .= ' ffs-lineproject'; endif;
                                if(in_array($type_extrafield, array('select','sellist'))): $class_extrafield .= ' ffs-slct'; endif;                               
                                ?>
                                <td class="left pgsz-optiontable-field"><?php echo $extrafields->showInputField($key_extrafield,$value_extrafield,'','-'.$line['line_num'],'',trim($class_extrafield),$facture->id,$facture_ligne->table_element); ?></td>

                            <?php endforeach; ?>
                        <?php endif; ?>
                        <td class="pgsz-optiontable-field"><input type="number" step="any" name="infofact-qty-<?php echo $line['line_num']; ?>" id="infofact-qty-<?php echo $line['line_num']; ?>" class="calc-qty" data-linenum="<?php echo $line['line_num']; ?>" value="<?php echo $line['qty']; ?>"></td>
                        <td class="pgsz-optiontable-field <?php if($fastfactsupplier->params['mode_amount'] == 'ttc'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantht-<?php echo $line['line_num']; ?>" id="infofact-montantht-<?php echo $line['line_num']; ?>" class="calc-amount <?php echo $fastfactsupplier->is_fielderror('infofact-montantht-'.$line['line_num'],$fastfactsupplier->errors); ?>" value="<?php echo $line['montant_ligne_ht']; ?>" data-mode="ht" data-linenum="<?php echo $line['line_num']; ?>" /></td>
                        <td class="pgsz-optiontable-field <?php if($fastfactsupplier->params['mode_amount'] == 'ht'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantttc-<?php echo $line['line_num']; ?>" id="infofact-montantttc-<?php echo $line['line_num']; ?>" class="calc-amount <?php echo $fastfactsupplier->is_fielderror('infofact-montantttc-'.$line['line_num'],$fastfactsupplier->errors); ?>" value="<?php echo $line['montant_ligne_ttc']; ?>" data-mode="ttc" data-linenum="<?php echo $line['line_num']; ?>" /></td>    
                        <td class="right">
                            <?php if(!empty($vat_rates)): 
                                echo $form->selectarray('infofact-tva-'.$line['line_num'],$vat_rates,$line['taux_tva'],0,0,0,'data-linenum="'.$line['line_num'].'"',0,0,0,'','minwidth100 calc-tva');
                            else: echo $langs->transnoentities('ffs_noVAT'); endif; ?> 
                         </td>                            
                    </tr>
                <?php endforeach; endif; ?>                
        </table>

        <!-- BOUTONS ET TOTAL LIGNE DE FACTURE -->
        <table class="dolpgs-table fastfact-table dolpgs-nohovertable">
            <tbody>
                <tr class="dolpgs-tbody nopadding withoutborder">
                    <td valign="top">
                        <input type="button" value="<?php echo $langs->transnoentities('ffs_details_addline'); ?>" id="add-facture-line" class="dolpgs-btn btn-primary btn-sm" data-addurl="<?php echo dol_buildpath('/fastfactsupplier/scripts/add_line.php',1); ?>" />
                        <?php if(isset($invoice_lines) && count($invoice_lines) > 1): $display = 'inline-block'; else: $display = 'none'; endif; ?>
                        <input type="button" value="<?php echo $langs->transnoentities('ffs_details_delline'); ?>" id="del-facture-line" style="display:<?php echo $display; ?>;" class="dolpgs-btn btn-danger btn-sm" />
                    </td>
                    <td valign="top" class="right">
                        <div id="ht-amount" class="ffs-bold"><?php echo $langs->transnoentities('ffs_tabletotal_ht'); ?>: <span class="ff-amount"><span class="calcul-zone-ht"><?php echo number_format($calcul_ht,2); ?></span> <?php echo $langs->getCurrencySymbol($conf->currency); ?></span></div>
                        <div id="tva-amount" class="ffs-bold"><?php echo $langs->transnoentities('ffs_tabletotal_tax'); ?>: <span class="ff-amount"><span class="calcul-zone-tva"><?php echo number_format($calcul_tva,2); ?></span> <?php echo $langs->getCurrencySymbol($conf->currency); ?></span></div>
                        <div id="ttc-amount" class="ffs-bold"><?php echo $langs->transnoentities('ffs_tabletotal_ttc'); ?>: <span class="ff-amount"><span class="calcul-zone-ttc"><?php echo number_format($calcul_ttc,2); ?></span> <?php echo $langs->getCurrencySymbol($conf->currency); ?></span></div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- DOCS -->
        <h3 class="dolpgs-table-title"><?php echo $langs->trans('ffs_docs'); ?></h3>
        <table class="dolpgs-table fastfact-table" style="border-top:none;">
            
            <tbody>                
                <?php if(getDolGlobalInt('MAIN_UPLOAD_DOC')): ?>
                <tr class="fastfact-thead dolpgs-thead">
                    <th colspan="2"><?php print $langs->transnoentities('ffs_docs_uploadfile'); ?></th>
                </tr>
                <tr class="dolpgs-tbody nopadding fastfact-drop">
                    <td class="ffs-nopadding" colspan="2">
                        <div id="zone-drop" data-maxsize="<?php echo $maxfilesize; ?>">
                            <div id="zone-drop-infos"><?php echo $langs->transnoentities('ffs_docs_dragndrop',$maxfilesize_mo); ?></div>
                            <input type="file" name="creafact-file" id="creafact-file"  />
                            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $maxfilesize; ?>" />
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <tr class="liste_titre fastfact-thead dolpgs-thead">
                    <th colspan="2"><?php print $langs->transnoentities('ffs_docs_link'); ?></th>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_docs_linkurl'); ?> <span class="required">*</span></td>
                    <td class="right">
                        <input type="url" name="creafact-linkurl" id="creafact-linkurl" placeholder="https://example.com" pattern="https://.*" value="<?php echo $file_link_url; ?>">
                    </td>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_docs_linklabel'); ?> </td>
                    <td class="right">
                        <input type="text" name="creafact-linklib" placeholder="<?php print $langs->transnoentities('ffs_docs_linklib'); ?>" value="<?php echo $file_link_label; ?>">
                    </td>
                </tr>
                
            </tbody>
        </table>

        <div id="" style="text-align: right; margin-top: 16px;">                
            <a class="dolpgs-btn btn-danger btn-sm" href="<?php echo $_SERVER['PHP_SELF']; ?>"><?php echo $langs->transnoentities('ffs_cancel'); ?></a>
            <input type="submit" class="dolpgs-btn btn-primary btn-sm" value="<?php print $langs->transnoentities('ffs_save_invoice'); ?>">
        </div>
    </form>
    <?php endif; ?>

</div>

<?php

// End of page
llxFooter();
$db->close();

?>