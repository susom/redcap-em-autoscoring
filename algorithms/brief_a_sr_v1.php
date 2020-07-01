<?php
/**

    Behavior Rating Inventory of Executive Function for Adults
	
	A REDCap AutoScoring Algorithm File for BRIEF-A
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

        - The raw scores for the following are calculated and raw scores, normalized T-scores and Percentile Rank are returned for:
             Inhibit, Shift, Emotional Control, Self Monitor, Initiate, Working Memory, Plan/Organize,
             Task-Monitor, Organization of Materials, Behavior Regulation Index, Emotion Regulation Index,
             and Global Executive Composite Index.

        - The lookup tables for this scoring is based on age and is split into three different files:
			 BehaviorRegulationIndex and MetaCognitionIndex are in file BRIEF-A_BRI_MI_<60,70 or 80>_<69,79 or 89>.csv
 			 GlobalExecutive are in file BRIEF-A_GEC_<60,70 or 80>_<69,79 or 89>.csv

 		- The questions are categorized as 1 for Never, 2 for Sometimes and 3 for Often

 		- The age range that is currently supported for lookup tables is 60 to 89 years old.
**/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Brief Adult Self-Report v1 Scoring. This algorithm calculates raw scores for the BRIEF-A algorithm.  Once the raw scores are calculated, a table lookup is performed for the T score and Percentile Rank values based on age from the calculated raw scores.";

# REQUIRED: Define $default_result_fields array of default input and result field_names to record the summary data
$categories = array(
	'Inhibit',
	'Shift',
	'EmotionalControl',
	'SelfMonitor',
	'Initiate',
	'WorkingMemory',
	'PlanOrganize',
	'TaskMonitor',
	'OrgMaterials',
	'BehaviorRegulationIndex',
	'MetaCognitionIndex',
	'GlobalExecutive'
);

$required_fields = array();

# REQUIRED: Define an array of fields that must be present for this algorithm to run
# There are 75 input questions
for ($ncnt = 1; $ncnt <= 75; $ncnt++) {
	array_push($required_fields, "brief_a_".$ncnt);		// raw score
}
array_push($required_fields, "age");

$default_raw = array();
$default_tscore = array();
$default_percrank = array();
$default_ci_min = array();
$default_ci_max = array();
foreach ($categories as $c) {
	array_push($default_raw, $c."_raw");		// raw score
	array_push($default_tscore, $c."_tscore");	// normalized t-score
	array_push($default_percrank, $c."_perc_rank");	// percentile rank
	array_push($default_ci_min, $c."_ci_min"); // confidence interval min
	array_push($default_ci_max, $c."_ci_max"); // confidence interval max
}

// Also calculate scales and Protocol Classifications
$scales = array("Negativity", "Infrequency", "Inconsistency");
$otherscales = array();
foreach($scales as $name) {
	array_push($otherscales, $name."_score");
	array_push($otherscales, $name."_protocol_class");
}
$default_result_fields = array_merge($default_raw, $default_tscore, $default_percrank,
									$default_ci_min, $default_ci_max, $otherscales);


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

# Test for presence of all required fields and report missing fields.
$source_fields = array_keys($src);
$missing_fields = array_diff($required_fields, $source_fields);
if (count($missing_fields) > 0) {
	$msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "].";
	$algorithm_log[] = $msg;
	$this->module->emDebug("Missing Fields: " . $missing_fields);
	return false;	//Since this is being called via include, the main script will continue to process other algorithms
}

// Max allowable missing values
$total_allowable_missing = 14;

# Check that all required fields have a value
$null_fields = array();
$input_values = array();
foreach ($required_fields as $rf) {
	if (empty($src[$rf]) && !is_numeric($src[$rf])) $null_fields[] = $rf;
	$input_values[$rf] = $src[$rf];
}

//$this->module->emDebug("Null fields: " . json_encode($null_fields));

