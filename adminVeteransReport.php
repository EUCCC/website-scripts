<!DOCTYPE html>
<html>
<head>
<style>

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

td.cen {
	text-align: center;
}

td.num {
	text-align: right;
}

table#table_1 th,
table#table_1 td {
	border: 1px solid silver;
}



</style>
</head>

<body>
	
<?php
/* Description:
*    Veteran's Report
*         
*      
*     
* Modification log:
*-------------------------------------------------------------------------
* 2015/04/18	bb	v1.1	split name to first/last columns, restyle table, table headings change,
* 							add Back button, change date format, blank 00/00/0000 dates
* 2014/09/15  bb  added &nbsp btwn last & first name in output
* 2014/07/27   js   re-created to avoid using explicit DB connect and change
*                  report format to be close to other reports
*
*/
    
 
   

   echo  "<h2>   Veterans Report v1.1</font>" ;	 
   
   $query = 'select m.first_name as euFN, m.last_name as euLN, 
  				 						m.email_address as euE, 
  				 						home_phone as euH,
  				 						mobile_phone as euM,
  				 						committee_name as euC,
  				 						member_status_desc as euS,
  				 						DATE_FORMAT(orient_date, "%m/%d/%Y") as euO,
  				 						DATE_FORMAT(active_date, "%m/%d/%Y") as euA 
  				 						from  eu_members m  
										left join eu_committees c
										on m.committee = c.committee 
										left join eu_member_statuses  s
										on m.status =   s.member_status   
										where  veteran = 1 and  upper(m.status) in ( "A", "P" )
										order by last_name, first_name';
                      	 
			
   $db = JFactory::getDBO();
   $db->setQuery($query);
   $members = $db->loadObjectList();
   
   $total = count($members);
    echo "<h4 ><font face=Verdata>  Total  Veterans: $total  </h4></font>";

    $today = date("F j, Y");
 	  echo "<h4  ><font face=Verdata>  Reporting Date : $today  </h4></font>";

   //  column headings  
     
   
   echo "<br><table  id=table_1 width=1250>";
   echo "<tr>
		<th> # </th>
		<th> First Name </th>
		<th> Last Name </th>
		<th>Email</th>
		<th> Home </th>
		<th> Mobile </th>
		<th>Committee</th>
		<th>Status </th>
		<th>Orientation Date</th>
		<th>Active Date</th>
		</tr>"; 

   //  column details
  
   $i = 0;
   foreach ($members as $member) {
      $i++;
	  echo "<tr>".
			"<td class=num>".$i."</td>".
			"<td>".$member->euFN."</td>".
			"<td>".$member->euLN."</td>".
			"<td>".$member->euE."</td>".
			"<td class=cen>".$member->euH."</td>".
			"<td class=cen>".$member->euM."</td>".
			"<td class=cen>".$member->euC."</td>".
			"<td class=cen>".$member->euS."</td>".
			"<td class=cen>" . $_ = ($member->euO=='00/00/0000' ? '' : $member->euO) . "</td>" .
			"<td class=cen>" . $_ = ($member->euA=='00/00/0000' ? '' : $member->euA) . "</td>";
	 echo  "</tr>";
      		
   }
   echo "</table>";     
	echo '<br/><input name="back" type="button" value="Back" onClick="history.go(-1)">';
?>

</body>

</html>
