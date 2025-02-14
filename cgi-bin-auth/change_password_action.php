<?php
//
// ----------------------------------------------------------
// File: change_password_action.php
// Author: Bill MacAllister
// Date: December 2002

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
// Set a kerberos password

function kp_pw ($uid, $pw) {

    global $CON;
    global $CONF;

    $kp = find_kp($uid);
    if (strlen($kp)>0) {
        // add the kerberos principal
        $kcmd = $CONF['k5start'] . ' -- /usr/bin/remctl ' . $CONF['kdcmaster']
            . " kadmin reset_passwd $uid $pw";
        $ret_last = exec($kcmd, $return_text, $return_status);
        if ($return_status) {
            $_SESSION['s_msg'] .= "<font $warn> remctl error: ";
            $msg = '';
            $br  = '';
            foreach ($return_text as $t) {
                $msg .= $br . $t;
                $br = '<br/>';
            }
            $_SESSION['s_msg'] .= warn_html($msg);
        } else {
            $_SESSION['s_msg'] .= ok_html('Kerberos password updated');
        }
    }
    return;
}

// -------------------------------
// update the passwords stored in the ldap directory

function ldap_set_password ($uid, $pwField, $newPw) {

    global $CON;
    global $CONF;

    $objClass = 'person';
    $newPw = '{crypt}'.crypt($newPw);

    // display errors ourselves, so we turn of auto-error reporting
    $old_ldap_error_level = error_reporting(E_ERROR | E_PARSE);

    // look for any user attributes for this object class
    $getAttrs = array();
    $ldapFilter = "(&(objectClass=$objClass)(uid=$uid))";
    $searchResult = ldap_search(
        $ds,
        $CONF['ldap_base'],
        $ldapFilter,
        $getAttrs
    );
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
                    $_SESSION['s_msg']
                        .= warn_html("Problem deleting $pwField from $ldapDN");
                    $ldapErr = ldap_errno ($ds);
                    $ldapMsg = ldap_error ($ds);
                    $_SESSION['s_msg']
                        .= warn_html("Error: $ldapErr, $ldapMsg");
                }
            }
        }

        $attrs = array();
        $attrs[$pwField] = $newPw;
        // Add the new password to the directory
        if (!ldap_mod_add($ds, $ldapDN, $attrs)) {
            $_SESSION['s_msg']
                .= warn_html("Problem adding $pwField for $ldapDN");
            $ldapErr = ldap_errno ($ds);
            $ldapMsg = ldap_error ($ds);
            $_SESSION['s_msg'] 
                .= want_html("Error: $ldapErr, $ldapMsg");
        } else {
            $_SESSION['s_msg'] .= ok_html("Password changed.");
        }
    }
    $junk = error_reporting($old_ldap_error_level);
}


// ----------------------------------------------------
// Main Routine

// initial directory connection and ldap base dn
require('inc_init.php');

$now = date ('Y-m-d H:i:s');
$in_date_last_maint = $now;
$in_uid = empty($_REQUEST['in_uid']) ? '' : $_REQUEST['in_uid'];

if ( !empty($in_button_update) ) {

    // bind anonymously and look up the dn to bind as
    $filter = "(&(objectclass=person)";
    $filter .= "(uid=$in_uid))";
    $return_attr = array('cn','dn');
    $ds = macdir_bind($CONF['ldap_server'], 'ANON');
    $sr = ldap_search($ds, $CONF['ldap_base'], $filter, $return_attr);  
    $info = ldap_get_entries($ds, $sr);
    if ($info["count"] == 0) {
        $_SESSION['s_msg']
            .= warn_html("Authentication failure for $in_uid");
    } else {
        $user_dn = $info[0]['dn'];
        // attempt to bind using the old password
        if ( !@ldap_bind($ds,$user_dn,$in_old_password) ) {
            $_SESSION['s_msg']
                .= warn_html('Failure to bind to ' . $CONF['ldap_server']
                    . "as $in_uid"
                );
            $_SESSION['s_msg'] .= warn_html('Password not changed');
        } else {
            $ds = $macdir_bind($CONF['ldap_server'], 'GSSAPI');
            ldap_set_password ($in_uid, 'userpassword', $in_new_password);
            // Essentially disable setting samba passwords, but leave the 
            // code here for a while.
            kp_pw($in_uid, $in_new_password);
        }
    }
}

header ("REFRESH: 0; URL=change_password.php?in_uid=$in_uid");

?>
