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

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;


$Indico->load();

$disable_cache=true;

if (!($queryArray["contribution_id"])){
    die("No contribution ID given... Stop.");
}

//print("user\n");
//var_dump($user);
$this_person=get_person($user);
print("<!--- ");
print("\nThis person user_id : ".$this_person["user_id"]."\n\n\n\n");
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
        die("Sorry you have not been invited to review this paper");
    }
}

$contribution=get_contribution($queryArray["contribution_id"]);
if (!($contribution)){
    die("Unable to get contribution!");
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
$content .= "<BR/><BR/>\n";

$content .="Contribution: ".$queryArray["contribution_id"]."<BR/>\n";
$content .="Title: ".$contribution["title"]."<BR/>\n";
$content .="Abstract: ".$contribution["description"]."<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";

if ($_POST){
    if ($_POST["action"]=="accept"){
        print("Accept<BR/>\n");
        assign_reviewer_to_paper($queryArray["contribution_id"], $this_person["user_id"]);
        comment_paper($queryArray["contribution_id"],"Reviewer accepted ".$this_person["user_id"],use_session_token:false);
        //send_email_file_to_eventperson("message_referee_request.txt","EventPerson:".$this_person["user_id"],"peer-review@ipac26.org",true,$contribution);
        $content .="Thanks you for accepting to review this contribution.<BR/>\n";
        $content .="You can now access and download the paper for this contribution <A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/papers/". $queryArray["contribution_id"]."/' >here</A>.<BR/>\n";
        $content .="To leave a review on this paper, go <A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/papers/". $queryArray["contribution_id"]."/' >here</A>, click on \"Review\" and fill the form.<BR/>\n";
        
    } else {
        if ($_POST["action"]=="decline_no_review"){
            $content .="Thanks you for your answer. We are sorry that you are unavailable to review papers for IPAC'26.<BR/>\n";
        } else {
            $content .="Thanks you for your answer. We are sorry that you are unavailable to review this contribution.<BR/>\n";
        }
        comment_paper($queryArray["contribution_id"],"Reviewer declined ".$this_person["user_id"],use_session_token:false);
        comment_paper($queryArray["contribution_id"],"Reason given by ".$this_person["user_id"].": ".$_POST["action"],use_session_token:false);
    }
} else {
    $content .="Thanks you for your help with IPAC'26 Light Peer Review process. Will you be able to review the contribution described above?<BR/>\n";

    $content .="<form method='POST' action='paper_acceptance.php?contribution_id=".$queryArray["contribution_id"]."'>\n";
    $content .="<INPUT type='hidden' name='contribution_id' value='".$queryArray["contribution_id"]."'>\n";

    $content .="<INPUT type='radio' name='action' value='accept'> I accept to review this contribution.<BR/>\n";
    $content .="<BR/>\n";

    $content .="I am unable to review this contribution for the following reason:<BR/>\n";        
    $content .="<INPUT type='radio' name='action' value='decline_conflict'> I have a conflict of interest.<BR/>\n";        
    $content .="<INPUT type='radio' name='action' value='decline_out_of_field'> It is out of my expertise.<BR/>\n";        
    $content .="<INPUT type='radio' name='action' value='decline_temporarily_unavailable'> I am temporarily unavailable.<BR/>\n";        
    $content .="<INPUT type='radio' name='action' value='decline_no_review'> I am not able to review papers for IPAC'26.<BR/>\n";        

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