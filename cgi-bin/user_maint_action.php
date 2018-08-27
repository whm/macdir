<?php
//
// ----------------------------------------------------------
//
// file: user_maint_action.php
// author: Bill MacAllister

require('inc_init.php');

// --------------------------------------------------------------
// Find a unique UID for use Linux

function get_an_id ($seed, $attr) {

    global $ds;
    global $CONF;

    if ($attr != 'gidnumber' && $attr != 'uidnumber') {
        $_SESSION['in_msg'] .= warn_html('Invalid call to get_an_id');
        return "INVALID ID REQUEST";
    }

    $found_it = 0;
    $this_id = $seed;
    while ($found_it == 0) {
        $a_filter = "(&(objectclass=posixaccount)($attr=$this_id))";
        $a_attrs  = array ($attr);
        $sr = @ldap_search ($ds, $CONF['ldap_base'], $a_filter, $a_attrs);
        $e = @ldap_get_entries($ds, $sr);
        $cnt = $e["count"];
        if ($cnt < 1) {
            $found_it = $this_id;
        } else {
            $this_id++;
        }
    }

    return $this_id;
}

//---------------------------------------------------------
// Routines to read XML passed from the form

function startElement($parser, $name, $attrs) {
    global $depth;
    global $current_tag;

    if ($depth==1) {
        $current_tag = $name;
    }
    $depth++;
}

function endElement($parser, $name) {
    global $depth;
    global $current_tag;
    global $formData;
    global $tags;

    if ($depth>2) {
        $t = 0;
        if (isset($tags['count'][$current_tag])) {
            $t = $tags['count'][$current_tag]+0;
        } else {
            $tags['count'][$current_tag] = 0;
        }
        $tags['data'][$current_tag][$t][$name] = $formData;
    } else {
        if (isset($tags['count'][$current_tag])) {
            $tags['count'][$current_tag]++;
        } else {
            $tags['count'][$current_tag] = 0;
        }
    }
    $formData = '';
    $depth--;
}

function characterData($parser, $data) {
    global $formData;
    $formData .= $data;
}

// --------------------------------------------------------------
// Update single value of multi-valued attribute

