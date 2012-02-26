<?php

/**
 * Searching users, getting user information
 * and managing authenticated user account information.
 *
 * @link      http://develop.github.com/p/users.html
 * @author    Thibault Duplessis <thibault.duplessis at gmail dot com>
 * @license   MIT License
 */
class Github_Api_User extends Github_Api
{
    /**
     * Search users by username
     * http://develop.github.com/p/users.html#searching_for_users
     *
     * @param   string  $username         the username to search
     * @return  array                     list of users found
     */
    public function search($username)
    {
        $response = $this->get('user/search/'.urlencode($username));

        return $response['users'];
    }

    /**
     * Get extended information about a user by its username
     * http://develop.github.com/p/users.html#getting_user_information
     *
     * @param   string  $username         the username to show
     * @return  array                     informations about the user
     */
    public function show($username)
    {
        $response = $this->get('user/show/'.urlencode($username));

        return $response['user'];
    }

    /**
     * Update user informations. Requires authentication.
     * http://develop.github.com/p/users.html#authenticated_user_management
     *
     * @param   string  $username         the username to update
     * @param   array   $data             key=>value user attributes to update.
     *                                    key can be name, email, blog, company or location
     * @return  array                     informations about the user
     */
    public function update($username, array $data)
    {
        $response = $this->post('user/show/'.urlencode($username), array('values' => $data));

        return $response['user'];
    }

    /**
     * Request the users that a specific user is following
     * http://develop.github.com/p/users.html#following_network
     *
     * @param   string  $username         the username
     * @return  array                     list of followed users
     */
    public function getFollowing($username)
    {
        $response = $this->get('user/show/'.urlencode($username).'/following');

        return $response['users'];
    }

    /**
     * Request the users following a specific user
     * http://develop.github.com/p/users.html#following_network
     *
     * @param   string  $username         the username
     * @return  array                     list of following users
     */
    public function getFollowers($username)
    {
        $response = $this->get('user/show/'.urlencode($username).'/followers');

        return $response['users'];
    }

    /**
     * Make the authenticated user follow the specified user. Requires authentication.
     * http://develop.github.com/p/users.html#following_network
     *
     * @param   string  $username         the username to follow
     * @return  array                     list of followed users
     */
    public function follow($username)
    {
        $response = $this->post('user/follow/'.urlencode($username));

        return $response['users'];
    }

    /**
     * Make the authenticated user unfollow the specified user. Requires authentication.
     * http://develop.github.com/p/users.html#following_network
     *
     * @param   string  $username         the username to unfollow
     * @return  array                     list of followed users
     */
    public function unFollow($username)
    {
        $response = $this->post('user/unfollow/'.urlencode($username));

        return $response['users'];
    }

    /**
     * Request the repos that a specific user is watching
     * http://develop.github.com/p/users.html#watched_repos
     *
     * @param   string  $username         the username
     * @return  array                     list of watched repos
     */
    public function getWatchedRepos($username)
    {
        $response = $this->get('repos/watched/'.urlencode($username));

        return $response['repositories'];
    }

    /**
     * Get the authenticated user public keys. Requires authentication
     *
     * @return  array                     list of public keys of the user
     */
    public function getKeys()
    {
        $response = $this->get('user/keys');

        return $response['public_keys'];
    }

    /**
     * Add a public key to the authenticated user. Requires authentication.
     *
     * @return  array                    list of public keys of the user
     */
    public function addKey($title, $key)
    {
        $response = $this->post('user/key/add', array('title' => $title, 'key' => $key));

        return $response['public_keys'];
    }

    /**
     * Remove a public key from the authenticated user. Requires authentication.
     *
     * @return  array                    list of public keys of the user
     */
    public function removeKey($id)
    {
        $response = $this->post('user/key/remove', array('id' => $id));

        return $response['public_keys'];
    }

    /**
     * Get the authenticated user emails. Requires authentication.
     *
     * @return  array                     list of authenticated user emails
     */
    public function getEmails()
    {
        $response = $this->get('user/emails');

        return $response['emails'];
    }

    /**
     * Add an email to the authenticated user. Requires authentication.
     *
     * @return  array                     list of authenticated user emails
     */
    public function addEmail($email)
    {
        $response = $this->post('user/email/add', array('email' => $email));

        return $response['emails'];
    }

    /**
     * Remove an email from the authenticated user. Requires authentication.
     *
     * @return  array                     list of authenticated user emails
     */
    public function removeEmail($email)
    {
        $response = $this->post('user/email/remove', array('email' => $email));

        return $response['emails'];
    }
}
