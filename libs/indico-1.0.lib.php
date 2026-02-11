<?php

/* Initial version by Stefano.Deiuri@Elettra.Eu

2026.02.10 - nicolas.delerue@ijclab.in2p3.fr: change oauth redirection using cookie to know where the visitor came from.
2025.11.20 - nicolas.delerue@ijclab.in2p3.fr: improve statistics
2025.11.10 - nicolas.delerue@ijclab.in2p3.fr: fix problem related to $_server [PWD] being empty
2025.06.05 - fix statistics
2024.07.16 - updates
2023.11.27 - handle public access mode
2023.03.01 - fix session block handlers
2022.08.29 - remove edots
2022.08.18 - save_citations
2022.07.13 - 1st version

*/

/* 

The Indico Editorial Module for JACoW
https://codimd.web.cern.ch/h6pGyyyqQK-g7uU9Gr7q7Q

JACoW Workflow using Indico
https://codimd.web.cern.ch/s/d2XPNF5L9#Editing-states

*/

//_changes_acceptance

define( 'FAIL_QA_STRING', 'his revision has failed QA.' );

define( 'QA_FAIL', 'QA Failed' );
define( 'QA_OK', 'QA Approved' );

define( 'MAP_STATUS', [ 'accepted' =>'g', 'acceptance' =>'g', '_accepted_submitter' =>'g', 'accepted_submitter' =>'g', 'needs_submitter_confirmation' =>'y', 'needs_submitter_changes' =>'r', 
'assigned' =>'a', 'nofiles' =>'nofiles', 'ready_for_review' =>'files', 'rejected' =>'x', 'rejection' =>'x' ]);

//-----------------------------------------------------------------------------
//-----------------------------------------------------------------------------
class INDICO extends JICT_OBJ {
	var $source_file_type_id =false;
	var $editing_tags =[];
	var $editors_stats =[];
	var $stats =[]; //[ 'slides' =>[ 'check' =>[ 'Yes' =>0, 'No' =>0 ]]];
	var $api =false;
	var $requests_api_count =0;
	var $requests_cache_count =0;

	//-------------------------------------------------------------------------
	function __construct( $_cfg =false, $_load =false ) {
		$this->api =new API_REQUEST( $_cfg['indico_server_url'] );
		$this->api->config( 'authorization_header', 'Bearer ' .$_cfg['indico_token'] );

		$this->event_id =$_cfg['indico_event_id'];
		
		if ($_cfg) $this->config( $_cfg );

		if (empty($this->cfg['cache_time'])) $this->cfg['cache_time'] =60;

        if ($_load) $this->load();

		$this->debug =false;
	}

	//-----------------------------------------------------------------------------
	function auth() {
		session_start();

		if (empty($this->cfg['indico_oauth']) || empty($this->cfg['indico_oauth']['client_id'])) {
			$user =[
				'full_name' =>'public',
				'email' =>'public',
				'public' =>true
				];

			return $user;
		}

		$login_message ="To use the utility please login with your Indico account<br /><br /><a href='$_SERVER[PHP_SELF]?cmd=login'>Log In</a>";

		if (!empty($_GET['cmd'])) {
			if ($_GET['cmd'] == 'logout') {
				$_SESSION['indico_oauth'] =false;
				echo $login_message;
				exit;
			}
	
			if ($_GET['cmd'] == 'login') {
				$this->oauth( 'authorize' );
				exit;
			}
		}

		if (empty($_SESSION['indico_oauth']['token']) || strlen($_SESSION['indico_oauth']['token']) < 20) {
			$cookie_name="URI_before_oauth";
			$cookie_value=$_SERVER["REQUEST_URI"];
			setcookie($cookie_name, $cookie_value, time() + 3600, '/'); //valid 1 hour
			echo $login_message;
			exit;
		}

		if (!empty($this->cfg['allow_roles'])) {
			if ($this->cfg['allow_roles'][0] == '*' && empty($_SESSION['indico_oauth']['user']['roles'])) {
				echo "You don't have an Indico role!";

				file_write( sprintf( "%s/users.log", $this->cfg['logs_path']),
					sprintf( "%s - %s (KO: NO ROLES) -%s\n", 
						date('Y-m-d H:i:s'), 
						$_SESSION['indico_oauth']['user']['full_name'], 
						$_SERVER['PHP_SELF'] 
						),
			  		'a');

				exit;			
			}

			if ($this->cfg['allow_roles'][0] != '*' && empty(array_intersect( $this->cfg['allow_roles'], $_SESSION['indico_oauth']['user']['roles'] ))) {
				echo "Your Indico roles don't include " .implode( ' or ', $this->cfg['allow_roles'] );

				file_write( sprintf( "%s/users.log", $this->cfg['logs_path']),
					sprintf( "%s - %s (KO: NO ROLES [%s]) -%s\n", 
						date('Y-m-d H:i:s'), 
						$_SESSION['indico_oauth']['user']['full_name'], 
						implode( ",", $this->cfg['allow_roles'] ),
						$_SERVER['PHP_SELF'] 
						),
			  		'a');

				exit;
			} 
		}

		file_write( sprintf( "%s/users.log", $this->cfg['logs_path']),
		  	sprintf( "%s - %s (OK) -%s\n", 
				date('Y-m-d H:i:s'), 
				$_SESSION['indico_oauth']['user']['full_name'], 
				$_SERVER['PHP_SELF'] 
				),
			'a');

		return $_SESSION['indico_oauth']['user'];
	}

	//-----------------------------------------------------------------------------
	function oauth( $_request, $_message =false ) {
//		global $indico_api, $cws_config;

		$oauth_cfg =$this->cfg['indico_oauth'];

		switch ($_request) {
			case 'token':
				$rqst_data =[
					'client_id'		=>$oauth_cfg['client_id'],
					'client_secret'	=>$oauth_cfg['client_secret'],
					'code'			=>$_GET['code'],
					'grant_type'	=>'authorization_code',
					'redirect_uri'  =>$oauth_cfg['redirect_uri']
					];

				$user_token =$this->request( '/oauth/token', 'POST', $rqst_data, [ 'disable_cache' =>true, 'return_data' =>true ]);

				$_SESSION['indico_oauth']['error'] =false;
				$_SESSION['indico_oauth']['token'] =$user_token['access_token'];
				
				$this->api->config( 'authorization_header', 'Bearer ' .$user_token['access_token'] );

				$user =$this->request( '/api/user', 'GET', false, [ 'disable_cache' =>true, 'return_data' =>true ]);
				//print_r( $user );
				//file_write( '/web/httpd/vhost-jacow.org/jict_ipac24/tmp/debug-user.json', $user  );
				$user['full_name'] ="$user[first_name] $user[last_name]";
				$_SESSION['indico_oauth']['user'] =$user;

				$this->api->config( 'authorization_header', 'Bearer ' .$this->cfg['indico_token'] );

				$roles =$this->request( '/event/{id}/manage/roles/api/roles/', 'GET', false, [ 'return_data' =>true ]);
		
				$user_roles =[];
				foreach ($roles as $role) {
					foreach ($role['members'] as $member) {
						if ($member['id'] == $user['id']) {
							$user_roles[] =$role['code'];
							// if ($role['code'] == 'JAD') $_SESSION['indico_oauth']['user']['admin'] =true;
						}
					}
				}

				$_SESSION['indico_oauth']['user']['roles'] =$user_roles;
				$_SESSION['indico_oauth']['user']['admin'] =in_array( 'JAD', $user_roles );
				break;  

			case 'authorize':
				$authorize_url =$this->cfg['indico_server_url'] .'/oauth/authorize?'
					.http_build_query([
						'client_id'     =>$oauth_cfg['client_id'],
						'redirect_uri'  =>$oauth_cfg['redirect_uri'],
						'response_type'	=>'code',
						'scope'         =>'read:user read:everything full:everything read:legacy_api'
						]);
			
				header( 'Location: ' .$authorize_url );
				exit;

			case 'error':
				$_SESSION['indico_oauth']['error'] =[ 'error' =>true, 'message' =>$_message ];
				break;  
		}
	}


	//-------------------------------------------------------------------------
	function request( $_request, $_method ='GET', $_data_request =false, $_rqst_cfg =false ) {
		$req =str_replace( "{id}", "$this->event_id", $_request );

        $fname =trim(str_replace( '/', '_', $req ), '_');
        if (substr( $fname, -5 ) != '.json') $fname .='.json';

		$verbose =empty($_rqst_cfg['quiet']);

		if ($_method != 'GET') $_rqst_cfg['disable_cache'] =true;

		//print_r( $_rqst_cfg );

		if (isset($_rqst_cfg['cache_time'])) $cache_time =$_rqst_cfg['cache_time'];
        else $cache_time =empty($_rqst_cfg['disable_cache']) ? $this->cfg['cache_time'] : 0;

		$cache =new CACHEDATA( $fname, $cache_time, $this->cfg['tmp_path'] );

		if (!$cache->get( $this->data[$req] )) {
			$this->requests_api_count ++;
			$t0 =time();
			if ($verbose) $this->verbose( "# $_method ($cache_time) $req... ", 2 );
			if ($_rqst_cfg['use_session_token']) {
				//Without that the request's auther will be the app oauth token's owner
				//print("Overriding authorization header with session token\n"); 
			    $this->api->config( 'authorization_header', 'Bearer ' .$_SESSION['indico_oauth']['token'] );	
			}
			
			$this->data[$req] =$this->api->request( $req, $_method, $_data_request );

			// print_r( $this->api ); die;
			
			if ($verbose) {
				$this->verbose_status( empty($this->data[$req]), "NO_DATA" );
//				$this->verbose_status( $this->data[$req], sprintf( "(%s) ", (time() -$t0) ));
			}

            if (empty($_rqst_cfg['disable_cache'])) {
                if ($verbose) $this->verbose(sprintf( "# SAVE %s/%s... ", $this->cfg['tmp_path'], $fname ), 2 );
				$cache_status =$cache->save( $this->data[$req] );
                if ($verbose) $this->verbose_status( !$cache_status );
            }

		} else {
			$this->requests_cache_count ++;
			if ($verbose) $this->verbose( "# USE CACHE $fname... OK", 2 );
		}

        if (empty($_rqst_cfg['return_data'])) return $req;

        return $this->data[$req];
	}

