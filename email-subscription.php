<?php
/*
 * Plugin Name: Email subscription
 * Plugin URI: http://www.webfish.se/wp/plugins/email-subscription
 * Version: 1.1.10
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

//Load admin
include_once dirname(__FILE__)."/admin.php";

/**
 * When a user fills in the form and press send this action will take place
 * Save the email in the database
 */
function emailSub_ajaxCallback() {
	
	$email = $_POST['email'];
    global $polylang;
    if(isset($polylang)) {
        $language = $_POST['language'];
    } else {
        $language = "";
    }

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
	$emails=$emailDb->getEmailsToSendSubscriptionMails($emailsToSend,5);
	
	//get some values from settings
    global $polylang;
    if(!isset($polylang)) {
        $org_subject=get_option('emailSub-subject');
        $org_body=get_option('emailSub-body');
        $fromName=get_option('emailSub-from_name');
        $fromMail=get_option('emailSub-from_email');
    }
	
	
	$body=array();
	$subject=array();
    $languages=array();
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
		
		//create post specific body and subject if not allready created
		if(!isset($body[$post_id]) || !isset($subject[$post_id])){
			$post=get_post($post_id);
			
			//load the excerpt
			$post->post_excerpt=emailSub_getExcerpt($post);
			$body[$post_id]=emailSub_prepareString($org_body, $post);
			$subject[$post_id]='=?UTF8?B?'.base64_encode(emailSub_prepareString($org_subject, $post)).'?=';
		}
		
		//add unsubsribe url
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
	
	//move to all emails to spool
	$emailDb=new EmailSubscriptionDatabase();
    global $polylang;
    if(isset($polylang)) {
        $language = $polylang->get_post_language($postId)->slug;
    } else {
        $language = "";
    }
	$emailDb->addAllToSpool($postId, $language);
	
	//tell the cron to run in 15 minutes
	emailSub_tellCron(15);
}
add_action('auto-draft_to_publish', 'emailSub_publishPost',100);
add_action('draft_to_publish', 'emailSub_publishPost',100);
add_action('future_to_publish', 'emailSub_publishPost',100);


