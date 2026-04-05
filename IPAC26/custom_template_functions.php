<?php
require_once( 'ipac26_tools.php' );

function php_encode($text){
    return str_replace("\t","\\t",str_replace("\e","\\e", $text));
} // function html_encode

function get_initial($name,$spacer){
    if ($name){
        //$matchtxt='#([[:alpha:]]+)([-.\']?)#';
        $matchtxt='#(\pL+)([-.\']?)#u';
        $returnValue = preg_match_all($matchtxt, $name, $matches);
        /*
        print("<--- \n");
        var_dump($matches);
        print("\n --->\n");
        */
        $initials="";
        for ($icount=0;$icount<count($matches[0]);$icount++){
            if (strlen($matches[2][$icount])==0){
                $matches[2][$icount]="";
            }
            if (strlen($matches[1][$icount])==2){
                $initials .= $matches[1][$icount]; //Two letters names are not initilialed (e.g. "Al" in "Al Gore", "Le" in "Le Guin")
            } else {
                $exceptions = array("ch","ph","ll","ij"); // list of double letter sounds that should not be split in initials (e.g. "ch" in "Chopin", "ph" in "Philip", "ll" in "Lloyd", "ij" in Dutch names like "Ruijgrok")
                if ((in_array(substr(strtolower($matches[1][$icount]),0,2), $exceptions))&&(in_array(substr(strtolower($matches[1][$icount]),2,1),array("a","e","i","o","u","y")))){
                    $initials .=strtoupper(substr($matches[1][$icount],0,1)).strtolower(substr($matches[1][$icount],1,1))."."; // If the first two letters are in the exceptions list, use them as the initial
                } else {
                    $initials .=strtoupper(substr($matches[1][$icount],0,1)).".";
                }
            }
            if ($matches[2][$icount]==""){
                $initials .=$spacer;
            } else {
                $initials .=$matches[2][$icount];
            }
        }
        return $initials;
    } else {
        return "";
    }
} // function get_initial

