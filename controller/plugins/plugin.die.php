<?php
	private function parseDie ($node)
	{
		if($this->validateAction($node))
		{
			$this -> stop = $node -> parentNode;
			return $node;
		}
		else
		{
			return $this -> dom -> createTextNode('');
		}
	}
?>
