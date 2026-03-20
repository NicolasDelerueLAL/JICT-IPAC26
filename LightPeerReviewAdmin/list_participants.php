<?php
/* by nicolas.delerue@ijclab.in2p3.fr 

This page parses tye list of participants to the conference to extract information

2026.01.14 - Created by nicolas.delerue@ijclab.in2p3.fr

*/

if (str_contains($_SERVER["QUERY_STRING"],"debug")){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} //if debug on


require( '../config.php' );
require_lib( 'jict', '1.0' );
require_lib( 'indico', '1.0' );
require('peer_review_functions.php');

$base_url="http://indico.jacow.org";

$cfg =config( 'LightPeerReviewAdmin' );
$cfg['verbose'] =1;

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;

check_lpr_rights();


$Indico->load();


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

//Foramt: ["indico field name" => "display name" ]
$fields_to_display=[ "id" => "id", "user_id" => "User ID", "identifier" => "identifier", 
                    "full_name" => "Name", 
                    "registered" => "Is registered?",
                    "affiliation_name" => "Affiliation",
                    "affiliation_country" => "Country",
                    "affiliation_region" => "Region",
                    "roles_txt" => "Roles",
                    "author_MCs_txt" => "MCs",
                    "author_tracks_txt" => "tracks", "editablePerson" => "editablePerson",
                ];
$num_fields=[  "id"];
$link_fields=[  ];
// , "MC_track"=> "MC and track", "primary_author_id" => "Main author",
$js_variables ="
<script>
";

$content ="";
$content .="<A HREF='list_papers.php'>Go to the list of papers</A><BR/><BR/>\n";


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
     if ($field=="roles_txt"){
        $column_width.="  width: 20em;\n";
     } else if ($field=="id"){
        $column_width.="  width: 1em;\n";
     } else {
        $column_width.="  width: 5em;\n";
     }

     $column_width.="}\n\n"; 
} //for each field
$content .="</TR>\n"; 
$content .="</THEAD>\n"; 
$content .="<TBODY>\n"; 

load_abstracts();
load_contributions();
$all_persons=get_participants(force_update:true);

foreach ($all_persons as $person) {  
    //print($person["affiliation"]);
    $content .="<TR id=\"TR-".$person["id"]."\">\n"; 
    foreach ($fields_to_display as $field => $display){       
        //echo "$field: ".$abstract[$field]." <BR/>\n"; 
        $content .="<TD ";
        $content .=">";
        if (in_array($field,$link_fields)){
            if ($field=="abstract_id"){
                $content .="<A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/abstracts/". $paper['abstract_id']."/' >";
            } else if ($field=="contribution_id"){
                $content .="<A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/contributions/". $paper["contribution_id"]."/' >";
            } else {
                $content .="<A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/papers/". $paper['contribution_id']."/' >";
            }
        }
        $content .="". $person[$field];
        if (in_array($field,$link_fields)){
            $content .="</A> ";
        } 
        $content .= "</TD>\n"; 
    } //for each field
    $content .="</TR>\n"; 
} // foreach person (display)
$content .="</tbody>";
$content .="</TABLE>";
$content .="<BR/>";
$content .="<BR/>";
$content .="<BR/>";

$n_registered=0;
$n_authors=0;
$n_speakers=0;
foreach ($all_persons as $person) {  
    if ($person["registered_value"]==1){
        $n_registered+=1;
    }
    $is_author=false;
    $is_speaker=false;
    for($jcount=0;$jcount<count($person["roles"]);$jcount++){

        if ($person["roles"][$jcount]["role"]=="Author"){
            $is_author=true;
        } else if ($person["roles"][$jcount]["role"]=="Speaker"){
            $is_speaker=true;
        } else {
            //var_dump($person["roles"][$jcount]["role"]);
        }
    }
    if ($is_author){
        $n_authors+=1;
    }
    if ($is_speaker){
        $n_speakers+=1;
    }
}//for all persons
$T->set( 'content', $content );
$T->set( 'column_width', $column_width );
$T->set( 'txt1_txt', "Participants" );
$T->set( 'txt1_val', count($all_persons) );
$T->set( 'txt2_txt', "Registered" );
$T->set( 'txt2_val', $n_registered );
$T->set( 'txt3_txt', "Authors" );
$T->set( 'txt3_val', $n_authors );
$T->set( 'txt4_txt', "Speakers" );
$T->set( 'txt4_val', $n_speakers );
$T->set( 'txt5_txt', "" );
$T->set( 'txt5_val', "" );

echo $T->get();

?>