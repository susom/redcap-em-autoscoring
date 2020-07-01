<?php
/**
 *
 * FES using T/F questions.
 *
 * This Falls Efficiency Scale (FES) assessment indicate the level of perceived confidence an individual has about carrying out everyday activities without falling.
 * This assessment is a self-report survey consisting of 90 T/F questions. There is another scoring algorithm called FES_v1.php which scores the 90 FES questions using the
 * likert scoring option. (** This assessment only has options for participants to select T/F instead of the likert scale. **)
 *
 * A REDCap AutoScoring Algorithm File
 *
 * - There exists an array called $src that contains the data from the source project
 * - There can exist an optional array called $manual_result_fields that can override the default_result_fields
 * - The final results should be presented in an array called $algorithm_results
 *
 **/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Falls Efficiency Scale assessment. This algorithm assumes the questions are coded as T or F for all 90 questions. If FES scoring for your project uses the Likert Scale, use the other version of the FES scoring (fes_v1.php). The algorithm handles reversing the scoring on certain questions.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run. There are 90 questions.
$prefix = 'fes_';
$required_fields = array();
foreach (range(1,90) as $i) {
	array_push($required_fields, $prefix.$i);
}

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'cohesion',
	'express',
	'conflict',
	'independence',
	'achieve',
	'intellect',
	'active',
	'moral',
	'organization',
	'control'
);
$default_raw = array();
$default_standard = array();
foreach ($categories as $c) {
	array_push($default_raw, $prefix.$c."_raw");
    array_push($default_standard, $prefix.$c."_standard");
}
$default_result_fields = array_merge($default_raw, $default_standard);
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
				//$this->module->emDebug("changing $k to $field");
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
//$this->module->emDebug("Final required field list: " . json_encode($required_fields));

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
		return;
	}
}
//$this->module->emDebug("Overridden result fields: " . json_encode($default_result_fields));


# Test for presense of all required fields and report missing fields
$source_fields = array_keys($src);
$missing_fields = array_diff($required_fields, $source_fields);
if ($missing_fields) {
	$msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "]";
	$algorithm_log[] = $msg;
    $this->module->emError($msg);
    $this->module->emDebug("Missing Fields" . $missing_fields);
	return false;	//Since this is being called via include, the main script will continue to process other algorithms
}

# Check that all required fields have a value
$null_fields = array();
foreach ($required_fields as $rf) {
	if (empty($src[$rf]) && !is_numeric($src[$rf])) $null_fields[] = $rf;
}
if (!empty($null_fields)) {
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
	return false;  // prevent scoring during partial submissions (section breaks)
}

### IMPLEMENT SCORING ###

# Since this is a subgroup scoring algoritm, divide the fields into the desired groups
// To get raw values, we need to summ up scores for each category based on scoreing sheet.
// The redcap values are 1-5 but the score sheet uses 5-1.  Some questions are reversed.

$reversedQuestions = array( 2,4,7,10,
                            11,13,16,18,20,
                            22,25,27,29,
                            33,36,38,
                            41,44,46,49,
                            52,55,57,60,
                            61,63,65,68,70,
                            72,74,76,79,
                            83,84,87);

$iResults = array();	// numerically indexed array of results
$normalizedSource = array();	// This is an array to hold the source data converted to a normal scale
foreach ($required_fields as $i => $field_name) {
	$i++;	// Add one to offset index starting at 0
	if (in_array($i, $reversedQuestions, true)) {
		$normalizedSource[$field_name] = 1 - $src[$field_name];
	} else {
		$normalizedSource[$field_name] = $src[$field_name];
	}
	$iResults[$i] = $normalizedSource[$field_name];
}

//$this->module->emDebug("SRC: " . $src);
//$this->module->emDebug("NSRC: " . json_encode($normalizedSource));
//$this->module->emDebug("iResults: " . json_encode($iResults));

// Create groups for scoring
$groups = array(
    $categories[0] 	=> array(1,11,21,31,41,51,61,71,81),
    $categories[1] 	=> array(2,12,22,32,42,52,62,72,82),
    $categories[2] 	=> array(3,13,23,33,43,53,63,73,83),
    $categories[3] 	=> array(4,14,24,34,44,54,64,74,84),
    $categories[4] 	=> array(5,15,25,35,45,55,65,75,85),
	$categories[5] 	=> array(6,16,26,36,46,56,66,76,86),
    $categories[6]	=> array(7,17,27,37,47,57,67,77,87),
    $categories[7] 	=> array(8,18,28,38,48,58,68,78,88),
	$categories[8] 	=> array(9,19,29,39,49,59,69,79,89),
    $categories[9]	=> array(10,20,30,40,50,60,70,80,90)
);
//$this->module->emDebug("GROUPS: " . json_encode($groups));

# Next, we go through each group and substitute in the actual source data for each question
# When this is done, we have an array where the key is each group and the elements are an array of
# question numbers and results:

// Since our required_fields array is indexed at 0 (so question 1 is at 0, I need to add a dummy value to do the alignment)
array_unshift($required_fields, 'dummy_value');
$src_groups = array();
foreach($groups as $name => $question_numbers) {
	$src_groups[$name] = array_intersect_key($iResults, array_flip($question_numbers));
}
//$this->module->emDebug("SOURCE GROUPS: " . json_encode($src_groups));

# Calculate our Totals
$result_values = array();
foreach ($src_groups as $name => $data) {	
	$raw = array_sum($data);
	$result_values[$name.'_raw'] = $raw;
}
//$this->module->emDebug("RV: " . json_encode($result_values));

# Now look up the standardized scores based on the just calculated raw values
$readFile = new ReadCSVFileClass();
$filename = $data_path . 'fes2/fes2_raw_to_standard_scores.csv';

$sscore_results = array();
$lookup = $readFile->returnResults($filename);
//$this->module->emDebug("Lookup tables: " . json_encode($lookup));

// Look up the standard score based on the raw score
foreach ($categories as $c) {
    $sscore_results[$c."_sscore"] = "";
    $size_of_array = count($lookup[$c]) - 1;
    if (empty($lookup[$c][$size_of_array])) {
        // For some reason Independence has a blank at the end of the lookup table, so if the last value of the column
        // is null, the size of the array is one less
        $size_of_array--;
    }

    // Find the index of the raw value and use that index for the standard score lookup
    $index = (array_keys($lookup["raw"], $result_values[$c."_raw"]))[0];
    //$this->module->emDebug("Category: " . $c . ", raw value: " . $result_values[$c."_raw"] . ". index: " . $index);
    if (($index >= 0) and ($index <= $size_of_array)) {
        $sscore_results[$c."_sscore"] = $lookup[$c][$index];
    } else {
        $sscore_results[$c."_sscore"] = "";
    }
}

// Add the standard scores to the raw scores
$result = array_merge($result_values, $sscore_results);
//$this->module->emDebug("Result array: " . json_encode($result));

### DEFINE RESULTS ###
# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $result);

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