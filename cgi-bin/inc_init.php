<?php
#
# This include defines common used subroutines and performs some
# initialization.

##############################################################################
# Subroutines
##############################################################################

# --------------------------------------------------------------
# Add warning text to the session variable in_msg
function set_warn ($txt) {
    $htxt = warn_html($txt);
    if (array_key_exists('in_msg', $_SESSION)) {
        $_SESSION['in_msg'] .= $htxt;
    } else {
        $_SESSION['in_msg'] = $htxt;
    }
    return;
}

# --------------------------------------------------------------
# Add ok text to the session variable in_msg
function set_ok ($txt) {
    $htxt = ok_html($txt);
    if (array_key_exists('in_msg', $_SESSION)) {
        $_SESSION['in_msg'] .= $htxt;
    } else {
        $_SESSION['in_msg'] = $htxt;
    }
    return;
}

# --------------------------------------------------------------
# Display perl debugging messages
function perl_debug ($txt) {
    global $CONF;
    if (!$CONF['perl_debug']) {
        return;
    }
    set_warn($txt . '<br/>');
    return;
}

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
# If input string is zero length non-breakable white space
function nbsp_html ($txt) {
    $return_txt = empty(trim($txt)) ? '&nbsp;' : $txt;
    return $return_txt;
}

# --------------------------------------------------------------
# Test if a variable is set and if not return an empty string
function set_val ($txt) {
    $return_txt = empty(trim($txt)) ? '' : $txt;
    return $return_txt;
}

# --------------------------------------------------------------
# Test if a variable is set and if not return an empty string
function set_conf_val ($txt) {
    global $CONF;
    $return_txt = empty(trim($txt)) ? '' : $txt;
    return $return_txt;
}

# --------------------------------------------------------------
# Return the UID portion of a Kerberos principal
function krb_uid ($p = '') {
    if (isset($p) && strlen($p) > 0) {
        $princ = $p;
    } else {
        if (isset($_SERVER['REMOTE_USER'])) {
          $princ = $_SERVER['REMOTE_USER'];
        }
    }
    if (isset($princ)) {
        $return_uid = strtok($princ, '@');
    }
    return $return_uid;
}

# --------------------------------------------------------------
# Encode a string

function macdir_encode ($str) {
    global $CONF;
    $key        = $CONF['key'];
    $nonce      = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $en_str     = sodium_crypto_secretbox($str, $nonce, $key);
    $en_str_b64 = base64_encode($nonce . $en_str);
    return $en_str_b64;
}

# --------------------------------------------------------------
# Decode a string

