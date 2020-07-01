<?php
/**

	BASC2 - Self-Report of Personality - Interview
	
	A REDCap AutoScoring Algorithm File

	Developed by Kim Wijaya and Alex Basile for ELSPAP June 2016
	Uses General Combined Sex Scales Ages 6-7
	See BASC2 Manual for Scale Details 	
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results
        - The answers are categorized as:
              0 = Yes
              1 = No

        - There are 65 questions that the participant fills out.

**/


# REQUIRED: Summarize this algorithm
$algorithm_summary = "BASC2, Self Report of Personality - Interview";
$this->module->emDebug("Scoring Title: " . $algorithm_summary);

	$default_result_fields = array(
		'srpi_ats_raw', 'srpi_att_raw', 'srpi_aty_raw', 'srpi_sst_raw',
		'srpi_anx_raw', 'srpi_dep_raw', 'srpi_ipr_raw',
		'srpi_ats_null', 'srpi_att_null', 'srpi_aty_null', 'srpi_sst_null',
		'srpi_anx_null', 'srpi_dep_null', 'srpi_ipr_null',
		'srpi_ats_tval', 'srpi_att_tval', 'srpi_aty_tval', 'srpi_sst_tval',
		'srpi_anx_tval', 'srpi_dep_tval', 'srpi_ipr_tval',
		'srpi_ats_tvalp', 'srpi_att_tvalp', 'srpi_aty_tvalp', 'srpi_sst_tvalp',
		'srpi_anx_tvalp', 'srpi_dep_tvalp', 'srpi_ipr_tvalp',
		'srpi_emosymp_imean', 'srpi_emosymp_raw'#, 'srpi_anx_sig',
		#'srpi_ats_sig','srpi_att_sig','srpi_aty_sig','srpi_dep_sig',
		#'srpi_emosymp_sig', 'srpi_sst_sig','srpi_ipr_sig' 
	);

# REQUIRED: Define an array of fields that must be present for this algorithm to run

$required_fields = array();
foreach (range(1,65) as $i) {
	array_push($required_fields, "basc_srp_i_q$i");
}

//$this->module->emDebug("Required input field names ".implode(',',$required_fields));

# Override default input array with manual field names specified by user (optional)
if (!empty($manual_source_fields)) {
    if (count($manual_source_fields) == count($required_fields)) {
            foreach($manual_source_fields as $k => $field) {
                        if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
                                $required_fields[$k] = $field;
                                $this->module->emDebug("Changing input field ".$k." to ".$field);
                       }
                }
                $log[] = "Overriding default input field names with ". implode(',',$manual_source_fields);
        } else {
                $msg = count($manual_source_fields) . " manual source fields specified, but the algorithm needs " . count($required_fields) . " fields.";
                $this->module->emError($msg);
                $algorithm_log[] = $msg;
                return false;
        }
}

### VALIDATION ##
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;

# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

# Override default result array with manual field names specified by user (optional)
if (!empty($manual_result_fields)) {
	if (count($manual_result_fields) == count($default_result_fields)) {
		foreach($manual_result_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$default_result_fields[$k] = $field;
				$this->module->emDebug("Changing result field ".$k." to ". $field);
			}
		}
		$log[] = "Overriding default result field names with ". implode(',',$manual_result_fields);
	} else {
		$msg = count($manual_result_fields) . " manual result fields specified, but the algorithm needs " . count($default_result_fields) . " fields.";
		$this->module->emError($msg);
		$algorithm_log[] = $msg;
#		return false;
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
#	return false;	//Since this is being called via include, the main script will continue to process other algorithms
}

# These are some fields that need their values flipped before scoring. Instead of going from 0 => 3, they go from 3 => 0
$new_source = array();
$reg_values = array(1,30,32,39,48,52,54,61,63);
foreach ($required_fields as $i => $req_name) {
	$val = $src[$req_name];
	$index = $i+1;

	if (!in_array(($index), $reg_values)) {
		$toadd = $val;
		if (isset($val) and (strlen($val) > 0))
		{
			if ($toadd == 2)
			{
				$toadd = 0;
			}
			else{
				$toadd = 1;
			}
		}
		else
		{
			$toadd = null;
		}
		$new_source[$req_name] = $toadd;
#		$this->module->emDebug("Flipped values: i=$i, field_name = $req_name, val = $val, new source = $new_source[$req_name]");
	} 
	else
	{
		$new_source[$req_name] = (isset($val)) ? $val-1 : null;
	}
}


