<?php

/* Initial version by Stefano.Deiuri@Elettra.Eu

2025.11.15 - nicolas.delerue@ijclab.in2p3.fr: Add get region
2022.08.30 - update

*/

define( 'WEB_ECHO_STYLE', 'font-family: Arial; font-weight: bold; padding: 3px;' );
define( 'COLORED_OUPUT', 'font-family: Arial; font-weight: bold; color:red; padding: 3px;' );

$cws_echo_mode =false;

//-----------------------------------------------------------------------------
function echo_result( $_status, $_error_message ='ERROR', $_ok_message ='OK' ) {
    if ($_status) echo_ok( $_ok_message);
    else echo_error( $_error_message );
    
    return $_status;
}

//-----------------------------------------------------------------------------
function echo_ok( $_message ='OK' ) { 
    global $cws_echo_mode;

    if ($cws_echo_mode == 'web') echo "<div style='" .WEB_ECHO_STYLE ." color: green;'>" .str_replace( "\n", "<br />", $_message )."</div>\n";
    else echo (defined(COLORED_OUTPUT) && COLORED_OUTPUT? "\033[0;32m$_message\033[0m\n" : "$_message\n"); 
}

//-----------------------------------------------------------------------------
function echo_error( $_message ='ERROR' ) { 
    global $cws_echo_mode;

    if ($cws_echo_mode == 'web') echo "<div style='" .WEB_ECHO_STYLE ." color: red;'>" .str_replace( "\n", "<br />", $_message )."</div>\n";
    else echo (defined(COLORED_OUTPUT) && COLORED_OUTPUT ? "\033[0;31m$_message\033[0m\n" : "$_message\n"); 
}

//-----------------------------------------------------------------------------
function need_file() {
    $files =func_get_args();
    
    foreach ($files as $fname) {
        if (!file_exists( $fname )) {
            echo_error( "ERROR (Unable to open $fname)" );
            return false;
        }
    }
        
    return true;
}


function get_region($country_code){
        $Asia_list=[ "AU", "CN", "IN", "JP", "KR", "KZ", "TH", "TW" ];
        $Americas_list=[ "BR", "CA", "MX", "US" ];
        $EMEAS_list=[ "AM", "AT", "BA", "BE", "CH", "CZ", "DE", "DK", "DZ", "ES", "FR" , "GB", "GR", "HR", "HU", "IL", "IR", "IT", "JO", "LT", "LU", "LV", "MT", "NG", "NL", "NO", "PL", "PS",  "PT", "RO", "RU", "SE", "SI", "TN", "TR",  "UA",  "UK", "ZA" ];
        if (in_array($country_code,$Asia_list)) return "Asia";
        if (in_array($country_code,$Americas_list)) return "Americas";
        if (in_array($country_code,$EMEAS_list)) return "EMEA";
        echo( "Unknown country code: ".$country_code."<BR/>\n" );
    
        return "Unknown";
} //get_region




//-----------------------------------------------------------------------------
//-----------------------------------------------------------------------------
class JICT_OBJ {
    var $cfg =false;
    var $data =false;
    var $verbose_next =false;

	//-------------------------------------------------------------------------
	function __construct( $_cfg =false, $_load =false ) {
//		if ($_cfg) $this->config( $_cfg, $_load =false );
		if ($_cfg) $this->config( $_cfg );

        if ($_load) $this->load();

        $this->verbose_next =false;
	}

    //-----------------------------------------------------------------------------
    public function config( $_var =false, $_val =false ) {
        if (is_array($_var)) {
            $this->cfg =$_var;

        } else if ($_var == false) {
            $c =$this->cfg;
            $c['indico_token'] ='*****';
            print_r( $c );

        } else {
            $this->cfg[$_var] =$_val;
        }
    }
    
    //-----------------------------------------------------------------------------
    function verbose( $_message ="", $_level =1, $_nl =true ) {
        if ($this->cfg['echo_mode'] == 'web') return; 

        $this->verbose_next =($this->cfg['verbose'] >= $_level);

        if (!$this->verbose_next) return;

        if (substr( trim($_message), -3 ) == '...') $_nl =false;

        if (is_array( $_message )) {
            foreach ($_message as $level =>$message) {
                echo $message;
            }
            return;
        }
        
        echo $_message .($_nl ? "\n" : false);
    }
 
