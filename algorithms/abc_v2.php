<?php
/**

	Aberrant Behavior Checklist
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "The Aberrant Behavior Checklist Score Sheet comes from Slosson Educational Publications, Inc.";

# REQUIRED: Define an array of default result field_names to record the summary data
$default_result_fields = array(
	'abc_irritability_total',
	'abc_lethargy_total',
	'abc_stereotypy_total',
	'abc_hyperactivity_total',
	'abc_speech_total',
	'abc_totalscore'
);

# REQUIRED: Define an array of fields that must be present for this algorithm to run

$required_fields = array();
foreach (range(1,58) as $i) {
	array_push($required_fields, "abc_$i");
}

# Override default input array with manual field names specified by user (optional)
if (!empty($manual_source_fields)) {
    if (count($manual_source_fields) == count($required_fields)) {
    	foreach($manual_source_fields as $k => $field) {
    		if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$required_fields[$k] = $field;
				//$this->module->emDebug("Changing input field ".$k." to ".$field);
    		}
    	}
    	$log[] = "Overriding default input field names with ". implode(',',$manual_source_fields);
    } else {
    	$msg = count($manual_source_fields) . " manual source fields specified, but the algorithm needs " . count($required_fields) . " fields.";
    	$this->module->emError($msg);
    	$algorithm_log[] = $msg;
    	return false;
    }
}


$this->module->emDebug("Required Fields: " . json_encode($required_fields));
$this->module->emDebug("Source fields: " . json_encode($src));
$this->module->emDebug("Default result fields: " . json_encode($default_result_fields));
### VALIDATION ###

# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;

# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

$this->module->emDebug("Algorithm: " . json_encode($algorithm_log));

# Override default result array with manual field names specified by user (optional)
if (!empty($manual_result_fields)) {
	$this->module->emDebug("In manual result fields: " . json_encode($manual_result_fields));
	if (count($manual_result_fields) == count($default_result_fields)) {
		$this->module->emDebug("Counts are the same");
		foreach($manual_result_fields as $k => $field) {
			$this->module->emDebug("K $k, field $field");
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$default_result_fields[$k] = $field;
				$this->module->emDebug("Changing result field ".$k." to ". $field);
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

$this->module->emDebug("Starting algorithm: log: " . json_encode($log));

# Test for presense of all required fields and report missing fields
$this->module->emDebug("Source fields: " . json_encode($src));
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
//	$this->module->emDebug("Value for field $rf is " . $src[$rf]);
	if (empty($src[$rf]) && !is_numeric($src[$rf])) $null_fields[] = $rf;
}
if (!empty($null_fields)) {
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
	return false;
}

### IMPLEMENT SCORING ###
# This is the array source
# Define lists of questions that correspond to each subscale

$q_subscale_I = array(2,4,8,10,14,19,25,29,34,36,41,47,50,52,57);
$q_subscale_II = array(3,5,12,16,20,23,26,30,32,37,40,42,43,53,55,58);
$q_subscale_III = array(6,11,17,27,35,45,49);
$q_subscale_IV = array(1,7,13,15,18,21,24,28,31,38,39,44,48,51,54,56);
$q_subscale_V = array(9,22,33,46);
$s_I = 0;
$s_II = 0;
$s_III = 0;
$s_IV = 0;
$s_V = 0;

foreach ($required_fields as $i => $field_name) {
	$val = $src[$field_name];
    if (in_array($i+1, $q_subscale_I)) {
       $s_I = $s_I + $val;
//       	$this->module->emDebug("In subscale I: Question = ". $i+1 . ", field_name = " . $field_name . ", field_val = ". $val . ", s_I = ". $s_I);
    } else if (in_array($i+1, $q_subscale_II)) {
       $s_II = $s_II + $val;
//       	$this->module->emDebug("In subscale II: Question = ". $i+1 . ", field_name = " . $field_name . ", field_val = ". $val . ", s_II = ". $s_II);
    } else if (in_array($i+1, $q_subscale_III)) {
       $s_III = $s_III + $val;
//       	$this->module->emDebug("In subscale III: Question = ". $i+1 . ", field_name = " . $field_name . ", field_val = ". $val . ", s_III = ". $s_III);
    } else if (in_array($i+1, $q_subscale_IV)) {
       $s_IV = $s_IV+ $val;
//       	$this->module->emDebug("In subscale IV: Question = " . $i+1 . ", field_name = " . $field_name . ", field_val = ". $val . ", s_IV = ". $s_IV);
    } else if (in_array($i+1, $q_subscale_V)) {
       $s_V = $s_V + $val;
//       	$this->module->emDebug("In subscale V Question = ". $i+1 . ", field_name = " . $field_name . ", field_val = ". $val . ", s_V = ". $s_V);
    } else {
		$this->module->emError("Not in subscale: Question = ". $i+1 . ", field_name = " . $field_name . ", field_val = ". $val);
    }
}

# Calculate our Totals
$result_values = array($s_I, $s_II, $s_III, $s_IV, $s_V);
array_push($result_values, array_sum($result_values));	// Add on a final grand total
$this->module->emDebug("Result values to save: ".json_encode($result_values));


### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $result_values);

# Append result field for algorithm log if specified via the log_field variable in the config project
# Because we aren't pulling the entire data dictionary, we can't confirm whether or not the field actually exists
if ($job['log_field']) {
	$algorithm_results[$job['log_field']] = implode("\n",$algorithm_log);
	$msg = "Custom log_field {$job['log_field']}";
	$algorithm_log[] = $msg;
	$algorithm_results = array_merge($algorithm_results, array($job['log_field'] => $algorithm_log));
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
