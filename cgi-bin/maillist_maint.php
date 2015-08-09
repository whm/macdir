<?php
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_localmailbox  = $_REQUEST['in_localmailbox'];
// ----------------------------------------------------------
//
// file: maillist_maint.php
// author: Bill MacAllister
// date: 18-Jan-2003
// description: This form is for updating phone information for people
//              listed in the Pride LDAP Directory.

// Open a session and check for authorization
require('whm_php_sessions.inc');
require('whm_php_auth.inc');

$title = 'Mail List Maintenance';
$heading = 'Mail List Maintenance';
require ('inc_header.php');

require('/etc/whm/macdir_auth.php');
require('inc_maillist_auth.php');
require('inc_bind.php');
$ds = macdir_bind($ldap_server, 'GSSAPI');

$ldap_search_base = "ou=maillists,$ldap_base";

$warn = '<font color="#cc0000">';
$ok = '<font color="#00cc00">';
$ef = "</font><br>\n";

$admin_user = ldap_maillist_admin();

// ---------------------------------------------------------
// Lookup an entry

$entry_found = 0;
$add_delete_flag = 1;
$thisLocalMailBox = $thisDN = '';
$ldap_filter = '';
if (strlen($in_localmailbox)>0) {
  $ldap_filter = "(&";
  $ldap_filter .= "(objectclass=prideemaillist)";
  $ldap_filter .= "(localmailbox=$in_localmailbox)";
  $ldap_filter .= ")";
}

if (strlen($ldap_filter)>0) {
  $return_attr = array();
  $sr = @ldap_search ($ds, $ldap_search_base, $ldap_filter, $return_attr);
  $info = @ldap_get_entries($ds, $sr);
  $ret_cnt = $info["count"];
  if ($ret_cnt == 1) {
     $entry_found = 1;
  } elseif ($retcnt > 1) {
     $msg .= "More than on entry found for $ldap_filter search.\n";
  } else {
     $msg .= "No entry found.\n";
  }
}

if ($entry_found) {

  $thisDN = $info[0]["dn"];
  $thisLocalMailBox = $in_localmailbox = $info[0]["localmailbox"][0];

  // ---------------------------------------------------------
  // Check the users credentials

  if (ldap_maillist_authorization($info[0]["manageruid"]) < 1) {
    $msg .= $warn."$in_localmailbox is inaccessible".$ef;
    $entry_found = 0;
    $this_DN = $thisLocalMailBox = $in_localmailbox = '';
    unset ($info);
  }

}


if ($entry_found) {

  // look up entries in ldap for individual users
  $mdi_attr = array('uid');
  $mdi_filter = '(&';
  $mdi_filter .= '(objectclass=person)';
  $mdi_filter .= "(maildistributionid=$in_localmailbox)";
  $mdi_filter .= ')';
  $sr = @ldap_search ($ds, $ldap_base, $mdi_filter, $mdi_attr);
  $mdi = @ldap_get_entries($ds, $sr);
  $mdi_cnt = $mdi["count"];
  
}
?>

<!-- Form Validation --------------------------------------------- -->
<script language="JavaScript">

/* ----------------- */
/* Helper routines   */
/* ----------------- */

/* ------------------------ */
/* get a valid carrier code */
/* ------------------------ */

function get_users() {
  var f = document.shipMaint;
  var win = window.open("get_users.php",
			"Pick_Users",
                        "width=400,height=400,status=yes"); 
  return false;
}

function js_set_std_headers() {

  var f;
  var t;
  f = document.maillist_maint;
  if (f.std_headers.checked) {
    t = "List-Help: <mailto:postmaster@macallister.grass-valley.ca.us>|\n";
    t += "List-Subscribe: <mailto:postmaster@macallister.grass-valley.ca.us>|\n";
    t += "List-Unsubscribe: <mailto:postmaster@macallister.grass-valley.ca.us>";
    f.in_new_mailservheaderaddition.value = t;
  } else {
    f.in_new_mailservheaderaddition.value = "";
  }

}

/* ----------------- */
/* Verify input data */
/* ----------------- */

