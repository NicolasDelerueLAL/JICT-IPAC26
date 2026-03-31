<?php
/* by nicolas.delerue@ijclab.in2p3.fr 
Allows a reviewer to accept (or not) a paper

2026.01.16 - Created by nicolas.delerue@ijclab.in2p3.fr

*/

if (str_contains($_SERVER["QUERY_STRING"],"debug")){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} //if debug on


if ($_SERVER["QUERY_STRING"]) {
    parse_str($_SERVER["QUERY_STRING"], $queryArray);
    //print($_SERVER["QUERY_STRING"]."\n");
    //print_r($queryArray);
}

require( '../config.php' );
require_lib( 'jict', '1.0' );
require_lib( 'indico', '1.0' );

require('../LightPeerReviewAdmin/peer_review_functions.php');
//require('../IPAC26/ipac26_tools.php');


$cfg =config( 'LightPeerReview' );
$cfg['verbose'] =1;
$cfg["allow_roles"]=[];

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;


$Indico->load();

$disable_cache=true;

if (!($queryArray["contribution_id"])){
    die("No contribution ID given... Stop.");
}






$T =new TMPL( $cfg['template'] );
$T->set([
    'style' =>'main { font-size: 22px; } main ul { margin: 20px; }',
    'title' =>$cfg['name'],
    'logo' =>$cfg['logo'],
    'conf_name' =>$cfg['conf_name'],
    'user' =>__h( 'small', $user['full_name'] ),
    'path' =>'../',
    'head' =>"<link rel='stylesheet' type='text/css' href='../page_edots/colors.css' />
    <link rel='stylesheet' type='text/css' href='style.css' />",
    'scripts' =>"",
    'js' =>false
    ]);



$content="";

//print("user\n");
//var_dump($user);
$this_person=get_person($user);

if ($queryArray["force_user_by_email"]){
    check_lpr_rights();
    print("Forcing user to ". $queryArray["force_user_by_email"]."\n");
    load_contributions();
    $content .= "<BR/><b>Forcing reviewer acceptance on behalf of ".$queryArray["force_user_by_email"]."!</b><BR/>\n";
    $this_person=get_participant("email",$queryArray["force_user_by_email"]);
    $content .= "\nThis person user_id : ".$this_person["user_id"]."\n\n";
    $content .= "<BR/>\n";
    $content .= "<BR/>\n";
}

print("<!--- ");
print("\nThis person user_id : ".$this_person["user_id"]."\n\n");
print("\n--->\n");

$paper_reviewer=get_paper_reviewers_status($queryArray["contribution_id"]);

if (!(in_array($this_person["user_id"],$paper_reviewer["invited"]))){
    if ((in_array($this_person["user_id"],$paper_reviewer["uninvited"]))){
        die("The invitation we sent you has expired");
    } if ((in_array($this_person["user_id"],$paper_reviewer["accepted"]))){
        die("The invitation we sent you has been accepted.");
    } if ((in_array($this_person["user_id"],$paper_reviewer["declined"]))){
        die("The invitation we sent you has been declined");
    } else {
        die("Sorry you ".$this_person["full_name"]." - ".$this_person["email"]." (".$this_person["user_id"].") have not been invited to review this paper");
    }
}

$contribution=get_contribution($queryArray["contribution_id"]);
if (!($contribution)){
    die("Unable to get contribution!");
}


$content .= "<BR/><BR/>\n";

$content .="Contribution: ".$queryArray["contribution_id"]."<BR/>\n";
$content .="Title: ".$contribution["title"]."<BR/>\n";
$content .="Abstract: ".$contribution["description"]."<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";


