<?php

/* by Nicolas.delerue@ijclab.in2p3.fr

Allows to edit a contribution:
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

//global $contributions,$contributions_by_abs_id,$contributions_by_fr_id,$all_contributions;


$allowed_roles=array("SS" , "STU", "LCC", "REG");
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

print("<A HREF='".$_SERVER['REQUEST_URI']."&nochache=1'>Reload cache (slower)</A><BR/>\n");
if (isset($queryArray["nochache"])){
    print("Reloading contributions...<BR/>\n");
    load_contributions(disable_contributions_cache: true);
} else {
    print("Using contributions from cache...<BR/>\n");
    load_contributions();
}


if (str_contains($_SERVER["QUERY_STRING"],"contrib_id")){
    $contrib_id=$queryArray["contrib_id"];
    $abstract_id=$contributions[$contrib_id]["abstract_id"];
    print("Contribution ID: <A HREF=https://indico.jacow.org/event/95/contributions/".$contrib_id."/>".$contrib_id."</A><BR/>\n");
} else if (str_contains($_SERVER["QUERY_STRING"],"friendly_id")){
    $contrib_id=$contributions_by_fr_id[$queryArray["friendly_id"]];
    $abstract_id=$contributions[$contrib_id]["abstract_id"];
    print("Contribution ID: <A HREF=https://indico.jacow.org/event/95/contributions/".$contrib_id."/>".$contrib_id."</A><BR/>\n");
} else if (str_contains($_SERVER["QUERY_STRING"],"abstract_id")){
    $abstract_id=$queryArray["abstract_id"];
    print("Contribution ID: <A HREF=https://indico.jacow.org/event/95/contributions/".$contributions_by_abs_id[$abstract_id]."/>".$contributions_by_abs_id[$abstract_id]."</A><BR/>\n");
    if (!($contributions_by_abs_id[$abstract_id])){
        print("Unable to find contribution linked to this abstract, exiting.<BR/>\n");
        die("End");
    }
} else if (str_contains($_SERVER["QUERY_STRING"],"email")){
    print("Contribution(s) with email ".$queryArray["email"].": <BR/>\n");
    $nfound=0;
    foreach($contributions as $contribution){
        if (($contribution["primary_author_email"]==$queryArray["email"])||($contribution["speaker_email"]==$queryArray["email"])){
            print("title: ".$contribution["title"].", contribution ID: ".$contribution["id"]." Friendly ID: ".$contribution["friendly_id"].", abstract ID: ".$contribution["abstract_id"]."\n");
            print("<A HREF='".$_SERVER['REQUEST_URI']."&contrib_id=".$contribution["id"]."'>Select this contribution</A><BR/><BR/>\n");
            $nfound=$nfound+1;
        }
    }
    if ($nfound==0){
        print("No contribution found with this email, Looking at abstracts<BR/>\n");
        load_abstracts();
        foreach($abstracts as $abstract){
            if ($abstract["submitter_email"]==$queryArray["email"]){
                print("(submitter) title: ".$abstract["title"].", contribution ID: ".$contributions_by_abs_id[$abstract["id"]]." Friendly ID: ".$contributions[$contributions_by_abs_id[$abstract["id"]]]["friendly_id"].", abstract ID: ".$abstract["id"]."\n");
                print("<A HREF='https://indico.jacow.org/event/95/abstracts/".$abstract["id"]."/'>Select this abstract</A><BR/>\n");
            } else {
                foreach($abstract["persons"] as $author){
                    if ($author["email"]==$queryArray["email"]){
                        print("(author) title: ".$abstract["title"].", contribution ID: ".$contributions_by_abs_id[$abstract["id"]]." Friendly ID: ".$contributions[$contributions_by_abs_id[$abstract["id"]]]["friendly_id"].", abstract ID: ".$abstract["id"]."\n");
                            print("<A HREF='https://indico.jacow.org/event/95/abstracts/".$abstract["id"]."/'>Select this abstract</A><BR/>\n");
                    }
                }
            }

        }
        die("Completed search, please select one of the abstracts found above.<BR/>\n");
    } else{
         die("Please select one of the above contributions.<BR/>\n");
    }
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
$req =$Indico->request( $base_url , 'POST', $post_data,  array(  'return_data' =>true, 'quiet' =>true , 'disable_cache' => true));
//var_dump($post_data);
//echo json_encode($req);
//var_dump($req);

//var_dump($contributions[$contrib_id]);


print("Contribution linked to abstract <A HREF='https://indico.jacow.org/event/95/abstracts/$abstract_id/'>$abstract_id</A><BR/>\n");
print("Contribution ID: <A HREF=https://indico.jacow.org/event/95/contributions/".$contrib_id."/>".$contrib_id."</A><BR/>\n");
print("LPR paper (if exists): <A HREF=https://indico.jacow.org/event/95/papers/".$contrib_id."/>".$contrib_id."</A><BR/>\n");
print("Proceedings paper (if exists): <A HREF=https://indico.jacow.org/event/95/contributions/".$contrib_id."/editing/paper>".$contrib_id."</A><BR/>\n");
print("Contribution Friendly ID: ".$contributions[$contrib_id]["friendly_id"]."<BR/>\n");
print("Title: ".$req["abstracts"][0]["title"]."<BR/>\n");
print("Content: ".$req["abstracts"][0]["content"]."<BR/>\n");
print("Track: ".$contributions[$contrib_id]["track"]["title"]."<BR/>\n");
print("Type: ".$contributions[$contrib_id]["type"]["name"]."<BR/>\n");
print("Session: ".$contributions[$contrib_id]["session"]["title"]."<BR/>\n");
print("Schedule: ".$contributions[$contrib_id]["start_dt"]."<BR/>\n");
print("Code: ".$contributions[$contrib_id]["code"]."<BR/>\n");
print("Submitter: <BR/>\n");
print($req["abstracts"][0]["submitter"]["full_name"]." (".$req["abstracts"][0]["submitter"]["email"].")<BR/>\n");
print($req["abstracts"][0]["submitter"]["affiliation"]."<BR/>\n");
print("Authors: <BR/>\n");
foreach($contributions[$contrib_id]["persons"] as $person){
    print($person["full_name"]." (".$person["email"].") ".$person["author_type"]);
    if ($person["is_speaker"]){
        print(" (speaker)");
    }
    print("<BR/>\n");
}


//var_dump($contributions[$contrib_id]);

if (isset($queryArray["student_session"])){
    print("Duplicate to the student poster session...<BR/>\n");
    clone_contribution_to_student_session($contrib_id,$contributions[$contrib_id]["track"]);
} else if ((isset($queryArray["reschedule"]))||(isset($queryArray["unschedule"]))){
    print("Unscheduling contribution by moving it to the student poster session...<BR/>\n");
    $Indico->api->config('header_content_type', 'application/json');
    $post_data=array( "session_id" => "980" ,  ); //Student poster session
    $req =$Indico->request( "/event/{id}/manage/contributions/".$contrib_id, 'PATCH', $post_data ,  array(  'return_data' =>true, 'quiet' =>true, 'disable_cache' => true));
    if (!($Indico->api->response_code==200)){
        print("Response code: ". $Indico->api->response_code. "<BR/>\n");
        //die("Response code indicates an error, please investigate...");
    }
    print("Response code: ". $Indico->api->response_code. "<BR/>\n");

    print("Set session id back to poster session: <BR/>\n");
    $post_data=array( "session_id" => "1003" ,  ); //1003 poster session 
    $req =$Indico->request( "/event/{id}/manage/contributions/".$contrib_id, 'PATCH', $post_data ,  array(  'return_data' =>true, 'quiet' =>true, 'disable_cache' => true));
    //var_dump($req);
    if (!($Indico->api->response_code==200)){
        print("Response code: ". $Indico->api->response_code. "<BR/>\n");
        die("Response code indicates an error, please investigate...");
    }
    print("Response code: ". $Indico->api->response_code. "<BR/>\n");
    print("<BR/>\n");

    if (isset($queryArray["reschedule"])){
        $contribution=$contributions[$contrib_id];
        if (str_contains($contributions[$contrib_id]["type"]["name"],"Invited Oral")){
            die("Can not reschedule this type of contribution: ".$contributions[$contrib_id]["type"]["name"]);
        } elseif (str_contains($contributions[$contrib_id]["type"]["name"],"Contributed Oral")){
            die("Can not reschedule this type of contribution: ".$contributions[$contrib_id]["type"]["name"]);
        } elseif (str_contains($contributions[$contrib_id]["type"]["name"],"Invited poster")){
            $presentation_type="V";
        } elseif (str_contains($contribution["type"]["name"],"Poster Presentation")){
            $presentation_type="P";
        } else {
            print("Error determining presentation type for contribution ID: ".$contribution["id"]."<BR/>\n");
            die("Can not reschedule this type of contribution: ".$contributions[$contrib_id]["type"]["name"]);
        }
        $room_assign=[];
        if ($queryArray["reschedule"]=="monday"){
            $room_assign["date"]="2026/05/18";
            $room_assign["id"]="1454";
        } else if ($queryArray["reschedule"]=="tuesday"){
            $room_assign["date"]="2026/05/19";
            $room_assign["id"]="1455";
        } else if ($queryArray["reschedule"]=="wednesday"){
            $room_assign["date"]="2026/05/20";
            $room_assign["id"]="1456";
        } else if ($queryArray["reschedule"]=="thursday"){
            $room_assign["date"]="2026/05/21";
            $room_assign["id"]="1462";
        } else {
            die("Unable to classify contribution.");
        }
        print("Schedule contribution in room assigned for ".$queryArray["reschedule"]." (id: ".$room_assign["id"].", date: ".$room_assign["date"].")<BR/>\n");
        print("Set schedule:<BR/>\n");
        //1454  "2026/05/18" ; 1455  "2026/05/19"  ; 1456  "2026/05/20" ; 1462  "2026/05/21" ; 
        $post_data=array( "contribution_ids" => array ( intval($contrib_id) ) , "day" => $room_assign["date"] );
        $req =$Indico->request( "/event/{id}/manage/timetable/block/".$room_assign["id"]."/schedule", 'POST', $post_data ,  array(  'return_data' =>true, 'quiet' =>true, 'disable_cache' => true));
        //var_dump($req);
        if (!($Indico->api->response_code==200)){
            print("Response code: ". $Indico->api->response_code. "<BR/>\n");
            die("Response code indicates an error, please investigate...");
        }
        print("Response code: ". $Indico->api->response_code. "<BR/>\n");
        print("<BR/>\n");

        //reassigning contribution code
        $assigned_codes=[];
        $assigned_codes=file_read_json(  $cws_config['global']['data_path']."/assigned_codes.json",true);
        $session_date=substr($room_assign["date"],8,2);
        print("Session date: $session_date<BR/>\n");
        $days=Array("SU","MO","TU","WE","TH","FR");
        $session_day=$days[$session_date-17];
        $MC_number=substr($contribution["track"]["code"],2,1);
        print("MC number: $MC_number<BR/>\n");
        print("Region: ".$contribution["region"]."<BR/>\n");
        $session_prefix=$session_day.$presentation_type.$MC_number;
        $key=$session_prefix;
        print("Contribution ID: $contrib_id, region: ".$contribution["region"]."<BR/>\n");
        $format="%03d";
        if ($contribution["region"]=="EMEA"){
            $counter=1;
        } else if ($contribution["region"]=="Americas"){
            $counter=301;
        } else if ($contribution["region"]=="Asia"){
            $counter=601;
        } else {
            die("Unable to identify region");
        }
        $number=sprintf($format,$counter);
        $contribution_code=$key.$number;
        while (in_array($contribution_code,array_keys($assigned_codes))){
            print("Error: contribution code $contribution_code already assigned to contribution ID: ".$assigned_codes[$contribution_code]."<BR/>\n");
            $counter++;
            $number=sprintf($format,$counter);
            $contribution_code=$key.$number;
        }
        $contributions[$contribution["id"]]["code"]=$contribution_code;
        print("contribution_code: $contribution_code<BR/>\n");
        $assigned_codes[$contribution_code]=$contribution["id"];

        $ret=assign_contribution_code($contribution["id"],$contribution_code);
        if ($ret["value"]){
            print("Code assigned successfully<BR/>\n");
        } else {
            print("Error assigning code: ".$ret["content"]."<BR/>\n");
        }

        print("Send email:<BR/>\n");
        $recipient_role=array ( "speaker" );
        $contribution_id=array( $contrib_id );
        $copy_for_sender=true;
        send_email_file_to_contributor_as_editor("contribution_reassigned_to_poster_session.txt",$recipient_role,$contribution_id,$copy_for_sender,$contributions[$contrib_id]);
        $fwret=file_write_json(  $cws_config['global']['data_path']."/assigned_codes.json",$assigned_codes);
        print($fwret?"Contribution codes saved successfully<BR/>\n":"Error saving contribution codes<BR/>\n");
    }
    print("Fixing contributions cache...<BR/>\n");
    load_contributions(disable_contributions_cache: true);

    print("Done\n");
} else if ((isset($queryArray["confirm_withdraw"]))&&($queryArray["confirm_withdraw"]=="1")){
    print("Confirmed, withdrawing contribution...<BR/>\n");

    if ($abstract_id){
        print("Resetting judgement...<BR/>\n");
        $req =$Indico->request( "/event/{id}/abstracts/".$abstract_id."/reset", 'POST', array( ) , array( 'return_data' =>true, 'quiet' =>true ) );
        print($req["flashed_messages"]);

        print("Adding comment...<BR/>\n");
        $req =$Indico->request( "/event/{id}/abstracts/".$abstract_id."/comment", 'POST', array( 'text' => "Contribution withdrawn by ".$_SESSION['indico_oauth']['user']['first_name']." ".$_SESSION['indico_oauth']['user']['last_name']."\nReason: ".$queryArray["reason"], 'visibility' => "reviewers" ) , array( 'return_data' =>true, 'quiet' =>true ) );
        print($req["success"]."<BR/>\n");

        print("Deleting contribution...<BR/>\n");
        $req =$Indico->request( "/event/{id}/manage/contributions/".$contributions_by_abs_id[$abstract_id], 'DELETE', array( ) , array( 'return_data' =>true, 'quiet' =>true ) );
        print($req["flashed_messages"]);
    } else {
        print("Deleting contribution (no abstract)...<BR/>\n");
        $req =$Indico->request( "/event/{id}/manage/contributions/".$contrib_id, 'DELETE', array( ) , array( 'return_data' =>true, 'quiet' =>true ) );
        print($req["flashed_messages"]);
    }
    if (!($queryArray["silent"])){
        //sending message to submitter
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
    }
    print("Marking abstract as withdrawn...<BR/>\n");
    $req =$Indico->request( "/event/{id}/abstracts/".$abstract_id."/withdraw", 'POST', array( 'text' => "Contribution withdrawn by ".$_SESSION['indico_oauth']['user']['first_name']." ".$_SESSION['indico_oauth']['user']['last_name']."\nReason: ".$queryArray["reason"], 'visibility' => "reviewers" ) , array( 'return_data' =>true, 'quiet' =>true ) );
    print($req["success"]."<BR/>\n");
    print("Fixing contributions cache...<BR/>\n");
    load_contributions(disable_contributions_cache: true);

    print("Done\n");
} else {
    print("<BR/>\n<BR/>\n<b>Would you like to reschedule this contribution?</b> <BR/>\n");
    print("<A HREF='".$_SERVER['REQUEST_URI']."&reschedule=monday'>Yes, reschedule it to Monday 18th 's poster session - MC1 (Europe, Middle-East and Africa), MC6 (Americas and Asia), MC7 (Europe, Middle-East and Africa) and MC8 (Americas and Asia).</A><BR/>\n");
    print("<A HREF='".$_SERVER['REQUEST_URI']."&reschedule=tuesday'>Yes, reschedule it to Tuesday 19th 's poster session - MC2 (Americas and Asia), MC3 (Europe, Middle-East and Africa), MC7 (Americas and Asia), MC8 (Europe, Middle-East and Africa)
</A><BR/>\n");
    print("<A HREF='".$_SERVER['REQUEST_URI']."&reschedule=wednesday'>Yes, reschedule it to Wednesday 20th 's poster session - MC1 (Americas and Asia), MC4 (Americas and Asia), MC5 (Europe, Middle-East and Africa), MC6 (Europe, Middle-East and Africa)</A><BR/>\n");
    print("<A HREF='".$_SERVER['REQUEST_URI']."&reschedule=thursday'>Yes, reschedule it to Thursday 21st 's poster session - MC2 (Europe, Middle-East and Africa), MC3 (Americas and Asia), MC4 (Europe, Middle-East and Africa), MC5 (Americas and Asia)</A><BR/>\n");
    print("<BR/>\n<BR/>\n<b>Student session</b> <BR/>\n");
    print("<A HREF='".$_SERVER['REQUEST_URI']."&student_session=1'>Duplicate to the student session</A><BR/>\n");
    print("<BR/>\n<BR/>\n<b>Would you like to unschedule this contribution?</b> <BR/>\n");
    print("<A HREF='".$_SERVER['REQUEST_URI']."&unschedule=1'>Yes, unschedule this contribution</A><BR/>\n");
    print("<BR/>\n<BR/>\n<b>Would you like to withdraw this contribution?</b> <BR/>\n");
    print("<A HREF='".$_SERVER['REQUEST_URI']."&confirm_withdraw=1&reason=authors%20request'>Yes, withdraw at the request of the author</A><BR/>\n");
    print("<A HREF='".$_SERVER['REQUEST_URI']."&confirm_withdraw=1&reason=other'>Yes, withdraw for other reasons</A><BR/>\n");
    print("<A HREF='".$_SERVER['REQUEST_URI']."&confirm_withdraw=1&reason=admin&silent=1'>Yes, withdraw for admin reasons (silent)</A><BR/>\n");
}

/*

$abs_id=109;
$req =$Indico->request( "/event/{id}/abstracts/".$abs_id."/comment", 'POST', array( 'text' => "test2" , 'visibility' => "judges") , array( 'return_data' =>true, 'quiet' =>true ) );
//$req =$Indico->request( "/event/{id}/abstracts/".$abs_id."/comment", 'POST', false , array( 'return_data' =>true, 'quiet' =>true ) );
var_dump($req);
var_dump($abs_id);
*/

?>