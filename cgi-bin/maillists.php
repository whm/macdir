<?php
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_listid  = $_REQUEST['in_listid'];
// ----------------------------------------------------------
//

// --------------------------------------------
// file: maillists.php
// author: Bill MacAllister
// date: 7-Jul-2003

$title = 'Search for a Mail Distribution List';
$heading = 'MacAllister Mail Lists';
require('inc_header.php');
require ('/etc/whm/macdir_auth.php');
require ('inc_maillist_auth.php');

?>

<div align="center">

<form name="maillist_find"
      method="post"
      action="<?php print $_SERVER['PHP_SELF']; ?>">
<table border="0">
<tr>
  <th align="right">Mail List or Alias:</th>
  <td><input type="text"
             name="in_listid"
             value="<?php print $in_listid;?>">
  </td>
</tr>
<tr>
  <td colspan="2" align="center">
    <input type="submit" name="btn_find" value="Lookup">
  </td>
</tr>
</table>
</form>


<?php

$filter = '(objectclass=prideEmailList)';
if ( strlen($in_listid)>0 ) {
  $filter = "(&$filter(|(localmailbox=*$in_listid*)(mailalias=*$in_listid*)))";
}

$base_dn = "ou=maillists,$ldap_base";
$return_attr = array('localmailbox',
		     'mailalias',
		     'manageruid',
		     'description');

$ds = ldap_connect($ldap_server);
$r  = ldap_bind($ds,$ldap_manager,$ldap_password);
$sr = ldap_search($ds, $base_dn, $filter, $return_attr);  
$info = ldap_get_entries($ds, $sr);
$ret_cnt = $info["count"];
if ($ret_cnt) {
  $high = "<font color=\"red\">$in_listid</font>";
  for ($i=0; $i<$ret_cnt; $i++) {
    $a_dn = $info[$i]["dn"];
    $mbx            = $info[$i]['localmailbox'][0];
    $ml_desc[$mbx]  = $info[$i]['description'][0];
    $ml_id[$mbx] = $mbx;
    if ( strlen($in_listid)>0 ) {
      $ml_id[$mbx] = str_replace ($in_listid, $high, $mbx);
    }
    $ml_maint[$mbx] = '';
    if (ldap_maillist_authorization($info[$i]['manageruid']) > 0) {
      $ml_maint[$mbx] = '<a href="maillist_maint.php'
                      . '?in_localmailbox=' . $mbx
                      . '"><img src="/macdir-images/icon-edit.png" border="0"></a>';
    }


    $ml_alias[$mbx] = '';
    $a_cnt = $info[$i]["mailalias"]["count"];
    $c = '';
    for ($j=0; $j<$a_cnt; $j++) {
      $z = $info[$i]['mailalias'][$j];
      if ( strlen($in_listid)>0 ) {
	$z = str_replace ($in_listid, $high, $info[$i]['mailalias'][$j]);
      }
      $ml_alias[$mbx] .= $c.$z;
      $c = ', ';
    }
  }
?>
<br>

<table border="1" cellpadding="2">
<tr>
  <th>Mail List</th>
  <th>Description</th>
  <th>Aliases</th>
</tr>

<?php
  ksort($ml_desc);
  foreach ($ml_desc as $thisList => $thisDescription) {
    $thisAlias = $ml_alias[$thisList];
    $thisMaint = $ml_maint[$thisList];
    $thisID    = $ml_id[$thisList];
?>
<tr>
   <td><?php echo $thisMaint;?>
       <a href="maillist_details.php?in_localmailbox=<?php echo $thisList;?>"><img
              src="/macdir-images/icon-view-details.png" border="0"></a>
       <?php echo $thisID;?></a></td>
   <td><?php echo $thisDescription;?> &nbsp;</td>
   <td><?php echo $thisAlias;?> &nbsp;</td>
</tr>
<?php
  }
} else {
  echo "<font color=\"red\">\n";
  echo "No matching entry found.<br>\n";
  echo "Try a less restrictive search.<br>\n";
  echo "</font>\n";
}

?>
</table>
</div>

<?php require('inc_footer.php');?>