/**
 * Return the post exceprt
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

class EmailSubscriptionWidget extends WP_Widget {
  private $defaults = array(
                          'title' => 'Subscribe to my posts',
                          'success_msg'=>'Thank you for subscribing',
                          'fail_msg' => 'Some unexpected error occurred',
  						'submit_button'=>'Subscribe',
                        );  
  
  public function __construct() {
    $options = array(
                  'classname' => 'EmailSubscriptionWidget',
                  'description' => __('Displays subpages for the current page.','email-subscription')
                );
    $this->WP_Widget('EmailSubscriptionWidget', 'Email Subscription Widget', $options);             
    add_filter( 'plugin_action_links', array(&$this, 'plugin_action_links'), 10, 2 );
  }
  
  public function widget($args, $instance) {
    extract($args, EXTR_SKIP);
   

    /*
     * Print widget
     */
    echo $before_widget;
     
    if(strlen($instance['title']) > 0){
    	echo $before_title.$instance['title'].$after_title;
    }
    ?>
    <ul id='emailSub-widget'>
    	<div id="emailSub-output" style="display:none;"></div>
    	<form id="emailSub-form" action="<?php echo site_url('wp-admin/admin-ajax.php')?>">
    		<input type="hidden" name="success_msg" id="emailSub-success" value="<?php echo $instance['success_msg'];?>" />
    		<input type="hidden" name="fail_msg" id="emailSub-fail" value="<?php echo $instance['fail_msg'];?>" />
            <?php
            global $polylang;
            if(isset($polylang)) { ?>
                <input type="hidden" name="language" id="emailSub-language" value="<?php echo pll_current_language();?>" />
            <?php } ?>
			<input type="text" name="email" id="emailSub-email" placeholder="Email:" />
			<br />			
			<input type="submit" class="submit" value="<?php echo $instance['submit_button'];?>" />
    	
    	</form>
    	
    
    
    </ul>
    <?php 
    echo $after_widget;
    
    
    
  }
  

  

 public function update($new_instance, $old_instance) {
    $instance = $old_instance;
	    
    return $new_instance;
  }
  
  public function form($instance) {
    $instance = wp_parse_args( (array) $instance, $this->defaults);

    $title = $instance['title'];
    $success = $instance['success_msg'];
    $fail = $instance['fail_msg'];
    $submit = $instance['submit_button'];
?>


  <p>
    <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e("Title:",'email-subscription');?></label><br />
      <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
  </p>
  <p>
    <label for="<?php echo $this->get_field_id('success_msg'); ?>"><?php _e("Message if succeed:",'email-subscription');?></label><br />
      <input class="widefat" id="<?php echo $this->get_field_id('success_msg'); ?>" name="<?php echo $this->get_field_name('success_msg'); ?>" type="text" value="<?php echo esc_attr($success); ?>" />
  </p>
  <p>
    <label for="<?php echo $this->get_field_id('fail_msg'); ?>"><?php _e("Message if fail:",'email-subscription');?></label><br />
      <input class="widefat" id="<?php echo $this->get_field_id('fail_msg'); ?>" name="<?php echo $this->get_field_name('fail_msg'); ?>" type="text" value="<?php echo esc_attr($fail); ?>" />
  </p>
  <p>
    <label for="<?php echo $this->get_field_id('submit_button'); ?>"><?php _e("Text on submit button:",'email-subscription');?></label><br />
      <input class="widefat" id="<?php echo $this->get_field_id('submit_button'); ?>" name="<?php echo $this->get_field_name('submit_button'); ?>" type="text" value="<?php echo esc_attr($submit); ?>" />
  </p>


<?php
  }

  public function plugin_action_links( $links, $file ) {
    static $this_plugin;
    
    if( empty($this_plugin) )
      $this_plugin = plugin_basename(__FILE__);

    if ( $file == $this_plugin )
      $links[] = '<a href="' . admin_url( 'widgets.php' ) . '">Widgets</a>';
    
    return $links;
  }
  
  
}

add_action('widgets_init', create_function('', 'return register_widget("EmailSubscriptionWidget");'));



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
function emailSub_install(){
	global $wpdb;

	//db version
	$emailSub_db_version = "1.1";
	
	//add some options
	add_option('emailSub-subject','New post on '.get_option('blogname'));
	add_option('emailSub-body',"There is a new post at %site_url%. You can read it here: \n%post_url% ".
			"\n\n\n\n To unsubscribe from future mail, follow this link: %unsubscribe_url% ");
	add_option('emailSub-from_name','Admin at '.get_option('blogname'));
	add_option('emailSub-from_email',get_option('admin_email'));
	add_option("emailSub-posts_processed", array());
	
	/*
	 * Check if tabels exists
	*/
	$table_name = $wpdb->prefix . "emailSub_addresses";
	$sql="";
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$sql .= "CREATE TABLE " . $table_name . " (
		  email_id INTEGER  NOT NULL AUTO_INCREMENT,
		  email VARCHAR(255)  NOT NULL,
          language VARCHAR(255)  NOT NULL,
		  PRIMARY KEY (email_id)
		);
		";
	}
	
	$table_name = $wpdb->prefix . "emailSub_spool";
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$sql .= "CREATE TABLE " . $table_name . " (
		spool_id INTEGER  NOT NULL AUTO_INCREMENT,
		email_id VARCHAR(255)  NOT NULL,
		post_id INTEGER  NOT NULL,
		PRIMARY KEY (spool_id)
		);
		";
	}

	/*
	 * if there was some sql to run.
	*/
	if($sql!=""){
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		add_option("emailSub_db_version", $emailSub_db_version);
	}


}
register_activation_hook(__FILE__,'emailSub_install');

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
function emailSub_registerUrl(){
	add_rewrite_rule('webfish-email-subscription/unsubscribe-(.*)/(.*)/?$',
			'index.php?emailSub_unsub=$matches[1]&emailSub_email=$matches[2]',"top");
	add_rewrite_tag('%emailSub_unsub%', '.*');
	add_rewrite_tag('%emailSub_email%', '.*');
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
	
}
add_action('init', 'emailSub_registerUrl');


