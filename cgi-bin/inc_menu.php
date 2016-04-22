<?php
#
# Generate navigation menu that is 2 grid columns wide

$menuLoggedIn = 0;
if ( isset($_SERVER['REMOTE_USER']) ) {
    $menuLoggedIn = 1;
}

$menuItem['user_search.php'] = array('title' => 'Search',
                                     'login' => 'user');

$menuItem['change_password.php'] = array('title' => 'Password',
                                         'login' => 'user');

$menuItem['user_maint.php'] = array('title' => 'User Maint',
                                    'login' => 'admin');

$menuItem['my_links.php'] = array('title' => 'My Links',
                                  'login' => 'user');

$menuItem['my_links_maint.php'] = array('title' => 'Links Maint',
                                        'login' => 'user');

$menuItem['group_search.php'] = array ('title' => 'Groups', 'login' => 'admin');

$menuItem['apps.php'] = array ('title' => 'Apps',
                               'login' => 'admin');

$menuItem['app_access_maint.php'] = array('title' => 'App Access',
                                          'login' => 'admin');

$itemList = '';

if ( isset($_SERVER['REMOTE_USER']) ) {
    foreach ($menuItem as $id => $items) {
        if (($items['login'] == 'admin' && $ldap_admin) 
            || ($items['login'] == 'user')) {
            $title = $items['title'];
            $url = $id;
            $itemList .= '<li>';
            $itemList .= '<a href="'.$url.'">'.$title."</a>";
            $itemList .= "</li>\n";
        }
    }
} else {
  $itemList .= '<li>';
  $itemList .= '<a href="auth/user_search.php">Login</a>';
  $itemList .= "</li>\n";
}

?>
<!-- Navigation -->

<div class="col-1 menu">
<ul>
<?php echo $itemList; ?>
</ul>
</div>
