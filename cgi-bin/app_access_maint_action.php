<?php
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_cn_cnt  = $_REQUEST['in_cn_cnt'];
$in_pwfs_cnt  = $_REQUEST['in_pwfs_cnt'];
$in_new_cn  = $_REQUEST['in_new_cn'];
$in_employeenumber  = $_REQUEST['in_employeenumber'];
$in_dn  = $_REQUEST['in_dn'];
$in_uid  = $_REQUEST['in_uid'];
$in_button_add = $_REQUEST['in_button_add'];
$in_button_update = $_REQUEST['in_button_update'];
$in_button_delete = $_REQUEST['in_button_delete'];
// ----------------------------------------------------------
//
// file: app_access_maint_action.php
// author: Bill MacAllister

// Open a session and check for authorization
require('whm_php_sessions.inc');
require('whm_php_auth.inc');
whm_auth("user");

require ('/etc/whm/macdir_auth.php');

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

        // Ooops, can find them for some reason.
        // Generally this is okay because they don't have complete access
        // to all systems and thus might not have the required objectclass.

    } else {

        $ldapDN = $ldapInfo[0]['dn'];
        $_SESSION['s_msg'] .= "<font $ok>updating $ldapDN</font><br>";
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
            $_SESSION['s_msg'] .= "<font $warn>Problem adding "
                . "$pwField for $ldapDN</font><br>";
            $ldapErr = ldap_errno ($ds);
            $ldapMsg = ldap_error ($ds);
            $_SESSION['s_msg']
                .= "<font $warn>Error: $ldapErr, $ldapMsg</font><br>";
        } else {
            $_SESSION['s_msg'] .= "<font $ok>$pwField changed.</font><br>";

        }

    }

    $junk = error_reporting($old_ldap_error_level);
}

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

    if (isset($a_flag)) {

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
                $_SESSION['in_msg']
                    .= "$warn ldap error adding $a_cn to $a_dn: "
                    . "$err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg'] .= "$ok Common Name $a_cn added "
                    . "to $a_dn.$ef";
            }
        }
    }
}

// --------------------------------------------------------------
// Check to make sure a uid is either in the specified application
// group, i.e. $a_flag>0, or is not in the group, i.e. $a_flag==0.