    //-----------------------------------------------------------------------------
    function verbose_next( $_message ="", $_nl =true ) {
        if ($this->cfg['echo_mode'] == 'web') return; 

        if ($this->verbose_next) echo $_message .($_nl ? "\n" : false);
    }

    //-----------------------------------------------------------------------------
    function verbose2( $_message ="", $_level =1, $_nl =true ) {
        if ($this->cfg['verbose'] == $_level) echo $_message .($_nl ? "\n" : false);
    }
    
    //-----------------------------------------------------------------------------
    function verbose_ok( $_message ='OK' ) {
        if ($this->verbose_next) echo_ok( $_message );
    }
    
    //-----------------------------------------------------------------------------
    function verbose_error( $_message ='ERROR' ) {
        if ($this->verbose_next) echo_error( $_message );
    } 
    
    //-----------------------------------------------------------------------------
    function verbose_status( $_fail, $_message_error ='ERROR', $_message_ok ='OK' ) {
        if (!$this->verbose_next) return;
        
        if ($_fail) echo_error( $_message_error );
        else echo_ok( $_message_ok );
    } 
    
    //-------------------------------------------------------------------------
    function load() {
        foreach ($this->cfg as $var =>$val) {
            if (substr( $var, 0, 3 ) == 'in_') {
                $obj_name =substr( $var, 3 );

                $this->verbose( "# LOAD $obj_name ($val)... " );

                if (file_exists($val)) {
                     $this->data[$obj_name] =file_read_json( $val, true );
                } else {
                    $this->data[$obj_name] =false;
                }

                $this->verbose_status( empty($this->data[$obj_name] ));
            }
        }
    }

    //-------------------------------------------------------------------------
    function save( $_name ) {
        $out_name ='out_'.$_name;

        if (empty($this->cfg[$out_name])) {
            $this->verbose( "# OBJECT not found ($out_name)!" );
        }

        $fname =$this->cfg[$out_name];

        $this->verbose( "# SAVE $out_name ($fname)... " );

        if (empty($this->data[$_name])) {
            echo "NO DATA!\n";
            return false;
        }

        $status =file_write_json( $fname, $this->data[$_name] );
        $this->verbose_status( !$status );

        return $status;
    }

} /* END CLASS */







