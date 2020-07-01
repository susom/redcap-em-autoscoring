<?php
/**
 *
 * BASC 3 Parent Relationship Questionnaire
 *
 * This questionnaire captures parental perspective on the parent-child relationship. The child age range spans 2 to 18 years old,
 *
 * There are a series of 87 questions with options 1-4 ranging from (Never to Almost always) given to parents which
 * are used in this scoring. Besides those 87 questions, the child's age and gender are also provided so T score and
 * T percentages for a general population can be looked up. The 7 raw scores calculated for the assessment are:
 * Attachment, Communication, Discipline Practices, Involvement, Parenting Confidence, Satisfaction With School and Relational Frustration.
 *
 * In addition, the F index and F index interpretation is calculated.  The F index measures the respondent's tendency
 * to be excessively negative about his or her relationship with their child.
 *
 * Also, the D index and D index interpretation is calculated. The D index measures the tendency to give an extremely
 * positive picture of his or her relationship with their child.
 *
 **/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "BASC-3 Parent Relationship Questionnaire. This assessment is used to capture parental perspective on the parent-child relationship. 
There are 87 questions with answer options of 1-4 (Never, Sometimes, Often, Almost Always) for parents to fill out.  These
questions result in 7 raw scores for Attachment, Communication, Discipline Practices, Involvement, Parenting Confidence,
Satisfaction With School and Relational Frustration.  These 7 raw scores are used to lookup T scores and percentiles. 
In addition, 4 indices values are calculated and looked up to help assess the quality of the completed record: F raw, F interpreted, D raw, D interpreted.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run. There are 90 questions.
$prefix = 'prq_';
$age_name = $prefix . "age";
$gender_name = $prefix . "gender";
$required_fields = array();
foreach (range(1,87) as $i) {
	array_push($required_fields, $prefix.$i);
}
array_push($required_fields, $gender_name);
array_push($required_fields, $age_name);

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'att',
	'comm',
	'dp',
	'inv',
	'pc',
	'sws',
	'rf'
);

$default_raw = array();
$default_tscore = array();
$default_percent = array();
foreach ($categories as $c) {
	array_push($default_raw, $prefix.$c."_raw");
    array_push($default_tscore, $prefix.$c."_tscore");
    array_push($default_percent, $prefix.$c."_percent");
}

$index_values = array("f_index_raw", "f_index_interp", "d_index_raw", "d_index_interp");
$default_result_fields = array_merge($default_raw, $default_tscore, $default_percent, $index_values);
//$this->module->emDebug("DRF: " . $default_result_fields);


### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;


# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

# Override required_fields array with manual field names specified by user (optional)
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
//$this->module->emDebug("Overriden required fields: " . json_encode($required_fields));

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
//$this->module->emDebug("Overriden required result: " . json_encode($default_result_fields));

### IMPLEMENT SCORING ###

# These fields need to reverse their scoring value
$reverse_values = array(60, 13, 79, 84);

# These are the questions that comprise each category
$category_questions = array(
    $categories[0] => array(1, 7, 15, 19, 24, 30, 34, 36, 41, 44, 52, 54, 64, 82, 87),      // att
    $categories[1] => array(2, 6, 20, 28, 35, 37, 51, 57, 60, 66, 68, 80, 85),              // comm
    $categories[2] => array(3, 9, 25, 27, 31, 43, 48, 61, 83),                              // dp
    $categories[3] => array(4, 10, 16, 38, 45, 47, 55, 62, 75),                             // inv
    $categories[4] => array(8, 13, 21, 39, 53, 56, 63, 70, 72, 76, 79, 84),                 // pc
    $categories[5] => array(12, 18, 23, 29, 33, 40, 50, 59, 73, 77),                        // sws
    $categories[6] => array(11, 14, 17, 22, 32, 42, 49, 58, 65, 67, 69, 71, 74, 78, 81, 86) // rf
);


# These are values that are used for substitution for null values based on categories.
$unscored_responses = array(
    $categories[0] => 2,            // att
    $categories[1] => 2,            // comm
    $categories[2] => 2,            // dp
    $categories[3] => 2,            // inv
    $categories[4] => 2,            // pc
    $categories[5] => 2,            // sws
    $categories[6] => 1             // rf
);

