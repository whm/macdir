<?php
// file: app_access_maint.php
// author: Bill MacAllister
// date: 18-Jan-2003
// description: This form is granting users access to PRIDE Web Files Services

// Open a session and check for authorization
require('whm_php_sessions.inc');
require('whm_php_auth.inc');
whm_auth("user");

$title = 'PRIDE Application Access Maintenance';
$heading = 'PRIDE Application Access Maintenance';

require ('inc_header.php');
require('/etc/whm/macdir_auth.php');
$ds = ldap_connect("ldap.whm.com");

// -----------------------------------------------------
// get a list of application groups to be managed

require('inc_groups_managed.php');

// ---------------------------------------------------------
// Lookup an entry

$entry_found = 0;
$add_delete_flag = 1;
$thisUID = $thisDN = '';

$warn = '<font color="#cc0000">';
$ok = '<font color="#00cc00">';
$ef = "</font><br>\n";

$ldap_filter = '';
if (strlen($in_employeenumber)>0) {
  $ldap_filter = "employeeNumber=$in_employeenumber";
} elseif (strlen($in_uid)>0) {
  $ldap_filter = "uid=$in_uid";
}

if (strlen($ldap_filter)>0) {

  $return_attr = array();
  $r=ldap_bind($ds,$pidir_manager,$pidir_password);
  $old_err = error_reporting(E_ERROR | E_PARSE);
  $sr = ldap_search ($ds, $pidir_base, $ldap_filter, $return_attr);
  $info = ldap_get_entries($ds, $sr);
  $tmp_err = error_reporting($old_err);
  $ret_cnt = $info["count"];
  if ($ret_cnt == 1) {
     $entry_found = 1;
     $in_uid = $info[0]["uid"][0];
  } elseif ($retcnt > 1) {
     $msg .= "$warn More than one entry found for $ldap_filter search.$ef\n";
  } else {
     $msg .= "$warn No entry found. ($ldap_filter)$ef\n";
  }

  if ($entry_found) {

    $thisDN = $info[0]["dn"];
    $thisUID = $in_uid            = $info[0]["uid"][0];
    $thisEmp = $in_employeenumber = $info[0]["employeenumber"][0];

    if (strlen($thisUID)>0) {

      // application groups for this user
      $aFilter = "(&(objectclass=prideApplication)(memberUid=$thisUID))";
      $aRetAttrs = array ('cn','description');
      $old_err = error_reporting(E_ERROR | E_PARSE);
      $sr = ldap_search ($ds, $pidir_base, $aFilter, $aRetAttrs);
      $aEntries = ldap_get_entries($ds, $sr);
      $tmp_err = error_reporting($old_err);
      $aCnt = $aEntries["count"];
      $thisApps = array();
      for ($gi=0; $gi<$aCnt; $gi++) {
        $a_cn = $aEntries[$gi]['cn'][0];
        $a_description = $aEntries[$gi]['description'][0];
        $thisApps[$a_cn] = $a_description;
      }
      asort ($thisApps);
    }

  }

}

?>
<script language="JavaScript">
  /* Make lookups easier */
  function clear_empno() {
    document.app_access_maint_find.in_employeenumber.value = '';
  }
</script>

<table border="0" width="100%">
<tr><td align="center" width="90%">

  <form name="app_access_maint_find"
        method="post"
        action="<?php print $PHP_SELF; ?>">
  <table border="0" width="100%">
  <tr>
    <td align="right">Employee Number:</td>
    <td align="left"><input type="text"
             name="in_employeenumber"
             value="<?php print $in_employeenumber;?>">
    </td>
  </tr>
  <tr>
    <td align="right">Computer UID:</td>
    <td align="left"><input type="text"
                                  name="in_uid"
                                  value="<?php print $in_uid;?>"
                                  onchange="clear_empno();">
    </td>
  </tr>
  <tr>
    <td align="center" colspan="2">
      <input type="submit" name="btn_find" value="Lookup">
    </td>
  </tr>
  <?php
  if (strlen($msg)>0) {
    echo "  <tr>\n";
    echo "    <td colspan=\"2\" align=\"center\">$msg</td>\n";
    echo "  </tr>\n";
    $msg = '';
  }
  if ( isset($_SESSION['in_msg']) ) {
    if (strlen($_SESSION['in_msg']) > 0) {
      echo "  <tr>\n";
      echo "    <td colspan=\"2\"\n";
      echo "        align=\"center\">".$_SESSION['in_msg']."</td>\n";
      echo "</tr>\n";
      $_SESSION['in_msg'] = '';
    }
  }
