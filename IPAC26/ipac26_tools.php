<?php


if (!($time_start)){
    $time_start=microtime(true);
    $execution_record="";
}

function show_exec_time($msg=""){
    global $time_start,$execution_record;
    print("<!--- Execution time: ".round((microtime(true)-$time_start),3)." @ $msg --->\n");
    $execution_record.="<!--- ".round((microtime(true)-$time_start),3)." @ $msg --->\n";
} //show_exec_time
function show_load_time(){
    global $time_start;
    print("Page generated in ".round((microtime(true)-$time_start),3)." seconds.<BR/>\n");
} // show_load_time
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
    show_exec_time("load_abstracts start disable_cache $disable_cache");
    //$_rqst_cfg=[];
    //$_rqst_cfg['disable_cache'] =$disable_cache;
    //$_rqst_cfg['disable_cache'] =true;
    $req_abstracts =$Indico->request( "/event/{id}/manage/abstracts/abstracts.json", 'GET', false, array( 'return_data' =>true, 'quiet' =>false ,  'disable_cache' => $disable_cache,  'cache_time' => 60*60*24*7 ) );
    show_exec_time("load_abstracts after req");
    $abstracts=[];
    //var_dump(json_decode($req_abstracts,true));
    //var_dump(json_decode($req_abstracts,true)['abstracts']);
    //var_dump($req_abstracts['abstracts']);
    //$all_abstracts=json_decode($req_abstracts,true)['abstracts'];
    if (is_array($req_abstracts)){
        $all_abstracts=$req_abstracts['abstracts'];
    } else if (json_validate($req_abstracts)){
        $all_abstracts=json_decode($req_abstracts,true)['abstracts'];
    } else {
        print("Unable to decode req_abstracts <BR/>\n");
        var_dump($req_abstracts);
        die("Unable to decode req_abstracts ");
    }
    show_exec_time("load_abstracts bf loop");
    foreach($all_abstracts as $abstract){
        $abstracts[$abstract["id"]]=$abstract;
    }
    show_exec_time("load_abstracts_end");
}//load_abstracts

