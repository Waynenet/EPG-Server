<?php
// 检测是否为 AJAX 请求
if (!(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    http_response_code(403); // 返回403禁止访问
    exit('禁止直接访问');
}

// 检测 ffmpeg 是否安装
if (!shell_exec('which ffprobe')) {
    echo '<p>未检测到 ffmpeg 环境，请使用以下指令重新部署：
        <br>docker run -e ENABLE_FFMPEG=true -d --name php-epg -p 5678:80 --restart always taksss/php-epg:latest</p>';
    exit;
}

// 禁用 PHP 输出缓冲
ob_implicit_flush(true);
@ob_end_flush();

// 设置 header，防止浏览器缓存输出
header('X-Accel-Buffering: no');

// 文件路径
$channelsFile = 'data/live/channels.csv';
$channelsInfoFile = 'data/live/channels_info.csv';

// cleanMode 参数为 true 时，清除测速数据
if (isset($_GET['cleanMode']) && $_GET['cleanMode'] === 'true') {
    // 读取 channels_info.csv 文件
    if (!file_exists($channelsInfoFile)) die('channels_info.csv 文件不存在');
    $channelsInfo = array_map('str_getcsv', file($channelsInfoFile));
    $infoHeaders = array_shift($channelsInfo);
    $tagIndex = array_search('tag', $infoHeaders);
    $speedIndex = array_search('speed', $infoHeaders);

    if ($tagIndex === false || $speedIndex === false) die('channels_info.csv 文件缺少字段');

    // 读取 channels.csv 文件
    if (!file_exists($channelsFile)) die('channels.csv 文件不存在');
    $channels = array_map('str_getcsv', file($channelsFile));
    $headers = array_shift($channels);
    $disableIndex = array_search('disable', $headers);
    $modifiedIndex = array_search('modified', $headers);

    if ($disableIndex === false || $modifiedIndex === false) die('channels.csv 文件缺少字段');

    // 更新 channels.csv 中与 channels_info.csv 中 tag 匹配的记录
    foreach ($channels as &$channel) {
        foreach ($channelsInfo as $infoRow) {
            if ($infoRow[$speedIndex] === 'N/A' && 
                $infoRow[$tagIndex] === $channel[array_search('tag', $headers)]) {
                $channel[$disableIndex] = '0'; // 清除禁用标志
                $channel[$modifiedIndex] = '0'; // 清除修改标志
                break;
            }
        }
    }

    // 重新写回更新后的 channels.csv 文件
    array_unshift($channels, $headers);
    $fileHandle = fopen($channelsFile, 'w');
    foreach ($channels as $channel) {
        fputcsv($fileHandle, $channel);
    }
    fclose($fileHandle);

    // 仅清空 channels_info.csv 中的数据，保留表头
    $fileHandle = fopen($channelsInfoFile, 'w');
    fputcsv($fileHandle, $infoHeaders); // 写入表头
    fclose($fileHandle);

    echo "测速数据已清除。";
    exit;
}

// 读取 channels.csv 文件并解析
if (!file_exists($channelsFile)) die('channels.csv 文件不存在');
$channels = array_map('str_getcsv', file($channelsFile));

// 提取表头，定位 streamUrl 和 tag 索引
$headers = array_shift($channels);
$streamUrlIndex = array_search('streamUrl', $headers);
$tagIndex = array_search('tag', $headers);
$channelNameIndex = array_search('channelName', $headers);

// 确保必要字段存在
if ($streamUrlIndex === false || $tagIndex === false) {
    die('channels.csv 文件缺少必要字段');
}

// 确保 disable 和 modified 字段存在
foreach (['disable', 'modified'] as $field) {
    if (!in_array($field, $headers)) {
        $headers[] = $field;
    }
}

// 准备 channels_info.csv 数据
$infoHeaders = ['tag', 'resolution', 'speed'];
$infoData = [];

// 写入 channels_info.csv 文件
$fileHandle = fopen($channelsInfoFile, 'w');
fputcsv($fileHandle, $infoHeaders);

// 遍历频道并检测分辨率和速度
$total = count($channels);
foreach ($channels as $i => $channel) {
    $streamUrl = $channel[$streamUrlIndex];
    $streamUrl = strtok($streamUrl, '$'); // 处理带有 $ 的 URL
    $tag = $channel[$tagIndex];
    $channelName = $channelNameIndex !== false ? $channel[$channelNameIndex] : '未知频道';

    // 跳过空的 streamUrl
    if (empty($streamUrl)) continue;

    echo "(" . ($i + 1) . "/{$total}): {$channelName} - {$streamUrl}<br>";

    // 使用 ffprobe 直接测量访问速度
    $startTime = microtime(true);
    $cmd = "ffprobe -rw_timeout 2000000 -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 \"{$streamUrl}\"";
    exec($cmd, $output, $returnVar);
    $duration = microtime(true) - $startTime;

    // 初始化字段索引
    $disableIndex = array_search('disable', $headers);
    $modifiedIndex = array_search('modified', $headers);

    // 如果 ffprobe 执行失败，访问速度设为 "N/A"，并设置 disable 和 modified 为 1
    if ($returnVar !== 0) {
        $speed = 'N/A';
        $resolution = 'N/A';
        $channel[$disableIndex] = '1';
        $channel[$modifiedIndex] = '1';
        echo '<strong><span style="color: red;">无法获取流信息，已标记为停用</span></strong><br><br>';
    } else {
        $speed = round($duration * 1000, 0);
        $resolution = 'N/A';
        $channel[$disableIndex] = '0'; // 访问成功，清除禁用标志
        $channel[$modifiedIndex] = '0';

        // 解析 ffprobe 输出的分辨率
        foreach ($output as $line) {
            if (strpos($line, ',') !== false) {
                list($width, $height) = array_pad(explode(',', $line), 2, '未知');
                $resolution = "{$width}x{$height}";
            }
        }

        echo "分辨率: {$resolution}, 访问速度: {$speed} ms<br><br>";
    }

    // 保存到 channels_info.csv 数据
    $infoRow = [$tag, $resolution, $speed];
    fputcsv($fileHandle, $infoRow);

    // 更新 channels
    $channels[$i] = $channel;
}
fclose($fileHandle);
echo "检测完成，已生成 channels_info.csv 文件。<br>";

// 重新写回 channels.csv 文件
array_unshift($channels, $headers);
$fileHandle = fopen($channelsFile, 'w');
foreach ($channels as $channel) {
    fputcsv($fileHandle, $channel);
}
fclose($fileHandle);

?>