?>
  </table>
  </form>

</td>
<td align="center" width="10%">

<script language="JavaScript">

/* ----------------- */
/* Verify input data */
/* ----------------- */

function checkIt() {

  var f;
  var i;
  var outData = "";
  
  EmptyField = "";
  f = document.app_access_maint;

  var nondigit = /\D/;
  var anumber = /^\d*\.?\d*$/;

  if (f.in_uid.value == EmptyField) {
    alert ("Please enter a UID");
    return false;
  }
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
  if (f.in_comments.value == EmptyField) {
    alert ("Please enter a descriptive comment.");
    return false;
  }

<?php if (strlen($thisEmp)==0 && strlen($thisUID)==0) { ?>

if (f.in_pass1.value == EmptyField) {
  alert ("Please enter at password.");
  return false;
}
if (f.in_pass1.value != f.in_pass2.value) {
  alert ("Password does not match Verify.  Please tray again.");
  return false;
}

<?php } ?>

  return true;

}

</script>

<form name="reset"
      method="post"
      action="<?php print $PHP_SELF; ?>">
<input type="hidden" name="in_uid" value="">
<input type="submit" name="reset" value="Reset">
</form>

</td>
</tr>
</table>

<p>
<form name="app_access_maint"
      method="post"
      action="app_access_maint_action"
      onsubmit="return checkIt()">

<input type="hidden" name="in_uid"
       value="<?php print $thisUID;?>">
<input type="hidden" name="in_employeenumber"
       value="<?php print $thisEmp;?>">
<input type="hidden" name="in_dn"
       value="<?php print $thisDN;?>">
<table border="1" cellpadding="2">

<?php 
$pwd_href = 'set_password?in_uid='.$thisUID;

// -------------------------------------------
// Non employee maintenance

if (strlen($thisEmp)==0) {
?>
<tr>
  <td align="right">Computer UID:</td>

<?php if (strlen($thisUID)==0) { ?>
  <td>
      <input type="text"
             name="in_uid"
             value="<?php print $thisUID;?>">
  </td>
  <td align="right">Password:</td>
  <td><input type="password"
             name="in_pass1">
  </td>
  <td align="right">Verify:</td>
  <td><input type="password"
             name="in_pass2">
  </td>

<?php } else { ?>
  <td colspan="5"><?php print $thisUID;?></td>

<?php } ?>

</tr>
<tr>
 <td align="right">Given Name:</td>
 <td><input type="text"
            name="in_givenname"
            value="<?php print $info[0]["givenname"][0];?>"></td>
 <td align="right">Middle Name:</td>
 <td><input type="text"
            name="in_middlename"
            value="<?php print $info[0]["middlename"][0];?>"></td>
 <td align="right">Surname:</td>
 <td><input type="text"
            name="in_sn"
            value="<?php print $info[0]["sn"][0];?>"></td>
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
 <td align="right">Title:</td>
 <td colspan="5"><input type="text"
            size="30"
            name="in_title"
            value="<?php print $info[0]["title"][0];?>"></td>
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
 <td align="right">Street Address:</td>
 <td colspan="5"><input type="text"
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
<? } else {

// -------------------------------------------
// employee display

?>

<tr>
  <td align="right">Employee Number:</td>
  <td colspan="5"><?php print $thisEmp;?></td>
</tr>
<tr>
  <td align="right">Computer UID:</td>
  <td colspan="5"><?php print $thisUID;?></td>
</tr>
<tr>
 <td align="right">Common Name:</td>
 <td colspan="5"><?php print $info[0]["cn"][0];?></td>
</tr>

<?php } ?>

