<?php

/* Created by Nicolas.Delerue@ijclab.in2p3.fr
2025.12.12 1st version

This page is meant to be included into another one and is used to send an email and record a comment in the timeline.

*/
$code_testing=0;

$Indico->api->cfg['header_content_type'] = 'application/json';

$post_data=array();


if (count($_POST)==0){
    die("No POST arguments passed, exiting\n");
    if ($code_testing==1) {
         echo "No POST arguments passed.\n";   
    }
     // abstract_id=113&vote_value=1&review_id=0&track_id=47
    if (count($_GET)>0){
        if ($code_testing==1) {
            echo "Using GET data\n";   
        }
        $abstract_id= $_GET['abstract_id'];
        $subject= $_GET['subject'];
        $body= $_GET['body'];
        $role= $_GET['role'];
    } 
} else {
    $abstract_id= $_POST['abstract_id'];
    $subject= $_POST['subject'];
    $body= urldecode($_POST['body']);
    $role= $_POST['role'];
}

if ($_POST["submit"]=="Notify"){
    $post_data["abstract_id"]=array( $abstract_id );
    $post_data["body"]=$body;
    $post_data["subject"]=$subject;
    $post_data["bcc_addresses"]=array();
    //$post_data["copy_for_sender"]=false;
    $post_data["copy_for_sender"]=true;
    $post_data["recipient_roles"]=array( $role );
    $post_data["sender_address"]=$_SESSION['indico_oauth']["user"]["email"];

    if ($code_testing==1) {
        print_r($post_data);
    }
    $target_url="/event/{id}/manage/abstracts/api/email-roles/send";
    $req =$Indico->request( $target_url , 'POST', $post_data,  array(  'return_data' =>true, 'quiet' =>false, 'use_session_token' => true));
    //$req =$Indico->request( $target_url , 'POST', json_encode($post_data),  array(  'return_data' =>true, 'quiet' =>true, 'use_session_token' => false));
    if ($code_testing==1) {
        echo "\nPost data:\n";
        var_dump($post_data);
        echo "\nResult:\n";
        var_dump($req);
    }
    if (($req)&&((array_key_exists("success",$req))||(array_key_exists("count",$req)))){
        //nothing
        print("Success sending<BR/>\n");    
    } else {
        print("No success sending<BR/>\n");
        //var_dump($req);
    }
} //submit action

//Recording comment on the abstract

if (!($abstract_id)){
    $abstract_id= $_POST['abstract_id'];
}
if (!($_POST["comment"])){
    if ($_POST["submit"]=="Notify"){
        $comment= "QA Email sent. Reason: ".$_POST['reasons'];
    } else if ($_POST["submit"]=="Ignore"){
        $comment= "QA issue ignored. Reason: ".$_POST['reasons'];
    } 
} else {
    $comment=$_POST["comment"];
}
$req =$Indico->request( "/event/{id}/abstracts/".$abstract_id."/comment", 'POST', array( 'text' => $comment , 'visibility' => "reviewers" ) , array( 'return_data' =>true, 'quiet' =>true, 'use_session_token' => true ) );
if (array_key_exists("success",$req)){
    //nothing
    print("Success recording");    
} else {
    print("No success recording");
    var_dump($req);
}

?>