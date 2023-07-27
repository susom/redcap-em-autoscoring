# Autoscoring
This REDCap External Module will help projects automate scoring for assessments and will perform lookup table
values that are too complex for REDCap calculated fields. Each algorithm is created based on standard scoring
calculations so each algorithm can be used for multiple projects.

## How it works
Currently, each algorithm is created and a configuration is setup for each scoring instance. There is a master
scoring configuration project which holds all the necessary information needed to perform the calculation.
These scoring algorithms are called when an assessment form is saved and will perform the calculation and
automate saving the results in the project.

## Setup
There is a master autoscoring configuration project where each project that uses autoscoring uses to enter
the autoscoring data necessary to run the algorithm.  Also, the result status of each score will be updated
to this record which will provide some insight into scoring status.

In addition to the record in the autoscoring configuration project, the configuration file for this External
Module needs to be configured in each project.  There are three entries that are needed for each autoscoring configuration: 1)
Name of the Configuration, 2) Record ID of the Master Scoring configuration project that holds the autoscoring
 information for this algorithm and 3) the form name, when saved, will initiate the autoscoring.


Change Log:

July 27, 2023 - Added PSIv4 Short Form algorithm and lookup tables

June 15, 2023 - Add tables for ages 0-3 to PSIv4
