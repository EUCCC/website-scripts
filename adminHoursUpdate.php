<?php
/**
 * Admin Hours Update page/script for Experience Unlimited Joomla! website
 *
 * This script allows an administrator to enter volunteer hours for a member 
 * 
 * PHP version 5
 * 
 * @category  EUAdminScripts
 * @package   AdminHoursUpdate
 * @author    Jean Shih <jean1shih@gmail.com>
 * @author    Ben Bonham <bhbonham@gmail.com>
 * @copyright 2014-2015 Experience Unlimited
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   1.5
 * @link      https://github.com/EUCCC/website-scripts/blob/master/adminHoursUpdate.php
 */

/* Modification log:
 * -------------------------------------------------------------------------
 * 2015/05/21	bb	1.5	add comments, don't pass $db to functions
 * 2015/04/15	bb	1.4 renumber tasks starting at #1, change Enter to Other
 * 						added Back buttons (x2)
 * 2015/01/26	bb	1.3 use database column to determine task display format
 * 2015/01/09	bb	1.2 change to radio button format, set database
 * 						update to (all-or-none) transaction, move some linear
 * 						logic into functions for clarity, layout form as table
 *						(regressive -- should be updated to css), paginate long
 *						tables
 * 2014/09/30   js	1.0 initial release of Admin Volunteer Hours Entry
 */

namespace AdminHoursUpdate;

echo <<<EOS
<h2>   Admin Hours Update v1.5  </h2>
EOS;

/*
 * Displaying query result table that has too many rows of bogs down the 
 * sql server and may cause a timeout, so the table can be paginated by
 * setting BLOCKSIZE, which is the max # of member entries to display on one page
 */
define('BLOCKSIZE', 30);
define('NUM_OTHER_TASK_MENUS', 3);  // # of dropdown menus for "Other Tasks"

// task_codes for specific tasks (from eu_member_tasks table)
define('GENERAL_MEETING_CODE', 2);
define('CALLING_PEOPLE_CODE', 16);
define('MISC_TASK_CODE', 20);

// members get double credit for calling people or resume review
define('CALLING_PEOPLE_HOURS_MULTIPLIER', 2);

// 
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
// 

// ------------------------------------------------------------------------
/**
 * Query database for hours tallied since the active date for the member
 * 
 * @param int $userId member ID of user
 * 
 * @return void
 */ 
function showMemberHoursBalance($userId )
{   
    $db = \JFactory::getDBO();
    if ($userId ) {
        $query
            = "SELECT SUM(task_hours) FROM `eu_member_hours`
            WHERE $userId = `member_id`
            AND `task_date` > 
            ( SELECT `active_date` FROM `eu_members` 
            WHERE $userId = `member_id` );";
            $db->setQuery($query);
            $results= $db->loadResult();
        if ($results == '' ) {
            $results = '0'; 
        }
        echo " <h4> Current net hours balance : &nbsp $results </h4> ";
    } else {
        echo 'no $userId=' . $userId . '!';
        echo 'FAIL, no userId';
    }
    return;
}
// ------------------------------------------------------------------------
/**
 * Show form fields for search
 * 
 * @return void
 */ 
function displayBlankSearchForm()
{
     // Display search fields:  first name, last name and Email 
    echo '<table><tr>';
    // First name 
    echo '<td class="blabel">First&nbspName</td>' .
        '<td align="left"><input type="text" size="20" max="30" name="fname" ' .
        '></td>';

    // Last name 
    echo '<td class="blabel">Last&nbspName</td>' .
        '<td align="left"><input type="text" size="20" max="30" name="lname" ' .
        '></td>';

    // Email address 
    echo '<td class="blabel">Email</td>' .
        '<td align="left"><input type="text" size="20" max="40" name="email" ' .
        '></td>';

    echo '</tr></table>';       
    echo '	<input type="hidden" name="process" value="1"> 
		 <input name="Search" type="submit" value="Search"> ';
    return;
}
// ------------------------------------------------------------------------
/**
 * Build and execute query to get member information from database
 * 
 * @return array $members  array of query objects with member data
 */ 
