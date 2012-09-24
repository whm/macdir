<?php
#
# Generate navigation menu

$menuItem['user_search'] = 'Search';
$menuItem['user_maint']  = 'User Maint';
$menuItem['notes']       = 'Notes';
$menuItem['notes_maint'] = 'Notes Maint';
$menuItem['apps']        = 'Apps';
$menuItem['apps_maint']  = 'App Maint';

$itemList = '';
foreach ($menuItem as $id => $title) {
    $itemList .= '<li><a href="'.$id.'">'."$title</a></li>\n";
}
?>
<!-- Navigation -->

<div id="nav">
<ul>
<?php echo $itemList; ?>
</ul>
</div>

<!-- end navigation -->
