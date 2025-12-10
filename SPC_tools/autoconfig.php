<?php

/* Created by Nicolas.Delerue@ijclab.in2p3.fr
2025.11.12 1st version

This page gives links to several tools needed by SPC.

*/
if (str_contains($_SERVER["QUERY_STRING"],"debug")){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} //if debug on

//print('autoconfig');

$tracksfile=$cws_config['global']['data_path']."/conference_tracks.json";

$cws_config['SPC_tools']['first_question_id'] =19; //to find this value check an abstract on which you voted
$cws_config['SPC_tools']['second_question_id'] = 20;
//parse the first abstract of asbtracts.json view-source:https://indico.jacow.org/event/37/abstracts/115/ and look for "question-index"


if (!(array_key_exists('tracks', $cws_config['SPC_tools']))){
    //load tracks file
    $tracks=file_read_json( $tracksfile, true );
    if ((!($tracks))||(str_contains($_SERVER["QUERY_STRING"],"reload_config"))){
        if (!($tracks)){
            //if unable to read the tracks file
            print("<BR/><BR/><BR/>Unable to read the tracks, fetching them again!");
        } else {
            print("<BR/><BR/><BR/>Reloading the tracks config!");
        }
        $tracks=[];
        //parse /event/95/manage/tracks/ to find the track labels (requires sufficient rights) and saves them in a json file.

        $req =$Indico->request( sprintf("/event/%s/manage/tracks/", $cws_config['global']['indico_event_id'] ), 'GET', false, array(  'return_data' =>true, 'quiet' =>true ) );
        $matches = null;
        $returnValue = preg_match_all("#<li class=\"track-row i-box\" data-id=\"(.*)\">.*\n.*\n.*\n.*<span class=\"i-box-title\">(.*)</span>#", $req , $matches);
        $labelMatches = null;
        $labelReturnValue = preg_match_all("#<span class=\"i-label small\">.*\n(.*)\n.*</span>#", $req , $labelMatches);
        $tracks=[];
        if ($returnValue!=$labelReturnValue){
            print("Error returnValue!=labelReturnValue");
            die("Unable to identify tracks");
        } else {
            for($iloop=0;$iloop<$returnValue;$iloop++){
                if (str_contains($matches[2][$iloop],trim($labelMatches[1][$iloop]))){
                    $tracks[]=array( "id"=> $matches[1][$iloop], "name" => $matches[2][$iloop], "code" => trim($labelMatches[1][$iloop]));
                } else {
                    print("Error code (".trim($labelMatches[1][$iloop]).")not in name (".$matches[2][$iloop].")");
                    die("Unable to identify tracks");
                }
            }
        }
        print("Tracks:<BR/>\n");
        print_r($tracks);
        print("<BR/>\n");
        $fwtracks=file_write_json($tracksfile,$tracks);
    } //re-read tracks config
    //print_r($tracks);
    $cws_config['SPC_tools']['tracks']=$tracks;
} // if tracks not defined
?>
