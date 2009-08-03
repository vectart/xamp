<?php 
/**
 * 	GuestZilla.com
 *
 *  @desc Mailing class - use it to create messages and send mail to recipients
 */

class mail {
	var $boundary 	= "";
	var $message	= array();
	var $html		= array();
	var $headers 	= "";
	var $attachs 	= array();

/**
 * @desc	Constructor - create boundary & common headers	
 */
	function mail() {
		$this->boundary = md5(microtime(true));
		$this->headers = 	"MIME-Version: 1.0\n"; 
	}

/**
 * @desc	Wraps a text after each 73 characters	
 * @param	$text	string	message body
 */
	function _wrapText($text, $cut = false) {
		return wordwrap($text, 73, "\n", $cut);
	}

/**
 * @desc	Set a text message content	
 * @param	$text	string	message body
 */
	function setText($text, $charset = 'utf-8') {
		$this->message['headers'] = "Content-type: text/plain; charset=".$charset."\n"
									."Content-Transfer-Encoding: 8bit\n\n";
		$this->message['body'] = preg_replace("/(\r\n|\r|\n)/", "\n", $text);
	}

/**
 * @desc	Sets an		html message content, and (optionally) text part of the message
 * @param	$html		string		message body, in html format
 * @param	$charset	srting		html charset, default us-ascii
 * @param	$text		boolean		set to true if you need to add auto parsed text
 */
	function setHTML($html, $charset = 'utf-8', $addText = false) {
/*		if ($addText) {
			$text = strip_tags($html, '<a>');
			$text = preg_replace("#<a[^>]* href=\"([^\"]+)[^>]+>[^<]+</a>#is", "\$1", $text);
			$text = str_replace(array('&gt;', '&lt;', '&quot;', '&amp;'), array('>', '<', '"', '&'), $text);
			$this->setText($text);
		}

		$this->html['headers'] =	"Content-Type: text/html; charset=".$charset."\n"
									."Content-Transfer-Encoding: 8bit\n"
									."Content-Disposition: inline; filename=message.html\n\n";
		$this->html['body'] = preg_replace("/(\r\n|\r|\n)/", "\n", $this->_wrapText($html));
*/

		$this->html['headers'] .= 'Content-type: text/html; charset=' .$charset. "\n";
		$this->html['body'] = preg_replace("/(\r\n|\r|\n)/", "\n", $this->_wrapText($html));
	}

/**
 * @desc	Adds an attachment to message
 * @param	$content	string	Attachment contents
 * @param	$filename	string	Attachment filename
 * @param	$mime_type	string	Attachment mime-type (default 'application/octet-stream')
 */
	function addAttachment($content, $filename, $mime_type = "application/octet-stream") {
		$filename = '"'.str_replace(array('"', "\r\n"), array("'", ""), $filename).'"';
		
		$this->attachs[] = 	array('headers' => "Content-type: $mime_type; name=".$filename."\n"
												."Content-Transfer-Encoding: base64\n"
												."Content-Disposition: attachment; filename=$filename\n\n",
								  'body'	=> $this->_wrapText(base64_encode($content), true));
	}

/**
 * @desc	Sends mail to recipient
 * @param	$to			string		Recipient's e-mail address
 * @param	$subject	string		Mail subject
 * @param	$from		string		From address (default = MAIL_SYSTEM)
 */
	function send($to, $subject, $from = _FROM, $charset = 'utf-8', $gate = false) {
		$subject = trim($subject);
		//#$subject = rtrim('=?'.$charset.'?B?'.base64_encode($subject), '=').'?=';
		$subject = '=?' . $charset . '?B?' . base64_encode($subject) . '?=';
		//$subject = '=?'.$charset.'?B?'.base64_encode($subject).'?=';
		
		$this->headers .= 'From: '.$from."\n";
		$this->headers .= 'Reply-To: '.($replyto ? $replyto : $from)."\n";
		$msg = '';
		if (!empty($this->attachs)) { // multipart message
			$this->headers .= "Content-type: multipart/mixed; boundary=".$this->boundary."\n";
			if (!empty($this->message['body'])) {
				$msg .=	"\n--".$this->boundary."\n"
						.$this->message['headers']
						.$this->message['body']."\n";
			}
			if (!empty($this->html['body'])) {
				$msg .=	"\n--".$this->boundary."\n"
						.$this->html['headers']
						.$this->html['body']."\n";
			}
			foreach ($this->attachs as $a) {
				$msg .=	"\n--".$this->boundary."\n"
						.$a['headers']
						.$a['body']."\n";
			}
			$msg .= "\n--".$this->boundary."--";
		} else {
			if (!empty($this->message) && !empty($this->html)) {
				$this->headers .= "Content-type: multipart/alternative; boundary=".$this->boundary."\n";
				$msg .=	"\n--".$this->boundary."\n"
						.$this->html['headers']
						.$this->html['body']."\n";
				$msg .=	"\n--".$this->boundary."\n"
						.$this->message['headers']
						.$this->message['body']."\n";
				$msg .= "\n--".$this->boundary."--";
			} elseif ($this->message) {
				$this->headers .= $this->message['headers'];
				$msg = $this->message['body']."\n";
			} elseif ($this->html) {
				$this->headers .= $this->html['headers'];
				$msg = $this->html['body']."\n";
			}
		}
		if($gate)
		{
			$postdata = "email=".urlencode($to)."&subject=".urlencode($subject)."&body=".urlencode($msg)."&headers=".urlencode($this->headers);
			
			$ch = curl_init($gate);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Expect:') );
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION  ,1);
			curl_setopt($ch, CURLOPT_HEADER,0);  // DO NOT RETURN HTTP HEADERS
			curl_setopt($ch, CURLOPT_RETURNTRANSFER  , 0);  // RETURN THE CONTENTS OF THE CALL
			
			ob_start();
			$Rec_Data = curl_exec($ch);
			ob_end_clean(); 

			curl_close($ch);
			return true;
		}
		else
		{
			if (mail($to, trim($subject), $msg, trim($this->headers))) return true;
		}
	}
}

/* EXAMPLE
$mail = new mail;
$mail->setText("myText");
$mail->setHTML("<h1>myHTML</h1>", 'UTF-8');
$mail->addAttachment(file_get_contents("/home/nagash/week.zip"), "week.zip");
$mail->addAttachment(file_get_contents("/home/nagash/week.zip"), "week1.zip");
$mail->send("site@giestzilla.com", "test", "test@com.com");
print "done";
*/
?>
