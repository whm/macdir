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
    # FIXME - search needed to find the user

    $found = 1;
    return $found;
}

// --------------------------------------------------------------
// Return a space if given a non-empty string

function add_space($s) {
    if (!empty($s)) {
        return ' ';
    }
    return;
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
                set_warn("LDAP error removing $a_fld=$a_val from $a_dn: $e");
            } else {
                set_ok("$a_fld=$a_val removed from $a_dn");
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
                set_warn("LDAP error adding $a_fld=$a_val to $a_dn: $e");
            } else {
                set_ok("$a_fld=$a_val added to $a_dn");
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

$link_owner  = $_SERVER['REMOTE_USER'];
$link_filter = '(&(objectclass=' . $CONF['oc_link'] . ")(cn=$in_cn))";

$this_tgt = getenv('KRB5CCNAME');

if (!empty($_REQUEST['in_button_add'])) {

    // -----------------------------------------------------
    // Add an LDAP Entry
    // -----------------------------------------------------

    if (empty($in_cn)) {
        set_warn('Common Name is required');
    } else {

        // check for duplicates first

        $filter = '(&(objectclass=' . $CONF['oc_link'] . ")(cn=$in_cn))";
        $attrs = array ('cn');
        $cmd = 'KRB5CCNAME=' . $this_tgt . ' /usr/bin/macdir-pw-read'
           . ' --owner=' . $link_owner
           . ' --filter="' . $link_filter . '"'
           . ' --attrs=cn';
        perl_debug("Executing: $cmd");
        $ldap_json = shell_exec($cmd);
        $ret_cnt = 0;
        $entries = array();
        if (isset($ldap_json) && strlen($ldap_json) > 0) {
            perl_debug(strlen($ldap_json) . ' bytes returned');
            $entries = json_decode($ldap_json, true);
            foreach ($entries as $dn => $entry) {
                $this_dn = $dn;
                $ret_cnt++;
            }
        } else {
            perl_debug('0 bytes returned');
        }

        if ($ret_cnt>0) {
            $a_cn = $entries['cn'][0];
            set_warn("cn is already in use ($a_cn)");
            set_warn('Add of entry aborted');
        } else {

            // add the new entry
            set_ok("Adding = $in_cn");

            $ldap_av_list = '';
            foreach ($fld_list as $fld => $attr) {
                $val = stripslashes(trim($_REQUEST["in_$fld"]));
                if ($fld == $CONF['attr_cred'] && !empty($CONF['key'])) {
                    $val = $CONF['key_prefix'] . macdir_encode($val);
                }
                if (!empty($val)) {
                    if ($fld == $CONF['attr_cred']) {
                        set_ok("Adding $fld");
                    } else {
                        set_ok("Adding $fld = $val");
                    }
                    $ldap_av_list .= add_space($ldap_av_list);
                    $ldap_av_list .= "'$attr=$val'";
                }
            }

            foreach ($access_ids as $a) {
                $in_list = 'in_new_' . strtolower($a);
                $in_attr = $CONF["attr_link_${a}"];
                $a_user = trim(strtok($_REQUEST[$in_list], ','));
                while ($a_user) {
                    if (is_uid($a_user)) {
                        $ldap_av_list .= add_space($ldap_av_list);
                        $ldap_av_list .= "'$in_attr=$a_user'";
                        set_ok("Adding $in_attr = $a_user");
                    } else {
                        set_warn("ERROR: Invalid UID $a_uid");
                    }
                    $a_user = trim(strtok(','));
                }
            }
            $cmd = 'KRB5CCNAME=' . $this_tgt
               . ' /usr/bin/macdir-pw-update'
               . ' --owner=' . $link_owner
               . ' --update';
            $cmd .= " add $in_cn $ldap_av_list";
            if ($CONF['perl_debug']) {
                $cmd .= ' --debug';
            }
            perl_debug("Executing: $cmd");
            $return_text = shell_exec($cmd);
            if (preg_match('/ERROR/', $return_text)) {
                set_warn($return_text);
            }
            perl_debug($return_text);
        }
    }

} elseif (!empty($_REQUEST['in_button_update'])) {

    // -----------------------------------------------------
    // Update an LDAP Entry
    // -----------------------------------------------------

    if (empty($in_cn)) {
        set_warn('No entry to update');
        $ret_cnt = 0;
    } else {

        $cmd = 'KRB5CCNAME=' . $this_tgt . ' /usr/bin/macdir-pw-read'
           . ' --owner=' . $link_owner
           . ' --filter="' . $link_filter . '"';
        perl_debug("Executing: $cmd");
        $ldap_json = shell_exec($cmd);
        $ret_cnt   = 0;
        $info      = array();
        $entries   = array();
        $err_msg   = '';
        if (isset($ldap_json) && strlen($ldap_json) > 0) {
            perl_debug(strlen($ldap_json) . ' bytes returned');
            $entries = json_decode($ldap_json, true);
            foreach ($entries as $dn => $entry) {
                $this_dn = $dn;
                $ret_cnt++;
            }
        } else {
            perl_debug('0 bytes returned');
        }
        if ($ret_cnt == 1) {
            $entry_found = 1;
            $info = $entries[$this_dn];
        } elseif ($ret_cnt > 1) {
            set_warn("More than one entry found for $link_filter search.");
        } else {
            set_warn('No entry found.');
        }
    }
    if ($ret_cnt == 1) {
        $add_cnt      = 0;
        $ldap_av_list = '';

        foreach ($fld_list as $fld => $attr) {

            $val_in = '';
            $tmp = $_REQUEST["in_$fld"];

            if (!empty($tmp)) {
                $val_in  = stripslashes(trim($tmp));
            }
            if (!empty($val_in) && $attr == $CONF['attr_cred']) {
                $val_in = $CONF['key_prefix'] . macdir_encode($val_in);
            }

            $val_ldap = empty($info["$attr"][0])
                ? '' : trim($info["$attr"][0]);

            if ( $val_in != $val_ldap ) {
                if (empty($val_in)) {
                    if (!empty($val_ldap)) {
                        // delete the attribute
                        $ldap_av_list .= add_space($ldap_av_list);
                        $ldap_av_list .= "'$attr/$val_ldap'";
                        set_ok("$attr = $val_ldap deleted");
                    }
                } else {
                   // perform update
                   $new_data["$attr"] = $val_in;
                   $ldap_av_list .= add_space($ldap_av_list);
                   if ($val_ldap) {
                       $ldap_av_list .= "'${attr}/${val_ldap}'";
                       $ldap_av_list .= ' ';
                       set_ok("Deleting $attr = $val_ldap");
                   }
                   $ldap_av_list .= "'${attr}=${val_in}'";
                   set_ok("Adding $attr = $val_in");
                }
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
                    $ldap_av_list .= add_space($ldap_av_list);
                    $ldap_av_list .= "'$in_attr=$a_user'";
                    set_ok("Adding $in_attr = $a_user");
                } else {
                    set_warn("ERROR: Invalid UID $a_uid");
                }
                $a_user = trim(strtok(','));
            }
        }

        // -- perform the update
        if (strlen($ldap_av_list) > 0) {
            $cmd = 'KRB5CCNAME=' . $this_tgt
               . ' /usr/bin/macdir-pw-update'
               . ' --owner=' . $link_owner
               . ' --update';
            $cmd .= " update $in_cn $ldap_av_list";
            if ($CONF['perl_debug']) {
                $cmd .= ' --debug';
            }
            perl_debug("Executing $cmd");
            $update_text = shell_exec($cmd);
            if (preg_match('/ERROR/', $update_text)) {
                set_warn($update_text);
            }
            perl_debug($update_text);
        }
    }
} elseif (!empty($_REQUEST['in_button_delete'])) {

    // -----------------------------------------------------
    // Delete an LDAP Entry
    // -----------------------------------------------------

    $cmd = 'KRB5CCNAME=' . $this_tgt . ' /usr/bin/macdir-pw-update'
          . ' --owner=' . $link_owner
          . ' --update';
     if ($CONF['perl_debug']) {
         $cmd .= ' --debug';
     }
    $cmd .= " delete $in_cn";
    perl_debug("Executing: $cmd");
    
    $update_text = shell_exec($cmd);
    if (preg_match('/ERROR/', $update_text, $mat)) {
        set_warn($update_text);
    } else {
        set_ok($update_text);
    }
    perl_debug($update_text);

} else {

    set_warn('invalid action');

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