function macdir_decode ($nonce_str_b64) {
    global $CONF;
    $key        = $CONF['key'];
    $nonce_str  = base64_decode($nonce_str_b64);
    $nonce      = mb_substr($nonce_str,
                            0,
                            SODIUM_CRYPTO_SECRETBOX_NONCEBYTES,
                            '8bit');
    $str        = mb_substr($nonce_str,
                            SODIUM_CRYPTO_SECRETBOX_NONCEBYTES,
                            null,
                            '8bit');
    $plain_text = sodium_crypto_secretbox_open($str, $nonce, $key);
    return $plain_text;
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
$CONF['perl_debug'] = isset($perl_debug)
    ? $perl_debug : '';
$CONF['manager_mailbox'] = isset($ldap_manager_mailbox)
    ? $ldap_manager_mailbox : 'bill@ca-zephyr.org';

$CONF['ldap_base'] = isset($ldap_base)
    ? $ldap_base : 'dc=example,dc=com';
$CONF['ldap_app_base'] = isset($ldap_app_base)
    ? $ldap_group_base : 'ou=applications,' . $CONF['ldap_base'];
$CONF['ldap_group_base'] = isset($ldap_group_base)
    ? $ldap_group_base : 'ou=groups,' . $CONF['ldap_base'];
$CONF['ldap_uidnumber_base'] = isset($ldap_uidnumber_base)
    ? $ldap_uidnumber_base : 4000;
$CONF['ldap_user_base'] = isset($ldap_user_base)
    ? $ldap_user_base : 'ou=people,' . $CONF['ldap_base'];
$CONF['ldap_server'] = isset($ldap_server)
    ? $ldap_server : '127.0.0.1';
$CONF['ldap_title'] = isset($ldap_title)
    ? $ldap_title : 'LDAP Directory';
$CONF['ldap_owner'] = isset($ldap_owner)
    ? $ldap_owner : 'mac@CA-ZEPHYR.ORG';

$CONF['k5start'] = isset($k5start)
    ? $k5start : '/usr/bin/k5start -f /etc/keytab/macdir.keytab -U';
$CONF['kdcmaster'] = isset($kdcmaster)
    ? $kdcmaster : 'portola.ca-zephyr.org';
$CONF['krb_realm'] = isset($krb_realm)
    ? $krb_realm : 'CA-ZEPHYR.ORG';

$CONF['key_file'] = isset($key_file)
    ? $key_file : '/etc/macdir/linkkey.txt';
$CONF['key_prefix'] = isset($key_prefix)
    ? $key_prefix : 'PREFIX:';

# Maintenance controls defining what fields/attributes to update.
$CONF['maint_address'] = isset($maint_address)
    ? $maint_address : 1;
$CONF['maint_app_groups'] = isset($maint_app_groups)
    ? $maint_app_groups : 0;
$CONF['maint_cell'] = isset($maint_cell)
    ? $maint_cell : 0;
$CONF['maint_comments'] = isset($maint_fax)
    ? $maint_comments : 1;
$CONF['maint_fax'] = isset($maint_fax)
    ? $maint_fax : 0;
$CONF['maint_linux'] = isset($maint_linux)
    ? $maint_linux : 1;
$CONF['maint_mail_acct'] = isset($maint_mail_acct)
    ? $maint_mail_acct : 0;
$CONF['maint_mail_addr'] = isset($maint_mail_addr)
    ? $maint_mail_addr : 1;
$CONF['maint_nickname'] = isset($maint_nickname)
    ? $maint_nickname : 0;
$CONF['maint_pager'] = isset($maint_pager)
    ? $maint_pager : 0;
$CONF['maint_phone'] = isset($maint_phone)
    ? $maint_phone : 1;
$CONF['maint_title'] = isset($maint_title)
    ? $maint_title : 0;
$CONF['maint_workphone']  = isset($maint_workphone)
    ? $maint_workphone : 1;

# Objectclasses
$CONF['oc_app'] = isset($oc_app)
    ? $oc_app : 'prideapplication';
$CONF['oc_person'] = isset($oc_person)
    ? $oc_person : 'pridePerson';
$CONF['oc_krb'] = isset($oc_krb)
    ? $oc_krb : 'krb5principal';
$CONF['oc_link'] = isset($oc_link)
    ? $oc_link : 'pridelistobject';

# Attributes
$CONF['attr_app'] = isset($attr_app)
    ? $attr_app : 'prideapplication';
$CONF['attr_comment'] = isset($attr_comment)
    ? $attr_comment : 'comments';
$CONF['attr_cred'] = isset($attr_cred)
    ? $attr_cred : 'pridecredential';
$CONF['attr_krb'] = isset($attr_krb)
    ? $attr_krb : 'krb5principalname';
$CONF['attr_link_read'] = isset($attr_link_read)
    ? $attr_link_read : 'pridereaduid';
$CONF['attr_link_uid'] = isset($attr_link_uid)
    ? $attr_link_uid : 'linkuid';
$CONF['attr_link_write'] = isset($attr_link_read)
    ? $attr_link_write : 'pridewriteuid';
$CONF['attr_link_url'] = isset($attr_link_url)
    ? $attr_link_url : 'prideurl';
$CONF['attr_link_visibility'] = isset($attr_link_visibility)
    ? $attr_link_visibility : 'prideurlprivate';
$CONF['attr_mailalias'] = isset($attr_mailalias)
    ? $attr_mailalias : 'mailalias';
$CONF['attr_maildelivery'] = isset($attr_maildelivery)
    ? $attr_maildelivery : 'maildelivery';
$CONF['attr_priv_group'] = isset($attr_priv_group)
    ? $attr_priv_group : 'czprivilegegroup';

# Controls for updating Cyrus IMAP.  Not tested in a long time and
# not documented.
$CONF['imap_host'] = isset($imap_host)
    ? $imap_host : '';
$CONF['imap_mgr_pass'] = isset($imap_mgr_pass)
    ? $imap_mgr_pass : '';
$CONF['imap_mgr_user'] = isset($imap_mgr_user)
    ? $imap_mgr_user : '';
$CONF['mail_domain'] = isset($mail_domain)
    ? $maildomain : '';
$CONF['mailbox_domain'] = isset($mailbox_domain)
    ? $mailbox_domain : '';

if (!empty($key)) {
  unset($key);
}
if (file_exists($CONF['key_file'])) {
    require($CONF['key_file']);
    if (!empty($key)) {
        $key_len = strlen($key);
        if ($key_len > SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            $key = left($key, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        } elseif ($key_len < SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            $_SESSION['in_msg']
                .= warn_html('Invalid key length.  Minimum length is '
                . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . '.  '
                . 'Key IGNORED.');
            unset($key);
       }
   }
}
$CONF['key'] = isset($key) ? $key : '';

// figure out the access level
$ldap_admin   = 0;
$phone_admin  = 0;
$this_user    = '';
if ( isset($_SERVER['REMOTE_USER']) ) {
    $this_user  = $_SERVER['REMOTE_USER'];
    if ($_SERVER['REMOTE_USER'] == $CONF['ldap_owner']) {
        $ldap_admin = 1;
    } else {
        $phone_admin = 1;
    }
    // Make the privilege state unambiguous
    if ($ldap_admin > 0) {
        $ldap_phone = 0;
    }
}
