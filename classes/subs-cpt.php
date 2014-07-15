<?php
/**
 * Submission CPT.
 * This class adds our submission CPT and handles displaying submissions in the wp-admin.
 *
 * @package     Ninja Forms
 * @subpackage  Classes/Submissions
 * @copyright   Copyright (c) 2014, WPNINJAS
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.7
*/

class NF_Subs_CPT {

	var $form_id;

	/**
	 * Get things started
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	function __construct() {
		// Register our submission custom post type.
		add_action( 'init', array( $this, 'register_cpt' ) );

		// Listen for the "download all" button.
		add_action( 'load-edit.php', array( $this, 'export_listen' ) );

		// Populate our field settings var
		add_action( 'current_screen', array( $this, 'setup_fields' ) );

		// Filter our hidden columns by form ID.
		add_action( 'wp', array( $this, 'filter_hidden_columns' ) );

		// Add our submenu for the submissions page.
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 11 );

		// Change our submission columns.
		add_filter( 'manage_nf_sub_posts_columns', array( $this, 'change_columns' ) );

		// Make our columns sortable.
		add_filter( 'manage_edit-nf_sub_sortable_columns', array( $this, 'sortable_columns' ) );
		// Actually do the sorting
		add_filter( 'request', array( $this, 'sort_columns' ) );

		// Add the appropriate data for our custom columns.
		add_action( 'manage_posts_custom_column', array( $this, 'custom_columns' ), 10, 2 );

		// Add our submission filters.
		add_action( 'restrict_manage_posts', array( $this, 'add_filters' ) );
		add_filter( 'parse_query', array( $this, 'table_filter' ) );
		add_filter( 'posts_clauses', array( $this, 'search' ), 20 );

		add_action( 'admin_footer', array( $this, 'jquery_remove_counts' ) );

		// Filter our post counts
		add_filter( 'wp_count_posts', array( $this, 'count_posts' ), 10, 3 );

		// Filter our bulk actions
		add_filter( 'bulk_actions-edit-nf_sub', array( $this, 'remove_bulk_edit' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'bulk_admin_footer' ) );

		// Filter our bulk updated/trashed messages
		add_filter( 'bulk_post_updated_messages', array( $this, 'updated_messages_filter' ), 10, 2 );

		// Filter singular updated/trashed messages
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );

		// Add our metabox for editing field values
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );

		// Save our metabox values
		add_action( 'save_post', array( $this, 'save_sub' ), 10, 2 );

		// Save our hidden columns by form id.
		add_action( 'wp_ajax_nf_hide_columns', array( $this, 'hide_columns' ) );
	}

	/**
	 * Register our submission CPT
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function register_cpt() {
		$labels = array(
		    'name' => _x('Submissions', 'post type general name' ),
		    'singular_name' => _x( 'Submission', 'post type singular name' ),
		    'add_new' => _x( 'Add New', 'nf_sub' ),
		    'add_new_item' => __( 'Add New Submission', 'ninja-forms' ),
		    'edit_item' => __( 'Edit Submission', 'ninja-forms' ),
		    'new_item' => __( 'New Submission', 'ninja-forms' ),
		    'view_item' => __( 'View Submission', 'ninja-forms' ),
		    'search_items' => __( 'Search Submissions', 'ninja-forms' ),
		    'not_found' =>  __( 'No Submissions Found', 'ninja-forms' ),
		    'not_found_in_trash' => __( 'No Submissions Found In The Trash', 'ninja-forms' ),
		    'parent_item_colon' => ''
	  	);

		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'_builtin' => false, // It's a custom post type, not built in!
			'query_var' => true,
			'capability_type' => 'post',
			'has_archive' => false,
			'show_in_menu' => false,
			// 'capabilities' => array(
		 //    	'create_posts' => false, // Removes support for the "Add New" function
			// ),
			'hierarchical' => false,
			'menu_events' => null,
			'rewrite' => array( 'slug' => 'nf_sub' ), // Permalinks format
			//'taxonomies' => array( 'novel_genre', 'novel_series', 'novel_author', 'post_tag'),
			'supports' => array( 'custom-fields' ),
		);

		register_post_type('nf_sub',$args);
	}

	/**
	 * Populate our fields var with all the fields. This keeps us from needing to ping the database later.
	 * 
	 * @access public
	 * @since 2.7
	 */
	public function setup_fields() {
		global $pagenow, $typenow;

		// Bail if we aren't on the edit.php page, we aren't editing our custom post type, or we don't have a form_id set.
		if ( ( $pagenow != 'edit.php' && $pagenow != 'post.php' ) || $typenow != 'nf_sub' )
			return false;

		if ( isset ( $_REQUEST['form_id'] ) ) {
			$form_id = $_REQUEST['form_id'];
		} else if ( isset ( $_REQUEST['post'] ) ) {
			$form_id = Ninja_Forms()->sub( $_REQUEST['post'] )->form_id;
		} else {
			$form_id = '';
		}

		$this->form_id = $form_id;

		Ninja_Forms()->form( $form_id );
	}

