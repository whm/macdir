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
            die("ERROR: GSSAPI bind to $this_server failed.");
        }
    }
    return $ldap;
}

##############################################################################
# Main Init Routine
##############################################################################

# Start up a session so we can remember where we are
session_start();

if (file_exists('/etc/macdir/config.php')) {
    require('/etc/macdir/config.php');
}


$CONF = array();
$CONF['manager_mailbox'] = empty($ldap_manager_mailbox)
    ? 'bill@ca-zephyr.org' : $manager_mailbox;

$CONF['imap_host']      = empty($imap_host) ? '' : $imap_host;
$CONF['imap_mgr_pass']  = empty($imap_mgr_pass) ? '' : $imap_mgr_pass;
$CONF['imap_mgr_user']  = empty($imap_mgr_user) ? '' : $imap_mgr_user;
$CONF['mail_domain']    = empty($mail_domain) ? '' : $maildomain;
$CONF['mailbox_domain'] = empty($mailbox_domain) ? '' : $mailbox_domain;
$CONF['ldap_base'] = empty($ldap_base)
    ? 'dc=macallister,dc=grass-valley,dc=ca,dc=us' : $ldap_base;
$CONF['ldap_app_base'] = empty($ldap_app_base)
    ? 'ou=applications,' . $CONF['ldap_base'] : $ldap_group_base; 
$CONF['ldap_group_base'] = empty($ldap_group_base)
    ? 'ou=groups,' . $CONF['ldap_base'] : $ldap_group_base; 
$CONF['ldap_uidnumber_base'] = empty($ldap_uidnumber_base)
    ? 4000 : $ldap_uidnumber_base;
$CONF['ldap_user_base'] = empty($ldap_user_base)
    ? 'ou=people,' . $CONF['ldap_base'] : $ldap_user_base; 
$CONF['ldap_server'] = empty($ldap_server)
    ? 'macdir.ca-zephyr.org' : $ldap_server;
$CONF['ldap_title'] = empty($ldap_title)
    ? 'MacAllister Directory' : $ldap_title;

$CONF['k5start'] = empty($k5start)
    ? '/usr/bin/k5start -f /etc/keytab/macdir.keytab -U' : $k5start;
$CONF['kdcmaster'] = empty($kdcmaster) ? 'portola.ca-zephyr.org' : $kdcmaster;
$CONF['krb_realm'] = empty($krb_realm) ? 'CA-ZEPHYR.ORG' : $krb_realm;

$CON = array();
$CON['krb_oc']    = 'krb5Principal';
$CON['krb_attr']  = 'krb5principalname';

// figure out the access level
$ldap_admin   = 0;
$phone_admin  = 0;
$this_user    = '';
if ( isset($_SERVER['REMOTE_USER']) ) {
    $this_user  = $_SERVER['REMOTE_USER'];
    if (empty($_SERVER['WEBAUTH_LDAP_CZPRIVILEGEGROUP1'])) {
        if ($_SERVER['WEBAUTH_LDAP_CZPRIVILEGEGROUP']=='ldap:admin') {
            $ldap_admin = 1;
        }
        if ($_SERVER['WEBAUTH_LDAP_CZPRIVILEGEGROUP']=='ldap:phoneadmin') {
            $phone_admin = 1;
        }
    } else {
        $i = 1;
        while(!empty($_SERVER["WEBAUTH_LDAP_CZPRIVILEGEGROUP$i"])) {
            if ($_SERVER["WEBAUTH_LDAP_CZPRIVILEGEGROUP$i"]=='ldap:admin') {
                $ldap_admin = 1;
            }
            if ($_SERVER["WEBAUTH_LDAP_CZPRIVILEGEGROUP$i"]=='ldap:phoneadmin')
            {
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

