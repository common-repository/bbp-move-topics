<?php

add_action('restrict_manage_posts', 'bbpmt_bulk_dropdown');
function bbpmt_bulk_dropdown() {
	// Bail if current user cannot promote users 
	// if ( !current_user_can( 'promote_users' ) )
	//	return;
	global $typenow;
	if (!in_array($typenow,array("forum","topic","reply"))) {
		?>
		<div style="float: right;">
		<label class="screen-reader-text" for="bbpmt-bulklist"><?php esc_html_e('Create topic(s) in forum&hellip;') ?></label>
		<select name="bbpmt-bulklist" id="bbpmt-bulklist" style="display:inline-block; float:none;">
			<option value=''><?php esc_html_e('Create topic(s) in forum&hellip;') ?></option>
			<?php
			if ( bbp_has_forums() ) {
				while (bbp_forums() ) {
					bbp_the_forum();
					$forum_id = bbp_get_forum_id();
					$forum_title = bbp_get_forum_title($forum_id);
					echo '<option value="'.$forum_id.'">'.$forum_title.'</option>';
					$subf1 = bbp_forum_get_subforums($forum_id);
					if ($subf1) {
						foreach ($subf1 as $sub1forum) {
							$sub1_forum_id = $sub1forum->ID;
							$forum_title = '&nbsp;&nbsp;'.bbp_get_forum_title($sub1_forum_id);
							echo '<option value="'.$sub1_forum_id.'">'.$forum_title.'</option>';
						}
					}
				}
			}
		?>	
		</select><?php submit_button( __('Apply', 'bbp-move-topics'), 'secondary', 'converttotopic', false );
		echo '</div>';
		
		wp_nonce_field( 'bbp-bulk-posts', 'bbp-bulk-posts-nonce' );
	}
}

