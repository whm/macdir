<?php
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_button_add        = $_REQUEST['in_button_add'];
$in_button_update     = $_REQUEST['in_button_update'];
$in_button_delete     = $_REQUEST['in_button_delete'];
$in_posix_new         = $_REQUEST['in_posix_new'];
$in_cn                = $_REQUEST['in_cn'];
$in_cn_cnt            = $_REQUEST['in_cn_cnt'];
$in_cn_list           = $_REQUEST['in_cn_list'];
$in_dn                = $_REQUEST['in_dn'];
$in_gidnumber         = $_REQUEST['in_gidnumber'];
$in_linux_add         = $_REQUEST['in_linux_add'];
$in_mailalias_cnt     = $_REQUEST['in_mailalias_cnt'];
$in_maildelivery      = $_REQUEST['in_maildelivery'];
$in_maildelivery_cnt  = $_REQUEST['in_maildelivery_cnt'];
$in_maildelivery_list = $_REQUEST['in_maildelivery_list'];
$in_new_cn            = $_REQUEST['in_new_cn'];
$in_new_mailalias     = $_REQUEST['in_new_mailalias'];
$in_new_maildelivery  = $_REQUEST['in_new_maildelivery'];
$in_uid               = $_REQUEST['in_uid'];
$in_uidnumber         = $_REQUEST['in_uidnumber'];
$in_xml_data          = $_REQUEST['in_xml_data'];
// ----------------------------------------------------------
//
// file: user_maint_action.php
// author: Bill MacAllister

session_start();
require('inc_config.php');
require ('/etc/whm/macdir_auth.php');

// --------------------------------------------------------------
// Find a unique UID for use Linux

function make_UID ($seed) {

    global $ds, $ldap_base, $ok, $warn, $ef;

    $found_it = 0;
    $this_uid = $seed + 1;
    while ($found_it == 0) {
        $a_filter = "(&(objectclass=posixaccount)(uidnumber=$this_uid))";
        $a_attrs = array ('uidnumber');
        $sr = @ldap_search ($ds, $ldap_base, $a_filter, $a_attrs);
        $e = @ldap_get_entries($ds, $sr);
        $cnt = $e["count"];
        if ($cnt < 1) {
            $found_it = $this_uid;
        } else {
            $this_uid++;
        }
    }

    return $this_uid;

}

// --------------------------------------------------------------
// mailbox_check

function mailbox_check ($in_uid, $addr, $local_domain) {

    global $ok;
    global $warn;
    global $CONF_imap_host;
    global $imap_muser;
    global $imap_mpass;

    // Don't check a mailbox if there is none
    if (strlen($impa_host) == 0) {return;}

    $CONF_imap_host_perl = '{'.$CONF_imap_host.':143}';
    $user_mbx = 'user/'.$in_uid;
    $imap_mbx = $CONF_imap_host_perl.$user_mbx;
    $acl_mbx = "user/$in_uid".'%';
    if ( !($imapCnx = imap_open($CONF_imap_host_perl,
                                $imap_muser,
                                $imap_mpass,
                                OP_HALFOPEN)) ) {
        $_SESSION['in_msg'] .= "$warn IMAP Connection failure ("
            . imap_last_error() . ")$ef";
    } else {

        $mbx_exists = 0;
        $mbxList = imap_list($imapCnx, $CONF_imap_host_perl, $user_mbx);
        if ( is_array($mbxList) ) {$mbx_exists = 1;}
        $pat = '/@'.$local_domain.'/i';
        if ( preg_match ($pat, $addr, $mat) ) {

            if ($mbx_exists == 0) {
                // add a mailbox
                if ( !(imap_createmailbox($imapCnx, $imap_mbx)) ) {
                    $_SESSION['in_msg']
                        .= "$warn IMAP Create mailbox failure ("
                        . imap_last_error() . ")$ef";
                } else {
                    $_SESSION['in_msg']
                        .= "$ok IMAP mailbox created</font><br>";
                }
            }
            imap_setacl ($imapCnx, $acl_mbx, $in_uid,     'lrswipcda');
            imap_setacl ($imapCnx, $acl_mbx, $imap_muser, 'lrswipcda');

        } else {

            if ($mbx_exists > 0) {

                // remove access to the mailbox
                imap_setacl ($imapCnx, $acl_mbx, $in_uid, "");

            }
        }
    }

    imap_close($imapCnx);
}

//---------------------------------------------------------
// Routines to read XML passed from the form

function startElement($parser, $name, $attrs) {
    global $depth;
    global $current_tag;

    if ($depth==1) {
        $current_tag = $name;
    }
    $depth++;
}

function endElement($parser, $name) {
    global $depth;
    global $current_tag;
    global $formData;
    global $tags;

    if ($depth>2) {
        $t = 0;
        if (isset($tags['count'][$current_tag])) {
            $t = $tags['count'][$current_tag]+0;
        } else {
            $tags['count'][$current_tag] = 0;
        }
        $tags['data'][$current_tag][$t][$name] = $formData;
    } else {
        if (isset($tags['count'][$current_tag])) {
            $tags['count'][$current_tag]++;
        } else {
            $tags['count'][$current_tag] = 0;
        }
    }
    $formData = '';
    $depth--;
}

function characterData($parser, $data) {
    global $formData;
    $formData .= $data;
}

// --------------------------------------------------------------
// Update common names as required

