<?php
/**

	What I Think and Feel (RCMAS)
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There are 49 input questions and age that are input.
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results
	- After the raw scores are calculated, the t-score is determined via a lookup table for 3
	-- age groups: 6-8 yr olds, 9-14 yr olds and 15-19 yr olds.

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "RCMAS v2  Cecil R. Reynolds, PhD.  Bert O. Richmond, Ed. D.  This algorithm assumes 1=Yes and any other value is No for all 49 questions.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$prefix = 'rcmas_';
// Replace as necessary for your particular scoring algorithm
$required_fields = array();
foreach (range(1,49) as $i) {
	array_push($required_fields, $prefix.$i);
}
array_push($required_fields, 'age');

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'inc',	// inconsistency index
	'def_raw',	//
	'phy_raw',	//
	'wor_raw',	//
	'soc_raw',	//
	'tot_raw',	// sum of phy/wor/soc
	'def_tscore',	// these are age based t-scores.
	'phy_tscore',
	'wor_tscore',
	'soc_tscore',
	'tot_tscore'
);

$default_result_fields = array();
foreach ($categories as $c) {
	array_push($default_result_fields, $prefix.$c);
}
array_push($default_result_fields, 'age_category');
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
$age_field = 49;
foreach ($required_fields as $i => $field_name) {
	$val = $src[$field_name];
	if ($field_name == $required_fields[$age_field]) {
		$age = floor($val);
	} elseif (in_array($i+1, $reversedQuestions,true)) {
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
$this->module->emDebug("These are the raw values: " . implode(',',$result_values));

$age_category = (($age >= 6 and $age <= 8) ? 1 : (($age >=9 and $age <= 14) ? 2 : (($age >= 15 and $age <= 19) ? 3 : NULL)));
#$this->module->emDebug("this is the age: $age and this is the age category: $age_category");

# Look up the t values based on the raw scores
# The values are: 1 = 6-8 yr old, 2 = 9-14 yr old and 3 = 15-19 yr old
$tvalue_arrays = array(
	1 => array(
		'def' => array(0 => '<30', 1 => 34, 2 => 38, 3 => 42, 4 => 45,
			       5 => 49, 6 => 52, 7 => 57, 8 => 62, 9 => 71),
		'phy' => array(0 => '<30', 1 => 35, 2 => 38, 3 => 41, 4 => 44, 5 => 48, 6 => 51,
                               7 => 54, 8 => 58, 9 => 61, 10 => 65, 11 => 68, 12 => 73),
		'wor' => array(0 => '<30', 1 => 34, 2 => 37, 3 => 39, 4 => 41, 5 => 44, 6 => 47,
                               7 => 49, 8 => 52, 9 => 54, 10 => 57, 11 => 59, 12 => 61, 13 => 63,
                               14 => 65, 15 => 68, 16 => 73),
		'soc' => array(0 => 32, 1 => 38, 2 => 41, 3 => 45, 4 => 48, 5 => 52, 6 => 54,
                               7 => 57, 8 => 60, 9 => 63, 10 => 66, 11 => 68, 12 => 73),
		'tot' => array(0 => '<30', 1 => 30, 2 => 32, 3 => 33, 4 => 34, 5 => 35, 6 => 36,
                               7 => 37, 8 => 38, 9 => 39, 10 => 40, 11 => 42, 12 => 43, 13 => 44,
                               14 => 45, 15 => 47, 16 => 48, 17 => 49, 18 => 51, 19 => 52,
                               20 => 53, 21 => 55, 22 => 56, 23 => 57, 24 => 58, 25 => 59,
                               26 => 60, 27 => 61, 28 => 61, 29 => 62, 30 => 63, 31 => 64,
                               32 => 65, 33 => 66, 34 => 67, 35 => 68, 36 => 69, 37 => 70,
                               38 => 71, 39 => 73, 40 => 75)
		),
	2 => array(
		'def' => array(0 => 35, 1 => 42, 2 => 46, 3 => 49, 4 => 52,
               		       5 => 56, 6 => 60, 7 => 64, 8 => 69, 9 => 76),
        	'phy' => array(0 => '<30', 1 => 36, 2 => 40, 3 => 43, 4 => 46,
                       		5 => 50, 6 => 54, 7 => 57, 8 => 61, 9 => 64,
                      		10 => 68, 11 => 72, 12 => 76),
		'wor' => array(0 => 32, 1 => 38, 2 => 40, 3 => 43, 4 => 45,
                       		5 => 47, 6 => 50, 7 => 52, 8 => 54, 9 => 57,
		       		10 => 59, 11 => 61, 12 => 63, 13 => 65, 14 => 68,
		       		15 => 72, 16 => 78),
		'soc' => array(0 => 34, 1 => 40, 2 => 44, 3 => 47, 4 => 50,
		       		5 => 53, 6 => 56, 7 => 59, 8 => 62, 9 => 65,
		       		10 => 69, 11 => 72, 12 => 78),
		'tot' => array(0 => '<30', 1 => 30, 2 => 33, 3 => 35, 4 => 37,
		       		5 => 38, 6 => 40, 7 => 41, 8 => 42, 9 => 43,
		       		10 => 44, 11 => 45, 12 => 46, 13 => 47, 14 => 48,
		       		15 => 49, 16 => 50, 17 => 51, 18 => 53, 19 => 54,
		       		20 => 56, 21 => 57, 22 => 59, 23 => 60, 24 => 61,
		       		25 => 63, 26 => 63, 27 => 64, 28 => 64, 29 => 65,
		       		30 => 66, 31 => 68, 32 => 69, 33 => 70, 34 => 71,
		       		35 => 72, 36 => 73, 37 => 75, 38 => 78, 39 => '>80',
				40 => '>80')
		),
	3 => array(
		'def' => array(0 => 36, 1 => 43, 2 => 48, 3 => 52, 4 => 56, 5 => 60,
				6 => 65, 7 => 69, 8 => 74, 9 => '>80'),
		'phy' => array(0 => 31, 1 => 37, 2 => 41, 3 => 45, 4 => 48, 5 => 52,
				6 => 56, 7 => 59, 8 => 63, 9 => 66, 10 => 69,
				11 => 72, 12 => 77),
		'wor' => array(0 => 34, 1 => 39, 2 => 43, 3 => 46, 4 => 48, 5 => 50,
				6 => 52, 7 => 54, 8 => 56, 9 => 58, 10 => 61, 11 => 63,
				12 => 66, 13 => 68, 14 => 70, 15 => 74, 16 => '>80'),
		'soc' => array(0 => 35, 1 => 41, 2 => 45, 3 => 49, 4 => 52, 5 => 55,
				6 => 58, 7 => 61, 8 => 64, 9 => 66, 10 => 68,
				11 => 73, 12 => '>80'),
		'tot' => array(0 => '<30', 1 => 32, 2 => 34, 3 => 37, 4 => 38, 5 => 40,
				6 => 41, 7 => 43, 8 => 44, 9 => 45, 10 => 47, 11 => 48,
				12 => 49, 13 => 50, 14 => 51, 15 => 52, 16 => 53,
				17 => 54, 18 => 56, 19 => 57, 20 => 58, 21 => 59,
				22 => 60, 23 => 61, 24 => 62, 25 => 63, 26 => 64,
				27 => 65, 28 => 66, 29 => 67, 30 => 68, 31 => 69,
				32 => 71, 33 => 72, 34 => 74, 35 => 75, 36 => 77,
				37 => 78, 38 => '>80', 39 => '>80', 40 => '>80')
		)
	);

function lookup_score($tvalue_matrix, $raw_score) {

	if (isset($tvalue_matrix[$raw_score])) {
		$result = $tvalue_matrix[$raw_score];
   	} else {
		$result = NULL;
   	}
	return $result;	
}

# Use the age appropriate tscore matrices
function age_based_tscores($tscore_arr, $result_values ) {
	$tvals = array();
	foreach ($tscore_arr as $i => $tscore_name) {
		$tvals[$i] = lookup_score($tscore_arr[$i], $result_values[$i.'_raw']);
	}
	return $tvals;
}

$tval_values = age_based_tscores($tvalue_arrays[$age_category], $result_values);
//$this->module->emDebug("age = $age, age category = $age_category, tvalues = " . implode(',',$tval_values));
//$this->module->emDebug("tvalue results: " . implode(',', $tval_values));
$age_cat = array('age_category' => $age_category);

### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, array_merge($result_values, $tval_values,$age_cat));
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
