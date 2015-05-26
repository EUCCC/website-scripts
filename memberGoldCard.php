<?php
/**
 * Member Gold Card page/script for Experience Unlimited Joomla! website
 *
 * This script allows a member to create a gold card entry for her/himself. The
 * entry includes the following information: New Position, Employer Name, 
 * Employer City, Return to work date, Job Leads, Alumni Contact, Comments, 
 * and Testimonial. 
 * Two gold cards cannot be created on the same day because the (member_id, 
 * gold_card_date) is * used as a unique key for the gold card entry.
 *
 * The status of the member is set to "Gold Card Pending".
 * 
 * PHP version 5
 * 
 * @category  EUMemberScripts
 * @package   MemberGoldCard
 * @author    Jean Shih <jean1shih@gmail.com>
 * @author    Ben Bonham <bhbonham@gmail.com>
 * @copyright 2014-2015 Experience Unlimited
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   1.2
 * @link      https://github.com/EUCCC/website-scripts/blob/master/memberGoldCard.php
 */

/* Modification log:
 * -------------------------------------------------------------------------
 * 1.2	05/20/2015	bb	don't pass $db to functions, add comments
 * 1.1	4/25/2015	bb	shade disabled fields, added Back button
 * 1.0	2014/12/03  bb  created from adminGoldCardBB.php
 */

/* Note:	Still need to deal with duplicate gold card entries. Trying
 *		to submit second gold card on same day for a member throws
 *		exception. This should trigger an update rather than an insert.
 */
 
namespace MemberGoldCard;

echo <<<EOS
<h2>   Member Gold Card Form v1.2  </h2>
Create Gold Card and change member status<br/><br/>
Instructions
	<ul><li>Fill out form, then click "Submit"</li></ul>
EOS;


// 

$doc = \JFactory::getDocument();
$style = <<<EOSTYLE

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
        $new_date_strg = $new_date_strg->format('Y-m-d');
    } else {
        echo '<br> Bad format for Active Date <br>';
    }
    return $new_date_strg;
}
// ------------------------------------------------------------------------
/**
 * Load arrays for pulldown menus from database
 * 
 * @return None
 */ 
function loadSessionArrays()
{
    loadJobLeadsArray();
}
// ------------------------------------------------------------------------
/**
 * Load job leads arrays (jid, jdesc) from database
 * 
 * @return None
 */ 
function loadJobLeadsArray()
{
    $db = \JFactory::getDBO();
    $query = "SELECT joblead_id, joblead_desc " .
        "FROM eu_jobleads " .
        "ORDER BY joblead_desc";

    $db->setQuery($query);
    $jobleades = $db->loadObjectList();

    unset($_SESSION['jid']);
    unset($_SESSION['jdesc']);

    $_SESSION['jid'][] = 1;
    $_SESSION['jdesc'][] = "Not Selected";
    foreach ($jobleades as $joblead) {
        $_SESSION['jid'][] = $joblead->joblead_id;
        $_SESSION['jdesc'][] = $joblead->joblead_desc;
    }
    return;
}
// -----------------------------------
/**
 * Put pulldown menu on web page
 * 
 * @param string   $label       label for pulldown menu
 * @param string   $name        name of pulldown menu
 * @param mixed[]  $index_array array of indexes for menu entries
 * @param string[] $value_array array of strings for entries
 * @param string   $disabled    empty, or "disabled" if menu should be disabled
 * @param mixed    $selection   index of initial selection
 * 
 * @return None
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
 * Load member data from database
 * 
 * @return object $member    Member data for current user
 */ 
function loadMemberData()
{
    $db = \JFactory::getDBO();
    $user = \JFactory::getUser();
    $member_id = $user->id;
    $query = $db->getQuery(true);
    $query
        ->select($db->quoteName(array('member_id','first_name','last_name')))
        ->from($db->quoteName('eu_members'))
        ->where($db->quoteName('member_id') . ' = ' . $member_id);
    $db->setQuery($query);
    $member = $db->loadObject();

    return $member;
}
// ------------------------------------------------------------------------
/**
 * Display form for gold card with name pre-filled
 * 
 * @param object $member Database object with data for member
 * 
 * @return None
 */ 