//j If the number of fields that are null is greater than the allowable, we can't calculate anything
if (count($null_fields) <= $total_allowable_missing) {
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
	$this->module->emError(json_encode($algorithm_log));
	$this->module->emDebug("Missing Values: " . $null_fields);
} else if (count($null_fields) > $total_allowable_missing) {
	$msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "]. Only 14 missing values are allowable";
	$algorithm_log[] = $msg;
	$this->module->emError($missing_fields);
	return false;	//Since this is being called via include, the main script will continue to process other algorithms

}

# Create a new array with names of the required fields and the values from the input array
# so we can use the required field names to access the values
$norm_array = array_combine($orig_required_fields, $input_values);


### IMPLEMENT SCORING ###
// These are the arrays of fields to use to calculate raw values for each category
$raw_score_questions = array(
	$categories[0] => array("brief_a_5", "brief_a_16", "brief_a_29", "brief_a_36", "brief_a_43", "brief_a_55",
							"brief_a_58", "brief_a_73"),																// Inhibit
	$categories[1] => array("brief_a_8", "brief_a_22", "brief_a_32", "brief_a_44", "brief_a_61", "brief_a_67"),			// Shift
	$categories[2] => array("brief_a_1", "brief_a_12", "brief_a_19", "brief_a_28", "brief_a_33",
							"brief_a_42", "brief_a_51", "brief_a_57", "brief_a_69", "brief_a_72"),						// EmotionalControl
	$categories[3] => array("brief_a_13", "brief_a_23", "brief_a_37", "brief_a_50", "brief_a_64", "brief_a_70"),		// SelfMonitor
	$categories[4] => array("brief_a_6", "brief_a_14", "brief_a_20", "brief_a_25", "brief_a_45",
							"brief_a_49", "brief_a_53", "brief_a_62"),													// Initiate
	$categories[5] => array("brief_a_4", "brief_a_11", "brief_a_17", "brief_a_26", "brief_a_35",
							"brief_a_46", "brief_a_56", "brief_a_68"),													// WorkingMemory
	$categories[6] => array("brief_a_9", "brief_a_15", "brief_a_21", "brief_a_34", "brief_a_39",
							"brief_a_47", "brief_a_54", "brief_a_63", "brief_a_66", "brief_a_71"),						// PlanOrganize
	$categories[7] => array("brief_a_2", "brief_a_18", "brief_a_24", "brief_a_41", "brief_a_52", "brief_a_75"),			// TaskMonitor
	$categories[8] => array("brief_a_3", "brief_a_7", "brief_a_30", "brief_a_31", "brief_a_40", "brief_a_60",
							"brief_a_65", "brief_a_74")																	// OrgMaterials
);

// These are the arrays of allowable null fields for each category
$max_allowable = array(
	$categories[0] => 2,	// Inhibit
	$categories[1] => 1,	// Shift
	$categories[2] => 2,	// EmotionalControl
	$categories[3] => 1,	// SelfMonitor
	$categories[4] => 2,	// Initiate
	$categories[5] => 2,	// WorkingMemory
	$categories[6] => 2,	// PlanOrganize
	$categories[7] => 1,	// TaskMonitor
	$categories[8] => 2		// OrgMaterials
);


// Calculate the raw scores by summing the survey results for each category
foreach($raw_score_questions as $category => $list) {

	// Filter the fields and retrieve only the fields for this category
	$category_fields = array_intersect_key($norm_array, array_flip($list));

	// This function will group the survey responses into bins based on the value
	$group_values = array_count_values($category_fields);

	// See if there is a null bin where the respondent did not answer a question.  If so, make sure the
	// number of unanswered questions is not bigger than the max allowable.  If the number is less than
	// the max allowable, use 1 for the null values.
	if (!is_null($group_values[""]) and ($group_values[""] > $max_allowable[$category])) {
		$raw[$category . "_raw"] = "";
	} else if (!is_null($group_values[""])) {
		$raw[$category . "_raw"] = array_sum($category_fields) + $group_values[""];
	} else {
		$raw[$category . "_raw"] = array_sum($category_fields);
	}
}

// For the Behavior Regulation Index, sum the raw scores for Inhibit, Shift, EmotionalControl and Self-Monitor
$raw['BehaviorRegulationIndex' . '_raw'] = $raw[$categories[0] . "_raw"] + $raw[$categories[1] . "_raw"] +
									$raw[$categories[2] . "_raw"] + $raw[$categories[3] . "_raw"];
