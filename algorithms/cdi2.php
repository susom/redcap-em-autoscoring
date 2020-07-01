<?php
/**

   CDI 2 Calculate Raw Scores and LookUp Tables
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

        - The raw scores for the following are calculated from the 28 questions on the survey
        -   and the normalized T-scores are returned:
 		- 	(TR) Total Raw = sum(q1 - q28)
 		-   (EP) Emotional Problems = sum(q1, q2, q6-q10, q13, q15-q18, q24, q26, q27)
 		-	(NM) Negative Mood = sum(q1,q9,q10,q15-q18,q26,q27)
 		-   (NS) Negative Self-Esteem = sum(q2, q6-q8, q13, q24)
 		-   (FP) Functional Problems = sum(q3-q5, q11, q12, q14, q19-q23, q25, q28)
 		-   (IE) Ineffectiveness = sum(q3, q4, q12, q14, q20, q22, q23, q28)
 		-   (IP) Interpersonal Problems = sum(q5, q11, q19, q21, q25)
        - The lookup T values are in csv files in the sub-directory CDI based on age (7-12 and 13-17)
 		- and gender (Males and Females) given to me by Vanessa Alschuler.

**/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "CDI Scoring. This script receives the answers to the 28 survey questions. Raw scores are calculated for each category and lookup tables are used for t-scores.";

# REQUIRED: Define $default_result_fields array of default input and result field_names to record the summary data
$categories = array(
	'TR',
	'EP',
	'NM',
	'NS',
	'FP',
	'IE',
	'IP');

$required_fields = array();
$default_result_raw = array();
$default_result_tscore = array();
$default_classifications = array();

# REQUIRED: Result fields that must be present for this algorithm to run
foreach ($categories as $c) {
	array_push($default_result_raw, $c."_raw");			// raw values
	array_push($default_result_tscore, $c."_tscore");		// normalized t-score
	array_push($default_classifications, $c."_class");		// classification scores
}

$default_result_fields = array_merge($default_result_raw, $default_result_tscore, $default_classifications);

# REQUIRED: Input fields that must be present for the algorithm to run
for ($ncnt=1; $ncnt <= 28; $ncnt++) {
	array_push($required_fields, $ncnt);		// questions
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
	if (count($manual_source_fields) == count($required_fields)) {
		foreach($manual_source_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$required_fields[$k] = $field;
			}
		}
		$log[] = "Overriding required fields with ". implode(',',$manual_source_fields);
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


# Test for presense of all required fields and report missing fields. Since each field is independent, just report which are missing.
$source_fields = array_keys($src);
$missing_fields = array_diff($required_fields, $source_fields);
if ($missing_fields) {
	$msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "]";
	$algorithm_log[] = $msg;
	$this->module->emError($msg);
	$this->module->emDebug("Missing Fields: " . $missing_fields);
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
}

# Create a new array with names of the manual input fields and the values
# so we can use the required names to access the values
$norm_array = array_combine($orig_required_fields, $input_values);

# Exception: if age or gender are not set, exit since the lookup tables are based on age and gender
if (is_null($norm_array['age']) or empty($norm_array['age']) or is_null($norm_array['gender']) or empty($norm_array['gender'])) {
	$algorithm_log[] = "ERROR - Age and Gender are required fields: age: " . $norm_array['age'] . " and gender: " . $norm_array['gender'];
	return false;
}

# Exception: if age is not in range between 7-17
if (($norm_array['age'] < 7) or ($norm_array['age'] > 17)) {
	$algorithm_log[] = "ERROR - Age is out of range: " . $norm_array['age'];
	return false;
}

### IMPLEMENT SCORING ###
# Calculate raw scores
# (TR) Total Raw = sum(q1 - q28)
# (EP) Emotional Problems = sum(q1, q2, q6-q10, q13, q15-q18, q24, q26, q27)
# (NS) Negative Self-Esteem = sum(q2, q6-q8, q13, q24)
# (FP) Functional Problems = sum(q3-q5, q11, q12, q14, q19-q23, q25, q28)
# (IE) Ineffectiveness = sum(q3, q4, q12, q14, q20, q22, q23, q28)
# (IP) Interpersonal Problems = sum(q5, q11, q19, q21, q25)
$survey_questions = array(
	'TR' => array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16, 17,18,19,20,21,22,23,24,25,26,27,28),
	'EP' => array(1, 2, 6, 7, 8, 9, 10, 13, 15, 16, 17, 18, 24, 26, 27),
	'NM' => array(1, 9, 10, 15, 16, 17, 18, 26, 27),
	'NS' => array(2, 6, 7, 8, 13, 24),
	'FP' => array(3, 4, 5, 11, 12, 14, 19, 20, 21, 22, 23, 25, 28),
	'IE' => array(3, 4, 12, 14, 20, 22, 23, 28),
	'IP' => array(5, 11, 19, 21, 25)
	);

