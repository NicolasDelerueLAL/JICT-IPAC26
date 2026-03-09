<?php
/* by Nicolas.delerue@ijclab.in2p3.fr

This page merges local and remote QA files (specific to IPAC'26 setup).
9.03.2026 Creation

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

require_once('ipac26_tools.php');

    $local_contribs_qa_data=file_read_json(  $cws_config['global']['data_path']."/contribs_qa.json",true);
    $remote_contribs_qa_data=file_read_json(  $cws_config['global']['data_path']."/contribs_qa.json",true);


foreach ($remote_contribs_qa_data as $contribution_id => $contribution_qa_data){
    if (!isset($local_contribs_qa_data[$contribution_id])){
        $local_contribs_qa_data[$contribution_id]=$contribution_qa_data;
        print("Adding contribution $contribution_id to local data<BR/>\n");
    } else {
        if (array_key_exists("title",$contribution_qa_data) && array_key_exists("title",$remote_contribs_qa_data[$contribution_id])){
            if ($local_contribs_qa_data[$contribution_id]["title"]["date"]<$remote_contribs_qa_data[$contribution_id]["title"]["date"]){
                $local_contribs_qa_data[$contribution_id]["title"]=$remote_contribs_qa_data[$contribution_id]["title"];
                print("Updating contribution $contribution_id in local data<BR/>\n");
            }
        } else {
            if (array_key_exists("title",$remote_contribs_qa_data[$contribution_id])){
                $local_contribs_qa_data[$contribution_id]["title"]=$remote_contribs_qa_data[$contribution_id]["title"];
                print("Updating contribution $contribution_id in local data<BR/>\n");
            }
        }
    }
}

$contribs_qa_data=$local_contribs_qa_data;
file_put_contents($cws_config['global']['data_path']."/contribs_qa.json",json_encode($contribs_qa_data));

?>
Done
