<?php
/**
 * 日志记录工具类
 */

class Logger {
    
    /**
     * 获取日志目录路径
     */
    private static function getLogPath() {
        return __DIR__ . '/../logs';
    }
    
    /**
     * 获取错误日志文件路径
     */
    private static function getErrorLogPath() {
        return self::getLogPath() . '/error.log';
    }
    
    /**
     * 获取访问日志文件路径
     */
    private static function getAccessLogPath() {
        return self::getLogPath() . '/access.log';
    }
    
    /**
     * 记录信息日志
     */
    public static function info($message, $context = []) {
        self::log('info', $message, $context);
    }
    
    /**
     * 记录警告日志
     */
    public static function warning($message, $context = []) {
        self::log('warning', $message, $context);
    }
    
    /**
     * 记录错误日志
     */
    public static function error($message, $context = []) {
        self::log('error', $message, $context);
    }
    
    /**
     * 记录调试日志
     */
    public static function debug($message, $context = []) {
        // 暂时禁用调试日志，避免依赖配置
        // 如果需要调试日志，可以通过参数控制
        self::log('debug', $message, $context);
    }
    
    /**
     * 记录访问日志
     */
    public static function access($message, $context = []) {
        self::log('access', $message, $context, self::getAccessLogPath());
    }
    
    /**
     * 核心日志记录方法
     */
    private static function log($level, $message, $context = [], $logFile = null) {
        // 确定日志文件
        if ($logFile === null) {
            $logFile = self::getErrorLogPath();
        }
        
        // 确保日志目录存在
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // 格式化日志消息
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        
        // 添加上下文信息
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // 添加请求信息
        $requestInfo = sprintf(
            ' | IP:%s | UA:%s | URL:%s',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100),
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        );
        
        $logMessage = "[{$timestamp}] [{$levelUpper}] {$message}{$contextStr}{$requestInfo}" . PHP_EOL;
        
        // 写入日志文件
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // 如果是错误级别，同时输出到错误日志
        if ($level === 'error' && $logFile !== self::getErrorLogPath()) {
            file_put_contents(self::getErrorLogPath(), $logMessage, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * 记录请求日志
     */
    public static function logRequest($method, $url, $headers = [], $body = '') {
        $context = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body
        ];
        
        self::access("API请求", $context);
    }
    
    /**
     * 记录响应日志
     */
    public static function logResponse($statusCode, $response) {
        $context = [
            'status_code' => $statusCode,
            'response' => $response
        ];
        
        self::access("API响应", $context);
    }
    
    /**
     * 清理过期日志文件
     */
    public static function cleanupLogs($days = 30) {
        $logDir = self::getLogPath();
        if (!is_dir($logDir)) {
            return;
        }
        
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        
        $files = glob($logDir . '/*.log');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                self::info("删除过期日志文件", ['file' => $file]);
            }
        }
    }
    
    /**
     * 获取日志文件大小
     */
    public static function getLogFileSize($logFile = null) {
        if ($logFile === null) {
            $logFile = self::getErrorLogPath();
        }
        
        if (!file_exists($logFile)) {
            return 0;
        }
        
        return filesize($logFile);
    }
    
    /**
     * 获取最近的日志条目
     */
    public static function getRecentLogs($logFile = null, $lines = 100) {
        if ($logFile === null) {
            $logFile = self::getErrorLogPath();
        }
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $logs = [];
        $file = new SplFileObject($logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);
        
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $logs[] = $line;
            }
            $file->next();
        }
        
        return array_reverse($logs);
    }
    
    /**
     * 记录支付相关日志
     */
    public static function logPayment($action, $orderNo, $details = []) {
        $context = array_merge(['order_no' => $orderNo], $details);
        self::info("支付操作: {$action}", $context);
    }
    
    /**
     * 记录安全相关日志
     */
    public static function logSecurity($event, $details = []) {
        $context = array_merge([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], $details);
        
        self::warning("安全事件: {$event}", $context);
    }
} 