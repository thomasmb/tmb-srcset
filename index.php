<?php
/*
Plugin Name: TMB SRCSET
Plugin URI: http://www.bensmann.no
Description: Add support for SRCSET to your WordPress site
Version: 0.1
Author: Thomas Bensmann
Author URI: http://www.bensmann.no
*/

namespace Bensmann;

if( !class_exists( 'SRCSET' ) ){

	class SRCSET{
	
		const HIDPI_SYMBOL = "_2x";

		public static function add_hidpi_sizes(){
			global $_wp_additional_image_sizes;
			$sizes = array_merge( $_wp_additional_image_sizes, static::get_default_sizes() );
			$sizes = apply_filters( 'bensmann_srcset_sizes_for_hidpi', $sizes );

			foreach( $sizes as $name => $attr ){

				extract( $attr );

				if( !isset($sizes[ $name . self::HIDPI_SYMBOL ]) )
					add_image_size( $name . self::HIDPI_SYMBOL, $width * 2, $height * 2, $crop );

			}
		}

		public static function get_default_sizes(){
			$data = array();
			$sizes = array( 'thumbnail', 'medium', 'large' );

			foreach( $sizes as $name ){
				$data[ $name ] = array(
					'width'  => get_option( "{$name}_size_w" ),
					'height' => get_option( "{$name}_size_h" ),
					'crop' 	 => get_option( "{$name}_crop", 'thumbnail' == $name ? true : false )
				);
			}
			
			return $data;
		}

		public static function has_image_with_size( $id, $hidpi_size_name ){
			global $_wp_additional_image_sizes;
			$img_meta = wp_get_attachment_metadata( $id );

			if( !$img_meta && !isset( $_wp_additional_image_sizes[ $hidpi_size_name ] ) )
				return false;

			$hidpi_size = $_wp_additional_image_sizes[ $hidpi_size_name ];
			$img_has_size = isset( $img_meta[ 'sizes' ][ $hidpi_size_name ] );
			$img_is_large_enough = ( $img_meta[ 'height' ] >= $hidpi_size[ 'height' ] && $img_meta[ 'width' ] >= $hidpi_size[ 'width' ] );

			if( !$img_is_large_enough )
				return false;

			if( !$img_has_size ){			
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				$upload_dir = wp_upload_dir();
				$meta_data = wp_generate_attachment_metadata( $id, $upload_dir[ 'basedir' ] . '/' . $img_meta[ 'file' ] );
				wp_update_attachment_metadata( $id, $meta_data );
			}

			return true;
		}
		
		public static function replace_image( $html, $id, $name ){
			$hidpi_size_name = $name . self::HIDPI_SYMBOL;

			if( static::has_image_with_size( $id, $hidpi_size_name ) ){
				$image = wp_get_attachment_image_src( $id, $hidpi_size_name );
				$hidpi_url = array_shift( $image );
				$min_width = ( (int) array_shift( $image ) ) / 4;
				$html = preg_replace( '/src="(.*?)"/i', 'src="\1" srcset="\1 1x, \1 2x '. $min_width .'w, ' . $hidpi_url . ' 2x', $html );
			}
			
			return $html;
		}

		public static function image_send_to_editor_filter( $html, $id, $caption, $title, $align, $url, $size, $alt ){
			return static::replace_image( $html, $id, $size );
		}

		public static function the_content_filter( $content ){
			$content = preg_replace_callback( "/<img(?!.*srcset).*size-(.*?)\s+wp-image-(\d+).*?>/i", function( $matches ){
				$keys = array( 'html', 'size', 'id' );
				extract( array_combine( $keys, $matches ) );
				return static::replace_image( $html, $id, $size );
			}, $content);

			return $content;
		}
		
		public static function post_thumbnail_html_filter( $html, $post_id, $id, $size, $attr ){
			return static::replace_image( $html, $id, $size );
		}
		
		public static function register_polyfill(){
			wp_register_script(
			    'srcset_polyfill',  //handle
			    plugins_url( ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) ? '/js/srcset.js' : '/js/srcset.min.js', __FILE__ ),
			    array(),  //dependencies
			    '2',  //version
			    true  //footer
			);
			
			
			if( apply_filters( 'bensmann_srcset_polyfill_enabled', true ) )
				wp_enqueue_script('srcset_polyfill');
			
		}

		public static function register(){
		
			if( !is_admin() )
				static::register_polyfill();
		
			static::add_hidpi_sizes();
			
			add_filter( 'the_content', array( __CLASS__, 'the_content_filter' ) );
			add_filter( 'post_thumbnail_html', array( __CLASS__, 'post_thumbnail_html_filter' ), 10, 5 );
			
			if( apply_filters( 'bensmann_scrset_activate_editor_filter', false ) )
				add_filter( 'image_send_to_editor', array( __CLASS__, 'image_send_to_editor_filter' ), 1, 8 );
		}
		
		public static function init(){
			add_action( 'init', array( __CLASS__, 'register' ) );
		}
	}

	SRCSET::init();
	
}
