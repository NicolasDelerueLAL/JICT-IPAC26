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

$latexfile="templates/jacow_latex_template.tex";
$latexbibfile="templates/jacow_latex_template_bib.tex";
$wordfile="templates/JACoW_MSWord_ipac26_custom.docx";
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
if ((str_contains($_SERVER["QUERY_STRING"],"contribution_id"))&&(str_contains($_SERVER["QUERY_STRING"],"type"))) {
    $contribution_id=$queryArray["contribution_id"];
    $type=$queryArray["type"];
} else {
    header("HTTP/1.1 303 See Other");
    header("Location: http://".$_SERVER['HTTP_HOST'].str_replace("custom_template_file.php","custom_template.php",$_SERVER['REQUEST_URI']));
}

$req =$Indico->request( "/event/{id}/contributions/".$contribution_id.".json", 'GET', false, array( 'return_data' =>true, 'quiet' =>true, 'disable_cache' =>true , 'use_session_token' => true ) );
if ((array_key_exists("code", $req))&&(strlen($req["code"])>0)){
    $contribution_code=$req["code"];
} else {
    $contribution_code="contribution_".$contribution_id;
    $content .="<b>This contribution does not yet have a code. Please contact the organizers.</b>\n";
}
//var_dump($req);
$author_block =create_title_author_block($req);

if (($type=="latex")||($type=="latex-bib")){
    if ($type=="latex"){
        $file=$latexfile;
    } else {
        $file=$latexbibfile;
    }
    $fp = @fopen($file, "r");

    header("Content-Description: File Transfer");
    header('Content-Disposition: attachment; filename="' . $contribution_code . '.tex"');
    header('Content-Type: application/x-latex');
    header('Content-Transfer-Encoding: ascii');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    if ($fp) {
        $skip=false;
        $author_block_sent=false;
        while (($buffer = fgets($fp, 4096)) !== false) {
            if (((str_contains($buffer,"\\title"))&&(!(str_contains($buffer,"\\titleblock"))))||(str_contains($buffer,"%%%%% Title - Author block"))){
                //echo "Skipped line".$buffer." skipped\n";
                $skip=true;
                if (!$author_block_sent){
                    $author_block_sent=true;
                    echo php_encode($author_block["latex"]);
                }
            }
            if (!$skip){
                echo $buffer;
            } else {
                if (str_contains($buffer,"\\end{abstract}")){
                    //echo "Skipped line".$buffer." skipped\n";
                    $skip=false;
                }
            }
        }
        fclose($fp);
    }
} else if ($type=="word"){
    $filetmp='../tmp/'.$contribution_code.'.docx';
    copy($wordfile,$filetmp);

    $zip = new ZipArchive;
    $fileToModify = 'word/document.xml';
    if ($zip->open($filetmp) === TRUE) {
        //Read contents into memory
        $oldContents = $zip->getFromName($fileToModify);

        $matchtxt='#{([a-zA-Z]+)}#';
        $returnValue = preg_match_all($matchtxt, $oldContents, $matches, PREG_OFFSET_CAPTURE);

        $copiedTo=0;
        $newContents = "";
        $authorAdded=false;
        $newContextPositions=[];
        
        foreach($matches[1] as $thematch){
            if ($thematch[0]=="title"){
                $newContents .= substr($oldContents,$copiedTo,$thematch[1]-1-$copiedTo);
                $newContextPositions[]=mb_strlen($newContents);
                $newContents .= $author_block["word"]["title"];
                $copiedTo = $thematch[1]+6; // Move past the inserted content 
            }
            if ($thematch[0]=="authors"){
                $newContents .= substr($oldContents,$copiedTo,$thematch[1]-1-$copiedTo);
                $newContextPositions[]=mb_strlen($newContents);
                $newContents .= $author_block["word"]["authors"];
                //print("Authors block inserted: ".$author_block["word"]["authors"]."\n");
                //die("here");
                $copiedTo = $thematch[1]+8; // Move past the inserted content 
            }
            if ($thematch[0]=="footnote"){
                
                $newContents .= str_replace('cx="2962275" cy="342900"','cx="2962275" cy="'. ($author_block["word"]["footnote_lines"]*220000). '"', 
                str_replace('<wp:positionV relativeFrom="page"><wp:posOffset>8994775</wp:posOffset>','<wp:positionV relativeFrom="page"><wp:posOffset>'.(8994775-($author_block["word"]["footnote_lines"]*100000)).'</wp:posOffset>',
                substr($oldContents,$copiedTo,$thematch[1]-1-$copiedTo)));
                $newContextPositions[]=mb_strlen($newContents);
                $newContents .= $author_block["word"]["footnote"];
                $copiedTo = $thematch[1]+9; // Move past the inserted content 
            }
            if ($thematch[0]=="abstract"){
                $newContents .= substr($oldContents,$copiedTo,$thematch[1]-1-$copiedTo);
                $newContextPositions[]=mb_strlen($newContents);
                $newContents .= $author_block["word"]["abstract"];
                $copiedTo = $thematch[1]+9; // Move past the inserted content 
            }
            /*
            if ($thematch[0]=="footnote"){
                $nlines=6;
                $newContents .= str_replace('cx="2962275" cy="268605"','cx="2962275" cy="'. ($nlines*168605). '"', 
                    str_replace('<wp:positionV relativeFrom="page"><wp:posOffset>9070340</wp:posOffset>','<wp:positionV relativeFrom="page"><wp:posOffset>'.(9070340-($nlines*108605)).'</wp:posOffset>',
                    substr($oldContents,$copiedTo,$thematch[1]-1-$copiedTo)));
                $newContextPositions[]=mb_strlen($newContents);
                $newContents .= '* Funding line 1</w:t> <w:br/><w:t>  line 2</w:t> <w:br/><w:t>  line 3</w:t> <w:br/><w:t>  line 4</w:t> <w:br/><w:t>  line 5</w:t> <w:br/><w:t> 1 email address';
                $copiedTo = $thematch[1]+9; // Move past the inserted content 
            }

            /*
            if ($thematch[0]=="abstract"){
                //tags_counter($copiedTo,$thematch[1],$oldContents);
                //$mypos = strpos($oldContents,"></w:rPr>",$thematch[1]+1);
                $newContents = substr($oldContents,$copiedTo,$thematch[1]+12).'abstract abstract abstract abstract abstract abstract abstract abstract abstract abstract ';
                $copiedTo = $thematch[1]+12; // Move past the inserted content 
            }
            */
        }
        $newContents .= substr($oldContents,$copiedTo);



        //Delete the old...
        $zip->deleteName($fileToModify);
        //Write the new...
        $zip->addFromString($fileToModify, $newContents);
        //And write back to the filesystem.
        $zip->close();
    } else {
        echo 'Opening docx template failed';
    }

    header("Content-type: application/zip"); 
    header("Content-Disposition: attachment; filename=".str_replace('../tmp/','',$filetmp));
    header("Content-length: " . filesize($filetmp));
    header("Pragma: no-cache"); 
    header("Expires: 0"); 
    readfile("$filetmp");
    unlink($filetmp);


} else {
    print("Reqested type not supported <BR/>\n");
    print("Return to the <A HREF='custom_template.php?contribution_id=".$contribution_id."'>contribution page</A><BR/>\n");
}

?>
