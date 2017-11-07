<?php 

if (!defined('ABSPATH')) {
	die();
}

// Bail if WP-CLI is not present
if ( !defined( 'WP_CLI' ) ) return;

WP_CLI::add_command( 'guetzli', 'WP_CLI_Guetzli' );

class WP_CLI_Guetzli extends WP_CLI_Command{

	private static $cols = array(
		'id_blog', 'attachment_id', 'after', 'before', 'saving' 
	);
	
	// Ruta del binario
	//private static $bin = 'guetzli';
	private static $bin = GUETZLI__PLUGIN_DIR.'lib/guetzli';
	
	// Calidad de la compresion por defecto
	private static $backup_extension = '.bak';
	private static $quality = 90;
	
	private static $result;
	private static $sites;
	
	private static $is_compressed = false;
	
	/**
	 * Compress a image using google's guetzli
	 * 
	 * ## OPTIONS
	 *  
	 * [--id=<attachment_id>]
	 * : The id_attachment of the image to compress.
	 *   
	 * [--idblog=<idblog>]
	 * : The idBlog.
	 * 
	 * [--debug]
	 * : Show verbose info about the process.
	 * ---
	 * default: 1
	 *  
	 */
	function image( $args, $assoc_args ) {
		
		// Attachment ID, Validacion del parametro
		if ( isset( $args[0] ) && ( $args[0] <= 0 ) ) {
			WP_CLI::error( sprintf( __( '%s is not a valid attachment ID.', WP_Guetzli::$i18n_domain ), $args[0] ) );
		}elseif( isset( $assoc_args['id'] ) && ( $assoc_args['id'] <= 0 ) ){
			WP_CLI::error( sprintf( __( '%s is not a valid attachment ID.', WP_Guetzli::$i18n_domain ), $assoc_args['id'] ) );
		}
		
		if ( isset( $args[0] ) && !empty( $args[0] ) ){
			$attachment_id = $args[0];
		}elseif( isset( $assoc_args['id'] ) && !empty( $assoc_args['id'] ) ){
			$attachment_id = $assoc_args['id'];
		}else{
			WP_CLI::error( __( 'Not a valid attachment ID.', WP_Guetzli::$i18n_domain ) );
		}


		// Si es Multisite preguntamos
		if( is_multisite() ){
		
			if( isset( $assoc_args['idblog'] ) && ( $assoc_args['idblog'] <= 0 ) ){
				WP_CLI::error( sprintf( __( '%s is not a valid blog ID.', WP_Guetzli::$i18n_domain ), $assoc_args['idblog'] ) );
				
			}elseif( !isset( $assoc_args['idblog'] ) || empty( $assoc_args['idblog'] )){
				
				$blog_id = get_current_blog_id();
				
				WP_CLI::warning( __('Working on a Wordpress Multisite instance') );
				WP_CLI::confirm( sprintf( __( 'Do you want to proceed with idblog=%d?', WP_Guetzli::$i18n_domain ), $blog_id ) );
				
			}else{
				$blog_id = (int) $assoc_args['idblog'];
			}
		
		}else{
			$blog_id = get_current_blog_id();
		}
		
		
		// Iniciamos
		if( is_multisite() && $blog_id && $blog_id != get_current_blog_id() ){
			switch_to_blog( $blog_id );
			WP_CLI::debug( sprintf( __( 'Switched to idblog #%d', WP_Guetzli::$i18n_domain ), $blog_id ), 'guetzli' );
		}
		
		// Comprimimos imagen
		self::compress( $attachment_id );
		
		WP_CLI\Utils\format_items( 'table', self::$result, self::$cols );
		
		if( is_multisite() && $blog_id && $blog_id == get_current_blog_id() ){
			restore_current_blog();
			WP_CLI::debug(  __( 'Switched back', WP_Guetzli::$i18n_domain ) , 'guetzli' );
			
		}
		
		$debug = json_encode( $assoc_args );
		WP_CLI::debug( 'Debug: ' . $debug, 'guetzli' );

		
	}
	
	public static function microtime_float(){
		list($useg, $seg) = explode(" ", microtime());
		return ((float)$useg + (float)$seg);
	}
	
