<?php

/**
 * Listing issues, searching, editing and closing your projects issues.
 *
 * @link      http://develop.github.com/p/issues.html
 * @author    Thibault Duplessis <thibault.duplessis at gmail dot com>
 * @license   MIT License
 */
class Github_Api_Issue extends Github_Api
{
    /**
     * List issues by username, repo and state
     * http://develop.github.com/p/issues.html#list_a_projects_issues
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $state            the issue state, can be open or closed
     * @return  array                     list of issues found
     */
    public function getList($username, $repo, $state = 'open')
    {
        $response = $this->get('issues/list/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($state));

        return $response['issues'];
    }

    /**
     * Search issues by username, repo, state and search term
     * http://develop.github.com/p/issues.html#list_a_projects_issues
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $state            the issue state, can be open or closed
     * @param   string  $searchTerm       the search term to filter issues by
     * @return  array                     list of issues found
     */
    public function search($username, $repo, $state, $searchTerm)
    {
        $response = $this->get('issues/search/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($state).'/'.urlencode($searchTerm));

        return $response['issues'];
    }

    /**
     * Search issues by label
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $label            the label to filter issues by
     * @return  array                     list of issues found
     */
    public function searchLabel($username, $repo, $label)
    {
        $response = $this->get('issues/list/'.urlencode($username).'/'.urlencode($repo).'/label/'.urlencode($label));

        return $response['issues'];
    }

    /**
     * Get extended information about an issue by its username, repo and number
     * http://develop.github.com/p/issues.html#view_an_issue
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $issueNumber      the issue number
     * @return  array                     information about the issue
     */
    public function show($username, $repo, $issueNumber)
    {
        $response = $this->get('issues/show/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($issueNumber));

        return $response['issue'];
    }

    /**
     * Create a new issue for the given username and repo.
     * The issue is assigned to the authenticated user. Requires authentication.
     * http://develop.github.com/p/issues.html#open_and_close_issues
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $issueTitle       the new issue title
     * @param   string   $issueBody       the new issue body
     * @return  array                     information about the issue
     */
    public function open($username, $repo, $issueTitle, $issueBody)
    {
        $response = $this->post('issues/open/'.urlencode($username).'/'.urlencode($repo), array(
            'title' => $issueTitle,
            'body' => $issueBody
        ));

        return $response['issue'];
    }

    /**
     * Close an existing issue by username, repo and issue number. Requires authentication.
     * http://develop.github.com/p/issues.html#open_and_close_issues
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $issueNumber      the issue number
     * @return  array                     information about the issue
     */
    public function close($username, $repo, $issueNumber)
    {
        $response = $this->post('issues/close/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($issueNumber));

        return $response['issue'];
    }

    /**
     * Update issue informations by username, repo and issue number. Requires authentication.
     * http://develop.github.com/p/issues.html#edit_existing_issues
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $issueNumber      the issue number
     * @param   array   $data             key=>value user attributes to update.
     *                                    key can be title or body
     * @return  array                     information about the issue
     */
    public function update($username, $repo, $issueNumber, array $data)
    {
        $response = $this->post('issues/edit/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($issueNumber), $data);

        return $response['issue'];
    }

    /**
     * Repoen an existing issue by username, repo and issue number. Requires authentication.
     * http://develop.github.com/p/issues.html#open_and_close_issues
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $issueNumber      the issue number
     * @return  array                     informations about the issue
     */
    public function reOpen($username, $repo, $issueNumber)
    {
        $response = $this->post('issues/reopen/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($issueNumber));

        return $response['issue'];
    }

    /**
     * List an issue comments by username, repo and issue number
     * http://develop.github.com/p/issues.html#list_an_issues_comments
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $issueNumber      the issue number
     * @return  array                     list of issue comments
     */
    public function getComments($username, $repo, $issueNumber)
    {
        $response = $this->get('issues/comments/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($issueNumber));

        return $response['comments'];
    }

    /**
     * Add a comment to the issue by username, repo and issue number
     * http://develop.github.com/p/issues.html#comment_on_issues
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $issueNumber      the issue number
     * @param   string  $comment          the comment body
     * @return  array                     the created comment
     */
    public function addComment($username, $repo, $issueNumber, $commentBody)
    {
        $response = $this->post('issues/comment/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($issueNumber), array(
            'comment' => $commentBody
        ));

        return $response['comment'];
    }

    /**
     * List all project labels by username and repo
     * http://develop.github.com/p/issues.html#listing_labels
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @return  array                     list of project labels
     */
    public function getLabels($username, $repo)
    {
        $response = $this->get('issues/labels/'.urlencode($username).'/'.urlencode($repo));

        return $response['labels'];
    }

    /**
     * Add a label to the issue by username, repo and issue number. Requires authentication.
     * http://develop.github.com/p/issues.html#add_and_remove_labels
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $issueNumber      the issue number
     * @param   string  $labelName        the label name
     * @return  array                     list of issue labels
     */
    public function addLabel($username, $repo, $labelName, $issueNumber)
    {
        $response = $this->post('issues/label/add/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($labelName).'/'.urlencode($issueNumber));

        return $response['labels'];
    }

    /**
     * Remove a label from the issue by username, repo, issue number and label name. Requires authentication.
     * http://develop.github.com/p/issues.html#add_and_remove_labels
     *
     * @param   string  $username         the username
     * @param   string  $repo             the repo
     * @param   string  $issueNumber      the issue number
     * @param   string  $labelName        the label name
     * @return  array                     list of issue labels
     */
    public function removeLabel($username, $repo, $labelName, $issueNumber)
    {
        $response = $this->post('issues/label/remove/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($labelName).'/'.urlencode($issueNumber));

        return $response['labels'];
    }
}
