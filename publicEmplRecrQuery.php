<?php
/**
 * Member database query page/script for Experience Unlimited Joomla! website
 * 
 * Member SQL database query page/script for Experience Unlimited Joomla! website.
 * The query page allows users to search the membership database using keywords.
 * The page has controls at the top and displays search results in 
 * tabular form below. Search controls are: a text input to match any of the 
 * info (following), and radio buttons for "match any" and "match all". 
 * Member info table displays [name, personal url, and email link], skills, 
 * industry, job class, and desired position
 * 
 * 
 * PHP version 5
 * 
 * @category  EUPublicScripts
 * @package   PublicEmplRecrQuery
 * @author    Roger Laurel <RogerLaurel@sbcglobal.net>
 * @author    Stephen Gelardi <Stephen.Gelardi@sbcglobal.net>
 * @author    Ben Bonham <bhbonham@gmail.com>
 * @copyright 2014-2015 Experience Unlimited
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   1.4
 * @link      https://github.com/EUCCC/website-scripts/blob/master/publicEmplRecrQuery.php
 */

/*
 * Summary of revisions
 * 1.4 05/20/15	bb	don't pass $db to functions, add comments
 * 1.3 04/28/15	bb	added Back button, reorder name in table, rework control 
 * 						logic, restyle table
 * 1.2 			bb	added name and email to match in query, don't match members with 
 * 						empty profile (was empty desired_position),
 *     					and added </form> tag, which had been missing
 * 1.1 			bb	added button for linkedin URLs and http:// to URLs (if needed) 
 * 						so apache URL rewrite would not make URLs local links
 */
 
namespace PublicEmplRecrQuery;

echo <<<EOS
<h2>Employer/Recruiter Query v1.4</h2>
Instructions
	<ul><li>Fill out form, then click "Search"</li></ul>
EOS;

/*
 * Displaying query result table that has too many email addresses when 
 * NoNumber email protector is on bogs down the 
 * server and may cause a timeout, so the table can be paginated by
 * setting BLOCKSIZE, which is the max # of member entries to display on 
 * one page
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

table td {
	padding: 0px 5px;
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
 * Reset search criteria to defaults
 * 
 * @return array $srch_parms Array of search parameters
 */ 
function resetSearchParms()
{
    /*
     * Clear or reset parameters used to build database query
     */
    $srch_parms = array();
    $srch_parms['srch_str'] = "";
    $srch_parms['any_all'] = "ANY";

    $_SESSION['startrow'] = 0;

    return $srch_parms;
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
    $srch_parms['srch_str'] = $postdata->get('srch_str', '', 'STRING');
    $srch_parms['any_all'] = $postdata->get('any_all', '', 'STRING');
    return $srch_parms;

}
// ------------------------------------------------------------------------
/**
 * Display table with member search results
 * 
 * @param array $members Array of member data objects
 * 
 * @return void
 */ 
