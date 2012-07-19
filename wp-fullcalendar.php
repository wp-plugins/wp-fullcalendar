<?php
/*
Plugin Name: WP FullCalendar
Version: 0.6
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

define('WPFC_VERSION', '1.0.1');

class WP_FullCalendar{
	static $args = array();
	static $tip_styles = array('default','plain','light','dark','red','green','blue','youtube','jtools','cluetip','tipped','tipsy');
	static $tip_styles_css3 = array('shadow','rounded');
	static $tip_positions = array('top left', 'top right', 'top center', 'bottom left', 'bottom right', 'bottom center', 'right center', 'right top', 'right bottom', 'left center', 'left top', 'left bottom', 'center');

	function init() {
		//Scripts
		if( !is_admin() ){ //show only in public area
			//Scripts
			wp_enqueue_script('wp-fullcalendar', plugins_url('includes/js/main.js',__FILE__), array('jquery', 'jquery-ui-core','jquery-ui-widget','jquery-ui-position')); //jQuery will load as dependency
			//Styles
			wp_enqueue_style('wp-fullcalendar', plugins_url('includes/css/main.css',__FILE__));
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
		//localize scripts
		WP_FullCalendar::localize_script();
		//START Events Manager Integration
		if( defined('EM_VERSION') ){
		    include('wpfc-events-manager.php');
		    wpfc_em_init();
		}
		//END Events Manager Integration
	}

	function localize_script(){
		$js_vars = array();
		$js_vars['ajaxurl'] = admin_url('admin-ajax.php');
		$js_vars['firstDay'] =  get_option('start_of_week');
		$js_vars['wpfc_theme'] = get_option('wpfc_theme_css') ? true:false;
		$js_vars['wpfc_limit'] = get_option('wpfc_limit',3);
		$js_vars['wpfc_limit_txt'] = get_option('wpfc_limit_txt','more ...');
		$js_vars['wpfc_theme_css'] = get_option('wpfc_theme_css') ? get_option('wpfc_theme_css'):'';
		//qtip options
		$js_vars['wpfc_qtips'] = get_option('wpfc_qtips',true) == true;
		$js_vars['wpfc_qtips_classes'] = 'ui-tooltip-'. get_option('wpfc_qtips_style','light');
		$js_vars['wpfc_qtips_my'] = get_option('wpfc_qtips_my','top center');
		$js_vars['wpfc_qtips_at'] = get_option('wpfc_qtips_at','bottom center');
		if( get_option('wpfc_qtips_rounded', false) ){
			$js_vars['wpfc_qtips_classes'] .= " ui-tooltip-rounded";
		}
		if( get_option('wpfc_qtips_shadow', true) ){
			$js_vars['wpfc_qtips_classes'] .= " ui-tooltip-shadow";
		}
		wp_localize_script('wp-fullcalendar', 'WPFC', $js_vars);
	}

	/**
	 * Catches ajax requests by fullcalendar
	 */
	function ajax(){
	    global $post;
	    //sort out args
	    $args = array ('scope'=>array(date("Y-m-d", $_REQUEST['start']), date("Y-m-d", $_REQUEST['end'])), 'owner'=>false, 'status'=>1, 'order'=>'ASC', 'orderby'=>'post_date');
	    //get post type and taxonomies, determine if we're filtering by taxonomy
	    $post_type = !empty($_REQUEST['type']) ? $_REQUEST['type']:'post';
	    $args['post_type'] = $post_type;
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
	    	if($item_date_counts[$post_date] <= $limit ){
	    		$title = $post->post_title;
	    		$items[] = array ("title" => $title, "color" => $color, "start" => date('Y-m-d\TH:i:s', $post_timestamp), "end" => date('Y-m-d\TH:i:s', $post_timestamp), "url" => get_permalink($post->ID), 'post_id' => $post->ID );
	    	}elseif( empty($item_dates_more[$post_date]) ){
	    		$item_dates_more[$post_date] = 1;
	    		$day_ending = $post_date."T23:59:59";
	    		//TODO: figure out where to send on more links, i.e. day archives?
	    		$items[] = apply_filters('wpfc_ajax_more', array ("title" => get_option('wpfc_limit_txt','more ...'), "color" => get_option('wpfc_limit_color','#fbbe30'), "start" => $day_ending, "end" => $day_ending, "url" => '#', 'post_id' => 0), $post_date);
	    	}
	    }
	    echo json_encode(apply_filters('wpfc_ajax', $items));
	    die(); //normally we'd wp_reset_postdata();
	}

	/**
	 * Called during AJAX request for qtip content for a calendar item 
	 */
	function qtip_content(){
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
	function calendar( $args = array() ){
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
					if( count(get_terms($taxonomy_name, array('hide_empty'=>1))) > 0 && (empty($search_taxonomies) || in_array($taxonomy_name, $search_taxonomies)) ){
						$default_value = !empty(self::$args[$taxonomy_name]) ? self::$args[$taxonomy_name]:0;
						$taxonomy_args = array( 'echo'=>true, 'hide_empty' => 1, 'name' => $taxonomy_name, 'hierarchical' => true, 'class' => 'wpfc-taxonomy', 'taxonomy' => $taxonomy_name, 'selected'=> $default_value, 'show_option_all' => $taxonomy->labels->all_items);
						wp_dropdown_categories( apply_filters('wpmfc_calendar_taxonomy_args', $taxonomy_args, $taxonomy ) );
					}
				}
				add_action('wpfc_calendar_search', self::$args);
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Run at wp_footer if a calendar is output earlier on in the page.
	 * @uses self::$args - which was modified during self::calendar()
	 */
	function footer_js(){
		$r = array();
		?>
		<script type='text/javascript'>
			var wpfc_loaded = false;
			var wpfc_counts = {};
			var wpfc_data = { action : 'WP_FullCalendar'<?php
					//these arguments were assigned earlier on when displaying the calendar, and remain constant between ajax calls
					if(!empty(self::$args)){ echo ", "; }
					$strings = array(); 
					foreach( self::$args as $key => $arg ){
						$arg = is_numeric($arg) ? (int) $arg : "'$arg'"; 
						$strings[] = "'$key'" ." : ". $arg ; 
					}
					echo implode(", ", $strings);
			?> };
			jQuery(document).ready( function($){
				$('#wpfc-calendar').fullCalendar({
					header: {
						left: 'prev,next today',
						center: 'title',
						right: 'month,basicWeek,basicDay'
					},
					month: <?php echo self::$args['month']; ?>,
					year: <?php echo self::$args['year']; ?>,
					theme: WPFC.wpfc_theme,
					firstDay: WPFC.firstDay,
					editable: false,
					eventSources: [{
							url : WPFC.ajaxurl,
							data : wpfc_data
					}],
				    eventRender: function(event, element) {
						if( event.post_id > 0 && WPFC.wpfc_qtips ){
							var event_data = { action : 'wpfc_qtip_content', post_id : event.post_id, event_id:event.event_id };
							element.qtip({
								content:{
									text : 'Loading...',
									ajax : {
										url : WPFC.ajaxurl,
										type : "POST",
										data : event_data
									}
								},
								position : {
									my: WPFC.wpfc_qtips_my,
									at: WPFC.wpfc_qtips_at
								},
								style : { classes:WPFC.wpfc_qtips_classes }
							});
						}
				    },
					loading: function(bool) {
						if (bool) {
							var position = $('#wpfc-calendar').position();
							$('.wpfc-loading').css('left',position.left).css('top',position.top).css('width',$('#calendar').width()).css('height',$('#calendar').height()).show();
						}else {
							wpfc_counts = {};
							$('.wpfc-loading').hide();
						}
					},
					viewDisplay: function(view) {
						if( !wpfc_loaded ){
							$('.fc-header tbody').append('<tr><td id="wpfc-filters"  colspan="3"></td></tr>');
							search_menu = $('#wpfc-calendar-search').show();
							$('#wpfc-filters').append(search_menu);
							//catchall selectmenu handle
							$('select.wpfc-taxonomy').selectmenu({
								format: function(text){
									//replace the color hexes with color boxes
									return text.replace(/#([a-zA-Z0-9]{3}[a-zA-Z0-9]{3}?) - /g, '<span class="wpfc-cat-icon" style="background-color:#$1"></span>');
								},
								open: function(){
									$('.ui-selectmenu-menu').css('z-index','1005');
								}
							}).change(function(event){
								wpfc_data[$(this).attr('name')] = $(this).find(':selected').val();
								$('#wpfc-calendar').fullCalendar('removeEventSource', WPFC.ajaxurl).fullCalendar('addEventSource', {url : WPFC.ajaxurl, data : wpfc_data});
							});
						}
						wpfc_loaded = true;
				    }
				});
				if( WPFC.wpfc_theme_css != '' ){ // add themeroller
					$('script#jquery-ui-css').remove(); //remove old css if exists
					var script = document.createElement("link"); script.id = "jquery-ui-css"; script.rel = "stylesheet"; script.href = WPFC.wpfc_theme_css;
					document.body.appendChild(script);
				}
			});
			//http://www.filamentgroup.com/lab/jquery_ui_selectmenu_an_aria_accessible_plugin_for_styling_a_html_select/
			(function(a){a.widget("ui.selectmenu",{getter:"value",version:"1.9",eventPrefix:"selectmenu",options:{transferClasses:true,appendTo:"body",typeAhead:1000,style:"dropdown",positionOptions:{my:"left top",at:"left bottom",offset:null},width:null,menuWidth:null,handleWidth:26,maxHeight:null,icons:null,format:null,escapeHtml:false,bgImage:function(){}},_create:function(){var b=this,e=this.options;var d=(this.element.attr("id")||"ui-selectmenu-"+Math.random().toString(16).slice(2,10)).replace(":","\\:");this.ids=[d,d+"-button",d+"-menu"];this._safemouseup=true;this.isOpen=false;this.newelement=a("<a />",{"class":this.widgetBaseClass+" ui-widget ui-state-default ui-corner-all",id:this.ids[1],role:"button",href:"#nogo",tabindex:this.element.attr("disabled")?1:0,"aria-haspopup":true,"aria-owns":this.ids[2]});this.newelementWrap=a("<span />").append(this.newelement).insertAfter(this.element);var c=this.element.attr("tabindex");if(c){this.newelement.attr("tabindex",c)}this.newelement.data("selectelement",this.element);this.selectmenuIcon=a('<span class="'+this.widgetBaseClass+'-icon ui-icon"></span>').prependTo(this.newelement);this.newelement.prepend('<span class="'+b.widgetBaseClass+'-status" />');this.element.bind({"click.selectmenu":function(f){b.newelement.focus();f.preventDefault()}});this.newelement.bind("mousedown.selectmenu",function(f){b._toggle(f,true);if(e.style=="popup"){b._safemouseup=false;setTimeout(function(){b._safemouseup=true},300)}return false}).bind("click.selectmenu",function(){return false}).bind("keydown.selectmenu",function(g){var f=false;switch(g.keyCode){case a.ui.keyCode.ENTER:f=true;break;case a.ui.keyCode.SPACE:b._toggle(g);break;case a.ui.keyCode.UP:if(g.altKey){b.open(g)}else{b._moveSelection(-1)}break;case a.ui.keyCode.DOWN:if(g.altKey){b.open(g)}else{b._moveSelection(1)}break;case a.ui.keyCode.LEFT:b._moveSelection(-1);break;case a.ui.keyCode.RIGHT:b._moveSelection(1);break;case a.ui.keyCode.TAB:f=true;break;case a.ui.keyCode.PAGE_UP:case a.ui.keyCode.HOME:b.index(0);break;case a.ui.keyCode.PAGE_DOWN:case a.ui.keyCode.END:b.index(b._optionLis.length);break;default:f=true}return f}).bind("keypress.selectmenu",function(f){if(f.which>0){b._typeAhead(f.which,"mouseup")}return true}).bind("mouseover.selectmenu",function(){if(!e.disabled){a(this).addClass("ui-state-hover")}}).bind("mouseout.selectmenu",function(){if(!e.disabled){a(this).removeClass("ui-state-hover")}}).bind("focus.selectmenu",function(){if(!e.disabled){a(this).addClass("ui-state-focus")}}).bind("blur.selectmenu",function(){if(!e.disabled){a(this).removeClass("ui-state-focus")}});a(document).bind("mousedown.selectmenu-"+this.ids[0],function(f){if(b.isOpen){b.close(f)}});this.element.bind("click.selectmenu",function(){b._refreshValue()}).bind("focus.selectmenu",function(){if(b.newelement){b.newelement[0].focus()}});if(!e.width){e.width=this.element.outerWidth()}this.newelement.width(e.width);this.element.hide();this.list=a("<ul />",{"class":"ui-widget ui-widget-content","aria-hidden":true,role:"listbox","aria-labelledby":this.ids[1],id:this.ids[2]});this.listWrap=a("<div />",{"class":b.widgetBaseClass+"-menu"}).append(this.list).appendTo(e.appendTo);this.list.bind("keydown.selectmenu",function(g){var f=false;switch(g.keyCode){case a.ui.keyCode.UP:if(g.altKey){b.close(g,true)}else{b._moveFocus(-1)}break;case a.ui.keyCode.DOWN:if(g.altKey){b.close(g,true)}else{b._moveFocus(1)}break;case a.ui.keyCode.LEFT:b._moveFocus(-1);break;case a.ui.keyCode.RIGHT:b._moveFocus(1);break;case a.ui.keyCode.HOME:b._moveFocus(":first");break;case a.ui.keyCode.PAGE_UP:b._scrollPage("up");break;case a.ui.keyCode.PAGE_DOWN:b._scrollPage("down");break;case a.ui.keyCode.END:b._moveFocus(":last");break;case a.ui.keyCode.ENTER:case a.ui.keyCode.SPACE:b.close(g,true);a(g.target).parents("li:eq(0)").trigger("mouseup");break;case a.ui.keyCode.TAB:f=true;b.close(g,true);a(g.target).parents("li:eq(0)").trigger("mouseup");break;case a.ui.keyCode.ESCAPE:b.close(g,true);break;default:f=true}return f}).bind("keypress.selectmenu",function(f){if(f.which>0){b._typeAhead(f.which,"focus")}return true}).bind("mousedown.selectmenu mouseup.selectmenu",function(){return false});a(window).bind("resize.selectmenu-"+this.ids[0],a.proxy(b.close,this))},_init:function(){var s=this,e=this.options;var b=[];this.element.find("option").each(function(){var i=a(this);b.push({value:i.attr("value"),text:s._formatText(i.text()),selected:i.attr("selected"),disabled:i.attr("disabled"),classes:i.attr("class"),typeahead:i.attr("typeahead"),parentOptGroup:i.parent("optgroup"),bgImage:e.bgImage.call(i)})});var m=(s.options.style=="popup")?" ui-state-active":"";this.list.html("");if(b.length){for(var k=0;k<b.length;k++){var f={role:"presentation"};if(b[k].disabled){f["class"]=this.namespace+"-state-disabled"}var u={html:b[k].text||"&nbsp;",href:"#nogo",tabindex:-1,role:"option","aria-selected":false};if(b[k].disabled){u["aria-disabled"]=b[k].disabled}if(b[k].typeahead){u.typeahead=b[k].typeahead}var r=a("<a/>",u);var d=a("<li/>",f).append(r).data("index",k).addClass(b[k].classes).data("optionClasses",b[k].classes||"").bind("mouseup.selectmenu",function(i){if(s._safemouseup&&!s._disabled(i.currentTarget)&&!s._disabled(a(i.currentTarget).parents("ul>li."+s.widgetBaseClass+"-group "))){var j=a(this).data("index")!=s._selectedIndex();s.index(a(this).data("index"));s.select(i);if(j){s.change(i)}s.close(i,true)}return false}).bind("click.selectmenu",function(){return false}).bind("mouseover.selectmenu focus.selectmenu",function(i){if(!a(i.currentTarget).hasClass(s.namespace+"-state-disabled")&&!a(i.currentTarget).parent("ul").parent("li").hasClass(s.namespace+"-state-disabled")){s._selectedOptionLi().addClass(m);s._focusedOptionLi().removeClass(s.widgetBaseClass+"-item-focus ui-state-hover");a(this).removeClass("ui-state-active").addClass(s.widgetBaseClass+"-item-focus ui-state-hover")}}).bind("mouseout.selectmenu blur.selectmenu",function(){if(a(this).is(s._selectedOptionLi().selector)){a(this).addClass(m)}a(this).removeClass(s.widgetBaseClass+"-item-focus ui-state-hover")});if(b[k].parentOptGroup.length){var l=s.widgetBaseClass+"-group-"+this.element.find("optgroup").index(b[k].parentOptGroup);if(this.list.find("li."+l).length){this.list.find("li."+l+":last ul").append(d)}else{a(' <li role="presentation" class="'+s.widgetBaseClass+"-group "+l+(b[k].parentOptGroup.attr("disabled")?" "+this.namespace+'-state-disabled" aria-disabled="true"':'"')+'><span class="'+s.widgetBaseClass+'-group-label">'+b[k].parentOptGroup.attr("label")+"</span><ul></ul></li> ").appendTo(this.list).find("ul").append(d)}}else{d.appendTo(this.list)}if(e.icons){for(var h in e.icons){if(d.is(e.icons[h].find)){d.data("optionClasses",b[k].classes+" "+s.widgetBaseClass+"-hasIcon").addClass(s.widgetBaseClass+"-hasIcon");var p=e.icons[h].icon||"";d.find("a:eq(0)").prepend('<span class="'+s.widgetBaseClass+"-item-icon ui-icon "+p+'"></span>');if(b[k].bgImage){d.find("span").css("background-image",b[k].bgImage)}}}}}}else{a('<li role="presentation"><a href="#nogo" tabindex="-1" role="option"></a></li>').appendTo(this.list)}var c=(e.style=="dropdown");this.newelement.toggleClass(s.widgetBaseClass+"-dropdown",c).toggleClass(s.widgetBaseClass+"-popup",!c);this.list.toggleClass(s.widgetBaseClass+"-menu-dropdown ui-corner-bottom",c).toggleClass(s.widgetBaseClass+"-menu-popup ui-corner-all",!c).find("li:first").toggleClass("ui-corner-top",!c).end().find("li:last").addClass("ui-corner-bottom");this.selectmenuIcon.toggleClass("ui-icon-triangle-1-s",c).toggleClass("ui-icon-triangle-2-n-s",!c);if(e.transferClasses){var t=this.element.attr("class")||"";this.newelement.add(this.list).addClass(t)}if(e.style=="dropdown"){this.list.width(e.menuWidth?e.menuWidth:e.width)}else{this.list.width(e.menuWidth?e.menuWidth:e.width-e.handleWidth)}this.list.css("height","auto");var n=this.listWrap.height();var g=a(window).height();var q=e.maxHeight?Math.min(e.maxHeight,g):g/3;if(n>q){this.list.height(q)}this._optionLis=this.list.find("li:not(."+s.widgetBaseClass+"-group)");if(this.element.attr("disabled")){this.disable()}else{this.enable()}this.index(this._selectedIndex());this._selectedOptionLi().addClass(this.widgetBaseClass+"-item-focus");clearTimeout(this.refreshTimeout);this.refreshTimeout=window.setTimeout(function(){s._refreshPosition()},200)},destroy:function(){this.element.removeData(this.widgetName).removeClass(this.widgetBaseClass+"-disabled "+this.namespace+"-state-disabled").removeAttr("aria-disabled").unbind(".selectmenu");a(window).unbind(".selectmenu-"+this.ids[0]);a(document).unbind(".selectmenu-"+this.ids[0]);this.newelementWrap.remove();this.listWrap.remove();this.element.unbind(".selectmenu").show();a.Widget.prototype.destroy.apply(this,arguments)},_typeAhead:function(e,f){var l=this,k=String.fromCharCode(e).toLowerCase(),d=null,j=null;if(l._typeAhead_timer){window.clearTimeout(l._typeAhead_timer);l._typeAhead_timer=undefined}l._typeAhead_chars=(l._typeAhead_chars===undefined?"":l._typeAhead_chars).concat(k);if(l._typeAhead_chars.length<2||(l._typeAhead_chars.substr(-2,1)===k&&l._typeAhead_cycling)){l._typeAhead_cycling=true;d=k}else{l._typeAhead_cycling=false;d=l._typeAhead_chars}var g=(f!=="focus"?this._selectedOptionLi().data("index"):this._focusedOptionLi().data("index"))||0;for(var h=0;h<this._optionLis.length;h++){var b=this._optionLis.eq(h).text().substr(0,d.length).toLowerCase();if(b===d){if(l._typeAhead_cycling){if(j===null){j=h}if(h>g){j=h;break}}else{j=h}}}if(j!==null){this._optionLis.eq(j).find("a").trigger(f)}l._typeAhead_timer=window.setTimeout(function(){l._typeAhead_timer=undefined;l._typeAhead_chars=undefined;l._typeAhead_cycling=undefined},l.options.typeAhead)},_uiHash:function(){var b=this.index();return{index:b,option:a("option",this.element).get(b),value:this.element[0].value}},open:function(e){var b=this,f=this.options;if(b.newelement.attr("aria-disabled")!="true"){b._closeOthers(e);b.newelement.addClass("ui-state-active");b.listWrap.appendTo(f.appendTo);b.list.attr("aria-hidden",false);b.listWrap.addClass(b.widgetBaseClass+"-open");var c=this._selectedOptionLi();if(f.style=="dropdown"){b.newelement.removeClass("ui-corner-all").addClass("ui-corner-top")}else{this.list.css("left",-5000).scrollTop(this.list.scrollTop()+c.position().top-this.list.outerHeight()/2+c.outerHeight()/2).css("left","auto")}b._refreshPosition();var d=c.find("a");if(d.length){d[0].focus()}b.isOpen=true;b._trigger("open",e,b._uiHash())}},close:function(c,b){if(this.newelement.is(".ui-state-active")){this.newelement.removeClass("ui-state-active");this.listWrap.removeClass(this.widgetBaseClass+"-open");this.list.attr("aria-hidden",true);if(this.options.style=="dropdown"){this.newelement.removeClass("ui-corner-top").addClass("ui-corner-all")}if(b){this.newelement.focus()}this.isOpen=false;this._trigger("close",c,this._uiHash())}},change:function(b){this.element.trigger("change");this._trigger("change",b,this._uiHash())},select:function(b){if(this._disabled(b.currentTarget)){return false}this._trigger("select",b,this._uiHash())},widget:function(){return this.listWrap.add(this.newelementWrap)},_closeOthers:function(b){a("."+this.widgetBaseClass+".ui-state-active").not(this.newelement).each(function(){a(this).data("selectelement").selectmenu("close",b)});a("."+this.widgetBaseClass+".ui-state-hover").trigger("mouseout")},_toggle:function(c,b){if(this.isOpen){this.close(c,b)}else{this.open(c)}},_formatText:function(b){if(this.options.format){b=this.options.format(b)}else{if(this.options.escapeHtml){b=a("<div />").text(b).html()}}return b},_selectedIndex:function(){return this.element[0].selectedIndex},_selectedOptionLi:function(){return this._optionLis.eq(this._selectedIndex())},_focusedOptionLi:function(){return this.list.find("."+this.widgetBaseClass+"-item-focus")},_moveSelection:function(e,b){if(!this.options.disabled){var d=parseInt(this._selectedOptionLi().data("index")||0,10);var c=d+e;if(c<0){c=0}if(c>this._optionLis.size()-1){c=this._optionLis.size()-1}if(c===b){return false}if(this._optionLis.eq(c).hasClass(this.namespace+"-state-disabled")){(e>0)?++e:--e;this._moveSelection(e,c)}else{this._optionLis.eq(c).trigger("mouseover").trigger("mouseup")}}},_moveFocus:function(f,b){if(!isNaN(f)){var e=parseInt(this._focusedOptionLi().data("index")||0,10);var d=e+f}else{var d=parseInt(this._optionLis.filter(f).data("index"),10)}if(d<0){d=0}if(d>this._optionLis.size()-1){d=this._optionLis.size()-1}if(d===b){return false}var c=this.widgetBaseClass+"-item-"+Math.round(Math.random()*1000);this._focusedOptionLi().find("a:eq(0)").attr("id","");if(this._optionLis.eq(d).hasClass(this.namespace+"-state-disabled")){(f>0)?++f:--f;this._moveFocus(f,d)}else{this._optionLis.eq(d).find("a:eq(0)").attr("id",c).focus()}this.list.attr("aria-activedescendant",c)},_scrollPage:function(c){var b=Math.floor(this.list.outerHeight()/this._optionLis.first().outerHeight());b=(c=="up"?-b:b);this._moveFocus(b)},_setOption:function(b,c){this.options[b]=c;if(b=="disabled"){if(c){this.close()}this.element.add(this.newelement).add(this.list)[c?"addClass":"removeClass"](this.widgetBaseClass+"-disabled "+this.namespace+"-state-disabled").attr("aria-disabled",c)}},disable:function(b,c){if(typeof(b)=="undefined"){this._setOption("disabled",true)}else{if(c=="optgroup"){this._disableOptgroup(b)}else{this._disableOption(b)}}},enable:function(b,c){if(typeof(b)=="undefined"){this._setOption("disabled",false)}else{if(c=="optgroup"){this._enableOptgroup(b)}else{this._enableOption(b)}}},_disabled:function(b){return a(b).hasClass(this.namespace+"-state-disabled")},_disableOption:function(b){var c=this._optionLis.eq(b);if(c){c.addClass(this.namespace+"-state-disabled").find("a").attr("aria-disabled",true);this.element.find("option").eq(b).attr("disabled","disabled")}},_enableOption:function(b){var c=this._optionLis.eq(b);if(c){c.removeClass(this.namespace+"-state-disabled").find("a").attr("aria-disabled",false);this.element.find("option").eq(b).removeAttr("disabled")}},_disableOptgroup:function(c){var b=this.list.find("li."+this.widgetBaseClass+"-group-"+c);if(b){b.addClass(this.namespace+"-state-disabled").attr("aria-disabled",true);this.element.find("optgroup").eq(c).attr("disabled","disabled")}},_enableOptgroup:function(c){var b=this.list.find("li."+this.widgetBaseClass+"-group-"+c);if(b){b.removeClass(this.namespace+"-state-disabled").attr("aria-disabled",false);this.element.find("optgroup").eq(c).removeAttr("disabled")}},index:function(b){if(arguments.length){if(!this._disabled(a(this._optionLis[b]))){this.element[0].selectedIndex=b;this._refreshValue()}else{return false}}else{return this._selectedIndex()}},value:function(b){if(arguments.length){this.element[0].value=b;this._refreshValue()}else{return this.element[0].value}},_refreshValue:function(){var d=(this.options.style=="popup")?" ui-state-active":"";var c=this.widgetBaseClass+"-item-"+Math.round(Math.random()*1000);this.list.find("."+this.widgetBaseClass+"-item-selected").removeClass(this.widgetBaseClass+"-item-selected"+d).find("a").attr("aria-selected","false").attr("id","");this._selectedOptionLi().addClass(this.widgetBaseClass+"-item-selected"+d).find("a").attr("aria-selected","true").attr("id",c);var b=(this.newelement.data("optionClasses")?this.newelement.data("optionClasses"):"");var e=(this._selectedOptionLi().data("optionClasses")?this._selectedOptionLi().data("optionClasses"):"");this.newelement.removeClass(b).data("optionClasses",e).addClass(e).find("."+this.widgetBaseClass+"-status").html(this._selectedOptionLi().find("a:eq(0)").html());this.list.attr("aria-activedescendant",c)},_refreshPosition:function(){var d=this.options;if(d.style=="popup"&&!d.positionOptions.offset){var c=this._selectedOptionLi();var b="0 "+(this.list.offset().top-c.offset().top-(this.newelement.outerHeight()+c.outerHeight())/2)}this.listWrap.zIndex(this.element.zIndex()+1).position({of:d.positionOptions.of||this.newelement,my:d.positionOptions.my,at:d.positionOptions.at,offset:d.positionOptions.offset||b,collision:d.positionOptions.collision||d.style=="popup"?"fit":"flip"})}})})(jQuery);
		</script>
		<style type="text/css">
		</style>
		<?php
	}
}
add_action('init',array('WP_FullCalendar','init'), 100);

// action links
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'wpfc_settings_link', 10, 1);
function wpfc_settings_link($links) {
	$new_links = array(); //put settings first
	$new_links[] = '<a href="'.admin_url('options-general.php?page=wp-fullcalendar').'">'.__('Settings', 'wpfc').'</a>';
	return array_merge($new_links,$links);
}

//translations
load_plugin_textdomain('wpfc', false, dirname( plugin_basename( __FILE__ ) ).'/includes/langs');