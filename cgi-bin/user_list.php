<?php
// Open a session

$title = 'PRIDE Directory Search Results';
$heading = 'Employee Directory';

require('inc_init.php');
require('inc_header.php');
require('/etc/whm/macdir.php');

$ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');
?>

<!-- Main body of document -->

<?php

// create a form to attribute mapping
$form["in_firstname"]        = "givenname";
$form["in_lastname"]         = "sn";
$form["in_title"]            = "title";
$form["in_location"]         = "location";
$form["in_postaladdress"]    = "postaladdress";
$form["in_telephone_number"] = "telephonenumber";
$form["in_email"]            = "mail";

// construct the filter
$filter = '(&(objectclass=person)';
do {
  $a_entry = key ($form);
  $a_val = $$a_entry;
  if (strlen($a_val) > 0) {
    $a_attr = $form["$a_entry"];
    $filter .= "($a_attr=*$a_val*)";
    $session_name = "SEAR_$a_entry";
    session_register("$session_name");
    $_SESSION["$session_name"] = $a_val;
  }
} while (next ($form));
$filter .= ')';
$base_dn = 'dc=whm,dc=com';
$return_attr = array('cn','mail','telephonenumber','employeenumber');

$sr = ldap_search($ds, $base_dn, $filter, $return_attr);  
$info = ldap_get_entries($ds, $sr);
$ret_cnt = $info["count"];
if ($ret_cnt) {
  echo "<table border=\"1\">\n";
  echo "<tr>\n";
  echo " <th>Common Name</th>\n";
  echo " <th>Employee Number</th>\n";
  echo " <th>Email Address</th>\n";
  echo " <th>Telephone Number</th>\n";
  echo "</tr>\n";
  for ($i=0; $i<$info["count"]; $i++) {
    $a_dn = $info[$i]["dn"];
    $a_dn_url = urlencode($a_dn);
    $a_cn = $info[$i]["cn"][0];
    $a_mail = $info[$i]["mail"][0];
    $a_phone = $info[$i]["telephonenumber"][0];
    $a_employeenumber  = $info[$i]["employeenumber"][0];
    echo "<tr>\n";
    echo " <td><a href=\"user_details.php?dn=$a_dn_url\">$a_cn</a></td>\n";
    echo " <td align=\"center\">$a_employeenumber</td>\n";
    echo " <td>$a_mail</td>\n";
    echo " <td>$a_phone</td>\n";
    echo "</tr>\n";
  }
  echo "</table>\n";
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
