<?php
/**

	PSI4 (VERSION 2)
	
   Includes lookup table information and additional result fields
   
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
	'competence',	// competence
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
	array_push($default_result_fields, $prefix.$c."_percent");		//percent score
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
//$this->module->emDebug("SRC: " . $src);
//$this->module->emDebug("NSRC: " . $normalizedSource);
//echo "\n";
foreach ($src as $k => $v) {
	if (isset($normalizedSource[$k])) {
		//echo "$k:\t$v => {$normalizedSource[$k]}\n";
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
	'competence' => array_merge(range(28,30),range(51,60)),
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
   
   $pct = percentLookup($name.'_lookup', $raw);
   //$this->module->emDebug("PERCENT LOOKUP: $name: $raw => $pct");
   if (empty($pct)) $algorithm_log[] = "Unable to lookup $name (raw=$raw)";
	$result_values[$name.'_percent'] = $pct;
}
//$this->module->emDebug("DRF: " . $default_result_fields);
$this->module->emDebug("RV: " . $result_values);


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



//Takes category and a raw score and finds the percent
function percentLookup($category, $score) {
    
	$distract_hyper_lookup = array(
		36 => 99,
		35 => 98,
		34 => 97,
		33 => 95,
		32 => 93,
		31 => 90,
		30 => 88,
		29 => 85,
		28 => 80,
		27 => 75,
		26 => 65,
		25 => 60,
		24 => 55,
		23 => 45,
		22 => 35,
		21 => 25,
		20 => 20,
		19 => 15,
		18 => 10,
		17 => 8,
		16 => 5,
		15 => 4, 14 => 4,
		13 => 3, 12 => 3,
		11 => 2, 10 => 2,
		9 => 1
	);

	$adaptability_lookup = array(
		38 => 99,
		37 => 98,
		36 => 97, 35 => 97,
		34 => 96,
		33 => 95,
		32 => 93,
		31 => 90,
		30 => 85,
		29 => 83,
		28 => 80,
		27 => 70,
		26 => 65,
		25 => 55,
		24 => 50,
		23 => 40,
		22 => 35,
		21 => 25,
		20 => 20,
		19 => 15,
		18 => 13,
		17 => 10,
		16 => 8,
		15 => 5,
		14 => 4,
		13 => 3,
		12 => 2,
		11 => 1
	);

	$reinforcesparent_lookup = array(
		18 => 99,
		17 => 98,
		16 => 96,
		15 => 95,
		14 => 90,
		13 => 88,
		12 => 85,
		11 => 80,
		10 => 65,
		9 => 55,
		8 => 45,
		7 => 30,
		6 => 15,
		5 => 1
	);

	$demandingness_lookup = array(
		31 => 99,
		30 => 98, 29 => 98,
		28 => 97,
		27 => 96, 26 => 96,
		25 => 95,
		24 => 90,
		23 => 88,
		22 => 85,
		21 => 75,
		20 => 70,
		19 => 65,
		18 => 55,
		17 => 45,
		16 => 35,
		15 => 25,
		14 => 20,
		13 => 15,
		12 => 10,
		11 => 7,
		10 => 4,
		9 => 1
	);

	$mood_lookup = array(
		18 => 99,
		17 => 98,
		16 => 97,
		15 => 96,
		14 => 95,
		13 => 90,
		12 => 85,
		11 => 75,
		10 => 60,
		9 => 50,
		8 => 35,
		7 => 25,
		6 => 15,
		5 => 1
	);

	$acceptability_lookup = array(
		21 => 99,
		20 => 98,
		19 => 96,
		18 => 95,
		17 => 90,
		16 => 85,
		15 => 80,
		14 => 70,
		13 => 60,
		12 => 50,
		11 => 40,
		10 => 30,
		9 => 20,
		8 => 15,
		7 => 10
	);

	$competence_lookup = array(
		45 => 99,
		44 => 98,
		43 => 97, 42 => 97,
		41 => 96,
		40 => 95,
		39 => 93,
		38 => 92,
		37 => 90,
		36 => 88,
		35 => 85,
		34 => 80,
		33 => 75,
		32 => 70,
		31 => 65,
		30 => 60,
		29 => 55,
		28 => 45,
		27 => 40,
		26 => 35,
		25 => 30,
		24 => 25,
		23 => 20,
		22 => 15,
		21 => 10,
		20 => 8,
		19 => 7,
		18 => 5,
		17 => 4,
		16 => 2,
		15 => 1
	);

	$isolation_lookup = array(
		22 => 99,
		21 => 97,
		20 => 95,
		19 => 93,
		18 => 90,
		17 => 85,
		16 => 80,
		15 => 75,
		14 => 70,
		13 => 60,
		12 => 50,
		11 => 35,
		10 => 25,
		9 => 15,
		8 => 10,
		7 => 5,
		6 => 1
	);

	$attachment_lookup = array(
		22 => 99,
		21 => 98,
		20 => 97,
		19 => 96,
		18 => 95,
		17 => 90,
		16 => 85,
		15 => 80,
		14 => 75,
		13 => 65,
		12 => 50,
		11 => 35,
		10 => 25,
		9 => 15,
		8 => 10,
		7 => 1
	);

	$health_lookup = array(
		21 => 99,
		20 => 97,
		19 => 95,
		18 => 93,
		17 => 90,
		16 => 85,
		15 => 80,
		14 => 75,
		13 => 70,
		12 => 65,
		11 => 50,
		10 => 35,
		9 => 20,
		8 => 10,
		7 => 5,
		6 => 3,
		5 => 1
	);

	$rolerestriction_lookup = array(
		32 => 99,
		31 => 98,
		30 => 96,
		29 => 95,
		28 => 93,
		27 => 92,
		26 => 90,
		25 => 88,
		24 => 85,
		23 => 80,
		22 => 75,
		21 => 70,
		20 => 65,
		19 => 55,
		18 => 45,
		17 => 40,
		16 => 30,
		15 => 25,
		14 => 20,
		13 => 15,
		12 => 10,
		11 => 5,
		10 => 4,
		9 => 2,
		8 => 1
	);

	$depression_lookup = array(
		36 => 99,
		35 => 98, 34 => 98,
		33 => 97,
		32 => 96, 31 => 96,
		30 => 95,
		29 => 93,
		28 => 92,
		27 => 90,
		26 => 85,
		25 => 83,
		24 => 80,
		23 => 75,
		22 => 70,
		21 => 60,
		20 => 50,
		19 => 45,
		18 => 35,
		17 => 30,
		16 => 20,
		15 => 15,
		14 => 13,
		13 => 10,
		12 => 5,
		11 => 4,
		10 => 2,
		9 => 1
	);

	$spouse_lookup = array(
		28 => 99,
		27 => 97,
		26 => 95,
		25 => 93,
		24 => 92,
		23 => 90,
		22 => 85,
		21 => 80,
		20 => 75,
		19 => 70,
		18 => 65,
		17 => 55,
		16 => 50,
		15 => 40,
		14 => 35,
		13 => 25,
		12 => 20,
		11 => 15,
		10 => 10,
		9 => 8,
		8 => 5,
		7 => 1
	);

	$childdomain_lookup = array(
		145 => 99, 144 => 99, 143 => 99,
		142 => 98, 141 => 98, 140 => 98,
		139 => 97, 138 => 97, 137 => 97, 136 => 97,
		135 => 96, 134 => 96, 133 => 96, 132 => 96,
		131 => 95, 130 => 95,
		129 => 94, 128 => 94,
		127 => 93, 126 => 93,
		125 => 92,
		124 => 91, 123 => 91,
		122 => 90,
		121 => 89,
		120 => 88,
		119 => 87, 118 => 87,
		117 => 86,
		116 => 85,
		115 => 83,
		114 => 80,
		113 => 78,
		112 => 77,
		111 => 75,
		110 => 73,
		109 => 72,
		108 => 70,
		107 => 68,
		106 => 67,
		105 => 65,
		104 => 63,
		103 => 62,
		102 => 60,
		101 => 58,
		100 => 55,
		99 => 50,
		98 => 48,
		97 => 45,
		96 => 43,
		95 => 40,
		94 => 38,
		93 => 35,
		92 => 34,
		91 => 33,
		90 => 31,
		89 => 30,
		88 => 28,
		87 => 25,
		86 => 24,
		85 => 23,
		84 => 22,
		83 => 21,
		82 => 20,
		81 => 19,
		80 => 18,
		79 => 16,
		78 => 15,
		77 => 13,
		76 => 12,
		75 => 10,
		74 => 9, 73 => 9,
		72 => 8, 71 => 8,
		70 => 7, 69 => 7,
		68 => 6, 67 => 6,
		66 => 5, 65 => 5, 64 => 5,
		63 => 4, 62 => 4, 61 => 4, 60 => 4,
		59 => 3, 58 => 3, 57 => 3, 56 => 3,
		55 => 2, 54 => 2, 53 => 2, 52 => 2,
		51 => 1, 50 => 1
	);

	$parentdomain_lookup = array(
		188 => 99, 187 => 99, 186 => 99,
		185 => 98, 184 => 98, 183 => 98, 182 => 98, 181 => 98,
		180 => 97, 179 => 97, 178 => 97, 177 => 97,
		176 => 96, 175 => 96, 174 => 96, 173 => 96, 172 => 96,
		171 => 95, 170 => 95, 169 => 95, 168 => 95,
		167 => 94, 166 => 94, 165 => 94,
		164 => 93, 163 => 93, 162 => 93,
		161 => 92, 160 => 92, 159 => 92, 158 => 92,
		157 => 91, 156 => 91, 155 => 91,
		154 => 90, 153 => 90,
		152 => 89,
		151 => 88,
		150 => 87,
		149 => 86,
		148 => 85,
		147 => 84,
		146 => 83,
		145 => 82, 144 => 82,
		143 => 81,
		142 => 80,
		141 => 79,
		140 => 78,
		139 => 77,
		138 => 76,
		137 => 75,
		136 => 74,
		135 => 73,
		134 => 72,
		133 => 71,
		132 => 70,
		131 => 68,
		130 => 67,
		129 => 65,
		128 => 63,
		127 => 62,
		126 => 60,
		125 => 58,
		124 => 57,
		123 => 55,
		122 => 53,
		121 => 50,
		120 => 48,
		119 => 47,
		118 => 45,
		117 => 43,
		116 => 42,
		115 => 40,
		114 => 38,
		113 => 37,
		112 => 35,
		111 => 33,
		110 => 30,
		109 => 28,
		108 => 27,
		107 => 25,
		106 => 24,
		105 => 23,
		104 => 22,
		103 => 21,
		102 => 20,
		101 => 18,
		100 => 17,
		99 => 15,
		98 => 14, 97 => 14,
		96 => 13,
		95 => 12,
		94 => 11, 93 => 11,
		92 => 10, 91 => 10,
		90 => 9, 89 => 9,
		88 => 8, 87 => 8,
		86 => 7, 85 => 7,
		84 => 6, 83 => 6,
		82 => 5, 81 => 5,
		80 => 4, 79 => 4, 78 => 4,
		77 => 3, 76 => 3, 75 => 3, 74 => 3,
		73 => 2, 72 => 2, 71 => 2,
		70 => 1, 69 => 1
	);

	$totalstress_lookup = array(
		320 => 99, 319 => 99, 318 => 99,
		317 => 98, 316 => 98, 315 => 98, 314 => 98, 313 => 98, 312 => 98, 311 => 98,
        310 => 97, 309 => 97, 308 => 97, 307 => 97, 306 => 97, 305 => 97, 304 => 97,
        303 => 96, 302 => 96, 301 => 96, 300 => 96, 299 => 96, 298 => 96,
        297 => 95, 296 => 95, 295 => 95, 294 => 95, 293 => 95, 292 => 95, 291 => 95,
        290 => 94, 289 => 94, 288 => 94, 287 => 94, 286 => 94,
		285 => 93, 284 => 93, 283 => 93, 282 => 93, 281 => 93,
		280 => 92, 279 => 92, 278 => 92, 277 => 92, 276 => 92, 275 => 92,
		274 => 91, 273 => 91, 272 => 91, 271 => 91, 270 => 91,
		269 => 90, 268 => 90, 267 => 90,
		266 => 89, 265 => 89,
		264 => 88, 263 => 88,
		262 => 87, 261 => 87,
		260 => 86, 259 => 86,
		258 => 85,
		257 => 84,
		256 => 83,
		255 => 82, 254 => 82,
		253 => 81,
		252 => 80,
		251 => 79, 250 => 79,
		249 => 78, 248 => 78,
		247 => 77,
		246 => 76, 245 => 76,
		244 => 75,
		243 => 74,
		242 => 73,
		241 => 72,
		240 => 71,
		239 => 70,
		238 => 69,
		237 => 68,
		236 => 67,
		235 => 66,
		234 => 65,
		233 => 64,
		232 => 63,
		231 => 62, 230 => 62,
		229 => 61,
		228 => 60,
		227 => 59,
		226 => 58,
		225 => 56,
		224 => 55,
		223 => 53,
		222 => 50,
		221 => 49,
		220 => 48,
		219 => 47,
		218 => 46,
		217 => 45,
		216 => 43,
		215 => 42,
		214 => 40,
		213 => 39,
		212 => 38,
		211 => 37, 210 => 37,
		209 => 36,
		208 => 35,
		207 => 34, 206 => 34,
		205 => 33,
		204 => 32,
		203 => 31, 202 => 31,
		201 => 30,
		200 => 29,
		199 => 28,
		198 => 27, 197 => 27,
		196 => 26,
		195 => 25,
		194 => 24, 193 => 24,
		192 => 23,
		191 => 22,
		190 => 21, 189 => 21,
		188 => 20,
		187 => 19, 186 => 19,
		185 => 18, 184 => 18,
		183 => 17,
		182 => 16, 181 => 16,
		180 => 15, 179 => 15,
		178 => 14, 177 => 14,
		176 => 13, 175 => 13,
		174 => 12, 173 => 12,
		172 => 11, 171 => 11,
		170 => 10, 169 => 10,
		168 => 9, 167 => 9,
		166 => 8, 165 => 8,
		164 => 7, 163 => 7,
		162 => 6, 161 => 6,
		160 => 5, 159 => 5, 158 => 5, 157 => 5, 156 => 5,
		155 => 4, 154 => 4, 153 => 4, 152 => 4, 151 => 4, 150 => 4, 149 => 4,
		148 => 3, 147 => 3, 146 => 3, 145 => 3, 144 => 3, 143 => 3, 142 => 3,
		141 => 2, 140 => 2, 139 => 2, 138 => 2, 137 => 2, 136 => 2, 135 => 2,
		134 => 1, 133 => 1, 132 => 1, 131 => 1
	);

	$lifestress_lookup = array(
		27 => 99,
		26 => 98, 25 => 98,
		24 => 97, 23 => 97,
		22 => 96, 21 => 96,
		20 => 95,
		19 => 93,
		18 => 92,
		17 => 90,
		16 => 88,
		15 => 87,
		14 => 85,
		13 => 83,
		12 => 80,
		11 => 75,
		10 => 70,
		9 => 65,
		8 => 60,
		7 => 55,
		6 => 50,
		5 => 40,
		4 => 35,
		3 => 25,
		2 => 20,
		1 => 10,
		0 => 5
	);

	if ($category == 'defensiveresponse_lookup') {
		// defensive response score less than or equal to 24 means subject may be
		// responding in a defensive manner. 1 = responses okay, 2 = not okay
	    if ($score >= 24) {
	    	$result = 1;
	    } else {
			$result = 2;
	    }
	} else {
      $table = ${$category};
      if (isset($table[$score])) {
         $result = $table[$score];
      } else {
         // extrapolate
         $keys = array_keys($table);
         $key_min = min($keys);
         $key_max = max($keys);
			if ($score > $key_max) $result = '>' . $table[$key_max];
			if ($score < $key_min) $result = htmlentities("<" . $table[$key_min]);
      }
	}
	return $result;
}




?>