$source_names = array("ats","att", "aty","sst","anx","dep", "ipr"); 
$source_indexes = array (
	'ats' => array(1,23,45,8,30,22),
	'att' => array(11,33,55,15,37,59),
	'aty' => array(6,28,50,14,36,58,21,43,44),
	'sst' => array(4,26,7,29,13,35,57,17,20,42),
	'anx' => array(2,24,46,9,31,53,12,34,56,18,40,62,65),
	'dep' => array(3,25,47,5,27,49,51,16,38,60),
	'ipr' => array(48,52,10,32,54,39,61,19,41,63)
);

$mult_factor = array(
	'ats' => 0,
	'att' => 0,
	'aty' => 0,
	'sst' => 0,
	'anx' => 0,
	'dep' => 0,
	'ipr' => 1
	);


$raw_scores = array();
$null_counter = array();
foreach ($source_names as $i => $field_name) {
	$raw_scores[$field_name] = 0;
	$null_counter[$field_name] = 0;
}



# Add up each array and count how many blank answers there are.
foreach ($required_fields as $i => $field_name) {
	$val = $new_source[$field_name];
	$index = $i+1;

	foreach ($source_names as $j => $result_name) {
		$target_result = $source_indexes[$result_name];
		if (in_array($index, $target_result)) {
			if (isset($val) and strlen($val) > 0) {
				$raw_scores[$result_name] += $val;
			} else {
				$null_counter[$result_name]++;
//				$this->module->emDebug("null value $val for field_name = $field_name, index = $index, null counter = $null_counter[$result_name]");
			}
       		}
	}
}


$tvalue = array(
	'ats' => array(
			0 => 43, 1 => 50, 2 => 56, 3 => 62, 4 => 68,
			5 => 74, 6 => 81
		),
	'att' => array(
			0 => 43, 1 => 49, 2 => 56, 3 => 62, 4 => 69,
			5 => 76, 6 => 82
		),
	'aty' => array(
			0 => 35, 1 => 39, 2 => 43, 3 => 47, 4 => 50,
			5 => 54, 6 => 58, 7 => 62, 8 => 66, 9 => 70
		),
	'sst' => array(
			0 => 38, 1 => 42, 2 => 46, 3 => 49, 4 => 53,
			5 => 57, 6 => 60, 7 => 64, 8 => 67, 9 => 71,
			10 => 75, 11 => 78
		),
	'anx' => array(
			0 => 36, 1 => 39, 2 => 42, 3 => 45, 4 => 48,
			5 => 50, 6 => 53, 7 => 56, 8 => 59, 9 => 62,
			10 => 65, 11 => 67, 12 => 70, 13 => 73
		),
	'dep' => array(
			0 => 41, 1 => 45, 2 => 49, 3 => 53, 4 => 56,
			5 => 60, 6 => 64, 7 => 68, 8 => 72, 9 => 76,
			10 => 80
		),
	'ipr' => array(
			0 => 13, 1 => 18, 2 => 22, 3 => 27, 4 => 32,
			5 => 36, 6 => 41, 7 => 46, 8 => 50, 9 => 55, 
			10 => 59
			)
);

$tvalue_perc = array(
	'ats' => array(
			0 => 29, 1 => 68, 2 => 80, 3 => 86, 4 => 91,
			5 => 95, 6 => 98
		),
	'att' => array(
			0 => 27, 1 => 63, 2 => 77, 3 => 86, 4 => 92,
			5 => 97, 6 => 99
		),
	'aty' => array(
			0 => 6, 1 => 16, 2 => 28, 3 => 40, 4 => 52,
			5 => 64, 6 => 75, 7 => 85, 8 => 94, 9 => 99
		),
	'sst' => array(
			0 => 9, 1 => 27, 2 => 42, 3 => 55, 4 => 65,
			5 => 74, 6 => 82, 7 => 88, 8 => 93, 9 => 97,
			10 => 99, 11 => 99
		),
	'anx' => array(
			0 => 5, 1 => 15, 2 => 26, 3 => 36, 4 => 46,
			5 => 55, 6 => 64, 7 => 71, 8 => 78, 9 => 85,
			10 => 90, 11 => 94, 12 => 97, 13 => 99
		),
	'dep' => array(
			0 => 17, 1 => 40, 2 => 56, 3 => 68, 4 => 76,
			5 => 83, 6 => 88, 7 => 93, 8 => 96, 9 => 98, 
			10 => 99
		),
	'ipr' => array(
			0 => 1, 1 => 1, 2 => 2, 3 => 4, 4 => 6,
			5 => 10, 6 => 16, 7 => 25, 8 => 39, 9 => 59,
			10 => 89
			)
);