	//-------------------------------------------------------------------------
	function download_pdf( $_paper ) {
		//if ($_paper['status'] == 'g') {
		if (!empty($_paper['pdf_url'])) {
			$token =$this->cfg['indico_token'];
			$pdf_fname =sprintf( "%s/papers/%s.pdf", $this->cfg['data_path'], $_paper['code'] );
			
			$cmd ="wget -q -O $pdf_fname --header='Authorization: Bearer $token' $_paper[pdf_url]; touch $pdf_fname";
			echo sprintf( "[ download ~ %s...", $_paper['code'] );
			system( $cmd );		
	
			$pdf_size =filesize($pdf_fname);

			if ($pdf_size == 0) {
				unlink( $pdf_fname );
				return false;
			}

			echo sprintf(" %sMB ]\n", round( $pdf_size /1024 /1024, 1 ));
	
			return filemtime( $pdf_fname );
		}
		
		return false;
	}

	//-------------------------------------------------------------------------
	function get_pdf_url( $_paper_id ) {
		$x =$this->request( "/event/{id}/api/contributions/$_paper_id/editing/paper", 'GET', false, [ 'return_data' =>true, 'quiet' =>true ]);

		$pcode =$x['contribution']['code'];

//		print_r( $last_revision );exit;

		$url =false;
		foreach ($x['revisions'] as $revision) {
			if (empty($revision['is_undone']) && !empty($revision['files'])) {
				foreach ($revision['files'] as $f) {
					if ($f['filename'] == "$pcode.pdf") $url =$f['external_download_url'];
				}
			}
		}

		$this->data['papers'][$pcode]['editor'] =$x['editor']['full_name'];

		return $url;
	}


	//-------------------------------------------------------------------------
	function import_stats() {
		global $cws_config;

		$this->verbose( "\nProcess stats" );

		$papers_list =$this->request( '/event/{id}/editing/api/paper/list', 'GET', false, 
			[ 'return_data' =>true, 'disable_cache' =>true ] );

		$now =time();

		$map_status =MAP_STATUS;

		$nums =[ 'qaok' =>0, 'files' =>0, 'a' =>0, 'g' =>0, 'y' =>0, 'r' =>0, 'nofiles' =>0, 'processed' =>0, 'total' =>0 ];
			
		$this->data['stats']['papers_submission'] =[];

		$editors =[];
		$editor_papers =[];
		$editor_papers_list =[];
		$days =[ 'processed' =>[] ];

		foreach ($papers_list as $x) {
			$pcode =$x['code'];

            if (!empty($pcode) && !empty($this->data['papers'][$pcode]) && empty($this->data['papers'][$pcode]['hide'])) {
				$p =$this->data['papers'][$pcode];

				$paper_status =empty($x['editable']) ? 'nofiles' : $x['editable']['state'];
				
				$editor =false; // first editor
				$reditor =false; // revision editor

				$istatus =false;  // indico status

				if (!empty($x['editable']['editor'])) {
					$rev_id =$this->get_rev_id( $x['editable'] );

					if (empty($p['rev_id']) || $rev_id != $p['rev_id']) { //  || $pcode == 'TUPS54'
						echo sprintf( "\nUPDATE %s %s > %s (%s)\n", $pcode, (empty($p['rev_id']) ? "NEW" : $p['rev_id']), $rev_id, date('r') );
						$p['rev_id'] =$rev_id;
						$cache_time =0;

					} else {
						$cache_time =3600*8 +rand(0,3600);
					}

					$pedit =$this->process_paper_revisions( $p, $cache_time, true );
					
					if (empty($pedit['editor'])) $pedit =$this->process_paper_revisions( $p, 0, true );

					$this->data['papers'][$pcode] =$p;
					
					$ieditor =false; // initial editor
					if (empty($pedit['editor'])) {
						$pedit =$this->process_paper_revisions( $p, 0, true );
						echo "WARN: $pcode no editor\n";
						$peditor =false;

					} else {
						$peditor =$pedit['editor']['full_name']; // current paper editor
					}
					
					$first_editing_state =false;
					foreach ($pedit['revisions'] as $rid =>$r) {
						if ($r['is_editor_revision']) {
							$reditor =$r['user']['full_name']; // revision editor

							if (empty($editor_papers_list[$reditor])) $editor_papers_list[$reditor] =[ 'g' =>false, 'y' =>false, 'r' =>false, 'a' =>false ];
							if (empty($editor_papers[$reditor])) $editor_papers[$reditor] =0;

							if (!empty($r['files'])) $this->editor_stats_inc( $reditor, 'revisions' );

							// salto le revision consecutive quando un editor da un verde ma carica dei nuovi file
							$skip =false;
							if ($r['qa'] != QA_FAIL && !empty($pedit['revisions'][$rid +1]) && $pedit['revisions'][$rid +1]['qa'] != QA_FAIL) {
								$r1 =$pedit['revisions'][$rid +1];

								if ($r1['is_editor_revision']
									&& $reditor == $r1['user']['full_name']
									&& (strtotime($r1['created_dt']) -strtotime($r['created_dt'])) < 1) $skip =true;
							}
								
							if (!$first_editing_state && $skip == false) {
								$first_editing_state =empty($map_status[ $r['type']['name'] ]) ? $r['type']['name'] : $map_status[ $r['type']['name'] ];

								$this->editor_stats_inc( $reditor, $first_editing_state );
								$editor_papers[$reditor] ++;
								$editor_papers_list[$reditor][$first_editing_state][] =$p['id'];						

								$ymd =substr( $r['created_dt'], 0, 10 );
								$days['processed'][$ymd] =1 +(empty($days['processed'][$ymd]) ? 0 : $days['processed'][$ymd]);

								$ieditor =$reditor; 
							}

							$rdate =substr( $r['created_dt'], 0, 10 );
							$this->stats_inc(sprintf( 'days.editors_revisions.%s', $rdate ));
							$this->stats_inc(sprintf( 'editors_revisions.%s.%s', $reditor, $rdate ));
						}

						$editor =$r['is_editor_revision'] ? $reditor : $peditor;
	
						if ($r['qa'] == QA_OK) {
							$this->editor_stats_inc( $editor, 'qa_ok' );
							$this->stats_inc( 'days.qa_approved.' .substr( $r['created_dt'], 0, 10 ));
							
						} else if ($r['qa'] == QA_FAIL) {
							$this->editor_stats_inc( $editor, 'qa_fail' );
							$this->stats_inc( 'days.qa_failed.' .substr( $r['created_dt'], 0, 10 ));
						}
					}					

					if ($paper_status == 'ready_for_review') {
						$paper_status ='assigned';
						$this->editor_stats_inc( $peditor, 'a' );
					}

					$status =isset($map_status[ $paper_status ]) ? $map_status[ $paper_status ] : "_$paper_status";

					if (in_array( $status, ['y','r'])) $this->editor_stats_inc( $peditor, 'waiting' );
				
				} else {
					$status =isset($map_status[ $paper_status ]) ? $map_status[ $paper_status ] : "_$paper_status";
				}

                if ($status != 'removed') {
                    $nums['total'] ++;
                    if ($p['status_qa'] == QA_OK) $nums['qaok'] ++;
                }
                
                if (empty($nums[$status])) $nums[$status] =1;
                else $nums[$status] ++;

				if ($p['created_ts']) {
					$d =date( 'Y-m-d', $p['created_ts'] );
					if (empty($this->data['stats']['papers_submission']['by_dates'][$d])) $this->data['stats']['papers_submission']['by_dates'][$d] =1;
					else $this->data['stats']['papers_submission']['by_dates'][$d] ++;

					$h =date( 'Y-m-d H:00', $p['created_ts'] );
					if (empty($this->data['stats']['papers_submission']['by_dates_and_hours'][$h])) $this->data['stats']['papers_submission']['by_dates_and_hours'][$h] =1;
					else $this->data['stats']['papers_submission']['by_dates_and_hours'][$h] ++;				
				}
			}
		}
		
		if (!empty($this->data['stats']['papers_submission'])) {
			ksort( $this->data['stats']['papers_submission']['by_dates'] );
			ksort( $this->data['stats']['papers_submission']['by_dates_and_hours'] );
		}

		$ts_deadline =strtotime($cws_config['global']['dates']['papers_submission']['deadline']);
		
		if (!empty($this->data['stats']['papers_submission']['by_dates'])) {
			foreach ($this->data['stats']['papers_submission']['by_dates'] as $date =>$x) {
				$ts =strtotime( $date );
				$ttd =($ts -$ts_deadline) /86400;
				$this->data['stats']['papers_submission']['by_days_to_deadline'][$ttd] =$x;
			}
			// ksort( $this->data['stats']['papers_submission']['by_days_to_deadline'] );
		}

		if (!empty($this->data['stats']['papers_submission']['by_dates_and_hours'])) {
			foreach ($this->data['stats']['papers_submission']['by_dates_and_hours'] as $date =>$x) {
				$ts =strtotime( $date );
				$tth =($ts -$ts_deadline) /3600;
				$this->data['stats']['papers_submission']['by_hours_to_deadline'][$tth] =$x;
			}
			// sort( $this->data['stats']['papers_submission']['by_hours_to_deadline'] );
		}

		if ($this->data['last_nums']['total'] *0.5 > $nums['total']) {
			echo sprintf( "\n\n*** Stop script, new paper count to low (%s > %s) ***\n\n", $this->data['last_nums']['total'], $nums['total'] );
			die;
		}


		$nums['processed'] =$nums['g'] +$nums['y'] +$nums['r'];

		if (json_encode($nums) != json_encode($this->data['last_nums'])) {
			$tm =date( 'Y-m-d-H' );
			$this->data['stats'][$tm] =array_merge( $nums, [ 'ts' =>$now ]);
		}

		ksort( $days['processed'] );
		$this->data['stats']['days_processed'] =$days['processed'];

		$this->data['last_nums'] =$nums;

		if (!empty($editor_papers)) {
			arsort( $editor_papers );
	
			foreach ($editor_papers as $e =>$n) {
				$eid =str_pad( $n, 3, '0', STR_PAD_LEFT ) .'|' .$e;
	
				$editors[$eid] =[
					'name' =>$e,
					'stats' =>$this->editors_stats[$e],
					'papers' =>$editor_papers_list[$e]
					];
			}
		}


		$authors_check =[];
		if (!empty($this->data['authors_check'])) {
			foreach ($this->data['authors_check'] as $pcode =>$x) {
				if (!empty($x) && !empty($x['done_author']) && !empty($x['done'])) {
					$ac_user =$x['done_author'];

					if (empty($authors_check['people'][$ac_user])) $authors_check['people'][$ac_user] =[ 'count' =>0, 'days' =>[]];

					$authors_check['people'][$ac_user]['count'] ++;

					if (!empty($x['done_ts'])) {
						$day =date('m-d', $x['done_ts']);
	
						if (empty($authors_check['people'][$ac_user]['days'][$day])) $authors_check['people'][$ac_user]['days'][$day] =1;
						else $authors_check['people'][$ac_user]['days'][$day] ++;
	
						if (empty($authors_check['days'][$day])) $authors_check['days'][$day] =1;
						else $authors_check['days'][$day] ++;

						$this->stats_inc( 'days.authors_check.' .date('Y-m-d', $x['done_ts']) );
					}
				}
			}
	
			if (!empty($authors_check['days'])) ksort( $authors_check['days'] );
		}


		echo "\nProcess Slides\n";

		// slides stats
		if (!empty($this->data['slides'])) {
			foreach ($this->data['slides'] as $pcode =>$x) {
				$this->stats_inc( 'days.slides_check.' .date('Y-m-d', $x['ts']) );
			}
		}

		$slides_list =$this->request( '/event/{id}/editing/api/slides/list', 'GET', false, 
			[ 'return_data' =>true, 'disable_cache' =>true ] );
		if (!empty($slides_list)) {
			foreach ($slides_list as $slide) {
				$pcode =$slide['code'];

				// filtrare anche i hide
				if (!empty($pcode) && empty($this->data['papers'][$pcode]['poster']) && empty($this->data['papers'][$pcode]['hide'])) {
					if (!empty($slide['editable'])) {
						$s =$slide['editable'];
						$this->stats_inc( 'slides.editing_status.' .ucwords(strtr($s['state'],'_',' ')));
	
						if (!empty($s['tags'])) {
							foreach ($s['tags'] as $tag) {
								if (substr( $tag['code'], 0, 2 ) == 'QA') {
									$this->stats_inc( 'slides.qa_status.' .$tag['title'] );
								}
							}
						}
	
						$this->stats_inc( 'slides.check.' 
							.(empty($this->data['slides'][$pcode]) ? 'Pending' : 'Done')
							);
	
					} else {
						$this->stats_inc( 'slides.check.Not Ready' );
					}
				}
			}
		}		


		$this->data['team'] =[
			'authors_check' =>$authors_check,
			'editors' =>$editors,
			'stats' =>$this->stats
			];

		// $this->data['editors'] =$editors;
		// $this->data['revisions'] =$revisions;
	}