function checkIt() {

  var f;
  var i;
  var outData = "";
  
  EmptyField = "";
  f = document.maillist_maint;

  var nondigit = /\D/;
  var anumber = /^\d*\.?\d*$/;

  if (f.in_localmailbox.value.length > 19) {
    alert ("Please enter a shorter name for the Mail List.\n"
          +"Names longer than 19 characters can cause ambiguities.\n"
          +"You can use aliases that are up to 40 characters long.");
    return false;
  }

  if (f.in_localmailbox.value == EmptyField) {
    alert ("Please enter a Mail List");
    return false;
  }

  if (f.in_description.value == EmptyField) {
    alert ("Please enter a Description");
    return false;
  }

  return true;

}

</script>

<!-- Lookup and Reset Forms -------------------------------------- -->

<table border="0" width="100%">
<tr>

  <!-- Lookup Form -->
  <td align="center" width="90%">
    <form name="maillist_maint_find"
          method="post"
          action="<?php print $_SERVER['PHP_SELF']; ?>">
    <table border="0" width="100%">
    <tr>
      <th align="right">Mail List:</th>
      <td><input type="text"
                 name="in_localmailbox"
                 value="<?php print $in_localmailbox;?>">
      </td>
    </tr>
    <tr>
      <td colspan="2" align="center">
        <input type="submit" name="in_button_find" value="Lookup">
      </td>
    </tr>
<?php
if (strlen($msg)>0) {
?>
    <tr>
      <td colspan="2" align="center"><?php echo $msg;?></td>
      <?php $msg = '';?>
    </tr>
<?php
}
?>

<?php
if ( isset($_SESSION['in_msg']) ) {
  if (strlen($_SESSION['in_msg']) > 0) {
?>
    <tr>
      <td colspan="2" align="center"><?php echo $_SESSION['in_msg'];?></td>
      <?php $_SESSION['in_msg'] = '';?>
    </tr>
<?php
  }
}
?>
    </table>
    </form>
  </td>

  <!-- Reset Form -->
  <td align="center" width="10%">
    <form name="reset"
          method="post"
          action="<?php print $_SERVER['PHP_SELF']; ?>">
    <input type="hidden" name="in_localmailbox" value="">
    <input type="submit" name="reset" value="Reset">
    </form>
  </td>

</tr>
</table>

<p>

<form name="maillist_maint"
      method="post"
      action="maillist_maint_action.php"
      onsubmit="return checkIt()">

<input type="hidden" name="in_dn" value="<?php print $thisDN;?>">

<table border="1" cellpadding="2">

<tr><td colspan="2" bgcolor="#660000" align="center">
    <font color="#ffffff" face="Arial, Helvetica, sans-serif">
      <b>List Definition</b>
    </font>
    </td>
</tr>

<tr>
  <th align="right">Mail List:</th>
  <td><input type="text"
             name="in_localmailbox"
             value="<?php print $thisLocalMailBox;?>"></td>
</tr>
<tr>
 <th align="right">Description:</th>
 <td><input type="text" size="40"
            name="in_description"
            value="<?php print $info[0]["description"][0];?>"></td>
</tr>

<tr>
 <th align="right">Mail Managers:</th>
 <td>
  <?php
  if ($entry_found>0) {
    $d = array();
    $d_cnt = 0;
    for ($i=0; $i<$info[0]["manageruid"]["count"]; $i++) {
      $d[] = $info[0]["manageruid"][$i];
    }
    sort($d);
    foreach ($d as $tmp) {
    ?>
     <input type="checkbox" CHECKED
            name="in_manageruid_flag_<?php echo $d_cnt;?>"
            value="Y"><?php print "$tmp\n";?>
      <input type="hidden" 
             name="in_manageruid_<?php echo $d_cnt;?>"
             value="<?php print $tmp;?>">
     <br>
  <?php
      $d_cnt++;
    // end of for loop
    }
  // end of entry_found test
  }
?>
     <input type="hidden" 
            name="in_manageruid_cnt" 
            value="<?php echo $d_cnt;?>">
     <input type="text"
            size="75"
            name="in_new_manageruid"></td>
</tr>

