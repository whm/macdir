<html>
<head>
<title>testing</title>
</head>
<body>
  <h1>mod_auth_kerb test page</h1>

  <H2>KRB5CCNAME</H2>
  <pre>
<?php
   $krb = $_SERVER['KRB5CCNAME'];
   print($krb);
?>
  </pre>
  
  <H2>klist output</H2>
  <pre>
<?php
   $out = shell_exec("KRB5CCNAME=$krb /usr/bin/klist");
   print($out);
?>
  </pre>
  
  <H2>ldapwhoami using GSSAPI</H2>
  <pre>
  <?php
  $out = shell_exec("KRB5CCNAME=$krb ldapwhoami -Y GSSAPI ");
   print($out);
  ?>
  </pre>

  <H2>ldapsearch using GSSAPI</H2>
  <pre>
  <?php
   $filter = '(&(uid=mac)(objectClass=person))';
   $out = shell_exec("KRB5CCNAME=$krb ldapsearch -Y GSSAPI '$filter' cn");
   print($out);
  ?>
  </pre>

  <h2>PHP GSSAPI bind to LDAP and search</h2>
    <?php
     $this_server = 'cz-ldap.ca-zephyr.org';
     $ldap = ldap_connect("ldap://$this_server");
     if(!$ldap) {
       print("ERROR: Unable to connect to $this_server!");
     }	    
       
     # Set an option
     ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
     
      # Attempt a GSSAPI bind 
      $r = ldap_sasl_bind($ldap,"","","GSSAPI");
      if (!isset($r)) {
        die("ERROR: GSSAPI bind to $this_server failed.");
      } else {
        print("INFO: bind complete<br/>");
      }

      $base = 'dc=ca-zephyr,dc=org';
      $attr = array();
      print("<pre>\n");
      print("\nldap:\n");
      print_r($ldap);
      print("\nbase:\n");
      print_r($base);
      print("\nfilter:\n");
      print_r($filter);
      print("\nattr:\n");
      print_r($attr);
      print("</pre>\n");

      $sr = ldap_search($ldap, $base, $filter, $attr);
      $info = ldap_get_entries($ldap, $sr);
      $ret_cnt = $info["count"];
      print("Return: $ret_cnt<br/>\n");
      if ($ret_cnt) {
          for ($i=0; $i<$info["count"]; $i++) {
              foreach ($info[$i] as $attr => $values) {
                  if (is_array($values)) {
                      print("$attr<br/>\n");
                      foreach ($values as $val) {
                          print("$val<br/>\n");
                      }
                      print("<br/>\n");
                  }
              }
          }
      } else {
        echo '<p class="error">No entries found.</p>' . "\n";
      }
    ?>

  <h2>PHP INFO</h2>
    <?php phpinfo(); ?>
  </pre>
</body>
</html>
