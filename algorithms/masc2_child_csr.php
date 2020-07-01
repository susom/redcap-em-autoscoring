<?php

/**
  Multi-Dimensional Anxiety Scale for Children, 2nd edition

  50 Question Child Self Report 
  Based on Hand Score Form 

  Developed by Kim Wijaya and Alex Basile for ELSPAP June 2016


  
  A REDCap AutoScoring Algorithm File
  
  - There exists an array called $src that contains the data from the source project
  - There can exist an optional array called $manual_result_fields that can override the default_result_fields
  - The final results should be presented in an array called $algorithm_results
        - The answers are categorized as:
              0 = Never
              1 = Rarely
              2 = Sometimes
              3 = Often
        - There are 50 questions that the participant fills out.
**/
# REQUIRED: Summarize this algorithm
$algorithm_summary = "Multidimensional Anxiety Scale for Children, 2nd edition";
$this->module->emDebug("Scoring Title: " . $algorithm_summary);
# REQUIRED: Define an array of default result field_names to record the summary data
# The MASC2 Validity Scoring Totals are:
#   MASC2 Total Scale and MASC2 Anxiety Probability Scale 
# The clinical scales are: 
#   Separation Anxiety/Phobias
#   GAD Index
#   Humiliation/Rejection
#   Performance Fears
#   Obsessions & Compulsions
#   Panic
#   Tense/Restless
#   Harm Avoidance
#   Social Anxiety
#   Physical Symptoms Total
#   MASC2 Total
#   Inconsistency Index (response style)
$default_result_fields = array(
        'masc2_sr_sp_val',
        'masc2_sr_gad_val',
        'masc2_sr_hr_val',
  'masc2_sr_pf_val',
  'masc2_sr_oc_val',
  'masc2_sr_p_val',
  'masc2_sr_tr_val',
  'masc2_sr_ha_val',
        'masc2_sr_sa_val',
        'masc2_sr_pst_val',
        'masc2_sr_total',
        'masc2_sr_inconsis_idx',
        'masc2_sr_total_tval',
        'masc2_sr_sp_tval',
        'masc2_sr_gad_tval',
        'masc2_sr_sa_tval',
        'masc2_sr_hr_tval',
        'masc2_sr_pf_tval',
        'masc2_sr_oc_tval',
        'masc2_sr_pst_tval',
        'masc2_sr_p_tval',
        'masc2_sr_tr_tval',
        'masc2_sr_ha_tval',
        'masc2_sr_anx_prob'
#        'masc2_sr_total_sig',
#        'masc2_sr_sp_sig',
#        'masc2_sr_gad_sig',
#        'masc2_sr_sa_sig',
#        'masc2_sr_hr_sig',
#        'masc2_sr_pf_sig',
#        'masc2_sr_oc_sig',
#        'masc2_sr_pst_sig',
#        'masc2_sr_p_sig',
#        'masc2_sr_tr_sig',
#        'masc2_sr_ha_sig',
#        'masc2_sr_anx_sig'
);
# REQUIRED: Define an array of fields that must be present for this algorithm to run
$required_fields = array();
foreach (range(1,50) as $i) {
  array_push($required_fields, "masc2_srp_q$i");
}
//$this->module->emDebug("Required input field names ".implode(',',$required_fields));
# Override default input array with manual field names specified by user (optional)
if (!empty($manual_source_fields)) {
    if (count($manual_source_fields) == count($required_fields)) {
            foreach($manual_source_fields as $k => $field) {
                        if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
                                $required_fields[$k] = $field;
#                                $this->module->emDebug("Changing input field ".$k." to ".$field,true);
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
      if ($field) { // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
        $default_result_fields[$k] = $field;
          $this->module->emDebug("Changing result field ".$k." to ". $field);
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
  return false; //Since this is being called via include, the main script will continue to process other algorithms
}
# Count up how many fields do not have a value
$null_fields = array();
$num_null_fields = 0;
foreach ($required_fields as $rf) {
  if (empty($src[$rf]) && !is_numeric($src[$rf])) {
               $null_fields[] = $rf;
               $num_null_fields++;
        }
}
if (!empty($null_fields)) {
  $algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
  return false;
}
### IMPLEMENT RAW SCORING ###
# This is the array source
# Define lists of questions that correspond to each subscale
$sp_idx = array(26,30,33,4,7,9,17,19,23);
$gad_idx = array(27,29,31,1,6,39,40,13,17,22);
$hr_idx = array(29,3,10,16,22);
$pf_idx = array(32,36,38,14);
$pf_idx = array(32,36,38,14);
$oc_idx = array(41,42,43,44,45,46,47,48,49,50);
$p_idx = array(31,37,6,12,18,20,24);
$tr_idx = array(27,1,34,8,15);
$ha_idx = array(28,2,35,5,11,13,21,25);

$sp_val  = 0;
$gad_val = 0;
$hr_val  = 0;
$pf_val  = 0;
$oc_val  = 0;
$p_val   = 0;
$tr_val  = 0;
$ha_val  = 0;


foreach ($required_fields as $i => $field_name) {
  $val = $src[$field_name];
  if (in_array($i+1, $sp_idx))  $sp_val  += $val;
  if (in_array($i+1, $gad_idx)) $gad_val += $val;
  if (in_array($i+1, $hr_idx))  $hr_val  += $val;
  if (in_array($i+1, $pf_idx))  $pf_val  += $val;
  if (in_array($i+1, $oc_idx))  $oc_val  += $val;
  if (in_array($i+1, $p_idx))   $p_val   += $val;
  if (in_array($i+1, $tr_idx))  $tr_val  += $val;
  if (in_array($i+1, $ha_idx))  $ha_val  += $val;
  $this->module->emDebug("Index = $i, field name = $field_name, field value = $val");
}
$required_keys = array_keys($required_fields);
$sat_val = $hr_val + $pf_val;
$pst_val = $p_val + $tr_val;
# Adding the 39th and 40th value but since the index starts at 0, the index is 38 and 39.
$masc2_total = $sp_val + $sat_val + $oc_val + $pst_val + $ha_val + 
        $src[$required_fields[$required_keys[38]]] + $src[$required_fields[$required_keys[39]]];
### Calculate the Inconsistency Index
$inconsistancy_idx = abs($src[$required_fields[$required_keys[2]]] - $src[$required_fields[$required_keys[9]]])
                   + abs($src[$required_fields[$required_keys[3]]] - $src[$required_fields[$required_keys[8]]])
                   + abs($src[$required_fields[$required_keys[7]]] - $src[$required_fields[$required_keys[14]]])
                   + abs($src[$required_fields[$required_keys[12]]] - $src[$required_fields[$required_keys[34]]])
                   + abs($src[$required_fields[$required_keys[19]]] - $src[$required_fields[$required_keys[26]]])
                   + abs($src[$required_fields[$required_keys[21]]] - $src[$required_fields[$required_keys[28]]])
                   + abs($src[$required_fields[$required_keys[42]]] - $src[$required_fields[$required_keys[43]]])
                   + abs($src[$required_fields[$required_keys[46]]] - $src[$required_fields[$required_keys[49]]]);
$result_values = array($sp_val, $gad_val, $hr_val, $pf_val, $oc_val, $p_val, $tr_val, $ha_val,
                       $sat_val, $pst_val, $masc2_total, $inconsistancy_idx);
### Now get the T values from the grid. There are 3 different grids to choose from based on age.
#  Grid 1 = 8-11 yr olds, Grid 2 = 12-15 yr olds, and Grid 3 = 16-19 yr olds
#  Since the example I have uses Grid 1, I will use Grid 1 here.
$masc2_tmatrix = array(
      37 =>  40,
      38 =>  41,  39 =>  41,
      40 =>  42,  41 =>  42,
      42 =>  43,  43 =>  43,
      44 =>  44,
      45 =>  45,  46 =>  45,
      47 =>  48,  48 =>  48,
      49 =>  47,  50 =>  47,
      51 =>  48,  52 =>  48,
      53 =>  49,  54 =>  49,
      55 =>  50,  56 =>  50,
      57 =>  51,  58 =>  51,
      59 =>  52,  60 =>  52,
      61 =>  53,  62 =>  53,
      63 =>  54,  64 =>  54,
      65 =>  55,  66 =>  55,
      67 =>  56,  68 =>  56,
      69 =>  57,
      70 =>  58,  71 =>  58,
      72 =>  59,  73 =>  59,
      74 =>  60,  75 =>  60,
      76 =>  61,  77 =>  61,
      78 =>  62,  79 =>  62,
      80 =>  63,  81 =>  63,
      82 =>  64,  83 =>  64,
      84 =>  65,  85 =>  65,
      86 =>  66,  87 =>  66,
      88 =>  67,  89 =>  67,
      90 =>  68,  91 =>  68,
      92 =>  69,  93 =>  69,
      94 =>  70,  95 =>  70,
      96 =>  71,
      97 =>  72,  98 =>  72,
      99 =>  73, 100 =>  73,
     101 =>  74, 102 =>  74,
     103 =>  75, 104 =>  75,
     105 =>  76, 106 =>  76,
     107 =>  77, 108 =>  77,
     109 =>  78, 110 =>  78,
     111 =>  79, 112 =>  79,
     113 =>  80, 114 =>  80,
     115 =>  81, 116 =>  81,
     117 =>  82, 118 =>  82,
     119 =>  83, 120 =>  83,
     121 =>  84,
     122 =>  85, 123 =>  85,
     124 =>  86, 125 =>  86,
     126 =>  87, 127 =>  87,
     128 =>  88, 129 =>  88,
     130 =>  89, 131 =>  89,
     132 =>  132
   );
$sp_tmatrix = array(
      5 =>  40,
      6 =>  41,
      7 =>  44,
      8 =>  46,
      9 =>  48,
     10 =>  50,
     11 =>  53,
     12 =>  55,
     13 =>  57,
     14 =>  59,
     15 =>  62,
     16 =>  64,
     17 =>  66,
     18 =>  68,
     19 =>  71,
     20 =>  73,
     21 =>  75,
     22 =>  78,
     23 =>  80,
     24 =>  82,
     25 =>  84,
     26 =>  87,
     27 =>  89
      );
$gad_tmatrix = array(
      5 =>  40,
      6 =>  42,
      7 =>  44,
      8 =>  46,
      9 =>  49,
     10 =>  51,
     11 =>  53,
     12 =>  55,
     13 =>  57,
     14 =>  59,
     15 =>  61,
     16 =>  63,
     17 =>  66,
     18 =>  68,
     19 =>  70,
     20 =>  72,
     21 =>  74,
     22 =>  76,
     23 =>  78,
     24 =>  80,
     25 =>  83,
     26 =>  85,
     27 =>  87,
     28 =>  89,
     29 =>  90
      );
$sa_tmatrix = array(
      5 =>  40,
      6 =>  42,
      7 =>  43,
      8 =>  45,
      9 =>  47,
     10 =>  48,
     11 =>  50,
     12 =>  52,
     13 =>  53,
     14 =>  55,
     15 =>  57,
     16 =>  58,
     17 =>  60,
     18 =>  62,
     19 =>  63,
     20 =>  65,
     21 =>  67,
     22 =>  68,
     23 =>  70,
     24 =>  71,
     25 =>  73,
     26 =>  75,
     27 =>  76
      );
$hr_tmatrix = array(
      1 =>  40,
      2 =>  41,
      3 =>  43,
      4 =>  45,
      5 =>  48,
      6 =>  50,
      7 =>  53,
      8 =>  55,
      9 =>  57,
     10 =>  60,
     11 =>  62,
     12 =>  65,
     13 =>  67,
     14 =>  69,
     15 =>  72
      );
$pf_tmatrix = array(
      2 =>  40,
      3 =>  43,
      4 =>  46,
      5 =>  50,
      6 =>  54,
      7 =>  58,
      8 =>  61,
      9 =>  65,
     10 =>  69,
     11 =>  73,
     12 =>  76
      );
$oc_tmatrix = array(
      1 =>  40,
      2 =>  41,
      3 =>  42,
      4 =>  44,
      5 =>  46,
      6 =>  47,
      7 =>  49,
      8 =>  51,
      9 =>  53,
     10 =>  54,
     11 =>  56,
     12 =>  58,
     13 =>  59,
     14 =>  61,
     15 =>  63,
     16 =>  64,
     17 =>  66,
     18 =>  68,
     19 =>  69,
     20 =>  71,
     21 =>  73,
     22 =>  75,
     23 =>  76,
     24 =>  78,
     25 =>  80,
     26 =>  81,
     27 =>  83,
     28 =>  85,
     29 =>  86,
     30 =>  88
      );
$pst_tmatrix = array(
      1 =>  40,
      2 =>  41,
      3 =>  43,
      4 =>  45,
      5 =>  46,
      6 =>  48,
      7 =>  50,
      8 =>  51,
      9 =>  53,
     10 =>  55,
     11 =>  56,
     12 =>  58,
     13 =>  60,
     14 =>  61,
     15 =>  63,
     16 =>  65,
     17 =>  66,
     18 =>  68,
     19 =>  70,
     20 =>  72,
     21 =>  73,
     22 =>  75,
     23 =>  77,
     24 =>  78,
     25 =>  80,
     26 =>  82,
     27 =>  83,
     28 =>  85,
     29 =>  87,
     30 =>  88,
     31 =>  90
       );
$p_tmatrix = array(
      0 =>  40,
      1 =>  42,
      2 =>  45,
      3 =>  48,
      4 =>  51,
      5 =>  54,
      6 =>  57,
      7 =>  60,
      8 =>  62,
      9 =>  65,
     10 =>  68,
     11 =>  71,
     12 =>  74,
     13 =>  77,
     14 =>  80,
     15 =>  83,
     16 =>  86,
     17 =>  89,
     18 =>  90
      );
$tr_tmatrix = array(
      0 =>  40,
      1 =>  42,
      2 =>  45,
      3 =>  49,
      4 =>  52,
      5 =>  55,
      6 =>  58,
      7 =>  61,
      8 =>  65,
      9 =>  68,
     10 =>  71,
     11 =>  74,
     12 =>  77,
     13 =>  81,
     14 =>  84,
     15 =>  87
      );
$ha_tmatrix = array(
     13 =>  40,
     14 =>  41,
     15 =>  43,
     16 =>  46,
     17 =>  48,
     18 =>  51,
     19 =>  54,
     20 =>  56,
     21 =>  59,
     22 =>  62,
     23 =>  64,
     24 =>  67
      );
function lookup_tvalue($tvalue_matrix, $raw_score) {
   $first_value = reset($tvalue_matrix);
   $first_key = key($tvalue_matrix);
   $last_value = end($tvalue_matrix);
   $last_key = key($tvalue_matrix);
   if ($raw_score <= $first_key) {
       $result = $first_value;
   } elseif ($raw_score >= $last_key) {
       $result = $last_value;
   } elseif (isset($tvalue_matrix[$raw_score])) {
       $result = $tvalue_matrix[$raw_score];
   } else {
       $result = NULL;
   }
   return $result;
}
# Go to the look up tables and retrieve the tvalues.


$masc2_total_tval = lookup_tvalue($masc2_tmatrix, $masc2_total);
$masc2_sp_tval  = lookup_tvalue($sp_tmatrix, $sp_val);
$masc2_gad_tval = lookup_tvalue($gad_tmatrix, $gad_val);
$masc2_sa_tval  = lookup_tvalue($sa_tmatrix, $sat_val);
$masc2_hr_tval  = lookup_tvalue($hr_tmatrix, $hr_val);
$masc2_pf_tval  = lookup_tvalue($pf_tmatrix, $pf_val);
$masc2_oc_tval  = lookup_tvalue($oc_tmatrix, $oc_val);
$masc2_pst_tval = lookup_tvalue($pst_tmatrix, $pst_val);
$masc2_p_tval   = lookup_tvalue($p_tmatrix, $p_val);
$masc2_tr_tval  = lookup_tvalue($tr_tmatrix, $tr_val);
$masc2_ha_tval  = lookup_tvalue($ha_tmatrix, $ha_val);


if ($masc2_total_tval <= 39)
{
  $sig_vals['masc2_sr_total_sig'] = "Low";
}
else if ($masc2_total_tval >= 40 and $masc2_total_tval <= 54)
{
  $sig_vals['masc2_sr_total_sig'] = "Average";
}
else if ($masc2_total_tval >= 55 and $masc2_total_tval <= 59)
{
  $sig_vals['masc2_sr_total_sig'] = "High Average";
}
else if ($masc2_total_tval >= 60 and $masc2_total_tval <= 64)
{
  $sig_vals['masc2_sr_total_sig'] = "Slightly Elevated";
}
else if ($masc2_total_tval >= 65 and $masc2_total_tval <= 69)
{
  $sig_vals['masc2_sr_total_sig'] = "Elevated";
}
else if ($masc2_total_tval >= 70)
{
  $sig_vals['masc2_sr_total_sig'] = "Very Elevated";
}

if ($masc2_sp_tval <= 39)
{
  $sig_vals['masc2_sr_sp_sig'] = "Low";
}
else if ($masc2_sp_tval >= 40 and $masc2_sp_tval <= 54)
{
  $sig_vals['masc2_sr_sp_sig'] = "Average";
}
else if ($masc2_sp_tval >= 55 and $masc2_sp_tval <= 59)
{
  $sig_vals['masc2_sr_sp_sig'] = "High Average";
}
else if ($masc2_sp_tval >= 60 and $masc2_sp_tval <= 64)
{
  $sig_vals['masc2_sr_total_sig'] = "Slightly Elevated";
}
else if ($masc2_sp_tval >= 65 and $masc2_sp_tval <= 69)
{
  $sig_vals['masc2_sr_total_sig'] = "Elevated";
}
else if ($masc2_sp_tval >= 70)
{
  $sig_vals['masc2_sr_total_sig'] = "Very Elevated";
}

if ($masc2_gad_tval <= 39)
{
  $sig_vals['masc2_sr_gad_sig'] = "Low";
}
else if ($masc2_gad_tval >= 40 and $masc2_gad_tval <= 54)
{
  $sig_vals['masc2_sr_gad_sig'] = "Average";
}
else if ($masc2_gad_tval >= 55 and $masc2_gad_tval <= 59)
{
  $sig_vals['masc2_sr_gad_sig'] = "High Average";
}
else if ($masc2_gad_tval >= 60 and $masc2_gad_tval <= 64)
{
  $sig_vals['masc2_sr_gad_sig'] = "Slightly Elevated";
}
else if ($masc2_gad_tval >= 65 and $masc2_gad_tval <= 69)
{
  $sig_vals['masc2_sr_gad_sig'] = "Elevated";
}
else if ($masc2_gad_tval >= 70)
{
  $sig_vals['masc2_sr_gad_sig'] = "Very Elevated";
}


if ($masc2_sa_tval <= 39)
{
  $sig_vals['masc2_sr_sa_sig'] = "Low";
}
else if ($masc2_sa_tval >= 40 and $masc2_sa_tval <= 54)
{
  $sig_vals['masc2_sr_sa_sig'] = "Average";
}
else if ($masc2_sa_tval >= 55 and $masc2_sa_tval <= 59)
{
  $sig_vals['masc2_sr_sa_sig'] = "High Average";
}
else if ($masc2_sa_tval >= 60 and $masc2_sa_tval <= 64)
{
  $sig_vals['masc2_sr_sa_sig'] = "Slightly Elevated";
}
else if ($masc2_sa_tval >= 65 and $masc2_sa_tval <= 69)
{
  $sig_vals['masc2_sr_sa_sig'] = "Elevated";
}
else if ($masc2_sa_tval >= 70)
{
  $sig_vals['masc2_sr_sa_sig'] = "Very Elevated";
}

if ($masc2_hr_tval <= 39)
{
  $sig_vals['masc2_sr_hr_sig'] = "Low";
}
else if ($masc2_hr_tval >= 40 and $masc2_hr_tval <= 54)
{
  $sig_vals['masc2_sr_hr_sig'] = "Average";
}
else if ($masc2_hr_tval >= 55 and $masc2_hr_tval <= 59)
{
  $sig_vals['masc2_sr_hr_sig'] = "High Average";
}
else if ($masc2_hr_tval >= 60 and $masc2_hr_tval <= 64)
{
  $sig_vals['masc2_sr_hr_sig'] = "Slightly Elevated";
}
else if ($masc2_hr_tval >= 65 and $masc2_hr_tval <= 69)
{
  $sig_vals['masc2_sr_hr_sig'] = "Elevated";
}
else if ($masc2_hr_tval >= 70)
{
  $sig_vals['masc2_sr_hr_sig'] = "Very Elevated";
}

if ($masc2_pf_tval <= 39)
{
  $sig_vals['masc2_sr_pf_sig'] = "Low";
}
else if ($masc2_pf_tval >= 40 and $masc2_pf_tval <= 54)
{
  $sig_vals['masc2_sr_pf_sig'] = "Average";
}
else if ($masc2_pf_tval >= 55 and $masc2_pf_tval <= 59)
{
  $sig_vals['masc2_sr_pf_sig'] = "High Average";
}
else if ($masc2_pf_tval >= 60 and $masc2_pf_tval <= 64)
{
  $sig_vals['masc2_sr_pf_sig'] = "Slightly Elevated";
}
else if ($masc2_pf_tval >= 65 and $masc2_pf_tval <= 69)
{
  $sig_vals['masc2_sr_pf_sig'] = "Elevated";
}
else if ($masc2_pf_tval >= 70)
{
  $sig_vals['masc2_sr_pf_sig'] = "Very Elevated";
}

if ($masc2_oc_tval <= 39)
{
  $sig_vals['masc2_sr_oc_sig'] = "Low";
}
else if ($masc2_oc_tval >= 40 and $masc2_oc_tval <= 54)
{
  $sig_vals['masc2_sr_oc_sig'] = "Average";
}
else if ($masc2_oc_tval >= 55 and $masc2_oc_tval <= 59)
{
  $sig_vals['masc2_sr_oc_sig'] = "High Average";
}
else if ($masc2_oc_tval >= 60 and $masc2_oc_tval <= 64)
{
  $sig_vals['masc2_sr_oc_sig'] = "Slightly Elevated";
}
else if ($masc2_oc_tval >= 65 and $masc2_oc_tval <= 69)
{
  $sig_vals['masc2_sr_oc_sig'] = "Elevated";
}
else if ($masc2_oc_tval >= 70)
{
  $sig_vals['masc2_sr_oc_sig'] = "Very Elevated";
}

if ($masc2_pst_tval <= 39)
{
  $sig_vals['masc2_sr_pst_sig'] = "Low";
}
else if ($masc2_pst_tval >= 40 and $masc2_pst_tval <= 54)
{
  $sig_vals['masc2_sr_pst_sig'] = "Average";
}
else if ($masc2_pst_tval >= 55 and $masc2_pst_tval <= 59)
{
  $sig_vals['masc2_sr_pst_sig'] = "High Average";
}
else if ($masc2_pst_tval >= 60 and $masc2_pst_tval <= 64)
{
  $sig_vals['masc2_sr_pst_sig'] = "Slightly Elevated";
}
else if ($masc2_pst_tval >= 65 and $masc2_pst_tval <= 69)
{
  $sig_vals['masc2_sr_pst_sig'] = "Elevated";
}
else if ($masc2_pst_tval >= 70)
{
  $sig_vals['masc2_sr_pst_sig'] = "Very Elevated";
}

if ($masc2_p_tval <= 39)
{
  $sig_vals['masc2_sr_p_sig'] = "Low";
}
else if ($masc2_p_tval >= 40 and $masc2_p_tval <= 54)
{
  $sig_vals['masc2_sr_p_sig'] = "Average";
}
else if ($masc2_p_tval >= 55 and $masc2_p_tval <= 59)
{
  $sig_vals['masc2_sr_p_sig'] = "High Average";
}
else if ($masc2_p_tval >= 60 and $masc2_p_tval <= 64)
{
  $sig_vals['masc2_sr_p_sig'] = "Slightly Elevated";
}
else if ($masc2_p_tval >= 65 and $masc2_p_tval <= 69)
{
  $sig_vals['masc2_sr_p_sig'] = "Elevated";
}
else if ($masc2_p_tval >= 70)
{
  $sig_vals['masc2_sr_p_sig'] = "Very Elevated";
}

if ($masc2_tr_tval <= 39)
{
  $sig_vals['masc2_sr_tr_sig'] = "Low";
}
else if ($masc2_tr_tval >= 40 and $masc2_tr_tval <= 54)
{
  $sig_vals['masc2_sr_tr_sig'] = "Average";
}
else if ($masc2_tr_tval >= 55 and $masc2_tr_tval <= 59)
{
  $sig_vals['masc2_sr_tr_sig'] = "High Average";
}
else if ($masc2_tr_tval >= 60 and $masc2_tr_tval <= 64)
{
  $sig_vals['masc2_sr_tr_sig'] = "Slightly Elevated";
}
else if ($masc2_tr_tval >= 65 and $masc2_tr_tval <= 69)
{
  $sig_vals['masc2_sr_tr_sig'] = "Elevated";
}
else if ($masc2_tr_tval >= 70)
{
  $sig_vals['masc2_sr_tr_sig'] = "Very Elevated";
}

if ($masc2_ha_tval <= 39)
{
  $sig_vals['masc2_sr_ha_sig'] = "Low";
}
else if ($masc2_ha_tval >= 40 and $masc2_ha_tval <= 54)
{
  $sig_vals['masc2_sr_ha_sig'] = "Average";
}
else if ($masc2_ha_tval >= 55 and $masc2_ha_tval <= 59)
{
  $sig_vals['masc2_sr_ha_sig'] = "High Average";
}
else if ($masc2_ha_tval >= 60 and $masc2_ha_tval <= 64)
{
  $sig_vals['masc2_sr_ha_sig'] = "Slightly Elevated";
}
else if ($masc2_ha_tval >= 65 and $masc2_ha_tval <= 69)
{
  $sig_vals['masc2_sr_ha_sig'] = "Elevated";
}
else if ($masc2_ha_tval >= 70)
{
  $sig_vals['masc2_sr_ha_sig'] = "Very Elevated";
}

# Calculate the Anxiety Probability Score.  Add a point for each of the 3 tvals >= 60: Separation Anxiety/Phobias,
#                GAD Index and Separation Anxiety: Total
$masc2_anx_prob = 0;
if ($masc2_sp_tval >= 60) $masc2_anx_prob++;
if ($masc2_gad_tval >= 60) $masc2_anx_prob++;
if ($masc2_sa_tval >= 60) $masc2_anx_prob++;



if ($masc2_anx_prob == 0)
{
  $sig_vals['masc2_sr_anx_sig'] = "Low Probability";
}
else if ($masc2_anx_prob == 1)
{
  $sig_vals['masc2_sr_anx_sig'] = "Borderline Probability";
}
else if ($masc2_anx_prob == 2)
{
  $sig_vals['masc2_sr_anx_sig'] = "High Probability";
}
else if ($masc2_anx_prob == 3)
{
  $sig_vals['masc2_sr_anx_sig'] = "Very High Probability";
}

$result_values = array($sp_val, $gad_val, $hr_val, $pf_val, $oc_val, $p_val, $tr_val, $ha_val,
                       $sat_val, $pst_val, $masc2_total, $inconsistancy_idx,
                       $masc2_total_tval, $masc2_sp_tval, $masc2_gad_tval, $masc2_sa_tval,
                       $masc2_hr_tval,
                       $masc2_pf_tval, $masc2_oc_tval,
                       $masc2_pst_tval, $masc2_p_tval,
                       $masc2_tr_tval, $masc2_ha_tval, $masc2_anx_prob);

### DEFINE RESULTS ###
# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
#$algorithm_results = array_combine($default_result_fields, $result_values, $sig_vals);
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
