<?php

/* bY Stefano.Deiuri@Elettra.Eu

2024.02.05 - update
2023.03.31 - 1st version

*/

require( 'config.php' );

require_lib( 'jict', '1.0' );
require_lib( 'indico', '1.0' );

session_start();

global $cws_echo_mode;

$cfg =config( 'global' );
$cfg['echo_mode'] ='none';


$Indico =new INDICO( $cfg );
$cws_echo_mode ='none';

if (empty($_GET['code'])) {
    $Indico->oauth( 'error', 'no code' );
	exit;
}
    
$Indico->oauth( 'token' );

$conf =in_array( $_GET['conf'], [ 'jfic', 'ipac23', 'ipac24' ]) ? '_' .$_GET['conf'] : false;
var_dump($_SERVER);

var_dump($user);

if (isset($_COOKIE["URI_before_oauth"])){
    $redirect_uri=$_COOKIE["URI_before_oauth"];    
    setcookie("URI_before_oauth", "", time() - 3600, '/'); //delete cookie
} else {
    $redirect_uri="/JICT-LPR/";
}
header( "Location: $redirect_uri" );
?>
