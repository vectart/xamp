<?php

	class xfiles {
		private	$var_index;
		private $files_path = TMP_FILE_DIR;
		private	$mime_types = array (
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

//		public function __destruct () {
			//$this -> removeFiles ($this -> files);
//		}
	
	}
?>