	/**
	 * Add our submissions submenu
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function add_submenu() {
		// Add our submissions submenu
		$sub_page = add_submenu_page( 'ninja-forms', __( 'Submissions', 'ninja-forms' ), __( 'Submissions', 'ninja-forms' ), apply_filters( 'nf_admin_menu_subs_capabilities', 'manage_options' ), 'edit.php?post_type=nf_sub'); 
		// Enqueue our JS on the edit page.
		//add_action( 'load-' . $sub_page, array( $this, 'load_js' ) );
		add_action( 'admin_print_styles', array( $this, 'load_js' ) );
		add_action( 'admin_print_styles', array( $this, 'load_css' ) );
		// Remove the publish box from the submission editing page.
		remove_meta_box( 'submitdiv', 'nf_sub', 'side' );
	}

	/**
	 * Enqueue our submissions JS file.
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function load_js() {
		global $pagenow;
		// Bail if we aren't on the edit.php page or we aren't editing our custom post type.
		if ( $pagenow != 'edit.php' || ! isset ( $_REQUEST['post_type'] ) || $_REQUEST['post_type'] != 'nf_sub' )
			return false;

		$form_id = isset ( $_REQUEST['form_id'] ) ? $_REQUEST['form_id'] : '';

		if ( defined( 'NINJA_FORMS_JS_DEBUG' ) && NINJA_FORMS_JS_DEBUG ) {
			$suffix = '';
			$src = 'dev';
		} else {
			$suffix = '.min';
			$src = 'min';
		}

		$suffix = '';
		$src = 'dev';

		$plugin_settings = nf_get_settings();
		$date_format = ninja_forms_date_to_datepicker( $plugin_settings['date_format'] );

		wp_enqueue_script( 'subs-cpt',
			NF_PLUGIN_URL . 'assets/js/' . $src .'/subs-cpt' . $suffix . '.js',
			array('jquery', 'jquery-ui-datepicker') );

		wp_localize_script( 'subs-cpt', 'nf_sub', array( 'form_id' => $form_id, 'date_format' => $date_format ) );

	}

	/**
	 * Enqueue our submissions CSS file.
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function load_css() {
		global $pagenow;
		// Bail if we aren't on the edit.php page or the post.php page.
		if ( $pagenow != 'edit.php' && $pagenow != 'post.php' )
			return false;

		wp_enqueue_style( 'nf-sub', NF_PLUGIN_URL .'assets/css/cpt.css');
	}

	/**
	 * Modify the columns of our submissions table.
	 * 
	 * @access public
	 * @since 2.7
	 * @return array $cols
	 */
	public function change_columns( $cols ) {
		// Compatibility with old field registration system. Can be removed when the new one is in place.
		global $ninja_forms_fields;
		// End Compatibility

		$cols = array(
			'cb'    => '<input type="checkbox" />',
			'id' => __( 'ID', 'ninja-forms' ),
		);
		/*
		 * This section uses the new Ninja Forms db structure. Until that is utilized, we must deal with the old db.
		if ( isset ( $_GET['form_id'] ) ) {
			$form_id = $_GET['form_id'];
			$fields = nf_get_fields_by_form_id( $form_id );
			if ( is_array ( $fields ) ) {
				foreach ( $fields as $field_id => $setting ) {
					if ( apply_filters( 'nf_add_sub_value', Ninja_Forms()->field( $field_id )->type->add_to_sub, $field_id ) )
						$cols[ 'form_' . $form_id . '_field_' . $field_id ] = $setting['label'];
				}
			}
		}		
		*/

		// Compatibility with old field registration system. Can be removed when the new one is in place.
		if ( isset ( $_GET['form_id'] ) ) {
			$form_id = $_GET['form_id'];
			if ( is_array ( Ninja_Forms()->form( $this->form_id )->fields ) ) {
				foreach ( Ninja_Forms()->form( $this->form_id )->fields as $field ) {
					$field_id = $field['id'];
					$field_type = $field['type'];
					if ( isset ( $ninja_forms_fields[ $field_type ] ) ) {
						$reg_field = $ninja_forms_fields[ $field_type ];
						$process_field = $reg_field['process_field'];
					} else {
						$process_field = false;
					}
					if ( isset ( $field['data']['admin_label'] ) && ! empty ( $field['data']['admin_label'] ) ) {
						$label = $field['data']['admin_label'];
					} else if ( isset ( $field['data']['label'] ) ) {
						$label = $field['data']['label'];
					} else {
						$label = '';
					}

					if ( strlen( $label ) > 140 )
						$label = substr( $label, 0, 140 );

					if ( isset ( $field['data']['label'] ) && $process_field )
						$cols[ 'form_' . $form_id . '_field_' . $field_id ] = $label;
				}
			}
		}
		// End Compatibility
		// Add our date column
		$cols['sub_date'] = __( 'Date', 'ninja-forms' );

		return $cols;
	}

