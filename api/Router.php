<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://enriquechavez.co
 * @since      1.0.0
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @author     Enrique Chavez <noone@tmeister.net>
 */
class Router
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     *
     * @var string The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     *
     * @var string The current version of this plugin.
     */
    private $version;

    /**
     * The namespace to add to the api calls.
     *
     * @var string The namespace to add to the api call
     */
    private $namespace;

    /**
     * Store errors to display if the JWT is wrong
     *
     * @var WP_Error
     */
    private $jwt_error = null;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version     The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->namespace = $this->plugin_name . '/v' . intval($this->version);
    }

    /**
     * Add the endpoints to the API
     */
    public function add_api_routes()
    {
        $routes = [
            [
                'methods' => 'POST',
                'cb' => 'authentication'
            ],
            [
                'cb' => 'query'
            ]
        ];
        foreach($routes as $route) {
            $method = isset($route['methods']) ? $route['methods'] : 'GET';
            $endpoint = isset($route['route']) ? $route['route'] : $route['cb'];
            register_rest_route($this->namespace, $endpoint, array(
                'methods' => $method,
                'callback' => array($this, $route['cb']),
            ));
        }
    }

    /**
     * Get the user and password in the request body and generate a JWT
     *
     * @param [type] $request [description]
     *
     * @return [type] [description]
     */
    public function authentication($request)
    {
        if (!$this->validate_sign($request)) {
            return new WP_Error(
                'LoginAccessDenined',
                '公共请求参数非法',
                array(
                    'status' => 403,
                )
            );
        }
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        /** Try to authenticate the user with the passed credentials*/
        $user = wp_authenticate($username, $password);

        /** If the authentication fails return a error*/
        if (is_wp_error($user)) {
            $error_code = $user->get_error_code();
            return new WP_Error(
                _($error_code),
                $user->get_error_message($error_code),
                array(
                    'status' => 403,
                )
            );
        }

        /** The token is signed, now create the object with no sensible user data to the client*/
        $userID = $user->data->ID;
        $data = array(
            'email' => $user->data->user_email,
            'username' => $user->data->display_name,
            'UID' => $userID
        );
        /** Let the user modify the data before send it back */
        return $data;
    }

    public function query($request)
    {

        $query = new WP_Query( $request->get_params() );

        $res = [];

        foreach ($query->posts as $post){
            array_push($res, $post);
        }
        
        wp_reset_postdata();

        if (count($res) < 1) {
            return new WP_Error(
                'NoPostFound',
                '没有找到',
                array(
                    'status' => 403,
                )
            );
        }
        
        return $res;
    }

    public function validate_sign($request)
    {
        $key = "SP6LQohujk2f3MBt0eJ94KzHOnNsAVcFExbwrvXD7YmgaCT5ldGpUqi8IWyZ1R";
        $t = $request->get_param('t');
        $us = $request->get_param('us');
        $sn = $request->get_param('sn');
        $sign = $request->get_param('sign');
        $requestParams = $request->get_params();
        foreach(['t', 'us', 'sn', 'sign', 'mid'] as $k) {
            unset($requestParams[$k]);
        }
        $content = http_build_query($requestParams);
        return md5($key . $sn . $content . $t . $us) === $sign;
    }
}
