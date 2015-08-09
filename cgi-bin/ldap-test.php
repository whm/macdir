#!/usr/bin/php
<?php

# The auth credentials are not used that are in this file.  The only
# think that is use is the ldap server and the ldap base.
require('/etc/whm/macdir_auth.php');

$thisUser = $_SERVER['REMOTE_USER'];
$thisServer = $ldap_server;

# Bind to the directory Server
$ldap = ldap_connect("ldap://$thisServer");
if($ldap) {
    $r = ldap_bind($ldap);
} else {
    echo "Unable to connect to $thisServer!";
}

# Set an option
ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

echo "<h1>Kerberos Credentials</h1>\n";
echo "<pre>\n";
system('klist');
echo "</pre>\n";

# Bind using the default kerberos credentials
if (ldap_sasl_bind($ldap,"","","GSSAPI")) {

        # Search the Directory
    $dn = $ldap_user_base;
    $filter = "(|(uid=$thisUser)(mail=$thisUser@*))";
    echo "<h1>LDAP Search</h1>\n";
    echo "Host: $thisServer<br />\n";
    echo "Base DN: $dn<br />\n";
    echo "Filter: $filter<br />\n";
    echo "REMOTE_USER: $thisUser<br />\n";

    $result = ldap_search($ldap, $dn, $filter);
    if ($result) {
        echo "<blockquote>\n";
        $cnt = ldap_count_entries($ldap, $result);
        echo "Number of entries returned is $cnt<br />\n";
        $info = ldap_get_entries($ldap,$result);
        echo "Data for " . $info["count"] . " items returned:<p>";
        print("\n");
        for($i=0;$i<$info["count"];$i++) {
            echo "dn is: " . $info[$i]["dn"] . "<br />";
            print("\n");
            echo "first cn entry is: " . $info[$i]["cn"][0] . "<br />";
            print("\n");
            echo "first email is: " . $info[$i]["mail"][0] . "<br /> <hr />";
            print("\n");
        }
        echo "</blockquote>\n";
    }
} else {
    echo '<font color="red">Bind to the directory failed.</font>'."\n";
}

ldap_close($ldap);

?>