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
//require( 'ipac26_tools.php' );

require_once('custom_template_functions.php');

$cfg =config( 'IPAC26', false, false );
$cfg["allow_roles"]=[];
$cfg['verbose'] =0;
//$cfg['disable_cache'] = true;

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;

if ($_SERVER["QUERY_STRING"]) {
    parse_str($_SERVER["QUERY_STRING"], $queryArray);
    //print($_SERVER["QUERY_STRING"]."\n");
    //print_r($queryArray);
}
if (str_contains($_SERVER["QUERY_STRING"],"contribution_id")){
    $contribution_id=$queryArray["contribution_id"];
} else {
    $contribution_id=false;
}




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
    //var_dump($req);
    $matchtxt='#/event/([0-9]+)/contributions/([0-9]+)/\">(.+)</a>#';
    $returnValue = preg_match_all($matchtxt, $req, $matches);
    $contribs=[];
    for ($icount=0;$icount<count($matches[0]);$icount++){
        $contribs[$matches[2][$icount]]=$matches[3][$icount];
    }
    $content .="Here is your list of contributions, please select the one for which you would like to download a template:<BR/>\n";
    $content .="<ul>\n";
    foreach($contribs as $id => $title){
        $content .="<li><A HREF='custom_template.php?contribution_id=".$id."'>".$id.": ".$title."</A></li>\n";
    }
    $content .="</ul>\n";
    $T->set( 'title', "Contributions of ".$user["first_name"]." ".$user["last_name"] ." at IPAC'26");
    $content .="<BR/>\n";
    $content .="<BR/>\n";
    $content .="<BR/>\n";

    //parse https://indico.jacow.org/event/37/contributions/mine for "/event/37/contributions/149/"
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
    $content .="Indico link to this contribution: <A HREF='https://indico.jacow.org/event/".$cws_config['global']['indico_event_id']."/contributions/".$contribution_id."/' TARGET='_BLANK'>Contribution $contribution_code (ID: $contribution_id)</A><BR/>\n";

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
        $T->set( 'content', $content );
        echo $T->get();
        exit;
    }
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
    $content .="<tr><td colspan=2>Note:<i> If your LaTeX install is more than a few years old, you may be using an old version of <tt>siunitx<\tt>. In this case you will need to add the following command after <tt>\begin{document}</tt>:</i><BR/><tt>\ifdefined\qty  \\else \\newcommand{\qty}{\SI} \\fi</tt></td><td></td></tr>\n";
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
