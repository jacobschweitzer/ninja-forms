<?php if ( ! defined( 'ABSPATH' ) ) exit;

class NF_AJAX_Controllers_Form extends NF_Abstracts_Controller
{
    public function __construct()
    {
        add_action( 'wp_ajax_nf_save_form',   array( $this, 'save' )   );
        add_action( 'wp_ajax_nf_delete_form', array( $this, 'delete' ) );
    }

    public function save()
    {
        check_ajax_referer( 'ninja_forms_ajax_nonce', 'security' );

        if( ! isset( $_POST[ 'form' ] ) ){
            $this->_errors[] = 'Form Not Found';
            $this->_respond();
        }

        $form_data = json_decode( stripslashes( $_POST['form'] ), ARRAY_A );

        $form = Ninja_Forms()->form( $form_data[ 'id' ] );

        $form->update_settings( $form_data[ 'settings' ] )->save();

        if( $form->get_tmp_id() ){

            $tmp_id = $form->get_tmp_id();
            $this->_data[ 'new_ids' ][ 'forms' ][ $tmp_id ] = $form->get_id();
        }

        foreach( $form_data[ 'fields' ] as $field_data ){
            $field = Ninja_Forms()->form()->get_field( $field_data[ 'id' ] );
            $field->update_settings( $field_data[ 'settings' ] )->save();

            if( $field->get_tmp_id() ){

                $tmp_id = $field->get_tmp_id();
                $this->_data[ 'new_ids' ][ 'fields' ][ $tmp_id ] = $field->get_id();
            }
        }

        foreach( $form_data[ 'actions' ] as $action_data ){
            $action = Ninja_Forms()->form()->get_field( $action_data[ 'id' ] );
            $action->update_settings( $action_data[ 'settings' ] )->save();

            if( $action->get_tmp_id() ){

                $tmp_id = $action->get_tmp_id();
                $this->_data[ 'new_ids' ][ 'actions' ][ $tmp_id ] = $action->get_id();
            }
        }

        delete_user_option( get_current_user_id(), 'nf_form_preview_' . $form_data['id'] );

        $this->_respond();
    }

    public function delete()
    {
        check_ajax_referer( 'ninja_forms_ajax_nonce', 'security' );

        $this->_respond();
    }
}
