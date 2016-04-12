<?php
/*
Plugin Name:        GovReady
Plugin URI:         http://govready.com/wordpress
Description:        GovReady provides a dashboard and tools to enhance security for government websites and achieve FISMA compliance.
Version:            1.0.0
Author:             GovReady
Author URI:         http://govready.com
License:            Affero GPL v3
*/

namespace Govready;


// Set the version of this plugin
//if( ! defined( 'GOVREADY_VERSION' ) ) {
//  define( 'GOVREADY_VERSION', '1.0' );
//} // end if


class Govready {

  public function __construct() {
    $this->key = 'govready';
    // @todo: get this from an API call?
    $this->auth0 = array(
      'domain' => 'govready.auth0.com',
      'client_id' => 'HbYZO5QXKfgNshjKlhZGizskiaJH9kGH'
    );
    $this->govready_url = 'http://workhorse.albatrossdigital.com:4000/v1.0';

    // Load plugin textdomain
    add_action( 'init', array( $this, 'plugin_textdomain' ) );

    // Display the admin notification
    add_action( 'admin_notices', array( $this, 'plugin_activation' ) ) ;

    // Add the dashboard page
    add_action( 'admin_menu', array($this, 'create_menu') );

    // Add the AJAX proxy endpoints
    add_action( 'wp_ajax_govready_refresh_token', array($this, 'api_refresh_token') );
    add_action( 'wp_ajax_govready_proxy', array($this, 'api_proxy') );
    add_action( 'wp_ajax_govready_nopriv_v1_trigger', array($this, 'api_agent') );
    
  }


  /**
    * Defines the plugin textdomain.
    */
  public function plugin_textdomain() {

    $locale = apply_filters( $this->key, get_locale(), $domain );

    load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
    load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

  } // end plugin_textdomain


  /**
   * Saves the version of the plugin to the database and displays an activation notice on where users
   * can connect to the GovReady API.
   */
  public function plugin_activation() {

    if( empty(get_option( 'govready_domain' )) ) {

      //add_option( 'govready_version', GOVREADY_VERSION ); @todo: this should be done only after connecting

      $html = '<div class="updated">';
        $html .= '<p>';
          $html .= __( '<a href="admin.php?page=govready">Connect to GovReady</a> to finish your setup and begin monitoring your site.', $this->key );
        $html .= '</p>';
      $html .= '</div><!-- /.updated -->';

      echo $html;

    } // end if

  } // end plugin_activation



  /**
   * Deletes the option from the database.
   */
  public static function plugin_deactivation() {

    delete_option( 'govready_domain' );

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
      plugins_url('/images/icon.png', __FILE__) 
    );

  } // end create_menu


  /**
   * Display the GovReady dashboard.
   */
  public function dashboard_page() {
    $options = get_option( 'govready_options', array() );
    $path = plugins_url('includes/js/',__FILE__);

    // First time using app, need to set everything up
    if( empty($options['refresh_token']) ) {
      
      // Save some JS variables (available at govready.siteId, etc)
      wp_enqueue_script( 'govready-connect', $path . 'govready-connect.js' );
      wp_localize_script( 'govready-connect', 'govready_connect', array( 
        'nonce' => wp_create_nonce( $this->key ),
        'auth0' => $this->auth0
      ) );

      // Call GovReady /initialize to set the allowed CORS endpoint
      // @todo: error handling: redirect user to GovReady API dedicated login page
      if (empty($options['siteId'])) {
        $data = array(
          'url' => get_site_url(),
        );
        $response = $this->api( '/initialize', 'POST', $data, true );
        print_r($response);
        $options['siteId'] = $response['_id'];
        update_option( 'govready_options', $options );
      }
      
      require_once plugin_dir_path(__FILE__) . '/templates/govready-connect.php';
    
    }

    // Show me the dashboard!
    else {
    
      // Save some JS variables (available at govready.siteId, etc)
      wp_enqueue_script( 'govready-dashboard', $path . 'govready.js' );
      wp_localize_script( 'govready-dashboard', 'govready', array( 
        'siteId' => !is_null($options['siteId']) ? $options['siteId'] : null, 
        'nonce' => wp_create_nonce( $this->key )
      ) );

      require_once plugin_dir_path(__FILE__) . '/templates/govready-dashboard.php';

    } // if()

  }



  /**
   * Make a request to the GovReady API.
   * @todo: error handling
   */
  public function api( $endpoint, $method = 'GET', $data = array(), $anonymous = false ) {

    $url = $this->govready_url . $endpoint;

    // Make sure our token is a-ok
    $token = get_option( 'govready_token', array() );
    if ( !$anonymous && ( empty($token['endoflife']) || $token['endoflife'] < time() ) ) {
      $token = $this->api_refresh_token();
    }
    $token = !$anonymous && !empty($token['id_token']) ? $token['id_token'] : false;

    // Make the API request with cURL
    // @todo should we support HTTP_request (https://pear.php.net/manual/en/package.http.http-request.intro.php)?
    $headers = array( 'Content-Type: application/json' );
    if ( $token ) {
      array_push( $headers, 'authentication: Bearer ' . $token );
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    if ( $data ) {
      curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($curl);
    curl_close($curl);

    $response = json_decode( $response, true );

    return $response;

  }


  /**
   * Refresh the access token.
   */
  public function api_refresh_token( $return = false ) {
    
    // @todo: nonce this call
    $options = get_option( 'govready_options' );
    if ( $_REQUEST['refresh_token'] ) {
      // Validate the nonce
      if (check_ajax_referer( $this->key, '_ajax_nonce' )) {
        //return;
      }
      $token = $_REQUEST['refresh_token'];
      $options['refresh_token'] = $token;
      update_option( 'govready_options', $options );
    }
    else {
      $token = !empty($options['refresh_token']) ? $options['refresh_token'] : '';
    }

    $response = $this->api( '/refresh-token', 'POST', array( 'refresh_token' => $token), true );
    $response['endoflife'] = time() + (int) $response['expires'];
    update_option( 'govready_token', $response );
    
    if ($return) {
      return $response;
    }
    else {
      wp_send_json($response);
    }

  }


  /**
   * Call the GovReady API.
   */
  public function api_proxy() {

    $method = !empty($_REQUEST['method']) ? $_REQUEST['method'] : $_SERVER['REQUEST_METHOD'];
    $response = $this->api( $_REQUEST['endpoint'], $method, $_REQUEST );
    wp_send_json($response);
    wp_die();

  }


  /**
   * Ping the site to trigger the agent to collect data.
   * Does not require authentication.
   */
  public function api_agent() {

    require_once plugin_dir_path(__FILE__) . '/lib/govready-agent.class.php';

  }


} // end class

new Govready;