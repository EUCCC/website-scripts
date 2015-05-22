<?php
namespace AdminMemberQuery;

/**
 * Member database admin query page/script for Experience Unlimited Joomla! website
 * 
 * Member SQL database admin query page/script for Experience Unlimited Joomla! website.
 * The query page allows admininstrators to search the membership database using various
 * search keys. The page has controls at the top and displays search results in 
 * tabular form below. Search controls (i.e., keys) are: first &last name, 
 * email address, start, end, active, and inactive dates of active membership, US
 * Armed Forces Veteran status,  
 * membership status, and committee membership. Member info table displays keys  
 * for each member matching the query, veteran status, membership hours balance, 
 * and also home & mobile phone #s (if these are in the database).
 * 
 * 
 * @package    admin-member-query-scgb.php
 * @author     Stephen Gelardi <Stephen.Gelardi@sbcglobal.net>
 * @author     Ben Bonham <bhbonham@gmail.com>
 * @minor edit Roger Laurel <RogerLaurel@sbcglobal.net>
 * @copyright  2014 Experience Unlimited
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    1.13
 */

/*
 * Summary of revisions28/201`
 * 1.13 -- bb -- 04/15/2015 -- change date format, blank 0/0/00 dates, 
 * 				blank "Not Selected" and "Undecided" in table, darken "disabled" controls, 
 * 				change column order, change title "Jobclass" to "Job Class", add columns
 * 				for city, state, and zip, format phone #s, add Back button, restyle table
 * 1.12.1 -- bb -- 04/09/15 -- turn off email protector for the members table and revert ' AT ' to '@' change -- only admins have access to 
 *              this script anyway, so no need to cloak emails
 * 1.12 - bb, email protector still causing problems after update to 1.3.9, so replace @-sign in email address with ' AT ' string to hide 
 *          email addresses from email protector. (ironic, isn't it.) need to contact NoNumber to resolve this, it seems...
 * 1.11 - bb, revert turn-off of output buffering (is now back on) - timeout was probably caused by NoNumber Email Protector plugin
 * 1.10 - bb, turn off output buffering (was creating 504 gateway timeout errors for large tables), blocksize to 5000, change styling of table output
 * 1.9 - rl, change default search to "All Selected" from "Active" 3/28/2015, search block to 500 (was 30) [bb]
 * 1.8 - bb, change $blocksize to constant BLOCKSIZE
 * 			 hide contact info for Gold Card Unreachable members
 * 1.7 - bb, limit search to current board members only
 * 1.6 - bb, add jobclass and industry columns to table
 * 1.5 - bb, redo logic to ensure loading of pulldown menus
 * 1.4 - bb, added fix for us date
 * 1.3 - bb, added board member to search parameters (use eu_board_members table)
 * 1.2 - bb, added </form> tag
 * 1.1 - bb, Use NoNumber email cloaking rather than Joomla! stock cloaking (which did not work)
 */

echo <<<EOS
<h1>Admin Member Query v1.13</h1>
EOS;

/*
 * Displaying query result table that has too many rows of bogs down the 
 * sql server and may cause a timeout, so the table can be paginated by
 * setting BLOCKSIZE, which is the max # of member entries to display on one page
 */
define('BLOCKSIZE', 5000);
 
###########################################################################

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
}
	
table td {
        padding: 0px 5px;
}

table#table_1 th,
table#table_1 td {
	border: 1px solid silver;
}

table#table_1 tr td:nth-child(8) {
	text-align: right;
}

table#table_1 tr td:nth-of-type(9) {
	text-align: center;
}

table#table_1 tr td:nth-of-type(16) {
	text-align: center;
}
input[disabled],select[disabled] {
    background: #cccccc;
}

SSS;

$doc->addStyleDeclaration($style);

###########################################################################

