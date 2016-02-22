<?php
#
# This include defines common used subroutines and performs some
# initialization.

##############################################################################
# Subroutines
##############################################################################

# --------------------------------------------------------------
# Format warning HTML
function warn_html ($txt) {
    return '<font color="#cc0000">' . $txt . "</font><br>\n";
}

# --------------------------------------------------------------
# Format ok HTML
function ok_html ($txt) {
    return '<font color="#00cc00">' . $txt . "</font><br>\n";
}

# --------------------------------------------------------------
# Bind to the directory and die if there is an error

function macdir_bind ($this_server, $bind_type) {
    
    # Bind to the directory Server
    $ldap = ldap_connect("ldap://$this_server");
    if($ldap) {
        $r = ldap_bind($ldap);
    } else {
        die("ERROR: Unable to connect to $this_server!");
    }
    # Set an option
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    # Attempt a GSSAPI bind only if requested and the user has
    # authenticated.
    if ($bind_type == 'GSSAPI' && isset($_SERVER['REMOTE_USER'])) {
        $r = ldap_sasl_bind($ldap,"","","GSSAPI");
        if (!isset($r)) {
            die("ERROR: GSSAPI bind to $ldap_server failed.");
        }
    }
    return $ldap;
}

##############################################################################
# Main Init Routine
##############################################################################

# Start up a session so we can remember where we are
session_start();

$CONF_use_samba = 0;

$CONF_krb_realm      = 'CA-ZEPHYR.ORG';

$CONF_imap_host      = 'imap.ca-zephyr.org';
$CONF_mail_domain    = 'ca-zephyr.org';
$CONF_mailbox_domain = 'imap.ca-zephyr.org';

$CONF_ldap_manager_mailbox = 'bill@ca-zephyr.org';

# Get configuration for this instance
require('/etc/whm/macdir.php');

$page_dir  = dirname($_SERVER['SCRIPT_FILENAME']);
$page_name = substr($_SERVER['SCRIPT_FILENAME'], strlen($page_dir));
$page_root = strtok($page_name,'.');
if (substr($page_root,0,1)=='/') {
    $page_root = substr($page_root,1);
}

// figure out the access level
$ldap_admin   = 0;
$phone_admin  = 0;
$this_user    = '';
if ( isset($_SERVER['REMOTE_USER']) ) {
    $this_user  = $_SERVER['REMOTE_USER'];
    if (empty($_SERVER['WEBAUTH_LDAP_CZPRIVILGEGROUP1'])) {
        if ($_SERVER['WEBAUTH_LDAP_CZPRIVILGEGROUP']=='ldap:admin') {
            $ldap_admin = 1;
        }
        if ($_SERVER['WEBAUTH_LDAP_CZPRIVILGEGROUP']=='ldap:phoneadmin') {
            $phone_admin = 1;
        }
    } else {
        $i = 1;
        while(!empty($_SERVER["WEBAUTH_LDAP_CZPRIVILGEGROUP$i"])) {
            if ($_SERVER["WEBAUTH_LDAP_CZPRIVILGEGROUP$i"]=='ldap:admin') {
                $ldap_admin = 1;
            }
            if ($_SERVER["WEBAUTH_LDAP_CZPRIVILGEGROUP$i"]=='ldap:phoneadmin') {
                $phone_admin = 1;
            }
            $i++;
            if ($ldap_admin==1 && $phone_admin==1) {
                break;
            }
            if ($i>32767) {
                exit('Problem setting admin privs');
            }
        }
    }
}

