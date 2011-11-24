<?php
# --------------------------------------------
# file: my_list.php
# author: Bill MacAllister

# Session variable support
require("whm_php_sessions.inc");
require('whm_php_auth.inc');

whm_auth("user|phoneadmin|ldapadmin");

$title = "Search My Links";
$heading = "Search My Links";
require('inc_header.php');
require ('/etc/whm/macdir_auth.php');

# create a form to attribute mapping
$form["commonname"]  = "cn";
$form["description"] = "description";
$form["password"]    = "pridecredential";
$form["url"]         = "prideurl";

if (strlen($in_private) == 0 && $_SESSION['MY_private']>0) {
    $in_private = $_SESSION['MY_private'];
}
$_SESSION['MY_private'] = $in_private;
$chk_private_y = $chk_private_n = $chk_private_all = '';
if ($in_private == 'Y') {
    $chk_private_y = 'CHECKED';
    $private_filter = '(prideurlprivate=Y)';
} elseif ($in_private == 'ALL') {
    $chk_private_all = 'CHECKED';
    $private_filter = '';
} else {
    $chk_private_n = 'CHECKED';
    $private_filter = '(!(prideurlprivate=Y))';
}

# set session information
foreach ($form as $formName => $ldapName) {
    $name = "in_$formName"; 
    $sessName = "MY_$formName";
    if (strlen($btn_search)>0) {
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
        $sessName = "MY_$formName";
        $a_val = $_SESSION[$sessName];
        if (strlen($a_val) > 0) {
            $base_filter .= "($ldapName=*$a_val*)";
        }
    }
    
}
?>

<p>
<div align="center">
<form action="<?php echo $PHP_SELF;?>" method="POST">
<table border="0">

  <tr> 
    <td> 
      <div align="right">Username:</div>
    </td>
    <td><?php echo $_SESSION['whm_directory_user'];?></td>
  </tr>

  <tr> 
    <td> 
      <div align="right">Common Name:</div>
    </td>
    <td> 
      <input type="text" 
             name="in_commonname" 
             value="<?php print $_SESSION['MY_commonname'];?>"
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
             value="<?php print $_SESSION['MY_description'];?>"
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
             value="<?php print $_SESSION['MY_url'];?>"
             size="32">
    </td>
  </tr>

  <tr> 
    <td> 
      <div align="right">Private:</div>
    </td>
    <td> 
      <input type="radio" 
            <?php echo $chk_private_n;?> name="in_private" 
             value="N">Only Public
      &nbsp;&nbsp;&nbsp;
      <input type="radio" 
            <?php echo $chk_private_y;?> name="in_private" 
             value="Y">Only Private
      &nbsp;&nbsp;&nbsp;
      <input type="radio" 
            <?php echo $chk_private_all;?>  name="in_private" 
             value="ALL">All
    </td>
  </tr>


  <tr><td align="center" colspan="2">
      <input type="submit" value="Search Directory" name="btn_search">
      </td>
  </tr>
<?php if (strlen($msg)>0) { ?>
  <tr><td align="center" colspan="2">
      <?php echo $msg;?>
      </td>
  </tr>
<?php } ?>
</table>
</form>

<p>

<?php

$my_base_dn = 'uid='.$_SESSION['whm_directory_user']
       .','.$ldap_user_base;
$base_filter .= $private_filter;
$ds = ldap_connect($ldap_server);
$r  = ldap_bind($ds,
                $_SESSION['whm_directory_user_dn'],
                $_SESSION['whm_credential']);
$filter = '(&(objectclass=pridelistobject)'.$base_filter.')';
$thisFilter = "(&";
$thisFilter .= "(objectclass=person)";
$thisFilter .= "(uid=".$_SESSION['whm_directory_user'].")";
$thisFilter .= ")";
$returnAttr = array('cn');
$sr = ldap_search($ds, $my_base_dn, $thisFilter, $returnAttr);  
$r = ldap_get_entries($ds, $sr);
if ($r["count"]) {
    $base_db = $r['dn'];
}

$return_attr = array('cn',
                     'description',
                     'prideurl',
                     'linkuid',
                     'pridecredential',
                     'prideurlprivate');
$sr = ldap_search($ds, $my_base_dn, $filter, $return_attr);  
ldap_sort($ds, $sr, 'description');
$info = ldap_get_entries($ds, $sr);
$ret_cnt = $info["count"];
if ($ret_cnt) {
    echo "<table border=\"1\" cellpadding=\"2\">\n";
    echo "<tr>\n";
    echo " <th>Description</th>\n";
    echo " <th>URL</th>\n";
    echo " <th>Private</th>\n";
    echo " <th>Username</th>\n";
    echo " <th>Password</th>\n";
    echo "</tr>\n";
    for ($i=0; $i<$info["count"]; $i++) {
        $a_cn = $info[$i]["cn"][0];
        $a_cn_url = urlencode($a_cn);
        
        $a_maint_link = '<a href="my_links_maint.php'
            .'?in_cn=' . $a_cn_url
            .'"><img src="images/icon-edit.png" border="0"></a>';
        $a_href_url = '<a href="'
            .htmlentities($info[$i]["prideurl"][0])
            .'" target="_BLANK">'
            .$info[$i]["prideurl"][0].'</a>';
        
        echo "<tr>\n";
        echo ' <td valign="center">'.$a_maint_link
            .$info[$i]["description"][0]."&nbsp;</td>\n";
        echo " <td>$a_href_url &nbsp;</td>\n";
        echo ' <td align="center">'
            .$info[$i]["prideurlprivate"][0]."&nbsp;</td>\n";
        echo " <td>".$info[$i]["linkuid"][0]."&nbsp;</td>\n";
        echo " <td>".$info[$i]["pridecredential"][0]."&nbsp;</td>\n";
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