	public static function getDiff( $origen, $destino ){
		
		$orig_bytes = filesize( $origen );
		$dest_bytes = filesize( $destino );
		
		return WP_Utils_Guetzli::formatSizeUnits( $orig_bytes - $dest_bytes );
		
	}
	

	public static function compress( $attachment_id ){
		
		//WP_CLI::colorize( "%PATH: %n " );
		//WP_CLI::line( WP_CLI::colorize( "%YPATH: %n " ). self::$bin, 'guetzli' );
		
		$id = (int) $attachment_id; // Compatibilidad
		$image = get_post( $attachment_id );
		
		if ( !$image || 'attachment' != $image->post_type || 'image/' != substr( $image->post_mime_type, 0, 6 ) )
			WP_CLI::error( sprintf( __( '%d is an invalid attachment ID for idblog %d.', WP_Guetzli::$i18n_domain ), $attachment_id, get_current_blog_id() ) );
		
		
		$fullsizepath = get_attached_file( $image->ID );
		
		// Copia de seguridad de la imagen 
		self::createBackup( $fullsizepath );
		
		@set_time_limit( 900 ); // 5 minutes per image should be PLENTY
		
		// Ejecutamos la compresion solo si se genero la copia
		if( !self::$is_compressed ){
			WP_CLI::debug( sprintf( __( 'Starting compression. [ %s ]', WP_Guetzli::$i18n_domain ), $fullsizepath ), 'guetzli' );
			shell_exec( self::$bin.' --quality '.self::$quality.' '.$fullsizepath. ' ' .$fullsizepath );
		}else{
			WP_CLI::debug( sprintf( __( 'Omitted compression. [ %s ]', WP_Guetzli::$i18n_domain ), $fullsizepath ), 'guetzli' );
		}
		
		if ( false === $fullsizepath || ! file_exists( $fullsizepath ) )
			WP_CLI::error( sprintf( __( 'The originally uploaded image file cannot be found at %s', WP_Guetzli::$i18n_domain ), $fullsizepath ) );
		
		WP_CLI::debug( sprintf( __( 'Ended compression. [ %s ]', WP_Guetzli::$i18n_domain ), $fullsizepath ), 'guetzli' );
		
		$metadata = wp_generate_attachment_metadata( $image->ID, $fullsizepath );
		
		WP_CLI::debug( sprintf( __( 'Generated metadata for image attachment. [ %d ]', WP_Guetzli::$i18n_domain ), $image->ID ), 'guetzli' );
		
		if ( is_wp_error( $metadata ) )
			WP_CLI::error( sprintf( __( 'Error %s', WP_Guetzli::$i18n_domain ), $metadata->get_error_message() ) );
		if ( empty( $metadata ) )
			WP_CLI::error(  __( 'Unknown failure reason.', WP_Guetzli::$i18n_domain ) );
		
		// If this fails, then it just means that nothing was changed (old value == new value)
		wp_update_attachment_metadata( $image->ID, $metadata );
		
		$saving = self::getDiff($fullsizepath.self::$backup_extension, $fullsizepath );

		if( GUETZLI__PLUGIN_DEBUG ) WP_CLI::success( sprintf( __( 'Saving %s [ %s ]', WP_Guetzli::$i18n_domain ), $saving , $fullsizepath ) );

		//'id_blog', 'attachment_id', 'after', 'before', 'saving'
		self::$result[] = array(
				'id_blog' 		=> get_current_blog_id(), // id_blog.
				'attachment_id' => $attachment_id,
				'after' 		=> WP_Utils_Guetzli::formatSizeUnits( filesize( $fullsizepath.'.bak' ) ),
				'before'		=> WP_Utils_Guetzli::formatSizeUnits( filesize( $fullsizepath ) ),
				//'saving' 		=> self::getDiff($fullsizepath.'.bak' ,$fullsizepath )
				'saving' 		=> WP_CLI::colorize( "%Y".self::getDiff($fullsizepath.'.bak' ,$fullsizepath )."%n" ) 
				);
		
	}