<tr>
 <th align="right">Email Aliases:</th>
 <td>
  <?php
  if ($entry_found>0) {
    $d = array();
    $d_cnt = 0;
    for ($i=0; $i<$info[0]["mailalias"]["count"]; $i++) {
      $d[] = $info[0]["mailalias"][$i];
    }
    sort($d);
    foreach ($d as $tmp) {
      ?>
      <input type="checkbox" CHECKED
             name="in_mailalias_flag_<?php echo $d_cnt;?>"
             value="Y"><?php print "$tmp\n";?>
      <input type="hidden" 
             name="in_mailalias_<?php echo $d_cnt;?>"
             value="<?php print $tmp;?>">
      <br>
  <?php
      $d_cnt++;
    // end of for loop
    }
  // end of entry_found test
  }
?>
     <input type="hidden" 
            name="in_mailalias_cnt" 
            value="<?php echo $d_cnt;?>">
     <input type="text"
            size="60"
            name="in_new_mailalias">
 </td>
</tr>

<tr>
 <th align="right">Delivery Addresses:</th>
 <td>
  <?php
  if ($entry_found>0) {
    $d = array();
    $d_cnt = 0;
    for ($i=0; $i<$info[0]["maildelivery"]["count"]; $i++) {
      $d[] = $info[0]["maildelivery"][$i];
    }
    for ($i=0; $i<$mdi_cnt; $i++) {
      $d[] = $mdi[$i]['uid'][0];
    }
    sort($d);
    foreach ($d as $tmp) {
      ?>
      <input type="checkbox" CHECKED
             name="in_maildelivery_flag_<?php echo $d_cnt;?>"
             value="Y"><?php print "$tmp\n";?>
      <input type="hidden" 
             name="in_maildelivery_<?php echo $d_cnt;?>"
             value="<?php print $tmp;?>">
      <br>
  <?php
      $d_cnt++;
    // end of for loop
    }
  // end of entry_found test
  }
?>
     <input type="hidden" 
            name="in_maildelivery_cnt" 
            value="<?php echo $d_cnt;?>">
     <input type="text"
            size="60"
            name="in_new_maildelivery">
     <input type="button"
            name="in_pick_users\"\n";
            value="Pick Users"
            onClick="get_users()">
  </td>
</tr>

<tr>
 <th align="right">LDAP Filter:</th>
 <td>
  <?php
  if ($entry_found>0) {
    $d = array();
    $d_cnt = 0;
    for ($i=0; $i<$info[0]["mailfilter"]["count"]; $i++) {
      $d[] = $info[0]["mailfilter"][$i];
    }
    sort($d);
    foreach ($d as $tmp) {
      $tmp_base = 'Both';
      if (preg_match('/^ldap:\/\/\/ou=staff/i', $tmp)){ $tmp_base = 'Staff';}
      if (preg_match('/^ldap:\/\/\/ou=people/i', $tmp)){ $tmp_base = 'People';}
      $tmp_search = '';
      $tmp_filter = '';
      if (preg_match('/\?single\?(.*)/i', $tmp, $matches)) { 
	$tmp_search = 'Single Level';
	$tmp_filter = $matches[1];
      } elseif (preg_match('/\?base\?(.*)/i', $tmp, $matches)) { 
	$tmp_search = 'Base Level';
	$tmp_filter = $matches[1];
      } elseif (preg_match('/\?sub\?(.*)/i', $tmp, $matches)) { 
	$tmp_search = 'Sub-Tree';
	$tmp_filter = $matches[1];
      }
      ?>
      <table border="1" cellpadding="2">
      <tr>
        <td align="right" valign="center" rowspan="3">
          <input type="checkbox" CHECKED
                 name="in_mailfilter_flag_<?php echo $d_cnt;?>"
                 value="Y">
          <input type="hidden" 
                 name="in_mailfilter_<?php echo $d_cnt;?>"
                 value="<?php print $tmp;?>">
        </td>
        <th align="right">LDAP Base:</th><td><?php echo $tmp_base;?></td>
      </tr>
      <tr>
        <th align="right">Scope:</th><td><?php echo $tmp_search;?></td>
      </tr>
      <tr>
        <th align="right">Filter:</th><td><?php echo $tmp_filter;?></td>
      </tr>
      </table>
  <?php
      $d_cnt++;
    // end of for loop
    }
  // end of entry_found test
  }
