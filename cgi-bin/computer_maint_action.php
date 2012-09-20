<?php 
// file: computer_maint_action.php
// author: Bill MacAllister

require('/etc/whm/macdir_auth.php');
require('inc_util.php');
require ('inc_pwset.php');

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
    $posix = @ldap_get_entries($ds, $sr);
    $posix_cnt = $posix["count"];
    
    if (strlen($a_flag)==0) {
        
        // delete it if we find it
        if ($posix_cnt>0) {
            $r = @ldap_mod_del($ds, $group_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $_SESSION['s_msg'] .= "$ok $a_uid removed from $a_group.$ef";
            if ($err>0) {
                $_SESSION['s_msg'] .= "$warn ldap error removing $a_uid "
                     . "from $a_group: $err - $err_msg.$ef";
            }
        }
        
    } else {
        // add it if we don't 
        if ($posix_cnt==0) {
            $r = @ldap_mod_add($ds, $group_dn, $group_attr);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            $_SESSION['s_msg'] .= "$ok $a_uid added to group $a_group.$ef";
            if ($err != 0) {
                $_SESSION['s_msg'] .= "$warn ldap error adding "
                     . "$a_uid to $a_group: "
                     . "$err - $err_msg.$ef";
            }
        }
    }
}

// --------------------------------------------------------------
// Check user groups

function check_groups ($a_dn,
                       $a_uid, 
                       $a_uidnumber,
                       $a_gidnumber,
                       $a_PosixGroup,
                       $a_PosixGroupList,
                       $a_PosixNew) {
    
    global $ds;
    global $ok;
    global $warn;
    global $ef;
    global $ldap_groupbase;
    global $ldap_base;
    
    if (strlen(trim($a_uidnumber))>0) {
        
        // posix group for this user
        $posixFilter = "(&(objectclass=posixGroup)";
        $posixFilter .= "(cn=$a_uid))";
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
            $posix_attrs['memberUid'][0] = $a_uid;
            $posix_attrs['gidNumber'][0] = $a_gidnumber;
            $posix_dn = "cn=$a_uid,$ldap_groupbase";
            $r = @ldap_add($ds, $posix_dn, $posix_attrs);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err == 0) {
                $_SESSION['s_msg'] .= "$ok $posix_dn updated.</font><br>";
            } else {
                $_SESSION['s_msg'] .= "$warn Problem adding posix group to "
                     .  "directory</font><br>";
                $_SESSION['s_msg'] .= "$warn Problem DN: $posix_dn to "
                     .  "directory</font><br>";
                $ldapErr = ldap_errno ($ds);
                $ldapMsg = ldap_error ($ds);
                $_SESSION['s_msg'] .= "$warn Error: "
                     . "$ldapErr, $ldapMsg</font><br>";
            }
            
        } else {
            
            // make sure of entry in their own group
            posix_group_check ($a_uid, $a_uid, $a_uid);
            
        }
        
        // check the rest of the posix groups
        if (is_array($a_PosixGroupList)) {
            foreach ($a_PosixGroupList as $thisIdx => $thisPosix) {
                $flag = '';
                if (is_array($a_PosixGroup)) {
                    foreach ($a_PosixGroup as $pgIdx => $pgGroup) {
                        if ($pgGroup == $thisPosix) {$flag = 'add';}
                    }
                }
                posix_group_check ($a_uid, $flag, $thisPosix);
            }
        }
        if (strlen($a_PosixNew)>0) {
            $thisGroup = trim(strtok($a_PosixNew, ','));
            while (strlen($thisGroup)>0) {
                posix_group_check ($a_uid, $thisGroup, $thisGroup);
                $thisGroup = trim(strtok(','));
            }
        }
        
    }
}


// --------------------------------------------------------------
// Request update by the pdc

function request_pdc_update($thisDN, $thisUID, $thisDomain) {
    
    global $warn, $ok, $ef;
    global $ds;
    global $samba_domain_info;
    
    $pdcHost = $samba_domain_info[$thisDomain]['pdchost'];
    
    $root_uid = preg_replace ("/\$$/", '', trim($thisUID));
    $cmd = 'ssh '.$pdcHost ;
    $cmd .= " /mac/scripts/ldap-set-samba-computer $root_uid ";
    
    unset ($results);
    exec ($cmd, $results);
    
    $cnt = 0;
    if (is_array($results)) {
        $_SESSION['s_msg'] .= $ok;
        foreach ($results as $l) {
            $_SESSION['s_msg'] .= htmlentities($l)."<br>\n";
            $cnt++;
        }
        $_SESSION['s_msg'] .= $ef;
        
        // set the domain name
        $add_data['sambadomainname'][] = $thisDomain;
        
        // store domain name
        $r = @ldap_mod_add($ds, $thisDN, $add_data);
        $err = ldap_errno ($ds);
        $err_msg = ldap_error ($ds);
        if ($err != 0) {
            $_SESSION['s_msg'] .= "$warn ldap error adding attributes "
                 . "for $thisDN "
                 . "$err - $err_msg.$ef";
        }
        
    } if ($cnt == 0) {
        $_SESSION['s_msg'] .= $warn."No Command Output from command:<br>\n";
        $_SESSION['s_msg'] .= $cmd.$ef;
    }
    
}

