<?php


/*-----------------------------------------
*/
class AI_REQUEST {
	var $ai_headers, $response_code, $result, $error, $cfg;

	function __construct( ) {
        //print("construct");
        //nothing
	}
  function query($question){
    //print("Query: ".$question."<BR/>\n");
    $apiKey = 'kSreZjTOIAcjqDGZyCa9fpJ8DHxscnF6'; // Replace with your actual API key
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
        //print($result);
        //print("<BR/>");
        $response = json_decode($result, true);
        if (isset($response['error'])) {
            echo "API Error: " . $response['error']['message'];
            return false;
        } else {
            //print_r($response);
            //echo $response['choices'][0]['message']['content'];
            return $response['choices'][0]['message']['content'];
        }
    }
    curl_close($ch);
  } //query ai
} //class AI


function add_editor_to_contact_info(){    
$req =$Indico->request( "/event/{id}/manage/settings/contact-info", 'POST', array(
    "contact_emails"=> 	'[{"email":"contact@ipac26.org"},{"email":"scientific.secretariat@ipac26.org"},{"email":"editor@ipac26.org"}]',
 ));
} //add_editor_to_contact_info()

function remove_editor_from_contact_info(){    
$req =$Indico->request( "/event/{id}/manage/settings/contact-info", 'POST', array(
    "contact_emails"=> 	'[{"email":"contact@ipac26.org"},{"email":"scientific.secretariat@ipac26.org"}]',
 ));
}  //remove_editor_from_contact_info()

function load_abstracts(){
    global $Indico;
    global $abstracts,$all_abstracts;
    $_rqst_cfg=[];
    $_rqst_cfg['disable_cache'] =false;
    //$_rqst_cfg['disable_cache'] =true;
    $req_abstracts =$Indico->request( "/event/{id}/manage/abstracts/abstracts.json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true ) );
    $abstracts=[];
    //var_dump(json_decode($req_abstracts,true));
    //var_dump(json_decode($req_abstracts,true)['abstracts']);
    //var_dump($req_abstracts['abstracts']);
    $all_abstracts=json_decode($req_abstracts,true)['abstracts'];
    foreach($all_abstracts as $abstract){
        $abstracts[$abstract["id"]]=$abstract;
    }
}//load_abstracts

//taken from LPR
function load_contributions(){
    global $Indico;
    global $contributions,$contributions_by_abs_id,$contributions_by_fr_id,$all_contributions;
    $_rqst_cfg=[];
    $_rqst_cfg['disable_cache'] =false;
    //$_rqst_cfg['disable_cache'] =true;
    $req_contributions =$Indico->request( "/event/{id}/manage/contributions/contributions.json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true ) );
    $contributions=[];
    $contributions_by_abs_id=[];
    //$all_contributions=json_decode($req_contributions,true);
    $all_contributions=$req_contributions;
    foreach($all_contributions as $contribution){
        $contribution["primary_author_name"]="";
        $contribution["primary_author_email"]="";
        $contribution["primary_author_affiliation"]="";
        $contribution["regions"]="";
        foreach($contribution["persons"] as $person){
            if ($person["author_type"]=="primary"){
                $contribution["primary_author_name"]=$person["full_name"];
                $contribution["primary_author_email"]=$person["email"];
                $contribution["primary_author_affiliation"]=$person["affiliation"];
            }
            if (($person["affiliation_link"])&&($person["affiliation_link"]["country_code"])){
                $the_region=get_region($person["affiliation_link"]["country_code"]);
                if (!(preg_match("#".$the_region."#",$contribution["regions"]))){
                    $contribution["regions"].=$the_region.", ";
                }
            }
        }
        if (strlen($contribution["regions"])>0){
            $contribution["regions"]=substr($contribution["regions"],0,strlen($contribution["regions"])-2);
        }
        $contributions[$contribution["id"]]=$contribution;
        $contributions_by_fr_id[$contribution["friendly_id"]]=$contribution["id"];
        if ($contribution["abstract_id"]){
            $contributions_by_abs_id[$contribution["abstract_id"]]=$contribution["id"];
        }        
    }
}// load_contributions

function send_email_to_participant($subject,$body,$copy_for_sender){
    print("Not working");
    global $Indico;
    print("email\n");
    $post_data=array(
        "sender_address" => "delerue@lal.in2p3.fr",
        "subject" => "test test", 
        "body" => "<p>Dear {first_name},<br><br><br><br>Best regards<br>Nicolas Delerue</p>",
        "bcc_addresses" => array(),
        "copy_for_sender" => true,
        "role_id" => array() ,
        "persons" => array( "EventPerson:36204" ),
        "no_account" => false,
        "not_invited_only" => false
    );
    var_dump($post_data);
    var_dump(json_encode($post_data));
    //$req =$Indico->request( "/event/{id}/manage/api/persons/email/send", 'POST', json_encode($post_data),  array( 'return_data' =>true, 'quiet' => false ) );
    $req =$Indico->request( "/event/95/manage/api/persons/email/send", 'POST', urlencode('{"sender_address":"delerue@lal.in2p3.fr","subject":"Test interface","body":"<p>Dear {first_name},<br><code>{email}</code><br><br><br>Best regards<br>Nicolas Delerue</p>","bcc_addresses":[],"copy_for_sender":true,"role_id":[],"persons":["EventPerson:36204"],"no_account":false,"not_invited_only":false}') ,  array( 'return_data' =>true, 'quiet' => false ) );
    var_dump($req);
}//send_email_to_participant

/*-----------------------------------------

//Judge abstract

$post_data=array (
    'judgment'=> 'accept',
    'accepted_track' => 1000,
    'accepted_contrib_type' => 210,
    'session' => '__None',
    'judgment_comment' => 'From script: accepted as is',
    'send_notifications' => 'y'
);
//Contribution types:
//139 = Contributed orals
//138 = Invited oral
//142 = Poster presentation
//210 invited poster
    
$base_url="/event/{id}/abstracts/12447/judge";
$req =$Indico->request( $base_url , 'POST', $post_data,  array(  'return_data' =>true, 'quiet' =>true));
var_dump($post_data);
print("<BR/>\n");
print("<BR/>\n");

var_dump($req);

print("<BR/>\n");
print("<BR/>\n");

*/
?>