	//-------------------------------------------------------------------------
	function editor_stats_inc( $_editor, $_var ) {
		if (empty($this->editors_stats[$_editor])) $this->editors_stats[$_editor] =[ 'g' =>0, 'y' =>0, 'r' =>0, 'a' =>0, 'pending' =>0, 'waiting' =>0, 'revisions' =>0, 'qa_fail' =>0, 'qa_ok' =>0, 'x' =>0 ];
		
		$this->editors_stats[$_editor][$_var] ++;
	}

	//-------------------------------------------------------------------------
	function stats_inc( $_key, $_increment =1 ) {
		$k =explode( '.', $_key );

		switch (count($k)) {
			case 1:
				if (empty($this->stats[$k[0]])) $this->stats[$k[0]] =$_increment;
				else $this->stats[$k[0]] +=$_increment;
				break;

			case 2:
				if (empty($this->stats[$k[0]][$k[1]])) $this->stats[$k[0]][$k[1]] =$_increment;
				else $this->stats[$k[0]][$k[1]] +=$_increment;
				break;

			case 3:
				if (empty($this->stats[$k[0]][$k[1]][$k[2]])) $this->stats[$k[0]][$k[1]][$k[2]] =$_increment;
				else $this->stats[$k[0]][$k[1]][$k[2]] +=$_increment;
				break;
		}
	}

