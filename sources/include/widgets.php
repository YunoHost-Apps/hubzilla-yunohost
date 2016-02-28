<?php
/**
 * @file include/widgets.php
 *
 * @brief This file contains the widgets.
 */

require_once('include/dir_fns.php');
require_once('include/contact_widgets.php');
require_once('include/attach.php');


function widget_profile($args) {
	$a = get_app();
	$block = (((get_config('system', 'block_public')) && (! local_channel()) && (! remote_channel())) ? true : false);
	return profile_sidebar($a->profile, $block, true);
}

// FIXME The problem with the next widget is that we don't have a search function for webpages that we can send the links to.
// Then we should also provide an option to search webpages and conversations.

function widget_tagcloud($args) {

	$o = '';
	//$tab = 0;
	$a = get_app();
	$uid = $a->profile_uid;
	$count = ((x($args,'count')) ? intval($args['count']) : 24);
	$flags = 0;
	$type = TERM_CATEGORY;

	// FIXME there exists no $authors variable
	$r = tagadelic($uid, $count, $authors, $owner, $flags, ITEM_TYPE_WEBPAGE, $type);

	if($r) {
		$o = '<div class="tagblock widget"><h3>' . t('Categories') . '</h3><div class="tags" align="center">';
		foreach($r as $rr) {
			$o .= '<span class="tag'.$rr[2].'">'.$rr[0].'</span> ' . "\r\n";
		}
		$o .= '</div></div>';
	}
	return $o;
}

function widget_collections($args) {
	require_once('include/group.php');

	$mode = ((array_key_exists('mode',$args)) ? $args['mode'] : 'conversation');
	switch($mode) {
		case 'conversation':
				$every = argv(0);
				$each = argv(0);
				$edit = true;
				$current = $_REQUEST['gid'];
				$abook_id = 0;
				$wmode = 0;
				break;
		case 'connections':
				$every = 'connections';
				$each = 'group';
				$edit = true;
				$current = $_REQUEST['gid'];
				$abook_id = 0;
				$wmode = 0;
		case 'groups':
				$every = 'connections';
				$each = argv(0);
				$edit = false;
				$current = intval(argv(1));
				$abook_id = 0;
				$wmode = 1;
				break;
		case 'abook':
				$every = 'connections';
				$each = 'group';
				$edit = false;
				$current = 0;
				$abook_id = get_app()->poi['abook_xchan'];
				$wmode = 1;
				break;
		default:
			return '';
			break;
	}

	return group_side($every, $each, $edit, $current, $abook_id, $wmode);
}


function widget_appselect($arr) {
	return replace_macros(get_markup_template('app_select.tpl'),array(
		'$title' => t('Apps'),
		'$system' => t('System'),
		'$authed' => ((local_channel()) ? true : false),
		'$personal' => t('Personal'),
		'$new' => t('Create Personal App'),
		'$edit' => t('Edit Personal App')
	));
}


function widget_suggestions($arr) {

	if((! local_channel()) || (! feature_enabled(local_channel(),'suggest')))
		return '';

	require_once('include/socgraph.php');

	$r = suggestion_query(local_channel(),get_observer_hash(),0,20);

	if(! $r) {
		return;
	}

	$arr = array();

	// Get two random entries from the top 20 returned.
	// We'll grab the first one and the one immediately following.
	// This will throw some entropy intot he situation so you won't 
	// be looking at the same two mug shots every time the widget runs

	$index = ((count($r) > 2) ? mt_rand(0,count($r) - 2) : 0);

	for($x = $index; $x <= ($index+1); $x ++) {
		$rr = $r[$x];
		if(! $rr['xchan_url'])
			break;

		$connlnk = z_root() . '/follow/?url=' . $rr['xchan_addr'];

		$arr[] = array(
			'url' => chanlink_url($rr['xchan_url']),
			'profile' => $rr['xchan_url'],
			'name' => $rr['xchan_name'],
			'photo' => $rr['xchan_photo_m'],
			'ignlnk' => z_root() . '/directory?ignore=' . $rr['xchan_hash'],
			'conntxt' => t('Connect'),
			'connlnk' => $connlnk,
			'ignore' => t('Ignore/Hide')
		);
	}

	$o = replace_macros(get_markup_template('suggest_widget.tpl'),array(
		'$title' => t('Suggestions'),
		'$more' => t('See more...'),
		'$entries' => $arr
	));

	return $o;
}


function widget_follow($args) {
	if(! local_channel())
		return '';

	$a = get_app();
	$uid =$a->channel['channel_id'];
	$r = q("select count(*) as total from abook where abook_channel = %d and abook_self = 0 ",
		intval($uid)
	);
	if($r)
		$total_channels = $r[0]['total'];	
	$limit = service_class_fetch($uid,'total_channels');
	if($limit !== false) {
		$abook_usage_message = sprintf( t("You have %1$.0f of %2$.0f allowed connections."), $total_channels, $limit);
	}
	else {
		$abook_usage_message = '';
 	}
	return replace_macros(get_markup_template('follow.tpl'),array(
		'$connect' => t('Add New Connection'),
		'$desc' => t('Enter channel address'),
		'$hint' => t('Examples: bob@example.com, https://example.com/barbara'),
		'$follow' => t('Connect'),
		'$abook_usage_message' => $abook_usage_message
	));
}