function multi_check($a_dn, $a_fld, $a_flag, $a_val) {

    GLOBAL $CON;
    global $ds;

    $attr_val[$a_fld] = $a_val;

    // search for it
    $a_filter = "(&(objectclass=person)($a_fld=$a_val))";
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
                $e =  $err - $err_msg;
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

// --------------------------------------------------------------
// Check to make sure a uid is either in the specified posix
// group, i.e. $a_flag>0, or is not in the group, i.e. $a_flag==0.

function posix_group_check ($a_uid, $a_flag, $a_group) {

    global $ds;
    global $CONF;

    $group_dn = "cn=$a_group," . $CONF['ldap_group_base'];
    $group_attr['memberuid'] = $a_uid;

    // search for it
    $posixFilter = "(&(objectclass=posixGroup)";
    $posixFilter .= "(cn=$a_group)";
    $posixFilter .= "(memberUid=$a_uid))";
    $posixReturn = array ('gidNumber','cn');
    $sr = @ldap_search ($ds, $CONF['ldap_base'], $posixFilter, $posixReturn);
    $posix = ldap_get_entries($ds, $sr);
    $posix_cnt = $posix["count"];

    if (empty($a_flag)) {

        // delete it if we find it
        if ($posix_cnt>0) {
            $r = @ldap_mod_del($ds, $group_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $_SESSION['in_msg'] .= ok_html("$a_uid removed from $a_group");
            if ($err>0) {
                $e = "$err - $err_msg";
                $_SESSION['in_msg']
                    .= warn_html(
                        "LDAP error removing $a_uid from $a_group: $e"
                    );
            }
        }

    } else {
        // add it if we don't
        if ($posix_cnt==0) {
            $r       = @ldap_mod_add($ds, $group_dn, $group_attr);
            $err     = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $_SESSION['in_msg'] .= ok_html("$a_uid added to $a_group");
            if ($err != 0) {
                $e = "$err - $err_msg";
                $_SESSION['in_msg']
                    .= warn_html("LDAP error add $a_uid to $a_group: $e");
            }
        }
    }
}

// --------------------------------------------------------------
// Check to make sure a uid is either in the specified application
// group, i.e. $a_flag>0, or is not in the group, i.e. $a_flag==0.

function app_group_check ($a_uid, $a_flag, $a_app) {

    global $ds;
    global $CONF;

    $group_attr['memberUid'] = $a_uid;

    // search for it
    $aFilter = "(&(objectclass=prideApplication)";
    $aFilter .= "(cn=$a_app)";
    $aFilter .= "(memberUid=$a_uid))";
    $aRetAttrs = array ('cn');
    $sr = @ldap_search ($ds, $CONF['ldap_base'], $aFilter, $aRetAttrs);
    $app = @ldap_get_entries($ds, $sr);
    $aCnt = $app["count"];

    if (strlen($a_flag)==0) {

        // delete it if we find it
        if ($aCnt>0) {
            $app_dn = $app[0]['dn'];
            $r = @ldap_mod_del($ds, $app_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err>0) {
                $e = "$err - $err_msg";
                $_SESSION['in_msg']
                    .= warn_html("LDAP error removing $a_uid from $a_app: $e");
            } else {
                $_SESSION['in_msg'] .= ok_html("$a_dn removed from $a_app");
            }
        }

    } else {

        // add it if we don't
        if ($aCnt==0) {
            $app_dn = "cn=$a_app," . $CONF['ldap_app_base'];
            $r = @ldap_mod_add($ds, $app_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err != 0) {
                $e = "$err - $err_msg";
                $_SESSION['in_msg']
                    .= warn_html("LDAP error add $a_uid to $app_dn: $e");
            } else {
                $_SESSION['in_msg'] .= ok_html("$a_uid added to $a_app");
            }
        }
    }
}

// -------------------------------
// find out if a kerberos principal exists

function find_kp ($uid) {

    global $CONF;

    $kp = '';

    // check to see if there is a kerberos principal
    $kcmd = $CONF['k5start']
        . ' -- /usr/bin/remctl ' . $CONF['kdcmaster'] . " kadmin examine $uid";
    $return_text = array();
    $ret_last = exec($kcmd, $return_text, $return_status);
    $pat = '/^Principal:\s+(.*)/';
    foreach ($return_text as $t) {
        if (preg_match($pat, $t, $mats)) {
            $kp = $mats[1];
            break;
        }
    }

    return $kp;

}

// -------------------------------
// Add a kerberos principal is there is not one

function kp_add ($uid) {

    global $CON;
    global $CONF;

    $kp = find_kp($uid);
    if (strlen($kp)==0) {
        // add the kerberos principal
        $tmppass = uniqid('usermaint');
        $kcmd = $CONF['k5start']
            . ' -- /usr/bin/remctl ' . $CONF['kdcmaster'] . ' kadmin'
            . " create $uid $tmppass enabled";
        $ret_last = exec($kcmd, $return_text, $return_status);
        if ($return_status) {
            $m = 'remctl error: ';
            foreach ($return_text as $t) {
                $m .= $t.'<br>';
            }
            $_SESSION['in_msg'] .= warn_html($m);
        } else {
            $_SESSION['in_msg'] .= ok_html("Added Kerberos principal $kp");
        }
    }
    return;
}

// -------------------------------
// Add a kerberos principal is there is not one

function kp_delete ($uid) {

    global $CON;
    global $CONF;

    $kp = find_kp($uid);
    if (strlen($kp)>0) {
        $_SESSION['in_msg'] .= ok_html("Deleting Kerberos principal");
        $kcmd = $CONF['k5start']
            . ' -- /usr/bin/remctl ' . $CONF['kdcmaster']
            . " kadmin delete $uid";
        $ret_last = exec($kcmd, $return_text, $return_status);
        if ($return_status) {
            $m = 'kerberos error: ';
            foreach ($return_text as $t) {
                $m .= $t.'<br>';
            }
            $_SESSION['in_msg'] .= warn_html($m);
        }
    }
    return;
}

// --------------------------------------------------------------
// Check user groups with entries not part of a persons base entry

function check_groups ($a_dn,
                       $a_uid,
                       $a_uidnumber,
                       $a_gidnumber,
                       $a_AppAddList,
                       $a_AppDelList) {

    global $ds;
    global $CONF;

    // check for PAM access stuctures

    // check applications groups
    if (strlen($a_uid)>0) {
        if (is_array($a_AppAddList)) {
            foreach ($a_AppAddList as $thisIdx => $thisApp) {
                app_group_check ($a_uid, "ADD", $thisApp);
            }
        }
        if (is_array($a_AppDelList)) {
            foreach ($a_AppDelList as $thisIdx => $thisApp) {
                app_group_check ($a_uid, "", $thisApp);
            }
        }
    }
    return;
}

# ------------------------------------------------------------
# Set global variables

function init_globals() {

    global $CONF;
    global $OUR;

    $OUR = array();
    $OUR['app_add_list']
        = empty($_REQUEST['in_appAddList'])
          ? array() : $_REQUEST['in_appAddList'];
    $OUR['app_del_list']
        = empty($_REQUEST['in_appDelList'])
          ? array() : $_REQUEST['in_appDelList'];
    $OUR['principal'] = $_REQUEST['in_uid'] . '@' . $CONF['krb_realm'];

    # This array describes the "simple" attributes.  That is attributes
    # that have a simple value.
    global $FLD_LIST;
    $FLD_LIST = array();
    array_push ($FLD_LIST, 'comments');
    array_push ($FLD_LIST, 'facsimiletelephonenumber');
    array_push ($FLD_LIST, 'gidnumber');
    array_push ($FLD_LIST, 'givenname');
    array_push ($FLD_LIST, 'homedirectory');
    array_push ($FLD_LIST, 'l');
    array_push ($FLD_LIST, 'loginshell');
    array_push ($FLD_LIST, 'mail');
    array_push ($FLD_LIST, 'maildepartment');
    array_push ($FLD_LIST, 'mobile');
    array_push ($FLD_LIST, 'nickname');
    array_push ($FLD_LIST, 'objectclass');
    array_push ($FLD_LIST, 'pager');
    array_push ($FLD_LIST, 'postaladdress');
    array_push ($FLD_LIST, 'postalcode');
    array_push ($FLD_LIST, 'siteresponsibility');
    array_push ($FLD_LIST, 'sn');
    array_push ($FLD_LIST, 'st');
    array_push ($FLD_LIST, 'telephonenumber');
    array_push ($FLD_LIST, 'title');
    array_push ($FLD_LIST, 'uid');
    array_push ($FLD_LIST, 'uidnumber');
    array_push ($FLD_LIST, 'workphone');

    return;
}

// -----------------------------------------------------
// Add an LDAP Entry

function add_ldap_entry($ds) {

    global $CON;
    global $CONF;
    global $FLD_LIST;
    global $OUR;

    if ( empty($_REQUEST['in_uid']) ) {
        $_SESSION['in_msg'] .= warn_html('UID is required');
        return;
    }
    if ( empty($_REQUEST['in_new_cn']) ) {
        $_SESSION['in_msg'] .= warn_html('Common Name is required');
        return;
    }

    // check for duplicates first
    $filter = '(uid=' . $_REQUEST['in_uid'] . ')';
    $attrs = array ('cn');
    $sr = @ldap_search ($ds, $CONF['ldap_base'], $filter, $attrs);
    $entries = @ldap_get_entries($ds, $sr);
    $uid_cnt = $entries["count"];
    if ($uid_cnt>0) {
        $a_cn = $entries[0]['cn'][0];
        $_SESSION['in_msg'] .= warn_html("UID is already in use by $a_cn");
        $_SESSION['in_msg'] .= warn_html('Add of user entry aborted');
        return;
    }

    // add the new entry
    $this_uidnumber = 0;
    $this_gidnumber = 0;
    $ldap_entry["objectclass"][] = 'top';
    $_SESSION['in_msg'] .= ok_html('Adding objectClass = top');
    $ldap_entry["objectclass"][] = 'person';
    $_SESSION['in_msg'] .= ok_html('Adding objectClass = person');
    $ldap_entry["objectclass"][] = 'czPerson';
    $_SESSION['in_msg'] .= ok_html('Adding objectClass = czPerson');
    $ldap_entry["objectclass"][] = 'pridePerson';
    $_SESSION['in_msg'] .= ok_html('Adding objectClass = pridePerson');
    $ldap_entry["objectclass"][] = $CON['krb_oc'];
    $_SESSION['in_msg'] .= ok_html('Adding objectClass = ' . $CON['krb_oc']);

    // Add kerberos principal name
    $ldap_entry[ $CON['krb_attr'] ][] = $OUR['principal'];
    $_SESSION['in_msg']
        .= ok_html('Adding ' . $CON['krb_attr'] . ' = ' . $OUR['principal']);

    // Create posix entry only when asked to
    $posix_entry = 0;
    if ( !empty($_REQUEST['in_linux_add']) ) {
        $posix_entry = 1;
    }

    foreach ($FLD_LIST as $fld) {
        $val = stripslashes(trim($_REQUEST["in_$fld"]));
        if (strlen($val)>0) {
            $_SESSION['in_msg'] .= ok_html("Adding $fld = $val");
            $ldap_entry[$fld][0] = $val;
            if ($fld=='uidnumber')     {
                $posix_entry = 1;
                $this_uidnumber = $val;
            }
            if ($fld=='gidnumber') {
                $posix_entry = 1;
                $this_gidnumber = $val;
            }
            if ($fld=='loginshell' || $fld=='homedirectory') {
                $posix_entry = 1;
            }
        }
    }

    // create cn entries
    $first_cn = $a_cn = strtok($_REQUEST['in_new_cn'], ',');
    while ( !empty($a_cn) ) {
        $_SESSION['in_msg'] .= ok_html("Adding cn = $a_cn");
        $ldap_entry["cn"][] = $a_cn;
        $a_cn = strtok(',');
    }

    // create mailDelivery entries
    $a_maildelivery = strtok($_REQUEST['in_new_maildelivery'], ',');
    while ( !empty($a_maildelivery) ) {
        $_SESSION['in_msg']
            .= ok_html("Adding mailDelivery = $a_maildelivery");
        $ldap_entry["mailDelivery"][] = $a_maildelivery;
        $a_maildelivery = strtok(',');
    }

    // see if we need posix objectclasses
    if ($posix_entry) {
        $ldap_entry['objectclass'][] = 'posixAccount';
        $_SESSION['in_msg'] .= ok_html('Adding objectClass = posixAccount');
        $ldap_entry['objectclass'][] = 'shadowAccount';
        $_SESSION['in_msg'] .= ok_html('Adding objectClass = shadowAccount');
        if ($this_uidnumber == 0) {
            $this_uidnumber = $CONF['ldap_uidnumber_base'];
        }
        $this_uidnumber = get_an_id($this_uidnumber, 'uidnumber');
        $ldap_entry["uidnumber"][] = $this_uidnumber;
        $_SESSION['in_msg'] .= ok_html("Adding uidNumber = $this_uidnumber");
        if ($this_gidnumber == 0) {
            $this_gidnumber = $this_uidnumber;
        }
        $this_gidnumber = get_an_id($this_gidnumber, 'gidnumber');
        $ldap_entry["gidnumber"][] = $this_gidnumber;
        $_SESSION['in_msg'] .= ok_html("adding gidNumber = $this_gidnumber");
    }

    // add data to directory
    $this_dn = 'uid=' . $_REQUEST['in_uid'] . ',' . $CONF['ldap_user_base'];
    if (@ldap_add($ds, $this_dn, $ldap_entry)) {
        $_SESSION['in_msg'] .= ok_html("Directory updated");
    } else {
        $_SESSION['in_msg']
            .= warn_html("Problem adding $this_dn to directory");
        $ldapErr = ldap_errno ($ds);
        $ldapMsg = ldap_error ($ds);
        $_SESSION['in_msg'] .= warn_html("Error: $ldapErr, $ldapMsg");
    }

    // check posix groups
    if ($this_uidnumber>0) {

        // posix group for this user maybe
        $posixFilter = "(&(objectclass=posixGroup)";
        $posixFilter .= '(cn=' . $_REQUEST['in_uid'] . ')';
        $posixReturn = array ('gidNumber', 'cn');
        $sr = @ldap_search (
            $ds,
            $CONF['ldap_group_base'],
            $posixFilter,
            $posixReturn
        );
        $posix = @ldap_get_entries($ds, $sr);
        $posix_cnt = $posix["count"];

        // create a posix group for this user
        if ($posix_cnt==0) {
            $posix_attrs['objectclass'][0] = 'top';
            $posix_attrs['objectclass'][1] = 'posixGroup';
            $posix_attrs['cn'][0]          = $_REQUEST['in_uid'];
            $posix_attrs['memberUid'][0]   = $_REQUEST['in_uid'];
            $posix_attrs['gidNumber'][0]   = $this_gidnumber;
            $posix_attrs['description'][0]
                = 'Posix Group for user ' . $REQUEST['in_uid'];
            $posix_dn
                = 'cn=' . $_REQUEST['in_uid'] . ',' . $CONF['ldap_groupbase'];
            $r = @ldap_add($ds, $posix_dn, $posix_attrs);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err == 0) {
                $_SESSION['in_msg'] .= ok_html("$posix_dn updated");
            } else {
                $_SESSION['in_msg']
                    .= warn_html("Problem adding $posix_dn to directory");
                $ldapErr = ldap_errno ($ds);
                $ldapMsg = ldap_error ($ds);
                $_SESSION['in_msg'] .= warn_html("Error: $ldapErr, $ldapMsg");
            }
        } else {
            posix_group_check ($_REQUEST['in_uid'],
            $_REQUEST['in_uid'],
            $_REQUEST['in_uid']);
        }

        // check the listed posix groups
        if ($tags['count']['posixgroup']>0) {
            if ($tags['count']['posixgroup']>0) {
                foreach ($tags['data']['posixgroup']
                as $idx => $this_posixgroup) {
                    $flag = '';
                    if ($this_posixgroup['checked'] == 'Y'
                    && $thisUIDNnumber>0)
                        {
                            $flag = 'Y';
                        }
                    posix_group_check(
                        $this_dn,
                        $flag,
                        $this_posixgroup['text']
                    );
                }
            }
        }

        // check for any other additions
        if ( !empty($_REQUEST['in_posix_new']) ) {
            $thisGroup = trim(strtok($_REQUEST['in_posix_new'], ','));
            while (strlen($thisGroup)>0) {
                posix_group_check ($in_uid, $thisGroup, $thisGroup);
                $thisGroup = trim(strtok(','));
            }
        }

        check_groups(
            $this_dn,
            $_REQUEST['in_uid'],
            $this_uidnumber,
            $this_gidnumber,
            $OUR['app_add_list'],
            $OUR['app_del_list']
        );
    }

    // add the kerberos principal
    kp_add($_REQUEST['in_uid']);

    // Check mailalias
    if ( !empty($_REQUEST['in_new_mailalias']) ) {
        $a_mailalias
            = trim(strtok($_REQUEST['in_new_mailalias'], ','));
        while ($a_mailalias) {
            multi_check($in_dn, 'mailAlias', $a_mailalias, $a_mailalias);
            $a_mailalias = trim(strtok(','));
        }
    }
    if ($_REQUEST['in_mailalias_cnt']>0) {
        for ($i=0; $i<$_REQUEST['in_mailalias_cnt']; $i++) {
            multi_check(
                $in_dn,
                'mailAlias',
                $_REQUEST["in_mailalias_$i"],
                $_REQUEST["in_mailalias_list_$i"]
            );
        }
    }

    // notify the administrator
    if ( !empty($CONF['manager_mailbox']) ) {
        $mail_msg .= strip_tags($_SESSION['in_msg']);
        $subj = 'New LDAP entry in ' . $CONF['ldap_title'];
        mail(
            $CONF['manager_mailbox'],
            $subj,
            $mail_msg
        );
    }

    return;
}

// -----------------------------------------------------
// Update an LDAP Entry

function update_ldap_entry($ds) {

    global $CON;
    global $CONF;
    global $FLD_LIST;
    global $OUR;

    $ldap_filter = 'objectclass=*';

    if ( empty($_REQUEST['in_dn']) ) {
        $_SESSION['in_msg'] .= warm_html("No entry to update");
        return;
    }
    $in_dn = $_REQUEST['in_dn'];

    $return_list   = $FLD_LIST;
    $return_list[] = $CON['krb_attr'];
    $sr = @ldap_read(
        $ds,
        $in_dn,
        $ldap_filter,
        $return_list
    );
    $info = @ldap_get_entries($ds, $sr);
    $err = ldap_errno ($ds);
    if ($err) {
        $err_msg = ldap_error ($ds);
        $_SESSION['in_msg'] .= "errors: $err $err_msg<br>\n";
    }
    $ret_cnt = $info["count"];
    if ($ret_cnt < 1) {
        $_SESSION['in_msg'] .= "Entry not found to update<br>\n";
        return;
    }

    $first_cn = '';
    if ( !empty($info[0]['cn'][0])) {
        $first_cn = trim($info[0]['cn'][0]);
    }
    $add_cnt     = 0;
    $posix_entry = 0;
    if ( !empty($_REQUEST['in_linux_add']) ) {
        $posix_entry = 1;
        $add_cnt++;
    }

    foreach ($FLD_LIST as $fld) {
        if ( $fld == 'objectclass' ) {
            continue;
        }

        $val_in = empty($_REQUEST["in_$fld"])
            ? '' : stripslashes(trim($_REQUEST["in_$fld"]));

        $val_ldap = empty($info[0]["$fld"][0])
            ? '' : trim($info[0]["$fld"][0]);

        if ( $val_in != $val_ldap ) {
            if ( empty($val_in) ) {
                if ( !empty($val_ldap) ) {
                    // delete the attribute
                    $new_data["$fld"] = $val_ldap;
                    $r = @ldap_mod_del($ds, $in_dn, $new_data);
                    $err = ldap_errno ($ds);
                    $err_msg = ldap_error ($ds);
                    $_SESSION['in_msg'] .= ok_html("$fld: $val_ldap deleted");
                    if ($err>0) {
                        $e = "$err - $err_msg";
                        $_SESSION['in_msg']
                            .= warn_html(
                                "ldap error deleting attribute $fld: $e"
                            );
                    }
                }
            } else {
                // try and replace it, if that fails try and add it
                $new_data["$fld"] = $val_in;
                // replace
                $r = @ldap_mod_replace(
                    $ds,
                    $_REQUEST['in_dn'],
                    $new_data
                );
                $err = ldap_errno ($ds);
                $err_msg = ldap_error ($ds);
                if ($err == 0) {
                    $_SESSION['in_msg'] .= ok_html("$fld replaced");
                } else {
                    if ($fld=='uidnumber') {
                        $this_uidnumber = $val_in;
                        $posix_entry = 1;
                    }
                    if ($fld=='gidnumber') {
                        $this_gidnumber = $val_in;
                        $posix_entry = 1;
                    }
                    if ($fld=='loginshell' || $fld=='homedirectory') {
                        $posix_entry = 1;
                    }
                    $add_cnt++;
                    $add_data["$fld"][] = $val_in;
                    $_SESSION['in_msg'] .= ok_html("$fld added");
                }
            }
        }
    }

    // -- Make sure every entry has a kerberos principal
    if ( empty($info[0][ $CON['krb_attr'] ][0]) ) {
        $krb_oc_add = 1;
        foreach ($info[0]['objectclass'] as $oc) {
            if ($oc==$CON['krb_oc']) {
                $krb_oc_add = 0;
            }
        }
        if ($krb_oc_add > 0) {
            $add_data['objectclass'][] = $CON['krb_oc'];
            $_SESSION['in_msg']
                .= ok_html('objectclass ' . $CON['krb_oc'] . ' added');
        }
        $add_data[ $CON['krb_attr'] ][] = $OUR['principal'];
        $_SESSION['in_msg']
            .= ok_html($CON['krb_attr'] . ' = ' . $OUR['principal'] . ' added');
        $add_cnt++;
    }

    // add the kerberos principal
    kp_add($_REQUEST['in_uid']);

    // -- add attributes
    if ($add_cnt>0) {

        // -- posix processing
        if ($posix_entry) {
            foreach ($info[0]['objectclass'] as $oc) {
                if ($oc=='posixaccount') {
                    $posix_entry = 0;
                }
            }
        }
        if ($posix_entry) {
            $add_data['objectclass'][] = 'posixAccount';
            $add_data['objectclass'][] = 'shadowAccount';
            $_SESSION['in_msg'] .= ok_html('posixAccount added');
            $_SESSION['in_msg'] .= ok_html('shadowAccount added');
            if (empty($this_uidnumber) || $this_uidnumber == 0) {
                $this_uidnumber = $CONF['ldap_uidnumber_base'];
            }
            $this_uidnumber = get_an_id($this_uidnumber, 'uidnumber');
            $add_data["uidnumber"][] = $this_uidnumber;
            $_SESSION['in_msg']
                .= ok_html("Adding uidNumber = $this_uidnumber");
            if (empty($this_gidnumber) || $this_gidnumber == 0) {
                $this_gidnumber = $this_uidnumber;
            }
            $this_gidnumber = get_an_id($this_gidnumber, 'gidnumber');
            $add_data["gidnumber"][] = $this_gidnumber;
            $_SESSION['in_msg']
                .= ok_html("Adding gidNumber = $this_gidnumber");
        }
    }

    // -- add the needed attributes
    $r = @ldap_mod_add($ds, $in_dn, $add_data);
    $err = ldap_errno ($ds);
    $err_msg = ldap_error ($ds);
    if ( $err != 0 ) {
        $e = "$err - $err_msg";
        $_SESSION['in_msg'] .= warn_html("LDAP error adding attributes: $e");
    }

    // Check mail aliases
    if ( !empty($_REQUEST['in_new_mailalias'])>0 ) {
        $a_mailalias = trim(strtok($_REQUEST['in_new_mailalias'], ','));
        while (strlen($a_mailalias)>0) {
            multi_check($in_dn, 'mailAlias', $a_mailalias, $a_mailalias);
            $a_mailalias = trim(strtok(','));
        }
    }
    if ( $_REQUEST['in_mailalias_cnt']>0 ) {
        for ($i=0; $i<$_REQUEST['in_mailalias_cnt']; $i++) {
            multi_check(
                $_REQUEST['in_dn'],
                'mailAlias',
                $_REQUEST["in_mailalias_$i"],
                $_REQUEST["in_mailalias_list_$i"]
            );
        }
    }

    // Check common name
    if ( !empty($_REQUEST['in_new_cn']) ) {
        $a_cn = stripslashes(trim(strtok($_REQUEST['in_new_cn'], ',')));
        while ( !empty($a_cn) ) {
            multi_check($_REQUEST['in_dn'], 'cn', $a_cn, $a_cn);
            $a_cn = stripslashes(trim(strtok(',')));
        }
    }
    if ( $_REQUEST['in_cn_cnt']>0 ) {
        for ($i=0; $i<$_REQUEST['in_cn_cnt']; $i++) {
            multi_check(
                $_REQUEST['in_dn'],
                'cn',
                stripslashes($_REQUEST["in_cn_$i"]),
                stripslashes($_REQUEST["in_cn_list_$i"])
            );
        }
    }

    // Check mailDelivery
    if ( !empty($_REQUEST['in_new_maildelivery']) ) {
        $a_maildelivery = strtok($_REQUEST['in_new_maildelivery'], ',');
        $a_maildelivery = stripslashes(trim($a_maildelivery));
        while (!empty($a_maildelivery)) {
            multi_check(
                $_REQUEST['in_dn'],
                'mailDelivery',
                $a_maildelivery,
                $a_maildelivery
            );
            $a_maildelivery = stripslashes(trim(strtok(',')));
        }
    }
    if ( $_REQUEST['in_maildelivery_cnt']>0 ) {
        for ($i=0; $i<$_REQUEST['in_maildelivery_cnt']; $i++) {
            multi_check(
                $_REQUEST['in_dn'],
                'mailDelivery',
                stripslashes($_REQUEST["in_maildelivery_$i"]),
                stripslashes($_REQUEST["in_maildelivery_list_$i"])
            );
        }
    }

    // check posix groups

    if ( !empty($_REQUEST['in_uidnumber']) ) {

        // posix group for this user maybe
        $posixFilter = '(&';
        $posixFilter .= '(objectclass=posixGroup)';
        $posixFilter .= '(cn=' . $_REQUEST['in_uid'] . ')';
        $posixFilter .= ')';
        $posixReturn = array ('gidNumber', 'cn');
        $sr = @ldap_search (
            $ds,
            $CONF['ldap_base'],
            $posixFilter,
            $posixReturn
        );
        $posix = @ldap_get_entries($ds, $sr);
        $posix_cnt = $posix["count"];

        // create a posix group for this user
        if ($posix_cnt==0) {
            $posix_attrs['objectclass'][0] = 'top';
            $posix_attrs['objectclass'][1] = 'posixGroup';
            $posix_attrs['cn'][0]          = $_REQUEST['in_uid'];
            $posix_attrs['memberUid'][0]   = $_REQUEST['in_uid'];
            $posix_attrs['gidNumber'][0]   = $this_gidnumber;
            $posix_attrs['description'][0] = "User's personal group";
            $posix_dn = "cn=$in_uid," . $CONF['ldap_group_base'];
            $r = @ldap_add($ds, $posix_dn, $posix_attrs);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err == 0) {
                $_SESSION['in_msg'] .= ok_html("$posix_dn updated");
            } else {
                $_SESSION['in_msg']
                    .= warn_html("Problem adding $posix_dn to directory");
                $ldapErr = ldap_errno ($ds);
                $ldapMsg = ldap_error ($ds);
                $_SESSION['in_msg'] .= warn_html("Error: $ldapErr, $ldapMsg");
            }
        } else {
            posix_group_check(
                $_REQUEST['in_uid'],
                $_REQUEST['in_uid'],
                $_REQUEST['in_uid']
            );
        }

        // check the listed posix groups
        if ( !empty($tags['count']['posixgroup']) ) {
            if ( $tags['count']['posixgroup']>0 ) {
                foreach (
                    $tags['data']['posixgroup'] as $idx => $this_posixgroup
                ) {
                    $flag = '';
                    if ($this_posixgroup['checked'] == 'Y'
                        & $this_uidnumber>0) {
                        $flag = 'Y';
                    }
                    posix_group_check (
                        $_REQUEST['in_uid'],
                        $flag,
                        $this_posixgroup['text']);
                }
            }
        }
        // check for any other additions
        if ( !empty($_REQUEST['in_posix_new']) ) {
            $thisGroup = trim(strtok($in_posix_new, ','));
            while ( !empty($thisGroup) ) {
                posix_group_check(
                    $_REQUEST['in_uid'],
                    $thisGroup,
                    $thisGroup
                );
                $thisGroup = trim(strtok(','));
            }
        }
    }

    check_groups(
        $_REQUEST['in_dn'],
        $_REQUEST['in_uid'],
        $_REQUEST['in_uidnumber'],
        $_REQUEST['in_gidnumber'],
        $OUR['app_add_list'],
        $OUR['app_del_list']
    );

    return;
}

