<?php

/*
################################
# Foxpost Parcels              #
# Copyright ToHr               #
# 2015.09.18                   #
# Foxpost parcels delivery core#
################################
*/



if (isset($_GET['shop'])) {

    require_once(realpath(dirname(__FILE__).'/../../config/config.inc.php'));
    require_once(realpath(dirname(__FILE__).'/../../init.php')); 
    global $cart;
   
    if(is_numeric(substr($_GET['shop'], 0, 5))) {
        $context = Context::getContext();
        
        $json=file_get_contents("https://www.foxpost.hu/foxpost_terminals/foxpost_terminals.php");
        $js=json_decode($json);
   
        foreach ($js as $j) {
            
            if ($j->place_id == $_GET['shop']) {
                
               $context->cookie->__set("foxpost_automata_".$cart->id, $j->name." - (".$_GET['shop'].")");  
            }
            
        }
        
        

    }
    
    echo json_encode(array());
    
}




?>
