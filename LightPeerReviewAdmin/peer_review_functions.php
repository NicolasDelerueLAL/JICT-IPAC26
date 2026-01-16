
<?php
/* by nicolas.delerue@ijclab.in2p3.fr 

Function for Light Peer Review

2026.01.14 - Created by nicolas.delerue@ijclab.in2p3.fr

*/

/*** FUNCTIONS  ***/
function format_time($time){
    if (strlen($time)>16){
        return substr(str_replace( "T"," ",$time),0,16);
    } else{
        return "Incorrect format: ".$time;
    }
}

function my_var_dump($thevar){
    print("\n$thevar\n");
    eval("var_dump($thevar);");
}

//Reviewer functions

function get_reviewers_from_contribution($contribution_id){
    global $Indico;
    $_rqst_cfg=[];
    $_rqst_cfg['disable_cache'] =true;
    $post_data=array( "contribution_id" => "".$contribution_id );
    $req_papers =$Indico->request( "/event/{id}/manage/papers/assignment-list/unassign/content_reviewer", 'POST', $post_data, array( 'return_data' =>true, 'quiet' =>true ) );
    //var_dump(json_decode($req_papers,true)["html"]);
    $returnValue = preg_match_all('#"assign-user-(.*)">\n +(.*)#', json_decode($req_papers,true)["html"], $matches);
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

function load_contributions(){
    global $Indico;
    global $contributions,$contributions_by_abs_id,$contributions_by_fr_id,$all_contributions;
    $_rqst_cfg=[];
    $_rqst_cfg['disable_cache'] =false;
    //$_rqst_cfg['disable_cache'] =true;
    $req_contributions =$Indico->request( "/event/{id}/manage/contributions/contributions.json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true ) );
    $contributions=[];
    $contributions_by_abs_id=[];
    $all_contributions=json_decode($req_contributions,true);
    foreach($all_contributions as $contribution){
        $contribution["primary_author_name"]="";
        $contribution["primary_author_affiliation"]="";
        $contribution["regions"]="";
        foreach($contribution["persons"] as $person){
            if ($person["author_type"]=="primary"){
                $contribution["primary_author_name"]=$person["full_name"];
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
    $all_papers=json_decode($req_papers,true)['papers'];
    for($ploop=0;$ploop<count($all_papers);$ploop++){
        $paper=$all_papers[$ploop];
        $paper["contribution_id"]=$paper["contribution"]['id'];
        $contribution=$contributions[$paper["contribution"]['id']];
        if (!($contribution)){
            print("Unable to find contribution".$paper["contribution"]['id']);
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
                print("Unable to find abstract for paper ".$paper["contribution"]['id']);
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

?>