<?php 
 class xamp 
 { 
  
 
 
// plugin.redirect.php 
 
private function parseRedirect ($node)
{
if ($this -> validateAction ($node))
{
header('Location: '.$this -> value($node -> getAttribute('url')));
exit();
}
$result = $this -> dom -> createElement ('redirect');
return $result;
}
 
 
 
// plugin.exec.php 
 
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
 
 
 
// plugin.xsl.php 
 
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
 
 
 
// plugin.recaptcha.php 
 
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
 
 
 
// plugin.vars.php 
 
private function parsePost ($node)
{
return $this->xmlDump('post', $_POST);
}

private function parseGet ($node) {
return $this->xmlDump('get', $_GET);
}

private function xmlDump($name, $arr)
{
$result = $this -> makeNode ($name);
if (!empty ($arr)) {
foreach ($arr as $key => $pst) {
if (strlen ($pst))
if(!is_array($pst))
{
$pst = array($pst);
}
foreach($pst as $val)
{
$result -> appendChild ($this -> makeNode ($key, $val));
}
}
}
return $result;
}

private function parsePath ($node) {
$path = $this -> dom -> createElement('path');
$req = $this -> dom -> createAttribute('request');
$req -> nodeValue = $this -> request;
$path -> appendChild( $req );
if (count($this -> path))
foreach( $this -> path as $p )
{
$path -> appendChild( $this -> dom -> createElement('dir', $p) );
}
return $path;
}

private function parseVar ($node)
{
if (!$this -> validateAction ($node)) return  $this -> simpleError ($node -> nodeName, 'invalid action');
if (!$node -> hasAttribute ('name')) return $this -> simpleError ('var', '@name is not defined');
$var_name = $node -> getAttribute ('name');
preg_match('/'.$this->registered.'\:([a-zA-Z0-9\_]+)/', $var_name, $result);
$type = $result[1];
$name = $result[2];

if ($node -> hasAttribute ('value')) 
{
$var_value = $node -> getAttribute ('value');
$value = $this -> value ($var_value);
}

if(isset($value))
{
$var = $this -> appendValue($type, $name, $value);
}
else
{
$value = $this -> extractValue($type, $name);
$var = $this -> dom -> createElement ('var', $value);
$var -> setAttribute ('name', $name);
$var -> setAttribute ('type', $type);
}

if($node -> hasChildNodes())
{
$values = split('(^\'|\',\'|\'$)', $value);
foreach($values as $val)
{
if(strlen($val))
{
$tmp = $var -> appendChild($this -> appendValue($type, $name, $val));
foreach($node -> childNodes as $child)
{
$clone = $tmp -> appendChild($child -> cloneNode(true));
}
$this -> parse($tmp);
}
}
}

return $var;
}

private function appendValue($type, $name, $value)
{
if($type === 'post') $_POST[$name] = $value;
if($type === 'get') $_GET[$name] = $value;
if($type === 'session') $_SESSION[$name] = $value;
if($type === 'cookie') $_COOKIE[$name] = $value;
if($type === 'globals') $this->globals[$name] = $value;
$var = $this -> dom -> createElement ('var', $value);
$var -> setAttribute ('name', $name);
$var -> setAttribute ('type', $type);
return $var;
}
private function extractValue($type, $name)
{
if($type === 'post') return $_POST[$name];
if($type === 'get') return $_GET[$name];
if($type === 'session') return $_SESSION[$name];
if($type === 'cookie') return $_COOKIE[$name];
if($type === 'globals') return $this->globals[$name];
}
 
 
 
// plugin.sql.php 
 

private function parseSelect ($node) {
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
$sql .=(!empty ($alias) ? ' AS '.$alias : '').', ';
$countFields++;
}else
$sql .= $this -> parseJoin ($child, $table);
}
}
if($countFields == 0)
{
$sql .= $table.'.*  ';
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
$sql .=(!empty ($alias) ? ' AS '.$alias : '').', ';
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
foreach ($child -> attributes as $attr)if($this -> isXmlNode ($child)) 
{
$val = $this -> attr ($attr);
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

private function parseInsert ($node) {
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
}else$result = $this -> simpleError ($name, $sql);

return $result;
}

private function parseUpdate ($node) {
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
}else$result = $this -> simpleError ($name, $sql);

return $result;
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
}else$result = $this -> simpleError ($name, $sql);
return $result;
}

private function mysqlToXML ($node, $result, $breaked = false) {
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
 
 
 
// plugin.imagick.php 
 
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
 
 
 
// plugin.mail.php 
 
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
$xfile= new xfiles ();
$files = $xfiles -> uploadFiles ($att -> getAttribute ('form'));

foreach ($files as $file)
$mail -> addAttachment(file_get_contents(TMP_FILE_DIR.$file), end (explode ('/', TMP_FILE_DIR.$file)));
}
}


$mail -> send($to, $subject, $from."@".$_SERVER['HTTP_HOST'], 'utf-8',($node -> hasAttribute ('gate')) ? $node -> getAttribute ('gate') : false);
$result = $this -> dom -> createElement('mail', $html);
return $result;
}
 
 
 
// plugin.file.php 
 
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
 
 
 
// plugin.common.php 
 
public  $dom,
$xpath,
$config,
$is_ajax,
$path,
$dir,
$xsl,
$xml,
$page,
$tableStatus = array(),
$dbcon,
$cache,
$speedAnalyze,
$globals = array(),
$checkAtrribs = array (
 'is'=> '=#value#',
 'in'=> ' in (#value#)',
 'like'=> ' LIKE #value#',
 'not'=> '<> #value#',
 'ne'=> '<> #value#',
'lt'=> '< #value#',
'gt'=> '> #value#',
'ltis'=> '<= #value#',
'gtis'=> '>= #value#',
'value'=> ''
);

private $ret_xml,
$sql,
$join = '',
$from,
$where = '',
$table, 
$logger,
$logger_start,
$registered = '(get|post|session|cookie|server|path|globals|server)';

public function __construct ($request = '/', $speedAnalyze)
{
speedAnalyzer('Начинаем работу');
$this -> speedAnalyze = $speedAnalyze;
speedAnalyzer('Подключаемся к базе');
if(DB_USER)
{
$this -> dbcon = new dbcon (DB_USER, DB_PASS, DB_NAME, DB_HOST, DB_PORT, true);
$tables = $this -> dbcon -> query ("show table status") -> fetch_assoc_all();
foreach($tables as $table) $this -> tableStatus[$table['Name']] = $table['Update_time'];
}

speedAnalyzer('Считаем $request');
$s = split('/', $request);
foreach ($s as $p) if (!empty ($p)) $this -> path[] = $p;

$this -> request = $request;

speedAnalyzer('Создаем DOM');
$imp = new DOMImplementation;
$dtd = $imp -> createDocumentType('page', '', VIEW_PATH.'entities.dtd');

if (isset ($_GET['xml']))
$dtd = $imp -> createDocumentType ('page', '-//W3C//DTD HTML 4.01 Transitional//EN', 'http://www.w3.org/TR/html4/loose.dtd');

$this -> dom = $imp -> createDocument("", "", $dtd);
$this -> dom -> encoding = 'UTF-8';

$this -> xpath = new DOMXpath ($this -> dom);
$this -> start();
unset($this -> dbcon);

$this -> finish();
}

public function start()
{
speedAnalyzer('Ищем XML и page');
$script_name = empty($this -> path) ? 'index' : $this -> path[0];
$script_xml = MODEL_PATH.$script_name.'.xml';
if(is_file($script_xml))
{
$this -> config = $this -> load ($script_xml);
$pages = $this -> config -> getElementsByTagName ('page');
$pageFounded = false;

foreach ($pages as $page)
{
if (strlen ($match = $page -> getAttribute ('match')))
{
if (preg_match ('#^'.$match.'$#', $this -> request, $matches))
{
$pageFounded = $page;
break; 
}
}
}
if(!$pageFounded && $pages -> length != 0)
{
$pageFounded = $pages -> item(0);
}
unset($this -> config);
if(!$pageFounded)
{
$error = $this -> simpleError('error', '510');
$error -> setAttribute('description', 'Config not declared');
$this -> dom -> appendChild($error);
}
else
{
$this -> startParse($pageFounded);
}
}
else
{
header("HTTP/1.0 404 Not Found");
$error = $this -> simpleError('error', '404');
$error -> setAttribute('description', 'Not found');
$this -> dom -> appendChild($error);
}

$this -> xml = $this -> dom;
$this -> xsl = VIEW_PATH.$script_name.'.xsl';
}

public function finish()
{
$result = '';
$is_xsl = is_file($this -> xsl);
if (XML_SOURCE == true || !$is_xsl)
{
header ("Content-type: text/xml; charset=utf-8");
$result = $this -> xml -> saveXML ();
}
else if($is_xsl)
{
speedAnalyzer('XSLT-трасформация');
if(XSL_CACHE)
{
$proc = new xsltCache ();
$proc -> importStyleSheet ($xgen -> xsl);
}
else
{
$proc = new XSLTProcessor;
$proc -> importStyleSheet ($this->load( $this -> xsl ));
}
$result = $proc -> transformToXML ($this -> xml);
if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) $result = preg_replace('#^\s*\<!DOCTYPE[^\>]+\>#', '', $result);
}
echo $result;

unset($xgen);
unset($proc);
speedAnalyzer('Финиш');
}

private function startParse($page)
{
$page = $this -> dom -> appendChild( $this -> dom -> importNode($page, true) );
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']))
{
$page -> setAttribute('ajax', 'true'); 
}
$this -> parse($page);
}

private function load ($path)
{
$dom = new DOMDocument('1.0', 'utf-8');
$dom -> resolveExternals = true;
$dom -> substituteEntities = true;
$dom -> preserveWhiteSpace = false;
$dom -> load ($path, LIBXML_NOBLANKS|LIBXML_COMPACT|LIBXML_DTDLOAD);
$dom -> xinclude ();
$dom -> normalizeDocument ();
return $dom;
}

private function makeNode ($nodeName, $text = '') {
if( gettype($text) == 'object')
{
$tmp = $this -> dom -> createElement($nodeName);
return $tmp -> appendChild($text);
}
else
{
return $this -> dom -> createElement($nodeName, $text);
}
}

private function simpleError ($nodeName, $text = '') {
return $this -> makeNode ($nodeName, $text);
}

private function multiError ($errors) {
$error = $this -> makeNode ('error');
if (count ($errors))
foreach ($errors as $err) $error -> appendChild ($err['node'] -> nodeName, isset ($err['text']) ? $err['text'] : '');
return $error;
}

private function parse ($node)
{
if(!empty($node -> attributes) && !$node -> hasAttribute ('static'))
{
foreach ($node -> attributes as $attr)
{
$this -> attr($attr);
}
}
if($node -> hasChildNodes())
{
$l = $node -> childNodes -> length;
for($i = 0; $i < $l; $i++)
{
$child = $node -> childNodes -> item($i);
if($child -> nodeType == XML_ELEMENT_NODE && !$child -> hasAttribute ('static'))
{
$mname = 'parse'.ucfirst($child -> nodeName);
if(method_exists($this, $mname))
{
$node -> replaceChild($this->{$mname}($child), $child);
}
else
{
$this -> parse($child);
}
}
}
}
}

