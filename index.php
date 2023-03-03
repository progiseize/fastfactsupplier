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
if($user->societe_id > 0): accessforbidden(); endif;
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

/*******************************************************************
* FONCTIONS
********************************************************************/
dol_include_once('./fastfactsupplier/lib/functions.lib.php');


/*******************************************************************
* CONFIGURATION
********************************************************************/


$use_server_list = $conf->global->SRFF_USESERVERLIST;
$cats_to_use = json_decode($conf->global->SRFF_CATS);
$prodservs = explode(',',$conf->global->SRFF_SERVERLIST);
$gotoreg = $conf->global->SRFF_GOTOREG;
$show_extrafields_facture = $conf->global->SRFF_SHOWEXTRAFACT;
$show_extrafields_factureline = $conf->global->SRFF_SHOWEXTRAFACTLINE;
$usecustomfield_uploadfile = $conf->global->SRFF_USECUSTOMFIELD_UPLOADFILE;

/*******************************************************************
* VARIABLES
********************************************************************/

// On récupère l'url du module
$new_script_file = str_replace('index.php', '', $_SERVER["PHP_SELF"]);

// ON INSTANCIE LES CLASSES
$facture = new FactureFournisseur($db);
$facture_ligne = new SupplierInvoiceLine($db);
$societe = new Societe($db);
$form = new Form($db);
if(!empty($conf->projet->enabled)): $formproject = new FormProjets($db); endif;
if($version[0] <= 10): $extrafields = new ExtraFields($db); endif;

// On calcule le prochain code fournisseur
$societe->get_codefournisseur($societe,1);

$form->load_cache_vatrates("'".$mysoc->country_code."'");
$vat_rates = array();
foreach($form->cache_vatrates as $vat):
    $vat_rates[$vat['txtva']] = $vat['label'];
endforeach;

// On recupère la liste des services
if($use_server_list): $tab_prodserv = ffs_getListProdServ($cats_to_use);
else : $tab_prodserv = $prodservs; endif;

// On crée la liste des fournisseurs
$list_options_fournisseurs = ffs_getListFourn(GETPOST('creatiers-nom', 'alpha'),$societe->code_fournisseur);

// On détermine la taille maximum des fichiers en upload
$maxfilesize = file_upload_max_size();
$maxfilesize_ko = $maxfilesize / 1024;
$maxfilesize_mo = $maxfilesize_ko / 1024;

// On definit la date d'aujourd'hui
$today = date('d/m/Y');

// SI REDIRECTION ACTIVEE
$redirect = GETPOST('creafact-redirect'); if($redirect): $gotoreg = $redirect; endif;

// Gestion des erreurs & securite
$errmsg = array();
$input_errors = array();
$token = GETPOST('token');

// Déclaration de variables utiles
$nb_lines = 0;
$calcul_ht = 0;
$calcul_tva = 0;
$calcul_ttc = 0;

// On récupère l'action à traiter
$action = GETPOST('action');

// ON RECUPERE LES LABELS DES EXTRAFIELDS $FACTURE (FOURNISSEUR)
if($show_extrafields_facture): $extralabels_facture = $extrafields->fetch_name_optionals_label($facture->table_element); endif;
if($show_extrafields_factureline): $extralabels_factureligne = $extrafields->fetch_name_optionals_label($facture_ligne->table_element); endif; // LIGNES FACTURE
$extraf_visibletab = array('1','3'); 

//var_dump($extralabels_facture);
//var_dump($extralabels_factureligne);

/*******************************************************************
* ACTIONS
********************************************************************/

