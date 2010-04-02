<?php

/* Todo:
	- on invitee join:
		- notifications to inviter(s) that individual has joined
	- admin functions:
		- number of email invitees allowed
	- link from group pages	
*/

require( 'db.php' );

function invite_anyone_setup_globals() {
	global $bp, $wpdb;

	$bp->invite_anyone->id = 'invite_anyone';

	$bp->invite_anyone->table_name = $wpdb->base_prefix . 'bp_invite_anyone';
	$bp->invite_anyone->slug = 'invite-anyone';

	/* Register this in the active components array */
	$bp->active_components[$bp->invite_anyone->slug] = $bp->invite_anyone->id;
}
add_action( 'wp', 'invite_anyone_setup_globals', 2 );
add_action( 'admin_menu', 'invite_anyone_setup_globals', 2 );



function invite_anyone_register_screen_message() {
	global $bp;
?>
	<?php if ( $bp->current_action == 'accept-invitation' && !$bp->action_variables[0] ) : ?>
		<div id="message" class="error"><p><?php _e( "It looks like you're trying to accept an invitation to join the site, but some information is missing. Please try again by clicking on the link in the invitation email.", 'bp-invite-anyone' ) ?></p></div>
	<?php endif; ?>
	
	
	<?php if ( $bp->current_action == 'accept-invitation' && $email = urldecode( $bp->action_variables[0] ) ) : ?>
		<?php 			
			$invites = invite_anyone_get_invitations_by_invited_email( $email );
			$inviters = array();
			foreach ( $invites as $invite ) {
				if ( !in_array( $invite->inviter_id, $inviters ) )
					$inviters[] = $invite->inviter_id;
			}
			
			$inviters_text = '';
			if ( count( $inviters ) == 1 ) {
				$inviters_text .= bp_core_get_user_displayname( $inviters[0] );
			} else {
				$counter = 1;
				$inviters_text .= bp_core_get_user_displayname( $inviters[0] );
				while ( $counter < count( $inviters ) - 1 ) {
					$inviters_text .= ', ' . bp_core_get_user_displayname( $inviters[$counter] );
					$counter++;
				}
				$inviters_text .= ' and ' . bp_core_get_user_displayname( $inviters[$counter] );
			}
			
/* Remember: This chunk has to be moved back into the activate once shown working. Change 17 to user_id too! */			

/* Todo: make an error happen when the email address in action_variables isn't real */
			/* begin test */
	
			
			$message = sprintf( __( "Welcome! You've been invited by %s to join the site. Please fill out the information below to create your account.", 'bp-invite-anyone' ), $inviters_text );
				
		?>
		<div id="message" class="success"><p><?php echo $message ?></p></div>	
	<?php endif; ?>
<?php
}
add_action( 'bp_before_register_page', 'invite_anyone_register_screen_message' );


function invite_anyone_activate_user( $user_id, $key, $user ) {
	global $bp;
	
	$email = bp_core_get_user_email( $user_id );

	if ( $invites = invite_anyone_get_invitations_by_invited_email( $email ) ) {
		/* Mark as "is_joined" */
		invite_anyone_mark_as_joined( $email );

		/* Friendship requests */
		$inviters = array();
		foreach ( $invites as $invite ) {
			if ( !in_array( $invite->inviter_id, $inviters ) )
				$inviters[] = $invite->inviter_id;
		}
	
		foreach ( $inviters as $inviter ) {		
			friends_add_friend( $inviter, $user_id );
		}
			
		/* Group invitations */
				
		$groups = array();
		foreach ( $invites as $invite ) {
			if ( !$invite->group_invitations[0] )
				continue;
			else
				$group_invitations = unserialize( $invite->group_invitations );
			
			foreach ( $group_invitations as $group ) {
				if ( !in_array( $group, array_keys($groups) ) )
					$groups[$group] = $invite->inviter_id;
			}
		}


		foreach ( $groups as $group_id => $inviter_id ) {
			$args = array(
				'user_id' => $user_id,
				'group_id' => $group_id,
				'inviter_id' => $inviter_id
			);
			
			groups_invite_user( $args );
			groups_send_invites( $inviter_id, $group_id );
		}			
	}
}
add_action( 'bp_core_activated_user', 'invite_anyone_activate_user', 10, 3 );

