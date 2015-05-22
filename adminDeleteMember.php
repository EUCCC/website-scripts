<?php
namespace adminDeleteMember;
/*
 * Description:
 *    This program will ...
 *
 * @package    adminDeleteMember_v1.1.php
 * @author     Jean Shih <jean1shih@gmail.com>
 * @author     Ben Bonham <bhbonham@gmail.com>
 * @copyright  2014,2015 Experience Unlimited
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    1.2
 *
 *
 *
 * Modification log:
 *  -------------------------------------------------------------------------
 *  2015/04/17 bb v1.2	Don't include gold-card-pending in search, clarify instructions
 *  2015/02/03 bb v1.1	Change to radio button format
 * 						Refuse to delete Active member (so we don't need to drop EU group 
 * 						memberships in this script)
 *  2014/09/01 js v1.0	Commented out statement to cloak email address. It's not
 *						needed since NoNumber Email Protector extension is used
 *  2014/08/10 js v1.0	Created
 */

echo <<<EOS
<h2>Admin Delete Member v1.2</h2>
Instructions
<ul>
	<li>Active and Gold-Card-Pending status members cannot be deleted
		<ul><li>Change Active or Gold-Card-Pending member to an inactive status (Inactive, Gold Card, etc.) using Admin Member Update, then delete</li></ul>
	</li>
</ul>
<br/>
EOS;

/*
 * Displaying query result table that has too many rows of bogs down the 
 * sql server and may cause a timeout, so the table can be paginated by
 * setting BLOCKSIZE, which is the max # of member entries to display on one page
 */
define('BLOCKSIZE', 30);

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
EOSTYLE;

$doc->addStyleDeclaration($style);
###########################################################################
 