    //-------------------------------------------------------------------------
    function import_registrants( $_details =true ) {
        global $cws_config;

		$this->verbose( "Process registrants" );

		$conf_registrants =[];
        $registrants =[];
        $stats =[];
        $gender_field=-1;
        $gender_codes=[];


        $this->cfg['cache_time'] =3600*24;

		$data_key2 =$this->request(sprintf( '/api/checkin/event/{id}/forms/%s/registrations', $cws_config['indico_stats_importer']['registrants_form_id'] ));
		foreach ($this->data[$data_key2] as $r) {
			$conf_registrants[ $r['id'] ] =[
				'ts' =>strtotime( $r['registration_date'] ),
				'paid' =>$r['is_paid'] ? $r['price'] : 0
				];
		}

		$data_key =$this->request( '/api/events/{id}/registrants' );
				
        foreach ($this->data[$data_key]['registrants'] as $r) {
			$rid =$r['registrant_id'];
            $p =$r['personal_data'];

            $type ='D';

			if (empty($conf_registrants[$rid])) { // is registered to another forms
				$ok =false;

			} else if (!empty($cws_config['indico_stats_importer']['registrants_skip_by_tags']) && !empty($r['tags'])) {
                foreach ($r['tags'] as $tag) {
                    if (in_array( $tag, $cws_config['indico_stats_importer']['registrants_skip_by_tags'] )) $ok =false;
                }

            } else {
                $ok =true;
            }

            if ($ok) {
                $registrants[$rid] =[
                    'surname' =>$p['surname'],
                    'name' =>$p['firstName'],
                    'email' =>$p['email'],
                    'inst' =>$p['affiliation'],
                    'nation' =>$p['country'],
                    'country' =>$p['country'],
                    'country_code' =>$p['country_code'],
                    'region' => get_region($p['country_code']),
                    'type' =>$type,
                    'tags' =>$r['tags'],
                    'present' =>$r['checked_in'],
					'ts' =>$conf_registrants[$rid]['ts'],
					'paid' =>$conf_registrants[$rid]['paid'],
					];
    
					//print($registrants[$rid]['region']);
					//print($p['country_code']);
					//print(get_region($p['country_code']));
					if ($registrants[$rid]['region']=="Unknown"){
						print("registrant ".$rid.": country unknown");
					}
					if (get_region($p['country_code'])=="Unknown"){
						print("regstrant ".$rid.": country unknown");
					}
					

                if (!empty($r['tags'])) {
                    foreach ($r['tags'] as $tag) {
                        if (empty($stats['by_tag'][$tag])) $stats['by_tag'][$tag] =1;
                        else $stats['by_tag'][$tag] ++;
                    }
                }

                //status
                $tag_status="";
                $status_tags=[ "Student registration","EPS member","LOC team member","PCO team member","Exhibitor registration"];
                foreach ($registrants[$rid]['tags'] as $tag){
                    if (in_array($tag,$status_tags)){
                        if (empty($tag_status)){
                            $tag_status=$tag;
                        } else {
                            $tag_status="Multiple status";
                        }
                    }
                }//foreach tag
                if (empty($tag_status)){
                    $tag_status="Normal";
                }
                $registrants[$rid]["tag_status"]=$tag_status;
                
                $stats_fields=[ 'by_dates', 'by_days_to_deadline', 'country', 'country_code' , 'region',  'paid', "tag_status"];
                
                //Get extra info on registrant
                if ((!empty($cws_config['indico_stats_importer']['registrants_load_extra_data']))&&($cws_config['indico_stats_importer']['registrants_load_extra_data']==1)){
                    $data_extra_key =$this->request( sprintf('/api/checkin/event/{id}/forms/%s/registrations/%s' ,  $cws_config['indico_stats_importer']['registrants_form_id'], $rid));
                    echo "stats on extra key\n";
                    $registrants_extra_stats=0;
                    foreach ($cws_config['indico_stats_importer']['registrants_extra'] as $statitem){
                        //$stats['registrants_extra_stats_'.strval($registrants_extra_stats)]["name"]=$statitem["name"];
                        //var_dump($this->data[$data_extra_key]);
                        foreach ($this->data[$data_extra_key]["registration_data"] as $part){
                            //echo 'part title: '.$part["title"]."\n"; 
                            foreach($part["fields"] as $formentry) {
                                //echo "      formentry: ".$formentry["title"]."  ".$formentry["data"]."\n";
								if (strlen($formentry["title"])>60){
									$formentry["title"]=substr($formentry["title"],0,25)."...".substr($formentry["title"],strlen($formentry["title"])-25,25);
									//print("Shortened title: ". $formentry["title"]);
								}
                                if (($statitem["type"]=="count")&&($formentry["title"]==$statitem["field"])){
                                    if (gettype($formentry["data"])=="boolean"){
                                        if ($formentry["data"]) {
                                            $value="Yes";
                                        } else {
                                            $value="No";
                                        }
                                    } else {
                                        $value=$formentry["data"];
                                    }
                                    if (empty($stats['registrants_extra_stats_'.strval($registrants_extra_stats)][strval($value)])) $stats['registrants_extra_stats_'.strval($registrants_extra_stats)][strval($value)] =1;
                                    else $stats['registrants_extra_stats_'.strval($registrants_extra_stats)][strval($value)] ++;
                                } else if (($statitem["type"]=="choice")&&($formentry["title"]==$statitem["field"])){
                                    //echo "match ".$formentry["data"]." choice \n";
                                    //var_dump($formentry);
                                    if (count($formentry["data"])==0){
                                        $value="None";
                                    } else {
                                        foreach($formentry["choices"] as $choice){
                                            if ($choice["id"]==array_keys($formentry["data"])[0]){
                                                $value=$choice["caption"];
                                            }
                                        }
                                    }
                                    //echo "value $value \n";
                                    if (empty($stats['registrants_extra_stats_'.strval($registrants_extra_stats)][strval($value)])) $stats['registrants_extra_stats_'.strval($registrants_extra_stats)][strval($value)] =1;
                                    else $stats['registrants_extra_stats_'.strval($registrants_extra_stats)][strval($value)] ++;
                                } else if ($statitem["type"]=="multiple"){
                                    foreach ($statitem["fields"] as $statfield){
										if (strlen($statfield)>60){
											$statfield=substr($statfield,0,25)."...".substr($statfield,strlen($statfield)-25,25);
											//print("Shortened field: ". $statfield);
										}

                                        if ($formentry["title"]==$statfield){
                                            //echo "match multiple".$formentry["data"]." \n";
                                            if ($formentry["data"]){
                                                if (empty($stats['registrants_extra_stats_'.strval($registrants_extra_stats)][$statfield])) $stats['registrants_extra_stats_'.strval($registrants_extra_stats)][$statfield] =1;
                                                else $stats['registrants_extra_stats_'.strval($registrants_extra_stats)][$statfield] ++;
                                            } //data==1
                                        } //match
                                    } //foreach statfield
                                } //multiple
                            } //foreach part formentry
                        } //foreach part 
                        $registrants_extra_stats++;
                    } //foreach statitem
                    //is paid
                    $registrants[$rid]['is_paid']=$this->data[$data_extra_key]["is_paid"];
                    array_push($stats_fields,'is_paid');
                    //gender
                    if ($gender_field==-1){
                        for ($iloop=0;$iloop<count($this->data[$data_extra_key]["registration_data"][0]["fields"]);$iloop++){
                            if (strtolower($this->data[$data_extra_key]["registration_data"][0]["fields"][$iloop]["title"])=="gender"){
                                $gender_field=$iloop;
                            }
                        }
                        //populating gender_codes
                        for ($iloop=0;$iloop<count($this->data[$data_extra_key]["registration_data"][0]["fields"][$gender_field]["choices"]);$iloop++){
                            $gender_codes[$this->data[$data_extra_key]["registration_data"][0]["fields"][$gender_field]["choices"][$iloop]["id"]]=$this->data[$data_extra_key]["registration_data"][0]["fields"][$gender_field]["choices"][$iloop]["caption"];                            
                        }
                    }
                    $registrants[$rid]['gender']=$gender_codes[array_keys($this->data[$data_extra_key]["registration_data"][0]["fields"][$gender_field]["data"])[0]];
                    array_push($stats_fields,'gender');
                } // get extra info on registrant 
                
            }         
        }
        foreach ($stats_fields as $k) {
            $stats[$k] =[];
        }

        $ts_deadline =strtotime($this->cfg['dates']['registration']['deadline']);

        foreach ($registrants as $x) {
            $x['by_dates'] =date( 'Y-m-d', $x['ts'] );
            $x['by_days_to_deadline'] =-floor( ($ts_deadline -$x['ts']) /86400 );

            foreach ($stats_fields as $k) {
                if (empty($stats[$k][$x[$k]])) $stats[$k][$x[$k]] =1;
                else $stats[$k][$x[$k]] ++;  
            }            
        }

        ksort( $stats['by_dates'] );
        ksort( $stats['by_days_to_deadline'] );
        arsort( $stats['country'] );
        arsort( $stats['gender'] );
        ksort( $stats['paid'] );

        $this->data['registrants'] =array( 
            'registrants' =>$registrants,
            'stats' =>$stats
            ); 

        //echo "print stats\n";
        //print_r( $stats );
    }


