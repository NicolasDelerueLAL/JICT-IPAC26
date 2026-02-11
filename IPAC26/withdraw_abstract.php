<?php

/* by Nicolas.delerue@ijclab.in2p3.fr

Allows to withdraw a contribution:
- reset judgement
- record comment that the contribution has been wthdrawn
- delete contribution

10.02.2026 Creation

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

load_contributions();
//global $contributions,$contributions_by_abs_id,$contributions_by_fr_id,$all_contributions;


$allowed_roles=array("SS");
if (empty(array_intersect( $allowed_roles, $_SESSION['indico_oauth']['user']['roles'] ))) {
    print("You don't have the right to access this page.<BR/>\n");
    print("You are identified as ".$_SESSION['indico_oauth']['user']['first_name']." ".$_SESSION['indico_oauth']['user']['last_name']."<BR/>\n");
    print("Your roles: ".implode(", ",$_SESSION['indico_oauth']['user']['roles'])."<BR/>\n");
    print("Expected roles: ".implode(", ",$allowed_roles)."<BR/>\n");
    die("End");
}

if ($_SERVER["QUERY_STRING"]) {
    parse_str($_SERVER["QUERY_STRING"], $queryArray);
    //print($_SERVER["QUERY_STRING"]."\n");
    //print_r($queryArray);
} else {
    die("No GET arguments passed, exiting\n");
}
if (str_contains($_SERVER["QUERY_STRING"],"contrib_id")){
    $contrib_id=$queryArray["contrib_id"];
    $abstract_id=$contributions[$contrib_id]["abstract_id"];
} else if (str_contains($_SERVER["QUERY_STRING"],"friendly_id")){
    $contrib_id=$contributions_by_fr_id[$queryArray["friendly_id"]];
    $abstract_id=$contributions[$contrib_id]["abstract_id"];
} else if (str_contains($_SERVER["QUERY_STRING"],"abstract_id")){
    $abstract_id=$queryArray["abstract_id"];
    print("Contribution ID: ".$contributions_by_abs_id[$abstract_id]."<BR/>\n");
    if (!($contributions_by_abs_id[$abstract_id])){
        print("Unable to find contribution linked to this abstract, exiting.<BR/>\n");
        die("End");
    }
} else if (str_contains($_SERVER["QUERY_STRING"],"email")){
    print("Contribution(s) with email ".$queryArray["email"].": <BR/>\n");
    foreach($contributions as $contribution){
        if ($contribution["primary_author_email"]==$queryArray["email"]){
            print("title: ".$contribution["title"].", contribution ID: ".$contribution["id"]." Friendly ID: ".$contribution["friendly_id"].", abstract ID: ".$contribution["abstract_id"]."\n");
            print("<A HREF='".$_SERVER['REQUEST_URI']."&contrib_id=".$contribution["id"]."'>Select this contribution</A><BR/><BR/>\n");
        }
    }
    die("Please select one of the above contributions.<BR/>\n");
} else {
    die("No abstract ID passed, exiting\n");
}


/*
        $contributions[$contribution["id"]]=$contribution;
        $contributions_by_fr_id[$contribution["friendly_id"]]=$contribution["id"];
        if ($contribution["abstract_id"]){
            $contributions_by_abs_id[$contribution["abstract_id"]]=$contribution["id"];
*/

$post_data=array( "abstract_id" => $abstract_id );
$base_url="/event/".$cws_config['global']['indico_event_id']."/manage/abstracts/abstracts.json";
//echo "base_url $base_url \n";
$req =$Indico->request( $base_url , 'POST', $post_data,  array(  'return_data' =>true, 'quiet' =>true));
//var_dump($post_data);
//echo json_encode($req);
//var_dump($req);

