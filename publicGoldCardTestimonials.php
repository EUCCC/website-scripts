<?php
/**
 * Member database gold card testimonial query page/script for Experience Unlimited Joomla! website
 *
 * Member SQL database gold card testimonial query page/script for Experience Unlimited Joomla! website.
 * The query page allows admininstrators to search the membership database using various
 * search keys. The page has controls at the top and displays search results in
 * tabular form below. Search controls (i.e., keys) are: first &last name, and gold card date. Member
 * info table displays keys for each member matching the query, gold card comments and testimonials,
 * and new position (if these are in the database).
 *
 *
 * @category   EUPublicScripts
 * @package    PublicGoldCardTestimonials
 * @author     Stephen Gelardi <Stephen.Gelardi@sbcglobal.net>
 * @author     Ben Bonham <bhbonham@gmail.com>
 * @copyright  2014 Experience Unlimited
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    1.9.1
 */

/* version history
 * 1.9.1  add note re. default date range
 * 1.9 -- bb start with default search displayed
 * 1.8 -- bb don't display date or comments; use gc.new_position instead of em.new_position * 1.7 -- bb change logic in main so it makes more sense
 * 1.6 -- bb default date interval now set to 4 months if any blank date field
 * 1.5 -- only return members with testimonials; set default start date to 4 months
 * 1.4 -- bb us date format fix
 * 1.3 -- bhb table-format to fixed with set width columns and overflow:hidden for cells; also changed numbering of search results to start from 1 (was 0)
 * 1.2 -- bhb changed toFormat to Format for joomla! version upgrade,
 * 			and changed not_first_pass label in superglobal $_SESSION to
 *          not_first_gold to avoid collision with other scripts, and changed query order to descending by date
 * 1.1 -- bhb added missing </form> tag
 */

namespace PublicGoldCardTestimonials;

echo <<<EOS
<h2>Outgoing Member Testimonials v1.9.1</h2>
Reports data from most recent four months if 'start' and 'end' dates are not specified
<br/><br/>
EOS;

/*
 * Displaying query result table that has too many rows of bogs down the
 * sql server and may cause a timeout, so the table can be paginated by
 * setting $blocksize, which is the max # of member entries to display on one page
 */
$blocksize = 500;
//$blocksize = 10;
###########################################################################

$doc = \JFactory::getDocument();
$style = ".blabel {"
	. "text-align:right; "
	. "max-width:300px; "
	. "padding:0px 10px;"
        . "color: rgb(0,0,255);"
        . "}";
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

    $srch_parms['from_gold_card_date'] =
		$default_start_date = date_sub(\JFactory::getDate(),
			date_interval_create_from_date_string('4 months'))->format('Y-m-d');
    $srch_parms['to_gold_card_date'] =
		$default_end_date = \JFactory::getDate()->format('Y-m-d');

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
        if (preg_match($us_dash_pat, trim($date_strg))) {
                $date_strg = str_replace("-","/",$date_strg);
        }

	if (empty($date_strg)) {
	}
	else if (is_numeric($date_strg)) {
		// don't allow unix timestamps here
		echo '<br> Bad format for Active Date <br>';
	}
	else if (date_create($date_strg)) {
		$new_date_strg = \JFactory::getDate($date_strg);
		$new_date_strg = $new_date_strg->format('Y-m-d');
	}
	else {
		echo '<br> Bad format for Active Date <br>';
	}
	return $new_date_strg;
}
#------------------------------------------------------------------------
function load_search_parms($postdata)
{
    $srch_parms['fname'] = $postdata->get('fname','','STRING');
    $srch_parms['lname'] = $postdata->get('lname','','STRING');

	$fromdate =	validated_date($postdata->get('from_gold_card_date','','STRING'));
	$todate = validated_date($postdata->get('to_gold_card_date','','STRING'));
	if (empty($fromdate) && empty($todate)) {
		$todate = \JFactory::getDate()->format('Y-m-d');
		$fromdate = date_sub(\JFactory::getDate(),
			date_interval_create_from_date_string('4 months'))->format('Y-m-d');
	} else if (empty($fromdate)) {
		$fromdate = date_sub(date_create($todate),
			date_interval_create_from_date_string('4 months'))->format('Y-m-d');
	} else if (empty($todate)) {
		$todate = date_add(date_create($fromdate),
			date_interval_create_from_date_string('4 months'))->format('Y-m-d');
	}
    $srch_parms['from_gold_card_date'] = $fromdate;
    $srch_parms['to_gold_card_date'] = $todate;

    return $srch_parms;

}
#------------------------------------------------------------------------
function show_member_data($members)
{
    /*
     * Display table with member query results
     */

    # Display column headings
    echo '<br><table border=1 style="table-layout:fixed; width:800px; max-width:800px;">'; // width based on 1280 screen less 100 for browser decorations
    echo '<tr style="background-color:#EBEBEB;">' .
             '<th style="width:100px;">First Name</th>' .
             '<th style="width:100px;">Last Name</th>' .
             '<th style="width:450px;">Testimonial</th>' .
             '<th >New Position</th></tr>';

    # Display member data
    $i = 0;
    foreach ($members as $member) {
        $i++;
        $tr = $i % 2 == 0 ? '<tr style="background-color:#EBEBEB;">' : '<tr>';
        echo $tr;
        echo "<td style='overflow:hidden;'>" . $member->first_name . "</td>" .
        "<td style='overflow:hidden;'>" . $member->last_name . "</td>" .
        "<td style='overflow:hidden;'>" . wordwrap($member->gc_testimonials,50,"\n", TRUE)  . "</td>" .
        "<td style='overflow:hidden;'>" . $member->new_position . "</td>" . "</tr>";
    }

    echo "</table>";
}


