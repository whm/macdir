<?php

$uid = uniqid("");
$pdf_file = "/tmp/list-phones-${uid}.pdf";

$cmd = "/var/www/macdir/list-phones.sh $uid > /dev/null ";
system ($cmd);

header("Content-type: application/pdf");
readfile($pdf_file);
flush();

unlink ($pdf_file);

?>