function common_name_check ($a_dn, $a_flag, $a_cn) {

    global $ds, $ok, $warn, $ef;

    $cn_attr['cn'] = $a_cn;

    // search for it
    $aFilter = "(&(objectclass=person)(cn=".$a_cn."))";
    $aReturn = array ('cn');
    $sr = @ldap_read ($ds, $a_dn, $aFilter, $aReturn);
    $cnEntry = ldap_get_entries($ds, $sr);
    $cn_cnt = $cnEntry["count"];

    if (strlen($a_flag)==0) {

        $_SESSION['in_msg'] .= "cn_cnt $cn_cnt<br>\n";

        // delete it if we find it
        if ($cn_cnt>0) {
            $r = @ldap_mod_del($ds, $a_dn, $cn_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err>0) {
                $_SESSION['in_msg'] .= "$warn ldap error removing $a_cn "
                    . "from $a_dn: $err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg'] .= "$ok Common Name $a_cn removed "
                    . "from $a_dn.$ef";
            }
        }

    } else {
        // add it if we don't have it
        if ($cn_cnt==0) {
            $r = @ldap_mod_add($ds, $a_dn, $cn_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err != 0) {
                $_SESSION['in_msg']
                    .= "$warn ldap error adding $a_cn to $a_dn: "
                    . "$err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg']
                    .= "$ok Common Name $a_cn added to $a_dn.$ef";
            }
        }
    }
}

// --------------------------------------------------------------
// Update mailDelivery as required

function maildelivery_check ($a_dn, $a_flag, $a_maildelivery) {

    global $ds, $ok, $warn, $ef;

    $maildelivery_attr['maildelivery'] = $a_maildelivery;

    // search for it
    $aFilter = "(&(objectclass=person)(maildelivery=".$a_maildelivery."))";
    $aReturn = array ('maildelivery');
    $sr = @ldap_read ($ds, $a_dn, $aFilter, $aReturn);
    $maildeliveryEntry = ldap_get_entries($ds, $sr);
    $maildelivery_cnt = $maildeliveryEntry["count"];

    if (strlen($a_flag)==0) {

        $_SESSION['in_msg'] .= "maildelivery_cnt $maildelivery_cnt<br>\n";

        // delete it if we find it
        if ($maildelivery_cnt>0) {
            $r = @ldap_mod_del($ds, $a_dn, $maildelivery_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err>0) {
                $_SESSION['in_msg']
                    .= "$warn ldap error removing $a_maildelivery "
                    . "from $a_dn: $err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg']
                    .= "$ok Common Name $a_maildelivery removed "
                    . "from $a_dn.$ef";
            }
        }

    } else {
        // add it if we don't have it
        if ($maildelivery_cnt==0) {
            $r = @ldap_mod_add($ds, $a_dn, $maildelivery_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err != 0) {
                $_SESSION['in_msg']
                    .= "$warn ldap error adding $a_maildelivery to $a_dn: "
                    . "$err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg']
                    .= "$ok Common Name $a_maildelivery added to $a_dn.$ef";
            }
        }
    }
}

// --------------------------------------------------------------
// Check to make sure a uid is either in the specified posix
// group, i.e. $a_flag>0, or is not in the group, i.e. $a_flag==0.