// Calculate the raw scores based on the arrays above
$intersection = array();
$raw_results = array();
foreach ($categories as $c) {
	$intersection[$c] = array_intersect_key($norm_array, array_flip($survey_questions[$c]));
	$raw_results[$c] = array_sum($intersection[$c]);
}
//$this->module->emDebug("Raw scores: " . json_encode($raw_results));


// Find the location of the file that we look up the tscores
$readFile = new ReadCSVFileClass();
$filepath = $data_path . 'CDI/';

// Create file name based on age and gender
$age = intval(floor($norm_array['age']));
$gender = (($norm_array['gender'] == '2')  ? 'Females' : 'Males');   // gender is 2=female or 1=male
$filename = "";
if ($age >= 7 and $age <= 12) {
	$filename = "CDI_Lookup_Table_" . $gender . "_7to12.csv";
} else if ($age >=13 and $age <= 17) {
	$filename = "CDI_Lookup_Table_" . $gender . "_13to17.csv";
} else {
	$algorithm_log[] = "Age: $age is outside the required span of 7 to 17";
}

$tscore_results = array();
if (!empty($filename)) {
	$lookup = $readFile->returnResults($filepath . $filename);

	// Look up the tscore based on the raw score
	foreach ($categories as $c) {
		$tscore_results[$c."_tscore"] = "";
		$sizeofarray = count($lookup[$c]) - 1;

		// If the raw value is >= the first value, return the top value with >=
		if (($lookup[$c][0] != '') and ($raw_results[$c] >= $lookup[$c][0])) {
			$tscore_results[$c."_tscore"] = $lookup["t_scores"][0];
		} else if (($lookup[$c][$sizeofarray] != '') and ($raw_results[$c] <= $lookup[$c][$sizeofarray])) {
			// If the results are less than the smallest raw value, use the tscore of the smallest raw value
			$tscore_results[$c."_tscore"] = $lookup["t_scores"][$sizeofarray];
		} else {
			for($ncnt=0; $ncnt < count($lookup[$c]); $ncnt++) {
				if (strcmp($lookup[$c][$ncnt], $raw_results[$c]) == 0) {
					$tscore_results[$c."_tscore"] = $lookup["t_scores"][$ncnt];
					break;
				}
			}
		}
	}
} else {
	foreach ($categories as $c) {
		$tscore_results[$c."_tscore"] = "";
	}
}

//$this->module->emDebug("tscore array: " . json_encode($tscore_results));

// Determine which classification each score falls into
$classifications = array();

foreach ($categories as $c) {
	if (empty($tscore_results[$c."_tscore"])) {
		$classifications[$c."_class"] = "";
	} else if ($tscore_results[$c."_tscore"] >= 70) {
		$classifications[$c."_class"] = 4;
	} else if ($tscore_results[$c."_tscore"] >= 65) {
		$classifications[$c."_class"] = 3;
	} else if ($tscore_results[$c."_tscore"] >= 60) {
		$classifications[$c."_class"] = 2;
	} else {
		$classifications[$c."_class"] = 1;
	}

	// If this is the largest value add the >= or if it is the lowest value, add the <=
	if ($tscore_results[$c."_tscore"] == 90) {
		$tscore_results[$c."_tscore"] = "&#8805;" . $tscore_results[$c."_tscore"];
	} else if ($tscore_results[$c."_tscore"] == 40) {
		$tscore_results[$c."_tscore"] = "&#8804;" . $tscore_results[$c."_tscore"];
	}
}

//$this->module->emDebug("Final classification results: " . json_encode($classifications));

### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$all_results = array_merge($raw_results, $tscore_results, $classifications);
//$this->module->emDebug("All results array: " . json_encode($all_results));
$algorithm_results = array_combine($default_result_fields, $all_results);
//$this->module->emDebug("Combined array: " . json_encode($algorithm_results));

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
