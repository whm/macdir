<?php
#
# Generate navigation menu

$menuLoggedIn = strlen($_SESSION['whm_directory_user']);

$menuItem['user_search'] = array('Title' => 'Search',
                                  'login' => '');
$menuItem['login'] = array('inTitle'      => 'Logout',
                            'outTitle'    => 'Login',
                            'login'       => 'user',
                            'inloginURL'  => 'user_search?in_action=logout',
                            'outloginURL' => 'user_search?in_action=login',
                            );
$menuItem['change_password'] = array('inTitle'  => 'Change Password',
                                      'outTitle' => '',
                                      'login'    => 'user');
$menuItem['user_maint'] = array('inTitle'  => 'User Maint',
                                 'outTitle' => '',
                                 'login'    => 'admin');

$menuItem['my_links'] = array('inTitle'  => 'My Links',
                              'outTitle' => '',
                              'login'    => 'user');

$menuItem['my_links_maint'] = array('inTitle' => 'Links Maint',
                                    'outTitle' => '',
                                    'login'    => 'user');

$menuItem['apps'] = array ('inTitle'  => 'Apps',
                            'outTitle' => '',
                            'login'    => 'admin');
$menuItem['app_access_maint'] = array('inTitle'  => 'App Access',
                                       'outTitle' => '',
                                       'login'    => 'admin');

$menuItem['computers']      = array('inTitle'  => 'Computers',
                                     'outTitle' => '',
                                     'login'    => 'admin');
$menuItem['computer_maint'] = array('inTitle'  => 'Computer Maint',
                                     'outTitle' => '',
                                     'login'    => 'admin');

$itemList = '';
foreach ($menuItem as $id => $items) {
    if ($items['login'] == '') {
        $itemList .= '<li>';
        $itemList .= '<a href="'.$id.'">'.$items['Title']."</a>";
        $itemList .= "</li>\n";
    } elseif (($items['login'] == 'admin' && $ldap_admin) 
              || ($items['login'] == 'user')) {
        $title = $items['outTitle'];
        if ($menuLoggedIn) {$title = $items['inTitle'];}
        $url = $id;
        if (strlen($items['outloginURL']) == 0) {
            if (!$menuLoggedIn) {$url = '';}
        } else {
            if ($menuLoggedIn) {
                $url = $items['inloginURL'];
            } else {
                $url = $items['outloginURL'];
            }
        }
        if (strlen($url) > 0) {
            $itemList .= '<li>';
            $itemList .= '<a href="'.$url.'">'.$title."</a>";
            $itemList .= "</li>\n";
        }
    }
}
?>
<!-- Navigation -->

<div id="nav">
<ul>
<?php echo $itemList; ?>
</ul>
</div>

<!-- end navigation -->
