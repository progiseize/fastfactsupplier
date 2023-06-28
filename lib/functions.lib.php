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

    complete_head_from_modules($conf, $langs, 'null', $head, $h, 'fastfactsupplier');

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
?>