function invite_anyone_setup_nav() {
	global $bp;
	
	/* Add 'Send Invites' to the main user profile navigation */
	bp_core_new_nav_item( array(
		'name' => __( 'Send Invites', 'buddypress' ),
		'slug' => $bp->invite_anyone->slug,
		'position' => 80,
		'screen_function' => 'bp_example_screen_one',
		'default_subnav_slug' => 'invite-new-members'
	) );

	$invite_anyone_link = $bp->loggedin_user->domain . $bp->invite_anyone->slug . '/';

	/* Create two sub nav items for this component */
	bp_core_new_subnav_item( array(
		'name' => __( 'Invite New Members', 'bp-invite-anyone' ),
		'slug' => 'invite-new-members',
		'parent_slug' => $bp->invite_anyone->slug,
		'parent_url' => $invite_anyone_link,
		'screen_function' => 'invite_anyone_screen_one',
		'position' => 10,
		'user_has_access' => bp_is_my_profile() // Only the logged in user can access this on his/her profile
	) );

	bp_core_new_subnav_item( array(
		'name' => __( 'Sent Invites', 'bp-invite-anyone' ),
		'slug' => 'sent-invites',
		'parent_slug' => $bp->invite_anyone->slug,
		'parent_url' => $invite_anyone_link,
		'screen_function' => 'invite_anyone_screen_two',
		'position' => 20,
		'user_has_access' => bp_is_my_profile() // Only the logged in user can access this on his/her profile
	) );
}
add_action( 'wp', 'invite_anyone_setup_nav', 2 );
add_action( 'admin_menu', 'invite_anyone_setup_nav', 2 );





function invite_anyone_screen_one() {
	global $bp;

	/*
	print "<pre>";
	print_r($bp);
	*/
	
	/* Add a do action here, so your component can be extended by others. */
	do_action( 'invite_anyone_screen_one' );

	add_action( 'bp_template_content', 'invite_anyone_screen_one_content' );

	bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
}

function invite_anyone_screen_one_content() {
		global $bp;
			
		if ( !$iaoptions = get_option( 'invite_anyone_options' ) )
			$iaoptions = array();
			
		if ( !$max_invites = $iaoptions['max_invites'] )
			$max_invites = 5;
		
		if ( 'group-invites' == $bp->action_variables[0] )
			$from_group = $bp->action_variables[1];
		
		/* Grabs any information previously entered but returned because of an error */
		$returned_emails = array();
		$counter = 0;
		while ( $_GET['email' . $counter] ) {
			$returned_emails[] = urldecode( $_GET['email' . $counter] );
			$counter++;
		}
		
		// $returned_groups is padded so that array_search (below) returns true for first group
		$returned_groups = array( 0 );
		$counter = 0;
		while ( $_GET['group' . $counter] ) {
			$returned_groups[] = urldecode( $_GET['group' . $counter] );
			$counter++;
		}
		
		if ( $_GET['message'] )
			$returned_message = urldecode( $_GET['message'] );
		
	?>
	<form action="<?php echo $bp->displayed_user->domain . $bp->invite_anyone->slug . '/sent-invites/send/' ?>" method="post">
	
	<ol id="invite-anyone-steps">
		<h4><?php _e( 'Invite New Members', 'bp-example' ) ?></h4>
		<p>Invite friends to join <?php echo bloginfo('name'); ?> by following these steps:</p>
		
		<li>
			<p><?php _e( 'Enter email addresses in the fields below.', 'bp-invite-anyone' ) ?> <?php if( invite_anyone_allowed_domains() ) : ?> <?php _e( 'You can only invite people whose email addresses end in one of the following domains:', 'bp-invite-anyone' ) ?> <?php echo invite_anyone_allowed_domains(); ?><?php endif; ?></p>
		</li>
		
		<?php invite_anyone_email_fields( $returned_emails ) ?>
		
		<li>
			<?php _e( '(optional) Customize the text of the invitation.', 'bp-invite-anyone' ) ?></p>
			<textarea rows="5" cols="40" name="invite_anyone_custom_message" id="invite-anyone-custom-message"><?php invite_anyone_invitation_message( $returned_message ) ?></textarea>		
		</li>
		
		<?php if ( bp_has_groups( "type=alphabetical&user_id=" . bp_loggedin_user_id() ) ) : ?>
		<li>
			<p><?php _e( '(optional) Select some groups. Invitees will receive invitations to these groups when they join the site.', 'bp-invite-anyone' ) ?></p>
			<ul id="invite-anyone-group-list">
				<?php while ( bp_groups() ) : bp_the_group(); ?>
					<li>
					<input type="checkbox" name="invite_anyone_groups[]" id="invite_anyone_groups[]" value="<?php bp_group_id() ?>" <?php if ( $from_group == bp_get_group_id() || array_search( bp_get_group_id(), $returned_groups) ) : ?>checked<?php endif; ?> />
					<?php bp_group_avatar_mini() ?>
					<?php bp_group_name() ?>

					</li>
				<?php endwhile; ?>
			
			</ul>
		
		</li>
		<?php endif; ?>
		
	</ol>
	
	<div class="submit">
		<input type="submit" name="invite-anyone-submit" id="invite-anyone-submit" value="<?php _e( 'Send Invites', 'buddypress' ) ?> " />
	</div>
	
	
	</form>
	<?php
	}

