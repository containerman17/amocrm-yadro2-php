<?php

/**
 * @author Marc Schloezer <marcschloezer@gmail.com>
 */
namespace solohin\yadro;

use \Psr\Log\AbstractLogger;
use \Psr\Log\LogLevel;

class Logger extends AbstractLogger
{
    const LEVEL_FATAL = 2;
    const LEVEL_WARNING = 1;
    const LEVEL_INFO = 0;

    /** @var string Папка, куда писать логи. */
    private $dir = '/var/log/yadro';

    /** @var string Название файла. */
    private $filename = null;

    /** Включён или выключен --verbose. По умолчанию выключен. */
    private $verbose = 0;

    function __construct($filename, $verbose, $dir)
    {
        if ($filename) $this->filename = $filename;

        $this->verbose = $verbose;
        $this->dir = $this->dir . '/' . $dir;
        $this->startCheck();
    }


    /**
     * Проверяет, всё ли готово.
     * @return void
     */
    private function startCheck()
    {
        if ($this->filename) {
            if (!is_dir($this->dir)) {
                mkdir($this->dir);
                chmod($this->dir, 0755);
            }
            if (!file_exists($this->dir . '/' . $this->filename)) {
                $this->info('Log file ' . $this->dir . '/' . $this->filename . ' not found, creating new file.');
                touch($this->dir . '/' . $this->filename);
                chmod($this->dir . '/' . $this->filename, 0644);
            }
            if (filesize($this->dir . '/' . $this->filename) > 2 * 1024 * 1024) {
                $this->info('Log file ' . $this->dir . '/' . $this->filename . ' is getting large, creating new file.');
                $i = 1;
                $seek = true;
                while ($seek) {
                    $filename2 = $this->dir . '/' . $this->filename . $i;
                    if (file_exists($filename2)) {
                        ++$i;
                    } else {
                        $seek = false;
                    }
                }
                rename($this->dir . '/' . $this->filename, $filename2);
                touch($this->dir . '/' . $this->filename);
                chmod($this->dir . '/' . $this->filename, 0644);
            }
            if (!is_writable($this->dir . '/' . $this->filename)) die($this->error('Cannot open ' . $this->dir . '/' . $this->filename . ', not writable.'));
        }
    }

    /**
     * Удаляет старые логи.
     * @return void
     */
    public function collectGarbage()
    {
        if ($this->filename && $this->dir) {
            $removed = false;
            $this->info('Starting garbage collection.', 1);
            $files = scandir($this->dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $deldate = strtotime(date('Y-m-d', strtotime('-6 days')));
                    if (filemtime($this->dir . '/' . $file) < $deldate) {
                        if (!is_dir($this->dir . '/' . $file)) {
                            $removed = true;
                            unlink($this->dir . '/' . $file);
                            $this->info('Removing ' . $this->dir . '/' . $file . '.', 1);
                        }
                    }
                }
            }
            if (!$removed) $this->info('Nothing to delete.', 1);
        } else {
            $this->info('Skipping garbage collection...', 1);
        }
    }

    /**
     * Экспортирует в лог дамп.
     * @param mixed $var
     * @return void
     */
    public function export($var)
    {
        if ($this->filename) {
            $debug_export = "[ " . date("Y-m-d H:i:s") . "] [EXPORT] " . var_export($var, true);
            if (!$handle = fopen($this->dir . '/' . $this->filename, 'a')) die($this->error('Cannot open ' . $this->this->dir . '/' . $this->filename . ', not writable.'));
            if (fwrite($handle, $debug_export . "\n") === FALSE) die($this->error('Cannot write to ' . $this->this->dir . '/' . $this->filename . '.'));
            fclose($handle);
        }
    }

    /*
     * Выводит информацию.
     * @param int $lvl
     * @param string $msg Сообщение.
     * @param int|bool $verbose Если 1/true, то лог выведется только при --verbose флаге.
     * @return void
     */
    public function log($lvl, $msg, array $context = null)
    {
        $debug_export = '';

        //преобразуем в старый логгер
        if (in_array($lvl, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR])) {
            $lvl = self::LEVEL_FATAL;
        } elseif ($lvl == LogLevel::WARNING) {
            $lvl = self::LEVEL_WARNING;
        } else {
            $lvl = self::LEVEL_INFO;
        }

        if ($lvl == self::LEVEL_INFO) {
            $debug_export = "[" . date("Y-m-d H:i:s") . "] [INFO] " . $msg . "\r\n";
        } elseif ($lvl == self::LEVEL_WARNING) {
            $debug_export = "[" . date("Y-m-d H:i:s") . "] [WARNING] " . $msg . "\r\n";
        } elseif ($lvl == self::LEVEL_FATAL) {
            $debug_export = "[" . date("Y-m-d H:i:s") . "] [FATAL] " . $msg . "\r\n";
            trigger_error($debug_export);
        }
        if ($this->filename) {
            if (!$handle = fopen($this->dir . '/' . $this->filename, 'a')) die($this->error('Cannot open ' . $this->dir . '/' . $this->filename . ', not writable.'));
            if (fwrite($handle, $debug_export) === FALSE) die($this->error('Cannot write to ' . $this->dir . '/' . $this->filename . '.'));
            fclose($handle);
        }
        if ($this->verbose) {
            /** --verbose включён, выводим все логи. */
            if ($lvl == self::LEVEL_INFO) echo "\e[32m[INFO]\e[39m " . $msg . "\r\n";
            if ($lvl == self::LEVEL_WARNING) echo "\e[33m[WARNING]\e[39m " . $msg . "\r\n";
            if ($lvl == self::LEVEL_FATAL) echo "\e[31m[FATAL]\e[39m " . $msg . "\r\n";

            if(!is_null($context)){
                $this->export($context);
            }
        }
    }
}
