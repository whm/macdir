<?php

function macdir_bind ($this_server, $bind_type) {

  # Bind to the directory Server
  $ldap = ldap_connect("ldap://$this_server");
  if($ldap) {
      $r = ldap_bind($ldap);
  } else {
      die("ERROR: Unable to connect to $this_server!");
  }
  # Set an option
  ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
  # Attempt a GSSAPI bind only if requested and the user has
  # authenticated.
  if ($bind_type == 'GSSAPI' && isset($_SERVER['REMOTE_USER'])) {
      $r = ldap_sasl_bind($ldap,"","","GSSAPI");
      if (!isset($r)) {
          die("ERROR: GSSAPI bind to $ldap_server failed.");
      }
  }
  return $ldap;
}
?>