if ($action == 'create' && $token == $_SESSION['token']):

    $db->begin();

    // ON RECUPERE LES INFOS DU FICHIER S'IL Y EN A
    $file = $_FILES['creafact-file'];

    // ON VERIFIE LES VARIABLES
    if (!GETPOST("creatiers-nom")): $error++; array_push($input_errors, 'creatiers-nom'); array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_tiersname'))); endif;
    if (!GETPOST("creatiers-codefournisseur")): $error++; array_push($input_errors, 'creatiers-codefournisseur'); array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_codefourn'))); endif;
    if (!GETPOST("creafact-reffourn")): $error++; array_push($input_errors, 'creafact-reffourn'); array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_reffourn'))); endif;
    if (!GETPOST("creafact-datefact")): $error++; array_push($input_errors, 'creafact-datefact'); array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_date'))); endif;
    if (!GETPOST("creafact-datelim")): $error++; array_push($input_errors, 'creafact-datelim'); array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_datelim'))); endif;
    if (GETPOST("creafact-datefact") && GETPOST("creafact-datelim")):
        $diff_date = GETPOST("creafact-datefact") - GETPOST("creafact-datelim"); 
        if ($diff_date > 0): array_push($input_errors, 'creafact-datelim'); array_push($errmsg, $langs->transnoentities('ffs_fielderror_date_limsupdate'));endif;
    endif;

    // ON VERIFIE LES EXTRAFIELDS DE FACTURES SI ACTIVE
    if($show_extrafields_facture):
        foreach($extralabels_facture as $key_exf => $exf):

            // ON VERIFIE SI LES CHAMPS OBLIG. SONT VIDES
            if(in_array($extrafields->attribute_list[$key_exf], $extraf_visibletab) && $extrafields->attribute_required[$key_exf] == '1' && empty(GETPOST('options_'.$key_exf))):
                $error++; array_push($input_errors,'options_'.$key_exf); array_push($errmsg , $langs->transnoentities("ErrorFieldRequired",$extrafields->attribute_label[$key_exf]));
            endif;

            // ON VERIFIE LES VALEURS EN FONCTIONS DES TYPES
            if(!empty(GETPOST('options_'.$key_exf))):
                switch($extrafields->attribute_type[$key_exf]):

                    case 'varchar': break;
                    case 'text': break;
                    case 'date': break;
                    case 'boolean': break;

                    // TELEPHONE
                    case 'phone': 
                        if(!is_numeric(GETPOST('options_'.$key_exf))): 
                            $error++;
                            array_push($input_errors,'options_'.$key_exf);
                            array_push($errmsg,$langs->transnoentities('ffs_fielderror_noletter',$extrafields->attribute_label[$key_exf]));
                        endif;
                    break;

                    // VALEUR ENTIERE, ON RETIRE SI CHIFFRES APRES LA VIRGULE
                    case 'int':
                        if(!filter_var(GETPOST('options_'.$key_exf), FILTER_VALIDATE_INT)):
                            $error++;
                            array_push($input_errors,'options_'.$key_exf);
                            array_push($errmsg,$langs->transnoentities('ffs_fielderror_needint',$extrafields->attribute_label[$key_exf]));
                        endif;
                    break;

                    // VALEURS DECIMALES
                    case 'double': case 'price': 
                        $float_post = str_replace(',','.',GETPOST('options_'.$key_exf));
                        if(!is_numeric($float_post)):
                            $error++;
                            array_push($input_errors,'options_'.$key_exf);
                            array_push($errmsg,$langs->transnoentities('ffs_fielderror_needfloat',$extrafields->attribute_label[$key_exf]));
                        else: $_POST['options_'.$key_exf] = $float_post;
                        endif;
                    break;
                endswitch;
            endif;
        endforeach;
    endif;

    // ON RECUPERE LE MODE DE CALCUL
    $mode_calcul = GETPOST('ffs_amout_mode');    

    // ON ENREGISTRE LES LIGNES DE FACTURE DANS UN TABLEAU
    $nb_lines = GETPOST('infofact-linenumber');
    $facture_lines = array();

    // POUR CHAQUE LIGNE DE FACTURE
    for ($i=1; $i <= $nb_lines; $i++):

        $calcul_line = true;

        $ligne_tva_taux = GETPOST('infofact-tva-'.$i,'int');
        $typesaisie = GETPOST('infofact-saisie-'.$i);

        // VERIFICATION DES CHAMPS
        if (!GETPOST('infofact-prodserv-'.$i)): $error++; array_push($input_errors, 'infofact-prodserv-'.$i); array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_prodserv').' '.$i)); endif;
        
        // EN FCTION DU MODE DE CALCUL
        switch ($typesaisie):
            case '':
                    $error++; $calcul_line = false;
                    array_push($input_errors, 'infofact-montantht-'.$i); 
                    array_push($input_errors, 'infofact-montantttc-'.$i); 
                    array_push($errmsg,$langs->transnoentities('ffs_fielderror_needhtorttc', $i));
            break;
            case 'ht':
                if (!GETPOST('infofact-montantht-'.$i) || empty(GETPOST('infofact-montantht-'.$i))): 
                    $error++; $calcul_line = false;
                    array_push($input_errors, 'infofact-montantht-'.$i); 
                    array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_amount').' '.$i));
                else:
                    $post_montant = str_replace(',', '.', GETPOST('infofact-montantht-'.$i));
                    if (!is_numeric($post_montant)): $error++; $calcul_line = false;array_push($input_errors, 'infofact-montantht-'.$i); array_push($errmsg,$langs->transnoentities("ErrorFieldFormat", $langs->transnoentities('ffs_field_amount').' '.$i)); endif;
                endif;                
            break;
            case 'ttc':
                if (!GETPOST('infofact-montantttc-'.$i) || empty(GETPOST('infofact-montantttc-'.$i))): 
                    $error++; $calcul_line = false;
                    array_push($input_errors, 'infofact-montantttc-'.$i); 
                    array_push($errmsg,$langs->transnoentities("ErrorFieldRequired", $langs->transnoentities('ffs_field_amount').' '.$i));
                else:
                    $post_montant = str_replace(',', '.', GETPOST('infofact-montantttc-'.$i));
                    if (!is_numeric($post_montant)): $error++; $calcul_line = false; array_push($input_errors, 'infofact-montantttc-'.$i); array_push($errmsg,$langs->transnoentities("ErrorFieldFormat", $langs->transnoentities('ffs_field_amount').' '.$i)); endif;
                endif;
            break;
            
        endswitch;

        // ON RECALCULE LES MONTANTS 
        if($calcul_line):
            switch ($typesaisie):
                case 'ht':
                    $ligne_ht_montant = floatval($post_montant);
                    $ligne_tva_montant = (floatval($post_montant)/100) * floatval($ligne_tva_taux);
                    $calcul_ht += $ligne_ht_montant;  $calcul_tva += $ligne_tva_montant;  $calcul_ttc += $ligne_ht_montant + $ligne_tva_montant;
                break;
                case 'ttc':
                    $ligne_ttc_montant = floatval($post_montant);
                    $ligne_ht_montant = $ligne_ttc_montant / (1 + floatval($ligne_tva_taux) / 100);
                    $calcul_ht += $ligne_ht_montant; $calcul_tva += $ligne_ttc_montant - $ligne_ht_montant; $calcul_ttc += $ligne_ttc_montant;
                break;
            endswitch;                        
        endif;

        // ON ENREGISTRE LA LIGNE DANS UN TABLEAU POUR LA REMETTRE DANS LE FORMULAIRE
        $fact_line = array(
            'label' => GETPOST('infofact-prodserv-'.$i),
            'type_saisie' => GETPOST('infofact-saisie-'.$i),
            'montant_ligne_ht' => GETPOST('infofact-montantht-'.$i),
            'montant_ligne_ttc' => GETPOST('infofact-montantttc-'.$i),
            'taux_tva' => $ligne_tva_taux);
        array_push($facture_lines, $fact_line);

        // ON VERIFIE LES EXTRAFIELDS DES LIGNES
        if($show_extrafields_factureline):

            foreach($extralabels_factureligne as $key_exfl => $exfl):

                // ON VERIFIE SI LES CHAMPS OBLIG. SONT VIDES
                if(in_array($extrafields->attribute_list[$key_exfl], $extraf_visibletab) && $extrafields->attribute_required[$key_exfl] == '1' && empty(GETPOST('options_'.$key_exfl.'-'.$i))):
                    $error++;
                    array_push($input_errors,'options_'.$key_exfl.'-'.$i);
                    array_push($errmsg, $langs->transnoentities("ErrorFieldRequired",$extrafields->attribute_label[$key_exfl].' '.$i));
                endif;

                // ON VERIFIE LES VALEURS EN FONCTIONS DES TYPES
                if(!empty(GETPOST('options_'.$key_exfl.'-'.$i))):
                    switch($extrafields->attribute_type[$key_exfl]):

                        case 'varchar': break;
                        case 'text': break;
                        case 'date': break;
                        case 'boolean': break;

                        // TELEPHONE
                        case 'phone': 
                            if(!is_numeric(GETPOST('options_'.$key_exfl.'-'.$i))): 
                                $error++;
                                array_push($input_errors,'options_'.$key_exfl.'-'.$i);
                                array_push($errmsg,$langs->transnoentities('ffs_fielderror_noletter',$extrafields->attribute_label[$key_exfl]));
                            endif;
                        break;

                        // VALEUR ENTIERE, ON RETIRE SI CHIFFRES APRES LA VIRGULE
                        case 'int':
                            if(!filter_var(GETPOST('options_'.$key_exfl.'-'.$i), FILTER_VALIDATE_INT)):
                                $error++;
                                array_push($input_errors,'options_'.$key_exfl.'-'.$i);
                                array_push($errmsg,$langs->transnoentities('ffs_fielderror_needint',$extrafields->attribute_label[$key_exfl]));
                            endif;
                        break;

                        // VALEURS DECIMALES
                        case 'double': case 'price': 
                            $float_post = str_replace(',','.',GETPOST('options_'.$key_exfl.'-'.$i));
                            if(!is_numeric($float_post)):
                                $error++;
                                array_push($input_errors,'options_'.$key_exfl.'-'.$i);
                                array_push($errmsg,$langs->transnoentities('ffs_fielderror_needfloat',$extrafields->attribute_label[$key_exfl]));
                            else: $_POST['options_'.$key_exfl.'-'.$i] = $float_post;
                            endif;
                        break;

                    endswitch;
                endif;
            endforeach;
        endif;
    endfor;

    if(!$error):

        // ON VERIFIE SI LE TIERS A BESOIN D'ETRE CRÉÉ
        $is_already = GETPOST('is-already');

        if(empty(GETPOST('fournid'))): $check_tiers = $societe->fetch('',GETPOST('creatiers-nom', 'alpha'));
        else: $check_tiers = $societe->fetch(GETPOST('fournid', 'int'));
        endif;

        /**********************************************************/
        /* TIERS*/
        /**********************************************************/
        // SI LE TIERS EXISTE
        if($check_tiers > 0):
            $tiers_rowid = GETPOST('fournid', 'int');
            $societe->fetch($societe->id);

        // SI LE TIERS N'EXISTE PAS
        elseif($check_tiers == 0):
            $societe->nom = GETPOST('creatiers-nom', 'alpha');
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

        if (GETPOST("creafact-reffourn")):
            $facture_reffourn = GETPOST("creafact-reffourn");
            $sql_checkref = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture_fourn WHERE ref_supplier = '$facture_reffourn' AND fk_soc = '$societe->id'";
            $result_checkref = $db->query($sql_checkref);$count_checkref = $db->num_rows($result_checkref);
            if($count_checkref > 0):$error++; array_push($input_errors, 'creafact-reffourn'); array_push($errmsg,$langs->transnoentities('ffs_fielderror_reffourn_exist'));endif;
        endif;

        if(!$error):

            $date_facturation_tmp = explode('/', GETPOST('creafact-datefact'));
            $date_facturation = mktime('0','0','1',$date_facturation_tmp[1],$date_facturation_tmp[0],$date_facturation_tmp[2]);
            $date_limit_tmp = explode('/', GETPOST('creafact-datelim'));
            $date_limit = mktime('0','0','1',$date_limit_tmp[1],$date_limit_tmp[0],$date_limit_tmp[2]);

            // 

            $facture->socid = $societe->id;
            $facture->ref = $facture->getNextNumRef($societe);
            $facture->ref_supplier = $facture_reffourn;
            $facture->libelle = GETPOST('creafact-libelle','alpha');
            $facture->date = $date_facturation;
            $facture->date_echeance = $date_limit;
            $facture->entity = $conf->entity;

            $facture->cond_reglement_id = $societe->cond_reglement_supplier_id;
            $facture->mode_reglement_id = $societe->mode_reglement_supplier_id;
            
            // ON AJOUTE LES EXTRAFIELDS A LA FACTURE
            if($show_extrafields_facture):
                $facture->array_options = array();
                foreach($extralabels_facture as $key_exf => $exf):
                    if(GETPOSTISSET('options_'.$key_exf)):
                        $facture->array_options['options_'.$key_exf] = GETPOST('options_'.$key_exf);
                    endif;
                endforeach;
            endif;      

            $facture_id = $facture->create($user); if($facture_id < 0): $error++; endif;
            
            $lines_ok = 0;

            // POUR CHAQUE LIGNE DE FACTURE
            for ($i=1; $i <= $nb_lines; $i++):

                // ON FAIT LES CALCULS DE BASE
                switch($mode_calcul):
                    case 'ht':
                        $montant_ligne = floatval(str_replace(',','.',GETPOST('infofact-montantht-'.$i)));
                        $typesaisie_ligne = $mode_calcul;
                    break;
                    case 'ttc':
                        $montant_ligne = floatval(str_replace(',','.',GETPOST('infofact-montantttc-'.$i)));
                        $typesaisie_ligne = $mode_calcul;
                    break;
                    case 'both':
                        $typesaisie_ligne = GETPOST('infofact-saisie-'.$i);
                        switch($typesaisie_ligne):
                            case 'ht': $montant_ligne = floatval(str_replace(',','.',GETPOST('infofact-montantht-'.$i))); break;
                            case 'ttc': $montant_ligne = floatval(str_replace(',','.',GETPOST('infofact-montantttc-'.$i))); break;
                        endswitch;
                    break;
                endswitch;

                $creafact_tva = number_format(floatval(GETPOST('infofact-tva-'.$i)),3,'.','');

                if($use_server_list): $creafact_fk_product = GETPOST('infofact-prodserv-'.$i); $creafact_description = '';
                else: $creafact_fk_product = 0; $creafact_description = GETPOST('infofact-prodserv-'.$i);
                endif;

                // ON AJOUTE UNE LIGNE DE FACTURE
                if($id_ligne = $facture->addline($creafact_description,$montant_ligne,$tva = $creafact_tva,$txlocaltax1 = 0,$txlocaltax2 = 0,$qty = 1,$creafact_fk_product,$remise_percent = 0,$date_start = '',$date_end = '',$ventil = 0,$info_bits = '',strtoupper($typesaisie_ligne),$type = 1,$rang = -1,$notrigger = false,'',$fk_unit = null,$origin_id = 0,$pu_ht_devise = 0)):
                    $lines_ok++;

                    /********************/
                    $facture_ligne->fetch($id_ligne);

                    if($show_extrafields_factureline):
                        foreach($extralabels_factureligne as $key_exfl => $exfl):
                            if(GETPOSTISSET('options_'.$key_exfl.'-'.$i)):
                                $facture_ligne->array_options['options_'.$key_exfl] = GETPOST('options_'.$key_exfl.'-'.$i);
                            endif;
                        endforeach;
                        if(!empty($facture_ligne->array_options)):
                            if(!$facture_ligne->insertExtraFields()): $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_saveextraslines')); endif;
                        endif;
                    endif;



                // SI IL Y A UNE ERREUR
                else: $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_saveline',$i));
                endif;

            endfor;

            if($nb_lines == $lines_ok):                
                $valid_facture = $facture->validate($user); if($valid_facture < 0): $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_validateinvoice')); endif;
            endif;
        
            // ON AJOUTE LE DOCUMENT LIE SI IL Y EN A UN
            $link_url = GETPOST('creafact-linkurl');                                
            if(!empty($link_url)):

                // ON RECUPERE LE LIBELLE DU DOC, SI IL N'Y EN A PAS ON LE CREE
                $link_lib = GETPOST('creafact-linklib');
                if(empty($link_lib)): $link_lib = 'linkfile_'.date('Ymd').'_'.date('His'); endif;

                $sql = "INSERT INTO ".MAIN_DB_PREFIX."links (url,objecttype,objectid,datea,label)";
                $sql .= " VALUES ('".$link_url."','invoice_supplier','".$facture_id."','".date('Y-m-d H:i:s')."','".$link_lib."')";

                if(!$db->query($sql)): $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_linkfile')); 
                else: if($usecustomfield_uploadfile): $facture->array_options['options_ffs_uploadfile'] = 1; endif;
                endif;

            endif;

            if(isset($_FILES) && !empty($_FILES['creafact-file']['name'])):

                // ON DEFINIT LES REPERTOIRES DES FICHIERS
                $upload_dir = $conf->fournisseur->facture->dir_output.'/'.get_exdir($facture_id,2,0,0,$facture,'invoice_supplier').$facture->ref .'/';

                // ON CREE LES REPERTOIRES (S'ILS N'EXISTENT PAS )
                if(!is_dir($upload_dir)): mkdir($upload_dir, 0777, true); endif;

                $upload_file = $upload_dir .$facture->ref.'-'.basename($_FILES['creafact-file']['name']);

                if(!move_uploaded_file($_FILES['creafact-file']['tmp_name'], $upload_file)): $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_notuploadfile'));
                else: if($usecustomfield_uploadfile): $facture->array_options['options_ffs_uploadfile'] = 1; endif;
                endif;

            endif;
        endif;

    endif;

    // ENREGISTREMENTS EXTRAFIELDS FACTURE
    if($show_extrafields_facture && !$error || $usecustomfield_uploadfile && !$error ): 

        if(!empty($facture->array_options)):
            if(!$facture->insertExtraFields()): $error++; array_push($errmsg,$langs->transnoentities('ffs_fielderror_saveextras')); endif;
        endif;
    endif;

    // BANK ACCOUNT
    if($conf->global->SRFF_BANKACCOUNT != '-1' ): 
        if(GETPOST('srff-bank-account') != '-1'): $facture->setBankAccount(GETPOST('srff-bank-account'),false,$user); endif;
    endif;

    // PROJETS
    if (!empty($conf->projet->enabled) && GETPOSTISSET('creafact-projet') && !empty(GETPOST('creafact-projet') && !$error)):
        $facture->setProject(GETPOST('creafact-projet'));
    endif;

    // ON AJOUTE UNE ERREUR POUR LE DEV
    //$error++;

    if (!$error): 
        $db->commit();
        if($redirect): header('Location:'.DOL_MAIN_URL_ROOT.'/fourn/facture/paiement.php?facid='.$facture->id.'&action=create'); exit(); 
        else:
            setEventMessages($langs->transnoentities('ffs_confirm_save_invoice',$facture->ref), null, 'mesgs');
            unset($_POST); $facture_lines = array(); $nb_lines = 0; $societe->get_codefournisseur($societe,1); $calcul_ht = 0; $calcul_tva = 0; $calcul_ttc = 0;
        endif;
    else: 
        $db->rollback();
        setEventMessages('',$errmsg, 'errors');
    endif;