add_action('load-edit.php', 'bbpmt_bulk_action');
function bbpmt_bulk_action() {
	global $typenow;
	$post_type = $typenow;
	
	if (!in_array($typenow,array("forum","topic","reply"))) {
	
		// get the action
		$wp_list_table = _get_list_table('WP_Posts_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
		$action = $wp_list_table->current_action();
		
		if ( empty( $_REQUEST['converttotopic'] ) )
			return;

		// security check
		check_admin_referer('bbp-bulk-posts', 'bbp-bulk-posts-nonce' );
		
		// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
		if(isset($_REQUEST['post'])) {
			$post_ids = array_map('intval', $_REQUEST['post']);
		}

		if(empty($post_ids)) return;
		
		// this is based on wp-admin/edit.php
		$sendback = remove_query_arg( array('exported', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
		if ( ! $sendback )
			$sendback = admin_url( "edit.php?post_type=$post_type" );
		
		$pagenum = $wp_list_table->get_pagenum();
		$sendback = add_query_arg( 'paged', $pagenum, $sendback );
		
				
		// Set up user permissions/capabilities, the code might look like:
		// if ( !current_user_can($post_type_object->cap->export_post, $post_id) )
		//	wp_die( __('You are not allowed to convert this post.') );
		
		$converted = 0;
		foreach( $post_ids as $post_id ) {
			
			if ( !bbpmt_perform_conversion($post_id, $_REQUEST['bbpmt-bulklist']) )
				wp_die( __('Error converting post.') );

			$converted++;
		}
		
		$sendback = add_query_arg( array('converted' => $converted, 'ids' => join(',', $post_ids) ), $sendback );
	
		$sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );
		
		wp_redirect($sendback);
		exit();
	}
}

add_action('admin_notices', 'bbpmt_bulk_admin_notices');
function bbpmt_bulk_admin_notices() {
	global $post_type, $pagenow;
	
	if($pagenow == 'edit.php' && $post_type == 'post' && isset($_REQUEST['converted']) && (int) $_REQUEST['converted']) {
		$message = sprintf( _n( 'Post converted.', '%s posts converted.', $_REQUEST['converted'] ), number_format_i18n( $_REQUEST['converted'] ) );
		echo "<div class=\"updated\"><p>{$message}</p></div>";
	}
}

function bbpmt_perform_conversion($post_id, $to_forum) {
	// These should come from global options
	$bbpmt_ptot_close_for_comment = get_option('bbpmt-ptot-close-for-comment', false);
	$bbpmt_delete_orig = get_option('bbpmt-ptot-del-comments', false);
	$bbpmt_add_author = get_option('bbpmt-ptot-add-author', false);
	$bbpmt_ptot_anon_author = get_option('bbpmt-ptot-anon-author', false);
		
	$bbpmt_unknown_author_id = 0; // Default is current user
	if ($bbpmt_ptot_anon_author) {
		$user = get_user_by( 'login', $bbpmt_ptot_anon_author );
		if ($user) {
			$bbpmt_unknown_author_id = $user->ID;
		}
	}
	
	// Get post details
	$mypost = get_post($post_id);
	
	// Close post for comments
	if ($bbpmt_ptot_close_for_comment) {
		$closepost = array(
			'ID' => $post_id,
			'comment_status' => 'closed',
			'ping_status' => 'closed'
		);
		$outcome = wp_update_post( $closepost, true );
		if (is_wp_error($outcome)) {
			$errors = $outcome->get_error_messages();
			foreach ($errors as $error) {
				echo $error;
				return false;
			}
		}
	}		
	
	// Cut topic if requested
	$pcontent = $mypost->post_content;
	$bbpmt_ptot_cuttopic_cb = get_option('bbpmt-ptot-cuttopic-cb', false);
	if ($bbpmt_ptot_cuttopic_cb) {
		$bbpmt_ptot_cuttopic_nbr = get_option('bbpmt-ptot-cuttopic-nbr', false);
		if ($bbpmt_ptot_cuttopic_nbr) {
			$pcontent = removehtml_and_cutwords($pcontent, $bbpmt_ptot_cuttopic_nbr);
		}
	}
	
	// Add link to initial post, page or custom post type
	$bbpmt_ptot_add_post_link = get_option('bbpmt-ptot-add-post-link', false); //
	if ($bbpmt_ptot_add_post_link) $pcontent = $pcontent . '<br>' . get_permalink($post_id);
			
	// Create topic
	$mytopic = array(
		'post_title' => $mypost->post_title,
		'post_content' => $pcontent,
		'post_status' => $mypost->post_status,
		'post_author' => $mypost->post_author,
		'post_type' => 'topic',
		'post_name' => $mypost->post_name,
		'post_date' => $mypost->post_date,
		'post_date_gmt' => $mypost->post_date_gmt,
		'post_parent' => $to_forum
	);  
	$mytopic_meta = array(
		'forum_id' => $to_forum,
		'author_ip' => '0.0.0.0',
		'last_active_time' => $mypost->post_date
	);  
	$topic_id = bbp_insert_topic( $mytopic, $mytopic_meta );

	// Get all comments and create replies
	$to_delete_ids = array();
	$comments_args = array (
		'post_id' => $post_id,
		'order' => 'ASC'
	);
	$comments = get_comments($comments_args);
	foreach($comments as $comment) {
		// set title
		$title = __('Reply To:') .' ' . $mypost->post_title;
		
		// get parent
		$parent_id = '';
		if ($comment->comment_parent) {
			// This is a reply to a comment
			$temp = get_comment_meta( intval($comment->comment_parent), 'bbpmt_convtoreply_id', true );
			if ($temp) {
				// attach to parent reply
				$parent_id = intval($temp);
				$title = __('Subreply To:') .' ' . $mypost->post_title;
			}
		} else {
			// This is a normal comment, parent is topic
			$parent_id = $topic_id;
			$title = __('Reply To:') .' ' . $mypost->post_title;
		}
		
		$content = $comment->comment_content;

		// Cut comment if requested
		$bbpmt_ptot_cutcomment_cb = get_option('bbpmt-ptot-cutcomment-cb', false);
		if ($bbpmt_ptot_cutcomment_cb) {
			$bbpmt_ptot_cutcomment_nbr = get_option('bbpmt-ptot-cutcomment-nbr', false);
			if ($bbpmt_ptot_cutcomment_nbr) {
				$content = removehtml_and_cutwords($content, $bbpmt_ptot_cutcomment_nbr);
			}
		}

		//check author and adapt comment if needed with the author
		if($comment->user_id != 0) {
			$post_author = $comment->user_id;
		} else {
			if ($bbpmt_unknown_author_id) {
				$post_author = $bbpmt_unknown_author_id;
			} else {
				$post_author = bbp_get_current_user_id();
			}
			if ($bbpmt_add_author) {
				$content = $content . '<br>' . __('Original author:') . ' ' . $comment->comment_author;
			}
		}
		
		//if comment was not approved, create it as pending reply
		$pstatus = 'publish';
		if ($comment->comment_approved == 0) $pstatus = 'pending';
		
		// insert reply
		$myreply = array(
			'post_title' => $title,
			'post_parent' => $topic_id,
			'post_status' => $pstatus,
			'post_type' => 'reply',
			'post_author' => $post_author,
			'post_content' => $content,
			'post_date' => $comment->comment_date,
			'post_date_gmt' => $comment->comment_date_gmt,
			'post_parent' => $topic_id
		);  
		$myreply_meta = array(
			'forum_id' => $to_forum,
			'author_ip' => $comment->comment_author_IP,
			'topic_id' => $topic_id,
			'reply_to' => $parent_id,
			'bbpmt_orig_post_id' => $post_id,
			'bbpmt_orig_comment_id' => $comment->comment_ID
		);  		
		$reply_id = bbp_insert_reply( $myreply, $myreply_meta );

		// Add meta to original comment
		update_comment_meta( $comment->comment_ID, 'bbpmt_convtoreply_id', $reply_id );
		
		// Add id to be deleted
		array_push( $to_delete_ids , $comment->comment_ID );

	}			

	// Delete all comments
	if ($bbpmt_delete_orig) {
		foreach ($to_delete_ids as $to_delete_id) {
			wp_delete_comment( $to_delete_id );
		}
	}	
		
	// Add 1 comment referring to the topic
	$bbpmt_ptot_finalcomment_cb = get_option('bbpmt-ptot-finalcomment-cb', false);
	if ($bbpmt_ptot_finalcomment_cb) {
		// Get text to add, if not exist use basic text
		$bbpmt_ptot_finalcomment_text = get_option('bbpmt-ptot-finalcomment-text', false);
		$bbpmt_ptot_finalcomment_link = get_option('bbpmt-ptot-finalcomment-link', false);
		if (!$bbpmt_ptot_finalcomment_text) {
			$bbpmt_ptot_finalcomment_text = 'The comments have been transferred to our forum. For further replies please visit:';
		}
		$c_content = $bbpmt_ptot_finalcomment_text;
		// check if URL to topic needs to be added
		if (!$bbpmt_ptot_finalcomment_link) {
			$c_content = $c_content . ' ' . bbp_get_topic_permalink($topic_id);
		}
	
		$new_comment = array (
			'comment_post_ID' => $post_id,
			'comment_content' => $c_content,
			'user_id' => bbp_get_current_user_id()
		);	
		wp_new_comment($new_comment);
	}
	
	// Update forum freshness date
	update_post_meta($to_forum, '_bbp_last_active_time', current_time('Y-m-d H:i:s', 0) );
	
	return true;
}	

?>