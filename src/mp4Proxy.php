<?php

/**
 * Created by PhpStorm.
 * User: crlt_
 * Date: 2018/1/15
 * Time: 上午11:57
 */


namespace Crlt_\Mp4Proxy;

class mp4Proxy
{
    /**
     * 通用的代理接口
     *
     * @param $url
     */
    function actionNormal($url)
    {
        $url = preg_replace("/offset=\d+/i", "", $url);
        if (isset($_SERVER['HTTP_RANGE'])) {
            list($name, $range) = explode("=", $_SERVER['HTTP_RANGE']);
            list($begin, $end) = explode("-", $range);
            if ($begin) {
                $url = $url . "&offset=" . $begin;
            }
        }

        $url = preg_replace('/(?:^[\'"]+|[\'"\/]+$)/', '', $url);
        $responseHeaders = array(
            "Content-type: video/mp4",
            "Accept-Ranges: bytes",
            "Access-Control-Allow-Origin: *",
            "Content-Disposition: inline",
        );
        $this->fetchF4v($url, $responseHeaders, 0);
    }

    private function fetchF4v($realUrl, $responseHeaders = array(), $redirectCount)
    {
        // 最多重定向五次
        if ($redirectCount > 5) {
            $this->echoAndEnd('too many redirects');
        }

        $url = $realUrl;

        if (!extension_loaded('sockets')) {
            $this->echoAndEnd('sockets extension not installed');
        }

        // 获得三个正则匹配
        preg_match('/http:\/\/([^\/\:]+)(\:\d{1,5})?(.*)/i', $url, $matches);
        $host = $matches[1];
        $port = $matches[2];
        $relativePath = $matches[3];

        if (!$matches) {
            $this->echoAndEnd('no matches!');
        }

        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!@socket_connect($sock, $host, $port ? intval(substr($port, 1)) : 80)) {
            $this->echoAndEnd('socket error');
        }

        $msg = 'GET ' . $relativePath . " HTTP/1.1\r\n" . 'Host: ' . $host . "\r\n" . 'Connection: Close' . "\r\n" . 'User-Agent: curl/7.19.7 (x86_64-redhat-linux-gnu) libcurl/7.19.7 NSS/3.13.1.0 zlib/1.2.3 libidn/1.18 libssh2/1.2.2' . "\r\n" . 'Accept: */*';
        // 如果原来的请求带有 Range，那么把这个 Range 原封不动带给目标服务器
        if (isset($_SERVER['HTTP_RANGE'])) {
            $msg .= "\r\n" . 'Range: ' . $_SERVER['HTTP_RANGE'];
        }
        $msg .= "\r\n\r\n";
        socket_write($sock, $msg);

        $headerProcessed = false;

        $readCount = 0;
        $contentLength = 0;
        // 读 socket 文件，每次读 1024 k
        while ($tmp = socket_read($sock, 1024000, PHP_BINARY_READ) and
            ($headerProcessed ? $readCount < $contentLength : true)) {

            // 如果已经处理过 HTTP Header，那么直接转发内容就好
            if ($headerProcessed) {
                $readCount += strlen($tmp);
                echo $tmp;
                continue;
            }

            // 分离出 HTTP Headers 和 Body
            list($originHeaders, $body) = explode("\r\n\r\n", $tmp);

            // 分割 HTTP Headers
            $headers = explode("\r\n", $originHeaders);

            // 看下有没有重定向
            foreach ($headers as $header) {
                preg_match('/Location: (.*)/i', $header, $locationMatch);
                // 有重定向，关闭 socket 并重新发起请求
                if ($locationMatch) {
                    @socket_close($sock);
                    $this->fetchF4v($locationMatch[1], $responseHeaders, ++$redirectCount);
                    return;
                }
            }

            // 没有重定向的请求了，确定这个 url 就是最终的视频 URL
            // 先写一部分响应头
            foreach ($responseHeaders as $responseHeader) {
                header($responseHeader);
            }

            foreach ($headers as $header) {
                preg_match('/Content-Length: (\d+)/i', $header, $match);
                if (!$match) {
                    continue;
                }
                header($header);

                $contentLength = $match[1];
                if (isset($_SERVER['HTTP_RANGE'])) {
                    header('HTTP/1.1 206 Partial Content');
                    list($_, $range) = explode("=", $_SERVER['HTTP_RANGE']);
                    list($begin, $end) = explode("-", $range);
                    if ($end == 0) {
                        $end = $contentLength - 1;
                    }
                    $totalLength = $contentLength + $begin;
                    header("Content-Range: bytes $begin-$end/$totalLength");
                }
                $headerProcessed = true;
                break;
            }
            $readCount += strlen($body);
            echo $body;
        }
        @socket_close($sock);
        exit(0);
    }

    private function echoAndEnd($message)
    {
        header('Content-Type: text/plain');
        header('Access-Control-Allow-Origin: *');
        echo $message;
        exit(0);
    }

}