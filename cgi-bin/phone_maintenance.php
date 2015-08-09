<?php 
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_uid  = $_REQUEST['in_uid'];
// ----------------------------------------------------------
//
// file: phone_maintenance.php
// author: Bill MacAllister
// date: 2-Jan-2004

// Open a session and check for authorization
require('whm_php_sessions.inc');
require('whm_php_auth.inc');
require('inc_bind.php');
$ds = macdir_bind($ldap_server, 'GSSAPI');

$title = 'Contact Maintenance';
$heading = 'Contact Maintenance';

require('/etc/whm/macdir_auth.php');
require('inc_header.php');

if (!isset($_SESSION['in_msg'])) {
  $_SESSION['in_msg'] = '';
}

$ldap_filter = '';
if (strlen($in_uid)>0) {
  $ldap_filter = "uid=$in_uid";
}

$add_delete_flag = 1; 
$thisUID = $thisDN = '';
$entry_found = 0;

if (strlen($ldap_filter)>0) {
  $return_attr = array();
  $old_err = error_reporting(E_ERROR | E_PARSE);
  $sr = ldap_search ($ds, $ldap_base, $ldap_filter, $return_attr);
  $info = ldap_get_entries($ds, $sr);
  $tmp_err = error_reporting($old_err);
  $ret_cnt = $info["count"];
  if ($ret_cnt == 1) {
     $entry_found = 1;
     $in_uid = $info[0]["uid"][0];
  } elseif ($retcnt > 1) {
     $_SESSION['in_msg'] .= "More than on entry found for $ldap_filter search.\n";
  } else {
     $_SESSION['in_msg'] .= "No entry found for $ldap_filter\n";
  }
}

if ($entry_found) {
  $thisDN = $info[0]["dn"];
  $thisUID = $in_uid = $info[0]["uid"][0];
}
?>

<script language="JavaScript">

/* ----------------- */
/* Verify input data */
/* ----------------- */

function checkIt() {

    var f;
    var i;

    EmptyField = "";
    f = document.phone_maintenance;

    var nondigit = /\D/;
    var anumber = /^\d*\.?\d*$/;

    if (f.in_sn.value == EmptyField) {
        alert ("Please enter a Surname.");
        return false;
    }
    if (f.in_cn_cnt == null) {
      if (f.in_new_cn.value == EmptyField) {
        alert ("Please enter at least one common name.");
        return false;
      }
    }

    var t = "";
    if (f.in_uid.value == EmptyField) {
        t = f.in_givenname.value + f.in_sn.value;
        t = t.replace (/\s+/g, "");
        f.in_uid.value = t;
        return false;
    }

}

</script>

<table border="0" width="100%">
<tr>
 <td align="center">
  <form name="phone_maint_lookup"
        method="post"
        action="<?php print $_SERVER['PHP_SELF']; ?>">
  <table border="0">
  <tr>
    <td align="right">UID:</td>
    <td><input type="text"
               name="in_uid"
               value="<?php print $in_uid;?>">
    </td>
  </tr>
  <tr>
    <td colspan="2" align="center">
      <input type="submit" name="in_button_find" value="Lookup">
    </td>
  </tr>
<?php
if (strlen($_SESSION['in_msg'])>0) {
  echo "  <tr>\n";
  echo '    <td colspan="2" align="center">'.$_SESSION['in_msg']."</td>\n";
  echo "  </tr>\n";
  $_SESSION['in_msg'] = '';
}
?>
  </table>
  </form>
 </td>
 <td>
    <form name="reset"
          method="post"
          action="<?php print $_SERVER['PHP_SELF']; ?>">
    <input type="hidden" name="in_uid" value="">
    <input type="submit" name="reset" value="Reset">
    </form>
 </td>
</tr>
</table>

<p>
<form name="phone_maintenance"
      method="post"
      action="phone_maintenance_action.php"
      onsubmit="return checkIt()">
<input type="hidden" name="in_dn"  value="<?php print $thisDN;?>">
<table border="1" cellpadding="2" cellspacing="2">

<?php if ($entry_found) { ?>

<tr>
  <td align="right">UID:</td>
  <td colspan="5"><?php print $thisUID;?> </td>
  <input type="hidden" name="in_uid" value="<?php echo $in_uid;?>">
</tr>

<?php
} else { 
?>

<tr>
  <td align="right">UID:</td>
  <td colspan="5"><input type="text" name="in_uid"></td>
</tr>

<?php 
} 
?>

