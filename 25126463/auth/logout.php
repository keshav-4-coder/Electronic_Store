// auth/logout.php
<?php
session_start();
session_destroy();
header("Location: /25126463/index.php");
exit();
?>