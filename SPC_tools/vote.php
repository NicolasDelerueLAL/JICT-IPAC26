<?php

/* Created by Nicolas.Delerue@ijclab.in2p3.fr
2025.11.12 1st version

This page gives links to several tools needed by SPC.

*/
if (str_contains($_SERVER["QUERY_STRING"],"debug")){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} //if debug on


require( '../config.php' );
require_lib( 'jict', '1.0' );
require_lib( 'indico', '1.0' );

$cfg =config( 'SPC_tools', false, false );
$cfg['verbose'] =0;

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;


$Indico->load();

require( 'autoconfig.php' );

$first_question_id=$cws_config['SPC_tools']['first_question_id'];
$second_question_id=$cws_config['SPC_tools']['second_question_id'];

$T =new TMPL( $cfg['template_vote'] );
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
$fields_to_display=[ "id" => "id", "friendly_id" => "Conf id", "MC_track"=> "MC and track"  , "title" => "Title",  "primary_author_name" => "Main author", "content" => "Abstract" , "vote" => "Vote", "vote_by_MC" => "Vote by MC" , "all_comments" => "Comments"  ];
$num_fields=[  "id", "friendly_id" ];
$link_fields=[ "id", "title" ];


$js_variables ="
<script>
";

    
    
$content .="<form>";
$content .="<input type='button' id='btnHideAbs' value='Hide abstracts'>\n";
$content .="<input type='button' id='btnShowAbs' value='Show abstracts'>\n";
$content .="<input type='button' id='btnHideComments' value='Hide comments'>\n";
$content .="<input type='button' id='btnShowComments' value='Show comments'>\n";

/*
for ($imc=1; $imc<=8; $imc++){
    $content .="<input type='button' id='toggle_mc_".$imc."' value='Hide ".$imc."' onClick='toggle_visibility_mc(".$imc.")'>\n";
}
*/
$content .="</form>\n";

$content .="
<div class=\"table-wrap\"><table id='abstracts_table' class=\"sortable\" width=\"95%\">
    <caption> (column headers with buttons are sortable)
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

$js_variables .="   
</script>
";

$content .=$js_variables;
 
$_rqst_cfg=[];
$_rqst_cfg['disable_cache'] =true;
$data_key= $Indico->request( '/event/{id}/manage/abstracts/abstracts.json', 'GET', false, $_rqst_cfg);
$votes_count=[];
for ($imc=1; $imc<=8; $imc++){
    $votes_count["MC".$imc][1] =0;
    $votes_count["MC".$imc][2] =0;
    $votes_count["MC".$imc][3] =0;
}

