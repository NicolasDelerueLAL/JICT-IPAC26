
<?php
/* by nicolas.delerue@ijclab.in2p3.fr 

Function for Light Peer Review

2026.01.14 - Created by nicolas.delerue@ijclab.in2p3.fr

*/

require_once('../IPAC26/ipac26_tools.php');

$days_for_review=7;
$days_to_accept_invitation=3;


/*** FUNCTIONS  ***/
function format_time($time){
    if (strlen($time)>16){
        return substr(str_replace( "T"," ",$time),0,16);
    } else{
        return "Incorrect format: ".$time;
    }
}


function load_papers($disable_cache){
    global $Indico;
    global $all_papers;
    global $contributions,$contributions_by_abs_id,$contributions_by_fr_id,$all_contributions;
    global $abstracts,$all_abstracts;
    global $cfg;
    global $days_for_review,$days_to_accept_invitation;


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
        $paper["description"]=$contribution["description"];
        $paper["author"]=$contribution["primary_author_name"];
        $paper["affiliation"]=$contribution["primary_author_affiliation"];
        $paper["status"]=$paper["state"]["name"];
        $paper["reviewers"]="";
        $paper["edit_link"]="<A HREF='edit_paper.php?contribution_id=".$paper["contribution_id"]."'>Edit paper</A>";
        $reviewers=get_reviewers_for_contribution($contribution["id"]);
        print("<!---\n"); 
        print(" reviewers \n"); 
        var_dump($reviewers);
        print(" --->\n");
        $paper["overdue"]="";
        if (($reviewers)&&(count($reviewers)>0)){
            $paper["n_reviewers"]=count($reviewers);
            $paper["reviewers"].="<ol>";
            foreach($reviewers as $reviewer){
                $rev_txt="";
                $rev_txt.=ucfirst($reviewer["action"]).": ".$reviewer["name"]." ( ".$reviewer["id"]." ".$reviewer["email"].")";
                if ($reviewer["date"]){
                    $rev_txt.=" on ".substr($reviewer["date"],0,10)." (";
                    $days_ago=round((time()-strtotime($reviewer["date"]))/(60*60*24));
                    $rev_txt.=$days_ago." day";
                    if ($days_ago>1){
                        $rev_txt.="s";
                    }
                    $rev_txt.=" ago) \n";      
                    if (
                        (($reviewer["action"]=="accepted")&&($days_ago>$days_for_review))
                        ||(($reviewer["action"]=="invited")&&($days_ago>$days_to_accept_invitation))
                        )
                        {
                        $paper["overdue"].=$rev_txt;
                        $rev_txt="<b style='color:red;'> Overdue: ".$rev_txt."</b>";
                    } 
                }
                $paper["reviewers"].="<li>".$rev_txt." </li>\n";  
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
    global $all_papers, $contributions, $contributions_by_fr_id;

    $content="";
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
    $contribution=$contributions[$paper["contribution"]["id"]];
    
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
                        "description" => "Abstract",  
                        "submitter" => "Submitter",
                        "author" => "author",  
                        "affiliation" => "affiliation",  
                        "authors" => "authors",
                        "regions" => "Region(s)", 
                        "reviewers" => "Reviewers", 
                        "revisions_history" => "Revision history",
                        "overdue" => "Overdue", 
                    ];




    foreach($fields_to_display as $field_name=>$field_title){
        $content .=$field_title." : ".$paper[$field_name]."<BR/>\n";
    } //for each field

    return $content;
} //function show_paper_info


function show_reviewer_info($person){
    //var_dump($person);
    global $contributions;
    global $abstracts;
    $content="";
    $content .="<ul>\n";
    $content .="<li>Name: ".$person["full_name"]."</li>\n";
    $content .="<li>Affiliation: ".$person["affiliation"]."</li>\n";
    $content .="<li>Email: ".$person["email"]."</li>\n";
    $content .="<li>Registration: ".$person["registered"]."</li>\n";
    $content .="<li>User ID: ".$person["user_id"]."</li>\n";
    $content .="<li>Roles: <ul>\n";
    foreach($person["roles"] as $role){
        $content .="<li>".$role["name"]." \n";
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
    $content .="<li>Contributions: <ul>";
    foreach($person["contributions_id"] as $contrib_id){
        $content .="<li><A HREF='https://indico.jacow.org/event/95/contributions/".$contrib_id."/>".$contributions[$contrib_id]["title"]."</A></li>";
    }
    $content .="</ul></li>\n";
    $content .="<li>Abstracts: <ul>";
    foreach($person["abstracts_id"] as $abstract_id){
        $content .="<li><A HREF='https://indico.jacow.org/event/95/abstracts/".$abstract_id."/>".$abstracts[$abstract_id]["title"]."</A></li>";
    }
    $content .="</ul></li>\n";
    $content .="</ul>\n";
    $activities=reviewer_activity($person["user_id"]);
    if (strlen($activities["content"])>0){
        $content .="<li> Reviewer activity: ".$activities["content"]." </li>\n";
    }


} //function show_reviewer_info

function reviewer_activity($user_id){

    global $cws_config;
    $content="";
    $reviewers_info=file_read_json( $cws_config['global']['data_path']."/reviewers_info.json",true);
    if (!$reviewers_info){
        print("Unable to read reviewers file!");
        $reviewers_info=[];
    }
    if (array_key_exists($user_id,$reviewers_info)){
        $retval["actions"]=[];
        $content .="<ul>";
        //print($person["user_id"]);
        //print("Reviewer activity: \n");
        //var_dump($reviewers_info);
        //print("Reviewer activity: \n");
        //var_dump($reviewers_info[$person["user_id"]]);
        foreach($reviewers_info[$user_id] as $id => $action){
            //print(" $id / $action \n");
            $content .="<li><A HREF='https://indico.jacow.org/event/95/contributions/".$id."/'>Contribution $id</A>: $action </li>\n";
            if (!array_key_exists($action,$retval["actions"])){
                $retval["actions"][$action]=0;
            }
            $retval["actions"][$action]+=1;
        }
        $content .="</ul>\n";
    }
    $retval["content"]=$content;
    return $retval;
} //reviewer_activity($user_id)

function check_authors_list_for_reviewer($contribution,$person_id){
    $content="";
    //print("person id $person_id \n");
    //var_dump(get_participant("id",$person_id)["full_name"]);
    //var_dump($contribution);
    //var_dump($contribution["persons"]);
    $found=false;
    foreach($contribution["persons"] as $person){
        //print("checking ". $person["person_id"]."  vs $person_id\n");
        //var_dump(get_participant("id",$person["person_id"])["full_name"]);
        //var_dump(get_participant("id",$person["person_id"]));
        //var_dump($person);
        if ($person["person_id"]==$person_id){
            $content.="<b>Warning reviewer is also among the paper persons!</b></br>\n";
            $found=true;
            //die("found");
        }
    }
    if (!($found)){
            $content.="Reviewer does not match any of the paper persons.</br>\n";
    }
    $retval=[];
    $retval["content"]=$content;
    $retval["found"]=$found;
    return $retval;
} //check_authors_list_for_reviewer


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

//Reviewer functions
function get_reviewers_for_contribution($contribution_id){
    //print("<!--- get_reviewers_for_contribution $contribution_id --->\n");
    global $Indico;
    global $cws_config;
    $reviewers_info=file_read_json( $cws_config['global']['data_path']."/reviewers_info.json",true);
    if (!$reviewers_info){
        print("<!--- Recretating reviewers_info --->\n");
        $reviewers_info=[];
    }

    $reviewers_from_history=get_paper_reviewers_status($contribution_id);
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
    if (!$returnValue){
        $retval=[];
    } else {
        /*
        var_dump($matches);
        var_dump(array_unique($matches[1]));
        var_dump(array_unique($matches[2]));
        */
        $retval=[];
        for ($iloop=0;$iloop<count(array_unique($matches[1]));$iloop++){
            $retval[]=array("id" => $matches[1][$iloop],"name"=>$matches[2][$iloop]);
        }
    }
    //var_dump($retval);
    foreach($reviewers_from_history["allocation"] as $id => $action){
        if ($action=="accepted"){
            $found=false;
            for ($ival=0;$ival<count($retval);$ival++){
                //print("".$retval[$ival]["id"]."=?=".$id."? \n");
                if ($retval[$ival]["id"]=="".$id){
                    $retval[$ival]["date"]=$reviewers_from_history["allocation_date"][$id];
                    $retval[$ival]["action"]="accepted";
                    $found=true;
                }
            }
            if (!$found){
                print("Warning: reviewer $id has accepted but is not among the reviewers lists! <BR/>\n");
            }
        } else if ($action=="invited"){
            $retval[]=array("id" => "".$id,"name"=>get_full_name_from_userid($id),"email"=>get_email_from_userid($id),"action"=>"invited", "date" => $reviewers_from_history["allocation_date"][$id]);
        }
    }    
    foreach($retval as $rev){
        //checking reviewer roles
        //print("<!--- Reviewrs roles: \n");
        //print("Rev id:".$rev["id"]." \n");
        if (!(array_key_exists($rev["id"],$reviewers_info))){
            $reviewers_info[$rev["id"]]=[];
            //print("Creating entry \n");
        }
        if (!(array_key_exists("action",$rev))){
                print("Warning: reviewer ".$rev["id"]." is among the reviewers lists but not has not accepted in the timeline! <BR/>\n");
            $reviewers_info[$rev["id"]][$contribution_id]="In reviewers list";
        } else {
            $reviewers_info[$rev["id"]][$contribution_id]=$rev["action"];
            //print("Action: ".$rev["action"]."\n");
        }
        //print("--->\n");
    }
    $fwret=file_write_json(  $cws_config['global']['data_path']."/reviewers_info.json",$reviewers_info);
    print($fwret?"<!--- Reviewers file saved successfully --->\n":"Error saving reviewers data\n");
    return $retval;    
} //get_reviewers

function get_paper_reviewers_status($contribution_id){
    //print("<!--- get_paper_reviewers_status $contribution_id --->\n");
    $paper=get_paper($contribution_id,use_session_token:false);
    //print("Timeline <BR/>\n");
    $reviewers=[];
    $reviewers["allocation"]=[];
    $reviewers["allocation_date"]=[];
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
                //print("Matches ".$timeitem["text"]." \n");
                //var_dump($timeitem["text"]);
                $matches=[];
                $matchtxt='#([a-zA-Z]+) reviewer ([0-9]+)#';
                $returnValue = preg_match_all($matchtxt, $timeitem["text"], $matches);
                if (!$returnValue){
                    $matches=[];
                    $matchtxt='#Reviewer ([a-zA-Z]+) ([0-9]+)#';
                    $returnValue = preg_match_all($matchtxt, $timeitem["text"], $matches);
                }
                if ($returnValue){
                    //var_dump($matches);
                    $reviewer_id=$matches[2][0];
                    $action=str_replace("ing","ed",strtolower($matches[1][0]));
                    //print("Action: $action \n");
                    $reviewers["allocation"][$reviewer_id]=$action;
                    $reviewers["allocation_date"][$reviewer_id]=$timeitem["created_dt"];
                    $reviewers[$action][]=$reviewer_id;
                    $reviewers[$action]=array_unique($reviewers[$action]);
                }
            }       
        } //for each timeitem
    } //for each revision
    //var_dump($reviewers);    
    return $reviewers;    
} //get_paper_reviewers_status


