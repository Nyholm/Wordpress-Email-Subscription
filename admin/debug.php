<?php

/**
 * Send latest post to admin
 */
function emailSub_sendLatestPostToAdmin() {
    $posts=wp_get_recent_posts(array('numberposts' => 1,  'post_status' => 'publish'));
    if (empty($posts)) {
        ?>
        <div class="error"><p>You don't have any published posts.</p></div>
        <?php
    }
    $post=array_pop($posts);

    $emailDb=new EmailSubscriptionDatabase();
    $emailDb->addToSpool(get_option('admin_email'), $post['ID']);
    emailSub_sendEmails();
}
/**
 * Send a test email
 */
function emailSub_sendTestEmail() {
    $fromName=get_option('emailSub-from_name');
    $fromMail=get_option('emailSub-from_email');
    $headers='From: "'.$fromName.'" <'.$fromMail.'>';

    if (wp_mail(
        get_option('admin_email'),
        "Test email",
        "Your server can send emails. \n Timestamp: " . date('Y-m-d H:i:s'),
        $headers
    )
    ) { //succeed
        //print message
        ?>
        <div class="updated"><p>A test email was sent to <?php echo get_option('admin_email'); ?>.</p></div>
    <?php
    } else {
        //print message
        ?>
        <div class="error"><p>We failed to send a test email.</p></div>
    <?php
    }
}

function emailSub_admin_debug() {
	$emailDb=new EmailSubscriptionDatabase();

	?><div id='emailSub_admin'><h1>Debug</h1>
    <?php

    if(isset($_POST['do'])){
		//Test email
		if($_POST['do']=='testEmail'){

            if ($_POST['test-email']) {
                emailSub_sendTestEmail();
            } elseif ($_POST['latest-post']) {
                emailSub_sendLatestPostToAdmin();
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
        <p>After you publish a new post we put all your subscribers in the emails spool. Then we send emails to the ones
        in the spool until the spool is empty. </p>
        <p>The emails spool contains <?php echo $emailDb->getSpoolCount(); ?> recipients.</p>

        <h2>Cron status</h2>
        <?php $nextRun = wp_next_scheduled('execute_emailSub_sendEmails');
        if ($nextRun===false) {
            ?><p>You have no scheduled cron tasks.</p><?php
        } else {
            $nextRunDate=new DateTime();
            $nextRunDate->setTimestamp($nextRun);
            $now=new DateTime();

            if ($now->diff($nextRunDate)->format('%r%i') < -10) {
                ?><p style="color:red;">You have some problems with the wordpress cron. </p><?php
            } else {
                ?><p>The cron will run once again in <?php echo $now->diff($nextRunDate)->format('%r%i minutes and %s seconds'); ?></p><?php
            }
        }
    ?>

        <hr />
        <h2>Common issues</h2>
        <h3>Cron error</h3>
        <p>If the emails is not sent automatically there is probably something wrong with the Wordpress cron. This is a
        common bug in Wordpress 3.7.1 and later. To fix this you may try one of the following:</p>
        <ol>
            <li>Try to force to send emails by clicking at the button to the right</li>
            <li>Make sure to remove <code>define('DISABLE_WP_CRON', true)</code> from your wp-config.php</li>
            <li>Add the <code>define('ALTERNATE_WP_CRON', true);</code> to the wp-config.php</li>
            <li>Disable W3 total cache plugin as it might cache the cron requests. </li>
            <li>Submit a bug report to Wordpress</li>
        </ol>
        <h3>Test email fails</h3>
        <p>If your test email fails then is something wrong with your server settings. Talk to your server administrator and
        he/she will figure it out. You might want to try using the WP SMTP plugin by BoLiQuan to make sure that you may send emails.</p>
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
            <input type="Submit" name="test-email" value="Send you test email" />
            <input type="Submit" name="latest-post" value="Send you the latest post" />
        </form>


    </div>
	
	</div> <!-- End: emailSub_admin -->
	
<?php
}