?>
     <input type="hidden" 
            name="in_mailfilter_cnt" 
            value="<?php echo $d_cnt;?>">

     <table border="1" cellpadding="2">
     <tr><th align="right">LDAP Base:</th>
         <td><input type="radio"
                    name="in_new_mailfilter_base"
                    value="staff">Staff
             <input type="radio"
                    name="in_new_mailfilter_base"
                    value="people">People
             <input type="radio" checked
                    name="in_new_mailfilter_base"
                    value="both">Both 
        </td>
     </tr>
     <tr><th align="right">Scope:</th>
         <td><input type="radio" checked
                    name="in_new_mailfilter_search"
                    value="sub">Sub-Tree 
             <input type="radio" 
                    name="in_new_mailfilter_search"
                    value="single">Single Level
             <input type="radio" 
                    name="in_new_mailfilter_search"
                    value="base">Base level
         </td>
     </tr>
     <tr><th align="right">Filter:</th>
         <td><input type="text"
                    size="65"
                    name="in_new_mailfilter">
         </td>
     </tr>
     </table>
 </td>
</tr>

<tr><td colspan="2" bgcolor="#660000" align="center">
    <font color="#ffffff" face="Arial, Helvetica, sans-serif">
      <b>List Controls</b>
    </font>
    </td>
</tr>

<tr>
 <th align="right">Tag:</th>
 <td><input type="text" size="60" name="in_mailservtag"
            value="<?php echo $info[0]['mailservtag'][0];?>">
 </td>
</tr>

<tr>
 <th align="right">Block Limit:</th>
 <td><input type="text" size="10" name="in_mailservblocklimit"
            value="<?php echo $info[0]['mailservblocklimit'][0];?>">
 </td>
</tr>

<tr>
 <th align="right">Line Limit:</th>
 <td><input type="text" size="10" name="in_mailservlinelimit"
            value="<?php echo $info[0]['mailservlinelimit'][0];?>">
 </td>
</tr>

<tr>
 <th align="right">Error Return Address:</th>
 <td><input type="text" size="60" name="in_mailserverrorreturnaddress"
            value="<?php echo $info[0]['mailserverrorreturnaddress'][0];?>">
 </td>
</tr>
<tr>
 <th align="right">Reply To Address:</th>
 <td><input type="text" size="60" name="in_mailservreplytoaddress"
            value="<?php echo $info[0]['mailservreplytoaddress'][0];?>">
 </td>
</tr>
<tr>
 <th align="right">Errors To Address:</th>
 <td><input type="text" size="60" name="in_mailserverrorstoaddress"
            value="<?php echo $info[0]['mailserverrorstoaddress'][0];?>">
 </td>
</tr>
<tr>
 <th align="right">Warnings To Address:</th>
 <td><input type="text" size="60" name="in_mailservwarningstoaddress"
            value="<?php echo $info[0]['mailservwarningstoaddress'][0];?>">
 </td>
</tr>

<tr>
 <th align="right">Envelope From:</th>
 <td><input type="text" size="60" name="in_mailservenvelopefrom"
            value="<?php echo $info[0]['mailservenvelopefrom'][0];?>">
 </td>
</tr>

<tr>
 <th align="right">Additional Headers:</th>
 <td>  <?php
  if ($entry_found>0) {
    $d = array();
    $d_cnt = 0;
    for ($i=0; $i<$info[0]["mailservheaderaddition"]["count"]; $i++) {
      $d[] = $info[0]["mailservheaderaddition"][$i];
    }
    sort($d);
    foreach ($d as $tmp) {
      ?>
      <input type="checkbox" CHECKED
             name="in_mailservheaderaddition_flag_<?php echo $d_cnt;?>"
             value="Y"><?php print htmlentities($tmp)."\n";?>
      <input type="hidden" 
             name="in_mailservheaderaddition_<?php echo $d_cnt;?>"
             value="<?php print $tmp;?>">
      <br>
  <?php
      $d_cnt++;
    // end of for loop
    }
  // end of entry_found test
  }
