<?php
/**
 * Description:
 *    This program will list out member hours/tasks detail
 *
 * PHP version 5
 * 
 * @category  EUMemberScripts
 * @package   MemberHoursDetail
 * @author    Jean Shih <jean1shih@gmail.com>
 * @author    Ben Bonham <bhbonham@gmail.com>
 * @copyright 2014-2015 Experience Unlimited
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   1.3
 * @link      https://github.com/EUCCC/website-scripts/blob/master/memberHoursDetail.php
 */

/* Modification log:
 * -------------------------------------------------------------------------
 * 05/18/2015	bb	1.3 add documentation, don't pass $db to each function
 * 4/25/15		bb	1.2	secondary ordering by task name in report, add Back button
 * 2015/01/30   bb	v1.1 created from adminHoursDetail_1.1.php
 */
 
namespace MemberHoursDetail;

echo <<<EOS
<h2>   Member Hours Detail v1.3  </h2>
EOS;

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
EOSTYLE;

$doc->addStyleDeclaration($style);
// 

// ------------------------------------------------------------------------
/**
 * Display member hours
 * 
 * @param string $member_name name of member (first last)
 * @param array  $tasks       array of objects describing tasks credited 
 *        to member since  member's active date member's active date
 * 
 * @return void
 */ 
function showHoursDetail($member_name, $tasks)
{
    $db = \JFactory::getDBO();
    /*
    * Display table with member query results 
    */
     
    $total_hours = 0;
    foreach ($tasks as $task) {
        $total_hours += $task->task_hours;
    }
    echo "<h4>Total volunteer hours for $member_name: $total_hours </h4>";  
    
    // Display column headings 
    echo '<br><table border=1 style="max-width:1000px">';
    echo '<tr style="background-color:#EBEBEB;"><th width=15%>' .
    'Task Recording Date </th><th width=30%>Task Name </th>' .
    '<th width=15%>Task Hours </th><th width=40%>' .
    'Task Hour Descriptions </th></tr>';
        
    // Display member data 
    $i = 0;
    foreach ($tasks as $task) {
        $i++;
        $tr = $i % 2 == 0 ? '<tr style="background-color:#EBEBEB;">' : 
        '<tr>';
        echo $tr;
        echo "<td align=center height=25>" . $task->task_date . "</td>" .
        "<td align=center height=25>" . $task->task_name . "</td>" .
        "<td align=center height=25>" . intval($task->task_hours) . "</td>" .
        "<td align=center height=25>" . $task->misc_task_hr_desc . "</td>" .
        "</tr>";
    }

    echo "</table>";
    echo '<br/><INPUT name="back" type="button" value="Back" ' .
    'onClick="history.back(-1)">';
    return;
}
// -----------------------------------------------------------------------
/**
 * Query database for tasks credited to member since active date
 * 
 * @param int $member_id member_id of member
 * 
 * @return array($member_name, $tasks)  $member_name is a string, 
 *             $tasks is a list of objects describing tasks
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
			and eh.task_date > em.active_date) order by 
					task_date desc, task_name asc";
    
    $db->setQuery($query);
    $tasks = $db->loadObjectList();

    return array($member_name, $tasks);
}

/*========================================================================*
 * Main body.                                                             *
 *========================================================================*/
$user = \JFactory::getUser();

$member_id = $user->id;

list($member_name, $tasks) = buildAndExecuteMemberHoursQuery($member_id);
showHoursDetail($member_name, $tasks);

?>		
