<?php

/**
 * Simple yet very cool PHP Github client
 *
 * @tutorial  http://github.com/ornicar/php-github-api/blob/master/README.markdown
 * @version   3.2
 * @author    Thibault Duplessis <thibault.duplessis at gmail dot com>
 * @license   MIT License
 *
 * Website: http://github.com/ornicar/php-github-api
 * Tickets: http://github.com/ornicar/php-github-api/issues
 */
class Github_Client
{
    /**
     * Constant for authentication method. Indicates the default, but deprecated
     * login with username and token in URL.
     */
    const AUTH_URL_TOKEN = 'url_token';

    /**
     * Constant for authentication method. Indicates the new favored login method
     * with username and password via HTTP Authentication.
     */
    const AUTH_HTTP_PASSWORD = 'http_password';

    /**
     * Constant for authentication method. Indicates the new login method with
     * with username and token via HTTP Authentication.
     */
    const AUTH_HTTP_TOKEN = 'http_token';

    /**
     * The httpClient instance used to communicate with GitHub
     *
     * @var Github_HttpClient_Interface
     */
    protected $httpClient = null;

    /**
     * The list of loaded API instances
     *
     * @var array
     */
    protected $apis = array();

    /**
     * Instanciate a new GitHub client
     *
     * @param  Github_HttpClient_Interface $httpClient custom http client
     */
    public function __construct(Github_HttpClientInterface $httpClient = null)
    {
        if (null === $httpClient) {
            $this->httpClient = new Github_HttpClient_Curl();
        } else {
            $this->httpClient = $httpClient;
        }
    }

    /**
     * Authenticate a user for all next requests
     *
     * @param  string         $login      GitHub username
     * @param  string         $secret     GitHub private token or Github password if $method == AUTH_HTTP_PASSWORD
     * @param  string         $method     One of the AUTH_* class constants
     *
     * @return null
     */
    public function authenticate($login, $secret, $method = NULL)
    {
        if (!$method) {
            $method = self::AUTH_URL_TOKEN;
        }

        $this->getHttpClient()
                ->setOption('auth_method', $method)
                ->setOption('login', $login)
                ->setOption('secret', $secret);
    }

    /**
     * Deauthenticate a user for all next requests
     *
     * @return null
     */
    public function deAuthenticate()
    {
        $this->authenticate(null, null, null);
    }

    /**
     * Call any path, GET method
     * Ex: $api->get('repos/show/my-username/my-repo')
     *
     * @param   string  $path            the GitHub path
     * @param   array   $parameters       GET parameters
     * @param   array   $requestOptions   reconfigure the request
     * @return  array                     data returned
     */
    public function get($path, array $parameters = array(), $requestOptions = array())
    {
        return $this->getHttpClient()->get($path, $parameters, $requestOptions);
    }

    /**
     * Call any path, POST method
     * Ex: $api->post('repos/show/my-username', array('email' => 'my-new-email@provider.org'))
     *
     * @param   string  $path            the GitHub path
     * @param   array   $parameters       POST parameters
     * @param   array   $requestOptions   reconfigure the request
     * @return  array                     data returned
     */
    public function post($path, array $parameters = array(), $requestOptions = array())
    {
        return $this->getHttpClient()->post($path, $parameters, $requestOptions);
    }

    /**
     * Get the http client.
     *
     * @return  Github_HttpClient_Interface   a request instance
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * Inject another http client
     *
     * @param   Github_HttpClient_Interface   a httpClient instance
     *
     * @return  null
     */
    public function setHttpClient(Github_HttpClient_Interface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Get the user API
     *
     * @return  Github_Api_User    the user API
     */
    public function getUserApi()
    {
        if (!isset($this->apis['user'])) {
            $this->apis['user'] = new Github_Api_User($this);
        }

        return $this->apis['user'];
    }

    /**
     * Get the issue API
     *
     * @return  Github_Api_Issue   the issue API
     */
    public function getIssueApi()
    {
        if (!isset($this->apis['issue'])) {
            $this->apis['issue'] = new Github_Api_Issue($this);
        }

        return $this->apis['issue'];
    }

    /**
     * Get the commit API
     *
     * @return  Github_Api_Commit  the commit API
     */
    public function getCommitApi()
    {
        if (!isset($this->apis['commit'])) {
            $this->apis['commit'] = new Github_Api_Commit($this);
        }

        return $this->apis['commit'];
    }

    /**
     * Get the repo API
     *
     * @return  Github_Api_Repo  the repo API
     */
    public function getRepoApi()
    {
        if (!isset($this->apis['repo'])) {
            $this->apis['repo'] = new Github_Api_Repo($this);
        }

        return $this->apis['repo'];
    }

    /**
     * Get the organization API
     *
     * @return  Github_Api_Organization  the object API
     */
    public function getOrganizationApi()
    {
        if (!isset($this->apis['organization'])) {
            $this->apis['organization'] = new Github_Api_Organization($this);
        }

        return $this->apis['organization'];
    }

    /**
     * Get the object API
     *
     * @return  Github_Api_Object  the object API
     */
    public function getObjectApi()
    {
        if (!isset($this->apis['object'])) {
            $this->apis['object'] = new Github_Api_Object($this);
        }

        return $this->apis['object'];
    }

    /**
     * Get the pull request API
     *
     * @return  Github_Api_PullRequest  the pull request API
     */
    public function getPullRequestApi()
    {
        if (!isset($this->apis['pullrequest'])) {
            $this->apis['pullrequest'] = new Github_Api_PullRequest($this);
        }

        return $this->apis['pullrequest'];
    }

    /**
     * Inject an API instance
     *
     * @param   string                $name the API name
     * @param   Github_ApiInterface  $api  the API instance
     *
     * @return  null
     */
    public function setApi($name, Github_ApiInterface $instance)
    {
        $this->apis[$name] = $instance;

        return $this;
    }

    /**
     * Get any API
     *
     * @param   string                $name the API name
     * @return  Github_ApiInterface  the API instance
     */
    public function getApi($name)
    {
        return $this->apis[$name];
    }
}
