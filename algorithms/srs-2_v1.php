<?php
/**

	SRS-2

	A REDCap AutoScoring Algorithm File

	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results
	- 10/17/2016 - Adding in lookup tables for tvalues.  These tables are based on gender.  A new input called srs_sex was created so the correct lookup tables can be retrieved.

**/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Social Responsiveness Scale-2 School-Age.  This algorithm calculates the RAW scores.  It assumes the questions are coded as 1-4 for all questions.  The algorithm handles reversing the scoring on certain questions. Tvalues are looked up based on the raw scores.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$prefix = 'srs_';
// Replace as necessary for your particular scoring algorithm
$default_required_fields = array();
foreach (range(1,65) as $i) {
	array_push($default_required_fields, $prefix.$i);
}
array_push($default_required_fields, "srs_sex");
$required_fields = $default_required_fields;

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'awr',	//
	'cog',	//
	'com',	//
	'mot',	//
	'rrb',	//
	'sci',	// sum of awr, cog, com, mot
	'total'
);
$raw_results = array();
$tvalue_results = array();
foreach ($categories as $c) {
	array_push($raw_results, $prefix.$c."_raw");		//raw score
	array_push($tvalue_results, $prefix.$c."_tval");                // lookup tvalue
}
$default_result_fields = array_merge($raw_results, $tvalue_results);
$this->module->emDebug("DRF: " . json_encode($default_result_fields));


### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;



# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

