<?php
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_date_last_maint  = $_REQUEST['in_date_last_maint'];
$in_new_password     = $_REQUEST['in_new_password'];
$in_uid              = $_REQUEST['in_uid'];
$in_button_update    = $_REQUEST['in_button_update'];
// ----------------------------------------------------------
//

// File: set_password_action.php
// Author: Bill MacAllister
// Date: 22-Oct-2001

// bind to the ldap directory
require('/etc/whm/macdir.php');
require('inc_bind.php');
$dirServer = macdir_bind($ldap_server, 'GSSAPI');

// -------------------------------
// find out if a kerberos principal exists

function find_kp ($uid) {

    global $k5start;
    global $kdcmaster;

    $kp = '';

    // check to see if there is a kerberos principal
    $kcmd = "$k5start -- /usr/bin/remctl $kdcmaster kadmin examine $uid";
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

    global $k5start;
    global $kdcmaster;
    global $ok;
    global $warn;

    $kp = find_kp($uid);
    if (strlen($kp)>0) {
        // add the kerberos principal
        $kcmd = "$k5start -- /usr/bin/remctl $kdcmaster "
            . "kadmin reset_passwd $uid $pw";
        $ret_last = exec($kcmd, $return_text, $return_status);
        if ($return_status) {
            $_SESSION['s_msg'] .= "<font $warn> remctl error: ";
            foreach ($return_text as $t) {
                $_SESSION['s_msg'] .= $t.'<br>';
            }
            $_SESSION['s_msg'] .= '</font>';
        } else {
            $_SESSION['s_msg'] 
                .= "<font $ok> Kerberos password updated</font><br>";
        }
    }
    return;
}

// -------------------------------
// update the passwords stored in the ldap directory

function ldap_set_password ($uid, $pwField, $newPw) {

    global $ldap_base;
    global $dirServer;
    global $ok;
    global $warn;

    $objClass = 'person';
    if ($pwField == 'sambantpassword' || $pwField == 'sambalmpassword') {
        $objClass = 'sambaSamAccount';
    } else {
        $newPw = '{crypt}'.crypt($newPw);
    }

    // look for any user attributes for this object class
    $getAttrs = array();
    $ldapFilter = "(&(objectClass=$objClass)(uid=$uid))";
    $searchResult = @ldap_search($dirServer, $ldap_base, $ldapFilter, $getAttrs);
    $ldapInfo = @ldap_get_entries($dirServer, $searchResult);
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
                if (!ldap_mod_del($dirServer, $ldapDN, $attrs)) {
                    $_SESSION['s_msg'] .= "<font $warn>Problem deleting "
                        . "$pwField $ldapDN</font><br>";
                    $ldapErr = ldap_errno ($dirServer);
                    $ldapMsg = ldap_error ($dirServer);
                    $_SESSION['s_msg'] .= "<font $warn>Error: $ldapErr, "
                        . "$ldapMsg</font><br>";
                }
            }
        }

        if (isset($newPw)) {
            // Add the new password to the directory
            $attrs = array();
            $attrs[$pwField] = $newPw;
            if (!ldap_mod_add($dirServer, $ldapDN, $attrs)) {
                $_SESSION['s_msg'] .= "<font $warn>Problem adding "
                    . "$pwField for $ldapDN</font><br>";
                $ldapErr = ldap_errno ($dirServer);
                $ldapMsg = ldap_error ($dirServer);
                $_SESSION['s_msg']
                    .= "<font $warn>Error: $ldapErr, $ldapMsg</font><br>";
            } else {
                $_SESSION['s_msg']
                    .= "<font $ok>$pwField changed.</font><br>";

            }
        }
    }

}


// ----------------------------------------------------
// Main Routine

$now = date ('Y-m-d H:i:s');
$in_date_last_maint = $now;

// No spaces allowed in the identifier
$in_uid = ereg_replace (" ","",$in_uid);

// set update message area
$_SESSION['s_msg'] = '';
$ok = 'color="#009900"';
$warn = 'color="#330000"';

if ( isset($in_button_update) ) {

    ldap_set_password ($in_uid, 'userpassword', $in_new_password);
    kp_pw ($in_uid, $in_new_password);

    # -- no samba passwords
    ldap_set_password ($in_uid, 'sambantpassword', '');
    ldap_set_password ($in_uid, 'sambalmpassword', '');

}

ldap_unbind($dirServer);

header ("REFRESH: 0; URL=set_password.php?in_uid=$in_uid");

?>
