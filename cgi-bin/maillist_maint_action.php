<?php 
// file: maillist_maint_action.php
// author: Bill MacAllister

require ('/etc/whm/macdir_auth.php');
require('inc_maillist_auth.php');
$ldap_ml_base = "ou=maillists,$ldap_base";
$ds = ldap_connect($ldap_server);
if (!$ds) {
  $_SESSION['in_msg'] .= "Problem connecting to the $ldap_server server";
  $btn_add = '';
  $btn_update = '';
  $btn_delete = '';
} else {
  $r=ldap_bind($ds,$ldap_manager,$ldap_password);
}

// --------------------------------------------------------------
// Make sure the uid exists

function check_uid ($a_uid) {

  global $ds, $ldap_base, $ok, $warn, $ef;

  $uid_okay = 0;

  // search for admin access first
  $aFilter = "(&(objectclass=person)";
  $aFilter .= "(uid=$a_uid))";
  $aReturn = array ('cn');
  $old_err = error_reporting(E_ERROR | E_PARSE);
  $sr = ldap_search ($ds, $ldap_base, $aFilter, $aReturn);  
  $app = ldap_get_entries($ds, $sr);
  $tmp_err = error_reporting($old_err);
  $app_cnt = $app["count"];

  if ($app_cnt > 0) {$uid_okay = 1;}

  return $uid_okay;
}
    
// --------------------------------------------------------------
// Make sure the manager user is a user and is in the 
// email-lists application group.

function add_emaillist_authorization ($a_uid) {

  global $ds, $ldap_base, $ok, $warn, $ef, $update_cnt;
  $app_base = "ou=Applications,$ldap_base";

  // search for admin access first
  $aFilter = "(&(objectclass=prideApplication)";
  $aFilter .= "(cn=email-admin)";
  $aFilter .= "(memberUid=$a_uid))";
  $aReturn = array ('cn');
  $old_err = error_reporting(E_ERROR | E_PARSE);
  $sr = ldap_search ($ds, $app_base, $aFilter, $aReturn);  
  $app = ldap_get_entries($ds, $sr);
  $tmp_err = error_reporting($old_err);
  $app_cnt = $app["count"];

  if ($app_cnt == 0) {
    
    // Not an admin, see if they can maintain email lists at all
    $aFilter = "(&(objectclass=prideApplication)";
    $aFilter .= "(cn=email-lists)";
    $aFilter .= "(memberUid=$a_uid))";
    $aReturn = array ('cn');
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_search ($ds, $app_base, $aFilter, $aReturn);  
    $app = ldap_get_entries($ds, $sr);
    $tmp_err = error_reporting($old_err);
    $app_cnt = $app["count"];

    if ($app_cnt == 0) {
    
      // Okay, they are a nobody.  Add them to the email-lists application
      // group

      $app_attr = array ('memberuid' => "$a_uid");
      $app_dn = "cn=email-lists,$app_base";
      $old_err = error_reporting(E_ERROR | E_PARSE);
      $r = ldap_mod_add($ds, $app_dn, $app_attr);
      $err = ldap_errno ($ds);
      $err_msg = ldap_error ($ds);
      $tmp_err = error_reporting($old_err);
      if ($err == 0) {
	$_SESSION['in_msg'] .= "$ok $a_uid added to email-lists.$ef";
	$update_cnt++;
      } else {
        $_SESSION['in_msg'] .= "$warn ldap error adding $a_uid to email-lists:"
                            . "$err - $err_msg.$ef";
      }
    }
  }
}

// --------------------------------------------------------------
// Update mailDistributionID of local user.  If the address is not
// for a local user then return a fully qualified address.

