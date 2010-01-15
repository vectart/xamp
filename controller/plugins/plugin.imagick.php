<?php
	private function parseImagick ($node) {
		$xpath = $this -> xpath;
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
?>