// For the MetaCognitionIndex, sum the raw scores for Initiate, Working Memory, Plan/Organize, Task Monitor
//													and Organization of Materials
$raw['MetaCognitionIndex' . '_raw'] = $raw[$categories[4] . "_raw"] + $raw[$categories[5] . "_raw"] + $raw[$categories[6] . "_raw"] +
									$raw[$categories[7] . "_raw"] + $raw[$categories[8] . "_raw"];
// For the Global Executive Composite Score, sum the BRI and MCI or sum all the raw scores
$raw['GlobalExecutive' . '_raw'] = $raw['BehaviorRegulationIndex' . '_raw'] + $raw['MetaCognitionIndex' . '_raw'];


// Now that the raw scores are calculated, put together the file names with the lookup tables which are based on age.
// Hannah wants 90yo to use the same lookup tables as 80-89 so I increased the age to 90.
$age_portion = "";
$age = floor($norm_array['age']);
if (($age >= 60) and ($age <= 69)) {
	$age_portion = "60_69";
} else if (($age >= 70) and ($age <= 79)) {
	$age_portion = "70_79";
} else if (($age >= 80) and ($age <= 90)) {
	$age_portion = "80_89";
} else {
	$algorithm_log[] = "ERROR - age, $age, is out of range";
	$this->module->emError($algorithm_log);
}

// These are the arrays of confidence intervals for each category
$confidence_intervals = array(
	$categories[0] => 2,	// Inhibit
	$categories[1] => 1,	// Shift
	$categories[2] => 2,	// EmotionalControl
	$categories[3] => 1,	// SelfMonitor
	$categories[4] => 2,	// Initiate
	$categories[5] => 2,	// WorkingMemory
	$categories[6] => 2,	// PlanOrganize
	$categories[7] => 1,	// TaskMonitor
	$categories[8] => 2		// OrgMaterials
);

$tscore = array();
$percrank = array();
$ci_min = array();
$ci_max = array();
if ($age_portion != "") {

	# Put together the filenames. There are 3 files - one for the raw scores, one for BRI and MI and one for GEC.
	$filePath = $data_path . "brief-A/";
	$filenameSR = $filePath . 'BRIEF-A_SR_' . $age_portion . '.csv';
	$filenameBRI_MI = $filePath . 'BRIEF-A_BRI_MI_' . $age_portion . '.csv';
	$filenameGEC = $filePath . 'BRIEF-A_GEC_' . $age_portion . '.csv';
	$filenameCI = $filePath . 'ConfidenceIntervals.csv';

	# Retrieve the look up tables for this gender and age group
	$readFile = new ReadCSVFileClass();
	$lookupTablesSR = $readFile->returnResults($filenameSR);
	$lookupTablesBRIMI = $readFile->returnResults($filenameBRI_MI);
	$lookupTablesGEC = $readFile->returnResults($filenameGEC);
	$confidence_intervals = $readFile->returnResults($filenameCI);

	# We now have the tables, look up each raw value and retrieve the Tscore and percentage rank
	foreach ($categories as $category) {

		// Based on the category, use the corresponding lookup tables
		if ($category == "GlobalExecutive") {
			$lookupTable = $lookupTablesGEC;
		} else if (($category == 'BehaviorRegulationIndex') or ($category == 'MetaCognitionIndex')) {
			$lookupTable = $lookupTablesBRIMI;
		} else {
			$lookupTable = $lookupTablesSR;
		}

		// Retrieve the raw value and lookup the raw score in the table
		$raw_value = $raw[$category . "_raw"];
		$index = array_search($raw_value, $lookupTable['Raw']);

		// Save the values corresponding to the raw value
		if (is_null($index)) {
			$tscore[$category . 'tscore'] = null;
			$percrank[$category . '_percrank'] = null;
		} else {
			$tscore[$category . 'tscore'] = $lookupTable[$category . 'TScore'][$index];
			$percrank[$category . '_percrank'] = $lookupTable[$category . '%Rank'][$index];
		}

		// If a lookup was found for the tscore, calculate the confidence intervals
		if (is_null($tscore[$category . 'tscore'])) {
			$ci_min[$category . "ci_min"] = null;
			$ci_max[$category . "ci_max"] = null;
		} else {
			$index = array_search($age_portion, $confidence_intervals['Age']);
			$ci_value = $confidence_intervals[$category][$index];
			$ci_min[$category . "ci_min"] = $tscore[$category . 'tscore'] - $ci_value;
			$ci_max[$category . "ci_max"] = $tscore[$category . 'tscore'] + $ci_value;
		}
	}

} else {

	// Age is out of range so we cannot lookup TScores and Percentile Rank
	foreach ($categories as $category) {
		$tscore[$category . 'tscore'] = null;
		$percrank[$category . '_percrank'] = null;
		$ci_min[$category . '_ci_min'] = null;
		$ci_max[$category . '_ci_max'] = null;
	}
	$this->module->emDebug("Invalid age: $age - skipping lookups");
}

