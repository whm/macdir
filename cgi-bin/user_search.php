<?php

// --------------------------------------------
// file: user_search.php
// author: Bill MacAllister
// date: 14-Oct-2002

$title   = 'Search for a Person';
$heading = 'Search for a Person';
require('inc_init.php');
require('inc_header_user_search.php');

// set default for amount of search details to display
if ( !empty($_REQUEST['in_more_search']) ) {
    $in_more_search = $_REQUEST['in_more_search'];
} else {
    $in_more_search
        = empty($_SESSION['SEAR_more_search'])
          ? 'no' : $_SESSION['SEAR_more_search'];
}
$_SESSION['SEAR_more_search'] = $in_more_search;
$more_label = '';
if (!empty($_SERVER['REMOTE_USER'])) {
    if ($in_more_search == 'yes') {
        $more_label
            = '<a href="' . $_SERVER['PHP_SELF'] . '?in_more_search=no">'
            . "Display Less Search Criteria</a>\n";
    } else {
        $more_label
            = '<a href="' . $_SERVER['PHP_SELF'] . '?in_more_search=yes">'
            . "Display More Search Criteria</a>\n";
    }
}

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
$form_val    = array();
foreach ($form as $formName => $ldapName) {
    $in_name  = "in_$formName";
    if ( !empty($_REQUEST[$in_name]) ) {
        $a_val = $_REQUEST[$in_name];
        $base_filter .= "($ldapName=*$a_val*)";
        $_SESSION["SEAR_$formName"] = $a_val;
        $form_val[$in_name]         = $a_val;
    } else {
        $form_val[$in_name] = '';
    }
}

if ( empty($base_filter) ) {

    // construct a filter from session information if there
    // is no input data
    foreach ($form as $formName => $ldapName) {
        $sess_name = "SEAR_$formName";
        if ( !empty($_SESSION[$sess_name]) ) {
            $a_val = $_SESSION[$sess_name];
            $base_filter .= "($ldapName=*$a_val*)";
        }
    }

} else {

    // reset session information from the input data
    foreach ($form as $formName => $ldapName) {
        $in_name = "in_$formName";
        $sess_name = "SEAR_$formName";
        $_SESSION[$sess_name]
            = empty($_REQUEST[$in_name]) ? '' : $_REQUEST[$in_name];
    }
}

?>

<div class="row">
<div class="col-10">
<p>
To search for somone just enter a fragment of the name.  If there is more
than one match
you will be presented with a list to select from.  If your selection
is too broad you will be presented with only a partial list of matches.
Anything that you enter into a field is treated as a wild card. For
example entering &quot;ill&quot; for the first name will return
matches for Bill and William.
</p>
</div>
</div>

<div class="row">
<div class="col-9">

<form action="user_search.php">
    <label for "in_firstname">First Name:</label>
    <input type="text"
           name="in_firstname"
           value="<?php print $form_val['in_firstname'];?>"
           size="32">
    <br/>

    <label for "in_lastname">Last Name:</label>
    <input type="text"
           name="in_lastname"
           value="<?php print $form_val['in_lastname'];?>"
           size="32">
    <br/>

<?php if ($in_more_search == 'yes') { ?>

    <label for "in_title">Title</label>
    <input type="text"
           name="in_title"
           value="<?php print $form_val['in_title'];?>"
           size="32">
    <br/>
<?php } ?>

    <label for "in_commonname">Common Name:</label>
    <input type="text"
           name="in_commonname"
           value="<?php print $form_val['in_commonname'];?>"
           size="32">
    <br/>