//----------------------------------------------------------------------------
function config( $_app =false, $_check_in_file_exit =false, $_check_in_file =true ) {
 global $cws_config, $cws_echo_mode;
 
// print_r($cws_config);

 foreach (array( 'conf_name', 'indico_server_url', 'indico_event_id', 'indico_token', 'root_url', 'root_path') as $var) {
    if (!isset($cws_config['global'][$var]) || $cws_config['global'][$var] == '') {
        echo_error( "\n\nWrong configuration! Please check config.php! (global\\$var)\n\n\n" );
        die;
    }	 
 }

 if (!$_app) {
    if ((!is_null($_SERVER['PWD']))&&(strlen($_SERVER['PWD'])>0)){
    	$p =pathinfo( $_SERVER['PWD'] );
    	$_app =$p['basename'];
    } else {  
        $basedir=pathinfo($_SERVER['REQUEST_URI'])['dirname'];
        $dirvals=explode("/",$basedir);
        $_app=$dirvals[count($dirvals)-1];
	}
	echo "Read config for $_app\n\n";
 }
 
 if (empty($cws_config[$_app])) {
    echo "App ($_app) undefined!\n\n";
    die;
 }
 
 cws_define( 'app', $_app );
  
 $cfg =$cws_config[$_app];
 $cfg['app'] =$_app;

 foreach ([ 'data_path', 'tmp_path', 'out_path', 'logs_path' ] as $path_name) {
	if (!isset($cfg[$path_name])) {
        $$path_name =$cfg[$path_name] =$cws_config['global'][$path_name];

    } else {
		$$path_name =$cfg[$path_name];
	}
 }

 foreach ($cws_config['global'] as $name =>$value) {
    if (!is_array($value)) {
        $value =str_replace( '{root_path}', ROOT_PATH, $value );       
        cws_define( $name, $value );

        // global paths
        if (substr( $name, -5 ) == '_path') {
            $dir =strtoupper( substr( $name, 0, -5 ));
            $path =$value;

            if (!file_exists( $path )) {
                echo "Create $dir directory ($path)... ";

                if (mkdir( $path, 0775, true )) {
                    system( 'chown apache.apache ' .$path );
        //			system( 'chmod 775 ' .$path );
                    echo( "OK\n" );

                } else {
                    echo( "ERROR! (unable to create $dir directory)" );
                    die;
                }		

            } else if ($dir != 'ROOT') {
                if (!is_writable( $path )) {
                    echo( "ERROR! Unable to write in $dir directory ($path)" );
                    die;
                }
            }
        }

    } else {
        foreach ($value as $name1 =>$value1) {
            if (!is_array($value1)) {
                $name2 =(in_array( $name, [ 'colors', 'labels' ] ) ? substr( $name, 0, -1 ) : $name) .'_' .$name1;
                $cfg[$name2] =$value1;
                cws_define( $name2, $value1 );
            }
        }
    }
 }

 foreach ($cfg as $name =>$value) {
	if (strpos( $name, '_' ) !== false) list( $name1, $name2 ) =explode( '_', $name );
    else $name1 =$name2 =false;
	 
    if (!is_array($value)) {
		$value =str_replace( '{app_data_path}', $data_path, $value );
		$value =str_replace( '{app_out_path}', $out_path, $value );
		$value =str_replace( '{app_tmp_path}', $tmp_path, $value );
		$value =str_replace( '{root_path}', ROOT_PATH, $value );
		$value =str_replace( '{data_path}', DATA_PATH, $value );
		$value =str_replace( '{out_path}', OUT_PATH, $value );
		$value =str_replace( '{tmp_path}', TMP_PATH, $value );
		$value =str_replace( '{root_url}', ROOT_URL, $value );
		$value =str_replace( '{app}', $_app, $value );
		 
        if (substr( $value, -1 ) == '*') {
            $value =substr( $value, 0, -1 );

        } else if ($_check_in_file && $name1 == 'in' && empty($cfg[ 'out' .substr( $name, 2 ) ])) {
			if (!file_exists( $value ) || filesize( $value ) == 0) {
				if ($_check_in_file_exit) return false;
				echo_error( "ERROR! Missing file $value!" );
				die;
			}
			
			if (!filesize( $value ) || filesize( $value ) == 0) {
				if ($_check_in_file_exit) return false; 
				echo_error( "ERROR! Bad size file $value!" );
				die;
			}
			
//			$name =substr( $name, 3 );
		}

		$cfg[$name] =$value;
	}
	 
	cws_define( $name, $value, true );
 }

 foreach ($cws_config['global'] as $name =>$value) {
//	list( $name1, $name2 ) =explode( '_', $name );
	if (!isset($cfg[$name])) $cfg[$name] =$value;
 }

 foreach ([ 'data_path', 'tmp_path', 'out_path' ] as $path_name) {
	$name =strtoupper( substr( $path_name, 0, -5 ));
	$path =$cfg[$path_name];

	if (!file_exists( $path )) {
		echo "Create $name directory ($path)... ";

		if (mkdir( $path, 0775, true )) {
			system( 'chown apache.apache ' .$path );
//			system( 'chmod 775 ' .$path );
			echo_ok();

		} else {
			echo_error( "ERROR! (unable to create $name directory)" );
			die;
		}		

	} else {
		if (!is_writable( $path )) {
			echo_error( "ERROR! Unable to write in $name directory ($path)" );
			die;
		}
	}
 }
 
//  date_default_timezone_set( TIMEZONE );
 
 $cws_echo_mode =$cfg['echo_mode'];

 return $cfg;
}

//----------------------------------------------------------------------------
function cws_define( $_name, $_value, $_app =false ) {
	if (is_array( $_value )) return;
	
	$name =($_app ? 'APP_' : false) .strtoupper( $_name);
	if (!defined( $name )) define( $name, $_value );
//	echo "define $name =$_value\n";
}



function debug_calltrace() {
    $e = new Exception();
    $trace = explode("\n", $e->getTraceAsString());
    // reverse array to make steps line up chronologically
    $trace = array_reverse($trace);
    array_shift($trace); // remove {main}
    array_pop($trace); // remove call to this method
    $length = count($trace);
    $result = array();
   
    for ($i = 0; $i < $length; $i++) {
           $result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
    }
   
    return "\t" . implode("\n\t", $result);
}
   

