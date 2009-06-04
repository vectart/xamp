<?php
	private function parseExecute ($node) {
		$content = '';
		$result = $this -> dom -> createElement ('execute');
		if ($this -> validateAction ($node))
		{
			if($node -> hasAttribute('shell'))
			{
				$command = escapeshellcmd($this -> value($node -> getAttribute('shell')));
				$result -> setAttribute('shell', $command);
				exec($command, $out, $status);
				$content = join("\n", $out);
			}
		}
		$result -> nodeValue = $content;
		return $result;
	}
?>