	/**
	 * Compress all media's images from a Wordpress blog using google's guetzli
	 *
	 * ## OPTIONS
	 *
	 * [--idblog=<idblog>]
	 * : The idBlog.
	 * ---
	 * default: 1
	 *
	 * [--debug]
	 * : Show verbose info about the process.
	 */
	function media( $args, $assoc_args ){ //$max_working_time = 10

		if( is_multisite() ){
		
			if( isset( $assoc_args['idblog'] ) && ( $assoc_args['idblog'] <= 0 ) ){
				WP_CLI::error( sprintf( __( '%s is not a valid blog ID.', WP_Guetzli::$i18n_domain ), $assoc_args['idblog'] ) );
		
			}elseif( !isset( $assoc_args['idblog'] ) || empty( $assoc_args['idblog'] )){
		
				$blog_id = get_current_blog_id();
		
				WP_CLI::warning( __('Working on a Wordpress Multisite instance') );
				WP_CLI::confirm( sprintf( __( 'Do you want to proceed with idblog=%d?', WP_Guetzli::$i18n_domain ), $blog_id ) );
		
			}else{
				$blog_id = (int) $assoc_args['idblog'];
			}
		
		}else{
			$blog_id = get_current_blog_id();
		}
		
		if( is_multisite() && $assoc_args['idblog'] ){
			switch_to_blog( $assoc_args['idblog'] );
			WP_CLI::debug( sprintf( __( 'Switched to blog #%d', WP_Guetzli::$i18n_domain ), get_current_blog_id() ), 'guetzli' );
		}
		
		$total_time = 0;
		$images = WP_Guetzli::getMediafiles();
		
		if(empty($images)){
			WP_CLI::warning( __( 'Media vacio.', WP_Guetzli::$i18n_domain ) );
		}
		
		WP_CLI::debug( sprintf( __( 'Starting process_cron for %d images', WP_Guetzli::$i18n_domain ), count($images) ), 'guetzli' );
		
		$progress = \WP_CLI\Utils\make_progress_bar( 'Optimizing sites', count($images) );
		
		
		foreach( $images as $attachment_id => $img ){

			// Si ha superado 10 minutos abortamos
			if( ($total_time ) > 10 * MINUTE_IN_SECONDS ) break;
			
			$start_time = self::microtime_float();
			WP_CLI::debug( sprintf( __( 'Start (ID %d)', WP_Guetzli::$i18n_domain ), $attachment_id ), 'guetzli' );
			
			self::compress( $attachment_id );
			
			$end_time = self::microtime_float();
			WP_CLI::debug( sprintf( __( 'Done! (ID %d)', WP_Guetzli::$i18n_domain ), $attachment_id ), 'guetzli' );
			
			$total_time = $total_time + ($end_time - $start_time);
			
			WP_CLI::debug( sprintf( __( 'Time: %d s', WP_Guetzli::$i18n_domain ), $total_time ), 'guetzli' );
			
			$progress->tick();
			
		}
		
		$progress->finish();
		
		if( is_multisite() && $assoc_args['idblog'] ){
			restore_current_blog();
			WP_CLI::debug(  __( 'Switched back', WP_Guetzli::$i18n_domain ) , 'guetzli' );
		}
		
		WP_CLI::debug( sprintf( __( 'Total Time: %d', WP_Guetzli::$i18n_domain ), $total_time ), 'guetzli' );
		
		WP_CLI\Utils\format_items( 'table', self::$result, self::$cols );
		
	}
	