    //-------------------------------------------------------------------------
    function import_abstracts() {
		$now =time();

		$this->verbose( "Process Abstracts List" );

		$data_key =$this->request( '/event/{id}/manage/abstracts/abstracts.json' );

        $persons =[];
        $abstracts =[];

		$withdrawn =0;

		$this->data['affiliations'] =[];
        $affiliations =&$this->data['affiliations'];
        $this->verbose( count($this->data[$data_key]['abstracts']) ." abstracts found");
        
        foreach ($this->data[$data_key]['abstracts'] as $x) {
			if ($x['state'] != 'withdrawn') {
				$pabs =&$this->data['abstracts_sub'][$x['id']]; // previous abstracts
				$cf =[];
				foreach ($x['custom_fields'] as $cfa) {
					$cf[$cfa['name']] =$cfa['value'];
				}

				if (!$x["submitter"]["affiliation_meta"]){
					$metadata=[];
					foreach($x['persons'] as $person){
						if ($person['affiliation_link']){
							$metadata=$person['affiliation_link'];
						}
						if ($person['author_type']=="primary"){
							$x["submitter"]["affiliation_meta"]=$person['affiliation_link'];
						}
					} 						
					if (!$x["submitter"]["affiliation_meta"]){
						$x["submitter"]["affiliation_meta"]=$metadata;
					}
				}
				$abstracts[ $x['id'] ] =[			
					'title' =>$x['title'],
					'title_bak' =>$pabs['title_bak'] ?? false,
					'content' =>$x['content'],
					'content_bak' =>$pabs['content_bak'] ?? false,					
					'stype' =>$x['submitted_contrib_type']['name'],
					'ts' =>$pabs['ts'] ?? strtotime( $x['submitted_dt'] ),
					'ts0' =>strtotime( $x['submitted_dt'] ),
					'mc'=>substr($x["submitted_for_tracks"][0]["code"],0,3),
					'track'=> substr($x["submitted_for_tracks"][0]["code"],4,3),
					'submitter_country'=> $x["submitter"]["affiliation_meta"]["country_name"],
					'submitter_region'=> get_region($x["submitter"]["affiliation_meta"]["country_code"]),
					'mc_region'=>substr($x["submitted_for_tracks"][0]["code"],0,3)."-".get_region($x["submitter"]["affiliation_meta"]["country_code"])
					];
                /*      
				if (get_region($x["submitter"]["affiliation_meta"]["country_code"])=="Unknown"){
					print("submitter: country unknown");
					print("<BR/>");
					print_r($x['id']);
					print("<BR/>");
					print("Submitter: ");
					print_r($x["submitter"]["affiliation_meta"]);
					print("<BR/>");
					print_r($x["submitter"]);
					print("<BR/>");
					//print_r($x);
					print("<BR/>");
				}
				*/
				$abs =&$abstracts[ $x['id'] ];

				if (!empty($pabs) && strtotime( $x['modified_dt'] ) != $pabs['ts']) {
					$updated =false;
					if (trim(strtolower($abs['title'])) != trim(strtolower($pabs['title']))) $updated =$abs['title_bak'] =$pabs['title'];
					if (trim($abs['content']) != trim($pabs['content'])) $updated =$abs['content_bak'] =$pabs['content'];
					
					if ($updated) {
						$abs['ts'] =strtotime( $x['modified_dt'] );

						$data_obj =[ 'current' =>$abs, 'prec' =>$pabs ];
						file_write_json( sprintf( "%s/abstract-%d-update-%d.json", $this->cfg['tmp_path'], $x['id'], $abs['ts'] ), $data_obj ); // debug
					}
				}

                foreach ($x['persons'] as $p) {
                    if (empty($persons[ $p['person_id'] ])) {
                        unset( $p['author_type'] );
                        unset( $p['is_speaker'] );

                        $persons[ $p['person_id'] ] =$p;
                    }

                    if (empty($affiliations[$p['affiliation']])) $affiliations[$p['affiliation']] =$p['affiliation_link'];

                    if (!empty($p['affiliation_link']['country_name'])) {
                        $affiliations[$p['affiliation']] =$p['affiliation_link'];
                    }					
                }

//				if (!empty($cf['Footnotes'])) print_r($abstracts[ $x['id'] ]);
			} else {
				$abstracts[ $x['id'] ] =[			
					'withdrawn' =>true,
					'ts' =>strtotime( $x['submitted_dt'] )
					];		
					
				$withdrawn ++;
			}
        }

        $this->data['abstracts_sub'] =$abstracts;
        $this->data['persons'] =$persons;

        if (!(is_null($affiliations))) ksort( $affiliations );		

        $chart_by_dates =[];
        $chart_by_days_to_deadline =[];

        $ts_deadline =strtotime($this->cfg['dates']['abstracts_submission']['deadline']);

        $stats_fields=[ 'mc', 'track', 'submitter_country', 'submitter_region', 'mc_region'];
                            
        foreach ($stats_fields as $k) {
            $stats[$k] =[];
        }



        foreach ($abstracts as $x) {
            $date =date( 'Y-m-d', $x['ts'] );
            if (empty($chart_by_dates[$date])) $chart_by_dates[$date] =1;
            else $chart_by_dates[$date] ++;        
            $days_to_deadline =-floor( ($ts_deadline -$x['ts']) /86400 );
            if (empty($chart_by_days_to_deadline[$days_to_deadline])) $chart_by_days_to_deadline[$days_to_deadline] =1;
            else $chart_by_days_to_deadline[$days_to_deadline] ++;                
            foreach ($stats_fields as $k) {
                if (empty($stats[$k][$x[$k]])) $stats[$k][$x[$k]] =1;
                else $stats[$k][$x[$k]] ++;  
            }            
        }
    
        ksort( $chart_by_dates );
        ksort( $chart_by_days_to_deadline );

        foreach ($stats_fields as $k) {
            ksort( $stats[$k] );
        }
        /*
        echo "stats\n";
        print_r($stats);
        */

        $this->data['abstracts_submission'] =[
			'by_dates' =>$chart_by_dates,
        	'by_days_to_deadline' =>$chart_by_days_to_deadline,
        	'count' =>count( $abstracts ),
        	'withdrawn' =>$withdrawn,
            'stats' =>$stats
			];
			
			
    }

