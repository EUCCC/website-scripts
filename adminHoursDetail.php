<?php
/**
 * Description:
 *    This program will list out member hours/tasks detail  by Admin
 *    As of now, it lists active or inactive members only
 *
 * PHP version 5
 * 
 * @category  EUAdminScripts
 * @package   AdminHoursDetail
 * @author    Jean Shih <jean1shih@gmail.com>
 * @author    Ben Bonham <bhbonham@gmail.com>
 * @copyright 2014-2015 Experience Unlimited
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   1.3
 * @link      https://github.com/EUCCC/website-scripts/blob/master/adminHoursDetail.php
 */

/* Modification log:
 * -------------------------------------------------------------------------
 * 2015/05/21	bb	1.3	add comments, don't pass $db to functions
 * 2015/04/17	bb	v1.2 secondary ordering by task name, change date format 
 * 					to m/d/Y, added Back buttons, change isset($_POST) to 
 * 					null !== $postdata->get (and the complement)
 * 2015/01/29	bb	v1.1 modify to use radio buttons for member selection
 * 2011/03/14   js	v1.0 created
 */
 
namespace AdminHoursDetail;

echo <<<EOS
<h2>   Admin Hours Detail v1.3  </h2>
EOS;

/*
 * Displaying query result table that has too many rows of bogs down the 
 * sql server and may cause a timeout, so the table can be paginated by
 * setting BLOCKSIZE, which is the max # of member entries to display on one page
 */
define('BLOCKSIZE', 30);

echo '<form method="post">';

// 
$doc = \JFactory::getDocument();

$style = <<<EOSTYLE
.blabel {
	text-align:right;
	max-width:300px;
	padding:0px 10px;
	color: rgb(0,0,255);
    }
.basictext {
	font-family: Arial, Helvetica, sans-serif; 
	font-size: 14px; color:#000066;
	text-align:center;
	margin-left:2cm;
}
.detailtext {
	font-family: Arial, Helvetica, sans-serif; 
	font-size: 14px; color:#000066;
	text-align:center;
	margin-left:7cm;
}
.titletext {
	font-family: Arial, Helvetica, sans-serif; 
	font-size: 22px; color:#000066;
	margin-left:13cm;
}
.subtitletext {
	font-family: Arial, Helvetica, sans-serif; 
	font-size: 16px; color:#000066;
	margin-left:14cm;
}
.submittext {
	font-family: Arial, Helvetica, sans-serif; 
	font-size: 16px; color:#000066;
	margin-left:16cm;
}
.errortext {
	font-family: Arial, Helvetica, sans-serif; 
	font-size: 14px; color:#C00000; font-weight: bold;
	margin-left:13cm;
}

p {text-indent:120px;}


table#table_1 {
	border: 2px solid silver;
}

table#table_1 tr:nth-child(odd) {
	background-color: #DDDDDD;
}
	
table th {
	background-color:#CCCCCC;
}

table td {
	padding: 0px 5px;
}
	
table#table_1 th,
table#table_1 td {
	border: 1px solid silver;
}

input[disabled],select[disabled] {
    background: #cccccc;
}


EOSTYLE;

$doc->addStyleDeclaration($style);
// 

// ------------------------------------------------------------------------
/**
 * Display table with member query results 
 * 
 * @param array $members array of member objects
 * 
 * @return void
 */ 
function showMemberData($members)
{
    /*
     */
     
    // Display column headings 
    echo '<br><table id=table_1>';
    echo '<tr>
    <th>Member ID</th>
    <th>First Name:</th>
    <th>Last Name</th>
    <th>Email</th>
    <th>Status</th>
    </tr>';
        
    // Display member data 
    foreach ($members as $member) {
        echo "<tr>";
        echo 
        "<td align=center>" . $member->member_id . "</td>" .
        "<td align=center>" . $member->first_name . "</td>" .
        "<td align=center>" . $member->last_name . "</td>" .
        "<td align=center>" . $member->email_address . "</td>" .
        "<td align=center>" . $member->Status . "</td>" .
        "</tr>";
    }

    echo "</table>";
    return;
}

// ------------------------------------------------------------------------
/**
 * Display member hours
 * 
 * @param string $member_name name of member (first last)
 * @param array  $tasks       array of objects describing tasks credited to
 *                            member since member's active date 
 * 
 * @return void
 */ 
