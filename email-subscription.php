<?php
/*
 * Plugin Name: Email subscription
 * Plugin URI: http://www.webfish.se/wp/plugins/email-subscription
 * Version: 1.2.2
 * Description: Let your visitors subscribe on you posts
 * Author: Tobias Nyholm
 * Author URI: http://www.tnyholm.se
 * License: GPLv3
 * Copyright: Tobias Nyholm 2010
 */
/*
Copyright (C) 2010 Tobias Nyholm

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

//add action for the cron
add_action('execute_emailSub_sendEmails', 'emailSub_sendEmails',10,0);

//Load admin stuff
include_once dirname(__FILE__)."/admin/init.php";
include_once dirname(__FILE__)."/EmailSubscriptionWidget.php";
include_once dirname(__FILE__)."/EmailSubscriptionDatabase.php";

/**
 * When a user fills in the form and press send this action will take place
 * Save the email in the database
 */
function emailSub_ajaxCallback() {
	
	$email = $_POST['email'];
    $language = isset($_POST['language'])?$_POST['language']:'';

	//validate email
	if(!is_email($email)){
		die(json_encode(array(
				'status'=>400,
				'message'=>'Email is not valid',
		)));
	}
	
	$emailDB=new EmailSubscriptionDatabase();
	if(!$emailDB->addEmail($email, $language)){
		die(json_encode(array(
				'status'=>500,
		)));
	}
	
	die(json_encode(array(
		'status'=>200,
	)));
}
//register callback
add_action('wp_ajax_email_subscription', 'emailSub_ajaxCallback');
add_action('wp_ajax_nopriv_email_subscription', 'emailSub_ajaxCallback');

/**
 * Send some emails
 */
function emailSub_sendEmails(){
	$emailsToSend=0;
	$emailDb=new EmailSubscriptionDatabase();
    //TODO make this 5 configurable
	$emails=$emailDb->getEmailsToSendSubscriptionMails($emailsToSend, 5);
	
	//get some values from settings
    global $polylang;
    $org_subject=stripslashes(get_option('emailSub-subject'));
    $org_body=stripslashes(get_option('emailSub-body'));
    $fromName=stripslashes(get_option('emailSub-from_name'));
    $fromMail=stripslashes(get_option('emailSub-from_email'));

	
	
	$body=array();
	$subject=array();
	foreach($emails as $email){

        if(isset($polylang)) {
            if(!isset($mos[$email['language']])) {
                $language = $polylang->model->get_language($email['language']); // import_from_db expects a language object
                $mos[$email['language']] = new PLL_MO();
                $mos[$email['language']]->import_from_db($language); // import all translations in $language
            }

            // then you can translated any registered string with:
            $org_subject = $mos[$email['language']]->translate("Subject");
            $org_body = $mos[$email['language']]->translate("Body");
            $fromName = $mos[$email['language']]->translate("From name");
            $fromMail = $mos[$email['language']]->translate("From mail");
        }
        
        
        //prepare headers to send in HTML
        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
        $headers .='From: "'.$fromName.'" <'.$fromMail.'>';
        
		$to=$email['email'];
		$post_id=$email['post_id'];
		
		//create post specific body and subject if not already created
		if(!isset($body[$post_id]) || !isset($subject[$post_id])){
			$post=get_post($post_id);
			
			//load the excerpt
			$post->post_excerpt=emailSub_getExcerpt($post);
			$body[$post_id]=emailSub_prepareString($org_body, $post);
			$subject[$post_id]='=?UTF8?B?'.base64_encode(emailSub_prepareString($org_subject, $post)).'?=';
		}
		
		//add unsubscribe url
		$code=substr(md5('little-more'.$to.'secure'),3,16);
		$user_body=str_replace(
				'%unsubscribe_url%',
				home_url('webfish-email-subscription/unsubscribe-'.$code.'/'.$to),
				$body[$post_id]
			);

		//send mail
		wp_mail($to,$subject[$post_id],$user_body,$headers);
	}

	//send more email asap
	if($emailsToSend>0)
		emailSub_tellCron(1);
}