#------------------------------------------------------------------------
function reset_search_parms($mysession)
{
    /*
     * Clear or reset parameters used to build database query
     */
	$srch_parms = array();
    $srch_parms['fname'] = "";
    $srch_parms['lname'] = "";
    $srch_parms['email'] = "";
    $srch_parms['vet_stat'] = "AL";
    $srch_parms['board_stat'] = "AL";
    $srch_parms['committee'] = "AL";
    # limit initial/default display to members with "All Selected" status (change from "Active" rl 3/28/2015 
    $srch_parms['status'] = "";
    $srch_parms['from_active_date'] = ''; // "1/1/1900";
    $srch_parms['to_active_date'] = ''; // "12/31/2100";
    $srch_parms['from_inactive_date'] = ''; // "1/1/1900";
    $srch_parms['to_inactive_date'] = ''; // "12/31/2100";
    $srch_parms['from_orient_date'] = ''; // "1/1/1900";
    $srch_parms['to_orient_date'] = ''; // "12/31/2100";

    $mysession->set('startrow', 0);

    return $srch_parms;
}
#------------------------------------------------------------------------
function validated_date($date_strg)
{
	$new_date_strg = '';

        // joomla will correctly interpret us dates with slashes (mm/dd/yy
        // or mm/dd/yyyy) but interprets dates as intl if they have dashes 
        // (dd-mm-yy or dd-mm-yyyy)
        $us_dash_pat = "/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{2,4})$/";
        if (preg_match($us_dash_pat, $date_strg)) {
                $date_strg = str_replace("-","/",$date_strg);
        }

	if (empty($date_strg)) {
	} else if (is_numeric($date_strg)) {
		// don't allow unix timestamps here
		echo '<br> Bad format for Active Date <br>';
	} else if (date_create($date_strg)) {
		$new_date_strg = \JFactory::getDate($date_strg);
		$new_date_strg = $new_date_strg->format('m/d/Y');
	} else {
		echo '<br> Bad format for Active Date <br>';
	}
	return $new_date_strg;
}
#------------------------------------------------------------------------
function formatted_phone($phone_strg)
{
	$ph_pat = "/^\D*(\d{3})\D*(\d{3})\D*(\d{4})\D*$/"; // interpret very messy phone #'s
	
	if (empty($phone_strg)) {
		return '';
	} else if (preg_match($ph_pat, $phone_strg, $matches_out)) {
		$new_phone_strg = '(' . $matches_out[1] . ')&nbsp' . $matches_out[2] . '-' . $matches_out[3];
		return $new_phone_strg;
	} else {
		return "bad format:" . $phone_strg;
	}
}#------------------------------------------------------------------------
function load_search_parms($postdata)
{
	$srch_parms = array();
    $srch_parms['fname'] = $postdata->get('fname','','STRING');
    $srch_parms['lname'] = $postdata->get('lname','','STRING');
    $srch_parms['email'] = $postdata->get('email','','STRING');
    $srch_parms['city'] = $postdata->get('city','','STRING');
    $srch_parms['state'] = $postdata->get('state','','STRING');
    $srch_parms['zip'] = $postdata->get('zip','','STRING');
    $srch_parms['vet_stat'] = $postdata->get('vet_stat','AL','ALNUM');
    $srch_parms['board_stat'] = $postdata->get('board_stat','AL','ALNUM');
    $srch_parms['status'] = $postdata->get('status','A','WORD');
    $srch_parms['committee'] = $postdata->get('committee','AL','WORD');

    $srch_parms['from_active_date'] = 
		validated_date($postdata->get('from_active_date','','STRING'));
    $srch_parms['to_active_date'] = 
		validated_date($postdata->get('to_active_date','','STRING'));
    $srch_parms['from_inactive_date'] = 
		validated_date($postdata->get('from_inactive_date','','STRING'));
    $srch_parms['to_inactive_date'] = 
		validated_date($postdata->get('to_inactive_date','','STRING'));
    $srch_parms['from_orient_date'] = 
		validated_date($postdata->get('from_orient_date','','STRING'));
    $srch_parms['to_orient_date'] = 
		validated_date($postdata->get('to_orient_date','','STRING'));
    return $srch_parms;

}
#------------------------------------------------------------------------
function hide_contact_if_unreachable($member)
{
	if ($member->status == 'GU') {
		$member->home_phone = '*HIDDEN*';
		$member->mobile_phone = '*HIDDEN*';
		$member->email_address = '*HIDDEN*';
	}
	return $member;
}#------------------------------------------------------------------------
function show_member_data($members, $mysession)
{

    echo "{emailprotector=off}"; // turn off email protector plugin
	$bid = $mysession->get('bid');
	$bdesc = $mysession->get('bdesc');
    /*
     * Display table with member query results 
     */
     
    # Display column headings 
    echo "<br><table id='table_1'>";		
    echo '<tr>
	    <th>First Name</th>
		<th>Last Name</th>
        <th>Home Phone</th>
        <th>Mobile Phone</th>
        <th>Email</th>
        <th>Status</th>
        <th>Committee</th>
        <th>Hours</th>
        <th>Veteran</th>
        <th>Active Date</th>
        <th>Inactive Date</th>
        <th>Orientation Date</th>
        <th>Industry</th>
        <th>Job Class</th>
        <th>City</th>
        <th>State</th>
        <th>Zip</th>
        <th>Board Title</th>
        </tr>';
        
    # Display member data

    foreach ($members as $member) {
		$member->home_phone = formatted_phone($member->home_phone);
		$member->mobile_phone = formatted_phone($member->mobile_phone);
		$member = hide_contact_if_unreachable($member);
        $email_address = $member->email_address;
        $board_title = $bdesc[array_search($member->board_id, $bid)];
        echo "<tr>" . 
        "<td>" . $member->first_name . "</td>" .
        "<td>" . $member->last_name . "</td>" .
        "<td>" . $member->home_phone . "</td>" .
        "<td>" . $member->mobile_phone . "</td>" .
        "<td>" . $email_address . "</td>" .
        "<td>" . $member->member_status_desc . "</td>" .
		"<td>" . $_ = ($member->committee_name === 'Undecided' ? '' : $member->committee_name) . "</td>" .
        "<td>" . (int) $member->hours_balance . "</td>" .
        "<td>" . ($member->veteran==0 ? "No" : "Yes") . "</td>" .
		"<td>" . $_ = ($member->active_date=='00/00/0000' ? '' : $member->active_date) . "</td>" .
		"<td>" . $_ = ($member->inactive_date=='00/00/0000' ? '' : $member->inactive_date) . "</td>" .
		"<td>" . $_ = ($member->orient_date=='00/00/0000' ? '' : $member->orient_date) . "</td>" .
		"<td>" . $_ = ($member->industry_name === 'Not Selected' ? '' : $member->industry_name) . "</td>" .
		"<td>" . $_ = ($member->jobclass_name === 'Not Selected' ? '' : $member->jobclass_name) . "</td>" .
        "<td>" . $member->city . "</td>" .
        "<td>" . $member->state . "</td>" .
        "<td>" . $member->zip . "</td>" .
        "<td>" . ($member->board_id==1 ? "" : "$board_title") . "</td>" ;
       echo "</tr>";
    }

    echo "</table>";
    return;
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
function show_search_form($srch_parms,$db,$mysession)
{    
    /*
     *  Set up/display controls for search 
     *  First column: first name, last name, email, member status, committee, veteran status, board position
     *  Second column: Date From and Date To: Active Status, Inactive Status, Orientation
     */

    loadSessionArraysForSearch($db,$mysession);
    echo '<form method="POST">';
    
    echo '<br><table>';
    # first column: first name, last name, email address, and committee
    echo '<td valign="top"><table>';
    # first name 
    echo '<tr><td class="blabel">First&nbspName</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="fname" ' .
        'value="' . $srch_parms['fname'] . '"></td></tr>';
    
    # last name 
    echo '<tr><td class="blabel">Last&nbspName</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="lname" ' .
        'value="' . $srch_parms['lname'] . '"></td></tr>';
           
    # email address 
    echo '<tr><td class="blabel">Email&nbspAddress</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="email" ' .
        'value="' . $srch_parms['email'] . '"></td></tr>';
    
    # status 
    insertPulldownMenu('Member&nbspStatus', 'status', 
        $mysession->get('sid'), $mysession->get('sdesc'), '', 
        $srch_parms['status']);
        
    # committee 
    insertPulldownMenu('Committee', 'committee', 
        $mysession->get('cid'), $mysession->get('cname'), '', 
        $srch_parms['committee']);

    # veteran status 
    insertPulldownMenu('Veteran', 'vet_stat', 
        $mysession->get('vid'), $mysession->get('vdesc'), '', 
        $srch_parms['vet_stat']);

    # board status 
    insertPulldownMenu('Board Status', 'board_stat', 
        $mysession->get('bid'), $mysession->get('bdesc'), '', 
        $srch_parms['board_stat']);

    echo '</table></td>';
    
    # second column: status, industry, jobclass, From active date, To active date
    echo '<td valign="top"><table>';
    # active date - from 
    echo '<tr><td class="blabel">Active&nbspDate&nbsp-&nbspFrom</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="from_active_date" ' .
        'value="' . $srch_parms['from_active_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
    
    # active date - to 
    echo '<tr><td class="blabel">Active&nbspDate&nbsp-&nbspTo</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="to_active_date" ' .
        'value="' . $srch_parms['to_active_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';

    # inactive date - from 
    echo '<tr><td class="blabel">Inactive&nbspDate&nbsp-&nbspFrom</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="from_inactive_date" ' .
        'value="' . $srch_parms['from_inactive_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
    
    # inactive date - to 
    echo '<tr><td class="blabel">Inactive&nbspDate&nbsp-&nbspTo</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="to_inactive_date" ' .
        'value="' . $srch_parms['to_inactive_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
   
    # orient date - from 
    echo '<tr><td class="blabel">Orientation&nbspDate&nbsp-&nbspFrom</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="from_orient_date" ' .
        'value="' . $srch_parms['from_orient_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
    
    # orient date - to 
    echo '<tr><td class="blabel">Orientation&nbspDate&nbsp-&nbspTo</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="to_orient_date" ' .
        'value="' . $srch_parms['to_orient_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
   
    echo '</table></td>';
    
    echo '</table>';
    
    echo '<input type="submit" value="Search" name="action">' .
         '&nbsp<input type="submit" value="Reset" name="action">' .
         '&nbsp<input type="button" value="Back" onClick=history.go(-1)>';

    echo "<input type='hidden' name='process' value='1'>";

    echo '</form>';

    return;
}
#------------------------------------------------------------------------
function show_search_results($db, $mysession, $srch_parms, $postdata)
{
    /*
     * Display search results
     * if BLOCKSIZE is less than the # of members returned by the query, this will
     * break the results into BLOCKSIZE blocks and include Next/Previous buttons on the 
     * page 
     */
     
    echo '<form method="POST">';
    
	# determine which rows of search should be displayed on current page 
	$startrow = $mysession->get('startrow',0);
	
	$action = $postdata->get('action','','STRING');

    if (!empty($action) && $action == "Next block") {
        $startrow = $startrow + BLOCKSIZE;
    } elseif (!empty($action) && $action == "Previous block") {
        $startrow = $startrow - BLOCKSIZE;
    } else {
        $startrow = 0;        # this is a new search 
    }

    $mysession->set('startrow', $startrow);

	$query = buildQueries($db,$srch_parms);
	$db->setQuery($query);
	$db->query();
	$cnt = $db->getNumRows();
	echo '<br>Search returns ' . $cnt . ' entries';
	$db->setQuery($query,$startrow,BLOCKSIZE);
	
	$members = $db->loadObjectList();
	
	echo '-- now displaying entries ' . (1+$startrow) . ' through ' . 
		($startrow + count($members) ) . '<br>';
	
	# show buttons to display next and/or previous blocks of rows if needed
	if ($startrow - BLOCKSIZE >= 0) {
		echo '<input type="submit" value="Previous block" name="action">';
	}
	if ($startrow + count($members) < $cnt) {
		echo '<input type="submit" value="Next block" name="action">';
	}
	
    echo "<input type='hidden' name='fname' value=$srch_parms[fname]>";
    echo "<input type='hidden' name='lname' value=$srch_parms[lname]>";
    echo "<input type='hidden' name='email' value=$srch_parms[email]>";
    echo "<input type='hidden' name='city' value=$srch_parms[city]>";
    echo "<input type='hidden' name='state' value=$srch_parms[state]>";
    echo "<input type='hidden' name='zip' value=$srch_parms[zip]>";
    echo "<input type='hidden' name='status' value=$srch_parms[status]>";
    echo "<input type='hidden' name='vet_stat' value=$srch_parms[vet_stat]>";
    echo "<input type='hidden' name='board_stat' value=$srch_parms[board_stat]>";
    echo "<input type='hidden' name='committee' value=$srch_parms[committee]>";    
    echo "<input type='hidden' name='to_active_date' value=$srch_parms[to_active_date]>";
    echo "<input type='hidden' name='from_active_date' value=$srch_parms[from_active_date]>";
    echo "<input type='hidden' name='to_inactive_date' value=$srch_parms[to_inactive_date]>";
    echo "<input type='hidden' name='from_inactive_date' value=$srch_parms[from_inactive_date]>";
    echo "<input type='hidden' name='to_orient_date' value=$srch_parms[to_orient_date]>";
    echo "<input type='hidden' name='from_orient_date' value=$srch_parms[from_orient_date]>";

    echo "<input type='hidden' name='process' value='1'>";

    echo '</form>';
	
	show_member_data($members, $mysession);

    return;
}

#------------------------------------------------------------------------
function loadSessionArraysForSearch($db, $mysession)
{
	loadVeteransArray($db, $mysession);
	loadBoardArray($db, $mysession);
	loadCommitteeArray($db, $mysession);
	loadStatusesArrayForSearch($db, $mysession);
	return;
}
#------------------------------------------------------------------------
function loadVeteransArray($db, $mysession)
{
	$vid = array("AL","0","1");
	$vdesc = array("Not Selected","No", "Yes");
	
	$mysession->set('vid', $vid);
	$mysession->set('vdesc', $vdesc);

   return;
}
#------------------------------------------------------------------------
function loadBoardArray($db, $mysession)
{
    $query = "SELECT board_id, board_title " .
        "FROM eu_board_positions " .
        "ORDER BY board_id";
    
    $db->setQuery($query);
    $statuses = $db->loadObjectList();
    
    $bid = array();
    $bdesc = array();
    
    $bid[] = "AL";
    $bdesc[] = "Not Selected";
    $bid[] = "AB";
    $bdesc[] = "Any Board Member";
    foreach ($statuses as $status) {
        $bid[] = $status->board_id;
        $bdesc[] = $status->board_title;
    }
	$mysession->set('bid', $bid);
	$mysession->set('bdesc', $bdesc);
    return;
}
#------------------------------------------------------------------------
function loadStatusesArrayForSearch($db, $mysession)
{
    $query = "SELECT member_status, member_status_desc " .
        "FROM eu_member_statuses " . "WHERE member_status <> 'D' " .
        "ORDER BY member_status_desc";
    
    $db->setQuery($query);
    $statuses = $db->loadObjectList();
      
    $sid = array();
    $sdesc = array();
    $sid[] = 'AL';
    $sdesc[] = 'All Selected';
    foreach ($statuses as $status) {
		$sid[] = $status->member_status;
		$sdesc[] = $status->member_status_desc;
	}
	$mysession->set('sid', $sid);
	$mysession->set('sdesc', $sdesc);
	
    return;
}
#------------------------------------------------------------------------
function loadCommitteeArray($db, $mysession)
{  
    $query = "SELECT committee, committee_name " .
        "FROM eu_committees " .
        "ORDER BY committee_name";
    
    $db->setQuery($query);
    $committees = $db->loadObjectList();
    
    $cid = array();
    $cname = array();
    $cid[] = 'AL';
    $cname[] = 'All Selected';
    foreach ($committees as $committee) {
		$cid[] = $committee->committee;
		$cname[] = $committee->committee_name;
	}
	$mysession->set('cid', $cid);
	$mysession->set('cname', $cname);
	
    return;
}
#------------------------------------------------------------------------
function buildQueries($db,$srch_parms)
{

	$query = $db->getQuery(true);
	$query
	->select($db->quoteName('em.first_name'))
	->select($db->quoteName('em.last_name'))
	->select($db->quoteName('em.home_phone'))
	->select($db->quoteName('em.mobile_phone'))
	->select($db->quoteName('em.email_address'))
	->select($db->quoteName('em.city'))
	->select($db->quoteName('em.state'))
	->select($db->quoteName('em.zip'))
	->select($db->quoteName('es.member_status_desc'))
	->select($db->quoteName('ec.committee_name'))
	->select($db->quoteName('ei.industry_name'))
	->select($db->quoteName('ej.jobclass_name'))
	->select('DATE_FORMAT(' . $db->quoteName('em.active_date') . ', "%m/%d/%Y") AS ' .
			$db->quoteName('active_date'))
	->select($db->quoteName('em.status'))
	->select('DATE_FORMAT(' . $db->quoteName('em.inactive_date') . ', "%m/%d/%Y") AS ' .
			$db->quoteName('inactive_date'))
	->select('DATE_FORMAT(' . $db->quoteName('em.orient_date') . ', "%m/%d/%Y") AS ' .
			$db->quoteName('orient_date'))
	->select($db->quoteName('em.veteran'))
    ->select('IFNULL(ep.board_id,"1") AS board_id')
	->select('SUM(' . $db->quoteName('eh.task_hours') . ') AS ' . 
	         $db->quoteName('hours_balance'))

	->from($db->quoteName('eu_members','em'))
	->where($db->quotename('em.status') . ' <> "D"')
	->join('LEFT', $db->quotename('eu_member_hours', 'eh') . 
		' ON ' . $db->quotename('eh.member_id') . 
		' = ' . $db->quotename('em.member_id') .
		' AND ' . $db->quotename('eh.task_date') . 
		' >= ' . $db->quotename('em.active_date')) 
	->join('LEFT', $db->quoteName('eu_member_statuses','es') .
		' ON ' . $db->quoteName('es.member_status') . 
		' = ' . $db->quoteName('em.status'))
	->join('LEFT', $db->quoteName('eu_committees','ec') .
		' ON ' . $db->quotename('ec.committee') .
		' = ' . $db->quoteName('em.committee'))
	->join('LEFT', $db->quotename('eu_board_members', 'eb') . 
		' ON (' . $db->quotename('eb.member_id') . 
		' = ' . $db->quotename('em.member_id') . 
        ' AND ' . $db->quoteName('eb.board_member_status') . ' = 1)') 
    ->join('LEFT', $db->quoteName('eu_board_positions', 'ep') .
        ' ON ' . $db->quotename('eb.board_position') .
        ' = ' . $db->quotename('ep.board_title'))
    ->join('LEFT', $db->quoteName('eu_industries', 'ei') .
        ' ON ' . $db->quotename('ei.industry_id') .
        ' = ' . $db->quotename('em.industry_id'))
    ->join('LEFT', $db->quoteName('eu_jobclasses', 'ej') .
        ' ON ' . $db->quotename('ej.jobclass_id') .
        ' = ' . $db->quotename('em.jobclass_id'))

 	->group($db->quoteName('em.member_id'))

	->order($db->quoteName('em.last_name'))
        ->order($db->quoteName('em.first_name'));

    if (!empty($srch_parms['fname'])) {
        $fname = $srch_parms['fname'];
	$fname = '%' . $db->escape($fname, true) . '%';
        $query->where($db->quoteName('em.first_name') . 
		' LIKE ' . $db->quote($fname, false));
    }
    if (!empty($srch_parms['lname'])) {
        $lname = $srch_parms['lname'];
	$lname = '%' . $db->escape($lname, true) . '%';
        $query->where($db->quoteName('em.last_name') . 
		' LIKE ' . $db->quote($lname, false));
    }
    if (!empty($srch_parms['email'])) {
        $email = $srch_parms['email'];
	    $email = '%' . $db->escape($email, true) . '%';
        $query->where($db->quoteName('em.email_address') . 
		' LIKE ' . $db->quote($email, false));  // checker for email?
    }
    
    
    if (!empty($srch_parms['status']) && $srch_parms['status'] != "AL") {
        $status = $srch_parms['status'];
        $query->where($db->quoteName('em.status') . 
		' = ' . $db->quote($status));
    } else if (empty($srch_parms['status'])) {
        # limit initial/default display to active members only 
        $query->where($db->quoteName('em.status') . " = 'A'");
    }
    if (isset($srch_parms['committee']) && $srch_parms['committee'] != "AL") {
        $committee = $srch_parms['committee'];
        $query->where($db->quoteName('em.committee') . 
		' = ' . $db->quote($committee));
    }

    if (isset($srch_parms['vet_stat']) && $srch_parms['vet_stat'] != "AL") {
        $veteran = $srch_parms['vet_stat'];
        $query->where($db->quoteName('em.veteran') . 
		' = ' . $db->quote($veteran));
    }
    if (isset($srch_parms['board_stat']) && $srch_parms['board_stat'] != "AL") {
        $board = $srch_parms['board_stat'];
        if ($board == 1) { // non-board-member  
            $query->where('IFNULL(ep.board_id,1) = 1');
		}
        else if ($board == 'AB') { // any board position
            $query->where($db->quoteName('eb.board_member_status') . ' = 1');
            $query->where($db->quoteName('ep.board_id') . ' > 1');
	    } else { // specific board position 
            $query->where($db->quoteName('eb.board_member_status') . ' = 1');
            $query->where($db->quoteName('ep.board_id') . 
            ' = ' . $db->quote($board));
	    }
    }
    if (!empty($srch_parms['from_active_date'])) {
		$query->where($db->quoteName('em.active_date') . 
			' >= STR_TO_DATE(' . $db->quote($srch_parms['from_active_date']) . ',"%m/%d/%Y")');
    }
    if (!empty($srch_parms['to_active_date'])) {
		$query->where($db->quoteName('em.active_date') . 
			' <= STR_TO_DATE(' . $db->quote($srch_parms['to_active_date']) . ',"%m/%d/%Y")');
    }
    if (!empty($srch_parms['from_inactive_date'])) {
		$query->where($db->quoteName('em.inactive_date') . 
			' >= STR_TO_DATE(' . $db->quote($srch_parms['from_inactive_date']) . ',"%m/%d/%Y")');
    }
    if (!empty($srch_parms['to_inactive_date'])) {
		$query->where($db->quoteName('em.inactive_date') . 
			' <= STR_TO_DATE(' . $db->quote($srch_parms['to_inactive_date']) . ',"%m/%d/%Y")');
    }
    if (!empty($srch_parms['from_orient_date'])) {
		$query->where($db->quoteName('em.orient_date') . 
			' >= STR_TO_DATE(' . $db->quote($srch_parms['from_orient_date']) . ',"%m/%d/%Y")');
    }
    if (!empty($srch_parms['to_orient_date'])) {
		$query->where($db->quoteName('em.orient_date') . 
			' <= STR_TO_DATE(' . $db->quote($srch_parms['to_orient_date']) . ',"%m/%d/%Y")');
    }

    return $query;
}


/*------------------------------------------------------------------------*
 * Main body.                                                             *
 *------------------------------------------------------------------------*/

// without turning off output buffering, this script creates a 504 (gateway timeout) error when queries result in large tables
// ob_end_flush(); 

$db = \JFactory::getDBO();
$mysession = \JFactory::getSession();
$postdata = \JFactory::getApplication()->input->post;

if (!$postdata->get('process') || $postdata->get('action') == 'Reset') {
    $srch_parms = reset_search_parms($mysession);
    show_search_form($srch_parms,$db,$mysession);
} else if ($postdata->get('process') == 1) {
	// execute the search and display search results table
	$srch_parms = load_search_parms($postdata);
	show_search_form($srch_parms,$db,$mysession);
	show_search_results($db, $mysession, $srch_parms, $postdata);
} else {	
	// how did we get here?
	echo "<br/><strong>how did we get here?</strong><br/>";
}
?>

