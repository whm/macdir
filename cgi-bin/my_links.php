<?php
# --------------------------------------------
# file: my_list.php
# author: Bill MacAllister

$title   = "Search My Links";
$heading = "Search My Links";

require('inc_init.php');
require('inc_header_my_links.php');

#echo "<pre>\n";
#print_r($CONF);
#echo "</pre>\n";

# ----------------------------------------------------------------------
# Subroutines

// ----------------------------------------
// Pull the UID form a DN.  If there is no uid just the whole dn

function uid_from_dn($dn) {
    $return = preg_match('/,{0,1}uid=([^,]*)/i', $dn, $mat);
    if (empty($mat[1])) {
        $uid = $dn;
    } else {
        $uid = $mat[1];
    }
    return $uid;
}

// ----------------------------------------
// Display search results

function display_links($edit_flag, $title, $entries) {

    global $CONF;

    echo "<h2>$title</h2>\n";
    echo "<table>\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo " <th>Description</th>\n";
    echo " <th>URL</th>\n";
    echo " <th>Username</th>\n";
    echo " <th>Password</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    foreach ($entries as $dn => $entry) {
        $a_cn = $entry['cn'][0];
        $a_cn_url = urlencode($a_cn);

        $a_maint_link
            = '<a href="my_links_maint.php' . '?in_cn=' . $a_cn_url . '">'
            . '<img src="/macdir-images/icon-edit.png" border="0"></a>';
        $a_desc    = empty($entry['description'][0])
            ? '' : $entry['description'][0];

        $a_url_list = '&nbsp;';
        $a_url_attr = $CONF['attr_link_url'];
        if (array_key_exists($a_url_attr, $entry)) {
            $url_list = explode(' ', $entry[$a_url_attr][0]);
            $a_br = '';
            $a_url_list = '';
            foreach ($url_list as $u) {
                $a_url_list .= $a_br
                    . '<a href="' . htmlentities($u) . '" target="_BLANK">'
                    . $u . '</a>';
                $a_br = "<br/>\n";
            }
        }

        $a_pw_list = '&nbsp;';
        $a_pw_attr = $CONF['attr_cred'];
        if (array_key_exists($a_pw_attr, $entry)) {
            $pw_list = explode(' ', $entry[$a_pw_attr][0]);
            $a_br = '';
            $a_pw_list = '';
            foreach ($pw_list as $pw) {
                $this_pat = '/^' . $CONF['key_prefix'] . '(.*)/';
                if (!empty($CONF['key'])
                    && preg_match($this_pat, $pw, $m))
                {
                    $this_epw = $m[1];
                    $pw = macdir_decode($this_epw);
                }
                $a_pw_list .= $a_br . $pw;
                $a_br = "<br/>\n";
            }
        }

        if ($edit_flag) {
            $a_desc_link = $a_maint_link . $a_desc;
        } else {
            $a_desc_link = $a_desc . ' (' . uid_from_dn($entry['dn']) . ')';
        }
        $a_attr_uid = $CONF['attr_link_uid'];
        if (array_key_exists($a_attr_uid, $entry)) {
            $a_uid = $entry[$a_attr_uid][0];
        } else {
            $a_uid = '';
        }
        echo "<tr>\n";
        echo " <td>$a_desc_link</td>\n";
        echo " <td>${a_url_list}</td>\n";
        echo ' <td>' . nbsp_html($a_uid) . "</td>\n";
        echo " <td>${a_pw_list}</td>\n";
        echo "</tr>\n";
    }
    echo "</tbody>\n";
    echo "</table>\n";
    return;
}

# ----------------------------------------------------------------------
# Main Routine

# create a form to attribute mapping
$form["commonname"]  = "cn";
$form["description"] = "description";
$form["password"]    = $CONF['attr_cred'];
$form["url"]         = $CONF['attr_link_url'];

# Define search types and set the default search
$public_filter  = '(!(' . $CONF['attr_link_visibility'] . '=Y))';
$private_filter = '(' . $CONF['attr_link_visibility'] . '=Y)';
$class_filter   = $public_filter;

# Set the search type
$btn_search_private = empty($_REQUEST['btn_search_private'])
    ? '' : $_REQUEST['btn_search_private'];
$btn_search_public = empty($_REQUEST['btn_search_public'])
    ? '' : $_REQUEST['btn_search_public'];
if ("${btn_search_private}${btn_search_public}" == '') {
    if (isset($_SESSION['MY_stype']) && $_SESSION['MY_stype'] == 'private') {
        $class_filter = $private_filter;
    }
} elseif ($btn_search_public == 'public') {
    $class_filter         = $public_filter;
    $_SESSION['MY_stype'] = 'public';
} elseif ($btn_search_private == 'private') {
    $class_filter         = $private_filter;
    $_SESSION['MY_stype'] = 'private';
}