	/**
	 * Compress all media's images from a Wordpress blog using google's guetzli
	 *
	 * ## OPTIONS
	 *
	 * [--idblog=<idblog>]
	 * : The idBlog.
	 * ---
	 * default: 1
	 *
	 * [--debug]
	 * : Show verbose info about the process.
	 */
	function restore_media( $args, $assoc_args ){
		
		if( empty($assoc_args) ){
			WP_CLI::error( 'Error' );
		}
		
		if( is_multisite() ){
		
			if( isset( $assoc_args['idblog'] ) && ( $assoc_args['idblog'] <= 0 ) ){
				WP_CLI::error( sprintf( __( '%s is not a valid blog ID.', WP_Guetzli::$i18n_domain ), $assoc_args['idblog'] ) );
		
			}elseif( !isset( $assoc_args['idblog'] ) || empty( $assoc_args['idblog'] )){
		
				$blog_id = get_current_blog_id();
		
				WP_CLI::warning( __('Working on a Wordpress Multisite instance') );
				WP_CLI::confirm( sprintf( __( 'Do you want to proceed with idblog=%d?', WP_Guetzli::$i18n_domain ), $blog_id ) );
		
			}else{
				$blog_id = (int) $assoc_args['idblog'];
			}
		
		}else{
			$blog_id = get_current_blog_id();
		}
				
		//if( GUETZLI__PLUGIN_DEBUG ){
			//WP_CLI::line( WP_CLI::colorize( "Selectd ID Blog \t %Y$blog_id%n " ), 'guetzli' );
		//}
		
		if( is_multisite() && $assoc_args['idblog'] ){
			switch_to_blog( $assoc_args['idblog'] );
			WP_CLI::debug( sprintf( __( 'Switched to blog #%d', WP_Guetzli::$i18n_domain ), get_current_blog_id() ), 'guetzli' );
		}
		
		$total_time = 0;
		$images = WP_Guetzli::getMediafiles();
		
		if(empty($images)){
			WP_CLI::warning( __( 'Media vacio.', WP_Guetzli::$i18n_domain ) );
		}
		
		WP_CLI::debug( sprintf( __( 'Starting process_cron for %d images', WP_Guetzli::$i18n_domain ), count($images) ), 'guetzli' );
		
		$progress = \WP_CLI\Utils\make_progress_bar( 'Optimizing sites', count($images) );
		
		
		foreach( $images as $attachment_id => $img ){
			
			$image = get_post( $attachment_id );
			
			if ( !$image || 'attachment' != $image->post_type || 'image/' != substr( $image->post_mime_type, 0, 6 ) )
				WP_CLI::error( sprintf( __( '%d is an invalid attachment ID for idblog %d.', WP_Guetzli::$i18n_domain ), $attachment_id, get_current_blog_id() ) );
			
			
			$fullsizepath = get_attached_file( $image->ID );
			
			// Copia de seguridad de la imagen
			self::restoreBackup( $fullsizepath );
			
			$progress->tick();
			
		}
		
		$progress->finish();
		
		if( is_multisite() && $assoc_args['idblog'] ){
			restore_current_blog();
			WP_CLI::debug(  __( 'Switched back', WP_Guetzli::$i18n_domain ) , 'guetzli' );
		}
		
		
		
			
	}
	
	/**
	 * Compress all media's images from a Wordpress blog using google's guetzli
	 *
	 * ## OPTIONS
	 *
	 * [--dryrun]
	 * : Nothing happens
	 * ---
	 * default: 0
	 *
	 * [--verbose]
	 * : Show verbose info about the process.
	 */
	function network( $args, $assoc_args ){
		
		if( isset( $assoc_args['dryrun'] ) && $assoc_args['dryrun'] ){
			$dryrun = true;
		}else
			$dryrun = false;
		
		if( isset( $assoc_args['verbose'] ) && $assoc_args['verbose'] ){
			$verbose = true;
		}else
			$verbose = false;
		
		
		self::$sites = get_sites();
		
		WP_CLI::debug( sprintf( __( 'Total Sites found: %d', WP_Guetzli::$i18n_domain ), count(self::$sites) ), 'guetzli' );
		
		
		if($verbose) $progress = \WP_CLI\Utils\make_progress_bar( 'Optimizing sites', count(self::$sites) );
				
		
		foreach ( self::$sites as $site ){
			ob_start();
			$options = array(
					'return'     => true,   // Return 'STDOUT'; use 'all' for full object.
					'parse'      => 'json', // Parse captured STDOUT to JSON array.
					'launch'     => false,  // Reuse the current process.
					'exit_error' => true,   // Halt script execution on error.
			);
			//$plugins = WP_CLI::runcommand( 'guetzli media --debug --idblog='.$site->ID, $options );
			WP_CLI::run_command( array( 'guetzli', 'media' ), array('debug' => true, 'idblog' => $site->ID) );
			$res = ob_get_clean();
			WP_CLI::line( '<pre>'.print_r($res,1).'</pre>' );
			
			if($verbose) $progress->tick();
			
		}
		if($verbose) $progress->finish();
		 
	}
	
