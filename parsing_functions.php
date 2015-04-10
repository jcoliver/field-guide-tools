<?php


// two files a description-file and taxonomy-file
function process_files() {
	include_once 'RequestedFields.php';
	$top_concept_guid = "bembidion.info:taxon-concepts:000007"; // this is the one we want replaced with "none"
	$skip_unidentified = TRUE;
	$tmp_path = "tmp/";
	$file_name = "data/taxa.json"; // Make sure you can write to this file
	$local_media_path = "data/media/"; // Make sure this directory has write permissions

	// Files stored on Scratchpads won't have full URL, but rather "public://Filename.jpg"
	// $public_path is value to use for replacing "public://" in files stored on scratchpads
	$public_path = "http://bembidion.info/sites/bembidion.info/files/"; 

	$dtz = new DateTimeZone("America/Los_Angeles");
	$start_time = new DateTime("NOW", $dtz);
	
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
		// start by just getting a json of taxonomy (id, parent, and name)
		$desc_file = $_FILES['description-file']['tmp_name'];
		$desc_file_array = file_to_array($desc_file);
		
		$tax_file = $_FILES['taxonomy-file']['tmp_name'];
		$tax_file_array = file_to_array($tax_file);
		
		if ($desc_file_array && $tax_file_array) {
			$desc_header = $desc_file_array[0];
			$desc_mapping = get_mapping("description", $desc_header);
			$desc_as_array = desc_to_array($desc_mapping, $desc_file_array);

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
						$is_unidentified = substr_count(strtolower($name), "unidentified") > 0;
						if (!$is_unidentified || ($is_unidentified && !$skip_unidentified)) {
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
									$for_taxons = add_description($description, $for_taxons, $local_media_path, $public_path, $tmp_path);
									//$for_taxons = add_description($description, $for_taxons, $local_media_path, $remote_media_path, $tmp_path);
								}
								
								$taxons_for_json[] = $for_taxons;
							}
						}	
					}
				}
			}
			
			$taxons_json = json_encode($taxons_for_json, JSON_PRETTY_PRINT);
			// TODO: file paths have "\/" instead of "/".  replace so we can find files
			// This is pretty drastic, but too bad
			$taxons_json = str_replace("\\/", "/", $taxons_json);
			
			if ($taxons_json) {
				// Have to be sure we have read & write permissions on system
				$file_path = $tmp_path . $file_name;
				$file_handle = fopen($file_path, "w");
				$write = fwrite($file_handle, $taxons_json);
				if ($write) {
					fclose($file_handle);
					$return_string .= "<p>Data write complete</p>";
				} else {
					$return_string .= "<p>Error writing file</p>";
				}
				// $saved = file_put_contents("tmp/data.json", $taxons_json);
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

// associative array with Taxonomic name (Name) as the key, and value an array of descriptions
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
				$value_in_file = $one_line[$column_position];
				if (strlen(trim($value_in_file)) > 0) {
					$one_desc[$column_name] = $value_in_file;
				} else {
					$one_desc[$column_name] = "";
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

function add_description($description, $add_to, $local_media_path, $public_path, $tmp_path) {
//function add_description($description, $add_to, $local_media_path, $remote_media_path, $tmp_path) {
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
						// TODO: some hard-coding here to just get the first image file...
						$value = explode("|", $value);
						$image_file_name = $value[2];
						$remote_image_path = $value[3];
						// Don't do this:
						//	$remote_path_exploded = explode("/", $remote_image_path);
						//	$image_file_name = $remote_path_exploded[count($remote_path_exploded) - 1];
						// it won't work with morphbank images (because $value[3] is a url with "/" characters)						
						
						if (substr_count($remote_image_path, "public://") > 0) {
							$remote_image_path = str_replace("public://", $public_path, $remote_image_path);
						}

						// TODO: Do check to make sure Imagick available
						// try/catch
						$image = new Imagick($remote_image_path);
						$image->setimageformat("jpg");
						$image_height = $image->getimageheight();
						if ($image_height > 500) {
							$image->scaleimage(0, 500);
						}
						$image_width = $image->getimagewidth();
						if ($image_width > 500) {
							$image->scaleimage(500, 0);
						}
						
						$local_file_path = $local_media_path . $image_file_name;
						if (!array_key_exists('media', $add_to) || !is_array($add_to['media'])) {
							$add_to['media'] = array();
						}
						$new_media = array(
								'type' => "img",
								'alt' => $key,
						);
						// TODO: it wouldn't hurt to replace spaces with underscores...
						$tmp_file_path = $tmp_path . $local_file_path;
						// Let's make sure the extension is .jpg...
						$path_exploded = explode(".", $tmp_file_path);
						$ext = $path_exploded[count($path_exploded) - 1];
						$jpg_array = array("jpg", "jpeg");
						if (!in_array(strtolower($ext), $jpg_array)) {
							$tmp_file_path .= ".jpg";
						}
						
						if ($image->writeimage("jpg:" . $tmp_file_path)){
							$new_media['src'] = $local_file_path;
						} else {// TODO: Need to deal with these failures on JavaScript side of things...
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