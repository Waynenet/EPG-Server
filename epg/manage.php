<?php
/**
 * @file manage.php
 * @brief 管理页面部分
 * 
 * 管理界面脚本，用于处理会话管理、密码更改、登录验证、配置更新、更新日志展示等功能。
 * 
 * 作者: Tak
 * GitHub: https://github.com/TakcC/PHP-EPG-Docker-Server
 */

// 引入公共脚本
require_once 'public.php';

session_start();

// 设置会话变量，表明用户可以访问 phpliteadmin.php
$_SESSION['can_access_phpliteadmin'] = true;

// 读取 configUpdated 状态
$configUpdated = isset($_SESSION['configUpdated']) && $_SESSION['configUpdated'];
if ($configUpdated) {
    unset($_SESSION['configUpdated']);
}

// 处理密码更新请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $oldPassword = $_POST['old_password'];
    $newPassword = $_POST['new_password'];

    // 验证原密码是否正确
    if ($oldPassword === $Config['manage_password']) {
        // 原密码正确，更新配置中的密码
        $newConfig = $Config;
        $newConfig['manage_password'] = $newPassword;

        // 将新配置写回 config.php
        file_put_contents('config.php', '<?php' . "\n\n" . '$Config = ' . var_export($newConfig, true) . ';' . "\n\n" . '?>');

        // 设置密码更改成功的标志变量
        $passwordChanged = true;
    } else {
        $passwordChangeError = "原密码错误";
    }
}

// 检查是否提交登录表单
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $password = $_POST['password'];

    // 验证密码
    if ($password === $Config['manage_password']) {
        // 密码正确，设置会话变量
        $_SESSION['loggedin'] = true;
    } else {
        $error = "密码错误";
    }
}

// 处理密码更改成功后的提示
$passwordChangedMessage = isset($passwordChanged) ? "<p style='color:green;'>密码已更改</p>" : '';
$passwordChangeErrorMessage = isset($passwordChangeError) ? "<p style='color:red;'>$passwordChangeError</p>" : '';

// 检查是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // 显示登录表单
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>登录</title>
        <link rel="stylesheet" type="text/css" href="css/login.css">
    </head>
    <body>
        <div class="container">
            <h2>登录</h2>
            <form method="POST">
                <label for="password">管理密码:</label><br><br>
                <input type="password" id="password" name="password"><br><br>
                <input type="hidden" name="login" value="1">
                <input type="submit" value="登录">
            </form>
            <div class="button-container">
                <button type="button" onclick="showChangePasswordForm()">更改密码</button>
            </div>
            <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
            <?php echo $passwordChangedMessage; ?>
            <?php echo $passwordChangeErrorMessage; ?>
        </div>
        <!-- 底部显示 -->
        <div class="footer">
            <a href="https://github.com/TakcC/PHP-EPG-Docker-Server" style="color: #888; text-decoration: none;">https://github.com/TakcC/PHP-EPG-Docker-Server</a>
        </div>
    
    <!-- 修改密码模态框 -->
    <div id="changePasswordModal" class="modal">
        <div class="passwd-modal-content">
            <span class="close-password">&times;</span>
            <h2>更改登录密码</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="old_password">原密码</label>
                    <input type="password" id="old_password" name="old_password">
                </div>
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input type="password" id="new_password" name="new_password">
                </div>
                <input type="hidden" name="change_password" value="1">
                <input type="submit" value="更改密码">
            </form>
        </div>
    </div>
    <script>
        function showChangePasswordForm() {
            var changePasswordModal = document.getElementById("changePasswordModal");
            var changePasswordSpan = document.getElementsByClassName("close-password")[0];

            changePasswordModal.style.display = "block";

            changePasswordSpan.onclick = function() {
                changePasswordModal.style.display = "none";
            }

            window.onclick = function(event) {
                if (event.target == changePasswordModal) {
                    changePasswordModal.style.display = "none";
                }
            }
        }
    </script>
    </body>
    </html>
    <?php
    exit;
}

