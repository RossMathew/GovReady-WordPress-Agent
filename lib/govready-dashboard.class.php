<?php
/**
 * @author GovReady
 */

namespace Govready\GovreadyDashboard;

use Govready;

class GovreadyDashboard extends Govready\Govready {


  function __construct() {
    parent::__construct();

    // Display the admin notification
    add_action( 'admin_notices', array( $this, 'plugin_activation' ) ) ;

    // Add the dashboard page
    add_action( 'admin_menu', array($this, 'create_menu') );
  }


  /**
   * Saves the version of the plugin to the database and displays an activation notice on where users
   * can connect to the GovReady API.
   */
  public function plugin_activation() {
    $options = get_option( 'govready_options', array() );
    
    // Check that GovReady has been enabled
    if( empty($options['refresh_token']) ) {

      $html = '<div class="updated">';
        $html .= '<p>';
          $html .= __( '<a href="admin.php?page=govready">Connect to GovReady</a> to finish your setup and begin monitoring your site.', $this->key );
        $html .= '</p>';
      $html .= '</div><!-- /.updated -->';

      echo $html;

    } // end if

    // check that cURL exists
    if( !function_exists('curl_version') ) {

      $html = '<div class="update-nag">';
        $html .= __( 'It looks like cURL is currently not enabled.  The GovReady plugin will not work without cURL enabled. <a href="http://www.tomjepson.co.uk/enabling-curl-in-php-php-ini-wamp-xamp-ubuntu/" target="_blank">Tutorial to enable cURL in PHP</a>.', $this->key );
      $html .= '</div><!-- /.update-nag -->';

      echo $html;

    } // end if

  } // end plugin_activation



  /**
   * Deletes the option from the database.
   */
  public static function plugin_deactivation() {

    delete_option( 'govready_options' );
    delete_option( 'govready_token' );

  } // end plugin_deactivation


  /**
   * Creates the wp-admin menu entries for the dashboard.
   */
  public function create_menu() {

    add_menu_page(
      __( 'GovReady', $this->key ), 
      __( 'GovReady', $this->key ), 
      'manage_options',
      'govready',
      array($this, 'dashboard_page'), 
      plugins_url('/../images/icon.png', __FILE__) 
    );

  } // end create_menu


  /**
   * Display the GovReady dashboard.
   */
  public function dashboard_page() {
    $options = get_option( 'govready_options', array() );
    $path = plugins_url('../includes/js/', __FILE__);
    $client_path = get_option('govready_client', 'remote') != 'local' ? $this->govready_client_url : $path . '/client/dist';
    $logo = plugins_url('/../images/logo.png', __FILE__);

    // Enqueue Bootstrap 

    // First time using app, need to set everything up
    if( empty($options['refresh_token']) ) {

      // Call GovReady /initialize to set the allowed CORS endpoint
      // @todo: error handling: redirect user to GovReady API dedicated login page
      if (empty($options['siteId'])) {
        $data = array(
          'url' => get_site_url(),
          'application' => 'wordpress',
        );
        $response = $this->api( '/initialize', 'POST', $data, true );
      }

      // Save some JS variables (available at govready.siteId, etc)
      wp_enqueue_script( 'govready-connect', $path . '/govready-connect.js' );
      wp_localize_script( 'govready-connect', 'govready_connect', array( 
        'govready_nonce' => wp_create_nonce( $this->key ),
        'key' => $this->key,
        'auth0' => $this->auth0,
        'siteId' => !empty( $options['siteId'] ) ? $options['siteId'] : NULL
      ) );

      require_once plugin_dir_path(__FILE__) . '/../templates/govready-connect.php';
    
    }

    // Show me the dashboard!
    else {

      // Enqueue react
      wp_register_script( 'govready-dashboard-app-vendor', $client_path . '/vendor.dist.js' );
      wp_register_script( 'govready-dashboard-app', $client_path . '/app.dist.js', array('govready-dashboard-app-vendor') );
      // Save some JS variables (available at govready.siteId, etc)
      wp_localize_script( 'govready-dashboard-app-vendor', 'govready', array( 
        'siteId' => !empty( $options['siteId'] ) ? $options['siteId'] : null, 
        'key'=> $this->key,
        'govready_nonce' => wp_create_nonce( $this->key ),
        'mode' => !empty($options['mode']) ? $options['mode'] : 'preview',
        'connectUrl' => $this->govready_api_url,
        'application' => 'wordpress'
      ) );
      wp_enqueue_script( 'govready-dashboard-app-vendor' );
      wp_enqueue_script( 'govready-dashboard-app' );
      wp_enqueue_style ( 'govready-dashboard-app', $client_path . '/app.dist.css' );

      require_once plugin_dir_path(__FILE__) . '../templates/govready-dashboard.php';

    } // if()

  }

}
new GovreadyDashboard;
