<?php

/**
 * Entry point for processing files. Called from parse-description-file.html
 * 
 * @return string
 * HTML-formatted string with details about success or failure of conversions
 */
function process_files() {
	include_once 'requested_fields.php';
	// TODO: make this an array (see also commented out sections below)
	// $top_conceptguids_array = array("bembidion.info:taxon-concepts:000007");
	$top_concept_guid = "bembidion.info:taxon-concepts:000007"; // this is the one we want replaced with "none"
	// TODO: make array of strings to skip?  Skip none is empty array (see also commented out sections below)
	// $strings_to_skip = array("unidentified");
	$skip_unidentified = TRUE;
	$json_file_name = "data/taxa.json"; // Make sure you can write to this file
	$tmp_path = "tmp/";

	// paths to replace in media files.  See MediaDetails
	$replacement_paths = array (
			array (
					'search' => "public://",
					'replace' => "http://bembidion.info/sites/bembidion.info/files/" 
			) 
	);
	$media_details = new MediaDetails("data/media/", $tmp_path, $replacement_paths);	

	$dtz = new DateTimeZone("America/Los_Angeles");
	$start_time = new DateTime("NOW", $dtz);
	
	$return_string = "";
	$desc_file_errors = check_file('description-file');
	$desc_file_ok = strlen(trim($desc_file_errors)) == 0;

	$tax_file_errors = check_file('taxonomy-file');
	$tax_file_ok = strlen(trim($tax_file_errors)) == 0;
	
	//$desc_file_ok = check_file('description-file');
	//$tax_file_ok = check_file('taxonomy-file');

	if (!$desc_file_ok) {
		$return_string .= "<p>Problem with description file:</p>";
		$return_string .= "<p>" . $desc_file_errors . "</p>";
	}
	if (!$tax_file_ok) {
		$return_string .= "<p>Problem with taxonomy file</p>";
		$return_string .= "<p>" . $tax_file_errors . "</p>";
	}

	if ($desc_file_ok && $tax_file_ok) {
		// start by just getting a json of taxonomy (id, parent, and name)
		$desc_file = $_FILES['description-file']['tmp_name'];
		$desc_file_array = file_to_array($desc_file);
		
		$tax_file = $_FILES['taxonomy-file']['tmp_name'];
		$tax_file_array = file_to_array($tax_file);
		
		if ($desc_file_array && $tax_file_array) {
			$desc_header = $desc_file_array[0];
			$desc_mapping = get_mapping("description", $desc_header);
			$desc_as_array = desc_to_array($desc_mapping, $desc_file_array);
/*			
$return_string .= "<h3>DESCRIPTION TITLES: </h3>\n";
foreach ($desc_as_array as $desc_title => $desc_value) {
	$return_string .= "<p>" . $desc_title . "</p>\n";
}
$return_string .= "<h3>END DESCRIPTION TITLES</h3>\n";
*/			
			$tax_header = $tax_file_array[0];
			$tax_mapping = get_mapping("taxonomy", $tax_header);
				
			$taxons_for_json = array();
			for ($line = 1; $line < count($tax_file_array); $line++) {
				$one_taxon = $tax_file_array[$line];
				if (strlen(trim($one_taxon)) > 0) {
					$taxon_array = explode("\t", $one_taxon);
					// Need to see if this is unidentified and make decision about whether to proceed
					$name = $taxon_array[$tax_mapping['Term name']];
					$guid = $taxon_array[$tax_mapping['GUID']];
					if ($guid != $top_concept_guid) {
					// if (!in_array($guid, $top_concept_guids_array)) {
						$is_unidentified = substr_count(strtolower($name), "unidentified") > 0;
						// $skip = FALSE;
						// foreach ($strings_to_skip as $to_skip) { // TODO: could make this a while that exits when $skip == TRUE
						// 	if (substr_count($name, $to_skip) > 0) {
						//		$skip = TRUE;
						// 	}
						// }
						if (!$is_unidentified || ($is_unidentified && !$skip_unidentified)) {
						// if (!$skip) {
							$for_taxons = array();
							foreach ($tax_mapping as $column_name => $column_position) {
								$value = $taxon_array[$column_position];
								switch ($column_name) {
									case "Term name":
										$for_taxons['name'] = $value;
										break;
									case "GUID":
										// for id references (and for our purposes, parentid's too), 
										// strip anything not a digit or letter (".", "-", and ":" may have been causing problems)
										// Remove any non-alphanumeric characters
										$value = preg_replace("/[^A-Za-z0-9 ]/", '', $value);
										$for_taxons['id'] = $value;
										break;
									case "Parent GUID":
										if ($value != $top_concept_guid) {
										// if (!in_array($value, $top_conceptguids_array)) {
											// Remove any non-alphanumeric characters
											$value = preg_replace("/[^A-Za-z0-9 ]/", '', $value);
											$for_taxons['parentid'] = $value;
										} else {
											$for_taxons['parentid'] = "none";
										}
										break;
									case "Rank":
										$rank = strtolower($value);
										if ($rank == "species" || $rank == "subspecies") {
											$for_taxons['category'] = "terminal";
										} else {
											$for_taxons['category'] = "internal";
										}
										break;
								} // end switch on column name
							} // end looping over all mappings
							if (count($for_taxons) > 0) {
								// Now see if there is description information we should add
								if (array_key_exists($name, $desc_as_array)) {
									$description = $desc_as_array[$name];
									$for_taxons = add_description($description, $for_taxons, $media_details);
/*									
$return_string .= "<p>Description: </p>\n";
foreach ($description as $d_key => $d_value) {
	$return_string .= "<p>" . $d_key . " = " . $d_value . "</p>\n";
}
*/
								}
								$taxons_for_json[] = $for_taxons;
							}
						}	
					}
				}
			}
			
			$taxons_json = json_encode($taxons_for_json, JSON_PRETTY_PRINT);
			// File paths have "\/" instead of "/".  replace so we can find files
			// This is pretty drastic, but too bad
			$taxons_json = str_replace("\\/", "/", $taxons_json);
			
			if ($taxons_json) {
				// Have to be sure we have read & write permissions on system
				$json_file_path = $tmp_path . $json_file_name;
				if ($json_file_handle = fopen($json_file_path, "w")) {
					$write = fwrite($json_file_handle, $taxons_json);
					if ($write) {
						fclose ( $json_file_handle );
						$return_string .= "<p>Data write complete</p>";
					} else {
						$return_string .= "<p>Error writing file</p>";
					}
				} else {
					$return_string .= "<p>Error.  Could not access file path '{$json_file_path}'</p>";
								}
			} else {
				$return_string .= "<p>Error encoding for json</p>";
			}
			$return_string .= "<h2>FIN</h2>";
		}
	}

	$end_time = new DateTime("NOW", $dtz);
	$proc_time = $start_time->diff($end_time);
	$proc_time_string = $proc_time->format("%h hours %i minutes %s seconds");
	$return_string .= "<h4>Total processing time: {$proc_time_string}</h4>";
	
	return $return_string;
}

