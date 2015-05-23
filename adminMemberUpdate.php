<?php
/**
 * Update member database for Experience Unlimited Joomla! website
 * 
 * This page allows administrators to update the membership database. 
 * Member information fields (which can also be set by members on a 
 * different script) include: first/last names, email addresses,
 * personal URL, home/mobile phones, city, state, zip, industry, job class,
 * desired position, and skills. Administrative information includes: 
 * member status, committee, board position, veteran status, and 
 * dates for orientation, join, active status, and inactive status. 
 * Member hours accrued since most recent active status are also indicated.
 * 
 * *** NOTE *** Changing email address, or first/last names, also changes the Joomla! users 
 * database, so login information for the member will change.
 * 
 * Board of Directors members are added to the Joomla! 'EU Board of Directors' group
 * (and removed appropriately).
 * 
 * There is an option to send a 'Welcome to EU!' email. The body of the email is (should be)
 * a Joomla! article with the title 'Active Welcome Template'. The subject line of the 
 * email is 'Welcome to EU!'. The sender is 'euoffice@euccc.org (EU Business Operations)'.
 * 
 * There is an option to add an Active member to the 'EU Member Database Administrators' 
 * Joomla! group
 *
 * This script updates the following tables: eu_members, eu_board_members, eu_member_urls, and
 * also #__users, #__user_usergroup_map (where #__ is replaced by the joomla table prefix)
 * 
 * 
 * PHP version 5
 * 
 * @category  EUAdminScripts
 * @package   AdminMemberUpdate
 * @author    Ben Bonham <bhbonham@gmail.com>
 * @copyright 2014-2015 Experience Unlimited
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   1.16
 * @link      http://euccc.org/live/admin-member-update
 */
 
/*
 * Summary of revisions
 * 
 * still need to format phones on update and re-enable email
 * 
 * 1.16 05/20/5 bb don't pass $db to functions, add comments
 * 1.15 bb 04/15/15 -- can't select deleted members, welcome email automatically sent to
 * 						all newly-active members, change date format, blank 0/0/00 dates,
 * 						dates fields no longer required, format phone #s for search
 * 						require Active status to be database administrator, add search
 * 						fields for dates, committee, vet status
 * 1.14.1 -- 04/09/15 -- bb -- turn off email protector for the members table and revert ' AT ' to '@' change -- only admins have access to 
 *              this script anyway, so no need to cloak emails
 * 1.14 - bb, email protector still causing problems after update to 1.3.9, so replace @-sign in email address with ' AT ' string to hide 
 *          email addresses from email protector. (ironic, isn't it.) need to contact NoNumber to resolve this, it seems...
 * 1.13 bb -- revert turn-off of output buffering (is now back on) (timeout probably caused by NoNumber Email Protector plugin); restyle table
 * 1.12 bb -- turn off output buffering (was creating 504 gateway timeout errors for large tables), blocksize to 5000
 * 1.11 bb -- default search all members (was active); change table blocksize to 500 (was 30)
 * 1.10 bb --  allow search for deleted members
 * 1.9 - bb --	disable conversion directly to Goldcard from Goldcard-Pending if 
 * 				goldcarding member says "do not contact"
 * 1.8 - bb --  add database admin group checkbox, 
 * 1.7 - bb --	change $blocksize to constant BLOCKSIZE
 * 				hide contact info for Gold Card Unreachable members
 * 1.6 - bb --	Unblock member (Joomla! user) when changing member status to Active or Goldcard Pending,
 * 				and block member when changing to any inactive status
 * 				Add board members to 'EU Board of Directors' Joomla! group 
 *              Refuse to inactivate Board of Directors members (user must also remove member from board)
 * 				Checkbox option to send 'Welcome to EU!' mail when activating member
 * 1.5 - bb --  Now refuse to update database if orientation date is more than 1 yr before active date,
 * 				or if active status is incompatible with (in)active_date
 * 				Added STRING filter to date inputs, since default CMD filter was dropping 
 * 				the '/' characters
 * 1.4 - bb -- fix for us date format
 * 1.3 - bb -- added "confirm email" field and test
 * 1.2 - bb -- combined search and update pages into this single page
 */
 
namespace AdminMemberUpdate;

echo <<<EOS
<h2>Admin Member Update v1.16</h2>
Update the EU member database<br/> <br/>
<br/><strong>Instructions</strong><br/>
Change entries, then click 'Submit Changes'
<ul>
	<li>Changing email address will change user's login id</li>
	<li>Change to Active status 
		<ul><li>Will send a "Welcome Email" to address entered</li>
			<li>Requires Orientation date within 1 year preceding Active date</li>
			<li>Requires Active date that is more recent than Inactive date</li>
		</ul>
	</li>
	<li>Change to Inactive, Gold Card, Gold Card Unreachable, or Pending status
		<ul>
			<li>Requires Inactive date that is more recent than Active date</li>
		</ul>
	</li>
	<li>Database Managers
		<ul>
			<li>Checkboxes to assign Database Managers default to un-checked</li>
			<li>Only Active status members can be assigned as Database Managers</li>
		</ul>
	</li>
</ul>
<br/>
    
EMAIL HAS BEEN DISABLED FOR TESTING
EOS;

/*
 * Displaying query result table that has too many rows of bogs down the 
 * sql server and may cause a timeout, so the table can be paginated by
 * setting BLOCKSIZE, which is the max # of member entries to display on one page
 */
define('BLOCKSIZE', 5000);

define("MAX_ORIENT_ACTIVE_INTERVAL", "1 year"); // max interval between orientation and membership activation (EU policy)
define("EU_BOARD_GROUP", "EU Board of Directors"); //Joomla! group name
define("EU_MEMBER_DATABASE_ADMIN", "EU Member Database Administrators"); //Joomla! group name
define("WELCOME_ACTIVE_EMAIL_TEMPLATE", '"%active%welcome%template"'); //title of article holding email template
define("WELCOME_EMAIL_SUBJECT", "Welcome to EU!");
define("MAIL_FROM_ADDRESS", 'euoffice@euccc.org');
define("MAIL_FROM_NAME", "EU Business Operations"); 

// 

$doc = \JFactory::getDocument();
$style = <<<EOSTYLE

.boxed {
	border: 1px solid silver;
	padding: 10px;
	}
	
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

input[disabled],select[disabled] {
    background: #cccccc;
}

input[type=radio] {
	margin: 1px 1px 3px 1px;
}

input[type=radio][disabled] {
    display: none;
}

EOSTYLE;

$doc->addStyleDeclaration($style);

// 
// ------------------------------------------------------------------------
/**
 * Reset search criteria to defaults and set $startrow to 0 (for table paging)
 * 
 * @return array $srch_parms array of search parameters
 */ 
function resetSearchParms()
{
    /*
     * Clear or reset parameters used to build database query
     */
    $mysession = \JFactory::getSession();
    $srch_parms = array();
    $srch_parms['member_id'] = "";
    $srch_parms['fname'] = "";
    $srch_parms['lname'] = "";
    $srch_parms['email_address'] = "";
    $srch_parms['home_phone'] = "";
    $srch_parms['mobile_phone'] = "";
    $srch_parms['status'] = "";
    
    $srch_parms['vet_stat'] = "AL";
    $srch_parms['committee'] = "AL";
    $srch_parms['from_active_date'] = ''; // "1/1/1900";
    $srch_parms['to_active_date'] = ''; // "12/31/2100";
    $srch_parms['from_inactive_date'] = ''; // "1/1/1900";
    $srch_parms['to_inactive_date'] = ''; // "12/31/2100";
    $srch_parms['from_orient_date'] = ''; // "1/1/1900";
    $srch_parms['to_orient_date'] = ''; // "12/31/2100";
    
    $mysession->set('startrow', 0);
    return $srch_parms;
}
// ------------------------------------------------------------------------
/**
 * Put date in standard US date format mm/dd/yyyy
 * 
 * @param string $date_strg date in any of several formats
 * 
 * @return string $new_date_strg  date in mm/dd/yyyy format, or empty if 
 *             input is not a valid date
 */ 
function validatedDate($date_strg)
{
    $new_date_strg = '';

    // joomla will correctly interpret us dates with slashes (mm/dd/yy
    // or mm/dd/yyyy) but interprets dates as intl if they have dashes 
    // (dd-mm-yy or dd-mm-yyyy)
    $us_dash_pat = "/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{2,4})$/";
    if (preg_match($us_dash_pat, $date_strg)) {
        $date_strg = str_replace("-", "/", $date_strg);
    }
    
    if (empty($date_strg)) {
    } else if (is_numeric($date_strg)) {
        // don't allow unix timestamps here
        echo "<br><font color='red'><strong>Bad format for date</strong></font><br>";
    } else if (date_create($date_strg)) {
        $new_date_strg = \JFactory::getDate($date_strg);
        $new_date_strg = $new_date_strg->format('m/d/Y');
    } else {
        echo "<br><font color='red'><strong>Bad format for date</strong></font><br>";
    }
    return $new_date_strg;
}
// ------------------------------------------------------------------------
/**
 * Put phone # in standard format (123) 456-7890
 * 
 * @param string $phone_strg Phone number in any of several formats
 * 
 * @return string $new_phone_strg Formatted phone number, or error message if
 *             input string cannot be converted
 */ 
