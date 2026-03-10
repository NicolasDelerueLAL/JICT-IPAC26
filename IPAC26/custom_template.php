<?php

/* by Nicolas.delerue@ijclab.in2p3.fr

This page returns a link to a customised template based on the contribution ID.

28.01.2026 Creation

*/


if (str_contains($_SERVER["QUERY_STRING"],"debug")){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} //if debug

require( '../config.php' );
require_lib( 'jict', '1.0' );
require_lib( 'indico', '1.0' );
require( 'ipac26_tools.php' );

require_once('custom_template_functions.php');

$cfg =config( 'IPAC26', false, false );
$cfg["allow_roles"]=[];
$cfg['verbose'] =0;
//$cfg['disable_cache'] = true;

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;



$T =new TMPL( $cfg['template'] );
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



if ($_SERVER["QUERY_STRING"]) {
    parse_str($_SERVER["QUERY_STRING"], $queryArray);
    //print($_SERVER["QUERY_STRING"]."\n");
    //print_r($queryArray);
}
if (str_contains($_SERVER["QUERY_STRING"],"contribution_id")){
    $contribution_id=$queryArray["contribution_id"];
} else if (str_contains($_SERVER["QUERY_STRING"],"contribution_code")){
    $contribution_code=strtoupper($queryArray["contribution_code"]);
    $dictionnary=file_read_json($cws_config['global']['data_path']."/contribs_dictionnary.json",true);
    if (array_key_exists($contribution_code, $dictionnary["contribution_code"])){
        $contribution_id=$dictionnary["contribution_code"][$contribution_code];
        $content .="Contribution ID: $contribution_id <BR/>\n";
        print("Contribution ID: $contribution_id <BR/>\n");
    } else {
        $content .="<b>Contribution code $contribution_code not found.</b><BR/>\n";
    }

} else {
    $contribution_id=false;
}



/*
if (array_search("ADM",$user["roles"])){
    $content .="You are an administrator<BR/>\n";
}
    */
//$Indico->cfg['disable_cache'] =true;


