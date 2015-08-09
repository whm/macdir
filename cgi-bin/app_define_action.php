<?php
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_dn  = $_REQUEST['in_dn'];
$in_cn  = $_REQUEST['in_cn'];
$in_button_add = $_REQUEST['in_button_add'];
$in_button_update = $_REQUEST['in_button_update'];
$in_button_delete = $_REQUEST['in_button_delete'];
// ----------------------------------------------------------
//
// file: app_define_action.php
// author: Bill MacAllister

// -------------------------------------------------------------
// main routine

// Open a session
session_start();

require ('/etc/whm/macdir_auth.php');
require('inc_bind.php');
$ds = macdir_bind($ldap_server, 'GSSAPI');

$_SESSION['in_msg'] = '';
$warn = '<font color="#cc0000">';
$ok = '<font color="#00cc00">';
$ef = "</font><br>\n";

$app_base = 'ou=applications,'.$ldap_base;
$pride_command_processor = 'ldap-aliases@pipe.whm.com';

// This array describes the "simple" attributes.  That is attributes
// that have a simple value.
$fld_list = array();
array_push ($fld_list, 'description');

if (isset($in_button_add)) {

    // -----------------------------------------------------
    // Add an LDAP Entry
    // -----------------------------------------------------

    if (!isset($in_cn)) {
        $_SESSION['in_msg'] .= "$warn Common Name is required.$ef";
    } else {

        // check for duplicates first

        $filter = "(cn=$in_cn)";
        $attrs = array ('description');
        $sr = @ldap_search ($ds, $ldap_base, $filter, $attrs);
        $entries = @ldap_get_entries($ds, $sr);
        $cn_cnt = $entries["count"];
        if ($cn_cnt>0) {
            $a_description = $entries[0]['description'][0];
            $_SESSION['in_msg'] .= "$warn CN is already in use by $a_cn.$ef";
            $_SESSION['in_msg'] .= "$warn Add of application aborted.$ef";
        } else {

            // add the new entry

            $ldap_entry["objectclass"][] = "top";
            $_SESSION['in_msg'] .= "$ok adding objectClass = top$ef";
            $ldap_entry["objectclass"][] = "prideApplication";
            $_SESSION['in_msg']
                .= "$ok adding objectClass = prideApplication$ef";
            $ldap_entry["cn"][] = $in_cn;
            $_SESSION['in_msg'] .= "$ok adding cn = $in_cn$ef";
            foreach ($fld_list as $fld) {
                $val = $_REQUEST["in_$fld"];
                if (isset($val)) {
                    $_SESSION['in_msg'] .= "$ok adding $fld = $val$ef";
                    $ldap_entry[$fld][0] = $val;
                }
            }

            // create member entries
            if (isset($inMemberUIDNew)) {
                $m = trim(strtok($inMemberUIDNew, ','));
                while (isset($m)) {
                    $_SESSION['in_msg'] .= "$ok Member:$m added$ef";
                    $ldap_entry['memberuid'][] = $m;
                    $m = trim(strtok(','));
                }
            }

            // create manager` entries
            if (isset($inManagerUIDNew)) {
                $m = trim(strtok($inManagerUIDNew, ','));
                while (isset($m)) {
                    $_SESSION['in_msg'] .= "$ok Manager:$m added$ef";
                    $ldap_entry['ManagerUID'][] = $m;
                    $m = trim(strtok(','));
                }
            }

            // add data to directory
            $this_dn = "cn=$in_cn,$app_base";
            if (@ldap_add($ds, $this_dn, $ldap_entry)) {
                $_SESSION['in_msg'] .= "$ok Directory updated.$ef";
            } else {
                $_SESSION['in_msg']
                    .= "$warn Problem adding $this_dn to directory$ef";
                $ldapErr = ldap_errno ($ds);
                $ldapMsg = ldap_error ($ds);
                $_SESSION['in_msg'] .= "$warn Error: $ldapErr, $ldapMsg<br>";
            }

            $subj = "Alias addition for new application requested";
            $msg = $subj . "\n";
            mail ($pride_command_processor,
                  $subj,
                  $msg);
            $_SESSION['in_msg'] .= "$ok Mail Server Update Requested$ef";

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
        $ret_list = $fld_list;
        array_push ($ret_list, 'cn');
        $sr = @ldap_read ($ds, $in_dn, $ldap_filter, $ret_list);
        $info = @ldap_get_entries($ds, $sr);
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
            if ($fld == 'cn')          { continue; }
            $val_in   = trim($_REQUEST["in_$fld"]);
            $val_ldap = trim($info[0]["$fld"][0]);

            if ( $val_in != $val_ldap ) {
                if (!isset($val_in)) {
                    if (isset($val_ldap)) {
                        // delete the attribute
                        $d = array();
                        $d["$fld"] = $val_ldap;
                        $r = @ldap_mod_del($ds, $in_dn, $d);
                        $err = ldap_errno ($ds);
                        $err_msg = ldap_error ($ds);
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
                    $r = @ldap_mod_replace($ds, $in_dn, $d);
                    $err = ldap_errno ($ds);
                    $err_msg = ldap_error ($ds);
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

        // Create member array
        $mems = array();
        foreach ($info[0]["$fld"] as $m) {$mems["$m"]++;}

        // Create manager array
        $mgrs = array();
        foreach ($info[0]["$fld"] as $m) {$mgrs["$m"]++;}

        // Check member lists
        if ($inMemberUIDCnt>0) {
            for ($i=0; $i<$inMemberUIDCnt; $i++) {
                $n = $_REQUEST["inMemberUIDList_$i"];
                $m = $_REQUEST["inMemberUID_$i"];
                if (isset($m)) {
                    if ($mems[$m] > 0) {
                        // add the value
                        $add_data['memberuid'][] = $n;
                    }
                } else {
                    if ($mems[$m] == 0) {
                        // delete the value
                        $d = array();
                        $d["memberuid"] = $n;
                        $r = @ldap_mod_del($ds, $in_dn, $d);
                        $err = ldap_errno ($ds);
                        $err_msg = ldap_error ($ds);
                        $_SESSION['in_msg'] .= "$ok memberUid: $n deleted.$ef";
                        if ($err>0) {
                            $_SESSION['in_msg'] .= "$warn ldap error deleting "
                                . "attribute $fld: $err - $err_msg.$ef";
                        }
                    }
                }
            }
        }
        if (isset($inMemberUIDNew)) {
            $m = trim(strtok($inMemberUIDNew, ','));
            while (isset($m)) {
                $_SESSION['in_msg'] .= "$ok Member:$m added$ef";
                $add_cnt++;
                $add_data['memberuid'][] = $m;
                $m = trim(strtok(','));
            }
        }

        // Check manager lists
        if ($inManagerUIDCnt>0) {
            for ($i=0; $i<$inManagerUIDCnt; $i++) {
                $n = $_REQUEST["inManagerUIDList_$i"];
                $m = $_REQUEST["inManagerUID_$i"];
                if (isset($m)) {
                    if ($mgrs[$m] > 0) {
                        // delete the value
                        $add_data['manageruid'][] = $n;
                    }
                } else {
                    if ($mgrs[$m] == 0) {
                        // delete the value
                        $d = array();
                        $d["manageruid"] = $n;
                        $r = @ldap_mod_del($ds, $in_dn, $d);
                        $err = ldap_errno ($ds);
                        $err_msg = ldap_error ($ds);
                        $_SESSION['in_msg']
                            .= "$ok managerUid: $n deleted.$ef";
                        if ($err>0) {
                            $_SESSION['in_msg'] .= "$warn ldap error deleting "
                                . "attribute $fld: $err - $err_msg.$ef";
                        }
                    }
                }
            }
        }

        if (isset($inManagerUIDNew)) {
            $m = trim(strtok($inManagerUIDNew, ','));
            while (isset($m)) {
                $_SESSION['in_msg'] .= "$ok Manager:$m added$ef";
                $add_cnt++;
                $add_data['ManagerUID'][] = $m;
                $m = trim(strtok(','));
            }
        }

        // add attributes
        if ($add_cnt>0) {
            $r = @ldap_mod_add($ds, $in_dn, $add_data);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err != 0) {
                $_SESSION['in_msg'] .= "$warn ldap error adding attributes: "
                    . "$err - $err_msg.$ef";
            }
        }
    }

} elseif (isset($in_button_delete)) {

    // -----------------------------------------------------
    // Delete an LDAP Entry
    // -----------------------------------------------------

    $r = @ldap_delete($ds, $in_dn);
    $err = ldap_errno ($ds);
    $err_msg = ldap_error ($ds);
    if ($err == 0) {
        $_SESSION['in_msg'] .= "$ok $in_dn deleted.$ef";
    } else {
        $_SESSION['in_msg'] .= "$warn ldap error deleting $in_dn: "
            . "$err - $err_msg.$ef";
    }
    $in_cn = '';

    $subj = "Alias delettion for obsolete application requested";
    $msg = $subj . "\n";
    mail ($pride_command_processor,
          $subj,
          $msg);
    $_SESSION['in_msg'] .= "$ok Mail Server Update Requested$ef";

} else {
    $_SESSION['in_msg'] .= "$warn invalid action$ef";
}

$a_url = "app_define.php?in_cn=$in_cn";
header ("REFRESH: 0; URL=$a_url");

?>

<html>
<head>
<title>PRIDE Application Definition</title>
</head>
<body>
<a href="<?php echo $a_url;?>">Back to Application Definition</a>
</body>
</html>
