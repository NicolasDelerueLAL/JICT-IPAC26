<?php

/* by Nicolas.delerue@ijclab.in2p3.fr

Redirect a contribution given by code to the Indico contribution page.

30.06.2026 Creation

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


if ($_SERVER["QUERY_STRING"]) {
    parse_str($_SERVER["QUERY_STRING"], $queryArray);
    //print($_SERVER["QUERY_STRING"]."\n");
    //print_r($queryArray);
} else {
    die("Please specify a contribution code\n");
}

if (!(str_contains($_SERVER["QUERY_STRING"],"code"))){
    die("Please specify a contribution code.\n");
}

load_contributions();

$contrib_id=false;
foreach($contributions as $contribution){
    if ($contribution["code"]==$queryArray["code"]){
        $contrib_id=$contribution["id"];
        break;
    }
}
if (!$contrib_id) {
    die("Contribution not found.\n");
} else {
    print("Contribution ID: <A HREF=https://indico.jacow.org/event/95/contributions/".$contrib_id."/>".$contrib_id."</A><BR/>\n");
    print("<script type=\"text/javascript\">\n");
    print("window.setTimeout(function(){\n");
    print("\n");
    //print("        // Move to a new location or you can do something else\n");
    print("        window.location.href = \"https://indico.jacow.org/event/95/contributions/".$contrib_id."/\";\n");
    print("\n");
    print("    }, 2000);");
    print("</script>\n");
}

?>