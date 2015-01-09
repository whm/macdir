<?php
// File: get_users.php
// Fate: 15-Jun-2004
// Author: Bill MacAllister

// get ldap information
require('/etc/whm/macdir_auth.php');

$return_attr[] = 'cn';
$return_attr[] = 'uid';

$filter = '(&';
$filter .= '(objectclass=person)';
$filter .= '(mail=*)';
$filter .= ')';

$ds = ldap_connect($ldap_server);
$r  = ldap_bind($ds,"","");
$sr = ldap_search($ds, $ldap_base, $filter, $return_attr);  
$info = ldap_get_entries($ds, $sr);
$ret_cnt = $info["count"];

for ($i=0; $i<$ret_cnt; $i++) {
  $a_cn = $info[$i]["cn"][0];
  $a_uid = $info[$i]["uid"][0];
  $select_list[$a_uid] = $a_cn;
}
asort($select_list);

?>
<html>
<head>
<title>Pick Users</title>

<script language="JavaScript">

/* ----------------- */
/* Set mailDelivery  */
/* ----------------- */

function setMailDelivery () {

  var f = document.selectUsers;
  var val = "";
  var c = "";
  for (var i=0; i<f.select_users.length; i++) {
    if (f.select_users[i].selected) {
      val += c + f.select_users.options[i].value;
      c = ",";
    }
  }
  window.opener.document.maillist_maint.in_new_maildelivery.value = val; 

  window.close();

}
</script>

</head>

<body bgcolor="#eeeeff">
<h3>Pick Some Users</h3>
<form name="selectUsers"
      onsubmit="return setMailDelivery()">
<select name="select_users" size="16" multiple>
<?php
foreach ($select_list as $a_uid => $a_cn) {
  echo "<option value=\"$a_uid\">$a_cn ($a_uid)\n";
}
?>
</select>
<input type="submit" name="in_button_submit" value="Set Mail Delivery">
</form>

</body>
</html>