$f_index_fields = array(
    $categories[0] => array(19, 54, 64),
    $categories[1] => array(35, 57, 80),
    $categories[2] => array(),
    $categories[3] => array(4, 16, 38),
    $categories[4] => array(8, 21, 63),
    $categories[5] => array(12, 23, 29),
    $categories[6] => array(71, 78, 86)
);

$max_category_values = array(
    $categories[0] => 45,
    $categories[1] => 39,
    $categories[2] => 27,
    $categories[3] => 27,
    $categories[4] => 36,
    $categories[5] => 30,
    $categories[6] => 0
);

# Define values used in the F-index and D-index calculations
$UNSCORABLE = 'N/A';
$HIGHEST_VALUE = 3;
$LOWEST_VALUE = 0;
$MAX_MISSING_ALLOWED = 2;

# Omitted values are based on each category so split up the answers into categories first.
# Since php starts at 0 but the questions start at 1, the questions are 0-86 and 87 is gender and 88 is age
$inputs = array();
foreach ($required_fields as $field_num => $field_name) {
    if ($field_num === 87) {
        $gender = $src[$field_name];
    } else if ($field_num == 88) {
        $age = $src[$field_name];
    } else if (is_null($src[$field_name]) || ($src[$field_name] == "")) {
        $inputs[$field_num+1] = $src[$field_name];
    } else if (in_array(($field_num+1), $reverse_values)) {
        $inputs[$field_num+1] = (4-$src[$field_name]);
    } else {
        $inputs[$field_num+1] = $src[$field_name] - 1;
    }
}

#$this->module->emDebug("Required fields: " . json_encode($required_fields));
#$this->module->emDebug("Inputs " . json_encode($inputs));
#$this->module->emDebug("Age " . $age);
#$this->module->emDebug("Gender " . $gender);

# Sort the answer by category and sum the values (adding in values for null responses as needed).
# The scoring algorithm calls for answers to be 0-3 but pid 11656 has answer values of 1-4 so subtract 1 from each answer.
$category_fields = array();
$null_fields = array();
$category_sum = array();
$f_index = 0;
$d_index = 0;
foreach($categories as $c) {
    $num_of_fields = sizeof($category_questions[$c]);
    $null_fields[$c] = 0;
    for($int=0; $int < $num_of_fields; $int++) {
        $category_fields[$c][$int] = $inputs[$category_questions[$c][$int]];
        if (!is_numeric($category_fields[$c][$int])) {
            $null_fields[$c]++;
        }
    }

    # 2 or less omitted responses are allowed.  If there are 3 or more, the category is invalid.  If there is
    # 1 or 2 omitted values, use an average value for the omitted values. For all categories except Relational
    # Frustration, the expected values are Often or Always so use a value of 2.  For Relational Frustration, the
    # expected value is Never or Sometimes so use a value of 1.
    if ($null_fields[$c] > $MAX_MISSING_ALLOWED) {
        $category_sum[$c] = $UNSCORABLE;
    } else {
        $category_sum[$c] = array_sum($category_fields[$c]) + $null_fields[$c]*$unscored_responses[$c];

        # If the category sum is the max value, add to the d-index which is looking for excessively positive responses
        if ($category_sum[$c] == $max_category_values[$c]) {
            $d_index++;
        }

        # Calculate the F index adding a point when the sum is at the max value
        foreach($f_index_fields[$c] as $field_key => $field_number) {
            if ($c == $categories[6]) {
                if ($inputs[$field_number] == $HIGHEST_VALUE) {
                    $f_index++;
                }
            } else {
                if ($inputs[$field_number] == $LOWEST_VALUE) {
                    $f_index++;
                }
            }
        }
    }
}

# Determine the Validity Index Scoring for the F and D Indices.
# If the F index is 0 or 1, the validity score is Acceptable(0), when it is 2, the score is Caution(1) and when it is larger, the
# validity score indicates Extreme Caution(2).
if ($f_index < 2) {
    $f_index_validity_index = 0;
} else if ($f_index == 2) {
    $f_index_validity_index = 1;
} else {
    $f_index_validity_index = 2;
}

