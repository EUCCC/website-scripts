<?php
/**
 * Add new member to EU member database and create login for them on EU website
 * 
 * This script asks for name, email, telephone, and orientation date
 *   for a new pending member. The script creates a new joomla user (whose 
 *   userid is the email address) and adds the user to the eu_members table.
 *   The new user is blocked.
 *   This script also sends email to the user. The email is composed by taking
 *   the contents of the article "Pending Member Welcome Template" (which should
 *   be html) and replacing {first_name} and {orient_date} with data entered 
 *   in this form.
 * 
 * PHP version 5
 * 
 * @category  EUAdminScripts
 * @package   AdminAddNewMember
 * @author    Ben Bonham <bhbonham@gmail.com>
 * @copyright 2015 Experience Unlimited
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   1.5
 * @link      http://euccc.org/live/admin-add-new-member
 */
 
/*
 * Summary of revisions
 * 05/20/2015	bb	1.5	don't pass $db to functions, add comments
 * 1.4 - bb 4/25/15 - format phone #s, put Reset button on different form so it
 * 						doesn't validate input fields, 
 * 						added Back button, validate date in loadMemberData
 * 1.3 - bb -- change user addition and email welcome (not activation) to user
 * 1.2 - bb -- send activation email to user
 * 1.1 - bb -- don't add member if orientation date is invalid
 * 1.0 - bb -- initial commit
 */
 
namespace AdminAddNewMember;

echo <<<EOS
<h2>Add New Pending Status Member v1.5</h2>
Create username and enter basic data for new pending status member<br/> <br/>

EMAIL HAS BEEN DISABLED FOR TESTING

EOS;

// 

$doc = \JFactory::getDocument();
$style = ".blabel {"
    . "text-align:right; "
    . "max-width:300px; "
    . "padding:0px 10px;"
        . "color: rgb(0,0,255);"
        . "}"; 
$doc->addStyleDeclaration($style);

// 
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
    // or mm/dd/yyyy) but interprets dates as int'l if they have dashes 
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
    // interpret very messy phone #'s
    $ph_pat = "/^[\D\s]*(\d{3})[\D\s]*(\d{3})[\D\s]*(\d{4})[\D\s]*$/"; 
    
    if (empty($phone_strg)) {
        return '';
    } else if (preg_match($ph_pat, $phone_strg, $matches_out)) {
        $new_phone_strg = '(' . $matches_out[1] . ') ' . $matches_out[2] . 
        '-' . $matches_out[3];
        return $new_phone_strg;
    } else {
        throw new \Exception(
            "<strong>bad format for phone: $phone_strg</strong>"
        );
    }
}
// --------------------------------------------------------------------
/**
 * Send email to welcome new Pending member
 * 
 * @param array $member_data array of member data
 * 
 * @return void
 */ 
function sendWelcomeEmail($member_data)
{
    $db = \JFactory::getDBO();
    $first_name = $member_data['first_name'];
    $email_address = $member_data['email_address'];
    $orient_date = validatedDate($member_data['orient_date']);
    
    $sender = array( 
    'euoffice@euccc.org',
    'EU Business Operations');
        
    // load the template for the pending member welcome email
    $query = $db->getQuery(true);
    $query->select('introtext')
        ->from($db->quoteName('#__content'))
        ->where(
            $db->quoteName('title') . 
            ' LIKE "%pending%member%welcome%template"'
        );
    $db->setQuery($query);
    $body = $db->loadResult();
    // fill in the blanks with pending member information
    $body = str_replace("{first_name}", $first_name, $body);
    $body = str_replace("{orient_date}", $orient_date, $body);

    $mailer = \JFactory::getMailer();
    $mailer->setSender($sender);
    $mailer->addRecipient($email_address);
    $mailer->setSubject('Welcome Pending EU Member');
    $mailer->setBody($body);
    $mailer->isHTML(true);
    $mailer->Encoding = 'base64';

    $send = $mailer->Send();
    if ($send !== true ) {
        throw new \Exception('Error sending email: ' . $send->__toString());
    } else {
        echo 'Mail sent';
    }
    return;
}
// --------------------------------------------------------------------
/**
 * Update joomla users table (new name & email) and block login unless 
 *         status is Active or Goldcard-Pending
 * 
 * @param array $member_data array of member data
 * 
 * @return int $new_user_id
 */ 