private function attr ($attr)
{
$value = htmlspecialchars($this -> value ($attr -> nodeValue));
$attr -> parentNode -> setAttribute($attr -> nodeName, $value);
return $value;
}

private function parseSpeedAnalyzer ($node)
{
$result = $this -> dom -> createElement ('speedAnalyzer');
$res = speedAnalyzer($node -> getAttribute('name'), $this -> speedAnalyze);
$result -> setAttribute('prev', $res[0]);
$result -> setAttribute('name', $res[1]);
$result -> setAttribute('time', $res[2]);
$result -> setAttribute('diff', $res[3]);
$result -> setAttribute('ms', $res[4]);
return $result;
}

private function value($value, $setup = false)
{
if (!strlen(trim($value))) return '';
$result = preg_replace_callback('/'.$this->registered.'\:([a-zA-Z0-9\_]+)/', array(&$this, 'insertData'), $value);
$result = preg_replace_callback("/xpath\:([\/\ \[\]a-z\'A-Z0-9\(\)\@\:\!\=\>\<\_\-]+)/", array(&$this, 'insertXML'), $result);
return $result;
}
private function insertData($matches)
{
$type = $matches[1];
$value = $matches[2];

if($type === 'post' && isset($_POST[$value]))
return (is_array($_POST[$value])) ? '\''.join('\',\'', $_POST[$value]).'\'' : $_POST[$value];

if($type === 'get' && isset($_GET[$value]))
return (is_array($_GET[$value])) ? '\''.join('\',\'', $_GET[$value]).'\'' : $_GET[$value];

if($type === 'session' && isset($_SESSION[$value]))
return $_SESSION[$value];

if($type === 'server' && isset($_SERVER[$value]))
return $_SERVER[$value];

if($type === 'cookie' && isset($_COOKIE[$value]))
return $_COOKIE[$value];

if($type === 'globals' && array_key_exists($value, $this->globals))
return $this->globals[$value];

if($type === 'path' && isset($this->path[$value-1]))
return $this->path[$value-1];

if($type === 'path' && $value == 0)
return REQUEST_SOURCE;

return null;
}
private function insertXML($matches)
{
$result = $this -> xpath -> query ($matches[1]);
if ($result -> length == 0)
{
$value = null;
}
elseif ($result -> length == 1)
{
$value = $result -> item(0) -> nodeValue;
}
else
{
$tmp = array();
foreach ($result as $n) $tmp[] = "'".$n -> firstChild -> nodeValue."'";
$value = join(',', $tmp);
}

return $value;
}

private function validateAction ($node)
{
if (!$node -> hasAttribute ('action'))return true;
$action = $this -> value ($node -> getAttribute ('action'));
if (!empty ($action)) return true;
return false;
}


private function isXmlNode ($node)
{
if ($node -> nodeType == XML_ELEMENT_NODE) return true;
return false;
}

private function cacheGet($name)
{
return apc_fetch($name);
}

private function cacheSet($name, $content = '')
{
apc_store($name, $content, 15);
}
  
 } 
 ?><?php  
 
 
// class.mail.php 
  
/**
 * GuestZilla.com
 *
 *  @desc Mailing class - use it to create messages and send mail to recipients
 */

