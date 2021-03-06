<?php
/**
 * @author GovReady
 */

namespace Govready\GovreadyAgent;

use Govready;

class GovreadyAgent extends Govready\Govready {


  function __construct() {
    parent::__construct();

    // Define the ping trigger endpoint
    add_action( 'wp_ajax_govready_v1_trigger', array($this, 'ping') );
    add_action( 'wp_ajax_nopriv_govready_v1_trigger', array($this, 'ping') );

    // Save the user's last login timestamp
    add_action( 'wp_login', array($this, 'last_login_save'), 10, 2 );

  }

  /**
   * Generic callback for ?action=govready_v1_trigger&key&endpoint&siteId
   * Examples:
   * ?action=govready_v1_trigger&key=plugins&endpoint=plugins&siteId=xxx
   * ?action=govready_v1_trigger&key=accounts&endpoint=accounts&siteId=xxx
   * ?action=govready_v1_trigger&key=stack&endpoint=stack/phpinfo&siteId=xxx
   */
  public function ping() {
    // print_r($_POST);
    
    $this->validate_token();
    $options = get_option( 'govready_options' );
    if ( empty($options['siteId']) || $_POST['siteId'] == $options['siteId'] ) {
      if ( !empty( $_POST['key'] ) ) { 
        $key = $_POST['key'];
        $data = call_user_func( array( $this, $key ) );
        $options = get_option( 'govready_options' );
        if( empty( $options['siteId'] ) ) {
          print_r('Invalid siteId');
          return;
        }
        if ( !empty( $data ) ) {
          //print_r($data);
          if( !empty( $_POST['endpoint'] ) ) {
            $endpoint = '/sites/' . $options['siteId'] . '/' . $_POST['endpoint'];
            $return = parent::api( $endpoint, 'POST', $data );
            //print_r($return); // @todo: comment this out, also don't return data in API
          }
          // @TODO return meaningful information
          wp_send_json(array('response' => 'ok'));
        }
      }
    }
    else {
      print_r('Invalid siteId');
    }
  }


  // Callback for ?action=govready_v1_trigger&key=plugins
  private function plugins() {

    $out = array();
    $plugins = get_plugins();
    foreach ($plugins as $key => $plugin) {
      $namespace = explode('/', $key);
      array_push( $out, array(
        'label' => $plugin['Name'],
        'namespace' => $namespace[0],
        'status' => is_plugin_active($key),
        'version' => $plugin['Version'],
        'project_link' => !empty( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : ''
      ) );
    }
    return array( 'plugins' => $out, 'forceDelete' => true );

  }


  // Callback for ?action=govready_v1_trigger&key=accounts
  private function accounts() {
    $out = array();
    $fields = array( 'ID', 'user_login', 'user_email', 'user_nicename', 'user_registered', 'user_status' );
    $users = get_users( array( 
      'fields' => $fields,
      'role__not_in' => 'subscriber',
    ) );

    foreach ($users as $key => $user) {
      $roles = array();
      foreach (get_user_meta( $user->ID, 'wp_capabilities', true ) as $role => $value) {
        if ($value) {
          array_push($roles, $role);
        }
      }
      array_push( $out, array(
        'userId' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'name' => $user->user_nicename,
        'created' => strtotime( $user->user_registered ),
        'roles' => $roles,
        'superAdmin' => (bool)in_array('administrator', $roles),
        'lastLogin' => strtotime( get_user_meta( $user->ID, 'govready_last_login', true ) ),
      ) );
    }
    
    return array( 'accounts' => $out, 'forceDelete' => true );

  }


  // Callback for ?action=govready_v1_trigger&key=stack
  private function stack() {

    global $wp_version;
    $stack = array(
      'os' => php_uname( 's' ) .' '. php_uname( 'r' ),
      'language' => 'PHP ' . phpversion(),
      'server' => $_SERVER["SERVER_SOFTWARE"],
      'application' => array(
        'platform' => 'WordPress',
        'version' => $wp_version,
      ),
      'database' => function_exists('mysql_get_client_info') ? 'MySQL ' . mysql_get_client_info() : null,
    );

    return array( 'stack' => $stack );

  }


  // Callback for ?action=govready_v1_trigger&key=changeMode
  private function changeMode() {
    
    $options = get_option( 'govready_options', array() );
    // Should we update?
    $update_mode = !empty($_POST['mode']) &&
                 ( $_POST['mode'] === 'local'
                || $_POST['mode'] === 'remote'
                || $_POST['mode'] === 'preview' );
    if($update_mode) {
      $options['mode'] = $_POST['mode'];
  
      // If we don't have siteId and do have it in post, save
      if(empty($options['siteId']) && !empty($_POST['siteId'])) {
        $options['siteId'] = $_POST['siteId'];
      }

      update_option( 'govready_options', $options );
    }
    return array( 'mode' => $options['mode'] );

  }


  /**
   * Helper functions
   **/

  // Save the user's last login
  // From: https://wordpress.org/support/topic/capture-users-last-login-datetime
  function last_login_save($user_login, $user) {
    $user = get_userdatabylogin($user_login);
    update_user_meta( $user->ID, 'govready_last_login', date('c') );
  }


}
new GovreadyAgent;