//----------------------------------------------------------------------------
function file_write( $_filename, $_data, $_mode ='w', $_verbose =false, $_verbose_message =false ) {
	
    if ($_verbose)	echo "Save $_verbose_message ($_filename)... ";

    $fp =fopen( $_filename, $_mode );

    if (!$fp) {
    //	echo "unable to save file $_filename!\n";
        if ($_verbose) echo_error( "ERROR (writing)" );


		if (false) { // for debug problems
       	 	$include_list =get_included_files();
        	$calltrace =debug_calltrace();

       	 	$debug =date('r') ." -------------------------------------------------------\n"
        	    .$_filename
    	        ."\n\nREQUEST\n"
 	           .print_r( $_REQUEST, true )

        	    ."\n\nINCLUDE\n"
    	        .print_r( $include_list, true )

	            ."\n\nCALLTRACE\n"
            	.print_r( $calltrace, true )
            
            	."\n";
        
        	file_write( TMP_PATH ."/debug-fwrite.log", $debug, 'a' );
		}

        return false;
    }

    fwrite( $fp, (is_array($_data) ? implode('',$_data) : $_data) );

    fclose( $fp );

    if ($_mode == 'a' || $_mode == 'w') chown( $_filename, 'apache' );

    if ($_verbose)	echo_ok();

    return true;
}

//----------------------------------------------------------------------------
function file_read( $_filename, $_verbose =false, $_verbose_message =false ) {
    
    if ($_verbose)	echo "Read $_verbose_message ($_filename)... ";
    
    if (!file_exists( $_filename )) {
        if ($_verbose) echo_error( "ERROR (not exists)" );
        return false;
    }
        
    $c =file( $_filename );
    
    if (empty($c)) {
        if ($_verbose) echo_error( "ERROR (empty file)" );
        return false;
    }
        
    if ($_verbose)	echo_ok();
    
    return implode( '', $c );
}

//----------------------------------------------------------------------------
function file_write_json( $_filename, &$_obj ) {
    if (empty($_obj)) {
        echo "ERROR in file_write_json: empty obj ";
        return false;
    } else {
        $json =json_encode( $_obj, JSON_INVALID_UTF8_IGNORE );
        //$json =json_encode( $_obj);
        //$json =json_encode( $_obj, JSON_THROW_ON_ERROR);
        //$json =json_encode( $_obj, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE  );
        //echo "file_write_json len json [0]".strlen($json)."\n";
        if ((!$json)||(empty($json))||(strlen($json)==0)) {
            echo "Json data size after 1st attempt: ".strlen($json)."\n";
            echo "Error with json conversion\n";
            echo "Second attempt...\n";
            $json =json_encode( $_obj,  JSON_THROW_ON_ERROR | JSON_THROW_ON_ERROR );
            echo "Json data size after 2nd attempt: ".strlen($json)."\n";
        }
        if ((!$json)||(empty($json))||(strlen($json)==0)) {
            echo "Third attempt...\n";
            $json =json_encode( $_obj);
            echo "Json data size after 3rd attempt: ".strlen($json)."\n";
        }
        if ((!$json)||(empty($json))||(strlen($json)==0)) {
            echo "ERROR in file_write_json: non existent or empty json ";
            return false;
        } else {
            //json OK
            if (!empty($_obj) && empty($json)) {
                echo sprintf( "ERROR: %s! (%s)\n", json_last_error_msg(), $_filename );
                return false;
            }
        } //json OK
        return file_write( $_filename, $json );
    }
}

//----------------------------------------------------------------------------
function file_read_json( $_filename, $_assoc =false ) {
    if (!file_exists( $_filename )) return false;
    
    return json_decode( implode( '', file( $_filename )), $_assoc );
}

//----------------------------------------------------------------------------
function file_write_object( $_filename, &$_obj ) {

    $fnp =explode( '.', $_filename );
    $ext =(is_array($fnp) && count($fnp)) ? end($fnp) : false;

	switch ($ext) {
		case 'jsonz':
			return file_write( $_filename, gzcompress(json_encode( $_obj )));

		case 'json':
			return file_write( $_filename, json_encode( $_obj ));

		case 'obz':
			return file_write( $_filename, gzcompress(serialize( $_obj )));

		case 'obj':
			return file_write( $_filename, serialize( $_obj ));

		default:
			return file_write( $_filename, $_obj );
	}
}

