<?php

/*-----------------------------------------
*/
class AI_REQUEST {
	var $ai_headers, $response_code, $result, $error, $cfg, $cws_config;


	function __construct( ) {
        //print("construct");
        //nothing
	}
  function query($question){
    global $cws_config;
    //print("Query: ".$question."<BR/>\n");
    $apiKey = $cws_config['global']['mistral_key']; // Replace with your actual API key
    $apiUrl = 'https://api.mistral.ai/v1/chat/completions';

    $data = [
        'model' => 'mistral-small-latest',
        'messages' => [
            ['role' => 'user', 'content' => $question]
        ],
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    } else {
        print($result);
        print("<BR/>");
        $response = json_decode($result, true);
        if (isset($response['error'])) {
            echo "API Error: " . $response['error']['message'];
            return false;
        } else {
            print_r($response);
            //echo $response['choices'][0]['message']['content'];
            return $response['choices'][0]['message']['content'];
        }
    }
    curl_close($ch);
  } //query ai
} //class AI


function add_editor_to_contact_info(){    
    global $Indico;
    $req =$Indico->request( "/event/{id}/manage/settings/contact-info", 'POST', array(
    "contact_emails"=> 	'[{"email":"contact@ipac26.org"},{"email":"scientific.secretariat@ipac26.org"},{"email":"editor@ipac26.org"},{"email":"registration@ipac26.org}]',
 ));
} //add_editor_to_contact_info()

function remove_editor_from_contact_info(){    
    global $Indico;
    $req =$Indico->request( "/event/{id}/manage/settings/contact-info", 'POST', array(
    "contact_emails"=> 	'[{"email":"contact@ipac26.org"},{"email":"scientific.secretariat@ipac26.org"},{"email":"registration@ipac26.org}]',
 ));
}  //remove_editor_from_contact_info()

