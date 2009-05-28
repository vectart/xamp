<?php

	class xparse {
		public	$xml,
			$page,
			$tableStatus = array(),
			$dbcon,
			$cache,
			$globals = array(),
			$checkAtrribs = array (
					 	'is'	=> '=#value#',
					 	'in'	=> ' in (#value#)',
					 	'like'	=> ' LIKE #value#',
					 	'not'	=> '<> #value#',
					 	'ne'	=> '<> #value#',
						'lt'	=> '< #value#',
						'gt'	=> '> #value#',
						'ltis'	=> '<= #value#',
						'gtis'	=> '>= #value#',
						'value'	=> ''
					);		

		private $sql,
			$join = '',
			$from,
			$where = '',								
			$table, 
			$logger,
			$logger_start,
			$registered = '(get|post|session|cookie|server|path|globals|server)';
    			
			
		public function xparse () {
			if(DB_USER)
			{
				$this -> dbcon = new dbcon (DB_USER, DB_PASS, DB_NAME, DB_HOST, DB_PORT, true);
				$this -> dbcon -> query ("SET NAMES utf8 COLLATE 'utf8_general_ci'");
				$tables = $this -> dbcon -> query ("show table status") -> fetch_assoc_all();
				foreach($tables as $table) $this -> tableStatus[$table['Name']] = $table['Update_time'];
			}
		}	
			
			
		public function __call ($name, $arg) {
			$node = $arg[0];

			switch ($node -> nodeType) {

				case XML_ATTRIBUTE_NODE:	
					return $this -> attr ($node);				
					break;
				case XML_ELEMENT_NODE:				
					return $this -> parse ($node);
					break;
				case XML_TEXT_NODE:	
					return $this -> dom -> createTextNode ($this -> value ($node -> nodeValue));
					break;
				default:
					return $node;
					break;			
			}
				
		}
		
		private function parseAttributes ($node, $result) {
			if (!empty ($node -> attributes))
				foreach ($node -> attributes as $attr)
					if (strlen ($attr -> nodeName))
						$result -> appendChild ($this -> {'attr'.ucfirst ($attr -> nodeName)} ($attr));
		}
		
		private function parseTags ($node, $result) {
			if ($node -> hasChildNodes ())
			
				foreach ($node -> childNodes as $child) {
					if($child -> nodeType == XML_ELEMENT_NODE && $child -> hasAttribute ('static'))
					{
						$result -> appendChild ($this -> dom -> importNode ($child, true));
					}
					elseif ($child -> nodeType != XML_COMMENT_NODE)
					{
						$result -> appendChild ($this -> {'parse'.ucfirst ($child -> nodeName)} ($child));
					}
				}		

		}
		
		public function parse ($node) {
			$result = $this -> dom -> createElement ($node -> nodeName);
			$this -> parseAttributes ($node, $result);
			$this -> parseTags ($node, $result);		
			return $result;
		}			
		
		public function parsePage ($node) {
			$this -> page = $this -> dom -> createElement ('page');
			$this -> dom -> appendChild ($this -> page);
			$this -> parseAttributes ($node, $this -> page);
			$this -> parseTags ($node, $this -> page);
		}
		
		
		private function attr ($node) {
			$node = $this -> dom -> importNode ($node, true);
			$node -> nodeValue = htmlspecialchars($this -> value ($node -> nodeValue));
			return $node;
		}
    
    		public function value($value, $setup = false)
    		{
    			if (!strlen(trim($value))) return '';

    			$result = preg_replace_callback('/'.$this->registered.'\:([a-zA-Z0-9\_]+)/', 'insertData', $value);
    			$result = preg_replace_callback("/xpath\:([\/\ \[\]a-z\'A-Z0-9\(\)\@\:\!\=\>\<\_\-]+)/", 'insertXML', $result);
    			
    			return $result;
    		}
		
		public function parseSelect ($node, $limit = false) {
			if (!$this -> validateAction ($node)) return  $this -> simpleError ($node -> nodeName, 'invalid action');
			$ch = '';
			$sql = '';
			$xml = false;
			$result = array();
if($node -> hasAttribute('cache'))
{
	$ch = $this -> value($node -> getAttribute('cache')) . $this -> checkTables($node);
	$xml = $this->cacheGet($ch);
}

if(!$xml)
{
			$sql = $node -> hasAttribute ('sqlcache') ? 'SELECT SQL_CACHE ' : 'SELECT ';
			$this -> join = '';
			$name = $node -> hasAttribute ('name') ? $node -> getAttribute ('name') : 'select';			
			$table = $node -> getAttribute ('table');
			$countFields = 0;
			foreach ($node -> childNodes as $child) {
				if ($this -> isXmlNode ($child)) {
					if ($child -> nodeName == 'where') continue;
					if (!$child -> hasChildNodes ()) {
						$alias = $child -> hasAttribute ('as') ? $child -> getAttribute ('as') : '';
						$sql .= ($child -> hasAttribute ('value')) ? $child -> getAttribute ('value') : (  $child -> hasAttribute ('function') ? $child -> getAttribute ('function').'('.$table.'.'.str_replace ('allfields', '*', $child -> nodeName).')' : $table.'.'.str_replace ('allfields', '*', $child -> nodeName)  );
						$sql .=	(!empty ($alias) ? ' AS '.$alias : '').', ';
						$countFields++;
					}else	
						$sql .= $this -> parseJoin ($child, $table);					
				}		
			}
			if($countFields == 0)
			{
				$sql .= '*  ';
			}
			$from = ($table) ? ' FROM '.$table : '';
			$where_el = $node -> getElementsByTagName('where');
			$where = ' ';
			if($where_el -> length != 0)
			{
				if ($this -> validateAction ($where_el -> item(0)))
				{
					$where = ' WHERE '.$this -> parseWhereChild($where_el -> item(0));
				}
			}

			$group = $node -> hasAttribute ('group') ? ' GROUP BY '.$node -> getAttribute ('group') : ' ';
			$order = $node -> hasAttribute ('order') ? ' ORDER BY '.$this->value($node -> getAttribute ('order')) : ' ';
			$limit = $this -> parseLimit ($node);
			$sql = substr ($sql, 0 , strlen ($sql)-2).$from.' '.$this -> join.$where.$group.$order.$limit;
			$has_error = strstr ($sql, '#xamp#') ? true : false;									
				if ($has_error)  return $this -> simpleError ($name, $sql);
					$dbcon = $this -> dbcon;
					$result = $dbcon -> query ($sql) -> fetch_assoc_all ();
					$xml = $this -> mysqlToXML ($node, $result);
if($node -> hasAttribute('cache')) $this->cacheSet($ch, $xml);
}
					$resultNode = $this -> dom -> createDocumentFragment();
					@$resultNode -> appendXML($xml);

					if($result && (!$resultNode -> firstChild || $resultNode -> firstChild -> childNodes -> length != count($result)))
					{
						$xml = $this -> mysqlToXML ($node, $result, true);
						$resultNode = $this -> dom -> createDocumentFragment();
						$resultNode -> appendXML($xml);
					}
			return $resultNode;
		}
		
		private function checkTables($node)
		{
			$tables = '';
			$xpath = new DOMXpath ($this -> config);
			$result = $xpath -> evaluate("descendant-or-self::*/@table", $node);
			
			foreach($result as $t)
			{
				$tables .= "_".$t->nodeValue.preg_replace('/([^0-9]|2009)/i', '', $this->tableStatus[$t->nodeValue]);
			}
			
			return $tables;
		}

		function micro_time()
		{
			$timearray = explode(" ", microtime());
			return ($timearray[1] + $timearray[0]);
		}
		
		private function parseJoin ($node, $table_previous) {
			$key = $node -> getAttribute ('key');
			$id = $node -> getAttribute ('id');
			$table = $node -> getAttribute ('table');
			$key = !empty ($key) ? $key : $table.'_id';
			$id = !empty ($id) ? $table.'.'.$id : $table.'.id';
			$sql = '';
			
			$this -> join .= ' '.$node -> getAttribute ('type').'
			JOIN '.$table.'
			on '.$table_previous.'.'.$key.'='.
			(($node -> hasAttribute ('function')) ? $node -> getAttribute ('function').'(':'').
			$id.
			(($node -> hasAttribute ('function')) ? ')':'');

				foreach ($node -> childNodes as $child) {
					if ($this -> isXmlNode ($child)) {
						if ($child -> hasChildNodes ()) $sql .= $this -> parseJoin ($child, $table);
						else {
							$alias = $child -> hasAttribute ('as') ? $child -> getAttribute ('as') : '';
							$sql .= ($child -> hasAttribute ('value')) ? $child -> getAttribute ('value') : (  $child -> hasAttribute ('function') ? $child -> getAttribute ('function').'('.$table.'.'.str_replace ('allfields', '*', $child -> nodeName).')' : $table.'.'.str_replace ('allfields', '*', $child -> nodeName)  );
							$sql .=	(!empty ($alias) ? ' AS '.$alias : '').', ';
						}
					}	
				}
							
			return $sql;	
		}
		
		private function parseWhereCond ($node) {
			$result = '';
			$prev = $node -> previousSibling;
			while ($prev && $prev -> nodeType != XML_ELEMENT_NODE) $prev = $prev -> previousSibling;
			if ($node -> nodeType == XML_ELEMENT_NODE && $this -> validateAction ($node))
			{
				$result .= ($prev && $prev -> nodeType == XML_ELEMENT_NODE) ? ' '. $node -> nodeName .' ( ' : ' ( ';
				$result .= $this -> parseWhereChild($node) . ' ) ';
			}
			return $result;
		}

		private function parseWhereChild ($node) {
			$result = '';
			$hasTable = $node;
			while( strlen ($table = $hasTable -> getAttribute ('table')) == 0) $hasTable = $hasTable -> parentNode;
			foreach ($node -> childNodes as $child) {
				if ($this -> isXmlNode ($child))
				{
					if( in_array($child -> nodeName, array('or', 'and')) )
					{
						$result .= $this -> parseWhereCond($child);
					}
					else
					{
						$result = $this -> parseCond ($child, $table);
					}
				}	
			}
			return $result;
		}
	
		private function parseCond ($child, $table) {
			$result = false;
			$compare = $this -> checkCompare($child, $table);
			foreach ($child -> attributes as $attr)	if($this -> isXmlNode ($child)) 
			{
				$val = $this -> attr ($attr);
				$val = $val -> nodeValue;
				$function = $this -> checkFunction($child, $val);
				if(array_key_exists($attr -> nodeName, $this -> checkAtrribs))
				{
					if ($this -> parseParam ($child, $val))
					{
						if($function == '')
						{
							$function = ($attr -> nodeName !== 'in') ? '\''.$val.'\'' : $val;
						}
						$result = $compare.str_replace('#value#', $function, $this -> checkAtrribs[$attr -> nodeName]);
					}
					else
					{
						$result = '#xamp#'.$attr -> nodeName.' => '.$child -> getAttribute ('is').'Error in match#xamp#';
					}
				}
			}
			if(!$result)
			{
				if ($child -> hasAttribute('function'))
				{
					$result = $compare.'='.$child -> getAttribute ('function').'()';
				}
				if ($child -> childNodes -> length != 0)
				{
					$result = $compare.'=\''.addslashes($this->value($this -> config -> saveXML( $child ))).'\'';
				}
			}
			return $result;	
		}
		
		private function checkCompare($node, $table)
		{
			$table = $node->hasAttribute('table') ? $node->getAttribute('table') : $table;
			if($node -> hasAttribute ('compare'))
			{
				$str = $node -> getAttribute('compare');
				if($this -> closedBrackets($str))
				{
					return $str;
				}
				else
				{
					return $str.'(`'. $table.'`.`'.$node->nodeName .'`)';
				}
			}
			else
			{
				return '`'.$table.'`'.'.`'.$node->nodeName.'`';
			}
		}
		private function checkFunction($node, $value)
		{
			if($node -> hasAttribute ('function'))
			{
				$str = $node -> getAttribute('function');
				if($this -> closedBrackets($str))
				{
					return $str;
				}
				else
				{
					return $str.'(\''. $value .'\')';
				}
			}
			else
			{
				return '';
			}
		}
		private function closedBrackets($str)
		{
			return (preg_match('/(\(|\))/', $str) && (preg_match('/\(/', $str) == preg_match('/\)/', $str)));
		}
		
		private function parseParam ($node, $value) {
			if (!$node -> hasAttribute ('match')) return true;
			$match = $node -> getAttribute ('match');
			if (empty ($match)) return false; 
			return preg_match ('/'.$match.'/u', $value) ? true : false;
		}
		
		private function parseLimit ($node) {
			$start = $node -> hasAttribute ('start') ? $this -> value ($node -> getAttribute ('start')) : 0;
			$start = empty ($start) ? 0 : $start;
			return $node -> hasAttribute ('limit') ? ' LIMIT '.$start.', '.$node -> getAttribute ('limit') : '';		
		}
	
		public function parseInsert ($node) {	
			if (!$this -> validateAction ($node)) return  $this -> simpleError ($node -> nodeName, 'invalid action');
			$table = $node -> getAttribute ('table');
			$name = $node -> hasAttribute ('name') ? $node -> getAttribute ('name') : 'insert';
			$parts = array ();
		
				foreach ($node -> childNodes as $child)
					if ($this -> isXmlNode ($child)) $parts[] = $this -> parseCond ($child, $table);	
			
			$sql = 'INSERT INTO `'.$table.'` set '.implode (',', $parts);
			$has_error = strstr ($sql, '#xamp#') ? true : false;
			$dbcon = $this -> dbcon;
				if (!$has_error) {
					$dbcon -> query ($sql);
					$result = $this -> dom -> createElement ($name, $dbcon -> insert_id);
				}else	$result = $this -> simpleError ($name, $sql);
				
			return $result;
		}	
	
		public function parseUpdate ($node) {	
			if (!$this -> validateAction ($node)) return  $this -> simpleError ($node -> nodeName, 'invalid action');
			$table = $node -> getAttribute ('table');
			$name = $node -> hasAttribute ('name') ? $node -> getAttribute ('name') : 'update';			
			$parts = array ();
		
			foreach ($node -> childNodes as $child)
				if ($child -> nodeName !== 'where' && $this -> isXmlNode ($child)) $parts[] = $this -> parseCond ($child, $table);	
				
			$limit = $this -> parseLimit ($node);
			$where = ($where_el = $node -> getElementsByTagName('where')) ? ' WHERE '.$this -> parseWhereChild ($where_el -> item(0)) : ' ';
			
			$sql = 'UPDATE `'.$table."` SET ".implode (',', $parts). $where.$limit;
			$has_error = strstr ($sql, '#xamp#') ? true : false;			
			
				if (!$has_error) {		 			
					$dbcon = $this -> dbcon;
					$dbcon -> query ($sql);
					$result = $this -> dom -> createElement ($name, $dbcon -> affected_rows);
				}else	$result = $this -> simpleError ($name, $sql);	

			return 	$result;
		}
	
		public function parseDelete ($node) {	
			if (!$this -> validateAction ($node)) return $this -> simpleError ($node -> nodeName, 'invalid action');
			$name = $node -> hasAttribute ('name') ? $node -> getAttribute ('name') : 'delete';		
			$table = $node -> getAttribute ('table');
			$where = $node -> getElementsByTagName ('where');
			$limit = $this -> parseLimit ($node);						
			
			$sql = 'DELETE FROM `'.$table.'`  WHERE '.$this -> parseWhereCond ($where -> item(0)).$limit;
			$has_error = strstr ($sql, '#xamp#') ? true : false;									

				if (!$has_error) {
					$dbcon = $this -> dbcon;
					$dbcon -> query ($sql);			
					$result = $this -> dom -> createElement ($name, $dbcon -> affected_rows);			
				}else	$result = $this -> simpleError ($name, $sql);	
			return $result;	
		}	
		
		public function mysqlToXML ($node, $result, $breaked = false) {
			$name = $node -> hasAttribute ('name') ? $node -> getAttribute ('name') : $node -> getAttribute ('table');
			$xml = '<'.$name.'>';
				if (count ($result)) {
					foreach ($result as &$res) {
						$xml .= '<row>';
						if (count ($res))
							foreach ($res as $tagName => &$value)
							{
								$value = preg_replace('/&(?!((#[0-9]+|[a-z]+);))/mi', '&amp;', $value);
								$xml .= '<'.$tagName.'>'.(($breaked) ? '<![CDATA['.$value.']]>' : $value).'</'.$tagName.'>';
							}
						$xml .= '</row>';
					}
				}
			$xml .= '</'.$name.'>';

			return $xml;	
		}
		
		public function parsePath ($node) {
			$path = $this -> dom -> createElement('path');
			$req = $this -> dom -> createAttribute('request');
			$req -> nodeValue = $_GET['request'];
			$path -> appendChild( $req );
			if (count($this -> path))
			foreach( $this -> path as $p )
			{
				$path -> appendChild( $this -> dom -> createElement('dir', $p) );
			}
			return $path;
		}
		
		private function validateAction ($node) {
			if (!$node -> hasAttribute ('action'))	return true;
			$action = $this -> value ($node -> getAttribute ('action'));
			if (!empty ($action)) return true;
			else return false;
		}
		
		
		private function isXmlNode ($node) {
			if ($node -> nodeType == XML_ELEMENT_NODE) return true;
			else return false;
		}
		
		private function parsePost ($node) {
			$result = $this -> makeNode ('post');
				if (!empty ($_POST)) {
					foreach ($_POST as $key => $pst) {
						if (strlen ($pst))
							$result -> appendChild ($this -> makeNode ($key, $pst));
					}	
				}
			return $result;	
		}
		
		private function parseGet ($node) {
			$result = $this -> makeNode ('get');
				if (!empty ($_GET)) {
					foreach ($_GET as $key => $get) {
						$result -> appendChild ($this -> makeNode ($key, $get));
					}	
				}
			return $result;	
		}		
		
		private function parseVar ($node) {
			if (!$this -> validateAction ($node)) return  $this -> simpleError ($node -> nodeName, 'invalid action');		
			if (!$node -> hasAttribute ('name')) return $this -> simpleError ('var', '@name is not defined');
			if (!$node -> hasAttribute ('value')) return $this -> simpleError ('var', '@value is not defined');
			$var_name = $node -> getAttribute ('name');
			$var_value = $node -> getAttribute ('value');
			
			preg_match('/'.$this->registered.'\:([a-zA-Z0-9\_]+)/', $var_name, $result);
			$type = $result[1];
			$name = $result[2];
			$value = $this -> value ($var_value);
			
		if($type === 'post')
			$_POST[$name] = $value;

		if($type === 'get')
			$_GET[$name] = $value;

		if($type === 'session')
			$_SESSION[$name] = $value;

		if($type === 'cookie')
			$_COOKIE[$name] = $value;

		if($type === 'globals')
			$this->globals[$name] = $value;

			$var = $this -> dom -> createElement ('var', $value);
			$var -> setAttribute ('name', $name);
			$var -> setAttribute ('type', $type);

			return $var;
		}
		
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
			$body = $node -> getElementsByTagName ('body') -> item (0);
			$html = $body -> getElementsByTagName ('xsl') -> item(0);
			$html = $this -> parseXsl ($html);
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
			
			
			$mail -> send($to, $subject, $from."@".$_SERVER['HTTP_HOST'], 'utf-8',($node -> hasAttribute ('gate')) ? $node -> getAttribute ('gate') : false);
			$result = $this -> dom -> createElement('mail', $html);
			return $result;
		}

		private function parseImagick ($node) {
			$xpath = new DOMXpath ($this -> config);			
			$nodes = $xpath -> query ("*[name() = 'form' or name() = 'url' or name() = 'in']", $node);
			if (!$nodes -> length) return  $this -> simpleError ($node -> nodeName, 'repository is not defined');
			$output = $xpath -> query ("*[name() = 'out']", $node);
			if (!$output -> length) return  $this -> simpleError ($node -> nodeName, 'Output is not defined');
			$imagickresult = $this -> dom -> createElement ('imagick');
			
				foreach ($nodes as $child) {
					if (!$this -> validateAction ($child)) return  $this -> simpleError ($child -> nodeName, 'invalid action');
					if (!$child -> hasAttribute ('folder')) return  $child -> simpleError ($child -> nodeName, '@folder is not defined');
					$allFiles = array ();
						switch ($child -> nodeName) {
							case 'form':
								if (!$child -> hasAttribute ('field')) return $this -> simpleError ($child -> nodeName, '@field is not defined');
								$xfiles = new xfiles ();
								$files = $xfiles -> uploadFiles ($child -> getAttribute ('field'));
								if (count ($files) && !empty ($files))
								{
									$temp['folder'] = $this -> value ($child -> getAttribute ('folder'));
									$temp['files'] = $files;
									$allFiles[] = $temp;
									unset ($temp);
								}	
								break;
							
							case 'in':
								if (!$child -> hasAttribute ('from')) return $this -> simpleError ($child -> nodeName, '@from is not defined');
								$temp['folder'] = $this -> value ($child -> getAttribute ('folder'));
								$temp['files'] = array (0 => SITE_PATH.$temp['folder'].$this -> value ($child -> getAttribute ('from')));
								$allFiles[] = $temp;
								unset ($temp);								
								break;	
							
							case 'url':
								if (!$child -> hasAttribute ('from')) return $this -> simpleError ($child -> nodeName, '@from is not defined');							
								$real_name = TMP_FILE_DIR.microtime(true);
								copy ($child -> getAttribute ('from'), $real_name);
								$temp['folder'] = $this -> value ($child -> getAttribute ('folder'));
								$temp['files'] = array (0 => $real_name);
								$allFiles[] = $temp;
								unset ($temp);																
								break;								
						}
						
				}
				if (count ($allFiles) && !empty ($allFiles)) {
					foreach ($allFiles as $file) {

						if (!is_dir (SITE_PATH.$file['folder'])) mkdir (SITE_PATH.$file['folder'], 0777);

						foreach ($file['files'] as $fl) {
							$newimg = $this -> dom -> createElement ('image');
							$newimg -> setAttribute ('folder', $file['folder']);

							foreach ($output as $out) {
								if ($out -> hasAttribute ('name')) {								
									$img = new imagick ($fl);
									
									$width = $img -> getImageWidth ();
									$height = $img -> getImageHeight ();
									
									$modify = $xpath -> query ("*[name() = 'rotate' or name() = 'modulate' or name() = 'sharpen' or name() = 'crop' or name() = 'watermark' or name() = 'enhance']", $out);
									if (!$modify -> length) $modify = false;									
									
									if ($out -> hasAttribute ('width') || $out -> hasAttribute ('height')) {
										$need_width = $out -> hasAttribute ('width') ? $this -> value ($out -> getAttribute ('width')) : 0;
										$need_height = $out -> hasAttribute ('height') ? $this -> value ($out -> getAttribute ('height')) : 0;
										
											if ($need_width && $need_height) {
												if ($width > $height) 
													$img -> thumbnailImage ($need_width, 0, false);													
												else	
													$img -> thumbnailImage (0, $need_height, false);																									
											}elseif ($need_width || $need_height){
												$img -> thumbnailImage ($need_width, $need_height, false);																																					
											}
									}	
								
																						
									if ($modify) {
										foreach ($modify as $mod) {
											switch ($mod -> nodeName) {
												case 'rotate':
													$img -> rotateImage(new ImagickPixel(), ($mod -> hasAttribute ('degrees') ? $this -> value ($mod -> getAttribute ('degrees')) : 1));
													break;
												
												case 'sharpen':
													$img -> sharpenImage(($mod -> hasAttribute ('radius') ? $this -> value ($mod -> getAttribute ('radius')) : 1), ($mod -> hasAttribute ('sigma') ? $this -> value ($mod -> getAttribute ('sigma')) : 1));
													break;

												case 'modulate':
													$img -> modulateImage(($mod -> hasAttribute ('brightness') ? $this -> value ($mod -> getAttribute ('brightness')) : 100), ($mod -> hasAttribute ('saturation') ? $this -> value ($mod -> getAttribute ('saturation')) : 100), ($mod -> hasAttribute ('hue') ? $this -> value ($mod -> getAttribute ('hue')) : 100));
													break;

												case 'crop':
													$img -> cropThumbnailImage (($mod -> hasAttribute ('width') ? $this -> value ($mod -> getAttribute ('width')) : 0), ($mod -> hasAttribute ('height') ? $this -> value ($mod -> getAttribute ('height')) : 0));
													break;
													
												case 'enhance':
													$img -> enhanceImage();
													break;
													
												case 'watermark':
													$wm_path = $mod -> getAttribute ('path');
													$width = $img -> getImageWidth ();
													$height = $img -> getImageHeight ();													
													$watermark = new imagick (SITE_PATH.$wm_path);
													$wm_width = $watermark -> getImageWidth ();
													$wm_height = $watermark -> getImageHeight ();
													$x = $width-10-$wm_width;
													$y = $height-10-$wm_height;
													$img -> compositeImage ($watermark, $watermark -> getImageCompose(), $x, $y);
													break;
											}		
										}
									}

									$filename = $this->value($out->getAttribute('name')).'.'.$out->getAttribute('ext');
									if($out->getAttribute('ext') == 'jpeg')
									{
										//$img -> setCompression(imagick::COMPRESSION_JPEG);
										if($out -> hasAttribute('quality'))
										{
											if($out -> getAttribute('quality') == 100)
											{
												$img -> setCompression(imagick::COMPRESSION_LOSSLESSJPEG);
												$img -> setCompressionQuality(100);
											}
											else
											{
												$img -> setCompressionQuality($out -> getAttribute('quality'));
											}
										}
										else
										{
											$img -> setCompressionQuality(70);
										}
									}
									
									$format = ($out->getAttribute('ext') == 'jpg') ? 'jpeg' : $out->getAttribute('ext');
									
									$img -> setImageFormat($format);
									
									if ($img -> writeImage(SITE_PATH.$file['folder'].$filename)) {
										$newimg -> appendChild ($this -> dom -> createElement ('folder', $file['folder']));
										$newimg -> appendChild ($this -> dom -> createElement ('name', $filename));
										$newimg -> appendChild ($this -> dom -> createElement ('compress', $img -> getCompression()));
										$newimg -> appendChild ($this -> dom -> createElement ('quality', $img -> getCompressionQuality()));
										$imagickresult -> appendChild($newimg);
										$img -> clear();
									}	
								}
							}
						}
					}
				}
			
			$xfiles = new xfiles ();
			$xfiles -> cleanDir();
			
			return $imagickresult;
				
		}
		
		private function parseXsl ($node) {
			if (!$node -> hasAttribute ('file')) 
				return $this -> simpleError ($node -> nodeName, '@file is not defined');
			if (!file_exists (VIEW_PATH.$node -> getAttribute ('file'))) 
				return $this -> simpleError ($node -> nodeName, 'xsl file not found');
			$xsl = $this -> load (VIEW_PATH.$node -> getAttribute ('file'));
			$proc = new XSLTProcessor;
			$proc -> importStyleSheet ($xsl);
			return $proc -> transformToXML ($this -> dom);
		}
		
		public function cacheGet($name)
		{
			return apc_fetch($name);
		}
		
		public function cacheSet($name, $content = '')
		{
			apc_store($name, $content, 15);
		}
		
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

		private function parseRedirect ($node) {
			if ($this -> validateAction ($node))
			{
				header('Location: '.$this -> value($node -> getAttribute('to')));
				exit();
			}
			$result = $this -> dom -> createElement ('redirect');
			return $result;
		}

		private function parseFile ($child) {
			if (!$child -> hasAttribute ('path')) return $this -> simpleError ($node -> nodeName, '@path is not defined');
			$path = SITE_PATH.$child -> getAttribute ('path');
			$delete = $child -> hasAttribute ('delete') ? true : false;
			if (!file_exists ($path)) return $this -> simpleError ($node -> nodeName, 'path is not found');
			$files = $this -> parseFiles ($path, $delete);

			$filestat = $this -> dom -> createElement ('filestat');
			$filestat -> setAttribute ('path', $path);
			
				if (count ($files)) {
					foreach ($files as &$file) {
						$fil = $this -> dom -> createElement (($file['file_type'] ? 'folder' : 'file'));
						$fil -> setAttribute ('name', $file['name']);
						unset ($file['file_type'], $file['file_type']);
							foreach ($file as $key => &$fl)
								$fil -> appendChild ($this -> dom -> createElement ($key, $fl));
						
						$filestat -> appendChild ($fil);
					}
				}
				
			return $filestat;
		}
		
		private function parseFiles ($path, $delete) {
			if (is_file ($path)) {			
				$attributes = array ();
				$attributes[0]['filesize'] = filesize ($path);
				$attributes[0]['file_type'] = 0;
				$attributes[0]['name'] = substr ($path, strrpos($path, '/')+1, strlen ($path)-1);				
				$attributes[0]['make_date'] = date("F d Y H:i:s.", filectime($path));
				$attributes[0]['change_date'] = date("F d Y H:i:s.", filemtime($path));
				$img = new imagick ($path);
				
					if (is_object ($img)) {
						$img_info = $img -> identifyImage ();						
						$img_info['width'] = $img_info['geometry']['width'];
						$img_info['height'] = $img_info['geometry']['height'];						
						unset ($img_info['geometry']);
						$img_info['x'] = $img_info['resolution']['x'];
						$img_info['y'] = $img_info['resolution']['y'];												
						unset ($img_info['resolution']);
						$attributes[0] = array_merge ($attributes[0], $img_info);
					}	 
					
				if ($delete) unlink ($path);
				return $attributes;
			}elseif (is_dir ($path)) {
				$openeddir = opendir ($path);

					while (($file = readdir ($openeddir)) !== false) {
						if (is_file ($path.$file)) {
							$temp = $this -> parseFiles ($path.$file, $delete);
							$files[] = $temp[0];
						}
						
						if (is_dir ($path.$file) && $file != '.' && $file != '..') {
							$attributes = array ();
							$attributes['filesize'] = filesize ($path);
							$attributes['file_type'] = 1;
							$attributes['name'] = $file;
							$attributes['make_date'] = date("F d Y H:i:s.", filectime($path));
							$attributes['change_date'] = date("F d Y H:i:s.", filemtime($path));
							$files[] = $attributes;							
						}
					}	
						
				return $files;		
			}
			
		}
			
	}
?>
