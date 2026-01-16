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
$fields_to_display=[ "id" => "id", 
                    "name" => "Name", 
                    "registered" => "Is registered?",
                    "affiliation_name" => "Affiliation",
                    "affiliation_country" => "Country",
                    "affiliation_region" => "Region",
                    "roles_txt" => "Roles",
                    "author_MCs_txt" => "MCs",
                    "author_tracks_txt" => "tracks",
                ];
$num_fields=[  "id"];
$link_fields=[  ];
// , "MC_track"=> "MC and track", "primary_author_id" => "Main author",
$js_variables ="
<script>
";


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

$_rqst_cfg=[];
$_rqst_cfg['disable_cache'] =false;
$req_papers =$Indico->request( "/event/{id}/manage/persons/", 'GET', false, array( 'return_data' =>true, 'quiet' =>true ) );

$matchtxt='#<tr id="person-(.*)"\s*[a-zA-Z"=\-\ ]*\s*data-person-roles="(.*)"[.\s]+';
$matchtxt.='#';
$returnValue = preg_match_all($matchtxt, $req_papers , $matches);
$all_persons=[];
for ($icount=0;$icount<count($matches[0]);$icount++){
    $person=[];
    $person["id"]=$matches[1][$icount];
    $person["roles"]=$matches[2][$icount];
    $all_persons[]=$person;
}

