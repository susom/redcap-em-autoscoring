<?php
/**

   Stroke Scoring
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

        - The raw scores for the following are entered and the normalized T-scores are returned: Trails A, Trails B,
                Boston Naming, Animal Naming, Digit Span, Symbol Search xx, Symbol Digit, COWAT, Word Reading,
                Color Naming, Color-Word, Interference, HVLT Imm, HVLT Del, HVLT Ret.  Also, lookup scores are
                found for COWAT through the CFL MOANS Norms for Persons Aged 56-97 table.
**/


# REQUIRED: Summarize this algorithm
$algorithm_summary = "RCFT Scoring. This algorithm looksup the Mean and Standard Deviation based on age and calculates the Z and T RCFT scores.";

# REQUIRED: Define $default_result_fields array of default input and result field_names to record the summary data

$required_fields = array();
$default_result_fields = array();

# REQUIRED: Define an array of fields that must be present for this algorithm to run
array_push($required_fields, "raw_score");
array_push($required_fields, "age");

array_push($default_result_fields, "z_score");
array_push($default_result_fields, "t_score");

### VALIDATION ###
# If we are simply verifying the result fields and required fields, we can exit now.
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
	return false;  // prevent scoring during partial submissions (section breaks)
}

# Create a new array with names of the manual input fields and the values
# so we can use the required names to access the values
$norm_array = array_combine($orig_required_fields, $input_values);

### IMPLEMENT SCORING ###

# Each category has their own calculation so calculate each separately
$age = $norm_array['age'];
$raw_score = $norm_array['raw_score'];
$this->module->emDebug("age: ". $age . "; and raw score " . $raw_score);
$result_values = array();

# These are the lookup tables based on age for Mean and SD, 
if ($age >= 6 && $age < 7) {
	$mean = 16.66;
        $sd =    7.97;
} elseif ($age >= 7 && $age < 8) {
	$mean = '21.29';
        $sd   = '7.67';
} elseif ($age >= 8 && $age < 9) {
	$mean = '23.64';
        $sd   = '8';
} elseif ($age >= 9 && $age < 10) {
	$mean = '24.46';
        $sd   = '6.94';
} elseif ($age >= 10 && $age < 11) {
	$mean = '27.2';
        $sd   = '7.58';
} elseif ($age >= 11 && $age < 12) {
	$mean = '28.61';
        $sd   = '7.31';
} elseif ($age >= 12 && $age < 13) {
	$mean = '30.21';
        $sd   = '6.69';
} elseif ($age >= 13 && $age < 14) {
	$mean = '32.63';
        $sd   = '4.35';
} elseif ($age >= 14 && $age < 15) {
	$mean = '33.53';
        $sd   = '3.18';
} elseif ($age >= 15 && $age < 16) {
	$mean = '33.6';
        $sd   = '2.98';
} else {
	$mean = 'NA';
        $sd   = 'NA';
}


if (is_numeric($mean) && is_numeric($sd)) {
	$z_score = round(($raw_score - $mean)/$sd, 2);
	$t_score = round(((($raw_score - $mean)/$sd)*10) + 50,2);
} else {
	$z_score = "NA";
	$t_score = "NA";
}

$result_values['z_score'] = $z_score;
$result_values['t_score'] = $t_score;

$this->module->emDebug("mean = " . $mean . "; sd = " . $sd . "; z result = " . $z_score . "; t result = " . $t_score);


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
