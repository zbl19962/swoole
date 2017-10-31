<?php
namespace Kernel;
use Itxiao6\Session\Session;
use Service\DB;
use Service\Exception;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;
use Itxiao6\Route\Route;
use Service\Http;
use Itxiao6\Route\Resources;
use Itxiao6\DebugBar\DebugBar;
use Itxiao6\DebugBar\DataCollector\ExceptionsCollector;
use Itxiao6\DebugBar\DataCollector\MessagesCollector;
use Itxiao6\DebugBar\DataCollector\PhpInfoCollector;
use Itxiao6\DebugBar\DataCollector\RequestDataCollector;
/**
* 框架核心类
*/
class Kernel
{
    /**
     * 类影射数组
     * @var array
     */
    protected static $class = [];
    /**
     * 是否已经加载过env
     * @var null | string
     */
    protected static $is_load_env = false;
    /**
     * 是否注册过autoload
     * @var bool
     */
    protected static $is_register_autoload = false;
    /**
     * 类的映射
     */
    public static function auto_load($class){
        if(count(self::$class)==0){
            # 加载配置文件
            self::$class = Config::get('class');
        }
        # 判断类是否存在
        if(isset(self::$class[$class])){
            # 获取类文件名
            $class_name = str_replace('\\','_',CLASS_PATH.self::$class[$class].'.php');
            # 判断缓存文件是否存在
            if(!file_exists($class_name)){
                # 写入文件
                file_put_contents($class_name,'<?php class '.$class.' extends '.self::$class[$class].'{ }');
            }
            # 引入映射类
            require($class_name);
        }
    }
    /**
     * 加载环境变量
     * */
    public static function load_env()
    {
        # 判断环境变量配置文件是否存在
        if(file_exists(ROOT_PATH.'.env')){
            # 自定义配置
            $f= fopen(ROOT_PATH.'.env',"r");
        }else{
            # 惯例配置
            $f= fopen(ROOT_PATH.'.env.example',"r");
        }
        # 循环行
        while (!feof($f))
        {
            $line = fgets($f);
            # 替换单个空格
            $line = preg_replace('! !','',$line);
            # 替换连续空格
            $line = preg_replace('! +!','',$line);
            # 替换制表符或空格
            $line = preg_replace('!\s+!','',$line);
            if((!strstr($line,'#')) && $line!=''){
                # 设置环境变量
                putenv(preg_replace('!\n$!','',$line));
            }
        }
        # 关闭文件
        fclose($f);
    }
    /**
     * 启动框架
     */
    public static function start($request = null,$response = null)
    {
        if(self::$is_load_env){
            # 加载环境变量
            self::load_env();
        }
        # 是否为Swoole
        if(defined('IS_SWOOLE') && IS_SWOOLE===true){

        }else{
            # 设置协议头
            header("Content-Type:text/html;charset=utf-8");
        }
        # 判断是否为调试模式
        if( DE_BUG === TRUE ){
            # 屏蔽提示错误和警告错误
            error_reporting(E_ALL ^ E_NOTICE);
            # 运行Whoops 构造器
            $whoops = new Run;
            # 判断是否为ajax
            if (\Whoops\Util\Misc::isAjaxRequest()) {
                # 输出json 格式的 错误信息
                $whoops->pushHandler(new \Whoops\Handler\JsonResponseHandler);
            }else{
                # 实例化错误页面类
                $PrettyPageHandler =  new PrettyPageHandler();
                # 设置错误页面标题
                $PrettyPageHandler -> setPageTitle('Minkernel-哎呀-出错了');
                # 输入页面 格式的 报错信息
                $whoops -> pushHandler($PrettyPageHandler);
            }
            $whoops->register();
            if(defined('IS_SWOOLE') && IS_SWOOLE===true){

            }else{
                # 禁止所有页面缓存
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . 'GMT');
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
            }
        }else{
            # 屏蔽所有错误
            error_reporting(0);
        }

        # 设置时区
        date_default_timezone_set(Config::get('sys','default_timezone'));