/**
 * Checks to make sure the file referenced by the given key in the $_FILES array 
 * is valid.
 * 
 * @param string $files_index
 * 	The key to check in $_FILES array
 * 
 * @return string
 * <p>In the case of no errors, returns an empty (zero-length) string; if there 
 * are problems, returns string with details about the problem.</p>
 */
function check_file($files_index) {
	if (!$files_index) {
		return "Null passed to check_file";
	}
	if (!(strlen($files_index) > 0)) {
		return "Empty string passed to check_file";
	}
	if (!$_FILES) {
		return "FILES array null in check_file";
	}
	if (!array_key_exists($files_index, $_FILES)) {
		return "key '{$files_index}' does not exist in FILES array in check_file";
	}
	if (!$_FILES[$files_index]) {
		return "element '{$files_index}' is null in FILES array in check_file";
	}
	if (!array_key_exists('name', $_FILES[$files_index])) {
		return "'name' element does not exist in FILES['{$files_index}'] in check_file";
	}
	if (!$_FILES[$files_index]['name']) {
		return "'name' element is null in FILES['{$files_index}'] in check_file";
	}
	if (!(strlen($_FILES[$files_index]['name']) > 0)) {
		return "'name' element is empty in FILES['{$files_index}'] in check_file";
	}
	$filename = $_FILES[$files_index]['name'];
	if (!is_uploaded_file($_FILES[$files_index]['tmp_name'])) {
		return "File '{$filename}' could not be uploaded in check_file";
	} 
	$file = $_FILES[$files_index]['tmp_name'];
	if (!file_exists($file) or !is_readable($file)) {
		return "File '{$filename}' does not exist or is unreadable on server in check_file";
	}
	return "";
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

/**
 * Converts a file to an array, replacing old linebreaks with \n and 
 * cludges to remove linebreak characters within fields.
 * 
 * @param string $filename
 * 	The name of the file to convert; should probably be something like 
 * 	$_FILES['description-file']['tmp_name']
 * 
 * @return string|boolean
 * 	If no errors occur, returns an array representation of the file, with 
 * 	each element in the array corresponding to a line in the file.  If any 
 * 	errors occur (file could not be found or if it is not UTF-8), returns 
 * 	FALSE
 */
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
	$file_string = str_replace(">\n", ">", $file_string);
	// And another pair to remove unnecessary quotes around paragraphs
	$file_string = str_replace("\"<p>", "<p>", $file_string);
	$file_string = str_replace("</p>\"", "</p>", $file_string);

	$file_array = explode("\n", $file_string);

	return $file_array;
	
}