if (!($contribution_id)){
    //$content .="No contribution ID passed.<BR/>\n";
    $content .="<center><H2>Contributions for ".$user["first_name"]." ".$user["last_name"]."</H2></center>\n";
    $req =$Indico->request( "/event/{id}/contributions/mine", 'GET', false, array( 'return_data' =>true, 'quiet' =>true , 'disable_cache' =>true ) );
    print("<!--- req size: ".strlen(json_encode($req))." bytes --->\n");
    $matchtxt='#/event/([0-9]+)/contributions/([0-9]+)/\"#';
    $returnValue = preg_match_all($matchtxt, $req, $matches);
    print("<!--- \n");
    var_dump($matches);
    print("--->\n");
    $matchtxt='#/event/([0-9]+)/contributions/([0-9]+)/\">(.+)</a>#';
    $returnValue = preg_match_all($matchtxt, $req, $matches);
    print("<!--- \n");
    var_dump($matches);
    print("--->\n");
    $contribs=[];
    for ($icount=0;$icount<count($matches[0]);$icount++){
        $contribs[$matches[2][$icount]]=$matches[3][$icount];
    }
    if (count($contribs)==0){
        $content .="<b>No contribution found for ".$user["first_name"]." ".$user["last_name"].".</b><BR/>\n";
        $content .="Please go to <A HREF='https://indico.jacow.org/event/".$cws_config['global']['indico_event_id']."/contributions/mine' TARGET='_BLANK'> the indico list of your contributions </A> and check the 7 digits code in parentheses after the contribution title. Please enter it below to get the associated template:<BR/>\n";
        $content .="<form method='GET' action='custom_template.php'>\n";
        $content .="<input type='text' name='contribution_code' placeholder='Contribution code (7 digits)' required pattern='[a-zA-Z0-9]{7}' />\n";
        $content .="<input type='submit' value='Get template' />\n";
        $content .="</form>\n";
    } else {
        if (count($contribs)==1){
            $content .="".count($contribs)." contribution found for ".$user["first_name"]." ".$user["last_name"].".<BR/>\n";
        } else {
            $content .="".count($contribs)." contribution(s) found for ".$user["first_name"]." ".$user["last_name"].".<BR/>\n";
        }
        $content .="Here is your list of contributions, please select the one for which you would like to download a template:<BR/>\n";
        $content .="<ul>\n";
        foreach($contribs as $id => $title){
            $content .="<li><A HREF='custom_template.php?contribution_id=".$id."'>".$id.": ".$title."</A></li>\n";
        }
        $content .="</ul>\n";
    }
    $T->set( 'title', "Contributions of ".$user["first_name"]." ".$user["last_name"] ." at IPAC'26");
    $content .="<BR/>\n";
    $content .="<BR/>\n";
    $content .="<BR/>\n";

} else {
    $req =$Indico->request( "/event/{id}/contributions/".$contribution_id.".json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true ) );
    if ((array_key_exists("code", $req))&&(strlen($req["code"])>0)){
        $contribution_code=$req["code"];
    } else {
        $contribution_code="contribution_".$contribution_id;
        $content .="<b>This contribution does not yet have a code. Please contact the organizers.</b>\n";
    }
    $content .="<center><H2>Custom template for contribution ".$contribution_code."</H2></center>\n";
    $content .="<center><H4>".$req["title"]."</H4></center>\n";
    $T->set( 'title', "Custom template - ".$contribution_code );
    $content .="Indico link to this contribution: <A HREF='https://indico.jacow.org/event/".$cws_config['global']['indico_event_id']."/contributions/".$contribution_id."/' TARGET='_BLANK'>Contribution $contribution_code (ID: $contribution_id)</A>.<BR/>\n";
    $content .="<BR/>\n";

    //check that the user is allowed to access this contribution
    $indico_link="<A HREF='https://indico.jacow.org/event/".$cws_config['global']['indico_event_id']."/contributions/".$contribution_id."/' TARGET='_BLANK'>Indico entry for contribution $contribution_code</A>";
    $allowed=false;
    foreach($req["persons"] as $author){
        if ($author["email"]==$user["email"]){
            $allowed=true;
            break;
        }
    }
    if (!($allowed)){
        $content .="<b>You are not allowed to access this contribution. Please check that you are among the contributors of this contribution. If necessary, ask the submitte to update the $indico_link.</b>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $T->set( 'content', $content );
        echo $T->get();
        exit;
    }

    if ($_POST){
        if (array_key_exists("action", $_POST)){
            if ($_POST["action"]=="update_title"){
                $contribs_qa_data=file_read_json(  $cws_config['global']['data_path']."/contribs_qa.json",true);
                $contribs_qa_data[$contribution_id]["title"]["sentence_case"]=$_POST["sentence_case"];
                $contribs_qa_data[$contribution_id]["title"]["upper_case"]=$_POST["upper_case"];
                $contribs_qa_data[$contribution_id]["title"]["entered_manually"]="manual_update_by_user";
                $content .="<b>Titles of contribution $contribution_code (ID: $contribution_id) updated manually.</b><BR/>\n";
                $content .="Sentence case: ".$contribs_qa_data[$contribution_id]["title"]["sentence_case"]."<BR/>\n";
                $content .="Upper case: ".$contribs_qa_data[$contribution_id]["title"]["upper_case"]."<BR/>\n";
                $contribs_qa_data[$contribution_id]["title"]["date"]=time();
                file_put_contents($cws_config['global']['data_path']."/contribs_qa.json",json_encode($contribs_qa_data));
                $ret=update_contribution_title($contribution_id,$contribs_qa_data[$contribution_id]["title"]["sentence_case"]);
                if ($ret["value"]){
                    $content .=$ret["content"];
                    $req["title"]=$contribs_qa_data[$contribution_id]["title"]["sentence_case"];
                } else {
                    $content .=$ret["content"];
                }
                $content .="<BR/>\n";
            } //if ($_POST["action"]=="update_title")
        } //if (array_key_exists("action", $_POST)){
    }//POST form


    $contribs_qa_data=file_read_json(  $cws_config['global']['data_path']."/contribs_qa.json",true);
    if (str_contains($_SERVER["QUERY_STRING"],"update_title")){
        if ((array_key_exists($contribution_id, $contribs_qa_data))&&(array_key_exists("title", $contribs_qa_data[$contribution_id]))&&($contribs_qa_data[$contribution_id]["title"]["sentence_case"]==$req["title"])){
            $sentece_case=$contribs_qa_data[$contribution_id]["title"]["sentence_case"];
            $upper_case=$contribs_qa_data[$contribution_id]["title"]["upper_case"];
        } else {
            $sentece_case=ucfirst($req["title"]);
            $upper_case=strtoupper($req["title"]);
        } 
        $content .="<b>Updating the title of contribution $contribution_code (ID: $contribution_id) manually.</b><BR/>\n";
        $content .="<form method='POST' action='custom_template.php?contribution_id=".$contribution_id."'>\n";
        $content .="<INPUT type='hidden' name='contribution_id' value='".$contribution_id."'>\n";        
        $content .="<INPUT type='hidden' name='action' value='update_title'>\n";        
        $content .="Title of your contribution in <A HREF='https://www.scribbr.com/academic-writing/sentence-case/'>sentence case</A> (for indico):  <input type='text' name='sentence_case' size=100 value='".$sentece_case."'><BR/>\n";        
        $content .="Title in upper case (for the paper): <input type='text' name='upper_case' size=100 value='".$upper_case."'><BR/>\n";
        $content .="<input type='submit' value='Save modified title'><BR/>\n";
        $content .="</form>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $content .="<BR/>\n";
        $T->set( 'content', $content );
        echo $T->get();
        exit;
    }
    if ((array_key_exists($contribution_id, $contribs_qa_data))&&(array_key_exists("title", $contribs_qa_data[$contribution_id]))&&($contribs_qa_data[$contribution_id]["title"]["sentence_case"]==$req["title"])){
            $content .="Title of your contribution in <A HREF='https://www.scribbr.com/academic-writing/sentence-case/'>sentence case</A> (for indico): ".$contribs_qa_data[$contribution_id]["title"]["sentence_case"]."<BR/>\n";
            $content .="Title in upper case (for the paper): ".$contribs_qa_data[$contribution_id]["title"]["upper_case"]."<BR/>\n";
    } else {
        $content .="<b>The title of your contribution has not yet been validated by an editor or has been modified after validation.</b><BR/>\n";
        $content .="Title of your contribution in <A HREF='https://www.scribbr.com/academic-writing/sentence-case/'>sentence case</A> (for indico): ".$req["title"]."<BR/>\n";
        $content .="Title in upper case (for the paper): ".strtoupper($req["title"])."<BR/>\n";
        $content .="<BR/>\n";
    }
    $content .="<i>In <b>sentence case</b>, please ensure that proper capitalization is used for proper nouns, acronyms and units.</i><BR/>\n";
    $content .="<i>In <b>upper case</b>, please ensure that acronyms or units that contain lower case have not been capitalized.</i><BR/>\n";
    $content .="If this is incorrect, please click <A HREF='custom_template_file.php?contribution_id=".$contribution_id."&update_title=1'>here to update the title manually</A>.<BR/>\n";
    $content .="<BR/>\n";
    //var_dump($req);
    $author_block =create_title_author_block($req,$indico_link);
    $content .=$author_block["html"];


    $content .="IMPORTANT:<BR/>If any of the details above are incorrect, please <A HREF='https://indico.jacow.org/event/".$cws_config['global']['indico_event_id']."/contributions/".$contribution_id."/' TARGET='_BLANK'>update the contribution in indico.</A><BR/>\n";
    $content .="The information transmitted to publication databases are extracted from indico, not from the paper itself, so it is important that the details in the paper and in indico match.<BR/>\n";
    $content .="If you need to update any details later, please download again the template and copy and paste the content of the paper in the new template.<BR/>\n";
    $content .="<BR/>\n";
    $content .="<BR/>\n";
    $content .="<table width=80% border=1>\n";
    $content .="<tr><td colspan=3> <center><b>Templates for this contribution</b></center></td></tr>\n";
    $content .="<tr><td width=80% colspan=2><center>Latex files</center></td><td width=20% rowspan=2> <center>MS Word file </center></td></tr>\n";
    $content .="<tr><td width=40%> <center>Using bibtex </center></td><td width=40%> <center>Not using bibtex</center></td></tr>\n";
    $content .="<tr>";
    $content .="<td>";
    $content .="<i>You need to download these files and place them in the same directory:</i><BR/>\n";
    $content .="<A HREF='custom_template_file.php?contribution_id=".$contribution_id."&type=latex'>Main LaTeX file</A><BR/>\n";    
    $content .="<A HREF='https://raw.githubusercontent.com/JACoW-org/JACoW_Templates/master/LaTeX/jacow.cls'>jacow.cls (JACoW class file)</A><BR/>\n";    
    $content .="</td>";
    $content .="<td>";
    $content .="<i>You need to download these files and place them in the same directory:</i><BR/>\n";
    $content .="<A HREF='custom_template_file.php?contribution_id=".$contribution_id."&type=latex-bib'>Main LaTeX file</A><BR/>\n";    
    $content .="<A HREF='https://raw.githubusercontent.com/JACoW-org/JACoW_Templates/master/LaTeX/jacow.cls'>jacow.cls (JACoW class file)</A><BR/>\n";    
    $content .="<A HREF='https://raw.githubusercontent.com/JACoW-org/JACoW_Templates/master/LaTeX/jacow.bbx'>jacow.bbx (JACoW's biblatex bibliography style)</A><BR/>\n";    
    $content .="<A HREF='https://raw.githubusercontent.com/JACoW-org/JACoW_Templates/master/LaTeX/jacow.cbx'>jacow.cbx (JACoW's cite style)</A><BR/>\n";    
    $content .="</td>";
    $content .="<td>";
    $content .="<A HREF='custom_template_file.php?contribution_id=".$contribution_id."&type=word'>MS Wordfile</A><BR/>\n";    
    $content .="</td>";
    $content .="<tr><td colspan=2>Note:<i> If your LaTeX install is more than a few years old, you may be using an old version of <tt>siunitx</tt>. In this case you will need to add the following command after <tt>\begin{document}</tt>:</i><BR/><tt>\ifdefined\qty  \\else \\newcommand{\qty}{\SI} \\fi</tt></td><td></td></tr>\n";
    $content .="</tr>";
    
    /*
    $content .="<tr>";
    $content .="<td>";
    $content .="<code background-color=#f0f0f0 >\n";
    $content .=html_encode($author_block["latex"]);
    $content .="</code>\n";
    $content .="</td>";
    $content .="<td>";
    $content .="</td>";
    $content .="</tr>";
    */
    $content .="</table>\n";

    //var_dump($user);


} // if there is a contribution ID

$content .="<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";
$content .="<BR/>\n";


$T->set( 'content', $content );
/*
$T->set( 'errors', $errors );
$T->set( 'to_fix', $to_fix );
$T->set( 'known', $known );
$T->set( 'to_notify', $to_notify );
$T->set( 'event_id', $cws_config['global']['indico_event_id'] );
*/
$T->set( 'user_name', $_SESSION['indico_oauth']["user"]["full_name"]);
$T->set( 'user_first_name', $_SESSION['indico_oauth']["user"]["first_name"]);
$T->set( 'user_last_name',$_SESSION['indico_oauth']["user"]["last_name"]);
echo $T->get();

?>
