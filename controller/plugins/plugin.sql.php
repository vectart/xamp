<?php

	public function parseSelect ($node) {
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
?>
