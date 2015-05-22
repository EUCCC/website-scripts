<?php
namespace memberUpdate;

/**
 * Update member database for Experience Unlimited Joomla! website
 * 
 * This page allows members to update the membership database. 
 * Settable member information fields include: first/last names, email address,
 * personal URL, home/mobile phones, city, state, zip, industry, job class,
 * desired position, and skills. Administrative information (not settable
 * by users) displayed includes: member status, committee, board position,
 * veteran status, and dates for orientation, join, active status, and 
 * inactive status. Member hours accrued since most recent active status
 * are also indicated.
 * 
 * *** NOTE *** Changing email address, or first/last names, also changes 
 * the Joomla! users database, so login information for the member will change.
 * 
 * This script updates the following tables for the user:
 * eu_members, eu_member_urls, and joomla #__users (where #__ is 
 * replaced by the joomla table prefix)
 * 
 * @package    memberUpdate.php
 * @author     Ben Bonham <bhbonham@gmail.com>
 * @copyright  2014 Experience Unlimited
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    1.1
 */
 
/*
 * Summary of revisions
 * 1.1	bb	04/21/15	shaded inactive, change date labels, change styling, change displayed
 * 						date format (to m/d/y), blank 0 dates, reformats phone #s, 
*/

###########################################################################

echo <<<EOS
<h2>   Member Update v1.1  </h2>
EOS;

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

input.eudisabled, select.eudisabled {
	background: #cccccc;
}

EOSTYLE;
$doc->addStyleDeclaration($style);

###########################################################################
//input[disabled],select[disabled] {
    //background: #00cccc;
