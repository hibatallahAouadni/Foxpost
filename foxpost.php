<?php

/*
################################
# Foxpost Parcels              #
# Copyright ToHr               #
# 2015.09.18                   #
# Foxpost parcels delivery core#
################################
*/


if (!defined('_PS_VERSION_'))
    exit;

class foxpost extends CarrierModule {
    var $salt = "eht7895z6v5z6775z76z45876z476zm8";

    public $id_carrier;
    public $id_lang;
    private $_html = '';
    private $_postErrors = array();
    private $_moduleName = 'foxpost';

    public function __construct() {
        $this->name = 'foxpost';
        $this->tab = 'shipping_logistics';
        $this->author = 'Foxpost';
        $this->module_key = '707f7513209a2e5a4933485820a3ba8f';
        $this->version = '1.5';
        $this->need_instance = 0;
        $this->id_lang = (!isset($cookie) OR ! is_object($cookie)) ? (int) (Configuration::get('PS_LANG_DEFAULT')) : (int) ($cookie->id_lang);
        //$this->limited_countries = array('hu');

        /* The parent construct is required for translations */
        parent::__construct();

        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('Foxpost');
        $this->description = $this->l('Foxpost A legokosabb csomagok');
    }

    public function install() {
        $carrier = new Carrier();
        $carrier->name = 'Foxpost';
        $carrier->id_tax_rules_group = 0; // We do not apply thecarriers tax
        $carrier->id_zone = 1; // Area where the carrier operates
        $carrier->active = true;
        $carrier->deleted = 0;
        $carrier->delay = array(
            'fr' => 'Foxpost',
            'en' => 'Foxpost',
            'hu' => 'Csomagátvétel Foxpost automatában',
            Language::getIsoById(Configuration::get('PS_LANG_DEFAULT')) => 'Foxpost');
        $carrier->shipping_handling = false;
        $carrier->range_behavior = 0;
        $carrier->is_module = true; // We specify that it is a module
        $carrier->shipping_external = false;
        $carrier->external_module_name = 'foxpost'; // We specify the name of the module
        $carrier->need_range = true; // We specify that we want the calculations for the ranges that are configured in the back office

        $languages = Language::getLanguages(true);
        foreach ($languages as $language) {
            if ($language['iso_code'] == 'hu')
                $carrier->delay[(int) $language['id_lang']] = 'Foxpost';
            if ($language['iso_code'] == Language::getIsoById(Configuration::get('PS_LANG_DEFAULT')))
                $carrier->delay[(int) $language['id_lang']] = 'Foxpost';
        }

        if ($carrier->add()) {
            $groups = Group::getGroups(true);
            foreach ($groups as $group)
                Db::getInstance()->autoExecute(_DB_PREFIX_ . 'carrier_group', array('id_carrier' => (int) ($carrier->id), 'id_group' => (int) ($group['id_group'])), 'INSERT');

            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->delimiter1 = '0';
            $rangePrice->delimiter2 = '500000';
            $rangePrice->add();

            $rangeWeight = new RangeWeight();
            $rangeWeight->id_carrier = $carrier->id;
            $rangeWeight->delimiter1 = '0';
            $rangeWeight->delimiter2 = '25';
            $rangeWeight->add();

            $zones = Zone::getZones(true);
            foreach ($zones as $zone) {
                Db::getInstance()->autoExecute(_DB_PREFIX_ . 'carrier_zone', array('id_carrier' => (int) ($carrier->id), 'id_zone' => (int) ($zone['id_zone'])), 'INSERT');
                Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_ . 'delivery', array('id_carrier' => (int) ($carrier->id), 'id_range_price' => (int) ($rangePrice->id), 'id_range_weight' => NULL, 'id_zone' => (int) ($zone['id_zone']), 'price' => '0'), 'INSERT');
                Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_ . 'delivery', array('id_carrier' => (int) ($carrier->id), 'id_range_price' => NULL, 'id_range_weight' => (int) ($rangeWeight->id), 'id_zone' => (int) ($zone['id_zone']), 'price' => '0'), 'INSERT');
            }

            
            //foxpost tábla
            $query_create = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."foxpost` (
                `id_foxpost` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `id_order` int(11) DEFAULT NULL,
                `automata` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
                `json_result` text COLLATE utf8_unicode_ci,
                `statusz` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
                `id_parcel` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
                `idopont` datetime DEFAULT NULL,
                PRIMARY KEY (`id_foxpost`)
              )";
            Db::getInstance()->execute($query_create);
            
            
            // Copy Logo
            @copy(dirname(__FILE__).'/carrier.jpg', _PS_SHIP_IMG_DIR_.'/'.(int)$carrier->id.'.jpg');
            // Return ID Carrier
            $id_foxpost = ($carrier->id);
        }