/**
 * Returns associative array with Taxonomic name (Name) as the key, and value an 
 * array of descriptions
 * 
 * @param array $desc_mapping
 * 	The mapping of field names in a taxon description to their position in the 
 * 	array representation of the taxon descriptions file
 * 
 * @param array $desc_file_array
 * 	Array representation of taxon description file (each line represented by a 
 * 	single element).
 * 
 * @return array
 * 	An array of taxon description arrays, indexed by taxonomic name
 */
function desc_to_array($desc_mapping, $desc_file_array) {
	if (is_array($desc_mapping) && count($desc_mapping) > 0
			&& is_array($desc_file_array)) {
		$desc_array = array();
		
		// loop over each line in the file (one line = one taxon description)
		for ($line = 1; $line < count($desc_file_array); $line++) {
			$one_line = $desc_file_array[$line];
			$one_line = explode("\t", $one_line);
			$one_desc = array();
			foreach ($desc_mapping as $column_name => $column_position) {
				if (array_key_exists($column_position, $one_line)){
					$value_in_file = $one_line[$column_position];
					if (strlen(trim($value_in_file)) > 0) {
						$one_desc[$column_name] = $value_in_file;
					} else {
						$one_desc[$column_name] = "";
					}
				}
			}
			if (count($one_desc) > 0) {
				$key_field = $desc_mapping['Taxonomic name (Name)'];
				$key = $one_line[$key_field];
				$desc_array[$key] = $one_desc;
			}
		}
		return $desc_array;
	}
	return FALSE;
}

/**
 * Adds description information, including any media details to the passes $add_to 
 * array and returns this array.
 * 
 * @param array $description
 * 	An array representation of a taxon description
 * 
 * @param array $add_to
 * 	An array of taxon information 
 * 
 * @param MediaDetails $media_details
 * 	Storage class for information about media file paths
 * 
 * @return array
 * 	The $add_to array, with any additional information about passed taxon description
 */
