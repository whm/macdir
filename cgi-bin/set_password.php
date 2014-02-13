<?PHP
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_uid  = $_REQUEST['in_uid'];
// ----------------------------------------------------------
//
// -------------------------------------------------------------
// set_password.php
// author: Bill MacAllister
// date: October 2002
//
session_start();

// -- Print a space or the field
function prt ($fld) {
  $str = trim ($fld);
  if (strlen($str) == 0) {
    $str = "&nbsp;";
  }
  return $str;
}

//-------------------------------------------------------------
// Start of main processing for the page

$title = 'System Manager Password Maintenance';
$heading = 'Password Maintenance';

require ('inc_header.php');
require('/etc/whm/macdir_auth.php');
$ds = ldap_connect($ldap_server);

?>

<script language="JavaScript">
// ------------------------------------
// Check password entry
function check_password () {
 var f;
 var i;
 f = document.setpw;
 if (f.in_new_password.value != f.in_verify_password.value) {
    alert ("Password entry and Verify entry don't match");
    return false;
 }
}
</script>

<div align="center">
<p>

<form name="setpw"
      action="set_password_action.php"
      onsubmit="return check_password()"
      method="post">
<input type="hidden" name="in_uid" value="<?php print $in_uid; ?>">
<table border="1">
<tr>
 <td align="right">Username:</td>
 <td> <input type="text" name="in_uid"
             value="<?php print $in_uid; ?>">
 </td>
</tr>
<tr>
 <td align="right">New Password:</td>
 <td> <input type="password"
             name="in_new_password">
 </td>
</tr>
<tr>
 <td align="right">Verify New Password:</td>
 <td> <input type="password"
             name="in_verify_password">
 </td>
</tr>
<tr>
 <td colspan="2" align="center">
   <input type="submit" name="in_button_update" value='Update'>
 </td>
</tr>
<?php
  if (isset ($_SESSION['s_msg']) && strlen ($_SESSION['s_msg']) > 0) {
    echo "<tr><td colspan=\"2\" align=\"center\">\n";
    echo $_SESSION['s_msg']."\n";
    echo "</td></tr>\n";
    $s_msg = '';
  }
?>
</table>
</form>
<p>
<a href="user_maint.php?in_uid=<?php print $in_uid;?>">
Back to User Maintenance</a>
</div>

<?php
 ldap_close($ds);
 require ('inc_footer.php');
?>