function file_read_object( $_filename, $_assoc =true ) {
	if (!file_exists($_filename)) return false;

    if (strpos( $_filename, '.' ) !== false) $ext =substr( $_filename, strrpos( $_filename, '.' ) +1 );
    else $ext =false;

	switch ($ext) {
		case 'jsonz':
			return json_decode( gzuncompress(implode( '', file( $_filename ))), $_assoc );

		case 'json':
			return json_decode( implode( '', file( $_filename )), $_assoc );

		case 'obz':
			return unserialize( gzuncompress(implode( '', file( $_filename ))));

		case 'obj':
			return unserialize( implode( '', file( $_filename )));
            
		default:
			return implode( '', file( $_filename ));
	}
}

//-----------------------------------------------------------------------------
function download_file( $_tmp_fname, $_download_fname, $_content_type ='text' ) {
    if ($_content_type) header( 'Content-type: ' .$_content_type );
    header( 'Content-Disposition: attachment; filename=' .$_download_fname );
    header( 'Content-Length: ' .filesize( $_tmp_fname ) );
    header( 'Pragma: public' );
    readfile( $_tmp_fname );
}

/* //-----------------------------------------------------------------------------
function _R( $_name, $_value =false, $_false_value =false ) {
    if (!isset( $_REQUEST[$_name] )) return $_false_value;
    if ($_value && ($_REQUEST[$_name] != $_value)) return $_false_value;
    return $_REQUEST[$_name];
} 

//-----------------------------------------------------------------------------
function _P( $_name, $_value =false, $_false_value =false ) {
    if (!isset( $_POST[$_name] )) return $_false_value;
    if ($_value && ($_POST[$_name] != $_value)) return $_false_value;
    return $_POST[$_name];
}

//-----------------------------------------------------------------------------
function _G( $_name, $_value =false, $_false_value =false ) {
    if (!isset( $_GET[$_name] )) return $_false_value;
    if ($_value && ($_GET[$_name] != $_value)) return $_false_value;
    return $_GET[$_name];
} */

//-----------------------------------------------------------------------------
function gz_http_response( $_text ) {
    if (strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) !== false) {
        ob_start("ob_gzhandler");
    } else {
        ob_start();
    }
        
    echo $_text;
    ob_flush();
}


//-----------------------------------------------------------------------------
class TMPL {
    function __construct( $_fname ) {
        global $cws_config;

        $this->vars =[ 'index_url' =>$cws_config['global']['root_url'] ];
        $this->template_fname =$_fname;
    }

    function set( $_var, $_value =false ) {
        if (is_array($_var)) {
            foreach ($_var as $var =>$val) {
                $this->vars[ $var ] =$val;
            }
            return;
        }

        $this->vars[ $_var ] =$_value;
    }

    function get() {
        $page =file_read( $this->template_fname );

        foreach ($this->vars as $var =>$value) {
            $page =str_replace( '{'.$var.'}', $value, $page );
        }
        
        return $page;        
    }
}


//-----------------------------------------------------------------------------
function _input( $_name, $_value, $_attr =[] ) {
	$_attr['name'] =$_name;
	$_attr['value'] =$_value;

	if (empty($_attr['type'])) $_attr['type'] ='text';
	if (empty($_attr['class'])) $_attr['class'] ='form-control';

	$html ="<input " .__aa([ 'type', 'name', 'value', 'size', 'class', 'id', 'disabled', 'checked', 'data-inputmask', 'placeholder', 'style', 'onClick' ], $_attr) ." />";

	if (!empty($_attr['input-group-addon'])) $html =__h( 'div', $html .__h( 'span', $_attr['input-group-addon'], [ 'class' =>'input-group-addon'] ) , [ 'class' =>'input-group' ]);

	return $html;
}


//-----------------------------------------------------------------------------
function _textarea( $_name, $_value, $_attr =[] ) {
	$_attr['name'] =$_name;

	if (empty($_attr['class'])) $_attr['class'] ='form-control';

	$html ="<textarea " .__aa([ 'name', 'cols', 'rows', 'class', 'id', 'disabled', 'checked', 'placeholder' ], $_attr )
        .">$_value</textarea>";

	return $html;
}


