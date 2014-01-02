<?PHP
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_uid  = $_REQUEST['in_uid'];
// ----------------------------------------------------------
//
// -------------------------------------------------------------
// computer_maint.php
// author: Bill MacAllister
// date: April 4, 2004
//

// Open a session and check for authorization
require('whm_php_sessions.inc');
require('whm_php_auth.inc');
whm_auth("ldapadmin");

$title = 'SMB Computer Maintenance';
$heading = 'SMB Computer Maintenance';

require ('inc_header.php');
require('/etc/whm/macdir_auth.php');

// bind to the ldap directory
$ds = ldap_connect($ldap_server);
$ldapReturn = ldap_bind($ds,$ldap_manager,$ldap_password);

// -- make a zulu time readable
function format_zulutime($in) {

    $ret = $in;
    
    if (strlen($in)>0) {
        $yyyy = substr($in,0,4);
        $mm = substr($in,4,2);
        $dd = substr($in,6,2);
        $hh = substr($in,8,2);
        $min = substr($in,10,2);
        $ss = substr($in,12,2);
        
        $mon = $mm;
        if ($mm == 1) {$mon = "Jan";}
        elseif ($mm == 2) {$mon = "Feb";}
        elseif ($mm == 3) {$mon = "Mar";}
        elseif ($mm == 4) {$mon = "Apr";}
        elseif ($mm == 5) {$mon = "May";}
        elseif ($mm == 6) {$mon = "Jun";}
        elseif ($mm == 7) {$mon = "Jul";}
        elseif ($mm == 8) {$mon = "Aug";}
        elseif ($mm == 9) {$mon = "Sep";}
        elseif ($mm == 10) {$mon = "Oct";}
        elseif ($mm == 11) {$mon = "Nov";}
        elseif ($mm == 12) {$mon = "Dec";}
        $ret = "$yyyy-$mon-$dd $hh:$min:$ss GMT";
    } else {
        $ret = '&nbsp;';
    }
    
    return $ret;
    
}

//-------------------------------------------------------------
// Start of main processing for the page

// get a list of pam, posix, and application groups
require('inc_groups.php');

if ($in_uid=='CLEARFORM' || $in_uid=='') {
    $add_flag = 1;
    $in_uid = '';
}

$entry_found = false;
$ldap_filter = '';
if (strlen($in_uid)>0) {
    $ldap_filter = "uid=$in_uid";
    $return_attr = array('uid','modifytimestamp');
    $sr = @ldap_search ($ds, 
                        $ldap_computerbase, 
                        $ldap_filter, 
                        $return_attr);
    $info = @ldap_get_entries($ds, $sr);
    $ret_cnt = $info["count"];
    if ($ret_cnt == 1) {
        $this_ts = $info[0]['modifytimestamp'][0];
        $in_uid = $info[0]["uid"][0];
        $return_attr = array();
        $sr = @ldap_search ($ds, 
                            $ldap_computerbase, 
                            $ldap_filter, 
                            $return_attr);
        $info = @ldap_get_entries($ds, $sr);
        $entry_found = 1;
    } elseif ($retcnt > 1) {
        $_SESSION['s_msg'].="More than on entry found for "
             . "$ldap_filter search.\n";
    } else {
        $_SESSION['s_msg'].="No entry found.\n";
        $add_flag = 1;
    }

    // Now see if there is a uid and if they are in any posix or pam groups
    if ($entry_found) {
        $thisUID = $info[0]["uid"][0];
        if (strlen($thisUID)>0) {
            $thisDN = $info[0]["dn"];
            
            // posix groups for this user
            $posixFilter = "(&(objectclass=posixGroup)(memberUid=$thisUID))";
            $posixReturn = array ('gidNumber','cn','description');
            $sr = @ldap_search ($ds, 
                                $ldap_groupbase, 
                                $posixFilter, 
                                $posixReturn);
            $posixEntries = @ldap_get_entries($ds, $sr);
            $thisPosix_cnt = $posixEntries["count"];
            $thisPosix = array();
            if ($thisPosix_cnt >0) {
                for ($gi=0; $gi<$thisPosix_cnt; $gi++) {
                    $groupName = $posixEntries[$gi]['cn'][0];
                    $groupDesc = $posixEntries[$gi]['description'][0];
                    $thisPosixGroups[$groupName] = "$groupName";
                    if (strlen($groupDesc)>0) {
                        $thisPosixGroups[$groupName] .= " - $groupDesc";
                    }
                }
            }
        }
    }
}
?>

