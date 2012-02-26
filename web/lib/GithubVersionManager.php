<?php

// 使用https://github.com/ornicar/php-github-api 作为Github客户端库
require_once dirname(__FILE__) . "/Github/Autoloader.php";
Github_Autoloader::register();

/**
 * 使用Github作为版本历史的后端
 */
class GithubVersionManager
{
	protected $user 	= NULL;
	protected $repos	= NULL;
	protected $branch	= NULL;

	protected $github   = NULL;

	public function __construct($user, $repos, $branch)
	{
		$this->user 	= $user;
		$this->repos	= $repos;
		$this->branch	= $branch;

		$this->github = new Github_Client();
	}

	public function getLastCommit($file) {
		$commits = $this->getRevisionHistories($file);
		
		return array_shift($commits);
	}

	public function getRevisionHistories($file)
	{
		return $this->github->getCommitApi()->getFileCommits($this->user, $this->repos, $this->branch, $file);
	}

	public function getRawDataByFile($file, $revision='HEAD')
	{
		try {
			return $this->github->getObjectApi()->getRawData($this->user, $this->repos, $revision);
		}
		catch(Github_HttpClient_Exception $e) {
			throw new BookPageWithRevisionNotAvailableException("Github接口通信失败：{$e->getMessage()}");
		}
	}
}
