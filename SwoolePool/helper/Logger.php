<?php
namespace Helpers;

class Logger
{
    /**
     * 打印数据
     * @param $message
     */
    public static function info($message)
    {
        self::prints($message, 'INFO');
    }

    /**
     * 打印错误
     * @param $message
     */
    public static function error($message)
    {
        self::prints($message, 'ERROR');
    }

    /**
     * @param $message
     * @param $str
     */
    private static function prints($message, $str)
    {
        $type = gettype($message);
        switch ($type) {
            case 'array':
            case 'object':
            case 'resource':
                $message = json_encode($message);
                break;
            default:
                $message = (string)$message;
                break;
        }
        echo '[' . date('Y-m-d H:i:s') . '] ' . ($str ? $str . ': ': '') . $message . PHP_EOL;
    }

    /**
     * 打印堆栈调试信息
     * @param $message
     */
    public static function trace($message)
    {
        $message = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $message . PHP_EOL;
        $traces = debug_backtrace();//堆栈信息
        //拼接堆栈信息
        foreach ($traces as $num => $trace) {
            if ($num == 0) {
                //第一次是调用本方法的信息，可以跳过
                $message .= '[TRACE]' . PHP_EOL;
                continue;
            }

            $message .= '#' . $num . ' ';
            $message .= isset($trace['file']) ? $trace['file'] : '';//存在文件名
            $message .= isset($trace['line']) ? '(' . $trace['line'] . '): ' : '';//存在问题行号
            $message .= $trace['class'] . $trace['type'] . $trace['function'];//拼接类调用方法字符串

            //如果有参数，拼接参数
            if (isset($trace['args'])) {
                $message .= '(';
                $args = [];
                foreach ($trace['args'] as $arg) {
                    $type = gettype($arg);
                    switch ($type) {
                        case 'string':
                            $args[] = "'" . $arg . "'";
                            break;
                        case 'array':
                        case 'object':
                        case 'resource':
                            $args[] = ucfirst($type);
                            break;
                        default:
                            $args[] = $arg;
                            break;
                    }
                }
                $message .= implode(', ', $args);
                $message .= ')';
            }

            $message .= PHP_EOL;
        }

        echo $message;
    }
}