function displayPersonalizedGoldcardForm($member)
{
    $db = \JFactory::getDBO();
    $disabled = ' disabled style="background-color:gray" ';
    $return_to_work_date  = "";
    $new_position  = "";
    $emp_name  = "";
    $emp_city  = "";
    $emp_state  = "";
    $joblead_id  = "";
    $status  = "";
    $alumni_event_contact  = "";
    $gc_comments  = "";
    $gc_testimonials  = "";
    $update = 0;
    $num_rows = 0;
    $first_name = $member->first_name;
    $last_name = $member->last_name;

    loadSessionArrays();

    echo '<br><table style="padding:25px;">';

     // first name 
    echo '<tr><td class="blabel">First&nbspName</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="first_name" ' .
        'value="' . $first_name . '"' . $disabled . ' ></td></tr>';

     // last name 
    echo '<tr><td class="blabel">Last&nbspName</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="last_name" ' .
        'value="' . $last_name . '"' . $disabled . ' ></td></tr>';

     // new position 
    echo '<tr><td class="blabel">New&nbspPosition</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="new_position" ' .
        'value="' . $new_position . '" ></td></tr>';

     // employer name
    echo '<tr><td class="blabel">Employer&nbspName</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="emp_name" ' .
        'value="' . $emp_name . '" ></td></tr>';

     // employer city
    echo '<tr><td class="blabel">Employer City</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="emp_city" ' .
        'value="' . $emp_city . '" ></td></tr>';

     // return to work date
    echo '<tr><td class="blabel">Return to work date</td>' .
        '<td style="text-align:left; width:70%;">' .
        '<input type="text" name="return_to_work_date" ' .
        'value="' . $return_to_work_date . 
        '" title="Enter as mm/dd/ccyy or ccyy-mm-dd"></td></tr>';
    
    // jobleads 
    insertPulldownMenu(
        'Job&nbspLeads', 'joblead', $_SESSION['jid'], 
        $_SESSION['jdesc'], '', ''
    );

    echo '<tr><td class="blabel">May we contact you?</td>' . 
    '<td>Yes <input type="radio" checked="checked" ' .
    'name="alumni_event_contact" value=1>
		 No	<input type="radio" name="alumni_event_contact" value=0></td></tr>';
             
    echo '</table>';       
    echo "<br/>";

    echo '<table>';
    // comments
    echo '<tr><td class="blabel">Comments</td>';
    echo '<td><textarea name="gc_comments" ' .
    'style="resize:none; height:7em; min-width:71ex;">' . 
    $gc_comments . '</textarea></td></tr>';
    
    // testimonial
    echo '<tr><td class="blabel">Testimonial</td>';
    echo '<td><textarea name="gc_testimonials" ' .
    'style="resize:none; height:7em; min-width:71ex;">' . 
    $gc_testimonials . '</textarea></td></tr>';
    echo '</table>';
    echo "<br/>";

    echo '<input  name=submit type=submit value=Submit>';
    echo '<input name=back type=button value=Back onClick=history.go(-1)>';
    echo '<input type=hidden name=process value=1>';
    // set member status to "Gold Card - Pending"
    echo '<input type=hidden name=status value=GP>'; 
    
    return;

}
// ------------------------------------------------------------------------
/**
 * Update eu_gold_cards table in database
 * 
 * @return None
 */ 