/**
 * invite_anyone_screen_two()
 *
 */
function invite_anyone_screen_two() {
	global $bp;
	
	/* Todo: "Are you sure" page after "Send Invites" */
	if ( $bp->current_component == $bp->invite_anyone->slug && $bp->current_action == 'sent-invites' && $bp->action_variables[0] == 'send' ) {
		if ( invite_anyone_process_invitations( $_POST ) )
			bp_core_add_message( __( 'Your invitations were sent successfully!', 'bp-invite-anyone' ), 'success' );
		else
			bp_core_add_message( __( 'Sorry, there was a problem sending your invitations. Please try again.', 'bp-invite-anyone' ), 'error' );
	}
	
	do_action( 'invite_anyone_sent_invites_screen' );

	add_action( 'bp_template_content', 'invite_anyone_screen_two_content' );

	bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
}


	function invite_anyone_screen_two_content() {
		global $bp; ?>

		<h4><?php _e( 'Sent Invites', 'bp-example' ) ?></h4>

		<p><?php _e( 'You have sent invitations to the following people.', 'bp-invite-anyone' ) ?></p>
		
		<?php $invites = invite_anyone_get_invitations_by_inviter_id( bp_loggedin_user_id() ) ?>
		
		<table class="invite-anyone-sent-invites">
			<tr>
				<th scope="column"><?php _e( 'Invited email address', 'bp-invite-anyone' ) ?></th>
				<th scope="column"><?php _e( 'Group invitations', 'bp-invite-anyone' ) ?></th>
				<th scope="column"><?php _e( 'Sent', 'bp-invite-anyone' ) ?></th>
				<th scope="column"><?php _e( 'Accepted', 'bp-invite-anyone' ) ?></th>
			</tr>
			
			<?php foreach( $invites as $invite ) : ?>
			<?php
				if ( $invite->group_invitations ) {
					$groups = unserialize( $invite->group_invitations );
					$group_names = '<ul>';
					foreach( $groups as $group_id ) {
						$group = new BP_Groups_Group( $group_id );
						$group_names .= '<li>' . bp_get_group_name( $group ) . '</li>';
					}
					$group_names .= '</ul>';
				} else {
					$group_names = '-';
				}
			
				$date_invited = invite_anyone_format_date( $invite->date_invited );
			
				if ( $invite->date_joined )
					$date_joined = invite_anyone_format_date( $invite->date_joined );
				else
					$date_joined = '-';
			?>
			
			<tr>
				<td><?php echo $invite->email ?></td>
				<td><?php echo $group_names ?></td>
				<td><?php echo $date_invited ?></td>
				<td><?php echo $date_joined ?></td>
			</tr>
			<?php endforeach; ?>
			
		
		</table>
		
	<?php
	}

/**
 * invite_anyone_email_fields()
 *
 */
function invite_anyone_email_fields( $returned_emails = false ) {
	if ( !$iaoptions = get_option( 'invite_anyone_options' ) )
		$iaoptions = array();
		
	if ( !$max_invites = $iaoptions['max_invites'] )
		$max_invites = 5;
	
?>
	<ol id="invite-anyone-email-fields">
	<?php for( $i = 0; $i < $max_invites; $i++ ) : ?>
		<li>
			<input type="text" name="invite_anyone_email[]" class="invite-anyone-email-field" size="30" <?php if ( $returned_emails[$i] ) : ?>value="<?php echo $returned_emails[$i] ?>"<?php endif; ?>" />
		</li>
	<?php endfor; ?>
	</ol>
<?php
}

function invite_anyone_invitation_message( $returned_message = false ) {
	global $bp;
	
	if ( !$returned_message ) {
		if ( !$iaoptions = get_option( 'invite_anyone_options' ) )
			$iaoptions = array();
		
		if ( !$text = $iaoptions['default_invitation_message'] ) {
			$inviter_name = $bp->loggedin_user->userdata->display_name;
			$site_name = get_bloginfo('name');
			$text = "You have been invited by $inviter_name to join the $site_name community."; 
		}
	} else {
		$text = $returned_message;	
	}
	
	echo $text;
}