function widget_notes($arr) {
	if(! local_channel())
		return '';
	if(! feature_enabled(local_channel(),'private_notes'))
		return '';

	$text = get_pconfig(local_channel(),'notes','text');

	$o = replace_macros(get_markup_template('notes.tpl'), array(
		'$banner' => t('Notes'),
		'$text' => $text,
		'$save' => t('Save'),
	));

	return $o;
}


function widget_savedsearch($arr) {
	if((! local_channel()) || (! feature_enabled(local_channel(),'savedsearch')))
		return '';

	$a = get_app();

	$search = ((x($_GET,'search')) ? $_GET['search'] : '');
	
	if(x($_GET,'searchsave') && $search) {
		$r = q("select * from `term` where `uid` = %d and `type` = %d and `term` = '%s' limit 1",
			intval(local_channel()),
			intval(TERM_SAVEDSEARCH),
			dbesc($search)
		);
		if(! $r) {
			q("insert into `term` ( `uid`,`type`,`term` ) values ( %d, %d, '%s') ",
				intval(local_channel()),
				intval(TERM_SAVEDSEARCH),
				dbesc($search)
			);
		}
	}

	if(x($_GET,'searchremove') && $search) {
		q("delete from `term` where `uid` = %d and `type` = %d and `term` = '%s'",
			intval(local_channel()),
			intval(TERM_SAVEDSEARCH),
			dbesc($search)
		);
		$search = '';
	}

	$srchurl = $a->query_string;

	$srchurl =  rtrim(preg_replace('/searchsave\=[^\&].*?(\&|$)/is','',$srchurl),'&');
	$hasq = ((strpos($srchurl,'?') !== false) ? true : false);
	$srchurl =  rtrim(preg_replace('/searchremove\=[^\&].*?(\&|$)/is','',$srchurl),'&');

	$srchurl =  rtrim(preg_replace('/search\=[^\&].*?(\&|$)/is','',$srchurl),'&');
	$srchurl =  rtrim(preg_replace('/submit\=[^\&].*?(\&|$)/is','',$srchurl),'&');
	$srchurl = str_replace(array('?f=','&f='),array('',''),$srchurl);


	$hasq = ((strpos($srchurl,'?') !== false) ? true : false);
	$hasamp = ((strpos($srchurl,'&') !== false) ? true : false);

	if(($hasamp) && (! $hasq))
		$srchurl = substr($srchurl,0,strpos($srchurl,'&')) . '?f=&' . substr($srchurl,strpos($srchurl,'&')+1);		

	$o = '';

	$r = q("select `tid`,`term` from `term` WHERE `uid` = %d and `type` = %d ",
		intval(local_channel()),
		intval(TERM_SAVEDSEARCH)
	);

	$saved = array();

	if(count($r)) {
		foreach($r as $rr) {
			$saved[] = array(
				'id'            => $rr['tid'],
				'term'          => $rr['term'],
				'dellink'       => z_root() . '/' . $srchurl . (($hasq || $hasamp) ? '' : '?f=') . '&amp;searchremove=1&amp;search=' . urlencode($rr['term']),
				'srchlink'      => z_root() . '/' . $srchurl . (($hasq || $hasamp) ? '' : '?f=') . '&amp;search=' . urlencode($rr['term']),
				'displayterm'   => htmlspecialchars($rr['term'], ENT_COMPAT,'UTF-8'),
				'encodedterm'   => urlencode($rr['term']),
				'delete'        => t('Remove term'),
				'selected'      => ($search==$rr['term']),
			);
		}
	}

	$tpl = get_markup_template("saved_searches.tpl");
	$o = replace_macros($tpl, array(
		'$title'	 => t('Saved Searches'),
		'$add'		 => t('add'),
		'$searchbox' => searchbox($search, 'netsearch-box', $srchurl . (($hasq) ? '' : '?f='), true),
		'$saved' 	 => $saved,
	));

	return $o;
}