function posix_group_check ($a_uid, $a_flag, $a_group) {

    global $ds, $ldap_base, $ldap_groupbase, $ok, $warn, $ef;

    $group_dn = "cn=$a_group,$ldap_groupbase";
    $group_attr['memberuid'] = $a_uid;

    // search for it
    $posixFilter = "(&(objectclass=posixGroup)";
    $posixFilter .= "(cn=$a_group)";
    $posixFilter .= "(memberUid=$a_uid))";
    $posixReturn = array ('gidNumber','cn');
    $sr = @ldap_search ($ds, $ldap_base, $posixFilter, $posixReturn);
    $posix = ldap_get_entries($ds, $sr);
    $posix_cnt = $posix["count"];

    if (strlen($a_flag)==0) {

        // delete it if we find it
        if ($posix_cnt>0) {
            $r = @ldap_mod_del($ds, $group_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $_SESSION['in_msg'] .= "$ok $a_uid removed from $a_group.$ef";
            if ($err>0) {
                $_SESSION['in_msg'] .= "$warn ldap error removing $a_uid "
                    . "from $a_group: $err - $err_msg.$ef";
            }
        }

    } else {
        // add it if we don't
        if ($posix_cnt==0) {
            $r = @ldap_mod_add($ds, $group_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $_SESSION['in_msg'] .= "$ok $a_uid added to $a_group.$ef";
            if ($err != 0) {
                $_SESSION['in_msg']
                    .= "$warn ldap error add $a_uid to $a_group: "
                    . "$err - $err_msg.$ef";
            }
        }
    }
}

// --------------------------------------------------------------
// Check to make sure a uid is either in the specified pam
// group, i.e. $a_flag>0, or is not in the group, i.e. $a_flag==0.

function pam_group_check ($a_dn, $a_flag, $a_group) {

    global $ds, $ldap_base, $ldap_groupbase, $ok, $warn, $ef;

    $group_dn = "cn=$a_group,$ldap_groupbase";
    $group_attr['memberdn'] = $a_dn;

    // search for it
    $pamFilter = "(&(objectclass=pamGroup)";
    $pamFilter .= "(cn=$a_group)";
    $pamFilter .= "(memberDN=$a_dn))";
    $pamReturn = array ('cn');
    $sr = @ldap_search ($ds, $ldap_base, $pamFilter, $pamReturn);
    $pam = @ldap_get_entries($ds, $sr);
    $pam_cnt = $pam["count"];

    if (strlen($a_flag)==0) {

        // delete it if we find it
        if ($pam_cnt>0) {
            $group_dn = $pam[0]['dn'];
            $r = @ldap_mod_del($ds, $group_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $_SESSION['in_msg'] .= "$ok $a_dn removed from $a_group.$ef";
            if ($err>0) {
                $_SESSION['in_msg'] .= "$warn ldap error removing $a_dn "
                    . "from $a_group: $err - $err_msg.$ef";
            }
        }

    } else {

        // add it if we don't
        if ($pam_cnt==0) {
            $r = @ldap_mod_add($ds, $group_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $_SESSION['in_msg'] .= "$ok $a_dn added to $a_group.$ef";
            if ($err != 0) {
                $_SESSION['in_msg']
                    .= "$warn ldap error add $a_dn to $a_group: "
                    . "$err - $err_msg.$ef";
            }
        }
    }
}

// --------------------------------------------------------------
// Check to make sure a uid is either in the specified application
// group, i.e. $a_flag>0, or is not in the group, i.e. $a_flag==0.

function app_group_check ($a_uid, $a_flag, $a_app) {

    global $ds, $ldap_base, $ok, $warn, $ef;

    $group_attr['memberUid'] = $a_uid;

    // search for it
    $aFilter = "(&(objectclass=prideApplication)";
    $aFilter .= "(cn=$a_app)";
    $aFilter .= "(memberUid=$a_uid))";
    $aRetAttrs = array ('cn');
    $sr = @ldap_search ($ds, $ldap_base, $aFilter, $aRetAttrs);
    $app = @ldap_get_entries($ds, $sr);
    $aCnt = $app["count"];

    if (strlen($a_flag)==0) {

        // delete it if we find it
        if ($aCnt>0) {
            $app_dn = $app[0]['dn'];
            $r = @ldap_mod_del($ds, $app_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err>0) {
                $_SESSION['in_msg'] .= "$warn ldap error removing $a_uid "
                    . "from $a_app: $err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg'] .= "$ok $a_dn removed from $a_app.$ef";
            }
        }

    } else {

        // add it if we don't
        if ($aCnt==0) {
            $app_dn = "cn=$a_app,ou=applications,$ldap_base";
            $r = @ldap_mod_add($ds, $app_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err != 0) {
                $_SESSION['in_msg']
                    .= "$warn ldap error add $a_uid to $app_dn: "
                    . "$err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg'] .= "$ok $a_uid added to $a_app.$ef";
            }
        }
    }
}

// --------------------------------------------------------------
// Check to make sure the dn has a mailAlias for this smtp alias

function mail_alias_check ($a_dn, $a_flag, $a_alias) {

    global $ds, $ldap_base, $CONF_mail_domain, $ok, $warn, $ef;

    $add_alias = $a_alias;
    if ( strlen($a_alias) == strlen(str_replace('@','',$a_alias)) ) {
        $add_alias .= '@' . $CONF_mail_domain;
    }

    // search for attributes values to delete
    $aFilter = "mailAlias=$a_alias";
    $aRetAttrs = array ('cn');
    $sr = @ldap_read ($ds, $a_dn, $aFilter, $aRetAttrs);
    $ml_entry = @ldap_get_entries($ds, $sr);
    $aCnt = $ml_entry["count"];

    // delete it if we find it
    if (strlen($a_flag)==0 || $add_alias != $a_alias) {
        if ($aCnt>0) {
            $group_attr['mailalias'] = $a_alias;
            $r = @ldap_mod_del($ds, $a_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err>0) {
                $_SESSION['in_msg']
                    .= "$warn ldap error removing alias $a_alias "
                    . "from $a_dn: $err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg'] .= "$ok Mail Alias $a_alias removed "
                    . "from $a_dn.$ef";
            }
        }
    }

    // search for attributes values to add
    $aFilter = "mailAlias=$add_alias";
    $aRetAttrs = array ('cn');
    $sr = @ldap_read ($ds, $a_dn, $aFilter, $aRetAttrs);
    $ml_entry = @ldap_get_entries($ds, $sr);
    $aCnt = $ml_entry["count"];

    // add it if we need to
    if (strlen($a_flag)>0 || $add_alias != $a_alias) {
        if ($aCnt==0) {
            $add_attr['mailalias'] = $add_alias;
            $r = @ldap_mod_add($ds, $a_dn, $add_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err != 0) {
                $_SESSION['in_msg']
                    .= "$warn ldap error adding alias $a_alias "
                    . "to $a_dn: "
                    . "$err - $err_msg.$ef";
            } else {
                $_SESSION['in_msg']
                    .= "$ok Mail Alias $a_alias added to $a_dn.$ef";
            }
        }
    }
}

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
// Add a kerberos principal is there is not one

function kp_add ($uid) {

    global $k5start;
    global $kdcmaster;
    global $ok;
    global $warn;

    $kp = find_kp($uid);
    if (strlen($kp)==0) {
        // add the kerberos principal
        $tmppass = uniqid('usermaint');
        $kcmd = "$k5start -- /usr/bin/remctl $kdcmaster kadmin create $uid $tmppass enabled";
        $ret_last = exec($kcmd, $return_text, $return_status);
        if ($return_status) {
            $_SESSION['in_msg'] .= "$warn remctl error: ";
            foreach ($return_text as $t) {
                $_SESSION['in_msg'] .= $t.'<br>';
            }
            $_SESSION['in_msg'] .= '</font>';
        } else {
            $_SESSION['in_msg'] .= "$ok Added Kerberos principal $kp</font><br>";
        }
    }
    return;
}

// -------------------------------
// Add a kerberos principal is there is not one

function kp_delete ($uid) {

    global $k5start;
    global $kdcmaster;
    global $ok;
    global $warn;

    $kp = find_kp($uid);
    if (strlen($kp)>0) {
        $_SESSION['in_msg'] .= "$ok Deleting Kerberos principal</font><br>";
        $kcmd = "$k5start -- /usr/bin/remctl $kdcmaster kadmin delete $uid";
        $ret_last = exec($kcmd, $return_text, $return_status);
        if ($return_status) {
            $_SESSION['in_msg'] .= "$warn krb error: ";
            foreach ($return_text as $t) {
                $_SESSION['in_msg'] .= $t.'<br>';
            }
            $_SESSION['in_msg'] .= '</font>';
        }
    }
    return;
}

// --------------------------------------------------------------
// Check user groups with entries not part of a persons base entry

function check_groups ($a_dn,
                       $a_uid,
                       $a_uidnumber,
                       $a_gidnumber,
                       $a_AppAddList,
                       $a_AppDelList) {

    global $ds;
    global $ok;
    global $warn;
    global $ef;
    global $ldap_groupbase;
    global $ldap_base;

    // check for PAM access stuctures

    // check applications groups
    if (strlen($a_uid)>0) {
        if (is_array($a_AppAddList)) {
            foreach ($a_AppAddList as $thisIdx => $thisApp) {
                app_group_check ($a_uid, "ADD", $thisApp);
            }
        }
        if (is_array($a_AppDelList)) {
            foreach ($a_AppDelList as $thisIdx => $thisApp) {
                app_group_check ($a_uid, "", $thisApp);
            }
        }
    }

}

// -------------------------------------------------------------
// main routine

if (!isset($inAppAddList)) {$inAppAddList = array();}
if (!isset($inAppDelList)) {$inAppDelList = array();}

$warn = '<font color="#cc0000">';
$ok = '<font color="#00cc00">';
$ef = "</font><br>\n";

$krb_oc     = 'krb5Principal';
$krb_attr   = 'krb5principalname';

// This array describes the "simple" attributes.  That is attributes
// that have a simple value.
$fld_list = array();
array_push ($fld_list, 'comments');
array_push ($fld_list, 'facsimiletelephonenumber');
array_push ($fld_list, 'gidnumber');
array_push ($fld_list, 'givenname');
array_push ($fld_list, 'homedirectory');
array_push ($fld_list, 'l');
array_push ($fld_list, 'loginshell');
array_push ($fld_list, 'mail');
array_push ($fld_list, 'maildepartment');
array_push ($fld_list, 'mobile');
array_push ($fld_list, 'nickname');
array_push ($fld_list, 'objectclass');
array_push ($fld_list, 'pager');
array_push ($fld_list, 'postaladdress');
array_push ($fld_list, 'postalcode');
array_push ($fld_list, 'siteresponsibility');
array_push ($fld_list, 'sn');
array_push ($fld_list, 'st');
array_push ($fld_list, 'telephonenumber');
array_push ($fld_list, 'title');
array_push ($fld_list, 'uid');
array_push ($fld_list, 'uidnumber');
array_push ($fld_list, 'workphone');

$ds = ldap_connect($ldap_server);
if (!$ds) {
    $_SESSION['in_msg'] .= "Problem connecting to the $ldap_server server";
    $in_button_add = '';
    $in_button_update = '';
    $in_button_delete = '';
} else {
    $r=ldap_bind($ds,$ldap_manager,$ldap_password);
}

// get a list of pam, posix, and application groups
require ('inc_groups.php');

// decode any xml data that is passed
$tags = array();
if (isset($in_xml_data)) {
    // Set up the XML parser
    $xml_parser = xml_parser_create();
    xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
    xml_set_element_handler($xml_parser, "startElement", "endElement");
    xml_set_character_data_handler($xml_parser, "characterData");

    // Parse the data passed and then free the resouce
    if ( !xml_parse($xml_parser, $in_xml_data, true) ) {
        echo "XML error:"
            . xml_error_string(xml_get_error_code($xml_parser))
            . "<br>\n";
        exit;
    }
    xml_parser_free($xml_parser);
}

// Set the kerberos principal name for everyone to use
$thisPrincipal = $in_uid . '@' . $CONF_krb_realm;

if (isset($in_button_add)) {

    // -----------------------------------------------------
    // Add an LDAP Entry
    // -----------------------------------------------------

    if (!isset($in_uid)) {
        $_SESSION['in_msg'] .= "$warn UID is required.$ef";
    } elseif (!isset($in_new_cn)) {
        $_SESSION['in_msg'] .= "$warn Common Name is required.$ef";
    } else {

        // check for duplicates first

        $filter = "(uid=$in_uid)";
        $attrs = array ('cn');
        $sr = @ldap_search ($ds, $ldap_base, $filter, $attrs);
        $entries = @ldap_get_entries($ds, $sr);
        $uid_cnt = $entries["count"];
        if ($uid_cnt>0) {
            $a_cn = $entries[0]['cn'][0];
            $_SESSION['in_msg'] .= "$warn UID is already in use by $a_cn.$ef";
            $_SESSION['in_msg'] .= "$warn Add of user entry aborted.$ef";
        } else {

            // add the new entry

            $thisUIDNumber = 0;
            $ldap_entry["objectclass"][] = "top";
            $_SESSION['in_msg'] .= "$ok adding objectClass = top$ef";
            $ldap_entry["objectclass"][] = "person";
            $_SESSION['in_msg'] .= "$ok adding objectClass = person$ef";
            $ldap_entry["objectclass"][] = "pridePerson";
            $_SESSION['in_msg'] .= "$ok adding objectClass = pridePerson$ef";
            $ldap_entry["objectclass"][] = $krb_oc;
            $_SESSION['in_msg'] .= "$ok adding objectClass = $krb_oc$ef";

            // Add kerberos principal name
            $ldap_entry[$krb_attr][] = $thisPrincipal;
            $_SESSION['in_msg'] .= "$ok adding $krb_attr = $thisPrincipal$ef";

            // Create posix entry only when asked to
            $posix_entry = 0;
            if (strlen($in_linux_add)>0) {$posix_entry = 1;}

            foreach ($fld_list as $fld) {
                $val = stripslashes(trim($_REQUEST["in_$fld"]));
                if (strlen($val)>0) {
                    $_SESSION['in_msg'] .= "$ok adding $fld = $val$ef";
                    $ldap_entry[$fld][0] = $val;
                    if ($fld=='uidnumber')     {
                        $posix_entry = 1;
                        $thisUIDNumber = $val;
                    }
                    if ($fld=='gidnumber') {
                        $posix_entry = 1;
                        $thisGIDNumber = $val;
                    }
                    if ($fld=='loginshell')    {$posix_entry = 1;}
                    if ($fld=='homedirectory') {$posix_entry = 1;}
                }
            }

            // create cn entries
            $first_cn = $a_cn = strtok($in_new_cn,',');
            while (strlen($a_cn)>0) {
                $_SESSION['in_msg'] .= "$ok adding cn = $a_cn$ef";
                $ldap_entry["cn"][] = $a_cn;
                $a_cn = strtok(',');
            }

            // create mailDelivery entries
            $a_maildelivery = strtok($in_new_maildelivery,',');
            while (strlen($a_maildelivery)>0) {
                $_SESSION['in_msg']
                    .="$ok adding mailDelivery = $a_maildelivery$ef";
                $ldap_entry["mailDelivery"][] = $a_maildelivery;
                $a_maildelivery = strtok(',');
            }

            // see if we need posix objectclasses
            if ($posix_entry) {
                $ldap_entry["objectclass"][] = "posixAccount";
                $_SESSION['in_msg']
                    .= "$ok adding objectClass = posixAccount$ef";
                $ldap_entry["objectclass"][] = "shadowAccount";
                $_SESSION['in_msg']
                    .= "$ok adding objectClass = shadowAccount$ef";
                if ($thisUIDNumber == 0) {
                    $thisUIDNumber = make_UID(4000);
                    $ldap_entry["uidnumber"][] = $thisUIDNumber;
                    $_SESSION['in_msg']
                        .= "$ok adding uidNumber = $thisUIDNumber$ef";
                }
                if ($thisGIDNumber == 0) {
                    $thisGIDNumber = $thisUIDNumber;
                    $ldap_entry["gidnumber"][] = $thisUIDNumber;
                    $_SESSION['in_msg']
                        .= "$ok adding gidNumber = $thisUIDNumber$ef";
                }
            }

            // add data to directory
            $this_dn = "uid=$in_uid,ou=people,$ldap_base";
            if (@ldap_add($ds, $this_dn, $ldap_entry)) {
                $_SESSION['in_msg'] .= "$ok Directory updated.$ef";
            } else {
                $_SESSION['in_msg']
                    .= "$warn Problem adding $this_dn to directory$ef";
                $ldapErr = ldap_errno ($ds);
                $ldapMsg = ldap_error ($ds);
                $_SESSION['in_msg'] .= "$warn Error: $ldapErr, $ldapMsg<br>";
            }

            // check posix groups

            if (strlen(trim($in_uidnumber))>0) {

                // posix group for this user maybe
                $posixFilter = "(&(objectclass=posixGroup)";
                $posixFilter .= "(cn=$in_uid))";
                $posixReturn = array ('gidNumber','cn');
                $sr = @ldap_search ($ds,
                                    $ldap_base,
                                    $posixFilter,
                                    $posixReturn);
                $posix = @ldap_get_entries($ds, $sr);
                $posix_cnt = $posix["count"];

                // create a posix group for this user
                if ($posix_cnt==0) {
                    $posix_attrs['objectclass'][0] = 'top';
                    $posix_attrs['objectclass'][1] = 'posixGroup';
                    $posix_attrs['cn'][0] = $a_uid;
                    $posix_attrs['memberUid'][0] = $in_uid;
                    $posix_attrs['gidNumber'][0] = $in_gidnumber;
                    $posix_attrs['description'][0] = $first_cn;
                    $posix_dn = "cn=$in_uid,$ldap_groupbase";
                    $r = @ldap_add($ds, $posix_dn, $posix_attrs);
                    $err = ldap_errno ($ds);
                    $err_msg = ldap_error ($ds);
                    if ($err == 0) {
                        $_SESSION['in_msg'] .= "$ok $posix_dn updated.$ef";
                    } else {
                        $_SESSION['in_msg']
                            .= "$warn Problem adding $posix_dn to "
                            .  "directory$ef";
                        $ldapErr = ldap_errno ($ds);
                        $ldapMsg = ldap_error ($ds);
                        $_SESSION['in_msg']
                            .= "$warn Error: $ldapErr, $ldapMsg$ef";
                    }
                } else {
                    posix_group_check ($in_uid, $in_uid, $in_uid);
                }
            }
            // check the listed posix groups
            if ($tags['count']['posixgroup']>0) {
                if ($tags['count']['posixgroup']>0) {
                    foreach ($tags['data']['posixgroup']
                             as $idx => $this_posixgroup) {
                        $flag = '';
                        if ($this_posixgroup['checked'] == 'Y'
                            && $in_uidnumber>0) {$flag = 'Y';}
                        posix_group_check ($this_dn,
                                           $flag,
                                           $this_posixgroup['text']);
                    }
                }
            }
            // check for any other additions
            if (strlen($in_posix_new)>0) {
                $thisGroup = trim(strtok($in_posix_new, ','));
                while (strlen($thisGroup)>0) {
                    posix_group_check ($in_uid, $thisGroup, $thisGroup);
                    $thisGroup = trim(strtok(','));
                }
            }

            // Check PAM groups
            if ($tags['count']['pamgroup']>0) {
                foreach ($tags['data']['pamgroup'] as $idx => $this_pamgroup) {
                    $flag = '';
                    if ($this_pamgroup['checked'] == 'Y'
                        && $in_uidnumber>0) {$flag = 'Y';}
                    pam_group_check ($this_dn, $flag, $this_pamgroup['text']);
                }
            }

            check_groups($this_dn,
                         $in_uid,
                         $in_uidnumber,
                         $in_gidnumber,
                         $inAppAddList,
                         $inAppDelList);


        }

        // add the kerberos principal
        kp_add($in_uid);

        // create a mailbox if necessary
        mailbox_check ($in_uid, $in_maildelivery, $CONF_mailbox_domain);

        // Check mailalias
        if ($in_mailalias_cnt>0) {
            for ($i=0; $i<$in_mailalias_cnt; $i++) {
                mail_alias_check ($in_dn,
                                  $_REQUEST["in_mailalias_$i"],
                                  $_REQUEST["in_mailalias_list_$i"]);
            }
        }
        if (strlen($in_new_mailalias)>0) {
            $a_mailalias = trim(strtok($in_new_mailalias, ','));
            while (strlen($a_mailalias)>0) {
                mail_alias_check ($in_dn, $a_mailalias, $a_mailalias);
                $a_mailalias = trim(strtok(','));
            }
        }

        // notify the administrator
        if ( strlen($CONF_ldap_manager_mailbox)>0 ) {
            $mail_msg .= strip_tags ($_SESSION['in_msg']);
            $subj = "New LDAP entry in $ldap_dir_title";
            mail ($CONF_ldap_manager_mailbox,
                  $subj,
                  $mail_msg);
        }
    }

} elseif (isset($in_button_update)) {

    // -----------------------------------------------------
    // Update an LDAP Entry
    // -----------------------------------------------------

    $ldap_filter = 'objectclass=*';

    if (strlen($in_dn) == 0) {
        $_SESSION['in_msg'] .= "$warn No entry to update$ef";
        $ret_cnt = 0;
    } else {
        $return_list = $fld_list;
        $return_list[] = $krb_attr;
        $sr = @ldap_read ($ds, $in_dn, $ldap_filter, $return_list);
        $info = @ldap_get_entries($ds, $sr);
        $err = ldap_errno ($ds);
        if ($err) {
            $err_msg = ldap_error ($ds);
            $_SESSION['in_msg'] .= "errors: $err $err_msg<br>\n";
        }
        $ret_cnt = $info["count"];
    }
    if ($ret_cnt) {

        $first_cn = '';
        if (isset($info[0]['cn'][0])) {$first_cn = trim($info[0]['cn'][0]);}
        $add_cnt = 0;
        $posix_entry = 0;
        if (strlen($in_linux_add)>0) {$posix_entry = 1; $add_cnt++;}

        foreach ($fld_list as $fld) {

            if ($fld == 'objectclass')     { continue; }

            $val_in = '';
            $tmp = $_REQUEST["in_$fld"];
            if (isset($tmp)) {
                $val_in  = stripslashes(trim($tmp));
            }

            $val_ldap = '';
            if (isset($info[0]["$fld"][0])) {
                $val_ldap = trim($info[0]["$fld"][0]);
            }

            if ( $val_in != $val_ldap ) {
                if (strlen($val_in)==0) {
                    if (strlen($val_ldap)>0) {
                        // delete the attribute
                        $new_data["$fld"] = $val_ldap;
                        $r = @ldap_mod_del($ds, $in_dn, $new_data);
                        $err = ldap_errno ($ds);
                        $err_msg = ldap_error ($ds);
                        $_SESSION['in_msg']
                            .= "$ok $fld: $val_ldap deleted.$ef";
                        if ($err>0) {
                            $_SESSION['in_msg'] .= "$warn ldap error deleting "
                                . "attribute $fld: $err - $err_msg.$ef";
                        }
                    }
                } else {
                    $new_data["$fld"] = $val_in;

                    // try and replace it, if that fails try and add it

                    // replace
                    $r = @ldap_mod_replace($ds, $in_dn, $new_data);
                    $err = ldap_errno ($ds);
                    $err_msg = ldap_error ($ds);
                    if ($err == 0) {
                        $_SESSION['in_msg'] .= "$ok $fld replaced.$ef";

                    } else {

                        // Check to see if we are adding computer.
                        // If we are then we need to make sure that they also
                        // have the correct object classes.
                        if ($fld=='uidnumber') {
                            $thisUIDNumber = $val_in;
                            $posix_entry = 1;
                        }
                        if ($fld=='gidnumber') {
                            $thisGIDNumber = $val_in;
                            $posix_entry = 1;
                        }
                        if ($fld=='loginshell')    {$posix_entry = 1;}
                        if ($fld=='homedirectory') {$posix_entry = 1;}

                        $add_cnt++;
                        $add_data["$fld"][] = $val_in;
                        $_SESSION['in_msg'] .= "$ok $fld added.$ef";
                    }
                }
            }
        }

        // -- Make sure every entry has a kerberos principal
        if (!isset($info[0][$krb_attr][0])) {
            $krb_oc_add = 1;
            foreach ($info[0]['objectclass'] as $oc) {
                if ($oc==$krb_oc) {$krb_oc_add = 0;}
            }
            if ($krb_oc_add > 0) {
                $add_data['objectclass'][] = $krb_oc;
                $_SESSION['in_msg'] .= "$ok objectclass $krb_oc added.$ef";
            }
            $add_data[$krb_attr][] = $thisPrincipal;
            $_SESSION['in_msg'] .= "$ok $krb_attr = $thisPrincipal added.$ef";
            $add_cnt++;
        }

        // add the kerberos principal
        kp_add($in_uid);

        // -- add attributes
        if ($add_cnt>0) {

            // -- posix processing
            if ($posix_entry) {
                foreach ($info[0]['objectclass'] as $oc) {
                    if ($oc=='posixaccount') {$posix_entry = 0;}
                }
            }
            if ($posix_entry) {
                $add_data['objectclass'][] = 'posixAccount';
                $add_data['objectclass'][] = 'shadowAccount';
                $_SESSION['in_msg'] .= "$ok posixAccount added.$ef";
                $_SESSION['in_msg'] .= "$ok shadowAccount added.$ef";
                if ($thisUIDNumber == 0) {
                    $thisUIDNumber = make_UID(4000);
                    $add_data["uidnumber"][] = $thisUIDNumber;
                    $_SESSION['in_msg']
                        .= "$ok adding uidNumber = $thisUIDNumber$ef";
                }
                if ($thisGIDNumber == 0) {
                    $thisGIDNumber = $thisUIDNumber;
                    $add_data["gidnumber"][] = $thisUIDNumber;
                    $_SESSION['in_msg']
                        .= "$ok adding gidNumber = $thisUIDNumber$ef";
                }
            }

            // -- add the needed attributes
            $r = @ldap_mod_add($ds, $in_dn, $add_data);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err != 0) {
                $_SESSION['in_msg'] .= "$warn ldap error adding attributes: "
                    . "$err - $err_msg.$ef";
            }
        }

        // Check mail aliases
        if ($in_mailalias_cnt>0) {
            for ($i=0; $i<$in_mailalias_cnt; $i++) {
                $flag_name = "in_mailalias_$i";
                $list_name = "in_mailalias_list_$i";
                mail_alias_check ($in_dn,
                                  $_REQUEST["in_mailalias_$i"],
                                  $_REQUEST["in_mailalias_list_$i"]);
            }
        }
        if (strlen($in_new_mailalias)>0) {
            $a_mailalias = trim(strtok($in_new_mailalias, ','));
            while (strlen($a_mailalias)>0) {
                mail_alias_check ($in_dn, $a_mailalias, $a_mailalias);
                $a_mailalias = trim(strtok(','));
            }
        }

        // Check common name
        if (isset($in_new_cn)) {
            $a_cn = stripslashes(trim(strtok($in_new_cn, ',')));
            while (strlen($a_cn)>0) {
                common_name_check ($in_dn, $a_cn, $a_cn);
                $a_cn = stripslashes(trim(strtok(',')));
            }
        }
        if ($in_cn_cnt>0) {
            for ($i=0; $i<$in_cn_cnt; $i++) {
                common_name_check ($in_dn,
                                   stripslashes($in_cn[$i]),
                                   stripslashes($in_cn_list[$i]));
            }
        }

        // Check mailDelivery
        if (isset($in_new_maildelivery)) {
            $a_maildelivery
                = stripslashes(trim(strtok($in_new_maildelivery, ',')));
            while (strlen($a_maildelivery)>0) {
                maildelivery_check ($in_dn, $a_maildelivery, $a_maildelivery);
                $a_maildelivery = stripslashes(trim(strtok(',')));
            }
        }
        if ($in_maildelivery_cnt>0) {
            for ($i=0; $i<$in_maildelivery_cnt; $i++) {
                maildelivery_check($in_dn,
                                   stripslashes($in_maildelivery[$i]),
                                   stripslashes($in_maildelivery_list[$i]));
            }
        }

        // check posix groups

        if ($in_uidnumber>0) {

            // posix group for this user maybe
            $posixFilter = "(&(objectclass=posixGroup)";
            $posixFilter .= "(cn=$in_uid))";
            $posixReturn = array ('gidNumber','cn');
            $sr = @ldap_search ($ds, $ldap_base, $posixFilter, $posixReturn);
            $posix = @ldap_get_entries($ds, $sr);
            $posix_cnt = $posix["count"];

            // create a posix group for this user
            if ($posix_cnt==0) {
                $posix_attrs['objectclass'][0] = 'top';
                $posix_attrs['objectclass'][1] = 'posixGroup';
                $posix_attrs['cn'][0] = $in_uid;
                $posix_attrs['memberUid'][0] = $in_uid;
                $posix_attrs['gidNumber'][0] = $in_gidnumber;
                $posix_attrs['description'][0] = "User's personal group";
                $posix_dn = "cn=$in_uid,$ldap_groupbase";
                $r = @ldap_add($ds, $posix_dn, $posix_attrs);
                $err = ldap_errno ($ds);
                $err_msg = ldap_error ($ds);
                if ($err == 0) {
                    $_SESSION['in_msg'] .= "$ok $posix_dn updated.$ef";
                } else {
                    $_SESSION['in_msg'] .= "$warn Problem adding $posix_dn to "
                        .  "directory$ef";
                    $ldapErr = ldap_errno ($ds);
                    $ldapMsg = ldap_error ($ds);
                    $_SESSION['in_msg']
                        .= "$warn Error: $ldapErr, $ldapMsg$ef";
                }
            } else {
                posix_group_check ($in_uid, $in_uid, $in_uid);
            }
        }
        // check the listed posix groups
        if (isset($tags['count']['posixgroup'])) {
            if ($tags['count']['posixgroup']>0) {
                foreach ($tags['data']['posixgroup']
                         as $idx => $this_posixgroup) {
                    $flag = '';
                    if ($this_posixgroup['checked'] == 'Y'
                        && $in_uidnumber>0) {$flag = 'Y';}
                    posix_group_check ($in_uid,
                                       $flag,
                                       $this_posixgroup['text']);
                }
            }
        }
        // check for any other additions
        if (isset($in_posix_new) && isset($in_uidnumber)) {
            $thisGroup = trim(strtok($in_posix_new, ','));
            while (strlen($thisGroup)>0) {
                posix_group_check ($in_uid, $thisGroup, $thisGroup);
                $thisGroup = trim(strtok(','));
            }
        }

        check_groups($in_dn,
                     $in_uid,
                     $in_uidnumber,
                     $in_gidnumber,
                     $inAppAddList,
                     $inAppDelList);
    }

    // check mailbox
    mailbox_check ($in_uid, $in_maildelivery, $CONF_mailbox_domain);

    // Check PAM groups
    if (isset($tags['count']['pamgroup'])) {
        foreach ($tags['data']['pamgroup'] as $idx => $this_pamgroup) {
            $flag = '';
            if ($this_pamgroup['checked'] == 'Y'
                && $in_uidnumber>0) {$flag = 'Y';}
            pam_group_check ($in_dn, $flag, $this_pamgroup['text']);
        }
    }

} elseif (isset($in_button_delete)) {

    // -----------------------------------------------------
    // Delete an LDAP Entry
    // -----------------------------------------------------

    // delete their posix group if they have one
    $del_dn = "cn=$in_uid,$ldap_groupbase";
    $r = @ldap_delete($ds, $posix_dn);
    $err = ldap_errno ($ds);
    $err_msg = ldap_error ($ds);
    if ($err == 0) {
        $_SESSION['in_msg'] .= "$ok $del_dn deleted.$ef";
    }

    // delete from other posix groups
    $pg_filter = "(&(objectclass=posixGroup)(memberUid=$in_uid))";
    $pg_attrs = array ('cn','description');
    $sr = @ldap_search ($ds, $ldap_base, $pg_filter, $pg_attrs);
    $pg_group = @ldap_get_entries($ds, $sr);
    $pg_cnt = $pg_group["count"];

    if ($pg_cnt >0) {
        for ($pg_i=0; $pg_i<$pg_cnt; $pg_i++) {
            posix_group_check ($in_uid, '', $pg_group[$pg_i]['cn'][0]);
        }
    }

    // delete from pam groups
    $pg_filter = "(&(objectclass=pamGroup)(memberDN=$in_dn))";
    $pg_attrs = array ('cn','description');
    $sr = @ldap_search ($ds, $ldap_base, $pg_filter, $pg_attrs);
    $pg_group = @ldap_get_entries($ds, $sr);
    $pg_cnt = $pg_group["count"];

    if ($pg_cnt >0) {
        for ($pg_i=0; $pg_i<$pg_cnt; $pg_i++) {
            pam_group_check ($in_dn, '', $pg_group[$pg_i]['cn'][0]);
        }
    }

    // delete from app groups
    $pg_filter = "(&(objectclass=prideApplication)(memberUid=$in_uid))";
    $pg_filter = "memberUid=$in_uid";
    $pg_attrs = array ('cn','description');
    $sr = @ldap_search ($ds, $ldap_base, $pg_filter, $pg_attrs);
    $pg_group = @ldap_get_entries($ds, $sr);
    $pg_cnt = $pg_group["count"];

    if ($pg_cnt > 0) {
        for ($pg_i=0; $pg_i<$pg_cnt; $pg_i++) {
            app_group_check ($in_uid, '', $pg_group[$pg_i]['cn'][0]);
        }
    }

    // now delete the entry
    $r = @ldap_delete($ds, $in_dn);
    $err = ldap_errno ($ds);
    $err_msg = ldap_error ($ds);
    if ($err == 0) {
        $_SESSION['in_msg'] .= "$ok $in_dn deleted.$ef";
    } else {
        $_SESSION['in_msg'] .= "$warn ldap error deleting $in_dn: "
            . "$err - $err_msg.$ef";
    }

    mailbox_check ($in_uid, "", $CONF_mailbox_domain);

    // delete the kerberos principal
    kp_delete($in_uid);

} else {
    $_SESSION['in_msg'] .= "$warn invalid action$ef";
}

$a_url = "user_maint.php?in_uid=$in_uid";
header ("REFRESH: 0; URL=$a_url");
?>

<html>
<head>
<title>MacAllister LDAP Directory Maintenance</title>
</head>
<body>
<a href="<?php echo $a_url;?>">Back to User Maintenance</a>
</body>
</html>
