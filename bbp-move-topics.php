<?php
/*
Plugin Name: bbPress Move Topics
Plugin URI: https://wordpress.org/plugins/bbp-move-topics/
Description: Move topics from one forum to another, convert post/comments into topic/replies in the same site.
Version: 1.1.6
Author: Pascal Casier
Author URI: http://casier.eu/wp-dev/
Text Domain: bbp-move-topics
License: GPL
*/

// No direct access
if ( !defined( 'ABSPATH' ) ) exit;

register_activation_hook( __FILE__, 'bbpmt_check_activation' );

define ('BBPMOVETOPICS_VERSION' , '1.1.6');

if(!defined('BBPMT_PLUGIN_DIR'))
	define('BBPMT_PLUGIN_DIR', dirname(__FILE__));
if(!defined('BBPMT_URL_PATH'))
	define('BBPMT_URL_PATH', plugin_dir_url(__FILE__));

include(BBPMT_PLUGIN_DIR . '/includes/posttotopic.php');

// Only in the admin area
if ( !is_admin() ) {
	return;
}

// Only continue if bbPress is running
if( !function_exists('is_plugin_active') ) {
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
if( !is_plugin_active( 'bbpress/bbpress.php' ) )
	return;

function bbpmt_get_forum_structure() {
	$forumarray = array();
	if ( bbp_has_forums() ) {
		while ( bbp_forums() ) {
			bbp_the_forum();
			$forum_id = bbp_get_forum_id();
			$forum_title = bbp_get_forum_title($forum_id);
			array_push($forumarray, array($forum_id,$forum_title));
			$subf1 = bbp_forum_get_subforums($forum_id);
			if ($subf1) {
				foreach ($subf1 as $sub1forum) {
					$sub1_forum_id = $sub1forum->ID;
					$forum_title = '&nbsp;&nbsp;'.bbp_get_forum_title($sub1_forum_id);
					array_push($forumarray, array($sub1_forum_id,$forum_title));
				}
			}
		}
	}

	return $forumarray;
}

function removehtml_and_cutwords($scontent, $limitwords) {
	// Remove HTML tags
	$scontent = wp_strip_all_tags($scontent);
	// Cut after $limitwords words
	$scontent = preg_replace('/(?<=\S,)(?=\S)/', ' ', $scontent);
	$scontent = str_replace("\n", " ", $scontent);
	$contentarray = explode(" ", $scontent);
	if (count($contentarray)>$limitwords) {
		array_splice($contentarray, $limitwords);
		$scontent = implode(" ", $contentarray)." ...";
	} 
	return $scontent;
}

function forums_move_topics_page() {
	// Backward compatibility
	// Clean v1.1.2
	delete_option('bbpmt-ptot-donot-close');

	echo '<h1>Move topics Forum to Forum</h1>';
/*	if ( !function_exists( 'bbp_list_forums' ) ) {
		require_once ABSPATH . PLUGINDIR . '/bbpress/includes/forums/template.php';
	}
*/
	// Check if coming from form (POST data)
	
	// Choose topics to move
	if ( isset($_POST['goforum']) ) {
		if( empty($_POST["sourceforum"]) ) {
			echo 'No forum selected';
		} else {
			global $wpdb;
			$allforumarray = bbpmt_get_forum_structure();
			$limitrecords = 15;
			$limitwords = 25;
			$sourceforum = $_POST["sourceforum"];
			echo '<h2>'.bbp_get_forum_title($sourceforum).'</h2>';
			// Get child IDs
			$child_ids = $wpdb->get_col( $wpdb->prepare( "SELECT distinct ID FROM {$wpdb->posts} as posts, {$wpdb->postmeta} WHERE post_parent = %d AND post_status = 0 AND post_type = '%s' AND ID = post_id AND NOT EXISTS ( select * FROM {$wpdb->postmeta} where meta_key = '_bbpmt_zapped' and post_id=posts.ID) ORDER BY ID DESC LIMIT %d", $sourceforum, 'topic', $limitrecords ) );
			if ($child_ids) {
				echo '<form action="" method="post" id="bbpmttopicform">';
				wp_nonce_field( 'move_topics_'.$sourceforum );
				echo '<table id="bbpmt-forum-topics">';
				foreach ($child_ids as $topic_id) {
					$acontent = $wpdb->get_results( $wpdb->prepare( "SELECT post_title, post_content, post_date FROM {$wpdb->posts} WHERE id = %d", $topic_id) );
					// Remove HTML tags and cut words
					$scontent = removehtml_and_cutwords($acontent[0]->post_content, $limitwords);
					// Start display row, checkbox, post
					echo '<tr>';
					echo '<td><input type="checkbox" class="bbpmtcbgroup" id="bbpmtcb_'.$topic_id.'" name="bbpmtcb[]" value="' . $topic_id . '"></td>';
					echo '<td><b>'.$acontent[0]->post_title.' ('.$topic_id.')</b> - '.$acontent[0]->post_date.'<br>'.$scontent.'</td>';
					echo '</tr>';
				}	
				echo '</table>';
				echo '_______<br><br>';
				echo '<input type="checkbox" id="bbpmtcbgroup_master" onchange="bbpmttoggleall(this,\'bbpmtcbgroup\')" /> Select All/None<br><br>';
				echo '<input type="submit" name="zapselected" value="HIDE selected" /><br>MOVE selected to:';
				echo '<select name="destinationforum" id="destinationforum">';
				echo '<option value="">-- Choose forum --</option>';
				foreach ($allforumarray as $forumarray) {
					if ($forumarray[0] == $sourceforum) {
						// No need to show, it's the source one
						// echo '<option value="'.$forumarray[0].'">'.$forumarray[1].' (=SOURCE)</option>';
					} else {
						echo '<option value="'.$forumarray[0].'">'.$forumarray[1].'</option>';
					}
				}
				echo '</select>';

				echo '<input type="submit" name="moveforum" value="Start Move" />';
				echo '<br><input type="submit" name="gohome" value="Back to homepage" />';
				echo '<input type="hidden" name="sourceforum" value="'.$sourceforum.'" />';
				echo '</form>';
			} else {
				// No topics found
				echo 'No topics found that need your attention.';
			}
			return;
		}
	}

	// MOVE selected items	
	if ( isset($_POST['moveforum']) ) {
		if( empty($_POST["bbpmtcb"]) ) {
			echo 'No topics selected for moving';
		} else {
			if( empty($_POST["destinationforum"]) ) {
				echo 'No destination forum selected for moving';
			} else {
				global $wpdb;
				// Select forum where to move to
				$sourceforum = $_POST["sourceforum"];
				$destinationforum = $_POST["destinationforum"];
				check_admin_referer( 'move_topics_'.$sourceforum );
				echo 'Source Forum : '.bbp_get_forum_title($sourceforum).'<br>';
				echo 'Destination Forum : '.bbp_get_forum_title($destinationforum).'<br><br>';
				// Move them all
				foreach ($_POST["bbpmtcb"] as $moveit) {
					// Move the topic
					$ptable = $wpdb->posts;
					$update_rec = $wpdb->query($wpdb->prepare("UPDATE $ptable SET post_parent=%d WHERE ID=%d", $destinationforum, $moveit));
					if ($update_rec === false) {
						echo '<p>DB update error for topic #'.$moveit.' !</p>';
					} else {
						// Update post metadata
						update_post_meta($moveit, '_bbpmt_movedon', date( 'Y-m-d H:i:s', current_time( 'timestamp', 1 ) ));
						update_post_meta($moveit, '_bbpmt_movedfrom', $sourceforum);
						update_post_meta($moveit, '_bbp_forum_id', $destinationforum);

						// Move the replies
						$extratext = ')';
						$child_recs = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'reply'", $moveit ) );
						if ($child_recs) {
							foreach ($child_recs as $child_id) {
								$cid = $child_id->ID;
								update_post_meta($cid, '_bbpmt_movedon', date( 'Y-m-d H:i:s', current_time( 'timestamp', 1 ) ));
								update_post_meta($cid, '_bbpmt_movedfrom', $sourceforum);
								update_post_meta($cid, '_bbp_forum_id', $destinationforum);
							}
							$counter = count($child_recs);
							if ($counter > 1) {
								$extratext = ' with ' . $counter . ' replies)';
							} else {
								$extratext = ' with 1 reply)';
							}
						}
						
						// Update forum counters sourceforum
						bbp_update_forum(array ('forum_id'=>$sourceforum));
						// Update freshness: Get last active id, then use that time for the forum
						$lastactiveid = get_post_meta($sourceforum, '_bbp_last_active_id', true);
						if ($lastactiveid) {
							$lastactivetime = get_post_meta($lastactiveid, '_bbp_last_active_time', true);
							update_post_meta($sourceforum, '_bbp_last_active_time', $lastactivetime);
						}
						// Update forum counters destinationforum
						bbp_update_forum(array ('forum_id'=>$destinationforum));
						// Update freshness: Get last active id, then use that time for the forum
						$lastactiveid = get_post_meta($destinationforum, '_bbp_last_active_id', true);
						if ($lastactiveid) {
							$lastactivetime = get_post_meta($lastactiveid, '_bbp_last_active_time', true);
							update_post_meta($destinationforum, '_bbp_last_active_time', $lastactivetime);
						}
						
						// Display done
						echo bbp_get_topic_title($moveit) . ' (#' . $moveit . $extratext . ' - MOVED.<br>';
					}
				}
			}
		}
		echo '<br><form action="" method="post">';
		echo '<input type="submit" name="goforum" value="Back to topics" />';
		echo '<input type="hidden" name="sourceforum" value="'.$_POST["sourceforum"].'" />';
		echo '</form>';
	
		return;
	}

	// ZAP selected items	
	if ( isset($_POST['zapselected']) ) {
		if( empty($_POST["bbpmtcb"]) ) {
			echo 'No topics selected for hiding';
		} else {
			echo 'Forum : '.bbp_get_forum_title($_POST["sourceforum"]).'<br><br>';
			foreach ($_POST["bbpmtcb"] as $zapit) {
				add_post_meta($zapit, '_bbpmt_zapped', date( 'Y-m-d H:i:s', current_time( 'timestamp', 1 ) ), true);
				echo bbp_get_topic_title($zapit) . ' HIDDEN.<br>';
			}
		}
		echo '<br><form action="" method="post">';
		echo '<input type="submit" name="goforum" value="Back to topics" />';
		echo '<input type="hidden" name="sourceforum" value="'.$_POST["sourceforum"].'" />';
		echo '</form>';
		return;
	}

	// UNZAP selected items	
	if ( isset($_POST['unzapselected']) ) {
		if( empty($_POST["bbpmtcb"]) ) {
			echo 'No topics selected for unhiding';
		} else {
			echo 'Forum : '.bbp_get_forum_title($_POST["sourceforum"]).'<br><br>';
			foreach ($_POST["bbpmtcb"] as $zapit) {
				delete_post_meta($zapit, '_bbpmt_zapped');
				echo bbp_get_topic_title($zapit) . ' UNHIDDEN.<br>';
			}
		}
		echo '<br><form action="" method="post">';
		echo '<input type="submit" name="gohome" value="Back to homepage" />';
		echo '</form>';
		return;
	}

	// View ZAPped items	
	if ( isset($_POST['gozapped']) ) {
		global $wpdb;
		$limitrecords = 5;
		$limitwords = 25;
		$zaporder = 'post';
		if ($_POST["zaporder"] = 'zap') $zaporder = 'hidden';
		echo '<h2>Hidden topics ordered by '.$zaporder.' date</h2>';
		// Get child records
		$sqlquery = 'SELECT ID, post_title, post_content, meta_value, post_date FROM {$wpdb->posts}, {$wpdb->postmeta} WHERE post_status = 0 AND ID = post_id AND meta_key = \'_bbpmt_zapped\'';
		if ($zaporder == 'post') {
			$child_recs = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content, meta_value, post_date FROM {$wpdb->posts}, {$wpdb->postmeta} WHERE post_status = 0 AND ID = post_id AND meta_key = '_bbpmt_zapped' ORDER BY post_date DESC LIMIT %d", $limitrecords ) );
		} else {
			$child_recs = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content, meta_value, post_date FROM {$wpdb->posts}, {$wpdb->postmeta} WHERE post_status = 0 AND ID = post_id AND meta_key = '_bbpmt_zapped' ORDER BY meta_value DESC LIMIT %d", $limitrecords ) );
		}
		echo '<form action="" method="post" id="bbpmttopicform">';
		if ($child_recs) {
			echo '<table id="bbpmt-forum-topics">';
			echo '<tr><th></th><th>Topic</th><th>Topic date</th><th>Zap date</th></tr>';
			foreach ($child_recs as $topic_details) {
				$topic_id = $topic_details->ID;
				$scontent = removehtml_and_cutwords($topic_details->post_content, $limitwords);
				// Start display row, checkbox, post
				echo '<tr>';
				echo '<td><input type="checkbox" class="bbpmtcbgroup" id="bbpmtcb_'.$topic_id.'" name="bbpmtcb[]" value="' . $topic_id . '"></td>';
				echo '<td><b>'.$topic_details->post_title.' ('.$topic_id.')</b><br>'.$scontent.'</td>';
				echo '<td nowrap>'.$topic_details->post_date.'</td>';
				echo '<td nowrap>'.$topic_details->meta_value.'</td>';
				echo '</tr>';
			}
			echo '</table>';
			echo '_______<br><br>';
			echo '<input type="checkbox" id="bbpmtcbgroup_master" onchange="bbpmttoggleall(this,\'bbpmtcbgroup\')" /> Select All/None<br><br>';
			echo '<input type="submit" name="unzapselected" value="UnZAP selected" />&nbsp;';
		}
		echo '<input type="submit" name="gohome" value="Back to homepage" />';
		echo '</form>';
		return;
	}

	// Options need to be saved
	if ( isset($_POST['optssave']) ) {
		if( !empty($_POST["bbpmt-ptot-donot-close"]) ) {
			add_option('bbpmt-ptot-donot-close', 'yes');
		} else {
			delete_option('bbpmt-ptot-donot-close');
		}		
		if( !empty($_POST["bbpmt-ptot-del-comments"]) ) {
			add_option('bbpmt-ptot-del-comments', 'yes');
		} else {
			delete_option('bbpmt-ptot-del-comments');
		}		
		if( !empty($_POST["bbpmt-ptot-add-author"]) ) {
			add_option('bbpmt-ptot-add-author', 'yes');
		} else {
			delete_option('bbpmt-ptot-add-author');
		}		
		if ( empty ($_POST['bbpmt-ptot-anon-author'] ) ) {
			delete_option('bbpmt-ptot-anon-author');
		} else {
			update_option('bbpmt-ptot-anon-author', $_POST['bbpmt-ptot-anon-author']);
		}
		if( !empty($_POST["bbpmt-ptot-finalcomment-cb"]) ) {
			add_option('bbpmt-ptot-finalcomment-cb', 'yes');
		} else {
			delete_option('bbpmt-ptot-finalcomment-cb');
		}
		if( !empty($_POST["bbpmt-ptot-finalcomment-link"]) ) {
			add_option('bbpmt-ptot-finalcomment-link', 'yes');
		} else {
			delete_option('bbpmt-ptot-finalcomment-link');
		}
		if ( !empty($_POST["bbpmt-ptot-finalcomment-text"]) ) {
			update_option('bbpmt-ptot-finalcomment-text', $_POST["bbpmt-ptot-finalcomment-text"]);
		} else {
			update_option('bbpmt-ptot-finalcomment-text', 'The comments have been transferred to your forum. For further replies please visit:');
		}
		if( !empty($_POST["bbpmt-ptot-cuttopic-cb"]) ) {
			add_option('bbpmt-ptot-cuttopic-cb', 'yes');
		} else {
			delete_option('bbpmt-ptot-cuttopic-cb');
		}
		if ( !empty($_POST["bbpmt-ptot-cuttopic-nbr"]) ) {
			update_option('bbpmt-ptot-cuttopic-nbr', $_POST["bbpmt-ptot-cuttopic-nbr"]);
		} else {
			delete_option('bbpmt-ptot-cuttopic-nbr');
		}
		if( !empty($_POST["bbpmt-ptot-cutcomment-cb"]) ) {
			add_option('bbpmt-ptot-cutcomment-cb', 'yes');
		} else {
			delete_option('bbpmt-ptot-cutcomment-cb');
		}
		if ( !empty($_POST["bbpmt-ptot-cutcomment-nbr"]) ) {
			update_option('bbpmt-ptot-cutcomment-nbr', $_POST["bbpmt-ptot-cutcomment-nbr"]);
		} else {
			delete_option('bbpmt-ptot-cutcomment-nbr');
		}
		if ( !empty($_POST["bbpmt-ptot-add-post-link"]) ) {
			update_option('bbpmt-ptot-add-post-link', $_POST["bbpmt-ptot-add-post-link"]);
		} else {
			delete_option('bbpmt-ptot-add-post-link');
		}
	}
	
	
	// No POST or GET, so display initial page
	if ( bbp_has_forums() ) {
		$forumarray = bbpmt_get_forum_structure();
		echo '<form action="" method="post">';
		echo 'Move topics from source forum ';
		echo '<select name="sourceforum" id="sourceforum">';
		echo '<option value="">-- Choose forum --</option>';
		foreach ($forumarray as $forum_info) {
			echo '<option value="'.$forum_info[0].'">'.$forum_info[1].'</option>';
		}

		echo '</select><input type="submit" name="goforum" value="'; _e('Go', 'bbp-move-topics'); echo '" />';

		echo '<p></p><form action="" method="post">';
		_e('View the hidden items', 'bbp-move-topics');
		echo ' <select name="zaporder" id="zaporder">';
		echo '<option value="zap">'; _e('by hidden date', 'bbp-move-topics'); echo '</option>';
		echo '<option value="post">'; _e('by post date', 'bbp-move-topics'); echo '</option>';
		echo '<input type="submit" name="gozapped" value="'; _e('Go', 'bbp-move-topics'); echo '" /> ';
		_e('(<strong>Hiding</strong> means marking a topic as OK in the forum it is in. This has no impact on bbPress, but the topic will no longer show in the list of topics to be moved when using this plugin)', 'bbp-move-topics');
		echo '</p>';
		
		echo '<p>&nbsp;</p><h1>'; _e('Move Posts to Topics (and Comments to Replies)', 'bbp-move-topics'); echo '</h1>';
		$bbpmt_ptot_close_for_comment = get_option('bbpmt-ptot-close-for-comment', false); // Close for new comments
		$bbpmt_ptot_del_comments = get_option('bbpmt-ptot-del-comments', false); // Delete the original comments
		$bbpmt_ptot_add_author = get_option('bbpmt-ptot-add-author', false); // Add the author name (if comment was unknown WordPress user) as extra info into the reply
		$bbpmt_ptot_anon_author = get_option('bbpmt-ptot-anon-author', false); // Username of the user that will be assigned to the anonymous replies
		$bbpmt_ptot_finalcomment_cb = get_option('bbpmt-ptot-finalcomment-cb', false); //
		$bbpmt_ptot_finalcomment_text = get_option('bbpmt-ptot-finalcomment-text', false); //
		$bbpmt_ptot_finalcomment_link = get_option('bbpmt-ptot-finalcomment-link', false); //
		$bbpmt_ptot_cuttopic_cb = get_option('bbpmt-ptot-cuttopic-cb', false); //
		$bbpmt_ptot_cuttopic_nbr = get_option('bbpmt-ptot-cuttopic-nbr', false); //
		$bbpmt_ptot_cutcomment_cb = get_option('bbpmt-ptot-cutcomment-cb', false); //
		$bbpmt_ptot_cutcomment_nbr = get_option('bbpmt-ptot-cutcomment-nbr', false); //
		$bbpmt_ptot_add_post_link = get_option('bbpmt-ptot-add-post-link', false); //
		
		echo '<h2>&nbsp;&nbsp;'; _e('Settings', 'bbp-move-topics'); echo '</h2>';
			echo '<h3 style="text-indent:-20px;margin-left:50px;">'; _e('On the original', 'bbp-move-topics'); echo '</h3>';
			echo '<form action="" method="post">';
			
			echo '<p style="text-indent:-20px;margin-left:80px;"><input type="checkbox" name="bbpmt-ptot-close-for-comment" id="bbpmt-ptot-close-for-comment" value="bbpmt-ptot-close-for-comment" ';
			if ($bbpmt_ptot_close_for_comment) { echo 'checked'; }
			echo '><label for="bbpmt-ptot-close-for-comment">&nbsp;'; _e('Close post for comments after conversion', 'bbp-move-topics'); echo '</label></p>';

			echo '<p style="text-indent:-20px;margin-left:80px;"><input type="checkbox" name="bbpmt-ptot-del-comments" id="bbpmt-ptot-del-comments" value="bbpmt-ptot-del-comments" ';
			if ($bbpmt_ptot_del_comments) { echo 'checked'; }
			echo '><label for="bbpmt-ptot-del-comments">&nbsp;'; _e('Delete comments after topic and replies creation', 'bbp-move-topics'); echo '</label></p>';

			echo '<p style="text-indent:-20px;margin-left:80px;"><input type="checkbox" name="bbpmt-ptot-finalcomment-cb" id="bbpmt-ptot-finalcomment-cb" value="bbpmt-ptot-finalcomment-cb" ';
			if ($bbpmt_ptot_finalcomment_cb) { echo 'checked'; }
			echo '>';
			echo '<label>&nbsp;'; _e('Create a new final comment on original post: ', 'bbp-move-topics'); echo' </label>';
			echo '<input type="text" name="bbpmt-ptot-finalcomment-text" id="bbpmt-ptot-finalcomment-text" value="' . $bbpmt_ptot_finalcomment_text . '" size="80" />';
			echo '<label>&nbsp;'; _e('(Empty and save for original text)', 'bbp-move-topics'); echo'</label></p>';
			
			echo '<p style="text-indent:-20px;margin-left:140px;"><input type="checkbox" name="bbpmt-ptot-finalcomment-link" id="bbpmt-ptot-finalcomment-link" value="bbpmt-ptot-finalcomment-link" ';
			if ($bbpmt_ptot_finalcomment_link) { echo 'checked'; }
			echo '><label for="bbpmt-ptot-finalcomment-link">&nbsp;'; _e('Insert the link to the forum topic just after the above text', 'bbp-move-topics'); echo '</label></p>';

			echo '<h3>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'; _e('The target topic', 'bbp-move-topics'); echo '</h3>';

			echo '<p style="text-indent:-20px;margin-left:80px;"><input type="checkbox" name="bbpmt-ptot-cuttopic-cb" id="bbpmt-ptot-cuttopic-cb" value="bbpmt-ptot-cuttopic-cb" ';
			if ($bbpmt_ptot_cuttopic_cb) { echo 'checked'; }
			echo '>';
			echo '<label>&nbsp;'; _e('Cut the newly created topic after ', 'bbp-move-topics'); echo' </label>';
			echo '<input type="text" name="bbpmt-ptot-cuttopic-nbr" id="bbpmt-ptot-cutwords-nbr" value="' . $bbpmt_ptot_cuttopic_nbr . '" size="3" />';
			echo '<label>&nbsp;'; _e('words', 'bbp-move-topics'); echo'</label></p>';

			echo '<p style="text-indent:-20px;margin-left:80px;"><input type="checkbox" name="bbpmt-ptot-add-post-link" id="bbpmt-ptot-add-post-link" value="bbpmt-ptot-add-post-link" ';
			if ($bbpmt_ptot_add_post_link) { echo 'checked'; }
			echo '><label for="bbpmt-ptot-add-post-link">&nbsp;'; _e('Insert the link to the original (custom) post or page as last line of the topic', 'bbp-move-topics'); echo '</label></p>';

			echo '<h3 style="text-indent:-20px;margin-left:50px;">'; _e('The target replies', 'bbp-move-topics'); echo '</h3>';
			
			echo '<p style="text-indent:-20px;margin-left:80px;"><input type="checkbox" name="bbpmt-ptot-cutcomment-cb" id="bbpmt-ptot-cutcomment-cb" value="bbpmt-ptot-cutcomment-cb" ';
			if ($bbpmt_ptot_cutcomment_cb) { echo 'checked'; }
			echo '>';
			echo '<label>&nbsp;'; _e('Cut the newly created comments after ', 'bbp-move-topics'); echo' </label>';
			echo '<input type="text" name="bbpmt-ptot-cutcomment-nbr" id="bbpmt-ptot-cutwords-nbr" value="' . $bbpmt_ptot_cutcomment_nbr . '" size="3" />';
			echo '<label>&nbsp;'; _e('words', 'bbp-move-topics'); echo'</label></p>';


			echo '<p style="text-indent:-20px;margin-left:80px;"><input type="checkbox" name="bbpmt-ptot-add-author" id="bbpmt-ptot-add-author" value="bbpmt-ptot-add-author" ';
			if ($bbpmt_ptot_add_author) { echo 'checked'; }
			echo '><label for="bbpmt-ptot-add-author">&nbsp;'; _e('Add original comment author name to the reply (on an extra last line) in case not a WordPress user for this site.', 'bbp-move-topics'); echo '</label></p>';
			
			echo '<p style="text-indent:-20px;margin-left:80px;"><label>'; _e('In case of anonymous user, use this exisiting <strong>username/login</strong> for creating topic/reply:', 'bbp-move-topics'); echo' </label>';
			echo '<input type="text" name="bbpmt-ptot-anon-author" id="bbpmt-ptot-anon-author" value="' . $bbpmt_ptot_anon_author . '" size="20" />';
			echo '<label>&nbsp;'; _e('(Leave empty to assign topics and replies to the user performing the action )', 'bbp-move-topics'); echo'</label></p>';
			
			if ($bbpmt_ptot_anon_author) {
				$testuser = get_user_by( 'login', $bbpmt_ptot_anon_author );
				if (!$testuser) echo '<p style="color:red;text-indent:-20px;margin-left:80px;">&nbsp;WARNING: login <strong>' .  $bbpmt_ptot_anon_author . '</strong> not found! Please choose a correct one and save again.</p>';
			}
	
			echo '<p style="text-indent:-20px;margin-left:50px;"><input type="submit" name="optssave" value="'; _e('Save settings', 'bbp-move-topics'); echo'" /></p>';
			echo '</form>';
		echo '<h2>&nbsp;&nbsp;'; _e('Convert', 'bbp-move-topics'); echo '</h2>';
			echo '<p style="text-indent:-20px;margin-left:80px;"><label>'; _e('Select one or more posts or pages in the normal edit post screen and use the dropdown list on that page to select the target forum.', 'bbp-move-topics');
			echo '<br><a href="' . get_site_url() . '/wp-admin/edit.php">'; _e('Go to posts', 'bbp-move-topics'); echo'</a>&nbsp;';
				echo'<a href="' . get_site_url() . '/wp-admin/edit.php?post_type=page">'; _e('Go to pages', 'bbp-move-topics'); echo'</a>&nbsp;</label>';

			echo '<p style="text-indent:-20px;margin-left:80px;"><label>'; _e('<strong>(Experimental!)</strong> Select one or more items from your custom post types to convert.', 'bbp-move-topics');
			$args = array(
				'public'   => true,
				'_builtin' => false
			);
			$output = 'names'; // names or objects, note names is the default
			$operator = 'and'; // 'and' or 'or'
			$post_types = get_post_types( $args, $output, $operator ); 
			unset($post_types['forum']);
			unset($post_types['topic']);
			unset($post_types['reply']);
			
			echo '<br><select name="CPTmenu1" id="CPTmenu1">';
			echo '<option value="">'; _e('Choose post_type...', 'bbp-move-topics'); echo '</option>';
			foreach ( $post_types  as $post_type ) {
				echo '<option value="' . get_site_url() . '/wp-admin/edit.php?post_type=' . $post_type . '">' . $post_type . '</option>';
			}
			echo '</select>';
			?>
				<script type="text/javascript">
				 var urlmenu = document.getElementById( 'CPTmenu1' );
				 urlmenu.onchange = function() {
				      window.open( this.options[ this.selectedIndex ].value );
				 };
				</script>
				<?php
	} else {
		echo 'No forums found';
	}

}

function bbpmt_admin_header() {
	echo '<script type=\'text/javascript\'>';
	echo 'function bbpmttoggleall(master,group) {';
	echo '	var cbarray = document.getElementsByClassName(group);';
	echo '	for(var i = 0; i < cbarray.length; i++){';
	echo '		var cb = document.getElementById(cbarray[i].id);';
	echo '		cb.checked = master.checked;';
	echo '	}';
	echo '}';
	echo '</script>';

}

// Checks during activation
function bbpmt_check_activation( $network_wide ) {
    if ( $network_wide ) {
		// Current version is not ready for MU so don't allow network wide activation
		deactivate_plugins( plugin_basename( __FILE__ ), TRUE, TRUE );
		trigger_error('Sorry, but this plugin is not ready for network activation, so please activate on the subsite. One of the next versions will support Network Activation.',E_USER_ERROR);
		die();
	} else {
		// do not activate if bbPress is not running
		if( !function_exists('is_bbpress') ) {
			deactivate_plugins( plugin_basename( __FILE__ ), TRUE );
			trigger_error('Please install and activate <b>bbPress</b> before activating this plugin.',E_USER_ERROR);
			die();
		}
	}
}

add_action('admin_menu', function(){
	$confHook = add_submenu_page('edit.php?post_type=forum', 'Move topics', 'Move topics', 'publish_forums', 'forums_move_topics', 'forums_move_topics_page');
	add_action("admin_head-$confHook", 'bbpmt_admin_header');
}
); // end add_action

// Create Text Domain For Translations
function bbpmt_textdomain() {
	load_plugin_textdomain( 'bbp-move-topics', false, dirname( plugin_basename( __FILE__ ) ) );
}
add_action( 'plugins_loaded', 'bbpmt_textdomain' );

?>