function formattedPhone($phone_strg)
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
}
// ------------------------------------------------------------------------
/**
 * Load search parameters from submitted form
 * 
 * @return array  $srch_parms    array of search parameters
 */ 
function loadSearchParms()
{
    $postdata = \JFactory::getApplication()->input->post;
    $srch_parms = array();
    $srch_parms['member_id'] = $postdata->get('member_id', '0', 'INT');
    $srch_parms['fname'] = $postdata->get('fname', '', 'STRING');
    $srch_parms['lname'] = $postdata->get('lname', '', 'STRING');
    $srch_parms['committee'] = $postdata->get('committee', '', 'STRING');
    $srch_parms['vet_stat'] = $postdata->get('vet_stat', '', 'STRING');
    $srch_parms['email_address'] = $postdata->get('email_address', '', 'STRING');
    $srch_parms['status'] = $postdata->get('status', '', 'WORD');
    $srch_parms['home_phone'] = $postdata->get('home_phone', '', 'STRING');
    $srch_parms['mobile_phone'] = $postdata->get('mobile_phone', '', 'STRING');
    
    $srch_parms['from_active_date'] 
        = validatedDate($postdata->get('from_active_date', '', 'STRING'));
    $srch_parms['to_active_date'] 
        = validatedDate($postdata->get('to_active_date', '', 'STRING'));
    $srch_parms['from_inactive_date'] 
        = validatedDate($postdata->get('from_inactive_date', '', 'STRING'));
    $srch_parms['to_inactive_date'] 
        = validatedDate($postdata->get('to_inactive_date', '', 'STRING'));
    $srch_parms['from_orient_date'] 
        = validatedDate($postdata->get('from_orient_date', '', 'STRING'));
    $srch_parms['to_orient_date'] 
        = validatedDate($postdata->get('to_orient_date', '', 'STRING'));
        
    return $srch_parms;
}
// ------------------------------------------------------------------------
/**
 * Replace phone and address with *HIDDEN* strings
 * 
 * @param object $member member data object
 * 
 * @return object $member member data object
 */ 
function hideContactIfUnreachable($member)
{
    if ($member->status == 'GU') {
        $member->home_phone = '*HIDDEN*';
        $member->mobile_phone = '*HIDDEN*';
        $member->email_address = '*HIDDEN*';
    }
    return $member;
}// ------------------------------------------------------------------------
/**
 * Display table with member query results
 * 
 * @param array $members Array of member data objects
 * 
 * @return void
 */ 
function showMembersTable($members)
{
    $mysession = \JFactory::getSession();
     
    // Display column headings 
    echo "<br><table id=table_1>";
    echo '<tr><th>Edit</th></th><th>First Name</th><th>Last Name</th>' .
        '<th>Home Phone</th><th>Mobile Phone</th><th>Email</th>' .
        '<th>Status</th></tr>';
        
    // Display member data
    $i = 0;
    $table_of_ids = array();
    foreach ($members as $member) {
        $member->home_phone = formattedPhone($member->home_phone);
        $member->mobile_phone = formattedPhone($member->mobile_phone);
        $member = hideContactIfUnreachable($member);
        $email_address = $member->email_address;
        echo "<tr>" .  
            "<td align='center'>" . "<input type='radio' name='edit_radio' value=$i></td>" .
            "<td>" . $member->first_name . "</td>" .
            "<td>" . $member->last_name . "</td>" .
            "<td>" . $member->home_phone . "</td>" .
            "<td>" . $member->mobile_phone . "</td>" .
            "<td>" . $email_address . "</td>" .
            "<td>" . $member->member_status_desc . "</td></tr>";        
        $table_of_ids[$i] = $member->member_id;
        $i++;
    }
    echo "</table>";
    
    // put this (confidential information) in session variable
    $mysession->set('table_of_ids', $table_of_ids);
    return;
}

// ------------------------------------------------------------------------
/**
 * Show form fields for search
 * 
 * @param array $srch_parms Search parameters
 * 
 * @return void
 */ 