function updateJoomlaUsersTable($member_data)
{
    $db = \JFactory::getDBO();
    // Joomla! username (=email_address) and real name
    $real_name = $member_data['first_name'] . ' ' . $member_data['last_name'];
    $username = $member_data['email_address'];
    $email_address = $member_data['email_address'];
    $email_address_2 = $member_data['email_address_2'];

    // check that email_address is confirmed
    if (strcmp($email_address, $email_address_2)) {
        throw new \Exception("email_addresses must match");
    }

    // don't allow duplicate email_addresses (they're used as usernames for eu)
    // (joomla doesn't require unique email_addresses on its own, though)
    $query = $db->getQuery(true);
    $query->select('COUNT(*)')
        ->from($db->quoteName('#__users'))
        ->where($db->quoteName('email') . ' = ' . $db->quote($email_address));
    $db->setQuery($query);
    $count = $db->loadResult();
    if ($count>0) {
        throw new \Exception(
            "That username/email_address ($email_address) is already in use"
        );
    }

    // "generate" a new JUser Object
    // it's important to set the "0" otherwise 
    // your admin user information will be loaded    
    $user = \JFactory::getUser(0); 

                                            
    // include libraries/application/component/helper.php
    jimport('joomla.application.component.helper'); 
    $usersParams = \JComponentHelper::getParams('com_users'); // load the Params
    
    $userdata = array(); // place user data in an array for storing.
    //set username
    $userdata['username'] = $member_data['email_address'];
    //set email
    $userdata['email'] = $member_data['email_address'];
    //set real name
    $userdata['name'] = $member_data['first_name'] . ' ' . $member_data['last_name'];
    //set password
    $userdata['password'] = '';
    //must be set the same as above to confirm password..
    $userdata['password2'] = '';
    //set default group.
    $defaultUserGroup = $usersParams->get('new_usertype', 2);
    //default to defaultUserGroup i.e.,Registered
    $userdata['groups']=array($defaultUserGroup);
    // set this to 0 so the user will be added immediately.
    $userdata['block'] = 1; 
    //now to add the new user to the database.
    if (!$user->bind($userdata)) { // bind the data and if it fails raise an error
        throw new \Exception(
            "Registration failed in EU script binding userdata" . 
            $user->getError()
        );
        exit;
    }
    if (!$user->save()) { // now check if the new user is saved
        throw new \Exception(
            "Registration failed in EU script saving user" . 
            $user->getError()
        );
        exit;
    }
    // If you reach this point then everything went good. 
    // You can now use the $user object.

    $new_user_id = $user->id;
    
    return $new_user_id;
}
// ------------------------------------------------------------------------
/**
 * Update eu_members table
 * 
 * @param array $member_data data for new pending member
 * @param int   $new_user_id EU member id of new pending member
 * 
 * @return void
 */ 
function updateMembersTable($member_data, $new_user_id)
{
    $db = \JFactory::getDBO();
    $orient_date = validatedDate($member_data['orient_date']);
    if (empty($orient_date)) {
        throw new \Exception("Invalid Orientation Date", 409);
    }
    $columns = array(
        'member_id',
        'first_name',
        'last_name',
        'email_address',
        'home_phone',
        'mobile_phone',
        'orient_date',
       );
    $values = array(
        $db->quote($new_user_id),
        $db->quote($member_data['first_name']),
        $db->quote($member_data['last_name']),
        $db->quote($member_data['email_address']),
        $db->quote($member_data['home_phone']),
        $db->quote($member_data['mobile_phone']),
        "STR_TO_DATE('$orient_date', '%m/%d/%Y')"
       );
        
    $query = $db->getQuery(true);
    $query
        ->insert($db->quoteName('eu_members'))
        ->columns($db->quoteName($columns))
        ->values(implode(',', $values));
    $db->setQuery($query);
    $result = $db->query();
    
    return;
}
// --------------------------------------------------------------------
/**
 * Update database tables as a single transaction
 * 
 * @param array $member_data array of member data
 * 
 * @return void
 */ 
function updateDatabaseTables($member_data)
{
    $db = \JFactory::getDBO();
    try {
        $db->transactionStart();
        $new_user_id = updateJoomlaUsersTable($member_data);
        updateMembersTable($member_data, $new_user_id);
        //	    sendWelcomeEmail($member_data);
        $db->transactionCommit();        
        echo "<br/>Database updated<br/>";
    } catch (\Exception $e) {
        $db->transactionRollback();
        echo "<br/><strong>" . $e->getMessage() . 
        " -- database was not updated</strong><br/>";
    }
    return;
}
// --------------------------------------------------------------------
/**
 * Reset member data array to defaults (empty strings)
 * 
 * @return array $member_data  array of member data
 */ 
