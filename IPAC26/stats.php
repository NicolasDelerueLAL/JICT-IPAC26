<?php

/* Created by Nicolas.Delerue@ijclab.in2p3.fr
2026.01.04 1st version

This page display statistical data for IPAC'26

*/
if (str_contains($_SERVER["QUERY_STRING"],"debug")){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} //if debug on


require( '../config.php' );
require_lib( 'jict', '1.0' );
require_lib( 'indico', '1.0' );
require( 'ipac26_tools.php' );

$cfg =config( 'SPC_tools', false, false );
$cfg['verbose'] =0;

$Indico =new INDICO( $cfg );

$user =$Indico->auth();
if (!$user) exit;

$abstracts_qa_data=file_read_json( $cws_config['global']['data_path']."/abstracts_qa.json", true );


$Indico->load();

$_rqst_cfg=[];
//$_rqst_cfg['disable_cache'] =true;
$data_key= $Indico->request( '/event/{id}/manage/abstracts/abstracts.json', 'GET', false, $_rqst_cfg);

$stats=[];
$stats["state"]=[];
$stats["state"]["label"]="Abstracts state";
$stats["MC"]=[];
$stats["track"]=[];
$stats["track"]["label"]="Number of abstracts by track";
$stats["region"]=[];
$stats["MC_by_region"]=[];
$stats["MC_by_region"]["label"]="Number of abstracts by MC and by region";
$stats["MC_by_region"]["normalize"]=true;
$stats["MC_by_gender"]=[];
$stats["MC_by_gender"]["label"]="Number of abstracts by MC and by gender";
$stats["MC_by_gender"]["normalize"]=true;
$stats["Gender_by_region"]=[];
$stats["countries"]=[];

$stats["speaker_gender"]=[];
$stats["speaker_gender"]=array("male" => 0, "female" => 0 , "unsure" => 0 );


$stats_data["submitters"]=[];
$stats_data["speakers"]=[];


$stats["region"]=array("EMEA" => 0 , "Asia" => 0,  "Americas" => 0 );
$stats["Gender_by_region"]=array("EMEA" => 0 , "Asia" => 0,  "Americas" => 0 );
foreach(array_keys($stats["Gender_by_region"]) as $key){
    $stats["Gender_by_region"][$key]=array("male" => 0, "female" => 0 , "unsure" => 0 );
}
$stats["Gender_by_region"]["label"]="Number of abstracts by region and by gender";
$stats["Gender_by_region"]["normalize"]=true;
for($imc=1;$imc<=8;$imc++){
    $stats["MC"]["MC".$imc]=0;
    $stats["MC_by_region"]["MC".$imc]=array("EMEA" => 0 , "Asia" => 0,  "Americas" => 0 );
    $stats["MC_by_gender"]["MC".$imc]=array("male" => 0, "female" => 0 , "unsure" => 0 );
}
foreach ($Indico->data[$data_key]['abstracts'] as $abstract) {
    if (!(array_key_exists($abstract["state"],$stats["state"]))){
        $stats["state"][$abstract["state"]]=0;
    }
    $stats["state"][$abstract["state"]]+=1;
    if ($abstract["state"]=="submitted"){
        $abstract["MC"]=substr($abstract["submitted_for_tracks"][0]["code"],0,3);
        $abstract["track"]=$abstract["submitted_for_tracks"][0]["code"]." - ".$abstract["submitted_for_tracks"][0]["title"];
        
        $stats["MC"][$abstract["MC"]]+=1;

        if (!(array_key_exists($abstract["submitted_for_tracks"][0]["code"],$stats["track"]))){
            $stats["track"][$abstract["submitted_for_tracks"][0]["code"]]=0;
        }
        $stats["track"][$abstract["submitted_for_tracks"][0]["code"]]+=1;

        $submitter_country="Unknown";
        $submitter_region="Unknown";
        if ($abstract["submitter"]["affiliation_meta"]){
            $submitter_country=$abstract["submitter"]["affiliation_meta"]["country_name"];
            $submitter_region=get_region($abstract["submitter"]["affiliation_meta"]["country_code"]);
        } else {
            foreach($abstract["persons"] as $person){
                if ($person["affiliation_link"]){
                    if ($person["affiliation_link"]["country_name"]){
                        $submitter_country=$person["affiliation_link"]["country_name"];
                        $submitter_region=get_region($person["affiliation_link"]["country_code"]);
                    }
                }
            }
        }
        /*
        if ($submitter_region=="Unknown"){
            if (!($submitter_country=="Unknown")){
                print("\n $submitter_country is in no region\n\n");
            } else {
                if ($abstract["submitter"]["affiliation_meta"]){
                    var_dump($abstract);
                }
            }
        }
        */        
        if (!(array_key_exists($submitter_country,$stats["countries"]))){
            $stats["countries"][$submitter_country]=0;
        }
        $stats["countries"][$submitter_country]+=1;
        $stats["region"][$submitter_region]+=1;
        $stats["MC_by_region"][$abstract["MC"]][$submitter_region]+=1;

        if (!(array_key_exists($abstract["submitter"]["full_name"],$stats_data["submitters"]))){
            $stats_data["submitters"][$abstract["submitter"]["full_name"]]=0;
        }
        $stats_data["submitters"][$abstract["submitter"]["full_name"]]+=1;

        $speaker_name=$abstracts_qa_data[$abstract["id"]]["gender"]["speaker_first_name"]." ".$abstracts_qa_data[$abstract["id"]]["gender"]["speaker_last_name"];
        $speaker_gender=$abstracts_qa_data[$abstract["id"]]["gender"]["speaker_likely_gender"];

        if (!(array_key_exists($speaker_name,$stats_data["speakers"]))){
            $stats_data["speakers"][$speaker_name]=0;
        }
        $stats_data["speakers"][$speaker_name]+=1;

        $stats["speaker_gender"][$speaker_gender]+=1;


        $stats["MC_by_gender"][$abstract["MC"]][$speaker_gender]+=1;
        $stats["Gender_by_region"][$submitter_region][$speaker_gender]+=1;


        //print_r($abstract["submitted_for_tracks"][0]);
        //$abstract["MC_track"]=$abstract["MC"]." - ".$abstract["submitted_for_tracks"][0]["code"].": ".$abstract["submitted_for_tracks"][0]["title"];
        //$abstract["MC_track"]=$abstract["MC"]." - ".$abstract["submitted_for_tracks"][0]["code"];
    } //if submitted
    else {
        //echo "Skipping abstract id ".$abstract["id"]." state ".$abstract["state"]."\n";
        continue;
    }
} //for each abstract