//-----------------------------------------------------------------------------
function _select( $_select_name, $_def_value, $_array, $_cfg =[] ) {
    $ret ="\n<select name='$_select_name" .(empty($_cfg['multiple']) ? "'" : "[]' multiple")
// 		.__a( 'id', $_cfg ) .__a( 'class', $_cfg ) .__a( 'style', $_cfg ) .__a( 'size', $_cfg ) .__a( 'onChange', $_cfg )
        .__aa([ 'id', 'class', 'style', 'size', 'onChange' ], $_cfg )
       .">\n";

//    decho( $_cfg );
   if (!empty($_def_value) && !empty($_cfg['serialize'])) $_def_value =explode( $_cfg['serialize'], $_def_value );

   $mode =empty($_cfg['mode']) ? false : $_cfg['mode'];

    $enum =($mode == "enum0") ? 0 : 1;

   if ($mode == 'id' && $_def_value == "") $_def_value =0;

   if (is_array($_array) && count($_array) > 0) {
        foreach ($_array as $key =>$data) {
           switch ($mode) {
               case "enum0":
                   $value =$enum ++;
                   $desc  =$data;
                   break;

               case "id":
                   $value =(int)$key;
                   $desc  =$data;
                   break;

               case "value":
                   $value =$key;
                   $desc  =$data;
                   break;

               default:
   //				$desc =$data;
                   $value =$desc =$data;
           }

           if (is_array($_def_value)) $selected =in_array( $value, $_def_value );
           else $selected =($value == $_def_value);

           $ret .="\t<option value='$value'"
               .($selected ? " selected" : false)
               .">$desc</option>\n";
       }
   }

   $ret .="</select>\n";

   if (empty($_cfg['echo'])) return $ret;

   echo $ret;
}

//-----------------------------------------------------------------------------
function __a( $_name, $_val, $_attribute =false ) {
	if (is_array( $_val )) {
		if (empty($_attribute)) $_attribute =$_name;
		return empty( $_val[$_name] ) ? false : " $_attribute='" .$_val[$_name] ."'";
	}

	return empty( $_val ) ? false : " $_name='$_val'";
}

//-----------------------------------------------------------------------------
function __aa( $_names, $_values ) {
	$ret =false;

	foreach ($_names as $name) {
		if (!empty($_values[$name])) $ret .=" $name='" .$_values[$name] ."'";
	}

	return $ret;
}

//-----------------------------------------------------------------------------
function __h( $_tag, $_content, $_attributes =false, $_raw_attributes =false ) {
	if (is_array($_content)) {
		$html =false;
		foreach ($_content as $c) {
			$html .=__h( $_tag, $c, $_attributes, $_raw_attributes );
		}
		return $html;
	}

    $html ="<$_tag $_raw_attributes ";

	if (!empty($_attributes)) {
		foreach ($_attributes as $name =>$value) {
			$html .=__a( $name, $value );
		}
	}

	$html .=">$_content</$_tag>";

	return $html;
}



/*-----------------------------------------
*/
define( 'CACHE_DIR', './cache' );

class CACHEDATA {
 var $cached, $mtime, $objfname, $age_sec;

 //------------------------------------------------------------------------
 function __construct( $_objname, $_maxage, $_cachedir =false, $_debug =false ) {
	$this->cached =false;
	$this->age_sec =0;
 
	if ($_cachedir && !file_exists( $_cachedir)) mkdir( $_cachedir, 0770 );
 
	$this->objfname =($_cachedir ? $_cachedir : CACHE_DIR) ."/$_objname";
	
	if (!file_exists( $this->objfname )) {
		if ($_debug) echo "\n<!-- cachedata: " .$this->objfname ." not exist -->\n";
		return;
	}
	
	$this->mtime =filemtime( $this->objfname );
	
	if ($this->age() > $_maxage) {
		if ($_debug) echo "\n<!-- cachedata: " .$this->objfname ." too old -->\n";
		return;
	}
 
	$this->cached =true;
 }

 //------------------------------------------------------------------------
 function check() {
 	if (!$this->cached) return false;
	return true;
 }
 
 //------------------------------------------------------------------------
 function touch() {
	touch( $this->objfname );
 }
 
 //------------------------------------------------------------------------
 function fname() {
	return end(explode('/',$this->objfname));
 }
 
 //------------------------------------------------------------------------
 function clear() {
 	if ($this->cached) {
		@unlink( $this->objfname );
		$this->cached =false;
		$this->mtime =false;
	}
 }

