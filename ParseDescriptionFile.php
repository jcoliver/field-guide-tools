<?php


// two files a description-file and taxonomy-file
function process_files() {
	
	$return_string = "";
	$desc_file_ok = check_file('description-file');
	$tax_file_ok = check_file('taxonomy-file');

	if (!$desc_file_ok) {
		$return_string .= "<p>Problem with description file</p>";
	}
	if (!$tax_file_ok) {
		$return_string .= "<p>Problem with taxonomy file</p>";
	}

	if ($desc_file_ok && $tax_file_ok) {
		$description_file_name = $_FILES['description-file']['name'];
		$return_string .= "<p>Description file: " . $description_file_name . "</p>";
		$taxonomy_file_name = $_FILES['taxonomy-file']['name'];
		$return_string .= "<p>Taxonomy file: " . $taxonomy_file_name . "</p>";

		
		
		/*
		if(!is_uploaded_file($_FILES['userfile']['tmp_name'])){
			$upload_error = $_FILES['userfile']['error'];
			setcookie('mbuploaderror', $upload_error, 0, COOKIE_PATH);
		} else {
			$file = $tmpfile;
			// check for readability
			if (!file_exists($file) or !is_readable($file)) {
				setcookie('mbreaderror', '1', 0, COOKIE_PATH);
			} else {
				// Get the file contents
				$all_file = file_get_contents($file);
				// Check for UTF-8
				if (!mb_check_encoding($all_file, "UTF-8")) {
					setcookie('notutf8', '1', 0, COOKIE_PATH);
				} else {
					// split file into array, each line is an element in the array
					// First replace any \r newlines with \n
					$newlines = substr_count($all_file,"\r");
					if ($newlines > 0) {
						$all_file = str_replace("\r", "\n", $all_file);
					}
		
					$all_file = convert_smart_quotes($all_file);
		
					$file_as_array = explode("\n", $all_file);
				}
			}
		}
		*/
		
	
	}
	/*	
	if (!$_FILES || !$_FILES['description-file'] || !$_FILES['taxonomy-file']) {
		return "<p>Files array empty or inaccessible</p>";
	}
	
	if (!array_key_exists('name', $_FILES['description-file'])) {
		return "<p>No description file</p>";
	} elseif (!$_FILES['description-file']['name']) {
		return "<p>No description file</p>";
	}
	
	if (!array_key_exists('name', $_FILES['taxonomy-file'])) {
		return "<p>No taxonomy file</p>";
	} elseif (!$_FILES['taxonomy-file']['name']) {
		return "<p>No taxonomy file</p>";
	}
	*/

	
	return $return_string;
}

function check_file($files_index) {
	if (!$files_index) {
		return FALSE;
	}
	if (!(strlen($files_index) > 0)) {
		return FALSE;
	}
	if (!$_FILES) {
		return FALSE;
	}
	if (!array_key_exists($files_index, $_FILES)) {
		return FALSE;
	}
	if (!$_FILES[$files_index]) {
		return FALSE;
	}
	if (!array_key_exists('name', $_FILES[$files_index])) {
		return FALSE;
	}
	if (!$_FILES[$files_index]['name']) {
		return FALSE;
	}
	if (!(strlen($_FILES[$files_index]['name']) > 0)) {
		return FALSE;
	}
	if (!is_uploaded_file($_FILES[$files_index]['tmp_name'])) {
		return FALSE;
	} 
	return TRUE;
}

function fromCastor() {
	
	// in the POST section from the castor file
	
	$redirect = TRUE;
	$show_acceptable_values = FALSE;
	// We've already done authorization, but need to check to see if a file was uploaded, and if so, to make sure it is not too large.
	$allowed_filetypes = array("txt");
	$filename = $_FILES['userfile']['name'];
	$tmpfile = $_FILES['userfile']['tmp_name'];
	$filename_exploded = explode(".", $filename);
	$ext = strtolower(end($filename_exploded));
	// check for extension
	if(!in_array($ext, $allowed_filetypes)){
		setcookie('mbbadextension', '1', 0, COOKIE_PATH);
	} else {
		$max_filesize = 1048576; //1.0 MB
		// check for size
		if(filesize($_FILES['userfile']['tmp_name']) > $max_filesize){
			$max_in_mb = $max_filesize/1000000;
			$max_in_mb = round($max_in_mb, 1);
			$upload_in_mb = filesize($_FILES['userfile']['tmp_name']) / 1000000;
			$upload_in_mb = round($upload_in_mb,1);
			setcookie('mbtoolarge', $upload_in_mb, 0, COOKIE_PATH);
		} else {
			// check for successful upload
			if(!is_uploaded_file($_FILES['userfile']['tmp_name'])){
				$upload_error = $_FILES['userfile']['error'];
				setcookie('mbuploaderror', $upload_error, 0, COOKIE_PATH);
			} else {
				$file = $tmpfile;
				// check for readability
				if (!file_exists($file) or !is_readable($file)) {
					setcookie('mbreaderror', '1', 0, COOKIE_PATH);
				} else {
					// Get the file contents
					$all_file = file_get_contents($file);
					// Check for UTF-8
					if (!mb_check_encoding($all_file, "UTF-8")) {
						setcookie('notutf8', '1', 0, COOKIE_PATH);
					} else {
						// split file into array, each line is an element in the array
						// First replace any \r newlines with \n
						$newlines = substr_count($all_file,"\r");
						if ($newlines > 0) {
							$all_file = str_replace("\r", "\n", $all_file);
						}
	
						$all_file = convert_smart_quotes($all_file);
	
						$file_as_array = explode("\n", $all_file);
						// Start with a validation check; need to make sure:
						// Parsed filename is valid
						//	-first token refers to specimen in database (which also has locality information)
						//  -second token is appropriate part ('habitus', 'pronotum', 'genleft', etc)
						//	-third token is acceptable ('scale' or 'edf')
					}
				}
			}
		}
	}
}