/**
 * Prepares a string with data from post
 * 
 * @param string $str
 * @param Post $post
 */
function emailSub_prepareString($str, &$post){
	
	$userData = get_userdata($post->post_author);
	$username = $userData->user_login;
	
	$replacements=array(
		'%post_title%'=>$post->post_title,	
		'%post_excerpt%'=>$post->post_excerpt,
		'%post_content%'=>$post->post_content,
		'%post_author%'=>$username,  // FIXED BY MARTY 7/24/2013
		'%post_date%'=>$post->post_date,
		'%post_url%'=>get_permalink($post->ID),
		'%site_url%'=>get_option('siteurl'),
		"\n"=>'<br>',//we send is as html
	);
	
	$str=str_replace(
				array_keys($replacements),
				array_values($replacements),
				$str
		);

	return $str;
}

/**
 * Tell cron to run emailSub_sendEmails() some minutes from now
 * 
 * @param int $minutesFromNow
 */
function emailSub_tellCron($minutesFromNow){
	$time=time()+$minutesFromNow*60;
	wp_schedule_single_event($time,"execute_emailSub_sendEmails");
		
}

/**
 * This function is executed when a post is published
 */
function emailSub_publishPost($post){
	if($post->post_type!='post'){
		return; //we only send emails when posts are updated
	}
	
	$postId=$post->ID;
	//check if the post has been processed before
	$processedPosts=get_option('emailSub-posts_processed',array());
	if(in_array($postId,$processedPosts))
		return;//do nothing
	
	//we dont want to process this post again
	$processedPosts[]=$postId;
	update_option('emailSub-posts_processed', $processedPosts);

    global $polylang;
    $language = isset($polylang)?$polylang->get_post_language($postId)->slug:'';

	//move to all emails to spool
	$emailDb=new EmailSubscriptionDatabase();
	$emailDb->addAllToSpool($postId, $language);
	
	//tell the cron to run in 15 minutes
    //TODO make sure this is configurable
	emailSub_tellCron(15);
}
add_action('auto-draft_to_publish', 'emailSub_publishPost',100);
add_action('draft_to_publish', 'emailSub_publishPost',100);
add_action('future_to_publish', 'emailSub_publishPost',100);


/**
 * Return the post excerpt
 * 
 * @param unknown_type $post
 */
function emailSub_getExcerpt( $post ) {
	if ( post_password_required($post) ) {
		$output = __('There is no excerpt because this is a protected post.');
		return $output;
	}

    if (!empty($post->post_excerpt)) {
        return $post->post_excerpt;
    }
	
	$output=$post->post_content;
	$output = strip_shortcodes( $output );
	
	$output = apply_filters('the_content', $output);
	$output = str_replace(']]>', ']]&gt;', $output);
	$excerpt_length = apply_filters('excerpt_length', 55);
	$excerpt_more = apply_filters('excerpt_more', ' [...]');
	$output = wp_trim_words( $output, $excerpt_length, $excerpt_more );
	
	return apply_filters('get_the_excerpt', $output);
}

/**
 * Enqueue JavaScripts/CSS
 */
function emailSub_assets() {
	//register css
	if(@file_exists(TEMPLATEPATH.'/email-subscription.css')) {
		wp_register_style('email-subscription_css', get_stylesheet_directory_uri().'/email-subscription.css', false, '0.50', 'all');
	} else {
		wp_register_style('email-subscription_css', plugins_url('email-subscription/assets/email-subscription.css'), false, '0.50', 'all');
	}
	
	//register scripts
	wp_register_script('email-subscription_js', plugins_url('email-subscription/assets/email-subscription.js'),array('jquery'),'1.0',true);
	
	//queue styles and scripts
	wp_enqueue_style('email-subscription_css');
	wp_enqueue_script('email-subscription_js');
}
add_action('wp_enqueue_scripts', 'emailSub_assets');


/**
 * Install the MySQL-tables
 */
