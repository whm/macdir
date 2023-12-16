<?php

# The auth credentials are not used that are in this file.  The only
# think that is use is the ldap server and the ldap base.

$thisUser = getenv('REMOTE_USER');
$thisTgt = getenv('KRB5CCNAME');
$thisServer = 'cz-ldap.ca-zephyr.org';

echo "<h1>Kerberos Credentials</h1>\n";
echo("REMOTEUSER: $thisUser<br>\n");
echo("KRB5CCNAME: $thisTgt<br>\n");

echo "<pre>\n";
system("KRB5CCNAME=$thisTgt /usr/bin/klist");
echo "</pre>\n";

echo "<h1>Perl directory search</h1>\n";
$json = shell_exec("KRB5CCNAME=$thisTgt /home/mac/macdir-ldap-read --conf=/home/mac/macdir.conf");

echo "$json<br/>\n";
echo "<br/>\n";
$entries = json_decode($json, true);
var_dump($entries);

echo "<br/>\n";
echo "<br/>\n";
echo "iterate over array<br/>\n";
foreach ($entries as $dn => $entry) {
    echo "<h2>dn: $dn</h2>\n";
    foreach ($entry as $attr => $vals) {
        echo "&nbsp; $attr<br/>\n";
	foreach ($vals as $val) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp; $val<br/>\n";
	}
    }	
}
    
# Bind to the directory Server
echo "<h1>Bind to directory</h1>\n";
$ldap = ldap_connect("ldap://$thisServer");
if($ldap) {
    $r = ldap_bind($ldap);
} else {
    echo "Unable to connect to $thisServer!";
}

# Set an option
ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

echo "<h1>PHP ldap_sasl_bind</h1>\n";
# Bind using the default kerberos credentials
if (ldap_sasl_bind($ldap,"","","GSSAPI")) {
    echo "Bind successful<br/>\n";
    # Search the Directory
    $dn = $ldap_user_base;
    $filter = "(|(uid=$thisUser)(mail=$thisUser@*))";
    echo "<h1>LDAP Search</h1>\n";
    echo "Host: $thisServer<br />\n";
    echo "Base DN: $dn<br />\n";
    echo "Filter: $filter<br />\n";
    echo "UID: $thisUser<br />\n";

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

phpinfo();
?>