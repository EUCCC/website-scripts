<!DOCTYPE html>
<html>
<head>
<style>

table#table_1 {
border: 2px solid silver;
font-size: medium;
}

table#table_1 tr:nth-child(odd) {
background-color: #DDDDDD;
}

table th {
background-color:#CCCCCC;
}

table td {
padding: 5px 5px;
}

table#table_1 th,
table#table_1 td {
border: 1px solid silver;
}

input[disabled],select[disabled] {
background: #cccccc;
}

</style>
</head>

<body>

<?php
/* Description:
*    Member Hours Delinquency Report
*         
*      
*     
* Modification log:
*-------------------------------------------------------------------------
* 2015/04/18	bb	v1.1 change table styling, lname,fname to fname lname, column order,
* 					heading seq# to #, and remove explicit email cloaking (site
* 					is using NoNumber email protector instead), added Back button
*2014/07/27   js   v1.0 re-created to avoid using explicit DB connect and change
*                  report format to be close to other reports
*
*/



// echo  "<h2 align=center><font face=Verdata>   Delinquent Membership Report </font>" ;	 

$query = 'select m.first_name as first_name, m.last_name as last_name, 
m.email_address as euE, committee_name as euC, 
sum(mh.task_hours) as euH from  eu_member_hours mh  
join  eu_members m on mh.member_id = m.member_id 
left join eu_committees c on m.committee = c.committee      
where   upper(m.status) = "A" and mh.task_date > m.active_date
group by  m.member_id 
having sum(mh.task_hours) < -32  
order by 5';

$db = JFactory::getDBO();
$db->setQuery($query);
$members = $db->loadObjectList();

$total = count($members);
//  live25: echo "<h3 align=center><font face=Verdata>  Total : $total  </h3></font>";
echo "<h4 ><font face=Verdata>  Total : $total  </h4></font>";

$today = date("F j, Y");
// live25 echo "<h3 align=center><font face=Verdata>  Reporting Date : $today  </h3></font>";
echo "<h4  ><font face=Verdata>  Reporting Date : $today  </h4></font>";

//  column headings  


echo "<br><table id=table_1>";
echo "<tr>
<th> # </th>
<th>First Name</th>
<th>Last Name</th>
<th>Email Address</th>
<th>Committee </th>
<th>Hours</th>
</tr>";


//  column details

$i = 0;
foreach ($members as $member) {
	$i++;
	
	$fname = $member->first_name;
	$lname = $member->last_name;
	
	echo "<tr>" .
		"<td align=right>".$i."</td>".
		"<td>".$fname."</td>".
		"<td>".$lname."</td>".
		"<td>".$member->euE."</td>".
		"<td>".$member->euC."</td>".
		"<td align=right>". intval($member->euH)."</td>".
		"</tr>";
		// to make email address clickable :
		//echo "<tr><td width=5% align=left height=25 >".$i."</td>".
		//		 "<td width=15% align=left height=25 >".$member->first_name."</td>".
		//      "<td width=15% align=left height=25 >".$member->last_name."</td>".
		//      "<td width=25% align=left height=25 >".$member->home_phone."</td>".
		//       "<td width=25% align=left height=25 >".$member->mobile_phone."</td>".
		//       "<td width=40% align=left height=20 >".$member->email_address."</td>".
		//       "</tr>";
	
}
echo "</table>";     
echo '<br/><input type="button" value="Back" onClick=history.go(-1)>';
?>

</body>

</html>
