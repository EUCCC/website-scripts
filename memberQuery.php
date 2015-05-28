<?php
/**
 * Member database query page/script for Experience Unlimited Joomla! website
 * 
 * Member SQL database query page/script for Experience Unlimited Joomla! website.
 * The query page allows users to search the membership database using various
 * search keys. The page has controls at the top and displays search results in 
 * tabular form below. Search controls (i.e., keys) are: first &last name, 
 * email address, start & end date of active membership, industry, job class, 
 * membership status, and committee membership. Member info table displays keys  
 * for each member matching the query, and also home & mobile phone #s (if these
 * are in the database).
 * 
 * 
 * PHP version 5
 * 
 * @category  EUMemberScripts
 * @package   MemberQuery
 * @author    Roger Laurel <RogerLaurel@sbcglobal.net>
 * @author    Stephen Gelardi <Stephen.Gelardi@sbcglobal.net>
 * @author    Ben Bonham <bhbonham@gmail.com>
 * @copyright 2014-2015 Experience Unlimited
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   1.7
 * @link      https://github.com/EUCCC/website-scripts/blob/master/memberQuery.php
 */

/*
 * Summary of revisions
 * 1.7 05/20/15	bb	don't pass $db to functions, add comments
 * 1.6 04/15/15	bb 	change query results date format, blank 0 dates, blank 
 * 					"Not Selected" and "Undecided" in table, darken "disabled" 
 * 					controls, change table styling, change default search 
 * 					status to AL, format all phone #s, added Back button
 * 1.5 			bb	change $blocksize to constant BLOCKSIZE
 * 					hide contact info for Gold Card Unreachable members
 * 1.4 			bb	redo logic to ensure loading of pulldown menus
 * 1.3 			bb	add fix for us date format
 * 1.2			bb	added </form> tag, which had been missing
 * 1.1			bb	Use NoNumber email cloaking rather than Joomla! stock 
 * 					cloaking (which did not work)
 */

namespace MemberQuery;
 
echo <<<EOS
<h2>   Member Query v1.7  </h2>
EOS;

/*
 * Displaying query result table that has too many rows of bogs down the 
 * sql server and may cause a timeout, so the table can be paginated by
 * setting BLOCKSIZE, which is the max # of member entries to display on one page
 */
define('BLOCKSIZE', 30);

// 

$doc = \JFactory::getDocument();
$style = <<<EOSTYLE

.boxed {
	border: 1px solid silver;
	padding: 10px;
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
	
table#table_1 th,
table#table_1 td {
	border: 1px solid silver;
}

.blabel {
text-align:right;
max-width:300px;
padding:0px 10px;
color: rgb(0,0,255);
}

input[disabled],select[disabled] {
    background: #cccccc;
}

EOSTYLE;
$doc->addStyleDeclaration($style);

// 

// ------------------------------------------------------------------------
/**
 * Reset search criteria to defaults and set session variable startrow to 0
 *   (used for table paging)
 * 
 * @return array $srch_parms Array of search parameters
 */ 
function resetSearchParms()
{
    /*
     * Clear or reset parameters used to build database query
     */
    $mysession = \JFactory::getSession();
    $srch_parms = array();
    $srch_parms['fname'] = "";
    $srch_parms['lname'] = "";
    $srch_parms['email'] = "";
    $srch_parms['industry_id'] = -1;
    $srch_parms['jobclass_id'] = -1;
    $srch_parms['status'] = "AL";
    $srch_parms['committee'] = "AL";
    $srch_parms['active_from_date'] = ''; // "1/1/1900";
    $srch_parms['active_to_date'] = ''; // "12/31/2100";
    $srch_parms['inactive_from_date'] = ''; // "1/1/1900";
    $srch_parms['inactive_to_date'] = ''; // "12/31/2100";
    $srch_parms['orient_from_date'] = ''; // "1/1/1900";
    $srch_parms['orient_to_date'] = ''; // "12/31/2100";

    $mysession->set('startrow', 0);

    return $srch_parms;
}
// ------------------------------------------------------------------------
/**
 * Put date in standard US date format mm/dd/yyyy
 * 
 * @param string $date_strg Date in any of several formats
 * 
 * @return string $new_date_strg Date in mm/dd/yyyy format, or empty if 
 *             input is not a valid date
 */ 
