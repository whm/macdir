<?php

// File: inc_util.php
// Author: Bill MacAllister

// --------------------------------------------------------------
// Find a unique UID for Linux use

function make_UID ($seed) {
    
    global $ds, $ldap_base, $ok, $warn, $ef;
    
    // get the unique UID that is also a unique GID
    
    $found_it = 0;
    $this_uid = $seed;
    while ($found_it == 0) {
        $this_uid++;
        $a_filter = "(&(objectclass=posixaccount)(uidnumber=$this_uid))";
        $a_attrs = array ('uidnumber');
        $sr = @ldap_search ($ds, $ldap_base, $a_filter, $a_attrs);  
        $e = @ldap_get_entries($ds, $sr);
        $cnt = $e["count"];
        if ($cnt < 1) {
            $a_filter = "(&(objectclass=posixgroup)(gidnumber=$this_uid))";
            $a_attrs = array ('gidnumber');
            $sr = @ldap_search ($ds, $ldap_base, $a_filter, $a_attrs);  
            $e = @ldap_get_entries($ds, $sr);
            $cnt = $e["count"];
            if ($cnt < 1) {
                $found_it = $this_uid;
            }
        }
    }
    
    return $this_uid;
    
}

?>