<?php declare(strict_types=1);

function read_credentials()
{
    global $endpoints;

    $endpoints = [ 'default' => [
        "url" => balloon_endpoint,
        "user" => balloon_username,
        "pass" => balloon_password,
        "waiting" => false,
        "errorred" => false,
        "autodone" => balloon_autodone,
    ]];
}

function setup_curl_handle(string $restuser, string $restpass)
{
    $curl_handle = curl_init();
    curl_setopt($curl_handle, CURLOPT_USERAGENT, "DOMjudge/7.0.2");
    curl_setopt($curl_handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl_handle, CURLOPT_USERPWD, $restuser . ":" . $restpass);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    return $curl_handle;
}

function close_curl_handles()
{
    global $endpoints;
    foreach($endpoints as $id => $endpoint) {
        if ( ! empty($endpoint['curl']) ) {
            curl_close($endpoint['curl']);
            unset($endpoints[$id]['curl']);
        }
    }
}


/**
 * Perform a request to the REST API and handle any errors.
 * $url is the part appended to the base DOMjudge $resturl.
 * $verb is the HTTP method to use: GET, POST, PUT, or DELETE
 * $data is the urlencoded data passed as GET or POST parameters.
 * When $failonerror is set to false, any error will be turned into a
 * warning and null is returned.
 */
$lastrequest = '';
function request(string $url, string $verb = 'GET', string $data = '', bool $failonerror = true)
{
    global $endpoints, $endpointID, $lastrequest;

    // Don't flood the log with requests for new judgings every few seconds.
    if (strpos($url, 'printing/next-printing') === 0 && $verb==='POST') {
        if ($lastrequest!==$url) {
            logmsg(LOG_DEBUG, "API request $verb $url");
            $lastrequest = $url;
        }
    } else {
        logmsg(LOG_DEBUG, "API request $verb $url");
        $lastrequest = $url;
    }

    $url = $endpoints[$endpointID]['url'] . "/" . $url;
    $curl_handle = $endpoints[$endpointID]['ch'];
    if ($verb == 'GET') {
        $url .= '?' . $data;
    }

    curl_setopt($curl_handle, CURLOPT_URL, $url);

    curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, $verb);
    curl_setopt($curl_handle, CURLOPT_HTTPHEADER, []);
    if ($verb == 'POST') {
        curl_setopt($curl_handle, CURLOPT_POST, true);
        if (is_array($data)) {
            curl_setopt($curl_handle, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);
        }
    } else {
        curl_setopt($curl_handle, CURLOPT_POST, false);
    }
    if ($verb == 'POST' || $verb == 'PUT') {
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $data);
    } else {
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, null);
    }

    $response = curl_exec($curl_handle);
    if ($response === false) {
        $errstr = "Error while executing curl $verb to url " . $url . ": " . curl_error($curl_handle);
        if ($failonerror) {
            error($errstr);
        } else {
            warning($errstr);
            $endpoints[$endpointID]['errorred'] = true;
            return null;
        }
    }
    $status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
    if ($status < 200 || $status >= 300) {
        if ($status == 401) {
            $errstr = "Authentication failed (error $status) while contacting $url. " .
                "Check credentials in restapi.secret.";
        } else {
            $errstr = "Error while executing curl $verb to url " . $url .
                ": http status code: " . $status . ", response: " . $response;
        }
        if ($failonerror) {
            error($errstr);
        } else {
            warning($errstr);
            $endpoints[$endpointID]['errorred'] = true;
            return null;
        }
    }

    if ( $endpoints[$endpointID]['errorred'] ) {
        $endpoints[$endpointID]['errorred'] = false;
        $endpoints[$endpointID]['waiting'] = false;
        logmsg(LOG_NOTICE, "Reconnected to endpoint $endpointID.");
    }

    return $response;
}


/**
 * Retrieve a value from the configuration through the REST API.
 */
function dbconfig_get_rest(string $name)
{
    $res = request('config', 'GET', 'name=' . urlencode($name));
    $res = dj_json_decode($res);
    return $res[$name];
}


/**
 * Log a message $string on the loglevel $msglevel.
 * Prepends a timestamp and log to the logfile.
 * If this is the web interface: write to the screen with the right CSS class.
 * If this is the command line: write to Standard Error.
 */
function logmsg(int $msglevel, string $string)
{
    global $verbose, $loglevel;

    // Trim $string to reasonable length to prevent server/browser crashes:
    $string = substr($string, 0, 10000);

    $msec = sprintf("%03d", (int)(explode(' ', microtime())[0]*1000));
    $stamp = "[" . strftime("%b %d %H:%M:%S") . ".$msec] " . 'printclient' .
        (function_exists('posix_getpid') ? "[" . posix_getpid() . "]" : "") .
        ": ";

    if (true || $msglevel <= $verbose) {
        fwrite(STDERR, $stamp . $string . "\n");
        fflush(STDERR);
    }
    if ($msglevel <= $loglevel) {
        if (defined('STDLOG')) {
            fwrite(STDLOG, $stamp . $string . "\n");
            fflush(STDLOG);
        }
        if (defined('SYSLOG')) {
            syslog($msglevel, $string . "\n");
        }
    }
}


/**
 * Log an error at level LOG_ERROR and exit with exitcode 1.
 */
function error(string $string)
{
    logmsg(LOG_ERR, "error: $string");
    exit(1);
}


/**
 * Decode a JSON string and handle errors.
 */
function dj_json_decode(string $str)
{
    $res = json_decode($str, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error("Error decoding JSON data '$str': ".json_last_error_msg());
    }
    return $res;
}


/**
 * Functions to support graceful shutdown of daemons upon receiving a signal
 */
function sig_handler(int $signal, $siginfo = null)
{
    global $exitsignalled, $gracefulexitsignalled;

    logmsg(LOG_DEBUG, "Signal $signal received");

    switch ($signal) {
        case SIGHUP:
            $gracefulexitsignalled = true;
            // no break
        case SIGINT:   # Ctrl+C
        case SIGTERM:
            $exitsignalled = true;
    }
}


function initsignals()
{
    global $exitsignalled;

    $exitsignalled = false;

    if (! function_exists('pcntl_signal')) {
        logmsg(LOG_INFO, "Signal handling not available");
        return;
    }

    logmsg(LOG_DEBUG, "Installing signal handlers");

    // Install signal handler for TERMINATE, HANGUP and INTERRUPT
    // signals. The sleep() call will automatically return on
    // receiving a signal.
    pcntl_signal(SIGTERM, "sig_handler");
    pcntl_signal(SIGHUP, "sig_handler");
    pcntl_signal(SIGINT, "sig_handler");
}
