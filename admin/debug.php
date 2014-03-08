<?php

function emailSub_admin_debug(){
	$emailDb=new EmailSubscriptionDatabase();

	?><div id='emailSub_admin'><h1>Debug</h1>
    <?php

    if(isset($_POST['do'])){
		//Test email
		if($_POST['do']=='testEmail'){
            $fromName=get_option('emailSub-from_name');
            $fromMail=get_option('emailSub-from_email');
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
		} elseif($_POST['do']=='forceSend') {
            $nextRun = wp_next_scheduled('execute_emailSub_sendEmails');
            if ($nextRun!==false) {
                //unschedule
                wp_unschedule_event($nextRun, 'execute_emailSub_sendEmails');
            }
            emailSub_sendEmails();
            ?>
            <div class="updated"><p>We just tried so send some emails by force.</p></div>
            <?php

        }

	}

?>
    <div class="emailSub-leftCol">
        <h2>Email spool info</h2>
        <p>We are about to send emails to <?php echo $emailDb->getSpoolCount(); ?> recipients.</p>

        <h2>Cron status</h2>
        <?php $nextRun = wp_next_scheduled('execute_emailSub_sendEmails');
        if ($nextRun===false) {
            ?><p>You have no scheduled cron tasks.</p><?php
        } else {
            $nextRunDate=new DateTime();
            $nextRunDate->setTimestamp($nextRun);
            $now=new DateTime();

            ?><p>The cron will run once again in <?php echo $now->diff($nextRunDate)->format('%r%i minutes and %s seconds'); ?></p>
        <?php
        }
    ?>

    </div>



    <div class="emailSub-rightCol">
        <form action="" method="POST" class="box">
            <input type="hidden" name="do" value="forceSend" />
            <h3>Send emails by force</h3>
            <p>If you suspect that something is wrong. Try send away some emails right away by clicking the button below.</p>

            <input type="Submit" value="Force send" />
        </form>

        <form action="" method="POST" id="emailSub_testEmailform" class="box">
            <input type="hidden" name="do" value="testEmail" />
            <h3>Test your server configuration</h3>
            <p>Test if your server configuration is allowing you to send emails.</p>
            <input type="Submit" value="Test" />
        </form>
    </div>
	
	</div> <!-- End: emailSub_admin -->
	
<?php
}
