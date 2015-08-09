<?php

// --------------------------------------------
// file: apps.php
// author: Bill MacAllister
// date: 15-Mar-2004

// --------------------------------------------------------------
// Check for admin access 

function admin_check ($right) {

  $admin_access = 0;

  if ( isset($_SESSION['WEBAUTH_LDAP_PRIVGROUP1']) ) { 
      $cnt = 1;
      while ($cnt > 0) {
          $pg = 'WEBAUTH_LDAP_PRIVGROUP'.$cnt;
          if ( isset($_SESSION[$pg]) ) {
              if ($_SESSION[$pg] == $right) {
                  $admin_access = 2;
                  $cnt = 0;
                  break;
              } else {
                  $cnt++;
              }
          } else {
              $cnt = 0;
          }
      }
  }
  return $admin_access;

}

// --------------------------------------------------------------
// Check for manager of an application

function manager_check ($mgr_array) {

  $access_okay = 0;

  // Check for admin
  $access_okay = admin_check('ldapadmin');

  if ($access_okay == 0) {
      for ($i=0; $i<$mgr_array["count"]; $i++) {
          if ( isset($_SESSION['WEBAUTH_USER']) ) { 
              if ($_SESSION['WEBAUTH_USER'] == $mgr_array[$i]) {
                  $access_okay = 1;
                  break;
              }
          }
      }
  }

  return $access_okay;

}

// ------------------------------------------------------------
// Main routine

$title = 'Software Applications';
$heading = 'Software Applications';
require('inc_header.php');
require('/etc/whm/macdir_auth.php');
require('inc_bind.php');
$ds = macdir_bind($ldap_server, 'GSSAPI');

// clean out the messages
$msg = '';

// define a limit for group display supression
$group_limit = 5;

// count of managed appications for non-admin types
$managed_cnt = 0;

// get the list
$filter = '(objectclass=prideApplication)';
$base_dn = "ou=applications,$ldap_base";
$return_attr = array('cn',
                     'description',
                     'manageruid',
                     'memberuid');