/**
 * A database class to simplify accessing wpdb
 * 
 * @author tobias
 *
 */
class EmailSubscriptionDatabase{
	
	
	private $address_table;
	private $spool_table;
	
	public function __construct(){
		global $wpdb;
		
		$this->address_table = $wpdb->prefix . "emailSub_addresses";
		$this->spool_table = $wpdb->prefix . "emailSub_spool";
	}
	
	/**
	 * Returns true if email was added. It does also returns true if email already is in the database
	 * 
	 * @param unknown_type $email
	 */
	public function addEmail($email, $language = ""){
		global $wpdb;
		
		$email=trim($email);
		
		//check if exists
		$res=$wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->address_table} WHERE email='%s' AND language ='%s'"
			,array($email, $language)
		));
		
		if($res)
			return true;//Allready exists
		
		//insert
		$wpdb->query(
			$wpdb->prepare("INSERT INTO {$this->address_table} SET "
				."email='%s', language='%s' "
			,array($email, $language)
		));
		
		return true;
	}
	
	/**
	 * This fancy long named function does check for email addresses to send subsciption email to.
	 * 
	 * This function does also remove the email addresses from the email spool.
	 * 
	 * Max $limit emails are returned. $count is the total number of emails that havn't been mailed, 
	 * excluding the ones returned now.  
	 * 
	 * @param int $count
	 * @param int $limit
	 */
	public function getEmailsToSendSubscriptionMails(&$count, $limit=2){
		global $wpdb;
		
		$results=$wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.email, a.language, s.post_id, s.spool_id FROM {$this->spool_table} s, {$this->address_table} a WHERE a.email_id=s.email_id ORDER BY spool_id LIMIT %d"
			,array($limit))
		,ARRAY_A);
		
		if(!$results){
			$count=0;
			return array();
		}
			
		$spoolIds=array();
		foreach($results as $idx=>$row){
			//save id
			$spoolIds[]=$row['spool_id'];
			
			//remove id from results
			unset($results[$idx]['spool_id']);
		}
		
		
		//remove these emails from spool
		$wpdb->query(
				"DELETE FROM {$this->spool_table} "
				."WHERE spool_id IN (".implode(',',$spoolIds).")"
			);
		
		
		$count= $wpdb->get_var("SELECT count(email_id) FROM {$this->spool_table} ");
		
		return $results;
		
	}
	
	/**
	 * Returns all emails
	 */
	public function getAllEmails(){
		global $wpdb;
		
		return $wpdb->get_results("SELECT * FROM {$this->address_table} ORDER BY language");
	}
	
	/**
	 * Copy all addresses from db to the email spool
	 * 
	 * @param unknown_type $postId
	 */
	public function addAllToSpool($postId, $language = ""){
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$this->spool_table} (email_id, post_id) 
					SELECT a.email_id, '%d' FROM {$this->address_table} a WHERE language='%s'"
			,array($postId, $language))
		);
	}
	
	/**
	 * Removes an emails from spool and address table
	 * 
	 * @param string $email
	 */
	public function removeEmail($email){
		global $wpdb;

		//get id
		$email_id= $wpdb->get_var(
			$wpdb->prepare(
				"SELECT email_id FROM {$this->address_table} WHERE email='%s' "
				,array($email)));
		
		//remove from address table
		$wpdb->query(
			$wpdb->prepare("DELETE FROM {$this->address_table} "
				."WHERE email_id='%d' "
				,array($email_id)));
		
		//remove from spool table
		$wpdb->query(
				$wpdb->prepare("DELETE FROM {$this->spool_table} "
				."WHERE email_id='%d' "
				,array($email_id)));

	}
	
	
	
}