	//-------------------------------------------------------------------------
	function import() {
        switch (APP) {
            case 'indico_stats_importer': return $this->import_stats();
            case 'make_page_participants': return $this->import_registrants();
            case 'make_chart_registrants': return $this->import_registrants();
            case 'make_chart_abstracts': return $this->import_abstracts();
        } 

		$prev_papers =$this->data['papers'];

		$abstracts =[];
		$papers =[];
		$authors_db =[];

		$programme =[
            'sessions' =>[],
			'classes' =>[],
			'rooms' =>[],
			'days' =>[]
            ];

		$map_status =MAP_STATUS;

		$this->verbose( "Process contributions" );

		$papers_submission_ok =(strtotime($this->cfg['dates']['papers_submission']['from']) < time());

		$editing_status =[];
		if ($papers_submission_ok) {
			$source_file_type_id =false;
			$types =$this->request( '/event/{id}/editing/api/paper/file-types', 'GET', false, 
				[ 'return_data' =>true, 'quiet' =>false ]);

			foreach ($types as $x) {
				if (strtolower($x['name']) == 'source files') $this->source_file_type_id =$x['id'];
			} 

			$data_key_editing_status =$this->request( '/event/{id}/editing/api/paper/list' );

			foreach ($this->data[$data_key_editing_status] as $x) {
				if (!empty($x['editable'])) $editing_status[ $x['code'] ] =$x['editable'];
			}
		}

		$c_custom_fields =[]; //contributions custom fields
		if (!empty($this->cfg["import_custom_fields"])) {
			$contributions =$this->request( "/event/{id}/manage/contributions/contributions.json", 'GET', false, 
				[ 'return_data' =>true, 'quiet' =>false, 'cache_time' =>1800 ]);
	
			if (!empty($contributions)) {
				foreach ($contributions as $c) {
					foreach ($c['custom_fields'] as $f) {
						if (in_array( $f['name'], $this->cfg["import_custom_fields"] )) {
							$c_custom_fields[ $c['code'] ][ $f['name'] ] =$f['value'];
						}
					}
				}
			}
		}


		$data_key_event =$this->request( '/export/event/{id}.json', 'GET', [ 'detail' =>'sessions' ]);

//		$this->cfg['timezone'] =$this->data[$data_key_event]['results'][0]['timezone'];

//		print_r( $this );

		$data_key_timetable =$this->request( '/export/timetable/{id}.json' );

		foreach ($this->data[$data_key_timetable]['results'][$this->event_id] as $day) {
			foreach ($day as $s) {
				if ($s['entryType'] == 'Break') {
					$b_from =new DateTime( $s['startDate']['date'] .' ' .$s['startDate']['time'], new DateTimeZone($s['startDate']['tz']));
					$b_from->setTimezone(new DateTimeZone($this->cfg['timezone']));
	
					$b_to =new DateTime( $s['endDate']['date'] .' ' .$s['endDate']['time'], new DateTimeZone($s['endDate']['tz']));
					$b_to->setTimezone(new DateTimeZone($this->cfg['timezone']));

					$programme['breaks'][ $b_from->format('Y-m-d') ][ $b_to->format('H:i') ] =[ 
						'title' =>$s['title'],
						"time_from" =>$b_from->format( 'H:i' ),
						"time_to" =>$b_to->format( 'H:i' ),
						"tsz_from" =>$b_from->getTimestamp(),
						"tsz_to" =>$b_to->getTimestamp(),							
						];
				
				} else if (!empty($s['entries'])) {
					if (!in_array( $s['code'], $this->cfg['papers_hidden_sessions'] ) && !empty($s['code'])) {
                        $programme['sessions'][ $s['sessionSlotId'] ] =[ 
							'code' =>$s['code'],
							'slotTitle' =>$s['slotTitle']
							];
                    } 

					foreach ($s['entries'] as $c) {										
						if (!empty($c['code'])) {
							$pcode =$c['code'];

							$c_from =new DateTime( $c['startDate']['date'] .' ' .$c['startDate']['time'], new DateTimeZone($c['startDate']['tz']));
							$c_from->setTimezone(new DateTimeZone($this->cfg['timezone']));
			
							$c_to =new DateTime( $c['endDate']['date'] .' ' .$c['endDate']['time'], new DateTimeZone($c['endDate']['tz']));
							$c_to->setTimezone(new DateTimeZone($this->cfg['timezone']));

							$presenter =empty($c['presenters'][0]) ? false : $c['presenters'][0];

							$this->verbose( "$pcode | $c[title]", 4 );
	
							$p =[
								'id' =>$c['contributionId'],
								"abstract_id" =>$c['friendlyId'],
								'session_code' =>$s['code'],
								'session_id' =>$s['sessionSlotId'],
								'code' =>$pcode,
								'title' =>$c['title'],
								'type' =>false,
								'poster' =>$s['isPoster'],
								"time_from" =>$c_from->format( 'H:i' ),
								"time_to" =>$c_to->format( 'H:i' ),
								"tsz_from" =>$c_from->getTimestamp(),
								"tsz_to" =>$c_to->getTimestamp(),
								"abstract" =>!empty($c['description']),
								"primary_code" =>"Y",
								"presenter" =>$presenter ? sprintf( "%s %s - %s", $presenter['firstName'], $presenter['familyName'], $presenter['affiliation'] ) : false,
								"presenter_email" =>$presenter ? $presenter['email'] : false,
                                "author" =>false,
                                "author_inst" =>false,
                                "authors" =>false,
								"authors_names" =>false,
								"authors_emails" =>false,
								"authors_by_inst" =>false,
								"source_type" =>false,
								"pdf_url" =>false,
								"pdf_ts" =>0,
								"created_ts" =>false,
								"status" =>false,
								"status_history" =>[],
                                "status_ts" =>0,
								"status_indico" =>false,
								"paper_state" =>empty($editing_status[$pcode]) ? false : $editing_status[$pcode]['state'],
								"revision_count" =>empty($editing_status[$pcode]) ? false : $editing_status[$pcode]['revision_count'],
								"rev_id" =>false, // revision id (rev_count - status - tags)
								"tags" =>false,
								"status_qa" =>false,
								"qa_ok" =>false,
								"qa_fail_count" =>0,
								"editor" =>false,
								"hide" =>in_array( $s['code'], $this->cfg['papers_hidden_sessions'] ),
								"custom_fields" =>$c_custom_fields[$pcode] ?? false
                                ];

							$papers[$pcode] =$p;
		
							$abstracts[ $pcode ] =[
								"text" =>$c['description'],
								"footnote" =>"",
								"agency" =>""                        
								];
						}
					}
				}
			}
		}

		$this->verbose( count($papers) ." contributions found\n" );

		$this->verbose( "Process sessions" );

		if (!empty($this->data[$data_key_event]['results'][0]['sessions'])) {
			foreach ($this->data[$data_key_event]['results'][0]['sessions'] as $sb) {
				$s =$sb['session'];
	
				$chair =empty($s['sessionConveners'][0]) ? false : $s['sessionConveners'][0];
	
				$s_from =new DateTime( $sb['startDate']['date'] .' ' .$sb['startDate']['time'], new DateTimeZone($sb['startDate']['tz']));
				$s_from->setTimezone(new DateTimeZone($this->cfg['timezone']));

				$s_to =new DateTime( $sb['endDate']['date'] .' ' .$sb['endDate']['time'], new DateTimeZone($sb['endDate']['tz']));
				$s_to->setTimezone(new DateTimeZone($this->cfg['timezone']));
	
				// $day =$s['startDate']['date'];
				$day =$s_from->format( 'Y-m-d' );
	
				$this->verbose( "$day - $sb[code] ($sb[room] | $s[room]) - $sb[title]" );
	
				$session_key =$s_from->format( 'Hi' )
					.'_' .str_replace( ' ', "", $sb['room'] )
					.'_' .$sb['code']
					.'_' .$sb['id']
					;
	
				$session_papers =[];
				foreach ($sb['contributions'] as $c) {
					$pcode =$c['code'];

                    if (!empty($papers[$pcode])) {
                        $p =$papers[$pcode];
						
						if (!empty($papers[$pcode]['revision_count']) && $papers_submission_ok) {
							if (empty($prev_papers[$pcode]) || empty( $editing_status[$pcode] )) {
								$new_revision =true;

							} else {
								$p['rev_id'] =$this->get_rev_id( $editing_status[$pcode] );

								$new_revision =$p['rev_id'] != $prev_papers[$pcode]['rev_id'];
							}

							$rqst_cache =$new_revision ? 0 : 3600*8; //3600*24*30;

							$this->process_paper_revisions( $p, $rqst_cache, false );
						}

                        $p['class'] =$c['track'];
                                              
                        $author =empty($c['primaryauthors'][0]) ? false : $c['primaryauthors'][0];
                        if ($author) {
                            $p['author'] =sprintf( "%s %s", $author['first_name'], $author['last_name'] );
                            $p['author_inst'] =$author['affiliation'];
                        }
                                
                        $p['authors'] =false;
						$p['authors_by_inst'] =false;
						$primary =true;

						usort($c['coauthors'], function($_a, $_b) { return strcmp($_a['last_name'], $_b['last_name']); });

                        foreach (array_merge( $c['primaryauthors'], $c['coauthors'] ) as $author) {
							$author_name =$this->author_name( $author );

							$p['authors'] .=($p['authors'] ? ', ' : false) .$author_name;
							$p['authors_by_inst'][$author['affiliation']][] =$author_name;
							
                            $p['authors_names'][] =$author_name;
							if (!empty($author['email'])) $p['authors_emails'][] =$author['email'];

							$aid =trim($author['last_name']) .'|' .trim($author['first_name']) .'|' .trim($author['affiliation']);

							if (empty($authors_db[$aid])) {
								$authors_db[$aid] =[
									'id' =>$author['id'],
									'affiliation' =>$author['affiliation'],
									'name' =>$author_name,
									'first_name' =>trim($author['first_name']),
									'last_name' =>trim($author['last_name']),
									'email' =>$author['email']
									];
							}

							if (!empty($p['status'])) $authors_db[$aid]['papers'][$pcode] =[
								'status' =>$p['qa_ok'] ? 'qaok' : $p['status'],
								'id' =>$p['id'],
								'primary' =>$primary
								];

							$primary =false;
						}
						
                        $p['type'] =$c['type'];
     
                        $session_papers[$pcode] =$p;
                        $papers[$pcode] =$p;
                    }
				}
	
				ksort( $session_papers );
	
				$room =preg_replace("/[^a-zA-Z0-9]+/", "", $sb['room'] );
				if (isset($programme['rooms'][$room])) $programme['rooms'][$room] ++;
				else $programme['rooms'][$room] =1;
	
				$programme['days'][$day][$session_key] =[
					'id' =>$sb['id'],
					'code' =>$sb['code'],
					'type' =>$s['isPoster'] ? "Poster Session" : $s['type'],
					'poster_session' =>$s['isPoster'],
					"class" =>"",
					"title" =>$sb['title'],
					"chair" =>$chair ? "$chair[first_name] $chair[last_name]" : false,
					"chair_inst" =>$chair ? "$chair[affiliation]" : false,
					"time_from" =>$s_from->format( 'H:i' ),
					"time_to" =>$s_to->format( 'H:i' ),
					"tsz_from" =>$s_from->getTimestamp(),
					"tsz_to" =>$s_to->getTimestamp(),
					"room" =>$room,
					"location" =>$sb['room'],
					'papers' =>$session_papers
					];          

                if (!empty($programme['sessions'][ $sb['id'] ])) {
                    $programme['sessions'][ $sb['id'] ] =$programme['days'][$day][$session_key];
                    $programme['sessions'][ $sb['id'] ]['papers'] =array_keys( $session_papers );
                }                    
			}
		}

        ksort( $programme['days'] );

		foreach ($programme['days'] as $day =>$d) {
			$programme['days'][$day]['999999_END'] ='END';
			ksort( $programme['days'][$day] );
		}

		$this->verbose( "" );

		if ($prev_papers && count($prev_papers) *0.5 > count($papers)) {
			echo sprintf( "\n\n*** Stop script, new paper count to low (%s > %s) ***\n\n", count($prev_papers), count($papers));
			die;
		}

		$this->data['abstracts'] =$abstracts;	
		$this->data['papers'] =$papers;
		$this->data['programme'] =$programme;
		$this->data['editing_tags'] =$this->editing_tags;

		ksort( $authors_db );
		$this->data['authors'] =$authors_db;

		// print_r( $programme['rooms'] );
	}

	//-----------------------------------------------------------------------------
	function cleanup( $_unlink =true ) {
		$cfg =$this->cfg;

		$this->verbose( "Remove temporary files ($cfg[tmp_path])... ", 1, false );
		if ($_unlink) system( "rm -f $cfg[tmp_path]/*" );
		$this->verbose_ok();

		foreach ($cfg as $var =>$val) {
			if (substr( $var, 0, 4 ) == 'out_' && strpos( $var, '_path') === false && file_exists( $val )) {
				$this->verbose( "Remove ($var) $val... " );
				
                if ($_unlink) $this->verbose_status( !unlink( $val ) );
                else $this->verbose_next( "SKIP" );
			}
		}
	} 

	//-----------------------------------------------------------------------------
	function save_all( $_cfg =false ) {
        foreach ($this->cfg as $c =>$fname ) {
			if ($c != 'out_path' && substr( $c, 0, 4 ) == 'out_') {
				$this->save_file( substr( $c, 4 ), $c, false, $_cfg );
			}
		}
	}

	//-----------------------------------------------------------------------------
	function save_file( $_data_id, $_file_id, $_label =false, $_cfg =false ) {
        $cfg =[
            'counter' =>true,
            'save_empty' =>false
			];

        if (!empty($_cfg) && is_array($_cfg)) {
            foreach ($_cfg as $key =>$val) {
                $cfg[$key] =$val;
            }
        }

		if (empty($_label)) $_label =strtoupper( $_data_id );

		$fname =$this->cfg[$_file_id];
		$this->verbose( "# Save $_label Data ($fname)... ", 2, false );
        if (empty($this->data[$_data_id])){
            $this->verbose_next( "NO_DATA" );
            if  (empty($cfg['save_empty'])) {
                return;
            } 
        } else {
            $this->verbose( "Array ".$_data_id." has ".count($this->data[$_data_id])." entries");
        }

		$counter =false;

        if ($cfg['counter']) {
            if (is_numeric($cfg['counter'])) $counter =sprintf( ' (%d)', $cfg['counter'] );
			else if (!empty($this->data[$_data_id])) $counter =sprintf( ' (%d)', count($this->data[$_data_id]) );
		}

		$this->verbose_status( !file_write_json( $fname, $this->data[$_data_id] ), "Unable to write file $fname", "OK" .$counter );
	}



