<?php
/* by nicolas.delerue@ijclab.in2p3.fr 
list all the information about a paper and then allows the possibility to assigna a reviewer

2026.01.16 - Created by nicolas.delerue@ijclab.in2p3.fr
2026.01.09 - Update

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

$disable_cache=true;
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
$content .="<center><h2>Information about paper ".$queryArray["contribution_id"]."</h2></center><BR/>\n";


$content .= show_paper_info($queryArray["contribution_id"]);

$content .="<hr>\n";

if (($paper["n_reviewers"]>2)&&((!($queryArray["add_extra_reviewer"]))||($queryArray["add_extra_reviewer"]==0))){
    $content .="Already ".$paper["n_reviewers"]." reviewers are assigned to this paper.<BR/>\n";
    $content .="<A HREF='edit_paper.php?".$_SERVER["QUERY_STRING"]."&add_extra_reviewer=1'>Add extra reviewer</A><BR/>\n";
} else{
    $content .="<center><h3>Add extra reviewer</h2></center>\n";
    //All participants files
    $all_persons=file_read_json($cws_config['global']['data_path']."/all_participants.json", true );
    if (!($all_persons)){
        die("Unable to read participants file. Go to <A HREF='list_participants.php'>list_participants</A> to recreate it!");
    }
    
    for ($iperson=0;$iperson<count($all_persons);$iperson++){
        if (($paper["abstract_id"])&&($all_persons[$iperson]["abstracts_id"])&&(in_array($paper["abstract_id"],$all_persons[$iperson]["abstracts_id"]))){
            $content .="Removing author: ".$all_persons[$iperson]["name"]." from the pool (abstract id).<BR/>\n";
        } else if (($paper["contribution_id"])&&($all_persons[$iperson]["contributions_id"])&&(in_array($paper["contribution_id"],$all_persons[$iperson]["contributions_id"]))){
            $content .="Removing author: ".$all_persons[$iperson]["name"]." from the pool (contribution id).<BR/>\n";
        } else {
            $all_persons[$iperson]["possible_reviewer"]=true;
        }
    }
    $n_possible_reviewers=count($all_persons);
    $content .="Reviewers: ".$n_possible_reviewers."<BR/>\n";


    //check registered
    $not_registered=0;
    $no_user_id=0;
    $n_possible_reviewers=0;
    if ((!($queryArray["ignore_registration"]))||($queryArray["ignore_registration"]==0)){
        for ($iperson=0;$iperson<count($all_persons);$iperson++){
            if (!($all_persons[$iperson]["registered_value"]==1)){
                $not_registered+=1;
                $all_persons[$iperson]["possible_reviewer"]=false;
            }  else if (!(array_key_exists("user_id",$all_persons[$iperson]))){
                print("Participant ".$person["name"]." has not user_id ");
                $no_user_id+=1;
                $all_persons[$iperson]["possible_reviewer"]=false;
            } else {
                if ($all_persons[$iperson]["possible_reviewer"]){
                    $n_possible_reviewers+=1;
                }
            }
        } //for each person
        $content .="Rejecting not registered: ".$not_registered." or no user_id ".$no_user_id." ; Remaining: ".$n_possible_reviewers."<BR/>\n";
        $content .=" <A HREF='?".$_SERVER['QUERY_STRING']."&ignore_registration=1'>Click here to ignore registration information.</A><BR/>\n";
    } else {
        $content .=" <A HREF='?".$_SERVER['QUERY_STRING']."&ignore_registration=0'>Click here to use registration information.</A><BR/>\n";     
    }



    //check region
    if (($paper["regions"])&&((!($queryArray["ignore_region"]))||($queryArray["ignore_region"]==0))){
        $same_region=0;
        $n_possible_reviewers=0;
        $matches=false;
        for ($iperson=0;$iperson<count($all_persons);$iperson++){
            if (preg_match("#".$all_persons[$iperson]["affiliation_region"]."#",$paper["regions"],$matches)){
                $all_persons[$iperson]["possible_reviewer"]=false;
                $same_region+=1;
            } else{
                if ($all_persons[$iperson]["possible_reviewer"]){
                    $n_possible_reviewers+=1;
                }
            }
        }
        $content .="Rejecting from region(s): ".$paper["regions"]."; ".$same_region." rejected; Remaining ".$n_possible_reviewers." possible reviewers.";
        $content .=" <A HREF='?".$_SERVER['QUERY_STRING']."&ignore_region=1'>Click here to ignore region information.</A><BR/>\n";
    } else {
        if (!$paper["regions"]){
            $content .="No region information for this paper.<BR/>\n";
        } else{
            $content .=" <A HREF='?".$_SERVER['QUERY_STRING']."&ignore_region=0'>Click here to use region information.</A><BR/>\n";
        }
    }

    //check MC
    if (($paper["MC"])&&((!($queryArray["ignore_mc"]))||($queryArray["ignore_mc"]==0))){
        $same_mc=0;
        $n_possible_reviewers=0;
        $matches=false;
        for ($iperson=0;$iperson<count($all_persons);$iperson++){
            //var_dump($all_persons[$iperson]["author_MCs"]);
            if (($all_persons[$iperson]["author_MCs"])&&(in_array($paper["MC"],$all_persons[$iperson]["author_MCs"]))){
                $same_mc+=1;
                if ($all_persons[$iperson]["possible_reviewer"]){
                    $n_possible_reviewers+=1;
                }
            } else{
                $all_persons[$iperson]["possible_reviewer"]=false;
            }
        }
        $content .="Keeping from same MC: ".$paper["MC"]."; ".$same_mc." in this MC; Remaining ".$n_possible_reviewers." possible reviewers.";
        $content .=" <A HREF='?".$_SERVER['QUERY_STRING']."&ignore_mc=1'>Click here to ignore MC information.</A><BR/>\n";
    } else {
        if (!$paper["MC"]){
            $content .="No MC information for this paper.<BR/>\n";
        } else{
            $content .=" <A HREF='?".$_SERVER['QUERY_STRING']."&ignore_mc=0'>Click here to use MC information.</A><BR/>\n";
        }
    }

    //check track
    //by default track is ignored
    if (!($queryArray["ignore_track"])){
        $queryArray["ignore_track"]=1;
    }
    if (($paper["track"])&&((!($queryArray["ignore_track"]))||($queryArray["ignore_track"]==0))){
        $same_mc=0;
        $n_possible_reviewers=0;
        $matches=false;
        for ($iperson=0;$iperson<count($all_persons);$iperson++){
            //var_dump($all_persons[$iperson]["author_tracks"]);
            if (($all_persons[$iperson]["author_tracks"])&&(in_array($paper["track"],$all_persons[$iperson]["author_tracks"]))){
                $same_mc+=1;
                if ($all_persons[$iperson]["possible_reviewer"]){
                    $n_possible_reviewers+=1;
                }
            } else{
                $all_persons[$iperson]["possible_reviewer"]=false;
            }
        }
        $content .="Keeping from same track: ".$paper["track"]."; ".$same_mc." in this track; Remaining ".$n_possible_reviewers." possible reviewers.";
        $content .=" <A HREF='?".$_SERVER['QUERY_STRING']."&ignore_track=1'>Click here to ignore track information.</A><BR/>\n";
    } else {
        if (!$paper["track"]){
            $content .="No track information for this paper.<BR/>\n";
        } else{
            $content .=" <A HREF='?".$_SERVER['QUERY_STRING']."&ignore_track=0'>Click here to use track information.</A><BR/>\n";
        }
    }

    $content .="<BR/><BR/>\n";
    shuffle($all_persons);
    $content .="<b>There are ".$n_possible_reviewers." possible reviewers (random order):</b><BR/>\n";

    $person_fields=[
            "id" => "ID", 
            "name" => "Name", 
            "registered" => "Is registered?",
            "affiliation_name" => "Affiliation",
            "affiliation_country" => "Country",
            "affiliation_region" => "Region",
            "author_MCs_txt" => "MCs",
            "author_tracks_txt" => "Tracks",
            "assign_link" => "Assign",
    ];
    $num_fields=[ "id" ];

    $content .="
<div class=\"table-wrap\"><table id='person_table' class=\"sortable\" width=\"95%\">
    <caption> Possible reviewers
    <span class=\"sr-only\"> .</span>
  </caption>
  <thead>
    <tr>";
    
    //Table headers
    $column_width="";
    $icol=0;
    foreach ($person_fields as $field => $display){        
        //echo "$field: ".$abstract[$field]." <BR/>\n"; 
        $content .="<TH ";
        if (in_array($field,$num_fields)){
            $content .=" class=\"num\"  ";
        }

        if ($field=="id"){
            $content .=" width=\"2em\" ";
        } else if ($field=="name"){
            $content .=" width=\"6em\" ";
        } else {
            $content .=" width=\"4em\" ";
        }
        $content .=" aria-sort=\"ascending\" ";
        $content .="> ";
        $content .="<button data-column-index=\"$icol\">\n";  
        $content .="$display \n";
        $content .="<span aria-hidden=\"true\"></span>\n ";
        $content .="</button>\n";
        $content .="</TH>\n"; 

        $icol++;
    } //for each field
    $content .="</TR>\n"; 
    $content .="</THEAD>\n"; 
    $content .="<TBODY>\n"; 

    foreach($all_persons as $person){
        if ($person["possible_reviewer"]){
            $person["assign_link"]="<A HREF='assign_paper.php?contribution_id=".$queryArray["contribution_id"]."&person_id=".$person["id"]."' >Assign to this reviewer</A>";
            $content .="<TR id=\"TR-".$person["id"]."\">\n"; 
            foreach ($person_fields as $field => $display){       
                //echo "$field: ".$abstract[$field]." <BR/>\n"; 
                $content .="<TD ";
                $content .=">";
                $content .="". $person[$field];
                $content .= "</TD>\n"; 
            } //for each field
            $content .="</TR>\n"; 
        }
    } // foreach
    $content .="</tbody>";
    $content .="</TABLE>";
    $content .="<BR/>";
    $content .="<BR/>";
    $content .="<BR/>";
} //List possible extra reviewers

//$fwret=file_write_json( $cws_config['global']['data_path']."/all_participants.json",$all_persons);
//echo "file write $fwret \n"; 

/*
    $content .="<form action='edit_paper.php?'".$_SERVER["QUERY_STRING"] ." method=\"get\">\n";
    $content .="<input type='button' value='Hide ".$imc."' onClick='toggle_visibility_mc(".$imc.")'>\n";
    $content .="</form>\n";
*/
/*
$content .="
<div class=\"table-wrap\"><table id='abstracts_table' class=\"sortable\" width=\"95%\">
    <caption> Papers to review
    <span class=\"sr-only\"> .</span>
  </caption>
  <thead>
    <tr>";
    
//Table headers
$column_width="";
$icol=0;
foreach ($fields_to_display as $field => $display){        
    //echo "$field: ".$abstract[$field]." <BR/>\n"; 
     $content .="<TH ";
     if (in_array($field,$num_fields)){
         $content .=" class=\"num\" ";
     }
     $content .=" aria-sort=\"ascending\" ";
     $content .="> ";
     $content .="<button data-column-index=\"$icol\">\n";  
     $content .="$display \n";
     $content .="<span aria-hidden=\"true\"></span>\n ";
     $content .="</button>\n";
     $content .="</TH>\n"; 

     $icol++;
     $column_width.="table.sortable th:nth-child($icol) {\n";
     if ($field=="title"){
        $column_width.="  width: 12em;\n";
     } else if ($field=="reviewers"){
        $column_width.="  width: 16em;\n";
     } else if ($field=="status"){
        $column_width.="  width: 6em;\n";
     } else {
        $column_width.="  width: 1em;\n";
     }

     $column_width.="}\n\n"; 
} //for each field
$content .="</TR>\n"; 
$content .="</THEAD>\n"; 
$content .="<TBODY>\n"; 
*/
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