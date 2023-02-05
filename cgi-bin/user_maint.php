<?php
//
// ----------------------------------------------------------
//
// file: user_maint.php
// author: Bill MacAllister
// date: 18-Jan-2003
// description: This form is for updating phone information for people
//              listed in the LDAP Directory.

$title   = 'User Maintenance';
$heading = 'User Maintenance';

require('inc_init.php');
require('inc_header.php');

##############################################################################
# Subroutines
##############################################################################

function display_update_buttons (
    $entry_found = 0,
    $add_delete_flag=0
) {

?>
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
<?php

 return;
}

##############################################################################
# Initializatrion
##############################################################################

// Bind to the directory
$ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');

// get a list of pam and application groups
require('inc_groups.php');

// -----------------------------------------------------
// Set some variables
$this_mail_domain    = empty($mail_domain) ? '' : $mail_domain;
$this_mailbox_domain = empty($mailbox_domain) ? '' : $mailbox_domain;

// ---------------------------------------------------------
// Lookup an entry

$entry_found     = 0;
$add_delete_flag = 1;
$thisUID         = '';
$thisDN          = '';
$ldap_filter     = '';

if (empty($_REQUEST['in_uid']) || !empty($_REQUEST['in_button_reset'])) {
    $in_uid = '';
} else {
    $in_uid = $_REQUEST['in_uid'];
    $ldap_filter = "(&(objectclass=$oc_person)(uid=$in_uid))";
}
if (!empty($ldap_filter)) {

  $return_attr = array();
  $old_err = error_reporting(E_ERROR | E_PARSE);
  $sr = ldap_search ($ds, $ldap_base, $ldap_filter, $return_attr);
  $info = ldap_get_entries($ds, $sr);
  $tmp_err = error_reporting($old_err);
  $ret_cnt = $info["count"];
  if ($ret_cnt == 1) {
     $entry_found = 1;
     $in_uid = $info[0]["uid"][0];
  } elseif ($ret_cnt > 1) {
     $_SESSION['in_msg']
         .= "More than on entry found for $ldap_filter search.\n";
  } else {
     $_SESSION['in_msg'] .= "No entry found.\n";
  }

  // Now see if there is a uid and if they are in any posix or pam groups
  if ($entry_found) {

    $thisDN = $info[0]["dn"];
    $thisUID = $in_uid = $info[0]["uid"][0];

    // posix groups for this user
    $posixFilter = "(&(objectclass=posixGroup)(memberUid=$thisUID))";
    $posixReturn = array ('gidNumber','cn','description');
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_search (
        $ds,
        $CONF['ldap_group_base'],
        $posixFilter,
        $posixReturn
    );
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

    // application groups for this user
    $aFilter = '(&(objectclass='  . $CONF['oc_app']. ")(memberUid=$thisUID))";
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

<?php
##############################################################################
# Java Script Routines
##############################################################################
?>

<script language="JavaScript">

/* --------------- */
/* Set mail fields */
/* --------------- */

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
    f.in_mail.value = t + '@<?php echo $this_mail_domain;?>';
    t = f.in_uid.value.toLowerCase() + '@<?php echo $this_mailbox_domain;?>';
    f.in_new_maildelivery.value = t;
}

/* ----------------- */
/* Unset mail fields */
/* ----------------- */

function unset_mail () {
 var f;
 var i;
 f = document.user_maint;
 f.in_mail.value = '';
 f.in_new_maildelivery.value = '';
}

/* -------------------- */
/* Set posix attributes */
/* -------------------- */
function set_posix () {
 var f;
 var i;
 f = document.user_maint;
 this_home = '/home/' + f.in_uid.value;
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
        f.in_sn.value = f.in_uid.value;
    }
    if (f.in_cn_cnt == null) {
        if (f.in_new_cn.value == EmptyField) {
            f.in_new_cn.value = f.in_uid.value;
        }
    }

    if (f.in_uidnumber.value != EmptyField) {
        var text = f.in_uidnumber.value;
        var result = text.match(nondigit);
        if (result != null) {
            alert ("UID Number is not a number!");
            return false;
        }
        if (f.in_gidnumber.value == EmptyField) {
            alert ("Please enter an GID Number!");
            return false;
        }
        var text = f.in_gidnumber.value;
        var result = text.match(nondigit);
        if (result != null) {
            alert ("GID Number is not a number!");
            return false;
        }
    }
    if (f.in_homedirectory.value == EmptyField) {
        f.in_homedirectory.value = '/home/' + f.in_uid.value;
    }
    var is_not_set = true;
    for (i=0; i<f.in_loginshell.length; i++) {
        if (f.in_loginshell[i].checked) {
            is_not_set = false;
        }
    }
    if (!login_shell_set) {
        alert ("Please pick a login shell!");
        return false;
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

<?php
##############################################################################
# Main Routine
##############################################################################
?>

<div class="row">

<form name="user_maint_find"
      method="post"
      action="<?php print $_SERVER['PHP_SELF']; ?>">

    <label for="in_uid">UID:</label>
    <input type="text"
             name="in_uid"
             value="<?php print $in_uid;?>">
    <br/>

    <input type="submit" name="in_button_find"  value="Lookup">
    <input type="submit" name="in_button_reset" value="Reset">
</form>
<br/>

<?php
if (isset($msg)) {
  echo "<p>$msg</p>\n";
  $msg = '';
}
if ( !empty($_SESSION['in_msg']) ) {
    echo "<p>" . $_SESSION['in_msg'] . "</p>\n";
    echo "</tr>\n";
    $_SESSION['in_msg'] = '';
}
?>
</form>

<p>
<form name="user_maint"
      method="post"
      action="user_maint_action.php"
      onsubmit="return checkIt()">

<table border="1" cellpadding="2">

<tr>
 <td colspan="6">
<?php display_update_buttons($entry_found, $add_delete_flag); ?>
 </td>
</tr>

<?php
  if ($entry_found) {
    $pwd_href = 'set_password.php?in_uid='.$thisUID;
?>
<tr>
  <input type="hidden" name="in_uid"
         value="<?php print $thisUID;?>">
  <input type="hidden" name="in_dn"
         value="<?php print $thisDN;?>">
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
            name="in_cn_<?php echo $i;?>"
            value="<?php print $thisCN;?>"><?php print "$thisCN\n";?>
     <input type="hidden"
            name="in_cn_list_<?php echo $i;?>"
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

<?php if ($CONF['maint_nickname']) { ?>
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
<?php } ?>

<?php if ($CONF['maint_title']) { ?>
<tr>
 <td align="right">Title:</td>
   <?php
     $z = '';
     if (isset($info[0]["title"][0])) {$z = $info[0]["title"][0];}
   ?>
 <td colspan="5">
   <input type="text" size="30" name="in_title" value="<?php print $z;?>"></td>
</tr>
<?php } ?>

<?php if ($CONF['maint_phone']) { ?>
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
<?php } ?>

<?php if ($CONF['maint_workphone']) { ?>
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
<?php } ?>

<?php if ($CONF['maint_cell']) { ?>
<tr>
 <td align="right">Cell Telephone Number:</td>
   <?php
     $z = '';
     if (isset($info[0]["mobile"][0])) {$z = $info[0]["mobile"][0];}
   ?>
 <td colspan="5">
   <input type="text" name="in_mobile" value="<?php print $z;?>"></td>
</tr>
<?php } ?>

<?php if ($CONF['maint_fax']) { ?>
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
<?php } ?>

<?php if ($CONF['maint_pager']) { ?>
<tr>
 <td align="right">Pager Telephone Number:</td>
   <?php
     $z = '';
     if (isset($info[0]["pager"][0])) {$z = $info[0]["pager"][0];}
   ?>
 <td colspan="5">
   <input type="text" name="in_pager" value="<?php print $z;?>"></td>
</tr>
<?php } ?>

<?php if ($CONF['maint_address']) { ?>
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
<?php } ?>

<?php if ($CONF['maint_comments']) { ?>
<tr>
 <td align="right">Comments:</td>
   <?php
     $z = '';
     $attr = $CONF['attr_comment'];
     if (isset($info[0][$attr][0])) {$z = $info[0][$attr][0];}
   ?>
 <td colspan="5"><textarea cols="60" rows="2" wrap="physical"
     name="in_<?php print $attr; ?>"><?php print $z;?></textarea></td>
</tr>
<?php } ?>

<?php if ($CONF['maint_app_groups']) { ?>
<tr>
 <td align="right">Applications:</td>
 <td colspan="5">
   Add:<br>
   <select name="inAppAddList[]" multiple="multiple" size="5">
<?php
  if ( !empty($app_groups) ) {
      foreach ($app_groups as $a_cn => $a_description) {
          if ( !empty($thisApps[$a_cn])) {
              continue;
          }
          echo "    <option value=\"$a_cn\">$a_description\n";
      }
  }
?>
   </select><br>
   Remove:<br>
   <select name="inAppDelList[]" multiple="multiple" size="4">
<?php
  if ( !empty($thisApps) ) {
      foreach ($thisApps as $appName => $appDescription) {
          echo "    <option value=\"$appName\">$appDescription ($appName)\n";
      }
  }
?>
   </select>
  </td>
</tr>
<?php } ?>

<?php if ($CONF['maint_mail_acct']) { ?>
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
<?php } ?>

<?php if ($CONF['maint_mail_addr']) { ?>
<tr>
 <td align="right">Mail:</td>
   <?php
     $z = '';
     if ( !empty($info[0]["mail"][0]) ) {
         $z = $info[0]["mail"][0];
     }
   ?>
 <td colspan="5"><input type="text" size="40"
            name="in_mail" value="<?php print $z;?>"></td>
</tr>
<tr>
 <td align="right">Mail Aliases:</td>
 <td colspan="5">
  <?php
  $attr = $CONF['attr_mailalias'];
  $ma_cnt = empty($info[0][$attr]["count"])
          ? 0 : $info[0][$attr]["count"];
  echo '    <input type="hidden" ';
  echo            'name="in_mailalias_cnt" ';
  echo            'value="' . $ma_cnt . '"' . ">\n";
  if ($ma_cnt>0) {
      for ($i=0; $i<$ma_cnt; $i++) {
          $ma[] = $info[0][$attr][$i];
      }
      sort($ma);
      $i = 0;
      foreach ($ma as $thisMA) {
  ?>
     <input type="checkbox" CHECKED
            name="in_mailalias_<?php echo $i;?>"
            value="<?php print $thisMA;?>"><?php print "$thisMA\n";?>
     <input type="hidden"
            name="in_mailalias_list_<?php echo $i;?>"
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
      $attr = $CONF['attr_maildelivery'];
      $maildelivery_cnt = empty($info[0][$attr]["count"])
          ? 0 : $info[0][$attr]["count"];
      echo "    <input type=\"hidden\" "
          . "name=\"in_maildelivery_cnt\" "
          . "value=\"$maildelivery_cnt\">\n";
      for ($i=0; $i<$maildelivery_cnt; $i++) {
          $this_maildelivery = $info[0][$attr][$i];
  ?>
     <input type="checkbox" CHECKED
            name="in_maildelivery_<?php echo $i;?>"
            value="<?php print $this_maildelivery;?>">
        <?php print "$this_maildelivery\n";?>
     <input type="hidden"
            name="in_maildelivery_list_<?php echo $i;?>"
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
<?php } ?>

<?php if ($CONF['maint_linux']) { ?>
<tr>
  <td colspan="6" align="center">
   <font color="#0000FF"><b>Linux Access</b></font>
  </td>
</tr>

<?php if ( empty($info[0]["uidnumber"][0]) ) {?>
<tr>
 <td align="right">Add Linux Access:</td>
 <td colspan="5">
   <input name="in_linux_add"
          type="checkbox"
          value="Y"
          onclick="set_posix();">
 </td>
</tr>
<tr>
 <td align="right">UID Number:</td>
 <td><input type="text" size="10"
            name="in_uidnumber"
            value=""></td>
 <td align="right">GID Number:</td>
 <td><input type="text" size="10"
            name="in_gidnumber"
            value=""></td>
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
 <td align="right">Login Shell:</td>
 <td colspan="5">
 <?php
$login_shells = array('bash', 'csh', 'ksh', 'sh', 'tcsh');
$ldap_shell = empty($info[0]["loginshell"][0])
    ? '/bin/bash' : $info[0]["loginshell"][0];
foreach ($login_shells as $sh) {
    $sh_file = "/bin/$sh";
    if ($sh_file == $ldap_shell) {
        $chk = 'CHECKED';
    } else {
        $chk = '';
    }
    print '  <input type="radio" ' . $chk . "\n";
    print '   name="in_loginshell" value="' . $sh_file . '">' . $sh . "<br>\n";
}
?>
 </td>
</tr>
<tr>
 <td align="right">Groups:</td>
 <td colspan="5">
     <input type="hidden"
            name="in_posixgroup_cnt" ';
            value="<?php print $thisPosix_cnt; ?>"
     >
<?php

$i = 0;
foreach ($thisPosix as $group => $description) {
?>
     <input type="checkbox" CHECKED
            name="in_posixgroup_<?php print $i; ?>"
            value="<?php print $group; ?>"
     > <?php print $description;?>
     <input type="hidden"
            name="in_posixgroup_list_<?php print $i;?> "
            value="<?php print $group;?>"
     >
     <br>
<?php
  // end of for loop
  $i++;
}
?>
    <input type="text"
           name="in_posix_new">
 </td>
</tr>

<tr>
 <td align="right">Privilege Groups:</td>
 <td colspan="5">
<?php
  $attr_pg = $CONF["attr_priv_group"];
  $pg_cnt = in_array($info[0], $attr_pg)
          ? 0 : $info[0][$attr_pg]["count"];
?>
    <input type="hidden"
          name="in_priv_group_cnt"
          value="<?php print $pg_cnt;?>"
    >
<?php
  $pg = array();
  if ($pg_cnt>0) {
      for ($i=0; $i<$pg_cnt; $i++) {
          $pg[] = $info[0][$attr_pg][$i];
      }
      sort($pg);
      $i = 0;
      foreach ($pg as $thisPG) {
?>
     <input type="checkbox" CHECKED
            name="in_priv_group_<?php echo $i;?>"
            value="<?php print $thisPG;?>"
     > <?php print "$thisPG\n";?>
     <input type="hidden"
            name="in_priv_group_list_<?php echo $i;?>"
            value="<?php print $thisPG;?>"
     >
     <br>
<?php
      // end of for loop
          $i++;
      }
    }
?>
     <input type="text"
            size="40"
            name="in_new_priv_group"></td>
</tr>
<?php } ?>

<tr>
 <td colspan="6">
<?php display_update_buttons($entry_found, $add_delete_flag); ?>
 </td>
</tr>

</table>

<input type="hidden" name="in_xml_data" value="">

</form>

<?php
 ldap_close($ds);
?>

</div>

<?php require('inc_footer.php');?>