# If the D index is 0-2, the validity score is Acceptable(0), when it is 3, the score is Caution(1) and when it is larger, the
# validity score indicates Extreme Caution(2).
if ($d_index < 3) {
    $d_index_validity_index = 0;
} else if ($d_index == 3) {
    $d_index_validity_index = 1;
} else {
    $d_index_validity_index = 2;
}

$this->module->emDebug("Each Category: " . json_encode($category_fields));
$this->module->emDebug("Null Fields: " . json_encode($null_fields));
$this->module->emDebug("Sums Fields: " . json_encode($category_sum));
$this->module->emDebug("D Index: " . $d_index);
$this->module->emDebug("D Index Validity Score: " . $d_index_validity_index);
$this->module->emDebug("F Index:" . $f_index);
$this->module->emDebug("F Index Validity Score: " . $f_index_validity_index);

# Determine gender and age labels for the lookup file
if ($gender == 1) {
    $gender_label = 'male';
} else if ($gender == 2) {
    $gender_label = 'female';
}

if ($age >= 6.0 && $age < 10) {
    $age_label = '6to9';
} else if ($age >= 10.0 && $age < 13) {
    $age_label = '10to12';
} else if ($age >= 13.0 && $age < 16) {
    $age_label = '13to15';
} else if ($age >= 16.0 && $age < 19) {
    $age_label = '16to18';
}

$result_tscore = array();
$result_percentage = array();
if ($age_label == '' || $gender_label == '') {

    // If the age or gender was not entered, we can't look up T-score and percentile
    $msg = "Age ($age_label) or gender ($gender_label) is missing.";
    $algorithm_log[] = $msg;
    foreach($categories as $c) {
        $result_tscore[$c] = '';
        $result_percentage[$c] = '';
    }
} else {

    // Find the correct file for the lookup tables based on age and gender
    $filename = $data_path . "basc3_prq/basc3_prq_" . $gender_label . "_ages_" . $age_label . ".csv";
    $this->module->emDebug("Filename: " . $filename);

    // Now look up the standardized scores based on the just calculated raw values
    try {
        $readFile = new ReadCSVFileClass();
        $this->module->emDebug("Create ReadCSVFile");
        $lookup = $readFile->returnResults($filename);
        $this->module->emDebug("Back from ReadCSVFile");
    } catch (Exception $ex) {
        $msg = "Cannot create class to read CSV file for lookup tables - filename " . $filename;
        $this->module->emError($msg);
        return false;
    }
    $this->module->emDebug("Lookup tables for raw: " . json_encode($lookup));

    // Look up the standard score based on the raw score
    $size_of_array = count($lookup["raw"]);
    foreach ($categories as $c) {
        $result_tscore[$c . "_tscore"] = "";
        $result_percentage[$c . "_tperc"] = "";

        // Find the index of the raw value and use that index for the standard score lookup
        $index = array_search($category_sum[$c], $lookup["raw"]);
        if (($index >= 0) and ($index <= $size_of_array)) {
            $result_tscore[$c . "_tscore"] = $lookup[$c][$index];
            $result_percentage[$c . "_tperc"] = $lookup[$c."_perc"][$index];
        } else {
            $result_tscore[$c . "_tscore"] = "";
            $result_percentage[$c . "_tperc"] = "";
        }
        //$this->module->emDebug("Category: " . $c . ", T score: " . $result_tscore[$c . "_tscore"] . ", T percentage: " . $result_percentage[$c . "_tperc"]);
    }
}


### DEFINE RESULTS ###
# Merge all the raw scores, T-scores and % scores from the lookup tables.  Also add the f and d indices.
$result_values = array_merge($category_sum, $result_tscore, $result_percentage,
                            array($f_index), array($f_index_validity_index), array($d_index), array($d_index_validity_index));

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $result_values);
$this->module->emDebug("AR: " . $algorithm_results);

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