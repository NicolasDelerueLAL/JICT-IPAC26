<?php

/* Created by Nicolas.Delerue@ijclab.in2p3.fr
2025.12.12 1st version

This page performs various qualitry tests on the abstracts and 
allow to fix and/or notify the authors 

*/
if (str_contains($_SERVER["QUERY_STRING"],"debug")){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} //if debug on

require( '../config.php' );
require_lib( 'jict', '1.0' );
require_lib( 'indico', '1.0' );
require( 'ipac26_tools.php' );

$cfg =config( 'SPC_tools', false, false );
$cfg['verbose'] =0;

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;


$Indico->load();

if (str_contains($_SERVER["QUERY_STRING"],"show_known_errors")){
    $show_known_errors=true;
}

if (count($_POST)>0){    
    if ($_POST["submit"]=="Notify"){
/*
{abstract_id}
{abstract_title}
{email}
{event_title}
{first_name}
{last_name}
{event_link}
{event_link:custom-text}
*/

        $_POST['subject']="[IPAC'26] Please fix your abstract: {abstract_title}";
$body1="Dear {first_name} {last_name},<BR/>\n
<BR/>\n
Thank you for submitting an abstract for IPAC'26.<BR/>\n
However, during the quality review of abstract {abstract_id} ({abstract_title}) we noticed the following problem";

$body2="Could you review your abstract at <A HREF=\"https://indico.jacow.org/event/95/abstracts/".$_POST['abstract_id']."/\">https://indico.jacow.org/event/95/abstracts/".$_POST['abstract_id']."/</A> <BR/>\n
<BR/>\n
For abstracts already submitted, modifications are still possible until December 15th.<BR/>\n
<BR/>\n
Thank you in advance,<BR/>\n
<BR/>\n
Nicolas Delerue<BR/>\n
(IPAC'26 Editor in chief)<BR/>\n
";
        $_POST['body']=$body1;
        if (count(explode(",",$_POST['reasons']))>1){
            $_POST['body'].="s:\n<BR/>";
        } else {
            $_POST['body'].=":\n<BR/>";
        }
        foreach(explode(",",$_POST['reasons']) as $reason) {
            if ($reason=="short_title"){
                $_POST['body'].="The title is very short.<BR/>\n";
            } else if ($reason=="short_abstract"){
                $_POST['body'].="The abstract is very short, probably too short to contain enough information for the reader to appreciate the interest of the work presented.<BR/>\n";
            } else if ($reason=="non_latin_char"){
                $_POST['body'].="The title or the abstract contain non latin characters that are not compatible with our publication policy.<BR/>\n";
            }            
        }
        $_POST['body'].=$body2;
        $_POST['role']="submitter";
        require('send_email_included.php');
    } //submit notify
    else if ($_POST["submit"]=="Ignore"){
        $_POST['subject']="";
        $_POST['body']="";
        $_POST['role']="";
        require('send_email_included.php');
    } //submit Ignore
} //POST>0

//Known errors file
$abstracts_qa_data=file_read_json( $cws_config['global']['data_path']."/abstracts_qa.json", true );
if (!($abstracts_qa_data)){
    $abstracts_qa_data=[];
}


//require( 'autoconfig.php' );
$cfg['template_qa']="template_qa.html";

$T =new TMPL( $cfg['template_qa'] );
$T->set([
    'style' =>'
        main { font-size: 14px; margin-bottom: 2em } 
        td.b_x { background: #555; color: white } 
        td.b_y2g { background: #ADFF2F; color: black }
        tr:hover td { background: #b0f4ff; color: black }
        tr.warn td { background: #ffbab0; color: black }

        ',
    'title' =>$cfg['name'],
    'logo' =>$cfg['logo'],
    'conf_name' =>$cfg['conf_name'],
    'user' =>__h( 'small', $user['email'] ),
    'path' =>'../',
    'head' =>"<link rel='stylesheet' type='text/css' href='../dist/datatables/datatables.min.css' />
    <link rel='stylesheet' type='text/css' href='../page_edots/colors.css' />
    <link rel='stylesheet' type='text/css' href='../style.css' />",
    'scripts' =>"<script src='../dist/datatables/datatables.min.js'></script>",
    'js' =>false
    ]);

$content =false;
$content ="<BR/>";
$content .="<BR/>";
$content .="<BR/>";

//Foramt: ["indico field name" => "display name" ]
$fields_to_display=[ "id" => "id", "friendly_id" => "Conf id"  , "title" => "Title", "submitter_id" => "Submitter",  "content" => "Abstract" , "all_comments"=> "Comments", "warnings" => "Warnings" ];
$num_fields=[  "id", "friendly_id" ];
$link_fields=[ "id", "title" ];
// , "MC_track"=> "MC and track", "primary_author_id" => "Main author",
$js_variables ="
<script>
";
    
$content .="<form>";

/*
for ($imc=1; $imc<=8; $imc++){
    $content .="<input type='button' id='toggle_mc_".$imc."' value='Hide ".$imc."' onClick='toggle_visibility_mc(".$imc.")'>\n";
}
*/
$content .="</form>\n";

$content .="
<div class=\"table-wrap\"><table id='abstracts_table' class=\"sortable\" width=\"95%\">
    <caption> Abstracts QA
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
     if ($field=="id"){
         $js_variables .="abstract_id_column=$icol;\n";
     } else if ($field=="MC_track"){
         $js_variables .="MC_column=$icol;\n";
     } else if ($field=="vote"){
         $js_variables .="vote_column=$icol;\n";
     } else if ($field=="vote_by_MC"){
         $js_variables .="vote_mc_column=$icol;\n";
     } else if ($field=="content"){
         $js_variables .="col_abstracts=$icol;\n";
        } else if ($field=="all_comments"){
         $js_variables .="col_comments=$icol;\n";
        }
     $content .="</TH>\n"; 

     $icol++;
     $column_width.="table.sortable th:nth-child($icol) {\n";
     if ($field=="title"){
        $column_width.="  width: 12em;\n";
     } else if ($field=="primary_author_name"){
        $column_width.="  width: 8em;\n";
     } else if ($field=="submitter_id"){
        $column_width.="  width: 8em;\n";
     } else if ($field=="content"){
        $column_width.="  width: 40em;\n";
        $js_variables .="col_content_width='40em';\n";
     } else if ($field=="all_comments"){
        $column_width.="  width: 20em;\n";
        $js_variables .="col_conmments_width='20em';\n";
     } else {
        $column_width.="  width: 1em;\n";
     }

     $column_width.="}\n\n"; 
} //for each field
$content .="</TR>\n"; 
$content .="</THEAD>\n"; 
$content .="<TBODY>\n"; 


 
$_rqst_cfg=[];
$_rqst_cfg['disable_cache'] =true;
$data_key= $Indico->request( '/event/{id}/manage/abstracts/abstracts.json', 'GET', false, $_rqst_cfg);

$known=0;
$errors=0;
$to_notify=0;
$to_fix=0;

foreach ($Indico->data[$data_key]['abstracts'] as $abstract) {
    $warnings=0;
    $abstract["warnings"]="";
    $warning_reasons="";
    $known_warnings="";
    if ($abstract["state"]=="submitted"){
        $abstract["MC"]=substr($abstract["submitted_for_tracks"][0]["code"],0,3);
        $abstract["track"]=$abstract["submitted_for_tracks"][0]["code"]." - ".$abstract["submitted_for_tracks"][0]["title"];
        //print_r($abstract["submitted_for_tracks"][0]);
        //$abstract["MC_track"]=$abstract["MC"]." - ".$abstract["submitted_for_tracks"][0]["code"].": ".$abstract["submitted_for_tracks"][0]["title"];
        $abstract["MC_track"]=$abstract["MC"]." - ".$abstract["submitted_for_tracks"][0]["code"];
        $abstract["primary_author_name"]="";
        $abstract["primary_author_id"]="";
        foreach($abstract["persons"] as $pers){
            if ($pers["author_type"]=="primary"){
                if (empty($abstract["primary_author_name"])) {
                    $abstract["primary_author_name"]=$pers["first_name"]." ".$pers["last_name"];
                    $abstract["primary_author_id"]=$pers["first_name"]." ".$pers["last_name"]."<BR/>\n".$pers["email"];
                }
            }
        } //find primary author
        $abstract["submitter_id"]=$abstract["submitter"]["first_name"]." ".$abstract["submitter"]["last_name"]."<BR/>\n".$abstract["submitter"]["email"];
        if (empty($abstract["primary_author_name"])) {
            $abstract["primary_author_name"]=$abstract["persons"][0]["first_name"]." ".$abstract["persons"][0]["last_name"];
            $abstract["primary_author_id"]=$abstract["persons"][0]["first_name"]." ".$abstract["persons"][0]["last_name"]."<BR/>\n".$abstract["persons"][0]["email"];
        }

        //deal with comments
        $abstract["all_comments"] ="";
        foreach($abstract["comments"] as $comment){
            $abstract["all_comments"] .=$comment["user"]["full_name"].": ".$comment["text"];
            if ((str_contains($comment["text"],"QA Email sent. Reason: "))||(str_contains($comment["text"],"QA issue ignored. Reason:"))){
                //print("Text: ".$comment["text"]."\n");
                $known_warnings.=explode(":",$comment["text"])[1];
                //print("known_warnings: ".$known_warnings."\n");
                //$abstract["all_comments"] .="(QA)";
                                
            }
            $abstract["all_comments"] .="<BR/>\n";
        }
        $abstract["all_comments"] .="<BR/><form>\n
        <INPUT type='hidden' name='abstract_id' value='".$abstract["id"]."'>\n
        <INPUT type='text' name='comment_".$abstract["id"]."' id='comment_".$abstract["id"]."' size='10'>\n
        <INPUT type=button value='Add comment' onclick=\"add_comment(".$abstract["id"].")\">
        </form>";


        //<INPUT type=button value='A' onclick=\"change_track(".$abstract["id"].")\">
        //$abstract["warnings"].="Known warnings: ".$known_warnings;
        //Quality checks
        $isknown=false;
        if (strlen($abstract["title"])<20){   
            if (str_contains($known_warnings,"short_title")){
                $known+=1;                
                $isknown=true;
            }
            if ((!$isknown)||($show_known_errors)){
                $warnings+=1;
                $warning_reasons.="short_title,";
                if (strlen($abstract["title"])<15){
                    $abstract["warnings"].="Ultra short title: ".strlen($abstract["title"])."\n";
                } else {
                    $abstract["warnings"].="Short title: ".strlen($abstract["title"])."\n";
                }
            }
        } //short title

        $isknown=false;
        if (strlen($abstract["content"])<150){
            if (str_contains($known_warnings,"short_abstract")){
                $known+=1;                
                $isknown=true;
            }
            if ((!$isknown)||($show_known_errors)){
                $warning_reasons.="short_abstract,";
                $warnings+=1;
                if (strlen($abstract["content"])<100){
                    $abstract["warnings"].="Ultra short content: ".strlen($abstract["content"])."\n";
                } else {
                    $abstract["warnings"].="Short content: ".strlen($abstract["content"])."\n";
                }
            }
        } //short abstract

        $matches=null;
        $isknown=false;
        if (preg_match('/[^\x20-\xFF]/u',$abstract["title"],$matches)){

            if (str_contains($known_warnings,"non_latin_char")){
                $known+=1;                
                $isknown=true;
            }
            if ((!$isknown)||($show_known_errors)){
                $warning_reasons.="non_latin_char,";
                $warnings+=1;
                $abstract["warnings"].="Title contains non latin charaters:\n".$matches[0]."\n";
            }
        } //abstracts non latin char


        $matches=null;
        if (preg_match('/[^\x20-\xFF]/u',$abstract["content"],$matches)){

        $isknown=false;
            if (str_contains($known_warnings,"non_latin_char")){
                $known+=1;                
                $isknown=true;
            }
            if ((!$isknown)||($show_known_errors)){
                $warning_reasons.="non_latin_char,";
                $warnings+=1;
                $abstract["warnings"].="Abstract contains non latin charaters:\n".$matches[0]."\n";
            }
        } //text non latin char

        if (array_key_exists($abstract["id"],$abstracts_qa_data)){
            $isknown=false;
            if (array_key_exists("title",$abstracts_qa_data[$abstract["id"]])){
                if (!(str_contains($abstracts_qa_data[$abstract["id"]]["title"]["case"],"**sentence case**"))){
                    $known+=1;                
                    $isknown=true;
                }
                if ((!$isknown)||($show_known_errors)){
                    if (str_contains($abstracts_qa_data[$abstract["id"]]["title"]["case"],"**title case**")){
                        //print($abstract["id"]."=> ");
                        //var_dump($abstracts_qa_data[$abstract["id"]]);                    
                        $warning_reasons.="title in title case";
                        $warnings+=1;
                        $abstract["warnings"].="The title was in title case. It has been changed to sentence case as follow:\n".$abstracts_qa_data[$abstract["id"]]["title"]["title_correct_case"]."\n";
                    } else {
                        $warning_reasons.="title not in sentence case";
                        $warnings+=1;
                        $abstract["warnings"].="The title was not in sentence case. It has been changed to sentence case as follow:\n".$abstracts_qa_data[$abstract["id"]]["title"]["title_correct_case"]."\n";
                    }

                }
            }
        }

        /*** To be done ***/
        //multiple first author
        //non latin char author
        //author without affiliation


        if ($warnings>0){
            $errors=$errors+1;    
            $warning_reasons=rtrim($warning_reasons, ",");        
            $abstract["warnings"].= "<BR/>\n"; 
            $abstract["warnings"].= $warning_reasons;
            $abstract["warnings"] .= "<form action='abstracts_qa.php' method=\"post\">\n";
            $abstract["warnings"] .= "<input type=\"hidden\" name=\"action\" value=\"notify\">\n";
            $abstract["warnings"] .= "<input type=\"hidden\" name=\"abstract_id\" value=\"".$abstract['id']."\">\n";
            $abstract["warnings"] .= "<input type=\"hidden\" name=\"reasons\" value=\"".$warning_reasons."\">\n";
            $abstract["warnings"] .= "<input type=\"submit\" name=\"submit\" value=\"Notify\">";
            $abstract["warnings"] .= "<input type=\"submit\" name=\"submit\" value=\"Ignore\">";
            $abstract["warnings"] .= "</form>\n";
            $to_notify=$to_notify+1;    
            $content .="<TR id=\"TR-".$abstract["id"]."\">\n"; 
            foreach ($fields_to_display as $field => $display){       
                //echo "$field: ".$abstract[$field]." <BR/>\n"; 
                $content .="<TD ";
                $content .=">";
                if (in_array($field,$link_fields)){
                    $content .="<A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/abstracts/". $abstract['id']."/' >";
                }
                    $content .="". $abstract[$field];
                if (in_array($field,$link_fields)){
                    $content .="</A>";
                } 
                $content .= "</TD>\n"; 
            } //for each field
            $content .="</TR>\n"; 
        } // if warnings >0
    } //if submitted
    else {
        //echo "Skipping abstract id ".$abstract["id"]." state ".$abstract["state"]."\n";
        continue;
    }
} //for each abstract
$content .="</TBODY>\n"; 

$content .="</TABLE>\n"; 
$content .="</div>\n"; 

$content .=$js_variables;

$T->set( 'content', $content );
$T->set( 'errors', $errors );
$T->set( 'to_fix', $to_fix );
$T->set( 'known', $known );
$T->set( 'to_notify', $to_notify );
$T->set( 'event_id', $cws_config['global']['indico_event_id'] );
$T->set( 'user_name', $_SESSION['indico_oauth']["user"]["full_name"]);
$T->set( 'user_first_name', $_SESSION['indico_oauth']["user"]["first_name"]);
$T->set( 'user_last_name',$_SESSION['indico_oauth']["user"]["last_name"]);
echo $T->get();
flush();

print("\nLoop on abstracts\n");
$ai_calls_counter=0;
foreach ($Indico->data[$data_key]['abstracts'] as $abstract) {
    print($abstract["id"]." ".$abstract["title"]."\n");
    if (!(array_key_exists($abstract["id"],$abstracts_qa_data))){
        $abstracts_qa_data[$abstract["id"]]=[];
    }
    if ((!(array_key_exists("title",$abstracts_qa_data[$abstract["id"]])))){
        $ai =new AI_REQUEST();
        $question='Is this title in title case, in sentence case or neither? "'.$abstract['title'].'" ? Please answer by saying title case, sentence case or neither and if it is not in sentence case, please rewrite it in sentence case without dot at the end on a separate line starting with "Rewritten title: ".';
        //print($question);
        //print("<BR/>");
        $result=$ai->query($question);
        if (!($result)){
            print("No result");
            sleep(5);          
        } else if (strlen($result)==0){
            print("Empty result");
            sleep(5);          
        } else {
            print($result);
            //print("<BR/>\n");
            //print_r(explode("\n",$result));
            $results=explode("\n",$result);
            print($results[0]);
            $answer_array=array('sentence case','title case','neither');
            if ((count($results)==1)&&(in_array($result,$answer_array))){
                $result_value=trim($result);
            } else if (in_array(trim(strtolower(str_replace(".","",$results[0]))),$answer_array)) {
                $result_value=trim(strtolower(str_replace(".","",$results[0])));
            } else {
                $returnValue = preg_match_all("#\*\*(.*)\*\*#", $results[0] , $matches);
                $result_value=trim($matches[1][0]);
            }
            if (in_array($result_value,$answer_array)){
                print("Title type:");
                print($result_value);
                print("\n");
                for ($cloop=0;$cloop<count($results);$cloop++){
                    if (str_starts_with("Rewritten title:",$results[$cloop])){
                        $correct_title=trim(str_replace("Rewritten title:","",$results[$cloop]));
                        $abstracts_qa_data[$abstract["id"]]["title"]["case"]=$results[0];
                        $abstracts_qa_data[$abstract["id"]]["title"]["correct_case"]=$correct_title;
                        $abstracts_qa_data[$abstract["id"]]["title"]["date"]=time();
                        print("title_correct_case: ".$abstracts_qa_data[$abstract["id"]]["title"]["correct_case"]."\n");
                    }
                }
            } else {
                print("Result can not be understood:\n");
                print($result);
                print("\nstrlen");
                print(strlen($result));
                print("\nQuestion was:\n");
                print($question);
                die("\nUnable to understand result");
            }
            flush();
            $fwret=file_write_json(  $cws_config['global']['data_path']."/abstracts_qa.json",$abstracts_qa_data);
        } // result is not empty
        sleep(0.5);
        $ai_calls_counter+=1;
    } else{
        print("Title key exists:\n");
        print($abstracts_qa_data[$abstract["id"]]["title"]["case"]."\n");
        print($abstracts_qa_data[$abstract["id"]]["title"]["correct_case"]."\n");
        print("\n");
    }
    if ((!(array_key_exists("lgender",$abstracts_qa_data[$abstract["id"]])))||(strlen($abstracts_qa_data[$abstract["id"]]["gender"])==0)){
        $ai =new AI_REQUEST();
        $speaker_first_name="";
        $speaker_last_name="";
        foreach($abstract['persons'] as $person){
            if ($person["is_speaker"]){
                $speaker_first_name=$person["first_name"];
                $speaker_last_name=$person["last_name"];
                if ($person["affiliation_link"]){
                    $speaker_country=$person["affiliation_link"]["country_name"];
                } else{
                    $speaker_country=$person["address"];
                }
            }
        }
        if (strlen($speaker_first_name)>0){
            $question='I would like to know the most likely gender of a person whose first name is  "'.$speaker_first_name.'" and their last name is "'.$speaker_last_name.'", living in "'.$speaker_country.'". What is your guess? Please answer by saying only male, female or unsure on the first line with additional explanations after';
            //print($question);
            //print("<BR/>");
            $result=$ai->query($question);
            //print("result:");
            //print($result);
            //print("<BR/>\n");
            //print_r(explode("\n",$result));
            if (!($result)){
                print("No result");
                sleep(5);          
            } else if (strlen($result)==0){
                print("Empty result");
                sleep(5);          
            } else {
                $results=explode("\n",$result);
                $answer_array=array('male','female','unsure');
                if ((count($results)==1)&&(in_array($result,$answer_array))){
                    $result_value=trim($result);
                } else if (in_array(trim(strtolower(str_replace(".","",$results[0]))),$answer_array)) {
                    $result_value=trim(strtolower(str_replace(".","",$results[0])));
                } else {
                    for ($cloop=0;$cloop<count($results);$cloop++){
                        $returnValue = preg_match_all("#\*\*(.*)\*\*#", $results[$cloop] , $matches);
                        if ($returnValue){                        
                            $result_value=trim($matches[1][0]);
                        }
                    }
                }
                if (in_array($result_value,$answer_array)){
                    $abstracts_qa_data[$abstract["id"]]["gender"]["likely_gender"]=$results[0];
                    $abstracts_qa_data[$abstract["id"]]["gender"]["speaker_likely_gender"]=$result_value;
                    print("Gender: ");
                    print($speaker_first_name);
                    print(" : ");
                    print($result_value);
                    print("\n");
                    $abstracts_qa_data[$abstract["id"]]["gender"]["speaker_first_name"]=$speaker_first_name;
                    $abstracts_qa_data[$abstract["id"]]["gender"]["speaker_last_name"]=$speaker_last_name;
                    $abstracts_qa_data[$abstract["id"]]["gender"]["speaker_country"]=$speaker_country;
                    $abstracts_qa_data[$abstract["id"]]["gender"]["date"]=time();
                    $fwret=file_write_json(  $cws_config['global']['data_path']."/abstracts_qa.json",$abstracts_qa_data);
                } else {
                    print("Result can not be understood:\n");
                    print($result);
                    print("\nstrlen");
                    print(strlen($result));
                    print("\nQuestion was:\n");
                    print($question);
                    die("\nUnable to understand result");
                }
            } //result not empty
            flush();
            sleep(0.5);
            $ai_calls_counter+=1;
        } else {
            print("Unable to find speaker first name\n");
        }
    } else {
        print("Gender key exists: \n");
        //print(strlen($abstracts_qa_data[$abstract["id"]]["likely_gender"])."\n");
        print($abstracts_qa_data[$abstract["id"]]["gender"]["speaker_first_name"].": ");
        print($abstracts_qa_data[$abstract["id"]]["gender"]["likely_gender"]."\n");
        print("\n");
    }
    print("<BR/><BR/><BR/>\n\n\n");
    if ($ai_calls_counter>25){
        die("AI called ".$ai_calls_counter." times. Stopping.");         
    }

} // for each abstract
print("All abstracts checked.");
?>