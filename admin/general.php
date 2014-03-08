<?php

function emailSub_admin_general(){
	?><div id='emailSub_admin'><h1>Email Subscriptions</h1>
    <?php
	

	if(isset($_POST['do']) && $_POST['do']=='updateSettings'){
		if (! wp_verify_nonce($_POST['nonce'], 'ejamju03fxfg') ) die('Security check'); 
		
		$subject=$_POST['subject'];
		$body=$_POST['body'];
		$fromName=$_POST['from_name'];
		$fromMail=$_POST['from_email'];
        $promotion=$_POST['promotion'];

		if( $fromName=="" ||
			$fromMail=="" ||
			$subject=="" ||
			$body==""
		){//not every field has a value
			//print message
			?>
			<div class="error"><p>Every field must have a value!</p></div>
			<?php 
		}
		else{			
			update_option('emailSub-subject',$subject);
			update_option('emailSub-body',$body);
			update_option('emailSub-from_name',$fromName);
			update_option('emailSub-from_email',$fromMail);
			update_option('emailSub-promotion',$promotion);

					
			//print message
			?>
			<div class="updated"><p>Settings updated!</p></div>
			<?php 
		}
		
	}
	else{//if not a post request
	
		$subject=get_option('emailSub-subject');
		$body=get_option('emailSub-body');
		$fromName=get_option('emailSub-from_name');
		$fromMail=get_option('emailSub-from_email');
        $promotion=get_option('emailSub-promotion', true);
	}
    
    if(isset($_POST['do'])){
		//Test email
		if($_POST['do']=='testEmail'){
			$headers='From: "'.$fromName.'" <'.$fromMail.'>';
			if(wp_mail(
					get_option('admin_email'),
					"Test email",
					"Your server can send emails. \n Timestamp: ".date('Y-m-d H:i:s'),
					$headers
				)){//succeed
				//print message
				?>
					<div class="updated"><p>A test email was sent to <?php echo get_option('admin_email');?>.</p></div>
				<?php 
			}
			else{
				//print message
				?>
				<div class="error"><p>We failed to send a test email.</p></div>
				<?php 
			}
		}
		//remove
		elseif($_POST['do']=='removeSubscriber'){
			$emailDb=new EmailSubscriptionDatabase();
			$emailDb->removeEmail($_POST['email']);
		}
	}

	global $polylang;
    ?>
	<form action="" method="POST" id="emailSub_form">
		<input type="hidden" name="nonce" value="<?php echo wp_create_nonce("ejamju03fxfg"); ?>" />
		<input type="hidden" name="do" value="updateSettings" />
		<table>
			<tr><td colspan="2">
				<h3>Who are sending mails to the visitors?</h3>
				</td>
			</tr>
			<tr>
				<td>Name:</td>
				<td><input type="text" name="from_name" value="<?php echo stripslashes($fromName);?>" /></td>
			</tr>
			<tr>
				<td>Email:</td>
				<td><input type="text" name="from_email" value="<?php echo stripslashes($fromMail);?>" /></td>
			</tr>
			<tr><td colspan="2">
				<h3>What is the content of the mails?</h3>
				</td>
			</tr>
			<tr>
				<td>Subject:</td>
				<td><input type="text" name="subject" value="<?php echo stripslashes($subject);?>" /></td>
			</tr>
			<tr>
				<td>Message:</td>
				<td><textarea name="body"><?php echo stripslashes($body);?></textarea></td>
			</tr>
			<tr>
				<td>Hint:</td>
				<td><p>HTML is allowed. You may use the following variables: <i>%post_title%, %post_excerpt%, 
				%post_content%, %post_author%, %post_date%, %post_url%
				%site_url%, %unsubscribe_url%</i></p></td>
			</tr>
            <tr><td colspan="2">
                    <h3>Show Webfish some gratitude?</h3>
                </td>
            </tr>
            <tr>
                <td></td>
                <td><input type="checkbox" name="promotion" <?php if ($promotion) echo 'checked="checked"';?> />Of course!
                I want to show a link to Webfish.se as a thank you <br />for giving me this plugin for free.
                </td>
            </tr>
		</table>
		
		<input type="submit" value="Save" />
	</form>

	
	<form action="" method="POST" id="emailSub_testEmailform" class="box">
		<input type="hidden" name="do" value="testEmail" />
		<h3>Test your server configuration</h3>
		<p>Test if your server configuration is allowing you to send emails.</p>
		<p>Remember to save the settings (if you have done any changes) before you click 'Test'.</p>
		<input type="Submit" value="Test" />
	</form>

	<div id="emailSub_webfish" class="box">
		<h3>Webfish</h3>
		<div id="emailSub_webfishLogo"></div>
		<p>We made this plugin for you. To see some of our wordpress related work
		please follow this <a href="http://www.webfish.se/wp" target="_blank">link</a>. 
		There are both themes and plugins.</p>
		<span class="alignright">
			- <a href="http://www.webfish.se" target="_blank">Webfish</a>
		</span>
	</div>
	
	<div class="clear"></div>

	
	</div> <!-- End: emailSub_admin -->
	
<?php
}