function lookup_tvalue($tvalue_matrix, $raw_score) {

	   if (isset($tvalue_matrix[$raw_score])) {
		$result = $tvalue_matrix[$raw_score];
	   } else {
		$result = NULL;
	   }
	   return $result;
	}



	# If there are more than 2 empty fields in the list, do not save the scoring.  
	# Also, the score must not exceed the maximum value specified.
	$raw_score_totals = array();
	$tvalue_scores = array();
	$tvalue_percent = array();
	$q_answered = 65;

	foreach ($source_names as $i => $result_name) {
		$raw_score_val = $raw_scores[$result_name];
		$null_vals = $null_counter[$result_name];

		$q_answered -= $null_vals;
	# Add in the null contributions to calculate the total raw score
		if ($null_vals > 2) {
			$raw_score_val = NULL;
		} else {
			$raw_score_val += $mult_factor[$result_name] * $null_vals;
		}

		$raw_score_totals[$result_name . '_null'] = $raw_score_val;

		if (isset($raw_score_val)) {
			$tvalue_scores[$result_name . '_tval'] = lookup_tvalue($tvalue[$result_name], $raw_score_val);
			$tvalue_percent[$result_name . '_tvalp'] = lookup_tvalue($tvalue_perc[$result_name], $raw_score_val);
		} else {
			$tvalue_scores[$result_name . '_tval'] = NULL;
			$tvalue_percent[$result_name . '_tvalp'] = NULL;
		}
	}

	# Now calculate the composite scores
	# These are the lookup tables for the composite score t values
	$composite_names = array('emosymp');
	$composite_calc = array(
			'emosymp' => array('ats', 'att', 'aty', 'sst', 'anx', 'dep', 'ipr')
				);

$composite_comb_tvalues = array(
			'emosymp' => array(
				36 => array('min' => 277, 'max' => 280), 37 => array('min' => 281, 'max' => 285), 38 => array('min' => 286, 'max' => 290),
				39 => array('min' => 291, 'max' => 295), 40 => array('min' => 295, 'max' => 300), 41 => array('min' => 301, 'max' => 306),
				42 => array('min' => 307, 'max' => 311), 43 => array('min' => 312, 'max' => 316), 44 => array('min' => 317, 'max' => 321),
				45 => array('min' => 322, 'max' => 326), 46 => array('min' => 327, 'max' => 332), 47 => array('min' => 333, 'max' => 337),
				48 => array('min' => 338, 'max' => 342), 49 => array('min' => 343, 'max' => 347), 50 => array('min' => 348, 'max' => 352),
				51 => array('min' => 353, 'max' => 358), 52 => array('min' => 359, 'max' => 363), 53 => array('min' => 364, 'max' => 368),
				54 => array('min' => 369, 'max' => 373), 55 => array('min' => 374, 'max' => 378), 56 => array('min' => 379, 'max' => 384),
				57 => array('min' => 385, 'max' => 389), 58 => array('min' => 390, 'max' => 394), 59 => array('min' => 395, 'max' => 399),
				60 => array('min' => 400, 'max' => 404), 61 => array('min' => 404, 'max' => 410), 62 => array('min' => 411, 'max' => 415),
				63 => array('min' => 416, 'max' => 420), 64 => array('min' => 421, 'max' => 425), 65 => array('min' => 426, 'max' => 430),
				66 => array('min' => 431, 'max' => 436), 67 => array('min' => 437, 'max' => 441), 68 => array('min' => 442, 'max' => 446),
				69 => array('min' => 447, 'max' => 451), 70 => array('min' => 452, 'max' => 456), 71 => array('min' => 457, 'max' => 462),
				72 => array('min' => 463, 'max' => 467), 73 => array('min' => 468, 'max' => 472), 74 => array('min' => 473, 'max' => 477),
				75 => array('min' => 478, 'max' => 482), 76 => array('min' => 483, 'max' => 488), 77 => array('min' => 489, 'max' => 493),
				78 => array('min' => 494, 'max' => 498), 79 => array('min' => 499, 'max' => 503), 80 => array('min' => 504, 'max' => 508),
				81 => array('min' => 509, 'max' => 514), 82 => array('min' => 515, 'max' => 519), 83 => array('min' => 520, 'max' => 524),
				84 => array('min' => 525, 'max' => 529), 85 => array('min' => 530, 'max' => 534), 86 => array('min' => 535, 'max' => 540),
				87 => array('min' => 541, 'max' => 545), 88 => array('min' => 546, 'max' => 550), 89 => array('min' => 551, 'max' => 551)
				)
		);


	function lookup_composite_tvalue($tvalue_matrix, $raw_score) {

		$composite_tval = NULL;
		$min_name = 'min';
		$max_name = 'max';

		foreach ($tvalue_matrix as $i => $range) {
			if ($raw_score >= $range[$min_name] and $raw_score <= $range[$max_name]) {
				$composite_tval = $i;
				break;
			}
		}

		return $composite_tval;
	}

	$composite_raw = array();
	$composite_tval = array();
	$composite_mean = array();
	foreach ($composite_names as $i => $field_names) {
		$result = 0;

		foreach ($composite_calc[$field_names] as $j => $subscale) {
			$name = $subscale . '_tval';
            $this->module->emLog("Adding " . $name . " with TVAL: " . $tvalue_scores[$name]);
            $this->module->emLog("Result Before: " . $result);
			if ($subscale == 'ipr')
			{
				$result += 100-$tvalue_scores[$name];
			}
			else
			{
				$result += $tvalue_scores[$name];
			}
            $this->module->emLog("Result After: " . $result);
		}

		$num_items = count($composite_calc['emosymp']);


		$composite_raw['srpi_emosymp_raw'] = $result;
		$composite_mean['srpi_emosymp_imean'] = 100-$result/$num_items;

	
		#$result_tval = lookup_composite_tvalue($composite_comb_tvalues[$field_names], $result); 
		#$composite_raw[$field_names . '_raw'] = $result;
		#if ($field_names == 'emosymp') {
			#$num_items = count($composite_calc[$field_names]);
			#$composite_mean[$field_names . '_comp_mean'] = 100-$result/$num_items;
		#}
	}

