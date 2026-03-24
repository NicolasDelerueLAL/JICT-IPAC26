<?php

/* by Nicolas.delerue@ijclab.in2p3.fr

List all contributions, by ID, friendly ID, code and firdst author name and email
24.03.2026 Creation

*/


if (str_contains($_SERVER["QUERY_STRING"],"debug")){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} //if debug

require( '../config.php' );
require_lib( 'jict', '1.0' );
require_lib( 'indico', '1.0' );
require( 'ipac26_tools.php' );

$cfg =config( 'IPAC26', false, false );

$cfg['verbose'] =0;
//$cfg['disable_cache'] = true;

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;



$allowed_roles=array("SS" , "STU", "LCC", "REG" , "LPR");
if (empty(array_intersect( $allowed_roles, $_SESSION['indico_oauth']['user']['roles'] ))) {
    print("You don't have the right to access this page.<BR/>\n");
    print("You are identified as ".$_SESSION['indico_oauth']['user']['first_name']." ".$_SESSION['indico_oauth']['user']['last_name']."<BR/>\n");
    print("Your roles: ".implode(", ",$_SESSION['indico_oauth']['user']['roles'])."<BR/>\n");
    print("Expected roles: ".implode(", ",$allowed_roles)."<BR/>\n");
    die("End");
}
load_contributions(disable_contributions_cache: false);

foreach($contributions as $contribution){
    print("Contribution ID: <A HREF='https://indico.jacow.org/event/95/contributions/".$contribution["id"]."'/>".$contribution["id"]."</A>;");
    print(" Friendly ID: #".$contribution["friendly_id"]."; ");
    if (strlen($contribution["code"])>0){
        print(" contribution code: ".$contribution["code"]."; ");
    } else {
        print(" No contribution code ");
    }
    print(" Authors: ");
    foreach($contribution["persons"] as $person){
        print( $person["full_name"] . " ".$person["email"]." ");
        if ($person["is_speaker"]){
            print (" (speaker) ");
        }
        //print($person["role"]);
        print($person["author_type"]);
        print(" - ");
    }
    print("<BR/>\n");
}  //for each contribution



?>