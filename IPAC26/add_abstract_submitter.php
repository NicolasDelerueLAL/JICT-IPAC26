<?php

/* by Nicolas.delerue@ijclab.in2p3.fr

Adds an abstract submitter

11.02.2026 Creation

*/
//die("Not working: need to ass all fields from form???");

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

//load_contributions();
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

if (str_contains($_SERVER["QUERY_STRING"],"email")){
    print("Email ".$queryArray["email"].": <BR/>\n");
    //Get search token

    print("Get info<BR/>\n");
    $req =$Indico->request( "/event/{id}/manage/abstracts/settings", 'GET', array( ) , array( 'return_data' =>true, 'quiet' =>true ,'disable_cache' => true ) );
    //var_dump($req);
    print("Get authorized submitters<BR/>\n");
    $matches=[];
    $matchtxt='#name="authorized_submitters" value="(.*)"#';
    $returnValue = preg_match_all($matchtxt, $req["html"], $matches);
    //var_dump($matches);
    //$authorizedSubmitters=str_replace("&#34;",'"', $matches[1][0]); 
    $authorizedSubmitters= $matches[1][0]; 
    print(" authorizedSubmitters: $authorizedSubmitters\n");   
    print("<BR/>\n");
    //count authorized submitters
    $matches=[];
    $matchtxt='#User:#';
    $returnValue = preg_match_all($matchtxt, $authorizedSubmitters, $matches);
    //var_dump($matches);
    $nSubmitters=count($matches[0]);
    print("There are $nSubmitters submitters.<BR/>\n");

    //$searchToken=$matches[1][0];
    /*
    print("Get oher info<BR/>\n");
    $matches=[];
    $matchtxt='#<textarea (.*)\n\sname="(.*)"\sdata-no-mathjax\s\n\s>(.*?)</textarea>#';
    $matchtxt='#<textarea .+\n\s*name="(.+)"\s*.*\s*.*\s*.*\s*(.*?)\s*>(.*)textarea#s';
    $matchtxt='#textarea ([[:alnum:]\s="\-_%:;]*)>([[:alnum:]\s="\-_%:;]*)#s';
    $returnValue = preg_match_all($matchtxt, $req["html"], $matches);
    var_dump($matches);
    var_dump($req["html"]);
    die("here");
    */
    //$authorizedSubmitters=str_replace("&#34;",'"', $matches[1][0]); 
    print(" authorizedSubmitters: $authorizedSubmitters\n");   
    //$searchToken=$matches[1][0];
    print("<BR/>\n");
    print("Get search token<BR/>\n");
    $matches=[];
    $matchtxt='#searchToken: "(.*)"#';
    $returnValue = preg_match_all($matchtxt, $req["js"][1], $matches);
    $searchToken=$matches[1][0];
    print("search Token:".$searchToken);
    print("<BR/>\n");

    print("Search user<BR/>\n");
    $req =$Indico->request( "/user/search/?email=".$queryArray["email"]."&favorites_first=true&token=".$searchToken, 'GET', array( ) , array( 'return_data' =>true, 'quiet' =>true ) );
    print("Total ".$req["total"]."<BR/>\n");
    if ($req["total"]==0){
        print("No users found. Ask the user to create an indico account.<BR/>\n");
    } else if ($req["total"]>1){
        print("Too many users found. Please check.<BR/>\n");
    } else {
        print("One user found. Identifier: ". $req["users"][0]["identifier"]."<BR/>\n");
        //$authorizedSubmitters=substr($authorizedSubmitters,0,strlen($authorizedSubmitters)-1).',&#34;'. $req["users"][0]["identifier"].'&#34;]';
        $authorizedSubmitters=substr($authorizedSubmitters,0,strlen($authorizedSubmitters)-1).',&#34;'. $req["users"][0]["identifier"].'&#34;]';
        $authorizedSubmitters=str_replace("&#34;",'"', $authorizedSubmitters); 
        //$authorizedSubmitters=substr($authorizedSubmitters,0,strlen($authorizedSubmitters)-1).']';
        print("New authorizedSubmitters: ". $authorizedSubmitters);
        $req =$Indico->request( "/event/{id}/manage/abstracts/settings", 'POST', array( 'authorized_submitters' => $authorizedSubmitters ) , array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' => true) );
        //var_dump($req);
        print("\n<BR/>\n");
        print("Checking: <BR/>\n");
        $req =$Indico->request( "/event/{id}/manage/abstracts/settings", 'GET', array( ) , array( 'return_data' =>true, 'quiet' =>true ,  'disable_cache' => true ) );
        //var_dump($req);
        if ($req["html"]){
            print("Get authorized submitters<BR/>\n");

            $matches=[];
            $matchtxt='#name="authorized_submitters" value="(.*)"#';
            $returnValue = preg_match_all($matchtxt, $req["html"], $matches);
            $authorizedSubmitters=str_replace("&#34;",'"', $matches[1][0]); 
            print(" authorizedSubmitters: $authorizedSubmitters\n");   
            print("<BR/>\n");
            //count authorized submitters
            $matches=[];
            $matchtxt='#User:#';
            $returnValue = preg_match_all($matchtxt, $authorizedSubmitters, $matches);
            //var_dump($matches);
            $nSubmitters=count($matches[0]);
            print("There are $nSubmitters submitters.<BR/>\n");

            print("Sending notification to the submitter.<BR/>\n");
            send_email_to_participant("tst","test",true);
            
            $message=file_get_contents("submission_privileges.txt");
            print($message);
            /*
            $_POST["submit"]="Notify";
            $_POST['subject']=substr($message,0,strpos($message,"\n"));
            $_POST['body']=urlencode(str_replace("\n","<BR/>\n",str_replace("##email##", $queryArray["email"], substr($message,strpos($message,"\n")))));
            require('send_email_included.php');
            print("<BR/>\nMessage sent <BR/>\n");
            //var_dump($req);
            */
            print("Done\n");

        }
    }

} else {
    die("No email passed, exiting\n");
}

?>