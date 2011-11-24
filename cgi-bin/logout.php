<? //-------------------------------------------------------
   // file: logout.php
   // author: Bill MacAllister
   // date: December 2002

require('whm_php_sessions.inc');
session_destroy(); 
header ("REFRESH: 0; URL=user_search.php");

?>
<html>
<head>
<title>MacAllister LDAP Directory Logout</title>
</head>
<body>
You have been logged out.
</body>
</html>
