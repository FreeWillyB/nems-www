<?php
  $functions_loaded=1;

  function ver($product='nems') {
    $arrContextOptions=array(
        "ssl"=>array(
          "verify_peer"=>false,
          "verify_peer_name"=>false,
        ),
    );
    switch ($product) {
      case 'nems':
        $nemsver = shell_exec('/usr/local/share/nems/nems-scripts/info.sh nemsver');
        return trim($nemsver); // version of NEMS
        break;
      case 'nems-branch':
        $nemsbranch = shell_exec('/usr/local/share/nems/nems-scripts/info.sh nemsbranch');
        return trim($nemsbranch);
        break;
      case 'nems-available': // obtained from our site each day via root cron
        $ver = file_get_contents('/var/www/html/inc/ver-available.txt');
        return trim($ver); // version of NEMS currently available on our site
        break;
      case 'nems-branch-avail':
        $ver = file_get_contents('/var/www/html/inc/ver-available.txt');
        $tmp = explode('.',$ver);
        $nems_branch_avail = $tmp[0] . '.' . $tmp[1];
        return trim($nems_branch_avail);
        break;
      case 'nagios': // /usr/sbin/nagios3 --version
        return '3.5.1'; // is this used anywhere?! If yes, need to fix this as it's completely wrong.
	break;
      case 'platform': // which platform is this for
        $platform->num = trim(shell_exec('/usr/local/share/nems/nems-scripts/info.sh platform'));
//        $platform = json_decode(file_get_contents('https://nemslinux.com/api/platform/' . $platform_num, false, stream_context_create($arrContextOptions)));
        $platform->name = trim(shell_exec('/usr/local/share/nems/nems-scripts/info.sh platform-name'));
        return $platform; // version of NEMS currently available on our site
	break;
    }
  }


  if (!function_exists('_getServerLoadLinuxData')) {
    function _getServerLoadLinuxData()
    {
        if (is_readable("/proc/stat"))
        {
            $stats = @file_get_contents("/proc/stat");

            if ($stats !== false)
            {
                // Remove double spaces to make it easier to extract values with explode()
                $stats = preg_replace("/[[:blank:]]+/", " ", $stats);

                // Separate lines
                $stats = str_replace(array("\r\n", "\n\r", "\r"), "\n", $stats);
                $stats = explode("\n", $stats);

                // Separate values and find line for main CPU load
                foreach ($stats as $statLine)
                {
                    $statLineData = explode(" ", trim($statLine));

                    // Found!
                    if
                    (
                        (count($statLineData) >= 5) &&
                        ($statLineData[0] == "cpu")
                    )
                    {
                        return array(
                            $statLineData[1],
                            $statLineData[2],
                            $statLineData[3],
                            $statLineData[4],
                        );
                    }
                }
            }
        }

        return null;
    }

    // Returns server load in percent (just number, without percent sign)
    function getServerLoad()
    {
        $load = null;

        if (stristr(PHP_OS, "win"))
        {
            $cmd = "wmic cpu get loadpercentage /all";
            @exec($cmd, $output);

            if ($output)
            {
                foreach ($output as $line)
                {
                    if ($line && preg_match("/^[0-9]+\$/", $line))
                    {
                        $load = $line;
                        break;
                    }
                }
            }
        }
        else
        {
            if (is_readable("/proc/stat"))
            {
                // Collect 2 samples - each with 1 second period
                // See: https://de.wikipedia.org/wiki/Load#Der_Load_Average_auf_Unix-Systemen
                $statData1 = _getServerLoadLinuxData();
                sleep(1);
                $statData2 = _getServerLoadLinuxData();

                if
                (
                    (!is_null($statData1)) &&
                    (!is_null($statData2))
                )
                {
                    // Get difference
                    $statData2[0] -= $statData1[0];
                    $statData2[1] -= $statData1[1];
                    $statData2[2] -= $statData1[2];
                    $statData2[3] -= $statData1[3];

                    // Sum up the 4 values for User, Nice, System and Idle and calculate
                    // the percentage of idle time (which is part of the 4 values!)
                    $cpuTime = $statData2[0] + $statData2[1] + $statData2[2] + $statData2[3];

                    // Invert percentage to get CPU time, not idle time
                    $load = 100 - ($statData2[3] * 100 / $cpuTime);
                }
            }
        }

        return $load;
    }

    //----------------------------


    function get_server_memory_usage(){

        $free = shell_exec('free');
        $free = (string)trim($free);
        $free_arr = explode("\n", $free);
        $mem = explode(" ", $free_arr[1]);
        $mem = array_filter($mem);
        $mem = array_merge($mem);
        $memory_usage = $mem[2]/$mem[1]*100;

        return $memory_usage;
    }
  }


  $self = new stdClass();
  if (isset($_SERVER['REQUEST_SCHEME'])) $self->protocol = $_SERVER['REQUEST_SCHEME']; else $self->protocol = 'http';
  if (isset($_SERVER['HTTP_HOST'])) $self->host = $_SERVER['HTTP_HOST']; else $self->host = 'nems.local';

  function checkConfEnabled($service) {
    $response = false;
    $conf = '/usr/local/share/nems/nems.conf';
    $tmp = file($conf);
    if (is_array($tmp)) {
      foreach ($tmp as $line) {
        $data = explode('=',$line);
        $confdata[$data[0]] = $data[1];
      }
      if (is_array($confdata) && isset($confdata['service.' . $service])) {
        if ($confdata['service.' . $service] == 0) {
          $response = false;
        } else {
          $response = true;
        }
      } else {
        $response = true; // it's true because it has not been set otherwise
      }
    }
    return($response);
  }

  function formatBytes($bytes, $precision = 2) {
      $units = array('B', 'KB', 'MB', 'GB', 'TB');
      $bytes = max($bytes, 0);
      $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
      $pow = min($pow, count($units) - 1);
      // Uncomment one of the following alternatives
      $bytes /= pow(1024, $pow);
      // $bytes /= (1 << (10 * $pow));
      return round($bytes, $precision) . $units[$pow];
  }

  function initialized() {
    $initialized = 0;
    $htpasswd = '/var/www/htpasswd';
    if (file_exists($htpasswd)) {
      $initialized = strlen(file_get_contents($htpasswd));
    }
    if ($initialized > 0) {
      return true;
    } else {
      return false;
    }
  }
?>
