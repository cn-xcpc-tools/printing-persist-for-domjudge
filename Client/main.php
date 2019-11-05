<?php declare(strict_types=1);

if (isset($_SERVER['REMOTE_ADDR'])) {
    die("Commandline use only");
}

require_once 'config.php';
require_once 'func.php';

if (isset($argv[1])) {
    $lpn = $argv[1];
} else {
    $lpn = null;
}

$waittime = 5;
initsignals();
read_credentials();
putenv('LANG=en-US');

// Perform setup work for each endpoint we are communicating with
foreach ($endpoints as $endpointID => $endpoint) {
    $endpoints[$endpointID]['ch'] = setup_curl_handle($endpoint['user'], $endpoint['pass']);
}

// Constantly check API for unprocessed printings
$endpointIDs = array_keys($endpoints);
$currentEndpoint = 0;
while (true) {

    // If all endpoints are waiting, sleep for a bit
    $dosleep = true;
    foreach ($endpoints as $id=>$endpoint) {
        if ($endpoint["waiting"] == false) {
            $dosleep = false;
            break;
        }
    }
    // Sleep only if everything is "waiting" and only if we're looking at the first endpoint again
    if ($dosleep && $currentEndpoint==0) {
        sleep($waittime);
    }

    // Increment our currentEndpoint pointer
    $currentEndpoint = ($currentEndpoint + 1) % count($endpoints);
    $endpointID = $endpointIDs[$currentEndpoint];

    // Check whether we have received an exit signal
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
    if ($exitsignalled) {
        logmsg(LOG_NOTICE, "Received signal, exiting.");
        close_curl_handles();
        exit;
    }

    // Request open files to print. Any errors will be treated as
    // non-fatal: we will just keep on retrying in this loop.
    $judging = request('printing/next-printing', 'POST', '', false);
    // If $judging is null, an error occurred; don't try to decode.
    if (!is_null($judging)) {
        $row = dj_json_decode($judging);
    }

    // nothing returned -> no open submissions for us
    if (empty($row)) {
        if (! $endpoints[$endpointID]["waiting"]) {
            logmsg(LOG_INFO, "No printings in queue (for endpoint $endpointID), waiting...");
            $endpoints[$endpointID]["waiting"] = true;
        }
        continue;
    }

    // we have gotten a submission for judging
    $endpoints[$endpointID]["waiting"] = false;

    logmsg(LOG_NOTICE, "Printing request p$row[id] (endpoint $endpointID)...");

    $lang    = $row['lang'];
    $printid = intval($row['id']);
    $team    = $row['team'];
    $room    = $row['room'];
    $orig_filename = $row['filename'];
    $temp_filename = '/tmp/djpc_p' . $printid;
    file_put_contents($temp_filename, base64_decode($row['sourcecode']));
    sendToPrinter($lpn, $temp_filename, $orig_filename, $lang, $team, $room);
    unlink($temp_filename);
    if (balloon_autodone) {
        request(sprintf('printing/set-done/%d', $printid), 'POST', '', false);
    }

    // Check if we were interrupted while printing, if so, exit(to avoid sleeping)
    if ($exitsignalled) {
        logmsg(LOG_NOTICE, "Received signal, exiting.");
        close_curl_handles();
        exit;
    }

    // restart the printing loop
}
