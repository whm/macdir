<?php

// --------------------------------------------
// file: group_search.php
// author: Bill MacAllister
// date: 27-Jan-2016

$title = 'Posix Groups';
$heading = 'Posix Groups';
require('inc_init.php');
require('inc_header.php');

// Bind to the directory
$ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');

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

<div class="row">
<div class="col-9">

<form action="group_search.php">
<?php
    foreach ($form as $attr => $title) {
        $this_attr = "in_${attr}";
        $this_val
        = empty($_SESSION["GROUP_${attr}"]) ? '' : $_SESSION["GROUP_${attr}"];
?>

    <label for="<?php print $this_attr;?>"><?php print $title;?>:</label>
    <input type="text"
           name="in_<?php print $attr; ?>"
           value="<?php print $this_val;?>">
    <br/>
<?php } ?>

<p>
<input type="submit" value="Search Directory" name="button">
</p>

<?php
    if ( !empty($_SESSION['in_msg']) ) { ?>
      <p><?php print $_SESSION['in_msg']; $_SESSION['in_msg'] = '';?></p>
<?php } ?>
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
                print "<table>\n";
                print "<tr>\n";
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
        print '<p>' . warn_html('No entries found') . "<p>\n";
    }
}
?>

</div>

<?php require('inc_menu.php');?>
</div>

<?php require('inc_footer.php');?>