for ($iloop=0;$iloop<6;$iloop++){
    $flags=0;
    if ($iloop==0){
        $matchtxt='#<i class="icon-ticket js-show-regforms [a-zA-Z]*" data-title="\s*(.*)';
    }    
    if ($iloop==1){
        $matchtxt='#<td class="i-table name-column" data-searchable="(.*)">\s*(.*)';
    }    
    if ($iloop==2){
        $matchtxt='#<td class="i-table email-column">\s*(.*)';
    }    
    if ($iloop==3){
        $matchtxt='#<td class="i-table affiliation-column">\s(\s.*)(\s.*)(\s.*)\s*\</td\>';
    }    
    if ($iloop==4){
        $matchtxt='#<td class="i-table roles-column">\s(\s.*)(\s.*)(\s.*)';
        $flags=PREG_OFFSET_CAPTURE;
    }    
    if ($iloop==5){
        $matchtxt='#<td class="i-table edit-column thin-column">\s(\s.*)';
    }    
    
    $matchtxt.='#';
    //print("matchtxt ".$matchtxt."\n");
    $returnValue = preg_match_all($matchtxt, $req_papers , $matches,$flags);
    /*
    print("Matches <BR/>\n");
    print("Counted ".count($matches[0]));
    for ($icount=0;$icount<count($matches);$icount++){
        print("Match # $icount <BR/>\n");
        var_dump($matches[$icount][0]);
        print("<BR/>\n<BR/>\n");
    }
    for ($icount=0;$icount<count($matches);$icount++){
        print("Match5 # $icount <BR/>\n");
        var_dump($matches[$icount][5]);
        print("<BR/>\n<BR/>\n");
    }
    */
    if (!(count($matches[0])==count($all_persons))){
        print("Warning mismatch on ");
        print($matchtxt);
        print("Expected ".count($all_persons)." got ".count($matches[0])."...");
        die("Matching problem");
    }
    for ($icount=0;$icount<count($matches[0]);$icount++){
        if ($iloop==0){
            $all_persons[$icount]["registered"]=$matches[1][$icount];
            $matchesSub="";
            if (preg_match("#The person has not registered yet#",$matches[1][$icount],$matchesSub)){
                $all_persons[$icount]["registered_value"]=0;
                $all_persons[$icount]["registered"]=str_replace("The person has not registered yet","Not registered",$all_persons[$icount]["registered"]);
            } else if (preg_match("#The person has registered in:#",$matches[1][$icount],$matchesSub)){
                $all_persons[$icount]["registered"]=str_replace("The person has registered in:","Is registered",$all_persons[$icount]["registered"]);            
                $all_persons[$icount]["registered_value"]=1;
            } else {
                print("Registration not understood: ".$all_persons[$icount]["registered"]);
                $all_persons[$icount]["registered_value"]=-9;
            }
        }
        if ($iloop==1){
            $all_persons[$icount]["name_lowercase"]=$matches[1][$icount];
            $all_persons[$icount]["name"]=$matches[2][$icount];
        }
        if ($iloop==2){
            $all_persons[$icount]["email"]=$matches[1][$icount];
        }
        if ($iloop==3){
            $all_persons[$icount]["affiliation_raw"]=$matches[0][$icount];
            //print($all_persons[$icount]["affiliation_raw"]."\n");
            $matchtxtSub='#setupAffiliationPopup\("([a-zA-Z0-9\-]*)",(["{},:.a-zA-Z0-9_\ ]*)#'; //
            //print("matchtxtSub ".$matchtxtSub."\n");
            $returnValueSub = preg_match_all($matchtxtSub, $all_persons[$icount]["affiliation_raw"] , $matchesSub);
            if ((count($matchesSub)>2)&&(count($matchesSub[2])>0)){
                //var_dump($matchesSub);
                $all_persons[$icount]["affiliation_json"]=json_decode($matchesSub[2][0], true);
                //var_dump($all_persons[$icount]["affiliation_json"]);
                if ($all_persons[$icount]["affiliation_json"]){
                    $all_persons[$icount]["affiliation_name"]=$all_persons[$icount]["affiliation_json"]["name"];
                    $all_persons[$icount]["affiliation_country"]=$all_persons[$icount]["affiliation_json"]["country_name"];
                    $all_persons[$icount]["affiliation_region"]=get_region($all_persons[$icount]["affiliation_json"]['country_code']);
                } else {
                    $all_persons[$icount]["affiliation_name"]="Unknown";
                    $all_persons[$icount]["affiliation_country"]="Unknown";
                    $all_persons[$icount]["affiliation_region"]="Unknown";
                }
            } else {
                $all_persons[$icount]["affiliation_name"]="Unknown";
                $all_persons[$icount]["affiliation_country"]="Unknown";
                $all_persons[$icount]["affiliation_region"]="Unknown";
            }
        }
        if ($iloop==4){
            //$all_persons[$icount]["roles_raw"]=$matches[0][$icount];
            //var_dump($matches);
            for($icount=0;$icount<count($matches[0]);$icount++){
                $all_persons[$icount]["roles"]=[];
                $person=$matches[0][$icount];
                //var_dump($person[1]);
                $matchtxtSub='#\</td\>#'; //
                //print("matchtxtSub ".$matchtxtSub."\n");
                $returnValueSub = preg_match_all($matchtxtSub, $req_papers , $matchesSub, PREG_OFFSET_CAPTURE, $person[1]);
                //var_dump($matchesSub[0][0][1]);
                //var_dump($matchesSub);
                $this_person_roles=substr($req_papers, $person[1],$matchesSub[0][0][1]- $person[1]);
                $this_person_roles=str_replace("&#34;",'"',$this_person_roles);
                //var_dump($this_person_roles);
                $matchtxtSub='#data-role-name="(.*)"\s+data-items="(["{}\\\\.\&\#:\;0-9a-zA-Z\-\ \(\),/\?\!\*=]*)"#'; //
                //print("matchtxtSub ".$matchtxtSub."\n");
                $returnValueSub = preg_match_all($matchtxtSub, $this_person_roles , $matchesSub);
                //var_dump($matchesSub);
                $all_persons[$icount]["roles_txt"]="";                
                for($jcount=0;$jcount<count($matchesSub[0]);$jcount++){
                    $all_persons[$icount]["roles"][$jcount]=[];
                    //print($matchesSub[1][$jcount]);
                    $all_persons[$icount]["roles"][$jcount]["role"]=$matchesSub[1][$jcount];
                    $all_persons[$icount]["roles"][$jcount]["item"]=[];
                    //print($matchesSub[2][$jcount]);
                    $jsontxt=json_decode($matchesSub[2][$jcount],true);
                    //var_dump($jsontxt);
                    //var_dump(array_keys($jsontxt));
                    $all_persons[$icount]["author_MCs"]=[];
                    $all_persons[$icount]["author_tracks"]=[];
                    $all_persons[$icount]["abstracts_id"]=[];
                    $all_persons[$icount]["contributions_id"]=[];
                    $all_persons[$icount]["roles_txt"].=$all_persons[$icount]["roles"][$jcount]["role"].": <ul>";                
                    for($kloop=0;$kloop<count(array_keys($jsontxt));$kloop++){
                        $all_persons[$icount]["roles_txt"].="<li>";
                        $key=array_keys($jsontxt)[$kloop];
                        //var_dump(array_keys($jsontxt[$key]));
                        $all_persons[$icount]["roles"][$jcount]["item"]["id"]=$key;
                        $all_persons[$icount]["roles_txt"].="<A HREF='$base_url"."/event/".$cfg['indico_event_id']."/abstracts/".$all_persons[$icount]["roles"][$jcount]["item"]["id"]."/'>abs id: ".$all_persons[$icount]["roles"][$jcount]["item"]["id"]."</A>;\n";
                        $all_persons[$icount]["roles"][$jcount]["item"]["title"]=$jsontxt[$key]["title"];
                        $contribution_id=false;
                        $abstract_id=false;
                        if (key_exists("url",$jsontxt[$key])){
                            $all_persons[$icount]["roles"][$jcount]["item"]["url"]=$jsontxt[$key]["url"];
                            $all_persons[$icount]["roles_txt"].="<A HREF='$base_url".$all_persons[$icount]["roles"][$jcount]["item"]["url"]."'>".$all_persons[$icount]["roles"][$jcount]["item"]["title"]."</A>\n";
                            $submatchesSubSub=false;
                            $submatchesSubSub2=false;
                            if (preg_match("#/abstracts/([0-9]*)#",$jsontxt[$key]["url"],$matchesSubSub)){
                                //var_dump($matchesSubSub);
                                $abstract_id=$matchesSubSub[1];
                                $all_persons[$icount]["abstracts_id"][]=$abstract_id;
                            } else if (preg_match("#/contributions/\?selected=([0-9]*)#",$jsontxt[$key]["url"],$matchesSubSub2)){
                                //var_dump($matchesSubSub2);
                                $contribution_friendly_id=$matchesSubSub2[1];
                                $contribution_id=$contributions_by_fr_id[$contribution_friendly_id];
                                $all_persons[$icount]["contributions_id"][]=$contribution_id;
                                //print("contribution_id $contribution_id");
                                if (array_key_exists("abstract_id",$contributions[$contribution_id])){
                                    $abstract_id=$contributions[$contribution_id]["abstract_id"];
                                    $all_persons[$icount]["abstracts_id"][]=$abstract_id;
                                }
                            } else {
                                print("URL:\n".$jsontxt[$key]["url"]);
                                die("Unable to interpret URL");
                            } 
                        } else {
                            $all_persons[$icount]["roles"][$jcount]["item"]["url"]="";
                            $all_persons[$icount]["roles_txt"].=$all_persons[$icount]["roles"][$jcount]["item"]["title"];
                            $abstract_id=$all_persons[$icount]["roles"][$jcount]["item"]["id"];
                        }
                        //get abstract and MC
                        if (($abstract_id) && (key_exists($abstract_id,$abstracts))){
                            $abstract=$abstracts[$abstract_id];
                            if (($abstract["accepted_track"])&&($abstract["accepted_track"]!="NULL")){
                                $all_persons[$icount]["author_tracks"][]=$abstract["accepted_track"]["code"];
                            } else {
                                $all_persons[$icount]["author_tracks"][]=$abstract["submitted_for_tracks"][0]["code"];
                            }
                        } else if (key_exists($contribution_id,$contributions)){
                            $contribution=$contributions[$contribution_id];
                            if ($contribution["track"]["code"]){
                                $all_persons[$icount]["author_tracks"][]=$contribution["track"]["code"];
                            }
                        } else {
                            $abstract=false;
                        }
                        $all_persons[$icount]["roles_txt"].="</li>";
                    }
                    $all_persons[$icount]["roles_txt"].="</ul>\n";
                }
                $all_persons[$icount]["author_tracks_txt"]="";
                $all_persons[$icount]["author_MCs_txt"]="";
                if ($all_persons[$icount]["author_tracks"]){
                    $all_persons[$icount]["author_tracks"]=array_unique($all_persons[$icount]["author_tracks"]);
                    for ($tloop=0;$tloop<count($all_persons[$icount]["author_tracks"]);$tloop++){
                        $all_persons[$icount]["author_MCs"][]=substr($all_persons[$icount]["author_tracks"][$tloop],0,3);
                        $all_persons[$icount]["author_tracks_txt"].=$all_persons[$icount]["author_tracks"][$tloop].", ";
                    }
                    $all_persons[$icount]["author_tracks_txt"]=substr($all_persons[$icount]["author_tracks_txt"],0,strlen($all_persons[$icount]["author_tracks_txt"])-2);
                    $all_persons[$icount]["author_MCs"]=array_unique($all_persons[$icount]["author_MCs"]);
                    for ($tloop=0;$tloop<count($all_persons[$icount]["author_MCs"]);$tloop++){
                        $all_persons[$icount]["author_MCs_txt"].=$all_persons[$icount]["author_MCs"][$tloop].", ";
                    }
                    $all_persons[$icount]["author_MCs_txt"]=substr($all_persons[$icount]["author_MCs_txt"],0,strlen($all_persons[$icount]["author_tracks_txt"])-2);
                }
            }
        }
        if ($iloop==5){
            $all_persons[$icount]["person_data"]=$matches[0][$icount];
        }
        if ($iloop==6){
            $all_persons[$icount]["edit_person"]=$matches[0][$icount];
        }
    }
} //iloop
//print("<BR/>\n<BR/>\n<BR/>\n<BR/>\n<BR/>\n");
//print("Persons <BR/>\n");
//var_dump($all_persons);
/*
for ($icount=0;$icount<count($matches[0]);$icount++){
    $person=[];
    $person["affiliation"]=$matches[6][$icount];
    $person["papers"]=$matches[7][$icount];
    $person["details"]=$matches[7][$icount];
    $all_persons[]=$person;
}
*/
//die("here");

//person id 
//data-person-roles=
//name-column" data-searchable="akihiro shirakawa">

$fwret=file_write_json( $cws_config['global']['data_path']."/all_participants.json",$all_persons);
//echo "file write $fwret \n"; 

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
            var_dump($person["roles"][$jcount]["role"]);
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