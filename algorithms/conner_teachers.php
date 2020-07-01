<?php
/**

   Conners Teachers Scoring
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

        - The raw scores for the following are entered and the normalized T-scores are returned:
 		- 	(IN) Inattention, (HY) Hyperactivity/Impulsivity, (LE) Learning Problems/Executive Functioning
 		-	(LP) Learning Problems, (EF) Executive Functioning, (AG) Aggression,
 		-	(PR) Peer Relations, (GI) Connors 3 Global Index Total, (AN) ADHD Predominately Inattentive,
 		-	(AH) ADHD Predominately Hyperactive-Impulsive, (CD) Conduct Disorder, (OD) Oppositional Defiant Disorder

        - The lookup T values are in csv files called 'conners_teachers_female_ageX.csv' and
 		-    'conners_teachers_male_ageX.csv' given to me by Hannah Fingerhut. These files are located
 		-	in the directory of ./plugins/autoscore/DataFiles/conners/teachers/. These files are based on
 		-	gender and age.  Age ranges are 6-18 with the 17 and 18 data files the same for each gender.

**/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Conner Teacher Scoring Lookup. This script receives the raw score for each category and looks up the T score.";

# REQUIRED: Define $default_result_fields array of default input and result field_names to record the summary data
$categories = array(
	'IN',
	'HY',
	'LE',
	'LP',
	'EF',
	'AG',
	'PR',
	'GI',
	'AN',
	'AH',
	'CD',
	'OD'
);
$categories_AIGI = array(
	'3AI',
	'3GI_RI',
	'3GI_EL'
);
$required_fields = array();
$default_result_fields = array();

# REQUIRED: Define an array of fields that must be present for this algorithm to run
foreach ($categories as $c) {
	array_push($required_fields, $c."_raw");		// raw score
	array_push($default_result_fields, $c."_tscore_norm");		// normalized t-score
}

foreach ($categories_AIGI as $c) {
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
// Make sure the age entered is in the acceptable age range: 6-18
$age = intval(floor($norm_array['age']));
if ($age < 6 or $age > 18) {
	$algorithm_log[] = "ERROR - Age range is 6-18 yrs old but entered age is " . $norm_array['age'] . '. Exiting';
	return false;
}

// Make sure the gender is either 1 for female or 2 for male
if ($norm_array['gender'] == '1') {
	$file_gender = "female";
} else if ($norm_array['gender'] == '2') {
	$file_gender = "male";
} else {
	$algorithm_log[] = "ERROR - Gender must be 1 for female or 2 for male but the entered gender is " . $norm_array['gender'] . ". Exiting";
	return false;
}

// Put together the file name
$result_values = array();
$filename = "conners_teachers_" . $file_gender . "_age" . $age . ".csv";
$filename_AIGI = "conners_teachers_" . $file_gender . "_age" . $age . "_AIGI.csv";

$filepath = $data_path . 'conners/teachers/';
$readFile = new ReadCSVFileClass();
$lookup = $readFile->returnResults($filepath . $filename);

foreach ($categories as $c) {

	if (!empty(trim($lookup[$c][0])) && ($norm_array[$c.'_raw'] >= $lookup[$c][0])) {
		$result_values[$c . "_tscore_norm"] = $lookup['T'][0];
	} else {
		$index = array_search($norm_array[$c . '_raw'], $lookup[$c]);
		$this->module->emDebug("Index of lookup Value is: " . $index);
		$result_values[$c . "_tscore_norm"] = $lookup['T'][$index];
	}
	$this->module->emDebug("This is category $c; raw value = " . $norm_array[$c.'_raw'] . ", t index = " . $index . ", and tscore = " . $result_values[$c."_tscore_norm"]);
}

$lookup = $readFile->returnResults($filepath . $filename_AIGI);
foreach ($categories_AIGI as $c) {

	if (!empty(trim($lookup[$c][0])) && ($norm_array[$c.'_raw'] >= $lookup[$c][0])) {
		$this->module->emDebug("Category $c, lookup: " . $lookup[$c][0] . ", calculated value: " . $norm_array[$c.'_raw']);
		$result_values[$c . "_tscore_norm"] = $lookup['T'][0];
	} else {
		$index = array_search($norm_array[$c . '_raw'], $lookup[$c]);
		$this->module->emDebug("Index of lookup Value for category $c is: " . $index);
		$result_values[$c . "_tscore_norm"] = $lookup['T'][$index];
	}
	$this->module->emDebug("This is category $c; raw value = " . $norm_array[$c.'_raw'] . ", t index = " . $index . ", and tscore = " . $result_values[$c."_tscore_norm"]);
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
