<?php


function get_empty_array($keys) {
	if (is_array($keys) && count($keys) > 0) {
		$empty_array = array_fill_keys($keys, "");
		return $empty_array;
	} else {
		return FALSE;
	}
}

	// will have field name as key, and position (column #) as value, e.g.
	// 'Term name' => 0
	// 'GUID' => 3
	// 'Parent GUID' => 4
	// 'Rank' => 10
/**
 * Returns a mapping associative array, where the key is the string name of a column in 
 * the passed $header string and the value is the position of that string in $header, 
 * when delimited by tabs.
 * <pre>
 * 	'Term name' => 0
 * 	'GUID' => 3
 * 	'Parent GUID' => 4
 * 	'Rank' => 10
 * </pre>
 * 
 * @param string $type 
 * The type of mapping; only recognizes "taxonomy" and "description"
 * 
 * @param string $header
 * The header row we want to map, where columns are separated $delimiter
 * 
 * @return array on success, FALSE on failure
 */
function get_mapping($type, $header, $delimiter = "\t") {
	$header_array = explode($delimiter, $header);
	
	$file_fields = FALSE;
	if ($type == "taxonomy") {
		$file_fields = get_tax_file_fields();
	} elseif ($type == "description") {
		$file_fields = get_desc_file_fields();
	}
	
	if ($file_fields && count($file_fields) > 0) {
		$mapping = get_empty_array($file_fields);
		for ($column = 0; $column < count($header_array); $column++) {
			$column_name = $header_array[$column];
			if (array_key_exists($column_name, $mapping)) {
				$mapping[$column_name] = $column;
			}
		}
		if (count($mapping) > 0) {
			return $mapping;
		}
	}
	return FALSE;
}

/**
 * Returns an array of the fields we are interested in from Taxon Description
 * 
 * @return array:string
 */
function get_desc_file_fields() {
	return array (
			"Taxonomic name (Name)",
			"Diagnostic Description",
			"Distribution",
			"Habitat",
			"Size",
			"Habitus image",
			"Morphological Description",
			"Habitat image",
			"Simlar Species (Name)",
			"Pronotum image",
			"Elytral microsculpture",
			"Genitalia left image",
			"General description",
			"Genetics" 
	);
}

/**
 * Returns an array of the fields we are interested in from Taxonomy
 * 
 * @return array:string
 */
function get_tax_file_fields() {
	return array (
			"Term name",
			"GUID",
			"Parent GUID",
			"Rank",
	);
}