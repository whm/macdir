<?php 

// --------------------------------------------------------------
// Check for admin access to mail distrubution lists

function ldap_maillist_admin () {

  $admin_access = 0;

  if (count($_SESSION['whm_rights_list']) > 0) {
    foreach  ($_SESSION['whm_rights_list'] as $rights_id) {
      if ($rights_id == 'email-admin') {
	$admin_access = 2;
	break;
      }
    }
  }
  return $admin_access;

}

// --------------------------------------------------------------
// Check for access to a given mail distrubution list
// given the ldap entry for the list.

function ldap_maillist_authorization ($mgr_array) {

  $access_okay = 0;

  // Check for admin
  $access_okay = ldap_maillist_admin();

  if ($access_okay == 0) {
    for ($i=0; $i<$mgr_array["count"]; $i++) {
      if ($_SESSION['whm_directory_user'] 
	  == $mgr_array[$i]) {
	$access_okay = 1;
	break;
      }
    }
  }

  return $access_okay;

}

?>