<?php
	private function parseRecaptcha($node)
	{
		if ($this -> validateAction ($node))
		{
			$resp = recaptcha_check_answer (RECAPTCHA_PRIVATE_KEY,
			$_SERVER["REMOTE_ADDR"],
			$_POST["recaptcha_challenge_field"],
			$_POST["recaptcha_response_field"]);

			if (!$resp->is_valid)
			{
				return $this -> simpleError($node -> nodeName, $resp->error);
			}
			else
			{
				$ok = $this -> dom -> createElement('ok');
				$result = $this -> dom -> createElement($node -> nodeName);
				$result -> appendChild($ok);
				return $result;
			}
		}
		else
		{
			$html = recaptcha_get_html(RECAPTCHA_PUBLIC_KEY);
			return $this -> dom -> createElement($node -> nodeName, $html);
		}
	}
?>