function add_description($description, $add_to, MediaDetails $media_details) {
	// TODO: instead of passing $add_do & returning it, could pass as reference and return 
	// nothing (or false if problems are encountered).
	$local_media_path = $media_details->local_media_path;
	$tmp_path = $media_details->additional_path_prefix;
	
	if (is_array($description)) {
		foreach ($description as $key => $value) {
			if (strlen(trim($value)) > 0) {
				switch ($key) {
					// fall throughs intended
					case "Diagnostic Description":
					case "Distribution":
					case "Habitat":
					case "Size":
					case "Morphological Description":
					case "General description":
					case "Genetics":
						if (! array_key_exists ( "descriptions", $add_to ) || ! is_array ( $add_to ['descriptions'] )) {
							$add_to ['descriptions'] = array ();
						}
						$value = strip_tags($value, "<a>"); // for now, just strip anything other than anchors
						$new_description = array(
								'type' => $key,
								'text' => $value,
						);
						$add_to['descriptions'][] = $new_description;
						break;
					case "Similar Species (Name)":
						if (! array_key_exists ( "descriptions", $add_to ) || ! is_array ( $add_to ['descriptions'] )) {
							$add_to ['descriptions'] = array ();
						}
						// TODO: links to other taxa would be ideal
						$value = strip_tags($value);
						$value = str_replace("|", ", ", $value);
						$new_description = array(
								'type' => $key,
								'text' => $value,
						);
						$add_to['descriptions'][] = $new_description;
						break; // end case Similar Species (Name)
					// Fall throughs intended
					case "Habitus image":
					case "Habitat image":
					case "Pronotum image":
					case "Elytral microsculpture":
					case "Genitalia left image":
						// Some hard-coding here to just get the first image file...
						// TODO: If we wanted to download MORE than one image file, would need to figure out 
						// where first file ends in the pipe-delimited text and where the second (and third, 
						// fourth...) image begins
						// e.g. 
						// 3019|2|V100779.Body_.jpg|public://V100779.Body_.jpg|...|1315|3016|2|V100776.Body_.Scale_.jpg|public://V100776.Body_.Scale_.jpg|...
						// ^---start of first                          end of first---^ ^---start of second
						$value = explode("|", $value);
						$image_file_name = $value[2];
						// Since we are NOT using $image_file_name to retrieve the file, we can manipulate
						// it here.  Replace space(s) with underscores
						$image_file_name = preg_replace("/\s+/", "_", trim($image_file_name));

						$remote_image_path = $value[3];
						// Don't do this:
						//	$remote_path_exploded = explode("/", $remote_image_path);
						//	$image_file_name = $remote_path_exploded[count($remote_path_exploded) - 1];
						// it won't work with morphbank images (because $value[3] is a url with "/" characters)

						// See if there are any path replacements necessary
						if ($media_details->replacement_paths && is_array($media_details->replacement_paths)) {
							foreach ($media_details->replacement_paths as $one_pair) {
								if (is_array($one_pair) && array_key_exists('search', $one_pair) && array_key_exists('replace', $one_pair)) {
									$search = $one_pair['search'];
									$replace = $one_pair['replace'];
									if (substr_count($remote_image_path, $search) > 0) {
										$remote_image_path = str_replace($search, $replace, $remote_image_path);
									}
								}
							}
						}

						if (! array_key_exists ( 'media', $add_to ) || ! is_array ( $add_to ['media'] )) {
							$add_to ['media'] = array ();
						}
						$new_media = array (
								'type' => "img",
								'alt' => $key 
						);
						$file_retrieved = FALSE;
						$local_file_path = $local_media_path . $image_file_name;
						try {
							$image = new Imagick ( $remote_image_path );
							$image->setimageformat ( "jpg" );
							$image_height = $image->getimageheight ();
							if ($image_height > 500) {
								$image->scaleimage ( 0, 500 );
							}
							$image_width = $image->getimagewidth ();
							if ($image_width > 500) {
								$image->scaleimage ( 500, 0 );
							}
							
							// Let's make sure the extension is .jpg...
							$append_jpg = FALSE;
							if (substr_count ( $local_file_path, "." ) > 0) {
								$path_exploded = explode ( ".", $local_file_path );
								$ext = $path_exploded [count ( $path_exploded ) - 1];
								$jpg_array = array (
										"jpg",
										"jpeg" 
								);
								if (! in_array ( strtolower ( $ext ), $jpg_array )) {
									$append_jpg = TRUE;
								}
							} else {
								$append_jpg = TRUE;
							}
							if ($append_jpg) {
								$local_file_path .= ".jpg";
							}
							$tmp_file_path = $tmp_path . $local_file_path;
							if ($image->writeimage("jpg:" . $tmp_file_path)){
								$new_media['src'] = $local_file_path;
								$file_retrieved = TRUE;
							}
						} catch ( Exception $e ) {
							// Maybe Imagemagick isn't installed, so just grab the file
							// First make sure the url is valid
							if (filter_var($remote_image_path, FILTER_VALIDATE_URL) === FALSE) {
								$new_media['missingsrc'] = $remote_image_path;
							} else {
								$tmp_file_path = $tmp_path . $local_file_path;
								if (copy($remote_image_path, $tmp_file_path)) {
									$new_media['src'] = $local_file_path;
									$file_retrieved = TRUE;
								}
							}
						}
						
						if (!$file_retrieved) { // TODO: Need to deal with these failures on JavaScript side of things...
							$new_media['filename'] = $image_file_name;
						}

						$add_to['media'][] = $new_media;

				} // end switch on $key
			} // end conditional for non-empty $value
		}
		return $add_to;
	} else {
		return $add_to;
	}
}