<?php if ($in_more_search == 'yes') { ?>

    <label for "in_postaladdress">Postal Address:</label>
    <input type="text"
           name="in_postaladdress"
           value="<?php print $form_val['in_postaladdress'];?>"
           size="32">
    <br/>

    <label for "in_city">City:</label>
    <input type="text"
           name="in_city"
           value="<?php print $form_val['in_city'];?>"
           size="32">
    <br/>

    <label for "in_state">State:</label>
    <input type="text"
           name="in_state"
           value="<?php print $form_val['in_state'];?>"
           size="32">
    <br/>

    <label for "in_telephone_number">Telephone Number:</label>
    <input type="text"
           name="in_telephone_number"
           value="<?php print $form_val['in_telephone_number'];?>"
           size="32">
    <br/>

    <label for "in_workphone">Work Telephone Number:</label>
    <input type="text"
           name="in_workphone"
           value="<?php print $form_val['in_workphone'];?>"
           size="32">
    <br/>

    <label for "in_cell_number">Cell Phone Number:</label>
    <input type="text"
           name="in_cell_number"
           value="<?php print $form_val['in_cell_number'];?>"
           size="32">
    <br/>

    <label for "in_email">eMail:</label>
    <input type="text"
           name="in_email"
           value="<?php print $form_val['in_email'];?>"
           size="32">
    <br/>

    <label for "in_maildistributionid">Mail Distribution List:</label>
    <input type="text"
           name="in_maildistributionid"
           value="<?php print $form_val['in_maildistributionid'];?>"
           size="32">
    <br/>

<?php } ?>
<p>
<label for="btn_search"><?php echo $more_label;?></label>
<input type="submit" value="Search Directory" name="in_button_search">
</p>

<?php
if ( !empty($_SESSION['in_msg']) ) {
    echo $_SESSION['in_msg'];
    echo "<br/>\n";
    $_SESSION['in_msg'] = '';
}
?>
</form>

<?php
if ( !empty($base_filter) ) {
  $filter = '(&(objectclass=person)'.$base_filter.')';
  $base_dn = $CONF['ldap_base'];
  $return_attr = array('cn',
                       'mail',
                       'mobile',
                       'telephonenumber',
                       'workphone',
                       'uid');
  if (isset($_SERVER['REMOTE_USER'])) {
      $ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');
  } else {
      $ds = macdir_bind($CONF['ldap_server'], '');
  }

  $sr = ldap_search($ds, $base_dn, $filter, $return_attr);
  $info = ldap_get_entries($ds, $sr);
  $ret_cnt = $info["count"];
  if ($ret_cnt) {
    echo "<table>\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo " <th>Common Name</th>\n";
    echo " <th>Email Address</th>\n";
    echo " <th>Home Phone</th>\n";
    echo " <th>Work Phone</th>\n";
    echo " <th>Cell Phone</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    for ($i=0; $i<$info["count"]; $i++) {
      $a_dn     = $info[$i]["dn"];
      $a_dn_url = urlencode($a_dn);
      $a_cn     = $info[$i]["cn"][0];
      $a_uid    = $info[$i]["uid"][0];

      $a_mail      = nbsp_html($info[$i]["mail"][0]);
      $a_mobile    = nbsp_html($info[$i]["mobile"][0]);
      $a_workphone = nbsp_html($info[$i]["workphone"][0]);
      $a_phone     = nbsp_html($info[$i]["telephonenumber"][0]);
      $a_maint_link = '&nbsp;';
      if ($ldap_admin>0) {
        $a_maint_link = '<a href="user_maint.php'
            . '?in_uid=' . $a_uid
            . '"><img src="/macdir-images/icon-edit.png" border="0"></a>';
      } elseif ($phone_admin>0) {
        $a_maint_link = '<a href="phone_maintenance.php'
            . '?in_uid=' . $a_uid
            . '"><img src="/macdir-images/icon-edit.png" border="0"></a>';
      }
      $detail_link = '&nbsp;';
      if ( isset($_SERVER['REMOTE_USER']) ) {
          $detail_link = "<a href=\"user_details.php?in_dn=$a_dn_url\">"
              . '<img src="/macdir-images/icon-view-details.png" border="0"</a>';
      }
      echo "<tr>\n";
      echo " <td>$a_maint_link$detail_link&nbsp;$a_cn</td>\n";
      echo " <td>$a_mail</td>\n";
      echo " <td>$a_phone</td>\n";
      echo " <td>$a_workphone</td>\n";
      echo " <td>$a_mobile</td>\n";
      echo "</tr>\n";
    }
    echo "</tbody>\n";
    echo "</table>\n";
  } else {
    echo '<div align="center">' . "\n";
    echo warn_html('No entries found.');
    echo "</div>\n";
  }
}
?>

</div>

<?php require('inc_menu.php');?>
</div>

<?php require('inc_footer.php');?>
