<?php
/**

	Revised Child Anxiety and Depression Scale 47 (RCADS-47)
	
	This algorithm is the extended algorithm from RCADS 25 Short Questionaire. This
	algorithm supports both Child version and there is a similar algorithm for the
	Parent version.

	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There are 47 input questions, gender and age that are input.
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results
	- After the raw scores are calculated, the t-score is determined via a lookup table for:
		Boys 3rd and 4th grade/Girls 3rd and 4th grade
		Boys 5th and 6th grade/Girls 5th and 6th grade
		Boys 7th and 8th grade/Girls 7th and 8th grade
		Boys 9th and 10th grade/Girls 9th and 10th grade
		Boys 11th and 12th grade/Girls 11th and 12th grade
	-- For each subcategory: Social Phobia (SP), Panic Disorder (PD), Major Depression (MD),
				 Separation Anxiety (SA), Generalized Anxiety (GA),
				 Obsessive-Compulsive (OC) and Total Anxiety (TA)
	-- The incoming data should be coded as: 0=Never, 1=Sometimes, 2=Often, 3=Always

**/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";


# REQUIRED: Summarize this algorithm
$algorithm_summary = "Revised Child Anxiety and Depression Scale 47 - Child version. This algorithm assumes 0=Never, 1=Sometimes, 2=Often and 3=Always for all 47 questions. This algorithm also needs gender, grade and who (either P for parent or C for child).";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$prefix = 'rcads_47';
// Replace as necessary for your particular scoring algorithm
$required_fields = array();
foreach (range(1,47) as $i) {
	array_push($required_fields, $prefix.'_q'.$i);
}

# These fields are used for the lookup tables.  Who refers to the parent questionaire 'P' or 
# the child questionaire 'C'. 
array_push($required_fields, 'grade');
array_push($required_fields, 'gender');
array_push($required_fields, 'who');


# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'soc',	// social phobia
	'pan',	// panic disorder
	'dep',	// major depression
	'sep',	// separation anxiety
	'anx',	// generalized anxiety
	'oc'	// obsessive-compulsive
);

$composites = array(
	'ta',   // total anxiety
	'tad'	 // total anxiety and depression
);

$default_result_fields = array();
$tscore_result_fields = array();
foreach ($categories as $c) {
	array_push($default_result_fields, $prefix.'_'.$c.'_raw');
	array_push($tscore_result_fields, $prefix.'_'.$c.'_tval');
}
foreach ($composites as $c) {
	array_push($default_result_fields, $prefix.'_'.$c.'_raw');
	array_push($tscore_result_fields, $prefix.'_'.$c.'_tval');
}

$default_result_fields = array_merge($default_result_fields, $tscore_result_fields);
$this->module->emDebug("DRF: " . $default_result_fields);


### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;


# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");


# Override required_fields array with manual field names specified by user (optional)
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


# Test for presense of all required fields and report missing fields. 
# We are reporting on it and stopping. Not all fields have to have a value since we are allowed missing values.
$source_fields = array_keys($src);
$missing_fields = array_diff($required_fields, $source_fields);
if ($missing_fields) {
	$msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "]";
	$algorithm_log[] = $msg;
	$this->module->emError($msg);
	$this->module->emDebug($missing_fields, $missing);
	return false;
}



### IMPLEMENT SCORING ###

# Create groups for scoring
$question_numbers = array(
	'soc' => array(4,7,8,12,20,30,32,38,43),      // social phobia
	'pan' => array(3,14,24,26,28,34,36,39,41),    // panic disorder
	'dep' => array(2,6,11,15,19,21,25,29,40,47),  // major depression
	'sep' => array(5,9,17,18,33,45,46),           // separation anxiety
	'anx' => array(1,13,22,27,35,37),             // generalized anxiety
	'oc'  => array(10,16,23,31,42,44)             // obsessive-compulsive
);

# To score, count up the answers and also count the number of missing answers.  If the number
# of missing is greater than 2, don't score. If 1 or 2 are missing, use the following algorithm
# to fill in the missing data: 
#     round(sum(completed scores)*(number of total questions/answered questions))

// Numerical Index of questions and results
$iResults = array();
for ($i=1 ;$i<=47 ;$i++) {
	$iResults[$i] = $src[$required_fields[$i-1]];
}
$this->module->emDebug("iResults: " . $iResults);
//$this->module->emDebug("This is the results array: " . implode(",", $iResults));


# These are the 3 extra pieces of data we are retrieving from the questionaire.  The lookup tables
# are based on grade, gender and who means 'C' for Child or 'P' for Parent.
# There are 47 questions but the index starts at 0 so the questions end at 46.
$grade = $src[$required_fields[47]];
$gender = $src[$required_fields[48]];
$who = $src[$required_fields[49]];