$stats["speakers"]=[];
$stats["speakers"]["label"]="Papers per speaker";
$stats["submitters"]=[];
$stats["submitters"]["label"]="Papers per submitter";
$maxnum=8;
for($iloop=1;$iloop<$maxnum;$iloop++){    
    $stats["speakers"][$iloop]=0;
    $stats["submitters"][$iloop]=0;
}
$stats["speakers"]["more"]=0;
$stats["submitters"]["more"]=0;
foreach (array_keys($stats_data["speakers"]) as $entry){
    if ($stats_data["speakers"][$entry]<$maxnum){
        $stats["speakers"][$stats_data["speakers"][$entry]]+=1;
    } else {
        $stats["speakers"]["more"]+=1;
    }
}
foreach (array_keys($stats_data["submitters"]) as $entry){
    if ($stats_data["submitters"][$entry]<$maxnum){
        $stats["submitters"][$stats_data["submitters"][$entry]]+=1;
    } else {
        $stats["submitters"]["more"]+=1;
    }
}

//require( 'autoconfig.php' );
$cfg['template']="template.html";

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

$jscontent="
<script>
google.charts.load('current', {packages: ['corechart', 'bar']});
";

foreach(array_keys($stats) as $key){
    if (array_key_exists("normalize",$stats[$key])){
        print("\nNormalize ".$key);
        next($stats[$key]);
        if (is_array(next($stats[$key]))){
            $stats[$key."_normalized"]=[];
            print (" - table created.\n");
            foreach(array_keys($stats[$key]) as $entry){
                if ((!($entry=="label"))&&(!($entry=="normalize"))){
                    $stats[$key."_normalized"][$entry]=[];
                    $sum=0;
                    $subkeys=array_keys(current($stats[$key]));
                    foreach($subkeys as $skey){
                        $sum+=$stats[$key][$entry][$skey];
                    }
                    foreach($subkeys as $skey){
                        $stats[$key."_normalized"][$entry][$skey]=$stats[$key][$entry][$skey]/$sum;
                    }
                } else if ($entry=="label") {
                    $stats[$key."_normalized"]["label"]=$stats[$key]["label"]." (normalized)";
                }
            } //foreach
        } else {
            $content.="Nornmalization of $key requested but it is not a 2D array<BR/>";
        }
        reset($stats[$key]);
    }
}
foreach(array_keys($stats) as $key){
    //print("key $key \n");
    //var_dump($stats[$key]);
    $content .="<P><center>\n";
    if (array_key_exists("label",$stats[$key])){
        $content .="<h3>".$stats[$key]["label"]."</h3>\n";
        next($stats[$key]);
    } else {
        $content .="<h3>$key</h3>\n";
    }
    if (array_key_exists("normalize",$stats[$key])){
        next($stats[$key]);
    }
    $content .="</center>\n";

    $content .="<div class=\"table-wrap\"><table id=\"table_$key\" class=\"stat_table\">\n";
    $content.="  <thead>\n";
    $content.="  <tr>\n";
    $subkeys=false;
        $jscontent.="
        google.charts.setOnLoadCallback(drawStacked_$key);

        function drawStacked_$key() {
            var data_$key = new google.visualization.DataTable();
            data_$key.addColumn('string', '');
        ";
    if (is_array(current($stats[$key]))){
        //print("here");
        $subkeys=array_keys(current($stats[$key]));
        $content.="  <th></th>\n";
        foreach($subkeys as $skey){
        //print("here $skey");
            $content.="  <th>$skey</th>\n";
                $jscontent.="
                data_$key.addColumn('number', '$skey');
                ";
            }
    } else {
        if (count($stats[$key])<10){
            ksort($stats[$key]);
        } else{
            arsort($stats[$key]);
        }
        $content.="  <th></th>\n";
        $content.="  <th></th>\n";
        $jscontent.="
        data_$key.addColumn('number', '$key');
        ";
    }
    $content.="  </tr>\n";
    $content.="</thead>";
    $content.="  <tbody>\n";
    $jscontent.="
    data_$key.addRows([
    ";
    $ientry=1;
    foreach(array_keys($stats[$key]) as $entry){
        if ((!($entry=="label"))&&(!($entry=="normalize"))){
            $content.="  <tr>\n";
            $content.="  <td>$entry</td>\n";
            $jscontent.="[ '$entry' ";
            $ientry+=1;
            if (is_array($stats[$key][$entry])){
                foreach(array_keys($stats[$key][$entry]) as $skey){
                    $content.="  <td>".$stats[$key][$entry][$skey]."</td>\n";
                    $jscontent.=", ".$stats[$key][$entry][$skey]; 
                }
            } else {
                $content.="  <td>".$stats[$key][$entry]."</td>\n";
                $jscontent.=", ".$stats[$key][$entry]; 
            }
            $content.="  </tr>\n";
            $jscontent.="],";
        } //entry is not label
    }
        $jscontent.="]); ";
    $content.="  </tbody>\n";
    $content.="</table></div>\n";
    $jscontent.="
    var options_$key = {
    ";
    if ($key=="track"){
    $jscontent.="
        width: 2000,
        ";
    } else if ($key=="countries"){
    $jscontent.="
        width: 2000,
        ";
    } else {
    $jscontent.="
        width: 600,
        ";
    }
    $jscontent.="
        height: 400,";
    if (array_key_exists("label",$stats[$key])){
    $jscontent.="
        title: '".$stats[$key]["label"]."',";
    } else {
    $jscontent.="
        title: '$key',";
    }

    $jscontent.="
        isStacked: true,
        bar: {groupWidth: \"95%\"},
        vAxis: {
        title: 'Abstracts submitted'
        }
    };
        ";
    $jscontent.="
    var chart_$key = new google.visualization.ColumnChart(document.getElementById('chart_div_$key'));
     google.visualization.events.addListener(chart_$key, 'ready', function () {
        //console.log(chart_$key.getImageURI());
        //document.getElementById('chart_div_$key').outerHTML = '<a href=\"' + chart_$key.getImageURI() + '\">Printable version</a>';
        //document.getElementById('chart_div_$key').innerHTML = '<img src=\"' + chart_$key.getImageURI() + '\"><BR/><a href=\"' + chart_$key.getImageURI() + '\">Printable version in</a>';
        document.getElementById('chart_div_$key').outerHTML = '<img src=\"' + chart_$key.getImageURI() + '\"><BR/><a href=\"' + chart_$key.getImageURI() + '\">Printable version</a>';
        //document.getElementById('chart_div_$key').innerHTML = '<img src=\"' + chart_$key.getImageURI() + '\"><BR/>';
        //document.getElementById('chart_div_$key').outerHTML = '<a href=\"' + chart_$key.getImageURI() + '\">Printable version</a>';
     });

     chart_$key.draw(data_$key, options_$key);
        //console.log(chart_$key.getImageURI());
    
}//function draw_stacked_$key
    ";
    $content.="  <div id=\"chart_div_$key\"></div>\n\n";
} //for each key



$jscontent.="
</script>
";

$content.='
  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
';

$content.=$jscontent;


$T->set( 'content', $content );
$T->set( 'event_id', $cws_config['global']['indico_event_id'] );
$T->set( 'user_name', $_SESSION['indico_oauth']["user"]["full_name"]);
$T->set( 'user_first_name', $_SESSION['indico_oauth']["user"]["first_name"]);
$T->set( 'user_last_name',$_SESSION['indico_oauth']["user"]["last_name"]);
echo $T->get();
?>