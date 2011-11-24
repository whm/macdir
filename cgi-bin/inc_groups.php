<?php

// pam groups
$g_filter = "(objectclass=pamGroup)";
$g_attrs = array ('cn','description');
$old_err = error_reporting(E_ERROR | E_PARSE);
$sr = ldap_search ($ds, $ldap_groupbase, $g_filter, $g_attrs);  
$g_group = ldap_get_entries($ds, $sr);
$tmp_err = error_reporting($old_err);
$pam_group_cnt = $g_group["count"];

$pam_groups = array();
if ($pam_group_cnt >0) {
  for ($g=0; $g<$pam_group_cnt; $g++) {
    $pam_groups[$g_group[$g]['cn'][0]] = 
       $g_group[$g]['description'][0] . ' ('.$g_group[$g]['cn'][0].')';
  }
  asort($pam_groups);
}

// posix groups
$g_filter = "(objectclass=posixGroup)";
$g_attrs = array ('cn','description');
$old_err = error_reporting(E_ERROR | E_PARSE);
$sr = ldap_search ($ds, $ldap_groupbase, $g_filter, $g_attrs);  
$g_group = ldap_get_entries($ds, $sr);
$tmp_err = error_reporting($old_err);
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

// posix groups for file services
$g_filter = "(&(objectclass=posixGroup)(cn=fs-*))";
$g_attrs = array ('cn','description');
$old_err = error_reporting(E_ERROR | E_PARSE);
$sr = ldap_search ($ds, $ldap_groupbase, $g_filter, $g_attrs);
$g_group = ldap_get_entries($ds, $sr);
$tmp_err = error_reporting($old_err);
$fs_group_cnt = $g_group["count"];

$fs_groups = array();
if ($fs_group_cnt >0) {
  for ($g=0; $g<$fs_group_cnt; $g++) {
    $z = ''; 
    if (isset($g_group[$g]['description'][0])) {
      $z = $g_group[$g]['description'][0];
    }
    $fs_groups[$g_group[$g]['cn'][0]] = $g_group[$g]['cn'][0] . ' ' . $z;
  }
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

// get a list of NT Domains
$g_filter = "(objectclass=sambaDomain)";
$g_attrs = array ('sambadomainname','sambasid','cn','iphostnumber');
$sr = @ldap_search ($ds, $ldap_domainbase, $g_filter, $g_attrs);
$g_group = @ldap_get_entries($ds, $sr);
$samba_domain_cnt = $g_group["count"];

$samba_domains = array();
if ($samba_domain_cnt >0) {
    for ($g=0; $g<$samba_domain_cnt; $g++) {
        $aDomainName = $g_group[$g]['sambadomainname'][0];
        $samba_domains[$aDomainName] = $g_group[$g]['sambasid'][0];
        $samba_domain_info[$aDomainName]['pdchost'] = $g_group[$g]['cn'][0];
        $samba_domain_info[$aDomainName]['ip'] = $g_group[$g]['iphostnumber'][0];
    }
    asort($samba_domains);
}

?>