function local_user_update ($a_addr, $a_flag, $a_ml) {

  global $ds, $ldap_base, $ok, $warn, $ef, $update_cnt;

  $return_addr = '';
  $local_domain = '@whm.com';
  $local_domain_pat = '/\@whm\.com$/i';
  $ml_attr['mailDistributionID'] = $a_ml;
  $md_attr['mailDelivery'] = $a_addr;

  $addr_type = 0;
  if (!strpos($a_addr,'@') || preg_match($local_domain_pat, $a_addr)) {
    // local address
    $addr_type = 1; 
    $test_uid = preg_replace ($local_domain_pat, '', $a_addr);
    if (check_uid($test_uid)>0) {
      // local user
      $addr_type = 2;
    }
  }

  if ($addr_type == 2) {

    // local user
    $aFilter = "(&(uid=$test_uid)(mailDistributionID=$a_ml))";
    $aRetAttrs = array ('cn');
    $sr = @ldap_search ($ds, $ldap_base, $aFilter, $aRetAttrs);  
    $ml_entry = @ldap_get_entries($ds, $sr);
    $aCnt = $ml_entry["count"];
 
    if (strlen($a_flag)==0) {

      // -- remove it from the user entry if we find it there
      if ($aCnt>0) {
	$aDN = $ml_entry[0]['dn'];
	$r = @ldap_mod_del($ds, $aDN, $ml_attr);
	$err = ldap_errno ($ds);
	$err_msg = ldap_error ($ds);
	if ($err>0) {
	  $_SESSION['in_msg'] .= "$warn ldap error removing $a_ml "
	     . "from $aDN: $err - $err_msg.$ef";
	} else {
	  $_SESSION['in_msg'] .= "$ok Mail List $a_ml removed "
	     . "from $aDN.$ef";
	  $update_cnt++;
	}
      }

      // -- check the mail list entry as well
      $aMdFilter = "(&(maildelivery=$a_addr)(localmailbox=$a_ml))";
      $aMdRetAttrs = array ('description');
      $md_sr = @ldap_search ($ds, $ldap_base, $aMdFilter, $aMdRetAttrs);  
      $md_entry = @ldap_get_entries($ds, $md_sr);
      $aMdCnt = $md_entry["count"];
 
      if ($aMdCnt>0) {
	$aMdDN = $md_entry[0]['dn'];
	$r = @ldap_mod_del($ds, $aMdDN, $md_attr);
	$err = ldap_errno ($ds);
	$err_msg = ldap_error ($ds);
	if ($err>0) {
	  $_SESSION['in_msg'] .= "$warn ldap error removing $a_addr "
	     . "from $aMdDN: $err - $err_msg.$ef";
	} else {
	  $_SESSION['in_msg'] .= "$ok Mail List $a_addr removed "
	     . "from $aMdDN.$ef";
	  $update_cnt++;
	}
      }

    } else {

      // -- add it if we don't 
      if ($aCnt==0) {
	// look up the user's entry
	$pFilter = "(&(objectclass=person)";
	$pFilter .= "(uid=$test_uid))";
	$pReturn = array ('cn');
	$old_err = error_reporting(E_ERROR | E_PARSE);
	$sr = ldap_search ($ds, $ldap_base, $pFilter, $pReturn);  
	$user = ldap_get_entries($ds, $sr);
	$tmp_err = error_reporting($old_err);
	$user_cnt = $user["count"];
	$aDN = $user[0]['dn'];
	// now add the maildistributionid
	$old_err = error_reporting(E_ERROR | E_PARSE);
	$r = ldap_mod_add($ds, $aDN, $ml_attr);
	$err = ldap_errno ($ds);
	$err_msg = ldap_error ($ds);
	$tmp_err = error_reporting($old_err);
	if ($err != 0) {
	  $_SESSION['in_msg'] .= "$warn ldap error adding $a_ml to $aDN: "
	     . "$err - $err_msg.$ef";
	} else {
	  $_SESSION['in_msg'] .= "$ok Mail List $a_ml added to $aDN.$ef";
	  $update_cnt++;
	}
      }
      
    }

  } elseif ($addr_type == 1) {

    // -- local address, but not a local user
    $return_addr = $test_uid . $local_domain;

  } else {

    // -- non-local address
    $return_addr = $a_addr;

  }

  return $return_addr;
}

// -------------------------------------------------------------
// main routine

$_SESSION['in_msg'] = '';
$warn = '<font color="#cc0000">';
$ok = '<font color="#00cc00">';
$ef = "</font><br>\n";

$pride_command_processor = 'mac-aliases@mail.macallister.grass-valley.ca.us';

// This array describes the "simple" attributes.  That is attributes
// that have a simple value.
$fld_list = array();
array_push ($fld_list, 'description');
array_push ($fld_list, 'mailservblocklimit');
array_push ($fld_list, 'mailservcomments');
array_push ($fld_list, 'mailservenvelopefrom');
array_push ($fld_list, 'mailserverrorreturnaddress');
array_push ($fld_list, 'mailserverrorstoaddress');
array_push ($fld_list, 'mailservlinelimit');
array_push ($fld_list, 'mailservmoderatoraddress');
array_push ($fld_list, 'mailservreplytoaddress');
array_push ($fld_list, 'mailservwarningstoaddress');
array_push ($fld_list, 'mailservtag');