function load_contributions($disable_contributions_cache=false,$query_affiliations=false){
    global $Indico;
    global $contributions,$contributions_by_abs_id,$contributions_by_fr_id,$all_contributions;
    global $abstracts;
    show_exec_time("load_contributions_start");
    $_rqst_cfg=[];
    $_rqst_cfg['disable_cache'] =$disable_contributions_cache;
    if ($disable_contributions_cache){
        $cache_time=1;
    } else {
        $cache_time=60*60*24*7;
    }
    $req_contributions =$Indico->request( "/event/{id}/manage/contributions/contributions.json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' => $disable_contributions_cache, 'cache_time' => $cache_time)  );
    $contributions=[];
    $contributions_by_abs_id=[];
    //$all_contributions=json_decode($req_contributions,true);
    $all_contributions=$req_contributions;
    foreach($all_contributions as $contribution){
        if (!$contribution["track"]["code"]){
            $contribution["MC"]="XXX";
            $contribution["track_name"]="";
            print("<!--- Error determining track code for contribution ID: ".$contribution["id"]." --->\n");
        } else {
            $contribution["MC"]=substr($contribution["track"]["code"],0,3);
            $contribution["track_name"]=$contribution["track"]["title"];
        }
        //$contribution["track_name"]=$contribution["track"]["code"]." - ".$contribution["track"]["title"];

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
            //affiliation handling
            $person=fix_affiliation($person,$query_affiliations);
            $contribution["persons"][$ipers]=$person;                    


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
    show_exec_time("load_contributions_end");
}// load_contributions


function fix_affiliation($person,$query_affiliations=true){  
    global $Indico,$cws_config;
    global $affiliation_fixes;
    //show_exec_time("fix_affiliation start");
    if (!($affiliation_fixes)){
        global $cws_config;
        $affiliation_fixes=file_read_json($cws_config['global']['data_path']."/affiliation_fixes.json",true);
    }
    if (!($affiliation_fixes)){
        print("Unable to read ".$cws_config['global']['data_path']."/affiliation_fixes.json");
        $affiliation_fixes=[];
    }

    if (
        (!($person["affiliation_link"]))
        ||($person["affiliation_link"]=="NULL")
        ||($person["affiliation_link"]==NULL)
        ||(str_contains($person["affiliation"],";"))){
            //print("<!--- Querying affiliation for person: ".$person["full_name"]." affiliation: ".$person["affiliation"]." --->\n");
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
                //$Indico->api->config('disable_cache', false);
                //$Indico->api->config('cache_time', 60*60*24*7);
                if ($query_affiliations){
                    $req_aff =$Indico->request( "/api/affiliations/?q=".urlencode($person_affiliation), 'GET', false, array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' => false, 'cache_time' => 60*60*24*7*2) );
                    if (count($req_aff)>1){
                        for ($iaff=0;$iaff<count($req_aff);$iaff++){
                            if ($req_aff[$iaff]["name"]==$person_affiliation){
                                $person[$link_name]=$req_aff[$iaff];   
                            }                     
                        }
                    } else if (count($req_aff)==1){
                        $person[$link_name]=$req_aff[0];   
                    }
                } else {
                    $person[$link_name]=["name"=>$person_affiliation , "id"=>null, "country_code"=>null, "country_name"=>null];
                }
            } //for each affiliation
    } // if no affiliation link or multiple affiliations
    //fix incorrect affiliations

    $link_name="affiliation_link";
    $jaff=0;
    while(array_key_exists($link_name, $person)){
        //print("<!--- Checking affiliation fix for person: ".$person["full_name"]." affiliation: ".$person[$link_name]["name"]." ".$person[$link_name]["id"]." --->\n");
        if (($person[$link_name])&&($person[$link_name]["id"])&&(!$person[$link_name]["id"]==null)&&(array_key_exists($person[$link_name]["id"],$affiliation_fixes))){
            $iwhile=0;
            while ((array_key_exists($person[$link_name]["id"],$affiliation_fixes))&&($iwhile<5)){
                if (array_key_exists("Accept",$affiliation_fixes[$person[$link_name]["id"]])){
                    if ($affiliation_fixes[$person[$link_name]["id"]]["Accept"]==1){
                        break;
                    }
                }
                $person[$link_name]=$affiliation_fixes[$person[$link_name]["id"]];
                //print("<!--- Affiliation fix applied for person: ".$person["full_name"]." affiliation: ".$person[$link_name]["name"]." --->\n");
                $iwhile+=1;
            } // while affiliation fix exists
        } // if affiliation id exists and is not null
        $jaff+=1;
        if ($jaff==0){
            $link_name="affiliation_link";
        } else {
            $link_name="affiliation_link_".($jaff+1);
        }
    } //while affiliation link exists

    //show_exec_time("fix_affiliation end");
    return $person;

} //fix_affiliation

function send_email_to_eventperson($subject,$body,$eventPerson,$sender_email,$copy_for_sender,$bcc_address_array=array(),$use_session_token=true,$use_indico_token=false){
    show_exec_time("send_email_to_eventperson start");
    //print("<!--- send_email_to_eventperson \n");
    /*
    print("\n<!--- ");
    print("subject: ".$subject."\n");
    print("eventPerson ".$eventPerson."\n");
    print("sender_email: ". $sender_email."\n");
    print("copy_for_sender: ".$copy_for_sender."\n");
    print("bcc: \n");
    print_r($bcc_address_array);
    print("\n");
    print("use_session_token: ". $use_session_token."\n");
    print("use_indico_token: ". $use_indico_token."\n");
    print ("--->\n");
    */
    global $Indico;

    $post_data=array(
        "sender_address" => $sender_email,
        "subject" => $subject, 
        "body" => $body,
        "bcc_addresses" => $bcc_address_array ,
        "copy_for_sender" => $copy_for_sender ,
        "role_id" => array() ,
        "persons" => array( $eventPerson ),
        "no_account" => false,
        "not_invited_only" => false, 
    );
    //var_dump($post_data);
    $Indico->api->config('header_content_type', 'application/json');

    $req =$Indico->request( "/event/{id}/manage/api/persons/email/send", 'POST', $post_data,  array( 'return_data' =>true, 'quiet' => false , 'use_session_token' => $use_session_token, 'use_indico_token' => $use_indico_token ) );
    //print("<BR/>\n");
    //print("Send email:<BR/>\n");
    //var_dump($req);
    if ((is_array($req))&&(array_key_exists("count",$req))){
        print("<!--- Message(s) sent: ".$req["count"]." --->\n");
        return $req["count"];
    } else {
        print("<!--- Message(s) NOT sent: ".$req["count"]." --->\n");
        print("<!--- \n");
        print("Req: \n");
        var_dump($req);
        print(" --->\n");
        return false;
    }
    show_exec_time("send_email_to_eventperson end");
}//send_email_to_eventperson



function send_email_file_to_eventperson($file,$eventPerson,$sender_email,$copy_for_sender,$contribution=null,$bcc_address_array=array(),$use_session_token=true,$use_indico_token=false){
    global $user;
    global $cws_config;
    //print("<!--- send_email_file_to_eventperson $file --->\n");
    show_exec_time("send_email_file_to_eventperson start");
    if ($contribution){
        $filename=$cws_config['global']['messages_path']."/".$file;
        if (!(file_exists($filename))) die("File for message does not exist: ".$filename);
        $message=file_get_contents($filename);
        $matches=[];
        $matchtxt='/##([a-zA-Z=@_:]+)##/';
        $returnValue = preg_match_all($matchtxt, $message, $matches);
        foreach($matches[1] as $thematch){
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
            } else if (substr($thematch,0,1)=="@"){
                if (!$user){
                    die("User is not defined");
                }
                if (str_contains($thematch,":")){
                    $fields=explode(":",substr($thematch,1));
                    //var_dump($fields);
                    if (count($fields)==2){
                        $newvalue=$user[$fields[0]][$fields[1]];
                    } else if (count($fields)==3){
                        $newvalue=$user[$fields[0]][$fields[1]][$fields[2]];
                    }
                } else {                    
                    $newvalue=$user[substr($thematch,1)];
                }
                $message=str_replace("##".$thematch."##",$newvalue,$message);
            }
        }

        $subject=substr($message,0,strpos($message,"\n"));
        //print("Subject: $subject <BR/>\n");
        $body=str_replace("\n","<BR/>\n",substr($message,strpos($message,"\n")+1));
        return send_email_to_eventperson($subject,$body,$eventPerson,$sender_email,$copy_for_sender,$bcc_address_array,$use_session_token,$use_indico_token);
    } else {
        print("Contribution is null!<BR/>\n");
        return false;
    }
}

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
    global $user;
    global $cws_config;
    if ($contribution){
        $filename=$cws_config['global']['messages_path']."/".$file;
        if (!(file_exists($filename))) die("File for message does not exist: ".$filename);
        $message=file_get_contents($filename);
        $matches=[];
        $matchtxt='/##([a-zA-Z=@_:]+)##/';
        $returnValue = preg_match_all($matchtxt, $message, $matches);
        foreach($matches[1] as $thematch){
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
            } else if (substr($thematch,0,1)=="@"){
                if (!$user){
                    die("User is not defined");
                }
                if (str_contains($thematch,":")){
                    $fields=explode(":",substr($thematch,1));
                    //var_dump($fields);
                    if (count($fields)==2){
                        $newvalue=$user[$fields[0]][$fields[1]];
                    } else if (count($fields)==3){
                        $newvalue=$user[$fields[0]][$fields[1]][$fields[2]];
                    }
                } else {                    
                    $newvalue=$user[substr($thematch,1)];
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

function get_contribution($contribution_id,$use_session_token=true){
    global $Indico;
    //global $cws_config;
    show_exec_time("get_contribution start");

    $req =$Indico->request( "/event/{id}/contributions/".$contribution_id.".json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true , 'use_session_token' => $use_session_token ) );
    if (!$req["title"]){
        print("Getting contribution ".$contribution_id." failed.");
        return false;
    }
    show_exec_time("get_contribution end");
    return $req;
    //var_dump($req);
    //print("Title: ".$req["title"]."<BR/>\n");
} //get_contribution

function get_paper($contribution_id,$use_session_token=true,$disable_cache=false){
    global $Indico;
    //print("<!--- get_paper $contribution_id --->\n");
    show_exec_time("get_paper start");
    //global $cws_config;
    $req =$Indico->request( "/event/{id}/papers/api/".$contribution_id, 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' => $disable_cache , 'use_session_token' => $use_session_token ) );
    //var_dump($req);
    //print("Title: ".$req["contribution"]["title"]."<BR/>\n");
    if (!$req["contribution"]["title"]){
        print("Getting paper for contribution ".$contribution_id." failed. (in get contribution)");
        return false;
    }
    show_exec_time("get_paper end");
    return $req;
} //get_contribution


function check_contribution_title_case($contribution_id,$retry=false){
    global $Indico;
    global $contribs_qa_data;
    global $cws_config;

    $req =$Indico->request( "/event/{id}/contributions/".$contribution_id.".json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true , 'use_session_token' => true ) );

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
            //print($result);
            //print("<BR/>\n");
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
        //$question='This is the title of a scientific publication: '.$req["title"].'. I will want to convert it into sentence case and upper case. Could you return an array with all the words it contains whose first letter should always be capitalized (such as proper nouns) and on a second line an array with all the words it contains whose case should never be changed (acronyms, units,...) and a third line with an array of all other words whose case should be adapted to the context? Please answer with the following format: Proper nouns: [list of nouns separated by semicolons];\n NoCaseChange: [list of other words separated by semicolons]\nNoCaseChange: [list of words whose case should not be changed separated by semicolons]\nOtherWords: [list of other words separated by semicolons].';
        //$question='This is the title of a scientific publication: "'.$req["title"].'". Could you return an array with all the proper nouns it contains as well as other words whose first letter should always be capitalized? Please answer with the following format: Proper nouns: [list of nouns separated by semicolons];\n';
        //$question='This is the title of a scientific publication: "'.$req["title"].'". Could you rewrite this title in sentence case?\n';
        //$question='This is the title of a scientific publication: "'.$req["title"].'". Could you rewrite this title in upper case?\n';
        $question='This is the title of a scientific publication: "'.$req["title"].'". Does it contain any proper nouns? If yes, please answer with a list of these proper nouns separated by semicolons, on the first line of your answer.\n';
        $question='This is the title of a scientific publication: "'.$req["title"].'". Does it contain any units or acronyms? If yes, please answer with a list of these proper nouns separated by semicolons, on the first line of your answer.\n';
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
    $req_html =$Indico->request( "/event/{id}/manage/contributions/".$contribution_id."/edit", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true , 'use_session_token' => true ) );

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
    $req =$Indico->request( "/event/{id}/manage/contributions/".$contribution_id."/edit?standalone=1", 'POST', $post_data, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true , 'use_session_token' => true ) );
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


function assign_contribution_code($contribution_id,$code){
    global $Indico, $contribs_qa_data, $cws_config;
    //Get necessary information to update the contribution
    $req_html =$Indico->request( "/event/{id}/manage/contributions/".$contribution_id."/edit", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true , 'use_session_token' => true ) );

    $matches=[];
    $matchtxt='/name="person_link_data" value="(.*)">/';
    $returnValue = preg_match_all($matchtxt, $req_html["html"], $matches);
    $person_link_data=html_entity_decode($matches[1][0]);

    $Indico->api->config('header_content_type', 'application/json');


    $post_data = [];
    $post_data["person_link_data"]=$person_link_data;
    $values=[  "title", "description" ];
    foreach($values as $value) {
        if (isset($req_json[$value])) {
            $post_data[$value] = $req_json[$value];
        }
    }
    $post_data["code"]=$code;
    $ret=[];
    $req =$Indico->request( "/event/{id}/manage/contributions/".$contribution_id."/edit?standalone=1", 'POST', $post_data, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true , 'use_session_token' => true ) );
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
} //function assign_contribution_code($contribution_id,$code)

function update_contribution_field($contribution_id,$update_field,$update_value){
    global $Indico, $contribs_qa_data, $cws_config;
    $req_html =$Indico->request( "/event/{id}/manage/contributions/".$contribution_id."/edit", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true , 'use_session_token' => true ) );

    $matches=[];
    $matchtxt='/name="person_link_data" value="(.*)">/';
    $returnValue = preg_match_all($matchtxt, $req_html["html"], $matches);
    $person_link_data=html_entity_decode($matches[1][0]);
    $Indico->api->config('header_content_type', 'application/json');
    $post_data = [];
    $post_data["person_link_data"]=$person_link_data;
    $values=[  "title", "description" ];
    foreach($values as $value) {
        if (isset($req_json[$value])) {
            $post_data[$value] = $req_json[$value];
        }
    }
    $post_data[$update_field]=$update_value;
    $ret=[];
    $req =$Indico->request( "/event/{id}/manage/contributions/".$contribution_id."/edit?standalone=1", 'POST', $post_data, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true , 'use_session_token' => true ) );
    if (is_array($req)){
        if (array_key_exists("success",$req)){
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
} //function update_contribution_field



function comment_paper($contribution_id,$comment,$use_session_token=true,$use_indico_token=false){
    global $Indico;
    show_exec_time("comment_paper start");
    $post_data=array(
        "comment" => $comment,
        "visibility" => "judges" , 
    );
    print("\ncommenting: $contribution_id\n");
    if ($use_indico_token){
        $use_session_token=false;
    }
    $req =$Indico->request( "/event/{id}/papers/api/".$contribution_id."/comment", 'POST', $post_data , array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true , 'use_session_token' => $use_session_token , 'use_indico_token' => $use_indico_token ) );
    //print("\n<!--- comment:\n");
    //var_dump($req);
    //print(" --->");
    show_exec_time("comment_paper end");
}//comment_paper

function get_search_token(){
    global $Indico;
    $contribution_id="14000";
    $req =$Indico->request( "/event/{id}/manage/contributions/14000/edit", 'GET', false, array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' => true ) );
    if (!(array_key_exists("js",$req))){
        print("Check that contribution_id $contribution_id exists");
    }
    //https://indico.jacow.org/event/95/manage/contributions/14565/edit
    $matches=[];
    $matchtxt='#searchToken: "(.*)"#';
    $returnValue = preg_match_all($matchtxt, $req["js"][2], $matches);
    $searchToken=$matches[1][0];
    print("<!--- search Token:".$searchToken." --->");
    return $searchToken;
} //function get_search_token()


function get_userid_from_email($email,$token=false){
    global $Indico;
    show_exec_time("get_userid_from_email start");
    if (!$token){
        $token=get_search_token();
    }
    $req =$Indico->request( "/user/search/?email=".$email."&favorites_first=true&token=".$token, 'GET', array( ) , array( 'return_data' =>true, 'quiet' =>true ) );
    if ($req["total"]==0){
        print("<!--- No users found. Ask the user to create an indico account. --->\n");
        return false;
    } else if ($req["total"]>1){
        print("<!--- Too many users found. Please check. --->\n");
        return false;
    } else {
        return $req["users"][0]["identifier"];
    }
} //function get_userid_from_email($email)


function update_participants(){
    global $Indico;
    global $contributions,$contributions_by_abs_id,$contributions_by_fr_id;
    show_exec_time("update_participants start");

    if (!($contributions_by_abs_id)){
        die("Contributions must be loaded to update participants!");
    }

    print("<!--- Updating participants list --->");
    $req_persons =$Indico->request( "/event/{id}/manage/persons/", 'GET', false, array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' => false ) );
    $matchtxt='#<tr id="person-([0-9]+)"#';
    $returnValue = preg_match_all($matchtxt, $req_persons , $matches, PREG_OFFSET_CAPTURE);
    $all_persons=[];
    print("<!--- ". count($matches[0])." participants found --->\n");
    for ($icount=0;$icount<count($matches[0]);$icount++){
        $person=[];
        $person["id"]=$matches[1][$icount][0];
        $person["user_id"]="";
        //print("Person: ".$matches[1][$icount][0]."\n");
        //print("Person offs: ".$matches[1][$icount][1]."\n");

        if ($icount<(count($matches[0])-1)){
            $person_txt=substr($req_persons,$matches[0][$icount][1],$matches[0][$icount+1][1]-$matches[0][$icount][1]);
        } else {
            $person_txt=substr($req_persons,$matches[0][$icount][1]);
        }
        
        //print("Person ".$person["id"].": \n");  
        
        //if (($icount>100)&&($icount<103)&&(1==0)){
        //    var_dump($person_txt);
        //}

        $submatchlist=array(
            "roles" => 'data-person-roles="(.*)"',
            "registered" => 'i class="icon-ticket js-show-regforms [a-zA-Z]*" data-title="\s*(.*)',
            "name_lowercase" => '<td class="i-table name-column" data-searchable="(.*)">',
            "full_name" => '<td class="i-table name-column" data-searchable=".*">\s*(.*)',
            "email"=>'<td class="i-table email-column">\s*(.*)',
            "affiliation"=>'<td class="i-table affiliation-column">\s.*<span>([^/]+)</span>',
            "editablePerson"=> 'setupEditEventPerson\(\s.*\s(.*)'
        );
        foreach( $submatchlist as $key => $txt){
            $submatches=[];
            $submatchtxt='#'.$txt.'#';
            $returnValue = preg_match($submatchtxt, $person_txt , $submatches);
            /*        
            if (!(count($submatches)==2)){
                print(" Warning: data matches ".count($submatches)." for $key \n");
                var_dump($submatches);
            }
            */
            if (count($submatches)>0){
                $person[$key]=$submatches[1];
            } else {
                $person[$key]="";
            }
            //print($key." => ".$person[$key]." \n");
        }
        //roles
        $person["roles"]=json_decode(str_replace('&#34;', '"', $person["roles"]),true);
        //print("Roles \n");
        //var_dump($person["roles"]);
        //die("here");
        $person["roles_txt"]="";
        foreach($person["roles"] as $role){
            if ((is_array($role))&&(array_key_exists("name",$role))){
                $person["roles_txt"].=$role["name"].",";
            }
        }
        if (array_key_exists("author",$person["roles"])){
            $person["author_MCs"]=[];
            $person["author_tracks"]=[];
            $person["abstracts_id"]=[];
            $person["contributions_id"]=[];
            foreach($person["roles"]["author"]["elements"] as $elem){
                //var_dump($elem);
                if (str_contains($elem["url"],"/contributions/")){
                    $contrib_fid=str_replace("/event/95/manage/contributions/?selected=","",$elem["url"]);
                    $contrib_id=$contributions_by_fr_id[$contrib_fid];
                } else if (str_contains($elem["url"],"/event/95/manage/abstracts/")){
                    $abstract_id=str_replace("/","",str_replace("/event/95/manage/abstracts/","",$elem["url"])); 
                    if (array_key_exists($abstract_id,$contributions_by_abs_id)){
                        $contrib_id=$contributions_by_abs_id[$abstract_id];
                    }
                    //print(" abstract_id : $abstract_id \n");
                    //print(" contrib_id : $contrib_id \n");
                } else {
                    print("elem is not contrib \n");
                    var_dump($elem);
                    $contrib_id=false;   
                }
                if ($contrib_id){
                    $contribution=$contributions[$contrib_id];
                    if ($contribution){
                        //var_dump($contribution);
                        $person["author_MCs"][]=$contribution["MC"];
                        if ((array_key_exists("track",$contribution))&&($contribution["track"])&&(array_key_exists("code",$contribution["track"]))){
                            $person["author_tracks"][]=$contribution["track"]["code"];
                        }
                        $person["abstracts_id"][]=$contribution["abstract_id"];
                        $person["contributions_id"][]=$contribution["id"];
                        //print("Contrib: ".$contribution["id"]."\n");
                    } else {
                        print("No contribution with id ".$contrib_id."\n");
                    }
                } else {
                    //print("Contrib id not found...\n");                    
                }

            }
            $person["author_MCs"]=array_unique($person["author_MCs"]);
            $person["author_tracks"]=array_unique($person["author_tracks"]);
            $person["author_MCs_txt"]="";
            $person["author_tracks_txt"]="";
            foreach($person["author_MCs"] as $mc){
                $person["author_MCs_txt"].=$mc.",";
            }
            foreach($person["author_tracks"] as $mc){
                $person["author_tracks_txt"].=$mc.",";
            }
        }

        if (strlen($person["editablePerson"])>10){
            $person["details"]=json_decode(substr($person["editablePerson"],0,strlen($person["editablePerson"])-1),true);
            //var_dump($person["details"]);
            if (array_key_exists("user_identifier",$person["details"])){
                $matchesSub=[];
                if (preg_match("#User:([0-9]+):#",$person["details"]["user_identifier"],$matchesSub)){
                    $person["user_id"]=$matchesSub[1];
                }
            }
            if (array_key_exists("affiliation",$person["details"])){
                $person["affiliation"]=$person["details"]["affiliation"];
                $person["affiliation_name"]=$person["details"]["affiliation"];
            }
            if ((array_key_exists("affiliation_meta",$person["details"]))&&($person["details"]["affiliation_meta"])){
                $person["affiliation_country"]=$person["details"]["affiliation_meta"]["country_name"];
                $person["affiliation_country_code"]=$person["details"]["affiliation_meta"]["country_code"];
                if ($person["details"]["affiliation_meta"]["country_code"]){
                    $person["affiliation_region"]=get_region($person["details"]["affiliation_meta"]["country_code"]);
                } else {
                    $person["affiliation_region"]="Unknown";
                }
            } else {
                $person["affiliation_country"]="Unknown";
                $person["affiliation_region"]="Unknown";
            }
            
        } else {
            $person["editable"]=false;
        }
        //registration    
        if (preg_match("#The person has not registered yet#",$person["registered"],$matchesSub)){
            $person["registered_value"]=0;
            $person["registered"]=str_replace("The person has not registered yet","Not registered",$person["registered"]);
        } else if (preg_match("#The person has registered in:#",$person["registered"],$matchesSub)){
            $person["registered"]=str_replace("The person has registered in:","Is registered",$person["registered"]);            
            $person["registered_value"]=1;
        } else {
            print("Registration not understood: ".$person["registered"]);
            $person["registered_value"]=-9;
        }

        //var_dump($person);
        //print("===============\n\n");
        $all_persons[]=$person;
        
        /*
        if ($icount>200){
            die(" $icount reached");
        }
        */
        
    }
    //print(count($matches[0]));
    //var_dump($matches);

    global $cws_config;
    $fwret=file_write_json( $cws_config['global']['data_path']."/all_participants.json",$all_persons);
    //echo "file write $fwret \n"; 
    show_exec_time("update_participants end");

} //function update_participants()

function get_participants($force_update=false){
    global $cws_config;
    global $participants;
    global $contributions_by_abs_id;
    show_exec_time("get_participants start");

    if (!($participants)){
        $fname=$cws_config['global']['data_path']."/all_participants.json";
        if ($contributions_by_abs_id){
            //Contributions must be loaded to update participants!
            print("<!--- Participants file age $fname ".(time() - filemtime($fname))." --->");
            if (($force_update)||((time() - filemtime($fname))>(60*60*24))){
                update_participants();
            }
        }
        $participants=file_read_json( $fname,true);
    }
    show_exec_time("get_participants end");
    return $participants;
} //function get_participants()


function get_person($user){
    global $cws_config;
    $all_persons=file_read_json( $cws_config['global']['data_path']."/all_participants.json",true);
    if (!$all_persons){
        die("Unable to read all_persons file.");
    }
    foreach($all_persons as $person){
        if ($person["email"]==$user["email"]){
            return $person;
            break;
        }
    }
    return false;
} //get_person


function get_participant($key,$value){
    show_exec_time("get_participant start");
    $participants=get_participants();
    $idx=array_search($value, array_column($participants, $key)); 
    show_exec_time("get_participant idx found");
    if ($idx){
        return $participants[$idx];
    } else {
        return false;
    }
}//get_participant

function get_full_name_from_userid($userid){
    return get_participant("user_id",$userid)["full_name"]; 
}//get_full_name_from_userid

function get_email_from_userid($userid){
    return get_participant("user_id",$userid)["email"]; 
}//get_full_name_from_userid

function get_full_name_from_eventid($eventid){
    return get_participant("id",$eventid)["full_name"]; 
}//get_full_name_from_userid

//LPR management 
function check_lpr_rights(){
    $allowed_roles=array("LPR" , "SPB", "ADM");    
    for ($mcloop=0;$mcloop<9;$mcloop++){
        $allowed_roles[]="MC".$mcloop;
    }
    
    //print("<!--- ");
    //print("allowed_roles: \n");
    //print_r($allowed_roles);
    //print("user roles: \n");
    //print_r($_SESSION['indico_oauth']['user']['roles']);
    //print("\n--->\n");
    if (empty(array_intersect( $allowed_roles, $_SESSION['indico_oauth']['user']['roles'] ))){
        $allowed=false;
    } else {
        $allowed=true;
    }
    if (!$allowed) {
        print("You don't have the right to access this page.<BR/>\n");
        print("You are identified as ".$_SESSION['indico_oauth']['user']['first_name']." ".$_SESSION['indico_oauth']['user']['last_name']."<BR/>\n");
        print("Your roles: ".implode(", ",$_SESSION['indico_oauth']['user']['roles'])."<BR/>\n");
        print("Expected roles: ".implode(", ",$allowed_roles)."<BR/>\n");
        die("End");
    } else {
        //print("<!--- ");
        //print("Not empty...\n");
        //print_r(array_intersect( $allowed_roles, $_SESSION['indico_oauth']['user']['roles'] ));
        //print("\n--->\n");
    }
} // check_lpr_rights

?>