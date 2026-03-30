<?php
/* by nicolas.delerue@ijclab.in2p3.fr 
sens a reminder to a reviewer

2026.03.26 - Created by nicolas.delerue@ijclab.in2p3.fr

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

require('peer_review_functions.php');


$cfg =config( 'LightPeerReviewAdmin' );
$cfg['verbose'] =1;

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;

check_lpr_rights();

$Indico->load();


load_papers($disable_cache);

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



$content ="";

$content .="<A HREF='".$_SERVER['REQUEST_URI']."&nochache=1'>Reload cache (slower)</A><BR/>\n";
$disable_cache=true;
if (isset($queryArray["nochache"])){
    $content .="Reloading contributions...<BR/>\n";
    $disable_cache=true;
} else {
    $content .="Using papers from cache...<BR/>\n";
    $disable_cache=false;
}

$content .="<A HREF='list_participants.php'>Go to the list of participants</A><BR/><BR/>\n";
$content .="<A HREF='list_papers.php'>Go to the list of papers</A><BR/><BR/>\n";

$content .="<BR/><BR/>\n";

if (!($queryArray["contribution_id"])){
    die("No contribution ID given... Stop.");
}

$action_url="https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

$reviewers=get_reviewers_for_contribution($queryArray["contribution_id"]);
//var_dump($reviewers);
$reviewers_txt="";
$overdue=false;
if (($reviewers)&&(count($reviewers)>0)){            
    $content .="There are ".count($reviewers)." reviewers.<BR/>\n";
    $reviewers_txt.="<ol>";
    foreach($reviewers as $reviewer){
        $reminder=false;
        $rev_txt="";
        $rev_txt.=ucfirst($reviewer["action"]).": ".$reviewer["name"]." ( ".$reviewer["id"]." ".$reviewer["email"].")";
        if ($reviewer["date"]){
            $rev_txt.=" on ".substr($reviewer["date"],0,10)." (";
            $days_ago=round((time()-strtotime($reviewer["date"]))/(60*60*24));
            $rev_txt.=$days_ago." day";
            if ($days_ago>1){
                $rev_txt.="s";
            }
            $rev_txt.=" ago) \n";      
            if (strlen($reviewer["reminder"])>0){
                $reminder=true;
                $rev_txt.="reminded on ";
                $rev_txt.=substr($reviewer["reminder"],0,10)." (";
                $days_ago=round((time()-strtotime($reviewer["reminder"]))/(60*60*24));
                $rev_txt.=$days_ago." day";
                if ($days_ago>1){
                    $rev_txt.="s";
                }
                $rev_txt.=" ago) \n";      
            }
            if (
                (($reviewer["action"]=="accepted")&&($days_ago>=$days_for_review))
                ||(($reviewer["action"]=="invited")&&($days_ago>=$days_to_accept_invitation))
                ||(($reminder)&&($days_ago>=$days_after_reminder))
                )
                {
                $text_form="<form method='POST' action='$action_url'>\n";
                $text_form .="<INPUT type='hidden' name='contribution_id' value='".$queryArray["contribution_id"]."'>\n";
                $text_form .="<INPUT type='hidden' name='reviewer' value='".$reviewer["id"]."'>\n";
                $text_form .="<input type='submit' value='Send reminder to this reviewer'><BR/>\n";
                $text_form .="</form>\n";

                $rev_txt="<b style='color:red;'> Overdue: ".$rev_txt."</b>";
                $overdue=true;
                //var_dump($reviewer);
                if (($_POST)&&(($_POST["reviewer"]==$reviewer["id"])||($_POST["reviewer"]=="all"))){
                    $bcc_address_array=array( "peer-review@ipac26.org"  );
                    $use_indico_token=true;
                    $use_session_token=false;        
                    //print("use_indico_token: $use_indico_token ,use_session_token: $use_session_token \n");
                    $copy_for_sender=false;
                    //var_dump($contributions[$queryArray["contribution_id"]]);
                    if ($reviewer["action"]=="invited"){
                        $reminder_file="message_reminder_referee_request.txt";
                    } else {
                        $reminder_file="message_reminder_review_overdue.txt";
                    }
                    $result=send_email_file_to_eventperson($reminder_file,"EventPerson:".$reviewer["event_id"],"peer-review@ipac26.org",$copy_for_sender,$contributions[$queryArray["contribution_id"]],$bcc_address_array,use_indico_token:$use_indico_token,use_session_token: $use_session_token);
                    //var_dump($result);
                    if ($result){
                        $rev_txt.=" Message sent succesfully.";
                        print("Comment:\n");
                        comment_paper($queryArray["contribution_id"],"Reminder sent to ".$reviewer["id"],use_indico_token:true,use_session_token:false);
                    }else{
                        $rev_txt.=" Sending message failed.";
                    }
                } else {
                    $rev_txt.=" ".$text_form;
                }
            } 
        }
        $reviewers_txt.="<li>".$rev_txt." </li>\n";  
    }
    $reviewers_txt.="</ol>";
} else {
    $reviewers_txt.="No reviewer";
}

$content.=$reviewers_txt;

if ($overdue){
    $text_form="<form method='POST' action='$action_url'>\n";
    $text_form .="<INPUT type='hidden' name='contribution_id' value='".$queryArray["contribution_id"]."'>\n";
    $text_form .="<INPUT type='hidden' name='reviewer' value='all'>\n";
    $text_form .="<input type='submit' value='Send reminder to all overdue reviewer'><BR/>\n";
    $text_form .="</form>\n";
    if ((!$_POST)||(!$_POST["reviewer"])){
        $content.=$text_form;
    }
}

$content .= "<BR/><BR/>\n";
$content .= "<BR/><BR/>\n";
$content .= "<BR/><BR/>\n";
$T->set( 'content', $content );
$T->set( 'txt5_txt', "" );
$T->set( 'txt5_val', "" );



//$T->set( 'papers', json_decode($req_papers,true)['papers'] );
//$T->set( 'abstracts', count($abstracts) );
//$T->set( 'contributions', count($contributions) );
//$T->set( 'column_width', $column_width);

/*
$T->set( 'todo_n', $todo_n );
$T->set( 'done_n', $done_n );
$T->set( 'undone_n', $undone_n );
$T->set( 'all_n', $todo_n +$done_n );
*/
echo $T->get();

//print("done");

?>