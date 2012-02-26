<?php

/**
 * Performs requests on GitHub API. API documentation should be self-explanatory.
 *
 * @author    Thibault Duplessis <thibault.duplessis at gmail dot com>
 * @license   MIT License
 */
abstract class Github_HttpClient implements Github_HttpClientInterface
{
    /**
     * The http client options
     * @var array
     */
    protected $options = array(
        'protocol'   => 'http',
        'url'        => ':protocol://github.com/api/v2/:format/:path',
        'format'     => 'json',
        'user_agent' => 'php-github-api (http://github.com/ornicar/php-github-api)',
        'http_port'  => 80,
        'timeout'    => 10,
        'login'      => null,
        'token'      => null
    );

    protected static $history = array();

    /**
     * Instanciate a new http client
     *
     * @param  array   $options  http client options
     */
    public function __construct(array $options = array())
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Send a request to the server, receive a response
     *
     * @param  string   $url           Request url
     * @param  array    $parameters    Parameters
     * @param  string   $httpMethod    HTTP method to use
     * @param  array    $options        Request options
     *
     * @return string   HTTP response
     */
    abstract protected function doRequest($url, array $parameters = array(), $httpMethod = 'GET', array $options = array());

    /**
     * Send a GET request
     *
     * @param  string   $path            Request path
     * @param  array    $parameters     GET Parameters
     * @param  string   $httpMethod     HTTP method to use
     * @param  array    $options        Request options
     *
     * @return array                    Data
     */
    public function get($path, array $parameters = array(), array $options = array())
    {
        return $this->request($path, $parameters, 'GET', $options);
    }

    /**
     * Send a POST request
     *
     * @param  string   $path            Request path
     * @param  array    $parameters     POST Parameters
     * @param  string   $httpMethod     HTTP method to use
     * @param  array    $options        reconfigure the request for this call only
     *
     * @return array                    Data
     */
    public function post($path, array $parameters = array(), array $options = array())
    {
        return $this->request($path, $parameters, 'POST', $options);
    }

    /**
     * Send a request to the server, receive a response,
     * decode the response and returns an associative array
     *
     * @param  string   $path            Request API path
     * @param  array    $parameters     Parameters
     * @param  string   $httpMethod     HTTP method to use
     * @param  array    $options        Request options
     *
     * @return array                    Data
     */
    public function request($path, array $parameters = array(), $httpMethod = 'GET', array $options = array())
    {
        $this->updateHistory();

        $options = array_merge($this->options, $options);

        // create full url
        $url = strtr($options['url'], array(
            ':protocol' => $options['protocol'],
            ':format'   => $options['format'],
            ':path'     => trim($path, '/')
        ));

        // get encoded response
        $response = $this->doRequest($url, $parameters, $httpMethod, $options);

        // decode response
        $response = $this->decodeResponse($response, $options);

        return $response;
    }

    /**
     * Get a JSON response and transform it to a PHP array
     *
     * @return  array   the response
     */
    protected function decodeResponse($response, array $options)
    {
        if ('text' === $options['format']) {
            return $response;
        } elseif ('json' === $options['format']) {
            return json_decode($response, true);
        }

        throw new Exception(__CLASS__.' only supports json & text format, '.$options['format'].' given.');
    }

    /**
     * Change an option value.
     *
     * @param string $name   The option name
     * @param mixed  $value  The value
     *
     * @return Github_HttpClientInterface The current object instance
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * Records the requests times
     * When 30 request have been sent in less than a minute,
     * sleeps for two second to prevent reaching GitHub API limitation.
     *
     * @access protected
     * @return void
     */
    protected function updateHistory()
    {
        self::$history[] = time();
        if (30 === count(self::$history)) {
            if (reset(self::$history) >= (time() - 35)) {
                sleep(2);
            }
            array_shift(self::$history);
        }
    }
}
