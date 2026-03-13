
<?php
/* by nicolas.delerue@ijclab.in2p3.fr 

Function for Light Peer Review

2026.01.14 - Created by nicolas.delerue@ijclab.in2p3.fr

*/

require_once('../IPAC26/ipac26_tools.php');


/*** FUNCTIONS  ***/
function format_time($time){
    if (strlen($time)>16){
        return substr(str_replace( "T"," ",$time),0,16);
    } else{
        return "Incorrect format: ".$time;
    }
}

//LPR management 
function check_lpr_rights(){
    $allowed_roles=array("LPR" , "SPB", "ADM");    
    for ($mcloop=0;$mcloop<9;$mcloop++){
        $allowed_roles[]="MC".$mcloop;
    }
    print("<!--- ");
    print("allowed_roles: \n");
    print_r($allowed_roles);
    print("user roles: \n");
    print_r($_SESSION['indico_oauth']['user']['roles']);
    if (empty(array_intersect( $allowed_roles, $_SESSION['indico_oauth']['user']['roles'] ))){
        $allowed=false;
    } else {
        $allowed=true;
    }
    print("\n--->\n");
    if (!$allowed) {
        print("You don't have the right to access this page.<BR/>\n");
        print("You are identified as ".$_SESSION['indico_oauth']['user']['first_name']." ".$_SESSION['indico_oauth']['user']['last_name']."<BR/>\n");
        print("Your roles: ".implode(", ",$_SESSION['indico_oauth']['user']['roles'])."<BR/>\n");
        print("Expected roles: ".implode(", ",$allowed_roles)."<BR/>\n");
        die("End");
    } else {
        print("<!--- ");
        print("Not empty...\n");
        print_r(array_intersect( $allowed_roles, $_SESSION['indico_oauth']['user']['roles'] ));
        print("\n--->\n");
    }
} // check_lpr_rights


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

//Reviewer functions
function get_reviewers_from_contribution($contribution_id){
    global $Indico;
    $_rqst_cfg=[];
    $_rqst_cfg['disable_cache'] =true;
    $post_data=array( "contribution_id" => "".$contribution_id );
    $req_papers =$Indico->request( "/event/{id}/manage/papers/assignment-list/unassign/content_reviewer", 'POST', $post_data, array( 'return_data' =>true, 'quiet' =>true ) );
    if (is_array($req_papers)){
        $all_data=$req_papers['html'];
    } else if (is_string($req_papers)){
        if (json_validate($req_papers)){
            $all_data=json_decode($req_papers,true)['html'];
        } else {
            print("Unable to decode req_papers <BR/>\n");
            var_dump($req_papers);
            die("Unable to decode req_papers ");
        }
    } else {
        print("Unable to decode req_papers <BR/>\n");
        var_dump($req_papers);
        die("Unable to decode req_papers ");
    }
    $returnValue = preg_match_all('#"assign-user-(.*)">\n +(.*)#', $all_data, $matches);
    if ($returnValue){
        /*
        var_dump($matches);
        var_dump(array_unique($matches[1]));
        var_dump(array_unique($matches[2]));
        */
        $retval=[];
        for ($iloop=0;$iloop<count(array_unique($matches[1]));$iloop++){
            $retval[]=array("id" => $matches[1][$iloop],"name"=>$matches[2][$iloop]);
        }
        return $retval;
    } else {
        return false;
    }
} //get_reviewers

