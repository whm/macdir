<?php
# --------------------------------------------
# file: my_list.php
# author: Bill MacAllister

$title = "Search Notes";
$heading = "Search Notes";
require('inc_config.php');
require('inc_macdir.php');

# create a form to attribute mapping
$form["commonname"]  = "cn";
$form["description"] = "description";
$form["password"]    = "whmcredential";
$form["url"]         = "whmurl";

$valid_visibility['private']   = 'Private';
$valid_visibility['ca-zephyr'] = 'CA Zephyr';
$valid_visibility['public']    = 'Public';

# Set the visibility filter
$visibility_filter = '';
foreach ($valid_visibility as $vvis => $vdesc) {
    if (isset(${"in_visibility_$vvis"})) {
        $visibility_filter .= "(whmurlvisibility=$vvis)";
    }
}
if ( !isset($visbility_filter) ) {
    if (isset($_SESSION['NOTES_visibility_filter'])) {
        $visibility_filter = $_SESSION['NOTES_visibility_filter'];
    } else {
        $visibility_filter = '(whmurlvisibility=public)';
    }
}
$_SESSION['NOTES_visibility_filter'] = $visibility_filter;

# set session information
foreach ($form as $formName => $ldapName) {
    $name = "in_$formName"; 
    $sessName = "NOTES_$formName";
    if (isset($btn_search)) {
        $_SESSION[$sessName] = $$name;
    } else {
        $_SESSION[$sessName] = '';
    }
}

# construct the filter from input data
$base_filter = '';
foreach ($form as $formName => $ldapName) {
    $name = "in_$formName"; 
    if (isset($$name)) {
        $a_val = $$name;
        if (strlen($a_val)>0) {$base_filter .= "($ldapName=*$a_val*)";}
    }
}

if (strlen($base_filter)==0) {
    // construct a filter from session information if there
    // is no input data
    foreach ($form as $formName => $ldapName) {
        $sessName = "NOTES_$formName";
        $a_val = $_SESSION[$sessName];
        if (strlen($a_val) > 0) {
            $base_filter .= "($ldapName=*$a_val*)";
        }
    }
}

require('inc_header.php');
?>

<p>
<div align="center">
<form action="<?php echo $PHP_SELF;?>" method="POST">
<table border="0">

  <tr> 
    <td> 
      <div align="right">Username:</div>
    </td>
    <td><?php echo $this_user;?></td>
  </tr>

  <tr> 
    <td> 
      <div align="right">Common Name:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_commonname" 
             value="<?php print $_SESSION['NOTES_commonname'];?>"
             size="32">
    </td>
  </tr>

  <tr> 
    <td> 
      <div align="right">Description:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_description" 
             value="<?php print $_SESSION['NOTES_description'];?>"
             size="32">
    </td>
  </tr>

  <tr> 
    <td> 
      <div align="right">URL:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_url" 
             value="<?php print $_SESSION['NOTES_url'];?>"
             size="32">
    </td>
  </tr>

  <tr> 
    <td> 
      <div align="right">Visibility:</div>
    </td>
    <td> 
<?php 
        foreach ($valid_visibility as $vval => $vdesc) {
            print '<input type="checkbox"';
            if ($vval == $in_visibility) {print ' CHECKED';}
            print ' name="in_visibility">.$vval';
            print "&nbsp;&nbsp;&nbsp;\n";
        }
?>
    </td>
  </tr>


  <tr><td align="center" colspan="2">
      <input type="submit" value="Search Directory" name="btn_search">
      </td>
  </tr>
<?php if (isset($msg)) { ?>
  <tr><td align="center" colspan="2">
      <?php echo $msg;?>
      </td>
  </tr>
<?php } ?>
</table>
</form>

<p>

<?php

$note_base_dn = 'uid='.$_SERVER['WEBAUTH_USER']
            .','.$macdirPROPS['ldap_user_base_dn'];
$base_filter .= $visibility_filter;
$filter = '(&(objectclass=whmPersonalNote)';
$filter .= $base_filter;
$filter .= '(objectclass=whmPersonalNote)';
$filter .= ")";
$return_attr = array('cn',
                     'description',
                     'whmCredential',
                     'whmEntryStatus',
                     'whmEntryVisibility',
                     'whmUrl',
                     'whmUrlVisibility' );

$sr = ldap_search($macdirDS, $note_base_dn, $filter, $return_attr);  
ldap_sort($macdirDS, $sr, 'description');
$info = ldap_get_entries($macdirDS, $sr);
$ret_cnt = 0;
if ( isset($info['count']) ) {$ret_cnt = $info["count"];}
if ($ret_cnt) {
    echo "<table border=\"1\" cellpadding=\"2\">\n";
    echo "<tr>\n";
    echo " <th>Description</th>\n";
    echo " <th>URL</th>\n";
    echo " <th>Visibility</th>\n";
    echo " <th>Username</th>\n";
    echo " <th>Password</th>\n";
    echo "</tr>\n";
    for ($i=0; $i<$info["count"]; $i++) {
        $a_cn = $info[$i]["cn"][0];
        $a_cn_url = urlencode($a_cn);
        
        $a_maint_link = '<a href="notes_maint.php'
            .'?in_cn=' . $a_cn_url
            .'"><img src="/macdir-images/icon-edit.png" border="0"></a>';
        $a_href_url = '<a href="'
            .htmlentities($info[$i]["whmurl"][0])
            .'" target="_BLANK">'
            .$info[$i]["whmurl"][0].'</a>';
        
        echo "<tr>\n";
        echo ' <td valign="center">'.$a_maint_link
            .$info[$i]["description"][0]."&nbsp;</td>\n";
        echo " <td>$a_href_url &nbsp;</td>\n";
        echo ' <td align="center">'
            .$info[$i]["whmurlvisibility"][0]."&nbsp;</td>\n";
        echo " <td>".$info[$i]["linkuid"][0]."&nbsp;</td>\n";
        echo " <td>".$info[$i]["whmcredential"][0]."&nbsp;</td>\n";
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
<p>

</div>

<?php require('inc_footer.php');?>

