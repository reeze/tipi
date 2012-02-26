<?php

/**
 * Abstract class for Github_Api classes
 *
 * @author    Thibault Duplessis <thibault.duplessis at gmail dot com>
 * @license   MIT License
 */
abstract class Github_Api implements Github_ApiInterface
{
    /**
     * The client
     * @var Github_Client
     */
    private $client;

    public function __construct(Github_Client $client)
    {
        $this->client = $client;
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
    protected function get($path, array $parameters = array(), $requestOptions = array())
    {
        return $this->client->get($path, $parameters, $requestOptions);
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
    protected function post($path, array $parameters = array(), $requestOptions = array())
    {
        return $this->client->post($path, $parameters, $requestOptions);
    }
}
