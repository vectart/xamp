<?php
	private function parseMail ($node) {
		if (!$this -> validateAction ($node)) return $this -> simpleError ($node -> nodeName, 'invalid action');
		if (!$node -> hasAttribute ('from')) return $this -> simleError ('mail', '@from is not defined');
		if (!$node -> hasAttribute ('to')) return $this -> simleError ('mail', '@to is not defined');
		if (!$node -> hasAttribute ('subject')) return $this -> simleError ('mail', '@subject is not defined');
		if (!$node -> hasChildNodes ()) return $this -> simleError ('mail', 'Mail body is not defined');
		$from = $this -> value ($node -> getAttribute ('from'));
		$to = $this -> value ($node -> getAttribute ('to'));
		$subject = $this -> value ($node -> getAttribute ('subject'));
		$dom = new DOMDocument('1.0', 'utf-8');
		$dom -> resolveExternals = true;			
		$dom -> preserveWhiteSpace = false;			
		$dom -> xinclude ();									
		$dom -> normalizeDocument ();
		$body = $node -> getElementsByTagName ('body') -> item(0);
		if($body -> getElementsByTagName('xsl') -> length == 0)
		{
			$html = $this -> value($body->textContent);
		}
		else
		{
			$html = $body -> getElementsByTagName ('xsl') -> item(0);
			$html = $this -> parseXsl($html);
		}
		$mail = new mail;
						
		$mail -> setHTML($html, 'UTF-8');			
		$attachments = $node -> getElementsByTagName ('file');
		
		foreach ($attachments as $att) {
			if ($att -> hasAttribute ('src')) {
				$src = SITE_PATH.$att -> getAttribute ('src');
				$filename = $att -> hasAttribute ('name') ? $att -> getAttribute ('name') : (explode ('/', $src));
				
					if (file_exists ($src))
						$mail -> addAttachment(file_get_contents($src), $filename);
						
			}elseif ($att -> hasAttribute ('form')) {
				$xfile	= new xfiles ();
				$files = $xfiles -> uploadFiles ($att -> getAttribute ('form'));
				
					foreach ($files as $file)
						$mail -> addAttachment(file_get_contents(TMP_FILE_DIR.$file), end (explode ('/', TMP_FILE_DIR.$file)));
			}
		}
		
		$mail -> send($to, $subject, $from, 'utf-8',($node -> hasAttribute ('gate')) ? $node -> getAttribute ('gate') : false);
		$result = $this -> dom -> createElement('mail', $html);
		$result -> setAttribute('subject', $subject);
		$result -> setAttribute('to', $to);
		$result -> setAttribute('from', $from);
		return $result;
	}
?>
