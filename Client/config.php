<?php

/**
 * Config file for printing client
 *
 * Some thing are needed for this client,
 * such as an account with BALLOON role,
 * some printer scheduling rules, etc.
 */

define('balloon_username', 'balloon');
define('balloon_password', 'ba1100n');
define('balloon_endpoint', 'http://localhost/domjudge/api');
define('balloon_autodone', true); // SET false if you want to handle the print document as sent by yourself
define('balloon_waittask', true);


/**
 * Function to send a local file to the printer.
 * Change this to match your local setup.
 *
 * The following parameters are available. Make sure you escape
 * them correctly before passing them to the shell.
 *   $filename: the on-disk file to be printed out
 *   $origname: the original filename as submitted by the team
 *   $language: langid of the programming language this file is in
 *   $username: username or teamname of the team this user belongs to
 *   $location: room/place to bring it to.
 *
 * Returns array with two elements: first a boolean indicating
 * overall success, and second a string to be displayed to the user.
 *
 * The default configuration of this function depends on the enscript
 * tool. It will optionally format the incoming text for the
 * specified language, and adds a header line with the team ID for
 * easy identification. To prevent misuse the amount of pages per
 * job is limited to 10.
 */
function sendToPrinter($lpname, string $filename, string $origname,
    $language = null, string $username, $location = null)
{
    global $exitsignalled;
    // Map our language to enscript language:
    $lang_remap = array(
        'adb'    => 'ada',
        'awk'    => 'awk',
        'bash'   => 'sh',
        'c'      => 'c',
        'csharp' => 'c',
        'cpp'    => 'cpp',
        'f95'    => 'f90',
        'hs'     => 'haskell',
        'java'   => 'java',
        'js'     => 'javascript',
        'kt'     => 'kt',
        'lua'    => 'lua',
        'pas'    => 'pascal',
        'pl'     => 'perl',
        'sh'     => 'sh',
        'plg'    => 'prolog',
        'py'     => 'python',
        'py2'    => 'python',
        'py3'    => 'python',
        'r'      => 'r',
        'rb'     => 'ruby',
        'scala'  => 'scala',
        'swift'  => 'swift',
        'plain'  => null,
    );
    if (isset($language) && array_key_exists($language, $lang_remap)) {
        $language = $lang_remap[$language];
    } else {
        $language = null;
    }
    $highlight = "";
    if (! empty($language)) {
        $highlight = "-E" . escapeshellarg($language);
    }
    $printerset = "";
    if (! empty($lpname)) {
        $printerset = " -d " . escapeshellarg($lpname) . " ";
    }

    $header = sprintf("Team: %s ", $username) .
              (!empty($location) ? "[".$location."]":"") .
              " File: $origname||Page $% of $=";

    // For debugging or spooling to a different host.
    // Also uncomment '-p $tmp' below.
    // $tmp = tempnam('/tmp', 'print_'.$username.'_');

    // You can add your printer giving rules here
    $cmd = "enscript -C " . $highlight . $printerset
         . " -b " . escapeshellarg($header)
         . " -a 0-10 "
         . " -f Courier9 "
      // . " -p $tmp "
         . escapeshellarg($filename) . " 2>&1";

    exec($cmd, $output, $retval);
    foreach ($output as $line)
        logmsg(LOG_NOTICE, $line);
    if ($retval != 0) error('Please check it out.');
    unset($output);
    unset($line);

    $finished = false;
    while (balloon_waittask && !$finished) {
        exec('lpq' . (empty($lpname) ? '' : (' -P ' . escapeshellarg($lpname))), $output2, $retval2);
        if (strstr($output2[1], 'no entries') !== FALSE) {
            $finished = true;
        } else {
            logmsg(LOG_NOTICE, 'Printing queue not empty, waiting...');
            sleep(5);

            // Check whether we have received an exit signal
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            if ($exitsignalled) {
                logmsg(LOG_NOTICE, "Received signal, exiting.");
                close_curl_handles();
                exit;
            }
        }
        unset($output2);
        unset($retval2);
    }
}

