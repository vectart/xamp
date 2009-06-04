<?php
	private function parseRedirect ($node) {
		if ($this -> validateAction ($node))
		{
			header('Location: '.$this -> value($node -> getAttribute('to')));
			exit();
		}
		$result = $this -> dom -> createElement ('redirect');
		return $result;
	}
?>