function showHoursDetail($member_name, $tasks)
{
    $db = \JFactory::getDBO();
    /*
    * Display table with member query results 
    */
     
    echo '<div align="right"><INPUT type="submit" value="New Search"></div>';
    
    $total_hours = 0;
    foreach ($tasks as $task) {
        $total_hours += $task->task_hours;
    }
    echo "<h4>Total volunteer hours for $member_name: $total_hours </h4>";  
    
    // Display column headings 
    echo '<br><table id=table_1>';
    echo '<tr>
    <th>Task Recording Date </th>
    <th>Task Name </th>
    <th>Task Hours </th>
    <th>Task Hour Descriptions </th>
    </tr>';
        
    // Display member data 
    foreach ($tasks as $task) {
        $tdate = date_create($task->task_date);
        echo "<tr>";
        echo "<td align=center>" . date_format($tdate, "m/d/Y") . "</td>" .
        "<td>" . $task->task_name . "</td>" .
        "<td align=right>" . intval($task->task_hours) . "</td>" .
        "<td>" . $task->misc_task_hr_desc . "</td>" .
        "</tr>";
    }

    echo "</table>";
    echo '<br/><input name="back" type="button" value="Back" onClick="history.go(-1)">';
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
		 <input name="Search" type="submit" value="Search">
		 <input name="action" type="submit" value="Reset"> ';
    return;
}
// ------------------------------------------------------------------------
// ------------------------------------------------------------------------
/**
 * Build and execute query to get member information from database
 * 
 * @return array $members  array of query objects with member data
 */ 
function buildAndExecuteSearchQuery()
{
    $db = \JFactory::getDBO();
    $postdata = \JFactory::getApplication()->input->post;
    $fname = trim($postdata->get('fname'));
    $lname = trim($postdata->get('lname'));
    $email = trim($postdata->get('email'));

    $query = $db->getQuery(true);
    $query
        ->select($db->quoteName('mbr.member_id', 'mID'))
        ->select($db->quoteName('mbr.first_name', 'euF'))
        ->select($db->quoteName('mbr.last_name', 'euL'))
        ->select($db->quoteName('mbr.email_address', 'Email'))
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
    if (null !== $postdata->get('change_block')) {
        $action = $postdata->get('change_block');
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

    echo "<input type='hidden' name='fname' value={$postdata->get('fname')}>";
    echo "<input type='hidden' name='lname' value={$postdata->get('lname')}>";
    echo "<input type='hidden' name='email' value={$postdata->get('email')}>";
    echo "<input type='hidden' name='email_address' value={$postdata->get('email')}>";

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
        "name='member_radio' value=$i></td>" .
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
    echo '<input name="Select" type="submit" value="Select">';
    echo '&nbsp<input name="back" type="button" value="Back" onClick="history.go(-1)">';
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
// -----------------------------------------------------------------------
/**
 * Query database for tasks credited to member since active date
 * 
 * @param int $member_id member_id of member
 * 
 * @return array($member_name, $tasks)
 *               string $member_name "first last"
 *               array  $tasks       objects describing tasks
 */ 
function buildAndExecuteMemberHoursQuery($member_id)
{
    $db = \JFactory::getDBO();
    $query = "select first_name, last_name 
				from eu_members
				where member_id = $member_id";
    $db->setQuery($query);
    $member = $db->loadObject();
    $member_name = $member->first_name . " " . $member->last_name;
    
    $query = "select 	task_date, task_name, task_hours, misc_task_hr_desc  
			from eu_member_hours eh 
			right join  eu_members em 
			on eh.member_id = em.member_id  
			left join eu_member_tasks emt 	
			on eh.task_id = emt.task_id 
			where (em.member_id=$member_id
			and eh.task_date > em.active_date) order by task_date desc, task_name asc";
    
    $db->setQuery($query);
    $tasks = $db->loadObjectList();

    return array($member_name, $tasks);
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
    echo '<input name="back" type="button" value="Back" onClick="history.go(-1)">';
    return;
}
/*========================================================================*
 * Main body.                                                             *
 *========================================================================*/
$db = \JFactory::getDBO();
$postdata = \JFactory::getApplication()->input->post;

if (!$postdata->get('process') || $postdata->get('action') == 'Reset') {
    displayBlankSearchForm();     
    loadTasksArray();                       
    $_SESSION['startrow'] = 0;
} elseif (($postdata->get('process') == 1)   
    or (($postdata->get('process') == 2)  
    and (null !== $postdata->get('change_block')))
) {                              
    $members = buildAndExecuteSearchQuery();
    displayManyMembersTable($members);
} elseif ($postdata->get('process') == 2) {     
    if (null == $postdata->get('member_radio')) {
        insistUponSelection();
    } else {
        $index_of_id =  $postdata->get('member_radio');
        $member_id = (int) $_SESSION['table_of_ids'][$index_of_id];
        list($member_name, $tasks) = buildAndExecuteMemberHoursQuery($member_id);
        showHoursDetail($member_name, $tasks);
    }
} else {
    echo "<br/>How did we ever get here???<br/>";
} 
?>		
</form> 
