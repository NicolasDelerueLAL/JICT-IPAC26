<?php
/* by nicolas.delerue@ijclab.in2p3.fr 
assign a reviewer to a paper

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

require('peer_review_functions.php');


$cfg =config( 'LightPeerReviewAdmin' );
$cfg['verbose'] =1;

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;

check_lpr_rights();

$Indico->load();

$disable_cache=false;
load_papers($disable_cache);

//All participants files
$all_persons=get_participants(force_update:false);
if (!($all_persons)){
    die("Unable to read participants file. Go to <A HREF='list_participants.php'>list_participants</A> to recreate it!");
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

    /*
$num_fields=[  "id", "friendly_id" ];
$link_fields=[ "code", "id" , "contribution_id" , "abstract_id" ];
// , "MC_track"=> "MC and track", "primary_author_id" => "Main author",
*/
/*
$js_variables ="
<script>
";
*/
$content ="";

$content .="<A HREF='list_participants.php'>Go to the list of participants</A><BR/><BR/>\n";
$content .="<A HREF='list_papers.php'>Go to the list of papers</A><BR/><BR/>\n";

$content .="<BR/><BR/>\n";

if (!($queryArray["contribution_id"])){
    die("No contribution ID given... Stop.");
}
if (!($queryArray["person_id"])){
    die("No person ID given... Stop.");
}

$all_persons=file_read_json($cws_config['global']['data_path']."/all_participants.json", true );

$content .= "<h3>Information about paper ".$queryArray["contribution_id"]."</h3><BR/>\n";
$content .= show_paper_info($queryArray["contribution_id"]);

$retval=check_authors_list_for_reviewer($contributions[$queryArray["contribution_id"]],$queryArray["person_id"]);
$content .=$retval["content"];
$cannot_assign=$retval["found"];
$content .= "<h3>Information about reviewer ".get_full_name_from_eventid($queryArray["person_id"])." (". $queryArray["person_id"].")</h3><BR/>\n";
//$content .= show_reviewer_info($queryArray["person_id"]);

foreach($all_persons as $person){
    if ($person["id"]==$queryArray["person_id"]){
        $the_reviewer=$person;
        $content .= show_reviewer_info($person);
        break;
    }
} //for each person
$content .= "<BR/><BR/>\n";
if (($queryArray["confirm"]==1)&&(!$cannot_assign)){
    $content .= "<h3>Assignation confirmed</h3><BR/>\n";
    if ((!($the_reviewer["email"]))||(!(add_reviewer_to_team($the_reviewer["email"])))){
        $content .= "<BR/>Unable to add reviewer ".$the_reviewer["email"]." to the team<BR/>\n";
    } else {
        $content .= "<BR/>Reviewer ".$the_reviewer["email"]." added to the team<BR/>\n";
        comment_paper($queryArray["contribution_id"],"Inviting reviewer ".$the_reviewer["user_id"]);
        $bcc_address_array=array( "peer-review@ipac26.org" , "editor@ipac26.org" );
        $use_indico_token=true;
        $use_session_token=false;        
        //print("use_indico_token: $use_indico_token ,use_session_token: $use_session_token \n");
        $copy_for_sender=false;
        send_email_file_to_eventperson("message_referee_request.txt","EventPerson:".$the_reviewer["id"],"peer-review@ipac26.org",$copy_for_sender,$contributions[$queryArray["contribution_id"]],$bcc_address_array,use_indico_token:$use_indico_token,use_session_token: $use_session_token);
    }
    $content .= "<BR/><BR/>\n";
    $content .= "<A HREF='list_papers.php'>Go to the list of papers</A><BR/><BR/>\n";
    $content .= "<A HREF='list_participants.php'>Go to the list of participants</A><BR/><BR/>\n";
    $T->set( 'content', $content );
    echo $T->get();
    exit;
} //if confirm
else {
    $content .= "<HR>\n";
    if ($cannot_assign){
        $content .= "<h3>Unable to assign</h3><BR/>\n";
        $content .= "<BR/><b><big>This reviewer can not be assigned to this paper!</big></b><BR/>\n";
    } else {
        $content .= "<h3>Assign to paper?</h3><BR/>\n";
        $content .= "<BR/><BR/>\n";
        $content .= "<b> <big>Confirm assignation? <A HREF='assign_paper.php?contribution_id=".$queryArray["contribution_id"]."&person_id=".$queryArray["person_id"]."&confirm=1'>Yes</A> | <A HREF='edit_paper.php?contribution_id=".$queryArray["contribution_id"]."'>No</A></big></b>\n";
    }
} 
$content .= "<BR/><BR/>\n";
$content .= "<BR/><BR/>\n";
$content .= "<BR/><BR/>\n";
$T->set( 'content', $content );
$T->set( 'column_width', $column_width );
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