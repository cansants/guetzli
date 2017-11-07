<?php 

class WP_Guetzli{
	
	private static $initiated = false;
	
	private static $menu_id;
	
	private static $capability;
	
	private static $option_name;
	private static $option;
	
	public static $i18n_domain;
	
	private static $bin;
	
	/**
	 * Initializes Plugin
	 */
	public static function init() {
	
		if ( ! self::$initiated ) {
				
			self::$option_name = 'wp_guetzli';
			self::$i18n_domain = 'guetzli';
				
			self::init_hooks();
			
			// Allow people to change what capability is required to use this plugin
			self::$capability = apply_filters( 'regenerate_thumbs_cap', 'manage_options' );
			
			self::$bin = plugin_dir_path( __FILE__ ) .'../lib/guetzli';
			
		}
	
	}
	
	/**
	 * Initializes WordPress hooks
	 */
	private static function init_hooks() {
	
		self::$initiated = true;
	
		// Backend Scripts
		add_action( 'admin_enqueue_scripts', array( 'WP_Guetzli', 'admin_enqueue_scripts') );
		
		add_action( 'init', array( 'WP_Guetzli', 'load_plugin_textdomain') );
	
		add_action( 'admin_menu', 		array( 'WP_Guetzli', 'admin_menu') );
	
		// Ajax Action
		add_action( 'wp_ajax_regenerate_image', array( 'WP_Guetzli', 'ajax_process_image' ) );
	
	
	}
	
	/**
	 * Enqueue css and js
	 */
	public static function admin_enqueue_scripts( $hook_suffix ){
	
		if ( $hook_suffix != self::$menu_id )
			return;
		
			
		wp_enqueue_script( 'wp_guetzli', plugins_url( '/../js/admin.js', __FILE__ ), array('jquery') );

		wp_localize_script( 'wp_guetzli', 'wp_Guetzli',array(
				 'ajax_url' => admin_url( 'admin-ajax.php' ),
				 'option_name' => self::$option_name )
		);
			
		
	
	}
	