function mgr_group_check ($a_uid, $a_flag, $a_app) {

    global $ds, $ldap_base, $ok, $warn, $ef, $mgr_groups, $mgr_group_cnt;

    if ($mgr_group_cnt==0)           {return;}
    if (!isset($mgr_groups[$a_app])) {return;}

    $group_attr['memberUid'] = $a_uid;

    // search for it
    $aFilter = "(&(objectclass=prideApplication)";
    $aFilter .= "(cn=$a_app)";
    $aFilter .= "(memberUid=$a_uid))";
    $aRetAttrs = array ('cn');
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_search ($ds, $ldap_base, $aFilter, $aRetAttrs);
    $app = ldap_get_entries($ds, $sr);
    $tmp_err = error_reporting($old_err);
    $aCnt = $app["count"];

    if (!isset($a_flag)) {

        // delete it if we find it
        if ($aCnt>0) {
            $app_dn = $app[0]['dn'];
            $old_err = error_reporting(E_ERROR | E_PARSE);
            $r = ldap_mod_del($ds, $app_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $tmp_err = error_reporting($old_err);
            if ($err>0) {
                $_SESSION['in_msg'] .= "$warn ldap error removing $a_uid "
                    . "from $a_app: $err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg'] .= "$ok $a_uid removed from $a_app.$ef";
            }
        }

    } else {

        // add it if we don't
        if ($aCnt==0) {
            $app_dn = "cn=$a_app,ou=applications,$ldap_base";
            $old_err = error_reporting(E_ERROR | E_PARSE);
            $r = ldap_mod_add($ds, $app_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $tmp_err = error_reporting($old_err);
            if ($err != 0) {
                $_SESSION['in_msg']
                    .= "$warn ldap error add $a_uid to $app_dn: "
                    . "$err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg'] .= "$ok $a_uid added to $a_app.$ef";
            }
        }
    }
}

// --------------------------------------------------------------
// Check to make sure the dn has a mailDistributionID for this
// mail list, i.e. $a_flag>0, or does not, i.e. $a_flag==0.

function mail_group_check ($a_dn, $a_flag, $a_ml) {

    global $ds, $ldap_base, $ok, $warn, $ef;

    $group_attr['mailDistributionID'] = $a_ml;

    // search for it
    $aFilter = "mailDistributionID=$a_ml";
    $aRetAttrs = array ('cn');
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_read ($ds, $a_dn, $aFilter, $aRetAttrs);
    $ml_entry = ldap_get_entries($ds, $sr);
    $tmp_err = error_reporting($old_err);
    $aCnt = $ml_entry["count"];

    if (!isset($a_flag)) {

        // delete it if we find it
        if ($aCnt>0) {
            $old_err = error_reporting(E_ERROR | E_PARSE);
            $r = ldap_mod_del($ds, $a_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $tmp_err = error_reporting($old_err);
            if ($err>0) {
                $_SESSION['in_msg'] .= "$warn ldap error removing $a_ml "
                    . "from $a_dn: $err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg'] .= "$ok Mail List $a_ml removed "
                    . "from $a_dn.$ef";
            }
        }

    } else {

        // add it if we don't
        if ($aCnt==0) {
            $old_err = error_reporting(E_ERROR | E_PARSE);
            $r = ldap_mod_add($ds, $a_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $tmp_err = error_reporting($old_err);
            if ($err != 0) {
                $_SESSION['in_msg']
                    .= "$warn ldap error adding $a_ml to $a_dn: "
                    . "$err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg']
                    .= "$ok Mail List $a_ml added to $a_dn.$ef";
            }
        }
    }
}

// -------------------------------------------------------------
// main routine

$warn = '<font color="#cc0000">';
$ok = '<font color="#00cc00">';
$ef = "</font><br>\n";

// This array describes the "simple" attributes.  That is attributes
// that have a simple value.
$fld_list = array();
array_push ($fld_list, 'comments');
if (!isset($in_employeenumber)) {
    array_push ($fld_list, 'facsimiletelephonenumber');
    array_push ($fld_list, 'givenname');
    array_push ($fld_list, 'l');
    array_push ($fld_list, 'mail');
    array_push ($fld_list, 'mobile');
    array_push ($fld_list, 'middlename');
    array_push ($fld_list, 'nickname');
    array_push ($fld_list, 'objectclass');
    array_push ($fld_list, 'pager');
    array_push ($fld_list, 'postaladdress');
    array_push ($fld_list, 'postalcode');
    array_push ($fld_list, 'sn');
    array_push ($fld_list, 'st');
    array_push ($fld_list, 'telephonenumber');
    array_push ($fld_list, 'title');
    array_push ($fld_list, 'uid');
}

$ds = ldap_connect($ldap_server);
if (!$ds) {
    $_SESSION['in_msg'] .= "Problem connecting to the $ldap_server server";
    $in_button_add = '';
    $in_button_update = '';
    $in_button_delete = '';
} else {
    $r=ldap_bind($ds,$ldap_manager,$ldap_password);
}

// get a list application groups to be managed
require ('inc_groups_managed.php');

if (isset($in_button_add)) {

    // -----------------------------------------------------
    // Add an LDAP Entry
    // -----------------------------------------------------

    if (!isset($in_new_cn)) {
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

            $ldap_entry["objectclass"][] = "top";
            $_SESSION['in_msg'] .= "$ok adding objectClass = top$ef";
            $ldap_entry["objectclass"][] = "person";
            $_SESSION['in_msg'] .= "$ok adding objectClass = person$ef";
            $posix_entry = 0;
            foreach ($fld_list as $fld) {
                $name = "in_$fld"; $val = $$name;
                if (isset($val)) {
                    $_SESSION['in_msg'] .= "$ok adding $fld = $val$ef";
                    $ldap_entry[$fld][0] = $val;
                }
            }

            // create cn entries
            $first_cn = $a_cn = strtok($in_new_cn,',');
            while (isset($a_cn)) {
                $_SESSION['in_msg'] .= "$ok adding cn = $a_cn$ef";
                $ldap_entry["cn"][] = $a_cn;
                $a_cn = strtok(',');
            }

            // create mail distribution entries
            $a_ml = strtok($inMailIDNew,',');
            while (isset($a_ml)) {
                $_SESSION['in_msg']
                    .="$ok adding mailDistributionID = $a_ml$ef";
                $ldap_entry["maildistributionid"][] = $a_ml;
                $a_ml = strtok(',');
            }

            // add data to directory
            $this_dn = "uid=$in_uid,ou=people,$ldap_base";
            if (@ldap_add($ds, $this_dn, $ldap_entry)) {
                $_SESSION['in_msg'] .= "$ok Directory updated.$ef";
            } else {
                $_SESSION['in_msg']
                    .= "$warn Problem adding $this_dn to directory$ef";
                $ldapErr = ldap_errno ($ds);
                $ldapMsg = ldap_error ($ds);
                $_SESSION['in_msg'] .= "$warn Error: $ldapErr, $ldapMsg<br>";
            }

            // Check application groups

            for ($i=0; $i<$in_pwfs_cnt; $i++) {
                $name = "in_pwfs_select_$i";  $selectApp = $$name;
                $name = "in_pwfs_$i";         $thisApp = $$name;
                if ($selectApp > 0) {
                    mgr_group_check ($in_uid, "ADD", $thisApp);
                } else {
                    mgr_group_check ($in_uid, "", $thisApp);
                }
            }

            // Finally set the password

            ldap_set_password ($in_uid, 'userpassword', $in_pass1);

        }
    }

} elseif (isset($in_button_update)) {

    // -----------------------------------------------------
    // Update an LDAP Entry
    // -----------------------------------------------------

    $ldap_filter = 'objectclass=*';

    if (!isset($in_dn)) {
        $_SESSION['in_msg'] .= "$warn No entry to update$ef";
        $ret_cnt = 0;
    } else {
        $old_err = error_reporting(E_ERROR | E_PARSE);
        $ret_list = $fld_list;
        array_push ($ret_list, 'cn');
        $sr = ldap_read ($ds, $in_dn, $ldap_filter, $ret_list);
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

        $first_cn = trim($info[0]['cn'][0]);
        $add_cnt = 0;

        foreach ($fld_list as $fld) {
            if ($fld == 'objectclass') { continue; }
            $tmp = 'in_' . $fld;  $val_in   = trim($$tmp) . '';
            $val_ldap = trim($info[0]["$fld"][0]);

            if ( $val_in != $val_ldap ) {
                if (!isset($val_in)) {
                    if (isset($val_ldap)) {
                        // delete the attribute
                        $d = array();
                        $d["$fld"] = $val_ldap;
                        $old_err = error_reporting(E_ERROR | E_PARSE);
                        $r = ldap_mod_del($ds, $in_dn, $d);
                        $err = ldap_errno ($ds);
                        $err_msg = ldap_error ($ds);
                        $tmp_err = error_reporting($old_err);
                        $_SESSION['in_msg']
                            .= "$ok $fld: $val_ldap deleted.$ef";
                        if ($err>0) {
                            $_SESSION['in_msg'] .= "$warn ldap error deleting "
                                . "attribute $fld: $err - $err_msg.$ef";
                        }
                    }
                } else {

                    // try and replace it, if that fails try and add it

                    // replace
                    $d = array();
                    $d["$fld"] = $val_in;
                    $old_err = error_reporting(E_ERROR | E_PARSE);
                    $r=ldap_mod_replace($ds, $in_dn, $d);
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

        // Check mail lists
        if ($inMailIDCnt>0) {
            for ($i=0; $i<$inMailIDCnt; $i++) {
                $flag_name = "inMailID_$i";
                $list_name = "inMailIDList_$i";
                mail_group_check ($in_dn, $$flag_name, $$list_name);
            }
        }
        if (isset($inMailIDNew)) {
            $ml = trim(strtok($inMailIDNew, ','));
            while (isset($ml)) {
                mail_group_check ($in_dn, $ml, $ml);
                $ml = trim(strtok(','));
            }
        }

        // Check common name
        if (isset($in_new_cn)) {
            $a_cn = trim(strtok($in_new_cn, ','));
            while (isset($a_cn)) {
                common_name_check ($in_dn, $a_cn, $a_cn);
                $a_cn = trim(strtok(','));
            }
        }
        if ($in_cn_cnt>0) {
            for ($i=0; $i<$in_cn_cnt; $i++) {
                common_name_check ($in_dn, $in_cn[$i], $in_cn_list[$i]);
            }
        }

        // Check application groups

        for ($i=0; $i<$in_pwfs_cnt; $i++) {
            $name = "in_pwfs_select_$i";  $selectApp = $$name;
            $name = "in_pwfs_$i";         $thisApp = $$name;
            if ($selectApp > 0) {
                mgr_group_check ($in_uid, "ADD", $thisApp);
            } else {
                mgr_group_check ($in_uid, "", $thisApp);
            }
        }
    }


} elseif (isset($in_button_delete)) {

    // -----------------------------------------------------
    // Delete an LDAP Entry
    // -----------------------------------------------------

    // delete from app groups
    $pg_filter = "(&(objectclass=prideApplication)(memberUid=$in_uid))";
    $pg_filter = "memberUid=$in_uid";
    $pg_attrs = array ('cn','description');
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_search ($ds, $ldap_base, $pg_filter, $pg_attrs);
    $pg_group = ldap_get_entries($ds, $sr);
    $tmp_err = error_reporting($old_err);
    $pg_cnt = $pg_group["count"];

    if ($pg_cnt > 0) {
        for ($pg_i=0; $pg_i<$pg_cnt; $pg_i++) {
            mgr_group_check ($in_uid, '', $pg_group[$pg_i]['cn'][0]);
        }
    }

    // now delete the entry
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

} else {
    $_SESSION['in_msg'] .= "$warn invalid action$ef";
}

$a_url = "app_access_maint.php?in_uid=$in_uid";
header ("REFRESH: 0; URL=$a_url");

?>

<html>
<head>
<title>PRIDE LDAP Directory Maintenance</title>
</head>
<body>
<a href="<?php echo $a_url;?>">Back to User Maintenance</a>
</body>
</html>
