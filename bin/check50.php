<?php

    /**
     * @author David J. Malan <malan@harvard.edu>
     * @link https://manual.cs50.net/Check
     * @package check50
     * @version 1.0
     *
     * Creative Commons Attribution-Noncommerical 3.0 Unported
     * http://creativecommons.org/licenses/by-nc/3.0/
     */

    // constants
    define("VERSION", "1.0");
    $TYPES = array(
     "*.c",
     "*.css",
     "*.h",
     "*.htm",
     "*.html",
     "*.js",
     "*.php",
     "Makefile"
    );

    // report all errors
    error_reporting(E_ALL);
    ini_set("display_errors", true);

    // explain usage
    if (in_array("-h", $argv) || in_array("--help", $argv))
    {
        echo "Usage: check50 /path/to/check\n";
        exit(1);
    }
    else if (in_array("-v", $argv) || in_array("--version", $argv))
    {
        echo VERSION . "\n";
        exit(1);
    }

    // ensure proper usage
    if ($argc < 2)
    {
        echo "Usage: check50 /path/to/check\n";
        exit(1);
    }

    // check for debugging mode
    $verbose = in_array("-d", $argv) || in_array("-D", $argv);
    $veryverbose = in_array("-D", $argv);

    // check STDIN else command line for inputs
    if ($argv[1] == "--")
    {
        echo "Taking inputs from STDIN, one per line...  Hit Ctrl-D when done else Ctrl-C to cancel.\n";
        $patterns = file("php://stdin", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    else
    {
        $patterns = array();
        for ($i = 1; $i < $argc; $i++)
        {
            if ($argv[$i][0] == "-")
                break;
            array_push($patterns, $argv[$i]);
        }
    }

    // glob patterns lest shell (e.g., Windows) not have done so
    $inputs = array();
    foreach ($patterns as $pattern)
    {
        // search for paths that match pattern
        $paths = glob($pattern, GLOB_BRACE);

        // sort paths that match pattern
        $inputs = array_merge($inputs, $paths);
    }

    // files to render
    $files = array();

    // directories into which to descend
    $directories = array();

    // parse command line for files and directories
    foreach ($inputs as $input)
    {
        // ensure file (or directory) exists
        if (!file_exists($input))
            die("File does not exist: {$input}\n");

        // ensure file (or directory) is readable
        if (!is_readable($input))
            die("File cannot be read: {$input}\n");

        // push file or directory onto array
        if (is_dir($input))
            array_push($directories, $input);
        else
            array_push($files, $input);
    }

    // descend into directories
    while (count($directories) > 0)
    {
        // pop directory
        $directory = array_shift($directories);

        // skip dotdirectories
        if (substr(basename($directory), 0, 1) == ".")
        {
            if ($verbose)
                echo "Skipping $directory (because it's hidden)...\n";
            continue;
        }

        // sort directory's children
        if (($children = @scandir($directory)) === false)
        {
            if ($verbose)
                echo "Skipping $directory (because it's unreadable)...\n";
            continue;
        }

        // iterate over directory's children
        foreach ($children as $child)
        {
            // ignore . and ..
            if ($child == "." || $child == "..")
                continue;
    
            // prepare child's path
            $path = rtrim($directory, "/") . DIRECTORY_SEPARATOR . $child;

            // push child's path onto array
            if (is_dir($path))
                array_push($directories, $path);
            else
                array_push($files, $path);
        }
    }

    // prepare fields
    $fields = array();

    // TEMP
    $fields["course"] = "cs50";
    $fields["program"] = "speller";
    $fields["term"] = "fall";
    $fields["year"] = "2011";
    $fields["version"] = VERSION;

    // sort files
    natcasesort($files);

    // counter for uploadable files
    $counter = 0;

    // determine which files to upload
    foreach ($files as $file)
    {
        // skip dotfiles
        if (substr(basename($file), 0, 1) == ".")
        {
            if ($verbose)
                echo "Skipping $file (because it's hidden)...\n";
            continue;
        }

        // skip binary files
        if (strpos(file_get_contents($file), "\x00") !== false)
        {
            if ($verbose)
                echo "Skipping $file (because it's binary)...\n";
            continue;
        }

        // skip unsupported types
        $supported = false;
        foreach ($TYPES as $type)
        {
            // escape . as \.
            $type = str_replace(".", "\.", $type);

            // convert * to .+
            $type = str_replace("*", ".+", $type);

            if (preg_match("/^$type$/i", basename($file)))
            {
                $supported = true;
                break;
            }
        }
        if (!$supported)
        {
            if ($verbose)
                echo "Skipping $file (because it's not a supported type)...\n";
            continue;
        }

        // report progress
        echo "Uploading $file...\n";

        // add file to POST
        $fields["file_{$counter}"] = "@{$file}";
        $counter++;
    }

    // ensure files were uploaded
    if ($counter == 0)
    {
        echo "Nothing to check (because no files were uploaded).\n";
        exit(1);
    }

    // POST files
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 32);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, "https://check.cs50.net/");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);

    // check response
    if ($info["http_code"] != 200)
        die("CS50 Check returned an error.  Email sysadmins@cs50.net to inquire.\n");

    // decode response
    $o = json_decode($response);
    if (is_null($o))
        die("CS50 Check returned an error.  Email sysadmins@cs50.net to inquire.\n");

    // check for URL
    if (isset($o->url))
    {
        // operating system name
        $uname = php_uname("s");

        // Mac OS
        if ($uname == "Darwin" && (boolean) getenv("DISPLAY"))
            system("open " . escapeshellarg($o->url) . " > /dev/null 2>&1");

        // Linux
        else if ($uname == "Linux" && (boolean) getenv("DISPLAY"))
            system("xdg-open " . escapeshellarg($o->url) . " > /dev/null 2>&1");

        // Windows
        else if ($uname == "Windows")
            system("start " . escapeshellarg($o->url));
    }

    // display message, if any
    if (isset($o->message))
        echo "{$o->message}\n";

    // that's all folks
    exit(0);

?>