// -----------------------------------------------------
// Delete an LDAP Entry
function delete_ldap_entry($ds) {

    global $CON;
    global $CONF;
    global $FLD_LIST;
    global $OUR;

    if ( empty($_REQUEST['in_dn']) ) {
        $_SESSION['in_msg'] .= warm_html("No entry to update");
        return;
    }

    // delete their posix group if they have one
    $del_dn = 'cn=' . $_REQUEST['in_uid'] . ',' . $CONF['ldap_groupbase'];
    $r = @ldap_delete($ds, $posix_dn);
    $err = ldap_errno ($ds);
    $err_msg = ldap_error ($ds);
    if ($err == 0) {
        $_SESSION['in_msg'] .= ok_html("$del_dn deleted");
    }

    // delete from other posix groups
    $pg_filter = '(&';
    $pg_filter .= '(objectclass=posixGroup)';
    $pg_filter .= '(memberUid=' . $_REQUEST['in_uid'] . ')';
    $pg_filter .= ')';
    $pg_attrs = array ('cn', 'description');
    $sr = @ldap_search ($ds, $CONF['ldap_base'], $pg_filter, $pg_attrs);
    $pg_group = @ldap_get_entries($ds, $sr);
    $pg_cnt = $pg_group["count"];

    if ($pg_cnt >0) {
        for ($pg_i=0; $pg_i<$pg_cnt; $pg_i++) {
            posix_group_check(
                $_REQUEST['in_uid'],
                '',
                $pg_group[$pg_i]['cn'][0]
            );
        }
    }

    // delete from app groups
    $pg_filter = '(&';
    $pg_filter .= '(objectclass=prideApplication)';
    $pg_filter .= '(memberUid=' . $_REQUEST['in_uid'] . ')';
    $pg_filter .= ')';
    $pg_attrs = array ('cn', 'description');
    $sr = @ldap_search ($ds, $CONF['ldap_base'], $pg_filter, $pg_attrs);
    $pg_group = @ldap_get_entries($ds, $sr);
    $pg_cnt = $pg_group["count"];

    if ($pg_cnt > 0) {
        for ($pg_i=0; $pg_i<$pg_cnt; $pg_i++) {
            app_group_check(
                $_REQUEST['in_uid'],
                '',
                $pg_group[$pg_i]['cn'][0]
            );
        }
    }

    // now delete the entry
    $r = @ldap_delete($ds, $_REQUEST['in_dn']);
    $err = ldap_errno ($ds);
    $err_msg = ldap_error ($ds);
    if ($err == 0) {
        $_SESSION['in_msg'] .= ok_html($_REQUEST['in_dn'] . " deleted)");
    } else {
        $E = "$err - $err_msg";
        $_SESSION['in_msg']
            .= warn_html('LDAP error deleting ' . $_REQUEST['in_dn'] . ": $e");
    }

    // delete the kerberos principal
    kp_delete($_REQUEST['in_uid']);

    return;
}

