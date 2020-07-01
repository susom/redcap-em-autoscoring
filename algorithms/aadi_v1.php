<?php
/**

	Adolescent Drinking Index (ADI) for the Turner Project
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results
        - There are 2 types of inputs: radio buttons and checkboxes. All radio button questions will just be
	-   added to the total.  The checkboxes will be add based on the selected values using a multiplier
	- Once the total is summed, the Full Assessment field will be set to Yes if the total is >=37.        

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "AADIS Scoring v1 - This algorithm calculates an AADI Total Score for Section A and one for Section B of the questionnaire. If the value of Section B is greater than or equal to 37, the Full Assessment field will be set to Yes.";

# REQUIRED: Define an array of default result field_names to record the summary data
$default_result_fields = array('aadi_sectiona_total', 'aadi_sectionb_total', 'aadi_assessment');

# REQUIRED: Define an array of fields that must be present for this algorithm to run
# The inputs for this scoring algorithm is a little complicated since there are checkboxes questions
#    which have to be accounted for individually.
$required_fields = array();
foreach (range(1,13) as $counter) {
	$required_fields[$counter] = 'aadi_q'.$counter;
} 

# This array defines how many possible answers there are for this question.  If it is a radio button,
# there will be 1 possible answer but if it is a checkbox, there maybe multiple answers.
$open_field_defn = array(
		1 => array(1),
		2 => array(1),
		3 => array(1,2,3,4,5),
		4 => array(1,2,3,4,5),
		5 => array(1,2,3,4,5),
		6 => array(1),
		7 => array(1,2,3,4,5),
		8 => array(1,2,3,4,5),
		9 => array(1),
		10 => array(1,2,3,4,5),
		11 => array(1,2,3,4,5,6),
		12 => array(0,2,3,4,5,6,7,8),
		13 => array(0,1,3,4,5,6),
		14 => array(0,2,3,4,5));
$open_fields = array();
$counter = 1;
foreach ($open_field_defn as $i => $fieldnums) {
	$field_array = $open_field_defn[$i];
	//$this->module->emDebug("This is question: $i and array = " . implode(',', $field_array));

	foreach($field_array as $k => $num) {
		if (count($field_array) > 1) {
			$open_fields[$counter++] = 'aadi_open_q'.$i.'___'.$num;
		} else {
			$open_fields[$counter++] = 'aadi_open_q'.$i;
		}
	}
}
$required_fields = array_merge($required_fields, $open_fields);
//$this->module->emDebug("Required fields: " . implode(',',$open_fields));

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
                        if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
                        	$required_fields[$k] = $field;
							$this->module->emDebug("For the field $k, these are the values $field");
                        }
                }
                $log[] = "Overriding required fields with ". implode(',',$manual_source_fields);
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
				$this->module->emDebug('changing $k to $field');
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
$this->module->emDebug("Source fields: " . json_encode($src));


// Turns out Section B (Open fields) are not always filled out so calculate the score for Section A even if B fields are not filled in.
/*
$missing_fields = array_diff($required_fields, $source_fields);
if ($missing_fields) {
	$msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "]";
	$algorithm_log[] = $msg;
	$this->module->emError($msg);
	$this->module->emDebug("Missing Fields: " . $missing_fields);
//	return;	//Since this is being called via include, the main script will continue to process other algorithms
}
*/

# For all the fields that were filled in, figure out the values based on the checkbox values
$source_values = array();
foreach ($required_fields as $i =>$rf) {
	$index = $i + 1;  // to account for arrays starting at 0 instead of 1
	if (is_numeric($src[$rf]))  {
		$selection = $src[$rf];
		if (strpos($rf, '___') === FALSE) {
	   		$source_values[$index] = $selection;
		} else {
			// If this is a checkbox and the value comes through as 0, make it null so we distinguish between 0 and NULL
			if ($selection <> 0) {
				$mult = substr($rf, -1);
	   			$source_values[$index] = $selection*$mult;
			} else {
				$source_values[$index] = NULL;
			}
		}
	}
}

### IMPLEMENT SCORING ###
# Calculate the AADI Scores
# There is a score for Section A which are the first 13 questions (all radio buttons).  
# Then we sum up Section B which are the next 14 questions but many of those questions are checkboxes
#   which may have multiple values.  
# So for section A, add the first 13 values and use everything else for section B.
# All participants have to fill out section A but often they don't fill out section B.
# Also, send back 1 (Yes) if the Section B Total is >= 37, otherwise send back 0 (No) for Full Assessment
$sectiona = range(1, 13);

$sectiona_values = array_intersect_key($source_values, array_flip($sectiona));
$sectiona_count = array_count_values($sectiona_values);
$sectiona_result = (empty($sectiona_count) ? NULL : array_sum($sectiona_values));
$this->module->emDebug("Section A count values array = " . implode(',',$sectiona_count));

$sectionb_values = array_diff_key($source_values, array_flip($sectiona));
$this->module->emDebug("Section B values array = " . implode(',', $sectionb_values));
$sectionb_result = NULL;
foreach ($sectionb_values as $i => $value) {
	if (isset($value)) {
		$sectionb_result = $sectionb_result + $value;
	}
}

$result_values['section_a'] = $sectiona_result;
$result_values['section_b'] = $sectionb_result;
$assessment = (empty($sectionb_result) ? NULL : ($sectionb_result >= 37 ? 1 : 0));
$result_values['assessment'] = $assessment;

### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $result_values);

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