function showSearchForm($srch_parms)
{    
    /*
     *  Set up/display controls for search 
     *  First column: first name, last name, email, member status, committee, 
     *  veteran status, board position
     *  Second column: Date From and Date To: Active Status, Inactive Status, 
     *  Orientation
     */

    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();
    loadSessionArraysForSearch();
    
    echo "<form method='POST'>";

    echo '<br><table>';
    echo '<td valign="top"><table>';
    echo '<tr>';
    // first name 
    echo '<tr><td class="blabel">First&nbspName</td>' .
        '<td align="left"><input type="text" name="fname" ' .
        'value="' . $srch_parms['fname'] . '"></td></tr>';
    // last name 
    echo '<tr><td class="blabel">Last&nbspName</td>' .
        '<td align="left"><input type="text" name="lname" ' .
        'value="' . $srch_parms['lname'] . '"></td></tr>';
    
    // status 
    echo '<tr><td class="blabel">Status</td>';
    echo '<td><select name="status">';
    $sid = $mysession->get('sid');
    $sdesc = $mysession->get('sdesc');
    for ($i = 0; $i < count($sid); $i++) {
        $s1 = $sid[$i];
        $s2 = $sdesc[$i];
        $selected = "";
        // limit initial/default display to active members only 
        if ($srch_parms['status'] == $s1) {
            $selected = "selected"; 
        }
        echo "<option value='" . $s1 . "' $selected>$s2</option>";
    }
    echo '</td></tr>';
    
    // committee 
    insertPulldownMenu(
        'Committee', 'committee', 
        $mysession->get('cid'), $mysession->get('cname'), '', 
        $srch_parms['committee']
    );

    // veteran status 
    insertPulldownMenu(
        'Veteran', 'vet_stat', 
        $mysession->get('vid'), $mysession->get('vdesc'), '', 
        $srch_parms['vet_stat']
    );
    
     // email address 
    echo '<tr><td class="blabel">Email&nbspAddress</td>' .
        '<td align="left"><input type="text" name="email_address" ' .
        'value="' . $srch_parms['email_address'] . '"></td></tr>';
     // home phone 
    echo '<tr><td class="blabel">Home&nbspPhone</td>' .
        '<td align="left"><input type="text" name="home_phone" ' .
        'value="' . $srch_parms['home_phone'] . '"></td></tr>';
     // mobile phone 
    echo '<tr><td class="blabel">Mobile&nbspPhone</td>' .
        '<td align="left"><input type="text" name="mobile_phone" ' .
        'value="' . $srch_parms['mobile_phone'] . '"></td></tr>';
        
    echo '</table></td>';
    
    // (blank) 
   
        // second column: status, industry, jobclass, From active date, To active date
    echo '<td valign="top"><table>';
    // active date - from 
    echo '<tr><td class="blabel">Active&nbspDate&nbsp-&nbspFrom</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="from_active_date" ' .
        'value="' . $srch_parms['from_active_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
    
    // active date - to 
    echo '<tr><td class="blabel">Active&nbspDate&nbsp-&nbspTo</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="to_active_date" ' .
        'value="' . $srch_parms['to_active_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';

    // inactive date - from 
    echo '<tr><td class="blabel">Inactive&nbspDate&nbsp-&nbspFrom</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="from_inactive_date" ' .
        'value="' . $srch_parms['from_inactive_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
    
    // inactive date - to 
    echo '<tr><td class="blabel">Inactive&nbspDate&nbsp-&nbspTo</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="to_inactive_date" ' .
        'value="' . $srch_parms['to_inactive_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
   
    // orient date - from 
    echo '<tr><td class="blabel">Orientation&nbspDate&nbsp-&nbspFrom</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="from_orient_date" ' .
        'value="' . $srch_parms['from_orient_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
    
    // orient date - to 
    echo '<tr><td class="blabel">Orientation&nbspDate&nbsp-&nbspTo</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="to_orient_date" ' .
        'value="' . $srch_parms['to_orient_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
   
    echo '</table></td>';
    
    echo '</table>';
    
    echo '<input type="submit" value="Search" name="action">' .
        '&nbsp<input type="submit" value="Reset" name="action">';

    // (blank) 
    
    echo "<input type='hidden' name='process' value='1'>";
    echo '</form>';
    
    return;
}


// ------------------------------------------------------------------------
/**
 * Load statuses arrays (sid, sdesc) from database
 * 
 * @return void
 */ 
function loadStatusesArrayForSearch()
{
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();

    $query = "SELECT member_status, member_status_desc " .
        "FROM eu_member_statuses " .
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
// ------------------------------------------------------------------------
/**
 * Build query to get member's information from database
 * 
 * @param array $srch_parms Array of search criteria
 * 
 * @return object $query Query object
 */ 
function buildManyMemberQuery($srch_parms)
{
    $db = \JFactory::getDBO();
    $query = $db->getQuery(true);
    $query
        ->select($db->quoteName('em.first_name'))
        ->select($db->quoteName('em.last_name'))
        ->select($db->quoteName('em.home_phone'))
        ->select($db->quoteName('em.mobile_phone'))
        ->select($db->quoteName('em.email_address'))
        ->select($db->quoteName('em.member_id'))
        ->select($db->quoteName('em.status'))
        ->select($db->quoteName('es.member_status_desc'))
        ->select($db->quoteName('ec.committee_name'))
        ->select($db->quoteName('em.veteran'))
        ->select(
            'DATE_FORMAT(' . $db->quoteName('em.active_date') . ', "%m/%d/%Y") AS ' .
            $db->quoteName('active_date')
        )
        ->select($db->quoteName('em.status'))
        ->select(
            'DATE_FORMAT(' . $db->quoteName('em.inactive_date') . ', "%m/%d/%Y") AS ' .
            $db->quoteName('inactive_date')
        )
        ->select(
            'DATE_FORMAT(' . $db->quoteName('em.orient_date') . ', "%m/%d/%Y") AS ' .
            $db->quoteName('orient_date')
        )

        ->from($db->quoteName('eu_members', 'em'))
        ->join(
            'LEFT', $db->quoteName('eu_member_statuses', 'es') .
            ' ON ' . $db->quoteName('es.member_status') . 
            ' = ' . $db->quoteName('em.status')
        )
        ->join(
            'LEFT', $db->quoteName('eu_committees', 'ec') .
            ' ON ' . $db->quotename('ec.committee') .
            ' = ' . $db->quoteName('em.committee')
        )

        ->order($db->quoteName('em.last_name'))
        ->order($db->quoteName('em.first_name'));

    if (!empty($srch_parms['fname'])) {
        $fname = $srch_parms['fname'];
        $fname = '%' . $db->escape($fname, true) . '%';
        $query->where(
            $db->quoteName('em.first_name') . 
            ' LIKE ' . $db->quote($fname, false)
        );
    }
    if (!empty($srch_parms['lname'])) {
        $lname = $srch_parms['lname'];
        $lname = '%' . $db->escape($lname, true) . '%';
        $query->where(
            $db->quoteName('em.last_name') . 
            ' LIKE ' . $db->quote($lname, false)
        );
    }
    if (!empty($srch_parms['home_phone'])) {
        $home_phone = $srch_parms['home_phone'];
        $home_phone = '%' . $db->escape($home_phone, true) . '%';
        $query->where(
            $db->quoteName('em.home_phone') . 
            ' LIKE ' . $db->quote($home_phone, false)
        );
    }
    if (!empty($srch_parms['mobile_phone'])) {
        $mobile_phone = $srch_parms['mobile_phone'];
        $mobile_phone = '%' . $db->escape($mobile_phone, true) . '%';
        $query->where(
            $db->quoteName('em.mobile_phone') . 
            ' LIKE ' . $db->quote($mobile_phone, false)
        );
    }
    if (!empty($srch_parms['email_address'])) {
        $email = $srch_parms['email_address'];
        $email = '%' . $db->escape($email, true) . '%';
        $query->where(
            $db->quoteName('em.email_address') . 
            ' LIKE ' . $db->quote($email, false)
        );  // checker for email?
    }
    if (!empty($srch_parms['status']) && $srch_parms['status'] != "AL") {
        $status = $srch_parms['status'];
        $query->where(
            $db->quoteName('em.status') . 
            ' = ' . $db->quote($status)
        );
    } else if (empty($srch_parms['status'])) {
        // limit initial/default display to active members only 
        $query->where($db->quoteName('em.status') . " = 'A'");
    }
    
    if (isset($srch_parms['committee']) && $srch_parms['committee'] != "AL") {
        $committee = $srch_parms['committee'];
        $query->where(
            $db->quoteName('em.committee') . 
            ' = ' . $db->quote($committee)
        );
    }

    if (isset($srch_parms['vet_stat']) && $srch_parms['vet_stat'] != "AL") {
        $veteran = $srch_parms['vet_stat'];
        $query->where(
            $db->quoteName('em.veteran') . 
            ' = ' . $db->quote($veteran)
        );
    }

    if (!empty($srch_parms['from_active_date'])) {
        $query->where(
            $db->quoteName('em.active_date') . 
            ' >= STR_TO_DATE(' . $db->quote($srch_parms['from_active_date']) . ',"%m/%d/%Y")'
        );
    }
    if (!empty($srch_parms['to_active_date'])) {
        $query->where(
            $db->quoteName('em.active_date') . 
            ' <= STR_TO_DATE(' . $db->quote($srch_parms['to_active_date']) . ',"%m/%d/%Y")'
        );
    }
    if (!empty($srch_parms['from_inactive_date'])) {
        $query->where(
            $db->quoteName('em.inactive_date') . 
            ' >= STR_TO_DATE(' . $db->quote($srch_parms['from_inactive_date']) . ',"%m/%d/%Y")'
        );
    }
    if (!empty($srch_parms['to_inactive_date'])) {
        $query->where(
            $db->quoteName('em.inactive_date') . 
            ' <= STR_TO_DATE(' . $db->quote($srch_parms['to_inactive_date']) . ',"%m/%d/%Y")'
        );
    }
    if (!empty($srch_parms['from_orient_date'])) {
        $query->where(
            $db->quoteName('em.orient_date') . 
            ' >= STR_TO_DATE(' . $db->quote($srch_parms['from_orient_date']) . ',"%m/%d/%Y")'
        );
    }
    if (!empty($srch_parms['to_orient_date'])) {
        $query->where(
            $db->quoteName('em.orient_date') . 
            ' <= STR_TO_DATE(' . $db->quote($srch_parms['to_orient_date']) . ',"%m/%d/%Y")'
        );
    }

    return $query;
}

// ------------------------------------------------------------------------

/**
 * Display table page of members satisfying search criteria
 * 
 * @return void
 */ 
function showSearchResults()
{
    /*
     * Display search results
     * if BLOCKSIZE is less than the # of members returned by the query, this will
     * break the results into BLOCKSIZE blocks and include Next/Previous buttons on the 
     * page 
     */
     
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();
    $postdata = \JFactory::getApplication()->input->post;
    $srch_parms = loadSearchParms($postdata);

    echo '<form method="POST">';
     
    echo '<hr>';
    echo "<strong>Select member to edit, then click 'Submit'</strong><br/>";
    echo "Note -- Changing email address will change site login id<br/>";
    
    // show the members table, with next/prev buttons if needed
    
    $query = buildManyMemberQuery($srch_parms);
    $db->setQuery($query);
    $db->query();
    $cnt = $db->getNumRows();
    
    // determine which rows of search should be displayed on current page 
    $startrow = $mysession->get('startrow', 0);
    
    $action = $postdata->get('action', '', 'STRING');

    if (!empty($action) && $action == "Next block") {
        $startrow = $startrow + BLOCKSIZE;
    } elseif (!empty($action) && $action == "Previous block") {
        $startrow = $startrow - BLOCKSIZE;
    } else {
        $startrow = 0;        // this is a new search 
    }
    $mysession->set('startrow', $startrow);
    
    echo '<br>Search returns ' . $cnt . ' entries';
    $db->setQuery($query, $startrow, BLOCKSIZE);
    $members = $db->loadObjectList();
    
    if ($cnt>0) {
        echo "{emailprotector=off}"; // turn off email protector
        echo '-- now displaying entries ' . (1+$startrow) . ' through ' . 
            ($startrow + count($members)) . '<br/>';
    }
        
    // show buttons to display next and/or previous blocks of rows if needed
    if ($startrow - BLOCKSIZE >= 0) {
        echo '<input type="submit" value="Previous block" name="action">';
    }
    if ($startrow + count($members) < $cnt) {
        echo '<input type="submit" value="Next block" name="action">';
    }
    echo "<input type='hidden' name='process' value='1'>";
    
    /* These hidden inputs are needed for paging to work */
    echo "<input type='hidden' name='fname' value=$srch_parms[fname]>";
    echo "<input type='hidden' name='lname' value=$srch_parms[lname]>";
    echo "<input type='hidden' name='status' value=$srch_parms[status]>";
    echo "<input type='hidden' name='email_address' value=$srch_parms[email_address]>";
    echo "<input type='hidden' name='home_phone' value=$srch_parms[home_phone]>";
    echo "<input type='hidden' name='mobile_phone' value=$srch_parms[mobile_phone]>";
    echo "<input type='hidden' name='vet_stat' value=$srch_parms[vet_stat]>";
    echo "<input type='hidden' name='committee' value=$srch_parms[committee]>";    
    echo "<input type='hidden' name='to_active_date' value=$srch_parms[to_active_date]>";
    echo "<input type='hidden' name='from_active_date' value=$srch_parms[from_active_date]>";
    echo "<input type='hidden' name='to_inactive_date' value=$srch_parms[to_inactive_date]>";
    echo "<input type='hidden' name='from_inactive_date' value=$srch_parms[from_inactive_date]>";
    echo "<input type='hidden' name='to_orient_date' value=$srch_parms[to_orient_date]>";
    echo "<input type='hidden' name='from_orient_date' value=$srch_parms[from_orient_date]>";

    echo "</form>";

    echo "<form method='POST'>";
    if ($cnt>0) {
        showMembersTable($members);
        echo "<br/>";
        echo '<input type="submit" value="Submit" name="action">';
        echo "<input type='hidden' name='process' value='2'>";
    }
    echo "</form>";
    
 
    return;
}
// ------------------------------------------------------------------------
/**
 * Load arrays for pulldown menus from database
 * 
 * @return void
 */ 
function loadSessionArraysForSearch()
{
    loadIndustryArray();
    loadJobClassArray();
    loadCommitteeArrayForSearch();
    loadBoardPositionsArray();
    loadUSStatesArray();
    loadStatusesArrayForSearch();
    loadVeteransArray();
    return;
}
// ------------------------------------------------------------------------
/**
 * Load board arrays (bid, bdesc) from database
 * 
 * @return void
 */ 
function loadBoardPositionsArray()
{    
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();
    $query = "SELECT board_id, board_title " .
        "FROM eu_board_positions " .
        "ORDER BY board_title";
    
    $db->setQuery($query);
    $board_positions = $db->loadObjectList();
    
    $pid = array();
    $pname = array();
    foreach ($board_positions as $board_position) {
        $pid[] = $board_position->board_id;
        $pname[] = $board_position->board_title;
    }
    array_multisort($pid, SORT_ASC, SORT_NUMERIC, $pname);
    $mysession->set('pid', $pid);
    $mysession->set('pname', $pname);
 
    return;
}
// ------------------------------------------------------------------------
/**
 * Load industries arrays (iid, iname) from database
 * 
 * @return void
 */ 
function loadIndustryArray()
{    
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();

    $query = "SELECT industry_id, industry_name " .
        "FROM eu_industries " .
        "ORDER BY industry_name";
    
    $db->setQuery($query);
    $industries = $db->loadObjectList();
    
    $iid = array();
    $iname = array();
    foreach ($industries as $industry) {
        $iid[] = $industry->industry_id;
        $iname[] = $industry->industry_name;
    }
    $mysession->set('iid', $iid);
    $mysession->set('iname', $iname);
    
    return;
}
// ------------------------------------------------------------------------
/**
 * Load veterans arrays (vid, vdesc) from database
 * 
 * @return void
 */ 
function loadVeteransArray()
{
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();
    $vid = array("AL","0","1");
    $vdesc = array("Not Selected","No", "Yes");
    
    $mysession->set('vid', $vid);
    $mysession->set('vdesc', $vdesc);

    return;
}
// ------------------------------------------------------------------------
/**
 * Load job class arrays (jid, jname) from database
 * 
 * @return void
 */ 
function loadJobClassArray()
{ 
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();
    $query = "SELECT jobclass_id, jobclass_name " .
        "FROM eu_jobclasses " .
        "ORDER BY jobclass_name";
    
    $db->setQuery($query);
    $jobclasses = $db->loadObjectList();
    
    $jid = array();
    $jname = array();
    foreach ($jobclasses as $jobclass) {
        $jid[] = $jobclass->jobclass_id;
        $jname[] = $jobclass->jobclass_name;
    }
    $mysession->set('jid', $jid);
    $mysession->set('jname', $jname);

    return;
}
// ------------------------------------------------------------------------
/**
 * Load committees arrays (cid, cname) from database
 * 
 * @return void
 */ 
function loadCommitteeArrayForUpdate()
{  
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();
    $query = "SELECT committee, committee_name " .
        "FROM eu_committees " .
        "ORDER BY committee_name";
    
    $db->setQuery($query);
    $committees = $db->loadObjectList();
    
    $cid = array();
    $cname = array();
    foreach ($committees as $committee) {
        $cid[] = $committee->committee;
        $cname[] = $committee->committee_name;
    }
    $mysession->set('cid', $cid);
    $mysession->set('cname', $cname);
    
    return;
}
// ------------------------------------------------------------------------
/**
 * Load committees arrays (cid, cname) from database and add All Selected option
 * 
 * @return void
 */ 
function loadCommitteeArrayForSearch()
{  
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();
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
// ------------------------------------------------------------------------
/**
 * Load US states arrays (usid, usname, uscode) from database
 * 
 * @return None
 */ 
function loadUSStatesArray()
{  
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();
    $query = "SELECT state_id, state_code, state_name " .
        "FROM eu_states " .
        "ORDER BY state_name";
    
    $db->setQuery($query);
    $usstates = $db->loadObjectList();
    
    $usid = array();
    $usname = array();
    $uscode = array();
    $usid[] = -1;
    $usname[] = "Not Selected";
    $uscode[] = ' ';
    foreach ($usstates as $usstate) {
        $usid[] = $usstate->state_id;
        $usname[] = $usstate->state_name;
        $uscode[] = $usstate->state_code;
    }
    $mysession->set('usid', $usid);
    $mysession->set('usname', $usname);
    $mysession->set('uscode', $uscode);

    return;
}

// ----------------------------------------------------------------------
/**
 * Build query to get member's information from database
 * 
 * @param array $user_id_to_edit EU member id 
 * 
 * @return object $query  query object
 */ 
function buildSingleMemberQuery($user_id_to_edit)
{
    /*  
    * Join several tables on the appropriate keys, but with one tricky bit to 
    * get only the most recent gold card by outer joining the eu_gold_cards
    * table with itself -- see 
    * http://stackoverflow.com/questions/7745609/sql-select-only-rows-with-max-value-on-a-column
    */
    $db = \JFactory::getDBO();
    $query = $db->getQuery(true);
    $query
        ->select(
            $db->quoteName(
                array('first_name', 'last_name', 'email_address', 'home_phone',
                  'mobile_phone', 'city', 'zip', 'desired_position', 'profile',
                  'veteran', 'members.jobclass_id', 'member_status', 'members.committee',
                  'url_link', 'members.industry_id', 'eb.board_member_id')
            )
        )
        ->select($db->quoteName('state', 'usstate'))
        ->select(
            'IFNULL(SUM(' . $db->quoteName('hours.task_hours') . '),0) AS ' . 
            $db->quoteName('hours_balance')
        )
        ->select(
            'IFNULL(' . $db->quoteName('ep.board_id') . ',"1") AS ' . 
            $db->quoteName('board_id')
        )
        ->select($db->quoteName('eg.alumni_event_contact'))
            
            
        ->select(
            'DATE_FORMAT(' . $db->quoteName('members.orient_date') . ', "%m/%d/%Y") AS ' .
            $db->quoteName('orient_date')
        )
        ->select(
            'DATE_FORMAT(' . $db->quoteName('members.join_date') . ', "%m/%d/%Y") AS ' .
            $db->quoteName('join_date')
        )
        ->select(
            'DATE_FORMAT(' . $db->quoteName('members.active_date') . ', "%m/%d/%Y") AS ' .
            $db->quoteName('active_date')
        )
        ->select(
            'DATE_FORMAT(' . $db->quoteName('members.inactive_date') . ', "%m/%d/%Y") AS ' .
            $db->quoteName('inactive_date')
        )
    
            
            
        ->from($db->quoteName('eu_members', 'members'))
        
        ->join(
            'LEFT OUTER', $db->quoteName('eu_member_hours', 'hours') . 
            ' ON (' . $db->quoteName('members.member_id') .' = ' . 
            $db->quoteName('hours.member_id') . 
            ' AND ' . $db->quoteName('hours.task_date') . ' >= ' .
            $db->quoteName('members.active_date') 
            . ')'
        )
            
        ->join(
            'LEFT OUTER', $db->quoteName('eu_member_urls', 'urls') . 
            ' ON ('. $db->quoteName('members.member_id') .' = ' . 
            $db->quoteName('urls.member_id') . ')'
        )
            
        ->join(
            'LEFT OUTER', $db->quoteName('eu_industries', 'industries') .
            ' ON (' . $db->quoteName('members.industry_id') . ' = ' .
            $db->quoteName('industries.industry_id') . ')'
        )
      
        ->join(
            'LEFT OUTER', $db->quoteName('eu_jobclasses', 'jobclasses') .
            ' ON (' . $db->quoteName('members.jobclass_id') . ' = ' .
            $db->quoteName('jobclasses.jobclass_id') . ')'
        )
        
        ->join(
            'LEFT OUTER', $db->quoteName('eu_member_statuses', 'statuses') .
            ' ON (' . $db->quoteName('members.status') . ' = ' .
            $db->quoteName('statuses.member_status') . ')'
        )
            
        ->join(
            'LEFT OUTER', $db->quoteName('eu_committees', 'committees') .
            ' ON (' . $db->quoteName('members.committee') . ' = ' .
            $db->quoteName('committees.committee') . ')'
        )
            
        ->join(
            'LEFT', $db->quoteName('eu_board_members', 'eb') . 
            ' ON (' . $db->quoteName('eb.member_id') . 
            ' = ' . $db->quoteName('members.member_id') . 
            ' AND ' . $db->quoteName('eb.board_member_status') . ' = 1)'
        ) 
                    
        ->join(
            'LEFT', $db->quoteName('eu_board_positions', 'ep') .
            ' ON ' . $db->quoteName('eb.board_position') .
            ' = ' . $db->quoteName('ep.board_title')
        )
        
        // nice trick to get only most recent gold card information
        ->join(
            'LEFT', $db->quoteName('eu_gold_cards', 'eg') .
            ' ON (' . $db->quoteName('members.member_id') . 
            ' = ' . $db->quoteName('eg.member_id') .
            'AND ' . $db->quoteName('members.status') . ' = "GP")'
        )
        ->join(
            'LEFT OUTER', $db->quoteName('eu_gold_cards', 'eg2') .
            ' ON ' . $db->quoteName('eg.member_id') . 
            ' = ' . $db->quoteName('eg2.member_id') .
            ' AND ' . $db->quoteName('eg.last_updated') . 
            ' < ' . $db->quoteName('eg2.last_updated')
        )
        ->where($db->quoteName('eg2.member_id') . ' IS NULL')
            
        ->group($db->quoteName('members.member_id'))
        
        ->where($db->quoteName('members.member_id') . ' = ' . $db->quote($user_id_to_edit));
        
    return $query;
}
// -----------------------------------
/**
 * Execute query
 * 
 * @param object $query query to execute
 * 
 * @return array $member_data    array of objects
 */ 
function doSearchQuery($query) 
{
    $db = \JFactory::getDBO();
    $db->setQuery($query);
    $member_data = $db->loadAssoc();
    return $member_data;
}
// -----------------------------------
/**
 * Put pulldown menu on web page
 * 
 * @param string $label       label for pulldown menu
 * @param string $name        name of pulldown menu
 * @param array  $index_array array of indexes for menu entries
 * @param array  $value_array array of strings for entries
 * @param string $disabled    empty, or "disabled" if menu should be disabled
 * @param mixed  $selection   index of initial selection
 * 
 * @return void
 */ 
function insertPulldownMenu($label, $name, $index_array, $value_array, $disabled, $selection) 
{
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
// -----------------------------------
/**
 * Put pulldown menu on web page    
 * 
 * This is the basically the same as insertPulldownMenu(), but additionally 
 * disables the "Goldcard" option if the member has requested no further 
 * contact from EU (via the member goldcard page) and always disables the 
 * "Deleted" option to prevent deleting members from this page
 * 
 * @param object $member_data data for single member, returned from earlier search
 * @param string $label       label for pulldown menu
 * @param string $name        name of pulldown menu
 * @param array  $index_array array of indexes for menu entries
 * @param array  $value_array array of strings for entries
 * @param string $disabled    empty, or "disabled" if menu should be disabled
 * @param mixed  $selection   index of initial selection
 * 
 * @return void
 */ 
function insertStatusPulldownMenu($member_data, $label, $name, $index_array, $value_array, $disabled, $selection) 
{
    // this is the same as insertPulldownMenu(), but additionally disables the "Goldcard" option if 
    //   the member has requested no further contact from EU (via the member goldcard page)
    // and always disables the "Deleted" option to prevent deleting members from this page
    $only_unreachable = 0;
    if (($member_data['member_status'] == 'GP') && ($member_data['alumni_event_contact'] == 0)) {
        $only_unreachable = 1;
    }
    echo "<tr><td class='blabel'>$label</td>";
    echo "<td><select $disabled name='$name'>";
    for ($i = 0; $i < count($index_array); $i++) {
        $ind = $index_array[$i];
        $val = $value_array[$i];
        $selected = $selection == $ind ? 'selected' : '';
        if (($only_unreachable) && ($val=="Gold Card")) {
            echo "<option value='$ind' $selected disabled>$val</option>";
        } else if ($val=="Deleted") {
            echo "<option value='$ind' $selected disabled>$val</option>";
        } else {
            echo "<option value='$ind' $selected>$val</option>";
        }
    }
    echo "</td></tr>";
    return;
}// --------------------------------------------------------------------
/**
 * Show form fields for update, prefilled from database
 * 
 * @return void
 */ 
function showMemberForm() 
{
    
    $disabled = ' ';
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();

    $user_id_to_edit = $mysession->get('user_id_to_edit');
    $query = buildSingleMemberQuery($user_id_to_edit);
    $member_data = doSearchQuery($query);
    
    loadCommitteeArrayForUpdate();
    
    if (($member_data['member_status'] == 'GP')  
        && ($member_data['alumni_event_contact'] == 0)
    ) {
        echo "<br/>This Pending Goldcard member previously requested no further contact from EU<br/><br/>";
    }
    
    echo "<form method='POST' class='form-validate'>";
    echo '<br><table style="padding:25px;">';
    echo '<td style="min-width:40%; max-width:40%;"><table style="width:100%; border-spacing: 0% 20%; border-collapse:collapse;">';
    // first name 
    echo '<tr><td class="blabel">First&nbspName</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="first_name" ' .
        'value="' . $member_data['first_name'] . '" class="required"></td></tr>';
        
    // last name 
    echo '<tr><td class="blabel">Last&nbspName</td>' .
        '<td style="text-align:left;"><input type="text" name="last_name" ' .
        'value="' . $member_data['last_name'] . '" class="required"></td></tr>';

    // email address 
    echo '<tr><td class="blabel">Email&nbspaddress (EU&nbspwebsite&nbspusername)</td>' .
        '<td style="text-align:left;"><input type="text" name="email_address" ' .
        'value="' . $member_data['email_address'] . '" class="required validate-email"></td></tr>';

    // confirm email address 
    echo '<tr><td class="blabel">Confirm&nbspemail&nbspaddress</td>' .
        '<td style="text-align:left;"><input type="text" name="email_address_2" ' .
        'value="' . $member_data['email_address'] . '" class="required validate-email"></td></tr>';

    // personal url
    echo '<tr><td class="blabel">Personal&nbspURL (75&nbspchar&nbspmax,&nbspno&nbsphttp://)</td>';
    echo '<td style="text-align:left;"><input type="text" name="member_url" ' .
        'value="' . $member_data['url_link'] . '"></td></tr>';

    // home phone
    echo '<tr><td class="blabel">Home phone (XXX)&nbspXXX-XXXX</td>' .
        '<td style="text-align:left;"><input type="text" name="home_phone" ' .
        'value="' . $member_data['home_phone'] . '"></td></tr>';

    // mobile phone
    echo '<tr><td class="blabel">Mobile phone (XXX)&nbspXXX-XXXX</td>' .
        '<td style="text-align:left;"><input type="text" name="mobile_phone" ' .
        'value="' . $member_data['mobile_phone'] . '"></td></tr>';

    // city 
    echo '<tr><td class="blabel">City</td>' .
        '<td style="text-align:left;"><input type="text" name="city" ' .
        'value="' . $member_data['city'] . '"></td></tr>';

    // state
    insertPulldownMenu(
        'State', 'usstate', 
        $mysession->get('uscode'), $mysession->get('usname'), '', 
        $member_data['usstate']
    );
    
    // zip
    echo '<tr><td class="blabel">Zip</td>' .
        '<td style="text-align:left;"><input type="text" name="zip" ' .
        'value="' . $member_data['zip'] . '"></td></tr>';

    // industry
    insertPulldownMenu(
        'Industry', 'industry_id', 
        $mysession->get('iid'), $mysession->get('iname'), '',
        $member_data['industry_id']
    );

    // jobclass
    insertPulldownMenu(
        'Job&nbspClass', 'jobclass_id', 
        $mysession->get('jid'), $mysession->get('jname'), '',
        $member_data['jobclass_id']
    );

    echo '</table></td>';

    echo '<td style="vertical-align:top;" ><table style="width:50%;">';

    // status 
    insertStatusPulldownMenu(
        $member_data, 'Member&nbspStatus', 'status', 
        $mysession->get('sid'), $mysession->get('sdesc'), $disabled, 
        $member_data['member_status']
    );
    // committee 
    insertPulldownMenu(
        'Committee', 'committee', 
        $mysession->get('cid'), $mysession->get('cname'), $disabled, 
        $member_data['committee']
    );
        
    // board position 
    insertPulldownMenu(
        'Current&nbspBoard&nbspPosition', 'board_position', 
        $mysession->get('pid'), $mysession->get('pname'), $disabled, 
        $member_data['board_id']
    );

    // veteran
    $checked_not_vet = $member_data['veteran'] == 0 ? 'checked' : '';
    $checked_is_vet = $member_data['veteran'] == 1 ? 'checked' : '';
    echo "<tr><td class='blabel'>Veteran</td>";
    echo "<td><input $disabled type='radio' name='veteran' value='0' $checked_not_vet style='text-align:left;'>  No ";
    echo "&nbsp";
    echo "<input $disabled type='radio' name='veteran' value='1' $checked_is_vet>  Yes";
    echo "</td></tr>";

    // orientation date 
    $bdate = ($member_data['orient_date']=='00/00/0000' ? '' : $member_data['orient_date']);
    echo "<tr><td class='blabel'>Orientation&nbspDate</td>" .
        "<td style='text-align:left;'><input $disabled type='text' name='orient_date' " .
        "value='$bdate' " .
        "title='Enter as mm/dd/yyyy or yyyy-mm-dd'></td></tr>";

    // join date
    $bdate = ($member_data['join_date']=='00/00/0000' ? '' : $member_data['join_date']);
    echo "<tr><td class='blabel'>Join&nbspDate</td>" .
        "<td style='text-align:left;'><input $disabled type='text' name='join_date' " .
        "value='$bdate' ".
        "title='Enter as mm/dd/yyyy or yyyy-mm-dd'></td></tr>";

    // active date 
    $bdate = ($member_data['active_date']=='00/00/0000' ? '' : $member_data['active_date']);
    echo "<tr><td class='blabel'>Active&nbspDate</td>" .
        "<td style='text-align:left;'><input $disabled type='text' name='active_date' " .
        "value='$bdate' " .
        "title='Enter as mm/dd/yyyy or yyyy-mm-dd'></td></tr>";

    // inactive date
    $bdate = ($member_data['inactive_date']=='00/00/0000' ? '' : $member_data['inactive_date']);
    echo "<tr><td class='blabel'>Inactive&nbspDate</td>" .
        "<td style='text-align:left;'><input $disabled type='text' name='inactive_date' " .
        "value='$bdate' " .
        "title='Enter as mm/dd/yyyy or yyyy-mm-dd'></td></tr>";

    // hours 
    echo "<tr><td class='blabel'>Volunteer&nbspHours&nbspBalance</td>" .
    "<td style='text-align:left;'>&nbsp&nbsp $member_data[hours_balance] </td></tr>";
    
    echo '</table></td>';
    
    echo '<table>';
    // desired_position
    echo '<tr><td class="blabel">Desired Position</td>' .
        '<td style="text-align:left; width:75ex;"><input type="text" name="desired_position" ' .
        'value="' . $member_data['desired_position'] . '"></td></tr>';
    
    // skills
    echo '<tr><td class="blabel">List&nbspyour&nbspskills&nbsp&&nbspexperience<br>';
    echo '(Content&nbsphere&nbspputs&nbspyour<br>skills & experience&nbspon&nbspthe<br>Employer/Recruiter&nbsppage)</td>';
    echo '<td><textarea name="profile" style="resize:none; height:7em; min-width:71ex;">' . $member_data['profile'] . '</textarea></td></tr>';
    
    echo '</table><br/>';
    
    // database managers
    echo '<div class="boxed" style="margin-left:200px; width:450px">';
    echo '<strong>EU Database Managers</strong><br/>';
    echo 'Check box if this member should be able to <em>modify</em> an EU database<br/>&nbsp&nbsp&nbsp(These boxes usually should remain unchecked)<br/>';
    echo '<input type="checkbox" name="is_member_db_admin" value=False><span class="blabel">EU Member Database</span><br/>';
    echo '<input type="checkbox" name="is_marketing_db_admin" value=False><span class="blabel">EU Marketing Database (future)</span><br/>';
    echo '</div><br/>';
    
    // send welcome email?
    
    /* Business Operations Beta Testers requested that email always be sent automatically
    * to newly-active users, so this checkbox was removed 
    echo '<br/>';
    echo '<input type="checkbox" name="send_welcome_email" value=True style="margin:10px">';
    echo '<span class="blabel">If you are activating a new or inactive member, check this box to have "Welcome to EU!" email sent</span>';
    echo '<br/><br/>';
    */    
    echo "<input type='hidden' name='send_welcome_email' value=True>";
    
    echo "<input type='hidden' name='board_member_id' value=$member_data[board_member_id]>";
    echo "<input type='hidden' name='previous_status' value=$member_data[member_status]>";
    echo "<input type='hidden' name='previous_committee' value=$member_data[committee]>";

    $form_id = mt_rand();
    $mysession->set('form_id', $form_id);    
    echo "<input type='hidden' name='form_id' value=$form_id>";
    
    echo "<input type='hidden' name='process' value='3'>";
    
    echo '<input type="submit" value="Submit Changes" name="action" class="validate">';
    
    echo "</form>";
    
    echo '<input type="button" value="Back" onClick=history.go(-1)><br/><br/>';
    echo "<form><input type='submit' value='New Search'></form><br/>";

    return;
}
// --------------------------------------------------------------------
/**
 * Add member to joomla group
 * 
 * @param int    $user_id_to_edit EU member id of member
 * @param string $group_name      name of joomla group
 * 
 * @return void
 */ 
function addToJoomlaGroup($user_id_to_edit, $group_name)
{
    $db = \JFactory::getDBO();
    // translate group_name into group_id
    $query = $db->getQuery(true);
    $query ->select($db->quoteName('id'))
        ->from($db->quoteName('#__usergroups'))
        ->where($db->quoteName('title') . ' = ' . $db->quote($group_name));
    $db->setQuery($query);
    $group_id = $db->loadResult();
    
    // see if member is already in the group
    $query = $db->getQuery(true);
    $query->select('count(*)')
        ->from($db->quoteName('#__user_usergroup_map'))
        ->where($db->quoteName('user_id') . ' = ' . $db->quote($user_id_to_edit))
        ->where($db->quoteName('group_id') . ' = ' . $db->quote($group_id));
    $db->setQuery($query);
    $count = $db->loadResult();
    
    if ($count == 0) {
        // add member to the Joomla! group
        $columns = array('user_id', 'group_id');
        $values = array($db->quote($user_id_to_edit), $db->quote($group_id));
        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__user_usergroup_map'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));
         $db->setQuery($query);
         $db->execute();
    }
    return;
}
// --------------------------------------------------------------------
/**
 * Drop member from joomla group
 * 
 * @param int    $user_id_to_edit EU member id of member
 * @param string $group_name      name of joomla group
 * 
 * @return void
 */ 
function dropFromJoomlaGroup($user_id_to_edit, $group_name)
{
    $db = \JFactory::getDBO();
    // translate group_name into group_id
    $query = $db->getQuery(true);
    $query ->select($db->quoteName('id'))
        ->from($db->quoteName('#__usergroups'))
        ->where($db->quoteName('title') . ' = ' . $db->quote($group_name));
    $db->setQuery($query);
    $group_id = $db->loadResult();
    
    // drop member from the Joomla! group
    $query = $db->getQuery(true);
    $query->delete($db->quoteName('#__user_usergroup_map'))
        ->where($db->quoteName('user_id') . ' = ' . $user_id_to_edit)
        ->where($db->quoteName('group_id') . ' = ' . $db->quote($group_id));
    $db->setQuery($query);
    $db->execute();    
    return;
}
// --------------------------------------------------------------------
/**
 * Update eu_members table
 * 
 * @param int $user_id_to_edit EU member id of member
 * 
 * @return void
 */ 
function updateMembersTable($user_id_to_edit)
{
    $db = \JFactory::getDBO();
    $postdata = \JFactory::getApplication()->input->post;
    $member_fields = array(
        $db->quoteName('first_name') . ' = ' . $db->quote($postdata->get('first_name', '', 'STRING')),
        $db->quoteName('last_name') . ' = ' . $db->quote($postdata->get('last_name', '', 'STRING')),
        $db->quoteName('email_address') . ' = ' . $db->quote($postdata->get('email_address', '', 'STRING')),
        $db->quoteName('home_phone') . ' = ' . $db->quote($postdata->get('home_phone', '', 'STRING')),
        $db->quoteName('mobile_phone') . ' = ' . $db->quote($postdata->get('mobile_phone', '', 'STRING')),
        $db->quoteName('city') . ' = ' . $db->quote($postdata->get('city', '', 'STRING')),
        $db->quoteName('state') . ' = ' . $db->quote($postdata->get('usstate', '', 'STRING')),
        $db->quoteName('zip') . ' = ' . $db->quote($postdata->get('zip', '', 'STRING')),
        $db->quoteName('desired_position') . ' = ' . $db->quote($postdata->get('desired_position', '', 'STRING')),
        $db->quoteName('profile') . ' = ' . $db->quote($postdata->get('profile', '', 'STRING')),
        $db->quoteName('veteran') . ' = ' . $db->quote($postdata->get('veteran', '', 'INT')),
        $db->quoteName('industry_id') . ' = ' . $db->quote($postdata->get('industry_id', '', 'INT')),
        $db->quoteName('jobclass_id') . ' = ' . $db->quote($postdata->get('jobclass_id', '', 'INT'))
        );
        
    $admin_fields = array(
        $db->quoteName('status') . ' = ' . $db->quote($postdata->get('status', '', 'WORD')),
        $db->quoteName('committee') . ' = ' . $db->quote($postdata->get('committee', '', 'WORD')),
    $db->quoteName('orient_date') . ' = STR_TO_DATE(' . $db->quote(validatedDate($postdata->get('orient_date', '', 'STRING'))) . ',"%m/%d/%Y")',      
    $db->quoteName('join_date') . ' = STR_TO_DATE(' . $db->quote(validatedDate($postdata->get('join_date', '', 'STRING'))) . ',"%m/%d/%Y")',      
    $db->quoteName('active_date') . ' = STR_TO_DATE(' . $db->quote(validatedDate($postdata->get('active_date', '', 'STRING'))) . ',"%m/%d/%Y")',      
    $db->quoteName('inactive_date') . ' = STR_TO_DATE(' . $db->quote(validatedDate($postdata->get('inactive_date', '', 'STRING'))) . ',"%m/%d/%Y")'     
        );
             
    $fields = array_merge($member_fields, $admin_fields);
    
    $query = $db->getQuery(true);
    $query->update($db->quoteName('eu_members'))
        ->set($fields)
        ->where($db->quoteName('member_id') . ' = ' . $user_id_to_edit);
          
    $db->setQuery($query);
    $result = $db->execute();
    
    $member_stat = $postdata->get('status');
    $member_orient = strtotime(validatedDate($postdata->get('orient_date', '', 'STRING')));
    $member_orient_plus_interval = strtotime(validatedDate($postdata->get('orient_date', '', 'STRING')) . ' + ' . MAX_ORIENT_ACTIVE_INTERVAL);
    $member_active = strtotime(validatedDate($postdata->get('active_date', '', 'STRING')));
    $member_inactive = strtotime(validatedDate($postdata->get('inactive_date', '', 'STRING')));

    if ($member_stat == 'A') {
        if (empty($member_orient) or empty($member_active)) {
            throw new \Exception("Active status requires Orientation and Active dates", 409);
        }
        if ($member_active > $member_orient_plus_interval) {
            throw new \Exception(
                "Status is Active but Orientation Date is more than " .
                MAX_ORIENT_ACTIVE_INTERVAL . " before Active Date", 409
            );
        }
        if ($member_inactive > $member_active) {
            throw new \Exception("Active status requires that Active Date be more recent than Inactive Date", 409);
        }
    } elseif (in_array($member_stat, array('G','I','P','GU','Q')) 
        and (empty($member_inactive) or ($member_inactive < $member_active))
    ) {
        throw new \Exception("Any inactive status requires that Inactive Date be more recent than Active Date", 409);
    }

    return;
}
// --------------------------------------------------------------------
/**
 * Update eu_member_urls table
 * 
 * @param int $user_id_to_edit EU member id of member
 * 
 * @return void
 */ 
function updateMemberURLsTable($user_id_to_edit)
{
    $db = \JFactory::getDBO();
    $postdata = \JFactory::getApplication()->input->post;
    $new_url = $postdata->get('member_url', '', 'STRING'); // quoted below at db entry

    $query = $db->getQuery(true);
    $query->select('count(*)')
        ->from($db->quoteName('eu_member_urls'))
        ->where(
            $db->quoteName('member_id') . ' = ' . 
            $db->quote($user_id_to_edit)
        );
    $db->setQuery($query);
    $url_count = $db->loadResult();
    if ($url_count > 1) {
        echo "<br/><strong>Warning: This member has $url_count URL(s) -- none were changed (contact IT dept to resolve)</strong><br/>";
    }

    $query = $db->getQuery(true);
    if (($url_count > 1)  
        || ($url_count==0 && empty($new_url))
    ) {
        // don't update any URL(s)
    } else {
        if ($url_count == 1 && !empty($new_url)) {
            $query->update($db->quoteName('eu_member_urls'))
                ->set($db->quoteName('url_link') . ' = ' . $db->quote($new_url))
                ->where($db->quoteName('member_id') . ' = ' . $user_id_to_edit);
        } elseif ($url_count == 1 && empty($new_url)) {
            $query->delete($db->quoteName('eu_member_urls'))
                ->where($db->quoteName('member_id') . ' = ' . $user_id_to_edit);
        } elseif ($url_count == 0 && !empty($new_url)) {
            $query->insert($db->quoteName('eu_member_urls'))
                ->columns($db->quoteName(array('member_id','url_link')))
                ->values(implode(',', $db->quote(array($user_id_to_edit,$new_url))));
        }
           $db->setQuery($query);
           $result = $db->execute();
    }
    return;
}
// --------------------------------------------------------------------
/**
 * Remove member from eu_board_members table and drop member from
 *         EU board of directors group
 * 
 * @param int $user_id_to_edit EU member id of member
 * 
 * @return void
 */ 
function closeOldBoardPosition($user_id_to_edit)
{
    $db = \JFactory::getDBO();
    $postdata = \JFactory::getApplication()->input->post;
    $query = $db->getQuery(true);
    $query->update($db->quoteName('eu_board_members'))
        ->set($db->quoteName('board_member_status') . ' = 0')
        ->set($db->quoteName('board_member_end_date') . ' = CURDATE()')
        ->where(
            $db->quoteName('board_member_id') . ' = ' . 
            $db->quote($postdata->get('board_member_id', '', 'INT'))
        );
    $db->setQuery($query);
    $db->execute();
    
    // remove the ex-board member to the joomla EU Board of Directors group
    dropFromJoomlaGroup($user_id_to_edit, EU_BOARD_GROUP);
    return;
}
// --------------------------------------------------------------------
/**
 * Retrieve board position of member from eu_board_members group
 * 
 * @return int $old_board_position  previous board position of member
 */ 
function getOldBoardPosition()
{
    $db = \JFactory::getDBO();
    $postdata = \JFactory::getApplication()->input->post;
    $query = $db->getQuery(true);
    $query->select($db->quoteName('board_id'))
        ->from($db->quoteName('eu_board_positions', 'ep'))
        ->join(
            'LEFT', $db->quoteName('eu_board_members', 'em') .
            ' ON (' . $db->quoteName('ep.board_title') . ' = ' .
            $db->quoteName('em.board_position') . ')'
        )
        ->where(
            $db->quoteName('em.board_member_id') . ' = ' . 
            $db->quote($postdata->get('board_member_id', '', 'INT'))
        );
    $db->setQuery($query);
    $old_board_position = $db->loadResult();
    return $old_board_position;
}
// --------------------------------------------------------------------
/**
 * Get title of new board position
 * 
 * @return string $new_board_title  title of member's new board position
 */ 
function getNewBoardTitle()
{
    $db = \JFactory::getDBO();
    $postdata = \JFactory::getApplication()->input->post;
    $query = $db->getQuery(true);
    $query->select($db->quoteName('board_title'))
        ->from($db->quoteName('eu_board_positions'))
        ->where(
            $db->quoteName('board_id') . ' = ' . 
            $db->quote($postdata->get('board_position', '', 'INT'))
        );
    $db->setQuery($query);
    $new_board_title = $db->loadResult();
    return $new_board_title;
}
// --------------------------------------------------------------------
/**
 * Add new board position for member and add member to EU board of directors group
 * 
 * @param int $user_id_to_edit EU member id of member
 * 
 * @return void
 */ 
function openNewBoardPosition($user_id_to_edit)
{
    $db = \JFactory::getDBO();
    $new_board_title = getNewBoardTitle($user_id_to_edit);
    $columns = array('member_id','board_member_start_date','board_member_end_date',
                     'board_position', 'board_member_status');
    $values = array((int) $user_id_to_edit, 'CURDATE()', '"0000-00-00"', 
                    $db->quote($new_board_title),1);
    $query = $db->getQuery(true);
    $query->insert($db->quoteName('eu_board_members'))
        ->columns($db->quoteName($columns))
        ->values(implode(',', $values));            
    $db->setQuery($query);
    $db->execute();
    
    // add the new board member to the joomla EU Board of Directors group
    addToJoomlaGroup($user_id_to_edit, EU_BOARD_GROUP);

    return;
}
// --------------------------------------------------------------------
/**
 * Update eu_board_members table and add or delete member from EU board of 
 *         directors group
 * 
 * @param int $user_id_to_edit EU member id of member
 * 
 * @return void
 */ 
function updateBoardMembersTable($user_id_to_edit)
{
    $db = \JFactory::getDBO();
    $postdata = \JFactory::getApplication()->input->post;
    $new_board_position = $postdata->get('board_position', '', 'INT');
    $old_board_member_id = $postdata->get('board_member_id', '', 'INT');
    $member_status = $postdata->get('status', '', 'WORD');
    
    if ($new_board_position==1) { // will not be a board member
        if (!empty($old_board_member_id)) { // was on board
            closeOldBoardPosition($user_id_to_edit);
        }
    } else { // will be a board member
        // set login blocking unless member is active
        if ($member_status != 'A') {
            throw new \Exception("Only Active members can be Board Members");
        }
        if (empty($old_board_member_id)) {  // was not on board
            openNewBoardPosition($user_id_to_edit);       
        } else { // was on board
            $old_board_position = getOldBoardPosition($user_id_to_edit);
            if ($new_board_position == $old_board_position) { // same board position
            } else {
                closeOldBoardPosition($user_id_to_edit);
                openNewBoardPosition($user_id_to_edit); 
            }        
        }
    }
    return;
}
// --------------------------------------------------------------------
/**
 * Update joomla users table (new name & email) and block login unless 
 *         status is Active or Goldcard-Pending
 * 
 * @param int $user_id_to_edit EU member id of member
 * 
 * @return void
 */ 
function updateJoomlaUsersTable($user_id_to_edit)
{
    $db = \JFactory::getDBO();
    $postdata = \JFactory::getApplication()->input->post;
    // change Joomla! username (=email address) and real name
    $realname = $postdata->get('first_name', '', 'STRING') . ' ' . $postdata->get('last_name', '', 'STRING');
    $email = $postdata->get('email_address', 'PROBLEM', 'STRING');
    $email2 = $postdata->get('email_address_2', 'PROBLEM', 'STRING');
    $member_status = $postdata->get('status', '', 'WORD');
    
    // check that email address is confirmed
    if (strcmp($email, $email2)) {
        throw new \Exception("Email addresses must match");
    }

    // don't allow duplicate email addresses (they're used as usernames for eu)
    $query = $db->getQuery(true);
    $query->select('COUNT(*)')
        ->from($db->quoteName('#__users'))
        ->where($db->quoteName('email') . ' = ' . $db->quote($email))
        ->where($db->quoteName('id') . ' != ' . $user_id_to_edit);
    $db->setQuery($query);
    $count = $db->loadResult();
    if ($count>0) {
        throw new \Exception("That username/email address ($email) is already in use");
    }
    
    $query = $db->getQuery(true);
    $query->update($db->quoteName('#__users'))
        ->set($db->quoteName('name') . ' = ' . $db->quote($realname))
        ->set($db->quoteName('username') . ' = ' . $db->quote($email))
        ->set($db->quoteName('email') . ' = ' . $db->quote($email))
        ->where($db->quoteName('id') . ' = ' . $user_id_to_edit);
          
    // set login blocking unless member is active or goldcard pending
    if (in_array($member_status, array('A', 'GP'))) {
        $query->set($db->quoteName('block') . ' = 0');
    } else {
        $query->set($db->quoteName('block') . ' = 1');
    }
    
    $db->setQuery($query);
    $db->execute();
    
    return;
}
// --------------------------------------------------------------------
/**
 * Send email to welcome new Active member
 * 
 * @return void
 */ 
function sendWelcomeEmail()
{
    $db = \JFactory::getDBO();
    $postdata = \JFactory::getApplication()->input->post;
    $member_status = $postdata->get('status', '', 'WORD');
    $previous_status = $postdata->get('previous_status', '', 'WORD');
    if ($member_status == 'A' &        $postdata->get('send_welcome_email', false, 'BOOLEAN') == true
    ) {
                
        $email_address = $postdata->get('email_address', '', 'STRING');
        $sender = array(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            
        // load the template for the active member welcome email
        $query = $db->getQuery(true);
        $query->select('introtext')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('title') . ' LIKE ' . WELCOME_ACTIVE_EMAIL_TEMPLATE);
        $db->setQuery($query);
        $body = $db->loadResult();
    
        $mailer = \JFactory::getMailer();
        $mailer->setSender($sender);
        $mailer->addRecipient($email_address);
        $mailer->setSubject(WELCOME_EMAIL_SUBJECT);
        $mailer->setBody($body);
        $mailer->isHTML(true);
        $mailer->Encoding = 'base64';
    
        $send = $mailer->Send();
        if ($send !== true ) {
            throw new \Exception('Error sending email: ' . $send->__toString());
        } else {
            echo 'Mail sent';
        }
    }
    return;
}
// --------------------------------------------------------------------
/**
 * Update joomla group(s)
 * 
 * @param int $user_id_to_edit EU member id of member
 * 
 * @return void
 */ 
function updateJoomlaGroups($user_id_to_edit)
{
    $postdata = \JFactory::getApplication()->input->post;
    if ($postdata->get('is_member_db_admin', false, 'BOOLEAN') == true) {
        if ($postdata->get('status', '', 'WORD') == 'A') {
            // add the member to the joomla EU Member Database Administrator group
            addToJoomlaGroup($user_id_to_edit, EU_MEMBER_DATABASE_ADMIN);
        } else {
            throw new \Exception("Only Active status members can be database administrators");
        }
    } else {
        // drop the member from the joomla EU Member Database Administrator group
        dropFromJoomlaGroup($user_id_to_edit, EU_MEMBER_DATABASE_ADMIN);
    }
}
// --------------------------------------------------------------------
/**
 * Update database tables as a single transaction
 * 
 * @param int $user_id_to_edit EU member id of member
 * 
 * @return void
 */ 
function updateDatabaseTables($user_id_to_edit)
{
    $db = \JFactory::getDBO();
    try {
        $db->transactionStart();
        updateJoomlaUsersTable($user_id_to_edit);
        updateMembersTable($user_id_to_edit);  
        updateMemberURLsTable($user_id_to_edit);
        updateBoardMembersTable($user_id_to_edit);
        updateJoomlaGroups($user_id_to_edit);
        // sendWelcomeEmail($postdata);
        $db->transactionCommit();        
        echo "<br/>Database updated<br/>";
    } catch (\Exception $e) {
        $db->transactionRollback();
        echo "<br/><strong>" . $e->getMessage() . " -- database was not updated</strong><br/>";
    }
    return;
}

/*========================================================================*
 * Main body.                                                             *
 *========================================================================*/

\JHTML::_('behavior.formvalidation');

$db = \JFactory::getDBO();
$mysession = \JFactory::getSession();
$postdata = \JFactory::getApplication()->input->post;

if (!$postdata->get('process') || $postdata->get('action') == 'Reset') {
    $srch_parms = array();
    $srch_parms = resetSearchParms();
    showSearchForm($srch_parms);
} else {
    switch ($postdata->get('process')) {
    case 1:    // execute the search and display search results table
        $srch_parms = loadSearchParms();
        showSearchForm($srch_parms);
        showSearchResults();
        break;
    case 2:    // display the individual member data
        $index_of_id =  $postdata->get('edit_radio');
        if (is_null($index_of_id)) {
            echo "<br/><strong>Please select a member.</strong><br/><br/><br/>";
            echo "<form><input type='submit' value='New Search'></form><br/>";
        } else {
            $table_of_ids = $mysession->get('table_of_ids');
            $user_id_to_edit = $table_of_ids[$index_of_id];
            $mysession->set('user_id_to_edit', $user_id_to_edit);
            showMemberForm();
        }
        break;
    case 3:    // update then display the individual member data if this is not a page reload
        if ($mysession->get('form_id') == $postdata->get('form_id')) {
            $user_id_to_edit = $mysession->get('user_id_to_edit');
            updateDatabaseTables($user_id_to_edit);
        } else {
            echo "<br/>(Change not submitted on reload)<br/>";
        }
        showMemberForm();
        break;
    default:    // how the did we get here?
        echo "<br/><strong>how the did we get here?</strong><br/>";
        $user = \JFactory::getUser();
        echo "<br/><strong>Current user is: $user->username (#$user->id)</strong><br/>";
        break;
    }
}

/* Don't want update by reloading, so detect if form was reloaded by creating
 * a random form_id (in showMemberForm()) that is saved in $_SESSION and 
 * also assigned to a hidden input accessed via $_POSTDATA. Compare the two 
 * values before updating tables -- if they match then continue with update, 
 * if they do not match, then the form was reloaded and just display
 */

?>