        # 判断是否开启了debugbar
        if(Config::get('sys','debugbar')) {
            # 定义全局变量
            global $debugbar;
            global $debugbarRenderer;
            global $database;

            # 启动DEBUGBAR
            $debugbar = new DebugBar();
            $debugbar->addCollector(new PhpInfoCollector());
            $debugbar->addCollector(new MessagesCollector('Time'));
            $debugbar->addCollector(new MessagesCollector('Request'));
            $debugbar->addCollector(new MessagesCollector('Session'));
            $debugbar->addCollector(new MessagesCollector('Database'));
            $debugbar->addCollector(new MessagesCollector('Application'));
            $debugbar->addCollector(new MessagesCollector('View'));
            $debugbar->addCollector(new RequestDataCollector());

            $debugbarRenderer = $debugbar->getJavascriptRenderer();
        }
        # 注册类映射方法
        spl_autoload_register('Kernel\Kernel::auto_load');
        # 设置请求头
        Http::set_request($request,$response);

        # 判断缓存主目录是否存在
        if(!is_dir(ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR)){
            # 递归创建目录
            mkdir(ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR,0777,true);
        }
        if(!(defined('CACHE_DATA') && CACHE_DATA != '')){
            # 数据缓存目录
            define('CACHE_DATA',ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR);
        }
        # 检查目录是否存在
        if(!is_dir(CACHE_DATA)){
            # 递归创建目录
            mkdir(CACHE_DATA,0777,true);
        }
        if(!(defined('CLASS_PATH') && CLASS_PATH != '')){
            # 类映射缓存目录
            define('CLASS_PATH',ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR);
        }
        # 检查目录是否存在
        if(!is_dir(CLASS_PATH)){
            # 递归创建目录
            mkdir(CLASS_PATH,0777,true);
        }
        if(!(defined('CACHE_LOG') && CACHE_LOG != '')){
            # 日志文件缓存路径
            define('CACHE_LOG',ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR.'log'.DIRECTORY_SEPARATOR);
        }
        # 检查目录是否存在
        if(!is_dir(CACHE_LOG)){
            # 递归创建目录
            mkdir(CACHE_LOG,0777,true);
        }
        if(!(defined('CACHE_SESSION') && CACHE_SESSION != '')){
            # 会话文件缓存路径
            define('CACHE_SESSION',ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR.'session'.DIRECTORY_SEPARATOR);
        }
        # 检查目录是否存在
        if(!is_dir(CACHE_SESSION)){
            # 递归创建目录
            mkdir(CACHE_SESSION,0777,true);
        }
        # 检查目录是否存在
        if(!is_dir(UPLOAD_TMP_DIR)){
            # 递归创建目录
            mkdir(UPLOAD_TMP_DIR,0777,true);
        }
        if(!(defined('CACHE_VIEW') && CACHE_VIEW != '')){
            # 模板编译缓存目录
            define('CACHE_VIEW',ROOT_PATH.'runtime'.DIRECTORY_SEPARATOR.'view'.DIRECTORY_SEPARATOR);
        }
        # 检查目录是否存在
        if(!is_dir(CACHE_VIEW)){
            # 递归创建目录
            mkdir(CACHE_VIEW,0777,true);
        }
        if(!(defined('IS_WIN') && IS_WIN != '')){
            # 是否为WEN 环境
            define('IS_WIN',strstr(PHP_OS, 'WIN') ? 1 : 0 );
        }

        # 获取API模式传入的参数
        $param_arr = getopt('U:');
        # 判断是否为API模式
        if($param_arr['U']){
            $_SERVER['REDIRECT_URL'] = $param_arr['U'];
            $_SERVER['PHP_SELF'] = $param_arr['U'];
            $_SERVER['QUERY_STRING'] = $param_arr['U'];
        }
        # 设置资源路由
        Route::set_resources_driver(
            Route::get_resources_driver() -> set_folder(Config::get('abstract')) -> set_file_type([
                '.js'=>'application/javascript',
                '.css'=>'text/css',
                '.jpg'=>'image/jpg',
                '.png'=>'image/png',
                '.jpeg'=>'image/jpeg',
                '.svg'=>'image/svg+xml',
            ])
        );
        # 设置url 分隔符
        Route::set_key_word(Config::get('sys','url_split'));
        try{
            # 加载路由
            Route::init(function($app,$controller,$action){
                $view_path = Config::get('sys','view_path');
                if(!in_array(ROOT_PATH.'app'.DIRECTORY_SEPARATOR.$app.DIRECTORY_SEPARATOR.'View',$view_path)){
                    $view_path[] = ROOT_PATH.'app'.DIRECTORY_SEPARATOR.$app.DIRECTORY_SEPARATOR.'View';
                    Config::set('sys',
                        $view_path
                        ,'view_path');
                }
            });
        }catch (\Exception $exception){
            var_dump($exception -> getMessage());
            # 页面找不到
            Http::send_http_status(404);
        }

    }
}