foreach ($Indico->data[$data_key]['abstracts'] as $abstract) {
    if ($abstract["state"]=="submitted"){
        $abstract["MC"]=substr($abstract["submitted_for_tracks"][0]["code"],0,3);
        $abstract["track"]=$abstract["submitted_for_tracks"][0]["code"]." - ".$abstract["submitted_for_tracks"][0]["title"];
        //print_r($abstract["submitted_for_tracks"][0]);
        $abstract["MC_track"]=$abstract["MC"]." - ".$abstract["submitted_for_tracks"][0]["code"].": ".$abstract["submitted_for_tracks"][0]["title"];
        $abstract["primary_author_name"]="";
        foreach($abstract["persons"] as $pers){
            if ($pers["author_type"]=="primary"){
                if (empty($abstract["primary_author_name"])) {
                    $abstract["primary_author_name"]=$pers["first_name"]." ".$pers["last_name"];
                }
            }
        } //find primary author
        if (empty($abstract["primary_author_name"])) {
            $abstract["primary_author_name"]=$abstract["persons"][0]["first_name"]." ".$abstract["persons"][0]["last_name"];
        }

        $current_vote="3";
        $current_action="";
        $review_id=0;
        $current_action="accept";
        $new_track_id=0;
        $abstract["vote"]="";
        foreach($abstract["reviews"] as $review){
            //echo "review\n"; 
            //var_dump($review);
            if ($review["user"]["full_name"]==$_SESSION['indico_oauth']['user']["full_name"]){
                $review_id=$review["id"];
                //echo "my review info\n";
                //var_dump($review);
                if (($review["proposed_action"]=="accept")||($review["proposed_action"]=="change_tracks")){
                    $current_action=$review["proposed_action"];
                    foreach($review["ratings"] as $rating){
                        if ($rating["question"]==$first_question_id){
                            if ($rating["value"]==true){
                                $current_vote="1";
                            }
                        } else if ($rating["question"]==$second_question_id){
                            if ($current_vote!="1"){
                                if ($rating["value"]==true){
                                    $current_vote="2";
                                }
                            }  
                        }                      
                    } //for each rating
                    if ($review["proposed_action"]=="change_tracks"){
                        //print_r($review);
                        $new_track_id=$review["proposed_tracks"][0]["id"];
                        $new_track_code=$review["proposed_tracks"][0]["code"];
                    }
                    $votes_count[$abstract["MC"]][$current_vote] +=1;
                } else {
                    //print($review["proposed_action"]);
                    //$abstract["vote"]=$review["proposed_action"];
                    $current_vote="3";
                    continue;
                }
            } // review by this user
        } //find vote
        foreach (array(1,2,3) as $vote_value){
            if ($vote_value==1){
                $vote_value_text="1st choice";
            } else if ($vote_value==2) {
                $vote_value_text="2nd choice";
            } else {
                $vote_value_text="Cancel";
            }
            if (!($current_vote==$vote_value)){
                $abstract["vote"].="\n<form><input type=\"button\" onclick=\"vote(".$vote_value.",".$abstract["id"].",".$review_id.",".$abstract["submitted_for_tracks"][0]["id"].",".$new_track_id.")\" value=\"Vote ".$vote_value_text."\"></button></form>\n";
            }
        }
        //$abstract["vote"].="\n<form><input type=\"button\" onclick=\"color_abstract(".$abstract["id"].",'#F7DC6F')\" value=\"Color abstract\"></button></form>\n";
        if ($current_vote=="1"){
            $abstract["vote"]="1st choice\n".$abstract["vote"];
        } else if ($current_vote=="2") {
            $abstract["vote"]="2nd choice\n".$abstract["vote"];
        } else {
            $abstract["vote"]="No vote\n".$abstract["vote"];
        }
        $abstract["vote_by_MC"]=$abstract["MC"]."_".$current_vote;

        //deal with comments
        $abstract["all_comments"] ="";
        foreach($abstract["comments"] as $comment){
            $abstract["all_comments"] .=$comment["user"]["full_name"].": ".$comment["text"]."<BR/>\n";
        }
        $abstract["all_comments"] .="<BR/><form>\n
        <INPUT type='hidden' name='abstract_id' value='".$abstract["id"]."'>\n
        <INPUT type='text' name='comment_".$abstract["id"]."' id='comment_".$abstract["id"]."' size='10'>\n
        <INPUT type=button value='Add comment' onclick=\"add_comment(".$abstract["id"].")\">
        </form>";

        $abstract["MC_track"] .="<BR/><form>\n<select id=\"change_track_".$abstract["id"]."\" onchange=\"change_track(".$abstract["id"].")\">\n";
        $abstract["MC_track"] .="<option value=\"no_change\">Change track</option>\n";    
        //print_r($cws_config['SPC_tools']['tracks']);    
        foreach($cws_config['SPC_tools']['tracks'] as $track){
            $abstract["MC_track"] .="<option value=\"".$track['id']."\">".$track['code']."</option>\n";
        }
        $abstract["MC_track"] .="</select>\n";
        $abstract["MC_track"] .="<INPUT type='hidden' id='abstract_".$abstract["id"]."_track_id' value=".$abstract["submitted_for_tracks"][0]["id"].">\n";
        $abstract["MC_track"] .="<INPUT type='hidden' id='abstract_".$abstract["id"]."_review_id' value=".$review_id.">\n";
        $abstract["MC_track"] .="</form>\n";

        //<INPUT type=button value='A' onclick=\"change_track(".$abstract["id"].")\">


        if ($new_track_id>0){
            $abstract["vote"].="\n<BR/>Track change to ".$new_track_code." proposed\n";
            $abstract["MC_track"].="\n<BR/>Track change to ".$new_track_code." proposed\n";
        }


        $content .="<TR id=\"TR-".$abstract["id"]."\">\n"; 
        foreach ($fields_to_display as $field => $display){       
            //echo "$field: ".$abstract[$field]." <BR/>\n"; 
            $content .="<TD ";
            if ($current_vote=="1"){
                $content .="style=\"background-color: #c39bd3 ;\""; 
            } else if ($current_vote=="2"){
                $content .="style=\"background-color: #a9cce3 ;\""; 
            }
            $content .=">";
            if (in_array($field,$link_fields)){
                $content .="<A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/abstracts/". $abstract['id']."/' >";
            }
                $content .="". $abstract[$field];
            if (in_array($field,$link_fields)){
                $content .="</A>";
                //<button type=button onClick='vote(1,"+'"'+this.responseURL+'"'+',"'+review_code+'"'+',"'+csrf_token+'"'+',"'+track_id+'"'+")'>Vote 1st</button>";
            } 
            $content .= "</TD>\n"; 
        } //for each field
        $content .="</TR>\n"; 
    } //if submitted
    else {
        //echo "Skipping abstract id ".$abstract["id"]." state ".$abstract["state"]."\n";
        continue;
    }
} //for each abstract
$content .="</TBODY>\n"; 

