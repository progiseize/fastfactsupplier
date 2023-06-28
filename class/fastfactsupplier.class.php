<?php

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

class FastFactSupplier {

	public $params = array(
		'use_categories_product' => '',
		'cats_to_use' => '',
		'custom_list' => '',
		'gotoreg' => '',
		'show_extrafields_facture' => '',
		'show_extrafields_factureline' => '',
		'usecustomfield_uploadfile' => '',
		'mode_amount' => '',
		'default_bankaccount' => '',
		'extra_lineproject' => '',
		'default_tva' => '',
	);


	/**
     * @var Array Errors messages.
     */
    public $errors = array();

	/**
     * @var DoliDB Database handler.
     */
    public $db;
	

	/**
	 *	Constructor
	 *
	 *	@param 	DoliDB	$_db	Database handler
	 */
    public function __construct($_db){
        global $db;
        $this->db = is_object($_db) ? $_db : $db;

        $this->set_params();
    }

    //
    private function set_params(){
    	global $conf;

    	$this->params['use_categories_product'] = getDolGlobalInt('SRFF_USESERVERLIST');
		$this->params['cats_to_use'] = json_decode(getDolGlobalString('SRFF_CATS'));
		$this->params['custom_list'] = getDolGlobalString('SRFF_SERVERLIST');
		$this->params['gotoreg'] = getDolGlobalInt('SRFF_GOTOREG');
		$this->params['show_extrafields_facture'] = getDolGlobalInt('SRFF_SHOWEXTRAFACT');
		$this->params['show_extrafields_factureline'] = getDolGlobalInt('SRFF_SHOWEXTRAFACTLINE');
		$this->params['usecustomfield_uploadfile'] = getDolGlobalInt('SRFF_USECUSTOMFIELD_UPLOADFILE');
		$this->params['mode_amount'] = getDolGlobalString('SRFF_AMOUNT_MODE');
		$this->params['default_bankaccount'] = getDolGlobalInt('SRFF_BANKACCOUNT');
		$this->params['extra_lineproject'] = getDolGlobalString('SRFF_EXTRAFACTLINE_PROJECT');
		$this->params['default_tva'] = getDolGlobalString('SRFF_DEFAULT_TVA');
		
    }

    //
    public function refresh_params(){
    	$this->set_params();
    	return 1;
    }

    /**
	 *	Check if a field is in error
	 *
	 *	@param 	string	$fieldname		Name of field
	 *	@param 	array	$tab_errors		Array of errors
	 */
    public function is_fielderror($fieldname){
	    $class_error = '';
	    if(in_array($fieldname, $this->errors)): $class_error = 'ffs-fielderror';endif;
	    return $class_error;
	}


	/**
	 *	Returns list of products & services
	 *
	 *	@param 	array	$tab_cats	Categories to use
	 */
	function get_products_services_list($tab_cats){

	    $tab_prodserv = array();

	    $sql = "SELECT rowid, label, tva_tx FROM ".MAIN_DB_PREFIX."product as a";
	    $sql .=" INNER JOIN ".MAIN_DB_PREFIX."categorie_product as b";
	    $sql .=" ON a.rowid = b.fk_product WHERE";

	    $nbcats = 0;
	    foreach($tab_cats as $cat_id): $nbcats++;
	        if($nbcats > 1): $sql .= " OR"; endif;
	        $sql .=" b.fk_categorie = '".$cat_id."'";
	    endforeach;
	    
	    $sql .=" ORDER BY a.label";
	    $results_prodserv = $this->db->query($sql);

	    if($results_prodserv): 
	        while ($prodserv = $this->db->fetch_object($results_prodserv)):
	            $tab_prodserv[$prodserv->rowid] = $prodserv->label;
	        endwhile;
	    endif;

	    return $tab_prodserv;
	}

	/**
	 *	Returns list of products & services
	 *
	 *	@param 	string	$selected				Name of supplier selected
	 *	@param 	string	$next_code_fournisseur	Next supplier code
	 */
	function get_supplier_list($selected = '',$next_code_fournisseur){

	    $suppliers_list = array();
	    $options = '';

	    $sql = "SELECT rowid,nom,code_fournisseur FROM ".MAIN_DB_PREFIX."societe WHERE fournisseur = '1'";
	    $res = $this->db->query($sql);
	    
	    while($supplier = $this->db->fetch_object($res)):
	        $options .= '<option ';
	        if($selected == $supplier->nom): $options .= 'selected="selected" '; endif;
	        $options .= 'value="'.$supplier->nom.'" data-fournid="'.$supplier->rowid.'" data-codefourn="'.$supplier->code_fournisseur.'" >'.$supplier->nom.'</option>';
	        array_push($suppliers_list, $supplier->nom);
	    endwhile;
	    if(!empty($selected) && !in_array($selected, $suppliers_list)):
	        $options .= '<option selected="selected" data-codefourn="'.$next_code_fournisseur.'" value="'.$selected.'">'.$selected.'</option>';
	    endif;

	    return $options;
	}

	
	/**
	 *	Select constructor for products & services
	 *
	 *	@param 	array		$tab_prodserv	Array of products & services
	 *	@param 	integer		$numero_ligne	Line number
	 *	@param 	string		$value			Value of field
	 */
	function select_prodserv($tab_prodserv,$numero_ligne,$value = ''){

	    global $db, $conf;

	    $fieldname = 'infofact-prodserv-'.$numero_ligne;
	    $class_error = $this->is_fielderror($fieldname);

	    $select = '<select class="flat minwidth300 pdx fact-line" data-addclass="'.$class_error.'" name="'.$fieldname.'" id="infofact-prodserv-'.$numero_ligne.'">';
	    $select .= '<option></option>';
	    
	    if(getDolGlobalInt('SRFF_USESERVERLIST')):

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

	/**
	 *	Check if extrafields is unique
	 *
	 *	@param 	array		$tab_prodserv	Array of products & services
	 *	@param 	integer		$numero_ligne	Line number
	 *	@param 	string		$value			Value of field
	 */
	public function is_extrafield_unique($extrafield_name,$table_element,$value){

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$table_element."_extrafields";
		$sql.= " WHERE ".$extrafield_name." = '".$this->db->escape($value)."'";
		$res = $this->db->query($sql);

		if(!$res): return -1; endif;
		return $res->num_rows;
	}

	public function check_exist_ref_supplier($ref_supplier,$socid){

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture_fourn WHERE ref_supplier = '".$this->db->escape($ref_supplier)."' AND fk_soc = '".$socid."'";
		$result = $this->db->query($sql);
		if($result->num_rows > 0): return true; endif;
		return false;
	}




}