function add_reviewer_to_team($email){
    global $Indico;
    //Get current content_reviewers team
    $req =$Indico->request( "/event/{id}/manage/papers/teams/", 'GET', false, array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' => true ) );
    //var_dump($req);

    $matches=[];
    $matchtxt='#name="content_reviewers" value="(.*)"#';
    $returnValue = preg_match_all($matchtxt, $req["html"], $matches);
    $content_reviewers= $matches[1][0]; 
    print("<!--- content_reviewers: $content_reviewers --->\n");   

    $matches=[];
    $matchtxt='#searchToken: "(.*)"#';
    $returnValue = preg_match_all($matchtxt, $req["js"][1], $matches);
    $searchToken=$matches[1][0];
    print("<!--- search Token:".$searchToken." --->");

    $userid=get_userid_from_email($email,$searchToken);
    $content_reviewers=substr($content_reviewers,0,strlen($content_reviewers)-1).',&#34;'. $userid.'&#34;]';
    $content_reviewers=str_replace("&#34;",'"', $content_reviewers); 
    $req =$Indico->request( "/event/{id}/manage/papers/teams/", 'POST', array( 'content_reviewers' => $content_reviewers ) , array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' => true) );
    //check
    $req =$Indico->request( "/event/{id}/manage/papers/teams/", 'GET', false, array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' => true) );
//var_dump($req);
    $matches=[];
    $matchtxt='#name="content_reviewers" value="(.*)"#';
    $returnValue = preg_match_all($matchtxt, $req["html"], $matches);
    print("<!--- ".$matches[1][0]." --->" );
    if (str_contains($matches[1][0],$userid)){
        //user added
        return true;
    } else {
        return false;
    }    
}//add_reviewer_to_team($email,$userid=null)

?>