$content .="</TABLE>\n"; 
$content .="</div>\n"; 

//var_dump($votes_count);

//Create the vote table
$vote_table_content="";
$vote_table_content.="<P><center>\n";
$vote_table_content.="<h3>Your votes summary</h3>\n";
$vote_table_content.="<div class=\"table-wrap\"><table id=\"votes\" class=\"vote_table\">\n";
$vote_table_content.="  <caption> Your votes\n";
$vote_table_content.="  </caption>\n";
$vote_table_content.="  <thead>\n";
$vote_table_content.="  <tr>\n";
$vote_table_content.="  <th></th>\n";
for ($imc=1; $imc<=8; $imc++){
    $vote_table_content.="  <th>MC".$imc."</th>\n";
}
$vote_table_content.="  </tr>\n";
$vote_table_content.="</thead>  <tbody>\n";
for($ivote=1; $ivote<=2; $ivote++){ 
    $vote_table_content.="  <tr>\n";
    if ($ivote==1){
        $vote_table_content.="  <td>First priority</td>\n";
    } else {
        $vote_table_content.="  <td>Second priority</td>\n";
    }
    for ($imc=1; $imc<=8; $imc++){
        $vote_table_content.="  <td> ".$votes_count["MC".$imc][$ivote]."</td>\n";  
    }
    $vote_table_content.="  </tr>\n";
}
$vote_table_content.="  </tbody>\n";
$vote_table_content.="</table></div>\n";
$vote_table_content.="</center></P>\n";

$content =$vote_table_content.$content;

$T->set( 'content', $content );
for ($imc=1; $imc<=8; $imc++){
    $mc_sum=$votes_count["MC".$imc][1]+$votes_count["MC".$imc][2];
    $T->set( 'MC'.$imc.'_n', "".$votes_count["MC".$imc][1]."+".$votes_count["MC".$imc][2]." = ".$mc_sum );
}
$T->set( 'column_width', $column_width);
$T->set( 'event_id', $cws_config['global']['indico_event_id'] );
$T->set( 'user_name', $_SESSION['indico_oauth']["user"]["full_name"]);
$T->set( 'user_first_name', $_SESSION['indico_oauth']["user"]["first_name"]);
$T->set( 'user_last_name',$_SESSION['indico_oauth']["user"]["last_name"]);
echo $T->get();


?>