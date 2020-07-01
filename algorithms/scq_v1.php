<?php
/**
	Social Communication Questionnaire (SCQ)
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Social Communication Questionnaire (SCQ) from Western Psychological Services.  40 questions where 1=Yes and 0/2=No";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$required_fields = array();
$algorithm_prefix = "scq";

// This is just a short-hand way to create a list of 58 fields (abc_1, abc_2, ...).  Replace as necessary for your particular scoring algorithm
foreach (range(1,40) as $i) {
	array_push($required_fields, $algorithm_prefix."_$i");
}

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$default_result_fields = array(
	'scq_phrases',	// 0 or 1
	'scq_rsi',				//reciprocal social interaction domain, items: 9, 10, 19, 26, 27, 28, 29, 30, 31, 32, 33, 36, 37, 39, 40
	'scq_com',				//communication domain, items: 2, 3, 4, 5, 6, 20, 21, 22, 23, 24, 25, 34, 35
	'scq_rrsp',				//restricted, repetitive, and stereotyped patterns, items: 7, 8, 11, 12, 13, 14, 15, 16
	'scq_total'		//Total score (2-40 or 8-40 depending on answer to part 1)
);

### VALIDATION ###

# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true)  return true;

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
				//$this->module->emDebug("changing $k to $field");
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

# If the answer to the first question is No, answers for questions 2, 3, 4, 5, 6 and 7 won't be available - but that's okay.
if (!empty($null_fields)) {
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
}


### IMPLEMENT SCORING ###

# Some questions count '1' for yes, others count '1' for no.  
# By default, REDCap will score '1' for Yes, so by inverting value for some questions 
# I can simply use the array_sum to get scores
$reversedQuestions = array_merge( array(2,9,19), range(20,40) );
$normalizedSource = array();	// This is an array to hold the source data converted to a 1 (checked) , 0 (not checked) scale taking into account reverse questions
foreach ($required_fields as $i => $field_name) {
	$val = $src[$field_name];
	//$this->module->emDebug("Question ". $i+1 . " $field_name = $val");
	//i is a 0-based index whereas questions start at 1, so we use i+1
	if (in_array($i+1, $reversedQuestions,true)) {
		if ($val == 1 || empty($val)) {
			$normalizedSource[$field_name] = 0;
		} else {
			$normalizedSource[$field_name] = 1;			
		}
	} else {
		$normalizedSource[$field_name] = ($val == 1 ? 1 : 0);
	}
}
//$this->module->emDebug("SRC: " . $src);
$this->module->emDebug("NSRC: " . $normalizedSource);

// Numerical Index of questions and results
$iResults = array();
for ($i=1;$i <= count($required_fields);$i++) {
	$iResults[$i] = $normalizedSource[$required_fields[$i-1]];
}
$this->module->emDebug("iResults: " . $iResults);

# Since this is a subgroup scoring algoritm, create the various groups
$groups = array(
	'scq_1_yes' => range(2,40),	// Used for total if 1 is yes
	'scq_1_no'	=> range(8,40), // Used for total if 1 is no
	'scq_rsi'	=> array(9,10,19,26,27,28,29,30,31,32,33,36,37,39,40),
	'scq_com'	=> array(2,3,4,5,6,20,21,22,23,24,25,34,35),
	'scq_rrsp'	=> array(7,8,11,12,13,14,15,16)
);

# Next, we go through each group and substitute in the actual source data for each question
$src_groups = array();
foreach($groups as $name => $question_numbers) {
	$src_groups[$name] = array_intersect_key($iResults, array_flip($question_numbers));
}
$this->module->emDebug("SOURCE GROUPS: " . $src_groups);

# Calculate our Totals
//	'scq_phrases',
//	'scq_rsi',
//	'scq_com',
//	'scq_rrsp',
//	'scq_total'
$result_values = array(
	$iResults[1],	// Phrases
	($iResults[1] == 1) ? array_sum($src_groups['scq_rsi']) : '',
	($iResults[1] == 1) ? array_sum($src_groups['scq_com']) : '',
	($iResults[1] == 1) ? array_sum($src_groups['scq_rrsp']) : '',
	($iResults[1] == 1) ? array_sum($src_groups['scq_1_yes']) : array_sum($src_groups['scq_1_no'])
);

//$this->module->emDebug("DRF: " . $default_result_fields);
$this->module->emDebug("RV: " . $result_values);


### DEFINE RESULTS ###
### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $result_values);

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
