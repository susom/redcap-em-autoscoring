<?php
/**
 *
 * STAI - State Trait Anxiety Inventory for Adults
 *
 * This assessment measures state and trait anxiety.  State anxiety evaluates how a participant feels right now and
 * trait anxiety measures how a participant "generally" feels. Each assessment consists of 20 questions.
 *
 * After these 2 raw scores are calculated, the T score and Percentile is looked up based on age and gender.  The
 * valid age range is 19 to 69 and Males = 1, and Females = 2.
 *
 **/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "The State Trait Anxiety Inventory for ages 19 to 69. There are 40 questions: the first 20 determine
State Anxiety and the second determine Trait Anxiety.  Besides the 40 answers, age and gender are required input.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run. There are 90 questions.
$prefix = 'stai_';
$required_fields = array();
foreach (range(1,40) as $i) {
	array_push($required_fields, $prefix.$i);
}
array_push($required_fields, "age");
array_push($required_fields, "gender");

# We are only determining state anxiety (how a person feels now) and trait anxiety (how a person normally feels).
# But, once we calculate those values, we will look up the tscore and percentile rank for state and trait.
$categories = array("state", "trait");
$default_raw = array();
$default_tscore = array();
$default_perc = array();
foreach ($categories as $c) {
	array_push($default_raw, $prefix.$c);
	array_push($default_raw, $prefix.$c);
    array_push($default_tscore, $prefix.$c."_tscore");
    array_push($default_perc, $prefix.$c."_perc");
}
$default_result_fields = array_merge($default_raw, $default_tscore, $default_perc);
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
//$this->module->emDebug("Overriden required result: " . json_encode($required_fields));

### IMPLEMENT SCORING ###

# Reverse the scoring for the following questions
$reverse_scoring = array(1, 2, 5, 8, 10, 11, 15, 16, 19, 20,            // State
                         21, 23, 26, 27, 30, 33, 34, 36, 39);           // Trait

# These are the questions that belong to each trait
$category_fields = array(
    $categories[0]  => array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20),           // State
    $categories[1]  => array(21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40)   // Trait
);


# Configure an array with the answered values reversing values as specified above. Input values range between 1-4.
# The field num starts at 0 instead of 1.
# The first 20 questions are added for the State Calculation and the second 20 are summed for the Trait Calculation.
$inputs = array();
foreach($required_fields as $field_num => $field_name) {
    $q_num = $field_num + 1;
    if ($q_num === 41) {
        $age = $src[$field_name];
    } else if ($q_num === 42) {
        $gender = $src[$field_name];
    } else if (is_null($src[$field_name]) || ($src[$field_name] === "")) {
        $inputs[$q_num] = $src[$field_name];
    } else if (in_array($q_num, $reverse_scoring)) {
        $inputs[$q_num] = (5-$src[$field_name]);
    } else {
        $inputs[$q_num] = $src[$field_name];
    }
}
$this->module->emDebug("Final input array: " . json_encode($inputs));


# If there are more than 3 missing answers, the result is not valid
$raw_value = array();
foreach($categories as $c) {
    # Array for this trait
    $trait[$c] = array_intersect_key($inputs, array_flip($category_fields[$c]));

    # See how many values are blank
    $options = array_count_values($trait[$c]);

    if ($options[''] >= 3) {
        # If there are more than 3 blanks, the assessment should be void.
        $raw_value[$c] = "N/A";
    } else if ($options[''] > 0) {
        # If 1 or 2 values are missing, find the weighted mean and multiple by 20 and round up
        $weighted_sum = 0;
        foreach($options as $num_questions => $question_value) {
            $weighted_sum += $num_questions * $question_value;
        }

        $weighted_mean = $weighted_sum/(20-$options['']);
        $raw_value[$c] = ceil($weighted_mean * 20);
    } else {
        # If all values are present, just sum them up.
        $raw_value[$c] = array_sum($trait[$c]);
    }
}
// $this->module->emDebug("Raw Trait Values: " . json_encode($raw_value));

# Use the raw values to look up state and trait T scores and percentiles
$tscores = array();
$percentage = array();
if (($gender == 1 or $gender == 2) and (($age >= 19) and ($age < 70))) {
    if ($gender == 1) {
        $gender_label = 'male';
    } else {
        $gender_label = 'female';
    }

    if ($age < 40) {
        $age_label = '19to39';
    } else if ($age < 50) {
        $age_label = '40to49';
    } else {
        $age_label = '50to69';
    }
    $lookup_file = $data_path . 'stai/stai_' . $gender_label . '_ages_' . $age_label . '.csv';

    try {
        $readFile = new ReadCSVFileClass();
        $lookup = $readFile->returnResults($lookup_file);
    } catch (Exception $ex) {
        $msg = "Cannot create class to read CSV file for lookup tables - filename " . $lookup_file;
        $this->module->emError($msg);
        return false;
    }

    # Look up the standard score based on the raw score
    foreach ($categories as $c) {
        $tscore[$c . "_tscore"] = "";
        $percentage[$c . "_perc"] = "";

        $index = array_search($raw_value[$c], $lookup["raw"]);
        if (is_numeric($index)) {
            $tscore[$c . "_tscore"] = $lookup[$c][$index];
            $percentage[$c . "_perc"] = $lookup[$c."_perc"][$index];
        } else {
            $tscore[$c . "_tscore"] = "";
            $percentage[$c . "_perc"] = "";
        }
    }
} else {
    $msg = "Gender ($gender) or age ($age) are invalid for algorithm STAI, project $project_id";
    $algorithm_log[] = $msg;
    foreach ($categories as $c) {
        $tscore[$c] = '';
        $percentage[$c] = '';
    }
}

$result_values = array_merge($raw_value, $tscore, $percentage);

### DEFINE RESULTS ###

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