function widget_filer($arr) {
	if(! local_channel())
		return '';

	$a = get_app();

	$selected = ((x($_REQUEST,'file')) ? $_REQUEST['file'] : '');

	$terms = array();
	$r = q("select distinct(term) from term where uid = %d and type = %d order by term asc",
		intval(local_channel()),
		intval(TERM_FILE)
	);
	if(! $r)
		return;

	foreach($r as $rr)
		$terms[] = array('name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : ''));

	return replace_macros(get_markup_template('fileas_widget.tpl'),array(
		'$title' => t('Saved Folders'),
		'$desc' => '',
		'$sel_all' => (($selected == '') ? 'selected' : ''),
		'$all' => t('Everything'),
		'$terms' => $terms,
		'$base' => z_root() . '/' . $a->cmd
	));
}

function widget_archive($arr) {

	$o = '';
	$a = get_app();

	if(! $a->profile_uid) {
		return '';
	}

	$uid = $a->profile_uid;

	if(! feature_enabled($uid,'archives'))
		return '';

	if(! perm_is_allowed($uid,get_observer_hash(),'view_stream'))
		return '';

	$wall = ((array_key_exists('wall', $arr)) ? intval($arr['wall']) : 0);
	$style = ((array_key_exists('style', $arr)) ? $arr['style'] : 'select');
	$showend = ((get_pconfig($uid,'system','archive_show_end_date')) ? true : false);
	$mindate = get_pconfig($uid,'system','archive_mindate');
	$visible_years = get_pconfig($uid,'system','archive_visible_years');
	if(! $visible_years)
		$visible_years = 5;

	$url = z_root() . '/' . $a->cmd;

	$ret = list_post_dates($uid,$wall,$mindate);

	if(! count($ret))
		return '';

	$cutoff_year = intval(datetime_convert('',date_default_timezone_get(),'now','Y')) - $visible_years;
	$cutoff = ((array_key_exists($cutoff_year,$ret))? true : false);

	$o = replace_macros(get_markup_template('posted_date_widget.tpl'),array(
		'$title' => t('Archives'),
		'$size' => $visible_years,
		'$cutoff_year' => $cutoff_year,
		'$cutoff' => $cutoff,
		'$url' => $url,
		'$style' => $style,
		'$showend' => $showend,
		'$dates' => $ret
	));
	return $o;
}


function widget_fullprofile($arr) {
	$a = get_app();
	if(! $a->profile['profile_uid'])
		return;

	$block = (((get_config('system', 'block_public')) && (! local_channel()) && (! remote_channel())) ? true : false);

	return profile_sidebar($a->profile, $block);
}

function widget_categories($arr) {
	$a = get_app();

	if($a->profile['profile_uid'] && (! perm_is_allowed($a->profile['profile_uid'],get_observer_hash(),'view_stream')))
		return '';

	$cat = ((x($_REQUEST,'cat')) ? htmlspecialchars($_REQUEST['cat'],ENT_COMPAT,'UTF-8') : '');
	$srchurl = $a->query_string;
	$srchurl =  rtrim(preg_replace('/cat\=[^\&].*?(\&|$)/is','',$srchurl),'&');
	$srchurl = str_replace(array('?f=','&f='),array('',''),$srchurl);

	return categories_widget($srchurl, $cat);

}

function widget_tagcloud_wall($arr) {
	$a = get_app();

	if((! $a->profile['profile_uid']) || (! $a->profile['channel_hash']))
		return '';
	if(! perm_is_allowed($a->profile['profile_uid'], get_observer_hash(), 'view_stream'))
		return '';

	$limit = ((array_key_exists('limit', $arr)) ? intval($arr['limit']) : 50);
	if(feature_enabled($a->profile['profile_uid'], 'tagadelic'))
		return wtagblock($a->profile['profile_uid'], $limit, '', $a->profile['channel_hash'], 'wall');

	return '';
}

function widget_catcloud_wall($arr) {
	$a = get_app();

	if((! $a->profile['profile_uid']) || (! $a->profile['channel_hash']))
		return '';
	if(! perm_is_allowed($a->profile['profile_uid'], get_observer_hash(), 'view_stream'))
		return '';

	$limit = ((array_key_exists('limit',$arr)) ? intval($arr['limit']) : 50);

	return catblock($a->profile['profile_uid'], $limit, '', $a->profile['channel_hash'], 'wall');
}


function widget_affinity($arr) {

	if(! local_channel())
		return '';

	$cmin = ((x($_REQUEST,'cmin')) ? intval($_REQUEST['cmin']) : 0);
	$cmax = ((x($_REQUEST,'cmax')) ? intval($_REQUEST['cmax']) : 99);


	if(feature_enabled(local_channel(),'affinity')) {

		$labels = array(
			t('Me'),
			t('Family'),
			t('Friends'),
			t('Acquaintances'),
			t('All')
		);
		call_hooks('affinity_labels',$labels);
		$label_str = '';

		if($labels) {
			foreach($labels as $l) {
				if($label_str) {
					$label_str .= ", '|'";
					$label_str .= ", '" . $l . "'";
				}
				else
					$label_str .= "'" . $l . "'";
			}
		}

		$tpl = get_markup_template('main_slider.tpl');
		$x = replace_macros($tpl,array(
			'$val' => $cmin . ',' . $cmax,
			'$refresh' => t('Refresh'),
			'$labels' => $label_str,
		));
		$arr = array('html' => $x);
		call_hooks('main_slider',$arr);
		return $arr['html']; 
	}

 	return '';
}


function widget_settings_menu($arr) {

	if(! local_channel())
		return;

	$a = get_app();
	$channel = $a->get_channel();

	$abook_self_id = 0;

	// Retrieve the 'self' address book entry for use in the auto-permissions link

	$role = get_pconfig(local_channel(),'system','permissions_role');

	$abk = q("select abook_id from abook where abook_channel = %d and abook_self = 1 limit 1",
		intval(local_channel())
	);
	if($abk)
		$abook_self_id = $abk[0]['abook_id'];

	$tabs = array(
		array(
			'label'	=> t('Account settings'),
			'url' 	=> $a->get_baseurl(true).'/settings/account',
			'selected'	=> ((argv(1) === 'account') ? 'active' : ''),
		),

		array(
			'label'	=> t('Channel settings'),
			'url' 	=> $a->get_baseurl(true).'/settings/channel',
			'selected'	=> ((argv(1) === 'channel') ? 'active' : ''),
		),

	);

	if(get_features()) {
		$tabs[] = 	array(
				'label'	=> t('Additional features'),
				'url' 	=> $a->get_baseurl(true).'/settings/features',
				'selected'	=> ((argv(1) === 'features') ? 'active' : ''),
		);
	}

	$tabs[] =	array(
		'label'	=> t('Feature/Addon settings'),
		'url' 	=> $a->get_baseurl(true).'/settings/featured',
		'selected'	=> ((argv(1) === 'featured') ? 'active' : ''),
	);

	$tabs[] =	array(
		'label'	=> t('Display settings'),
		'url' 	=> $a->get_baseurl(true).'/settings/display',
		'selected'	=> ((argv(1) === 'display') ? 'active' : ''),
	);	

	$tabs[] =	array(
		'label' => t('Connected apps'),
		'url' => $a->get_baseurl(true) . '/settings/oauth',
		'selected' => ((argv(1) === 'oauth') ? 'active' : ''),
	);

	$tabs[] =	array(
		'label' => t('Export channel'),
		'url' => $a->get_baseurl(true) . '/uexport',
		'selected' => ''
	);


	if($role === false || $role === 'custom') {
		$tabs[] = array(
			'label' => t('Connection Default Permissions'),
			'url' => $a->get_baseurl(true) . '/connedit/' . $abook_self_id,
			'selected' => ''
		);
	}

	if(feature_enabled(local_channel(),'premium_channel')) {
		$tabs[] = array(
			'label' => t('Premium Channel Settings'),
			'url' => $a->get_baseurl(true) . '/connect/' . $channel['channel_address'],
			'selected' => ''
		);
	}

	if(feature_enabled(local_channel(),'channel_sources')) {
		$tabs[] = array(
			'label' => t('Channel Sources'),
			'url' => $a->get_baseurl(true) . '/sources',
			'selected' => ''
		);
	}

	$tabtpl = get_markup_template("generic_links_widget.tpl");
	return replace_macros($tabtpl, array(
		'$title' => t('Settings'),
		'$class' => 'settings-widget',
		'$items' => $tabs,
	));
}


function widget_mailmenu($arr) {
	if (! local_channel())
		return;

	$a = get_app();

	return replace_macros(get_markup_template('message_side.tpl'), array(
		'$title' => t('Private Mail Menu'),
		'$combined'=>array(
			'label' => t('Combined View'),
			'url' => $a->get_baseurl(true) . '/mail/combined',
			'sel' => (argv(1) == 'combined'),
		),
		'$inbox'=>array(
			'label' => t('Inbox'),
			'url' => $a->get_baseurl(true) . '/mail/inbox',
			'sel' => (argv(1) == 'inbox'),
		),
		'$outbox'=>array(
			'label' => t('Outbox'),
			'url' => $a->get_baseurl(true) . '/mail/outbox',
			'sel' => (argv(1) == 'outbox'),
		),
		'$new'=>array(
			'label' => t('New Message'),
			'url' => $a->get_baseurl(true) . '/mail/new',
			'sel'=> (argv(1) == 'new'),
		)
	));
}


function widget_conversations($arr) {
	if (! local_channel())
		return;

	$a = get_app();

	if(argc() > 1) {

		switch(argv(1)) {
			case 'combined':
				$mailbox = 'combined';
				$header = t('Conversations');
				break;
			case 'inbox':
				$mailbox = 'inbox';
				$header = t('Received Messages');
				break;
			case 'outbox':
				$mailbox = 'outbox';
				$header = t('Sent Messages');
				break;
			default:
				$mailbox = 'combined';
				$header = t('Conversations');
				break;
		}

		require_once('include/message.php');

		// private_messages_list() can do other more complicated stuff, for now keep it simple
		$r = private_messages_list(local_channel(), $mailbox, $a->pager['start'], $a->pager['itemspage']);

		if(! $r) {
			info( t('No messages.') . EOL);
			return $o;
		}

		$messages = array();

		foreach($r as $rr) {

			$messages[] = array(
				'mailbox'      => $mailbox,
				'id'           => $rr['id'],
				'from_name'    => $rr['from']['xchan_name'],
				'from_url'     => chanlink_hash($rr['from_xchan']),
				'from_photo'   => $rr['from']['xchan_photo_s'],
				'to_name'      => $rr['to']['xchan_name'],
				'to_url'       => chanlink_hash($rr['to_xchan']),
				'to_photo'     => $rr['to']['xchan_photo_s'],
				'subject'      => (($rr['seen']) ? $rr['title'] : '<strong>' . $rr['title'] . '</strong>'),
				'delete'       => t('Delete conversation'),
				'body'         => $rr['body'],
				'date'         => datetime_convert('UTC',date_default_timezone_get(),$rr['created'], 'c'),
				'seen'         => $rr['seen'],
				'selected'     => ((argv(2)) ? (argv(2) == $rr['id']) : ($r[0]['id'] == $rr['id']))
			);
		}

		$tpl = get_markup_template('mail_head.tpl');
		$o .= replace_macros($tpl, array(
			'$header' => $header,
			'$messages' => $messages
		));

		$o .= alt_pager($a,count($r));

	}

	return $o;
}

function widget_eventsmenu($arr) {
	if (! local_channel())
		return;

	return replace_macros(get_markup_template('events_menu_side.tpl'), array(
		'$title' => t('Events Menu'),
		'$day' => t('Day View'),
		'$week' => t('Week View'),
		'$month' => t('Month View'),
		'$export' => t('Export'),
		'$upload' => t('Import'),
		'$submit' => t('Submit')
	));
}

function widget_eventstools($arr) {
	if (! local_channel())
		return;

	return replace_macros(get_markup_template('events_tools_side.tpl'), array(
		'$title' => t('Events Tools'),
		'$export' => t('Export Calendar'),
		'$import' => t('Import Calendar'),
		'$submit' => t('Submit')
	));
}

function widget_design_tools($arr) {
	$a = get_app();

	// mod menu doesn't load a profile. For any modules which load a profile, check it.
	// otherwise local_channel() is sufficient for permissions.

	if($a->profile['profile_uid']) 
		if(($a->profile['profile_uid'] != local_channel()) && (! $a->is_sys))
			return '';
 
	if(! local_channel())
		return '';

	return design_tools();
}


function widget_findpeople($arr) {
	return findpeople_widget();
}


function widget_photo_albums($arr) {
	$a = get_app();

	if(! $a->profile['profile_uid'])
		return '';
	$channelx = channelx_by_n($a->profile['profile_uid']);
	if((! $channelx) || (! perm_is_allowed($a->profile['profile_uid'], get_observer_hash(), 'view_storage')))
		return '';
	require_once('include/photos.php');

	return photos_album_widget($channelx, $a->get_observer());
}


function widget_vcard($arr) {
	require_once ('include/Contact.php');
	return vcard_from_xchan('', get_app()->get_observer());
}


/*
 * The following directory widgets are only useful on the directory page
 */


function widget_dirsort($arr) {
	return dir_sort_links();
}

function widget_dirtags($arr) {
	return dir_tagblock(z_root() . '/directory', null);
}

function widget_menu_preview($arr) {
	if(! get_app()->data['menu_item'])
		return;
	require_once('include/menu.php');

	return menu_render(get_app()->data['menu_item']);
}

function widget_chatroom_list($arr) {
	$a = get_app();

	require_once("include/chat.php");
	$r = chatroom_list($a->profile['profile_uid']);
	return replace_macros(get_markup_template('chatroomlist.tpl'), array(
		'$header' => t('Chat Rooms'),
		'$baseurl' => z_root(),
		'$nickname' => $a->profile['channel_address'],
		'$items' => $r,
	));
}

function widget_bookmarkedchats($arr) {
	$h = get_observer_hash();
	if(! $h)
		return;
	$r = q("select xchat_url, xchat_desc from xchat where xchat_xchan = '%s' order by xchat_desc",
		dbesc($h)
	);
	if($r) {
		for($x = 0; $x < count($r); $x ++) {
			$r[$x]['xchat_url'] = zid($r[$x]['xchat_url']);
		}
	}
	return replace_macros(get_markup_template('bookmarkedchats.tpl'),array(
		'$header' => t('Bookmarked Chatrooms'),
		'$rooms' => $r
	));
}

function widget_suggestedchats($arr) {

	// probably should restrict this to your friends, but then the widget will only work
	// if you are logged in locally.

	$h = get_observer_hash();
	if(! $h)
		return;
	$r = q("select xchat_url, xchat_desc, count(xchat_xchan) as total from xchat group by xchat_url, xchat_desc order by total desc, xchat_desc limit 24");
	if($r) {
		for($x = 0; $x < count($r); $x ++) {
			$r[$x]['xchat_url'] = zid($r[$x]['xchat_url']);
		}
	}
	return replace_macros(get_markup_template('bookmarkedchats.tpl'),array(
		'$header' => t('Suggested Chatrooms'),
		'$rooms' => $r
	));
}

function widget_item($arr) {

	$channel_id = 0;
	if(array_key_exists('channel_id',$arr) && intval($arr['channel_id']))
		$channel_id = intval($arr['channel_id']);
	if(! $channel_id)
		$channel_id = get_app()->profile_uid;
	if(! $channel_id)
		return '';


	if((! $arr['mid']) && (! $arr['title']))
		return '';

	if(! perm_is_allowed($channel_id, get_observer_hash(), 'view_pages'))
		return '';

	require_once('include/security.php');
	$sql_extra = item_permissions_sql($channel_id);

	if($arr['title']) {
		$r = q("select item.* from item left join item_id on item.id = item_id.iid
			where item.uid = %d and sid = '%s' and service = 'WEBPAGE' and item_type = %d $sql_options $revision limit 1",
			intval($channel_id),
			dbesc($arr['title']),
			intval(ITEM_TYPE_WEBPAGE)
		);
	}
	else {
		$r = q("select * from item where mid = '%s' and uid = %d and item_type = " . intval(ITEM_TYPE_WEBPAGE) . " $sql_extra limit 1",
			dbesc($arr['mid']),
			intval($channel_id)
		);
	}

	if(! $r)
		return '';

	xchan_query($r);
	$r = fetch_post_tags($r, true);

	$o = prepare_page($r[0]);
	return $o;
}

function widget_clock($arr) {

	$miltime = 0;
	if(isset($arr['military']) && $arr['military'])
		$miltime = 1;

$o = <<< EOT
<div class="widget">
<h3 class="clockface"></h3>
<script>

var timerID = null
var timerRunning = false

function stopclock(){
    if(timerRunning)
        clearTimeout(timerID)
    timerRunning = false
}

function startclock(){
    stopclock()
    showtime()
}

function showtime(){
    var now = new Date()
    var hours = now.getHours()
    var minutes = now.getMinutes()
    var seconds = now.getSeconds()
	var military = $miltime
    var timeValue = ""
	if(military)
		timeValue = hours
	else
		timeValue = ((hours > 12) ? hours - 12 : hours)
    timeValue  += ((minutes < 10) ? ":0" : ":") + minutes
//    timeValue  += ((seconds < 10) ? ":0" : ":") + seconds
	if(! military)
	    timeValue  += (hours >= 12) ? " P.M." : " A.M."
    $('.clockface').html(timeValue) 
    timerID = setTimeout("showtime()",1000)
    timerRunning = true
}

$(document).ready(function() {
	startclock();
});

</script>
</div>
EOT;
return $o;

}


/**
 * @function widget_photo($arr)
 *    widget to display a single photo.
 * @param array $arr;
 *    'src' => URL of photo
 *    'zrl' => true or false, use zid in url
 *    'style' => CSS string
 * URL must be an http or https URL
 */

function widget_photo($arr) {

	$style = $zrl = false;

	if(array_key_exists('src', $arr) && isset($arr['src']))
		$url = $arr['src'];

	if(strpos($url,'http') !== 0)
		return '';

	if(array_key_exists('style', $arr) && isset($arr['style']))
		$style = $arr['style'];

	// ensure they can't sneak in an eval(js) function

	if(strpbrk($style,'(\'"<>') !== false)
		$style = '';

	if(array_key_exists('zrl', $arr) && isset($arr['zrl']))
		$zrl = (($arr['zrl']) ? true : false);

	if($zrl)
		$url = zid($url);

	$o = '<div class="widget">';

	$o .= '<img ' . (($zrl) ? ' class="zrl" ' : '') 
				  . (($style) ? ' style="' . $style . '"' : '') 
				  . ' src="' . $url . '" alt="' . t('photo/image') . '">';

	$o .= '</div>';

	return $o;
}


function widget_cover_photo($arr) {

	require_once('include/identity.php');
	$o = '';

	$channel_id = 0;
	if(array_key_exists('channel_id', $arr) && intval($arr['channel_id']))
		$channel_id = intval($arr['channel_id']);
	if(! $channel_id)
		$channel_id = get_app()->profile_uid;
	if(! $channel_id)
		return '';

	if(array_key_exists('style', $arr) && isset($arr['style']))
		$style = $arr['style'];
	else 
		$style = 'width:100%; padding-right: 10px; height: auto;'; 

	// ensure they can't sneak in an eval(js) function

	if(strpbrk($style,'(\'"<>') !== false)
		$style = '';

	$c = get_cover_photo($channel_id,'html');

	if($c) {
		$o = '<div class="widget">' . (($style) ? str_replace('alt=',' style="' . $style . '" alt=',$c) : $c) . '</div>';
	}
	return $o;
}


function widget_photo_rand($arr) {

	require_once('include/photos.php');
	$style = false;

	if(array_key_exists('album', $arr) && isset($arr['album']))
		$album = $arr['album'];
	else
		$album = '';

	$channel_id = 0;
	if(array_key_exists('channel_id', $arr) && intval($arr['channel_id']))
		$channel_id = intval($arr['channel_id']);
	if(! $channel_id)
		$channel_id = get_app()->profile_uid;
	if(! $channel_id)
		return '';

	$scale = ((array_key_exists('scale',$arr)) ? intval($arr['scale']) : 0);

	$ret = photos_list_photos(array('channel_id' => $channel_id),get_app()->get_observer(),$album);

	$filtered = array();
	if($ret['success'] && $ret['photos'])
	foreach($ret['photos'] as $p)
		if($p['scale'] == $scale)
			$filtered[] = $p['src'];

	if($filtered) {
		$e = mt_rand(0, count($filtered) - 1);
		$url = $filtered[$e];
	}

	if(strpos($url, 'http') !== 0)
		return '';

	if(array_key_exists('style', $arr) && isset($arr['style']))
		$style = $arr['style'];

	// ensure they can't sneak in an eval(js) function

	if(strpos($style,'(') !== false)
		return '';

	$url = zid($url);

	$o = '<div class="widget">';

	$o .= '<img class="zrl" ' 
		. (($style) ? ' style="' . $style . '"' : '') 
		. ' src="' . $url . '" alt="' . t('photo/image') . '">';

	$o .= '</div>';

	return $o;
}


function widget_random_block($arr) {

	$channel_id = 0;
	if(array_key_exists('channel_id',$arr) && intval($arr['channel_id']))
		$channel_id = intval($arr['channel_id']);
	if(! $channel_id)
		$channel_id = get_app()->profile_uid;
	if(! $channel_id)
		return '';

	if(array_key_exists('contains',$arr))
		$contains = $arr['contains'];

	$o = '';

	require_once('include/security.php');
	$sql_options = item_permissions_sql($channel_id);

	$randfunc = db_getfunc('RAND');

	$r = q("select item.* from item left join item_id on item.id = item_id.iid
		where item.uid = %d and sid like '%s' and service = 'BUILDBLOCK' and 
		item_type = %d $sql_options order by $randfunc limit 1",
		intval($channel_id),
		dbesc('%' . $contains . '%'),
		intval(ITEM_TYPE_BLOCK)
	);

	if($r) {
		$o = '<div class="widget bblock">';
		if($r[0]['title'])
			$o .= '<h3>' . $r[0]['title'] . '</h3>';

		$o .= prepare_text($r[0]['body'],$r[0]['mimetype']);
		$o .= '</div>';
	}

	return $o;
}


function widget_rating($arr) {
	$a = get_app();

	$poco_rating = get_config('system','poco_rating_enable');
	if((! $poco_rating) && ($poco_rating !== false)) {
		return;
	}

	if($arr['target'])
		$hash = $arr['target'];
	else
		$hash = $a->poi['xchan_hash'];

	if(! $hash)
		return;

	$url = '';
	$remote = false;

	if(remote_channel() && ! local_channel()) {
		$ob = $a->get_observer();
		if($ob && $ob['xchan_url']) {
			$p = parse_url($ob['xchan_url']);
			if($p) {
				$url = $p['scheme'] . '://' . $p['host'] . (($p['port']) ? ':' . $p['port'] : '');
				$url .= '/rate?f=&target=' . urlencode($hash);
			}
			$remote = true;
		}
	}

	$self = false;

	if(local_channel()) {
		$channel = $a->get_channel();

		if($hash == $channel['channel_hash'])
			$self = true;

		head_add_js('ratings.js');

	}


	$o = '<div class="widget">';
	$o .= '<h3>' . t('Rating Tools') . '</h3>';

	if((($remote) || (local_channel())) && (! $self)) {
		if($remote)
			$o .= '<a class="btn btn-block btn-primary btn-sm" href="' . $url . '"><i class="icon-pencil"></i> ' . t('Rate Me') . '</a>';
		else
			$o .= '<div class="btn btn-block btn-primary btn-sm" onclick="doRatings(\'' . $hash . '\'); return false;"><i class="icon-pencil"></i> ' . t('Rate Me') . '</div>';
	}

	$o .= '<a class="btn btn-block btn-default btn-sm" href="ratings/' . $hash . '"><i class="icon-eye-open"></i> ' . t('View Ratings') . '</a>';
	$o .= '</div>';

	return $o;

}

// used by site ratings pages to provide a return link
function widget_pubsites($arr) {
	if(get_app()->poi)
		return;
	return '<div class="widget"><ul class="nav nav-pills"><li><a href="pubsites">' . t('Public Hubs') . '</a></li></ul></div>';
}


function widget_forums($arr) {

	$a = get_app();

	if(! local_channel())
		return '';

	$o = '';

	if(is_array($arr) && array_key_exists('limit',$arr))
		$limit = " limit " . intval($limit) . " ";
	else
		$limit = '';

	$unseen = 0;
	if(is_array($arr) && array_key_exists('unseen',$arr) && intval($arr['unseen']))
		$unseen = 1;

	$perms_sql = item_permissions_sql(local_channel()) . item_normal();

	$r1 = q("select * from abook left join xchan on abook_xchan = xchan_hash where ( xchan_pubforum = 1 or ((abook_their_perms & %d ) != 0 and (abook_their_perms & %d ) = 0) ) and abook_channel = %d order by xchan_name $limit ",
		intval(PERMS_W_TAGWALL),
		intval(PERMS_W_STREAM),
		intval(local_channel())
	);
	if(! $r1)
		return $o;

	$str = '';

	// Trying to cram all this into a single query with joins and the proper group by's is tough.
	// There also should be a way to update this via ajax.

	for($x = 0; $x < count($r1); $x ++) {
		$r = q("select sum(item_unseen) as unseen from item where owner_xchan = '%s' and uid = %d $perms_sql ",
			dbesc($r1[$x]['xchan_hash']),
			intval(local_channel())
		);
		if($r)
			$r1[$x]['unseen'] = $r[0]['unseen'];
	}

	if($r1) {
		$o .= '<div class="widget">';
		$o .= '<h3>' . t('Forums') . '</h3><ul class="nav nav-pills nav-stacked">';

		foreach($r1 as $rr) {
			if($unseen && (! intval($rr['unseen'])))
				continue;
			$o .= '<li><a href="network?f=&pf=1&cid=' . $rr['abook_id'] . '" ><span class="badge pull-right">' . ((intval($rr['unseen'])) ? intval($rr['unseen']) : '') . '</span><img src="' . $rr['xchan_photo_s'] . '" style="width: 16px; height: 16px;" /> ' . $rr['xchan_name'] . '</a></li>';
		}
		$o .= '</ul></div>';
	}
	return $o; 

}


function widget_tasklist($arr) {

	if (! local_channel())
		return;

	require_once('include/event.php');
	$o .= '<script>var tasksShowAll = 0; $(document).ready(function() { tasksFetch(); $("#tasklist-new-form").submit(function(event) { event.preventDefault(); $.post( "tasks/new", $("#tasklist-new-form").serialize(), function(data) { tasksFetch();  $("#tasklist-new-summary").val(""); } ); return false; } )});</script>';
	$o .= '<script>function taskComplete(id) { $.post("tasks/complete/"+id, function(data) { tasksFetch();}); }
		function tasksFetch() {
			$.get("tasks/fetch" + ((tasksShowAll) ? "/all" : ""), function(data) {
				$(".tasklist-tasks").html(data.html);
			});
		}
		</script>'; 

	$o .= '<div class="widget">' . '<h3>' . t('Tasks') . '</h3><div class="tasklist-tasks">';
	$o .= '</div><form id="tasklist-new-form" action="" ><input id="tasklist-new-summary" type="text" name="summary" value="" /></form>';
	$o .= '</div>';
	return $o;

}


function widget_helpindex($arr) {
	$o .= '<div class="widget">' . '<h3>' . t('Documentation') . '</h3>';
	$o .= '<ul class="nav nav-pills nav-stacked">';
	$o .= '<li><a href="help/general">' . t('Project/Site Information') . '</a></li>';
	$o .= '<li><a href="help/members">' . t('For Members') . '</a></li>';
	$o .= '<li><a href="help/admins">'  . t('For Administrators') . '</a></li>';
	$o .= '<li><a href="help/develop">' . t('For Developers') . '</a></li>';
	$o .= '</ul></div>';
	return $o;

}



function widget_admin($arr) {

	/*
	 * Side bar links
	 */

	if(! is_site_admin()) {
		return login(false);
	}


	$a = get_app();
	$o = '';

	// array( url, name, extra css classes )

	$aside = array(
		'site'      => array(z_root() . '/admin/site/',     t('Site'),           'site'),
		'users'     => array(z_root() . '/admin/users/',    t('Accounts'),       'users'),
		'channels'  => array(z_root() . '/admin/channels/', t('Channels'),       'channels'),
		'features'  => array(z_root() . '/admin/features/', t('Features'),       'features'),
		'plugins'   => array(z_root() . '/admin/plugins/',  t('Plugins'),        'plugins'),
		'themes'    => array(z_root() . '/admin/themes/',   t('Themes'),         'themes'),
		'queue'     => array(z_root() . '/admin/queue',     t('Inspect queue'),  'queue'),
		'profs'     => array(z_root() . '/admin/profs',     t('Profile Config'), 'profs'),
		'dbsync'    => array(z_root() . '/admin/dbsync/',   t('DB updates'),     'dbsync')

	);

	/* get plugins admin page */

	$r = q("SELECT * FROM addon WHERE plugin_admin = 1");

	$aside['plugins_admin'] = array();
	if($r) {
		foreach ($r as $h){
			$plugin = $h['name'];
			$aside['plugins_admin'][] = array(z_root() . '/admin/plugins/' . $plugin, $plugin, 'plugin');
			// temp plugins with admin
			$a->plugins_admin[] = $plugin;
		}
	}

	$aside['logs'] = array(z_root() . '/admin/logs/', t('Logs'), 'logs');

	$o .= replace_macros(get_markup_template('admin_aside.tpl'), array(
			'$admin' => $aside, 
			'$admtxt' => t('Admin'),
			'$plugadmtxt' => t('Plugin Features'),
			'$logtxt' => t('Logs'),
			'$h_pending' => t('User registrations waiting for confirmation'),
			'$admurl'=> z_root() . '/admin/'
	));

	return $o;

}



function widget_album($args) {

	$owner_uid = get_app()->profile_uid;
	$sql_extra = permissions_sql($owner_uid);


	if(! perm_is_allowed($owner_uid,get_observer_hash(),'view_storage'))
		return '';

	if($args['album'])
		$album = $args['album'];
	if($args['title'])
		$title = $args['title'];

	/** 
	 * This may return incorrect permissions if you have multiple directories of the same name.
	 * It is a limitation of the photo table using a name for a photo album instead of a folder hash
	 */

	if($album) {
		$x = q("select hash from attach where filename = '%s' and uid = %d limit 1",
			dbesc($album),
			intval($owner_uid)
		);
		if($x) {
			$y = attach_can_view_folder($owner_uid,get_observer_hash(),$x[0]['hash']);
			if(! $y)
				return '';
		}
	}

	$order = 'DESC';

	$r = q("SELECT p.resource_id, p.id, p.filename, p.type, p.scale, p.description, p.created FROM photo p INNER JOIN
		(SELECT resource_id, max(scale) scale FROM photo WHERE uid = %d AND album = '%s' AND scale <= 4 AND photo_usage IN ( %d, %d ) $sql_extra GROUP BY resource_id) ph 
		ON (p.resource_id = ph.resource_id AND p.scale = ph.scale)
		ORDER BY created $order ",
		intval($owner_uid),
		dbesc($album),
		intval(PHOTO_NORMAL),
		intval(PHOTO_PROFILE)
	);
		
	//edit album name
	$album_edit = null;

	$photos = array();
	if($r) {
		$twist = 'rotright';
		foreach($r as $rr) {

			if($twist == 'rotright')
				$twist = 'rotleft';
			else
				$twist = 'rotright';
				
			$ext = $phototypes[$rr['type']];

			$imgalt_e = $rr['filename'];
			$desc_e = $rr['description'];

			$imagelink = (z_root() . '/photos/' . get_app()->profile['channel_address'] . '/image/' . $rr['resource_id']);


			$photos[] = array(
				'id' => $rr['id'],
				'twist' => ' ' . $twist . rand(2,4),
				'link' => $imagelink,
				'title' => t('View Photo'),
				'src' => z_root() . '/photo/' . $rr['resource_id'] . '-' . $rr['scale'] . '.' .$ext,
				'alt' => $imgalt_e,
				'desc'=> $desc_e,
				'ext' => $ext,
				'hash'=> $rr['resource_id'],
				'unknown' => t('Unknown')
			);
		}
	}


	$tpl = get_markup_template('photo_album.tpl');
	$o .= replace_macros($tpl, array(
		'$photos' => $photos,
		'$album' => (($title) ? $title : $album),
		'$album_id' => rand(),
		'$album_edit' => array(t('Edit Album'), $album_edit),
		'$can_post' => false,
		'$upload' => array(t('Upload'), z_root() . '/photos/' . get_app()->profile['channel_address'] . '/upload/' . bin2hex($album)),
		'$order' => false,
		'$upload_form' => $upload_form,
		'$usage' => $usage_message
	));

	return $o;
}