<html>
<head>
<title>Computer Maintenance</title>
</head>

<script language="JavaScript">

// ------------------------------------
// Clean up the uid
function cleanUID() {

    var f;
    var t;
    f = document.computer_data;
    
    t = f.in_uid.value;
    t = t.replace (/\s+/g,"");
    t = t.toLowerCase();
    f.in_uid.value = t;
    
    var pat = /\$$/;
    var result = t.match(pat);
    if (result == null) {
        t = t + "$";
        f.in_uid.value = t;
    }
    f.in_cn.value = t;
}

// ------------------------------------
// Verify the input
function verifyInput() {

    var f;
    var i;
    var outData = "";
    
    EmptyField = "";
    f = document.computer_data;
    
    if (f.in_cn.value == EmptyField) {
        alert ("Please enter a Common Name");
        return false;
    }
    
    var macPattern = /[0-9a-fA-f]{12,12}/;
    if (f.in_macaddress.value != EmptyField) {
        if ( ! macPattern.test(f.in_macaddress.value) ) {
            var msg = "Invalid MAC Address.\n"
                + "Format must be 12 hex numbers long.\n"
                + "Please try again.";
            alert (msg);
            return false;
        }
    }
    
    var ipPattern = /^([0-2][0-9][0-9]|[0-9][0-9]|[0-9])\.([0-2][0-9][0-9]|[0-9][0-9]|[0-9])\.([0-2][0-9][0-9]|[0-9][0-9]|[0-9])\.([0-2][0-9][0-9]|[0-9][0-9]|[0-9])$/;
    if (f.in_iphostnumber.value != EmptyField) {
        if ( ! ipPattern.test(f.in_iphostnumber.value) ) {
            var msg = "Invalid IP Address.\n"
                + "Format must be: n:n:n:n where nnn is 0-255.\n"
                + "Please try again.";
            alert (msg);
            return false;
        }
    }
    
    return true;
}
</script>

<div align="center">
<form name="find_user" action="<?php print $_SERVER['PHP_SELF'];?>" method="post">
<table border="1">
<tr>
  <td align="right">Computer UID:</td>
  <td><input type="text" 
             name="in_uid"
             value="<?php print $in_uid;?>">
  </td>
</tr>
<tr>
  <td align="center" colspan="2">
  <input type="submit" name="btn_find" value="Find">
  </td>
</tr>
<?php 
if(strlen($_SESSION['s_msg'])>0) { ?>
<tr><td bgcolor="#ffffff" align="center" colspan="2">
    <font color="#ff0000"><?php print $_SESSION['s_msg'];?></font>
    </td>
</tr>
<?php
}
$_SESSION['s_msg'] = '';
?>
</table>
</form>

<p> 

<form name="computer_data" 
      action="computer_maint_action" 
      onsubmit="return verifyInput()"
      method="post">
<table border="1">
<tr>
 <td colspan="2">
 <table border="0" width="100%">
 <tr>
   <td align="right">
    <a href="computer_maint.php?in_uid=CLEARFORM">Clear Form</a>
   </td>
  </tr>
  </table>
  </td>
</tr>
<?php if ($entry_found) { ?>
<tr>
 <td align="right">Computer UID:</td>
 <td> <?php print $info[0]['uid'][0]; ?>
      <input type="hidden" name="in_uid"
             value="<?php print $info[0]['uid'][0]; ?>">
      <input type="hidden" name="in_password" value="notset">
 </td>
</tr>
<?php } else { ?>
<tr>
 <td align="right">Computer UID:</td>
 <td> <input type="text" name="in_uid"
             value="<?php print $info[0]['uid'][0]; ?>"
             onchange="cleanUID();">
      <input type="hidden" name="in_password" value="notset">
 </td>
</tr>
<?php } ?>
<tr>
 <td align="right">Common Name:</td>
 <td> <input type="text" name="in_cn" size="32" maxlength="32"
             value="<?php print $info[0]['cn'][0]; ?>"
 </td>
