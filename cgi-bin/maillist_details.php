<?php
//
// ----------------------------------------------------------
// Register Global Fix
//
$in_localmailbox  = $_REQUEST['in_localmailbox'];
// ----------------------------------------------------------
//

// --------------------------------------------
// file: maillist_details.php
// author: Bill MacAllister
// date:  7-Jul-2003

$title = 'Mail List Details';
$heading = "Mail List Details";
require('inc_header.php');
require ('/etc/whm/macdir_auth.php');

// clean out the messages
$msg = '';

// Bind to the directory
$ds = ldap_connect($ldap_server);
$r  = ldap_bind($ds,$ldap_manager,$ldap_password);

// mail list array
$ml_mail = array();

// Get the details for the distribution list
$filter = "(&(objectclass=prideemaillist)(localmailbox=$in_localmailbox))";
$base_dn = "ou=maillists,$ldap_base";
$return_attr = array('description',
		     'maildelivery',
		     'mailfilter');
$sr = ldap_search($ds, $base_dn, $filter, $return_attr);  
$info = ldap_get_entries($ds, $sr);
$ret_cnt = $info["count"];
if ($ret_cnt) {
  $a_desc = $info[0]["description"][0];
  $a_mailDeliveryCnt = $info[0]["maildelivery"]["count"];
  for ($i=0; $i<$a_mailDeliveryCnt; $i++) {
    $a_mailDelivery = $info[0]["maildelivery"][$i];
    $ml_mail[$a_mailDelivery] = $a_mailDelivery;
  }
  $a_mailFilterCnt = $info[0]["mailfilter"]["count"];
  for ($i=0; $i<$a_mailFilterCnt; $i++) {
    $a_mailFilter[$i] = $info[0]["mailfilter"][$i];
  }

  // search for folks in our directory on the distribution list
  $filter = "(&(objectclass=person)(maildistributionid=$in_localmailbox))";
  $base_dn = $ldap_base;
  $return_attr = array('cn',
		       'mail',
		       'uid');
  $sr = ldap_search($ds, $base_dn, $filter, $return_attr);  
  $info = ldap_get_entries($ds, $sr);
  $ret_cnt = $info["count"];
  if ($ret_cnt) {
    for ($i=0; $i<$ret_cnt; $i++) {
      $a_uid  = $info[$i]["uid"][0];
      $a_cn   = $info[$i]["cn"][0];
      $a_mail = $info[$i]["mail"][0];
      $a_userlink = '<a href="user_details.php?in_uid='.$a_uid.'">'
	 .$a_cn.'</a>';
      $ml_cn[$a_uid]   = $a_cn;
      $ml_mail[$a_uid] = $a_mail;
      $ml_link[$a_uid] = $a_userlink;
    }
  }

  // use any filters to look up the rest
  for ($filter_idx=0; $filter_idx<$a_mailFilterCnt; $filter_idx++) {
    $ldap_search_base = $ldap_base;
    $ldap_search = 'sub';
    $ldap_filter = '(&' .
                   '(objectclass=person)' .
                   '(mail=*)' .
                   "(maildistributionid=$in_localmailbox)" .
                   ')';
    if (preg_match('/^ldap:\/\/\/(.*?)\?/i', 
		   $a_mailFilter[$filter_idx], 
		   $matches)) { 
      $ldap_search_base = $matches[1];
    }
    if (preg_match('/\?(single|base|sub)\?(.*)/i', 
		   $a_mailFilter[$filter_idx], 
		   $matches)) { 
	$ldap_search = $matches[1];
	$ldap_filter = $matches[2];
    }
    $return_attr = array('cn',
			 'mail',
			 'uid');
    if ($ldap_search == 'single') {
      $sr = ldap_list($ds, $ldap_search_base, $ldap_filter, $return_attr);  
    } elseif ($ldap_search == 'base') {
      $sr = ldap_read($ds, $ldap_search_base, $ldap_filter, $return_attr);  
    } else {
      $sr = ldap_search($ds, $ldap_search_base, $ldap_filter, $return_attr);  
    }
    $info = ldap_get_entries($ds, $sr);
    $ret_cnt = $info["count"];
    if ($ret_cnt) {
      for ($i=0; $i<$ret_cnt; $i++) {
	$a_uid  = $info[$i]["uid"][0];
	$a_cn   = $info[$i]["cn"][0];
	$a_mail = $info[$i]["mail"][0];
	$a_userlink = '<a href="user_details.php?in_uid='.$a_uid.'">'
	   .$a_cn.'</a>';
	$ml_cn[$a_uid]   = $a_cn;
	$ml_mail[$a_uid] = $a_mail;
	$ml_link[$a_uid] = $a_userlink;
      }
    }
  }

?>

<div align="center">

<table border="1" cellpadding="2">
<tr>
  <th colspan="3"><?php echo "$a_desc ($in_localmailbox)";?></th>
</tr>
<tr>
  <th>Recipient UID</th>
  <th>Common Name</th>
  <th>Mail Address</th>
</tr>

<?php
  ksort($ml_mail);
  foreach ($ml_mail as $thisUID => $thisMail) {
    if ($thisUID == $thisMail) {
      $thisUID = '';
      $thisCN = '';
    } else {
      $thisCN = $ml_link[$thisUID];
    }
?>
<tr>
   <td><?php echo $thisUID;?></td>
   <td><?php echo $thisCN;?></td>
   <td><?php echo $thisMail;?></td>
</tr>
<?php
  }
}

?>
</table>
</div>

<?php require('inc_footer.php');?>

