<?php
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_private = $_REQUEST['in_private'];
$in_button_search = $_REQUEST['in_button_search'];
$in_commonname = $_REQUEST['in_commonname'];
$in_description = $_REQUEST['in_description'];
$in_password = $_REQUEST['in_password'];
$in_url = $_REQUEST['in_url'];
// ----------------------------------------------------------
//
# --------------------------------------------
# file: my_list.php
# author: Bill MacAllister

session_start();

$title = "Search My Links";
$heading = "Search My Links";
require('inc_header.php');
require('/etc/whm/macdir.php');
require('inc_bind.php');
$ds = macdir_bind($ldap_server, 'GSSAPI');

# create a form to attribute mapping
$form["commonname"]  = "cn";
$form["description"] = "description";
$form["password"]    = "pridecredential";
$form["url"]         = "prideurl";

if (!isset($in_private) && isset($_SESSION['MY_private'])) {
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
    if (isset($in_button_search)) {
        $_SESSION["MY_$formName"] = $_REQUEST["in_$formName"];
    } else {
        $_SESSION["MY_$formName"] = '';
    }
}

# construct the filter from input data
$base_filter = '';
foreach ($form as $formName => $ldapName) {
    $a_val = $_REQUEST["in_$formName"];
    if (isset($a_val) && strlen($a_val)>0) {
        $base_filter .= "($ldapName=*$a_val*)";
    }
}

// Construct a filter from session information if there
// is no input data.
if (!isset($base_filter)) {
    foreach ($form as $formName => $ldapName) {
        $a_val = $_SESSION["MY_$formName"];
        if (isset($a_val) && strlen($a_val)>0) {
            $base_filter .= "($ldapName=*$a_val*)";
        }
    }
}

$this_uid = $_SERVER['REMOTE_USER'];
?>

<p>
<div align="center">
<form action="<?php echo $_SERVER['PHP_SELF'];?>" method="POST">
<table border="0">

  <tr>
    <td>
      <div align="right">Username:</div>
    </td>
    <td><?php echo $this_uid;?></td>
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
      <input type="submit" value="Search Directory" name="in_button_search">
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

$link_base = "uid=${this_uid},${ldap_user_base}";
$base_filter .= $private_filter;
$thisFilter .= "(objectclass=person)";
$thisFilter .= "(uid=".$this_uid.")";
$thisFilter .= ")";
$returnAttr = array('cn');
$sr = ldap_search($ds, $link_base, $thisFilter, $returnAttr);
$r = ldap_get_entries($ds, $sr);

$return_attr = array('cn',
                     'description',
                     'prideurl',
                     'linkuid',
                     'pridecredential',
                     'prideurlprivate');
$thisFilter = "(&";
$filter = '(&(objectclass=pridelistobject)'.$base_filter.')';
$sr = ldap_search($ds, $link_base, $filter, $return_attr);
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
            .'"><img src="/macdir-images/icon-edit.png" border="0"></a>';
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
