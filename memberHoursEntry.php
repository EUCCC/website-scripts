<?php
namespace volunteerHoursEntry;
/**
 * Member Hours Update page/script for Experience Unlimited Joomla! website
 *
 * This script allows a member to enter volunteer hours
 * 
 * @package    volunteerHoursEntry_v1.4.php
 * @author     Jean Shih <jean1shih@gmail.com>
 * @author     Ben Bonham <bhbonham@gmail.com>
 * @copyright  2014,2015 Experience Unlimited
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    1.4
 */

/* Modification log:
 * -------------------------------------------------------------------------
 * 2015/04/15	bb	 v1.4 change "Enter" -> "Other", renumber tasks 1,2,3 instead of 0,1,2,
 * 							add Back button (2 places)
 * 2015/01/30	bb   v1.3 create from adminHoursUpdate1.3.php
 */

echo <<<EOS
<h2>   Volunteer Hours Entry v1.4  </h2>
EOS;

define('NUM_OTHER_TASK_MENUS', 3);  // # of dropdown menus for "Other Tasks"

echo '<form method="post">';

###########################################################################
$doc = \JFactory::getDocument();
$style = <<<EOSTYLE
.blabel {
	text-align:right;
	max-width:300px;
	padding:0px 10px;
	color: rgb(0,0,255);
    }
.submittext {
	font-family: Arial, Helvetica, sans-serif; 
	font-size: 16px; color:#000066;
	margin-left:16cm;
}
EOSTYLE;

$doc->addStyleDeclaration($style);
###########################################################################