function buildAndExecuteSearchQuery()
{
    $db = \JFactory::getDBO();
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);

    $query = $db->getQuery(true);
    $query
        ->select($db->quoteName('mbr.member_id', 'mID'))
        ->select($db->quoteName('mbr.first_name', 'euF'))
        ->select($db->quoteName('mbr.last_name', 'euL'))
        ->select($db->quoteName('mbr.email_address', 'Email'))
        ->select($db->quoteName('mbr.home_phone', 'euH'))
        ->select($db->quoteName('mbr.mobile_phone', 'euM'))
        ->select($db->quoteName('ms.member_status_desc', 'euS'))
        ->from($db->quoteName('eu_members', 'mbr'))
        ->join(
            'LEFT', $db->quotename('eu_member_statuses', 'ms') .
            ' ON ' . $db->quotename('ms.member_status') .
            ' = ' . $db->quotename('mbr.status')
        )
        ->where($db->quotename('mbr.status') . ' IN ("A")')
        ->order($db->quoteName('mbr.last_name'));

    if (!empty($fname)) {
        $fname = '%' . $db->escape($fname, true) . '%';
        $query->where(
            $db->quoteName('mbr.first_name') .
            ' LIKE ' . $db->quote($fname, false)
        );
    }
    if (!empty($lname)) {
        $lname = '%' . $db->escape($lname, true) . '%';
        $query->where(
            $db->quoteName('mbr.last_name') .
            ' LIKE ' . $db->quote($lname, false)
        );
    }
    if (!empty($email)) {
        $email = '%' . $db->escape($email, true) . '%';
        $query->where(
            $db->quoteName('mbr.email_address') .
            ' LIKE ' . $db->quote($email, false)
        );
    }

    $db->setQuery($query);

    $db->query();
    $cnt = $db->getNumRows();

    // determine which rows of search should be displayed on current page 
    $startrow = $_SESSION['startrow'];
    if (isset($_POST['change_block'])) {
        $action = $_POST['change_block'];
    } else {
        $action = '';
    }

    if (!empty($action) and ($action == "Next block")) {
        $startrow = $startrow + BLOCKSIZE;
    } elseif (!empty($action) and ($action == "Previous block")) {
        $startrow = $startrow - BLOCKSIZE;
    } else {
        $startrow = 0;        // this is a new search 
    }
    $_SESSION['startrow'] = $startrow;

    echo '<br>Search returns ' . $cnt . ' entries';
    $db->setQuery($query, $startrow, BLOCKSIZE);
    $members = $db->loadObjectList();

    if ($cnt>0) {
        echo '-- now displaying entries ' . (1+$startrow) . ' through ' . 
            ($startrow + count($members)) . '<br/>';
    }

    // show buttons to display next and/or previous blocks of rows if needed
    if ($startrow - BLOCKSIZE >= 0) {
        echo '<input type="submit" value="Previous block" name="change_block">';
    }
    if ($startrow + count($members) < $cnt) {
        echo '<input type="submit" value="Next block" name="change_block">';
    }

    echo "<input type='hidden' name='fname' value=$_POST[fname]>";
    echo "<input type='hidden' name='lname' value=$_POST[lname]>";
    echo "<input type='hidden' name='email' value=$_POST[email]>";
    echo "<input type='hidden' name='email_address' value=$_POST[email]>";

    return $members;
}
// ------------------------------------------------------------------------
/**
 * Display table with member query results
 * 
 * @param array $members array of member data objects
 * 
 * @return void
 */ 
function displayManyMembersTable($members)
{
    // Display column headings
    echo "<br><table border=1>";
    echo '<tr style="background-color:#EBEBEB;"><th>Select</th><th>First Name</th>' .
        '<th>Last Name</th><th>Email</th><th>Status</th></tr>';

    // column details
    $table_of_ids = array();
    $i = 0;
    foreach ($members as $member) {
        $tr = $i % 2 == 0 ? '<tr style="background-color:#EBEBEB;">' : '<tr>';
        echo $tr;
        echo "<td align='center' width=5% ><input type='radio' " .
        " name='member_radio' value=$i></td>" .
        "<td width=20% align=left height=30 >".$member->euF."</td>".
        "<td width=20% align=left height=30 >".$member->euL."</td>".
        "<td width=20% align=left height=30 >".$member->Email."</td>".
        "<td width=10% align=center height=30 >".$member->euS."</td> ";
        echo  "</tr>";
        $table_of_ids[$i] = $member->mID;
        $i++;

    }
    echo "</table>";
    echo "<br />";

    echo '<br/>';
    echo '<input name="Select" type="submit" value="Select">' .
    '&nbsp<input name=back type=button value=Back onClick=history.go(-1)>';
    echo '<br/>';

    // put this (confidential information) in session variable
    $_SESSION['table_of_ids'] = $table_of_ids;
    echo '<input type="hidden" name="process" value="2">';
    return;
}
// ------------------------------------------------------------------------
/**
 * Query database for member task information, and store this in $taskArray session
 *     variable
 * 
 * @return void
 */ 