function load_papers($disable_cache){
    global $Indico;
    global $all_papers;
    global $contributions,$contributions_by_abs_id,$contributions_by_fr_id,$all_contributions;
    global $abstracts,$all_abstracts;
    global $cfg;

    load_abstracts();

    load_contributions();

    $_rqst_cfg=[];
    $_rqst_cfg['disable_cache'] =$disable_cache;
    $req_papers =$Indico->request( "/event/{id}/manage/papers/assignment-list/export-json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true ) );
    //print("\nContrib\n");
    //var_dump($contributions);
    //die("here");
    //var_dump(json_decode($req_papers,true));
    //print("\nPapers\n");
    //var_dump(json_decode($req_papers,true)['papers']);
    //var_dump(array_keys($req_abstracts['abstracts']));
    //print("\nPapers: ".count($req_papers['papers'])."\n");
    if (is_array($req_papers)){
        $all_papers=$req_papers['papers'];
    } else if (is_string($req_papers)){
        if (json_validate($req_papers)){
            $all_papers=json_decode($req_papers,true)['papers'];
        } else {
            print("Unable to decode req_papers <BR/>\n");
            var_dump($req_papers);
            die("Unable to decode req_papers ");
        }
    } else {
        print("Unable to decode req_papers <BR/>\n");
        var_dump($req_papers);
        die("Unable to decode req_papers ");
    }

    for($ploop=0;$ploop<count($all_papers);$ploop++){
        $paper=$all_papers[$ploop];
        $paper["contribution_id"]=$paper["contribution"]['id'];
        $contribution=$contributions[$paper["contribution"]['id']];
        if (!($contribution)){
            //print("Unable to find contribution".$paper["contribution"]['id']);
        } else {
            if ($contribution["abstract_id"]) {
                //print("abstract_id: ".$contribution["abstract_id"]);
                $paper["abstract_id"]=$contribution['abstract_id'];
                $abstract=$contribution["abstract_id"];
            } else {
                //print("No abstract_id: for ".$contribution["id"]);
                $abstract=false;
                $paper["abstract_id"]=-1;
            }
            if (!($abstract)){
                //print("Unable to find abstract for paper ".$paper["contribution"]['id']);
            }
        } 
        /*
        print("\nContrib\n");
        var_dump(array_keys($contribution));
        var_dump($contribution);
        print("\nPaper\n");
        var_dump(array_keys($paper));
        var_dump($paper);
        die("here");
        */     
        $paper["contribution_friendly_id"]=$paper["contribution"]['friendly_id'];
        $paper["title"]=$paper["contribution"]['title'];
        $paper["code"]=$paper["contribution"]['code'];
        //print("code ".$paper["contribution"]['code']);
        if ($contribution["track"]["code"]){
            $paper["MC"]=substr($contribution["track"]["code"],0,3);
            $paper["track"]=$contribution["track"]["code"];
        } else {
            $paper["MC"]="";
            $paper["track"]="";
        }
        $paper["regions"]=$contribution["regions"];
        $paper["author"]=$contribution["primary_author_name"];
        $paper["affiliation"]=$contribution["primary_author_affiliation"];
        $paper["status"]=$paper["state"]["name"];
        $paper["reviewers"]="";
        $paper["edit_link"]="<A HREF='edit_paper.php?contribution_id=".$paper["contribution_id"]."'>Edit paper</A>";
        $reviewers=get_reviewers_from_contribution($contribution["id"]);
        if ($reviewers){
            $paper["n_reviewers"]=count($reviewers);
            $paper["reviewers"].="<ol>";
            foreach($reviewers as $reviewer){
                $paper["reviewers"].="<li>".$reviewer["name"]."</li>\n";
            }
            $paper["n_reviewers"]=0;
            $paper["reviewers"].="</ol>";
        } else {
            $paper["reviewers"].="No reviewer";
        }
        if (count($paper["revisions"])>0){
            $latest_rev=$paper["revisions"][count($paper["revisions"])-1];
            //print("\nLast rev\n");
            //var_dump($last_rev);
            $paper["latest_revision"]=format_time($latest_rev["submitted_dt"])." Rev #".count($paper["revisions"]);
            if (count($latest_rev["comments"])>0){
                $latest_comment=$latest_rev["comments"][count($latest_rev["comments"])-1];
                //print("\nLast comment\n");
                //var_dump($latest_comment);
                $paper["latest_comment"]=format_time($latest_comment["created_dt"])." - ".$latest_comment["text"];
            } else {
                $latest_comment=false;
                $paper["latest_comment"]="No comment";         
            }
        } else {
            $latest_rev=false;
            $paper["latest_revision"]="No rev.";
            $paper["latest_comment"]="No comment";         
        }
        

        $paper["ids"]="<A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/papers/". $paper['contribution_id']."/' > paper ".$paper["code"]."</A><BR/>\n";
        $paper["ids"].="Abs: "."<A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/abstracts/". $paper['abstract_id']."/' >".$paper['abstract_id']."</A><BR/>\n";
        $paper["ids"].="Contrib: ". "<A HREF='https://indico.jacow.org/event/".$cfg['indico_event_id']."/contributions/". $paper["contribution_id"]."/' >". $paper["contribution_id"]." #".$paper["contribution_friendly_id"]."</A><BR/>\n";
    
        $all_papers[$ploop]=$paper;
    } // for each paper
} //load_papers 


function show_paper_info($contribution_id){
    global $all_papers, $contributions;


    //for paper number
    $paper_val=-1;
    for($ploop=0;$ploop<count($all_papers);$ploop++){    
        $paper=$all_papers[$ploop];    
        if ($paper["contribution"]['id']==$contribution_id){
            $paper_val=$ploop;
        }
    } //looking for the paper
    if ($paper_val==-1){
        die("Unable to find paper with contribution ID ".$contribution_id);
    }
    $paper=$all_papers[$paper_val];
    $contribution=$paper["contribution"];
    $content .="<h2><center>\n".$paper["title"]."</center></h2>\n";

    //authors
    $paper["submitter"]=$contribution["submitter"]["full_name"]."<BR>\n".$contribution["submitter"]["affiliation"];
    $paper["authors"]="<ul>";
    foreach($contributions[$contributions_by_fr_id[$paper["contribution"]["friendly_id"]]]["persons"] as $person){
        $paper["authors"].="<li>";
        $paper["authors"].=$person["full_name"]." - ".$person["author_type"]." - ".$person["affiliation"]."<BR/>";
        $paper["authors"].="</li>";
    }
    $paper["authors"].="</ul>";


    $paper["revisions_history"]="<ol>";
    for ($irev=0;$irev<count($paper["revisions"]);$irev++){
        $the_rev=$paper["revisions"][$irev];
        $paper["revisions_history"].="<li>Revision ".($irev+1)." submitted on ".format_time($the_rev["submitted_dt"])."<BR/>\n";
        if (count($the_rev["comments"])>0){
            $paper["revisions_history"].="Comments:<BR/><ol>\n";
            for ($icom=0;$icom<count($the_rev["comments"]);$icom++){
                $the_comment=$the_rev["comments"][$icom];
                $paper["revisions_history"].="<li>".format_time($the_comment["created_dt"])." - ".$the_comment["text"]."</li>\n";
            }
            $paper["revisions_history"].="</ol>\n";
        }
        $paper["revisions_history"].="</li>";
    } //for irev
    $paper["revisions_history"].="</ol>";

    $content .="<BR/><BR/>\n";
    //Format: ["indico field name" => "display name" ]
    $fields_to_display=[ 
                        "abstract_id"=>"Abstract ID",
                        "code"=>"code",
                        "ids" => "IDs",
                        "MC" => "MC", 
                        "track" => "track", 
                        "round" => "Round", 
                        "status" => "Status" ,
                        "title" => "Title",  
                        "abstract_text" => "Abstract",  
                        "submitter" => "Submitter",
                        "author" => "author",  
                        "affiliation" => "affiliation",  
                        "authors" => "authors",
                        "regions" => "Region(s)", 
                        "reviewers" => "Reviewers", 
                        "revisions_history" => "Revision history",
                    ];




    foreach($fields_to_display as $field_name=>$field_title){
        $content .=$field_title." : ".$paper[$field_name]."<BR/>\n";
    } //for each field

    return $content;
} //function show_paper_info


function show_reviewer_info($person){
    //var_dump($person);
    $content="";
    $content .="<ul>\n";
    $content .="<li>Name: ".$person["first_name"]." ".$person["last_name"]."</li>\n";
    $content .="<li>Affiliation: ".$person["affiliation_raw"]."</li>\n";
    $content .="<li>Email: ".$person["email"]."</li>\n";
    $content .="<li>Registration: ".$person["registered"]."</li>\n";
    $content .="<li>Roles: <ul>\n";
    foreach($person["roles"] as $role){
        $content .="<li>".$role["role"]." - \n";
        $content .="".$role["item"]["id"].": <A HREF='".$role["item"]["url"]."'>".$role["item"]["title"]."</A></li>\n";
    }
    $content .="</ul></li>\n";
    $content .="<li>MCs: ";
    foreach($person["author_MCs"] as $mc){
        $content .=$mc." ";
    }
    $content .="</li>\n";
    $content .="<li>Tracks: ";
    foreach($person["author_tracks"] as $track){
        $content .=$track." ";
    }
    $content .="</li>\n";
    $content .="<li>Abstracts: ";
    foreach($person["abstracts_id"] as $abstract_id){
        $content .=$abstract_id." ";
    }
    $content .="</li>\n";
    $content .="<li>Contributions: ";
    foreach($person["contributions_id"] as $contrib_id){
        $content .=$contrib_id." ";
    }
    $content .="</li>\n";
    $content .="</ul>\n";

    return $content;
} //function show_reviewer_info


function assign_reviewer_to_paper($contribution_id, $person_id){
    global $Indico;
    $content="";
    $content.="Assigning reviewer ".$person_id." to paper ".$contribution_id." <BR/>\n";
    print("<!--- assigning reviewer ".$person_id." to paper ".$contribution_id." <BR/> -->\n");

    $post_data=array( "contribution_id" =>  "".$contribution_id , "user_id" =>  "".$person_id  );
    $req_assign =$Indico->request( "/event/{id}/manage/papers/assignment-list/assign/content_reviewer", 'POST', $post_data, array( 'return_data' =>true, 'quiet' =>true  , 'use_indico_token' => true  ) );
    if ((is_array($req_assign))&&(array_key_exists("flashed_messages",$req_assign))){
            $content.="Reviewer assigned successfully! <BR/>\n";
    } else {
            $content.="Error during assignment <BR/>\n";
    }
    return $content;
}

function get_paper_reviewers_status($contribution_id){
    $paper=get_paper($contribution_id,use_session_token:false);

    //print("Timeline <BR/>\n");
    $reviewers=[];
    $reviewers["invited"]=[];
    $reviewers["uninvited"]=[];
    $reviewers["accepted"]=[];
    $reviewers["declined"]=[];
    $reviewers["assigned"]=[];
    $reviewers["unassigned"]=[];
    foreach($paper["revisions"] as $revision){
        foreach($revision["timeline"] as $timeitem){
            /*
            print("\n");
            print("timeitem");
            print_r($timeitem);
            print("\n");
            print($timeitem["text"]);
            print("\n");
            */
            if (array_key_exists("text",$timeitem)){
                if (str_contains($timeitem["text"],"Inviting reviewer")){
                    $reviewers["invited"][]=trim(str_replace("Inviting reviewer","",$timeitem["text"]));
                } else if (str_contains($timeitem["text"],"Uninviting reviewer")){
                    $reviewers["uninvited"][]=trim(str_replace("Uninviting reviewer","",$timeitem["text"]));
                } else if (str_contains($timeitem["text"],"Reviewer accepted")){
                    $reviewers["accepted"][]=trim(str_replace("Reviewer accepted","",$timeitem["text"]));
                } else if (str_contains($timeitem["text"],"Reviewer declined")){
                    $reviewers["declined"][]=trim(str_replace("Reviewer declined","",$timeitem["text"]));
                } else if (str_contains($timeitem["text"],"Reviewer assigned")){
                    $reviewers["assigned"][]=trim(str_replace("Reviewer assigned","",$timeitem["text"]));
                } else if (str_contains($timeitem["text"],"Reviewer unassigned")){
                    $reviewers["assigned"][]=trim(str_replace("Reviewer unassigned","",$timeitem["text"]));
                } 
            }       
        } //for each timeitem
    } //for each revision
    //var_dump($reviewers);    
    return $reviewers;    
} //get_paper_reviewers_status
?>