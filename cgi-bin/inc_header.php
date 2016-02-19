<?php
$page_dir = dirname($_SERVER['SCRIPT_FILENAME']);
$page_name = substr($_SERVER['SCRIPT_FILENAME'], strlen($page_dir));
$page_root = strtok($page_name,'.');
if (substr($page_root,0,1)=='/') {$page_root = substr($page_root,1);}

require('/etc/whm/macdir_admin.php');

// figure out the access level
$ldap_admin   = 0;
$phone_admin  = 0;
$this_user    = '';
$menuLoggedIn = 0;
if ( isset($_SERVER['REMOTE_USER']) ) {
    $this_user    = $_SERVER['REMOTE_USER'];
    $menuLoggedIn = 1;
}
if ($menuLoggedIn) {
    // Just set this to admin for now.  In the future there will be
    // multiple levels of access to the directory.
    if (isset($macdir_admin[$this_user])) {
        $ldap_admin = 1;
    }
    if ( isset($_SERVER['WEBAUTH_LDAP_GROUP']) ) {
        if ($_SERVER['WEBAUTH_LDAP_GROUP'] == 'ldap:admin') {
            $ldap_admin = 1;
        }
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