function showMemberData($members)
{
    /*
     * Display table with member query results 
     */
     
    // Display column headings 
    echo "<table id=table_1>";
    echo '<tr><th>Name, URL, email</th><th>Skills</th>' .
        '<th>Industry</th><th>Job Class</th><th>Position Desired</th></tr>';
        
    // Display member data with cloaked email addresses 
    foreach ($members as $member) {

        if (empty($member->url_link)) {
                $url_link = "";
        } elseif (stristr($member->url_link, "linkedin.com")) {
                $url_link = '<a href="http://www.' . 
                stristr($member->url_link, "linkedin.com") . ' ">' .
               '<img src="https://static.licdn.com/scds/common/u/img/webpromo/btn_liprofile_blue_80x15.png" ' .
               ' width="80" height="15" border="0"></a>'  ;
        } elseif (!stripos($member->url_link, "http")  
            || stripos($member->url_link, "http") != 0
        ) {
                $url_link = "<a href='http://" . $member->url_link . "'>" . 
                $member->url_link . "</a>";
        } else {
                $url_link = "<a href='" . $member->url_link . "'>" . 
                $member->url_link . "</a>";
        } 

        echo "<tr>" .
            "<td>" . $member->first_name . " " . $member->last_name . 
                "<br/>" . $url_link .
                "<br/>" . $member->email_address . "</td>" .
            "<td>" . $member->profile . "</td>" .
            "<td>" . $_ = ($member->industry_name === 'Not Selected' ? '' : 
        $member->industry_name) . "</td>" .
            "<td>" . $_ = ($member->jobclass_name === 'Not Selected' ? '' : 
        $member->jobclass_name) . "</td>" .
            "<td>" . $member->desired_position . "</td>" . 
            "</tr>";
    }

    echo "</table>";
    
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
     *  Set up/display controls for search as a table of four rows
     *  Each table row has six cells: three pairs of one title cell and one 
     *     entry cell
     *  First row: first name, industry and status
     *  Second row: last name, jobclass and committee
     *  Third row: email address, (blank), From active date
     *  Fourth row: Search, Reset, (blank), To active date
    
     *  Note if all email addresses are cloaked, the email address input control
     *  displays the javascript cloak rather than the email address, so need to
     *  disable default cloaking on this page and then cloak each address in 
     *  table individually
     */

    loadSessionArrays();
    
    echo '<form method="POST">';
    echo '<br><table>';
    echo '<tr>';
    echo '<td align="right">' .
    'Skills,&nbspexperience&nbspor&nbspkeyword&nbspneeded:</td>' .
        '<td align="left"><input type="text" size="20" max="30" name="srch_str" ' .
        'value="' . $srch_parms['srch_str'] . '"></td>';
    echo '</tr>';
    echo '<tr>';
    $checked = $srch_parms['any_all'] == "ALL" ? " checked" : "";
    echo '<td align="left"><input type="radio" name="any_all" ' .
        'value="ALL"' . $checked . '>  Match all search specifications provided';
    $checked = $srch_parms['any_all'] != "ALL" ? " checked" : "";
    echo '<td align="left"><input type="radio" name="any_all" ' .
        'value="ANY"' . $checked . 
        '>  Match any listing containing a single specification</td>';
    echo '</tr>';
    echo '</table>';
    
    echo '<br/><input type="submit" value="Search" name="action">' .
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
 * Also display Next/Previous buttons if more than one page of search results
 * 
 * @param array $srch_parms Array of search parameters
 * 
 * @return void
 */ 
function showSearchResults($srch_parms)
{
    /*
     * Display Member Query page -- Search controls at the top, search results below
     * if $blocksize is less than the # of members returned by the query, this will
     * break the results into $blocksize blocks and include Next/Previous buttons 
     * on the page 
     */
     
    $db = \JFactory::getDBO();
   
    echo '<form method="POST">';
            
    $postdata = \JFactory::getApplication()->input->post;
    $action = $postdata->get('action', '', 'STRING');

    // determine which rows of search should be displayed on current page 
    $startrow = $_SESSION['startrow'];
    
    if (!empty($action) && $action == "Next block") {
        $startrow = $startrow + BLOCKSIZE;
    } elseif (!empty($action) && $action == "Previous block") {
        $startrow = $startrow - BLOCKSIZE;
    } else {
        $startrow = 0;        // this is a new search 
    }
    $_SESSION['startrow'] = $startrow;
    
    $query = buildQueries($srch_parms);
    $db->setQuery($query);
    $db->query();
    $cnt = $db->getNumRows();
    echo '<br>Search returns ' . $cnt . ' entries';
    $db->setQuery($query, $startrow, BLOCKSIZE);
    $members = $db->loadObjectList();

    echo '-- now displaying entries ' . (1+$startrow) . ' through ' . 
    ($startrow + count($members) ) . '<br/>';
    
    // show buttons to display next and/or previous blocks of rows if needed
    if ($startrow - BLOCKSIZE >= 0) {
        echo '<input type="submit" value="Previous block" name="action">';
    }
    if ($startrow + count($members) < $cnt) {
        echo '<input type="submit" value="Next block" name="action">';
    }
    
    echo "<input type='hidden' name='any_all' value=$srch_parms[any_all]>";
    echo "<input type='hidden' name='srch_str' value=$srch_parms[srch_str]>";
    echo "<input type='hidden' name='process' value='1'>";
    echo '</form>';

    showMemberData($members);
    
    return;
}

// ------------------------------------------------------------------------
/**
 * Load arrays from database
 * 
 * @return void
 */ 
function loadSessionArrays()
{   
        loadIndustryArray();
        loadJobClassArray();
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
    $query = "SELECT industry_id, industry_name " .
        "FROM eu_industries " .
        "ORDER BY industry_name";
    
    $db->setQuery($query);
    $industries = $db->loadObjectList();
    
    unset($_SESSION['iid']);
    unset($_SESSION['iname']);
    
    $_SESSION['iid'][] = -1;
    $_SESSION['iname'][] = "All Selected";
    foreach ($industries as $industry) {
        $_SESSION['iid'][] = $industry->industry_id;
        $_SESSION['iname'][] = $industry->industry_name;
    }
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
  
    $query = "SELECT jobclass_id, jobclass_name " .
        "FROM eu_jobclasses " .
        "ORDER BY jobclass_name";
    
    $db->setQuery($query);
    $jobclasses = $db->loadObjectList();
    
    unset($_SESSION['jid']);
    unset($_SESSION['jname']);
    
    $_SESSION['jid'][] = -1;
    $_SESSION['jname'][] = "All Selected";
    foreach ($jobclasses as $jobclass) {
        $_SESSION['jid'][] = $jobclass->jobclass_id;
        $_SESSION['jname'][] = $jobclass->jobclass_name;
    }
    return;
}

// ------------------------------------------------------------------------
/**
 * Build query to get members' information from database
 * 
 * @param array $srch_parms Array of search criteria
 * 
 * @return object $query Query object
 */ 
function buildQueries($srch_parms)
{
    // mysql 5.5 supports fulltext search only for MyISAM tables, so to do 
    // a fulltext search, create temporary MyISAM table with needed data 
    $db = \JFactory::getDBO();
    $subquery = $db->getQuery(true);
    $subquery
        ->select($db->quoteName('em.first_name'))
        ->select($db->quoteName('em.last_name'))
        ->select($db->quoteName('em.email_address'))
        ->select($db->quoteName('eu.url_link'))
        ->select($db->quoteName('em.profile'))
        ->select($db->quoteName('ei.industry_name'))
        ->select($db->quoteName('ej.jobclass_name'))
        ->select($db->quoteName('em.desired_position'))
    
        ->from($db->quoteName('eu_members', 'em'))
    
        ->where(
            $db->quoteName('em.profile') . ' IS NOT NULL AND ' .
            $db->quoteName('em.profile') . ' != ""'
        )  
        ->where($db->quoteName('em.status') . ' = "A"')
    
        ->join(
            'LEFT', $db->quoteName('eu_industries', 'ei') .
            ' ON ' . $db->quoteName('ei.industry_id') .
            ' = ' . $db->quoteName('em.industry_id')
        )
    
        ->join(
            'LEFT', $db->quoteName('eu_jobclasses', 'ej') .
            ' ON ' . $db->quoteName('ej.jobclass_id') .
            ' = ' . $db->quoteName('em.jobclass_id')
        )  
    
        ->join(
            'LEFT', $db->quoteName('eu_member_urls', 'eu') .
            ' ON ' . $db->quoteName('eu.member_id') .
            ' = ' . $db->quoteName('em.member_id')
        );
    
    $query = 'CREATE TEMPORARY TABLE IF NOT EXISTS t2 ' .
    ' ENGINE=MyISAM AS (' . $subquery . ')';
    $db->setQuery($query);
    $db->execute();
    
    // temporary table t2 has been created with appropriate rows
    // now do fulltext search of this table for query.
    
    $query = 'SELECT ' . 
        $db->quoteName('last_name') . ', ' .
    $db->quoteName('first_name') . ', ' .
    $db->quoteName('email_address') . ', ' .
    $db->quoteName('url_link') . ', ' .
    $db->quoteName('profile') . ', ' .
        $db->quoteName('industry_name') . ', ' .
        $db->quoteName('jobclass_name') . ', ' .
        $db->quoteName('desired_position') . ' FROM t2 ';
                   
    $query_where_str = '';
    if (!empty($srch_parms['srch_str'])) {
        $srch_array = explode(" ", $srch_parms['srch_str']);
        $query_where_str = ' WHERE MATCH (' . 
                                          $db->quoteName('last_name') . ', ' .
                                          $db->quoteName('first_name') . ', ' .
                                          $db->quoteName('email_address') . ', ' .
                                          $db->quoteName('profile') . ', ' .
                                  $db->quoteName('desired_position') . ', ' .
                                  $db->quoteName('jobclass_name') . ', ' .
                                  $db->quoteName('industry_name') . 
                       ") AGAINST ('" ;
        foreach ($srch_array as $word) {
            if ($srch_parms['any_all'] === "ALL") {         
                $query_where_str .= "+" . $db->escape($word, true) . "* ";
            } else {
                $query_where_str .= $db->escape($word, true) . "* ";
            }
        }
        $query_where_str .= "' IN BOOLEAN MODE)";
    }
       
    $query .= $query_where_str;
    $query .= ' ORDER BY ' . 
    $db->quoteName('last_name') . ', ' . 
    $db->quoteName('first_name');
      
    return $query;
}


/*------------------------------------------------------------------------*
 * Main body.                                                             *
 *------------------------------------------------------------------------*/

$db = \JFactory::getDBO();
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

