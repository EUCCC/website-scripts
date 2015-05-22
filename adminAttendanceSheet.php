<!DOCTYPE html>
<html>
<head>
<style>

table#table_1 {
border: 1px solid silver;
}

<-- 
table#table_1 tr:nth-child(odd) {
background-color: #DDDDDD;
}
-->

table th {
background-color:#CCCCCC;
}

table td {
padding: 0px 5px;
}

<--
table#table_1 th,
table#table_1 td {
border: 1px solid silver;
}
-->

table#table_1 tr:nth-child(5n+1) {
border-bottom: 1px solid silver;
}

td.num {
	text-align: right;
}

input[disabled],select[disabled] {
background: #cccccc;
}

</style>
</head>

<body>
	
<?php
/* Description:
*    Admin Attendance Sheet
*         
*      
*     
* Modification log:
*-------------------------------------------------------------------------
*2014/04/28   bb   changed Seq#->#, name order, table styling
*2014/07/27   js   re-created to avoid using YUI since no one knows about it for now.
*                   
*
*/

      
   //  Comment out to use Joomla heading:
   // echo  "<font size=4> <b>   Attendance Sheet Only    </font></b>" ;
 
   $nexttuesday = date('m-d-Y', strtotime('next tuesday'));
   
 	 echo "<font size=3>  Meeting Date : $nexttuesday  </font>";
  echo " <br/><br/>";
   //  column headings  
     
   
   echo "<table id=table_1>";
   echo "<tr>
	   <th> # </th>
	   <th> Present </th>
	   <th>First Name</th>
	   <th>Last Name</th>
	   <th>Status </th>
	   <th>Committee</th>
	   <th>Hours</th>
	   </tr>";
   
   $query = "SELECT
						m.last_name 			AS euLN,
						m.first_name			AS euFN,
						m.`status` 				AS euScode,
						s.member_status_desc 	AS euS,
						c.committee_name 		AS euC,
						(SELECT FORMAT(sum(h.task_hours),0) FROM eu_member_hours h 
							WHERE ((m.member_id = h.member_id) AND (h.task_date > m.active_date) AND (ucase(m.`status`) = 'A'))
							)						AS euH
						FROM eu_members m
						LEFT JOIN eu_member_statuses s ON m.`status` = s.member_status
						LEFT JOIN eu_committees c ON m.committee = c.committee
						WHERE (ucase(m.`status`) IN ('A','P', 'S'))
						ORDER BY m.last_name, m.first_name;";
                      	 
   $db = JFactory::getDBO();
   $db->setQuery($query);
   $members = $db->loadObjectList();
   
   //  column details
  $present = '<div style="width:30px;height:13px;border:1px solid #000;"></div>';
	//$present =  ' [ <span style="padding-left:30px">]</span>';
   $i = 0;
   foreach ($members as $member) {
		$i++;
		echo "<tr>";
		echo "<td class=num>".$i."</td>".
			"<td>".$present."</td>".
			"<td>".$member->euFN."</td>".
			"<td>".$member->euLN."</td>".
			"<td>".$member->euS."</td>".
			"<td>".$member->euC."</td>".
			"<td class=num>".$member->euH."</td> ";
		echo  "</tr>";
   }
   echo "</table>";     
   echo '<br/><input type="button" value="Back" onClick=history.go(-1)>';

?>

</body>

</html>