	//-----------------------------------------------------------------------------
	function export_refs( $_fname =false, $_final =false ) {
		if ($this->cfg['refs_final']) return $this->export_refs_final( $_fname );
		
		$out_fname =$_fname ? $_fname : $this->cfg['export_refs'];
		
		$this->verbose( "# Save REFS data (" .$out_fname .")... ", 1, false );
		$citations =false;

		foreach ($this->data['papers'] as $pid =>$p) {
			if (!empty($p['authors']) && !in_array( $p['session_code'], $this->cfg['refs_hidden_sessions'] )) {
				// echo $p['session_code'] ."\n";

				$citations[] =array(
					'paper' =>$pid,
					'authors' =>$p['authors'],
					'title' =>$p['title'],
					'position' =>$p['position'] ?? false,
					'contribution ID' =>$p['abstract_id']
					);
			}
		}
		
		if ($citations) {
			$fp =fopen( $out_fname, 'w' );
			fputcsv( $fp, array_keys( $citations[0] ) );
			foreach ($citations as $cit) {
				fputcsv( $fp, $cit );
			}
			fclose( $fp );

			$this->verbose_ok( "(" .count($citations) .") " );
			
		} else {
			$this->verbose_error( "(No data)" );
		}
	}



	//-----------------------------------------------------------------------------
	// copy dois files from meow /opt/cat/meow/var/run
	function export_refs_final( $_fname =false ) {
		
		$out_fname =$_fname ? $_fname : $this->cfg['export_refs'];
		
		$this->verbose( "# Save REFS data ($out_fname)... ", 1, false );
		$refs =[];

		$doi_info =file_read_json( sprintf( "%s/doi/%d.json", $this->cfg['data_path'], $this->cfg['indico_event_id'] ), true );

		if (empty($doi_info)) {
			$this->verbose_error( "Unable to read DOI data" );
			return false;
		}

		$pubdate =false;

		foreach ($this->data['papers'] as $pid =>$p) {
			if (!empty($p['authors']) && !in_array( $p['session_code'], $this->cfg['refs_hidden_sessions'] )) {
				$position =false;

				$refs[$pid] =[
					'PaperId' =>$pid,
					'AuthorList' =>$p['authors'],
					'Title' =>$p['title'],
					'PageNumbers' =>false,
					'UniqueIdentifier' =>$p['abstract_id'],
					'PubStatus' =>3,
					];

				$doi_fname =sprintf( "%s/doi/%s.json", $this->cfg['data_path'], $pid );
                if (file_exists( $doi_fname)) {
					$doi =file_read_json( $doi_fname, true );

					if (empty($doi)) {
						echo "ERROR: $pid";
						return false;
					}

					if ($doi && !empty($doi['data']['attributes']['sizes'][0])) $position =trim(str_replace( ' pages', "", $doi['data']['attributes']['sizes'][0] ));

					if (!$pubdate) {
						foreach ($doi['data']['attributes']['dates'] as $d) {
							if ($d['dateType'] == 'Issued') {
								$ts =strtotime( $d['date'] );
								$pubdate =date( 'n,Y', $ts );
							}
						}
					}

					$refs[$pid]['PageNumbers'] =$position;
					$refs[$pid]['PubStatus'] =1;
				}
			}
		}
		
		if (!empty($refs)) {
			$fp =fopen( $out_fname, 'w' );
			// fputcsv( $fp, array_keys( reset($refs) ));
			foreach ($refs as $cit) {
				fputcsv( $fp, $cit );
			}
			fclose( $fp );

			$this->verbose_ok( "(" .count($refs) .") " );
			
		} else {
			$this->verbose_error( "(No data)" );
		}
	}


	//-----------------------------------------------------------------------------
	function import_posters() {
		$PP =false;
		$poster_count =0;
			
 		foreach ($this->data['programme']['days'] as $day =>$odss) { // ObjDaySessions
			foreach ($odss as $id =>$os) { // ObjSession
				if (is_array($os) 
					&& !empty($os['poster_session']) 
					&& (empty($this->cfg['posters_hidden_sessions']) || !in_array( $os['code'], $this->cfg['posters_hidden_sessions'] ))
					) {

					$sid =$os['code'];
					
					$PP[$day][$sid] =[ 
						'code' =>$sid,
						'type' =>$os['type'],
						'title' =>$os['title'],
						'location' =>$os['location']
						];			
					
					$digits =false;
					foreach ($os['papers'] as $pid =>$op) { // ObjPoster					
						if (!$digits) {
							$digits =strlen($pid) -strlen($sid);
						}

						$pn =substr( $pid, -$digits );
						$PP[$day][$sid]['posters'][$pn] =[
							'code' =>$pid,
							'title' =>$op['title'],
							'presenter' =>$op['presenter'],
							'abstract_id' =>$op['abstract_id']
							];

						$poster_count ++;
					}

					if (!empty($PP[$day][$sid]['posters'])) ksort( $PP[$day][$sid]['posters'] );
				}
			}
		}
		
        $this->data['posters'] =$PP;

//        $this->save_file( 'posters', 'out_posters', 'POSTERS', $poster_count );
	}	

	//-----------------------------------------------------------------------------
	function GoogleChart( $_data =false ) {
		extract( $this->cfg );
		
//		list( $type, $what ) =explode( ',', $xtract );
		
		$var =$y_title;

//		if ($startdate && strpos( $startdate, '-' )) $startdate =strtr( $startdate, '-', ',' );
		
//		$data =$this->xtract( $type, $what, true );

        if ($_data) $data =$_data;
        else $data =$this->data[$source];
		
		if (!$data) {
			$this->verbose_error( "ERROR: no data" );
			return;
		}
		
		$i =0;
		$n =0;
		$addrow =false;
		foreach ($data as $date =>$value) {
				if ($value[0]) {
					if ($i == 0 && $startdate) {
                        list( $dy, $dm, $dd ) =explode( '-', $startdate );
                        $dm --;
                        $addrow .=" data.addRow([new Date($dy,$dm,$dd),0]);\n";
                    }
				
					list( $dy, $dm, $dd ) =explode( '-', $date );
					$dm --;
					$dd +=0;

					$n +=$value[0];
					$addrow .=" data.addRow([new Date($dy,$dm,$dd),$n]);\n";
					
					$i ++;
				}
		}

	//	$this->verbose_ok( "OK ($i records)" );
		
		echo "\n";

		$width =CHART_WIDTH;
		$height =CHART_HEIGHT;
		
		$color1 =$this->cfg['colors']['primary'];
		$color2 =$this->cfg['colors']['secondary'];
		
		$js =APP_OUT_JS;
				
		foreach (array( 'html', 'js' ) as $ftype) {
			$tmpl =$this->cfg['chart_'.$ftype];
			if ($tmpl) {
				$template =file_read( $tmpl );
				
				eval( "\$out =\"$template\";" );
				file_write( $this->cfg['out_path'] .'/' .($ftype == 'html' ? APP_OUT_HTML : APP_OUT_JS), $out, 'w', true, $ftype );
				
				echo "\n";
			}
		}
	}

	//-------------------------------------------------------------------------
	function parse_template( $_vars, $_template ='template', $_out ='out_html') {
		$tmpl =$this->cfg[$_template];
		
//		echo "Read template $tmpl\n";

		if ($tmpl) {
			$page =file_read( $tmpl );
			
			foreach ($_vars as $var =>$value) {
				$page =str_replace( '{'.$var.'}', $value, $page );
			}
			
			file_write( $this->cfg[$_out], $page, 'w', true );
			
			//			echo "\n";
			
			return $page;
		}
	}
	
	//-------------------------------------------------------------------------
	function author_name( $_author ) {
		$fn0 =str_replace( '-', ' -', trim($_author['first_name']) );
		$fn_p_list =explode( ' ', $fn0 );
		
		$fn =false;
		foreach ($fn_p_list as $fn_p) {
			$fn .=(mb_substr( $fn_p, 0, 1 ) == '-' ? mb_substr( $fn_p, 0, 2 ) : mb_substr( $fn_p, 0, 1 )) .'.';
		}
		
		return $fn .' ' .trim($_author['last_name']);  
	}
	
	//-------------------------------------------------------------------------
	function get_rev_id( $_x ) {
		if (empty($_x)) return false;

		$rev_id =$_x['revision_count'] .'-' .$_x['state'];
		if (!empty($_x['tags'])) {
			foreach ($_x['tags'] as $tag) {
				if (substr( $tag['code'], 0, 2 ) == 'QA') $rev_id .='-' .$tag['code'];
			}
		}		

		return $rev_id;
	}