?>
     <input type="hidden" 
            name="in_mailservheaderaddition_cnt" 
            value="<?php echo $d_cnt;?>">
     <textarea cols="60" rows="3"
            name="in_new_mailservheaderaddition">
     </textarea>
     <br>
     <input type="checkbox" name="std_headers" value=""
            onclick="js_set_std_headers();">Standard Headers
 </td>
</tr>

<tr>
  <th align="right">Moderator Address:</th>
  <td><input type="text"
             size="60"
             name="in_mailservmoderatoraddress"
             value="<?php print $info[0]["mailservmoderatoraddress"][0];?>"></td>
</tr>

<?php
$ml_list = array ('mailservmoderatorlist' => 'Moderator List',
                  'mailservauthlist'      => 'Auth List');
foreach ($ml_list as $id => $id_title) {
?>
<tr>
 <th align="right"><?php echo $id_title;?>:</th>
 <td>
  <?php
  if (strlen($info[0]["$id"][0])>0) {
    $tmp = $info[0]["$id"][0];
    $tmp_base = 'Both';
    if (preg_match('/^ldap:\/\/\/ou=staff/i', $tmp)){ $tmp_base = 'Staff';}
    if (preg_match('/^ldap:\/\/\/ou=people/i', $tmp)){ $tmp_base = 'People';}
    $tmp_search = '';
    $tmp_filter = '';
    if (preg_match('/\?single\?(.*)/i', $tmp, $matches)) { 
      $tmp_search = 'Single Level';
      $tmp_filter = $matches[1];
    } elseif (preg_match('/\?base\?(.*)/i', $tmp, $matches)) { 
      $tmp_search = 'Base Level';
      $tmp_filter = $matches[1];
    } elseif (preg_match('/\?sub\?(.*)/i', $tmp, $matches)) { 
      $tmp_search = 'Sub-Tree';
      $tmp_filter = $matches[1];
    }
  ?>
    <table border="1" cellpadding="2">
    <tr>
      <td align="right" valign="center" rowspan="3">
          <input type="checkbox" CHECKED
                 name="in_<?php echo $id;?>_flag"
                 value="Y">
          <input type="hidden" 
                 name="in_<?php echo $id;?>"
                 value="<?php print $tmp;?>">
      </td>
      <th align="right">LDAP Base:</th><td><?php echo $tmp_base;?></td>
    </tr>
    <tr>
      <th align="right">Scope:</th><td><?php echo $tmp_search;?></td>
    </tr>
    <tr>
      <th align="right">Filter:</th><td><?php echo $tmp_filter;?></td>
    </tr>
    </table>

  <?php
  } else {
  ?>

    <table border="1" cellpadding="2">
    <tr><th align="right">LDAP Base:</th>
        <td><input type="radio"
                   name="in_<?php echo $id;?>_base"
                   value="staff">Staff
            <input type="radio"
                   name="in_<?php echo $id;?>_base"
                   value="people">People
            <input type="radio" checked
                   name="in_<?php echo $id;?>_base"
                   value="both">Both 
       </td>
    </tr>
    <tr><th align="right">Scope:</th>
        <td><input type="radio" checked
                   name="in_<?php echo $id;?>_search"
                   value="sub">Sub-Tree 
            <input type="radio" 
                   name="in_<?php echo $id;?>_search"
                   value="single">Single Level
            <input type="radio" 
                   name="in_<?php echo $id;?>_search"
                   value="base">Base level
        </td>
    </tr>
    <tr><th align="right">Filter:</th>
        <td><input type="text"
                   size="65"
                   name="in_<?php echo $id;?>_filter">
        </td>
    </tr>
    </table>
<?php 
  } 
}
?>

 </td>
</tr>

<tr>
 <th align="right">Comments:</th>
 <td><input type="text" size="60" name="in_mailservcomments"
            value="<?php echo $info[0]['mailservcomments'][0];?>">
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

 <?php if ($add_delete_flag>0 && $entry_found>0 && $admin_user>0) { ?>
 <td width="33%" align="center">
  <input type="submit" name="in_button_delete" value="Delete">
 </td>
 <?php } else { ?>
   <td>&nbsp;</td>
 <?php } ?>

 <?php if ($admin_user>0) { ?>
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

</form>

<?php
 ldap_close($ds);
 require ('inc_footer.php');
?>
