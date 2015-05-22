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



</style>
</head>

<body>  
	<!-- 
	<h2>   Experience Unlimited EDD Monthly Report    </h2> 		
	--!>		 
	<!-- 
	* Modification log:
	*-------------------------------------------------------------------------
	* 2015/04/18	bb	v1.1	make output into a table, add Back button
	--!>		 

    <b>Reporting Month, Year: 
	<?php echo strftime('%B, %Y', strtotime("-1 month")); ?>  
	</b>
	
  

  
  
    <p></p><b>Today's Date:  
	<?php echo strftime('%m/%d/%Y'); ?>
	</b>
  
  <br><br>
  
			 

<table id=table_1>


<tr><td>1.</td>
<td>Active members (start of month)</td>
 
	<?php 
	  $db = JFactory::getDBO();
      $query = 
		"SELECT member_id as 'Member ID', 
		first_name as 'First Name', 
		last_name as 'Last Name', 
		date(active_date) as 'Active Date', 
		email_address as 'Email Address'
		from eu_members 
		where (status IN ('a', 'A') and 
		active_date <= date(last_day(date_add(last_day(now()), interval -2 month ) ) ) )
		or (upper(status) IN ('I',  'G', 'GU', 'GP') and
		inactive_date > date( last_day( date_add( last_day( now( ) ) , INTERVAL -2 MONTH ) ) ) )		
		order by last_name, first_name;";
	  $db->setQuery($query);
	  $active_members_beg_mnth = $db->loadObjectList(); 	
	  $active_members_beg_mnth_count = count ($active_members_beg_mnth);
	  echo  "<td align=right>" . $active_members_beg_mnth_count . "</td>";
	?>
  </tr>
  
  
  <tr><td>2.</td>
  <td> Members enrolled during the month (new or reinstated)</td>

	<?php 
	  $db = JFactory::getDBO();
      $query = 
		"SELECT member_id as 'Member ID', 
		first_name as 'First Name', 
		last_name as 'Last Name', 
		date(active_date) as 'Active Date', 
		email_address as 'Email Address' 
		from eu_members where upper(status) = 'A' 
		and active_date > date(last_day(date_add(last_day(now()), interval -2 month)))  
		and active_date <= date(last_day(date_add(last_day(now()), interval -1 month)))
		order by last_name, first_name;";
	  $db->setQuery($query);
	  $active_members_new = $db->loadObjectList(); 	
	  $active_members_new_count = count ($active_members_new);
	  
	  echo "<td align=right>" . $active_members_new_count . "</td>";
	?>
	</tr>
	
	
    <tr><td>3.</td>
    <td> Member exits (Employment)</td>
     
  <?php 
	  $db = JFactory::getDBO();
      $query = 
		"SELECT member_id AS 'Member ID', 
		first_name AS 'First Name', 
		last_name AS 'Last Name', 
		date( inactive_date ) AS 'Gold Card Date', 
		email_address AS 'Email Address'
		FROM eu_members 
		WHERE inactive_date > date( last_day( date_add( last_day( now( ) ) , INTERVAL -2 MONTH ) ) )
		AND inactive_date <= date( last_day( date_add( last_day( now( ) ) , INTERVAL -1	MONTH ) ) )
		AND upper(status) IN ('GU', 'GP', 'G')
		ORDER BY last_name, first_name";
	  $db->setQuery($query);
	  $member_exits_empl = $db->loadObjectList(); 	
	  $member_exits_empl_count = count ($member_exits_empl);
	  echo  "<td align=right>" . $member_exits_empl_count . "</td>";
	?> 
	</tr>
	
	
    <tr><td>4.</td>
    <td> Member exits (Made Inactive Status)</td>
     
	<?php 
	  $db = JFactory::getDBO();
      $query = 
		"SELECT member_id AS 'Member ID', 
		first_name AS 'First Name', 
		last_name AS 'Last Name', 
		date( inactive_date ) AS 'Gold Card Date', 
		email_address AS 'Email Address'
		FROM eu_members 
		WHERE inactive_date > date( last_day( date_add( last_day( now( ) ) , INTERVAL -2 MONTH ) ) )
		AND inactive_date <= date( last_day( date_add( last_day( now( ) ) , INTERVAL -1	MONTH ) ) )
		AND upper(status) = 'I'
		ORDER BY last_name, first_name;";
	  $db->setQuery($query);
	  $member_exits_other = $db->loadObjectList(); 	
	  $member_exits_other_count = count ($member_exits_other);
	  echo  "<td align=right>" . $member_exits_other_count . "</td>";
	?>
	</tr>
	
    <tr><td>5.</td>
    <td> Active members (end of month)</td>
    
	<?php 
	echo "<td align=right>" . ($active_members_beg_mnth_count + $active_members_new_count - $member_exits_empl_count - $member_exits_other_count) . 
	"</td>";

	?>
	</tr>    
	
	
	<tr><td>6.</td>
	<td> Total number of volunteer hours logged</td>

 
	<?php 
	  $db = JFactory::getDBO();
      $query = 
		"SELECT sum(eu_member_hours.task_hours)
		from eu_member_hours   
		left join eu_members on eu_member_hours.member_id = eu_members.member_id
		where ((eu_members.status IN ('a', 'A') and 
		eu_members.active_date <= date(last_day(date_add(last_day(now()), interval -1 month ) ) ) ) 
		or (eu_members.status IN ('i', 'I', 'g', 'G') and
		eu_members.inactive_date > date( last_day( date_add( last_day( now( ) ) , INTERVAL -1 MONTH ) ) ) ) ) 
		and eu_member_hours.task_date > date(last_day(date_add(last_day(now()), interval -2 month)))
		and eu_member_hours.task_date <= date(last_day(date_add(last_day(now()), interval -1 month)))
		and eu_member_hours.task_id <> 1
		and eu_member_hours.task_id <> 22
		and eu_member_hours.task_hours >0;";
	  $db->setQuery($query);
	  $volunteer_hours_sum = intval ($db->loadResult()); 	
	  echo  "<td align=right>" . $volunteer_hours_sum . "</td>";
	?>

	</tr>
	
	
    <tr><td>6a.</td>
    <td> Total number of members who volunteered time   :  &nbsp &nbsp&nbsp     </td>

	<?php 
	  $db = JFactory::getDBO();
      $query = 
		"SELECT count(distinct(eu_member_hours.member_id))
		from eu_member_hours 
		left join eu_members on eu_member_hours.member_id = eu_members.member_id
		where ((upper(eu_members.status) = 'A' and 
		eu_members.active_date <= date(last_day(date_add(last_day(now()), interval -1 month ) ) ) ) 
		or (upper(eu_members.status) IN ('I', 'GU', 'G', 'GP') and
		eu_members.inactive_date > date( last_day( date_add( last_day( now( ) ) , INTERVAL -1 MONTH ) ) ) ) ) 
		and eu_member_hours.task_date > date(last_day(date_add(last_day(now()), interval -2 month)))
		and eu_member_hours.task_date <= date(last_day(date_add(last_day(now()), interval -1 month)))
		and eu_member_hours.task_id <> 1
		and eu_member_hours.task_id <> 22
		and eu_member_hours.task_hours >0;";
	  $db->setQuery($query);
	  $member_volunteers_count = $db->loadResult(); 	
	  echo  "<td align=right>" . $member_volunteers_count . "</td>";
	?> 
	</tr>
	</table>
    <br/><input name="back" type="button" value="Back" onClick="history.go(-1)">

  

<!-- Retain this Veterans report code division. It may be a requested report inclusion later
 
  <p style="font-size:16px">7. Active or Pending Military Veteran members (start of month) :  &nbsp &nbsp&nbsp 
	<?php 
	  $db = JFactory::getDBO();
      $query = 
		"SELECT  count(*) 
		from eu_members 
		where 
		(veteran = 1  ) 
		and
		(
			(	( upper(status) = 'A' or 'P' and  
			 	( active_date <= date(last_day(date_add(last_day(now()), interval -2 month ) ) ) ) ) 
			)
			or (upper(status) IN (  'I', 'GP', 'GU', 'G') and
					inactive_date > date( last_day( date_add( last_day( now( ) ) , INTERVAL -2 MONTH ) ) )
		    		    )			
		 )   		;";
	  $db->setQuery($query);
	  $results= $db->loadResult();
	  echo  $results;
	?> </p>
 End of retained code for Veterans count -->
  
</body>

</html>  