# Next, we go through each group and substitute in the actual source data for each question
$category_totals = array();
$src_groups = array();
$missing = 'Missing Data';
foreach($categories as $name) {
	$src_groups[$name] = array_intersect_key($iResults, array_flip($question_numbers[$name]));
	$nullVals = 0;

	foreach ($src_groups[$name] as $value) {
		if (empty($value)) { 
			$nullVals += 1; 
		}
	}

	# According to the scoring algorithm, we are allowed 2 nulls for each sub-category
	if ($nullVals <= 2) {
		$category_totals[$name] = array_sum($src_groups[$name]);

		# If there are some missing fields, we must compensate for the missing values.
		if ($nullVals > 0) {
			$num_questions = count($question_numbers[$name]);
			$category_totals[$name] = round(($category_totals[$name]/($num_questions - $nullVals)) * $num_questions);
		}

	} else {
		# We don't score anything that has more than 2 missing values
		$category_totals[$name] = $missing;
	}
}


# Now calculate the composite totals: tad (Total Anxiety and Depression) sum of all 6 subscales
#                                     ta (Total Anxiety) sum of all subscales except dep
$strict = TRUE;
$null_key = array_search($missing, $category_totals, $strict);
$category_totals['ta'] = $missing;
$category_totals['tad'] = $missing;
if (strlen($null_key) == 0 or $null_key == 'dep') {
	$category_totals['ta'] = array_sum($category_totals) - $category_totals['dep'];
	if (empty($null_key)) {
		$category_totals['tad'] = $category_totals['ta'] + $category_totals['dep'];
	}
}

#
#  These are the files which hold the tscores for parents and children.
#  Figure out which file to use based on grade and gender - 0 are boys, 1 are girls

$tscore_files_child = array (
	0 => array(                                     # 0 = Male
		'3' => array('rcad47_child_male_3_4_grade.csv', 'rcad47_child_male_totals_3_4_grade.csv' ),
		'4' => array('rcad47_child_male_3_4_grade.csv', 'rcad47_child_male_totals_3_4_grade.csv' ),
		'5' => array('rcad47_child_male_5_6_grade.csv', 'rcad47_child_male_totals_5_6_grade.csv' ),
		'6' => array('rcad47_child_male_5_6_grade.csv', 'rcad47_child_male_totals_5_6_grade.csv' ),
		'7' => array('rcad47_child_male_7_8_grade.csv', 'rcad47_child_male_totals_7_8_grade.csv' ),
		'8' => array('rcad47_child_male_7_8_grade.csv', 'rcad47_child_male_totals_7_8_grade.csv' ),
		'9' => array('rcad47_child_male_9_10_grade.csv', 'rcad47_child_male_totals_9_10_grade.csv' ),
		'10' => array('rcad47_child_male_9_10_grade.csv', 'rcad47_child_male_totals_9_10_grade.csv' ),
		'11' => array('rcad47_child_male_11_12_grade.csv', 'rcad47_child_male_totals_11_12_grade.csv' ),
		'12' => array('rcad47_child_male_11_12_grade.csv', 'rcad47_child_male_totals_11_12_grade.csv' ),
		),
	1 => array(                                    # 1 = Female
		'3' => array('rcad47_child_female_3_4_grade.csv', 'rcad47_child_female_totals_3_4_grade.csv' ),
		'4' => array('rcad47_child_female_3_4_grade.csv', 'rcad47_child_female_totals_3_4_grade.csv' ),
		'5' => array('rcad47_child_female_5_6_grade.csv', 'rcad47_child_female_totals_5_6_grade.csv' ),
		'6' => array('rcad47_child_female_5_6_grade.csv', 'rcad47_child_female_totals_5_6_grade.csv' ),
		'7' => array('rcad47_child_female_7_8_grade.csv', 'rcad47_child_female_totals_7_8_grade.csv' ),
		'8' => array('rcad47_child_female_7_8_grade.csv', 'rcad47_child_female_totals_7_8_grade.csv' ),
		'9' => array('rcad47_child_female_9_10_grade.csv', 'rcad47_child_female_totals_9_10_grade.csv' ),
		'10' => array('rcad47_child_female_9_10_grade.csv', 'rcad47_child_female_totals_9_10_grade.csv' ),
		'11' => array('rcad47_child_female_11_12_grade.csv', 'rcad47_child_female_totals_11_12_grade.csv' ),
		'12' => array('rcads47_child_female_11_12_grade.csv', 'rcad47_child_female_totals_11_12_grade.csv' )
	)
);

