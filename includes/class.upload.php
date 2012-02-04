<?php

/* Handles image uploads. */
class Upload {
	
	/* The file array from $_FILES. */
	private $file;
	
	/* Attributes of the file. */
	private $type;
	private $new_name;
	private $original_name;
	private $md5;
	
	/* Is the upload successful so far? */
	public $success = false;
	
	/* Descriptions of constants from $_FILES[]['error']. */
	private $errors = array
	(
		UPLOAD_ERR_PARTIAL     => 'The image was only partially uploaded.',
		UPLOAD_ERR_INI_SIZE    => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
		UPLOAD_ERR_NO_FILE     => 'No file was uploaded.',
		UPLOAD_ERR_NO_TMP_DIR  => 'Missing a temporary directory.',
		UPLOAD_ERR_CANT_WRITE  => 'Failed to write image to disk.',
	);
	
	/* Valid image file types. (JPEG will be transformed to JPG). */
	private $valid_types = array('jpg', 'gif', 'png');
	
	/* Potentially unsafe characters, to be stripped from the file name. */
	private $unsafe_chars = array
	(
		'/', '<', '>',
		'"', "'", '%'
	);
	
	const FULL_DIR  = '/img/';
	const THUMB_DIR = '/thumbs/';
	
	/**
	 * Get properties and validate the upload.
	 * @param  array  $file  An image from the $_FILES superglobal.
	 */
	public function __construct($file) {		
		$this->file = $file;
		
		/* Check for PHP-issued errors. */
		if($file['error'] !== UPLOAD_ERR_OK) {
			$error = (isset($this->errors[$file['error']]) ? $this->errors[$file['error']] : 'Unable to upload image.');
			throw new Exception($error);
		}
		
		/* Check that our target dirs are writable */
		if( ! is_writable(SITE_ROOT . self::FULL_DIR)) {
			throw new Exception('The image directory (' . self::FULL_DIR . ') is not writable.');
		}
		
		if( ! is_writable(SITE_ROOT . self::THUMB_DIR)) {
			throw new Exception('The thumbnail directory (' . self::THUMB_DIR . ') is not writable.');
		}

		/* Get file properties. */
		$valid_name = preg_match('/(.+)\.([a-z0-9]+)$/i', $file['name'], $match);
		$this->type = str_replace('jpeg', 'jpg', strtolower($match[2]));
		$this->md5 = md5_file($file['tmp_name']);
		$this->new_name = $_SERVER['REQUEST_TIME'] . mt_rand(99, 999999) . '.' . $this->type;
		$this->original_name = str_replace($this->unsafe_chars, '', $file['name']);
		$this->original_name = substr(trim($this->original_name), 0, 70);
				
		if ($valid_name === 0) {
			throw new Exception('The image has an invalid file name.');
		}
		
		if ( ! in_array($this->type, $this->valid_types)) {
			$last = array_pop($this->valid_types);
			throw new Exception('Only ' . implode(', ', $this->valid_types) . ', and ' . $last . ' files are allowed.');
		}
		
		if ($file['size'] > MAX_IMAGE_SIZE) {
			throw new Exception('Uploaded images can be no greater than ' . round(MAX_IMAGE_SIZE / 1048576, 2) . ' MB. ');
		}
		
		$this->success = true;	
	}
	
	/**
	 * Moves an image into the public dirs and associates it with a post.
	 * @param  string  $post_type  Either 'reply' or 'topic'
	 * @param  int     $post_id    The ID of the post in the topic/replies table.  
	 */
	public function move($post_type, $post_id) {
		global $db;
		
		if($post_type !== 'topic' && $post_type !== 'reply') {
			throw new Exception('Invalid post type.');
		}
		
		/* Check if an identical image has already been uploaded. */
		$res = $db->q('SELECT file_name FROM images WHERE md5 = ? AND deleted = 0 LIMIT 1', $this->md5);
		$previous_image = $res->fetchColumn();				
				
		if ($previous_image) {
			/* An identical version exists -- just link to it. */
			$this->new_name = $previous_image;
		} else {
			/* Create a thumbnail and move file into the public directory. */
			$this->thumbnail();
			move_uploaded_file($this->file['tmp_name'], SITE_ROOT . self::FULL_DIR . $this->new_name);
		}

		$db->q
		(
			'INSERT INTO images 
			(file_name, original_name, md5, ' . $post_type . '_id) VALUES 
			(?, ?, ?, ?)', 
			$this->new_name, $this->original_name, $this->md5, $post_id
		);	
	}
	
	/* Generates a thumbnail for this upload. */
	private function thumbnail() {
		$dest = SITE_ROOT . self::THUMB_DIR . $this->new_name;
		$type = strtolower($this->type);
		
		switch($type) {
			case 'jpg':
				$image = imagecreatefromjpeg($this->file['tmp_name']);
			break;
										
			case 'gif':
				$image = imagecreatefromgif($this->file['tmp_name']);
			break;
										
			case 'png':
				$image = imagecreatefrompng($this->file['tmp_name']);
			break;
		}
		
		$width = imagesx($image);
		$height = imagesy($image);
		$max_dimensions = ($type == 'gif' ? MAX_GIF_DIMENSIONS : MAX_IMAGE_DIMENSIONS);
		
		if($width > $max_dimensions || $height > $max_dimensions) {
			$percent = $max_dimensions / ( ($width > $height) ? $width : $height );
											
			$new_width = $width * $percent;
			$new_height = $height * $percent;
		} else {
			/* The full image is small enough to be the thumbnail. */
			copy($this->file['tmp_name'], $dest);
			
			return;
		}

		if(IMAGEMAGICK) {
			/* ImageMagick -- just use the CLI, it's much faster than PHP's extension */
			exec('convert ' . escapeshellarg($this->file['tmp_name']) . ' -quality ' . ($type == 'gif' ? '75' : '90') . ' -resize ' . (int) $new_width. 'x' . (int) $new_height . ' ' . escapeshellarg($dest));
			
			return;
		}
		
		/* GD */
		$thumbnail = imagecreatetruecolor($new_width, $new_height) ; 
		imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
			
		switch($type) {
			case 'jpg':
				imagejpeg($thumbnail, $dest, 70);
			break;
									
			case 'gif':
				imagegif($thumbnail, $dest);
			break;
									
			case 'png':
				imagepng($thumbnail, $dest);
		}
					
		imagedestroy($thumbnail);
		if(gettype($image) === 'resource') {
			imagedestroy($image);
		}
	}
}

?>