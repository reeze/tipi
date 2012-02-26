<?php

/**
 * Getting full versions of specific files and trees in your Git repositories.
 *
 * @link      http://develop.github.com/p/object.html
 * @author    Thibault Duplessis <thibault.duplessis at gmail dot com>
 * @license   MIT License
 */
class Github_Api_Object extends Github_Api
{
    /**
     * Get a listing of the root tree of a project by the username, repo, and tree SHA
     * http://develop.github.com/p/object.html#trees
     *
     * @param   string $username          the username
     * @param   string $repo              the repo
     * @param   string $treeSHA           the tree sha
     * @return  array                     root tree of the project
     */
    public function showTree($username, $repo, $treeSHA)
    {
        $response = $this->get('tree/show/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($treeSHA));

        return $response['tree'];
    }

    /**
     * Lists the data blobs of a tree by tree SHA
     * http://develop.github.com/p/object.html#blobs
     *
     * @param   string $username          the username
     * @param   string $repo              the repo
     * @param   string $treeSHA           the tree sha
     * @param   string $path              the path
     * @return  array                     data blobs of tree
     */
    public function listBlobs($username, $repo, $treeSHA)
    {
        $response = $this->get('blob/all/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($treeSHA));

        return $response['blobs'];
    }

    /**
     * Get the data about a blob by tree SHA and file path.
     * http://develop.github.com/p/object.html#blobs
     *
     * @param   string $username          the username
     * @param   string $repo              the repo
     * @param   string $treeSHA           the tree sha
     * @param   string $path              the path
     * @return  array                     data blob of tree and path
     */
    public function showBlob($username, $repo, $treeSHA, $path)
    {
        $response = $this->get('blob/show/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($treeSHA).'/'.urlencode($path));

        return $response['blob'];
    }

    /**
     * Returns the raw text content of the object.
     * http://develop.github.com/p/object.html#raw_git_data
     *
     * @param   string $username          the username
     * @param   string $repo              the repo
     * @param   string $objectSHA         the object sha can be either a blob SHA1, a tree SHA1 or a commit SHA1
     * @return  string                    raw text content of the blob, tree or commit object
     */
    public function getRawData($username, $repo, $objectSHA)
    {
        $response = $this->get('blob/show/'.urlencode($username).'/'.urlencode($repo).'/'.urlencode($objectSHA), array(), array(
            'format' => 'text'
        ));

        return $response;
    }
}
