<?php
/**

	NEO-Five Factory Inventory (FFI)
        The 5 factors are: Neuroticism, Extraversion, Openness to experience, Agreeableness, Conscientiousness
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
        - There are 60 questions that the participant fills out.
	- We also need a gender fields as the 61st field.  The values are 1 = Male, 2 = Female.

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "NEO-Five Factory Inventory (FFI)";
$this->module->emDebug("Scoring Title: " . $algorithm_summary);

# REQUIRED: Define an array of default result field_names to record the summary data
# The NEO-FFI scoring is as follows:
$default_result_fields = array(
        'neoscoren', 'neoscoree', 'neoscoreo', 'neoscorea', 'neoscorec',
        'neoscorent_gender', 'neoscoreet_gender', 'neoscoreot_gender', 'neoscoreat_gender', 'neoscorect_gender',
        'neoscorent', 'neoscoreet', 'neoscoreot', 'neoscoreat', 'neoscorect'
);

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$required_fields = array();
foreach (range(1,60) as $i) {
	array_push($required_fields, "neo_q$i");
}

# We also need gender since there are different look up tables for males and females
array_push($required_fields, "neo_gender");

# Override default input array with manual field names specified by user (optional)

if (!empty($manual_source_fields)) {
    if (count($manual_source_fields) == count($required_fields)) {
            foreach($manual_source_fields as $k => $field) {
            	if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
            		$required_fields[$k] = $field;
            		# $this->module->emDebug("Changing input field ".$k." to ".$field);
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

## VALIDATION ##
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
		return false;
	}
}

#$this->module->emDebug("This is manual result array: " . implode(',',$manual_result_fields));
#$this->module->emDebug("This is default result array: " . implode(',',$default_result_fields));

### IMPLEMENT RAW SCORING ###
# This raw scoring is very simple - just basic arthimatic for:
#         1. Neuroticism, 2. Extraversion, 3. Openness, 4. Agreeableness, 5. Conscientiousness
# This index is (question number - 1) since php starts at 0 and not 1
$q1 = $required_fields[0];
$q6 = $required_fields[5];
$q11 = $required_fields[10];
$q16 = $required_fields[15];
$q21 = $required_fields[20];
$q26 = $required_fields[25];
$q31 = $required_fields[30];
$q36 = $required_fields[35];
$q41 = $required_fields[40];
$q46 = $required_fields[45];
$q51 = $required_fields[50];
$q56 = $required_fields[55];
$neuroticism = (5-$src[$q1]) + ($src[$q6]-1) + ($src[$q11]-1) + (5-$src[$q16]) + ($src[$q21]-1) + ($src[$q26]-1) +
               (5-$src[$q31]) + ($src[$q36]-1) + ($src[$q41]-1) + (5-$src[$q46]) + ($src[$q51]-1) + ($src[$q56]-1);

$q2 = $required_fields[1];
$q7 = $required_fields[6];
$q12 = $required_fields[11];
$q17 = $required_fields[16];
$q22 = $required_fields[21];
$q27 = $required_fields[26];
$q32 = $required_fields[31];
$q37 = $required_fields[36];
$q42 = $required_fields[41];
$q47 = $required_fields[46];
$q52 = $required_fields[51];
$q57 = $required_fields[56];
$extraversion = ($src[$q2]-1) + ($src[$q7]-1) + (5-$src[$q12]) + ($src[$q17]-1) + ($src[$q22]-1) + (5-$src[$q27]) +
                ($src[$q32]-1) + ($src[$q37]-1) + (5-$src[$q42]) + ($src[$q47]-1) + ($src[$q52]-1) + (5-$src[$q57]);

$q3 = $required_fields[2];
$q8 = $required_fields[7];
$q13 = $required_fields[12];
$q18 = $required_fields[17];
$q23 = $required_fields[22];
$q28 = $required_fields[27];
$q33 = $required_fields[32];
$q38 = $required_fields[37];
$q43 = $required_fields[42];
$q48 = $required_fields[47];
$q53 = $required_fields[52];
$q58 = $required_fields[57];
$openness = ($src[$q3]-1) + ($src[$q8]-1) + ($src[$q13]-1) + (5-$src[$q18]) + (5-$src[$q23]) + (5-$src[$q28]) +
            (5-$src[$q33]) + ($src[$q38]-1) + ($src[$q43]-1) + (5-$src[$q48]) + ($src[$q53]-1) + ($src[$q58]-1);

$q4 = $required_fields[3];
$q9 = $required_fields[8];
$q14 = $required_fields[13];
$q19 = $required_fields[18];
$q24 = $required_fields[23];
$q29 = $required_fields[28];
$q34 = $required_fields[33];
$q39 = $required_fields[38];
$q44 = $required_fields[43];
$q49 = $required_fields[48];
$q54 = $required_fields[53];
$q59 = $required_fields[58];
$agreeableness = ($src[$q4]-1) + (5-$src[$q9]) + (5-$src[$q14]) + (5-$src[$q19]) + (5-$src[$q24]) + ($src[$q29]-1) +
                 ($src[$q34]-1) + (5-$src[$q39]) + (5-$src[$q44]) + ($src[$q49]-1) + (5-$src[$q54]) + (5-$src[$q59]);

$q5 = $required_fields[4];
$q10 = $required_fields[9];
$q15 = $required_fields[14];
$q20 = $required_fields[19];
$q25 = $required_fields[24];
$q30 = $required_fields[29];
$q35 = $required_fields[34];
$q40 = $required_fields[39];
$q45 = $required_fields[44];
$q50 = $required_fields[49];
$q55 = $required_fields[54];
$q60 = $required_fields[59];
$conscientiousness = ($src[$q5]-1) + ($src[$q10]-1) + (5-$src[$q15]) + ($src[$q20]-1) + ($src[$q25]-1) + (5-$src[$q30]) +
                     ($src[$q35]-1) + ($src[$q40]-1) + (5-$src[$q45]) + ($src[$q50]-1) + (5-$src[$q55]) + ($src[$q60]-1);

#$this->module->emDebug("These are the results: N=$neuroticism, E=$extraversion, O=$openness, A=$agreeableness, C=$conscientiousness ");

# This are the arrays for the lookup tables
$male_n_table = array(
		 1 => 25, 2 => 26, 3 => 27, 4 => 29, 5 => 30, 6 => 32, 7 => 33, 8 => 34, 9 => 36, 10 => 37,
		11 => 39, 12 => 40, 13 => 41, 14 => 43, 15 => 44, 16 => 46, 17 => 47, 18 => 48, 19 => 50, 20 => 51,
		21 => 53, 22 => 54, 23 => 55, 24 => 57, 25 => 58, 26 => 60, 27 => 61, 28 => 63, 29 => 64, 30 => 65,
		31 => 67, 32 => 68, 33 => 70, 34 => 71, 35 => 72, 36 => 74, 37 => 75
		);

$male_e_table = array( 
		12 => 25, 13 => 27, 14 => 28, 15 => 30, 16 => 32, 17 => 33, 18 => 35, 19 => 37, 20 => 38, 21 => 40,
		22 => 41, 23 => 43, 24 => 45, 25 => 46, 26 => 48, 27 => 50, 28 => 51, 29 => 53, 30 => 55, 31 => 56,
		32 => 58, 33 => 60, 34 => 61, 35 => 63, 36 => 64, 37 => 66, 38 => 68, 39 => 69, 40 => 71, 41 => 73,
		42 => 74, 43 => 75
		);

$male_o_table = array( 
		11 => 25, 12 => 26, 13 => 27, 14 => 29, 15 => 30, 16 => 32, 17 => 34, 18 => 35, 19 => 37, 20 => 38,
		21 => 40, 22 => 42, 23 => 43, 24 => 45, 25 => 46, 26 => 48, 27 => 50, 28 => 51, 29 => 53, 30 => 54,
		31 => 56, 32 => 57, 33 => 59, 34 => 61, 35 => 62, 36 => 64, 37 => 65, 38 => 67, 39 => 69, 40 => 70,
		41 => 72, 42 => 73, 43 => 75
		);

$male_a_table = array(
		16 => 25, 17 => 27, 18 => 29, 19 => 31, 20 => 32, 21 => 34, 22 => 36, 23 => 38, 24 => 39, 25 => 41,
		26 => 43, 27 => 45, 28 => 46, 29 => 48, 30 => 50, 31 => 52, 32 => 54, 33 => 55, 34 => 57, 35 => 59,
		36 => 61, 37 => 62, 38 => 64, 39 => 66, 40 => 68, 41 => 69, 42 => 71, 43 => 73, 44 => 75
		);


$male_c_table = array( 
		17 => 25, 18 => 26, 19 => 28, 20 => 30, 21 => 31, 22 => 33, 23 => 35, 24 => 36, 25 => 38, 26 => 40,
		27 => 41, 28 => 43, 29 => 45, 30 => 46, 31 => 48, 32 => 50, 33 => 51, 34 => 53, 35 => 55, 36 => 56,
		37 => 58, 38 => 60, 39 => 61, 40 => 63, 41 => 65, 42 => 66, 43 => 68, 44 => 70, 45 => 71, 46 => 73,
		47 => 75
		);

$female_n_table = array(
		 2 => 25,  3 => 26,  4 => 27,  5 => 28,  6 => 29,  7 => 31,  8 => 32,  9 => 33, 10 => 35, 11 => 36, 
		12 => 37, 13 => 38, 14 => 40, 15 => 41, 16 => 42, 17 => 43, 18 => 45, 19 => 46, 20 => 47, 21 => 48, 
		22 => 50, 23 => 51, 24 => 52, 25 => 54, 26 => 55, 27 => 56, 28 => 57, 29 => 59, 30 => 60, 31 => 61, 
		32 => 62, 33 => 64, 34 => 65, 35 => 66, 36 => 67, 37 => 69, 38 => 70, 39 => 71, 40 => 73, 41 => 74,
		42 => 75
		);

$female_e_table = array( 
		13 => 25, 14 => 26, 15 => 27, 16 => 29, 17 => 31, 18 => 32, 19 => 34, 20 => 35, 21 => 37, 22 => 39, 
		23 => 40, 24 => 42, 25 => 44, 26 => 45, 27 => 47, 28 => 48, 29 => 50, 30 => 52, 31 => 53, 32 => 55, 
		33 => 56, 34 => 58, 35 => 60, 36 => 61, 37 => 63, 38 => 65, 39 => 66, 40 => 68, 41 => 69, 42 => 71, 
		43 => 73, 44 => 74, 45 => 75
		);

$female_o_table = array( 
		14 => 25, 15 => 27, 16 => 29, 17 => 30, 18 => 32, 19 => 33, 20 => 35, 21 => 37, 22 => 38, 23 => 40,
		24 => 41, 25 => 43, 26 => 45, 27 => 46, 28 => 48, 29 => 50, 30 => 51, 31 => 53, 32 => 54, 33 => 56,
		34 => 58, 35 => 59, 36 => 61, 37 => 62, 38 => 64, 39 => 66, 40 => 67, 41 => 69, 42 => 70, 43 => 72,
		44 => 74, 45 => 75
		);

$female_a_table = array(
		19 => 25, 20 => 26, 21 => 28, 22 => 29, 23 => 31, 24 => 33, 25 => 35, 26 => 36, 27 => 38, 28 => 40,
		29 => 42, 30 => 44, 31 => 45, 32 => 47, 33 => 49, 34 => 51, 35 => 52, 36 => 54, 37 => 56, 38 => 58, 
		39 => 59, 40 => 61, 41 => 63, 42 => 65, 43 => 66, 44 => 68, 45 => 70, 46 => 72, 47 => 73, 48 => 75
		);


$female_c_table = array( 
		16 => 25, 17 => 26, 18 => 27, 19 => 29, 20 => 30, 21 => 32, 22 => 33, 23 => 35, 24 => 36, 25 => 38,
		26 => 40, 27 => 41, 28 => 43, 29 => 44, 30 => 46, 31 => 47, 32 => 49, 33 => 50, 34 => 52, 35 => 53,
		36 => 55, 37 => 56, 38 => 58, 39 => 60, 40 => 61, 41 => 63, 42 => 64, 43 => 66, 44 => 67, 45 => 69,
		46 => 70, 47 => 72, 48 => 73
		);


$combo_n_table = array(
		 1 => 25,  2 => 26,  3 => 27,  4 => 28,  5 => 29,  6 => 31,  7 => 32,  8 => 33,  9 => 35, 10 => 36,
		11 => 37, 12 => 39, 13 => 40, 14 => 41, 15 => 42, 16 => 44, 17 => 45, 18 => 46, 19 => 48, 20 => 49,
		21 => 50, 22 => 52, 23 => 53, 24 => 54, 25 => 55, 26 => 57, 27 => 58, 28 => 59, 29 => 61, 30 => 62,
		31 => 63, 32 => 65, 33 => 66, 34 => 67, 35 => 68, 36 => 70, 37 => 71, 38 => 72, 39 => 74, 40 => 75
		);

$combo_e_table = array( 
		13 => 25, 14 => 27, 15 => 29, 16 => 30, 17 => 32, 18 => 34, 19 => 35, 20 => 37, 21 => 38, 22 => 40, 
		23 => 42, 24 => 43, 25 => 45, 26 => 46, 27 => 48, 28 => 50, 29 => 51, 30 => 53, 31 => 55, 32 => 56, 
		33 => 58, 34 => 59, 35 => 61, 36 => 63, 37 => 64, 38 => 66, 39 => 67, 40 => 69, 41 => 71, 42 => 72, 
		43 => 74, 44 => 75
		);

$combo_o_table = array( 
		12 => 25, 13 => 26, 14 => 27, 15 => 29, 16 => 30, 17 => 32, 18 => 33, 19 => 35, 20 => 37, 21 => 38,
		22 => 40, 23 => 41, 24 => 43, 25 => 45, 26 => 46, 27 => 48, 28 => 49, 29 => 51, 30 => 53, 31 => 54,
		32 => 56, 33 => 57, 34 => 59, 35 => 60, 36 => 62, 37 => 64, 38 => 65, 39 => 67, 40 => 68, 41 => 70,
		42 => 72, 43 => 73, 44 => 75
		);

$combo_a_table = array(
		17 => 25, 18 => 27, 19 => 28, 20 => 30, 21 => 32, 22 => 33, 23 => 35, 24 => 37, 25 => 38, 26 => 40,
		27 => 42, 28 => 43, 29 => 45, 30 => 47, 31 => 48, 32 => 50, 33 => 52, 34 => 53, 35 => 55, 36 => 57, 
		37 => 58, 38 => 60, 39 => 62, 40 => 63, 41 => 65, 42 => 67, 43 => 68, 44 => 70, 45 => 72, 46 => 73,
		47 => 75
		);


$combo_c_table = array(
 		17 => 25, 18 => 27, 19 => 29, 20 => 30, 21 => 32, 22 => 33, 23 => 35, 24 => 37, 25 => 38, 26 => 40,
		27 => 41, 28 => 43, 29 => 44, 30 => 46, 31 => 48, 32 => 49, 33 => 51, 34 => 52, 35 => 54, 36 => 56, 
		37 => 57, 38 => 59, 39 => 60, 40 => 62, 41 => 63, 42 => 65, 43 => 67, 44 => 68, 45 => 70, 46 => 71,
		47 => 73, 48 => 75
		);

# This is the function that will be used to lookup the t values - one based on gender and the other not
function lookup_tvalue($tvalue_table, $raw_score) {

	$keys = array_keys($tvalue_table);
	$min_value = min($keys);
	$max_value = max($keys);

	if ($raw_score <= $min_value) {
		$values = array_values($tvalue_table);
		$result = "<=" . min($values);
	} elseif ($raw_score >= $max_value) {
		$values = array_values($tvalue_table);
		$result = ">=" . max($values);
	} elseif (isset($tvalue_table[$raw_score])) {
		$result = $tvalue_table[$raw_score];
	} else {
		$result = NULL;
	}
	return $result;
}

# Now lookup the t values based on gender - there are different tables for males versus females
# For gender 1=Male, 2=Female. It is the last input field that is required.
# Find the name of the gender field and then retrieve the value.
$gender = $required_fields[60];
if ($src[$gender] == 1) {
	$neuro_t_gender = lookup_tvalue($male_n_table, $neuroticism);
	$extra_t_gender = lookup_tvalue($male_e_table, $extraversion);
	$open_t_gender = lookup_tvalue($male_o_table, $openness);
	$agree_t_gender = lookup_tvalue($male_a_table, $agreeableness);
	$consci_t_gender = lookup_tvalue($male_c_table, $conscientiousness);
} elseif ($src[$gender] == 2) {
	$neuro_t_gender = lookup_tvalue($female_n_table, $neuroticism);
	$extra_t_gender = lookup_tvalue($female_e_table, $extraversion);
	$open_t_gender = lookup_tvalue($female_o_table, $openness);
	$agree_t_gender = lookup_tvalue($female_a_table, $agreeableness);
	$consci_t_gender = lookup_tvalue($female_c_table, $conscientiousness);
}

# There are also combo tables regardless of gender
$neuro_t = lookup_tvalue($combo_n_table, $neuroticism);
$extra_t = lookup_tvalue($combo_e_table, $extraversion);
$open_t = lookup_tvalue($combo_o_table, $openness);
$agree_t = lookup_tvalue($combo_a_table, $agreeableness);
$consci_t = lookup_tvalue($combo_c_table, $conscientiousness);

# Package all the results up together to send back
$raw_results = array($neuroticism, $extraversion, $openness, $agreeableness,$conscientiousness,
                     $neuro_t_gender, $extra_t_gender, $open_t_gender, $agree_t_gender, $consci_t_gender,
                     $neuro_t, $extra_t, $open_t, $agree_t, $consci_t); 

### DEFINE RESULTS ###
# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $raw_results);

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

