<?php

function emailSub_admin_import() {
	$emailDb=new EmailSubscriptionDatabase();
	?><div id="emailSub_admin" class="emailSub-import">
    <h1>Import</h1>
    <p>If you have a list of emails that you want to be subscribers you may paste them here. </p>
    <?php

    $emails=array();
    $addedCount=0;
    if(isset($_POST['do'])){
		//Test email
		if($_POST['do']=='import'){
            $emails=explode("\n", $_POST['emails']);

            foreach ($emails as $i=>$email) {
                $email=trim($email);
                if(is_email($email)){
                    $emailDb->addEmail($email);
                    unset($emails[$i]);
                    $addedCount++;
                }
            }
            //assert: $emails is an array with malformed emails
            ?><div class="updated">We successfully added <?php echo $addedCount ?> emails.</div><?php
		}
	}
    ?>
	<form action="" method="POST" id="emailSub_form">
		<input type="hidden" name="nonce" value="<?php echo wp_create_nonce("ejamju03fxfg"); ?>" />
		<input type="hidden" name="do" value="import" />
        <p>Paste your emails here, one email per line</p>
		<textarea name="emails"><?php echo implode("\n", $emails); ?></textarea>
        <br />
        <input type="submit" value="Import" />
        <p>When clicking on the import button it may take a while for the page to refresh. The time it takes is depending
        on the amount on emails you are importing. When the import is finished and you still see some emails in the import box,
        it means that those emails were malformed. </p>
	</form>
	
	</div> <!-- End: emailSub_admin -->
	
<?php
}
