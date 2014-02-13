<?php

// --------------------------------------------
// file: user_search.php
// author: Bill MacAllister
// date: 14-Oct-2002

session_start();

$title = 'Search for a Person';
$heading = 'MacAllister Directory';
require('inc_header.php');
require ('/etc/whm/macdir_auth.php');

// -- disable admin access without authentication
$ldap_manager = '';
$ldap_password = '';

// clean out the messages
$msg = '';

// set default for amount of search details to display
if ( isset($_REQUEST['in_more_search']) ) {
    $in_more_search = $_REQUEST['in_more_search'];
} else {
    if ( isset($_SESSION['s_more_search']) ) {
        $in_more_search = $_SESSION['s_more_search'];
    } else {
        $in_more_search = 'no';
    }
}
$_SESSION['s_more_search'] = $in_more_search;

// create a form to attribute mapping
$form["firstname"]          = "givenname";
$form["lastname"]           = "sn";
$form["title"]              = "title";
$form["city"]               = "l";
$form["commonname"]         = "commonname";
$form["postaladdress"]      = "postaladdress";
$form["state"]              = "st";
$form["telephone_number"]   = "telephonenumber";
$form["workphone"]          = "workphone";
$form["cell_number"]        = "mobile";
$form["email"]              = "mail";
$form["maildistributionid"] = "maildistributionid";

// construct the filter from input data
$base_filter = '';
foreach ($form as $formName => $ldapName) {
  $name = "in_$formName"; 
  if (isset($_REQUEST[$name])) {
    $a_val = $_REQUEST[$name];
    if (strlen($a_val)>0) {$base_filter .= "($ldapName=*$a_val*)";}
  }
}

if ( ! isset($base_filter) ) {

  // construct a filter from session information if there
  // is no input data
  foreach ($form as $formName => $ldapName) {
    $sessName = "SEAR_$formName";
    if ( isset($_SESSION[$sessName]) ) {
        $a_val = $_SESSION[$sessName];
        if ( strlen($a_val) > 0 ) {
            $base_filter .= "($ldapName=*$a_val*)";
        }
    }
  }

} else {

  // reset session information from the input data
  foreach ($form as $formName => $ldapName) {
    $name = "in_$formName"; 
    $sessName = "SEAR_$formName";
    if (isset($$name)) {
      $_SESSION[$sessName] = $$name;
    } else {
      $_SESSION[$sessName] = '';
    }
  }

}

?>

To search for an somone just enter some or all of the data requested
below.  If there is more than one match you will be presented with a 
list to select from.  If your selection is too broad you will be presented 
with only a partial list of matches. Anything that you enter into 
a field is treated as a wild card. For example entering &quot;ill&quot; 
for the first name will return matches for Bill and William.

<p>
<div align="center">
<form action="user_search.php">
<table border="0">
  <tr> 
    <td> 
      <div align="right">First Name:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_firstname" 
             value="<?php print $_SESSION['SEAR_firstname'];?>"
             size="32">
    </td>
  </tr>
  <tr> 
    <td> 
      <div align="right">Last Name:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_lastname" 
             value="<?php print $_SESSION['SEAR_lastname'];?>"
             size="32">
    </td>
  </tr>
<? if ($in_more_search == 'yes') { ?>
  <tr> 
    <td> 
      <div align="right">Title:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_title" 
             value="<?php print $_SESSION['SEAR_title'];?>"
             size="32">
    </td>
  </tr>
<?php } ?>
  <tr> 
    <td> 
      <div align="right">Common Name:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_commonname" 
             value="<?php print $_SESSION['SEAR_commonname'];?>"
             size="32">
    </td>
  </tr>
<? if ($in_more_search == 'yes') { ?>
  <tr> 
    <td> 
      <div align="right">Postal Address:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_postaladdress" 
             value="<?php print $_SESSION['SEAR_postaladdress'];?>"
             size="32">
    </td>
  </tr>
  <tr> 
    <td> 
      <div align="right">City:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_city" 
             value="<?php print $_SESSION['SEAR_city'];?>"
             size="32">
    </td>
  </tr>
  <tr> 
    <td> 
      <div align="right">State:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_state" 
             value="<?php print $_SESSION['SEAR_state'];?>"
             size="32">
    </td>
  </tr>
  <tr> 
    <td> 
      <div align="right">Telephone Number:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_telephone_number" 
             value="<?php print $_SESSION['SEAR_telephone_number'];?>"
             size="32">
    </td>
  </tr>
  <tr> 
    <td> 
      <div align="right">Work Telephone Number:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_workphone" 
             value="<?php print $_SESSION['SEAR_workphone'];?>"
             size="32">
    </td>
  </tr>
  <tr> 
    <td> 
      <div align="right">Cell Phone Number:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_cell_number" 
             value="<?php print $_SESSION['SEAR_cell_number'];?>"
             size="32">
    </td>
  </tr>
  <tr> 
    <td> 
      <div align="right">eMail:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_email" 
             value="<?php print $_SESSION['SEAR_email'];?>"
             size="32">
    </td>
  </tr>
  <tr> 
    <td> 
      <div align="right">Mail Distribution List:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_maildistributionid" 
             value="<?php print $_SESSION['SEAR_maildistributionid'];?>"
             size="32">
    </td>
  </tr>
<?php } ?>
  <tr><td align="center" colspan="2">
      <input type="submit" value="Search Directory" name="button">
      </td>
  </tr>
