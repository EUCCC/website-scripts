<?php
namespace AdminGoldCard;
/**
 * Admin Gold Card page/script for Experience Unlimited Joomla! website
 *
 * This script allows an administrator to create a gold card entry for a member. 
 * Directorships and EU Joomla! Board of Director group membership are terminated
 * Logins are blocked for Gold Card and Gold Card Unreachable (but not Gold Card 
 *		Pending) members
 * The first page of the script allows the administrator to search for a member based on
 * 		first name, last name, and/or email address. If all search fields are blank, the query
 * 		returns only active members. If any search field is not blank, the query returns all
 * 		members (active or otherwise) that satisfy the query.
 * The second page of the script allows the administrator to create the gold card entry. The
 * 		entry includes the following information: New Position, Employer Name, Employer City,
 * 		Return to work date, Job Leads, Alumni Contact, Comments, and Testimonial. 
 * Two gold cards cannot be created on the same day because the (member_id, gold_card_date) is
 * 		used as a unique key for the gold card entry.
 *
 * The status of the member can be set to "Gold Card Pending", "Gold Card", or "Gold Card Unreachable".
 * 
 * 
 * @package    adminGoldCard.php
 * @author     Jean Shih <jean1shih@gmail.com>
 * @author     Ben Bonham <bhbonham@gmail.com>
 * @copyright  2014 Experience Unlimited
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    1.4
 */

/* Modification log:
 * -------------------------------------------------------------------------
 * 2015/04/28	bb	 v1.4 add Back button, change error message
 * 2015/02/13	bb   v1.3 remove from Joomla "EU Member Database Administrators" group 
 * 2015/01/22	bb   v1.2 terminate directorship, remove from Joomla board group, block login for 
 *						gold-carded (but not gold-card-pending) members
 * 2015/01/05	bb   v1.1 search only active, inactive, pending, and gold-card-pending members 
 *                     (only these members can convert to gold card); set inactive date to
 * 						execution date of this gold-card; move "Gold Card" button below search
 * 						result table
 * 2014/12/03   bb   v1.0 updateObject was causing problems (not updating) -- switched
 *                     to string instead for updating members table
 * 2014/11/06   bb   Removed non-useful $num_rows test, moved some of linear
 *                     logic into functions for clarity, changed selection of
 *                     member to goldcard to radio buttons, layout form as table
 *                     (regressive -- should be updated to css), paginate long
 *                     tables, set database update to (all-or-none) transaction
 * 2014/08/10   js   Rebuilt for Joomla 3 upgrade
 */

/* Note:Still need to deal with duplicate gold card entries. Trying
 * 		to submit second gold card on same day for a member throws
 * 		exception. This should probably trigger an update rather than an insert.
 * 		Also should be able to convert "gold card pending" to "gold card" 
 *		(though this can be done through admin_member_update).
 */

echo <<<EOS
<h2>   Admin Gold Card Form v1.4  </h2>
Create a new Gold Card and change member status<br/>
Instructions
<ul>
<li>Enter values and click Submit</li>
<li>This will create a <em>new</em> Gold Card, which will replace a Gold Card entered by the member</li>
<li>Use Admin Member Update to change only status, e.g., "Gold Card Pending" to "Gold Card"</li>
</ul>
EOS;

echo '<form method="post">';


/*
 * Displaying query result table that has too many rows of bogs down the 
 * sql server and may cause a timeout, so the table can be paginated by
 * setting $blocksize, which is the max # of member entries to display on one page
 */
$blocksize = 30;
define("EU_BOARD_GROUP", "EU Board of Directors"); //Joomla! group name
define("EU_MEMBER_DATABASE_ADMIN", "EU Member Database Administrators"); //Joomla! group name

###########################################################################

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
	text-align:center;
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

.errortext {
	font-family: Arial, Helvetica, sans-serif;
	font-size: 14px; color:#C00000; font-weight: bold;
	margin-left:13cm;
}
body {
	background-color:#EBEBEB;
}
EOSTYLE;
     
$doc->addStyleDeclaration($style);

###########################################################################

