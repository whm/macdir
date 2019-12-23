<?php
//
// file: my_links_maint_action.php
// author: Bill MacAllister

// -------------------------------------------------------------
// main routine

// This array describes the "simple" attributes.  That is attributes
// that have a simple value.
$fld_list = array();
array_push ($fld_list, 'description');
array_push ($fld_list, 'prideurl');
array_push ($fld_list, 'prideurlprivate');
array_push ($fld_list, 'linkuid');
array_push ($fld_list, 'pridecredential');

$in_dn = empty($_REQUEST['in_dn']) ? '' : $_REQUEST['in_dn'];
$in_cn = empty($_REQUEST['in_cn']) ? '' : $_REQUEST['in_cn'];

require('inc_init.php');

$ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');

$link_base = 'uid=' . $_SERVER['REMOTE_USER'] . ',' . $ldap_user_base;
$link_filter = "(&(objectclass=pridelistobject)(cn=$in_cn))";

if (!empty($_REQUEST['in_button_add'])) {

    // -----------------------------------------------------
    // Add an LDAP Entry
    // -----------------------------------------------------

    if (empty($in_cn)) {
        $_SESSION['in_msg'] .= warn_html('Common Name is required');
    } else {

        // check for duplicates first

        $filter = "(cn=$in_cn)";
        $attrs = array ('cn');
        $sr = @ldap_search ($ds, $link_base, $link_filter, $attrs);
        $entries = @ldap_get_entries($ds, $sr);
        $cn_cnt = $entries["count"];
        if ($cn_cnt>0) {
            $a_cn = $entries[0]['cn'][0];
            $_SESSION['in_msg'] .= warn_html("cn is already in use ($a_cn)");
            $_SESSION['in_msg'] .= warn_html('Add of entry aborted');
        } else {

            // add the new entry

            $ldap_entry["objectclass"][] = "top";
            $_SESSION['in_msg'] .= ok_html('adding objectClass = top');
            $ldap_entry["objectclass"][] = "pridelistobject";
            $_SESSION['in_msg']
                .= ok_html("adding objectClass = pridelistobject");
            $ldap_entry["cn"][] = $in_cn;
            $_SESSION['in_msg'] .= ok_html("Adding cn = $in_cn");

            foreach ($fld_list as $fld) {
                $val = stripslashes(trim($_REQUEST["in_$fld"]));
                if ($fld == 'pridecredential' && !empty($CONF['key'])) {
                    $val = $CONF['key_prefix'] . macdir_encode($val);
                }
                if (!empty($val)) {
                    $_SESSION['in_msg'] .= ok_html("Adding $fld = $val");
                    $ldap_entry[$fld][0] = $val;
                }
            }

            // add data to directory
            $this_dn = "cn=$in_cn,$link_base";
            if (@ldap_add($ds, $this_dn, $ldap_entry)) {
                $_SESSION['in_msg'] .= ok_html('Directory updated');
            } else {
                $_SESSION['in_msg'] .= warn_html("Problem adding $this_dn");
                $ldapErr = ldap_errno ($ds);
                $ldapMsg = ldap_error ($ds);
                $_SESSION['in_msg'] .= warn_html("Error: $ldapErr, $ldapMsg");
            }

        }
    }

} elseif (!empty($_REQUEST['in_button_update'])) {

    // -----------------------------------------------------
    // Update an LDAP Entry
    // -----------------------------------------------------

    if (empty($in_cn)) {
        $_SESSION['in_msg'] .= warn_html('No entry to update');
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
            if (!empty($tmp)) {
                $val_in  = stripslashes(trim($tmp));
            }
            if (!empty($val_in) && $fld == 'pridecredential') {
                $val_in = $CONF['key_prefix'] . macdir_encode($val_in);
            }

            $val_ldap = empty($info[0]["$fld"][0])
                ? '' : trim($info[0]["$fld"][0]);

            if ( $val_in != $val_ldap ) {
                if (empty($val_in)) {
                    if (!empty($val_ldap)) {

                        // delete the attribute
                        $new_data["$fld"] = $val_ldap;
                        $r = @ldap_mod_del($ds, $in_dn, $new_data);
                        $err = @ldap_errno ($ds);
                        $err_msg = ldap_error ($ds);
                        $tmp_err = error_reporting($old_err);
                        $_SESSION['in_msg']
                            .= ok_html("$fld = $val_ldap deleted");
                        if ($err>0) {
                            $_SESSION['in_msg']
                                .= warn_html('LDAP error deleting attribute '
                                . "$fld = $val_ldap : $err - $err_msg");
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
                        $_SESSION['in_msg']
                            .= ok_html("$fld replaced with $in_val");
                    } else {
                        // add
                        $add_cnt++;
                        $add_data["$fld"][] = $val_in;
                        $_SESSION['in_msg']
                            .= ok_html("Added $fld = $in_val");
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
                $_SESSION['in_msg']
                    .= warn_html('ldap error adding attributes: '
                    . "$err - $err_msg");
            }
        }
    }

} elseif (!empty($_REQUEST['in_button_delete'])) {

    // -----------------------------------------------------
    // Delete an LDAP Entry
    // -----------------------------------------------------

    $r = @ldap_delete($ds, $in_dn);
    $err = ldap_errno ($ds);
    $err_msg = ldap_error ($ds);
    if ($err == 0) {
        $_SESSION['in_msg'] .= ok_html("$in_dn deleted");
    } else {
        $_SESSION['in_msg']
            .= warn_html("ldap error deleting $in_dn: $err - $err_msg");
    }

} else {

    $_SESSION['in_msg'] .= warn_html('invalid action');

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
