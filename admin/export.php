<?php

function emailSub_admin_export(){
	$emailDb=new EmailSubscriptionDatabase();

    if(isset($_POST['do'])){
        //remove
        if($_POST['do']=='removeSubscriber'){
            $emailDb->removeEmail($_POST['email']);
        }
    }

    //get all subscribers
    $subscriptions=$emailDb->getAllEmails();

	?><div id='emailSub_admin'><h1>Export</h1>

	<h2>Current subscribers</h2>
	<table id="emailSub_subscrptions">
        <tr><td><b>E-mail address</b></td>
        <?php
            if(isset($polylang)) {
                echo "<td><b>Language</b></td>";
            }
            ?>
        <td><b>Remove</b></td></tr>
		<?php foreach ($subscriptions as $i=>$subscriber): ?>
			<tr>
            <td><?php echo $subscriber->email; ?></td>
            <?php
            if(isset($polylang)) {
                echo "<td>".$subscriber->language."</td>";
            }
            ?>
			<td><form action="" method="POST" id="email_sub_<?php echo $i; ?>">
				<input type="hidden" name="nonce" value="<?php echo wp_create_nonce("ejamju03fxfg"); ?>" />
				<input type="hidden" name="do" value="removeSubscriber" />
				<input type="hidden" name="email" value="<?php echo $subscriber->email;?>" />
				 <a href="javascript:void(0)" onclick="if (confirm('Are you sure to remove this email?')) document.getElementById('email_sub_<?php echo $i; ?>').submit()";>Remove</a>
			</form></td>
			</tr>
		<?php endforeach; ?>
	</table>
	
	</div> <!-- End: emailSub_admin -->
	
<?php
}
