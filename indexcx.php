<?php
/**
 * 首页文件路径查找工具 - 优化版
 * 专门用于批量查找网站中的真实首页文件并输出清晰路径
 */

class HomepagePathFinder {
    private $searchDir;
    private $foundFiles = [];
    private $excludedDirs = ['vendor', 'node_modules', '.git', 'cache', 'log', 'tmp'];
    
    public function __construct($dir = '.') {
        $this->searchDir = realpath($dir);
        echo "=== 首页文件路径查找工具 ===\n";
        echo "搜索目录: " . $this->searchDir . "\n";
        echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";
    }
    
    /**
     * 查找首页文件并输出路径
     */
    public function findAndOutputHomepages() {
        $this->scanForHomepages($this->searchDir);
        
        if (empty($this->foundFiles)) {
            echo "\n❌ 未找到可能的首页文件。\n";
            return;
        }
        
        // 按分数排序
        usort($this->foundFiles, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        $this->outputResults();
        $this->generateSummary();
    }
    
    /**
     * 扫描查找首页文件
     */
    private function scanForHomepages($dir, $depth = 0) {
        if ($depth > 10) return; // 防止无限递归
        
        try {
            $items = @scandir($dir);
            if (!$items) return;
            
            foreach ($items as $item) {
                if ($item == '.' || $item == '..') continue;
                
                $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
                
                // 跳过排除的目录
                if (is_dir($fullPath)) {
                    if (in_array($item, $this->excludedDirs)) {
                        continue;
                    }
                    $this->scanForHomepages($fullPath, $depth + 1);
                    continue;
                }
                
                // 只检查PHP文件
                if (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                    $this->analyzeFile($fullPath, $item);
                }
            }
        } catch (Exception $e) {
            // 跳过无权限访问的目录
        }
    }
    
    /**
     * 分析文件是否是首页
     */
    private function analyzeFile($filePath, $fileName) {
        $content = @file_get_contents($filePath);
        if (!$content) return;
        
        $fileSize = filesize($filePath);
        $relativePath = str_replace($this->searchDir, '.', $filePath);
        
        $score = 0;
        $reasons = [];
        
        // 首页特征检测
        $features = [
            // 高权重特征
            ['pattern' => '/include.*[\'"]head\.php[\'"]/', 'weight' => 30, 'desc' => '包含head.php'],
            ['pattern' => '/\$DB->(query|getRow|getColumn|getAll)/', 'weight' => 25, 'desc' => '使用DB类数据库操作'],
            ['pattern' => '/SELECT.*FROM.*pre_/', 'weight' => 25, 'desc' => '查询pre_前缀表'],
            ['pattern' => '/pre_class|pre_view|pre_article/', 'weight' => 20, 'desc' => '使用特定数据表'],
            
            // 中权重特征
            ['pattern' => '/\$_GET.*page.*\d+/', 'weight' => 15, 'desc' => '分页逻辑'],
            ['pattern' => '/echo.*<html|<\?php.*\?>\s*<html/', 'weight' => 15, 'desc' => '输出HTML文档'],
            ['pattern' => '/content-mini|block-content|excerpt/', 'weight' => 12, 'desc' => '使用特定CSS类'],
            ['pattern' => '/carousel.*slide|content-wrap/', 'weight' => 10, 'desc' => '包含轮播或内容区域'],
            
            // 文件名特征
            ['pattern' => '/^index\.php$/i', 'weight' => 20, 'desc' => '标准首页文件名'],
            ['pattern' => '/^home\.php$/i', 'weight' => 15, 'desc' => 'Home文件名'],
            ['pattern' => '/^main\.php$/i', 'weight' => 12, 'desc' => 'Main文件名'],
            ['pattern' => '/^default\.php$/i', 'weight' => 10, 'desc' => 'Default文件名'],
        ];
        
        // 文件大小评分
        if ($fileSize > 2048) {
            $score += 15;
            $reasons[] = "文件大小合适({$fileSize}字节)";
        } elseif ($fileSize < 200) {
            $score -= 10;
            $reasons[] = "文件过小({$fileSize}字节)";
        }
        
        // 检查特征模式
        foreach ($features as $feature) {
            if (preg_match($feature['pattern'], $content) || 
                preg_match($feature['pattern'], $fileName)) {
                $score += $feature['weight'];
                $reasons[] = $feature['desc'];
            }
        }
        
        // 如果分数足够高，记录文件
        if ($score >= 25) {
            $this->foundFiles[] = [
                'path' => $relativePath,
                'full_path' => $filePath,
                'score' => $score,
                'size' => $fileSize,
                'reasons' => $reasons
            ];
            
            echo "✅ 发现候选: {$relativePath} (评分: {$score})\n";
        }
    }
    
    /**
     * 输出查找结果
     */
    private function outputResults() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "🎯 首页文件查找结果\n";
        echo str_repeat("=", 80) . "\n\n";
        
        foreach ($this->foundFiles as $index => $fileInfo) {
            $rank = $index + 1;
            $stars = str_repeat("★", min(5, ceil($fileInfo['score'] / 20)));
            
            echo "🏆 第 {$rank} 名 - 评分: {$fileInfo['score']} {$stars}\n";
            echo "📁 路径: {$fileInfo['path']}\n";
            echo "📊 大小: " . $this->formatSize($fileInfo['size']) . "\n";
            echo "📝 匹配特征:\n";
            
            foreach ($fileInfo['reasons'] as $reason) {
                echo "   ✓ {$reason}\n";
            }
            
            // 显示文件预览
            $preview = $this->getFilePreview($fileInfo['full_path']);
            if ($preview) {
                echo "👀 文件预览:\n";
                echo $preview . "\n";
            }
            
            echo str_repeat("-", 60) . "\n\n";
        }
    }
    
