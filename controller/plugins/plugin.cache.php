<?php
	public function cacheGet($name)
	{
		return apc_fetch($name);
	}
	
	public function cacheSet($name, $content = '')
	{
		apc_store($name, $content, 15);
	}
?>