<?php if ( isset($msg) ) { ?>
  <tr><td align="center" colspan="2">
      <?php echo $msg; $msg = '';?>
      </td>
  </tr>
<?php } ?>
</table>
</form>

<p>

<?php
if ( isset($base_filter) && strlen($base_filter)>0) {
  $filter = '(&(objectclass=person)'.$base_filter.')';
  $base_dn = $ldap_base;
  $return_attr = array('cn',
                       'mail',
                       'mobile',
                       'telephonenumber',
                       'workphone',
                       'uid');

  $ds = ldap_connect($ldap_server);
  $r  = ldap_bind($ds,$ldap_manager,$ldap_password);
  $sr = ldap_search($ds, $base_dn, $filter, $return_attr);  
  $info = ldap_get_entries($ds, $sr);
  $ret_cnt = $info["count"];
  if ($ret_cnt) {
    echo "<table border=\"1\" cellpadding=\"2\">\n";
    echo "<tr>\n";
    echo " <th>Common Name</th>\n";
    echo " <th>Email Address</th>\n";
    echo " <th>Home Phone</th>\n";
    echo " <th>Work Phone</th>\n";
    echo " <th>Cell Phone</th>\n";
    echo "</tr>\n";
    for ($i=0; $i<$info["count"]; $i++) {
      $a_dn = $info[$i]["dn"];
      $a_dn_url = urlencode($a_dn);
      $a_cn = $info[$i]["cn"][0];
      $a_uid = $info[$i]["uid"][0];

      $a_mail = $info[$i]["mail"][0];
      $a_mobile = $info[$i]["mobile"][0];
      $a_workphone = $info[$i]["workphone"][0];
      $a_phone = $info[$i]["telephonenumber"][0];
      $a_maint_link = '';
      if ($ldap_admin>0) {
        $a_maint_link = '<a href="user_maint.php'
                      . '?in_uid=' . $a_uid
                      . '"><img src="/macdir-images/icon-edit.png" border="0"></a>';
      } elseif ($phone_admin>0) {
        $a_maint_link = '<a href="phone_maintenance.php'
                      . '?in_uid=' . $a_uid
                      . '"><img src="/macdir-images/icon-edit.png" border="0"></a>';
      }
      $detail_link = '';
      if (isset($menuLoggedIn)) {
        $detail_link = "<a href=\"user_details.php?in_dn=$a_dn_url\">"
             . '<img src="/macdir-images/icon-view-details.png" border="0"</a>';
      }
      echo "<tr>\n";
      echo " <td>$a_maint_link$detail_link&nbsp;$a_cn</td>\n";
      echo " <td><a href=\"mailto:$a_mail\">$a_mail</a>&nbsp;</td>\n";
      echo " <td>$a_phone &nbsp;</td>\n";
      echo " <td>$a_workphone &nbsp;</td>\n";
      echo " <td>$a_mobile &nbsp;</td>\n";
      echo "</tr>\n";
    }
    echo "</table>\n";
  } else {
    echo "<p>\n";
    echo "<div align=\"center\">\n";
    echo "<font face=\"Arial, Helvetica, sans-serif\"\n";
    echo "      size=\"+1\"\n";
    echo "      color=\"#FF0000\">No entries found.</font>\n";
    echo "</div>\n";
  }
}
?>
<p>
<?php 
if (isset($_SERVER['REMOTE_USER'])) {
    if ($in_more_search == 'yes') { 
        print '<a href="' . $_SERVER['PHP_SELF'] . '?in_more_search=no">';
        print "Display Less Search Criteria</a>\n";
    } else {
        print '<a href="' . $_SERVER['PHP_SELF'] . '?in_more_search=yes">';
        print "Display More Search Criteria</a>\n";
    } 
}
?>
<p>

</div>

<?php require('inc_footer.php');?>