	/**
	 * Compress all media's images from a Wordpress blog using google's guetzli
	 *
	 * ## OPTIONS
	 * 
	 * [--id=<attachment_id>]
	 * : The id_attachment of the image to compress.
	 *
	 * [--idblog=<idblog>]
	 * : The idBlog.
	 * ---
	 * default: 1
	 *
	 * [--debug]
	 * : Show verbose info about the process.
	 */
	function restore_image( $args, $assoc_args ){

		// ID BLOG PARAM
		if( isset( $assoc_args['idblog'] ) && ( $assoc_args['idblog'] <= 0 ) ){
			WP_CLI::error( sprintf( __( '%s is not a valid idblog.', WP_Guetzli::$i18n_domain ), $assoc_args['id'] ) );
			
		}elseif( !isset( $assoc_args['idblog'] ) || empty( $assoc_args['idblog'] )){
		
			$blog_id = get_current_blog_id();
			
			WP_CLI::warning( __('Working on a Wordpress Multisite instance') );
			WP_CLI::confirm( sprintf( __( 'Do you want to proceed with idblog=%d?', WP_Guetzli::$i18n_domain ), $blog_id ) );
			
		}else{
			$blog_id = $assoc_args['idblog'];
		}
		
		if( isset( $assoc_args['id'] ) && ( (int) $assoc_args['id'] <= 0 ) ){
			WP_CLI::error( sprintf( __( '%s is not a valid attachment ID.', WP_Guetzli::$i18n_domain ), $assoc_args['id'] ) );
			
		}elseif( !isset( $assoc_args['id'] ) ){
			WP_CLI::error( __( 'Required id to restore backup image. ', WP_Guetzli::$i18n_domain ) );
		}else{
			$id = $assoc_args['id'];
		}
		

		if( is_multisite() && $blog_id > 1 ){
			switch_to_blog( $blog_id );
		}
		
		$image = get_post( $id );

		if ( !$image || 'attachment' != $image->post_type || 'image/' != substr( $image->post_mime_type, 0, 6 ) )
			WP_CLI::error( sprintf( __( '%d is an invalid attachment ID for idblog %d.', WP_Guetzli::$i18n_domain ), $id, get_current_blog_id() ) );
		
		
		$fullsizepath = get_attached_file( $image->ID );
		
		if( GUETZLI__PLUGIN_DEBUG ){
			WP_CLI::line( WP_CLI::colorize( "Selectd ID Attachment \t %Y$id%n " ) , 'guetzli' );
			WP_CLI::line( WP_CLI::colorize( "Selectd ID Blog \t %Y$blog_id%n " ), 'guetzli' );
			WP_CLI::line( WP_CLI::colorize( "Image Path \t %Y$fullsizepath%n " ), 'guetzli' );
		}

		// restauramos backup
		self::restoreBackup( $fullsizepath );
		
		if( is_multisite() && $blog_id > 1 ){
			restore_current_blog();
			WP_CLI::debug(  __( 'Switched back', WP_Guetzli::$i18n_domain ) , 'guetzli' );
		}
		
	}
	
	
	public static function restoreBackup( $filename ){

		if ( file_exists( $filename.self::$backup_extension ) ) {
			if (!rename( $filename.self::$backup_extension, $filename ) )
				WP_CLI::error( sprintf( __( 'Error al restaurar la copia de respaldo para el fichero: "%s".', WP_Guetzli::$i18n_domain ), $filename ) );
				
			WP_CLI::success( sprintf( __( 'Restored backup successfully. [ %s ]', WP_Guetzli::$i18n_domain ), $filename ) );
			//self::$is_compressed = false;
				
		}else{
			WP_CLI::debug( sprintf( __( 'No Backup found. [ %s ]', WP_Guetzli::$i18n_domain ), $filename.self::$backup_extension ), 'guetzli' );
			//self::$is_compressed = true;
		}
		
	}
	
	public static function createBackup( $filename ){
	
		if (!file_exists( $filename.self::$backup_extension ) ) {
			if (!copy( $filename, $filename.self::$backup_extension ) )
				WP_CLI::error( sprintf( __( 'Error al realizar copia de respaldo para el fichero: "%s".', WP_Guetzli::$i18n_domain ), $filename ) );
				
			WP_CLI::debug( sprintf( __( 'Created backup successfully. [ %s ]', WP_Guetzli::$i18n_domain ), $filename.self::$backup_extension ), 'guetzli' );
			self::$is_compressed = false;
				
		}else{
			WP_CLI::debug( sprintf( __( 'Backup already exists. [ %s ]', WP_Guetzli::$i18n_domain ), $filename.self::$backup_extension ), 'guetzli' );
			self::$is_compressed = true;
		}
	
	}
	
