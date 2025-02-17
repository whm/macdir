<?php 
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_dn  = empty($_REQUEST['in_dn']) ? '' : $_REQUEST['in_dn'];
$in_uid = empty($_REQUEST['in_uid']) ? '' : $_REQUEST['in_uid'];
// ----------------------------------------------------------
//
# ------------------------------------------
# file: user_details.php
# author: Bill MacAllister

$title = 'Directory Search Details';
$heading = "Details";

require('inc_init.php');
require('inc_header_user_search.php');

$ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');

# -- print a row if there is something to print

function prt_row($t, $v) {

    global $data_font;
    global $label_font;
    
    if (strlen($v)>0) {
        echo '<tr><td valign="top" align="right">'
            .$label_font.$t.'</font></td>';
        echo '<td valign="top">'.$data_font.nl2br($v)."</td></tr>\n";
    }
}

$label_font = '<font face="Arial, Helvetica, sans-serif">';
$data_font = '<font face="Times New Roman, Times, serif">';

if (strlen($in_dn) > 0 || strlen($in_uid)>0) {
    $_SESSION['s_dn'] = $_SESSION['s_uid'] = '';
}
if (!isset($in_dn))
    {$in_dn = $_SESSION['s_dn'];
}
if (!isset($in_uid)) {
    $in_uid = $_SESSION['s_uid'];
}
if (!isset($in_dn) && isset($in_uid)) {
    $return_attr = array('cn');
    $filter = "(&(objectclass=person)(uid=$in_uid))";
    $sr = @ldap_search($ds, $ldap_base, $filter, $return_attr);
    $info = @ldap_get_entries($ds, $sr);
    $ret_cnt = $info["count"];
    if ($ret_cnt>0) {
        $in_dn = $info[0]["dn"];
    }
} elseif (!isset($in_dn)) {
    header ("REFRESH: 0; URL=user_search");
    echo "<html>\n";
    echo "<header><title>MacAllister Directory</title></head>\n";
    echo "<body>\n";
    echo "This page left intentionally blank.\n";
    echo "</body>\n";
    echo "</html>\n";
    exit;
}

$_SESSION['s_dn']  = $in_dn;
$_SESSION['s_uid'] = $in_uid;

$dump_url = 'user_dump.php?dn=' . urlencode($in_dn);
$user_dn  = $in_dn;
$filter   = '(objectclass=person)';

$sr = ldap_read($ds, $user_dn, $filter);  
if ($entry = ldap_first_entry ($ds, $sr)) {
    $attrs = ldap_get_attributes ($ds, $entry);
    $attr_cnt = $attrs["count"];
    for ($i=0;$i<$attr_cnt;$i++) {
        $this_attr = $attrs[$i];
        $err_level = error_reporting (E_ERROR | E_PARSE);
        $vals = ldap_get_values ($ds, $entry, $attrs[$i]);
        error_reporting ($err_level);
        $user_attr[$this_attr] = $vals[0];
    }
    
    $in_uid = $user_attr['uid'];
    require('inc_menu.php');
    echo '<div class="header">' .  "\n";
    echo '<h2 class="person">' . $user_attr['cn'] . "</h2>\n";
    echo '</div>' . "\n";
    echo '<div class="row">' . "\n";
    echo "<blockquote>\n";
    echo "<table border=\"0\">\n";
    
    prt_row('Given Name:',$user_attr['givenName']);
    prt_row('Surname:',   $user_attr['sn']);
    prt_row('Title:',     $user_attr['title']);
    prt_row('Location:',  $user_attr['location']);
    $comma = ',';
    if (empty($user_attr['l'])) {
      $comma='';
    }
    prt_row('Address:',
            $user_attr['postalAddress'] . "\n"
            . $user_attr['l'] . $comma . ' '
            . $user_attr['st'] . ' '
            . $user_attr['postalCode']);
    prt_row('eMail:',           $user_attr['mail']);
    prt_row('Telephone:',       $user_attr['telephoneNumber']);
    prt_row('Work Telephone:',  $user_attr['workPhone']);
    prt_row('MobileTelephone:', $user_attr['mobile']);
    prt_row('Pager:',           $user_attr['pager']);
    prt_row('Notes:',           $user_attr['comments']);

    echo "</table>\n";
    echo "</blockquote>\n";

    echo "</div>\n";

    echo "</div>\n";
    echo "<table width=\"100%\" border=\"0\">\n";
    echo "<tr>\n";
    echo "    <td align=\"right\"><a href=\"$dump_url\">";
    echo      "Show All Details</a></td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    
    $filter = "(&(objectclass=pridelistobject)(prideURLPrivate=N))";
    $return_attr = array();
    $sr = ldap_search($ds, $user_dn, $filter, $return_attr);  
    $info = ldap_get_entries($ds, $sr);
    $ret_cnt = $info["count"];
    if ($ret_cnt) {
        $url_index = array();
        for ($i=0; $i<$ret_cnt; $i++) {
            $url_index[$info[$i]['description'][0]] = $i;
        }
        ksort($url_index);
        echo "<br>\n";
        echo "<h2>Links</h2>\n";
        echo "<br>\n";
        echo "<table border=\"1\" cellpadding=\"2\">\n";
        echo "<tr>\n";
        echo " <th>Description</th>\n";
        echo " <th>URL</th>\n";
        echo "</tr>\n";
        foreach ($url_index as $desc => $i) {
            echo "<tr>\n";
            echo ' <td>'.htmlentities($info[$i]['description'][0])."</td>\n";
            $href = '<a href="'
                . htmlentities($info[$i]['prideurl'][0]).'" '
                . 'target="_BLANK">'
                . htmlentities($info[$i]['prideurl'][0]).'</a>';
            echo " <td>$href\n";
            if ($info[$i]['prideurlprivate'][0] == 'Y') {
                echo "  <br>\n";
                echo '  UID: '.htmlentities($info[$i]['uid'][0])."<br>\n";
                echo '  Credential: '
                    .htmlentities($info[$i]['pridecredential'][0])."<br>\n";
            }
            echo " </td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }

} else {
    echo "<p>\n";
    echo "<div align=\"center\">\n";
    echo "<font face=\"Arial, Helvetica, sans-serif\"\n";
    echo "      size=\"+1\"\n";
    echo "      color=\"#FF0000\">No entries found.</font>\n";
    echo "</div>\n";
}
?>


<!-- end of document body -->
<?php require ('inc_footer.php');?>
