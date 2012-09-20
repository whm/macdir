<?php

// File: change_password_action.php
// Author: Bill MacAllister
// Date: December 2002

// -------------------------------
// update the passwords stored in the ldap directory

function ldap_set_password ($uid, $pwField, $newPw) {

  global $ldap_base;
  global $ds;
  global $ok;
  global $warn;

  $objClass = 'person';
  if ($pwField == 'ntpassword' || $pwField == 'lmpassword') {
    $objClass = 'sambaAccount';
  } else {
    $newPw = '{crypt}'.crypt($newPw);
  }

  // display errors ourselves, so we turn of auto-error reporting
  $old_ldap_error_level = error_reporting(E_ERROR | E_PARSE);

  // look for any user attributes for this object class
  $getAttrs = array();
  $ldapFilter = "(&(objectClass=$objClass)(uid=$uid))";
  $searchResult = ldap_search($ds, $ldap_base, $ldapFilter, $getAttrs);
  $ldapInfo = ldap_get_entries($ds, $searchResult);
  if ($ldapInfo["count"] == 0) {

    // ooops, can find them for some reason
    // Generally this is okay because they don't have complete access
    // to all systems and thus might not have the required objectclass.
    // Uncomment this mostly for debugging.
    // $_SESSION['s_msg'] .= "<font $warn>Entry not found for:$uid<br>";

  } else {

    $ldapDN = $ldapInfo[0]['dn'];
    $_SESSION['s_msg'] .= "<font $ok>Updating $uid</font><br>";
    for ($i=0; $i<$ldapInfo["count"]; $i++) {
      for ($j=0; $j<$ldapInfo[$i][$pwField]["count"]; $j++) {
        // delete the old value
        $oldpw = $ldapInfo[$i][$pwField][$j];
        $attrs = array();
        $attrs[$pwField] = $oldpw;
        if (!ldap_mod_del($ds, $ldapDN, $attrs)) {
          $_SESSION['s_msg'] .= "<font $warn>Problem deleting "
                             . "$pwField $ldapDN</font><br>";
          $ldapErr = ldap_errno ($ds);
          $ldapMsg = ldap_error ($ds);
          $_SESSION['s_msg'] .= "<font $warn>Error: $ldapErr, "
                              . "$ldapMsg</font><br>";
        }
      }
    }

    $attrs = array();
    $attrs[$pwField] = $newPw;
    // Add the new password to the directory
    if (!ldap_mod_add($ds, $ldapDN, $attrs)) {
      $_SESSION['s_msg'] .= "<font $warn>Problem adding $pwField "
                         . "for $ldapDN</font><br>";
      $ldapErr = ldap_errno ($ds);
      $ldapMsg = ldap_error ($ds);
      $_SESSION['s_msg'] .= "<font $warn>Error: $ldapErr, $ldapMsg</font><br>";
    } else {
      $_SESSION['s_msg'] .= "<font $ok>Password changed.</font><br>";
    }
  }
  $junk = error_reporting($old_ldap_error_level);
}


// ----------------------------------------------------
// Main Routine

$now = date ('Y-m-d H:i:s');
$in_date_last_maint = $now;

// initial directory connection and ldap base dn
require('/etc/whm/macdir_auth.php');

// No spaces allowed in the identifier
$in_uid = ereg_replace (" ","",$in_uid);

// set update message area
if (!session_is_registered('s_msg')) {
  session_register('s_msg');
}
$_SESSION['s_msg'] = '';
$ok = 'color="#009900"';
$warn = 'color="#990000"';

if ( strlen($btn_update)>0 ) {

  // bind anonymously and look up the dn to bind as
  $filter = "(&(objectclass=person)";
  $filter .= "(uid=$in_uid))";
  $return_attr = array('cn','dn');
  $ds = ldap_connect($ldap_server);
  if (!ldap_bind($ds,'','')) {
    $_SESSION['s_msg'] .= "<font $warn>Failure to bind anonymously to "
                             . "$ldap_server</font><br>";
  }
  $sr = ldap_search($ds, $ldap_base, $filter, $return_attr);  
  $info = ldap_get_entries($ds, $sr);
  if ($info["count"] == 0) {
    $_SESSION['s_msg'] .= "<font $warn>Authentication failure for $in_uid</font><br>";
  } else {
    $user_dn = $info[0]['dn'];

    // attempt to bind using the old password
    if ( !@ldap_bind($ds,$user_dn,$in_old_password) ) {
      $_SESSION['s_msg'] .= "<font $warn>"
                   . "Failure to bind $ldap_server as $in_uid</font><br>";
      $_SESSION['s_msg'] .= "<font $warn>Password not changed.</font><br>";
    } else {
      // now bind as the admin to make the changes
      if (!@ldap_bind($ds,$ldap_manager,$ldap_password)) {
        $_SESSION['s_msg'] .= "<font $warn>"
            . "Failure to bind $ldap_server as directory manager</font><br>";
        $_SESSION['s_msg'] .= "<font $warn>Password not changed.</font><br>";
      } else {
        ldap_set_password ($in_uid, 'userpassword', $in_new_password);
        # Essentially disable setting samba passwords, but leave the 
        # code here for a while.
        if ($config['smb_passwd'] == 'yes') {
            $samba_cmd = "/usr/local/sbin/mkntpwd $in_new_password";
            $nt_lm_passwords = shell_exec($samba_cmd);
            $lmPwd = strtok($nt_lm_passwords,":");
            $ntPwd = substr(strtok(":"),0,32);
            # -- no LM passwords 
                # ldap_set_password ($in_uid, 'lmpassword', $lmPwd);
            ldap_set_password ($in_uid, 'ntpassword', $ntPwd);
            ldap_set_password ($in_uid, 'sambantpassword', $ntPwd);
        }
      }
    }
  }
}

ldap_unbind($ds);

header ("REFRESH: 0; URL=change_password.php?in_uid=$in_uid");

?>