$tscore_files_parents = array (
	0 => array(                                     # 0 = Male
		'3' => array('rcad47_parent_male_3_4_grade.csv', 'rcad47_parent_male_totals_3_4_grade.csv' ),
		'4' => array('rcad47_parent_male_3_4_grade.csv', 'rcad47_parent_male_totals_3_4_grade.csv' ),
		'5' => array('rcad47_parent_male_5_6_grade.csv', 'rcad47_parent_male_totals_5_6_grade.csv' ),
		'6' => array('rcad47_parent_male_5_6_grade.csv', 'rcad47_parent_male_totals_5_6_grade.csv' ),
		'7' => array('rcad47_parent_male_7_8_grade.csv', 'rcad47_parent_male_totals_7_8_grade.csv' ),
		'8' => array('rcad47_parent_male_7_8_grade.csv', 'rcad47_parent_male_totals_7_8_grade.csv' ),
		'9' => array('rcad47_parent_male_9_10_grade.csv', 'rcad47_parent_male_totals_9_10_grade.csv' ),
		'10' => array('rcad47_parent_male_9_10_grade.csv', 'rcad47_parent_male_totals_9_10_grade.csv' ),
		'11' => array('rcad47_parent_male_11_12_grade.csv', 'rcad47_parent_male_totals_11_12_grade.csv' ),
		'12' => array('rcad47_parent_male_11_12_grade.csv', 'rcad47_parent_male_totals_11_12_grade.csv' ),
		),
	1 => array(                                    # 1 = Female
		'3' => array('rcad47_parent_female_3_4_grade.csv', 'rcad47_parent_female_totals_3_4_grade.csv' ),
		'4' => array('rcad47_parent_female_3_4_grade.csv', 'rcad47_parent_female_totals_3_4_grade.csv' ),
		'5' => array('rcad47_parent_female_5_6_grade.csv', 'rcad47_parent_female_totals_5_6_grade.csv' ),
		'6' => array('rcad47_parent_female_5_6_grade.csv', 'rcad47_parent_female_totals_5_6_grade.csv' ),
		'7' => array('rcad47_parent_female_7_8_grade.csv', 'rcad47_parent_female_totals_7_8_grade.csv' ),
		'8' => array('rcad47_parent_female_7_8_grade.csv', 'rcad47_parent_female_totals_7_8_grade.csv' ),
		'9' => array('rcad47_parent_female_9_10_grade.csv', 'rcad47_parent_female_totals_9_10_grade.csv' ),
		'10' => array('rcad47_parent_female_9_10_grade.csv', 'rcad47_parent_female_totals_9_10_grade.csv' ),
		'11' => array('rcad47_parent_female_11_12_grade.csv', 'rcad47_parent_female_totals_11_12_grade.csv' ),
		'12' => array('rcads47_parent_female_11_12_grade.csv', 'rcad47_parent_female_totals_11_12_grade.csv' )
	)
);

# Go read the lookup table which will give us the tvalue.
# Find the name of the file that holds the subcategory to tvalue lookup tables
# These files are based on parent/child questionaire, grade and gender
if ($who == 'P') {
	$tscore_files = $tscore_files_parents[$gender][$grade];
} else {
	$tscore_files = $tscore_files_child[$gender][$grade];
}

# Read in the lookup tables from the files. Current directory is autoscore so use relative path from there.
$dataPath = $data_path . 'rcad47/';
$filename = $dataPath . $tscore_files[0];
$this->module->emDebug("Gender: " . $gender . " Grade: " . $grade . " Who: " . $who);
$this->module->emDebug("The tscore file is: " . $filename );


$readFile = new ReadCSVFileClass();
$tscore_array = $readFile->returnResults($filename);

# Make sure we get the lookup tables back. If not, set all the tvalues to N/A
$tval_values = array();
if (is_null($tscore_array)) {
	foreach($categories as $category => $value) {
		$tval_values[$value."_tval"] = $missing;
	}

} else {

	# Find out tscore sub-category scores based on the raw values
	foreach($categories as $category =>  $value) {
		if ($category_totals[$value] === $missing)  {
			$this->module->emDebug("Category: $value and category value: " . $category_totals[$value]);
			$tval_values[$value."_tval"] = $missing;
		} else {
			$valueIndex = array_search($category_totals[$value], $tscore_array['raw']);
			$tval_values[$value."_tval"] = $tscore_array[$value][$valueIndex];
		}
	}
}

# if there are values for the totals (total anxiety and total anxiety and depression), then get those lookup tables
$filename = $dataPath . $tscore_files[1];
$tscore_array = $readFile->returnResults($filename);

# Make sure we get the lookup tables back. If not, set all the tvalues to N/A
if (is_null($tscore_array)) {
        foreach($composites as $category => $value) {
                $tval_values[$value."_tval"] = $missing;
        }

} else {

        # Find out tscore sub-category scores based on the raw values
        foreach($composites as $category =>  $value) {
#		$this->module->emDebug("Composite value: " . $value . " value: " . $category_totals[$value]);
                if ($category_totals[$value] == $missing) {
                        $tval_values[$value."_tval"] = $missing;
                } else {
                        $valueIndex = array_search($category_totals[$value], $tscore_array['raw']);
                        $tval_values[$value."_tval"] = $tscore_array[$value][$valueIndex];
                }
        }
}


### DEFINE RESULTS ###
#$this->module->emDebug("category_results = " . implode(",", $category_totals));
#$this->module->emDebug("tval results = " . implode(",", $tval_values));
#$this->module->emDebug("combined results = " . implode(",", array_merge($category_totals, $tval_values)));


# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, array_merge($category_totals, $tval_values));
$this->module->emDebug("AR: " . $algorithm_results);

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