function loadTasksArray()
{
    // Populate list of tasks for dropdown menus below
    $db = \JFactory::getDBO();
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
// ------------------------------------------------------------------------
/**
 * Display unfilled form for entry of volunteer hours. Tasks and descriptions 
 * used to create the form are taken from $taskArray session variable.
 * 
 * @param object $member object with information of member to update
 * 
 * @return void
 */ 
function showHoursEntryForm($member)
{
    $db = \JFactory::getDBO();
       $memId = $member->member_id;
    $firstname = $member->first_name;
    $lastname = $member->last_name;

    $taskArray = $_SESSION["taskArray"];
    
    // Populate list of tasks for dropdown menus below

    echo "<h4>$firstname&nbsp$lastname </h4>";

    showMemberHoursBalance($memId);
    echo "<br/>";
    echo '<table>';
    // Checkboxes, for General Meeting, etc
    $task_input_ctr = 0;
    foreach ($taskArray['checkboxes'] as $task) {
        echo "<tr><td class='blabel'>{$task->task_name} (" . 
        intval($task->default_task_hrs) . "&nbsph)</td>" .
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
        foreach ($taskArray['pulldowns'] as $task) {
            echo "<option value=$task->task_id > {$task->task_name} (" . 
            intval($task->default_task_hrs) . " h)</option>";
        }
        echo "</SELECT></td>";
        echo "</tr>";
    }
    echo "<tr><td>&nbsp</td></tr>";
    
    // Variable-hours task entry, for Calling People, etc
    $task_input_ctr = 0;
    foreach ($taskArray['varhours'] as $task) {
        $task_input_list[$task_input_ctr] = $task->task_id;
        echo "<tr><td class='blabel'>{$task->task_name} - Enter hours spent on task</td>";
        echo '<td style="text-align:left; width:30%;">';
        echo "<select  name=task_varhours_0$task_input_ctr>";
        echo "<option value=''>Select Hours</option>";
        for ($num_hours=1; $num_hours<=20; $num_hours++) {
            echo "<option value='" . $task->hours_multiplier*$num_hours . 
            "'>$num_hours h</option>";
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
        echo "<input type='text' maxlength='150' size='30' " .
        "name='task_misc_desc_0$task_input_ctr'></td>";
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

    echo "<input name='member_id' type='hidden'	value='$memId' size=10>";         
    echo '<br/><br/>';
    echo '<input type="hidden" name="process" value="4">';
    echo '<input name="Submit" type="submit" value="Add Hours"> ' .
    '&nbsp<input name=back type=button value=Back onClick=history.go(-1)>';
        
    return;
}
// ------------------------------------------------------------------------
/**
 * Build and execute query to get member information from database
 * 
 * @return object $member  query object with member data
 */ 
function buildAndExecuteMemberQuery()
{
    $db = \JFactory::getDBO();
    $index_of_id =  $_POST['member_radio'];
    $member_id = (int) $_SESSION['table_of_ids'][$index_of_id];
    $_SESSION['member_id'] = $member_id;
    
    $query = $db->getQuery(true);
    $query
        ->select($db->quoteName('mbr.member_id'))
        ->select($db->quoteName('mbr.first_name'))
        ->select($db->quoteName('mbr.last_name'))
        ->from($db->quoteName('eu_members', 'mbr'))
        ->where($db->quoteName('mbr.member_id') . ' = ' . $member_id);

    $db->setQuery($query);
    $member = $db->loadObject();

    return $member;
}
// ------------------------------------------------------------------------
/**
 * Update database with a new task and hours
 * 
 * @param int    $memid    member_id of user
 * @param int    $taskid   task_id of task to be entered
 * @param int    $taskhrs  number of hours to be credited for task
 * @param string $taskdesc description of task
 * 
 * @return void
 */
function updateMemberHoursTable($memid, $taskid, $taskhrs, $taskdesc)
{
    $db = \JFactory::getDBO();
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
// ------------------------------------------------------------------------
/**
 * Update database with all tasks entered on form and display number of 
 * hours added. Tasks descriptions are taken from $taskArray session variable.
 *
 * @return $success boolean true if database was updated, false if error
 */
function addHoursAndUpdateDatabaseTables()
{
    $db = \JFactory::getDBO();
    $totalHrsAdded = 0;    
    $memId = trim($_POST['member_id']);
    $taskArray = $_SESSION["taskArray"];
    
    $success = false;
    try {
        $db->transactionStart();
        
        // get hours from checkboxes
        for ($ii=0; $ii<count($taskArray['checkboxes']); $ii++) {
            if (!empty($_POST["task_checkboxes_0$ii"])) {
                $task_id = $taskArray['checkboxes'][$ii]->task_id;
                $task_hours = $taskArray['checkboxes'][$ii]->default_task_hrs;
                updateMemberHoursTable($memId, $task_id, $task_hours, null);
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
                updateMemberHoursTable($memId, $task_id, $task_hours, null);
                $totalHrsAdded += $task_hours;
            }
        }
        
        // get hours from hours pulldowns
        for ($ii=0; $ii<count($taskArray['varhours']); $ii++) {
            $task_hours = trim($_POST["task_varhours_0$ii"]);
            if (!empty($task_hours)) {
                $task_id = $taskArray['varhours'][$ii]->task_id;
                updateMemberHoursTable($memId, $task_id, $task_hours, null);
                $totalHrsAdded += $task_hours;
            }
        }
        
        // get hours from misc fill-in-the-box
        for ($ii=0; $ii<count($taskArray['misc']); $ii++) {
            $task_hours = trim($_POST["task_misc_0$ii"]);
            if (!empty($task_hours)) {
                $task_id = $taskArray['misc'][$ii]->task_id;
                $task_desc = trim($_POST["task_misc_desc_0$ii"]);
                updateMemberHoursTable($memId, $task_id, $task_hours, $task_desc);
                $totalHrsAdded += $task_hours; 
            }
        }
        
        $db->transactionCommit();        
        echo "<br/>Database updated<br/>";
        $success = true;
    } catch (\Exception $e) {
        $db->transactionRollback();
        echo "<br/>" . $e->getMessage() . 
        "<br/><strong>-- database was not updated --</strong><br/>";
        $totalHrsAdded = 0;
    }

    echo "<br/><br/><h4>   Hours Added : &nbsp&nbsp $totalHrsAdded </h4>";  

    // Display new net balance

    showMemberHoursBalance($memId);    

    echo '<input class=submittext2 name=back type=submit value="New Search">';
    return $success;
}
// ------------------------------------------------------------------------
/**
 * Display message that user must choose a member to update
 * 
 * @return void
 */ 
function insistUponSelection()
{
    echo "<br/><strong>Please choose a member.</strong><br/><br/><br/>";
    echo '<input class="submittext" name="back" type="button" value="Back" ' .
    'onClick="history.go(-1)">';
    return;
}

/*========================================================================*
 * Main body.                                                             *
 *========================================================================*/

$db = \JFactory::getDBO();

echo '<form method="post">';

if (!isset($_POST['process'] )) {
    displayBlankSearchForm();     
    loadTasksArray();                       
    $_SESSION['startrow'] = 0;
} elseif (($_POST['process'] == 1)   
    or (($_POST['process'] == 2) and isset($_POST['change_block']))
) {                              
    $members = buildAndExecuteSearchQuery();
    displayManyMembersTable($members);
} elseif ($_POST['process'] == 2) {     
    if (!isset($_POST['member_radio'])) {
        insistUponSelection();
    } else {
        $member = buildAndExecuteMemberQuery();
        showHoursEntryForm($member);
    }
} elseif ($_POST['process'] == 4) { 
    addHoursAndUpdateDatabaseTables();
} else {
    echo "<br/>How did we ever get here???<br/>";
} 
?>

</form> 
