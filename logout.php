<?php
session_start();
session_destroy();
header('Location: /alicia_cprs/index.php');
exit;