<tr>
 <td align="right">Given Name:</td>
 <td><input type="text"
            name="in_givenname"
            value="<?php print $info[0]["givenname"][0];?>"></td>
 <td align="right">Surname:</td>
 <td><input type="text"
            name="in_sn"
            value="<?php print $info[0]["sn"][0];?>"></td>
 <td colspan="2">&nbsp;</td>
</tr>

<tr>
 <td align="right">Nickname:</td>
 <td colspan="5"><input type="text"
            size="30"
            name="in_nickname"
            value="<?php print $info[0]["nickname"][0];?>"></td>
</tr>

<tr>
 <td align="right">Common Name:</td>
 <td colspan="5">
  <?php
  if ($entry_found>0) {
    $cn_cnt = $info[0]["cn"]["count"];
    echo "    <input type=\"hidden\" name=\"in_cn_cnt\" value=\"$cn_cnt\">\n";
    for ($i=0; $i<$cn_cnt; $i++) {
      $thisCN = $info[0]["cn"][$i];
  ?>
     <input type="checkbox" CHECKED
            name="in_cn[<?echo $i;?>]"
            value="<?php print $thisCN;?>"><?php print "$thisCN\n";?>
     <input type="hidden"
            name="in_cn_list[<?echo $i;?>]"
            value="<?php print $thisCN;?>">
     <br>
  <?php
    // end of for loop
    }
  // end of entry_found test
  }
?>
     <input type="text"
            size="40"
            name="in_new_cn"></td>
</tr>
<tr>
 <td align="right">Telephone Number:</td>
 <td colspan="5"><input type="text"
            name="in_telephonenumber"
            value="<?php print $info[0]["telephonenumber"][0];?>"></td>
</tr>
<tr>
 <td align="right">Cell Telephone Number:</td>
 <td colspan="5"><input type="text"
            name="in_mobile"
            value="<?php print $info[0]["mobile"][0];?>"></td>
</tr>
<tr>
 <td align="right">FAX Telephone Number:</td>
 <td colspan="5"><input type="text"
            name="in_facsimiletelephonenumber"
            value="<?php print $info[0]["facsimiletelephonenumber"][0];?>"></td>
</tr>
<tr>
 <td align="right">Pager Telephone Number:</td>
 <td colspan="5"><input type="text"
            name="in_pager"
            value="<?php print $info[0]["pager"][0];?>"></td>
</tr>
<tr>
<tr>
 <td align="right">Street Address:</td>
 <td colspan="5"><input type="text" size="50"
            name="in_postaladdress"
            value="<?php print $info[0]["postaladdress"][0];?>"></td>
</tr>
<tr>
 <td align="right">City:</td>
 <td><input type="text"
            name="in_l"
            value="<?php print $info[0]["l"][0];?>"></td>
 <td align="right">State:</td>
 <td><input type="text"
            size="4"
            name="in_st"
            value="<?php print $info[0]["st"][0];?>"></td>
 <td align="right">ZIP Code:</td>
 <td><input type="text"
            size="10"
            name="in_postalcode"
            value="<?php print $info[0]["postalcode"][0];?>"></td>
</tr>
<tr>
 <td align="right">Comments:</td>
 <td colspan="5"><textarea cols="60" rows="2" wrap="physical"
            name="in_comments"><?php print $info[0]["comments"][0];?></textarea></td>
</tr>
<tr>
 <td align="right">Mail:</td>
 <td colspan="5"><input type="text" size="40"
            name="in_mail"
            value="<?php print $info[0]["mail"][0];?>"></td>
</tr>

<tr>
 <td colspan="6">

 <table border="0" width="100%">
 <tr>

 <?php if ($entry_found>0) { ?>
 <td width="33%">
  <input type="submit" name="in_button_update" value="Update">
 </td>
 <?php } else { ?>
   <td>&nbsp;</td>
 <?php } ?>

 <?php if ($add_delete_flag>0 && $entry_found>0) { ?>
 <td width="33%" align="center">
  <input type="submit" name="in_button_delete" value="Delete">
 </td>
 <?php } else { ?>
   <td>&nbsp;</td>
 <?php } ?>

 <?php if ($add_delete_flag>0 && $entry_found==0) { ?>
 <td width="33%" align="right">
  <input type="submit" name="in_button_add" value="Add">
 </td>
 <?php } else { ?>
   <td>&nbsp;</td>
 <?php } ?>
 </tr>
 </table>

 </td>
</tr>
</table>
</form>

<?php require ('inc_footer.php'); ?>