$sr = ldap_search($ds, $base_dn, $filter, $return_attr);  
$info = ldap_get_entries($ds, $sr);
$ret_cnt = $info["count"];
if ($ret_cnt) {
  for ($i=0; $i<$ret_cnt; $i++) {
    $a_dn            = $info[$i]["dn"];
    $app             = $info[$i]['cn'][0];
    $app_desc[$app]  = $info[$i]['description'][0];

    // get a list of managers
    $a_cnt = $info[$i]["manageruid"]["count"];
    $c = '';
    for ($j=0; $j<$a_cnt; $j++) {
      $u = $info[$i]['manageruid'][$j];
      $app_managers[$app] .= "$c $u";
      $c = ', ';
    }

    // create href for managers
    $user_type = manager_check($info[$i]['manageruid']);
    if ($user_type > 1) {
      $app_define[$app] = '<a href="app_define.php'
                      . '?in_cn=' . $app
                      . '"><img src="/macdir-images/icon-edit.png" border="0"></a>';
    } elseif ($user_type == 1) {
      $access_maint[$app] = 'yes';
      $managed_cnt++;
    }

    // get a list of users
    $a_cnt = $info[$i]["memberuid"]["count"];
    $c = '';
    for ($j=0; $j<$a_cnt; $j++) {
      $u = $info[$i]['memberuid'][$j];
      if ($user_type == 1) {
          $app_users[$app] 
              .= "$c <a href=\"app_access_maint.php?in_uid=$u\">$u</a>";
      } else {
          $app_users[$app] .= "$c $u";
      }
      $c = ', ';
    }

    // list of application groups
    $group = $app;
    if (preg_match("/^(.+?)[\-\_\.]/", $app, $matches)) {
      $group = $matches[1];
    }
    $app_group[$app] = $group;
    $group_list[$group]++;

  }

?>
<div align="center">

<form name="refresh"
      action="<?php echo $_SERVER['PHP_SELF'];?>"
      method="post">

<table border="0" cellpadding="20">
 <tr>
  <td>
     <input type="submit" name="in_button_refresh" value="Refresh">
  </td>
  <td>

<?php
   if ($user_type > 1) {

     // admin type user
     $group_header = 0;
     foreach ($group_list as $g => $gc) {
       if ($gc > $group_limit) {
           if ($group_header == 0) {
               $group_header = 1;
               echo "\n";
               echo "<table border=\"1\" cellpadding=\"2\">\n";
               echo " <tr>\n";
               echo "  <th>Show Group</th>\n";
               echo "  <th>Suppress Group</th>\n";
               echo "  <th>Group Identifier</th>\n";
               echo " </tr>\n";
           }
           $name = "show_$g";
           if ($$name == 'show') {
               $chked_show = ' CHECKED';
               $chked_hide = '';
           } else {
               $chked_hide = 'CHECKED';
               $chked_show = '';
               $$name = 'hide';
           }
           echo "  <td align=\"center\">\n";
           echo "    <input type=\"radio\"$chked_show\n";
           echo "           name=\"$name\"\n";
           echo "           value=\"show\">\n";
           echo "  </td>\n";
           echo "  <td align=\"center\">\n";
           echo "    <input type=\"radio\"$chked_hide\n";
           echo "           name=\"$name\"\n";
           echo "           value=\"hide\">\n";
           echo "  </td>\n";
           echo "  <td align=\"center\">$g</td>\n";
           echo " </tr>\n";
       }
     }
     if ($group_header>0) {
       echo "</table>\n";
     }
   } else {

     // user type user or not logged in yet
     if ($managed_cnt > 0) {
       if ($managed_only == 'no') {
           $chked_all = ' CHECKED';
           $chked_managed = '';
       } else {
           $chked_managed = ' CHECKED';
           $chked_all = '';
           $managed_only = 'yes';
       }
       echo "\n";
       echo "<table border=\"0\">\n";
       echo " <tr>\n";
       echo "  <td align=\"center\">\n";
       echo "    Show only Applications I manage:\n";
       echo "    Yes <input type=\"radio\"$chked_managed\n";
       echo "           name=\"managed_only\"\n";
       echo "           value=\"yes\">\n";
       echo "  </td>\n";
       echo "  <td align=\"center\">\n";
       echo "    No <input type=\"radio\"$chked_all\n";
       echo "           name=\"managed_only\"\n";
       echo "           value=\"no\">\n";
       echo "</table>\n";
     }
   }
?>

  </td>
 </tr>
</table>
</form>

<br>

<table border="1" cellpadding="2">
<tr>
  <th>Application ID</th>
  <th>Description</th>
  <th>Managers</th>
  <th>Users</th>
</tr>

<?php
  ksort($app_desc);
  foreach ($app_desc as $thisApp => $thisDescription) {
    $display_it = 1;
    $thisAccessMaint = $access_maint[$thisApp];
    $thisAppDefine   = $app_define[$thisApp];
    $thisManagers    = $app_managers[$thisApp];
    $thisUsers       = $app_users[$thisApp];
    if ($managed_cnt > 0) {
      if ($managed_only == 'yes' && strlen($thisAccessMaint) == 0) {
          $display_it = 0;
      }
    } else {
      if ($group_list[$app_group[$thisApp]]>$group_limit) {
          $name = 'show_'.$app_group[$thisApp];
          if ($$name == 'hide') {$display_it = 0;}
      }
    }
    if ($display_it>0) {
?>
<tr>
   <td><?php echo $thisAppDefine;?>
       <?php echo $thisApp;?></a></td>
   <td><?php echo $thisDescription;?></td>
   <td><?php echo $thisManagers;?>&nbsp;</td>
   <td><?php echo $thisUsers;?></td>
</tr>
<?php
    }
  }
}

?>
</table>
</div>

<?php require('inc_footer.php');?>