    /**
     * 生成总结报告
     */
    private function generateSummary() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📊 查找总结\n";
        echo str_repeat("=", 80) . "\n";
        
        $topFile = $this->foundFiles[0];
        echo "🏅 最可能是首页的文件:\n";
        echo "   📍 {$topFile['path']}\n";
        echo "   ⭐ 评分: {$topFile['score']} (满分100)\n";
        echo "   📏 大小: " . $this->formatSize($topFile['size']) . "\n";
        
        echo "\n💡 建议访问以下URL测试:\n";
        $baseUrl = "http://" . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com');
        foreach (array_slice($this->foundFiles, 0, 3) as $file) {
            $urlPath = str_replace('./', '/', $file['path']);
            echo "   🌐 {$baseUrl}{$urlPath}\n";
        }
        
        echo "\n⏰ 查找完成时间: " . date('Y-m-d H:i:s') . "\n";
        echo "总共扫描文件: " . count($this->foundFiles) . " 个候选文件\n";
    }
    
    /**
     * 获取文件预览
     */
    private function getFilePreview($filePath, $lines = 8) {
        $content = @file_get_contents($filePath);
        if (!$content) return '';
        
        $linesArray = explode("\n", $content);
        $previewLines = array_slice($linesArray, 0, $lines);
        
        $preview = "";
        foreach ($previewLines as $i => $line) {
            $preview .= "   " . ($i + 1) . ". " . htmlspecialchars(substr($line, 0, 100)) . "\n";
        }
        
        if (count($linesArray) > $lines) {
            $preview .= "   ... (还有 " . (count($linesArray) - $lines) . " 行)\n";
        }
        
        return $preview;
    }
    
    /**
     * 格式化文件大小
     */
    private function formatSize($size) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        
        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}

// 专门针对您网站的特征查找
function findSpecificHomepages($dir = '.') {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "🔍 精确特征匹配查找\n";
    echo str_repeat("=", 80) . "\n";
    
    $specificPatterns = [
        'include.*head\.php' => '包含head.php',
        '\$DB->getRow' => 'DB查询getRow方法',
        'pre_class' => 'pre_class表',
        'SELECT.*FROM.*pre_view' => '查询pre_view表',
        'content-wrap' => 'content-wrap样式',
        'excerpt excerpt-1' => '文章列表样式',
        'carousel slide' => '轮播图组件'
    ];
    
    $found = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            $matches = [];
            $matchCount = 0;
            
            foreach ($specificPatterns as $pattern => $desc) {
                if (preg_match("/{$pattern}/", $content)) {
                    $matches[] = $desc;
                    $matchCount++;
                }
            }
            
            if ($matchCount >= 2) {
                $relativePath = str_replace($dir, '.', $file->getPathname());
                $found[] = [
                    'path' => $relativePath,
                    'matches' => $matches,
                    'count' => $matchCount
                ];
                
                echo "🎯 {$relativePath} - 匹配{$matchCount}个特征\n";
                foreach ($matches as $match) {
                    echo "   ✓ {$match}\n";
                }
                echo "\n";
            }
        }
    }
    
    return $found;
}

// 主程序执行
try {
    // 获取搜索目录
    $searchDir = isset($argv[1]) ? $argv[1] : (isset($_GET['dir']) ? $_GET['dir'] : '.');
    
    // 运行通用查找
    $finder = new HomepagePathFinder($searchDir);
    $finder->findAndOutputHomepages();
    
    // 运行精确特征查找
    findSpecificHomepages($searchDir);
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}

// 使用说明
echo "\n" . str_repeat("=", 80) . "\n";
echo "📖 使用说明\n";
echo str_repeat("=", 80) . "\n";
echo "命令行使用:\n";
echo "  php " . basename(__FILE__) . "                    # 当前目录查找\n";
echo "  php " . basename(__FILE__) . " /path/to/website   # 指定目录查找\n";
echo "  php " . basename(__FILE__) . " ~/www/wwwroot/m.993113.com/  # 您的网站目录\n\n";
echo "Web访问使用:\n";
echo "  http://your-domain.com/" . basename(__FILE__) . "\n";
echo "  http://your-domain.com/" . basename(__FILE__) . "?dir=./core\n";

?>
