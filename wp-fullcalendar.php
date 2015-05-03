<?php
/*
Plugin Name: WP FullCalendar
Version: 0.9
Plugin URI: http://wordpress.org/extend/plugins/wp-fullcalendar/
Description: Uses the jQuery FullCalendar plugin to create a stunning calendar view of events, posts and eventually other CPTs. Integrates well with Events Manager
Author: Marcus Sykes
Author URI: http://msyk.es
*/

/*
Copyright (c) 2012, Marcus Sykes

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

define('WPFC_VERSION', '1.0.2');
define('WPFC_UI_VERSION','1.11'); //jQuery 1.11.x

class WP_FullCalendar{
	static $args = array();
	static $tip_styles = array('default','plain','light','dark','red','green','blue','youtube','jtools','cluetip','tipped','tipsy');
	static $tip_styles_css3 = array('shadow','rounded');
	static $tip_positions = array('top left', 'top right', 'top center', 'bottom left', 'bottom right', 'bottom center', 'right center', 'right top', 'right bottom', 'left center', 'left top', 'left bottom', 'center');

	public static function init() {
		//Scripts
		if( !is_admin() ){ //show only in public area
		    add_action('wp_enqueue_scripts',array('WP_FullCalendar','enqueue_scripts'));
			//shortcodes
			add_shortcode('fullcalendar', array('WP_FullCalendar','calendar'));
			add_shortcode('events_fullcalendar', array('WP_FullCalendar','calendar')); //depreciated, will be gone by 1.0
		}else{
			//admin actions
			include('wpfc-admin.php');
		}
		add_action('wp_ajax_WP_FullCalendar', array('WP_FullCalendar','ajax') );
		add_action('wp_ajax_nopriv_WP_FullCalendar', array('WP_FullCalendar','ajax') );
		add_action('wp_ajax_wpfc_qtip_content', array('WP_FullCalendar','qtip_content') );
		add_action('wp_ajax_nopriv_wpfc_qtip_content', array('WP_FullCalendar','qtip_content') );
		//base arguments
		self::$args['type'] = get_option('wpfc_default_type','event');
		//START Events Manager Integration - will soon be removed
		if( defined('EM_VERSION') && is_admin() ){
		    include('wpfc-events-manager.php');
		}
		//END Events Manager Integration
	}
	
	public static function enqueue_scripts(){
	    global $wp_query;
	    $min = defined('WP_DEBUG') && WP_DEBUG ? '':'.min';
	    $obj_id = is_home() ? '-1':$wp_query->get_queried_object_id();
	    $wpfc_scripts_limit = get_option('wpfc_scripts_limit');
	    if( empty($wpfc_scripts_limit) || in_array($obj_id, explode(',',$wpfc_scripts_limit)) ){
		    //Scripts
		    wp_enqueue_script('wp-fullcalendar', plugins_url('includes/js/main.js',__FILE__), array('jquery', 'jquery-ui-core','jquery-ui-widget','jquery-ui-position', 'jquery-ui-selectmenu'), WPFC_VERSION); //jQuery will load as dependency
		    WP_FullCalendar::localize_script();
		    //Styles
		    wp_enqueue_style('wp-fullcalendar', plugins_url('includes/css/main.css',__FILE__), array(), WPFC_VERSION);
		    //Load custom style or jQuery UI Theme
		    $wpfc_theme = get_option('wpfc_theme_css');
		    if( preg_match('/\.css$/', $wpfc_theme) ){
		        //user-defined style within the themes/themename/plugins/wp-fullcalendar/ folder
		        //if you're using jQuery UI Theme-Roller, you need to include the jquery-ui-css framework file too, you could do this using the @import CSS rule or include it all in your CSS file
		        if( file_exists(get_stylesheet_directory()."/plugins/wp-fullcalendar/".$wpfc_theme) ){
		            $wpfc_theme_css = get_stylesheet_directory_uri()."/plugins/wp-fullcalendar/".$wpfc_theme;
            	    wp_deregister_style('jquery-ui-css'); 
            	    wp_enqueue_style('jquery-ui-css', $wpfc_theme_css, array('wp-fullcalendar'), WPFC_VERSION);
		        }
		    }elseif( !empty($wpfc_theme) ){
    		    //We'll find the current jQuery UI version and attempt to load the right version of jQuery UI, otherwise we'll load the default. This allows backwards compatability from 3.6 onwards.
        	    global $wp_scripts;
        	    $jquery_ui_version = preg_replace('/\.[0-9]+$/', '', $wp_scripts->registered['jquery-ui-core']->ver);
        	    if( $jquery_ui_version != WPFC_UI_VERSION ){
            	    $jquery_ui_css_versions = glob( $plugin_path = plugin_dir_path(__FILE__)."/includes/css/jquery-ui-".$jquery_ui_version.'*', GLOB_ONLYDIR);
        		    if( !empty($jquery_ui_css_versions) ){
        		        //use backwards compatible theme
        		        $jquery_ui_css_folder = str_replace(plugin_dir_path(__FILE__),'', array_pop($jquery_ui_css_versions));
        		        $jquery_ui_css_uri = plugins_url(trailingslashit($jquery_ui_css_folder).$wpfc_theme."/jquery-ui$min.css",__FILE__);
        		        $wpfc_theme_css = plugins_url(trailingslashit($jquery_ui_css_folder).$wpfc_theme.'/theme.css',__FILE__);
        		    }
        	    }
        	    if( empty($wpfc_theme_css) ){
    		        //use default theme
    		        $jquery_ui_css_uri = plugins_url('/includes/css/jquery-ui/'.$wpfc_theme."/jquery-ui$min.css",__FILE__);
    		        $wpfc_theme_css = plugins_url('/includes/css/jquery-ui/'.$wpfc_theme.'/theme.css',__FILE__);
    		    }
            	if( !empty($wpfc_theme_css) ){   
            	    wp_deregister_style('jquery-ui-css'); 
            	    wp_enqueue_style('jquery-ui-css', $jquery_ui_css_uri, array('wp-fullcalendar'), WPFC_VERSION);
            	    wp_enqueue_style('jquery-ui-css-theme', $wpfc_theme_css, array('wp-fullcalendar'), WPFC_VERSION);
            	}
		    }
	    }
	}

	public static function localize_script(){
		$js_vars = array();
		$schema = is_ssl() ? 'https':'http';
		$js_vars['ajaxurl'] = admin_url('admin-ajax.php', $schema);
		$js_vars['firstDay'] =  get_option('start_of_week');
		$js_vars['wpfc_theme'] = get_option('wpfc_theme_css') ? true:false;
		$js_vars['wpfc_limit'] = get_option('wpfc_limit',3);
		$js_vars['wpfc_limit_txt'] = get_option('wpfc_limit_txt','more ...');
		//FC options
		$js_vars['timeFormat'] = get_option('wpfc_timeFormat', 'h(:mm)t');
		$js_vars['defaultView'] = get_option('wpfc_defaultView', 'month');
		$js_vars['weekends'] = get_option('wpfc_weekends',true) ? 'true':'false';
		$js_vars['header'] = new stdClass();
		$js_vars['header']->right = implode(',', get_option('wpfc_available_views', array('month','basicWeek','basicDay')));
		//qtip options
    	$js_vars['wpfc_qtips'] = get_option('wpfc_qtips',true) == true;
		if( $js_vars['wpfc_qtips'] ){
    		$js_vars['wpfc_qtips_classes'] = 'ui-tooltip-'. get_option('wpfc_qtips_style','light');
    		$js_vars['wpfc_qtips_my'] = get_option('wpfc_qtips_my','top center');
    		$js_vars['wpfc_qtips_at'] = get_option('wpfc_qtips_at','bottom center');
    		if( get_option('wpfc_qtips_rounded', false) ){
    			$js_vars['wpfc_qtips_classes'] .= " ui-tooltip-rounded";
    		}
    		if( get_option('wpfc_qtips_shadow', true) ){
    			$js_vars['wpfc_qtips_classes'] .= " ui-tooltip-shadow";
    		}
		}
		//calendar translations
		//This is taken from the Events Manager 5.2+ plugin. Improvements made here will be reflected there and vice-versa
		$locale_code = get_locale();
		$locale_code_short = substr ( $locale_code, 0, 2 );
		include('wpfc-languages.php'); //see here for translations
		$calendar_languages = wpfc_get_calendar_languages();
		if( array_key_exists($locale_code, $calendar_languages) ){
		    $js_vars['wpfc_locale'] = $calendar_languages[$locale_code];
		}elseif( array_key_exists($locale_code_short, $calendar_languages) ){
			$js_vars['wpfc_locale'] = $calendar_languages[$locale_code_short];
		}
		$js_vars['wpfc_locale']['firstDay'] =  $js_vars['firstDay']; //override firstDay with wp settings
		wp_localize_script('wp-fullcalendar', 'WPFC', apply_filters('wpfc_js_vars', $js_vars));
	}

	/**
	 * Catches ajax requests by fullcalendar
	 */
	public static function ajax(){
	    global $post;
	    //sort out args
	    unset($_REQUEST['month']); //no need for these two
	    unset($_REQUEST['year']);
	    $args = array ('scope'=>array(date("Y-m-d", $_REQUEST['start']), date("Y-m-d", $_REQUEST['end'])), 'owner'=>false, 'status'=>1, 'order'=>'ASC', 'orderby'=>'post_date','full'=>1);
	    //get post type and taxonomies, determine if we're filtering by taxonomy
	    $post_type = !empty($_REQUEST['type']) ? $_REQUEST['type']:'post';
	    $args['post_type'] = $post_type;
	    if( $args['post_type'] == 'attachment' ) $args['post_status'] = 'inherit';
	    $args['tax_query'] = array();
	    foreach( get_object_taxonomies($post_type) as $taxonomy_name ){
	        if( !empty($_REQUEST[$taxonomy_name]) ){
		    	$args['tax_query'][] = array(
					'taxonomy' => $taxonomy_name,
					'field' => 'id',
					'terms' => $_REQUEST[$taxonomy_name]
				);
	        }
	    }
	    //initiate vars
	    $args = apply_filters('wpfc_fullcalendar_args', array_merge($_REQUEST, $args));
		$limit = get_option('wpfc_limit',3);
	    $items = array();
	    $item_dates_more = array();
	    $item_date_counts = array();
	    
	    //Create our own loop here and tamper with the where sql for date ranges, as per http://codex.wordpress.org/Class_Reference/WP_Query#Time_Parameters
	    function wpfc_temp_filter_where( $where = '' ) {
	    	$where .= " AND post_date >= '".date("Y-m-d", $_REQUEST['start'])."' AND post_date < '".date("Y-m-d", $_REQUEST['end'])."'";
	    	return $where;
	    }
	    add_filter( 'posts_where', 'wpfc_temp_filter_where' );
		$the_query = new WP_Query( $args );
	    remove_filter( 'posts_where', 'wpfc_temp_filter_where' );
	    //loop through each post and slot them into the array of posts to return to browser
	    while ( $the_query->have_posts() ) { $the_query->the_post();
	    	$color = "#a8d144";
	    	$post_date = substr($post->post_date, 0, 10);
	    	$post_timestamp = strtotime($post->post_date);
	    	if( empty($item_date_counts[$post_date]) || $item_date_counts[$post_date] < $limit ){
	    		$title = $post->post_title;
	    		$item = array ("title" => $title, "color" => $color, "start" => date('Y-m-d\TH:i:s', $post_timestamp), "end" => date('Y-m-d\TH:i:s', $post_timestamp), "url" => get_permalink($post->ID), 'post_id' => $post->ID );
	    		$items[] = apply_filters('wpfc_ajax_post', $item, $post);
	    		$item_date_counts[$post_date] = (!empty($item_date_counts[$post_date]) ) ? $item_date_counts[$post_date]+1:1;
	    	}elseif( empty($item_dates_more[$post_date]) ){
	    		$item_dates_more[$post_date] = 1;
	    		$day_ending = $post_date."T23:59:59";
	    		//TODO archives not necesarrily working
	    		$more_array = array ("title" => get_option('wpfc_limit_txt','more ...'), "color" => get_option('wpfc_limit_color','#fbbe30'), "start" => $day_ending, 'post_id' => 0, 'allDay' => true);
	    		global $wp_rewrite;
	    		$archive_url = get_post_type_archive_link($post_type);
	    		if( !empty($archive_url) || $post_type == 'post' ){ //posts do have archives
	    		    $archive_url = trailingslashit($archive_url);
		    		$archive_url .= $wp_rewrite->using_permalinks() ? date('Y/m/', $post_timestamp):'?m='.date('Ym', $post_timestamp);
		    		$more_array['url'] = $archive_url;
	    		}
	    		$items[] = apply_filters('wpfc_ajax_more', $more_array, $post_date);
	    	}
	    }
	    echo json_encode(apply_filters('wpfc_ajax', $items));
	    die(); //normally we'd wp_reset_postdata();
	}

	/**
	 * Called during AJAX request for qtip content for a calendar item 
	 */
	public static function qtip_content(){
	    $content = '';
		if( !empty($_REQUEST['post_id']) ){
	        $post = get_post($_REQUEST['post_id']);
	        $content = ( !empty($post) ) ? $post->post_content : '';
	        if( get_option('wpfc_qtips_image',1) ){
	            $post_image = get_the_post_thumbnail($post->ID, array(get_option('wpfc_qtip_image_w',75),get_option('wpfc_qtip_image_h',75)));
	            if( !empty($post_image) ){
	                $content = '<div style="float:left; margin:0px 5px 5px 0px;">'.$post_image.'</div>'.$content;
	            }
	        }
	    }
		echo apply_filters('wpfc_qtip_content', $content);
		die();
	}
	
	/**
	 * Returns the calendar HTML setup and primes the js to load at wp_footer
	 * @param array $args
	 * @return string
	 */
	public static function calendar( $args = array() ){
		if (is_array($args) ) self::$args = array_merge(self::$args, $args);
		self::$args['month'] = (!empty($args['month'])) ? $args['month']-1:date('m', current_time('timestamp'))-1;
		self::$args['year'] = (!empty($args['year'])) ? $args['year']:date('Y', current_time('timestamp'));
		self::$args = apply_filters('wpfc_fullcalendar_args', self::$args);
		add_action('wp_footer', array('WP_FullCalendar','footer_js'));
		ob_start();
		?>
		<div id="wpfc-calendar-wrapper"><form id="wpfc-calendar"></form><div class="wpfc-loading"></div></div>
		<div id="wpfc-calendar-search" style="display:none;">
			<?php
				$post_type = !empty(self::$args['type']) ? self::$args['type']:'post';
				//figure out what taxonomies to show
				$wpfc_post_taxonomies = get_option('wpfc_post_taxonomies');
				$search_taxonomies = !empty($wpfc_post_taxonomies[$post_type]) ? array_keys($wpfc_post_taxonomies[$post_type]):array();
				if( !empty($args['taxonomies']) ){
					//we accept taxonomies in arguments
					$search_taxonomies = explode(',',$args['taxonomies']);
					array_walk($search_taxonomies, 'trim');
					unset(self::$args['taxonomies']);
				}
				//go through each post type taxonomy and display if told to
				foreach( get_object_taxonomies($post_type) as $taxonomy_name ){
					$taxonomy = get_taxonomy($taxonomy_name);
					if( count(get_terms($taxonomy_name, array('hide_empty'=>1))) > 0 && in_array($taxonomy_name, $search_taxonomies) ){
						$default_value = !empty(self::$args[$taxonomy_name]) ? self::$args[$taxonomy_name]:0;
						$taxonomy_args = array( 'echo'=>true, 'hide_empty' => 1, 'name' => $taxonomy_name, 'hierarchical' => true, 'class' => 'wpfc-taxonomy '.$taxonomy_name, 'taxonomy' => $taxonomy_name, 'selected'=> $default_value, 'show_option_all' => $taxonomy->labels->all_items);
						wp_dropdown_categories( apply_filters('wpmfc_calendar_taxonomy_args', $taxonomy_args, $taxonomy ) );
					}
				}
				do_action('wpfc_calendar_search', self::$args);
			?>
		</div>
		<script type="text/javascript">
    		WPFC.data = { action : 'WP_FullCalendar'<?php
    				//these arguments were assigned earlier on when displaying the calendar, and remain constant between ajax calls
    				if(!empty(self::$args)){ echo ", "; }
    				$strings = array(); 
    				foreach( self::$args as $key => $arg ){
    					$arg = is_numeric($arg) ? (int) $arg : "'$arg'"; 
    					$strings[] = "'$key'" ." : ". $arg ; 
    				}
    				echo implode(", ", $strings);
    		?> };
    		WPFC.month = <?php echo self::$args['month']; ?>;
    		WPFC.year = <?php echo self::$args['year']; ?>;
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Run at wp_footer if a calendar is output earlier on in the page.
	 * @uses self::$args - which was modified during self::calendar()
	 */
	public static function footer_js(){
		?>
		<script type='text/javascript'>
		<?php include('includes/js/inline.js'); ?>
		</script>
		<?php
	}
}
add_action('plugins_loaded',array('WP_FullCalendar','init'), 100);

// action links
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'wpfc_settings_link', 10, 1);
function wpfc_settings_link($links) {
	$new_links = array(); //put settings first
	$new_links[] = '<a href="'.admin_url('options-general.php?page=wp-fullcalendar').'">'.__('Settings', 'wpfc').'</a>';
	return array_merge($new_links,$links);
}

//translations
load_plugin_textdomain('wpfc', false, dirname( plugin_basename( __FILE__ ) ).'/includes/langs');