print("Withdrawing contribution linked to abstract $abstract_id<BR/>\n");
print("Contribution ID: ".$contributions_by_abs_id[$abstract_id]."<BR/>\n");
print("Contribution Friendly ID: ".$contributions[$contrib_id]["friendly_id"]."<BR/>\n");
print("Title: ".$req["abstracts"][0]["title"]."<BR/>\n");
print("Content: ".$req["abstracts"][0]["content"]."<BR/>\n");
print("Submitter: <BR/>\n");
print($req["abstracts"][0]["submitter"]["full_name"]." (".$req["abstracts"][0]["submitter"]["email"].")<BR/>\n");
print($req["abstracts"][0]["submitter"]["affiliation"]."<BR/>\n");
//var_dump($req["abstracts"]);

if ((isset($queryArray["confirm"]))&&($queryArray["confirm"]=="1")){
    print("Confirmed, withdrawing contribution...<BR/>\n");

    print("Resetting judgement...<BR/>\n");
    $req =$Indico->request( "/event/{id}/abstracts/".$abstract_id."/reset", 'POST', array( ) , array( 'return_data' =>true, 'quiet' =>true ) );
    print($req["flashed_messages"]);

    print("Adding comment...<BR/>\n");
    $req =$Indico->request( "/event/{id}/abstracts/".$abstract_id."/comment", 'POST', array( 'text' => "Contribution withdrawn by ".$_SESSION['indico_oauth']['user']['first_name']." ".$_SESSION['indico_oauth']['user']['last_name']."\nReason: ".$queryArray["reason"], 'visibility' => "reviewers" ) , array( 'return_data' =>true, 'quiet' =>true ) );
    print($req["success"]."<BR/>\n");

    print("Deleting contribution...<BR/>\n");
    $req =$Indico->request( "/event/{id}/manage/contributions/".$contributions_by_abs_id[$abstract_id], 'DELETE', array( ) , array( 'return_data' =>true, 'quiet' =>true ) );
    print($req["flashed_messages"]);

    //sending message to submitted
    print("Sending notification to the submitter.<BR/>\n");
    $message=file_get_contents("abstract_withdrawal_submitter_notification.txt");
    $_POST["submit"]="Notify";
    $_POST['abstract_id']=$abstract_id;
    $_POST['subject']=substr($message,0,strpos($message,"\n"));
    $_POST['body']=urlencode(str_replace("\n","<BR/>\n",str_replace("##abstract_id##", $_POST["abstract_id"], str_replace("##problem##", $_POST['reasons'], substr($message,strpos($message,"\n"))))));
    $_POST['role']="submitter";
    $_POST["comment"]="Contribution withdrawn. Reason: ".$queryArray["reason"];
    require('send_email_included.php');
    print("<BR/>\nMessage sent <BR/>\n");
    //var_dump($req);

    print("Marking abstract as withdrawn...<BR/>\n");
    $req =$Indico->request( "/event/{id}/abstracts/".$abstract_id."/withdraw", 'POST', array( 'text' => "Contribution withdrawn by ".$_SESSION['indico_oauth']['user']['first_name']." ".$_SESSION['indico_oauth']['user']['last_name']."\nReason: ".$queryArray["reason"], 'visibility' => "reviewers" ) , array( 'return_data' =>true, 'quiet' =>true ) );
    print($req["success"]."<BR/>\n");

    print("Done\n");
} else {
    print("<BR/>\n<BR/>\nAre you sure you want to withdraw this contribution? <BR/>\n");
    print("<A HREF='".$_SERVER['REQUEST_URI']."&confirm=1&reason=authors%20request'>Yes, withdraw at the request of the author</A><BR/>\n");
    print("<A HREF='".$_SERVER['REQUEST_URI']."&confirm=1&reason=other'>Yes, withdraw for other reasons</A><BR/>\n");
}

/*

$abs_id=109;
$req =$Indico->request( "/event/{id}/abstracts/".$abs_id."/comment", 'POST', array( 'text' => "test2" , 'visibility' => "judges") , array( 'return_data' =>true, 'quiet' =>true ) );
//$req =$Indico->request( "/event/{id}/abstracts/".$abs_id."/comment", 'POST', false , array( 'return_data' =>true, 'quiet' =>true ) );
var_dump($req);
var_dump($abs_id);
*/

?>