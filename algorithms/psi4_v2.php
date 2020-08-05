<?php
/**

	PSI4

	A REDCap AutoScoring Algorithm File

	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "PSI-4.  It assumes the questions are coded as 1-5 for all questions.  The algorithm handles reversing the scoring on certain questions. Once the raw scores are calculated, the percentiles and tvals are looked up based on age and the defensive response score is determined.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$prefix = 'psi_';
// Replace as necessary for your particular scoring algorithm
$required_fields = array();
foreach (range(1,120) as $i) {
	array_push($required_fields, $prefix.$i);
}
# Age is now required so we can use the lookup tables to retrieve percentiles and tvalues
array_push($required_fields, 'age');


# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'distract_hyper',	// Distractibilty / Hyperactivity
	'adaptability',		// Adaptability
	'reinforcesparent',	// Reinforces Parent
	'demandingness',	// demandingness
	'mood',			// mood
	'acceptability',	// acceptabilty
	'coompetence',		// competence
	'isolation',		// isolation
	'attachment',		// Attachment
	'health',		// Health
	'rolerestriction',	// Role Restriction
	'depression',		// Depression
	'spouse',		// Spouse Partner Relationship
	'childdomain',		// Child Domain
	'parentdomain',		// Parent Domain
	'totalstress',		// Total Stress
	'lifestress',		// Life Stress
	'defensiveresponse'	// Defensive Raw
);
$default_result_fields = array();
$raw_fields = array();
$perc_fields = array();
$tval_fields = array();
foreach ($categories as $c) {
	array_push($raw_fields, $prefix.$c."_raw");			// raw score
	if ($c != 'defensiveresponse') {
		array_push($perc_fields, $prefix.$c."_perc");		// raw score => lookup percentile
		array_push($tval_fields, $prefix.$c."_tval");		// raw score => lookup tvalue score
	}
}
array_push($tval_fields, $prefix."defensive_significant");
$default_result_fields = array_merge($raw_fields, $perc_fields, $tval_fields);
$this->module->emDebug("DRF: " . json_encode($default_result_fields));


### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;


# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

$default_required_fields = $required_fields;
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

$key_index = array_search('age', $default_required_fields);
$age = intval(floor($src[$required_fields[$key_index]]));
$this->module->emDebug("Age: $age");


### IMPLEMENT SCORING ###

# Since this is a subgroup scoring algoritm, divide the fields into the desired groups
// To get raw values, we need to summ up scores for each category based on scoreing sheet.
// The redcap values are 1-5 but the score sheet uses 5-1.  Some questions are reversed.

// Since more are reveresed than not, I'm going to make an array of those we don't need to reverse:
$standardQuestions = array(5,11,16,30,42,53,54,57,58,61,95,98);
$main_set = array_merge(range(1,14), range(16,39), range(41,101));
$reversedQuestions = array_values(array_diff($main_set,$standardQuestions));
// Q15 is special!
// Q40 is special!

$special_conv = array (1=>1,2=>2,3=>4,4=>5);
$special_conv2 = array(102=>7, 103=>4, 104=>5, 105=>8, 106=>4, 107=>4, 108=>4, 109=>4, 110=>2, 111=>3, 112=>4, 113=>7, 114=>4, 115=>4, 116=>3, 117=>2, 118=>2, 119=>2, 120=>6);

$normalizedSource = array();	// This is an array to hold the source data converted to a normal scale
foreach ($required_fields as $i => $field_name) {
	$i++;	// Add one to offset index starting at 0
	if ($i == 15 || $i == 40) {
		$normalizedSource[$field_name] = $special_conv[$src[$field_name]];
		//$this->module->emDebug("Question $i is special: ". $src[$field_name] . " => " . $normalizedSource[$field_name]);
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
	} else {
		$normalizedSource[$field_name] = $src[$field_name];
        }
}
//$this->module->emDebug("SRC: " . $src);
//$this->module->emDebug("NSRC: " . $normalizedSource);
//echo "\n";
//foreach ($src as $k => $v) {
//	if (isset($normalizedSource[$k])) {
//		echo "$k:\t$v => {$normalizedSource[$k]}\n";
//	}
//}



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
//$this->module->emDebug("SOURCE GROUPS: " . $src_groups);

# Calculate our Totals
$result_values = array();
foreach ($src_groups as $name => $data) {
	$raw = array_sum($data);
//	list($std, $pct, $convmsg) = convert($name, $raw, $age, $sex);
//	if ($convmsg) $algorithm_log[] = $convmsg;
	$result_values[$name.'_raw'] = $raw;
}
//$this->module->emDebug("DRF: " . $default_result_fields);
//$this->module->emDebug("RV: " . $result_values);

# Now that we have the raw scores, look up the percentiles and tvals from the raw scores.
# These lookup values are based on age

# These are the percentile and tvalue lookup tables
$perc_lookup_matrix = array(
	4 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(41,45,">=99"),array(40,40,98),array(35,39,96),array(34,34,94),
			array(33,33,93),array(32,32,91),array(31,31,90),
			array(30,30,86),array(29,29,81),array(28,28,79),array(27,27,76),array(26,26,73),array(25,25,65),array(24,24,63),
			array(23,23,56),array(22,22,51),array(21,21,44),array(20,20,39),array(19,19,31),array(18,18,24),array(17,17,15),
			array(16,16,14),array(15,15,9),array(14,14,8),array(13,13,5),array(12,12,4),array(11,11,3),array(9,10,"<=1")
					),
		'adaptability'	=> array(						// Adaptability
			array(40,55,">=99"),array(39,39,98),array(38,38,96),array(37,37,95),array(36,36,94),array(35,35,90),
			array(34,34,86),array(33,33,83),array(32,32,81),array(31,31,73),array(30,30,66),array(29,29,64),
			array(28,28,60),array(27,27,58),array(26,26,48),array(25,25,45),array(24,24,43),array(23,23,38),
			array(22,22,34),array(21,21,28),array(20,20,24),array(19,19,20),array(18,18,16),array(17,17,14),
			array(16,16,10),array(15,15,5),array(13,14,3),array(11,12,"<=1")
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(18,30,">=99"),array(17,17,96),array(16,16,89),array(15,15,81),array(14,14,75),array(13,13,66),
			array(12,12,63),array(11,11,58),array(10,10,45),array(9,9,40),array(8,8,34),array(7,7,25),array(6,6,18)
					),
		'demandingness'	=> array(						// demandingness
			array(33,45,">=99"),array(32,32,98),array(31,31,91),array(30,30,88),array(29,29,84),array(28,28,80),
			array(27,27,76),array(26,26,75),array(25,25,74),array(24,24,70),array(23,23,65),array(22,22,61),
			array(21,21,55),array(20,20,53),array(19,19,48),array(18,18,41),array(17,17,36),array(16,16,35),
			array(15,15,34),array(14,14,25),array(13,13,18),array(12,12,15),array(11,11,9),array(10,10,8),
			array(9,9,3)
					),
		'mood' => array(							// mood
			array(21,25,">=99"),array(20,20,98),array(19,19,96),array(18,18,95),array(17,17,86),array(16,16,85),
			array(15,15,75),array(14,14,71),array(13,13,66),array(12,12,59),array(11,11,49),array(10,10,39),
			array(9,9,28),array(8,8,16),array(7,7,11),array(6,6,4),array(5,5,"<=1")
					),
		'acceptability' => array(						// acceptabilty
			array(26,35,">=99"),array(24,25,98),array(23,23,91),array(22,22,90),array(20,21,81),array(19,19,76),
			array(18,18,75),array(17,17,71),array(16,16,69),array(15,15,66),array(14,14,60),array(13,13,51),
			array(12,12,45),array(11,11,36),array(10,10,33),array(9,9,29),array(8,8,24),array(7,7,9)
					),
		'coompetence' => array(							// competence
			array(44,65,">=99"),array(43,43,96),array(42,42,95),array(41,41,85),array(40,40,81),array(39,39,80),
			array(38,38,79),array(37,37,76),array(36,36,74),array(35,35,73),array(34,34,70),array(33,33,68),
			array(32,32,65),array(31,31,56),array(30,30,55),array(29,29,51),array(28,28,48),array(27,27,43),
			array(26,26,38),array(25,25,34),array(24,24,26),array(23,23,24),array(21,22,23),array(20,20,20),
			array(19,19,15),array(18,18,13),array(17,17,9),array(16,16,6),array(14,15,4),array(13,13,"<=1")
					),
		'isolation' => array(							// isolation
			array(22,30,">=99"),array(21,21,95),array(20,20,94),array(19,19,93),array(18,18,88),array(17,17,76),
			array(16,16,74),array(14,15,68),array(13,13,61),array(12,12,53),array(11,11,48),array(10,10,35),
			array(9,9,33),array(8,8,23),array(7,7,15),array(6,6,9)
					),
		'attachment' => array(							// Attachment
			array(25,35,">=99"),array(24,24,98),array(22,23,94),array(21,21,90),array(20,20,89),array(18,19,79),
			array(16,17,75),array(15,15,74),array(14,14,66),array(13,13,63),array(12,12,55),array(11,11,50),
			array(10,10,39),array(9,9,34),array(8,8,23),array(7,7,15)
					),
		'health' => array(							// Health
			array(18,25,">=99"),array(16,17,98),array(15,15,94),array(14,14,91),array(13,13,80),array(12,12,73),
			array(11,11,56),array(10,10,48),array(9,9,38),array(8,8,30),array(7,7,20),array(6,6,11),array(5,5,9)
					),
		'rolerestriction' => array(						// Role Restriction
			array(31,35,">=99"),array(29,30,96),array(28,28,94),array(27,27,93),array(26,26,89),array(25,25,88),
			array(24,24,85),array(23,23,83),array(22,22,79),array(21,21,64),array(20,20,59),array(19,19,53),
			array(18,18,50),array(17,17,45),array(16,16,43),array(15,15,41),array(14,14,35),array(13,13,25),
			array(12,12,19),array(10,11,9),array(9,9,5),array(7,8,"<=1")
					),
		'depression' => array(							// Depression
			array(32,45,">=99"),array(31,31,96),array(30,30,95),array(29,29,94),array(28,28,91),array(27,27,80),
			array(26,26,79),array(25,25,76),array(24,24,71),array(23,23,69),array(22,22,68),array(21,21,65),
			array(20,20,61),array(19,19,59),array(18,18,51),array(17,17,45),array(16,16,41),array(15,15,35),
			array(14,14,31),array(13,13,29),array(12,12,26),array(11,11,15),array(10,10,9),array(9,9,6)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(29,35,">=99"),array(28,28,96),array(27,27,93),array(26,26,91),array(25,25,90),array(24,24,88),
			array(23,23,83),array(22,22,81),array(21,21,69),array(20,20,68),array(19,19,59),array(18,18,55),
			array(17,17,53),array(16,16,49),array(15,15,45),array(14,14,43),array(13,13,38),array(12,12,31),
			array(11,11,25),array(10,10,20),array(9,9,16),array(8,8,13),array(7,7,5)
					),
		'childdomain' => array(							// Child Domain
			array(162,235,">=99"),array(160,161,98),array(154,159,96),array(153,153,95),array(152,152,94),
			array(151,151,91),array(147,150,89),array(145,146,86),array(144,144,84),array(142,143,83),array(139,141,79),
			array(137,138,78),array(136,136,76),array(135,135,75),array(133,134,74),array(131,132,73),array(130,130,70),
			array(125,129,69),array(121,124,68),array(119,120,66),array(116,118,64),array(114,115,61),array(111,113,59),
			array(109,110,58),array(108,108,56),array(107,107,54),array(105,106,53),array(104,104,51),array(103,103,50),
			array(102,102,48),array(99,101,46),array(97,98,45),array(96,96,43),array(94,95,40),array(92,93,38),array(91,91,35),
			array(90,90,33),array(88,89,30),array(87,87,29),array(86,86,28),array(85,85,25),array(83,84,24),array(82,82,23),
			array(78,81,20),array(77,77,19),array(76,76,18),array(75,75,15),array(73,74,13),array(72,72,11),array(70,71,10),
			array(68,69,9),array(67,67,8),array(60,66,6),array(59,59,4),array(47,58,"<=1")
					),
		'parentdomain' => array(						// Parent Domain
			array(186,270,">=99"),array(175,185,98),array(173,174,96),array(171,172,94),array(169,170,93),
			array(167,168,90),array(165,166,89),array(164,164,86),array(163,163,83),array(162,162,80),
			array(161,161,78),array(155,160,76),array(151,154,74),array(145,150,73),array(144,144,71),
			array(141,143,69),array(139,140,68),array(136,138,66),array(135,135,65),array(134,134,64),
			array(130,133,61),array(127,129,60),array(124,126,59),array(122,123,56),array(121,121,55),
			array(120,120,53),array(118,119,51),array(115,117,50),array(114,114,49),array(112,113,46),
			array(109,111,45),array(108,108,43),array(105,107,40),array(103,104,39),array(101,102,38),
			array(100,100,35),array(99,99,34),array(98,98,31),array(97,97,30),array(96,96,29),array(95,95,28),
			array(94,94,26),array(92,93,24),array(90,91,23),array(89,89,21),array(86,88,20),array(82,85,16),
			array(78,81,15),array(77,77,14),array(76,76,13),array(75,75,11),array(71,74,10),array(69,70,9),
			array(65,68,6),array(63,64,5),array(54,62,"<=1")
					),
		'totalstress' => array(							// Total Stress
			array(324,505,">=99"),array(320,323,98),array(319,319,96),array(318,318,94),array(317,317,93),
			array(316,316,91),array(314,315,90),array(311,313,89),array(308,310,88),array(307,307,86),
			array(306,306,85),array(305,305,84),array(304,304,83),array(301,303,80),array(299,300,79),
			array(294,298,78),array(292,293,75),array(283,291,73),array(274,282,70),array(272,273,69),
			array(255,271,68),array(253,254,65),array(243,252,64),array(240,242,63),array(239,239,61),
			array(238,238,60),array(233,237,56),array(230,232,55),array(229,229,54),array(228,228,53),
			array(223,227,51),array(221,222,50),array(219,220,49),array(217,218,48),array(214,216,46),
			array(213,213,45),array(210,212,44),array(205,209,43),array(199,204,41),array(198,198,40),
			array(193,197,39),array(192,192,38),array(191,191,36),array(188,190,35),array(187,187,34),
			array(183,186,33),array(180,182,29),array(179,179,28),array(178,178,26),array(176,177,25),
			array(175,175,23),array(174,174,21),array(171,173,20),array(165,170,18),array(163,164,16),
			array(161,162,15),array(159,160,14),array(152,158,13),array(149,151,11),array(148,148,10),
			array(143,147,9),array(142,142,8),array(137,141,6),array(136,136,5),array(122,135,4),
			array(101,121,"<=1")
					),
		'lifestress' => array(							// Life Stress
			array(31,79,">=99"),array(30,30,98),array(29,29,96),array(28,28,94),array(27,27,93),array(26,26,89),
			array(24,25,86),array(23,23,84),array(17,22,81),array(16,16,78),array(15,15,75),array(14,14,74),
			array(13,13,73),array(11,12,66),array(10,10,63),array(9,9,53),array(8,8,51),array(7,7,49),array(6,6,46),
			array(4,5,41),array(3,3,33),array(2,2,25),array(0,1,19)
					)
			),
	5 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(36,45,">=99"),array(34,35,98),array(33,33,96),array(32,32,93),array(31,31,91),array(30,30,90),array(29,29,86),
			array(28,28,84),array(27,27,76),array(26,26,73),array(25,25,66),array(24,24,64),array(23,23,59),array(22,22,55),
			array(21,21,51),array(20,20,40),array(19,19,30),array(18,18,23),array(17,17,16),array(16,16,13),array(15,15,9),
			array(13,14,6),array(12,12,3),array(9,11,"<=1")
					),
		'adaptability'	=> array(						// Adaptability
			array(36,55,">=99"),array(35,35,95),array(34,34,89),array(33,33,84),array(32,32,81),array(31,31,78),array(30,30,76),
			array(29,29,70),array(28,28,69),array(27,27,63),array(26,26,58),array(25,25,53),array(24,24,48),array(23,23,43),
			array(22,22,36),array(21,21,28),array(20,20,23),array(18,19,15),array(17,17,13),array(16,16,9),array(15,15,4),
			array(14,14,3),array(11-13,"<=1")
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(20,30,">=99"),array(19,19,98),array(18,18,96),array(17,17,94),array(16,16,91),array(15,15,86),
			array(14,14,83),array(13,13,71),array(12,12,63),array(11,11,58),array(10,10,51),array(9,9,45),
			array(8,8,40),array(7,7,29),array(6,6,18)
					),
		'demandingness'	=> array(						// demandingness
			array(35,45,">=99"),array(34,34,98),array(33,33,96),array(32,32,95),array(31,31,93),array(30,30,88),
			array(29,29,86),array(28,28,84),array(27,27,83),array(26,26,80),array(25,25,71),array(24,24,70),
			array(23,23,69),array(22,22,68),array(21,21,64),array(20,20,58),array(19,19,56),array(18,18,51),
			array(17,17,44),array(16,16,34),array(15,15,29),array(14,14,24),array(13,13,20),array(12,12,14),
			array(11,11,6),array(9,10,3),
					),
		'mood' => array(							// mood
			array(20,25,">=99"),array(19,19,98),array(18,18,96),array(17,17,88),array(16,16,83),array(14,15,76),
			array(13,13,73),array(12,12,69),array(11,11,60),array(10,10,51),array(9,9,35),array(8,8,20),
			array(7,7,16),array(6,6,10),array(5,5,5)
					),
		'acceptability' => array(						// acceptabilty
			array(24,35,">=99"),array(22,23,95),array(21,21,84),array(20,20,83),array(19,19,79),array(18,18,75),
			array(17,17,73),array(16,16,71),array(15,15,66),array(14,14,65),array(13,13,58),array(12,12,51),
			array(11,11,48),array(10,10,40),array(9,9,36),array(8,8,25),array(7,7,14)
					),
		'coompetence' => array(							// competence
			array(44,65,">=99"),array(43,43,96),array(42,42,94),array(41,41,89),array(40,40.83),array(39,39,79),
			array(38,38,78),array(37,37,76),array(36,36,75),array(35,35,74),array(34,34,69),array(33,33,68),
			array(32,32,66),array(31,31,65),array(30,30,63),array(29,29,59),array(28,28,54),array(27,27,51),
			array(26,26,45),array(25,25,43),array(24,24,35),array(23,23,33),array(22,22,25),array(21,21,20),
			array(20,20,14),array(19,19,11),array(18,18,10),array(17,17,9),array(16,16,4),array(13,15,"<=1")
					),
		'isolation' => array(							// isolation
			array(22,30,">=99"),array(21,21,95),array(20,20,93),array(19,19,91),array(18,18,86),array(17,17,79),
			array(16,16,76),array(15,15,66),array(14,14,63),array(13,13,53),array(12,12,45),array(11,11,40),
			array(10,10,35),array(9,9,28),array(8,8,19),array(7,7,14),array(6,6,6)
					),
		'attachment' => array(							// Attachment
			array(22,35,">=99"),array(21,21,91),array(20,20,90),array(18,19,83),array(16,17,79),array(15,15,71),
			array(14,14,69),array(13,13,60),array(12,12,53),array(11,11,46),array(10,10,40),array(9,9,34),
			array(8,8,24),array(7,7,13)
					),
		'health' => array(							// Health
			array(20,25,">=99"),array(18,19,94),array(17,17,90),array(16,16,86),array(15,15,81),array(14,14,78),
			array(13,13,70),array(12,12,60),array(11,11,49),array(10,10,41),array(9,9,26),array(8,8,18),
			array(7,7,9),array(6,6,5),array(5,5,3),
					),
		'rolerestriction' => array(						// Role Restriction
			array(28,35,">=99"),array(26,27,98),array(25,25,96),array(24,24,93),array(23,23,86),array(22,22,83),
			array(21,21,74),array(20,20,73),array(19,19,61),array(18,18,56),array(17,17,45),array(16,16,35),
			array(15,15,31),array(14,14,28),array(13,13,15),array(12,12,13),array(11,11,6),array(10,10,3),array(7,9,"<=1")
					),
		'depression' => array(							// Depression
			array(35,45,">=99"),array(34,34,98),array(33,33,96),array(32,32,95),array(31,31,93),array(30,30,91),
			array(29,29,88),array(28,28,86),array(27,27,78),array(26,26,76),array(24,25,71),array(23,23,68),
			array(22,22,64),array(21,21,63),array(20,20,58),array(19,19,53),array(18,18,50),array(17,17,41),
			array(16,16,34),array(15,15,29),array(14,14,24),array(13,13,21),array(12,12,14),array(11,11,9),
			array(10,10,8),array(9,9,6),
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(28,35,">=99"),array(27,27,94),array(26,26,93),array(24,25,89),array(23,23,81),array(22,22,79),
			array(21,21,66),array(20,20,64),array(19,19,55),array(18,18,54),array(17,17,48),array(16,16,45),
			array(15,15,43),array(14,14,39),array(13,13,30),array(12,12,23),array(11,11,18),array(10,10,14),
			array(9,9,13),array(8,8,6),array(7,7,3)
					),
		'childdomain' => array(							// Child Domain
			array(158,235,">=99"),array(153,157,98),array(150,152,95),array(149,149,94),array(147,148,91),array(146,146,89),
			array(145,145,88),array(144,144,86),array(143,143,84),array(142,142,83),array(141,141,80),array(140,140,78),
			array(134,139,75),array(131,133,74),array(120,130,73),array(115,119,71),array(114,114,70),array(113,113,69),
			array(112,112,68),array(111,111,66),array(110,110,65),array(108,109,64),array(107,107,63),array(106,106,61),
			array(105,105,59),array(103,104,56),array(101,102,55),array(100,100,54),array(99,99,51),array(97,98,49),
			array(96,96,48),array(95,95,46),array(94,94,45),array(93,93,43),array(92,92,40),array(88,91,38),array(87,87,35),
			array(86,86,34),array(84,85,31),array(83,83,30),array(82,82,29),array(81,81,25),array(79,80,23),array(78,78,21),
			array(77,77,20),array(75,76,18),array(73,74,15),array(70,72,13),array(69,69,9),array(68,68,8),array(64,67,6),
			array(59,63,4),array(58,58,3),array(47,57,"<=1"),
					),
		'parentdomain' => array(						// Parent Domain
			array(176,270,">=99"),array(175,175,98),array(174,174,96),array(173,173,94),array(172,172,93),
			array(171,171,91),array(170,170,90),array(169,169,89),array(168,168.85),array(166,167,84),array(165,165,83),
			array(164,164,80),array(162,163,79),array(161,161,78),array(154,160,75),array(151,153,73),array(150,150,71),
			array(149,149,70),array(139,148,69),array(137,138,68),array(133,136,66),array(129,132,65),array(128,128,63),
			array(127,127,61),array(126,126,60),array(123,125,59),array(122,122,58),array(120,121,55),array(119,119,53),
			array(117,118,50),array(114,116,48),array(113,113,45),array(112,112,44),array(111,111,43),array(110,110,41),
			array(109,109,38),array(106,108,36),array(104,105,35),array(103,103,34),array(101,102,33),array(100,100,30),
			array(99,99,29),array(97,98,26),array(96,96,25),array(93,95,24),array(88,92,21),array(87,87,18),array(86,86,15),
			array(85,85,11),array(83,84,10),array(78,82,9),array(77,77,6),array(72,76,5),array(70,71,4),array(54,69,"<=1")
					),
		'totalstress' => array(							// Total Stress
			array(329,505,">=99"),array(327,328,98),array(325,326,96),array(320,324,95),array(319,319,93),
			array(317,318,91),array(315,316,90),array(313,314,85),array(309,312,83),array(308,308,81),array(307,307,80),
			array(302,306,79),array(294,301,78),array(292,293,76),array(288,291,75),array(281,287,74),array(266,280,73),
			array(262,265,71),array(256,261,70),array(248,255,69),array(242,247,68),array(240,241,66),array(237,239,65),
			array(234,236,64),array(229,233,63),array(227,228,60),array(224,226,59),array(223,223,58),array(221,222,54),
			array(214,220,53),array(213,213,51),array(212,212,49),array(205-211,48),array(204,204,46),array(203,203,45),
			array(201,202,43),array(200,200,40),array(196,199,39),array(194,195,36),array(193,193,35),array(192,192,34),
			array(191,191,33),array(190,190,31),array(188,189,30),array(184,187,28),array(181,183,26),array(180,180,25),
			array(179,179,24),array(171,178,21),array(170,170,20),array(169,169,19),array(165,168,16),array(159,164,14),
			array(158,158,11),array(156,157,9),array(155,155,6),array(147,154,5),array(141,146,4),array(131,140,3),
			array(101,130,"<=1")
					),
		'lifestress' => array(							// Life Stress
			array(39,79,">=99"),array(37,38,95),array(36,36,94),array(35,35,93),array(32,34,91),array(30,31,89),
			array(29,29,86),array(28,28,85),array(27,27,84),array(25,26,83),array(24,24,81),array(23,23,80),
			array(22,22,79),array(21,21,76),array(20,20,74),array(19,19,73),array(16,18,69),array(15,15,68),
			array(14,14,66),array(13,13,61),array(12,12,56),array(11,11,53),array(10,10,48),array(9,9,44),
			array(8,8,41),array(7,7,39),array(6,6,31),array(5,5,28),array(4,4,26),array(3,3,19),array(2,2,16),
			array(1,1,14),array(0,0,11)
					)
			),
	6 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(34,45,">=99"),array(32,33,94),array(31,31,93),array(30,30,88),array(29,29,83),array(28,28,79),
			array(27,27,69),array(26,26,68),array(25,25,62),array(24,24,57),array(23,23,49),array(22,22,43),
			array(21,21,41),array(20,20,36),array(19,19,31),array(18,18,25),array(17,17,19),array(16,16,15),
			array(15,15,9),array(14,14,6),array(12,13,5),array(9,11,"<=1")
					),
		'adaptability'	=> array(						// Adaptability
			array(41,55,">=99"),array(40,40,98),array(39,39,96),array(37,38,94),array(36,36,91),array(35,35,86),
			array(34,34,84),array(33,33,81),array(32,32,77),array(31,31,69),array(30,30,68),array(29,29,67),
			array(28,28,62),array(27,27,57),array(26,26,52),array(25,25,49),array(24,24,41),array(23,23,36),
			array(22,22,32),array(21,21,30),array(20,20,21),array(19,19,17),array(18,18,12),array(17,17,11),
			array(16,16,7),array(13,15,5),array(11,12,4)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(20,30,">=99"),array(18,19,98),array(16,17,90),array(15,15,77),array(14,14,72),array(13,13,65),
			array(12,12,60),array(11,11,54),array(10,10,44),array(9,9,35),array(8,8,30),array(7,7,22),array(6,6,12)
					),
		'demandingness'	=> array(						// demandingness
			array(34,45,">=99"),array(32,33,98),array(31,31,96),array(30,30,95),array(29,29,91),array(28,28,88),
			array(27,27,83),array(26,26,77),array(25,25,70),array(24,24,67),array(23,23,63),array(22,22,58),
			array(21,21,54),array(20,20,51),array(19,19,47),array(18,18,41),array(17,17,37),array(16,16,23),
			array(15,15,20),array(14,14,19),array(13,13,15),array(12,12,12),array(11,11,5),array(10,10,4),array(9,9,2)
					),
		'mood' => array(							// mood
			array(22,25,">=99"),array(20,21,98),array(19,19,95),array(18,18,93),array(16,17,88),array(15,15,81),
			array(14,14,79),array(13,13,65),array(12,12,60),array(11,11,47),array(10,10,38),array(9,9,28),
			array(8,8,20),array(7,7,14),array(6,6,10),array(5,5,7)
					),
		'acceptability' => array(						// acceptabilty
			array(27,35,">=99"),array(25,26,98),array(24,24,96),array(23,23,94),array(22,22,93),array(20,21,80),
			array(19,19,75),array(16,18,72),array(15,15,65),array(14,14,63),array(13,13,51),array(12,12,49),
			array(11,11,37),array(10,10,31),array(9,9,22),array(8,8,21),array(7,7,16)
					),
		'coompetence' => array(							// competence
			array(46,65,">=99"),array(45,45,95),array(44,44,93),array(43,43,90),array(42,42,89),array(41,41,88),
			array(40,40,83),array(39,39,80),array(36,38,72),array(35,35,69),array(32,34,67),array(31,31,63),
			array(30,30,56),array(29,29,51),array(28,28,47),array(27,27,42),array(26,26,38),array(25,25,32),
			array(24,24,28),array(23,23,23),array(22,22,21),array(21,21,14),array(20,20,11),array(19,19,7),
			array(18,18,4),array(17,17,2),array(13,16,"<=1")
					),
		'isolation' => array(							// isolation
			array(24,30,">=99"),array(22,23,98),array(21,21,93),array(20,20,89),array(19,19,83),array(18,18,80),
			array(17,17,78),array(16,16,73),array(15,15,62),array(14,14,58),array(13,13,53),array(12,12,44),
			array(11,11,35),array(10,10,27),array(9,9,17),array(8,8,14),array(7,7,6),array(6,6,4)
					),
		'attachment' => array(							// Attachment
			array(26,35,">=99"),array(25,25,96),array(24,24,93),array(22,23,89),array(21,21,85),array(20,20,83),
			array(19,19,79),array(18,18,75),array(16,17,69),array(15,15,68),array(14,14,65),array(13,13,57),
			array(12,12,52),array(11,11,46),array(10,10,36),array(9,9,31),array(8,8,22),array(7,7,11)
					),
		'health' => array(							// Health
			array(20,25,">=99"),array(19,19,96),array(18,18,95),array(17,17,93),array(16,16,89),array(15,15,83),
			array(14,14,78),array(13,13,72),array(12,12,65),array(11,11,53),array(10,10,46),array(9,9,31),
			array(8,8,25),array(7,7,19),array(6,6,14),array(5,5,5)
					),
		'rolerestriction' => array(						// Role Restriction
			array(29,35,">=99"),array(28,28,98),array(27,27,95),array(26,26,93),array(24,25,91),array(23,23,89),
			array(22,22,83),array(21,21,75),array(20,20,65),array(19,19,52),array(18,18,48),array(17,17,40),
			array(16,16,37),array(15,15,27),array(14,14,22),array(13,13,16),array(12,12,14),array(11,11,9),
			array(9,10,4),array(8,8,2),array(7,7,"<=1")
					),
		'depression' => array(							// Depression
			array(37,45,">=99"),array(35,36,98),array(34,34,95),array(32,33,94),array(31,31,91),array(30,30,89),
			array(29,29,83),array(28,28,81),array(27,27,74),array(26,26,68),array(24,25,65),array(23,23,64),
			array(22,22,63),array(21,21,60),array(20,20,58),array(19,19,53),array(18,18,51),array(17,17,41),
			array(16,16,35),array(15,15,27),array(14,14,22),array(13,13,20),array(12,12,16),array(11,11,10),
			array(10,10,7),array(9,9,6),
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(31,35,">=99"),array(29,30,98),array(28,28,95),array(27,27,93),array(26,26,90),array(25,25,84),
			array(23,24,80),array(22,22,78),array(21,21,68),array(20,20,65),array(19,19,58),array(18,18,53),
			array(17,17,48),array(16,16,44),array(15,15,40),array(14,14,37),array(13,13,31),array(12,12,26),
			array(11,11,21),array(10,10,16),array(9,9,14),array(8,8,10),array(7,7,5)
					),
		'childdomain' => array(							// Child Domain
			array(155,235,">=99"),array(149,154,95),array(147,148,93),array(145,146,91),array(143,144,88),
			array(142,142,86),array(141,141,81),array(140,140,80),array(139,139,79),array(137,138,77),
			array(136,136,75),array(135,135,74),array(134,134,73),array(131,133,72),array(130,130,69),
			array(129,129,68),array(128,128,65),array(123,127,64),array(121,122,63),array(120,120,62),
			array(119,119,59),array(118,118,58),array(115,117,57),array(109,114,56),array(108,108,54),
			array(106,107,53),array(104,105,51),array(103,103,49),array(101,102,48),array(100,100,47),
			array(99,99,46),array(97,98,42),array(96,96,41),array(95,95,40),array(94,94,37),array(93,93,35),
			array(92,92,32),array(89,91,31),array(87,88,30),array(85,86,26),array(83,84,23),array(81,82,22),
			array(77,80,20),array(76,76,17),array(75,75,15),array(73,74,14),array(72,72,13),array(71,71,12),
			array(69,70,11),array(67,68,9),array(65,66,6),array(61,64,5),array(60,60,4),array(47,59,"<=1"),
					),
		'parentdomain' => array(						// Parent Domain
			array(211,270,">=99"),array(195,210,98),array(186,194,96),array(177,185,95),array(176,176,94),
			array(175,175,93),array(171,174,91),array(170,170,89),array(169,169,88),array(167,168,84),
			array(166,166,80),array(164,165,78),array(163,163,74),array(160,162,72),array(143,159,69),
			array(142,142,67),array(141,141,66),array(140,140,65),array(139,139,64),array(136,138,63),
			array(133,135,62),array(130,132,60),array(128,129,59),array(126,127,57),array(125,125,56),
			array(123,124,54),array(122,122,53),array(121,121,52),array(120,120,51),array(118,119,49),
			array(117,117,48),array(115,116,47),array(113,114,46),array(112,112,41),array(111,111,40),
			array(107,110,38),array(106,106,36),array(105,105,33),array(104,104,32),array(101,103,31),
			array(100,100,27),array(98,99,25),array(97,97,22),array(95,96,21),array(94,94,20),array(90,93,17),
			array(85,89,16),array(84,84,15),array(83,83,14),array(82,82,13),array(81,81,12),array(80,80,11),
			array(79,79,7),array(78,78,6),array(72,77,4),array(68,71,2),array(54,67,"<=1")
					),
		'totalstress' => array(							// Total Stress
			array(361,505,">=99"),array(322,360,98),array(319,321,96),array(318,318,95),array(315,317,91),
			array(314,314,90),array(313,313,89),array(312,312,88),array(311,311,86),array(310,310,81),
			array(308,309,78),array(306,307,77),array(303,305,74),array(302,302,73),array(293,301,72),
			array(278,292,70),array(273,277,69),array(270,272,68),array(267,269,67),array(264,266,65),
			array(262,263,64),array(260,261,63),array(255,259,62),array(244,254,59),array(243,243,58),
			array(238,242,57),array(234,237,56),array(230,233,54),array(225,229,53),array(224,224,52),
			array(222,223,51),array(221,221,49),array(219,220,48),array(218,218,47),array(212,217,44),
			array(210,211,43),array(207,209,42),array(204,206,41),array(202,203,40),array(201,201,38),
			array(199,200,37),array(196,198,36),array(194,195,35),array(193,193,31),array(192,192,30),
			array(191,191,28),array(184,190,27),array(183,183,26),array(182,182,25),array(179,181,23),
			array(178,178,22),array(177,177,21),array(176,176,20),array(175,175,19),array(167,174,17),
			array(165,166,15),array(164,164,14),array(163,163,12),array(159,162,11),array(157,158,10),
			array(146,156,6),array(141,145,5),array(139,140,4),array(130,138,2),array(101,129,"<=1")
					),
		'lifestress' => array(							// Life Stress
			array(38,79,">=99"),array(36,37,98),array(34,35,96),array(33,33,95),array(31,32,94),array(30,30,90),
			array(29,29,88),array(28,28,86),array(27,27,85),array(26,26,84),array(25,25,83),array(24,24,81),
			array(22,23,80),array(21,21,79),array(20,20,77),array(19,19,75),array(18,18,72),array(16,17,70),
			array(15,15,65),array(14,14,64),array(13,13,59),array(12,12,57),array(11,11,53),array(10,10,51),
			array(9,9,48),array(8,8,47),array(6,7,43),array(5,5,36),array(4,4,35),array(3,3,23),array(2,2,17),array(0,1,16)
					)
			),
	7 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(39,45,">=99"),array(38,38,98),array(36,37,95),array(34,35,93),array(32,33,92),array(31,31,89),
			array(30,30,82),array(29,29,81),array(28,28,78),array(27,27,76),array(26,26,73),array(25,25,70),
			array(24,24,67),array(23,23,64),array(22,22,63),array(21,21,54),array(20,20,47),array(19,19,42),
			array(18,18,34),array(17,17,29),array(16,16,22),array(15,15,16),array(14,14,10),array(13,13,7),
			array(12,12,2),array(9,11,"<=1")
					),
		'adaptability'	=> array(						// Adaptability
			array(44,55,">=99"),array(43,43,98),array(38,42,96),array(37,37,94),array(36,36,90),array(35,35,87),
			array(34,34,86),array(33,33,84),array(32,32,82),array(30,31,81),array(29,29,78),array(28,28,76),
			array(27,27,75),array(26,26,73),array(25,25,69),array(24,24,63),array(23,23,58),array(22,22,48),
			array(21,21,42),array(20,20,33),array(19,19,29),array(18,18,24),array(17,17,18),array(16,16,12),
			array(15,15,11),array(14,14,7),array(13,13,6),array(11,12,"<=1")
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(23,30,">=99"),array(19,22,96),array(18,18,94),array(17,17,92),array(16,16,88),array(15,15,83),
			array(14,14,81),array(13,13,76),array(12,12,64),array(11,11,54),array(10,10,41),array(9,9,35),
			array(8,8,28),array(7,7,20),array(6,6,14)
					),
		'demandingness'	=> array(						// demandingness
			array(38,45,">=99"),array(37,37,98),array(36,36,96),array(33,35,95),array(31,32,94),array(30,30,92),
			array(29,29,89),array(28,28,87),array(27,27,86),array(25,26,82),array(24,24,78),array(23,23,75),
			array(22,22,71),array(21,21,64),array(20,20,60),array(19,19,57),array(18,18,51),array(17,17,48),
			array(16,16,39),array(15,15,30),array(14,14,25),array(13,13,20),array(12,12,17),array(11,11,11),
			array(10,10,6)
					),
		'mood' => array(							// mood
			array(24,25,">=99"),array(23,23,98),array(22,22,96),array(21,21,95),array(19,20,93),array(18,18,90),
			array(16,17,88),array(15,15,83),array(14,14,78),array(13,13,72),array(12,12,67),array(11,11,59),
			array(10,10,42),array(9,9,34),array(8,8,25),array(7,7,16),array(6,6,6),array(5,5,"<=1")
					),
		'acceptability' => array(						// acceptabilty
			array(26,35,">=99"),array(25,25,98),array(24,24,96),array(23,23,94),array(22,22,93),array(21,21,89),
			array(20,20,86),array(19,19,83),array(18,18,81),array(17,17,80),array(16,16,77),array(15,15,73),
			array(14,14,69),array(13,13,53),array(12,12,46),array(11,11,42),array(10,10,40),array(9,9,30),
			array(8,8,25),array(7,7,16)
					),
		'coompetence' => array(							// competence
			array(52,65,">=99"),array(45,51,98),array(44,44,96),array(43,43,94),array(40,42,92),array(39,39,89),
			array(36,38,86),array(35,35,83),array(34,34,80),array(32,33,78),array(31,31,73),array(30,30,69),
			array(29,29,61),array(28,28,52),array(27,27,49),array(26,26,41),array(25,25,39),array(24,24,34),
			array(23,23,28),array(22,22,20),array(21,21,19),array(20,20,14),array(19,19,12),array(18,18,10),
			array(17,17,7),array(16,16,5),array(15,15,4),array(14,14,2),array(13,13,"<=1")
					),
		'isolation' => array(							// isolation
			array(22,30,">=99"),array(21,21,95),array(20,20,93),array(19,19,88),array(18,18,83),array(17,17,80),
			array(16,16,76),array(15,15,71),array(14,14,64),array(13,13,59),array(12,12,52),array(11,11,39),
			array(10,10,30),array(9,9,23),array(8,8,17),array(7,7,16),array(6,6,7)
					),
		'attachment' => array(							// Attachment
			array(27,35,">=99"),array(26,26,98),array(23,25,96),array(22,22,95),array(21,21,93),array(18,20,89),
			array(17,17,84),array(16,16,83),array(15,15,81),array(14,14,77),array(13,13,64),array(12,12,55),
			array(11,11,49),array(10,10,45),array(9,9,41),array(8,8,22),array(7,7,13)
					),
		'health' => array(							// Health
			array(21,25,">=99"),array(19,20,98),array(18,18,96),array(17,17,93),array(16,16,92),array(14,15,88),
			array(13,13,87),array(12,12,83),array(11,11,67),array(10,10,59),array(9,9,41),array(8,8,28),array(7,7,20),
			array(6,6,13),array(5,5,10)
					),
		'rolerestriction' => array(						// Role Restriction
			array(26,35,">=99"),array(25,25,98),array(24,24,95),array(23,23,93),array(22,22,90),array(21,21,84),
			array(20,20,80),array(19,19,76),array(18,18,66),array(17,17,60),array(16,16,57),array(15,15,41),
			array(14,14,34),array(13,13,29),array(12,12,23),array(11,11,18),array(10,10,13),array(9,9,10),
			array(7,8,4)
					),
		'depression' => array(							// Depression
			array(39,45,">=99"),array(33,38,98),array(32,32,96),array(30,31,94),array(29,29,93),array(28,28,92),
			array(27,27,90),array(26,26,88),array(25,25,82),array(24,24,78),array(23,23,76),array(22,22,75),
			array(21,21,71),array(20,20,69),array(19,19,66),array(18,18,61),array(17,17,46),array(16,16,42),
			array(15,15,36),array(14,14,34),array(13,13,30),array(12,12,25),array(11,11,23),array(10,10,18),
			array(9,9,11)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(32,35,">=99"),array(28,31,95),array(26,27,90),array(25,25,88),array(24,24,87),array(23,23,84),
			array(22,22,80),array(21,21,75),array(20,20,72),array(19,19,69),array(18,18,66),array(17,17,60),
			array(16,16,57),array(15,15,55),array(14,14,51),array(13,13,37),array(12,12,30),array(11,11,28),
			array(10,10,24),array(9,9,22),array(8,8,16),array(7,7,12)
					),
		'childdomain' => array(							// Child Domain
			array(191,235,">=99"),array(181,190,98),array(168,180,96),array(154,167,95),array(153,153,94),
			array(149,152,93),array(147,148,92),array(146,146,90),array(145,145,89),array(141,144,87),
			array(140,140,84),array(138,139,83),array(137,137,82),array(136,136,81),array(129,135,80),
			array(127,128,77),array(124,126,76),array(120,123,75),array(115,119,72),array(112,114,71),
			array(111,111,69),array(110,110,65),array(103,109,64),array(102,102,63),array(100,101,59),
			array(99,99,58),array(97,98,53),array(96,96,51),array(95,95,49),array(94,94,47),array(93,93,43),
			array(92,92,40),array(91,91,37),array(89,90,34),array(86,88,31),array(84,85,30),array(82,83,28),
			array(80,81,27),array(79,79,25),array(76,78,24),array(74,75,22),array(72,73,20),array(69,71,19),
			array(68,68,17),array(67,67,16),array(66,66,14),array(65,65,10),array(64,64,8),array(62,63,7),array(61,61,6),
			array(56,60,5),array(55,55,2),array(47,54,"<=1")
					),
		'parentdomain' => array(						// Parent Domain
			array(192,270,">=99"),array(186,191,98),array(178,185,96),array(171,177,93),array(168,170,92),
			array(166,167,90),array(164,165,87),array(160,163,86),array(153,159,84),array(145,152,83),
			array(144,144,82),array(143,143,80),array(142,142,77),array(139,141,76),array(135,138,75),
			array(131,134,73),array(126,130,72),array(125,125,71),array(124,124,70),array(123,123,67),
			array(122,122,66),array(118,121,64),array(116,117,63),array(115,115,61),array(114,114,58),
			array(113,113,55),array(112,112,53),array(111,111,52),array(110,110,48),array(109,109,46),
			array(107,108,41),array(104,105,39),array(101,103,37),array(99,100,36),array(97,98,35),array(94,96,31),
			array(92,93,30),array(91,91,25),array(86,90,24),array(85,85,23),array(83,84,22),array(81,82,19),
			array(80,80,18),array(79,79,16),array(78,78,14),array(77,77,12),array(72,76,11),array(71,71,8),
			array(70,70,7),array(68,69,6),array(62,67,5),array(54,61,"<=1")
					),
		'totalstress' => array(							// Total Stress
			array(383,505,">=99"),array(359,382,98),array(327,358,96),array(323,326,95),array(321,322,94),
			array(316,320,93),array(315,315,92),array(310,314,90),array(307,309,89),array(305,306,88),
			array(304,304,87),array(302,303,86),array(297,301,84),array(268,296,82),array(262,267,81),
			array(260,261,78),array(255,259,77),array(253,254,73),array(246,252,72),array(243,245,71),
			array(237,242,70),array(233,236,67),array(230,232,65),array(227,229,63),array(226,226,61),
			array(225,225,60),array(215,224,59),array(214,214,57),array(211,213,54),array(210,210,53),
			array(209,209,51),array(208,208,49),array(205,207,48),array(204,204,46),array(202,203,45),
			array(199,201,42),array(195,198,41),array(194,194,39),array(190,193,37),array(189,189,36),
			array(187,188,35),array(185,186,34),array(181,184,33),array(179,180,31),array(177,178,29),
			array(174,176,27),array(172,173,25),array(171,171,24),array(169,170,23),array(167,168,22),
			array(164,166,20),array(160,163,19),array(154,159,18),array(152,153,17),array(148,151,16),
			array(147,147,14),array(146,146,13),array(144,145,12),array(138,143,11),array(133,137,10),
			array(132,132,8),array(130,131,7),array(128,129,6),array(127,127,5),array(125,126,4),array(118,124,2),
			array(101,117,"<=1")
					),
		'lifestress' => array(							// Life Stress
			array(34,79,">=99"),array(33,33,98),array(32,32,96),array(30,31,95),array(23,29,94),array(22,22,90),
			array(20,21,87),array(18,19,84),array(17,17,83),array(16,16,80),array(12,15,75),array(11,11,72),
			array(10,10,69),array(9,9,66),array(8,8,64),array(7,7,63),array(6,6,58),array(5,5,52),array(4,4,49),
			array(3,3,37),array(2,2,36),array(0,1,34)
					)
			),
	8 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(35,45,">=99"),array(34,34,96),array(33,33,95),array(32,32,93),array(30,31,90),array(29,29,88),
			array(28,28,83),array(27,27,80),array(26,26,78),array(25,25,77),array(24,24,71),array(23,23,65),
			array(22,22,59),array(20,21,52),array(19,19,49),array(18,18,41),array(17,17,37),array(16,16,30),
			array(15,15,24),array(14,14,18),array(13,13,14),array(12,12,8),array(11,11,7),array(10,10,5),array(9,9,4)
					),
		'adaptability'	=> array(						// Adaptability
			array(38,55,">=99"),array(37,37,96),array(35,36,95),array(34,34,94),array(33,33,90),array(32,32,88),
			array(31,31,86),array(30,30,84),array(28,29,82),array(27,27,81),array(26,26,77),array(25,25,76),
			array(24,24,72),array(22,23,70),array(21,21,65),array(20,20,54),array(19,19,45),array(18,18,39),
			array(17,17,33),array(16,16,23),array(15,15,19),array(14,14,13),array(13,13,11),array(12,12,5),array(11,11,4)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(21,30,">=99"),array(18,20,98),array(17,17,96),array(16,16,95),array(15,15,86),array(14,14,81),
			array(13,13,75),array(12,12,66),array(11,11,59),array(10,10,46),array(9,9,39),array(8,8,33),array(7,7,23),
			array(6,6,16)
					),
		'demandingness'	=> array(						// demandingness
			array(35,45,">=99"),array(34,34,98),array(32,33,96),array(30,31,95),array(28,29,93),array(27,27,88),
			array(25,26,86),array(23,24,80),array(22,22,73),array(21,21,69),array(20,20,61),array(19,19,57),
			array(18,18,51),array(17,17,47),array(16,16,46),array(15,15,36),array(14,14,34),array(13,13,25),
			array(12,12,22),array(11,11,12),array(10,10,10),array(9,9,4)
					),
		'mood' => array(							// mood
			array(20,25,">=99"),array(19,19,98),array(18,18,96),array(17,17,95),array(16,16,92),array(15,15,88),
			array(14,14,84),array(13,13,77),array(12,12,72),array(11,11,66),array(10,10,51),array(9,9,45),
			array(8,8,34),array(7,7,18),array(6,6,11),array(5,5,6)
					),
		'acceptability' => array(						// acceptabilty
			array(27,35,">=99"),array(26,26,98),array(25,25,96),array(24,24,94),array(23,23,92),array(22,22,90),
			array(20,21,87),array(18,19,82),array(16,17,80),array(15,15,76),array(14,14,75),array(13,13,67),
			array(12,12,63),array(11,11,57),array(10,10,48),array(9,9,45),array(8,8,28),array(7,7,13)
					),
		'coompetence' => array(							// competence
			array(52,65,">=99"),array(47,51,98),array(46,46,96),array(44,45,95),array(42,43,93),array(41,41,88),
			array(40,40,86),array(38,39,83),array(35,37,81),array(34,34,80),array(32,33,78),array(31,31,73),
			array(30,30,70),array(29,29,64),array(28,28,58),array(27,27,51),array(26,26,48),array(25,25,43),
			array(24,24,37),array(23,23,34),array(22,22,29),array(21,21,24),array(20,20,20),array(19,19,12),
			array(18,18,6),array(17,17,2),array(13,16,"<=1")
					),
		'isolation' => array(							// isolation
			array(25,30,">=99"),array(20,24,98),array(19,19,94),array(18,18,92),array(17,17,84),array(16,16,81),
			array(15,15,72),array(14,14,69),array(13,13,63),array(12,12,51),array(11,11,40),array(10,10,35),
			array(9,9,31),array(8,8,23),array(7,7,14),array(6,6,8)
					),
		'attachment' => array(							// Attachment
			array(24,35,">=99"),array(22,23,98),array(21,21,94),array(20,20,93),array(19,19,84),array(18,18,82),
			array(17,17,81),array(16,16,78),array(15,15,76),array(14,14,72),array(13,13,65),array(12,12,63),
			array(11,11,54),array(10,10,49),array(9,9,46),array(8,8,37),array(7,7,10)
					),
		'health' => array(							// Health
			array(22,25,">=99"),array(20,21,96),array(18,19,95),array(17,17,93),array(16,16,92),array(15,15,86),
			array(14,14,81),array(13,13,78),array(12,12,72),array(11,11,67),array(10,10,61),array(9,9,49),array(8,8,43),
			array(7,7,27),array(6,6,19),array(5,5,10)
					),
		'rolerestriction' => array(						// Role Restriction
			array(26,35,">=99"),array(24,25,95),array(23,23,94),array(22,22,93),array(21,21,82),array(20,20,81),
			array(19,19,76),array(18,18,67),array(17,17,54),array(16,16,48),array(15,15,42),array(14,14,35),
			array(13,13,31),array(12,12,25),array(11,11,17),array(10,10,12),array(9,9,5),array(7,8,"<=1")
					),
		'depression' => array(							// Depression
			array(35,45,">=99"),array(30,34,98),array(29,29,96),array(28,28,95),array(26,27,88),array(25,25,84),
			array(24,24,82),array(20,23,80),array(19,19,75),array(18,18,69),array(17,17,58),array(16,16,51),
			array(15,15,46),array(14,14,40),array(13,13,36),array(12,12,24),array(11,11,18),array(10,10,13),
			array(9,9,8)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(32,35,">=99"),array(28,31,98),array(27,27,95),array(26,26,94),array(25,25,90),array(24,24,87),
			array(22,23,83),array(21,21,73),array(20,20,71),array(19,19,66),array(18,18,63),array(17,17,58),
			array(16,16,52),array(15,15,46),array(14,14,37),array(13,13,34),array(12,12,24),array(11,11,19),
			array(10,10,12),array(8,9,8),array(7,7,6)
					),
		'childdomain' => array(							// Child Domain
			array(173,235,">=99"),array(158,172,98),array(153,157,96),array(149,152,95),array(148,148,94),
			array(147,147,93),array(146,146,92),array(143,145,89),array(140,142,88),array(139,139,87),
			array(137,138,86),array(134,136,83),array(131,133,82),array(122,130,81),array(115,121,80),
			array(113,114,78),array(112,112,77),array(110,111,75),array(108,109,73),array(104,107,71),
			array(103,103,70),array(101,102,67),array(100,100,66),array(99,99,64),array(98,98,63),array(97,97,60),
			array(95,96,59),array(93,94,58),array(92,92,54),array(91,91,53),array(89,90,52),array(88,88,49),
			array(87,87,48),array(86,86,47),array(85,85,46),array(84,84,45),array(83,83,40),array(82,82,37),
			array(79,81,36),array(78,78,34),array(75,77,29),array(70,74,27),array(67,69,25),array(66,66,20),
			array(65,65,17),array(64,64,16),array(63,63,13),array(62,62,8),array(61,61,7),array(60,60,5),array(58,59,2),
			array(47,57,"<=1")
					),
		'parentdomain' => array(						// Parent Domain
			array(178,270,">=99"),array(177,177,98),array(172,176,96),array(170,171,95),array(168,169,93),
			array(167,167,90),array(166,166,88),array(164,165,87),array(161,163,86),array(159,160,83),
			array(152,158,82),array(141,151,81),array(138,140,80),array(130,137,78),array(127,129,77),
			array(126,126,76),array(125,125,75),array(123,124,73),array(122,122,72),array(121,121,71),
			array(120,120,70),array(119,119,69),array(116,118,66),array(115,115,64),array(114,114,61),
			array(113,113,58),array(112,112,57),array(110,111,54),array(109,109,51),array(107,108,47),
			array(106,106,45),array(105,105,42),array(104,104,40),array(103,103,37),array(96,102,35),
			array(95,95,34),array(94,94,33),array(93,93,25),array(89,92,22),array(88,88,20),array(83,87,19),
			array(82,82,18),array(81,81,14),array(79,80,12),array(76,78,10),array(73,75,7),array(71,72,6),
			array(68,70,5),array(67,67,4),array(66,66,2),array(54,65,"<=1")
					),
		'totalstress' => array(							// Total Stress
			array(340,505,">=99"),array(317,339,98),array(316,316,95),array(315,315,94),array(314,314,93),
			array(311,313,92),array(310,310,90),array(305,309,87),array(304,304,86),array(299,303,84),
			array(298,298,83),array(278,297,82),array(256,277,81),array(253,255,80),array(248,252,78),
			array(246,247,77),array(242,245,76),array(235,241,75),array(226,234,73),array(223,225,72),
			array(218,222,70),array(217,217,69),array(214,216,66),array(213,213,65),array(212,212,64),
			array(210,211,63),array(209,209,60),array(207,208,59),array(204,206,58),array(203,203,57),
			array(200,202,55),array(197,199,53),array(195,196,52),array(194,194,51),array(193,193,48),
			array(189,192,47),array(188,188,46),array(187,187,45),array(186,186,43),array(184,185,42),
			array(183,183,41),array(180,182,40),array(179,179,39),array(178,178,37),array(177,177,36),
			array(175,176,33),array(173,174,29),array(172,172,28),array(171,171,23),array(169,170,22),
			array(162,168,20),array(161,161,19),array(160,160,18),array(155,159,17),array(148,154,14),
			array(145,147,13),array(143,144,12),array(142,142,11),array(139,141,10),array(138,138,7),
			array(131,137,6),array(129,130,4),array(126,128,2),array(101,125,"<=1")
					),
		'lifestress' => array(							// Life Stress
			array(36,79,">=99"),array(33,35,98),array(30,32,96),array(28,29,94),array(27,27,93),array(25,26,92),
			array(24,24,89),array(23,23,87),array(22,22,82),array(20,21,81),array(19,19,80),array(17,18,76),
			array(15,16,67),array(14,14,64),array(13,13,63),array(11,12,61),array(10,10,59),array(9,9,57),array(8,8,52),
			array(7,7,46),array(6,6,45),array(4,5,41),array(3,3,34),array(2,2,29),array(0,1,28)
					)
			),
	9 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(39,45,">=99"),array(38,38,98),array(37,37,96),array(36,36,95),array(34,35,93),array(33,33,90),
			array(31,32,85),array(30,30,83),array(29,29,79),array(28,28,77),array(27,27,76),array(26,26,74),
			array(25,25,72),array(24,24,67),array(23,23,62),array(22,22,56),array(21,21,49),array(20,20,45),
			array(19,19,40),array(18,18,38),array(17,17,30),array(16,16,23),array(15,15,20),array(14,14,12),
			array(13,13,7),array(9,12,4)
					),
		'adaptability'	=> array(						// Adaptability
			array(40,55,">=99"),array(39,39,96),array(38,38,95),array(37,37,90),array(36,36,89),array(35,35,87),
			array(33,34,84),array(32,32,80),array(30,31,79),array(29,29,76),array(28,28,74),array(27,27,71),
			array(26,26,66),array(25,25,65),array(24,24,54),array(23,23,50),array(22,22,46),array(21,21,40),
			array(20,20,35),array(19,19,32),array(18,18,28),array(17,17,23),array(16,16,21),array(15,15,13),
			array(14,14,10),array(13,13,7),array(12,12,2),array(11,11,"<=1")
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(23,30,">=99"),array(21,22,96),array(20,20,93),array(19,19,91),array(18,18,87),array(17,17,85),
			array(16,16,83),array(15,15,77),array(14,14,71),array(13,13,61),array(12,12,56),array(11,11,46),
			array(10,10,38),array(9,9,30),array(8,8,21),array(7,7,18),array(6,6,10)
					),
		'demandingness'	=> array(						// demandingness
			array(38,45,">=99"),array(33,37,98),array(32,32,95),array(31,31,92),array(30,30,89),array(29,29,83),
			array(28,28,82),array(27,27,77),array(26,26,76),array(25,25,74),array(24,24,72),array(23,23,71),
			array(22,22,67),array(21,21,65),array(20,20,57),array(19,19,52),array(18,18,43),array(17,17,39),
			array(16,16,32),array(15,15,30),array(14,14,20),array(13,13,18),array(12,12,13),array(11,11,12),
			array(10,10,7),array(9,9,6)
					),
		'mood' => array(							// mood
			array(22,25,">=99"),array(20,21,96),array(19,19,95),array(18,18,93),array(17,17,85),array(16,16,80),
			array(15,15,77),array(14,14,70),array(13,13,65),array(12,12,63),array(11,11,51),array(10,10,41),
			array(9,9,33),array(8,8,24),array(7,7,16),array(6,6,7),array(5,5,"<=1")
					),
		'acceptability' => array(						// acceptabilty
			array(28,35,">=99"),array(27,27,96),array(26,26,95),array(25,25,94),array(24,24,93),array(23,23,87),
			array(19,22,85),array(18,18,83),array(17,17,82),array(16,16,77),array(15,15,73),array(14,14,71),
			array(13,13,67),array(12,12,54),array(11,11,45),array(10,10,34),array(9,9,28),array(8,8,24),array(7,7,13)
					),
		'coompetence' => array(							// competence
			array(49,65,">=99"),array(48,48,98),array(46,47,94),array(45,45,93),array(44,44,92),array(43,43,91),
			array(42,42,84),array(40,41,83),array(39,39,82),array(38,38,79),array(37,37,77),array(36,36,74),
			array(34,35,73),array(33,33,70),array(32,32,67),array(31,31,61),array(30,30,55),array(29,29,54),
			array(28,28,48),array(27,27,38),array(26,26,35),array(25,25,27),array(24,24,23),array(23,23,17),
			array(22,22,13),array(21,21,10),array(20,20,9),array(19,19,7),array(17,18,4),array(13,16,"<=1")
					),
		'isolation' => array(							// isolation
			array(24,30,">=99"),array(23,23,94),array(21,22,90),array(20,20,89),array(18,19,84),array(17,17,79),
			array(16,16,76),array(15,15,67),array(14,14,63),array(13,13,51),array(12,12,46),array(11,11,39),
			array(10,10,28),array(9,9,24),array(8,8,20),array(7,7,15),array(6,6,9)
					),
		'attachment' => array(							// Attachment
			array(26,35,">=99"),array(25,25,96),array(24,24,95),array(23,23,94),array(22,22,90),array(21,21,88),
			array(20,20,87),array(19,19,83),array(18,18,78),array(17,17,72),array(16,16,71),array(15,15,68),
			array(14,14,60),array(13,13,51),array(12,12,39),array(11,11,35),array(10,10,27),array(9,9,24),
			array(8,8,17),array(7,7,10)
					),
		'health' => array(							// Health
			array(22,25,">=99"),array(20,21,98),array(19,19,95),array(18,18,92),array(17,17,89),array(16,16,85),
			array(15,15,79),array(14,14,76),array(13,13,71),array(12,12,67),array(11,11,55),array(10,10,52),
			array(9,9,37),array(8,8,29),array(7,7,20),array(6,6,11),array(5,5,7)
					),
		'rolerestriction' => array(						// Role Restriction
			array(34,35,">=99"),array(31,33,98),array(29,30,95),array(28,28,94),array(27,27,93),array(26,26,91),
			array(24,25,90),array(23,23,83),array(22,22,79),array(21,21,72),array(20,20,70),array(19,19,65),
			array(18,18,51),array(17,17,45),array(16,16,40),array(15,15,32),array(14,14,23),array(13,13,15),
			array(12,12,12),array(11,11,10),array(10,10,7),array(9,9,5),array(8,8,3),array(7,7,"<=1")
					),
		'depression' => array(							// Depression
			array(39,45,">=99"),array(36,38,98),array(35,35,96),array(32,34,95),array(31,31,94),array(30,30,91),
			array(28,29,88),array(27,27,84),array(26,26,83),array(25,25,79),array(24,24,78),array(23,23,74),
			array(22,22,72),array(21,21,70),array(20,20,61),array(19,19,54),array(18,18,51),array(17,17,44),
			array(16,16,35),array(15,15,32),array(14,14,27),array(13,13,20),array(12,12,15),array(11,11,10),
			array(10,10,7),array(9,9,5)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(35,35,">=99"),array(34,34,98),array(31,33,96),array(30,30,95),array(29,29,94),array(28,28,93),
			array(27,27,91),array(26,26,89),array(25,25,82),array(24,24,80),array(23,23,77),array(22,22,72),
			array(21,21,65),array(20,20,63),array(18,19,54),array(17,17,49),array(16,16,48),array(15,15,43),
			array(14,14,39),array(13,13,29),array(12,12,22),array(11,11,15),array(10,10,12),array(9,9,11),
			array(8,8,9),array(7,7,4)
					),
		'childdomain' => array(							// Child Domain
			array(184,235,">=99"),array(167,183,98),array(166,166,96),array(158,165,95),array(157,157,94),
			array(156,156,93),array(155,155,92),array(154,154,91),array(153,153,90),array(152,152,89),
			array(151,151,87),array(150,150,84),array(149,149,83),array(147,148,82),array(146,146,80),
			array(139,145,79),array(138,138,78),array(130,137,77),array(125,129,76),array(124,124,75),
			array(123,123,74),array(122,122,73),array(121,121,72),array(116,120,71),array(114,115,70),
			array(112,113,67),array(109,111,66),array(107,108,65),array(105,106,62),array(104,104,61),
			array(103,103,59),array(102,102,57),array(101,101,56),array(100,100,55),array(99,99,52),array(98,98,51),
			array(97,97,49),array(96,96,46),array(95,95,44),array(94,94,43),array(93,93,40),array(90,92,35),
			array(89,89,34),array(86,88,33),array(85,85,30),array(84,84,29),array(80,83,28),array(79,79,26),
			array(76,78,24),array(75,75,22),array(74,74,20),array(73,73,16),array(72,72,15),array(71,71,14),
			array(70,70,13),array(69,69,11),array(63,68,10),array(59,62,9),array(58,58,6),array(55,57,4),array(54,54,3),
			array(53,53,2),array(47,52,"<=1")
					),
		'parentdomain' => array(						// Parent Domain
			array(215,270,">=99"),array(200,214,98),array(191,199,96),array(184,190,94),array(180,183,93),
			array(177,179,91),array(176,176,90),array(175,175,89),array(174,174,88),array(173,173,87),
			array(172,172,86),array(171,171,85),array(167,170,84),array(166,166,83),array(165,165,82),
			array(163,164,79),array(161,162,78),array(160,160,77),array(156,159,76),array(153,155,74),
			array(144,152,73),array(143,143,72),array(140,142,71),array(137,139,70),array(136,136,68),
			array(135,135,67),array(134,134,66),array(133,133,65),array(132,132,64),array(131,131,63),
			array(130,130,59),array(127,129,57),array(125,126,56),array(124,124,55),array(123,123,54),
			array(122,122,53),array(121,121,52),array(120,120,50),array(119,119,49),array(117,118,48),
			array(116,116,45),array(115,115,43),array(112,114,41),array(111,111,40),array(110,110,39),
			array(109,109,37),array(108,108,36),array(107,107,35),array(106,106,34),array(104,105,33),
			array(103,103,29),array(102,102,28),array(101,101,27),array(100,100,24),array(98,99,23),
			array(97,97,22),array(96,96,21),array(93,95,20),array(92,92,18),array(91,91,17),array(90,90,16),
			array(89,89,15),array(86,88,13),array(82,85,12),array(81,81,10),array(80,80,9),array(79,79,7),array(74,78,6),
			array(70,73,5),array(67,69,2),array(54,66,"<=1")
					),
		'totalstress' => array(							// Total Stress
			array(391,505,">=99"),array(375,390,98),array(342,374,96),array(341,341,95),array(340,340,94),
			array(331,339,93),array(329,330,90),array(326,328,89),array(324,325,88),array(320,323,87),
			array(317,319,85),array(316,316,84),array(313,315,83),array(308,312,80),array(301,307,79),
			array(292,300,78),array(286,291,77),array(283,285,76),array(276,282,74),array(274,275,73),
			array(267,273,72),array(262,266,71),array(260,261,70),array(243,259,68),array(242,242,67),
			array(236,241,65),array(235,235,62),array(232,234,61),array(228,231,59),array(225,227,57),
			array(221,224,56),array(220,220,54),array(217,219,52),array(216,216,50),array(215,215,49),
			array(214,214,48),array(213,213,46),array(212,212,45),array(211,211,44),array(210,210,41),
			array(201,209,40),array(200,200,39),array(198,199,34),array(195,197,33),array(194,194,32),
			array(193,193,30),array(192,192,29),array(191,191,28),array(185,190,27),array(181,184,26),
			array(180,180,23),array(179,179,21),array(176,178,20),array(173,175,18),array(168,172,17),
			array(167,167,16),array(166,166,15),array(164,165,13),array(149,163,12),array(148,148,11),
			array(142,147,10),array(139,141,9),array(138,138,6),array(137,137,5),array(129,136,4),
			array(120,128,2),array(101,119,"<=1")
					),
		'lifestress' => array(							// Life Stress
			array(39,79,">=99"),array(34,38,96),array(33,33,95),array(31,32,94),array(28,30,93),array(27,27,91),
			array(24,26,89),array(22,23,88),array(21,21,87),array(20,20,84),array(19,19,80),array(18,18,78),
			array(17,17,77),array(16,16,76),array(15,15,74),array(14,14,73),array(13,13,72),array(12,12,70),
			array(11,11,68),array(10,10,63),array(8,9,59),array(6,7,51),array(5,5,49),array(4,4,48),array(3,3,33),
			array(2,2,29),array(1,1,28),array(0,0,27)
					)
			),
	10 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(36,45,">=99"),array(32,35,98),array(31,31,97),array(30,30,96),array(29,29,95),array(28,28,93),
			array(27,27,89),array(26,26,86),array(25,25,81),array(24,24,79),array(23,23,74),array(22,22,69),
			array(21,21,63),array(20,20,55),array(19,19,48),array(18,18,43),array(17,17,34),array(16,16,26),
			array(15,15,23),array(14,14,14),array(13,13,9),array(12,12,6),array(11,11,4),array(9,10,3)
					),
		'adaptability'	=> array(						// Adaptability
			array(36,55,">=99"),array(35,35,98),array(34,34,95),array(33,33,91),array(32,32,90),array(31,31,89),
			array(30,30,85),array(29,29,83),array(28,28,76),array(26,27,73),array(25,25,69),array(24,24,64),
			array(23,23,60),array(22,22,58),array(21,21,50),array(20,20,44),array(19,19,36),array(18,18,28),
			array(17,17,25),array(16,16,19),array(15,15,14),array(14,14,8),array(13,13,5),array(12,12,4),array(11,11,3)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(25,30,">=99"),array(19,24,96),array(17,18,95),array(16,16,93),array(15,15,90),array(14,14,85),
			array(13,13,81),array(12,12,75),array(11,11,66),array(10,10,54),array(9,9,48),array(8,8,36),
			array(7,7,29),array(6,6,14)
					),
		'demandingness'	=> array(						// demandingness
			array(32,45,">=99"),array(30,31,96),array(29,29,94),array(28,28,93),array(27,27,90),array(26,26,86),
			array(25,25,85),array(24,24,81),array(23,23,80),array(22,22,78),array(21,21,74),array(20,20,73),
			array(19,19,69),array(18,18,58),array(17,17,53),array(16,16,40),array(15,15,34),array(14,14,28),
			array(13,13,24),array(12,12,21),array(11,11,16),array(10,10,9),array(9,9,3)
					),
		'mood' => array(							// mood
			array(19,25,">=99"),array(18,18,94),array(17,17,89),array(16,16,86),array(15,15,83),array(14,14,80),
			array(13,13,79),array(12,12,76),array(11,11,73),array(10,10,53),array(9,9,36),array(8,8,28),array(7,7,18),
			array(6,6,5),array(5,5,3)
					),
		'acceptability' => array(						// acceptabilty
			array(24,35,">=99"),array(22,23,96),array(18,21,89),array(17,17,86),array(16,16,85),array(15,15,80),
			array(14,14,74),array(13,13,65),array(12,12,55),array(11,11,48),array(10,10,39),array(9,9,33),
			array(8,8,24),array(7,7,14)
					),
		'coompetence' => array(							// competence
			array(47,65,">=99"),array(45,46,98),array(44,44,95),array(43,43,94),array(41,42,93),array(37,40,91),
			array(36,36,90),array(35,35,86),array(34,34,83),array(33,33,81),array(32,32,80),array(31,31,76),
			array(30,30,73),array(29,29,68),array(28,28,64),array(27,27,55),array(26,26,46),array(25,25,40),
			array(24,24,30),array(23,23,24),array(22,22,23),array(21,21,20),array(20,20,16),array(18,19,8),
			array(17,17,4),array(16,16,3),array(13,15,"<=1")
					),
		'isolation' => array(							// isolation
			array(22,30,">=99"),array(20,21,98),array(19,19,96),array(18,18,95),array(16,17,93),array(15,15,88),
			array(14,14,85),array(13,13,68),array(12,12,63),array(11,11,43),array(10,10,34),array(9,9,28),array(8,8,16),
			array(7,7,10),array(6,6,8)
					),
		'attachment' => array(							// Attachment
			array(25,35,">=99"),array(22,24,98),array(20,21,94),array(19,19,91),array(18,18,89),array(17,17,85),
			array(16,16,81),array(15,15,78),array(14,14,75),array(13,13,66),array(12,12,51),array(11,11,44),
			array(10,10,35),array(9,9,33),array(8,8,25),array(7,7,14)
					),
		'health' => array(							// Health
			array(18,25,">=99"),array(17,17,95),array(16,16,94),array(15,15,91),array(14,14,90),array(13,13,84),
			array(12,12,83),array(11,11,74),array(10,10,69),array(9,9,40),array(8,8,31),array(7,7,24),array(6,6,15),
			array(5,5,9)
					),
		'rolerestriction' => array(						// Role Restriction
			array(33,35,">=99"),array(28,32,97),array(26,27,96),array(24,25,94),array(23,23,91),array(22,22,89),
			array(21,21,87),array(20,20,82),array(19,19,78),array(18,18,72),array(17,17,62),array(16,16,58),
			array(15,15,49),array(14,14,41),array(13,13,28),array(12,12,20),array(11,11,11),array(10,10,6),
			array(9,9,5),array(8,8,3),array(7,7,"<=1")
					),
		'depression' => array(							// Depression
			array(36,45,">=99"),array(31,35,98),array(29,30,95),array(28,28,94),array(27,27,93),array(26,26,90),
			array(25,25,89),array(24,24,85),array(23,23,84),array(22,22,83),array(21,21,81),array(20,20,76),
			array(19,19,70),array(18,18,66),array(17,17,53),array(16,16,46),array(15,15,38),array(14,14,28),
			array(13,13,20),array(12,12,18),array(11,11,13),array(10,10,9),array(9,9,5)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(32,35,">=99"),array(30,31,96),array(28,29,95),array(26,27,93),array(25,25,86),array(24,24,81),
			array(23,23,79),array(22,22,76),array(21,21,75),array(20,20,73),array(19,19,68),array(18,18,66),
			array(17,17,63),array(16,16,59),array(15,15,46),array(14,14,45),array(13,13,35),array(12,12,29),
			array(11,11,25),array(10,10,18),array(9,9,11),array(8,8,9),array(7,7,8)
					),
		'childdomain' => array(							// Child Domain
			array(159,235,">=99"),array(155,158,98),array(154,154,95),array(146,153,94),array(144,145,93),
			array(139,143,91),array(138,138,90),array(126,137,89),array(125,125,88),array(124,124,85),
			array(116,123,84),array(115,115,83),array(114,114,79),array(113,113,78),array(112,112,75),
			array(109,111,73),array(108,108,70),array(106,107,69),array(105,105,68),array(104,104,66),
			array(101,103,65),array(100,100,64),array(98,99,63),array(97,97,61),array(96,96,60),array(94,95,59),
			array(92,93,53),array(91,91,51),array(90,90,48),array(89,89,45),array(86,88,44),array(83,85,36),
			array(81,82,34),array(79,80,33),array(76,78,31),array(75,75,30),array(74,74,29),array(73,73,28),
			array(72,72,26),array(71,71,25),array(70,70,21),array(69,69,14),array(68,68,13),array(66,67,10),
			array(63,65,9),array(62,62,8),array(57,61,6),array(56,56,5),array(55,55,3),array(47,54,"<=1")
					),
		'parentdomain' => array(						// Parent Domain
			array(190,270,">=99"),array(186,189,97),array(182,185,96),array(179,181,95),array(178,178,94),
			array(176,177,92),array(158,175,91),array(150,157,90),array(146,149,89),array(139,145,87),
			array(137,138,86),array(136,136,84),array(134,135,82),array(132,133,81),array(131,131,80),
			array(130,130,77),array(129,129,75),array(126,128,72),array(124,125,71),array(122,123,68),
			array(121,121,67),array(120,120,65),array(118,119,63),array(115,117,62),array(113,114,59),
			array(112,112,58),array(110,111,54),array(109,109,53),array(108,108,51),array(106,107,48),
			array(105,105,46),array(104,104,44),array(103,103,43),array(101,102,42),array(100,100,39),
			array(99,99,38),array(98,98,35),array(97,97,34),array(96,96,30),array(95,95,29),array(92,94,24),
			array(91,91,23),array(90,90,20),array(88,89,19),array(84,87,15),array(83,83,14),array(81,82,11),
			array(77,80,10),array(76,76,9),array(74,75,6),array(73,73,5),array(69,72,4),array(67,68,3),
			array(54,66,"<=1")
					),
		'totalstress' => array(							// Total Stress
			array(347,505,">=99"),array(344,346,97),array(335,343,96),array(325,334,95),array(322,324,94),
			array(296,321,92),array(287,295,91),array(285,286,90),array(268,284,89),array(260,267,87),
			array(255,259,86),array(249,254,85),array(247,248,84),array(246,246,82),array(245,245,81),
			array(244,244,78),array(237,243,77),array(236,236,76),array(235,235,75),array(234,234,74),
			array(233,233,73),array(228,232,72),array(225,227,70),array(224,224,68),array(222,223,67),
			array(221,221,66),array(220,220,65),array(217,219,63),array(213,216,62),array(212,212,61),
			array(209,211,59),array(208,208,57),array(207,207,56),array(205,206,54),array(203,204,53),
			array(202,202,52),array(201,201,51),array(198,200,49),array(194,197,48),array(193,193,47),
			array(192,192,44),array(189,191,43),array(186,188,39),array(184,185,38),array(180,183,37),
			array(179,179,33),array(177,178,32),array(174,176,30),array(173,173,28),array(170,172,25),
			array(166,169,23),array(165,165,20),array(164,164,19),array(162,163,18),array(161,161,15),
			array(159,160,14),array(158,158,13),array(150,157,11),array(140,149,9),array(139,139,8),
			array(137,138,6),array(132,136,5),array(129,131,3),array(101,128,"<=1")
					),
		'lifestress' => array(							// Life Stress
			array(35,79,">=99"),array(33,34,95),array(30,32,94),array(29,29,93),array(25,28,91),array(22,24,90),
			array(21,21,89),array(19,20,88),array(17,18,86),array(16,16,85),array(15,15,83),array(14,14,76),
			array(13,13,70),array(12,12,68),array(11,11,66),array(9,10,63),array(8,8,56),array(7,7,54),array(6,6,53),
			array(4,5,48),array(3,3,41),array(2,2,40),array(1,1,39),array(0,0,38)
					)
			),
	11 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(35,45,">=99"),array(33,34,98),array(32,32,96),array(30,31,91),array(28,29,90),array(27,27,89),
			array(26,26,85),array(25,25,83),array(24,24,76),array(23,23,75),array(22,22,74),array(21,21,70),
			array(20,20,66),array(19,19,61),array(18,18,56),array(17,17,45),array(16,16,40),array(15,15,24),
			array(14,14,13),array(13,13,6),array(12,12,3),array(9,11,"<1")
					),
		'adaptability'	=> array(						// Adaptability
			array(38,55,">=99"),array(36,37,98),array(33,35,96),array(32,32,91),array(31,31,89),array(30,30,88),
			array(28,29,86),array(27,27,85),array(26,26,81),array(25,25,79),array(24,24,75),array(23,23,70),
			array(22,22,68),array(21,21,58),array(20,20,54),array(19,19,46),array(18,18,41),array(17,17,34),
			array(16,16,26),array(15,15,20),array(14,14,13),array(13,13,6),array(11,12,"<1")
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(21,30,">=99"),array(20,20,96),array(19,19,93),array(18,18,90),array(17,17,88),array(16,16,85),
			array(15,15,81),array(14,14,80),array(13,13,70),array(12,12,61),array(11,11,55),array(10,10,44),
			array(9,9,38),array(8,8,34),array(7,7,24),array(6,6,11)
					),
		'demandingness'	=> array(						// demandingness
			array(33,45,">=99"),array(31,32,98),array(29,30,96),array(28,28,95),array(27,27,90),array(26,26,89),
			array(25,25,88),array(24,24,83),array(23,23,79),array(22,22,76),array(21,21,71),array(20,20,68),
			array(19,19,63),array(18,18,59),array(17,17,53),array(16,16,41),array(15,15,38),array(14,14,30),
			array(13,13,24),array(12,12,9),array(11,11,5),array(10,10,3),array(9,9,"<1")
					),
		'mood' => array(							// mood
			array(19,25,">=99"),array(18,18,95),array(17,17,94),array(16,16,93),array(15,15,90),array(14,14,88),
			array(13,13,83),array(12,12,74),array(11,11,70),array(10,10,58),array(9,9,43),array(8,8,31),
			array(7,7,19),array(6,6,8),array(5,5,4)
					),
		'acceptability' => array(						// acceptabilty
			array(23,35,">=99"),array(22,22,98),array(20,21,96),array(19,19,94),array(18,18,93),array(17,17,90),
			array(16,16,88),array(15,15,81),array(14,14,79),array(13,13,69),array(12,12,64),array(11,11,51),
			array(10,10,41),array(9,9,33),array(8,8,26),array(7,7,9)
					),
		'coompetence' => array(							// competence
			array(46,65,">=99"),array(43,45,98),array(42,42,96),array(41,41,95),array(40,40,94),array(38,39,93),
			array(37,37,91),array(36,36,89),array(35,35,88),array(34,34,86),array(33,33,85),array(32,32,80),
			array(31,31,74),array(30,30,70),array(29,29,68),array(28,28,60),array(27,27,54),array(26,26,50),
			array(25,25,45),array(24,24,39),array(23,23,33),array(22,22,21),array(21,21,19),array(20,20,15),
			array(19,19,13),array(18,18,8),array(16,17,4),array(13,15,3)
					),
		'isolation' => array(							// isolation
			array(22,30,">=99"),array(20,21,96),array(18,19,93),array(17,17,85),array(16,16,80),array(15,15,71),
			array(14,14,68),array(13,13,63),array(12,12,56),array(11,11,46),array(10,10,41),array(9,9,29),
			array(8,8,21),array(7,7,20),array(6,6,13)
					),
		'attachment' => array(							// Attachment
			array(22,35,">=99"),array(20,21,96),array(18,19,94),array(16,17,90),array(15,15,89),array(14,14,83),
			array(13,13,80),array(12,12,71),array(11,11,60),array(10,10,51),array(9,9,43),array(8,8,34),array(7,7,18)
					),
		'health' => array(							// Health
			array(18,25,">=99"),array(17,17,96),array(16,16,95),array(15,15,91),array(14,14,86),array(13,13,79),
			array(12,12,75),array(11,11,66),array(10,10,56),array(9,9,45),array(8,8,41),array(7,7,34),array(6,6,16),
			array(5,5,10)
					),
		'rolerestriction' => array(						// Role Restriction
			array(29,35,">=99"),array(28,28,98),array(27,27,95),array(25,26,93),array(24,24,89),array(23,23,86),
			array(22,22,84),array(21,21,76),array(20,20,74),array(19,19,70),array(18,18,64),array(17,17,55),
			array(16,16,48),array(15,15,40),array(14,14,31),array(13,13,25),array(12,12,18),array(11,11,15),
			array(10,10,10),array(9,9,8),array(8,8,5),array(7,7,4)
					),
		'depression' => array(							// Depression
			array(30,45,">=99"),array(29,29,98),array(28,28,96),array(27,27,93),array(26,26,91),array(25,25,89),
			array(24,24,85),array(23,23,83),array(22,22,79),array(21,21,76),array(20,20,74),array(19,19,66),
			array(18,18,64),array(17,17,60),array(16,16,53),array(15,15,36),array(14,14,31),array(13,13,23),
			array(12,12,19),array(11,11,18),array(10,10,15),array(9,9,10)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(31,35,">=99"),array(29,30,98),array(28,28,96),array(27,27,95),array(26,26,91),array(25,25,89),
			array(24,24,81),array(23,23,79),array(22,22,75),array(21,21,71),array(20,20,69),array(19,19,63),
			array(18,18,60),array(17,17,55),array(16,16,51),array(15,15,46),array(14,14,40),array(13,13,31),
			array(12,12,28),array(11,11,20),array(10,10,16),array(8,9,13),array(7,7,9)
					),
		'childdomain' => array(							// Child Domain
			array(150,235,">=99"),array(149,149,98),array(146,148,96),array(142,145,95),array(140,141,94),
			array(137,139,93),array(132,136,91),array(129,131,90),array(128,128,89),array(127,127,88),
			array(126,126,86),array(124,125,85),array(121,123,84),array(120,120,83),array(113,119,81),
			array(111,112,79),array(107,110,78),array(106,106,76),array(105,105,74),array(103,104,73),
			array(102,102,70),array(101,101,69),array(99,100,68),array(96,98,66),array(95,95,65),array(94,94,63),
			array(93,93,60),array(92,92,59),array(91,91,58),array(90,90,56),array(88,89,54),array(87,87,51),
			array(86,86,46),array(85,85,45),array(84,84,44),array(83,83,41),array(82,82,40),array(81,81,38),
			array(80,80,36),array(79,79,34),array(78,78,33),array(76,77,29),array(75,75,26),array(74,74,23),
			array(73,73,20),array(72,72,18),array(68,71,15),array(65,67,8),array(64,64,5),array(62,63,3),array(47,61,"<1")
					),
		'parentdomain' => array(						// Parent Domain
			array(172,270,">=99"),array(168,171,98),array(165,167,95),array(162,164,94),array(158,161,93),
			array(157,157,91),array(155,156,90),array(154,154,89),array(152,153,88),array(146,151,86),
			array(145,145,85),array(143,144,83),array(142,142,81),array(139,141,80),array(138,138,79),
			array(132,137,76),array(131,131,75),array(130,130,73),array(126,129,71),array(125,125,70),
			array(124,124,69),array(121,123,66),array(120,120,64),array(119,119,63),array(118,118,61),
			array(116,117,60),array(114,115,59),array(113,113,56),array(110,112,55),array(109,109,54),
			array(108,108,53),array(107,107,51),array(106,106,46),array(105,105,43),array(104,104,41),
			array(103,103,40),array(101,102,39),array(100,100,35),array(99,99,34),array(97,98,30),
			array(96,96,29),array(93,95,28),array(92,92,26),array(91,91,25),array(90,90,24),array(89,89,23),
			array(88,88,21),array(87,87,20),array(85,86,19),array(83,84,16),array(82,82,15),array(80,81,14),
			array(77,79,10),array(75,76,8),array(72,74,6),array(69,71,5),array(66,68,4),array(59,65,3),array(54,58,"<1")
					),
		'totalstress' => array(							// Total Stress
			array(318,505,">=99"),array(314,317,98),array(305,313,96),array(299,304,95),array(298,298,94),
			array(296,297,93),array(288,295,91),array(284,287,90),array(279,283,89),array(259,278,88),
			array(251,258,86),array(249,250,85),array(248,248,83),array(244,247,81),array(242,243,80),
			array(237,241,79),array(235,236,76),array(229,234,74),array(225,228,73),array(223,224,71),
			array(222,222,70),array(221,221,69),array(220,220,68),array(219,219,66),array(217,218,65),
			array(216,216,63),array(213,215,61),array(212,212,60),array(209,211,59),array(208,208,58),
			array(206,207,56),array(204,205,55),array(202,203,54),array(201,201,53),array(200,200,50),
			array(197,199,49),array(192,196,46),array(191,191,45),array(190,190,44),array(189,189,43),
			array(188,188,40),array(187,187,39),array(186,186,38),array(185,185,36),array(183,184,35),
			array(180,182,33),array(178,179,31),array(177,177,30),array(175,176,26),array(173,174,25),
			array(172,172,24),array(168,171,23),array(165,167,21),array(160,164,20),array(159,159,18),
			array(157,158,16),array(155,156,14),array(154,154,11),array(151,153,9),array(148,150,8),
			array(145,147,6),array(134,144,5),array(131,133,4),array(127,130,3)
					),
		'lifestress' => array(							// Life Stress
			array(42,79,">=99"),array(35,41,97),array(33,34,96),array(32,32,95),array(31,31,94),array(27,30,92),
			array(26,26,91),array(24,25,89),array(23,23,87),array(21,22,86),array(18,20,85),array(17,17,84),
			array(16,16,81),array(15,15,78),array(14,14,75),array(13,13,73),array(12,12,68),array(10,11,66),
			array(9,9,62),array(8,8,61),array(7,7,51),array(6,6,48),array(5,5,46),array(4,4,43),array(3,3,34),
			array(2,2,32),array(0,1,27)
					)
			),
	12 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(34,45,">=99"),array(33,33,98),array(32,32,94),array(30,31,90),array(29,29,86),array(28,28,78),
			array(27,27,75),array(26,26,69),array(24,25,66),array(23,23,55),array(22,22,50),array(21,21,45),
			array(20,20,39),array(19,19,36),array(18,18,33),array(17,17,30),array(16,16,26),array(15,15,20),
			array(14,14,16),array(13,13,11),array(12,12,6),array(11,11,5),array(9,10,3)
					),
		'adaptability'	=> array(						// Adaptability
			array(37,55,">=99"),array(36,36,98),array(35,35,94),array(34,34,90),array(32,33,89),array(31,31,84),
			array(30,30,80),array(29,29,78),array(28,28,73),array(27,27,71),array(26,26,66),array(25,25,61),
			array(24,24,53),array(23,23,49),array(22,22,44),array(21,21,34),array(20,20,30),array(19,19,25),
			array(18,18,21),array(16,17,15),array(15,15,10),array(14,14,6),array(13,13,4),array(11,12,3)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(27,30,">=99"),array(23,26,98),array(22,22,96),array(21,21,95),array(19,20,93),array(18,18,89),
			array(17,17,88),array(16,16,83),array(15,15,74),array(14,14,70),array(13,13,60),array(12,12,55),
			array(11,11,41),array(10,10,34),array(9,9,23),array(8,8,20),array(7,7,11),array(6,6,6)
					),
		'demandingness'	=> array(						// demandingness
			array(37,45,">=99"),array(35,36,98),array(34,34,96),array(33,33,95),array(29,32,94),
			array(28,28,93),array(27,27,86),array(26,26,81),array(25,25,79),array(24,24,78),array(23,23,71),
			array(22,22,64),array(21,21,56),array(20,20,48),array(19,19,44),array(18,18,38),array(17,17,34),
			array(16,16,28),array(15,15,24),array(14,14,16),array(13,13,14),array(12,12,11),array(11,11,8),
			array(10,10,6),array(9,9,4)
					),
		'mood' => array(							// mood
			array(20,25,">=99"),array(18,19,98),array(17,17,93),array(16,16,88),array(15,15,81),array(14,14,80),
			array(13,13,69),array(12,12,63),array(11,11,53),array(10,10,39),array(9,9,26),array(8,8,19),
			array(7,7,13),array(6,6,9),array(5,5,4)
					),
		'acceptability' => array(						// acceptabilty
			array(27,35,">=99"),array(24,26,98),array(22,23,93),array(20,21,89),array(19,19,85),array(18,18,81),
			array(17,17,80),array(16,16,74),array(15,15,73),array(14,14,64),array(13,13,50),array(12,12,43),
			array(11,11,36),array(10,10,31),array(9,9,21),array(8,8,14),array(7,7,11)
					),
		'coompetence' => array(							// competence
			array(48,65,">=99"),array(45,47,98),array(44,44,96),array(43,43,93),array(42,42,91),array(41,41,89),
			array(40,40,85),array(38,39,81),array(36,37,78),array(35,35,71),array(34,34,70),array(33,33,68),
			array(32,32,64),array(31,31,61),array(30,30,55),array(29,29,49),array(28,28,44),array(27,27,40),
			array(26,26,35),array(25,25,24),array(24,24,21),array(23,23,19),array(22,22,13),array(21,21,10),
			array(20,20,8),array(19,19,6),array(18,18,3),array(13,17,2)
					),
		'isolation' => array(							// isolation
			array(21,30,">=99"),array(20,20,95),array(19,19,93),array(18,18,86),array(17,17,79),array(16,16,75),
			array(15,15,65),array(14,14,58),array(13,13,48),array(12,12,33),array(11,11,20),array(10,10,15),
			array(9,9,11),array(8,8,8),array(7,7,5),array(6,6,4)
					),
		'attachment' => array(							// Attachment
			array(26,35,">=99"),array(25,25,98),array(24,24,96),array(23,23,94),array(22,22,93),array(21,21,90),
			array(20,20,89),array(19,19,84),array(18,18,80),array(17,17,74),array(16,16,73),array(15,15,68),
			array(14,14,64),array(13,13,51),array(12,12,46),array(11,11,40),array(10,10,34),array(9,9,28),
			array(8,8,18),array(7,7,10)
					),
		'health' => array(							// Health
			array(21,25,">=99"),array(20,20,98),array(19,19,96),array(18,18,94),array(16,17,91),array(15,15,86),
			array(14,14,83),array(13,13,81),array(12,12,70),array(11,11,61),array(10,10,49),array(9,9,34),
			array(8,8,20),array(7,7,11),array(6,6,8),array(5,5,4)
					),
		'rolerestriction' => array(						// Role Restriction
			array(29,35,">=99"),array(28,28,98),array(27,27,95),array(26,26,93),array(25,25,89),array(24,24,86),
			array(23,23,85),array(22,22,83),array(21,21,76),array(20,20,70),array(19,19,61),array(18,18,55),
			array(17,17,40),array(16,16,35),array(15,15,29),array(14,14,26),array(13,13,18),array(12,12,13),
			array(11,11,9),array(10,10,6),array(9,9,5),array(7,8,3)
					),
		'depression' => array(							// Depression
			array(31,45,">=99"),array(29,30,95),array(28,28,94),array(27,27,90),array(26,26,86),array(25,25,78),
			array(24,24,75),array(23,23,71),array(22,22,68),array(21,21,61),array(20,20,60),array(19,19,54),
			array(18,18,45),array(17,17,35),array(16,16,23),array(15,15,20),array(14,14,16),array(13,13,14),
			array(12,12,13),array(11,11,10),array(10,10,9),array(9,9,8)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(32,35,">=99"),array(31,31,98),array(29,30,95),array(28,28,91),array(27,27,89),array(26,26,86),
			array(25,25,80),array(24,24,78),array(23,23,71),array(22,22,69),array(21,21,68),array(20,20,60),
			array(19,19,46),array(18,18,41),array(16,17,36),array(15,15,28),array(14,14,24),array(13,13,14),
			array(12,12,11),array(11,11,8),array(10,10,6),array(9,9,5),array(8,8,3),array(7,7,"<=1")
					),
		'childdomain' => array(							// Child Domain
			array(170,235,">=99"),array(153,169,98),array(151,152,96),array(150,150,95),array(146,149,94),
			array(145,145,93),array(144,144,91),array(141,143,90),array(140,140,89),array(138,139,88),
			array(137,137,86),array(132,136,85),array(131,131,83),array(130,130,81),array(129,129,79),
			array(126,128,78),array(124,125,76),array(122,123,75),array(121,121,71),array(120,120,70),
			array(118,119,68),array(116,117,66),array(115,115,61),array(114,114,59),array(113,113,58),
			array(111,112,56),array(109,110,55),array(108,108,53),array(107,107,51),array(102,106,50),
			array(101,101,48),array(100,100,41),array(97,99,40),array(96,96,39),array(95,95,38),
			array(94,94,36),array(91,93,35),array(89,90,34),array(87,88,33),array(86,86,31),array(84,85,29),
			array(83,83,28),array(82,82,26),array(80,81,23),array(79,79,21),array(78,78,19),array(77,77,18),
			array(75,76,16),array(74,74,15),array(72,73,14),array(71,71,13),array(69,70,11),array(67,68,9),
			array(66,66,8),array(63,65,6),array(57,62,4),array(55,56,3),array(47,54,"<=1")
					),
		'parentdomain' => array(						// Parent Domain
			array(173,270,">=99"),array(172,172,98),array(169,171,95),array(167,168,94),array(165,166,93),
			array(164,164,91),array(163,163,86),array(162,162,84),array(161,161,81),array(160,160,80),
			array(158,159,78),array(156,157,76),array(155,155,75),array(153,154,73),array(152,152,71),
			array(148,151,70),array(147,147,69),array(146,146,68),array(143,145,66),array(142,142,65),
			array(141,141,64),array(140,140,63),array(131,139,60),array(130,130,59),array(129,129,58),
			array(127,128,56),array(125,126,55),array(124,124,51),array(123,123,50),array(122,122,49),
			array(121,121,45),array(120,120,44),array(118,119,43),array(117,117,41),array(116,116,39),
			array(115,115,35),array(113,114,34),array(112,112,33),array(111,111,31),array(110,110,28),
			array(108,109,25),array(106,107,24),array(105,105,23),array(104,104,21),array(103,103,20),
			array(102,102,19),array(101,101,16),array(99,100,15),array(96,98,13),array(88,95,11),
			array(87,87,10),array(86,86,9),array(83,85,8),array(75,82,6),array(72,74,5),array(67,71,4),
			array(66,66,3),array(54,65,"<=1")
					),
		'totalstress' => array(							// Total Stress
			array(339,505,">=99"),array(324,338,98),array(313,323,96),array(311,312,95),array(309,310,93),
			array(307,308,91),array(303,306,90),array(302,302,89),array(301,301,88),array(300,300,86),
			array(298,299,85),array(296,297,84),array(292,295,83),array(291,291,81),array(290,290,80),
			array(278,289,79),array(277,277,78),array(276,276,76),array(274,275,75),array(273,273,74),
			array(269,272,73),array(268,268,71),array(262,267,70),array(260,261,69),array(259,259,66),
			array(258,258,65),array(255,257,64),array(253,254,63),array(252,252,61),array(246,251,60),
			array(245,245,59),array(239,244,58),array(238,238,56),array(236,237,55),array(235,235,54),
			array(232,234,53),array(230,231,51),array(226,229,49),array(224,225,48),array(222,223,46),
			array(218,221,45),array(217,217,44),array(214,216,43),array(213,213,40),array(211,212,39),
			array(210,210,36),array(208,209,35),array(207,207,34),array(206,206,30),array(204,205,29),
			array(203,203,28),array(196,202,26),array(192,195,25),array(191,191,24),array(190,190,23),
			array(189,189,20),array(186,188,19),array(183,185,18),array(181,182,16),array(178,180,15),
			array(168,177,14),array(166,167,11),array(161,165,10),array(157,160,9),array(149,156,8),
			array(138,148,6),array(127,137,5),array(123,126,3),array(101,122,"<=1")
					),
		'lifestress' => array(							// Life Stress
			array(39,79,">=99"),array(33,38,95),array(32,32,94),array(31,31,92),array(30,30,91),array(29,29,89),
			array(28,28,86),array(26,27,84),array(24,25,82),array(23,23,80),array(22,22,77),array(21,21,76),
			array(20,20,73),array(19,19,71),array(18,18,68),array(17,17,67),array(16,16,66),array(15,15,62),
			array(14,14,59),array(13,13,57),array(12,12,56),array(11,11,49),array(10,10,47),array(9,9,41),
			array(8,8,39),array(6,7,35),array(4,5,29),array(2,3,25),array(0,1,24)
					)

			)
		);


$tval_lookup_matrix = array(
	4 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(45,45,82),array(44,44,81),array(43,43,79),array(42,42,78),array(41,41,76),array(40,40,75),array(39,39,73),
			array(38,38,72),array(37,37,70),array(36,36,69),array(35,35,67),array(34,34,66),array(33,33,64),array(32,32,63),
			array(31,31,62),array(30,30,60),array(29,29,59),array(28,28,57),array(27,27,56),array(26,26,54),array(25,25,53),
			array(24,24,51),array(23,23,50),array(22,22,48),array(21,21,47),array(20,20,45),array(19,19,44),array(18,18,42),
			array(17,17,41),array(16,16,39),array(15,15,38),array(14,14,36),array(13,13,35),array(12,12,33),array(11,11,32),
			array(10,10,30),array(9,9,29)
					),
		'adaptability'	=> array(						// Adaptability
			array(55,55,88),array(54,54,87),array(53,53,86),array(52,52,84),array(51,51,83),array(50,50,82),array(49,49,80),
			array(48,48,79),array(47,47,78),array(46,46,76),array(45,45,75),array(44,44,74),array(43,43,72),array(42,42,71),
			array(41,41,70),array(40,40,68),array(39,39,67),array(38,38,65),array(37,37,64),array(36,36,63),array(35,35,61),
			array(34,34,60),array(33,33,59),array(32,32,57),array(31,31,56),array(30,30,55),array(29,29,53),array(28,28,52),
			array(27,27,51),array(26,26,49),array(25,25,48),array(24,24,47),array(23,23,45),array(22,22,44),array(21,21,43),
			array(20,20,41),array(19,19,40),array(18,18,39),array(17,17,37),array(16,16,36),array(15,15,35),array(14,14,33),
			array(13,13,32),array(12,12,31),array(11,11,29)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(30,30,98),array(29,29,96),array(28,28,93),array(27,27,91),array(26,26,88),array(25,25,86),array(24,24,83),
			array(23,23,80),array(22,22,78),array(21,21,75),array(20,20,73),array(19,19,70),array(18,18,68),array(17,17,65),
			array(16,16,63),array(15,15,60),array(14,14,57),array(13,13,55),array(12,12,52),array(11,11,50),array(10,10,47),
			array(9,9,45),array(8,8,42),array(7,7,39),array(6,6,37)
					),
		'demandingness'	=> array(						// demandingness
			array(45,45,84),array(44,44,83),array(43,43,81),array(42,42,80),array(41,41,78),array(40,40,77),array(39,39,76),
			array(38,38,74),array(37,37,73),array(36,36,71),array(35,35,70),array(34,34,69),array(33,33,67),array(32,32,66),
			array(31,31,64),array(30,30,63),array(29,29,62),array(28,28,60),array(27,27,59),array(26,26,57),array(25,25,56),
			array(24,24,55),array(23,23,53),array(22,22,52),array(21,21,50),array(20,20,49),array(19,19,48),array(18,18,46),
			array(17,17,45),array(16,16,44),array(15,15,42),array(14,14,41),array(13,13,39),array(12,12,38),array(11,11,37),
			array(10,10,35),array(9,9,34)
					),
		'mood' => array(							// mood
			array(25,25,82),array(24,24,79),array(23,23,77),array(22,22,74),array(21,21,72),array(20,20,69),array(19,19,67),
			array(18,18,64),array(17,17,62),array(16,16,59),array(15,15,57),array(14,14,54),array(13,13,52),array(12,12,49),
			array(11,11,47),array(10,10,44),array(9,9,42),array(8,8,39),array(7,7,37),array(6,6,34),array(5,5,32)
					),
		'acceptability' => array(						// acceptabilty
			array(35,35,86),array(34,34,85),array(33,33,83),array(32,32,81),array(31,31,79),array(30,30,78),array(29,29,76),
			array(28,28,74),array(27,27,72),array(26,26,71),array(25,25,69),array(24,24,67),array(23,23,65),array(22,22,64),
			array(21,21,62),array(20,20,60),array(19,19,58),array(18,18,57),array(17,17,55),array(16,16,53),array(15,15,51),
			array(14,14,50),array(13,13,48),array(12,12,46),array(11,11,44),array(10,10,43),array(9,9,41),array(8,8,39),
			array(7,7,37)
					),
		'coompetence' => array(							// competence
			array(65,65,90),array(64,64,89),array(63,63,88),array(62,62,87),array(61,61,86),array(60,60,84),array(59,59,83),
			array(58,58,82),array(57,57,81),array(56,56,80),array(55,55,79),array(54,54,78),array(53,53,76),array(52,52,75),
			array(51,51,74),array(50,50,73),array(49,49,72),array(48,48,71),array(47,47,70),array(46,46,68),array(45,45,67),
			array(44,44,66),array(43,43,65),array(42,42,64),array(41,41,63),array(40,40,62),array(39,39,61),array(38,38,59),
			array(37,37,58),array(36,36,57),array(35,35,56),array(34,34,55),array(33,33,54),array(32,32,53),array(31,31,51),
			array(30,30,50),array(29,29,49),array(28,28,48),array(27,27,47),array(26,26,46),array(25,25,45),array(24,24,43),
			array(23,23,42),array(22,22,41),array(21,21,40),array(20,20,39),array(19,19,38),array(18,18,37),array(17,17,35),
			array(16,16,34),array(15,15,33),array(14,14,32),array(13,13,31)
					),
		'isolation' => array(							// isolation
			array(30,30,85),array(29,29,83),array(28,28,81),array(27,27,79),array(26,26,77),array(25,25,75),array(24,24,73),
			array(23,23,71),array(22,22,69),array(21,21,67),array(20,20,65),array(19,19,63),array(18,18,61),array(17,17,59),
			array(16,16,57),array(15,15,55),array(14,14,52),array(13,13,50),array(12,12,48),array(11,11,46),array(10,10,44),
			array(9,9,42),array(8,8,40),array(7,7,38),array(6,6,36)
					),
		'attachment' => array(							// Attachment
			array(35,35,89),array(34,34,87),array(33,33,85),array(32,32,83),array(31,31,82),array(30,30,80),array(29,29,78),
			array(28,28,76),array(27,27,74),array(26,26,73),array(25,25,71),array(24,24,69),array(23,23,67),array(22,22,66),
			array(21,21,64),array(20,20,62),array(19,19,60),array(18,18,58),array(17,17,57),array(16,16,55),array(15,15,53),
			array(14,14,51),array(13,13,50),array(12,12,48),array(11,11,46),array(10,10,44),array(9,9,43),array(8,8,41),
			array(7,7,39)
					),
		'health' => array(							// Health
			array(25,25,94),array(24,24,91),array(23,23,88),array(22,22,85),array(21,21,82),array(20,20,79),array(19,19,76),
			array(18,18,73),array(17,17,70),array(16,16,67),array(15,15,64),array(14,14,60),array(13,13,57),array(12,12,54),
			array(11,11,51),array(10,10,48),array(9,9,45),array(8,8,42),array(7,7,39),array(6,6,36),array(5,5,33)
					),
		'rolerestriction' => array(						// Role Restriction
			array(35,35,77),array(34,34,76),array(33,33,74),array(32,32,72),array(31,31,71),array(30,30,69),array(29,29,67),
			array(28,28,66),array(27,27,64),array(26,26,62),array(25,25,61),array(24,24,59),array(23,23,57),array(22,22,56),
			array(21,21,54),array(20,20,53),array(19,19,51),array(18,18,49),array(17,17,48),array(16,16,46),array(15,15,44),
			array(14,14,43),array(13,13,41),array(12,12,39),array(11,11,38),array(10,10,36),array(9,9,34),array(8,8,33),array(7,7,31)
					),
		'depression' => array(							// Depression
			array(45,45,86),array(44,44,84),array(43,43,83),array(42,42,82),array(41,41,80),array(40,40,79),array(39,39,77),
			array(38,38,76),array(37,37,75),array(36,36,73),array(35,35,72),array(34,34,71),array(33,33,69),array(32,32,68),
			array(31,31,66),array(30,30,65),array(29,29,64),array(28,28,62),array(27,27,61),array(26,26,59),array(25,25,58),
			array(24,24,57),array(23,23,55),array(22,22,54),array(21,21,52),array(20,20,51),array(19,19,50),array(18,18,48),
			array(17,17,47),array(16,16,46),array(15,15,44),array(14,14,43),array(13,13,41),array(12,12,40),array(11,11,39),
			array(10,10,37),array(9,9,36)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(35,35,78),array(34,34,76),array(33,33,75),array(32,32,73),array(31,31,72),array(30,30,70),array(29,29,69),
			array(28,28,67),array(27,27,66),array(26,26,64),array(25,25,62),array(24,24,61),array(23,23,59),array(22,22,58),
			array(21,21,56),array(20,20,55),array(19,19,53),array(18,18,52),array(17,17,50),array(16,16,49),array(15,15,47),
			array(14,14,45),array(13,13,44),array(12,12,42),array(11,11,41),array(10,10,39),array(9,9,38),array(8,8,36),array(7,7,35)
					),
		'childdomain' => array(							// Child Domain
			array(233,235,92),array(230,232,91),array(227,229,90),array(224,226,89),array(221,223,88),array(218,220,87),
			array(215,217,86),array(212,214,85),array(209,211,84),array(206,208,83),array(203,205,82),array(200,202,81),
			array(197,199,80),array(194,196,79),array(191,193,78),array(188,190,77),array(185,187,76),array(182,184,75),
			array(179,181,74),array(176,178,73),array(173,175,72),array(170,172,71),array(167,169,70),array(164,166,69),
			array(161,163,68),array(158,160,67),array(155,157,66),array(152,154,65),array(149,151,64),array(146,148,63),
			array(143,145,62),array(140,142,61),array(137,139,60),array(134,136,59),array(131,133,58),array(128,130,57),
			array(125,127,56),array(122,124,55),array(119,121,54),array(116,118,53),array(113,115,52),array(110,112,51),
			array(107,109,50),array(104,106,49),array(101,103,48),array(98,100,47),array(95,97,46),array(92,94,45),
			array(89,91,44),array(86,88,43),array(83,85,42),array(80,82,41),array(77,79,40),array(74,76,39),array(71,73,38),
			array(68,70,37),array(65,67,36),array(62,64,35),array(59,61,34),array(56,58,33),array(53,55,32),array(50,52,31),
			array(47,49,30)
					),
		'parentdomain' => array(						// Parent Domain
			array(269,270,91),array(266,268,90),array(262,265,89),array(259,261,88),array(255,258,87),array(251,254,86),
			array(248,250,85),array(244,247,84),array(240,243,83),array(237,239,82),array(233,236,81),array(229,232,80),
			array(226,228,79),array(222,225,78),array(218,221,77),array(215,217,76),array(211,214,75),array(207,210,74),
			array(204,206,73),array(200,203,72),array(196,199,71),array(193,195,70),array(189,192,69),array(185,188,68),
			array(182,184,67),array(178,181,66),array(174,177,65),array(171,173,64),array(167,170,63),array(163,166,62),
			array(160,162,61),array(156,159,60),array(153,155,59),array(149,152,58),array(145,148,57),array(142,144,56),
			array(138,141,55),array(134,137,54),array(131,133,53),array(127,130,52),array(123,126,51),array(120,122,50),
			array(116,119,49),array(112,115,48),array(109,111,47),array(105,108,46),array(101,104,45),array(98,100,44),
			array(94,97,43),array(90,93,42),array(87,89,41),array(83,86,40),array(79,82,39),array(76,78,38),array(72,75,37),
			array(68,71,36),array(65,67,35),array(61,64,34),array(57,60,33),array(54,56,32)
					),
		'totalstress' => array(							// Total Stress
			array(500,505,93),array(494,499,92),array(487,498,91),array(481,486,90),array(474,480,89),array(468,473,88),
			array(462,467,87),array(455,461,86),array(499,454,85),array(443,448,84),array(436,442,83),array(430,435,82),
			array(424,429,81),array(417,423,80),array(411,416,79),array(404,410,78),array(398,403,77),array(392,397,76),
			array(385,391,75),array(379,384,74),array(373,378,73),array(366,372,72),array(360,365,71),array(354,359,70),
			array(347,353,69),array(341,346,68),array(334,340,67),array(328,333,66),array(322,327,65),array(315,321,64),
			array(309,314,63),array(303,308,62),array(296,302,61),array(290,295,60),array(284,289,59),array(277,283,58),
			array(271,276,57),array(264,270,56),array(258,263,55),array(252,257,54),array(245,251,53),array(239,244,52),
			array(233,238,51),array(226,232,50),array(220,225,49),array(213,219,48),array(207,212,47),array(201,206,46),
			array(194,200,45),array(188,193,44),array(182,187,43),array(175,181,42),array(169,174,41),array(163,168,40),
			array(156,162,39),array(150,155,38),array(143,149,37),array(137,142,36),array(131,136,35),array(124,130,34),
			array(118,123,33),array(112,117,32),array(105,111,31),array(101,104,30)
					),
		'lifestress' => array(							// Life Stress
			array(79,79,">121"),array(78,78,120),array(77,77,119),array(76,76,118),array(75,75,117),array(74,74,116),
			array(73,73,115),array(72,72,114),array(71,71,113),array(70,70,112),array(69,69,111),array(68,68,110),
			array(67,67,109),array(66,66,107),array(65,65,106),array(64,64,105),array(68,68,104),array(62,62,103),
			array(61,61,102),array(60,60,101),array(59,59,100),array(58,58,99),array(57,57,98),array(56,56,97),array(55,55,96),
			array(54,54,95),array(53,53,94),array(52,52,93),array(51,51,92),array(50,50,91),array(49,49,90),array(48,48,89),
			array(47,47,88),array(46,46,87),array(45,45,86),array(44,44,85),array(43,43,84),array(42,42,83),array(41,41,82),
			array(40,40,81),array(39,39,80),array(38,38,79),array(37,37,78),array(36,36,77),array(35,35,76),array(34,34,74),
			array(33,33,73),array(32,32,72),array(31,31,71),array(30,30,70),array(29,29,69),array(28,28,68),array(27,27,67),
			array(26,26,66),array(25,25,65),array(24,24,64),array(23,23,63),array(22,22,62),array(21,21,61),array(20,20,60),
			array(19,19,59),array(18,18,58),array(17,17,57),array(16,16,56),array(15,15,55),array(14,14,54),array(13,13,53),
			array(12,12,52),array(11,11,51),array(10,10,50),array(9,9,49),array(8,8,48),array(7,7,47),array(6,6,46),array(5,5,45),
			array(4,4,44),array(3,3,43),array(2,2,42),array(1,1,40),array(0,0,39)
					)
			),
	5 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(45,45,88),array(44,44,86),array(43,43,84),array(42,42,83),array(41,41,81),array(40,40,79),array(39,39,78),
			array(38,38,76),array(37,37,74),array(36,36,73),array(35,35,71),array(34,34,69),array(33,33,67),array(32,32,66),
			array(31,31,64),array(30,30,62),array(29,29,61),array(28,28,59),array(27,27,57),array(26,26,56),array(25,25,54),
			array(24,24,52),array(23,23,50),array(22,22,49),array(21,21,47),array(20,20,45),array(19,19,44),array(18,18,42),
			array(17,17,40),array(16,16,39),array(15,15,37),array(14,14,35),array(13,13,33),array(12,12,32),array(11,11,30),
			array(10,10,28),array(9,9,27)
					),
		'adaptability'	=> array(						// Adaptability
			array(55,55,97),array(54,54,96),array(53,53,94),array(52,52,92),array(51,51,91),array(50,50,89),array(49,49,88),
			array(48,48,86),array(47,47,84),array(46,46,83),array(45,45,81),array(44,44,80),array(43,43,78),array(42,42,76),
			array(41,41,75),array(40,40,73),array(39,39,72),array(38,38,70),array(37,37,68),array(36,36,67),array(35,35,65),
			array(34,34,64),array(33,33,62),array(32,32,60),array(31,31,59),array(30,30,57),array(29,29,56),array(28,28,54),
			array(27,27,52),array(26,26,51),array(25,25,49),array(24,24,48),array(23,23,46),array(22,22,44),array(21,21,43),
			array(20,20,41),array(19,19,40),array(18,18,38),array(17,17,36),array(16,16,35),array(15,15,33),array(14,14,32),
			array(13,13,30),array(12,12,28),array(11,11,27)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(30,30,97),array(29,29,95),array(28,28,92),array(27,27,90),array(26,26,87),array(25,25,85),array(24,24,82),
			array(23,23,80),array(22,22,77),array(21,21,75),array(20,20,73),array(19,19,70),array(18,18,68),array(17,17,65),
			array(16,16,63),array(15,15,60),array(14,14,58),array(13,13,55),array(12,12,53),array(11,11,50),array(10,10,48),
			array(9,9,46),array(8,8,43),array(7,7,41),array(6,6,38)
					),
		'demandingness'	=> array(						// demandingness
			array(45,45,85),array(44,44,84),array(43,43,82),array(42,42,81),array(41,41,80),array(40,40,78),array(39,39,77),
			array(38,38,75),array(37,37,74),array(36,36,72),array(35,35,71),array(34,34,70),array(33,33,68),array(32,32,67),
			array(31,31,65),array(30,30,64),array(29,29,62),array(28,28,61),array(27,27,60),array(26,26,58),array(25,25,57),
			array(24,24,55),array(23,23,54),array(22,22,53),array(21,21,51),array(20,20,50),array(19,19,48),array(18,18,47),
			array(17,17,45),array(16,16,44),array(15,15,43),array(14,14,41),array(13,13,40),array(12,12,38),array(11,11,37),
			array(10,10,35),array(9,9,34)
					),
		'mood' => array(							// mood
			array(25,25,83),array(24,24,80),array(23,23,78),array(22,22,76),array(21,21,73),array(20,20,71),array(19,19,68),
			array(18,18,66),array(17,17,63),array(16,16,61),array(15,15,59),array(14,14,56),array(13,13,54),array(12,12,51),
			array(11,11,49),array(10,10,46),array(9,9,44),array(8,8,42),array(7,7,39),array(6,6,37),array(5,5,34)
					),
		'acceptability' => array(						// acceptabilty
			array(35,35,89),array(34,34,87),array(33,33,85),array(32,32,83),array(31,31,81),array(30,30,80),array(29,29,78),
			array(28,28,76),array(27,27,74),array(26,26,72),array(25,25,71),array(24,24,69),array(23,23,67),array(22,22,65),
			array(21,21,64),array(20,20,62),array(19,19,60),array(18,18,58),array(17,17,56),array(16,16,55),array(15,15,53),
			array(14,14,51),array(13,13,49),array(12,12,47),array(11,11,46),array(10,10,44),array(9,9,42),array(8,8,40),
			array(7,7,38)
					),
		'coompetence' => array(							// competence
			array(65,65,92),array(64,64,91),array(63,63,90),array(62,62,89),array(61,61,87),array(60,60,86),array(59,59,85),
			array(58,58,84),array(57,57,83),array(56,56,81),array(55,55,80),array(54,54,79),array(53,53,78),array(52,52,77),
			array(51,51,76),array(50,50,74),array(49,49,73),array(48,48,72),array(47,47,71),array(46,46,70),array(45,45,69),
			array(44,44,67),array(43,43,66),array(42,42,65),array(41,41,64),array(40,40,63),array(39,39,62),array(38,38,60),
			array(37,37,59),array(36,36,58),array(35,35,57),array(34,34,56),array(33,33,54),array(32,32,53),array(31,31,52),
			array(30,30,51),array(29,29,50),array(28,28,49),array(27,27,47),array(26,26,46),array(25,25,45),array(24,24,44),
			array(23,23,43),array(22,22,42),array(21,21,40),array(20,20,39),array(19,19,38),array(18,18,37),array(17,17,36),
			array(16,16,35),array(15,15,33),array(14,14,32),array(13,13,31)
					),
		'isolation' => array(							// isolation
			array(30,30,87),array(29,29,85),array(28,28,82),array(27,27,80),array(26,26,78),array(25,25,76),array(24,24,74),
			array(23,23,72),array(22,22,69),array(21,21,67),array(20,20,65),array(19,19,63),array(18,18,61),array(17,17,58),
			array(16,16,56),array(15,15,54),array(14,14,52),array(13,13,50),array(12,12,48),array(11,11,45),array(10,10,43),
			array(9,9,41),array(8,8,39),array(7,7,37),array(6,6,34)
					),
		'attachment' => array(							// Attachment
			array(35,35,95),array(34,34,93),array(33,33,91),array(32,32,89),array(31,31,87),array(30,30,85),array(29,29,83),
			array(28,28,81),array(27,27,79),array(26,26,77),array(25,25,75),array(24,24,73),array(23,23,71),array(22,22,69),
			array(21,21,67),array(20,20,65),array(19,19,62),array(18,18,60),array(17,17,58),array(16,16,56),array(15,15,54),
			array(14,14,52),array(13,13,50),array(12,12,48),array(11,11,46),array(10,10,44),array(9,9,42),array(8,8,40),
			array(7,7,38)
					),
		'health' => array(							// Health
			array(25,25,85),array(24,24,82),array(23,23,79),array(22,22,77),array(21,21,74),array(20,20,71),array(19,19,69),
			array(18,18,66),array(17,17,63),array(16,16,61),array(15,15,58),array(14,14,55),array(13,13,53),array(12,12,50),
			array(11,11,47),array(10,10,45),array(9,9,42),array(8,8,39),array(7,7,37),array(6,6,34),array(5,5,31)
					),
		'rolerestriction' => array(						// Role Restriction
			array(35,35,88),array(34,34,85),array(33,33,83),array(32,32,81),array(31,31,79),array(30,30,76),array(29,29,74),
			array(28,28,72),array(27,27,70),array(26,26,68),array(25,25,65),array(24,24,63),array(23,23,61),array(22,22,59),
			array(21,21,56),array(20,20,54),array(19,19,52),array(18,18,50),array(17,17,48),array(16,16,45),array(15,15,43),
			array(14,14,41),array(13,13,39),array(12,12,36),array(11,11,34),array(10,10,32),array(9,9,30),array(8,8,28),
			array(7,7,25)
					),
		'depression' => array(							// Depression
			array(45,45,85),array(44,44,83),array(43,43,82),array(42,42,81),array(41,41,79),array(40,40,78),array(39,39,76),
			array(38,38,75),array(37,37,74),array(36,36,72),array(35,35,71),array(34,34,69),array(33,33,68),array(32,32,67),
			array(31,31,65),array(30,30,64),array(29,29,62),array(28,28,61),array(27,27,60),array(26,26,58),array(25,25,57),
			array(24,24,55),array(23,23,54),array(22,22,53),array(21,21,51),array(20,20,50),array(19,19,48),array(18,18,47),
			array(17,17,46),array(16,16,44),array(15,15,43),array(14,14,41),array(13,13,40),array(12,12,38),array(11,11,37),
			array(10,10,36),array(9,9,34)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(35,35,79),array(34,34,77),array(33,33,75),array(32,32,74),array(31,31,72),array(30,30,71),array(29,29,69),
			array(28,28,67),array(27,27,66),array(26,26,64),array(25,25,62),array(24,24,61),array(23,23,59),array(22,22,57),
			array(21,21,56),array(20,20,54),array(19,19,52),array(18,18,51),array(17,17,49),array(16,16,47),array(15,15,46),
			array(14,14,44),array(13,13,42),array(12,12,41),array(11,11,39),array(10,10,37),array(9,9,36),array(8,8,34),
			array(7,7,32)
					),
		'childdomain' => array(							// Child Domain
			array(235,235,95),array(232,234,94),array(229,231,93),array(226,228,92),array(223,225,91),array(220,222,90),
			array(217,219,89),array(214,216,88),array(211,213,87),array(208,210,86),array(206,207,85),array(203,205,84),
			array(200,202,83),array(197,199,82),array(194,196,81),array(191,193,80),array(188,190,79),array(185,187,78),
			array(182,184,77),array(179,181,76),array(176,178,75),array(173,175,74),array(170,172,73),array(168,169,72),
			array(165,167,71),array(162,164,70),array(159,161,69),array(156,158,68),array(153,155,67),array(150,152,66),
			array(147,149,65),array(144,146,64),array(141,143,63),array(138,140,62),array(135,137,61),array(132,134,60),
			array(130,131,59),array(127,129,58),array(124,126,57),array(121,123,56),array(118,120,55),array(115,117,54),
			array(112,114,53),array(109,111,52),array(106,108,51),array(103,105,50),array(100,102,49),array(97,99,48),
			array(94,96,47),array(92,93,46),array(89,91,45),array(86,88,44),array(83,85,43),array(80,82,42),array(77,79,41),
			array(74,76,40),array(71,73,39),array(68,70,38),array(65,67,37),array(62,64,36),array(59,61,35),array(57,58,34),
			array(54,56,33),array(51,53,32),array(48,50,31),array(47,47,30)
					),
		'parentdomain' => array(						// Parent Domain
			array(269,270,94),array(265,268,93),array(262,264,92),array(259,261,91),array(255,258,90),array(252,254,89),
			array(249,251,88),array(245,248,87),array(242,244,86),array(239,241,85),array(235,238,84),array(232,234,83),
			array(229,231,82),array(225,228,81),array(222,224,80),array(219,221,79),array(215,218,78),array(212,214,77),
			array(209,211,76),array(205,208,75),array(202,204,74),array(199,201,73),array(195,198,72),array(192,194,71),
			array(189,191,70),array(185,188,69),array(182,184,68),array(179,181,67),array(175,178,66),array(172,174,65),
			array(169,171,64),array(165,168,63),array(162,164,62),array(159,161,61),array(155,158,60),array(152,154,59),
			array(149,151,58),array(145,148,57),array(142,144,56),array(139,141,55),array(135,138,54),array(132,134,53),
			array(129,131,52),array(125,128,51),array(122,124,50),array(119,121,49),array(115,118,48),array(112,114,47),
			array(109,111,46),array(105,108,45),array(102,104,44),array(99,101,43),array(95,98,42),array(92,94,41),
			array(89,91,40),array(85,88,39),array(82,84,38),array(79,81,37),array(75,78,36),array(72,74,35),array(69,71,34),
			array(65,68,33),array(62,64,32),array(59,61,31),array(55,58,30),array(54,54,29)
					),
		'totalstress' => array(							// Total Stress
			array(505,505,97),array(499,504,96),array(493,498,95),array(488,492,94),array(482,487,93),array(476,481,92),
			array(470,475,91),array(464,469,90),array(458,463,89),array(452,457,88),array(446,451,87),array(440,445,86),
			array(434,439,85),array(428,433,84),array(422,427,83),array(416,421,82),array(410,415,81),array(404,409,80),
			array(398,403,79),array(392,397,78),array(386,391,77),array(380,385,76),array(374,379,75),array(368,373,74),
			array(362,367,73),array(356,361,72),array(350,355,71),array(344,349,70),array(338,343,69),array(332,337,68),
			array(326,331,67),array(320,325,66),array(314,319,65),array(308,313,64),array(302,307,63),array(296,301,62),
			array(291,295,61),array(285,290,60),array(279,284,59),array(273,278,58),array(267,272,57),array(261,266,56),
			array(255,260,55),array(249,254,54),array(243,248,53),array(237,242,52),array(231,236,51),array(225,230,50),
			array(219,224,49),array(213,218,48),array(207,212,47),array(201,206,46),array(195,200,45),array(189,194,44),
			array(183,188,43),array(177,182,42),array(171,176,41),array(165,170,40),array(159,164,39),array(153,158,38),
			array(147,152,37),array(141,146,36),array(135,140,35),array(129,134,34),array(123,128,33),array(117,122,32),
			array(111,116,31),array(105,110,30),array(101,104,29)
					),
		'lifestress' => array(							// Life Stress
			array(79,79,">=107"),array(78,78,106),array(77,77,105),array(76,76,104),array(74,75,103),array(73,73,102),
			array(72,72,101),array(71,71,100),array(70,70,99),array(69,69,98),array(68,68,97),array(66,67,96),array(65,65,95),
			array(64,64,94),array(63,63,93),array(62,62,92),array(61,61,91),array(60,60,90),array(58,59,89),array(57,57,88),
			array(56,56,87),array(55,55,86),array(54,54,85),array(53,53,84),array(52,52,83),array(50,51,82),array(49,49,81),
			array(48,48,80),array(47,47,79),array(46,46,78),array(45,45,77),array(43,44,76),array(42,42,75),array(41,41,74),
			array(40,40,73),array(39,39,72),array(38,38,71),array(37,37,70),array(35,36,69),array(34,34,68),array(33,33,67),
			array(32,32,66),array(31,31,65),array(30,30,64),array(29,29,63),array(27,28,62),array(26,26,61),array(25,25,60),
			array(24,24,59),array(23,23,58),array(22,22,57),array(21,21,56),array(19,20,55),array(18,18,54),array(17,17,53),
			array(16,16,52),array(15,15,51),array(14,14,50),array(12,13,49),array(11,11,48),array(10,10,47),array(9,9,46),
			array(8,8,45),array(7,7,44),array(6,6,43),array(4,5,42),array(3,3,41),array(2,2,40),array(1,1,39),array(0,0,38)
					)
			),
	6 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(45,45,86),array(44,44,85),array(43,43,83),array(42,42,81),array(41,41,80),array(40,40,78),array(39,39,76),
			array(38,38,75),array(37,37,73),array(36,36,71),array(35,35,69),array(34,34,68),array(33,33,66),array(32,32,64),
			array(31,31,63),array(30,30,61),array(29,29,59),array(28,28,58),array(27,27,56),array(26,26,54),array(25,25,53),
			array(24,24,51),array(23,23,49),array(22,22,48),array(21,21,46),array(20,20,44),array(19,19,43),array(18,18,41),
			array(17,17,39),array(16,16,38),array(15,15,36),array(14,14,34),array(13,13,33),array(12,12,31),array(11,11,29),
			array(10,10,28),array(9,9,26)
					),
		'adaptability'	=> array(						// Adaptability
			array(55,55,88),array(54,54,87),array(53,53,85),array(52,52,84),array(51,51,83),array(50,50,81),array(49,49,80),
			array(48,48,79),array(47,47,77),array(46,46,76),array(45,45,75),array(44,44,73),array(43,43,72),array(42,42,71),
			array(41,41,69),array(40,40,68),array(39,39,67),array(38,38,65),array(37,37,64),array(36,36,63),array(35,35,61),
			array(34,34,60),array(33,33,59),array(32,32,57),array(31,31,56),array(30,30,55),array(29,29,53),array(28,28,52),
			array(27,27,51),array(26,26,49),array(25,25,48),array(24,24,47),array(23,23,45),array(22,22,44),array(21,21,43),
			array(20,20,41),array(19,19,40),array(18,18,39),array(17,17,37),array(16,16,36),array(15,15,35),array(14,14,33),
			array(13,13,32),array(12,12,31),array(11,11,29)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(30,30,96),array(29,29,93),array(28,28,91),array(27,27,88),array(26,26,86),array(25,25,83),array(24,24,81),
			array(23,23,78),array(22,22,76),array(21,21,73),array(20,20,71),array(19,19,68),array(18,18,66),array(17,17,63),
			array(16,16,61),array(15,15,59),array(14,14,56),array(13,13,54),array(12,12,51),array(11,11,49),array(10,10,46),
			array(9,9,44),array(8,8,41),array(7,7,39),array(6,6,36)
					),
		'demandingness'	=> array(						// demandingness
			array(45,45,88),array(44,44,86),array(43,43,84),array(42,42,83),array(41,41,81),array(40,40,80),array(39,39,78),
			array(38,38,77),array(37,37,75),array(36,36,74),array(35,35,72),array(34,34,70),array(33,33,69),array(32,32,67),
			array(31,31,66),array(30,30,64),array(29,29,63),array(28,28,61),array(27,27,60),array(26,26,58),array(25,25,56),
			array(24,24,55),array(23,23,53),array(22,22,52),array(21,21,50),array(20,20,49),array(19,19,47),array(18,18,45),
			array(17,17,44),array(16,16,42),array(15,15,41),array(14,14,39),array(13,13,38),array(12,12,36),array(11,11,35),
			array(10,10,33),array(9,9,31)
					),
		'mood' => array(							// mood
			array(25,25,82),array(24,24,79),array(23,23,77),array(22,22,74),array(21,21,72),array(20,20,70),array(19,19,67),
			array(18,18,65),array(17,17,62),array(16,16,60),array(15,15,57),array(14,14,55),array(13,13,53),array(12,12,50),
			array(11,11,48),array(10,10,45),array(9,9,43),array(8,8,40),array(7,7,38),array(6,6,36),array(5,5,33)
					),
		'acceptability' => array(						// acceptabilty
			array(35,35,86),array(34,34,85),array(33,33,83),array(32,32,81),array(31,31,79),array(30,30,78),array(29,29,76),
			array(28,28,74),array(27,27,72),array(26,26,71),array(25,25,69),array(24,24,67),array(23,23,65),array(22,22,64),
			array(21,21,62),array(20,20,60),array(19,19,58),array(18,18,57),array(17,17,55),array(16,16,53),array(15,15,51),
			array(14,14,50),array(13,13,48),array(12,12,46),array(11,11,44),array(10,10,43),array(9,9,41),array(8,8,39),
			array(7,7,37)
					),
		'coompetence' => array(							// competence
			array(65,65,91),array(64,64,90),array(63,63,88),array(62,62,87),array(61,61,86),array(60,60,85),array(59,59,84),
			array(58,58,82),array(57,57,81),array(56,56,80),array(55,55,79),array(54,54,78),array(53,53,77),array(52,52,75),
			array(51,51,74),array(50,50,73),array(49,49,72),array(48,48,71),array(47,47,69),array(46,46,68),array(45,45,67),
			array(44,44,66),array(43,43,65),array(42,42,64),array(41,41,62),array(40,40,61),array(39,39,60),array(38,38,59),
			array(37,37,58),array(36,36,56),array(35,35,55),array(34,34,54),array(33,33,53),array(32,32,52),array(31,31,50),
			array(30,30,49),array(29,29,48),array(28,28,47),array(27,27,46),array(26,26,45),array(25,25,43),array(24,24,42),
			array(23,23,41),array(22,22,40),array(21,21,39),array(20,20,37),array(19,19,36),array(18,18,35),array(17,17,34),
			array(16,16,33),array(15,15,32),array(14,14,30),array(13,13,29)
					),
		'isolation' => array(							// isolation
			array(30,30,85),array(29,29,82),array(28,28,80),array(27,27,78),array(26,26,76),array(25,25,74),array(24,24,72),
			array(23,23,70),array(22,22,67),array(21,21,65),array(20,20,63),array(19,19,61),array(18,18,59),array(17,17,57),
			array(16,16,54),array(15,15,52),array(14,14,50),array(13,13,48),array(12,12,46),array(11,11,44),array(10,10,42),
			array(9,9,39),array(8,8,37),array(7,7,35),array(6,6,33)
					),
		'attachment' => array(							// Attachment
			array(35,35,86),array(34,34,84),array(33,33,82),array(32,32,81),array(31,31,79),array(30,30,77),array(29,29,76),
			array(28,28,74),array(27,27,72),array(26,26,70),array(25,25,69),array(24,24,67),array(23,23,65),array(22,22,64),
			array(21,21,62),array(20,20,60),array(19,19,59),array(18,18,57),array(17,17,55),array(16,16,54),array(15,15,52),
			array(14,14,50),array(13,13,49),array(12,12,47),array(11,11,45),array(10,10,43),array(9,9,42),array(8,8,40),
			array(7,7,38)
					),
		'health' => array(							// Health
			array(25,25,84),array(24,24,81),array(23,23,79),array(22,22,76),array(21,21,74),array(20,20,71),array(19,19,69),
			array(18,18,66),array(17,17,64),array(16,16,61),array(15,15,59),array(14,14,56),array(13,13,54),array(12,12,51),
			array(11,11,49),array(10,10,46),array(9,9,44),array(8,8,41),array(7,7,39),array(6,6,36),array(5,5,34)
					),
		'rolerestriction' => array(						// Role Restriction
			array(35,35,83),array(34,34,81),array(33,33,79),array(32,32,77),array(31,31,75),array(30,30,73),array(29,29,71),
			array(28,28,69),array(27,27,67),array(26,26,65),array(25,25,63),array(24,24,61),array(23,23,59),array(22,22,57),
			array(21,21,55),array(20,20,53),array(19,19,51),array(18,18,49),array(17,17,47),array(16,16,45),array(15,15,43),
			array(14,14,41),array(13,13,39),array(12,12,37),array(11,11,35),array(10,10,33),array(9,9,31),array(8,8,29),
			array(7,7,27)
					),
		'depression' => array(							// Depression
			array(45,45,81),array(44,44,80),array(43,43,79),array(42,42,77),array(41,41,76),array(40,40,75),array(39,39,73),
			array(38,38,72),array(37,37,71),array(36,36,70),array(35,35,68),array(34,34,67),array(33,33,66),array(32,32,64),
			array(31,31,63),array(30,30,62),array(29,29,61),array(28,28,59),array(27,27,58),array(26,26,57),array(25,25,55),
			array(24,24,54),array(23,23,53),array(22,22,52),array(21,21,50),array(20,20,49),array(19,19,48),array(18,18,46),
			array(17,17,45),array(16,16,44),array(15,15,43),array(14,14,41),array(13,13,40),array(12,12,39),array(11,11,37),
			array(10,10,36),array(9,9,35)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(35,35,76),array(34,34,74),array(33,33,73),array(32,32,71),array(31,31,70),array(30,30,68),array(29,29,67),
			array(28,28,65),array(27,27,64),array(26,26,62),array(25,25,61),array(24,24,59),array(23,23,58),array(22,22,56),
			array(21,21,55),array(20,20,53),array(19,19,52),array(18,18,50),array(17,17,49),array(16,16,47),array(15,15,46),
			array(14,14,44),array(13,13,43),array(12,12,41),array(11,11,40),array(10,10,38),array(9,9,37),array(8,8,35),
			array(7,7,34)
					),
		'childdomain' => array(							// Child Domain
			array(235,235,94),array(232,234,93),array(229,231,92),array(226,228,91),array(223,225,90),array(220,222,89),
			array(217,219,88),array(215,216,87),array(212,214,86),array(209,211,85),array(206,208,84),array(203,205,83),
			array(200,202,82),array(197,199,81),array(194,196,80),array(191,193,79),array(188,190,78),array(186,187,77),
			array(183,185,76),array(180,182,75),array(177,179,74),array(174,176,73),array(171,173,72),array(168,170,71),
			array(165,167,70),array(162,164,69),array(160,161,68),array(157,159,67),array(154,156,66),array(151,153,65),
			array(148,150,64),array(145,147,63),array(142,144,62),array(139,141,61),array(136,138,60),array(133,135,59),
			array(131,132,58),array(128,130,57),array(125,127,56),array(122,124,55),array(119,121,54),array(116,118,53),
			array(113,115,52),array(110,112,51),array(107,109,50),array(105,106,49),array(102,104,48),array(99,101,47),
			array(96,98,46),array(93,95,45),array(90,92,44),array(87,89,43),array(84,86,42),array(81,83,41),array(78,80,40),
			array(76,77,39),array(73,75,38),array(70,72,37),array(67,69,36),array(64,66,35),array(61,63,34),array(58,60,33),
			array(55,57,32),array(52,54,31),array(50,51,30),array(47,49,29)
					),
		'parentdomain' => array(						// Parent Domain
			array(268,270,89),array(264,267,88),array(261,263,87),array(257,260,86),array(253,256,85),array(250,252,84),
			array(246,249,83),array(242,245,82),array(239,241,81),array(235,238,80),array(231,234,79),array(228,230,78),
			array(224,227,77),array(220,223,76),array(217,219,75),array(213,216,74),array(209,212,73),array(206,208,72),
			array(202,205,71),array(198,201,70),array(195,197,69),array(191,194,68),array(188,190,67),array(184,187,66),
			array(180,183,65),array(177,179,64),array(173,176,63),array(169,172,62),array(166,168,61),array(162,165,60),
			array(158,161,59),array(155,157,58),array(151,154,57),array(147,150,56),array(144,146,55),array(140,143,54),
			array(136,139,53),array(133,135,52),array(129,132,51),array(125,128,50),array(122,124,49),array(118,121,48),
			array(115,117,47),array(111,114,46),array(107,110,45),array(104,106,44),array(100,103,43),array(96,99,42),
			array(93,95,41),array(89,92,40),array(85,88,39),array(82,84,38),array(78,81,37),array(74,77,36),array(71,73,35),
			array(67,70,34),array(63,66,33),array(60,62,32),array(56,59,31),array(54,55,30)
					),
		'totalstress' => array(							// Total Stress
			array(501,505,93),array(495,500,92),array(489,494,91),array(482,488,90),array(476,481,89),array(470,475,88),
			array(464,469,87),array(457,463,86),array(451,456,85),array(445,450,84),array(439,444,83),array(432,438,82),
			array(426,431,81),array(420,425,80),array(414,419,79),array(407,413,78),array(401,406,77),array(395,400,76),
			array(389,394,75),array(382,388,74),array(376,381,73),array(370,375,72),array(364,369,71),array(357,363,70),
			array(351,356,69),array(345,350,68),array(339,344,67),array(332,338,66),array(326,331,65),array(320,325,64),
			array(314,319,63),array(307,313,62),array(301,306,61),array(295,300,60),array(289,294,59),array(283,288,58),
			array(276,282,57),array(270,275,56),array(264,269,55),array(258,263,54),array(251,257,53),array(245,250,52),
			array(239,244,51),array(233,238,50),array(226,232,49),array(220,225,48),array(214,219,47),array(208,213,46),
			array(201,207,45),array(195,200,44),array(189,194,43),array(183,188,42),array(176,182,41),array(170,175,40),
			array(164,169,39),array(158,163,38),array(151,157,37),array(145,150,36),array(139,144,35),array(133,138,34),
			array(126,132,33),array(120,125,32),array(114,119,31),array(108,113,30),array(101,107,29)
					),
		'lifestress' => array(							// Life Stress
			array(79,79,">=109"),array(78,78,108),array(76,77,107),array(75,75,106),array(74,74,105),array(73,73,104),
			array(72,72,103),array(71,71,102),array(70,70,101),array(69,69,100),array(68,68,99),array(66,67,98),array(65,65,97),
			array(64,64,96),array(63,63,95),array(62,62,94),array(61,61,93),array(60,60,92),array(59,59,91),array(57,58,90),
			array(56,56,89),array(55,55,88),array(54,54,87),array(53,53,86),array(52,52,85),array(51,51,84),array(50,50,83),
			array(48,49,82),array(47,47,81),array(46,46,80),array(45,45,79),array(44,44,78),array(43,43,77),array(42,42,76),
			array(41,41,75),array(39,40,74),array(38,38,73),array(37,37,72),array(36,36,71),array(35,35,70),array(34,34,69),
			array(33,33,68),array(32,32,67),array(31,31,66),array(29,30,65),array(28,28,64),array(27,27,63),array(26,26,62),
			array(25,25,61),array(24,24,60),array(23,23,59),array(22,22,58),array(20,21,57),array(19,19,56),array(18,18,55),
			array(17,17,54),array(16,16,53),array(15,15,52),array(14,14,51),array(13,13,50),array(11,12,49),array(10,10,48),
			array(9,9,47),array(8,8,46),array(7,7,45),array(6,6,44),array(5,5,43),array(4,4,42),array(2,3,41),array(1,1,40),
			array(0,0,39)
					)
			),
	7 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(45,45,81),array(44,44,80),array(43,43,79),array(42,42,77),array(41,41,76),array(40,40,74),array(39,39,73),
			array(38,38,72),array(37,37,70),array(36,36,69),array(35,35,67),array(34,34,66),array(33,33,65),array(32,32,63),
			array(31,31,62),array(30,30,61),array(29,29,59),array(28,28,58),array(27,27,56),array(26,26,55),array(25,25,54),
			array(24,24,52),array(23,23,51),array(22,22,49),array(21,21,48),array(20,20,47),array(19,19,45),array(18,18,44),
			array(17,17,42),array(16,16,41),array(15,15,40),array(14,14,38),array(13,13,37),array(12,12,35),array(11,11,34),
			array(10,10,33),array(9,9,31)
					),
		'adaptability'	=> array(						// Adaptability
			array(55,55,89),array(54,54,88),array(53,53,87),array(52,52,85),array(51,51,84),array(50,50,83),array(49,49,82),
			array(48,48,80),array(47,47,79),array(46,46,78),array(45,45,76),array(44,44,75),array(43,43,74),array(42,42,73),
			array(41,41,71),array(40,40,70),array(39,39,69),array(38,38,68),array(37,37,66),array(36,36,65),array(35,35,64),
			array(34,34,62),array(33,33,61),array(32,32,60),array(31,31,59),array(30,30,57),array(29,29,56),array(28,28,55),
			array(27,27,54),array(26,26,52),array(25,25,51),array(24,24,50),array(23,23,48),array(22,22,47),array(21,21,46),
			array(20,20,45),array(19,19,43),array(18,18,42),array(17,17,41),array(16,16,40),array(15,15,38),array(14,14,37),
			array(13,13,36),array(12,12,34),array(11,11,33)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(30,30,92),array(29,29,90),array(28,28,87),array(27,27,85),array(26,26,83),array(25,25,80),array(24,24,78),
			array(23,23,76),array(22,22,74),array(21,21,71),array(20,20,69),array(19,19,67),array(18,18,65),array(17,17,62),
			array(16,16,60),array(15,15,58),array(14,14,56),array(13,13,53),array(12,12,51),array(11,11,49),array(10,10,47),
			array(9,9,44),array(8,8,42),array(7,7,40),array(6,6,38)
					),
		'demandingness'	=> array(						// demandingness
			array(45,45,85),array(44,44,84),array(43,43,83),array(42,42,81),array(41,41,80),array(40,40,78),array(39,39,77),
			array(38,38,76),array(37,37,74),array(36,36,73),array(35,35,71),array(34,34,70),array(33,33,69),array(32,32,67),
			array(31,31,66),array(30,30,64),array(29,29,63),array(28,28,62),array(27,27,60),array(26,26,59),array(25,25,57),
			array(24,24,56),array(23,23,55),array(22,22,53),array(21,21,52),array(20,20,51),array(19,19,49),array(18,18,48),
			array(17,17,46),array(16,16,45),array(15,15,44),array(14,14,42),array(13,13,41),array(12,12,39),array(11,11,38),
			array(10,10,37),array(9,9,35)
					),
		'mood' => array(							// mood
			array(25,25,80),array(24,24,78),array(23,23,76),array(22,22,73),array(21,21,71),array(20,20,69),array(19,19,66),
			array(18,18,64),array(17,17,62),array(16,16,60),array(15,15,57),array(14,14,55),array(13,13,53),array(12,12,51),
			array(11,11,48),array(10,10,46),array(9,9,44),array(8,8,41),array(7,7,39),array(6,6,37),array(5,5,35)
					),
		'acceptability' => array(						// acceptabilty
			array(35,35,90),array(34,34,88),array(33,33,87),array(32,32,85),array(31,31,83),array(30,30,81),array(29,29,79),
			array(28,28,77),array(27,27,75),array(26,26,74),array(25,25,72),array(24,24,70),array(23,23,68),array(22,22,66),
			array(21,21,64),array(20,20,62),array(19,19,61),array(18,18,59),array(17,17,57),array(16,16,55),array(15,15,53),
			array(14,14,51),array(13,13,49),array(12,12,48),array(11,11,46),array(10,10,44),array(9,9,42),array(8,8,40),array(7,7,38)
					),
		'coompetence' => array(							// competence
			array(65,65,93),array(64,64,92),array(63,63,91),array(62,62,90),array(61,61,88),array(60,60,87),array(59,59,86),
			array(58,58,85),array(57,57,84),array(56,56,82),array(55,55,81),array(54,54,80),array(53,53,79),array(52,52,78),
			array(51,51,77),array(50,50,75),array(49,49,74),array(48,48,73),array(47,47,72),array(46,46,71),array(45,45,69),
			array(44,44,68),array(43,43,67),array(42,42,66),array(41,41,65),array(40,40,64),array(39,39,62),array(38,38,61),
			array(37,37,60),array(36,36,59),array(35,35,58),array(34,34,56),array(33,33,55),array(32,32,54),array(31,31,53),
			array(30,30,52),array(29,29,51),array(28,28,49),array(27,27,48),array(26,26,47),array(25,25,46),array(24,24,45),
			array(23,23,43),array(22,22,42),array(21,21,41),array(20,20,40),array(19,19,39),array(18,18,38),array(17,17,36),
			array(16,16,35),array(15,15,34),array(14,14,33),array(13,13,32)
					),
		'isolation' => array(							// isolation
			array(30,30,87),array(29,29,85),array(28,28,83),array(27,27,80),array(26,26,78),array(25,25,76),array(24,24,74),
			array(23,23,72),array(22,22,69),array(21,21,67),array(20,20,65),array(19,19,63),array(18,18,61),array(17,17,59),
			array(16,16,56),array(15,15,54),array(14,14,52),array(13,13,50),array(12,12,48),array(11,11,45),array(10,10,43),
			array(9,9,41),array(8,8,39),array(7,7,37),array(6,6,35)
					),
		'attachment' => array(							// Attachment
			array(35,35,95),array(34,34,93),array(33,33,91),array(32,32,89),array(31,31,87),array(30,30,85),array(29,29,83),
			array(28,28,81),array(27,27,79),array(26,26,77),array(25,25,75),array(24,24,73),array(23,23,71),array(22,22,69),
			array(21,21,67),array(20,20,65),array(19,19,63),array(18,18,61),array(17,17,59),array(16,16,57),array(15,15,55),
			array(14,14,53),array(13,13,51),array(12,12,49),array(11,11,47),array(10,10,45),array(9,9,43),array(8,8,41),
			array(7,7,39)
					),
		'health' => array(							// Health
			array(25,25,89),array(24,24,86),array(23,23,83),array(22,22,81),array(21,21,78),array(20,20,75),array(19,19,73),
			array(18,18,70),array(17,17,67),array(16,16,65),array(15,15,62),array(14,14,59),array(13,13,57),array(12,12,54),
			array(11,11,51),array(10,10,49),array(9,9,46),array(8,8,44),array(7,7,41),array(6,6,38),array(5,5,36)
					),
		'rolerestriction' => array(						// Role Restriction
			array(35,35,87),array(34,34,85),array(33,33,83),array(32,32,81),array(31,31,79),array(30,30,77),array(29,29,75),
			array(28,28,73),array(27,27,71),array(26,26,69),array(25,25,67),array(24,24,65),array(23,23,63),array(22,22,61),
			array(21,21,59),array(20,20,57),array(19,19,55),array(18,18,53),array(17,17,51),array(16,16,49),array(15,15,47),
			array(14,14,45),array(13,13,43),array(12,12,41),array(11,11,39),array(10,10,37),array(9,9,35),array(8,8,33),
			array(7,7,31)
					),
		'depression' => array(							// Depression
			array(45,45,87),array(44,44,86),array(43,43,84),array(42,42,83),array(41,41,82),array(40,40,80),array(39,39,79),
			array(38,38,77),array(37,37,76),array(36,36,75),array(35,35,73),array(34,34,72),array(33,33,70),array(32,32,69),
			array(31,31,68),array(30,30,66),array(29,29,65),array(28,28,63),array(27,27,62),array(26,26,61),array(25,25,59),
			array(24,24,58),array(23,23,57),array(22,22,55),array(21,21,54),array(20,20,52),array(19,19,51),array(18,18,50),
			array(17,17,48),array(16,16,47),array(15,15,45),array(14,14,44),array(13,13,43),array(12,12,41),array(11,11,40),
			array(10,10,39),array(9,9,37)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(35,35,77),array(34,34,75),array(33,33,74),array(32,32,72),array(31,31,71),array(30,30,70),array(29,29,68),
			array(28,28,67),array(27,27,65),array(26,26,64),array(25,25,62),array(24,24,61),array(23,23,60),array(22,22,58),
			array(21,21,57),array(20,20,55),array(19,19,54),array(18,18,52),array(17,17,51),array(16,16,50),array(15,15,48),
			array(14,14,47),array(13,13,45),array(12,12,44),array(11,11,42),array(10,10,41),array(9,9,40),array(8,8,38),
			array(7,7,37)
					),
		'childdomain' => array(							// Child Domain
			array(233,235,90),array(230,232,89),array(226,229,88),array(223,225,87),array(220,222,86),array(216,219,85),
			array(213,215,84),array(210,212,83),array(207,209,82),array(203,206,81),array(200,202,80),array(197,199,79),
			array(193,196,78),array(190,192,77),array(187,189,76),array(184,186,75),array(180,183,74),array(177,179,73),
			array(174,176,72),array(171,173,71),array(167,170,70),array(164,166,69),array(161,163,68),array(157,160,67),
			array(154,156,66),array(151,153,65),array(148,150,64),array(144,147,63),array(141,143,62),array(138,140,61),
			array(134,137,60),array(131,133,59),array(128,130,58),array(125,127,57),array(121,124,56),array(118,120,55),
			array(115,117,54),array(112,114,53),array(108,111,52),array(105,107,51),array(102,104,50),array(98,101,49),
			array(95,97,48),array(92,94,47),array(89,91,46),array(85,88,45),array(82,84,44),array(79,81,43),array(75,78,42),
			array(72,74,41),array(69,71,40),array(66,68,39),array(62,65,38),array(59,61,37),array(56,58,36),array(53,55,35),
			array(49,52,34),array(47,48,33)
					),
		'parentdomain' => array(						// Parent Domain
			array(270,270,94),array(267,269,93),array(263,266,92),array(260,262,91),array(256,259,90),array(253,255,89),
			array(249,252,88),array(245,248,87),array(242,244,86),array(238,241,85),array(235,237,84),array(231,234,83),
			array(228,230,82),array(224,227,81),array(221,223,80),array(217,220,79),array(213,216,78),array(210,212,77),
			array(206,209,76),array(203,205,75),array(199,202,74),array(196,198,73),array(192,195,72),array(189,191,71),
			array(185,188,70),array(182,184,69),array(178,181,68),array(174,177,67),array(171,173,66),array(167,170,65),
			array(164,166,64),array(160,163,63),array(157,159,62),array(153,156,61),array(150,152,60),array(146,149,59),
			array(143,145,58),array(139,142,57),array(135,138,56),array(132,134,55),array(128,131,54),array(125,127,53),
			array(121,124,52),array(118,120,51),array(114,117,50),array(111,113,49),array(107,110,48),array(104,106,47),
			array(100,103,46),array(96,99,45),array(93,95,44),array(89,92,43),array(86,88,42),array(82,85,41),array(79,81,40),
			array(75,78,39),array(72,74,38),array(68,71,37),array(64,67,36),array(61,63,35),array(57,60,34),array(54,56,33)
					),
		'totalstress' => array(							// Total Stress
			array(504,505,94),array(498,503,93),array(491,497,92),array(485,490,91),array(478,484,90),array(472,477,89),
			array(465,471,88),array(458,464,87),array(452,457,86),array(445,451,85),array(439,444,84),array(432,438,83),
			array(426,431,82),array(419,425,81),array(412,418,80),array(406,411,79),array(399,405,78),array(393,398,77),
			array(386,392,76),array(380,385,75),array(373,379,74),array(366,372,73),array(360,365,72),array(353,359,71),
			array(347,352,70),array(340,346,69),array(334,339,68),array(327,333,67),array(321,326,66),array(314,320,65),
			array(307,313,64),array(301,306,63),array(294,300,62),array(288,293,61),array(281,287,60),array(275,280,59),
			array(268,274,58),array(261,267,57),array(255,260,56),array(248,254,55),array(242,247,54),array(235,241,53),
			array(229,234,52),array(222,228,51),array(215,221,50),array(209,214,49),array(202,208,48),array(196,201,47),
			array(189,195,46),array(183,188,45),array(176,182,44),array(170,175,43),array(163,169,42),array(156,162,41),
			array(150,155,40),array(143,149,39),array(137,142,38),array(130,136,37),array(124,129,36),array(117,123,35),
			array(110,116,34),array(104,109,33),array(101,103,32)
					),
		'lifestress' => array(							// Life Stress
			array(79,79,">=134"),array(78,78,133),array(77,77,132),array(76,76,131),array(75,75,130),array(74,74,129),
			array(73,73,127),array(72,72,126),array(71,71,125),array(70,70,124),array(69,69,123),array(68,68,121),
			array(67,67,120),array(66,66,119),array(65,65,118),array(64,64,117),array(63,63,115),array(62,62,114),
			array(61,61,113),array(60,60,112),array(59,59,111),array(58,58,109),array(57,57,108),array(56,56,107),
			array(55,55,106),array(54,54,105),array(53,53,103),array(52,52,102),array(51,51,101),array(50,50,100),
			array(49,49,99),array(48,48,97),array(47,47,96),array(46,46,95),array(45,45,94),array(44,44,93),array(43,43,91),
			array(42,42,90),array(41,41,89),array(40,40,88),array(39,39,87),array(38,38,85),array(37,37,84),array(36,36,83),
			array(35,35,82),array(34,34,81),array(33,33,79),array(32,32,78),array(31,31,77),array(30,30,76),array(29,29,75),
			array(28,28,73),array(27,27,72),array(26,26,71),array(25,25,70),array(24,24,69),array(23,23,68),array(22,22,66),
			array(21,21,65),array(20,20,64),array(19,19,63),array(18,18,62),array(17,17,60),array(16,16,59),array(15,15,58),
			array(14,14,57),array(13,13,56),array(12,12,54),array(11,11,53),array(10,10,52),array(9,9,51),array(8,8,50),
			array(7,7,48),array(6,6,47),array(5,5,46),array(4,4,45),array(3,3,44),array(2,2,42),array(1,1,41),array(0,0,40)
					)
			),
	8 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(45,45,85),array(44,44,84),array(43,43,82),array(42,42,81),array(41,41,79),array(40,40,78),array(39,39,77),
			array(38,38,75),array(37,37,74),array(36,36,72),array(35,35,71),array(34,34,69),array(33,33,68),array(32,32,66),
			array(31,31,65),array(30,30,63),array(29,29,62),array(28,28,60),array(27,27,59),array(26,26,57),array(25,25,56),
			array(24,24,54),array(23,23,53),array(22,22,52),array(21,21,50),array(20,20,49),array(19,19,47),array(18,18,46),
			array(17,17,44),array(16,16,43),array(15,15,41),array(14,14,40),array(13,13,38),array(12,12,37),array(11,11,35),
			array(10,10,34),array(9,9,32)
					),
		'adaptability'	=> array(						// Adaptability
			array(55,55,96),array(54,54,95),array(53,53,93),array(52,52,92),array(51,51,91),array(50,50,89),array(49,49,88),
			array(48,48,87),array(47,47,85),array(46,46,84),array(45,45,82),array(44,44,81),array(43,43,80),array(42,42,78),
			array(41,41,77),array(40,40,75),array(39,39,74),array(38,38,73),array(37,37,71),array(36,36,70),array(35,35,69),
			array(34,34,67),array(33,33,66),array(32,32,64),array(31,31,63),array(30,30,62),array(29,29,60),array(28,28,59),
			array(27,27,57),array(26,26,56),array(25,25,55),array(24,24,53),array(23,23,52),array(22,22,51),array(21,21,49),
			array(20,20,48),array(19,19,46),array(18,18,45),array(17,17,44),array(16,16,42),array(15,15,41),array(14,14,40),
			array(13,13,38),array(12,12,37),array(11,11,35)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(30,30,99),array(29,29,96),array(28,28,94),array(27,27,91),array(26,26,89),array(25,25,86),array(24,24,83),
			array(23,23,81),array(22,22,78),array(21,21,76),array(20,20,73),array(19,19,71),array(18,18,68),array(17,17,65),
			array(16,16,63),array(15,15,60),array(14,14,58),array(13,13,55),array(12,12,53),array(11,11,50),array(10,10,47),
			array(9,9,45),array(8,8,42),array(7,7,40),array(6,6,37)
					),
		'demandingness'	=> array(						// demandingness
			array(45,45,90),array(44,44,88),array(43,43,87),array(42,42,85),array(41,41,84),array(40,40,82),array(39,39,81),
			array(38,38,79),array(37,37,78),array(36,36,76),array(35,35,75),array(34,34,73),array(33,33,72),array(32,32,70),
			array(31,31,69),array(30,30,67),array(29,29,66),array(28,28,64),array(27,27,63),array(26,26,61),array(25,25,60),
			array(24,24,58),array(23,23,57),array(22,22,55),array(21,21,53),array(20,20,52),array(19,19,50),array(18,18,49),
			array(17,17,47),array(16,16,46),array(15,15,44),array(14,14,43),array(13,13,41),array(12,12,40),array(11,11,38),
			array(10,10,37),array(9,9,35)
					),
		'mood' => array(							// mood
			array(25,25,89),array(24,24,86),array(23,23,83),array(22,22,81),array(21,21,78),array(20,20,75),array(19,19,72),
			array(18,18,70),array(17,17,67),array(16,16,64),array(15,15,62),array(14,14,59),array(13,13,56),array(12,12,54),
			array(11,11,51),array(10,10,48),array(9,9,45),array(8,8,43),array(7,7,40),array(6,6,37),array(5,5,35)
					),
		'acceptability' => array(						// acceptabilty
			array(35,35,88),array(34,34,87),array(33,33,85),array(32,32,83),array(31,31,81),array(30,30,80),array(29,29,78),
			array(28,28,76),array(27,27,75),array(26,26,73),array(25,25,71),array(24,24,69),array(23,23,68),array(22,22,66),
			array(21,21,64),array(20,20,63),array(19,19,61),array(18,18,59),array(17,17,57),array(16,16,56),array(15,15,54),
			array(14,14,52),array(13,13,51),array(12,12,49),array(11,11,47),array(10,10,45),array(9,9,44),array(8,8,42),array(7,7,40)
					),
		'coompetence' => array(							// competence
			array(65,65,92),array(64,64,91),array(63,63,90),array(62,62,89),array(61,61,87),array(60,60,86),
			array(59,59,85),array(58,58,84),array(57,57,83),array(56,56,82),array(55,55,80),array(54,54,79),
			array(53,53,78),array(52,52,77),array(51,51,76),array(50,50,75),array(49,49,74),array(48,48,72),
			array(47,47,71),array(46,46,70),array(45,45,69),array(44,44,68),array(43,43,67),array(42,42,66),
			array(41,41,64),array(40,40,63),array(39,39,62),array(38,38,61),array(37,37,60),array(36,36,59),
			array(35,35,57),array(34,34,56),array(33,33,55),array(32,32,54),array(31,31,53),array(30,30,52),
			array(29,29,51),array(28,28,49),array(27,27,48),array(26,26,47),array(25,25,46),array(24,24,45),
			array(23,23,44),array(22,22,43),array(21,21,41),array(20,20,40),array(19,19,39),array(18,18,38),
			array(17,17,37),array(16,16,36),array(15,15,35),array(14,14,33),array(13,13,32)
					),
		'isolation' => array(							// isolation
			array(30,30,88),array(29,29,86),array(28,28,84),array(27,27,82),array(26,26,80),array(25,25,77),array(24,24,75),
			array(23,23,73),array(22,22,71),array(21,21,69),array(20,20,66),array(19,19,64),array(18,18,62),array(17,17,60),
			array(16,16,58),array(15,15,55),array(14,14,53),array(13,13,51),array(12,12,49),array(11,11,47),array(10,10,44),
			array(9,9,42),array(8,8,40),array(7,7,38),array(6,6,35)
					),
		'attachment' => array(							// Attachment
			array(35,35,95),array(34,34,93),array(33,33,91),array(32,32,89),array(31,31,87),array(30,30,85),array(29,29,83),
			array(28,28,81),array(27,27,79),array(26,26,77),array(25,25,75),array(24,24,73),array(23,23,71),array(22,22,69),
			array(21,21,67),array(20,20,65),array(19,19,63),array(18,18,61),array(17,17,59),array(16,16,57),array(15,15,55),
			array(14,14,53),array(13,13,51),array(12,12,50),array(11,11,48),array(10,10,46),array(9,9,44),array(8,8,42),array(7,7,40)
					),
		'health' => array(							// Health
			array(25,25,84),array(24,24,81),array(23,23,79),array(22,22,77),array(21,21,74),array(20,20,72),
			array(19,19,70),array(18,18,68),array(17,17,65),array(16,16,63),array(15,15,61),array(14,14,58),
			array(13,13,56),array(12,12,54),array(11,11,51),array(10,10,49),array(9,9,47),array(8,8,44),
			array(7,7,42),array(6,6,40),array(5,5,38)
					),
		'rolerestriction' => array(						// Role Restriction
			array(35,35,88),array(34,34,86),array(33,33,84),array(32,32,82),array(31,31,80),array(30,30,78),array(29,29,76),
			array(28,28,74),array(27,27,71),array(26,26,69),array(25,25,67),array(24,24,65),array(23,23,63),array(22,22,61),
			array(21,21,59),array(20,20,57),array(19,19,55),array(18,18,53),array(17,17,51),array(16,16,49),array(15,15,47),
			array(14,14,45),array(13,13,43),array(12,12,41),array(11,11,39),array(10,10,37),array(9,9,34),array(8,8,32),array(7,7,30)
					),
		'depression' => array(							// Depression
			array(45,45,91),array(44,44,89),array(43,43,88),array(42,42,86),array(41,41,85),array(40,40,83),array(39,39,82),
			array(38,38,80),array(37,37,79),array(36,36,77),array(35,35,76),array(34,34,74),array(33,33,73),array(32,32,72),
			array(31,31,70),array(30,30,69),array(29,29,67),array(28,28,66),array(27,27,64),array(26,26,63),array(25,25,61),
			array(24,24,60),array(23,23,58),array(22,22,57),array(21,21,55),array(20,20,54),array(19,19,52),array(18,18,51),
			array(17,17,49),array(16,16,48),array(15,15,47),array(14,14,45),array(13,13,44),array(12,12,42),array(11,11,41),
			array(10,10,39),array(9,9,38)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(35,35,79),array(34,34,78),array(33,33,76),array(32,32,74),array(31,31,73),array(30,30,71),array(29,29,70),
			array(28,28,68),array(27,27,66),array(26,26,65),array(25,25,63),array(24,24,61),array(23,23,60),array(22,22,58),
			array(21,21,56),array(20,20,55),array(19,19,53),array(18,18,52),array(17,17,50),array(16,16,48),array(15,15,47),
			array(14,14,45),array(13,13,43),array(12,12,42),array(11,11,40),array(10,10,39),array(9,9,37),array(8,8,35),array(7,7,34)
					),
		'childdomain' => array(							// Child Domain
			array(234,235,96),array(231,233,95),array(228,230,94),array(225,227,93),array(222,224,92),array(219,221,91),
			array(216,218,90),array(213,215,89),array(210,212,88),array(207,209,87),array(204,206,86),array(201,203,85),
			array(198,200,84),array(195,197,83),array(192,194,82),array(188,191,81),array(185,187,80),array(182,184,79),
			array(179,181,78),array(176,178,77),array(173,175,76),array(170,172,75),array(167,169,74),array(164,166,73),
			array(161,163,72),array(158,160,71),array(155,157,70),array(152,154,69),array(149,151,68),array(146,148,67),
			array(143,145,66),array(140,142,65),array(137,139,64),array(134,136,63),array(131,133,62),array(128,130,61),
			array(125,127,60),array(122,124,59),array(119,121,58),array(116,118,57),array(113,115,56),array(110,112,55),
			array(107,109,54),array(104,106,53),array(101,103,52),array(98,100,51),array(95,97,50),array(92,94,49),
			array(89,91,48),array(85,88,47),array(82,84,46),array(79,81,45),array(76,78,44),array(73,75,43),array(70,72,42),
			array(67,69,41),array(64,66,40),array(61,63,39),array(58,60,38),array(55,57,37),array(52,54,36),array(49,51,35),
			array(47,48,34)
					),
		'parentdomain' => array(						// Parent Domain
			array(269,270,97),array(266,268,96),array(263,265,95),array(260,262,94),array(256,259,93),array(253,255,92),
			array(250,252,91),array(246,249,90),array(243,245,89),array(240,242,88),array(236,239,87),array(233,235,86),
			array(230,232,85),array(226,229,84),array(223,225,83),array(220,222,82),array(216,219,81),array(213,215,80),
			array(210,212,79),array(206,209,78),array(203,205,77),array(200,202,76),array(196,199,75),array(193,195,74),
			array(190,192,73),array(187,189,72),array(183,186,71),array(180,182,70),array(177,179,69),array(173,176,68),
			array(170,172,67),array(167,169,66),array(163,166,65),array(160,162,64),array(157,159,63),array(153,156,62),
			array(150,152,61),array(147,149,60),array(143,146,59),array(140,142,58),array(137,139,57),array(133,136,56),
			array(130,132,55),array(127,129,54),array(123,126,53),array(120,122,52),array(117,119,51),array(113,116,50),
			array(110,112,49),array(107,109,48),array(104,106,47),array(100,103,46),array(97,99,45),array(94,96,44),
			array(90,93,43),array(87,89,42),array(84,86,41),array(80,83,40),array(77,79,39),array(74,76,38),array(70,73,37),
			array(67,69,36),array(64,66,35),array(60,63,34),array(57,59,33),array(54,56,32)
					),
		'totalstress' => array(							// Total Stress
			array(501,505,98),array(494,500,97),array(488,493,96),array(482,487,95),array(476,481,94),array(470,475,93),
			array(464,469,92),array(458,463,91),array(452,457,90),array(446,451,89),array(440,445,88),array(433,439,87),
			array(427,432,86),array(421,426,85),array(415,420,84),array(409,414,83),array(403,408,82),array(397,402,81),
			array(391,396,80),array(385,390,79),array(378,384,78),array(372,377,77),array(366,371,76),array(360,365,75),
			array(354,359,74),array(348,353,73),array(342,347,72),array(336,341,71),array(330,335,70),array(324,329,69),
			array(317,323,68),array(311,316,67),array(305,310,66),array(299,304,65),array(293,298,64),array(287,292,63),
			array(281,286,62),array(275,280,61),array(269,274,60),array(263,268,59),array(256,262,58),array(250,255,57),
			array(244,249,56),array(238,243,55),array(232,237,54),array(226,231,53),array(220,225,52),array(214,219,51),
			array(208,213,50),array(202,207,49),array(195,201,48),array(189,194,47),array(183,188,46),array(177,182,45),
			array(171,176,44),array(165,170,43),array(159,164,42),array(153,158,41),array(147,152,40),array(141,146,39),
			array(134,140,38),array(128,133,37),array(122,127,36),array(116,121,35),array(110,115,34),array(104,109,33),
			array(101,103,32)
					),
		'lifestress' => array(							// Life Stress
			array(79,79,">=116"),array(78,78,115),array(77,77,114),array(76,76,113),array(75,75,112),array(74,74,111),
			array(73,73,110),array(72,72,109),array(71,71,108),array(70,70,107),array(69,69,106),array(68,68,105),
			array(67,67,104),array(66,66,103),array(65,65,102),array(64,64,101),array(63,63,100),array(62,62,99),
			array(61,61,98),array(59,60,97),array(58,58,96),array(57,57,95),array(56,56,94),array(55,55,93),array(54,54,92),
			array(53,53,91),array(52,52,90),array(51,51,89),array(50,50,88),array(49,49,87),array(48,48,86),array(47,47,85),
			array(46,46,84),array(45,45,83),array(44,44,82),array(43,43,81),array(42,42,80),array(41,41,79),array(40,40,78),
			array(39,39,77),array(38,38,76),array(37,37,75),array(36,36,74),array(35,35,73),array(34,34,72),array(32,33,71),
			array(31,31,70),array(30,30,69),array(29,29,68),array(28,28,67),array(27,27,66),array(26,26,65),array(25,25,64),
			array(24,24,63),array(23,23,62),array(22,22,61),array(21,21,60),array(20,20,59),array(19,19,58),array(18,18,57),
			array(17,17,56),array(16,16,55),array(15,15,54),array(14,14,53),array(13,13,52),array(12,12,51),array(11,11,50),
			array(10,10,49),array(9,9,48),array(8,8,47),array(7,7,46),array(5,6,45),array(4,4,44),array(3,3,43),array(2,2,42),
			array(1,1,41),array(0,0,40)
					)
				),
	9 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(45,45,80),array(44,44,79),array(43,43,78),array(42,42,76),array(41,41,75),array(40,40,74),array(39,39,72),
			array(38,38,71),array(37,37,70),array(36,36,68),array(35,35,67),array(34,34,65),array(33,33,64),array(32,32,63),
			array(31,31,61),array(30,30,60),array(29,29,59),array(28,28,57),array(27,27,56),array(26,26,55),array(25,25,53),
			array(24,24,52),array(23,23,51),array(22,22,49),array(21,21,48),array(20,20,47),array(19,19,45),array(18,18,44),
			array(17,17,43),array(16,16,41),array(15,15,40),array(14,14,39),array(13,13,37),array(12,12,36),array(11,11,35),
			array(10,10,33),array(9,9,32)
					),
		'adaptability'	=> array(						// Adaptability
			array(55,55,88),array(54,54,87),array(53,53,86),array(52,52,84),array(51,51,83),array(50,50,82),array(49,49,81),
			array(48,48,80),array(47,47,78),array(46,46,77),array(45,45,76),array(44,44,75),array(43,43,73),array(42,42,72),
			array(41,41,71),array(40,40,70),array(39,39,68),array(38,38,67),array(37,37,66),array(36,36,65),array(35,35,63),
			array(34,34,62),array(33,33,61),array(32,32,60),array(31,31,58),array(30,30,57),array(29,29,56),array(28,28,55),
			array(27,27,53),array(26,26,52),array(25,25,51),array(24,24,50),array(23,23,48),array(22,22,47),array(21,21,46),
			array(20,20,45),array(19,19,43),array(18,18,42),array(17,17,41),array(16,16,40),array(15,15,38),array(14,14,37),
			array(13,13,36),array(12,12,35),array(11,11,34)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(30,30,88),array(29,29,86),array(28,28,84),array(27,27,81),array(26,26,79),array(25,25,77),array(24,24,75),
			array(23,23,73),array(22,22,71),array(21,21,68),array(20,20,66),array(19,19,64),array(18,18,62),array(17,17,60),
			array(16,16,58),array(15,15,56),array(14,14,53),array(13,13,51),array(12,12,49),array(11,11,47),array(10,10,45),
			array(9,9,43),array(8,8,40),array(7,7,38),array(6,6,36)
					),
		'demandingness'	=> array(						// demandingness
			array(45,45,83),array(44,44,82),array(43,43,81),array(42,42,79),array(41,41,78),array(40,40,77),array(39,39,75),
			array(38,38,74),array(37,37,73),array(36,36,71),array(35,35,70),array(34,34,69),array(33,33,67),array(32,32,66),
			array(31,31,64),array(30,30,63),array(29,29,62),array(28,28,60),array(27,27,59),array(26,26,58),array(25,25,56),
			array(24,24,55),array(23,23,54),array(22,22,52),array(21,21,51),array(20,20,49),array(19,19,48),array(18,18,47),
			array(17,17,45),array(16,16,44),array(15,15,43),array(14,14,41),array(13,13,40),array(12,12,39),array(11,11,37),
			array(10,10,36),array(9,9,34)
					),
		'mood' => array(							// mood
			array(25,25,80),array(24,24,77),array(23,23,75),array(22,22,73),array(21,21,71),array(20,20,68),array(19,19,66),
			array(18,18,64),array(17,17,61),array(16,16,59),array(15,15,57),array(14,14,54),array(13,13,52),array(12,12,50),
			array(11,11,48),array(10,10,45),array(9,9,43),array(8,8,41),array(7,7,38),array(6,6,36),array(5,5,34)
					),
		'acceptability' => array(						// acceptabilty
			array(35,35,86),array(34,34,84),array(33,33,82),array(32,32,81),array(31,31,79),array(30,30,77),array(29,29,76),
			array(28,28,74),array(27,27,72),array(26,26,71),array(25,25,69),array(24,24,67),array(23,23,66),array(22,22,64),
			array(21,21,62),array(20,20,61),array(19,19,59),array(18,18,57),array(17,17,56),array(16,16,54),array(15,15,53),
			array(14,14,51),array(13,13,49),array(12,12,48),array(11,11,46),array(10,10,44),array(9,9,43),array(8,8,41),array(7,7,39)
					),
		'coompetence' => array(							// competence
			array(65,65,90),array(64,64,89),array(63,63,88),array(62,62,87),array(61,61,86),array(60,60,85),array(59,59,83),
			array(58,58,82),array(57,57,81),array(56,56,80),array(55,55,79),array(54,54,77),array(53,53,76),array(52,52,75),
			array(51,51,74),array(50,50,73),array(49,49,72),array(48,48,70),array(47,47,69),array(46,46,68),array(45,45,67),
			array(44,44,66),array(43,43,64),array(42,42,63),array(41,41,62),array(40,40,61),array(39,39,60),array(38,38,58),
			array(37,37,57),array(36,36,56),array(35,35,55),array(34,34,54),array(33,33,53),array(32,32,51),array(31,31,50),
			array(30,30,49),array(29,29,48),array(28,28,47),array(27,27,45),array(26,26,44),array(25,25,43),array(24,24,42),
			array(23,23,41),array(22,22,39),array(21,21,38),array(20,20,37),array(19,19,36),array(18,18,35),array(17,17,34),
			array(16,16,32),array(15,15,31),array(14,14,30),array(13,13,29)
					),
		'isolation' => array(							// isolation
			array(30,30,82),array(29,29,80),array(28,28,78),array(27,27,76),array(26,26,74),array(25,25,72),array(24,24,70),
			array(23,23,68),array(22,22,66),array(21,21,64),array(20,20,62),array(19,19,60),array(18,18,59),array(17,17,57),
			array(16,16,55),array(15,15,53),array(14,14,51),array(13,13,49),array(12,12,47),array(11,11,45),array(10,10,43),
			array(9,9,41),array(8,8,39),array(7,7,37),array(6,6,36)
					),
		'attachment' => array(							// Attachment
			array(35,35,88),array(34,34,87),array(33,33,85),array(32,32,83),array(31,31,81),array(30,30,79),array(29,29,77),
			array(28,28,76),array(27,27,74),array(26,26,72),array(25,25,70),array(24,24,68),array(23,23,66),array(22,22,64),
			array(21,21,63),array(20,20,61),array(19,19,59),array(18,18,57),array(17,17,55),array(16,16,53),array(15,15,51),
			array(14,14,50),array(13,13,48),array(12,12,46),array(11,11,44),array(10,10,42),array(9,9,40),array(8,8,39),array(7,7,37)
					),
		'health' => array(							// Health
			array(25,25,81),array(24,24,79),array(23,23,76),array(22,22,74),array(21,21,72),array(20,20,70),array(19,19,67),
			array(18,18,65),array(17,17,63),array(16,16,60),array(15,15,58),array(14,14,56),array(13,13,54),array(12,12,51),
			array(11,11,49),array(10,10,47),array(9,9,44),array(8,8,42),array(7,7,40),array(6,6,38),array(5,5,35)
					),
		'rolerestriction' => array(						// Role Restriction
			array(35,35,79),array(34,34,77),array(33,33,76),array(32,32,74),array(31,31,72),array(30,30,70),array(29,29,69),
			array(28,28,67),array(27,27,65),array(26,26,63),array(25,25,61),array(24,24,60),array(23,23,58),array(22,22,56),
			array(21,21,54),array(20,20,53),array(19,19,51),array(18,18,49),array(17,17,47),array(16,16,46),array(15,15,44),
			array(14,14,42),array(13,13,40),array(12,12,39),array(11,11,37),array(10,10,35),array(9,9,33),array(8,8,31),
			array(7,7,30)
					),
		'depression' => array(							// Depression
			array(45,45,85),array(44,44,84),array(43,43,83),array(42,42,81),array(41,41,80),array(40,40,78),array(39,39,77),
			array(38,38,76),array(37,37,74),array(36,36,73),array(35,35,71),array(34,34,70),array(33,33,69),array(32,32,67),
			array(31,31,66),array(30,30,64),array(29,29,63),array(28,28,62),array(27,27,60),array(26,26,59),array(25,25,57),
			array(24,24,56),array(23,23,55),array(22,22,53),array(21,21,52),array(20,20,50),array(19,19,49),array(18,18,48),
			array(17,17,46),array(16,16,45),array(15,15,43),array(14,14,42),array(13,13,41),array(12,12,39),array(11,11,38),
			array(10,10,37),array(9,9,35)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(35,35,74),array(34,34,72),array(33,33,71),array(32,32,70),array(31,31,68),array(30,30,67),array(29,29,65),
			array(28,28,64),array(27,27,62),array(26,26,61),array(25,25,60),array(24,24,58),array(23,23,57),array(22,22,55),
			array(21,21,54),array(20,20,52),array(19,19,51),array(18,18,50),array(17,17,48),array(16,16,47),array(15,15,45),
			array(14,14,44),array(13,13,42),array(12,12,41),array(11,11,40),array(10,10,38),array(9,9,37),array(8,8,35),
			array(7,7,34)
					),
		'childdomain' => array(							// Child Domain
			array(235,235,89),array(231,234,88),array(228,230,87),array(225,227,86),array(221,224,85),array(218,220,84),
			array(215,217,83),array(211,214,82),array(208,210,81),array(205,207,80),array(201,204,79),array(198,200,78),
			array(194,197,77),array(191,193,76),array(188,190,75),array(184,187,74),array(181,183,73),array(178,180,72),
			array(174,177,71),array(171,173,70),array(168,170,69),array(164,167,68),array(161,163,67),array(158,160,66),
			array(154,157,65),array(151,153,64),array(148,150,63),array(144,147,62),array(141,143,61),array(137,140,60),
			array(134,136,59),array(131,133,58),array(127,130,57),array(124,126,56),array(121,123,55),array(117,120,54),
			array(114,116,53),array(111,113,52),array(107,110,51),array(104,106,50),array(101,103,49),array(97,100,48),
			array(94,96,47),array(90,93,46),array(87,89,45),array(84,86,44),array(80,83,43),array(77,79,42),array(74,76,41),
			array(70,73,40),array(67,69,39),array(64,66,38),array(60,63,37),array(57,59,36),array(54,56,35),array(50,53,34),
			array(47,49,33)
					),
		'parentdomain' => array(						// Parent Domain
			array(268,270,89),array(264,267,88),array(260,263,87),array(257,259,86),array(253,256,85),array(250,252,84),
			array(246,249,83),array(242,245,82),array(239,241,81),array(235,238,80),array(231,234,79),array(228,230,78),
			array(224,227,77),array(220,223,76),array(217,219,75),array(213,216,74),array(209,212,73),array(206,208,72),
			array(202,205,71),array(198,201,70),array(195,197,69),array(191,194,68),array(187,190,67),array(184,186,66),
			array(180,183,65),array(176,179,64),array(173,175,63),array(169,172,62),array(165,168,61),array(162,164,60),
			array(158,161,59),array(154,157,58),array(151,153,57),array(147,150,56),array(143,146,55),array(140,142,54),
			array(136,139,53),array(132,135,52),array(129,131,51),array(125,128,50),array(122,124,49),array(118,121,48),
			array(114,117,47),array(111,113,46),array(107,110,45),array(103,106,44),array(100,102,43),array(96,99,42),
			array(92,95,41),array(89,91,40),array(85,88,39),array(81,84,38),array(78,80,37),array(74,77,36),array(70,73,35),
			array(67,69,34),array(63,66,33),array(59,62,32),array(56,58,31),array(54,55,30)
					),
		'totalstress' => array(							// Total Stress
			array(504,505,91),array(498,503,90),array(491,497,89),array(484,490,88),array(478,483,87),array(471,477,86),
			array(464,470,85),array(457,463,84),array(451,456,83),array(444,450,82),array(437,443,81),array(430,436,80),
			array(424,429,79),array(417,423,78),array(410,416,77),array(404,409,76),array(397,403,75),array(390,396,74),
			array(383,389,73),array(377,382,72),array(370,376,71),array(363,369,70),array(356,362,69),array(350,355,68),
			array(343,349,67),array(336,342,66),array(330,335,65),array(323,329,64),array(316,322,63),array(309,315,62),
			array(303,308,61),array(296,302,60),array(289,295,59),array(283,288,58),array(276,282,57),array(269,275,56),
			array(262,268,55),array(256,261,54),array(249,255,53),array(242,248,52),array(235,241,51),array(229,234,50),
			array(222,228,49),array(215,221,48),array(209,214,47),array(202,208,46),array(195,201,45),array(188,194,44),
			array(182,187,43),array(175,181,42),array(168,174,41),array(161,167,40),array(155,160,39),array(148,154,38),
			array(141,147,37),array(135,140,36),array(128,134,35),array(121,127,34),array(114,120,33),array(108,113,32),
			array(101,107,31)
					),
		'lifestress' => array(							// Life Stress
			array(79,79,">=114"),array(78,78,113),array(77,77,112),array(76,76,111),array(75,75,110),array(73,74,109),
			array(72,72,108),array(71,71,107),array(70,70,106),array(69,69,105),array(68,68,104),array(67,67,103),
			array(66,66,102),array(65,65,101),array(64,64,100),array(63,63,99),array(62,62,98),array(61,61,97),array(59,60,96),
			array(58,58,95),array(57,57,94),array(56,56,93),array(55,55,92),array(54,54,91),array(53,53,90),array(52,52,89),
			array(51,51,88),array(50,50,87),array(49,49,86),array(48,48,85),array(47,47,84),array(45,46,83),array(44,44,82),
			array(43,43,81),array(42,42,80),array(41,41,79),array(40,40,78),array(39,39,77),array(38,38,76),array(37,37,75),
			array(36,36,74),array(35,35,73),array(34,34,72),array(33,33,71),array(31,32,70),array(30,30,69),array(29,29,68),
			array(28,28,67),array(27,27,66),array(26,26,65),array(25,25,64),array(24,24,63),array(23,23,62),array(22,22,61),
			array(21,21,60),array(20,20,59),array(19,19,58),array(17,18,57),array(16,16,56),array(15,15,55),array(14,14,54),
			array(13,13,53),array(12,12,52),array(11,11,51),array(10,10,50),array(9,9,49),array(8,8,48),array(7,7,47),array(6,6,46),
			array(5,5,45),array(3,4,44),array(2,2,43),array(1,1,42),array(0,0,41)
					)
			),
	10 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(45,45,93),array(44,44,91),array(43,43,90),array(42,42,88),array(41,41,86),array(40,40,84),array(39,39,83),
			array(38,38,81),array(37,37,79),array(36,36,77),array(35,35,76),array(34,34,74),array(33,33,72),array(32,32,70),
			array(31,31,69),array(30,30,67),array(29,29,65),array(28,28,63),array(27,27,62),array(26,26,60),array(25,25,58),
			array(24,24,57),array(23,23,55),array(22,22,53),array(21,21,51),array(20,20,50),array(19,19,48),array(18,18,46),
			array(17,17,44),array(16,16,43),array(15,15,41),array(14,14,39),array(13,13,37),array(12,12,36),array(11,11,34),
			array(10,10,32),array(9,9,30)
					),
		'adaptability'	=> array(						// Adaptability
			array(55,55,99),array(54,54,98),array(53,53,96),array(52,52,95),array(51,51,93),array(50,50,92),array(49,49,90),
			array(48,48,89),array(47,47,87),array(46,46,86),array(45,45,84),array(44,44,83),array(43,43,81),array(42,42,80),
			array(41,41,78),array(40,40,76),array(39,39,75),array(38,38,73),array(37,37,72),array(36,36,70),array(35,35,69),
			array(34,34,67),array(33,33,66),array(32,32,64),array(31,31,63),array(30,30,61),array(29,29,60),array(28,28,58),
			array(27,27,57),array(26,26,55),array(25,25,54),array(24,24,52),array(23,23,50),array(22,22,49),array(21,21,47),
			array(20,20,46),array(19,19,44),array(18,18,43),array(17,17,41),array(16,16,40),array(15,15,38),array(14,14,37),
			array(13,13,35),array(12,12,34),array(11,11,32)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(30,30,95),array(29,29,93),array(28,28,91),array(27,27,88),array(26,26,86),array(25,25,84),array(24,24,81),
			array(23,23,79),array(22,22,77),array(21,21,74),array(20,20,72),array(19,19,70),array(18,18,67),array(17,17,65),
			array(16,16,63),array(15,15,60),array(14,14,58),array(13,13,56),array(12,12,53),array(11,11,51),array(10,10,49),
			array(9,9,46),array(8,8,44),array(7,7,42),array(6,6,39)
					),
		'demandingness'	=> array(						// demandingness
			array(45,45,94),array(44,44,92),array(43,43,91),array(42,42,89),array(41,41,87),array(40,40,86),array(39,39,84),
			array(38,38,82),array(37,37,81),array(36,36,79),array(35,35,78),array(34,34,76),array(33,33,74),array(32,32,73),
			array(31,31,71),array(30,30,69),array(29,29,68),array(28,28,66),array(27,27,64),array(26,26,63),array(25,25,61),
			array(24,24,59),array(23,23,58),array(22,22,56),array(21,21,55),array(20,20,53),array(19,19,51),array(18,18,50),
			array(17,17,48),array(16,16,46),array(15,15,45),array(14,14,43),array(13,13,41),array(12,12,40),array(11,11,38),
			array(10,10,36),array(9,9,35)
					),
		'mood' => array(							// mood
			array(25,25,87),array(24,24,84),array(23,23,82),array(22,22,79),array(21,21,76),array(20,20,74),array(19,19,71),
			array(18,18,69),array(17,17,66),array(16,16,63),array(15,15,61),array(14,14,58),array(13,13,55),array(12,12,53),
			array(11,11,50),array(10,10,47),array(9,9,45),array(8,8,42),array(7,7,39),array(6,6,37),array(5,5,34)
					),
		'acceptability' => array(						// acceptabilty
			array(35,35,97),array(34,34,95),array(33,33,93),array(32,32,91),array(31,31,89),array(30,30,87),array(29,29,85),
			array(28,28,82),array(27,27,80),array(26,26,78),array(25,25,76),array(24,24,74),array(23,23,72),array(22,22,70),
			array(21,21,68),array(20,20,66),array(19,19,64),array(18,18,61),array(17,17,59),array(16,16,57),array(15,15,55),
			array(14,14,53),array(13,13,51),array(12,12,49),array(11,11,47),array(10,10,45),array(9,9,43),array(8,8,40),
			array(7,7,38)
					),
		'coompetence' => array(							// competence
			array(65,65,100),array(64,64,99),array(63,63,98),array(62,62,96),array(61,61,95),array(60,60,93),array(59,59,92),
			array(58,58,91),array(57,57,89),array(56,56,88),array(55,55,87),array(54,54,85),array(53,53,84),array(52,52,83),
			array(51,51,81),array(50,50,80),array(49,49,79),array(48,48,77),array(47,47,76),array(46,46,74),array(45,45,73),
			array(44,44,72),array(43,43,70),array(42,42,69),array(41,41,68),array(40,40,66),array(39,39,65),array(38,38,64),
			array(37,37,62),array(36,36,61),array(35,35,60),array(34,34,58),array(33,33,57),array(32,32,55),array(31,31,54),
			array(30,30,53),array(29,29,51),array(28,28,50),array(27,27,49),array(26,26,47),array(25,25,46),array(24,24,45),
			array(23,23,43),array(22,22,42),array(21,21,41),array(20,20,39),array(19,19,38),array(18,18,37),array(17,17,35),
			array(16,16,34),array(15,15,32),array(14,14,31),array(13,13,30)
					),
		'isolation' => array(							// isolation
			array(30,30,101),array(29,29,98),array(28,28,95),array(27,27,92),array(26,26,90),array(25,25,87),array(24,24,84),
			array(23,23,81),array(22,22,78),array(21,21,76),array(20,20,73),array(19,19,70),array(18,18,67),array(17,17,64),
			array(16,16,62),array(15,15,59),array(14,14,56),array(13,13,53),array(12,12,50),array(11,11,47),array(10,10,45),
			array(9,9,42),array(8,8,39),array(7,7,36),array(6,6,33)
					),
		'attachment' => array(							// Attachment
			array(35,35,98),array(34,34,96),array(33,33,94),array(32,32,91),array(31,31,89),array(30,30,87),array(29,29,85),
			array(28,28,83),array(27,27,81),array(26,26,79),array(25,25,76),array(24,24,74),array(23,23,72),array(22,22,70),
			array(21,21,68),array(20,20,66),array(19,19,64),array(18,18,62),array(17,17,59),array(16,16,57),array(15,15,55),
			array(14,14,53),array(13,13,51),array(12,12,49),array(11,11,47),array(10,10,44),array(9,9,42),array(8,8,40),array(7,7,38)
					),
		'health' => array(							// Health
			array(25,25,95),array(24,24,92),array(23,23,89),array(22,22,86),array(21,21,83),array(20,20,80),
			array(19,19,77),array(18,18,74),array(17,17,71),array(16,16,68),array(15,15,65),array(14,14,62),
			array(13,13,59),array(12,12,56),array(11,11,53),array(10,10,50),array(9,9,47),array(8,8,44),
			array(7,7,41),array(6,6,38),array(5,5,35)
					),
		'rolerestriction' => array(						// Role Restriction
			array(35,35,86),array(34,34,84),array(33,33,82),array(32,32,80),array(31,31,78),array(30,30,76),
			array(29,29,74),array(28,28,72),array(27,27,70),array(26,26,68),array(25,25,67),array(24,24,65),
			array(23,23,63),array(22,22,61),array(21,21,59),array(20,20,57),array(19,19,55),array(18,18,53),
			array(17,17,51),array(16,16,49),array(15,15,47),array(14,14,45),array(13,13,43),array(12,12,41),
			array(11,11,39),array(10,10,37),array(9,9,35),array(8,8,33),array(7,7,31)
					),
		'depression' => array(							// Depression
			array(45,45,96),array(44,44,94),array(43,43,92),array(42,42,90),array(41,41,89),array(40,40,87),
			array(39,39,85),array(38,38,84),array(37,37,82),array(36,36,80),array(35,35,79),array(34,34,77),
			array(33,33,75),array(32,32,74),array(31,31,72),array(30,30,70),array(29,29,69),array(28,28,67),
			array(27,27,65),array(26,26,64),array(25,25,62),array(24,24,60),array(23,23,59),array(22,22,57),
			array(21,21,55),array(20,20,54),array(19,19,52),array(18,18,50),array(17,17,49),array(16,16,47),
			array(15,15,45),array(14,14,44),array(13,13,42),array(12,12,40),array(11,11,38),array(10,10,37),
			array(9,9,35)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(35,35,77),array(34,34,76),array(33,33,74),array(32,32,73),array(31,31,71),array(30,30,70),
			array(29,29,68),array(28,28,67),array(27,27,65),array(26,26,64),array(25,25,62),array(24,24,61),
			array(23,23,59),array(22,22,58),array(21,21,56),array(20,20,55),array(19,19,53),array(18,18,52),
			array(17,17,50),array(16,16,49),array(15,15,47),array(14,14,46),array(13,13,44),array(12,12,43),
			array(11,11,41),array(10,10,40),array(9,9,38),array(8,8,37),array(7,7,35)
					),
		'childdomain' => array(							// Child Domain
			array(235,235,102),array(233,234,101),array(230,232,100),array(227,229,99),array(224,226,98),array(222,223,97),
			array(219,221,96),array(216,218,95),array(214,215,94),array(211,213,93),array(208,210,92),array(206,207,91),
			array(203,205,90),array(200,202,89),array(197,199,88),array(195,196,87),array(192,194,86),array(189,191,85),
			array(187,188,84),array(184,186,83),array(181,183,82),array(178,180,81),array(176,177,80),array(173,175,79),
			array(170,172,78),array(168,169,77),array(165,167,76),array(162,164,75),array(160,161,74),array(157,159,73),
			array(154,156,72),array(151,153,71),array(149,150,70),array(146,148,69),array(143,145,68),array(141,142,67),
			array(138,140,66),array(135,137,65),array(132,134,64),array(130,131,63),array(127,129,62),array(124,126,61),
			array(122,123,60),array(119,121,59),array(116,118,58),array(113,115,57),array(111,112,56),array(108,110,55),
			array(105,107,54),array(103,104,53),array(100,102,52),array(97,99,51),array(95,96,50),array(92,94,49),
			array(89,91,48),array(86,88,47),array(84,85,46),array(81,83,45),array(78,80,44),array(76,77,43),array(73,75,42),
			array(70,72,41),array(67,69,40),array(65,66,39),array(62,64,38),array(59,61,37),array(57,58,36),array(54,56,35),
			array(51,53,34),array(49,50,33),array(47,48,32)
					),
		'parentdomain' => array(						// Parent Domain
			array(268,270,102),array(265,267,101),array(262,264,100),array(259,261,99),array(256,258,98),
			array(253,255,97),array(250,252,96),array(247,249,95),array(244,246,94),array(241,243,93),
			array(238,240,92),array(235,237,91),array(232,234,90),array(229,231,89),array(226,228,88),
			array(223,225,87),array(220,222,86),array(217,219,85),array(214,216,84),array(211,213,83),
			array(208,210,82),array(205,207,81),array(202,204,80),array(199,201,79),array(196,198,78),
			array(193,195,77),array(190,192,76),array(187,189,75),array(184,186,74),array(181,183,73),
			array(178,180,72),array(175,177,71),array(172,174,70),array(169,171,69),array(166,168,68),
			array(163,165,67),array(160,162,66),array(157,159,65),array(154,156,64),array(151,153,63),
			array(148,150,62),array(145,147,61),array(142,144,60),array(139,141,59),array(136,138,58),
			array(133,135,57),array(130,132,56),array(127,129,55),array(124,126,54),array(121,123,53),
			array(118,120,52),array(115,117,51),array(112,114,50),array(109,111,49),array(106,108,48),
			array(103,105,47),array(100,102,46),array(97,99,45),array(94,96,44),array(91,93,43),
			array(88,90,42),array(85,87,41),array(82,84,40),array(79,81,39),array(76,78,38),array(73,75,37),
			array(70,72,36),array(67,69,35),array(64,66,34),array(61,63,33),array(58,60,32),array(55,57,31),
			array(54,54,30)
					),
		'totalstress' => array(							// Total Stress
			array(501,505,105),array(495,500,104),array(490,494,103),array(485,489,102),array(479,484,101),
			array(474,478,100),array(469,473,99),array(463,468,98),array(458,462,97),array(453,457,96),
			array(447,452,95),array(442,446,94),array(436,441,93),array(431,435,92),array(426,430,91),
			array(420,425,90),array(415,419,89),array(410,414,88),array(404,409,87),array(399,403,86),
			array(394,398,85),array(388,393,84),array(383,387,83),array(378,382,82),array(372,377,81),
			array(367,371,80),array(362,366,79),array(356,361,78),array(351,355,77),array(345,350,76),
			array(340,344,75),array(335,339,74),array(329,334,73),array(324,328,72),array(319,323,71),
			array(313,318,70),array(308,312,69),array(303,307,68),array(297,302,67),array(292,296,66),
			array(287,291,65),array(281,286,64),array(276,280,63),array(271,275,62),array(265,270,61),
			array(260,264,60),array(254,259,59),array(249,253,58),array(244,248,57),array(238,243,56),
			array(233,237,55),array(228,232,54),array(222,227,53),array(217,221,52),array(212,216,51),
			array(206,211,50),array(201,205,49),array(196,200,48),array(190,195,47),array(185,189,46),
			array(179,184,45),array(174,178,44),array(169,173,43),array(163,168,42),array(158,162,41),
			array(153,157,40),array(147,152,39),array(142,146,38),array(137,141,37),array(131,136,36),
			array(126,130,35),array(121,125,34),array(115,120,33),array(110,114,32),array(105,109,31),
			array(101,104,30)
					),
		'lifestress' => array(							// Life Stress
			array(79,79,">=118"),array(78,78,117),array(77,77,116),array(76,76,115),array(75,75,114),
			array(74,74,113),array(73,73,112),array(72,72,111),array(71,71,110),array(70,70,109),array(69,69,108),
			array(68,68,107),array(67,67,106),array(66,66,105),array(65,65,104),array(64,64,103),array(63,63,102),
			array(62,62,101),array(61,61,100),array(60,60,99),array(59,59,98),array(57,58,97),array(56,56,96),
			array(55,55,95),array(54,54,94),array(53,53,93),array(52,52,92),array(51,51,91),array(50,50,90),
			array(49,49,89),array(48,48,88),array(47,47,87),array(46,46,86),array(45,45,85),array(44,44,84),
			array(43,43,83),array(42,42,82),array(41,41,81),array(40,40,80),array(39,39,79),array(38,38,78),
			array(37,37,77),array(36,36,76),array(35,35,75),array(34,34,74),array(33,33,73),array(32,32,72),
			array(31,31,71),array(30,30,70),array(29,29,69),array(27,28,68),array(26,26,67),array(25,25,66),
			array(24,24,65),array(23,23,64),array(22,22,63),array(21,21,62),array(20,20,61),array(19,19,60),
			array(18,18,59),array(17,17,58),array(16,16,57),array(15,15,56),array(14,14,55),array(13,13,54),
			array(12,12,53),array(11,11,52),array(10,10,51),array(9,9,50),array(8,8,49),array(7,7,48),array(6,6,47),
			array(5,5,46),array(4,4,45),array(3,3,44),array(2,2,43),array(1,1,42),array(0,0,41)
					)
			),
	11 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(45,45,93),array(44,44,91),array(43,43,89),array(42,42,88),array(41,41,86),array(40,40,84),
			array(39,39,83),array(38,38,81),array(37,37,79),array(36,36,78),array(35,35,76),array(34,34,74),
			array(33,33,72),array(32,32,71),array(31,31,69),array(30,30,67),array(29,29,66),array(28,28,64),
			array(27,27,62),array(26,26,61),array(25,25,59),array(24,24,57),array(23,23,55),array(22,22,54),
			array(21,21,52),array(20,20,50),array(19,19,49),array(18,18,47),array(17,17,45),array(16,16,44),
			array(15,15,42),array(14,14,40),array(13,13,38),array(12,12,37),array(11,11,35),array(10,10,33),
			array(9,9,32)
					),
		'adaptability'	=> array(						// Adaptability
			array(55,55,103),array(54,54,101),array(53,53,100),array(52,52,98),array(51,51,97),array(50,50,95),
			array(49,49,93),array(48,48,92),array(47,47,90),array(46,46,89),array(45,45,87),array(44,44,86),
			array(43,43,84),array(42,42,82),array(41,41,81),array(40,40,79),array(39,39,78),array(38,38,76),
			array(37,37,75),array(36,36,73),array(35,35,72),array(34,34,70),array(33,33,68),array(32,32,67),
			array(31,31,65),array(30,30,64),array(29,29,62),array(28,28,61),array(27,27,59),array(26,26,57),
			array(25,25,56),array(24,24,54),array(23,23,53),array(22,22,51),array(21,21,50),array(20,20,48),
			array(19,19,47),array(18,18,45),array(17,17,43),array(16,16,42),array(15,15,40),array(14,14,39),
			array(13,13,37),array(12,12,36),array(11,11,34)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(30,30,92),array(29,29,90),array(28,28,88),array(27,27,85),array(26,26,83),array(25,25,81),
			array(24,24,79),array(23,23,76),array(22,22,74),array(21,21,72),array(20,20,69),array(19,19,67),
			array(18,18,65),array(17,17,63),array(16,16,60),array(15,15,58),array(14,14,56),array(13,13,53),
			array(12,12,51),array(11,11,49),array(10,10,47),array(9,9,44),array(8,8,42),array(7,7,40),array(6,6,37)
					),
		'demandingness'	=> array(						// demandingness
			array(45,45,96),array(44,44,94),array(43,43,93),array(42,42,91),array(41,41,89),array(40,40,87),
			array(39,39,86),array(38,38,84),array(37,37,82),array(36,36,80),array(35,35,79),array(34,34,77),
			array(33,33,75),array(32,32,73),array(31,31,72),array(30,30,70),array(29,29,68),array(28,28,67),
			array(27,27,65),array(26,26,63),array(25,25,61),array(24,24,60),array(23,23,58),array(22,22,56),
			array(21,21,54),array(20,20,53),array(19,19,51),array(18,18,49),array(17,17,47),array(16,16,46),
			array(15,15,44),array(14,14,42),array(13,13,40),array(12,12,39),array(11,11,37),array(10,10,35),
			array(9,9,33)
					),
		'mood' => array(							// mood
			array(25,25,92),array(24,24,89),array(23,23,86),array(22,22,83),array(21,21,80),array(20,20,77),
			array(19,19,74),array(18,18,71),array(17,17,69),array(16,16,66),array(15,15,63),array(14,14,60),
			array(13,13,57),array(12,12,54),array(11,11,51),array(10,10,48),array(9,9,46),array(8,8,43),
			array(7,7,40),array(6,6,37),array(5,5,34)
					),
		'acceptability' => array(						// acceptabilty
			array(35,35,107),array(34,34,105),array(33,33,102),array(32,32,100),array(31,31,97),array(30,30,95),
			array(29,29,92),array(28,28,90),array(27,27,87),array(26,26,85),array(25,25,82),array(24,24,80),
			array(23,23,77),array(22,22,75),array(21,21,73),array(20,20,70),array(19,19,68),array(18,18,65),
			array(17,17,63),array(16,16,60),array(15,15,58),array(14,14,55),array(13,13,53),array(12,12,50),
			array(11,11,48),array(10,10,45),array(9,9,43),array(8,8,40),array(7,7,38)
					),
		'coompetence' => array(							// competence
			array(65,65,104),array(64,64,102),array(63,63,101),array(62,62,100),array(61,61,98),array(60,60,97),
			array(59,59,95),array(58,58,94),array(57,57,92),array(56,56,91),array(55,55,89),array(54,54,88),
			array(53,53,87),array(52,52,85),array(51,51,84),array(50,50,82),array(49,49,81),array(48,48,79),
			array(47,47,78),array(46,46,77),array(45,45,75),array(44,44,74),array(43,43,72),array(42,42,71),
			array(41,41,69),array(40,40,68),array(39,39,67),array(38,38,65),array(37,37,64),array(36,36,62),
			array(35,35,61),array(34,34,59),array(33,33,58),array(32,32,56),array(31,31,55),array(30,30,54),
			array(29,29,52),array(28,28,51),array(27,27,49),array(26,26,48),array(25,25,46),array(24,24,45),
			array(23,23,44),array(22,22,42),array(21,21,41),array(20,20,39),array(19,19,38),array(18,18,36),
			array(17,17,35),array(16,16,34),array(15,15,32),array(14,14,31),array(13,13,29)
					),
		'isolation' => array(							// isolation
			array(30,30,89),array(29,29,87),array(28,28,85),array(27,27,83),array(26,26,80),array(25,25,78),
			array(24,24,76),array(23,23,74),array(22,22,71),array(21,21,69),array(20,20,67),array(19,19,65),
			array(18,18,63),array(17,17,60),array(16,16,58),array(15,15,56),array(14,14,54),array(13,13,52),
			array(12,12,49),array(11,11,47),array(10,10,45),array(9,9,43),array(8,8,40),array(7,7,38),array(6,6,36)
					),
		'attachment' => array(							// Attachment
			array(35,35,110),array(34,34,107),array(33,33,105),array(32,32,102),array(31,31,100),array(30,30,97),
			array(29,29,95),array(28,28,92),array(27,27,90),array(26,26,87),array(25,25,85),array(24,24,82),
			array(23,23,80),array(22,22,77),array(21,21,75),array(20,20,72),array(19,19,70),array(18,18,67),
			array(17,17,65),array(16,16,62),array(15,15,60),array(14,14,57),array(13,13,55),array(12,12,52),
			array(11,11,50),array(10,10,47),array(9,9,45),array(8,8,42),array(7,7,40)
					),
		'health' => array(							// Health
			array(25,25,91),array(24,24,88),array(23,23,85),array(22,22,83),array(21,21,80),array(20,20,77),
			array(19,19,74),array(18,18,72),array(17,17,69),array(16,16,66),array(15,15,63),array(14,14,61),
			array(13,13,58),array(12,12,55),array(11,11,52),array(10,10,50),array(9,9,47),array(8,8,44),
			array(7,7,42),array(6,6,39),array(5,5,36)
					),
		'rolerestriction' => array(						// Role Restriction
			array(35,35,83),array(34,34,81),array(33,33,79),array(32,32,77),array(31,31,75),array(30,30,73),
			array(29,29,72),array(28,28,70),array(27,27,68),array(26,26,66),array(25,25,64),array(24,24,62),
			array(23,23,61),array(22,22,59),array(21,21,57),array(20,20,55),array(19,19,53),array(18,18,51),
			array(17,17,50),array(16,16,48),array(15,15,46),array(14,14,44),array(13,13,42),array(12,12,40),
			array(11,11,39),array(10,10,37),array(9,9,35),array(8,8,33),array(7,7,31)
					),
		'depression' => array(							// Depression
			array(45,45,98),array(44,44,96),array(43,43,94),array(42,42,93),array(41,41,91),array(40,40,89),
			array(39,39,88),array(38,38,86),array(37,37,84),array(36,36,82),array(35,35,81),array(34,34,79),
			array(33,33,77),array(32,32,75),array(31,31,74),array(30,30,72),array(29,29,70),array(28,28,68),
			array(27,27,67),array(26,26,65),array(25,25,63),array(24,24,61),array(23,23,60),array(22,22,58),
			array(21,21,56),array(20,20,54),array(19,19,53),array(18,18,51),array(17,17,49),array(16,16,47),
			array(15,15,46),array(14,14,44),array(13,13,42),array(12,12,41),array(11,11,39),array(10,10,37),
			array(9,9,35)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(35,35,77),array(34,34,75),array(33,33,74),array(32,32,72),array(31,31,71),array(30,30,69),
			array(29,29,68),array(28,28,66),array(27,27,65),array(26,26,63),array(25,25,62),array(24,24,60),
			array(23,23,59),array(22,22,57),array(21,21,56),array(20,20,54),array(19,19,53),array(18,18,51),
			array(17,17,50),array(16,16,48),array(15,15,47),array(14,14,45),array(13,13,44),array(12,12,42),
			array(11,11,41),array(10,10,39),array(9,9,38),array(8,8,36),array(7,7,35)
					),
		'childdomain' => array(							// Child Domain
			array(235,235,109),array(233,234,108),array(230,232,107),array(228,229,106),array(225,227,105),
			array(223,224,104),array(221,222,103),array(218,220,102),array(216,217,101),array(213,215,100),
			array(211,212,99),array(208,210,98),array(206,207,97),array(204,205,96),array(201,203,95),
			array(199,200,94),array(196,198,93),array(194,195,92),array(192,193,91),array(189,191,90),
			array(187,188,89),array(184,186,88),array(182,183,87),array(180,181,86),array(177,179,85),
			array(175,176,84),array(172,174,83),array(170,171,82),array(168,169,81),array(165,167,80),
			array(163,164,79),array(160,162,78),array(158,159,77),array(155,157,76),array(153,154,75),
			array(151,152,74),array(148,150,73),array(146,147,72),array(143,145,71),array(141,142,70),
			array(139,140,69),array(136,138,68),array(134,135,67),array(131,133,66),array(129,130,65),
			array(127,128,64),array(124,126,63),array(122,123,62),array(119,121,61),array(117,118,60),
			array(115,116,59),array(112,114,58),array(110,111,57),array(107,109,56),array(105,106,55),
			array(102,104,54),array(100,101,53),array(98,99,52),array(95,97,51),array(93,94,50),array(90,92,49),
			array(88,89,48),array(86,87,47),array(83,85,46),array(81,82,45),array(78,80,44),array(76,77,43),
			array(74,75,42),array(71,73,41),array(69,70,40),array(66,68,39),array(64,65,38),array(62,63,37),
			array(59,61,36),array(57,58,35),array(54,56,34),array(52,53,33),array(50,51,32),array(47,49,31)
					),
		'parentdomain' => array(						// Parent Domain
			array(269,270,105),array(266,268,104),array(264,265,103),array(261,263,102),array(258,260,101),
			array(255,257,100),array(252,254,99),array(249,251,98),array(246,248,97),array(244,245,96),
			array(241,243,95),array(238,240,94),array(235,237,93),array(232,234,92),array(229,231,91),
			array(226,228,90),array(224,225,89),array(221,223,88),array(218,220,87),array(215,217,86),
			array(212,214,85),array(209,211,84),array(206,208,83),array(204,205,82),array(201,203,81),
			array(198,200,80),array(195,197,79),array(192,194,78),array(189,191,77),array(186,188,76),
			array(184,185,75),array(181,183,74),array(178,180,73),array(175,177,72),array(172,174,71),
			array(169,171,70),array(166,168,69),array(163,165,68),array(161,162,67),array(158,160,66),
			array(155,157,65),array(152,154,64),array(149,151,63),array(146,148,62),array(143,145,61),
			array(141,142,60),array(138,140,59),array(135,137,58),array(132,134,57),array(129,131,56),
			array(126,128,55),array(123,125,54),array(121,122,53),array(118,120,52),array(115,117,51),
			array(112,114,50),array(109,111,49),array(106,108,48),array(103,105,47),array(101,102,46),
			array(98,100,45),array(95,97,44),array(92,94,43),array(89,91,42),array(86,88,41),array(83,85,40),
			array(81,82,39),array(78,80,38),array(75,77,37),array(72,74,36),array(69,71,35),array(66,68,34),
			array(63,65,33),array(61,62,32),array(58,60,31),array(55,57,30),array(54,54,29)
					),
		'totalstress' => array(							// Total Stress
			array(501,505,112),array(496,500,111),array(491,495,110),array(486,490,109),array(482,485,108),
			array(477,481,107),array(472,476,106),array(467,471,105),array(463,466,104),array(458,462,103),
			array(453,457,102),array(448,452,101),array(443,447,100),array(439,442,99),array(434,438,98),
			array(429,433,97),array(424,428,96),array(420,423,95),array(415,419,94),array(410,414,93),
			array(405,409,92),array(400,404,91),array(396,399,90),array(391,395,89),array(386,390,88),
			array(381,385,87),array(377,380,86),array(372,376,85),array(367,371,84),array(362,366,83),
			array(357,361,82),array(353,356,81),array(348,352,80),array(343,347,79),array(338,342,78),
			array(334,337,77),array(329,333,76),array(324,328,75),array(319,323,74),array(314,318,73),
			array(310,313,72),array(305,309,71),array(300,304,70),array(295,299,69),array(291,294,68),
			array(286,290,67),array(281,285,66),array(276,280,65),array(271,275,64),array(267,270,63),
			array(262,266,62),array(257,261,61),array(252,256,60),array(248,251,59),array(243,247,58),
			array(238,242,57),array(233,237,56),array(228,232,55),array(224,227,54),array(219,223,53),
			array(214,218,52),array(209,213,51),array(205,208,50),array(200,204,49),array(195,199,48),
			array(190,194,47),array(186,189,46),array(181,185,45),array(176,180,44),array(171,175,43),
			array(166,170,42),array(162,165,41),array(157,161,40),array(152,156,39),array(147,151,38),
			array(143,146,37),array(138,142,36),array(133,137,35),array(128,132,34),array(123,127,33),
			array(119,122,32),array(114,118,31),array(109,113,30),array(104,108,29),array(101,103,28)
					),
		'lifestress' => array(							// Life Stress
			array(78,79,">=115"),array(77,77,114),array(76,76,113),array(75,75,112),array(74,74,111),
			array(73,73,110),array(72,72,109),array(71,71,108),array(70,70,107),array(69,69,106),array(68,68,105),
			array(67,67,104),array(66,66,103),array(65,65,102),array(64,64,101),array(63,63,100),array(62,62,99),
			array(60,61,98),array(59,59,97),array(58,58,96),array(57,57,95),array(56,56,94),array(55,55,93),
			array(54,54,92),array(53,53,91),array(52,52,90),array(51,51,89),array(50,50,88),array(49,49,87),
			array(48,48,86),array(47,47,85),array(46,46,84),array(45,45,83),array(44,44,82),array(42,43,81),
			array(41,41,80),array(40,40,79),array(39,39,78),array(38,38,77),array(37,37,76),array(36,36,75),
			array(35,35,74),array(34,34,73),array(33,33,72),array(32,32,71),array(31,31,70),array(30,30,69),
			array(29,29,68),array(28,28,67),array(27,27,66),array(26,26,65),array(24,25,64),array(23,23,63),
			array(22,22,62),array(21,21,61),array(20,20,60),array(19,19,59),array(18,18,58),array(17,17,57),
			array(16,16,56),array(15,15,55),array(14,14,54),array(13,13,53),array(12,12,52),array(11,11,51),
			array(10,10,50),array(9,9,49),array(8,8,48),array(6,7,47),array(5,5,46),array(4,4,45),array(3,3,44),
			array(2,2,43),array(1,1,42),array(0,0,41)
					)
			),
	12 => array(
		'distract_hyper' => array(						// Distractibilty / Hyperactivity
			array(45,45,83),array(44,44,82),array(43,43,81),array(42,42,79),array(41,41,78),array(40,40,76),
			array(39,39,75),array(38,38,73),array(37,37,72),array(36,36,70),array(35,35,69),array(34,34,67),
			array(33,33,66),array(32,32,64),array(31,31,63),array(30,30,61),array(29,29,60),array(28,28,59),
			array(27,27,57),array(26,26,56),array(25,25,54),array(24,24,53),array(23,23,51),array(22,22,50),
			array(21,21,48),array(20,20,47),array(19,19,45),array(18,18,44),array(17,17,42),array(16,16,41),
			array(15,15,39),array(14,14,38),array(13,13,37),array(12,12,35),array(11,11,34),array(10,10,32),
			array(9,9,31)
					),
		'adaptability'	=> array(						// Adaptability
			array(55,55,97),array(54,54,95),array(53,53,94),array(52,52,92),array(51,51,91),array(50,50,89),
			array(49,49,88),array(48,48,86),array(47,47,85),array(46,46,83),array(45,45,82),array(44,44,80),
			array(43,43,79),array(42,42,77),array(41,41,76),array(40,40,74),array(39,39,73),array(38,38,71),
			array(37,37,69),array(36,36,68),array(35,35,66),array(34,34,65),array(33,33,63),array(32,32,62),
			array(31,31,60),array(30,30,59),array(29,29,57),array(28,28,56),array(27,27,54),array(26,26,53),
			array(25,25,51),array(24,24,50),array(23,23,48),array(22,22,47),array(21,21,45),array(20,20,43),
			array(19,19,42),array(18,18,40),array(17,17,39),array(16,16,37),array(15,15,36),array(14,14,34),
			array(13,13,33),array(12,12,31),array(11,11,30)
					),
		'reinforcesparent' => array(						// Reinforces Parent
			array(30,30,87),array(29,29,85),array(28,28,83),array(27,27,80),array(26,26,78),array(25,25,76),
			array(24,24,74),array(23,23,72),array(22,22,70),array(21,21,68),array(20,20,65),array(19,19,63),
			array(18,18,61),array(17,17,59),array(16,16,57),array(15,15,55),array(14,14,53),array(13,13,50),
			array(12,12,48),array(11,11,46),array(10,10,44),array(9,9,42),array(8,8,40),array(7,7,38),array(6,6,35)
					),
		'demandingness'	=> array(						// demandingness
			array(45,45,88),array(44,44,86),array(43,43,85),array(42,42,83),array(41,41,82),array(40,40,80),
			array(39,39,79),array(38,38,77),array(37,37,76),array(36,36,74),array(35,35,72),array(34,34,71),
			array(33,33,69),array(32,32,68),array(31,31,66),array(30,30,65),array(29,29,63),array(28,28,62),
			array(27,27,60),array(26,26,58),array(25,25,57),array(24,24,55),array(23,23,54),array(22,22,52),
			array(21,21,51),array(20,20,49),array(19,19,48),array(18,18,46),array(17,17,44),array(16,16,43),
			array(15,15,41),array(14,14,40),array(13,13,38),array(12,12,37),array(11,11,35),array(10,10,34),
			array(9,9,32)
					),
		'mood' => array(							// mood
			array(25,25,86),array(24,24,83),array(23,23,81),array(22,22,78),array(21,21,75),array(20,20,73),
			array(19,19,70),array(18,18,67),array(17,17,64),array(16,16,62),array(15,15,59),array(14,14,56),
			array(13,13,53),array(12,12,51),array(11,11,48),array(10,10,45),array(9,9,43),array(8,8,40),
			array(7,7,37),array(6,6,34),array(5,5,32)
					),
		'acceptability' => array(						// acceptabilty
			array(35,35,91),array(34,34,89),array(33,33,87),array(32,32,85),array(31,31,83),array(30,30,81),
			array(29,29,79),array(28,28,77),array(27,27,76),array(26,26,74),array(25,25,72),array(24,24,70),
			array(23,23,68),array(22,22,66),array(21,21,64),array(20,20,62),array(19,19,60),array(18,18,58),
			array(17,17,56),array(16,16,54),array(15,15,52),array(14,14,50),array(13,13,48),array(12,12,46),
			array(11,11,44),array(10,10,43),array(9,9,41),array(8,8,39),array(7,7,37)
					),
		'coompetence' => array(							// competence
			array(65,65,94),array(64,64,93),array(63,63,92),array(62,62,91),array(61,61,89),array(60,60,88),
			array(59,59,87),array(58,58,85),array(57,57,84),array(56,56,83),array(55,55,81),array(54,54,80),
			array(53,53,79),array(52,52,78),array(51,51,76),array(50,50,75),array(49,49,74),array(48,48,72),
			array(47,47,71),array(46,46,70),array(45,45,68),array(44,44,67),array(43,43,66),array(42,42,65),
			array(41,41,63),array(40,40,62),array(39,39,61),array(38,38,59),array(37,37,58),array(36,36,57),
			array(35,35,55),array(34,34,54),array(33,33,53),array(32,32,52),array(31,31,50),array(30,30,49),
			array(29,29,48),array(28,28,46),array(27,27,45),array(26,26,44),array(25,25,42),array(24,24,41),
			array(23,23,40),array(22,22,39),array(21,21,37),array(20,20,36),array(19,19,35),array(18,18,33),
			array(17,17,32),array(16,16,31),array(15,15,29),array(14,14,28),array(13,13,27)
					),
		'isolation' => array(							// isolation
			array(30,30,93),array(29,29,90),array(28,28,87),array(27,27,85),array(26,26,82),array(25,25,79),
			array(24,24,77),array(23,23,74),array(22,22,71),array(21,21,69),array(20,20,66),array(19,19,63),
			array(18,18,61),array(17,17,58),array(16,16,55),array(15,15,52),array(14,14,50),array(13,13,47),
			array(12,12,44),array(11,11,42),array(10,10,39),array(9,9,36),array(8,8,34),array(7,7,31),array(6,6,28)
					),
		'attachment' => array(							// Attachment
			array(35,35,91),array(34,34,89),array(33,33,87),array(32,32,85),array(31,31,83),array(30,30,81),
			array(29,29,79),array(28,28,77),array(27,27,75),array(26,26,74),array(25,25,72),array(24,24,70),
			array(23,23,68),array(22,22,66),array(21,21,64),array(20,20,62),array(19,19,60),array(18,18,58),
			array(17,17,56),array(16,16,54),array(15,15,52),array(14,14,51),array(13,13,49),array(12,12,47),
			array(11,11,45),array(10,10,43),array(9,9,41),array(8,8,39),array(7,7,37)
					),
		'health' => array(							// Health
			array(25,25,87),array(24,24,84),array(23,23,82),array(22,22,79),array(21,21,76),array(20,20,73),
			array(19,19,71),array(18,18,68),array(17,17,65),array(16,16,63),array(15,15,60),array(14,14,57),
			array(13,13,55),array(12,12,52),array(11,11,49),array(10,10,47),array(9,9,44),array(8,8,41),
			array(7,7,39),array(6,6,36),array(5,5,33)
					),
		'rolerestriction' => array(						// Role Restriction
			array(35,35,83),array(34,34,81),array(33,33,79),array(32,32,77),array(31,31,75),array(30,30,73),
			array(29,29,71),array(28,28,69),array(27,27,67),array(26,26,65),array(25,25,63),array(24,24,61),
			array(23,23,59),array(22,22,57),array(21,21,55),array(20,20,53),array(19,19,51),array(18,18,49),
			array(17,17,48),array(16,16,46),array(15,15,44),array(14,14,42),array(13,13,40),array(12,12,38),
			array(11,11,36),array(10,10,34),array(9,9,32),array(8,8,30),array(7,7,28)
					),
		'depression' => array(							// Depression
			array(45,45,93),array(44,44,91),array(43,43,90),array(42,42,88),array(41,41,86),array(40,40,84),
			array(39,39,83),array(38,38,81),array(37,37,79),array(36,36,78),array(35,35,76),array(34,34,74),
			array(33,33,72),array(32,32,71),array(31,31,69),array(30,30,67),array(29,29,66),array(28,28,64),
			array(27,27,62),array(26,26,61),array(25,25,59),array(24,24,57),array(23,23,55),array(22,22,54),
			array(21,21,52),array(20,20,50),array(19,19,49),array(18,18,47),array(17,17,45),array(16,16,43),
			array(15,15,42),array(14,14,40),array(13,13,38),array(12,12,37),array(11,11,35),array(10,10,33),
			array(9,9,32)
					),
		'spouse' => array(							// Spouse Partner Relationship
			array(35,35,76),array(34,34,74),array(33,33,72),array(32,32,71),array(31,31,69),array(30,30,67),
			array(29,29,66),array(28,28,64),array(27,27,62),array(26,26,61),array(25,25,59),array(24,24,57),
			array(23,23,56),array(22,22,54),array(21,21,52),array(20,20,51),array(19,19,49),array(18,18,47),
			array(17,17,46),array(16,16,44),array(15,15,42),array(14,14,41),array(13,13,39),array(12,12,37),
			array(11,11,36),array(10,10,34),array(9,9,32),array(8,8,31),array(7,7,29)
					),
		'childdomain' => array(							// Child Domain
			array(234,235,96),array(231,233,95),array(229,230,94),array(226,228,93),array(223,225,92),
			array(220,222,91),array(217,219,90),array(214,216,89),array(212,213,88),array(209,211,87),
			array(206,208,86),array(203,205,85),array(200,202,84),array(198,199,83),array(195,197,82),
			array(192,194,81),array(189,191,80),array(186,188,79),array(183,185,78),array(181,182,77),
			array(178,180,76),array(175,177,75),array(172,174,74),array(169,171,73),array(167,168,72),
			array(164,166,71),array(161,163,70),array(158,160,69),array(155,157,68),array(152,154,67),
			array(150,151,66),array(147,149,65),array(144,146,64),array(141,143,63),array(138,140,62),
			array(136,137,61),array(133,135,60),array(130,132,59),array(127,129,58),array(124,126,57),
			array(121,123,56),array(119,120,55),array(116,118,54),array(113,115,53),array(110,112,52),
			array(107,109,51),array(105,106,50),array(102,104,49),array(99,101,48),array(96,98,47),
			array(93,95,46),array(90,92,45),array(88,89,44),array(85,87,43),array(82,84,42),array(79,81,41),
			array(76,78,40),array(74,75,39),array(71,73,38),array(68,70,37),array(65,67,36),array(62,64,35),
			array(59,61,34),array(57,58,33),array(54,56,32),array(51,53,31),array(48,50,30),array(47,47,29)
					),
		'parentdomain' => array(						// Parent Domain
			array(270,270,99),array(267,269,98),array(264,266,97),array(261,263,96),array(258,260,95),
			array(255,257,94),array(252,254,93),array(249,251,92),array(246,248,91),array(244,245,90),
			array(241,243,89),array(238,240,88),array(235,237,87),array(232,234,86),array(229,231,85),
			array(226,228,84),array(223,225,83),array(220,222,82),array(217,219,81),array(214,216,80),
			array(211,213,79),array(208,210,78),array(206,207,77),array(203,205,76),array(200,202,75),
			array(197,199,74),array(194,196,73),array(191,193,72),array(188,190,71),array(185,187,70),
			array(182,184,69),array(179,181,68),array(176,178,67),array(173,175,66),array(170,172,65),
			array(168,169,64),array(165,167,63),array(162,164,62),array(159,161,61),array(156,158,60),
			array(153,155,59),array(150,152,58),array(147,149,57),array(144,146,56),array(141,143,55),
			array(138,140,54),array(135,137,53),array(132,134,52),array(130,131,51),array(127,129,50),
			array(124,126,49),array(121,123,48),array(118,120,47),array(115,117,46),array(112,114,45),
			array(109,111,44),array(106,108,43),array(103,105,42),array(100,102,41),array(97,99,40),
			array(95,96,39),array(92,94,38),array(89,91,37),array(86,88,36),array(83,85,35),array(80,82,34),
			array(77,79,33),array(74,76,32),array(71,73,31),array(68,70,30),array(65,67,29),array(62,64,28),
			array(59,61,27),array(57,58,26),array(54,56,25)
					),
		'totalstress' => array(							// Total Stress
			array(503,505,100),array(497,502,99),array(492,496,98),array(486,491,97),array(481,485,96),
			array(476,480,95),array(470,475,94),array(465,469,93),array(459,464,92),array(454,458,91),
			array(448,453,90),array(443,447,89),array(437,442,88),array(432,436,87),array(427,431,86),
			array(421,426,85),array(416,420,84),array(410,415,83),array(405,409,82),array(399,404,81),
			array(394,398,80),array(389,393,79),array(383,388,78),array(378,382,77),array(372,377,76),
			array(367,371,75),array(361,366,74),array(356,360,73),array(350,355,72),array(345,349,71),
			array(340,344,70),array(334,339,69),array(329,333,68),array(323,328,67),array(318,322,66),
			array(312,317,65),array(307,311,64),array(302,306,63),array(296,301,62),array(291,295,61),
			array(285,290,60),array(280,284,59),array(274,279,58),array(269,273,57),array(263,268,56),
			array(258,262,55),array(253,257,54),array(247,252,53),array(242,246,52),array(236,241,51),
			array(231,235,50),array(225,230,49),array(220,224,48),array(215,219,47),array(209,214,46),
			array(204,208,45),array(198,203,44),array(193,197,43),array(187,192,42),array(182,186,41),
			array(176,181,40),array(171,175,39),array(166,170,38),array(160,165,37),array(155,159,36),
			array(149,154,35),array(144,148,34),array(138,143,33),array(133,137,32),array(128,132,31),
			array(122,127,30),array(117,121,29),array(111,116,28),array(106,110,27),array(101,105,26)
					),
		'lifestress' => array(							// Life Stress
			array(79,79,">=106"),array(78,78,105),array(77,77,104),array(75,76,103),array(74,74,102),
			array(73,73,101),array(72,72,100),array(71,71,99),array(70,70,98),array(68,69,97),array(67,67,96),
			array(66,66,95),array(65,65,94),array(64,64,93),array(62,63,92),array(61,61,91),array(60,60,90),
			array(59,59,89),array(58,58,88),array(57,57,87),array(55,56,86),array(54,54,85),array(53,53,84),
			array(52,52,83),array(51,51,82),array(50,50,81),array(48,49,80),array(47,47,79),array(46,46,78),
			array(45,45,77),array(44,44,76),array(43,43,75),array(41,42,74),array(40,40,73),array(39,39,72),
			array(38,38,71),array(37,37,70),array(36,36,69),array(34,35,68),array(33,33,67),array(32,32,66),
			array(31,31,65),array(30,30,64),array(29,29,63),array(27,28,62),array(26,26,61),array(25,25,60),
			array(24,24,59),array(23,23,58),array(21,22,57),array(20,20,56),array(19,19,55),array(18,18,54),
			array(17,17,53),array(16,16,52),array(14,15,51),array(13,13,50),array(12,12,49),array(11,11,48),
			array(10,10,47),array(9,9,46),array(7,8,45),array(6,6,44),array(5,5,43),array(4,4,42),array(3,3,41),
			array(2,2,40),array(0,1,39)
					)
			)
		);


function lookup_tables($raw_values, $lookup_matrix, $categories, $appendage) {

	$results = array();

	if (is_null($lookup_matrix)) {
		foreach ($categories as $i => $category) {
			$results[$category . $appendage] = null;
		}

	} else {
 		foreach ($categories as $i => $category) {
			$found = false;
			$category_matrix = $lookup_matrix[$category];
			$raw = $raw_values[$category.'_raw'];
			#$this->module->emDebug("raw value is ". $raw . ", and category is " . $category);
			foreach ($category_matrix as $j) {
				# The arrays are triplets with min raw_value first, then max raw_value next,
				# then percentile or tvalue. This was easier to put
				# into array form because there were SOOOO many of them.
				if ($raw >= $j[0] and $raw <= $j[1]) {
					$results[$category . $appendage] = $j[2];
					$found = true;
					#$this->module->emDebug(" category = " . $category . ", tvalue found = " . $results[$category]);
					break;
				}

			}
			if ($found == false) {
				$results[$category . $appendage] = null;
				$this->module->emDebug("WARNING: Could not find a lookup value for category " . $category . " with raw value of " . $raw);
			}
		}
	}
	return $results;

}

// Initialize defensive Significant to blank

// check that the age is within limits of 4-12 yr old
// Amy wants raw scores to calculate if age is out of range
if ($age < 4 || $age > 12) {
    $msg = "Age ($age) must be between 4 and 12 for t-scores";
    $algorithm_log[] = $msg;
    $this->module->emError($msg);

    foreach ($categories as $c) {
        if ($c === "defensiveresponse") {
            // skip defensive response - no lookup tables
        } else {
            $results_perc[$c . "_perc"] = "";
            $results_tval[$c . "_tval"] = "";
        }
    }

} else {

    # We are not looking values for defensive response to take it out of the category array
    unset($categories[array_search('defensiveresponse', $categories)]);

    $results_perc = lookup_tables($result_values, $perc_lookup_matrix[$age], $categories, '_perc');
    $results_tval = lookup_tables($result_values, $tval_lookup_matrix[$age], $categories, '_tval');
}

// Defensive Significance is valid whether or not the participant is within the acceptable age range
$defensive_significant = null;
$def_response = $result_values['defensiveresponse_raw'];

# if the defensive response value is less than or equal to 24, the defensive significance is true.  Otherwise it is false.
if (!empty($def_response)) {
    $defensive_significant = ($def_response <= 24 ? 1 : 0);
}
$this->module->emDebug("defensive significance: " . $defensive_significant);

array_push($results_tval, $defensive_significant);
$this->module->emDebug("Results perc: " . json_encode($results_perc));
$this->module->emDebug("Results tval: " . json_encode($results_tval));

$results_total = array_merge($result_values, $results_perc, $results_tval);
$this->module->emDebug("All Results: " . implode(",", $results_total));


### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
#$this->module->emDebug("Before combining: " . json_encode($default_result_fields));
#$this->module->emDebug("Before combining results_total: " . json_encode($results_total));
$algorithm_results = array_combine($default_result_fields, $results_total);
//$this->module->emDebug("AR: " . $algorithm_results);

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