function validatedDate($date_strg)
{
    $new_date_strg = '';
    
    // joomla will correctly interpret us dates with slashes (mm/dd/yy
    // or mm/dd/yyyy) but interprets dates as intl if they have dashes 
    // (dd-mm-yy or dd-mm-yyyy)
    $us_dash_pat = "/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{2,4})$/";
    if (preg_match($us_dash_pat, trim($date_strg))) {
        $date_strg = str_replace("-", "/", $date_strg);
    }
    
    if (empty($date_strg)) {
    } else if (is_numeric($date_strg)) {
        // don't allow unix timestamps here
        echo '<br> Bad format for Active Date <br>';
    } else if (date_create($date_strg)) {
        $new_date_strg = \JFactory::getDate($date_strg);
        $new_date_strg = $new_date_strg->format('m/d/Y');
    } else {
        echo '<br><font color="red"><strong>' .
        'Bad format for Active Date</strong></font><br>';
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
    // interpret very messy phone #'s
    $ph_pat = "/^\D*(\d{3})\D*(\d{3})\D*(\d{4})\D*$/"; 
    
    if (empty($phone_strg)) {
        return '';
    } else if (preg_match($ph_pat, trim($phone_strg), $matches_out)) {
        $new_phone_strg = '(' . $matches_out[1] . ')&nbsp' . $matches_out[2] . '-' . 
        $matches_out[3];
        return $new_phone_strg;
    } else {
        return "bad format:" . $phone_strg;
    }
}
// ------------------------------------------------------------------------
/**
 * Load search parameters from submitted form 
 * 
 * @return array $srch_parms    Array of search parameters
 */ 
function loadSearchParms()
{
    $postdata = \JFactory::getApplication()->input->post;
    $srch_parms = array();
    $srch_parms['fname'] = $postdata->get('fname', '', 'STRING');
    $srch_parms['lname'] = $postdata->get('lname', '', 'STRING');
    $srch_parms['email'] = $postdata->get('email', '', 'STRING');
    $srch_parms['industry_id'] = $postdata->get('industry_id', -1, 'INT');
    $srch_parms['jobclass_id'] = $postdata->get('jobclass_id', -1, 'INT');
    $srch_parms['status'] = $postdata->get('status', 'A', 'WORD');
    $srch_parms['committee'] = $postdata->get('committee', 'AL', 'WORD');

    $srch_parms['active_from_date'] 
        = validatedDate($postdata->get('active_from_date', '', 'STRING'));
    $srch_parms['active_to_date'] 
        = validatedDate($postdata->get('active_to_date', '', 'STRING'));
    $srch_parms['inactive_from_date'] 
        = validatedDate($postdata->get('inactive_from_date', '', 'STRING'));
    $srch_parms['inactive_to_date'] 
        = validatedDate($postdata->get('inactive_to_date', '', 'STRING'));
    $srch_parms['orient_from_date'] 
        = validatedDate($postdata->get('orient_from_date', '', 'STRING'));
    $srch_parms['orient_to_date'] 
        = validatedDate($postdata->get('orient_to_date', '', 'STRING'));
    return $srch_parms;

}
// ------------------------------------------------------------------------
/**
 * Replace phone and address with "*HIDDEN*" string
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
}
// ------------------------------------------------------------------------
/**
 * Display table with member query results
 * 
 * @param array $members  Array of member data objects
 * 
 * @return void
 */ 
function showMemberData($members)
{
    /*
     * Display table with member query results 
     */
     
    // Display column headings 
    echo "<br><table id=table_1>";
    echo '<tr><th>First Name</th><th>Last Name</th>' .
        '<th>Home Phone</th><th>Mobile Phone</th><th>Email</th>' .
        '<th>Industry</th><th>Job Class</th><th>Status</th>' .
        '<th>Committee</th><th>Active Date</th></tr>';
        
    // Display member data with cloaked email addresses 
    foreach ($members as $member) {
        $member->home_phone = formattedPhone($member->home_phone);
        $member->mobile_phone = formattedPhone($member->mobile_phone);
        $member = hideContactIfUnreachable($member);
        echo "<tr>";
        echo "<td>" . $member->first_name . "</td>" .
        "<td>" . $member->last_name . "</td>" .
        "<td>" . $member->home_phone . "</td>" .
        "<td>" . $member->mobile_phone . "</td>" .
        "<td>" . $member->email_address . "</td>" .
        "<td>" . $_ = ($member->industry_name === 'Not Selected' ? '' 
        : $member->industry_name) . "</td>" .
        "<td>" . $_ = ($member->jobclass_name === 'Not Selected' ? '' 
        : $member->jobclass_name) . "</td>" .
        "<td>" . $member->member_status_desc . "</td>" .
        "<td>" . $_ = ($member->committee_name === 'Undecided' ? '' 
        : $member->committee_name) . "</td>" .
        "<td>" . $_ = $member->active_date=='00/00/0000' ? '' 
        : $member->active_date . "</td></tr>";
    }

    echo "</table>";
}

// -----------------------------------
/**
 * Put pulldown menu on web page
 * 
 * @param string   $label       label for pulldown menu
 * @param string   $name        name of pulldown menu
 * @param array    $index_array array of indexes for menu entries
 * @param string[] $value_array array of strings for entries
 * @param string   $disabled    empty, or "disabled" if menu should be disabled
 * @param mixed    $selection   index of initial selection
 * 
 * @return void
 */ 
function insertPulldownMenu($label, $name, $index_array, $value_array, 
    $disabled, $selection
) {
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

// ------------------------------------------------------------------------
/**
 * Show form fields for search, pre-filled if criteria have been set
 * 
 * @param array $srch_parms Search parameters
 * 
 * @return void
 */ 
function showSearchForm($srch_parms)
{    
    /*
     *  Set up/display controls for search as a two-column table
     *  First column: first name, last name, email address, and committee
     *  Second column: status, industry, jobclass, From active date, To active date
     */

    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();
    loadSessionArraysForSearch($db, $mysession);
    
    echo '<form method="POST">';
        
    echo '<br><table>';
    // first column: first name, last name, email address, and committee
    echo '<td valign="top"><table>';
    // first name 
    echo '<tr><td class="blabel">First&nbspName</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="fname" ' .
        'value="' . $srch_parms['fname'] . '"></td></tr>';
    
    // last name 
    echo '<tr><td class="blabel">Last&nbspName</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="lname" ' .
        'value="' . $srch_parms['lname'] . '"></td></tr>';
           
    // email address 
    echo '<tr><td class="blabel">Email&nbspAddress</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="email" ' .
        'value="' . $srch_parms['email'] . '"></td></tr>';
    
    // committee 
    insertPulldownMenu(
        'Committee', 'committee', 
        $mysession->get('cid'), $mysession->get('cname'), '', 
        $srch_parms['committee']
    );
         
    // status 
    insertPulldownMenu(
        'Member&nbspStatus', 'status', 
        $mysession->get('sid'), $mysession->get('sdesc'), '', 
        $srch_parms['status']
    );
        
    // industry
    insertPulldownMenu(
        'Industry', 'industry_id', 
        $mysession->get('iid'), $mysession->get('iname'), '',
        $srch_parms['industry_id']
    );

    // jobclass
    insertPulldownMenu(
        'Job&nbspClass', 'jobclass_id', 
        $mysession->get('jid'), $mysession->get('jname'), '',
        $srch_parms['jobclass_id']
    );

    echo '</table></td>';
    
    // second column: status, industry, jobclass, From active date, To active date
    echo '<td valign="top"><table>';
    // active date - from 
    echo '<tr><td class="blabel">Active&nbspDate&nbsp-&nbspFrom</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="active_from_date" ' .
        'value="' . $srch_parms['active_from_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
    
    // active date - to 
    echo '<tr><td class="blabel">Active&nbspDate&nbsp-&nbspTo</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="active_to_date" ' .
        'value="' . $srch_parms['active_to_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
   
    // inactive date - from 
    echo '<tr><td class="blabel">Inactive&nbspDate&nbsp-&nbspFrom</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="inactive_from_date" ' .
        'value="' . $srch_parms['inactive_from_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
    
    // inactive date - to 
    echo '<tr><td class="blabel">Inactive&nbspDate&nbsp-&nbspTo</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="inactive_to_date" ' .
        'value="' . $srch_parms['inactive_to_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
   
    // active date - from 
    echo '<tr><td class="blabel">Orientation&nbspDate&nbsp-&nbspFrom</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="orient_from_date" ' .
        'value="' . $srch_parms['orient_from_date'] . 
        '" title="Enter as mm/dd/yyyy or yyyy-mm-dd"></td></tr>';
    
    // orientation date - to 
    echo '<tr><td class="blabel">Orientation&nbspDate&nbsp-&nbspTo</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="orient_to_date" ' .
        'value="' . $srch_parms['orient_to_date'] . 
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
// ------------------------------------------------------------------------

/**
 * Display table page of members satisfying search criteria
 * 
 * Also display Next/Previous buttons if more than one table page
 * 
 * @param array $srch_parms Array of search parameters
 * 
 * @return void
 */ 
function showSearchResults($srch_parms)
{     
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();
    $postdata = \JFactory::getApplication()->input->post;
    echo '<form method="POST">';
    
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

    $query = buildQueries($srch_parms);
    $db->setQuery($query);
    $db->query();
    $cnt = $db->getNumRows();
    echo '<br>Search returns ' . $cnt . ' entries';
    $db->setQuery($query, $startrow, BLOCKSIZE);
    $members = $db->loadObjectList();
    
    echo '-- now displaying entries ' . (1+$startrow) . ' through ' . 
    ($startrow + count($members) ) . '<br>';
    
    // show buttons to display next and/or previous blocks of rows if needed
    if ($startrow - BLOCKSIZE >= 0) {
        echo '<input type="submit" value="Previous block" name="action">';
    }
    if ($startrow + count($members) < $cnt) {
        echo '<input type="submit" value="Next block" name="action">';
    }
    
    echo "<input type='hidden' name='fname' value=$srch_parms[fname]>";
    echo "<input type='hidden' name='lname' value=$srch_parms[lname]>";
    echo "<input type='hidden' name='email' value=$srch_parms[email]>";
    echo "<input type='hidden' name='status' value=$srch_parms[status]>";
    echo "<input type='hidden' name='jobclass_id' " .
    "value=$srch_parms[jobclass_id]>";
    echo "<input type='hidden' name='industry_id' " .
    "value=$srch_parms[industry_id]>";
    echo "<input type='hidden' name='committee' " .
    "value=$srch_parms[committee]>";    
    echo "<input type='hidden' name='active_to_date' " .
    "value=$srch_parms[active_to_date]>";
    echo "<input type='hidden' name='active_from_date' " .
    "value=$srch_parms[active_from_date]>";
    echo "<input type='hidden' name='inactive_to_date' " .
    "value=$srch_parms[inactive_to_date]>";
    echo "<input type='hidden' name='inactive_from_date' " .
    "value=$srch_parms[inactive_from_date]>";
    echo "<input type='hidden' name='orient_to_date' " .
    "value=$srch_parms[orient_to_date]>";
    echo "<input type='hidden' name='orient_from_date' " .
    "value=$srch_parms[orient_from_date]>";

    echo "<input type='hidden' name='process' value='1'>";

    echo '</form>';
    
    showMemberData($members);

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
    loadCommitteeArray();
    loadStatusesArrayForSearch();
    return;
}
// ------------------------------------------------------------------------
/**
 * Load industries arrays (iid, iname) from database, adding "All" option
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
    $iid[] = -1;
    $iname[] = "All Selected";
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
 * Load statuses arrays (sid, sdesc) from database, adding "All" option
 * 
 * But do not allow search for Deleted members
 * 
 * @return void
 */ 
function loadStatusesArrayForSearch()
{
    $db = \JFactory::getDBO();
    $mysession = \JFactory::getSession();
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
// ------------------------------------------------------------------------
/**
 * Load job classes arrays (jid, jname) from database, adding "All" option
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
    $jid[] = 'AL';
    $jname[] = 'All Selected';
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
 * Load committees arrays (cid, cname) from database, adding "All" option
 * 
 * @return void
 */ 
function loadCommitteeArray()
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
 * Build query to get member's information from database
 * 
 * @param array $srch_parms Array of search criteria
 * 
 * @return object $query Query object
 */ 
function buildQueries($srch_parms)
{
    $db = \JFactory::getDBO();
    $query = $db->getQuery(true);
    $query
        ->select($db->quoteName('em.first_name'))
        ->select($db->quoteName('em.last_name'))
        ->select($db->quoteName('em.home_phone'))
        ->select($db->quoteName('em.mobile_phone'))
        ->select($db->quoteName('em.email_address'))
        ->select($db->quoteName('ei.industry_name'))
        ->select($db->quoteName('ej.jobclass_name'))
        ->select($db->quoteName('es.member_status_desc'))
        ->select($db->quoteName('ec.committee_name'))
        ->select(
            'DATE_FORMAT(' . $db->quoteName('em.active_date') . ', "%m/%d/%Y") AS ' .
            $db->quoteName('active_date')
        )
        ->select($db->quoteName('em.status'))
        
        ->from($db->quoteName('eu_members', 'em'))
        ->from($db->quoteName('eu_industries', 'ei'))
        ->from($db->quoteName('eu_jobclasses', 'ej'))
        ->from($db->quoteName('eu_member_statuses', 'es'))
        ->from($db->quoteName('eu_committees', 'ec'))
        
        ->where(
            $db->quoteName('ei.industry_id') . 
            ' = ' . $db->quoteName('em.industry_id')
        )
        ->where(
            $db->quoteName('ej.jobclass_id') . 
            ' = ' . $db->quoteName('em.jobclass_id')
        )
        ->where($db->quoteName('em.status') . ' <> "D"')
        ->where(
            $db->quoteName('es.member_status') . 
            ' = ' . $db->quoteName('em.status')
        )
        ->where(
            $db->quoteName('ec.committee') . 
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
    if (!empty($srch_parms['email'])) {
        $email = $srch_parms['email'];
        $email = '%' . $db->escape($email, true) . '%';
        $query->where(
            $db->quoteName('em.email_address') . 
            ' LIKE ' . $db->quote($email, false)
        );  // checker for email?
    }
    if (!empty($srch_parms['industry_id']) && $srch_parms['industry_id'] != -1) {
        $industry_id = $srch_parms['industry_id'];
        $query->where(
            $db->quoteName('em.industry_id') . 
            ' = ' . $db->quote($industry_id)
        );
    }
    if (!empty($srch_parms['jobclass_id']) && $srch_parms['jobclass_id'] != -1) {
        $jobclass_id = $srch_parms['jobclass_id'];
        $query->where(
            $db->quoteName('em.jobclass_id') . 
            ' = ' . $db->quote($jobclass_id)
        );
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
    if (!empty($srch_parms['committee']) && $srch_parms['committee'] != "AL") {
        $committee = $srch_parms['committee'];
        $query->where(
            $db->quoteName('em.committee') . 
            ' = ' . $db->quote($committee)
        );
    }
    if (!empty($srch_parms['active_from_date'])) {
        $query->where(
            $db->quoteName('em.active_date') . 
            ' >= STR_TO_DATE(' . $db->quote($srch_parms['active_from_date']) . 
            ',"%m/%d/%Y")'
        );
    }
    if (!empty($srch_parms['active_to_date'])) {
        $query->where(
            $db->quoteName('em.active_date') . 
            ' <= STR_TO_DATE(' . $db->quote($srch_parms['active_to_date']) . 
            ',"%m/%d/%Y")'
        );
    }
    if (!empty($srch_parms['inactive_from_date'])) {
        $query->where(
            $db->quoteName('em.inactive_date') . 
            ' >= STR_TO_DATE(' . $db->quote($srch_parms['inactive_from_date']) . 
            ',"%m/%d/%Y")'
        );
    }
    if (!empty($srch_parms['inactive_to_date'])) {
        $query->where(
            $db->quoteName('em.inactive_date') . 
            ' <= STR_TO_DATE(' . $db->quote($srch_parms['inactive_to_date']) . 
            ',"%m/%d/%Y")'
        );
    }
    if (!empty($srch_parms['orient_from_date'])) {
        $query->where(
            $db->quoteName('em.orient_date') . 
            ' >= STR_TO_DATE(' . $db->quote($srch_parms['orient_from_date']) . 
            ',"%m/%d/%Y")'
        );
    }
    if (!empty($srch_parms['orient_to_date'])) {
        $query->where(
            $db->quoteName('em.orient_date') . 
            ' <= STR_TO_DATE(' . $db->quote($srch_parms['orient_to_date']) . 
            ',"%m/%d/%Y")'
        );
    }
    
    return $query;
}


/*------------------------------------------------------------------------*
 * Main body.                                                             *
 *------------------------------------------------------------------------*/

$db = \JFactory::getDBO();
$mysession = \JFactory::getSession();
$postdata = \JFactory::getApplication()->input->post;

if (!$postdata->get('process') || $postdata->get('action') == 'Reset') {
    $srch_parms = resetSearchParms();
    showSearchForm($srch_parms);
} else if ($postdata->get('process') == 1) {
    // execute the search and display search results table
    $srch_parms = loadSearchParms();
    showSearchForm($srch_parms);
    showSearchResults($srch_parms);
} else {    
    // how did we get here?
    echo "<br/><strong>how did we get here?</strong><br/>";
}

?>