/**
 * Storage class for media path information.
 */
class MediaDetails {
	/**
	 * The path to which media will be saved locally.
	 * @var string
	 */
	public $local_media_path;
	
	/**
	 * Any additional path to prefix, such as "tmp/"
	 * @var string
	 */
	public $additional_path_prefix;
	
	/**
	 * An array of two-element arrays, each with 'search' and 'replace' keys.
	 * @var array
	 */
	public $replacement_paths;	
	
	/**
	 * Constructor
	 * 
	 * @param string $local_media_path
	 * The path to which media will be saved locally.
	 * 
	 * @param string[optional] $additional_path_prefix
	 * Any additional path to prefix, such as "tmp/"; prefix will be used for saving files
	 * but <em>not</em> for writing path information to json file.
	 * 
	 * @param array[optional] $replacement_paths
	 * An array of two-element arrays, each of which has a 'search' key and a 'replace' key:
	 * <pre>
	 * 	array (
	 * 		array (
	 * 			'search' => "public://",
	 * 			'replace' => "http://bembidion.info/sites/bembidion.info/files/"
	 * 		),
	 * 		array (
	 * 			'search' => "local://",
	 * 			'replace' => "http://another.site.com/files/"
	 * 		),
	 * );
	 * </pre>
	 * Will be used to find/replace path fragements that are not URLs; e.g. Scratchpads will 
	 * refer to files stored on the Scratchpad with the local path information only 
	 * ("public://V100778.Body_.Scale_.jpg"), which is thus inaccessible to us.
	 * 
	 * @return MediaDetails
	 */
	function __construct($local_media_path, $additional_path_prefix = "", $replacement_paths = array()) {
		if (!is_null($local_media_path) && strlen(trim($local_media_path)) > 0) {
			$this->local_media_path = trim($local_media_path);
			if (!is_null($additional_path_prefix) && strlen(trim($additional_path_prefix)) > 0) {
				$this->additional_path_prefix = $additional_path_prefix;
			}
			if (!is_null($replacement_paths) && is_array($replacement_paths)) {
				$arrays_ok = TRUE;
				foreach ($replacement_paths as $one_pair) {
					if (!array_key_exists('search', $one_pair) || !array_key_exists('replace', $one_pair)) {
						$arrays_ok = FALSE;
					}
				}
				if ($arrays_ok) {				
					$this->replacement_paths = $replacement_paths;
				}
			}
		} else {
			return NULL;
		}
	}
}