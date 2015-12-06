<?php /** @file */

require_once('include/cli_startup.php');
require_once('include/zot.php');


function deliver_run($argv, $argc) {

	cli_startup();

	$a = get_app();

	if($argc < 2)
		return;

	logger('deliver: invoked: ' . print_r($argv,true), LOGGER_DATA);


	for($x = 1; $x < $argc; $x ++) {

		$dresult = null;
		$r = q("select * from outq where outq_hash = '%s' limit 1",
			dbesc($argv[$x])
		);
		if($r) {

			/**
			 * Check to see if we have any recent communications with this hub (in the last month).
			 * If not, reduce the outq_priority.
			 */

			$base = '';

			$h = parse_url($r[0]['outq_posturl']);
			if($h) {
				$base = $h['scheme'] . '://' . $h['host'] . (($h['port']) ? ':' . $h['port'] : '');
				if($base !== z_root()) {
					$y = q("select site_update, site_dead from site where site_url = '%s' ",
						dbesc($base)
					);
					if($y) {
						if(intval($y[0]['site_dead'])) {
							q("delete from outq where outq_posturl = '%s'",
								dbesc($r[0]['outq_posturl'])
							);
							logger('dead site ignored ' . $base);
							continue;							
						}
						if($y[0]['site_update'] < datetime_convert('UTC','UTC','now - 1 month')) {
							q("update outq set outq_priority = %d where outq_hash = '%s'",
								intval($r[0]['outq_priority'] + 10),
								dbesc($r[0]['outq_hash'])
							);
							logger('immediate delivery deferred for site ' . $base);
							continue;
						}
					}
					else {

						// zot sites should all have a site record, unless they've been dead for as long as 
						// your site has existed. Since we don't know for sure what these sites are, 
						// call them unknown

						q("insert into site (site_url, site_update, site_dead, site_type) values ('%s','%s',0,%d) ",
							dbesc($base),
							dbesc(datetime_convert()),
							intval(($r[0]['outq_driver'] === 'post') ? SITE_TYPE_NOTZOT : SITE_TYPE_UNKNOWN)
						);
					}
				}
			} 

			// "post" queue driver - used for diaspora and friendica-over-diaspora communications.

			if($r[0]['outq_driver'] === 'post') {


				$result = z_post_url($r[0]['outq_posturl'],$r[0]['outq_msg']); 
				if($result['success'] && $result['return_code'] < 300) {
					logger('deliver: queue post success to ' . $r[0]['outq_posturl'], LOGGER_DEBUG);
					if($base) {
						q("update site set site_update = '%s', site_dead = 0 where site_url = '%s' ",
							dbesc(datetime_convert()),
							dbesc($base)
						);
					}
					q("update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s' limit 1",
						dbesc('accepted for delivery'),
						dbesc(datetime_convert()),
						dbesc($argv[$x])
					);

					$y = q("delete from outq where outq_hash = '%s'",
						dbesc($argv[$x])
					);

				}
				else {
					logger('deliver: queue post returned ' . $result['return_code'] . ' from ' . $r[0]['outq_posturl'],LOGGER_DEBUG);
					$y = q("update outq set outq_updated = '%s' where outq_hash = '%s'",
						dbesc(datetime_convert()),
						dbesc($argv[$x])
					);
				}
				continue;
			}

			$notify = json_decode($r[0]['outq_notify'],true);

			// Messages without an outq_msg will need to go via the web, even if it's a
			// local delivery. This includes conversation requests and refresh packets.

			if(($r[0]['outq_posturl'] === z_root() . '/post') && ($r[0]['outq_msg'])) {
				logger('deliver: local delivery', LOGGER_DEBUG);
				// local delivery
				// we should probably batch these and save a few delivery processes

				if($r[0]['outq_msg']) {
					$m = json_decode($r[0]['outq_msg'],true);
					if(array_key_exists('message_list',$m)) {
						foreach($m['message_list'] as $mm) {
							$msg = array('body' => json_encode(array('success' => true, 'pickup' => array(array('notify' => $notify,'message' => $mm)))));
							zot_import($msg,z_root());
						}
					}	
					else {	
						$msg = array('body' => json_encode(array('success' => true, 'pickup' => array(array('notify' => $notify,'message' => $m)))));
						$dresult = zot_import($msg,z_root());
					}
					$r = q("delete from outq where outq_hash = '%s'",
						dbesc($argv[$x])
					);
					if($dresult && is_array($dresult)) {
						foreach($dresult as $xx) {
							if(is_array($xx) && array_key_exists('message_id',$xx)) {
								if(delivery_report_is_storable($xx)) {
									q("insert into dreport ( dreport_mid, dreport_site, dreport_recip, dreport_result, dreport_time, dreport_xchan ) values ( '%s', '%s','%s','%s','%s','%s' ) ",
										dbesc($xx['message_id']),
										dbesc($xx['location']),
										dbesc($xx['recipient']),
										dbesc($xx['status']),
										dbesc(datetime_convert($xx['date'])),
										dbesc($xx['sender'])
									);
								}
							}
						}
					}

					q("delete from dreport where dreport_queue = '%s' limit 1",
						dbesc($argv[$x])
					);
				}
			}
			else {
				logger('deliver: dest: ' . $r[0]['outq_posturl'], LOGGER_DEBUG);
				$result = zot_zot($r[0]['outq_posturl'],$r[0]['outq_notify']); 
				if($result['success']) {
					logger('deliver: remote zot delivery succeeded to ' . $r[0]['outq_posturl']);
					zot_process_response($r[0]['outq_posturl'],$result, $r[0]);				
				}
				else {
					logger('deliver: remote zot delivery failed to ' . $r[0]['outq_posturl']);
					logger('deliver: remote zot delivery fail data: ' . print_r($result,true), LOGGER_DATA);
					$y = q("update outq set outq_updated = '%s' where outq_hash = '%s'",
						dbesc(datetime_convert()),
						dbesc($argv[$x])
					);
				}
			}
		}
	}
}

if (array_search(__file__,get_included_files())===0){
  deliver_run($argv,$argc);
  killme();
}