function create_title_author_block($req,$indico_link=false){
    global $Indico,$cws_config,$_SESSION;
    //To do:
    // - check for secondary affili
    // - Authors by full alphabetical order 

    $function_content ="<BR/>\n";
    $latex="%%%%% Title - Author block generated from indico contribution details %%%%%\n";
    $latex.="%%%%%%%%%%%%%%%%%%%% Do not modify  %%%%%%%%%%%%%%%%%%%%%%%%%\n";
    $latex.="%%%%% If you need to modify the content of this block, do it by modifying the $indico_link %%%%%\n";
    $function_content .="Contribution title: <b>".$req["title"]."</b><BR/>\n";

    $contribs_qa_data=file_read_json(  $cws_config['global']['data_path']."/contribs_qa.json",true);
    if (!($contribs_qa_data)) {
        die("Unable to read contribs_qa_data.");
    }
    $allowed=false;
    foreach($req["persons"] as $author){
        if ((array_key_exists("email", $author))&&($author["email"]==$_SESSION['indico_oauth']["user"]["email"])){
            $allowed=true;
            break;
        }
        if ((array_key_exists("email_hash", $author))&&($author["email_hash"]==hash('md5', $_SESSION['indico_oauth']["user"]["email"]))){
            $allowed=true;
            break;
        }
    }
    if (!(empty(array_intersect(array ("SS","JAD"),$_SESSION['indico_oauth']["user"]["roles"])))){
        $allowed=true;
    }
    if (!($allowed)){
        $content ="<b>You are not allowed to access this contribution. Please check that you are among the contributors of this contribution. If necessary, ask the submitter to update the $indico_link.</b>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";

        print($content );
        exit;
    }

    $contribution_id=$req["id"];
    if (
        (array_key_exists($contribution_id, $contribs_qa_data))
        &&(array_key_exists("title", $contribs_qa_data[$contribution_id]))
        &&($contribs_qa_data[$contribution_id]["title"]["sentence_case"]==$req["title"])
    ){
        $title_upper_case=$contribs_qa_data[$contribution_id]["title"]["upper_case"];
        $title_sentence_case=$contribs_qa_data[$contribution_id]["title"]["sentence_case"];
    } else {
        $title_upper_case=strtoupper($req["title"]);
        $title_sentence_case=ucfirst($req["title"]);
    }


    //check for uppercase in title
    if (!(preg_match("/[A-Z]/",substr($req["title"],0,1)))){
        $function_content .="<b>WARNING:</b> The title in indico does not start with an uppercase letter. This should not be the case.<BR/>\n";
        $function_content .="To fix this, please update the contribution title in  by modifying the $indico_link: the title should start with an uppercase letter. <BR/>\n";
    } 
    $upmatches=[];
    /*
    $latex_title_case=$req["title"];
    if (preg_match_all("/[A-Z]/",substr($req["title"],1),$upmatches,PREG_OFFSET_CAPTURE)){
        //var_dump($upmatches);
        //$function_content .="<b>WARNING:</b> The title contains uppercase letters after the first letter.<BR/>\n";
        //print(trim($req["title"])." "."\n");
        preg_match_all("/ (\S*[A-Z]\S*) /",trim($req["title"])." ",$upmatches,PREG_OFFSET_CAPTURE);
        //var_dump($upmatches);  
        $function_content .="<ul>\n";  
        $offset=0;
        foreach($upmatches[1] as $thematch){
            $function_content .="<li>Please ensure that the word \"".$thematch[0]."\" is an acronym or a proper noun, otherwise it should be in lowercase.</li>\n";
            $new_latex_title_case=substr($latex_title_case,0,$thematch[1]+$offset)."\NoCaseChange{".substr($latex_title_case,$thematch[1]+$offset, strlen($thematch[0]))."}".substr($latex_title_case,$thematch[1]+$offset+strlen($thematch[0]));
            $latex_title_case=$new_latex_title_case;
            $offset+=15; // account for the length of \NoCaseChange{}
        }
        $function_content .="</ul>\n";  
        $function_content .="If any of the above words are not acronyms or proper nouns, please update the contribution title by modifying the $indico_link: the title should be in sentence case with only the first letter capitalized (and acronyms or proper nouns). <BR/>\n";    
    }
    */

    $latex.="\title{".$title_upper_case;
    $word_title=$title_upper_case;
    $function_content .="<BR/>\n";
    $word_footnote="";
    $word_footnote_lines=0;
    if (strlen(trim($req["custom_fields"][1]["value"]))>0){
        $function_content .="Footnotes: <em>".$req["custom_fields"][1]["value"]."</em><BR/>\n";
        $latex .="\thanks{".$req["custom_fields"][1]["value"]."}";
        //$word_footnote="* ".$req["custom_fields"][1]["value"]." <w:br/> ";
        $word_footnote="* ".$req["custom_fields"][1]["value"].'</w:t></w:r></w:p><w:p w:rsidR="00A63049" w:rsidRPr="00FA5D95" w:rsidRDefault="00A63049" w:rsidP="00A63049"><w:pPr><w:pStyle w:val="JACoWFootnoteText"/></w:pPr><w:r w:rsidRPr="00FA5D95"><w:t>';
        $word_title.="*";
        $word_footnote_lines+=intval(mb_strlen($req["custom_fields"][1]["value"])/50)+1; // rough estimate of number of lines in footnote
        //$function_content .="Footnote is on ".$word_footnote_lines." lines in the Word template.<BR/>\n";
    } else{
        $function_content .="No footnotes (funding information) found.<BR/>\n";
    }
    $latex .="}\n\n";

    if (count($req["persons"])>1){
       $function_content .="Authors:<BR/><ul>\n";
    } else{
       $function_content .="Author:<BR/><ul>\n";
    }
    $all_labs=[];
    foreach($req["persons"] as $person){
        //affiliations have already been fixed
        $affname="";
        $affcity="";
        $affcountry="";
        $affcountry_code="";
        if (!array_key_exists("affiliation_link",$person)){
            if (array_key_exists("affiliation",$person)){
                $person["affiliation_link"]=[];
                $person["affiliation_link"]["name"]=$person["affiliation"];
            }
        }
        if (array_key_exists("affiliation_link",$person)){
            //var_dump($person["affiliation_link"]);
            if (!key_exists($person["affiliation_link"]["id"],$all_labs)){
                $affid=$person["affiliation_link"]["id"];
                if (!key_exists($affid,$all_labs)){
                    $theaff=$affid;
                    $all_labs[$affid]=[];
                    $all_labs[$affid]["authors"]=[];
                    $all_labs[$affid]["name"]=$person["affiliation_link"]["name"];
                    $all_labs[$affid]["city"]=$person["affiliation_link"]["city"];
                    $all_labs[$affid]["country_name"]=$person["affiliation_link"]["country_name"];
                    $all_labs[$affid]["country_code"]=$person["affiliation_link"]["country_code"];
                }
                if (!key_exists($person["affiliation_link"]["id"],$all_labs)){
                    $all_labs[$person["affiliation_link"]["id"]]=[];
                    $all_labs[$person["affiliation_link"]["id"]]["rename"]=$affname;   
                }             
                //print ("New affiliation found: ".$person["affiliation"]."\n");
            }
        } //affiliation link exists
        if (key_exists("rename",$all_labs[$person["affiliation_link"]["id"]])){
            $theaff=$all_labs[$person["affiliation_link"]["id"]]["rename"];
        } else {
            $theaff=$person["affiliation_link"]["id"];
        }
        $all_labs[$theaff]["authors_names"][]=$person["last_name"];
        $all_labs[$theaff]["authors"][]=$person;
        $jaff=1;
    } // foreach person

    //var_dump($all_labs);

    ksort($all_labs);
    $primary_found=false;
    $multiple_primary_found=false;
    $primary_aff="";
    $all_aff="";
    $primary_latex="";
    $primary_word="";
    $all_latex="";
    $all_word="";
    $word_authors="";
    $word_primary_emails=[];

    //var_dump($all_labs);
    $footnote_symbols=array("†","‡","♦","♣","♠");
    $footnote_symbol_index=0;

    foreach($all_labs as $lab => $lab_info){
        $primary_found=false;
        $this_primary_aff_txt="";
        $this_primary_latex_txt="";
        $this_primary_word_txt="";
        $this_secondary_aff_txt="";
        $this_secondary_latex_txt="";
        $this_secondary_word_txt="";
        $is_primary_aff=false;
        if (!(key_exists("rename",$lab_info))){
            array_multisort($lab_info["authors_names"], SORT_ASC, $lab_info["authors"]);
            foreach($lab_info["authors"] as $author){
                //var_dump($author);
                //print("\n\n");

                if ($author["author_type"]=="primary"){
                    $is_primary_aff=true;
                    if ($primary_found){
                        $multiple_primary_found=true;
                    }
                    $primary_found=true;
                    if (!($author["email"])){
                        if (($author["person_id"])){
                            $author["email"]=get_participant("id",$author["person_id"])["email"];
                        }
                    }
                    if (!($author["email"])){
                        $function_content .="<b>WARNING:</b> The primary author (".get_initial($author["first_name"],"&nbsp;").$author["last_name"].") does not have an email address. This should not be the case.<BR/>\n";
                        $function_content .="To add the email address of the primary author, please update modify the $indico_link: only the corresponding author and only the corresponding author should appear \"author\". All other contributors should be listed as \"co-author\". <BR/>\n";
                        $this_primary_aff_txt .=get_initial($author["first_name"],"&nbsp;").$author["last_name"]." ";
                        $this_primary_latex_txt .=get_initial($author["first_name"],"~").$author["last_name"]."";
                        $this_primary_word_txt .=get_initial($author["first_name"]," ").$author["last_name"];
                    } else {
                        $this_primary_aff_txt .= get_initial($author["first_name"],"&nbsp;").$author["last_name"]."&nbsp;(";
                        $this_primary_aff_txt .= substr($author["email"],0,strpos($author["email"],"@")+5)."... ";
                        $this_primary_aff_txt .= ")";
                        $this_primary_latex_txt .=get_initial($author["first_name"],"~").$author["last_name"]."\thanks{".$author["email"]."}";
                        $word_primary_emails[]=$author["email"];
                        $this_primary_word_txt .=get_initial($author["first_name"]," ").$author["last_name"].'</w:t><w:rPr><w:vertAlign w:val="superscript"/></w:rPr><w:t>';
                        $this_footnote_symbol=$footnote_symbols[$footnote_symbol_index%count($footnote_symbols)];
                        for($dloop=0;$dloop<intdiv($footnote_symbol_index,count($footnote_symbols))+1;$dloop++){                            
                            $this_primary_word_txt .=$this_footnote_symbol;
                            $word_footnote .=$this_footnote_symbol;
                        }
                        $footnote_symbol_index+=1;
                        $word_footnote .=$author["email"].'</w:t></w:r></w:p><w:p w:rsidR="00A63049" w:rsidRPr="00FA5D95" w:rsidRDefault="00A63049" w:rsidP="00A63049"><w:pPr><w:pStyle w:val="JACoWFootnoteText"/></w:pPr><w:r w:rsidRPr="00FA5D95"><w:t>';
                        $word_footnote_lines+=1;
                    } //author has email?
                } else {
                    //secondary author
                    $this_secondary_aff_txt .=get_initial($author["first_name"],"&nbsp;").$author["last_name"];
                    $this_secondary_latex_txt .=get_initial($author["first_name"],"~").$author["last_name"];
                    $this_secondary_word_txt .=get_initial($author["first_name"]," ").$author["last_name"];
                } //primary or secondary?
                if (array_key_exists("affiliation_link_2",$author)){
                    $this_multi_aff_txt .= "(also at";
                    $this_multi_latex_txt .="\\footnote{also at ";
                    $this_multi_word_txt .='</w:t><w:rPr><w:vertAlign w:val="superscript"/></w:rPr><w:t>';
                    $this_footnote_symbol=$footnote_symbols[$footnote_symbol_index%count($footnote_symbols)];
                    for($dloop=0;$dloop<intdiv($footnote_symbol_index,count($footnote_symbols))+1;$dloop++){
                        $this_primary_word_txt .=$this_footnote_symbol;
                        $word_footnote .=$this_footnote_symbol;
                    }
                    $footnote_symbol_index+=1;
                    $word_footnote .= 'Also at ';
                    $word_footnote_lines+=1;
                    $jaff=1;
                    $link_name="affiliation_link_".($jaff+1);
                    while(array_key_exists($link_name, $author)){
                        $this_multi_aff_txt .= " ".$author[$link_name]["name"].", ".$author[$link_name]["city"].", ".$author[$link_name]["country_name"].";";
                        $this_multi_latex_txt .=" ".$author[$link_name]["name"].", ".$author[$link_name]["city"].", ".$author[$link_name]["country_name"].";";
                        $word_footnote .= " ".$author[$link_name]["name"].", ".$author[$link_name]["city"].", ".$author[$link_name]["country_name"].";";
                        $jaff+=1;
                        $link_name="affiliation_link_".($jaff+1);
                    }
                    $this_multi_aff_txt =substr($this_multi_aff_txt,0,-1); // remove last comma
                    $this_multi_aff_txt .= ")";
                    $this_multi_latex_txt =substr($this_multi_latex_txt,0,-1); // remove last comma
                    $this_multi_latex_txt .="}";
                    $word_footnote =substr($word_footnote,0,-1); // remove last comma
                    $word_footnote .= '</w:t></w:r></w:p><w:p w:rsidR="00A63049" w:rsidRPr="00FA5D95" w:rsidRDefault="00A63049" w:rsidP="00A63049"><w:pPr><w:pStyle w:val="JACoWFootnoteText"/></w:pPr><w:r w:rsidRPr="00FA5D95"><w:t>';
                if ($author["author_type"]=="primary"){
                    $this_primary_aff_txt .= $this_multi_aff_txt;
                    $this_primary_latex_txt .= $this_multi_latex_txt;
                    $this_primary_word_txt .= $this_multi_word_txt;
                } else {
                    $this_secondary_aff_txt .= $this_multi_aff_txt;
                    $this_secondary_latex_txt .= $this_multi_latex_txt;
                    $this_secondary_word_txt .=$this_multi_word_txt;
                }
                } // if multiple affiliations, they are added as footnotes in LaTeX and in the text in HTML and Word, but not in the affiliation block
                if ($author["author_type"]=="primary"){
                    $this_primary_aff_txt .= ", ";
                    $this_primary_latex_txt .= ", ";
                    $this_primary_word_txt .='</w:t><w:rPr><w:vertAlign w:val="normal"/></w:rPr><w:t>'.", ";
                } else {
                    $this_secondary_aff_txt .= ", ";
                    $this_secondary_latex_txt .= ", ";
                    $this_secondary_word_txt .='</w:t><w:rPr><w:vertAlign w:val="normal"/></w:rPr><w:t>'.", ";
                }
            } //foreach author

            $this_aff_txt="<li>".$this_primary_aff_txt . $this_secondary_aff_txt;
            $this_latex_txt=$this_primary_latex_txt . $this_secondary_latex_txt;
            $this_word_txt=$this_primary_word_txt . $this_secondary_word_txt;
            if (!($lab)||(!$lab_info)||(!$lab_info["name"])||strlen(trim($lab_info["name"]))==0){
                $function_content .="<b>WARNING:</b> Affiliation empty for ". $author["first_name"]." ".$author["last_name"]." This should not be the case.<BR/>\n";
            } else {
                $this_aff_txt .=$lab_info["name"];
                $this_latex_txt .= $lab_info["name"]; 
                $this_word_txt .= $lab_info["name"];
                if (strlen(trim($lab_info["city"]))==0){
                    $function_content .="<b>WARNING:</b> City empty for affiliation ". $lab." This should not be the case.<BR/>\n";
                } else {
                    $this_aff_txt .=", ".$lab_info["city"];
                    $this_latex_txt .=", ".$lab_info["city"];
                    $this_word_txt .=", ".$lab_info["city"];
                }
                if (strlen(trim($lab_info["country_name"]))==0){
                    $function_content .="<b>WARNING:</b> Country empty for affiliation ". $lab." This should not be the case.<BR/>\n";
                } else{
                    $this_aff_txt .=", ".$lab_info["country_name"];
                    $this_latex_txt .=", ".$lab_info["country_name"];
                    $this_word_txt .=", ".$lab_info["country_name"];
                }
            }

            $this_aff_txt .="</li>\n";
            $this_latex_txt .="\\\\ \n";
            $this_word_txt .=" <w:br/> ";
            if ($primary_found){
                $primary_aff .=$this_aff_txt;
                $primary_latex .=$this_latex_txt;
                $primary_word .=$this_word_txt;            
            } else {
                $all_aff .=$this_aff_txt;
                $all_latex .=$this_latex_txt;
                $all_word .=$this_word_txt;
            }
        }// if not renamed affiliation
        $lab_info=[];
    } // foreach affiliation
    $function_content .=$primary_aff;
    $function_content .= $all_aff;
    $function_content .="</ul>\n";

    $latex .="\author{".$primary_latex.$all_latex;
    $latex=substr($latex,0,-4); // remove last comma and space
    $latex .="}\n\n";

    $word_authors=$primary_word.$all_word;

    $function_content .="Reminder of the JACoW rule on authors list: The name of the primary author should be first, followed by all the other contributors (co-authors in indico), alphabetically by affiliation.<BR/>\n<BR/>\n\n";

    if ($multiple_primary_found){
        $function_content .="<b>WARNING:</b> Several authors have been identified as primary authors. This should not be the case.<BR/>\n";
        $function_content .="To select the correct primary author, please update the contribution by modifying the $indico_link: only the corresponding author should appear \"author\". All other contributors should be listed as \"co-author\". <BR/>\n";
    } elseif (!$primary_found){
        $function_content .="<b>WARNING:</b> No primary author has been identified. \n<BR/>";
        $function_content .="To select the correct primary author, please update the contribution by modifying the $indico_link: the correspondaning author and only the corresponding author should appear \"author\". All other contributors should be listed as \"co-author\". <BR/>\n";
    }

    $latex .="\maketitle\n\n";

    $latex .="%%%%% End of Title - Author block %%%%%\n\n";
    $latex .="%%%%% You can modify the abstract to add LaTeX commands. However, please make sure that it is similar to the one in indico %%%%%\n";
    $latex .="\begin{abstract}\n";
    $latex .=$req["description"]."\n";
    $latex .="\end{abstract}\n";

    $function_content .="Abstract: <em>".$req["description"]."</em><BR/>\n";
    $function_content .="<BR/>\n";

    /*    
        $function_content .="<li><b>".get_initial($person["first_name"],"&nbsp;").$person["last_name"]."</b>, affiliation: <b>".$person["affiliation"]."</b></li>\n";
    }  
    */      
    $function_content .="</ul>\n";
    $function_content .="<BR/>\n";
    $returnValue=[];
    $returnValue["html"] =$function_content;
    $returnValue["latex"] =$latex;
    $returnValue["word"]=[];
    $returnValue["word"]["authors"] = $word_authors;
    $returnValue["word"]["title"] = $word_title;
    $returnValue["word"]["footnote"] = $word_footnote;
    $returnValue["word"]["abstract"] = $req["description"];
    $returnValue["word"]["footnote_lines"] = $word_footnote_lines;
    //$returnValue["word"]["authors"] = "";
    //$returnValue["word"]["title"] = "";
    //$returnValue["word"]["footnote"] = "";
    $returnValue["word"]["title"] =  
                                        str_replace("<","&lt;",
                                        str_replace(">","&gt;",
                                        str_replace("&","&amp;",
                                        str_replace(mb_chr(0xA),"",
                                        $word_title))));
    $returnValue["word"]["abstract"] =  
                                        str_replace("<","&lt;",
                                        str_replace(">","&gt;",
                                        str_replace("&","&amp;",
                                        str_replace(mb_chr(0xA),"",
                                        $req["description"]))));
    //$returnValue["word"]["footnote_lines"] = 0;

    return $returnValue;
} // function create_title_author_block
?>