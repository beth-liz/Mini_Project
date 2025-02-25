<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Add cache control headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Add meta refresh as a fallback
echo '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <script>
        if (window.history && window.history.pushState) {
            window.history.pushState("", "", "logout.php");
            window.onpopstate = function() {
                window.history.pushState("", "", "logout.php");
                window.location.href = "signin.php";
            };
        }
    </script>
</head>
<body>
    <h1>Logging out...</h1>
</body>
</html>';

// Small delay to ensure the page loads
sleep(1);

// Redirect to login page
header("Location: signin.php");
exit();
?> 