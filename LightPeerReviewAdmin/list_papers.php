<?php
/* by nicolas.delerue@ijclab.in2p3.fr based on a page created by Stefano.Deiuri@Elettra.Eu

2025.09.01 - Created by nicolas.delerue@ijclab.in2p3.fr
2026.01.09 - Update

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
$fields_to_display=[ 
                    "ids" => "IDs",
                    "title" => "Title",  
                    "author" => "author",  
                    "MC" => "MC",  
                    "affiliation" => "affiliation",  
                    "regions" => "Region(s)", 
                    "status" => "Status" ,
                    "reviewers" => "Reviewers", 
                    "edit_link" => "Edit paper",
                    "round" => "Round", 
                    "latest_revision" => "Latest revision", 
                    "latest_comment" => "Latest comment", 
                    "overdue" => "Overdue" , 
                ];
$num_fields=[  "id", "friendly_id" ];
$link_fields=[ "code", "id" , "contribution_id" , "abstract_id" ];
// , "MC_track"=> "MC and track", "primary_author_id" => "Main author",
$js_variables ="
<script>
";

$content .="<A HREF='list_participants.php'>Go to the list of participants</A><BR/><BR/>\n";


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

$disable_cache=false;
show_exec_time("bf load_paper");
load_papers($disable_cache);
show_exec_time("af load_paper");

for($ploop=0;$ploop<count($all_papers);$ploop++){
    $paper=$all_papers[$ploop];
    $content .="<TR id=\"TR-".$paper["contribution_id"]."\">\n"; 
    foreach ($fields_to_display as $field => $display){       
        //echo "$field: ".$abstract[$field]." <BR/>\n"; 
        $content .="<TD ";
        $content .=">";
        if (array_key_exists($field,$paper)){
            $content .="". $paper[$field];
        } else {
            $content .=" ??? ";
        }
        $content .= "</TD>\n"; 
    } //for each field
    $content .="</TR>\n"; 
} // foreach
show_exec_time("af paper loop");

$content .="</tbody>";
$content .="</TABLE>";
$content .="<BR/>";
$content .="<BR/>";
$content .="<BR/>";


$T->set( 'content', $content );
$T->set( 'column_width', $column_width );

$T->set( 'txt1_txt', "Papers" );
$T->set( 'txt1_val', count($all_papers) );
$T->set( 'txt2_txt', "" );
$T->set( 'txt2_val', "" );
$T->set( 'txt3_txt', "" );
$T->set( 'txt3_val', "" );
$T->set( 'txt4_txt', "" );
$T->set( 'txt4_val', "" );
$T->set( 'txt5_txt', "" );
$T->set( 'txt5_val', "" );


//$T->set( 'papers', json_decode($req_papers,true)['papers'] );
//$T->set( 'abstracts', count($abstracts) );
//$T->set( 'contributions', count($contributions) );
$T->set( 'column_width', $column_width);

/*
$T->set( 'todo_n', $todo_n );
$T->set( 'done_n', $done_n );
$T->set( 'undone_n', $undone_n );
$T->set( 'all_n', $todo_n +$done_n );
*/
echo $T->get();

show_exec_time("end");
if ($execution_record){
    print($execution_record);
}

//print("done");

?>