<?php
# file: my_links_maint.php
# author: Bill MacAllister

# Open a session and check for authorization
require('whm_php_sessions.inc');
require('whm_php_auth.inc');
whm_auth("ldapadmin|user");

$title = 'Link Maintenance';
$heading = 'Link Maintenance';

require ('inc_header.php');
require('/etc/whm/macdir_auth.php');
$ds = ldap_connect($ldap_server);
$r  = ldap_bind($ds,
                $_SESSION['whm_directory_user_dn'],
                $_SESSION['whm_credential']);

if (strlen($in_cn)>0) {
    
    $return_attr = array();
    $link_base = $_SESSION['whm_directory_user_dn'];
    $link_filter = "(&(objectclass=pridelistobject)(cn=$in_cn))";
    $sr = @ldap_search ($ds, $link_base, $link_filter, $return_attr);
    $info = @ldap_get_entries($ds, $sr);
    $ret_cnt = $info["count"];
    if ($ret_cnt == 1) {
        $entry_found = 1;
    } elseif ($retcnt > 1) {
        $msg .= "More than on entry found for $ldap_filter search.\n";
    } else {
        $msg .= "No entry found.\n";
    }

    $chk_prideurlprivate_y = $chk_prideurlprivate_n = '';
    if ($info[0]['prideurlprivate'][0] == 'N') {
        $chk_prideurlprivate_n = 'CHECKED';
    } else {
        $chk_prideurlprivate_y = 'CHECKED';
    }

}

?>

<table border="0" width="100%">
<tr><td align="center" width="90%">

<form name="maint_find"
      method="post"
      action="<?php print $PHP_SELF; ?>">
<table border="0" width="100%">
<tr>
  <td align="right" width="50%">Common Name:</td>
  <td width="50%"><input type="text"
             name="in_cn"
             value="<?php print $in_cn;?>">
  </td>
</tr>
<tr>
  <td colspan="2" align="center">
    <input type="submit" name="btn_find" value="Lookup">
  </td>
</tr>
<?php
if (isset($msg)) {
  echo "<tr>\n";
  echo "  <td colspan=\"2\" align=\"center\">$msg</td>\n";
  echo "</tr>\n";
  $msg = '';
}
if (session_is_registered('in_msg')) {
  if (strlen($_SESSION['in_msg']) > 0) {
    echo "<tr>\n";
    echo "  <td colspan=\"2\" align=\"center\">".$_SESSION['in_msg']."</td>\n";
    echo "</tr>\n";
    $_SESSION['in_msg'] = '';
  }
}
?>
</table>
</form>

</td>
<td align="center" width="10%">

<script language="JavaScript">

/* ----------------- */
/* Verify input data */
/* ----------------- */

function checkIt() {

    var f;
    var i;
    var outData = "";
  
    EmptyField = "";
    f = document.maint;

    var nondigit = /\D/;
    var anumber = /^\d*\.?\d*$/;

    if (f.in_cn.value == EmptyField) {
        alert ("Please enter a Common Name");
        return false;
    }

    return true;
    
}

</script>

</td>
</tr>
</table>

<p>
<form name="maint"
      method="post"
      action="my_links_maint_action"
      onsubmit="return checkIt()">

<table border="1" cellpadding="2">

<tr>
  <td colspan="2" align="right">
    <a href="<?php echo $PHP_SELF;?>">Reset</a>
  </td>
</tr>

<tr>
  <td align="right">Description:</td>
  <td><input type="text" name="in_description" size="60"
             value="<?php echo $info[0]['description'][0];?>">
  </td>
</tr>

<tr>
  <td align="right">Common Name:</td>
  <td>
<?php if (strlen($info[0]['cn'][0]) > 0) { ?>
 <input type="hidden" name="in_cn" value="<?php echo $info[0]['cn'][0];?>">
 <?php echo $info[0]['cn'][0];?>
<?php } else { ?>
<input type="text" name="in_cn" value="">
<?php } ?>
  </td>
</tr>

<tr>
 <td align="right">View:</td>
    <td> 
      <input type="radio" 
            <?php echo $chk_prideurlprivate_n;?> name="in_prideurlprivate" 
             value="N">Public
      &nbsp;&nbsp;&nbsp;
      <input type="radio" 
            <?php echo $chk_prideurlprivate_y;?> name="in_prideurlprivate" 
             value="Y">Private
    </td>
</tr>

<tr>
 <td align="right">URL:</td>
 <td>
   <input type="text" size="50" name="in_prideurl" 
          value="<?php print $info[0]['prideurl'][0];?>">
 </td>
</tr>

<tr>
 <td align="right">Username:</td>
 <td>
   <input type="text" size="50" name="in_linkuid" 
          value="<?php print $info[0]['linkuid'][0];?>">
 </td>
</tr>

<tr>
 <td align="right">Password:</td>
 <td>
   <input type="text" size="50" name="in_pridecredential" 
          value="<?php print $info[0]['pridecredential'][0];?>">
 </td>
</tr>

<tr>
 <td colspan="2">

 <table border="0" width="100%">
 <tr>

 <?php if (strlen($info[0]['cn'][0]) > 0) {?>

 <td width="50%">
  <input type="submit" name="btn_update" value="Update">
 </td>
 <td width="50%" align="right">
  <input type="submit" name="btn_delete" value="Delete">
 </td>

<?php } else { ?>

 <td align="center">
  <input type="submit" name="btn_add" value="Add">
 </td>

<?php } ?>

</tr>
 </table>

 </td>
</tr>
</table>

<input type="hidden" name="in_dn" value="<?php echo $info[0]['dn'];?>">

</form>

<?php
 ldap_close($ds);
 require ('inc_footer.php');
?>
