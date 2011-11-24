<?PHP
// -------------------------------------------------------------
// computers.php
// author: Bill MacAllister
// date: 6-Apr-2004
//

// Open a session and check for authorization
require('whm_php_sessions.inc');
require('whm_php_auth.inc');
whm_auth("ldapadmin");

$title = 'Computers';
$heading = 'Computers';

require ('inc_header.php');
require('/etc/whm/macdir_auth.php');

// bind to the ldap directory
$dirServer = ldap_connect($ldap_server);
$r=ldap_bind($dirServer,$ldap_manager,$ldap_password);

//-------------------------------------------------------------
// Start of main processing for the page

?>

<?php

// attributes we are searching on
$formFld[] = 'uid';
$formFld[] = 'comments';

// Set up search using either new input or session stuff
$fld_filter = '';
if (strlen($button_find)>0) {
    foreach ($formFld as $fld) {
        $sessName = "COMPUTER_$fld";
        $name = "in_$fld"; $a_val = $$name; 
        if (strlen($a_val)>0) {
            $_SESSION[$sessName] = $a_val;
            $fld_filter .= "($fld=*$a_val*)";
        } else {
            $_SESSION[$sessName] = '';
        }
    }
    if (strlen($search_filter)>0) {
        $search_filter = '(&';
        $search_filter .= $fld_filter;
        $search_filter .= '(|(objectclass=sambaSamAccount)';
        $search_filter .= '(objectclass=prideComputer))';
        $search_filter .= ')';
    } else {
        $search_filter = '(|(objectclass=sambaSamAccount)';
        $search_filter .= '(objectclass=prideComputer))';
    }
    $_SESSION['COMPUTER_filter'] = $search_filter;
}

if ( strlen($_SESSION['COMPUTER_filter'])>0 ) {
    
    // Get the goods
    $attrs = array ('uid','uidnumber','gidnumber','objectclass','comments');
    $sr = @ldap_search ($dirServer, 
                        $ldap_computerbase, 
                        $_SESSION['COMPUTER_filter'], 
                        $attrs);  
    $info = @ldap_get_entries($dirServer, $sr);
    $info_cnt = $info["count"];
    $infoSort = array();
    if ($info_cnt >0) {
        for ($i=0; $i<$info_cnt; $i++) {
            $uid = $info[$i]['uid'][0];
            $infoSort["$uid"] = "$i";
        }
        ksort ($infoSort);
    }
}
?>
<form method="post" action="<?php print $PHP_SELF;?>">

<div align="center">
<table>
<tr><td align="right">UserID:</td>
    <td> 
    <input type="text" name="in_uid" 
           value="<?php print $_SESSION['COMPUTER_uid']; ?>">
    </td>
</tr>
<tr>
  <td align="right">Comments:</td>
  <td>
  <input type="text" name="in_comments" 
         value="<?php print $_SESSION['COMPUTER_comments']; ?>">
  </td>
</tr>
<tr>
  <td colspan="2" align="center">
  <input type="submit" name="button_find" value="Find">
  </td>
</tr>
<tr>
  <td colspan="2" align="center">
    All values entered are wildcarded.<br>
    Empty selections, then click Find to see all computers.
  </td>
</tr>
</table>
</div>
</form>

<?php

if ($info_cnt>0) {
?>
<table border="1" cellspacing="2" cellpadding="2">
  <tr>
    <th>Action</th>
    <th>Computer UID</th>
    <th>Object Class</th>
    <th>Comments</th>
    <th>UID Number</th>
    <th>GID Number</th>
  </tr>
<?php
  $cnt = 0;
  foreach ($infoSort as $uid => $idx) {
      $uid_encode = urlencode("$uid");
      $cm_href = '<a href="computer_maint?in_uid='.$uid_encode.'" '
           . 'target="_blank">';
      echo " <tr>\n";
      echo "  <td align=\"center\">$cm_href<img ";
      echo "src=\"/macdir-images/icon-edit.png\" border=\"0\"></a></td>\n";
      echo "  <td>$uid</td>\n";
      echo "  <td>\n";
      $br = ''; $t = '    ';
      for ($i=0; $i<$info[$idx]["objectclass"]["count"]; $i++) {
          echo $br.$t.$info[$idx]["objectclass"][$i]."\n";
          $br = "$t<br>\n";
      }
      echo "<pre>";
      echo "</pre>\n";
      echo "  </td>\n";
      echo "  <td>".$info[$idx]["comments"][0]."&nbsp;</td>\n";
      echo "  <td align=\"center\">".$info[$idx]["uidnumber"][0]."</td>\n";
      echo "  <td align=\"center\">".$info[$idx]["gidnumber"][0]."</td>\n";
      echo " <tr>\n";
  }
  echo "</table>\n";
} else {
  if (strlen($button_find) > 0) {
      echo '<div align="center">'."\n";
      echo '<font color="#ff0000">Nothing found!</font>'."\n";
      echo '</div>'."\n";
  }
}

?>

<?php
 ldap_close($dirServer);
 require ('inc_footer.php');
?>