<tr>
 <td align="right">Comments:</td>
 <td colspan="5"><textarea cols="60" rows="2" wrap="physical"
            name="in_comments"><?php print $info[0]["comments"][0];?></textarea></td>
</tr>
<tr>
 <td align="right">Applications:</td>
 <td colspan="5">
<?php
$pwfs_cnt = 0;
$thisbr = '';
if ($mgr_group_cnt > 0) {
  foreach ($mgr_groups as $a_cn => $a_description) {
    $picked = '';
    if (strlen($thisApps[$a_cn])>0) { $picked = 'CHECKED'; }
    echo $thisbr;
    echo "    <input type=\"checkbox\"\n";
    echo "           name=\"in_pwfs_select_$pwfs_cnt\"\n";
    echo "           value=\"1\" $picked>$a_description ($a_cn)\n";
    echo "    <input type=\"hidden\"\n";
    echo "           name=\"in_pwfs_$pwfs_cnt\"\n";
    echo "           value=\"$a_cn\" $picked>\n";
    $pwfs_cnt++;
    $thisbr = "    <br>\n";
  }
} else {
  echo "&nbsp;";
}
echo "    <input type=\"hidden\"\n";
echo "           name=\"in_pwfs_cnt\"\n";
echo "           value=\"$pwfs_cnt\">\n";  
?>
  </td>
</tr>

 <? if (strlen($thisEmp) == 0) { ?>

<tr>
 <td align="right">Mail:</td>
 <td colspan="5"><input type="text" size="40"
            name="in_mail"
            value="<?php print $info[0]["mail"][0];?>"></td>
</tr>
<tr>
 <td align="right">Mail Distribution Lists: (sorted)</td>
 <td colspan="5">
<?php
  $br = '';
  $mailID_cnt = $info[0]["maildistributionid"]["count"];
  if ($mailID_cnt > 0) {
    $mls = array();
    for ($mailEntry=0; $mailEntry<$mailID_cnt; $mailEntry++) {  
      $mls[] = $info[0]["maildistributionid"][$mailEntry];
    }
    sort($mls);
    $cnt_ml = 0;
    foreach ($mls as $ml) {
      print $br;
?>
     <input type="checkbox" CHECKED
            name="inMailID_<?php print $cnt_ml;?>"
            value="<?php print $ml;?>"><?php print "$ml\n";?>
     <input type="hidden"
            name="inMailIDList_<?php print $cnt_ml;?>"
            value="<?php print $ml;?>">
<?php
      $cnt_ml++;
      $br = "      <br>\n";
    }
  }
  print $br;
?>
     <input type="hidden"
            name="inMailIDCnt"
            value="<?php print $cnt_ml;?>">

 <input type="text"
        name="inMailIDNew">
 </td>
</tr>
 <? } ?>

<tr>
 <td colspan="6">

 <table border="0" width="100%">
 <tr>

 <?php if ($entry_found>0) { ?>
 <td width="33%">
  <input type="submit" name="btn_update" value="Update">
 </td>
 <?php } else { ?>
   <td>&nbsp;</td>
 <?php } ?>

 <?php if ($add_delete_flag>0 && $entry_found>0 && strlen($thisEmp)==0) { ?>
 <td width="33%" align="center">
  <input type="submit" name="btn_delete" value="Delete">
 </td>
 <?php } else { ?>
   <td>&nbsp;</td>
 <?php } ?>

 <?php if ($add_delete_flag>0 && $entry_found==0) { ?>
 <td width="33%" align="right">
  <input type="submit" name="btn_add" value="Add">
 </td>
 <?php } else { ?>
   <td>&nbsp;</td>
 <?php } ?>

 </tr>
 </table>

 </td>
</tr>
</table>

<input type="hidden" name="in_xml_data" value="">

</form>

<?php
 ldap_close($ds);
 require ('inc_footer.php');
?>
