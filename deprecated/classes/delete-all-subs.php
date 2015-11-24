<?php if ( ! defined( 'ABSPATH' ) ) exit;

class NF_Delete_All_Subs extends NF_Step_Processing {

	function __construct() {
		$this->action = 'delete_all_subs';

		parent::__construct();
	}

	public function loading() {

		$form_id  = isset( $this->args['form_id'] ) ? absint( $this->args['form_id'] ) : 0;

		if ( empty( $form_id ) ) {
			return array( 'complete' => true );
		}
			
	 	$sub_count = nf_get_sub_count( $form_id );

		if( empty( $this->total_steps ) || $this->total_steps <= 1 ) {
			$this->total_steps = round( ( $sub_count / 250 ), 0 ) + 2;
		}

		$args = array(
			'total_steps' => $this->total_steps,
		);

		update_user_option( get_current_user_id(), 'nf_delete_all_subs_filename', $this->args['filename'] );
		$this->redirect = esc_url_raw( add_query_arg( array( 'download_all' => $this->args['filename'] ), $this->args['redirect'] ) );

		return $args;
	}

	public function step() {
		
		$deleted_subs = get_user_option( get_current_user_id(), 'nf_delete_all_subs_ids' );
		if ( ! is_array( $deleted_subs ) ) {
			$deleted_subs = array();
		}

		$args = array(
			'posts_per_page' => 250,
			'paged' => $this->step,
			'post_type' => 'nf_sub',
			'meta_query' => array(
				array( 
					'key' => '_form_id',
					'value' => $this->args['form_id'],
				),
			),
		);

		$subs_results = get_posts( $args );

		if ( is_array( $subs_results ) && ! empty( $subs_results ) ) {
			$x = 0;
			foreach ( $subs_results as $sub ) {
				$sub_delete = Ninja_Forms()->sub( $sub->ID )->delete();

				if ( ! in_array( $sub->ID, $deleted_subs ) ) {
					$deleted_subs[] = $sub->ID;
				}
				$x++;
			}
		}
		update_user_option( get_current_user_id(), 'nf_delete_all_subs_ids', $deleted_subs );
	}

	public function complete() {
		delete_user_option( get_current_user_id(), 'nf_delete_all_subs_ids' );
		delete_user_option( get_current_user_id(), 'nf_delete_all_subs_filename' );
	}

}