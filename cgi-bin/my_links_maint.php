<?php
//
// file: my_links_maint.php
// author: Bill MacAllister

$title   = 'Link Maintenance';
$heading = 'Link Maintenance';

require('inc_init.php');
require('inc_header.php');

$this_dn = '';

if (empty($_REQUEST['in_cn']) || !empty($_REQUEST['in_button_reset'])) {
    $in_cn = '';
} else {
    $in_cn = $_REQUEST['in_cn'];
    $this_tgt = getenv('KRB5CCNAME');

    $link_owner = $_SERVER['REMOTE_USER'];
    $link_filter = '(&(objectclass=' . $CONF['oc_link'] . ")(cn=$in_cn))";

    $cmd = 'KRB5CCNAME=' . $this_tgt . ' /usr/bin/macdir-pw-read'
        . ' --owner=' . $link_owner
        . ' --filter="' . $link_filter . '"';
    $ldap_json = shell_exec($cmd);
    $ret_cnt = 0;
    $entries = array();
    if (isset($ldap_json) && strlen($ldap_json) > 0) {
        $entries = json_decode($ldap_json, true);
        if (is_array($entries)) {
            foreach ($entries as $dn => $entry) {
                $this_dn = $dn;
                $ret_cnt++;
            }
        }
    }
    $info = '';
    if ($ret_cnt == 1) {
        $entry_found = 1;
        $info = $entries[$this_dn];
    } elseif ($ret_cnt > 1) {
        set_warn("More than one entry found for $link_filter search.");
    } else {
        set_warn('No entry found.');
    }

    $chk_linkprivate_y = $chk_linkprivate_n = '';
    if (is_array($info) && $info[ $CONF['attr_link_visibility'] ][0] == 'N') {
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
// Display any messages and clear out the message variable once
// messages are displayed.
if (!empty($_SESSION['in_msg'])) {
    echo '<p>' . $_SESSION['in_msg'] . "</p>\n";
    $_SESSION['in_msg'] = '';
}

// Make sure we don't reference empty variables
$a_cn       = '';
$a_desc     = '';
$a_link_url = '';
$a_link_uid = '';
$this_pw    = '';
if (is_array($info)) {
    $a_cn = empty($info['cn'][0]) ?
        '' : trim($info['cn'][0]);
    $a_desc = empty($info['description'][0]) ?
        '' : trim($info['description'][0]);
    $a_link_url = empty($info[ $CONF['attr_link_url'] ][0]) ?
        '' : trim($info[ $CONF['attr_link_url'] ][0]);
    $a_link_uid = empty($info[ $CONF['attr_link_uid'] ][0]) ?
        '' : trim($info[ $CONF['attr_link_uid'] ][0]);
    $this_pw = empty($info[ $CONF['attr_cred'] ][0]) ?
        '' : trim($info[ $CONF['attr_cred'] ][0]);
    $this_pat = '/^' . $CONF['key_prefix'] . '(.*)/';
    if (!empty($CONF['key']) && preg_match($this_pat, $this_pw, $m)) {
        $this_epw = $m[1];
        $this_pw = macdir_decode($this_epw);
    }
}
?>
<form name="maint"
      method="post"
      action="my_links_maint_action.php"
      onsubmit="return checkIt()">

    <label for="in_description">Description:</label>
    <input type="text" name="in_description" size="60"
             value="<?php echo $a_desc;?>">
    <br/>

    <label for "in_cn">Common Name:</label>
<?php if (is_array($info) && !empty($info['cn'][0])) { ?>
    <input type="hidden" name="in_cn" value="<?php echo $a_cn;?>">
    <?php echo $info['cn'][0];?>
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
         value="<?php echo $a_link_url;?>">
    <br/>

    <label for="in_linkuid">Username:</label>
    <input type="text" size="50" name="in_linkuid"
         value="<?php echo $a_link_uid;?>">
    <br/>

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
      $a_old_cnt = 0;
      if (is_array($info) && array_key_exists($a_attr, $info)) {
          $a_old_cnt = count($info[$a_attr]);
      }
      $a_old_var = 'in_' . strtolower($a) . 'uid_cnt';
    ?>
      <br/>
      <input type="hidden"
             name="<?php echo $a_old_var; ?>"
             value="<?php echo $a_old_cnt; ?>">
      <label for="<?php echo $a_new_var; ?>"><?php echo $a; ?> Access:</label>
      <input type="text" size="50" name="<?php echo $a_new_var; ?>">

      <?php if (is_array($info) && array_key_exists($a_attr, $info)
                && count($info[$a_attr]) > 0) {
        for ($i=0; $i<count($info[$a_attr]); $i++) {
          $a_var     = 'in_' . strtolower($a) . "uid_${i}";
          $a_var_cur = 'in_' . strtolower($a) . "uid_current_${i}";
          $v = $info[$a_attr][$i];
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

<?php if (is_array($info) && array_key_exists('cn', $info)) {?>

 <p>
  <input type="submit" name="in_button_update" value="Update">
  <input type="submit" name="in_button_delete" value="Delete">
 </p>

<?php } else { ?>

 <p>
  <input type="submit" name="in_button_add" value="Add">
 </p>

<?php } ?>

<?php if (!empty($this_dn)) { ?>
<input type="hidden" name="in_dn" value="<?php echo $this_dn;?>">
<?php } ?>

</form>

</div>

<?php require('inc_footer.php');?>