# set session information
if ("${btn_search_private}${btn_search_public}" != '') {
    foreach ($form as $formName => $ldapName) {
        $sname = "MY_${formName}";
        $fname = "in_${formName}";
        $_SESSION[$sname] = empty($_REQUEST[$fname]) ? '' : $_REQUEST[$fname];
    }
}

# construct the filter from input data
$base_filter = '';
foreach ($form as $formName => $ldapName) {
    $fname = "in_${formName}";
    if (!empty($_REQUEST[$fname])) {
        $a_val        = $_REQUEST[$fname];
        $base_filter .= "($ldapName=*$a_val*)";
    }
}

// Construct a filter from session information if there
// is no input data.
if ($base_filter == '') {
    foreach ($form as $formName => $ldapName) {
        $sname = "MY_${formName}";
        $fname = "in_${formName}";
        if (!empty($_SESSION[$sname])) {
            $a_val        = $_SESSION[$sname];
            $base_filter .= "($ldapName=*$a_val*)";
        }
    }
} else {
    foreach ($form as $formName => $ldapName) {
        $sname = "MY_${formName}";
        $fname = "in_${formName}";
        $_SESSION[$sname] = empty($_REQUEST[$fname]) ? '' : $_REQUEST[$fname];
    }
}

$this_princ = getenv('REMOTE_USER');
$this_uid = krb_uid($this_princ);
?>

<div class="row">
<form name="link_search"
    action="<?php echo $_SERVER['PHP_SELF'];?>"
    method="POST">

    <label for="in_commonname">Name</label>
    <input type="text" name="in_commonname"
           value="<?php print $_SESSION['MY_commonname'];?>"
           placeholder="Fragment of a Name">
    <br/>

    <label for="in_description">Description</label>
    <input type="text" name="in_description"
           value="<?php print $_SESSION['MY_description'];?>">
    <br/>

    <label for="in_url">URL</label>
    <input type="text" name="in_url"
           value="<?php print $_SESSION['MY_url'];?>">
    <br/>

    <p align="center">Search:
    <input type="submit" value="public" name="btn_search_public">
    <input type="submit" value="private" name="btn_search_private">
    </p>

<?php
if ( !empty($_SESSION['in_msg']) ) {
    echo '<p>' . $_SESSION['in_msg'] . "</p>\n";
    $_SESSION['in_msg'] = '';
}
?>
</form>

<?php

$link_base = "uid=${this_uid},${ldap_user_base}";
$base_filter .= $class_filter;
$filter = '(&(objectclass=' . $CONF['oc_link'] . ')'. $base_filter. ')';
$thisTgt = getenv('KRB5CCNAME');
$attr_list = array('cn',
                   'description',
                    $CONF['attr_cred'],
                    $CONF['attr_link_url'],
                    $CONF['attr_link_uid'],
                    $CONF['attr_link_visibility']);
$attrs = implode(',', $attr_list);

$cmd = 'KRB5CCNAME=' . $thisTgt . ' /usr/bin/macdir-ldap-read'
   . ' --base=' . $link_base
   . ' --filter="' . $filter . '"'
   . ' --attrs=' . $attrs;
$ldap_json = shell_exec($cmd);
if (isset($ldap_json) && strlen($ldap_json) > 0) {
    $entries = json_decode($ldap_json, true);
    display_links(1, 'Links', $entries);
} else {
    echo '<p class="error">No entries found.</p>' . "\n";
}

if (!empty($_SERVER['REMOTE_USER'])) {
    $link_filter = '(|(' . $CONF['attr_link_read'] . "=${this_uid})"
                 . '(' . $CONF['attr_link_write'] . "=${this_uid}))";
    $filter      = '(&'
                 . '(objectclass=' . $CONF['oc_link'] . ')'
                 . $base_filter
                 . $link_filter
                 . ')';
    $ldap_json = shell_exec('KRB5CCNAME=' . $thisTgt
	   . ' /home/mac/macdir-ldap-read --conf=/etc/macdir/ldap.conf'
	   . ' -b ' . $ldap_user_base
	   . '"' . $filter . '"'
	   . $attrs);
    if (isset($ldap_json) && strlen($ldap_json) > 0) {
        $entries = json_decode($ldap_json, true);
        display_links(0, 'Shared Links', $entries);
    }
}

?>

</div>

<?php require('inc_footer.php');?>