$forty = array('ipr');
$sig_vals = array();
foreach ($source_names as $i => $result_name) {
    $tval = $tvalue_scores[$result_name . '_tval'];
    if ($tval == NULL)
    {
      $sig_vals[$result_name . '_sig'] = "Not enough information.";
    }
    else
    {
      if (in_array($result_name, $forty)) {
        if ($tval >= 41)
          {
            $sig_vals[$result_name . '_sig'] = "Average";
          }
          else if ($tval >= 31 and $tval <=40)
          {
            $sig_vals[$result_name . '_sig'] = "At Risk";
          }
          else if ($tval <= 30)
          {
            $sig_vals[$result_name . '_sig'] = "Clincially Significant";
          }
      }

      else
      {
          if ($tval < 60)
          {
            $sig_vals[$result_name . '_sig'] = "Average";
          }
          else if ($tval >= 60 and $tval <= 69)
          {
            $sig_vals[$result_name . '_sig'] = "At Risk";
          }
          else if ($tval > 69)
          {
            $sig_vals[$result_name . '_sig'] = "Clincially Significant";
          }
      }
    }
}

foreach ($composite_names as $i => $field_names) {
			if ($field_names == 'emosymp') continue;
        $tval = $composite_tval[$field_names . '_comp_tval'];

        if ($tval == NULL)
        {
          $sig_vals[$field_names . '_sig'] = "Not enough information.";
        }
        else
        {
          if ($tval < 60)
          {
            $sig_vals[$field_names . '_sig'] = "Average";
          }
          else if ($tval >= 60 and $tval <= 69)
          {
            $sig_vals[$field_names . '_sig'] = "At Risk";
          }
          else if ($tval > 69)
          {
            $sig_vals[$field_names . '_sig'] = "Clincially Significant";
          }
        }
}


### DEFINE RESULTS ###
# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$totals =  array_merge($raw_score_totals, $null_counter, $tvalue_scores, $tvalue_percent, $composite_raw, $composite_tval, $composite_mean#, $sig_vals
	);
#$totals = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38);
$this->module->emDebug("Raw total: " . count($raw_score_totals) . "\nNull Counter: " . count($null_counter) . "\nTvalue: " . count($tvalue_scores) . "\n Tvalp: " . count($tvalue_percent));
$this->module->emDebug("Default: " . count($default_result_fields) . "Mine: " . count($totals));
$algorithm_results = array_combine($default_result_fields, $totals);



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