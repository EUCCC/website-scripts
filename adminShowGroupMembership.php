<?php
namespace AdminShowGroupMembership;

/**
 * Displays group memberships for Joomla! groups
 * 
 * This page displays group memberships for EU groups (those with 
 * titles that begin with "EU"), and also for the groups 
 * "Administrator", "Super Users", and "Manager". Joomla! username,
 * EU Name, and EU Status are shown for each user.
 * 
 * If any user is shown that does not have an "Active" EU status, 
 * this should be of concern. 
 * 
 * 
 * @package    admin-show-group-membership.php
 * @author     Ben Bonham <bhbonham@gmail.com>
 * @copyright  2015 Experience Unlimited
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    1.0
 */

/*
 * Summary of revisions
 * 1.0	4/20/15	bb	Initial release
 */
 
###########################################################################

echo <<<EOS
<h1>Admin Show Group Membership 1.0</h1>
EOS;

$doc = \JFactory::getDocument();
$style = <<<SSS
.blabel {
	text-align:right; 
	max-width:300px; 
	padding:0px 10px;
	color: rgb(0,0,255);
}

table#table_1 {
	border: 2px solid silver;
}

table#table_1 tr:nth-child(odd) {
	background-color: #DDDDDD;
}
	
table th {
	background-color:#CCCCCC;
        padding: 0px 5px;
}
	
table td {
        padding: 0px 5px;
}

table#table_1 th,
table#table_1 td {
	border: 1px solid silver;
}


table#table_1 tr td:nth-of-type(3) {
	text-align: center;
}

input[disabled],select[disabled] {
    background: #cccccc;
}

SSS;

$doc->addStyleDeclaration($style);

#-------------------------------------------------------
function show_instructions()
{
$Instructions = <<<EOI
This page displays group memberships for EU groups, and also for 
the groups "Super Users", "Administrator", and "Manager".<br/> 
The Joomla! username, EU Name, and EU Status are shown for each 
user.<br/>

If any user is shown that does not have an "Active" EU status, 
this should be of concern. 
EOI;
	echo $Instructions;
	echo "<br/><br/>";
}
#-------------------------------------------------------
function get_eu_group_list()
{
	$db = \JFactory::getDbo();
	$query = $db->getQuery(true);
	$query->select($db->quoteName(array(
		'groups.id', 
		'groups.title'),
		array(
		'group_id',
		'group_title')))
	->from($db->quoteName('#__usergroups','groups'))
	->where($db->quoteName('groups.title') . ' LIKE "EU%"')		
	->order($db->quoteName('groups.title'));
	$db->setQuery($query);
	$grouplist = $db->loadObjectList();
	
	return $grouplist;
}
#-------------------------------------------------------
function find_group_membership($group_title)
{
	$db = \JFactory::getDbo();
	$query = $db->getQuery(true);
	$query->select($db->quoteName(array(
		'groups.title',
		'users.username',
		'members.first_name',
		'members.last_name',
		'members.status'),
		array(
		'group_title',
		'user_username',
		'member_fname',
		'member_lname',
		'member_status')))
	->from($db->quoteName('#__users','users'))
	->join('LEFT', $db->quoteName('#__user_usergroup_map','map') . 
		' ON (' . $db->quoteName('map.user_id') . 
			' = ' . $db->quoteName('users.id') . ')')
	->join('LEFT', $db->quoteName('#__usergroups','groups') .
		' ON (' . $db->quoteName('map.group_id') . 
			' = ' . $db->quoteName('groups.id') . ')')
	->join('LEFT', $db->quoteName('eu_members','members') .
		' ON (' . $db->quoteName('users.id') . 
			' = ' . $db->quoteName('members.member_id') . ')')
	
	->where($db->quoteName('groups.title') . 
		' = ' . $db->quote($group_title))		
	->order($db->quoteName('members.status'))
	->order($db->quoteName('users.username'))
	;
	
	$db->setQuery($query);
	$userlist = $db->loadObjectList();
	
	return $userlist;
}
#-------------------------------------------------------
function show_group_membership($groupname, $userlist)
{
	echo "<strong>$groupname</strong> group<br/>";
	if (empty($userlist)) {
		echo "This group has no members<br/>";
	} else {
		echo "<table id=table_1>";
		echo "<th>Username (Joomla!)</th>";
		echo "<th>EU Name</th>";
		echo "<th>EU Status</tr>";
		
		foreach ($userlist as $row) {
			echo "<tr>";
			echo "<td>$row->user_username</td>";
			echo "<td>$row->member_fname $row->member_lname</td>";
			echo "<td>$row->member_status</td>";
			echo "</tr>";
		}
		echo "</table>";
	}
	echo "<br/>";
}
/*------------------------------------------------------------------------*
 * Main body.                                                             *
 *------------------------------------------------------------------------*/

show_instructions();

$groupnames = array('Administrator','Manager','Super Users');
$eu_groups = get_eu_group_list();
$eu_groupnames = array_map(
	create_function('$o', 'return $o->group_title;'), $eu_groups);
$groupnames = array_merge($groupnames, $eu_groupnames);
foreach ($groupnames as $groupname) {
	$userlist = find_group_membership($groupname);
	show_group_membership($groupname, $userlist);
}
?>