	/**
	 * Make our columns sortable
	 * 
	 * @access public
	 * @since 2.7
	 * @return array
	 */
	public function sortable_columns() {
		// Get a list of all of our fields.
		$columns = get_column_headers( 'edit-nf_sub' );
		$tmp_array = array();
		foreach ( $columns as $slug => $c ) {
			if ( $slug != 'cb' ) {
				$tmp_array[ $slug ] = $slug;				
			}
		}
		return $tmp_array;
	}

	/**
	 * Actually sort our columns
	 * 
	 * @access public
	 * @since 2.7
	 * @return array $vars
	 */
	public function sort_columns( $vars ) {
		if( array_key_exists( 'orderby', $vars ) ) {
           if( strpos( $vars['orderby'], 'form_' ) !== false ) {
           		$args = explode( '_', $vars['orderby'] );
           		$field_id = $args[3];

           		if ( isset ( Ninja_Forms()->form( $this->form_id )->fields[ $field_id ]['data']['num_sort'] ) && Ninja_Forms()->form( $this->form_id )->fields[ $field_id ]['data']['num_sort'] == 1 ) {
           			$orderby = 'meta_value_num';
           		} else {
           			$orderby = 'meta_value';
           		}

                $vars['orderby'] = $orderby;
                $vars['meta_key'] = '_field_' . $field_id;
           }
		}
		return $vars;
	}

