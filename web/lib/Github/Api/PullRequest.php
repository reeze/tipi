<?php

/**
 * API for accessing Pull Requests from your Git/Github repositories.
 *
 * @link      http://develop.github.com/p/pulls.html
 * @author    Nicolas Pastorino <nicolas.pastorino at gmail dot com>
 * @license   MIT License
 */
class Github_Api_PullRequest extends Github_Api
{
    /**
     * Get a listing of a project's pull requests by the username, repo, and optionnally state.
     *
     * @link      http://develop.github.com/p/pulls.html
     * @param   string $username          the username
     * @param   string $repo              the repo
     * @param   string $state             the state of the fetched pull requests.
     *                                    The API seems to automatically default to 'open'
     * @return  array                     array of pull requests for the project
     */
    public function listPullRequests($username, $repo, $state = '')
    {
        $response = $this->get('pulls/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($state));
        return $response['pulls'];
    }

    /**
     * Show all details of a pull request, including the discussions.
     *
     * @link      http://develop.github.com/p/pulls.html
     * @param   string $username          the username
     * @param   string $repo              the repo
     * @param   string $pullRequestId     the ID of the pull request for which details are retrieved
     * @return  array                     array of pull requests for the project
     */
    public function show($username, $repo, $pullRequestId)
    {
        $response = $this->get('pulls/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($pullRequestId));
        return $response['pulls'];
    }

    /**
     * Create a pull request
     *
     * @link      http://develop.github.com/p/pulls.html
     * @param   string $username          the username
     * @param   string $repo              the repo
     * @param   string $base              A String of the branch or commit SHA that you want your changes to be pulled to.
     * @param   string $head              A String of the branch or commit SHA of your changes.
     *                                    Typically this will be a branch. If the branch is in a fork of the original repository,
     *                                    specify the username first: "my-user:some-branch".
     * @param   string $title             The String title of the Pull Request. Used in pair with $body.
     * @param   string $body              The String body of the Pull Request. Used in pair with $title.
     * @param   int $issueId              If a pull-request is related to an issue, place issue ID here. The $title-$body pair and this are mutually exclusive.
     * @return  array                     array of pull requests for the project
     */
    public function create($username, $repo, $base, $head, $title = null, $body = null, $issueId = null)
    {
        $postParameters = array( 'pull[base]' => $base,
                                 'pull[head]' => $head
                          );

        if ( $title !== null and $body !== null ) {
            $postParameters = array_merge( $postParameters,
                                           array(
                                             'pull[title]' => $title,
                                             'pull[body]'  => $body
                                           )
                                         );
        } elseif ( $issueId !== null ) {
            $postParameters = array_merge( $postParameters,
                                           array(
                                             'pull[issue]' => $issueId
                                           )
                                         );
        } else {
            // @FIXME : Exception required here.
            return null;
        }

        $response = $this->post('pulls/'.urlencode($username).'/'.urlencode($repo),
                                $postParameters
                               );

        // @FIXME : Exception to be thrown when $response['error'] exists.
        //          Content of error can be : "{"error":["A pull request already exists for <username>:<branch>."]}"
        return $response['pull'];
    }
}
