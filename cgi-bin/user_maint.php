<?php
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_button_find = $_REQUEST['in_button_find'];
$in_uid         = $_REQUEST['in_uid'];
// ----------------------------------------------------------
//
// file: user_maint.php
// author: Bill MacAllister
// date: 18-Jan-2003
// description: This form is for updating phone information for people
//              listed in the LDAP Directory.

// Open a session
session_start();

require('inc_config.php');

$title = 'User Maintenance';
$heading = 'User Maintenance';

require('inc_header.php');
require('/etc/whm/macdir_auth.php');
require('inc_bind.php');
$ds = macdir_bind($ldap_server, 'GSSAPI');

// -----------------------------------------------------
// get a list of pam and application groups

require('inc_groups.php');

// ---------------------------------------------------------
// Lookup an entry

$entry_found = 0;
$add_delete_flag = 1;
$thisUID = $thisDN = $ldap_filter = '';

if (!isset($in_uid)) {$in_uid = '';}
if (strlen($in_uid)>0) {
  $ldap_filter = "uid=$in_uid";
}
if (isset($ldap_filter)) {

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
     $msg .= "More than on entry found for $ldap_filter search.\n";
  } else {
     $msg .= "No entry found.\n";
  }

  // Now see if there is a uid and if they are in any posix or pam groups
  if ($entry_found) {

    $thisDN = $info[0]["dn"];
    $thisUID = $in_uid = $info[0]["uid"][0];

    // posix groups for this user
    $posixFilter = "(&(objectclass=posixGroup)(memberUid=$thisUID))";
    $posixReturn = array ('gidNumber','cn','description');
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_search ($ds, $ldap_groupbase, $posixFilter, $posixReturn);
    $posixEntries = ldap_get_entries($ds, $sr);
    $tmp_err = error_reporting($old_err);
    $thisPosix_cnt = $posixEntries["count"];
    $thisPosix = array();
    for ($gi=0; $gi<$thisPosix_cnt; $gi++) {
      $z = '';
      if (isset($posixEntries[$gi]['description'][0])) {
        $z = $posixEntries[$gi]['description'][0];
      }
      $groupName = $posixEntries[$gi]['cn'][0];
      $groupDesc = $z;
      $thisPosix[$groupName] = "$groupName";
      if (strlen($groupDesc)>0) {
        $thisPosix[$groupName] .= " - $groupDesc";
      }
    }
    asort ($thisPosix);

    // pam groups for this user
    $pamFilter = "(&(objectclass=pamGroup)(memberDN=$thisDN))";
    $pamReturn = array ('cn','description');
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_search ($ds, $ldap_groupbase, $pamFilter, $pamReturn);
    $thisPamEntries = ldap_get_entries($ds, $sr);
    $tmp_err = error_reporting($old_err);
    $thisPam_cnt = $thisPamEntries["count"];
    $thisPam = array();
    for ($gi=0; $gi<$thisPam_cnt; $gi++) {
      $z = '';
      if (isset($thisPamEntries[$gi]['description'][0])) {
        $z = $thisPamEntries[$gi]['description'][0];
      }
      $a_cn = $thisPamEntries[$gi]['cn'][0];
      $a_description  = $z;
      $thisPam[$a_cn] = $a_description;
    }
    asort($thisPam);

    // application groups for this user
    $aFilter = "(&(objectclass=prideApplication)(memberUid=$thisUID))";
    $aRetAttrs = array ('cn','description');
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_search ($ds, $ldap_base, $aFilter, $aRetAttrs);
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

?>

<table border="0" width="100%">
<tr><td align="center" width="90%">

<form name="user_maint_find"
      method="post"
      action="<?php print $_SERVER['PHP_SELF']; ?>">
<table border="0" width="100%">
<tr>
  <td align="right" width="50%">UID:</td>
  <td width="50%"><input type="text"
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
if (isset($msg)) {
  echo "<tr>\n";
  echo "  <td colspan=\"2\" align=\"center\">$msg</td>\n";
  echo "</tr>\n";
  $msg = '';
}
if (isset($_SESSION['in_msg'])) {
    echo "<tr>\n";
    echo "  <td colspan=\"2\" align=\"center\">".$_SESSION['in_msg']."</td>\n";
    echo "</tr>\n";
    $_SESSION['in_msg'] = '';
}
?>
</table>
</form>

</td>
<td align="center" width="10%">

<script language="JavaScript">

// ------------------------------------
// Set mail fields
function set_mail () {
 var f;
 f = document.user_maint;
 var this_cn = f.in_new_cn.value;
 if (f.in_cn_cnt.value > 0) {
   this_cn = f.in_first_cn.value;
 } else if (f.in_new_cn.value == '') {
   this_cn = f.in_uid.value;
 }
 var t = this_cn;
 t = t.replace (/\s+/g,".");
 t = t.replace (/\.\./g,"");
 t = t.replace (/^\./g,"");
 t = t.replace (/\.$/g,"");
 f.in_mail.value = t + '@<?php echo $mail_domain;?>';
 t = f.in_uid.value.toLowerCase() + '@<?php echo $mailbox_domain;?>';
 f.in_new_maildelivery.value = t;
}

// ------------------------------------
// Unset mail fields
function unset_mail () {
 var f;
 var i;
 f = document.user_maint;
 f.in_mail.value = '';
 f.in_new_maildelivery.value = '';
}

/* ----------------- */
/* Verify input data */
/* ----------------- */

function checkIt() {

  var f;
  var i;
  var outData = "";

  EmptyField = "";
  f = document.user_maint;

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

  var login_shell_set = false;
  if (f.in_loginshell.length == 1) {
      if (f.in_loginshell.checked) {login_shell_set = true;}
  } else if (f.in_loginshell.length > 1) {
    for (i=0; i<f.in_loginshell.length; i++) {
      if (f.in_loginshell[i].checked) {login_shell_set = true;}
    }
  }

  if (f.in_uidnumber.value != EmptyField) {
    if (f.in_gidnumber.value == EmptyField) {
      alert ("Please enter an GID Number!");
      return false;
    }
    var text = f.in_uidnumber.value;
    var result = text.match(nondigit);
    if (result != null) {
      alert ("UID Number is not a number!");
      return false;
    }
    var text = f.in_gidnumber.value;
    var result = text.match(nondigit);
    if (result != null) {
      alert ("GID Number is not a number!");
      return false;
    }
    if (f.in_homedirectory.value == EmptyField) {
      alert ("Please pick a home directory!");
      return false;
    }
    var is_not_set = true;
    for (i=0; i<f.in_loginshell.length; i++) {
      if (f.in_loginshell[i].checked) {is_not_set = false;}
    }
    if (!login_shell_set) {
      alert ("Please pick a login shell!");
      return false;
    }
  }

  /* pam group checks */
  var pam_cnt = 0;
  if (f.in_pam.length > 1) {
    for (i=0; i<f.in_pam.length; i++) {
      outData += "<pamgroup>";
      outData += "<text>"+f.in_pam[i].value+"</text>";
      if (f.in_pam[i].checked) {
          outData += "<checked>Y</checked>";
          pam_cnt++;
      } else {
          outData += "<checked>N</checked>";
      }
      outData += "</pamgroup>";
    }
  } else if (f.in_pam.value != EmptyField) {
    outData += "<pamgroup>";
    outData += "<text>"+f.in_pam.value+"</text>";
    if (f.in_pam.checked) {
      outData += "<checked>Y</checked>";
      pam_cnt++;
    } else {
      outData += "<checked>N</checked>";
    }
    outData += "</pamgroup>";
  }

  /* posix group checks */
  var posix_cnt = 0;
  if (f.in_posix.length > 1) {
    for (i=0; i<f.in_posix.length; i++) {
      outData += "<posixgroup>";
      outData += "<text>"+f.in_posix[i].value+"</text>";
      if (f.in_posix[i].checked) {
          outData += "<checked>Y</checked>";
          posix_cnt++;
      } else {
          outData += "<checked>N</checked>";
      }
      outData += "</posixgroup>";
    }
  } else if (f.in_posix.value != EmptyField) {
    outData += "<posixgroup>";
    outData += "<text>"+f.in_posix.value+"</text>";
    if (f.in_posix.checked) {
      outData += "<checked>Y</checked>";
      posix_cnt++;
    } else {
      outData += "<checked>N</checked>";
    }
    outData += "</posixgroup>";
  }

  if (outData.length>0) {f.in_xml_data.value = "<xml>"+outData+"</xml>";}
  return true;

}

</script>

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
<form name="user_maint"
      method="post"
      action="user_maint_action.php"
      onsubmit="return checkIt()">

<input type="hidden" name="in_uid"
       value="<?php print $thisUID;?>">
<input type="hidden" name="in_dn"
       value="<?php print $thisDN;?>">
<table border="1" cellpadding="2">
<?php
  if ($entry_found) {
    $pwd_href = 'set_password.php?in_uid='.$thisUID;
?>
<tr>
  <td align="right">Computer UID:</td>
  <td colspan="5">
    <table border="0" width="100%">
      <tr><td>
            <?php
            $z = '';
            $krb_attr = 'krb5principalname';
            if ( isset($info[0][$krb_attr][0]) ) {
              $z = ' ('.$info[0][$krb_attr][0].')';
            }
            print $thisUID.$z;
            ?>
          </td>
          <td align="right"><a href="<?php echo $pwd_href;?>">
              Set Password</a>
          </td>
    </tr>
    </table>
  </td>
</tr>
<?php
  } else {
?>
<tr>
  <td align="right">Computer UID:</td>
  <td colspan="5"><input type="text" name="in_uid"></td>
</tr>
<?php
  }
?>
<tr>
 <td align="right">Given Name:</td>
   <?php
     $z = '';
     if (isset($info[0]["givenname"][0])) {$z = $info[0]["givenname"][0];}
   ?>
 <td><input type="text" name="in_givenname" value="<?php print $z;?>"></td>
 <td align="right">Surname:</td>
   <?php
     $z = '';
     if (isset($info[0]["sn"][0])) {$z = $info[0]["sn"][0];}
   ?>
 <td><input type="text" name="in_sn" value="<?php print $z;?>"></td>
 <td colspan="2">&nbsp;</td>
</tr>

<tr>
 <td align="right">Nickname:</td>
   <?php
     $z = '';
     if (isset($info[0]["nickname"][0])) {$z = $info[0]["nickname"][0];}
   ?>
 <td colspan="5">
   <input type="text" size="30" name="in_nickname" value="<?php print $z;?>">
 </td>
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
      if ($i == 0) { ?>
      <input type="hidden"
             name="in_first_cn"
             value="<?php print $thisCN;?>">
  <?php
      // end of if
      }
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
   <?php
     $z = '';
     if (isset($info[0]["title"][0])) {$z = $info[0]["title"][0];}
   ?>
 <td colspan="5">
   <input type="text" size="30" name="in_title" value="<?php print $z;?>"></td>
</tr>
<tr>
 <td align="right">Telephone Number:</td>
   <?php
     $z = '';
     if (isset($info[0]["telephonenumber"][0])) {
       $z = $info[0]["telephonenumber"][0];
     }
   ?>
 <td colspan="5">
   <input type="text" name="in_telephonenumber" value="<?php print $z;?>"></td>
</tr>
<tr>
 <td align="right">Work Telephone Number:</td>
   <?php
     $z = '';
     if (isset($info[0]["workphone"][0])) {
       $z = $info[0]["workphone"][0];
     }
   ?>
 <td colspan="5">
   <input type="text" name="in_workphone" value="<?php print $z;?>"></td>
</tr>
<tr>
 <td align="right">Cell Telephone Number:</td>
   <?php
     $z = '';
     if (isset($info[0]["mobile"][0])) {$z = $info[0]["mobile"][0];}
   ?>
 <td colspan="5">
   <input type="text" name="in_mobile" value="<?php print $z;?>"></td>
</tr>
<tr>
 <td align="right">FAX Telephone Number:</td>
   <?php
     $z = '';
     if (isset($info[0]["facsimiletelephonenumber"][0])) {
       $z = $info[0]["facsimiletelephonenumber"][0];
     }
   ?>
 <td colspan="5"><input type="text"
            name="in_facsimiletelephonenumber"
            value="<?php print $z;?>"></td>
</tr>
<tr>
 <td align="right">Pager Telephone Number:</td>
   <?php
     $z = '';
     if (isset($info[0]["pager"][0])) {$z = $info[0]["pager"][0];}
   ?>
 <td colspan="5">
   <input type="text" name="in_pager" value="<?php print $z;?>"></td>
</tr>
<tr>
<tr>
 <td align="right">Street Address:</td>
   <?php
     $z = '';
     if (isset($info[0]["postaladdress"][0])) {
       $z = $info[0]["postaladdress"][0];
     }
   ?>
 <td colspan="5">
   <input type="text" name="in_postaladdress" value="<?php print $z;?>"></td>
</tr>
<tr>
 <td align="right">City:</td>
   <?php
     $z = '';
     if (isset($info[0]["l"][0])) {$z = $info[0]["l"][0];}
   ?>
 <td><input type="text" name="in_l" value="<?php print $z;?>"></td>
 <td align="right">State:</td>
   <?php
     $z = '';
     if (isset($info[0]["st"][0])) {$z = $info[0]["st"][0];}
   ?>
 <td><input type="text" size="4" name="in_st" value="<?php print $z;?>"></td>
 <td align="right">ZIP Code:</td>
   <?php
     $z = '';
     if (isset($info[0]["postalcode"][0])) {$z = $info[0]["postalcode"][0];}
   ?>
 <td><input type="text" size="10" name="in_postalcode"
            value="<?php print $z;?>"></td>
</tr>
<tr>
 <td align="right">Comments:</td>
   <?php
     $z = '';
     if (isset($info[0]["comments"][0])) {$z = $info[0]["comments"][0];}
   ?>
 <td colspan="5"><textarea cols="60" rows="2" wrap="physical"
            name="in_comments"><?php print $z;?></textarea></td>
</tr>
<tr>
 <td align="right">Applications:</td>
 <td colspan="5">
   Add:<br>
   <select name="inAppAddList[]" multiple="multiple" size="5">
<?php
  foreach ($app_groups as $a_cn => $a_description) {
    if (isset($thisApps[$a_cn])) { continue; }
    echo "    <option value=\"$a_cn\">$a_description\n";
  }
?>
   </select><br>
   Remove:<br>
   <select name="inAppDelList[]" multiple="multiple" size="4">
<?php
  foreach ($thisApps as $appName => $appDescription) {
    echo "    <option value=\"$appName\">$appDescription ($appName)\n";
  }
?>
   </select>
  </td>
</tr>
<tr>
 <td align="right">Mail Account:</td>
 <td><input type="radio"
            onClick="set_mail()"
            name="in_mail_button"
            value="Y">Yes &nbsp;&nbsp;&nbsp;
     <input type="radio"
            name="in_mail_button"
            onClick="unset_mail()"
            value="N">No
 </td>
</tr>
<tr>
 <td align="right">Mail:</td>
   <?php
     $z = '';
     if (isset($info[0]["mail"][0])) {$z = $info[0]["mail"][0];}
   ?>
 <td colspan="5"><input type="text" size="40"
            name="in_mail" value="<?php print $z;?>"></td>
</tr>
<tr>
 <td align="right">Mail Aliases:</td>
 <td colspan="5">
  <?php
    $ma_cnt = $info[0]["mailalias"]["count"];
    echo "    <input type=\"hidden\" name=\"in_mailalias_cnt\" value=\"$ma_cnt\">\n";
    if ($ma_cnt>0) {
      for ($i=0; $i<$ma_cnt; $i++) {
        $ma[] = $info[0]["mailalias"][$i];
      }
      sort($ma);
      $i = 0;
      foreach ($ma as $thisMA) {
  ?>
     <input type="checkbox" CHECKED
            name="in_mailalias_<?echo $i;?>"
            value="<?php print $thisMA;?>"><?php print "$thisMA\n";?>
     <input type="hidden"
            name="in_mailalias_list_<?echo $i;?>"
            value="<?php print $thisMA;?>">
     <br>
  <?php
      // end of for loop
          $i++;
      }
    }
?>
     <input type="text"
            size="40"
            name="in_new_mailalias"></td>
</tr>
        
<tr>
 <td align="right">Mail Delivery:</td>
 <td colspan="5">
  <?php
  if ($entry_found>0) {
    $maildelivery_cnt = $info[0]["maildelivery"]["count"];
    echo "    <input type=\"hidden\" "
         . "name=\"in_maildelivery_cnt\" "
         . "value=\"$maildelivery_cnt\">\n";
    for ($i=0; $i<$maildelivery_cnt; $i++) {
      $this_maildelivery = $info[0]["maildelivery"][$i];
  ?>
     <input type="checkbox" CHECKED
            name="in_maildelivery[<?echo $i;?>]"
            value="<?php print $this_maildelivery;?>">
        <?php print "$this_maildelivery\n";?>
     <input type="hidden"
            name="in_maildelivery_list[<?echo $i;?>]"
            value="<?php print $this_maildelivery;?>">
     <br>
  <?php
    // end of for loop
    }
  // end of entry_found test
  }
?>
     <input type="text"
            size="40"
            name="in_new_maildelivery"></td>
</tr>

<tr bgcolor="#660000">
  <td colspan="6" align="center">
   <font color="#FFFFFF"><b>Linux Access</b></font>
  </td>
</tr>

<?php if (strlen($info[0]["uidnumber"][0]) == 0) {?>
<tr>
 <td align="right">Add Linux Access:</td>
 <td colspan="5">
   <input name="in_linux_add"
          type="checkbox"
          value="Y"
          onclick="setPosix();">
 </td>
</tr>
<tr>
 <td align="right">UID Number:</td>
 <td><input type="text" size="10"
            name="in_uidnumber"
            value="<?php print $info[0]["uidnumber"][0];?>"></td>
 <td align="right">GID Number:</td>
 <td><input type="text" size="10"
            name="in_gidnumber"
            value="<?php print $info[0]["gidnumber"][0];?>"></td>
 <td colspan="2">Leave blank to auto-generate</td>
</tr>
<?php } else { ?>
<tr>
 <td align="right">UID Number:</td>
 <td><?php print $info[0]["uidnumber"][0];?>
     <input type="hidden"
            name="in_uidnumber"
            value="<?php print $info[0]["uidnumber"][0];?>">
 </td>
 <td align="right">GID Number:</td>
 <td><?php print $info[0]["gidnumber"][0];?>
     <input type="hidden"
            name="in_gidnumber"
            value="<?php print $info[0]["gidnumber"][0];?>">
 </td>
 <td colspan="2">&nbsp;</td>
</tr>
<?php } ?>

<tr>
 <td align="right">homedirectory:</td>
   <?php
     $z = '';
     if (isset($info[0]["homedirectory"][0])) {
       $z = $info[0]["homedirectory"][0];
     }
   ?>
 <td colspan="5"><input type="text"
            name="in_homedirectory"
            value="<?php print $z;?>"></td>
</tr>
<tr>
 <?php $this_shell = '';
       $ls_bin_sh=$ls_bin_bash=$ls_bin_csh=$ls_bin_tcsh=$ls_bin_ksh='';
       if (isset($info[0]["loginshell"][0])) {
         $this_shell = $info[0]["loginshell"][0];
       }
       $name = 'ls'.$this_shell;
       $name = str_replace ('/','_',$name);
       $$name = 'CHECKED';
 ?>
 <td align="right">Login Shell:</td>
 <td colspan="5">
     <input type="radio" <?php echo $ls_bin_sh;?>
            name="in_loginshell" value="/bin/sh">Bourne Shell<br>
     <input type="radio" <?php echo $ls_bin_bash;?>
            name="in_loginshell" value="/bin/bash">Bash Shell<br>
     <input type="radio" <?php echo $ls_bin_csh;?>
            name="in_loginshell" value="/bin/csh">c Shell<br>
     <input type="radio" <?php echo $ls_bin_tcsh;?>
            name="in_loginshell" value="/bin/tcsh">tcsh Shell<br>
     <input type="radio" <?php echo $ls_bin_ksh;?>
            name="in_loginshell" value="/bin/ksh">Korn Shell
</tr>
<tr>
 <td align="right">Computer Access:</td>
 <td colspan="5">
<?php
  $br = '';
  if ($pam_group_cnt>0) {
    foreach ($pam_groups as $a_cn => $a_description) {
      print $br;
      $chk = '';
      if (strlen($thisPam["$a_cn"])>0) {
          $chk = 'CHECKED';
      }
?>
     <input type="checkbox" <?php print $chk;?>
            name="in_pam"
            value="<?php print $a_cn;?>"><?php print "$a_description\n";?>
<?php
      $br = "      <br>\n";
    }
  }
?>
 </td>
</tr>
<tr>
 <td align="right">Computer Groups:</td>
 <td colspan="5">
<?php

$br = '';
$posix_display = array();
$posix_checked = array();
if (count($fs_groups)>0) {
  foreach ($fs_groups as $group => $description) {
    $posix_display[$group] = $description;
    $posix_checked[$group] = '';
  }
}
if (count($thisPosix)>0) {
  foreach ($thisPosix as $group => $description) {
    $posix_display[$group] = $description;
    $posix_checked[$group] = 'CHECKED';
  }
}
if (count($posix_display)>0) {
  foreach ($posix_display as $group => $group_description) {
    print $br;
?>
     <input type="checkbox" <?php echo $posix_checked[$group]; ?>
            name="in_posix"
            value="<?php print $group;?>"><?php print $group_description;?>
<?php
    $br = "      <br>\n";
  }
}
print $br;

?>
 <input type="text"
        name="in_posix_new">
 </td>
</tr>
<?php
# ---------------------------------------------------------------------
# Samba LDAP maintenance
# ---------------------------------------------------------------------
if ($CONF_use_samba) {
?>

<tr bgcolor="#660000">
  <td colspan="6" align="center">
   <font color="#FFFFFF"><b>Samba Access</b></font>
  </td>
</tr>
<?php if (strlen($info[0]["sambasid"][0]) > 0) {?>
<tr>
 <td align="right">Delete Samba Access:</td>
 <td>
   <input name="in_samba_delete"
          type="checkbox"
          value="Y">
 </td>
 <td colspan="4">
  Samba SID:<?php print $info[0]["sambasid"][0];?>
 </td>
</tr>
<?php } else { ?>
<tr>
 <td align="right">Add Samba Access:</td>
 <td colspan="5">
   <input name="in_samba_add"
          type="checkbox"
          value="Y">
 </td>
</tr>
<?php } ?>
<tr>
 <td align="right">Samba Allow Password Change:</td>
 <td colspan="5">
 <input type="text"
        name="in_sambapwdcanchange"
        value="<?php print $info[0]["sambapwdcanchange"][0];?>">
 </td>
</tr>
<tr>
 <td align="right">Samba Password Must Change:</td>
 <td colspan="5">
 <input type="text"
        name="in_sambapwdmustchange"
        value="<?php print $info[0]["sambapwdmustchange"][0];?>">
 </td>
</tr>
<tr>
 <td align="right">Samba Home Path:</td>
 <td colspan="5">
 <input type="text"
        name="in_sambahomepath"
        value="<?php print $info[0]["sambahomepath"][0];?>">
  A UNC name, e.g. \\%N\
 </td>
</tr>
<tr>
 <td align="right">Samba Home Drive:</td>
 <td colspan="5">
 <input type="text"
        name="in_sambahomedrive"
        value="<?php print $info[0]["sambahomedrive"][0];?>">
  A simple drive letter, e.g. H:
 </td>
</tr>
<tr>
 <td align="right">Samba Logon Script:</td>
 <td colspan="5">
 <input type="text"
        name="in_sambalogonscript"
        value="<?php print $info[0]["sambalogonscript"][0];?>">
  A simple file name, e.g. logon.bat
 </td>
</tr>
<tr>
 <td align="right">Samba Profile Path:</td>
 <td colspan="5">
 <input type="text"
        name="in_sambaprofilepath"
        value="<?php print $info[0]["sambaprofilepath"][0];?>">
  A UNC path for the profile directory, e.g. \\%N\profilename
 </td>
</tr>
<tr>
 <td align="right">Samba Primary Group SID:</td>
 <td colspan="5">
 <input type="text"
        name="in_sambaprimarygroupsid"
        value="<?php print $info[0]["sambaprimarygroupsid"][0];?>">
 </td>
</tr>
<tr>
 <td align="right">Samba Domain Name:</td>
 <td colspan="5">
 <input type="text"
        name="in_sambadomainname"
        value="<?php print $info[0]["sambadomainname"][0];?>">
  A simple string for the Samba Domain, e.g. WORKGROUP
 </td>
</tr>

<tr>
 <td align="right">Account Flags:</td>
 <td colspan="5">
<?php
foreach ($samba_acct_flags['desc'] as $f_id => $f_desc) {
    $chked = '';
    if ( preg_match("/$f_id/", $info[0]["sambaacctflags"][0]) ) {
        $chked = ' CHECKED';
    }
    if (strlen($chked)>0 || $samba_acct_flags['type'][$f_id]=='USER') {
        echo " <input type=\"checkbox\" ";
        echo "name=\"in_acct_flag_$f_id\" ";
        echo "value=\"$f_id\"$chked>";
        echo "$f_desc($f_id)<br> \n";
    }
}

?>
 </td>
</tr>

<?php
# End Samba
# ------------------------------------------------------------------
} ?>
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

<input type="hidden" name="in_xml_data" value="">

</form>

<?php
 ldap_close($ds);
 require ('inc_footer.php');
?>