// -------------------------------------------------------------
// main routine

init_globals();

$ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');

// get a list of pam, posix, and application groups
require ('inc_groups.php');

// decode any xml data that is passed
$tags = array();
if (!empty($_REQUEST['in_xml_data'])) {
    // Set up the XML parser
    $xml_parser = xml_parser_create();
    xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
    xml_set_element_handler($xml_parser, "startElement", "endElement");
    xml_set_character_data_handler($xml_parser, "characterData");

    // Parse the data passed and then free the resouce
    if ( !xml_parse($xml_parser, $_REQUEST['in_xml_data'], true) ) {
        echo "XML error:"
            . xml_error_string(xml_get_error_code($xml_parser))
            . "<br>\n";
        exit;
    }
    xml_parser_free($xml_parser);
}

if (!empty($_REQUEST['in_button_add'])) {
    add_ldap_entry($ds);
} elseif ( !empty($_REQUEST['in_button_update']) ) {
    update_ldap_entry($ds);
} elseif ( !empty($_REQUEST['in_button_delete']) ) {
    delete_ldap_entry($ds);
} else {
    $_SESSION['in_msg'] .= warn_html('Invalid action');
}

$a_url = 'user_maint.php?in_uid=' . $_REQUEST['in_uid'];
header ("REFRESH: 0; URL=$a_url");
?>

<html>
<head>
<title>LDAP Directory Maintenance</title>
</head>
<body>
<a href="<?php echo $a_url;?>">Back to User Maintenance</a>
</body>
</html>
