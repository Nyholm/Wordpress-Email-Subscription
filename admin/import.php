<?php

function emailSub_admin_import(){
	$emailDb=new EmailSubscriptionDatabase();
	?><div id="emailSub_admin" class="emailSub-import">
    <h1>Import</h1>
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
        <p>Paste our emails here, one email per line</p>
		<textarea name="emails"><?php echo implode("\n", $emails); ?></textarea>
        <input type="submit" value="Import" />
	</form>
	
	</div> <!-- End: emailSub_admin -->
	
<?php
}