// 		
// 		$id_carrier1 = $this->installExternalCarrier($carrierConfig[0]);
        Configuration::updateValue('FOXPOST_CARRIER_ID', (int) $id_foxpost);

        if (parent::install() == false
		//front
                || ! $this->registerHook('updateCarrier')
                || ! $this->registerHook('extraCarrier')
                || ! $this->registerHook('orderConfirmation')
                //backend
                || !$this->registerHook('backOfficeHeader')
                || !$this->registerHook('header')
                || !$this->installModuleTab('AdminFoxpost', array(Configuration::get('PS_LANG_DEFAULT') => 'Foxpost'), $idTabParent = 10)
				
        )
            return false; //OR !$this->unregisterHook('beforeCarrier')

        return true;
    }
	
	private function installModuleTab($tabClass, $tabName, $idTabParent) {
		@copy(_PS_MODULE_DIR_ . $this->name . '/logo.png', _PS_IMG_DIR_ . 't/' . $tabClass . '.png');
		$tab = new Tab();
		$tab->name = $tabName;
		$tab->class_name = $tabClass;
		$tab->module = $this->name;
		$tab->id_parent = $idTabParent;
		if (!$tab->save())
		  return false;
		return true;
	}
	 
	private function uninstallModuleTab($tabClass) {
		$idTab = Tab::getIdFromClassName($tabClass);
		if ($idTab != 0) {
		  $tab = new Tab($idTab);
		  $tab->delete();
		  @unlink(_PS_IMG_DIR . "t/" . $tabClass . ".png");
		  return true;
		}
		return false;
	}

