<?php

/* by Nicolas.delerue@ijclab.in2p3.fr

Creates a dictionnary of all contributions
9.03.2026 Creation

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

//global $contributions,$contributions_by_abs_id,$contributions_by_fr_id,$all_contributions;


$allowed_roles=array("SS" , "STU", "LCC", "REG");
if (empty(array_intersect( $allowed_roles, $_SESSION['indico_oauth']['user']['roles'] ))) {
    print("You don't have the right to access this page.<BR/>\n");
    print("You are identified as ".$_SESSION['indico_oauth']['user']['first_name']." ".$_SESSION['indico_oauth']['user']['last_name']."<BR/>\n");
    print("Your roles: ".implode(", ",$_SESSION['indico_oauth']['user']['roles'])."<BR/>\n");
    print("Expected roles: ".implode(", ",$allowed_roles)."<BR/>\n");
    die("End");
}
load_contributions(disable_contributions_cache: true);

$dictionnary["contribution_code"]=[];
$dictionnary["contribution_friendly_id"]=[];
foreach($contributions as $contribution){
    print("Contribution ID: ".$contribution["id"].",");
    $dictionnary["contribution_friendly_id"][$contribution["friendly_id"]] = $contribution["id"];
    print(" friendly ID: ".$contribution["friendly_id"].", ");
    if (strlen($contribution["code"])>0){
        $dictionnary["contribution_code"][$contribution["code"]] = $contribution["id"];
        print(" contribution code: ".$contribution["code"]);
    }
    print("<BR/>\n");
}  //for each contribution



file_put_contents($cws_config['global']['data_path']."/contribs_dictionnary.json",json_encode($dictionnary));
print("Saved dictionary<BR/>\n");


?>