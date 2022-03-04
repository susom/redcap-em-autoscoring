<?php

/**
 * PROMIS Pediatric Depressive Symptoms 8a v2
 *
 * This REDCap Autoscoring Algorithm will sum the response of the 8 questions on the Promise survey.
 * Each of the responses range from 1 (Never) to 5 (Almost Always) therefore the result from the
 * questionnaire ranges from 8 to 40.
 *
 * Once the result is calculated, the T-score and standard error on the T-score will be looked up
 * in the data file and returned.
 */


use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Promise Pediatric Depressive Symptoms 8a v2. This algorithm sums the results of the 8 survey questions and looks up the T-scores and standard error";

# REQUIRED: Define $default_result_fields array of default input and result field_names to record the summary data
$default_result_fields = array("score", "tscore", "se");

# REQUIRED: Define an array of fields that must be present for this algorithm to run -  only 8 questions
$required_fields = array("feeling_sad", "alone", "lifewrong", "nothingright", "lonely", "sad", "unhappy", "nofun");


### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;


# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

# Override required_fields array with manual field names specified by user (optional)
$orig_required_fields = $required_fields;
if (!empty($manual_source_fields)) {
	if (count($manual_source_fields) == count($required_fields)) {
		foreach($manual_source_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$required_fields[$k] = $field;
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
$input_values = array();
foreach ($required_fields as $rf) {
	if (empty($src[$rf]) && !is_numeric($src[$rf])) $null_fields[] = $rf;
	$input_values[$rf] = $src[$rf];
}
if (!empty($null_fields)) {
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
// continue processing even if some values are missing
//	return false;  // prevent scoring during partial submissions (section breaks)
}

# Create a new array with names of the manual input fields and the values
# so we can use the required names to access the values
$norm_array = array_combine($orig_required_fields, $input_values);

### IMPLEMENT SCORING ###

// Read in the lookup file so we can retrieve the tscore and standard
$filePath = $this->module->getModulePath() . "DataFiles/promise/ped_depsym8a_v2.csv";
$readFile = new ReadCSVFileClass();
$lookup = $readFile->returnResults($filePath);

// Sum the results of each question.  The values range from 1-5 with 8 questions so the
// total ranges from 8 to 40. Look up the index into the table for this total score.
$total_score = sum($norm_array);
$index = array_search($total_score, $lookup['raw']);

// Fill in the result array
$result_values[] = $total_score;
$result_values[] = $lookup['tscore'][$index];
$result_values[] = $lookup['se'][$index];

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