	/**
	 * Compress all media's images from a Wordpress blog using google's guetzli
	 *
	 * ## OPTIONS
	 *
	 * [--idblog=<idblog>]
	 * : The idBlog.
	 * ---
	 * default: 1
	 *
	 * [--debug]
	 * : Show verbose info about the process.
	 */
	function status( $args, $assoc_args ){
	    
	    // ID BLOG PARAM
	    if( isset( $assoc_args['idblog'] ) && ( $assoc_args['idblog'] <= 0 ) ){
	        WP_CLI::error( sprintf( __( '%s is not a valid idblog.', WP_Guetzli::$i18n_domain ), $assoc_args['id'] ) );
	        
	    }elseif( !isset( $assoc_args['idblog'] ) || empty( $assoc_args['idblog'] )){
	        
	        $blog_id = get_current_blog_id();
	        
	        WP_CLI::warning( __('Working on a Wordpress Multisite instance') );
	        WP_CLI::confirm( sprintf( __( 'Do you want to proceed with idblog=%d?', WP_Guetzli::$i18n_domain ), $blog_id ) );
	        
	    }else{
	        $blog_id = $assoc_args['idblog'];
	    }
	    
	    if( is_multisite() && $blog_id > 1 ){
	        switch_to_blog( $blog_id );
	    }

	    
	    $total_time = 0;
	    $images = WP_Guetzli::getMediafiles();
	    self::$result = array();

	    foreach( $images as $attachment_id => $img ){
	        
	        $fullsizepath = get_attached_file( $attachment_id );
	        
	        if ( false === $fullsizepath ){
	            
	            $imagen_original = false;
	            $imagen_comprimida = false;
	            
	            
	        }elseif( !file_exists( $fullsizepath ) ){
	            
	            $imagen_comprimida = false;
	            
	            if( !file_exists( $fullsizepath.'.bak' ) ){
	                
	                $imagen_original = false;
	                
	            }else{
	                $imagen_original = WP_Utils_Guetzli::formatSizeUnits( filesize( $fullsizepath.'.bak' ) );
	            }
	            
	        }else{
	            
	            //$imagen_original = WP_Utils_Guetzli::formatSizeUnits( filesize( $fullsizepath.'.bak' ) );
	            $imagen_comprimida = WP_Utils_Guetzli::formatSizeUnits( filesize( $fullsizepath ) );
	            
	            if( !file_exists( $fullsizepath.'.bak' ) ){
	                
	                $imagen_original = false;
	                
	            }else{
	                $imagen_original = WP_Utils_Guetzli::formatSizeUnits( filesize( $fullsizepath.'.bak' ) );
	            }
	            
	        }
	        
	        
	        
	        $image_backup = 
	        
	        self::$result[] = array(
	            'id_blog' 		=> $blog_id,
	            'attachment_id' => $attachment_id,
// 	            'after' 		=> WP_Utils_Guetzli::formatSizeUnits( filesize( $fullsizepath.'.bak' ) ),
// 	            'before'		=> WP_Utils_Guetzli::formatSizeUnits( filesize( $fullsizepath ) ),
// 	            'saving' 		=> WP_CLI::colorize( "%Y".self::getDiff($fullsizepath.'.bak' ,$fullsizepath )."%n" )
	            'after' 		=> ( $imagen_original ) ? $imagen_original : '0 bytes',
	            'before'		=> ( $imagen_comprimida ) ? $imagen_comprimida : '0 bytes',
	            'saving' 		=> ( $imagen_original && $imagen_original ) ? WP_CLI::colorize( "%Y".self::getDiff($fullsizepath.'.bak' ,$fullsizepath )."%n" ) : WP_CLI::colorize( "%Y0 bytes%n" ),
	        );
	        
	        
	        
	    }
	    
	    if( is_multisite() && $blog_id > 1 ){
	        restore_current_blog();
	    }
	    
	    WP_CLI\Utils\format_items( 'table', self::$result, self::$cols );
	    
        	    
	}
	
}
