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
 
jQuery(document).ready(function() {


/*********************/
/***** LANGUAGES *****/

var traductionList = ['fr_FR','en_US']
let traductionTxt = new Object();
var dirLang = jQuery('#fastfact-lang').val();
var dirLangFinal;

if(traductionList.includes(dirLang)){ dirLangFinal = dirLang;}
else {dirLangFinal = 'en_US';}

jQuery.ajax('./langs/'+dirLangFinal+'/fastfactsupplier.lang',{
    async: false,
    success:function(response){
    var lines = response.split(/\r\n|\n/);
    lines.forEach(function(elem){
        if(elem.startsWith('ffs_')){ var result = elem.split('=');  traductionTxt[result[0].trim()] = result[1].trim();}
    });
    }
});


/*********************/
/***** CALENDAR *****/
jQuery( ".datepick" ).datepicker();


/*********************/
/***** SUPPLIER SELECT *****/
nom_addclass = jQuery('#creatiers-nom').data('addclass');
jQuery('#creatiers-nom').select2({
    placeholder: traductionTxt['ffs_infosgen_typeorselectinlist'],
    tags: true,
    containerCssClass: nom_addclass
});

jQuery('#creatiers-nom').on('select2:select', function (e) {
  
    var data = e.params.data;
    if (typeof data.element !== 'undefined') {
        var dataset = data.element.dataset;        
        jQuery('input[name="is-already"]').attr('value','1');
        jQuery('input[name="fournid"]').attr('value',dataset.fournid);
        jQuery('#creatiers-codefournisseur').val(dataset.codefourn);
    }
    else {
        var new_reffourn = jQuery('#new_reffourn').val();
        jQuery('#creatiers-codefournisseur').val(new_reffourn);
        jQuery('input[name="is-already"]').attr('value','0');
        jQuery('input[name="fournid"]').attr('value','');
    }
    
    var numfacturl = jQuery(this).data('numfacturl');

    jQuery.ajax({
        url:numfacturl,
        async: true,
        method:'GET',
        data:{tiers:data.id},
        success:function(response){
            jQuery('.txt-numfact').html('('+response+')');

            var test_reffourn = jQuery('#creafact-reffourn').val();
            if (test_reffourn !== "") {
                checkSupplierRef(jQuery('#creafact-reffourn').data('checkrefurl'),test_reffourn,dataset.fournid);
            }
        }
    });
    
});


/*********************/
/***** SUPPLIER REF *****/
jQuery('#creafact-reffourn').on('change',function(e){

    e.preventDefault();
    var checkrefurl = jQuery(this).data('checkrefurl');
    var reffourn = jQuery(this).val();
    var fournid = jQuery('#fournid').val();
    checkSupplierRef(checkrefurl,reffourn,fournid);

});

function checkSupplierRef(checkrefurl,reffourn,fournid){
   if (fournid !== "") {

        jQuery.ajax({
            url:checkrefurl,
            async: true,
            method:'GET',
            data:{facture_reffourn:reffourn,facture_fournid:fournid},
            success:function(response){

                console.log(response);

                if(parseInt(response) > 0){
                    alert(traductionTxt['ffs_fielderror_reffourn_exist']);
                    jQuery('#creafact-reffourn').addClass('ffs-fielderror');
                } else {
                    jQuery('#creafact-reffourn').removeClass('ffs-fielderror');
                }
            }
        });
    }
}







// SUPPRESSION D'UNE LIGNE DE FACTURE
jQuery('#del-facture-line').on('click',function(e){

    var numViews = parseInt(jQuery('input[name="infofact-linenumber"]').val());
    jQuery('tr#linefact-'+ numViews).remove();
    var viewNumber = numViews - 1;
    jQuery('input[name="infofact-linenumber"]').attr('value',viewNumber);
    if(viewNumber == 1){jQuery(this).hide();}

    calcul_totaux();

});

// AJOUT D'UNE LIGNE DE FACTURE
jQuery('#add-facture-line').on('click',function(e){

    e.preventDefault();

    var addurl = jQuery(this).data('addurl');
    var numViews = parseInt(jQuery('input[name="infofact-linenumber"]').val());
    var viewNumber = numViews + 1;
    jQuery('input[name="infofact-linenumber"]').attr('value',viewNumber);

    jQuery.ajax({
        url:addurl,
        async: true,
        method:'GET',
        data:{viewnumber:viewNumber},
        success:function(view){
            jQuery('#fastfact-tablelines tbody').append(view);
            if(viewNumber > 1){jQuery('#del-facture-line').show();}

            jQuery('.pdx.pdx-'+ viewNumber).select2({placeholder: traductionTxt['ffs_infosgen_selectinlist'],language: {noResults: function(){return traductionTxt['ffs_infosgen_selectnoresults'];}}});
            jQuery('.linefact select.pdx, .linefact select.calc-tva').each(function(e){
                class_pdx = jQuery(this).data('addclass');
                jQuery(this).select2({placeholder: traductionTxt['ffs_infosgen_selectinlist'],containerCssClass: class_pdx,language: {noResults: function(){return traductionTxt['ffs_infosgen_selectnoresults'];}}});
            });
            jQuery('.linefact select.pj-select').each(function(e){
                jQuery(this).select2({placeholder:{id: '0', text: traductionTxt['ffs_infosgen_selectinlist']},language: {noResults: function(){return traductionTxt['ffs_infosgen_selectnoresults'];}}});
            });
            jQuery('.ffs-slct').each(function(e){
                jQuery(this).select2({containerCssClass: ':all:',language: {noResults: function(){return traductionTxt['ffs_infosgen_selectnoresults'];}}});
            });
        }
    });

});

// SELECT2 SUR LIGNE - PRODUITS
jQuery('.pdx').each(function(e){
    class_pdx = jQuery(this).data('addclass');
    jQuery(this).select2({placeholder: traductionTxt['ffs_infosgen_selectinlist'],containerCssClass: class_pdx,language: {noResults: function(){return traductionTxt['ffs_infosgen_selectnoresults'];}}});
});

// SELECT2
jQuery('#cats-ids').select2({
    placeholder:traductionTxt['ffs_infosgen_selectinlist'],
    language: {noResults: function(){return traductionTxt['ffs_infosgen_selectnoresults'];}},
    tags: true,
    allowClear: true
});

// SELECT2 - LIGNE TVA
jQuery('.calc-tva').each(function(e){
    class_pdx = jQuery(this).data('addclass');
    jQuery(this).select2({containerCssClass: class_pdx,language: {noResults: function(){return traductionTxt['ffs_infosgen_selectnoresults'];}}});
});

// SELECT2 - LISTE
jQuery('.ffs-slct').each(function(e){
    jQuery(this).select2({containerCssClass: ':all:',language: {noResults: function(){return traductionTxt['ffs_infosgen_selectnoresults'];}}});
});




//



// Zone de calcul TTC

function calcul_totaux(){

    // ON RECUPERE LE MODE DE CALCUL - HT, TTC ou BOTH
    var modeCalcul = jQuery('input[name=ffs_amout_mode]').val();

    // ON PREPARE LES VARIABLES
    var calcul_ht = 0;
    var calcul_tva = 0;
    var calcul_ttc = 0;

    var i = 0;

    // POUR CHAQUE LIGNE DE FACTURE
    jQuery('.linefact').each(function(e){ i++;

        var ligne_ht = parseFloat(jQuery(this).find('.calc-amount[data-mode="ht"]').val().replace(',', '.')) || 0;
        var ligne_ttc = parseFloat(jQuery(this).find('.calc-amount[data-mode="ttc"]').val().replace(',', '.')) || 0;
        var ligne_tva = parseFloat(jQuery(this).find('.calc-tva').val().replace(',', '.'));

        calcul_ht += ligne_ht;
        //calcul_tva += (ligne_ht/100) * ligne_tva;
        calcul_ttc += ligne_ttc;

    });

    calcul_tva = calcul_ttc - calcul_ht;

    if (isNaN(calcul_ht)) { jQuery('.calcul-zone-ht').html('--');
    } else { jQuery('.calcul-zone-ht').html(calcul_ht.toFixed(2));}

    if (isNaN(calcul_tva)) { jQuery('.calcul-zone-tva').html('--');
    } else { jQuery('.calcul-zone-tva').html(calcul_tva.toFixed(2));}

    if (isNaN(calcul_ttc)) { jQuery('.calcul-zone-ttc').html('--');
    } else { jQuery('.calcul-zone-ttc').html(calcul_ttc.toFixed(2));}

}

// LIGNE DE FACTURE - SAISIE MONTANT
jQuery('#fastfact-tablelines').on('change','.calc-amount',function(e){

    var typesaisie = jQuery(this).data('mode');    
    var linenum = jQuery(this).data('linenum');

    var ml = parseFloat(jQuery(this).val().replace(',', '.')) || 0;
    var ml_calc = 0;
    var ml_tva = parseFloat(jQuery('#infofact-tva-'+linenum).val().replace(',', '.'));

    // ON RENSEIGNE LE TYPE DE SAISIE DANS LE CHAMP TVA POUR RECALCUL SI BESOIN
    jQuery('#infofact-saisie-'+linenum).val(typesaisie);

    // ON REMPLI LE CHAMP MANQUANT
    if(typesaisie == 'ht'){
        ml_calc = ml + ((ml/100)*ml_tva);
        jQuery('#infofact-montantttc-'+linenum).val(ml_calc.toFixed(2));
    } else if(typesaisie == 'ttc'){
        ml_calc = ml / (1 + ml_tva / 100);
        jQuery('#infofact-montantht-'+linenum).val(ml_calc.toFixed(2));
    }

    calcul_totaux();
});

// LIGNE DE FACTURE - CHOIX TVA
jQuery('#fastfact-tablelines').on('change','.calc-tva',function(e){

    var linenum = jQuery(this).data('linenum');
    var typesaisie = jQuery('#infofact-saisie-'+linenum).val();

    if(typesaisie == 'ht'){

        var ml = parseFloat(jQuery('#infofact-montantht-'+linenum).val().replace(',', '.')) || 0;
        var ml_calc = 0;
        var ml_tva = parseFloat(jQuery(this).val().replace(',', '.'));

        ml_calc = ml + ((ml/100)*ml_tva);
        jQuery('#infofact-montantttc-'+linenum).val(ml_calc.toFixed(2));

    } else if(typesaisie == 'ttc'){

        var ml = parseFloat(jQuery('#infofact-montantttc-'+linenum).val().replace(',', '.')) || 0;
        var ml_calc = 0;
        var ml_tva = parseFloat(jQuery(this).val().replace(',', '.'));

        ml_calc = ml / (1 + ml_tva / 100);
        jQuery('#infofact-montantht-'+linenum).val(ml_calc.toFixed(2));
    }

    calcul_totaux();
});



var toggle = 0;
jQuery('#toggle_untoggle').on('click',function(e){

    e.preventDefault();

    if(toggle == 0){ jQuery('input.toguntog').prop('checked',true); toggle = 1;
    } else { jQuery('input.toguntog').prop('checked',false); toggle = 0;
    }
})

//


var urlform = jQuery('#fournifact').attr('action');

jQuery('#creafact-file').change(function () {

    var verifSize = jQuery('#zone-drop').data('maxsize');
    var sendSize = this.files[0].size;
    var s = Math.round(sendSize / 1024);

    //console.log(verifSize);

    var msg = '<span class="bold">' + this.files[0].name + '</span> (' + s + ' Ko)';

    if(sendSize >= verifSize){
        msg += '<br/><span class="bold" style="color:#b22525;font-size:10px;"><i class="fa fa-close"></i> '+traductionTxt['ffs_fielderror_filetoobig']+'</span>';
         jQuery('#zone-drop').addClass('warning');
    } else {
        msg += ' <span class="bold" style="color:#78ba4a;"><i class="fa fa-check"></i></span>';
        jQuery('#zone-drop').removeClass('warning');
    }
    
    jQuery('#zone-drop-infos').html(msg);
  });

jQuery('#zone-drop').on('dragover',function(e){ jQuery(this).addClass('dragged'); });
jQuery('#zone-drop').on('dragleave',function(e){ jQuery(this).removeClass('dragged'); });


//dragleave


jQuery('#creafact-redirect').change(function(){
    if(this.checked) {
        jQuery('.redirect-active').show();
    } else {
        jQuery('.redirect-active').hide();
    }
});

/*jQuery('.linefact .ffs-lineproject').each(function(e){ 
                console.log(this);
                //jQuery(this).val(data.id).trigger("change");
                console.log('yo');
            });*/

if(jQuery('#creafact-projet').length){

    jQuery('#creafact-projet').select2({placeholder: traductionTxt['ffs_infosgen_selectinlistproject'],allowClear:true});

    var is_line_projet = jQuery(".ffs-lineproject").length;
    //console.log(parseInt(is_line_projet));

    //if(parseInt(is_line_projet) > 0){ console.log('oui');
        jQuery('#creafact-projet').on('select2:select',function(e){  
            var data = e.params.data;
            //console.log(data);
            //console.log(data.id);
            // jQuery('.pj-select').val(data.id);

            jQuery('.linefact .ffs-lineproject').each(function(e){ 
                //console.log(this);
                jQuery(this).val(data.id).trigger("change");
                console.log('change');
            });
            
        });
    //}

    /**/
}


});
