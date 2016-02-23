<?php

// --------------------------------------------
// file: group_search.php
// author: Bill MacAllister
// date: 27-Jan-2016

$title = 'MacAllister Directory Groups';
$heading = 'MacAllister Directory Groups';
require('inc_header.php');
require('/etc/whm/macdir.php');
require('inc_bind.php');

// Bail out immediately is user is not logged in.  They should
// never get here.
if (! isset($_SERVER['REMOTE_USER'])) {
    header ("REFRESH: 0; URL=/");
    exit;
}

// Bind to the directory
$ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');

// clean out the messages
$msg = '';

// create a form to attribute mapping
$form["cn"]          = "Group Name";
$form["gidnumber"]   = "GID Number";
$form["memberuid"]   = "Member UID";
$form["description"] = "Description";

// construct the filter from input data
$base_filter = '';
foreach ($form as $attr => $title) {
  $name = "in_$attr";
  if (isset($_REQUEST[$name])) {
    $a_val = $_REQUEST[$name];
    if (strlen($a_val)>0) {$base_filter .= "($attr=$a_val)";}
  }
}

if ( ! isset($base_filter) ) {
  // construct a filter from session information if there
  // is no input data
  foreach ($form as $attr => $title) {
    $sessName = "GROUP_$attr";
    if ( isset($_SESSION[$sessName]) ) {
        $a_val = $_SESSION[$sessName];
        if ( strlen($a_val) > 0 ) {
            $base_filter .= "($attr=$a_val)";
        }
    }
  }
} else {
  // reset session information from the input data
  foreach ($form as $attr => $title) {
    $name = "in_$attr";
    $sessName = "GROUP_$attr";
    if (isset($$name)) {
      $_SESSION[$sessName] = $$name;
    } else {
      $_SESSION[$sessName] = '';
    }
  }
}
?>

<p>
<div align="center">
<form action="group_search.php">
<table border="0">
<?php foreach ($form as $attr => $title) { ?>
  <tr>
    <td><div align="right"><?php print $title;?>:</div></td>
    <td>
      <input type="text"
             name="in_<?php print $attr; ?>"
             value="<?php print $_SESSION["GROUP_$attr"];?>"
             size="32">
    </td>
  </tr>
<?php } ?>
  <tr><td align="center" colspan="2">
      <input type="submit" value="Search Directory" name="button">
      </td>
  </tr>
<?php if ( isset($msg) ) { ?>
  <tr><td align="center" colspan="2">
      <?php echo $msg; $msg = '';?>
      </td>
  </tr>
<?php } ?>
</table>
</form>

<p>

<?php
if ( isset($base_filter) && strlen($base_filter)>0) {
    $filter = '(&(objectclass=posixGroup)'.$base_filter.')';
    $base_dn = $ldap_base;
    $return_attr = array(
        'cn',
        'description',
        'gidnumber',
        'memberuid'
    );

    $sr = ldap_search($ds, $base_dn, $filter, $return_attr);
    $info = ldap_get_entries($ds, $sr);
    $ret_cnt = $info["count"];
    if ($ret_cnt) {
        $header_set = 0;
        for ($i=0; $i<$info["count"]; $i++) {
            if ($header_set == 0) {
                $header_set = 1;
                echo "<table border=\"1\" cellpadding=\"2\">\n";
                echo "<tr>\n";
                foreach ($form as $attr => $title) {
                    print " <th>$title</th>\n";
                }
                print "</tr>\n";
            }
            print "<tr>\n";
            foreach ($form as $attr => $title) {
                print ' <td>';
                $a_val = '';
                $sep = '';
                for ($a=0; $a<$info[$i][$attr]["count"]; $a++) {
                    $a_val .= $info[$i][$attr][$a];
                    $sep = "<br>\n";
                }
                if ($attr == 'cn') {
                    $a_val =
                        '<a href="group_maintenance.php'
                        . '?in_cn=' . $a_val
                        . '">' . $a_val;
                }
                print $a_val;
                print "</td>\n";
            }
            print "</tr>\n";
        }
        if ($header_set > 0) {
            print "</table>\n";
        }
    } else {
        print "<p>\n";
        print "<div align=\"center\">\n";
        print "<font face=\"Arial, Helvetica, sans-serif\"\n";
        print "      size=\"+1\"\n";
        print "      color=\"#FF0000\">No entries found.</font>\n";
        print "</div>\n";
    }
}
?>
<p>

</div>

<?php require('inc_footer.php');?>