// 检查是否提交配置表单
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {

    // 获取表单数据并去除每个 URL 末尾的换行符
    $xml_urls = array_map('trim', explode("\n", trim($_POST['xml_urls'])));
    $days_to_keep = intval($_POST['days_to_keep']);
    $gen_xml = isset($_POST['gen_xml']) ? intval($_POST['gen_xml']) : $Config['gen_xml'];
    $include_future_only = isset($_POST['include_future_only']) ? intval($_POST['include_future_only']) : $Config['include_future_only'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    
    // 处理间隔时间
    $interval_hour = intval($_POST['interval_hour']);
    $interval_minute = intval($_POST['interval_minute']);
    $interval_time = $interval_hour * 3600 + $interval_minute * 60;

    // 处理频道替换
    $channel_replacements = array_map('trim', explode("\n", trim($_POST['channel_replacements'])));

    // 处理频道映射
    $channel_mappings_lines = array_map('trim', explode("\n", trim($_POST['channel_mappings'])));
    $channel_mappings = [];
    foreach ($channel_mappings_lines as $line) {
        list($search, $replace) = explode('=>', $line);
        $channel_mappings[trim(str_replace("，", ",", $search))] = trim($replace);
    }

    // 获取旧的配置
    $oldConfig = $Config;

    // 更新配置
    $newConfig = [
        'xml_urls' => $xml_urls,
        'days_to_keep' => $days_to_keep,
        'gen_xml' => $gen_xml,
        'include_future_only' => $include_future_only,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'interval_time' => $interval_time,
        'manage_password' => $Config['manage_password'], // 保留密码
        'channel_replacements' => $channel_replacements,
        'channel_mappings' => $channel_mappings
    ];

    // 将新配置写回config.php
    file_put_contents('config.php', '<?php' . "\n\n" . '$Config = ' . var_export($newConfig, true) . ';' . "\n\n" . '?>');

    // 设置标志变量以显示弹窗
    $_SESSION['configUpdated'] = true;

    // 重新加载配置以确保页面显示更新的数据
    require 'config.php';

    // 重新启动 cron.php ，设置新的定时任务
    if ($oldConfig['start_time'] !== $start_time || $oldConfig['end_time'] !== $end_time || $oldConfig['interval_time'] !== $interval_time) {
        exec('php cron.php > /dev/null 2>/dev/null &');
    }
    header('Location: manage.php');
    exit;
} else {
}

// 连接数据库并获取日志表中的数据
$logData = [];
$cronLogData = [];
$channels = [];
try {
    // 返回更新日志数据
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['get_update_logs'])) {
        $stmt = $db->query("SELECT * FROM update_log");
        $logData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($logData);
        exit;
    }

    // 返回定时任务日志数据
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['get_cron_logs'])) {
        $stmt = $db->query("SELECT * FROM cron_log");
        $cronLogData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($cronLogData);
        exit;
    }

    // 返回所有频道数据和频道数量
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['get_channel'])) {
        $stmt = $db->query("SELECT DISTINCT channel FROM epg_data ORDER BY channel ASC");
        $channels = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $channelCount = count($channels);
        $response = [
            'channels' => $channels,
            'count' => $channelCount
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // 返回待保存频道列表数据
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['get_gen_list'])) {        
        $stmt = $db->query("SELECT channel FROM gen_list");
        $genList = $stmt->fetchAll(PDO::FETCH_COLUMN);
        header('Content-Type: application/json');
        echo json_encode($genList);
        exit;
    }

    // 将频道数据写入数据库
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['set_gen_list'])) {
        $data = json_decode(file_get_contents("php://input"), true)['data'] ?? '';
        try {
            // 启动事务
            $db->beginTransaction();
            // 清空表中的数据
            $db->exec("DELETE FROM gen_list");
            // 插入新数据
            $lines = array_filter(array_map('trim', explode("\n", $data)));
            $stmt = $db->prepare("INSERT INTO gen_list (channel) VALUES (:channel)");
            foreach ($lines as $line) {
                $stmt->bindValue(':channel', $line, PDO::PARAM_STR);
                $stmt->execute(); // 执行插入操作
            }
            // 提交事务
            $db->commit();
            echo 'success';
        } catch (PDOException $e) {
            // 回滚事务
            $db->rollBack();
            echo "数据库操作失败: " . $e->getMessage();
        }
        exit;
    }

    // 返回处理后的频道列表
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['get_clean_list'])) {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
    
        if (isset($data['channels'])) {
            $convertedChannels = explode("\n", implode("\n", $data['channels']));
            $cleanedChannels = array_map('cleanChannelName', $convertedChannels);
            $uniqueChannels = array_unique($cleanedChannels);
            echo json_encode(array_values($uniqueChannels)); // 确保返回的是数组
            exit;
        }
    }

} catch (Exception $e) {
    // 处理数据库连接错误
    $logData = [];
    $cronLogData = [];
    $channels = [];
}

