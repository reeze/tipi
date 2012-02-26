<?php

class TIPI
{
	public static function getVersion()
	{
		static $version = NULL;
		
		if(!$version) {
			$version = trim(file_get_contents(TIPI_ROOT_PATH . "/VERSION"));
		}

		return $version ? $version : 'Unkown';
	}

	public static function haveNewVersion()
	{
		return self::getRequestParam('v') != self::getVersion();	
	}

	// Simple Wrapper
	public static function getRequestParam($key, $default=NULL)
	{
		return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
	}

	public static function getHomeUrlForPdf()
	{
		return self::getHomeUrlFor('pdf');	
	}

	public static function getHomeUrlForEpub()
	{
		return self::getHomeUrlFor('epub');	
	}

	public static function getHomeUrlForChm()
	{
		return self::getHomeUrlFor('chm');	
	}

	protected static function getHomeUrlFor($type)
	{
		return url_for("http://www.php-internal.com/?v=" . self::getVersion() . "&ref=$type");	
	}
}
