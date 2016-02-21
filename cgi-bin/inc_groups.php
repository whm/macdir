<?php

// posix groups
$g_filter = "(objectclass=posixGroup)";
$g_attrs  = array ('cn','description');
$old_err  = error_reporting(E_ERROR | E_PARSE);
$sr       = ldap_search ($ds, $ldap_groupbase, $g_filter, $g_attrs);  
$g_group  = ldap_get_entries($ds, $sr);
$tmp_err  = error_reporting($old_err);

$posix_group_cnt = $g_group["count"];

$posix_groups = array();
if ($posix_group_cnt >0) {
  for ($g=0; $g<$posix_group_cnt; $g++) {
    $z = ''; 
    if (isset($g_group[$g]['description'][0])) {
      $z = $g_group[$g]['description'][0];
    }
    $posix_groups[$g_group[$g]['cn'][0]] = $g_group[$g]['cn'][0] . ' ' . $z;
  }
  asort($posix_groups);
}

// get a list of application groups 
$g_filter = "(objectclass=prideApplication)";
$g_attrs = array ('cn','description');
$sr = @ldap_search ($ds, $ldap_base, $g_filter, $g_attrs);  
$g_group = @ldap_get_entries($ds, $sr);
$app_group_cnt = $g_group["count"];

$app_groups = array();
if ($app_group_cnt >0) {
  for ($g=0; $g<$app_group_cnt; $g++) {
    $app_groups[$g_group[$g]['cn'][0]] = 
       $g_group[$g]['description'][0] . ' ('.$g_group[$g]['cn'][0].')';
  }
  asort($app_groups);
}
?>