# Override required_fields array with manual field names specified by user (optional)
if (!empty($manual_source_fields)) {
	//$this->module->emDebug("Manual Source Fields: " . $manual_source_fields);
	//$this->module->emDebug("Required Fields: " . $default_required_fields);
	if (count($manual_source_fields) == count($default_required_fields)) {
		foreach($manual_source_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
                $default_required_fields[$k] = $field;
				//$this->module->emDebug("changing $k to $field");
			}
		}
		$log[] = "Overriding required fields with ". implode(',',$manual_source_fields);
		//$this->module->emDebug("Required Fields After: " . $default_required_fields);
	} else {
		$msg = count($manual_source_fields) . " manual source fields specified, but the algorithm needs " . count($default_required_fields) . " fields.";
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
$missing_fields = array_diff($default_required_fields, $source_fields);
if ($missing_fields) {
	$msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "]";
	$algorithm_log[] = $msg;
	$this->module->emError($msg);
	$this->module->emDebug("Missing Fields: " . json_encode($missing_fields));
	return false;	//Since this is being called via include, the main script will continue to process other algorithms
}

# Check that all required fields have a value
$null_fields = array();
foreach ($default_required_fields as $rf) {
	if (empty($src[$rf]) && !is_numeric($src[$rf])) $null_fields[] = $rf;
}
if (!empty($null_fields)) {
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
	return false;	// Skip.  This is most commonly occurring in a multi-page survey.  We don't want to compute unless all is done.
}


### IMPLEMENT SCORING ###

# Since this is a subgroup scoring algoritm, divide the fields into the desired groups
// To get raw values, we need to summ up scores for each category based on scoreing sheet.
// The redcap values are 1-4 but the score sheet uses 0-3.  Some questions are reversed.
$reversedQuestions = array(3,7,11,12,15,17,21,22,26,32,38,40,43,45,48,52,55);
$normalizedSource = array();	// This is an array to hold the source data converted to a 0-3 scale
$gender = null;
foreach ($default_required_fields as $i => $field_name) {
	//$this->module->emDebug("Key: $i, and value: $field_name, and src[fieldname]: $src[$field_name]");
	if ($i == 65) {
		$gender = $src[$field_name];
	} else if (in_array($i+1, $reversedQuestions,true)) {
		// reverse (1=>3, 2=>2, 3=>1, 4=>0)
		$normalizedSource[$field_name] = (($src[$field_name] * -1) + 4);
		//$this->module->emDebug("Question $i should be reversed");
	} else {
		// convert 1-4 to 0-3
		$normalizedSource[$field_name] = $src[$field_name] -1;
		//$this->module->emDebug("Question $i should be NOT reversed");
	}
}
//$this->module->emDebug("SRC: " . $src);
$this->module->emDebug("NSRC: " . json_encode($normalizedSource));

// Create groups for scoring
$groups = array(
	'awr' => array(2,7,25,32,45,52,54,56),
	'cog' => array(5,10,15,17,30,40,42,44,48,58,59,62),
	'com' => array(12,13,16,18,19,21,22,26,33,35,36,37,38,41,46,47,51,53,55,57,60,61),
	'mot' => array(1,3,6,9,11,23,27,34,43,64,65),
	'rrb' => array(4,8,14,20,24,28,29,31,39,49,50,63)
);
$groups['sci'] = array_values(array_diff(range(1,65),$groups['rrb']));
$groups['total'] = range(1,65);
$this->module->emDebug("GROUPS: " . json_encode($groups));

# Next, we go through each group and substitute in the actual source data for each question
# When this is done, we have an array where the key is each group and the elemnts are an array of
# question numbers and results:
// [rbs_sub3] => Array ([rbs_15] => 3,[rbs_16] => 3,[rbs_17] => 3, ...),
// [rbs_sub4] => Array (...)
// Since our required_fields array is indexed at 0 (so question 1 is at 0, I need to add a dummy value to do the alignment)
array_unshift($default_required_fields, 'dummy_value');
$src_groups = array();
foreach($groups as $name => $question_numbers) {
	// Take the list of question numbers and get the field_names from the required_fields array
	$question_fields = array_intersect_key($default_required_fields, array_flip($question_numbers));
	//$this->module->emDebug("Question Fields: " . $question_fields);

	// Now, get the values from the normalizedSource using the field_names from above.
	$src_groups[$name] = array_intersect_key($normalizedSource, array_flip($question_fields));
}
//$this->module->emDebug("SOURCE GROUPS: " . $src_groups);


# Calculate our Totals
$result_values = array();
foreach ($src_groups as $name => $data) {
	$raw = array_sum($data);
	$result_values[$name.'_raw'] = $raw;
#	$this->module->emDebug("result name: $name and value: $raw");
}

# Retrieve the lookup tvalue table based on gender.  1 = male and 2 = female
# Current directory is autoscore so use relative path from there.
$dataPath = $data_path . 'srs-2_v1/';
$filename = null;
if ($gender == 1) {
	$filename = $dataPath . "SRS-2_v1_male.csv";
} else if ($gender == 2) {
	$filename = $dataPath . "SRS-2_v1_female.csv";
}
$this->module->emDebug(" Filename: " . $filename);

if (!empty($filename)) {
	$readFile = new ReadCSVFileClass();
	$tscore_array = $readFile->returnResults($filename);
}

$tval_values = array();
# Find out tscore sub-category scores based on the raw values
foreach($categories as $category => $value) {
	if (empty($filename)) {
		$tval_values[$value . '_tval'] = null;
	} else {
		$raw = $result_values[$value . '_raw'];
		if (isset($raw)) {
			# Look up the index of the raw value in the raw value array
			# Then, use that index into the category array
			$raw_index = array_search($raw, $tscore_array['raw']);
			$tval_values[$value . '_tval'] = $tscore_array[$value][$raw_index];
		} else {
			$tval_values[$value . '_tval'] = null;
		}
	}
}


### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$tot_result_values = array_merge($result_values, $tval_values);
$this->module->emDebug("Raw Results: " . json_encode($result_values));
$this->module->emDebug("Tval Results: " . json_encode($tval_values));
$this->module->emDebug("Total Results: " . json_encode($tot_result_values));
$algorithm_results = array_combine($default_result_fields, $tot_result_values);
$this->module->emDebug("AR: " . json_encode($algorithm_results));

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
