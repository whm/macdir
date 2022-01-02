<?php
//
// file: my_links_maint_action.php
// author: Bill MacAllister

require('inc_init.php');

// -------------------------------------------------------------
// Check to see if a uid exists

function is_uid($uid) {
    global $ds;
    global $ldap_user_base;

    $found = 0;

    $return_attr = array('objectclass');
    $uid_filter = "(&(objectclass=person)(uid=$uid))";
    $sr = @ldap_search ($ds, $ldap_user_base, $uid_filter, $return_attr);
    $info = @ldap_get_entries($ds, $sr);
    if ($info["count"] > 0) {
        $found = 1;
    }
    return $found;
}

// --------------------------------------------------------------
// Update single value of multi-valued attribute

function multi_check($a_dn, $a_fld, $a_flag, $a_val) {

    global $ds;

    $attr_val[$a_fld] = $a_val;

    // search for it
    $a_filter = "($a_fld=$a_val)";
    $a_return = array ($a_fld);
    $sr = @ldap_read ($ds, $a_dn, $a_filter, $a_return);
    $entry = ldap_get_entries($ds, $sr);
    $entry_cnt = $entry["count"];

    if (!$a_flag) {

        // delete it if we find it
        if ($entry_cnt) {
            $r = @ldap_mod_del($ds, $a_dn, $attr_val);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err>0) {
                $e = "$err - $err_msg";
                $_SESSION['in_msg'] .=
                    warn_html(
                        "LDAP error removing $a_fld=$a_val from $a_dn: $e");
            } else {
                $_SESSION['in_msg'] .=
                    ok_html("$a_fld=$a_val removed from $a_dn");
            }
        }

    } else {
        // add it if we don't have it
        if (!$entry_cnt) {
            $r = @ldap_mod_add($ds, $a_dn, $attr_val);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err != 0) {
                $e =  "$err - $err_msg";
                $_SESSION['in_msg']
                    .= warn_html(
                        "LDAP error adding $a_fld=$a_val to $a_dn: $e");
            } else {
                $_SESSION['in_msg']
                    .= ok_html("$a_fld=$a_val added to $a_dn");
            }
        }
    }
    return;
}

// -------------------------------------------------------------
// main routine

// This array describes the "simple" attributes.  That is attributes
// that have a simple value.
$fld_list = array(
  'description' => 'description',
  'linkurl'     => $CONF['attr_link_url'],
  'linkuid'     => $CONF['attr_link_uid'],
  'credential'  => $CONF['attr_cred'],
  'linkprivate' => $CONF['attr_link_visibility']
);
$attr_list = array();
foreach ($fld_list as $fld => $attr) {
  array_push($attr_list, $attr);
}

$access_ids = ['read', 'write'];

$in_dn = empty($_REQUEST['in_dn']) ? '' : $_REQUEST['in_dn'];
$in_cn = empty($_REQUEST['in_cn']) ? '' : $_REQUEST['in_cn'];

$ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');