class mail {
var $boundary = "";
var $message= array();
var $html= array();
var $headers = "";
var $attachs = array();

/**
 * @descConstructor - create boundary & common headers
 */
function mail() {
$this->boundary = md5(microtime(true));
$this->headers = "MIME-Version: 1.0\n"; 
}

/**
 * @descWraps a text after each 73 characters
 * @param$textstringmessage body
 */
function _wrapText($text, $cut = false) {
return wordwrap($text, 73, "\n", $cut);
}

/**
 * @descSet a text message content
 * @param$textstringmessage body
 */
function setText($text, $charset = 'utf-8') {
$this->message['headers'] = "Content-type: text/plain; charset=".$charset."\n"
."Content-Transfer-Encoding: 8bit\n\n";
$this->message['body'] = preg_replace("/(\r\n|\r|\n)/", "\n", $text);
}

/**
 * @descSets anhtml message content, and (optionally) text part of the message
 * @param$htmlstringmessage body, in html format
 * @param$charsetsrtinghtml charset, default us-ascii
 * @param$textbooleanset to true if you need to add auto parsed text
 */
function setHTML($html, $charset = 'utf-8', $addText = false) {
/*if ($addText) {
$text = strip_tags($html, '<a>');
$text = preg_replace("#<a[^>]* href=\"([^\"]+)[^>]+>[^<]+</a>#is", "\$1", $text);
$text = str_replace(array('&gt;', '&lt;', '&quot;', '&amp;'), array('>', '<', '"', '&'), $text);
$this->setText($text);
}

$this->html['headers'] ="Content-Type: text/html; charset=".$charset."\n"
."Content-Transfer-Encoding: 8bit\n"
."Content-Disposition: inline; filename=message.html\n\n";
$this->html['body'] = preg_replace("/(\r\n|\r|\n)/", "\n", $this->_wrapText($html));
*/

$this->html['headers'] .= 'Content-type: text/html; charset=' .$charset. "\n";
$this->html['body'] = preg_replace("/(\r\n|\r|\n)/", "\n", $this->_wrapText($html));
}

/**
 * @descAdds an attachment to message
 * @param$contentstringAttachment contents
 * @param$filenamestringAttachment filename
 * @param$mime_typestringAttachment mime-type (default 'application/octet-stream')
 */
function addAttachment($content, $filename, $mime_type = "application/octet-stream") {
$filename = '"'.str_replace(array('"', "\r\n"), array("'", ""), $filename).'"';

$this->attachs[] = array('headers' => "Content-type: $mime_type; name=".$filename."\n"
."Content-Transfer-Encoding: base64\n"
."Content-Disposition: attachment; filename=$filename\n\n",
  'body'=> $this->_wrapText(base64_encode($content), true));
}

/**
 * @descSends mail to recipient
 * @param$tostringRecipient's e-mail address
 * @param$subjectstringMail subject
 * @param$fromstringFrom address (default = MAIL_SYSTEM)
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
$msg .="\n--".$this->boundary."\n"
.$this->message['headers']
.$this->message['body']."\n";
}
if (!empty($this->html['body'])) {
$msg .="\n--".$this->boundary."\n"
.$this->html['headers']
.$this->html['body']."\n";
}
foreach ($this->attachs as $a) {
$msg .="\n--".$this->boundary."\n"
.$a['headers']
.$a['body']."\n";
}
$msg .= "\n--".$this->boundary."--";
} else {
if (!empty($this->message) && !empty($this->html)) {
$this->headers .= "Content-type: multipart/alternative; boundary=".$this->boundary."\n";
$msg .="\n--".$this->boundary."\n"
.$this->html['headers']
.$this->html['body']."\n";
$msg .="\n--".$this->boundary."\n"
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
 
 
 
// class.dbcon.php 
 
/**
 *  @desc DataBase class - use it to work with any DB from the list (MySQL \ PostgreSQL):(mysql \ pg)
 */


class dbcon {
static $instance = false;// Ссылка на самого себя для раздачи всем вместо себя...
var $dbuser= '';// db access user
var $dbpass= '';// db access password
var $dbhost= '';// db access host i.e. 127.0.0.1 / localhost / db.dotorgc.org
var $dbport= '';// db access port
var $dbname= '';// default database to use
var $query= '';// query to execute
var $error= array();// last error
var $qress= array();// all query resources
var $dbres= false;// connection resource
var $qres= false;// last query resource
var $insert_id= false;// last inserted ID (from primary field)
var $affected_rows= false;// how many rows affected (update \ delete)
var $debug_queries= array();
var $query_counter= 0;

/**
 * @descclass constructor
 * @paramstring$userdb access user
 * @paramstring$passdb access password
 * @paramstring$typedb we want to use
 * @paramstring$namedefault database to use
 * @paramstring$hostdb access host i.e. 127.0.0.1 / localhost / db.dotorgc.org
 * @paramstring$portdb access port
 * @paramboolean$persistentdo we want persistent connection? I hope: NO
 * @paramboolean$new_onedo we want to create new connection instead of using old instance of db con. 
 */
 
function dbcon($user, $pass, $name, $host, $port = false, $persistent = false, $new_one = false) {

if (dbcon::$instance !== false && $new_one === false) return dbcon::$instance;

$error = array();
if (empty($user)) $error[] = 'DataBase user: empty';
if (empty($host)) $error[] = 'DataBase host: empty';
if (empty($name)) $error[] = 'DataBase name: empty';

$this->dbuser= $user;
$this->dbpass= $pass;
$this->dbhost= $host;
$this->dbport= $port;
$this->dbname= $name;

if (!empty($error)) {
$this->error(implode("\n", $error));
return false;
}

try
{
$connect = 'mysql:host='.$this->dbhost.';port='.$this->dbport.';dbname='.$this->dbname;
$this->pdo = new PDO($connect, $this->dbuser, $this->dbpass, array(PDO::ATTR_PERSISTENT => $persistent, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8 COLLATE 'utf8_general_ci'"));
}
catch (PDOException $e)
{
$error[] = $e;
}

if (!empty($error)) {
$this->error(implode("\n", $error));
return false;
}

if ($new_one === false) dbcon::$instance = &$this;
}


/**
 * @descМетод для эмуляции синглетона.
 * @paramboolean$real_loginФлаг для обозначения - обязан ли пользователь быть залогиненым или нет.
 * @returnobjectСсылку на объект или свежесозданный объект
 */
 
static function instance() {
if(dbcon::$instance === false) dbcon::$instance = new dbcon(DB_USER, DB_PASS, DB_NAME, DB_HOST, DB_PORT);
return dbcon::$instance;
}


/**
 * @descfunction to send query to the DataBase
 * @param$querystringstring with the query
 * @returnresourcelast query resource ID or 'false' with $this->error
 */
function query($query = false, $last = false) {


$num_rows = false;
$this->insert_id= false;
$this->affected_rows= false;

if ($query === false)$query= $this->query;
else$this->query= $query;
if (empty($query)) {
$this->error('db::query: no query to execute');
return false;
}

$query = trim($query);
$temp_arr = explode(" ", $query, 2);
$temp_arr[0] = trim(strtoupper($temp_arr[0]));

switch($temp_arr[0]) {
case 'INSERT' :
$last = true;
$query = html_entity_decode ($query);
case 'UPDATE' :
$num_rows = true;
$query = html_entity_decode ($query);
case 'DELETE' :
$num_rows = true;
break;
}
$query = trim($query);

if ($this->qres = $this->pdo->query($query)) {
if ($last !== false) {
$this->insert_id = $this->pdo->lastInsertId();

}
if ($num_rows === true) {
$this->affected_rows = $this->qres->rowCount();
}
} else {
$this->error($this->pdo->errorInfo());
return false;
}

$this->qress[] = $this->qres;

//echo '<pre>',print_r ($this->affected_rows),'</pre>';
$returner = new dbconQuery($this->qres);
$returner->insert_id= $this->insert_id;
$returner->affected_rows= $this->affected_rows;

return $returner;
}


/**
 * @descfunction to show affected rows after query
 * @param$resresourcequery resource ID
 * @returnintnumber of rows or 'false' with $this->error
 */
function affected_rows($res = false) {

if ($res === false) $res = $this->qres;
if ($res !== true) {
$this->error('db::affected_rows: gived string is not resource');
return false;
}

$returner = $res->rowCount;

return $returner;
}


/**
 * @descfunction to prepare string to use in query
 * @param$stringstringstring to prepare
 * @returnstringescaped string
 */
 
function escape_string($string) {
$returner = mysql_real_escape_string($string, $this->dbres);
return $returner;
}


/**
 * @descfunction to free all DB results from memory
 * @returnbooloperation result
 */
function free_all_results() {
if (empty($this->qress)) return true;
foreach ($this->qress AS $k => $v) {
if (gettype($v) != 'boolean') $returner = $v->closeCursor();;
unset($this->qress[$k]);
}
}


/**
 * @descfunction to print queries with time
 */
function print_debug_queries() {
foreach ($this->debug_queries AS $query) {
echo $query['time'].': '.$query['query']."\n\n";
}
}


/**
 * @descfunction to create append error and call debugger
 * @param$errorstringstring with error in it
 */
function error($error) {
echo '<pre>',$error,'</pre>';
echo $this -> query;
exit;
}

function show_tables () {
return new dbconQuery (mysql_list_tables ($this -> name));
}


/**
 * @descfunction destructor
 */
function __destruct() {
$this->free_all_results();
$returner = false;
$returner = @mysql_close($this->dbres);
}
}

/**
 *  @desc DataBase class - query class
 */


class dbconQuery {

var $res= false;// last query resource
var $insert_id= false;// last inserted ID (from primary field)
var $affected_rows= false;// last inserted ID (from primary field)

/**
 * @descclass constructor
 * @paramresourse$resРесурс на запрос
 * @paramstring$dbtypeчё за база такая ваще
 */
function __construct($res) {
$this->res= $res;
}


/**
 * @returnstringвозвращает одну переменную
 */
function fetch_one() {
return list($returner) = $this->fetch_row();
}


/**
 * @descfunction to fetch resource into associative array
 * @returnarrayfetched query in the array
 */
function fetch_assoc() {
return $this->res->fetch(PDO::FETCH_ASSOC);
}


/**
 * @returnarrayfetched query in the array
 */
function fetch_row() {
return $this->res->fetch();
}


/**
 * @returnarrayfetched query in the array
 */
function fetch_object() {
return $this->res->fetch(PDO::FETCH_OBJ);
}


/**
 * @descfunction to fetch resource with 'assoc' type
 * @returnarrayarray from fetched recource
 */
function fetch_assoc_all() {
return $this->res->fetchAll(PDO::FETCH_ASSOC);
}



/**
 * @descfunction to count number of rows in the selected query
 * @returnintnumber of rows or 'false' with $this->error
 */
function num_rows() {
$returner = mysql_num_rows($this->res);
return $returner;
}


/**
 * @descfunction to free DB result from memory
 * @returnbooloperation result
 */
function free_result() {
$returner = mysql_free_result($this->res);
return $returner;
}


/**
 * @descfunction to get last id
 * @returnintigerlast insert id
 */
function insert_id() {
return $this->pdo->lastInsertId();
}

function fetch_obj () {
$returner = array();

while ($returner[] = mysql_fetch_object ($this->res));
array_pop($returner);

return $returner;
}


/**
 * @descfunction to get affected_rows
 * @returnintigernumber of affected rows
 */
function affected_rows() {
return $this->res->rowCount;
}


/**
 * @descfunction destructor
 */
function __destruct() {
//$this->free_result();
}
}
 
 
 
// class.xfiles.php 
 

class xfiles {
private$var_index;
private $files_path = TMP_FILE_DIR;
private$mime_types = array (
'image/x-jg',
'image/bmp',
'image/x-windows-bmp',
'image/gif',
'image/x-icon',
'image/pjpeg',
'image/jpeg',
'image/x-pcx',
'image/pict',
'image/png',
'image/tiff',
'image/tif',
'image/png'
);
public $files;

public function __construct () {
}

public function uploadFiles ($index_name) {
if (empty ($index_name)) return false;
$this -> var_index = $index_name;

//echo '<pre>'.print_r($_FILES).'</pre>';

if (!isset ($_FILES[$this -> var_index])) return false;
if (!count ($_FILES[$this -> var_index]['name'])) return false;
$this -> files = array ();

if (count ($_FILES[$this -> var_index]['name']) > 1) foreach ($_FILES[$this -> var_index]['name'] as $key => $value) $this -> files[] = $this -> doUpload ($key);
else $this -> files[] = $this -> doUploadOne ();
if (!count ($this -> files) || empty ($this -> files) || empty ($this -> files[0])) return false;
return $this -> files;
}

private function doUploadAll ($index) {
if (empty ($_FILES[$this -> var_index]['name'][$index])) return false;
if (!is_uploaded_file ($_FILES[$this -> var_index]['tmp_name'][$index])) return false;
if (!in_array ($_FILES[$this -> var_index]['type'][$index], $this -> mime_types)) return false;
if (!$extension = $this -> getExtension ($_FILES[$this -> var_index]['name'][$index])) return false;
$file_name = $this -> files_path.'tmp_'.intval (microtime (true)).'.'.$extension;
if (!move_uploaded_file ($_FILES[$this -> var_index]['tmp_name'][$index], $file_name)) return false;
chmod ($file_name, 0666);
return $file_name;
}

private function doUploadOne () {

if (empty ($_FILES[$this -> var_index]['name'])) return false;
if (!is_uploaded_file ($_FILES[$this -> var_index]['tmp_name'])) return false;
if (!in_array ($_FILES[$this -> var_index]['type'], $this -> mime_types)) return false;
if (!$extension = $this -> getExtension ($_FILES[$this -> var_index]['name'])) return false;
$file_name = $this -> files_path.'tmp_'.intval (microtime (true)).'.'.$extension;
if (!move_uploaded_file ($_FILES[$this -> var_index]['tmp_name'], $file_name)) return false;
chmod ($file_name, 0666);
return $file_name;
}

private function getExtension ($string) {
if (empty ($string)) return false;
$extension = substr ($string, strrpos ($string, '.')+1, strlen ($string)-1);
if (empty ($extension)) return false;
return $extension;
}

private function removeFiles ($files, $path = TMP_FILE_DIR) {
foreach ($files as $file)
if (is_file ($path.$file)) unlink ($path.$file);

}

public function cleanDir($dir = TMP_FILE_DIR) {
    $mydir = opendir($dir);
    while(false !== ($file = readdir($mydir))) {
if($file != "." && $file != "..") {
    chmod($dir.$file, 0666);
    if(is_dir($dir.$file)) {
chdir('.');
destroy($dir.$file.'/');
rmdir($dir.$file);
    }
    else
unlink($dir.$file);
}
    }
    closedir($mydir);
}

//public function __destruct () {
//$this -> removeFiles ($this -> files);
//}

}
 
 
 
// class.jevix.php 
 
/**
 * Jevix — средство автоматического применения правил набора текстов,
 * наделённое способностью унифицировать разметку HTML/XML документов,
 * контролировать перечень допустимых тегов и аттрибутов,
 * предотвращать возможные XSS-атаки в коде документов.
 * http://code.google.com/p/jevix/
 *
 * @author ur001 <ur001ur001@gmail.com>, http://ur001.habrahabr.ru
 * @version 1.01
 * 
 * История версий:
 * 1.01
 *  + cfgSetAutoReplace теперь регистронезависимый
 *  + Возможность указать через cfgSetTagIsEmpty теги с пустым содержанием, которые не будут адалены парсером (rus.engine)
 *  + фикс бага удаления контента тега при разном регистре открывающего и закрывающего тегов  (rus.engine)
 *+ Исправлено поведение парсера при установке правила sfgParamsAutoAdd(). Теперь
 *    параметр устанавливается только в том случае, если его вообще нет в
 *    обрабатываемом тексте. Если есть - оставляется оригинальное значение. (deadyaga)
 * 1.00
 *  + Исправлен баг с закрывающимися тегами приводящий к созданию непарного тега рушащего вёрстку
 * 1.00 RC2
 *  + Небольшая чистка кода
 * 1.00 RC1
 *  + Добавлен символьный класс Jevix::RUS для определния русских символов
 *  + Авторасстановка пробелов после пунктуации только для кирилицы
 *  + Добавлена настройка cfgSetTagNoTypography() отключающая типографирование в указанном теге
 *  + Немного переделан алгоритм обработки кавычек. Он стал более строгим
 *  + Знак дюйма 33" больше не превращается в открывающуюся кавычку. Однако варриант "мой 24" монитор" - парсер не переварит.
 * 0.99
 *  + Расширена функциональность для проверки атрибутов тега:
 *    можно указать тип атрибута ( 'colspan'=>'#int', 'value' => '#text' )
 *    в Jevix, по-умолчанию, определён массив типов для нескольких стандартных атрибутов (src, href, width, height)
 * 0.98
 *  + Расширена функциональность для проверки атрибутов тега:
 *    можно задавать список дозможных значений атрибута (  'align'=>array('left', 'right', 'center') )
 * 0.97
 *  + Обычные "кавычки" сохраняются как &quote; если они были так написаны
 * 0.96
 *  + Добавлены разрешённые протоколы https и ftp для ссылок (a href="https://...)
 * 0.95
 *  + Исправлено типографирование ?.. и !.. (две точки в конце больше не превращаются в троеточие)
 *  + Отключено автоматическое добавление пробела после точки для латиницы из-за чего невозможно было написать
 *    index.php или .htaccess
 * 0.94
 *  + Добавлена настройка автодобавления параметров тегов. Непример rel = "nofolow" для ссылок.
 *    Спасибо Myroslav Holyak (vbhjckfd@gmail.com)
 * 0.93
 *      + Исправлен баг с удалением пробелов (например в "123 &mdash; 123")
 *  + Исправлена ошибка из-за которой иногда не срабатывало автоматическое преобразования URL в ссылу
 *  + Добавлена настройка cfgSetAutoLinkMode для отключения автоматического преобразования URL в ссылки
 *  + Автодобавление пробела после точки, если после неё идёт русский символ
 * 0.92
 *      + Добавлена настройка cfgSetAutoBrMode. При установке в false, переносы строк не будут автоматически заменяться на BR
 *      + Изменена обработка HTML-сущностей. Теперь все сущности имеющие эквивалент в Unicode (за исключением <>)
 *    автоматически преобразуются в символ
 * 0.91
 *      + Добавлена обработка преформатированных тегов <pre>, <code>. Для задания используйте cfgSetTagPreformatted()
 *  + Добавлена настройка cfgSetXHTMLMode. При отключении пустые теги будут оформляться как <br>, при включенном - <br/>
 *      + Несколько незначительных багфиксов
 * 0.9
 *      + Первый бета-релиз
 */

class Jevix{
        const PRINATABLE  = 0x1;
        const ALPHA       = 0x2;
        const LAT         = 0x4;
        const RUS         = 0x8;
        const NUMERIC     = 0x10;
        const SPACE       = 0x20;
        const NAME        = 0x40;
        const URL         = 0x100;
        const NOPRINT     = 0x200;
        const PUNCTUATUON = 0x400;
        //const           = 0x800;
        //const           = 0x1000;
        const HTML_QUOTE  = 0x2000;
        const TAG_QUOTE   = 0x4000;
        const QUOTE_CLOSE = 0x8000;
        const NL          = 0x10000;
        const QUOTE_OPEN  = 0;

        const STATE_TEXT = 0;
        const STATE_TAG_PARAMS = 1;
        const STATE_TAG_PARAM_VALUE = 2;
        const STATE_INSIDE_TAG = 3;
        const STATE_INSIDE_NOTEXT_TAG = 4;
        const STATE_INSIDE_PREFORMATTED_TAG = 5;

        public $tagsRules = array();
        public $entities0 = array('"'=>'&quot;', "'"=>'&#39;', '&'=>'&amp;', '<'=>'&lt;', '>'=>'&gt;');
        public $entities1 = array();
        public $entities2 = array('<'=>'&lt;', '>'=>'&gt;', '"'=>'&quot;');
        public $textQuotes = array(array('«', '»'), array('„', '“'));
        public $dash = " — ";
        public $apostrof = "’";
        public $dotes = "…";
        public $nl = "\r\n";
        public $defaultTagParamRules = array('href' => '#link', 'src' => '#image', 'width' => '#int', 'height' => '#int', 'text' => '#text', 'title' => '#text');

        protected $text;
        protected $textBuf;
        protected $textLen = 0;
        protected $curPos;
        protected $curCh;
        protected $curChOrd;
        protected $curChClass;
        protected $states;
        protected $quotesOpened = 0;
        protected $brAdded = 0;
        protected $state;
        protected $tagsStack;
        protected $openedTag;
        protected $autoReplace; // Автозамена
        protected $isXHTMLMode  = true; // <br/>, <img/>
        protected $isAutoBrMode = true; // \n = <br/>
        protected $isAutoLinkMode = true;
        protected $br = "<br/>";

        protected $noTypoMode = false;

        public    $outBuffer = '';
        public    $errors;


        /**
         * Константы для класификации тегов
         *
         */
        const TR_TAG_ALLOWED = 1;                // Тег позволен
        const TR_PARAM_ALLOWED = 2;      // Параметр тега позволен (a->title, a->src, i->alt)
        const TR_PARAM_REQUIRED = 3;     // Параметр тега влятся необходимым (a->href, img->src)
        const TR_TAG_SHORT = 4;                  // Тег может быть коротким (img, br)
        const TR_TAG_CUT = 5;                    // Тег необходимо вырезать вместе с контентом (script, iframe)
        const TR_TAG_CHILD = 6;                  // Тег может содержать другие теги
        const TR_TAG_CONTAINER = 7;      // Тег может содержать лишь указанные теги. В нём не может быть текста
        const TR_TAG_CHILD_TAGS = 8;     // Теги которые может содержать внутри себя другой тег
        const TR_TAG_PARENT = 9;                 // Тег в котором должен содержаться данный тег
        const TR_TAG_PREFORMATTED = 10;  // Преформатированные тег, в котором всё заменяется на HTML сущности типа <pre> сохраняя все отступы и пробелы
        const TR_PARAM_AUTO_ADD = 11;    // Auto add parameters + default values (a->rel[=nofollow])
        const TR_TAG_NO_TYPOGRAPHY = 12; // Отключение типографирования для тега
        const TR_TAG_IS_EMPTY = 13;              // Не короткий тег с пустым содержанием имеет право существовать

        /**
         * Классы символов генерируются symclass.php
         *
         * @var array
         */
        protected $chClasses = array(0=>512,1=>512,2=>512,3=>512,4=>512,5=>512,6=>512,7=>512,8=>512,9=>32,10=>66048,11=>512,12=>512,13=>66048,14=>512,15=>512,16=>512,17=>512,18=>512,19=>512,20=>512,21=>512,22=>512,23=>512,24=>512,25=>512,26=>512,27=>512,28=>512,29=>512,30=>512,31=>512,32=>32,97=>71,98=>71,99=>71,100=>71,101=>71,102=>71,103=>71,104=>71,105=>71,106=>71,107=>71,108=>71,109=>71,110=>71,111=>71,112=>71,113=>71,114=>71,115=>71,116=>71,117=>71,118=>71,119=>71,120=>71,121=>71,122=>71,65=>71,66=>71,67=>71,68=>71,69=>71,70=>71,71=>71,72=>71,73=>71,74=>71,75=>71,76=>71,77=>71,78=>71,79=>71,80=>71,81=>71,82=>71,83=>71,84=>71,85=>71,86=>71,87=>71,88=>71,89=>71,90=>71,1072=>11,1073=>11,1074=>11,1075=>11,1076=>11,1077=>11,1078=>11,1079=>11,1080=>11,1081=>11,1082=>11,1083=>11,1084=>11,1085=>11,1086=>11,1087=>11,1088=>11,1089=>11,1090=>11,1091=>11,1092=>11,1093=>11,1094=>11,1095=>11,1096=>11,1097=>11,1098=>11,1099=>11,1100=>11,1101=>11,1102=>11,1103=>11,1040=>11,1041=>11,1042=>11,1043=>11,1044=>11,1045=>11,1046=>11,1047=>11,1048=>11,1049=>11,1050=>11,1051=>11,1052=>11,1053=>11,1054=>11,1055=>11,1056=>11,1057=>11,1058=>11,1059=>11,1060=>11,1061=>11,1062=>11,1063=>11,1064=>11,1065=>11,1066=>11,1067=>11,1068=>11,1069=>11,1070=>11,1071=>11,48=>337,49=>337,50=>337,51=>337,52=>337,53=>337,54=>337,55=>337,56=>337,57=>337,34=>57345,39=>16385,46=>1281,44=>1025,33=>1025,63=>1281,58=>1025,59=>1281,1105=>11,1025=>11,47=>257,38=>257,37=>257,45=>257,95=>257,61=>257,43=>257,35=>257,124=>257,);

        /**
         * Установка конфигурационного флага для одного или нескольких тегов
         *
         * @param array|string $tags тег(и)
         * @param int $flag флаг
         * @param mixed $value значеник=е флага
         * @param boolean $createIfNoExists если тег ещё не определён - создть его
         */
        protected function _cfgSetTagsFlag($tags, $flag, $value, $createIfNoExists = true){
                if(!is_array($tags)) $tags = array($tags);
                foreach($tags as $tag){
                        if(!isset($this->tagsRules[$tag])) {
                                if($createIfNoExists){
                                        $this->tagsRules[$tag] = array();
                                } else {
                                        throw new Exception("Тег $tag отсутствует в списке разрешённых тегов");
                                }
                        }
                        $this->tagsRules[$tag][$flag] = $value;
                }
        }

        /**
         * КОНФИГУРАЦИЯ: Разрешение или запрет тегов
         * Все не разрешённые теги считаются запрещёнными
         * @param array|string $tags тег(и)
         */
        function cfgAllowTags($tags){
                $this->_cfgSetTagsFlag($tags, self::TR_TAG_ALLOWED, true);
        }

        /**
         * КОНФИГУРАЦИЯ: Коротие теги типа <img>
         * @param array|string $tags тег(и)
         */
        function cfgSetTagShort($tags){
                $this->_cfgSetTagsFlag($tags, self::TR_TAG_SHORT, true, false);
        }

        /**
         * КОНФИГУРАЦИЯ: Преформатированные теги, в которых всё заменяется на HTML сущности типа <pre>
         * @param array|string $tags тег(и)
         */
        function cfgSetTagPreformatted($tags){
                $this->_cfgSetTagsFlag($tags, self::TR_TAG_PREFORMATTED, true, false);
        }

        /**
         * КОНФИГУРАЦИЯ: Теги в которых отключено типографирование типа <code>
         * @param array|string $tags тег(и)
         */
        function cfgSetTagNoTypography($tags){
                $this->_cfgSetTagsFlag($tags, self::TR_TAG_NO_TYPOGRAPHY, true, false);
        }

        /**
         * КОНФИГУРАЦИЯ: Не короткие теги которые не нужно удалять с пустым содержанием, например, <param name="code" value="die!"></param>
         * @param array|string $tags тег(и)
         */
        function cfgSetTagIsEmpty($tags){
                $this->_cfgSetTagsFlag($tags, self::TR_TAG_IS_EMPTY, true, false);
        }

        /**
         * КОНФИГУРАЦИЯ: Тег необходимо вырезать вместе с контентом (script, iframe)
         * @param array|string $tags тег(и)
         */
        function cfgSetTagCutWithContent($tags){
                $this->_cfgSetTagsFlag($tags, self::TR_TAG_CUT, true);
        }

        /**
         * КОНФИГУРАЦИЯ: Добавление разрешённых параметров тега
         * @param string $tag тег
         * @param string|array $params разрешённые параметры
         */
        function cfgAllowTagParams($tag, $params){
                if(!isset($this->tagsRules[$tag])) throw new Exception("Тег $tag отсутствует в списке разрешённых тегов");
                if(!is_array($params)) $params = array($params);
                // Если ключа со списком разрешенных параметров не существует - создаём ео
                if(!isset($this->tagsRules[$tag][self::TR_PARAM_ALLOWED])) {
                        $this->tagsRules[$tag][self::TR_PARAM_ALLOWED] = array();
                }
                foreach($params as $key => $value){
                        if(is_string($key)){
                                $this->tagsRules[$tag][self::TR_PARAM_ALLOWED][$key] = $value;
                        } else {
                                $this->tagsRules[$tag][self::TR_PARAM_ALLOWED][$value] = true;
                        }
                }
        }

        /**
         * КОНФИГУРАЦИЯ: Добавление необходимых параметров тега
         * @param string $tag тег
         * @param string|array $params разрешённые параметры
         */
        function cfgSetTagParamsRequired($tag, $params){
                if(!isset($this->tagsRules[$tag])) throw new Exception("Тег $tag отсутствует в списке разрешённых тегов");
                if(!is_array($params)) $params = array($params);
                // Если ключа со списком разрешенных параметров не существует - создаём ео
                if(!isset($this->tagsRules[$tag][self::TR_PARAM_REQUIRED])) {
                        $this->tagsRules[$tag][self::TR_PARAM_REQUIRED] = array();
                }
                foreach($params as $param){
                        $this->tagsRules[$tag][self::TR_PARAM_REQUIRED][$param] = true;
                }
        }

        /* КОНФИГУРАЦИЯ: Установка тегов которые может содержать тег-контейнер
         * @param string $tag тег
         * @param string|array $childs разрешённые теги
         * @param boolean $isContainerOnly тег является только контейнером других тегов и не может содержать текст
         * @param boolean $isChildOnly вложенные теги не могут присутствовать нигде кроме указанного тега
         */
        function cfgSetTagChilds($tag, $childs, $isContainerOnly = false, $isChildOnly = false){
                if(!isset($this->tagsRules[$tag])) throw new Exception("Тег $tag отсутствует в списке разрешённых тегов");
                if(!is_array($childs)) $childs = array($childs);
                // Тег является контейнером и не может содержать текст
                if($isContainerOnly) $this->tagsRules[$tag][self::TR_TAG_CONTAINER] = true;
                // Если ключа со списком разрешенных тегов не существует - создаём ео
                if(!isset($this->tagsRules[$tag][self::TR_TAG_CHILD_TAGS])) {
                        $this->tagsRules[$tag][self::TR_TAG_CHILD_TAGS] = array();
                }
                foreach($childs as $child){
                        $this->tagsRules[$tag][self::TR_TAG_CHILD_TAGS][$child] = true;
                        //  Указанный тег должен сущеаствовать в списке тегов
                        if(!isset($this->tagsRules[$child])) throw new Exception("Тег $child отсутствует в списке разрешённых тегов");
                        if(!isset($this->tagsRules[$child][self::TR_TAG_PARENT])) $this->tagsRules[$child][self::TR_TAG_PARENT] = array();
                        $this->tagsRules[$child][self::TR_TAG_PARENT][$tag] = true;
                        // Указанные разрешённые теги могут находится только внтутри тега-контейнера
                        if($isChildOnly) $this->tagsRules[$child][self::TR_TAG_CHILD] = true;
                }
        }

    /**
     * CONFIGURATION: Adding autoadd attributes and their values to tag
     * @param string $tag tag
     * @param string|array $params array of pairs attributeName => attributeValue
     */
    function cfgSetTagParamsAutoAdd($tag, $params){
        if(!isset($this->tagsRules[$tag])) throw new Exception("Tag $tag is missing in allowed tags list");
        if(!is_array($params)) $params = array($params);
        if(!isset($this->tagsRules[$tag][self::TR_PARAM_AUTO_ADD])) {
            $this->tagsRules[$tag][self::TR_PARAM_AUTO_ADD] = array();
        }
        foreach($params as $param => $value){
            $this->tagsRules[$tag][self::TR_PARAM_AUTO_ADD][$param] = $value;
        }
    }


        /**
         * Автозамена
         *
         * @param array $from с
         * @param array $to на
         */
        function cfgSetAutoReplace($from, $to){
                $this->autoReplace = array('from' => $from, 'to' => $to);
        }

        /**
         * Включение или выключение режима XTML
         *
         * @param boolean $isXHTMLMode
         */
        function cfgSetXHTMLMode($isXHTMLMode){
                $this->br = $isXHTMLMode ? '<br/>' : '<br>';
                $this->isXHTMLMode = $isXHTMLMode;
        }

        /**
         * Включение или выключение режима замены новых строк на <br/>
         *
         * @param boolean $isAutoBrMode
         */
        function cfgSetAutoBrMode($isAutoBrMode){
                $this->isAutoBrMode = $isAutoBrMode;
        }

        /**
         * Включение или выключение режима автоматического определения ссылок
         *
         * @param boolean $isAutoLinkMode
         */
        function cfgSetAutoLinkMode($isAutoLinkMode){
                $this->isAutoLinkMode = $isAutoLinkMode;
        }

        protected function &strToArray($str){
                $chars = null;
                preg_match_all('/./su', $str, $chars);
                return $chars[0];
        }


        function parse($text, &$errors){
                $this->curPos = -1;
                $this->curCh = null;
                $this->curChOrd = 0;
                $this->state = self::STATE_TEXT;
                $this->states = array();
                $this->quotesOpened = 0;
                $this->noTypoMode = false;

                // Авто растановка BR?
                if($this->isAutoBrMode) {
                        $this->text = preg_replace('/<br\/?>(\r\n|\n\r|\n)?/ui', $this->nl, $text);
                } else {
                        $this->text = $text;
                }


                if(!empty($this->autoReplace)){
                        $this->text = str_ireplace($this->autoReplace['from'], $this->autoReplace['to'], $this->text);
                }
                $this->textBuf = $this->strToArray($this->text);
                $this->textLen = count($this->textBuf);
                $this->getCh();
                $content = '';
                $this->outBuffer='';
                $this->brAdded=0;
                $this->tagsStack = array();
                $this->openedTag = null;
                $this->errors = array();
                $this->skipSpaces();
                $this->anyThing($content);
                $errors = $this->errors;
                return $content;
        }

        /**
         * Получение следующего символа из входной строки
         * @return string считанный символ
         */
        protected function getCh(){
                return $this->goToPosition($this->curPos+1);
        }

        /**
         * Перемещение на указанную позицию во входной строке и считывание символа
         * @return string символ в указанной позиции
         */
        protected function goToPosition($position){
                $this->curPos = $position;
                if($this->curPos < $this->textLen){
                        $this->curCh = $this->textBuf[$this->curPos];
                        $this->curChOrd = uniord($this->curCh);
                        $this->curChClass = $this->getCharClass($this->curChOrd);
                } else {
                        $this->curCh = null;
                        $this->curChOrd = 0;
                        $this->curChClass = 0;
                }
                return $this->curCh;
        }

        /**
         * Сохранить текущее состояние
         *
         */
        protected function saveState(){
                $state = array(
                        'pos'   => $this->curPos,
                        'ch'    => $this->curCh,
                        'ord'   => $this->curChOrd,
                        'class' => $this->curChClass,
                );

                $this->states[] = $state;
                return count($this->states)-1;
        }

        /**
         * Восстановить
         *
         */
        protected function restoreState($index = null){
                if(!count($this->states)) throw new Exception('Конец стека');
                if($index == null){
                        $state = array_pop($this->states);
                } else {
                        if(!isset($this->states[$index])) throw new Exception('Неверный индекс стека');
                        $state = $this->states[$index];
                        $this->states = array_slice($this->states, 0, $index);
                }

                $this->curPos     = $state['pos'];
                $this->curCh      = $state['ch'];
                $this->curChOrd   = $state['ord'];
                $this->curChClass = $state['class'];
        }

        /**
         * Проверяет точное вхождение символа в текущей позиции
         * Если символ соответствует указанному автомат сдвигается на следующий
         *
         * @param string $ch
         * @return boolean
         */
        protected function matchCh($ch, $skipSpaces = false){
                if($this->curCh == $ch) {
                        $this->getCh();
                        if($skipSpaces) $this->skipSpaces();
                        return true;
                }

                return false;
        }

        /**
         * Проверяет точное вхождение символа указанного класса в текущей позиции
         * Если символ соответствует указанному классу автомат сдвигается на следующий
         *
         * @param int $chClass класс символа
         * @return string найденый символ или false
         */
        protected function matchChClass($chClass, $skipSpaces = false){
                if(($this->curChClass & $chClass) == $chClass) {
                        $ch = $this->curCh;
                        $this->getCh();
                        if($skipSpaces) $this->skipSpaces();
                        return $ch;
                }

                return false;
        }

        /**
         * Проверка на точное совпадение строки в текущей позиции
         * Если строка соответствует указанной автомат сдвигается на следующий после строки символ
         *
         * @param string $str
         * @return boolean
         */
        protected function matchStr($str, $skipSpaces = false){
                $this->saveState();
                $len = strlen($str);
                $test = '';
                while($len-- && $this->curChClass){
                        $test.=$this->curCh;
                        $this->getCh();
                }

                if($test == $str) {
                        if($skipSpaces) $this->skipSpaces();
                        return true;
                } else {
                        $this->restoreState();
                        return false;
                }
        }

        /**
         * Пропуск текста до нахождения указанного символа
         *
         * @param string $ch сиимвол
         * @return string найденый символ или false
         */
        protected function skipUntilCh($ch){
                $chPos = strpos($this->text, $ch, $this->curPos);
                if($chPos){
                        return $this->goToPosition($chPos);
                } else {
                        return false;
                }
        }

        /**
         * Пропуск текста до нахождения указанной строки или символа
         *
         * @param string $str строка или символ ля поиска
         * @return boolean
         */
        protected function skipUntilStr($str){
                $str = $this->strToArray($str);
                $firstCh = $str[0];
                $len = count($str);
                while($this->curChClass){
                        if($this->curCh == $firstCh){
                                $this->saveState();
                                $this->getCh();
                                $strOK = true;
                                for($i = 1; $i<$len ; $i++){
                                        // Конец строки
                                        if(!$this->curChClass){
                                                return false;
                                        }
                                        // текущий символ не равен текущему символу проверяемой строки?
                                        if($this->curCh != $str[$i]){
                                                $strOK = false;
                                                break;
                                        }
                                        // Следующий символ
                                        $this->getCh();
                                }

                                // При неудаче откатываемся с переходим на следующий символ
                                if(!$strOK){
                                        $this->restoreState();
                                } else {
                                        return true;
                                }
                        }
                        // Следующий символ
                        $this->getCh();
                }
                return false;
        }

        /**
         * Возвращает класс символа
         *
         * @return int
         */
        protected function getCharClass($ord){
                return isset($this->chClasses[$ord]) ? $this->chClasses[$ord] : self::PRINATABLE;
        }

        /*function isSpace(){
                return $this->curChClass == slf::SPACE;
        }*/

        /**
         * Пропуск пробелов
         *
         */
        protected function skipSpaces(&$count = 0){
                while($this->curChClass == self::SPACE) {
                        $this->getCh();
                        $count++;
                }
                return $count > 0;
        }

        /**
         *  Получает име (тега, параметра) по принципу 1 сиивол далее цифра или символ
         *
         * @param string $name
         */
        protected function name(&$name = '', $minus = false){
                if(($this->curChClass & self::LAT) == self::LAT){
                        $name.=$this->curCh;
                        $this->getCh();
                } else {
                        return false;
                }

                while((($this->curChClass & self::NAME) == self::NAME || ($minus && $this->curCh=='-'))){
                        $name.=$this->curCh;
                        $this->getCh();
                }

                $this->skipSpaces();
                return true;
        }

        protected function tag(&$tag, &$params, &$content, &$short){
                $this->saveState();
                $params = array();
                $tag = '';
                $closeTag = '';
                $params = array();
                $short = false;
                if(!$this->tagOpen($tag, $params, $short)) return false;
                // Короткая запись тега
                if($short) return true;

                // Сохраняем кавычки и состояние
                //$oldQuotesopen = $this->quotesOpened;
                $oldState = $this->state;
                $oldNoTypoMode = $this->noTypoMode;
                //$this->quotesOpened = 0;


                // Если в теге не должно быть текста, а только другие теги
                // Переходим в состояние self::STATE_INSIDE_NOTEXT_TAG
                if(!empty($this->tagsRules[$tag][self::TR_TAG_PREFORMATTED])){
                        $this->state = self::STATE_INSIDE_PREFORMATTED_TAG;
                } elseif(!empty($this->tagsRules[$tag][self::TR_TAG_CONTAINER])){
                        $this->state = self::STATE_INSIDE_NOTEXT_TAG;
                } elseif(!empty($this->tagsRules[$tag][self::TR_TAG_NO_TYPOGRAPHY])) {
                        $this->noTypoMode = true;
                        $this->state = self::STATE_INSIDE_TAG;
                } else {
                        $this->state = self::STATE_INSIDE_TAG;
                }

                // Контент тега
                array_push($this->tagsStack, $tag);
                $this->openedTag = $tag;
                $content = '';
                if($this->state == self::STATE_INSIDE_PREFORMATTED_TAG){
                        $this->preformatted($content, $tag);
                } else {
                        $this->anyThing($content, $tag);
                }

                array_pop($this->tagsStack);
                $this->openedTag = !empty($this->tagsStack) ? array_pop($this->tagsStack) : null;

                $isTagClose = $this->tagClose($closeTag);
                if($isTagClose && ($tag != $closeTag)) {
                        $this->eror("Неверный закрывающийся тег $closeTag. Ожидалось закрытие $tag");
                        //$this->restoreState();
                }

                // Восстанавливаем предыдущее состояние и счетчик кавычек
                $this->state = $oldState;
                $this->noTypoMode = $oldNoTypoMode;
                //$this->quotesOpened = $oldQuotesopen;

                return true;
        }

        protected function preformatted(&$content = '', $insideTag = null){
                while($this->curChClass){
                        if($this->curCh == '<'){
                                $tag = '';
                                $this->saveState();
                                // Пытаемся найти закрывающийся тег
                                $isClosedTag = $this->tagClose($tag);
                                // Возвращаемся назад, если тег был найден
                                if($isClosedTag) $this->restoreState();
                                // Если закрылось то, что открылось - заканчиваем и возвращаем true
                                if($isClosedTag && $tag == $insideTag) return;
                        }
                        $content.= isset($this->entities2[$this->curCh]) ? $this->entities2[$this->curCh] : $this->curCh;
                        $this->getCh();
                }
        }

        protected function tagOpen(&$name, &$params, &$short = false){
                $restore = $this->saveState();

                // Открытие
                if(!$this->matchCh('<')) return false;
                $this->skipSpaces();
                if(!$this->name($name)){
                        $this->restoreState();
                        return false;
                }
                $name=strtolower($name);
                // Пробуем получить список атрибутов тега
                if($this->curCh != '>' && $this->curCh != '/') $this->tagParams($params);

                // Короткая запись тега
                $short = !empty($this->tagsRules[$name][self::TR_TAG_SHORT]);

                // Short && XHTML && !Slash || Short && !XHTML && !Slash = ERROR
                $slash = $this->matchCh('/');
                //if(($short && $this->isXHTMLMode && !$slash) || (!$short && !$this->isXHTMLMode && $slash)){
                if(!$short && $slash){
                        $this->restoreState();
                        return false;
                }

                $this->skipSpaces();

                // Закрытие
                if(!$this->matchCh('>')) {
                        $this->restoreState($restore);
                        return false;
                }

                $this->skipSpaces();
                return true;
        }


        protected function tagParams(&$params = array()){
                $name = null;
                $value = null;
                while($this->tagParam($name, $value)){
                        $params[$name] = $value;
                        $name = ''; $value = '';
                }
                return count($params) > 0;
        }

        protected function tagParam(&$name, &$value){
    $this->saveState();
                if(!$this->name($name, true)) return false;

                if(!$this->matchCh('=', true)){
                        // Стремная штука - параметр без значения <input type="checkbox" checked>, <td nowrap class=b>
                        if(($this->curCh=='>' || ($this->curChClass & self::LAT) == self::LAT)){
                                $value = null;
                                return true;
                        } else {
                                $this->restoreState();
                                return false;
                        }
                }

                $quote = $this->matchChClass(self::TAG_QUOTE, true);

                if(!$this->tagParamValue($value, $quote)){
                        $this->restoreState();
                        return false;
                }

                if($quote && !$this->matchCh($quote, true)){
                        $this->restoreState();
                        return false;
                }

                $this->skipSpaces();
                return true;
        }

        protected function tagParamValue(&$value, $quote){
                if($quote !== false){
                        // Нормальный параметр с кавычкамию Получаем пока не кавычки и не конец
                        $escape = false;
                        while($this->curChClass && ($this->curCh != $quote || $escape)){
                                $escape = false;
                                // Экранируем символы HTML которые не могут быть в параметрах
                                $value.=isset($this->entities1[$this->curCh]) ? $this->entities1[$this->curCh] : $this->curCh;
                                // Символ ескейпа <a href="javascript::alert(\"hello\")">
                                if($this->curCh == '\\') $escape = true;
                                $this->getCh();
                        }
                } else {
                        // долбаный параметр без кавычек. получаем его пока не пробел и не > и не конец
                        while($this->curChClass && !($this->curChClass & self::SPACE) && $this->curCh != '>'){
                                // Экранируем символы HTML которые не могут быть в параметрах
                                $value.=isset($this->entities1[$this->curCh]) ? $this->entities1[$this->curCh] : $this->curCh;
                                $this->getCh();
                        }
                }

                return true;
        }

        protected function tagClose(&$name){
                $this->saveState();
                if(!$this->matchCh('<')) return false;
                $this->skipSpaces();
                if(!$this->matchCh('/')) {
                        $this->restoreState();
                        return false;
                }
                $this->skipSpaces();
                if(!$this->name($name)){
                        $this->restoreState();
                        return false;
                }
                $name=strtolower($name);
                $this->skipSpaces();
                if(!$this->matchCh('>')) {
                        $this->restoreState();
                        return false;
                }
                return true;
        }

        protected function makeTag($tag, $params, $content, $short, $parentTag = null){
                $tag = strtolower($tag);

                // Получаем правила фильтрации тега
                $tagRules = isset($this->tagsRules[$tag]) ? $this->tagsRules[$tag] : null;

                // Проверка - родительский тег - контейнер, содержащий только другие теги (ul, table, etc)
                $parentTagIsContainer = $parentTag && isset($this->tagsRules[$parentTag][self::TR_TAG_CONTAINER]);

                // Вырезать тег вместе с содержанием
                if($tagRules && isset($this->tagsRules[$tag][self::TR_TAG_CUT])) return '';

                // Позволен ли тег
                if(!$tagRules || empty($tagRules[self::TR_TAG_ALLOWED])) return $parentTagIsContainer ? '' : $content;

                // Если тег находится внутри другого - может ли он там находится?
                if($parentTagIsContainer){
                        if(!isset($this->tagsRules[$parentTag][self::TR_TAG_CHILD_TAGS][$tag])) return '';
                }

                // Тег может находится только внтури другого тега
                if(isset($tagRules[self::TR_TAG_CHILD])){
                        if(!isset($tagRules[self::TR_TAG_PARENT][$parentTag])) return $content;
                }


                $resParams = array();
                foreach($params as $param=>$value){
                        $param = strtolower($param);
                        $value = trim($value);
                        if(empty($value)) continue;

                        // Атрибут тега разрешён? Какие возможны значения? Получаем список правил
                        $paramAllowedValues = isset($tagRules[self::TR_PARAM_ALLOWED][$param]) ? $tagRules[self::TR_PARAM_ALLOWED][$param] : false;
                        if(empty($paramAllowedValues)) continue;

                        // Если есть список разрешённых параметров тега
                        if(is_array($paramAllowedValues) && !in_array($value, $paramAllowedValues)) {
                                $this->eror("Недопустимое значение для атрибута тега $tag $param=$value");
                                continue;
                        // Если атрибут тега помечен как разрешённый, но правила не указаны - смотрим в массив стандартных правил для атрибутов
                        } elseif($paramAllowedValues === true && !empty($this->defaultTagParamRules[$param])){
                                $paramAllowedValues = $this->defaultTagParamRules[$param];
                        }

                        if(is_string($paramAllowedValues)){
                                switch($paramAllowedValues){
                                        case '#int':
                                                if(!is_numeric($value)) {
                                                        $this->eror("Недопустимое значение для атрибута тега $tag $param=$value. Ожидалось число");
                                                        continue(2);
                                                }
                                                break;

                                        case '#text':
                                                $value = htmlspecialchars($value);
                                                break;

                                        case '#link':
                                                // Ява-скрипт в ссылке
                                                if(preg_match('/javascript:/ui', $value)) {
                                                        $this->eror('Попытка вставить JavaScript в URI');
                                                        continue(2);
                                                }
                                                // Первый символ должен быть a-z0-9!
                                                if(!preg_match('/^[a-z0-9\/]/ui', $value)) {
                                                        $this->eror('URI: Первый символ адреса должен быть буквой или цифрой');
                                                        continue(2);
                                                }
                                                // HTTP в начале если нет
                                                if(!preg_match('/^(http|https|ftp):\/\//ui', $value) && !preg_match('/^\//ui', $value)) $value = 'http://'.$value;
                                                break;

                                        case '#image':
                                                // Ява-скрипт в пути к картинке
                                                if(preg_match('/javascript:/ui', $value)) {
                                                        $this->eror('Попытка вставить JavaScript в пути к изображению');
                                                        continue(2);
                                                }
                                                // HTTP в начале если нет
                                                if(!preg_match('/^http:\/\//ui', $value) && !preg_match('/^\//ui', $value)) $value = 'http://'.$value;
                                                break;

                                        default:
                                                $this->eror("Неверное описание атрибута тега в настройке Jevix: $param => $paramAllowedValues");
                                                continue(2);
                                                break;
                                }
                        }

                        $resParams[$param] = $value;
                }

                // Проверка обязятельных параметров тега
                // Если нет обязательных параметров возвращаем только контент
                $requiredParams = isset($tagRules[self::TR_PARAM_REQUIRED]) ? array_keys($tagRules[self::TR_PARAM_REQUIRED]) : array();
                if($requiredParams){
                        foreach($requiredParams as $requiredParam){
                                if(empty($resParams[$requiredParam])) return $content;
                        }
                }

                // Автодобавляемые параметры
                if(!empty($tagRules[self::TR_PARAM_AUTO_ADD])){
                foreach($tagRules[self::TR_PARAM_AUTO_ADD] as $name => $value) {
                    // If there isn't such attribute - setup it
                    if(!array_key_exists($name, $resParams)) {
                        $resParams[$name] = $value;
                    }
                }
                }

                // Пустой некороткий тег удаляем кроме исключений
                if (!isset($tagRules[self::TR_TAG_IS_EMPTY]) or !$tagRules[self::TR_TAG_IS_EMPTY]) {
                        if(!$short && empty($content)) return '';
                }
                // Собираем тег
                $text='<'.$tag;
                // Параметры
                foreach($resParams as $param=>$value) $text.=' '.$param.'="'.$value.'"';
                // Закрытие тега (если короткий то без контента)
                $text.= $short && $this->isXHTMLMode ? '/>' : '>';
                if(isset($tagRules[self::TR_TAG_CONTAINER])) $text .= "\r\n";
                if(!$short) $text.= $content.'</'.$tag.'>';
                if($parentTagIsContainer) $text .= "\r\n";
                if($tag == 'br') $text.="\r\n";
                return $text;
        }

        protected function comment(){
                if(!$this->matchStr('<!--')) return false;
                return $this->skipUntilStr('-->');
        }

        protected function anyThing(&$content = '', $parentTag = null){
                $this->skipNL();
                while($this->curChClass){
                        $tag = '';
                        $params = null;
                        $text = null;
                        $shortTag = false;
                        $name = null;

                        // Если мы находимся в режиме тега без текста
                        // пропускаем контент пока не встретится <
                        if($this->state == self::STATE_INSIDE_NOTEXT_TAG && $this->curCh!='<'){
                                $this->skipUntilCh('<');
                        }

                        // <Тег> кекст </Тег>
                        if($this->curCh == '<' && $this->tag($tag, $params, $text, $shortTag)){
                                // Преобразуем тег в текст
                                $tagText = $this->makeTag($tag, $params, $text, $shortTag, $parentTag);
                                $content.=$tagText;
                                // Пропускаем пробелы после <br> и запрещённых тегов, которые вырезаются парсером
                                if ($tag=='br') {
                                        $this->skipNL();
                                } elseif (empty($tagText)){
                                        $this->skipSpaces();
                                }

                        // Коментарий <!-- -->
                        } elseif($this->curCh == '<' && $this->comment()){
                                continue;

                        // Конец тега или символ <
                        } elseif($this->curCh == '<') {
                                // Если встречается <, но это не тег
                                // то это либо закрывающийся тег либо знак <
                                $this->saveState();
                                if($this->tagClose($name)){
                                        // Если это закрывающийся тег, то мы делаем откат
                                        // и выходим из функции
                                        // Но если мы не внутри тега, то просто пропускаем его
                                        if($this->state == self::STATE_INSIDE_TAG || $this->state == self::STATE_INSIDE_NOTEXT_TAG) {
                                                $this->restoreState();
                                                return false;
                                        } else {
                                                $this->eror('Не ожидалось закрывающегося тега '.$name);
                                        }
                                } else {
                                        if($this->state != self::STATE_INSIDE_NOTEXT_TAG) $content.=$this->entities2['<'];
                                        $this->getCh();
                                }

                        // Текст
                        } elseif($this->text($text)){
                                $content.=$text;
                        }
                }

                return true;
        }

        /**
         * Пропуск переводов строк подсчет кол-ва
         *
         * @param int $count ссылка для возвращения числа переводов строк
         * @return boolean
         */
        protected function skipNL(&$count = 0){
                if(!($this->curChClass & self::NL)) return false;
                $count++;
                $firstNL = $this->curCh;
                $nl = $this->getCh();
                while($this->curChClass & self::NL){
                        // Если символ новый строки ткой же как и первый увеличиваем счетчик
                        // новых строк. Это сработает при любых сочетаниях
                        // \r\n\r\n, \r\r, \n\n - две перевода
                        if($nl == $firstNL) $count++;
                        $nl = $this->getCh();
                        // Между переводами строки могут встречаться пробелы
                        $this->skipSpaces();
                }
                return true;
        }

        protected function dash(&$dash){
                if($this->curCh != '-') return false;
                $dash = '';
                $this->saveState();
                $this->getCh();
                // Несколько подряд
                while($this->curCh == '-') $this->getCh();
                if(!$this->skipNL() && !$this->skipSpaces()){
                        $this->restoreState();
                        return false;
                }
                $dash = $this->dash;
                return true;
        }

        protected function punctuation(&$punctuation){
                if(!($this->curChClass & self::PUNCTUATUON)) return false;
                $this->saveState();
                $punctuation = $this->curCh;
                $this->getCh();

                // Проверяем ... и !!! и ?.. и !..
                if($punctuation == '.' && $this->curCh == '.'){
                        while($this->curCh == '.') $this->getCh();
                        $punctuation = $this->dotes;
                } elseif($punctuation == '!' && $this->curCh == '!'){
                        while($this->curCh == '!') $this->getCh();
                        $punctuation = '!!!';
                } elseif (($punctuation == '?' || $punctuation == '!') && $this->curCh == '.'){
                        while($this->curCh == '.') $this->getCh();
                        $punctuation.= '..';
                }

                // Далее идёт слово - добавляем пробел
                if($this->curChClass & self::RUS) {
                        if($punctuation != '.') $punctuation.= ' ';
                        return true;
                // Далее идёт пробел, перенос строки, конец текста
                } elseif(($this->curChClass & self::SPACE) || ($this->curChClass & self::NL) || !$this->curChClass){
                        return true;
                } else {
                        $this->restoreState();
                        return false;
                }
        }

        protected function number(&$num){
                if(!(($this->curChClass & self::NUMERIC) == self::NUMERIC)) return false;
                $num = $this->curCh;
                $this->getCh();
                while(($this->curChClass & self::NUMERIC) == self::NUMERIC){
                        $num.= $this->curCh;
                        $this->getCh();
                }
                return true;
        }

        protected function htmlEntity(&$entityCh){
                if($this->curCh<>'&') return false;
                $this->saveState();
                $this->matchCh('&');
                if($this->matchCh('#')){
                        $entityCode = 0;
                        if(!$this->number($entityCode) || !$this->matchCh(';')){
                                $this->restoreState();
                                return false;
                        }
                        $entityCh = html_entity_decode("&#$entityCode;", ENT_COMPAT, 'UTF-8');
                        return true;
                } else{
                        $entityName = '';
                        if(!$this->name($entityName) || !$this->matchCh(';')){
                                $this->restoreState();
                                return false;
                        }
                        $entityCh = html_entity_decode("&$entityName;", ENT_COMPAT, 'UTF-8');
                        return true;
                }
        }

        /**
         * Кавычка
         *
         * @param boolean $spacesBefore были до этого пробелы
         * @param string $quote кавычка
         * @param boolean $closed закрывающаяся
         * @return boolean
         */
        protected function quote($spacesBefore,  &$quote, &$closed){
                $this->saveState();
                $quote = $this->curCh;
                $this->getCh();
                // Если не одна кавычка ещё не была открыта и следующий символ - не буква - то это нифига не кавычка
                if($this->quotesOpened == 0 && !(($this->curChClass & self::ALPHA) || ($this->curChClass & self::NUMERIC))) {
                        $this->restoreState();
                        return false;
                }
                // Закрывается тогда, одна из кавычек была открыта и (до кавычки не было пробела или пробел или пунктуация есть после кавычки)
                // Или, если открыто больше двух кавычек - точно закрываем
                $closed =  ($this->quotesOpened >= 2) ||
                          (($this->quotesOpened >  0) &&
                           (!$spacesBefore || $this->curChClass & self::SPACE || $this->curChClass & self::PUNCTUATUON));
                return true;
        }

        protected function makeQuote($closed, $level){
                $levels = count($this->textQuotes);
                if($level > $levels) $level = $levels;
                return $this->textQuotes[$level][$closed ? 1 : 0];
        }


        protected function text(&$text){
                $text = '';
                //$punctuation = '';
                $dash = '';
                $newLine = true;
                $newWord = true; // Возможно начало нового слова
                $url = null;
                $href = null;

                // Включено типографирование?
                //$typoEnabled = true;
                $typoEnabled = !$this->noTypoMode;

                // Первый символ может быть <, это значит что tag() вернул false
                // и < к тагу не относится
                while(($this->curCh != '<') && $this->curChClass){
                        $brCount = 0;
                        $spCount = 0;
                        $quote = null;
                        $closed = false;
                        $punctuation = null;
                        $entity = null;

                        $this->skipSpaces($spCount);

                        // автопреобразование сущностей...
                        if (!$spCount && $this->curCh == '&' && $this->htmlEntity($entity)){
                                $text.= isset($this->entities2[$entity]) ? $this->entities2[$entity] : $entity;
                        } elseif ($typoEnabled && ($this->curChClass & self::PUNCTUATUON) && $this->punctuation($punctuation)){
                                // Автопунктуация выключена
                                // Если встретилась пунктуация - добавляем ее
                                // Сохраняем пробел перед точкой если класс следующий символ - латиница
                                if($spCount && $punctuation == '.' && ($this->curChClass & self::LAT)) $punctuation = ' '.$punctuation;
                                $text.=$punctuation;
                                $newWord = true;
                        } elseif ($typoEnabled && ($spCount || $newLine) && $this->curCh == '-' && $this->dash($dash)){
                                // Тире
                                $text.=$dash;
                                $newWord = true;
                        } elseif ($typoEnabled && ($this->curChClass & self::HTML_QUOTE) && $this->quote($spCount, $quote, $closed)){
                                // Кавычки
                                $this->quotesOpened+=$closed ? -1 : 1;
                                // Исправляем ситуацию если кавычка закрыввается раньше чем открывается
                                if($this->quotesOpened<0){
                                        $closed = false;
                                        $this->quotesOpened=1;
                                }
                                $quote = $this->makeQuote($closed, $closed ? $this->quotesOpened : $this->quotesOpened-1);
                                if($spCount) $quote = ' '.$quote;
                                $text.= $quote;
                                $newWord = true;
                        } elseif ($spCount>0){
                                $text.=' ';
                                // после пробелов снова возможно новое слово
                                $newWord = true;
                        } elseif ($this->isAutoBrMode && $this->skipNL($brCount)){
                                // Перенос строки
                                $br = $this->br.$this->nl;
                                $text.= $brCount == 1 ? $br : $br.$br;
                                // Помечаем что новая строка и новое слово
                                $newLine = true;
                                $newWord = true;
                                // !!!Добавление слова
                        } elseif ($newWord && $this->isAutoLinkMode && ($this->curChClass & self::LAT) && $this->openedTag!='a' && $this->url($url, $href)){
                                // URL
                                $text.= $this->makeTag('a' , array('href' => $href), $url, false);
                        } elseif($this->curChClass & self::PRINATABLE){
                                // Экранируем символы HTML которые нельзя сувать внутрь тега (но не те? которые не могут быть в параметрах)
                                $text.=isset($this->entities2[$this->curCh]) ? $this->entities2[$this->curCh] : $this->curCh;
                                $this->getCh();
                                $newWord = false;
                                $newLine = false;
                                // !!!Добавление к слова
                        } else {
                                // Совершенно непечатаемые символы которые никуда не годятся
                                $this->getCh();
                        }
                }

                // Пробелы
                $this->skipSpaces();
                return $text != '';
        }

        protected function url(&$url, &$href){
                $this->saveState();
                $url = '';
                //$name = $this->name();
                //switch($name)
                $urlChMask = self::URL | self::ALPHA;

                if($this->matchStr('http://')){
                        while($this->curChClass & $urlChMask){
                                $url.= $this->curCh;
                                $this->getCh();
                        }

                        if(!strlen($url)) {
                                $this->restoreState();
                                return false;
                        }

                        $href = 'http://'.$url;
                        return true;
                } elseif($this->matchStr('www.')){
                        while($this->curChClass & $urlChMask){
                                $url.= $this->curCh;
                                $this->getCh();
                        }

                        if(!strlen($url)) {
                                $this->restoreState();
                                return false;
                        }

                        $url = 'www.'.$url;
                        $href = 'http://'.$url;
                        return true;
                }
                $this->restoreState();
                return false;
        }

        protected function eror($message){
                $str = '';
                $strEnd = min($this->curPos + 8, $this->textLen);
                for($i = $this->curPos; $i < $strEnd; $i++){
                        $str.=$this->textBuf[$i];
                }

                $this->errors[] = array(
                        'message' => $message,
                        'pos'     => $this->curPos,
                        'ch'      => $this->curCh,
                        'line'    => 0,
                        'str'     => $str,
                );
        }
}

/**
 * Функция ord() для мультибайтовы строк
 *
 * @param string $c символ utf-8
 * @return int код символа
 */
function uniord($c) {
    $h = ord($c{0});
    if ($h <= 0x7F) {
        return $h;
    } else if ($h < 0xC2) {
        return false;
    } else if ($h <= 0xDF) {
        return ($h & 0x1F) << 6 | (ord($c{1}) & 0x3F);
    } else if ($h <= 0xEF) {
        return ($h & 0x0F) << 12 | (ord($c{1}) & 0x3F) << 6
                                 | (ord($c{2}) & 0x3F);
    } else if ($h <= 0xF4) {
        return ($h & 0x0F) << 18 | (ord($c{1}) & 0x3F) << 12
                                 | (ord($c{2}) & 0x3F) << 6
                                 | (ord($c{3}) & 0x3F);
    } else {
        return false;
    }
}

/**
 * Функция chr() для мультибайтовы строк
 *
 * @param int $c код символа
 * @return string символ utf-8
 */
function unichr($c) {
    if ($c <= 0x7F) {
        return chr($c);
    } else if ($c <= 0x7FF) {
        return chr(0xC0 | $c >> 6) . chr(0x80 | $c & 0x3F);
    } else if ($c <= 0xFFFF) {
        return chr(0xE0 | $c >> 12) . chr(0x80 | $c >> 6 & 0x3F)
                                    . chr(0x80 | $c & 0x3F);
    } else if ($c <= 0x10FFFF) {
        return chr(0xF0 | $c >> 18) . chr(0x80 | $c >> 12 & 0x3F)
                                    . chr(0x80 | $c >> 6 & 0x3F)
                                    . chr(0x80 | $c & 0x3F);
    } else {
        return false;
    }
}
 
 
 
// class.recaptcha.php 
 
/*
 * This is a PHP library that handles calling reCAPTCHA.
 *    - Documentation and latest version
 *          http://recaptcha.net/plugins/php/
 *    - Get a reCAPTCHA API Key
 *          http://recaptcha.net/api/getkey
 *    - Discussion group
 *          http://groups.google.com/group/recaptcha
 *
 * Copyright (c) 2007 reCAPTCHA -- http://recaptcha.net
 * AUTHORS:
 *   Mike Crawford
 *   Ben Maurer
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * The reCAPTCHA server URL's
 */
define("RECAPTCHA_API_SERVER", "http://api.recaptcha.net");
define("RECAPTCHA_API_SECURE_SERVER", "https://api-secure.recaptcha.net");
define("RECAPTCHA_VERIFY_SERVER", "api-verify.recaptcha.net");

/**
 * Encodes the given data into a query string format
 * @param $data - array of string elements to be encoded
 * @return string - encoded request
 */
function _recaptcha_qsencode ($data) {
        $req = "";
        foreach ( $data as $key => $value )
                $req .= $key . '=' . urlencode( stripslashes($value) ) . '&';

        // Cut the last '&'
        $req=substr($req,0,strlen($req)-1);
        return $req;
}



/**
 * Submits an HTTP POST to a reCAPTCHA server
 * @param string $host
 * @param string $path
 * @param array $data
 * @param int port
 * @return array response
 */
function _recaptcha_http_post($host, $path, $data, $port = 80) {

        $req = _recaptcha_qsencode ($data);

        $http_request  = "POST $path HTTP/1.0\r\n";
        $http_request .= "Host: $host\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= "Content-Length: " . strlen($req) . "\r\n";
        $http_request .= "User-Agent: reCAPTCHA/PHP\r\n";
        $http_request .= "\r\n";
        $http_request .= $req;

        $response = '';
        if( false == ( $fs = @fsockopen($host, $port, $errno, $errstr, 10) ) ) {
                die ('Could not open socket');
        }

        fwrite($fs, $http_request);

        while ( !feof($fs) )
                $response .= fgets($fs, 1160); // One TCP-IP packet
        fclose($fs);
        $response = explode("\r\n\r\n", $response, 2);

        return $response;
}



/**
 * Gets the challenge HTML (javascript and non-javascript version).
 * This is called from the browser, and the resulting reCAPTCHA HTML widget
 * is embedded within the HTML form it was called from.
 * @param string $pubkey A public key for reCAPTCHA
 * @param string $error The error given by reCAPTCHA (optional, default is null)
 * @param boolean $use_ssl Should the request be made over ssl? (optional, default is false)

 * @return string - The HTML to be embedded in the user's form.
 */
function recaptcha_get_html ($pubkey, $error = null, $use_ssl = false)
{
if ($pubkey == null || $pubkey == '') {
die ("To use reCAPTCHA you must get an API key from <a href='http://recaptcha.net/api/getkey'>http://recaptcha.net/api/getkey</a>");
}

if ($use_ssl) {
                $server = RECAPTCHA_API_SECURE_SERVER;
        } else {
                $server = RECAPTCHA_API_SERVER;
        }

        $errorpart = "";
        if ($error) {
           $errorpart = "&amp;error=" . $error;
        }
        return '<script type="text/javascript" src="'. $server . '/challenge?k=' . $pubkey . $errorpart . '"></script>

<noscript>
  <iframe src="'. $server . '/noscript?k=' . $pubkey . $errorpart . '" height="300" width="500" frameborder="0"></iframe><br/>
  <textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
  <input type="hidden" name="recaptcha_response_field" value="manual_challenge"/>
</noscript>';
}




/**
 * A ReCaptchaResponse is returned from recaptcha_check_answer()
 */
class ReCaptchaResponse {
        var $is_valid;
        var $error;
}


/**
  * Calls an HTTP POST function to verify if the user's guess was correct
  * @param string $privkey
  * @param string $remoteip
  * @param string $challenge
  * @param string $response
  * @param array $extra_params an array of extra variables to post to the server
  * @return ReCaptchaResponse
  */
function recaptcha_check_answer ($privkey, $remoteip, $challenge, $response, $extra_params = array())
{
if ($privkey == null || $privkey == '') {
die ("To use reCAPTCHA you must get an API key from <a href='http://recaptcha.net/api/getkey'>http://recaptcha.net/api/getkey</a>");
}

if ($remoteip == null || $remoteip == '') {
die ("For security reasons, you must pass the remote ip to reCAPTCHA");
}



        //discard spam submissions
        if ($challenge == null || strlen($challenge) == 0 || $response == null || strlen($response) == 0) {
                $recaptcha_response = new ReCaptchaResponse();
                $recaptcha_response->is_valid = false;
                $recaptcha_response->error = 'incorrect-captcha-sol';
                return $recaptcha_response;
        }

        $response = _recaptcha_http_post (RECAPTCHA_VERIFY_SERVER, "/verify",
                                          array (
                                                 'privatekey' => $privkey,
                                                 'remoteip' => $remoteip,
                                                 'challenge' => $challenge,
                                                 'response' => $response
                                                 ) + $extra_params
                                          );

        $answers = explode ("\n", $response [1]);
        $recaptcha_response = new ReCaptchaResponse();

        if (trim ($answers [0]) == 'true') {
                $recaptcha_response->is_valid = true;
        }
        else {
                $recaptcha_response->is_valid = false;
                $recaptcha_response->error = $answers [1];
        }
        return $recaptcha_response;

}

/**
 * gets a URL where the user can sign up for reCAPTCHA. If your application
 * has a configuration page where you enter a key, you should provide a link
 * using this function.
 * @param string $domain The domain where the page is hosted
 * @param string $appname The name of your application
 */
function recaptcha_get_signup_url ($domain = null, $appname = null) {
return "http://recaptcha.net/api/getkey?" .  _recaptcha_qsencode (array ('domain' => $domain, 'app' => $appname));
}

function _recaptcha_aes_pad($val) {
$block_size = 16;
$numpad = $block_size - (strlen ($val) % $block_size);
return str_pad($val, strlen ($val) + $numpad, chr($numpad));
}

/* Mailhide related code */

function _recaptcha_aes_encrypt($val,$ky) {
if (! function_exists ("mcrypt_encrypt")) {
die ("To use reCAPTCHA Mailhide, you need to have the mcrypt php module installed.");
}
$mode=MCRYPT_MODE_CBC;   
$enc=MCRYPT_RIJNDAEL_128;
$val=_recaptcha_aes_pad($val);
return mcrypt_encrypt($enc, $ky, $val, $mode, "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");
}


function _recaptcha_mailhide_urlbase64 ($x) {
return strtr(base64_encode ($x), '+/', '-_');
}

/* gets the reCAPTCHA Mailhide url for a given email, public key and private key */
function recaptcha_mailhide_url($pubkey, $privkey, $email) {
if ($pubkey == '' || $pubkey == null || $privkey == "" || $privkey == null) {
die ("To use reCAPTCHA Mailhide, you have to sign up for a public and private key, " .
     "you can do so at <a href='http://mailhide.recaptcha.net/apikey'>http://mailhide.recaptcha.net/apikey</a>");
}


$ky = pack('H*', $privkey);
$cryptmail = _recaptcha_aes_encrypt ($email, $ky);

return "http://mailhide.recaptcha.net/d?k=" . $pubkey . "&c=" . _recaptcha_mailhide_urlbase64 ($cryptmail);
}

/**
 * gets the parts of the email to expose to the user.
 * eg, given johndoe@example,com return ["john", "example.com"].
 * the email is then displayed as john...@example.com
 */
function _recaptcha_mailhide_email_parts ($email) {
$arr = preg_split("/@/", $email );

if (strlen ($arr[0]) <= 4) {
$arr[0] = substr ($arr[0], 0, 1);
} else if (strlen ($arr[0]) <= 6) {
$arr[0] = substr ($arr[0], 0, 3);
} else {
$arr[0] = substr ($arr[0], 0, 4);
}
return $arr;
}

/**
 * Gets html to display an email address given a public an private key.
 * to get a key, go to:
 *
 * http://mailhide.recaptcha.net/apikey
 */
function recaptcha_mailhide_html($pubkey, $privkey, $email) {
$emailparts = _recaptcha_mailhide_email_parts ($email);
$url = recaptcha_mailhide_url ($pubkey, $privkey, $email);

return htmlentities($emailparts[0]) . "<a href='" . htmlentities ($url) .
"' onclick=\"window.open('" . htmlentities ($url) . "', '', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=500,height=300'); return false;\" title=\"Reveal this e-mail address\">...</a>@" . htmlentities ($emailparts [1]);

}


 ?>