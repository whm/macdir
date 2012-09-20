<?php

// where the groups are
$app_base = 'ou=applications,'.$pidir_base;

// get a list of application group managed by this user

$this_user = 'unknownUser';
if ( isset($_SESSION['WEBAUTH_USER']) ) { 
    $this_user = $_SESSION['WEBAUTH_USER'];
}

$g_filter = '(&';
$g_filter .= '(objectclass=prideApplication)';
$g_filter .= '(managerUid='.$this_user.')';
$g_filter .= ')';
$g_attrs = array ('cn','description');
$old_err = error_reporting(E_ERROR | E_PARSE);
$sr = ldap_search ($ds, $app_base, $g_filter, $g_attrs);  
$g_group = ldap_get_entries($ds, $sr);
$tmp_err = error_reporting($old_err);
$mgr_group_cnt = $g_group["count"];

$mgr_groups = array();
if ($mgr_group_cnt >0) {
  for ($g=0; $g<$mgr_group_cnt; $g++) {
    $mgr_groups[$g_group[$g]['cn'][0]] = $g_group[$g]['description'][0];
  }
  asort($mgr_groups);
}

?>