</tr>
<tr>
 <td align="right">MAC Address:</td>
 <td> <input type="text" name="in_macaddress" size="20" maxlength="17"
             value="<?php print $info[0]['macaddress'][0]; ?>"
 </td>
</tr>
<tr>
 <td align="right">IP Address:</td>
 <td> <input type="text" name="in_iphostnumber" size="20" maxlength="17"
             value="<?php print $info[0]['iphostnumber'][0]; ?>"
 </td>
</tr>
<tr>
 <td align="right">userPassword:</td>
 <td> <input type="text" name="in_userpassword" size="20" 
             value="<?php print $info[0]['pridecredential'][0]; ?>"
 </td>
</tr>
<tr>
 <td align="right">Computer Groups:</td>
 <td>
<?php
  $br = '';
  $posix_display = array();
  $posix_checked = array();
  if (is_array($thisPosixGroups)) {
    foreach ($thisPosixGroups as $group => $description) {
      $posix_display[$group] = $description;
      $posix_checked[$group] = 'CHECKED';
    }
  }
  foreach ($posix_display as $group => $group_description) {
    print $br;
?>
     <input type="checkbox" <?php echo $posix_checked[$group]; ?>
            name="inPosixGroup[]"
            value="<?php print $group;?>"><?php print $group_description;?>
     <input type="hidden"
            name="inPosixGroupList[]"
            value="<?php print $group;?>">
<?php
    $br = "      <br>\n";
  }
  print $br;
?>
 <input type="text"
        name="inPosixNew">
 </td>
</tr>
<tr>
 <td align="right">Comments:</td>
 <td>
<TEXTAREA name="in_comments" rows="2" cols="40">
<?php print $info[0]['comments'][0];?>
</TEXTAREA>
 </td>
</tr>

<?php
if (strlen($info[0]["sambasid"][0]) == 0) {
?>
<tr>
 <td align="right">Add Samba SID:</td>
 <td>
<?php 
$br = '';
foreach ($samba_domains as $d => $d_sid) { 
  echo $br;
  $br = "<br>\n";
  $chk = '';
  if (strtolower($d) == strtolower($info[0]["sambadomainname"][0])) {
    $chk = 'CHECKED ';
  }
?>
 <input type="radio" 
        name="in_sambadomainname"
        <?php echo $chk;?>value="<?php echo $d;?>"><?php echo $d;?>
<?php } ?>

 </td>
</tr>
<?php
} else {
?>
<tr>
 <td align="right">Domain Name:</td>
<td><?php echo $info[0]["sambadomainname"][0];?></td>
</tr>
<tr>
 <td align="right">Samba SID:</td>
 <td><?php echo $info[0]["sambasid"][0];?></td>
</tr>
<?php } ?>

<tr>
 <td align="right">Modify Timestamp:</td>
 <td> <?php print format_zulutime($this_ts); ?> </td>
</tr>
</table>

<p>
<table width="100%" border="0">
  <tr>
<?php 
  if ($add_flag > 0) { ?>
    <td colspan="2" align="center">
      <input type="submit" name="btn_add" value="Add">
    </td>
<?php 
  } elseif ($acct_cnt>0) { ?>
    <td colspan="2" align="center">
      <input type="submit" name="btn_update" value="Update">
    </td>
<?php
  } else { ?>
    <td>
      <input type="submit" name="btn_update" value="Update">
    </td>
    <td align="right">
      <input type="submit" name="btn_delete" value="Delete">
    </td>
<?php } ?>
  </tr>
</table>
</form>
</div>

<?php
 ldap_close($ds);
 require ('inc_footer.php');
?>
