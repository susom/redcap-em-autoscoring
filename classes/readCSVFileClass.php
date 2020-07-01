<?php
namespace Stanford\Autoscore;
/** @var \Stanford\Autoscore\Autoscore $module */


class ReadCSVFileClass
{
	public function returnResults($filename) {

		$result = array();

		// Use this to detect end of line - especially when there are discrepancies
 		// between Macs and PCs
		ini_set("auto_detect_line_endings", true);

		// Open a handle to the file
		$handle = fopen($filename, "r");
		if (empty($handle) or is_null($handle)) {
			// Couldn't open a handle, something is wrong with the file
			//$module->emError("Error opening file = " . $filename . ". Return code is: " . $handle . "\n");
			return null;

		} else {

			//  Read the file, line-by-line
			$nlines = 0;
			$nfields = 0;

			while (($csvData = fgetcsv($handle)) !== FALSE)  {

                		// The first line is headers so keep a list of them
				if ($nlines == 0) {
					$nfields = count($csvData);
					$result_hdrs = $csvData;

					// It is a header so start our array
					for ($ifield=0; $ifield < $nfields; $ifield++) {
						$result[$csvData[$ifield]] = array();
					}


				} else {

					// Add this line to the array
					for ($ifield=0; $ifield < $nfields; $ifield++) {
						$num_elements = array_push($result[$result_hdrs[$ifield]], $csvData[$ifield]);
					}

				}				
				
				$nlines++;

			}

		}

		// Close the file
		fclose($handle);

		//return the data from the file
		return $result;
	}
	
}

?>
