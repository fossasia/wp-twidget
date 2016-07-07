jQuery(function() {
	if(jQuery("#loklak_api").prop('checked')){
		console.log("init");
		twidget_update_twitter_auth(true);
	}

    jQuery("#loklak_api").live('change', function() {
    	if(jQuery(this).is(':checked')){
	    	twidget_update_twitter_auth(true);
	    }
	    else {
	    	twidget_update_twitter_auth(false);
	    }
	});

	function twidget_update_twitter_auth(arg) {
		jQuery("#twitget_api").prop('disabled', arg);
	    jQuery("#twitget_consumer_key").prop('disabled', arg);
		jQuery("#twitget_consumer_secret").prop('disabled', arg);
		jQuery("#twitget_user_token").prop('disabled', arg);
		jQuery("#twitget_user_secret").prop('disabled', arg);
	}
});
