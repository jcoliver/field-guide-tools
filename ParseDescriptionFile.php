<?php


// two files a description-file and taxonomy-file
function process_files() {
	include_once 'RequestedFields.php';	
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
		
		$desc_file_fields = get_desc_file_fields();
		$tax_file_fields = get_tax_file_fields();
		
		$desc_file = $_FILES['description-file']['tmp_name'];
		$desc_file_array = file_to_array($desc_file);

		$tax_file = $_FILES['taxonomy-file']['tmp-name'];
		$tax_file_array = file_to_array($tax_file);
		
		if ($desc_file_array && $tax_file_array) {
			$desc_header = $desc_file_array[0];
			$tax_header = $tax_file_array[0];
			$line_count = 0;
			$return_string .= "<h2>Taxonomy file:</h2>";
			foreach ($tax_file_array as $tax_file_line) {
				if ($line_count == 0) { // header row
					$return_string .= "<h4>Header row: " . $tax_file_line . "</h4>";
				} else {
					$return_string .= "<p>Row " . ($line_count + 1) . ": " . $tax_file_line . "</p>";
				}
				$line_count++;
			}
			/***/
			$line_count = 0;
			$return_string .= "<h2>Description file:</h2>";
			foreach ($desc_file_array as $desc_file_line) {
				if ($line_count == 0) { // header row
					$return_string .= "<h4>Header row: " . $desc_file_line . "</h4>";
				} else {
					$return_string .= "<p>Row " . ($line_count + 1) . ": " . $desc_file_line . "</p>";
				}
				$line_count++;
			}
			/***/
			// TODO: Need to remember to strip tags before sending to the JSON file
		} else {
			$return_string .= "<p>Error reading files</p>";
		}

		/*
		$taxonomy_file_name = $_FILES['taxonomy-file']['name'];
		$return_string .= "<p>Taxonomy file: " . $taxonomy_file_name . "</p>";
		*/
	}

	// setup array of fields to extract
	// e.g. descriptions ("Diagnostic Description", "Distribution", "Habitat")
	// and images ("Habitat image", "Pronotum image", "Elytral microsculpture")
	// and maybe separate from descriptions: ("Similar species") - or make this a separate array
	// in JSON file...
	
	return $return_string;
}

function check_file($files_index) { //TODO: revise returns so they are text strings with explanation of problem.
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
	$file = $_FILES[$files_index]['tmp_name'];
	if (!file_exists($file) or !is_readable($file)) {
		return FALSE;
	}
	return TRUE;
}

/**
 * Converts smart quotes to regular quotes and em-dash to dash in passed string
 *
 * @param string $string
 * 	The string on which to perform replacement
 *
 * @return Returns version of passed string with smart quotes (‘,’,”,“) replaced by
 * regular quotes (' and "); replaces em-dash (–) with dash(-).
 */
function convert_smart_quotes($string) {
	$search = array("‘",
			"’",
			"”",
			"“",
			"–");
	
	$replace = array("'",
			"'",
			"\"",
			"\"",
			'-');

	return str_replace($search, $replace, $string);
}

function file_to_array($filename) {
	$file_string = file_get_contents($filename);
	if (!$file_string) {
		return FALSE;
	}
	if (!mb_check_encoding($file_string, "UTF-8")) {
		return FALSE;
	}

	$newlines = substr_count($file_string, "\r");
	if ($newlines > 0) {
		$file_string = str_replace("\r", "\n", $file_string);
	}
	$file_string = convert_smart_quotes($file_string);
	// A pair of cludges to deal with in-field newlines
	$file_string = str_replace(">\n\"", ">\"", $file_string);
	$file_string = str_replace("/p>\n<p", "/p><p", $file_string);
	// And another pair to remove unnecessary quotes around paragraphs
	$file_string = str_replace("\"<p>", "<p>", $file_string);
	$file_string = str_replace("</p>\"", "</p>", $file_string);

	$file_array = explode("\n", $file_string);

	return $file_array;
	
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