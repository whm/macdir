<?php 
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_cn_cnt  = $_REQUEST['in_cn_cnt'];
$in_new_cn  = $_REQUEST['in_new_cn'];
$in_dn  = $_REQUEST['in_dn'];
$in_uid  = $_REQUEST['in_uid'];
// ----------------------------------------------------------
//
// file: phone_maintenance_action.php
// author: Bill MacAllister
// date:  2-Jan-2004

session_register("in_msg");
$_SESSION['in_msg'] = '';

require ('/etc/whm/macdir_auth.php');

// --------------------------------------------------------------
// Update common names as required

function common_name_check ($a_dn, $a_flag, $a_cn) {

  global $ds, $ok, $warn, $ef;

  $cn_attr['cn'] = $a_cn;

  // search for it
  $aFilter = "(&(objectclass=person)(cn=$a_cn))";
  $aReturn = array ('cn');
  $old_err = error_reporting(E_ERROR | E_PARSE);
  $sr = ldap_read ($ds, $a_dn, $aFilter, $aReturn);  
  $cnEntry = ldap_get_entries($ds, $sr);
  $tmp_err = error_reporting($old_err);
  $cn_cnt = $cnEntry["count"];

  if (strlen($a_flag)==0) {
    
    // delete it if we find it
    if ($cn_cnt>0) {
      $old_err = error_reporting(E_ERROR | E_PARSE);
      $r = ldap_mod_del($ds, $a_dn, $cn_attr);
      $err = ldap_errno ($ds);
      $err_msg = ldap_error ($ds);
      $tmp_err = error_reporting($old_err);
      if ($err>0) {
        $_SESSION['in_msg'] .= "$warn ldap error removing $a_cn "
                       . "from $a_dn: $err - $err_msg.$ef";
      } else {
        $_SESSION['in_msg'] .= "$ok Common Name $a_cn removed "
                             . "from $a_dn.$ef";
      }
    }

  } else {
    // add it if we don't have it
    if ($cn_cnt==0) {
      $old_err = error_reporting(E_ERROR | E_PARSE);
      $r = ldap_mod_add($ds, $a_dn, $cn_attr);
      $err = ldap_errno ($ds);
      $err_msg = ldap_error ($ds);
      $tmp_err = error_reporting($old_err);
      if ($err != 0) {
        $_SESSION['in_msg'] .= "$warn ldap error adding $a_cn to $a_dn: "
                            . "$err - $err_msg.$ef";
      } else {
        $_SESSION['in_msg'] .= "$ok Common Name $a_cn added "
                             . "to $a_dn.$ef";
      }
    }
  }
}

// -------------------------------------------------------------
// main routine

if (!session_is_registered('in_msg')) {
  session_register('in_msg');
}
$_SESSION['in_msg'] = '';
$warn = '<font color="#cc0000">';
$ok = '<font color="#00cc00">';
$ef = "</font><br>\n";

$ldap_base = 'dc=whm,dc=com';

// This array describes the "simple" attributes.  That is attributes
// that have a simple value.
$fld_list = array();
array_push ($fld_list, 'comments');
array_push ($fld_list, 'facsimiletelephonenumber');
array_push ($fld_list, 'givenname');
array_push ($fld_list, 'l');
array_push ($fld_list, 'mail');
array_push ($fld_list, 'mobile');
array_push ($fld_list, 'nickname');
array_push ($fld_list, 'objectclass');
array_push ($fld_list, 'pager');
array_push ($fld_list, 'postaladdress');
array_push ($fld_list, 'postalcode');
array_push ($fld_list, 'sn');
array_push ($fld_list, 'st');
array_push ($fld_list, 'telephonenumber');
array_push ($fld_list, 'uid');

require ('inc_auth.php');
$ds = ldap_connect($ldap_server);
if (!$ds) {
  $_SESSION['in_msg'] .= "Problem connecting to the $ldap_server server";
  $btn_add = '';
  $btn_update = '';
  $btn_delete = '';
} else {
  $r=ldap_bind($ds,$ldap_manager,$ldap_password);
}