#------------------------------------------------------------------------
function validated_date($date_strg)
{
	$new_date_strg = '';

        // joomla will correctly interpret us dates with slashes (mm/dd/yy
        // or mm/dd/yyyy) but interprets dates as intl if they have dashes 
        // (dd-mm-yy or dd-mm-yyyy)
        $us_dash_pat = "/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{2,4})$/";
        if (preg_match($us_dash_pat, trim($date_strg))) {
                $date_strg = str_replace("-","/",$date_strg);
        }

	if (empty($date_strg)) {
	}
	else if (is_numeric($date_strg)) {
		// don't allow unix timestamps here
		echo '<br> Bad format for Active Date <br>';
	} 
	else if (date_create($date_strg)) {
		$new_date_strg = \JFactory::getDate($date_strg);
		$new_date_strg = $new_date_strg->format('Y-m-d');
	} 
	else {
		echo '<br> Bad format for Active Date <br>';
	}
	return $new_date_strg;
}
#------------------------------------------------------------------------
function loadSessionArrays($db)
{
  loadJobLeadsArray($db);
  loadStatusesArray($db);
}
#------------------------------------------------------------------------
function loadJobLeadsArray($db)
{

    $query = "SELECT joblead_id, joblead_desc " .
        "FROM eu_jobleads " .
        "ORDER BY joblead_desc";

    $db->setQuery($query);
    $jobleades = $db->loadObjectList();

    unset($_SESSION['jid']);
    unset($_SESSION['jdesc']);

    $_SESSION['jid'][] = 1;
    $_SESSION['jdesc'][] = "Not Selected";
    foreach ($jobleades as $joblead) {
        $_SESSION['jid'][] = $joblead->joblead_id;
        $_SESSION['jdesc'][] = $joblead->joblead_desc;
    }
    return;
}

#------------------------------------------------------------------------
function loadStatusesArray($db)
{

    $query = "SELECT member_status, member_status_desc " .
        "FROM eu_member_statuses " . "WHERE member_status in ('G', 'GU', 'GP') " .
        "ORDER BY member_status_desc";

    $db->setQuery($query);
    $statuses = $db->loadObjectList();

    unset($_SESSION['sid']);
    unset($_SESSION['sdesc']);

    foreach ($statuses as $status) {
        $_SESSION['sid'][] = $status->member_status;
        $_SESSION['sdesc'][] = $status->member_status_desc;
    }
    
    return;
}

