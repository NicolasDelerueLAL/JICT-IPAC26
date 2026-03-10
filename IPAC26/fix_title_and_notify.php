<?php

/* by Nicolas.delerue@ijclab.in2p3.fr

This page gets a contribution ID  as paramnters, takes its title and ask an AI to convert it to Sentence case for indico and uppercase for the template.
5.03.2026 Creation

*/


if (str_contains($_SERVER["QUERY_STRING"],"debug")){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} //if debug

require( '../config.php' );
require_lib( 'jict', '1.0' );
require_lib( 'indico', '1.0' );
//require( 'ipac26_tools.php' );

require_once('ipac26_tools.php');

$cfg =config( 'IPAC26', false, false );
$cfg["allow_roles"]=[];
$cfg['verbose'] =0;
//$cfg['disable_cache'] = true;

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;

if ($_SERVER["QUERY_STRING"]) {
    parse_str($_SERVER["QUERY_STRING"], $queryArray);
    //print($_SERVER["QUERY_STRING"]."\n");
    //print_r($queryArray);
}
if (str_contains($_SERVER["QUERY_STRING"],"contribution_id")){
    $contribution_id=$queryArray["contribution_id"];
} else {
    die("No contribution ID provided");
}
$contribs_qa_data=file_read_json(  $cws_config['global']['data_path']."/contribs_qa.json",true);

if ((!($contribs_qa_data))||(!(array_key_exists($contribution_id,$contribs_qa_data)))||(!(array_key_exists("title",$contribs_qa_data[$contribution_id])))){
        die("Title case not yet determined for this contribution.<BR/>\n");
} 

print("Title case: ".$contribs_qa_data[$contribution_id]["title"]["case"]."<BR/>\n");
print("Correct title case: ".$contribs_qa_data[$contribution_id]["title"]["sentence_case"]."<BR/>\n");
print("Date: ".$contribs_qa_data[$contribution_id]["title"]["date"]."<BR/>\n");
$req_json =$Indico->request( "/event/{id}/contributions/".$contribution_id.".json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true ) );
if (!($contribs_qa_data[$contribution_id]["title"]["fixed"]==true)){
    die("Title case not yet fixed for this contribution. Please fix it first before trying to update the contribution.<BR/>\n");
}
if ($req_json["title"]==$contribs_qa_data[$contribution_id]["title"]["sentence_case"]) {
    die("Already fixed and title matches the saved sentence case title. No update needed.");
    print("Already fixed and title matches the saved sentence case title. No update needed.<BR/>\n");
    print("Not dying! <BR/>\n");
} 

if(!($req_json["title"]==$contribs_qa_data[$contribution_id]["title"]["old"])) {
    die("Apparently the title does not match the saved data.<BR/>\n");
}

update_contribution_title($contribution_id,$contribs_qa_data[$contribution_id]["title"]["sentence_case"]);

$contribution_ids=array( $contribution_id );
$sender_email="editor@ipac26.org";
//$sender_email="delerue@lal.in2p3.fr";
$copy_for_sender=true;
$recipient_role=array ( "speaker" );
send_email_file_to_contributor("title_case_updated.txt",$recipient_role,$contribution_ids,$sender_email,$copy_for_sender,$req_json);


?>

 
