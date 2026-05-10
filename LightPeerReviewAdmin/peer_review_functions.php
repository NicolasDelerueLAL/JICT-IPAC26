
<?php
/* by nicolas.delerue@ijclab.in2p3.fr 

Function for Light Peer Review

2026.01.14 - Created by nicolas.delerue@ijclab.in2p3.fr

*/

require_once('../IPAC26/ipac26_tools.php');

$days_for_review=7;
$days_to_accept_invitation=3;
$days_after_reminder=1;

/*** FUNCTIONS  ***/
function format_time($time){
    if (strlen($time)>16){
        return substr(str_replace( "T"," ",$time),0,16);
    } else{
        return "Incorrect format: ".$time;
    }
}


function load_papers($disable_cache,$disable_abstracts_cache=false,$recheck_probability_percent=10){
    global $Indico;
    global $all_papers;
    global $overdue_papers,$reviewed_papers,$accepted_papers,$rejected_papers;
    global $contributions,$contributions_by_abs_id,$contributions_by_fr_id,$all_contributions;
    global $abstracts,$all_abstracts;
    global $cfg;
    global $days_for_review,$days_to_accept_invitation,$days_after_reminder;

    show_exec_time("load_paper start");

    load_abstracts($disable_abstracts_cache);

    load_contributions($disable_cache);

    //$_rqst_cfg=[];
    //$_rqst_cfg['disable_cache'] =$disable_cache;
    $req_papers =$Indico->request( "/event/{id}/manage/papers/assignment-list/export-json", 'GET', false, array( 'return_data' =>true, 'quiet' =>false , 'disable_cache' => $disable_cache) );
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
    show_exec_time("load_paper bf loop");
    $overdue_papers=0;
    $reviewed_papers=0;
    $accepted_papers=0;
    $rejected_papers=0;

    for($ploop=0;$ploop<count($all_papers);$ploop++){
        show_exec_time("load_paper loop $ploop");
        $paper=$all_papers[$ploop];
        $paper["round"]=1;
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
        $paper["status"]="LPR: ".$paper["state"]["name"];
        $paper["reviewers"]="";
        $paper["proposed_judgement"]=false;
        $paper["edit_link"]="<A HREF='edit_paper.php?contribution_id=".$paper["contribution_id"]."'>Edit paper</A>";
        //get editing status

        //$reqEdit =$Indico->request( "/event/{id}/api/contributions/".$paper["contribution_id"]."/editing/paper", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>false , 'use_session_token' => true ) );
        
        if ((rand(0,100)<$recheck_probability_percent)){
            $reqEdit =$Indico->request( "/event/{id}/api/contributions/".$paper["contribution_id"]."/editing/paper", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>false , 'cache_time'=> 10 , 'use_session_token' => true ) );
        } else {
            $reqEdit =$Indico->request( "/event/{id}/api/contributions/".$paper["contribution_id"]."/editing/paper", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>false , 'cache_time'=> 60*60 , 'use_session_token' => true ) );
        }
        
        
        if ($reqEdit){
            $paper["editing_status"]="Editing: ".$reqEdit["state"]["name"]." ";
            if ($reqEdit["editor"]){
                $paper["editing_status"].=$reqEdit["editor"]["full_name"];
            } else {
                $paper["editing_status"].="No editor yet";
            }
        } else {
            $paper["editing_status"]="Paper not yet ready for editing";
        } //recheck paper editing status
        $paper["status"].="<BR/>\n".$paper["editing_status"];
        
        
        $reviewers=get_reviewers_for_contribution($contribution["id"],recheck_probability_percent:$recheck_probability_percent);
        //print("<!---\n"); print("Reviewers for contribution ".$contribution["id"]." \n"); var_dump($reviewers); print(" --->\n");
        $paper["overdue"]="";
        if (($reviewers)&&(count($reviewers)>0)){            
            $paper["n_reviewers"]=count($reviewers);
            $paper["reviewers"].="<ol>";
            foreach($reviewers as $reviewer){
                $reminder=false;
                $rev_txt="";
                if (array_key_exists("email",$reviewer)){
                    $rev_txt.=ucfirst($reviewer["action"]).": ".$reviewer["name"]." ( ".$reviewer["id"]." - ".$reviewer["email"]." )";
                } else {
                    $rev_txt.=ucfirst($reviewer["action"]).": ( ".$reviewer["id"]." ".$reviewer["email"]." )";
                }
                if ($reviewer["date"]){
                    //print($reviewer["date"]);
                    $rev_txt.=" on ".substr($reviewer["date"],0,10)." (";
                    $days_ago=round((time()-strtotime($reviewer["date"]))/(60*60*24));
                    $rev_txt.=$days_ago." day";
                    if ($days_ago>1){
                        $rev_txt.="s";
                    }
                    $rev_txt.=" ago) \n";      
                    if (strlen($reviewer["reminder"])>0){
                        //print("<!--- Reminder: ".$reviewer["reminder"]." -->\n");
                        $reminder=true;
                        $rev_txt.="reminded on ";
                        $rev_txt.=substr($reviewer["reminder"],0,10)." (";
                        $days_ago=round((time()-strtotime($reviewer["reminder"]))/(60*60*24));
                        $rev_txt.=$days_ago." day";
                        if ($days_ago>1){
                            $rev_txt.="s";
                        }
                        $rev_txt.=" ago) \n";      
                    }
                    //print("<!--- rev_txt: ".$rev_txt." -->\n");
                    if (
                        ((($reviewer["action"]=="accepted")&&($days_ago>=$days_for_review))
                        ||(($reviewer["action"]=="invited")&&($days_ago>=$days_to_accept_invitation))
                        ||(($reminder)&&($days_ago>=$days_after_reminder)))
                        &&(!($reviewer["action"]=="reviewed"))
                        )
                        {
                        $rev_txt.="<b style='color:red;'> Overdue: ".$rev_txt."</b> (<A HREF='send_reminder.php?contribution_id=".$paper["contribution_id"]."'>Send a reminder</A>) ";
                        $paper["overdue"].=$rev_txt;
                        $overdue_papers+=1;
                    } 
                    $rev_txt.="(<A HREF='../LightPeerReview/paper_acceptance?contribution_id=".$paper["contribution_id"]."&force_user_by_email=".$reviewer["email"]."'>Manual action</A>)";
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

                if(str_contains($latest_rev["comments"][count($latest_rev["comments"])-1]["text"],"Proposed judgement:")){
                    $paper["proposed_judgement"]=trim(explode("\n",$latest_rev["comments"][count($latest_rev["comments"])-1]["text"])[1]);
                }

            } else {
                $latest_comment=false;
                $paper["latest_comment"]="No comment";         
            }
            if (count($latest_rev["reviews"])>0){
                if (!($latest_comment)){
                    $latest_comment="";
                    $paper["latest_comment"]="";
                }
                $latest_comment.="<BR/>\n";
                $paper["latest_comment"].="<BR/>\n";
                if (count($latest_rev["reviews"])==1){                    
                    $latest_comment.="1 review<BR/>\n";
                    $paper["latest_comment"].="1 review<BR/>\n";
                } else {
                    $latest_comment.=count($latest_rev["reviews"])." reviews<BR/>\n";
                    $paper["latest_comment"].=count($latest_rev["reviews"])." reviews<BR/>\n";
                    $paper["overdue"].="Check reviews";
                    $reviewed_papers++;
                }
                foreach($latest_rev["reviews"] as $review){
                    $latest_comment.=$review["proposed_action"]["name"]." <BR/>\n";
                    $paper["latest_comment"].=$review["proposed_action"]["name"]." <BR/>\n";
                }
                //print("\nLast comment\n");
                //var_dump($latest_comment);
                if (count($latest_rev["reviews"])>=2){
                    $paper["round"]+=1;
                }
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
    show_exec_time("load_paper end");

} //load_papers 


function show_paper_info($contribution_id,$paper=false){
    global $all_papers, $contributions, $contributions_by_fr_id;

    $content="";
    //for paper number
    if (!($paper)){
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
    }
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
        $paper["proposed_judgement"]=false;
        if (count($the_rev["comments"])>0){
            $paper["revisions_history"].="Comments:<BR/><ol>\n";
            for ($icom=0;$icom<count($the_rev["comments"]);$icom++){
                $the_comment=$the_rev["comments"][$icom];
                if(str_contains($the_comment["text"],"Proposed judgement:")){
                    $paper["proposed_judgement"]=trim(explode("\n",$the_comment["text"])[1]);
                    $paper["proposed_judgement_date"]=$the_comment["created_dt"];
                    $paper["proposed_judgement_text"]=$the_comment["text"];
                    $paper["revisions_history"].="<li><b>".format_time($the_comment["created_dt"])." - ".$the_comment["text"]."</b></li>\n";
                } else {
                   $paper["revisions_history"].="<li>".format_time($the_comment["created_dt"])." - ".$the_comment["text"]."</li>\n";
                }
            }
            $paper["revisions_history"].="</ol>\n";
        }
        if (count($the_rev["reviews"])>0){
            $paper["revisions_history"].="Reviews:<BR/><ol>\n";
            for ($icom=0;$icom<count($the_rev["reviews"]);$icom++){
                $the_review=$the_rev["reviews"][$icom];
                $paper["revisions_history"].="<li>".format_time($the_review["created_dt"])." - ".$the_review["proposed_action"]["name"]."</li>\n";
            }
            $paper["revisions_history"].="</ol>\n";
        }
        $paper["revisions_history"].="</li>";
    } //for irev



    $content .="<BR/><BR/>\n";
    //Format: ["indico field name" => "display name" ]
    $fields_to_display=[ 
                        "abstract_id"=>"Abstract ID",
                        "code"=>"code",
                        "ids" => "IDs",
                        "MC" => "MC", 
                        "track" => "track", 
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

    $review_txt="";
    $full_review_txt="";
    if (count($paper["revisions"][count($paper["revisions"])-1]["reviews"])){
        $the_rev=$paper["revisions"][count($paper["revisions"])-1];
        $content .="<BR/><BR/>\n";
        $content .="<b style='color:green;'>".count($the_rev["reviews"])." reviews received for the latest revision:</b>\n";
        $review_txt.=count($the_rev["reviews"])." reviews received for the latest revision.<BR/>\n";
        $content .="<table border=1>\n";
        for($irating=-5;$irating<count($the_rev["reviews"][0]["ratings"]);$irating++){
            $content .="<tr>\n";
            $this_question_txt="";
            for($ireview=-1;$ireview<count($the_rev["reviews"]);$ireview++){
                $content .="<td>\n";
                if ($irating==-5){
                    if ($ireview>=0){
                        $content .=$the_rev["reviews"][$ireview]["user"]["full_name"];
                        $content .=" (".$the_rev["reviews"][$ireview]["user"]["id"].")";
                    } else {
                        $content .="Name";
                    }
                } else if ($irating==-4){
                    if ($ireview>=0){
                        $content .=$the_rev["reviews"][$ireview]["user"]["email"];
                    } else {
                        $content .="email";
                    }
                } else if ($irating==-3){
                    if ($ireview>=0){
                        $content .=$the_rev["reviews"][$ireview]["user"]["affiliation"];
                        if ($the_rev["reviews"][$ireview]["user"]["affiliation_meta"]){
                            $content .=" (".$the_rev["reviews"][$ireview]["user"]["affiliation_meta"]["country_name"].")";
                        }
                    } else {
                        $content .="Affiliation";
                    }
                } else if ($irating==-2){
                    if ($ireview>=0){
                        $content .=$the_rev["reviews"][$ireview]["proposed_action"]["name"];
                        $review_txt.="Reviewer ".($ireview+1).": ".$the_rev["reviews"][$ireview]["proposed_action"]["name"]."\n";

                    } else {
                        $content .="Decision";
                    }
                } else if ($irating==-1){
                    if ($ireview>=0){
                        $content .=str_replace("T"," ",substr($the_rev["reviews"][$ireview]["created_dt"],0,19));
                    } else {
                        $content .="Date";
                    }
                } else{
                     if ($ireview==-1){
                        $content .=$the_rev["reviews"][0]["ratings"][$irating]["question"]["title"];
                        $this_question_txt.="\n".$the_rev["reviews"][0]["ratings"][$irating]["question"]["title"]."\n";
                    } else {
                        $this_question_txt.="Reviewer ".($ireview+1).": ";
                        if (is_bool($the_rev["reviews"][$ireview]["ratings"][$irating]["value"])){
                            if ($the_rev["reviews"][$ireview]["ratings"][$irating]["value"]){
                                $content .="Yes";
                                $this_question_txt.="Yes\n";
                            } else {
                                $content .="No";
                                $this_question_txt.="No\n";
                            }
                        } else {
                            $content .=$the_rev["reviews"][$ireview]["ratings"][$irating]["value"];
                            $this_question_txt.=$the_rev["reviews"][$ireview]["ratings"][$irating]["value"]."\n";
                        }
                    }
                }
                $content .="</td>\n";
            }
            $full_review_txt.=$this_question_txt;
            $content .="</tr>\n";
        }            
        $content .="</table>\n";
        $content .="<br/>\n";
        $content .="<br/>\n";
        $content .="Editing status: ".$paper["editing_status"]."<br/>\n";
        
        if (count($paper["revisions"][count($paper["revisions"])-1]["reviews"])>=2){
            $content .="<br/>\n";
            $content .="<hr/>\n";
            $content .="<br/>\n";
            if (!($paper["proposed_judgement"])){
                $content .="<b style='color:red;'>No judgement proposed yet.</b><br/>\n";
                $content .="<b style='color:green;'>Paper ready to be judged.</b><br/>\n";
                $content .="Propose a judgement:\n";
                $content .="<form action='edit_paper.php?contribution_id=".$contribution_id."' method='post'>\n";
                $content .="<input type=hidden name=contribution_id value=".$contribution_id.">";
                $content .="<input type=hidden name=MC value=".$paper["MC"].">";
                $content .="<input type=hidden name=editing_status value='Editing status: ".$paper["editing_status"]."' >";
                $content .="<input type=hidden name=review_txt value='".$review_txt."'>";                
                $content .="<input type=hidden name=full_review_txt value='".$full_review_txt."'>";                
                $content .="<input type=radio name=judgement value=accept> Accept<br/>\n";
                $content .="<input type=radio name=judgement value=minor> Minor modifications<br/>\n";
                $content .="<input type=radio name=judgement value=major> Major modifications<br/>\n";
                $content .="<input type=radio name=judgement value=reject> Reject<br/>\n";
                $content .="<br/>\n";
                $content .="Comment to the authors:<br/>\n";
                $content .="<textarea name=comment_to_authors rows=4 cols=50></textarea><br/>\n";
                $content .="Comments to the MC cordinators and for the records (not sent to the authors):<br/>\n";
                $content .="<textarea name=internal_comment rows=4 cols=50></textarea><br/>\n";
                $content .="<br/>\n";
                $content .="<input type=submit name=submit_notify value=\"Judge and notify MC coordinators\"> <br/>\n";
                $content .="<input type=submit name=submit_notify value=\"Judge and notify LPR coordinators only\"> <br/>\n";
                $content .="<input type=submit name=submit_silent value=\"Judge but do not notify MC coordinators\"> <br/>\n";
                $content .="</form>\n";
            } else {
                $content .="<b style='color:green;'>Proposed judgement</b><br/>";
                $content .="Proposed on ".$paper["proposed_judgement_date"];
                $days_ago=round((time()-strtotime($paper["proposed_judgement_date"]))/(60*60*24));
                $days_txt="( ".$days_ago." day";
                if ($days_ago>1){
                    $days_txt.="s";
                }
                $days_txt.=" ago) \n";      
                $content .=$days_txt."<br/>\n";
                $content .=str_replace("\n", "<br/>", $paper["proposed_judgement_text"])." <br/>\n";
                if ($days_ago>1){
                    $content .="Apply the proposed judgement: ".$paper["proposed_judgement_text"]."\n";
                    $content .="<form action='edit_paper.php?contribution_id=".$contribution_id."' method='post'>\n";
                    $content .="<input type=hidden name=contribution_id value=".$contribution_id.">";
                    $content .="<input type=hidden name=proposed_judgement value='".$paper["proposed_judgement"]."'>";
                    $content .="<input type=submit name=apply_judgement value=\"Apply the judgement and notify the authors\"> <br/>\n";
                    $content .="<input type=submit name=apply_judgement_notify_me value=\"Apply the judgement but notify me\"> <br/>\n";
                    $content .="<input type=hidden name=editing_status value='Editing status: ".$paper["editing_status"]."' >";
                    $content .="<input type=hidden name=review_txt value='".$review_txt."'>";                
                    $content .="<input type=hidden name=full_review_txt value='".$full_review_txt."'>";                
                    $content .="</form>\n";
                }
            }
            
        }

    }


    return $content;
} //function show_paper_info


function show_reviewer_info($person){
    //print("<!---\n");
    ///print("show_reviewer_info for person \n");
    //var_dump($person);
    //print(" --->\n");
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
    return $content;

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
            if ($id=="unavailable"){
                $content .="<li><b color='red'>Unavailable</b></li>\n";
            } else if (($id=="name")||($id=="event_id")||($id=="email")||($id=="date")||($id=="reminder")){
                $content .="";
            } else {                
                $content .="<li><A HREF='https://indico.jacow.org/event/95/contributions/".$id."/'>Contribution $id</A>: $action </li>\n";
                if (!array_key_exists($action,$retval["actions"])){
                    $retval["actions"][$action]=0;
                }
                $retval["actions"][$action]+=1;
            }
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
            $content.="<b>Warning reviewer ".$person["person_id"]." is also among the paper persons!</b></br>\n";
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
function get_reviewers_for_contribution($contribution_id,$disable_chache=false,$recheck_probability_percent=0){
    //print("<!--- get_reviewers_for_contribution $contribution_id --->\n");
    global $Indico;
    global $cws_config;
    global $reviewers_info;
    show_exec_time("get_reviewers_for_contribution start");
    if (!$reviewers_info){
        $reviewers_info=file_read_json( $cws_config['global']['data_path']."/reviewers_info.json",true);
    }
    if (!$reviewers_info){
        print("<!--- Recreating reviewers_info --->\n");
        $reviewers_info=[];
    }

    if (($recheck_probability_percent>0)&&(rand(0,100)>$recheck_probability_percent)){
        //var_dump($reviewers_info);
        $retval=[];
        $ival=0;
        foreach($reviewers_info as $id => $info){
            if (array_key_exists($contribution_id,$info)){
                //print("(no recheck) Reviewer $id has action ".$info[$contribution_id]." for contribution $contribution_id \n");
                //var_dump($info);
                $retval[$ival]=[];
                $retval[$ival]["id"]="".$id;
                $retval[$ival]["name"]=$info["name"];
                $retval[$ival]["email"]=$info["email"];
                $retval[$ival]["event_id"]=$info["event_id"];
                /*
                $retval[$ival]["date"]=$reviewers_from_history["allocation_date"][$id];
                $retval[$ival]["reminder"]=$reviewers_from_history["reminder_date"][$id];
                */
                $retval[$ival]["action"]=$info[$contribution_id];
                $retval[$ival]["date"]=$info["date"][$contribution_id];
                $retval[$ival]["reminder"]=$info["reminder"][$contribution_id];
                //$retval[$ival]["date"]=$reviewers_from_history["allocation_date"][$id];
                //$retval[$ival]["reminder"]=$reviewers_from_history["reminder_date"][$id];
                $ival+=1;
            }            
        }
        show_exec_time("get_reviewers_for_contribution end (no recheck)");        
        //var_dump($retval);
        return $retval;
    } //no recheck

    $reviewers_from_history=get_paper_reviewers_status($contribution_id);
    //$_rqst_cfg=[];
    //$_rqst_cfg['disable_cache'] =true;
    $post_data=array( "contribution_id" => "".$contribution_id );
    $req_papers =$Indico->request( "/event/{id}/manage/papers/assignment-list/unassign/content_reviewer", 'POST', $post_data, array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' => $disable_chache ) );
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
    //print("<!--- Reviewers from assignement: \n"); var_dump($retval); print(" --->\n");
    //print("<!--- Reviewers from history: \n");var_dump($reviewers_from_history); print(" --->\n");
    foreach($reviewers_from_history["allocation"] as $id => $action){
        if (($action=="accepted")||($action=="reviewed")){
            $found=false;
            for ($ival=0;$ival<count($retval);$ival++){
                //print("".$retval[$ival]["id"]."=?=".$id."? \n");
                if ($retval[$ival]["id"]=="".$id){                    
                    $retval[$ival]["date"]=$reviewers_from_history["allocation_date"][$id];
                    $retval[$ival]["reminder"]=$reviewers_from_history["reminder_date"][$id];
                    $retval[$ival]["action"]=$action;
                    $retval[$ival]["event_id"]=get_participant("user_id",$id)["id"];
                    $retval[$ival]["name"]=get_participant("user_id",$id)["full_name"];
                    $retval[$ival]["email"]=get_email_from_userid($id);
                    $found=true;
                }
            }
            if (!$found){
                print("Warning: reviewer $id has accepted but is not among the reviewers lists for contribution ".$contribution_id."! <BR/>\n");
            }
        } else if (($action=="invited")){
            //print("get_participant:\n");
            //var_dump(get_participant("user_id",$id)["id"]);            
            //print("\n");
            $retval[]=array("id" => "".$id,
                            "event_id"=>get_participant("user_id",$id)["id"],
                            "name"=>get_full_name_from_userid($id),
                            "email"=>get_email_from_userid($id),
                            "action"=>$action, 
                            "date" => $reviewers_from_history["allocation_date"][$id],
                            "reminder" => $reviewers_from_history["reminder_date"][$id]
                            );
            //var_dump($retval[count($retval)-1]);
        } else {
            //print("\n<!--- \n"); print("other action: $id");var_dump($action);   print("--->\n");
            
            if (!(array_key_exists($id,$reviewers_info))){
                $reviewers_info[$id]=[];
                //print("Creating entry \n");
            }
            $reviewers_info[$id][$contribution_id]=$action;
            $reviewers_info[$id]["id"]="".$id;
            $reviewers_info[$id]["event_id"]=get_participant("user_id",$id)["id"];
            $reviewers_info[$id]["name"]=get_full_name_from_userid($id);
            $reviewers_info[$id]["email"]=get_email_from_userid($id);
            $reviewers_info[$id]["action"]=$action;
            $reviewers_info[$id]["date"]="";
            $reviewers_info[$id]["reminder"]="";
            
            if ($action=="declined"){
                if (array_key_exists("decline_reason",$reviewers_from_history)){
                    //print("Reason for decline: ");
                    //var_dump($reviewers_from_history["decline_reason"]);
                    foreach($reviewers_from_history["decline_reason"] as $rid => $reason){
                        if (($reason=="decline_no_review")||($reason=="decline_not_eligible")){
                            //print("Reviewer $rid is unavailable for review! $reason \n");
                            $reviewers_info[$rid]["unavailable"]=true;
                        }
                    }                    
                }
            }
        }
    }    
    //print("<!--- Loop on reviewer returned values:\n"); var_dump($retval); print("--->\n");
    foreach($retval as $rev){

        //checking reviewer roles
        //print("<!--- Reviewers roles: \n"); print("Rev id:".$rev["id"]." ".$rev["action"]."\n"); print("--->\n");

        if (!(array_key_exists($rev["id"],$reviewers_info))){
            $reviewers_info[$rev["id"]]=[];
            //print("Creating entry \n");
        }
        $reviewers_info[$rev["id"]]["event_id"]=$rev["event_id"];
        $reviewers_info[$rev["id"]]["name"]=$rev["name"];
        $reviewers_info[$rev["id"]]["email"]=$rev["email"];

        if (!(array_key_exists("action",$rev))){
                print("Warning: reviewer ".$rev["id"]." is among the reviewers lists but not has not accepted or reviewed in the timeline for contribution ".$contribution_id."! <BR/>\n");
                $reviewers_info[$rev["id"]][$contribution_id]="In reviewers list";
        } else {
            //print("Action: ".$rev["action"]."\n");
            $reviewers_info[$rev["id"]][$contribution_id]=$rev["action"];
            $reviewers_info[$rev["id"]]["date"]=[];
            $reviewers_info[$rev["id"]]["date"][$contribution_id]=$rev["date"];
            $reviewers_info[$rev["id"]]["reminder"]=[];
            $reviewers_info[$rev["id"]]["reminder"][$contribution_id]=$rev["reminder"];
        }
        //print("<!--- Reviewer ".$rev["id"]." updated:\n"); var_dump($reviewers_info[$rev["id"]]); print("--->\n");
    }
    $fwret=file_write_json(  $cws_config['global']['data_path']."/reviewers_info.json",$reviewers_info);
    //var_dump($reviewers_info);
    print($fwret?"<!--- Reviewers file saved successfully --->\n":"Error saving reviewers data\n");
    show_exec_time("get_reviewers_for_contribution end (rechecked)");
    return $retval;    
} //get_reviewers

function get_paper_reviewers_status($contribution_id){
    //print("<!--- get_paper_reviewers_status $contribution_id --->\n");
    show_exec_time("get_paper_reviewers_status start");
    $paper=get_paper($contribution_id,use_session_token:false);
    if (!$paper){
        print("<!--- Unable to get paper for contribution $contribution_id --->\n");
        return false;
    }
    //print("Timeline <BR/>\n");
    $reviewers=[];
    $reviewers["allocation"]=[];
    $reviewers["allocation_date"]=[];
    $reviewers["invited"]=[];
    $reviewers["uninvited"]=[];
    $reviewers["accepted"]=[];
    $reviewers["reviewed"]=[];
    $reviewers["declined"]=[];
    $reviewers["assigned"]=[];
    $reviewers["unassigned"]=[];
    foreach($paper["revisions"] as $revision){
        foreach($revision["timeline"] as $timeitem){
            /*
            print("\n");
            print("<!--- \n");
            print("timeitem");
            print_r($timeitem);
            print("\n");
            print($timeitem["text"]);
            print("--->\n");
            print("\n");
            */
            if ($timeitem["timeline_item_type"]=="comment"){                        
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
                        $reviewers["reminder_date"][$reviewer_id]="";
                        $reviewers[$action][]=$reviewer_id;
                        $reviewers[$action]=array_unique($reviewers[$action]);
                    } else {
                        $matchtxt='#Reminder sent to ([0-9]+)#';
                        $returnValue = preg_match_all($matchtxt, $timeitem["text"], $matches);
                        if ($returnValue){
                            $reviewer_id=$matches[1][0];
                            $reviewers["reminder_date"][$reviewer_id]=$timeitem["created_dt"];
                        } else {
                            $matchtxt='#Reason given by ([0-9]+): (decline_[a-z_]+)#';
                            $returnValue = preg_match_all($matchtxt, $timeitem["text"], $matches);
                            if ($returnValue){
                                $reviewer_id=$matches[1][0];
                                $reviewers["decline_reason"][$reviewer_id]=$matches[2][0];
                            }
                        }
                    }
                } 
            }  //timeitem is a comment 
            else if ($timeitem["timeline_item_type"]=="review"){
                //print("\n<!--- \n"); print("timeitem (review)"); print_r($timeitem); print("\n"); print($timeitem["text"]); print("--->\n");
                $reviewer_id=$timeitem["user"]["id"];
                $action="reviewed";
                //print("Action: $action \n");
                $reviewers["allocation"][$reviewer_id]=$action;
                $reviewers["allocation_date"][$reviewer_id]=$timeitem["created_dt"];
                $reviewers["reminder_date"][$reviewer_id]="";
                $reviewers[$action][]=$reviewer_id;
                $reviewers[$action]=array_unique($reviewers[$action]);
            }  else {
                print("\n");
                print("<!--- \n");
                print("ignored timeitem");
                print_r($timeitem);
                print("\n");
                print($timeitem["text"]);
                print("--->\n");
                print("\n");
            }
        } //for each timeitem

    } //for each revision
    //print("<!--- Reviewers for paper $contribution_id:\n");
    //var_dump($reviewers);    
    //print("--->\n");
    show_exec_time("get_paper_reviewers_status end");
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


function check_reviewer_availability_for_paper($person_event_id,$contribution_id) {
    global $cws_config;
    $reviewers_info=file_read_json( $cws_config['global']['data_path']."/reviewers_info.json",true);
    $person_id=get_participant("id",$person_event_id)["user_id"];
    print("<!--- Checking availability of reviewer $person_id ( $person_event_id ) for paper $contribution_id --->\n");
    $retval=[];
    $retval["content"]="";
    $retval["can_assign"]=true;
    if (!$reviewers_info){
        print("Unable to read reviewers file!");
        $retval["content"]="Unable to read reviewers file!";
        $retval["can_assign"]=false;
        return $retval;
    }
    if (array_key_exists($person_id,$reviewers_info)){
        if (array_key_exists("unavailable",$reviewers_info[$person_id])){
            $retval["content"]="Reviewer ". $person_id ." is unavailable for review!";
            $retval["can_assign"]=false;
            return $retval;
        } 
        if (array_key_exists($contribution_id,$reviewers_info[$person_id])){
            if ($reviewers_info[$person_id][$contribution_id]=="declined"){
                $retval["content"]="Reviewer ". $person_id ." has already declined to review this paper!";
                $retval["can_assign"]=false;
                return $retval;
            } else if ($reviewers_info[$person_id][$contribution_id]=="invited"){
                $retval["content"]="Reviewer ". $person_id ." has already been invited to review this paper!";
                $retval["can_assign"]=true;
                return $retval;
            } else if ($reviewers_info[$person_id][$contribution_id]=="accepted"){
                $retval["content"]="Reviewer ". $person_id ." has already accepted to review this paper!";
                $retval["can_assign"]=true;
                return $retval;
            } else {
                $retval["content"]="Reviewer ". $person_id ." has the following status for this paper: ".$reviewers_info[$person_id][$contribution_id].". Please check the reviewers list for this paper to get more details.";
                $retval["can_assign"]=true;
                return $retval;
            }
        //no information about this reviewer, assuming available
        } else{
            $retval["content"]="Reviewer ". $person_id ." not yet assigned to this paper.";
            $retval["can_assign"]=true;
            return $retval;
        }
    } 
    $retval["content"]="Reviewer ". $person_id ." is not in the reviewers list yet.";
    $retval["can_assign"]=true;
    return $retval;
} //check_reviewer_availability_for_paper($contribution, $person_id)

function judge_paper($contribution_id,$decision,$comment,$use_session_token=true,$use_indico_token=false){
    global $Indico;
    show_exec_time("judge_paper start");
    //print("\njudging: $contribution_id\n");
    //print("Judging paper $contribution_id with decision $decision and comment: $comment \n");
    $post_data=array(
        "comment" => $comment,
        'action' => $decision , 
    );
    if ($use_indico_token){
        $use_session_token=false;
    }
    $req =$Indico->request( "/event/{id}/papers/api/".$contribution_id."/judge", 'POST', $post_data , array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true , 'use_session_token' => $use_session_token , 'use_indico_token' => $use_indico_token ) );
    show_exec_time("judge_paper end");
}//judge_paper

