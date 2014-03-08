
jQuery(document).ready(function(){
	jQuery('#emailSub-form').submit(function(){
		form=jQuery(this);
		form.fadeOut("Slow");

		
		//send message
		jQuery.post(form.attr("action"),{
			action: 'email_subscription',
			email:jQuery("#emailSub-email").val(),
            language:jQuery("#emailSub-language").val()
		},function(data){
			if(data.status==200){
				jQuery("#emailSub-output")
					.html(jQuery("#emailSub-success").val())
					.fadeIn("Slow");
			}
			else{
				jQuery("#emailSub-output")
				.html(jQuery("#emailSub-fail").val()+": "+data.message)
				.fadeIn("Slow");
			}
		},'json');
		
		return false;
	});

});