function emailSub_install() {
	//db version
	$currentVersion = 2;
	$dbVersion=get_option("emailSub_db_version", false);
	
	if ($dbVersion==false) {
		add_option("emailSub_db_version", $currentVersion);
	} elseif ($dbVersion==$currentVersion) {
		//we do not need to update
		return;
	}

	global $wpdb;

	//add some options
	add_option('emailSub-subject','New post on '.get_option('blogname'));
	add_option('emailSub-body',"There is a new post at %site_url%. You can read it here: \n".'<a href="%post_url%">%post_title%</a>'.
			"\n\n\n\n To unsubscribe from future mail, follow this link: \n".'<a href="%unsubscribe_url%">%unsubscribe_url%</a>');
	add_option('emailSub-from_name','Admin at '.get_option('blogname'));
	add_option('emailSub-from_email',get_option('admin_email'));
	add_option('emailSub-promotion',true);
	add_option("emailSub-posts_processed", array());
	
	/*
	 * Check if tables exists
	*/
    $table_name = $wpdb->prefix . "emailSub_addresses";
    $sql[] = "CREATE TABLE $table_name (
      email_id INTEGER NOT NULL AUTO_INCREMENT,
      email VARCHAR(255) NOT NULL,
      language VARCHAR(255) NOT NULL,
      PRIMARY KEY (email_id));";


    $table_name = $wpdb->prefix . "emailSub_spool";
    $sql[] = "CREATE TABLE $table_name (
    spool_id INTEGER NOT NULL AUTO_INCREMENT,
    email_id VARCHAR(255) NOT NULL,
    post_id INTEGER NOT NULL,
    PRIMARY KEY (spool_id));";


    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    update_option("emailSub_db_version", $currentVersion);

    emailSub_initPolylang();
}
add_action('init','emailSub_install',1);

/**
 * Install polylang
 */
function emailSub_initPolylang() {
    global $polylang;
    if(isset($polylang)) {
        pll_register_string("From name", get_option('emailSub-from_name'), "Email Subscription");
        pll_register_string("Subject", get_option('emailSub-subject'), "Email Subscription");
        pll_register_string("Body", get_option('emailSub-body'), "Email Subscription", true);
    }
}


/**
 * We need to parse the query to see if someone tries to unsubscribe
 * 
 * @param unknown_type $q
 */
function emailSub_queryParser ($query) {

	if(isset($query->query_vars['emailSub_unsub'])){
		$code=$query->query_vars['emailSub_unsub'];
		$email=$query->query_vars['emailSub_email'];
		
		if($code==substr(md5('little-more'.$email.'secure'),3,16)){
			$emailDb=new EmailSubscriptionDatabase();
			$emailDb->removeEmail($email);
		}
		
		wp_redirect( home_url().'?unsubscribed' ); 
		exit;
	}

	return $query;

}
add_filter('parse_query', 'emailSub_queryParser');


/**
 * Register a url to let users unsubscribe
 */
function emailSub_registerUrlRules($rules) {

    add_rewrite_tag('%emailSub_unsub%', '([^&]+)');
    add_rewrite_tag('%emailSub_email%', '([^&]+)');

    $newrules = array();
    $newrules['webfish-email-subscription/unsubscribe-([^/]+)/([^/]+)/?$'] = 'index.php?page=2&emailSub_unsub=$matches[1]&emailSub_email=$matches[2]';

    $return= $newrules + $rules;

    return $return;
}
add_filter('rewrite_rules_array', 'emailSub_registerUrlRules');


/**
 * Flush rules if they are not flushed
 */
function emailSub_flushRewriteRules(){
    $rules = get_option( 'rewrite_rules' );

    if ( ! isset( $rules['webfish-email-subscription/unsubscribe-([^/]+)/([^/]+)/?$'] ) ) {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }
}
add_action( 'wp_loaded','emailSub_flushRewriteRules' );

/**
 * Adding the id var so that WP recognizes it
 *
 * @param $vars
 *
 * @return mixed
 */
function emailSub_registerUrlVars( $vars )
{
    array_push($vars, 'emailSub_unsub');
    array_push($vars, 'emailSub_email');
    return $vars;
}
add_filter('query_vars', 'emailSub_registerUrlVars');

