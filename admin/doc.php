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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

dol_include_once('./fastfactsupplier/lib/functions.lib.php');

// POUR <= V10, ON INSTANCIE LES EXTRAFIELDS
$version = explode('.', DOL_VERSION);
if($version[0] <= 10): $extrafields = new ExtraFields($db); endif;

// Change this following line to use the correct relative path from htdocs
dol_include_once('/module/class/skeleton_class.class.php');

// Protection if external user
if ($user->societe_id > 0): accessforbidden(); endif;
if(!$user->rights->fastfactsupplier->configurer): accessforbidden(); endif;

/*******************************************************************
* VARIABLES
********************************************************************/
$action = GETPOST('action');

/*******************************************************************
* ACTIONS
********************************************************************/

/***************************************************
* VIEW
****************************************************/
llxHeader('',$langs->trans('ffs_options_page_title'),'','','','',array(),array('/fastfactsupplier/css/fastfactsupplier.css'),'','fastfactsupplier doc'); ?>

<div class="dolpgs-main-wrapper fastfact">

    <?php if(in_array('progiseize', $conf->modules)): ?>
        <h1 class="has-before"><?php echo $langs->transnoentities('ffs_page_title'); ?></h1>
    <?php else : ?>
        <table class="centpercent notopnoleftnoright table-fiche-title"><tbody><tr class="titre"><td class="nobordernopadding widthpictotitle valignmiddle col-picto"><span class="fas fa-file-invoice-dollar valignmiddle widthpictotitle pictotitle" style=""></span></td><td class="nobordernopadding valignmiddle col-title"><div class="titre inline-block"><?php echo $langs->transnoentities('ffs_page_title'); ?></div></td></tr></tbody></table>
    <?php endif; ?>
    <?php $head = ffsAdminPrepareHead(); dol_fiche_head($head, 'doc','FastFactSupplier', 0,'fa-file-invoice-dollar_file-invoice-dollar_fas'); ?>

    <?php if(!in_array('progiseize', $conf->modules)): ?>
        <div class="alert-message-need-base">
            <i class="fas fa-info-circle" style="margin-right:5px;"></i> 
            Cette version nécéssite le module PROGISEIZE pour fonctionner correctement. Vous pouvez la télécharger depuis Github en cliquant sur ce lien : <a href="https://github.com/progiseize/progiseize" target="_blank">Module Progiseize Github</a>
        </div>
    <?php endif; ?>

    <h3 class="dolpgs-table-title"><?php echo $langs->trans('ffs_doc_title'); ?></h3>
    <table class="dolpgs-table fastfact-table">
        <tbody>
            <tr class="dolpgs-thead noborderside" >
                <th><?php echo $langs->trans('Parameter'); ?></th>
                <th><?php echo $langs->trans('Description'); ?></th>
            </tr>
            <tr class="dolpgs-tbody" valign="top">
                <td class="dolpgs-font-medium"><?php echo $langs->trans('ffs_cc_insert'); ?></td>               
                <td class="dolpgs-color-gray-i"><?php echo $langs->trans('ffs_doc_cc_insert_desc'); ?></td>
            </tr>
            <tr class="dolpgs-tbody" valign="top">
                <td class="dolpgs-font-medium"><?php echo $langs->trans('ffs_options_cats'); ?></td>               
                <td class="dolpgs-color-gray-i"><?php echo $langs->transnoentities('ffs_doc_options_cats_desc'); ?></td>
            </tr>
            <tr class="dolpgs-tbody" valign="top">
                <td class="dolpgs-font-medium"><?php echo $langs->trans('ffs_doc_extra_uploadfile'); ?></td>               
                <td class="dolpgs-color-gray-i"><?php echo $langs->transnoentities('ffs_doc_extra_uploadfile_desc'); ?></td>
            </tr>
            <tr class="dolpgs-tbody" valign="top">
                <td class="dolpgs-font-medium"><?php echo $langs->trans('ffs_options_customfields'); ?></td>               
                <td class="dolpgs-color-gray-i"><?php echo $langs->transnoentities('ffs_doc_options_customfields_desc'); ?></td>
            </tr>
        </tbody>
    </table>

</div>

<?php llxFooter(); $db->close(); ?>