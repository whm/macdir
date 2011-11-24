<?php

// File: set_password_action.php
// Author: Bill MacAllister
// Date: 22-Oct-2001

// Open a session 
require('whm_php_sessions.inc');
// Enforce authentication
require('whm_php_auth.inc');
whm_auth("ldapadmin");

// bind to the ldap directory
require('/etc/whm/macdir_auth.php');
$dirServer = ldap_connect($ldap_server);
$ldapReturn = ldap_bind($dirServer, $ldap_manager, $ldap_password);

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
        // Uncomment this mostly for debugging.
        // $_SESSION['s_msg'] .= "<font $warn>Entry not found for:$ldapFilter<br>";
        
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
        
        if (strlen($newPw) > 0) {
            // Add the new password to the directory
            $attrs = array();
            $attrs[$pwField] = $newPw;
            if (!ldap_mod_add($dirServer, $ldapDN, $attrs)) {
                $_SESSION['s_msg'] .= "<font $warn>Problem adding "
                    . "$pwField for $ldapDN</font><br>";
                $ldapErr = ldap_errno ($dirServer);
                $ldapMsg = ldap_error ($dirServer);
                $_SESSION['s_msg'] .= "<font $warn>Error: $ldapErr, $ldapMsg</font><br>";
            } else {
                $_SESSION['s_msg'] .= "<font $ok>$pwField changed.</font><br>";
                
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

if ( strlen($btn_update)>0 ) {

    ldap_set_password ($in_uid, 'userpassword', $in_new_password);

    # -- no samba passwords for now
    $samba_cmd = "/mac/www/macdir/get-nt-pw.pl $in_new_password";
    $nt_lm_passwords = shell_exec($samba_cmd);
    $lmPwd = strtok($nt_lm_passwords,":");
    $ntPwd = substr(strtok(":"),0,32);
    ldap_set_password ($in_uid, 'sambantpassword', $ntPwd);

    # Do not set the lmpassword. It is too easy to decipher.
    # Well, set it for a little bit to get around a problem
    ldap_set_password ($in_uid, 'sambalmpassword', '');

}

ldap_unbind($dirServer);

header ("REFRESH: 0; URL=set_password.php?in_uid=$in_uid");

?>