	//-------------------------------------------------------------------------
	function process_paper_revisions( &$_paper, $_cache_time =0, $_download_pdf =false ) {

		$map_status =MAP_STATUS;

/* 		$pedit =$this->request( "/event/{id}/api/contributions/$_paper[id]/editing/paper", 'GET', false, 
			[ 'return_data' =>true, 'quiet' =>true, 'cache_time' =>$_cache_time ]); */

		$pedit =$this->get_paper_details( $_paper['id'], $_cache_time, true );

		if (empty($pedit['error'])) {
			if (!empty($pedit['state'])) {
				$_paper['status_indico'] =$pedit['state']['title'];

				$paper_status =$pedit['state']['name'];
				if (!empty($pedit['editor']) && $paper_status == 'ready_for_review') $paper_status ='assigned';										
				$_paper['status'] =isset($map_status[ $paper_status ]) ? $map_status[ $paper_status ] : "_$paper_status";
			}

			if (!empty($pedit['editor'])) $_paper['editor'] =$pedit['editor']['full_name'];

			if (!empty($pedit['revisions'])) {
				$ri =0; // number revisions (excluded undone)
				$lrid =false;
				$_paper['status_history'] =[];
				$_paper['qa_fail_count'] =0;

				foreach ($pedit['revisions'] as $rid =>$revision) {
					if (empty($revision['is_undone'])) {
						if ($ri == 0) {
							$_paper['created_ts'] =strtotime( $revision['created_dt'] );

							foreach ($revision['files'] as $f) {
								if ($f['file_type'] == $this->source_file_type_id) $_paper['source_type'] =strtolower(pathinfo( $f['filename'], PATHINFO_EXTENSION ));
							}

							$pedit['first_revision'] =$rid;
						}

						if ($_paper['status'] == 'g' && !empty($revision['files'])) { 
							foreach ($revision['files'] as $f) {
								if ($f['filename'] == $_paper['code'] .".pdf") $_paper['pdf_url'] =$f['external_download_url'];
							}
						}

						$paper_tags =[];
						foreach ($revision['tags'] as $tag) {
							if (!$tag['system']) {
								$paper_tags[] =$tag['verbose_title'];	
							}
						}

						$_paper['status_qa'] =$revision['qa'];

						if ($_paper['status_qa'] == QA_OK) $_paper['qa_ok'] =true;
						else if ($_paper['status_qa'] == QA_FAIL) {
							$_paper['qa_ok'] =false;
							$_paper['qa_fail_count'] ++;
						}

/* 						foreach ($revision['tags'] as $tag) {
							if (substr( $tag['code'], 0, 2 ) == 'QA') {
								$_paper['status_qa'] =$tag['title'];
								if ($_paper['status_qa'] == QA_OK) {
									$_paper['qa_ok'] =true;
								}

							} else if (!$tag['system']) {
								$paper_tags[] =$tag['verbose_title'];	
							}
						} */

						foreach (array_unique($paper_tags) as $tag) {
							if (empty($this->editing_tags[$tag])) $this->editing_tags[$tag] =1;
							else $this->editing_tags[$tag] ++;
						}

						$ri ++;
						$lrid =$rid;

						$rs =$revision['type']['name']; // revision status
						$_paper['status_history'][] =isset($map_status[ $rs ]) ? $map_status[ $rs ] : "_$rs";
						
/* 					} else {
						foreach ($revision['tags'] as $tag) {
							if ($tag['code'] == 'QA02') {
								$_paper['status_qa'] =QA_FAIL;
								$_paper['qa_ok'] =false;
								$_paper['qa_fail_count'] ++;
							}
						}

						unset($pedit['revisions'][$rid]); */
					}
				}

				if (!empty($_paper['pdf_url'])) {
					$pdf_fname =sprintf( "%s/%s.pdf", $this->cfg['pdf_path'], $_paper['code'] );

					if ($_download_pdf && (!$_cache_time || !file_exists( $pdf_fname ))) $_paper['pdf_ts'] =$this->download_pdf( $_paper );
					else if (file_exists( $pdf_fname )) $_paper['pdf_ts'] =filemtime( $pdf_fname );
				}
			
				$pedit['revisions_count'] =$ri;

				// about last revision
				$pedit['last_revision'] =$lrid;
				$revision =$pedit['revisions'][$lrid];
				
				$_paper['status_ts'] =strtotime( $revision['created_dt'] );
			}
		}

		return $pedit;
	}


	//-------------------------------------------------------------------------
	function process_paper_revisions_old( &$_paper, $_cache_time =0, $_download_pdf =false ) {

		$map_status =MAP_STATUS;

		$pedit =$this->request( "/event/{id}/api/contributions/$_paper[id]/editing/paper", 'GET', false, 
			[ 'return_data' =>true, 'quiet' =>true, 'cache_time' =>$_cache_time ]);

		if (empty($pedit['error'])) {
			if (!empty($pedit['state'])) {
				$_paper['status_indico'] =$pedit['state']['title'];

				$paper_status =$pedit['state']['name'];
				if (!empty($pedit['editor']) && $paper_status == 'ready_for_review') $paper_status ='assigned';										
				$_paper['status'] =isset($map_status[ $paper_status ]) ? $map_status[ $paper_status ] : "_$paper_status";
			}

			if (!empty($pedit['editor'])) $_paper['editor'] =$pedit['editor']['full_name'];

			if (!empty($pedit['revisions'])) {
				$ri =0; // number revisions (excluded undone)
				$lrid =false;
				$_paper['status_history'] =[];
				$_paper['qa_fail_count'] =0;

				foreach ($pedit['revisions'] as $rid =>$revision) {
					if (empty($revision['is_undone'])) {
						if ($ri == 0) {
							$_paper['created_ts'] =strtotime( $revision['created_dt'] );

							foreach ($revision['files'] as $f) {
								if ($f['file_type'] == $this->source_file_type_id) $_paper['source_type'] =strtolower(pathinfo( $f['filename'], PATHINFO_EXTENSION ));
							}

							$pedit['first_revision'] =$rid;
						}

						if ($_paper['status'] == 'g' && !empty($revision['files'])) { 
							foreach ($revision['files'] as $f) {
								if ($f['filename'] == $_paper['code'] .".pdf") $_paper['pdf_url'] =$f['external_download_url'];
							}
						}

						$paper_tags =[];
						foreach ($revision['tags'] as $tag) {
							if (substr( $tag['code'], 0, 2 ) == 'QA') {
								$_paper['status_qa'] =$tag['title'];
								if ($_paper['status_qa'] == QA_OK) {
									$_paper['qa_ok'] =true;
								}

							} else if (!$tag['system']) {
								$paper_tags[] =$tag['verbose_title'];	
							}
						}

						foreach (array_unique($paper_tags) as $tag) {
							if (empty($this->editing_tags[$tag])) $this->editing_tags[$tag] =1;
							else $this->editing_tags[$tag] ++;
						}

						$ri ++;
						$lrid =$rid;

						$rs =$revision['type']['name']; // revision status
						$_paper['status_history'][] =isset($map_status[ $rs ]) ? $map_status[ $rs ] : "_$rs";
						
					} else {
						foreach ($revision['tags'] as $tag) {
							if ($tag['code'] == 'QA02') {
								$_paper['status_qa'] =QA_FAIL;
								$_paper['qa_ok'] =false;
								$_paper['qa_fail_count'] ++;
							}
						}

						unset($pedit['revisions'][$rid]);
					}
				}

				if (!empty($_paper['pdf_url'])) {
					$pdf_fname =sprintf( "%s/%s.pdf", $this->cfg['pdf_path'], $_paper['code'] );

					if ($_download_pdf && (!$_cache_time || !file_exists( $pdf_fname ))) $_paper['pdf_ts'] =$this->download_pdf( $_paper );
					else if (file_exists( $pdf_fname )) $_paper['pdf_ts'] =filemtime( $pdf_fname );
				}
			
				$pedit['revisions_count'] =$ri;

				// about last revision
				$pedit['last_revision'] =$lrid;
				$revision =$pedit['revisions'][$lrid];
				
				$_paper['status_ts'] =strtotime( $revision['created_dt'] );
			}
		}

		return $pedit;
	}


	//-----------------------------------------------------------------------------
	function get_paper_details( $_pid, $_cache =0, $_remove_undone =false ) {
		
		$pedit =$this->request( "/event/{id}/api/contributions/$_pid/editing/paper", 'GET', false, 
			[ 'return_data' =>true, 'quiet' =>true, 'cache_time' =>$_cache ]);
		
		$revisions =[];

		$i =0;
		foreach ($pedit['revisions'] as $rid =>$r) {
			if ($r['type']['name'] != 'reset') {
				$extra =false;
	
				$r['qa'] =false;
				$r['created_ts'] =strtotime( $r['created_dt'] );
	
				foreach ($r['tags'] as $tag) {
					switch ($tag['code']) {
						case 'QA01':
						case 'QA02':
						case 'QA03':
							$r['qa'] =$tag['title'];
							break;

/* 						case 'QA02':
							$r['qa'] =$tag['title'];
	
							if ($r['is_undone']) {							
								$r['is_undone'] =false;
								$revisions[$i -1]['is_undone'] =false;
								
								$extra =$r;
								$extra['type']['name'] ='ready_for_review';
								$extra['qa'] =QA_FAIL;
								$extra['tags'] =[[
									'code' =>'QA03',
									'color' =>'red',
									'system' =>true,
									'title' =>$extra['qa']
									]];
							}
							break;
	
						case 'QA01':
							$r['qa'] =$tag['title'];
							break; */
					}
				}
				
				$revisions[$i++] =$r;
		
				if ($extra) $revisions[$i++] =$extra;
			}
		}
		
 		if ($_remove_undone) {
			$t =[];

			foreach ($revisions as $rid =>$r) {
				if (empty($r['is_undone'])) $t[] =$r;
			}

			$pedit['revisions'] =$t;

		} else {
			$pedit['revisions'] =$revisions;
		}

		return $pedit;
	}

} /* END CLASS */

?>