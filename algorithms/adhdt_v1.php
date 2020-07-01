<?php
/**

	Attention-Defecit / Hyperactivity Disorder Test
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "The ADHDT test requires both age and sex be included on the actual form with the assessment.  These should be calculcated fields with sex of 1 = male, sex of 2 = female.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$prefix = 'adhdt_q';
$required_fields = array('adhdt_age', 'adhdt_sex');
// Replace as necessary for your particular scoring algorithm
foreach (range(1,36) as $i) {
	array_push($required_fields, $prefix.$i);
}

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'adhdt_hyper',		//hyperactivity
	'adhdt_impulse',	//impulse
	'adhdt_inattent'	//inattention
);
$default_result_fields = array();
foreach ($categories as $c) {
	array_push($default_result_fields, $c."_raw");		//raw score
	array_push($default_result_fields, $c."_tscore");	//t-score
	array_push($default_result_fields, $c."_percent");	//percentile score
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
	$this->module->emDebug("Manual Source Fields: " . $manual_source_fields);
	$this->module->emDebug("Required Fields: " . $required_fields);
	if (count($manual_source_fields) == count($required_fields)) {
		foreach($manual_source_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$required_fields[$k] = $field;
				$this->module->emDebug("changing $k to $field");
			}
		}
		$log[] = "Overriding required fields with ". implode(',',$manual_source_fields);
		$this->module->emDebug("Required Fields After: " . $required_fields);
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

# Our required fields array contains age, sex, then 36 questions for a total of 38 fields.
$age = $src[array_shift($required_fields)];	//'ts_adhdt_age'];
$sex = $src[array_shift($required_fields)];	//'ts_adhdt_sex'];

# Since this is a subgroup scoring algoritm, divide the fields into the desired groups
$groups = array(
	'hyper' => array_slice($required_fields,0,13),		//range(1,13),	//hyperactivity
	'impulse' => array_slice($required_fields,13,10),	//range(14,23),	//impulse
	'inattent' => array_slice($required_fields,23,13)	//range(24,36)	//inattention
);
//$this->module->emDebug("GROUPS: " . $groups);

# Next, we go through each group and substitute in the actual source data for each question
# When this is done, we have an array where the key is each group and the elemnts are an array of
# question numbers and results:
// [rbs_sub3] => Array ([rbs_15] => 3,[rbs_16] => 3,[rbs_17] => 3, ...),
// [rbs_sub4] => Array (...)
$src_groups = array();
foreach($groups as $name => $questions) {
	$src_groups[$name] = array_intersect_key($src, array_flip($questions));
}
//$this->module->emDebug("SOURCE GROUPS: " . $src_groups);


# Calculate our Totals
$result_values = array();
foreach ($src_groups as $name => $data) {	
	$raw = array_sum($data);
	list($std, $pct, $convmsg) = convert($name, $raw, $age, $sex);
	if ($convmsg) $algorithm_log[] = $convmsg;	
	$result_values['adhdt_'.$name.'_rs'] = $raw;
	$result_values['adhdt_'.$name.'_ss'] = $std;
	$result_values['adhdt_'.$name.'_percent'] = $pct;
}
$this->module->emDebug("DRF: " . $default_result_fields);
$this->module->emDebug("RV: " . $result_values);


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

// Inputs are category ('hyper','impulse','inattent') raw score, age, and sex (1=male / other is female)
// Returns array of standard score, percentile, and error message
function convert($category,$raw,$age,$sex) {
	$age = floor($age);	// round down age
	if ($age < 3 || $age > 23) {
		return array(false, '', "Age of $age is out of range (3-23)");
	}

	$hyper_male_3to7 = array(
		0 =>	3,
		1 =>	4,
		2 =>	5,
		3 =>	5,
		4 =>	6,
		5 =>	6,
		6 =>	6,
		7 =>	6,
		8 =>	7,
		9 =>	7,
		10 =>	7,
		11 =>	8,
		12 =>	8,
		13 =>	8,
		14 =>	9,
		15 =>	9,
		16 =>	9,
		17 =>	10,
		18 =>	10,
		19 =>	10,
		20 =>	10,
		21 =>	11,
		22 =>	11,
		23 =>	12,
		24 =>	13,
		25 =>	14,
		26 =>	15
	);
	$hyper_male_8to23 = array(
		0 =>	4,
		1 =>	5,
		2 =>	6,
		3 =>	6,
		4 =>	7,
		5 =>	7,
		6 =>	7,
		7 =>	7,
		8 =>	8,
		9 =>	8,
		10 =>	8,
		11 =>	9,
		12 =>	9,
		13 =>	9,
		14 =>	10,
		15 =>	10,
		16 =>	10,
		17 =>	11,
		18 =>	11,
		19 =>	11,
		20 =>	11,
		21 =>	12,
		22 =>	12,
		23 =>	13,
		24 =>	14,
		25 =>	15,
		26 =>	16
	);
	$impulse_male = array(
		0 =>	4,
		1 =>	5,
		2 =>	6,
		3 =>	6,
		4 =>	6,
		5 =>	7,
		6 =>	7,
		7 =>	7,
		8 =>	8,
		9 =>	8,
		10 =>	9,
		11 =>	9,
		12 =>	10,
		13 =>	10,
		14 =>	11,
		15 =>	11,
		16 =>	12,
		17 =>	12,
		18 =>	13,
		19 =>	14,
		20 =>	15
	);
	$inattent_male = array(
		0 =>	2,
		1 =>	3,
		2 =>	3,
		3 =>	4,
		4 =>	4,
		5 =>	4,
		6 =>	5,
		7 =>	5,
		8 =>	6,
		9 =>	6,
		10 =>	6,
		11 =>	7,
		12 =>	7,
		13 =>	8,
		14 =>	8,
		15 =>	8,
		16 =>	9,
		17 =>	9,
		18 =>	9,
		19 =>	10,
		20 =>	10,
		21 =>	10,
		22 =>	11,
		23 =>	12,
		24 =>	12,
		25 =>	13,
		26 =>	14
	);
	$standard_percentile = array(
		2 =>	'<1',
		3 =>	1,
		4 =>	2,
		5 =>	5,
		6 =>	9,
		7 =>	16,
		8 =>	25,
		9 =>	37,
		10 =>	50,
		11 =>	63,
		12 =>	75,
		13 =>	84,
		14 =>	91,
		15 =>	95,
		16 =>	98,
		17 =>	99,
		18 =>	'>99'
	);
	$hyper_female_3to7 = array(
		0 =>	3,
		1 =>	4,
		2 =>	5,
		3 =>	5,
		4 =>	5,
		5 =>	6,
		6 =>	6,
		7 =>	6,
		8 =>	7,
		9 =>	7,
		10 =>	8,
		11 =>	8,
		12 =>	8,
		13 =>	9,
		14 =>	9,
		15 =>	9,
		16 =>	10,
		17 =>	10,
		18 =>	10,
		19 =>	11,
		20 =>	11,
		21 =>	12,
		22 =>	12,
		23 =>	13,
		24 =>	13,
		25 =>	14,
		26 =>	15
	);
	$hyper_female_8to23 = array(
		0 =>	4,
		1 =>	5,
		2 =>	6,
		3 =>	6,
		4 =>	6,
		5 =>	7,
		6 =>	7,
		7 =>	7,
		8 =>	8,
		9 =>	8,
		10 =>	9,
		11 =>	9,
		12 =>	9,
		13 =>	10,
		14 =>	10,
		15 =>	10,
		16 =>	11,
		17 =>	11,
		18 =>	11,
		19 =>	12,
		20 =>	12,
		21 =>	13,
		22 =>	13,
		23 =>	14,
		24 =>	14,
		25 =>	15,
		26 =>	16
	);
	$impulse_female = array(
		0 =>	4,
		1 =>	5,
		2 =>	6,
		3 =>	6,
		4 =>	6,
		5 =>	7,
		6 =>	7,
		7 =>	8,
		8 =>	8,
		9 =>	9,
		10 =>	9,
		11 =>	10,
		12 =>	10,
		13 =>	11,
		14 =>	11,
		15 =>	11,
		16 =>	12,
		17 =>	12,
		18 =>	13,
		19 =>	14,
		20 =>	15
	);
	$inattent_female = array(
		0 =>	2,
		1 =>	3,
		2 =>	3,
		3 =>	4,
		4 =>	4,
		5 =>	5,
		6 =>	5,
		7 =>	6,
		8 =>	6,
		9 =>	6,
		10 =>	7,
		11 =>	7,
		12 =>	8,
		13 =>	8,
		14 =>	9,
		15 =>	9,
		16 =>	9,
		17 =>	10,
		18 =>	10,
		19 =>	10,
		20 =>	11,
		21 =>	11,
		22 =>	12,
		23 =>	12,
		24 =>	13,
		25 =>	14,
		26 =>	15
	);

	$arr = array();
	switch ($category) {
		case "hyper":
			if (in_array($age, range(3,7))) {
				$arr = ($sex == 1 ? $hyper_male_3to7 : $hyper_female_3to7);
			} else {
				$arr = ($sex == 1 ? $hyper_male_8to23 : $hyper_female_8to23);			
			}
			break;
		case "impulse":
			$arr = ($sex == 1 ? $impulse_male : $impulse_female);					
			break;
		case "inattent":
			$arr = ($sex == 1 ? $inattent_male : $inattent_female);					
			break;
		default:
			return array (false, '', "Invalid category: $category");
			break;
	}

	if (isset($arr[$raw])) {
		$val = $arr[$raw];
		$percent = $standard_percentile[$val];
		return array ($val, $percent, '');
	} else {
		return array (false, '', "Unable to find $raw in lookup table");
	}	
}

return true;

?>
