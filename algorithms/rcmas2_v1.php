<?php
/**

	What I Think and Feel (RCMAS)
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "RCMAS.  Cecil R. Reynolds, PhD.  Bert O. Richmond, Ed. D.  This algorithm assumes 1=Yes and any other value is No for all 49 questions.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$prefix = 'rcmas_';
// Replace as necessary for your particular scoring algorithm
$required_fields = array();
foreach (range(1,49) as $i) {
	array_push($required_fields, $prefix.$i);
}

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'inc',	// inconsistency index
	'def_raw',	//
	'phy_raw',	//
	'wor_raw',	//
	'soc_raw',	//
	'tot_raw'	// sum of phy/wor/soc
);
$default_result_fields = array();
foreach ($categories as $c) {
	array_push($default_result_fields, $prefix.$c);
}
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
	return false;
}


### IMPLEMENT SCORING ###

# Since this is a subgroup scoring algoritm, divide the fields into the desired groups
$reversedQuestions = array(40,44,48);
$normalizedSource = array();	// This is an array to hold the source data converted to a 1 (checked) , 0 (not checked) scale taking into account reverse questions
foreach ($required_fields as $i => $field_name) {
	$val = $src[$field_name];
	if (in_array($i+1, $reversedQuestions,true)) {
		$normalizedSource[$field_name] = ($val == 1 ? 0 : 1);
		//$this->module->emDebug("Question ".$i+1." should be reversed");
	} else {
		$normalizedSource[$field_name] = ($val == 1 ? 1 : 0);
	}
}
//$this->module->emDebug("SRC: " . $src);
//$this->module->emDebug("NSRC: " . $normalizedSource);



// Create groups for scoring
$groups = array(
	'def' => array(14,19,24,29,33,38,40,44,48),
	'phy' => array(1,5,7,11,15,20,25,31,34,39,43,46),
	'wor' => array(2,3,6,8,12,16,17,18,21,26,30,32,35,42,45,49),
	'soc' => array(4,9,10,13,22,23,27,28,36,37,41,47),
);
$groups['tot'] = array_values(array_diff(range(1,49),$groups['def']));	// Include everything but def
$this->module->emDebug("GROUPS: " . $groups);

// Numerical Index of questions and results
$iResults = array();
for ($i=1;$i<=49;$i++) {
	$iResults[$i] = $normalizedSource[$required_fields[$i-1]];
}
//$this->module->emDebug("iResults: " . $iResults);


# Next, we go through each group and substitute in the actual source data for each question
$src_groups = array();
foreach($groups as $name => $question_numbers) {
	$src_groups[$name] = array_intersect_key($iResults, array_flip($question_numbers));
}
$this->module->emDebug("SOURCE GROUPS: " . $src_groups);

# Calculate INC Score
$inc = ($iResults[2] == $iResults[8] ? 0 : 1) + ($iResults[3] == $iResults[35] ? 0 : 1) + ($iResults[4] == $iResults[10] ? 0 : 1) + ($iResults[6] == $iResults[49] ? 0 : 1) + ($iResults[7] == $iResults[39] ? 0 : 1) + ($iResults[19] == $iResults[33] ? 0 : 1) + ($iResults[23] == $iResults[37] ? 0 : 1) + ($iResults[24] == $iResults[29] ? 0 : 1) + ($iResults[38] == $iResults[48] ? 0 : 1);

# Calculate our Totals
$result_values = array($inc);
foreach ($src_groups as $name => $data) {	
	$raw = array_sum($data);
	$result_values[$name.'_raw'] = $raw;
}

//$this->module->emDebug("DRF: " . $default_result_fields);
//$this->module->emDebug("RV: " . $result_values);


### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $result_values);
$this->module->emDebug("AR: " . $algorithm_results);

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
