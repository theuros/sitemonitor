<?php
/*
* Author: Uroš Likar
* Email: uros.likar@gmail.com
* December 2018
*/
class Sitemonitor {

    public $pageCfg;
    public $modx;

    public $tpl;
    public $tplRow;
    public $tplExtRow;

    private $key = null;

    public $config = array();
    public function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;
        $basePath = $this->modx->getOption('sitemonitor.core_path',$config,$this->modx->getOption('core_path').'components/sitemonitor/');
        $assetsUrl = $this->modx->getOption('sitemonitor.assets_url',$config,$this->modx->getOption('assets_url').'components/sitemonitor/');
        $this->config = array_merge(array(
            'basePath' => $basePath,
            'corePath' => $basePath,
            'modelPath' => $basePath,
            //'processorsPath' => $basePath.'processors/',
            //'templatesPath' => $basePath.'templates/',
            'chunksPath' => $basePath.'elements/chunks/',
            //'jsUrl' => $assetsUrl.'js/',
            //'cssUrl' => $assetsUrl.'css/',
            'assetsUrl' => $assetsUrl,
            //'connectorUrl' => $assetsUrl.'connector.php',
        ),$config);
        $this->modx->addPackage('sitemonitor',$this->config['modelPath']);
    }

    public function getChunk($name,$properties = array()) {
        $chunk = null;
        
        $folder = '';
        if (strpos($name, '/') !== false) {
            $tmp = explode('/',$name);
            $name = end($tmp);
            array_pop($tmp);
            $folder = implode('/',$tmp).'/';
        }

        if (!isset($this->chunks[$name])) {
            $chunk = $this->modx->getObject('modChunk',array('name' => $name));
            if (empty($chunk) || !is_object($chunk)) {
                $chunk = $this->_getTplChunk($folder,$name);
                if ($chunk == false) return false;
            }
            $this->chunks[$name] = $chunk->getContent();
        } else {
            $o = $this->chunks[$name];
            $chunk = $this->modx->newObject('modChunk');
            $chunk->setContent($o);
        }
        $chunk->setCacheable(false);
        return $chunk->process($properties);
    }
    
    private function _getTplChunk($folder,$name,$postfix = '.chunk.tpl') {
        $chunk = false;
        $f = $this->config['chunksPath'].$folder.strtolower($name).$postfix;
        if (file_exists($f)) {
            $o = file_get_contents($f);
            $chunk = $this->modx->newObject('modChunk');
            $chunk->set('name',$name);
            $chunk->setContent($o);
        }
        return $chunk;
    }

    /* ********************************** */


    public function setKey($key){
        $this->key = sha1($key).md5($key);
    }
    
    public function getData($add){
    
        //$ip = isset($_SERVER['HTTP_CLIENT_IP'])?$_SERVER['HTTP_CLIENT_IP']:isset($_SERVER['HTTP_X_FORWARDED_FOR'])?$_SERVER['HTTP_X_FORWARDED_FOR']:$_SERVER['REMOTE_ADDR'];

        $data = [
            'distro' => $this->modx->getOption("settings_distro"),
            'version' => $this->modx->getOption("settings_version"),
            'poweredby_header' => $this->modx->getOption("send_poweredby_header"),
            'site_name' => $this->modx->getOption("site_name"),
            'log_size' => $this->getErrorLogSize(),            
            'log_preview' => $this->tail($this->modx->getOption("core_path").'cache/logs/error.log',5),
            'extensionsList' => $this->getExtensionsList()
        ];
        $data['extensionsCount'] = count($data['extensionsList']);

        if($add){
            $addArray = json_decode($add);
            if(json_last_error() == JSON_ERROR_NONE){
                foreach($addArray as $k => $v){ $data[$k] = $v; }
            } else {
                $data['ERROR'] = '&add => Json not valid';
            }
        }

       //return json_encode($data);

        if($this->key){            
            $ivlen = openssl_cipher_iv_length('AES-256-CBC');
            $iv = openssl_random_pseudo_bytes($ivlen);
            $data = openssl_encrypt(
                json_encode($data),
                'AES-256-CBC',
                $this->key,
                0,
                $iv
            );            
            return json_encode([
                'crypt' => $data,
                'hash' => bin2hex($iv)
            ]);
        }else{
            return json_encode($data);
        }

    }

    public function readRow($url){
        $data = [];
        if($url) {            
            $data = $this->getDataFromUrl($url);
            $url = explode("/",$url);
            $data['url'] = $url[0].'//'.$url[2];
            
            $data['extensions']='';
            
            if(is_array($data['extensionsList'])){              
                
                foreach ($data['extensionsList'] as $v) {
                    
                    if($this->tplExtRow)
                        $data['extensions'] .= $this->modx->getChunk($this->tplExtRow,$v);
                    else
                        $data['extensions'] .= $this->getChunk('sm_tpl_ext',$v);
                    
                }

            }

        }

        if($this->tplRow)
            return  $this->modx->getChunk($this->tplRow,$data);
        else
            return  $this->getChunk('sm_tpl_row',$data);

    }

    public function getDataFromUrl($url){

        $url = filter_var($url, FILTER_SANITIZE_URL);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);

        //$error_msg = curl_error($ch); echo $error_msg; die;

        curl_close($ch);

        $data = json_decode($data,true);
        
        if($data['crypt']){
            $json = openssl_decrypt(
                $data['crypt'],
                'AES-256-CBC',
                $this->key,
                0,
                hex2bin($data['hash'])
            );
            $output = json_decode($json,true);
        } else {
            $output = $data;           
        }

        //echo '<pre>'.print_r($output,true).'</pre>'; die;
        return $output;
    }

    private function getExtensionsList(){
        
        $list = $this->modx->getCollection("transport.modTransportPackage");
        if(is_array($list)){
            $extras = [];
            foreach($list as $val){
                array_push($extras, [
                    'name'=> $val->get('package_name'),
                    'version'=>$val->get('version_major').'.'.$val->get('version_minor').'.'.$val->get('version_patch'),
                    'release'=>$val->get('release'),
                ]);            
            }
        }
        return $extras;
    }

    /* ********************************** */

    private function human_filesize($bytes, $decimals = 2) {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

    private function getErrorLogSize(){
        if(file_exists($this->modx->getOption("core_path").'cache/logs/error.log')){
            return $this->human_filesize(filesize($this->modx->getOption("core_path").'cache/logs/error.log'));
        }else{
            return '/';
        }

    }
    
    // Author of this tail function: https://gist.github.com/lorenzos/1711e81a9162320fde20     
    private function tail($filepath, $lines = 1, $adaptive = true) {        
        $f = @fopen($filepath, "rb");
        if ($f === false) return false;        
        if (!$adaptive) $buffer = 4096;
        else $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));        
        fseek($f, -1, SEEK_END);        
        if (fread($f, 1) != "\n") $lines -= 1;                
        $output = '';
        $chunk = '';        
        while (ftell($f) > 0 && $lines >= 0) {        
            $seek = min(ftell($f), $buffer);        
            fseek($f, -$seek, SEEK_CUR);        
            $output = ($chunk = fread($f, $seek)) . $output;        
            fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);        
            $lines -= substr_count($chunk, "\n");
        }        
        while ($lines++ < 0) {
            $output = substr($output, strpos($output, "\n") + 1);
        }        
        fclose($f);
        return trim($output);
    }

}