// 生成配置管理表单
?>
<!DOCTYPE html>
<html>
<head>
    <title>管理配置</title>
    <link rel="stylesheet" type="text/css" href="css/manage.css">
</head>
<body>
<div class="container">
    <h2>管理配置</h2>
    <form method="POST" id="settingsForm">

        <label for="xml_urls">EPG源地址（支持 xml 跟 .xml.gz 格式， # 为注释）</label><br><br>
        <textarea placeholder="一行一个，地址前面加 # 可以临时停用，后面加 # 可以备注。快捷键： Ctrl+/  。" id="xml_urls" name="xml_urls" rows="5" required><?php echo implode("\n", array_map('trim', $Config['xml_urls'])); ?></textarea><br><br>

        <div class="form-row">
            <label for="days_to_keep" class="label-days-to-keep">数据保存天数</label>
            <label for="start_time" class="label-time custom-margin1">【定时任务】： 开始时间</label>
            <label for="end_time" class="label-time2 custom-margin2">结束时间</label>
            <label for="interval_time" class="label-time3 custom-margin3">间隔周期 (选0小时0分钟取消)</label>
        </div>

        <div class="form-row">
            <select id="days_to_keep" name="days_to_keep" required>
                <?php for ($i = 1; $i <= 30; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $Config['days_to_keep'] == $i ? 'selected' : ''; ?>>
                        <?php echo $i; ?>
                    </option>
                <?php endfor; ?>
            </select>
            <input type="time" id="start_time" name="start_time" value="<?php echo $Config['start_time']; ?>" required>
            <input type="time" id="end_time" name="end_time" value="<?php echo $Config['end_time']; ?>" required>
            
            <!-- Interval Time Controls -->
            <select id="interval_hour" name="interval_hour" required>
                <?php for ($h = 0; $h < 24; $h++): ?>
                    <option value="<?php echo $h; ?>" <?php echo intval($Config['interval_time']) / 3600 == $h ? 'selected' : ''; ?>>
                        <?php echo $h; ?>
                    </option>
                <?php endfor; ?>
            </select> 小时
            <select id="interval_minute" name="interval_minute" required>
                <?php for ($m = 0; $m < 60; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo (intval($Config['interval_time']) % 3600) / 60 == $m ? 'selected' : ''; ?>>
                        <?php echo $m; ?>
                    </option>
                <?php endfor; ?>
            </select> 分钟
        </div><br>
        
        <div class="flex-container">
            <div class="flex-item">
                <label for="channel_replacements">频道忽略字符串<br>（匹配时按先后顺序清除， \s 表示空格）</label><br><br>
                <textarea id="channel_replacements" name="channel_replacements" rows="5" required><?php echo implode("\n", array_map('trim', $Config['channel_replacements'])); ?></textarea><br><br>
            </div>
            <div class="flex-item">
                <label for="channel_mappings">
                    频道映射<br>[自定1, 自定2, ...] => 
                    <span id="dbChannelName" onclick="showModal('channel')" style="color: blue; text-decoration: underline; cursor: pointer;">数据库频道名</span>
                    (正则表达式regex:)
                </label><br><br>
                <textarea id="channel_mappings" name="channel_mappings" rows="5" required><?php echo implode("\n", array_map(function($search, $replace) { return $search . ' => ' . $replace; }, array_keys($Config['channel_mappings']), $Config['channel_mappings'])); ?></textarea><br><br>
            </div>
        </div>

        <div class="tooltip">
            <input id="updateConfig" type="submit" name="update" value="更新配置">
            <span class="tooltiptext">快捷键：Ctrl+S</span>
        </div><br><br>
        <div class="button-container">
            <a href="update.php" target="_blank">更新数据库</a>
            <a href="phpliteadmin.php" target="_blank">管理数据库</a>
            <button type="button" onclick="showModal('cron')">定时任务日志</button>
            <button type="button" onclick="showModal('update')">数据库更新日志</button>
            <button type="button" onclick="showModal('moresetting')">更多设置</button>
        </div>
    </form>
</div>

<!-- 底部显示 -->
<div class="footer">
    <a href="https://github.com/TakcC/PHP-EPG-Docker-Server" style="color: #888; text-decoration: none;">https://github.com/TakcC/PHP-EPG-Docker-Server</a>
</div>

<!-- 配置更新模态框 -->
<div id="myModal" class="modal">
    <div class="modal-content config-modal-content">
        <span class="close">&times;</span>
        <p id="modalMessage"></p>
    </div>
</div>

<!-- 更新日志模态框 -->
<div id="updatelogModal" class="modal">
    <div class="modal-content update-log-modal-content">
        <span class="close">&times;</span>
        <h2>数据库更新日志</h2>
        <div class="table-container" id="log-table-container">
            <table id="logTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>日期</th>
                        <th>描述</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- 数据由 JavaScript 动态生成 -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 定时任务日志模态框 -->
<div id="cronlogModal" class="modal">
    <div class="modal-content cron-log-modal-content">
        <span class="close">&times;</span>
        <h2>定时任务日志</h2>
        <textarea id="cronLogContent" readonly style="width: 100%; height: 420px;"></textarea>
    </div>
</div>

<!-- 频道列表模态框 -->
<div id="channelModal" class="modal">
    <div class="modal-content channel-modal-content">
        <span class="close">&times;</span>
        <h2 id="channelModalTitle">频道列表</h2>
        <input type="text" id="searchInput" placeholder="搜索频道名..." onkeyup="filterChannels()">
        <textarea id="channelList" readonly style="width: 100%; height: 370px;"></textarea>
    </div>
</div>

<!-- 更多设置模态框 -->
<div id="moreSettingModal" class="modal">
    <div class="modal-content more-setting-modal-content">
        <span class="close">&times;</span>
        <h2>更多设置</h2>
        <label for="gen_xml">生成 .xml.gz 文件：</label>
        <select id="gen_xml" name="gen_xml" required>
            <option value="1" <?php if ($Config['gen_xml'] == 1) echo 'selected'; ?>>是</option>
            <option value="0" <?php if ($Config['gen_xml'] == 0) echo 'selected'; ?>>否</option>
        </select>
        <label for="include_future_only">生成方式：</label>
        <select id="include_future_only" name="include_future_only" required>
            <option value="1" <?php if ($Config['include_future_only'] == 1) echo 'selected'; ?>>仅预告数据</option>
            <option value="0" <?php if ($Config['include_future_only'] == 0) echo 'selected'; ?>>所有数据</option>
        </select>
        <br><br>
        <label for="gen_list_text"">仅生成以下频道的节目单：</label>
        <span id="dbChannelName" onclick="parseSource()">
            （可粘贴 txt、m3u 直播源并<span style="color: blue; text-decoration: underline; cursor: pointer;">点击解析</span>）
        </span><br><br>
        <textarea id="gen_list_text" style="width: 100%; height: 316px;"></textarea><br><br>
        <button id="saveConfig" type="button" onclick="saveAndUpdateConfig();">保存配置</button>
    </div>
</div>

<script>
    let genListLoaded = false; // 用于跟踪数据是否已加载

    // Ctrl+S 保存设置
    document.addEventListener("keydown", function(event) {
        if (event.ctrlKey && event.key === "s") {
            event.preventDefault(); // 阻止默认行为，如保存页面
            saveAndUpdateConfig();
        }
    });

    // Ctrl+/ 设置（取消）注释
    document.getElementById('xml_urls').addEventListener('keydown', function(event) {
    if (event.ctrlKey && event.key === '/') {
            event.preventDefault();

            const textarea = this;
            const { selectionStart, selectionEnd, value } = textarea;
            const lines = value.split('\n');

            // 计算当前选中的行
            const startLine = value.slice(0, selectionStart).split('\n').length - 1;
            const endLine = value.slice(0, selectionEnd).split('\n').length - 1;

            // 判断选中的行是否都已注释
            const allCommented = lines.slice(startLine, endLine + 1).every(line => line.trim().startsWith('#'));
            const newLines = lines.map((line, index) => {
                if (index >= startLine && index <= endLine) {
                    return allCommented ? line.replace(/^#\s*/, '') : '# ' + line;
                }
                return line;
            });

            // 更新 textarea 的内容
            textarea.value = newLines.join('\n');

            // 检查光标开始位置是否在行首
            const startLineStartIndex = value.lastIndexOf('\n', selectionStart - 1) + 1;
            const isStartInLineStart = (selectionStart - startLineStartIndex < 2);

            // 检查光标结束位置是否在行首
            const endLineStartIndex = value.lastIndexOf('\n', selectionEnd - 1) + 1;
            const isEndInLineStart = (selectionEnd - endLineStartIndex < 2);

            // 计算光标新的开始位置
            const newSelectionStart = isStartInLineStart 
                ? startLineStartIndex
                : selectionStart + newLines[startLine].length - lines[startLine].length;

            // 计算光标新的结束位置
            const lengthDiff = newLines.join('').length - lines.join('').length;
            const endLineDiff = newLines[endLine].length - lines[endLine].length;
            const newSelectionEnd = isEndInLineStart
                ? (endLineDiff > 0 ? endLineStartIndex + lengthDiff : endLineStartIndex + lengthDiff - endLineDiff)
                : selectionEnd + lengthDiff;

            // 恢复光标位置
            textarea.setSelectionRange(newSelectionStart, newSelectionEnd);
        }
    });

    function formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}小时${minutes}分钟`;
    }

    var configUpdated = <?php echo json_encode($configUpdated); ?>;
    var intervalTime = "<?php echo $Config['interval_time']; ?>";
    var startTime = "<?php echo $Config['start_time']; ?>";
    var endTime = "<?php echo $Config['end_time']; ?>";

    if (configUpdated) {
        var modal = document.getElementById("myModal");
        var span = document.getElementsByClassName("close")[0];
        var modalMessage = document.getElementById("modalMessage");

        if (intervalTime === "0") {
            modalMessage.innerHTML = "配置已更新<br><br>已取消定时任务";
        } else {
            modalMessage.innerHTML = `配置已更新<br><br>已设置定时任务<br>开始时间：${startTime}<br>结束时间：${endTime}<br>间隔周期：${formatTime(intervalTime)}`;
        }

        modal.style.display = "block";

        span.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        $configUpdated = false;
    }

    function showModal(type) {
        var modal, logSpan, logContent;

        switch (type) {
        case 'update':
            modal = document.getElementById("updatelogModal");
            logSpan = document.getElementsByClassName("close")[1];
            fetchLogs('<?php echo $_SERVER['PHP_SELF']; ?>?get_update_logs=true', updateLogTable);
            break;
        case 'cron':
            modal = document.getElementById("cronlogModal");
            logSpan = document.getElementsByClassName("close")[2];
            fetchLogs('<?php echo $_SERVER['PHP_SELF']; ?>?get_cron_logs=true', updateCronLogContent);
            break;
        case 'channel':
            modal = document.getElementById("channelModal");
            logSpan = document.getElementsByClassName("close")[3];
            fetchLogs('<?php echo $_SERVER['PHP_SELF']; ?>?get_channel=true', updateChannelList);
            document.getElementById('searchInput').value = ""; // 清空搜索框
            break;
        case 'moresetting':
            modal = document.getElementById("moreSettingModal");
            logSpan = document.getElementsByClassName("close")[4];            
            fetchLogs('<?php echo $_SERVER['PHP_SELF']; ?>?get_gen_list=true', updateGenList);
            genListLoaded = true; // 数据已加载
            break;
        default:
            console.error('Unknown type:', type);
            break;
        }

        modal.style.display = "block";

        logSpan.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    }

    function fetchLogs(endpoint, callback) {
        fetch(endpoint)
            .then(response => response.json())
            .then(data => callback(data))
            .catch(error => {
                console.error('Error fetching log:', error);
                callback([]);
            });
    }

    function updateLogTable(logData) {
        var logTableBody = document.querySelector("#logTable tbody");
        logTableBody.innerHTML = '';

        logData.forEach(log => {
            var row = document.createElement("tr");
            row.innerHTML = `
                <td>${log.id}</td>
                <td>${new Date(log.timestamp).toLocaleString()}</td>
                <td>${log.log_message}</td>
            `;
            logTableBody.appendChild(row);
        });
        var logTableContainer = document.getElementById("log-table-container");
        logTableContainer.scrollTop = logTableContainer.scrollHeight;
    }

    function updateCronLogContent(logData) {
        var logContent = document.getElementById("cronLogContent");
        logContent.value = logData.map(log => `[${new Date(log.timestamp).toLocaleDateString('zh-CN', { month: '2-digit', day: '2-digit' })} ${new Date(log.timestamp).toLocaleTimeString()}] ${log.log_message}`).join('\n');
        logContent.scrollTop = logContent.scrollHeight;
    }

    function updateChannelList(data) {
        const channelList = document.getElementById('channelList');
        const channelTitle = document.getElementById('channelModalTitle');
        channelList.dataset.allChannels = data.channels.join('\n'); // 保存所有频道数据
        channelList.value = channelList.dataset.allChannels;
        channelTitle.innerHTML = `频道列表<span style="font-size: 18px;">（总数：${data.count}）</span>`; // 更新频道总数
    }

    function updateGenList(genData) {
        const gen_list_text = document.getElementById('gen_list_text');
        gen_list_text.value = genData.join('\n');
    }    

    function filterChannels() {
        var input = document.getElementById('searchInput').value.toLowerCase();
        var channelList = document.getElementById('channelList');
        var allChannels = channelList.dataset.allChannels.split('\n');
        var filteredChannels = allChannels.filter(channel => channel.toLowerCase().includes(input));
        channelList.value = filteredChannels.join('\n');
    }

    // 解析 txt、m3u 直播源，并生成频道列表
    function parseSource() {
        const textarea = document.getElementById('gen_list_text');
        const text = textarea.value;
        const channels = new Set();

        if (text.includes('#EXTM3U')) {
            if (text.includes('tvg-name')) {
                text.replace(/tvg-name="([^"]+)"/g, (_, name) => channels.add(name.trim()));
            } else {
                text.replace(/#EXTINF:[^,]*,([^,\n]+)/g, (_, name) => channels.add(name.trim()));
            }
        } else {
            text.split('\n').forEach(line => {
                if (line && !line.includes('#genre#')) {
                    channels.add(line.split(',')[0].trim());
                }
            });
        }

        // 发送解析后的频道数据到服务器
        fetch('<?php echo $_SERVER['PHP_SELF']; ?>?get_clean_list=true', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ channels: Array.from(channels) })
        })
        .then(response => response.json())
        .then(data => {
            // 更新 textarea 显示处理后的频道数据
            textarea.value = data.join('\n');
        })
        .catch(error => console.error('Error:', error));
    }

    // 保存数据并更新配置
    function saveAndUpdateConfig() {
        if (!genListLoaded) {
            document.getElementById('updateConfig').click();
            return;
        }

        const textAreaContent = document.getElementById('gen_list_text').value;

        fetch('<?php echo $_SERVER['PHP_SELF']; ?>?set_gen_list=true', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ data: textAreaContent })
        })
        .then(response => response.text())
        .then(responseText => {
            if (responseText.trim() === 'success') {
                document.getElementById('updateConfig').click();
            } else {
                console.error('服务器响应错误:', responseText);
            }
        })
        .catch(error => {
            console.error('请求失败:', error);
        });
    }

    // 在提交表单时，将更多设置中的数据包括在表单数据中
    document.getElementById('settingsForm').addEventListener('submit', function() {
        const fields = ['gen_xml', 'include_future_only'];
        fields.forEach(function(field) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = field;
            hiddenInput.value = document.getElementById(field).value;
            this.appendChild(hiddenInput);
        }, this);
    });
    
</script>
</body>
</html>