// 
    public function uninstall() {
        global $cookie;
        // We delete the carriers we created earlier
        $Carrier_foxpost = new Carrier((int) (Configuration::get('FOXPOST_CARRIER_ID')));

        // If the modules was the default carrier, we choose another
        if (Configuration::get('PS_CARRIER_DEFAULT') == (int) ($Carrier_foxpost->id)) {
            $carriersD = Carrier::getCarriers($cookie->id_lang, true, false, false, NULL, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);
            foreach ($carriersD as $carrierD){
                if ($carrierD['active'] && ! $carrierD['deleted'] && ( $carrierD['name'] != $this->_config['name'])){
                    Configuration::updateValue('PS_CARRIER_DEFAULT', $carrierD['id_carrier']);
                }
            }
        }

        if (parent::uninstall() == false
                || ! Configuration::deleteByName('FOXPOST_CARRIER_ID')
                || ! $this->unregisterHook('updateCarrier')
                || ! $this->unregisterHook('extraCarrier')
                || ! $this->registerHook('orderConfirmation')
				|| !$this->uninstallModuleTab('AdminFoxpost')
        ){
            return false;
        }

        // Then we delete the carriers using variable delete
        // in order to keep the carrier history for orders placed with them
        $Carrier_foxpost->name = 'Foxpost';
        $Carrier_foxpost->deleted = 1;
        if (!$Carrier_foxpost->update()){
            return false;
        }

        return true;
    }

    public function getOrderShippingCost($params, $shipping_cost) {
        return $shipping_cost;
    }

    public function getOrderShippingCostExternal($params) {
        return false;
    }

    public function displayInfoByCart($cartID) {
        return '';
    }

    // ----------------------------------------------------------------------
    //      *** HOOKS ***
    // ----------------------------------------------------------------------
    // ** Hook update carrier
    // az admin felületen ha módosítják a szállító adatait, akkor új ID-t kap, ezért újra be kell állítani
    public function hookupdateCarrier($params) {
        if ((int) ($params['id_carrier']) == (int) (Configuration::get('FOXPOST_CARRIER_ID'))){
            Configuration::updateValue('FOXPOST_CARRIER_ID', (int) ($params['carrier']->id));
        }
        return true;
    }

    public function hookextraCarrier($params) {
        global $cart, $smarty;
        $smarty->assign('foxpost_ID', Configuration::get('FOXPOST_CARRIER_ID'));

        //választott automata
        $context = Context::getContext();
        $selectedAutomata = $context->cookie->__get("foxpost_automata_".$params['cookie']->id_cart);
        
        $fox = new FoxpostClass();
        $options = $fox->fox_getAllAutomataAsOptions($selectedAutomata);
        
        //var_dump($cart);
        $smarty->assign("id_address_d", $cart->id_address_delivery);
        
        $smarty->assign('options', $options);

        return $this->display(__FILE__, 'foxpost_carrier.tpl');
    }

    /* rendelés végén fut le */

    public function hookorderConfirmation($params) {
        //ha foxpost a szállító
        if ($params['objOrder']->id_carrier == Configuration::get('FOXPOST_CARRIER_ID')) {
            
            //automata azonosítója
            $context = Context::getContext();
            $huwbx = $context->cookie->__get("foxpost_automata_".$params['objOrder']->id_cart);
            
            //foxpost táblába eltesszük az adatokat, amiket később kiegészítünk
            Db::getInstance()->Execute('INSERT INTO ' . _DB_PREFIX_ . 'foxpost (id_order, automata, statusz)
                    VALUES (' . $params['objOrder']->id . ', "' . $huwbx . '", "new")');
            
            //követési szám mezőbe eltesszük egyelőre a foxpost számát, hogy látható legyen a megrendelés adatlapján
            Db::getInstance()->Execute('UPDATE ' . _DB_PREFIX_ . 'order_carrier SET tracking_number = "'.$huwbx.'"
                    WHERE id_order = ' . $params['objOrder']->id . '');
        } 
    }
    
    /**
	 * Display the Foxpost's module settings page
	 * for the user to set their API Key pairs and choose 
	 * whether their customer's can save their card details for
	 * repeate visits.
	 *
	 * @return string Foxpost settings page
	 */
    public function getContent(){

        $output = "";
        if (Tools::isSubmit('SubmitFoxpostConfig')){
           
         
	Configuration::updateValue('FOXPOST_API_URL', Tools::getValue('foxpost_api_url'));
	Configuration::updateValue('FOXPOST_API_USERNAME', Tools::getValue('foxpost_api_username'));
	Configuration::updateValue('FOXPOST_API_PASSWORD', Tools::getValue('foxpost_api_password'));
	Configuration::updateValue('FOXPOST_SHIPPING_FEE', Tools::getValue('foxpost_shipping_fee'));

            $this->context->smarty->assign('formaction', Tools::safeOutput($_SERVER['REQUEST_URI']));
            $this->context->smarty->assign('link', $this->context->link);
            $this->context->smarty->assign("foxpost_error_msg", "Sikeres beállítás!");
        }	
	

        $foxclass = new FoxpostClass();

		
		
               $this->context->smarty->assign('foxpost_api_url', Configuration::get('FOXPOST_API_URL'));
	       $this->context->smarty->assign('foxpost_api_username', Configuration::get('FOXPOST_API_USERNAME'));
	       $this->context->smarty->assign('foxpost_api_password', Configuration::get('FOXPOST_API_PASSWORD'));
               $this->context->smarty->assign('foxpost_shipping_fee', Configuration::get('FOXPOST_SHIPPING_FEE'));

                    $db=DB::getInstance();
                        $res = $db->ExecuteS('select id_carrier from '._DB_PREFIX_.'carrier where active = 1 AND deleted=0 AND name LIKE "%Foxpost%" ');
                        foreach ($res as $restt) {
                            $db->execute("UPDATE "._DB_PREFIX_."delivery SET price='".Configuration::get('FOXPOST_SHIPPING_FEE')."' WHERE id_carrier='".$restt['id_carrier']."'");
                        }

	
		$this->context->smarty->assign('formaction', Tools::safeOutput($_SERVER['REQUEST_URI']));
	
        $this->context->smarty->assign('link', $this->context->link);
        $output .= $this->context->smarty->fetch(_PS_MODULE_DIR_.'/foxpost/view/admin/foxpostSettings.tpl');
		return $output;
    }

}

class FoxpostClass{
 
    public $total_count = 0;
    public $json;
    
    public function __construct($json = "") {
        if(strlen($json) == 0){
            $json = $this->fox_downloadAutomatakAndReturn();
        }
        $this->json = json_decode($json);
        //$this->total_count = $this->json->total_count;
    }
    
    public function fox_downloadAutomatakAndReturn(){
        
 
        
        $tmp_dir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : (is_callable('sys_get_temp_dir') ? sys_get_temp_dir() : '');
        
 
            $ch = curl_init(Configuration::get('FOXPOST_API_URL')."places");


            //curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch,CURLOPT_HEADER,"Accept:application/vnd.cleveron+json; version=1.0");
            curl_setopt($ch,CURLOPT_HEADER,"Content-Type:application/vnd.cleveron+xml");
            curl_setopt($ch,CURLOPT_USERPWD,Configuration::get('FOXPOST_API_USERNAME').":".Configuration::get('FOXPOST_API_PASSWORD'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);


            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $jsonData =curl_exec($ch);
            curl_close($ch);

            
      
        return $jsonData;
    }
    
    function fox_getAllAutomataAsOptions($selectedAutomata){
        $result = "";
        $automatak = array();
        foreach($this->fox_getAutomatakByTelepules("") as $automata){
            /* @var $automata FoxpostItem */
            $cim = $automata->fox_getAddress_city() . " " . $automata->fox_getName() . ", " . $automata->fox_getAddress_street() . " ";
            $automatak[$automata->fox_getId()] = array(
                'text' => $cim . "", 
                'id' => $automata->fox_getId(), 
                'map' => $automata->fox_getMinimap(), 
                'info' => $automata->fox_getLocation_description()
            );
        }
        sort($automatak);
        foreach($automatak as $s){
            $selected = (strlen($selectedAutomata) > 1 && $s['id'] == $selectedAutomata ? "selected='selected'" : "");
            $result .=  "<option value='".$s['id']."' data-map='".$s['map']."' data-info='".$s['info']."' ".$selected.">".$s['text']."</option>\n";
        }
        

        return $result;
        
    }
    
    function fox_getAutomatakByTelepules($telepules){
        $result = array();
        foreach($this->json as $id => $json){
    
            if($json->group == $telepules || strlen($telepules) == 0){
                $result[$id] = new FoxpostItem($json);
            }
        }
        return $result;
    }

}

class FoxpostItem{
    public $json;
    public $href, $minimap, $id, $type, $services = array(), $payment_type, $address_street, $address_building_no, $address_city, $address_post_code, $address_province;
    public $address_str, $location_lat, $location_lon, $location_description, $location_description1;
    
    public function __construct($json) {
        $this->json = $json;
        $this->minimap = "http://maps.googleapis.com/maps/api/staticmap?size=800x400&center=".$this->json->geolat.",".$this->json->geolng."&zoom=15&markers=icon:https://www.foxpost.hu/foxpost_terminals/map_widget2/marker.png%7Clabel:X%7C".$this->json->geolat.",".$this->json->geolng."";
        $this->id = $this->json->place_id;
        $this->name = $this->json->name;
        //$this->type = $this->json->name;
        //$this->services = $this->json->services;
        //$this->payment_type = $this->json->payment_type;
        $this->address_street = $this->json->address;
        //$this->address_building_no = $this->json->address->building_no;
        $this->address_city = $this->json->group;
        //$this->address_post_code = $this->json->address->post_code;
        //$this->address_province = $this->json->address->province;
        //$this->address_str = $this->json->address_str;
        $this->location_lat = $this->json->geolat;
        $this->location_lon = $this->json->geolng;
        $this->location_description = $this->json->findme;
        //@$this->location_description1 = $this->json->location_description1;
        return $this;
    }
    
    function fox_getTotal_count() {
        return $this->total_count;
    }

    function fox_getJson() {
        return $this->json;
    }

    function fox_getHref() {
        return $this->href;
    }

    function fox_getMinimap() {
        return $this->minimap;
    }
    
    function fox_getName() {
        return $this->name;
    }

    function fox_getId() {
        return $this->id;
    }

    function fox_getType() {
        return $this->type;
    }

    function fox_getServices() {
        return $this->services;
    }

    function fox_getPayment_type() {
        return $this->payment_type;
    }

    function fox_getAddress_street() {
        return $this->address_street;
    }

    function fox_getAddress_building_no() {
        return $this->address_building_no;
    }

    function fox_getAddress_city() {
        return $this->address_city;
    }

    function fox_getAddress_post_code() {
        return $this->address_post_code;
    }

    function fox_getAddress_province() {
        return $this->address_province;
    }

    function fox_getAddress_str() {
        return $this->address_str;
    }

    function fox_getLocation_lat() {
        return $this->location_lat;
    }

    function fox_getLocation_lon() {
        return $this->location_lon;
    }

    function fox_getLocation_description() {
        return $this->location_description;
    }

    function fox_getLocation_description1() {
        return $this->location_description1;
    }
}

?>