#------------------------------------------------------------------------
function show_member_hours_balance($db, $userId )
{   
    if ( $userId )
    {
        $query = 
            "SELECT SUM(task_hours) FROM `eu_member_hours`
            WHERE $userId = `member_id`
            AND `task_date` > 
            ( SELECT `active_date` FROM `eu_members` 
            WHERE $userId = `member_id` );";
            $db->setQuery($query);
            $results= $db->loadResult();
        if( $results == '' )
            $results = '0';
        echo " <h4> Current net hours balance : &nbsp $results </h4> ";
    }
    else
    {
        echo 'no $userId=' . $userId . '!';
        echo 'FAIL, no userId';
    }
    return;
}
#------------------------------------------------------------------------
function load_tasks_array($db)
{
		// Populate list of tasks for dropdown menus below
	$query = "SELECT * FROM `eu_member_tasks` 
				WHERE display_type != 0
				ORDER BY task_name;";
	$db->setQuery($query);
	$tasklist = $db->loadObjectList();
	
	$taskArray = array();
	$taskArray['checkboxes'] = array();
	$taskArray['pulldowns'] = array();
	$taskArray['varhours'] = array();
	$taskArray['misc'] = array();
	foreach ($tasklist as $task) {
		switch ($task->display_type) {
			case 1:
				$taskArray['checkboxes'][] = $task;
				break;
			case 2:
				$taskArray['pulldowns'][] = $task;
				break;
			case 3:
				$taskArray['varhours'][] = $task;
				break;
			case 4:
				$taskArray['misc'][] = $task;
				break;
			otherwise:
				// do nothing (do not display)
		}
	}

	$_SESSION["taskArray"] = $taskArray;
    return;
}
#------------------------------------------------------------------------
function show_hours_entry_form($db ,$memId)
{
   	
	//$firstname = $member->first_name;
	//$lastname = $member->last_name;
	//echo "<h4>$firstname&nbsp$lastname </h4>";
	
	$taskArray = $_SESSION["taskArray"];
	
	// Populate list of tasks for dropdown menus below


	show_member_hours_balance($db, $memId );
	echo "<br/>";
	echo '<table>';
	// Checkboxes, for General Meeting, etc
	$task_input_ctr = 0;
	foreach ($taskArray['checkboxes'] as $task)	{
	    echo "<tr><td class='blabel'>{$task->task_name} (" . intval($task->default_task_hrs) . " h)</td>" .
	            '<td style="text-align:left; width:30%;">';
		echo "<input type='checkbox' value='True' name=task_checkboxes_0$task_input_ctr></td>";
		echo "</tr>";
		$task_input_ctr += 1;
	}
	echo "<tr><td>&nbsp</td></tr>";
	
	// Dropdown menus for task selection, for Board Meeting, etc.
	for ($task_input_ctr = 1; $task_input_ctr<=NUM_OTHER_TASK_MENUS; $task_input_ctr++) {
		$task_input_list[$task_input_ctr] = '';
		echo "<tr><td class='blabel'>Select Task $task_input_ctr</td>";
		echo "<td style='text-align:left; width:30%;'>";
		echo "<SELECT id=task_0$task_input_ctr  name=task_pulldowns_0$task_input_ctr > ";
		echo "<OPTION value=''>Select Task</OPTION>"; 
		foreach ($taskArray['pulldowns'] as $task)	{
			echo "<option value=$task->task_id > {$task->task_name} (" . intval($task->default_task_hrs) . " h)</option>";
			}
		echo "</SELECT></td>";
		echo "</tr>";
	}
	echo "<tr><td>&nbsp</td></tr>";
	
	// Variable-hours task entry, for Calling People, etc
	$task_input_ctr = 0;
	foreach ($taskArray['varhours'] as $task)	{
		$task_input_list[$task_input_ctr] = $task->task_id;
	    echo "<tr><td class='blabel'>{$task->task_name} - Enter hours spent on task</td>";
		echo '<td style="text-align:left; width:30%;">';
		echo "<select  name=task_varhours_0$task_input_ctr>";
		echo "<option value=''>Select Hours</option>";
		for ($num_hours=1; $num_hours<=20; $num_hours++) {
			echo "<option value='" . $task->hours_multiplier*$num_hours . "'>$num_hours h</option>";
		}
		echo " </select></td>";
		echo "</tr>";
		$task_input_ctr += 1;
	}
	echo "<tr><td>&nbsp</td></tr>";
	
	// Hours for Other Task/Tasks  
	$task_input_ctr = 0;
	foreach ($taskArray['misc'] as $task) {
		$task_input_list[$task_input_ctr] = $task->task_id;
		echo "<tr>";
		echo "<td class='blabel'>Other Task(s) - Enter Description</td>";
		echo '<td style="text-align:left; width:30%;">';
		echo "<input type='text' maxlength='150' size='30' name='task_misc_desc_0$task_input_ctr'></td>";
		echo "<td class='blabel'>Other Hours</td>";
		echo '<td style="text-align:left; width:30%;">';
		echo "<select  name=task_misc_0$task_input_ctr>";
		echo "<option value=''>Select Hours</option>";
		for ($num_hours=1; $num_hours<=20; $num_hours++) {
			echo "<option value='$num_hours'>$num_hours h</option>";
		}
		for ($num_hours=-1; $num_hours>=-5; $num_hours--) {
			echo "<option value='$num_hours'>$num_hours h</option>";
		}
		echo " </select></td>";
		echo "</tr>";
		$task_input_ctr += 1;
	}
	
	echo "</table>";

	echo '<br/><br/>	<input type="hidden" name="process" value="1"> 
		 <input name="Submit" type="submit" value="Add Hours"> ' .
	     '&nbsp<input type="button" value="Back" onClick=history.go(-1)>';

	return;
}
#------------------------------------------------------------------------
function update_member_hours_table($db, $memid, $taskid, $taskhrs, $taskdesc)
{
	$today = date("Y-m-d");
	
	// Create a new query object for inserting a row to eu_member_hours.
	if (is_null($taskdesc)) {
		$columns = array('member_id',
			'task_date',
			'task_id',
			'task_hours');	
		$values = array($memid,
			$db->quote($today), 
			$db->quote($taskid),
			$db->quote($taskhrs));
	} else {
		$columns = array('member_id',
			'task_date',
			'task_id',
			'task_hours',
			'misc_task_hr_desc');
		$values = array($memid,
			$db->quote($today), 
			$db->quote($taskid),
			$db->quote($taskhrs),
			$db->quote($taskdesc));
	}

	$query = $db->getQuery(true);
	$query
		->insert($db->quoteName('eu_member_hours'))
		->columns($db->quoteName($columns))
		->values(implode(',', $values));
	
	// Set the query using our newly populated query object and execute it.
	$db->setQuery($query);
	$db->query();
	return;
}
#------------------------------------------------------------------------
function add_hours_and_update_database_tables($db, $memId)
{
	$totalHrsAdded = 0;	
	$taskArray = $_SESSION["taskArray"];
	
	$success = false;
	try {
		$db->transactionStart();
		
		// get hours from checkboxes
		for ($ii=0; $ii<count($taskArray['checkboxes']); $ii++) {
			if (!empty($_POST["task_checkboxes_0$ii"])) {
				$task_id = $taskArray['checkboxes'][$ii]->task_id;
				$task_hours = $taskArray['checkboxes'][$ii]->default_task_hrs;
				update_member_hours_table($db, $memId, $task_id, $task_hours, null );
				$totalHrsAdded += $task_hours;
			}
		}
		
		// get hours from task pulldowns
		for ($ii=1; $ii<=NUM_OTHER_TASK_MENUS; $ii++) {
			$task_index = 'task_pulldowns_0' . $ii;
			if (!empty($_POST[$task_index])) {
				$task_id = trim($_POST[$task_index]);
				foreach ($taskArray['pulldowns'] as $task) {
					if ($task->task_id == $task_id) {
						$task_hours = $task->default_task_hrs;
						break;
					}
				}
				update_member_hours_table($db, $memId, $task_id, $task_hours, null);
				$totalHrsAdded += $task_hours;
			}
		}
		
		// get hours from hours pulldowns
		for ($ii=0; $ii<count($taskArray['varhours']); $ii++) {
			$task_hours = trim($_POST["task_varhours_0$ii"]);
			if (!empty($task_hours)) {
				$task_id = $taskArray['varhours'][$ii]->task_id;
				update_member_hours_table($db, $memId, $task_id, $task_hours, null);
				$totalHrsAdded += $task_hours;
			}
		}
		
		// get hours from misc fill-in-the-box
		for ($ii=0; $ii<count($taskArray['misc']); $ii++) {
			$task_hours = trim($_POST["task_misc_0$ii"]);
			if (!empty($task_hours)) {
				$task_id = $taskArray['misc'][$ii]->task_id;
				$task_desc = trim($_POST["task_misc_desc_0$ii"]);
				update_member_hours_table($db, $memId, $task_id, $task_hours, $task_desc );
				$totalHrsAdded += $task_hours; 
			}
		}
		
		$db->transactionCommit();		
		echo "<br/>Database updated<br/>";
		$success = true;
    } catch (\Exception $e) {
	    $db->transactionRollback();
	    echo "<br/>" . $e->getMessage() . "<br/><strong>-- database was not updated --</strong><br/>";
	    $totalHrsAdded = 0;
	}

	echo "<br/><br/><h4>   Hours Added : &nbsp&nbsp $totalHrsAdded </h4>";  

    // Display new net balance

    show_member_hours_balance($db, $memId );   
     
	echo '<input type="button" value="Back" onClick=history.go(-1)>';

	return $success;
}

/*========================================================================*
 * Main body.                                                             *
 *========================================================================*/

$db = \JFactory::getDBO();
$user = \JFactory::getUser();

$member_id = $user->id;

if  (!isset($_POST['process'] )) { 
	load_tasks_array($db);			  		 
	show_hours_entry_form($db, $member_id);
} elseif ($_POST['process'] == 1) { 
	add_hours_and_update_database_tables($db, $member_id);
} else {
	echo "<br/>How did we ever get here???<br/>";
} 
?>

</form> 
