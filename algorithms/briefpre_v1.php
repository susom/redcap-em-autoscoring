<?php
/**

	BRIEF Preschool
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "BRIEF Preschool.  It assumes the questions are coded as 1-3 for all questions.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$prefix = 'briefpre_';
// Replace as necessary for your particular scoring algorithm
$required_fields = array();
foreach (range(1,63) as $i) {
	array_push($required_fields, $prefix.$i);
}


# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'inhibit',
	'shift',
	'emotionalcontrol',
	'workingmemory',
	'planorganize',
	'isci',
	'fi',
	'emi',
	'gec'
);
$default_result_fields = array();
foreach ($categories as $c) {
	array_push($default_result_fields, $prefix.$c."_raw");		//raw score
}
array_push($default_result_fields, $prefix."inconsistency");
array_push($default_result_fields, $prefix."negativity");
//$this->module->emDebug("DRF: " . $default_result_fields);


### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;


# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

# Override required_fields array with manual field names specified by user (optional)
if (!empty($manual_source_fields)) {
	//$this->module->emDebug("Manual Source Fields: " . $manual_source_fields);
	//$this->module->emDebug("Required Fields: " . $required_fields);
	if (count($manual_source_fields) == count($required_fields)) {
		foreach($manual_source_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$required_fields[$k] = $field;
				//$this->module->emDebug("changing $k to $field");
			}
		}
		$log[] = "Overriding required fields with ". implode(',',$manual_source_fields);
		//$this->module->emDebug("Required Fields After: " . $required_fields);
	} else {
		$msg = count($manual_source_fields) . " manual source fields specified, but the algorithm needs " . count($required_fields) . " fields.";
		$this->module->emError($msg);
		$algorithm_log[] = $msg;
		return false;
	}
}

# Override default result array with manual field names specified by user (optional)
if (!empty($manual_result_fields)) {
	if (count($manual_result_fields) == count($default_result_fields)) {
		foreach($manual_result_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$default_result_fields[$k] = $field;
				//$this->module->emDebug('changing $k to $field');
			}
		}
		$log[] = "Overriding default result field names with ". implode(',',$manual_result_fields);
	} else {
		$msg = count($manual_result_fields) . " manual result fields specified, but the algorithm needs " . count($default_result_fields) . " fields.";
		$this->module->emError($msg);
		$algorithm_log[] = $msg;
		return false;
	}
}

# Test for presense of all required fields and report missing fields
$source_fields = array_keys($src);
$missing_fields = array_diff($required_fields, $source_fields);
if ($missing_fields) {
	$msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "]";
	$algorithm_log[] = $msg;
	$this->module->emError($msg);
	$this->module->emDebug("Missing Fields: " . $missing_fields);
	return false;	//Since this is being called via include, the main script will continue to process other algorithms
}

# Check that all required fields have a value
$null_fields = array();
foreach ($required_fields as $rf) {
	if (empty($src[$rf]) && !is_numeric($src[$rf])) $null_fields[] = $rf;
}
if (!empty($null_fields)) {
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
	return false;  // prevent scoring during partial submissions (section breaks)
}


### IMPLEMENT SCORING ###

# Since this is a subgroup scoring algoritm, divide the fields into the desired groups
$iResults = array();
$normalizedSource = array();	// This is an array to hold the source data converted to a normal scale
foreach ($required_fields as $i => $field_name) {
	$i++;	// Add one to offset index starting at 0
	$normalizedSource[$field_name] = $src[$field_name];
	$iResults[$i] = $normalizedSource[$field_name];
}
$this->module->emDebug("NSRC: " . $normalizedSource);
$this->module->emDebug("iResults: " . $iResults);

// Create groups for scoring
$groups = array(
	'inhibit' => array(3,8,13,18,23,28,33,38,43,48,52,54,56,58,60,62),
	'shift' => array(5,10,15,20,25,30,35,40,45,50),
	'emotionalcontrol' => array(1,6,11,16,21,26,31,36,41,46),
	'workingmemory' => array(2,7,12,17,22,27,32,37,42,47,51,53,55,57,59,61,63),
	'planorganize' => array(4,9,14,19,24,29,34,39,44,49),
	'isci' => array(
		3,8,13,18,23,28,33,38,43,48,52,54,56,58,60,62,
		1,6,11,16,21,26,31,36,41,46),
	'fi' => array(
		5,10,15,20,25,30,35,40,45,50,
		1,6,11,16,21,26,31,36,41,46),
	'emi' => array(
		2,7,12,17,22,27,32,37,42,47,51,53,55,57,59,61,63,
		4,9,14,19,24,29,34,39,44,49),
	'gec' => array(
		3,8,13,18,23,28,33,38,43,48,52,54,56,58,60,62,
		5,10,15,20,25,30,35,40,45,50,
		1,6,11,16,21,26,31,36,41,46,
		2,7,12,17,22,27,32,37,42,47,51,53,55,57,59,61,63,
		4,9,14,19,24,29,34,39,44,49)
);
$this->module->emDebug("GROUPS: " . $groups);

# Next, we go through each group and substitute in the actual source data for each question
# When this is done, we have an array where the key is each group and the elemnts are an array of
# question numbers and results:

$src_groups = array();
foreach($groups as $name => $question_numbers) {
	$src_groups[$name] = array_intersect_key($iResults, array_flip($question_numbers));
}
$this->module->emDebug("SOURCE GROUPS: " . $src_groups);


# Calculate our Totals
$result_values = array();
foreach ($src_groups as $name => $data) {	
	$raw = array_sum($data);
	$result_values[$name.'_raw'] = $raw;
}

// Calculate inconsistency score
$arr_inconsistency = array(
	abs($iResults[1] - $iResults[11]),
	abs($iResults[3] - $iResults[33]),
	abs($iResults[5] - $iResults[45]),
	abs($iResults[10] - $iResults[20]),
	abs($iResults[11] - $iResults[26]),
	abs($iResults[16] - $iResults[21]),
	abs($iResults[18] - $iResults[52]),
	abs($iResults[33] - $iResults[38]),
	abs($iResults[43] - $iResults[52]),
	abs($iResults[48] - $iResults[54])
);
$this->module->emDebug("INCONSISTENCY: " . $arr_inconsistency);
$result_values['inconsistency'] = array_sum($arr_inconsistency);

// Calculate Negativity score
$negativity = 0;
foreach(array(30,44,46,47,53,55,56,57,59,63) as $q) {
	if ($iResults[$q] == 3) $negativity++;
}
$result_values['negativity'] = $negativity;


//$this->module->emDebug("DRF: " . $default_result_fields);
$this->module->emDebug("RV: " . $result_values);


### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $result_values);
$this->module->emDebug( "AR: " . $algorithm_results);

# Append result field for algorithm log if specified via the log_field variable in the config project
# Because we aren't pulling the entire data dictionary, we can't confirm whether or not the field actually exists
if ($job['log_field']) {
	$algorithm_results[$job['log_field']] = implode("\n",$algorithm_log);
	$msg = "Custom log_field {$job['log_field']}";
	$algorithm_log[] = $msg;
	//$algorithm_results = array_merge($algorithm_results, array($job['log_field'] => $algorithm_log));
}

# Append additional data to the results if specified via the 'append_data' field in the config project.
# This data is in the format of fieldname=value, fieldname2=value2, etc... and is useful for setting the result
# form status to complete
if ($job['append_data']) {
	$data_pairs = array_map('trim',explode(',', $job['append_data']));
	foreach ($data_pairs as $pair) {
		list($fname, $fvalue) = array_map('trim',explode('=', $pair));
		$algorithm_results[$fname] = $fvalue;
		$algorithm_log[] = "Appended [$fname] = $fvalue";
	}
}

return true;

?>
