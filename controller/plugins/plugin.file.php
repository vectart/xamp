<?php
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
?>
