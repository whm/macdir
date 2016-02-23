<?php
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_cn = empty($_REQUEST['in_cn']) ? '' : $_REQUEST['in_cn'];
// ----------------------------------------------------------
//
# file: my_links_maint.php
# author: Bill MacAllister

$title = 'Link Maintenance';
$heading = 'Link Maintenance';

require('inc_init.php');
require('/etc/whm/macdir.php');
require('inc_header.php');

$ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');

if (isset($in_cn)) {
    
    $return_attr = array();
    $link_base = 'uid='.$_SERVER['REMOTE_USER'].','.$ldap_user_base;
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

<form name="maint_find"
      method="post"
      action="<?php print $_SERVER['PHP_SELF']; ?>">
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
    <input type="submit" name="in_button_find" value="Lookup">
  </td>
</tr>
<?php
if (isset($msg)) {
  echo "<tr>\n";
  echo "  <td colspan=\"2\" align=\"center\">$msg</td>\n";
  echo "</tr>\n";
  $msg = '';
}
if (isset($_SESSION['in_msg'])) {
    echo "<tr>\n";
    echo "  <td colspan=\"2\" align=\"center\">".$_SESSION['in_msg']."</td>\n";
    echo "</tr>\n";
    $_SESSION['in_msg'] = '';
}
?>
</table>
</form>

<form name="maint"
      method="post"
      action="my_links_maint_action.php"
      onsubmit="return checkIt()">

<table border="1" cellpadding="2" align="center">

<tr>
  <td colspan="2" align="right">
    <a href="<?php echo $_SERVER['PHP_SELF'];?>">Reset</a>
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
<?php if ( isset($info[0]['cn'][0]) ) { ?>
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

 <?php if ( isset($info[0]['cn'][0]) ) {?>

 <td width="50%">
  <input type="submit" name="in_button_update" value="Update">
 </td>
 <td width="50%" align="right">
  <input type="submit" name="in_button_delete" value="Delete">
 </td>

<?php } else { ?>

 <td align="center">
  <input type="submit" name="in_button_add" value="Add">
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
