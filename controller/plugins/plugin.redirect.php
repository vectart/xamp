<?php
	private function parseRedirect ($node)
	{
		if($this -> validateAction ($node))
		{
			header('Location: '.$this -> value($node -> getAttribute('url')), true);
			exit();
		}
		$result = $node;
		$result -> setAttribute('url', $this -> value($node -> getAttribute('url')));
		return $result;
	}
?>