 //------------------------------------------------------------------------
 function time() {
	return $this->mtime;
 }
 
 //------------------------------------------------------------------------
 function date( $_fmt ='r' ) {
	return date( $_fmt, $this->mtime );
 }
 
 //------------------------------------------------------------------------
 function age() {
	$this->age_sec =time() - $this->mtime;
	return $this->age_sec;
 }

 //------------------------------------------------------------------------
 function get( &$_obj, $_info =false, $_return_cache =false, $_force_cache =false ) {
 	if ($this->cached || $_force_cache) {
		$_obj =file_read_object( $this->objfname );
		
		if ($_info) $_obj ="\n<!-- begin_cache: " .substr( $this->objfname, strrpos( $this->objfname, '/' ) +1 ) .", age: " .$this->age_sec ." -->\n" .$_obj ."\n<!-- end_cache -->\n";
		
		if ($_return_cache) return $_obj;
		
		return true;
	}
	
	return false;
 }

 //------------------------------------------------------------------------
 function save( $_obj ) {
	$this->cached =true;
	return file_write_object( $this->objfname, $_obj );
 }
}


/*-----------------------------------------
*/
class API_REQUEST {
	var $api_url, $api_headers, $response_code, $result, $error, $cfg;
	
	//-----------------------------------------------------------------------------
	function __construct( $_url ) {
		$this->api_url =$_url;

		$this->cfg =[
			'http_timeout' =>60,
			'authorization_header' =>false,
            'header' =>"Accept: */*\r\n",
            'header_content_type' =>"application/x-www-form-urlencoded",
            'ignore_errors' =>false
            ];
	}
	
	//-----------------------------------------------------------------------------
	function config( $_key, $_val ) {
		$this->cfg[ $_key ] =$_val;
	}

	//-----------------------------------------------------------------------------
	function configs( $_configs ) {
		foreach ($_configs as $key =>$val) {
			$this->cfg[ $key ] =$val;
		}
	}

	//-----------------------------------------------------------------------------
	function request( $_name =false, $_method ='GET', $_data =false ) {
        global $http_response_header;

        $content =false;
        if (!empty($_data)) {
            if ($this->cfg['header_content_type'] == 'application/json') $content =json_encode( $_data );
            else if (is_array($_data)) $content =http_build_query($_data);
			else $content =$_data;
        }

		$this->request_options =array(	
			'http' =>array(
				'header'  =>($this->cfg['authorization_header'] ? "Authorization: " .$this->cfg['authorization_header'] ."\r\n" : false) 
                    .$this->cfg['header']
                    .(empty($this->cfg['header_content_type']) ? false : 'Content-Type:' .$this->cfg['header_content_type'] ."\r\n"),
				'method'  =>$_method,
				'timeout' =>$this->cfg['http_timeout'],
                'ignore_errors' =>$this->cfg['ignore_errors'],
				'content' =>$content
                ));
		if ($this->cfg['proxy']){
            $this->request_options->http['proxy']=$this->cfg['proxy'];
        }


		$s =@stream_context_create( $this->request_options );

		$this->api_request =$this->api_url .$_name
			.($_method == 'GET' && !empty($_data) ? '?' .http_build_query($_data) : false );

		$result =@file_get_contents( $this->api_request, false, $s );
        
		$headers =$this->parseHeaders( $http_response_header );
		
		$this->api_headers =$headers;
		$this->response_code =$headers['response_code'];

		if ($headers['response_code'] != 200) {
            print("Response code error: ");
            print($headers['response_code']."<BR/>\n");
			$this->result =$result;
			$this->error =error_get_last();

		} else {
            if (strpos( $headers['Content-Type'], 'html' )) $this->result =$result;
			else $this->result =json_decode( $result, true );

            //$this->error =false;
		}
		
		return $this->result;
	}

	//-----------------------------------------------------------------------------
	function parseHeaders( $headers ) {
		$head =[];

		foreach( $headers as $k=>$v ) {
			$t = explode( ':', $v, 2 );
			if (isset( $t[1] )) {
				$head[ trim($t[0]) ] = trim( $t[1] );
				
			} else {
				$head[] = $v;
				if( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out ) ) $head['response_code'] = intval($out[1]);
			}
		}
		
		return $head;
	}
	//-----------------------------------------------------------------------------
}


?>
