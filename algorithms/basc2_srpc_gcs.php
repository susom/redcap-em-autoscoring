<?php
/**

    BASC2 - Self-Report of Personality- Child
    
    A REDCap AutoScoring Algorithm File

    Developed by Kim Wijaya and Alex Basile for ELSPAP June 2016
    Uses General Combined Sex Scales Ages 8-11
    See BASC2 Manual for Scale Details  
    
    - There exists an array called $src that contains the data from the source project
    - There can exist an optional array called $manual_result_fields that can override the default_result_fields
    - The final results should be presented in an array called $algorithm_results
        - The answers are categorized as:
              0 = Never
              1 = Sometimes
              2 = Often
              3 = Almost Always
        - There are 140 questions that the participant fills out.

**/

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
        'srpc_loc_raw', 'srpc_sos_raw', 'srpc_anx_raw', 
        'srpc_dep_raw', 'srpc_soi_raw', 'srpc_apr_raw', 
        'srpc_hyp_raw', 'srpc_rwp_raw', 'srpc_ipr_raw', 
        'srpc_sfe_raw', 'srpc_sfr_raw',
        'srpc_ats_null', 'srpc_att_null', 'srpc_ata_null',
        'srpc_loc_null', 'srpc_sos_null', 'srpc_anx_null', 
        'srpc_dep_null', 'srpc_soi_null', 'srpc_apr_null', 
        'srpc_hyp_null', 'srpc_rwp_null', 'srpc_ipr_null', 'srpc_sfe_null', 'srpc_sfr_null',
        'srpc_ats_tval', 'srpc_att_tval', 'srpc_ata_tval',
        'srpc_loc_tval', 'srpc_sos_tval', 'srpc_anx_tval',
        'srpc_dep_tval', 'srpc_soi_tval', 'srpc_apr_tval', 
        'srpc_hyp_tval', 'srpc_rwp_tval', 'srpc_ipr_tval', 'srpc_sfe_tval', 'srpc_sfr_tval',
        'srpc_ats_tvalp', 'srpc_att_tvalp',  'srpc_ata_tvalp',
        'srpc_loc_tvalp', 'srpc_sos_tvalp', 'srpc_anx_tvalp', 'srpc_dep_tvalp',
        'srpc_soi_tvalp', 'srpc_apr_tvalp', 'srpc_hyp_tvalp',
        'srpc_rwp_tvalp', 'srpc_ipr_tvalp', 'srpc_sfe_tvalp', 'srpc_sfr_tvalp',
        'srpc_sprob_raw', 'srpc_intprob_raw', 'srpc_inthyp_raw', 'srpc_peradj_raw', 'srpc_emosymp_raw',
        'srpc_sprob_tval', 'srpc_intprob_tval', 'srpc_inthyp_tval', 'srpc_peradj_tval', 'srpc_emosymp_tval', 
        'srpc_emosymp_imean',
        'srpc_anx_valid', 'srpc_apr_valid', 'srpc_ata_valid', 'srpc_ats_valid',
        'srpc_att_valid', 'srpc_dep_valid', 'srpc_hyp_valid','srpc_ipr_valid',
        'srpc_loc_valid', 'srpc_rwp_valid', 'srpc_sfe_valid', 'srpc_sfr_valid',
        'srpc_soi_valid', 'srpc_sos_valid', 
        'srpc_findex', 'srpc_fv', 
        'srpc_v', 'srpc_vv', 
        'srpc_l', 'srpc_lv', 
        'srpc_con', 'srpc_conv', 
        'srpc_patt','srpc_pattv', 
        'srpc_allval', 'srpc_90val', 'srpc_scaletotal',
        'srpc_ats_sig', 'srpc_att_sig', 'srpc_ata_sig',
        'srpc_loc_sig', 'srpc_sos_sig', 'srpc_anx_sig', 
        'srpc_dep_sig', 'srpc_soi_sig', 'srpc_apr_sig', 
        'srpc_hyp_sig', 'srpc_rwp_sig', 'srpc_ipr_sig', 
        'srpc_sfe_sig', 'srpc_sfr_sig',
        'srpc_sprob_sig', 'srpc_intprob_sig', 'srpc_inthyp_sig', 'srpc_peradj_sig', 'srpc_emosymp_sig', 
    );

    # REQUIRED: Define an array of fields that must be present for this algorithm to run
    $required_fields = array();
    foreach (range(1,139) as $i) {
        array_push($required_fields, "basc_srp_c_q$i");
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
                if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
                    $default_result_fields[$k] = $field;
                    $this->module->emDebug("Changing result field ".$k." to ". $field);
                }
            }
            $log[] = "Overriding default result field names with ". implode(',',$manual_result_fields);
        } else {
            $msg = count($manual_result_fields) . " manual result fields specified, but the algorithm needs " . count($default_result_fields) . " fields.";
            $this->module->emError($msg);
            $algorithm_log[] = $msg;
    #       return false;
        }
    }

    # Test for presense of all required fields and report missing fields
    $source_fields = array_keys($src);
    $missing_fields = array_diff($required_fields, $source_fields);
    if ($missing_fields) {
        $msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "]";
        $algorithm_log[] = $msg;
        $this->module->emError($msg);
        $this->module->emDebug("Missing fields: " . $missing_fields);
    #   return false; //Since this is being called via include, the main script will continue to process other algorithms
    }

    $source_indexes = array (
        'ats' => array(2,31,59,87,115,44,72),
        'att' => array(32,60,88,116,64,92,120),
        'ata' => array(10,39,67,95,123,78,106, 134),
        'loc' => array(1,30,58,6,35,63,91,119),
        'sos' => array(79,107,135,25,54,82,110,138),
        'anx' => array(86,114,8,37,65,93,121,104,132,55,83,111,139),
        'dep' => array(4,33,61,14,43,71,17,46,74,19,48,76,29),
        'soi' => array(102,130,24,53,81,28,57,85),
        'apr' => array(9,38,66,94,122,47,75,103,131),
        'hyp' => array(90,118,40,68,96,124,99,127),
        'rwp' => array(89,117,100,101,129,133,109,137,112),
        'ipr' => array(12,41,69,97,125,136),
        'sfe' => array(5,34,62,45,73,20,49,77),
        'sfr' => array(42,70,98,126,23,52,80,108)
    );
    $source_names = array_keys($source_indexes); //("ats","att", "ata","loc","sos","anx","dep","soi","apr","hyp","rwp","ipr","sfe", "sfr"); 
    
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
        'ats' => 'srpc_ats_valid',
        'att' => 'srpc_att_valid',
        'ata' => 'srpc_ata_valid',
        'loc' => 'srpc_loc_valid',
        'sos' => 'srpc_sos_valid',
        'anx' => 'srpc_anx_valid',
        'dep' => 'srpc_dep_valid',
        'soi' => 'srpc_soi_valid',
        'apr' => 'srpc_apr_valid',
        'hyp' => 'srpc_hyp_valid',
        'rwp' => 'srpc_rwp_valid',
        'ipr' => 'srpc_ipr_valid',
        'sfe' => 'srpc_sfe_valid',
        'sfr' => 'srpc_sfr_valid'
    );

    $F_index = 0;

    $F_index_values = array(
        109 => 0,
        102 => 3,
        92  => 3,
        121 => 3,
        48  => 0,
        137 => 0,
        45  => 1,
        75  => 0,
        5   => 1,
        112 => 0,
        117 => 0,
        64  => 0,
        32  => 1,
        33  => 0,
        78  => 3
    );

    $L_index = 0;

    $L_index_values = array(
        51  => 0,
        11  => 0,
        27  => 1,
        113 => 0,
        26  => 0,
        7   => 1,
        3   => 0,
        128 => 3,
        36  => 0,
        15  => 0,
        21  => 0,
        13  => 0,
        18  => 0
    );

    $cons_index_values = array(
        17  => 4,
        76  => 19,
        49  => 20,
        43  => 24,
        106 => 56,
        120 => 60,
        91  => 63,
        116 => 64,
        125 => 69,
        115 => 72,
        77  => 73,
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

    $raw_answers = array();
    # These are some fields that need their values flipped before scoring. Instead of going from 0 => 3, they go from 3 => 0
    $new_source = array();
    $flipped_values = array(64,69,73,75,77,87,97,116,125,136);
    $flipped_tf = array(1,2,4,5,6,8,9,10,14,16,17,19,22,23,24,25,28,29,30,31,33,34,35,37,38,39,40,42,43,44,45,46,47,48,50);
    $one_response_tf = array(3,11,13,15,18,21,26,36,51);
    $one_response_flipped_tf = array(7,27);
    $one_response_reg = array(113,128);
    
    foreach ($required_fields as $i => $req_name) {
        $index = $i+1;
        $val = $src[$req_name];
        if (isset($val) and strlen($val) > 0)
        {
            $val--;
        }
        
        if ($index == 49) {
            $raw_answers[$req_name] = (isset($val) and strlen($val) > 0) ? 3-$val : 0;
        }
        else
        {
            $raw_answers[$req_name] = (isset($val) and strlen($val) > 0) ? $val : 0;
        }
        if ($index == 84 or $index == 105)
        {
#           $this->module->emDebug("Val: " . $val . " so v_index was " . $v_index);
            $v_index += (isset($val) and strlen($val) > 0) ? $val : 0;
#           $this->module->emDebug("Now its " . $v_index);
        }
        if ($index == 16 or $index == 22 or $index == 50)
        {
            if ($val == 0 and isset($val) and strlen($val) > 0)
            {
#               $this->module->emDebug("Val: " . $val . " so v_index was " . $v_index);
                $v_index += 2;
#               $this->module->emDebug("Now its " . $v_index);
            }
        }

        if (in_array(($index), $flipped_values)) {
            $new_source[$req_name] = (isset($val) and strlen($val) > 0) ? 3-$val : null;
    #       $this->module->emDebug("Flipped values: i=$i, field_name = $req_name, val = $val, new source = $new_source[$req_name]");
        } 
        else if (($index)<52) {
            if (in_array(($index), $one_response_tf))
            {
                $realval = 0;
                if (in_array(($index), $one_response_flipped_tf))
                {
                    $new_source[$req_name] = (isset($val) and strlen($val) > 0) ? 1-$val : null;
                }
                else
                {
                    $new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $val : null;
                }
            }
            else if (in_array(($index), $flipped_tf))
            {
                $realval = 0;
                if ($val == 0)
                {
                    $realval = 2;
                }
                else
                {
                    $realval = 0;
                }
                $new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $realval: null;
            }
            else
            {
                $realval = 0;
                if ($val == 1)
                {
                    $realval = 2;
                }
                $new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $realval : null;
            }
        }
        else
        {
            if (in_array(($index), $one_response_reg))
            {
                $realval = 0;
                if (($index) == 128)
                {
                    if ($val == 3)
                    {
                        $realval = 1;
                    }
                    $new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $realval : null;
                }
                else if (($index) == 113) 
                {
                    if ($val == 0)
                    {
                        $realval = 1;
                    }
                    $new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $realval : null;
                }
            }
            else
            {
                $new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $val : null;
            }
        }

        if (array_key_exists(($index), $F_index_values))
    {
        if ($val == $F_index_values[($index)])
        {
            $F_index++;
        }
    }

    if (array_key_exists(($index), $L_index_values))
    {
        if ($val == $L_index_values[($index)])
        {
            $L_index++;
        }
    }

    if (array_key_exists(($index), $cons_index_values))
        {
            $cons_pair_val = $raw_answers[$required_fields[$cons_index_values[$index]-1]];
            $thisval = $raw_answers[$req_name];
#           $this->module->emDebug($req_name . ":" . $required_fields[$cons_index_values[$index]-1] . " || " . $raw_answers[$req_name] . ":" . $cons_pair_val);
            $cons_index += abs($cons_pair_val - $thisval);
#           $this->module->emDebug("adding: " . $cons_index);
        }

        foreach ($source_indexes as $scale => $qs)
        {
            if (in_array(($index), $qs))
            {
                $currVal = $validity_arr[$scale];
                if (!(isset($val) and (strlen($val) <= 0)))
                {
                    $validity_arr[$scale] = $currVal+1;
                }
            }
        }

        if ($index == 1)
        {
            $last_response = $val;
        }
        else 
        {
            if ($val != $last_response)
            {
                $resp_pattern++;
            }
            $last_response = $val;
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
    //              $this->module->emDebug("null value $val for field_name = $field_name, index = $index, null counter = $null_counter[$result_name]");
                }
            }
        }
    }


    $tvalue = array(
        'ats' => array(
                0 => 38, 1 => 40, 2 => 43, 3 => 45, 4 => 47, 5 => 50,
                6 => 52, 7 => 54, 8 => 56, 9 => 59, 10 => 61,
                11 => 63, 12 => 66, 13 => 68, 14 => 70, 15 => 72,
                16 => 75, 17 => 77, 18 => 79
            ),
        'att' => array(
                0 => 40, 1 => 43, 2 => 46, 3 => 49, 4 => 52, 5 => 55,
                6 => 58, 7 => 62, 8 => 65, 9 => 68, 10 => 71,
                11 => 74, 12 => 77, 13 => 80, 14 => 83, 15 => 86,
                16 => 89, 17 => 92, 18 => 95, 19 => 98, 20 => 101
            ),
        'ata' => array(
                0 => 38, 1 => 40, 2 => 42, 3 => 44, 4 => 45, 5 => 47,
                6 => 49, 7 => 51, 8 => 52, 9 => 54, 10 => 56,
                11 => 58, 12 => 59, 13 => 61, 14 => 63, 15 => 65,
                16 => 67, 17 => 68, 18 => 70, 19 => 72, 20 => 74,
                21 => 75, 22 => 77, 23 => 79, 24 => 81, 25 => 82
            ),
        'loc' => array(
                0 => 37, 1 => 39, 2 => 42, 3 => 44, 4 => 46, 5 => 48,
                6 => 51, 7 => 53, 8 => 55, 9 => 58, 10 => 60,
                11 => 62, 12 => 63, 13 => 67, 14 => 69, 15 => 72,
                16 => 74, 17 => 76, 18 => 78, 19 => 81, 20 => 83
            ),
        'sos' => array(
                0 => 37, 1 => 39, 2 => 41, 3 => 43, 4 => 46, 5 => 48,
                6 => 50, 7 => 52, 8 => 55, 9 => 57, 10 => 59,
                11 => 61, 12 => 64, 13 => 66, 14 => 68, 15 => 70, 
                16 => 72, 17 => 75, 18 => 77, 19 => 79, 20 => 81,
                21 => 84, 22 => 86, 23 => 88
            ),
        'anx' => array(
                0 => 34, 1 => 36, 2 => 37, 3 => 39, 4 => 40, 5 => 41,
                6 => 43, 7 => 44, 8 => 46, 9 => 47, 10 => 48,
                11 => 50, 12 => 51, 13 => 52, 14 => 54, 15 => 55,
                16 => 57, 17 => 58, 18 => 59, 19 => 61, 20 => 62,
                21 => 64, 22 => 65, 23 => 66, 24 => 68, 25 => 69,
                26 => 70, 27 => 72, 28 => 73, 29 => 75, 30 => 76,
                31 => 77, 32 => 79, 33 => 80, 34 => 82, 35 => 83,
                46 => 84, 47 => 86
            ),
        'dep' => array(
                0 => 40, 1 => 41, 2 => 43, 3 => 45, 4 => 46, 5 => 48,
                6 => 50, 7 => 51, 8 => 53, 9 => 55, 10 => 56,
                11 => 58, 12 => 60, 13 => 61, 14 => 63, 15 => 65,
                16 => 67, 17 => 68, 18 => 70, 19 => 72, 20 => 73,
                21 => 75, 22 => 77, 23 => 78, 24 => 80, 25 => 82,
                26 => 83, 27 => 85, 28 => 87, 29 => 88, 30 => 90
            ),
        'soi' => array(
                0 => 37, 1 => 39, 2 => 41, 3 => 44, 4 => 46, 5 => 49,
                6 => 51, 7 => 53, 8 => 56, 9 => 58, 10 => 60,
                11 => 63, 12 => 65, 13 => 68, 14 => 70, 15 => 72,
                16 => 75, 17 => 77, 18 => 80, 19 => 82, 20 => 84,
                21 => 87, 22 => 89
            ),
        'apr' => array(
                0 => 35, 1 => 37, 2 => 40, 3 => 42, 4 => 44, 5 => 46,
                6 => 49, 7 => 51, 8 => 53, 9 => 55, 10 => 58,
                11 => 60, 12 => 62, 13 => 64, 14 => 67, 15 => 69,
                16 => 71, 17 => 73, 18 => 76, 19 => 78, 20 => 80,
                21 => 82, 22 => 85, 23 => 87, 24 => 89
            ),
        'hyp' => array(
                0 => 35, 1 => 37, 2 => 40, 3 => 42, 4 => 44, 5 => 46,
                6 => 49, 7 => 51, 8 => 53, 9 => 56, 10 => 58,
                11 => 60, 12 => 63, 13 => 65, 14 => 67, 15 => 70,
                16 => 72, 17 => 74, 18 => 77, 19 => 79, 20 => 81,
                21 => 84, 22 => 86, 23 => 88
            ),
        'rwp' => array(
                0 => 10, 1 => 11, 2 => 13, 3 => 15, 4 => 17, 5 => 19,
                6 => 21, 7 => 23, 8 => 25, 9 => 27, 10 => 29,
                11 => 31, 12 => 33, 13 => 35, 14 => 36, 15 => 38,
                16 => 40, 17 => 42, 18 => 44, 19 => 46, 20 => 48,
                21 => 50, 22 => 52, 23 => 54, 24 => 56, 25 => 58,
                26 => 60, 27 => 62
            ),
        'ipr' => array(
                0 => 11, 1 => 14, 2 => 17, 3 => 20, 4 => 23, 5 => 26,
                6 => 29, 7 => 32, 8 => 35, 9 => 38, 10 => 41,
                11 => 44, 12 => 47, 13 => 50, 14 => 53, 15 => 56,
                16 => 59
            ),
        'sfe' => array(
                0 => 10, 1 => 10, 2 => 10, 3 => 12, 4 => 15, 5 => 18,
                6 => 21, 7 => 24, 8 => 27, 9 => 30, 10 => 32,
                11 => 35, 12 => 38, 13 => 41, 14 => 44, 15 => 47,
                16 => 50, 17 => 53, 18 => 55, 19 => 58
            ),
        'sfr' => array(
                0 => 14, 1 => 16, 2 => 18, 3 => 21, 4 => 23, 5 => 26,
                6 => 28, 7 => 30, 8 => 33, 9 => 35, 10 => 38,
                11 => 40, 12 => 43, 13 => 45, 14 => 47, 15 => 50,
                16 => 62, 17 => 55, 18 => 57, 19 => 59, 20 => 62,
                21 => 64, 22 => 67
            )
        );

    $tvalue_perc = array(
        'ats' => array(
                0 => 6, 1 => 17, 2 => 28, 3 => 39, 4 => 49, 5 => 57,
                6 => 64, 7 => 70, 8 => 76, 9 => 81, 10 => 85,
                11 => 88, 12 => 91, 13 => 93, 14 => 95, 15 => 97,
                16 => 98, 17 => 99, 18 => 99
            ),
        'att' => array(
                0 => 11, 1 => 28, 2 => 45, 3 => 58, 4 => 69, 5 => 77,
                6 => 83, 7 => 88, 8 => 91, 9 => 93, 10 => 95,
                11 => 97, 12 => 98, 13 => 98, 14 => 99, 15 => 99,
                16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99
            ),
        'ata' => array(
                0 => 9, 1 => 17, 2 => 25, 3 => 33, 4 => 41, 5 => 48,
                6 => 54, 7 => 60, 8 => 65, 9 => 70, 10 => 74,
                11 => 78, 12 => 81, 13 => 84, 14 => 87, 15 => 90,
                16 => 92, 17 => 94, 18 => 95, 19 => 97, 20 => 98,
                21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99
            ),
        'loc' => array(
                0 => 5, 1 => 13, 2 => 22, 3 => 32, 4 => 42, 5 => 51,
                6 => 59, 7 => 67, 8 => 73, 9 => 78, 10 => 83,
                11 => 87, 12 => 90, 13 => 93, 14 => 95, 15 => 97,
                16 => 98, 17 => 99, 18 => 99, 19 => 99, 20 => 99
            ),
        'sos' => array(
                0 => 4, 1 => 11, 2 => 19, 3 => 29, 4 => 39, 5 => 49,
                6 => 58, 7 => 66, 8 => 72, 9 => 78, 10 => 83,
                11 => 87, 12 => 90, 13 => 92, 14 => 94, 15 => 96,
                16 => 97, 17 => 98, 18 => 98, 19 => 99, 20 => 99,
                21 => 99, 22 => 99, 23 => 99
            ),
        'anx' => array(
                0 => 3, 1 => 5, 2 => 8, 3 => 12, 4 => 16, 5 => 21,
                6 => 26, 7 => 32, 8 => 37, 9 => 43, 10 => 48,
                11 => 53, 12 => 58, 13 => 63, 14 => 67, 15 => 72,
                16 => 75, 17 => 79, 18 => 82, 19 => 85, 20 => 87,
                21 => 89, 22 => 91, 23 => 93, 24 => 94, 25 => 96,
                26 => 97, 27 => 97, 28 => 98, 29 => 99, 30 => 99,
                31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99,
                36 => 99, 37 => 99
            ),
        'dep' => array(
                0 => 2, 1 => 15, 2 => 28, 3 => 39, 4 => 48, 5 => 56,
                6 => 62, 7 => 67, 8 => 72, 9 => 75, 10 => 79,
                11 => 82, 12 => 84, 13 => 86, 14 => 88, 15 => 90,
                16 => 92, 17 => 93, 18 => 94, 19 => 95, 20 => 96,
                21 => 97, 22 => 97, 23 => 98, 24 => 98, 25 => 99,
                26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99
            ),
        'soi' => array(
                0 => 3, 1 => 10, 2 => 20, 3 => 31, 4 => 42, 5 => 52,
                6 => 61, 7 => 69, 8 => 76, 9 => 81, 10 => 85,
                11 => 89, 12 => 91, 13 => 94, 14 => 95, 15 => 97,
                16 => 98, 17 => 98, 18 => 99, 19 => 99, 20 => 99,
                21 => 99, 22 => 99
            ),
        'apr' => array(
                0 => 2, 1 => 7, 2 => 15, 3 => 24, 4 => 33, 5 => 42,
                6 => 51, 7 => 59, 8 => 66, 9 => 72, 10 => 78,
                11 => 83, 12 => 87, 13 => 90, 14 => 93, 15 => 95,
                16 => 97, 17 => 98, 18 => 99, 19 => 99, 20 => 99,
                21 => 99, 22 => 99, 23 => 99, 24 => 99
            ),
        'hyp' => array(
                0 => 3, 1 => 8, 2 => 14, 3 => 23, 4 => 32, 5 => 41,
                6 => 51, 7 => 59, 8 => 67, 9 => 74, 10 => 80, 
                11 => 84, 12 => 88, 13 => 92, 14 => 94, 15 => 96,
                16 => 97, 17 => 98, 18 => 99, 19 => 99, 20 => 99,
                21 => 99, 22 => 99, 23 => 99
            ),
        'rwp' => array(
                0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 
                6 => 1, 7 => 1, 8 => 2, 9 => 3, 10 => 4,
                11 => 5, 12 => 7, 13 => 9, 14 => 11, 15 => 14,
                16 => 17, 17 => 21, 18 => 26, 19 => 31, 20 => 37,
                21 => 43, 22 => 51, 23 => 59, 24 => 67, 25 => 76,
                26 => 85, 27 => 93
            ),
        'ipr' => array(
                0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4,
                6 => 5, 7 => 7, 8 => 9, 9 => 12, 10 => 16,
                11 => 22, 12 => 29, 13 => 38, 14 => 50, 15 => 68,
                16 => 89
            ),
        'sfe' => array(
                0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
                6 => 2, 7 => 3, 8 => 4, 9 => 5, 10 => 7,
                11 => 9, 12 => 12, 13 => 15, 14 => 19, 15 => 25,
                16 => 34, 17 => 45, 18 => 62, 19 => 88
            ),
        'sfr' => array(
                0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
                6 => 2, 7 => 4, 8 => 6, 9 => 9, 10 => 13,
                11 => 17, 12 => 23, 13 =>  30, 14 => 37, 15 => 45,
                16 => 54, 17 => 64, 18 => 73, 19 => 81, 20 => 89,
                21 => 94, 22 => 98
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
    # Changing this to 0 missing scores - 12/13/2017 LY (from Robert Borah)
        if ($null_vals > 0) {
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
        'sprob'   => array('ats', 'att'),
        'intprob' => array('ata', 'loc', 'sos', 'anx', 'dep', 'soi'),
        'inthyp'  => array('apr', 'hyp'),
        'peradj'  => array('rwp', 'ipr', 'sfe', 'sfr'),
        'emosymp' => array('sos', 'anx', 'dep', 'soi', 'sfe', 'sfr')
    );


    $composite_comb_tvalues = array(
            'sprob' => array( 
                38 => array('min' => 78, 'max' => 79), 39 => array('min' => 80, 'max' => 81), 40 => array('min' => 82, 'max' => 83),
                41 => array('min' => 84, 'max' => 84), 42 => array('min' => 85, 'max' => 86), 43 => array('min' => 87, 'max' => 88),
                44 => array('min' => 89, 'max' => 90), 45 => array('min' => 91, 'max' => 92), 46 => array('min' => 93, 'max' => 93),
                47 => array('min' => 94, 'max' => 95), 48 => array('min' => 96, 'max' => 97), 49 => array('min' => 98, 'max' => 99),
                50 => array('min' => 100, 'max' => 100), 51 => array('min' => 101, 'max' => 102), 52 => array('min' => 103, 'max' => 104),
                53 => array('min' => 105, 'max' => 106), 54 => array('min' => 107, 'max' => 107), 55 => array('min' => 108, 'max' => 109),
                56 => array('min' => 110, 'max' => 111), 57 => array('min' => 112, 'max' => 113), 58 => array('min' => 114, 'max' => 115),
                59 => array('min' => 116, 'max' => 116), 60 => array('min' => 117, 'max' => 118), 61 => array('min' => 119, 'max' => 120),
                62 => array('min' => 121, 'max' => 122), 63 => array('min' => 123, 'max' => 123), 64 => array('min' => 124, 'max' => 125),
                66 => array('min' => 128, 'max' => 129), 67 => array('min' => 130, 'max' => 130), 68 => array('min' => 131, 'max' => 132),
                69 => array('min' => 133, 'max' => 134), 70 => array('min' => 135, 'max' => 136), 71 => array('min' => 137, 'max' => 138),
                72 => array('min' => 139, 'max' => 139), 73 => array('min' => 140, 'max' => 141), 74 => array('min' => 142, 'max' => 143),
                75 => array('min' => 144, 'max' => 145), 76 => array('min' => 146, 'max' => 146), 77 => array('min' => 147, 'max' => 148),
                78 => array('min' => 149, 'max' => 150), 79 => array('min' => 151, 'max' => 152), 80 => array('min' => 153, 'max' => 153),
                81 => array('min' => 154, 'max' => 155), 82 => array('min' => 156, 'max' => 157), 83 => array('min' => 158, 'max' => 159),
                84 => array('min' => 160, 'max' => 161), 85 => array('min' => 162, 'max' => 162), 86 => array('min' => 163, 'max' => 164),
                87 => array('min' => 165, 'max' => 166), 88 => array('min' => 167, 'max' => 168), 89 => array('min' => 169, 'max' => 169),
                90 => array('min' => 170, 'max' => 171), 91 => array('min' => 172, 'max' => 173), 92 => array('min' => 174, 'max' => 175),
                93 => array('min' => 176, 'max' => 176), 94 => array('min' => 177, 'max' => 178), 95 => array('min' => 179, 'max' => 180)
                ),
            'intprob' => array(
                35 => array('min' => 223, 'max' => 226), 36 => array('min' => 227, 'max' => 231), 37 => array('min' => 232, 'max' => 236),
                38 => array('min' => 237, 'max' => 241), 39 => array('min' => 242, 'max' => 246), 40 => array('min' => 247, 'max' => 251),
                41 => array('min' => 252, 'max' => 256), 42 => array('min' => 257, 'max' => 261), 43 => array('min' => 262, 'max' => 267),
                44 => array('min' => 268, 'max' => 272), 45 => array('min' => 273, 'max' => 277), 46 => array('min' => 278, 'max' => 282),
                47 => array('min' => 283, 'max' => 287), 48 => array('min' => 288, 'max' => 292), 49 => array('min' => 293, 'max' => 297),
                50 => array('min' => 298, 'max' => 302), 51 => array('min' => 303, 'max' => 307), 52 => array('min' => 308, 'max' => 312),
                53 => array('min' => 313, 'max' => 317), 54 => array('min' => 318, 'max' => 322), 55 => array('min' => 323, 'max' => 327),
                56 => array('min' => 328, 'max' => 332), 57 => array('min' => 333, 'max' => 338), 58 => array('min' => 339, 'max' => 343),
                59 => array('min' => 344, 'max' => 348), 60 => array('min' => 349, 'max' => 353), 61 => array('min' => 354, 'max' => 358),
                62 => array('min' => 359, 'max' => 363), 63 => array('min' => 364, 'max' => 368), 64 => array('min' => 369, 'max' => 373),
                65 => array('min' => 374, 'max' => 378), 66 => array('min' => 379, 'max' => 383), 67 => array('min' => 384, 'max' => 388),
                68 => array('min' => 389, 'max' => 393), 69 => array('min' => 394, 'max' => 398), 70 => array('min' => 399, 'max' => 403),
                71 => array('min' => 404, 'max' => 409), 72 => array('min' => 410, 'max' => 414), 73 => array('min' => 415, 'max' => 419),
                74 => array('min' => 420, 'max' => 424), 75 => array('min' => 425, 'max' => 429), 76 => array('min' => 430, 'max' => 434),
                77 => array('min' => 435, 'max' => 439), 78 => array('min' => 440, 'max' => 444), 79 => array('min' => 445, 'max' => 449),
                80 => array('min' => 450, 'max' => 454), 81 => array('min' => 455, 'max' => 459), 82 => array('min' => 460, 'max' => 464),
                83 => array('min' => 465, 'max' => 469), 84 => array('min' => 470, 'max' => 474), 85 => array('min' => 475, 'max' => 480),
                86 => array('min' => 481, 'max' => 485), 87 => array('min' => 486, 'max' => 490), 88 => array('min' => 491, 'max' => 495),
                89 => array('min' => 496, 'max' => 500), 90 => array('min' => 501, 'max' => 505), 91 => array('min' => 506, 'max' => 510),
                92 => array('min' => 511, 'max' => 515), 93 => array('min' => 516, 'max' => 518)
                ),
            'inthyp' => array(
                34 => array('min' => 70, 'max' => 71), 35 => array('min' => 72, 'max' => 73), 36 => array('min' => 74, 'max' => 75),
                37 => array('min' => 76, 'max' => 76), 38 => array('min' => 77, 'max' => 78), 39 => array('min' => 79, 'max' => 80),
                40 => array('min' => 81, 'max' => 82), 41 => array('min' => 83, 'max' => 84), 42 => array('min' => 85, 'max' => 86),
                43 => array('min' => 87, 'max' => 88), 44 => array('min' => 89, 'max' => 89), 45 => array('min' => 90, 'max' => 91),
                46 => array('min' => 92, 'max' => 93), 47 => array('min' => 94, 'max' => 95), 48 => array('min' => 96, 'max' => 97),
                49 => array('min' => 98, 'max' => 99), 50 => array('min' => 100, 'max' => 100), 51 => array('min' => 101, 'max' => 102),
                52 => array('min' => 103, 'max' => 104), 53 => array('min' => 105, 'max' => 106), 54 => array('min' => 107, 'max' => 108), 
                55 => array('min' => 109, 'max' => 110), 56 => array('min' => 111, 'max' => 111), 57 => array('min' => 112, 'max' => 113),
                58 => array('min' => 114, 'max' => 115), 59 => array('min' => 116, 'max' => 117), 60 => array('min' => 118, 'max' => 119),
                61 => array('min' => 120, 'max' => 121), 62 => array('min' => 122, 'max' => 123), 63 => array('min' => 124, 'max' => 124),
                64 => array('min' => 125, 'max' => 126), 65 => array('min' => 127, 'max' => 128), 66 => array('min' => 129, 'max' => 130),
                67 => array('min' => 131, 'max' => 132), 68 => array('min' => 133, 'max' => 134), 69 => array('min' => 135, 'max' => 135),
                70 => array('min' => 136, 'max' => 137), 71 => array('min' => 138, 'max' => 139), 72 => array('min' => 140, 'max' => 141),
                73 => array('min' => 142, 'max' => 143), 74 => array('min' => 144, 'max' => 145), 75 => array('min' => 146, 'max' => 146),
                76 => array('min' => 147, 'max' => 148), 77 => array('min' => 149, 'max' => 150), 78 => array('min' => 151, 'max' => 152),
                79 => array('min' => 153, 'max' => 154), 80 => array('min' => 155, 'max' => 156), 81 => array('min' => 157, 'max' => 157), 
                82 => array('min' => 158, 'max' => 159), 83 => array('min' => 160, 'max' => 161), 84 => array('min' => 162, 'max' => 163),
                85 => array('min' => 164, 'max' => 165), 86 => array('min' => 166, 'max' => 167), 87 => array('min' => 168, 'max' => 169),
                88 => array('min' => 170, 'max' => 170), 89 => array('min' => 171, 'max' => 172), 90 => array('min' => 173, 'max' => 174),
                91 => array('min' => 175, 'max' => 176), 92 => array('min' => 177, 'max' => 177)
                ),
            'peradj' => array(
                10 => array('min' => 45, 'max' => 80), 11 => array('min' => 81, 'max' => 83), 12 => array('min' => 84, 'max' => 86),
                13 => array('min' => 87, 'max' => 89), 14 => array('min' => 90, 'max' => 92), 15 => array('min' => 93, 'max' => 96),
                16 => array('min' => 97, 'max' => 99), 17 => array('min' => 100, 'max' => 102), 18 => array('min' => 103, 'max' => 105),
                19 => array('min' => 106, 'max' => 108), 20 => array('min' => 109, 'max' => 111), 21 => array('min' => 112, 'max' => 114),
                22 => array('min' => 115, 'max' => 117), 23 => array('min' => 118, 'max' => 120), 24 => array('min' => 121, 'max' => 123), 
                25 => array('min' => 124, 'max' => 126), 26 => array('min' => 127, 'max' => 129), 27 => array('min' => 130, 'max' => 132),
                28 => array('min' => 133, 'max' => 135), 29 => array('min' => 136, 'max' => 138), 30 => array('min' => 139, 'max' => 141),
                31 => array('min' => 142, 'max' => 144), 32 => array('min' => 145, 'max' => 147), 33 => array('min' => 148, 'max' => 150),
                34 => array('min' => 151, 'max' => 153), 35 => array('min' => 154, 'max' => 156), 36 => array('min' => 157, 'max' => 159),
                37 => array('min' => 160, 'max' => 162), 38 => array('min' => 163, 'max' => 165), 39 => array('min' => 166, 'max' => 168),
                40 => array('min' => 169, 'max' => 171), 41 => array('min' => 172, 'max' => 174), 42 => array('min' => 175, 'max' => 177),
                43 => array('min' => 178, 'max' => 180), 44 => array('min' => 181, 'max' => 183), 45 => array('min' => 184, 'max' => 186),
                46 => array('min' => 187, 'max' => 189), 47 => array('min' => 190, 'max' => 192), 48 => array('min' => 193, 'max' => 195),
                49 => array('min' => 196, 'max' => 198), 50 => array('min' => 199, 'max' => 201), 51 => array('min' => 202, 'max' => 204),
                52 => array('min' => 205, 'max' => 207), 53 => array('min' => 208, 'max' => 210), 54 => array('min' => 211, 'max' => 213),
                55 => array('min' => 214, 'max' => 216), 56 => array('min' => 217, 'max' => 219), 57 => array('min' => 220, 'max' => 222),
                58 => array('min' => 223, 'max' => 225), 59 => array('min' => 226, 'max' => 228), 60 => array('min' => 229, 'max' => 231),
                61 => array('min' => 232, 'max' => 234), 62 => array('min' => 235, 'max' => 237), 63 => array('min' => 238, 'max' => 240),
                64 => array('min' => 241, 'max' => 243), 65 => array('min' => 244, 'max' => 246)
                ),
            'emosymp' => array(
                23 => array('min' => 172, 'max' => 176), 24 => array('min' => 177, 'max' => 180), 25 => array('min' => 181, 'max' => 185),
                26 => array('min' => 186, 'max' => 190), 27 => array('min' => 191, 'max' => 194), 28 => array('min' => 195, 'max' => 199),
                29 => array('min' => 200, 'max' => 204), 30 => array('min' => 205, 'max' => 208), 31 => array('min' => 209, 'max' => 213),
                32 => array('min' => 214, 'max' => 218), 33 => array('min' => 219, 'max' => 222), 34 => array('min' => 223, 'max' => 227),
                35 => array('min' => 228, 'max' => 232), 36 => array('min' => 233, 'max' => 236), 37 => array('min' => 237, 'max' => 241),
                38 => array('min' => 242, 'max' => 246), 39 => array('min' => 247, 'max' => 250), 40 => array('min' => 251, 'max' => 255),
                41 => array('min' => 256, 'max' => 260), 42 => array('min' => 261, 'max' => 264), 43 => array('min' => 265, 'max' => 269),
                44 => array('min' => 270, 'max' => 274), 45 => array('min' => 275, 'max' => 278), 46 => array('min' => 279, 'max' => 283),
                47 => array('min' => 284, 'max' => 288), 48 => array('min' => 289, 'max' => 292), 49 => array('min' => 293, 'max' => 297),
                50 => array('min' => 298, 'max' => 302), 51 => array('min' => 303, 'max' => 307), 52 => array('min' => 308, 'max' => 311),
                53 => array('min' => 312, 'max' => 316), 54 => array('min' => 317, 'max' => 321), 55 => array('min' => 322, 'max' => 325),
                56 => array('min' => 326, 'max' => 330), 57 => array('min' => 331, 'max' => 335), 58 => array('min' => 336, 'max' => 339),
                59 => array('min' => 340, 'max' => 344), 60 => array('min' => 345, 'max' => 349), 61 => array('min' => 350, 'max' => 353),
                62 => array('min' => 354, 'max' => 358), 63 => array('min' => 359, 'max' => 363), 64 => array('min' => 364, 'max' => 367),
                65 => array('min' => 368, 'max' => 372), 
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
    foreach ($composite_names as $field_names) {
        $result = 0;

        foreach ($composite_calc[$field_names] as $subscale) {
            $name = $subscale . '_tval';
            // if ($field_names == 'emosymp')
            // {
                // if ($subscale == 'sfe' or $subscale == 'sfr')
                // {
                    // $result += 100-$tvalue_scores[$name];
                // }
                // else
                // {
                    // $result += $tvalue_scores[$name];
                // }
            // }
            // else
            // {
                $result += $tvalue_scores[$name];
            // }
        }

        $result_tval = lookup_composite_tvalue($composite_comb_tvalues[$field_names], $result); 
        $composite_raw[$field_names . '_comp'] = $result;
        // if ($field_names != 'emosymp')
        // {
            $composite_tval[$field_names . '_comp_tval'] = $result_tval;
        // }
        if ($field_names == 'emosymp') {
            $num_items = count($composite_calc[$field_names]);
            $composite_mean[$field_names . '_comp_imean'] = 100-$result/$num_items;
        }
    }
    
#    $this->module->emDebug("Composite Raw:" . json_encode($composite_raw));
#    $this->module->emDebug("Composite Tval:" . json_encode($composite_tval));
    
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


$forty = array('sfr', 'sfe', 'rwp', 'ipr');
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
        //if ($field_names == 'emosymp') continue;
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
$totals =  array_merge(
    $raw_score_totals, 
    $null_counter, 
    $tvalue_scores, 
    $tvalue_percent, 
    $composite_raw, 
    $composite_tval, 
    $composite_mean, 
    $completeness_interpret, 
    $F_index_result, 
    $V_index_result, 
    $L_index_result, 
    $cons_index_result, 
    $resp_pattern_result, 
    $stat_result, 
    $sig_vals
);

/*
$this->module->emDebug(
    "\nRaw total: " . count($raw_score_totals) . 
    "\nNull Counter: " . count($null_counter) . 
    "\nTvalue: " . count($tvalue_scores) . 
    "\n Tvalp: " . count($tvalue_percent) . 
    "\nComp Raw: " . count($composite_raw) . 
    "\nComp Tval: " . count($composite_tval) . 
    "\nComp Mean: " . count($composite_mean) . 
    "\nComplete: " . count($completeness_interpret) . 
    "\nF: " . count($F_index_result) . 
    "\nV: " . count($V_index_result) . 
    "\nL: " . count($L_index_result) . 
    "\nCons: " . count($cons_index_result) . 
    "\nResp: " . count($resp_pattern_result) . 
    "\nStats: " . count($stat_result) .
    "\nSigVal: " . count($sig_vals));
$this->module->emDebug("Default Result Fields: " . count($default_result_fields) . " vs Mine: " . count($totals));
*/

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