function load_abstracts($disable_cache=false){
    global $Indico;
    global $abstracts,$all_abstracts;
    $_rqst_cfg=[];
    $_rqst_cfg['disable_cache'] =$disable_cache;
    //$_rqst_cfg['disable_cache'] =true;
    $req_abstracts =$Indico->request( "/event/{id}/manage/abstracts/abstracts.json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true ) );
    $abstracts=[];
    //var_dump(json_decode($req_abstracts,true));
    //var_dump(json_decode($req_abstracts,true)['abstracts']);
    //var_dump($req_abstracts);
    //var_dump($req_abstracts['abstracts']);
    //$all_abstracts=json_decode($req_abstracts,true)['abstracts'];
    $all_abstracts=$req_abstracts['abstracts'];
    foreach($all_abstracts as $abstract){
        $abstracts[$abstract["id"]]=$abstract;
    }
}//load_abstracts

function load_contributions($disable_contributions_cache=false,$fix_affiliations=false){
    global $Indico,$cws_config;
    global $contributions,$contributions_by_abs_id,$contributions_by_fr_id,$all_contributions;
    global $abstracts;
    $_rqst_cfg=[];
    $_rqst_cfg['disable_cache'] =$disable_contributions_cache;
    if ($disable_contributions_cache){
        $cache_time=1;
    } else {
        $cache_time=60*60*24*7;
    }
    //$_rqst_cfg['disable_cache'] =true;
    $affiliation_fixes=file_read_json($cws_config['global']['data_path']."/affiliation_fixes.json",true);
    $req_contributions =$Indico->request( "/event/{id}/manage/contributions/contributions.json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' => $disable_contributions_cache, 'cache_time' => $cache_time)  );
    $contributions=[];
    $contributions_by_abs_id=[];
    //$all_contributions=json_decode($req_contributions,true);
    $all_contributions=$req_contributions;
    foreach($all_contributions as $contribution){
        $contribution["MC"]=substr($contribution["track"]["code"],0,3);
        //$contribution["track_name"]=$contribution["track"]["code"]." - ".$contribution["track"]["title"];
        $contribution["track_name"]=$contribution["track"]["title"];

        $contribution["speaker_name"]="";
        $contribution["speaker_country"]="";
        $contribution["speaker_region"]="";
        $contribution["speaker_affiliation"]="";
        $contribution["primary_author_name"]="";
        $contribution["primary_author_email"]="";
        $contribution["primary_author_affiliation"]="";
        $contribution["primary_author_region"]="";
        $contribution["primary_author_country"]="";
        $contribution["regions"]="";
        $contribution["region"]="";
        for ($ipers=0;$ipers<count($contribution["persons"]);$ipers++){
            $person=$contribution["persons"][$ipers];
            if ($person["author_type"]=="primary"){
                $contribution["primary_author_name"]=$person["full_name"];
                $contribution["primary_author_email"]=$person["email"];
                $contribution["primary_author_affiliation"]=$person["affiliation"];
            }
            if ($person["is_speaker"]==true){
                $contribution["speaker_name"]=$person["full_name"];
                $contribution["speaker_email"]=$person["email"];
                $contribution["speaker_affiliation"]=$person["affiliation"];
            }
            if ((!($person["affiliation_link"]))||($person["affiliation_link"]=="NULL")||($person["affiliation_link"]==NULL)||(str_contains($person["affiliation"],";"))){
                if ($fix_affiliations){
                    $affiliations=[];
                    if (str_contains($person["affiliation"],";")){
                        $affiliations=explode(";",$person["affiliation"]);
                    } else {
                        $affiliations[]=$person["affiliation"];
                    }
                    for ($jaff=0;$jaff<count($affiliations);$jaff++){
                        if ($jaff==0){
                            $link_name="affiliation_link";
                        } else {
                            $link_name="affiliation_link_".($jaff+1);
                        }
                        $person_affiliation=trim($affiliations[$jaff]);
                        $Indico->api->config('disable_cache', false);
                        $Indico->api->config('cache_time', 60*60*24*7);
                        $req_aff =$Indico->request( "/api/affiliations/?q=".urlencode($person_affiliation), 'GET', false, array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' => false, 'cache_time' => 60*60*24*7) );
                        if (count($req_aff)>1){
                            for ($iaff=0;$iaff<count($req_aff);$iaff++){
                                if ($req_aff[$iaff]["name"]==$person_affiliation){
                                    $person[$link_name]=$req_aff[$iaff];   
                                    $contribution["persons"][$ipers]=$person;                    
                                }                     
                            }
                        } else if (count($req_aff)==1){
                            $person[$link_name]=$req_aff[0];   
                            $contribution["persons"][$ipers]=$person;                    
                        }
                    }
                    /*
                    if (count($affiliations)>1){
                        print("Person with multiple affiliations<BR/>\n");
                        var_dump($person);
                    }
                    */
                }
            }
            //fix incorrect affiliations
            if (($person["affiliation_link"])&&($person["affiliation_link"]["id"])&&(array_key_exists($person["affiliation_link"]["id"],$affiliation_fixes))){
                if (($person["affiliation_link"])&&($person["affiliation_link"]["id"])&&(!$person["affiliation_link"]["id"]==null)){
                    $iwhile=0;
                    while ((array_key_exists($person["affiliation_link"]["id"],$affiliation_fixes))&&($iwhile<5)){
                        if (array_key_exists("Accept",$affiliation_fixes[$person["affiliation_link"]["id"]])){
                            if ($affiliation_fixes[$person["affiliation_link"]["id"]]["Accept"]==1){
                                break;
                            }
                        }
                        $person["affiliation_link"]=$affiliation_fixes[$person["affiliation_link"]["id"]];
                        //var_dump($person["affiliation_link"]);
                        $iwhile+=1;
                    }
                }
                $contribution["persons"][$ipers]=$person;                    
            }
            if (($person["affiliation_link"])&&($person["affiliation_link"]["country_code"])){
                $the_region=get_region($person["affiliation_link"]["country_code"]);
                if (!(preg_match("#".$the_region."#",$contribution["regions"]))){
                    $contribution["regions"].=$the_region.", ";
                }
                if (($person["author_type"]=="primary")||(strlen($contribution["primary_author_region"])==0)){
                    $contribution["primary_author_region"]=$the_region;
                    if (!$contribution["region"]){
                        $contribution["region"]=$the_region;
                    }
                    $contribution["primary_author_country"]=$person["affiliation_link"]["country_name"];
                }
                if ($person["is_speaker"]==true){
                    $contribution["region"]=$the_region;                    
                    $contribution["speaker_country"]=$person["affiliation_link"]["country_name"];
                    $contribution["speaker_region"]=$the_region;
                }
            }
        }
        if (strlen($contribution["regions"])>0){
            $contribution["regions"]=substr($contribution["regions"],0,strlen($contribution["regions"])-2);
        }
        if ($contribution["abstract_id"]){
            $contributions_by_abs_id[$contribution["abstract_id"]]=$contribution["id"];
            //Looking for the submitter
            if (($abstracts)&&(count($abstracts)>0)){
                $abstract=$abstracts[$contribution["abstract_id"]];
                $contribution["submitter"]=$abstract["submitter"];                
            }
        }     
        $contributions[$contribution["id"]]=$contribution;
        $contributions_by_fr_id[$contribution["friendly_id"]]=$contribution["id"];
    }
}// load_contributions



function send_email_to_participant($subject,$body,$recipient_email,$sender_email,$copy_for_sender){
    global $Indico;
    print("Get eventPerson IDs<BR/>\n");
    //get EventPerson id
    $req =$Indico->request( "/event/{id}/manage/persons/", 'GET', array( ) , array( 'return_data' =>true, 'quiet' =>true ,  'disable_cache' => true ) );
    //var_dump($req);
    $matches=[];
    $matchtxt='#"email":"'.$recipient_email.'",(.*),"identifier":"(EventPerson:[0-9]+)",(.*),"user_identifier":"(User:.*)"#';
    $returnValue = preg_match_all($matchtxt, $req, $matches);
    //print("email\n");
    if (!(count($matches[2])==1)){
        print("Error while searching person ID.<BR/>\n");
        var_dump($matches);
        return false;
    } else {
        $eventPerson=$matches[2][0];
    }
    $post_data=array(
        "sender_address" => $sender_email,
        "subject" => $subject, 
        "body" => $body,
        "bcc_addresses" => array(),
        "copy_for_sender" => $copy_for_sender ,
        "role_id" => array() ,
        "persons" => array( $eventPerson ),
        "no_account" => false,
        "not_invited_only" => false, 
    );
    //var_dump($post_data);
    $Indico->api->config('header_content_type', 'application/json');

    $req =$Indico->request( "/event/{id}/manage/api/persons/email/send", 'POST', $post_data,  array( 'return_data' =>true, 'quiet' => false ) );
    //var_dump($req);
    print("Message sent <BR/>\n");
    return true;
}//send_email_to_participant

function send_email_file_to_contributor_as_editor($file,$recipient_role,$contribution_ids,$copy_for_sender,$contribution=null){
    //add_editor_to_contact_info();
    $sender_email="editor@ipac26.org";
    send_email_file_to_contributor($file,$recipient_role,$contribution_ids,$sender_email,$copy_for_sender,$contribution);
    //remove_editor_from_contact_info();
} 


function send_email_file_to_contributor($file,$recipient_role,$contribution_ids,$sender_email,$copy_for_sender,$contribution=null){
    if ($contribution){
        $message=file_get_contents("messages/".$file);
        $matches=[];
        $matchtxt='/##([a-zA-Z=_:]+)##/';
        $returnValue = preg_match_all($matchtxt, $message, $matches);
        foreach($matches[1] as $thematch){
            /*
            var_dump($thematch);
        print(" <BR/>\n");
            print("the match $thematch <BR/>\n");
            print(substr($thematch,1)." <BR/>\n");
            print($contribution[substr($thematch,1)]);
        print(" <BR/>\n");
            */
            if (substr($thematch,0,1)=="="){
                if (str_contains($thematch,":")){
                    $fields=explode(":",substr($thematch,1));
                    //var_dump($fields);
                    if (count($fields)==2){
                        $newvalue=$contribution[$fields[0]][$fields[1]];
                    } else if (count($fields)==3){
                        $newvalue=$contribution[$fields[0]][$fields[1]][$fields[2]];
                    }
                } else {
                    $newvalue=$contribution[substr($thematch,1)];
                }
                $message=str_replace("##".$thematch."##",$newvalue,$message);
            }
        }
        //print($message);
        /*
        print(" <BR/>\n");
        print(" <BR/>\n");
        */
        $subject=substr($message,0,strpos($message,"\n"));
        //print("Subject: $subject <BR/>\n");
        $body=str_replace("\n","<BR/>\n",substr($message,strpos($message,"\n")+1));
        send_email_to_contributor($subject,$body,$recipient_role,$contribution_ids,$sender_email,$copy_for_sender);
    } else {
        print("Contribution is null!<BR/>\n");
    }
}

function send_email_to_contributor($subject,$body,$recipient_role,$contribution_ids,$sender_email,$copy_for_sender){
    global $Indico;
    $post_data=array(
        "sender_address" => $sender_email,
        "subject" => $subject, 
        "body" => $body,
        "bcc_addresses" => array(),
        "copy_for_sender" => $copy_for_sender ,
        "recipient_roles" => $recipient_role ,
        "contribution_id" => $contribution_ids,
    );
    //var_dump($post_data);
    $Indico->api->config('header_content_type', 'application/json');

    $req =$Indico->request( "/event/{id}/manage/contributions/api/email-roles/send", 'POST', $post_data,  array( 'return_data' =>true, 'quiet' => false ) );
        if (1==0){
            print("<BR/>\n");
            var_dump($post_data);
            print("<BR/>\n");
            var_dump($req);
            print("<BR/>\n");
        }
    if (!$req){
        print("Message NOT sent <BR/>\n");
        var_dump($req);
        print("<BR/>\n");
        var_dump($post_data);
        return false;
    } else if (array_key_exists("count",$req)){
        print("Message(s) sent: ".$req["count"]."<BR/>\n");
        return true;
    } else {
        print("Message NOT sent <BR/>\n");
        var_dump($req);
        print("<BR/>\n");
        var_dump($post_data);
        return false;
    }
}//function send_email_to_contributor($subject,$body,$recipient_role,$contribution_id,$sender_email,$copy_for_sender){




function check_contribution_title_case($contribution_id,$retry=false){
    global $Indico;
    global $contribs_qa_data;
    global $cws_config;

    $req =$Indico->request( "/event/{id}/contributions/".$contribution_id.".json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true ) );

    print("Title: ".$req["title"]."<BR/>\n");
    $contribs_qa_data=file_read_json(  $cws_config['global']['data_path']."/contribs_qa.json",true);
    if (!(isset($contribs_qa_data))){
        $contribs_qa_data=[];
    }
    if (!($contribs_qa_data)){
        $contribs_qa_data=[];
    }

    if (
        (array_key_exists($contribution_id,$contribs_qa_data))
            and(array_key_exists("title",$contribs_qa_data[$contribution_id]))
            and($contribs_qa_data[$contribution_id]["title"]["fixed"]==true)
            and(($req["title"]==$contribs_qa_data[$contribution_id]["title"]["sentence_case"])
                or ($req["title"]==$contribs_qa_data[$contribution_id]["title"]["old"]))
            and(!($retry))
    ){
        print("Entry already exists<BR/>\n");
        print("Title case: ".$contribs_qa_data[$contribution_id]["title"]["case"]."<BR/>\n");
        print("Sentence case: ".$contribs_qa_data[$contribution_id]["title"]["sentence_case"]."<BR/>\n");
        print("Date: ".$contribs_qa_data[$contribution_id]["title"]["date"]."<BR/>\n");
    }  else {
        print("Title case not yet determined for contribution <A HREF='https://indico.jacow.org/event/95/contributions/".$contribution_id."/'>".$contribution_id."</A>.<BR/>\n");
        $contribs_qa_data[$contribution_id]=[];
        $contribs_qa_data[$contribution_id]["title"]=[];
        $contribs_qa_data[$contribution_id]["title"]["old"]=$req["title"];
        $contribs_qa_data[$contribution_id]["title"]["fixed"]=false;
        $contribs_qa_data[$contribution_id]["title"]["sentence_case"]="";
        $contribs_qa_data[$contribution_id]["title"]["upper_case"]="";
        $ai =new AI_REQUEST();
        $question='This is the title of a scientific publication: '.$req["title"].'. Is this title in title case, in sentence case or neither? Please answer by saying title case, sentence case or neither.';
        print("Question to AI:<BR/>\n");
        print($question);
        print("<BR/>\n");
        $result=false;
        $result=$ai->query($question);
        if (!($result)){
            //print("No result<BR/>\n");
            die("No result<BR/>\n");
        } else if (strlen($result)==0){
            die("Empty result<BR/>\n");
        } else {
            print("Answer:<BR/>\n");
            print($result);
            print("<BR/>\n");
            $results=explode("\n",$result);
            $answer_array=array('sentence case','title case','neither');
            if ((count($results)==1)&&(in_array($result,$answer_array))){
                $result_value=trim($result);
                $contribs_qa_data[$contribution_id]["title"]["case"]=$result_value;
            } else if (in_array(trim(strtolower(str_replace(".","",$results[0]))),$answer_array)) {
                $result_value=trim(strtolower(str_replace(".","",$results[0])));
                $contribs_qa_data[$contribution_id]["title"]["case"]=$result_value;
            } else {
                $result_found=false;
                foreach($answer_array as $answer){
                    if (str_contains(trim(strtolower($results[0])),$answer)){
                        $result_value=$answer;
                        $contribs_qa_data[$contribution_id]["title"]["case"]=$result_value;
                        $result_found=true;
                    }
                }
                if (!($result_found)){
                    print("Result can not be understood:<BR/>\n");
                    var_dump($results);
                    die("<BR/>\nUnable to understand result");
                }
            }
        }
        print("<BR/>\n");
        $question='This is the title of a scientific publication: '.$req["title"].'. I will want to convert it into sentence case and uppercase. Could you return an array with all the words it contains whose first letter should always be capitalized (such as proper nouns,...), on a second line an array with all the words it contains whose case should never be changed (acronyms, units,...) and a third line with an array of all other words whose case should be adapted to the context? Please answer with the following format: Proper nouns: [list of nouns separated by semicolons];\n NoCaseChange: [list of other words separated by semicolons]\nNoCaseChange: [list of words whose case should not be changed separated by semicolons]\nOtherWords: [list of other words separated by semicolons].';
        print("Question to AI:<BR/>\n");
        print($question);
        print("<BR/>\n");
        $result=false;
        $result=$ai->query($question);
        if (!($result)){
            print("No result<BR/>\n");
        } else if (strlen($result)==0){
            print("Empty result<BR/>\n");
        } else {
            print($result);
            $results=explode("\n",$result);
            if (count($results)>=3){
                foreach($results as $line){
                    print("Line: ".$line."<BR/>\n");
                    if (str_starts_with(trim($line),"Proper nouns:")){
                        print("Proper nouns line found<BR/>\n");
                        $contribs_qa_data[$contribution_id]["title"]["proper_nouns"]=trim(str_replace("Proper nouns:","",$line));
                    } else if (str_starts_with($line,"NoCaseChange:")){
                        print("No case change line found<BR/>\n");
                        $contribs_qa_data[$contribution_id]["title"]["no_case_change"]=trim(str_replace("NoCaseChange:","",$line));
                    } else if (str_starts_with($line,"OtherWords:")){
                        print("Other words line found<BR/>\n");
                        $contribs_qa_data[$contribution_id]["title"]["other_words"]=trim(str_replace("OtherWords:","",$line));
                    } else {
                        print("Line ignored:<BR/>\n".$line."<BR/>\n");
                    }
                }
            } else {
                print("Result can not be understood:<BR/>\n");
                print($result);
                die("<BR/>\nUnable to understand result");
            }
        }
        //rewrite the title in sentence case and uppercase using the arrays provided by the AI
        //first put the title in lowercase and then uppercase the first letter of each word that is either in the proper nouns list or in the no case change list, and put all the other words in lowercase for sentence case and uppercase for uppercase
        $contribs_qa_data[$contribution_id]["title"]["sentence_case"]=strtolower($req["title"]);
        $contribs_qa_data[$contribution_id]["title"]["upper_case"]=strtoupper($req["title"]);
        $proper_nouns=str_replace("]","",str_replace("[","",$contribs_qa_data[$contribution_id]["title"]["proper_nouns"]));
        foreach(explode(";", $proper_nouns) as $word){
            $contribs_qa_data[$contribution_id]["title"]["sentence_case"]=str_ireplace(strtolower($word),ucfirst(strtolower($word)),$contribs_qa_data[$contribution_id]["title"]["sentence_case"]);
        }
        $noCaseChange=str_replace("]","",str_replace("[","",$contribs_qa_data[$contribution_id]["title"]["no_case_change"]));
        print("Sentence case with nouns: ".$contribs_qa_data[$contribution_id]["title"]["sentence_case"]."<BR/>\n");
        foreach(explode(";",$noCaseChange) as $word){
            $contribs_qa_data[$contribution_id]["title"]["sentence_case"]=str_ireplace(strtolower($word),$word,$contribs_qa_data[$contribution_id]["title"]["sentence_case"]);
            $contribs_qa_data[$contribution_id]["title"]["upper_case"]=str_replace(strtoupper($word),$word,$contribs_qa_data[$contribution_id]["title"]["upper_case"]);
        }
        $contribs_qa_data[$contribution_id]["title"]["sentence_case"]=ucfirst($contribs_qa_data[$contribution_id]["title"]["sentence_case"]);
        print("Rewritten titles<BR/>\n");
        print("Sentence case: ".$contribs_qa_data[$contribution_id]["title"]["sentence_case"]."<BR/>\n");
        print("Upper case: ".$contribs_qa_data[$contribution_id]["title"]["upper_case"]."<BR/>\n");

        flush();
        $contribs_qa_data[$contribution_id]["title"]["date"]=time();
        $contribs_qa_data[$contribution_id]["title"]["fixed"]=true;
        //var_dump($contribs_qa_data[$contribution_id]);
        $fwret=file_write_json(  $cws_config['global']['data_path']."/contribs_qa.json",$contribs_qa_data);
        print($fwret?"Data saved successfully<BR/>\n":"Error saving data<BR/>\n");
    } // title case not yet determined

    print("<HR/>\n");
    print("<TABLE>\n");
    print("<TR><TD>Old title:</TD><TD>".$contribs_qa_data[$contribution_id]["title"]["old"]."</TD></TR>\n");
    print("<TR><TD>Sentence case title:</TD><TD>".$contribs_qa_data[$contribution_id]["title"]["sentence_case"]."</TD></TR>\n");
    print("<TR><TD>Uppercase title:</TD><TD>".$contribs_qa_data[$contribution_id]["title"]["upper_case"]."</TD></TR>\n");
    print("</TABLE>\n");
    if (($contribs_qa_data[$contribution_id]["title"]["old"]!=$contribs_qa_data[$contribution_id]["title"]["sentence_case"])){
        print("<A HREF='fix_title_and_notify.php?contribution_id=".$contribution_id."'>Fix title and notify</A><BR>\n");
    }
    print("<A HREF='fix_contribution_title_case.php?contribution_id=".$contribution_id."&retry=1'>Retry for this contribution</A><BR>\n");
    print("<A HREF='fix_contribution_title_case.php?contribution_id=".$contribution_id."&manual=1'>Fix manually</A><BR>\n");
} // function check_contribution_title_case($contribution_id){

function update_contribution_title($contribution_id,$new_title){
    global $Indico, $contribs_qa_data, $cws_config;
    //Get necessary information to update the contribution
    $req_html =$Indico->request( "/event/{id}/manage/contributions/".$contribution_id."/edit", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true ) );

    $matches=[];
    $matchtxt='/name="person_link_data" value="(.*)">/';
    $returnValue = preg_match_all($matchtxt, $req_html["html"], $matches);
    $person_link_data=html_entity_decode($matches[1][0]);

    $Indico->api->config('header_content_type', 'application/json');


    $post_data = [];
    $post_data["person_link_data"]=$person_link_data;
    $values=[  "description" ];
    foreach($values as $value) {
        if (isset($req_json[$value])) {
            $post_data[$value] = $req_json[$value];
        }
    }
    $post_data["title"]=$new_title;
    $ret=[];
    $req =$Indico->request( "/event/{id}/manage/contributions/".$contribution_id."/edit?standalone=1", 'POST', $post_data, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true ) );
    if (is_array($req)){
        if (array_key_exists("success",$req)){
            //print("Contribution updated successfully<BR/>\n");
            $ret["content"]="Contribution updated successfully<BR/>\n";
            $ret["value"]=true;
            return $ret;
        } else {
            print("Error updating contribution<BR/>\n");
            //var_dump($req);
            //die("Error");
            $ret["content"]="Error updating contribution<BR/>\n";
            $ret["value"]=false;
            return $ret;
        }
    } else {
        print("Error updating contribution<BR/>\n");
        //var_dump($req);
        //die("Error");
        $ret["content"]="Error updating contribution<BR/>\n";
        $ret["value"]=false;
        return $ret;
    }
} //function update_contribution_title($contribution_id,$new_title)

?>