if ($_POST){
    $reviewers_info=file_read_json( $cws_config['global']['data_path']."/reviewers_info.json",true);
    if (!(array_key_exists($this_person["user_id"],$reviewers_info))){
        $reviewers_info[$this_person["user_id"]]=[];
        //print("Creating entry \n");
    }
    $sender="peer-review@ipac26.org";
    $bcc_address_array=array( "peer-review@ipac26.org" );
    $lpr_manager="EventPerson:35761";
    $use_indico_token=true;
    $use_session_token=false;
    $copy_for_sender=false;
    if ($_POST["action"]=="accept"){
        assign_reviewer_to_paper($queryArray["contribution_id"], $this_person["user_id"]);
        send_email_file_to_eventperson("message_thank_you_accept.txt","EventPerson:".$this_person["id"],$sender,$copy_for_sender,$contribution,$bcc_address_array,use_session_token:$use_session_token,use_indico_token:$use_indico_token);
        //print("<BR/><BR/><BR/><BR/><BR/>comment<BR/>\n");
        comment_paper($queryArray["contribution_id"],"Reviewer accepted ".$this_person["user_id"],use_indico_token:true,use_session_token:false);
        $content .="Thanks you for accepting to review this contribution.<BR/>\n";
        $content .="You can now access and download the paper for this contribution <A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/papers/". $queryArray["contribution_id"]."/' >here</A>.<BR/>\n";
        $content .="To leave a review on this paper, go <A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/papers/". $queryArray["contribution_id"]."/' >here</A>, click on \"Review\" and fill the form.<BR/>\n";
        sleep(0.1);
        send_email_file_to_eventperson("reviewer_accepted.txt",$lpr_manager,$sender,$copy_for_sender,$contribution,$bcc_address_array,use_session_token:$use_session_token,use_indico_token:$use_indico_token);
        $reviewers_info[$this_person["user_id"]][$queryArray["contribution_id"]]="accepted";        
    } else {
        if (($_POST["action"]=="decline_no_review")||($_POST["action"]=="decline_not_eligible")){
            $content .="Thanks you for your answer. We are sorry that you are unavailable to review papers for IPAC'26.<BR/>\n";
            send_email_file_to_eventperson("message_thank_you_no_review.txt","EventPerson:".$this_person["id"],$sender,$copy_for_sender,$contribution,$bcc_address_array,use_session_token:$use_session_token,use_indico_token:$use_indico_token);
            $reviewers_info[$this_person["user_id"]][$queryArray["contribution_id"]]="declined";
            $reviewers_info[$this_person["user_id"]]["unavailable"]=true;
        //print("<BR/><BR/><BR/><BR/><BR/>comment<BR/>\n");
        } else if ($_POST["action"]=="decline_no_reply"){
            $content .="Recorded that there were no reply from the reviewer<BR/>\n";
            send_email_file_to_eventperson("message_no_reply.txt","EventPerson:".$this_person["id"],$sender,$copy_for_sender,$contribution,$bcc_address_array,use_session_token:$use_session_token,use_indico_token:$use_indico_token);
        //print("<BR/><BR/><BR/><BR/><BR/>comment<BR/>\n");
            $reviewers_info[$this_person["user_id"]][$queryArray["contribution_id"]]="declined";
        } else {
            $content .="Thanks you for your answer. We are sorry that you are unavailable to review this contribution.<BR/>\n";
            send_email_file_to_eventperson("message_thank_you_unavailable.txt","EventPerson:".$this_person["id"],$sender,$copy_for_sender,$contribution,$bcc_address_array,use_session_token:$use_session_token,use_indico_token:$use_indico_token);
        //print("<BR/><BR/><BR/><BR/><BR/>comment<BR/>\n");
            $reviewers_info[$this_person["user_id"]][$queryArray["contribution_id"]]="declined";
        }
        comment_paper($queryArray["contribution_id"],"Reviewer declined ".$this_person["user_id"],use_indico_token:true,use_session_token:false);
        comment_paper($queryArray["contribution_id"],"Reason given by ".$this_person["user_id"].": ".$_POST["action"],use_indico_token:true,use_session_token:false);
        send_email_file_to_eventperson("reviewer_declined.txt",$lpr_manager,$sender,$copy_for_sender,$contribution,$bcc_address_array,use_session_token:$use_session_token,use_indico_token:$use_indico_token);
    }
    $fwret=file_write_json(  $cws_config['global']['data_path']."/reviewers_info.json",$reviewers_info);
} else {
    //$content .="Thanks you for your help with IPAC'26 Light Peer Review process. Will you be able to review the contribution described above?<BR/>\n";

    $content .="Thank you for your help with IPAC'26 Light Peer Review process.  As a reviewer, your job is to review the submitted papers and to propose that the paper be accepted, be rejected or be corrected by its author. The LPR manager will act as Judge, taking your feedback into account when deciding what to do.  We require a Ph.D. equivalent self-assessed experience for taking care of this task. More information will be sent to you after acceptance. Will you be able to review the contribution described above? <BR/>\n";
    $action_url="paper_acceptance.php?contribution_id=".$queryArray["contribution_id"];
    if (str_contains($_SERVER["QUERY_STRING"],"debug")){
        $action_url.="&debug";
    }
    if ($queryArray["force_user_by_email"]){
        $action_url.="&force_user_by_email=".$queryArray["force_user_by_email"];
    }
    $content .="<form method='POST' action='$action_url'>\n";
    $content .="<INPUT type='hidden' name='contribution_id' value='".$queryArray["contribution_id"]."'>\n";

    $content .="<INPUT type='radio' name='action' value='accept'> I accept to review this contribution.<BR/>\n";
    $content .="<BR/>\n";

    $content .="I am unable to review this contribution for the following reason:<BR/>\n";        
    $content .="<INPUT type='radio' name='action' value='decline_conflict'> I have a conflict of interest.<BR/>\n";        
    $content .="<INPUT type='radio' name='action' value='decline_out_of_field'> It is out of my expertise.<BR/>\n";        
    $content .="<INPUT type='radio' name='action' value='decline_not_eligible'> I am not eligible to be a reviewer (student,...).<BR/>\n";        
    $content .="<INPUT type='radio' name='action' value='decline_temporarily_unavailable'> I am temporarily unavailable.<BR/>\n";        
    $content .="<INPUT type='radio' name='action' value='decline_no_review'> I am not able to review papers for IPAC'26.<BR/>\n";        
    if ($queryArray["force_user_by_email"]){
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<INPUT type='radio' name='action' value='decline_no_reply'> Reviewer did not reply.<BR/>\n";        
    }
    $content .="<input type='submit' value='Submit'><BR/>\n";
    $content .="</form>\n";
    $content .="<BR/>\n";
    $content .="<BR/>\n";
}
$content .= "<BR/><BR/>\n";
$content .= "<BR/><BR/>\n";
$content .= "<BR/><BR/>\n";
$T->set( 'content', $content );

echo $T->get();


?>