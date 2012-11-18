<?php

// =======================================================================
// Utility routines
// =======================================================================

// --------------------------------------------------
// Format error messages

function macdir_msg($level, $msg) {
  $msg_display = $msg;
  if ($level == 'error') {
    $msg_display = '<span class="msgError">'.$msg.'</span>';
  } elseif ($level == 'warning') {
    $msg_display = '<span class="msgWarning">'.$msg.'</span>';
  } elseif ($level == 'okay') {
    $msg_display = '<span class="msgOkay">'.$msg.'</span>';
  }
  return $msg_display."<br>\n";
}

// =======================================================================
// Initialization
// =======================================================================

session_start();

// Get LDAP directory properties
$macdirPROPS = '/etc/whm/macdir.'.$_SERVER['HTTP_HOST'].'.conf';
$fh = fopen($macdirPROPS, 'r');
$macdirPROPS = array();
if ($fh) {
    while (($line = fgets($fh, 512)) != false) {
        if (preg_match("/^\s*$/", $line))  { continue; }
        if (preg_match("/^\s*\#/", $line)) { continue; }
        if (preg_match("/\s*([\w\d\-_]+)\s*=\s*(\S+)/", $line, $mat)) {
            $macdirPROPS[$mat[1]] = $mat[2];
        }
    }
} else {
    die("ERROR: Problem reading $macdirPROPS\n");
}

$macdirDS = ldap_connect('ldap://'.$macdirPROPS['ldap_server']);
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
?>