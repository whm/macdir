<?php

// File: inc_pwset.php
// Author: Bill MacAllister
// Date: 22-Oct-2001

// -------------------------------
// update the passwords stored in the ldap directory

function ldap_set_password ($uid, $pwField, $newPw, $cryptFlag) {

    global $ldap_base;
    global $ds;
    global $ok;
    global $warn;
    global $ef;
    
    $objFilter = '(|(objectclass=person)(objectclass=posixaccount))';
    $lc_pwField = strtolower($pwField);
    $fld_delete[] = $lc_pwField;

    if ($pwField == 'sambantpassword' || $pwField == 'sambalmpassword') {
        $objFilter = '(objectclass=sambaSamAccount)';
        $samba_change_time = time();
        $fld_delete[] = 'sambapwdlastset';
    } elseif ($pwField == 'ntpassword' || $pwField == 'lmpassword') {
        $objFilter = '(objectclass=sambaAccount)';
    }
    
    if (strlen($cryptFlag)>0) {
        $newPw = '{crypt}'.crypt($newPw);
    }
    
    // look for any user attributes for this object class
    $getAttrs = array();
    $ldapFilter = "(&$objFilter(uid=$uid))";
    $searchResult = @ldap_search($ds, 
                                 $ldap_base, 
                                 $ldapFilter, 
                                 $getAttrs);
    $ldapInfo = @ldap_get_entries($ds, $searchResult);
    if ($ldapInfo["count"] == 0) {
        
        // Ooops, can't find them for some reason.
        // Generally this is okay because they don't have complete access
        // to all systems and thus might not have the required objectclass.
        // Uncomment this mostly for debugging.
        // $_SESSION['s_msg'] .= "$warn Entry not found:$ldapFilter$ef";
        
    } else {
        
        $ldapDN = $ldapInfo[0]['dn'];
        $_SESSION['s_msg'] .= "$ok updating $ldapDN$ef";
        for ($i=0; $i<$ldapInfo["count"]; $i++) {
            foreach ($fld_delete as $this_fld) {
                for ($j=0; $j<$ldapInfo[$i][$this_fld]["count"]; $j++) {
                    // delete the old value
                    $oldval = $ldapInfo[$i][$this_fld][$j];
                    $attrs = array();
                    $attrs[$this_fld] = $oldval;
                    if (!@ldap_mod_del($ds, $ldapDN, $attrs)) {
                        $_SESSION['s_msg'] .= "$warn Problem deleting "
                             . "$this_fld $ldapDN$ef";
                        $ldapErr = ldap_errno ($ds);
                        $ldapMsg = ldap_error ($ds);
                        $_SESSION['s_msg'] .= "$warn Error: $ldapErr, "
                             . "$ldapMsg$ef";
                    }
                }
            }
        }
        
        if (strlen($newPw) > 0) {
            $attrs = array();
            $attrs[$pwField] = $newPw;
            if ($samba_change_time > 0) {
                $attrs['sambapwdlastset'] = $samba_change_time;
            }
            // Add the new password to the directory
            if (!@ldap_mod_add($ds, $ldapDN, $attrs)) {
                $_SESSION['s_msg'] .= "$warn Problem adding "
                     . "$pwField for $ldapDN $ef";
                $ldapErr = ldap_errno ($ds);
                $ldapMsg = ldap_error ($ds);
                $_SESSION['s_msg'] .= "$warn Error: "
                     . "$ldapErr, $ldapMsg$ef";
            } else {
                $_SESSION['s_msg'] .= "$ok $pwField changed.$ef";
                
            }
        }
    }
    
}

?>