#------------------------------------------------------------------------
function display_blank_search_form()
{
	# Display search fields:  first name, last name and Email
    echo '<table><tr>';

     # first name 
    echo '<td class="blabel">First&nbspName</td>' .
        '<td style="text-align:left; width:30%;">' .
        '<input type="text" size="20" max="30" name="fname"></td>';
        
     # last name 
    echo '<td class="blabel">Last&nbspName</td>' .
        '<td style="text-align:left; width:30%;">' .
        '<input type="text" size="20" max="30" name="lname"></td>';
        
     # Email address 
    echo '<td class="blabel">Email</td>' .
        '<td style="text-align:left; width:30%;">' .
        '<input type="text" size="20" max="30" name="email"></td>';

	echo '</tr></table>';
	echo '<input name="Search" type="submit" value="Search">';
	echo '<input type="hidden" name="process" value="1">';
	return;
}
#------------------------------------------------------------------------
function build_and_execute_search_query($db, $blocksize)
{
	
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
		->join('LEFT', $db->quotename('eu_member_statuses', 'ms') .
			' ON ' . $db->quotename('ms.member_status') .
			' = ' . $db->quotename('mbr.status'))
		->where($db->quotename('mbr.status') . ' IN ("A","I","P","GP")')
		->order($db->quoteName('mbr.last_name'));

	if (!empty($fname))
		{
			$fname = '%' . $db->escape($fname, true) . '%';
			$query->where($db->quoteName('mbr.first_name') .
				' LIKE ' . $db->quote($fname, false));
		}
	if (!empty($lname))
		{
			$lname = '%' . $db->escape($lname, true) . '%';
			$query->where($db->quoteName('mbr.last_name') .
				' LIKE ' . $db->quote($lname, false));
		}
	if (!empty($email))
		{
			$email = '%' . $db->escape($email, true) . '%';
			$query->where($db->quoteName('mbr.email_address') .
				' LIKE ' . $db->quote($email, false));
		}

    $db->setQuery($query);
   
	$db->query();
	$cnt = $db->getNumRows();
	
	# determine which rows of search should be displayed on current page 
	$startrow = $_SESSION['startrow'];
	if (isset($_POST['change_block'])) {
		$action = $_POST['change_block'];
	} else {
		$action = '';
	}

    if (!empty($action) and ($action == "Next block")) {
        $startrow = $startrow + $blocksize;
    } elseif (!empty($action) and ($action == "Previous block")) {
        $startrow = $startrow - $blocksize;
    } else {
        $startrow = 0;        # this is a new search 
    }
    $_SESSION['startrow'] = $startrow;
	
	echo '<br>Search returns ' . $cnt . ' entries';
	$db->setQuery($query,$startrow,$blocksize);
	$members = $db->loadObjectList();
	
	if ($cnt>0) {
        echo '-- now displaying entries ' . (1+$startrow) . ' through ' . 
            ($startrow + count($members)) . '<br/>';
		}
		
	# show buttons to display next and/or previous blocks of rows if needed
	if ($startrow - $blocksize >= 0) {
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
#------------------------------------------------------------------------
function display_many_members_table($members)
{

   # Display column headings

    echo "<br><table border=1>";
    echo '<tr style="background-color:#EBEBEB;"><th>Select</th><th>First Name</th>' .
        '<th>Last Name</th><th>Email</th><th>Status</th></tr>';

   # column details

   $table_of_ids = array();
   $i = 0;
   foreach ($members as $member)
   {
	$tr = $i % 2 == 0 ? '<tr style="background-color:#EBEBEB;">' : '<tr>';
	echo $tr;
	echo "<td align='center' width=5% ><input type='radio' name='member_radio' value=$i></td>" .
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
   
	echo '<input name="Select" type="submit" value="Select">';
	echo '&nbsp<INPUT name="back" type="button" value="Back" onClick="history.back(-1)">';
    echo '<br/>';
   
    // put this (confidential information) in session variable
    $_SESSION['table_of_ids'] = $table_of_ids;
	echo '<input type="hidden" name="process" value="2">';
    
}
#------------------------------------------------------------------------
function build_and_execute_member_query($db)
{
	$index_of_id =  $_POST['member_radio'];
	$member_id = (int) $_SESSION['table_of_ids'][$index_of_id];
	$_SESSION['member_id'] = $member_id;
	
	$query = $db->getQuery(true);
	$query
		->select($db->quoteName('mbr.first_name'))
		->select($db->quoteName('mbr.last_name'))
		->select($db->quoteName('mbr.email_address'))
		->select($db->quoteName('mbr.home_phone'))
		->select($db->quoteName('mbr.mobile_phone'))
		->select($db->quoteName('ms.member_status_desc'))
		->from($db->quoteName('eu_members', 'mbr'))
		->join('LEFT', $db->quotename('eu_member_statuses', 'ms') .
			' ON ' . $db->quotename('ms.member_status') .
			' = ' . $db->quotename('mbr.status'))
		->where($db->quoteName('mbr.member_id') . ' = ' . $member_id);

	$db->setQuery($query);
	$member = $db->loadObject();

	return $member;
}
#-----------------------------------
function insertPulldownMenu($label, $name, $index_array, $value_array, $disabled, $selection) {
	echo "<tr><td class='blabel'>$label</td>";
	echo "<td><select $disabled name='$name'>";
	for ($i = 0; $i < count($index_array); $i++) {
		$ind = $index_array[$i];
		$val = $value_array[$i];
		$selected = $selection == $ind ? 'selected' : '';
		echo "<option value='$ind' $selected>$val</option>";
	}
	echo "</td></tr>";
	return;
}
#------------------------------------------------------------------------
function display_new_goldcard_form($db, $member)
{
	$disabled = ' disabled style="background-color:gray" ';
	
	$return_to_work_date  = "";
	$new_position  = "";
	$emp_name  = "";
	$emp_city  = "";
	$emp_state  = "";
	$joblead_id  = "";
	$status  = "";
	$alumni_event_contact  = "";
	$gc_comments  = "";
	$gc_testimonials  = "";
	$update = 0;
	$num_rows = 0;
	$first_name = $member->first_name;
	$last_name = $member->last_name;

 	loadSessionArrays($db);

	echo '<br><table style="padding:25px;">';

     # first name 
    echo '<tr><td class="blabel">First&nbspName</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="first_name" ' .
        'value="' . $first_name . '"' . $disabled . ' ></td></tr>';

     # last name 
    echo '<tr><td class="blabel">Last&nbspName</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="last_name" ' .
        'value="' . $last_name . '"' . $disabled . ' ></td></tr>';

     # new position 
    echo '<tr><td class="blabel">New&nbspPosition</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="new_position" ' .
        'value="' . $new_position . '" ></td></tr>';

     # employer name
    echo '<tr><td class="blabel">Employer&nbspName</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="emp_name" ' .
        'value="' . $emp_name . '" ></td></tr>';

     # employer city
    echo '<tr><td class="blabel">Employer City</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="emp_city" ' .
        'value="' . $emp_city . '" ></td></tr>';

     # return to work date
    echo '<tr><td class="blabel">Return to work date</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="return_to_work_date" ' .
        'value="' . $return_to_work_date . '" title="Enter as mm/dd/ccyy or ccyy-mm-dd"></td></tr>';
	
    # jobleads 
    insertPulldownMenu('Job&nbspLeads', 'joblead', $_SESSION['jid'], $_SESSION['jdesc'], '','');

    # status 
    insertPulldownMenu('Status', 'status', $_SESSION['sid'], $_SESSION['sdesc'], '','');

	echo '<tr><td class="blabel">May we contact you?</td>' . 
		 '<td>Yes <input type="radio" checked="checked" name="alumni_event_contact" value=1>
		 No	<input type="radio" name="alumni_event_contact" value=0></td></tr>';
			 
	echo '</table>';       
	echo "<br/>";

	echo '<table>';
    # comments
    echo '<tr><td class="blabel">Comments</td>';
    echo '<td><textarea name="gc_comments" style="resize:none; height:7em; min-width:71ex;">' . 
			$gc_comments . '</textarea></td></tr>';
    
    # testimonial
    echo '<tr><td class="blabel">Testimonial</td>';
    echo '<td><textarea name="gc_testimonials" style="resize:none; height:7em; min-width:71ex;">' . 
			$gc_testimonials . '</textarea></td></tr>';
	echo '</table>';
	echo "<br/>";

	echo '<input name=submit type=submit value=Submit>';
	echo '<input name=back type=button value=Back onClick=history.go(-2)>';
	echo '<input type=hidden name=process value=3>';

}
#------------------------------------------------------------------------
function update_goldcard_database_table($db)
{
	$today = date("Y-m-d");
	$member_id = trim($_SESSION['member_id']);
	$gold_card_date=$today;
	$return_to_work_date  = trim(validated_date($_POST['return_to_work_date']));
 	$new_position  = trim($_POST['new_position']);
 	$emp_name  = trim($_POST['emp_name']);
 	$emp_city  = trim($_POST['emp_city']);
 	$status  = trim($_POST['status']);
 	$joblead_id = trim($_POST['joblead']);
 	$alumni_event_contact  = trim($_POST['alumni_event_contact']);
 	$gc_comments  = trim($_POST['gc_comments']);
 	$gc_testimonials  = trim($_POST['gc_testimonials']);

	// Create a new query object for inserting a record to eu_gold_cards.
	$query = $db->getQuery(true);

	// Prepare for insert
	// Insert columns.
	$columns = array(
				'member_id',
				'gold_card_date',
				'return_to_work_date',
				'new_position',
				'emp_name',
				'emp_city',
				'joblead_id',
				'alumni_event_contact',
				'gc_comments',
				'gc_testimonials');

	// Prepare insert values.
	$values = array(	
				$db->quote($member_id),
				$db->quote($gold_card_date),
				$db->quote($return_to_work_date),
				$db->quote($new_position),
				$db->quote($emp_name),
				$db->quote($emp_city),
				$db->quote($joblead_id),
				$db->quote($alumni_event_contact),
				$db->quote($gc_comments),
				$db->quote($gc_testimonials));

	// Prepare the insert query.
	$query
		->insert($db->quoteName('eu_gold_cards'))
		->columns($db->quoteName($columns))
		->values(implode(',', $values));

	// Set the query using our newly populated query object and execute it.
	$db->setQuery($query);
	$db->query();
	
	return;
}
#------------------------------------------------------------------------
function update_joomla_users_table($db)
{
  // Update eu_members table, change member status accordingly
	$member_id = trim($_SESSION['member_id']);
 	$status  = trim($_POST['status']);
	$query = $db->getQuery(True);
	$query->update($db->quoteName('#__users'))
		  ->where($db->quoteName('id') . ' = ' . $member_id);
		  
	// set login blocking unless member is goldcard pending
	if ($status == 'GP') {
		$query->set($db->quoteName('block') . ' = 0');
	} else {
		$query->set($db->quoteName('block') . ' = 1');
	}
	
	$db->setQuery($query);
	$db->execute();
	
	return;
}
#------------------------------------------------------------------------
function update_member_database_table($db)
{
  // Update eu_members table, change member status accordingly
	$member_id = trim($_SESSION['member_id']);
 	$status  = trim($_POST['status']);
 	$new_position  = trim($_POST['new_position']);
	$today = date("Y-m-d");

 	$query = $db->getQuery(true);

 	$fields = array(
		$db->quoteName('new_position') . ' = ' . $db->quote($new_position),
		$db->quoteName('status') . ' = ' . $db->quote($status),
		$db->quoteName('inactive_date') . ' = ' . $db->quote($today),
		$db->quoteName('board_position_id') . ' = 1'); // not a board member
		
    $query
		->update($db->quoteName('eu_members'))
		->set($fields)
		->where($db->quoteName('member_id') . " =  $member_id");
		
	$db->setQuery($query);
	$db->execute();

	return;
}
#--------------------------------------------------------------------
function drop_from_joomla_group($db, $group_name)
{
	$user_id_to_edit = trim($_SESSION['member_id']);
	// translate group_name into group_id
	$query = $db->getQuery(True);
	$query ->select($db->quoteName('id'))
			  ->from($db->quoteName('#__usergroups'))
			  ->where($db->quoteName('title') . ' = ' . $db->quote($group_name));
	$db->setQuery($query);
	$group_id = $db->loadResult();
	
	// drop member from the Joomla! group
	$query = $db->getQuery(True);
	$query->delete($db->quoteName('#__user_usergroup_map'))
		  ->where($db->quotename('user_id') . ' = ' . $user_id_to_edit)
		  ->where($db->quoteName('group_id') . ' = ' . $db->quote($group_id));
    $db->setQuery($query);
    $db->execute();	
	return;
}
#--------------------------------------------------------------------
function close_old_board_positions($db)
{
	$user_id_to_edit = trim($_SESSION['member_id']);
	$query = $db->getQuery(True);
	$query->update($db->quoteName('eu_board_members'))
		  ->set($db->quoteName('board_member_status') . ' = 0')
		  ->set($db->quoteName('board_member_end_date') . ' = CURDATE()')
		  ->where($db->quoteName('member_id') . ' = ' . $db->quote($user_id_to_edit)) 
		  ->where($db->quoteName('board_member_status') . ' = 1');
	$db->setQuery($query);
	$db->execute();
	
    // remove the ex-board member from the joomla EU Board of Directors group
    drop_from_joomla_group($db, EU_BOARD_GROUP);
    return;
}
#--------------------------------------------------------------------
function updateJoomlaGroups($db)
{
	drop_from_joomla_group($db, EU_MEMBER_DATABASE_ADMIN);
}
#--------------------------------------------------------------------
function update_database_tables($db)
{
	define("UNIQUE_CONSTRAINT_ERROR", 1062);
	$success = false;
	try {
		$db->transactionStart();
		update_joomla_users_table($db);
	    updateJoomlaGroups($db);
		update_member_database_table($db);
		update_goldcard_database_table($db);
		close_old_board_positions($db);
		$db->transactionCommit();		
		echo "<br/>Database updated<br/>";
		$success = true;
    } catch (\Exception $e) {
	    $db->transactionRollback();
	    if ($e->getCode()==UNIQUE_CONSTRAINT_ERROR) {	    
			$tomorrow = \JFactory::getDate('now + 1 day')->Format('Y-m-d');
			echo "<br/><strong>Duplicate Gold Card entry -- database was not updated</strong><br/>
			(New Gold Card can be entered for this member tomorrow, $tomorrow, or later)<br/><br/>";
		} else {
		    echo "<br/>" . $e->getMessage() . "<br/><strong>-- database was not updated --</strong><br/>";
		}
	}
	return $success;
}

/*========================================================================*
 * Main body.                                                             *
 *========================================================================*/

\JHTML::_('behavior.formvalidation');
$db = \JFactory::getDBO();

if  (!isset($_POST['process'] )) {
	display_blank_search_form();
	$_SESSION['startrow'] = 0;
} elseif (($_POST['process'] == 1) or (($_POST['process'] == 2) and isset($_POST['change_block']))) {
	$members = build_and_execute_search_query($db, $blocksize);
	display_many_members_table($members);
} elseif ($_POST['process'] == 2) {
	if (isset($_POST['member_radio'])) 	{
		$member = build_and_execute_member_query($db);
		display_new_goldcard_form($db, $member);
	} else 	{
		echo "<br/><strong>Please select a member to Gold-Card.</strong><br/><br/><br/>";
		echo '<input name="back" type="button" value="Back" onClick="history.go(-1)">';
	} 
} elseif ($_POST['process'] == 3) {
	update_database_tables($db);
	echo '<input name=back type=submit value="New Search">';
} else {
	echo "<br/>How did we ever get here???<br/>";
}

echo "</form>";
?>