if (strlen($btn_add)>0) {

  // -----------------------------------------------------
  // Add an LDAP Entry
  // -----------------------------------------------------

  if (ldap_maillist_admin() == 0) {
    $_SESSION['in_msg'] .= $warn."$in_localmailbox is inaccessible".$ef;
  } elseif (strlen($in_localmailbox)==0) {
    $_SESSION['in_msg'] .= "$warn Mail List is required.$ef";
  } elseif (strlen($in_description)==0) {
    $_SESSION['in_msg'] .= "$warn Description is required.$ef";
  } else {

    // check for duplicates first

    $filter = "(localmailbox=$in_localmailbox)";
    $attrs = array ('description');
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_search ($ds, $ldap_ml_base, $filter, $attrs);  
    $entries = ldap_get_entries($ds, $sr);
    $tmp_err = error_reporting($old_err);
    $ml_cnt = $entries["count"];

    if ($ml_cnt>0) {

      $tmp = $entries[0]['description'][0];
      $_SESSION['in_msg'] .= "$warn Mail List is already in use by $tmp.$ef";

    } else {

      // add the new entry

      $ldap_entry["objectclass"][] = "top";
      $_SESSION['in_msg'] .= "$ok adding objectClass = top$ef";
      $ldap_entry["objectclass"][] = "prideemaillist";
      $_SESSION['in_msg'] .= "$ok adding objectClass = prideemaillist$ef";
      $ldap_entry["localmailbox"][] = $in_localmailbox;
      $_SESSION['in_msg'] .= "$ok adding localMailBox = $in_localmailbox$ef";
      foreach ($fld_list as $fld) {
        $name = "in_$fld"; $val = $$name;
        if (strlen($val)>0) {
          $_SESSION['in_msg'] .= "$ok adding $fld = $val$ef";
          $ldap_entry[$fld][0] = $val;
        }
      }

      $ml_groups = array('mailalias',
	                 'maildelivery',
		         'mailfilter',
                         'mailservheaderaddition',
		         'manageruid');
      foreach ($ml_groups as $id) {
        $list = array();
        $name = "in_new_$id"; $new = trim($$name);

        // checked values from a copy operation
        $name = 'in_'.$id.'_cnt'; $id_cnt = $$name;
        for ($i=0; $i<$id_cnt; $i++) {
          $name = 'in_'.$id.'_'.$i;       $val  = $$name;
          $name = 'in_'.$id.'_flag_'.$i; $flag = $$name;
          if ($flag == 'Y') {$list["$val"]++;}
        }

        // values from the text input field
        if ($id == 'mailfilter') {
	  if (strlen($new)>0) {
            $name = "in_new_${id}_base"; $new_base = $$name;
            $name = "in_new_${id}_search"; $new_search = $$name;
            $tmp = 'ldap:///';
	    if ($new_base == 'both') {
	      $tmp .= $ldap_base;
            } else {
	      $tmp .= "ou=$new_base,$ldap_base";
	    }
	    $tmp .= '?mail';
            $tmp .= "?$in_new_mailfilter_search";
	    $tmp .= "?$in_new_mailfilter";
            $list["$tmp"]++;
	  }
        } else {
          $sep = ',';
          if ($id == 'mailservheaderaddition') {$sep = '|';}
          $a_ent = strtok($new, $sep);
          while (strlen($a_ent)>0) {
            $list["$a_ent"]++;
            $a_ent = strtok(',');
          }
        }
	foreach ($list as $v => $c) {
          $_SESSION['in_msg'] .= "$ok Adding $id = $v$ef";
	  if ($id == 'maildelivery') {
	    $fq_addr = local_user_update($v, 'ADD', $in_localmailbox);
	    if (strlen($fq_addr)>0) {
	      $ldap_entry["$id"][] = $fq_addr;
	    }
	  } else {
	    $ldap_entry["$id"][] = $v;
	  }
        }
      }

      // create authList Filter
      if (strlen($in_mailservauthlist_filter)>0) {
	$tmp = 'ldap:///';
	if ($in_mailservauthlist_base == 'both') {
	  $tmp .= $ldap_base;
        } else {
	  $tmp .= "ou=$in_mailservauthlist_base,$ldap_base";
	}
	$tmp .= '?mail';
        $tmp .= "?$in_mailservauthlist_search";
	$tmp .= "?$in_mailservauthlist_filter";
        $_SESSION['in_msg'] .= "$ok Adding mailFilter = $tmp$ef";
        $ldap_entry["mailservauthlist"][] = $tmp;
      }

      // create moderatorList Filter
      if (strlen($in_mailservmoderatorlist_filter)>0) {
	$tmp = 'ldap:///';
	if ($in_mailservmoderatorlist_base == 'both') {
	  $tmp .= $ldap_base;
        } else {
	  $tmp .= "ou=$in_mailservmoderatorlist_base,$ldap_base";
	}
	$tmp .= '?mail';
        $tmp .= "?$in_mailservmoderatorlist_search";
	$tmp .= "?$in_mailservmoderatorlist_filter";
        $_SESSION['in_msg'] .= "$ok Adding mailFilter = $tmp$ef";
        $ldap_entry["mailservmoderatorlist"][] = $tmp;
      }

      // add data to directory
      $this_dn = "localmailbox=$in_localmailbox,$ldap_ml_base";
      if (@ldap_add($ds, $this_dn, $ldap_entry)) {
        $_SESSION['in_msg'] .= "$ok Directory update complete.$ef";
      } else {
        $_SESSION['in_msg'] .= "$warn Problem adding $this_dn to directory$ef";
        $ldapErr = ldap_errno ($ds);
        $ldapMsg = ldap_error ($ds);
        $_SESSION['in_msg'] .= "$warn Error: $ldapErr, $ldapMsg<br>$ef";
      }

      // request pmdf update
      $subj = "New Alias creation requested";
      $msg = $subj . "\n"; 
      mail ($pride_command_processor,
            $subj,
            $msg);
      $_SESSION['in_msg'] .= "$ok Mail Server Update Requested$ef";
    }
  }

} elseif (strlen($btn_update)>0) {

  // -----------------------------------------------------
  // Update an LDAP Entry
  // -----------------------------------------------------

  $ldap_filter = 'objectclass=*';

  if (strlen($in_dn) == 0) {
      $_SESSION['in_msg'] .= "$warn No entry to update$ef";
      $ret_cnt = 0;
  } else {
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $ret_list = array();
    $sr = ldap_read ($ds, $in_dn, $ldap_filter, $ret_list);  
    $info = ldap_get_entries($ds, $sr);
    $err = ldap_errno ($ds);
    if ($err) {
      $err_msg = ldap_error ($ds);
      $_SESSION['in_msg'] .= "errors: $err $err_msg<br>\n";
    }
    $tmp_err = error_reporting($old_err);
    $ret_cnt = $info["count"];
  }

  if ($ret_cnt>0) {
    if (ldap_maillist_authorization($info[0]["manageruid"]) < 1) {
      $_SESSION['in_msg'] .= $warn."$in_localmailbox is inaccessible".$ef;
      $ret_cnt = 0;
      unset($info);
    }
  }

  if ($ret_cnt>0) {

    $add_cnt = 0;
    $add_data = array();
    $del_cnt = 0;
    $del_data = array();
    $update_cnt = 0;

    // -- simple attributes

    foreach ($fld_list as $fld) {

      $tmp = 'in_' . $fld;  $val_in   = trim($$tmp) . '';
      $val_ldap = trim($info[0]["$fld"][0]);

      if ( $val_in != $val_ldap ) {

        // delete attribute
        if (strlen($val_in)==0) {
          $del_cnt++;
          $del_data["$fld"] = $val_ldap;
          $_SESSION['in_msg'] .= "$ok $fld: $val_ldap deleted.$ef";
        } else {

          // try and replace it, if that fails try and add it
	  $d = array();
          $d["$fld"] = $val_in;
          $old_err = error_reporting(E_ERROR | E_PARSE);
          $r=ldap_mod_replace($ds, $in_dn, $d);
          $err = ldap_errno ($ds);
          $err_msg = ldap_error ($ds);
          $tmp_err = error_reporting($old_err);
          if ($err == 0) {
            $update_cnt++;
            $_SESSION['in_msg'] .= "$ok $fld replaced.$ef";
          } else {
            $add_cnt++;
            $add_data["$fld"] = $val_in;
            $_SESSION['in_msg'] .= "$ok $fld added.$ef";
          }
        }
      }
    }

    // -- single valued filters (add or delete only)

    $ml_filters = array('mailservauthlist',
                        'mailservmoderatorlist');

    foreach ($ml_filters as $id) {
      $name = 'in_'.$id; $old_filter = $$name;

      // check to see if we delete the old filter
      if (strlen($old_filter)>0) {
	$name = 'in_'.$id.'_flag'; $flag     = $$name;
	$name = 'in_'.$id;         $val_ldap = $$name;
        if (strlen ($flag)==0) {
	  $del_cnt++;
	  $del_data["$id"] = $val_ldap;
          $_SESSION['in_msg'] .= "$ok $id: $val_ldap deleted.$ef";
        }

      } else {

        // add a new filter if we have one
        $name = 'in_'.$id.'_base';   $ml_base = $$name;
        $name = 'in_'.$id.'_search'; $ml_search = $$name;
        $name = 'in_'.$id.'_filter'; $ml_filter = $$name;
        if (strlen($ml_filter)>0) {
        $val_in = 'ldap:///';
          if ($ml_base == 'both') {
	    $val_in .= $ldap_base;
          } else {
            $val_in .= "ou=$ml_base,$ldap_base";
          }
          $val_in .= '?mail';
          $val_in .= "?$ml_search";
          $val_in .= "?$ml_filter";
          $add_cnt++;
          $add_data["$id"] = $val_in;
          $_SESSION['in_msg'] .= "$ok $id added.$ef";
        }
      }
    }

    // -- handle multivalue attributes

    $ml_groups = array('mailalias',
		       'maildelivery',
		       'mailfilter',
                       'mailservheaderaddition',
		       'manageruid');

    foreach ($ml_groups as $id) {
      $list = array();

      // check for new values
      $name = "in_new_$id"; $new = trim($$name);
      if ($id == 'mailfilter') {
	if (strlen($new)>0) {
	  $tmp = 'ldap:///';
	  if ($in_new_mailfilter_base == 'both') {
	    $tmp .= $ldap_base;
	  } else {
	    $tmp .= "ou=$in_new_mailfilter_base,$ldap_base";
	  }
	  $tmp .= '?mail';
	  $tmp .= "?$in_new_mailfilter_search";
	  $tmp .= "?$in_new_mailfilter";
	  $list["$tmp"]++;
	}
      } else {
	if (strlen($new)>0) {
	  $delimiter = ',';
	  if ($id == 'mailservheaderaddition') {$delimiter = '|';}
	  $tmp = trim(strtok($new, $delimiter));
	  while (strlen($tmp)>0) {
	    $list["$tmp"]++; 
	    $tmp = trim(strtok($delimiter)); 
	  }
	}
      }

      // tot up the old values from the form
      $name = 'in_'.$id.'_cnt'; $id_cnt = $$name;
      for ($i=0; $i<$id_cnt; $i++) {
	$name = 'in_'.$id.'_'.$i;       $val  = $$name;
	$name = 'in_'.$id.'_flag_'.$i; $flag = $$name;
	if ($flag == 'Y') {
	  $list["$val"]++;
	} else {
	  $list["$val"] -= 1;
	}
      }

      if ($info[0][$id]['count']>0) {
	for ($i=0; $i<$info[0][$id]['count']; $i++) {
	  $val = $info[0][$id][$i];
	  $list["$val"] -= 1;
	}
      }

      // check out the check boxes to see what is checked or not checked
      foreach ($list as $val => $flag) {

	if ($flag>0) {

	  // add 

	  if ($id == 'manageruid') {
	    if (check_uid($val)>0) {
	      $add_cnt++;
	      $add_data["$id"][] = $val;
	      $_SESSION['in_msg'] .= "$ok $id of $val added.$ef";
	      add_emaillist_authorization($val);
	    } else {
	      $_SESSION['in_msg'] .= "$warn Invalid UID $val "
		 . "--- not added.$ef";
	    }

	  } elseif ($id == 'maildelivery') {
	    $fq_addr = local_user_update($val, 'ADD', $in_localmailbox);
	    if (strlen($fq_addr)>0) {
	      $add_cnt++;
	      $add_data["$id"][] = $fq_addr;
	      $_SESSION['in_msg'] .= "$ok $id of $val added.$ef";
	    }

	  } else {
	    $add_cnt++;
	    $add_data["$id"][] = $val;
	    $_SESSION['in_msg'] .= "$ok $id of $val added.$ef";
	  }

	} elseif ($flag<0) {

	  // -- delete
 
	  if ($id == 'maildelivery') {
	    $fq_addr = local_user_update($val, '', $in_localmailbox);
	    if (strlen($fq_addr)>0) {
	      $del_cnt++;
	      $del_data["$id"][] = $val;
	      $_SESSION['in_msg'] .= "$ok $id of $val deleted.$ef";
	    }

	  } else {
	    $del_cnt++;
	    $del_data["$id"][] = $val;
	    $_SESSION['in_msg'] .= "$ok $id of $val deleted.$ef";
	  }

	}
      }
    }

    // add attributes
    if ($add_cnt>0) {
      $old_err = error_reporting(E_ERROR | E_PARSE);
      $r = ldap_mod_add($ds, $in_dn, $add_data);
      $err = ldap_errno ($ds);
      $err_msg = ldap_error ($ds);
      $tmp_err = error_reporting($old_err);
      if ($err != 0) {
        $_SESSION['in_msg'] .= "$warn ldap error adding attributes: "
                 . "$err - $err_msg.$ef";
      }
    }

    // delete attributes
    if ($del_cnt>0) {
      $old_err = error_reporting(E_ERROR | E_PARSE);
      $r = ldap_mod_del($ds, $in_dn, $del_data);
      $err = ldap_errno ($ds);
      $err_msg = ldap_error ($ds);
      $tmp_err = error_reporting($old_err);
      if ($err>0) {
	$_SESSION['in_msg'] .= "$warn ldap error deleting attributes: "
	   . "$err - $err_msg.$ef";
      }
    }

    // request pmdf update
    if ($add_cnt+$del_cnt+$update_cnt>0) {
      $subj = "New Alias creation requested";
      $msg = $subj . "\n"; 
      mail ($pride_command_processor,
	    $subj,
	    $msg);
      $_SESSION['in_msg'] .= "$ok Mail Server Update Requested$ef";
    }

  }

} elseif (strlen($btn_delete)>0) {

  // -----------------------------------------------------
  // Delete an LDAP Entry
  // -----------------------------------------------------

  if (ldap_maillist_admin() == 0) {

    $_SESSION['in_msg'] .= $warn."$in_localmailbox is inaccessible".$ef;

  } else {

    // remove any references to list in maildistributionid
    $filter = "(&(objectclass=person)";
    $filter .= "(maildistributionid=$in_localmailbox))";
    $ret = array ('uid');
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $sr = ldap_search ($ds, $ldap_base, $filter, $ret);  
    $mid = ldap_get_entries($ds, $sr);
    $tmp_err = error_reporting($old_err);
    $mid_cnt = $mid["count"];
    for ($i=0; $i<$mid_cnt; $i++) {
      local_user_update($mid[$i]['uid'][0], '', $in_localmailbox);
    }

    // now delete the entry
    $old_err = error_reporting(E_ERROR | E_PARSE);
    $r = ldap_delete($ds, $in_dn);
    $err = ldap_errno ($ds);
    $err_msg = ldap_error ($ds);
    $tmp_err = error_reporting($old_err);
    if ($err == 0) {
      $_SESSION['in_msg'] .= "$ok $in_dn deleted.$ef";
    } else {
      $_SESSION['in_msg'] .= "$warn ldap error deleting $in_dn: "
	 . "$err - $err_msg.$ef";
    }
    $in_localmailbox = '';

    $subj = "Alias deletion requested";
    $msg = $subj . "\n"; 
    mail ($pride_command_processor,
	  $subj,
	  $msg);
    $_SESSION['in_msg'] .= "$ok Mail Server Update Requested$ef";

  }

} else {
  $_SESSION['in_msg'] .= "$warn Invalid Action$ef";
}
$a_url = 'maillist_maint.php';
if (strlen($in_localmailbox) > 0) {
  $a_url .= "?in_localmailbox=$in_localmailbox";
}
header ("REFRESH: 0; URL=$a_url");

?>

<html>
<head>
<title>PRIDE LDAP Directory Maintenance</title>
</head>
<body>
<a href="<?php echo $a_url;?>">Back to User Maintenance</a>
</body>
</html>
