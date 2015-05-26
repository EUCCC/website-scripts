<?php

/**
 * Dues Collecton script for Experience Unlimited Joomla! website
 * 
 * PHP version 5
 * 
 * @category  EUAdminScripts
 * @package   DuesCollection
 * @author    Jean Shih <jean1shih@gmail.com>
 * @author    Ben Bonham <bhbonham@gmail.com>
 * @copyright 2014-2015 Experience Unlimited
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   1.3
 * @link      https://github.com/EUCCC/website-scripts/blob/master/adminDuesCollection.php
 */
 
 /*------------------------------------------------------------------------
 * Note:
 *      1. The program applies monthly dues collection for $months_to_check
 *         months and it can be rerun.
 *      2. The monthly dues collection is applied to active members with
 *         active date one month older than the 1st day of processing month.
 * 
 * Modification log:
 * -------------------------------------------------------------------------
 * 2011/02/21   js   created
 * 2013/11/04   fgh  this file got clobbered. this is from a 10/23 save
 * 2013/11/17   fgh  background color no longer set here.
 * 2103/11/19   fgh  name changed to (Admin) Monthly Hour Charge, menu. task 15.1
 * 2014/08/17   js   v1.0 Changed to use JDatabase function, removed db_connect 
 *                     to avoid hard coding database name and password.
 * 2015/03/02   bb   v1.1 Added try/catch and $db->execute()
 * 2015/04/28   bb   v1.2 Changed title, added Back button
 * 05/20/2015 bb    add comments, change structure a bit
 */

namespace DuesCollection;

echo <<<EOS
<h2>Volunteer Hours - Apply Monthly Charge v1.2</h2></br>
EOS;

$doc = \JFactory::getDocument();

$style = <<<EOSTYLE

.titletext {
	font-family: Arial, Helvetica, sans-serif;
	font-size: 20px; color:#000066;
	margin-left:5cm;
	}
.msgtext {
	font-family: Arial, Helvetica, sans-serif;
	font-size: 16px; color:#000066;
	margin-left:2cm;
	}
.msgtext2 {
	font-family: Arial, Helvetica, sans-serif;
	font-size: 14px; color:#000066;
	margin-left:3cm;
	}
p {text-indent:420px;}
 /* body { background-color:#EBEBEB; } */
EOSTYLE;

$months_to_check = 2;
$today = date("F j, Y");

echo "<span class=\"msgtext2\">Run date: " . $today . "</br></br></span>";

$db = \JFactory::getDBO();

for ($i = 1; $i <= $months_to_check; $i++) {
    $process_month = strtotime(date("Y-m-d", strtotime($today)) . "-$i month");
    echo "<span class=\"msgtext2\">" . date('F Y', $process_month)."</span>";
    $query = "select count(*) from eu_member_hours where task_id=22
        and year(task_date) 
			= year(date_add(last_day(curdate()), interval -$i month))
        and month(task_date) 
			= month(date_add(last_day(curdate()), interval -$i month))";
    $db->setQuery($query);
    $total = $db->loadResult();

    if ($total==0) {
        try {
            $db->setQuery("call eu_monthly_charge($i);");
            $db->execute();
            echo " charges being tallied and sent to database.</br></br>";
        } catch (\Exception $e) {
            echo "<br/>" . $e->getMessage() . 
            "<br/><strong>-- database was not updated --</strong><br/>";
        }
    } else {
        echo " hours tallied on previous run.</br>";
    }

}

echo '<input name="back" type="button" value="Back" onClick="history.go(-1)">';

?>
