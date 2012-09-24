<?php 

$title = 'MacAllister Directory Search Details';
$heading = 'MacAllister Directory';
require('inc_macdir.php');
require ('inc_header.php');

?>

<!-- Main body of document -->
<div align="center">
<a href="user_details">Return to User Display</a>
</div>
<?php

$base_dn = $_REQUEST['dn'];
$filter = '(objectclass=person)';
$dn_array = ldap_explode_dn ($_REQUEST['dn'], 1);
$in_uid = $dn_array[0];

$sr = ldap_read($macdirDS, $base_dn, $filter);  
if ($entry = ldap_first_entry ($macdirDS, $sr)) {
  echo "<table border=\"1\">\n";
  echo "<tr>\n";
  echo " <th>Attribute</th>\n";
  echo " <th>Value</th>\n";
  echo "</tr>\n";
  $attrs = ldap_get_attributes ($macdirDS, $entry);
  $attr_cnt = $attrs["count"];
  $attr_list = array();
  for ($i=0;$i<$attr_cnt;$i++) {
    if (preg_match('/password/i',$attrs[$i])) {continue;}
    array_push ($attr_list, $attrs[$i]);
  }
  asort ($attr_list);
  foreach ($attr_list as $this_attr) {
    $err_level = error_reporting (E_ERROR | E_PARSE);
    $vals = ldap_get_values ($macdirDS, $entry, $this_attr);
    error_reporting ($err_level);
    $val_cnt = $vals["count"];
    if ($val_cnt > 0) {
      echo "<tr> <td>$this_attr</td> <td>\n";
      $newline = '';
      for ($j=0;$j<$val_cnt;$j++) {
        $this_val = $vals[$j];
        echo "$newline $this_val";
        $newline = "\n<br>";
      }
      echo "</td> </tr>\n";
    }
  }
  echo "</table>\n";
  echo "<p>\n";
} else {
  echo "<p>\n";
  echo "<div align=\"center\">\n";
  echo "<font face=\"Arial, Helvetica, sans-serif\"\n";
  echo "      size=\"+1\"\n";
  echo "      color=\"#FF0000\">No entries found.</font>\n";
  echo "</div>\n";
}
?>

<!-- end of document body -->
<?php require ('inc_footer.php');?>