// -------------------------------------------------------------
// main routine

$warn = '<font color="#cc0000">';
$ok = '<font color="#00cc00">';
$ef = "</font><br>\n";

// This array describes the "simple" attributes.  That is attributes
// that have a simple value.
$fld_list = array();
array_push ($fld_list, 'cn');
array_push ($fld_list, 'comments');
array_push ($fld_list, 'objectclass');
array_push ($fld_list, 'macaddress');
array_push ($fld_list, 'iphostnumber');

// bind to the ldap directory
$ds = ldap_connect($ldap_server);
if (!$ds) {
    $_SESSION['s_msg'] .= "Problem connecting to the $ldap_server server";
    $btn_add = '';
    $btn_update = '';
    $btn_delete = '';
} else {
    $ldapReturn = ldap_bind($ds, $ldap_manager, $ldap_password);
}

// get a list of pam, posix, and application groups
require ('inc_groups.php');

$in_uid = strtolower($in_uid);
$thisDN = "uid=$in_uid,$ldap_computerbase";

if (strlen($btn_add)>0) {
    if (strlen($in_uid)==0) {
        $_SESSION['s_msg'] .= "$warn UID is required. $ef";
    } elseif (strlen($in_cn)==0) {
        $_SESSION['s_msg'] .= "$warn Common Name is required.$ef";
    } else {
        
        $thisDN = "uid=$in_uid,$ldap_computerbase";
        
        // Minimum attributes for a user.
        $ldap_entry["objectclass"][] = "top";
        $ldap_entry["objectclass"][] = "prideComputer";
        $ldap_entry["uid"][] = $in_uid;
        
        // now add computer stuff
        $new_uid_no = make_UID($ldap_computer_uidnumber_base);
        $ldap_entry['objectclass'][] = 'posixAccount';
        $ldap_entry['objectclass'][] = 'shadowAccount';
        $ldap_entry['uidnumber'][] = $new_uid_no;
        $ldap_entry['gidnumber'][] = $new_uid_no;
        $ldap_entry['homedirectory'][] = "/dev/null";
        $ldap_entry['loginshell'][] = '/dev/null';
        
        // now add the data
        foreach ($fld_list as $fld) {
            $name = "in_$fld"; $val = $$name;
            if (strlen($val)>0) {
                $_SESSION['s_msg'] .= "$ok adding $fld = $val</font><br>";
                $ldap_entry[$fld][] = $val;
            }
        }
        // add data to directory
        if (@ldap_add($ds, $thisDN, $ldap_entry)) {
            $_SESSION['s_msg'] .= "$ok Directory updated.</font><br>";
        } else {
            $_SESSION['s_msg'] .= "$warn Problem adding $thisDN<br>";
            $ldapErr = ldap_errno ($ds);
            $ldapMsg = ldap_error ($ds);
            $_SESSION['s_msg'] .= "$warn Error: $ldapErr, $ldapMsg<br>";
        }
        
        check_groups($thisDN, 
                     $in_uid, 
                     $new_uid_no, 
                     $new_uid_no,
                     $inPAMList,
                     $inPosixGroup,
                     $inPosixGroupList,
                     $inPosixNew);
        
        if (strlen($in_sambadomainname) > 0) {
            sleep(1);
            request_pdc_update ($thisDN, $in_uid, $in_sambadomainname); 
        }

        if (strlen($in_userpassword) > 0) {
            ldap_set_password ($in_uid,'userpassword',$in_userpassword,'');
            ldap_set_password ($in_uid,'pridecredential',$in_userpassword,'');
        }
    }
    
} elseif (strlen($btn_update)>0) {
    
    $ldap_filter = 'objectclass=*';
    
    if (strlen($in_uid) == 0) {
        $_SESSION['s_msg'] .= "$warn No entry to update$ef";
        $ret_cnt = 0;
    } else {
        $ret_all = array();
        $sr = @ldap_read ($ds, $thisDN, $ldap_filter, $ret_all);  
        $info = @ldap_get_entries($ds, $sr);
        $err = ldap_errno ($ds);
        if ($err) {
            $err_msg = ldap_error ($ds);
            $_SESSION['s_msg'] .= "Problem finding $thisDN<br>\n";
            $_SESSION['s_msg'] .= "errors: $err $err_msg<br>\n";
        }
        $ret_cnt = $info["count"];
    }
    if ($ret_cnt) {
        
        $this_uidnumber = $info[0]['uidnumber'][0]; 
        $this_gidnumber = $info[0]['gidnumber'][0];
        
        // first make sure there are posix attributies
        if ($this_uidnumber == 0) {
            $this_uidnumber = make_UID(6000);
            $this_gidnumber = $this_uidnumber;
            $posix_data['objectclass'][]   = 'posixAccount';
            $posix_data['objectclass'][]   = 'shadowAccount';
            $posix_data['objectclass'][]   = 'sambaSamAccount';
            $posix_data['uidnumber'][]     = $this_uidnumber;
            $posix_data['gidnumber'][]     = $this_gidnumber;
            $posix_data['homedirectory'][] = "/dev/null";
            $posix_data['loginshell'][]    = '/dev/null';
            $r = @ldap_mod_add($ds, $thisDN, $posix_data);
            $err = ldap_errno ($ds);
            $err_msg = ldap_error ($ds);
            if ($err) {
                $_SESSION['s_msg'] .= "$warn ldap error adding "
                     . "posix attributes: "
                     . "$err - $err_msg.$ef";
            } else {
                $_SESSION['s_msg'] .= "$ok posixAccount added.$ef";
            }
        }
        
        // now process changes
        $add_cnt = 0;
        $request_msgstore = 0;
        foreach ($fld_list as $fld) {
            if ($fld == 'objectclass') { continue; }
            
            $tmp = 'in_' . $fld;  $val_in   = trim($$tmp) . '';
            $val_ldap = trim($info[0]["$fld"][0]);
            if ( $val_in != $val_ldap ) {
                
                if (strlen($val_in)==0) {
                    if (strlen($val_ldap)>0) {
                        // delete the attribute
                        $new_data["$fld"] = $val_ldap;
                        $r = @ldap_mod_del($ds, $thisDN, $new_data);
                        $err = ldap_errno ($ds);
                        $err_msg = ldap_error ($ds);
                        $_SESSION['s_msg'].="$ok $fld: $val_ldap deleted.$ef";
                        if ($err>0) {
                            $_SESSION['s_msg'] .= "$warn ldap error deleting "
                                 . "attribute $fld: $err - $err_msg.$ef";
                        }
                    }
                } else {
                    $new_data["$fld"] = $val_in;
                    
                    // try and replace it, if that fails try and add it
                    $r = @ldap_mod_replace($ds, $thisDN, $new_data);
                    $err = ldap_errno ($ds);
                    $err_msg = ldap_error ($ds);
                    if ($err == 0) {
                        $_SESSION['s_msg'] .= "$ok $fld replaced.$ef";
                        
                    } else {
                        
                        // Check to see if we are adding computer access.
                        // If we are then we need to make sure that they also
                        // have the correct object classes.
                        $add_cnt++;
                        $add_data["$fld"] = $val_in;
                        $_SESSION['s_msg'] .= "$ok $fld added.$ef";
                    }
                }
            }
        }
    }
    
    // add the data that is new
    if ($add_cnt>0) {
        $r = @ldap_mod_add($ds, $thisDN, $add_data);
        $err = ldap_errno ($ds);
        $err_msg = ldap_error ($ds);
        if ($err != 0) {
            $_SESSION['s_msg'] .= "$warn ldap error adding attributes: "
                 . "$err - $err_msg.$ef";
        }
    }
    
    check_groups($thisDN, 
                 $in_uid, 
                 $this_uidnumber, 
                 $this_gidnumber,
                 $inPosixGroup,
                 $inPosixGroupList,
                 $inPosixNew);
    
    if (strlen($in_sambadomainname) > 0) {
        request_pdc_update ($thisDN, $in_uid, $in_sambadomainname); 
    }

    if (strlen($in_userpassword) > 0) {
        ldap_set_password ($in_uid,'userpassword',$in_userpassword,'');
        ldap_set_password ($in_uid,'pridecredential',$in_userpassword,'');
    }
    
} elseif (strlen($btn_delete)>0) {
    
    // delete their posix group if they have one
    $del_dn = "cn=$in_uid,$ldap_groupbase";
    $r = @ldap_delete($ds, $del_dn);
    $err = ldap_errno ($ds);
    $err_msg = ldap_error ($ds);
    if ($err == 0) {
        $_SESSION['s_msg'] .= "$ok $del_dn deleted.</font><br>";
    } else {
        $_SESSION['s_msg'] .= "$warn Problem deleting $del_dn</font><br>";
        $_SESSION['s_msg'] .= "$warn $err_msg</font><br>";
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
    
    // now delete the entry
    $r = @ldap_delete($ds, $thisDN);
    $err = ldap_errno ($ds);
    $err_msg = ldap_error ($ds);
    if ($err == 0) {
        $_SESSION['s_msg'] .= "$ok $thisDN deleted.</font><br>";
    } else {
        $_SESSION['s_msg'] .= "$warn ldap error deleting $thisDN: "
             . "$err - $err_msg.$ef";
    }
    
    $in_uid = 'CLEARFORM';
    
} else {
    $_SESSION['s_msg'] .= "$warn invalid action$ef";
}

header ("REFRESH: 0; URL=computer_maint.php?in_uid=$in_uid");

?>

<html>
<head>
<title>LDAP SMB Maintenance Action</title>
</head>
<body>
LDAP SMB Maintenance Action.
</body>
</html>
