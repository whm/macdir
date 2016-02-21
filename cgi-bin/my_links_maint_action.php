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
// file: my_links_maint_action.php
// author: Bill MacAllister

// -------------------------------------------------------------
// main routine

$_SESSION['in_msg'] = '';
$warn = '<font color="#cc0000">';
$ok = '<font color="#00cc00">';
$ef = "</font><br>\n";

// This array describes the "simple" attributes.  That is attributes
// that have a simple value.
$fld_list = array();
array_push ($fld_list, 'description');
array_push ($fld_list, 'prideurl');
array_push ($fld_list, 'prideurlprivate');
array_push ($fld_list, 'linkuid');
array_push ($fld_list, 'pridecredential');

require('inc_init.php');
require('/etc/whm/macdir.php');

$ds = macdir_bind($ldap_server, 'GSSAPI');

$link_base = 'uid=' . $_SERVER['REMOTE_USER'] . ',' . $ldap_user_base;
$link_filter = "(&(objectclass=pridelistobject)(cn=$in_cn))";

if (isset($in_button_add)) {

    // -----------------------------------------------------
    // Add an LDAP Entry
    // -----------------------------------------------------

    if (!isset($in_cn)) {
        $_SESSION['in_msg'] .= "$warn Common Name is required.$ef";
    } else {

        // check for duplicates first

        $filter = "(cn=$in_cn)";
        $attrs = array ('cn');
        $sr = @ldap_search ($ds, $link_base, $link_filter, $attrs);
        $entries = @ldap_get_entries($ds, $sr);
        $cn_cnt = $entries["count"];
        if ($cn_cnt>0) {
            $a_cn = $entries[0]['cn'][0];
            $_SESSION['in_msg'] .= "$warn cn is already in use ($a_cn).$ef";
            $_SESSION['in_msg'] .= "$warn Add of entry aborted.$ef";
        } else {

            // add the new entry

            $ldap_entry["objectclass"][] = "top";
            $_SESSION['in_msg'] .= "$ok adding objectClass = top$ef";
            $ldap_entry["objectclass"][] = "pridelistobject";
            $_SESSION['in_msg'] .= "$ok adding objectClass = "
                . "pridelistobject$ef";
            $ldap_entry["cn"][] = $in_cn;
            $_SESSION['in_msg'] .= "$ok adding cn = $in_cn$ef";

            foreach ($fld_list as $fld) {
                $val = stripslashes(trim($_REQUEST["in_$fld"]));
                if (isset($val)) {
                    $_SESSION['in_msg'] .= "$ok adding $fld = $val$ef";
                    $ldap_entry[$fld][0] = $val;
                }
            }

            // add data to directory
            $this_dn = "cn=$in_cn,$link_base";
            if (@ldap_add($ds, $this_dn, $ldap_entry)) {
                $_SESSION['in_msg'] .= "$ok Directory updated.$ef";
            } else {
                $_SESSION['in_msg'] .= "$warn Problem adding $this_dn $ef";
                $ldapErr = ldap_errno ($ds);
                $ldapMsg = ldap_error ($ds);
                $_SESSION['in_msg'] .= "$warn Error: $ldapErr, $ldapMsg<br>";
            }

        }
    }

} elseif (isset($in_button_update)) {

    // -----------------------------------------------------
    // Update an LDAP Entry
    // -----------------------------------------------------

    if (!isset($in_cn)) {
        $_SESSION['in_msg'] .= "$warn No entry to update$ef";
        $ret_cnt = 0;
    } else {

        $sr = @ldap_read ($ds, $in_dn, $link_filter, $fld_list);
        $info = @ldap_get_entries($ds, $sr);
        $err = ldap_errno ($ds);
        if ($err) {
            $err_msg = ldap_error ($ds);
            $_SESSION['in_msg'] .= "errors: $err $err_msg<br>\n";
        }
        $ret_cnt = $info["count"];
    }
    if ($ret_cnt) {

        foreach ($fld_list as $fld) {

            if ($fld == 'objectclass') { continue; }
            if ($fld == 'cn')          { continue; }

            $val_in = '';
            $tmp = $_REQUEST["in_$fld"];
            if (isset($tmp)) {
                $val_in  = stripslashes(trim($tmp));
            }

            $val_ldap = '';
            if (isset($info[0]["$fld"][0])) {
                $val_ldap = trim($info[0]["$fld"][0]);
            }

            if ( $val_in != $val_ldap ) {
                if (!isset($val_in)) {
                    if (isset($val_ldap)) {

                        // delete the attribute
                        $new_data["$fld"] = $val_ldap;
                        $r = @ldap_mod_del($ds, $in_dn, $new_data);
                        $err = @ldap_errno ($ds);
                        $err_msg = ldap_error ($ds);
                        $tmp_err = error_reporting($old_err);
                        $_SESSION['in_msg'] .= "$ok $fld: $val_ldap "
                            ."deleted.$ef";
                        if ($err>0) {
                            $_SESSION['in_msg'] .= "$warn ldap error deleting "
                                . "attribute $fld: $err - $err_msg.$ef";
                        }

                    }
                } else {

                    $new_data["$fld"] = $val_in;

                    // try and replace it, if that fails try and add it

                    // replace
                    $r = @ldap_mod_replace($ds, $in_dn, $new_data);
                    $err = ldap_errno ($ds);
                    $err_msg = ldap_error ($ds);
                    if ($err == 0) {
                        $_SESSION['in_msg'] .= "$ok $fld replaced.$ef";
                    } else {
                        // add
                        $add_cnt++;
                        $add_data["$fld"][] = $val_in;
                        $_SESSION['in_msg'] .= "$ok $fld added.$ef";
                    }
                }
            }
        }


        // -- add attributes
        if ($add_cnt>0) {

            // -- add the needed attributes
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

} else {

    $_SESSION['in_msg'] .= "$warn invalid action$ef";

}

$a_url = "my_links_maint.php?in_cn=$in_cn";
header ("REFRESH: 0; URL=$a_url");
?>

<html>
<head>
<title>MacAllister LDAP Directory Maintenance</title>
</head>
<body>
<a href="<?php echo $a_url;?>">Back to Link Maintenance</a>
</body>
</html>
