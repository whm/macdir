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
    $link_filter = '(&(objectclass=' . $CONF['oc_link'] . ")(cn=$in_cn))";
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

    $chk_linkprivate_y = $chk_linkprivate_n = '';
    if ($info[0][ $CONF['attr_link_visibility'] ][0] == 'N') {
        $chk_linkprivate_n = 'CHECKED';
    } else {
        $chk_linkprivate_y = 'CHECKED';
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

    <label for="in_linkprivate">Visibility:</label>
    <input type="radio"
           <?php echo $chk_linkprivate_n;?> name="in_linkprivate"
           value="N">Public
    &nbsp;&nbsp;&nbsp;
    <input type="radio"
           <?php echo $chk_linkprivate_y;?> name="in_linkprivate"
           value="Y">Private
    <br/>

    <label for "in_linkurl">URL:</label>
    <input type="text" size="50" name="in_linkurl"
         value="<?php echo set_val($info[0][ $CONF['attr_link_url'] ][0]);?>">
    <br/>

    <label for="in_linkuid">Username:</label>
    <input type="text" size="50" name="in_linkuid"
         value="<?php echo set_val($info[0][ $CONF['attr_link_uid'] ][0]);?>">
    <br/>

    <?php
        $this_pw = set_val($info[0][ $CONF['attr_cred'] ][0]);
        $this_pat = '/^' . $CONF['key_prefix'] . '(.*)/';
        if (!empty($CONF['key']) && preg_match($this_pat, $this_pw, $m)) {
            $this_epw = $m[1];
            $this_pw = macdir_decode($this_epw);
        }
    ?>
    <label for="in_credential">Password:</label>
    <input type="password" size="50" name="in_credential"
      onkeyUp="document.getElementById('printbox').innerHTML = this.value"
      value="<?php echo $this_pw;?>"/>
    <div class="printbox" id="printbox" align="center">
        <?php echo $this_pw;?>
    </div>

    <?php
    $access_attrs = ['read', 'write'];
    foreach ($access_attrs as $a) {
      $a_attr = strtolower($CONF["attr_link_${a}"]);
      $a_new_var = 'in_new_' . strtolower($a);
      $a_old_cnt = empty($info[0][$a_attr]['count'])
                 ? 0 : $info[0][$a_attr]['count'];
      $a_old_var = 'in_' . strtolower($a) . 'uid_cnt';
    ?>
      <br/>
      <input type="hidden"
             name="<?php echo $a_old_var; ?>"
         value="<?php echo $a_old_cnt; ?>">
      <label for="<?php echo $a_new_var; ?>"><?php echo $a; ?> Access:</label>
      <input type="text" size="50" name="<?php echo $a_new_var; ?>">

      <?php if ($info[0][$a_attr]['count'] > 0) {
        for ($i=0; $i<$info[0][$a_attr]['count']; $i++) {
          $a_var     = 'in_' . strtolower($a) . "uid_${i}";
          $a_var_cur = 'in_' . strtolower($a) . "uid_current_${i}";
          $v = $info[0][$a_attr][$i];
      ?>
          <br/>
          <label for="$a_var">&nbsp;</label>
          <input type="checkbox" CHECKED
                 name="<?php echo $a_var; ?>"
                 value="<?php echo $v;?>">
                   <?php echo "$v";?>
          </input>
          <input type="hidden"
                 name="<?php echo $a_var_cur; ?>"
                 value="<?php echo $v;?>">
          </input>
        <?php } ?>
      <?php } ?>
    <?php } ?>

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

<?php require('inc_footer.php');?>
