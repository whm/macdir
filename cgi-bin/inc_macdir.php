<?php

// Get LDAP directory properties
macdirPROPS = '/etc/whm'.$_SERVER['HTTP_HOST'].'macdir.conf';
$fh = fopen(macdirPROPS, 'r');
$macdirLDAP = array();
if ($fh) {
    while (($line = fgets($fh, 512)) != false) {
        if (preg_match("/^\s*$/", $line))  { continue; }
        if (preg_match("/^\s*\#/", $line)) { continue; }
        if (preg_match("/\s*([\w\d\-_]+)\s*=\s*(\S+)/", $line, $mat)) {
            $macdirLDAP[$mat[1]] = $mat[2];
        }
    }
} else {
    die("ERROR: Problem reading $macdirPROPS\n");
}

$macdirDS = ldap_connect('ldap://'.$macdirDS['ldap_server']);
ldap_set_option($macdirDS, LDAP_OPT_PROTOCOL_VERSION, 3); 
if($macdirDS) {
    $r = ldap_bind($macdirDS);
} else {
    die("ERROR: Unable to connect to $ldap_server\n");
}

# Bind to the directory
if ( isset($macdirPROPS['ldap_tgt']) ) {
    putenv('KRB5CCNAME='.$macdirPROPS['ldap_tgt']);
    if (!ldap_sasl_bind($macdirDS,'','','GSSAPI')) {
        die("ERROR: GSSAPI bind to the directory failed\n");
    }
} elseif ( isset($macdirPROPS['ldap_bind_dn']) ) {
    if ( !ldap_bind($macdirDS,
                    $macdirPROPS['ldap_bind_dn'],
                    $macdirPROPS['ldap_bind_password']) ) {
        die("ERROR: Simple bind to the directory failed\n");
    }
}
  

// figure out the access level
$ldap_admin = $phone_admin = 0;
$this_user = '';
if ( isset($_SERVER['WEBAUTH_USER']) ) {
    $this_user = $_SERVER['WEBAUTH_USER'];
}
$menuLoggedIn = strlen($this_user);
if ($menuLoggedIn>0) {
    // Just set this to admin for now.  In the future there will be 
    // multiple levels of access to the directory.
    $ldap_admin = 1; 
    if ( isset($_SERVER['WEBAUTH_LDAP_GROUP']) ) {
        if ($_SERVER['WEBAUTH_LDAP_GROUP'] == 'ldap:admin') {
            $ldap_admin = 1;
        }
    }
}

// Make sure data from the directory displays correctly 
// by setting the character set.
header("Content-Type: text/html; charset=UTF-8");
?>