$link_base = 'uid=' . $_SERVER['REMOTE_USER'] . ',' . $ldap_user_base;
$link_filter = '(&(objectclass=' . $CONF['oc_link'] . ")(cn=$in_cn))";

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
            $_SESSION['in_msg'] .= ok_html('Adding objectClass = top');
            $ldap_entry["objectclass"][] = $CONF['oc_link'];
            $_SESSION['in_msg']
                .= ok_html('Adding objectClass = ' . $CONF['oc_link']);
            $ldap_entry["cn"][] = $in_cn;
            $_SESSION['in_msg'] .= ok_html("Adding cn = $in_cn");

            foreach ($fld_list as $fld => $attr) {
                $val = stripslashes(trim($_REQUEST["in_$fld"]));
                if ($fld == $CONF['attr_cred'] && !empty($CONF['key'])) {
                    $val = $CONF['key_prefix'] . macdir_encode($val);
                }
                if (!empty($val)) {
                    if ($fld == $CONF['attr_cred']) {
                        $_SESSION['in_msg'] .= ok_html("Adding $fld");
                    } else {
                        $_SESSION['in_msg'] .= ok_html("Adding $fld = $val");
                    }
                    $ldap_entry[$attr][0] = $val;
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

            // Add any access controls
            foreach ($access_ids as $a) {
                $in_list = 'in_new_' . strtolower($a);
                $in_attr = $CONF["attr_link_${a}"];
                $a_user = trim(strtok($_REQUEST[$in_list], ','));
                while ($a_user) {
                    if (is_uid($a_user)) {
                        multi_check($this_dn, $in_attr, $a_user, $a_user);
                    } else {
                        $_SESSION['in_msg']
                            .= warn_html("ERROR: Invalid UID $a_uid");
                    }
                    $a_user = trim(strtok(','));
                }
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

        $sr = @ldap_read ($ds, $in_dn, $link_filter, $attr_list);
        $info = @ldap_get_entries($ds, $sr);
        $err = ldap_errno ($ds);
        if ($err) {
            $err_msg = ldap_error ($ds);
            $_SESSION['in_msg'] .= "errors: $err $err_msg<br>\n";
        }
        $ret_cnt = $info["count"];
    }
    if ($ret_cnt) {
        $add_cnt = 0;

        foreach ($fld_list as $fld => $attr) {

            $val_in = '';
            $tmp = $_REQUEST["in_$fld"];

            if (!empty($tmp)) {
                $val_in  = stripslashes(trim($tmp));
            }
            if (!empty($val_in) && $attr == $CONF['attr_cred']) {
                $val_in = $CONF['key_prefix'] . macdir_encode($val_in);
            }

            $val_ldap = empty($info[0]["$attr"][0])
                ? '' : trim($info[0]["$attr"][0]);

            if ( $val_in != $val_ldap ) {
                if (empty($val_in)) {
                    if (!empty($val_ldap)) {

                        // delete the attribute
                        $new_data["$attr"] = $val_ldap;
                        $r = @ldap_mod_del($ds, $in_dn, $new_data);
                        $err = @ldap_errno ($ds);
                        $err_msg = ldap_error ($ds);
                        $tmp_err = error_reporting($old_err);
                        $_SESSION['in_msg']
                            .= ok_html("$attr = $val_ldap deleted");
                        if ($err>0) {
                            $_SESSION['in_msg']
                                .= warn_html('LDAP error deleting attribute '
                                . "$attr = $val_ldap : $err - $err_msg");
                        }

                    }
                } else {

                    $new_data["$attr"] = $val_in;

                    // try and replace it, if that fails try and add it

                    // replace
                    $r = @ldap_mod_replace($ds, $in_dn, $new_data);
                    $err = ldap_errno ($ds);
                    $err_msg = ldap_error ($ds);
                    if ($err == 0) {
                        if ($attr == $CONF['attr_cred']) {
                            $_SESSION['in_msg']
                                .= ok_html("$attr replaced");
                        } else {
                            $_SESSION['in_msg']
                                .= ok_html("$attr replaced with $val_in");
                        }
                    } else {
                        // add
                        $add_cnt++;
                        $add_data["$attr"][] = $val_in;
                        $_SESSION['in_msg']
                            .= ok_html("Added $attr = $in_val");
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
                    .= warn_html('LDAP error adding attributes: '
                    . "$err - $err_msg");
            }
        }

        // Update access controls
        foreach ($access_ids as $a) {
            // add new
            $in_list = 'in_new_' . strtolower($a);
            $in_attr = $CONF["attr_link_${a}"];
        $a_user = trim(strtok($_REQUEST[$in_list], ','));
            while ($a_user) {
                if (is_uid($a_user)) {
                    multi_check($in_dn, $in_attr, $a_user, $a_user);
                } else {
                    $_SESSION['in_msg']
                        .= warn_html("ERROR: Invalid UID $a_uid");
                }
                $a_user = trim(strtok(','));
            }
            // Update old
            $in_prefix  = 'in_' . strtolower($a) . 'uid_';
            $in_var_cnt = $_REQUEST[$in_prefix . 'cnt'];
            if ($in_var_cnt>0) {
                for ($i=0; $i<$in_var_cnt; $i++) {
                    $a_flag = empty($_REQUEST[$in_prefix . $i])
                            ? '' : $_REQUEST[$in_prefix . $i];
                    $a_cur  = empty($_REQUEST[$in_prefix . "current_$i"])
                            ? '' : $_REQUEST[$in_prefix . "current_$i"];
                    multi_check($in_dn, $in_attr, $a_flag, $a_cur);
                }
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