//}
#------------------------------------------------------------------------
function formatted_phone($phone_strg)
{
	$ph_pat = "/^[\D\s]*(\d{3})[\D\s]*(\d{3})[\D\s]*(\d{4})[\D\s]*$/"; // allow very messy phone #'s
	
	if (empty($phone_strg)) {
		return '';
	} else if (preg_match($ph_pat, $phone_strg, $matches_out)) {
		$new_phone_strg = '(' . $matches_out[1] . ') ' . $matches_out[2] . '-' . $matches_out[3];
		return $new_phone_strg;
	} else {
		throw new \Exception("<strong>bad format for phone: $phone_strg</strong>");
	}
}
#------------------------------------------------------------------------
function loadSessionArrays($db, $mysession)
{
	loadIndustryArray($db, $mysession);
	loadJobClassArray($db, $mysession);
	loadCommitteeArray($db, $mysession);
	loadBoardPositionsArray($db, $mysession);
	loadUSStatesArray($db, $mysession);
	loadStatusesArrayforUpdate($db, $mysession);
	return;
}
#------------------------------------------------------------------------
function loadBoardPositionsArray($db, $mysession)
{    
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
#------------------------------------------------------------------------
function loadIndustryArray($db, $mysession)
{    
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
#------------------------------------------------------------------------
function loadStatusesArrayForUpdate($db, $mysession)
{  
    $query = "SELECT member_status, member_status_desc " .
        "FROM eu_member_statuses " . "WHERE member_status <> 'D' " .
        "ORDER BY member_status_desc";
    
    $db->setQuery($query);
    $statuses = $db->loadObjectList();
    
    $sid = array();
    $sdesc = array();
    foreach ($statuses as $status) {
		$sid[] = $status->member_status;
		$sdesc[] = $status->member_status_desc;
	}
	$mysession->set('sid', $sid);
	$mysession->set('sdesc', $sdesc);

    return;
}
#------------------------------------------------------------------------
function loadJobClassArray($db, $mysession)
{ 
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
    foreach ($committees as $committee) {
		$cid[] = $committee->committee;
		$cname[] = $committee->committee_name;
	}
	$mysession->set('cid', $cid);
	$mysession->set('cname', $cname);
	
    return;
}
#------------------------------------------------------------------------
function loadUSStatesArray($db, $mysession)
{  
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

#----------------------------------------------------------------------
function buildSingleMemberQuery($db, $user_id_to_edit)
{

$query = $db->getQuery(true);
$query
  	->select($db->quoteName(array('first_name', 'last_name', 'email_address', 'home_phone',
	    'mobile_phone', 'city', 'zip', 'desired_position', 'profile',
	    'veteran', 'members.jobclass_id', 'member_status', 'members.committee',
        'url_link', 'members.industry_id', 'eb.board_member_id')))
    ->select($db->quoteName('state','usstate'))
    ->select('SUM(' . $db->quoteName('hours.task_hours') . ') AS ' . 
        $db->quoteName('hours_balance'))
    ->select('IFNULL(' . $db->quoteName('ep.board_id') . ',"1") AS ' . 
        $db->quoteName('board_id'))
        
 	->select('DATE_FORMAT(' . $db->quoteName('active_date') . ', "%m/%d/%Y") AS ' .
			$db->quoteName('active_date'))
  	->select('DATE_FORMAT(' . $db->quoteName('inactive_date') . ', "%m/%d/%Y") AS ' .
			$db->quoteName('inactive_date'))
 	->select('DATE_FORMAT(' . $db->quoteName('orient_date') . ', "%m/%d/%Y") AS ' .
			$db->quoteName('orient_date'))
  	->select('DATE_FORMAT(' . $db->quoteName('join_date') . ', "%m/%d/%Y") AS ' .
			$db->quoteName('join_date'))
             
	->from($db->quoteName('eu_members','members'))
	
	->join('LEFT OUTER', $db->quoteName('eu_member_hours','hours') . 
	    ' ON (' . $db->quoteName('members.member_id') .' = ' . 
	    $db->quoteName('hours.member_id') . 
	    ' AND ' . $db->quoteName('hours.task_date') . ' >= ' .
	    $db->quoteName('members.active_date') 
	    . ')')
	    
	->join('LEFT OUTER', $db->quoteName('eu_member_urls','urls') . 
	    ' ON ('. $db->quoteName('members.member_id') .' = ' . 
	    $db->quoteName('urls.member_id') . ')')
	    
	->join('LEFT OUTER', $db->quoteName('eu_industries','industries') .
	    ' ON (' . $db->quoteName('members.industry_id') . ' = ' .
	    $db->quoteName('industries.industry_id') . ')')
  
	->join('LEFT OUTER', $db->quoteName('eu_jobclasses','jobclasses') .
	    ' ON (' . $db->quoteName('members.jobclass_id') . ' = ' .
	    $db->quoteName('jobclasses.jobclass_id') . ')')
	
	->join('LEFT OUTER', $db->quoteName('eu_member_statuses','statuses') .
	    ' ON (' . $db->quoteName('members.status') . ' = ' .
	    $db->quoteName('statuses.member_status') . ')')
	    
	->join('LEFT OUTER', $db->quoteName('eu_committees','committees') .
	    ' ON (' . $db->quoteName('members.committee') . ' = ' .
	    $db->quoteName('committees.committee') . ')')
	    
    ->join('LEFT', $db->quotename('eu_board_members', 'eb') . 
                ' ON (' . $db->quotename('eb.member_id') . 
                ' = ' . $db->quotename('members.member_id') . 
                ' AND ' . $db->quoteName('eb.board_member_status') . ' = 1)') 
                
    ->join('LEFT', $db->quoteName('eu_board_positions', 'ep') .
        ' ON ' . $db->quotename('eb.board_position') .
        ' = ' . $db->quotename('ep.board_title'))
	    
	    
	->group($db->quoteName('members.member_id'))
	
	->where($db->quoteName('members.member_id') . ' = ' . 
	    $db->quote($user_id_to_edit));
	    
  return $query;
}
#-----------------------------------
function doSearchQuery($db, $query) {
	$db->setQuery($query);
	$member_data = $db->loadAssoc();
	return $member_data;
}
#-----------------------------------
function insertPulldownMenu($member_data, $label, $name, $index_array, $value_array, $disabled, $selection) {
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
#--------------------------------------------------------------------
function show_member_form($db,$isAdminCall, $mysession) {
	// show data for either admin or user call
	
	$disabled = 'disabled style="background-color:gray" ';
	
	$user_id_to_edit = $mysession->get('user_id_to_edit');
	$query = buildSingleMemberQuery($db,$user_id_to_edit);
	$member_data = doSearchQuery($db,$query);
	
    loadSessionArrays($db,$mysession);
	
	echo "<strong>Change entries, then click 'Submit Changes'</strong>";

	echo "<form method='POST' class='form-validate'>";
	echo '<br><table style="padding:25px;">';
	echo '<td style="min-width:40%; max-width:40%;"><table style="width:100%; border-spacing: 0% 20%; border-collapse:collapse;">';
    # first name 
    echo '<tr><td class="blabel">First&nbspName</td>' .
        '<td style="text-align:left; width:70%;"><input type="text" name="first_name" ' .
        'value="' . $member_data['first_name'] . '" class="required"></td></tr>';
        
    # last name 
    echo '<tr><td class="blabel">Last&nbspName</td>' .
        '<td style="text-align:left;"><input type="text" name="last_name" ' .
        'value="' . $member_data['last_name'] . '" class="required"></td></tr>';

    # email address 
    echo '<tr><td class="blabel">Email&nbspaddress (EU&nbspwebsite&nbspusername)</td>' .
        '<td style="text-align:left;"><input type="text" name="email_address" ' .
        'value="' . $member_data['email_address'] . '" class="required validate-email"></td></tr>';

    # confirm email address 
    echo '<tr><td class="blabel">Confirm&nbspemail&nbspaddress</td>' .
        '<td style="text-align:left;"><input type="text" name="email_address_2" ' .
        'value="' . $member_data['email_address'] . '" class="required validate-email"></td></tr>';

    # personal url
    echo '<tr><td class="blabel">Personal&nbspURL (75&nbspchar&nbspmax,&nbspno&nbsphttp://)</td>';
    echo '<td style="text-align:left;"><input type="text" name="member_url" ' .
        'value="' . $member_data['url_link'] . '"></td></tr>';

    # home phone
    echo '<tr><td class="blabel">Home phone (XXX)&nbspXXX-XXXX</td>' .
        '<td style="text-align:left;"><input type="text" name="home_phone" ' . 
        'value="' . $member_data['home_phone'] . '"></td></tr>';

    # mobile phone
    echo '<tr><td class="blabel">Mobile phone (XXX)&nbspXXX-XXXX</td>' .
        '<td style="text-align:left;"><input type="text" name="mobile_phone" ' .
        'value="' . $member_data['mobile_phone'] . '"></td></tr>';

    # city 
    echo '<tr><td class="blabel">City</td>' .
        '<td style="text-align:left;"><input type="text" name="city" ' .
        'value="' . $member_data['city'] . '"></td></tr>';

    # state
    insertPulldownMenu($member_data, 'State', 'usstate', 
        $mysession->get('uscode'), $mysession->get('usname'), '', 
        $member_data['usstate']);
    
    # zip
    echo '<tr><td class="blabel">Zip</td>' .
        '<td style="text-align:left;"><input type="text" name="zip" ' .
        'value="' . $member_data['zip'] . '"></td></tr>';

    # industry
    insertPulldownMenu($member_data, 'Industry', 'industry_id', 
        $mysession->get('iid'), $mysession->get('iname'), '',
        $member_data['industry_id']);

    #jobclass
    insertPulldownMenu($member_data, 'Job&nbspClass', 'jobclass_id', 
        $mysession->get('jid'), $mysession->get('jname'), '',
        $member_data['jobclass_id']);

	echo '</table></td>';

	echo '<td style="vertical-align:top;" ><table style="width:50%;">';

    # status 
    insertPulldownMenu($member_data, 'Member&nbspStatus', 'status', 
        $mysession->get('sid'), $mysession->get('sdesc'), $disabled, 
        $member_data['member_status']);
    # committee 
    insertPulldownMenu($member_data, 'Committee', 'committee', 
        $mysession->get('cid'), $mysession->get('cname'), $disabled, 
        $member_data['committee']);
        
    # board position 
    insertPulldownMenu($member_data, 'Current&nbspBoard&nbspPosition', 'board_position', 
        $mysession->get('pid'), $mysession->get('pname'), $disabled, 
        $member_data['board_id']);

    # veteran
    $checked_not_vet = $member_data['veteran'] == 0 ? 'checked' : '';
    $checked_is_vet = $member_data['veteran'] == 1 ? 'checked' : '';
    echo "<tr><td class='blabel'>Veteran</td>";
    echo "<td><input $disabled type='radio' name='veteran' value='0' $checked_not_vet style='text-align:left;'>  No ";
    echo "&nbsp";
    echo "<input $disabled type='radio' name='veteran' value='1' $checked_is_vet>  Yes";
    echo "</td></tr>";

    # orientation date 
    echo "<tr><td class='blabel'>Orientation&nbspDate</td>" .
        "<td style='text-align:left;'><input $disabled type='text' name='orient_date' " .
        "value=" . 
        $_ = ($member_data['orient_date']=='00/00/0000' ? '' : $member_data['orient_date']) . 
        "></td></tr>";

    # join date
    echo "<tr><td class='blabel'>Join&nbspDate</td>" .
        "<td style='text-align:left;'><input $disabled type='text' name='join_date' " .
        "value=" .
        $_ = ($member_data['join_date']=='00/00/0000' ? '' : $member_data['join_date']) . 
        "></td></tr>";

    # active date 
    echo "<tr><td class='blabel'>Active&nbspDate</td>" .
        "<td style='text-align:left;'><input $disabled type='text' name='active_date' " .
        "value=" .
        $_ = ($member_data['active_date']=='00/00/0000' ? '' : $member_data['active_date']) . 
        "></td></tr>";

    # inactive date
    echo "<tr><td class='blabel'>Inactive&nbspDate</td>" .
        "<td style='text-align:left;'><input $disabled type='text' name='inactive_date' " .
        "value=" .
        $_ = ($member_data['inactive_date']=='00/00/0000' ? '' : $member_data['inactive_date']) . 
        "></td></tr>";

    # hours 
    echo "<tr><td class='blabel'>Volunteer&nbspHours&nbspBalance</td>" .
	"<td style='text-align:left;'>&nbsp&nbsp $member_data[hours_balance] </td></tr>";
	
	echo '</table></td>';
	
	echo '<table>';
    # desired_position
    echo '<tr><td class="blabel">Desired Position</td>' .
        '<td style="text-align:left; width:75ex;"><input type="text" name="desired_position" ' .
        'value="' . $member_data['desired_position'] . '"></td></tr>';
    
    # skills
    echo '<tr><td class="blabel">List&nbspyour&nbspskills&nbsp&&nbspexperience<br>';
    echo '(Content&nbsphere&nbspputs&nbspyour<br>skills & experience&nbspon&nbspthe<br>Employer/Recruiter&nbsppage)</td>';
    echo '<td><textarea name="profile" style="resize:none; height:7em; min-width:71ex;">' . $member_data['profile'] . '</textarea></td></tr>';
	
	echo '</table>';
	
	echo "<input type='hidden' name='board_member_id' value=$member_data[board_member_id]>";
	
	$form_id = mt_rand();
	$mysession->set('form_id', $form_id);	
	echo "<input type='hidden' name='form_id' value=$form_id>";
	
	echo "<input type='hidden' name='process' value='3'>";
	
	echo '<input type="submit" value="Submit Changes" name="action" class="validate">' .
	     '&nbsp<input type="button" value="Back" onClick=history.go(-1)>';

	echo "</form>";

	return;
}

#--------------------------------------------------------------------
function updateMembersTable($db, $user_id_to_edit, $postdata, $isAdminCall)
{
    $member_fields = array(
        $db->quoteName('first_name') . ' = ' . $db->quote($postdata->get('first_name','','STRING')),
        $db->quoteName('last_name') . ' = ' . $db->quote($postdata->get('last_name','','STRING')),
        $db->quoteName('email_address') . ' = ' . $db->quote($postdata->get('email_address','','STRING')),
        $db->quoteName('home_phone') . ' = ' . $db->quote(formatted_phone($postdata->get('home_phone','','STRING'))),
        $db->quoteName('mobile_phone') . ' = ' . $db->quote(formatted_phone($postdata->get('mobile_phone','','STRING'))),
        $db->quoteName('city') . ' = ' . $db->quote($postdata->get('city','','STRING')),
        $db->quoteName('state') . ' = ' . $db->quote($postdata->get('usstate','','STRING')),
        $db->quoteName('zip') . ' = ' . $db->quote($postdata->get('zip','','STRING')),
        $db->quoteName('desired_position') . ' = ' . $db->quote($postdata->get('desired_position','','STRING')),
        $db->quoteName('profile') . ' = ' . $db->quote($postdata->get('profile','','STRING')),
        $db->quoteName('veteran') . ' = ' . $db->quote($postdata->get('veteran','','INT')),
        $db->quoteName('industry_id') . ' = ' . $db->quote($postdata->get('industry_id','','INT')),
        $db->quoteName('jobclass_id') . ' = ' . $db->quote($postdata->get('jobclass_id','','INT'))
	    );
	    
	$fields = $member_fields;
	
	$query = $db->getQuery(True);
	$query->update($db->quoteName('eu_members'))
	      ->set($fields)
	      ->where($db->quoteName('member_id') . ' = ' . $user_id_to_edit);
	$db->setQuery($query);
	$result = $db->execute();
	
	if (($postdata->get('status') == 'A') && 
	    (strtotime($postdata->get('inactive_date')) > strtotime($postdata->get('active_date')))) {
			echo "<br/><strong>Warning: Status is Active but inactive date is more recent than active date</strong><br/>";
	} elseif (($postdata->get('status') == 'I') && 
	    (strtotime($postdata->get('inactive_date')) < strtotime($postdata->get('active_date')))) {
			echo "<br/><strong>Warning: Status is Inactive but active date is more recent than inactive date</strong><br/>";
	}
	return;
}
#--------------------------------------------------------------------
function updateMemberURLsTable($db, $user_id_to_edit, $postdata)
{
    $new_url = $postdata->get('member_url','','STRING'); // quoted below at db entry

	$query = $db->getQuery(True);
	$query->select('count(*)')
	      ->from($db->quoteName('eu_member_urls'))
	      ->where($db->quoteName('member_id') . ' = ' . 
	          $db->quote($user_id_to_edit));
    $db->setQuery($query);
	$url_count = $db->loadResult();
	if ($url_count > 1) {
	    echo "<br/><strong>Warning: This member has "; 
	    print_r($url_count); 
	    echo " URL(s) -- none were changed (contact IT dept to resolve)</strong><br/>";
	}

	$query = $db->getQuery(True);
	if (($url_count > 1) || 
	    ($url_count==0 && empty($new_url))) {
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
		      ->values(implode(',',$db->quote(array($user_id_to_edit,$new_url))));
		}
        $db->setQuery($query);
	    $result = $db->execute();
    }
    return;
}
#--------------------------------------------------------------------
function updateJoomlaUsersTable($db, $user_id_to_edit, $postdata)
{
	// change Joomla! username (=email address) and real name
	$realname = $postdata->get('first_name','','STRING') . ' ' . $postdata->get('last_name', '', 'STRING');
	$email = $postdata->get('email_address','PROBLEM','STRING');
	$email2 = $postdata->get('email_address_2','PROBLEM','STRING');
	
	// check that email address is confirmed
	if (strcmp($email, $email2)) {
		throw new \Exception("Email addresses must match");
	}

	// don't allow duplicate email addresses (they're used as usernames for eu)
	$query = $db->getQuery(True);
	$query->select('COUNT(*)')
	      ->from($db->quoteName('#__users'))
	      ->where($db->quoteName('email') . ' = ' . $db->quote($email))
	      ->where($db->quoteName('id') . ' != ' . $user_id_to_edit);
	$db->setQuery($query);
	$count = $db->loadResult();
	if ($count>0) {
		throw new \Exception("That username/email address ($email) is already in use");
	}
	
	$query = $db->getQuery(True);
	$query->update($db->quoteName('#__users'))
		  ->set($db->quoteName('name') . ' = ' . $db->quote($realname))
		  ->set($db->quoteName('username') . ' = ' . $db->quote($email))
		  ->set($db->quoteName('email') . ' = ' . $db->quote($email))
		  ->where($db->quoteName('id') . ' = ' . $user_id_to_edit);
	$db->setQuery($query);
	$db->execute();
	return;
}
#--------------------------------------------------------------------
function updateDatabaseTables($db, $user_id_to_edit, $postdata, $isAdminCall)
{
	try {
		$db->transactionStart();
	    updateJoomlaUsersTable($db, $user_id_to_edit, $postdata);
	    updateMembersTable($db, $user_id_to_edit, $postdata, $isAdminCall);  
	    updateMemberURLsTable($db, $user_id_to_edit, $postdata);
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

$isAdminCall = False;

$db = \JFactory::getDBO();
$mysession = \JFactory::getSession();
$postdata = \JFactory::getApplication()->input->post;
// echo "<br/><pre>"; print_r($postdata->getArray(array())); echo "</pre><br/>";

$user = \JFactory::getUser();
$user_id_to_edit = $user->id;
$mysession->set('user_id_to_edit', $user_id_to_edit);

if ($postdata->get('process')) {
	// update then display the individual member data if this is not a page reload
	if ($mysession->get('form_id') == $postdata->get('form_id')) {
		$user_id_to_edit = $mysession->get('user_id_to_edit');
		updateDatabaseTables($db, $user_id_to_edit, $postdata, $isAdminCall);
	} else {
		echo "<br/>(Change not submitted on reload)<br/>";
	}
}
show_member_form($db,$isAdminCall, $mysession);

/* Don't want update by reloading, so detect if form was reloaded by creating
 * a random form_id (in show_member_form()) that is saved in $_SESSION and 
 * also assigned to a hidden input accessed via $_POSTDATA. Compare the two 
 * values before updating tables -- if they match then continue with update, 
 * if they do not match, then the form was reloaded and just display
 */

?>
