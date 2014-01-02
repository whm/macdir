<?PHP
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_uid  = $_REQUEST['in_uid'];
$in_emp  = $_REQUEST['in_emp'];
// ----------------------------------------------------------
//
// -------------------------------------------------------------
// change_password.php
// author: Bill MacAllister
// date: December 2002
//

// Open a session 
require('whm_php_sessions.inc');
require('whm_php_auth.inc');

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

$title = 'Password Change';
$heading = 'Password Change';
require ('inc_header.php');
require('/etc/whm/macdir_auth.php');

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
      action="change_password_action.php" 
      onsubmit="return check_password()"
      method="post">
<table border="1">
<tr>
 <td align="right">Username:</td>
 <td> <input type="text" name="in_uid"
             value="<?php print $in_uid; ?>">
 </td>
</tr>
<tr>
 <td align="right">Old Password:</td>
 <td> <input type="password" 
             name="in_old_password"> 
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
   <input type="hidden" name="in_emp" value="<?php print $in_emp;?>">
   <input type="submit" name="btn_update" value='Update'>
 </td>
</tr>
<?php
    if (isset($_SESSION['s_msg'])) {
        echo "<tr><td colspan=\"2\" align=\"center\">\n";
        echo $_SESSION['s_msg']."\n";
        echo "</td></tr>\n";
        $_SESSION['s_msg'] = '';
    }
?>
</table>
</form>
</div>

<?php
 require ('inc_footer.php'); 
?>
