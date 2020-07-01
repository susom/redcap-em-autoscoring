<?php
/**
	Pediatriac Quality of Life Inventory (PedsQL) based on choices of 0,1,2,3,4
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

        - This one scoring script encompassing many different age groups.  Here is a summary:
        -	Toddlers - have 21 questions with 5 answer options
	-	Young Child ages 5-7 yrs old - 23 questions with 5 options
	-	Child ages 8-12 yrs old - 23 questions with 5 options
	-	Teen ages 13-18 yrs old - 23 questions with 5 options

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Pediatric Quality of Life Inventory (PedsQL). This survey has 23 questions for ages: 5-7Y answers=0-4, 8-12Y answers=0-4, 13-18Y answers=0-4.  Also, Toddlers with 21 questions. There are 11 result fields";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$required_fields = array();
$algorithm_prefix = "pedsql";

// This is just a short-hand way to create a list of default required fields (abc_1, abc_2, ...).  Replace as necessary for your particular scoring algorithm
foreach (range(1,23) as $i) {
	array_push($required_fields, $algorithm_prefix."_$i");
}

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$default_result_fields = array(
	'pedsql_phys', 'pedsql_phys_scaled',
	'pedsql_emot', 'pedsql_emot_scaled',
	'pedsql_soci', 'pedsql_soci_scaled',
	'pedsql_school', 'pedsql_school_scaled',
	'pedsql_psychosocial_scaled',
	'pedsql_total_scaled'
);

### VALIDATION ###

# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;

# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s") . " from $instrument";

# Override required_fields array with manual field names specified by user (optional)
if (!empty($manual_source_fields)) {
	//$this->module->emDebug("Manual Source Fields: " . $manual_source_fields);
	//$this->module->emDebug("Required Fields: " . $required_fields);
	if (count($required_fields) - count($manual_source_fields) == 2) {
		// The parent toddler version only requires 21 questions - two are left out of the school group
		$toddler = true;
		$algorithm_log[] = "Toddler scoring in effect (only 3 questions in school block)";
		// Take off the last two required fields
		array_pop($required_fields);
		array_pop($required_fields);
	} else {
		$toddler = false;
	}
	
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

// The toddler version for parents has only 21 questions instead of 23 - rather than build a new scoring algorithm for this, I'm adding this kludge
/*
if (count($missing_fields) == 2) {
	foreach ($missing_fields as $field) {
		$src[$field] = 0;
	}
	unset($missing_fields);
	$missing_fields = array();
	//$this->module->emDebug("MF: " . $missing_fields);
}
*/

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
	// Skip this warning if in toddler mode
	if (!$toddler) {
		$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
		return false;
	}
}


### IMPLEMENT SCORING ###

$iResults = array();
$normalizedSource = array();	// This is an array to hold the source data converted to a 1 (checked) , 0 (not checked) scale taking into account reverse questions
foreach ($required_fields as $i => $field_name) {
	$val = $src[$field_name];
	//$this->module->emDebug("Question ". $i+1 . " $field_name = $val");
	$normalizedSource[$field_name] = $val;
	$iResults[$i+1] = $val;
}
//$this->module->emDebug("SRC: " . $src);
//$this->module->emDebug("NSRC: " . $normalizedSource);
//$this->module->emDebug("iResults: " . $iResults);

# Since this is a subgroup scoring algoritm, create the various groups
$groups = array(
	'pedsql_phys'	=> range(1,8),
	'pedsql_emot' => range(9,13),
	'pedsql_soci' => range(14,18),
	'pedsql_school' => $toddler ? range(19,21) : range(19,23),
	'pedsql_psychosocial' => $toddler ? range(9,21) : range(9,23),
	'pedsql_total' => $toddler ? range(1,21) : range(1,23)
);


# Next, we go through each group and substitute in the actual source data for each question (by flipping the number/value array)
$src_groups = array();
foreach($groups as $name => $question_numbers) {
	$src_groups[$name] = array_intersect_key($iResults, array_flip($question_numbers));
}
$this->module->emDebug("SOURCE GROUPS: " . $src_groups);


# Calculate our Totals - for averaging we need to ignore unanswered questions
$group_values = array();
foreach($src_groups as $name => $questions) {
	$sum = array_sum($questions);
	$count = count(array_filter($questions, function($x) { return is_numeric($x);}));
	if (!$count) $algorithm_log[] = "No questions were answered for the $name block - no score reported.";
	//$this->module->emDebug("Name:$name  / Sum:$sum / Count:$count", "$name");
	$group_values[$name] 			= $count ? $sum : "";
	$group_values[$name."_scale"] 	= $count ? abs(round((4 - $sum/$count) * 25,0)) : "";
}

// Remove raw summary totals for last two
unset ($group_values['pedsql_psychosocial']);
unset ($group_values['pedsql_total']);
$this->module->emDebug("GV: " . $group_values);

//exit;


### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $group_values);
//$this->module->emDebug("AR: " . $algorithm_results);

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