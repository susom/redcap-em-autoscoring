<?php
/**

   Conners Scoring
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

        - The raw scores for the following are entered and the normalized T-scores are returned:
 		- 	(AG) Defiance/Aggression, (AH) DSM-5 ADHD Hyperactive-Impulsive, (AN) DSM-5 ADHD Inattentive,
 		-   (CD) DSM-5 Conduct Disorder, (EF) Executive Functioning, (GI) Connors 3 Global Index Total,
 		-   (HY) Hyperactivity/Impulsivity, (IN) Inattention, (LP) Learning Problems, (OD) DSM-5 Oppositional Defiant Disorder,
 		-   (PR) Peer Relations
        - The lookup T values are in spreadsheets called 'Female Profile Scoring Table-Edited.xlsx' and
 		-    'Male Profile Scoring Table.xlsx' given to me by Lindsay Chromik.

**/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Conner Scoring. This script receives the raw score for each category and looks up the T score.";

# REQUIRED: Define $default_result_fields array of default input and result field_names to record the summary data
$categories = array(
	'AG',
	'AH',
	'AN',
	'CD',
	'EF',
	'GI',
	'HY',
	'IN',
	'LP',
	'OD',
	'PR',
	'ADHD',
	'RI',
	'EL'
);
$required_fields = array();
$default_result_fields = array();

# REQUIRED: Define an array of fields that must be present for this algorithm to run
foreach ($categories as $c) {
	array_push($required_fields, $c."_raw");		// raw score
	array_push($default_result_fields, $c."_tscore_norm");		// normalized t-score
}

# We also need age and gender
array_push($required_fields, "age");
array_push($required_fields, "gender");

### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;


# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

# Override required_fields array with manual field names specified by user (optional)
$orig_required_fields = $required_fields;
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


# Test for presense of all required fields and report missing fields. Since each field is independent, just report which are missing.
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
$this->module->emDebug("Input values: " . json_encode($input_values));

if (!empty($null_fields)) {
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
// continue processing even if some values are missing
//	return false;  // prevent scoring during partial submissions (section breaks)
}

# Create a new array with names of the manual input fields and the values
# so we can use the required names to access the values
$norm_array = array_combine($orig_required_fields, $input_values);

# Exception: if age or gender are not set, exit since the lookup tables are based on age and gender
if (is_null($norm_array['age']) or empty($norm_array['age']) or is_null($norm_array['gender']) or empty($norm_array['gender'])) {
	$algorithm_log[] = "ERROR - Age and Gender are required fields: age: " . $norm_array['age'] . " and gender: " . $norm_array['gender'];
	return false;
}

### IMPLEMENT SCORING ###

# Each category has their own calculation so calculate each separately
$age = intval(floor($norm_array['age']));
$gender = (($norm_array['gender'] == '1')  ? 'female' : 'male');   // gender is 1=female or 2=male
$result_values = array();

$filepath = $data_path . 'conners/';
$readFile = new ReadCSVFileClass();

foreach ($categories as $c) {
	$filename = 'conners_' . $c . '_' . $gender . '.csv';
    $lookup = $readFile->returnResults($filepath . $filename);
	if (!empty($lookup[$age][0]) and intval(substr($lookup[$age][0], 0, strlen($lookup[$age][0]) - 1)) <= $norm_array[$c.'_raw']) {
        $result_values[$c."_tscore_norm"] = $lookup['T'][0];
	} else {
        $index = array_search($norm_array[$c.'_raw'], $lookup[$age]);
		//$this->module->emDebug("This is the category: " . $c . ", this is the raw value: " . $norm_array[$c.'_raw'] . ", this is the index: " . $index);
		$result_values[$c."_tscore_norm"] = $lookup['T'][$index];
	}
	//$this->module->emDebug("T Value for category: " . $c . " is " . $result_values[$c."_tscore_norm"] . " for raw value of " . $norm_array[$c.'_raw']);
}

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