endif;


/*********************************/
 // ON DETERMINE LES VARIABLES DU FORMULAIRE AVANT AFFICHAGE
$code_fournisseur = GETPOST("creatiers-codefournisseur"); if(empty($code_fournisseur)): $code_fournisseur = $societe->code_fournisseur; endif;
$date_facturation = GETPOST("creafact-datefact"); if(empty($date_facturation)): $date_facturation = $today; endif;
$date_limit = GETPOST("creafact-datelim"); if(empty($date_limit)): $date_limit = $today; endif;

/***************************************************
* VIEW
****************************************************/

llxHeader('',$langs->transnoentities('ffs_page_title'),'','','','',array("/fastfactsupplier/js/jquery-ui.min.js","/fastfactsupplier/js/fastfactsupplier.js"),array("/fastfactsupplier/css/fastfactsupplier.css"),'','fastfactsupplier saisie'); ?>

<!-- CONTENEUR GENERAL -->
<div class="dolpgs-main-wrapper fastfact">

    <input type="hidden" id="fastfact-lang" value="<?php echo $langs->defaultlang; ?>">

    <?php if(in_array('progiseize', $conf->modules)): ?>
        <h1 class="has-before"><?php echo $langs->transnoentities('ffs_page_title'); ?></h1>
    <?php else : ?>
        <table class="centpercent notopnoleftnoright table-fiche-title"><tbody><tr class="titre"><td class="nobordernopadding widthpictotitle valignmiddle col-picto"><span class="fas fa-file-invoice-dollar valignmiddle widthpictotitle pictotitle" style=""></span></td><td class="nobordernopadding valignmiddle col-title"><div class="titre inline-block"><?php echo $langs->transnoentities('ffs_page_title'); ?></div></td></tr></tbody></table>
    <?php endif; ?>
    <?php $head = ffsAdminPrepareHead(); dol_fiche_head($head, 'saisir','FastFactSupplier', 0,'fa-file-invoice-dollar_file-invoice-dollar_fas'); ?>

    <?php if(!in_array('progiseize', $conf->modules)): ?>
        <div class="alert-message-need-base">
            <i class="fas fa-info-circle" style="margin-right:5px;"></i> 
            Cette version nécéssite le module PROGISEIZE pour fonctionner correctement. Vous pouvez la télécharger depuis Github en cliquant sur ce lien : <a href="https://github.com/progiseize/progiseize" target="_blank">Module Progiseize Github</a>
        </div>
    <?php endif; ?>

    <?php if($user->rights->fastfactsupplier->saisir): ?>
    <form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>" method="POST">

        <input type="hidden" name="action" value="create">
        <input type="hidden" name="new_reffourn" id="new_reffourn" value="<?php echo $societe->code_fournisseur; ?>">
        <input type="hidden" name="is-already" id="is-already" value="0">
        <input type="hidden" name="fournid" id="fournid" value="">
        <input type="hidden" name="token" id="token" value="<?php echo newtoken(); ?>">
        <input type="hidden" name="infofact-linenumber" id="infofact-linenumber" value="<?php if($nb_lines == 0): echo '1'; else: echo $nb_lines; endif; ?>">
        <input type="hidden" name="ffs_amout_mode" value="<?php echo $conf->global->SRFF_AMOUNT_MODE; ?>">

        <!-- INFOS FACTURE -->
        <h3 class="dolpgs-table-title"><?php echo $langs->trans('ffs_infosgen'); ?></h3>
        <table class="dolpgs-table fastfact-table">
            <tbody>
                <tr class="dolpgs-thead left">
                    <th colspan="2"><?php echo $langs->trans('ffs_infosgen_tiers'); ?></th>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php echo $langs->transnoentities('ffs_infosgen_tiers_name'); ?> <span class="required">*</span></td>
                    <td class="fournisseur-zone right ">
                        <select id="creatiers-nom" class="quatrevingtpercent" name="creatiers-nom" data-addclass="<?php echo is_fielderror('creatiers-nom',$input_errors); ?>" data-numfacturl="<?php echo $new_script_file.'scripts/numfact.php'; ?>">
                            <option></option>
                            <?php echo $list_options_fournisseurs; ?>
                        </select>                                
                        <input type="text" name="creatiers-codefournisseur" id="creatiers-codefournisseur" value="<?php echo $code_fournisseur; ?>" style="opacity:0.6;" readonly />
                    </td>
                </tr> 
            </tbody>
            <tbody>

                <tr class="dolpgs-thead left">
                    <th colspan="2"><?php echo $langs->transnoentities('ffs_infosgen_facture'); ?> <span class="txt-numfact"></span></th>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php echo $langs->transnoentities('ffs_infosgen_facture_libelle'); ?></td>
                    <td class="right">
                        <?php $post_libelle = GETPOST('creafact-libelle','alpha'); ?>
                        <input type="text" name="creafact-libelle" id="creafact-libelle" value="<?php if (!empty($post_libelle)): echo $post_libelle; endif; ?>" />
                    </td>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_infosgen_facture_ref'); ?> <span class="required">*</span></td>
                    <td class="right">
                        <?php $post_reffourn = GETPOST('creafact-reffourn','alpha'); ?>
                        <input type="text" data-checkrefurl="<?php echo $new_script_file.'scripts/verif_reffourn.php'; ?>" name="creafact-reffourn" id="creafact-reffourn" class="<?php echo is_fielderror('creafact-reffourn',$input_errors); ?>" value="<?php if (!empty($post_reffourn)): echo $post_reffourn; endif; ?>"  />
                    </td>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_infosgen_facture_date'); ?> <span class="required">*</span></td>
                    <td class="right">
                        <input type="text" name="creafact-datefact" id="creafact-datefact" value="<?php echo $date_facturation; ?>" class="datepick <?php echo is_fielderror('creafact-datefact',$input_errors); ?>" />
                    </td>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_infosgen_facture_datelim'); ?> <span class="required">*</span></td>
                    <td class="right">
                        <input type="text" name="creafact-datelim" id="creafact-datelim" value="<?php echo $date_limit; ?>" class="datepick <?php echo is_fielderror('creafact-datelim',$input_errors); ?>" />
                    </td>
                </tr>
                <?php if($conf->global->SRFF_BANKACCOUNT != '-1'): ?>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_infosgen_facture_bankaccount'); ?></td>
                    <td class="right">
                        <?php $form->select_comptes(GETPOSTISSET('srff-bank-account') ? GETPOST('srff-bank-account') : $conf->global->SRFF_BANKACCOUNT,'srff-bank-account',0,'',1,'',0,'minwidth300'); ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tbody>

                <tr class="dolpgs-thead left">
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
                                    <option value="<?php echo $opt['key']; ?>" <?php if($opt['key'] == GETPOST('creafact-projet')): echo 'selected'; endif; ?>><?php echo $opt['label']; ?></option>
                                <?php endif; endforeach; ?>
                            </select>                      
                      </td>
                </tr>
                <?php endif; ?>
            </tbody>


                <?php // EXTRAFIELDS FACTURE                        
                if($show_extrafields_facture): 

                    $visible_extrafacture = array();
                    foreach($extralabels_facture as $key_exf => $exf):
                        if(in_array($extrafields->attributes['facture_fourn']['list'][$key_exf], $extraf_visibletab) && $extrafields->attributes['facture_fourn']['enabled'][$key_exf]):
                            $visible_extrafacture[$key_exf] = $extralabels_facture[$key_exf];
                        endif;
                    endforeach;

                    $nb_extrafacture = count($visible_extrafacture);

                    // SI IL Y A DES CHAMPS VISIBLES
                    if($nb_extrafacture > 0): ?>

                        <tbody>

                            <tr class="dolpgs-thead left">
                                <th colspan="2"><?php echo $langs->transnoentities('ffs_infosgen_customfields'); ?></th>
                            </tr>

                            <?php
                            // POUR CHAQUE CHAMP VISIBLE
                            foreach($visible_extrafacture as $key_exf => $exf):

                                $exf_type = $extrafields->attributes['facture_fourn']['type'][$key_exf];
                                $exf_label = $extrafields->attributes['facture_fourn']['label'][$key_exf];
                                $exf_required = $extrafields->attributes['facture_fourn']['required'][$key_exf];

                                $value_extrafield = GETPOST('options_'.$key_exf); if (is_array($value_extrafield)): $value_extrafield = implode(',', $value_extrafield); endif;
                                $class_extrafield = ''; if(in_array('options_'.$key_exf, $input_errors)):  $class_extrafield .= ' ffs-fielderror'; endif;

                                ?>
                                
                                <tr class="dolpgs-tbody type-<?php echo $exf_type; ?>">                                        

                                <?php // AFFICHAGE TEXTAREA - COLSPAN 2
                                if($exf_type == 'text'): ?> 
                                    <td colspan="2" class="dolpgs-font-medium">
                                        <?php echo $exf_label; ?> <?php if($exf_required == '1'): ?> <span class="required">*</span> <?php endif; ?><br/> 
                                        <textarea id="options_<?php echo $key_exf; ?>" name="options_<?php echo $key_exf; ?>" class="flat <?php echo $class_extrafield; ?> type-<?php echo $exf_type; ?>"></textarea>
                                    </td>

                                <?php // AFFICHAGE NORMAL / 2 COLONNES
                                else: ?>
                                    <td class="dolpgs-font-medium"><?php echo $exf_label; ?><?php if($exf_required == '1'): ?> <span class="required">*</span> <?php endif; ?></td>
                                    <td class="right">
                                        <?php echo $extrafields->showInputField($key_exf,$value_extrafield,'','','',$class_extrafield,$facture->id,$facture->table_element); ?>
                                    </td>
                                <?php
                                endif; ?>

                                </tr>
                                <?php 

                            endforeach; ?>

                        </tbody>
                    <?php
                    endif;
                endif; ?>

            </tbody>
        </table>

        <!-- LIGNES DE FACTURE -->
        <h3 class="dolpgs-table-title"><?php echo $langs->trans('ffs_details'); ?></h3>
        <table class="dolpgs-table fastfact-table" id="fastfact-tablelines">
            <tbody>
                <tr class="dolpgs-thead noborderside ">
                    <th><?php echo $langs->trans('ffs_details_prodserv'); ?> <span class="required">*</span></th>
                    <?php if($show_extrafields_factureline): // ON RECUPERE LES CHAMPS VISIBLES
                        $visible_extrafacture_ligne = array();
                        foreach($extralabels_factureligne as $key_exfl => $exfl):
                            if(in_array($extrafields->attributes['facture_fourn_det']['list'][$key_exfl], $extraf_visibletab) && $extrafields->attributes['facture_fourn_det']['enabled'][$key_exfl]):
                                $visible_extrafacture_ligne[$key_exfl] = $extralabels_factureligne[$key_exfl]; ?>
                                <th class="left">
                                    <?php echo $extrafields->attribute_label[$key_exfl]; if($extrafields->attribute_required[$key_exfl] == 1): ?><span class="required">*</span><?php endif; ?>
                                </th>
                            <?php endif;
                        endforeach;
                    endif; ?>
                    <th class="<?php if($conf->global->SRFF_AMOUNT_MODE == 'ttc'): echo 'fastfact-hidden'; endif; ?>"><?php echo $langs->transnoentities('ffs_details_amountht'); ?> <span class="required">*</span></th>
                    <th class="<?php if($conf->global->SRFF_AMOUNT_MODE == 'ht'): echo 'fastfact-hidden'; endif; ?>"><?php echo $langs->transnoentities('ffs_details_amountttc'); ?> <span class="required">*</span></th>
                    <th class="right"><?php echo $langs->transnoentities('ffs_details_amounttax'); ?> <span class="required">*</span></th>
                </tr>

                <?php // SI IL N'Y A PAS ENCORE DE LIGNES ?>
                <?php if($nb_lines == 0 && empty($facture_lines)): ?>

                    <tr id="linefact-1" class="oddeven dolpgs-tbody linefact">
                        <td class="pgsz-optiontable-field pdxline">
                            <input type="hidden" name="infofact-saisie-1" id="infofact-saisie-1" value="">
                            <?php echo ffs_select_prodserv($tab_prodserv,1,'',$input_errors); ?>
                        </td>
                        <?php if($show_extrafields_factureline && !empty($visible_extrafacture_ligne)): 
                            foreach($visible_extrafacture_ligne as $key_exfl => $exfl):

                                $exfl_type = $extrafields->attributes['facture_fourn_det']['type'][$key_exfl];
                                $exfl_label = $extrafields->attributes['facture_fourn_det']['label'][$key_exfl];
                                $exfl_required = $extrafields->attributes['facture_fourn_det']['required'][$key_exfl];

                                $value_extrafield = $_POST['options_'.$key_exfl.'-1']; if (is_array($value_extrafield)): $value_extrafield = implode(',', $value_extrafield); endif;
                                
                                $class_extrafield = 'minwidth200';
                                if(in_array('options_'.$key_exfl.'-1', $input_errors)): $class_extrafield .= ' ffs-fielderror'; endif;
                                if($key_exfl == $conf->global->SRFF_EXTRAFACTLINE_PROJECT): $class_extrafield .= ' ffs-lineproject'; endif;
                                if(in_array($exfl_type, array('select','sellist'))): $class_extrafield .= ' ffs-slct'; endif;
                               
                                ?>
                                <td class="left pgsz-optiontable-field">
                                    <?php echo $extrafields->showInputField($key_exfl,$value_extrafield,'','-1','',trim($class_extrafield),$facture->id,$facture->table_element_line); ?>
                                </td>

                            <?php endforeach; ?>
                        <?php endif; ?>
                        <td class="pgsz-optiontable-field <?php if($conf->global->SRFF_AMOUNT_MODE == 'ttc'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantht-1" id="infofact-montantht-1" class="calc-amount" value="" data-mode="ht" data-linenum="1"/></td>
                        <td class="pgsz-optiontable-field <?php if($conf->global->SRFF_AMOUNT_MODE == 'ht'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantttc-1" id="infofact-montantttc-1" class="calc-amount" value="" data-mode="ttc" data-linenum="1"/></td>
                        <td class="pgsz-optiontable-field right">
                            <?php if(!empty($vat_rates)): echo $form->selectarray('infofact-tva-1',$vat_rates,$conf->global->SRFF_DEFAULT_TVA,0,0,0,'data-linenum="1"',0,0,0,'','minwidth100 calc-tva');
                            else: echo $langs->transnoentities('ffs_noVAT');
                            endif; ?>                                
                        </td>                        
                    </tr>

                <?php else: $i= 0; foreach($facture_lines as $line): $i++; ?>

                    <tr id="linefact-<?php echo $i; ?>" class="oddeven dolpgs-tbody linefact">
                        <td class="pgsz-optiontable-field pdxline">
                            <input type="hidden" name="infofact-saisie-<?php echo $i; ?>" id="infofact-saisie-<?php echo $i; ?>" value="<?php echo $line['type_saisie']; ?>" >
                            <?php echo ffs_select_prodserv($tab_prodserv,$i,$line['label'],$input_errors); ?>
                        </td>
                        <?php if($show_extrafields_factureline && !empty($visible_extrafacture_ligne)): 
                            foreach($visible_extrafacture_ligne as $key_exfl => $exfl):

                                $exfl_type = $extrafields->attributes['facture_fourn_det']['type'][$key_exfl];
                                $exfl_label = $extrafields->attributes['facture_fourn_det']['label'][$key_exfl];
                                $exfl_required = $extrafields->attributes['facture_fourn_det']['required'][$key_exfl];

                                $value_extrafield = $_POST['options_'.$key_exfl.'-'.$i]; if (is_array($value_extrafield)): $value_extrafield = implode(',', $value_extrafield); endif;
                                
                                $class_extrafield = 'minwidth200';
                                if(in_array('options_'.$key_exfl.'-'.$i, $input_errors)):  $class_extrafield .= ' ffs-fielderror'; endif;
                                if($key_exfl == $conf->global->SRFF_EXTRAFACTLINE_PROJECT): $class_extrafield .= ' ffs-lineproject'; endif;
                                if(in_array($exfl_type, array('select','sellist'))): $class_extrafield .= ' ffs-slct'; endif;
                               
                                ?>
                                <td class="left pgsz-optiontable-field"><?php echo $extrafields->showInputField($key_exfl,$value_extrafield,'','-'.$i,'',trim($class_extrafield),$facture->id,$facture->table_element_line); ?></td>

                            <?php endforeach; ?>
                        <?php endif; ?>
                        <td class="pgsz-optiontable-field <?php if($conf->global->SRFF_AMOUNT_MODE == 'ttc'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantht-<?php echo $i; ?>" id="infofact-montantht-<?php echo $i; ?>" class="calc-amount <?php echo is_fielderror('infofact-montantht-'.$i,$input_errors); ?>" value="<?php echo $line['montant_ligne_ht']; ?>" data-mode="ht" data-linenum="<?php echo $i; ?>" /></td>
                        <td class="pgsz-optiontable-field <?php if($conf->global->SRFF_AMOUNT_MODE == 'ht'): echo 'fastfact-hidden'; endif; ?>"><input type="text" name="infofact-montantttc-<?php echo $i; ?>" id="infofact-montantttc-<?php echo $i; ?>" class="calc-amount <?php echo is_fielderror('infofact-montantttc-'.$i,$input_errors); ?>" value="<?php echo $line['montant_ligne_ttc']; ?>" data-mode="ttc" data-linenum="<?php echo $i; ?>" /></td>    
                        <td class="right">
                            <?php if(!empty($vat_rates)): 
                                echo $form->selectarray('infofact-tva-'.$i,$vat_rates,$line['taux_tva'],0,0,0,'data-linenum="'.$i.'"',0,0,0,'','minwidth100 calc-tva');
                            else: echo $langs->transnoentities('ffs_noVAT');
                            endif; ?> 

                         </td>                            
                    </tr>
                <?php endforeach; endif; ?>                
        </table>

        <!-- BOUTONS ET TOTAL LIGNE DE FACTURE -->
        <table class="dolpgs-table fastfact-table dolpgs-nohovertable">
            <tbody>
                <tr class="dolpgs-tbody nopadding withoutborder">
                    <td valign="top">
                        <input type="button" value="<?php echo $langs->transnoentities('ffs_details_addline'); ?>" id="add-facture-line" class="dolpgs-btn btn-primary btn-sm" data-addurl="<?php echo $new_script_file.'scripts/add_line.php'; ?>" />
                        <?php if($facture_lines && count($facture_lines) > 1): $display = 'inline-block'; else: $display = 'none'; endif; ?>
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
                <?php if($conf->global->MAIN_UPLOAD_DOC): ?>
                <tr class="dolpgs-thead">
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
                <tr class="liste_titre dolpgs-thead">
                    <th colspan="2"><?php print $langs->transnoentities('ffs_docs_link'); ?></th>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_docs_linkurl'); ?> <span class="required">*</span></td>
                    <td class="right">
                        <input type="url" name="creafact-linkurl" id="creafact-linkurl" placeholder="https://example.com" pattern="https://.*">
                    </td>
                </tr>
                <tr class="dolpgs-tbody">
                    <td class="dolpgs-font-medium"><?php print $langs->transnoentities('ffs_docs_linklabel'); ?> <span class="required">*</span></td>
                    <td class="right">
                        <input type="text" name="creafact-linklib" placeholder="<?php print $langs->transnoentities('ffs_docs_linklib'); ?>">
                    </td>
                </tr>
                
            </tbody>
        </table>

        <div id="" style="text-align: right; margin-top: 16px;">                
            <input type="button" class="dolpgs-btn btn-danger btn-sm" value="<?php print $langs->transnoentities('ffs_cancel'); ?>" onclick="javascript:history.go(-1)">
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