#------------------------------------------------------------------------
function display_blank_search_form()
{
     # Display search fields:  first name, last name and Email 
    echo '<table><tr>';
    # First name 
    echo '<td class="blabel">First&nbspName</td>' .
        '<td align="left"><input type="text" size="20" max="30" name="fname" ' .
        '></td>';

    # Last name 
    echo '<td class="blabel">Last&nbspName</td>' .
        '<td align="left"><input type="text" size="20" max="30" name="lname" ' .
        '></td>';

    # Email address 
    echo '<td class="blabel">Email</td>' .
        '<td align="left"><input type="text" size="20" max="40" name="email" ' .
        '></td>';

	echo '</tr></table>';	   
	echo '	<input type="hidden" name="process" value="1"> 
		 <input name="Search" type="submit" value="Search"> ';
	return;
}
#------------------------------------------------------------------------
function build_and_execute_search_query($db)
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
		->select($db->quoteName('ms.member_status_desc', 'euS'))
		->from($db->quoteName('eu_members', 'mbr'))
		->join('LEFT', $db->quotename('eu_member_statuses', 'ms') .
			' ON ' . $db->quotename('ms.member_status') .
			' = ' . $db->quotename('mbr.status'))
		->where($db->quotename('mbr.status') . ' NOT IN ("A","D","GP")')  
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
        $startrow = $startrow + BLOCKSIZE;
    } elseif (!empty($action) and ($action == "Previous block")) {
        $startrow = $startrow - BLOCKSIZE;
    } else {
        $startrow = 0;        # this is a new search 
    }
    $_SESSION['startrow'] = $startrow;

	echo 'Search returns ' . $cnt . ' entries';
	$db->setQuery($query,$startrow,BLOCKSIZE);
	$members = $db->loadObjectList();

	if ($cnt>0) {
        echo '-- now displaying entries ' . (1+$startrow) . ' through ' . 
            ($startrow + count($members)) . '<br/>';
		}

	# show buttons to display next and/or previous blocks of rows if needed
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
#------------------------------------------------------------------------
function display_many_members_table($members)
{
   # Display column headings
    echo "<table border=1>";
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

    echo '<br/>';
	echo '<input name="Select" type="submit" value="Select">';
    echo '<br/>';

    // put this (confidential information) in session variable
    $_SESSION['table_of_ids'] = $table_of_ids;
	echo '<input type="hidden" name="process" value="2">
		<INPUT class="submittext2" name="back" type="button" value="Back" onClick="history.back(-2)">';
	return;
}
#------------------------------------------------------------------------
function show_single_member_data($member)
{
   # Display column headings
    echo "<table border=1>";
    echo '<tr style="background-color:#EBEBEB;"><th>First Name</th>' .
        '<th>Last Name</th><th>Email</th><th>Status</th></tr>';

	echo "<tr>";
	echo "<td width=20% align=left height=30 >".$member->first_name."</td>".
		"<td width=20% align=left height=30 >".$member->last_name."</td>".
		"<td width=20% align=left height=30 >".$member->email_address."</td>".
		"<td width=10% align=center height=30 >".$member->member_status_desc."</td> ";
        echo  "</tr>";
	
	echo "</table>";
	echo "<br/>";
			
	echo '<input type="hidden" name="process" value="3">
		<input name="Select" type="submit" value="Delete"> 
		<INPUT name="back" type="button" value="Back" onClick="history.back(-2)">';
	return;
}
#------------------------------------------------------------------------
function build_and_execute_single_member_query($db, $member_id)
{
   	$query = "SELECT	member_id,
						first_name,
						last_name,
						email_address, 
						home_phone, 
						mobile_phone,
						ms.member_status_desc 
			from eu_members mbr, eu_member_statuses ms
			where member_id = $member_id  and
				mbr.status= ms.member_status;"; 
	$db->setQuery($query);
	$member_data = $db->loadObject();
	return $member_data; 		
}
#------------------------------------------------------------------------
function delete_member($db)
{
	// Changes email address to "deleted+NN+oldEmail" in eu_members table and in
	// Joomla! users database, where NN is a random #, and changes member status 
	// to "D" in eu_members table
	
	// Random number is needed in case of deleting login(i.e., email) multiple times
	$rnum =  mt_rand(10,100);
	
	$deleted_email = 'deleted' . $rnum . '.' . $_SESSION['email_address'];
	
	$eu_member = new \stdClass();
	$eu_member->member_id = $_SESSION['member_id'];
	$eu_member->email_address = $deleted_email;
	$eu_member->status = 'D';
	
	$joomla_user = new \stdClass();
	$joomla_user->id = $_SESSION['member_id'];
	$joomla_user->email = $deleted_email;
	$joomla_user->username = $deleted_email;
	
	// first check that member has not already been deleted (e.g., that this is not a reloaded page)
	$query = $db->getQuery(True);
	$query
		->from($db->quoteName('eu_members'))
		->select($db->quoteName('member_id'))
		->where($db->quoteName('member_id') . ' = ' . $db->quote($_SESSION['member_id']))
		->where($db->quoteName('status') . ' <> "D"');
	$db->setQuery($query);
	$result = $db->loadResult();
	if (empty($result)) {
		echo "<br/>Database not updated -- member appears to have been previously deleted<br/>";
		echo '<INPUT type="Submit" value="New Search">';
		return;
	}
	
	try {
		$db->transactionStart();
		
		// Update eu_members table
		$result = \JFactory::getDbo()->updateObject('eu_members', $eu_member, 'member_id');
	 
		// Update joomla users table's username and email field with follow:
		// '#__'   will take default joomla table prefix 
		$result = \JFactory::getDbo()->updateObject('#__users', $joomla_user, 'id');
	
		$db->transactionCommit();		
		echo "<br/>Database updated<br/>";
		$success = true;
    } catch (\Exception $e) {
	    $db->transactionRollback();
	    echo "<br/>" . $e->getMessage() . "<br/><strong>-- database was not updated --</strong><br/>";
	}

	echo '<INPUT type="Submit" value="New Search">';
	return;
}
#------------------------------------------------------------------------
function insist_upon_selection()
{
	echo "<br/><strong>Please select a member to delete.</strong><br/><br/><br/>";
	echo '<input class="submittext" name="back" type="button" value="Back" onClick="history.go(-1)">';
	return;
}
/*========================================================================*
 * Main body.                                                             *
 *========================================================================*/
$db = \JFactory::getDBO();

if  (!isset($_POST['process'] )) { 
	display_blank_search_form(); 	
	$_SESSION['startrow'] = 0;
} elseif (($_POST['process'] == 1)  or (($_POST['process'] == 2) and isset($_POST['change_block']))) { 		   		  		
	$members = build_and_execute_search_query($db);
	display_many_members_table($members);
} elseif ($_POST['process'] == 2) { 	
	if (!isset($_POST['member_radio'])) {
		insist_upon_selection();
	} else {
		$index_of_id =  $_POST['member_radio'];
		$member_id = (int) $_SESSION['table_of_ids'][$index_of_id];
		$member_data = build_and_execute_single_member_query($db, $member_id);
		$_SESSION['member_id'] = $member_id;
		$_SESSION['email_address'] = $member_data->email_address;
		show_single_member_data($member_data);
	}
} elseif ($_POST['process'] == 3) { 
		delete_member($db);	
} else {
	echo "<br/>How did we ever get here???<br/>";
} 
?>		
</form> 