if (strlen($btn_add)>0) {
  if (strlen($in_uid)==0) {
    $_SESSION['in_msg'] .= "$warn UID is required.$ef";
  } elseif (strlen($in_new_cn)==0) {
    $_SESSION['in_msg'] .= "$warn Common Name is required.$ef";
  } else {
    // check for duplicates first
    $filter = "(uid=$in_uid)";
    $attrs = array ('cn');
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_search ($ds, $ldap_base, $filter, $attrs);  
    $entries = ldap_get_entries($ds, $sr);
    $tmp_err = error_reporting($old_err);
    $uid_cnt = $entries["count"];
    if ($uid_cnt>0) {
      $a_cn = $entries[0]['cn'][0];
      $_SESSION['in_msg'] .= "$warn UID is already in use by $a_cn.$ef";
      $_SESSION['in_msg'] .= "$warn Add of user entry aborted.$ef";
    } else {

      // add the new entry
      $ldap_entry["objectclass"][0] = "top";
      $_SESSION['in_msg'] .= "$ok adding objectClass top$ef";
      $ldap_entry["objectclass"][1] = "person";
      $_SESSION['in_msg'] .= "$ok adding objectClass person$ef";
      foreach ($fld_list as $fld) {
        $name = "in_$fld"; $val = $$name;
        if (strlen($val)>0) {
          $_SESSION['in_msg'] .= "$ok adding $fld = $val$ef";
          $ldap_entry[$fld][0] = $val;
        }
      }

      // create cn entries
      $a_cn = strtok($in_new_cn,',');
      while (strlen($a_cn)>0) {
        $_SESSION['in_msg'] .= "$ok adding cn = $a_cn$ef";
        $ldap_entry["cn"][] = $a_cn;
        $a_cn = strtok(',');
      }

      // add data to directory
      $this_dn = "uid=$in_uid,ou=people,$ldap_base";
      if (@ldap_add($ds, $this_dn, $ldap_entry)) {
        $_SESSION['in_msg'] .= "$ok Directory updated.$ef";
      } else {
        $_SESSION['in_msg'] .="$warn Problem adding $this_dn to directory<br>";
        $ldapErr = ldap_errno ($ds);
        $ldapMsg = ldap_error ($ds);
        $_SESSION['in_msg'] .= "$warn Error: $ldapErr, $ldapMsg<br>";
      }
    }
  }

} elseif (strlen($btn_update)>0) {

  $ldap_filter = 'objectclass=*';

  if (strlen($in_dn) == 0) {
      $_SESSION['in_msg'] .= "$warn No entry to update$ef";
      $ret_cnt = 0;
  } else {
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_read ($ds, $in_dn, $ldap_filter, $fld_list);  
    $info = ldap_get_entries($ds, $sr);
    $err = ldap_errno ($ds);
    if ($err) {
      $err_msg = ldap_error ($ds);
      $_SESSION['in_msg'] .= "errors: $err $err_msg<br>\n";
    }
    $tmp_err = error_reporting($old_err);
    $ret_cnt = $info["count"];
  }
  if ($ret_cnt) {
    $add_cnt = 0;
    foreach ($fld_list as $fld) {
      if ($fld == 'objectclass') { continue; }
      if ($fld == 'rightslist') { continue; }
      $tmp = 'in_' . $fld;  $val_in   = trim($$tmp) . '';
      $val_ldap = trim($info[0]["$fld"][0]);

      if ( $val_in != $val_ldap ) {
        if (strlen($val_in)==0) {
          if (strlen($val_ldap)>0) {
            // delete the attribute
            $new_data["$fld"] = $val_ldap;
            $old_err = error_reporting(E_ERROR | E_PARSE);
            $r = ldap_mod_del($ds, $in_dn, $new_data);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $tmp_err = error_reporting($old_err);
            $_SESSION['in_msg'] .= "$ok $fld: $val_ldap deleted.$ef";
            if ($err>0) {
              $_SESSION['in_msg'] .= "$warn ldap error deleting "
                       . "attribute $fld: $err - $err_msg.$ef";
            }
          }
        } else {
          $new_data["$fld"] = $val_in;

          // try and replace it, if that fails try and add it

          // replace
          $old_err = error_reporting(E_ERROR | E_PARSE);
          $r=ldap_mod_replace($ds, $in_dn, $new_data);
          $err = ldap_errno ($ds);
          $err_msg = ldap_error ($ds);
          $tmp_err = error_reporting($old_err);
          if ($err == 0) {
            $_SESSION['in_msg'] .= "$ok $fld replaced.$ef";

          } else {

            $add_cnt++;
            $add_data["$fld"] = $val_in;
            $_SESSION['in_msg'] .= "$ok $fld added.$ef";
          }
        }
      }
    }

    // add attributes
    if ($add_cnt>0) {
      $old_err = error_reporting(E_ERROR | E_PARSE);
      $r = ldap_mod_add($ds, $in_dn, $add_data);
      $err = ldap_errno ($ds);
      $err_msg = ldap_error ($ds);
      $tmp_err = error_reporting($old_err);
      if ($err != 0) {
        $_SESSION['in_msg'] .= "$warn ldap error adding attributes: "
                 . "$err - $err_msg.$ef";
      }
    }
    
    // Check common name
    if (strlen($in_new_cn)>0) {
      $a_cn = trim(strtok($in_new_cn, ','));
      while (strlen($a_cn)>0) {
        common_name_check ($in_dn, $a_cn, $a_cn);
        $a_cn = trim(strtok(','));
      }
    }
    if ($in_cn_cnt>0) {
      for ($i=0; $i<$in_cn_cnt; $i++) {
        common_name_check ($in_dn, $in_cn[$i], $in_cn_list[$i]);
      }
    }
    
  }

} elseif (strlen($btn_delete)>0) {

  // check for duplicates first
  $filter = "(uid=$in_uid)";
  $attrs = array ('uidnumber');
  $old_err = error_reporting(E_ERROR | E_PARSE);
  $sr = ldap_search ($ds, $ldap_base, $filter, $attrs);  
  $entries = ldap_get_entries($ds, $sr);
  $tmp_err = error_reporting($old_err);
  $uid_cnt = $entries["count"];
  if ($uid_cnt>0 && strlen($entries[0]['uidnumber'][0])>0) {
    $_SESSION['in_msg'] .= "$warn UID is for a computer user ($in_uid).$ef";
    $_SESSION['in_msg'] .= "$warn Delete of user entry aborted.$ef";
    $_SESSION['in_msg'] .= "$warn Please use User Maint to delete this user.$ef";
  } else {

    // delete the entry
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $r = ldap_delete($ds, $in_dn);
    $err = ldap_errno ($ds);
    $err_msg = ldap_error ($ds);
    $tmp_err = error_reporting($old_err);
    if ($err == 0) {
      $_SESSION['in_msg'] .= "$ok $in_dn deleted.$ef";
    } else {
      $_SESSION['in_msg'] .= "$warn ldap error deleting $in_dn: "
                            . "$err - $err_msg.$ef";
    }

    $in_uid = '';

  }
} else {
  $_SESSION['in_msg'] .= "$warn invalid action$ef";
}

header ("REFRESH: 0; URL=/phone_maintenance.php?in_uid=$in_uid");


?>

<html>
<head>
<title>LDAP Directory Maintenance</title>
</head>
<body>
<a href="http://www.ca-zephyr.org">www.ca-zephyr.org</a>
</body>
</html>
