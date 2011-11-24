<?php
$page_dir = dirname($SCRIPT_NAME);
$page_name = substr($SCRIPT_NAME,strlen($page_dir));
$page_root = strtok($page_name,'.');
if (substr($page_root,0,1)=='/') {$page_root = substr($page_root,1);}

// figure out the access level
$ldap_admin = $phone_admin = 0;
$this_user = '';
$menuLoggedIn = (strlen($_SESSION['whm_directory_user']) > 0);
if ($menuLoggedIn>0) {
  $this_user = $_SESSION['whm_directory_user'];
  if (whm_auth_check_policy('phoneadmin')) {$phone_admin = 1;}
  if (whm_auth_check_policy('ldapadmin')) {
    $ldap_admin = 1; 
    $phone_admin = 0;
  }
}


header("Content-Type: text/html; charset=UTF-8");
?>
<html>
<head>
<title><?php print $title;?></title>
<link rel="stylesheet" type="text/css" href="/macdir-styles/macdir.css">
</head>

<body>
<div id="wrap">

 <div id="header">
 <h1>MacAllister Directory</h1>
 </div>

 <div id="main">