	/**
	 * Load the plugin text domain for translation.
	 */
	public static function load_plugin_textdomain() {
	
		$res = load_plugin_textdomain(
				self::$i18n_domain,
				false,
				dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	
	}
	
	/**
	 * Add menu element for the plugin.
	 */
	public static function admin_menu(){
	
		self::$menu_id = add_media_page(
				__('Guetzli', self::$i18n_domain),
				__('Guetzli', self::$i18n_domain),
				'manage_options',
				'guetzli',
				array('WP_Guetzli','renderAdminPage'));
	
	}
	
	/**
	 * Render Admin page
	 */	
	public static function renderAdminPage(){
	
		$images = self::getMediafiles();
		
		//$sizes = self::get_image_sizes();
		$sizes = self::getSizes();
		
		include( plugin_dir_path( __FILE__ ) . '../views/grid.php');
	
	
	}
	
	public static function getMediafiles(){
		
		$query_images_args = array(
		    'post_type'      => 'attachment',
		    'post_mime_type' => 'image',
		    'post_status'    => 'inherit',
		    'posts_per_page' => - 1,
		);
		
		$query_images = new WP_Query( $query_images_args );
		
		$images = array();
		foreach ( $query_images->posts as $image ) {
		    // ID Attachment y src
			//$images[ $image->ID ] = wp_get_attachment_url( $image->ID );
			
			$images[ $image->ID ] = self::getImageSizes( $image->ID );
		}
		
		return $images;
			
	}
	
	static private function getSizes(){
		return get_intermediate_image_sizes();
	}
	
	static private function getImageSizes( $attachment_ID ){
		
		$imageSizes[ $attachment_ID ] = [];
		
		$imageSizes[ $attachment_ID ]['path'] = get_attached_file( $attachment_ID );
		
		foreach ( self::getSizes() as $size ){
		
			//$tmp = wp_get_attachment_image_src( $attachment_ID, $size, false );
			$imageSizes[ $attachment_ID ][$size] = wp_get_attachment_image_src( $attachment_ID, $size, false );
			
		}
		
		return $imageSizes;
		
	}
	
	/**
	 * Get size information for all currently-registered image sizes.
	 *
	 * @global $_wp_additional_image_sizes
	 * @uses   get_intermediate_image_sizes()
	 * @return array $sizes Data for all currently-registered image sizes.
	 */
	static private function get_image_sizes() {
		global $_wp_additional_image_sizes;
	
		$sizes = array();
	
		foreach ( get_intermediate_image_sizes() as $_size ) {
			if ( in_array( $_size, array('thumbnail', 'medium', 'medium_large', 'large') ) ) {
				$sizes[ $_size ]['width']  = get_option( "{$_size}_size_w" );
				$sizes[ $_size ]['height'] = get_option( "{$_size}_size_h" );
				$sizes[ $_size ]['crop']   = (bool) get_option( "{$_size}_crop" );
			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
				$sizes[ $_size ] = array(
						'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
						'height' => $_wp_additional_image_sizes[ $_size ]['height'],
						'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
				);
			}
		}
	
		return $sizes;
	}


	static public function ajax_process_image(){
		@error_reporting( 0 ); // Don't break the JSON result
		header( 'Content-type: application/json' );
		
		//die( json_encode(array( 'success' => '<pre>'.print_r($_POST,1).'</pre>')));
		
		$id = (int) $_POST['id'];
		$image = get_post( $id );
		
		if ( ! $image || 'attachment' != $image->post_type || 'image/' != substr( $image->post_mime_type, 0, 6 ) )
			die( json_encode( array( 'error' => sprintf( __( 'Failed resize: %s is an invalid image ID.', 'regenerate-thumbnails' ), esc_html( $_REQUEST['id'] ) ) ) ) );
		
		if ( ! current_user_can( self::$capability ) )
			$this->die_json_error_msg( $image->ID, __( "Your user account doesn't have permission to resize images", 'regenerate-thumbnails' ) );
		
		$fullsizepath = get_attached_file( $image->ID );
		
		if (!copy($fullsizepath, $fullsizepath.'.bak') )
			die( json_encode( array( 'error' => sprintf( __( 'Error al copiar el fichero: %s.', 'regenerate-thumbnails' ), esc_html( $fullsizepath ) ) ) ) );

		@set_time_limit( 900 ); // 5 minutes per image should be PLENTY
		
		shell_exec( self::$bin.' --quality 90 '.$fullsizepath. ' '.$fullsizepath );
		
		//die( json_encode(array( 'success' => '<pre>'.print_r($fullsizepath,1).'</pre>')));
		
		if ( false === $fullsizepath || ! file_exists( $fullsizepath ) )
			$this->die_json_error_msg( $image->ID, sprintf( __( 'The originally uploaded image file cannot be found at %s', 'regenerate-thumbnails' ), '<code>' . esc_html( $fullsizepath ) . '</code>' ) );
				
		$metadata = wp_generate_attachment_metadata( $image->ID, $fullsizepath );
		
		if ( is_wp_error( $metadata ) )
			$this->die_json_error_msg( $image->ID, $metadata->get_error_message() );
		if ( empty( $metadata ) )
			$this->die_json_error_msg( $image->ID, __( 'Unknown failure reason.', 'regenerate-thumbnails' ) );
		
		// If this fails, then it just means that nothing was changed (old value == new value)
		wp_update_attachment_metadata( $image->ID, $metadata );
		
		die( json_encode( array( 'success' => sprintf( __( '&quot;%1$s&quot; (ID %2$s) was successfully resized in %3$s seconds.', 'regenerate-thumbnails' ), esc_html( get_the_title( $image->ID ) ), $image->ID, timer_stop() ) ) ) );
		
	} 
	
}