	/**
	 * Add our custom column data
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function custom_columns( $column, $sub_id ) {
		if ( isset ( $_GET['form_id'] ) ) {
			$form_id = $_GET['form_id'];
			switch( $column ) {
				case 'id':
					echo Ninja_Forms()->sub( $sub_id )->get_seq_id();
					echo '<div class="locked-info"><span class="locked-avatar"></span> <span class="locked-text"></span></div>';
					if ( !isset ( $_GET['post_status'] ) || $_GET['post_status'] == 'all' ) {
						echo '<div class="row-actions">
							<span class="edit"><a href="post.php?post=' . $sub_id . '&action=edit&ref=' . urlencode( add_query_arg( array() ) ) . '" title="' . __( 'Edit this item', 'ninja-forms' ) . '">Edit</a> | </span> 
							<span class="edit"><a href="' . add_query_arg( array( 'export_single' => $sub_id ) ) . '" title="' . __( 'Export this item', 'ninja-forms' ) . '">' . __( 'Export', 'ninja-forms' ) . '</a> | </span>  
							<span class="trash"><a class="submitdelete" title="' . __( 'Move this item to the Trash', 'ninja-forms' ) . '" href="' . get_delete_post_link( $sub_id ) . '">Trash</a> | </span>
							
							</div>';
					} else {
						echo '<div class="row-actions"><span class="untrash"><a title="' . esc_attr( __( 'Restore this item from the Trash' ) ) . '" href="' . wp_nonce_url( sprintf( get_edit_post_link( $sub_id ) . '&amp;action=untrash', $sub_id ) , 'untrash-post_' . $sub_id ) . '">' . __( 'Restore' ) . '</a> | </span> <span class="delete"><a class="submitdelete" title="' . esc_attr( __( 'Delete this item permanently' ) ) . '" href="' . get_delete_post_link( $sub_id, '', true ) . '">' . __( 'Delete Permanently' ) . '</a></span></div>';
					}

				break;
				case 'sub_date':
					$post = get_post( $sub_id );
					$mode = empty( $_REQUEST['mode'] ) ? 'list' : $_REQUEST['mode'];

					if ( '0000-00-00 00:00:00' == $post->post_date ) {
						$t_time = $h_time = __( 'Unpublished' );
						$time_diff = 0;
					} else {
						$t_time = get_the_time( __( 'Y/m/d g:i:s A' ) );
						$m_time = $post->post_date;
						$time = get_post_time( 'G', true, $post );

						$time_diff = time() - $time;

						if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS )
							$h_time = sprintf( __( '%s ago' ), human_time_diff( $time ) );
						else
							$h_time = mysql2date( __( 'Y/m/d' ), $m_time );
					}
					
					if ( 'excerpt' == $mode ) {
						echo $t_time;
					} else {
						/** This filter is documented in wp-admin/includes/class-wp-posts-list-table.php */
						echo '<abbr title="' . $t_time . '">' . $h_time . '</abbr>';
					}
					echo '<br />';
					if ( 'publish' == $post->post_status ) {
						_e( 'Submitted', 'ninja-forms' );
					} else {
						_e( 'Last Modified' );
					}
					
				break;
				default:
					$field_id = str_replace( 'form_' . $form_id . '_field_', '', $column );
					//if ( apply_filters( 'nf_add_sub_value', Ninja_Forms()->field( $field_id )->type->add_to_sub, $field_id ) ) {
						$user_value = Ninja_Forms()->sub( $sub_id )->get_field( $field_id );
						if ( is_array ( $user_value ) ) {
							echo '<ul>';
							$max_items = apply_filters( 'nf_sub_table_user_value_max_items', 3, $field_id );
							$x = 0;

							while ( $x < $max_items && $x <= count( $user_value ) - 1 ) {
								echo '<li>' . $user_value[$x] . '</li>';
								$x++;
							}							
							echo '</ul>';
						} else {
							// Cut down our string if it is longer than 140 characters.
							$max_len = apply_filters( 'nf_sub_table_user_value_max_len', 140, $field_id );
							if ( strlen( $user_value ) > 140 )
								$user_value = substr( $user_value, 0, 140 );

							echo nl2br( $user_value );							
						}

					//}		
				break;
			}
		}
	}

	/**
	 * Add our submission filters
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function add_filters() {
		global $typenow;

		// Bail if we aren't in our submission custom post type.
		if ( $typenow != 'nf_sub' )
			return false;

		/*
		// Bail if we are looking at the trashed submissions.
		if ( isset ( $_REQUEST['post_status'] ) && $_REQUEST['post_status'] == 'trash' )
			return false;
		*/

		/*
		 * This section uses the new database structure for Ninja Forms. Until that structure is in place, we have to get data from the old db.

		// Get our list of forms
		$forms = nf_get_all_forms();

		$form_id = isset( $_GET['form_id'] ) ? $_GET['form_id'] : '';

 		$html = '<select name="form_id" id="form_id">';
		$html .= '<option value="">- Select a form</option>';
		if ( is_array( $forms ) ) {
			foreach ( $forms as $form ) {
				$html .= '<option value="' . $form['id'] . '" ' . selected( $form['id'], $form_id, false ) . '>' . nf_get_form_setting( $form['id'], 'name' ) . '</option>';
			}
		}
		$html .= '</select>';
		echo $html;		
		*/

		$begin_date = isset ( $_GET['begin_date'] ) ? $_GET['begin_date'] : '';
		$end_date = isset ( $_GET['end_date'] ) ? $_GET['end_date'] : '';

		// Add begin date and end date filter fields.
		$html = '<div style="float:left;">';
		$html .= '<input name="begin_date" type="text" class="datepicker" placeholder="' . __( 'Begin Date', 'ninja-forms' ) . '" value="' . $begin_date . '" /> ';
		$html .= '<input name="end_date" type="text" class="datepicker" placeholder="' . __( 'End Date', 'ninja-forms' ) . '" value="' . $end_date . '" />';
		$html .= '</div>';

		// Add our Form selection dropdown.
		// Get our list of forms
		$forms = ninja_forms_get_all_forms();

		$form_id = isset( $_GET['form_id'] ) ? $_GET['form_id'] : '';

 		$html .= '<select name="form_id" id="form_id">';
		$html .= '<option value="">- Select a form</option>';
		if ( is_array( $forms ) ) {
			foreach ( $forms as $form ) {
				$html .= '<option value="' . $form['id'] . '" ' . selected( $form['id'], $form_id, false ) . '>' . $form['data']['form_title'] . '</option>';
			}
		}
		$html .= '</select>';

		if ( isset ( $_REQUEST['post_status'] ) && $_REQUEST['post_status'] == 'all' ) {
			// Add our "Download All" button.
			$html .= '<input type="submit" name="submit" class="download-all button-secondary" style="float:right;" value="' . __( 'Download All', 'ninja-forms' ) . '" />';
		}

		echo $html;
	}

	/**
	 * Filter our submission list by form_id
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function table_filter( $query ) {

		if( is_admin() AND $query->query['post_type'] == 'nf_sub' ) {

		    $qv = &$query->query_vars;

		    if( !empty( $_GET['form_id'] ) ) {
		    	$form_id = $_GET['form_id'];
		    } else {
		    	$form_id = 0;
		    }

		    $plugin_settings = nf_get_settings();
		    $date_format = $plugin_settings['date_format'];

		    if ( !empty ( $_GET['begin_date'] ) ) {
		    	$begin_date = $_GET['begin_date'];
				if ( $date_format == 'd/m/Y' ) {
					$begin_date = str_replace( '/', '-', $begin_date );
				} else if ( $date_format == 'm-d-Y' ) {
					$begin_date = str_replace( '-', '/', $begin_date );
				}
				$begin_date .= '00:00:00';
				$begin_date = new DateTime( $begin_date );
				$begin_date = $begin_date->format("Y-m-d G:i:s");
		    } else {
		    	$begin_date = '';
		    }

			if ( !empty ( $_GET['end_date'] ) ) {
		    	$end_date = $_GET['end_date'];
			    if ( $date_format == 'd/m/Y' ) {
					$end_date = str_replace( '/', '-', $end_date );
				} else if ( $date_format == 'm-d-Y' ) {
					$end_date = str_replace( '-', '/', $end_date );
				}
				$end_date .= '23:59:59';
				$end_date = new DateTime( $end_date );
				$end_date = $end_date->format("Y-m-d G:i:s");
		    } else {
		    	$end_date = '';
		    }

		    $qv['date_query'] = array(
		    	'after' => $begin_date,
		    	'before' => $end_date,
		    );

		    $qv['meta_query'] = array(
		    	array(
		    		'key' => '_form_id',
		    		'value' => $form_id,
		    		'compare' => '=',
		    	),
		    );
		}
	}

	/**
	 * Filter our search
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function search( $pieces ) {
		global $typenow;
	    // filter to select search query
	    if ( is_search() && is_admin() && $typenow == 'nf_sub' && isset ( $_GET['s'] ) ) { 
	        global $wpdb;

	        $keywords = explode(' ', get_query_var('s'));
	        $query = "";

	        foreach ($keywords as $word) {

	             $query .= " (mypm1.meta_value  LIKE '%{$word}%') OR ";
	         }

	        if (!empty($query)) {
	            // add to where clause
	            $pieces['where'] = str_replace("(((wp_posts.post_title LIKE '%", "( {$query} ((wp_posts.post_title LIKE '%", $pieces['where']);

	            $pieces['join'] = $pieces['join'] . " INNER JOIN {$wpdb->postmeta} AS mypm1 ON ({$wpdb->posts}.ID = mypm1.post_id)";
	        }
	    }
	    return ($pieces);
	}

	/**
	 * Filter our bulk updated/trashed messages so that it uses "submission" rather than "post"
	 * 
	 * @access public
	 * @since 2.7
	 * @return array $bulk_messages
	 */
	public function updated_messages_filter( $bulk_messages, $bulk_counts ) {
	    $bulk_messages['nf_sub'] = array(
	        'updated'   => _n( '%s submission updated.', '%s submissions updated.', $bulk_counts['updated'] ),
	        'locked'    => _n( '%s submission not updated, somebody is editing it.', '%s submissions not updated, somebody is editing them.', $bulk_counts['locked'] ),
	        'deleted'   => _n( '%s submission permanently deleted.', '%s submissions permanently deleted.', $bulk_counts['deleted'] ),
	        'trashed'   => _n( '%s submission moved to the Trash.', '%s submissions moved to the Trash.', $bulk_counts['trashed'] ),
	        'untrashed' => _n( '%s submission restored from the Trash.', '%s submissions restored from the Trash.', $bulk_counts['untrashed'] ),
	    );

	    return $bulk_messages;
	}

	/**
	 * Filter our updated/trashed post messages
	 * 
	 * @access public
	 * @since 2.7
	 * @return array $messages
	 */
	function post_updated_messages( $messages ) {

		global $post, $post_ID;
		$post_type = get_post_type( $post_ID );

		$obj = get_post_type_object( $post_type );
		$singular = $obj->labels->singular_name;

		$messages[$post_type] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => $singular . ' ' . __( 'updated', 'ninja-forms' ) . '.',
			2 => __('Custom field updated.'),
			3 => __('Custom field deleted.'),
			4 => __($singular.' updated.'),
			5 => isset($_GET['revision']) ? sprintf( __($singular.' restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __($singular.' published. <a href="%s">View '.strtolower($singular).'</a>'), esc_url( get_permalink($post_ID) ) ),
			7 => __('Page saved.'),
			8 => sprintf( __($singular.' submitted. <a target="_blank" href="%s">Preview '.strtolower($singular).'</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
			9 => sprintf( __($singular.' scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview '.strtolower($singular).'</a>'), date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
			10 => sprintf( __($singular.' draft updated. <a target="_blank" href="%s">Preview '.strtolower($singular).'</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
		);

		return $messages;
	}

	/**
	 * Remove the 'edit' bulk action
	 * 
	 * @access public
	 * @since 2.7
	 * @return array $actions
	 */
	public function remove_bulk_edit( $actions ) {
		unset( $actions['edit'] );
		return $actions;
	}

	/**
	 * Add our "export" bulk action
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function bulk_admin_footer() {
		global $post_type;
 
		if( $post_type == 'nf_sub' && isset ( $_REQUEST['post_status'] ) && $_REQUEST['post_status'] == 'all' ) {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery('<option>').val('export').text('<?php _e('Export')?>').appendTo("select[name='action']");
					jQuery('<option>').val('export').text('<?php _e('Export')?>').appendTo("select[name='action2']");
				});
			</script>
			<?php
		}
	}

	/**
	 * jQuery that hides some of our post-related page items.
	 * Also adds the active class to All and Trash links, and changes those
	 * links to match the current filter.
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function jquery_remove_counts() {
		global $typenow, $pagenow;
		if ( $typenow == 'nf_sub' && $pagenow == 'edit.php' ) {
			if ( ! isset ( $_GET['post_status'] ) || $_GET['post_status'] == 'all' ) {
				$active = 'all';
			} else if ( $_GET['post_status'] == 'trash' ) {
				$active = 'trash';
			}

			$all_url = add_query_arg( array( 'post_status' => 'all' ) );
			$all_url = remove_query_arg( 's', $all_url );
			$trash_url = add_query_arg( array( 'post_status' => 'trash' ) );
			$trash_url = remove_query_arg( 's', $trash_url );
			if ( isset ( $_GET['form_id'] ) ) {
				$trashed_sub_count = nf_get_sub_count( $_GET['form_id'], 'trash' );	
			} else {
				$trashed_sub_count = 0;
			}

			?>
			<script type="text/javascript">
				jQuery(function(){
					jQuery( "li.all" ).find( "a" ).attr( "href", "<?php echo $all_url; ?>" );
					jQuery( "li.<?php echo $active; ?>" ).addClass( "current" );
					jQuery( "li.<?php echo $active; ?>" ).find( "a" ).addClass( "current" );
					jQuery( "li.trash" ).find( "a" ).attr( "href", "<?php echo $trash_url; ?>" );
					jQuery( ".view-switch" ).remove();
					<?php
					if ( $trashed_sub_count == 0 ) {
						?>
						var text = jQuery( "li.all" ).prop( "innerHTML" );
						text = text.replace( " |", "" );
						jQuery( "li.all" ).prop( "innerHTML", text );
						<?php
					}
					?>
				});
			</script>

			<style>
				.add-new-h2 {
					display:none;
				}
				li.publish {
					display:none;
				}
				select[name=m] {
					display:none;
				}
			</style>
			<?php			
		} else if ( $typenow == 'nf_sub' && $pagenow == 'post.php' ) {
			if ( isset ( $_REQUEST['ref'] ) ) {
				$back_url = urldecode( $_REQUEST['ref'] );
			} else {
				$back_url = '';
			}
			?>
			<script type="text/javascript">
				jQuery(function(){
					var html = '<a href="<?php echo $back_url; ?>" class="back"><?php _e( 'Back to list', 'ninja-forms' ); ?></a>';
					console.log( html );
					jQuery( 'div.wrap' ).children( 'h2:first' ).append( html );
				});
			</script>
			<style>
				.add-new-h2 {
					display:none;
				}
			</style>	

			<?php
		}
	}

	/**
	 * Filter our post counts for the submission listing page
	 * 
	 * @access public
	 * @since 2.7
	 * @return int $count
	 */
	public function count_posts( $count, $post_type, $perm ) {
		
		// Bail if we aren't working with our custom post type.
		if ( $post_type != 'nf_sub' )
			return $count;

		if ( isset ( $_GET['form_id'] ) ) {
			$sub_count = nf_get_sub_count( $_GET['form_id'] );
			$trashed_sub_count = nf_get_sub_count( $_GET['form_id'], 'trash' );
			$count->publish = $sub_count;
			$count->trash = $trashed_sub_count;
		} else {
			$count->publish = 0;
			$count->trash = 0;
		}

		return $count;
	}

	/**
	 * Add our field editing metabox to the CPT editing page.
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function add_metaboxes() {
		// Remove the 'custom fields' metabox from our CPT edit page
		remove_meta_box( 'postcustom', 'nf_sub', 'normal' );
		// Remove the 'slug' metabox from our CPT edit page.
		remove_meta_box( 'slugdiv', 'nf_sub', 'normal' );
		// Add our field editing metabox.
		add_meta_box( 'nf_fields', __( 'User Submitted Values', 'ninja-forms' ), array( $this, 'edit_sub_metabox' ), 'nf_sub', 'normal', 'default');
		// Add our save field values metabox
		add_meta_box( 'nf_fields_save', __( 'Submission Stats', 'ninja-forms' ), array( $this, 'save_sub_metabox' ), 'nf_sub', 'side', 'default');

	}

	/**
	 * Output our field editing metabox to the CPT editing page.
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function edit_sub_metabox( $post ) {
		global $ninja_forms_fields;
		// Get all the post meta
		$fields = Ninja_Forms()->sub( $post->ID )->get_all_fields();
		$form_id = Ninja_Forms()->sub( $post->ID )->form_id;

		?>
		<div id="postcustomstuff">
			<table id="list-table">
				<thead>
					<tr>
						<th class="left"><?php _e( 'Field', 'ninja-forms' ); ?></th>
						<th><?php _e( 'Value', 'ninja-forms' ); ?></th>
					</tr>
				</thead>
				<tbody id="the-list">
					<?php
					// Loop through our post meta and keep our field values
					foreach ( $fields as $field_id => $user_value ) {

						$field = Ninja_Forms()->form( $this->form_id )->fields[ $field_id ];
						$field_type = $field['type'];

						if ( isset ( $field['data']['admin_label'] ) && $field['data']['admin_label'] != '' ) {
							$label = $field['data']['admin_label'];
						} else if ( isset ( $field['data']['label'] ) ) {
							$label = $field['data']['label'];
						} else {
							$label = '';
						}

						if ( isset ( $ninja_forms_fields[ $field_type ] ) ) {
							$reg_field = $ninja_forms_fields[ $field_type ];
							$process_field = $reg_field['process_field'];
						} else {
							$process_field = false;
						}

						if ( isset ( Ninja_Forms()->form( $this->form_id )->fields[ $field_id ] ) && $process_field ) {
							?>
							<tr>
								<td class="left"><?php echo $label; ?></td>
								<td>
								<?php
									if ( isset ( $reg_field['edit_sub_value'] ) ) {
										$edit_value_function = $reg_field['edit_sub_value'];
									} else {
										$edit_value_function = 'nf_field_text_edit_sub_value';
									}
									$args['field_id'] = $field_id;
									$args['user_value'] = $user_value;
									$args['field'] = $field;

									call_user_func_array( $edit_value_function, $args );

								?>
								</td>
							</tr>
							<?php
						}

					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Output our field editing metabox to the CPT editing page.
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function save_sub_metabox( $post ) {
		$date_submitted = date( 'M j, Y @ h:i', strtotime( $post->post_date ) );
		$date_modified = date( 'M j, Y @ h:i', strtotime( $post->post_modified ) );
		$user_data = get_userdata( $post->post_author );
		$first_name = $user_data->first_name;
		$last_name = $user_data->last_name;
		$form_id = Ninja_Forms()->sub( $post->ID )->form_id;
		$form = ninja_forms_get_form_by_id( $form_id );
		$form_title = $form['data']['form_title'];
		?>
		<input type="hidden" name="nf_edit_sub" value="1">
		<div class="submitbox" id="submitpost">
			<div id="minor-publishing">
				<div id="misc-publishing-actions">
					<div class="misc-pub-section misc-pub-post-status">
						<label for="post_status"><?php _e( 'ID', 'ninja-forms' ); ?>:</label>
						<span id="post-status-display"><?php echo Ninja_Forms()->sub( $post->ID )->get_seq_id(); ?></span>
					</div>
					<div class="misc-pub-section misc-pub-post-status">
						<label for="post_status"><?php _e( 'Status', 'ninja-forms' ); ?>:</label>
						<span id="post-status-display"><?php _e( 'Complete', 'ninja-forms' ); ?></span>
					</div>
					<div class="misc-pub-section misc-pub-post-status">
						<label for="post_status"><?php _e( 'Form', 'ninja-forms' ); ?>:</label>
						<span id="post-status-display"><?php echo $form_title; ?></span>
					</div>
					<div class="misc-pub-section curtime misc-pub-curtime">
						<span id="timestamp">
							<?php _e( 'Submitted on', 'ninja-forms' ); ?>: <b><?php echo $date_submitted; ?></b>
						</span>
					</div>
					<div class="misc-pub-section curtime misc-pub-curtime">
						<span id="timestamp">
							<?php _e( 'Modified on', 'ninja-forms' ); ?>: <b><?php echo $date_modified; ?></b>
						</span>
					</div>
					<div class="misc-pub-section misc-pub-visibility" id="visibility">
						<?php _e( 'Submitted By', 'ninja-forms' ); ?>: <span id="post-visibility-display"><?php echo $first_name; ?> <?php echo $last_name; ?></span>
					</div>
				</div>
			</div>
			<div id="major-publishing-actions">
				<div id="delete-action">
				<a class="submitdelete deletion" href="http://localhost/wp-dev/wp-admin/post.php?post=296&amp;action=trash&amp;_wpnonce=604c2e6a4c">Move to Trash</a></div>

				<div id="publishing-action">
				<span class="spinner"></span>
						<input name="original_publish" type="hidden" id="original_publish" value="Update">
						<input name="save" type="submit" class="button button-primary button-large" id="publish" accesskey="p" value="Update">
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save our submission user values
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function save_sub( $sub_id, $post ) {
		global $pagenow;

		if ( ! isset ( $_POST['nf_edit_sub'] ) || $_POST['nf_edit_sub'] != 1 )
			return $sub_id;

		// verify if this is an auto save routine.
		// If it is our form has not been submitted, so we dont want to do anything
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		  return $sub_id;

		if ( $pagenow != 'post.php' )
			return $sub_id;

		if ( $post->post_type != 'nf_sub' )
			return $sub_id;

		/* Get the post type object. */
		$post_type = get_post_type_object( $post->post_type );

		/* Check if the current user has permission to edit the post. */
		if ( !current_user_can( $post_type->cap->edit_post, $sub_id ) )
	    	return $sub_id;

	    foreach ( $_POST['fields'] as $field_id => $user_value ) {
	    	Ninja_Forms()->sub( $sub_id )->update_field( $field_id, $user_value );
	    }
	}

	/**
	 * Filter our hidden columns so that they are handled on a per-form basis.
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function filter_hidden_columns() {
		global $pagenow;
		// Bail if we aren't on the edit.php page, we aren't editing our custom post type, or we don't have a form_id set.
		if ( $pagenow != 'edit.php' || ! isset ( $_REQUEST['post_type'] ) || $_REQUEST['post_type'] != 'nf_sub' || ! isset ( $_REQUEST['form_id'] ) )
			return false;

		// Grab our current user.
		$user = wp_get_current_user();
		// Grab our form id.
		$form_id = $_REQUEST['form_id'];
		// Get the columns that should be hidden for this form ID.
		$hidden_columns = get_user_option( 'manageedit-nf_subcolumnshidden-form-' . $form_id );
		
		if ( $hidden_columns === false ) {
			// If we don't have custom hidden columns set up for this form, then only show the first five columns.
			// Get our column headers
			$columns = get_column_headers( 'edit-nf_sub' );
			$hidden_columns = array();
			$x = 0;
			foreach ( $columns as $slug => $name ) {
				if ( $x > 5 ) {
					if ( $slug != 'sub_date' )
						$hidden_columns[] = $slug;
				}
				$x++;
			}
		}
		update_user_option( $user->ID, 'manageedit-nf_subcolumnshidden', $hidden_columns, true );
	}

	/**
	 * Save our hidden columns per form id.
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function hide_columns() {
		// Grab our current user.
		$user = wp_get_current_user();
		// Grab our form id.
		$form_id = $_REQUEST['form_id'];
		$hidden = isset( $_POST['hidden'] ) ? explode( ',', $_POST['hidden'] ) : array();
		$hidden = array_filter( $hidden );
		update_user_option( $user->ID, 'manageedit-nf_subcolumnshidden-form-' . $form_id, $hidden, true );
		die();
	}

	/**
	 * Download all submissions within a date range
	 * 
	 * @access public
	 * @since 2.7
	 * @return void
	 */
	public function export_listen() {

		if ( isset ( $_REQUEST['export_single'] ) && ! empty( $_REQUEST['export_single'] ) )
			Ninja_Forms()->sub( $_REQUEST['export_single'] )->export();

		if ( isset ( $_REQUEST['action'] ) && $_REQUEST['action'] == 'export' )
			Ninja_Forms()->subs()->export( $_REQUEST['post'] );

		if ( isset ( $_REQUEST['submit'] ) && $_REQUEST['submit'] == __( 'Download All', 'ninja-forms' ) && isset ( $_REQUEST['form_id'] ) ) {
			$subs = Ninja_Forms()->form( 241 )->get_subs();
		}
	}
}