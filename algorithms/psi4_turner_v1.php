<?php
/**

	PSI4
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "PSI-4.  It assumes the questions are coded as 1-5 for all questions.  The algorithm handles reversing the scoring on certain questions.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$prefix = 'psi_';
// Replace as necessary for your particular scoring algorithm
$required_fields = array();
foreach (range(1,120) as $i) {
	array_push($required_fields, $prefix.$i);
}


# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'distract_hyper',	// Distractibilty / Hyperactivity
	'adaptability',	// Adaptability
	'reinforcesparent',	// Reinforces Parent
	'demandingness',	// demandingness
	'mood',	// mood
	'acceptability',	// acceptabilty
	'coompetence',	// competence
	'isolation',	// isolation
	'attachment',	// Attachment
	'health',	// Health
	'rolerestriction',	// Role Restriction
	'depression',	// Depression
	'spouse',	// Spouse Partner Relationship
	'childdomain',	// Child Domain
	'parentdomain',	// Parent Domain
	'totalstress',	// Total Stress
	'lifestress',	// Life Stress
	'defensiveresponse'	// Defensive Raw
);
$default_result_fields = array();
foreach ($categories as $c) {
	array_push($default_result_fields, $prefix.$c."_raw");		//raw score
}
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

// Since more are reveresed than not, I'm going to make an array of those we don't need to reverse:
$standardQuestions = array(5,9,11,16,30,42,43,53,54,57,58,61,66,95,98);
$main_set = array_merge(range(1,14), range(16,39), range(41,100));
$reversedQuestions = array_values(array_diff($main_set,$standardQuestions));
// Q15 is special!	
// Q40 is special!
// Q101 is special

$special_conv = array (1=>1,2=>2,3=>4,4=>5);
$special_conv2 = array(102=>7, 103=>4, 104=>5, 105=>8, 106=>4, 107=>4, 108=>4, 109=>4, 110=>2, 111=>3, 112=>4, 113=>7, 114=>4, 115=>4, 116=>3, 117=>2, 118=>2, 119=>2, 120=>6);
$special_conv3 = array (1=>5,2=>4,3=>2,4=>1);

$normalizedSource = array();	// This is an array to hold the source data converted to a normal scale
foreach ($required_fields as $i => $field_name) {
	$i++;	// Add one to offset index starting at 0
	if ($i == 15 || $i == 40) {
		$normalizedSource[$field_name] = $special_conv[$src[$field_name]];		
		//$this->module->emDebug("Question $i is special: ". $src[$field_name] . " => " . $normalizedSource[$field_name]);
	} elseif($i == 101) {
		$normalizedSource[$field_name] = $special_conv3[$src[$field_name]];		
	} elseif (in_array($i, $reversedQuestions,true)) {
		// reverse (1=>5, 2=>4, 3=>3, 4=>2, 5=>1)
		$normalizedSource[$field_name] = (($src[$field_name] * -1) + 6);
		//$this->module->emDebug("Question $i should be reversed: ". $src[$field_name] . " => " . $normalizedSource[$field_name]);
	} elseif (in_array($i, $standardQuestions,true)) {
		$normalizedSource[$field_name] = $src[$field_name];
		//$this->module->emDebug("Question $i should be NOT reversed: ". $src[$field_name] . " => " . $normalizedSource[$field_name]);
	} elseif (filter_var($i,FILTER_VALIDATE_INT,array('options'=>array('min_range'=>102,'max_range'=>120)))) {
		$normalizedSource[$field_name] = $src[$field_name] == 1 ? $special_conv2[$i] : 0;				
		//$this->module->emDebug("Question $i is special: ". $src[$field_name] . " => " . $normalizedSource[$field_name]);
	}
}
$this->module->emDebug("SRC: " . $src);
$this->module->emDebug("NSRC: " . $normalizedSource);
echo "\n";
foreach ($src as $k => $v) {
	if (isset($normalizedSource[$k])) {
		echo "$k:\t$v => {$normalizedSource[$k]}\n";
	}
}



// Create groups for scoring
$groups = array(
	'distract_hyper' => range(1,9),
	'adaptability' => range(31,41),
	'reinforcesparent' => range(10,15),
	'demandingness' => range(42,50),
	'mood' => range(16,20),
	'acceptability' => range(21,27),
	'coompetence' => array_merge(range(28,30),range(51,60)),
	'isolation' => range(91,96),
	'attachment' => range(61,67),
	'health' => range(97,101),
	'rolerestriction' => range(68,74),
	'depression' => range(75,83),
	'spouse' => range(84,90),
	'childdomain' => array_merge(range(1,27),range(31,50)),
	'parentdomain' => array_merge(range(28,30),range(51,101)),
	'totalstress' => range(1,101),
	'lifestress' => range(102,120),
	'defensiveresponse' => array(56,69,70,71,77,80,81,82,85,87,88,91,93,94,95)
);
//$this->module->emDebug("GROUPS: " . $groups);

# Next, we go through each group and substitute in the actual source data for each question
# When this is done, we have an array where the key is each group and the elemnts are an array of
# question numbers and results:
// [rbs_sub3] => Array ([rbs_15] => 3,[rbs_16] => 3,[rbs_17] => 3, ...),
// [rbs_sub4] => Array (...)
// Since our required_fields array is indexed at 0 (so question 1 is at 0, I need to add a dummy value to do the alignment)
array_unshift($required_fields, 'dummy_value');
$src_groups = array();
foreach($groups as $name => $question_numbers) {
	// Take the list of question numbers and get the field_names from the required_fields array
	$question_fields = array_intersect_key($required_fields, array_flip($question_numbers));
	//$this->module->emDebug("Question Fields: " . $question_fields);
	
	// Now, get the values from the normalizedSource using the field_names from above.
	$src_groups[$name] = array_intersect_key($normalizedSource, array_flip($question_fields));
}
$this->module->emDebug("SOURCE GROUPS: " . $src_groups);

# Calculate our Totals
$result_values = array();
foreach ($src_groups as $name => $data) {	
	$raw = array_sum($data);
//	list($std, $pct, $convmsg) = convert($name, $raw, $age, $sex);
//	if ($convmsg) $algorithm_log[] = $convmsg;	
	$result_values[$name.'_raw'] = $raw;
//	$result_values[$name.'_percent'] = $pct;
}
//$this->module->emDebug("DRF: " . $default_result_fields);
//$this->module->emDebug("RV: " . $result_values);


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