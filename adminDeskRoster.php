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

<h1>Admin Desk Roster 1.1</h1>
<?php
/* Description:
*    Admin Desk Roster Sheet
*         
*      
*     
* Modification log:
*-------------------------------------------------------------------------
* 2015/04/18	bb 	v1.1	lname,fname -> fname lname, change table styling, add Back button
* 2015/03/02 bb   change search to "Business Operations Director" (was Bus Ops Director)
*2014/07/27   js   re-created to avoid using YUI since no one knows about it for now.
*
*
*/
    
   // Get the name of contact person: Business Operations Director
      
   $query = "Select	m.first_name,	m.last_name
        from eu_members m,  eu_board_members b
        where  m.member_id = b.member_id
        and     (ucase(m.`status`) = 'A')
        and     b.board_member_status = '1'
        and     b.board_member_end_date = '0000-00-00'
        and     b.board_position = 'Business Operations Director';";
                      	 
   $db = JFactory::getDBO();
   $db->setQuery($query);
   $rows = $db->loadObjectList();
   
   $len = count($rows);
   
	 $bentry = '';
	 for ($i = 0; $i < $len; $i++) {  
			$bentry .= $rows[$i]->first_name . " ";
			
      if ($i == $len-1)  
           $bentry .= $rows[$i]->last_name; 
      else  
           $bentry .= $rows[$i]->last_name . " or ";     
       
};
   
   echo  "<font size=2>    For help, please contact:   $bentry    </font>" ;
 
   $today = date("m-d-Y");
   
 	 echo "<h4> Active Membership As of : $today  </h4>";
 	 
   //  column headings  
     
   
   echo "<table  id=table_1>";
   echo "<tr>
   <th> # </th>
   <th>First Name</th>
   <th>Last Name</th>
   <th>Home </th>
   <th>Mobile</th>
   <th>Email Address</th></tr>";
   
   $query = "select first_name, last_name, home_phone, mobile_phone, email_address ".
            "from  eu_members ".
            "WHERE upper(status) like '%A' ".
            "order by last_name, first_name";
                      	 
   $db = JFactory::getDBO();
   $db->setQuery($query);
   $members = $db->loadObjectList();
   
   //  column details
  
   $i = 0;
   foreach ($members as $member) {
      $i++;
       
      $fname = $member->first_name;
      $lname = $member->last_name;
      
		  echo "<tr>".
				"<td>".$i."</td>".
				"<td>".$fname."</td>".
				"<td>".$lname."</td>".
				"<td >".$member->home_phone."</td>".
				"<td>".$member->mobile_phone."</td>".
				"<td>".JHtml::_('email.cloak', $member->email_address, 0)."</td>".
				"</tr>";
   }
   echo "</table>";     
   echo '<br/><input type="button" value="Back" onClick=history.go(-1)>';
   
?>
</body>
