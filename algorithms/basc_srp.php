<?php
/**

	BASC2 - Self-Report of Personality - Adolescent
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results
        - The answers are categorized as:
              0 = Never
              1 = Sometimes
              2 = Often
              3 = Almost Always
        - There are 150 questions that the participant fills out.

**/




# REQUIRED: Define an array of default result field_names to record the summary data
# The BASC2 - PRS-A scoring is as follows:
#    Accumulated values and Omitted values
# If any of the subscales is missing more than 2 values, do not scale that subscale.
# This clinical scales/subscales are: 
#    Hyperactivity, Aggression, Conduct Problems, Anxiety, Depression, Somalization,
#    Atypicality, Withdrawal, Attention Problems, Adaptability, Social Skills,
#    Leadership, Activities of Daily Living, Functional Communication
# Once the clincal scales are calculated, calculate the composite score for:
#    Externalizing Problems Composite, Internalizing Problems Composite, 
#    Behaviorial Symptoms Index and Adaptive Skills Composite
# These composite scores also have lookup tables for T scores.

$required_fields = array();

if ($src['basc_srp_formused'] == "02") {


# REQUIRED: Summarize this algorithm
$algorithm_summary = "BASC2, Self Report of Personality - Adolescent";
$this->module->emDebug("Scoring Title: " . $algorithm_summary);

	$default_result_fields = array(
		'srpa_anx_raw', 'srpa_apr_raw','srpa_ata_raw', 'srpa_ats_raw',
		'srpa_att_raw','srpa_dep_raw', 'srpa_rwp_raw', 'srpa_sfe_raw', 
		'srpa_sfr_raw', 'srpa_soi_raw', 'srpa_som_raw', 'srpa_sos_raw',
		'srpa_loc_raw', 'srpa_hyp_raw', 'srpa_ipr_raw', 'srpa_ssk_raw',
		'srpa_anx_null', 'srpa_apr_null', 'srpa_ata_null', 'srpa_ats_null',
		'srpa_att_null', 'srpa_dep_null', 'srpa_hyp_null', 'srpa_ipr_null',
		'srpa_loc_null', 'srpa_rwp_null', 'srpa_sfe_null', 'srpa_sfr_null',
		'srpa_soi_null', 'srpa_som_null', 'srpa_sos_null', 'srpa_ssk_null',
		'srpa_anx_tval', 'srpa_apr_tval', 'srpa_ata_tval', 'srpa_ats_tval',
		'srpa_att_tval', 'srpa_dep_tval', 'srpa_hyp_tval', 'srpa_ipr_tval',
		'srpa_loc_tval', 'srpa_rwp_tval', 'srpa_sfe_tval', 'srpa_sfr_tval',
		'srpa_soi_tval', 'srpa_som_tval', 'srpa_sos_tval', 'srpa_ssk_tval',
		'srpa_anx_tvalp', 'srpa_apr_tvalp', 'srpa_ata_tvalp', 'srpa_ats_tvalp',
		'srpa_att_tvalp', 'srpa_dep_tvalp','srpa_hyp_tvalp', 'srpa_ipr_tvalp',
		'srpa_loc_tvalp',  'srpa_rwp_tvalp', 'srpa_sfe_tvalp', 'srpa_sfr_tvalp',
		'srpa_soi_tvalp', 'srpa_som_tvalp','srpa_sos_tvalp', 'srpa_ssk_tvalp',
		 'srpa_emosymp_raw', 'srpa_inthyp_raw', 'srpa_intprob_raw', 'srpa_peradj_raw', 'srpa_sprob_raw',
		 'srpa_inthyp_tval', 'srpa_intprob_tval', 'srpa_peradj_tval',
		 'srpa_sprob_tval', 'srpa_emosymp_imean',
		 'srpa_anx_valid', 'srpa_apr_valid',
		'srpa_ata_valid', 'srpa_ats_valid', 'srpa_att_valid', 'srpa_dep_valid',
		'srpa_hyp_valid', 'srpa_ipr_valid', 'srpa_loc_valid', 'srpa_rwp_valid',
		'srpa_sfe_valid', 'srpa_sfr_valid', 'srpa_soi_valid', 'srpa_som_valid',
		'srpa_sos_valid', 'srpa_ssk_valid','srpa_findex', 'srpa_fv', 'srpa_v',
		'srpa_vv', 'srpa_l', 'srpa_lv', 'srpa_con' ,'srpa_conv','srpa_patt',
		'srpa_pattv','srpa_allval','srpa_90val','srpa_scaletotal'

	);

	# REQUIRED: Define an array of fields that must be present for this algorithm to run

	foreach (range(1,176) as $i) {
		array_push($required_fields, "basc_srp_a_$i");
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

	$source_names = array("ats","att","ssk","ata","loc","sos","anx","dep","soi","som","apr","hyp","rwp","ipr","sfe", "sfr"); 
	$source_indexes = array (
		'ats' => array(10,40,70,82,112,142,172),
		'att' => array(37,67,97,127,157,85,115,145,175),
		'ssk' => array(47,77,107,137,27,57,87,117,147),
		'ata' => array(62,92,122,152,100,130,160,119,149),
		'loc' => array(6,36,66,19,49,79,109,139,169),
		'sos' => array(75,105,135,165,26,56,86,116,146,176),
		'anx' => array(11,41,71,101,131,108,138,110,140,170),
		'dep' => array(3,33,63,93,8,38,68,98,21,51,81,111),
		'soi' => array(24,54,84,114,144,30,60,90,120,150),
		'som' => array(4,34,64,9,39,69,99),
		'apr' => array(5,35,65,95,125,53,83,113,143),
		'hyp' => array(124,154,134,164,88,118,148),
		'rwp' => array(155,126,156,42,72,102,132,141,171,173),
		'ipr' => array(151,13,43,73,103,133,163),
		'sfe' => array(1,31,61,91,121,44,74,104),
		'sfr' => array(123,153,16,46,76,106,136,166)
	);

	$validity_arr = array(
		'ats' => 0,
		'att' => 0,
		'ssk' => 0,
		'ata' => 0,
		'loc' => 0,
		'sos' => 0,
		'anx' => 0,
		'dep' => 0,
		'soi' => 0,
		'som' => 0,
		'apr' => 0,
		'hyp' => 0,
		'rwp' => 0,
		'ipr' => 0,
		'sfe' => 0,
		'sfr' => 0
		);


	$mult_factor = array(
		'ats' => 1,
		'att' => 1,
		'ssk' => 1,
		'ata' => 0,
		'loc' => 1,
		'sos' => 1,
		'anx' => 1,
		'dep' => 1,
		'soi' => 1,
		'som' => 0,
		'apr' => 1,
		'hyp' => 1,
		'rwp' => 2,
		'ipr' => 2,
		'sfe' => 2,
		'sfr' => 2
		);

	$validity_key_source = array(

	'anx' => 'srpa_anx_valid',
	'apr' => 'srpa_apr_valid',
	'ata' => 'srpa_ata_valid',
	'ats' => 'srpa_ats_valid',
	'att' => 'srpa_att_valid',
	'dep' => 'srpa_dep_valid',
	'hyp' => 'srpa_hyp_valid',
	'ipr' => 'srpa_ipr_valid',
	'loc' => 'srpa_loc_valid',
	'rwp' => 'srpa_rwp_valid',
	'sfe' => 'srpa_sfe_valid',
	'sfr' => 'srpa_sfr_valid',
	'soi' => 'srpa_soi_valid',
	'som' => 'srpa_som_valid',
	'sos' => 'srpa_sos_valid',
	'ssk' => 'srpa_ssk_valid'
		);

	$F_index = 0; 

	$F_index_values = array(
		104	=> 0,
		152	=> 3,
		99	=> 3,
		71	=> 3,
		1	=> 1,
		95	=> 0,
		13	=> 0,
		68	=> 0,
		21	=> 0,
		43	=> 0,
		73	=> 3,
		176	=> 3,
		86	=> 3,
		149	=> 3,
		100	=> 3
	);

	$L_index = 0; 

	$L_index_values = array(
		58	=> 0,
		28	=> 0,
		12	=> 0,
		48	=> 1,
		7	=> 1,
		161	=> 0,
		25	=> 0,
		55	=> 1,
		52	=> 0,
		129	=> 3,
		17	=> 0,
		22	=> 0,
		45	=> 0,
		23	=> 0,
		15	=> 0,
	);

	$cons_index_values = array(
		61 => 1,
		63 => 3,
		43 => 13,
		51 => 24,
		44 => 31,
		69 => 34,
		53 => 35,
		141 => 72,
		166 => 76,
		147 => 77,
		139 => 79,
		172 => 82,
		145 => 85,
		124 => 88,
		121 => 91,
		105 => 93,
		140 => 110,
		160 => 122,
		163 => 133,
		173 => 155
		);

	$v_index = 0;
	$cons_index = 0;
	$resp_pattern = 0;
	$last_response = 0;



	# These are some fields that need their values flipped before scoring. Instead of going from 0 => 3, they go from 3 => 0
	$new_source = array();
	$flipped_values = array(70,73,85,91,95,103,121,125,145,151);
	$flipped_tf = array(1,2,3,4,5,6,8,9,10,11,14,16,18,19,20,21,24,26,27,29,32,33,34,35,36,38,39,40,41,42,46,47,49,50,51,53,54,56,57,59,60,61,62,63,64,65,66,68,69);
	$one_response_tf = array(7,12,15,17,22,23,25,28,45,48,52,55,58);
	$one_response_flipped_tf = array(12,15,17,22,23,25,28,45,52,58);
	$one_response_reg = array(129,161);
	foreach ($required_fields as $i => $req_name) {
		$index = $i+1;
        if (empty($src[$req_name]) or is_null($src[$req_name])) {
			$this->module->emDebug("Setting new source to null: " . $req_name . ", i value: " . $i . ", index: " . $index . "\n");
            $new_source[$req_name] = null;
        } else {
            $val = $src[$req_name] - 1;
            #		$this->module->emDebug("Index " . $index);
            if ($index == 159 or $index == 94) {
                #			$this->module->emDebug("Val: " . $val . " so v_index was " . $v_index);
                $v_index += $val;
                #			$this->module->emDebug("Now its " . $v_index);
            }
            if ($index == 32 or $index == 18 or $index == 59) {
                if ($val == 1) {
                    #				$this->module->emDebug("Val: " . $val . " so v_index was " . $v_index);
                    $v_index += 2;
                    #				$this->module->emDebug("Now its " . $v_index);
                }
            }

            if (in_array(($index), $flipped_values)) {
                $new_source[$req_name] = ($val != 4) ? 3 - $val : null;
                #		$this->module->emDebug("Flipped values: i=$i, field_name = $req_name, val = $val, new source = $new_source[$req_name]");
            } else if (($index) < 70) {
                if (in_array(($index), $one_response_tf)) {
                    $realval = 0;
                    if (in_array(($index), $one_response_flipped_tf)) {
                        $new_source[$req_name] = ($val != 4) ? 1 - $val : null;
                    } else {
                        $new_source[$req_name] = ($val != 4) ? $val : null;
                    }
                } else if (in_array(($index), $flipped_tf)) {
                    $realval = 0;
                    if ($val == 0) {
                        $realval = 2;
                    } else {
                        $realval = 0;
                    }
                    $new_source[$req_name] = ($val != 4) ? $realval : null;
                } else {
                    $realval = 0;
                    if ($val == 1) {
                        $realval = 2;
                    }
                    $new_source[$req_name] = ($val != 4) ? $realval : null;
                }
            } else {
                if (in_array(($index), $one_response_reg)) {
                    $realval = 0;
                    if (($index) == 129) {
                        if ($val == 3) {
                            $realval = 1;
                        }
                        $new_source[$req_name] = ($val != 4) ? $realval : null;
                    } else if (($index) == 161) {
                        if ($val == 0) {
                            $realval = 1;
                        }
                        $new_source[$req_name] = ($val != 4) ? $realval : null;
                    }
                } else {
                    $new_source[$req_name] = ($val != 4) ? $val : null;
                }
            }

            if (array_key_exists(($index), $F_index_values)) {
                if ($val == $F_index_values[($index)]) {
                    $F_index++;
                }
            }

            if (array_key_exists(($index), $L_index_values)) {
                if ($val == $L_index_values[($index)]) {
                    $L_index++;
                }
            }

            if (array_key_exists(($index), $cons_index_values)) {
                $cons_pair_val = $new_source[$required_fields[$cons_index_values[$index]]];
                if ($cons_pair_val != $new_source[$req_name]) {
                    $cons_index++;
                }
            }

            foreach ($source_indexes as $scale => $qs) {
                if (in_array(($index), $qs)) {
                    $currVal = $validity_arr[$scale];
                    if (!(isset($val) and (strlen($val) <= 0))) {
                        $validity_arr[$scale] = $currVal + 1;
                    }
                }
            }

            if ($index == 1) {
                $last_response = $val;
            } else {
                if ($val != $last_response) {
                    $resp_pattern++;
                }
                $last_response = $val;
            }
        }
	}

	### IMPLEMENT RAW SCORING ###
	# This is the array source
	# Define lists of questions that correspond to each subscale
	# Note: Most questions are scored as N=0, S=1, O=2, A=3 but some questions have reversed scoring
	#    The following scoring should already be reversed from above: 5,14,26,63,65,76,81,91,104,106,107,108,122,146
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
                if (empty($val) and is_null($val)) {
                    $null_counter[$result_name]++;
				} else {
                    $raw_scores[$result_name] += $val;
				}
			}
		}
	}

	$tvalue = array(
		'ats' => array(
				0 => 34, 1 => 36, 2 => 38, 3 => 40, 4 => 42, 5 => 44,
				6 => 46, 7 => 49, 8 => 51, 9 => 53, 10 => 55,
				11 => 57, 12 => 59, 13 => 61, 14 => 63, 15 => 66,
				16 => 68, 17 => 70, 18 => 72, 19 => 74
			),
		'att' => array(
				0 => 35, 1 => 37, 2 => 39, 3 => 41, 4 => 43, 5 => 45,
				6 => 47, 7 => 49, 8 => 51, 9 => 53, 10 => 55,
				11 => 57, 12 => 59, 13 => 61, 14 => 63, 15 => 65,
				16 => 67, 17 => 69, 18 => 70, 19 => 72, 20 => 74,
				21 => 76, 22 => 78, 23 => 80, 24 => 82, 25 => 84
			),
		'ssk' => array(
				0 => 23, 1 => 26, 2 => 28, 3 => 30, 4 => 32, 5 => 35,
				6 => 37, 7 => 39, 8 => 41, 9 => 43, 10 => 46,
				11 => 48, 12 => 50, 13 => 52, 14 => 55, 15 => 57,
				16 => 59, 17 => 61, 18 => 64, 19 => 66, 20 => 68,
				21 => 70, 22 => 73, 23 => 75, 24 => 77
			),
		'ata' => array(
				0 => 40, 1 => 43, 2 => 45, 3 => 47, 4 => 49, 5 => 51,
				6 => 53, 7 => 56, 8 => 58, 9 => 60, 10 => 62,
				11 => 64, 12 => 66, 13 => 69, 14 => 71, 15 => 73,
				16 => 75, 17 => 77, 18 => 79, 19 => 82, 20 => 84,
				21 => 86, 22 => 88, 23 => 90, 24 => 93, 25 => 95,
				26 => 97
			),
		'loc' => array(
				0 => 36, 1 => 38, 2 => 40, 3 => 42, 4 => 44, 5 => 46,
				6 => 48, 7 => 50, 8 => 52, 9 => 54, 10 => 56,
				11 => 58, 12 => 60, 13 => 62, 14 => 64, 15 => 66,
				16 => 68, 17 => 70, 18 => 72, 19 => 74, 20 => 76,
				21 => 78, 22 => 80
			),
		'sos' => array(
				0 => 34, 1 => 36, 2 => 38, 3 => 40, 4 => 42, 5 => 44,
				6 => 46, 7 => 47, 8 => 49, 9 => 51, 10 => 53,
				11 => 55, 12 => 57, 13 => 59, 14 => 61, 15 => 63,
				16 => 65, 17 => 66, 18 => 68, 19 => 70, 20 => 72,
				21 => 74, 22 => 76, 23 => 78, 24 => 80, 25 => 82,
				26 => 84, 27 => 86, 28 => 87
			),
		'anx' => array(
				0 => 33, 1 => 35, 2 => 36, 3 => 38, 4 => 39, 5 => 40,
				6 => 42, 7 => 43, 8 => 45, 9 => 46, 10 => 48,
				11 => 49, 12 => 51, 13 => 52, 14 => 54, 15 => 55,
				16 => 57, 17 => 58, 18 => 60, 19 => 61, 20 => 62,
				21 => 64, 22 => 65, 23 => 67, 24 => 68, 25 => 70,
				26 => 71, 27 => 73, 28 => 74, 29 => 76, 30 => 77,
				31 => 79, 32 => 80, 33 => 81, 34 => 83, 35 => 84
			),
		'dep' => array(
				0 => 39, 1 => 41, 2 => 42, 3 => 44, 4 => 45, 5 => 47,
				6 => 48, 7 => 50, 8 => 52, 9 => 53, 10 => 55,
				11 => 56, 12 => 58, 13 => 59, 14 => 61, 15 => 63, 
				16 => 64, 17 => 66, 18 => 67, 19 => 69, 20 => 70,
				21 => 72, 22 => 74, 23 => 75, 24 => 77, 25 => 78,
				26 => 80, 27 => 81, 28 => 83
			),
		'soi' => array(
				0 => 34, 1 => 36, 2 => 38, 3 => 40, 4 => 42, 5 => 44,
				6 => 46, 7 => 48, 8 => 50, 9 => 52, 10 => 54,
				11 => 56, 12 => 58, 13 => 60, 14 => 62, 15 => 64,
				16 => 66, 17 => 68, 18 => 70, 19 => 73, 20 => 75,
				21 => 77, 22 => 79, 23 => 81, 24 => 83, 25 => 85,
				26 => 87
			),
		'som' => array(
				0 => 40, 1 => 42, 2 => 45, 3 => 48, 4 => 51, 5 => 54,
				6 => 56, 7 => 59, 8 => 62, 9 => 65, 10 => 68, 
				11 => 71, 12 => 73, 13 => 76, 14 => 79, 15 => 82
			),
		'apr' => array(
				0 => 32, 1 => 34, 2 => 36, 3 => 38, 4 => 40, 5 => 42,
				6 => 45, 7 => 47, 8 => 49, 9 => 51, 10 => 53,
				11 => 55, 12 => 57, 13 => 60, 14 => 62, 15 => 64,
				16 => 66, 17 => 68, 18 => 70, 19 => 72, 20 => 75,
				21 => 77, 22 => 79, 23 => 81
			),
		'hyp' => array(
				0 => 33, 1 => 35, 2 => 38, 3 => 40, 4 => 43, 5 => 46,
				6 => 48, 7 => 51, 8 => 53, 9 => 56, 10 => 58, 
				11 => 61, 12 => 64, 13 => 66, 14 => 69, 15 => 71,
				16 => 74, 17 => 76, 18 => 79, 19 => 81, 20 => 84,
				21 => 87
			),
		'rwp' => array(
				0 => 23, 1 => 24, 2 => 26, 3 => 27, 4 => 29, 5 => 30,
				6 => 32, 7 => 33, 8 => 35, 9 => 36, 10 => 38, 
				11 => 39, 12 => 41, 13 => 42, 14 => 44, 15 => 45,
				16 => 46, 17 => 48, 18 => 49, 19 => 51, 20 => 52,
				21 => 54, 22 => 55, 23 => 57, 24 => 58, 25 => 60,
				26 => 61, 27 => 63, 28 => 64, 29 => 66
			),
		'ipr' => array(
				0 => 12, 1 => 15, 2 => 17, 3 => 20, 4 => 23, 5 => 25,
				6 => 28, 7 => 31, 8 => 33, 9 => 36, 10 => 39,
				11 => 41, 12 => 44, 13 => 47, 14 => 49, 15 => 52,
				16 => 54, 17 => 57, 18 => 60, 19 => 62
			),
		'sfe' => array(
				0 => 19, 1 => 21, 2 => 23, 3 => 26, 4 => 28, 5 => 30,
				6 => 32, 7 => 34, 8 => 36, 9 => 38, 10 => 41,
				11 => 43, 12 => 45, 13 => 47, 14 => 49, 15 => 51,
				16 => 53, 17 => 56, 18 => 58, 19 => 60, 20 => 62
			),
		'sfr' => array(
				0 => 16, 1 => 18, 2 => 21, 3 => 23, 4 => 26, 5 => 28,
				6 => 31, 7 => 33, 8 => 36, 9 => 38, 10 => 41,
				11 => 43, 12 => 46, 13 => 48, 14 => 51, 15 => 53, 
				16 => 56, 17 => 59, 18 => 61, 19 => 64, 20 => 66,
				21 => 69, 22 => 71
			)
		);
	$tvalue_perc = array(
		'ats' => array(
				0 => 1, 1 => 3, 2 => 9, 3 => 17, 4 => 26, 5 => 34,
				6 => 43, 7 => 51, 8 => 58, 9 => 64, 10 => 70,
				11 => 76, 12 => 81, 13 => 85, 14 => 88, 15 => 91,
				16 => 94, 17 => 96, 18 => 97, 19 => 98
			),
		'att' => array(
				0 => 2, 1 => 6, 2 => 12, 3 => 12, 4 => 20, 4 => 28, 5 => 36, 
				6 => 44, 7 => 52, 8 => 59, 9 => 65, 10 => 71, 
				11 => 76, 12 => 81, 13 => 85, 14 => 88, 15 => 91, 
				16 => 93, 17 => 95, 18 => 96, 19 => 97, 20 => 98, 
				21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99
			),
		'ssk' => array(
				0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 6,
				6 => 10, 7 => 14, 8 => 20, 9 => 27, 10 => 35,
				11 => 43, 12 => 51, 13 => 60, 14 => 68, 15 => 75,
				16 => 81, 17 => 86, 18 => 91, 19 => 94, 20 => 96, 
				21 => 98, 22 => 99, 23 => 99, 24 => 99
			),
		'ata' => array(
				0 => 12, 1 => 25, 2 => 38, 3 => 49, 4 => 58, 5 => 66,
				6 => 72, 7 => 77, 8 => 81, 9 => 85, 10 => 88,
				11 => 90, 12 => 92, 13 => 94, 14 => 95, 15 => 96, 
				16 => 97, 17 => 98, 18 => 98, 19 => 99, 20 => 99,
				21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
				26 => 99
			),
		'loc' => array(
				0 => 3, 1 => 9, 2 => 17, 3 => 25, 4 => 33, 5 => 41,
				6 => 49, 7 => 56, 8 => 62, 9 => 68, 10 => 73,
				11 => 78, 12 => 82, 13 => 86, 14 => 89, 15 => 92,
				16 => 94, 17 => 95, 18 => 97, 19 => 98, 20 => 99, 
				21 => 99, 22 => 99
			),
		'sos' => array(
				0 => 2, 1 => 5, 2 => 9, 3 => 15, 4 => 21, 5 => 29,
				6 => 37, 7 => 45, 8 => 53, 9 => 60, 10 => 67,
				11 => 72, 12 => 78, 13 => 82, 14 => 86, 15 => 89,
				16 => 91, 17 => 93, 18 => 95, 19 => 96, 20 => 97,
				21 => 98, 22 => 98, 23 => 99, 24 => 99, 25 => 99, 
				26 => 99, 27 => 99, 28 => 99
			),
		'anx' => array(
				0 => 1, 1 => 2, 2 => 4, 3 => 8, 4 => 12, 5 => 17,
				6 => 23, 7 => 29, 8 => 35, 9 => 41, 10 => 47, 
				11 => 53, 12 => 58, 13 => 64, 14 => 68, 15 => 72,
				16 => 76, 17 => 80, 18 => 83, 19 => 86, 20 => 88, 
				21 => 90, 22 => 92, 23 => 93, 24 => 95, 25 => 96, 
				26 => 97, 27 => 97, 28 => 98, 29 => 98, 30 => 99,
				31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99
			),
		'dep' => array(
				0 => 1, 1 => 12, 2 => 25, 3 => 36, 4 => 44, 5 => 51,
				6 => 57, 7 => 62, 8 => 67, 9 => 71, 10 => 74,
				11 => 77, 12 => 80, 13 => 82, 14 => 85, 15 => 87,
				16 => 89, 17 => 90, 18 => 92, 19 => 93, 20 => 94,
				21 => 95, 22 => 96, 23 => 97, 24 => 98, 25 => 99,
				26 => 99, 27 => 99, 28 => 99
			),
		'soi' => array(
				0 => 1, 1 => 3, 2 => 8, 3 => 15, 4 => 24, 5 => 32,
				6 => 41, 7 => 49, 8 => 57, 9 => 64, 10 => 70, 
				11 => 76, 12 => 80, 13 => 84, 14 => 88, 15 => 90,
				16 => 93, 17 => 95, 18 => 96, 19 => 97, 20 => 98,
				21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
				26 => 99
			),
		'som' => array(
				0 => 12, 1 => 28, 2 => 41, 3 => 52, 4 => 61, 5 => 69,
				6 => 75, 7 => 81, 8 => 85, 9 => 89, 10 => 93,
				11 => 95, 12 => 97, 13 => 99, 14 => 99, 15 => 99
			),
		'apr' => array(
				0 => 1, 1 => 3, 2 => 7, 3 => 12, 4 => 19, 5 => 26,
				6 => 33, 7 => 41, 8 => 49, 9 => 56, 10 => 63,
				11 => 70, 12 => 76, 13 => 81, 14 => 86, 15 => 90,
				16 => 93, 17 => 96, 18 => 97, 19 => 99, 20 => 99,
				21 => 99, 22 => 99, 23 => 99
			),
		'hyp' => array(
				0 => 1, 1 => 3, 2 => 9, 3 => 17, 4 => 27, 5 => 38,
				6 => 49, 7 => 58, 8 => 67, 9 => 75, 10 => 81,
				11 => 86, 12 => 90, 13 => 93, 14 => 95, 15 => 97,
				16 => 98, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
				21 => 99
			),
		'rwp' => array(
				0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4,
				6 => 5, 7 => 7, 8 => 9, 9 => 11, 10 => 13,
				11 => 16, 12 => 19, 13 => 22, 14 => 26, 15 => 29,
				16 => 34, 17 => 38, 18 => 43, 19 => 48, 20 => 54, 
				21 => 59, 22 => 65, 23 => 71, 24 => 77, 25 => 82,
				26 => 87, 27 => 92, 28 => 95, 29 => 98
			),
		'ipr' => array(
				0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3,
				6 => 4, 7 => 6, 8 => 8, 9 => 10, 10 => 14,
				11 => 18, 12 => 23, 13 => 30, 14 => 38, 15 => 48, 
				16 => 59, 17 => 72, 18 => 86, 19 => 97
			),
		'sfe' => array(
				0 => 1, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5,
				6 => 7, 7 => 9, 8 => 12, 9 => 15, 10 => 18,
				11 => 22, 12 => 26, 13 => 31, 14 => 37, 15 => 44,
				16 => 52, 17 => 62, 18 => 72, 19 => 87, 20 => 99
			),
		'sfr' => array(
				0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
				6 => 4, 7 => 6, 8 => 9, 9 => 13, 10 => 19,
				11 => 26, 12 => 33, 13 => 42, 14 => 51, 15 => 61, 
				16 => 70, 17 => 79, 18 => 86, 19 => 92, 20 => 96, 
				21 => 99, 22 => 99
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
	$q_answered = 176;

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
	$composite_names = array('sprob', 'intprob', 'inthyp', 'peradj', 'emosymp');
	$composite_calc = array(
			'sprob' => array('ats', 'att', 'ssk'),
			'intprob' => array('ata', 'loc', 'sos', 'anx', 'dep', 'soi', 'som'),
			'inthyp' => array('apr', 'hyp'),
			'peradj' => array('rwp', 'ipr', 'sfe', 'sfr'),
			'emosymp' => array('sos', 'anx', 'dep', 'soi', 'som', 'sfe', 'sfr')
				);


	$composite_comb_tvalues = array(
			'sprob' => array( 
				25 => array('min' => 92, 'max' => 93), 26 => array('min' => 94, 'max' => 94), 27 => array('min' => 96, 'max' => 97),
				28 => array('min' => 98, 'max' => 100), 29 => array('min' => 101, 'max' => 102), 30 => array('min' => 103, 'max' => 104),
				31 => array('min' => 105, 'max' => 107), 32 => array('min' => 108, 'max' => 109), 33 => array('min' => 110, 'max' => 111),
				34 => array('min' => 112, 'max' => 114), 35 => array('min' => 115, 'max' => 116), 36 => array('min' => 117, 'max' => 118),
				37 => array('min' => 119, 'max' => 121), 38 => array('min' => 122, 'max' => 123), 39 => array('min' => 124, 'max' => 125),
				40 => array('min' => 126, 'max' => 127), 41 => array('min' => 128, 'max' => 130), 42 => array('min' => 131, 'max' => 132),
				43 => array('min' => 133, 'max' => 134), 44 => array('min' => 135, 'max' => 137), 45 => array('min' => 138, 'max' => 139),
				46 => array('min' => 140, 'max' => 141), 47 => array('min' => 142, 'max' => 144), 48 => array('min' => 145, 'max' => 146),
				49 => array('min' => 147, 'max' => 148), 50 => array('min' => 149, 'max' => 151), 51 => array('min' => 152, 'max' => 153),
				52 => array('min' => 154, 'max' => 155), 53 => array('min' => 156, 'max' => 158), 54 => array('min' => 159, 'max' => 160),
				55 => array('min' => 161, 'max' => 162), 56 => array('min' => 163, 'max' => 165), 57 => array('min' => 166, 'max' => 167),
				58 => array('min' => 168, 'max' => 169), 59 => array('min' => 170, 'max' => 172), 60 => array('min' => 173, 'max' => 174),
				61 => array('min' => 175, 'max' => 176), 62 => array('min' => 177, 'max' => 178), 63 => array('min' => 179, 'max' => 181),
				64 => array('min' => 182, 'max' => 183), 65 => array('min' => 184, 'max' => 185), 66 => array('min' => 186, 'max' => 188), 
				67 => array('min' => 189, 'max' => 188), 68 => array('min' => 191, 'max' => 192), 69 => array('min' => 193, 'max' => 195),
				70 => array('min' => 196, 'max' => 197), 71 => array('min' => 198, 'max' => 199), 72 => array('min' => 200, 'max' => 202),
				73 => array('min' => 203, 'max' => 204), 74 => array('min' => 205, 'max' => 206), 75 => array('min' => 207, 'max' => 209),
				76 => array('min' => 210, 'max' => 211), 77 => array('min' => 212, 'max' => 213), 78 => array('min' => 214, 'max' => 216),
				79 => array('min' => 217, 'max' => 218), 80 => array('min' => 219, 'max' => 220), 81 => array('min' => 221, 'max' => 223),
				82 => array('min' => 224, 'max' => 225), 83 => array('min' => 226, 'max' => 227), 84 => array('min' => 228, 'max' => 230),
				85 => array('min' => 231, 'max' => 232), 86 => array('min' => 233, 'max' => 234), 87 => array('min' => 235, 'max' => 235)
				),
			'intprob' => array(
				33 => array('min' => 256, 'max' => 257), 34 => array('min' => 258, 'max' => 263), 35 => array('min' => 264, 'max' => 269),
				36 => array('min' => 270, 'max' => 274), 37 => array('min' => 275, 'max' => 280), 38 => array('min' => 281, 'max' => 285),
				39 => array('min' => 286, 'max' => 291), 40 => array('min' => 292, 'max' => 296), 41 => array('min' => 297, 'max' => 302),
				42 => array('min' => 303, 'max' => 308), 43 => array('min' => 309, 'max' => 313), 44 => array('min' => 314, 'max' => 319),
				45 => array('min' => 320, 'max' => 324), 46 => array('min' => 325, 'max' => 330), 47 => array('min' => 331, 'max' => 336),
				48 => array('min' => 337, 'max' => 341), 49 => array('min' => 342, 'max' => 347), 50 => array('min' => 348, 'max' => 352),
				51 => array('min' => 353, 'max' => 358), 52 => array('min' => 359, 'max' => 363), 53 => array('min' => 364, 'max' => 369),
				54 => array('min' => 370, 'max' => 375), 55 => array('min' => 376, 'max' => 380), 56 => array('min' => 381, 'max' => 386),
				57 => array('min' => 387, 'max' => 391), 58 => array('min' => 392, 'max' => 397), 59 => array('min' => 398, 'max' => 403),
				60 => array('min' => 404, 'max' => 408), 61 => array('min' => 409, 'max' => 414), 62 => array('min' => 415, 'max' => 419),
				63 => array('min' => 420, 'max' => 425), 64 => array('min' => 426, 'max' => 430), 65 => array('min' => 431, 'max' => 436),
				66 => array('min' => 437, 'max' => 442), 67 => array('min' => 443, 'max' => 447), 68 => array('min' => 448, 'max' => 453),
				69 => array('min' => 454, 'max' => 458), 70 => array('min' => 459, 'max' => 464), 71 => array('min' => 465, 'max' => 469),
				72 => array('min' => 470, 'max' => 475), 73 => array('min' => 476, 'max' => 481), 74 => array('min' => 482, 'max' => 486),
				75 => array('min' => 487, 'max' => 492), 76 => array('min' => 493, 'max' => 497), 77 => array('min' => 498, 'max' => 503),
				78 => array('min' => 504, 'max' => 509), 79 => array('min' => 510, 'max' => 514), 80 => array('min' => 515, 'max' => 520),
				81 => array('min' => 521, 'max' => 525), 82 => array('min' => 526, 'max' => 531), 83 => array('min' => 532, 'max' => 536), 
				84 => array('min' => 537, 'max' => 542), 85 => array('min' => 543, 'max' => 548), 86 => array('min' => 549, 'max' => 553),
				87 => array('min' => 554, 'max' => 559), 88 => array('min' => 560, 'max' => 564), 89 => array('min' => 565, 'max' => 570),
				90 => array('min' => 571, 'max' => 576), 91 => array('min' => 577, 'max' => 581), 92 => array('min' => 582, 'max' => 587),
				93 => array('min' => 588, 'max' => 592), 94 => array('min' => 593, 'max' => 598), 95 => array('min' => 599, 'max' => 600)
				),
			'inthyp' => array(
				30 => array('min' => 65, 'max' => 65), 31 => array('min' => 66, 'max' => 67), 32 => array('min' => 68, 'max' => 69),
				33 => array('min' => 70, 'max' => 70), 34 => array('min' => 71, 'max' => 72), 35 => array('min' => 73, 'max' => 74),
				36 => array('min' => 75, 'max' => 76), 37 => array('min' => 77, 'max' => 78), 38 => array('min' => 79, 'max' => 79),
				39 => array('min' => 80, 'max' => 81), 40 => array('min' => 82, 'max' => 83), 41 => array('min' => 84, 'max' => 85),
				42 => array('min' => 86, 'max' => 86), 43 => array('min' => 87, 'max' => 88), 44 => array('min' => 89, 'max' => 90),
				45 => array('min' => 91, 'max' => 92), 46 => array('min' => 93, 'max' => 93), 47 => array('min' => 94, 'max' => 95),
				48 => array('min' => 96, 'max' => 97), 49 => array('min' => 98, 'max' => 99), 50 => array('min' => 100, 'max' => 100),
				51 => array('min' => 101, 'max' => 102), 52 => array('min' => 103, 'max' => 104), 53 => array('min' => 105, 'max' => 106),
				54 => array('min' => 107, 'max' => 107), 55 => array('min' => 108, 'max' => 109), 56 => array('min' => 110, 'max' => 111), 
				57 => array('min' => 112, 'max' => 113), 58 => array('min' => 114, 'max' => 114), 59 => array('min' => 115, 'max' => 116),
				60 => array('min' => 117, 'max' => 118), 61 => array('min' => 119, 'max' => 120), 62 => array('min' => 121, 'max' => 121),
				63 => array('min' => 122, 'max' => 123), 64 => array('min' => 124, 'max' => 125), 65 => array('min' => 126, 'max' => 127),
				66 => array('min' => 128, 'max' => 129), 67 => array('min' => 130, 'max' => 130), 68 => array('min' => 131, 'max' => 132),
				69 => array('min' => 133, 'max' => 134), 70 => array('min' => 135, 'max' => 136), 71 => array('min' => 137, 'max' => 137),
				72 => array('min' => 138, 'max' => 139), 73 => array('min' => 140, 'max' => 141), 74 => array('min' => 142, 'max' => 143),
				75 => array('min' => 144, 'max' => 144), 76 => array('min' => 145, 'max' => 146), 77 => array('min' => 147, 'max' => 148), 
				78 => array('min' => 149, 'max' => 150), 79 => array('min' => 151, 'max' => 151), 80 => array('min' => 152, 'max' => 153),
				81 => array('min' => 154, 'max' => 155), 82 => array('min' => 156, 'max' => 157), 83 => array('min' => 158, 'max' => 158),
				84 => array('min' => 159, 'max' => 160), 85 => array('min' => 161, 'max' => 162), 86 => array('min' => 163, 'max' => 164),
				87 => array('min' => 165, 'max' => 165), 88 => array('min' => 166, 'max' => 167), 89 => array('min' => 168, 'max' => 168)
				),
			'peradj' => array(
				10 => array('min' => 70, 'max' => 84), 11 => array('min' => 85, 'max' => 87), 12 => array('min' => 88, 'max' => 90),
				13 => array('min' => 91, 'max' => 93), 14 => array('min' => 94, 'max' => 96), 15 => array('min' => 97, 'max' => 99),
				16 => array('min' => 100, 'max' => 102), 17 => array('min' => 103, 'max' => 104), 18 => array('min' => 105, 'max' => 107),
				19 => array('min' => 108, 'max' => 110), 20 => array('min' => 111, 'max' => 113), 21 => array('min' => 114, 'max' => 116),
				22 => array('min' => 117, 'max' => 119), 23 => array('min' => 120, 'max' => 122), 24 => array('min' => 123, 'max' => 125),
				25 => array('min' => 126, 'max' => 128), 26 => array('min' => 129, 'max' => 131), 27 => array('min' => 132, 'max' => 134),
				28 => array('min' => 135, 'max' => 137), 29 => array('min' => 138, 'max' => 140), 30 => array('min' => 141, 'max' => 142),
				31 => array('min' => 143, 'max' => 145), 32 => array('min' => 146, 'max' => 148), 33 => array('min' => 149, 'max' => 151),
				34 => array('min' => 152, 'max' => 154), 35 => array('min' => 155, 'max' => 157), 36 => array('min' => 158, 'max' => 160),
				37 => array('min' => 161, 'max' => 163), 38 => array('min' => 164, 'max' => 166), 39 => array('min' => 167, 'max' => 169),
				40 => array('min' => 170, 'max' => 172), 41 => array('min' => 173, 'max' => 175), 42 => array('min' => 176, 'max' => 178),
				43 => array('min' => 179, 'max' => 180), 44 => array('min' => 181, 'max' => 183), 45 => array('min' => 184, 'max' => 186),
				46 => array('min' => 187, 'max' => 189), 47 => array('min' => 190, 'max' => 192), 48 => array('min' => 193, 'max' => 195),
				49 => array('min' => 196, 'max' => 198), 50 => array('min' => 199, 'max' => 201), 51 => array('min' => 202, 'max' => 204),
				52 => array('min' => 205, 'max' => 207), 53 => array('min' => 208, 'max' => 210), 54 => array('min' => 211, 'max' => 213),
				55 => array('min' => 214, 'max' => 216), 56 => array('min' => 217, 'max' => 219), 57 => array('min' => 220, 'max' => 221), 
				58 => array('min' => 222, 'max' => 224), 59 => array('min' => 225, 'max' => 227), 60 => array('min' => 228, 'max' => 230),
				61 => array('min' => 231, 'max' => 233), 62 => array('min' => 234, 'max' => 236), 63 => array('min' => 237, 'max' => 239), 
				64 => array('min' => 240, 'max' => 242), 65 => array('min' => 243, 'max' => 245), 66 => array('min' => 246, 'max' => 248),
				67 => array('min' => 249, 'max' => 251), 68 => array('min' => 252, 'max' => 254), 69 => array('min' => 255, 'max' => 257),
				70 => array('min' => 258, 'max' => 259), 71 => array('min' => 260, 'max' => 261)
				),
			'emosymp' => array(
				23 => array('min' => 175, 'max' => 178), 24 => array('min' => 179, 'max' => 183), 25 => array('min' => 184, 'max' => 187),
				26 => array('min' => 188, 'max' => 192), 27 => array('min' => 193, 'max' => 196), 28 => array('min' => 197, 'max' => 201),
				29 => array('min' => 202, 'max' => 206), 30 => array('min' => 207, 'max' => 210), 31 => array('min' => 211, 'max' => 215),
				32 => array('min' => 216, 'max' => 219), 33 => array('min' => 220, 'max' => 224), 34 => array('min' => 225, 'max' => 229),
				35 => array('min' => 230, 'max' => 233), 36 => array('min' => 234, 'max' => 238), 37 => array('min' => 239, 'max' => 242),
				38 => array('min' => 243, 'max' => 247), 39 => array('min' => 248, 'max' => 251), 40 => array('min' => 252, 'max' => 256),
				41 => array('min' => 257, 'max' => 261), 42 => array('min' => 262, 'max' => 265), 43 => array('min' => 266, 'max' => 270),
				44 => array('min' => 271, 'max' => 274), 45 => array('min' => 275, 'max' => 279), 46 => array('min' => 280, 'max' => 283),
				47 => array('min' => 284, 'max' => 288), 48 => array('min' => 289, 'max' => 293), 49 => array('min' => 294, 'max' => 297),
				50 => array('min' => 298, 'max' => 302), 51 => array('min' => 303, 'max' => 306), 52 => array('min' => 307, 'max' => 311),
				53 => array('min' => 312, 'max' => 316), 54 => array('min' => 317, 'max' => 320), 55 => array('min' => 321, 'max' => 325),
				56 => array('min' => 326, 'max' => 329), 57 => array('min' => 330, 'max' => 334), 58 => array('min' => 335, 'max' => 338),
				59 => array('min' => 339, 'max' => 343), 60 => array('min' => 344, 'max' => 348), 61 => array('min' => 349, 'max' => 352), 
				62 => array('min' => 353, 'max' => 357), 63 => array('min' => 358, 'max' => 361), 64 => array('min' => 362, 'max' => 366),
				65 => array('min' => 367, 'max' => 370), 66 => array('min' => 371, 'max' => 375), 67 => array('min' => 376, 'max' => 380), 
				68 => array('min' => 381, 'max' => 384), 69 => array('min' => 385, 'max' => 389), 70 => array('min' => 390, 'max' => 393),
				71 => array('min' => 394, 'max' => 398), 72 => array('min' => 399, 'max' => 403), 73 => array('min' => 404, 'max' => 407),
				74 => array('min' => 408, 'max' => 412), 75 => array('min' => 413, 'max' => 416), 76 => array('min' => 417, 'max' => 421),
				77 => array('min' => 422, 'max' => 425), 78 => array('min' => 426, 'max' => 430), 79 => array('min' => 431, 'max' => 435),
				80 => array('min' => 436, 'max' => 439), 81 => array('min' => 440, 'max' => 444), 82 => array('min' => 445, 'max' => 448),
				83 => array('min' => 449, 'max' => 453), 84 => array('min' => 454, 'max' => 457), 85 => array('min' => 458, 'max' => 462),
				86 => array('min' => 463, 'max' => 467), 87 => array('min' => 468, 'max' => 471), 88 => array('min' => 472, 'max' => 477),
				89 => array('min' => 478, 'max' => 482), 90 => array('min' => 483, 'max' => 487), 91 => array('min' => 488, 'max' => 491),
				92 => array('min' => 492, 'max' => 496), 93 => array('min' => 497, 'max' => 501), 94 => array('min' => 502, 'max' => 505),
				95 => array('min' => 506, 'max' => 506)
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
			$result += $tvalue_scores[$name];
		}

		$result_tval = lookup_composite_tvalue($composite_comb_tvalues[$field_names], $result); 
		$composite_raw[$field_names . '_comp'] = $result;
		if ($field_names != 'emosymp')
		{
			$composite_tval[$field_names . '_comp_tval'] = $result_tval;
		}
		if ($field_names == 'emosymp') {
			$num_items = count($composite_calc[$field_names]);
			$composite_mean[$field_names . '_imean'] = 100-$result/$num_items;
		}
	}

	### INDEXES ###

	$completeness_interpret = array();
		foreach ($validity_key_source as $scale => $field_name)
		{
			$null_count = $null_counter[$scale];
			if ($null_count > 2)
			{
				$completeness_interpret[$field_name] = 0;
			}
			else
			{
				$completeness_interpret[$field_name] = 1;
			}
		}

		# F_index result
		$F_interpret = 1;
		if ($F_index >= 4 and $F_index <= 6)
		{
			$F_interpret = 2;
		}
		else if ($F_index >= 7 and $F_index <= 15)
		{
			$F_interpret = 3;
		}
		$F_index_result = array($F_index, $F_interpret);


		# L_index result
		$L_interpret = 1;
		if ($L_index >= 9 and $L_index <= 11)
		{
			$L_interpret = 2;
		}
		else if ($L_index >= 12 and $L_index <= 15)
		{
			$L_interpret = 3;
		}
		$L_index_result = array($L_index, $L_interpret);

		#V_Index result
		$V_interpret = 1;
		if ($v_index == 3)
		{
			$V_interpret = 2;
		}
		else if ($v_index >= 4 and $v_index <= 12)
		{
			$V_interpret = 3;
		}
		$V_index_result = array($v_index, $V_interpret);

		#Consistency Index result
		$cons_interpret = 1;
		if ($cons_index >= 15 and $cons_index <= 20)
		{
			$cons_interpret = 2;
		}
		else if ($cons_index >= 21)
		{
			$cons_interpret = 3;
		}
		$cons_index_result = array($cons_index, $cons_interpret);


		# Response Pattern result
		$resp_pattern_interpret = 0;
		if ($resp_pattern >= 100 and $resp_pattern <= 129)
		{
			$resp_pattern_interpret = 1;
		}
		else if ($resp_pattern >= 130 and $resp_pattern <= 185)
		{
			$resp_pattern_interpret = 2;
		}
		$resp_pattern_result = array($resp_pattern, $resp_pattern_interpret);

		#Overall Statistics result

		# AllVal
		$allval = 0;
		if ($q_answered == 176)
		{
			$allval = 1;
		}



		#90Val
		$val_90 = 0;
		$limit = 0.9*176;

		if ($q_answered >= $limit)
		{
			$val_90 = 1;
		}


		$validity_count = 0;
		foreach ($completeness_interpret as $field => $valid)
		{
			if ($valid == 1)
			{
				$validity_count++;
			}
		}
		$stat_result = array($allval, $val_90, $validity_count);


	### DEFINE RESULTS ###
	# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
	$totals =  array_merge($raw_score_totals, $null_counter, $tvalue_scores, $tvalue_percent, $composite_raw, $composite_tval, $composite_mean, $completeness_interpret, $F_index_result, $V_index_result, $L_index_result, $cons_index_result, $resp_pattern_result, $stat_result);
#	$this->module->emDebug("Raw total: " . count($raw_score_totals) . "\nNull Counter: " . count($null_counter) . "\nTvalue: " . count($tvalue_scores) . "\n Tvalp: " . count($tvalue_percent) . "\nComp Raw: " . count($composite_raw) . "\nComp Tval: " . count($composite_tval) . "\nComp Mean: " . count($composite_mean) . "\nComplete: " . count($completeness_interpret) . "\nF: " . count($F_index_result) . "\nV: " . count($V_index_result) . "\nL: " . count($L_index_result) . "\nCons: " . count($cons_index_result) . "\nResp: " . count($resp_pattern_result) . "\nStats: " . count($stat_result));
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
}
else if ($src['basc_srp_formused'] == "01")
{
	$algorithm_summary = "BASC2, Self Report of Personality - Child";
	$this->module->emDebug("Scoring Title: " . $algorithm_summary);


	# REQUIRED: Define an array of default result field_names to record the summary data
	# The BASC2 - PRS-A scoring is as follows:
	#    Accumulated values and Omitted values
	# If any of the subscales is missing more than 2 values, do not scale that subscale.
	# This clinical scales/subscales are: 
	#    Hyperactivity, Aggression, Conduct Problems, Anxiety, Depression, Somalization,
	#    Atypicality, Withdrawal, Attention Problems, Adaptability, Social Skills,
	#    Leadership, Activities of Daily Living, Functional Communication
	# Once the clincal scales are calculated, calculate the composite score for:
	#    Externalizing Problems Composite, Internalizing Problems Composite, 
	#    Behaviorial Symptoms Index and Adaptive Skills Composite
	# These composite scores also have lookup tables for T scores.

	$default_result_fields = array(
		'srpc_ats_raw', 'srpc_att_raw', 'srpc_ata_raw',
		'srpc_loc_raw', 'srpc_sos_raw', 'srpc_anx_raw', 'srpc_dep_raw',
		'srpc_soi_raw', 'srpc_apr_raw', 'srpc_hyp_raw',
		'srpc_rwp_raw', 'srpc_ipr_raw', 'srpc_sfe_raw', 'srpc_sfr_raw',
		'srpc_ats_null', 'srpc_att_null', 'srpc_ata_null',
		'srpc_loc_null', 'srpc_sos_null', 'srpc_anx_null', 'srpc_dep_null',
		'srpc_soi_null', 'srpc_apr_null', 'srpc_hyp_null',
		'srpc_rwp_null', 'srpc_ipr_null', 'srpc_sfe_null', 'srpc_sfr_null',
		'srpc_ats_tval', 'srpc_att_tval', 'srpc_ata_tval',
		'srpc_loc_tval', 'srpc_sos_tval', 'srpc_anx_tval', 'srpc_dep_tval',
		'srpc_soi_tval', 'srpc_apr_tval', 'srpc_hyp_tval',
		'srpc_rwp_tval', 'srpc_ipr_tval', 'srpc_sfe_tval', 'srpc_sfr_tval',
		'srpc_ats_tvalp', 'srpc_att_tvalp',  'srpc_ata_tvalp',
		'srpc_loc_tvalp', 'srpc_sos_tvalp', 'srpc_anx_tvalp', 'srpc_dep_tvalp',
		'srpc_soi_tvalp', 'srpc_apr_tvalp', 'srpc_hyp_tvalp',
		'srpc_rwp_tvalp', 'srpc_ipr_tvalp', 'srpc_sfe_tvalp', 'srpc_sfr_tvalp',
		'srpc_sprob_raw', 'srpc_intprob_raw', 'srpc_inthyp_raw', 'srpc_peradj_raw', 'srpc_emosymp_raw',
		'srpc_sprob_tval', 'srpc_intprob_tval', 'srpc_inthyp_tval', 'srpc_peradj_tval', 'srpc_emosymp_imean',
		'srpc_anx_valid', 'srpc_apr_valid', 'srpc_ata_valid', 'srpc_ats_valid',
		'srpc_att_valid', 'srpc_dep_valid', 'srpc_hyp_valid','srpc_ipr_valid',
		'srpc_loc_valid', 'srpc_rwp_valid', 'srpc_sfe_valid', 'srpc_sfr_valid',
		'srpc_soi_valid', 'srpc_sos_valid', 'srpc_findex', 'srpc_fv', 'srpc_v',
		'srpc_vv', 'srpc_l', 'srpc_lv', 'srpc_con', 'srpc_conv', 'srpc_patt',
		'srpc_pattv', 'srpc_allval', 'srpc_90val', 'srpc_scaletotal'

	);

	# REQUIRED: Define an array of fields that must be present for this algorithm to run
	$required_fields = array();
	foreach (range(1,139) as $i) {
		array_push($required_fields, "basc_srp_c_$i");
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

$source_names = array("ats","att", "ata","loc","sos","anx","dep","soi","apr","hyp","rwp","ipr","sfe", "sfr"); 
	$source_indexes = array (
		'ats' => array(2,31,59,87,115,44,72),
		'att' => array(32,60,88,116,64,92,120),
		'ata' => array(10,39,67,95,123,78,106),
		'loc' => array(1,30,58,6,35,63,91,119),
		'sos' => array(79,107,134,25,54,82,110,137),
		'anx' => array(86,114,8,37,65,93,104,132,55,83,111,138),
		'dep' => array(4,33,61,14,43,71,17,46,74,19,48,76,29),
		'soi' => array(102,130,24,53,81,28,57,85),
		'apr' => array(9,38,66,94,122,47,75,103,131),
		'hyp' => array(90,118,40,68,96,124,99,127),
		'rwp' => array(89,117,100,101,129,133,110,137),
		'ipr' => array(12,41,69,97,125,136),
		'sfe' => array(5,34,62,45,73,20,49,77),
		'sfr' => array(42,70,98,126,23,52,80,108)
	);

	$validity_arr = array(
		'ats' => 0,
		'att' => 0,
		'ata' => 0,
		'loc' => 0,
		'sos' => 0,
		'anx' => 0,
		'dep' => 0,
		'soi' => 0,
		'apr' => 0,
		'hyp' => 0,
		'rwp' => 0,
		'ipr' => 0,
		'sfe' => 0,
		'sfr' => 0
		);

	$validity_key_source = array(
		'anx' => 'srpc_anx_valid',
		'apr' => 'srpc_apr_valid',
		'ata' => 'srpc_ata_valid',
		'ats' => 'srpc_ats_valid',
		'att' => 'srpc_att_valid',
		'dep' => 'srpc_dep_valid',
		'hyp' => 'srpc_hyp_valid',
		'ipr' => 'srpc_ipr_valid',
		'loc' => 'srpc_loc_valid',
		'rwp' => 'srpc_rwp_valid',
		'sfe' => 'srpc_sfe_valid',
		'sfr' => 'srpc_sfr_valid',
		'soi' => 'srpc_soi_valid',
		'sos' => 'srpc_sos_valid'
	);

	$F_index = 0;

	$F_index_values = array(
		109 => 0,
		102	=> 3,
		92	=> 3,
		121	=> 3,
		48	=> 0,
		137	=> 0,
		45	=> 1,
		75	=> 0,
		5	=> 1,
		112	=> 0,
		117	=> 0,
		64	=> 0,
		32	=> 1,
		33	=> 0,
		78	=> 3
	);

	$L_index = 0;

	$L_index_values = array(
		51 => 0,
		11 => 0,
		27 => 1,
		113	=> 0,
		26	=> 0,
		7	=> 1,
		3	=> 0,
		128	=> 3,
		36	=> 0,
		15	=> 0,
		21	=> 0,
		13	=> 0,
		18	=> 0
	);

	$cons_index_values = array(
		17 => 4,
		76 => 19,
		49 => 20,
		43 => 24,
		106 => 56,
		120 => 60,
		91 => 63,
		116 => 64,
		125 => 69,
		115 => 72,
		77 => 73,
		126 => 80,
		135 => 82,
		102 => 85,
		131 => 94,
		136 => 97,
		139 => 111,
		133 => 117,
		138 => 119,
		134 => 123
	);

	$v_index =0;
	$cons_index = 0;
	$resp_pattern = 0;
	$last_response = 0;

#    $this->module->emDebug("This is the original _source array: " . implode(',', $src));

    # These are some fields that need their values flipped before scoring. Instead of going from 0 => 3, they go from 3 => 0
	$new_source = array();
	$flipped_values = array(64,69,73,75,77,87,97,116,125,136);
	$flipped_tf = array(1,2,4,5,6,8,9,10,14,16,17,19,22,23,24,25,28,29,30,31,33,34,35,37,38,39,40,42,43,44,45,46,47,48,50);
	$one_response_tf = array(3,11,13,15,18,21,26,36,51);
	$one_response_flipped_tf = array(7,27);
	$one_response_reg = array(113,128);
	foreach ($required_fields as $i => $req_name) {
		$index = $i+1;
		if (empty($src[$req_name]) or is_null($src[$req_name])) {
            $new_source[$req_name] = null;
		} else {
            $val = $src[$req_name] - 1;

            #		$this->module->emDebug("Index " . $index);
            if ($index == 84 or $index == 105) {
                #			$this->module->emDebug("Val: " . $val . " so v_index was " . $v_index);
                $v_index += $val;
                #			$this->module->emDebug("Now its " . $v_index);
            }
            if ($index == 16 or $index == 22 or $index == 50) {
                if ($val == 1) {
                    #				$this->module->emDebug("Val: " . $val . " so v_index was " . $v_index);
                    $v_index += 2;
                    #				$this->module->emDebug("Now its " . $v_index);
                }
            }

            if (in_array(($index), $flipped_values)) {
                $new_source[$req_name] = ($val != 4) ? 3 - $val : null;
                #		$this->module->emDebug("Flipped values: i=$i, field_name = $req_name, val = $val, new source = $new_source[$req_name]");
            } else if (($index) < 52) {
                if (in_array(($index), $one_response_tf)) {
                    $realval = 0;
                    if (in_array(($index), $one_response_flipped_tf)) {
                        $new_source[$req_name] = ($val != 4) ? 1 - $val : null;
                    } else {
                        $new_source[$req_name] = ($val != 4) ? $val : null;
                    }
                } else if (in_array(($index), $flipped_tf)) {
                    $realval = 0;
                    if ($val == 0) {
                        $realval = 2;
                    } else {
                        $realval = 0;
                    }
                    $new_source[$req_name] = ($val != 4) ? $realval : null;
                } else {
                    $realval = 0;
                    if ($val == 1) {
                        $realval = 2;
                    }
                    $new_source[$req_name] = ($val != 4) ? $realval : null;
                }
            } else {
                if (in_array(($index), $one_response_reg)) {
                    $realval = 0;
                    if (($index) == 128) {
                        if ($val == 3) {
                            $realval = 1;
                        }
                        $new_source[$req_name] = ($val != 4) ? $realval : null;
                    } else if (($index) == 113) {
                        if ($val == 0) {
                            $realval = 1;
                        }
                        $new_source[$req_name] = ($val != 4) ? $realval : null;
                    }
                } else {
                    $new_source[$req_name] = ($val != 4) ? $val : null;
                }
            }

            if (array_key_exists(($index), $F_index_values)) {
                if ($val == $F_index_values[($index)]) {
                    $F_index++;
                }
            }

            if (array_key_exists(($index), $L_index_values)) {
                if ($val == $L_index_values[($index)]) {
                    $L_index++;
                }
            }

            if (array_key_exists(($index), $cons_index_values)) {
                $cons_pair_val = $new_source[$required_fields[$cons_index_values[$index]]];
                if ($cons_pair_val != $new_source[$req_name]) {
                    $cons_index++;
                }
            }

            foreach ($source_indexes as $scale => $qs) {
                if (in_array(($index), $qs)) {
                    $currVal = $validity_arr[$scale];
                    if (!(isset($val) and (strlen($val) <= 0))) {
                        $validity_arr[$scale] = $currVal + 1;
                    }
                }
            }

            if ($index == 1) {
                $last_response = $val;
            } else {
                if ($val != $last_response) {
                    $resp_pattern++;
                }
                $last_response = $val;
            }
        }
	}

	### IMPLEMENT RAW SCORING ###
	# This is the array source
	# Define lists of questions that correspond to each subscale
	# Note: Most questions are scored as N=0, S=1, O=2, A=3 but some questions have reversed scoring
	#    The following scoring should already be reversed from above: 5,14,26,63,65,76,81,91,104,106,107,108,122,146
	
	$mult_factor = array(
		'ats' => 1,
		'att' => 0,
		'ata' => 1,
		'loc' => 1,
		'sos' => 1,
		'anx' => 1,
		'dep' => 0,
		'soi' => 1,
		'apr' => 1,
		'hyp' => 1,
		'rwp' => 2,
		'ipr' => 2,
		'sfe' => 2,
		'sfr' => 2
		);


	$raw_scores = array();
	$null_counter = array();
	foreach ($source_names as $i => $field_name) {
		$raw_scores[$field_name] = 0;
		$null_counter[$field_name] = 0;
	}

#    $this->module->emDebug("This is the new_source array: " . implode(',', $new_source));

	# Add up each array and count how many blank answers there are.
	foreach ($required_fields as $i => $field_name) {
		$val = $new_source[$field_name];
		$index = $i+1;

		foreach ($source_names as $j => $result_name) {
			$target_result = $source_indexes[$result_name];
			if (in_array($index, $target_result)) {
				if (empty($val) and is_null($val)) {
                    $null_counter[$result_name]++;
				} else {
                    $raw_scores[$result_name] += $val;
	//				$this->module->emDebug("null value $val for field_name = $field_name, index = $index, null counter = $null_counter[$result_name]");
				}
			}
		}
	}


	$tvalue = array(
		'ats' => array(
				0 => 37, 1 => 39, 2 => 41, 3 => 44, 4 => 46, 5 => 48,
				6 => 50, 7 => 52, 8 => 54, 9 => 56, 10 => 58,
				11 => 61, 12 => 63, 13 => 65, 14 => 67, 15 => 69,
				16 => 71, 17 => 73, 18 => 75
			),
		'att' => array(
				0 => 39, 1 => 42, 2 => 45, 3 => 47, 4 => 50, 5 => 53,
				6 => 55, 7 => 58, 8 => 61, 9 => 63, 10 => 66,
				11 => 69, 12 => 71, 13 => 74, 14 => 77, 15 => 79,
				16 => 82, 17 => 85, 18 => 87, 19 => 90, 20 => 93
			),
		'ata' => array(
				0 => 37, 1 => 39, 2 => 40, 3 => 42, 4 => 44, 5 => 45,
				6 => 47, 7 => 49, 8 => 51, 9 => 52, 10 => 54,
				11 => 56, 12 => 57, 13 => 59, 14 => 61, 15 => 62,
				16 => 64, 17 => 66, 18 => 68, 19 => 69, 20 => 71,
				21 => 73, 22 => 74, 23 => 76, 24 => 78, 25 => 80
			),
		'loc' => array(
				0 => 36, 1 => 38, 2 => 40, 3 => 42, 4 => 44, 5 => 46,
				6 => 48, 7 => 51, 8 => 53, 9 => 55, 10 => 57,
				11 => 59, 12 => 61, 13 => 63, 14 => 65, 15 => 67,
				16 => 69, 17 => 71, 18 => 74, 19 => 76, 20 => 78
			),
		'sos' => array(
				0 => 36, 1 => 38, 2 => 40, 3 => 42, 4 => 44, 5 => 46, 
				6 => 48, 7 => 50, 8 => 52, 9 => 54, 10 => 56,
				11 => 58, 12 => 60, 13 => 62, 14 => 64, 15 => 66,
				16 => 68, 17 => 70, 18 => 72, 19 => 74, 20 => 76,
				21 => 78, 22 => 80, 23 => 82
			),
		'anx' => array(
				0 => 33, 1 => 34, 2 => 36, 3 => 37, 4 => 38, 5 => 40,
				6 => 41, 7 => 43, 8 => 44, 9 => 45, 10 => 47,
				11 => 48, 12 => 50, 13 => 51, 14 => 52, 15 => 54,
				16 => 55, 17 => 57, 18 => 58, 19 => 59, 20 => 61,
				21 => 62, 22 => 64, 23 => 65, 24 => 66, 25 => 68,
				26 => 69, 27 => 71, 28 => 72, 29 => 73, 30 => 75,
				31 => 76, 32 => 77, 33 => 79, 34 => 80, 35 => 82,
				36 => 83, 37 => 84
			),
		'dep' => array(
				0 => 38, 1 => 39, 2 => 41, 3 => 42, 4 => 44, 5 => 45,
				6 => 47, 7 => 48, 8 => 50, 9 => 51, 10 => 53, 
				11 => 55, 12 => 56, 13 => 58, 14 => 59, 15 => 61,
				16 => 62, 17 => 64, 18 => 65, 19 => 67, 20 => 68,
				21 => 70, 22 => 71, 23 => 73, 24 => 74, 25 => 76,
				26 => 77, 27 => 79, 28 => 80, 29 => 82, 30 => 83
			),
		'soi' => array(
				0 => 35, 1 => 37, 2 => 39, 3 => 42, 4 => 44, 5 => 46,
				6 => 48, 7 => 51, 8 => 53, 9 => 55, 10 => 57,
				11 => 59, 12 => 62, 13 => 64, 14 => 66, 15 => 68,
				16 => 71, 17 => 73, 18 => 75, 19 => 77, 20 => 79,
				21 => 82, 22 => 84
			),
		'apr' => array(
				0 => 33, 1 => 35, 2 => 37, 3 => 39, 4 => 41, 5 => 43,
				6 => 45, 7 => 47, 8 => 49, 9 => 51, 10 => 53,
				11 => 55, 12 => 57, 13 => 59, 14 => 61, 15 => 64,
				16 => 66, 17 => 68, 18 => 70, 19 => 72, 20 => 74,
				21 => 76, 22 => 78, 23 => 80, 24 => 82
			),
		'hyp' => array(
				0 => 34, 1 => 36, 2 => 38, 3 => 40, 4 => 42, 5 => 44,
				6 => 46, 7 => 48, 8 => 50, 9 => 52, 10 => 54,
				11 => 56, 12 => 58, 13 => 60, 14 => 62, 15 => 64,
				16 => 66, 17 => 68, 18 => 70, 19 => 72, 20 => 74,
				21 => 76, 22 => 78, 23 => 80
			),
		'rwp' => array(
				0 => 13, 1 => 15, 2 => 16, 3 => 18, 4 => 20, 5 => 22,
				6 => 24, 7 => 26, 8 => 28, 9 => 30, 10 => 31,
				11 => 33, 12 => 35, 13 => 37, 14 => 39, 15 => 41,
				16 => 43, 17 => 45, 18 => 46, 19 => 48, 20 => 50,
				21 => 52, 22 => 52, 23 => 56, 24 => 58, 25 => 59,
				26 => 61, 27 => 63
			),
		'ipr' => array(
				0 => 19, 1 => 22, 2 => 25, 3 => 27, 4 => 30, 5 => 32,
				6 => 35, 7 => 37, 8 => 40, 9 => 42, 10 => 45,
				11 => 48, 12 => 50, 13 => 53, 14 => 55, 15 => 58,
				16 => 60
			),
		'sfe' => array(
				0 => 11, 1 => 13, 2 => 16, 3 => 18, 4 => 21, 5 => 23,
				6 => 26, 7 => 28, 8 => 31, 9 => 34, 10 => 36,
				11 => 39, 12 => 41, 13 => 44, 14 => 46, 15 => 49,
				16 => 51, 17 => 54, 18 => 57, 19 => 59
			),
		'sfr' => array(
				0 => 17, 1 => 19, 2 => 21, 3 => 24, 4 => 26, 5 => 28,
				6 => 31, 7 => 33, 8 => 35, 9 => 38, 10 => 40,
				11 => 42, 12 => 45, 13 => 47, 14 => 49, 15 => 52,
				16 => 54, 17 => 56, 18 => 59, 19 => 61, 20 => 63,
				21 => 66, 22 => 68
			)
		);

	$tvalue_perc = array(
		'ats' => array(
				0 => 5, 1 => 14, 2 => 23, 3 => 33, 4 => 41, 5 => 49,
				6 => 56, 7 => 63, 8 => 69, 9 => 74, 10 => 79,
				11 => 83, 12 => 87, 13 => 90, 14 => 92, 15 => 95,
				16 => 97, 17 => 98, 18 => 99
			),
		'att' => array(
				0 => 9, 1 => 23, 2 => 37, 3 => 50, 4 => 60, 5 => 68,
				6 => 75, 7 => 81, 8 => 85, 9 => 89, 10 => 92,
				11 => 94, 12 => 96, 13 => 97, 14 => 98, 15 => 99,
				16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99
			),
		'ata' => array(
				0 => 7, 1 => 12, 2 => 18, 3 => 25, 4 => 32, 5 => 38,
				6 => 45, 7 => 51, 8 => 57, 9 => 62, 10 => 68,
				11 => 72, 12 => 76, 13 => 80, 14 => 84, 15 => 87,
				16 => 90, 17 => 92, 18 => 94, 19 => 96, 20 => 97,
				21 => 98, 22 => 99, 23 => 99, 24 => 99, 25 => 99
			),
		'loc' => array(
				0 => 3, 1 => 9, 2 => 17, 3 => 26, 4 => 34, 5 => 43,
				6 => 51, 7 => 58, 8 => 64, 9 => 70, 10 => 76,
				11 => 80, 12 => 84, 13 => 88, 14 => 91, 15 => 93,
				16 => 95, 17 => 97, 18 => 98, 19 => 99, 20 => 99
			),
		'sos' => array(
				0 => 3, 1 => 8, 2 => 15, 3 => 22, 4 => 31, 5 => 39, 
				6 => 47, 7 => 55, 8 => 62, 9 => 68, 10 => 73,
				11 => 78, 12 => 83, 13 => 86, 14 => 89, 15 => 92,
				16 => 94, 17 => 96, 18 => 97, 19 => 98, 20 => 99,
				21 => 99, 22 => 99, 23 => 99
			),
		'anx' => array(
				0 => 2, 1 => 4, 2 => 6, 3 => 9, 4 => 12, 5 => 16,
				6 => 21, 7 => 26, 8 => 31, 9 => 36, 10 => 41,
				11 => 47, 12 => 52, 13 => 57, 14 => 62, 15 => 67,
				16 => 71, 17 => 75, 18 => 79, 19 => 82, 20 => 85,
				21 => 87, 22 => 90, 23 => 92, 24 => 93, 25 => 95,
				26 => 96, 27 => 97, 28 => 98, 29 => 98, 30 => 99,
				31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99,
				36 => 99, 37 => 99
			),
		'dep' => array(
				0 => 4, 1 => 11, 2 => 19, 3 => 27, 4 => 34, 5 => 41,
				6 => 47, 7 => 53, 8 => 59, 9 => 63, 10 => 68,
				11 => 72, 12 => 75, 13 => 78, 14 => 81, 15 => 84, 
				16 => 86, 17 => 88, 18 => 90, 19 => 92, 20 => 93,
				21 => 95, 22 => 96, 23 => 97, 24 => 98, 25 => 98,
				26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99
			),
		'soi' => array(
				0 => 2, 1 => 6, 2 => 13, 3 => 22, 4 => 31, 5 => 41,
				6 => 50, 7 => 58, 8 => 66, 9 => 73, 10 => 78,
				11 => 83, 12 => 87, 13 => 90, 14 => 93, 15 => 95,
				16 => 96, 17 => 97, 18 => 98, 19 => 99, 20 => 99,
				21 => 99, 22 => 99
			),
		'apr' => array(
				0 => 2, 1 => 4, 2 => 8, 3 => 14, 4 => 20, 5 => 27,
				6 => 35, 7 => 43, 8 => 50, 9 => 58, 10 => 65,
				11 => 71, 12 => 77, 13 => 82, 14 => 86, 15 => 89,
				16 => 92, 17 => 95, 18 => 96, 19 => 98, 20 => 99,
				21 => 99, 22 => 99, 23 => 99, 24 => 99
			),
		'hyp' => array(
				0 => 2, 1 => 5, 2 => 10, 3 => 16, 4 => 23, 5 => 30,
				6 => 38, 7 => 46, 8 => 53, 9 => 60, 10 => 67,
				11 => 73, 12 => 78, 13 => 83, 14 => 87, 15 => 90,
				16 => 93, 17 => 95, 18 => 97, 19 => 98, 20 => 99,
				21 => 99, 22 => 99, 23 => 99
			),
		'rwp' => array(
				0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
				6 => 1, 7 => 2, 8 => 3, 9 => 4, 10 => 6,
				11 => 7, 12 => 10, 13 => 12, 14 => 15, 15 => 18,
				16 => 22, 17 => 27, 18 => 32, 19 => 37, 20 => 44,
				21 => 50, 22 => 58, 23 => 66, 24 => 74, 25 => 82,
				26 => 90, 27 => 96
			),
		'ipr' => array(
				0 => 1, 1 => 1, 2 => 2, 3 => 4, 4 => 5, 5 => 8,
				6 => 10, 7 => 13, 8 => 16, 9 => 21, 10 => 26,
				11 => 32, 12 => 39, 13 => 48, 14 => 60, 15 => 74,
				16 => 92
			),
		'sfe' => array(
				0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3,
				6 => 4, 7 => 5, 8 => 6, 9 => 8, 10 => 11, 
				11 => 14, 12 => 17, 13 => 21, 14 => 27, 15 => 33,
				16 => 42, 17 => 54, 18 => 69, 19 => 89
			),
		'sfr' => array(
				0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
				6 => 4, 7 => 6, 8 => 8, 9 => 12, 10 => 16,
				11 => 22, 12 => 28, 13 => 36, 14 => 44, 15 => 53,
				16 => 62, 17 => 70, 18 => 79, 19 => 86, 20 => 92,
				21 => 96, 22 => 98
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
	$q_answered = 139;

	foreach ($source_names as $i => $result_name) {
		$raw_score_val = $raw_scores[$result_name];
		$null_vals = $null_counter[$result_name];

		$q_answered -= $null_vals;
	# Add in the null contributions to calculate the total raw score
		# Changing this from 2 nulls to any nulls per Robert Borah
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
	$composite_names = array('sprob', 'intprob', 'inthyp', 'peradj', 'emosymp');
	$composite_calc = array(
			'sprob' => array('ats', 'att'),
			'intprob' => array('ata', 'loc', 'sos', 'anx', 'dep', 'soi'),
			'inthyp' => array('apr', 'hyp'),
			'peradj' => array('rwp', 'ipr', 'sfe', 'sfr'),
			'emosymp' => array('sos', 'anx', 'dep', 'soi', 'sfe', 'sfr')
				);


	$composite_comb_tvalues = array(
			'sprob' => array( 
				36 => array('min' => 76, 'max' => 76), 37 => array('min' => 77, 'max' => 78), 38 => array('min' => 79, 'max' => 79),
				39 => array('min' => 80, 'max' => 81), 40 => array('min' => 82, 'max' => 83), 41 => array('min' => 84, 'max' => 85),
				42 => array('min' => 86, 'max' => 86), 43 => array('min' => 87, 'max' => 88), 44 => array('min' => 89, 'max' => 90), 
				45 => array('min' => 91, 'max' => 92), 46 => array('min' => 93, 'max' => 93), 47 => array('min' => 94, 'max' => 95),
				48 => array('min' => 96, 'max' => 97), 49 => array('min' => 98, 'max' => 99), 50 => array('min' => 100, 'max' => 100),
				51 => array('min' => 101, 'max' => 102), 52 => array('min' => 103, 'max' => 104), 53 => array('min' => 105, 'max' => 106),
				54 => array('min' => 107, 'max' => 107), 55 => array('min' => 108, 'max' => 109), 56 => array('min' => 110, 'max' => 111),
				57 => array('min' => 112, 'max' => 113), 58 => array('min' => 114, 'max' => 114), 59 => array('min' => 115, 'max' => 116), 
				60 => array('min' => 117, 'max' => 118), 61 => array('min' => 119, 'max' => 120), 62 => array('min' => 121, 'max' => 121), 
				63 => array('min' => 122, 'max' => 123), 64 => array('min' => 124, 'max' => 125), 65 => array('min' => 126, 'max' => 127),
				66 => array('min' => 128, 'max' => 129), 67  => array('min' => 130, 'max' => 130), 68 => array('min' => 131, 'max' => 132),
				69 => array('min' => 133, 'max' => 134), 70 => array('min' => 135, 'max' => 136), 71 => array('min' => 137, 'max' => 137),
				72 => array('min' =>  138, 'max' => 139), 73 => array('min' => 140, 'max' => 141), 74 => array('min' => 142, 'max' => 143),
				75 => array('min' => 144, 'max' => 144), 76 => array('min' => 145, 'max' => 146), 77 => array('min' => 147, 'max' => 148),
				78 => array('min' => 149, 'max' => 150), 79 => array('min' => 151, 'max' => 151), 80 => array('min' => 152, 'max' => 153),
				81 => array('min' => 154, 'max' => 155), 82 => array('min' => 156, 'max' => 157), 83 => array('min' => 158, 'max' => 158),
				84 => array('min' => 159, 'max' => 160), 85 => array('min' => 161, 'max' => 162), 86 => array('min' => 163, 'max' => 164),
				87 => array('min' => 165, 'max' => 165), 88 => array('min' => 166, 'max' => 167), 89 => array('min' => 168, 'max' => 168)
				),
			'intprob' => array(
				33 => array('min' => 215, 'max' => 217), 34 => array('min' => 218, 'max' => 222), 35 => array('min' => 223, 'max' => 227), 
				36 => array('min' => 228, 'max' => 232), 37 => array('min' => 233, 'max' => 237), 38 => array('min' => 238, 'max' => 242),
				39 => array('min' => 243, 'max' => 247), 40 => array('min' => 248, 'max' => 252), 41 => array('min' => 253, 'max' => 257), 
				42 => array('min' => 258, 'max' => 262), 43 => array('min' => 263, 'max' => 267), 44 => array('min' => 268, 'max' => 272),
				45 => array('min' => 273, 'max' => 277), 46 => array('min' => 278, 'max' => 282), 47 => array('min' => 283, 'max' => 287),
				48 => array('min' => 288, 'max' => 292), 49 => array('min' => 293, 'max' => 297), 50 => array('min' => 298, 'max' => 302),
				51 => array('min' => 303, 'max' => 307), 52 => array('min' => 308, 'max' => 312), 53 => array('min' => 313, 'max' => 317),
				54 => array('min' => 318, 'max' => 322), 55 => array('min' => 323, 'max' => 327), 56 => array('min' => 328, 'max' => 332),
				57 => array('min' => 333, 'max' => 337), 58 => array('min' => 338, 'max' => 342), 59 => array('min' => 343, 'max' => 347),
				60 => array('min' => 348, 'max' => 352), 61 => array('min' => 353, 'max' => 357), 62 => array('min' => 358, 'max' => 362),
				63 => array('min' => 363, 'max' => 367), 64 => array('min' => 368, 'max' => 372), 65 => array('min' => 373, 'max' => 377),
				66 => array('min' => 378, 'max' => 382), 67 => array('min' => 382, 'max' => 387), 68 => array('min' => 388, 'max' => 392),
				69 => array('min' => 393, 'max' => 397), 70 => array('min' => 398, 'max' => 402), 71 => array('min' => 403, 'max' => 407), 
				72 => array('min' => 408, 'max' => 412), 73 => array('min' => 413, 'max' => 417), 74 => array('min' => 418, 'max' => 422),
				75 => array('min' => 423, 'max' => 427), 76 => array('min' => 428, 'max' => 432), 77 => array('min' => 433, 'max' => 437),
				78 => array('min' => 438, 'max' => 442), 79 => array('min' => 443, 'max' => 447), 80 => array('min' => 448, 'max' => 452),
				81 => array('min' => 453, 'max' => 457), 82 => array('min' => 458, 'max' => 462), 83 => array('min' => 463, 'max' => 467),
				84 => array('min' => 468, 'max' => 472), 85 => array('min' => 473, 'max' => 477), 86 => array('min' => 478, 'max' => 482),
				87 => array('min' => 483, 'max' => 487), 88 => array('min' => 488, 'max' => 491)
				),
			'inthyp' => array(
				32 => array('min' => 67, 'max' => 67), 33 => array('min' => 68, 'max' => 69), 34 => array('min' => 70, 'max' => 71),
				35 => array('min' => 72, 'max' => 73), 36 => array('min' => 74, 'max' => 75), 37 => array('min' => 76, 'max' => 77),
				38 => array('min' => 78, 'max' => 78), 39 => array('min' => 79, 'max' => 80), 40 => array('min' => 81, 'max' => 82),
				41 => array('min' => 83, 'max' => 84), 42 => array('min' => 85, 'max' => 86), 43 => array('min' => 87, 'max' => 88),
				44 => array('min' => 89, 'max' => 89), 45 => array('min' => 90, 'max' => 91), 46 => array('min' => 92, 'max' => 93),
				47 => array('min' => 94, 'max' => 95), 48 => array('min' => 96, 'max' => 97), 49 => array('min' => 98, 'max' => 99),
				50 => array('min' => 100, 'max' => 100), 51 => array('min' => 101, 'max' => 102), 52 => array('min' => 103, 'max' => 104),
				53 => array('min' => 105, 'max' => 106), 54 => array('min' => 107, 'max' => 108), 55 => array('min' => 109, 'max' => 110), 
				56 => array('min' => 111, 'max' => 111), 57 => array('min' => 112, 'max' => 113), 58 => array('min' => 114, 'max' => 115),
				59 => array('min' => 116, 'max' => 117), 60 => array('min' => 118, 'max' => 119), 61 => array('min' => 120, 'max' => 121),
				62 => array('min' => 122, 'max' => 122), 63 => array('min' => 123, 'max' => 124), 64 => array('min' => 125, 'max' => 126),
				65 => array('min' => 127, 'max' => 128), 66 => array('min' => 129, 'max' => 130), 67 => array('min' => 131, 'max' => 132),
				68 => array('min' => 133, 'max' => 133), 69 => array('min' => 134, 'max' => 135), 70 => array('min' => 136, 'max' => 137), 
				71 => array('min' => 138, 'max' => 139), 72 => array('min' => 140, 'max' => 141), 73 => array('min' => 142, 'max' => 143),
				74 => array('min' => 144, 'max' => 145), 75 => array('min' => 146, 'max' => 146), 76 => array('min' => 147, 'max' => 148),
				77 => array('min' => 149, 'max' => 150), 78 => array('min' => 151, 'max' => 152), 79 => array('min' => 153, 'max' => 154),
				80 => array('min' => 155, 'max' => 156), 81 => array('min' => 157, 'max' => 157), 82 => array('min' => 158, 'max' => 159),
				83 => array('min' => 160, 'max' => 161), 84 => array('min' => 162, 'max' => 162)
				),
			'peradj' => array(
				10 => array('min' => 60, 'max' => 79), 11 => array('min' => 80, 'max' => 83), 12 => array('min' => 84, 'max' => 86),
				13 => array('min' => 87, 'max' => 89), 14 => array('min' => 90, 'max' => 92), 15 => array('min' => 93, 'max' => 95),
				16 => array('min' => 96, 'max' => 98), 17 => array('min' => 99, 'max' => 101), 18 => array('min' => 102, 'max' => 104),
				19 => array('min' => 105, 'max' => 107), 20 => array('min' => 108, 'max' => 110), 21 => array('min' => 111, 'max' => 113),
				22 => array('min' => 114, 'max' => 116), 23 => array('min' => 117, 'max' => 119), 24 => array('min' => 120, 'max' => 122),
				25 => array('min' => 123, 'max' => 125), 26 => array('min' => 126, 'max' => 128), 27 => array('min' => 129, 'max' => 131),
				28 => array('min' => 132, 'max' => 134), 29 => array('min' => 135, 'max' => 137), 30 => array('min' => 138, 'max' => 140), 
				31 => array('min' => 141, 'max' => 143), 32 => array('min' => 144, 'max' => 146), 33 => array('min' => 147, 'max' => 149),
				34 => array('min' => 150, 'max' => 152), 35 => array('min' => 153, 'max' => 155), 36 => array('min' => 156, 'max' => 158),
				37 => array('min' => 159, 'max' => 162), 38 => array('min' => 163, 'max' => 165), 39 => array('min' => 166, 'max' => 168),
				40 => array('min' => 169, 'max' => 171), 41 => array('min' => 172, 'max' => 174), 42 => array('min' => 175, 'max' => 177),
				43 => array('min' => 178, 'max' => 180), 44 => array('min' => 181, 'max' => 183), 45 => array('min' => 184, 'max' => 186),
				46 => array('min' => 187, 'max' => 189), 47 => array('min' => 190, 'max' => 192), 48 => array('min' => 193, 'max' => 195),
				49 => array('min' => 196, 'max' => 198), 50 => array('min' => 199, 'max' => 201), 51 => array('min' => 202, 'max' => 204),
				52 => array('min' => 205, 'max' => 207), 53 => array('min' => 208, 'max' => 210), 54 => array('min' => 211, 'max' => 213),
				55 => array('min' => 214, 'max' => 216), 56 => array('min' => 217, 'max' => 219), 57 => array('min' => 220, 'max' => 222),
				58 => array('min' => 223, 'max' => 225), 59 => array('min' => 226, 'max' => 228), 60 => array('min' => 229, 'max' => 231),
				61 => array('min' => 232, 'max' => 234), 62 => array('min' => 235, 'max' => 238), 63 => array('min' => 239, 'max' => 241),
				64 => array('min' => 242, 'max' => 244), 65 => array('min' => 245, 'max' => 247), 66 => array('min' => 248, 'max' => 250)
				),
			'emosymp' => array(
				22 => array('min' => 170, 'max' => 172), 23 => array('min' => 173, 'max' => 177), 24 => array('min' => 178, 'max' => 182),
				25 => array('min' => 183, 'max' => 186), 26 => array('min' => 187, 'max' => 191), 27 => array('min' => 192, 'max' => 196),
				28 => array('min' => 197, 'max' => 200), 29 => array('min' => 201, 'max' => 205), 30 => array('min' => 206, 'max' => 209),
				31 => array('min' => 210, 'max' => 214), 32 => array('min' => 215, 'max' => 219), 33 => array('min' => 220, 'max' => 223),
				34 => array('min' => 224, 'max' => 228), 35 => array('min' => 229, 'max' => 232), 36 => array('min' => 233, 'max' => 237),
				37 => array('min' => 238, 'max' => 242), 38 => array('min' => 243, 'max' => 246), 39 => array('min' => 247, 'max' => 251),
				40 => array('min' => 252, 'max' => 256), 41 => array('min' => 257, 'max' => 260), 42 => array('min' => 261, 'max' => 265),
				43 => array('min' => 266, 'max' => 269), 44 => array('min' => 270, 'max' => 274), 45 => array('min' => 275, 'max' => 279),
				46 => array('min' => 280, 'max' => 283), 47 => array('min' => 284, 'max' => 288), 48 => array('min' => 289, 'max' => 293),
				49 => array('min' => 294, 'max' => 297), 50 => array('min' => 298, 'max' => 302), 51 => array('min' => 303, 'max' => 306),
				52 => array('min' => 307, 'max' => 311), 53 => array('min' => 312, 'max' => 316), 54 => array('min' => 317, 'max' => 320),
				55 => array('min' => 321, 'max' => 325), 56 => array('min' => 326, 'max' => 330), 57 => array('min' => 331, 'max' => 334),
				58 => array('min' => 335, 'max' => 339), 59 => array('min' => 340, 'max' => 343), 60 => array('min' => 344, 'max' => 348),
				61 => array('min' => 349, 'max' => 353), 62 => array('min' => 354, 'max' => 357), 63 => array('min' => 358, 'max' => 362),
				64 => array('min' => 363, 'max' => 366), 65 => array('min' => 367, 'max' => 371), 66 => array('min' => 372, 'max' => 376),
				67 => array('min' => 377, 'max' => 380), 68 => array('min' => 381, 'max' => 385), 69 => array('min' => 386, 'max' => 390), 
				70 => array('min' => 391, 'max' => 394), 71 => array('min' => 395, 'max' => 399), 72 => array('min' => 400, 'max' => 403),
				73 => array('min' => 404, 'max' => 408), 74 => array('min' => 409, 'max' => 413), 75 => array('min' => 414, 'max' => 417), 
				76 => array('min' => 418, 'max' => 422), 77 => array('min' => 423, 'max' => 427), 78 => array('min' => 428, 'max' => 431),
				79 => array('min' => 432, 'max' => 436), 80 => array('min' => 437, 'max' => 440), 81 => array('min' => 441, 'max' => 445),
				82 => array('min' => 446, 'max' => 450), 83 => array('min' => 451, 'max' => 454), 84 => array('min' => 455, 'max' => 459),
				85 => array('min' => 460, 'max' => 464), 86 => array('min' => 465, 'max' => 468), 87 => array('min' => 469, 'max' => 473),
				88 => array('min' => 474, 'max' => 477), 89 => array('min' => 478, 'max' => 482), 90 => array('min' => 483, 'max' => 487),
				91 => array('min' => 488, 'max' => 491), 92 => array('min' => 492, 'max' => 496), 93 => array('min' => 497, 'max' => 501),
				94 => array('min' => 502, 'max' => 505)
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
			$result += $tvalue_scores[$name];
		}

		$result_tval = lookup_composite_tvalue($composite_comb_tvalues[$field_names], $result); 
		$composite_raw[$field_names . '_comp'] = $result;
		if ($field_names != 'emosymp')
		{
			$composite_tval[$field_names . '_comp_tval'] = $result_tval;
		}
		if ($field_names == 'emosymp') {
			$num_items = count($composite_calc[$field_names]);
			$composite_mean[$field_names . '_comp_mean'] = 100-$result/$num_items;
		}
	}

### INDEXES ###

$completeness_interpret = array();
	foreach ($validity_key_source as $scale => $field_name)
	{
		$null_count = $null_counter[$scale];
		if ($null_count > 2)
		{
			$completeness_interpret[$field_name] = 0;
		}
		else
		{
			$completeness_interpret[$field_name] = 1;
		}
	}

	# F_index result
	$F_interpret = 1;
	if ($F_index >= 4 and $F_index <= 6)
	{
		$F_interpret = 2;
	}
	else if ($F_index >= 7 and $F_index <= 15)
	{
		$F_interpret = 3;
	}
	$F_index_result = array($F_index, $F_interpret);


	# L_index result
	$L_interpret = 1;
	if ($L_index >= 10 and $L_index <= 12)
	{
		$L_interpret = 2;
	}
	else if ($L_index == 13)
	{
		$L_interpret = 3;
	}
	$L_index_result = array($L_index, $L_interpret);

	#V_Index result
	$V_interpret = 1;
	if ($v_index >= 3 and $v_index <= 4)
	{
		$V_interpret = 2;
	}
	else if ($v_index >= 5 and $v_index <= 12)
	{
		$V_interpret = 3;
	}
	$V_index_result = array($v_index, $V_interpret);

	#Consistency Index result
	$cons_interpret = 1;
	if ($cons_index >= 17 and $cons_index <= 25)
	{
		$cons_interpret = 2;
	}
	else if ($cons_index >= 26)
	{
		$cons_interpret = 3;
	}
	$cons_index_result = array($cons_index, $cons_interpret);


	# Response Pattern result
	$resp_pattern_interpret = 0;
	if ($resp_pattern >= 51 and $resp_pattern <= 101)
	{
		$resp_pattern_interpret = 1;
	}
	else if ($resp_pattern >= 102 and $resp_pattern <= 139)
	{
		$resp_pattern_interpret = 2;
	}
	$resp_pattern_result = array($resp_pattern, $resp_pattern_interpret);

	#Overall Statistics result

	# AllVal
	$allval = 0;
	if ($q_answered == 139)
	{
		$allval = 1;
	}



	#90Val
	$val_90 = 0;
	$limit = 0.9*139;

	if ($q_answered >= $limit)
	{
		$val_90 = 1;
	}


	$validity_count = 0;
	foreach ($completeness_interpret as $field => $valid)
	{
		if ($valid == 1)
		{
			$validity_count++;
		}
	}
	$stat_result = array($allval, $val_90, $validity_count);


### DEFINE RESULTS ###
# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$totals =  array_merge($raw_score_totals, $null_counter, $tvalue_scores, $tvalue_percent, $composite_raw, $composite_tval, $composite_mean, $completeness_interpret, $F_index_result, $V_index_result, $L_index_result, $cons_index_result, $resp_pattern_result, $stat_result);
#$this->module->emDebug("Raw total: " . count($raw_score_totals) . "\nNull Counter: " . count($null_counter) . "\nTvalue: " . count($tvalue_scores) . "\n Tvalp: " . count($tvalue_percent) . "\nComp Raw: " . count($composite_raw) . "\nComp Tval: " . count($composite_tval) . "\nComp Mean: " . count($composite_mean) . "\nComplete: " . count($completeness_interpret) . "\nF: " . count($F_index_result) . "\nV: " . count($V_index_result) . "\nL: " . count($L_index_result) . "\nCons: " . count($cons_index_result) . "\nResp: " . count($resp_pattern_result) . "\nStats: " . count($stat_result));
#$this->module->emDebug("Default: " . count($default_result_fields) . "Mine: " . count($totals));
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

}

return true;

?>
