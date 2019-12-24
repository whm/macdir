<?php
//
// file: my_links_maint.php
// author: Bill MacAllister

$title   = 'Link Maintenance';
$heading = 'Link Maintenance';

require('inc_init.php');
require('inc_header.php');

$ds = macdir_bind($CONF['ldap_server'], 'GSSAPI');

if (empty($_REQUEST['in_cn']) || !empty($_REQUEST['in_button_reset'])) {
    $in_cn = '';
} else {
    $in_cn = $_REQUEST['in_cn'];

    $return_attr = array();
    $link_base = 'uid='.$_SERVER['REMOTE_USER'] . ',' . $ldap_user_base;
    $link_filter = "(&(objectclass=pridelistobject)(cn=$in_cn))";
    $sr = @ldap_search ($ds, $link_base, $link_filter, $return_attr);
    $info = @ldap_get_entries($ds, $sr);
    $ret_cnt = $info["count"];
    if ($ret_cnt == 1) {
        $entry_found = 1;
    } elseif ($retcnt > 1) {
        $_SESSION['in_msg']
            .= warn_html("More than on entry found for $ldap_filter search.");
    } else {
        $_SESSION['in_msg'] .= warn_html('No entry found.');
    }

    $chk_prideurlprivate_y = $chk_prideurlprivate_n = '';
    if ($info[0]['prideurlprivate'][0] == 'N') {
        $chk_prideurlprivate_n = 'CHECKED';
    } else {
        $chk_prideurlprivate_y = 'CHECKED';
    }

}

?>

<script language="JavaScript">

/* ----------------- */
/* Verify input data */
/* ----------------- */

function checkIt() {

    var f;
    var i;
    var outData = "";

    EmptyField = "";
    f = document.maint;

    var nondigit = /\D/;
    var anumber = /^\d*\.?\d*$/;

    if (f.in_cn.value == EmptyField) {
        alert ("Please enter a Common Name");
        return false;
    }

    return true;

}

</script>

<?php
##############################################################################
# Main Routine
##############################################################################
?>

<div class="row">
<div class="col-9">

<form name="maint_find"
      method="post"
      action="<?php print $_SERVER['PHP_SELF']; ?>">

    <label for "in_cn">Common Name:</label>
    <input type="text"
             name="in_cn"
             value="<?php print $in_cn;?>">
    <p>
    <input type="submit" name="in_button_find" value="Lookup">
    <input type="submit" name="in_button_reset" value="Reset">
    </p>
</form>
<?php
if (!empty($_SESSION['in_msg'])) {
    echo '<p>' . $_SESSION['in_msg'] . "</p>\n";
    $_SESSION['in_msg'] = '';
}
?>
<form name="maint"
      method="post"
      action="my_links_maint_action.php"
      onsubmit="return checkIt()">

    <label for="in_description">Description:</label>
    <input type="text" name="in_description" size="60"
             value="<?php echo set_val($info[0]['description'][0]);?>">
    <br/>

    <label for "in_cn">Common Name:</label>
<?php if ( !empty($info[0]['cn'][0]) ) { ?>
    <input type="hidden" name="in_cn" value="<?php echo $info[0]['cn'][0];?>">
    <?php echo $info[0]['cn'][0];?>
<?php } else { ?>
    <input type="text" name="in_cn" value="">
<?php } ?>
    <br/>

    <label for="in_prideurlprivate">Visibility:</label>
    <input type="radio"
           <?php echo $chk_prideurlprivate_n;?> name="in_prideurlprivate"
           value="N">Public
    &nbsp;&nbsp;&nbsp;
    <input type="radio"
           <?php echo $chk_prideurlprivate_y;?> name="in_prideurlprivate"
           value="Y">Private
    <br/>

    <label for "in_prideurl">URL:</label>
    <input type="text" size="50" name="in_prideurl"
           value="<?php echo set_val($info[0]['prideurl'][0]);?>">
    <br/>

    <label for="in_linkuid">Username:</label>
    <input type="text" size="50" name="in_linkuid"
           value="<?php echo set_val($info[0]['linkuid'][0]);?>">
    <br/>

    <?php
        $this_pw = set_val($info[0]['pridecredential'][0]);
        $this_pat = '/^' . $CONF['key_prefix'] . '(.*)/';
        if (!empty($CONF['key']) && preg_match($this_pat, $this_pw, $m)) {
            $this_epw = $m[1];
            $this_pw = macdir_decode($this_epw);
        }
    ?>
    <label for="in_pridecredential">Password:</label>
    <input type="password" size="50" name="in_pridecredential"
      onkeyUp="document.getElementById('printbox').innerHTML = this.value"
      value="<?php echo $this_pw;?>"/>
    <div class="printbox" id="printbox" align="center">
        <?php echo $this_pw;?>
    </div>
    <br/>

<?php if ( !empty($info[0]['cn'][0]) ) {?>

 <p>
  <input type="submit" name="in_button_update" value="Update">
  <input type="submit" name="in_button_delete" value="Delete">
 </p>

<?php } else { ?>

 <p>
  <input type="submit" name="in_button_add" value="Add">
 </p>

<?php } ?>

<?php if (!empty($info[0]['dn'])) { ?>
<input type="hidden" name="in_dn" value="<?php echo $info[0]['dn'];?>">
<?php } ?>

</form>

<?php
 ldap_close($ds);
?>

</div>

<?php require('inc_menu.php');?>
</div>

<?php require('inc_footer.php');?>
