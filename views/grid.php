<style>
#wp-guetzli-admin {
	
}
#wp-guetzli-admin dt{
	border-top: 1px solid #CCC;
	margin-right: 40px;
}

#wp-guetzli-admin input.code{
	font-family: 'Ubuntu Mono', monospace;
}

#guetzli-list pre{ margin-top: 0px; margin-bottom: 0px; }

</style>
<div id="wp-guetzli-admin" style="padding-left: 15px; padding-right: 15px;">
	<div class="welcome-panel" style="padding-bottom: 25px;">
		<div class="welcome-panel-content">
		
			<form method="post">
			
			<h2><?php _e('Guetzli', self::$i18n_domain );?></h2>
			<p class="description"></p>
			
			<div class="welcome-panel-column-container" >
				<div class="welcome-panel-column">
					<h3><?php _e('Configuration', self::$i18n_domain );?></h3>
				</div>
			
				<div class="welcome-panel-column">
				
					<h3><?php _e('Form Fields', self::$i18n_domain );?></h3>
					<p class="description"><?php _e("Add the Contact Form 7's fields you want to send to My Leads", self::$i18n_domain );?></p>
					
				</div>
			
				<div class="welcome-panel-column welcome-panel-last">
				
					<h3><?php _e('Pages', self::$i18n_domain );?></h3>
					<p class="description"><?php _e('Page Matching', self::$i18n_domain );?></p>
					
				</div>
				
			</div>
			<div class="welcome-panel-column-container">
			<?php 
			
			wp_nonce_field( self::$option_name.'_nonce' );
			submit_button();
			
			?>
			</div>
			</form>
			
		</div>
		
		<div class="welcome-panel-content">
		
			<table id="guetzli-list" class="fields">
				<thead>
					<tr>
						<th>ID</th>
						<th>Image</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach( $images as $attachment_ID => $img ): ?>
				<tr>
					<td><input type="checkbox" class="code" name="<?php echo self::$option_name;?>[attachment_ID][]" value="<?php echo $attachment_ID; ?>"></td>
					<td><pre><?php echo $img[ $attachment_ID ]['path']; ?></pre></td>
					<td>
						<a href="#" id="regenerate<?php echo $attachment_ID;?>" class="regenerate-one guetzli" data-attachmentid="<?php echo $attachment_ID;?>" />Regenerar</a>
						 
					</td>
				</tr>
				<?php endforeach; ?>
				
				</tbody>
			</table>
		
			<input type="button" value="Regenerar todas" class="regenerate-all guetzli" />
		</div>
		
	</div>
</div>