#------------------------------------------------------------------------
function show_search_form($srch_parms)
{
    /*
     *  Set up/display controls for search as a table of four rows
     *  Each table row has six cells: three pairs of one title cell and one entry cell
     *  First row: first name, last name
     *  Second row: From gold card date, To gold card date
     *  Third row: Search, Reset
     */

	echo '<form method="POST">';
    echo '<br><table>';


   # first name
    echo '<tr><td class="blabel">First&nbspName</td>' .
        '<td style="text-align:left;"><input type="text" name="fname" ' .
        'value="' . $srch_parms['fname'] . '"></td>';

    # last name
    echo '<td class="blabel">Last&nbspName</td>' .
        '<td style="text-align:left;"><input type="text" name="lname" ' .
        'value="' . $srch_parms['lname'] . '"></td></tr>';

    # active date - from
    echo '<tr><td class="blabel">Gold&nbspCard&nbspDate&nbsp-&nbspFrom</td>' .
        '<td style="text-align:left;"><input type="text" name="from_gold_card_date" ' .
        'value="' . $srch_parms['from_gold_card_date'] .
        '" title="Enter as mm/dd/ccyy or ccyy-mm-dd"></td>';

    # active date - to
    echo '<td class="blabel">Gold&nbspCard&nbspDate&nbsp-&nbspTo</td>' .
        '<td style="text-align:left;"><input type="text" name="to_gold_card_date" ' .
        'value="' . $srch_parms['to_gold_card_date'] .
        '" title="Enter as mm/dd/ccyy or ccyy-mm-dd"></td></tr>';

    echo '</table>';

    echo '<input type="submit" value="Search" name="action">&nbsp' .
        '<input type="submit" value="Reset" name="action">';

    echo "<input type='hidden' name='process' value='1'>";

    echo '</form>';
    return;
}
#------------------------------------------------------------------------

function show_search_results($db, $mysession, $srch_parms, $blocksize, $postdata)
{
    /*
     * Display Member Query page -- Search controls at the top, search results below
     * if $blocksize is less than the # of members returned by the query, this will
     * break the results into $blocksize blocks and include Next/Previous buttons on the
     * page
     */

    echo '<form method="POST">';

	# determine which rows of search should be displayed on current page
	$startrow = $mysession->get('startrow',0);

	$action = $postdata->get('action','','STRING');

    if (!empty($action) && $action == "Next block") {
        $startrow = $startrow + $blocksize;
    } elseif (!empty($action) && $action == "Previous block") {
        $startrow = $startrow - $blocksize;
    } else {
        $startrow = 0;        # this is a new search
    }

    $mysession->set('startrow', $startrow);

	$query = buildQueries($db,$srch_parms);
	$db->setQuery($query);
	$db->query();
	$cnt = $db->getNumRows();
	echo '<br>Search returns ' . $cnt . ' entries';
	$db->setQuery($query,$startrow,$blocksize);
	$members = $db->loadObjectList();

	echo '-- now displaying entries ' . (1+$startrow) . ' through ' .
		($startrow + count($members) ) . '<br>';

	# show buttons to display next and/or previous blocks of rows if needed
	if ($startrow - $blocksize >= 0) {
		echo '<input type="submit" value="Previous block" name="action">';
	}
	if ($startrow + count($members) < $cnt) {
		echo '<input type="submit" value="Next block" name="action">';
	}

    echo "<input type='hidden' name='fname' value=$srch_parms[fname]>";
    echo "<input type='hidden' name='lname' value=$srch_parms[lname]>";
    echo "<input type='hidden' name='from_gold_card_date'
			value=$srch_parms[from_gold_card_date]>";
    echo "<input type='hidden' name='to_gold_card_date'
			value=$srch_parms[to_gold_card_date]>";

    echo "<input type='hidden' name='process' value='1'>";

    echo '</form>';

	show_member_data($members);
    return;
}

#------------------------------------------------------------------------
function buildQueries($db,$srch_parms)
{

	$query = $db->getQuery(true);
	$query
	->select($db->quoteName('em.first_name'))
	->select($db->quoteName('em.last_name'))
	->select($db->quoteName('eg.gc_testimonials'))
	->select($db->quoteName('eg.gold_card_date'))
	->select($db->quoteName('eg.new_position'))

	->from($db->quoteName('eu_members','em'))
	->join('LEFT', $db->quotename('eu_gold_cards', 'eg') .
		' ON ' . $db->quotename('eg.member_id') .
		' = ' . $db->quotename('em.member_id'))

	->where($db->quotename('em.status') . ' = "G"')
	->where($db->quotename('eg.gc_testimonials') . ' IS NOT NULL AND ' .
            $db->quoteName('eg.gc_testimonials') . ' != ""')

        ->order($db->quoteName('eg.gold_card_date') . ' DESC')
	->order($db->quoteName('em.last_name'));

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

    if (!empty($srch_parms['from_gold_card_date'])) {
	$query->where($db->quoteName('eg.gold_card_date') .
		' >= ' . $db->quote($srch_parms['from_gold_card_date'],false));
    }
    if (!empty($srch_parms['to_gold_card_date'])) {
	$query->where($db->quoteName('eg.gold_card_date') .
		' <= ' . $db->quote($srch_parms['to_gold_card_date'],false));
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
    $srch_parms = reset_search_parms($mysession);
    show_search_form($srch_parms);
    show_search_results($db, $mysession, $srch_parms, $blocksize, $postdata);
} else if ($postdata->get('process') == 1) {
	// execute the search and display search results table
	$srch_parms = load_search_parms($postdata);
	show_search_form($srch_parms);
	show_search_results($db, $mysession, $srch_parms, $blocksize, $postdata);
} else {
	// how did we get here?
	echo "<br/><strong>how did we get here?</strong><br/>";
}



?>