function updateGoldcardDatabaseTable()
{
    $db = \JFactory::getDBO();
    $today = date("Y-m-d");
    $member_id = trim($_SESSION['member_id']);
    $gold_card_date=$today;
    $return_to_work_date  = trim(validatedDate($_POST['return_to_work_date']));
    $new_position  = trim($_POST['new_position']);
    $emp_name  = trim($_POST['emp_name']);
    $emp_city  = trim($_POST['emp_city']);
    $status  = trim($_POST['status']);
    $joblead_id = trim($_POST['joblead']);
    $alumni_event_contact  = trim($_POST['alumni_event_contact']);
    $gc_comments  = trim($_POST['gc_comments']);
    $gc_testimonials  = trim($_POST['gc_testimonials']);

    // Create a new query object for inserting a record to eu_gold_cards.
    $query = $db->getQuery(true);

    // Prepare for insert
    // Insert columns.
    $columns = array(
                'member_id',
                'gold_card_date',
                'return_to_work_date',
                'new_position',
                'emp_name',
                'emp_city',
                'joblead_id',
                'alumni_event_contact',
                'gc_comments',
                'gc_testimonials');

    // Prepare insert values.
    $values = array(    
                $db->quote($member_id),
                $db->quote($gold_card_date),
                $db->quote($return_to_work_date),
                $db->quote($new_position),
                $db->quote($emp_name),
                $db->quote($emp_city),
                $db->quote($joblead_id),
                $db->quote($alumni_event_contact),
                $db->quote($gc_comments),
                $db->quote($gc_testimonials));

    // Prepare the insert query.
    $query
        ->insert($db->quoteName('eu_gold_cards'))
        ->columns($db->quoteName($columns))
        ->values(implode(',', $values));

    // Set the query using our newly populated query object and execute it.
    $db->setQuery($query);
    $db->query();
    
    return;
}
// ------------------------------------------------------------------------
/**
 * Update eu_members table in database
 * 
 * @return None
 */ 
function updateMemberDatabaseTable()
{
    // Update eu_members table, change member status accordingly
    $db = \JFactory::getDBO();
    $member_id = trim($_SESSION['member_id']);
    $status  = trim($_POST['status']);
    $new_position  = trim($_POST['new_position']);

    $query = $db->getQuery(true);

    $fields = array(
    $db->quoteName('new_position') . ' = ' . $db->quote($new_position),
    $db->quoteName('status') . ' = ' . $db->quote($status));
        
    $query
        ->update($db->quoteName('eu_members'))
        ->set($fields)
        ->where($db->quoteName('member_id') . " =  $member_id");
        
    $db->setQuery($query);
    $db->execute();

    return;
}
// ------------------------------------------------------------------------
/**
 * Update tables in database
 * 
 * @return boolean $success    True if database updated, False if not
 */ 
function updateDatabaseTables()
{
    $db = \JFactory::getDBO();
    define("UNIQUE_CONSTRAINT_ERROR", 1062);
    $success = false;
    try {
        $db->transactionStart();
        updateMemberDatabaseTable();
        updateGoldcardDatabaseTable();
        $db->transactionCommit();        
        echo "<br/>Database updated<br/>";
        $success = true;
    } catch (\Exception $e) {
        $db->transactionRollback();
        if ($e->getCode()==UNIQUE_CONSTRAINT_ERROR) {        
            $tomorrow = \JFactory::getDate('now + 1 day')->Format('Y-m-d');
            echo "<br/><strong>
				Duplicate Gold Card entry -- database was not updated</strong><br/>
				(New Gold Card can be entered for this member tomorrow, $tomorrow, 
				or later)<br/><br/>";
        } else {
            echo "<br/>" . $e->getMessage() . 
            "<br/><strong>-- database was not updated --</strong><br/>";
        }
    }
    return $success;
}

/*========================================================================*
 * Main body.                                                             *
 *========================================================================*/

\JHTML::_('behavior.formvalidation');
$db = \JFactory::getDBO();

echo '<form method="post">';

if (!isset($_POST['process'] )) {
    $member = loadMemberData();
    $_SESSION['member_id'] = $member->member_id;
    displayPersonalizedGoldcardForm($member);
} elseif ($_POST['process'] == 1) {
    if (updateDatabaseTables()) {
        echo 'Gold Card sumitted -- Thank you, and Congratulations!';
        echo '<br/><input name=back type=button value=Back onClick=history.go(-1)>';
    }
} else {
    echo "<br/>How did we ever get here???<br/>";
}

echo '</form>';
?>

