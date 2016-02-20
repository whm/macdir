<?php
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_cn  = $_REQUEST['in_cn'];
// ----------------------------------------------------------
//
// file: app_define.php
// author: Bill MacAllister
// date: 18-Jan-2003
// description: This form is for creating application access control
//              structures.

$title = 'Application Definition';
$heading = 'Application Definition';

require('inc_header.php');
require('/etc/whm/macdir.php');
require('inc_bind.php');
$ds = macdir_bind($ldap_server, 'GSSAPI');

$app_base = 'ou=applications,'.$ldap_base;

// ---------------------------------------------------------
// Lookup an entry

$entry_found = 0;
$thisDN = '';

$warn = '<font color="#cc0000">';
$ok = '<font color="#00cc00">';
$ef = "</font><br>\n";

$ldap_filter = '';
if (strlen($in_cn)>0) {
  $ldap_filter = "cn=$in_cn";
}

if (strlen($ldap_filter)>0) {

  $return_attr = array();
  $sr = @ldap_search ($ds, $app_base, $ldap_filter, $return_attr);
  $info = @ldap_get_entries($ds, $sr);
  $ret_cnt = $info["count"];
  if ($ret_cnt > 0) {
     $entry_found = 1;
     $in_cn = $info[0]["cn"][0];
  } else {
     $msg .= "$warn No entry found. ($ldap_filter)$ef\n";
  }

  if ($entry_found) {
    $thisDN = $info[0]["dn"];
  }

}

?>

<table border="0" width="100%">
<tr><td align="center" width="90%">

  <form name="app_define_find"
        method="post"
        action="<?php print $_SERVER['PHP_SELF']; ?>">
  <table border="0" width="100%">
  <tr>
    <td align="right">Common Name:</td>
    <td align="left"><input type="text"
             name="in_cn"
             value="<?php print $in_cn;?>">
    </td>
  </tr>
  <tr>
    <td align="center" colspan="2">
      <input type="submit" name="in_button_find" value="Lookup">
    </td>
  </tr>
  <?php
  if (strlen($msg)>0) {
    echo "  <tr>\n";
    echo "    <td colspan=\"2\" align=\"center\">$msg</td>\n";
    echo "  </tr>\n";
    $msg = '';
  }
  if ( isset($_SESSION['in_msg']) ) {
    if (strlen($_SESSION['in_msg']) > 0) {
      echo "  <tr>\n";
      echo "    <td colspan=\"2\"\n";
      echo "        align=\"center\">".$_SESSION['in_msg']."</td>\n";
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
  f = document.app_define;

  var nondigit = /\D/;
  var anumber = /^\d*\.?\d*$/;

  if (f.in_cn.value == EmptyField) {
    alert ("Please enter a Common Name");
    return false;
  }
  if (f.in_description.value == EmptyField) {
    alert ("Please enter a Description.");
    return false;
  }

  return true;

}

</script>

<form name="reset"
      method="post"
      action="<?php print $_SERVER['PHP_SELF']; ?>">
<input type="hidden" name="in_uid" value="">
<input type="submit" name="reset" value="Reset">
</form>

</td>
</tr>
</table>

<p>
<form name="app_define"
      method="post"
      action="app_define_action.php"
      onsubmit="return checkIt()">

<input type="hidden" name="in_dn"
       value="<?php print $thisDN;?>">

<table border="1" cellpadding="2">

<!-- ------------------ Common Name ----------------------- -->
<tr>
 <td align="right">Common Name:</td>
 <td>
<?php
  if ($entry_found>0) {
    echo "$in_cn\n";
    echo "<input type=\"hidden\" name=\"in_cn\" value=\"$in_cn\">\n";
  } else {
?>
     <input type="text"
            size="40"
            name="in_cn"></td>
<?php 
  }
?>
 </td>
</tr>

<!-- ------------------ Descriptioin ----------------------- -->

<tr>
 <td align="right">Description:</td>
 <td><input type="text"
            size="60"
            name="in_description"
            value="<?php print $info[0]["description"][0];?>"></td>
</tr>

<!-- ------------------ Application Users ----------------------- -->

<tr>
 <td align="right">Application Users: </td>
 <td>
<?php
  $br = '';
  $mem_cnt = $info[0]["memberuid"]["count"];
  if ($mem_cnt > 0) {
    $mem = array();
    for ($memEntry=0; $memEntry<$mem_cnt; $memEntry++) {  
      $mem[] = $info[0]["memberuid"][$memEntry];
    }
    sort($mem);
    $cnt_m = 0;
    foreach ($mem as $m) {
      print $br;
?>
     <input type="checkbox" CHECKED
            name="inMemberUID_<?php print $cnt_m;?>"
            value="<?php print $m;?>"><?php print "$m\n";?>
     <input type="hidden"
            name="inMemberUIDList_<?php print $cnt_m;?>"
            value="<?php print $m;?>">
<?php
      $cnt_m++;
      $br = "      <br>\n";
    }
  }
  print $br;
?>
     <input type="hidden"
            name="inMemberUIDCnt"
            value="<?php print $mem_cnt;?>">

 <input type="text"
        name="inMemberUIDNew">
 </td>
</tr>

<!-- ------------------ Application Managers ----------------------- -->
<tr>
 <td align="right">Application Managers: </td>
 <td>
<?php
  $br = '';
  $mgr_cnt = $info[0]["manageruid"]["count"];
  if ($mgr_cnt > 0) {
    $mgr = array();
    for ($mgrEntry=0; $mgrEntry<$mgr_cnt; $mgrEntry++) {  
      $mgr[] = $info[0]["manageruid"][$mgrEntry];
    }
    sort($mgr);
    $cnt_m = 0;
    foreach ($mgr as $m) {
      print $br;
?>
     <input type="checkbox" CHECKED
            name="inManagerUID_<?php print $cnt_m;?>"
            value="<?php print $m;?>"><?php print "$m\n";?>
     <input type="hidden"
            name="inManagerUIDList_<?php print $cnt_m;?>"
            value="<?php print $m;?>">
<?php
      $cnt_m++;
      $br = "      <br>\n";
    }
  }
  print $br;
?>
     <input type="hidden"
            name="inManagerUIDCnt"
            value="<?php print $mgr_cnt;?>">

 <input type="text"
        name="inManagerUIDNew">
 </td>
</tr>

<tr>
 <td colspan="2">

 <table border="0" width="100%">
 <tr>

 <?php if ($entry_found>0) { ?>
 <td width="33%">
  <input type="submit" name="in_button_update" value="Update">
 </td>
 <?php } else { ?>
   <td>&nbsp;</td>
 <?php } ?>

 <?php if ($entry_found>0) { ?>
 <td width="33%" align="center">
  <input type="submit" name="in_button_delete" value="Delete">
 </td>
 <?php } else { ?>
   <td>&nbsp;</td>
 <?php } ?>

 <?php if ($entry_found==0) { ?>
 <td width="33%" align="right">
  <input type="submit" name="in_button_add" value="Add">
 </td>
 <?php } else { ?>
   <td>&nbsp;</td>
 <?php } ?>

 </tr>
 </table>

 </td>
</tr>
</table>

<input type="hidden" name="in_xml_data" value="">

</form>

<?php
 ldap_close($ds);
 require ('inc_footer.php');
?>
