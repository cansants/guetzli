jQuery(document).ready(function($) {
	/*
	$('#wp-guetzli-admin').on('click', '.regenerate-all.guetzli', function(e){
		
		var data = {
				action: 'regenerate_image',
		}
		
		$.ajax({
			  type: "POST",
			  url: wp_Guetzli.ajax_url,
			  data: data,
			  success: function(response,status, xhr){
				console.log(response);
				console.log(status);
				console.log(xhr);
			  },
			  error: function(XMLHttpRequest, textStatus, errorThrown) {
				  console.log(XMLHttpRequest);
				  console.log(textStatus);
				  console.log(errorThrown);
			  }
		});
		  
	});
	*/
	$('#wp-guetzli-admin').on('click', '.regenerate-one.guetzli', function(e){
		
		e.preventDefault();
		var attachment_id = $(this).data('attachmentid');
		
		console.log( attachment_id );
		
		var data = {
				'action': 'regenerate_image',
				'id': attachment_id,
		}
		
		$.ajax({
			  type: "POST",
			  url: wp_Guetzli.ajax_url,
			  data: data,
			  success: function(response,status){
				console.log(response);
				console.log(status);
				
			  },
			  error: function(XMLHttpRequest, textStatus, errorThrown) {
				  console.log(XMLHttpRequest);
				  console.log(textStatus);
				  console.log(errorThrown);
			  }
		});
		
	});
	
	
	/*
	var row = '<tr> \
					<td><input type="text" class="code" name="'+misLeadsCF7.option_name+'[cf7_fields][]"></td> \
					<td>= <input type="text" class="code" name="'+misLeadsCF7.option_name+'[misleads_fields][]"></td> \
				</tr>';
	
	$('#misleads-cf7-admin').on('click', '.misleads-addfield', function(e){
		console.log('duplicar');
		$('#misleads_tablefields tr:last').after( row );
		
	});
	*/
	
});