<?php
$installerUrl = 'https://getcomposer.org/installer';
$setupFile = 'composer-setup.php';
echo "Downloading installer from $installerUrl...\n";
if (copy($installerUrl, $setupFile)) {
    echo "Installer downloaded. Running setup...\n";
    include $setupFile;
    echo "\nSetup finished. Cleaning up...\n";
    unlink($setupFile);
    if (file_exists('composer.phar')) {
        echo "composer.phar created successfully!\n";
    } else {
        echo "Failed to create composer.phar.\n";
    }
} else {
    echo "Failed to download installer.\n";
}
