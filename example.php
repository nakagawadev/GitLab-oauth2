<?php
require __DIR__ . '/vendor/autoload.php';
use nakagawadev\GitLabOauth2;

// Redirect uri
$redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

session_start();

// We instantiate the client
$GitLabOauth2 = new GitLabOauth2();

// Secure state
if(empty($_SESSION['GitLabOauth2State']))
    $_SESSION['GitLabOauth2State'] = $GitLabOauth2->createSecureState();

// Log out
if(isset($_REQUEST['logout'])) {
    unset($_SESSION['GitLabOauth2']);
    unset($_SESSION['GitLabOauth2State']);
}

// Confgi client
$GitLabOauth2->setConfig([
    'app_id' => 'APP_ID',
    'app_secret' => 'APP_SECRET',
    'redirect_uri' => $redirect_uri,
    'state' => $_SESSION['GitLabOauth2State'],
    'scopes' => 'api'
]);

// Get code?
if(isset($_GET['code'])) {
    // verify the "state token"
    if(!$GitLabOauth2->verifySecureState($_SESSION['GitLabOauth2State'], $_GET['state']))
        exit('Invalid state');
    // Get access token
    $token = $GitLabOauth2->getAccessToken($_GET['code']);
    // Save the token in session
    $_SESSION['GitLabOauth2'] = $token;
    // Redirect to the example page
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
}

// Is there a token in the session?
if(!empty($_SESSION['GitLabOauth2'])) {
    // Set the token
    $GitLabOauth2->setAccessToken($_SESSION['GitLabOauth2']);
    // Access token expired?
    if($GitLabOauth2->isAccessTokenExpired()) {
        // Refresh access token and update session
        $_SESSION['GitLabOauth2'] = $GitLabOauth2->refreshAccessToken();
        $renoved = true;
    }
    // Get user data
    $user = json_decode($GitLabOauth2->api('user')->response);
} else {
    // Get auth URL
    $authUrl = $GitLabOauth2->getAuthUrl();
}
?>
<div>
    <?php if(isset($authUrl)): ?>
        <a href="<?= $authUrl ?>">Connect with GitLab</a>
    <?php elseif(!empty($_SESSION['GitLabOauth2'])): ?>
        <p>Hi <?= $user->username ?>, your tooken is:</p>
        <pre><?= var_dump($_SESSION['GitLabOauth2']) ?></pre>
        <?php if(isset($renoved)): ?>
            <p>The access token expired but was successfully renewed.</p>
        <?php endif ?>
        <a href="<?= $redirect_uri ?>?logout">Log out</a>
    <?php endif ?>
</div>