// Now calculate the negativity scale from the results. Sum the number of negativity fields which have
// the value of 3 (Often)
$other_scales = array();
$neg_scale_fields = array("brief_a_1", "brief_a_8", "brief_a_19", "brief_a_21", "brief_a_22",
						  "brief_a_23", "brief_a_29", "brief_a_36", "brief_a_39", "brief_a_40");
// Filter the fields and retrieve only the fields for the negativity score
$category_fields = array_intersect_key($norm_array, array_flip($neg_scale_fields));

// This function will group the survey responses into bins based on the value
// The score is determined by how many answers of Often (3)there are
$group_values = array_count_values($category_fields);
if (is_null($group_values['3']) || ($group_values['3'] = '')) {
	$other_scales["Negativity"] = 0;
} else {
	$other_scales["Negativity"] = $group_values['3'];
}

// Based on the negativity score, the protocol classification is either Elevated or Acceptable
if ($other_scales["Negativity"] >= 6) {
	$other_scales["NegativityPC"] = 'Elevated';
} else {
	$other_scales["NegativityPC"] = 'Acceptable';
}

// Calculate the Infrequency Scale
$infrequency_fields = array("brief_a_10" => 3, "brief_a_27" => 1, "brief_a_38" => 3,
							"brief_a_48" => 1, "brief_a_59" => 1);

// Filter the fields and retrieve only the fields for the negativity score
$other_scales["Infrequency"] = 0;
foreach($infrequency_fields as $field => $value) {
	if ($norm_array[$field] == $value) {
		$other_scales["Infrequency"]++;
	}
}

// Based on the infrequency score, determine the protocol classification
if ($other_scales["Infrequency"] >= 3) {
	$other_scales["InfrequencyPC"] = 'Infrequent';
} else {
	$other_scales["InfrequencyPC"] = 'Acceptable';
}

// Calculate the field value differences for the inconsistency scale
$inconsistency_fields = array("brief_a_2" => "brief_a_41", "brief_a_25" => "brief_a_49",
							"brief_a_28" => "brief_a_42", "brief_a_33" => "brief_a_72",
							"brief_a_34" => "brief_a_63", "brief_a_44" => "brief_a_61",
							"brief_a_46" => "brief_a_56", "brief_a_52" => "brief_a_75",
							"brief_a_60" => "brief_a_74", "brief_a_64" => "brief_a_70");

// The inconsistency score is calculated by summing the subtraction of the field values
// and taking the absolute value.
$other_scales["Inconsistency"] = 0;
foreach($inconsistency_fields as $field1 => $field2) {
	$other_scales["Inconsistency"] += abs($norm_array[$field1] - $norm_array[$field2]);
}

// Determine the protocol classification based on the inconsistency score
if ($$other_scales["Inconsistency"] >= 8) {
	$other_scales["InconsistencyPC"] = 'Inconsistent';
} else {
	$other_scales["InconsistencyPC"] = 'Acceptable';
}


# REQUIRED: Combine the results. The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$result_values = array_merge($raw, $tscore, $percrank, $ci_min, $ci_max, $other_scales);
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
