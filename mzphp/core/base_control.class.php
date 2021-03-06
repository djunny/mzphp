<?php

/**
 * Class base_control
 */
class base_control
{

    /**
     * @var string run path for undefined method
     */
    public $run_path = '';

    /**
     * config for current
     *
     * @var array
     */
    public $conf = array();

    /**
     * cache conf for cache page
     *
     * @var null
     */
    private $cache_conf = NULL;

    /**
     * @param $conf
     */
    function __construct(&$conf) {
        $this->conf = &$conf;
        // bind control for global
        core::$control = $this;
    }

    /**
     * @param $var
     *
     * @return template
     * @throws Exception
     */
    public function __get($var) {
        // class_exists 会自动去取 spl_autoload_register 注册的方法
        if (class_exists($var)) {
            $this->$var = new $var();
            return $this->$var;
        } else {
            // 如果没有取到 model
            // 读取 $conf['model_map'] 中配置了对应的 model 映射
            $this->$var = core::model($this->conf, $var);
            if (!$this->$var) {
                throw new Exception('Not Found Model:' . $var);
            } else {
                return $this->$var;
            }
        }
    }

    /**
     * @param $method
     * @param $args
     *
     * @throws Exception
     */
    public function __call($method, $args) {
        // get module path file
        $file = $this->get_run_file($method);
        if (!$file || !is_file($file)) {
            $message = 'base_control.class.php Not implement method：' . $method . ': (' . var_export($args, 1) . ')';
            throw new Exception($message);
        } else {
            include $file;
            return true;
        }
    }

    /**
     * get cli run file
     *
     * @param $method
     *
     * @return string
     */
    function get_run_file($method) {
        $method = substr($method, 3);
        if ($this->run_path) {
            return $this->run_path . $method . '.php';
        } else {
            return '';
        }
    }


    /**
     * @param string $template
     * @param string $make_file
     * @param string $charset
     */
    public function show($template = '', $make_file = '', $charset = '', $compress = 6, $by_return = 0) {
        $template = $template ? $template : core::R('c') . '_' . core::R('a') . '.htm';
        if ((!$make_file && $make_file != 'NO') && $this->cache_conf) {
            // set template render content save to cache
            $make_file = 'CACHE$' . $this->cache_conf['key'] . '$' . $this->cache_conf['time'];
        }
        return VI::display($this, $template, $make_file, $charset, $compress, $by_return);
    }


    /**
     * set cache page
     *
     * @param string $cache_key  current page cache key
     * @param int    $cache_time current page cache time
     *
     * @return mixed
     */
    public function cache_page($cache_key, $cache_time = 60) {
        if ($cache_time < 1) {
            return false;
        }
        // add prefix
        $cache_key = 'p:' . $cache_key;
        // query for cache page is exists
        $exists = CACHE::get($cache_key);
        // set cache header for browser
        // cache exists
        if ($exists !== false && !is_null($exists)) {
            // get expire and return 304 Not Modified header
            if (isset($exists['time'])) {
                // todo remove
                $file_time = $exists['time'];
                $body      = $exists['body'];
            } else {
                list($file_time, $body) = $exists;
            }
            unset($exists);
            $file_time = $file_time - $cache_time;
            $gmt_mtime = gmdate('D, d M Y H:i:s', $file_time) . ' GMT';
            $this->set_cache_header($cache_time, $file_time);
            // check client has modified cache
            if (stripos($_SERVER['HTTP_IF_MODIFIED_SINCE'], $gmt_mtime) !== false) {
                header($_SERVER['SERVER_PROTOCOL'] . " 304 Not Modified", true, 304);
                exit;
            }
            // check browser support gzip encoding
            // because template is default gzencode for content
            if (stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
                header('Content-Encoding: gzip');
                echo $body;
            } else {
                // gzdecode content
                echo gzinflate(substr($body, 0x0a, -8));
            }
            exit;
        }
        $this->set_cache_header($cache_time);
        // save cache conf for show bind template
        $this->cache_conf = array(
            'key'  => $cache_key,
            'time' => $_SERVER['time'] + $cache_time,
        );
        return $this->cache_conf;
    }

    /**
     * set cache header for browser
     *
     * @param int $cache_time
     * @param int $time
     */
    public function set_cache_header($cache_time = 3600, $time = 0) {
        // 设置客户端缓存有效时间
        $time      = $time ? $time : $_SERVER['time'];
        $gmt_mtime = gmdate('D, d M Y H:i:s', $time) . ' GMT';
        //header("Date: " . $gmt_mtime);
        header("Expires: " . gmdate("D, d M Y H:i:s", $time + $cache_time) . " GMT");
        header("Cache-Control: max-age=" . $cache_time);
        // 设置最后修改时间
        header("Last-Modified: " . $gmt_mtime);
    }

    /**
     * 404 Page
     *
     * @param string $message
     */
    function on_404($message = '') {
        die('PageNotFound' . (DEBUG ? ':' . $message : ''));
    }
}

?>