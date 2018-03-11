<?php
# --------------------------------------------
# file: my_list.php
# author: Bill MacAllister

$title   = "Search My Links";
$heading = "Search My Links";

require('inc_init.php');
require('inc_header_my_links.php');

$ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');

# create a form to attribute mapping
$form["commonname"]  = "cn";
$form["description"] = "description";
$form["password"]    = "pridecredential";
$form["url"]         = "prideurl";

# Define search types and set the default search
$public_filter  = '(!(prideurlprivate=Y))';
$private_filter = '(prideurlprivate=Y)';
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

$this_uid = $_SERVER['REMOTE_USER'];
?>

<div class="row">
<div class="col-9">
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

$return_attr = array('cn',
                     'description',
                     'prideurl',
                     'linkuid',
                     'pridecredential',
                     'prideurlprivate');
$filter = '(&(objectclass=pridelistobject)'. $base_filter. ')';
$sr = ldap_search($ds, $link_base, $filter, $return_attr);
ldap_sort($ds, $sr, 'description');
$info = ldap_get_entries($ds, $sr);
$ret_cnt = $info["count"];
if ($ret_cnt) {
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
    for ($i=0; $i<$info["count"]; $i++) {
        $a_cn = $info[$i]["cn"][0];
        $a_cn_url = urlencode($a_cn);

        $a_maint_link
            = '<a href="my_links_maint.php' . '?in_cn=' . $a_cn_url . '">'
            . '<img src="/macdir-images/icon-edit.png" border="0"></a>';
        $a_desc    = empty($info[$i]["description"][0])
            ? '' : $info[$i]["description"][0];

        $a_url_list = '&nbsp;';
        if (!empty($info[$i]["prideurl"][0])) {
            $url_list = explode(' ', $info[$i]["prideurl"][0]);
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
        if (!empty($info[$i]["pridecredential"][0])) {
            $pw_list = explode(' ', $info[$i]["pridecredential"][0]);
            $a_br = '';
            $a_pw_list = '';
            foreach ($pw_list as $pw) {
                $a_pw_list .= $a_br . $pw;
                $a_br = "<br/>\n";
            }
        }

        echo "<tr>\n";
        echo " <td>${a_maint_link}${a_desc}</td>\n";
        echo " <td>${a_url_list}</td>\n";
        echo ' <td>' . nbsp_html($info[$i]["linkuid"][0]) . "</td>\n";
        echo " <td>${a_pw_list}</td>\n";
        echo "</tr>\n";
    }
    echo "</tbody>\n";
    echo "</table>\n";
} else {
    echo '<p class="error">No entries found.</p>' . "\n";
}
?>

</div>

<?php require('inc_menu.php');?>
</div>

<?php require('inc_footer.php');?>
