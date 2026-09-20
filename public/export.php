<?php
// 数据库配置
$host     = "localhost";   // 数据库地址
$user     = "xinayang";        // 用户名
$password = "mYMbSLAL7GicSF26";      // 密码
$dbname   = "xinayang"; // 数据库名
$table    = "mod_goods";    // 要导出的表名

// 连接数据库
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 设置编码，避免中文乱码
$conn->set_charset("utf8");

// 查询数据
$result = $conn->query("SELECT * FROM `$table`");

if (!$result) {
    die("查询失败: " . $conn->error);
}

// 设置导出头
header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename={$table}.csv");
header('Cache-Control: max-age=0');

// 打开输出流
$out = fopen('php://output', 'w');

// 写入 UTF-8 BOM，避免 Excel 中文乱码
fwrite($out, "\xEF\xBB\xBF");

// 写入表头
$fields = $result->fetch_fields();
$headers = [];
foreach ($fields as $field) {
    $headers[] = $field->name;
}
fputcsv($out, $headers);

// 写入数据
while ($row = $result->fetch_assoc()) {
    fputcsv($out, $row);
}

fclose($out);
$conn->close();
exit;
