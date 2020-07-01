<?php
/**

   brief2 v1 Scoring
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

        - The raw scores for the following are entered and the normalized T-scores and Percentile Rank are returned:
             Inhibit, Self-Monitor, Shift, Emotional Control, Initiate, Working Memory, Plan/Organize,
             Task-Monitor, Organization of Materials, Behavior Regulation, Emotion Regulation,
             Cognitive Regulation, Global Executive Composite

        - The lookup tables for this scoring is based on age and from an Excel spreadsheet called
          'LookUpTable_BRIEF2.xlsx'

**/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Brief2_v1 Scoring. This algorithm performs a table lookup for the T score and Percentile Rank values based on age from the input calculated raw values.";

# REQUIRED: Define $default_result_fields array of default input and result field_names to record the summary data
$categories = array(
	'Inhibit',
	'SelfMonitor',
	'Shift',
	'EmotionalControl',
	'Initiate',
	'WorkingMemory',
	'PlanOrganize',
	'TaskMonitor',
	'OrgMaterials',
	'BehaviorRegulation',
	'EmotionRegulation',
	'CognitiveRegulation',
	'GlobalExecutive'
);
$required_fields = array();
$default_result_fields = array();

# REQUIRED: Define an array of fields that must be present for this algorithm to run
foreach ($categories as $c) {
	array_push($required_fields, $c."_raw");		// raw score
	array_push($default_result_fields, $c."_tscore");	// normalized t-score
	array_push($default_result_fields, $c."_perc_rank");	// percentile rank
}

# We also need age and education as inputes
array_push($required_fields, "age");
array_push($required_fields, "gender");

### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true)  return true;



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
	$this->module->emError($algorithm_log);
	$this->module->emDebug("Missing Values: " . $null_fields);
}

$this->module->emDebug("Input values: " . json_encode($input_values));

# Create a new array with names of the manual input fields and the values
# so we can use the required names to access the values
$norm_array = array_combine($orig_required_fields, $input_values);

### IMPLEMENT SCORING ###

$age = floor($norm_array['age']);
$gender = $norm_array['gender'];
$this->module->emDebug("Age: $age, and gender: $gender");
if (($age < 5) or ($age > 18)) {
	$algorithm_log[] = "ERROR - age is out of range";
	$this->module->emError($algorithm_log);
	$this->module->emDebug("Age is Out of Range" . $age);
	return false;
}
if ($gender < 0 or $gender > 2) {
	$algorithm_log[] = "ERROR - gender is not valid";
	$this->module->emError($algorithm_log);
	$this->module->emDebug("Gender is Out of Range" . $gender);
	return false;
}


# Put together the filename. The naming convention is brief_v2_<gender>_<x>to<y>.csv
# <gender> is 1 = girls, 2 = boys
# <x> is the lower age for assessment
# <y> is the upper age for assessment
# There are 4 age groups 5 to 7, 8 to 10, 11 to 13, 14 to 18.
$gender_lookup = array(1 => 'girls', 2 => 'boys');
$age_lookup = array(5 => '5to7', 6 => '5to7', 7 => '5to7',
                    8 => '8to10', 9 => '8to10', 10 => '8to10',
                    11 => '11to13', 12 => '11to13', 13=> '11to13',
                    14 => '14to18', 15 => '14to18', 16 => '14to18', 17 => '14to18', 18 => '14to18');


# Retrieve the look up tables for this gender and age group
$filePath = $data_path . "brief2_v1/";
$filename = $filePath . 'brief2_v1_' . $gender_lookup[$gender] . '_' . $age_lookup[$age] . '.csv';
$this->module->emDebug("This is the lookup filename: " . $filename);

$readFile = new ReadCSVFileClass();
$lookupTables = $readFile->returnResults($filename);
$result_values = array();

# We now have the tables, look up each raw value and retrieve the Tscore and percentage rank
foreach ($categories as $val) {
	$name = $val . '_raw';
	$raw = $norm_array[$val . '_raw'];
	$index = array_search($raw, $lookupTables['RawScore']);
	if (is_null($index)) {
		$result_values[$val . 'tscore'] = null;
		$result_values[$val . '_perc_rank'] = null;
	} else {
		$result_values[$val . 'tscore'] = $lookupTables[$val . '_TScore'][$index];
		$result_values[$val . '_perc_rank'] = $lookupTables[$val . '_Perc_Rank'][$index];
	}
}

#$this->module->emLog("Result array: " . implode($result_values, ','));

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