function invite_anyone_allowed_domains() {
	
	$domains = '';
	
	if ( function_exists( 'get_site_option' ) ) {
		$limited_email_domains = get_site_option( 'limited_email_domains' );
		
		if ( !$limited_email_domains )
			return $domains;
		
		foreach( $limited_email_domains as $domain )
			$domains .= "<strong>$domain</strong> ";
	}
	
	return $domains;
}


function invite_anyone_format_date( $date ) {
	$thetime = strtotime( $date );
	$thetime = strftime( "%D", $thetime );
	return $thetime;
}

function invite_anyone_process_invitations( $data ) {
	global $bp;
	
	$emails = array();
	foreach ( $data['invite_anyone_email'] as $email ) {
		if ( $email != '' )
			$emails[] = $email;
	}
	
	if ( empty($emails) ) {
		bp_core_add_message( __( "You didn't include any email addresses!", 'bp-invite-anyone' ), 'error' );
		bp_core_redirect( $bp->loggedin_user->domain . $bp->invite_anyone->slug . '/invite-new-members' );
	}
	
	/* validate email addresses */
	foreach( $emails as $email ) {
		$check = invite_anyone_validate_email( $email );
		switch ( $check ) {
			case 'unsafe' :
				bp_core_add_message( __("Sorry, $email is not a permitted email address.", 'bp-invite-anyone' ), 'error' );
				$is_error = 1;
				break;
			
			case 'invalid' :
				bp_core_add_message( __("Sorry, $email is not a valid email address. Please make sure that you have typed it correctly.", 'bp-invite-anyone' ), 'error' );
				$is_error = 1;
				break;
			
			case 'limited_domain' :
				bp_core_add_message( __( "Sorry, $email is not a permitted email address. Please make sure that you have typed the domain name correctly.", 'bp-invite-anyone' ), 'error');
				$is_error = 1;
				break;
			
			case 'used' :
				bp_core_add_message( __( "$email is already a registered user of this site.", 'bp-invite_anyone'), 'error');
				$is_error = 1;
				break;		
		}
		
		if ( $is_error ) {
			$d = '';
			foreach ( $emails as $key => $email )
				$d .= "email$key=" . urlencode($email) . '&';
		
			foreach ( $data['invite_anyone_groups'] as $key => $group )
				$d .= "group$key=" . $group . '&';
			
			$d .= 'message=' . urlencode($data['invite_anyone_custom_message']);
		
			bp_core_redirect( $bp->loggedin_user->domain . $bp->invite_anyone->slug . '/invite-new-members?' . $d  );
		}		
	}
	
	/* send and record invitations */
	
	$groups = $data['invite_anyone_groups'];	
	$is_error = 0;
	
	foreach( $emails as $email ) {
		$subject = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . '] ' . sprintf( __( 'An invitation to join %s', 'buddypress' ), get_blog_option( BP_ROOT_BLOG, 'blogname' ) );

		$message = $data['invite_anyone_custom_message'];
		
		$accept_link = bp_get_root_domain() . '/register/accept-invitation/' . urlencode($email);
		
		$message .= sprintf( __( '

To accept this invitation, please visit %s', 'bp-invite-anyone' ), $accept_link );
		
		$to = apply_filters( 'invite_anyone_invitee_email', $email );
		$subject = apply_filters( 'invite_anyone_invitation_subject', $subject );
		$message = apply_filters( 'invite_anyone_invitation_message', $message, $accept_link );
		
		wp_mail( $to, $subject, $message );
			
		/* todo: isolate which email(s) cause problems, and send back to user */
	/*	if ( !invite_anyone_send_invitation( $bp->loggedin_user->id, $email, $message, $groups ) )
			$is_error = 1; */
		
		invite_anyone_record_invitation( $bp->loggedin_user->id, $email, $message, $groups );
		
		unset( $message, $to );
	}
	
	
	return true;
}



function invite_anyone_send_invitation( $inviter_id, $email, $message, $groups ) {
	global $bp;

}


function invite_anyone_validate_email( $user_email ) {
	
	//if ( email_exists($user_email) )
	//	return 'used';
	
	// The following checks can only be run on WPMU
	if ( function_exists( 'get_site_option' ) ) {
		if ( is_email_address_unsafe( $user_email ) )
			return 'unsafe';
		
		if ( !validate_email( $user_email ) )
			return 'invalid';
	
		$limited_email_domains = get_site_option( 'limited_email_domains' );
		
		if ( is_array( $limited_email_domains ) && empty( $limited_email_domains ) == false ) {
			$emaildomain = substr( $user_email, 1 + strpos( $user_email, '@' ) );
			if( in_array( $emaildomain, $limited_email_domains ) == false ) {
				return 'limited_domain';
			}
		}
	}
	


	return 'safe';
}

?>