function resetMemberData()
{
    $member_data = array();
    $member_data['first_name'] = '';
    $member_data['last_name'] = '';
    $member_data['email_address'] = '';
    $member_data['email_address_2'] = '';
    $member_data['home_phone'] = '';
    $member_data['mobile_phone'] = '';
    $member_data['orient_date'] = '';
    return $member_data;
}
// --------------------------------------------------------------------
/**
 * Load member data array from form
 * 
 * @return array $member_data  array of member data
 */ 
function loadMemberData()
{
    $postdata = \JFactory::getApplication()->input->post;
    $member_data['first_name'] = $postdata->get('first_name', '', 'STRING');
    $member_data['last_name'] = $postdata->get('last_name', '', 'STRING');
    $member_data['email_address'] 
        = $postdata->get('email_address', '', 'STRING');
    $member_data['email_address_2'] 
        = $postdata->get('email_address_2', '', 'STRING');
    $member_data['home_phone']
        = formattedPhone($postdata->get('home_phone', '', 'STRING'));
    $member_data['mobile_phone'] 
        = formattedPhone($postdata->get('mobile_phone', '', 'STRING'));
    $member_data['orient_date'] 
        = validatedDate($postdata->get('orient_date', '', 'STRING'));
    return $member_data;
}
// --------------------------------------------------------------------
/**
 * Show form fields for member data entry
 * 
 * @param array $member_data array of member data
 * 
 * @return void
 */ 
function showAddMemberForm($member_data)
{
     echo <<<EOInstructions
<br/><strong>Instructions</strong><br/>
Enter values, then click 'Submit'
<ul>
	<li>"Welcome Pending Member Email" will be sent to address entered</li>
</ul>
<br/>
    
EOInstructions;

    echo "<form method='POST' class='form-validate'>";
    echo '<table>';
    // first name 
    echo '<tr><td class="blabel">First&nbspName</td>' .
        '<td><input type="text" name="first_name" ' .
        'value="' . $member_data['first_name'] . '" class="required"></td></tr>';
        
    // last name 
    echo '<tr><td class="blabel">Last&nbspName</td>' .
        '<td><input type="text" name="last_name" ' .
        'value="' . $member_data['last_name'] . '" class="required"></td></tr>';

    // home phone
    echo '<tr><td class="blabel">Home phone</td>' .
        '<td><input type="text" name="home_phone" ' .
        'value="' . $member_data['home_phone'] . 
        '" title="Enter as nnn-nnn-nnnn, (nnn) nnn-nnnn, or nnnnnnnnnn">' .
        '</td></tr>';

    // mobile phone
    echo '<tr><td class="blabel">Mobile phone</td>' .
        '<td><input type="text" name="mobile_phone" ' .
        'value="' . $member_data['mobile_phone'] . 
        '" title="Enter as nnn-nnn-nnnn, (nnn) nnn-nnnn, or nnnnnnnnnn">' .
        '</td></tr>';

    // orientation date 
    echo "<tr><td class='blabel'>Orientation&nbspDate<br></td>" .
        "<td><input type='text' name='orient_date' " .
        "value='$member_data[orient_date]' class='required'" .
        " title='Enter as mm/dd/yyyy or yyyy-mm-dd'></td></tr>";

    // email_address 
    echo '<tr><td class="blabel">' .
        'email&nbspaddress (EU&nbspwebsite&nbspusername)</td>' .
        '<td><input type="text" name="email_address" ' .
        'value="' . $member_data['email_address'] . 
        '" class="required validate-email_address"></td></tr>';

    // confirm email_address 
    echo '<tr><td class="blabel">Confirm&nbspemail&nbspaddress</td>' .
        '<td><input type="text" name="email_address_2" ' .
        'value="' . $member_data['email_address_2'] . 
        '" class="required validate-email_address"></td></tr>';

    echo '</table>';
    
    echo '<input type="submit" value="Submit" name="action" class="validate">';
    echo "<input type='hidden' name='process' value='1'>";
    echo '</form>';
    echo '<form>';
    echo '<input type="submit" value="Reset" name="action">';
    echo '&nbsp<input type="button" value="Back" onClick=history.go(-1)>';
    echo '</form>';
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
    $member_data = resetMemberData();
} else {
    $member_data = loadMemberData();
    updateDatabaseTables($member_data);
}
showAddMemberForm($member_data);
    
?>
